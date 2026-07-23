<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le champ description à l'entité Plat (nécessaire pour le formulaire
 * "Ajouter / Modifier un plat" du tableau de bord, qui l'attendait déjà).
 */
final class Version20260721101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute la colonne description (TEXT, nullable) à la table plat.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plat ADD description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plat DROP description');
    }
}
