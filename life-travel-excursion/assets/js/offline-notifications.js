/**
 * Life Travel - Notifications pour mode hors ligne
 * 
 * Gestion des notifications affichées aux utilisateurs
 * lorsqu'ils tentent d'effectuer des actions en mode hors ligne.
 */

(function($) {
    'use strict';
    
    // Objet principal pour les notifications hors ligne
    const LifeTravelOfflineNotifications = {
        
        // Référence aux éléments DOM
        elements: {
            notification: null,
            overlay: null,
            title: null,
            message: null,
            button: null,
            icon: null,
            close: null
        },
        
        // État de la connexion
        isOffline: false,
        
        // Actions en attente de synchronisation
        pendingActions: [],
        
        /**
         * Initialisation
         */
        init: function() {
            // Vérifier si la variable de messages existe
            if (typeof life_travel_offline_messages === 'undefined') {
                console.warn('Life Travel: Les messages hors ligne ne sont pas définis.');
                return;
            }
            
            // Créer l'overlay s'il n'existe pas
            if (!document.querySelector('.life-travel-offline-overlay')) {
                const overlay = document.createElement('div');
                overlay.className = 'life-travel-offline-overlay';
                document.body.appendChild(overlay);
                this.elements.overlay = overlay;
            } else {
                this.elements.overlay = document.querySelector('.life-travel-offline-overlay');
            }
            
            // Obtenir les références aux éléments DOM
            this.elements.notification = document.getElementById('life-travel-offline-notification');
            
            if (!this.elements.notification) {
                console.warn('Life Travel: L\'élément de notification hors ligne n\'existe pas.');
                return;
            }
            
            this.elements.title = this.elements.notification.querySelector('.life-travel-offline-notification-title');
            this.elements.message = this.elements.notification.querySelector('.life-travel-offline-notification-message');
            this.elements.button = this.elements.notification.querySelector('.life-travel-offline-notification-button');
            this.elements.icon = this.elements.notification.querySelector('.life-travel-offline-notification-icon');
            this.elements.close = this.elements.notification.querySelector('.life-travel-offline-notification-close');
            
            // Initialiser les événements
            this.initEvents();
            
            // Vérifier l'état de la connexion initial
            this.checkConnectionStatus();
            
            // Récupérer les actions en attente du localStorage
            this.loadPendingActions();
            
            // Surveiller les formulaires et boutons importants
            this.monitorForms();
            
            console.log('Life Travel: Système de notifications hors ligne initialisé.');
        },
        
        /**
         * Initialiser les écouteurs d'événements
         */
        initEvents: function() {
            // Listener pour le bouton de fermeture
            if (this.elements.close) {
                this.elements.close.addEventListener('click', this.hideNotification.bind(this));
            }
            
            // Listener pour le bouton d'action principal
            if (this.elements.button) {
                this.elements.button.addEventListener('click', this.handleActionButton.bind(this));
            }
            
            // Écouter les événements de connexion
            window.addEventListener('online', this.handleOnline.bind(this));
            window.addEventListener('offline', this.handleOffline.bind(this));
            
            // Écouter les événements de connexion du détecteur de réseau Life Travel
            document.addEventListener('lifetravel:connection_changed', this.handleConnectionChange.bind(this));
        },
        
        /**
         * Afficher une notification
         * 
         * @param {string} type - Type de notification (reservation, payment, etc.)
         * @param {Object} customData - Données supplémentaires (optionnel)
         */
        showNotification: function(type, customData = {}) {
            // Ne rien faire si les éléments ne sont pas disponibles
            if (!this.elements.notification || !this.elements.title || !this.elements.message || !this.elements.button) {
                return;
            }
            
            // Obtenir les données du message
            const messageData = this.getMessageData(type);
            if (!messageData) return;
            
            // Mise à jour du contenu
            this.elements.title.textContent = messageData.title;
            this.elements.message.textContent = messageData.message;
            this.elements.button.textContent = messageData.action_text;
            
            // Mise à jour de l'icône si elle existe
            if (this.elements.icon && messageData.icon) {
                this.elements.icon.className = 'life-travel-offline-notification-icon icon-' + messageData.icon;
            }
            
            // Stocker le type de notification courante pour les actions du bouton
            this.elements.button.dataset.type = type;
            
            // Stocker les données personnalisées pour les actions du bouton
            if (customData) {
                this.elements.button.dataset.customData = JSON.stringify(customData);
            }
            
            // Afficher la notification et l'overlay
            this.elements.notification.style.display = 'block';
            this.elements.overlay.style.display = 'block';
            
            // Utiliser un setTimeout pour permettre au navigateur de traiter le changement de display
            setTimeout(() => {
                this.elements.notification.classList.add('visible');
                this.elements.overlay.classList.add('visible');
            }, 10);
            
            // Si c'est une action qui nécessite une synchronisation, l'ajouter à la liste
            if (['reservation', 'contact', 'cart_add'].includes(type)) {
                this.addPendingAction(type, customData);
            }
        },
        
        /**
         * Cacher la notification
         */
        hideNotification: function() {
            this.elements.notification.classList.remove('visible');
            this.elements.overlay.classList.remove('visible');
            
            // Utiliser un setTimeout pour masquer complètement après la transition
            setTimeout(() => {
                this.elements.notification.style.display = 'none';
                this.elements.overlay.style.display = 'none';
            }, 300);
        },
        
        /**
         * Obtenir les données d'un message spécifique
         * 
         * @param {string} type - Type de message
         * @return {Object|null} Données du message ou null si non trouvé
         */
        getMessageData: function(type) {
            if (life_travel_offline_messages && life_travel_offline_messages[type]) {
                return life_travel_offline_messages[type];
            }
            
            // Message général par défaut
            if (life_travel_offline_messages && life_travel_offline_messages.general) {
                return life_travel_offline_messages.general;
            }
            
            return null;
        },
        
        /**
         * Gérer le clic sur le bouton d'action principal
         * 
         * @param {Event} event - L'événement de clic
         */
        handleActionButton: function(event) {
            const type = event.target.dataset.type;
            
            // Actions spécifiques selon le type
            switch (type) {
                case 'synchronization':
                    this.syncPendingActions();
                    break;
                case 'reservation':
                    // Stocker la réservation localement
                    this.storeLocalData('reservation', this.getCustomData(event.target));
                    break;
                case 'contact':
                    // Stocker le message de contact localement
                    this.storeLocalData('contact', this.getCustomData(event.target));
                    break;
                case 'cart_add':
                    // Stocker le produit ajouté localement
                    this.storeLocalData('cart', this.getCustomData(event.target));
                    break;
            }
            
            // Fermer la notification
            this.hideNotification();
        },
        
        /**
         * Obtenir les données personnalisées d'un élément
         * 
         * @param {HTMLElement} element - Élément contenant les données
         * @return {Object} Données personnalisées
         */
        getCustomData: function(element) {
            if (element.dataset.customData) {
                try {
                    return JSON.parse(element.dataset.customData);
                } catch (e) {
                    console.error('Life Travel: Erreur de parsing des données personnalisées', e);
                }
            }
            
            return {};
        },
        
        /**
         * Stocker des données localement
         * 
         * @param {string} key - Clé de stockage
         * @param {Object} data - Données à stocker
         */
        storeLocalData: function(key, data) {
            if (!data) return;
            
            // Récupérer les données existantes
            let existingData = localStorage.getItem('lifetravel_offline_' + key);
            let dataArray = [];
            
            if (existingData) {
                try {
                    dataArray = JSON.parse(existingData);
                    if (!Array.isArray(dataArray)) {
                        dataArray = [dataArray];
                    }
                } catch (e) {
                    dataArray = [];
                }
            }
            
            // Ajouter les nouvelles données
            data.timestamp = new Date().getTime();
            dataArray.push(data);
            
            // Stocker les données
            localStorage.setItem('lifetravel_offline_' + key, JSON.stringify(dataArray));
            
            console.log('Life Travel: Données stockées localement', key, data);
        },
        
        /**
         * Ajouter une action en attente de synchronisation
         * 
         * @param {string} type - Type d'action
         * @param {Object} data - Données associées
         */
        addPendingAction: function(type, data) {
            const action = {
                type: type,
                data: data,
                timestamp: new Date().getTime()
            };
            
            this.pendingActions.push(action);
            
            // Stocker les actions en attente
            localStorage.setItem('lifetravel_pending_actions', JSON.stringify(this.pendingActions));
            
            // Mettre à jour le badge si nécessaire
            this.updatePendingBadge();
        },
        
        /**
         * Charger les actions en attente depuis le stockage local
         */
        loadPendingActions: function() {
            const storedActions = localStorage.getItem('lifetravel_pending_actions');
            
            if (storedActions) {
                try {
                    this.pendingActions = JSON.parse(storedActions);
                    
                    // Mettre à jour le badge
                    this.updatePendingBadge();
                } catch (e) {
                    console.error('Life Travel: Erreur lors du chargement des actions en attente', e);
                    this.pendingActions = [];
                }
            } else {
                this.pendingActions = [];
            }
        },
        
        /**
         * Mettre à jour le badge des actions en attente
         */
        updatePendingBadge: function() {
            // Créer ou mettre à jour un badge pour indiquer les actions en attente
            if (this.pendingActions.length > 0) {
                // Trouver ou créer le badge
                let badge = document.getElementById('lifetravel-pending-badge');
                
                if (!badge) {
                    badge = document.createElement('div');
                    badge.id = 'lifetravel-pending-badge';
                    badge.className = 'lifetravel-pending-badge';
                    badge.title = 'Actions en attente de synchronisation';
                    badge.addEventListener('click', () => {
                        if (navigator.onLine) {
                            this.showNotification('synchronization');
                        }
                    });
                    
                    document.body.appendChild(badge);
                }
                
                badge.textContent = this.pendingActions.length;
                badge.style.display = 'flex';
            } else {
                // Masquer le badge s'il n'y a pas d'actions en attente
                const badge = document.getElementById('lifetravel-pending-badge');
                if (badge) {
                    badge.style.display = 'none';
                }
            }
        },
        
        /**
         * Synchroniser les actions en attente
         */
        syncPendingActions: function() {
            if (!navigator.onLine) {
                this.showNotification('general');
                return;
            }
            
            // Si aucune action en attente, ne rien faire
            if (this.pendingActions.length === 0) {
                return;
            }
            
            // Créer une copie des actions pour ne pas modifier l'original pendant la boucle
            const actions = [...this.pendingActions];
            
            // Pour chaque action, envoyer au serveur
            actions.forEach(action => {
                // Simuler un envoi AJAX (à implémenter réellement)
                console.log('Life Travel: Synchronisation de l\'action', action);
                
                // Dans un environnement réel, cela serait remplacé par un appel AJAX
                // Par exemple avec la fonction fetch ou $.ajax
                
                // Pour cet exemple, nous allons simplement supprimer l'action de la liste
                const index = this.pendingActions.findIndex(a => 
                    a.type === action.type && 
                    a.timestamp === action.timestamp
                );
                
                if (index !== -1) {
                    this.pendingActions.splice(index, 1);
                }
            });
            
            // Mettre à jour le stockage
            localStorage.setItem('lifetravel_pending_actions', JSON.stringify(this.pendingActions));
            
            // Mettre à jour le badge
            this.updatePendingBadge();
            
            // Afficher un message de succès
            alert('Synchronisation réussie !');
        },
        
        /**
         * Vérifier l'état de la connexion
         */
        checkConnectionStatus: function() {
            this.isOffline = !navigator.onLine;
            
            // Si nous sommes hors ligne, vérifier les actions en attente
            if (this.isOffline && this.pendingActions.length > 0) {
                // Informer l'utilisateur qu'il y a des actions en attente
                setTimeout(() => {
                    this.showNotification('synchronization');
                }, 2000);
            }
        },
        
        /**
         * Gérer l'événement de connexion
         */
        handleOnline: function() {
            this.isOffline = false;
            
            // Si nous avons des actions en attente, demander à l'utilisateur de synchroniser
            if (this.pendingActions.length > 0) {
                setTimeout(() => {
                    this.showNotification('synchronization');
                }, 1000);
            }
        },
        
        /**
         * Gérer l'événement de déconnexion
         */
        handleOffline: function() {
            this.isOffline = true;
            
            // Informer l'utilisateur qu'il est hors ligne
            this.showNotification('general');
        },
        
        /**
         * Gérer le changement de connexion du détecteur de réseau Life Travel
         * 
         * @param {CustomEvent} event - L'événement de changement de connexion
         */
        handleConnectionChange: function(event) {
            if (event.detail && event.detail.status) {
                // Si nous passons en mode offline ou très lent, considérer comme hors ligne
                if (event.detail.status === 'offline' || event.detail.status === 'very_slow') {
                    if (!this.isOffline) {
                        this.isOffline = true;
                        this.showNotification('general');
                    }
                } else if (this.isOffline) {
                    this.isOffline = false;
                    
                    // Si nous avons des actions en attente, demander à l'utilisateur de synchroniser
                    if (this.pendingActions.length > 0) {
                        setTimeout(() => {
                            this.showNotification('synchronization');
                        }, 1000);
                    }
                }
            }
        },
        
        /**
         * Surveiller les formulaires et boutons importants
         */
        monitorForms: function() {
            // Surveiller les formulaires de réservation
            const reservationForms = document.querySelectorAll('.woocommerce-checkout, .life-travel-booking-form');
            reservationForms.forEach(form => {
                form.addEventListener('submit', (event) => {
                    if (this.isOffline) {
                        event.preventDefault();
                        
                        // Récupérer les données du formulaire
                        const formData = new FormData(form);
                        const data = {};
                        
                        for (let [key, value] of formData.entries()) {
                            data[key] = value;
                        }
                        
                        // Afficher la notification de réservation
                        this.showNotification('reservation', data);
                    }
                });
            });
            
            // Surveiller les formulaires de contact
            const contactForms = document.querySelectorAll('.wpcf7-form, .life-travel-contact-form');
            contactForms.forEach(form => {
                form.addEventListener('submit', (event) => {
                    if (this.isOffline) {
                        event.preventDefault();
                        
                        // Récupérer les données du formulaire
                        const formData = new FormData(form);
                        const data = {};
                        
                        for (let [key, value] of formData.entries()) {
                            data[key] = value;
                        }
                        
                        // Afficher la notification de contact
                        this.showNotification('contact', data);
                    }
                });
            });
            
            // Surveiller les boutons d'ajout au panier
            const addToCartButtons = document.querySelectorAll('.add_to_cart_button, .single_add_to_cart_button');
            addToCartButtons.forEach(button => {
                button.addEventListener('click', (event) => {
                    if (this.isOffline) {
                        event.preventDefault();
                        
                        // Récupérer les données du produit
                        const productId = button.dataset.productId || button.value;
                        const quantity = document.querySelector('input.qty') ? document.querySelector('input.qty').value : 1;
                        
                        const data = {
                            product_id: productId,
                            quantity: quantity
                        };
                        
                        // Afficher la notification d'ajout au panier
                        this.showNotification('cart_add', data);
                    }
                });
            });
            
            // Surveiller les boutons de paiement
            const paymentButtons = document.querySelectorAll('#place_order, .payment-button');
            paymentButtons.forEach(button => {
                button.addEventListener('click', (event) => {
                    if (this.isOffline) {
                        event.preventDefault();
                        this.showNotification('payment');
                    }
                });
            });
        }
    };
    
    // Initialiser les notifications quand le DOM est prêt
    $(document).ready(function() {
        LifeTravelOfflineNotifications.init();
        
        // Exposer l'objet globalement pour permettre l'accès depuis d'autres scripts
        window.LifeTravelOfflineNotifications = LifeTravelOfflineNotifications;
    });
    
})(jQuery);
