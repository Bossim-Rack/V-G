import Route from "./Route.js";

// ── Définis les  routes des HTML, pense au JS dans l'assets ──
export const allRoutes = [
    new Route("/accueil","Accueil","/assets/pages/acceuil.html","/assets/JS/acceuil.js"),
    // ancienne adresse (faute de frappe) conservée pour ne pas casser les liens existants
    new Route("/acceuil","Accueil","/assets/pages/acceuil.html","/assets/JS/acceuil.js"),
    new Route("/","Accueil","/assets/pages/acceuil.html","/assets/JS/acceuil.js"),
    new Route("/connexion","Connexion","/assets/pages/connexion.html","/assets/JS/connexion.js"),
    new Route("/inscription","Inscription","/assets/pages/inscription.html","/assets/JS/inscription.js"),
    new Route("/mdp-oublie","Mot de passe oublié","/assets/pages/forgotPassword.html","/assets/JS/forgotPassword.js"),
    new Route("/verifier-email","Confirmation de l'email","/assets/pages/verifierEmail.html","/assets/JS/verifierEmail.js"),
    new Route("/reinitialiser-mot-de-passe","Nouveau mot de passe","/assets/pages/reinitialiserMotDePasse.html","/assets/JS/reinitialiserMotDePasse.js"),
    new Route("/mentions-legales","Mentions légales","/assets/pages/mentions-legales.html"),
    new Route("/cgv","Conditions générales de vente","/assets/pages/CGV.html"),
    new Route("/menus","Nos menus","/assets/pages/menus.html","/assets/JS/menus.js"),
    new Route("/contact","Contact","/assets/pages/contact.html","/assets/JS/contact.js"),
    new Route ("/profil","Mon profil","/assets/pages/profil.html","/assets/JS/profil.js"),
    new Route ("/tableau_de_bord","Tableau de bord","/assets/pages/tableaubord.html","/assets/JS/tableaubord.js"),
];

// Le titre s'affiche : "Page - Nom du site"
export const websiteName = "Vite & Gourmand";