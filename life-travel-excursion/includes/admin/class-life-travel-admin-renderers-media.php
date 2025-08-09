<?php
/**
 * Renderers de gestion des médias pour Life Travel
 *
 * Ce fichier contient les méthodes de rendu pour la gestion des médias
 * dans l'interface administrateur unifiée
 *
 * @package Life Travel Excursion
 * @version 2.3.7
 */

defined('ABSPATH') || exit;

/**
 * Méthodes de rendu pour la gestion des médias
 */
trait Life_Travel_Admin_Renderers_Media {
    
    /**
     * Affiche l'interface de gestion des logos et de l'identité visuelle
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_media_logos($page_id, $section_id) {
        // Récupérer les paramètres actuels
        $logo_id = get_option('life_travel_main_logo', 0);
        $logo_mobile_id = get_option('life_travel_mobile_logo', 0);
        $favicon_id = get_option('life_travel_favicon', 0);
        $logo_quality = get_option('life_travel_logo_quality', 100);
        
        // Traitement du formulaire
        if (isset($_POST['life_travel_save_logos']) && check_admin_referer('life_travel_save_logos')) {
            // Sauvegarder les IDs des médias
            if (isset($_POST['life_travel_main_logo'])) {
                update_option('life_travel_main_logo', absint($_POST['life_travel_main_logo']));
                $logo_id = absint($_POST['life_travel_main_logo']);
            }
            
            if (isset($_POST['life_travel_mobile_logo'])) {
                update_option('life_travel_mobile_logo', absint($_POST['life_travel_mobile_logo']));
                $logo_mobile_id = absint($_POST['life_travel_mobile_logo']);
            }
            
            if (isset($_POST['life_travel_favicon'])) {
                update_option('life_travel_favicon', absint($_POST['life_travel_favicon']));
                $favicon_id = absint($_POST['life_travel_favicon']);
            }
            
            if (isset($_POST['life_travel_logo_quality'])) {
                $quality = intval($_POST['life_travel_logo_quality']);
                $quality = max(60, min(100, $quality)); // Limiter entre 60 et 100
                update_option('life_travel_logo_quality', $quality);
                $logo_quality = $quality;
            }
            
            echo '<div class="updated"><p>' . esc_html__('Paramètres des logos enregistrés avec succès.', 'life-travel-excursion') . '</p></div>';
        }
        
        // Afficher l'interface utilisateur
        ?>
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-intro">
                <h3><?php _e('Logos et identité visuelle', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Configurez les logos et éléments d\'identité visuelle de votre site.', 'life-travel-excursion'); ?></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('life_travel_save_logos'); ?>
                
                <div class="life-travel-admin-field-group">
                    <div class="life-travel-admin-field">
                        <label for="life_travel_main_logo">
                            <?php _e('Logo principal', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Logo affiché sur les écrans larges. Formats recommandés: PNG ou SVG.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-media-field">
                            <div class="life-travel-media-preview">
                                <?php if ($logo_id) : ?>
                                    <?php echo wp_get_attachment_image($logo_id, 'medium'); ?>
                                <?php else : ?>
                                    <div class="life-travel-no-image"><?php _e('Aucun logo', 'life-travel-excursion'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="life_travel_main_logo" id="life_travel_main_logo" value="<?php echo esc_attr($logo_id); ?>">
                            
                            <div class="life-travel-media-buttons">
                                <button type="button" class="button life-travel-select-media" data-target="life_travel_main_logo">
                                    <?php _e('Sélectionner', 'life-travel-excursion'); ?>
                                </button>
                                
                                <?php if ($logo_id) : ?>
                                    <button type="button" class="button life-travel-remove-media" data-target="life_travel_main_logo">
                                        <?php _e('Supprimer', 'life-travel-excursion'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <p class="description">
                                <?php _e('Dimensions recommandées: 240×80 pixels', 'life-travel-excursion'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_mobile_logo">
                            <?php _e('Logo mobile', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Version simplifiée du logo pour les appareils mobiles.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-media-field">
                            <div class="life-travel-media-preview">
                                <?php if ($logo_mobile_id) : ?>
                                    <?php echo wp_get_attachment_image($logo_mobile_id, 'medium'); ?>
                                <?php else : ?>
                                    <div class="life-travel-no-image"><?php _e('Aucun logo mobile', 'life-travel-excursion'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="life_travel_mobile_logo" id="life_travel_mobile_logo" value="<?php echo esc_attr($logo_mobile_id); ?>">
                            
                            <div class="life-travel-media-buttons">
                                <button type="button" class="button life-travel-select-media" data-target="life_travel_mobile_logo">
                                    <?php _e('Sélectionner', 'life-travel-excursion'); ?>
                                </button>
                                
                                <?php if ($logo_mobile_id) : ?>
                                    <button type="button" class="button life-travel-remove-media" data-target="life_travel_mobile_logo">
                                        <?php _e('Supprimer', 'life-travel-excursion'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <p class="description">
                                <?php _e('Dimensions recommandées: 120×40 pixels', 'life-travel-excursion'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_favicon">
                            <?php _e('Favicon', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Petit icône affiché dans les onglets du navigateur.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-media-field">
                            <div class="life-travel-media-preview life-travel-favicon-preview">
                                <?php if ($favicon_id) : ?>
                                    <?php echo wp_get_attachment_image($favicon_id, 'thumbnail'); ?>
                                <?php else : ?>
                                    <div class="life-travel-no-image"><?php _e('Aucun favicon', 'life-travel-excursion'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="life_travel_favicon" id="life_travel_favicon" value="<?php echo esc_attr($favicon_id); ?>">
                            
                            <div class="life-travel-media-buttons">
                                <button type="button" class="button life-travel-select-media" data-target="life_travel_favicon">
                                    <?php _e('Sélectionner', 'life-travel-excursion'); ?>
                                </button>
                                
                                <?php if ($favicon_id) : ?>
                                    <button type="button" class="button life-travel-remove-media" data-target="life_travel_favicon">
                                        <?php _e('Supprimer', 'life-travel-excursion'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <p class="description">
                                <?php _e('Format carré, dimensions recommandées: 512×512 pixels', 'life-travel-excursion'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_logo_quality">
                            <?php _e('Qualité des images', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Qualité de compression JPEG. Plus élevée = meilleure qualité mais fichiers plus lourds.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-range-field">
                            <input type="range" name="life_travel_logo_quality" id="life_travel_logo_quality" min="60" max="100" value="<?php echo esc_attr($logo_quality); ?>">
                            <span class="life-travel-range-value"><?php echo esc_html($logo_quality); ?>%</span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Pour connexions lentes, réduisez à 80% ou moins', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="life-travel-admin-submit">
                    <input type="submit" name="life_travel_save_logos" class="button button-primary" value="<?php esc_attr_e('Enregistrer les modifications', 'life-travel-excursion'); ?>">
                </div>
            </form>
            
            <div class="life-travel-admin-tips">
                <h4><?php _e('Conseils pour les logos', 'life-travel-excursion'); ?></h4>
                <ul>
                    <li><?php _e('Utilisez des formats PNG ou SVG pour une meilleure qualité', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Le logo mobile devrait être une version simplifiée du logo principal', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Pour les connexions lentes, privilégiez des images optimisées', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Gestionnaire pour sélectionner un média
            $('.life-travel-select-media').on('click', function() {
                var targetId = $(this).data('target');
                var mediaUploader;
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: '<?php _e('Sélectionner une image', 'life-travel-excursion'); ?>',
                    button: {
                        text: '<?php _e('Utiliser cette image', 'life-travel-excursion'); ?>'
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#' + targetId).val(attachment.id);
                    
                    // Mettre à jour l'aperçu
                    var $preview = $('#' + targetId).closest('.life-travel-media-field').find('.life-travel-media-preview');
                    
                    if (attachment.type === 'image') {
                        $preview.html('<img src="' + attachment.url + '" alt="">');
                    } else {
                        $preview.html('<div class="life-travel-no-image"><?php _e('Format non supporté', 'life-travel-excursion'); ?></div>');
                    }
                    
                    // Afficher le bouton de suppression
                    $('#' + targetId).closest('.life-travel-media-field').find('.life-travel-remove-media').show();
                });
                
                mediaUploader.open();
            });
            
            // Gestionnaire pour supprimer un média
            $('.life-travel-remove-media').on('click', function() {
                var targetId = $(this).data('target');
                $('#' + targetId).val('');
                
                // Mettre à jour l'aperçu
                var $preview = $('#' + targetId).closest('.life-travel-media-field').find('.life-travel-media-preview');
                $preview.html('<div class="life-travel-no-image"><?php _e('Aucune image', 'life-travel-excursion'); ?></div>');
                
                // Cacher le bouton de suppression
                $(this).hide();
            });
            
            // Mise à jour dynamique de la valeur du curseur de qualité
            $('#life_travel_logo_quality').on('input', function() {
                $(this).next('.life-travel-range-value').text($(this).val() + '%');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Affiche l'interface de gestion des arrière-plans du site
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_media_backgrounds($page_id, $section_id) {
        // Récupérer les paramètres actuels
        $header_bg_id = get_option('life_travel_header_background', 0);
        $footer_bg_id = get_option('life_travel_footer_background', 0);
        $home_bg_id = get_option('life_travel_home_background', 0);
        $excursion_default_bg_id = get_option('life_travel_excursion_default_background', 0);
        $bg_overlay_opacity = get_option('life_travel_background_overlay_opacity', 40);
        $bg_quality = get_option('life_travel_background_quality', 90);
        $bg_mobile_quality = get_option('life_travel_background_mobile_quality', 75);
        $bg_lazy_loading = get_option('life_travel_background_lazy_loading', 'on');
        
        // Traitement du formulaire
        if (isset($_POST['life_travel_save_backgrounds']) && check_admin_referer('life_travel_save_backgrounds')) {
            // Sauvegarder les IDs des médias
            if (isset($_POST['life_travel_header_background'])) {
                update_option('life_travel_header_background', absint($_POST['life_travel_header_background']));
                $header_bg_id = absint($_POST['life_travel_header_background']);
            }
            
            if (isset($_POST['life_travel_footer_background'])) {
                update_option('life_travel_footer_background', absint($_POST['life_travel_footer_background']));
                $footer_bg_id = absint($_POST['life_travel_footer_background']);
            }
            
            if (isset($_POST['life_travel_home_background'])) {
                update_option('life_travel_home_background', absint($_POST['life_travel_home_background']));
                $home_bg_id = absint($_POST['life_travel_home_background']);
            }
            
            if (isset($_POST['life_travel_excursion_default_background'])) {
                update_option('life_travel_excursion_default_background', absint($_POST['life_travel_excursion_default_background']));
                $excursion_default_bg_id = absint($_POST['life_travel_excursion_default_background']);
            }
            
            if (isset($_POST['life_travel_background_overlay_opacity'])) {
                $opacity = intval($_POST['life_travel_background_overlay_opacity']);
                $opacity = max(0, min(100, $opacity)); // Limiter entre 0 et 100
                update_option('life_travel_background_overlay_opacity', $opacity);
                $bg_overlay_opacity = $opacity;
            }
            
            if (isset($_POST['life_travel_background_quality'])) {
                $quality = intval($_POST['life_travel_background_quality']);
                $quality = max(60, min(100, $quality)); // Limiter entre 60 et 100
                update_option('life_travel_background_quality', $quality);
                $bg_quality = $quality;
            }
            
            if (isset($_POST['life_travel_background_mobile_quality'])) {
                $mobile_quality = intval($_POST['life_travel_background_mobile_quality']);
                $mobile_quality = max(40, min(90, $mobile_quality)); // Limiter entre 40 et 90
                update_option('life_travel_background_mobile_quality', $mobile_quality);
                $bg_mobile_quality = $mobile_quality;
            }
            
            $lazy_loading = isset($_POST['life_travel_background_lazy_loading']) ? 'on' : 'off';
            update_option('life_travel_background_lazy_loading', $lazy_loading);
            $bg_lazy_loading = $lazy_loading;
            
            echo '<div class="updated"><p>' . esc_html__('Paramètres des arrière-plans enregistrés avec succès.', 'life-travel-excursion') . '</p></div>';
        }
        
        // Afficher l'interface utilisateur
        ?>
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-intro">
                <h3><?php _e('Arrière-plans du site', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Configurez les arrière-plans des différentes sections du site.', 'life-travel-excursion'); ?></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('life_travel_save_backgrounds'); ?>
                
                <div class="life-travel-admin-field-group">
                    <div class="life-travel-admin-field">
                        <label for="life_travel_header_background">
                            <?php _e('Arrière-plan d\'en-tête', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Image de fond pour l\'en-tête du site.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-media-field">
                            <div class="life-travel-media-preview life-travel-background-preview">
                                <?php if ($header_bg_id) : ?>
                                    <?php echo wp_get_attachment_image($header_bg_id, 'medium'); ?>
                                <?php else : ?>
                                    <div class="life-travel-no-image"><?php _e('Aucune image', 'life-travel-excursion'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="life_travel_header_background" id="life_travel_header_background" value="<?php echo esc_attr($header_bg_id); ?>">
                            
                            <div class="life-travel-media-buttons">
                                <button type="button" class="button life-travel-select-media" data-target="life_travel_header_background">
                                    <?php _e('Sélectionner', 'life-travel-excursion'); ?>
                                </button>
                                
                                <?php if ($header_bg_id) : ?>
                                    <button type="button" class="button life-travel-remove-media" data-target="life_travel_header_background">
                                        <?php _e('Supprimer', 'life-travel-excursion'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <p class="description">
                                <?php _e('Image large de haute qualité, dimensions recommandées: 1920×400 pixels', 'life-travel-excursion'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_footer_background">
                            <?php _e('Arrière-plan de pied de page', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Image de fond pour le pied de page du site.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-media-field">
                            <div class="life-travel-media-preview life-travel-background-preview">
                                <?php if ($footer_bg_id) : ?>
                                    <?php echo wp_get_attachment_image($footer_bg_id, 'medium'); ?>
                                <?php else : ?>
                                    <div class="life-travel-no-image"><?php _e('Aucune image', 'life-travel-excursion'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="life_travel_footer_background" id="life_travel_footer_background" value="<?php echo esc_attr($footer_bg_id); ?>">
                            
                            <div class="life-travel-media-buttons">
                                <button type="button" class="button life-travel-select-media" data-target="life_travel_footer_background">
                                    <?php _e('Sélectionner', 'life-travel-excursion'); ?>
                                </button>
                                
                                <?php if ($footer_bg_id) : ?>
                                    <button type="button" class="button life-travel-remove-media" data-target="life_travel_footer_background">
                                        <?php _e('Supprimer', 'life-travel-excursion'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <p class="description">
                                <?php _e('Image large, dimensions recommandées: 1920×600 pixels', 'life-travel-excursion'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_home_background">
                            <?php _e('Arrière-plan page d\'accueil', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Grande image d\'accueil pour la page principale.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-media-field">
                            <div class="life-travel-media-preview life-travel-background-preview">
                                <?php if ($home_bg_id) : ?>
                                    <?php echo wp_get_attachment_image($home_bg_id, 'medium'); ?>
                                <?php else : ?>
                                    <div class="life-travel-no-image"><?php _e('Aucune image', 'life-travel-excursion'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="life_travel_home_background" id="life_travel_home_background" value="<?php echo esc_attr($home_bg_id); ?>">
                            
                            <div class="life-travel-media-buttons">
                                <button type="button" class="button life-travel-select-media" data-target="life_travel_home_background">
                                    <?php _e('Sélectionner', 'life-travel-excursion'); ?>
                                </button>
                                
                                <?php if ($home_bg_id) : ?>
                                    <button type="button" class="button life-travel-remove-media" data-target="life_travel_home_background">
                                        <?php _e('Supprimer', 'life-travel-excursion'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <p class="description">
                                <?php _e('Grande image haute qualité, dimensions recommandées: 1920×1080 pixels', 'life-travel-excursion'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_excursion_default_background">
                            <?php _e('Arrière-plan par défaut des excursions', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Image par défaut pour les excursions sans image personnalisée.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-media-field">
                            <div class="life-travel-media-preview life-travel-background-preview">
                                <?php if ($excursion_default_bg_id) : ?>
                                    <?php echo wp_get_attachment_image($excursion_default_bg_id, 'medium'); ?>
                                <?php else : ?>
                                    <div class="life-travel-no-image"><?php _e('Aucune image', 'life-travel-excursion'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="life_travel_excursion_default_background" id="life_travel_excursion_default_background" value="<?php echo esc_attr($excursion_default_bg_id); ?>">
                            
                            <div class="life-travel-media-buttons">
                                <button type="button" class="button life-travel-select-media" data-target="life_travel_excursion_default_background">
                                    <?php _e('Sélectionner', 'life-travel-excursion'); ?>
                                </button>
                                
                                <?php if ($excursion_default_bg_id) : ?>
                                    <button type="button" class="button life-travel-remove-media" data-target="life_travel_excursion_default_background">
                                        <?php _e('Supprimer', 'life-travel-excursion'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <p class="description">
                                <?php _e('Image représentant au mieux vos excursions, dimensions recommandées: 1200×800 pixels', 'life-travel-excursion'); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="life-travel-admin-field-group">
                    <h4><?php _e('Paramètres d\'optimisation', 'life-travel-excursion'); ?></h4>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_background_overlay_opacity">
                            <?php _e('Opacité du calque de superposition', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('L\'opacité du calque sombre superposé aux arrière-plans pour améliorer la lisibilité du texte.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-range-field">
                            <input type="range" name="life_travel_background_overlay_opacity" id="life_travel_background_overlay_opacity" min="0" max="100" value="<?php echo esc_attr($bg_overlay_opacity); ?>">
                            <span class="life-travel-range-value"><?php echo esc_html($bg_overlay_opacity); ?>%</span>
                        </div>
                        
                        <p class="description">
                            <?php _e('0% = aucun calque, 100% = complètement opaque', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_background_quality">
                            <?php _e('Qualité des arrière-plans (ordinateur)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Qualité de compression des images d\'arrière-plan pour les ordinateurs de bureau.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-range-field">
                            <input type="range" name="life_travel_background_quality" id="life_travel_background_quality" min="60" max="100" value="<?php echo esc_attr($bg_quality); ?>">
                            <span class="life-travel-range-value"><?php echo esc_html($bg_quality); ?>%</span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Pour de meilleures performances, réduisez pour les connexions lentes', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_background_mobile_quality">
                            <?php _e('Qualité des arrière-plans (mobile)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Qualité de compression des images d\'arrière-plan pour les appareils mobiles.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-range-field">
                            <input type="range" name="life_travel_background_mobile_quality" id="life_travel_background_mobile_quality" min="40" max="90" value="<?php echo esc_attr($bg_mobile_quality); ?>">
                            <span class="life-travel-range-value"><?php echo esc_html($bg_mobile_quality); ?>%</span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Réduisez davantage pour optimiser la vitesse sur mobile', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label>
                            <?php _e('Chargement différé (lazy loading)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Charge les images d\'arrière-plan uniquement quand elles deviennent visibles à l\'écran.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-toggle-field">
                            <label class="life-travel-toggle">
                                <input type="checkbox" name="life_travel_background_lazy_loading" <?php checked($bg_lazy_loading, 'on'); ?>>
                                <span class="life-travel-toggle-slider"></span>
                            </label>
                            <span class="life-travel-toggle-label"><?php _e('Activer le chargement différé', 'life-travel-excursion'); ?></span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Recommandé pour améliorer les performances et la vitesse de chargement initiale', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="life-travel-admin-submit">
                    <input type="submit" name="life_travel_save_backgrounds" class="button button-primary" value="<?php esc_attr_e('Enregistrer les modifications', 'life-travel-excursion'); ?>">
                </div>
            </form>
            
            <div class="life-travel-admin-tips">
                <h4><?php _e('Conseils pour les arrière-plans', 'life-travel-excursion'); ?></h4>
                <ul>
                    <li><?php _e('Utilisez des images de haute qualité mais optimisées (max 500KB)', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Privilégiez des images avec un bon contraste pour la superposition de texte', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Pour améliorer la vitesse de chargement au Cameroun, utilisez des paramètres de compression adaptés', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Mise à jour dynamique des valeurs des curseurs
            $('.life-travel-range-field input[type="range"]').on('input', function() {
                $(this).next('.life-travel-range-value').text($(this).val() + '%');
            });
            
            // Prévisualisation des paramètres
            $('#life_travel_background_overlay_opacity').on('input', function() {
                var opacity = $(this).val() / 100;
                $('.life-travel-background-preview').css('--overlay-opacity', opacity);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Affiche l'interface de gestion des galeries d'images
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_media_gallery($page_id, $section_id) {
        // Récupérer les paramètres actuels
        $excursion_gallery_limit = get_option('life_travel_excursion_gallery_limit', 10);
        $gallery_quality = get_option('life_travel_gallery_quality', 85);
        $gallery_thumbnail_size = get_option('life_travel_gallery_thumbnail_size', 'medium');
        $gallery_lightbox = get_option('life_travel_gallery_lightbox', 'on');
        $gallery_lazy_loading = get_option('life_travel_gallery_lazy_loading', 'on');
        
        // Traitement du formulaire
        if (isset($_POST['life_travel_save_gallery_settings']) && check_admin_referer('life_travel_save_gallery_settings')) {
            if (isset($_POST['life_travel_excursion_gallery_limit'])) {
                $limit = intval($_POST['life_travel_excursion_gallery_limit']);
                $limit = max(3, min(30, $limit)); // Limiter entre 3 et 30
                update_option('life_travel_excursion_gallery_limit', $limit);
                $excursion_gallery_limit = $limit;
            }
            
            if (isset($_POST['life_travel_gallery_quality'])) {
                $quality = intval($_POST['life_travel_gallery_quality']);
                $quality = max(60, min(100, $quality)); // Limiter entre 60 et 100
                update_option('life_travel_gallery_quality', $quality);
                $gallery_quality = $quality;
            }
            
            if (isset($_POST['life_travel_gallery_thumbnail_size'])) {
                $thumbnail_size = sanitize_text_field($_POST['life_travel_gallery_thumbnail_size']);
                // Vérifier que c'est une taille valide
                $valid_sizes = array('thumbnail', 'medium', 'large');
                $thumbnail_size = in_array($thumbnail_size, $valid_sizes) ? $thumbnail_size : 'medium';
                update_option('life_travel_gallery_thumbnail_size', $thumbnail_size);
                $gallery_thumbnail_size = $thumbnail_size;
            }
            
            $lightbox = isset($_POST['life_travel_gallery_lightbox']) ? 'on' : 'off';
            update_option('life_travel_gallery_lightbox', $lightbox);
            $gallery_lightbox = $lightbox;
            
            $lazy_loading = isset($_POST['life_travel_gallery_lazy_loading']) ? 'on' : 'off';
            update_option('life_travel_gallery_lazy_loading', $lazy_loading);
            $gallery_lazy_loading = $lazy_loading;
            
            echo '<div class="updated"><p>' . esc_html__('Paramètres des galeries enregistrés avec succès.', 'life-travel-excursion') . '</p></div>';
        }
        
        // Afficher l'interface utilisateur
        ?>
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-intro">
                <h3><?php _e('Gestion des galeries d\'images', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Configurez les paramètres pour les galeries d\'images et les diaporamas du site.', 'life-travel-excursion'); ?></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('life_travel_save_gallery_settings'); ?>
                
                <div class="life-travel-admin-field-group">
                    <h4><?php _e('Paramètres généraux des galeries', 'life-travel-excursion'); ?></h4>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_excursion_gallery_limit">
                            <?php _e('Nombre maximum d\'images par excursion', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Nombre maximum d\'images que les administrateurs peuvent ajouter à une excursion.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-number-field">
                            <input type="number" name="life_travel_excursion_gallery_limit" id="life_travel_excursion_gallery_limit" 
                                   min="3" max="30" value="<?php echo esc_attr($excursion_gallery_limit); ?>">
                        </div>
                        
                        <p class="description">
                            <?php _e('Une valeur élevée peut affecter les performances de chargement', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_gallery_quality">
                            <?php _e('Qualité des images de galerie', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Qualité des images dans les galeries. Une qualité plus faible réduit la taille des fichiers.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-range-field">
                            <input type="range" name="life_travel_gallery_quality" id="life_travel_gallery_quality" 
                                   min="60" max="100" value="<?php echo esc_attr($gallery_quality); ?>">
                            <span class="life-travel-range-value"><?php echo esc_html($gallery_quality); ?>%</span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Pour les connexions lentes au Cameroun, une valeur de 70-85% est recommandée', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_gallery_thumbnail_size">
                            <?php _e('Taille des miniatures', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Taille des miniatures dans les galeries et listes d\'excursions.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-select-field">
                            <select name="life_travel_gallery_thumbnail_size" id="life_travel_gallery_thumbnail_size">
                                <option value="thumbnail" <?php selected($gallery_thumbnail_size, 'thumbnail'); ?>><?php _e('Petite (150×150)', 'life-travel-excursion'); ?></option>
                                <option value="medium" <?php selected($gallery_thumbnail_size, 'medium'); ?>><?php _e('Moyenne (300×300)', 'life-travel-excursion'); ?></option>
                                <option value="large" <?php selected($gallery_thumbnail_size, 'large'); ?>><?php _e('Grande (600×600)', 'life-travel-excursion'); ?></option>
                            </select>
                        </div>
                        
                        <p class="description">
                            <?php _e('Pour les connexions lentes, choisissez une taille plus petite', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label>
                            <?php _e('Visionneuse Lightbox', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Affiche les images en plein écran lorsque l\'utilisateur clique dessus.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-toggle-field">
                            <label class="life-travel-toggle">
                                <input type="checkbox" name="life_travel_gallery_lightbox" <?php checked($gallery_lightbox, 'on'); ?>>
                                <span class="life-travel-toggle-slider"></span>
                            </label>
                            <span class="life-travel-toggle-label"><?php _e('Activer la visionneuse Lightbox', 'life-travel-excursion'); ?></span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Permet aux visiteurs de voir les images en grand format', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label>
                            <?php _e('Chargement différé (lazy loading)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Charge les images uniquement lorsqu\'elles sont visibles à l\'écran.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-toggle-field">
                            <label class="life-travel-toggle">
                                <input type="checkbox" name="life_travel_gallery_lazy_loading" <?php checked($gallery_lazy_loading, 'on'); ?>>
                                <span class="life-travel-toggle-slider"></span>
                            </label>
                            <span class="life-travel-toggle-label"><?php _e('Activer le chargement différé', 'life-travel-excursion'); ?></span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Améliore considérablement les performances, surtout pour les connexions lentes', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="life-travel-admin-preview-gallery">
                    <h4><?php _e('Aperçu des styles de galerie', 'life-travel-excursion'); ?></h4>
                    <div class="life-travel-gallery-preview-container">
                        <div class="life-travel-gallery-style life-travel-gallery-grid">
                            <h5><?php _e('Grille', 'life-travel-excursion'); ?></h5>
                            <div class="preview-box"></div>
                            <div class="preview-box"></div>
                            <div class="preview-box"></div>
                            <div class="preview-box"></div>
                        </div>
                        
                        <div class="life-travel-gallery-style life-travel-gallery-masonry">
                            <h5><?php _e('Mosaïque', 'life-travel-excursion'); ?></h5>
                            <div class="preview-box preview-tall"></div>
                            <div class="preview-box"></div>
                            <div class="preview-box preview-wide"></div>
                            <div class="preview-box"></div>
                        </div>
                        
                        <div class="life-travel-gallery-style life-travel-gallery-carousel">
                            <h5><?php _e('Carrousel', 'life-travel-excursion'); ?></h5>
                            <div class="preview-carousel">
                                <div class="preview-box active"></div>
                                <div class="preview-dot active"></div>
                                <div class="preview-dot"></div>
                                <div class="preview-dot"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="life-travel-admin-submit">
                    <input type="submit" name="life_travel_save_gallery_settings" class="button button-primary" value="<?php esc_attr_e('Enregistrer les modifications', 'life-travel-excursion'); ?>">
                </div>
            </form>
            
            <div class="life-travel-admin-tips">
                <h4><?php _e('Conseils pour les galeries d\'images', 'life-travel-excursion'); ?></h4>
                <ul>
                    <li><?php _e('Optimisez vos images avant de les téléverser (compression, dimensions adaptées)', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Utilisez des dimensions d\'image constantes pour maintenir un affichage uniforme', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Pour les connexions lentes au Cameroun, privilégiez des images moins nombreuses mais de meilleure qualité', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Le chargement différé améliore considérablement l\'expérience sur les réseaux mobiles', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Mise à jour dynamique de la valeur du curseur de qualité
            $('#life_travel_gallery_quality').on('input', function() {
                $(this).next('.life-travel-range-value').text($(this).val() + '%');
            });
            
            // Aperçu du style de galerie sélectionné
            $('input[name="life_travel_gallery_style"]').on('change', function() {
                var selectedStyle = $(this).val();
                $('.life-travel-gallery-style').removeClass('active');
                $('.life-travel-gallery-' + selectedStyle).addClass('active');
            });
        });
        </script>
        <?php
    }
}