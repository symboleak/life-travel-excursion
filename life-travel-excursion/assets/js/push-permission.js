/**
 * Script de demande de permission pour les notifications push du système de fidélité
 *
 * @package Life_Travel
 * @subpackage Frontend
 * @since 2.5.0
 */

(function($) {
    'use strict';
    
    // Attendre que le DOM soit chargé
    $(document).ready(function() {
        // Vérifier si le navigateur supporte les notifications
        if (!('Notification' in window)) {
            console.log('Ce navigateur ne prend pas en charge les notifications desktop');
            return;
        }
        
        // Attendre quelques secondes avant de demander la permission
        setTimeout(askNotificationPermission, 5000);
    });
    
    /**
     * Demande la permission d'envoyer des notifications
     */
    function askNotificationPermission() {
        // Demander seulement si l'utilisateur n'a pas encore pris de décision
        if (Notification.permission !== 'granted' && Notification.permission !== 'denied') {
            // Afficher une boîte de dialogue personnalisée
            if (confirm(ltePushObj.askText)) {
                Notification.requestPermission().then(function(permission) {
                    if (permission === 'granted') {
                        registerServiceWorker();
                    }
                });
            }
        } else if (Notification.permission === 'granted') {
            // Si la permission est déjà accordée, enregistrer le service worker
            registerServiceWorker();
        }
    }
    
    /**
     * Enregistre le service worker et le token de l'appareil
     */
    function registerServiceWorker() {
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            // L'enregistrement du SW est centralisé (pwa-bridge.php). On attend qu'il soit prêt
            navigator.serviceWorker.ready
                .then(function(registration) {
                    console.log('Service Worker prêt pour les notifications push');
                    // Vérifier l'abonnement existant avant de souscrire
                    return registration.pushManager.getSubscription()
                        .then(function(subscription) {
                            if (subscription) {
                                return subscription;
                            }
                            return registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: urlBase64ToUint8Array(ltePushObj.publicKey)
                            });
                        });
                })
                .then(function(subscription) {
                    // Envoyer le token au serveur
                    var token = JSON.stringify(subscription);
                    registerDeviceOnServer(token);
                })
                .catch(function(error) {
                    console.error('Service Worker non prêt ou Push indisponible:', error);
                });
        }
    }
    
    /**
     * Enregistre l'appareil sur le serveur via AJAX
     * 
     * @param {string} token Token de l'appareil
     */
    function registerDeviceOnServer(token) {
        $.ajax({
            url: ltePushObj.ajaxUrl,
            type: 'POST',
            data: {
                action: 'lte_register_push_device',
                nonce: ltePushObj.nonce,
                device_token: token,
                device_type: 'web',
                user_id: ltePushObj.userId
            },
            success: function(response) {
                if (response.success) {
                    console.log('Appareil enregistré avec succès pour les notifications');
                } else {
                    console.error('Erreur lors de l\'enregistrement de l\'appareil:', response.data.message);
                }
            },
            error: function() {
                console.error('Erreur de connexion lors de l\'enregistrement de l\'appareil');
            }
        });
    }
    
    /**
     * Convertit une chaîne base64 en tableau Uint8Array
     * 
     * @param {string} base64String Chaîne en base64
     * @return {Uint8Array} Tableau d'octets
     */
    function urlBase64ToUint8Array(base64String) {
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
    
})(jQuery);
