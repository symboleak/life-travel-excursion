<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/abandoned-cart-security-logger.php';
require_once __DIR__ . '/abandoned-cart-security-analyzer.php';
require_once __DIR__ . '/abandoned-cart-security-report.php';
require_once __DIR__ . '/abandoned-cart-security-chart.php';

class Abandoned_Cart_Security {
    private $analyzer;
    private $report;
    private $chart;
    private $option_name = 'acs_security_settings';

    public function __construct() {
        $this->analyzer = new Abandoned_Cart_Security_Analyzer();
        $this->report   = new Abandoned_Cart_Security_Report();
        $this->chart    = new Abandoned_Cart_Security_Chart();
        add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function register_admin_page() {
        add_menu_page(
            'Abandoned Cart Security',
            'Security Report',
            'manage_options',
            'acs-security-report',
            array( $this, 'render_admin_page' ),
            'dashicons-shield'
        );
    }

    public function register_settings() {
        register_setting( 'acs_security_group', $this->option_name, array( $this, 'sanitize_settings' ) );
        add_settings_section( 'acs_security_main', 'Detection Thresholds', null, 'acs-security-report' );
        add_settings_field(
            'fraud_token_threshold',
            'Token Fraud Attempts Threshold',
            array( $this, 'field_input' ),
            'acs-security-report',
            'acs_security_main',
            array( 'label_for' => 'fraud_token_threshold' )
        );
    }

    public function sanitize_settings( $input ) {
        $output                   = array();
        $output['fraud_token_threshold'] = isset( $input['fraud_token_threshold'] ) ? intval( $input['fraud_token_threshold'] ) : 10;
        return $output;
    }

    public function field_input( $args ) {
        $options = get_option( $this->option_name );
        $value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
        printf(
            '<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="1" class="small-text" />',
            esc_attr( $args['label_for'] ),
            esc_attr( $this->option_name ),
            esc_attr( $value )
        );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized user' );
        }
        ?>
        <div class="wrap">
            <h1>Abandoned Cart Security</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'acs_security_group' );
                do_settings_sections( 'acs-security-report' );
                submit_button();
                ?>
            </form>
            <h2>Security Report</h2>
            <div><?php echo $this->report->generate_report(); ?></div>
            <canvas id="securityChart"></canvas>
            <script>
                const ctx = document.getElementById('securityChart').getContext('2d');
                const chart = new Chart(ctx, {
                    type: 'bar',
                    data: <?php echo $this->chart->get_chart_data(); ?>,
                    options: {
                        title: {
                            display: true,
                            text: 'Security Chart'
                        },
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero: true
                                }
                            }]
                        }
                    }
                });
            </script>
        </div>
        <?php
    }
}

new Abandoned_Cart_Security();
