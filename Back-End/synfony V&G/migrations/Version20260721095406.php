<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fait évoluer avis.statut d'un booléen (validé / non validé) vers un statut
 * à trois états : "En attente" (défaut), "Publié", "Refusé".
 */
final class Version20260721095406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Transforme avis.statut de TINYINT(1) vers VARCHAR(20) (En attente / Publié / Refusé)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE avis CHANGE statut statut VARCHAR(20) NOT NULL');
        $this->addSql("UPDATE avis SET statut = 'Publié' WHERE statut = '1'");
        $this->addSql("UPDATE avis SET statut = 'En attente' WHERE statut = '0'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE avis SET statut = '1' WHERE statut = 'Publié'");
        $this->addSql("UPDATE avis SET statut = '0' WHERE statut IN ('En attente', 'Refusé')");
        $this->addSql('ALTER TABLE avis CHANGE statut statut TINYINT(1) NOT NULL');
    }
}
