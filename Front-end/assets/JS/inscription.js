(function () {
const $ = id => document.getElementById(id);

//  ================================= //
//         Btn Hide Password         //
//  =================================//
function togglebtn (input) {
    input.type = input.type === 'password' ? 'text' : 'password';
}
$('btn-hide-Password').addEventListener("click",() => togglebtn($('ins-Password')));
$('btn-hide-ConfirmPassword').addEventListener("click", () => togglebtn($('ins-ConfirmPassword')));

//  ================================= //
//    Barre de robustesse + règles    //
//  =================================//
function updatestrengthBar (level){
    const bar = $('strength-bar');
    bar.className = 'ins-strength';
    if (level > 0) bar.classList.add('s' + level);
}

// Coche visuellement chaque règle respectée (même règle que le back : verifierMotDePasse dans config.js)
function majReglesMotDePasse(resultat) {
    document.querySelectorAll('#ins-password-regles li').forEach(function (li) {
        const ok = resultat.criteres[li.dataset.critere];
        li.classList.toggle('text-success', ok);
        li.classList.toggle('text-muted', !ok);
    });
}

//  ================================= //
//         Remplissage formulaire     //
//  =================================//
const Infos = {
    Nom : $('ins-Nom'),
    PreNom : $('ins-PreNom'),
    Email : $('ins-Email'),
    Tel : $('ins-Telephone'),
    Adresse : $('ins-Adresse'),
    Password : $('ins-Password'),
    Confirm_Password : $('ins-ConfirmPassword')
};

function marquer(input, valide) {
    input.classList.toggle("valid", valide);
    input.classList.toggle("invalid", !valide);
}

// chef d'orchestre
function ValidForm(){
    const regexNom = /^[a-zA-ZÀ-ÿ\s\-']+$/; // refuse les caractères spéciaux
    marquer(Infos.Nom, Infos.Nom.value.trim().length >= 2 && regexNom.test(Infos.Nom.value.trim()));
    marquer(Infos.PreNom, Infos.PreNom.value.trim().length >= 2 && regexNom.test(Infos.PreNom.value.trim()));
    marquer(Infos.Email, /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(Infos.Email.value.trim()));
    marquer(Infos.Tel, /^0[67]\d{8}$/.test(normaliserTelephone(Infos.Tel.value)));
    // Adresse : au moins un numéro/une rue + un code postal à 5 chiffres
    marquer(Infos.Adresse, Infos.Adresse.value.trim().length >= 8 && /\d{5}/.test(Infos.Adresse.value));

    const resultat = verifierMotDePasse(Infos.Password.value);
    updatestrengthBar(resultat.niveau);
    majReglesMotDePasse(resultat);
    marquer(Infos.Password, resultat.valide);
    marquer(Infos.Confirm_Password, Infos.Confirm_Password.value !== '' && Infos.Confirm_Password.value === Infos.Password.value);
}

function normaliserTelephone(valeur) {
    return valeur.replace(/[\s.\-]/g, '').replace(/^\+33/, '0');
}

function isValid(){
    return Object.values(Infos).every(function (input) { return input.classList.contains('valid'); });
}

Object.values(Infos).forEach(function (input) {
    input.addEventListener("input", ValidForm);
});

//  ================================= //
//              Messages              //
//  =================================//
function afficherErreur(message) {
    $('ins-succes').classList.add('d-none');
    $('ins-erreur').textContent = message;
    $('ins-erreur').classList.remove('d-none');
}

function afficherSucces(message) {
    $('ins-erreur').classList.add('d-none');
    $('ins-succes').textContent = message;
    $('ins-succes').classList.remove('d-none');
}

//  ================================= //
//                API                //
//  =================================//
$('btn-inscription-envoie').addEventListener("click", () => Envoie());

async function Envoie() {
    ValidForm();
    if (!isValid()) {
        afficherErreur("Merci de corriger les champs en rouge : tous les champs sont obligatoires et le mot de passe doit respecter toutes les règles.");
        return;
    }

    const bouton = $('btn-inscription-envoie');
    bouton.disabled = true;

    try {
        const reponse = await fetch(API_URL + "/registration", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                "firstName": Infos.PreNom.value.trim(),
                "lastName": Infos.Nom.value.trim(),
                "phoneNumber": normaliserTelephone(Infos.Tel.value),
                "adresse": Infos.Adresse.value.trim(),
                "email": Infos.Email.value.trim(),
                "password": Infos.Password.value
            })
        });
        const data = await reponse.json().catch(() => null);

        if (!reponse.ok) {
            afficherErreur(messageErreurApi(data, "L'inscription a échoué."));
            bouton.disabled = false;
            return;
        }

        // Pas de connexion automatique : le compte doit d'abord être confirmé via le lien reçu par mail.
        $('inscriptionForm').querySelectorAll('input').forEach(function (input) { input.disabled = true; });
        bouton.classList.add('d-none');
        afficherSucces(data.message + " (pensez à regarder dans vos spams)");
    } catch (error) {
        console.error(error);
        afficherErreur("Impossible de contacter le serveur.");
        bouton.disabled = false;
    }
}
})();
