// GESTION DE L'AJOUT DES DOCUMENTS
const maxFiles = 3;
let fileCount = 0;
const fileInputContainer = document.getElementById('fileInputContainer');
const submitButton = document.getElementById('submitButton');
const fileUploadSection = document.getElementById('fileUploadSection');

function handleFileUpload(wantsToUpload, googleDriveLink) {
    if (wantsToUpload) { // Ouvre le lien Google Drive dans une nouvelle fenêtre
        if (googleDriveLink) {
        window.open(googleDriveLink, '_blank');
        }

        // Afficher la section de téléchargement des fichiers
        fileUploadSection.style.display = 'block';
        submitButton.style.display = 'none'; // Masque le bouton "ENREGISTRER" jusqu'à saisie du titre
        fileInputContainer.innerHTML = ''; // Vide les champs existants
        fileCount = 0; // Réinitialise le compteur de fichiers
        addFileInputFields(maxFiles); // Ajoute trois champs pour les fichiers
    } else { // Masque la section de téléchargement
        fileUploadSection.style.display = 'none';
        fileInputContainer.innerHTML = ''; // Efface le contenu de la section pour supprimer les champs et valeurs
        fileCount = 0; // Réinitialise le compteur de fichiers
        submitButton.style.display = 'block'; // Affiche directement le bouton "ENREGISTRER"
    }
}

function addFileInputFields(count) {
    while (fileCount < count && fileCount < maxFiles) {
        fileCount++;
        const fileDiv = document.createElement('div');
        fileDiv.classList.add('form-group');
        fileDiv.innerHTML = `
            <label for="fileTitle${fileCount}"><b>Titre du fichier ${fileCount} :</b></label>
            <input type="text" id="fileTitle${fileCount}" name="fileTitle${fileCount}" class="form-control"
                oninput="validateFileTitle()">
            <label for="fileLink${fileCount}"><b>Lien du fichier ${fileCount} :</b></label>
            <input type="text" id="fileLink${fileCount}" name="fileLink${fileCount}" class="form-control">
        `;
        fileInputContainer.appendChild(fileDiv);
    }
}

// Valide le titre du premier fichier pour afficher le bouton ENREGISTRER
function validateFileTitle() {
    const titleField1 = document.getElementById('fileTitle1');

    if (titleField1 && titleField1.value.trim() !== '') {
        submitButton.style.display = 'block'; // Affiche le bouton "ENREGISTRER" si le titre du premier fichier est rempli
    } else {
        submitButton.style.display = 'none'; // Masque le bouton si le titre du premier fichier est vide
    }
}

function validateForm() {
    let valid = true;
    // Vérifiez que tous les champs de titre et lien sont remplis
    for (let i = 1; i <= maxFiles; i++) {
        const title = document.getElementById(`fileTitle${i}`).value;
        const link = document.getElementById(`fileLink${i}`).value;

        if (!title.trim() || !link.trim()) {
            valid = false;
            alert(`Veuillez remplir les champs pour le fichier ${i}`);
            break;
        }
    }
    return valid;
}

document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0'); // Mois de 0 à 11
    const dd = String(today.getDate()).padStart(2, '0'); // Jours du mois

    const formattedDate = `${yyyy}-${mm}-${dd}`;
    const dueDateInput = document.getElementById('due_date');
    dueDateInput.setAttribute('min', formattedDate); // Définir la date minimale

    dueDateInput.addEventListener('change', function() {
        const inputDate = new Date(this.value);
        const limitDate = new Date(today.getTime() + 48 * 60 * 60 * 1000); // 48 heures à partir de maintenant

        if (inputDate < limitDate) {
            document.getElementById('date_warning').style.display = 'block';
        } else {
            document.getElementById('date_warning').style.display = 'none';
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