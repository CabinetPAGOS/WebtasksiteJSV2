// GESTION FERMETURE DU MENU
document.addEventListener('DOMContentLoaded', () => {
    const dropdownToggle = document.getElementById('administrationDropdown');
    const dropdownMenu = document.getElementById('administrationMenu');

    // Gestion de l'affichage du menu
    dropdownToggle.addEventListener('click', (event) => {
        event.preventDefault(); // Empêche le comportement par défaut du lien
        dropdownMenu.classList.toggle('hidden'); // Affiche ou masque le menu
    });

    // Masquer le menu si l'utilisateur clique ailleurs
    document.addEventListener('click', (event) => {
        if (!dropdownToggle.contains(event.target) && !dropdownMenu.contains(event.target)) {
            dropdownMenu.classList.add('hidden');
        }
    });
});
