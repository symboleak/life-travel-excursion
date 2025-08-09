<?php
/**
 * Checkout Enhancement
 * 
 * Améliore l'expérience utilisateur lors du checkout pour les excursions
 * 
 * @package Life_Travel_Excursion
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class Life_Travel_Checkout_Enhancement {
    // Singleton instance
    private static $instance = null;
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Hooks pour le checkout
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('woocommerce_checkout_fields', [$this, 'customize_checkout_fields']);
        add_action('woocommerce_before_checkout_form', [$this, 'display_checkout_steps'], 10);
        add_action('woocommerce_checkout_process', [$this, 'validate_excursion_fields']);
        
        // Hooks pour les modifications du Template
        add_action('woocommerce_checkout_before_customer_details', [$this, 'open_checkout_container']);
        add_action('woocommerce_checkout_after_customer_details', [$this, 'close_customer_details_container']);
        add_action('woocommerce_checkout_before_order_review', [$this, 'open_order_review_container']);
        add_action('woocommerce_checkout_after_order_review', [$this, 'close_checkout_container']);
        
        // Hooks pour la récupération des paniers abandonnés
        add_action('woocommerce_before_checkout_form', [$this, 'check_abandoned_cart']);
        add_action('wp_ajax_lte_save_cart_progress', [$this, 'ajax_save_cart_progress']);
        add_action('wp_ajax_nopriv_lte_save_cart_progress', [$this, 'ajax_save_cart_progress']);
    }
    
    /**
     * Récupère l'instance unique
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Enregistre les scripts et styles
     */
    public function enqueue_assets() {
        if (is_checkout() || is_account_page()) {
            wp_enqueue_style(
                'lte-checkout-enhancement',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/css/checkout-enhancement.css',
                [],
                LIFE_TRAVEL_EXCURSION_VERSION
            );
            
            wp_enqueue_script(
                'lte-checkout-enhancement',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/js/checkout-enhancement.js',
                ['jquery'],
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
            
            wp_localize_script('lte-checkout-enhancement', 'lteCheckout', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lte_checkout_nonce'),
                'save_cart_interval' => 30000, // 30 secondes
                'i18n' => [
                    'error_validation' => __('Veuillez corriger les erreurs avant de continuer.', 'life-travel-excursion'),
                    'cart_recovered' => __('Votre panier a été récupéré.', 'life-travel-excursion')
                ]
            ]);
        }
    }
    
    /**
     * Ajoute des étapes visuelles au checkout
     */
    public function display_checkout_steps() {
        // Vérifier si le template existe
        $template_path = LIFE_TRAVEL_EXCURSION_DIR . 'templates/checkout/steps.php';
        
        if (file_exists($template_path)) {
            include_once $template_path;
        } else {
            // Template de secours intégré
            ?>
            <div class="lte-checkout-steps">
                <ul>
                    <li class="step active" data-step="information">
                        <span class="step-number">1</span>
                        <span class="step-title"><?php esc_html_e('Informations', 'life-travel-excursion'); ?></span>
                    </li>
                    <li class="step" data-step="excursion">
                        <span class="step-number">2</span>
                        <span class="step-title"><?php esc_html_e('Excursion', 'life-travel-excursion'); ?></span>
                    </li>
                    <li class="step" data-step="payment">
                        <span class="step-number">3</span>
                        <span class="step-title"><?php esc_html_e('Paiement', 'life-travel-excursion'); ?></span>
                    </li>
                    <li class="step" data-step="confirmation">
                        <span class="step-number">4</span>
                        <span class="step-title"><?php esc_html_e('Confirmation', 'life-travel-excursion'); ?></span>
                    </li>
                </ul>
            </div>
            <?php
        }
    }
    
    /**
     * Personnalise les champs du checkout
     */
    public function customize_checkout_fields($fields) {
        // Réorganisation des champs existants
        $fields['billing']['billing_first_name']['priority'] = 10;
        $fields['billing']['billing_last_name']['priority'] = 20;
        $fields['billing']['billing_phone']['priority'] = 30;
        $fields['billing']['billing_email']['priority'] = 40;
        
        // Modification des libellés pour plus de clarté
        $fields['billing']['billing_phone']['label'] = __('Téléphone mobile', 'life-travel-excursion');
        $fields['billing']['billing_phone']['description'] = __('Nous vous contacterons uniquement en cas de nécessité.', 'life-travel-excursion');
        
        // Ajout de champs spécifiques aux excursions
        // Ces champs seront conditionnels selon le type de produit
        if ($this->cart_has_excursion()) {
            $fields['order']['excursion_date'] = [
                'type' => 'date',
                'label' => __('Date de l\'excursion', 'life-travel-excursion'),
                'placeholder' => __('Sélectionnez une date', 'life-travel-excursion'),
                'required' => true,
                'class' => ['form-row-first'],
                'priority' => 90,
                'clear' => true
            ];
            
            $fields['order']['excursion_participants'] = [
                'type' => 'number',
                'label' => __('Nombre de participants', 'life-travel-excursion'),
                'placeholder' => __('Nombre', 'life-travel-excursion'),
                'required' => true,
                'class' => ['form-row-last'],
                'priority' => 100,
                'min' => 1,
                'max' => 20
            ];
        }
        
        return $fields;
    }
    
    /**
     * Vérifie si le panier contient une excursion
     * 
     * @return bool
     */
    private function cart_has_excursion() {
        if (!function_exists('WC') || empty(WC()->cart)) {
            return false;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            
            // Vérifier si c'est une excursion (par taxonomie ou méta)
            $terms = get_the_terms($product->get_id(), 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if ($term->slug === 'excursion') {
                        return true;
                    }
                }
            }
            
            // Alternative : vérifier par méta
            if (get_post_meta($product->get_id(), '_is_excursion', true) === 'yes') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Valide les champs spécifiques aux excursions
     */
    public function validate_excursion_fields() {
        if ($this->cart_has_excursion()) {
            // Validation de la date (passée)
            if (isset($_POST['excursion_date']) && !empty($_POST['excursion_date'])) {
                $excursion_date = sanitize_text_field($_POST['excursion_date']);
                $date_obj = DateTime::createFromFormat('Y-m-d', $excursion_date);
                
                if (!$date_obj || $date_obj->format('Y-m-d') !== $excursion_date) {
                    wc_add_notice(__('Format de date invalide.', 'life-travel-excursion'), 'error');
                } elseif ($date_obj < new DateTime('tomorrow')) {
                    wc_add_notice(__('La date d\'excursion doit être au moins le lendemain.', 'life-travel-excursion'), 'error');
                }
            }
            
            // Validation du nombre de participants
            if (isset($_POST['excursion_participants'])) {
                $participants = intval($_POST['excursion_participants']);
                if ($participants < 1 || $participants > 20) {
                    wc_add_notice(__('Le nombre de participants doit être entre 1 et 20.', 'life-travel-excursion'), 'error');
                }
            }
        }
    }
    
    /**
     * Ouvre un conteneur pour le checkout
     */
    public function open_checkout_container() {
        echo '<div class="lte-checkout-container">';
    }
    
    /**
     * Ferme la section des détails client
     */
    public function close_customer_details_container() {
        echo '</div><!-- .lte-customer-details -->';
    }
    
    /**
     * Ouvre le conteneur de résumé de commande
     */
    public function open_order_review_container() {
        echo '<div class="lte-order-review">';
    }
    
    /**
     * Ferme le conteneur de checkout
     */
    public function close_checkout_container() {
        echo '</div><!-- .lte-order-review -->';
        echo '</div><!-- .lte-checkout-container -->';
    }
    
    /**
     * Vérifie l'existence d'un panier abandonné et propose de le restaurer
     */
    public function check_abandoned_cart() {
        // Ne rien faire si nous ne sommes pas sur la page panier ou si le panier n'est pas vide
        if (!function_exists('WC') || !is_cart() || !WC()->cart->is_empty()) {
            return;
        }
        
        // Vérifier si l'utilisateur est connecté
        $user_id = get_current_user_id();
        if (!$user_id) {
            // Pour les utilisateurs non-connectés, vérifier par cookie
            $cookie_cart = $this->get_cart_cookie();
            if (empty($cookie_cart)) {
                return;
            }
            
            try {
                // Décoder les données du cookie
                $cart_data = json_decode(stripslashes($cookie_cart), true);
                if (empty($cart_data) || empty($cart_data['items']) || !is_array($cart_data['items'])) {
                    return;
                }
                
                // Vérifier la fraicheur des données (maximum 30 jours)
                $timestamp = isset($cart_data['timestamp']) ? (int)$cart_data['timestamp'] : 0;
                if ($timestamp < (time() - 30 * DAY_IN_SECONDS)) {
                    return;
                }
                
                // Afficher le message de récupération
                $this->display_cart_recovery_notice($cart_data);
                
            } catch (\Exception $e) {
                error_log('Life Travel: Erreur lors du traitement du panier abandonné cookie - ' . $e->getMessage());
                return;
            }
        } else {
            // Pour les utilisateurs connectés, vérifier les données en base
            global $wpdb;
            
            // Récupérer le panier abandonné le plus récent
            $abandoned_cart = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}lte_abandoned_carts 
                WHERE user_id = %d 
                AND recovered = 0 
                AND cart_contents != ''
                ORDER BY last_updated DESC 
                LIMIT 1",
                $user_id
            ));
            
            if (empty($abandoned_cart) || empty($abandoned_cart->cart_contents)) {
                return;
            }
            
            try {
                // Décoder les données du panier
                $cart_data = json_decode($abandoned_cart->cart_contents, true);
                if (empty($cart_data) || empty($cart_data['items']) || !is_array($cart_data['items'])) {
                    return;
                }
                
                // Vérifier la fraicheur des données (maximum 30 jours)
                $timestamp = strtotime($abandoned_cart->last_updated);
                if ($timestamp < (time() - 30 * DAY_IN_SECONDS)) {
                    return;
                }
                
                // Ajouter l'ID du panier abandonné pour la récupération
                $cart_data['abandoned_cart_id'] = $abandoned_cart->id;
                
                // Afficher le message de récupération
                $this->display_cart_recovery_notice($cart_data);
                
            } catch (\Exception $e) {
                error_log('Life Travel: Erreur lors du traitement du panier abandonné BDD - ' . $e->getMessage());
                return;
            }
        }
    }
    
    /**
     * Affiche le message de récupération de panier abandonné
     *
     * @param array $cart_data Données du panier abandonné
     */
    private function display_cart_recovery_notice($cart_data) {
        $item_count = count($cart_data['items']);
        $timestamp = isset($cart_data['timestamp']) ? (int)$cart_data['timestamp'] : 0;
        $date = date_i18n(get_option('date_format'), $timestamp);
        $time = date_i18n(get_option('time_format'), $timestamp);
        
        // Construire la liste des produits (limitée à 3 pour ne pas surcharger)
        $product_list = '<ul class="lte-abandoned-products">';
        $counter = 0;
        foreach ($cart_data['items'] as $item) {
            if ($counter >= 3) {
                break;
            }
            
            $product_id = isset($item['product_id']) ? (int)$item['product_id'] : 0;
            $product = wc_get_product($product_id);
            
            if ($product) {
                $product_list .= '<li>' . $product->get_name() . '</li>';
                $counter++;
            }
        }
        
        if ($item_count > 3) {
            $product_list .= '<li>' . sprintf(
                __('et %d autres articles...', 'life-travel-excursion'),
                $item_count - 3
            ) . '</li>';
        }
        
        $product_list .= '</ul>';
        
        // Message adapté pour affichage
        $message = sprintf(
            __('Vous avez un panier abandonné du %s à %s contenant %d article(s). Souhaitez-vous le restaurer?', 'life-travel-excursion'),
            '<strong>' . $date . '</strong>',
            '<strong>' . $time . '</strong>',
            $item_count
        );
        
        // Boutons d'action
        $restore_url = add_query_arg([
            'lte-restore-cart' => '1',
            'cart-data' => base64_encode(json_encode([
                'cart_id' => isset($cart_data['abandoned_cart_id']) ? $cart_data['abandoned_cart_id'] : '',
                'user_id' => get_current_user_id(),
                'timestamp' => $timestamp
            ])),
            'nonce' => wp_create_nonce('lte_restore_cart')
        ]);
        
        $ignore_url = add_query_arg([
            'lte-ignore-cart' => '1',
            'cart-data' => base64_encode(json_encode([
                'cart_id' => isset($cart_data['abandoned_cart_id']) ? $cart_data['abandoned_cart_id'] : '',
                'user_id' => get_current_user_id(),
                'timestamp' => $timestamp
            ])),
            'nonce' => wp_create_nonce('lte_ignore_cart')
        ]);
        
        $actions = sprintf(
            '<a href="%s" class="button lte-restore-cart">%s</a> <a href="%s" class="lte-ignore-cart">%s</a>',
            esc_url($restore_url),
            __('Restaurer mon panier', 'life-travel-excursion'),
            esc_url($ignore_url),
            __('Non merci', 'life-travel-excursion')
        );
        
        // Afficher la notice
        wc_add_notice(
            $message . $product_list . '<p class="lte-cart-actions">' . $actions . '</p>',
            'notice',
            ['lte_abandoned_cart' => true]
        );
    }
    
    /**
     * Récupère le cookie du panier abandonné
     *
     * @return string Contenu du cookie ou chaine vide
     */
    private function get_cart_cookie() {
        if (!isset($_COOKIE['lte_abandoned_cart'])) {
            return '';
        }
        
        return sanitize_text_field($_COOKIE['lte_abandoned_cart']);
    }
    
    /**
     * Sauvegarde la progression du panier via AJAX
     */
    public function ajax_save_cart_progress() {
        // Vérifier la sécurité
        if (!check_ajax_referer('lte_save_cart_progress', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Erreur de sécurité', 'life-travel-excursion')
            ]);
            wp_die();
        }
        
        // Vérifier l'existence d'un panier WooCommerce
        if (!function_exists('WC') || !isset(WC()->cart)) {
            wp_send_json_error([
                'message' => __('Panier non disponible', 'life-travel-excursion')
            ]);
            wp_die();
        }
        
        // Collecter les données du panier actuel
        $cart = WC()->cart;
        $cart_items = [];
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
            $quantity = $cart_item['quantity'];
            
            $item_data = [
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'quantity' => $quantity,
                'key' => $cart_item_key
            ];
            
            // Ajouter les métadonnées spécifiques aux excursions
            if (isset($cart_item['date_start'])) {
                $item_data['date_start'] = $cart_item['date_start'];
            }
            
            if (isset($cart_item['date_end'])) {
                $item_data['date_end'] = $cart_item['date_end'];
            }
            
            if (isset($cart_item['participants'])) {
                $item_data['participants'] = $cart_item['participants'];
            }
            
            // Sauvegarder d'autres métadonnées personnalisées
            if (isset($cart_item['meta_data']) && is_array($cart_item['meta_data'])) {
                $item_data['meta_data'] = $cart_item['meta_data'];
            }
            
            $cart_items[] = $item_data;
        }
        
        // Récupérer les informations de checkout si disponibles
        $checkout_fields = [];
        if (!empty($_POST['checkout_fields']) && is_array($_POST['checkout_fields'])) {
            foreach ($_POST['checkout_fields'] as $key => $value) {
                $checkout_fields[sanitize_key($key)] = sanitize_text_field($value);
            }
        }
        
        // Créer l'objet de données complet
        $cart_data = [
            'items' => $cart_items,
            'checkout_fields' => $checkout_fields,
            'timestamp' => time(),
            'session_id' => WC()->session->get_customer_id()
        ];
        
        // Ajouter des données de progression checkout si présentes
        if (!empty($_POST['checkout_step'])) {
            $cart_data['checkout_step'] = sanitize_text_field($_POST['checkout_step']);
        }
        
        // Ajouter des informations sur l'appareil si disponibles
        if (!empty($_POST['device_info']) && is_array($_POST['device_info'])) {
            $cart_data['device_info'] = $this->sanitize_device_info($_POST['device_info']);
        }
        
        // Sauvegarder dans la base de données si utilisateur connecté
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            global $wpdb;
            
            // Vérifier si la table existe
            $table_name = $wpdb->prefix . 'lte_abandoned_carts';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
                // Créer la table si elle n'existe pas
                $this->create_abandoned_carts_table();
            }
            
            // Vérifier si un panier existe déjà pour cet utilisateur
            $existing_cart = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}lte_abandoned_carts 
                WHERE user_id = %d AND recovered = 0",
                $user_id
            ));
            
            // Préparer les données pour la base
            $cart_contents = wp_json_encode($cart_data);
            $current_time = current_time('mysql');
            
            if ($existing_cart) {
                // Mettre à jour le panier existant
                $wpdb->update(
                    $wpdb->prefix . 'lte_abandoned_carts',
                    [
                        'cart_contents' => $cart_contents,
                        'last_updated' => $current_time
                    ],
                    ['id' => $existing_cart->id],
                    ['%s', '%s'],
                    ['%d']
                );
                
                $cart_id = $existing_cart->id;
            } else {
                // Créer un nouveau panier abandonné
                $wpdb->insert(
                    $wpdb->prefix . 'lte_abandoned_carts',
                    [
                        'user_id' => $user_id,
                        'cart_contents' => $cart_contents,
                        'created_at' => $current_time,
                        'last_updated' => $current_time,
                        'recovered' => 0
                    ],
                    ['%d', '%s', '%s', '%s', '%d']
                );
                
                $cart_id = $wpdb->insert_id;
            }
            
            // Journaliser pour debugging
            error_log(sprintf(
                'Life Travel: Panier abandonné sauvegardé pour l\'utilisateur %d, ID: %d, %d articles',
                $user_id,
                $cart_id,
                count($cart_items)
            ));
        } else {
            // Pour les utilisateurs non-connectés, sauvegarder dans un cookie
            $expiration = time() + 30 * DAY_IN_SECONDS; // 30 jours
            $cart_json = wp_json_encode($cart_data);
            
            // Définir le cookie
            setcookie(
                'lte_abandoned_cart',
                $cart_json,
                $expiration,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true // httponly
            );
            
            // Journaliser pour debugging
            error_log(sprintf(
                'Life Travel: Panier abandonné sauvegardé dans cookie, %d articles',
                count($cart_items)
            ));
        }
        
        // Déclencher une action pour extensions
        do_action('lte_cart_progress_saved', $cart_data, $user_id);
        
        // Retourner succès
        wp_send_json_success([
            'message' => __('Progression sauvegardée', 'life-travel-excursion'),
            'timestamp' => time(),
            'item_count' => count($cart_items)
        ]);
        
        wp_die();
    }
    
    /**
     * Sanitize les données d'appareil
     * 
     * @param array $device_info Informations sur l'appareil
     * @return array Données nettoyées
     */
    private function sanitize_device_info($device_info) {
        $allowed_keys = [
            'browser', 'browser_version', 'os', 'os_version', 'device_type',
            'screen_width', 'screen_height', 'connection_type', 'language', 'timezone'
        ];
        
        $sanitized = [];
        foreach ($device_info as $key => $value) {
            if (in_array($key, $allowed_keys)) {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Crée la table des paniers abandonnés si nécessaire
     */
    private function create_abandoned_carts_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lte_abandoned_carts';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            cart_contents longtext NOT NULL,
            created_at datetime NOT NULL,
            last_updated datetime NOT NULL,
            recovered tinyint(1) NOT NULL DEFAULT 0,
            recovery_date datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY recovered (recovered)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialisation
add_action('woocommerce_init', function() {
    Life_Travel_Checkout_Enhancement::get_instance();
});
