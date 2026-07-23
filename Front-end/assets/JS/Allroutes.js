import Route from "./Route.js";

// ── Définis les  routes des HTML, pense au JS dans l'assets ──
export const allRoutes = [
    new Route("/acceuil","Accueil","/assets/pages/acceuil.html","/assets/JS/acceuil.js"),
    new Route("/","Accueil","/assets/pages/acceuil.html","/assets/JS/acceuil.js"),
    new Route("/connexion","Connexion","/assets/pages/connexion.html","/assets/JS/connexion.js"),
    new Route("/inscription","Inscription","/assets/pages/inscription.html","/assets/JS/inscription.js"),
    new Route("/mdp-oublie","Mot de passe oublié","/assets/pages/forgotPassword.html","/assets/JS/forgotPassword.js"),
    new Route("/mentions-legales","Mentions Légales","/assets/pages/mentions-legales.html"),
    new Route("/cgv","CGV","/assets/pages/CGV.html"),
    new Route("/menus","Nos menu","/assets/pages/menus.html","/assets/JS/menus.js"),
    new Route("/contact","contact","/assets/pages/contact.html","/assets/js/contact.js"),
    new Route ("/profil","profil","/assets/pages/profil.html","/assets/js/profil.js"),
    new Route ("/tableau_de_bord","Tableau de bord","/assets/pages/tableaubord.html","/assets/js/tableaubord.js"),
];

// Le titre s'affiche : "Page - Nom du site"
export const websiteName = "Vite & Gourmand";