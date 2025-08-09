<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Abandoned_Cart_Security_Chart {
    private $analyzer;

    public function __construct() {
        $this->analyzer = new Abandoned_Cart_Security_Analyzer();
    }

    /**
     * Retourne les données formatées pour Chart.js
     * @return string JSON compréhensible par Chart.js
     */
    public function get_chart_data() {
        $results = $this->analyzer->analyze();
        $labels = array(
            __('Fraud Attempts', 'life-travel-excursion'),
            __('Suspicious Patterns', 'life-travel-excursion'),
            __('IP Issues', 'life-travel-excursion'),
        );
        $data = array(
            intval( $results['fraud_attempts']['count'] ),
            count( $results['suspicious_patterns'] ),
            count( $results['ip_issues'] ),
        );
        $chart_data = array(
            'labels'   => $labels,
            'datasets' => array(
                array(
                    'label'           => __('Security Events', 'life-travel-excursion'),
                    'backgroundColor' => array('#f39c12', '#e74c3c', '#3498db'),
                    'data'            => $data,
                ),
            ),
        );
        return wp_json_encode( $chart_data );
    }
}
