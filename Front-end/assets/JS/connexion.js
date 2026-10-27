// Enveloppé dans une IIFE : ce script est réinjecté (nouvelle balise <script>) à chaque
// navigation SPA vers cette page. Sans ce wrapper, les "const"/"let" de premier niveau
// entrent en collision au 2e chargement -> "Identifier ... has already been declared".
(function () {
function togglebtn (input) {
    input.type = input.type === 'password' ? 'text' : 'password';
}

const $ = id => document.getElementById(id);
$('btn-hide-Password').addEventListener("click",()=> togglebtn($('ins-Password')));

const Infos_CX = {
    Username : $('ins-Email'),
    Password : $('ins-Password')
};

function afficherErreur(message) {
    $('cx-succes').classList.add('d-none');
    $('cx-erreur').textContent = message;
    $('cx-erreur').classList.remove('d-none');
}

function afficherSucces(message) {
    $('cx-erreur').classList.add('d-none');
    $('cx-succes').textContent = message;
    $('cx-succes').classList.remove('d-none');
}

$('btn-cx-form').addEventListener('click', () => connexion());
// Validation avec la touche Entrée
[Infos_CX.Username, Infos_CX.Password].forEach(function (input) {
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') connexion(); });
});

async function connexion(){
    $('cx-renvoyer-wrap').classList.add('d-none');

    if (!Infos_CX.Username.value.trim() || !Infos_CX.Password.value) {
        afficherErreur("Merci de saisir votre email et votre mot de passe.");
        return;
    }

    try {
        const reponse = await fetch(API_URL + "/login", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                "username": Infos_CX.Username.value.trim(),
                "password": Infos_CX.Password.value,
            })
        });
        const data = await reponse.json().catch(() => null);

        if (!reponse.ok) {
            // L'API précise si l'email n'est pas encore confirmé ou si le compte est désactivé.
            const message = (data && data.error) || '';
            if (message.indexOf('confirmer') !== -1) {
                afficherErreur(message);
                $('cx-renvoyer-wrap').classList.remove('d-none');
            } else if (message.indexOf('désactivé') !== -1) {
                afficherErreur(message);
            } else {
                afficherErreur("Email ou mot de passe incorrect.");
            }
            return;
        }

        localStorage.setItem('token', data.apiToken);
        localStorage.setItem('roles', JSON.stringify(data.roles));
        window.location.href = "/";
    } catch (error) {
        console.error(error);
        afficherErreur("Impossible de contacter le serveur.");
    }
}

// Email non confirmé : on peut redemander un lien.
$('btn-renvoyer-lien').addEventListener('click', async function () {
    try {
        const reponse = await fetch(API_URL + "/resend-verification", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email: Infos_CX.Username.value.trim() })
        });
        const data = await reponse.json().catch(() => null);
        afficherSucces(messageErreurApi(data, "Un nouveau lien vous a été envoyé."));
        $('cx-renvoyer-wrap').classList.add('d-none');
    } catch (error) {
        console.error(error);
        afficherErreur("Impossible de contacter le serveur.");
    }
});

})();
