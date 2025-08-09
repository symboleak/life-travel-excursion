/**
 * Life Travel - Calculateur de prix interactif
 * 
 * Ce script gère le calcul dynamique des prix d'excursions et 
 * assure un affichage optimal sur tous les appareils, 
 * avec une attention particulière pour les mobiles.
 * 
 * Intègre les configurations des paramètres d'administration (prix saisonniers, 
 * niveaux de prix, etc.) et implémenté avec la même robustesse que sync_abandoned_cart.
 */

(function($) {
    'use strict';
    
    // Variables globales
    var priceCache = {};
    
    /**
     * Initialisation du script
     */
    function init() {
        // Gérer le calcul de prix
        setupPriceCalculator();
        
        // Gérer les toggles des options et extras
        setupOptionToggles();
        
        // Affichage des véhicules
        setupVehicleDisplay();
    }
    
    /**
     * Configuration du calculateur de prix
     */
    function setupPriceCalculator() {
        // Bouton de calcul du prix
        $('.calculate-price-button').on('click', calculatePrice);
        
        // Déclencher automatiquement le calcul si tous les champs requis sont remplis
        $('#participants, #start_date, #end_date').on('change', function() {
            if (areRequiredFieldsFilled()) {
                calculatePrice();
            }
        });
        
        // Calculer le prix lorsqu'on change les options (extras, activités)
        $('input[name^="extras"], input[name^="activities"]').on('change', function() {
            if (areRequiredFieldsFilled()) {
                calculatePrice();
            }
        });
    }
    
    /**
     * Vérifie si tous les champs requis sont remplis
     * 
     * @return {Boolean} Vrai si tous les champs requis sont remplis
     */
    function areRequiredFieldsFilled() {
        // Vérifier si les champs requis sont remplis
        var participants = $('#participants').val();
        var startDate = $('#start_date').val();
        
        return participants && startDate;
    }
    
    /**
     * Calculer le prix
     */
    function calculatePrice() {
        // Récupérer les valeurs des champs
        var productId = $('input[name="add-to-cart"]').val() || $('input[name="excursion_id"]').val();
        var participants = parseInt($('#participants').val()) || 1;
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val() || startDate;
        var excursionType = $('input[name="excursion_type"]').val() || 'group';
        
        // Vérifier que nous avons bien un ID de produit
        if (!productId) {
            console.error('ID de produit non trouvé');
            return;
        }
        
        // Récupérer les extras sélectionnés
        var extras = [];
        $('input[name^="extras"]:checked').each(function() {
            extras.push($(this).val());
        });
        
        // Récupérer les activités sélectionnées
        var activities = [];
        $('input[name^="activities"]:checked').each(function() {
            activities.push($(this).val());
        });
        
        // Appliquer les limitations configurées dans l'administration
        if (typeof lifeTravel !== 'undefined') {
            // Limiter le nombre d'extras si défini
            if (lifeTravel.limits && lifeTravel.limits.max_extras && extras.length > lifeTravel.limits.max_extras) {
                // Conserver uniquement le nombre maximum autorisé
                extras = extras.slice(0, lifeTravel.limits.max_extras);
                alert(lifeTravel.strings.max_extras_message || 'Vous avez atteint le nombre maximum d\'extras autorisés.');
                
                // Mettre à jour l'UI pour refléter les limitations
                $('input[name^="extras"]:checked').each(function(idx) {
                    if (idx >= lifeTravel.limits.max_extras) {
                        $(this).prop('checked', false);
                    }
                });
            }
            
            // Limiter le nombre d'activités si défini
            if (lifeTravel.limits && lifeTravel.limits.max_activities && activities.length > lifeTravel.limits.max_activities) {
                // Conserver uniquement le nombre maximum autorisé
                activities = activities.slice(0, lifeTravel.limits.max_activities);
                alert(lifeTravel.strings.max_activities_message || 'Vous avez atteint le nombre maximum d\'activités autorisées.');
                
                // Mettre à jour l'UI pour refléter les limitations
                $('input[name^="activities"]:checked').each(function(idx) {
                    if (idx >= lifeTravel.limits.max_activities) {
                        $(this).prop('checked', false);
                    }
                });
            }
        }
        
        // Créer une clé de cache unique pour cette combinaison
        var cacheKey = [
            productId,
            participants,
            startDate,
            endDate,
            extras.join(','),
            activities.join(','),
            excursionType
        ].join('|');
        
        // Vérifier si le prix est déjà en cache
        if (priceCache[cacheKey]) {
            updatePriceDisplay(priceCache[cacheKey]);
            return;
        }
        
        // Afficher un loader pendant le calcul
        var $priceBreakdown = $('.price-breakdown');
        var $priceSpinner = $('#price_spinner');
        
        if ($priceSpinner.length) {
            $priceSpinner.show();
        } else {
            $priceBreakdown.html('<div class="loading-spinner">' + (lifeTravel.strings && lifeTravel.strings.calculating ? lifeTravel.strings.calculating : 'Calcul en cours...') + '</div>');
        }
        
        // Faire une requête AJAX pour calculer le prix
        $.ajax({
            url: (typeof lifeTravel !== 'undefined' ? lifeTravel.ajax_url : lifeTravelPrices.ajax_url),
            type: 'POST',
            data: {
                'action': 'life_travel_excursion_calculate_price',
                'product_id': productId,
                'participants': participants,
                'start_date': startDate,
                'end_date': endDate,
                'extras': extras,
                'activities': activities,
                'nonce': (typeof lifeTravel !== 'undefined' ? lifeTravel.nonce : lifeTravelPrices.security),
                'excursion_type': excursionType
            },
            success: function(response) {
                if (response.success) {
                    // Mettre le résultat en cache pour les futures demandes
                    priceCache[cacheKey] = response.data;
                    updatePriceDisplay(response.data);
                } else {
                    $priceBreakdown.html('<p class="error">' + response.data + '</p>');
                }
            },
            error: function() {
                $priceBreakdown.html('<p class="error">Erreur lors du calcul du prix. Veuillez réessayer.</p>');
            }
        });
    }
    
    /**
     * Mettre à jour l'affichage du prix
     * 
     * @param {Object} data Les données du prix
     */
    function updatePriceDisplay(data) {
        // Masquer le spinner de chargement si présent
        $('#price_spinner').hide();
        
        // Affichage du prix total
        $('#total_price').text(data.formatted ? data.formatted.total : formatPrice(data.total_price || data.total || 0));
        
        // Autres éléments d'affichage détaillé selon le format de retour
        if (data.formatted) {
            // Nouveau format retourné par notre service de prix
            $('#price_per_person').text(data.formatted.per_person || '0');
            $('#extras_price').text(data.formatted.extras_price || '0');
            $('#activities_price').text(data.formatted.activities_price || '0');
            $('#participant_count').text(data.raw.participants || '1');
            $('#day_count').text(data.raw.num_days || '1');
            
            // Afficher des informations supplémentaires si disponibles
            var $additionalInfo = $('#additional_units_info');
            if ($additionalInfo.length && data.raw.details && (data.raw.details.seasonal || data.raw.details.group_discount)) {
                var infoText = '';
                
                if (data.raw.details.seasonal) {
                    infoText += data.raw.details.seasonal.label + ' ';
                }
                
                if (data.raw.details.group_discount) {
                    infoText += data.raw.details.group_discount.label + ' ';
                }
                
                if (infoText) {
                    $additionalInfo.text(infoText).show();
                }
            }
        } else {
            // Ancien format pour la rétrocompatibilité
            $('.total-price').text(formatPrice(data.total_price || data.total || 0));
        }
        
        // Mise à jour de la décomposition du prix dans le conteneur dédié
        var $priceBreakdown = $('.price-breakdown');
        var $priceDetails = $('#pricing_details');
        
        // Si nous avons un conteneur de détails spécifique et de nouveaux détails formatés
        if ($priceDetails.length && data.formatted && data.formatted.details) {
            // Ne pas remplacer tout le contenu, mettre à jour uniquement les valeurs
            // Nous avons déjà mis à jour les valeurs principales ci-dessus
        } else {
            // Affichage différent selon le device pour la rétrocompatibilité
            var isMobile = (typeof lifeTravelPrices !== 'undefined' && lifeTravelPrices.is_mobile) || 
                           (typeof lifeTravel !== 'undefined' && lifeTravel.is_mobile) || 
                           window.innerWidth < 768;
            
            if (isMobile && $priceBreakdown.length) {
                renderMobilePriceBreakdown(data, $priceBreakdown);
            } else if ($priceBreakdown.length) {
                renderDesktopPriceBreakdown(data, $priceBreakdown);
            }
        }
        
        // Mise à jour des informations sur les véhicules si nécessaire
        if (data.vehicles_needed || (data.raw && data.raw.vehicles_needed)) {
            updateVehicleInfo(data.vehicles_needed || data.raw.vehicles_needed);
        }
    }
    
    /**
     * Rendu mobile de la décomposition du prix
     * 
     * @param {Object} data Les données du prix
     * @param {jQuery} $container Le conteneur HTML
     */
    function renderMobilePriceBreakdown(data, $container) {
        var html = '<h4>' + lifeTravelPrices.strings.total + ': ' + formatPrice(data.total_price) + '</h4>';
        html += '<div class="price-mobile-breakdown">';
        
        // Prix de base
        html += '<div class="base-price-row">';
        html += '<span>' + lifeTravelPrices.strings.base_price + '</span>';
        html += '<span>' + formatPrice(data.base_price) + '</span>';
        html += '</div>';
        
        // Extras si présents
        if (data.extras_price > 0) {
            html += '<div class="extras-price-row">';
            html += '<span>' + lifeTravelPrices.strings.extras + '</span>';
            html += '<span>' + formatPrice(data.extras_price) + '</span>';
            html += '</div>';
        }
        
        // Activités si présentes
        if (data.activities_price > 0) {
            html += '<div class="activities-price-row">';
            html += '<span>' + lifeTravelPrices.strings.activities + '</span>';
            html += '<span>' + formatPrice(data.activities_price) + '</span>';
            html += '</div>';
        }
        
        // Véhicules si nécessaire
        if (data.vehicles_needed > 1 && data.vehicle_price > 0) {
            html += '<div class="vehicle-price-row">';
            html += '<span>' + lifeTravelPrices.strings.vehicles + ' (' + data.vehicles_needed + ')</span>';
            html += '<span>' + formatPrice(data.vehicle_price) + '</span>';
            html += '</div>';
        }
        
        html += '</div>';
        
        // Ajouter la classe pour les styles mobiles
        $container.html(html).addClass('mobile-view');
    }
    
    /**
     * Rendu desktop de la décomposition du prix
     * 
     * @param {Object} data Les données du prix
     * @param {jQuery} $container Le conteneur HTML
     */
    function renderDesktopPriceBreakdown(data, $container) {
        var html = '<h4>Détail du prix</h4>';
        html += '<table>';
        html += '<tr><th>Élément</th><th>Détails</th><th>Prix</th></tr>';
        
        // Prix de base
        html += '<tr>';
        html += '<td>' + lifeTravelPrices.strings.base_price + '</td>';
        html += '<td>' + data.participants + ' × ' + formatPrice(data.price_per_person) + '</td>';
        html += '<td>' + formatPrice(data.base_price) + '</td>';
        html += '</tr>';
        
        // Extras si présents
        if (data.extras_price > 0 && data.extras_details && data.extras_details.length > 0) {
            $.each(data.extras_details, function(i, extra) {
                html += '<tr>';
                html += '<td>Extra: ' + extra.name + '</td>';
                html += '<td>' + extra.quantity + ' × ' + formatPrice(extra.price) + '</td>';
                html += '<td>' + formatPrice(extra.total) + '</td>';
                html += '</tr>';
            });
        }
        
        // Activités si présentes
        if (data.activities_price > 0 && data.activities_details && data.activities_details.length > 0) {
            $.each(data.activities_details, function(i, activity) {
                html += '<tr>';
                html += '<td>Activité: ' + activity.name + '</td>';
                html += '<td>' + activity.quantity + ' × ' + formatPrice(activity.price) + '</td>';
                html += '<td>' + formatPrice(activity.total) + '</td>';
                html += '</tr>';
            });
        }
        
        // Véhicules si nécessaire
        if (data.vehicles_needed > 0) {
            html += '<tr>';
            html += '<td>' + lifeTravelPrices.strings.vehicles + '</td>';
            html += '<td>' + data.vehicles_needed + ' véhicule(s)</td>';
            html += '<td>' + formatPrice(data.vehicle_price) + '</td>';
            html += '</tr>';
        }
        
        html += '</table>';
        html += '<div class="price-total">' + lifeTravelPrices.strings.total + ': ' + formatPrice(data.total_price) + '</div>';
        
        $container.html(html).removeClass('mobile-view');
    }
    
    /**
     * Configuration des toggles d'options
     */
    function setupOptionToggles() {
        // Rendre les labels d'options et extras cliquables
        $('.option-label, .extra-label').on('click', function(e) {
            // Ne pas déclencher si on a cliqué directement sur la case à cocher
            if (e.target.type !== 'checkbox' && e.target.type !== 'radio') {
                var $input = $(this).find('input');
                $input.prop('checked', !$input.prop('checked')).trigger('change');
            }
        });
    }
    
    /**
     * Configuration de l'affichage des véhicules
     */
    function setupVehicleDisplay() {
        // Préparer le conteneur pour les informations sur les véhicules
        if ($('.vehicle-info-container').length === 0) {
            $('<div class="vehicle-info-container"></div>').insertAfter('.price-breakdown');
        }
    }
    
    /**
     * Mettre à jour les informations sur les véhicules
     * 
     * @param {Number} vehiclesNeeded Nombre de véhicules nécessaires
     */
    function updateVehicleInfo(vehiclesNeeded) {
        var $container = $('.vehicle-info-container');
        
        if (vehiclesNeeded > 1) {
            var html = '<div class="vehicle-options">';
            html += '<div class="vehicle-info">';
            html += '<svg class="vehicle-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3h9l3 4.5v9.5h-2m-10 0h-6v-9.5l3-4.5h3m6 4h-8M3 9h4M17 9h4M3 13h18M5 17h2M17 17h2M5 12v3.5M19 12v3.5M8 12v3.5M16 12v3.5M12 12v3.5"/></svg>';
            html += '<span>' + vehiclesNeeded + ' véhicules seront nécessaires pour cette excursion</span>';
            html += '</div>';
            html += '<p class="vehicle-note">Le coût des véhicules supplémentaires est inclus dans le prix total</p>';
            html += '</div>';
            
            $container.html(html).show();
        } else {
            $container.hide();
        }
    }
    
    /**
     * Formatter un prix
     * 
     * @param {Number} price Le prix à formatter
     * @return {String} Le prix formatté
     */
    function formatPrice(price) {
        if (!price && price !== 0) {
            return '0';
        }
        
        // Utiliser les paramètres d'administration si disponibles
        if (typeof lifeTravel !== 'undefined' && lifeTravel.price_format) {
            var formattedPrice = parseFloat(price).toFixed(lifeTravel.price_format.decimals || 0);
            
            // Remplacer le séparateur décimal
            if (lifeTravel.price_format.decimal_separator) {
                formattedPrice = formattedPrice.replace('.', lifeTravel.price_format.decimal_separator);
            }
            
            // Ajouter les séparateurs de milliers
            if (lifeTravel.price_format.thousand_separator) {
                var parts = formattedPrice.split(lifeTravel.price_format.decimal_separator || '.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, lifeTravel.price_format.thousand_separator);
                formattedPrice = parts.join(lifeTravel.price_format.decimal_separator || '.');
            }
            
            // Appliquer le symbole de devise selon la position configurée
            var currencySymbol = lifeTravel.currency_symbol || '\u00A0XAF';
            
            if (lifeTravel.price_format.currency_position === 'before') {
                return currencySymbol + formattedPrice;
            } else {
                return formattedPrice + '\u00A0' + currencySymbol;
            }
        } 
        // Utiliser la configuration ancienne pour la rétrocompatibilité
        else if (typeof lifeTravelPrices !== 'undefined' && lifeTravelPrices.currency) {
            return lifeTravelPrices.currency + ' ' + parseFloat(price).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        } 
        // Fallback par défaut
        else {
            return parseFloat(price).toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }) + '\u00A0XAF';
        }
    }
    
    // Initialiser le script au chargement du document
    $(document).ready(init);
    
    // Exposer certaines fonctions pour une utilisation externe
    if (typeof window.lifeTravelPriceCalculator === 'undefined') {
        window.lifeTravelPriceCalculator = {
            formatPrice: formatPrice,
            calculate: calculatePrice,
            updateDisplay: updatePriceDisplay
        };
    }
    
})(jQuery);
