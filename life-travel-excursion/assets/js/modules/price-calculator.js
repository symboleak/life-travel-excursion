/**
 * Module Price Calculator - Life Travel Excursion
 * 
 * Gestion du calcul de prix dynamique pour les excursions
 * Version optimisée pour les connexions lentes (Cameroun)
 */

(function($) {
    'use strict';
    
    // Créer le namespace pour le module
    window.LifeTravel = window.LifeTravel || {};
    LifeTravel.priceCalculator = {};
    
    // Cache local pour éviter les requêtes répétées
    var priceCache = {};
    
    /**
     * Initialisation du calculateur de prix
     */
    LifeTravel.priceCalculator.init = function() {
        var $form = $('.life-travel-excursion-form');
        
        // Si le formulaire n'existe pas, on s'arrête là
        if (!$form.length) return;
        
        console.log('Calculateur de prix initialisé');
        
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
            LifeTravel.priceCalculator.updatePrice($form, $priceDetails);
        });
        
        // Calculer le prix initial
        LifeTravel.priceCalculator.updatePrice($form, $priceDetails);
    };
    
    /**
     * Mise à jour du prix basée sur les valeurs actuelles
     * 
     * @param {jQuery} $form Formulaire de réservation
     * @param {jQuery} $priceDetails Conteneur d'affichage du prix
     */
    LifeTravel.priceCalculator.updatePrice = function($form, $priceDetails) {
        // Si le mode réseau est hors ligne, utiliser les données en cache
        if (LifeTravel.config.networkStatus === 'offline') {
            LifeTravel.core.showError('Mode hors ligne. Impossible de calculer un nouveau prix.');
            return;
        }
        
        // Récupérer toutes les valeurs du formulaire
        var productId = $form.find('input[name="product_id"]').val();
        var participants = parseInt($form.find('input[name="participants"]').val()) || 1;
        var startDate = $form.find('input[name="start_date"]').val();
        var endDate = $form.find('input[name="end_date"]').val();
        
        // Récupérer les extras sélectionnés
        var extras = [];
        $form.find('input[name^="extras"]').filter(':checked').each(function() {
            extras.push($(this).val());
        });
        
        // Récupérer les activités sélectionnées
        var activities = [];
        $form.find('input[name^="activities"]').filter(':checked').each(function() {
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
            LifeTravel.priceCalculator.updatePriceDisplay(priceCache[cacheKey], $priceDetails);
            return;
        }
        
        // Spinner d'attente
        $priceDetails.html('<div class="loading-spinner">Calcul en cours...</div>');
        
        // Réduire la taille des données transmises en regroupant des valeurs
        // (important pour les connexions lentes)
        var postData = {
            'action': 'life_travel_excursion_calculate_price',
            'product_id': productId,
            'participants': participants,
            'start_date': startDate,
            'end_date': endDate,
            'extras': extras,
            'activities': activities,
            'security': LifeTravel.config.nonce
        };
        
        // Pour les connexions très lentes, ajouter un indicateur pour obtenir des résultats compressés
        if (LifeTravel.config.networkStatus === 'very_slow') {
            postData.optimize_response = 1;
        }
        
        // Faire une requête AJAX pour calculer le prix
        $.ajax({
            url: LifeTravel.config.ajaxUrl,
            type: 'POST',
            data: postData,
            success: function(response) {
                if (response.success) {
                    // Mettre le résultat en cache pour les futures demandes
                    priceCache[cacheKey] = response.data;
                    LifeTravel.priceCalculator.updatePriceDisplay(response.data, $priceDetails);
                    
                    // Sauvegarder en localStorage pour permettre le mode hors ligne
                    try {
                        localStorage.setItem('lte_last_price_data', JSON.stringify(response.data));
                        localStorage.setItem('lte_last_price_cache_key', cacheKey);
                    } catch (e) {
                        console.warn('Impossible de sauvegarder les données de prix en local');
                    }
                } else {
                    $priceDetails.html('<p class="error">' + response.data + '</p>');
                }
            },
            error: function() {
                // En cas d'erreur, essayer de récupérer les dernières données locales
                try {
                    var lastCacheKey = localStorage.getItem('lte_last_price_cache_key');
                    var lastPriceData = JSON.parse(localStorage.getItem('lte_last_price_data'));
                    
                    if (lastPriceData) {
                        $priceDetails.html('<p class="notice">Connexion perdue. Affichage des dernières données connues.</p>');
                        setTimeout(function() {
                            LifeTravel.priceCalculator.updatePriceDisplay(lastPriceData, $priceDetails);
                        }, 1500);
                        return;
                    }
                } catch (e) {
                    console.warn('Impossible de récupérer les données de prix du cache local');
                }
                
                $priceDetails.html('<p class="error">Erreur lors du calcul du prix. Veuillez réessayer.</p>');
            }
        });
    };
    
    /**
     * Mettre à jour l'affichage des détails du prix
     * 
     * @param {Object} data Les données de prix
     * @param {jQuery} $priceDetails Conteneur d'affichage du prix
     */
    LifeTravel.priceCalculator.updatePriceDisplay = function(data, $priceDetails) {
        // Si l'écran est petit (mobile), on utilise un format compact
        if (LifeTravel.config.isMobile) {
            var html = '<h4>Détail du prix</h4>';
            html += '<div class="price-mobile-breakdown">';
            html += '<div class="base-price-row"><span>Base</span><span>' + LifeTravel.core.formatPrice(data.base_price) + '</span></div>';
            
            // Ajouter les extras si présents
            if (data.extras_price > 0) {
                html += '<div class="extras-price-row"><span>Extras</span><span>' + LifeTravel.core.formatPrice(data.extras_price) + '</span></div>';
            }
            
            // Ajouter les activités si présentes
            if (data.activities_price > 0) {
                html += '<div class="activities-price-row"><span>Activités</span><span>' + LifeTravel.core.formatPrice(data.activities_price) + '</span></div>';
            }
            
            // Ajouter les infos de véhicules si nécessaire
            if (data.vehicles_needed > 1) {
                html += '<div class="vehicle-price-row"><span>Véhicules (' + data.vehicles_needed + ')</span><span>' + LifeTravel.core.formatPrice(data.vehicle_price) + '</span></div>';
            }
            
            html += '<div class="price-total-row"><span>Total</span><span>' + LifeTravel.core.formatPrice(data.total_price) + '</span></div>';
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
            html += '<td>' + data.participants + ' personne(s) × ' + LifeTravel.core.formatPrice(data.price_per_person) + '</td>';
            html += '<td>' + LifeTravel.core.formatPrice(data.base_price) + '</td>';
            html += '</tr>';
            
            // Extras
            if (data.extras_price > 0 && data.extras_details && data.extras_details.length > 0) {
                for (var i = 0; i < data.extras_details.length; i++) {
                    var extra = data.extras_details[i];
                    html += '<tr>';
                    html += '<td>Extra: ' + extra.name + '</td>';
                    html += '<td>' + extra.quantity + ' × ' + LifeTravel.core.formatPrice(extra.price) + '</td>';
                    html += '<td>' + LifeTravel.core.formatPrice(extra.total) + '</td>';
                    html += '</tr>';
                }
            }
            
            // Activités
            if (data.activities_price > 0 && data.activities_details && data.activities_details.length > 0) {
                for (var i = 0; i < data.activities_details.length; i++) {
                    var activity = data.activities_details[i];
                    html += '<tr>';
                    html += '<td>Activité: ' + activity.name + '</td>';
                    html += '<td>' + activity.quantity + ' × ' + LifeTravel.core.formatPrice(activity.price) + '</td>';
                    html += '<td>' + LifeTravel.core.formatPrice(activity.total) + '</td>';
                    html += '</tr>';
                }
            }
            
            // Véhicules
            if (data.vehicles_needed > 0) {
                html += '<tr>';
                html += '<td>Véhicules</td>';
                html += '<td>' + data.vehicles_needed + ' véhicule(s)</td>';
                html += '<td>' + LifeTravel.core.formatPrice(data.vehicle_price) + '</td>';
                html += '</tr>';
            }
            
            html += '</table>';
            html += '<div class="price-total">Total: ' + LifeTravel.core.formatPrice(data.total_price) + '</div>';
            
            $priceDetails.html(html);
        }
        
        // Déclencher un événement pour notifier les autres modules
        $(document).trigger('price_updated', [data]);
    };
    
    // Initialisation au chargement du DOM
    $(function() {
        LifeTravel.priceCalculator.init();
    });
    
})(jQuery);
