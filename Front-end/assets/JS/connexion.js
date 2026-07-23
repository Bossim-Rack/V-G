// Enveloppé dans une IIFE : ce script est réinjecté (nouvelle balise <script>) à chaque
// navigation SPA vers cette page. Sans ce wrapper, les "const"/"let" de premier niveau
// entrent en collision au 2e chargement -> "Identifier ... has already been declared".
(function () {
// alert ('Bonjour')
function togglebtn (input) {
    if (input.type == 'password') {
        input.type = 'text'
    }
    else {
        input.type = 'password'
    }
};

const $ = id => document.getElementById(id);
$('btn-hide-Password').addEventListener("click",()=> togglebtn($('ins-Password')));

const Infos_CX ={
    Username : $('ins-Email'),
    Password : $('ins-Password')
}
$('btn-cx-form').addEventListener('click', () => connexion() );
function connexion(){
    const myHeaders = new Headers();
    myHeaders.append("Content-Type", "application/json");

    const raw = JSON.stringify({
    "username": Infos_CX.Username.value,
    "password": Infos_CX.Password.value,
    });

    const requestOptions = {
    method: "POST",
    headers: myHeaders,
    body: raw,
    redirect: "follow"
    };
    fetch("http://127.0.0.1:8000/api/login", requestOptions)
        .then((response) => {
            if (response.ok) {
                alert ('Connexion réussi');    
                response.json().then((data) => {
                    console.log(data);
                    localStorage.setItem('token', data.apiToken);
                    localStorage.setItem('roles', JSON.stringify(data.roles));
                    window.location.href = "/";
            })}

            else { alert ('Connexion echoué')}
        })

        .catch((error) => console.error(error));
    }


})();
