// GESTION DU FORUM
document.addEventListener("DOMContentLoaded", () => {
    const forumItems = document.querySelectorAll('.forum-badge');
    const forumContentDisplay = document.getElementById('forum-content-display');

    // Afficher le dernier forum créé par défaut
    if (forumItems.length > 0) {
        const firstForum = forumItems[0];
        firstForum.classList.add('active');
        loadForumDetails(firstForum);
    }

    // Écouter les clics sur les badges des forums
    forumItems.forEach(item => {
        item.addEventListener('click', () => {
            forumItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            loadForumDetails(item);
        });
    });

    // Charger les détails du forum sélectionné
    function loadForumDetails(item) {
        const forumId = item.getAttribute('data-forum-id');

        fetch(`/admin/forum/details/${forumId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur HTTP: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    forumContentDisplay.innerHTML = `
                        <div class="forum-entry">
                            <p><strong>Titre :</strong> ${data.forum.titre}</p>
                            <p><strong>Date :</strong> ${data.forum.date}</p>
                            <pre class="forum-content">${data.forum.content}</pre>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des détails du forum:', error);
                alert('Une erreur est survenue lors du chargement du forum. Veuillez réessayer.');
            });
    }

    // Soumettre le formulaire pour créer un nouveau forum
    document.getElementById("submit-forum").addEventListener("click", async () => {
        const title = document.getElementById("forum-title").value.trim();
        const content = document.getElementById("forum-content").value.trim();
        const clientId = document.getElementById("client-info").dataset.clientId;

        if (!title || !content) {
            alert("Veuillez remplir tous les champs.");
            return;
        }

        try {
            const response = await fetch("/admin/forum/create", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    title: title,
                    content: content,
                    clientId: clientId,
                }),
            });

            if (response.ok) {
                alert("Forum créé avec succès !");
                location.reload();
            } else {
                const error = await response.json();
                alert("Erreur : " + error.message);
            }
        } catch (error) {
            console.error("Erreur lors de l'envoi des données :", error);
            alert("Une erreur s'est produite. Veuillez réessayer.");
        }
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