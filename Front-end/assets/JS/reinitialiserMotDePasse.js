(function () {
const $ = id => document.getElementById(id);
// Jeton reçu dans le lien du mail : /reinitialiser-mot-de-passe?token=...
const token = new URLSearchParams(window.location.search).get('token');

function naviguerSPA(chemin) {
    if (typeof window.route === 'function') {
        window.route({ preventDefault: function () {}, target: { href: chemin } });
    } else {
        window.location.href = chemin;
    }
}

function togglebtn(input) {
    input.type = input.type === 'password' ? 'text' : 'password';
}
$('btn-hide-Password').addEventListener("click", () => togglebtn($('reset-Password')));
$('btn-hide-ConfirmPassword').addEventListener("click", () => togglebtn($('reset-ConfirmPassword')));

function afficherErreur(message) {
    $('reset-succes').classList.add('d-none');
    $('reset-error').textContent = message;
    $('reset-error').classList.remove('d-none');
}

function afficherSucces(message) {
    $('reset-error').classList.add('d-none');
    $('reset-succes').textContent = message;
    $('reset-succes').classList.remove('d-none');
}

if (!token) {
    afficherErreur("Lien incomplet : utilisez le lien reçu par mail, ou faites une nouvelle demande.");
    $('btn-reset-envoie').disabled = true;
}

$('reset-Password').addEventListener('input', function () {
    const resultat = verifierMotDePasse($('reset-Password').value);
    const bar = $('strength-bar');
    bar.className = 'ins-strength';
    if (resultat.niveau > 0) bar.classList.add('s' + resultat.niveau);
    document.querySelectorAll('#reset-password-regles li').forEach(function (li) {
        const ok = resultat.criteres[li.dataset.critere];
        li.classList.toggle('text-success', ok);
        li.classList.toggle('text-muted', !ok);
    });
});

$('btn-reset-envoie').addEventListener('click', async function () {
    const password = $('reset-Password').value;

    if (!verifierMotDePasse(password).valide) {
        afficherErreur("Le mot de passe doit respecter toutes les règles indiquées.");
        return;
    }
    if (password !== $('reset-ConfirmPassword').value) {
        afficherErreur("Les deux mots de passe ne correspondent pas.");
        return;
    }

    const bouton = $('btn-reset-envoie');
    bouton.disabled = true;

    try {
        const reponse = await fetch(API_URL + "/reset-password", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ token: token, password: password })
        });
        const data = await reponse.json().catch(() => null);

        if (!reponse.ok) {
            afficherErreur(messageErreurApi(data, "La réinitialisation a échoué."));
            bouton.disabled = false;
            return;
        }

        // Toutes les sessions ont été fermées côté serveur : on repart propre.
        localStorage.removeItem('token');
        localStorage.removeItem('roles');
        afficherSucces(data.message);
        setTimeout(function () { naviguerSPA('/connexion'); }, 2000);
    } catch (error) {
        console.error(error);
        afficherErreur("Impossible de contacter le serveur.");
        bouton.disabled = false;
    }
});

})();
