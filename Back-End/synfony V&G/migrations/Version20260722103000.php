<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute 3 régimes de base (table "regime", entité Regime/RegimeController
 * déjà en place mais jamais peuplée).
 */
final class Version20260722103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Insère 3 régimes (Végétarien, Sans gluten, Végétalien).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO regime (libelle) VALUES ('Végétarien')");
        $this->addSql("INSERT INTO regime (libelle) VALUES ('Sans gluten')");
        $this->addSql("INSERT INTO regime (libelle) VALUES ('Végétalien')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM regime WHERE libelle IN ('Végétarien', 'Sans gluten', 'Végétalien')");
    }
}
