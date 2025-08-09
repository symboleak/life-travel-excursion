/**
 * Gestionnaire de formulaire amélioré pour Life Travel Excursion
 * Optimisé pour les connexions intermittentes et l'expérience utilisateur
 * @version 2.3.4
 */

(function($) {
    'use strict';
    
    // Configuration du gestionnaire de formulaire
    const FormHandler = {
        // Options configurables
        options: {
            formSelector: '.life-travel-excursion-form',
            submitSelector: '.excursion-submit',
            bookingFormSelector: '#booking-form',
            loaderClass: 'form-loading',
            errorClass: 'form-error',
            successClass: 'form-success',
            fieldErrorClass: 'field-error',
            offlineClass: 'form-offline',
            savingClass: 'form-saving',
            autosaveInterval: 30000, // 30 secondes
        },
        
        // État du formulaire
        state: {
            isOffline: !navigator.onLine,
            formData: null,
            lastSaved: null,
            formModified: false,
            currentSubmission: null,
            submissionQueue: []
        },
        
        /**
         * Initialisation du gestionnaire
         */
        init: function() {
            this.bindEvents();
            this.setupAutoSave();
            this.checkConnectionStatus();
            this.restoreFormData();
            
            // Afficher l'indicateur de connexion
            this.updateConnectionIndicator();
            
            console.log('Gestionnaire de formulaire Life Travel initialisé');
        },
        
        /**
         * Association des événements
         */
        bindEvents: function() {
            const self = this;
            const options = this.options;
            
            // Détection des changements de connexion
            window.addEventListener('online', function() {
                self.state.isOffline = false;
                self.updateConnectionIndicator();
                self.processPendingSubmissions();
            });
            
            window.addEventListener('offline', function() {
                self.state.isOffline = true;
                self.updateConnectionIndicator();
            });
            
            // Soumission du formulaire
            $(options.formSelector).on('submit', function(e) {
                e.preventDefault();
                self.handleFormSubmit($(this));
            });
            
            // Détection des modifications du formulaire
            $(options.formSelector + ' :input').on('change input', function() {
                self.state.formModified = true;
            });
            
            // Validation en temps réel des champs
            $(options.formSelector + ' input[required], ' + options.formSelector + ' select[required]').on('blur', function() {
                self.validateField($(this));
            });
            
            // Synchronisation avec le service worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', function(event) {
                    if (event.data && event.data.type === 'FORM_DATA_SYNCED') {
                        self.showMessage('Vos données ont été synchronisées avec succès.', 'success');
                    }
                });
            }
            
            // Confirmation avant de quitter la page si formulaire modifié
            window.addEventListener('beforeunload', function(e) {
                if (self.state.formModified && !self.state.currentSubmission) {
                    // Sauvegarder avant de quitter
                    self.saveFormData();
                    
                    // Message de confirmation standard
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });
        },
        
        /**
         * Configuration de l'enregistrement automatique
         */
        setupAutoSave: function() {
            const self = this;
            
            // Enregistrement périodique des données du formulaire
            setInterval(function() {
                if (self.state.formModified) {
                    self.saveFormData();
                }
            }, this.options.autosaveInterval);
        },
        
        /**
         * Vérification du statut de connexion
         */
        checkConnectionStatus: function() {
            const self = this;
            
            // Vérifier régulièrement l'état de la connexion avec le serveur
            function pingServer() {
                const timestamp = new Date().getTime();
                fetch('/wp-admin/admin-ajax.php?action=ping&t=' + timestamp, {
                    method: 'HEAD',
                    cache: 'no-store'
                })
                .then(function(response) {
                    if (response.ok) {
                        if (self.state.isOffline) {
                            self.state.isOffline = false;
                            self.updateConnectionIndicator();
                            self.processPendingSubmissions();
                        }
                    } else {
                        self.state.isOffline = true;
                        self.updateConnectionIndicator();
                    }
                })
                .catch(function() {
                    self.state.isOffline = true;
                    self.updateConnectionIndicator();
                });
            }
            
            // Vérifier toutes les 30 secondes
            setInterval(pingServer, 30000);
            
            // Vérifier immédiatement
            pingServer();
        },
        
        /**
         * Met à jour l'indicateur de statut de connexion
         */
        updateConnectionIndicator: function() {
            const $indicator = $('#connection-status');
            
            if (!$indicator.length) {
                return;
            }
            
            if (this.state.isOffline) {
                $indicator.removeClass('online').addClass('offline').text('Hors ligne');
            } else {
                $indicator.removeClass('offline').addClass('online').text('En ligne');
            }
        },
        
        /**
         * Gestion de la soumission du formulaire
         */
        handleFormSubmit: function($form) {
            const self = this;
            
            // Désactiver le bouton de soumission
            const $submitBtn = $form.find(this.options.submitSelector);
            const originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).text('Traitement en cours...');
            
            // Ajouter la classe de chargement
            $form.addClass(this.options.loaderClass);
            
            // Supprimer les messages d'erreur précédents
            this.clearFormMessages($form);
            
            // Valider le formulaire côté client
            if (!this.validateForm($form)) {
                $submitBtn.prop('disabled', false).text(originalText);
                $form.removeClass(this.options.loaderClass);
                self.showMessage('Veuillez corriger les erreurs du formulaire avant de continuer.', 'error', $form);
                return;
            }
            
            // Sérialiser les données du formulaire
            const formData = this.serializeForm($form);
            
            // Si hors ligne, placer en file d'attente pour soumission ultérieure
            if (this.state.isOffline) {
                this.saveFormData();
                this.queueFormSubmission(formData);
                $submitBtn.prop('disabled', false).text(originalText);
                $form.removeClass(this.options.loaderClass).addClass(this.options.offlineClass);
                this.showMessage('Vous êtes actuellement hors ligne. Vos données ont été sauvegardées et seront envoyées dès que votre connexion sera rétablie.', 'info', $form);
                return;
            }
            
            // En ligne, soumettre normalement
            this.submitFormData(formData, $form, function(success, response) {
                $submitBtn.prop('disabled', false).text(originalText);
                $form.removeClass(self.options.loaderClass);
                
                if (success) {
                    // Réinitialiser l'état du formulaire
                    self.state.formModified = false;
                    self.clearSavedFormData();
                    
                    // Afficher le message de succès
                    self.showMessage(response.message || 'Opération réussie!', 'success', $form);
                    
                    // Redirection si nécessaire
                    if (response.redirect) {
                        setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 1000);
                    }
                } else {
                    // Afficher le message d'erreur
                    self.showMessage(response.message || 'Une erreur est survenue. Veuillez réessayer.', 'error', $form);
                    
                    // Gérer les erreurs de champ spécifiques
                    if (response.field_errors) {
                        self.handleFieldErrors(response.field_errors, $form);
                    }
                }
            });
        },
        
        /**
         * Soumet les données du formulaire au serveur
         */
        submitFormData: function(formData, $form, callback) {
            const self = this;
            
            // Stocker la soumission en cours
            this.state.currentSubmission = formData;
            
            // Ajouter le nonce AJAX sécurisé
            formData.security = lifeTravel.add_to_cart_nonce;
            
            // Requête AJAX avec timeout
            $.ajax({
                url: lifeTravel.ajax_url,
                type: 'POST',
                data: formData,
                timeout: 15000, // 15 secondes de timeout
                success: function(response) {
                    self.state.currentSubmission = null;
                    
                    if (response.success) {
                        callback(true, response.data);
                    } else {
                        callback(false, response.data || { message: "Erreur de serveur" });
                    }
                },
                error: function(xhr, status, error) {
                    self.state.currentSubmission = null;
                    
                    if (status === 'timeout') {
                        // Cas spécial pour les timeouts
                        self.queueFormSubmission(formData);
                        callback(false, { 
                            message: "La requête a expiré. Vos données ont été sauvegardées et seront soumises ultérieurement.",
                            timeout: true
                        });
                    } else if (!navigator.onLine) {
                        // Cas spécial pour la perte de connexion pendant la soumission
                        self.state.isOffline = true;
                        self.updateConnectionIndicator();
                        self.queueFormSubmission(formData);
                        callback(false, { 
                            message: "Connexion perdue. Vos données ont été sauvegardées et seront soumises ultérieurement.",
                            offline: true
                        });
                    } else {
                        // Autres erreurs
                        callback(false, { 
                            message: "Erreur de communication avec le serveur. Veuillez réessayer.",
                            error: error
                        });
                    }
                }
            });
        },
        
        /**
         * Validation complète du formulaire
         */
        validateForm: function($form) {
            let isValid = true;
            const self = this;
            
            // Valider chaque champ requis
            $form.find('input[required], select[required], textarea[required]').each(function() {
                if (!self.validateField($(this))) {
                    isValid = false;
                }
            });
            
            // Validation spécifique pour le formulaire d'excursion
            if ($form.attr('id') === 'booking-form') {
                // Vérification de la date
                const $dateField = $form.find('input[name="start_date"]');
                if ($dateField.length && $dateField.val()) {
                    const selectedDate = new Date($dateField.val());
                    const today = new Date();
                    
                    // La date doit être dans le futur
                    if (selectedDate < today) {
                        this.addFieldError($dateField, "Veuillez sélectionner une date future");
                        isValid = false;
                    }
                }
                
                // Vérification du nombre de participants
                const $participantsField = $form.find('input[name="participants"]');
                if ($participantsField.length && $participantsField.val()) {
                    const participants = parseInt($participantsField.val(), 10);
                    const minParticipants = parseInt($participantsField.data('min') || 1, 10);
                    const maxParticipants = parseInt($participantsField.data('max') || 20, 10);
                    
                    if (participants < minParticipants || participants > maxParticipants) {
                        this.addFieldError($participantsField, `Le nombre de participants doit être entre ${minParticipants} et ${maxParticipants}`);
                        isValid = false;
                    }
                }
            }
            
            return isValid;
        },
        
        /**
         * Validation d'un champ individuel
         */
        validateField: function($field) {
            // Supprimer les messages d'erreur précédents
            this.clearFieldError($field);
            
            // Obtenir la valeur du champ
            const value = $field.val();
            
            // Vérifier si le champ est vide (et requis)
            if ($field.prop('required') && !value) {
                this.addFieldError($field, "Ce champ est requis");
                return false;
            }
            
            // Validation d'email
            if ($field.attr('type') === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    this.addFieldError($field, "Veuillez entrer une adresse email valide");
                    return false;
                }
            }
            
            // Validation de numéro de téléphone
            if ($field.attr('type') === 'tel' && value) {
                const phoneRegex = /^\+?[0-9\s\-\(\)]{8,20}$/;
                if (!phoneRegex.test(value)) {
                    this.addFieldError($field, "Veuillez entrer un numéro de téléphone valide");
                    return false;
                }
            }
            
            // Validation de nombre
            if ($field.attr('type') === 'number' && value) {
                const min = parseFloat($field.attr('min'));
                const max = parseFloat($field.attr('max'));
                const num = parseFloat(value);
                
                if (isNaN(num)) {
                    this.addFieldError($field, "Veuillez entrer un nombre valide");
                    return false;
                }
                
                if (!isNaN(min) && num < min) {
                    this.addFieldError($field, `La valeur minimale est ${min}`);
                    return false;
                }
                
                if (!isNaN(max) && num > max) {
                    this.addFieldError($field, `La valeur maximale est ${max}`);
                    return false;
                }
            }
            
            // Si nous arrivons ici, le champ est valide
            return true;
        },
        
        /**
         * Ajoute un message d'erreur pour un champ
         */
        addFieldError: function($field, message) {
            // Supprimer d'abord toute erreur existante
            this.clearFieldError($field);
            
            // Ajouter la classe d'erreur
            $field.addClass(this.options.fieldErrorClass);
            
            // Créer et insérer le message d'erreur
            const $error = $('<div class="field-error-message">' + message + '</div>');
            $field.after($error);
        },
        
        /**
         * Supprime le message d'erreur d'un champ
         */
        clearFieldError: function($field) {
            $field.removeClass(this.options.fieldErrorClass);
            $field.next('.field-error-message').remove();
        },
        
        /**
         * Gère les erreurs de champ renvoyées par le serveur
         */
        handleFieldErrors: function(fieldErrors, $form) {
            const self = this;
            
            // Parcourir chaque erreur de champ
            $.each(fieldErrors, function(fieldName, errorMessage) {
                const $field = $form.find('[name="' + fieldName + '"]');
                if ($field.length) {
                    self.addFieldError($field, errorMessage);
                }
            });
            
            // Faire défiler jusqu'à la première erreur
            const $firstError = $form.find('.' + this.options.fieldErrorClass).first();
            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 500);
            }
        },
        
        /**
         * Supprime tous les messages du formulaire
         */
        clearFormMessages: function($form) {
            // Supprimer les classes d'état
            $form.removeClass(this.options.errorClass + ' ' + this.options.successClass);
            
            // Supprimer le conteneur de message
            $form.find('.form-message').remove();
            
            // Supprimer toutes les erreurs de champ
            $form.find('.' + this.options.fieldErrorClass).each(function() {
                $(this).removeClass(this.options.fieldErrorClass);
                $(this).next('.field-error-message').remove();
            });
        },
        
        /**
         * Affiche un message sur le formulaire
         */
        showMessage: function(message, type, $form) {
            // Type doit être 'error', 'success' ou 'info'
            const messageClass = type === 'error' ? this.options.errorClass :
                                type === 'success' ? this.options.successClass : 'form-info';
            
            // Si aucun formulaire n'est spécifié, utiliser le premier formulaire trouvé
            if (!$form) {
                $form = $(this.options.formSelector).first();
            }
            
            // Supprimer tout message existant
            $form.find('.form-message').remove();
            
            // Ajouter la classe appropriée au formulaire
            $form.removeClass(this.options.errorClass + ' ' + this.options.successClass + ' form-info')
                 .addClass(messageClass);
            
            // Créer et insérer le message
            const $message = $('<div class="form-message ' + messageClass + '">' + message + '</div>');
            $form.prepend($message);
            
            // Faire défiler jusqu'au message
            $('html, body').animate({
                scrollTop: $message.offset().top - 100
            }, 500);
            
            // Pour les messages de succès et d'info, masquer après un délai
            if (type === 'success' || type === 'info') {
                setTimeout(function() {
                    $message.fadeOut(500, function() {
                        $(this).remove();
                    });
                    $form.removeClass(messageClass);
                }, 5000);
            }
        },
        
        /**
         * Sérialise les données du formulaire
         */
        serializeForm: function($form) {
            const formArray = $form.serializeArray();
            const formData = {};
            
            // Convertir le tableau en objet
            $.each(formArray, function() {
                formData[this.name] = this.value;
            });
            
            // Ajouter le nom du formulaire pour identification
            formData.form_id = $form.attr('id') || 'unknown_form';
            
            return formData;
        },
        
        /**
         * Sauvegarde les données du formulaire en stockage local
         */
        saveFormData: function() {
            const $form = $(this.options.formSelector);
            if (!$form.length) return;
            
            // Sérialiser les données du formulaire
            const formData = this.serializeForm($form);
            
            // Ajouter un timestamp
            formData.saved_at = new Date().getTime();
            
            // Sauvegarder dans le stockage local
            try {
                localStorage.setItem('life_travel_form_data', JSON.stringify(formData));
                this.state.lastSaved = formData.saved_at;
                this.state.formData = formData;
                this.state.formModified = false;
                
                // Afficher brièvement l'indicateur de sauvegarde
                const self = this;
                $form.addClass(this.options.savingClass);
                setTimeout(function() {
                    $form.removeClass(self.options.savingClass);
                }, 1000);
                
                return true;
            } catch (e) {
                console.error('Erreur lors de la sauvegarde des données du formulaire:', e);
                return false;
            }
        },
        
        /**
         * Restaure les données du formulaire depuis le stockage local
         */
        restoreFormData: function() {
            try {
                const savedData = localStorage.getItem('life_travel_form_data');
                if (!savedData) return false;
                
                const formData = JSON.parse(savedData);
                const $form = $(this.options.formSelector);
                
                if (!$form.length) return false;
                
                // Vérifier si les données ne sont pas trop anciennes (24 heures)
                const now = new Date().getTime();
                if (formData.saved_at && (now - formData.saved_at > 24 * 60 * 60 * 1000)) {
                    this.clearSavedFormData();
                    return false;
                }
                
                // Restaurer chaque champ
                $.each(formData, function(name, value) {
                    // Ignorer les champs spéciaux
                    if (name === 'saved_at' || name === 'form_id') return;
                    
                    const $field = $form.find('[name="' + name + '"]');
                    if ($field.length) {
                        if ($field.is(':checkbox') || $field.is(':radio')) {
                            $field.prop('checked', value === 'on' || value === true);
                        } else {
                            $field.val(value);
                        }
                    }
                });
                
                // Mettre à jour l'état du formulaire
                this.state.lastSaved = formData.saved_at;
                this.state.formData = formData;
                
                // Déclencher les événements change pour que tout soit mis à jour
                $form.find('select').trigger('change');
                
                // Afficher un message à l'utilisateur
                this.showMessage('Vos données précédentes ont été restaurées.', 'info', $form);
                
                return true;
            } catch (e) {
                console.error('Erreur lors de la restauration des données du formulaire:', e);
                return false;
            }
        },
        
        /**
         * Supprime les données sauvegardées du formulaire
         */
        clearSavedFormData: function() {
            try {
                localStorage.removeItem('life_travel_form_data');
                this.state.lastSaved = null;
                this.state.formData = null;
                return true;
            } catch (e) {
                console.error('Erreur lors de la suppression des données du formulaire:', e);
                return false;
            }
        },
        
        /**
         * Place une soumission de formulaire en file d'attente
         */
        queueFormSubmission: function(formData) {
            try {
                // Récupérer la file d'attente existante
                let queue = JSON.parse(localStorage.getItem('life_travel_form_queue') || '[]');
                
                // Ajouter les données à la file d'attente
                formData.queued_at = new Date().getTime();
                queue.push(formData);
                
                // Sauvegarder la file d'attente mise à jour
                localStorage.setItem('life_travel_form_queue', JSON.stringify(queue));
                
                // Mettre à jour l'état
                this.state.submissionQueue = queue;
                
                // Enregistrer avec le service worker si disponible
                if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({
                        type: 'SAVE_FORM_DATA',
                        formData: formData
                    });
                }
                
                return true;
            } catch (e) {
                console.error('Erreur lors de la mise en file d\'attente de la soumission:', e);
                return false;
            }
        },
        
        /**
         * Traite les soumissions en attente
         */
        processPendingSubmissions: function() {
            try {
                // Vérifier s'il y a des soumissions en attente
                const queueStr = localStorage.getItem('life_travel_form_queue');
                if (!queueStr) return;
                
                const queue = JSON.parse(queueStr);
                if (!queue.length) return;
                
                console.log('Traitement de ' + queue.length + ' soumissions en attente...');
                
                // Copier la file d'attente et la vider
                const submissionsToProcess = [...queue];
                localStorage.removeItem('life_travel_form_queue');
                this.state.submissionQueue = [];
                
                // Traiter chaque soumission
                const self = this;
                submissionsToProcess.forEach(function(formData) {
                    // Trouver le formulaire correspondant
                    const $form = $('#' + formData.form_id);
                    
                    // Envoyer la soumission
                    $.ajax({
                        url: lifeTravel.ajax_url,
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                console.log('Soumission en attente réussie:', formData.form_id);
                                
                                // Afficher une notification si le formulaire existe encore
                                if ($form.length) {
                                    self.showMessage('Votre demande a été traitée avec succès.', 'success', $form);
                                }
                            } else {
                                console.error('Échec de la soumission en attente:', response.data);
                                
                                // Remettre en file d'attente si nécessaire
                                self.queueFormSubmission(formData);
                            }
                        },
                        error: function() {
                            console.error('Erreur réseau lors de la soumission en attente');
                            // Remettre en file d'attente
                            self.queueFormSubmission(formData);
                        }
                    });
                });
            } catch (e) {
                console.error('Erreur lors du traitement des soumissions en attente:', e);
            }
        }
    };
    
    // Initialiser lorsque le document est prêt
    $(document).ready(function() {
        FormHandler.init();
    });
    
    // Exposer globalement pour utilisation dans d'autres scripts
    window.LifeTravelFormHandler = FormHandler;
    
})(jQuery);
