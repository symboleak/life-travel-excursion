<?php
/**
 * Template pour l'éditeur de modèles de notification
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

// S'assurer que l'utilisateur a les permissions nécessaires
if (!current_user_can('manage_options')) {
    wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', 'life-travel-excursion'));
}
?>

<div class="wrap lte-template-editor-wrap">
    <h1><?php esc_html_e('Éditeur de modèles de notification', 'life-travel-excursion'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php esc_html_e('Personnalisez les modèles de notification pour les différents canaux de communication. Vous pouvez utiliser des variables pour rendre vos messages dynamiques.', 'life-travel-excursion'); ?></p>
    </div>
    
    <div class="lte-template-selector">
        <form method="get">
            <input type="hidden" name="page" value="lte-notification-templates">
            
            <div class="lte-selector-group">
                <label for="type"><?php esc_html_e('Type de notification:', 'life-travel-excursion'); ?></label>
                <select name="type" id="type">
                    <?php foreach ($notification_types as $type_id => $type) : ?>
                        <option value="<?php echo esc_attr($type_id); ?>" <?php selected($notification_type, $type_id); ?>>
                            <?php echo esc_html($type['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="lte-selector-group">
                <label for="channel"><?php esc_html_e('Canal:', 'life-travel-excursion'); ?></label>
                <select name="channel" id="channel">
                    <option value="subject" <?php selected($channel, 'subject'); ?>><?php esc_html_e('Sujet d\'email', 'life-travel-excursion'); ?></option>
                    <option value="email" <?php selected($channel, 'email'); ?>><?php esc_html_e('Email (HTML)', 'life-travel-excursion'); ?></option>
                    <option value="sms" <?php selected($channel, 'sms'); ?>><?php esc_html_e('SMS', 'life-travel-excursion'); ?></option>
                    <option value="whatsapp" <?php selected($channel, 'whatsapp'); ?>><?php esc_html_e('WhatsApp', 'life-travel-excursion'); ?></option>
                </select>
            </div>
            
            <button type="submit" class="button button-secondary"><?php esc_html_e('Charger le modèle', 'life-travel-excursion'); ?></button>
        </form>
    </div>
    
    <div class="lte-template-editor-container">
        <div class="lte-editor-main">
            <h2>
                <?php 
                echo sprintf(
                    esc_html__('Édition du modèle: %s - %s', 'life-travel-excursion'),
                    esc_html($notification_types[$notification_type]['name'] ?? $notification_type),
                    esc_html($channel === 'subject' ? __('Sujet d\'email', 'life-travel-excursion') : $notification_channels[$channel]['name'] ?? $channel)
                ); 
                ?>
            </h2>
            
            <form id="lte-template-form" method="post">
                <input type="hidden" name="notification_type" value="<?php echo esc_attr($notification_type); ?>">
                <input type="hidden" name="channel" value="<?php echo esc_attr($channel); ?>">
                
                <?php if ($is_subject) : ?>
                    <div class="lte-subject-editor">
                        <input type="text" id="template-content" name="template_content" 
                               value="<?php echo esc_attr($template_content); ?>" 
                               class="large-text" placeholder="<?php esc_attr_e('Sujet de l\'email', 'life-travel-excursion'); ?>">
                    </div>
                <?php else : ?>
                    <div class="lte-content-editor">
                        <textarea id="template-content" name="template_content" rows="20" class="large-text code" 
                                  data-editor-mode="<?php echo $channel === 'email' ? 'html' : 'text'; ?>"><?php echo esc_textarea($template_content); ?></textarea>
                    </div>
                <?php endif; ?>
                
                <div class="lte-editor-actions">
                    <button type="button" id="lte-preview-button" class="button button-secondary"><?php esc_html_e('Prévisualiser', 'life-travel-excursion'); ?></button>
                    <button type="button" id="lte-save-button" class="button button-primary"><?php esc_html_e('Enregistrer', 'life-travel-excursion'); ?></button>
                    <button type="button" id="lte-reset-button" class="button button-link-delete"><?php esc_html_e('Réinitialiser', 'life-travel-excursion'); ?></button>
                    <span id="lte-save-status"></span>
                </div>
            </form>
        </div>
        
        <div class="lte-editor-sidebar">
            <div class="lte-variables-panel">
                <h3><?php esc_html_e('Variables disponibles', 'life-travel-excursion'); ?></h3>
                <p class="description"><?php esc_html_e('Cliquez sur une variable pour l\'insérer dans votre modèle.', 'life-travel-excursion'); ?></p>
                
                <div class="lte-variables-container">
                    <?php foreach ($available_variables as $group_name => $variables) : ?>
                        <div class="lte-variables-group">
                            <h4 class="lte-variables-group-title"><?php echo esc_html(ucfirst($group_name)); ?></h4>
                            <div class="lte-variables-list">
                                <?php foreach ($variables as $var => $description) : ?>
                                    <div class="lte-variable-item" data-variable="<?php echo esc_attr($var); ?>">
                                        <code><?php echo esc_html($var); ?></code>
                                        <span class="lte-variable-description"><?php echo esc_html($description); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="lte-preview-panel" id="lte-preview-panel">
                <h3><?php esc_html_e('Prévisualisation', 'life-travel-excursion'); ?></h3>
                <div class="lte-preview-container">
                    <div id="lte-preview-content" class="lte-preview-content">
                        <div class="lte-preview-placeholder">
                            <?php esc_html_e('Cliquez sur "Prévisualiser" pour voir le rendu de votre modèle avec des données d\'exemple.', 'life-travel-excursion'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="lte-default-content" style="display: none;">
        <?php echo $is_subject ? esc_attr($default_content) : esc_textarea($default_content); ?>
    </div>
    
    <div class="lte-template-guide">
        <h3><?php esc_html_e('Guide de création de modèles', 'life-travel-excursion'); ?></h3>
        
        <div class="lte-guide-content">
            <div class="lte-guide-section">
                <h4><?php esc_html_e('Conseils pour les emails', 'life-travel-excursion'); ?></h4>
                <ul>
                    <li><?php esc_html_e('Utilisez un titre clair et concis.', 'life-travel-excursion'); ?></li>
                    <li><?php esc_html_e('Personnalisez le message avec le nom du client.', 'life-travel-excursion'); ?></li>
                    <li><?php esc_html_e('Incluez toujours un appel à l\'action clair.', 'life-travel-excursion'); ?></li>
                    <li><?php esc_html_e('Gardez le design simple pour une meilleure compatibilité.', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
            
            <div class="lte-guide-section">
                <h4><?php esc_html_e('Conseils pour les SMS et WhatsApp', 'life-travel-excursion'); ?></h4>
                <ul>
                    <li><?php esc_html_e('Soyez bref et direct.', 'life-travel-excursion'); ?></li>
                    <li><?php esc_html_e('Limitez l\'utilisation des variables aux plus importantes.', 'life-travel-excursion'); ?></li>
                    <li><?php esc_html_e('Incluez toujours le nom de votre entreprise.', 'life-travel-excursion'); ?></li>
                    <li><?php esc_html_e('Pour WhatsApp, vous pouvez utiliser des formatages simples comme *texte* pour le gras et _texte_ pour l\'italique.', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
