
(function () {
const $ = id => document.getElementById(id);
function naviguerSPA(chemin) {
  if (typeof window.route === 'function') {
    window.route({ preventDefault: function () {}, target: { href: chemin } });
  } else {
    window.location.href = chemin;
  }
}

//  ================================= //
//         Btn Hide Password         //
//  =================================//
function togglebtn(input) {
    if (input.type == 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

$('btn-hide-Password').addEventListener("click", () => togglebtn($('mdp-Password')));
$('btn-hide-ConfirmPassword').addEventListener("click", () => togglebtn($('mdp-ConfirmPassword')));

//  ================================= //
//    Barre de robustesse du mdp     //
//  =================================//
// Même logique que inscription.js / User::validatePasswordStrength côté back.
function LevelClassBar(input) {
    let level = 0;
    const regexMin = /[a-z]/;
    const regexMaj = /[A-Z]/;
    const regexChiffre = /\d/;
    const regexSpec = /[^a-zA-Z0-9]/;
    if (input.length >= 8) { level++; }
    if (regexMin.test(input) && regexMaj.test(input)) { level++; }
    if (regexChiffre.test(input)) { level++; }
    if (regexSpec.test(input)) { level++; }
    return level;
}

function updatestrengthBar(level) {
    const bar = $('strength-bar');
    bar.className = 'ins-strength';
    if (level > 0) bar.classList.add('s' + level);
}

$('mdp-Password').addEventListener('keyup', () => {
    updatestrengthBar(LevelClassBar($('mdp-Password').value));
});

//  ================================= //
//              Messages              //
//  =================================//
function afficherErreur(message) {
    $('mdp-oublie-succes').classList.add('d-none');
    const erreur = $('mdp-oublie-error');
    erreur.textContent = message;
    erreur.classList.remove('d-none');
}

function afficherSucces(message) {
    $('mdp-oublie-error').classList.add('d-none');
    const succes = $('mdp-oublie-succes');
    succes.textContent = message;
    succes.classList.remove('d-none');
}

function masquerMessages() {
    $('mdp-oublie-error').classList.add('d-none');
    $('mdp-oublie-succes').classList.add('d-none');
}

//  ================================= //
//                API                //
//  =================================//
// Appel API : POST /api/forgot-password
// Pas d'envoi d'email dans ce projet : la réinitialisation se fait directement via email + nouveau
// mot de passe, sans étape de vérification intermédiaire.
$('btn-mdp-oublie-envoie').addEventListener('click', () => reinitialiserMotDePasse());

function reinitialiserMotDePasse() {
    masquerMessages();

    const email = $('mdp-Email').value.trim();
    const password = $('mdp-Password').value;
    const confirmPassword = $('mdp-ConfirmPassword').value;

    if (!email) {
        afficherErreur("L'email est obligatoire.");
        return;
    }
    if (!password) {
        afficherErreur('Le nouveau mot de passe est obligatoire.');
        return;
    }
    if (password !== confirmPassword) {
        afficherErreur('Les deux mots de passe ne correspondent pas.');
        return;
    }

    const myHeaders = new Headers();
    myHeaders.append("Content-Type", "application/json");

    const raw = JSON.stringify({
        "email": email,
        "password": password
    });

    const requestOptions = {
        method: "POST",
        headers: myHeaders,
        body: raw,
        redirect: "follow"
    };

    fetch("http://127.0.0.1:8000/api/forgot-password", requestOptions)
        .then(async (response) => {
            if (response.ok) {
                afficherSucces('Mot de passe réinitialisé. Vous pouvez vous connecter.');
                $('mdp-oublie-form').reset();
                updatestrengthBar(0);
                setTimeout(() => { naviguerSPA("/connexion"); }, 1500);
            } else {
                const data = await response.json().catch(() => null);
                if (data && data.errors) {
                    afficherErreur(Object.values(data.errors).join(' '));
                } else if (data && data.message) {
                    afficherErreur(data.message);
                } else {
                    afficherErreur("Une erreur est survenue lors de la réinitialisation.");
                }
            }
        })
        .catch((error) => {
            console.error(error);
            afficherErreur("Une erreur est survenue lors de la réinitialisation.");
        });
}

})();
