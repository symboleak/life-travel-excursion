/**
 * Script pour la page Mon Compte
 * 
 * @package Life_Travel_Excursion
 */
(function($) {
    'use strict';
    
    var LTEAccount = {
        init: function() {
            // Initialiser les fonctionnalités de l'onglet Excursions
            this.setupExcursionsTab();
            
            // Initialiser les fonctionnalités de l'onglet Points de fidélité
            this.setupLoyaltyTab();
        },
        
        /**
         * Configure l'onglet Excursions
         */
        setupExcursionsTab: function() {
            // Toggle des détails de l'excursion
            $('.lte-excursion-toggle').on('click', function(e) {
                e.preventDefault();
                var $details = $(this).closest('.lte-excursion-card').find('.lte-excursion-extra-details');
                $details.slideToggle(300);
                $(this).toggleClass('open');
                
                if ($(this).hasClass('open')) {
                    $(this).text(lteAccount.i18n.hide_details || 'Masquer les détails');
                } else {
                    $(this).text(lteAccount.i18n.show_details || 'Voir les détails');
                }
            });
            
            // Filtrage des excursions
            $('#lte-excursion-filter').on('change', function() {
                var filter = $(this).val();
                
                if (filter === 'all') {
                    $('.lte-excursion-card').show();
                } else {
                    $('.lte-excursion-card').hide();
                    $('.lte-excursion-card[data-status="' + filter + '"]').show();
                }
                
                // Mettre à jour le compteur
                var visibleCount = $('.lte-excursion-card:visible').length;
                $('.lte-excursion-count').text(visibleCount);
            });
        },
        
        /**
         * Configure l'onglet Points de fidélité
         */
        setupLoyaltyTab: function() {
            // Simuler le chargement d'autres transactions d'historique
            $('.lte-load-more-history').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var page = parseInt($button.data('page')) || 1;
                
                // Afficher un indicateur de chargement
                $button.text(lteAccount.i18n.loading || 'Chargement...').prop('disabled', true);
                
                // Charger plus d'historique via AJAX
                $.ajax({
                    url: lteAccount.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'lte_load_loyalty_history',
                        nonce: lteAccount.nonce,
                        page: page + 1
                    },
                    success: function(response) {
                        if (response.success && response.data.html) {
                            // Ajouter les nouvelles entrées
                            $('.lte-loyalty-table tbody').append(response.data.html);
                            
                            // Mettre à jour le numéro de page
                            $button.data('page', page + 1);
                            
                            // Masquer le bouton s'il n'y a plus de données
                            if (!response.data.has_more) {
                                $button.hide();
                            } else {
                                $button.text(lteAccount.i18n.load_more || 'Charger plus');
                                $button.prop('disabled', false);
                            }
                        } else {
                            // Erreur ou plus de données
                            $button.text(lteAccount.i18n.no_more_data || 'Pas plus de données').prop('disabled', true);
                            setTimeout(function() {
                                $button.hide();
                            }, 2000);
                        }
                    },
                    error: function() {
                        $button.text(lteAccount.i18n.try_again || 'Réessayer').prop('disabled', false);
                    }
                });
            });
            
            // Expliquer les règles de fidélité
            $('.lte-loyalty-rules-toggle').on('click', function(e) {
                e.preventDefault();
                $('.lte-loyalty-rules').slideToggle(300);
                $(this).toggleClass('open');
                
                if ($(this).hasClass('open')) {
                    $(this).text(lteAccount.i18n.hide_rules || 'Masquer les règles');
                } else {
                    $(this).text(lteAccount.i18n.show_rules || 'Afficher les règles');
                }
            });
        }
    };
    
    // Initialiser quand le DOM est prêt
    $(document).ready(function() {
        LTEAccount.init();
    });
    
})(jQuery);
