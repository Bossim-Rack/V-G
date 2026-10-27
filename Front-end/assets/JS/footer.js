                          //========================//
                          // Horaires du pied de page //
                          //========================//

//pas de token : la route GET /api/horraires est publique.
async function chargerHorairesFooter() {
  const container = document.getElementById('footer-horaires');
  if (!container) return;

  const jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

  try {
    const reponse = await fetch(API_URL + "/horraires", {
      method: "GET"
    });

    if (!reponse.ok) {
      container.innerHTML = '<p class="footer-text">Horaires indisponibles</p>';
      return;
    }

    const horaires = await reponse.json();

    container.innerHTML = jours.map(function (jour) {
      const horaire = horaires.find(function (h) { return h.jour === jour; });
      const texte = horaire ? horaire.heure : 'Fermé';
      return '<p class="footer-text mb-1">' + jour + ' : ' + texte + '</p>';
    }).join('');

  } catch (error) {
    console.error(error);
    container.innerHTML = '<p class="footer-text">Horaires indisponibles</p>';
  }
}

chargerHorairesFooter();
