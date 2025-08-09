/**
 * Script d'amélioration du checkout
 * 
 * @package Life_Travel_Excursion
 */
(function($) {
    'use strict';
    
    // Objet principal pour la gestion du checkout
    var LTECheckout = {
        /**
         * Initialisation
         */
        init: function() {
            // Gestion des étapes du checkout
            this.setupCheckoutSteps();
            
            // Validation des champs
            this.setupFieldValidation();
            
            // Sauvegarde automatique du panier
            this.setupCartAutoSave();
            
            // Gestion des paniers abandonnés
            this.setupAbandonedCart();
        },
        
        /**
         * Configure les étapes visuelles du checkout
         */
        setupCheckoutSteps: function() {
            var $steps = $('.lte-checkout-steps .step');
            
            // Mise à jour des étapes selon la section active
            function updateSteps() {
                var currentStep = 'information';
                
                // Détecter l'étape active basée sur la position de défilement
                if ($('#order_review').visible()) {
                    currentStep = 'payment';
                } else if ($('.excursion-fields').visible()) {
                    currentStep = 'excursion';
                }
                
                // Mettre à jour les classes des étapes
                $steps.removeClass('active');
                $steps.each(function() {
                    var $step = $(this);
                    var stepName = $step.data('step');
                    
                    if (stepName === currentStep) {
                        $step.addClass('active');
                    } else if (getStepIndex(stepName) < getStepIndex(currentStep)) {
                        $step.addClass('completed');
                    }
                });
            }
            
            // Obtenir l'index d'une étape
            function getStepIndex(stepName) {
                var steps = ['information', 'excursion', 'payment', 'confirmation'];
                return steps.indexOf(stepName);
            }
            
            // Vérifier si un élément est visible à l'écran
            $.fn.visible = function() {
                var $el = $(this);
                var $window = $(window);
                
                var viewportTop = $window.scrollTop();
                var viewportBottom = viewportTop + $window.height();
                var $elTop = $el.offset().top;
                var $elBottom = $elTop + $el.height();
                
                return ($elBottom >= viewportTop && $elTop <= viewportBottom);
            };
            
            // Mettre à jour les étapes lors du défilement
            $(window).on('scroll', updateSteps);
            
            // Initialiser les étapes
            updateSteps();
            
            // Navigation par clic sur les étapes
            $steps.on('click', function() {
                var $step = $(this);
                var stepName = $step.data('step');
                var $target;
                
                switch (stepName) {
                    case 'information':
                        $target = $('.woocommerce-billing-fields');
                        break;
                    case 'excursion':
                        $target = $('.excursion-fields');
                        break;
                    case 'payment':
                        $target = $('#order_review');
                        break;
                    case 'confirmation':
                        return; // Ne rien faire sur l'étape de confirmation
                }
                
                if ($target && $target.length) {
                    $('html, body').animate({
                        scrollTop: $target.offset().top - 50
                    }, 500);
                }
            });
        },
        
        /**
         * Configure la validation des champs du formulaire
         */
        setupFieldValidation: function() {
            // Valider un champ quand on quitte le focus
            $('form.checkout').on('blur', 'input, select, textarea', function() {
                var $field = $(this);
                LTECheckout.validateField($field);
            });
            
            // Validation de l'email avec un format correct
            $('form.checkout').on('blur', '#billing_email', function() {
                var $field = $(this);
                var email = $field.val();
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && !emailRegex.test(email)) {
                    LTECheckout.showFieldError($field, lteCheckout.i18n.invalid_email);
                }
            });
            
            // Validation du téléphone avec un format correct pour le Cameroun
            $('form.checkout').on('blur', '#billing_phone', function() {
                var $field = $(this);
                var phone = $field.val();
                var phoneRegex = /^(\+237|237)?[6-9][0-9]{8}$/;
                
                if (phone && !phoneRegex.test(phone)) {
                    LTECheckout.showFieldError($field, lteCheckout.i18n.invalid_phone);
                }
            });
            
            // Validation de la date d'excursion
            $('form.checkout').on('blur', '#excursion_date', function() {
                var $field = $(this);
                var date = $field.val();
                
                if (date) {
                    var selectedDate = new Date(date);
                    var tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    tomorrow.setHours(0, 0, 0, 0);
                    
                    if (selectedDate < tomorrow) {
                        LTECheckout.showFieldError($field, lteCheckout.i18n.future_date_required);
                    }
                }
            });
        },
        
        /**
         * Valide un champ individuel
         */
        validateField: function($field) {
            var value = $field.val();
            var isRequired = $field.prop('required');
            
            // Vérifier si le champ est vide mais requis
            if (isRequired && !value) {
                this.showFieldError($field, lteCheckout.i18n.required_field);
                return false;
            }
            
            // Nettoyer les erreurs si le champ est valide
            this.clearFieldError($field);
            return true;
        },
        
        /**
         * Affiche une erreur pour un champ
         */
        showFieldError: function($field, message) {
            // Ajouter la classe d'erreur
            $field.addClass('input-error');
            
            // Supprimer toute erreur existante
            var $error = $field.next('.field-error');
            if ($error.length) {
                $error.remove();
            }
            
            // Ajouter le message d'erreur
            $field.after('<span class="field-error">' + message + '</span>');
        },
        
        /**
         * Supprime les erreurs d'un champ
         */
        clearFieldError: function($field) {
            $field.removeClass('input-error');
            $field.next('.field-error').remove();
        },
        
        /**
         * Configure la sauvegarde automatique du panier
         */
        setupCartAutoSave: function() {
            // Sauvegarder le panier périodiquement
            var saveInterval = setInterval(function() {
                LTECheckout.saveCartProgress();
            }, lteCheckout.save_cart_interval || 30000);
            
            // Sauvegarder le panier quand la page est cachée (changement d'onglet, etc.)
            $(window).on('beforeunload', function() {
                LTECheckout.saveCartProgress();
            });
            
            // Sauvegarder le panier quand des champs importants sont modifiés
            var $importantFields = $('.woocommerce-billing-fields input, #order_review input, .excursion-fields input');
            
            $importantFields.on('change', function() {
                // Utiliser un délai pour éviter trop d'appels API
                clearTimeout(LTECheckout.saveTimer);
                LTECheckout.saveTimer = setTimeout(function() {
                    LTECheckout.saveCartProgress();
                }, 2000);
            });
        },
        
        /**
         * Sauvegarde la progression du panier
         */
        saveCartProgress: function() {
            // Ne sauvegarder que si le formulaire contient des données
            if ($('form.checkout').length === 0 || $('.woocommerce-billing-fields input:first').val() === '') {
                return;
            }
            
            // Collecter les données du formulaire
            var formData = $('form.checkout').serialize();
            
            // Envoyer les données au serveur
            $.ajax({
                url: lteCheckout.ajax_url,
                type: 'POST',
                data: {
                    action: 'lte_save_cart_progress',
                    nonce: lteCheckout.nonce,
                    form_data: formData
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Progression du panier sauvegardée');
                    }
                }
            });
        },
        
        /**
         * Configure la gestion des paniers abandonnés
         */
        setupAbandonedCart: function() {
            // Gérer la fermeture de la notification
            $(document).on('click', '.lte-abandoned-cart-notice .close', function() {
                $(this).closest('.lte-abandoned-cart-notice').fadeOut(300, function() {
                    $(this).remove();
                });
                
                // Stocker dans sessionStorage pour ne pas réafficher
                sessionStorage.setItem('lte_abandoned_cart_dismissed', '1');
            });
            
            // Gérer le clic sur le bouton de restauration
            $(document).on('click', '.lte-restore-cart-button', function(e) {
                e.preventDefault();
                
                // Afficher un loader
                var $button = $(this);
                $button.prop('disabled', true).text(lteCheckout.i18n.loading);
                
                // Appeler l'API pour restaurer le panier
                $.ajax({
                    url: lteCheckout.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'lte_restore_abandoned_cart',
                        nonce: lteCheckout.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Recharger la page pour afficher le panier restauré
                            window.location.reload();
                        } else {
                            $button.prop('disabled', false).text(lteCheckout.i18n.try_again);
                            alert(response.data.message || lteCheckout.i18n.error_restoring);
                        }
                    },
                    error: function() {
                        $button.prop('disabled', false).text(lteCheckout.i18n.try_again);
                        alert(lteCheckout.i18n.network_error);
                    }
                });
            });
        }
    };
    
    // Initialiser quand le DOM est prêt
    $(document).ready(function() {
        LTECheckout.init();
    });
    
})(jQuery);
