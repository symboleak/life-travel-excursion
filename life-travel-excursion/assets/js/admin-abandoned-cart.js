/**
 * JavaScript pour l'administration des paniers abandonnés
 * 
 * Gère les interactions utilisateur et les requêtes AJAX sécurisées
 * pour la gestion des paniers abandonnés.
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

(function($) {
    'use strict';

    // Objet principal pour l'administration des paniers abandonnés
    var LifeTravelAdminCart = {
        
        // Initialisation
        init: function() {
            this.bindEvents();
            this.initCharts();
        },
        
        // Lier les événements
        bindEvents: function() {
            // Action pour envoyer un email de récupération
            $('.life-travel-send-recovery').on('click', this.handleRecoveryEmail);
            
            // Action pour supprimer un panier
            $('.life-travel-delete-cart').on('click', this.handleDeleteCart);
            
            // Actions en masse
            $('#doaction, #doaction2').on('click', this.handleBulkActions);
            
            // Sélectionner/désélectionner toutes les lignes
            $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
                $('input[name="cart_id[]"]').prop('checked', $(this).prop('checked'));
            });
            
            // Mise à jour de la prévisualisation du modèle d'email
            $('#life-travel-email-template-editor').on('input', this.updateEmailPreview);
            
            // Changer le modèle d'email
            $('#life-travel-email-template-select').on('change', this.changeEmailTemplate);
        },
        
        // Initialiser les graphiques (si présents)
        initCharts: function() {
            // Vérifier si la page contient des graphiques et si Chart.js est chargé
            if ($('.life-travel-analytics-chart').length && typeof Chart !== 'undefined') {
                this.initRecoveryRateChart();
                this.initAbandonedProductsChart();
                this.initCartValueChart();
                this.initEmailEfficiencyChart();
            }
        },
        
        // Initialiser le graphique du taux de récupération
        initRecoveryRateChart: function() {
            var ctx = document.getElementById('life-travel-recovery-rate-chart');
            if (!ctx) return;
            
            // Vérifier si les données sont disponibles
            if (!lifeTravelAnalytics || !lifeTravelAnalytics.dates || lifeTravelAnalytics.dates.length === 0) {
                $(ctx).html('<div class="life-travel-no-data">' + 
                    'Aucune donnée disponible pour la période sélectionnée.' +
                    '</div>');
                return;
            }
            
            // Calculer les taux de récupération
            var recoveryRates = [];
            for (var i = 0; i < lifeTravelAnalytics.dates.length; i++) {
                var rate = lifeTravelAnalytics.totals[i] > 0 
                    ? (lifeTravelAnalytics.recovered[i] / lifeTravelAnalytics.totals[i]) * 100 
                    : 0;
                recoveryRates.push(parseFloat(rate.toFixed(2)));
            }
            
            // Créer le graphique
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: lifeTravelAnalytics.dates,
                    datasets: [{
                        label: 'Taux de récupération (%)',
                        data: recoveryRates,
                        borderColor: '#00a0d2',
                        backgroundColor: 'rgba(0, 160, 210, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#00a0d2',
                        pointRadius: 4,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Taux (%)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + '%';
                                }
                            }
                        }
                    }
                }
            });
        },
        
        // Initialiser le graphique des produits abandonnés
        initAbandonedProductsChart: function() {
            var ctx = document.getElementById('life-travel-abandoned-products-chart');
            if (!ctx || !lifeTravelAnalytics.products) return;
            
            // Vérifier si les données sont disponibles
            if (!lifeTravelAnalytics.products || lifeTravelAnalytics.products.length === 0) {
                $(ctx).html('<div class="life-travel-no-data">' + 
                    'Aucune donnée disponible pour la période sélectionnée.' +
                    '</div>');
                return;
            }
            
            // Organiser les données
            var labels = lifeTravelAnalytics.products.map(function(item) {
                return item.name;
            });
            
            var data = lifeTravelAnalytics.products.map(function(item) {
                return item.count;
            });
            
            // Créer le graphique
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Fréquence d\'abandon',
                        data: data,
                        backgroundColor: 'rgba(220, 50, 50, 0.7)',
                        borderColor: '#dc3232',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre d\'abandons'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Produit'
                            }
                        }
                    }
                }
            });
        },
        
        // Initialiser le graphique de la valeur des paniers
        initCartValueChart: function() {
            var ctx = document.getElementById('life-travel-cart-value-chart');
            if (!ctx) return;
            
            // Vérifier si les données sont disponibles
            if (!lifeTravelAnalytics || !lifeTravelAnalytics.dates || lifeTravelAnalytics.dates.length === 0) {
                $(ctx).html('<div class="life-travel-no-data">' + 
                    'Aucune donnée disponible pour la période sélectionnée.' +
                    '</div>');
                return;
            }
            
            // Créer le graphique
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: lifeTravelAnalytics.dates,
                    datasets: [{
                        label: 'Valeur moyenne des paniers',
                        data: lifeTravelAnalytics.avgValues,
                        borderColor: '#826eb4',
                        backgroundColor: 'rgba(130, 110, 180, 0.1)',
                        borderWidth: 2,
                        yAxisID: 'y',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Valeur moyenne'
                            },
                            ticks: {
                                callback: function(value) {
                                    return lifeTravelAnalytics.currencySymbol + value;
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return lifeTravelAnalytics.currencySymbol + context.parsed.y;
                                }
                            }
                        }
                    }
                }
            });
        },
        
        // Initialiser le graphique d'efficacité des emails
        initEmailEfficiencyChart: function() {
            var ctx = document.getElementById('life-travel-email-efficiency-chart');
            if (!ctx) return;
            
            // Vérifier si les données sont disponibles
            if (!lifeTravelAnalytics || !lifeTravelAnalytics.dates || lifeTravelAnalytics.dates.length === 0) {
                $(ctx).html('<div class="life-travel-no-data">' + 
                    'Aucune donnée disponible pour la période sélectionnée.' +
                    '</div>');
                return;
            }
            
            // Créer le graphique
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: lifeTravelAnalytics.dates,
                    datasets: [
                        {
                            label: lifeTravelAnalytics.labels.reminded,
                            data: lifeTravelAnalytics.reminded,
                            backgroundColor: 'rgba(49, 112, 143, 0.7)',
                            borderColor: '#31708f',
                            borderWidth: 1
                        },
                        {
                            label: lifeTravelAnalytics.labels.recovered,
                            data: lifeTravelAnalytics.recovered,
                            backgroundColor: 'rgba(70, 180, 80, 0.7)',
                            borderColor: '#46b450',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });
        },
        
        // Gérer l'envoi d'un email de récupération
        handleRecoveryEmail: function(e) {
            e.preventDefault();
            
            var cartId = $(this).data('cart-id');
            var button = $(this);
            
            // Confirmation
            if (!confirm(lifeTravelAdminCart.messages.confirmRecovery)) {
                return;
            }
            
            // Désactiver le bouton et montrer l'état de chargement
            button.prop('disabled', true).text(lifeTravelAdminCart.messages.processingRequest);
            
            // Envoyer la requête AJAX
            $.ajax({
                url: lifeTravelAdminCart.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'life_travel_admin_send_recovery_email',
                    cart_id: cartId,
                    nonce: lifeTravelAdminCart.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Afficher la notification
                        LifeTravelAdminCart.showNotice(response.data.message, 'success');
                        
                        // Mettre à jour le statut dans le tableau
                        var row = button.closest('tr');
                        row.find('.column-recovery').html('<span class="sent">' + response.data.status + '</span>');
                        
                        // Recharger la page après un court délai
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        // Afficher l'erreur
                        LifeTravelAdminCart.showNotice(response.data.message || lifeTravelAdminCart.messages.error, 'error');
                        button.prop('disabled', false).text('Envoyer un rappel');
                    }
                },
                error: function() {
                    // Erreur de communication
                    LifeTravelAdminCart.showNotice(lifeTravelAdminCart.messages.error, 'error');
                    button.prop('disabled', false).text('Envoyer un rappel');
                }
            });
        },
        
        // Gérer la suppression d'un panier
        handleDeleteCart: function(e) {
            e.preventDefault();
            
            var cartId = $(this).data('cart-id');
            var button = $(this);
            
            // Confirmation
            if (!confirm(lifeTravelAdminCart.messages.confirmDelete)) {
                return;
            }
            
            // Désactiver le bouton et montrer l'état de chargement
            button.prop('disabled', true);
            
            // Envoyer la requête AJAX
            $.ajax({
                url: lifeTravelAdminCart.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'life_travel_admin_delete_cart',
                    cart_id: cartId,
                    nonce: lifeTravelAdminCart.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Afficher la notification
                        LifeTravelAdminCart.showNotice(response.data.message, 'success');
                        
                        // Supprimer la ligne du tableau
                        button.closest('tr').fadeOut(400, function() {
                            $(this).remove();
                            
                            // Vérifier s'il reste des lignes
                            if ($('#the-list tr').length === 0) {
                                $('#the-list').append('<tr class="no-items"><td class="colspanchange" colspan="7">' + 
                                    'Aucun panier abandonné trouvé.' +
                                    '</td></tr>');
                            }
                        });
                    } else {
                        // Afficher l'erreur
                        LifeTravelAdminCart.showNotice(response.data.message || lifeTravelAdminCart.messages.error, 'error');
                        button.prop('disabled', false);
                    }
                },
                error: function() {
                    // Erreur de communication
                    LifeTravelAdminCart.showNotice(lifeTravelAdminCart.messages.error, 'error');
                    button.prop('disabled', false);
                }
            });
        },
        
        // Gérer les actions en masse
        handleBulkActions: function(e) {
            var action = $(this).prev('select').val();
            
            // Vérifier si une action est sélectionnée
            if (action !== 'bulk_send_recovery' && action !== 'bulk_delete') {
                return true;
            }
            
            // Vérifier si des paniers sont sélectionnés
            var selectedCarts = $('input[name="cart_id[]"]:checked');
            if (selectedCarts.length === 0) {
                e.preventDefault();
                LifeTravelAdminCart.showNotice('Veuillez sélectionner au moins un panier.', 'error');
                return false;
            }
            
            // Demander confirmation
            if (action === 'bulk_send_recovery') {
                if (!confirm(lifeTravelAdminCart.messages.confirmRecoveryMultiple)) {
                    e.preventDefault();
                    return false;
                }
            } else if (action === 'bulk_delete') {
                if (!confirm(lifeTravelAdminCart.messages.confirmDeleteMultiple)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        },
        
        // Mettre à jour la prévisualisation du modèle d'email
        updateEmailPreview: function() {
            var content = $(this).val();
            $('#life-travel-email-preview').html(content);
        },
        
        // Changer le modèle d'email
        changeEmailTemplate: function() {
            var template = $(this).val();
            var editor = $('#life-travel-email-template-editor');
            
            // Afficher un indicateur de chargement
            editor.prop('disabled', true);
            $('#life-travel-email-preview').html('<p>Chargement du modèle...</p>');
            
            // Récupérer le contenu du modèle
            $.ajax({
                url: lifeTravelAdminCart.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'life_travel_get_email_template',
                    template: template,
                    nonce: lifeTravelAdminCart.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Mettre à jour l'éditeur et la prévisualisation
                        editor.val(response.data.content).prop('disabled', false);
                        $('#life-travel-email-preview').html(response.data.content);
                    } else {
                        // Afficher l'erreur
                        editor.prop('disabled', false);
                        LifeTravelAdminCart.showNotice(response.data.message || 'Erreur lors du chargement du modèle.', 'error');
                    }
                },
                error: function() {
                    // Erreur de communication
                    editor.prop('disabled', false);
                    LifeTravelAdminCart.showNotice('Erreur de communication avec le serveur.', 'error');
                }
            });
        },
        
        // Afficher une notification
        showNotice: function(message, type) {
            // Supprimer les notifications existantes
            $('.life-travel-admin-notice').remove();
            
            // Créer la notification
            var notice = $('<div class="notice is-dismissible life-travel-admin-notice notice-' + type + '"><p>' + message + '</p></div>');
            
            // Ajouter la notification en haut de la page
            $('.wrap h1').after(notice);
            
            // Rendre la notification supprimable
            notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Fermer</span></button>');
            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut(300, function() { $(this).remove(); });
            });
            
            // Faire défiler vers le haut pour montrer la notification
            $('html, body').animate({ scrollTop: 0 }, 300);
        }
    };
    
    // Initialiser lorsque le DOM est prêt
    $(document).ready(function() {
        LifeTravelAdminCart.init();
    });
    
})(jQuery);
