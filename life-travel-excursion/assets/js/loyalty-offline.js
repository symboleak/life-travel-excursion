/**
 * Life Travel Excursion - Gestion hors-ligne des points de fidélité
 * Gère la synchronisation des transactions de points lors des reconnexions réseau
 */

(function($) {
    'use strict';

    // Stockage local des transactions de points
    var LTELoyaltyOffline = {
        /**
         * Initialisation
         */
        init: function() {
            // Vérifier la disponibilité de localStorage
            if (!this.isLocalStorageAvailable()) {
                console.log('LTE: localStorage non disponible, synchronisation hors-ligne désactivée');
                return;
            }

            // Initialiser le stockage si nécessaire
            this.initStorage();

            // Écouter les événements de connectivité
            this.setupConnectivityListeners();

            // Écouter les événements d'attribution/déduction de points
            $(document).on('lte_points_awarded', this.handlePointsAwarded);
            $(document).on('lte_points_redeemed', this.handlePointsRedeemed);

            // Vérifier s'il y a des transactions en attente à synchroniser
            $(document).ready(function() {
                LTELoyaltyOffline.attemptSync();
            });

            console.log('LTE: Système de fidélité hors-ligne initialisé');
        },

        /**
         * Vérifie si localStorage est disponible
         */
        isLocalStorageAvailable: function() {
            try {
                var test = 'lte_test';
                localStorage.setItem(test, test);
                localStorage.removeItem(test);
                return true;
            } catch (e) {
                return false;
            }
        },

        /**
         * Initialise le stockage local si nécessaire
         */
        initStorage: function() {
            if (!localStorage.getItem('lte_pending_loyalty_transactions')) {
                localStorage.setItem('lte_pending_loyalty_transactions', JSON.stringify([]));
            }
        },

        /**
         * Configure les écouteurs d'événements de connectivité réseau
         */
        setupConnectivityListeners: function() {
            // Détecter les changements de connectivité
            window.addEventListener('online', function() {
                console.log('LTE: Connectivité rétablie, tentative de synchronisation');
                LTELoyaltyOffline.attemptSync();
            });

            // Pour les navigateurs qui ne supportent pas l'événement 'online'
            setInterval(function() {
                if (navigator.onLine && LTELoyaltyOffline.hasPendingTransactions()) {
                    LTELoyaltyOffline.attemptSync();
                }
            }, 60000); // Vérifier toutes les minutes
        },

        /**
         * Gère l'attribution de points
         * @param {Event} event L'événement
         * @param {Object} data Les données de points
         */
        handlePointsAwarded: function(event, data) {
            if (!navigator.onLine) {
                // Stocker la transaction pour synchronisation ultérieure
                LTELoyaltyOffline.storeTransaction({
                    userId: data.userId,
                    points: data.points,
                    action: 'add',
                    source: data.source || 'order',
                    details: data.details || {}
                });

                // Mettre à jour l'interface utilisateur localement
                LTELoyaltyOffline.updateLocalPointsDisplay(data.points, true);
            }
        },

        /**
         * Gère l'utilisation de points
         * @param {Event} event L'événement
         * @param {Object} data Les données de points
         */
        handlePointsRedeemed: function(event, data) {
            if (!navigator.onLine) {
                // Stocker la transaction pour synchronisation ultérieure
                LTELoyaltyOffline.storeTransaction({
                    userId: data.userId,
                    points: data.points,
                    action: 'deduct',
                    source: data.source || 'checkout',
                    details: data.details || {}
                });

                // Mettre à jour l'interface utilisateur localement
                LTELoyaltyOffline.updateLocalPointsDisplay(data.points, false);
            }
        },

        /**
         * Stocke une transaction de points en local
         * @param {Object} transaction La transaction à stocker
         */
        storeTransaction: function(transaction) {
            var transactions = this.getPendingTransactions();
            
            // Ajouter un ID et horodatage
            transaction.id = 'offline_' + new Date().getTime() + '_' + Math.floor(Math.random() * 1000);
            transaction.timestamp = new Date().getTime();
            
            transactions.push(transaction);
            localStorage.setItem('lte_pending_loyalty_transactions', JSON.stringify(transactions));
            
            console.log('LTE: Transaction de points stockée localement', transaction);
        },

        /**
         * Récupère les transactions en attente
         * @return {Array} Transactions en attente
         */
        getPendingTransactions: function() {
            try {
                var data = localStorage.getItem('lte_pending_loyalty_transactions');
                return data ? JSON.parse(data) : [];
            } catch (e) {
                console.error('LTE: Erreur lors de la lecture des transactions', e);
                return [];
            }
        },

        /**
         * Vérifie s'il y a des transactions en attente
         * @return {Boolean} Vrai s'il y a des transactions en attente
         */
        hasPendingTransactions: function() {
            var transactions = this.getPendingTransactions();
            return transactions.length > 0;
        },

        /**
         * Met à jour visuellement le solde de points sans requête serveur
         * @param {Number} points Nombre de points
         * @param {Boolean} isAddition Vrai si ajout, faux si déduction
         */
        updateLocalPointsDisplay: function(points, isAddition) {
            var $pointsDisplay = $('.lte-loyalty-points-balance');
            if ($pointsDisplay.length) {
                var currentPoints = parseInt($pointsDisplay.text().replace(/[^0-9]/g, '')) || 0;
                var newPoints = isAddition ? currentPoints + points : currentPoints - points;
                
                if (newPoints < 0) newPoints = 0;
                
                $pointsDisplay.text(newPoints);
                
                // Ajouter une indication visuelle que c'est une mise à jour locale
                $pointsDisplay.addClass('lte-pending-sync');
                
                // Afficher une notification
                var message = isAddition ? 
                    points + ' points ajoutés (mode hors-ligne)' : 
                    points + ' points utilisés (mode hors-ligne)';
                
                this.showNotification(message);
            }
        },

        /**
         * Affiche une notification
         * @param {String} message Le message à afficher
         */
        showNotification: function(message) {
            var $notification = $('<div class="lte-offline-notification">' + message + '</div>');
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.addClass('lte-show');
                
                setTimeout(function() {
                    $notification.removeClass('lte-show');
                    setTimeout(function() {
                        $notification.remove();
                    }, 500);
                }, 3000);
            }, 100);
        },

        /**
         * Tente de synchroniser les transactions en attente
         */
        attemptSync: function() {
            if (!navigator.onLine || !this.hasPendingTransactions()) {
                return;
            }
            
            var transactions = this.getPendingTransactions();
            var data = {
                action: 'lte_sync_loyalty_points',
                nonce: lte_loyalty_params.nonce,
                transactions: transactions
            };
            
            $.ajax({
                url: lte_loyalty_params.ajax_url,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Supprimer les transactions synchronisées
                        LTELoyaltyOffline.removeTransactions(response.data.synced_ids);
                        
                        // Actualiser l'affichage des points
                        if (response.data.current_points !== undefined) {
                            $('.lte-loyalty-points-balance').text(response.data.current_points).removeClass('lte-pending-sync');
                        }
                        
                        // Afficher notification de succès
                        if (response.data.synced > 0) {
                            LTELoyaltyOffline.showNotification('Points de fidélité synchronisés avec succès');
                        }
                    } else {
                        console.error('LTE: Erreur de synchronisation', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('LTE: Échec de la synchronisation', error);
                }
            });
        },

        /**
         * Supprime les transactions synchronisées
         * @param {Array} syncedIds IDs des transactions synchronisées
         */
        removeTransactions: function(syncedIds) {
            if (!syncedIds || !syncedIds.length) {
                return;
            }
            
            var transactions = this.getPendingTransactions();
            var remaining = transactions.filter(function(transaction) {
                return syncedIds.indexOf(transaction.id) === -1;
            });
            
            localStorage.setItem('lte_pending_loyalty_transactions', JSON.stringify(remaining));
            
            console.log('LTE: ' + syncedIds.length + ' transactions supprimées, ' + remaining.length + ' restantes');
        }
    };

    // Initialiser au chargement de la page
    $(document).ready(function() {
        LTELoyaltyOffline.init();
    });

})(jQuery);
