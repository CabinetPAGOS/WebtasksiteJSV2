// GESTION BOUTON ON/OFF
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-status').forEach(button => {
        button.addEventListener('click', () => {
            const clientId = button.getAttribute('data-client-id');
            const currentStatus = button.getAttribute('data-status');

            fetch(`/client/${clientId}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ status: currentStatus })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.setAttribute('data-status', data.newStatus);
                    button.textContent = data.newStatus == 1 ? 'ON' : 'OFF';
                } else {
                    alert('Erreur lors de la mise à jour.');
                }
            })
            .catch(error => console.error('Erreur :', error));
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