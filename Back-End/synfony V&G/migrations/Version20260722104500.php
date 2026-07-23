<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la relation ManyToMany Menu <-> Regime (sélection manuelle de
 * régimes alimentaires depuis le tableau de bord, avec création à la volée
 * si le régime cherché n'existe pas — même principe que Menu <-> Theme).
 */
final class Version20260722104500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Crée la table de liaison menu_regime (ManyToMany Menu <-> Regime).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE menu_regime (menu_id INT NOT NULL, regime_id INT NOT NULL, INDEX IDX_MENU_REGIME_MENU (menu_id), INDEX IDX_MENU_REGIME_REGIME (regime_id), PRIMARY KEY(menu_id, regime_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE menu_regime ADD CONSTRAINT FK_MENU_REGIME_MENU FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_regime ADD CONSTRAINT FK_MENU_REGIME_REGIME FOREIGN KEY (regime_id) REFERENCES regime (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_regime DROP FOREIGN KEY FK_MENU_REGIME_MENU');
        $this->addSql('ALTER TABLE menu_regime DROP FOREIGN KEY FK_MENU_REGIME_REGIME');
        $this->addSql('DROP TABLE menu_regime');
    }
}
