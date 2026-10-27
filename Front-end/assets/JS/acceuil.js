// Enveloppé dans une IIFE : ce script est réinjecté (nouvelle balise <script>) à chaque
// navigation SPA vers cette page. Sans ce wrapper, les "const"/"let" de premier niveau
// entrent en collision au 2e chargement -> "Identifier ... has already been declared".
(function () {
                          //========================//
                          // Avis clients (carousel) //
                          //========================//

function etoiles(note) {
  return '★'.repeat(note) + '☆'.repeat(5 - note);
}

// Appel API : GET /api/avis/publies — route publique, uniquement les avis validés
async function chargerAvisAccueil() {
  const carousel = document.getElementById('avisCarousel');
  if (!carousel) return;

  try {
    const reponse = await fetch(API_URL + "/avis/publies", {
      method: "GET"
    });

    // En cas d'erreur réseau/serveur, on laisse les avis d'exemple déjà dans le HTML.
    if (!reponse.ok) return;

    const avisRecus = await reponse.json();

    if (!avisRecus.length) {
      carousel.outerHTML = '<p class="text-center avis-text mb-0">Aucun avis pour le moment.</p>';
      return;
    }

    const inner = carousel.querySelector('.carousel-inner');
    const indicators = carousel.querySelector('.carousel-indicators');
    inner.innerHTML = '';
    indicators.innerHTML = '';

    avisRecus.forEach(function (avis, index) {
      const item = document.createElement('div');
      item.className = 'carousel-item' + (index === 0 ? ' active' : '');
      item.innerHTML =
        '<div class="row g-4 justify-content-center">' +
          '<div class="col-12 col-md-4">' +
            '<div class="avis-card text-center p-4">' +
              '<div class="stars mb-2">' + etoiles(avis.note) + '</div>' +
              '<p class="avis-text"></p>' +
              '<p class="avis-name fw-bold"></p>' +
            '</div>' +
          '</div>' +
        '</div>';
      item.querySelector('.avis-text').textContent = avis.description || '';
      item.querySelector('.avis-name').textContent = avis.prenom || 'Client Vite & Gourmand';
      inner.appendChild(item);

      const dot = document.createElement('button');
      dot.type = 'button';
      dot.setAttribute('data-bs-target', '#avisCarousel');
      dot.setAttribute('data-bs-slide-to', String(index));
      dot.setAttribute('aria-label', 'Avis ' + (index + 1));
      if (index === 0) {
        dot.className = 'active';
        dot.setAttribute('aria-current', 'true');
      }
      indicators.appendChild(dot);
    });

    // Le contenu de la page est injecté par le routeur après l'évènement "load"
    // de la fenêtre : l'auto-init data-api de Bootstrap (data-bs-ride="carousel")
    // ne s'applique donc pas ici, on initialise le carousel explicitement.
    bootstrap.Carousel.getOrCreateInstance(carousel);

  } catch (error) {
    console.error(error);
  }
}

chargerAvisAccueil();

})();
