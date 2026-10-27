(function () {
const $ = id => document.getElementById(id);

function afficherErreur(message) {
    $('contact-succes').classList.add('d-none');
    $('contact-erreur').textContent = message;
    $('contact-erreur').classList.remove('d-none');
}

function afficherSucces(message) {
    $('contact-erreur').classList.add('d-none');
    $('contact-succes').textContent = message;
    $('contact-succes').classList.remove('d-none');
}

// Pré-remplit l'email si l'utilisateur est connecté.
(async function preRemplirEmail() {
    const token = localStorage.getItem('token');
    if (!token) return;
    try {
        const reponse = await fetch(API_URL + "/account/me", { headers: { "X-AUTH-TOKEN": token } });
        if (reponse.ok) {
            const user = await reponse.json();
            if (!$('contact-email').value) $('contact-email').value = user.email || '';
        }
    } catch (error) {
        console.error(error);
    }
})();

// Appel API : POST /api/contact (public) -> la demande part par mail à l'entreprise.
$('contact-form').addEventListener('submit', async function (event) {
    event.preventDefault();

    const email = $('contact-email').value.trim();
    const titre = $('contact-titre').value.trim();
    const description = $('contact-description').value.trim();

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        afficherErreur("Merci de saisir une adresse email valide pour que nous puissions vous répondre.");
        return;
    }
    if (!titre || description.length < 10) {
        afficherErreur("Merci d'indiquer un titre et un message (10 caractères minimum).");
        return;
    }

    const bouton = $('btn-contact-envoie');
    bouton.disabled = true;

    try {
        const reponse = await fetch(API_URL + "/contact", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email: email, titre: titre, description: description, site: $('contact-site').value })
        });
        const data = await reponse.json().catch(() => null);

        if (!reponse.ok) {
            afficherErreur(messageErreurApi(data, "Votre message n'a pas pu être envoyé."));
            return;
        }

        afficherSucces(data.message);
        $('contact-titre').value = '';
        $('contact-description').value = '';
    } catch (error) {
        console.error(error);
        afficherErreur("Impossible de contacter le serveur.");
    } finally {
        bouton.disabled = false;
    }
});

})();
