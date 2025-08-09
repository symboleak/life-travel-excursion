/**
 * Enregistrement du Service Worker pour Life Travel Excursion
 * Ce script gère les connexions intermittentes et sauvegarde les données en cas de perte de connexion
 */

if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        // L'enregistrement du Service Worker est centralisé dans pwa-bridge.php
        // Ici, on attend simplement qu'il soit prêt pour initialiser les fonctionnalités dépendantes
        navigator.serviceWorker.ready
            .then(function(registration) {
                console.log('Service Worker prêt:', registration.scope);
                if (typeof registration.update === 'function') {
                    registration.update();
                }
            })
            .catch(function(error) {
                console.log('Service Worker non prêt:', error);
            });

        // Écouter les changements de connectivité pour l'interface utilisateur
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);

        // Initialiser l'état de connexion
        updateOnlineStatus();
    });
}

// Mise à jour de l'interface selon l'état de la connexion
function updateOnlineStatus() {
    const isOnline = navigator.onLine;
    
    // Si nous sommes en ligne, tenter de synchroniser les paniers abandonnés
    if (isOnline && navigator.serviceWorker.controller) {
        navigator.serviceWorker.ready
            .then(function(registration) {
                if ('sync' in registration) {
                    registration.sync.register('sync-cart')
                        .catch(function(error) {
                            console.error('Erreur lors de l\'enregistrement de la synchronisation:', error);
                        });
                }
            });
    }
    
    // Afficher un message d'état de connexion
    const connectionStatus = document.getElementById('connection-status');
    if (connectionStatus) {
        if (isOnline) {
            connectionStatus.classList.remove('offline');
            connectionStatus.classList.add('online');
            connectionStatus.textContent = 'Connecté';
        } else {
            connectionStatus.classList.remove('online');
            connectionStatus.classList.add('offline');
            connectionStatus.textContent = 'Hors ligne - Vos données seront sauvegardées';
            
            // Remplacer les soumissions de formulaires par une sauvegarde locale
            setupOfflineFormHandling();
        }
    }
}

// Sauvegarde locale des formulaires quand hors ligne
function setupOfflineFormHandling() {
    const bookingForms = document.querySelectorAll('.excursion-booking-form');
    
    bookingForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                
                // Sauvegarder les données du formulaire localement
                const formData = new FormData(form);
                const formDataObject = {};
                
                for (const [key, value] of formData.entries()) {
                    formDataObject[key] = value;
                }
                
                // Utiliser localforage pour stocker les données (nécessite le chargement de localforage.js)
                if (window.localforage) {
                    localforage.setItem('abandoned_cart', {
                        timestamp: Date.now(),
                        form: formDataObject,
                        url: window.location.href
                    }).then(function() {
                        // Afficher un message à l'utilisateur
                        alert('Vous êtes actuellement hors ligne. Vos informations de réservation ont été sauvegardées et seront envoyées automatiquement lorsque votre connexion sera rétablie.');
                    });
                }
            }
        });
    });
}
