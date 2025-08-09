<?php
/**
 * Vue des paramètres pour les paniers abandonnés
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

/**
 * Classe pour l'affichage de la page des paramètres des paniers abandonnés
 */
class Life_Travel_Abandoned_Cart_Settings_View {
    
    /**
     * Affiche la vue des paramètres
     */
    public static function render_settings() {
        // Récupérer les paramètres actuels
        $settings = get_option('life_travel_abandoned_cart_settings', array());
        
        // Valeurs par défaut
        $defaults = array(
            'enable_recovery' => 'yes',
            'recovery_wait_time' => 60, // Minutes
            'send_email_automatically' => 'yes',
            'email_template' => 'default',
            'email_subject' => __('Votre panier vous attend', 'life-travel-excursion'),
            'recovery_link_expiry' => 7, // Jours
            'max_recovery_emails' => 3,
            'email_interval' => 24, // Heures
            'enable_security_logging' => 'yes',
            'connection_retry_limit' => 3,
            'offline_storage_days' => 14,
            'validate_participants_data' => 'yes',
            'secure_storage_method' => 'encrypted'
        );
        
        // Fusionner avec les valeurs par défaut
        $settings = wp_parse_args($settings, $defaults);
        
        echo '<div class="wrap life-travel-cart-dashboard">';
        echo '<div class="life-travel-header">';
        echo '<h1 class="life-travel-title">' . esc_html__('Paramètres des paniers abandonnés', 'life-travel-excursion') . '</h1>';
        echo '</div>';
        
        echo '<form method="post" action="" class="life-travel-settings-form">';
        wp_nonce_field('life_travel_cart_settings');
        
        // Onglets des paramètres
        echo '<div class="nav-tab-wrapper">';
        echo '<a href="#general" class="nav-tab nav-tab-active">' . esc_html__('Général', 'life-travel-excursion') . '</a>';
        echo '<a href="#emails" class="nav-tab">' . esc_html__('Emails', 'life-travel-excursion') . '</a>';
        echo '<a href="#security" class="nav-tab">' . esc_html__('Sécurité', 'life-travel-excursion') . '</a>';
        echo '<a href="#offline" class="nav-tab">' . esc_html__('Mode hors ligne', 'life-travel-excursion') . '</a>';
        echo '</div>';
        
        // Section générale
        echo '<div id="general" class="settings-tab active">';
        echo '<table class="form-table">';
        
        // Activer la récupération
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Activer la récupération', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="settings[enable_recovery]" value="yes" ' . checked($settings['enable_recovery'], 'yes', false) . '> ' . esc_html__('Activer le système de récupération des paniers abandonnés', 'life-travel-excursion') . '</label>';
        echo '<p class="description">' . esc_html__('Lorsque cette option est activée, le système suivra les paniers abandonnés.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Temps d'attente avant de considérer un panier comme abandonné
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Temps d\'attente', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<input type="number" name="settings[recovery_wait_time]" value="' . esc_attr($settings['recovery_wait_time']) . '" min="15" max="1440" class="small-text"> ' . esc_html__('minutes', 'life-travel-excursion');
        echo '<p class="description">' . esc_html__('Temps d\'attente avant de considérer un panier comme abandonné.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Valider les données des participants
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Validation des participants', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="settings[validate_participants_data]" value="yes" ' . checked($settings['validate_participants_data'], 'yes', false) . '> ' . esc_html__('Valider les données des participants aux excursions', 'life-travel-excursion') . '</label>';
        echo '<p class="description">' . esc_html__('Active la validation complète des données de participants dans les paniers abandonnés (noms, âges, etc.).', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</div>'; // #general
        
        // Section emails
        echo '<div id="emails" class="settings-tab" style="display:none;">';
        echo '<table class="form-table">';
        
        // Envoi automatique d'emails
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Emails automatiques', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="settings[send_email_automatically]" value="yes" ' . checked($settings['send_email_automatically'], 'yes', false) . '> ' . esc_html__('Envoyer automatiquement des emails de récupération', 'life-travel-excursion') . '</label>';
        echo '<p class="description">' . esc_html__('Lorsque cette option est activée, le système enverra automatiquement des emails de récupération.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Modèle d'email
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Modèle d\'email', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<select name="settings[email_template]" id="life-travel-email-template-select">';
        echo '<option value="default" ' . selected($settings['email_template'], 'default', false) . '>' . esc_html__('Modèle par défaut', 'life-travel-excursion') . '</option>';
        echo '<option value="security-focused" ' . selected($settings['email_template'], 'security-focused', false) . '>' . esc_html__('Axé sur la sécurité', 'life-travel-excursion') . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__('Choisissez le modèle d\'email à utiliser pour les emails de récupération.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Sujet de l'email
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Sujet de l\'email', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<input type="text" name="settings[email_subject]" value="' . esc_attr($settings['email_subject']) . '" class="regular-text">';
        echo '</td>';
        echo '</tr>';
        
        // Expiration du lien de récupération
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Expiration du lien', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<input type="number" name="settings[recovery_link_expiry]" value="' . esc_attr($settings['recovery_link_expiry']) . '" min="1" max="30" class="small-text"> ' . esc_html__('jours', 'life-travel-excursion');
        echo '<p class="description">' . esc_html__('Nombre de jours après lesquels le lien de récupération expire.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Nombre maximum d'emails
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Emails maximum', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<input type="number" name="settings[max_recovery_emails]" value="' . esc_attr($settings['max_recovery_emails']) . '" min="1" max="10" class="small-text">';
        echo '<p class="description">' . esc_html__('Nombre maximum d\'emails de récupération à envoyer pour un panier abandonné.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Intervalle entre les emails
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Intervalle entre les emails', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<input type="number" name="settings[email_interval]" value="' . esc_attr($settings['email_interval']) . '" min="1" max="72" class="small-text"> ' . esc_html__('heures', 'life-travel-excursion');
        echo '<p class="description">' . esc_html__('Intervalle minimum entre deux emails de récupération.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        // Prévisualisation du modèle d'email
        echo '<div class="life-travel-email-template-editor">';
        
        echo '<h3>' . esc_html__('Variables disponibles', 'life-travel-excursion') . '</h3>';
        echo '<div class="life-travel-email-variables">';
        echo '<ul>';
        echo '<li><code>{site_name}</code> - ' . esc_html__('Nom du site', 'life-travel-excursion') . '</li>';
        echo '<li><code>{customer_first_name}</code> - ' . esc_html__('Prénom du client', 'life-travel-excursion') . '</li>';
        echo '<li><code>{customer_last_name}</code> - ' . esc_html__('Nom de famille du client', 'life-travel-excursion') . '</li>';
        echo '<li><code>{cart_items}</code> - ' . esc_html__('Contenu du panier', 'life-travel-excursion') . '</li>';
        echo '<li><code>{cart_total}</code> - ' . esc_html__('Total du panier', 'life-travel-excursion') . '</li>';
        echo '<li><code>{recovery_link}</code> - ' . esc_html__('Lien de récupération', 'life-travel-excursion') . '</li>';
        echo '<li><code>{expiry_days}</code> - ' . esc_html__('Jours avant expiration', 'life-travel-excursion') . '</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</div>'; // .life-travel-email-template-editor
        echo '</div>'; // #emails
        
        // Section sécurité
        echo '<div id="security" class="settings-tab" style="display:none;">';
        echo '<table class="form-table">';
        
        // Activer la journalisation de sécurité
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Journalisation de sécurité', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="settings[enable_security_logging]" value="yes" ' . checked($settings['enable_security_logging'], 'yes', false) . '> ' . esc_html__('Activer la journalisation avancée de sécurité', 'life-travel-excursion') . '</label>';
        echo '<p class="description">' . esc_html__('Enregistre les événements de sécurité liés aux paniers abandonnés pour analyse ultérieure.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Méthode de stockage sécurisé
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Stockage sécurisé', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<select name="settings[secure_storage_method]">';
        echo '<option value="standard" ' . selected($settings['secure_storage_method'], 'standard', false) . '>' . esc_html__('Standard', 'life-travel-excursion') . '</option>';
        echo '<option value="encrypted" ' . selected($settings['secure_storage_method'], 'encrypted', false) . '>' . esc_html__('Chiffré', 'life-travel-excursion') . '</option>';
        echo '<option value="anonymized" ' . selected($settings['secure_storage_method'], 'anonymized', false) . '>' . esc_html__('Anonymisé', 'life-travel-excursion') . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__('Méthode de stockage des données de paniers abandonnés. Le chiffrement ajoute une couche de sécurité supplémentaire.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Rapport de sécurité
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Rapport de sécurité', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=' . $_GET['page'] . '&action=security_report'), 'life_travel_security_report')) . '" class="button">';
        echo esc_html__('Générer un rapport de sécurité', 'life-travel-excursion');
        echo '</a>';
        echo '<p class="description">' . esc_html__('Génère un rapport détaillé sur la sécurité du système de paniers abandonnés.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</div>'; // #security
        
        // Section mode hors ligne
        echo '<div id="offline" class="settings-tab" style="display:none;">';
        echo '<table class="form-table">';
        
        // Limite de tentatives de reconnexion
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Tentatives de reconnexion', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<input type="number" name="settings[connection_retry_limit]" value="' . esc_attr($settings['connection_retry_limit']) . '" min="1" max="10" class="small-text">';
        echo '<p class="description">' . esc_html__('Nombre maximum de tentatives de reconnexion automatique lors de problèmes réseau.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Durée de conservation des données hors ligne
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Conservation des données hors ligne', 'life-travel-excursion') . '</th>';
        echo '<td>';
        echo '<input type="number" name="settings[offline_storage_days]" value="' . esc_attr($settings['offline_storage_days']) . '" min="1" max="90" class="small-text"> ' . esc_html__('jours', 'life-travel-excursion');
        echo '<p class="description">' . esc_html__('Durée de conservation des données de panier en mode hors ligne avant suppression automatique.', 'life-travel-excursion') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</div>'; // #offline
        
        // Bouton d'enregistrement
        echo '<p class="submit">';
        echo '<input type="submit" name="life_travel_save_cart_settings" class="button button-primary" value="' . esc_attr__('Enregistrer les paramètres', 'life-travel-excursion') . '">';
        echo '</p>';
        
        echo '</form>';
        
        // Script pour les onglets
        echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                // Gestion des onglets
                $(".nav-tab").on("click", function(e) {
                    e.preventDefault();
                    
                    // Activer l\'onglet sélectionné
                    $(".nav-tab").removeClass("nav-tab-active");
                    $(this).addClass("nav-tab-active");
                    
                    // Afficher le contenu correspondant
                    var target = $(this).attr("href");
                    $(".settings-tab").hide();
                    $(target).show();
                });
            });
        </script>';
        
        echo '</div>'; // .wrap
    }
    
    /**
     * Génère une liste des modèles d'email disponibles
     * 
     * @return array Liste des modèles disponibles
     */
    public static function get_available_email_templates() {
        $templates_dir = LIFE_TRAVEL_EXCURSION_DIR . 'templates/emails/';
        $templates = array();
        
        // Ajouter le modèle par défaut
        $templates['default'] = __('Modèle par défaut', 'life-travel-excursion');
        
        // Scanner le répertoire des modèles
        if (is_dir($templates_dir)) {
            $files = scandir($templates_dir);
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === 'default.php') {
                    continue;
                }
                
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $template_id = pathinfo($file, PATHINFO_FILENAME);
                    $template_name = ucwords(str_replace('-', ' ', $template_id));
                    $templates[$template_id] = $template_name;
                }
            }
        }
        
        return $templates;
    }
}
