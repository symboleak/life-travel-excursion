<?php
/**
 * Gestionnaire d'emails pour les paniers abandonnés
 * 
 * Gère l'envoi sécurisé des emails de récupération pour les paniers abandonnés
 * avec des modèles personnalisables et un suivi des envois.
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

/**
 * Classe pour la gestion des emails de récupération de paniers abandonnés
 */
class Life_Travel_Abandoned_Cart_Mailer {
    
    /**
     * Instance unique (singleton)
     * @var Life_Travel_Abandoned_Cart_Mailer
     */
    private static $instance = null;
    
    /**
     * Constructeur privé (pattern singleton)
     */
    private function __construct() {
        // Aucune initialisation spéciale nécessaire
    }
    
    /**
     * Obtenir l'instance unique (pattern singleton)
     * 
     * @return Life_Travel_Abandoned_Cart_Mailer Instance unique
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Envoie un email de récupération pour un panier abandonné
     * 
     * @param int $cart_id ID du panier abandonné
     * @return bool True si l'email a été envoyé, false sinon
     */
    public function send_recovery_email($cart_id) {
        global $wpdb;
        
        // Vérification de sécurité sur l'ID du panier
        $cart_id = absint($cart_id);
        if ($cart_id === 0) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Récupérer les informations du panier avec une requête préparée
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $cart_id
        ));
        
        // Vérifier si le panier existe et n'a pas été récupéré
        if (!$cart || $cart->recovered == 1) {
            return false;
        }
        
        // Récupérer l'email du client
        $customer_email = sanitize_email($cart->email);
        if (!is_email($customer_email)) {
            $this->log_error(sprintf('Email invalide pour le panier ID %d', $cart_id));
            return false;
        }
        
        // Récupérer les paramètres des emails
        $settings = get_option('life_travel_abandoned_cart_settings', array());
        $email_subject = isset($settings['email_subject']) 
            ? sanitize_text_field($settings['email_subject']) 
            : __('Votre panier vous attend', 'life-travel-excursion');
        
        $template = isset($settings['email_template']) ? $settings['email_template'] : 'default';
        
        // Générer un token de récupération unique et sécurisé
        $recovery_token = $this->generate_recovery_token($cart_id, $customer_email);
        
        // Calculer l'expiration du lien (en jours)
        $expiry_days = isset($settings['recovery_link_expiry']) ? intval($settings['recovery_link_expiry']) : 7;
        $expiry_date = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));
        
        // Mettre à jour le token dans la base de données
        $wpdb->update(
            $table_name,
            array(
                'recovery_token' => $recovery_token,
                'token_expiry' => $expiry_date
            ),
            array('id' => $cart_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Créer l'URL de récupération
        $recovery_url = add_query_arg(array(
            'life_travel_recover' => $recovery_token
        ), wc_get_cart_url());
        
        // Obtenir les informations du panier
        $cart_contents = $this->safely_decode_cart_contents($cart->cart_contents);
        $cart_items = $this->format_cart_items($cart_contents);
        
        // Préparer les variables pour le modèle
        $email_vars = array(
            'site_name' => get_bloginfo('name'),
            'customer_first_name' => $cart->first_name,
            'customer_last_name' => $cart->last_name,
            'cart_items' => $cart_items,
            'cart_total' => wc_price($cart->cart_total),
            'recovery_link' => esc_url($recovery_url),
            'expiry_days' => $expiry_days
        );
        
        // Récupérer et remplir le modèle d'email
        $email_content = $this->get_email_template($template);
        $email_content = $this->replace_template_vars($email_content, $email_vars);
        
        // Configuration de l'email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        // Envoyer l'email
        $sent = wp_mail($customer_email, $email_subject, $email_content, $headers);
        
        if ($sent) {
            // Mettre à jour les informations de rappel dans la base de données
            $wpdb->update(
                $table_name,
                array(
                    'reminder_sent' => 1,
                    'reminder_count' => $cart->reminder_count + 1,
                    'last_reminder_sent' => current_time('mysql'),
                    'last_updated' => current_time('mysql')
                ),
                array('id' => $cart_id),
                array('%d', '%d', '%s', '%s'),
                array('%d')
            );
            
            // Journaliser l'envoi
            $this->log_email_sent($cart_id, $customer_email);
            
            return true;
        } else {
            // Journaliser l'échec
            $this->log_error(sprintf('Échec de l\'envoi de l\'email de récupération pour le panier ID %d', $cart_id));
            
            return false;
        }
    }
    
    /**
     * Récupère le contenu d'un modèle d'email
     * 
     * @param string $template Nom du modèle
     * @return string Contenu du modèle
     */
    public function get_email_template($template) {
        // Sanitize pour éviter les injections de chemin de fichier
        $template = sanitize_file_name($template);
        
        // Chemin du modèle
        $template_path = LIFE_TRAVEL_EXCURSION_DIR . 'templates/emails/' . $template . '.php';
        
        // Vérifier si le modèle existe
        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }
        
        // Sinon, utiliser le modèle par défaut
        $default_template_path = LIFE_TRAVEL_EXCURSION_DIR . 'templates/emails/default.php';
        
        if (file_exists($default_template_path)) {
            ob_start();
            include $default_template_path;
            return ob_get_clean();
        }
        
        // Si aucun modèle n'est trouvé, utiliser un modèle de secours
        return $this->get_fallback_template();
    }
    
    /**
     * Remplace les variables dans un modèle d'email
     * 
     * @param string $content Contenu du modèle
     * @param array $vars Variables à remplacer
     * @return string Contenu avec variables remplacées
     */
    private function replace_template_vars($content, $vars) {
        // Remplacer les variables standard
        $content = str_replace('{site_name}', esc_html($vars['site_name']), $content);
        $content = str_replace('{customer_first_name}', esc_html($vars['customer_first_name']), $content);
        $content = str_replace('{customer_last_name}', esc_html($vars['customer_last_name']), $content);
        $content = str_replace('{cart_total}', $vars['cart_total'], $content);
        $content = str_replace('{recovery_link}', $vars['recovery_link'], $content);
        $content = str_replace('{expiry_days}', esc_html($vars['expiry_days']), $content);
        
        // Remplacer la liste des articles du panier
        if (strpos($content, '{cart_items}') !== false) {
            $cart_items_html = '';
            
            if (!empty($vars['cart_items'])) {
                $cart_items_html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
                $cart_items_html .= '<tr><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Produit</th>';
                $cart_items_html .= '<th style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;">Quantité</th>';
                $cart_items_html .= '<th style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;">Prix</th></tr>';
                
                foreach ($vars['cart_items'] as $item) {
                    $cart_items_html .= '<tr>';
                    $cart_items_html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($item['name']) . '</td>';
                    $cart_items_html .= '<td style="text-align: right; padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($item['quantity']) . '</td>';
                    $cart_items_html .= '<td style="text-align: right; padding: 8px; border-bottom: 1px solid #eee;">' . $item['price'] . '</td>';
                    $cart_items_html .= '</tr>';
                }
                
                $cart_items_html .= '</table>';
            } else {
                $cart_items_html = '<p>' . __('Votre panier est vide.', 'life-travel-excursion') . '</p>';
            }
            
            $content = str_replace('{cart_items}', $cart_items_html, $content);
        }
        
        return $content;
    }
    
    /**
     * Génère un token de récupération sécurisé
     * 
     * @param int $cart_id ID du panier
     * @param string $email Email du client
     * @return string Token de récupération
     */
    private function generate_recovery_token($cart_id, $email) {
        // Créer un token unique et difficile à deviner
        $token_data = $cart_id . '|' . $email . '|' . time() . '|' . wp_generate_password(16, false);
        $token = wp_hash($token_data);
        
        // Tronquer pour une URL plus courte mais toujours sécurisée
        return substr($token, 0, 32);
    }
    
    /**
     * Décode en toute sécurité le contenu du panier
     * 
     * @param string $cart_contents Contenu du panier encodé
     * @return array|false Contenu du panier décodé ou false en cas d'échec
     */
    private function safely_decode_cart_contents($cart_contents) {
        if (empty($cart_contents)) {
            return false;
        }
        
        // Désérialisation sécurisée
        $contents = maybe_unserialize($cart_contents);
        
        // Si c'est déjà un tableau, le renvoyer
        if (is_array($contents)) {
            return $contents;
        }
        
        // Essayer de décoder JSON
        $json_contents = json_decode($cart_contents, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json_contents)) {
            return $json_contents;
        }
        
        // Si nous arrivons ici, le format n'est pas reconnu
        return false;
    }
    
    /**
     * Formate les articles du panier pour l'email
     * 
     * @param array|false $cart_contents Contenu du panier
     * @return array Articles formatés
     */
    private function format_cart_items($cart_contents) {
        $items = array();
        
        if (!$cart_contents || !is_array($cart_contents)) {
            return $items;
        }
        
        // Parcourir les articles du panier
        foreach ($cart_contents as $item) {
            // Support de différents formats de panier
            $product_id = isset($item['product_id']) ? absint($item['product_id']) : 0;
            $quantity = isset($item['quantity']) ? absint($item['quantity']) : 1;
            
            if ($product_id > 0) {
                $product = wc_get_product($product_id);
                
                if ($product) {
                    $items[] = array(
                        'id' => $product_id,
                        'name' => $product->get_name(),
                        'quantity' => $quantity,
                        'price' => wc_price($product->get_price() * $quantity)
                    );
                }
            }
        }
        
        return $items;
    }
    
    /**
     * Fournit un modèle d'email de secours
     * 
     * @return string Modèle de secours
     */
    private function get_fallback_template() {
        $template = '<!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <title>{site_name} - Votre panier vous attend</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h1 style="color: #2a3950;">{site_name}</h1>
                </div>
                
                <div style="background-color: #f9f9f9; border-radius: 5px; padding: 20px; margin-bottom: 20px;">
                    <h2 style="color: #2a3950; margin-top: 0;">Bonjour {customer_first_name},</h2>
                    
                    <p>Nous avons remarqué que vous avez laissé des articles dans votre panier. Vous êtes peut-être occupé(e), ou peut-être avez-vous rencontré un problème lors de la finalisation de votre achat ?</p>
                    
                    <p>Voici un récapitulatif de votre panier :</p>
                    
                    {cart_items}
                    
                    <p><strong>Total : {cart_total}</strong></p>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{recovery_link}" style="background-color: #2a3950; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">Récupérer mon panier</a>
                    </div>
                    
                    <p style="font-size: 0.9em; color: #777;">Ce lien expirera dans {expiry_days} jours.</p>
                </div>
                
                <div style="text-align: center; font-size: 0.8em; color: #777; margin-top: 30px;">
                    <p>© ' . date('Y') . ' {site_name}. Tous droits réservés.</p>
                    <p>Si vous ne souhaitez plus recevoir ces emails, veuillez nous contacter.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Journalise l'envoi d'un email
     * 
     * @param int $cart_id ID du panier
     * @param string $email Email du destinataire
     */
    private function log_email_sent($cart_id, $email) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Life Travel] Email de récupération envoyé pour le panier ID %d à %s',
                $cart_id,
                $email
            ));
        }
        
        // Ajouter une entrée dans le journal des emails si la table existe
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_email_log';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $wpdb->insert(
                $table_name,
                array(
                    'email_type' => 'abandoned_cart',
                    'recipient' => $email,
                    'cart_id' => $cart_id,
                    'status' => 'sent',
                    'sent_date' => current_time('mysql')
                ),
                array('%s', '%s', '%d', '%s', '%s')
            );
        }
    }
    
    /**
     * Journalise une erreur
     * 
     * @param string $message Message d'erreur
     */
    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Life Travel] ' . $message);
        }
        
        // Ajouter une entrée dans le journal des erreurs si la table existe
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_error_log';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $wpdb->insert(
                $table_name,
                array(
                    'error_type' => 'email',
                    'error_message' => $message,
                    'error_date' => current_time('mysql')
                ),
                array('%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Vérifie si un token de récupération est valide
     * 
     * @param string $token Token de récupération
     * @return int|false ID du panier si le token est valide, false sinon
     */
    public function validate_recovery_token($token) {
        if (empty($token)) {
            return false;
        }
        
        // Sanitize pour éviter les injections SQL
        $token = sanitize_text_field($token);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Récupérer le panier correspondant au token
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT id, token_expiry, recovered FROM {$table_name} WHERE recovery_token = %s",
            $token
        ));
        
        // Vérifier si le panier existe
        if (!$cart) {
            return false;
        }
        
        // Vérifier si le panier a déjà été récupéré
        if ($cart->recovered == 1) {
            return false;
        }
        
        // Vérifier si le token a expiré
        if (strtotime($cart->token_expiry) < time()) {
            return false;
        }
        
        return $cart->id;
    }
    
    /**
     * Marque un panier comme récupéré
     * 
     * @param int $cart_id ID du panier
     * @return bool True si le panier a été marqué comme récupéré, false sinon
     */
    public function mark_cart_as_recovered($cart_id) {
        global $wpdb;
        
        $cart_id = absint($cart_id);
        if ($cart_id === 0) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Mettre à jour le statut du panier
        $updated = $wpdb->update(
            $table_name,
            array(
                'recovered' => 1,
                'recovered_at' => current_time('mysql'),
                'last_updated' => current_time('mysql')
            ),
            array('id' => $cart_id),
            array('%d', '%s', '%s'),
            array('%d')
        );
        
        return $updated !== false;
    }
}
