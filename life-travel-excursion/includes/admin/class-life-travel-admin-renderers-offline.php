<?php
/**
 * Life Travel Admin Renderers Offline Messages
 * 
 * Ce fichier contient le trait qui ajoute une interface d'administration
 * pour la gestion des messages hors ligne.
 *
 * @package Life Travel Excursion
 * @since 2.4.0
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Trait Life_Travel_Admin_Renderers_Offline
 */
trait Life_Travel_Admin_Renderers_Offline {

    /**
     * Affiche l'interface de personnalisation des messages hors ligne
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_offline_messages($page_id, $section_id) {
        // Récupérer l'instance de gestion des messages hors ligne
        $offline_messages = life_travel_offline_messages();
        
        // Récupérer tous les messages (par défaut + personnalisés)
        $messages = $offline_messages->get_all_messages();
        ?>
        <div class="life-travel-admin-intro">
            <h3><?php _e('Personnalisation des messages hors ligne', 'life-travel-excursion'); ?></h3>
            <p><?php _e('Personnalisez les messages affichés aux utilisateurs lorsqu\'ils tentent d\'effectuer des actions en mode hors ligne.', 'life-travel-excursion'); ?></p>
        </div>
        
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-info-box">
                <div class="life-travel-admin-info-icon">
                    <span class="dashicons dashicons-info"></span>
                </div>
                <div class="life-travel-admin-info-content">
                    <h4><?php _e('À propos du mode hors ligne', 'life-travel-excursion'); ?></h4>
                    <p><?php _e('Le mode hors ligne permet aux utilisateurs de naviguer sur votre site même sans connexion Internet, grâce au Service Worker et au cache du navigateur.', 'life-travel-excursion'); ?></p>
                    <p><?php _e('Les messages ci-dessous seront affichés lorsque les utilisateurs tentent d\'effectuer des actions qui nécessitent une connexion Internet.', 'life-travel-excursion'); ?></p>
                    <p><?php _e('Personnalisez ces messages pour offrir une expérience claire et adaptée à votre audience camerounaise, en tenant compte des spécificités locales et du vocabulaire familier.', 'life-travel-excursion'); ?></p>
                </div>
            </div>
            
            <form id="life-travel-offline-messages-form" method="post">
                <?php wp_nonce_field('life_travel_offline_messages_nonce', 'nonce'); ?>
                
                <h4><?php _e('Messages par type d\'action', 'life-travel-excursion'); ?></h4>
                
                <div class="life-travel-admin-tabs">
                    <div class="life-travel-admin-tabs-nav">
                        <?php
                        $first = true;
                        foreach ($messages as $type => $message_data) :
                            $label = '';
                            switch ($type) {
                                case 'reservation':
                                    $label = __('Réservation', 'life-travel-excursion');
                                    break;
                                case 'payment':
                                    $label = __('Paiement', 'life-travel-excursion');
                                    break;
                                case 'contact':
                                    $label = __('Contact', 'life-travel-excursion');
                                    break;
                                case 'cart_add':
                                    $label = __('Ajout au panier', 'life-travel-excursion');
                                    break;
                                case 'synchronization':
                                    $label = __('Synchronisation', 'life-travel-excursion');
                                    break;
                                case 'general':
                                    $label = __('Général', 'life-travel-excursion');
                                    break;
                            }
                            ?>
                            <a href="#offline-tab-<?php echo esc_attr($type); ?>" class="life-travel-admin-tab <?php echo $first ? 'active' : ''; ?>">
                                <?php echo esc_html($label); ?>
                            </a>
                            <?php
                            $first = false;
                        endforeach;
                        ?>
                    </div>
                    
                    <div class="life-travel-admin-tabs-content">
                        <?php
                        $first = true;
                        foreach ($messages as $type => $message_data) :
                            ?>
                            <div id="offline-tab-<?php echo esc_attr($type); ?>" class="life-travel-admin-tab-content <?php echo $first ? 'active' : ''; ?>">
                                <div class="life-travel-admin-field-group">
                                    <div class="life-travel-admin-field">
                                        <label for="life_travel_offline_<?php echo esc_attr($type); ?>_title"><?php _e('Titre', 'life-travel-excursion'); ?></label>
                                        <input type="text" id="life_travel_offline_<?php echo esc_attr($type); ?>_title" name="life_travel_offline_<?php echo esc_attr($type); ?>_title" value="<?php echo esc_attr($message_data['title']); ?>" class="regular-text">
                                        <p class="description"><?php _e('Le titre du message affiché à l\'utilisateur.', 'life-travel-excursion'); ?></p>
                                    </div>
                                    
                                    <div class="life-travel-admin-field">
                                        <label for="life_travel_offline_<?php echo esc_attr($type); ?>_message"><?php _e('Message', 'life-travel-excursion'); ?></label>
                                        <textarea id="life_travel_offline_<?php echo esc_attr($type); ?>_message" name="life_travel_offline_<?php echo esc_attr($type); ?>_message" rows="4" class="regular-text"><?php echo esc_textarea($message_data['message']); ?></textarea>
                                        <p class="description"><?php _e('Le message détaillé expliquant la situation à l\'utilisateur. Utilisez un langage simple et précis.', 'life-travel-excursion'); ?></p>
                                    </div>
                                    
                                    <div class="life-travel-admin-field">
                                        <label for="life_travel_offline_<?php echo esc_attr($type); ?>_action_text"><?php _e('Texte du bouton', 'life-travel-excursion'); ?></label>
                                        <input type="text" id="life_travel_offline_<?php echo esc_attr($type); ?>_action_text" name="life_travel_offline_<?php echo esc_attr($type); ?>_action_text" value="<?php echo esc_attr($message_data['action_text']); ?>" class="regular-text">
                                        <p class="description"><?php _e('Le texte affiché sur le bouton d\'action.', 'life-travel-excursion'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="life-travel-admin-preview">
                                    <h4><?php _e('Aperçu', 'life-travel-excursion'); ?></h4>
                                    <div class="life-travel-admin-preview-box">
                                        <div class="life-travel-offline-notification-preview">
                                            <div class="life-travel-offline-notification-inner">
                                                <div class="life-travel-offline-notification-icon icon-<?php echo esc_attr($message_data['icon']); ?>">
                                                    <span class="dashicons"></span>
                                                </div>
                                                <div class="life-travel-offline-notification-content">
                                                    <h3 class="life-travel-offline-notification-title"><?php echo esc_html($message_data['title']); ?></h3>
                                                    <div class="life-travel-offline-notification-message"><?php echo esc_html($message_data['message']); ?></div>
                                                    <div class="life-travel-offline-notification-actions">
                                                        <button class="life-travel-offline-notification-button"><?php echo esc_html($message_data['action_text']); ?></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="description"><?php _e('Cet aperçu vous montre comment le message apparaîtra aux utilisateurs. Le style final peut varier légèrement en fonction du thème.', 'life-travel-excursion'); ?></p>
                                </div>
                                
                                <?php if ($type === 'general') : ?>
                                <div class="life-travel-admin-tips">
                                    <h4><?php _e('Conseils de rédaction pour le contexte camerounais', 'life-travel-excursion'); ?></h4>
                                    <ul>
                                        <li><?php _e('Utilisez un vocabulaire simple et accessible, évitez le jargon technique.', 'life-travel-excursion'); ?></li>
                                        <li><?php _e('Mentionnez explicitement les limitations du mode hors ligne pour éviter toute confusion.', 'life-travel-excursion'); ?></li>
                                        <li><?php _e('Pour les paiements, précisez que les méthodes locales (Mobile Money, Orange Money) nécessitent une connexion.', 'life-travel-excursion'); ?></li>
                                        <li><?php _e('Adaptez les messages à la réalité des connexions variables au Cameroun.', 'life-travel-excursion'); ?></li>
                                        <li><?php _e('Rassurez l\'utilisateur sur la sécurité de ses données et la reprise ultérieure de ses actions.', 'life-travel-excursion'); ?></li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php
                            $first = false;
                        endforeach;
                        ?>
                    </div>
                </div>
                
                <p><button type="submit" id="save-offline-messages" class="button button-primary"><?php _e('Enregistrer les messages', 'life-travel-excursion'); ?></button></p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Gestion des onglets
            $('.life-travel-admin-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                // Désactiver tous les onglets et contenus
                $('.life-travel-admin-tab').removeClass('active');
                $('.life-travel-admin-tab-content').removeClass('active');
                
                // Activer l'onglet et le contenu sélectionnés
                $(this).addClass('active');
                $(target).addClass('active');
            });
            
            // Mise à jour de l'aperçu en temps réel
            function updatePreview(type) {
                var title = $('#life_travel_offline_' + type + '_title').val();
                var message = $('#life_travel_offline_' + type + '_message').val();
                var actionText = $('#life_travel_offline_' + type + '_action_text').val();
                
                var $preview = $('#offline-tab-' + type + ' .life-travel-offline-notification-preview');
                
                $preview.find('.life-travel-offline-notification-title').text(title);
                $preview.find('.life-travel-offline-notification-message').text(message);
                $preview.find('.life-travel-offline-notification-button').text(actionText);
            }
            
            // Écouter les changements dans les champs
            $('input[id^="life_travel_offline_"], textarea[id^="life_travel_offline_"]').on('input', function() {
                var id = $(this).attr('id');
                var type = id.match(/life_travel_offline_([^_]+)_/)[1];
                
                updatePreview(type);
            });
            
            // Gestion de la soumission du formulaire via AJAX
            $('#life-travel-offline-messages-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submitButton = $('#save-offline-messages');
                var formData = $form.serialize();
                
                $submitButton.prop('disabled', true).text('<?php _e('Enregistrement en cours...', 'life-travel-excursion'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'life_travel_save_offline_messages',
                        ...Object.fromEntries(new FormData($form[0]))
                    },
                    success: function(response) {
                        $submitButton.prop('disabled', false).text('<?php _e('Enregistrer les messages', 'life-travel-excursion'); ?>');
                        
                        if (response.success) {
                            // Ajouter une notification de succès
                            var $notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                            $form.before($notice);
                            
                            // Supprimer la notification après 3 secondes
                            setTimeout(function() {
                                $notice.fadeOut(function() {
                                    $(this).remove();
                                });
                            }, 3000);
                        } else {
                            // Ajouter une notification d'erreur
                            var $notice = $('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
                            $form.before($notice);
                        }
                    },
                    error: function() {
                        $submitButton.prop('disabled', false).text('<?php _e('Enregistrer les messages', 'life-travel-excursion'); ?>');
                        
                        // Ajouter une notification d'erreur
                        var $notice = $('<div class="notice notice-error is-dismissible"><p><?php _e('Une erreur est survenue lors de l\'enregistrement des messages.', 'life-travel-excursion'); ?></p></div>');
                        $form.before($notice);
                    }
                });
            });
        });
        </script>
        <?php
    }
}
