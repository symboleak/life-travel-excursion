<?php
/**
 * Life Travel Mobile Money Payment Gateway
 * 
 * Passerelle de paiement intégrée pour Mobile Money
 * Remplace la dépendance externe à IwomiPay
 * 
 * @package Life Travel Excursion
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Life Travel Mobile Money Payment Gateway
 */
class Life_Travel_Mobile_Money_Gateway extends WC_Payment_Gateway {
    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'life_travel_momo';
        $this->icon               = LIFE_TRAVEL_EXCURSION_URL . 'assets/img/momo-icon.png';
        $this->has_fields         = true;
        $this->method_title       = __('Life Travel Mobile Money', 'life-travel-excursion');
        $this->method_description = __('Accepte les paiements via Mobile Money (MTN, Orange, etc.)', 'life-travel-excursion');
        $this->supports           = array(
            'products'
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
        $this->supported_operators = $this->get_option('supported_operators', array('mtn', 'orange'));
        
        // Logging
        $this->log = new WC_Logger();
        
        // Save settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        
        // Payment listener/API hook
        add_action('woocommerce_api_life_travel_momo_callback', array($this, 'process_webhook'));
        
        // Thank you page
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        
        // Customer Emails
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }
    
    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Activer/Désactiver', 'life-travel-excursion'),
                'type'    => 'checkbox',
                'label'   => __('Activer le paiement Mobile Money', 'life-travel-excursion'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Titre', 'life-travel-excursion'),
                'type'        => 'text',
                'description' => __('Titre affiché au client lors du paiement', 'life-travel-excursion'),
                'default'     => __('Paiement Mobile Money', 'life-travel-excursion'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'life-travel-excursion'),
                'type'        => 'textarea',
                'description' => __('Description affichée au client lors du paiement', 'life-travel-excursion'),
                'default'     => __('Payez avec votre compte Mobile Money. Assurez-vous d\'avoir suffisamment de fonds disponibles.', 'life-travel-excursion'),
                'desc_tip'    => true,
            ),
            'instructions' => array(
                'title'       => __('Instructions', 'life-travel-excursion'),
                'type'        => 'textarea',
                'description' => __('Instructions affichées au client après sa commande', 'life-travel-excursion'),
                'default'     => __('Vous recevrez une notification sur votre téléphone pour confirmer le paiement. Veuillez valider la transaction pour finaliser votre commande.', 'life-travel-excursion'),
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
                'description' => __('Votre clé API de production pour le service Mobile Money', 'life-travel-excursion'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_api_key' => array(
                'title'       => __('Clé API Test', 'life-travel-excursion'),
                'type'        => 'password',
                'description' => __('Votre clé API de test pour le service Mobile Money', 'life-travel-excursion'),
                'default'     => 'test_momo_key',
                'desc_tip'    => true,
            ),
            'supported_operators' => array(
                'title'       => __('Opérateurs supportés', 'life-travel-excursion'),
                'type'        => 'multiselect',
                'description' => __('Sélectionnez les opérateurs Mobile Money supportés', 'life-travel-excursion'),
                'options'     => array(
                    'mtn'    => 'MTN Mobile Money',
                    'orange' => 'Orange Money',
                    'moov'   => 'Moov Money'
                ),
                'default'     => array('mtn', 'orange'),
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
     * Display payment fields and custom fields
     */
    public function payment_fields() {
        // Description du paiement
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
        
        // Opérateurs supportés
        $supported_operators = $this->supported_operators;
        if (!is_array($supported_operators)) {
            $supported_operators = array('mtn', 'orange');
        }
        
        ?>
        <div id="life-travel-momo-form">
            <p class="form-row form-row-wide">
                <label for="life_travel_momo_phone"><?php esc_html_e('Numéro de téléphone', 'life-travel-excursion'); ?> <span class="required">*</span></label>
                <input id="life_travel_momo_phone" name="life_travel_momo_phone" type="tel" class="input-text" placeholder="ex: 612345678" pattern="[0-9]{9}" required>
                <span class="description"><?php esc_html_e('Entrez votre numéro de téléphone Mobile Money (sans le préfixe international)', 'life-travel-excursion'); ?></span>
            </p>
            
            <p class="form-row form-row-wide">
                <label for="life_travel_momo_operator"><?php esc_html_e('Opérateur', 'life-travel-excursion'); ?> <span class="required">*</span></label>
                <select id="life_travel_momo_operator" name="life_travel_momo_operator" class="select" required>
                    <?php foreach ($supported_operators as $operator): ?>
                        <?php 
                        $operator_name = '';
                        switch ($operator) {
                            case 'mtn':
                                $operator_name = 'MTN Mobile Money';
                                break;
                            case 'orange':
                                $operator_name = 'Orange Money';
                                break;
                            case 'moov':
                                $operator_name = 'Moov Money';
                                break;
                            default:
                                $operator_name = ucfirst($operator);
                        }
                        ?>
                        <option value="<?php echo esc_attr($operator); ?>"><?php echo esc_html($operator_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <?php if ($this->testmode): ?>
                <div class="life-travel-momo-test-mode-notice">
                    <p style="background-color: #f8f8f8; padding: 10px; border-left: 4px solid #ddd;">
                        <?php esc_html_e('Mode test activé. Dans ce mode, aucun paiement réel ne sera effectué.', 'life-travel-excursion'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Process the payment
     */
    public function process_payment($order_id) {
        // Récupérer la commande
        $order = wc_get_order($order_id);
        
        // Vérifier les données du formulaire
        if (!isset($_POST['life_travel_momo_phone']) || !isset($_POST['life_travel_momo_operator'])) {
            wc_add_notice(__('Veuillez remplir tous les champs de paiement Mobile Money.', 'life-travel-excursion'), 'error');
            return;
        }
        
        $phone = sanitize_text_field($_POST['life_travel_momo_phone']);
        $operator = sanitize_text_field($_POST['life_travel_momo_operator']);
        
        // Valider le numéro de téléphone
        if (!preg_match('/^[0-9]{9}$/', $phone)) {
            wc_add_notice(__('Le numéro de téléphone doit contenir 9 chiffres, sans indicatif international.', 'life-travel-excursion'), 'error');
            return;
        }
        
        // Enregistrer les métadonnées de paiement
        update_post_meta($order_id, '_life_travel_momo_phone', $phone);
        update_post_meta($order_id, '_life_travel_momo_operator', $operator);
        
        // Générer un identifiant de transaction unique
        $transaction_id = 'LTMOMO' . strtoupper(uniqid());
        update_post_meta($order_id, '_life_travel_momo_transaction_id', $transaction_id);
        
        // Journalisation
        $this->log("Nouvelle demande de paiement Mobile Money - Commande #$order_id - Transaction ID: $transaction_id");
        
        // En mode test, simuler une transaction réussie
        if ($this->testmode) {
            $this->log("Mode test: Simulation d'une transaction réussie pour la commande #$order_id");
            
            // Mettre à jour le statut de la commande
            $order->update_status('on-hold', __('En attente de confirmation Mobile Money.', 'life-travel-excursion'));
            
            // Vider le panier
            WC()->cart->empty_cart();
            
            // Message de confirmation
            wc_add_notice(__('Paiement en attente de confirmation. Vous recevrez une notification sur votre téléphone.', 'life-travel-excursion'));
            
            // Rediriger vers la page de remerciement
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        
        // Dans un environnement de production, on initierait la transaction avec l'API Mobile Money
        // Exemple simplifié pour démonstration
        $api_endpoint = 'https://api.example.com/mobile-money/initiate';
        $amount = $order->get_total();
        $currency = $order->get_currency();
        $callback_url = home_url('wc-api/life_travel_momo_callback');
        
        $this->log("Préparation de la transaction pour la commande #$order_id - Montant: $amount $currency - Callback: $callback_url");
        
        // Simuler une réponse API positive pour le prototype
        $api_response = array(
            'success' => true,
            'transaction_id' => $transaction_id,
            'status' => 'pending'
        );
        
        if ($api_response['success']) {
            // Enregistrer les détails de la transaction
            update_post_meta($order_id, '_life_travel_momo_transaction_status', 'pending');
            
            // Mettre à jour le statut de la commande
            $order->update_status('on-hold', __('En attente de confirmation Mobile Money.', 'life-travel-excursion'));
            
            // Vider le panier
            WC()->cart->empty_cart();
            
            // Message de confirmation
            wc_add_notice(__('Paiement en attente de confirmation. Vous recevrez une notification sur votre téléphone.', 'life-travel-excursion'));
            
            // Rediriger vers la page de remerciement
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } else {
            // Gérer l'échec
            $error_message = isset($api_response['message']) ? $api_response['message'] : __('Impossible d\'initialiser le paiement Mobile Money.', 'life-travel-excursion');
            wc_add_notice($error_message, 'error');
            $this->log("Échec de la transaction pour la commande #$order_id - Erreur: $error_message");
            
            return array(
                'result'   => 'fail',
                'redirect' => ''
            );
        }
    }
    
    /**
     * Process webhook from payment provider
     */
    public function process_webhook() {
        $this->log('Webhook reçu pour le paiement Mobile Money');
        
        // Vérifier la signature de sécurité
        if (!isset($_SERVER['HTTP_X_MOMO_SIGNATURE'])) {
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
        $transaction_status = sanitize_text_field($data['status']);
        
        // Rechercher la commande associée à cette transaction
        $orders = wc_get_orders(array(
            'meta_key'   => '_life_travel_momo_transaction_id',
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
        
        $this->log("Transaction $transaction_id trouvée pour la commande #$order_id - Statut: $transaction_status");
        
        // Mettre à jour le statut de la transaction
        update_post_meta($order_id, '_life_travel_momo_transaction_status', $transaction_status);
        
        // Traiter selon le statut de la transaction
        if ($transaction_status === 'completed') {
            // Paiement réussi
            $order->payment_complete();
            $order->add_order_note(__('Paiement Mobile Money confirmé.', 'life-travel-excursion'));
            $this->log("Paiement confirmé pour la commande #$order_id");
        } elseif ($transaction_status === 'failed') {
            // Paiement échoué
            $order->update_status('failed', __('Paiement Mobile Money échoué.', 'life-travel-excursion'));
            $this->log("Paiement échoué pour la commande #$order_id");
        } elseif ($transaction_status === 'cancelled') {
            // Paiement annulé
            $order->update_status('cancelled', __('Paiement Mobile Money annulé.', 'life-travel-excursion'));
            $this->log("Paiement annulé pour la commande #$order_id");
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
        $phone = get_post_meta($order_id, '_life_travel_momo_phone', true);
        $operator = get_post_meta($order_id, '_life_travel_momo_operator', true);
        $transaction_id = get_post_meta($order_id, '_life_travel_momo_transaction_id', true);
        
        if ($phone && $operator && $transaction_id) {
            echo '<h2>' . esc_html__('Détails du paiement Mobile Money', 'life-travel-excursion') . '</h2>';
            echo '<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">';
            echo '<li class="woocommerce-order-overview__transaction-id">' . esc_html__('Référence de transaction:', 'life-travel-excursion') . ' <strong>' . esc_html($transaction_id) . '</strong></li>';
            echo '<li class="woocommerce-order-overview__phone">' . esc_html__('Numéro de téléphone:', 'life-travel-excursion') . ' <strong>' . esc_html($phone) . '</strong></li>';
            echo '<li class="woocommerce-order-overview__operator">' . esc_html__('Opérateur:', 'life-travel-excursion') . ' <strong>' . esc_html(ucfirst($operator)) . '</strong></li>';
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
        
        $phone = get_post_meta($order->get_id(), '_life_travel_momo_phone', true);
        $operator = get_post_meta($order->get_id(), '_life_travel_momo_operator', true);
        $transaction_id = get_post_meta($order->get_id(), '_life_travel_momo_transaction_id', true);
        
        if ($phone && $operator && $transaction_id) {
            if ($plain_text) {
                echo esc_html__('Détails du paiement Mobile Money', 'life-travel-excursion') . "\n\n";
                echo esc_html__('Référence de transaction:', 'life-travel-excursion') . ' ' . esc_html($transaction_id) . "\n";
                echo esc_html__('Numéro de téléphone:', 'life-travel-excursion') . ' ' . esc_html($phone) . "\n";
                echo esc_html__('Opérateur:', 'life-travel-excursion') . ' ' . esc_html(ucfirst($operator)) . "\n\n";
            } else {
                echo '<h2>' . esc_html__('Détails du paiement Mobile Money', 'life-travel-excursion') . '</h2>';
                echo '<ul>';
                echo '<li>' . esc_html__('Référence de transaction:', 'life-travel-excursion') . ' <strong>' . esc_html($transaction_id) . '</strong></li>';
                echo '<li>' . esc_html__('Numéro de téléphone:', 'life-travel-excursion') . ' <strong>' . esc_html($phone) . '</strong></li>';
                echo '<li>' . esc_html__('Opérateur:', 'life-travel-excursion') . ' <strong>' . esc_html(ucfirst($operator)) . '</strong></li>';
                echo '</ul>';
            }
        }
    }
}
