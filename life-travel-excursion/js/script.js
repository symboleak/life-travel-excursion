jQuery(document).ready(function($) {
    // Fonction debounce pour limiter la fréquence des appels
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

    // Fonction générique d'AJAX avec mécanisme de retry
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

    // Gestion du cache local du formulaire (1 heure)
    var storageExpiration = 3600000;
    var storageKey = 'life_travel_reservation_data';

    // Sauvegarde de l'état du formulaire dans le localStorage
    function saveFormState() {
        var state = {
            participants: $('#participants').val(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            extras: {},
            activities: {},
            timestamp: Date.now()
        };
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
        $('.activities-section .activity-item input[type="number"]').each(function(){
            var activityKeyMatch = $(this).attr('name').match(/(.*?)/);
            if(activityKeyMatch) {
                var activityKey = activityKeyMatch[1];
                state.activities[activityKey] = $(this).val();
            }
        });
        localStorage.setItem(storageKey, JSON.stringify(state));
    }

    // Chargement de l'état du formulaire depuis le localStorage
    function loadFormState() {
        var stateStr = localStorage.getItem(storageKey);
        if (stateStr) {
            var state = JSON.parse(stateStr);
            if (Date.now() - state.timestamp > storageExpiration) {
                localStorage.removeItem(storageKey);
                return;
            }
            if (state.participants) $('#participants').val(state.participants);
            if (state.start_date) $('#start_date').val(state.start_date);
            if (state.end_date) $('#end_date').val(state.end_date);
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
            $('.activities-section .activity-item input[type="number"]').each(function(){
                var activityKeyMatch = $(this).attr('name').match(/(.*?)/);
                if(activityKeyMatch && state.activities && state.activities[activityKeyMatch[1]] !== undefined) {
                    $(this).val(state.activities[activityKeyMatch[1]]);
                }
            });
        }
    }

    loadFormState();

    // Affichage des spinners et zones d'erreur
    function showSpinner() {
        if ($('#price_spinner').length === 0) {
            $('.pricing-section').append('<div id="price_spinner" style="text-align:center; margin-top:10px;"><svg width="24" height="24" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke="#0073aa" stroke-width="5" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/></circle></svg></div>');
        } else {
            $('#price_spinner').show();
        }
    }

    function hideSpinner() {
        $('#price_spinner').hide();
    }

    function displayError(message) {
        $('#booking_error').text(message).show();
        // Optionnel : cacher l'erreur après quelques secondes
        setTimeout(function() {
            $('#booking_error').fadeOut();
        }, 5000);
    }

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
                var dateString = $.datepicker.formatDate('yy-mm-dd', date);
                var isUnavailable = life_travel_excursion_ajax.unavailable_dates.includes(dateString);
                return (date < dateToday || isUnavailable) ? [false, 'unavailable-date', 'Indisponible'] : [true, '', 'Disponible'];
            },
            onSelect: function(selectedDate) {
                var startDate = $(this).datepicker('getDate');
                $('#end_date').datepicker('option', 'minDate', startDate);
                saveFormState();
                debouncedUpdateTotalPrice();
            }
        });
        $('#end_date').datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: dateToday,
            beforeShowDay: function(date) {
                var startDate = $('#start_date').datepicker('getDate');
                var dateString = $.datepicker.formatDate('yy-mm-dd', date);
                var isUnavailable = life_travel_excursion_ajax.unavailable_dates.includes(dateString);
                return (!startDate || date < startDate || isUnavailable) ? [false, 'unavailable-date', 'Indisponible'] : [true, '', 'Disponible'];
            },
            onSelect: function(selectedDate) {
                saveFormState();
                debouncedUpdateTotalPrice();
            }
        });
    }

    // Gestion des extras
    $('.extras-section').on('change', 'input[type="checkbox"]', function(){
        var extraItem = $(this).closest('.extra-item');
        var extraType = $(this).data('type').toLowerCase();
        if ($(this).is(':checked') && (extraType === 'quantite' || extraType === 'quantité')) {
            extraItem.find('.extra-quantity').slideDown();
        } else {
            extraItem.find('.extra-quantity').slideUp();
            extraItem.find('.extra-quantity input').val('1');
        }
        saveFormState();
        debouncedUpdateTotalPrice();
    });
    $('.extras-section').on('input', '.extra-quantity-input', function(){
        saveFormState();
        debouncedUpdateTotalPrice();
    });

    // Gestion des activités
    $('.activities-section').on('input', 'input[type="number"]', function(){
        saveFormState();
        debouncedUpdateTotalPrice();
    });

    // Mise à jour sur modification des champs principaux
    $('#participants, #start_date, #end_date, #start_time, #end_time').on('change', function(){
        saveFormState();
        debouncedUpdateTotalPrice();
    });

    function updateTotalPrice() {
        showSpinner();
        var product_id = $('#excursion_id').val();
        var participants = parseInt($('#participants').val()) || 1;
        var start_date = $('#start_date').val();
        var end_date = $('#end_date').val();
        var start_time = $('#start_time').val() || '';
        var end_time = $('#end_time').val() || '';
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
        var activities = {};
        $('.activities-section .activity-item input[type="number"]').each(function(){
            var activityKeyMatch = $(this).attr('name').match(/(.*?)/);
            if(activityKeyMatch) {
                var activityKey = activityKeyMatch[1];
                var val = parseInt($(this).val()) || 0;
                if(val > 0) {
                    activities[activityKey] = val;
                }
            }
        });
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
                    $('#price_per_person').html(response.data.price_per_person_html);
                    $('#extras_price').html(response.data.extras_price_html);
                    $('#activities_price').html(response.data.activities_price_html);
                    $('#total_price').html(response.data.total_price_html);
                    $('#participant_count').text(response.data.participants);
                    $('#day_count').text(response.data.days);
                    // Affichage de l'indicateur d'unités supplémentaires si applicable
                    if(response.data.additional_cost_html && parseFloat(response.data.additional_cost_html.replace(/[^0-9.]/g, '')) > 0) {
                        $('#additional_units_info').text("Unité additionnelle déclenchée.").show();
                    } else {
                        $('#additional_units_info').hide();
                    }
                    // Supprimer toute erreur affichée précédemment
                    $('#booking_error').hide();
                } else {
                    hideSpinner();
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

    $('#add-to-cart-button').on('click', function(){
        $(this).prop('disabled', true).text('Ajout en cours...');
        var product_id = $('#excursion_id').val();
        var participants = parseInt($('#participants').val()) || 1;
        var start_date = $('#start_date').val();
        var end_date = $('#end_date').val();
        var start_time = $('#start_time').val() || '';
        var end_time = $('#end_time').val() || '';
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
        var activities = {};
        $('.activities-section .activity-item input[type="number"]').each(function(){
            var activityKeyMatch = $(this).attr('name').match(/(.*?)/);
            if(activityKeyMatch) {
                var activityKey = activityKeyMatch[1];
                var val = parseInt($(this).val()) || 0;
                if(val > 0) {
                    activities[activityKey] = val;
                }
            }
        });
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