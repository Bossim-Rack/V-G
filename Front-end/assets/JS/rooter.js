import Route from "./Route.js";
import { allRoutes, websiteName } from "./Allroutes.js";
// Création d'une route pour la page 404 (page introuvable)
const route404 = new Route("404", "Page introuvable", "/assets/pages/404.html");
//chargement du header et footer
const loadHeaderFooter = async () => {
  const header = await fetch("/Component/Header.html").then(r => r.text());
  document.getElementById("header-slot").innerHTML = header;

  const footer = await fetch("/Component/Footer.html").then(r => r.text());
  document.getElementById("footer-slot").innerHTML = footer;
  loadScript("/assets/JS/header.js");
  loadScript("/assets/JS/footer.js");

};
// injection de js dans le header
function loadScript(src){
  const script = document.createElement("script");
  script.src =src;
  document.body.appendChild(script);
}

loadHeaderFooter();
// Fonction pour récupérer la route correspondant à une URL donnée
const getRouteByUrl = (url) => {
  let currentRoute = null;
  // Parcours de toutes les routes pour trouver la correspondance
  allRoutes.forEach((element) => {
    if (element.url == url) {
      currentRoute = element;
    }
  });
  // Si aucune correspondance n'est trouvée, on retourne la route 404
  if (currentRoute != null) {
    return currentRoute;
  } else {
    return route404;
  }
};
// Fonction pour charger le contenu de la page
const LoadContentPage = async () => {
  const path = window.location.pathname;
  // Récupération de l'URL actuelle
  const actualRoute = getRouteByUrl(path);
  // Récupération du contenu HTML de la route
  const html = await fetch(actualRoute.pathHtml).then((data) => data.text());
  // Ajout du contenu HTML à l'élément avec l'ID "main-page"
  document.getElementById("main-page").innerHTML = html;
  // Ajout du contenu JavaScript (l'ancien script de page est retiré avant)
  if (actualRoute.pathJS != "") {
    document.querySelectorAll('script[data-page-script]').forEach(s => s.remove());
    var scriptTag = document.createElement("script");
    scriptTag.setAttribute("type", "text/javascript");
    scriptTag.setAttribute("src", actualRoute.pathJS);
    scriptTag.setAttribute("data-page-script", "true");
    document.querySelector("body").appendChild(scriptTag);
  }
  // Changement du titre de la page
  document.title = actualRoute.title + " - " + websiteName;
};
// Fonction pour gérer les événements de routage (clic sur les liens)
const routeEvent = async (event) => {
  event = event || window.event;
  event.preventDefault();
  // On remonte jusqu'au lien : le clic peut venir d'un élément à l'intérieur (ex : le "&" du logo)
  const lien = event.target.closest("a");
  if (!lien) return;
  // Mise à jour de l'URL dans l'historique du navigateur
  window.history.pushState({}, "", lien.href);
  // Chargement du contenu de la nouvelle page
  await LoadContentPage();
  // Accessibilité : on remonte en haut et le focus passe sur le contenu de la nouvelle page
  window.scrollTo(0, 0);
  document.getElementById("main-page").focus({ preventScroll: true });
};
// Gestion de l'événement de retour en arrière dans l'historique du navigateur
window.onpopstate = LoadContentPage;
// Assignation de la fonction routeEvent à la propriété route de la fenêtre
window.route = routeEvent;

// Liens reçus par mail (ex : /?page=/verifier-email&token=...) : on passe toujours par la
// racine du site, qui sert index.html quel que soit le serveur web, puis on affiche la bonne page.
const parametresInitiaux = new URLSearchParams(window.location.search);
const pageDemandee = parametresInitiaux.get("page");
if (pageDemandee && pageDemandee.startsWith("/") && !pageDemandee.startsWith("//")) {
  parametresInitiaux.delete("page");
  const suite = parametresInitiaux.toString();
  window.history.replaceState({}, "", pageDemandee + (suite ? "?" + suite : ""));
}

// Chargement du contenu de la page au chargement initial
LoadContentPage();