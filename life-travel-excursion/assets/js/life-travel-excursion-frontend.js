/**
 * Script JavaScript pour Life Travel Excursion
 * 
 * Ce script gère l'affichage dynamique des prix et l'expérience utilisateur
 * pour les excursions de Life Travel.
 */

(function($) {
    'use strict';
    
    // Variables globales
    var priceCache = {};
    
    /**
     * Initialisation du script
     */
    function init() {
        // Gérer la mise à jour des prix en direct
        initPriceCalculator();
        
        // Gérer les extras et options
        initExtrasToggle();
        
        // Gestion des sélections de véhicules
        initVehicleOptions();
        
        // Gérer les méthodes de paiement
        initPaymentMethods();
        
        // Optimisations pour mobiles
        initMobileOptimizations();
        
        // Initialiser la recherche AJAX avec suggestions
        initSearch();
        
        // Initialiser le système de vote AJAX
        initVote();
        
        // Initialiser les onglets My Account
        initAccountTabs();
    }
    
    /**
     * Initialiser le calculateur de prix en direct
     */
    function initPriceCalculator() {
        var $form = $('.life-travel-excursion-form');
        
        // Si le formulaire n'existe pas, on s'arrête là
        if (!$form.length) return;
        
        // Éléments du formulaire
        var $participantsInput = $form.find('input[name="participants"]');
        var $startDateInput = $form.find('input[name="start_date"]');
        var $endDateInput = $form.find('input[name="end_date"]');
        var $extrasInputs = $form.find('input[name^="extras"]');
        var $activitiesInputs = $form.find('input[name^="activities"]');
        var $productIdField = $form.find('input[name="product_id"]');
        
        // Conteneur pour afficher les détails du prix
        var $priceDetails = $('.price-breakdown');
        
        // Mise à jour des prix lors de modifications
        $form.on('change', 'input, select', function() {
            updatePrice();
        });
        
        // Calculer le prix initial
        updatePrice();
        
        /**
         * Mise à jour du prix basée sur les valeurs actuelles
         */
        function updatePrice() {
            var productId = $productIdField.val();
            var participants = parseInt($participantsInput.val()) || 1;
            var startDate = $startDateInput.val();
            var endDate = $endDateInput.val();
            
            // Récupérer les extras sélectionnés
            var extras = [];
            $extrasInputs.filter(':checked').each(function() {
                extras.push($(this).val());
            });
            
            // Récupérer les activités sélectionnées
            var activities = [];
            $activitiesInputs.filter(':checked').each(function() {
                activities.push($(this).val());
            });
            
            // Créer une clé de cache unique pour cette combinaison
            var cacheKey = [
                productId,
                participants,
                startDate,
                endDate,
                extras.join(','),
                activities.join(',')
            ].join('|');
            
            // Vérifier si le prix est déjà en cache
            if (priceCache[cacheKey]) {
                updatePriceDisplay(priceCache[cacheKey]);
                return;
            }
            
            // Spinner d'attente
            $priceDetails.html('<div class="loading-spinner">Calcul en cours...</div>');
            
            // Faire une requête AJAX pour calculer le prix
            $.ajax({
                url: lifeTravel.ajax_url,
                type: 'POST',
                data: {
                    'action': 'life_travel_excursion_calculate_price',
                    'product_id': productId,
                    'participants': participants,
                    'start_date': startDate,
                    'end_date': endDate,
                    'extras': extras,
                    'activities': activities,
                    'security': lifeTravel.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Mettre le résultat en cache pour les futures demandes
                        priceCache[cacheKey] = response.data;
                        updatePriceDisplay(response.data);
                    } else {
                        $priceDetails.html('<p class="error">' + response.data + '</p>');
                    }
                },
                error: function() {
                    $priceDetails.html('<p class="error">Erreur lors du calcul du prix. Veuillez réessayer.</p>');
                }
            });
        }
        
        /**
         * Mettre à jour l'affichage des détails du prix
         * 
         * @param {Object} data Les données de prix
         */
        function updatePriceDisplay(data) {
            // Si l'écran est petit (mobile), on utilise un format compact
            if (lifeTravel.is_mobile) {
                var html = '<h4>Détail du prix</h4>';
                html += '<div class="price-mobile-breakdown">';
                html += '<div class="base-price-row"><span>Base</span><span>' + formatPrice(data.base_price) + '</span></div>';
                
                // Ajouter les extras si présents
                if (data.extras_price > 0) {
                    html += '<div class="extras-price-row"><span>Extras</span><span>' + formatPrice(data.extras_price) + '</span></div>';
                }
                
                // Ajouter les activités si présentes
                if (data.activities_price > 0) {
                    html += '<div class="activities-price-row"><span>Activités</span><span>' + formatPrice(data.activities_price) + '</span></div>';
                }
                
                // Ajouter les infos de véhicules si nécessaire
                if (data.vehicles_needed > 1) {
                    html += '<div class="vehicle-price-row"><span>Véhicules (' + data.vehicles_needed + ')</span><span>' + formatPrice(data.vehicle_price) + '</span></div>';
                }
                
                html += '<div class="price-total-row"><span>Total</span><span>' + formatPrice(data.total_price) + '</span></div>';
                html += '</div>';
                
                $priceDetails.html(html);
            } else {
                // Format desktop plus détaillé
                var html = '<h4>Détail du prix</h4>';
                html += '<table>';
                html += '<tr><th>Élément</th><th>Détails</th><th>Prix</th></tr>';
                
                // Prix de base
                html += '<tr>';
                html += '<td>Prix de base</td>';
                html += '<td>' + data.participants + ' personne(s) × ' + formatPrice(data.price_per_person) + '</td>';
                html += '<td>' + formatPrice(data.base_price) + '</td>';
                html += '</tr>';
                
                // Extras
                if (data.extras_price > 0 && data.extras_details.length > 0) {
                    for (var i = 0; i < data.extras_details.length; i++) {
                        var extra = data.extras_details[i];
                        html += '<tr>';
                        html += '<td>Extra: ' + extra.name + '</td>';
                        html += '<td>' + extra.quantity + ' × ' + formatPrice(extra.price) + '</td>';
                        html += '<td>' + formatPrice(extra.total) + '</td>';
                        html += '</tr>';
                    }
                }
                
                // Activités
                if (data.activities_price > 0 && data.activities_details.length > 0) {
                    for (var i = 0; i < data.activities_details.length; i++) {
                        var activity = data.activities_details[i];
                        html += '<tr>';
                        html += '<td>Activité: ' + activity.name + '</td>';
                        html += '<td>' + activity.quantity + ' × ' + formatPrice(activity.price) + '</td>';
                        html += '<td>' + formatPrice(activity.total) + '</td>';
                        html += '</tr>';
                    }
                }
                
                // Véhicules
                if (data.vehicles_needed > 0) {
                    html += '<tr>';
                    html += '<td>Véhicules</td>';
                    html += '<td>' + data.vehicles_needed + ' véhicule(s)</td>';
                    html += '<td>' + formatPrice(data.vehicle_price) + '</td>';
                    html += '</tr>';
                }
                
                html += '</table>';
                html += '<div class="price-total">Total: ' + formatPrice(data.total_price) + '</div>';
                
                $priceDetails.html(html);
            }
            
            // Mettre à jour l'affichage des véhicules
            updateVehicleDisplay(data.vehicles_needed);
        }
    }
    
    /**
     * Gestion des extras
     */
    function initExtrasToggle() {
        $('.extra-item').on('click', function(e) {
            if (e.target.type !== 'checkbox') {
                var $checkbox = $(this).find('input[type="checkbox"]');
                $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
            }
        });
    }
    
    /**
     * Gestion des options de véhicules
     */
    function initVehicleOptions() {
        // Votre code pour la gestion des véhicules ici
    }
    
    /**
     * Mettre à jour l'affichage des véhicules
     * 
     * @param {Number} vehiclesNeeded Nombre de véhicules nécessaires
     */
    function updateVehicleDisplay(vehiclesNeeded) {
        var $vehicleInfo = $('.vehicle-options');
        
        if (!$vehicleInfo.length) {
            return;
        }
        
        if (vehiclesNeeded > 1) {
            var html = '<div class="vehicle-info">';
            html += '<svg class="vehicle-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3h9l3 4.5v9.5h-2m-10 0h-6v-9.5l3-4.5h3m6 4h-8M3 9h4M17 9h4M3 13h18M5 17h2M17 17h2M5 12v3.5M19 12v3.5M8 12v3.5M16 12v3.5M12 12v3.5"/></svg>';
            html += '<span>' + vehiclesNeeded + ' véhicules seront nécessaires pour cette excursion.</span>';
            html += '</div>';
            html += '<p class="vehicle-note">Le coût des véhicules supplémentaires est inclus dans le prix total.</p>';
            
            $vehicleInfo.html(html).show();
        } else {
            var html = '<div class="vehicle-info">';
            html += '<svg class="vehicle-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3h9l3 4.5v9.5h-2m-10 0h-6v-9.5l3-4.5h3m6 4h-8M3 9h4M17 9h4M3 13h18M5 17h2M17 17h2M5 12v3.5M19 12v3.5M8 12v3.5M16 12v3.5M12 12v3.5"/></svg>';
            html += '<span>Un véhicule standard est inclus.</span>';
            html += '</div>';
            
            $vehicleInfo.html(html).show();
        }
    }
    
    /**
     * Gestion des méthodes de paiement
     */
    function initPaymentMethods() {
        $('.payment-method-item').on('click', function() {
            $('.payment-method-item').removeClass('selected');
            $(this).addClass('selected');
            $(this).find('input[type="radio"]').prop('checked', true);
        });
    }
    
    /**
     * Optimisations pour les mobiles
     */
    function initMobileOptimizations() {
        if (!lifeTravel.is_mobile) {
            return;
        }
        
        // Simplifier l'interface sur mobile
        $('.extras-grid').addClass('mobile-view');
        $('.price-breakdown').addClass('mobile-view');
        
        // Ajuster la taille des champs de saisie
        $('input, select').addClass('mobile-input');
        
        // Optimiser les tableaux pour mobile
        $('table').addClass('mobile-table');
    }
    
    /**
     * Initialiser la recherche AJAX avec suggestions
     */
    function initSearch() {
        var $form = $('.lte-search');
        if (!$form.length) return;
        var $input = $form.find('input[type=search]');
        var $results = $form.find('.lte-search-results');
        var timer;
        $input.on('input', function() {
            clearTimeout(timer);
            var term = $(this).val();
            if (term.length < 2) { $results.empty(); return; }
            timer = setTimeout(function() {
                $.get(LTE_Search.ajax_url, { action:'lte_search_suggestions', term:term, nonce:LTE_Search.nonce }, function(data) {
                    $results.empty();
                    data.forEach(function(item) {
                        var $li = $('<div class="lte-suggestion"><a href="'+item.link+'"><img src="'+item.thumb+'" /><span>'+item.title+'</span></a></div>');
                        $results.append($li);
                    });
                });
            }, 300);
        });
    }
    
    /**
     * Initialiser le système de vote AJAX
     */
    function initVote() {
        var $widgets = $('.lte-vote');
        $widgets.each(function() {
            var $w = $(this);
            var pid = $w.data('product-id');
            $w.find('.lte-vote-button').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $.post(LTE_Vote.ajax_url, { action:'lte_vote_submit', product_id:pid, nonce:LTE_Vote.nonce }, function(resp) {
                    if (resp.success) {
                        // rafraîchir le comptage
                        $.get(LTE_Vote.ajax_url, { action:'lte_vote_results', product_id:pid }, function(count) {
                            $w.find('.lte-vote-results').text(count+' votes');
                        });
                    } else {
                        alert(resp.data.message || 'Error');
                    }
                });
            });
        });
    }
    
    /**
     * Initialiser les onglets dans My Account
     */
    function initAccountTabs() {
        var $tabs = $('.lte-account-tabs button');
        $tabs.on('click', function() {
            var tab = $(this).data('tab');
            $('.lte-account-tabs button').removeClass('active');
            $(this).addClass('active');
            $('.lte-tab-content').hide();
            $('#lte-tab-' + tab).show();
        });
    }
    
    /**
     * Formater un prix selon les paramètres WooCommerce
     * 
     * @param {Number} price Le prix à formater
     * @return {String} Le prix formaté
     */
    function formatPrice(price) {
        var formattedPrice = parseFloat(price).toFixed(lifeTravel.decimals);
        
        formattedPrice = formattedPrice.replace('.', lifeTravel.decimal_separator);
        
        // Ajouter les séparateurs de milliers
        var parts = formattedPrice.split(lifeTravel.decimal_separator);
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, lifeTravel.thousand_separator);
        formattedPrice = parts.join(lifeTravel.decimal_separator);
        
        // Appliquer le format de prix
        return lifeTravel.price_format
            .replace('%1$s', lifeTravel.currency_symbol)
            .replace('%2$s', formattedPrice);
    }
    
    // Initialiser lorsque le document est prêt
    $(document).ready(init);
    
})(jQuery);
