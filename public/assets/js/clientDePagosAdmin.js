src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"
src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.min.js">


// AFFICHAGE DES TÂCHES DU CLIENT
document.addEventListener('DOMContentLoaded', function () {

    // Fonction d'ajout de résumé
    document.querySelectorAll('.btn-primary.add-summary').forEach(function (button) {
        button.addEventListener('click', function () {
            const clientId = this.getAttribute('data-client-id');
            const summaryText = prompt("Veuillez entrer le résumé de la réunion :");

            if (summaryText) {
                fetch(`/client/${clientId}/forum`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ content: summaryText })
                }).then(response => {
                    if (response.ok) {
                        alert("Résumé ajouté avec succès !");
                    } else {
                        alert("Erreur lors de l'ajout du résumé.");
                    }
                }).catch(error => console.error('Erreur lors de l\'ajout du résumé:', error));
            }
        });
    });

    // Afficher le résumé d'un client
    document.querySelectorAll('.btn-info.show-summary').forEach(function (button) {
        button.addEventListener('click', function () {
            const clientId = this.getAttribute('data-client-id');
            const summaryContainer = document.getElementById('summary-container-' + clientId);
            const summaryContent = document.getElementById('summary-content-' + clientId);

            fetch(`/client/${clientId}/forum`).then(response => response.json()).then(summaryData => {
                if (summaryData.length > 0) {
                    summaryContent.innerHTML = summaryData.map(summary => `<p>${summary.content}</p>`).join('');
                } else {
                    summaryContent.innerHTML = '<p>Aucun résumé trouvé.</p>';
                }
                summaryContainer.style.display = 'block';
            }).catch(error => {
                console.error('Erreur lors de la récupération des résumés:', error);
                summaryContent.innerHTML = '<p>Erreur lors de la récupération des résumés.</p>';
                summaryContainer.style.display = 'block';
            });
        });
    });

    // Gestion de la modale pour les forums
    document.querySelector('.close').onclick = function () {
        document.getElementById('modal-forums').style.display = "none";
    };
    window.onclick = function (event) {
        if (event.target === document.getElementById('modal-forums')) {
            document.getElementById('modal-forums').style.display = "none";
        }
    };

    // Fonction pour charger les WebTasks
    function loadWebTasks(clientId, query = '') {
        fetch(`/client/${clientId}/webtasks?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(webtasksData => {
                const container = document.getElementById('webtasks-list');
                container.innerHTML = ''; // Vider le container

                if (webtasksData.length === 0) {
                    container.innerHTML = '<p><b>Aucune Webtask ne correspond à votre recherche.</b></p>';
                    return;
                }

                const webtasksContainer = document.getElementById('webtasks-container');
                if (!document.getElementById('separator-hr')) {
                    const hr = document.createElement('hr');
                    hr.id = 'separator-hr';
                    webtasksContainer.insertAdjacentElement('beforebegin', hr);
                }

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
                            <button type="button" class="btn btn-secondary consulter rounded" onclick="window.location.href='/admin/consultertaches?id=${webtask.code}'">CONSULTER</button>
                        </div>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Erreur lors de la récupération des Webtasks:', error);
                container.innerHTML = `<p><b>Aucune Webtask pour ce client</b></p>`;
            });
    }

    // Initialisation de la recherche
    function initSearch(clientId, clientName) {
        const container = document.getElementById('webtasks-container');
        container.innerHTML = `
            <h3 style="text-align: center;">Liste des WebTask pour le client : <span class="client-name">${clientName}</span></h3>
            <div class="search-bar">
                <input type="text" id="searchInput" class="search-input" placeholder="Rechercher une WebTask 🔎" value="">
            </div>
            <div class="webtask-container" id="webtasks-list"></div>
        `;

        loadWebTasks(clientId);

        document.getElementById('searchInput').addEventListener('input', function () {
            const query = this.value.trim();
            loadWebTasks(clientId, query);
        });

        document.getElementById('searchInput').addEventListener('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                const query = this.value.trim();
                loadWebTasks(clientId, query);
            }
        });
    }

    // Écoute pour afficher les WebTasks du client
    document.querySelectorAll('.btn-secondary.consulter').forEach(function (button) {
        button.addEventListener('click', function () {
            const clientId = this.getAttribute('data-client-id');
            fetch(`/client/${clientId}/name`).then(response => response.json()).then(clientData => {
                const clientName = clientData.name;
                document.getElementById('webtasks-container').innerHTML = `
                    <h3 style="text-align: center;">Liste des WebTask pour le client : <span class="client-name">${clientName}</span></h3>
                `;
                initSearch(clientId, clientName);
            }).catch(error => {
                console.error('Erreur lors de la récupération des informations du client:', error);
                document.getElementById('webtasks-container').innerHTML = `<p><b>Erreur lors de la récupération des informations du client</b></p>`;
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