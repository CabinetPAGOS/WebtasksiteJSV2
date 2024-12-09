// GESTION DU FORUM
document.getElementById('submit-summary').addEventListener('click', function () {
    const summaryText = document.getElementById('summary-text').value;
    
    // Récupérer l'ID du client depuis l'attribut data
    const clientInfo = document.getElementById('client-info');
    const clientId = clientInfo.dataset.clientId;
    
    if (!clientId) {
        console.error('Erreur : l\'ID du client est introuvable.');
        alert('Impossible de récupérer l\'ID du client.');
        return;
    }

    if (summaryText) {
        fetch(`/client/${clientId}/forum`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({content: summaryText})
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erreur HTTP : ${response.status}`);
            }
            return response.json();
        }).then(data => {
            if (data.content && data.date) { 
                // Ajout du résumé à la liste affichée
                const newEntry = document.createElement('div');
                newEntry.classList.add('forum-entry', 'mb-4');
                newEntry.innerHTML = `
                    <p><strong>Date :</strong> ${new Date(data.date).toLocaleString()}</p>
                    <pre class="forum-content">${data.content}</pre>
                    <hr>`;
                document.getElementById('forum-contents').appendChild(newEntry);

                // Réinitialise le champ texte
                document.getElementById('summary-text').value = ''; 
            } else {
                alert('Erreur lors de l\'ajout du résumé.');
            }
        })
        .catch(error => {
            console.error('Erreur détectée :', error);
            alert('Une erreur est survenue : ' + error.message);
        });
    } else {
        alert('Veuillez entrer un résumé avant de l\'envoyer.');
    }
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