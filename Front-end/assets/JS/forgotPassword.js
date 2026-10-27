(function () {
const $ = id => document.getElementById(id);

function afficherErreur(message) {
    $('mdp-oublie-succes').classList.add('d-none');
    $('mdp-oublie-error').textContent = message;
    $('mdp-oublie-error').classList.remove('d-none');
}

function afficherSucces(message) {
    $('mdp-oublie-error').classList.add('d-none');
    $('mdp-oublie-succes').textContent = message;
    $('mdp-oublie-succes').classList.remove('d-none');
}

//  ================================= //
//                API                //
//  =================================//
// Appel API : POST /api/forgot-password -> un lien valable 1 heure est envoyé par mail.
// La réponse est identique que le compte existe ou non (on ne révèle pas qui est inscrit).
$('btn-mdp-oublie-envoie').addEventListener('click', () => demanderLien());
$('mdp-Email').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); demanderLien(); }
});

async function demanderLien() {
    const email = $('mdp-Email').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        afficherErreur("Merci de saisir une adresse email valide.");
        return;
    }

    const bouton = $('btn-mdp-oublie-envoie');
    bouton.disabled = true;

    try {
        const reponse = await fetch(API_URL + "/forgot-password", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email: email })
        });
        const data = await reponse.json().catch(() => null);

        if (!reponse.ok) {
            afficherErreur(messageErreurApi(data, "Une erreur est survenue."));
            bouton.disabled = false;
            return;
        }

        afficherSucces(data.message);
    } catch (error) {
        console.error(error);
        afficherErreur("Impossible de contacter le serveur.");
        bouton.disabled = false;
    }
}

})();
