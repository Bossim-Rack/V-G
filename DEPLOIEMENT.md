# Déploiement de Vite & Gourmand

L'application est composée de 5 briques, chacune hébergée sur un service gratuit :

| Brique | Hébergeur | Rôle |
|---|---|---|
| Front (HTML / CSS / JS) | **Vercel** | Pages du site, appelle l'API |
| API Symfony | **Render** (conteneur Docker) | Toute la logique métier |
| Base relationnelle MySQL | **Aiven** | Utilisateurs, menus, commandes, avis… |
| Base NoSQL MongoDB | **MongoDB Atlas** (cluster M0) | Statistiques des commandes (espace admin) |
| Envoi des mails | **Brevo** (API HTTP) | Mails automatiques (inscription, commande…) |

```
Navigateur ──> Vercel (front) ──fetch──> Render (API Symfony) ──> Aiven (MySQL)
                                                   │ ├──────────> MongoDB Atlas
                                                   │ └──────────> Brevo (mails)
                                                   └────────────> Géoplateforme IGN (distance de livraison)
```

Ordre à respecter : **bases de données et mails → API → front** (chaque étape a besoin des adresses de la précédente).

---

## 1. MySQL sur Aiven

1. Créer un compte sur https://aiven.io puis **Create service → MySQL → Free plan**.
2. Une fois le service démarré, onglet **Overview / Connection information**, noter :
   - Host, Port, User (`avnadmin`), Password, Database (`defaultdb`)
   - **CA certificate** : cliquer sur « Show » et copier tout le contenu (de `-----BEGIN CERTIFICATE-----` à `-----END CERTIFICATE-----`).
3. Construire la variable `DATABASE_URL` :

```
mysql://avnadmin:MOT_DE_PASSE@HOST:PORT/defaultdb?serverVersion=8.0&charset=utf8mb4
```

> Aiven impose une connexion chiffrée (SSL). Le certificat est donné à l'API par la variable `MYSQL_CA_CERT` (voir étape 4), la configuration se trouve dans `config/packages/doctrine.yaml` (section `when@prod`).
>
> Les tables, les 3 rôles et le compte administrateur sont créés **automatiquement** au premier démarrage de l'API (commande `php bin/console app:init-database`). Il n'y a rien à importer à la main.

## 2. MongoDB sur Atlas

1. Créer un compte sur https://www.mongodb.com/cloud/atlas puis **Create → M0 (Free)**.
2. **Database Access** : créer un utilisateur (nom + mot de passe).
3. **Network Access** : ajouter `0.0.0.0/0` (Render n'a pas d'adresse IP fixe).
4. **Connect → Drivers** : copier l'adresse, de la forme :

```
mongodb+srv://UTILISATEUR:MOT_DE_PASSE@cluster0.xxxxx.mongodb.net/?retryWrites=true&w=majority
```

La base `vite_gourmand` et la collection `commandes` sont créées automatiquement à la première commande.

## 3. Mails avec Brevo

> Render bloque le SMTP sur son offre gratuite : l'API envoie donc les mails par l'**API HTTP de Brevo** (classe `src/Mailer/BrevoApiTransport.php`).

1. Créer un compte sur https://www.brevo.com.
2. **Senders, Domains & Dedicated IPs → Senders** : ajouter et valider l'adresse expéditrice (celle qui sera dans `MAILER_FROM`). Sans cette validation, Brevo refuse les envois.
3. **SMTP & API → API Keys** : générer une clé API (commence par `xkeysib-`).
4. La variable `MAILER_DSN` sera :

```
brevo+api://xkeysib-VOTRE_CLE@default
```

## 4. API Symfony sur Render

Fichiers utilisés :

- `Dockerfile` et `.dockerignore` (**à la racine du dépôt**) : image PHP 8.3 + Apache + extensions `pdo_mysql`, `mongodb`, `intl`. Ils sont à la racine car Render n'accepte pas d'espace ni de `&` dans « Root Directory » : c'est le Dockerfile qui va chercher le code dans `Back-End/synfony V&G`.
- `Back-End/synfony V&G/docker/apache.conf` : Apache sert le dossier `public/`
- `Back-End/synfony V&G/docker/entrypoint.sh` : à chaque démarrage → cache, création/mise à jour de la base, synchro MongoDB, lancement d'Apache

Étapes :

1. Sur https://render.com : **New → Web Service → Build and deploy from a Git repository**, choisir le dépôt GitHub.
2. Réglages :
   - **Branch** : `main`
   - **Root Directory** : laisser **vide** (le `Dockerfile` est à la racine)
   - **Runtime / Language** : `Docker`
   - **Instance type** : `Free`
   - **Health Check Path** (Advanced) : `/api/menu`
3. **Environment Variables** :

| Variable | Valeur |
|---|---|
| `APP_ENV` | `prod` |
| `APP_SECRET` | une longue chaîne aléatoire (bouton « Generate ») |
| `DATABASE_URL` | l'adresse Aiven (étape 1) |
| `MYSQL_CA_CERT` | le certificat CA d'Aiven (tout le texte) |
| `MONGODB_URL` | l'adresse Atlas (étape 2) |
| `MONGODB_DB` | `vite_gourmand` |
| `MAILER_DSN` | `brevo+api://xkeysib-...@default` (étape 3) |
| `MAILER_FROM` | l'adresse expéditrice validée chez Brevo |
| `CONTACT_EMAIL` | l'adresse de l'entreprise qui reçoit le formulaire de contact |
| `FRONT_URL` | l'adresse du front Vercel, ex. `https://vite-gourmand.vercel.app` |
| `CORS_ALLOW_ORIGIN` | la même adresse en expression régulière, ex. `^https://vite-gourmand\.vercel\.app$` |
| `ADMIN_EMAIL` | l'email du compte administrateur (José) |
| `ADMIN_PASSWORD` | son mot de passe (10 caractères, majuscule, minuscule, chiffre, caractère spécial) |

> `FRONT_URL` et `CORS_ALLOW_ORIGIN` ne sont connus qu'après l'étape 5 : mettre une valeur provisoire, puis les corriger et cliquer sur **Manual Deploy → Deploy latest commit**.

4. **Create Web Service**. Le premier déploiement prend quelques minutes. Dans les logs, on doit voir `Base prête.` puis le démarrage d'Apache.
5. Vérifier : `https://NOM-DU-SERVICE.onrender.com/api/menu` doit répondre `[]` (ou la liste des menus), et `/api/doc` affiche la documentation de l'API.

> **Offre gratuite** : le service s'endort après 15 minutes sans visite et met environ une minute à se réveiller. Avant une démonstration, ouvrir `/api/menu` une ou deux minutes avant.

## 5. Front sur Vercel

1. Dans `Front-end/assets/JS/config.js`, remplacer `https://VOTRE-API-EN-LIGNE/api` par l'adresse de l'API Render (ex. `https://vite-gourmand-api.onrender.com/api`), committer et pousser.
2. Sur https://vercel.com : **Add New → Project**, importer le dépôt GitHub.
3. Réglages :
   - **Root Directory** : `Front-end`
   - **Framework Preset** : `Other`
   - Pas de commande de build
4. **Deploy**. Le fichier `Front-end/vercel.json` renvoie toutes les adresses (`/menus`, `/profil`, …) vers `index.html`, c'est le routeur JavaScript qui affiche ensuite la bonne page.
5. Reporter l'adresse obtenue dans `FRONT_URL` et `CORS_ALLOW_ORIGIN` sur Render (étape 4).

## 6. Vérification finale

1. Ouvrir le site Vercel, créer un compte → le mail de bienvenue arrive → cliquer le lien → se connecter.
2. Commander un menu → mail de confirmation, détail du prix avec livraison.
3. Se connecter avec le compte admin (`ADMIN_EMAIL`) → tableau de bord : faire avancer la commande, créer un employé, voir l'onglet Statistiques.

## Mises à jour

Chaque `git push` sur `main` redéploie automatiquement l'API (Render) et le front (Vercel). Les nouvelles migrations Doctrine sont appliquées automatiquement au démarrage de l'API.

## Lancer le projet en local

```
cd "Back-End/synfony V&G"
composer install
php bin/console doctrine:database:create
php bin/console app:init-database        # tables + rôles (+ admin si ADMIN_EMAIL / ADMIN_PASSWORD sont définis)
symfony serve                            # ou : php -S 127.0.0.1:8000 -t public
```

Puis ouvrir `Front-end/index.html` avec Live Server (port 5500).
