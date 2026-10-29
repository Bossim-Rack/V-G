-- =====================================================================
--  Vite & Gourmand - Création de la base de données (MySQL 8)
-- =====================================================================
--  Ce script crée la base "vite_gourmand" et toutes ses tables.
--  Il est relançable : les tables existantes sont supprimées puis recréées
--  (ATTENTION : toutes les données de ces tables sont alors perdues).
--
--  Utilisation :
--    mysql -u root -p < database/01_creation_base.sql
--  puis, pour avoir des données de démonstration :
--    mysql -u root -p vite_gourmand < database/02_donnees_demo.sql
--
--  Les noms des index et des clés étrangères sont ceux générés par Doctrine :
--  la base créée par ce script est donc identique à celle des entités Symfony
--  (vérifiable avec : php bin/console doctrine:schema:validate).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS vite_gourmand
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE vite_gourmand;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Suppression dans l'ordre inverse des dépendances
DROP TABLE IF EXISTS avis;
DROP TABLE IF EXISTS commande_historique;
DROP TABLE IF EXISTS commande;
DROP TABLE IF EXISTS menu_regime;
DROP TABLE IF EXISTS menu_theme;
DROP TABLE IF EXISTS menu_plat;
DROP TABLE IF EXISTS plat_allergene;
DROP TABLE IF EXISTS menu;
DROP TABLE IF EXISTS plat;
DROP TABLE IF EXISTS allergene;
DROP TABLE IF EXISTS regime;
DROP TABLE IF EXISTS theme;
DROP TABLE IF EXISTS horraires;
DROP TABLE IF EXISTS user;
DROP TABLE IF EXISTS role;

SET FOREIGN_KEY_CHECKS = 1;


-- ---------------------------------------------------------------------
--  Utilisateurs et rôles
-- ---------------------------------------------------------------------

-- Les 3 rôles de l'application : Admin, Employé, Client
CREATE TABLE role (
    id      INT AUTO_INCREMENT NOT NULL,
    libelle VARCHAR(50) NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Comptes : clients (inscription), employés (créés par l'admin) et l'administrateur.
-- Le mot de passe est stocké hashé (bcrypt), jamais en clair.
-- Les jetons envoyés par mail (vérification, mot de passe oublié) sont stockés
-- sous forme d'empreinte SHA-256 : une fuite de la base ne permet pas de les réutiliser.
CREATE TABLE user (
    id                            INT AUTO_INCREMENT NOT NULL,
    role_id                       INT NOT NULL,
    first_name                    VARCHAR(32) NOT NULL,
    last_name                     VARCHAR(64) NOT NULL,
    phone_number                  VARCHAR(10) DEFAULT NULL,
    allergy                       VARCHAR(255) DEFAULT NULL,
    adresse                       VARCHAR(255) DEFAULT NULL,
    email                         VARCHAR(180) NOT NULL,
    api_token                     VARCHAR(255) NOT NULL,
    roles                         JSON NOT NULL,
    created_at                    DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at                    DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    password                      VARCHAR(255) NOT NULL,
    is_verified                   TINYINT(1) DEFAULT 0 NOT NULL,
    is_active                     TINYINT(1) DEFAULT 1 NOT NULL,
    email_verification_token_hash VARCHAR(64) DEFAULT NULL,
    email_verification_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    reset_password_token_hash     VARCHAR(64) DEFAULT NULL,
    reset_password_expires_at     DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX UNIQ_8D93D649E7927C74 (email),
    UNIQUE INDEX UNIQ_8D93D6497BA2F5EB (api_token),
    INDEX IDX_8D93D649D60322AC (role_id),
    PRIMARY KEY (id),
    CONSTRAINT FK_8D93D649D60322AC FOREIGN KEY (role_id) REFERENCES role (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;


-- ---------------------------------------------------------------------
--  Horaires (affichés dans le pied de page)
-- ---------------------------------------------------------------------

CREATE TABLE horraires (
    id    INT AUTO_INCREMENT NOT NULL,
    jour  VARCHAR(10) NOT NULL,            -- Lundi ... Dimanche
    heure VARCHAR(20) NOT NULL,            -- ex : "09:00 - 18:00"
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;


-- ---------------------------------------------------------------------
--  Menus, plats et leurs caractéristiques
-- ---------------------------------------------------------------------

CREATE TABLE theme (
    id      INT AUTO_INCREMENT NOT NULL,
    libelle VARCHAR(100) NOT NULL,         -- Noël, Pâques, Classique, Événement...
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE regime (
    id      INT AUTO_INCREMENT NOT NULL,
    libelle VARCHAR(100) NOT NULL,         -- Classique, Végétarien, Vegan...
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE allergene (
    id      INT AUTO_INCREMENT NOT NULL,
    libelle VARCHAR(100) NOT NULL,         -- les 14 allergènes réglementaires
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Une entrée, un plat ou un dessert ; il peut être présent dans plusieurs menus
CREATE TABLE plat (
    id          INT AUTO_INCREMENT NOT NULL,
    title_plat  VARCHAR(100) NOT NULL,
    photo_plat  LONGBLOB DEFAULT NULL,
    description LONGTEXT DEFAULT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE menu (
    id                      INT AUTO_INCREMENT NOT NULL,
    title_menu              VARCHAR(50) NOT NULL,
    nombre_personne_minimum INT NOT NULL,
    prix_par_personne       NUMERIC(10, 2) NOT NULL,
    description             VARCHAR(100) DEFAULT NULL,
    quantite_restante       INT DEFAULT NULL,      -- stock : nombre de commandes encore possibles
    conditions              LONGTEXT DEFAULT NULL, -- délai de commande, conservation...
    photo_menu              LONGBLOB DEFAULT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Tables de liaison (relations plusieurs à plusieurs)
CREATE TABLE plat_allergene (
    plat_id      INT NOT NULL,
    allergene_id INT NOT NULL,
    INDEX IDX_6FA44BBFD73DB560 (plat_id),
    INDEX IDX_6FA44BBF4646AB2 (allergene_id),
    PRIMARY KEY (plat_id, allergene_id),
    CONSTRAINT FK_6FA44BBFD73DB560 FOREIGN KEY (plat_id) REFERENCES plat (id) ON DELETE CASCADE,
    CONSTRAINT FK_6FA44BBF4646AB2 FOREIGN KEY (allergene_id) REFERENCES allergene (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE menu_plat (
    menu_id INT NOT NULL,
    plat_id INT NOT NULL,
    INDEX IDX_E8775249CCD7E912 (menu_id),
    INDEX IDX_E8775249D73DB560 (plat_id),
    PRIMARY KEY (menu_id, plat_id),
    CONSTRAINT FK_E8775249CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE,
    CONSTRAINT FK_E8775249D73DB560 FOREIGN KEY (plat_id) REFERENCES plat (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE menu_theme (
    menu_id  INT NOT NULL,
    theme_id INT NOT NULL,
    INDEX IDX_6D9C46FCCD7E912 (menu_id),
    INDEX IDX_6D9C46F59027487 (theme_id),
    PRIMARY KEY (menu_id, theme_id),
    CONSTRAINT FK_6D9C46FCCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE,
    CONSTRAINT FK_6D9C46F59027487 FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE menu_regime (
    menu_id   INT NOT NULL,
    regime_id INT NOT NULL,
    INDEX IDX_79C112A4CCD7E912 (menu_id),
    INDEX IDX_79C112A435E7D534 (regime_id),
    PRIMARY KEY (menu_id, regime_id),
    CONSTRAINT FK_79C112A4CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE,
    CONSTRAINT FK_79C112A435E7D534 FOREIGN KEY (regime_id) REFERENCES regime (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;


-- ---------------------------------------------------------------------
--  Commandes, suivi et avis
-- ---------------------------------------------------------------------

-- prix_menu = prix par personne appliqué (réduction de 10 % incluse le cas échéant),
-- figé au moment de la commande pour ne pas changer si le menu est modifié ensuite.
CREATE TABLE commande (
    id                   INT AUTO_INCREMENT NOT NULL,
    menu_id              INT NOT NULL,
    user_id              INT NOT NULL,
    date_commande        DATE DEFAULT NULL,
    date_prestation      DATE DEFAULT NULL,
    heure_livraison      VARCHAR(10) DEFAULT NULL,
    adresse              VARCHAR(255) DEFAULT NULL,
    prix_menu            NUMERIC(10, 2) DEFAULT NULL,
    nombre_personne      INT DEFAULT NULL,
    prix_livraison       NUMERIC(10, 2) DEFAULT NULL,
    statut               VARCHAR(50) DEFAULT NULL,
    pret_materiel        TINYINT(1) DEFAULT NULL,
    restitution_materiel TINYINT(1) DEFAULT NULL,
    motif_annulation     VARCHAR(255) DEFAULT NULL,  -- obligatoire quand l'équipe annule
    mode_contact         VARCHAR(10) DEFAULT NULL,   -- "GSM" ou "Email" : contact du client avant modification
    INDEX IDX_6EEAA67DCCD7E912 (menu_id),
    INDEX IDX_6EEAA67DA76ED395 (user_id),
    PRIMARY KEY (id),
    CONSTRAINT FK_6EEAA67DCCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id),
    CONSTRAINT FK_6EEAA67DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Suivi de commande : une ligne par changement de statut (date et heure comprises)
CREATE TABLE commande_historique (
    id          INT AUTO_INCREMENT NOT NULL,
    commande_id INT NOT NULL,
    auteur_id   INT DEFAULT NULL,
    statut      VARCHAR(50) NOT NULL,
    commentaire VARCHAR(255) DEFAULT NULL,
    changed_at  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_757DF90A82EA2E54 (commande_id),
    INDEX IDX_757DF90A60BB6FE6 (auteur_id),
    PRIMARY KEY (id),
    CONSTRAINT FK_757DF90A82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE,
    CONSTRAINT FK_757DF90A60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES user (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Avis client : un seul avis par commande terminée, publié après validation
-- par un employé ("En attente", "Publié" ou "Refusé")
CREATE TABLE avis (
    id          INT AUTO_INCREMENT NOT NULL,
    user_id     INT NOT NULL,
    commande_id INT DEFAULT NULL,
    note        INT NOT NULL,                  -- de 1 à 5
    description VARCHAR(255) DEFAULT NULL,
    statut      VARCHAR(20) NOT NULL,
    INDEX IDX_8F91ABF0A76ED395 (user_id),
    UNIQUE INDEX UNIQ_8F91ABF082EA2E54 (commande_id),
    PRIMARY KEY (id),
    CONSTRAINT FK_8F91ABF0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id),
    CONSTRAINT FK_8F91ABF082EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
