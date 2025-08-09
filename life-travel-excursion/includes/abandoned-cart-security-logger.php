<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Abandoned_Cart_Security_Logger {
    private $log_dir;
    private $log_file;

    public function __construct() {
        $this->log_dir  = WP_CONTENT_DIR . '/acs-logs';
        if ( ! file_exists( $this->log_dir ) ) {
            wp_mkdir_p( $this->log_dir );
        }
        $this->log_file = $this->log_dir . '/security.log';
    }

    public function log_event( $type, $details = array() ) {
        $data = array(
            'time'    => current_time( 'mysql' ),
            'ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '',
            'type'    => sanitize_text_field( $type ),
            'details' => $this->sanitize_details( $details ),
        );
        $line = wp_json_encode( $data ) . "\n";
        error_log( $line, 3, $this->log_file );
    }

    public function get_logs() {
        if ( ! file_exists( $this->log_file ) ) {
            return array();
        }
        $lines = file( $this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        $logs  = array();
        foreach ( $lines as $l ) {
            $entry = json_decode( $l, true );
            if ( $entry ) {
                $logs[] = $entry;
            }
        }
        return $logs;
    }

    private function sanitize_details( $details ) {
        if ( is_array( $details ) ) {
            return array_map( 'sanitize_text_field', $details );
        }
        return sanitize_text_field( (string) $details );
    }
}
