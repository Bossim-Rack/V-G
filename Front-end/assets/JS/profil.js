(function () {
// ===============================//
//        Navbar du profil        //
//================================//

function ouvrirSection(cible) {
  document.querySelectorAll('.profil-nav-link').forEach(function (b) {
    b.classList.toggle('active', b.dataset.target === cible);
  });
  document.querySelectorAll('.profil-panel').forEach(function (p) { p.classList.add('d-none'); });
  document.getElementById('panel-' + cible).classList.remove('d-none');
}

document.querySelectorAll('.profil-nav-link').forEach(function (btn) {
  btn.addEventListener('click', function () { ouvrirSection(btn.dataset.target); });
});

function enTetes(avecJson) {
  var h = { "X-AUTH-TOKEN": localStorage.getItem('token') };
  if (avecJson) h["Content-Type"] = "application/json";
  return h;
}

// ===============================//
//recuperation (get) infos du profil //
//================================//
async function chargerProfil() {
  try {
    const reponse = await fetch(API_URL + "/account/me", { method: "GET", headers: enTetes() });

    if (!reponse.ok) {
      console.error("Impossible de récupérer le profil.");
      return;
    }

    const user = await reponse.json();

    document.getElementById('profil-fullname').textContent = `${user.firstName} ${user.lastName}`;
    document.getElementById('profil-Prenom').value = user.firstName ?? '';
    document.getElementById('profil-Nom').value = user.lastName ?? '';
    document.getElementById('profil-Telephone').value = user.phoneNumber ?? '';
    document.getElementById('profil-Adresse').value = user.adresse ?? '';
    document.getElementById('profil-Allergy').value = user.allergy ?? '';
    document.getElementById('profil-role').textContent = user.role ? user.role.libelle : '';
    if (user.createdAt) {
      document.getElementById('profil-since').textContent = 'Membre depuis le ' + formaterDateFr(user.createdAt.slice(0, 10));
    }

    const Initial = `${user.firstName[0]}.${user.lastName[0]}`;
    document.getElementById('profil-initials').textContent = Initial.toUpperCase();
  } catch (error) {
    console.error(error);
  }
}

chargerProfil();

// ===============================//
//Bouton-section-information-personnelle //
//================================//

const btnToggleInfo = document.getElementById('btn-profil-toggle-infos');
const infosFields = document.getElementById('profilInfosForm').querySelectorAll('input');

btnToggleInfo.addEventListener('click', async function () {
  const isEditing = btnToggleInfo.dataset.mode === 'edit';

  if (!isEditing) {
    // passage en mode édition
    infosFields.forEach(function (field) { field.removeAttribute('readonly'); });
    infosFields[0].focus();
    btnToggleInfo.textContent = 'Enregistrer les changements';
    btnToggleInfo.dataset.mode = 'edit';
    return;
  }

  try {
    const reponse = await fetch(API_URL + "/account/edit", {
      method: "PUT",
      headers: enTetes(true),
      body: JSON.stringify({
        "firstName": document.getElementById('profil-Prenom').value,
        "lastName": document.getElementById('profil-Nom').value,
        "phoneNumber": document.getElementById('profil-Telephone').value,
        "adresse": document.getElementById('profil-Adresse').value,
        "allergy": document.getElementById('profil-Allergy').value
      })
    });

    if (!reponse.ok) {
      const errorData = await reponse.json().catch(() => null);
      alert(messageErreurApi(errorData, "La mise à jour des informations personnelles a échoué"));
      return;
    }

    infosFields.forEach(function (field) { field.setAttribute('readonly', true); });
    btnToggleInfo.textContent = 'Modifier';
    btnToggleInfo.dataset.mode = 'view';
    chargerProfil();
  } catch (error) {
    console.error(error);
    alert("Impossible de contacter le serveur.");
  }
});

              // ===============================//
              // section Mes commandes  //
              //================================//

let mesCommandesCache = [];

function formaterDateMaCommande(commande) {
  var date = formaterDateFr(commande.datePrestation);
  return commande.heureLivraison ? date + ' · ' + commande.heureLivraison : date;
}

function euros(montant) {
  return Number(montant || 0).toFixed(2).replace('.', ',') + ' €';
}

// Suivi : chaque changement de statut avec sa date et son heure.
function suiviHtml(commande) {
  if (!commande.historique || !commande.historique.length) return '';
  return '<ul class="suivi-commande mt-2">' +
    commande.historique.map(function (h) {
      return '<li><strong>' + echapperHtml(h.statut) + '</strong> <span class="suivi-date">le ' + echapperHtml(formaterDateFr(h.date)) + '</span>' +
        (h.commentaire ? '<br><span class="text-muted">' + echapperHtml(h.commentaire) + '</span>' : '') + '</li>';
    }).join('') +
  '</ul>';
}

function creerCarteMaCommande(commande) {
  var badgeClass = CLASSES_STATUT_COMMANDE[commande.statut] || 'profil-statut-attente';
  var livraison = parseFloat(commande.prixLivraison) || 0;
  var div = document.createElement('div');
  div.className = 'commande-card p-3 mb-3';
  div.dataset.id = commande.id;

  var actions = '';
  if (commande.modifiableParClient) {
    actions += '<button type="button" class="btn btn-sm btn-connexion js-modifier-commande">Modifier</button> ' +
               '<button type="button" class="btn btn-sm btn-outline-danger js-annuler-commande">Annuler la commande</button>';
  }
  if (commande.statut === 'Terminée' && !commande.avisDonne) {
    actions += '<button type="button" class="btn btn-sm btn-connexion js-donner-avis"><i class="bi bi-star"></i> Donner mon avis</button>';
  }

  // Le suivi détaillé est disponible dès qu'un employé a accepté la commande.
  var suiviDisponible = commande.statut !== 'En attente';

  div.innerHTML =
    '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2">' +
      '<div>' +
        '<p class="commande-menu mb-1">Commande n°' + commande.id + ' — ' + echapperHtml(commande.menu ? commande.menu.titleMenu : 'Menu') + '</p>' +
        '<p class="commande-details mb-0">Prestation le ' + echapperHtml(formaterDateMaCommande(commande)) + ' · ' + (commande.nombrePersonne != null ? commande.nombrePersonne : '—') + ' personnes</p>' +
        (commande.adresse ? '<p class="commande-details mb-0"><i class="bi bi-geo-alt"></i> ' + echapperHtml(commande.adresse) + '</p>' : '') +
        '<p class="commande-details mb-0 small text-muted">Menu : ' + euros(parseFloat(commande.prixMenu) * commande.nombrePersonne) +
          ' (' + euros(commande.prixMenu) + '/pers.) · Livraison : ' + (livraison > 0 ? euros(livraison) : 'offerte') + '</p>' +
        (commande.motifAnnulation ? '<p class="commande-details mb-0 small text-danger">Motif d\'annulation : ' + echapperHtml(commande.motifAnnulation) + '</p>' : '') +
      '</div>' +
      '<div class="text-end">' +
        '<span class="profil-statut ' + badgeClass + '">' + echapperHtml(commande.statut) + '</span>' +
        '<p class="commande-prix mb-0 mt-1">' + euros(commande.montantTotal) + '</p>' +
      '</div>' +
    '</div>' +
    (actions ? '<div class="mt-2 d-flex flex-wrap gap-2">' + actions + '</div>' : '') +
    (suiviDisponible
      ? '<details class="mt-2"><summary class="small">Suivi de la commande</summary>' + suiviHtml(commande) + '</details>'
      : '<p class="small text-muted mt-2 mb-0">En attente de validation par notre équipe : vous pouvez encore la modifier ou l\'annuler.</p>');
  return div;
}

// Appel API : GET /api/commande/me — mes commandes (utilisateur connecté), avec leur suivi
async function chargerMesCommandes() {
  var container = document.getElementById('profil-commandes-list');
  if (!container) return;

  try {
    const reponse = await fetch(API_URL + "/commande/me", { method: "GET", headers: enTetes() });

    if (!reponse.ok) {
      container.innerHTML = '<p class="text-center profil-empty-hint mb-0">Impossible de charger vos commandes.</p>';
      return;
    }

    mesCommandesCache = await reponse.json();
    container.innerHTML = '';

    if (!mesCommandesCache.length) {
      container.innerHTML = '<p class="text-center profil-empty-hint mb-0">Vous n\'avez pas encore passé de commande.</p>';
    } else {
      mesCommandesCache.forEach(function (commande) {
        container.appendChild(creerCarteMaCommande(commande));
      });
    }

    peuplerCommandesPourAvis();
  } catch (error) {
    console.error(error);
    container.innerHTML = '<p class="text-center profil-empty-hint mb-0">Impossible de contacter le serveur.</p>';
  }
}

chargerMesCommandes();

// --- Actions sur une commande (délégation d'événements) ---
let commandeEnModification = null;

document.getElementById('profil-commandes-list').addEventListener('click', async function (event) {
  var carte = event.target.closest('.commande-card');
  if (!carte) return;
  var commande = mesCommandesCache.find(function (c) { return String(c.id) === carte.dataset.id; });
  if (!commande) return;

  if (event.target.closest('.js-annuler-commande')) {
    if (!confirm('Annuler la commande n°' + commande.id + ' ? Cette action est définitive.')) return;
    try {
      const reponse = await fetch(API_URL + "/commande/" + commande.id + "/annuler", {
        method: "POST",
        headers: enTetes(true),
        body: JSON.stringify({})
      });
      const data = await reponse.json().catch(() => null);
      if (!reponse.ok) {
        alert(messageErreurApi(data, "Impossible d'annuler cette commande."));
      }
      chargerMesCommandes();
    } catch (error) {
      console.error(error);
      alert("Impossible de contacter le serveur.");
    }
    return;
  }

  if (event.target.closest('.js-modifier-commande')) {
    commandeEnModification = commande;
    document.getElementById('modif-date').value = commande.datePrestation || '';
    document.getElementById('modif-date').min = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
    document.getElementById('modif-heure').value = commande.heureLivraison || '';
    document.getElementById('modif-adresse').value = commande.adresse || '';
    document.getElementById('modif-personnes').value = commande.nombrePersonne || '';
    var minimum = commande.menu ? commande.menu.nombrePersonneMinimum : 1;
    document.getElementById('modif-personnes').min = minimum;
    document.getElementById('modif-personnes-info').textContent = minimum + ' personnes minimum pour ce menu.';
    document.getElementById('modif-erreur').classList.add('d-none');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modifCommandeModal')).show();
    return;
  }

  if (event.target.closest('.js-donner-avis')) {
    ouvrirSection('avis');
    document.getElementById('avis-commande').value = String(commande.id);
    bootstrap.Collapse.getOrCreateInstance(document.getElementById('form-ajout-avis')).show();
  }
});

// Appel API : PUT /api/commande/{id} — possible tant que la commande est "En attente"
document.getElementById('btn-modif-commande-enregistrer').addEventListener('click', async function () {
  if (!commandeEnModification) return;
  var erreur = document.getElementById('modif-erreur');
  erreur.classList.add('d-none');

  try {
    const reponse = await fetch(API_URL + "/commande/" + commandeEnModification.id, {
      method: "PUT",
      headers: enTetes(true),
      body: JSON.stringify({
        datePrestation: document.getElementById('modif-date').value,
        heureLivraison: document.getElementById('modif-heure').value,
        adresse: document.getElementById('modif-adresse').value.trim(),
        nombrePersonne: parseInt(document.getElementById('modif-personnes').value, 10)
      })
    });
    const data = await reponse.json().catch(() => null);

    if (!reponse.ok) {
      erreur.textContent = messageErreurApi(data, "La modification a échoué.");
      erreur.classList.remove('d-none');
      return;
    }

    bootstrap.Modal.getInstance(document.getElementById('modifCommandeModal')).hide();
    chargerMesCommandes();
  } catch (error) {
    console.error(error);
    erreur.textContent = "Impossible de contacter le serveur.";
    erreur.classList.remove('d-none');
  }
});

              // ===============================//
              // section sécurité  //
              //================================//

function togglePasswordVisibility(input) {
  input.type = input.type === 'password' ? 'text' : 'password';
}

document.getElementById('btn-hide-CurrentPassword').addEventListener('click', () => {
  togglePasswordVisibility(document.getElementById('profil-CurrentPassword'));
});
document.getElementById('btn-hide-NewPassword').addEventListener('click', () => {
  togglePasswordVisibility(document.getElementById('profil-NewPassword'));
});
document.getElementById('btn-hide-ConfirmNewPassword').addEventListener('click', () => {
  togglePasswordVisibility(document.getElementById('profil-ConfirmNewPassword'));
});

function majBarreForce(level) {
  const bar = document.getElementById('profil-strength-bar');
  bar.className = 'ins-strength';
  if (level > 0) bar.classList.add('s' + level);
}

const newPasswordField = document.getElementById('profil-NewPassword');
newPasswordField.addEventListener('input', () => {
  majBarreForce(verifierMotDePasse(newPasswordField.value).niveau);
});

document.getElementById('btn-profil-save-password').addEventListener('click', async function () {
  const currentPassword = document.getElementById('profil-CurrentPassword').value;
  const newPassword = document.getElementById('profil-NewPassword').value;
  const confirmPassword = document.getElementById('profil-ConfirmNewPassword').value;

  if (!currentPassword || !newPassword || !confirmPassword) {
    alert("Merci de remplir tous les champs.");
    return;
  }
  if (newPassword !== confirmPassword) {
    alert("Le nouveau mot de passe et sa confirmation ne correspondent pas.");
    return;
  }
  if (!verifierMotDePasse(newPassword).valide) {
    alert("Le nouveau mot de passe doit contenir au moins 10 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.");
    return;
  }

  try {
    const reponse = await fetch(API_URL + "/account/edit", {
      method: "PUT",
      headers: enTetes(true),
      body: JSON.stringify({ "password": newPassword, "currentPassword": currentPassword })
    });

    if (!reponse.ok) {
      const errorData = await reponse.json().catch(() => null);
      alert(messageErreurApi(errorData, "La mise à jour du mot de passe a échoué."));
      return;
    }

    document.getElementById('profilPasswordForm').reset();
    majBarreForce(0);
    alert("Mot de passe mis à jour avec succès.");
  } catch (error) {
    console.error(error);
    alert("Impossible de contacter le serveur.");
  }
});

              // ===============================//
              // section avis  //
              //================================//

const formAjoutAvis = document.getElementById('form-ajout-avis');
const avisAddForm = document.getElementById('avisAddForm');
const avisAddError = document.getElementById('avis-add-error');
const avisList = document.getElementById('profil-avis-list');
const avisCount = document.getElementById('profil-avis-count');
let nbAvis = 0;

// Un avis se donne sur une commande "Terminée" pour laquelle aucun avis n'a encore été donné.
function commandesSansAvis() {
  return mesCommandesCache.filter(function (c) { return c.statut === 'Terminée' && !c.avisDonne; });
}

function peuplerCommandesPourAvis() {
  var select = document.getElementById('avis-commande');
  var eligibles = commandesSansAvis();
  select.innerHTML = eligibles.length
    ? eligibles.map(function (c) {
        return '<option value="' + c.id + '">n°' + c.id + ' — ' + echapperHtml(c.menu ? c.menu.titleMenu : 'Menu') + ' (' + echapperHtml(formaterDateFr(c.datePrestation)) + ')</option>';
      }).join('')
    : '<option value="">Aucune commande terminée en attente d\'avis</option>';
  document.getElementById('btn-save-avis').disabled = !eligibles.length;
}

formAjoutAvis.addEventListener('show.bs.collapse', function () {
  var commandeChoisie = document.getElementById('avis-commande').value;
  avisAddForm.reset();
  if (commandeChoisie) document.getElementById('avis-commande').value = commandeChoisie;
  avisAddError.classList.add('d-none');
});

formAjoutAvis.addEventListener('shown.bs.collapse', function () {
  document.getElementById('ajouter-avis-icon').className = 'bi bi-dash-lg';
  document.getElementById('ajouter-avis-label').textContent = 'Fermer';
});

formAjoutAvis.addEventListener('hidden.bs.collapse', function () {
  document.getElementById('ajouter-avis-icon').className = 'bi bi-plus-lg';
  document.getElementById('ajouter-avis-label').textContent = 'Ajouter un avis';
});

function etoiles(note) {
  return '★'.repeat(note) + '☆'.repeat(5 - note);
}

const AVIS_BADGE = {
  'En attente': { classe: 'dash-avis-attente', texte: 'En attente de validation' },
  'Publié': { classe: 'dash-avis-publie', texte: 'Publié' },
  'Refusé': { classe: 'dash-avis-refuse', texte: 'Refusé' }
};

function creerCarteAvis(avis) {
  const badge = AVIS_BADGE[avis.statut] || AVIS_BADGE['En attente'];

  const div = document.createElement('div');
  div.className = 'avis-card p-3 mb-3';
  div.innerHTML =
    '<div class="d-flex justify-content-between align-items-start mb-2">' +
      '<div class="stars" aria-label="Note : ' + avis.note + ' sur 5">' + etoiles(avis.note) + '</div>' +
      '<span class="dash-avis-statut ' + badge.classe + '">' + badge.texte + '</span>' +
    '</div>' +
    (avis.commande ? '<p class="small text-muted mb-1">Commande n°' + avis.commande.id + ' — ' + echapperHtml(avis.commande.menu || '') + '</p>' : '') +
    '<p class="avis-text mb-0"></p>';
  div.querySelector('.avis-text').textContent = avis.description || '';

  return div;
}

// Appel API : GET /api/avis/me
async function chargerAvis() {
  try {
    const reponse = await fetch(API_URL + "/avis/me", { method: "GET", headers: enTetes() });

    if (!reponse.ok) {
      console.error("Impossible de récupérer les avis.");
      return;
    }

    const avisRecus = await reponse.json();
    avisList.innerHTML = '';

    avisRecus.forEach(function (avis) {
      avisList.appendChild(creerCarteAvis(avis));
    });

    nbAvis = avisRecus.length;
    avisCount.textContent = nbAvis;

    if (nbAvis === 0) {
      avisList.innerHTML = '<p class="text-center profil-empty-hint mb-0" id="profil-avis-empty">Vous n\'avez pas encore laissé d\'avis.</p>';
    }
  } catch (error) {
    console.error(error);
  }
}

chargerAvis();

document.getElementById('btn-save-avis').addEventListener('click', async function () {
  const commandeId = parseInt(document.getElementById('avis-commande').value, 10);
  const note = parseInt(document.getElementById('avis-note').value, 10);
  const description = document.getElementById('avis-description').value.trim();

  avisAddError.classList.add('d-none');

  if (!commandeId) {
    avisAddError.textContent = "Choisissez la commande sur laquelle porte votre avis.";
    avisAddError.classList.remove('d-none');
    return;
  }
  if (!description) {
    avisAddError.textContent = "Merci de laisser un commentaire.";
    avisAddError.classList.remove('d-none');
    return;
  }

  try {
    const reponse = await fetch(API_URL + "/avis", {
      method: "POST",
      headers: enTetes(true),
      body: JSON.stringify({ commandeId: commandeId, note: note, description: description })
    });
    const data = await reponse.json().catch(() => null);

    if (!reponse.ok) {
      avisAddError.textContent = messageErreurApi(data, "Impossible d'envoyer ton avis.");
      avisAddError.classList.remove('d-none');
      return;
    }

    bootstrap.Collapse.getOrCreateInstance(formAjoutAvis).hide();
    chargerAvis();
    chargerMesCommandes();
  } catch (error) {
    console.error(error);
    avisAddError.textContent = "Impossible d'envoyer ton avis. Vérifie ta connexion ou réessaie plus tard.";
    avisAddError.classList.remove('d-none');
  }
});

              // ===============================//
              // section Supprimer mon compte  //
              //================================//

// Appel API : DELETE /api/account (mot de passe obligatoire pour confirmer)
document.getElementById('btn-profil-delete').addEventListener('click', async function () {
  const erreur = document.getElementById('profil-delete-erreur');
  const motDePasse = document.getElementById('profil-delete-password').value;
  erreur.classList.add('d-none');

  if (document.getElementById('profil-confirm-delete').value.trim() !== 'SUPPRIMER') {
    erreur.textContent = 'Tape SUPPRIMER (en majuscules) pour confirmer.';
    erreur.classList.remove('d-none');
    return;
  }
  if (!motDePasse) {
    erreur.textContent = 'Saisis ton mot de passe pour confirmer.';
    erreur.classList.remove('d-none');
    return;
  }
  if (!confirm('Dernière confirmation : supprimer définitivement ton compte ?')) return;

  const bouton = this;
  bouton.disabled = true;

  try {
    const reponse = await fetch(API_URL + "/account", {
      method: "DELETE",
      headers: enTetes(true),
      body: JSON.stringify({ password: motDePasse })
    });

    if (!reponse.ok) {
      const data = await reponse.json().catch(() => null);
      erreur.textContent = messageErreurApi(data, 'La suppression du compte a échoué.');
      erreur.classList.remove('d-none');
      bouton.disabled = false;
      return;
    }

    // Compte supprimé : on déconnecte et on revient à l'accueil.
    localStorage.removeItem('token');
    localStorage.removeItem('roles');
    alert('Ton compte a bien été supprimé.');
    window.location.href = '/';
  } catch (error) {
    console.error(error);
    erreur.textContent = 'Impossible de contacter le serveur.';
    erreur.classList.remove('d-none');
    bouton.disabled = false;
  }
});

})();
