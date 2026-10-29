-- =====================================================================
--  Vite & Gourmand - Données de démonstration
-- =====================================================================
--  À lancer APRÈS 01_creation_base.sql :
--    mysql -u root -p vite_gourmand < database/02_donnees_demo.sql
--
--  Contenu : rôles, comptes de démonstration, horaires, allergènes, régimes,
--  thèmes, plats, menus, commandes (avec leur suivi) et avis clients.
--
--  Comptes de démonstration (mots de passe connus de tous : réservés au
--  développement local, NE PAS importer ce fichier sur une base en production) :
--
--    Rôle            Email                                   Mot de passe
--    Administrateur  admin@vite-gourmand.test                Admin-Demo2026!
--    Employé         employe@vite-gourmand.test              Employe-Demo2026!
--    Employé désactivé (connexion refusée)
--                    ancien.employe@vite-gourmand.test       Inactif-Demo2026!
--    Client          client@vite-gourmand.test               Client-Demo2026!
--    Clients         thomas.petit@vite-gourmand.test,
--                    sarah.lambert@vite-gourmand.test        Client-Demo2026!
--
--  Les mots de passe sont stockés hashés (bcrypt, coût 13), comme le fait
--  l'application. Les jetons d'API sont tirés au hasard à l'import (SHA2(UUID())).
--
--  Les statistiques de l'administrateur viennent de MongoDB : après l'import,
--  lancer "php bin/console app:stats:sync" pour y recopier ces commandes.
-- =====================================================================

USE vite_gourmand;
SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
--  Rôles
-- ---------------------------------------------------------------------
INSERT INTO role (id, libelle) VALUES
    (1, 'Admin'),
    (2, 'Employé'),
    (3, 'Client');


-- ---------------------------------------------------------------------
--  Comptes
-- ---------------------------------------------------------------------
INSERT INTO user (id, role_id, first_name, last_name, phone_number, allergy, adresse, email,
                  api_token, roles, created_at, updated_at, password, is_verified, is_active)
VALUES
    (1, 1, 'José', 'Gourmand', '0612345678', NULL, '1 rue de la Liberté, 33000 Bordeaux',
        'admin@vite-gourmand.test', SHA2(UUID(), 256), '[]', '2026-07-01 09:00:00', NULL,
        '$2y$13$S1adwB7zOuNyrXIDDcCSVe1NHpY69If7ACu.lMjVZV.xUyaRCyhX6', 1, 1),
    (2, 2, 'Julie', 'Martin', '0623456789', NULL, NULL,
        'employe@vite-gourmand.test', SHA2(UUID(), 256), '[]', '2026-07-01 09:30:00', NULL,
        '$2y$13$SqHl//MQD8r0NirXolrk9.30uC33SJROrFh7zB9XX3CuhFqdPE6cy', 1, 1),
    (3, 2, 'Lucas', 'Bernard', '0634567890', NULL, NULL,
        'ancien.employe@vite-gourmand.test', SHA2(UUID(), 256), '[]', '2026-07-02 10:00:00', '2026-09-30 18:00:00',
        '$2y$13$Uz.UaVzCUmAdDbr.aLzJVuWPDGeIdiSQFouujh/cXrCTbTD0z8mFy', 1, 0),
    (4, 3, 'Camille', 'Durand', '0645678901', 'Fruits à coque', '15 cours de l''Intendance, 33000 Bordeaux',
        'client@vite-gourmand.test', SHA2(UUID(), 256), '[]', '2026-03-10 14:12:00', NULL,
        '$2y$13$4MWEjsAXvl6okayNm7jDWewL48yuCVHhHIHne9Ws7NutQQgq98OPi', 1, 1),
    (5, 3, 'Thomas', 'Petit', '0756789012', NULL, 'Place Charles de Gaulle, 33700 Mérignac',
        'thomas.petit@vite-gourmand.test', SHA2(UUID(), 256), '[]', '2026-09-01 20:45:00', NULL,
        '$2y$13$4MWEjsAXvl6okayNm7jDWewL48yuCVHhHIHne9Ws7NutQQgq98OPi', 1, 1),
    (6, 3, 'Sarah', 'Lambert', '0767890123', 'Végétarienne', '8 rue Esprit des Lois, 33000 Bordeaux',
        'sarah.lambert@vite-gourmand.test', SHA2(UUID(), 256), '[]', '2026-09-20 11:05:00', NULL,
        '$2y$13$4MWEjsAXvl6okayNm7jDWewL48yuCVHhHIHne9Ws7NutQQgq98OPi', 1, 1);


-- ---------------------------------------------------------------------
--  Horaires (un jour absent = affiché "Fermé")
-- ---------------------------------------------------------------------
INSERT INTO horraires (id, jour, heure) VALUES
    (1, 'Lundi',    '09:00 - 18:00'),
    (2, 'Mardi',    '09:00 - 18:00'),
    (3, 'Mercredi', '09:00 - 18:00'),
    (4, 'Jeudi',    '09:00 - 18:00'),
    (5, 'Vendredi', '09:00 - 19:00'),
    (6, 'Samedi',   '09:00 - 14:00');


-- ---------------------------------------------------------------------
--  Allergènes (les 14 allergènes à déclaration obligatoire), régimes, thèmes
-- ---------------------------------------------------------------------
INSERT INTO allergene (id, libelle) VALUES
    (1, 'Gluten'), (2, 'Crustacés'), (3, 'Œufs'), (4, 'Poissons'), (5, 'Arachides'),
    (6, 'Soja'), (7, 'Lait'), (8, 'Fruits à coque'), (9, 'Céleri'), (10, 'Moutarde'),
    (11, 'Graines de sésame'), (12, 'Sulfites'), (13, 'Lupin'), (14, 'Mollusques');

INSERT INTO regime (id, libelle) VALUES
    (1, 'Classique'),
    (2, 'Végétarien'),
    (3, 'Vegan'),
    (4, 'Pescétarien');

INSERT INTO theme (id, libelle) VALUES
    (1, 'Noël'),
    (2, 'Pâques'),
    (3, 'Classique'),
    (4, 'Événement');


-- ---------------------------------------------------------------------
--  Plats (entrées, plats, desserts) et leurs allergènes
-- ---------------------------------------------------------------------
INSERT INTO plat (id, title_plat, description) VALUES
    (1,  'Foie gras mi-cuit et chutney de figues', 'Entrée - foie gras du Sud-Ouest, chutney maison.'),
    (2,  'Velouté de potimarron aux châtaignes', 'Entrée - velouté onctueux, éclats de châtaignes.'),
    (3,  'Huîtres du bassin d''Arcachon', 'Entrée - six huîtres n°3, citron et vinaigre à l''échalote.'),
    (4,  'Salade de chèvre chaud au miel', 'Entrée - crottin de chèvre sur toast, miel et noix.'),
    (5,  'Houmous et crudités de saison', 'Entrée - houmous maison, légumes croquants.'),
    (6,  'Chapon rôti aux marrons', 'Plat - chapon fermier, marrons et pommes grenaille.'),
    (7,  'Gigot d''agneau de sept heures', 'Plat - cuisson lente, jus réduit, légumes de printemps.'),
    (8,  'Magret de canard, sauce aux cèpes', 'Plat - magret rosé, sauce crémée aux cèpes.'),
    (9,  'Dos de cabillaud, beurre blanc', 'Plat - cabillaud nacré, beurre blanc et riz.'),
    (10, 'Risotto aux champignons des bois', 'Plat - riz carnaroli, champignons de saison, parmesan.'),
    (11, 'Curry de légumes au lait de coco', 'Plat - légumes de saison, lait de coco, riz basmati.'),
    (12, 'Bûche chocolat praliné', 'Dessert - biscuit chocolat, crème pralinée.'),
    (13, 'Nid de Pâques au chocolat', 'Dessert - mousse chocolat, œufs en sucre.'),
    (14, 'Canelés bordelais', 'Dessert - trois canelés maison à la vanille et au rhum.'),
    (15, 'Salade de fruits frais à la menthe', 'Dessert - fruits de saison, sirop léger à la menthe.'),
    (16, 'Mousse au chocolat noir vegan', 'Dessert - chocolat noir 70 %, sans œuf ni lait.'),
    (17, 'Assortiment de verrines salées', 'Cocktail - crevettes, mousse de chèvre, gaspacho.'),
    (18, 'Mignardises sucrées', 'Cocktail - mini-tartelettes, macarons, financiers.');

INSERT INTO plat_allergene (plat_id, allergene_id) VALUES
    (1, 12),
    (2, 7), (2, 9),
    (3, 14),
    (4, 1), (4, 7), (4, 8),
    (5, 11),
    (6, 9),
    (7, 9), (7, 12),
    (8, 7), (8, 12),
    (9, 4), (9, 7),
    (10, 7), (10, 9),
    (11, 6),
    (12, 1), (12, 3), (12, 7), (12, 8),
    (13, 1), (13, 3), (13, 7),
    (14, 1), (14, 3), (14, 7),
    (16, 6),
    (17, 2), (17, 3), (17, 7),
    (18, 1), (18, 3), (18, 7), (18, 8);


-- ---------------------------------------------------------------------
--  Menus (prix par personne, minimum de personnes, stock, conditions)
-- ---------------------------------------------------------------------
INSERT INTO menu (id, title_menu, nombre_personne_minimum, prix_par_personne, description, quantite_restante, conditions) VALUES
    (1, 'Menu de Noël', 8, 45.00,
        'Le grand classique des fêtes : foie gras, chapon rôti et bûche maison.', 10,
        'À commander au moins 2 semaines avant la prestation. Conserver au réfrigérateur (0 à 4 °C) et consommer dans les 24 heures.'),
    (2, 'Menu de Pâques', 6, 38.00,
        'Gigot de sept heures et douceurs chocolatées pour fêter le printemps.', 8,
        'À commander au moins 10 jours avant la prestation. Réchauffer le gigot 20 minutes à 160 °C.'),
    (3, 'Menu Sud-Ouest', 4, 32.00,
        'Les saveurs de la région : magret aux cèpes et canelés bordelais.', 15,
        'À commander au moins 3 jours avant la prestation. Conserver au réfrigérateur.'),
    (4, 'Menu Végétarien du Marché', 4, 29.00,
        'Une cuisine de saison sans viande ni poisson, généreuse et colorée.', 12,
        'À commander au moins 3 jours avant la prestation.'),
    (5, 'Menu Vegan Gourmand', 6, 31.00,
        'Cent pour cent végétal, du houmous maison à la mousse au chocolat.', 10,
        'À commander au moins 5 jours avant la prestation. Préparé dans un atelier qui manipule aussi des produits animaux.'),
    (6, 'Menu Cocktail Événement', 20, 24.00,
        'Verrines et mignardises pour vos réceptions, inaugurations et mariages.', 5,
        'À commander au moins 3 semaines avant la prestation. Matériel prêté (plateaux, présentoirs) : à restituer sous 10 jours ouvrés, sinon 600 € de frais (voir les CGV).'),
    (7, 'Menu de la Mer', 6, 42.00,
        'Huîtres du bassin, cabillaud au beurre blanc et canelés.', 6,
        'À commander au moins 3 jours avant la prestation. Conserver les huîtres au frais et les consommer le jour de la livraison.');

INSERT INTO menu_plat (menu_id, plat_id) VALUES
    (1, 1), (1, 2), (1, 6), (1, 12),
    (2, 4), (2, 7), (2, 13),
    (3, 4), (3, 8), (3, 14),
    (4, 2), (4, 10), (4, 15),
    (5, 5), (5, 11), (5, 16),
    (6, 17), (6, 18),
    (7, 3), (7, 9), (7, 14);

INSERT INTO menu_theme (menu_id, theme_id) VALUES
    (1, 1),
    (2, 2),
    (3, 3),
    (4, 3),
    (5, 3), (5, 4),
    (6, 4),
    (7, 3);

INSERT INTO menu_regime (menu_id, regime_id) VALUES
    (1, 1),
    (2, 1),
    (3, 1),
    (4, 2),
    (5, 2), (5, 3),
    (6, 1),
    (7, 4);


-- ---------------------------------------------------------------------
--  Commandes
--  prix_menu = prix par personne appliqué (-10 % dès 5 personnes de plus que le minimum)
--  prix_livraison = 0 à Bordeaux, sinon 5 € + 0,59 €/km
-- ---------------------------------------------------------------------
INSERT INTO commande (id, menu_id, user_id, date_commande, date_prestation, heure_livraison, adresse,
                      prix_menu, nombre_personne, prix_livraison, statut, pret_materiel, restitution_materiel,
                      motif_annulation, mode_contact)
VALUES
    (1, 2, 4, '2026-03-20', '2026-04-05', '12:30', '15 cours de l''Intendance, 33000 Bordeaux',
        38.00, 6, 0.00, 'Terminée', 0, NULL, NULL, NULL),
    (2, 3, 4, '2026-09-05', '2026-09-20', '12:00', '15 cours de l''Intendance, 33000 Bordeaux',
        32.00, 6, 0.00, 'Terminée', 0, NULL, NULL, NULL),
    (3, 6, 5, '2026-09-12', '2026-10-03', '18:30', 'Place Charles de Gaulle, 33700 Mérignac',
        21.60, 25, 8.36, 'En attente du retour de matériel', 1, 0, NULL, NULL),
    (4, 7, 6, '2026-10-01', '2026-10-10', '19:00', '8 rue Esprit des Lois, 33000 Bordeaux',
        37.80, 11, 0.00, 'Terminée', 0, NULL, NULL, NULL),
    (5, 3, 5, '2026-10-02', '2026-10-18', '12:00', 'Place Charles de Gaulle, 33700 Mérignac',
        32.00, 4, 8.36, 'Annulée', 0, NULL, 'Client indisponible à la date prévue', 'GSM'),
    (6, 1, 6, '2026-10-20', '2026-12-24', '19:30', '8 rue Esprit des Lois, 33000 Bordeaux',
        45.00, 8, 0.00, 'Accepté', 0, NULL, NULL, NULL),
    (7, 4, 5, '2026-10-22', '2026-11-08', '12:30', 'Place Charles de Gaulle, 33700 Mérignac',
        29.00, 4, 8.36, 'En attente', 0, NULL, NULL, NULL),
    (8, 5, 4, '2026-10-25', '2026-11-14', '20:00', '15 cours de l''Intendance, 33000 Bordeaux',
        31.00, 6, 0.00, 'En attente', 0, NULL, NULL, NULL);

-- Stock des menus : déjà diminué de ces commandes dans les valeurs ci-dessus.

-- Suivi des commandes : chaque changement de statut, daté (auteur = qui l'a fait)
INSERT INTO commande_historique (commande_id, auteur_id, statut, commentaire, changed_at) VALUES
    (1, 4, 'En attente', 'Commande passée par le client', '2026-03-20 18:42:00'),
    (1, 2, 'Accepté', NULL, '2026-03-21 09:15:00'),
    (1, 2, 'En préparation', NULL, '2026-04-04 08:30:00'),
    (1, 2, 'En cours de livraison', NULL, '2026-04-05 11:40:00'),
    (1, 2, 'Livré', NULL, '2026-04-05 12:25:00'),
    (1, 2, 'Terminée', NULL, '2026-04-05 12:30:00'),

    (2, 4, 'En attente', 'Commande passée par le client', '2026-09-05 10:02:00'),
    (2, 2, 'Accepté', NULL, '2026-09-05 14:20:00'),
    (2, 2, 'En préparation', NULL, '2026-09-20 08:00:00'),
    (2, 2, 'En cours de livraison', NULL, '2026-09-20 11:30:00'),
    (2, 2, 'Livré', NULL, '2026-09-20 11:58:00'),
    (2, 2, 'Terminée', NULL, '2026-09-20 12:05:00'),

    (3, 5, 'En attente', 'Commande passée par le client', '2026-09-12 21:10:00'),
    (3, 2, 'Accepté', NULL, '2026-09-13 09:05:00'),
    (3, 2, 'En préparation', NULL, '2026-10-03 09:00:00'),
    (3, 2, 'En cours de livraison', NULL, '2026-10-03 17:45:00'),
    (3, 2, 'Livré', NULL, '2026-10-03 18:20:00'),
    (3, 2, 'En attente du retour de matériel', 'Plateaux et présentoirs prêtés', '2026-10-03 18:25:00'),

    (4, 6, 'En attente', 'Commande passée par le client', '2026-10-01 12:30:00'),
    (4, 1, 'Accepté', NULL, '2026-10-01 16:00:00'),
    (4, 2, 'En préparation', NULL, '2026-10-10 14:00:00'),
    (4, 2, 'En cours de livraison', NULL, '2026-10-10 18:15:00'),
    (4, 2, 'Livré', NULL, '2026-10-10 18:55:00'),
    (4, 2, 'Terminée', NULL, '2026-10-10 19:00:00'),

    (5, 5, 'En attente', 'Commande passée par le client', '2026-10-02 08:50:00'),
    (5, 2, 'Annulée', 'Annulée par un employé après contact du client par GSM : Client indisponible à la date prévue', '2026-10-03 10:12:00'),

    (6, 6, 'En attente', 'Commande passée par le client', '2026-10-20 19:40:00'),
    (6, 2, 'Accepté', NULL, '2026-10-21 09:00:00'),

    (7, 5, 'En attente', 'Commande passée par le client', '2026-10-22 13:25:00'),

    (8, 4, 'En attente', 'Commande passée par le client', '2026-10-25 17:08:00');


-- ---------------------------------------------------------------------
--  Avis clients (seuls les avis "Publié" apparaissent sur l'accueil)
-- ---------------------------------------------------------------------
INSERT INTO avis (user_id, commande_id, note, description, statut) VALUES
    (4, 1, 5, 'Le gigot de sept heures était fondant, parfait pour Pâques en famille.', 'Publié'),
    (6, 4, 4, 'Huîtres très fraîches et cabillaud parfait. Livraison avec quelques minutes de retard.', 'Publié'),
    (4, 2, 5, 'Magret délicieux et canelés comme à Bordeaux : tous nos invités ont adoré !', 'En attente');
