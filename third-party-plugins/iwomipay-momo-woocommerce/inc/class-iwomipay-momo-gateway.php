<?php

/**
 * Classe de la passerelle de paiement MTN MoMo via IwomiPay
 */
class WC_Momo_Iwomipay extends WC_Payment_Gateway
{
    /**
     * Le type de passerelle (momo ou om)
     * @var string
     */
    public $gateway = '';

    /**
     * Sections de configuration
     * @var string
     */
    public $sectionOne = '';
    public $sectionTwo = '';
    public $sectionThree = '';
    public $sectionFour = '';

    /**
     * Paramètres de production
     * @var string
     */
    public $iwomipayUser = '';
    public $iwomipayPassword = '';
    public $iwomipayCrediKey = '';
    public $iwomipayCrediSecret = '';

    /**
     * Paramètres sandbox
     * @var string
     */
    public $iwomipayUserSandbox = '';
    public $iwomipayPasswordSandbox = '';
    public $iwomipayCrediKeySandbox = '';
    public $iwomipayCrediSecretSandbox = '';

    /**
     * Paramètres d'API
     * @var string
     */
    public $iwomipayApiBaseUrl = '';
    public $iwomipayApiBaseUrlSandbox = '';
    public $iwomipayEnvironment = '';
    public $iwomipayOptions = '';
    public $iwomipayToken = '';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        // AJOUT_IMPORTANT: Ceci est le plugin MTN Mobile Money (MoMo)
        // Pour Orange Money (OM), vous devez installer et configurer le plugin distinct iwomipay-om-woocommerce.zip
        // Ne modifiez pas cette ligne - les deux plugins doivent coexister
        $this->gateway = 'momo';

        $this->id                 = 'iwomipay_payment_' . $this->gateway;
        $this->icon               = apply_filters('woocommerce_iwomipay_icon',  plugins_url('../assets/' . $this->gateway . '-32.jpg', __FILE__));
        $this->has_fields         = false;
        $this->method_title       = __(($this->gateway === 'om' ? 'Orange Money' : 'MTN Mobile money') . ' (via IWOMIPAY)', $this->iwomipay_get_id());
        $this->method_description = __(($this->gateway === 'om' ? 'Orange Money' : 'MTN Mobile money') . '. Seemless Payment For Woocommerce', $this->iwomipay_get_id());

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->sectionOne   = $this->get_option('sectionOne');
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');

        /**
         * Production mode credentials
         */
        $this->sectionTwo = $this->get_option('sectionTwo');

        // AJOUT_IMPORTANT: Mettez ici vos identifiants de production IwomiPay
        // Account Identifiants
        $this->iwomipayUser = $this->iwomipay_init_fields('iwomipayUser');
        $this->iwomipayPassword = $this->iwomipay_init_fields('iwomipayPassword');

        // AJOUT_IMPORTANT: Ajoutez ici vos clés d'API fournies par IwomiPay
        // Credit Credentials 
        $this->iwomipayCrediKey = $this->iwomipay_init_fields('iwomipayCrediKey');
        $this->iwomipayCrediSecret = $this->iwomipay_init_fields('iwomipayCrediSecret');

        /**
         * Sandbox mode credentials
         */
        $this->sectionThree = $this->get_option('sectionThree');

        // AJOUT_IMPORTANT: Mettez ici vos identifiants sandbox fournis par IwomiPay pour les tests
        // Account Identifiants
        $this->iwomipayUserSandbox = $this->iwomipay_init_fields('iwomipayUserSandbox');
        $this->iwomipayPasswordSandbox = $this->iwomipay_init_fields('iwomipayPasswordSandbox');

        // AJOUT_IMPORTANT: Ajoutez ici vos clés d'API sandbox fournies par IwomiPay pour les tests
        // Credit Credentials 
        $this->iwomipayCrediKeySandbox = $this->iwomipay_init_fields('iwomipayCrediKeySandbox');
        $this->iwomipayCrediSecretSandbox = $this->iwomipay_init_fields('iwomipayCrediSecretSandbox');

        // Base Url
        $this->sectionFour = $this->get_option('sectionFour');
        // AJOUT_IMPORTANT: Configurez ici les URLs de l'API IwomiPay
        $this->iwomipayApiBaseUrl = $this->iwomipay_init_fields('apiBaseUrl'); // Généralement https://api.iwomipay.com/
        $this->iwomipayApiBaseUrlSandbox = $this->iwomipay_init_fields('apiBaseUrlSandbox'); // Généralement https://sandbox.iwomipay.com/
        
        // AJOUT_IMPORTANT: Définissez l'environnement ('production' ou 'sandbox')
        $this->iwomipayEnvironment = $this->iwomipay_init_fields('environment');

        $this->iwomipayOptions = [];
        $this->iwomipayToken = [
            'token' => null,
            'expired_time' => null
        ];

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        // Customer Emails
        // add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);

        add_filter( 'woocommerce_gateway_description', [$this, 'iwomipay_add_custom_field'], 20, 2 );

    }

    public function iwomipay_add_custom_field( $description, $payment_id ){
        //
        if( $this->id === $payment_id ){
            ob_start();
    
            echo '
                <style>
                    div.payment_method_iwomipay_payment_' . $this->gateway . '{margin-top: 5px;padding: 20px 10px 10px 15px;background: linear-gradient(90deg, #188235, #688614);}
                    .payment_method_iwomipay_payment_' . $this->gateway . ' p{color: #fff;font-size: 14px;}
                    .payment_method_iwomipay_payment_' . $this->gateway . ' > p {text-align: center;}
                    .iwomipay_phone_number_div {padding: 10px 0;position: relative;}
                    .iwomipay_phone_number_div .iwomipay_phone_number_span{position: absolute;bottom: 10px;padding: 0 10px;background: rgba(0, 0, 0, 0.05);height: 40px;line-height: 40px;}
                    .iwomipay_phone_number_div .iwomipay_phone_number_span + p{display: none;}
                    .iwomipay_phone_number_div #iwomipay_phone_number_' . $this->gateway . '_field{display: block;}
                    .payment_method_iwomipay_payment_' . $this->gateway . ' #iwomipay_phone_number_' . $this->gateway . '{width: 100%;height: 40px;min-height: 40px;line-height: 30px;padding-left: 65px;}
                </style>
                <div class="iwomipay_phone_number_div"><span class="iwomipay_phone_number_span">+237</span>';
                woocommerce_form_field( 'iwomipay_phone_number_' . $this->gateway , array(
                    'type'          => 'number',
                    'label'         => __("Phone number", $this->iwomipay_get_id()),
                    'class'         => array('form-row-wide'),
                    'required'      => true
                ), '');
            echo '<div>';
    
            $description .= ob_get_clean();
        }
        return $description;
    }

    public function iwomipay_get_id(): string
    {
        return 'wc-iwomipay-' . $this->gateway;
    }

    public function iwomipay_init_fields(string $option): string
    {
        return $this->get_option($option . $this->gateway);
    }

    public function iwomipay_get_fields(string $field): string
    {
        return $field . $this->gateway;
    }

    public function iwomipay_set_field(string $field, string $type = 'text', string $default = 'xxx', string $description = ''): array
    {
        return array(
            'title' => __($field, $this->iwomipay_get_id()),
            'type' => $type,
            'default' => __($default, $this->iwomipay_get_id()),
            'desc_tip' => true,
            'description' => __($description, $this->iwomipay_get_id())
        );
    }

    //Initialize Gateway Settings Form Fields
    public function init_form_fields()
    {

        $this->form_fields = apply_filters('wc_iwomipay_fields', array(

            'sectionOne' => array(
                'title' => __('Global Settings', $this->iwomipay_get_id()),
                'type' => 'title',
            ),
            'enabled' => array(
                'title'   => __('Enable/Disable', $this->iwomipay_get_id()),
                'type'    => 'checkbox',
                'label'   => __('Enable or Disable Iwomipay ' . strtoupper($this->gateway) . ' Payments', $this->iwomipay_get_id()),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __('Gateway Title', $this->iwomipay_get_id()),
                'type'        => 'text',
                'description' => __('Tire that will be displayed on your checkout page', $this->iwomipay_get_id()),
                'default'     => __(($this->gateway === 'om' ? 'Orange Money' : 'MTN Mobile money') . ' (via IWOMIPAY)', $this->iwomipay_get_id()),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', $this->iwomipay_get_id()),
                'type'        => 'textarea',
                'description' => __('Description that will be displayed on your checkout page', $this->iwomipay_get_id()),
                'default'     => __(($this->gateway === 'om' ? 'Orange Money' : 'MTN Mobile money') . ' Seemless Payment For Woocommerce', $this->iwomipay_get_id()),
                'desc_tip'    => true,
            ),

            // roduction mode credentials
            'sectionTwo' => array(
                'title' => __('Production Credentials', $this->iwomipay_get_id()),
                'type' => 'title',
            ),
            $this->iwomipay_get_fields('iwomipayUser') => $this->iwomipay_set_field('Username', 'text', 'xxx', 'API username, contact IWOMIPAY admin'),
            $this->iwomipay_get_fields('iwomipayPassword') => $this->iwomipay_set_field('Password', 'password', 'xxx', 'API password, contact IWOMIPAY admin'),
            $this->iwomipay_get_fields('iwomipayCrediKey') => $this->iwomipay_set_field(strtoupper($this->gateway) . ' Credi Apikey', 'text', 'xxx', 'Get it from the API keys menu in your Iwomipay Portal'),
            $this->iwomipay_get_fields('iwomipayCrediSecret') => $this->iwomipay_set_field(strtoupper($this->gateway) . ' Credi ApiSecret', 'password', 'xxx', 'Get it from the API keys menu in your Iwomipay Portal'),

            // Sandbox mode credential
            'sectionThree' => array(
                'title' => __('Sandbox Credentials', $this->iwomipay_get_id()),
                'type' => 'title',
            ),
            $this->iwomipay_get_fields('iwomipayUserSandbox') => $this->iwomipay_set_field('Username', 'text', 'xxx', 'Get it from the sandbox documentation on Iwomipay landing page'),
            $this->iwomipay_get_fields('iwomipayPasswordSandbox') => $this->iwomipay_set_field('Password', 'password', 'xxx', 'Get it from the sandbox documentation on Iwomipay landing page'),
            $this->iwomipay_get_fields('iwomipayCrediKeySandbox') => $this->iwomipay_set_field(strtoupper($this->gateway) . ' Credi ApiKey', 'text','xxx', 'Get it from the sandbox documentation on Iwomipay landing page'),
            $this->iwomipay_get_fields('iwomipayCrediSecretSandbox') => $this->iwomipay_set_field(strtoupper($this->gateway) . ' Credi ApiSecret', 'password', 'xxx', 'Get it from the sandbox documentation on Iwomipay landing page'),

            // API Mode
            'sectionFour' => array(
                'title' => __('Api mode', $this->iwomipay_get_id()),
                'type' => 'title',
            ),
            $this->iwomipay_get_fields('apiBaseUrl') => $this->iwomipay_set_field('API base url', 'text', 'https://www.pay.iwomitechnologies.com:8443/iwomipay_prod/', 'Get it from the documentation on Iwomipay landing page'),
            $this->iwomipay_get_fields('apiBaseUrlSandbox') => $this->iwomipay_set_field('Sandbox API base url', 'text', 'https://www.pay.iwomitechnologies.com:8443/iwomipay_sandbox/', 'Get it from the sandbox documentation on Iwomipay landing page'),

            $this->iwomipay_get_fields('environment') => array(
                'title'        => __('Payment Mode', $this->iwomipay_get_id()),
                'type'        => 'select',
                'desc_tip' => true,
                'description' => __('Select Sandbox when testing and Production when going live.', $this->iwomipay_get_id()),
                'default'    => 'test',
                'options' => array(
                    'test' => 'Sandbox',
                    'live' => 'Production'
                )
            ),
        ));
    }

    public function iwomipay_check_phone(?string $phone = null): ?string
    {

        if ($phone == null) {

            return null;
        }

        $lenght = strlen($phone);
        if ($lenght === 9) {

            return '237' . $phone;
        } else if ($lenght === 12) {

            return $phone;
        } else {

            return null;
        }
    }

    public function iwomipay_true_var(string $var): string
    {
        $sandbox = $var . 'Sandbox';
        return ($this->iwomipayEnvironment === 'live') ? $this->$var : $this->$sandbox;
    }

    public function iwomipay_get_token()
    {

        if ($this->iwomipayToken['expired_time'] && $this->iwomipayToken['expired_time'] < time()) {

            return $this->iwomipayToken['token'];
        }

        $login = $this->login($this->iwomipay_true_var('iwomipayUser'), $this->iwomipay_true_var('iwomipayPassword'));
        if (!$login['error']) {

            $login = json_decode($login['data']);
            if (isset($login->token) && $login->status == "01") {

                $this->iwomipayToken['token'] = $login->token;
                $this->iwomipayToken['expired_time'] = time() + 600;
                return $this->iwomipayToken['token'];
            } else {
    
                wc_add_notice('An unknown error while connecting. line: 254, Message: ' ($login->message ?? '/'), 'error');
                return null;
            }
        } else {

            wc_add_notice('Unknown API error. Line: 259, Message: ' . $login['error'], 'error');
            return null;
        }
    }

    public function iwomipay_get_key()
    {
        return [
            'api_key' => $this->iwomipay_true_var('iwomipayCrediKey'),
            'api_secret' => $this->iwomipay_true_var('iwomipayCrediSecret'),
            'gateway' => $this->gateway,
            'base_url' => $this->iwomipay_true_var('iwomipayApiBaseUrl')
        ];
    }

    public function login(string $username, string $password): array
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->iwomipay_true_var('iwomipayApiBaseUrl') . 'authenticate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                "username": "' . $username . '",
                "password": "' . $password . '"
            }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $error = curl_errno($curl) ? curl_error($curl) : null;
        curl_close($curl);

        return [
            'error' => $error,
            'data' => $response
        ];
    }

    public function iwomipay_check_transaction($transaction_id): array
    {

        $token = $this->iwomipay_get_token();
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->iwomipay_true_var('iwomipayApiBaseUrl') . 'iwomipayStatus/' . $transaction_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $token
            ),
        ));

        $response = curl_exec($curl);
        $error = curl_errno($curl) ? curl_error($curl) : null;
        curl_close($curl);

        return [
            'error' => $error,
            'data' => json_decode($response)
        ];
    }

    // We're processing the payments here
    public function process_payment($order_id)
    {

        global $woocommerce;
        $order = wc_get_order($order_id);
        $token = $this->iwomipay_get_token();

        if (!$token) {

            return false;
        }

        $key = $this->iwomipay_get_key();
        $phone = $this->iwomipay_check_phone($_POST['iwomipay_phone_number_' . $this->gateway] ?? null);

        if (!$phone) {

            wc_add_notice('Wrong phone number format. (6 ***)', 'error');
            return;
        }

        $body = wp_json_encode([
            'op_type' => 'credit',
            'type' => $key['gateway'],
            'amount' => $order->get_total(),
            'external_id' => 'jg' . time(),
            'motif' => 'Paiement website',
            'tel' => $phone,
            'country' => 'cm',
            'currency' => strtolower($order->get_currency())
        ]);

        $options = [
            'method'      => 'POST',
            'body'        => $body,
            'headers'     => [
                'AccountKey'    => base64_encode($key['api_key'] . ':' . $key['api_secret']),
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ],
            'data_format' => 'body',
            'httpversion' => '1.0',
            'timeout'     => 45,
            'sslverify'   => false
        ];

        // Your API interaction could be built with wp_remote_post()
        $response = wp_remote_post($key['base_url'] . 'iwomipay', $options);

        if (!is_wp_error($response)) {
            $body = json_decode($response['body'], true);
            file_put_contents('body.txt', json_encode($body));
            if (isset($body['status']) && $body['status'] == "1000") {

                $transaction_id = 'Transcation ID: ' . $body['external_id'];
                if ($body['message'] == "Operation Pending" || $body['message'] == "Status is Pending, Operation successfully initiated") {

                    $confirm = false;

                    for ($i = 0; $i < 12; $i++) {

                        $resultat = $this->iwomipay_check_transaction($body['internal_id']);
                        file_put_contents('check-' . $i . '.txt', json_encode($resultat));

                        sleep(5);
                        if(!$resultat['error']) {

                            $resultat = $resultat['data'];
                            if (isset($resultat->status, $resultat->message)) {

                                if ($resultat->status === "01" && ($resultat->message == 'SUCCESS' || $resultat->message == 'SUCCESSFUL' || $resultat->message == 'SUCCESSFULL')) {

                                    $confirm = true;
                                    break;
                                } elseif ($resultat->status === "100") {

                                    wc_add_notice($resultat->message, 'error');
                                    break;
                                }
                            }
                
                            sleep(10);
                        } else {

                            wc_add_notice('An error occurred while verifying the transaction (' . $transaction_id . '). Line: 423, Message: ' . $resultat['error'], 'error');
                            break;
                        }
                    }

                    if ($confirm === true) {

                        // $message = __('Payment successfully completed. ' . $transaction_id . '. Thank you for choosing IWOMIPAY.', 'textdomain');
                        // $message_type = 'success';
                        // wc_add_notice($message,$message_type);
                        $order->update_status('completed');
                        if (!defined('WOOCOMMERCE_VERSION')) {
                            define('WOOCOMMERCE_VERSION', WC()->version);
                        }
                        
                        // Gestion moderne du stock - utilisation exclusive de la méthode recommandée
                        wc_reduce_stock_levels($order->get_id());
                        WC()->cart->empty_cart();

                        return array(
                            'result'         => 'success',
                            'transaction_id' => $body['external_id'],
                            'redirect'       => $this->get_return_url($order)
                        );
                    } else {

                        $order->update_status('failed');
                        wc_add_notice('Transaction canceled. You did not confirm it on your mobile.', 'error');
                        return;
                    }
                } else {

                    wc_add_notice($body['message'] ?? 'An error has occurred internally, please try again later !!!', 'error');
                    return;
                }
            } else {

                wc_add_notice($body['message'] ?? 'Error when initiating the transaction. Please try again later.', 'error');
                return;
            }
        } else {

            wc_add_notice('Connection error. Line: 457, Message: ' . ($response->get_error_message() ?? '/'), 'error');
            return;
        }
    }

    // Output for the order received page.
    public function thankyou_page()
    {

        if ($this->description) {

            echo wpautop(wptexturize($this->description));
        }
    }
}
