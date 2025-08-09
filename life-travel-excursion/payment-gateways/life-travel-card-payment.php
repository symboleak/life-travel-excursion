<?php
/**
 * Life Travel Card Payment Gateway
 * 
 * Passerelle de paiement intégrée pour les paiements par carte
 * Remplace la dépendance externe à IwomiPay
 * 
 * @package Life Travel Excursion
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Life Travel Card Payment Gateway
 */
class Life_Travel_Card_Gateway extends WC_Payment_Gateway {
    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'life_travel_card';
        $this->icon               = LIFE_TRAVEL_EXCURSION_URL . 'assets/img/card-icon.png';
        $this->has_fields         = true;
        $this->method_title       = __('Life Travel Carte Bancaire', 'life-travel-excursion');
        $this->method_description = __('Accepte les paiements par carte bancaire (Visa, Mastercard, etc.)', 'life-travel-excursion');
        $this->supports           = array(
            'products',
            'refunds'
        );

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->testmode     = 'yes' === $this->get_option('testmode');
        $this->api_key      = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('api_key');
        $this->debug        = 'yes' === $this->get_option('debug');
        
        // Logging
        $this->log = new WC_Logger();
        
        // Save settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        
        // Payment listener/API hook
        add_action('woocommerce_api_life_travel_card_callback', array($this, 'process_webhook'));
        
        // Thank you page
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        
        // Customer Emails
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        
        // Add some scripts
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }
    
    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Activer/Désactiver', 'life-travel-excursion'),
                'type'    => 'checkbox',
                'label'   => __('Activer le paiement par carte', 'life-travel-excursion'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Titre', 'life-travel-excursion'),
                'type'        => 'text',
                'description' => __('Titre affiché au client lors du paiement', 'life-travel-excursion'),
                'default'     => __('Paiement par carte bancaire', 'life-travel-excursion'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'life-travel-excursion'),
                'type'        => 'textarea',
                'description' => __('Description affichée au client lors du paiement', 'life-travel-excursion'),
                'default'     => __('Payez de manière sécurisée avec votre carte bancaire.', 'life-travel-excursion'),
                'desc_tip'    => true,
            ),
            'instructions' => array(
                'title'       => __('Instructions', 'life-travel-excursion'),
                'type'        => 'textarea',
                'description' => __('Instructions affichées au client après sa commande', 'life-travel-excursion'),
                'default'     => __('Votre paiement a été traité avec succès. Vous recevrez une confirmation par email.', 'life-travel-excursion'),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __('Mode test', 'life-travel-excursion'),
                'type'        => 'checkbox',
                'label'       => __('Activer le mode test', 'life-travel-excursion'),
                'default'     => 'yes',
                'description' => __('Dans ce mode, les transactions ne sont pas réellement traitées.', 'life-travel-excursion'),
            ),
            'api_key' => array(
                'title'       => __('Clé API Production', 'life-travel-excursion'),
                'type'        => 'password',
                'description' => __('Votre clé API de production pour le service de paiement par carte', 'life-travel-excursion'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_api_key' => array(
                'title'       => __('Clé API Test', 'life-travel-excursion'),
                'type'        => 'password',
                'description' => __('Votre clé API de test pour le service de paiement par carte', 'life-travel-excursion'),
                'default'     => 'test_card_key',
                'desc_tip'    => true,
            ),
            'accepted_cards' => array(
                'title'       => __('Cartes acceptées', 'life-travel-excursion'),
                'type'        => 'multiselect',
                'description' => __('Sélectionnez les types de cartes acceptées', 'life-travel-excursion'),
                'options'     => array(
                    'visa'        => 'Visa',
                    'mastercard'  => 'Mastercard',
                    'amex'        => 'American Express',
                    'discover'    => 'Discover',
                    'diners'      => 'Diners Club',
                    'jcb'         => 'JCB'
                ),
                'default'     => array('visa', 'mastercard'),
                'desc_tip'    => true,
            ),
            'debug' => array(
                'title'       => __('Débogage', 'life-travel-excursion'),
                'type'        => 'checkbox',
                'label'       => __('Activer le journal de débogage', 'life-travel-excursion'),
                'default'     => 'no',
                'description' => __('Enregistre les événements de la passerelle dans le journal WooCommerce.', 'life-travel-excursion'),
            )
        );
    }
    
    /**
     * Logging method
     */
    public function log($message) {
        if ($this->debug) {
            $this->log->add($this->id, $message);
        }
    }
    
    /**
     * Load JS for the checkout
     */
    public function payment_scripts() {
        if (!is_checkout() || !$this->is_available()) {
            return;
        }
        
        // Use minified libraries if in production
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        
        wp_enqueue_script(
            'life-travel-card-js',
            LIFE_TRAVEL_EXCURSION_URL . 'assets/js/life-travel-card' . $suffix . '.js',
            array('jquery'),
            LIFE_TRAVEL_EXCURSION_VERSION,
            true
        );
        
        // Localize script with data
        $card_params = array(
            'test_mode'  => $this->testmode,
            'public_key' => $this->testmode ? 'pk_test_example' : 'pk_live_example',
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('life-travel-card-nonce'),
            'cards'      => $this->get_option('accepted_cards', array('visa', 'mastercard'))
        );
        
        wp_localize_script('life-travel-card-js', 'life_travel_card_params', $card_params);
        
        // CSS
        wp_enqueue_style(
            'life-travel-card-css',
            LIFE_TRAVEL_EXCURSION_URL . 'assets/css/life-travel-card' . $suffix . '.css',
            array(),
            LIFE_TRAVEL_EXCURSION_VERSION
        );
    }
    
    /**
     * Display payment fields and custom fields
     */
    public function payment_fields() {
        // Description du paiement
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
        
        // Cartes acceptées
        $accepted_cards = $this->get_option('accepted_cards', array('visa', 'mastercard'));
        
        ?>
        <div id="life-travel-card-form">
            <div class="life-travel-card-errors"></div>
            
            <?php if ($this->testmode): ?>
                <div class="life-travel-card-test-mode-notice">
                    <p style="background-color: #f8f8f8; padding: 10px; border-left: 4px solid #ddd;">
                        <?php esc_html_e('Mode test activé. Vous pouvez utiliser les numéros de carte de test suivants:', 'life-travel-excursion'); ?>
                        <br><code>4242 4242 4242 4242</code> (Visa)
                        <br><code>5555 5555 5555 4444</code> (Mastercard)
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="form-row form-row-wide">
                <label for="life_travel_card_number"><?php esc_html_e('Numéro de carte', 'life-travel-excursion'); ?> <span class="required">*</span></label>
                <div class="card-number-field">
                    <input id="life_travel_card_number" name="life_travel_card_number" type="text" class="input-text life-travel-card-input" placeholder="•••• •••• •••• ••••" autocomplete="cc-number" required>
                    <div class="card-type-icon"></div>
                </div>
            </div>
            
            <div class="form-row form-row-first">
                <label for="life_travel_card_expiry"><?php esc_html_e('Date d\'expiration', 'life-travel-excursion'); ?> <span class="required">*</span></label>
                <input id="life_travel_card_expiry" name="life_travel_card_expiry" type="text" class="input-text life-travel-card-input" placeholder="MM / YY" autocomplete="cc-exp" required>
            </div>
            
            <div class="form-row form-row-last">
                <label for="life_travel_card_cvc"><?php esc_html_e('Code de sécurité', 'life-travel-excursion'); ?> <span class="required">*</span></label>
                <input id="life_travel_card_cvc" name="life_travel_card_cvc" type="text" class="input-text life-travel-card-input" placeholder="CVC" autocomplete="cc-csc" required>
            </div>
            
            <div class="form-row form-row-wide">
                <label for="life_travel_card_holder"><?php esc_html_e('Titulaire de la carte', 'life-travel-excursion'); ?> <span class="required">*</span></label>
                <input id="life_travel_card_holder" name="life_travel_card_holder" type="text" class="input-text life-travel-card-input" placeholder="<?php esc_attr_e('Nom sur la carte', 'life-travel-excursion'); ?>" autocomplete="cc-name" required>
            </div>
            
            <div class="clear"></div>
        </div>
        <?php
    }
    
    /**
     * Process the payment
     */
    public function process_payment($order_id) {
        // Get the order
        $order = wc_get_order($order_id);
        
        // Log the payment attempt
        $this->log("Tentative de paiement par carte pour la commande #$order_id");
        
        // Check if we're in test mode
        if ($this->testmode) {
            $this->log("Mode test: Simulation d'un paiement réussi pour la commande #$order_id");
            
            // Generate a fake transaction ID
            $transaction_id = 'LTCARD' . strtoupper(uniqid());
            
            // Save transaction data
            update_post_meta($order_id, '_life_travel_card_transaction_id', $transaction_id);
            update_post_meta($order_id, '_life_travel_card_last4', '4242');
            update_post_meta($order_id, '_life_travel_card_brand', 'visa');
            
            // Mark as processing or completed
            $order->payment_complete($transaction_id);
            
            // Add order note
            $order->add_order_note(__('Paiement par carte traité avec succès en mode test.', 'life-travel-excursion'));
            
            // Empty cart
            WC()->cart->empty_cart();
            
            // Return success
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        
        // In production mode, we would proceed with a real payment processing via an API
        // For this implementation, we'll simulate a successful payment
        
        // Validate card data (this would be handled by the payment processor in reality)
        $card_number = sanitize_text_field($_POST['life_travel_card_number'] ?? '');
        $card_expiry = sanitize_text_field($_POST['life_travel_card_expiry'] ?? '');
        $card_cvc = sanitize_text_field($_POST['life_travel_card_cvc'] ?? '');
        $card_holder = sanitize_text_field($_POST['life_travel_card_holder'] ?? '');
        
        // Basic validation
        if (empty($card_number) || empty($card_expiry) || empty($card_cvc) || empty($card_holder)) {
            wc_add_notice(__('Veuillez remplir tous les champs de la carte bancaire.', 'life-travel-excursion'), 'error');
            return array(
                'result'   => 'fail',
                'redirect' => ''
            );
        }
        
        // Process the payment (simulation)
        $transaction_id = 'LTCARD' . strtoupper(uniqid());
        
        // Save transaction data
        update_post_meta($order_id, '_life_travel_card_transaction_id', $transaction_id);
        update_post_meta($order_id, '_life_travel_card_last4', substr(preg_replace('/[^0-9]/', '', $card_number), -4));
        update_post_meta($order_id, '_life_travel_card_brand', 'visa'); // Sample value
        
        // Mark as processing or completed
        $order->payment_complete($transaction_id);
        
        // Add order note
        $order->add_order_note(__('Paiement par carte traité avec succès.', 'life-travel-excursion'));
        $this->log("Paiement par carte réussi pour la commande #$order_id - Transaction ID: $transaction_id");
        
        // Empty cart
        WC()->cart->empty_cart();
        
        // Return success
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }
    
    /**
     * Process refunds
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        
        if (!$this->can_refund_order($order)) {
            return new WP_Error('error', __('Le remboursement n\'est pas pris en charge pour cette commande.', 'life-travel-excursion'));
        }
        
        $transaction_id = get_post_meta($order_id, '_life_travel_card_transaction_id', true);
        
        if (!$transaction_id) {
            return new WP_Error('error', __('Impossible de rembourser: identifiant de transaction manquant.', 'life-travel-excursion'));
        }
        
        $this->log("Demande de remboursement pour la commande #$order_id - Montant: $amount, Raison: $reason");
        
        // In production, we would make an API call to the payment processor
        // For this implementation, we'll simulate a successful refund
        
        $refund_transaction_id = 'REFUND-' . $transaction_id;
        
        $order->add_order_note(
            sprintf(__('Remboursement de %1$s effectué. Référence de remboursement: %2$s. Raison: %3$s', 'life-travel-excursion'),
                wc_price($amount),
                $refund_transaction_id,
                $reason
            )
        );
        
        $this->log("Remboursement réussi pour la commande #$order_id - ID de remboursement: $refund_transaction_id");
        
        return true;
    }
    
    /**
     * Check if we can refund an order
     */
    public function can_refund_order($order) {
        $transaction_id = get_post_meta($order->get_id(), '_life_travel_card_transaction_id', true);
        return $order->get_payment_method() === $this->id && !empty($transaction_id);
    }
    
    /**
     * Process webhook from payment provider
     */
    public function process_webhook() {
        $this->log('Webhook reçu pour le paiement par carte');
        
        // Vérifier la signature de sécurité
        if (!isset($_SERVER['HTTP_X_CARD_SIGNATURE'])) {
            $this->log('Webhook rejeté: Signature manquante');
            status_header(403);
            exit;
        }
        
        // Récupérer les données du webhook
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        
        if (!$data || !isset($data['transaction_id'])) {
            $this->log('Webhook rejeté: Données invalides');
            status_header(400);
            exit;
        }
        
        $transaction_id = sanitize_text_field($data['transaction_id']);
        $event_type = sanitize_text_field($data['event_type']);
        
        // Rechercher la commande associée à cette transaction
        $orders = wc_get_orders(array(
            'meta_key'   => '_life_travel_card_transaction_id',
            'meta_value' => $transaction_id,
            'limit'      => 1
        ));
        
        if (empty($orders)) {
            $this->log("Webhook rejeté: Transaction ID introuvable - $transaction_id");
            status_header(404);
            exit;
        }
        
        $order = $orders[0];
        $order_id = $order->get_id();
        
        $this->log("Transaction $transaction_id trouvée pour la commande #$order_id - Événement: $event_type");
        
        // Traiter selon le type d'événement
        if ($event_type === 'payment.succeeded') {
            // Paiement réussi
            $order->payment_complete($transaction_id);
            $order->add_order_note(__('Paiement par carte confirmé via webhook.', 'life-travel-excursion'));
            $this->log("Paiement confirmé via webhook pour la commande #$order_id");
        } elseif ($event_type === 'payment.failed') {
            // Paiement échoué
            $order->update_status('failed', __('Échec du paiement par carte, notifié via webhook.', 'life-travel-excursion'));
            $this->log("Échec de paiement notifié via webhook pour la commande #$order_id");
        } elseif ($event_type === 'charge.refunded') {
            // Remboursement
            $refunded_amount = isset($data['amount']) ? floatval($data['amount']) : 0;
            $refund_transaction_id = isset($data['refund_id']) ? sanitize_text_field($data['refund_id']) : '';
            
            $order->add_order_note(
                sprintf(__('Remboursement de %1$s traité via webhook. Référence: %2$s', 'life-travel-excursion'),
                    wc_price($refunded_amount),
                    $refund_transaction_id
                )
            );
            
            $this->log("Remboursement traité via webhook pour la commande #$order_id - Montant: $refunded_amount");
        }
        
        // Répondre au webhook
        status_header(200);
        echo json_encode(array('success' => true));
        exit;
    }
    
    /**
     * Output for the order received page.
     */
    public function thankyou_page($order_id) {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }
        
        $order = wc_get_order($order_id);
        $transaction_id = get_post_meta($order_id, '_life_travel_card_transaction_id', true);
        $last4 = get_post_meta($order_id, '_life_travel_card_last4', true);
        $brand = get_post_meta($order_id, '_life_travel_card_brand', true);
        
        if ($transaction_id) {
            echo '<h2>' . esc_html__('Détails du paiement par carte', 'life-travel-excursion') . '</h2>';
            echo '<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">';
            echo '<li class="woocommerce-order-overview__transaction-id">' . esc_html__('Référence de transaction:', 'life-travel-excursion') . ' <strong>' . esc_html($transaction_id) . '</strong></li>';
            
            if ($last4 && $brand) {
                echo '<li class="woocommerce-order-overview__card">' . esc_html__('Carte:', 'life-travel-excursion') . ' <strong>' . esc_html(ucfirst($brand)) . ' se terminant par ' . esc_html($last4) . '</strong></li>';
            }
            
            echo '</ul>';
        }
    }
    
    /**
     * Add content to the WC emails.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if ($sent_to_admin || !$order || $order->get_payment_method() !== $this->id) {
            return;
        }
        
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
        }
        
        $transaction_id = get_post_meta($order->get_id(), '_life_travel_card_transaction_id', true);
        $last4 = get_post_meta($order->get_id(), '_life_travel_card_last4', true);
        $brand = get_post_meta($order->get_id(), '_life_travel_card_brand', true);
        
        if ($transaction_id) {
            if ($plain_text) {
                echo esc_html__('Détails du paiement par carte', 'life-travel-excursion') . "\n\n";
                echo esc_html__('Référence de transaction:', 'life-travel-excursion') . ' ' . esc_html($transaction_id) . "\n";
                
                if ($last4 && $brand) {
                    echo esc_html__('Carte:', 'life-travel-excursion') . ' ' . esc_html(ucfirst($brand)) . ' se terminant par ' . esc_html($last4) . "\n\n";
                }
            } else {
                echo '<h2>' . esc_html__('Détails du paiement par carte', 'life-travel-excursion') . '</h2>';
                echo '<ul>';
                echo '<li>' . esc_html__('Référence de transaction:', 'life-travel-excursion') . ' <strong>' . esc_html($transaction_id) . '</strong></li>';
                
                if ($last4 && $brand) {
                    echo '<li>' . esc_html__('Carte:', 'life-travel-excursion') . ' <strong>' . esc_html(ucfirst($brand)) . ' se terminant par ' . esc_html($last4) . '</strong></li>';
                }
                
                echo '</ul>';
            }
        }
    }
}
