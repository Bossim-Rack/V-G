<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Suppression des tables "restaurant" et "picture" : elles venaient du projet
 * de départ (studi-restaurant) et ne sont utilisées nulle part dans Vite & Gourmand.
 */
final class Version20261029120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression des tables restaurant et picture (restes du projet de départ, inutilisées)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS picture');
        $this->addSql('DROP TABLE IF EXISTS restaurant');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("CREATE TABLE restaurant (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(32) NOT NULL, description LONGTEXT NOT NULL, am_opening_time JSON NOT NULL, pm_opening_time JSON NOT NULL, max_guest SMALLINT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE picture (id INT AUTO_INCREMENT NOT NULL, restaurant_id INT NOT NULL, title VARCHAR(128) NOT NULL, slug VARCHAR(128) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_16DB4F89B1E7706E (restaurant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE picture ADD CONSTRAINT FK_16DB4F89B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id)');
    }
}
