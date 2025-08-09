<?php
/**
 * Gestion des modèles de notification
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

/**
 * Classe de gestion des modèles de notification
 */
class Life_Travel_Notification_Templates {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Modèles de notification par défaut
     */
    private $default_templates = [];
    
    /**
     * Variables disponibles pour les modèles
     */
    private $available_variables = [];
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Initialiser les modèles par défaut
        $this->init_default_templates();
        
        // Initialiser les variables disponibles
        $this->init_available_variables();
        
        // Hooks pour l'admin
        add_action('admin_init', [$this, 'register_template_settings']);
        add_action('admin_menu', [$this, 'add_template_settings_page']);
        
        // AJAX pour la prévisualisation des modèles
        add_action('wp_ajax_lte_preview_notification_template', [$this, 'ajax_preview_template']);
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_Notification_Templates
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialise les modèles par défaut
     */
    private function init_default_templates() {
        // Modèles de notification par défaut
        $this->default_templates = [
            'order_confirmation' => [
                'subject' => __('Confirmation de votre réservation #{order_number}', 'life-travel-excursion'),
                'email' => $this->get_default_email_template('order_confirmation'),
                'sms' => __('Life Travel: Votre réservation #{order_number} pour {product_name} a été confirmée. Montant: {order_total}. Détails sur votre compte.', 'life-travel-excursion'),
                'whatsapp' => __("*Life Travel - Confirmation de réservation*\n\nBonjour {customer_name},\n\nVotre réservation #{order_number} pour {product_name} a été confirmée.\n\nMontant: {order_total}\nDate d'excursion: {excursion_date}\n\nConsultez les détails sur votre compte: {account_url}", 'life-travel-excursion')
            ],
            'order_status' => [
                'subject' => __('Mise à jour de votre réservation #{order_number}', 'life-travel-excursion'),
                'email' => $this->get_default_email_template('order_status'),
                'sms' => __('Life Travel: Le statut de votre réservation #{order_number} est passé à "{order_status}". Pour plus d\'informations, consultez votre compte.', 'life-travel-excursion'),
                'whatsapp' => __("*Life Travel - Mise à jour de réservation*\n\nBonjour {customer_name},\n\nLe statut de votre réservation #{order_number} a été modifié.\n\nNouveau statut: {order_status}\n\nConsultez les détails sur votre compte: {account_url}", 'life-travel-excursion')
            ],
            'excursion_reminder' => [
                'subject' => __('Rappel: Votre excursion {product_name} approche!', 'life-travel-excursion'),
                'email' => $this->get_default_email_template('excursion_reminder'),
                'sms' => __('Life Travel: Rappel! Votre excursion {product_name} est prévue pour {excursion_date}. N\'oubliez pas de vérifier les détails sur votre compte.', 'life-travel-excursion'),
                'whatsapp' => __("*Life Travel - Rappel d'excursion*\n\nBonjour {customer_name},\n\nVotre excursion {product_name} est prévue pour le {excursion_date}, soit dans {days_before} jours.\n\nNombre de participants: {participants_count}\nPoint de rendez-vous: {meeting_point}\nHeure: {meeting_time}\n\nConsultez les détails sur votre compte: {account_url}", 'life-travel-excursion')
            ]
        ];
        
        // Ajouter d'autres modèles dans une fonction séparée pour éviter un fichier trop volumineux
        $this->add_account_templates();
    }
    
    /**
     * Ajoute les modèles liés au compte utilisateur
     */
    private function add_account_templates() {
        $account_templates = [
            'account_created' => [
                'subject' => __('Bienvenue chez Life Travel - Votre compte a été créé', 'life-travel-excursion'),
                'email' => $this->get_default_email_template('account_created'),
                'sms' => __('Life Travel: Votre compte a été créé avec succès. Connectez-vous pour explorer nos excursions: {login_url}', 'life-travel-excursion'),
                'whatsapp' => __("*Life Travel - Bienvenue!*\n\nBonjour {customer_name},\n\nVotre compte a été créé avec succès.\n\nVous pouvez maintenant vous connecter et explorer nos excursions: {login_url}\n\nMerci de nous rejoindre!", 'life-travel-excursion')
            ],
            'password_reset' => [
                'subject' => __('Réinitialisation de votre mot de passe Life Travel', 'life-travel-excursion'),
                'email' => $this->get_default_email_template('password_reset'),
                'sms' => __('Life Travel: Demande de réinitialisation de mot de passe reçue. Utilisez ce lien pour réinitialiser: {reset_link}', 'life-travel-excursion'),
                'whatsapp' => __("*Life Travel - Réinitialisation de mot de passe*\n\nBonjour,\n\nNous avons reçu une demande de réinitialisation de mot de passe pour votre compte.\n\nPour réinitialiser votre mot de passe, cliquez sur ce lien: {reset_link}\n\nSi vous n'avez pas demandé cette réinitialisation, veuillez ignorer ce message.", 'life-travel-excursion')
            ],
            'new_login' => [
                'subject' => __('Nouvelle connexion à votre compte Life Travel', 'life-travel-excursion'),
                'email' => $this->get_default_email_template('new_login'),
                'sms' => __('Life Travel: Nouvelle connexion à votre compte détectée depuis {login_location}. Si ce n\'était pas vous, contactez-nous immédiatement.', 'life-travel-excursion'),
                'whatsapp' => __("*Life Travel - Alerte de sécurité*\n\nBonjour {customer_name},\n\nUne nouvelle connexion à votre compte a été détectée.\n\nDate: {login_date}\nLocalisation: {login_location}\nAppareil: {login_device}\n\nSi ce n'était pas vous, veuillez sécuriser votre compte immédiatement.", 'life-travel-excursion')
            ]
        ];
        
        $this->default_templates = array_merge($this->default_templates, $account_templates);
    }
    
    /**
     * Initialise les variables disponibles pour les modèles
     */
    private function init_available_variables() {
        $this->available_variables = [
            'general' => [
                '{site_name}' => __('Nom du site', 'life-travel-excursion'),
                '{site_url}' => __('URL du site', 'life-travel-excursion'),
                '{current_date}' => __('Date actuelle', 'life-travel-excursion'),
                '{customer_name}' => __('Nom du client', 'life-travel-excursion'),
                '{customer_first_name}' => __('Prénom du client', 'life-travel-excursion'),
                '{customer_last_name}' => __('Nom de famille du client', 'life-travel-excursion'),
                '{customer_email}' => __('Email du client', 'life-travel-excursion'),
                '{customer_phone}' => __('Téléphone du client', 'life-travel-excursion'),
                '{account_url}' => __('URL du compte client', 'life-travel-excursion'),
                '{login_url}' => __('URL de connexion', 'life-travel-excursion')
            ],
            'order' => [
                '{order_number}' => __('Numéro de commande', 'life-travel-excursion'),
                '{order_date}' => __('Date de commande', 'life-travel-excursion'),
                '{order_total}' => __('Montant total de la commande', 'life-travel-excursion'),
                '{order_status}' => __('Statut de la commande', 'life-travel-excursion'),
                '{payment_method}' => __('Méthode de paiement', 'life-travel-excursion'),
                '{order_details_url}' => __('URL des détails de la commande', 'life-travel-excursion')
            ],
            'excursion' => [
                '{product_name}' => __('Nom de l\'excursion', 'life-travel-excursion'),
                '{excursion_date}' => __('Date de l\'excursion', 'life-travel-excursion'),
                '{participants_count}' => __('Nombre de participants', 'life-travel-excursion'),
                '{meeting_point}' => __('Point de rendez-vous', 'life-travel-excursion'),
                '{meeting_time}' => __('Heure de rendez-vous', 'life-travel-excursion'),
                '{days_before}' => __('Jours restants avant l\'excursion', 'life-travel-excursion')
            ]
        ];
        
        // Ajouter d'autres groupes de variables dans une fonction séparée
        $this->add_additional_variables();
    }
    
    /**
     * Ajoute des variables supplémentaires aux modèles
     */
    private function add_additional_variables() {
        $additional_variables = [
            'reset_password' => [
                '{reset_link}' => __('Lien de réinitialisation', 'life-travel-excursion'),
                '{reset_expiry}' => __('Délai d\'expiration du lien', 'life-travel-excursion')
            ],
            'security' => [
                '{login_date}' => __('Date de connexion', 'life-travel-excursion'),
                '{login_location}' => __('Localisation de connexion', 'life-travel-excursion'),
                '{login_device}' => __('Appareil utilisé', 'life-travel-excursion'),
                '{login_ip}' => __('Adresse IP', 'life-travel-excursion')
            ]
        ];
        
        $this->available_variables = array_merge($this->available_variables, $additional_variables);
    }
    
    /**
     * Récupère le modèle pour un type de notification et un canal
     *
     * @param string $notification_type Type de notification
     * @param string $channel Canal de notification (email, sms, whatsapp)
     * @param string $field Champ spécifique à récupérer (par défaut, tout le modèle)
     * @return string|array Modèle ou champ spécifique du modèle
     */
    public function get_template($notification_type, $channel, $field = null) {
        // Récupérer le modèle enregistré ou utiliser le modèle par défaut
        $template_option_name = "lte_notification_template_{$notification_type}_{$channel}";
        $template = get_option($template_option_name, $this->get_default_template($notification_type, $channel));
        
        // Si on demande un champ spécifique et que c'est un tableau
        if ($field !== null && is_array($template) && isset($template[$field])) {
            return $template[$field];
        }
        
        return $template;
    }
    
    /**
     * Récupère le modèle par défaut pour un type et canal
     *
     * @param string $notification_type Type de notification
     * @param string $channel Canal de notification
     * @return string|array Modèle par défaut
     */
    public function get_default_template($notification_type, $channel) {
        if (isset($this->default_templates[$notification_type][$channel])) {
            return $this->default_templates[$notification_type][$channel];
        }
        
        // Valeur par défaut générique si le modèle n'existe pas
        switch ($channel) {
            case 'subject':
                return __('Notification Life Travel', 'life-travel-excursion');
            case 'email':
                return '<p>' . __('Notification de Life Travel.', 'life-travel-excursion') . '</p>';
            case 'sms':
                return __('Notification de Life Travel.', 'life-travel-excursion');
            case 'whatsapp':
                return __('Notification de Life Travel.', 'life-travel-excursion');
            default:
                return '';
        }
    }
    
    /**
     * Récupère le modèle par défaut pour les emails
     * 
     * @param string $template_id ID du modèle
     * @return string Contenu HTML du modèle
     */
    private function get_default_email_template($template_id) {
        // Contenu spécifique selon le type de notification
        $specific_content = $this->get_email_template_content($template_id);
        
        // Template commun pour tous les emails
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>{email_title}</title>
        </head>
        <body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">
            <div style="text-align:center;margin-bottom:20px;">
                <img src="{logo_url}" alt="{site_name}" style="max-width:150px;">
            </div>
            
            <div style="background-color:#f9f9f9;border-radius:5px;padding:20px;border:1px solid #ddd;">
                <p>Bonjour {customer_name},</p>
                
                ' . $specific_content . '
                
                <p style="margin-top:30px;">Cordialement,<br>L\'équipe Life Travel</p>
            </div>
            
            <div style="text-align:center;font-size:12px;color:#777;margin-top:20px;padding-top:20px;border-top:1px solid #ddd;">
                <p>&copy; {current_year} Life Travel. Tous droits réservés.</p>
                <p>
                    <a href="{site_url}" style="color:#4CAF50;text-decoration:none;">Visiter notre site</a> | 
                    <a href="{account_url}" style="color:#4CAF50;text-decoration:none;">Mon compte</a>
                </p>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Récupère le contenu spécifique d'un modèle d'email
     *
     * @param string $template_id ID du modèle
     * @return string Contenu HTML spécifique
     */
    private function get_email_template_content($template_id) {
        switch ($template_id) {
            case 'order_confirmation':
                return '
                <h2 style="color:#4CAF50;">Confirmation de réservation #{order_number}</h2>
                <p>Nous avons le plaisir de vous confirmer votre réservation pour <strong>{product_name}</strong>.</p>
                <p><strong>Date de l\'excursion:</strong> {excursion_date}<br>
                <strong>Nombre de participants:</strong> {participants_count}<br>
                <strong>Montant total:</strong> {order_total}</p>
                <p>Vous pouvez consulter les détails de votre réservation à tout moment depuis <a href="{order_details_url}">votre compte</a>.</p>';
                
            case 'order_status':
                return '
                <h2 style="color:#4CAF50;">Mise à jour de votre réservation #{order_number}</h2>
                <p>Le statut de votre réservation pour <strong>{product_name}</strong> a été mis à jour.</p>
                <p><strong>Nouveau statut:</strong> {order_status}</p>
                <p>Vous pouvez consulter les détails de votre réservation à tout moment depuis <a href="{order_details_url}">votre compte</a>.</p>';
                
            case 'excursion_reminder':
                return '
                <h2 style="color:#4CAF50;">Rappel d\'excursion</h2>
                <p>Nous vous rappelons que votre excursion <strong>{product_name}</strong> est prévue pour le <strong>{excursion_date}</strong>, soit dans <strong>{days_before} jours</strong>.</p>
                <p><strong>Nombre de participants:</strong> {participants_count}<br>
                <strong>Point de rendez-vous:</strong> {meeting_point}<br>
                <strong>Heure:</strong> {meeting_time}</p>
                <p>Nous vous attendons avec impatience pour cette aventure! Vous pouvez consulter tous les détails de votre réservation depuis <a href="{order_details_url}">votre compte</a>.</p>';
                
            case 'account_created':
                return '
                <h2 style="color:#4CAF50;">Bienvenue chez Life Travel!</h2>
                <p>Votre compte a été créé avec succès.</p>
                <p>Vous pouvez maintenant vous connecter et explorer nos excursions en utilisant le lien suivant: <a href="{login_url}">Se connecter</a>.</p>
                <p>Nous vous remercions de nous rejoindre et espérons vous faire vivre des expériences inoubliables au Cameroun!</p>';
                
            case 'password_reset':
                return '
                <h2 style="color:#4CAF50;">Réinitialisation de mot de passe</h2>
                <p>Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.</p>
                <p>Pour réinitialiser votre mot de passe, cliquez sur le bouton ci-dessous:</p>
                <p style="text-align:center;margin:30px 0;">
                    <a href="{reset_link}" style="background-color:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;">Réinitialiser mon mot de passe</a>
                </p>
                <p>Ce lien expirera dans {reset_expiry}. Si vous n\'avez pas demandé cette réinitialisation, veuillez ignorer ce message.</p>';
                
            case 'new_login':
                return '
                <h2 style="color:#4CAF50;">Nouvelle connexion détectée</h2>
                <p>Une nouvelle connexion à votre compte Life Travel a été détectée avec les informations suivantes:</p>
                <p><strong>Date et heure:</strong> {login_date}<br>
                <strong>Localisation:</strong> {login_location}<br>
                <strong>Appareil:</strong> {login_device}<br>
                <strong>Adresse IP:</strong> {login_ip}</p>
                <p>Si vous ne reconnaissez pas cette activité, nous vous recommandons de <a href="{account_url}">changer votre mot de passe</a> immédiatement.</p>';
                
            default:
                return '<p>Notification de Life Travel</p>';
        }
    }
    
    /**
     * Remplace les variables d'un modèle par leurs valeurs réelles
     *
     * @param string $template Modèle à utiliser
     * @param array $data Données pour remplacer les variables
     * @return string Modèle avec variables remplacées
     */
    public function replace_template_variables($template, $data) {
        // Variables de base communes à tous les modèles
        $common_data = [
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_bloginfo('url'),
            '{current_date}' => date_i18n(get_option('date_format')),
            '{current_year}' => date('Y'),
            '{logo_url}' => $this->get_logo_url(),
        ];
        
        // Fusionner avec les données spécifiques
        $all_data = array_merge($common_data, $data);
        
        // Remplacer toutes les variables
        return str_replace(array_keys($all_data), array_values($all_data), $template);
    }
    
    /**
     * Récupère l'URL du logo du site
     *
     * @return string URL du logo
     */
    private function get_logo_url() {
        $custom_logo_id = get_theme_mod('custom_logo');
        
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            return $logo_url ? $logo_url : '';
        }
        
        // Logo par défaut si aucun logo personnalisé n'est défini
        return LIFE_TRAVEL_EXCURSION_URL . 'assets/images/logo.png';
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_Notification_Templates::get_instance();
});
