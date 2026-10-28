(function(){
                          //========================//
                          // Récupération info profil  actuelle //
                          //========================//

async function chargerProfil() {
  try {
    const reponse = await fetch(API_URL + "/account/me", {
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
    document.getElementById('profil-role').textContent = `${user.role.libelle}`;
    const Initial = `${user.firstName [0]} . ${user.lastName [0]}`;
    document.getElementById('profil-initials').textContent = Initial.toUpperCase();

    // La section "Utilisateurs" (gestion des comptes) est réservée à l'admin,
    // contrairement aux autres onglets accessibles aux employés.
    const estAdmin = user.role && user.role.libelle === 'Admin';
    const navUtilisateurs = document.getElementById('nav-item-utilisateurs');
    if (navUtilisateurs) navUtilisateurs.classList.toggle('d-none', !estAdmin);
    const navStats = document.getElementById('nav-item-stats');
    if (navStats) navStats.classList.toggle('d-none', !estAdmin);
    if (estAdmin) {
      chargerUtilisateurs();
      initialiserStatistiques();
    }

  } catch (error) {
    console.error(error);
  }
}

chargerProfil();


                          //========================//
                          // Stat "Clients inscrits" (vue d'ensemble) //
                          //========================//

// Appel API : GET /api/user/count-clients (réservé admin/employé côté back)
// Ne compte que les comptes ayant le rôle "Client", ni les employés ni les admins.
async function chargerStatClients() {
  const stat = document.getElementById('stat-clients-inscrits');
  if (!stat) return;

  try {
    const reponse = await fetch(API_URL + "/user/count-clients", {
      method: "GET",
      headers: {
        "X-AUTH-TOKEN": localStorage.getItem('token')
      }
    });

    if (!reponse.ok) {
      console.error("Impossible de récupérer le nombre de clients inscrits.");
      return;
    }

    const data = await reponse.json();
    stat.textContent = data.count;
  } catch (error) {
    console.error(error);
  }
}

chargerStatClients();


document.querySelectorAll('.profil-nav-link').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.profil-nav-link').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
    document.querySelectorAll('.profil-panel').forEach(function (p) { p.classList.add('d-none'); });
    document.getElementById('panel-' + btn.dataset.target).classList.remove('d-none');
  });
});



                          //========================//
                          // Avis (modération) //
                          //========================//

function etoiles(note) {
  return '★'.repeat(note) + '☆'.repeat(5 - note);
}

// Statut à trois états : "En attente" (badge orange, actions possibles),
// "Publié" (badge vert) ou "Refusé" (badge rouge) — plus d'actions une fois tranché.
const AVIS_BADGE_CLASS = {
  'En attente': 'dash-avis-attente',
  'Publié': 'dash-avis-publie',
  'Refusé': 'dash-avis-refuse'
};

function creerCarteAvisModeration(avis) {
  const enAttente = avis.statut === 'En attente';
  const badgeClass = AVIS_BADGE_CLASS[avis.statut] || 'dash-avis-attente';

  const div = document.createElement('div');
  div.className = 'avis-card p-3 mb-3';
  div.dataset.id = avis.id;

  let boutons = '';
  if (enAttente) {
    boutons =
      '<div class="d-flex gap-2 mt-2">' +
        '<button type="button" class="btn btn-sm btn-connexion js-publier-avis">Publier</button>' +
        '<button type="button" class="btn btn-sm btn-outline-danger js-refuser-avis">Refuser</button>' +
      '</div>';
  }

  div.innerHTML =
    '<div class="d-flex justify-content-between align-items-start mb-2">' +
      '<div class="stars">' + etoiles(avis.note) + '</div>' +
      '<span class="dash-avis-statut ' + badgeClass + '">' + avis.statut + '</span>' +
    '</div>' +
    '<p class="small text-muted mb-1">' +
      echapperHtml(avis.user ? (avis.user.firstName + ' ' + avis.user.lastName) : '') +
      (avis.commande ? ' — commande n°' + avis.commande.id + ' (' + echapperHtml(avis.commande.menu || '') + ')' : '') +
    '</p>' +
    '<p class="avis-text mb-0"></p>' +
    boutons;
  div.querySelector('.avis-text').textContent = avis.description || '';

  return div;
}

// Met à jour la carte statistique "Avis à modérer" de la Vue d'ensemble
function majStatAvisAttente(count) {
  const stat = document.getElementById('stat-avis-attente');
  if (stat) stat.textContent = count;
}

function decrementerStatAvisAttente() {
  const stat = document.getElementById('stat-avis-attente');
  if (!stat) return;
  const valeur = parseInt(stat.textContent, 10);
  if (!isNaN(valeur) && valeur > 0) stat.textContent = valeur - 1;
}

// Appel API : GET /api/avis (réservé admin/employé côté back)
async function chargerAvisModeration() {
  const container = document.getElementById('avis-liste');

  try {
    const reponse = await fetch(API_URL + "/avis", {
      method: "GET",
      headers: {
        "X-AUTH-TOKEN": localStorage.getItem('token')
      }
    });

    if (!reponse.ok) {
      container.innerHTML = '<p class="text-center profil-empty-hint mb-0">Impossible de charger les avis.</p>';
      return;
    }

    const avisRecus = await reponse.json();
    container.innerHTML = '';

    const nbEnAttente = avisRecus.filter(function (avis) { return avis.statut === 'En attente'; }).length;
    majStatAvisAttente(nbEnAttente);

    if (!avisRecus.length) {
      container.innerHTML = '<p class="text-center profil-empty-hint mb-0">Aucun avis pour le moment.</p>';
      return;
    }

    avisRecus.forEach(function (avis) {
      container.appendChild(creerCarteAvisModeration(avis));
    });
  } catch (error) {
    console.error(error);
    container.innerHTML = '<p class="text-center profil-empty-hint mb-0">Impossible de contacter le serveur.</p>';
  }
}

chargerAvisModeration();

// Appel API : PUT /api/avis/{id} — fait basculer le statut (réservé admin/employé côté back)
async function changerStatutAvis(id, statut) {
  const reponse = await fetch(API_URL + "/avis/" + id, {
    method: "PUT",
    headers: {
      "Content-Type": "application/json",
      "X-AUTH-TOKEN": localStorage.getItem('token')
    },
    body: JSON.stringify({ statut: statut })
  });

  if (!reponse.ok) {
    throw new Error("Impossible de mettre à jour le statut de l'avis.");
  }
}

function appliquerStatutSurCarte(carte, statut) {
  const badge = carte.querySelector('.dash-avis-statut');
  badge.className = 'dash-avis-statut ' + (AVIS_BADGE_CLASS[statut] || 'dash-avis-attente');
  badge.textContent = statut;

  const boutons = carte.querySelector('.d-flex.gap-2');
  if (boutons) boutons.remove();

  // La carte quitte l'état "En attente" (publiée ou refusée) : un avis de moins à modérer.
  decrementerStatAvisAttente();
}

document.getElementById('avis-liste').addEventListener('click', function (event) {
  const publierBtn = event.target.closest('.js-publier-avis');
  const refuserBtn = event.target.closest('.js-refuser-avis');

  if (publierBtn) {
    const carte = publierBtn.closest('.avis-card');

    changerStatutAvis(carte.dataset.id, 'Publié')
      .then(function () { appliquerStatutSurCarte(carte, 'Publié'); })
      .catch(function (error) {
        console.error(error);
        alert("Impossible de publier cet avis.");
      });
    return;
  }

  if (refuserBtn) {
    const carte = refuserBtn.closest('.avis-card');

    if (!confirm('Refuser cet avis ? Il ne sera pas affiché sur le site.')) return;

    changerStatutAvis(carte.dataset.id, 'Refusé')
      .then(function () { appliquerStatutSurCarte(carte, 'Refusé'); })
      .catch(function (error) {
        console.error(error);
        alert("Impossible de refuser cet avis.");
      });
  }
});



                          //========================//
                          // Compte  //
                          //========================//

const ROLE_BADGE_CLASS = {
  'Client': 'dash-role-client',
  'Employé': 'dash-role-employe',
  'Admin': 'dash-role-admin'
};

function creerLigneUtilisateur(user) {
  const tr = document.createElement('tr');
  const nomComplet = (user.firstName || '') + ' ' + (user.lastName || '');
  const badgeClass = ROLE_BADGE_CLASS[user.role] || 'dash-role-client';

  const statut = user.isActive === false
    ? '<span class="dash-avis-statut dash-avis-refuse">Désactivé</span>'
    : (user.isVerified === false ? '<span class="dash-avis-statut dash-avis-attente">Email non confirmé</span>' : '<span class="dash-avis-statut dash-avis-publie">Actif</span>');

  tr.innerHTML =
    '<td></td>' +
    '<td></td>' +
    '<td><span class="dash-role-badge ' + badgeClass + '"></span></td>' +
    '<td>' + statut + '</td>' +
    '<td><button type="button" class="btn btn-sm dash-table-btn js-edit-user" data-id="' + user.id + '" data-name="' + echapperHtml(nomComplet.trim()) + '" data-email="' + echapperHtml(user.email || '') + '" data-telephone="' + echapperHtml(user.phoneNumber || '') + '" data-role="' + echapperHtml(user.role || '') + '" data-since="' + echapperHtml(user.createdAt || '') + '" data-active="' + (user.isActive === false ? '0' : '1') + '">Modifier</button></td>';

  tr.children[0].textContent = nomComplet.trim();
  tr.children[1].textContent = user.email || '';
  tr.querySelector('.dash-role-badge').textContent = user.role || '';

  return tr;
}

// Appel API : GET /api/user (réservé admin côté back)
async function chargerUtilisateurs() {
  const tbody = document.getElementById('users-tbody');
  if (!tbody) return;

  try {
    const reponse = await fetch(API_URL + "/user", {
      method: "GET",
      headers: {
        "X-AUTH-TOKEN": localStorage.getItem('token')
      }
    });

    if (!reponse.ok) {
      tbody.innerHTML = '<tr><td colspan="5" class="profil-empty-hint">Impossible de charger les comptes.</td></tr>';
      return;
    }

    const users = await reponse.json();
    tbody.innerHTML = '';

    if (!users.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="profil-empty-hint">Aucun compte pour le moment.</td></tr>';
      return;
    }

    users.forEach(function (user) {
      tbody.appendChild(creerLigneUtilisateur(user));
    });
  } catch (error) {
    console.error(error);
    tbody.innerHTML = '<tr><td colspan="5" class="profil-empty-hint">Impossible de contacter le serveur.</td></tr>';
  }
}

// Appel API : POST /api/user/create-employe (réservé admin côté back)
async function creerEmploye(prenom, nom, email, password) {
  const reponse = await fetch(API_URL + "/user/create-employe", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-AUTH-TOKEN": localStorage.getItem('token')
    },
    body: JSON.stringify({ firstName: prenom, lastName: nom, email: email, password: password })
  });

  if (!reponse.ok) {
    const errorData = await reponse.json().catch(function () { return {}; });
    throw new Error(errorData.message || (errorData.errors && Object.values(errorData.errors).join(' ')) || "Impossible de créer l'employé.");
  }
}

document.getElementById('btn-create-employe').addEventListener('click', async function () {
  const prenom = document.getElementById('employe-prenom').value.trim();
  const nom = document.getElementById('employe-nom').value.trim();
  const email = document.getElementById('employe-email').value.trim();
  const password = document.getElementById('employe-password').value;
  const errorEl = document.getElementById('employe-add-error');
  errorEl.classList.add('d-none');

  if (!prenom || !nom || !email || !password) {
    errorEl.textContent = 'Merci de remplir tous les champs.';
    errorEl.classList.remove('d-none');
    return;
  }

  try {
    await creerEmploye(prenom, nom, email, password);
    document.getElementById('employeAddForm').reset();
    chargerUtilisateurs();
  } catch (error) {
    console.error(error);
    errorEl.textContent = error.message;
    errorEl.classList.remove('d-none');
  }
});

// Délégation d'évènements : fonctionne aussi bien sur les comptes chargés
// dynamiquement que sur d'éventuelles lignes déjà présentes dans le DOM.
document.getElementById('users-tbody').addEventListener('click', function (event) {
  const btn = event.target.closest('.js-edit-user');
  if (!btn) return;

  var name = btn.dataset.name;
  var parts = name.split(' ');
  var initials = (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();

  document.getElementById('detail-user-initials').textContent = initials;
  document.getElementById('detail-user-name').textContent = name;
  document.getElementById('detail-user-role-badge').textContent = btn.dataset.role;
  document.getElementById('detail-user-since').textContent = 'Membre depuis le ' + btn.dataset.since;
  document.getElementById('detail-Prenom').value = parts[0] || '';
  document.getElementById('detail-Nom').value = parts.slice(1).join(' ') || '';
  document.getElementById('detail-Email').value = btn.dataset.email;
  document.getElementById('detail-Telephone').value = btn.dataset.telephone || '';
  document.getElementById('detail-Role').value = btn.dataset.role;
  document.getElementById('userDetailForm').dataset.id = btn.dataset.id;
  document.getElementById('userDetailForm').dataset.role = btn.dataset.role;
  afficherStatutCompte(btn.dataset.active === '1', btn.dataset.role === 'Admin');

  // on repart toujours en mode lecture seule à l'ouverture d'un compte
  var btnToggle = document.getElementById('btn-toggle-user-detail');
  btnToggle.textContent = 'Modifier';
  btnToggle.dataset.mode = 'view';
  document.getElementById('userDetailForm').querySelectorAll('input, select').forEach(function (f) {
    if (f.tagName === 'SELECT') { f.disabled = true; } else { f.readOnly = true; }
  });

  document.getElementById('users-list-view').classList.add('d-none');
  document.getElementById('user-detail-view').classList.remove('d-none');
});

document.getElementById('btn-back-to-users').addEventListener('click', function () {
  document.getElementById('user-detail-view').classList.add('d-none');
  document.getElementById('users-list-view').classList.remove('d-none');
});

                          //========================//
                          // Activer / désactiver un compte //
                          //========================//

function afficherStatutCompte(actif, estAdmin) {
  var zone = document.getElementById('user-activation-zone');
  // Un compte administrateur ne peut pas être désactivé.
  zone.classList.toggle('d-none', estAdmin);
  document.getElementById('detail-user-statut').textContent = actif ? 'actif' : 'désactivé';
  var btn = document.getElementById('btn-toggle-user-actif');
  btn.textContent = actif ? 'Désactiver le compte' : 'Réactiver le compte';
  btn.dataset.actif = actif ? '1' : '0';
}

// Appel API : PUT /api/user/{id} avec isActive (réservé admin côté back)
document.getElementById('btn-toggle-user-actif').addEventListener('click', async function () {
  var id = document.getElementById('userDetailForm').dataset.id;
  var actif = this.dataset.actif === '1';
  var nom = document.getElementById('detail-user-name').textContent;
  if (actif && !confirm('Désactiver le compte de ' + nom + ' ? Il ne pourra plus se connecter.')) return;

  try {
    await modifierUtilisateur(id, { isActive: !actif });
    afficherStatutCompte(!actif, false);
    chargerUtilisateurs();
  } catch (error) {
    console.error(error);
    alert(error.message);
  }
});



                          //========================//
                          // modifier / renregistrer  (syteme de bascule) //
                          //========================//

// Appel API : PUT /api/user/{id} (réservé admin côté back)
async function modifierUtilisateur(id, infos) {
  const reponse = await fetch(API_URL + "/user/" + id, {
    method: "PUT",
    headers: {
      "Content-Type": "application/json",
      "X-AUTH-TOKEN": localStorage.getItem('token')
    },
    body: JSON.stringify(infos)
  });

  if (!reponse.ok) {
    const errorData = await reponse.json().catch(function () { return {}; });
    throw new Error(errorData.message || (errorData.errors && Object.values(errorData.errors).join(' ')) || 'Impossible de modifier ce compte.');
  }
}

// Même bascule Modifier / Enregistrer les changements que sur la page Profil,
// mais avec un vrai appel API à l'enregistrement (PUT /api/user/{id}).
var btnToggleUserDetail = document.getElementById('btn-toggle-user-detail');
btnToggleUserDetail.addEventListener('click', async function () {
  var isEditing = btnToggleUserDetail.dataset.mode === 'edit';

  if (!isEditing) {
    // passage en mode édition (le rôle d'un administrateur reste verrouillé, l'email n'est pas modifiable)
    var estCompteAdmin = document.getElementById('userDetailForm').dataset.role === 'Admin';
    document.getElementById('userDetailForm').querySelectorAll('input, select').forEach(function (f) {
      if (f.id === 'detail-Email') return;
      if (f.tagName === 'SELECT') { f.disabled = estCompteAdmin; } else { f.readOnly = false; }
    });
    btnToggleUserDetail.textContent = 'Enregistrer les changements';
    btnToggleUserDetail.dataset.mode = 'edit';
    return;
  }

  var id = document.getElementById('userDetailForm').dataset.id;
  if (!id) return;

  var infos = {
    firstName: document.getElementById('detail-Prenom').value.trim(),
    lastName: document.getElementById('detail-Nom').value.trim(),
    phoneNumber: document.getElementById('detail-Telephone').value.trim()
  };
  if (document.getElementById('userDetailForm').dataset.role !== 'Admin') {
    infos.role = document.getElementById('detail-Role').value;
  }

  try {
    await modifierUtilisateur(id, infos);

    document.getElementById('userDetailForm').querySelectorAll('input, select').forEach(function (f) {
      if (f.tagName === 'SELECT') { f.disabled = true; } else { f.readOnly = true; }
    });
    btnToggleUserDetail.textContent = 'Modifier';
    btnToggleUserDetail.dataset.mode = 'view';

    document.getElementById('detail-user-name').textContent = infos.firstName + ' ' + infos.lastName;
    document.getElementById('detail-user-role-badge').textContent = infos.role;

    chargerUtilisateurs();
  } catch (error) {
    console.error(error);
    alert(error.message);
  }
});

// Appel API : DELETE /api/user/{id} (réservé admin côté back)
document.getElementById('btn-delete-user').addEventListener('click', async function () {
  var id = document.getElementById('userDetailForm').dataset.id;
  if (!id) return;

  var nom = document.getElementById('detail-user-name').textContent;
  if (!confirm('Supprimer définitivement le compte de ' + nom + ' ? Cette action est irréversible.')) return;

  try {
    const reponse = await fetch(API_URL + "/user/" + id, {
      method: "DELETE",
      headers: {
        "X-AUTH-TOKEN": localStorage.getItem('token')
      }
    });

    if (!reponse.ok) {
      const errorData = await reponse.json().catch(function () { return {}; });
      alert(errorData.message || 'Impossible de supprimer ce compte.');
      return;
    }

    document.getElementById('user-detail-view').classList.add('d-none');
    document.getElementById('users-list-view').classList.remove('d-none');

    chargerUtilisateurs();
  } catch (error) {
    console.error(error);
    alert('Impossible de contacter le serveur.');
  }
});




                          //========================//
                          // Plats //
                          //========================//

let platsCache = [];
let allergenesCache = [];
let platEditId = null;       // null = création, sinon id du plat en cours de modification
let platPhotoData = undefined; // data URI base64 en attente d'envoi (undefined = pas de changement)

function badgesAllergenes(allergenes) {
  if (!allergenes || allergenes.length === 0) {
    return '<span class="profil-empty-hint">Aucun</span>';
  }
  return allergenes.map(function (a) {
    return '<span class="dash-role-badge dash-role-client">' + a.libelle + '</span>';
  }).join(' ');
}

function creerLignePlat(plat) {
  var tr = document.createElement('tr');
  tr.innerHTML =
    '<td>' + plat.titlePlat + '</td>' +
    '<td>' + badgesAllergenes(plat.allergenes) + '</td>' +
    '<td><button type="button" class="btn btn-sm dash-table-btn js-edit-plat" data-id="' + plat.id + '">Modifier</button></td>';
  return tr;
}

function afficherPlats() {
  var tbody = document.getElementById('plats-tbody');
  if (!tbody) return;
  tbody.innerHTML = '';
  if (platsCache.length === 0) {
    tbody.innerHTML = '<tr><td colspan="3" class="profil-empty-hint">Aucun plat pour le moment.</td></tr>';
    return;
  }
  platsCache.forEach(function (plat) {
    tbody.appendChild(creerLignePlat(plat));
  });
}

async function chargerPlats() {
  var tbody = document.getElementById('plats-tbody');
  try {
    const reponse = await fetch(API_URL + "/plat", {
      method: "GET",
      headers: { "X-AUTH-TOKEN": localStorage.getItem('token') }
    });

    if (!reponse.ok) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="3" class="profil-empty-hint">Impossible de charger les plats.</td></tr>';
      return;
    }

    platsCache = await reponse.json();
    afficherPlats();
  } catch (error) {
    console.error(error);
    if (tbody) tbody.innerHTML = '<tr><td colspan="3" class="profil-empty-hint">Impossible de charger les plats.</td></tr>';
  }
}

chargerPlats();

function afficherAllergenesCheckboxes(checkedIds) {
  var container = document.getElementById('plat-allergenes-list');
  if (!container) return;
  if (allergenesCache.length === 0) {
    container.innerHTML = '<p class="profil-empty-hint mb-0">Aucun allergène disponible.</p>';
    return;
  }
  container.innerHTML = allergenesCache.map(function (a) {
    var domId = 'allergene-' + a.id;
    var checked = (checkedIds || []).includes(a.id) ? 'checked' : '';
    return '<div class="form-check">' +
      '<input class="form-check-input" type="checkbox" value="' + a.id + '" id="' + domId + '" ' + checked + '>' +
      '<label class="form-check-label" for="' + domId + '">' + a.libelle + '</label>' +
      '</div>';
  }).join('');
}

async function chargerAllergenes(checkedIds) {
  var container = document.getElementById('plat-allergenes-list');
  try {
    const reponse = await fetch(API_URL + "/allergene", {
      method: "GET",
      headers: { "X-AUTH-TOKEN": localStorage.getItem('token') }
    });

    if (!reponse.ok) {
      if (container) container.innerHTML = '<p class="profil-empty-hint mb-0">Impossible de charger les allergènes.</p>';
      return;
    }

    allergenesCache = await reponse.json();
    afficherAllergenesCheckboxes(checkedIds);
  } catch (error) {
    console.error(error);
    if (container) container.innerHTML = '<p class="profil-empty-hint mb-0">Impossible de charger les allergènes.</p>';
  }
}

function platMasquerErreur() {
  var errorEl = document.getElementById('plat-detail-error');
  errorEl.textContent = '';
  errorEl.classList.add('d-none');
}

function platAfficherErreur(message) {
  var errorEl = document.getElementById('plat-detail-error');
  errorEl.textContent = message;
  errorEl.classList.remove('d-none');
}

function openPlatDetail(mode, plat) {
  var titleEl = document.getElementById('plat-detail-title');
  var deleteBtn = document.getElementById('btn-delete-plat');
  var saveBtn = document.getElementById('btn-save-plat');

  platMasquerErreur();
  platPhotoData = undefined;
  document.getElementById('detail-plat-photo-input').value = '';

  document.getElementById('detail-plat-titre').value = mode === 'edit' ? plat.titlePlat : '';
  document.getElementById('detail-plat-description').value = mode === 'edit' ? (plat.description || '') : '';
  document.getElementById('detail-plat-photo-preview').src = mode === 'edit' ? (plat.photoPlat || '') : '';

  var checkedIds = mode === 'edit' ? (plat.allergenes || []).map(function (a) { return a.id; }) : [];
  chargerAllergenes(checkedIds);

  if (mode === 'create') {
    platEditId = null;
    titleEl.textContent = 'Ajouter un plat';
    deleteBtn.classList.add('d-none');
    saveBtn.textContent = 'Créer le plat';
  } else {
    platEditId = plat.id;
    titleEl.textContent = 'Modifier le plat';
    deleteBtn.classList.remove('d-none');
    saveBtn.textContent = 'Enregistrer';
  }

  document.getElementById('plats-list-view').classList.add('d-none');
  document.getElementById('plat-detail-view').classList.remove('d-none');
}

// Délégation d'événements : les lignes de #plats-tbody sont générées
// dynamiquement (fetch API), un binding statique .forEach ne les verrait pas.
document.getElementById('plats-tbody').addEventListener('click', function (e) {
  var btn = e.target.closest('.js-edit-plat');
  if (!btn) return;
  var id = parseInt(btn.dataset.id, 10);
  var plat = platsCache.find(function (p) { return p.id === id; });
  if (plat) openPlatDetail('edit', plat);
});

document.getElementById('btn-add-plat').addEventListener('click', function () {
  openPlatDetail('create', {});
});


                          //========================//
                          // Photo du plat (aperçu + conversion en base64) //
                          //========================//

document.getElementById('detail-plat-photo-input').addEventListener('change', function (e) {
  var file = e.target.files[0];
  if (!file) return;

  var lecteur = new FileReader();
  lecteur.onload = function () {
    // Data URI (ex: "data:image/png;base64,....") envoyée telle quelle à
    // l'API et stockée/renvoyée telle quelle : utilisable directement comme
    // src d'une balise <img>, sans étape d'upload séparée.
    platPhotoData = lecteur.result;
    document.getElementById('detail-plat-photo-preview').src = platPhotoData;
  };
  lecteur.readAsDataURL(file);
});

document.getElementById('btn-back-to-plats').addEventListener('click', function () {
  document.getElementById('plat-detail-view').classList.add('d-none');
  document.getElementById('plats-list-view').classList.remove('d-none');
});


                          //========================//
                          // Enregistrer / Supprimer un plat //
                          //========================//

async function creerOuModifierPlat() {
  var titre = document.getElementById('detail-plat-titre').value.trim();
  if (!titre) {
    platAfficherErreur('Le titre du plat est obligatoire.');
    return;
  }
  platMasquerErreur();

  var description = document.getElementById('detail-plat-description').value.trim();
  var allergenesChoisis = Array.from(document.querySelectorAll('#plat-allergenes-list .form-check-input:checked'))
    .map(function (cb) { return parseInt(cb.value, 10); });

  var payload = {
    titlePlat: titre,
    description: description,
    allergenes: allergenesChoisis
  };
  if (platPhotoData) {
    payload.photoPlat = platPhotoData;
  }

  var estEdition = platEditId !== null;
  var url = API_URL + "/plat" + (estEdition ? "/" + platEditId : "");
  var methode = estEdition ? "PUT" : "POST";

  try {
    const reponse = await fetch(url, {
      method: methode,
      headers: {
        "Content-Type": "application/json",
        "X-AUTH-TOKEN": localStorage.getItem('token')
      },
      body: JSON.stringify(payload)
    });

    if (!reponse.ok) {
      platAfficherErreur("Une erreur est survenue lors de l'enregistrement du plat.");
      return;
    }

    document.getElementById('plat-detail-view').classList.add('d-none');
    document.getElementById('plats-list-view').classList.remove('d-none');
    chargerPlats();
  } catch (error) {
    console.error(error);
    platAfficherErreur("Une erreur est survenue lors de l'enregistrement du plat.");
  }
}

document.getElementById('btn-save-plat').addEventListener('click', creerOuModifierPlat);

document.getElementById('btn-delete-plat').addEventListener('click', async function () {
  if (platEditId === null) return;

  try {
    const reponse = await fetch(API_URL + "/plat/" + platEditId, {
      method: "DELETE",
      headers: { "X-AUTH-TOKEN": localStorage.getItem('token') }
    });

    if (!reponse.ok) {
      platAfficherErreur('Une erreur est survenue lors de la suppression du plat.');
      return;
    }

    document.getElementById('plat-detail-view').classList.add('d-none');
    document.getElementById('plats-list-view').classList.remove('d-none');
    chargerPlats();
  } catch (error) {
    console.error(error);
    platAfficherErreur('Une erreur est survenue lors de la suppression du plat.');
  }
});


                          //========================//
                          // Menus //
                          //========================//

// Visuel de repli pour les menus sans photo enregistrée.
const MENU_IMAGE_PLACEHOLDER = "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=600&q=60";

let menusCache = [];
let themesCache = [];
let menuEditId = null;       // null = création, sinon id du menu en cours de modification
let menuPhotoData = undefined; // data URI base64 en attente d'envoi (undefined = pas de changement)

function creerCarteMenu(menu) {
  var col = document.createElement('div');
  col.className = 'col-12 col-md-6 col-xl-4';

  var prix = parseFloat(menu.prixParPersonne);
  var prixTexte = isNaN(prix) ? menu.prixParPersonne : prix.toFixed(2);

  col.innerHTML =
    '<div class="menu-card">' +
      '<img src="' + (menu.photoMenu || MENU_IMAGE_PLACEHOLDER) + '" class="menu-card-img" alt="' + menu.titleMenu + '">' +
      '<div class="menu-card-body">' +
        '<h3 class="menu-card-title">' + menu.titleMenu.toUpperCase() + '</h3>' +
        '<p class="menu-card-text">' + (menu.description || '') + '</p>' +
      '</div>' +
      '<div class="menu-card-footer d-flex justify-content-between align-items-center">' +
        '<p class="menu-card-prix mb-0">' + prixTexte + ' €/pers.</p>' +
        '<button type="button" class="btn btn-sm dash-table-btn js-edit-menu" data-id="' + menu.id + '">Modifier</button>' +
      '</div>' +
    '</div>';

  return col;
}

function afficherMenus() {
  var conteneur = document.getElementById('menus-list');
  if (!conteneur) return;
  conteneur.innerHTML = '';
  if (menusCache.length === 0) {
    conteneur.innerHTML = '<div class="col-12"><p class="profil-empty-hint mb-0">Aucun menu pour le moment.</p></div>';
    return;
  }
  menusCache.forEach(function (menu) {
    conteneur.appendChild(creerCarteMenu(menu));
  });
}

async function chargerMenus() {
  var conteneur = document.getElementById('menus-list');
  try {
    const reponse = await fetch(API_URL + "/menu", {
      method: "GET",
      headers: { "X-AUTH-TOKEN": localStorage.getItem('token') }
    });

    if (!reponse.ok) {
      if (conteneur) conteneur.innerHTML = '<div class="col-12"><p class="profil-empty-hint mb-0">Impossible de charger les menus.</p></div>';
      return;
    }

    menusCache = await reponse.json();
    afficherMenus();
  } catch (error) {
    console.error(error);
    if (conteneur) conteneur.innerHTML = '<div class="col-12"><p class="profil-empty-hint mb-0">Impossible de charger les menus.</p></div>';
  }
}

chargerMenus();

function afficherPlatsCheckboxesMenu(checkedIds) {
  var conteneur = document.getElementById('menu-plats-list');
  if (!conteneur) return;
  if (platsCache.length === 0) {
    conteneur.innerHTML = '<p class="profil-empty-hint mb-0">Aucun plat disponible. Ajoutez d\'abord des plats dans la section "Plats".</p>';
    return;
  }
  conteneur.innerHTML = platsCache.map(function (plat) {
    var domId = 'menu-plat-' + plat.id;
    var checked = (checkedIds || []).includes(plat.id) ? 'checked' : '';
    return '<div class="form-check">' +
      '<input class="form-check-input" type="checkbox" value="' + plat.id + '" id="' + domId + '" ' + checked + '>' +
      '<label class="form-check-label" for="' + domId + '">' + plat.titlePlat + '</label>' +
      '</div>';
  }).join('');
}

// Réutilise/rafraîchit platsCache (chargé par la section "Plats" plus haut
// dans ce même script) pour peupler les cases à cocher "Plats inclus".
async function chargerPlatsPourMenu(checkedIds) {
  await chargerPlats();
  afficherPlatsCheckboxesMenu(checkedIds);
}

// Ids des thèmes cochés, conservés à part car la liste affichée peut être
// filtrée par la recherche (on ne veut pas perdre les cases cochées
// masquées par le filtre).
let menuThemesCheckedIds = [];

function themeExisteDeja(libelle) {
  var recherche = libelle.trim().toLowerCase();
  return themesCache.some(function (theme) { return theme.libelle.trim().toLowerCase() === recherche; });
}

function afficherThemesCheckboxes(filtre) {
  var conteneur = document.getElementById('menu-themes-list');
  if (!conteneur) return;

  var recherche = (filtre || '').trim().toLowerCase();
  var themesAffiches = recherche
    ? themesCache.filter(function (t) { return t.libelle.toLowerCase().includes(recherche); })
    : themesCache;

  if (themesCache.length === 0) {
    conteneur.innerHTML = '<p class="profil-empty-hint mb-0">Aucun thème disponible.</p>';
    return;
  }
  if (themesAffiches.length === 0) {
    conteneur.innerHTML = '<p class="profil-empty-hint mb-0">Aucun thème ne correspond à la recherche.</p>';
    return;
  }

  conteneur.innerHTML = themesAffiches.map(function (theme) {
    var domId = 'menu-theme-' + theme.id;
    var checked = menuThemesCheckedIds.includes(theme.id) ? 'checked' : '';
    return '<div class="form-check">' +
      '<input class="form-check-input" type="checkbox" value="' + theme.id + '" id="' + domId + '" ' + checked + '>' +
      '<label class="form-check-label" for="' + domId + '">' + theme.libelle + '</label>' +
      '</div>';
  }).join('');
}

async function chargerThemesPourMenu(checkedIds) {
  var conteneur = document.getElementById('menu-themes-list');
  menuThemesCheckedIds = checkedIds || [];
  document.getElementById('menu-theme-recherche').value = '';
  document.getElementById('btn-creer-theme').classList.add('d-none');

  try {
    const reponse = await fetch(API_URL + "/theme", {
      method: "GET",
      headers: { "X-AUTH-TOKEN": localStorage.getItem('token') }
    });

    if (!reponse.ok) {
      if (conteneur) conteneur.innerHTML = '<p class="profil-empty-hint mb-0">Impossible de charger les thèmes.</p>';
      return;
    }

    themesCache = await reponse.json();
    afficherThemesCheckboxes('');
  } catch (error) {
    console.error(error);
    if (conteneur) conteneur.innerHTML = '<p class="profil-empty-hint mb-0">Impossible de charger les thèmes.</p>';
  }
}

// Case cochée/décochée -> on tient menuThemesCheckedIds à jour (la liste
// affichée peut être filtrée, donc on ne peut pas se fier uniquement au DOM).
document.getElementById('menu-themes-list').addEventListener('change', function (e) {
  var cb = e.target.closest('.form-check-input');
  if (!cb) return;
  var id = parseInt(cb.value, 10);
  if (cb.checked) {
    if (!menuThemesCheckedIds.includes(id)) menuThemesCheckedIds.push(id);
  } else {
    menuThemesCheckedIds = menuThemesCheckedIds.filter(function (v) { return v !== id; });
  }
});

// Recherche d'un thème : filtre la liste, et propose de créer le thème
// directement en base s'il n'existe pas encore.
document.getElementById('menu-theme-recherche').addEventListener('input', function (e) {
  var valeur = e.target.value;
  afficherThemesCheckboxes(valeur);

  var btnCreer = document.getElementById('btn-creer-theme');
  var texte = valeur.trim();
  if (texte && !themeExisteDeja(texte)) {
    btnCreer.textContent = 'Créer le thème « ' + texte + ' »';
    btnCreer.classList.remove('d-none');
  } else {
    btnCreer.classList.add('d-none');
  }
});

document.getElementById('btn-creer-theme').addEventListener('click', async function () {
  var input = document.getElementById('menu-theme-recherche');
  var libelle = input.value.trim();
  if (!libelle || themeExisteDeja(libelle)) return;

  try {
    const reponse = await fetch(API_URL + "/theme", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-AUTH-TOKEN": localStorage.getItem('token')
      },
      body: JSON.stringify({ libelle: libelle })
    });

    if (!reponse.ok) {
      menuAfficherErreur("Une erreur est survenue lors de la création du thème.");
      return;
    }

    const nouveauTheme = await reponse.json();
    themesCache.push(nouveauTheme);
    menuThemesCheckedIds.push(nouveauTheme.id);

    input.value = '';
    document.getElementById('btn-creer-theme').classList.add('d-none');
    afficherThemesCheckboxes('');
  } catch (error) {
    console.error(error);
    menuAfficherErreur("Une erreur est survenue lors de la création du thème.");
  }
});

// Régimes alimentaires (Végétarien, Sans gluten, ...) : même principe que
// les thèmes ci-dessus (liste depuis la DB + recherche + création à la
// volée si le régime cherché n'existe pas). Distinct du "Résumé allergènes"
// affiché plus haut, qui reste calculé automatiquement.
let regimesCache = [];
let menuRegimesCheckedIds = [];

function regimeExisteDeja(libelle) {
  var recherche = libelle.trim().toLowerCase();
  return regimesCache.some(function (regime) { return regime.libelle.trim().toLowerCase() === recherche; });
}

function afficherRegimesCheckboxes(filtre) {
  var conteneur = document.getElementById('menu-regimes-list');
  if (!conteneur) return;

  var recherche = (filtre || '').trim().toLowerCase();
  var regimesAffiches = recherche
    ? regimesCache.filter(function (r) { return r.libelle.toLowerCase().includes(recherche); })
    : regimesCache;

  if (regimesCache.length === 0) {
    conteneur.innerHTML = '<p class="profil-empty-hint mb-0">Aucun régime disponible.</p>';
    return;
  }
  if (regimesAffiches.length === 0) {
    conteneur.innerHTML = '<p class="profil-empty-hint mb-0">Aucun régime ne correspond à la recherche.</p>';
    return;
  }

  conteneur.innerHTML = regimesAffiches.map(function (regime) {
    var domId = 'menu-regime-' + regime.id;
    var checked = menuRegimesCheckedIds.includes(regime.id) ? 'checked' : '';
    return '<div class="form-check">' +
      '<input class="form-check-input" type="checkbox" value="' + regime.id + '" id="' + domId + '" ' + checked + '>' +
      '<label class="form-check-label" for="' + domId + '">' + regime.libelle + '</label>' +
      '</div>';
  }).join('');
}

async function chargerRegimesPourMenu(checkedIds) {
  var conteneur = document.getElementById('menu-regimes-list');
  menuRegimesCheckedIds = checkedIds || [];
  document.getElementById('menu-regime-recherche').value = '';
  document.getElementById('btn-creer-regime').classList.add('d-none');

  try {
    const reponse = await fetch(API_URL + "/regime", {
      method: "GET",
      headers: { "X-AUTH-TOKEN": localStorage.getItem('token') }
    });

    if (!reponse.ok) {
      if (conteneur) conteneur.innerHTML = '<p class="profil-empty-hint mb-0">Impossible de charger les régimes.</p>';
      return;
    }

    regimesCache = await reponse.json();
    afficherRegimesCheckboxes('');
  } catch (error) {
    console.error(error);
    if (conteneur) conteneur.innerHTML = '<p class="profil-empty-hint mb-0">Impossible de charger les régimes.</p>';
  }
}

document.getElementById('menu-regimes-list').addEventListener('change', function (e) {
  var cb = e.target.closest('.form-check-input');
  if (!cb) return;
  var id = parseInt(cb.value, 10);
  if (cb.checked) {
    if (!menuRegimesCheckedIds.includes(id)) menuRegimesCheckedIds.push(id);
  } else {
    menuRegimesCheckedIds = menuRegimesCheckedIds.filter(function (v) { return v !== id; });
  }
});

document.getElementById('menu-regime-recherche').addEventListener('input', function (e) {
  var valeur = e.target.value;
  afficherRegimesCheckboxes(valeur);

  var btnCreer = document.getElementById('btn-creer-regime');
  var texte = valeur.trim();
  if (texte && !regimeExisteDeja(texte)) {
    btnCreer.textContent = 'Créer le régime « ' + texte + ' »';
    btnCreer.classList.remove('d-none');
  } else {
    btnCreer.classList.add('d-none');
  }
});

document.getElementById('btn-creer-regime').addEventListener('click', async function () {
  var input = document.getElementById('menu-regime-recherche');
  var libelle = input.value.trim();
  if (!libelle || regimeExisteDeja(libelle)) return;

  try {
    const reponse = await fetch(API_URL + "/regime", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-AUTH-TOKEN": localStorage.getItem('token')
      },
      body: JSON.stringify({ libelle: libelle })
    });

    if (!reponse.ok) {
      menuAfficherErreur("Une erreur est survenue lors de la création du régime.");
      return;
    }

    const nouveauRegime = await reponse.json();
    regimesCache.push(nouveauRegime);
    menuRegimesCheckedIds.push(nouveauRegime.id);

    input.value = '';
    document.getElementById('btn-creer-regime').classList.add('d-none');
    afficherRegimesCheckboxes('');
  } catch (error) {
    console.error(error);
    menuAfficherErreur("Une erreur est survenue lors de la création du régime.");
  }
});

function menuMasquerErreur() {
  var errorEl = document.getElementById('menu-detail-error');
  errorEl.textContent = '';
  errorEl.classList.add('d-none');
}

function menuAfficherErreur(message) {
  var errorEl = document.getElementById('menu-detail-error');
  errorEl.textContent = message;
  errorEl.classList.remove('d-none');
}

function openMenuDetail(mode, menu) {
  var titleEl = document.getElementById('menu-detail-title');
  var deleteBtn = document.getElementById('btn-delete-menu');
  var saveBtn = document.getElementById('btn-save-menu');

  menuMasquerErreur();
  menuPhotoData = undefined;
  document.getElementById('detail-menu-photo-input').value = '';

  document.getElementById('detail-menu-titre').value = mode === 'edit' ? menu.titleMenu : '';
  document.getElementById('detail-menu-min').value = mode === 'edit' ? menu.nombrePersonneMinimum : '';
  document.getElementById('detail-menu-prix').value = mode === 'edit' ? menu.prixParPersonne : '';
  document.getElementById('detail-menu-quantite').value = mode === 'edit' ? menu.quantiteRestante : '';
  document.getElementById('detail-menu-description').value = mode === 'edit' ? (menu.description || '') : '';
  document.getElementById('detail-menu-conditions').value = mode === 'edit' ? (menu.conditions || '') : '';
  document.getElementById('detail-menu-photo-preview').src = mode === 'edit' ? (menu.photoMenu || '') : '';

  var checkedPlatIds = mode === 'edit' ? (menu.plats || []).map(function (p) { return p.id; }) : [];
  chargerPlatsPourMenu(checkedPlatIds);

  var checkedThemeIds = mode === 'edit' ? (menu.themes || []).map(function (t) { return t.id; }) : [];
  chargerThemesPourMenu(checkedThemeIds);

  var checkedRegimeIds = mode === 'edit' ? (menu.regimesAlimentaires || []).map(function (r) { return r.id; }) : [];
  chargerRegimesPourMenu(checkedRegimeIds);

  if (mode === 'create') {
    menuEditId = null;
    titleEl.textContent = 'Ajouter un menu';
    deleteBtn.classList.add('d-none');
    saveBtn.textContent = 'Créer le menu';
  } else {
    menuEditId = menu.id;
    titleEl.textContent = 'Modifier le menu';
    deleteBtn.classList.remove('d-none');
    saveBtn.textContent = 'Enregistrer les changements';
  }

  document.getElementById('menus-list-view').classList.add('d-none');
  document.getElementById('menu-detail-view').classList.remove('d-none');
}

// Délégation d'événements : les cartes de #menus-list sont générées
// dynamiquement (fetch API), un binding statique .forEach ne les verrait pas.
document.getElementById('menus-list').addEventListener('click', function (e) {
  var btn = e.target.closest('.js-edit-menu');
  if (!btn) return;
  var id = parseInt(btn.dataset.id, 10);
  var menu = menusCache.find(function (m) { return m.id === id; });
  if (menu) openMenuDetail('edit', menu);
});

document.getElementById('btn-add-menu').addEventListener('click', function () {
  openMenuDetail('create', {});
});

document.getElementById('btn-back-to-menus').addEventListener('click', function () {
  document.getElementById('menu-detail-view').classList.add('d-none');
  document.getElementById('menus-list-view').classList.remove('d-none');
});


                          //========================//
                          // Photo du menu (aperçu + conversion en base64) //
                          //========================//

document.getElementById('detail-menu-photo-input').addEventListener('change', function (e) {
  var file = e.target.files[0];
  if (!file) return;

  var lecteur = new FileReader();
  lecteur.onload = function () {
    menuPhotoData = lecteur.result;
    document.getElementById('detail-menu-photo-preview').src = menuPhotoData;
  };
  lecteur.readAsDataURL(file);
});


                          //========================//
                          // Enregistrer / Supprimer un menu //
                          //========================//

async function creerOuModifierMenu() {
  var titre = document.getElementById('detail-menu-titre').value.trim();
  var min = document.getElementById('detail-menu-min').value;
  var prix = document.getElementById('detail-menu-prix').value;

  if (!titre) {
    menuAfficherErreur('Le titre du menu est obligatoire.');
    return;
  }
  if (!min || !prix) {
    menuAfficherErreur('Le nombre de personnes minimum et le prix par personne sont obligatoires.');
    return;
  }
  menuMasquerErreur();

  var description = document.getElementById('detail-menu-description').value.trim();
  var quantite = document.getElementById('detail-menu-quantite').value;
  var platsChoisis = Array.from(document.querySelectorAll('#menu-plats-list .form-check-input:checked'))
    .map(function (cb) { return parseInt(cb.value, 10); });
  // menuThemesCheckedIds (et non une lecture du DOM) : la liste des thèmes
  // peut être filtrée par la recherche, donc des cases cochées peuvent ne
  // plus être présentes dans le DOM au moment de l'enregistrement.
  var themesChoisis = menuThemesCheckedIds.slice();
  // Même remarque que pour les thèmes : on lit menuRegimesCheckedIds (pas le
  // DOM) car la liste affichée peut être filtrée par la recherche.
  var regimesChoisis = menuRegimesCheckedIds.slice();

  var payload = {
    titleMenu: titre,
    nombrePersonneMinimum: parseInt(min, 10),
    prixParPersonne: parseFloat(prix),
    description: description,
    conditions: document.getElementById('detail-menu-conditions').value.trim(),
    quantiteRestante: quantite ? parseInt(quantite, 10) : null,
    plats: platsChoisis,
    themes: themesChoisis,
    regimesAlimentaires: regimesChoisis
  };
  if (menuPhotoData) {
    payload.photoMenu = menuPhotoData;
  }

  var estEdition = menuEditId !== null;
  var url = API_URL + "/menu" + (estEdition ? "/" + menuEditId : "");
  var methode = estEdition ? "PUT" : "POST";

  try {
    const reponse = await fetch(url, {
      method: methode,
      headers: {
        "Content-Type": "application/json",
        "X-AUTH-TOKEN": localStorage.getItem('token')
      },
      body: JSON.stringify(payload)
    });

    if (!reponse.ok) {
      menuAfficherErreur("Une erreur est survenue lors de l'enregistrement du menu.");
      return;
    }

    document.getElementById('menu-detail-view').classList.add('d-none');
    document.getElementById('menus-list-view').classList.remove('d-none');
    chargerMenus();
  } catch (error) {
    console.error(error);
    menuAfficherErreur("Une erreur est survenue lors de l'enregistrement du menu.");
  }
}

document.getElementById('btn-save-menu').addEventListener('click', creerOuModifierMenu);

document.getElementById('btn-delete-menu').addEventListener('click', async function () {
  if (menuEditId === null) return;

  try {
    const reponse = await fetch(API_URL + "/menu/" + menuEditId, {
      method: "DELETE",
      headers: { "X-AUTH-TOKEN": localStorage.getItem('token') }
    });

    if (!reponse.ok) {
      const data = await reponse.json().catch(() => null);
      menuAfficherErreur(messageErreurApi(data, 'Une erreur est survenue lors de la suppression du menu.'));
      return;
    }

    document.getElementById('menu-detail-view').classList.add('d-none');
    document.getElementById('menus-list-view').classList.remove('d-none');
    chargerMenus();
  } catch (error) {
    console.error(error);
    menuAfficherErreur('Une erreur est survenue lors de la suppression du menu.');
  }
});

                          //========================//
                          // Commandes //
                          //========================//

let commandesCache = [];          // commandes affichées (selon les filtres)
let commandeDetailCourante = null;

function enTetesApi(avecJson) {
  var h = { "X-AUTH-TOKEN": localStorage.getItem('token') };
  if (avecJson) h["Content-Type"] = "application/json";
  return h;
}

function eurosCommande(montant) {
  return Number(montant || 0).toFixed(2).replace('.', ',') + ' €';
}

function formaterDateCommande(commande) {
  var date = formaterDateFr(commande.datePrestation);
  return commande.heureLivraison ? date + ' · ' + commande.heureLivraison : date;
}

function creerLigneCommande(commande) {
  var tr = document.createElement('tr');
  tr.dataset.id = commande.id;
  var badgeClass = CLASSES_STATUT_COMMANDE[commande.statut] || 'profil-statut-attente';
  tr.innerHTML =
    '<td>' + commande.id + '</td>' +
    '<td>' + echapperHtml(commande.menu ? commande.menu.titleMenu : '—') + '</td>' +
    '<td>' + echapperHtml(commande.user ? (commande.user.firstName + ' ' + commande.user.lastName) : '—') + '</td>' +
    '<td>' + echapperHtml(formaterDateCommande(commande)) + '</td>' +
    '<td>' + (commande.nombrePersonne != null ? commande.nombrePersonne : '—') + '</td>' +
    '<td>' + eurosCommande(commande.montantTotal) + '</td>' +
    '<td><span class="profil-statut ' + badgeClass + '">' + echapperHtml(commande.statut) + '</span></td>' +
    '<td><button type="button" class="btn btn-sm dash-table-btn js-edit-commande" data-id="' + commande.id + '">Gérer</button></td>';
  return tr;
}

function creerCarteApercuCommande(commande) {
  var badgeClass = CLASSES_STATUT_COMMANDE[commande.statut] || 'profil-statut-attente';
  var div = document.createElement('div');
  div.className = 'commande-card p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2';
  div.innerHTML =
    '<div>' +
      '<p class="commande-menu mb-1">' + echapperHtml(commande.menu ? commande.menu.titleMenu : 'Menu') + '</p>' +
      '<p class="commande-details mb-0">Prestation le ' + echapperHtml(formaterDateCommande(commande)) + ' · ' + (commande.nombrePersonne != null ? commande.nombrePersonne : '—') + ' personnes</p>' +
    '</div>' +
    '<div class="text-end">' +
      '<span class="profil-statut ' + badgeClass + '">' + echapperHtml(commande.statut) + '</span>' +
      '<p class="commande-prix mb-0 mt-1">' + eurosCommande(commande.montantTotal) + '</p>' +
    '</div>';
  return div;
}

// "CA du mois" (Vue d'ensemble) : commandes "Terminée" dont la prestation tombe dans le mois en cours.
function calculerCaDuMois(commandes) {
  var maintenant = new Date();
  return commandes
    .filter(function (c) {
      if (c.statut !== 'Terminée' || !c.datePrestation) return false;
      var d = new Date(c.datePrestation);
      return d.getMonth() === maintenant.getMonth() && d.getFullYear() === maintenant.getFullYear();
    })
    .reduce(function (somme, c) { return somme + (c.montantTotal || 0); }, 0);
}

// Vue d'ensemble : toutes les commandes, sans filtre.
async function chargerApercuCommandes() {
  var apercu = document.getElementById('dernieres-commandes-liste');
  try {
    const reponse = await fetch(API_URL + "/commande", { headers: enTetesApi() });
    if (!reponse.ok) {
      if (apercu) apercu.innerHTML = '<p class="text-center profil-empty-hint mb-0">Impossible de charger les commandes.</p>';
      return;
    }
    const toutes = await reponse.json();

    var statAttente = document.getElementById('stat-commandes-attente');
    if (statAttente) statAttente.textContent = toutes.filter(function (c) { return c.statut === 'En attente'; }).length;
    var statCa = document.getElementById('stat-ca-mois');
    if (statCa) statCa.textContent = eurosCommande(calculerCaDuMois(toutes));

    if (apercu) {
      apercu.innerHTML = '';
      var dernieres = toutes.slice(0, 3);
      if (!dernieres.length) {
        apercu.innerHTML = '<p class="text-center profil-empty-hint mb-0">Aucune commande pour le moment.</p>';
      } else {
        dernieres.forEach(function (commande) { apercu.appendChild(creerCarteApercuCommande(commande)); });
      }
    }
  } catch (error) {
    console.error(error);
    if (apercu) apercu.innerHTML = '<p class="text-center profil-empty-hint mb-0">Impossible de contacter le serveur.</p>';
  }
}

// Appel API : GET /api/commande?statut=...&client=... (réservé admin/employé côté back)
async function chargerCommandes() {
  var tbody = document.getElementById('commandes-tbody');
  var params = new URLSearchParams();
  var statut = document.getElementById('filtre-commande-statut').value;
  var client = document.getElementById('filtre-commande-client').value.trim();
  if (statut) params.set('statut', statut);
  if (client) params.set('client', client);

  try {
    const reponse = await fetch(API_URL + "/commande" + (params.toString() ? '?' + params.toString() : ''), { headers: enTetesApi() });

    if (!reponse.ok) {
      tbody.innerHTML = '<tr><td colspan="8" class="profil-empty-hint">Impossible de charger les commandes.</td></tr>';
      return;
    }

    commandesCache = await reponse.json();
    tbody.innerHTML = '';
    if (!commandesCache.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="profil-empty-hint">Aucune commande ne correspond à ces critères.</td></tr>';
    } else {
      commandesCache.forEach(function (commande) { tbody.appendChild(creerLigneCommande(commande)); });
    }
  } catch (error) {
    console.error(error);
    tbody.innerHTML = '<tr><td colspan="8" class="profil-empty-hint">Impossible de contacter le serveur.</td></tr>';
  }
}

// Liste des statuts (filtre + sélecteur) fournie par l'API : une seule source de vérité.
let statutsCommande = [];
async function chargerStatutsCommande() {
  try {
    const reponse = await fetch(API_URL + "/commande/statuts", { headers: enTetesApi() });
    if (!reponse.ok) return;
    const data = await reponse.json();
    statutsCommande = data.statuts;
    document.getElementById('filtre-commande-statut').innerHTML = '<option value="">Tous les statuts</option>' +
      statutsCommande.map(function (s) { return '<option value="' + echapperHtml(s) + '">' + echapperHtml(s) + '</option>'; }).join('');
    // "Annulée" passe par le bouton dédié (motif + mode de contact obligatoires).
    document.getElementById('detail-commande-statut').innerHTML =
      statutsCommande.filter(function (s) { return s !== 'Annulée'; })
        .map(function (s) { return '<option value="' + echapperHtml(s) + '">' + echapperHtml(s) + '</option>'; }).join('');
  } catch (error) {
    console.error(error);
  }
}

chargerStatutsCommande();
chargerApercuCommandes();
chargerCommandes();

// Filtres : mise à jour sans rechargement de la page.
let minuteurFiltreCommandes = null;
document.getElementById('filtre-commande-statut').addEventListener('change', chargerCommandes);
document.getElementById('filtre-commande-client').addEventListener('input', function () {
  clearTimeout(minuteurFiltreCommandes);
  minuteurFiltreCommandes = setTimeout(chargerCommandes, 300);
});
document.getElementById('btn-filtres-commandes-reset').addEventListener('click', function () {
  document.getElementById('filtre-commande-statut').value = '';
  document.getElementById('filtre-commande-client').value = '';
  chargerCommandes();
});

function afficherDetailCommande(commande) {
  commandeDetailCourante = commande;
  var badge = document.getElementById('detail-commande-statut-badge');
  badge.className = 'profil-statut ' + (CLASSES_STATUT_COMMANDE[commande.statut] || 'profil-statut-attente');
  badge.textContent = commande.statut;

  document.getElementById('detail-commande-titre').textContent = 'Commande n°' + commande.id + ' du ' + formaterDateFr(commande.dateCommande);
  document.getElementById('detail-commande-menu').textContent = commande.menu ? commande.menu.titleMenu : '—';
  document.getElementById('detail-commande-client').textContent = commande.user ? (commande.user.firstName + ' ' + commande.user.lastName) : '—';
  document.getElementById('detail-commande-contact').textContent = commande.user
    ? 'GSM : ' + (commande.user.phoneNumber || 'non renseigné') + ' · Email : ' + commande.user.email
    : '';
  document.getElementById('detail-commande-adresse').textContent = commande.adresse || '—';
  document.getElementById('detail-commande-date').textContent = formaterDateCommande(commande);
  document.getElementById('detail-commande-personnes').textContent = commande.nombrePersonne != null ? commande.nombrePersonne : '—';
  var livraison = parseFloat(commande.prixLivraison) || 0;
  document.getElementById('detail-commande-prix').textContent = eurosCommande(commande.montantTotal) +
    ' (menu ' + eurosCommande(parseFloat(commande.prixMenu) * commande.nombrePersonne) + ' + livraison ' + (livraison > 0 ? eurosCommande(livraison) : 'offerte') + ')';

  var annulationWrap = document.getElementById('detail-commande-annulation-wrap');
  annulationWrap.classList.toggle('d-none', !commande.motifAnnulation);
  document.getElementById('detail-commande-annulation').textContent = commande.motifAnnulation
    ? 'Motif d\'annulation : ' + commande.motifAnnulation + (commande.modeContact ? ' (client contacté par ' + commande.modeContact + ')' : '')
    : '';

  // Suivi (historique des statuts)
  var suivi = document.getElementById('detail-commande-suivi');
  suivi.innerHTML = (commande.historique && commande.historique.length)
    ? '<ul class="suivi-commande">' + commande.historique.map(function (h) {
        return '<li><strong>' + echapperHtml(h.statut) + '</strong> <span class="suivi-date">le ' + echapperHtml(formaterDateFr(h.date)) + '</span>' +
          (h.commentaire ? '<br><span class="text-muted">' + echapperHtml(h.commentaire) + '</span>' : '') + '</li>';
      }).join('') + '</ul>'
    : '<p class="profil-empty-hint mb-0">Aucun historique.</p>';

  // Une commande terminée ou annulée ne bouge plus.
  var finale = commande.statut === 'Terminée' || commande.statut === 'Annulée';
  document.getElementById('detail-commande-actions').classList.toggle('d-none', finale);

  document.getElementById('detail-commande-statut').value = commande.statut;
  document.getElementById('detail-commande-pret').checked = !!commande.pretMateriel;
  document.getElementById('detail-commande-mode-contact').value = '';
  document.getElementById('detail-commande-motif').value = '';
  document.getElementById('detail-commande-edit-date').value = commande.datePrestation || '';
  document.getElementById('detail-commande-edit-heure').value = commande.heureLivraison || '';
  document.getElementById('detail-commande-edit-adresse').value = commande.adresse || '';
  document.getElementById('detail-commande-edit-personnes').value = commande.nombrePersonne || '';
  document.getElementById('detail-commande-edit-personnes').min = commande.menu ? commande.menu.nombrePersonneMinimum : 1;
  ['detail-commande-statut-erreur', 'detail-commande-modif-erreur'].forEach(function (id) {
    document.getElementById(id).classList.add('d-none');
  });

  document.getElementById('commandes-list-view').classList.add('d-none');
  document.getElementById('commande-detail-view').classList.remove('d-none');
}

// Appel API : GET /api/commande/{id} (détail complet avec le suivi)
async function ouvrirDetailCommande(id) {
  try {
    const reponse = await fetch(API_URL + "/commande/" + id, { headers: enTetesApi() });
    if (!reponse.ok) {
      alert('Impossible de charger cette commande.');
      return;
    }
    afficherDetailCommande(await reponse.json());
  } catch (error) {
    console.error(error);
    alert('Impossible de contacter le serveur.');
  }
}

document.getElementById('commandes-tbody').addEventListener('click', function (event) {
  var btn = event.target.closest('.js-edit-commande');
  if (btn) ouvrirDetailCommande(parseInt(btn.dataset.id, 10));
});

document.getElementById('btn-back-to-commandes').addEventListener('click', function () {
  document.getElementById('commande-detail-view').classList.add('d-none');
  document.getElementById('commandes-list-view').classList.remove('d-none');
});

function afficherErreurCommande(idElement, message) {
  var el = document.getElementById(idElement);
  el.textContent = message;
  el.classList.remove('d-none');
}

// Envoie une action sur la commande courante puis rafraîchit le détail et la liste.
async function actionCommande(chemin, methode, corps, idErreur) {
  try {
    const reponse = await fetch(API_URL + "/commande/" + commandeDetailCourante.id + chemin, {
      method: methode,
      headers: enTetesApi(true),
      body: JSON.stringify(corps)
    });
    const data = await reponse.json().catch(() => null);

    if (!reponse.ok) {
      afficherErreurCommande(idErreur, messageErreurApi(data, "L'opération a échoué."));
      return;
    }

    if (data && data.mailEnvoye === false) {
      alert("Commande mise à jour, mais le mail au client n'a pas pu être envoyé.");
    }
    afficherDetailCommande(data);
    chargerCommandes();
    chargerApercuCommandes();
  } catch (error) {
    console.error(error);
    afficherErreurCommande(idErreur, 'Impossible de contacter le serveur.');
  }
}

// Appel API : PUT /api/commande/{id}/statut
document.getElementById('btn-save-commande').addEventListener('click', function () {
  if (!commandeDetailCourante) return;
  document.getElementById('detail-commande-statut-erreur').classList.add('d-none');
  actionCommande('/statut', 'PUT', {
    statut: document.getElementById('detail-commande-statut').value,
    pretMateriel: document.getElementById('detail-commande-pret').checked
  }, 'detail-commande-statut-erreur');
});

function contactClientRenseigne() {
  var mode = document.getElementById('detail-commande-mode-contact').value;
  var motif = document.getElementById('detail-commande-motif').value.trim();
  if (!mode || !motif) {
    afficherErreurCommande('detail-commande-modif-erreur', 'Contactez d\'abord le client puis indiquez le mode de contact (GSM ou Email) et le motif.');
    return null;
  }
  return { modeContact: mode, motif: motif };
}

// Appel API : PUT /api/commande/{id} (modification après contact du client)
document.getElementById('btn-modifier-commande').addEventListener('click', function () {
  if (!commandeDetailCourante) return;
  document.getElementById('detail-commande-modif-erreur').classList.add('d-none');
  var contact = contactClientRenseigne();
  if (!contact) return;

  actionCommande('', 'PUT', Object.assign(contact, {
    datePrestation: document.getElementById('detail-commande-edit-date').value,
    heureLivraison: document.getElementById('detail-commande-edit-heure').value,
    adresse: document.getElementById('detail-commande-edit-adresse').value.trim(),
    nombrePersonne: parseInt(document.getElementById('detail-commande-edit-personnes').value, 10)
  }), 'detail-commande-modif-erreur');
});

// Appel API : POST /api/commande/{id}/annuler (motif + mode de contact obligatoires)
document.getElementById('btn-annuler-commande').addEventListener('click', function () {
  if (!commandeDetailCourante) return;
  document.getElementById('detail-commande-modif-erreur').classList.add('d-none');
  var contact = contactClientRenseigne();
  if (!contact) return;
  if (!confirm('Annuler définitivement la commande n°' + commandeDetailCourante.id + ' ?')) return;

  actionCommande('/annuler', 'POST', contact, 'detail-commande-modif-erreur');
});


                          //========================//
                          // Statistiques (administrateur, données MongoDB) //
                          //========================//

let graphiqueCommandes = null;
let statistiquesInitialisees = false;

async function initialiserStatistiques() {
  if (statistiquesInitialisees) return;
  statistiquesInitialisees = true;

  // Liste des menus pour le filtre
  try {
    const reponse = await fetch(API_URL + "/menu");
    if (reponse.ok) {
      const menus = await reponse.json();
      document.getElementById('stats-menu').innerHTML = '<option value="">Tous les menus</option>' +
        menus.map(function (m) { return '<option value="' + m.id + '">' + echapperHtml(m.titleMenu) + '</option>'; }).join('');
    }
  } catch (error) {
    console.error(error);
  }

  chargerStatistiques();
}

function parametresStatistiques(avecMenu) {
  var params = new URLSearchParams();
  var debut = document.getElementById('stats-debut').value;
  var fin = document.getElementById('stats-fin').value;
  var menu = document.getElementById('stats-menu').value;
  if (debut) params.set('debut', debut);
  if (fin) params.set('fin', fin);
  if (avecMenu && menu) params.set('menuId', menu);
  return params.toString() ? '?' + params.toString() : '';
}

// Appels API : GET /api/stats/commandes-par-menu et /api/stats/chiffre-affaires
async function chargerStatistiques() {
  var erreur = document.getElementById('stats-erreur');
  erreur.classList.add('d-none');

  try {
    const [repCommandes, repCa] = await Promise.all([
      fetch(API_URL + "/stats/commandes-par-menu" + parametresStatistiques(false), { headers: enTetesApi() }),
      fetch(API_URL + "/stats/chiffre-affaires" + parametresStatistiques(true), { headers: enTetesApi() })
    ]);
    const commandesParMenu = await repCommandes.json().catch(() => null);
    const ca = await repCa.json().catch(() => null);

    if (!repCommandes.ok || !repCa.ok) {
      erreur.textContent = messageErreurApi(!repCommandes.ok ? commandesParMenu : ca, 'Statistiques indisponibles.');
      erreur.classList.remove('d-none');
      return;
    }

    dessinerGraphiqueCommandes(commandesParMenu);

    document.getElementById('stats-ca-total').textContent = eurosCommande(ca.total);
    document.getElementById('stats-nb-commandes').textContent = ca.nombreCommandes;
    var tbody = document.getElementById('stats-ca-tbody');
    tbody.innerHTML = ca.parMenu.length
      ? ca.parMenu.map(function (ligne) {
          return '<tr><td>' + echapperHtml(ligne.menu) + '</td><td>' + ligne.nombreCommandes + '</td><td>' + eurosCommande(ligne.chiffreAffaires) + '</td></tr>';
        }).join('')
      : '<tr><td colspan="3" class="profil-empty-hint">Aucune commande sur cette période.</td></tr>';
  } catch (error) {
    console.error(error);
    erreur.textContent = 'Impossible de contacter le serveur.';
    erreur.classList.remove('d-none');
  }
}

// Graphique en barres (Chart.js) : comparaison du nombre de commandes entre les menus.
function dessinerGraphiqueCommandes(donnees) {
  var canvas = document.getElementById('stats-chart');
  if (typeof Chart === 'undefined' || !canvas) return;

  if (graphiqueCommandes) graphiqueCommandes.destroy();
  graphiqueCommandes = new Chart(canvas, {
    type: 'bar',
    data: {
      labels: donnees.map(function (d) { return d.menu; }),
      datasets: [{
        label: 'Nombre de commandes',
        data: donnees.map(function (d) { return d.nombreCommandes; }),
        backgroundColor: '#3d4a3a',
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
  });
}

document.getElementById('btn-stats-appliquer').addEventListener('click', chargerStatistiques);


                          //========================//
                          // Horraire //
                          //========================//

// Le déroulé/repli du formulaire est géré par Bootstrap (data-bs-toggle="collapse"
// sur le bouton "Ajouter"). horaireEditRow retient la ligne <tr> en cours de
// modification : null = mode création, sinon mode édition.
const formAjoutHoraire = document.getElementById('form-ajout-horaire');
const horaireAddForm = document.getElementById('horaireAddForm');
const horaireAddError = document.getElementById('horaire-add-error');
const horaireFormTitle = document.getElementById('horaire-form-title');
const btnSaveHoraire = document.getElementById('btn-save-horaire');
const btnAjouterHoraire = document.getElementById('ajouter-horaire');
let horaireEditRow = null;

// Clic sur le bouton "Ajouter" de l'entête : on repart toujours en mode création
btnAjouterHoraire.addEventListener('click', function () {
  horaireEditRow = null;
});

formAjoutHoraire.addEventListener('show.bs.collapse', function () {
  horaireAddError.classList.add('d-none');

  if (horaireEditRow) {
    // mode édition : on pré-remplit avec les valeurs de la ligne cliquée
    horaireFormTitle.textContent = "Modifier l'horaire";
    btnSaveHoraire.textContent = 'Enregistrer les modifications';
    document.getElementById('horaire-jour').value = horaireEditRow.dataset.jour || 'Lundi';
    const parts = (horaireEditRow.dataset.heure || '').split(' - ');
    document.getElementById('horaire-ouverture').value = (parts[0] || '').trim();
    document.getElementById('horaire-fermeture').value = (parts[1] || '').trim();
  } else {
    // mode création : formulaire vierge
    horaireFormTitle.textContent = 'Ajouter un horaire';
    btnSaveHoraire.textContent = 'Enregistrer';
    horaireAddForm.reset();
  }
});

formAjoutHoraire.addEventListener('shown.bs.collapse', function () {
  document.getElementById('ajouter-horaire-icon').className = 'bi bi-dash-lg';
  document.getElementById('ajouter-horaire-label').textContent = 'Fermer';
});

formAjoutHoraire.addEventListener('hidden.bs.collapse', function () {
  document.getElementById('ajouter-horaire-icon').className = 'bi bi-plus-lg';
  document.getElementById('ajouter-horaire-label').textContent = 'Ajouter';
  horaireEditRow = null;
});

// Appel API : GET /api/horraires — charge la vraie liste depuis la base
async function chargerHoraires() {
  const tbody = document.getElementById('horaires-tbody');

  try {
    const reponse = await fetch(API_URL + "/horraires", {
      method: "GET",
      headers: {
        "X-AUTH-TOKEN": localStorage.getItem('token')
      }
    });

    if (!reponse.ok) {
      console.error("Impossible de récupérer les horaires.");
      tbody.innerHTML = '<tr><td colspan="3" class="profil-empty-hint">Impossible de charger les horaires.</td></tr>';
      return;
    }

    const horaires = await reponse.json();
    tbody.innerHTML = '';

    if (!horaires.length) {
      tbody.innerHTML = '<tr><td colspan="3" class="profil-empty-hint">Aucun horaire renseigné.</td></tr>';
      return;
    }

    horaires.forEach(function (horaire) {
      creerLigneHoraire(horaire);
    });
  } catch (error) {
    console.error(error);
    tbody.innerHTML = '<tr><td colspan="3" class="profil-empty-hint">Impossible de contacter le serveur.</td></tr>';
  }
}

chargerHoraires();

// Ajoute une ligne dans le tableau des horaires sans recharger la page
function creerLigneHoraire(horaire) {
  const tbody = document.getElementById('horaires-tbody');
  // On enlève la ligne "Aucun horaire renseigné." si c'est le premier horaire ajouté
  const ligneVide = tbody.querySelector('.profil-empty-hint');
  if (ligneVide) ligneVide.closest('tr').remove();
  const tr = document.createElement('tr');
  remplirLigneHoraire(tr, horaire);
  tbody.appendChild(tr);
}

// Met à jour le contenu (cellules + dataset) d'une ligne existante
function remplirLigneHoraire(tr, horaire) {
  tr.dataset.id = horaire.id;
  tr.dataset.jour = horaire.jour;
  tr.dataset.heure = horaire.heure;
  tr.innerHTML =
    '<td>' + horaire.jour + '</td>' +
    '<td>' + horaire.heure + '</td>' +
    '<td>' +
      '<button type="button" class="dash-icon-btn" title="Modifier"><i class="bi bi-pencil"></i></button>' +
      '<button type="button" class="dash-icon-btn danger" title="Supprimer"><i class="bi bi-trash"></i></button>' +
    '</td>';
}

// Appel API : POST /api/horraires
async function add_horaire(jour, heure) {
  const reponse = await fetch(API_URL + "/horraires", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-AUTH-TOKEN": localStorage.getItem('token')
    },
    body: JSON.stringify({ jour: jour, heure: heure })
  });

  if (!reponse.ok) {
    throw new Error("Impossible d'ajouter l'horaire.");
  }

  return reponse.json();
}

// Appel API : PUT /api/horraires/{id}
async function put_horaire(id, jour, heure) {
  const reponse = await fetch(API_URL + "/horraires/" + id, {
    method: "PUT",
    headers: {
      "Content-Type": "application/json",
      "X-AUTH-TOKEN": localStorage.getItem('token')
    },
    body: JSON.stringify({ jour: jour, heure: heure })
  });

  if (!reponse.ok) {
    throw new Error("Impossible de modifier l'horaire.");
  }
}

// Appel API : DELETE /api/horraires/{id}
async function del_horaire(id) {
  const reponse = await fetch(API_URL + "/horraires/" + id, {
    method: "DELETE",
    headers: {
      "X-AUTH-TOKEN": localStorage.getItem('token')
    }
  });

  if (!reponse.ok) {
    throw new Error("Impossible de supprimer l'horaire.");
  }
}

btnSaveHoraire.addEventListener('click', async function () {
  const jour = document.getElementById('horaire-jour').value;
  const ouverture = document.getElementById('horaire-ouverture').value;
  const fermeture = document.getElementById('horaire-fermeture').value;

  horaireAddError.classList.add('d-none');

  if (!ouverture || !fermeture) {
    horaireAddError.textContent = "Merci de renseigner une heure d'ouverture et de fermeture.";
    horaireAddError.classList.remove('d-none');
    return;
  }

  const heure = ouverture + ' - ' + fermeture;

  try {
    if (horaireEditRow) {
      await put_horaire(horaireEditRow.dataset.id, jour, heure);
      remplirLigneHoraire(horaireEditRow, { id: horaireEditRow.dataset.id, jour: jour, heure: heure });
    } else {
      const horaireCree = await add_horaire(jour, heure);
      creerLigneHoraire(horaireCree);
    }
    horaireEditRow = null;
    bootstrap.Collapse.getOrCreateInstance(formAjoutHoraire).hide();
  } catch (error) {
    console.error(error);
    horaireAddError.textContent = horaireEditRow
      ? "Impossible de modifier l'horaire. Vérifie ta connexion ou tes droits."
      : "Impossible d'ajouter l'horaire. Vérifie ta connexion ou tes droits.";
    horaireAddError.classList.remove('d-none');
  }
});

// Délégation d'évènements sur le tableau : gère les clics "Modifier"/"Supprimer"
// aussi bien sur les lignes déjà présentes que sur celles ajoutées dynamiquement.
document.getElementById('horaires-tbody').addEventListener('click', function (event) {
  const editBtn = event.target.closest('.dash-icon-btn:not(.danger)');
  const deleteBtn = event.target.closest('.dash-icon-btn.danger');

  if (editBtn) {
    const tr = editBtn.closest('tr');
    if (!tr.dataset.id) return;
    horaireEditRow = tr;
    bootstrap.Collapse.getOrCreateInstance(formAjoutHoraire).show();
    return;
  }

  if (deleteBtn) {
    const tr = deleteBtn.closest('tr');
    if (!tr.dataset.id) return;
    if (!confirm('Supprimer cet horaire ?')) return;

    del_horaire(tr.dataset.id)
      .then(function () { tr.remove(); })
      .catch(function (error) {
        console.error(error);
        alert("Impossible de supprimer cet horaire.");
      });
  }
});
})();