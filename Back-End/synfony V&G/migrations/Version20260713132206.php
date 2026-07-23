<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260713132206 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE menu_theme (menu_id INT NOT NULL, theme_id INT NOT NULL, INDEX IDX_6D9C46FCCD7E912 (menu_id), INDEX IDX_6D9C46F59027487 (theme_id), PRIMARY KEY(menu_id, theme_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE menu_theme ADD CONSTRAINT FK_6D9C46FCCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_theme ADD CONSTRAINT FK_6D9C46F59027487 FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_theme DROP FOREIGN KEY FK_6D9C46FCCD7E912');
        $this->addSql('ALTER TABLE menu_theme DROP FOREIGN KEY FK_6D9C46F59027487');
        $this->addSql('DROP TABLE menu_theme');
    }
}
