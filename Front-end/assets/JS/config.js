// ===================================================================== //
//  Configuration du front : chargée AVANT tous les autres scripts       //
//  (voir index.html). Une seule ligne à changer lors du déploiement.     //
// ===================================================================== //
var API_URL = (window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1")
  ? "http://127.0.0.1:8000/api"        // API Symfony en local
  : "https://v-g.onrender.com/api";    // API Symfony en ligne (Render)

// Protège l'affichage des textes saisis par les utilisateurs (noms, adresses, commentaires...)
// avant de les insérer avec innerHTML : évite l'injection de HTML/JavaScript (faille XSS).
function echapperHtml(valeur) {
  return String(valeur === null || valeur === undefined ? "" : valeur)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

// Mot de passe (identique au back, cf. User::$plainPassword) :
// 10 caractères minimum, au moins une majuscule, une minuscule, un chiffre et un caractère spécial.
function verifierMotDePasse(motDePasse) {
  var criteres = {
    longueur: motDePasse.length >= 10,
    majuscule: /[A-Z]/.test(motDePasse),
    minuscule: /[a-z]/.test(motDePasse),
    chiffre: /\d/.test(motDePasse),
    special: /[^a-zA-Z0-9]/.test(motDePasse)
  };
  var nbOk = Object.values(criteres).filter(Boolean).length;
  return {
    criteres: criteres,
    valide: nbOk === 5,
    // Niveau 0 à 4 pour la barre de robustesse (.ins-strength.s1 ... .s4)
    niveau: nbOk === 5 ? 4 : Math.min(3, Math.max(0, nbOk - 1))
  };
}

// Message lisible à partir d'une réponse d'erreur de l'API ({message} ou {errors: {...}} ou {error}).
function messageErreurApi(data, messageParDefaut) {
  if (data && data.errors) return Object.values(data.errors).join(" ");
  if (data && data.message) return data.message;
  if (data && data.error) return data.error;
  return messageParDefaut || "Une erreur est survenue.";
}

// Statut d'une commande -> classe CSS du badge (voir .profil-statut-* dans style.css)
var CLASSES_STATUT_COMMANDE = {
  'En attente': 'profil-statut-attente',
  'Accepté': 'profil-statut-confirmee',
  'En préparation': 'profil-statut-encours',
  'En cours de livraison': 'profil-statut-encours',
  'Livré': 'profil-statut-livree',
  'En attente du retour de matériel': 'profil-statut-materiel',
  'Terminée': 'profil-statut-terminee',
  'Annulée': 'profil-statut-annulee'
};

// "2026-12-24" -> "24/12/2026" ; "2026-12-24 18:05:00" -> "24/12/2026 à 18:05"
function formaterDateFr(valeur) {
  if (!valeur) return '—';
  var morceaux = String(valeur).split(' ');
  var date = morceaux[0].split('-').reverse().join('/');
  return morceaux[1] ? date + ' à ' + morceaux[1].slice(0, 5) : date;
}
