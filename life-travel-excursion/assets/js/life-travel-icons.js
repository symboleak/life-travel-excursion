/**
 * Life Travel - Gestionnaire d'icônes SVG
 * 
 * Ce script léger (<2KB) permet d'utiliser facilement les icônes SVG du sprite
 * dans l'interface utilisateur, même en mode offline. Il est spécifiquement optimisé
 * pour le contexte camerounais avec des connexions intermittentes.
 */
(function() {
  'use strict';
  
  // Configuration
  var config = {
    // Utiliser la configuration globale si disponible, sinon utiliser des valeurs par défaut
    spriteUrl: window.LIFE_TRAVEL_CONFIG && window.LIFE_TRAVEL_CONFIG.svgSpritePath 
        ? window.LIFE_TRAVEL_CONFIG.svgSpritePath 
        : '/assets/sprite.svg',
    iconPrefix: 'icon-',
    defaultSize: 24,
    defaultColor: 'currentColor',
    fallbackIcon: 'placeholder'
  };
  
  // Détection de chemin de base pour WordPress
  function getBasePath() {
    // Essayer de détecter le chemin de base à partir du script actuel
    var scripts = document.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
      var src = scripts[i].src;
      if (src.indexOf('life-travel-icons.js') !== -1) {
        return src.substring(0, src.lastIndexOf('/assets/js/life-travel-icons.js'));
      }
    }
    return '';
  }
  
  // Si le chemin est relatif, le transformer en absolu
  if (config.spriteUrl.indexOf('/') === 0 && config.spriteUrl.indexOf('//') !== 0) {
    var basePath = getBasePath();
    if (basePath) {
      config.spriteUrl = basePath + config.spriteUrl;
    }
  }
  
  // Cache pour le sprite SVG
  var spriteLoaded = false;
  var pendingIcons = [];
  var spriteContainer = null;
  
  /**
   * Charge le sprite SVG si nécessaire
   */
  function loadSprite() {
    if (spriteLoaded || document.getElementById('life-travel-svg-sprite')) {
      return Promise.resolve();
    }
    
    return new Promise(function(resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', config.spriteUrl, true);
      xhr.timeout = 3000; // Timeout adapté au contexte camerounais
      
      xhr.onload = function() {
        if (xhr.status === 200) {
          // Créer un conteneur pour le sprite
          spriteContainer = document.createElement('div');
          spriteContainer.id = 'life-travel-svg-sprite';
          spriteContainer.style.display = 'none';
          spriteContainer.innerHTML = xhr.responseText;
          document.body.appendChild(spriteContainer);
          
          spriteLoaded = true;
          
          // Traiter les icônes en attente
          pendingIcons.forEach(function(pendingIcon) {
            insertIcon(pendingIcon.element, pendingIcon.iconName, pendingIcon.options);
          });
          pendingIcons = [];
          
          resolve();
        } else {
          reject(new Error('Impossible de charger le sprite SVG'));
        }
      };
      
      xhr.onerror = function() {
        // En cas d'erreur, créer un sprite local minimal pour les icônes essentielles
        createMinimalSprite();
        resolve();
      };
      
      xhr.ontimeout = function() {
        // En cas de timeout, créer un sprite local minimal
        createMinimalSprite();
        resolve();
      };
      
      xhr.send();
    });
  }
  
  /**
   * Crée un sprite minimal pour les icônes essentielles en cas d'échec de chargement
   */
  function createMinimalSprite() {
    spriteContainer = document.createElement('div');
    spriteContainer.id = 'life-travel-svg-sprite';
    spriteContainer.style.display = 'none';
    spriteContainer.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">' +
      '<symbol id="icon-offline" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 10.15 4.63 8.45 5.69 7.1L16.9 18.31C15.55 19.37 13.85 20 12 20ZM18.31 16.9L7.1 5.69C8.45 4.63 10.15 4 12 4C16.41 4 20 7.59 20 12C20 13.85 19.37 15.55 18.31 16.9Z" fill="currentColor"/></symbol>' +
      '<symbol id="icon-refresh" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4C7.58 4 4.01 7.58 4.01 12C4.01 16.42 7.58 20 12 20C15.73 20 18.84 17.45 19.73 14H17.65C16.83 16.33 14.61 18 12 18C8.69 18 6 15.31 6 12C6 8.69 8.69 6 12 6C13.66 6 15.14 6.69 16.22 7.78L13 11H20V4L17.65 6.35Z" fill="currentColor"/></symbol>' +
      '<symbol id="icon-placeholder" viewBox="0 0 24 24"><rect width="24" height="24" fill="#EEEEEE"/><path d="M19 5V19H5V5H19ZM19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="currentColor"/></symbol>' +
    '</svg>';
    document.body.appendChild(spriteContainer);
    spriteLoaded = true;
  }
  
  /**
   * Insère une icône SVG dans un élément
   * 
   * @param {HTMLElement} element Élément où insérer l'icône
   * @param {string} iconName Nom de l'icône (sans préfixe)
   * @param {Object} options Options de l'icône
   */
  function insertIcon(element, iconName, options) {
    if (!spriteLoaded) {
      pendingIcons.push({
        element: element,
        iconName: iconName,
        options: options
      });
      loadSprite();
      return;
    }
    
    var symbolId = config.iconPrefix + iconName;
    var symbolExists = spriteContainer && spriteContainer.querySelector('#' + symbolId);
    
    // Utiliser l'icône de fallback si l'icône demandée n'existe pas
    if (!symbolExists) {
      console.warn('Icône non trouvée: ' + iconName + ', utilisation du fallback');
      symbolId = config.iconPrefix + config.fallbackIcon;
    }
    
    // Créer l'élément SVG
    var size = options.size || config.defaultSize;
    var color = options.color || config.defaultColor;
    var classes = options.class ? 'lt-icon ' + options.class : 'lt-icon';
    
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', classes);
    svg.setAttribute('width', size);
    svg.setAttribute('height', size);
    svg.setAttribute('fill', color);
    svg.setAttribute('aria-hidden', 'true');
    
    var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', '#' + symbolId);
    svg.appendChild(use);
    
    // Vider l'élément cible et y insérer l'icône
    while (element.firstChild) {
      element.removeChild(element.firstChild);
    }
    element.appendChild(svg);
  }
  
  /**
   * Initialise les icônes dans le DOM
   */
  function initIcons() {
    var icons = document.querySelectorAll('[data-lt-icon]');
    if (icons.length > 0) {
      loadSprite().then(function() {
        icons.forEach(function(iconElement) {
          var iconName = iconElement.getAttribute('data-lt-icon');
          var size = iconElement.getAttribute('data-lt-size');
          var color = iconElement.getAttribute('data-lt-color');
          
          insertIcon(iconElement, iconName, {
            size: size,
            color: color,
            class: iconElement.getAttribute('data-lt-class')
          });
        });
      });
    }
  }
  
  // Initialiser les icônes quand le DOM est prêt
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initIcons);
  } else {
    initIcons();
  }
  
  // Exposer l'API publique
  window.LifeTravelIcons = {
    /**
     * Insère une icône dans un élément
     * 
     * @param {string|HTMLElement} selector Sélecteur CSS ou élément DOM
     * @param {string} iconName Nom de l'icône (sans préfixe)
     * @param {Object} options Options de l'icône (taille, couleur, classe)
     */
    insert: function(selector, iconName, options) {
      var element = typeof selector === 'string' ? document.querySelector(selector) : selector;
      if (element) {
        insertIcon(element, iconName, options || {});
      }
    },
    
    /**
     * Précharge le sprite SVG pour une utilisation ultérieure
     * 
     * @return {Promise} Promise résolue quand le sprite est chargé
     */
    preload: loadSprite
  };
})();
