function updateNavbar() {
  const token = localStorage.getItem("token");
  const guestBlock = document.getElementById("guest-block");
  const userBlock = document.getElementById("user-block");
  const staffLinks = document.querySelectorAll(".staff-only");


  if (!guestBlock || !userBlock) return; // header pas encore chargé

  if (token) {
    guestBlock.classList.add("d-none");
    userBlock.classList.remove("d-none");
    const roles = JSON.parse(localStorage.getItem("roles") || "[]");
    // Le tableau de bord est accessible aux employés ET aux administrateurs
    // (seule la section "Utilisateurs" à l'intérieur reste réservée à l'admin,
    const estStaff = roles.includes("ROLE_ADMIN") || roles.includes("ROLE_EMPLOYE");

    staffLinks.forEach(el => el.classList.toggle("d-none", !estStaff));

  } else {
    guestBlock.classList.remove("d-none");
    userBlock.classList.add("d-none");
    staffLinks.forEach(el => el.classList.add("d-none"));
  }
}
updateNavbar();
// ===========================================//
//                  Deconnection              //
// ===========================================//
const btnDeconnexion = document.getElementById("btn-deconnexion");
if (btnDeconnexion) {
  btnDeconnexion.addEventListener("click", () => {
    localStorage.removeItem("token");
    localStorage.removeItem("roles");
    window.location.href = "/";
  });
}

// ===========================================//
//     Initiales du compte connecté (navbar)   //
// ===========================================//
// Même logique que sur les pages Profil et Tableau de bord (GET /api/account/me),
// appliquée ici au petit badge rond tout à droite de la navbar.
async function chargerInitialesNavbar() {
  const token = localStorage.getItem("token");
  const initialesEl = document.getElementById("user-initials");
  if (!token || !initialesEl) return;

  try {
    const reponse = await fetch(API_URL + "/account/me", {
      method: "GET",
      headers: {
        "X-AUTH-TOKEN": token
      }
    });

    if (!reponse.ok) return;

    const user = await reponse.json();
    const initiales = `${user.firstName[0]}.${user.lastName[0]}`;
    initialesEl.textContent = initiales.toUpperCase();
  } catch (error) {
    console.error(error);
  }
}
chargerInitialesNavbar();
