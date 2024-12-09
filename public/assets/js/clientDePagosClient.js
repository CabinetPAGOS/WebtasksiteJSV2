// AFFICHAGE DES TÂCHES DU CLIENT
document.addEventListener('DOMContentLoaded', function () {
    function loadWebTasks(clientId, query = '') {
        fetch(`/client/${clientId}/webtasks?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(webtasksData => {
                var container = document.getElementById('webtasks-list');
                container.innerHTML = ''; // Vider le container

                if (webtasksData.length === 0) {
                    container.innerHTML = '<p><b>Aucune Webtask ne correspond à votre recherche.</b></p>';
                    return;
                }

                // Insérer une barre de séparation avant les webtasks
                var webtasksContainer = document.getElementById('webtasks-container');
                if (!document.getElementById('separator-hr')) {
                    var hr = document.createElement('hr');
                    hr.id = 'separator-hr';
                    webtasksContainer.insertAdjacentElement('beforebegin', hr);
                }

                // Afficher les webtasks
                container.innerHTML = webtasksData.filter(webtask => webtask.etatdelawebtask === 'ON').map(webtask => `
                    
                    <div class="webtask-card hidden" data-code="${webtask.code}" data-title="${webtask.title}">
                        <h2><b>${webtask.title}</b></h2>
                        <ul>
                            <li><b><u>WebTask :</u></b> ${webtask.webtask}</li>
                            <li><b><u>Description :</u></b> ${webtask.description.replace(/\n/g, '<br>')}</li>
                            <li><b><u>Version :</u></b> ${webtask.versionLibelle}</li>
                            <li><b><u>Date de fin demandée :</u></b> ${webtask.datefinDemandee}</li>
                        </ul>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-secondary consulter rounded" onclick="window.location.href='/consultertaches?id=${webtask.code}'">CONSULTER</button>
                        </div>
                    </div>
                
                `).join('');
            })
            .catch(error => {
                console.error('Erreur lors de la récupération des Webtasks:', error);
                container.innerHTML = `<p><b>Aucune Webtask pour ce client</b></p>`;
            });
    }

    function initSearch(clientId, clientName) {
        var container = document.getElementById('webtasks-container');
        container.innerHTML = `
            <h3 style="text-align: center;">Liste des WebTask pour le client : <span class="client-name">${clientName}</span></h3>
            <div class="search-bar">
                <input type="text" id="searchInput" class="search-input" placeholder="Rechercher une WebTask 🔎" value="">
            </div>
            <div class="webtask-container" id="webtasks-list"></div> <!-- Conteneur pour les webtasks -->
        `;

        // Charger les webtasks initiales sans filtre
        loadWebTasks(clientId);

        // Gestion de la recherche
        document.getElementById('searchInput').addEventListener('input', function () {
            const query = this.value.trim();
            loadWebTasks(clientId, query); // Charger les webtasks filtrées
        });

        // Recherche avec la touche "Entrée"
        document.getElementById('searchInput').addEventListener('keypress', function (e) {
            if (e.which === 13) { // Touche Entrée
                e.preventDefault(); // Empêche l'action par défaut de l'Entrée
                const query = this.value.trim();
                loadWebTasks(clientId, query); // Charger les webtasks filtrées
            }
        });
    }

    document.querySelectorAll('.btn-secondary.consulter').forEach(function (button) {
        button.addEventListener('click', function () {
            var clientId = this.getAttribute('data-client-id');

            fetch(`/client/${clientId}/name`)
                .then(response => response.json())
                .then(clientData => {
                    var clientName = clientData.name; // Assurez-vous que `name` est la bonne clé dans la réponse
                    document.getElementById('webtasks-container').innerHTML = `
                        <h3 style="text-align: center;">Liste des WebTask pour le client : <span class="client-name">${clientName}</span></h3>
                    `;

                    // Initialiser la recherche avec le nom du client
                    initSearch(clientId, clientName);

                })
                .catch(error => {
                    console.error('Erreur lors de la récupération des informations du client:', error);
                    var container = document.getElementById('webtasks-container');
                    container.innerHTML = `<p><b>Erreur lors de la récupération des informations du client</b></p>`;
                });
        });
    });
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