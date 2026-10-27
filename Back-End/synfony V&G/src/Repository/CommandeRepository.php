<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 *
 * @method Commande|null find($id, $lockMode = null, $lockVersion = null)
 * @method Commande|null findOneBy(array $criteria, array $orderBy = null)
 * @method Commande[]    findAll()
 * @method Commande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Filtre des commandes pour l'espace employé : par statut et/ou par client
     * (recherche dans le prénom, le nom ou l'email). Les plus récentes d'abord.
     *
     * @return Commande[]
     */
    public function rechercher(?string $statut, ?string $client): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.user', 'u')
            ->addSelect('u')
            ->leftJoin('c.menu', 'm')
            ->addSelect('m')
            ->orderBy('c.id', 'DESC');

        if ($statut) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        if ($client) {
            $qb->andWhere("(LOWER(u.firstName) LIKE :client OR LOWER(u.lastName) LIKE :client OR LOWER(u.email) LIKE :client OR LOWER(CONCAT(u.firstName, ' ', u.lastName)) LIKE :client)")
                ->setParameter('client', '%' . mb_strtolower($client) . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
