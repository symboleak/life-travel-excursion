/**
 * Script frontend optimisé pour Life Travel Excursion
 * 
 * Ce fichier gère toutes les fonctionnalités interactives côté client:
 * - Réservation d'excursions
 * - Gestion des extras et activités
 * - Calcul de prix en temps réel
 * - Stockage local des données de formulaire
 * - Support hors ligne
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */
jQuery(document).ready(function($) {
    // ======== UTILITAIRES COMMUNS ========
    
    /**
     * Limite la fréquence d'exécution d'une fonction
     * 
     * @param {Function} func - Fonction à exécuter 
     * @param {number} wait - Délai en millisecondes
     * @return {Function} - Fonction debounced
     */
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }
    
    /**
     * Effectue une requête AJAX avec mécanisme de retry automatique
     * 
     * @param {Object} options - Options de la requête $.ajax 
     * @param {number} retries - Nombre de tentatives en cas d'échec
     * @param {number} delay - Délai entre les tentatives en ms
     */
    function ajaxRequestWithRetry(options, retries, delay) {
        function attempt(remainingRetries) {
            $.ajax(options)
            .done(function(response) {
                if (typeof options.success === 'function') {
                    options.success(response);
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                if (remainingRetries > 0) {
                    setTimeout(function() {
                        attempt(remainingRetries - 1);
                    }, delay);
                } else {
                    console.error("Erreur AJAX:", textStatus, errorThrown, jqXHR.responseText);
                    if (typeof options.error === 'function') {
                        options.error(jqXHR, textStatus, errorThrown);
                    }
                }
            });
        }
        attempt(retries);
    }
    
    // ======== GESTION DE L'INTERFACE ========
    
    /**
     * Affiche l'indicateur de chargement
     */
    function showSpinner() {
        $('.loading-spinner').fadeIn(200);
        $('.price-container').addClass('loading');
        $('.error-message').hide();
    }
    
    /**
     * Masque l'indicateur de chargement
     */
    function hideSpinner() {
        $('.loading-spinner').fadeOut(200);
        $('.price-container').removeClass('loading');
    }
    
    /**
     * Affiche un message d'erreur
     * 
     * @param {string} message - Message d'erreur à afficher
     */
    function displayError(message) {
        $('.error-message').html(message).fadeIn(300);
        setTimeout(function() {
            $('.error-message').fadeOut(500);
        }, 6000);
    }
    
    // ======== STOCKAGE LOCAL DES DONNÉES ========
    
    // Configuration du stockage local avec expiration
    var storageExpiration = 3600000; // 1 heure
    var storageKey = 'life_travel_reservation_data';
    
    /**
     * Sauvegarde l'état du formulaire dans le localStorage
     */
    function saveFormState() {
        var state = {
            participants: $('#participants').val(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            extras: {},
            activities: {},
            timestamp: Date.now()
        };
        
        // Capture des valeurs d'extras
        $('.extras-section .extra-item input[type="checkbox"]').each(function(){
            var extraKey = $(this).data('key');
            if ($(this).is(':checked')) {
                var extraType = $(this).data('type').toLowerCase();
                if (extraType === 'quantite' || extraType === 'quantité') {
                    var qty = $(this).closest('.extra-item').find('.extra-quantity input.extra-quantity-input').val();
                    state.extras[extraKey] = qty;
                } else {
                    state.extras[extraKey] = 1;
                }
            }
        });
        
        // Capture des valeurs d'activités
        $('.activities-section .activity-item input[type="number"]').each(function(){
            var activityKeyMatch = $(this).attr('name').match(/(.*?)/);
            if(activityKeyMatch) {
                var activityKey = activityKeyMatch[1];
                state.activities[activityKey] = $(this).val();
            }
        });
        
        // Sauvegarde dans le localStorage
        localStorage.setItem(storageKey, JSON.stringify(state));
    }
    
    /**
     * Charge l'état du formulaire depuis le localStorage
     */
    function loadFormState() {
        var stateStr = localStorage.getItem(storageKey);
        if (!stateStr) return;
        
        try {
            var state = JSON.parse(stateStr);
            
            // Vérifier si les données ont expiré
            if (Date.now() - state.timestamp > storageExpiration) {
                localStorage.removeItem(storageKey);
                return;
            }
            
            // Restaurer les valeurs principales
            if (state.participants) $('#participants').val(state.participants);
            if (state.start_date) $('#start_date').val(state.start_date);
            if (state.end_date) $('#end_date').val(state.end_date);
            
            // Restaurer les extras
            $('.extras-section .extra-item input[type="checkbox"]').each(function(){
                var extraKey = $(this).data('key');
                if (state.extras && state.extras[extraKey]) {
                    $(this).prop('checked', true);
                    var extraType = $(this).data('type').toLowerCase();
                    if (extraType === 'quantite' || extraType === 'quantité') {
                        $(this).closest('.extra-item').find('.extra-quantity').slideDown();
                        $(this).closest('.extra-item').find('.extra-quantity input.extra-quantity-input').val(state.extras[extraKey]);
                    }
                }
            });
            
            // Restaurer les activités
            $('.activities-section .activity-item input[type="number"]').each(function() {
                var activityKeyMatch = $(this).attr('name').match(/(.*?)/);
                if (activityKeyMatch) {
                    var activityKey = activityKeyMatch[1];
                    if (state.activities && state.activities[activityKey]) {
                        $(this).val(state.activities[activityKey]);
                    }
                }
            });
            
            // Mettre à jour le prix total si nécessaire
            if ($('#price_container').length > 0) {
                updateTotalPrice();
            }
        } catch (e) {
            console.error("Erreur lors de la restauration des données:", e);
            localStorage.removeItem(storageKey);
        }
    }
    
    // Charger l'état du formulaire au chargement
    loadFormState();
    
    // ======== INITIALISATION DES DATEPICKERS ========
    
    // Création d'une version debounced pour réduire les appels AJAX
    var debouncedUpdateTotalPrice = debounce(updateTotalPrice, 300);
    
    // Initialisation des datepickers pour les excursions personnalisables
    if ($('#start_date').length > 0 && !$('#start_date').prop('disabled')) {
        var dateToday = new Date();
        var min_days_before = parseInt($('#min_days_before').val()) || 0;
        dateToday.setDate(dateToday.getDate() + min_days_before);
        
        $('#start_date').datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: dateToday,
            beforeShowDay: function(date) {
                // Logique pour désactiver les jours non disponibles
                return [true, ''];
            },
            onSelect: function(selectedDate) {
                var minEndDate = new Date(selectedDate);
                $('#end_date').datepicker('option', 'minDate', minEndDate);
                saveFormState();
                debouncedUpdateTotalPrice();
            }
        });
        
        $('#end_date').datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: dateToday,
            beforeShowDay: function(date) {
                // Vérifie les dates de début qui sont déjà sélectionnées
                if (!$('#start_date').val()) {
                    return [false, ''];
                }
                return [true, ''];
            },
            onSelect: function(selectedDate) {
                saveFormState();
                debouncedUpdateTotalPrice();
            }
        });
    }
    
    // ======== GESTION DES EXTRAS ========
    
    // Affichage/masquage des champs de quantité pour les extras
    $('.extras-section').on('change', 'input[type="checkbox"]', function(){
        var extraItem = $(this).closest('.extra-item');
        var extraType = $(this).data('type').toLowerCase();
        
        if (extraType === 'quantite' || extraType === 'quantité') {
            if ($(this).is(':checked')) {
                extraItem.find('.extra-quantity').slideDown();
            } else {
                extraItem.find('.extra-quantity').slideUp();
            }
        }
        
        saveFormState();
        debouncedUpdateTotalPrice();
    });
    
    // Mise à jour du prix sur changement de quantité
    $('.extras-section').on('change', '.extra-quantity input.extra-quantity-input', function(){
        saveFormState();
        debouncedUpdateTotalPrice();
    });
    
    // Gestion des activités
    $('.activities-section').on('change', 'input[type="number"]', function(){
        saveFormState();
        debouncedUpdateTotalPrice();
    });
    
    // Mise à jour sur modification des champs principaux
    $('#participants, #start_date, #end_date, #start_time, #end_time').on('change', function(){
        saveFormState();
        debouncedUpdateTotalPrice();
    });
    
    // ======== CALCUL DU PRIX TOTAL EN TEMPS RÉEL ========
    
    /**
     * Met à jour le prix total via AJAX avec tous les paramètres
     */
    function updateTotalPrice() {
        // Récupérer les valeurs actuelles
        var product_id = $('#excursion_id').val();
        var participants = parseInt($('#participants').val()) || 1;
        var start_date = $('#start_date').val();
        var end_date = $('#end_date').val() || start_date;
        var start_time = $('#start_time').val() || '';
        var end_time = $('#end_time').val() || '';
        
        // Vérifier que les données minimales sont présentes
        if (!product_id || !participants || !start_date) {
            return;
        }
        
        // Collecter les extras sélectionnés
        var extras = {};
        $('.extras-section .extra-item input[type="checkbox"]').each(function(){
            var extraKey = $(this).data('key');
            if ($(this).is(':checked')) {
                var extraType = $(this).data('type').toLowerCase();
                if (extraType === 'quantite' || extraType === 'quantité') {
                    var qty = parseInt($(this).closest('.extra-item').find('.extra-quantity input.extra-quantity-input').val()) || 1;
                    extras[extraKey] = qty;
                } else {
                    extras[extraKey] = 1;
                }
            }
        });
        
        // Collecter les activités sélectionnées
        var activities = {};
        $('.activities-section .activity-item input[type="number"]').each(function(){
            var activityKeyMatch = $(this).attr('name').match(/(.*?)/);
            if(activityKeyMatch) {
                var activityKey = activityKeyMatch[1];
                var val = parseInt($(this).val()) || 0;
                if(val > 0) {
                    activities[activityKey] = val;
                }
            }
        });
        
        // Afficher l'indicateur de chargement
        showSpinner();
        
        // Effectuer la requête AJAX avec retry
        ajaxRequestWithRetry({
            url: life_travel_excursion_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'life_travel_excursion_calculate_price',
                security: life_travel_excursion_ajax.nonce,
                product_id: product_id,
                participants: participants,
                start_date: start_date,
                end_date: end_date,
                start_time: start_time,
                end_time: end_time,
                extras: extras,
                activities: activities
            },
            success: function(response) {
                hideSpinner();
                
                if (response.success) {
                    // Mise à jour des sections de prix
                    $('#total_price').html(response.data.total_price_html);
                    $('#base_price').html(response.data.base_price_html);
                    
                    // Mise à jour des extras
                    if(response.data.extras_price_html) {
                        $('#extras_price').html(response.data.extras_price_html);
                        $('.extras-price-container').show();
                    } else {
                        $('.extras-price-container').hide();
                    }
                    
                    // Mise à jour des activités
                    if(response.data.activities_price_html) {
                        $('#activities_price').html(response.data.activities_price_html);
                        $('.activities-price-container').show();
                    } else {
                        $('.activities-price-container').hide();
                    }
                    
                    // Informations supplémentaires
                    if(response.data.additional_cost_html && parseFloat(response.data.additional_cost_html.replace(/[^0-9.]/g, '')) > 0) {
                        $('#additional_units_info').text("Unité additionnelle déclenchée.").show();
                    } else {
                        $('#additional_units_info').hide();
                    }
                    
                    // Supprimer toute erreur affichée précédemment
                    $('#booking_error').hide();
                } else {
                    displayError(response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                hideSpinner();
                console.error("Erreur AJAX (Calcul Prix):", textStatus, errorThrown, jqXHR.responseText);
                displayError("Une erreur de communication est survenue. Veuillez réessayer plus tard.");
            }
        }, 2, 3000);
    }
    
    // ======== AJOUT AU PANIER ========
    
    // Gestion du clic sur le bouton d'ajout au panier
    $('#add-to-cart-button').on('click', function(){
        $(this).prop('disabled', true).text('Ajout en cours...');
        
        // Récupération de toutes les données
        var product_id = $('#excursion_id').val();
        var participants = parseInt($('#participants').val()) || 1;
        var start_date = $('#start_date').val();
        var end_date = $('#end_date').val() || start_date;
        var start_time = $('#start_time').val() || '';
        var end_time = $('#end_time').val() || '';
        
        // Collecte des extras
        var extras = {};
        $('.extras-section .extra-item input[type="checkbox"]').each(function(){
            var extraKey = $(this).data('key');
            if ($(this).is(':checked')) {
                var extraType = $(this).data('type').toLowerCase();
                if (extraType === 'quantite' || extraType === 'quantité') {
                    var qty = parseInt($(this).closest('.extra-item').find('.extra-quantity input.extra-quantity-input').val()) || 1;
                    extras[extraKey] = qty;
                } else {
                    extras[extraKey] = 1;
                }
            }
        });
        
        // Collecte des activités
        var activities = {};
        $('.activities-section .activity-item input[type="number"]').each(function(){
            var activityKeyMatch = $(this).attr('name').match(/(.*?)/);
            if(activityKeyMatch) {
                var activityKey = activityKeyMatch[1];
                var val = parseInt($(this).val()) || 0;
                if(val > 0) {
                    activities[activityKey] = val;
                }
            }
        });
        
        // Requête AJAX avec retry pour l'ajout au panier
        ajaxRequestWithRetry({
            url: life_travel_excursion_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'life_travel_excursion_add_to_cart',
                security: life_travel_excursion_ajax.nonce,
                product_id: product_id,
                participants: participants,
                start_date: start_date,
                end_date: end_date,
                start_time: start_time,
                end_time: end_time,
                extras: extras,
                activities: activities
            },
            success: function(response) {
                if (response.success) {
                    // Réinitialiser le formulaire et le cache local
                    alert(response.data.message);
                    localStorage.removeItem(storageKey);
                    
                    // Redirection vers le panier si spécifié
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                        return;
                    }
                } else {
                    displayError(response.data.message);
                }
                $('#add-to-cart-button').prop('disabled', false).text('Ajouter au panier');
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Erreur AJAX (Ajout au Panier):", textStatus, errorThrown, jqXHR.responseText);
                displayError("Une erreur est survenue lors de l'ajout au panier. Veuillez réessayer plus tard.");
                $('#add-to-cart-button').prop('disabled', false).text('Ajouter au panier');
            }
        }, 2, 3000);
    });
});
