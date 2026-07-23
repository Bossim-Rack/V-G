<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lie les commandes à un menu et à un utilisateur : nécessaire pour le
 * bouton "Commander" de la page publique des menus (jusqu'ici, une
 * commande n'était rattachée à rien).
 */
final class Version20260722090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute menu_id et user_id (NOT NULL, FK) à la table commande.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD menu_id INT NOT NULL, ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_COMMANDE_MENU FOREIGN KEY (menu_id) REFERENCES menu (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_COMMANDE_USER FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_COMMANDE_MENU ON commande (menu_id)');
        $this->addSql('CREATE INDEX IDX_COMMANDE_USER ON commande (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_COMMANDE_MENU');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_COMMANDE_USER');
        $this->addSql('DROP INDEX IDX_COMMANDE_MENU ON commande');
        $this->addSql('DROP INDEX IDX_COMMANDE_USER ON commande');
        $this->addSql('ALTER TABLE commande DROP menu_id, DROP user_id');
    }
}
