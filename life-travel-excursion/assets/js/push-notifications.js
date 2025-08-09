/**
 * Gestion des notifications push pour Life Travel Excursion
 * Permet d'envoyer des rappels pour les excursions et promotions
 */

(function() {
    // Vérifier si les notifications sont supportées
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.log('Les notifications push ne sont pas supportées par ce navigateur');
        return;
    }

    // Clés publiques VAPID (Elles doivent correspondre aux clés privées côté serveur)
    const applicationServerPublicKey = 'BNbxGYDvlx4zvs2Ss2lFSfL5bpwRbDXkDVcH7uHlHN9YwLxP6YF9V5xPx1j4jcB8npH0NJXeGG9EEfKMxC0xmH4';

    let isSubscribed = false;
    let swRegistration = null;

    /**
     * Initialiser les notifications push
     */
    function initPushNotifications() {
        // L'enregistrement du Service Worker est centralisé côté PHP (pwa-bridge.php)
        // Essayer d'obtenir une registration immédiatement, sinon attendre qu'il soit prêt
        getSWRegistration()
            .then(function(registration) {
                console.log('Service Worker prêt');
                swRegistration = registration;

                // Vérifier l'état d'abonnement
                return swRegistration.pushManager.getSubscription();
            })
            .then(function(subscription) {
                isSubscribed = subscription !== null;
                updateSubscriptionUI();
            })
            .catch(function(error) {
                console.error('Service Worker non prêt ou indisponible:', error);
            });

        // Ajouter les événements aux boutons d'abonnement/désabonnement
        document.querySelectorAll('.lte-subscribe-push').forEach(function(btn) {
            btn.addEventListener('click', subscribeUser);
        });

        document.querySelectorAll('.lte-unsubscribe-push').forEach(function(btn) {
            btn.addEventListener('click', unsubscribeUser);
        });
    }

    /**
     * Récupère l'objet ServiceWorkerRegistration dès que possible
     * Préfère getRegistration() (immédiat si déjà enregistré), sinon attend ready
     * @returns {Promise<ServiceWorkerRegistration>}
     */
    function getSWRegistration() {
        if (!('serviceWorker' in navigator)) {
            return Promise.reject(new Error('Service Worker non supporté'));
        }
        return navigator.serviceWorker.getRegistration().then(function(reg) {
            return reg ? Promise.resolve(reg) : navigator.serviceWorker.ready;
        });
    }

    /**
     * Met à jour l'interface utilisateur selon l'état d'abonnement
     */
    function updateSubscriptionUI() {
        const subscribeButtons = document.querySelectorAll('.lte-subscribe-push');
        const unsubscribeButtons = document.querySelectorAll('.lte-unsubscribe-push');
        const notificationStatus = document.querySelectorAll('.lte-notification-status');

        if (isSubscribed) {
            // Mise à jour UI pour l'état abonné
            subscribeButtons.forEach(btn => {
                btn.style.display = 'none';
            });
            unsubscribeButtons.forEach(btn => {
                btn.style.display = 'inline-block';
            });
            notificationStatus.forEach(status => {
                status.textContent = 'Vous êtes abonné aux notifications';
                status.classList.add('subscribed');
            });
        } else {
            // Mise à jour UI pour l'état non abonné
            subscribeButtons.forEach(btn => {
                btn.style.display = 'inline-block';
            });
            unsubscribeButtons.forEach(btn => {
                btn.style.display = 'none';
            });
            notificationStatus.forEach(status => {
                status.textContent = 'Vous n\'êtes pas abonné aux notifications';
                status.classList.remove('subscribed');
            });
        }
    }

    /**
     * Convertit la clé publique en tableau d'octets
     */
    function urlB64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    /**
     * Abonner l'utilisateur aux notifications push
     */
    function subscribeUser() {
        const applicationServerKey = urlB64ToUint8Array(applicationServerPublicKey);
        
        // Demander l'autorisation pour les notifications
        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                swRegistration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey
                })
                .then(function(subscription) {
                    console.log('Utilisateur abonné:', subscription);
                    isSubscribed = true;
                    updateSubscriptionUI();
                    
                    // Envoyer l'abonnement au serveur
                    sendSubscriptionToServer(subscription);
                })
                .catch(function(error) {
                    console.error('Échec de l\'abonnement:', error);
                    updateSubscriptionUI();
                });
            } else {
                console.log('Permission de notification refusée');
            }
        });
    }

    /**
     * Désabonner l'utilisateur des notifications push
     */
    function unsubscribeUser() {
        swRegistration.pushManager.getSubscription()
            .then(function(subscription) {
                if (subscription) {
                    // Envoyer le désabonnement au serveur
                    sendUnsubscriptionToServer(subscription);
                    
                    // Désabonner côté client
                    return subscription.unsubscribe();
                }
            })
            .then(function() {
                console.log('Utilisateur désabonné');
                isSubscribed = false;
                updateSubscriptionUI();
            })
            .catch(function(error) {
                console.error('Erreur lors du désabonnement:', error);
            });
    }

    /**
     * Envoyer l'abonnement au serveur WordPress
     */
    function sendSubscriptionToServer(subscription) {
        const subscriptionJson = subscription.toJSON();
        
        // Récupérer le nonce depuis l'élément data
        const nonceElem = document.querySelector('[data-push-nonce]');
        const nonce = nonceElem ? nonceElem.dataset.pushNonce : '';
        
        fetch(lifeTravel.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'life_travel_push_subscribe',
                subscription: JSON.stringify(subscriptionJson),
                security: nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Vous êtes maintenant abonné aux notifications!');
            } else {
                console.error('Erreur lors de l\'enregistrement de l\'abonnement:', data.message);
            }
        })
        .catch(error => {
            console.error('Erreur lors de l\'envoi de l\'abonnement:', error);
        });
    }

    /**
     * Envoyer le désabonnement au serveur WordPress
     */
    function sendUnsubscriptionToServer(subscription) {
        const subscriptionJson = subscription.toJSON();
        
        // Récupérer le nonce depuis l'élément data
        const nonceElem = document.querySelector('[data-push-nonce]');
        const nonce = nonceElem ? nonceElem.dataset.pushNonce : '';
        
        fetch(lifeTravel.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'life_travel_push_unsubscribe',
                subscription: JSON.stringify(subscriptionJson),
                security: nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Vous êtes maintenant désabonné des notifications.');
            } else {
                console.error('Erreur lors du désabonnement:', data.message);
            }
        })
        .catch(error => {
            console.error('Erreur lors de l\'envoi du désabonnement:', error);
        });
    }

    /**
     * Afficher une notification à l'utilisateur
     */
    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'lte-notification';
        notification.innerHTML = message + '<span class="lte-close-notification">&times;</span>';
        document.body.appendChild(notification);
        
        // Afficher la notification
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Masquer la notification après 5 secondes
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 5000);
        
        // Permettre la fermeture manuelle
        notification.querySelector('.lte-close-notification').addEventListener('click', () => {
            notification.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        });
    }

    // Initialiser les notifications quand le DOM est chargé
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPushNotifications);
    } else {
        initPushNotifications();
    }
})();
