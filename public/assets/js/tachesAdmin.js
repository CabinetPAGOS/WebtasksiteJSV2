// GESTION BARRE DE RECHERCHE ET FILTRES
$(document).ready(function () {
    const $tasks = $('.custom-rectangle');
    const $searchInput = $('.custom-search-input');
    const $activeFilters = $('#activeFilters');

    let currentAvancements = []; // Liste des filtres d'avancement
    let currentPilotes = []; // Liste des filtres de pilotes

    // Fonction pour appliquer les filtres de pilotes
    window.applyPiloteFilters = function () {
        currentPilotes = [];
        $('.pilote-checkbox:checked').each(function () {
            const value = $(this).val();
            if (value) {
                currentPilotes.push(value);
            }
        });

        updateActiveFilters(); // Met à jour l'affichage des filtres actifs
        const query = $searchInput.val().trim(); // Récupère la requête de recherche
        filterTasks(query); // Applique les filtres
    };

    // Fonction pour appliquer les filtres d'avancement
    window.applyAvancementFilters = function () {
        currentAvancements = [];
        let allAvancementsChecked = false;  // Si "Tous les avancements" est coché
    
        $('.avancement-checkbox:checked').each(function () {
            const value = $(this).val();
            if (value === 'all') {
                allAvancementsChecked = true;  // Si "Tous les avancements" est coché
            } else if (value) {
                currentAvancements.push(value); // Ajouter les autres filtres d'avancement
            }
        });
    
        // Si "Tous les avancements" est coché, on ignore tous les autres filtres d'avancement
        if (allAvancementsChecked) {
            currentAvancements = ['0', '1', '2', '3', '4', '5', '6', '7'];
        }
    
        // Met à jour l'affichage des filtres actifs
        updateActiveFilters();
    
        // Récupère la requête de recherche
        const query = $searchInput.val().trim();
        filterTasks(query); // Applique les filtres
    }; 

    // Fonction pour mettre à jour l'affichage des filtres actifs
    function updateActiveFilters() {
        const filters = [];

        if (currentAvancements.length > 0) {
            const avancementLabels = currentAvancements.map((value) => {
                switch (value) {
                    case '0': return 'Non Prise en Compte';
                    case '1': return 'Prise en Compte';
                    case '2': return 'Terminée';
                    case '3': return '❇️ Amélioration ❇️';
                    case '4': return '⛔️ Refusée ⛔️';
                    case '5': return '✅ Validée';
                    case '6': return '❌ Stop Client ❌';
                    case '7': return '😃 Go Client 😃';
                    default: return 'Inconnu';
                }
            });
            filters.push(`Avancements : ${avancementLabels.join(', ')}`);
        }

        if (currentPilotes.length > 0) {
            const piloteLabels = currentPilotes.map(id => {
                const pilote = piloteData[id];  // Accède aux données du pilote via l'ID
                if (pilote) {
                    return `${pilote.initiale} ${pilote.nom}`;  // Affiche l'initiale et le nom
                }
                return 'Pilote inconnu';  // Cas où le pilote est introuvable
            }).filter(Boolean);  // Filtrer les valeurs vides (si l'objet pilote est introuvable)

            filters.push(`Pilotes : ${piloteLabels.join(', ')}`);
        }

        $activeFilters.html(filters.length > 0 ? filters.map(f => `<span class="filter-item">${f}</span>`).join('') : '<span>Aucun filtre actif</span>');
    }

    // Fonction pour filtrer les tâches
    function filterTasks(query = '') {
        const lowerCaseQuery = query.toLowerCase();
    
        $tasks.each(function () {
            const $task = $(this);
            const taskCode = ($task.data('code') || '').toString().toLowerCase();
            const taskTitre = ($task.data('titre') || '').toString().toLowerCase();
            const taskAvancement = ($task.data('avancement') || '0').toString();
            const taskPilote = ($task.data('pilote') || '').toString();
    
            const isValidated = taskAvancement === '5'; // Vérifie si la tâche est validée
            const matchesQuery = taskCode.includes(lowerCaseQuery) || taskTitre.includes(lowerCaseQuery);
            const matchesAvancement = currentAvancements.length === 0 || currentAvancements.includes(taskAvancement);
            const matchesPilote = currentPilotes.length === 0 || currentPilotes.includes(taskPilote);

            // Si une recherche est active, afficher uniquement les résultats correspondant
            if (query !== '') {
                if (matchesQuery) {
                    $task.removeClass('hidden');
                } else {
                    $task.addClass('hidden');
                }
            } else {
                // Applique les filtres combinés
                if (matchesAvancement && matchesPilote && (!isValidated || currentAvancements.includes('5'))) {
                    $task.removeClass('hidden');
                } else {
                    $task.addClass('hidden');
                }
            }
        });

        updateTaskInfoText(); // Met à jour le texte d'information des tâches visibles
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

    // Lecture et application des filtres depuis l'URL au chargement
    (function initializeFiltersFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const filter = urlParams.get('filter');

        if (filter) {
            switch (filter) {
                case 'nonPriseEnCompte':
                    $('.avancement-checkbox[value="0"]').prop('checked', true);
                    break;
                case 'priseEnCompte':
                    $('.avancement-checkbox[value="1"]').prop('checked', true);
                    break;
                case 'terminee':
                    $('.avancement-checkbox[value="2"]').prop('checked', true);
                    break;
                case 'amelioration':
                    $('.avancement-checkbox[value="3"]').prop('checked', true);
                    break;
                case 'refusee':
                    $('.avancement-checkbox[value="4"]').prop('checked', true);
                    break;
                case 'validee':
                    $('.avancement-checkbox[value="5"]').prop('checked', true);
                    break;
                case 'stopClient':
                    $('.avancement-checkbox[value="6"]').prop('checked', true);
                    break;
                case 'goClient':
                    $('.avancement-checkbox[value="7"]').prop('checked', true);
                    break;
            }
            applyAvancementFilters();
        }

        // Supprimer le paramètre de l'URL après application du filtre initial
        const newURL = window.location.origin + window.location.pathname;
        window.history.replaceState({}, document.title, newURL);
    })();

    // Gestion des changements dans les cases à cocher
    $('.avancement-checkbox').on('change', function () {
        applyAvancementFilters();
    });

    $('.pilote-checkbox').on('change', function () {
        applyPiloteFilters();
    });

    // Écouteur pour la barre de recherche
    $searchInput.on('input', function () {
        const query = $(this).val().trim();
        
        // Réinitialiser les filtres lors de la recherche
        currentAvancements = [];
        currentPilotes = [];
        updateActiveFilters(); // Mettre à jour l'affichage des filtres actifs (aucun filtre actif)
        
        filterTasks(query); // Applique les filtres de recherche sans les autres filtres
    });

    // Initialisation : Masque toutes les tâches validées au chargement
    filterTasks();
    updateActiveFilters(); // Initialisation des filtres actifs
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