/**
 * Life Travel - Gestionnaire d'images temporaires
 * 
 * Ce script permet de :
 * 1. Remplacer automatiquement les images manquantes par des alternatives temporaires
 * 2. Faciliter la transition vers WordPress en utilisant des attributs data-*
 * 3. Journaliser les images manquantes pour faciliter leur remplacement futur
 */

(function() {
    // Configuration des images de remplacement par catégorie
    const placeholderImages = {
        // Images principales des destinations
        'mont-cameroun.jpg': 'https://images.unsplash.com/photo-1621118209335-e913957125e7',
        'chutes-lobe.jpg': 'https://images.unsplash.com/photo-1506260408121-e353d10b87c7',
        'dja-reserve.jpg': 'https://images.unsplash.com/photo-1565967511849-76a60a516170',
        'reserve-dja.jpg': 'https://images.unsplash.com/photo-1565967511849-76a60a516170',
        'kribi-beach.jpg': 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e',
        'kribi.jpg': 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e',
        'rhumsiki.jpg': 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272',
        'chefferie.jpg': 'https://images.unsplash.com/photo-1586031297158-18030a3be5b7',
        'yaounde.jpg': 'https://images.unsplash.com/photo-1574236170880-faa1ef15718d',

        // Images de blog
        'mont-cameroun-blog.jpg': 'https://images.unsplash.com/photo-1621849400072-f554417f7051',
        'lobe-blog.jpg': 'https://images.unsplash.com/photo-1591020215160-8f0ff30a8210',

        // Images miniatures pour le calendrier
        'mini-mont-cameroun.jpg': 'https://images.unsplash.com/photo-1621118209335-e913957125e7?auto=format&fit=crop&w=150&h=100',
        'mini-lobe.jpg': 'https://images.unsplash.com/photo-1506260408121-e353d10b87c7?auto=format&fit=crop&w=150&h=100',
        'mini-kribi.jpg': 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=150&h=100',
        'mini-dja.jpg': 'https://images.unsplash.com/photo-1565967511849-76a60a516170?auto=format&fit=crop&w=150&h=100',

        // Images de galerie
        'gallery/mont-cameroun-1.jpg': 'https://images.unsplash.com/photo-1621118276570-2b9fc232841b',
        'gallery/mont-cameroun-2.jpg': 'https://images.unsplash.com/photo-1621790941816-813737b959da',
        'gallery/mont-cameroun-3.jpg': 'https://images.unsplash.com/photo-1621273983736-9c2f53adf814',
        'gallery/mont-cameroun-4.jpg': 'https://images.unsplash.com/photo-1621249508284-c1e2918167c1',
        
        // Images exclusives
        'gallery/exclusive-1.jpg': 'https://images.unsplash.com/photo-1533227268428-f9ed0900fb3b',
        'gallery/exclusive-2.jpg': 'https://images.unsplash.com/photo-1618137353323-374df7bbc9f6',
        'gallery/exclusive-3.jpg': 'https://images.unsplash.com/photo-1503614472-8c93d56e92ce',
        'gallery/exclusive-4.jpg': 'https://images.unsplash.com/photo-1586016413664-864c0dd76f53',
        'gallery/exclusive-5.jpg': 'https://images.unsplash.com/photo-1527525443983-6e60c75fff46',
        
        // Témoignages
        'testimonial-1.jpg': 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=150&h=150',
        'testimonial-2.jpg': 'https://images.unsplash.com/photo-1531427186611-ecfd6d936c79?auto=format&fit=crop&w=150&h=150',
        'testimonial-3.jpg': 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=150&h=150',
        
        // Avatars
        'avatar1.jpg': 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=60&h=60',
        'avatar2.jpg': 'https://images.unsplash.com/photo-1531427186611-ecfd6d936c79?auto=format&fit=crop&w=60&h=60'
    };

    // Configuration des qualités d'image selon la catégorie
    const imageQualityParams = {
        'default': '?auto=format&fit=crop&w=800&q=80',
        'gallery': '?auto=format&fit=crop&w=600&q=80',
        'mini': '?auto=format&fit=crop&w=150&h=100&q=70',
        'testimonial': '?auto=format&fit=crop&w=150&h=150&q=80',
        'avatar': '?auto=format&fit=crop&w=60&h=60&q=80'
    };

    // Liste pour suivre les images manquantes
    const missingImages = [];

    /**
     * Remplace une image qui ne se charge pas par une alternative temporaire
     */
    function handleImageError(img) {
        const src = img.getAttribute('src');
        const fileName = src.split('/').pop();
        const basePath = src.substring(0, src.lastIndexOf('/') + 1);
        const fullPath = src.replace(/^\.\//, '');  // Normalise le chemin relatif
        
        // Si nous avons déjà traité cette image, ne pas continuer
        if (img.getAttribute('data-replaced') === 'true') {
            return;
        }

        // Stocker le chemin d'origine pour référence future
        img.setAttribute('data-original-src', src);
        img.setAttribute('data-replaced', 'true');
        
        // Ajouter des attributs pour faciliter l'intégration WordPress
        img.setAttribute('data-wp-replace', 'true');
        
        // Déterminer la catégorie de l'image
        let imageType = 'default';
        if (fullPath.includes('gallery/')) {
            imageType = 'gallery';
        } else if (fullPath.includes('mini-')) {
            imageType = 'mini';
        } else if (fullPath.includes('testimonial')) {
            imageType = 'testimonial';
        } else if (fullPath.includes('avatar')) {
            imageType = 'avatar';
        }
        
        // Chercher une image de remplacement
        let replacement = null;
        
        // Vérifier d'abord le chemin complet
        if (placeholderImages[fullPath]) {
            replacement = placeholderImages[fullPath] + imageQualityParams[imageType];
        } 
        // Puis juste le nom de fichier
        else if (placeholderImages[fileName]) {
            replacement = placeholderImages[fileName] + imageQualityParams[imageType];
        }
        
        // Si aucune correspondance n'est trouvée, utiliser un placeholder générique
        if (!replacement) {
            replacement = 'https://via.placeholder.com/800x600?text=' + encodeURIComponent('Image: ' + fileName);
            
            // Ajouter à la liste des images manquantes
            if (!missingImages.includes(fullPath)) {
                missingImages.push(fullPath);
                console.warn('Image manquante remplacée par un placeholder: ' + fullPath);
            }
        }
        
        // Remplacer l'image
        img.setAttribute('src', replacement);
        
        // Mémoriser pour WordPress
        img.setAttribute('data-wp-image-name', fileName);
        
        // Ajouter une classe pour le styling
        img.classList.add('placeholder-image');
    }

    /**
     * Configure le suivi des images sur la page
     */
    function setupImageTracking() {
        // Traiter toutes les images existantes
        document.querySelectorAll('img').forEach(img => {
            if (!img.complete || img.naturalWidth === 0) {
                handleImageError(img);
            }
            
            // Ajouter un gestionnaire d'erreurs pour les futures charges d'images
            img.addEventListener('error', function() {
                handleImageError(this);
            });
        });

        // Journaliser les images manquantes en console
        if (missingImages.length > 0) {
            console.log('Images manquantes remplacées par des placeholders:', missingImages);
        }
    }

    // Initialiser le système quand le DOM est chargé
    document.addEventListener('DOMContentLoaded', setupImageTracking);
})();

// Fonction helper pour WordPress (à utiliser plus tard)
function registerLifeTravelImages() {
    if (typeof wp !== 'undefined' && wp.domReady) {
        wp.domReady(function () {
            // Ce code s'exécutera dans l'environnement WordPress
            const replacedImages = document.querySelectorAll('[data-wp-replace="true"]');
            console.log('Images à remplacer dans WordPress:', replacedImages.length);
        });
    }
}
