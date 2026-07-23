
(function () {
// ===============================//
//        Navbar du profil        //
//================================//

document.querySelectorAll('.profil-nav-link').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.profil-nav-link').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
    document.querySelectorAll('.profil-panel').forEach(function (p) { p.classList.add('d-none'); });
    document.getElementById('panel-' + btn.dataset.target).classList.remove('d-none');
  });
});
// ===============================//
//recuperation (get) infos du profil //
//================================//
async function chargerProfil() {
  try {
    const reponse = await fetch("http://127.0.0.1:8000/api/account/me", {
      method: "GET",
      headers: {
        "X-AUTH-TOKEN": localStorage.getItem('token')
      }
    });

    if (!reponse.ok) {
      console.error("Impossible de récupérer le profil.");
      return;
    }

    const user = await reponse.json();

    document.getElementById('profil-fullname').textContent = `${user.firstName} ${user.lastName}`;
    document.getElementById('profil-Prenom').value = `${user.lastName}`;
    document.getElementById('profil-Nom').value = `${user.firstName}`;
    document.getElementById('profil-Telephone').value = user.phoneNumber ?? '';
    document.getElementById('profil-Adresse').value = user.adresse ?? '';
    document.getElementById('profil-role').textContent = `${user.role.libelle}`;
   
    const Initial = `${user.firstName [0]} . ${user.lastName [0]}`;
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
  const infosRemove = {
    prenom: document.getElementById('profil-Prenom').value,
    nom: document.getElementById('profil-Nom').value,
    telephone: document.getElementById('profil-Telephone').value,
    adresse: document.getElementById('profil-Adresse').value,
    allergy: document.getElementById('profil-Allergy').value
  };

  try {
    const reponse = await modifierInfosProfil(infosRemove);

    if (!reponse.ok) {
      const errorData = await reponse.json();
      console.error('Erreur de validation :', errorData);
      alert("La mise à jour des informations personnelles a échoué");
      return;
    }

    infosFields.forEach(function (field) { field.setAttribute('readonly', true); });
    btnToggleInfo.textContent = 'Modifier';
    btnToggleInfo.dataset.mode = 'view';

  } catch (error) {
    console.error(error);
    alert("Impossible de contacter le serveur.");
  }
});

function modifierInfosProfil(infosRemove) {
    const myHeaders = new Headers();
    myHeaders.append("Content-Type", "application/json");
    myHeaders.append("X-AUTH-TOKEN", localStorage.getItem('token'));

    const raw = JSON.stringify({
        "firstName": infosRemove.prenom,
        "lastName": infosRemove.nom,
        "phoneNumber": infosRemove.telephone,
        // "email": infosRemove.mail,
        "adresse": infosRemove.adresse,
        "allergy": infosRemove.allergy
    });

    const requestOptions = {
        method: "PUT",
        headers: myHeaders,
        body: raw,
        redirect: "follow"
    };

    return fetch("http://127.0.0.1:8000/api/account/edit", requestOptions);
}

              // ===============================//
              // section Mes commandes  //
              //================================//

// Statut réel stocké côté API ("En attente"/"Confirmée"/"Terminée"/"Annulée") -> classe
// CSS du badge (voir .profil-statut-* dans le CSS).
const MA_COMMANDE_BADGE_CLASS = {
  'En attente': 'profil-statut-attente',
  'Confirmée': 'profil-statut-confirmee',
  'Terminée': 'profil-statut-terminee',
  'Annulée': 'profil-statut-annulee'
};

function formaterDateMaCommande(commande) {
  var date = commande.datePrestation ? commande.datePrestation.split('-').reverse().join('/') : '—';
  return commande.heureLivraison ? date + ' · ' + commande.heureLivraison : date;
}

function formaterPrixMaCommande(commande) {
  var prixUnitaire = parseFloat(commande.prixMenu);
  var total = (isNaN(prixUnitaire) ? 0 : prixUnitaire * (commande.nombrePersonne || 0)) + (parseFloat(commande.prixLivraison) || 0);
  return total.toFixed(2) + ' €';
}

function creerCarteMaCommande(commande) {
  var badgeClass = MA_COMMANDE_BADGE_CLASS[commande.statut] || 'profil-statut-attente';
  var div = document.createElement('div');
  div.className = 'commande-card p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2';
  div.innerHTML =
    '<div>' +
      '<p class="commande-menu mb-1">' + (commande.menu ? commande.menu.titleMenu : 'Menu') + '</p>' +
      '<p class="commande-details mb-0">Prestation le ' + formaterDateMaCommande(commande) + ' · ' + (commande.nombrePersonne != null ? commande.nombrePersonne : '—') + ' personnes</p>' +
      (commande.adresse ? '<p class="commande-details mb-0"><i class="bi bi-geo-alt"></i> ' + commande.adresse + '</p>' : '') +
    '</div>' +
    '<div class="text-end">' +
      '<span class="profil-statut ' + badgeClass + '">' + commande.statut + '</span>' +
      '<p class="commande-prix mb-0 mt-1">' + formaterPrixMaCommande(commande) + '</p>' +
    '</div>';
  return div;
}

// Appel API : GET /api/commande/me — mes commandes (utilisateur connecté)
async function chargerMesCommandes() {
  var container = document.getElementById('profil-commandes-list');
  if (!container) return;

  try {
    const reponse = await fetch("http://127.0.0.1:8000/api/commande/me", {
      method: "GET",
      headers: {
        "X-AUTH-TOKEN": localStorage.getItem('token')
      }
    });

    if (!reponse.ok) {
      container.innerHTML = '<p class="text-center profil-empty-hint mb-0">Impossible de charger vos commandes.</p>';
      return;
    }

    const commandes = await reponse.json();
    container.innerHTML = '';

    if (!commandes.length) {
      container.innerHTML = '<p class="text-center profil-empty-hint mb-0">Vous n\'avez pas encore passé de commande.</p>';
      return;
    }

    commandes.forEach(function (commande) {
      container.appendChild(creerCarteMaCommande(commande));
    });
  } catch (error) {
    console.error(error);
    container.innerHTML = '<p class="text-center profil-empty-hint mb-0">Impossible de contacter le serveur.</p>';
  }
}

chargerMesCommandes();

              // ===============================//
              // section sécurité  //
              //================================//
// ===============================//
//   Sécurité - afficher/masquer  //
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

// ===============================//
//  Sécurité - force du mot de passe //
//================================//

function niveauMotDePasse(valeur) {
  let level = 0;
  const regexMin = /[a-z]/;
  const regexMaj = /[A-Z]/;
  const regexChiffre = /\d/;
  const regexSpec = /[^a-zA-Z0-9]/;
  if (valeur.length >= 8) level++;
  if (regexMin.test(valeur) && regexMaj.test(valeur)) level++;
  if (regexChiffre.test(valeur)) level++;
  if (regexSpec.test(valeur)) level++;
  return level;
}

function majBarreForce(level) {
  const bar = document.getElementById('profil-strength-bar');
  bar.className = 'ins-strength';
  if (level > 0) bar.classList.add('s' + level);
}

const newPasswordField = document.getElementById('profil-NewPassword');
newPasswordField.addEventListener('keyup', () => {
  majBarreForce(niveauMotDePasse(newPasswordField.value));
});

// ===============================//
//  Sécurité - envoi du changement //
//================================//

const btnSavePassword = document.getElementById('btn-profil-save-password');

btnSavePassword.addEventListener('click', async function () {
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

  if (niveauMotDePasse(newPassword) < 3) {
    alert("Le nouveau mot de passe est trop faible.");
    return;
  }

  try {
    const reponse = await fetch("http://127.0.0.1:8000/api/account/edit", {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        "X-AUTH-TOKEN": localStorage.getItem('token')
      },
      body: JSON.stringify({
        "password": newPassword,
        "currentPassword": currentPassword
      })
    });

    if (!reponse.ok) {
      const errorData = await reponse.json();
      console.error('Erreur de validation :', errorData);
      alert("La mise à jour du mot de passe a échoué.");
      return;
    }

    document.getElementById('profilPasswordForm').reset();
    document.getElementById('profil-strength-bar').className = 'ins-strength';
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

formAjoutAvis.addEventListener('show.bs.collapse', function () {
  avisAddForm.reset();
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

// Construit la carte d'un avis à partir de son statut réel
function creerCarteAvis(avis) {
  const badge = AVIS_BADGE[avis.statut] || AVIS_BADGE['En attente'];

  const div = document.createElement('div');
  div.className = 'avis-card p-3 mb-3';
  div.innerHTML =
    '<div class="d-flex justify-content-between align-items-start mb-2">' +
      '<div class="stars">' + etoiles(avis.note) + '</div>' +
      '<span class="dash-avis-statut ' + badge.classe + '">' + badge.texte + '</span>' +
    '</div>' +
    '<p class="avis-text mb-0"></p>';
  div.querySelector('.avis-text').textContent = avis.description || '';

  return div;
}

// Ajoute la carte du nouvel avis en tête de liste (l'avis est toujours créé
// avec statut = "En attente" : "en attente de validation" tant qu'un employé ou un
// admin ne l'a pas publié depuis le tableau de bord)
function ajouterAvisDansListe(avis) {
  const emptyHint = document.getElementById('profil-avis-empty');
  if (emptyHint) emptyHint.remove();

  avisList.insertBefore(creerCarteAvis(avis), avisList.firstChild);

  nbAvis++;
  avisCount.textContent = nbAvis;
}

// Appel API : GET /api/avis/me — recharge mes avis existants au chargement de la page
async function chargerAvis() {
  try {
    const reponse = await fetch("http://127.0.0.1:8000/api/avis/me", {
      method: "GET",
      headers: {
        "X-AUTH-TOKEN": localStorage.getItem('token')
      }
    });

    if (!reponse.ok) {
      console.error("Impossible de récupérer les avis.");
      return;
    }

    const avisRecus = await reponse.json();
    const emptyHint = document.getElementById('profil-avis-empty');
    if (emptyHint) emptyHint.remove();

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

async function ajouterAvis(note, description) {
  const reponse = await fetch("http://127.0.0.1:8000/api/avis", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-AUTH-TOKEN": localStorage.getItem('token')
    },
    body: JSON.stringify({ note: note, description: description })
  });

  if (!reponse.ok) {
    throw new Error("Impossible d'ajouter l'avis.");
  }

  return reponse.json();
}

document.getElementById('btn-save-avis').addEventListener('click', async function () {
  const note = parseInt(document.getElementById('avis-note').value, 10);
  const description = document.getElementById('avis-description').value.trim();

  avisAddError.classList.add('d-none');

  if (!description) {
    avisAddError.textContent = "Merci de laisser un commentaire.";
    avisAddError.classList.remove('d-none');
    return;
  }

  try {
    const avisCree = await ajouterAvis(note, description);
    ajouterAvisDansListe(avisCree);
    bootstrap.Collapse.getOrCreateInstance(formAjoutAvis).hide();
  } catch (error) {
    console.error(error);
    avisAddError.textContent = "Impossible d'envoyer ton avis. Vérifie ta connexion ou réessaie plus tard.";
    avisAddError.classList.remove('d-none');
  }
});

})();
