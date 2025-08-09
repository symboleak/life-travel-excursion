<?php
/**
 * Gateway Orange Money pour WooCommerce
 */
defined('ABSPATH') || exit;

// Charger la passerelle uniquement si WooCommerce est disponible
if (class_exists('WC_Payment_Gateway')) {
    class Life_Travel_Gateway_Orange_Money extends WC_Payment_Gateway {
        public function __construct() {
            $this->id                 = 'orange_money';
            $icon_id                  = get_theme_mod('lte_payment_icon_om', 0);
            $this->icon               = $icon_id ? wp_get_attachment_url($icon_id) : plugin_dir_url(__FILE__).'../assets/img/orange-money.png';
            $this->method_title       = __('Orange Money', 'life-travel-excursion');
            $this->method_description = __('Paiement via Orange Money', 'life-travel-excursion');
            $this->has_fields         = true;
            $this->init_form_fields();
            $this->init_settings();
            $this->title              = $this->get_option('title');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_lte_om_callback', array($this, 'callback_handler'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __('Activer/désactiver', 'life-travel-excursion'),
                    'type'    => 'checkbox',
                    'label'   => __('Activer Orange Money', 'life-travel-excursion'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title'       => __('Titre affiché', 'life-travel-excursion'),
                    'type'        => 'text',
                    'description' => __('Titre au checkout', 'life-travel-excursion'),
                    'default'     => __('Orange Money', 'life-travel-excursion'),
                    'desc_tip'    => true,
                ),
                'mode' => array(
                    'title'       => __('Mode', 'life-travel-excursion'),
                    'type'        => 'select',
                    'options'     => array('sandbox'=>__('Sandbox','life-travel-excursion'),'production'=>__('Production','life-travel-excursion')),
                    'default'     => 'sandbox',
                ),
                'username_prod' => array('title'=>__('Utilisateur prod','life-travel-excursion'),'type'=>'text','default'=>'','desc_tip'=>true),
                'password_prod' => array('title'=>__('Mot de passe prod','life-travel-excursion'),'type'=>'password','default'=>'','desc_tip'=>true),
                'key_prod'      => array('title'=>__('Clé API prod','life-travel-excursion'),'type'=>'text','default'=>'','desc_tip'=>true),
                'secret_prod'   => array('title'=>__('Secret API prod','life-travel-excursion'),'type'=>'password','default'=>'','desc_tip'=>true),
                'username_sandbox' => array('title'=>__('Utilisateur sandbox','life-travel-excursion'),'type'=>'text','default'=>'','desc_tip'=>true),
                'password_sandbox' => array('title'=>__('Mot de passe sandbox','life-travel-excursion'),'type'=>'password','default'=>'','desc_tip'=>true),
                'key_sandbox'      => array('title'=>__('Clé API sandbox','life-travel-excursion'),'type'=>'text','default'=>'','desc_tip'=>true),
                'secret_sandbox'   => array('title'=>__('Secret API sandbox','life-travel-excursion'),'type'=>'password','default'=>'','desc_tip'=>true),
            );
        }

        public function payment_fields() {
            // Affichage de la description si définie
            if ($description = $this->get_description()) {
                echo '<p>' . esc_html($description) . '</p>';
            }
            
            // Champ pour le numéro de téléphone Orange Money avec validation
            echo '<div class="form-row form-row-wide">';
            echo '<label for="om_phone">' . esc_html__('Numéro de téléphone Orange Money', 'life-travel-excursion') . ' <span class="required">*</span></label>';
            echo '<input type="tel" class="input-text" id="om_phone" name="om_phone" placeholder="+2376xxxxxxx" pattern="^\+237[0-9]{9}$" required autocomplete="tel" />';
            echo '<span class="description">' . esc_html__('Format: +237xxxxxxxxx', 'life-travel-excursion') . '</span>';
            echo '</div>';
            
            // Nonce CSRF pour sécuriser la transaction
            wp_nonce_field('lte_orange_money_payment', 'lte_om_nonce');
            
            // Avertissement pour environnement de test
            if ($this->get_option('mode') === 'sandbox') {
                echo '<div class="om-sandbox-notice">';
                echo '<p><strong>' . esc_html__('MODE TEST ACTIVÉ', 'life-travel-excursion') . '</strong> - ';
                echo esc_html__('Aucun paiement réel ne sera effectué.', 'life-travel-excursion') . '</p>';
                echo '</div>';
            }
        }

        public function validate_fields() {
            // Vérification du nonce CSRF
            if (!isset($_POST['lte_om_nonce']) || !wp_verify_nonce($_POST['lte_om_nonce'], 'lte_orange_money_payment')) {
                wc_add_notice(__('Erreur de sécurité. Veuillez rafraîchir la page.', 'life-travel-excursion'), 'error');
                return false;
            }
            
            // Validation du numéro de téléphone
            if (empty($_POST['om_phone'])) {
                wc_add_notice(__('Veuillez saisir votre numéro Orange Money', 'life-travel-excursion'), 'error');
                return false;
            }
            
            // Valider le format du numéro
            $phone = sanitize_text_field($_POST['om_phone']);
            if (!preg_match('/^\+237[0-9]{9}$/', $phone)) {
                wc_add_notice(__('Le numéro Orange Money doit être au format +237xxxxxxxxx', 'life-travel-excursion'), 'error');
                return false;
            }
            
            return true;
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', __('En attente de paiement Orange Money', 'life-travel-excursion'));
            wc_reduce_stock_levels($order_id);
            
            // Récupérer et préparer les données
            $mode = $this->get_option('mode');
            $user = $this->get_option($mode === 'production' ? 'username_prod' : 'username_sandbox');
            $pass = $this->get_option($mode === 'production' ? 'password_prod' : 'password_sandbox');
            $key  = $this->get_option($mode === 'production' ? 'key_prod' : 'key_sandbox');
            $secret = $this->get_option($mode === 'production' ? 'secret_prod' : 'secret_sandbox');
            $phone = sanitize_text_field($_POST['om_phone']);
            $amount = $order->get_total();
            $endpoint = $mode === 'production' ? 'https://api.orange-money.com/payment' : 'https://sandbox.orange-money.com/payment';
            
            // Générer un identifiant unique pour la transaction
            $transaction_id = 'OM-' . $order_id . '-' . time();
            $order->update_meta_data('_om_transaction_id', $transaction_id);
            $order->save();
            
            // Construire les données pour l'API
            $body = [
                'user' => $user,
                'amount' => $amount,
                'phone' => $phone,
                'order_id' => $order_id,
                'transaction_id' => $transaction_id,
                'callback_url' => add_query_arg(
                    ['order' => $order_id, 'token' => $this->generate_callback_token($order_id, $secret)],
                    WC()->api_request_url('lte_om_callback')
                ),
                'return_url' => $this->get_return_url($order)
            ];
            
            // Générer la signature HMAC
            $signature = hash_hmac('sha256', json_encode($body), $secret);
            
            // Envoi de la requête avec authentification (JSON)
            $response = wp_remote_post($endpoint, [
                'body' => wp_json_encode($body),
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($user . ':' . $pass),
                    'X-API-Key' => $key,
                    'X-Signature' => $signature,
                    'Content-Type' => 'application/json; charset=utf-8'
                ],
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $order->add_order_note('Erreur Orange Money: ' . $error_message);
                wc_add_notice(__('Erreur de connexion au service Orange Money: ', 'life-travel-excursion') . $error_message, 'error');
                return;
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($data['redirect_url'])) {
                $error = isset($data['error']) ? $data['error'] : __('Réponse invalide du service OM', 'life-travel-excursion');
                $order->add_order_note('Erreur Orange Money: ' . $error);
                wc_add_notice($error, 'error');
                return;
            }
            
            // Succès - redirection vers la page Orange Money
            return [
                'result' => 'success',
                'redirect' => $data['redirect_url']
            ];
        }
        
        /**
         * Génère un token sécurisé pour le callback
         */
        private function generate_callback_token($order_id, $secret) {
            return hash_hmac('sha256', 'order_' . $order_id, $secret);
        }

        /**
         * Gestionnaire des callbacks webhook d'Orange Money
         */
        public function callback_handler() {
            // Vérifier le token dans l'URL
            $order_id = isset($_GET['order']) ? intval($_GET['order']) : 0;
            $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
            
            if (!$order_id || !$token) {
                $this->log_error('Callback: Missing order ID or token');
                wp_die('Invalid request', 'Orange Money Callback', ['response' => 400]);
            }
            
            // Vérifier que l'ordre existe
            $order = wc_get_order($order_id);
            if (!$order) {
                $this->log_error('Callback: Order not found: ' . $order_id);
                wp_die('Order not found', 'Orange Money Callback', ['response' => 404]);
            }
            
            // Récupérer le secret pour vérifier le token
            $mode = $this->get_option('mode');
            $secret = $this->get_option($mode === 'production' ? 'secret_prod' : 'secret_sandbox');
            
            // Vérifier le token
            $expected_token = $this->generate_callback_token($order_id, $secret);
            if (!hash_equals($expected_token, $token)) {
                $this->log_error('Callback: Invalid token for order: ' . $order_id);
                wp_die('Invalid token', 'Orange Money Callback', ['response' => 401]);
            }
            
            // Récupérer et vérifier les données JSON
            $payload = file_get_contents('php://input');
            $signature = isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : '';
            
            // Vérifier la signature HMAC
            $calculated_signature = hash_hmac('sha256', $payload, $secret);
            if (!hash_equals($calculated_signature, $signature)) {
                $this->log_error('Callback: Invalid signature for payload');
                wp_die('Invalid signature', 'Orange Money Callback', ['response' => 401]);
            }

            // Traiter les données JSON
            $data = json_decode($payload, true);
            if (!$data || !isset($data['status'])) {
                $this->log_error('Callback: Invalid JSON data');
                wp_die('Invalid data', 'Orange Money Callback', ['response' => 400]);
            }
            
            // Mettre à jour le statut de la commande
            if ($data['status'] === 'success') {
                // Paiement réussi
                $order->payment_complete();
                $order->add_order_note(__('Paiement Orange Money confirmé. ID Transaction: ', 'life-travel-excursion') 
                    . (isset($data['transaction_id']) ? $data['transaction_id'] : 'N/A'));
            } else {
                // Paiement échoué
                $order->update_status('failed', __('Paiement Orange Money échoué: ', 'life-travel-excursion') 
                    . (isset($data['message']) ? $data['message'] : 'Raison inconnue'));
            }

            // Répondre avec succès
            echo wp_json_encode(['status' => 'ok']);
            exit;
        }
        /**
         * Journalise les erreurs de manière sécurisée
         */
        private function log_error($message) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Orange Money] ' . $message);
            }
        }
    }
} else {
    // Prévenir en admin si le fichier est chargé sans WooCommerce (sécurité supplémentaire)
    if (is_admin()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p>Life Travel: la passerelle Orange Money nécessite WooCommerce. Veuillez activer WooCommerce.</p></div>';
        });
    }
}
