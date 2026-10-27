<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Emails automatiques, sécurité et suivi des commandes :
 *  - user : confirmation d'email, désactivation de compte, jetons (hashés) des liens envoyés par mail
 *  - menu : conditions du menu
 *  - commande : motif d'annulation + mode de contact du client, historique des statuts (suivi)
 *  - avis : lien vers la commande notée
 */
final class Version20261026100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Vérification d'email, reset de mot de passe par lien, désactivation de compte, conditions des menus, suivi et annulation des commandes, avis liés aux commandes";
    }

    public function up(Schema $schema): void
    {
        // --- user ---
        $this->addSql("ALTER TABLE user ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL, ADD is_active TINYINT(1) DEFAULT 1 NOT NULL, ADD email_verification_token_hash VARCHAR(64) DEFAULT NULL, ADD email_verification_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD reset_password_token_hash VARCHAR(64) DEFAULT NULL, ADD reset_password_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        // Les comptes existants avant cette mise à jour sont considérés comme confirmés.
        $this->addSql('UPDATE user SET is_verified = 1');

        // --- menu ---
        $this->addSql('ALTER TABLE menu ADD conditions LONGTEXT DEFAULT NULL');

        // --- commande ---
        $this->addSql('ALTER TABLE commande ADD motif_annulation VARCHAR(255) DEFAULT NULL, ADD mode_contact VARCHAR(10) DEFAULT NULL');
        // Ancien libellé -> libellé du sujet.
        $this->addSql("UPDATE commande SET statut = 'Accepté' WHERE statut = 'Confirmée'");

        $this->addSql("CREATE TABLE commande_historique (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, auteur_id INT DEFAULT NULL, statut VARCHAR(50) NOT NULL, commentaire VARCHAR(255) DEFAULT NULL, changed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_757DF90A82EA2E54 (commande_id), INDEX IDX_757DF90A60BB6FE6 (auteur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE commande_historique ADD CONSTRAINT FK_757DF90A82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commande_historique ADD CONSTRAINT FK_757DF90A60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES user (id) ON DELETE SET NULL');
        // Les commandes existantes démarrent leur suivi avec leur statut actuel.
        $this->addSql("INSERT INTO commande_historique (commande_id, statut, commentaire, changed_at) SELECT id, COALESCE(statut, 'En attente'), 'Statut au moment de la mise en place du suivi', COALESCE(date_commande, NOW()) FROM commande");

        // --- avis ---
        $this->addSql('ALTER TABLE avis ADD commande_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF082EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8F91ABF082EA2E54 ON avis (commande_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF082EA2E54');
        $this->addSql('DROP INDEX UNIQ_8F91ABF082EA2E54 ON avis');
        $this->addSql('ALTER TABLE avis DROP commande_id');

        $this->addSql('ALTER TABLE commande_historique DROP FOREIGN KEY FK_757DF90A82EA2E54');
        $this->addSql('ALTER TABLE commande_historique DROP FOREIGN KEY FK_757DF90A60BB6FE6');
        $this->addSql('DROP TABLE commande_historique');
        $this->addSql("UPDATE commande SET statut = 'Confirmée' WHERE statut = 'Accepté'");
        $this->addSql('ALTER TABLE commande DROP motif_annulation, DROP mode_contact');

        $this->addSql('ALTER TABLE menu DROP conditions');

        $this->addSql('ALTER TABLE user DROP is_verified, DROP is_active, DROP email_verification_token_hash, DROP email_verification_expires_at, DROP reset_password_token_hash, DROP reset_password_expires_at');
    }
}
