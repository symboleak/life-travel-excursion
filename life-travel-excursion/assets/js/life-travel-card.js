/**
 * Life Travel Card Payment JS
 * Gestion de la passerelle de paiement par carte
 */

(function($) {
    'use strict';

    // Initialisation une fois le DOM chargé
    $(document).ready(function() {
        if ($('#life-travel-card-form').length === 0) {
            return;
        }

        // Éléments du formulaire
        const cardForm = $('#life-travel-card-form');
        const cardNumber = $('#life_travel_card_number');
        const cardExpiry = $('#life_travel_card_expiry');
        const cardCvc = $('#life_travel_card_cvc');
        const cardHolder = $('#life_travel_card_holder');
        const errorContainer = $('.life-travel-card-errors');
        const cardTypeIcon = $('.card-type-icon');
        
        // Formatage du numéro de carte
        cardNumber.on('input', function(e) {
            let value = $(this).val().replace(/\D/g, '');
            let formattedValue = '';
            
            // Format selon le type de carte
            const cardType = getCardType(value);
            
            // Mettre à jour l'icône
            updateCardTypeIcon(cardType);
            
            // Format spécifique à American Express
            if (cardType === 'amex') {
                // Format 4-6-5
                for (let i = 0; i < value.length; i++) {
                    if (i === 4 || i === 10) {
                        formattedValue += ' ';
                    }
                    formattedValue += value[i];
                }
            } else {
                // Format standard 4-4-4-4
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) {
                        formattedValue += ' ';
                    }
                    formattedValue += value[i];
                }
            }
            
            $(this).val(formattedValue);
        });
        
        // Formatage de la date d'expiration
        cardExpiry.on('input', function(e) {
            let value = $(this).val().replace(/\D/g, '');
            let formattedValue = '';
            
            if (value.length > 0) {
                // Format MM / YY
                let month = value.substring(0, 2);
                let year = value.substring(2, 4);
                
                // Validation du mois
                if (month.length === 1) {
                    if (parseInt(month) > 1) {
                        month = '0' + month;
                    }
                } else if (month.length === 2) {
                    if (parseInt(month) > 12) {
                        month = '12';
                    }
                }
                
                formattedValue = month;
                
                if (value.length > 2) {
                    formattedValue += ' / ' + year;
                }
            }
            
            $(this).val(formattedValue);
        });
        
        // Formatage du CVC
        cardCvc.on('input', function(e) {
            let value = $(this).val().replace(/\D/g, '');
            $(this).val(value);
        });
        
        // Validation avant soumission
        $('form.checkout').on('checkout_place_order_life_travel_card', function() {
            return validateCardForm();
        });
        
        // Validation du formulaire de carte
        function validateCardForm() {
            clearErrors();
            
            const number = cardNumber.val().replace(/\s/g, '');
            const expiry = cardExpiry.val();
            const cvc = cardCvc.val();
            const name = cardHolder.val();
            
            // Vérification basique
            if (!number || !expiry || !cvc || !name) {
                showError('Veuillez remplir tous les champs obligatoires.');
                return false;
            }
            
            // Validation du numéro de carte (algorithme de Luhn)
            if (!validateCardNumber(number)) {
                showError('Le numéro de carte n\'est pas valide.');
                return false;
            }
            
            // Validation de la date d'expiration
            if (!validateExpiry(expiry)) {
                showError('La date d\'expiration n\'est pas valide ou est expirée.');
                return false;
            }
            
            // Validation du CVC
            if (!validateCVC(cvc)) {
                showError('Le code de sécurité n\'est pas valide.');
                return false;
            }
            
            return true;
        }
        
        // Obtenir le type de carte à partir du numéro
        function getCardType(number) {
            // Patterns pour différents types de cartes
            const patterns = {
                visa: /^4/,
                mastercard: /^5[1-5]/,
                amex: /^3[47]/,
                discover: /^(6011|65|64[4-9]|622)/,
                diners: /^(36|38|30[0-5])/,
                jcb: /^35/
            };
            
            for (const type in patterns) {
                if (patterns[type].test(number)) {
                    return type;
                }
            }
            
            return 'unknown';
        }
        
        // Mettre à jour l'icône du type de carte
        function updateCardTypeIcon(type) {
            cardTypeIcon.removeClass();
            cardTypeIcon.addClass('card-type-icon');
            
            if (type !== 'unknown') {
                cardTypeIcon.addClass('card-type-' + type);
            }
        }
        
        // Validation Luhn pour les numéros de carte
        function validateCardNumber(number) {
            if (/[^0-9-\s]+/.test(number)) return false;
            
            let nCheck = 0, nDigit = 0, bEven = false;
            number = number.replace(/\D/g, "");
            
            for (let n = number.length - 1; n >= 0; n--) {
                const cDigit = number.charAt(n);
                nDigit = parseInt(cDigit, 10);
                
                if (bEven) {
                    if ((nDigit *= 2) > 9) nDigit -= 9;
                }
                
                nCheck += nDigit;
                bEven = !bEven;
            }
            
            return (nCheck % 10) === 0;
        }
        
        // Validation de la date d'expiration
        function validateExpiry(expiry) {
            const parts = expiry.split(' / ');
            
            if (parts.length !== 2) {
                return false;
            }
            
            const month = parseInt(parts[0], 10);
            const year = parseInt('20' + parts[1], 10);
            
            if (isNaN(month) || isNaN(year)) {
                return false;
            }
            
            if (month < 1 || month > 12) {
                return false;
            }
            
            const now = new Date();
            const currentYear = now.getFullYear();
            const currentMonth = now.getMonth() + 1;
            
            // Vérifie si la carte n'est pas expirée
            return (year > currentYear || (year === currentYear && month >= currentMonth));
        }
        
        // Validation du CVC
        function validateCVC(cvc) {
            return /^[0-9]{3,4}$/.test(cvc);
        }
        
        // Afficher une erreur
        function showError(message) {
            errorContainer.html(message);
            errorContainer.addClass('visible');
            
            // Animation de scroll vers l'erreur
            $('html, body').animate({
                scrollTop: errorContainer.offset().top - 100
            }, 500);
        }
        
        // Effacer les erreurs
        function clearErrors() {
            errorContainer.html('');
            errorContainer.removeClass('visible');
        }
        
        // En mode test, remplir des données de test
        if (life_travel_card_params && life_travel_card_params.test_mode) {
            $('#life-travel-card-form .card-test-data').on('click', function(e) {
                e.preventDefault();
                
                const testType = $(this).data('type');
                
                if (testType === 'visa') {
                    cardNumber.val('4242 4242 4242 4242');
                    updateCardTypeIcon('visa');
                } else if (testType === 'mastercard') {
                    cardNumber.val('5555 5555 5555 4444');
                    updateCardTypeIcon('mastercard');
                }
                
                const now = new Date();
                const month = (now.getMonth() + 1).toString().padStart(2, '0');
                const year = (now.getFullYear() + 1).toString().substr(-2);
                
                cardExpiry.val(month + ' / ' + year);
                cardCvc.val('123');
                cardHolder.val('Test User');
            });
        }
    });
})(jQuery);
