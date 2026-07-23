<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le champ photo_menu à l'entité Menu (même principe que
 * Plat::photo_plat : stockée/renvoyée en data URI base64).
 */
final class Version20260721110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute la colonne photo_menu (BLOB, nullable) à la table menu.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu ADD photo_menu LONGBLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu DROP photo_menu');
    }
}
