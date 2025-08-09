/**
 * Script d'administration pour la gestion des médias
 * @package Life Travel
 */

(function($) {
    'use strict';
    
    // Au chargement du document
    $(document).ready(function() {
        // Gestionnaire pour les boutons de téléversement de médias
        $('.upload-media-button').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var targetField = button.data('target');
            
            // Ouvrir la médiathèque WordPress
            var mediaUploader = wp.media({
                title: 'Sélectionner une image',
                button: {
                    text: 'Utiliser cette image'
                },
                multiple: false
            });
            
            // Quand une image est sélectionnée
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                
                // Mettre à jour le champ caché
                $('#' + targetField).val(attachment.id);
                
                // Mettre à jour l'aperçu
                var preview = button.siblings('.media-preview');
                if (preview.length) {
                    preview.html('<img src="' + attachment.url + '" alt="" style="max-width: 100%; height: auto;">');
                }
            });
            
            // Ouvrir la médiathèque
            mediaUploader.open();
        });
        
        // Afficher la valeur du curseur de qualité
        $('#life_travel_image_quality').on('input', function() {
            $('.quality-value').text($(this).val() + '%');
        });
    });
    
})(jQuery);
