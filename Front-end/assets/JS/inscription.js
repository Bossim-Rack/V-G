
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
// function switch btn Afficher cacher mdp //
function togglebtn (input) {
    if (input.type == 'password') {
        input.type = 'text'
    }
    else {
        input.type = 'password'
    }
}
// 
//appel de la fonction au clic //
$('btn-hide-Password').addEventListener("click",() => togglebtn($('ins-Password')))
$('btn-hide-ConfirmPassword').addEventListener("click", () => togglebtn($('ins-ConfirmPassword')));

//  ================================= //
//         Strength bar du mot de passe //
//  =================================//

function updatestrengthBar (level){
    const bar = $('strength-bar');
    bar.className = 'ins-strength';
    if (level >0 ) bar.classList.add('s'+level);
}

//  ================================= //
//         Remplissage formulaire     //
//  =================================//
// instance chaque variable //
const Infos = {
    
    Nom : $('ins-Nom'),
    PreNom : $('ins-PreNom'),
    Email : $('ins-Email'),
    Tel : $('ins-Telephone'),
    Adresse : $('ins-Adresse'),
    Password : $('ins-Password'),
    Confirm_Password : $('ins-ConfirmPassword')
    };
// chef d'orchestre
function ValidForm(){
    ValidateForm_Nom(Infos.Nom);
    ValidateForm_Nom(Infos.PreNom);
    ValidateForm_Email(Infos.Email);
    ValidateForm_Tel(Infos.Tel);
    ValidateForm_Password(Infos.Password);
    ValidateForm_Confirm_Password(Infos.Confirm_Password);
    $('ins-submit').disabled = !isValid();
}
//  ================================= //
//         Tout les parametres        //
//  =================================//

// valide Nom / PreNom

function ValidateForm_Nom (input) {
    const regex = /^[a-zA-ZÀ-ÿ\s\-']+$/; // refuse les caratere speciaux //
    if(input.value.length >= 2 && regex.test (input.value.trim()) ){
        input.classList.add("valid");
        input.classList.remove("invalid"); 
    }
    else{
        input.classList.remove("valid");
        input.classList.add("invalid");
    }
};
//              Email Valide 
function ValidateForm_Email (input){
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if(regex.test(input.value.trim())){
         input.classList.add("valid");
         input.classList.remove("invalid"); 
     }
     else{
         input.classList.remove("valid");
         input.classList.add("invalid");
     }
};
function ValidateForm_Tel (input){
    const regex = /^0[67]\d{8}$/;
    const valeur = input.value.replace(/^\+33/,'0');
    if(regex.test(valeur.trim())){
        input.classList.add("valid");
        input.classList.remove("invalid"); 
     }
    else{
         input.classList.remove("valid");
         input.classList.add("invalid");
     }
};

// Niveau du mdp, fonction a part //
function LevelClassBar(input){
    let level = 0
    const regexMaj = /[a-z]/;
    const regexMin = /[A-Z]/;
    const regexChiffre = /\d/;
    const regexSpec = /[^a-zA-Z0-9]/;
    if (input.length >= 8){ level++};
    if (regexMaj.test(input) && regexMin.test(input)){level++};
    if (regexChiffre.test(input)){level++};
    if (regexSpec.test(input)){level++};
    return level
}
 //valide Password
 function ValidateForm_Password(input){
    const strengh = LevelClassBar(input.value);
    updatestrengthBar(strengh);
     if(strengh >= 3 ){
         input.classList.add("valid");
         input.classList.remove("invalid"); 
        }
    else{
         input.classList.remove("valid");
         input.classList.add("invalid");
        }
};
 //valide confirm password
 function ValidateForm_Confirm_Password(input){
     if(input.value === Infos.Password.value){
         input.classList.add("valid");
         input.classList.remove("invalid"); 
     }
     else{
         input.classList.remove("valid");
         input.classList.add("invalid");
     }
 };

//  ================================= //
//         Appel des listener        //
//  =================================//

Infos.Nom.addEventListener("keyup",() => ValidForm());
Infos.PreNom.addEventListener("keyup",() => ValidForm());
Infos.Email.addEventListener("keyup",() => ValidForm());
Infos.Tel.addEventListener("keyup",() => ValidForm());
Infos.Password.addEventListener("keyup",() => ValidForm());
Infos.Confirm_Password.addEventListener("input", () => ValidForm());

//  ================================= //
//         Controle du bouton         //
//          de soumission            //
//  =================================//

function isValid(){
    return (
        Infos.Nom.classList.contains('valid') &&
        Infos.PreNom.classList.contains('valid') &&
        Infos.Email.classList.contains('valid') &&
        Infos.Tel.classList.contains('valid') &&
        Infos.Password.classList.contains('valid') &&
        Infos.Confirm_Password.classList.contains('valid')
    );
}
// $('ins-submit').addEventListener('click',() => {
//     $('ins-submit').disabled =true ;
//     $('ins-submit').textContent = 'Inscription';
//     InscriptionUtilisateur();
//     });
//  ================================= //
//                API                //
//  =================================//
$('btn-inscription-envoie').addEventListener("click", () => Envoie());

function Envoie() {
    const myHeaders = new Headers();
    myHeaders.append("Content-Type", "application/json");

    const raw = JSON.stringify({
        "firstName": Infos.PreNom.value,
        "lastName": Infos.Nom.value,
        "phoneNumber" : Infos.Tel.value,
        "adresse" : Infos.Adresse.value || null,
        "email": Infos.Email.value,
        "password": Infos.Password.value
    });

    const requestOptions = {
        method: "POST",
        headers: myHeaders,
        body: raw,
        redirect: "follow"
    };

    fetch("http://127.0.0.1:8000/api/registration", requestOptions)
        .then((response) => response.json())
        .then((result) => {
            console.log(result);
            localStorage.setItem('token', result.apiToken);
            naviguerSPA("/connexion");
        })
        .catch((error) => console.error(error));
}
})();
