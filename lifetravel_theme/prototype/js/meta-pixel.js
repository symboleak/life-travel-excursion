/**
 * Life Travel - Intégration Meta Pixel
 * 
 * Ce fichier gère l'intégration avancée avec Meta Pixel pour le suivi des conversions
 * publicitaires Facebook/Instagram (événements standards et personnalisés)
 */

(function() {
    // Configuration des événements
    const PIXEL_CONFIG = {
        // À remplacer par l'ID réel du pixel
        pixelId: 'VOTRE_PIXEL_ID',
        
        // Configuration des événements standard
        events: {
            // Pages générales
            viewContent: {
                enabled: true,
                triggerDelay: 3000, // ms
            },
            
            // Événements d'excursions
            viewExcursion: {
                enabled: true,
                selector: '.excursion-detail',
            },
            initiateCheckout: {
                enabled: true,
                selector: '.booking-button, .reserve-button, [data-action="book-now"]',
            },
            addToCart: {
                enabled: true,
                selector: '.add-to-cart, [data-action="add-to-cart"]',
            },
            
            // Formulaire de contact/réservation
            formSubmit: {
                enabled: true,
                selector: 'form.contact-form, form.booking-form',
            },
        },
        
        // Paramètres généraux
        settings: {
            // Délai avant le chargement du pixel (permet aux bandeaux de cookies de s'afficher)
            loadDelay: 1500,
            
            // Si true, log les événements mais ne les envoie pas réellement (dev/test)
            debugMode: true,
            
            // Tracking avancé
            trackAdvanced: true,
        }
    };
    
    /**
     * Initialisation du pixel
     */
    function initMetaPixel() {
        // Ne pas initialiser plusieurs fois
        if (window.fbq) return;
        
        // Chargement du code Meta Pixel standard
        !function(f,b,e,v,n,t,s) {
            if(f.fbq)return;
            n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)
        }(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
        
        // Initialiser le pixel avec l'ID configuré
        fbq('init', PIXEL_CONFIG.pixelId);
        
        // Événement de vue de page standard
        fbq('track', 'PageView');
        
        // Log en mode développement
        if (PIXEL_CONFIG.settings.debugMode) {
            console.log('Meta Pixel initialisé:', PIXEL_CONFIG.pixelId);
        }
    }
    
    /**
     * Configuration des écouteurs d'événements pour les conversions
     */
    function setupEventListeners() {
        // Vérifier que le pixel est chargé
        if (typeof fbq !== 'function') {
            console.error('Meta Pixel non initialisé');
            return;
        }
        
        // ViewContent - Suivi d'engagement sur la page
        if (PIXEL_CONFIG.events.viewContent.enabled) {
            setTimeout(() => {
                fbq('track', 'ViewContent', {
                    content_type: 'page',
                    content_name: document.title,
                    content_category: getPageCategory()
                });
                
                if (PIXEL_CONFIG.settings.debugMode) {
                    console.log('Meta Pixel Événement: ViewContent', document.title);
                }
            }, PIXEL_CONFIG.events.viewContent.triggerDelay);
        }
        
        // Événements de clics sur les boutons de réservation
        if (PIXEL_CONFIG.events.initiateCheckout.enabled) {
            setupClickEvents(
                PIXEL_CONFIG.events.initiateCheckout.selector,
                'InitiateCheckout',
                getProductInfoFromElement
            );
        }
        
        // Événements d'ajout au panier
        if (PIXEL_CONFIG.events.addToCart.enabled) {
            setupClickEvents(
                PIXEL_CONFIG.events.addToCart.selector,
                'AddToCart',
                getProductInfoFromElement
            );
        }
        
        // Soumission de formulaire
        if (PIXEL_CONFIG.events.formSubmit.enabled) {
            document.querySelectorAll(PIXEL_CONFIG.events.formSubmit.selector).forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Type de formulaire (contact ou réservation)
                    const formType = form.classList.contains('booking-form') ? 'booking' : 'contact';
                    const eventName = formType === 'booking' ? 'CompleteRegistration' : 'Lead';
                    
                    // Trouver des informations supplémentaires si disponibles
                    let additionalData = {};
                    
                    // Pour les formulaires de réservation, essayer de trouver des détails
                    if (formType === 'booking') {
                        const excursionName = form.querySelector('[name="excursion_name"]')?.value || document.title;
                        const participantsField = form.querySelector('[name="participants"]');
                        const participants = participantsField ? parseInt(participantsField.value, 10) : 1;
                        
                        additionalData = {
                            content_name: excursionName,
                            num_items: participants,
                            status: 'booking_form_submitted'
                        };
                    }
                    
                    // Envoyer l'événement
                    fbq('track', eventName, additionalData);
                    
                    if (PIXEL_CONFIG.settings.debugMode) {
                        console.log(`Meta Pixel Événement: ${eventName}`, additionalData);
                    }
                });
            });
        }
    }
    
    /**
     * Configure des écouteurs d'événements pour les clics sur des éléments
     * 
     * @param {string} selector - Sélecteur CSS pour les éléments à surveiller
     * @param {string} eventName - Nom de l'événement Meta Pixel à déclencher
     * @param {Function} dataExtractor - Fonction pour extraire les données de l'élément
     */
    function setupClickEvents(selector, eventName, dataExtractor) {
        document.querySelectorAll(selector).forEach(element => {
            element.addEventListener('click', function(e) {
                // Extraire les données
                const eventData = dataExtractor(this);
                
                // Envoyer l'événement
                fbq('track', eventName, eventData);
                
                if (PIXEL_CONFIG.settings.debugMode) {
                    console.log(`Meta Pixel Événement: ${eventName}`, eventData);
                }
            });
        });
    }
    
    /**
     * Détermine la catégorie de la page actuelle
     * 
     * @return {string} Catégorie de la page
     */
    function getPageCategory() {
        const path = window.location.pathname;
        
        if (path.includes('excursions.html') || path.includes('excursion/')) {
            return 'excursions';
        } else if (path.includes('blog') || path.includes('carnet-de-voyage')) {
            return 'blog';
        } else if (path.includes('sur-mesure.html')) {
            return 'sur-mesure';
        } else if (path.includes('contact.html')) {
            return 'contact';
        } else if (path.includes('a-propos.html')) {
            return 'about';
        } else {
            return 'home';
        }
    }
    
    /**
     * Extrait les informations produit à partir d'un élément DOM
     * 
     * @param {HTMLElement} element - L'élément DOM (généralement un bouton)
     * @return {Object} Données du produit
     */
    function getProductInfoFromElement(element) {
        // Essayer d'abord les attributs data-*
        let name = element.getAttribute('data-product-name');
        let price = element.getAttribute('data-product-value');
        
        // Si non disponibles, chercher dans les parents
        if (!name || !price) {
            const container = element.closest('.excursion-card, .product, .excursion-detail');
            
            if (container) {
                // Chercher le nom dans un titre
                const titleElement = container.querySelector('.excursion-title, .product-title, h2, h3');
                if (titleElement && !name) {
                    name = titleElement.textContent.trim();
                }
                
                // Chercher le prix
                const priceElement = container.querySelector('.price, .amount, .excursion-price');
                if (priceElement && !price) {
                    // Extraire uniquement les chiffres
                    price = priceElement.textContent.replace(/[^0-9]/g, '');
                }
            }
        }
        
        // Valeurs par défaut
        return {
            content_name: name || 'Excursion',
            value: price || '0',
            currency: 'XAF',
            content_type: 'product',
            content_category: 'excursion'
        };
    }
    
    /**
     * Initialisation après délai
     */
    function init() {
        // Attendre pour charger le pixel (permet aux bandeaux de cookies de s'afficher)
        setTimeout(() => {
            initMetaPixel();
            
            // Configuration des écouteurs après que le DOM soit complètement chargé
            if (document.readyState === 'complete') {
                setupEventListeners();
            } else {
                document.addEventListener('DOMContentLoaded', setupEventListeners);
            }
        }, PIXEL_CONFIG.settings.loadDelay);
    }
    
    // Démarrer l'initialisation
    init();
})();
