<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime le champ "regime" (résumé allergènes calculé automatiquement)
 * de la table menu : remplacé par la relation ManyToMany Menu <-> Regime
 * (régimes alimentaires choisis manuellement).
 */
final class Version20260722110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Supprime la colonne menu.regime (résumé allergènes).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu DROP regime');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE menu ADD regime VARCHAR(255) NOT NULL DEFAULT 'Aucun allergène particulier'");
    }
}
