
(function () {
                          //========================//
                          // Catalogue des menus (page publique) //
                          //========================//


function naviguerSPA(chemin) {
  if (typeof window.route === 'function') {
    window.route({ preventDefault: function () {}, target: { href: chemin } });
  } else {
    window.location.href = chemin;
  }
}

const MENUS_IMAGE_PLACEHOLDER = "/assets/images/Plat homard.jpg";

let menusPublicsCache = [];

function creerCarteMenuPublic(menu) {
  var col = document.createElement('div');
  col.className = 'col-12 col-md-6 col-lg-4';

  var prix = parseFloat(menu.prixParPersonne);
  var prixTexte = isNaN(prix) ? menu.prixParPersonne : prix.toFixed(2);

  col.innerHTML =
    '<div class="menu-card">' +
      '<img src="' + (menu.photoMenu || MENUS_IMAGE_PLACEHOLDER) + '" alt="' + echapperHtml(menu.titleMenu) + '" class="menu-card-img">' +
      '<div class="menu-card-body">' +
        '<h2 class="menu-card-title">' + echapperHtml(menu.titleMenu.toUpperCase()) + '</h2>' +
        '<p class="menu-card-text">' + echapperHtml(menu.description || '') + '</p>' +
        (menu.conditions ? '<p class="menu-card-conditions small mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Conditions particulières : voir le détail</p>' : '') +
        ((menu.regimesAlimentaires && menu.regimesAlimentaires.length > 0)
          ? '<p class="menu-card-regimes mb-0"><i class="bi bi-leaf"></i> ' + echapperHtml(menu.regimesAlimentaires.map(function (r) { return r.libelle; }).join(', ')) + '</p>'
          : '') +
      '</div>' +
      '<div class="menu-card-footer d-flex justify-content-between align-items-center">' +
        '<div>' +
          '<p class="menu-card-personnes mb-1"><i class="bi bi-people-fill"></i> ' + menu.nombrePersonneMinimum + ' personnes minimum</p>' +
          '<p class="menu-card-prix mb-0">' + prixTexte + ' € / pers.</p>' +
        '</div>' +
        '<div class="menu-card-actions">' +
          '<button type="button" class="btn btn-details js-details-menu" data-id="' + menu.id + '">Details</button>' +
          '<button type="button" class="btn btn-commander js-commander-menu" data-id="' + menu.id + '">Commander</button>' +
        '</div>' +
      '</div>' +
    '</div>';

  return col;
}

// Applique les filtres (thème, prix min/max, allergène à exclure, régime alimentaire)
// en lisant directement l'état des contrôles du DOM à chaque appel.
function afficherMenusPublics() {
  var grille = document.getElementById('menus-grille');
  if (!grille) return;

  var themeId = document.getElementById('filtre-theme').value;
  var allergeneId = document.getElementById('filtre-allergene').value;
  var regimeAlimentaireId = document.getElementById('filtre-regime-alimentaire').value;
  var nbPersonnes = parseInt(document.getElementById('filtre-personnes').value, 10);
  var prixMin = parseFloat(document.getElementById('filtre-prix-min').value);
  var prixMax = parseFloat(document.getElementById('filtre-prix-max').value);

  var menusAffiches = menusPublicsCache.filter(function (menu) {
    if (themeId && !(menu.themes || []).some(function (t) { return String(t.id) === String(themeId); })) {
      return false;
    }

    // "Sans [allergène]" : on retire tout menu qui contient cet allergène
    // (agrégé côté API depuis les plats du menu, voir menuToArray()).
    if (allergeneId && (menu.allergenes || []).some(function (a) { return String(a.id) === String(allergeneId); })) {
      return false;
    }

    // Régime alimentaire sélectionné manuellement (Végétarien, Sans gluten, ...) —
    // voir Menu::regimesAlimentaires.
    if (regimeAlimentaireId && !(menu.regimesAlimentaires || []).some(function (r) { return String(r.id) === String(regimeAlimentaireId); })) {
      return false;
    }

    // Nombre de personnes : on garde les menus commandables pour ce nombre
    // (leur minimum de personnes est inférieur ou égal au nombre saisi).
    if (!isNaN(nbPersonnes) && nbPersonnes > 0 && menu.nombrePersonneMinimum > nbPersonnes) {
      return false;
    }

    var prix = parseFloat(menu.prixParPersonne);
    if (!isNaN(prixMin) && (isNaN(prix) || prix < prixMin)) {
      return false;
    }
    if (!isNaN(prixMax) && (isNaN(prix) || prix > prixMax)) {
      return false;
    }

    return true;
  });

  grille.innerHTML = '';

  if (menusAffiches.length === 0) {
    grille.innerHTML = '<div class="col-12"><p class="text-center">Aucun menu ne correspond à ces filtres.</p></div>';
    return;
  }

  menusAffiches.forEach(function (menu) {
    grille.appendChild(creerCarteMenuPublic(menu));
  });
}

// Liste des thèmes depuis la DB (GET /api/theme, désormais public) : montre
// aussi les thèmes qui n'ont pas encore de menu associé. En cas d'échec de
// l'appel, on retombe sur les thèmes déduits des menus déjà chargés.
function peuplerFiltreThemes(themesDb) {
  var select = document.getElementById('filtre-theme');
  if (!select) return;

  var options;
  if (themesDb && themesDb.length > 0) {
    options = themesDb.map(function (t) { return { id: t.id, libelle: t.libelle }; });
  } else {
    var themesVus = {};
    menusPublicsCache.forEach(function (menu) {
      (menu.themes || []).forEach(function (t) { themesVus[t.id] = t.libelle; });
    });
    options = Object.keys(themesVus).map(function (id) { return { id: id, libelle: themesVus[id] }; });
  }

  select.innerHTML = '<option value="">Tous les thèmes</option>' +
    options.map(function (t) {
      return '<option value="' + t.id + '">' + t.libelle + '</option>';
    }).join('');
}

// Liste des allergènes depuis la DB (GET /api/allergene).
function peuplerFiltreAllergenes(allergenesDb) {
  var select = document.getElementById('filtre-allergene');
  if (!select) return;

  select.innerHTML = '<option value="">Aucun filtre</option>' +
    (allergenesDb || []).map(function (a) {
      return '<option value="' + a.id + '">Sans ' + a.libelle + '</option>';
    }).join('');
}

// Liste des régimes alimentaires depuis la DB (GET /api/regime) : Végétarien, Sans gluten, ... — sélection manuelle côté tableau
// de bord.
function peuplerFiltreRegimeAlimentaire(regimesDb) {
  var select = document.getElementById('filtre-regime-alimentaire');
  if (!select) return;

  select.innerHTML = '<option value="">Tous les régimes</option>' +
    (regimesDb || []).map(function (r) {
      return '<option value="' + r.id + '">' + r.libelle + '</option>';
    }).join('');
}

// Fourchette de prix : bornes calculées à partir des prix réellement présents
// dans les menus chargés (plutôt qu'un min/max arbitraire codé en dur).
function initialiserFiltrePrix() {
  var sliderMin = document.getElementById('filtre-prix-min');
  var sliderMax = document.getElementById('filtre-prix-max');
  if (!sliderMin || !sliderMax) return;

  var prix = menusPublicsCache
    .map(function (m) { return parseFloat(m.prixParPersonne); })
    .filter(function (p) { return !isNaN(p); });

  var borneMin = prix.length ? Math.floor(Math.min.apply(null, prix)) : 0;
  var borneMax = prix.length ? Math.ceil(Math.max.apply(null, prix)) : 100;

  sliderMin.min = borneMin;
  sliderMin.max = borneMax;
  sliderMin.value = borneMin;

  sliderMax.min = borneMin;
  sliderMax.max = borneMax;
  sliderMax.value = borneMax;

  majAffichagePrix();
}

// Met à jour le texte "X € - Y €" et la barre de remplissage entre les 2 curseurs.
function majAffichagePrix() {
  var sliderMin = document.getElementById('filtre-prix-min');
  var sliderMax = document.getElementById('filtre-prix-max');
  var fill = document.getElementById('filtre-prix-fill');
  var valeurs = document.getElementById('filtre-prix-valeurs');
  if (!sliderMin || !sliderMax) return;

  var borneMin = parseFloat(sliderMin.min);
  var borneMax = parseFloat(sliderMin.max);
  var min = parseFloat(sliderMin.value);
  var max = parseFloat(sliderMax.value);

  if (valeurs) valeurs.textContent = min === max ? min + ' €' : min + ' € - ' + max + ' €';

  if (fill && borneMax > borneMin) {
    var pctMin = ((min - borneMin) / (borneMax - borneMin)) * 100;
    var pctMax = ((max - borneMin) / (borneMax - borneMin)) * 100;
    fill.style.left = pctMin + '%';
    fill.style.right = (100 - pctMax) + '%';
  }
}

// Appel API : GET /api/menu — route publique (voir security.yaml).
// /api/theme, /api/allergene et /api/regime sont chargés en parallèle pour peupler les filtres.
async function chargerMenusPublics() {
  var grille = document.getElementById('menus-grille');
  try {
    const [menusReponse, themesReponse, allergenesReponse, regimesReponse] = await Promise.all([
      fetch(API_URL + "/menu", { method: "GET" }),
      fetch(API_URL + "/theme", { method: "GET" }),
      fetch(API_URL + "/allergene", { method: "GET" }),
      fetch(API_URL + "/regime", { method: "GET" })
    ]);

    if (!menusReponse.ok) {
      if (grille) grille.innerHTML = '<div class="col-12"><p class="text-center">Impossible de charger les menus pour le moment.</p></div>';
      return;
    }

    menusPublicsCache = await menusReponse.json();
    var themesDb = themesReponse.ok ? await themesReponse.json() : [];
    var allergenesDb = allergenesReponse.ok ? await allergenesReponse.json() : [];
    var regimesDb = regimesReponse.ok ? await regimesReponse.json() : [];

    peuplerFiltreThemes(themesDb);
    peuplerFiltreAllergenes(allergenesDb);
    peuplerFiltreRegimeAlimentaire(regimesDb);
    initialiserFiltrePrix();
    afficherMenusPublics();
  } catch (error) {
    console.error(error);
    if (grille) grille.innerHTML = '<div class="col-12"><p class="text-center">Impossible de charger les menus pour le moment.</p></div>';
  }
}

chargerMenusPublics();

document.getElementById('filtre-theme').addEventListener('change', function () { afficherMenusPublics(); });
document.getElementById('filtre-allergene').addEventListener('change', function () { afficherMenusPublics(); });
document.getElementById('filtre-regime-alimentaire').addEventListener('change', function () { afficherMenusPublics(); });
document.getElementById('filtre-personnes').addEventListener('input', function () { afficherMenusPublics(); });

// Les 2 curseurs ne doivent jamais se croiser (le curseur "min" ne peut pas
// dépasser le curseur "max", et inversement).
document.getElementById('filtre-prix-min').addEventListener('input', function () {
  var sliderMax = document.getElementById('filtre-prix-max');
  if (parseFloat(this.value) > parseFloat(sliderMax.value)) {
    this.value = sliderMax.value;
  }
  majAffichagePrix();
  afficherMenusPublics();
});
document.getElementById('filtre-prix-max').addEventListener('input', function () {
  var sliderMin = document.getElementById('filtre-prix-min');
  if (parseFloat(this.value) < parseFloat(sliderMin.value)) {
    this.value = sliderMin.value;
  }
  majAffichagePrix();
  afficherMenusPublics();
});

document.getElementById('btn-reinitialiser-filtres').addEventListener('click', function () {
  document.getElementById('filtre-theme').value = '';
  document.getElementById('filtre-allergene').value = '';
  document.getElementById('filtre-regime-alimentaire').value = '';
  document.getElementById('filtre-personnes').value = '';
  initialiserFiltrePrix();
  afficherMenusPublics();
});

                          //========================//
                          // Modale de détail //
                          //========================//

// menu actuellement affiché dans la modale de détail (pour le bouton
// "Commander" qui s'y trouve).
let menuDetailCourant = null;

// Délégation d'événements : les boutons "Details"/"Commander" sont générés dynamiquement.
document.getElementById('menus-grille').addEventListener('click', function (e) {
  var btnCommander = e.target.closest('.js-commander-menu');
  if (btnCommander) {
    var idCommander = parseInt(btnCommander.dataset.id, 10);
    var menuCommander = menusPublicsCache.find(function (m) { return m.id === idCommander; });
    if (menuCommander) ouvrirCommandeModal(menuCommander);
    return;
  }

  var btn = e.target.closest('.js-details-menu');
  if (!btn) return;

  var id = parseInt(btn.dataset.id, 10);
  var menu = menusPublicsCache.find(function (m) { return m.id === id; });
  if (!menu) return;

  menuDetailCourant = menu;
  document.getElementById('menuDetailModalTitre').textContent = menu.titleMenu;
  document.getElementById('menuDetailModalPhoto').src = menu.photoMenu || MENUS_IMAGE_PLACEHOLDER;
  document.getElementById('menuDetailModalDescription').textContent = menu.description || '';

  // Conditions du menu mises bien en évidence (en haut de la fenêtre, encadré orange).
  var conditionsWrap = document.getElementById('menuDetailModalConditionsWrap');
  document.getElementById('menuDetailModalConditions').textContent = menu.conditions || '';
  conditionsWrap.classList.toggle('d-none', !menu.conditions);
  document.getElementById('menuDetailModalMin').textContent = menu.nombrePersonneMinimum;

  var prix = parseFloat(menu.prixParPersonne);
  document.getElementById('menuDetailModalPrix').textContent = isNaN(prix) ? menu.prixParPersonne : prix.toFixed(2);
  document.getElementById('menuDetailModalStock').textContent = (menu.quantiteRestante === null || menu.quantiteRestante === undefined) ? 'Illimité' : menu.quantiteRestante;

  var regimesAlimentairesWrap = document.getElementById('menuDetailModalRegimesAlimentairesWrap');
  if (menu.regimesAlimentaires && menu.regimesAlimentaires.length > 0) {
    regimesAlimentairesWrap.classList.remove('d-none');
    document.getElementById('menuDetailModalRegimesAlimentaires').textContent = menu.regimesAlimentaires.map(function (r) { return r.libelle; }).join(', ');
  } else {
    regimesAlimentairesWrap.classList.add('d-none');
  }

  var themesWrap = document.getElementById('menuDetailModalThemesWrap');
  if (menu.themes && menu.themes.length > 0) {
    themesWrap.classList.remove('d-none');
    document.getElementById('menuDetailModalThemes').textContent = menu.themes.map(function (t) { return t.libelle; }).join(', ');
  } else {
    themesWrap.classList.add('d-none');
  }

  // Allergènes agrégés depuis les plats inclus dans le menu (voir menuAllergenesArray() côté API).
  var allergenesWrap = document.getElementById('menuDetailModalAllergenesWrap');
  if (menu.allergenes && menu.allergenes.length > 0) {
    allergenesWrap.classList.remove('d-none');
    document.getElementById('menuDetailModalAllergenes').textContent = menu.allergenes.map(function (a) { return a.libelle; }).join(', ');
  } else {
    allergenesWrap.classList.add('d-none');
  }

  var platsTexte = (menu.plats && menu.plats.length > 0)
    ? menu.plats.map(function (p) { return p.titlePlat; }).join(', ')
    : 'Aucun plat renseigné';
  document.getElementById('menuDetailModalPlats').textContent = platsTexte;

  var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('menuDetailModal'));
  modal.show();
});

document.getElementById('btn-commander-depuis-details').addEventListener('click', function () {
  if (!menuDetailCourant) return;

  var detailModal = bootstrap.Modal.getInstance(document.getElementById('menuDetailModal'));
  if (detailModal) detailModal.hide();

  ouvrirCommandeModal(menuDetailCourant);
});

                          //========================//
                          // Modale de commande //
                          //========================//

// Menu pour lequel une commande est en cours de saisie.
let menuCommandeCourant = null;
// Dernier détail de prix calculé par l'API (null tant que l'adresse n'est pas valide).
let devisCourant = null;
let minuteurDevis = null;

function commandeMasquerMessages() {
  document.getElementById('commandeModalError').classList.add('d-none');
  document.getElementById('commandeModalSuccess').classList.add('d-none');
}

function commandeAfficherErreur(message) {
  var erreur = document.getElementById('commandeModalError');
  erreur.textContent = message;
  erreur.classList.remove('d-none');
  document.getElementById('commandeModalSuccess').classList.add('d-none');
}

function euros(montant) {
  return Number(montant).toFixed(2).replace('.', ',') + ' €';
}

// Aperçu immédiat du prix du menu (réduction de 10 % si >= minimum + 5 personnes),
// puis demande du détail exact à l'API (frais de livraison calculés depuis l'adresse).
function commandeMettreAJourPrixEstime() {
  if (!menuCommandeCourant) return;
  var nombre = parseInt(document.getElementById('commande-nombre-personne').value, 10) || 0;
  var prixUnitaire = parseFloat(menuCommandeCourant.prixParPersonne) || 0;

  var reductionApplicable = nombre >= (menuCommandeCourant.nombrePersonneMinimum + 5);
  if (reductionApplicable) prixUnitaire = Math.round(prixUnitaire * 0.9 * 100) / 100;

  document.getElementById('commande-recap-detail-menu').textContent = nombre + ' pers. x ' + euros(prixUnitaire);
  document.getElementById('commande-recap-menu').textContent = euros(prixUnitaire * nombre);
  document.getElementById('commande-reduction-info').classList.toggle('d-none', !reductionApplicable);

  devisCourant = null;
  document.getElementById('commande-recap-livraison').textContent = '…';
  document.getElementById('commande-recap-detail-livraison').textContent = '';
  document.getElementById('commande-prix-estime').textContent = '…';

  clearTimeout(minuteurDevis);
  minuteurDevis = setTimeout(demanderDevis, 600);
}

// Appel API : POST /api/commande/devis -> détail exact (c'est aussi ce calcul qui sera appliqué à la commande).
async function demanderDevis() {
  var adresse = document.getElementById('commande-adresse').value.trim();
  var nombre = parseInt(document.getElementById('commande-nombre-personne').value, 10) || 0;
  var livraisonEl = document.getElementById('commande-recap-livraison');
  var totalEl = document.getElementById('commande-prix-estime');

  if (adresse.length < 8 || nombre < menuCommandeCourant.nombrePersonneMinimum) {
    livraisonEl.textContent = '—';
    totalEl.textContent = '—';
    document.getElementById('commande-recap-detail-livraison').textContent = adresse.length < 8 ? "(saisissez l'adresse)" : '';
    return;
  }

  try {
    const reponse = await fetch(API_URL + "/commande/devis", {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-AUTH-TOKEN": localStorage.getItem('token') },
      body: JSON.stringify({ menuId: menuCommandeCourant.id, nombrePersonne: nombre, adresse: adresse })
    });
    const data = await reponse.json().catch(() => null);

    if (!reponse.ok) {
      livraisonEl.textContent = '—';
      totalEl.textContent = '—';
      document.getElementById('commande-recap-detail-livraison').textContent = '(' + messageErreurApi(data, 'adresse à vérifier') + ')';
      return;
    }

    devisCourant = data;
    document.getElementById('commande-recap-menu').textContent = euros(data.prixMenu);
    document.getElementById('commande-recap-detail-livraison').textContent = data.livraison.horsBordeaux
      ? '(' + data.livraison.ville + ', ' + String(data.livraison.distanceKm).replace('.', ',') + ' km)'
      : '(Bordeaux)';
    livraisonEl.textContent = data.livraison.montant > 0 ? euros(data.livraison.montant) : 'Offerte';
    totalEl.textContent = euros(data.total);
  } catch (error) {
    console.error(error);
    livraisonEl.textContent = '—';
    totalEl.textContent = '—';
  }
}

function ouvrirCommandeModal(menu) {
  // Visiteur non connecté : il doit se connecter (ou créer un compte) avant de commander.
  const token = localStorage.getItem('token');
  if (!token) {
    alert('Connectez-vous ou créez un compte pour passer commande.');
    naviguerSPA('/connexion');
    return;
  }

  if (menu.quantiteRestante !== null && menu.quantiteRestante !== undefined && menu.quantiteRestante <= 0) {
    alert("Ce menu n'est plus disponible à la commande (stock épuisé).");
    return;
  }

  menuCommandeCourant = menu;
  devisCourant = null;
  commandeMasquerMessages();
  document.getElementById('btn-confirmer-commande').disabled = false;

  document.getElementById('commandeModalTitre').textContent = 'Commander : ' + menu.titleMenu;
  var demain = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
  document.getElementById('commande-date-prestation').min = demain;
  document.getElementById('commande-date-prestation').value = '';
  document.getElementById('commande-heure-livraison').value = '';
  document.getElementById('commande-nombre-personne').value = menu.nombrePersonneMinimum;
  document.getElementById('commande-nombre-personne').min = menu.nombrePersonneMinimum;
  document.getElementById('commande-min-personnes-info').textContent = menu.nombrePersonneMinimum + ' personnes minimum pour ce menu.';

  // Rappel des conditions du menu, à accepter explicitement.
  var conditionsWrap = document.getElementById('commande-conditions-wrap');
  document.getElementById('commande-conditions').textContent = menu.conditions || '';
  document.getElementById('commande-conditions-ok').checked = false;
  conditionsWrap.classList.toggle('d-none', !menu.conditions);

  document.getElementById('commande-adresse').value = '';
  document.getElementById('commande-adresse-info').textContent = '';
  ['commande-nom', 'commande-prenom', 'commande-email', 'commande-gsm'].forEach(function (id) {
    document.getElementById(id).value = '';
  });
  preRemplirInfosClient();
  commandeMettreAJourPrixEstime();

  var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('commandeModal'));
  modal.show();
}

// Appel API : GET /api/account/me — nom, prénom, email et GSM du client sont repris de son compte,
// ainsi que son adresse (modifiable) pour le lieu de livraison.
async function preRemplirInfosClient() {
  try {
    const reponse = await fetch(API_URL + "/account/me", {
      method: "GET",
      headers: { "X-AUTH-TOKEN": localStorage.getItem('token') }
    });

    if (!reponse.ok) return;

    const user = await reponse.json();
    document.getElementById('commande-nom').value = user.lastName || '';
    document.getElementById('commande-prenom').value = user.firstName || '';
    document.getElementById('commande-email').value = user.email || '';
    document.getElementById('commande-gsm').value = user.phoneNumber || '';

    var champAdresse = document.getElementById('commande-adresse');
    if (user.adresse && !champAdresse.value) {
      champAdresse.value = user.adresse;
      document.getElementById('commande-adresse-info').textContent = 'Adresse reprise depuis votre profil, modifiable si besoin.';
      commandeMettreAJourPrixEstime();
    }
  } catch (error) {
    console.error(error);
  }
}

document.getElementById('commande-nombre-personne').addEventListener('input', commandeMettreAJourPrixEstime);
document.getElementById('commande-adresse').addEventListener('input', commandeMettreAJourPrixEstime);

document.getElementById('btn-confirmer-commande').addEventListener('click', function () {
  if (!menuCommandeCourant) return;
  commandeMasquerMessages();

  var datePrestation = document.getElementById('commande-date-prestation').value;
  var heureLivraison = document.getElementById('commande-heure-livraison').value;
  var adresse = document.getElementById('commande-adresse').value.trim();
  var nombrePersonne = parseInt(document.getElementById('commande-nombre-personne').value, 10);

  if (!datePrestation) {
    commandeAfficherErreur('La date de prestation est obligatoire.');
    return;
  }
  if (!heureLivraison) {
    commandeAfficherErreur("L'heure de livraison souhaitée est obligatoire.");
    return;
  }
  if (!adresse) {
    commandeAfficherErreur("L'adresse de livraison est obligatoire.");
    return;
  }
  if (!nombrePersonne || nombrePersonne < menuCommandeCourant.nombrePersonneMinimum) {
    commandeAfficherErreur('Le nombre de personnes minimum pour ce menu est de ' + menuCommandeCourant.nombrePersonneMinimum + '.');
    return;
  }
  if (menuCommandeCourant.conditions && !document.getElementById('commande-conditions-ok').checked) {
    commandeAfficherErreur('Merci de lire et d\'accepter les conditions du menu.');
    return;
  }
  if (!devisCourant) {
    commandeAfficherErreur("Le prix n'a pas encore pu être calculé : vérifiez l'adresse de livraison.");
    demanderDevis();
    return;
  }

  // Aucun prix n'est envoyé : c'est l'API qui calcule tout (menu, réduction, livraison).
  const payload = {
    menuId: menuCommandeCourant.id,
    datePrestation: datePrestation,
    heureLivraison: heureLivraison,
    adresse: adresse,
    nombrePersonne: nombrePersonne
  };

  var bouton = document.getElementById('btn-confirmer-commande');
  bouton.disabled = true;

  fetch(API_URL + "/commande", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-AUTH-TOKEN": localStorage.getItem('token')
    },
    body: JSON.stringify(payload)
  })
    .then(async (reponse) => {
      const data = await reponse.json().catch(() => null);
      if (!reponse.ok) {
        commandeAfficherErreur(messageErreurApi(data, "Une erreur est survenue lors de la commande."));
        bouton.disabled = false;
        return;
      }

      const succes = document.getElementById('commandeModalSuccess');
      succes.textContent = 'Commande n°' + data.id + ' enregistrée (' + euros(data.montantTotal) + ') ! Un email de confirmation vous a été envoyé. Vous pouvez suivre votre commande depuis votre profil.';
      succes.classList.remove('d-none');

      // Le stock affiché du menu diminue.
      if (menuCommandeCourant.quantiteRestante !== null && menuCommandeCourant.quantiteRestante !== undefined) {
        menuCommandeCourant.quantiteRestante--;
      }

      setTimeout(function () {
        var modal = bootstrap.Modal.getInstance(document.getElementById('commandeModal'));
        if (modal) modal.hide();
      }, 3500);
    })
    .catch((error) => {
      console.error(error);
      commandeAfficherErreur("Une erreur est survenue lors de la commande.");
      bouton.disabled = false;
    });
});

})();
