<?php
/**
 * Intégration du plugin Life Travel Excursion avec le site web Life Travel
 * 
 * Ce fichier gère l'intégration entre le plugin Life Travel Excursion
 * et le site web principale
 * 
 * @package Life Travel Excursion
 * @version 2.3.5
 * @modified 2.3.5 - Ajout du support multilingue avec TranslatePress
 */

defined('ABSPATH') || exit;

// Charger la configuration centralisée
require_once __DIR__ . '/config.php';

// Charger le gestionnaire de médias
require_once __DIR__ . '/media-manager.php';

// Charger les shortcodes
require_once __DIR__ . '/shortcodes.php';

// Charger l'optimisation réseau pour les connexions lentes (Cameroun)
require_once __DIR__ . '/network-optimization.php';

/**
 * Classe d'intégration du site Life Travel
 */
class Life_Travel_Site_Integration {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Styles et scripts spécifiques pour l'intégration
        add_action('wp_enqueue_scripts', array($this, 'enqueue_integration_assets'));
        
        // Ajouter des supports pour les éléments visuels
        add_action('after_setup_theme', array($this, 'add_theme_supports'));
        
        // Ajouter les passerelles de paiement
        add_filter('woocommerce_payment_gateways', array($this, 'add_iwomipay_gateways'));
        
        // Notification WhatsApp ou email selon le choix administrateur
        add_action('woocommerce_order_status_changed', array($this, 'notify_admin_order_status_change'), 10, 4);
        
        // Amélioration de l'affichage des prix dynamiques
        add_filter('woocommerce_cart_item_price', array($this, 'enhance_cart_price_display'), 10, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'enhance_cart_subtotal_display'), 10, 3);
        
        // Format responsive pour les appareils mobiles
        add_action('wp_head', array($this, 'add_mobile_viewport_meta'));
        
        // Sécurité renforcée pour les requêtes AJAX
        add_action('wp_ajax_life_travel_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_nopriv_life_travel_calculate_price', array($this, 'ajax_calculate_price'));
        
        // Vérifier la sécurité des paramètres et des cookies
        add_action('init', array($this, 'security_checks'));
        
        // Ajouter un indicateur de statut de connexion dans le footer
        add_action('wp_footer', array($this, 'add_connection_status_indicator'));
        
        // Support multilingue
        if (function_exists('trp_translate_gettext')) {
            $this->setup_translation_support();
        }
    }
    
    /**
     * Ajouter des supports thème pour les éléments visuels
     */
    public function add_theme_supports() {
        // Activer le support des logos personnalisés
        add_theme_support('custom-logo', array(
            'height'      => 120,
            'width'       => 240,
            'flex-height' => true,
            'flex-width'  => true,
            'header-text' => array('site-title', 'site-description'),
        ));
        
        // Activer le support des images mise en avant
        add_theme_support('post-thumbnails');
        
        // Activer le support HTML5 pour les galeries et médias
        add_theme_support('html5', array('gallery', 'caption', 'script', 'style', 'search-form'));
    }
    
    /**
     * Vérifie les problèmes de sécurité courants
     */
    public function security_checks() {
        // Vérifier les tentatives d'injection de paramètres suspects
        $suspicious_params = array('eval', 'exec', 'system', 'passthru', '<script', 'javascript:');
        
        foreach ($_GET as $key => $value) {
            foreach ($suspicious_params as $pattern) {
                if (is_string($value) && stripos($value, $pattern) !== false) {
                    // Journaliser la tentative suspecte
                    life_travel_log_security_issue(
                        sprintf('Suspicious GET parameter detected: %s with value containing %s', $key, $pattern)
                    );
                    
                    // Nettoyer le paramètre
                    $_GET[$key] = sanitize_text_field($value);
                }
            }
        }
        
        // Vérifier l'intégrité des cookies de session
        if (isset($_COOKIE['life_travel_session'])) {
            $session_data = sanitize_text_field($_COOKIE['life_travel_session']);
            
            // Vérifier le format attendu (par exemple, un format JSON valide)
            if (strpos($session_data, '{') === 0) {
                $decoded = json_decode($session_data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Cookie potentiellement manipulé, supprimer
                    setcookie('life_travel_session', '', time() - 3600, LIFE_TRAVEL_COOKIE_PATH, LIFE_TRAVEL_COOKIE_DOMAIN, LIFE_TRAVEL_SECURE_COOKIE, true);
                    life_travel_log_security_issue('Invalid session cookie format detected, cookie cleared');
                }
            }
        }
    }
    
    /**
     * Gère le calcul de prix via AJAX avec sécurité renforcée
     */
    public function ajax_calculate_price() {
        // Vérifier le nonce CSRF
        check_ajax_referer('life_travel_calculate_price_nonce', 'security');
        
        // Valider et assainir les entrées
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $participants = isset($_POST['participants']) ? absint($_POST['participants']) : 1;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        
        // Valider le produit
        if (!$product_id || get_post_type($product_id) !== 'product') {
            wp_send_json_error(array('message' => __('Produit invalide', 'life-travel-excursion')));
        }
        
        // Valider les dates
        if (!empty($start_date)) {
            // Valider le format de date
            $start_timestamp = strtotime($start_date);
            if (!$start_timestamp) {
                wp_send_json_error(array('message' => __('Format de date invalide', 'life-travel-excursion')));
            }
            
            // Vérifier que la date est dans le futur
            if ($start_timestamp < strtotime('today')) {
                wp_send_json_error(array('message' => __('La date doit être dans le futur', 'life-travel-excursion')));
            }
        } else {
            wp_send_json_error(array('message' => __('Date requise', 'life-travel-excursion')));
        }
        
        // Appeler la fonction de calcul de prix avec les paramètres validés
        if (function_exists('life_travel_excursion_get_pricing_details')) {
            $pricing = life_travel_excursion_get_pricing_details(
                $product_id,
                $participants,
                $start_date,
                $end_date,
                isset($_POST['extras']) ? array_map('absint', (array)$_POST['extras']) : array(),
                isset($_POST['activities']) ? array_map('absint', (array)$_POST['activities']) : array()
            );
            
            wp_send_json_success($pricing);
        } else {
            wp_send_json_error(array('message' => __('Fonctionnalité non disponible', 'life-travel-excursion')));
        }
    }
    
    /**
     * Enregistrer les styles et scripts pour l'intégration
     * Version améliorée avec gestion des connexions lentes et intermittentes
     * Optimisée pour la compatibilité cross-platform (iOS, Android et desktop)
     * Support amélioré pour les éléments visuels (images, vidéos, icônes, logo)
     */
    public function enqueue_integration_assets() {
        // Charger les ressources uniquement sur les pages pertinentes pour optimiser la performance
        $load_full_assets = is_product() || is_cart() || is_checkout() || 
                            is_account_page() || has_shortcode(get_the_content(), 'life_travel_booking_form');
        
        // Version consolidée des styles principaux
        wp_enqueue_style(
            'life-travel-integration', 
            plugins_url('assets/css/integration.min.css', dirname(__FILE__)),
            array(),
            defined('WP_DEBUG') && WP_DEBUG ? time() : LIFE_TRAVEL_VERSION
        );
        
        // Styles spécifiques pour les éléments visuels
        wp_enqueue_style(
            'life-travel-visuals', 
            plugins_url('assets/css/visual-elements.css', dirname(__FILE__)),
            array('life-travel-integration'),
            defined('WP_DEBUG') && WP_DEBUG ? time() : LIFE_TRAVEL_VERSION
        );
        
        // Styles optimisés pour les connexions lentes (Cameroun)
        wp_enqueue_style(
            'life-travel-slow-connection', 
            plugins_url('assets/css/slow-connection.css', dirname(__FILE__)),
            array('life-travel-visuals'),
            defined('WP_DEBUG') && WP_DEBUG ? time() : LIFE_TRAVEL_VERSION
        );
        
        // Styles pour le template de galerie
        if (is_page_template('templates/image-gallery-template.php')) {
            wp_enqueue_style(
                'life-travel-gallery-template', 
                plugins_url('assets/css/gallery-template.css', dirname(__FILE__)),
                array('life-travel-visuals'),
                defined('WP_DEBUG') && WP_DEBUG ? time() : LIFE_TRAVEL_VERSION
            );
        }
        
        // Scripts principaux avec gestion avancée des événements et connexions intermittentes
        wp_enqueue_script(
            'life-travel-integration',
            plugins_url('assets/js/integration.min.js', dirname(__FILE__)),
            array('jquery'),
            defined('WP_DEBUG') && WP_DEBUG ? time() : LIFE_TRAVEL_VERSION,
            true
        );
        
        // Chargement des scripts pour les éléments visuels et media
        wp_enqueue_script(
            'life-travel-media-handler',
            plugins_url('assets/js/media-handler.min.js', dirname(__FILE__)),
            array('jquery'),
            LIFE_TRAVEL_VERSION,
            true
        );
        
        // Script pour la gestion des connexions lentes (optimisé pour le Cameroun)
        wp_enqueue_script(
            'life-travel-connection-manager',
            plugins_url('assets/js/connection-manager.min.js', dirname(__FILE__)),
            array('jquery', 'life-travel-media-handler'),
            LIFE_TRAVEL_VERSION,
            true
        );
        
        // Chargement conditionnel : styles spécifiques aux appareils mobiles
        if (wp_is_mobile()) {
            wp_enqueue_style(
                'life-travel-mobile',
                plugins_url('assets/css/mobile.min.css', dirname(__FILE__)),
                array('life-travel-integration'),
                LIFE_TRAVEL_VERSION
            );
        }
        
        // Styles spécifiques pour TranslatePress
        if (function_exists('trp_translate_gettext')) {
            wp_enqueue_style(
                'life-travel-translatepress',
                plugins_url('assets/css/translatepress-compat.css', dirname(__FILE__)),
                array('life-travel-integration'),
                LIFE_TRAVEL_VERSION
            );
        }
        
        // Détection d'appareils iOS pour optimisations spécifiques
        if ($this->is_ios_device()) {
            wp_enqueue_script(
                'life-travel-ios-fixes',
                plugins_url('assets/js/ios-fixes.min.js', dirname(__FILE__)),
                array('life-travel-integration'),
                LIFE_TRAVEL_VERSION,
                true
            );
        }
        
        // Si la page contient des galeries d'images ou vidéos, charger les scripts appropriés
        if (has_shortcode(get_the_content(), 'gallery') || has_shortcode(get_the_content(), 'life_travel_gallery') || $load_full_assets) {
            wp_enqueue_style('life-travel-lightbox', plugins_url('assets/css/lightbox.min.css', dirname(__FILE__)));
            wp_enqueue_script('life-travel-lightbox', plugins_url('assets/js/lightbox.min.js', dirname(__FILE__)), array('jquery'), LIFE_TRAVEL_VERSION, true);
        }
        
        // Localization pour JavaScript avec support multilingue
        $i18n_texts = array(
            'invalid_form' => esc_html__('Veuillez vérifier les informations saisies.', 'life-travel-excursion'),
            'server_error' => esc_html__('Erreur de communication avec le serveur.', 'life-travel-excursion'),
            'session_expired' => esc_html__('Votre session a expiré. Veuillez rafraîchir la page.', 'life-travel-excursion'),
            'offline_mode' => esc_html__('Vous êtes en mode hors ligne. Vos données seront synchronisées quand la connexion sera rétablie.', 'life-travel-excursion'),
            'cart_updated' => esc_html__('Votre panier a été mis à jour.', 'life-travel-excursion'),
            'error_date' => esc_html__('Veuillez sélectionner une date valide.', 'life-travel-excursion'),
            'error_participants' => esc_html__('Le nombre de participants est incorrect.', 'life-travel-excursion'),
            'processing' => esc_html__('Traitement en cours...', 'life-travel-excursion'),
            'platform_ios' => esc_html__('Appareil iOS détecté', 'life-travel-excursion'),
            'platform_android' => esc_html__('Appareil Android détecté', 'life-travel-excursion')
        );
        
        // Appliquer les traductions si TranslatePress est actif
        if (function_exists('trp_translate_gettext')) {
            foreach ($i18n_texts as $key => $text) {
                $i18n_texts[$key] = trp_translate_gettext($text, $text, 'life-travel-excursion');
            }
        }
        
        // Passer toutes les données nécessaires au script JavaScript
        wp_localize_script('life-travel-integration', 'LifeTravelParams', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'calculate_price' => life_travel_get_nonce('calculate_price'),
                'add_to_cart' => life_travel_get_nonce('add_to_cart'),
                'check_availability' => life_travel_get_nonce('check_availability'),
                'update_cart' => life_travel_get_nonce('update_cart'),
                'process_checkout' => life_travel_get_nonce('process_checkout')
            ),
            'i18n' => $i18n_texts,
            'config' => array(
                'min_booking_advance' => LIFE_TRAVEL_MIN_BOOKING_ADVANCE,
                'max_participants' => LIFE_TRAVEL_MAX_PARTICIPANTS,
                'autosave_interval' => 30, // En secondes
            ),
            'currency_symbol' => 'FCFA',
            'currency_position' => 'right',
            'decimal_separator' => ',',
            'thousand_separator' => ' ',
            'current_language' => function_exists('trp_get_current_language') ? trp_get_current_language() : 'fr_FR',
            'visual_elements' => array(
                'logo_url' => wp_get_attachment_url(get_option('life_travel_logo_id')),
                'default_background' => wp_get_attachment_url(get_option('life_travel_main_background_id')),
                'theme_color' => '#0073B2',
                'theme_secondary_color' => '#8BC84B',
                'placeholder_image' => plugins_url('assets/img/placeholder.jpg', dirname(__FILE__)),
                'image_sizes' => apply_filters('life_travel_image_sizes', array(
                    'thumbnail' => '300x200',
                    'medium' => '600x400',
                    'large' => '1200x800',
                    'banner' => '1920x600',
                    'logo' => '240x120'
                ))
            )
        ));
        
        // Ajouter les données de l'utilisateur connecté si pertinent
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $script_data = apply_filters('life_travel_user_data', array(
                'username' => esc_html($current_user->user_login),
                'email' => esc_html($current_user->user_email),
                'avatar' => get_avatar_url($current_user->ID, array('size' => 50))
            ));
            wp_localize_script('life-travel-integration', 'LifeTravelUser', $script_data);
        }
        
        // Support spécifique pour les formats d'image dans le contenu
        add_filter('the_content', array($this, 'enhance_content_images'), 20);
    }
    
    /**
     * Améliore les images dans le contenu avec des attributs de chargement et adaptatifs
     * 
     * @param string $content Le contenu
     * @return string Le contenu amélioré
     */
    public function enhance_content_images($content) {
        // Ne pas traiter le contenu dans l'admin
        if (is_admin()) {
            return $content;
        }
        
        // Ne pas traiter si le contenu ne contient pas d'images
        if (!stripos($content, '<img')) {
            return $content;
        }
        
        // Ajouter des attributs loading="lazy" et srcset pour les images qui n'en ont pas
        $content = preg_replace_callback('/<img([^>]+)>/i', function($matches) {
            $img_html = $matches[0];
            $atts = $matches[1];
            
            // Ne pas modifier les images qui ont déjà un attribut loading
            if (strpos($atts, 'loading=') !== false) {
                return $img_html;
            }
            
            // Ajouter loading="lazy" pour toutes les images sauf la première du contenu
            static $first_image = true;
            if ($first_image) {
                $first_image = false;
                return $img_html;
            } else {
                $img_html = str_replace('<img', '<img loading="lazy"', $img_html);
                return $img_html;
            }
        }, $content);
        
        return $content;
    }
    
    /**
     * Initialise les scripts et données côté client
     */
    public function initialize_client_scripts() {
        $script_data = array();
        $script_data['user'] = array('logged_in' => is_user_logged_in());
            
        // Transmettre les données aux scripts
        wp_localize_script('life-travel-excursion-frontend', 'lifeTravel', $script_data);
    
        // Charger le script de pixel Facebook si configuré
        $pixel_id = get_option('life_travel_facebook_pixel_id');
        if (!empty($pixel_id)) {
            add_action('wp_head', function() use ($pixel_id) {
            ?>
            <!-- Meta Pixel Code -->
            <script>
            !function(f,b,e,v,n,t,s) {
                if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                n.callMethod.apply(n,arguments):n.queue.push(arguments)};
                if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
                n.queue=[];t=b.createElement(e);t.async=!0;
                t.src=v;s=b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t,s)}(window, document,'script',
                'https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', '<?php echo esc_js($pixel_id); ?>');
                fbq('track', 'PageView');
            </script>
            <noscript>
                <img height="1" width="1" style="display:none" 
                src="https://www.facebook.com/tr?id=<?php echo esc_attr($pixel_id); ?>&ev=PageView&noscript=1" />
            </noscript>
            <!-- End Meta Pixel Code -->
            <?php
            }, 999);
        }
    }
    
    
    /**
     * Ajoute un indicateur de statut de connexion dans le footer
     */
    public function add_connection_status_indicator() {
        echo '<div id="connection-status" class="connection-status online">Connecté</div>';
        
        // Styles CSS inline pour l'indicateur de statut
        echo '<style>
            .connection-status {
                position: fixed;
                bottom: 10px;
                right: 10px;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: bold;
                z-index: 9999;
                opacity: 0.8;
                transition: all 0.3s ease;
            }
            .connection-status.online {
                background-color: #4CAF50;
                color: white;
                display: none;
            }
            .connection-status.offline {
                background-color: #F44336;
                color: white;
                display: block;
            }
        </style>';
    }
    
    /**
     * Ajouter les passerelles de paiement IwomiPay
     * 
     * @param array $gateways Liste des passerelles de paiement
     * @return array Passerelles de paiement mises à jour
     */
    public function add_iwomipay_gateways($gateways) {
        // Utilisation du chemin de plugin sécurisé
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        
        // Tableau des passerelles à vérifier avec leurs classes correspondantes
        $payment_gateways = array(
            'class-iwomipay-card-gateway.php' => 'WC_Gateway_IwomiPay_Card',
            'class-iwomipay-momo-gateway.php' => 'WC_Gateway_IwomiPay_Momo',
            'class-iwomipay-orange-gateway.php' => 'WC_Gateway_IwomiPay_Orange'
        );
        
        // Vérifier si le répertoire des passerelles de paiement existe
        $gateway_dir = trailingslashit($plugin_dir) . 'payment-gateways';
        
        if (file_exists($gateway_dir) && is_dir($gateway_dir)) {
            foreach ($payment_gateways as $file => $class) {
                $file_path = $gateway_dir . '/' . $file;
                
                // Vérification de sécurité du fichier
                if (file_exists($file_path) && is_readable($file_path)) {
                    // Chemin validé, inclusion sécurisée
                    require_once $file_path;
                    
                    // Vérifier si la classe existe avant de l'ajouter
                    if (class_exists($class)) {
                        $gateways[] = $class;
                    }
                }
            }
        }
        
        return $gateways;
    }
    
    /**
     * Notification de l'administrateur lors d'un changement de statut de commande
     * 
     * @param int $order_id ID de la commande
     * @param string $old_status Ancien statut
     * @param string $new_status Nouveau statut
     * @param WC_Order $order Objet de la commande
     */
    public function notify_admin_order_status_change($order_id, $old_status, $new_status, $order) {
        // Validation des données d'entrée
        $order_id = absint($order_id);
        if (!$order_id || !$order instanceof WC_Order) {
            return;
        }
        
        // Liste des statuts significatifs pour lesquels notifier
        $significant_statuses = array('processing', 'completed', 'on-hold', 'cancelled', 'refunded', 'failed');
        
        if (!in_array($new_status, $significant_statuses, true)) {
            return;
        }
        
        // Vérifier si la commande contient des produits de type excursion
        $has_excursion = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && 'excursion' === $product->get_type()) {
                $has_excursion = true;
                break;
            }
        }
        
        if (!$has_excursion) {
            return;
        }

        // Préparer le message
        $message = sprintf(
            esc_html__('La commande #%s est passée de "%s" à "%s".', 'life-travel-excursion'),
            $order->get_order_number(),
            wc_get_order_status_name($old_status),
            wc_get_order_status_name($new_status)
        );

        // Informations client (avec échappement des données)
        $message .= "\n\n" . esc_html__('Informations client:', 'life-travel-excursion') . "\n";
        $message .= esc_html__('Nom:', 'life-travel-excursion') . ' ' . esc_html($order->get_billing_first_name()) . ' ' . esc_html($order->get_billing_last_name()) . "\n";
        $message .= esc_html__('Email:', 'life-travel-excursion') . ' ' . esc_html($order->get_billing_email()) . "\n";
        $message .= esc_html__('Téléphone:', 'life-travel-excursion') . ' ' . esc_html($order->get_billing_phone()) . "\n";

        // Récapitulatif des excursions
        $message .= "\n" . esc_html__('Excursions commandées:', 'life-travel-excursion') . "\n";
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && 'excursion' === $product->get_type()) {
                $excursion_name = esc_html($product->get_name());
                $participants = isset($item['participants']) ? absint($item['participants']) : '?';
                
                // Validation de la date
                $start_date = '?';
                if (isset($item['start_date']) && !empty($item['start_date'])) {
                    $date_timestamp = strtotime($item['start_date']);
                    if ($date_timestamp !== false) {
                        $start_date = date_i18n(get_option('date_format'), $date_timestamp);
                    }
                }
                
                $message .= sprintf("- %s | %s participant(s) | %s\n",
                    $excursion_name,
                    $participants,
                    $start_date
                );
            }
        }

        // Total de la commande
        $message .= "\n" . esc_html__('Total:', 'life-travel-excursion') . ' ' . $order->get_formatted_order_total() . "\n";
        $message .= esc_html__('Lien vers la commande:', 'life-travel-excursion') . ' ' . esc_url(admin_url('post.php?post=' . $order_id . '&action=edit'));

        // Méthode préférée pour les notifications (avec valeur par défaut sécurisée)
        $allowed_methods = array('email', 'sms', 'whatsapp');
        $notification_method = get_option('life_travel_notification_method', 'email');
        if (!in_array($notification_method, $allowed_methods, true)) {
            $notification_method = 'email'; // Méthode par défaut sécurisée
        }
        
        // Récupérer les administrateurs
        $admin_users = get_users(array('role' => 'administrator'));
        
        foreach ($admin_users as $admin) {
            $admin_email = sanitize_email($admin->user_email);
            $admin_phone = sanitize_text_field(get_user_meta($admin->ID, 'billing_phone', true));

            if ($notification_method === 'email' || empty($admin_phone)) {
                // Notification par email
                $subject = sprintf(esc_html__('Changement de statut - Commande #%s', 'life-travel-excursion'), 
                    $order->get_order_number());
                wp_mail($admin_email, $subject, $message);
            } else {
                // Notification par SMS ou WhatsApp si le numéro existe
                if (!empty($admin_phone)) {
                    if ($notification_method === 'sms') {
                        $this->send_sms_notification($admin_phone, $message);
                    } elseif ($notification_method === 'whatsapp') {
                        $this->send_whatsapp_notification($admin_phone, $message);
                    }
                }
            }
        }
    }
    
    /**
     * Amélioration de l'affichage des prix dans le panier
     * 
     * @param string $price Le prix formaté
     * @param array $cart_item L'élément du panier
     * @param string $cart_item_key La clé de l'élément du panier
     * @return string Le prix formaté amélioré
     */
    /**
     * Vérifie si l'appareil est un appareil iOS
     * 
     * @return bool True si iOS, false sinon
     */
    private function is_ios_device() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return (bool) (strpos($user_agent, 'iPhone') !== false || 
                       strpos($user_agent, 'iPad') !== false || 
                       strpos($user_agent, 'iPod') !== false || 
                       (strpos($user_agent, 'Mac OS') !== false && strpos($user_agent, 'Safari') !== false && strpos($user_agent, 'Mobile') !== false));
    }
    
    /**
     * Vérifie si l'appareil est un appareil Android
     * 
     * @return bool True si Android, false sinon
     */
    private function is_android_device() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return (bool) (strpos($user_agent, 'Android') !== false);
    }

    public function enhance_cart_price_display($price, $cart_item, $cart_item_key) {
        $product = $cart_item['data'];
        
        if ($product->get_type() !== 'excursion') {
            return $price;
        }
        
        // Récupérer les détails de tarification
        $participants = isset($cart_item['participants']) ? $cart_item['participants'] : 1;
        $price_per_person = $product->get_meta('_price_per_person') ?: $product->get_price();
        
        // Format responsive spécial pour mobile
        if (wp_is_mobile()) {
            return sprintf(
                '<div class="excursion-price mobile">%s <span class="price-details">/ personne</span></div>',
                wc_price($price_per_person)
            );
        } else {
            return sprintf(
                '<div class="excursion-price">%s <span class="price-details">/ personne</span></div>',
                wc_price($price_per_person)
            );
        }
    }
    
    /**
     * Amélioration de l'affichage des sous-totaux dans le panier
     * 
     * @param string $subtotal Le sous-total formaté
     * @param array $cart_item L'élément du panier
     * @param string $cart_item_key La clé de l'élément du panier
     * @return string Le sous-total formaté amélioré
     */
    public function enhance_cart_subtotal_display($subtotal, $cart_item, $cart_item_key) {
        $product = $cart_item['data'];
        
        if ($product->get_type() !== 'excursion') {
            return $subtotal;
        }
        
        // Récupérer les détails de tarification
        $participants = isset($cart_item['participants']) ? $cart_item['participants'] : 1;
        $start_date = isset($cart_item['start_date']) ? $cart_item['start_date'] : '';
        $end_date = isset($cart_item['end_date']) ? $cart_item['end_date'] : '';
        $vehicles_needed = isset($cart_item['vehicles_needed']) ? $cart_item['vehicles_needed'] : 1;
        $price_per_person = $product->get_meta('_price_per_person') ?: $product->get_price();
        
        // Si c'est un mobile, affichage compact
        if (wp_is_mobile()) {
            $breakdown = sprintf(
                '<div class="excursion-subtotal mobile">%s</div>
                <div class="subtotal-details mobile">
                    <span class="detail-item">%d personne(s)</span>
                </div>',
                $subtotal,
                $participants
            );
            
            // Afficher le nombre de véhicules si > 1
            if ($vehicles_needed > 1) {
                $breakdown .= sprintf(
                    '<div class="vehicle-details mobile">
                        <span class="vehicle-count">%d véhicules</span>
                    </div>',
                    $vehicles_needed
                );
            }
            
            return $breakdown;
        } else {
            // Affichage desktop complet
            $breakdown = sprintf(
                '<div class="excursion-subtotal">%s</div>
                <div class="subtotal-details">
                    <span class="detail-item">%d personne(s) × %s</span>',
                $subtotal,
                $participants,
                wc_price($price_per_person)
            );
            
            // Afficher les détails des véhicules si nécessaire
            if ($vehicles_needed > 1) {
                $vehicle_base_price = $product->get_meta('_vehicle_base_price') ?: 0;
                $additional_vehicle_cost = $product->get_meta('_additional_vehicle_cost') ?: 0;
                
                $breakdown .= sprintf(
                    '<div class="vehicle-details">
                        <span class="vehicle-info">%d véhicules (base: %s, +%s/véhicule supp.)</span>
                    </div>',
                    $vehicles_needed,
                    wc_price($vehicle_base_price),
                    wc_price($additional_vehicle_cost)
                );
            }
            
            $breakdown .= '</div>';
            
            return $breakdown;
        }
    }
    
    /**
     * Ajouter meta viewport pour les appareils mobiles
     * Version améliorée pour l'accessibilité (permet le zoom)
     */
    public function add_mobile_viewport_meta() {
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    }
    
    /**
     * Configure le support de traduction pour l'intégration du site
     * Compatible avec TranslatePress
     */
    private function setup_translation_support() {
        // Ajouter un filtre pour rendre les champs personnalisés traduisibles
        add_filter('trp_translatable_custom_post_meta', array($this, 'register_translatable_meta'), 10, 2);
        
        // Ajouter le sélecteur de langue dans le header si non présent
        if (!has_action('wp_head', 'trp_render_language_switcher')) {
            add_action('wp_head', array($this, 'add_language_detection_meta'));
        }
        
        // Gérer les traductions de messages dynamiques AJAX
        add_filter('trp_translatable_strings', array($this, 'add_dynamic_messages_to_translation'));
        
        // Support spécifique pour la détection de langue dans les API
        add_filter('rest_pre_dispatch', array($this, 'set_rest_api_language'), 10, 3);
    }
    
    /**
     * Enregistre les métadonnées à traduire pour les excursions
     * 
     * @param array $metas Liste des métadonnées traduisibles
     * @param int $post_id ID du post
     * @return array Liste mise à jour
     */
    public function register_translatable_meta($metas, $post_id) {
        // Métadonnées pour les excursions qui doivent être traduites
        $excursion_meta_keys = array(
            // Informations générales
            '_excursion_title',
            '_excursion_subtitle',
            '_excursion_description',
            '_excursion_short_description',
            
            // Détails techniques
            '_excursion_details',
            '_excursion_difficulty',
            '_excursion_duration',
            '_excursion_group_size',
            
            // Inclusions et exclusions
            '_excursion_included',
            '_excursion_not_included',
            '_excursion_extras',
            
            // Itinéraire et lieux
            '_excursion_itinerary',
            '_excursion_meeting_point',
            '_excursion_location_description',
            
            // Autres informations
            '_excursion_notes',
            '_excursion_faq',
            '_excursion_tips',
            '_excursion_requirements'
        );
        
        return array_merge($metas, $excursion_meta_keys);
    }
    
    /**
     * Ajoute des métadonnées pour la détection automatique de langue
     */
    public function add_language_detection_meta() {
        if (function_exists('trp_get_languages')) {
            $languages = trp_get_languages();
            foreach ($languages as $code => $language) {
                echo '<link rel="alternate" hreflang="' . esc_attr($code) . '" href="' . 
                     esc_url(add_query_arg('lang', $code)) . '" />' . "\n";
            }
        }
    }
    
    /**
     * Ajoute des messages dynamiques à la liste des chaînes traduisibles
     * 
     * @param array $strings Liste des chaînes à traduire
     * @return array Liste mise à jour
     */
    public function add_dynamic_messages_to_translation($strings) {
        // Messages d'erreur API
        $error_messages = array(
            'Connection failed',
            'Invalid request',
            'Session expired',
            'Please try again',
            'Cart updated',
            'Loading...',
            'Please select a date',
            'Please enter number of participants',
            'Booking confirmed',
            'Processing your request',
            'Calculating price',
            'Checking availability'
        );
        
        foreach ($error_messages as $message) {
            $strings[] = array(
                'id' => md5('excursion_js_' . $message),
                'original' => $message,
                'domain' => 'life-travel-js'
            );
        }
        
        return $strings;
    }
    
    /**
     * Configure la langue pour les requêtes de l'API REST
     * 
     * @param mixed $result Résultat initial 
     * @param WP_REST_Server $server Instance du serveur REST
     * @param WP_REST_Request $request Requête REST
     * @return mixed Résultat non modifié
     */
    public function set_rest_api_language($result, $server, $request) {
        if (function_exists('trp_get_languages') && isset($_REQUEST['lang'])) {
            $lang = sanitize_text_field($_REQUEST['lang']);
            $languages = trp_get_languages();
            
            if (array_key_exists($lang, $languages)) {
                add_filter('locale', function() use ($lang) {
                    return $lang;
                });
                
                // Forcer le chargement des traductions
                if (function_exists('trp_load_language_packs')) {
                    trp_load_language_packs($lang);
                }
            }
        }
        
        return $result;
    }
}

// Initialiser l'intégration
new Life_Travel_Site_Integration();
