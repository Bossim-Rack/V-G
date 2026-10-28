<?php

namespace App\Command;

use App\Entity\Allergene;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\AllergeneRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * php bin/console app:init-database
 * Prépare une base vide pour la mise en ligne : crée les tables, les 3 rôles, les 14 allergènes et le compte administrateur.
 * Sans danger si la base existe déjà : on ne recrée rien de ce qui est présent.
 */
#[AsCommand(name: 'app:init-database', description: 'Crée les tables, les rôles et le compte admin sur une base vide')]
class InitDatabaseCommand extends Command
{
    private const ROLES = ['Admin', 'Employé', 'Client'];

    // Les 14 allergènes à déclaration obligatoire (règlement européen INCO)
    private const ALLERGENES = [
        'Gluten', 'Crustacés', 'Œufs', 'Poissons', 'Arachides', 'Soja', 'Lait',
        'Fruits à coque', 'Céleri', 'Moutarde', 'Graines de sésame', 'Sulfites', 'Lupin', 'Mollusques',
    ];

    public function __construct(
        private EntityManagerInterface $manager,
        private RoleRepository $roleRepository,
        private AllergeneRepository $allergeneRepository,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. Tables : sur une base vide on crée tout d'un coup à partir des entités,
        // puis on marque les migrations comme déjà faites (les anciennes ne peuvent pas être rejouées depuis zéro).
        $tables = $this->manager->getConnection()->createSchemaManager()->listTableNames();
        if (!in_array('user', $tables, true)) {
            $io->section('Base vide : création des tables');
            (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());

            $this->getApplication()->find('doctrine:migrations:sync-metadata-storage')->run(new ArrayInput([]), $output);
            $marquage = $this->getApplication()->find('doctrine:migrations:version');
            $parametres = new ArrayInput(['--add' => true, '--all' => true]);
            $parametres->setInteractive(false);
            $marquage->run($parametres, $output);
        } else {
            $io->note('Les tables existent déjà : rien à créer.');
        }

        // 2. Les 3 rôles
        foreach (self::ROLES as $libelle) {
            if (!$this->roleRepository->findOneBy(['libelle' => $libelle])) {
                $this->manager->persist((new Role())->setLibelle($libelle));
                $io->text('Rôle créé : ' . $libelle);
            }
        }
        $this->manager->flush();

        // 3. Les allergènes
        foreach (self::ALLERGENES as $libelle) {
            if (!$this->allergeneRepository->findOneBy(['libelle' => $libelle])) {
                $this->manager->persist((new Allergene())->setLibelle($libelle));
                $io->text('Allergène créé : ' . $libelle);
            }
        }
        $this->manager->flush();

        // 4. Le compte administrateur (José), à partir des variables ADMIN_EMAIL et ADMIN_PASSWORD
        $roleAdmin = $this->roleRepository->findOneBy(['libelle' => 'Admin']);
        $email = mb_strtolower(trim((string) ($_SERVER['ADMIN_EMAIL'] ?? $_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: '')));
        $motDePasse = (string) ($_SERVER['ADMIN_PASSWORD'] ?? $_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: '');

        if ($this->userRepository->count(['role' => $roleAdmin]) > 0) {
            $io->note('Un compte administrateur existe déjà.');
        } elseif ($email === '' || $motDePasse === '') {
            $io->warning('Aucun administrateur : définissez ADMIN_EMAIL et ADMIN_PASSWORD puis relancez la commande.');
        } else {
            $admin = (new User())
                ->setFirstName('José')
                ->setLastName('Gourmand')
                ->setEmail($email)
                ->setRole($roleAdmin)
                ->setIsVerified(true)
                ->setCreatedAt(new \DateTimeImmutable());
            $admin->setPassword($this->passwordHasher->hashPassword($admin, $motDePasse));
            $this->manager->persist($admin);
            $this->manager->flush();
            $io->text('Compte administrateur créé : ' . $email);
        }

        $io->success('Base prête.');

        return Command::SUCCESS;
    }
}
