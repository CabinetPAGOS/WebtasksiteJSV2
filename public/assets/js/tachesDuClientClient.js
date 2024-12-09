// GESTION DES FILTRES
$(document).ready(function () {
    const $tasks = $('.custom-rectangle');
    const $searchInput = $('.custom-search-input');
    const $avancementButton = $('#filterDropdown'); // Bouton de filtre d'avancement
    const $piloteButton = $('#filterPiloteDropdown'); // Bouton de filtre de pilote
    const $selectedPiloteText = $('#selectedPiloteText'); // Texte du bouton de filtre pilote

    // Variables pour conserver les filtres
    let currentAvancement = getUrlParam('filter') || ''; // Filtre d'avancement
    let currentPiloteId = getUrlParam('filterByPilote') || ''; // Filtre de pilote
    let currentPiloteName = getUrlParam('piloteName') || '-- Tous les pilotes --'; // Nom du pilote

    // Fonction pour récupérer les paramètres d'URL
    function getUrlParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param) || ''; // Retourne la valeur du paramètre ou une chaîne vide
    }

    // Fonction pour afficher toutes les tâches
    function showAllTasks() {
        $tasks.removeClass('hidden');
        updateTaskInfoText();
    }

    // Fonction pour mettre à jour le texte d'information
    function updateTaskInfoText() {
        const visibleTasks = $tasks.filter(':not(.hidden)');
        if (visibleTasks.length === 0) {
            $('#pageInfoText').text('Aucune tâche trouvée');
        } else {
            $('#pageInfoText').text(`${visibleTasks.length} tâche(s) trouvée(s)`);
        }
    }

    // Fonction pour filtrer les tâches
    function filterTasks(query = '') {
        const lowerCaseQuery = query.toLowerCase();

        $tasks.each(function () {
            const $task = $(this);
            const taskCode = ($task.data('code') || '').toString().toLowerCase();
            const taskTitre = ($task.data('titre') || '').toString().toLowerCase();
            const taskAvancement = $task.data('avancement') || '';
            const taskPiloteId = $task.data('pilote-id') || '';

            // Vérification des filtres
            const matchesQuery = taskCode.includes(lowerCaseQuery) || taskTitre.includes(lowerCaseQuery);
            const matchesAvancement = currentAvancement === '' || taskAvancement === currentAvancement;
            const matchesPilote = currentPiloteId === '' || taskPiloteId === currentPiloteId;

            // Affiche ou masque la tâche en fonction des filtres
            if (matchesQuery && matchesAvancement && matchesPilote) {
                $task.removeClass('hidden');
            } else {
                $task.addClass('hidden');
            }
        });

        // Mise à jour du texte d'information
        updateTaskInfoText();
    }

    // Fonction pour mettre à jour le texte du bouton d'avancement
    function updateAvancementButtonText() {
        const avancementText = currentAvancement ? getAvancementDisplayText(currentAvancement) : 'Filtrer par avancement';
        $avancementButton.text(avancementText);
    }

    // Fonction pour mettre à jour le texte du bouton de filtre pilote
    function updatePiloteButtonText() {
        $selectedPiloteText.text(currentPiloteName || 'Filtrer par pilote');
    }

    // Fonction pour récupérer le texte d'affichage de l'avancement
    function getAvancementDisplayText(avancement) {
        switch (avancement) {
            case 'nonPriseEnCompte': return 'Non Prise en Compte';
            case 'priseEnCompte': return 'Prise en Compte';
            case 'terminee': return 'Terminée';
            case 'amelioration': return '❇️ Amélioration ❇️';
            case 'refusee': return '⛔️ Refusée ⛔️';
            case 'validee': return '✅ Validée';
            case 'stopClient': return '❌ Stop Client ❌';
            case 'goClient': return '😃 Go Client 😃';
            default: return '-- Tous les avancements --';
        }
    }

    // Affiche toutes les tâches au chargement de la page
    showAllTasks();

    // Met à jour les filtres et l'URL
    function updateUrlAndFilter(piloteId, piloteName, avancement) {
        const currentUrl = new URL(window.location.href);
        if (piloteId) {
            currentUrl.searchParams.set('filterByPilote', piloteId); // Met à jour le filtre de pilote
            currentUrl.searchParams.set('piloteName', piloteName); // Met à jour le nom du pilote
        } else {
            currentUrl.searchParams.delete('filterByPilote'); // Supprime le filtre de pilote si aucun ID
            currentUrl.searchParams.delete('piloteName'); // Supprime le nom du pilote
        }

        if (avancement) {
            currentUrl.searchParams.set('filter', avancement); // Met à jour le filtre d'avancement
        } else {
            currentUrl.searchParams.delete('filter'); // Supprime le filtre d'avancement si aucune valeur
        }

        // Mise à jour de l'URL et des boutons
        window.history.pushState({}, '', currentUrl); // Met à jour l'URL sans recharger la page
        updateAvancementButtonText(); // Met à jour le texte du bouton d'avancement
        updatePiloteButtonText(); // Met à jour le texte du bouton de filtre pilote
    }

    // Réinitialiser la recherche avec "Entrée"
    $searchInput.on('keypress', function (e) {
        if (e.which === 13) { // Appuie sur la touche Entrée
            const query = $(this).val().trim();
            filterTasks(query); // Appelle le filtrage en tenant compte des autres filtres
        }
    });

    // Fonction pour filtrer par pilote
    window.filterByPilote = function(piloteId, piloteName = '-- Tous les pilotes --') {
        currentPiloteId = piloteId; // Met à jour le filtre de pilote
        currentPiloteName = piloteName; // Met à jour le nom du pilote
        const query = $searchInput.val().trim(); // Récupère la requête de recherche
        updateUrlAndFilter(currentPiloteId, currentPiloteName, currentAvancement); // Met à jour l'URL
        filterTasks(query); // Filtre les tâches avec le filtre de pilote
    };

    // Fonction pour filtrer par avancement
    window.filterByAvancement = function(avancement) {
        currentAvancement = avancement; // Met à jour le filtre d'avancement
        const query = $searchInput.val().trim(); // Récupère la requête de recherche
        updateUrlAndFilter(currentPiloteId, currentPiloteName, currentAvancement); // Met à jour l'URL
        filterTasks(query); // Filtre les tâches avec le filtre de pilote et d'avancement
    };

    // Initialisation du texte des boutons
    updateAvancementButtonText();
    updatePiloteButtonText();
});


// GESTION AFFICHAGE NOTIFICATIONS
document.addEventListener('DOMContentLoaded', function () {
    // Code de gestion des notifications ici

    // Gestion de l'affichage des notifications
    document.getElementById('notificationIcon').addEventListener('click', function() {
        var notificationList = document.getElementById('notificationList');
        notificationList.style.display = notificationList.style.display === 'none' || notificationList.style.display === '' ? 'block' : 'none'; // Toggle
    });
});

function consulterNotification(notificationId) {
    // Rediriger vers la page de consultation de la notification
    window.location.href = `/notification/${notificationId}`; // Remplacez par l'URL réelle
}


// LIRE UNE NOTIFICATION
document.querySelectorAll('.mark-as-read').forEach(button => {
    button.addEventListener('click', function() {
        const notificationId = this.getAttribute('data-id');

        fetch(`/mark-as-read/${notificationId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur lors de la mise à jour de la notification');
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                // Masquer la notification
                const notificationElement = document.getElementById(`notification-${notificationId}`);
                if (notificationElement) {
                    notificationElement.style.display = 'none'; // Masque la notification
                }

                // Mettre à jour le compteur de notifications
                const countElement = document.querySelector('.notification-count');
                const currentCount = parseInt(countElement.textContent, 10);
                countElement.textContent = currentCount - 1; // Décrementer le compteur

                // Vérifiez les notifications du client
                checkClientNotifications();
            } else {
                console.error(data);
            }
        })
        .catch(error => console.error('Erreur:', error));
    });
});

function checkClientNotifications() {
    const clients = document.querySelectorAll('.client-notification');
    let hasVisibleNotifications = false;

    clients.forEach(client => {
        const notifications = client.querySelectorAll('li[id^="notification-"]'); // Sélectionner les notifications
        const visibleNotifications = Array.from(notifications).filter(notification => notification.style.display !== 'none');

        if (visibleNotifications.length === 0) {
            client.style.display = 'none'; // Masquer le client si aucune notification n'est visible
        } else {
            hasVisibleNotifications = true; // Il y a encore des notifications visibles
        }
    });

    // Afficher le message si aucune notification visible
    const notificationList = document.getElementById('notificationList');
    if (!hasVisibleNotifications) {
        notificationList.innerHTML = '<li>Aucune notification disponible</li>'; // Afficher le message
    }
}