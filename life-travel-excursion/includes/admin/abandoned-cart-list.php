<?php
/**
 * Liste des paniers abandonnés dans l'administration
 * 
 * Fournit une interface sécurisée pour afficher et gérer les paniers abandonnés
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

// S'assurer que la classe WP_List_Table est disponible
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Classe pour afficher les paniers abandonnés sous forme de tableau dans l'administration
 */
class Life_Travel_Abandoned_Cart_List extends WP_List_Table {
    
    /**
     * Table des paniers abandonnés
     * @var string
     */
    private $table_name;
    
    /**
     * Constructeur
     */
    public function __construct() {
        global $wpdb;
        
        parent::__construct([
            'singular' => __('Panier abandonné', 'life-travel-excursion'),
            'plural'   => __('Paniers abandonnés', 'life-travel-excursion'),
            'ajax'     => false,
        ]);
        
        $this->table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
    }
    
    /**
     * Vérifie si la table des paniers abandonnés existe
     * 
     * @return bool True si la table existe
     */
    private function table_exists() {
        global $wpdb;
        
        return $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
    }
    
    /**
     * Colonnes sans titres, utilisées pour la mise en page ou les actions
     * 
     * @return array Colonnes sans titres
     */
    public function get_hidden_columns() {
        return [];
    }
    
    /**
     * Colonnes par défaut
     * 
     * @return array Colonnes par défaut
     */
    function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'id'            => __('ID', 'life-travel-excursion'),
            'email'         => __('Email', 'life-travel-excursion'),
            'cart_contents' => __('Contenu du panier', 'life-travel-excursion'),
            'cart_total'    => __('Montant', 'life-travel-excursion'),
            'created_at'    => __('Créé le', 'life-travel-excursion'),
            'last_updated'  => __('Dernière mise à jour', 'life-travel-excursion'),
            'recovery'      => __('Récupération', 'life-travel-excursion'),
            'actions'       => __('Actions', 'life-travel-excursion'),
        ];
    }
    
    /**
     * Colonnes triables
     * 
     * @return array Colonnes triables
     */
    public function get_sortable_columns() {
        return [
            'id'           => ['id', true],
            'email'        => ['email', false],
            'cart_total'   => ['cart_total', false],
            'created_at'   => ['created_at', true],
            'last_updated' => ['last_updated', false],
        ];
    }
    
    /**
     * Traitement des données pour chaque colonne
     * 
     * @param object $item Données de la ligne
     * @param string $column_name Nom de la colonne
     * @return string Contenu HTML de la cellule
     */
    function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item['id'];
            
            case 'email':
                return esc_html($item['email']);
            
            case 'cart_total':
                return wc_price($item['cart_total']) . ' ' . esc_html($item['currency']);
            
            case 'created_at':
            case 'last_updated':
                return $this->format_date($item[$column_name]);
            
            case 'recovery':
                $status = $item['recovered'] == 1 
                    ? '<span class="recovered">' . __('Récupéré', 'life-travel-excursion') . '</span>' 
                    : '<span class="abandoned">' . __('Abandonné', 'life-travel-excursion') . '</span>';
                
                $sent = $item['reminder_sent'] == 1 
                    ? '<span class="sent">' . __('Email envoyé', 'life-travel-excursion') . '</span>' 
                    : '<span class="not-sent">' . __('Email non envoyé', 'life-travel-excursion') . '</span>';
                
                return "$status<br>$sent";
            
            case 'cart_contents':
                return $this->format_cart_contents($item['cart_contents']);
            
            default:
                return print_r($item, true); // Pour le débogage
        }
    }
    
    /**
     * Formate le contenu du panier pour l'affichage
     * 
     * @param string $cart_contents Contenu du panier sérialisé
     * @return string Contenu formaté HTML
     */
    private function format_cart_contents($cart_contents) {
        $contents = maybe_unserialize($cart_contents);
        
        if (!is_array($contents)) {
            return '<em>' . __('Contenu invalide', 'life-travel-excursion') . '</em>';
        }
        
        $output = '<ul class="cart-items">';
        
        if (isset($contents['product_id'])) {
            // Format simplifié pour une excursion
            $product_id = $contents['product_id'];
            $product = wc_get_product($product_id);
            
            if (!$product) {
                return '<em>' . __('Produit introuvable', 'life-travel-excursion') . '</em>';
            }
            
            $output .= '<li>';
            $output .= '<strong>' . esc_html($product->get_name()) . '</strong><br>';
            
            if (isset($contents['participants'])) {
                $output .= __('Participants', 'life-travel-excursion') . ': ' . esc_html($contents['participants']) . '<br>';
            }
            
            if (isset($contents['start_date'])) {
                $output .= __('Date de début', 'life-travel-excursion') . ': ' . esc_html($contents['start_date']) . '<br>';
            }
            
            $output .= '</li>';
        } else {
            // Format standard de panier WooCommerce
            foreach ($contents as $cart_item_key => $item) {
                if (!isset($item['product_id'])) continue;
                
                $product_id = $item['product_id'];
                $product = wc_get_product($product_id);
                
                if (!$product) continue;
                
                $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
                
                $output .= '<li>';
                $output .= '<strong>' . esc_html($product->get_name()) . '</strong> x ' . esc_html($quantity) . '<br>';
                
                // Afficher les données spécifiques aux excursions
                if (isset($item['participants'])) {
                    $output .= __('Participants', 'life-travel-excursion') . ': ' . esc_html($item['participants']) . '<br>';
                }
                
                if (isset($item['start_date'])) {
                    $output .= __('Date', 'life-travel-excursion') . ': ' . esc_html($item['start_date']) . '<br>';
                }
                
                $output .= '</li>';
            }
        }
        
        $output .= '</ul>';
        
        return $output;
    }
    
    /**
     * Colonne pour les cases à cocher
     * 
     * @param object $item Données de la ligne
     * @return string Contenu HTML de la case à cocher
     */
    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="cart_id[]" value="%s" />', 
            $item['id']
        );
    }
    
    /**
     * Colonne des actions
     * 
     * @param object $item Données de la ligne
     * @return string Contenu HTML des actions
     */
    function column_actions($item) {
        $actions = [];
        
        // Lien de récupération
        if ($item['recovered'] == 0) {
            $recovery_url = wp_nonce_url(
                admin_url('admin.php?page=life-travel-abandoned-carts&action=send_recovery&cart_id=' . $item['id']),
                'life_travel_recovery_email_' . $item['id'],
                'security'
            );
            
            $actions['recovery'] = sprintf(
                '<a href="%s" class="button button-primary">%s</a>',
                $recovery_url,
                __('Envoyer email', 'life-travel-excursion')
            );
        }
        
        // Lien de suppression
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=life-travel-abandoned-carts&action=delete&cart_id=' . $item['id']),
            'life_travel_delete_cart_' . $item['id'],
            'security'
        );
        
        $actions['delete'] = sprintf(
            '<a href="%s" class="button button-secondary delete-cart" onclick="return confirm(\'%s\');">%s</a>',
            $delete_url,
            __('Êtes-vous sûr de vouloir supprimer ce panier abandonné ?', 'life-travel-excursion'),
            __('Supprimer', 'life-travel-excursion')
        );
        
        // Lien de visualisation
        $view_url = wp_nonce_url(
            admin_url('admin.php?page=life-travel-abandoned-carts&action=view&cart_id=' . $item['id']),
            'life_travel_view_cart_' . $item['id'],
            'security'
        );
        
        $actions['view'] = sprintf(
            '<a href="%s" class="button button-secondary">%s</a>',
            $view_url,
            __('Détails', 'life-travel-excursion')
        );
        
        return implode(' ', $actions);
    }
    
    /**
     * Actions en masse disponibles
     * 
     * @return array Actions en masse
     */
    public function get_bulk_actions() {
        return [
            'send_recovery' => __('Envoyer email de récupération', 'life-travel-excursion'),
            'delete'        => __('Supprimer', 'life-travel-excursion'),
        ];
    }
    
    /**
     * Formate une date pour l'affichage
     * 
     * @param string $date Date au format MySQL
     * @return string Date formatée
     */
    private function format_date($date) {
        $timestamp = strtotime($date);
        
        if (!$timestamp) {
            return __('Date invalide', 'life-travel-excursion');
        }
        
        // Format selon les paramètres WordPress
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }
    
    /**
     * Prépare les données à afficher
     */
    public function prepare_items() {
        global $wpdb;
        
        // Vérifier si la table existe
        if (!$this->table_exists()) {
            return;
        }
        
        // Colonnes
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        // Traitement des actions en masse
        $this->process_bulk_action();
        
        // Pagination
        $per_page = 10;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // Ordre et tri
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'created_at';
        if (empty($orderby)) {
            $orderby = 'created_at';
        }
        
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'desc';
        if (!in_array(strtolower($order), ['asc', 'desc'])) {
            $order = 'desc';
        }
        
        // Récupérer les données
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY %s %s LIMIT %d OFFSET %d",
            $orderby,
            $order,
            $per_page,
            ($current_page - 1) * $per_page
        );
        
        // Utiliser une requête préparée sécurisée
        $safe_sql = str_replace('%s', $orderby, $sql);
        $safe_sql = str_replace('%s', $order, $safe_sql);
        
        $this->items = $wpdb->get_results($safe_sql, ARRAY_A);
        
        // Configurer la pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
    
    /**
     * Traitement des actions en masse
     */
    public function process_bulk_action() {
        global $wpdb;
        
        // Vérifier le nonce de sécurité
        if (isset($_REQUEST['_wpnonce']) && !empty($_REQUEST['_wpnonce'])) {
            $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
            
            if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                wp_die(__('Vérification de sécurité échouée', 'life-travel-excursion'));
            }
        }
        
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }
        
        // Traitement des actions individuelles
        if (isset($_REQUEST['cart_id']) && is_array($_REQUEST['cart_id'])) {
            $cart_ids = array_map('intval', $_REQUEST['cart_id']);
            
            switch ($action) {
                case 'send_recovery':
                    foreach ($cart_ids as $cart_id) {
                        $this->send_recovery_email($cart_id);
                    }
                    break;
                
                case 'delete':
                    foreach ($cart_ids as $cart_id) {
                        $wpdb->delete(
                            $this->table_name,
                            ['id' => $cart_id],
                            ['%d']
                        );
                    }
                    
                    // Log l'action pour audit de sécurité
                    life_travel_log_security_issue(
                        sprintf(__('Suppression de paniers abandonnés en masse: %s', 'life-travel-excursion'), 
                        implode(', ', $cart_ids)), 
                        'admin_delete'
                    );
                    break;
            }
            
            // Rediriger après le traitement
            wp_redirect(admin_url('admin.php?page=life-travel-abandoned-carts&action_completed=' . $action));
            exit;
        }
    }
    
    /**
     * Envoie un email de récupération pour un panier abandonné
     * 
     * @param int $cart_id ID du panier abandonné
     * @return bool Succès de l'envoi
     */
    private function send_recovery_email($cart_id) {
        global $wpdb;
        
        // Récupérer les données du panier
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $cart_id
        ), ARRAY_A);
        
        if (!$cart) {
            return false;
        }
        
        // Ne pas envoyer si déjà récupéré
        if ($cart['recovered'] == 1) {
            return false;
        }
        
        // Tenter d'envoyer l'email de récupération
        $sent = $this->send_email($cart);
        
        if ($sent) {
            // Mettre à jour le statut
            $wpdb->update(
                $this->table_name,
                ['reminder_sent' => 1],
                ['id' => $cart_id],
                ['%d'],
                ['%d']
            );
            
            // Log pour audit de sécurité
            life_travel_log_security_issue(
                sprintf(__('Email de récupération envoyé pour le panier ID: %d', 'life-travel-excursion'), $cart_id),
                'recovery_email'
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Envoie un email de récupération avec lien sécurisé
     * 
     * @param array $cart Données du panier abandonné
     * @return bool Succès de l'envoi
     */
    private function send_email($cart) {
        // Vérifier l'email
        if (empty($cart['email']) || !is_email($cart['email'])) {
            return false;
        }
        
        $to = sanitize_email($cart['email']);
        
        // Récupérer les infos du panier
        $cart_contents = maybe_unserialize($cart['cart_contents']);
        
        // Créer un lien de récupération sécurisé
        $recovery_key = base64_encode($cart['id'] . '|' . $cart['email']);
        $recovery_nonce = wp_create_nonce('life_travel_recover_cart_' . $cart['id']);
        
        $recovery_url = add_query_arg([
            'life_travel_recover' => $recovery_key,
            'recovery_nonce' => $recovery_nonce
        ], wc_get_page_permalink('shop'));
        
        // Obtenir le template d'email
        ob_start();
        include(LIFE_TRAVEL_EXCURSION_DIR . 'templates/emails/abandoned-cart-recovery.php');
        $message = ob_get_clean();
        
        // Remplacer les variables dynamiques
        $replacements = [
            '{{customer_name}}' => $this->get_customer_name($to),
            '{{recovery_link}}' => esc_url($recovery_url),
            '{{cart_contents}}' => $this->get_cart_summary_html($cart_contents),
            '{{cart_total}}' => wc_price($cart['cart_total']) . ' ' . $cart['currency'],
            '{{site_name}}' => get_bloginfo('name'),
            '{{expiry_time}}' => human_time_diff(strtotime($cart['created_at']), strtotime('+24 hours', strtotime($cart['created_at'])))
        ];
        
        foreach ($replacements as $placeholder => $value) {
            $message = str_replace($placeholder, $value, $message);
        }
        
        // En-têtes de l'email
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        // Sujet de l'email
        $subject = sprintf(
            __('Votre panier vous attend chez %s', 'life-travel-excursion'),
            get_bloginfo('name')
        );
        
        // Envoi de l'email
        $sent = wp_mail($to, $subject, $message, $headers);
        
        return $sent;
    }
    
    /**
     * Obtient le nom du client à partir de son email
     * 
     * @param string $email Email du client
     * @return string Nom du client ou partie locale de l'email
     */
    private function get_customer_name($email) {
        // Chercher un utilisateur existant
        $user = get_user_by('email', $email);
        
        if ($user) {
            $name = $user->display_name;
        } else {
            // Extraire la partie locale de l'email
            $parts = explode('@', $email);
            $name = $parts[0];
            
            // Nettoyer le nom
            $name = str_replace(['.', '_', '-'], ' ', $name);
            $name = ucwords($name);
        }
        
        return $name;
    }
    
    /**
     * Génère un résumé HTML du contenu du panier
     * 
     * @param array $cart_contents Contenu du panier
     * @return string HTML du résumé
     */
    private function get_cart_summary_html($cart_contents) {
        if (!is_array($cart_contents)) {
            return '';
        }
        
        ob_start();
        
        echo '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        echo '<tr style="background-color: #f8f9fa;">';
        echo '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">' . __('Produit', 'life-travel-excursion') . '</th>';
        echo '<th style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . __('Quantité', 'life-travel-excursion') . '</th>';
        echo '<th style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __('Détails', 'life-travel-excursion') . '</th>';
        echo '</tr>';
        
        if (isset($cart_contents['product_id'])) {
            // Format simplifié pour une excursion
            $product_id = $cart_contents['product_id'];
            $product = wc_get_product($product_id);
            
            if ($product) {
                echo '<tr>';
                echo '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($product->get_name()) . '</td>';
                echo '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">1</td>';
                
                echo '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">';
                if (isset($cart_contents['participants'])) {
                    echo __('Participants', 'life-travel-excursion') . ': ' . esc_html($cart_contents['participants']) . '<br>';
                }
                
                if (isset($cart_contents['start_date'])) {
                    echo __('Date', 'life-travel-excursion') . ': ' . esc_html($cart_contents['start_date']);
                }
                echo '</td>';
                
                echo '</tr>';
            }
        } else {
            // Format standard de panier WooCommerce
            foreach ($cart_contents as $cart_item_key => $item) {
                if (!isset($item['product_id'])) continue;
                
                $product_id = $item['product_id'];
                $product = wc_get_product($product_id);
                
                if (!$product) continue;
                
                $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
                
                echo '<tr>';
                echo '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($product->get_name()) . '</td>';
                echo '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . esc_html($quantity) . '</td>';
                
                echo '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">';
                if (isset($item['participants'])) {
                    echo __('Participants', 'life-travel-excursion') . ': ' . esc_html($item['participants']) . '<br>';
                }
                
                if (isset($item['start_date'])) {
                    echo __('Date', 'life-travel-excursion') . ': ' . esc_html($item['start_date']);
                }
                echo '</td>';
                
                echo '</tr>';
            }
        }
        
        echo '</table>';
        
        return ob_get_clean();
    }
}
