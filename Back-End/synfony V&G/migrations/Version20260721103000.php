<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute 3 allergènes de base pour peupler la liste utilisée par le
 * formulaire "Ajouter / Modifier un plat" du tableau de bord.
 */
final class Version20260721103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Insère 3 allergènes (Gluten, Crustacés, Fruits à coque).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Gluten')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Crustacés')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Fruits à coque')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM allergene WHERE libelle IN ('Gluten', 'Crustacés', 'Fruits à coque')");
    }
}
