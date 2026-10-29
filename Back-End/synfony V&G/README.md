# API Vite & Gourmand (Symfony 5.4)

Ce dossier contient l'API REST de l'application. L'installation complète (API + base de données + front) est décrite dans le [README à la racine du dépôt](../../README.md).

Repères rapides :

| Dossier | Contenu |
|---|---|
| `src/Controller/` | les routes de l'API (`/api/...`) |
| `src/Entity/` | les entités Doctrine (tables MySQL) |
| `src/Service/` | mails, calcul du prix des commandes, statistiques MongoDB |
| `src/Command/` | `app:init-database` (base vide → tables, rôles, allergènes, admin) et `app:stats:sync` (copie des commandes dans MongoDB) |
| `templates/emails/` | les mails automatiques (Twig) |
| `migrations/` | l'historique des évolutions du schéma |
| `tests/` | tests PHPUnit (`php bin/phpunit`) |
| `docker/` | configuration Apache et script de démarrage utilisés en production (Render) |

Documentation interactive de l'API : `/api/doc` (NelmioApiDocBundle).
