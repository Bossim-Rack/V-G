(function () {
const $ = id => document.getElementById(id);
// Jeton reçu dans le lien du mail de bienvenue : /verifier-email?token=...
const token = new URLSearchParams(window.location.search).get('token');

function terminer(succes, message) {
    $('verif-chargement').classList.add('d-none');
    const bloc = $(succes ? 'verif-succes' : 'verif-erreur');
    bloc.textContent = message;
    bloc.classList.remove('d-none');
    $('verif-btn-connexion').classList.remove('d-none');
}

async function verifier() {
    if (!token) {
        terminer(false, "Lien incomplet : utilisez le lien reçu par mail.");
        return;
    }

    try {
        const reponse = await fetch(API_URL + "/verify-email", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ token: token })
        });
        const data = await reponse.json().catch(() => null);
        terminer(reponse.ok, messageErreurApi(data, reponse.ok ? "Adresse confirmée." : "Lien invalide."));
    } catch (error) {
        console.error(error);
        terminer(false, "Impossible de contacter le serveur.");
    }
}

verifier();
})();
