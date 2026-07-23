<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la colonne "adresse" :
 * - sur user (facultative à l'inscription, modifiable dans le profil,
 *   sert à pré-remplir l'adresse de livraison lors d'une commande) ;
 * - sur commande (adresse de livraison de la commande, obligatoire côté
 *   application au moment de la création, gérée en nullable au niveau
 *   du schéma comme le reste des colonnes de cette table).
 */
final class Version20260722120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute la colonne adresse à user et à commande.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD adresse VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE commande ADD adresse VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP adresse');
        $this->addSql('ALTER TABLE commande DROP adresse');
    }
}
