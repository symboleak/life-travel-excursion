/**
 * Script de gestion du calendrier de réservation pour Life Travel Excursion
 * 
 * Ce script gère l'initialisation et la configuration du calendrier de réservation
 * en utilisant les paramètres définis dans l'administration.
 * 
 * Implémenté avec la même robustesse que sync_abandoned_cart
 */

(function($) {
    'use strict';
    
    // Options de configuration globales
    var config = {};
    
    // Élements DOM
    var $startDateInput = null;
    var $endDateInput = null;
    var $participantsInput = null;
    var $excursionType = null;
    var $productId = null;
    
    // Dates sélectionnées
    var selectedStartDate = null;
    var selectedEndDate = null;
    
    /**
     * Initialisation du calendrier
     */
    function init() {
        // Vérifier que les éléments du DOM existent
        $startDateInput = $('#start_date');
        $endDateInput = $('#end_date');
        $participantsInput = $('#participants');
        $productId = $('#excursion_id');
        
        if (!$startDateInput.length || !$productId.length) {
            console.log('Calendar elements not found, stopping initialization');
            return;
        }
        
        // Récupérer le type d'excursion (privée ou groupe)
        $excursionType = $('input[name="excursion_type"]').val() || 'group';
        
        // Initialiser la configuration avec les valeurs par défaut
        initConfig();
        
        // Initialiser les calendriers après la configuration
        initDatepickers();
        
        // Gérer les changements de participants qui peuvent affecter la disponibilité
        $participantsInput.on('change', function() {
            refreshAvailableDates();
        });
    }
    
    /**
     * Initialiser la configuration en utilisant les données fournies par WordPress
     */
    function initConfig() {
        // Valeurs par défaut
        config = {
            bookingWindowDays: 180,
            leadTimeHours: 24,
            availableDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            excludedDates: [],
            startTime: '08:00',
            endTime: '18:00',
            minParticipants: 1,
            maxParticipants: 15
        };
        
        // Si lifeTravel (script localisé) existe, récupérer les configurations
        if (typeof lifeTravel !== 'undefined' && lifeTravel.availability) {
            config.bookingWindowDays = lifeTravel.availability.booking_window_days || config.bookingWindowDays;
            config.availableDays = lifeTravel.availability.default_availability || config.availableDays;
            config.excludedDates = lifeTravel.availability.excluded_dates || config.excludedDates;
            config.startTime = lifeTravel.availability.daily_start_time || config.startTime;
            config.endTime = lifeTravel.availability.daily_end_time || config.endTime;
            
            // Définir le délai minimum selon le type d'excursion
            if ($excursionType === 'private' && lifeTravel.availability.private_booking_lead_time) {
                config.leadTimeHours = lifeTravel.availability.private_booking_lead_time;
            } else if ($excursionType === 'group' && lifeTravel.availability.group_booking_lead_time) {
                config.leadTimeHours = lifeTravel.availability.group_booking_lead_time;
            }
        }
        
        // Récupérer les configurations spécifiques à cette excursion (data attributes)
        if ($startDateInput.data('min-participants')) {
            config.minParticipants = parseInt($startDateInput.data('min-participants'), 10);
        }
        
        if ($startDateInput.data('max-participants')) {
            config.maxParticipants = parseInt($startDateInput.data('max-participants'), 10);
        }
        
        if ($startDateInput.data('lead-time')) {
            config.leadTimeHours = parseInt($startDateInput.data('lead-time'), 10);
        }
        
        if ($startDateInput.data('booking-window')) {
            config.bookingWindowDays = parseInt($startDateInput.data('booking-window'), 10);
        }
        
        // Calculer les dates min et max pour le calendrier
        var today = new Date();
        config.minDate = new Date(today.getTime() + (config.leadTimeHours * 60 * 60 * 1000));
        config.maxDate = new Date(today.getTime() + (config.bookingWindowDays * 24 * 60 * 60 * 1000));
        
        // Limites de participants
        $participantsInput.attr('min', config.minParticipants);
        $participantsInput.attr('max', config.maxParticipants);
        
        // Si la valeur actuelle est en dehors des limites, ajuster
        var currentParticipants = parseInt($participantsInput.val(), 10);
        if (currentParticipants < config.minParticipants) {
            $participantsInput.val(config.minParticipants);
        } else if (currentParticipants > config.maxParticipants) {
            $participantsInput.val(config.maxParticipants);
        }
    }
    
    /**
     * Initialiser les sélecteurs de date
     */
    function initDatepickers() {
        // Options de base pour les datepickers
        var datepickerOptions = {
            dateFormat: 'yy-mm-dd',
            minDate: config.minDate,
            maxDate: config.maxDate,
            changeMonth: true,
            changeYear: true,
            showButtonPanel: true,
            beforeShowDay: isDateAvailable,
            onSelect: function(dateText, inst) {
                // Logique de sélection de dates
                handleDateSelection(dateText, inst.id);
            }
        };
        
        // Configuration spécifique pour la date de début
        var startDateOptions = $.extend({}, datepickerOptions, {
            onClose: function(selectedDate) {
                // Si une date de début est sélectionnée, mettre à jour la date minimale pour la date de fin
                if (selectedDate) {
                    $endDateInput.datepicker('option', 'minDate', selectedDate);
                }
            }
        });
        
        // Configuration spécifique pour la date de fin
        var endDateOptions = $.extend({}, datepickerOptions, {
            onClose: function(selectedDate) {
                // Si une date de fin est sélectionnée, mettre à jour la date maximale pour la date de début
                if (selectedDate) {
                    $startDateInput.datepicker('option', 'maxDate', selectedDate);
                }
            }
        });
        
        // Initialiser les datepickers
        $startDateInput.datepicker(startDateOptions);
        if ($endDateInput.length) {
            $endDateInput.datepicker(endDateOptions);
        }
        
        // Initialiser les valeurs par défaut
        if (!$startDateInput.val()) {
            // Trouver la première date disponible
            var firstAvailableDate = findFirstAvailableDate();
            if (firstAvailableDate) {
                $startDateInput.datepicker('setDate', firstAvailableDate);
                if ($endDateInput.length) {
                    // Pour les excursions de plusieurs jours, définir une date de fin par défaut (jour suivant)
                    var defaultEndDate = new Date(firstAvailableDate.getTime() + (24 * 60 * 60 * 1000));
                    $endDateInput.datepicker('setDate', defaultEndDate);
                }
            }
        }
    }
    
    /**
     * Vérifier si une date est disponible pour la réservation
     * 
     * @param {Date} date Date à vérifier
     * @return {Array} [disponible, classe CSS, tooltip]
     */
    function isDateAvailable(date) {
        // Convertir la date en format YYYY-MM-DD pour vérification
        var dateString = $.datepicker.formatDate('yy-mm-dd', date);
        
        // Vérifier si la date est dans les dates exclues
        if ($.inArray(dateString, config.excludedDates) !== -1) {
            return [false, 'date-excluded', 'Cette date n\'est pas disponible'];
        }
        
        // Vérifier si le jour de la semaine est disponible
        var dayOfWeek = date.toLocaleDateString('en-US', { weekday: 'lowercase' });
        if ($.inArray(dayOfWeek, config.availableDays) === -1) {
            return [false, 'day-unavailable', 'Ce jour de la semaine n\'est pas disponible'];
        }
        
        // Vérifier la disponibilité spécifique à l'excursion via AJAX
        var participants = parseInt($participantsInput.val(), 10) || 1;
        var excursionId = $productId.val();
        
        // Créer une clé de cache unique pour cette vérification
        var cacheKey = excursionId + '|' + dateString + '|' + participants;
        
        // Si nous avons déjà vérifié cette combinaison, utiliser le résultat en cache
        if (typeof window.availabilityCache === 'undefined') {
            window.availabilityCache = {};
        }
        
        if (window.availabilityCache[cacheKey] !== undefined) {
            return window.availabilityCache[cacheKey];
        }
        
        // Sinon, faire une vérification AJAX synchrone (pour les performances, on pourrait optimiser avec un chargement initial en bulk)
        var result = [true, '', ''];
        
        $.ajax({
            url: lifeTravel.ajax_url,
            type: 'POST',
            async: false, // Synchrone pour pouvoir retourner le résultat
            data: {
                'action': 'life_travel_check_date_availability',
                'date': dateString,
                'product_id': excursionId,
                'participants': participants,
                'nonce': lifeTravel.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Date disponible
                    result = [true, 'date-available', response.data.message || 'Disponible'];
                    
                    // Si stock limité, indiquer visuellement
                    if (response.data.stock_status === 'limited') {
                        result = [true, 'date-limited', response.data.message || 'Places limitées'];
                    }
                } else {
                    // Date non disponible
                    result = [false, 'date-unavailable', response.data.message || 'Non disponible'];
                }
                
                // Mettre en cache le résultat
                window.availabilityCache[cacheKey] = result;
            },
            error: function() {
                // En cas d'erreur, considérer la date comme non disponible
                result = [false, 'date-error', 'Erreur lors de la vérification'];
                
                // Ne pas mettre en cache les erreurs pour permettre de réessayer
            }
        });
        
        return result;
    }
    
    /**
     * Trouver la première date disponible après le délai minimum
     * 
     * @return {Date|null} La première date disponible ou null si aucune n'est trouvée
     */
    function findFirstAvailableDate() {
        var date = new Date(config.minDate);
        var maxDate = new Date(config.maxDate);
        var found = false;
        var safetyCounter = 0; // Éviter les boucles infinies
        
        // Chercher jusqu'à 30 jours après la date minimale, ou jusqu'à la date maximale
        while (!found && date <= maxDate && safetyCounter < 30) {
            var availability = isDateAvailable(date);
            if (availability[0] === true) {
                found = true;
                break;
            }
            
            // Passer au jour suivant
            date.setDate(date.getDate() + 1);
            safetyCounter++;
        }
        
        return found ? date : null;
    }
    
    /**
     * Gérer la sélection d'une date et mettre à jour les champs de temps si nécessaire
     * 
     * @param {String} dateText Date sélectionnée (format YYYY-MM-DD)
     * @param {String} inputId ID du champ de saisie
     */
    function handleDateSelection(dateText, inputId) {
        // Mettre à jour les variables de dates sélectionnées
        if (inputId === 'start_date') {
            selectedStartDate = new Date(dateText);
            
            // Mettre à jour les champs d'heure de début par défaut
            var $startTimeInput = $('#start_time');
            if ($startTimeInput.length && !$startTimeInput.val()) {
                $startTimeInput.val(config.startTime);
            }
        } else if (inputId === 'end_date') {
            selectedEndDate = new Date(dateText);
            
            // Mettre à jour les champs d'heure de fin par défaut
            var $endTimeInput = $('#end_time');
            if ($endTimeInput.length && !$endTimeInput.val()) {
                $endTimeInput.val(config.endTime);
            }
        }
        
        // Déclencher un événement pour mettre à jour les prix, etc.
        $(document).trigger('life_travel_date_selected', [dateText, inputId]);
    }
    
    /**
     * Rafraîchir les dates disponibles dans le calendrier (par exemple après changement du nombre de participants)
     */
    function refreshAvailableDates() {
        // Vider le cache de disponibilité
        window.availabilityCache = {};
        
        // Actualiser les datepickers
        $startDateInput.datepicker('refresh');
        if ($endDateInput.length) {
            $endDateInput.datepicker('refresh');
        }
    }
    
    /**
     * Registre d'événements AJAX pour le calendrier
     */
    function registerAjaxHandlers() {
        if (typeof lifeTravel === 'undefined') {
            console.error('lifeTravel object not defined, cannot register AJAX handlers');
            return;
        }
        
        // Handler pour vérifier la disponibilité d'une date
        $(document).on('life_travel_check_availability', function(e, date, callback) {
            var participants = parseInt($participantsInput.val(), 10) || 1;
            var excursionId = $productId.val();
            
            $.ajax({
                url: lifeTravel.ajax_url,
                type: 'POST',
                data: {
                    'action': 'life_travel_check_date_availability',
                    'date': date,
                    'product_id': excursionId,
                    'participants': participants,
                    'nonce': lifeTravel.nonce
                },
                success: function(response) {
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                }
            });
        });
    }
    
    // Initialiser quand le document est prêt
    $(document).ready(function() {
        init();
        registerAjaxHandlers();
    });
    
})(jQuery);
