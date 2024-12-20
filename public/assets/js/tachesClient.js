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

        // Vide la barre de recherche lorsque des filtres sont appliqués
        $('.custom-search-input').val('');

        updateActiveFilters(); // Met à jour l'affichage des filtres actifs
        const query = $('.custom-search-input').val().trim(); // Récupère la requête de recherche
        filterTasks(query); // Applique les filtres
    };

    // Fonction pour appliquer les filtres d'avancement
    window.applyAvancementFilters = function () {
        currentAvancements = [];

        // Si "Tous les avancements" est coché
        const allAvancementsChecked = $('.avancement-checkbox[value="all"]').is(':checked');
        if (allAvancementsChecked) {
            // Coche toutes les cases
            $('.avancement-checkbox').prop('checked', true);
            currentAvancements = ['0', '1', '2', '3', '4', '5', '6', '7'];
        } else {
            // Collecte les autres cases cochées
            $('.avancement-checkbox:checked').each(function () {
                const value = $(this).val();
                if (value !== 'all') {
                    currentAvancements.push(value);
                }
            });
        }

        // Vide la barre de recherche lorsque des filtres sont appliqués
        $('.custom-search-input').val('');

        // Met à jour l'affichage des filtres actifs
        updateActiveFilters();

        // Récupère la requête de recherche
        const query = $('.custom-search-input').val().trim();
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

        // Si une recherche est en cours, on applique la recherche en priorité
        if (query !== '') {
            $tasks.each(function () {
                const $task = $(this);
                const taskCode = ($task.data('code') || '').toString().toLowerCase();
                const taskTitre = ($task.data('titre') || '').toString().toLowerCase();
                const matchesQuery = taskCode.includes(lowerCaseQuery) || taskTitre.includes(lowerCaseQuery);

                // Si la tâche correspond à la recherche, on l'affiche
                if (matchesQuery) {
                    $task.removeClass('hidden');
                } else {
                    $task.addClass('hidden');
                }
            });
        } else {
            // Si aucune recherche n'est effectuée, on applique les filtres d'avancement et de pilote
            if (currentAvancements.length === 0 && currentPilotes.length === 0) {
                $tasks.addClass('hidden');
                updateTaskInfoText(); // Mise à jour du texte d'information
                return; // Arrêter le processus de filtrage
            }

            $tasks.each(function () {
                const $task = $(this);
                const taskAvancement = ($task.data('avancement') || '0').toString();
                const taskPilote = ($task.data('pilote') || '').toString();

                const isValidated = taskAvancement === '5'; // Vérifie si la tâche est validée
                const matchesAvancement = currentAvancements.length === 0 || currentAvancements.includes(taskAvancement);
                const matchesPilote = currentPilotes.length === 0 || currentPilotes.includes(taskPilote);

                // Applique les filtres d'avancement et de pilote
                if (matchesAvancement && matchesPilote && (!isValidated || currentAvancements.includes('5'))) {
                    $task.removeClass('hidden');
                } else {
                    $task.addClass('hidden');
                }
            });
        }

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

    (function initializeDefaultFilters() {
        // Initialiser les filtres d'avancement par défaut
        $('.avancement-checkbox[value="0"]').prop('checked', true);
        $('.avancement-checkbox[value="1"]').prop('checked', true);
        $('.avancement-checkbox[value="2"]').prop('checked', true);
        $('.avancement-checkbox[value="3"]').prop('checked', true);
        $('.avancement-checkbox[value="4"]').prop('checked', true);
        $('.avancement-checkbox[value="6"]').prop('checked', true);
        $('.avancement-checkbox[value="7"]').prop('checked', true);
    
        // Appliquer les filtres par défaut
        applyAvancementFilters();
    })();    

    // Gestion des changements dans les cases à cocher
    $('.avancement-checkbox').on('change', function () {
        const isAllCheckbox = $(this).val() === 'all';
    
        if (isAllCheckbox) {
            const isChecked = $(this).is(':checked');
    
            // Si "Tous les avancements" est coché, cocher toutes les autres cases
            $('.avancement-checkbox').prop('checked', isChecked);
        } else {
            // Si une autre option est décochée, décocher "Tous les avancements"
            if (!$(this).is(':checked')) {
                $('.avancement-checkbox[value="all"]').prop('checked', false);
            } else {
                // Vérifie si toutes les cases sont cochées après un changement manuel
                const allChecked = $('.avancement-checkbox:not([value="all"])').length === $('.avancement-checkbox:not([value="all"]):checked').length;
                $('.avancement-checkbox[value="all"]').prop('checked', allChecked);
            }
        }
    
        applyAvancementFilters(); // Appliquer les filtres après les changements
    });    

    $('.pilote-checkbox').on('change', function () {
        applyPiloteFilters();
    });

    // Écouteur pour la barre de recherche
    $searchInput.on('input', function () {
        const query = $(this).val().trim();

        // Réinitialiser les filtres de pilotes
        currentPilotes = [];
        $('.pilote-checkbox').prop('checked', false);

        // Cocher toutes les cases d'avancement automatiquement
        currentAvancements = ['0', '1', '2', '3', '4', '5', '6', '7'];
        $('.avancement-checkbox').prop('checked', true);

        // Met à jour l'affichage des filtres actifs
        updateActiveFilters();

        // Applique la recherche
        filterTasks(query);
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