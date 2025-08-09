<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/abandoned-cart-security-logger.php';

class Abandoned_Cart_Security_Analyzer {
    private $logger;
    private $settings;

    public function __construct() {
        $this->logger   = new Abandoned_Cart_Security_Logger();
        $this->settings = get_option( 'acs_security_settings', array( 'fraud_token_threshold' => 10 ) );
    }

    public function analyze() {
        $logs = $this->logger->get_logs();

        return array(
            'fraud_attempts'      => $this->detect_fraud_token_attempts( $logs ),
            'suspicious_patterns' => $this->identify_suspicious_patterns( $logs ),
            'ip_issues'           => $this->analyze_ip_behaviors( $logs ),
        );
    }

    private function detect_fraud_token_attempts( $logs ) {
        $count     = 0;
        foreach ( $logs as $entry ) {
            if ( isset( $entry['type'] ) && 'fraud_token_attempt' === $entry['type'] ) {
                $count++;
            }
        }
        $threshold = intval( $this->settings['fraud_token_threshold'] );
        return array(
            'count'     => $count,
            'threshold' => $threshold,
            'flagged'   => ( $count > $threshold ),
        );
    }

    private function identify_suspicious_patterns( $logs ) {
        $window   = 60; // seconds
        $cutoff   = time() - $window;
        $ip_types = array();

        foreach ( $logs as $entry ) {
            if ( empty( $entry['time'] ) || empty( $entry['ip'] ) || empty( $entry['type'] ) ) {
                continue;
            }
            $tstamp = strtotime( $entry['time'] );
            if ( $tstamp < $cutoff ) {
                continue;
            }
            $ip       = $entry['ip'];
            $type     = $entry['type'];
            if ( ! isset( $ip_types[ $ip ] ) ) {
                $ip_types[ $ip ] = array();
            }
            $ip_types[ $ip ][ $type ] = true;
        }

        $patterns = array();
        foreach ( $ip_types as $ip => $types ) {
            if ( count( $types ) > 3 ) {
                $patterns[] = array(
                    'ip'             => $ip,
                    'distinct_types' => count( $types ),
                );
            }
        }
        return $patterns;
    }

    private function analyze_ip_behaviors( $logs ) {
        $counts = array();
        foreach ( $logs as $entry ) {
            if ( empty( $entry['ip'] ) ) {
                continue;
            }
            $ip = $entry['ip'];
            if ( ! isset( $counts[ $ip ] ) ) {
                $counts[ $ip ] = 0;
            }
            $counts[ $ip ]++;
        }
        $issues = array();
        $limit  = 100;

        foreach ( $counts as $ip => $cnt ) {
            if ( $cnt > $limit ) {
                $issues[] = array(
                    'ip'    => $ip,
                    'count' => $cnt,
                );
            }
        }
        return $issues;
    }
}
