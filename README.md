# Vite & Gourmand

Application web du traiteur bordelais **Vite & Gourmand** (Julie et José) : les visiteurs consultent les menus, les clients commandent et suivent leurs commandes, l'équipe gère le catalogue, les commandes et les avis, et l'administrateur suit les statistiques.

Projet réalisé pour l'ECF du titre professionnel **Développeur Web et Web Mobile** (Studi).

| | Lien |
|---|---|
| Site en ligne | https://vite-gourmand-kappa.vercel.app |
| API en ligne | https://v-g.onrender.com/api/menu |
| Documentation de l'API | https://v-g.onrender.com/api/doc |
| Dépôt GitHub | https://github.com/Bossim-Rack/V-G |

> L'API est hébergée sur l'offre gratuite de Render : après 15 minutes sans visite, elle se met en veille et met environ une minute à se réveiller. Ouvrir l'adresse de l'API une minute avant une démonstration.

---

## Sommaire

1. [Fonctionnalités](#fonctionnalités)
2. [Technologies](#technologies)
3. [Organisation du dépôt](#organisation-du-dépôt)
4. [Installation en local](#installation-en-local)
5. [Comptes de démonstration](#comptes-de-démonstration)
6. [Tests](#tests)
7. [Branches Git](#branches-git)
8. [Déploiement](#déploiement)
9. [Documentation](#documentation)

---

## Fonctionnalités

| Profil | Ce qu'il peut faire |
|---|---|
| **Visiteur** | Voir l'accueil (présentation, avis validés), tous les menus avec filtres dynamiques (prix maximum, fourchette de prix, thème, régime, allergène, nombre de personnes), le détail d'un menu et ses conditions, contacter l'entreprise, créer un compte |
| **Client** | Se connecter, réinitialiser son mot de passe par mail, commander un menu (prix et livraison calculés avant validation), modifier ou annuler sa commande tant qu'elle n'est pas acceptée, suivre chaque étape de sa commande, laisser un avis (note de 1 à 5 + commentaire), modifier ses informations, supprimer son compte |
| **Employé** | Gérer les menus, plats, thèmes, régimes et horaires ; filtrer les commandes par statut ou par client ; faire avancer les commandes ; les modifier ou les annuler après avoir contacté le client (mode de contact + motif obligatoires) ; valider ou refuser les avis |
| **Administrateur** | Tout ce que fait un employé ; créer et désactiver des comptes employés ; consulter le nombre de commandes par menu (graphique) et le chiffre d'affaires par menu et par période, calculés depuis MongoDB |

Mails automatiques : bienvenue avec lien de confirmation de l'adresse, mot de passe oublié, confirmation de commande, rappel de retour du matériel (10 jours ouvrés, 600 € de frais), invitation à laisser un avis, création d'un compte employé, formulaire de contact.

## Technologies

| Partie | Choix |
|---|---|
| Front | HTML 5, CSS, Bootstrap 5.3, JavaScript sans framework (routeur maison en une seule page), Chart.js |
| Back | PHP 8, Symfony 5.4 (API REST), Doctrine ORM |
| Base relationnelle | MySQL 8 |
| Base NoSQL | MongoDB (statistiques des commandes) |
| Mails | Symfony Mailer ; en production, API HTTP de Brevo |
| Déploiement | Vercel (front), Render en conteneur Docker (API), Aiven (MySQL), MongoDB Atlas (MongoDB) |

Les raisons de ces choix sont détaillées dans la [documentation technique](docs/documentation-technique.pdf).

## Organisation du dépôt

```
V-G/
├── Front-end/                 Site (HTML, CSS, JS) déployé sur Vercel
│   ├── index.html             Page unique : charge le routeur
│   ├── Component/             En-tête et pied de page
│   └── assets/
│       ├── pages/             Contenu HTML de chaque page
│       ├── JS/                Routeur, configuration (URL de l'API) et script de chaque page
│       └── images/
├── Back-End/synfony V&G/      API Symfony déployée sur Render
├── database/
│   ├── 01_creation_base.sql   Création de la base et des tables
│   └── 02_donnees_demo.sql    Données de démonstration (comptes, menus, commandes, avis)
├── docs/                      Manuel d'utilisation, charte graphique, documentation technique, gestion de projet (PDF)
├── Dockerfile                 Image de l'API pour Render
└── DEPLOIEMENT.md             Déploiement en ligne pas à pas
```

## Installation en local

### Prérequis

- **PHP 8.0 ou plus** avec les extensions `pdo_mysql`, `intl`, `ctype`, `iconv` et **`mongodb`** (`pecl install mongodb`)
- **Composer** 2
- **MySQL 8** (ou MariaDB 10.6+)
- **MongoDB 6 ou plus** : facultatif, seules les statistiques de l'administrateur en ont besoin
- **Node.js**, uniquement pour servir le front avec `npx serve` (n'importe quel serveur de fichiers statiques convient)
- Facultatif : la [Symfony CLI](https://symfony.com/download)

### 1. Récupérer le projet

```bash
git clone https://github.com/Bossim-Rack/V-G.git
cd V-G
```

### 2. Installer les dépendances de l'API

```bash
cd "Back-End/synfony V&G"
composer install
```

### 3. Configurer l'API

Créer le fichier `Back-End/synfony V&G/.env.local` (il n'est pas versionné) en adaptant l'utilisateur et le mot de passe MySQL :

```dotenv
DATABASE_URL="mysql://root:MOT_DE_PASSE@127.0.0.1:3306/vite_gourmand?serverVersion=8.0&charset=utf8mb4"
MAILER_DSN=null://null
MONGODB_URL=mongodb://127.0.0.1:27017
MONGODB_DB=vite_gourmand
FRONT_URL=http://localhost:5500
```

- `MAILER_DSN=null://null` désactive l'envoi réel des mails. Pour les voir passer, utiliser par exemple [Mailpit](https://mailpit.axllent.org/) avec `MAILER_DSN=smtp://127.0.0.1:1025`.
- Avec MariaDB, remplacer `serverVersion=8.0` par la version exacte, par exemple `serverVersion=10.11.14-MariaDB`.
- Les autres variables (`CORS_ALLOW_ORIGIN`, `MAILER_FROM`, `CONTACT_EMAIL`, `GEOCODAGE_URL`) ont déjà une valeur adaptée au local dans `.env`.

### 4. Créer la base de données avec les fichiers SQL

Depuis la racine du dépôt :

```bash
mysql -u root -p < database/01_creation_base.sql
mysql -u root -p vite_gourmand < database/02_donnees_demo.sql
```

Le premier script crée la base `vite_gourmand` et ses 15 tables. Le second ajoute les données de démonstration. Le schéma obtenu est identique à celui des entités Doctrine (`php bin/console doctrine:schema:validate` le confirme).

Indiquer ensuite à Doctrine que la base est déjà à jour, pour qu'il ne rejoue pas les anciennes migrations :

```bash
cd "Back-End/synfony V&G"
php bin/console doctrine:migrations:sync-metadata-storage
php bin/console doctrine:migrations:version --add --all --no-interaction
```

> **Autre possibilité, sans données de démonstration :** `php bin/console doctrine:database:create` puis `php bin/console app:init-database`. Cette commande crée les tables, les rôles et les 14 allergènes, ainsi que le compte administrateur si les variables `ADMIN_EMAIL` et `ADMIN_PASSWORD` sont définies.

### 5. Remplir MongoDB (statistiques)

MongoDB doit être lancé. Cette commande recopie dans MongoDB toutes les commandes de MySQL :

```bash
php bin/console app:stats:sync
```

Ensuite, chaque nouvelle commande, modification ou annulation est recopiée automatiquement. Sans MongoDB, le site fonctionne normalement : seul l'onglet « Statistiques » de l'administrateur affiche une erreur.

### 6. Lancer l'API

```bash
symfony serve --port=8000
# ou, sans la Symfony CLI :
php -S 127.0.0.1:8000 -t public
```

L'API répond sur http://127.0.0.1:8000/api/menu et sa documentation est sur http://127.0.0.1:8000/api/doc.

### 7. Lancer le front

Dans un autre terminal, depuis la racine du dépôt :

```bash
npx serve -s Front-end -l 5500
```

Ouvrir http://localhost:5500. Sur `localhost` ou `127.0.0.1`, le front appelle automatiquement l'API locale `http://127.0.0.1:8000/api` (voir `Front-end/assets/JS/config.js`). L'option `-s` renvoie toutes les adresses vers `index.html`, ce qui permet de recharger une page comme `/menus` sans erreur 404. Le fichier `Front-end/serve.json` empêche `serve` de retirer le `.html` des adresses, ce qui casserait le chargement des pages (même rôle que `vercel.json` en production).

> Le calcul des frais de livraison hors de Bordeaux interroge le service de géocodage de l'IGN (`data.geopf.fr`) : il faut donc une connexion Internet pour commander avec une adresse hors de Bordeaux.

## Comptes de démonstration

Ces comptes sont créés par `database/02_donnees_demo.sql` et existent **uniquement en local**. Leurs mots de passe sont publics : ce fichier ne doit jamais être importé sur une base en production.

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur (José) | `admin@vite-gourmand.test` | `Admin-Demo2026!` |
| Employée (Julie) | `employe@vite-gourmand.test` | `Employe-Demo2026!` |
| Employé désactivé (connexion refusée) | `ancien.employe@vite-gourmand.test` | `Inactif-Demo2026!` |
| Cliente (Camille) | `client@vite-gourmand.test` | `Client-Demo2026!` |
| Clients (Thomas, Sarah) | `thomas.petit@vite-gourmand.test`, `sarah.lambert@vite-gourmand.test` | `Client-Demo2026!` |

Les identifiants du site en ligne sont donnés dans le [manuel d'utilisation](docs/manuel-utilisation.pdf).

## Tests

Les tests PHPUnit utilisent une base `vite_gourmand_test`, créée avec les mêmes fichiers SQL :

```bash
sed 's/vite_gourmand/vite_gourmand_test/g' database/01_creation_base.sql | mysql -u root -p
sed 's/^USE vite_gourmand;/USE vite_gourmand_test;/' database/02_donnees_demo.sql | mysql -u root -p
cd "Back-End/synfony V&G"
php bin/phpunit
```

Ils vérifient la documentation de l'API, la protection des routes, la connexion (mot de passe correct, mot de passe faux, compte désactivé), l'interdiction des statistiques à un client et l'entité `User`.

## Branches Git

- `main` : version en production. Chaque push sur `main` redéploie automatiquement l'API (Render) et le front (Vercel).
- `develop` : branche de développement, où les fonctionnalités terminées sont réunies et testées.
- `feature/...` : une branche par fonctionnalité, créée depuis `develop` et fusionnée dans `develop` après test, par exemple `feature/mails-securite-commandes`, `feature/deploiement` ou `feature/allergenes-affichage`.

Quand `develop` est testée, elle est fusionnée dans `main`.

## Déploiement

Le déploiement en ligne (Aiven, MongoDB Atlas, Brevo, Render, Vercel) est décrit étape par étape dans [DEPLOIEMENT.md](DEPLOIEMENT.md) et dans la documentation technique.

## Documentation

Tous les documents sont dans le dossier [`docs/`](docs/) :

- [Manuel d'utilisation](docs/manuel-utilisation.pdf) : présentation et parcours de chaque profil, avec les identifiants
- [Charte graphique](docs/charte-graphique.pdf) : couleurs, polices, wireframes et maquettes (3 bureau, 3 mobile)
- [Documentation technique](docs/documentation-technique.pdf) : choix techniques, environnement de travail, modèle de données, diagrammes, sécurité, déploiement
- [Gestion de projet](docs/gestion-de-projet.pdf) : organisation, outils et suivi du projet
