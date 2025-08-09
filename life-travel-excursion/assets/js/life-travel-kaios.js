/**
 * Life Travel - Support spécifique KaiOS
 * 
 * Ce script améliore l'expérience utilisateur sur les appareils KaiOS 
 * très populaires au Cameroun. Il fournit des optimisations pour la 
 * navigation au clavier, la gestion des ressources limitées et le support 
 * des fonctionnalités spécifiques aux téléphones à touches.
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */

// Namespace pour éviter les collisions
window.LIFE_TRAVEL = window.LIFE_TRAVEL || {};
window.LIFE_TRAVEL.KAIOS = {};

/**
 * Détection des appareils KaiOS
 */
(function() {
    // Détection de KaiOS via user agent
    var isKaiOS = navigator.userAgent.indexOf('KAIOS') > -1;
    
    // Détection basée sur les fonctionnalités spécifiques
    var hasKaiHardwareKeys = typeof navigator.getDeviceStorage === 'function';
    
    // Informations sur la version de KaiOS si disponible
    var kaiOSVersion = (function() {
        if (!isKaiOS) return null;
        var match = navigator.userAgent.match(/KAIOS\/(\d+\.\d+)/);
        return match ? match[1] : '2.5'; // Version par défaut
    })();
    
    // Stocker ces informations dans l'espace de noms global
    window.LIFE_TRAVEL.KAIOS = {
        isKaiOS: isKaiOS,
        hasKaiHardwareKeys: hasKaiHardwareKeys,
        version: kaiOSVersion,
        isLowEndDevice: isKaiOS && (
            navigator.deviceMemory < 1 || 
            navigator.hardwareConcurrency < 2
        )
    };
    
    // Si c'est un appareil KaiOS, appliquer les optimisations
    if (isKaiOS) {
        document.documentElement.classList.add('lt-kaios');
        
        // Ajouter la version comme classe pour les styles CSS spécifiques
        if (kaiOSVersion) {
            document.documentElement.classList.add('lt-kaios-' + kaiOSVersion.replace('.', '-'));
        }
        
        // Configurer immédiatement la navigation au clavier
        window.addEventListener('DOMContentLoaded', setupKeyboardNavigation);
    }
})();

/**
 * Configuration optimisée pour la navigation au clavier sur KaiOS
 * Particulièrement important pour les téléphones à touches au Cameroun
 */
function setupKeyboardNavigation() {
    // Ne pas continuer si ce n'est pas KaiOS
    if (!window.LIFE_TRAVEL.KAIOS.isKaiOS) return;
    
    var currentFocusIndex = -1;
    var focusableElements = [];
    
    // Éléments pouvant recevoir le focus (optimisé pour KaiOS)
    function refreshFocusableElements() {
        // Sélecteurs adaptés à KaiOS
        var selectors = [
            'a[href]:not([tabindex="-1"])', 
            'button:not([disabled]):not([tabindex="-1"])',
            'input:not([disabled]):not([type="hidden"]):not([tabindex="-1"])',
            'select:not([disabled]):not([tabindex="-1"])',
            '.lt-focusable',
            '[tabindex]:not([tabindex="-1"])'
        ].join(',');
        
        // Obtenir tous les éléments pouvant recevoir le focus
        focusableElements = Array.from(document.querySelectorAll(selectors))
            // Filtrer les éléments cachés
            .filter(function(el) {
                return el.offsetParent !== null && 
                       getComputedStyle(el).display !== 'none' &&
                       getComputedStyle(el).visibility !== 'hidden';
            });
        
        // Si aucun élément n'a le focus actuellement, réinitialiser l'index
        if (!document.activeElement || document.activeElement === document.body) {
            currentFocusIndex = focusableElements.length > 0 ? 0 : -1;
            if (currentFocusIndex >= 0) {
                focusableElements[currentFocusIndex].focus();
            }
        } else {
            // Mettre à jour l'index actuel basé sur l'élément actuellement focus
            currentFocusIndex = focusableElements.indexOf(document.activeElement);
            if (currentFocusIndex === -1 && focusableElements.length > 0) {
                currentFocusIndex = 0;
            }
        }
    }

    // Navigation avec les touches (touches 2, 4, 6, 8 et 5/OK sur KaiOS)
    function handleKeyNavigation(event) {
        // Rafraîchir la liste des éléments focusables si nécessaire
        if (focusableElements.length === 0) {
            refreshFocusableElements();
        }
        
        // Ne rien faire s'il n'y a pas d'éléments focusables
        if (focusableElements.length === 0) return;
        
        var handled = false;
        
        switch (event.key) {
            case 'ArrowUp':    // Touche 2 sur KaiOS
                currentFocusIndex = Math.max(0, currentFocusIndex - 1);
                handled = true;
                break;
                
            case 'ArrowDown':  // Touche 8 sur KaiOS
                currentFocusIndex = Math.min(focusableElements.length - 1, currentFocusIndex + 1);
                handled = true;
                break;
                
            case 'ArrowLeft':  // Touche 4 sur KaiOS
                // Dans une application de voyage comme Life Travel, la navigation gauche/droite
                // peut être utilisée pour naviguer entre les onglets ou sections
                var prevSection = document.querySelector('.lt-prev-section');
                if (prevSection) {
                    prevSection.click();
                    handled = true;
                }
                break;
                
            case 'ArrowRight': // Touche 6 sur KaiOS
                var nextSection = document.querySelector('.lt-next-section');
                if (nextSection) {
                    nextSection.click();
                    handled = true;
                }
                break;
                
            case 'Enter':      // Touche 5/OK sur KaiOS
                // La plupart des navigateurs gèrent déjà l'événement Enter automatiquement
                // Aucun traitement spécial n'est nécessaire ici
                break;
                
            case 'Backspace':  // Touche retour arrière sur KaiOS
                // Gestion spéciale du retour arrière pour la navigation
                if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'TEXTAREA') {
                    // Retour à la page précédente si possible
                    if (window.history.length > 1) {
                        window.history.back();
                        handled = true;
                    }
                }
                break;
        }
        
        // Si la touche a été traitée, focus sur le nouvel élément et empêcher l'action par défaut
        if (handled && focusableElements[currentFocusIndex]) {
            focusableElements[currentFocusIndex].focus();
            event.preventDefault();
            event.stopPropagation();
        }
    }
    
    // Ajouter les écouteurs d'événements pour la navigation clavier
    document.addEventListener('keydown', handleKeyNavigation);
    
    // Rafraîchir les éléments focusables lors des mises à jour DOM majeures
    var observer = new MutationObserver(function(mutations) {
        var needsRefresh = false;
        for (var i = 0; i < mutations.length; i++) {
            var mutation = mutations[i];
            if (mutation.type === 'childList' || 
                (mutation.type === 'attributes' && 
                (mutation.attributeName === 'style' || 
                 mutation.attributeName === 'class'))) {
                needsRefresh = true;
                break;
            }
        }
        
        if (needsRefresh) {
            refreshFocusableElements();
        }
    });
    
    // Observer les changements dans le document
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['style', 'class', 'hidden', 'disabled']
    });
    
    // Initialisation initiale
    refreshFocusableElements();
    
    // Ajouter l'indicateur de focus visuel pour améliorer l'UX
    var style = document.createElement('style');
    style.innerHTML = `
        .lt-kaios *:focus {
            outline: 2px solid #0073B2 !important;
            outline-offset: 2px !important;
        }
        
        /* Augmenter la taille des zones cliquables pour les touches */
        .lt-kaios button,
        .lt-kaios a,
        .lt-kaios [role="button"],
        .lt-kaios input[type="button"],
        .lt-kaios input[type="submit"] {
            min-height: 28px;
            min-width: 40px;
            padding: 6px 8px;
        }
    `;
    document.head.appendChild(style);
}
