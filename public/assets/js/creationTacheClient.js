// GESTION DE CREATION D'UNE TACHE
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

document.addEventListener('DOMContentLoaded', function () {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0'); // Mois de 0 à 11
    const dd = String(today.getDate()).padStart(2, '0'); // Jours du mois

    const formattedDate = `${yyyy}-${mm}-${dd}`;
    const dueDateInput = document.getElementById('due_date');
    dueDateInput.setAttribute('min', formattedDate); // Définir la date minimale

    dueDateInput.addEventListener('change', function () {
        const inputDate = new Date(this.value);
        const limitDate = new Date(today.getTime() + 48 * 60 * 60 * 1000); // 48 heures à partir de maintenant

        if (inputDate < limitDate) {
            document.getElementById('date_warning').style.display = 'block';
        } else {
            document.getElementById('date_warning').style.display = 'none';
        }
    });
});


// GESTION DE L'AJOUT DES DOCUMENTS
function showAdditionalFileQuestion() {
    if (!document.getElementById('additionalFileQuestion')) {
        const additionalFileQuestion = document.createElement('div');
        additionalFileQuestion.id = 'additionalFileQuestion';
        additionalFileQuestion.innerHTML = `
            <p>Voulez-vous déposer un autre fichier ?</p>
            <button type="button" onclick="addAnotherFile(true)">Oui</button>
            <button type="button" onclick="addAnotherFile(false)">Non</button>
        `;
        fileInputContainer.appendChild(additionalFileQuestion);
    }
}

function hideAdditionalFileQuestion() {
    const additionalFileQuestion = document.getElementById('additionalFileQuestion');
    if (additionalFileQuestion) 
    additionalFileQuestion.remove();
}

function addAnotherFile(addFile) {
    if (addFile && fileCount < maxFiles) {
        addFileInputFields();
    } else {
        const submitButton = document.getElementById('submitButton');
        const titleField1 = document.getElementById('fileTitle1');

        if (titleField1 && titleField1.value.trim() !== '') {
            submitButton.disabled = false; // Active le bouton de soumission si le titre est rempli
        }hideAdditionalFileQuestion();
    }
}

// Vérification du titre lors de la saisie avec affichage instantané du message d'avertissement
document.getElementById('title').addEventListener('input', function () {
    const title = this.value;
    const warningDiv = document.getElementById('title-warning'); // Div pour afficher les messages d'avertissement

    fetch(`/check-title?title=${
        encodeURIComponent(title)
    }`).then(response => {
        if (!response.ok) {
            throw new Error('Erreur lors de la vérification du titre.');
        }
        return response.json();
    }).then(data => {
        if (data.exists) {
            warningDiv.innerHTML = 'Un titre similaire existe déjà !';
            warningDiv.style.color = 'red'; // Mettre le texte en rouge
        } else {
            warningDiv.innerHTML = ''; // Effacer le message si le titre est unique
        }
    }).catch(error => {
        console.error('Erreur:', error);
        warningDiv.innerHTML = 'Erreur de réseau lors de la vérification du titre.';
        warningDiv.style.color = 'orange'; // Mettre le texte en orange
    });
});

document.getElementById('due_date').addEventListener('change', function () {
    const selectedDate = new Date(this.value);
    const today = new Date();
    const timeDifference = selectedDate.getTime() - today.getTime();
    const daysDifference = Math.ceil(timeDifference / (1000 * 3600 * 24));

    const warningMessage = document.getElementById('date_warning');

    if (daysDifference < 1 && daysDifference > 0) {
        warningMessage.style.display = 'block';
    } else {
        warningMessage.style.display = 'none';
    }
});

function toggleFileUpload() {
    const checkbox = document.getElementById('uploadFileCheckbox');
    const fileUploadSection = document.getElementById('fileUploadSection');
    const hiddenInput = document.querySelector('input[name="documents_attaches"]');

    // Affiche ou masque la section d'upload en fonction de l'état de la case à cocher
    if (checkbox.checked) {
        fileUploadSection.style.display = 'block'; // Affiche la section
        hiddenInput.value = '1'; // Change la valeur à 1
    } else {
        fileUploadSection.style.display = 'none'; // Masque la section
        hiddenInput.value = '0'; // Change la valeur à 0
    }
}

// Fonction pour bannir le point-virgule, la séquence "V(" et les guillemets uniquement pour le champ "title"
function bannirCaracteresInterdits(event) {
    if (event.target.id === "title") {
        event.target.value = event.target.value.replace(/[;""]|V\(/g, "");
    } else {
        event.target.value = event.target.value.replace(/[;]/g, "");
    }
}

// Ajoute l'écouteur d'événements pour les champs du formulaire
const formFields = document.querySelectorAll('input[type="text"], textarea');
formFields.forEach(field => {
    field.addEventListener('input', bannirCaracteresInterdits);
});

document.getElementById('due_date').addEventListener('change', function () {
    const inputDate = new Date(this.value);
    const currentDate = new Date();

    // Calculer la date limite 48h après la date actuelle
    const limitDate = new Date(currentDate.getTime() + 48 * 60 * 60 * 1000);
    // ajout de 48 heures

    // Vérifier si la date choisie est inférieure à la limite des 48 heures
    if (inputDate < limitDate) {
        document.getElementById('date_warning').style.display = 'block';
    } else {
        document.getElementById('date_warning').style.display = 'none';
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