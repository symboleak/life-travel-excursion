/**
 * Script de gestion de l'authentification fluide
 * 
 * @package Life_Travel_Excursion
 */
(function($) {
    'use strict';
    
    // Objet principal pour la gestion de l'authentification
    var LTEAuthentication = {
        /**
         * Initialisation
         */
        init: function() {
            // Chargement des éléments du DOM
            this.tabs = $('.lte-auth-tab');
            this.methods = $('.lte-auth-method');
            this.emailForm = $('.lte-method-email form');
            this.phoneForm = $('.lte-method-phone form');
            this.fbButton = $('.lte-facebook-btn');
            this.messageContainer = $('<div class="lte-auth-message"></div>');
            
            // Ajout du conteneur de message
            this.messageContainer.insertBefore(this.methods.first());
            this.messageContainer.hide();
            
            // Initialisation des événements
            this.initTabs();
            this.initEmailAuth();
            this.initPhoneAuth();
            this.initFacebookAuth();
            
            // Vérifie si un message d'erreur dans l'URL (après redirection)
            this.checkUrlParams();
        },
        
        /**
         * Initialise les onglets de méthode d'authentification
         */
        initTabs: function() {
            var self = this;
            
            this.tabs.on('click', function() {
                var $tab = $(this);
                var method = $tab.data('method');
                
                // Activer l'onglet cliqué
                self.tabs.removeClass('active');
                $tab.addClass('active');
                
                // Afficher la méthode correspondante
                self.methods.removeClass('active');
                $('.lte-method-' + method).addClass('active');
                
                // Masquer les messages
                self.hideMessage();
            });
        },
        
        /**
         * Initialise l'authentification par email
         */
        initEmailAuth: function() {
            var self = this;
            
            if (this.emailForm.length === 0) {
                return;
            }
            
            // Bouton d'envoi de code
            this.emailForm.find('.lte-send-code-btn').on('click', function() {
                var $btn = $(this);
                var email = self.emailForm.find('#lte-email').val();
                
                // Validation de base
                if (!self.validateEmail(email)) {
                    self.showMessage('error', lteAuth.i18n.enter_email);
                    return;
                }
                
                // Désactiver le bouton et afficher le chargement
                $btn.prop('disabled', true).html('<span class="lte-loader"></span> ' + lteAuth.i18n.sending);
                
                // Envoyer le code via AJAX
                self.sendAuthCode('email', email, function(success, message) {
                    if (success) {
                        // Passer à l'étape du code
                        self.emailForm.find('.lte-step-email').removeClass('active');
                        self.emailForm.find('.lte-step-code').addClass('active');
                        self.showMessage('success', message);
                    } else {
                        self.showMessage('error', message);
                        $btn.prop('disabled', false).text(lteAuth.i18n.try_again);
                    }
                });
            });
            
            // Bouton de vérification du code
            this.emailForm.find('.lte-verify-code-btn').on('click', function() {
                var $btn = $(this);
                var email = self.emailForm.find('#lte-email').val();
                var code = self.emailForm.find('#lte-email-code').val();
                var redirect = self.emailForm.find('input[name="redirect_to"]').val();
                
                // Validation de base
                if (!code) {
                    self.showMessage('error', lteAuth.i18n.enter_code);
                    return;
                }
                
                // Désactiver le bouton et afficher le chargement
                $btn.prop('disabled', true).html('<span class="lte-loader"></span> ' + lteAuth.i18n.verifying);
                
                // Vérifier le code via AJAX
                self.verifyAuthCode('email', email, code, redirect, function(success, message, redirect) {
                    if (success) {
                        self.showMessage('success', message);
                        // Rediriger après un court délai
                        setTimeout(function() {
                            window.location.href = redirect;
                        }, 1000);
                    } else {
                        self.showMessage('error', message);
                        $btn.prop('disabled', false).text(lteAuth.i18n.try_again);
                    }
                });
            });
            
            // Bouton pour renvoyer le code
            this.emailForm.find('.lte-resend-code-btn').on('click', function() {
                var $btn = $(this);
                var email = self.emailForm.find('#lte-email').val();
                
                // Désactiver le bouton et afficher le chargement
                $btn.prop('disabled', true).html('<span class="lte-loader"></span> ' + lteAuth.i18n.sending);
                
                // Envoyer le code via AJAX
                self.sendAuthCode('email', email, function(success, message) {
                    if (success) {
                        self.showMessage('success', message);
                    } else {
                        self.showMessage('error', message);
                    }
                    $btn.prop('disabled', false).text(lteAuth.i18n.resend_code);
                });
            });
        },
        
        /**
         * Initialise l'authentification par téléphone
         */
        initPhoneAuth: function() {
            var self = this;
            
            if (this.phoneForm.length === 0) {
                return;
            }
            
            // Bouton d'envoi de code
            this.phoneForm.find('.lte-send-code-btn').on('click', function() {
                var $btn = $(this);
                var phone = self.phoneForm.find('#lte-phone').val();
                
                // Validation de base
                if (!self.validatePhone(phone)) {
                    self.showMessage('error', lteAuth.i18n.enter_phone);
                    return;
                }
                
                // Désactiver le bouton et afficher le chargement
                $btn.prop('disabled', true).html('<span class="lte-loader"></span> ' + lteAuth.i18n.sending);
                
                // Envoyer le code via AJAX
                self.sendAuthCode('phone', phone, function(success, message) {
                    if (success) {
                        // Passer à l'étape du code
                        self.phoneForm.find('.lte-step-phone').removeClass('active');
                        self.phoneForm.find('.lte-step-code').addClass('active');
                        self.showMessage('success', message);
                    } else {
                        self.showMessage('error', message);
                        $btn.prop('disabled', false).text(lteAuth.i18n.try_again);
                    }
                });
            });
            
            // Bouton de vérification du code
            this.phoneForm.find('.lte-verify-code-btn').on('click', function() {
                var $btn = $(this);
                var phone = self.phoneForm.find('#lte-phone').val();
                var code = self.phoneForm.find('#lte-phone-code').val();
                var redirect = self.phoneForm.find('input[name="redirect_to"]').val();
                
                // Validation de base
                if (!code) {
                    self.showMessage('error', lteAuth.i18n.enter_code);
                    return;
                }
                
                // Désactiver le bouton et afficher le chargement
                $btn.prop('disabled', true).html('<span class="lte-loader"></span> ' + lteAuth.i18n.verifying);
                
                // Vérifier le code via AJAX
                self.verifyAuthCode('phone', phone, code, redirect, function(success, message, redirect) {
                    if (success) {
                        self.showMessage('success', message);
                        // Rediriger après un court délai
                        setTimeout(function() {
                            window.location.href = redirect;
                        }, 1000);
                    } else {
                        self.showMessage('error', message);
                        $btn.prop('disabled', false).text(lteAuth.i18n.try_again);
                    }
                });
            });
            
            // Bouton pour renvoyer le code
            this.phoneForm.find('.lte-resend-code-btn').on('click', function() {
                var $btn = $(this);
                var phone = self.phoneForm.find('#lte-phone').val();
                
                // Désactiver le bouton et afficher le chargement
                $btn.prop('disabled', true).html('<span class="lte-loader"></span> ' + lteAuth.i18n.sending);
                
                // Envoyer le code via AJAX
                self.sendAuthCode('phone', phone, function(success, message) {
                    if (success) {
                        self.showMessage('success', message);
                    } else {
                        self.showMessage('error', message);
                    }
                    $btn.prop('disabled', false).text(lteAuth.i18n.resend_code);
                });
            });
        },
        
        /**
         * Initialise l'authentification par Facebook
         */
        initFacebookAuth: function() {
            var self = this;
            
            if (this.fbButton.length === 0) {
                return;
            }
            
            this.fbButton.on('click', function() {
                var $btn = $(this);
                var redirect = $btn.siblings('input[name="redirect_to"]').val();
                
                // Vérifier si l'API Facebook est chargée
                if (typeof FB === 'undefined') {
                    self.showMessage('error', lteAuth.i18n.fb_not_loaded);
                    return;
                }
                
                // Désactiver le bouton et afficher le chargement
                $btn.prop('disabled', true).html('<span class="lte-loader"></span> ' + lteAuth.i18n.connecting);
                
                // Connexion via Facebook
                FB.login(function(response) {
                    if (response.status === 'connected') {
                        // Obtenir les informations de base
                        FB.api('/me', {fields: 'email,name'}, function(userData) {
                            // Authentifier l'utilisateur sur notre site
                            self.facebookAuth(userData, response.authResponse.accessToken, redirect, function(success, message, redirect) {
                                if (success) {
                                    self.showMessage('success', message);
                                    // Rediriger après un court délai
                                    setTimeout(function() {
                                        window.location.href = redirect;
                                    }, 1000);
                                } else {
                                    self.showMessage('error', message);
                                    $btn.prop('disabled', false).html('<span class="lte-facebook-icon"></span> ' + lteAuth.i18n.continue_with_fb);
                                }
                            });
                        });
                    } else {
                        self.showMessage('error', lteAuth.i18n.fb_cancelled);
                        $btn.prop('disabled', false).html('<span class="lte-facebook-icon"></span> ' + lteAuth.i18n.continue_with_fb);
                    }
                }, {scope: 'email'});
            });
        },
        
        /**
         * Envoie un code d'authentification
         * 
         * @param {string} method Méthode d'authentification (email, phone)
         * @param {string} identifier Email ou téléphone
         * @param {function} callback Fonction de rappel
         */
        sendAuthCode: function(method, identifier, callback) {
            $.ajax({
                url: lteAuth.ajax_url,
                type: 'POST',
                data: {
                    action: 'lte_send_auth_code',
                    nonce: lteAuth.nonce,
                    method: method,
                    identifier: identifier
                },
                success: function(response) {
                    if (response.success) {
                        callback(true, response.data.message || lteAuth.i18n.code_sent);
                    } else {
                        callback(false, response.data.message || lteAuth.i18n.error_sending);
                    }
                },
                error: function() {
                    callback(false, lteAuth.i18n.network_error);
                }
            });
        },
        
        /**
         * Vérifie un code d'authentification
         * 
         * @param {string} method Méthode d'authentification (email, phone)
         * @param {string} identifier Email ou téléphone
         * @param {string} code Code à vérifier
         * @param {string} redirect URL de redirection
         * @param {function} callback Fonction de rappel
         */
        verifyAuthCode: function(method, identifier, code, redirect, callback) {
            $.ajax({
                url: lteAuth.ajax_url,
                type: 'POST',
                data: {
                    action: 'lte_verify_auth_code',
                    nonce: lteAuth.nonce,
                    method: method,
                    identifier: identifier,
                    code: code,
                    redirect_to: redirect
                },
                success: function(response) {
                    if (response.success) {
                        callback(
                            true,
                            response.data.message || lteAuth.i18n.login_success,
                            response.data.redirect || redirect || window.location.href
                        );
                    } else {
                        callback(false, response.data.message || lteAuth.i18n.invalid_code);
                    }
                },
                error: function() {
                    callback(false, lteAuth.i18n.network_error);
                }
            });
        },
        
        /**
         * Authentifie via Facebook
         * 
         * @param {object} userData Données utilisateur de Facebook
         * @param {string} accessToken Jeton d'accès Facebook
         * @param {string} redirect URL de redirection
         * @param {function} callback Fonction de rappel
         */
        facebookAuth: function(userData, accessToken, redirect, callback) {
            $.ajax({
                url: lteAuth.ajax_url,
                type: 'POST',
                data: {
                    action: 'lte_facebook_auth',
                    nonce: lteAuth.nonce,
                    user_data: userData,
                    access_token: accessToken,
                    redirect_to: redirect
                },
                success: function(response) {
                    if (response.success) {
                        callback(
                            true,
                            response.data.message || lteAuth.i18n.login_success,
                            response.data.redirect || redirect || window.location.href
                        );
                    } else {
                        callback(false, response.data.message || lteAuth.i18n.fb_error);
                    }
                },
                error: function() {
                    callback(false, lteAuth.i18n.network_error);
                }
            });
        },
        
        /**
         * Affiche un message
         * 
         * @param {string} type Type de message (error, success, info)
         * @param {string} text Texte du message
         */
        showMessage: function(type, text) {
            this.messageContainer
                .removeClass('error success info')
                .addClass(type)
                .html(text)
                .show();
                
            // Faire défiler vers le message
            $('html, body').animate({
                scrollTop: this.messageContainer.offset().top - 50
            }, 300);
            
            // Masquer après un certain temps si c'est un succès
            if (type === 'success') {
                var self = this;
                setTimeout(function() {
                    self.messageContainer.fadeOut(300);
                }, 5000);
            }
        },
        
        /**
         * Masque le message
         */
        hideMessage: function() {
            this.messageContainer.hide();
        },
        
        /**
         * Valide un email
         * 
         * @param {string} email Adresse email à valider
         * @return {boolean} Validité de l'email
         */
        validateEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },
        
        /**
         * Valide un numéro de téléphone
         * 
         * @param {string} phone Numéro de téléphone à valider
         * @return {boolean} Validité du téléphone
         */
        validatePhone: function(phone) {
            // Format international avec ou sans +
            var regex = /^(\+)?[0-9]{8,15}$/;
            return regex.test(phone);
        },
        
        /**
         * Vérifie les paramètres d'URL pour les messages d'erreur
         */
        checkUrlParams: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var error = urlParams.get('auth_error');
            
            if (error) {
                this.showMessage('error', decodeURIComponent(error));
            }
        }
    };
    
    // Initialiser quand le DOM est prêt
    $(document).ready(function() {
        // Vérifier si les formulaires d'authentification sont présents
        if ($('.lte-auth-container').length > 0) {
            LTEAuthentication.init();
        }
        
        // Initialiser l'authentification à deux facteurs admin si présente
        if ($('.lte-admin-2fa-container').length > 0) {
            // Logique 2FA admin
            var $form = $('.lte-admin-2fa-form');
            
            $form.on('submit', function(e) {
                e.preventDefault();
                
                var $btn = $form.find('button');
                var code = $form.find('input[name="code"]').val();
                var nonce = $form.find('input[name="nonce"]').val();
                var redirect = $form.find('input[name="redirect"]').val();
                
                $btn.prop('disabled', true).html('<span class="lte-loader"></span> ' + lteAuth.i18n.verifying);
                
                $.ajax({
                    url: lteAuth.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'lte_2fa_verify',
                        nonce: nonce,
                        code: code
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = redirect || lteAuth.admin_url;
                        } else {
                            $form.find('.lte-auth-message')
                                .removeClass('success')
                                .addClass('error')
                                .text(response.data.message || lteAuth.i18n.invalid_code)
                                .show();
                            
                            $btn.prop('disabled', false).text(lteAuth.i18n.verify);
                        }
                    },
                    error: function() {
                        $form.find('.lte-auth-message')
                            .removeClass('success')
                            .addClass('error')
                            .text(lteAuth.i18n.network_error)
                            .show();
                        
                        $btn.prop('disabled', false).text(lteAuth.i18n.verify);
                    }
                });
            });
        }
    });
    
})(jQuery);
