<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute 3 thèmes de base pour peupler la liste utilisée par le
 * formulaire "Ajouter / Modifier un menu" du tableau de bord.
 */
final class Version20260721111500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Insère 3 thèmes (Noël, Vacances, Mariage).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO theme (libelle) VALUES ('Noël')");
        $this->addSql("INSERT INTO theme (libelle) VALUES ('Vacances')");
        $this->addSql("INSERT INTO theme (libelle) VALUES ('Mariage')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM theme WHERE libelle IN ('Noël', 'Vacances', 'Mariage')");
    }
}
