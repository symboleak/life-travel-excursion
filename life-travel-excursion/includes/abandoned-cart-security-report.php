<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/abandoned-cart-security-analyzer.php';

class Abandoned_Cart_Security_Report {
    private $analyzer;

    public function __construct() {
        $this->analyzer = new Abandoned_Cart_Security_Analyzer();
    }

    public function generate_report() {
        $results = $this->analyzer->analyze();
        ob_start();
        ?>
        <div class="acs-security-report">
            <h3>Fraud Token Detection</h3>
            <p>Attempts: <?php echo esc_html( $results['fraud_attempts']['count'] ); ?></p>
            <p>Threshold: <?php echo esc_html( $results['fraud_attempts']['threshold'] ); ?></p>
            <?php if ( $results['fraud_attempts']['flagged'] ) : ?>
                <p style="color:red;">Alert: Fraud token attempts exceeded threshold!</p>
            <?php endif; ?>

            <h3>Suspicious Patterns (last 60s)</h3>
            <?php if ( empty( $results['suspicious_patterns'] ) ) : ?>
                <p>None detected.</p>
            <?php else : ?>
                <ul>
                    <?php foreach ( $results['suspicious_patterns'] as $pattern ) : ?>
                        <li>IP <?php echo esc_html( $pattern['ip'] ); ?>: <?php echo esc_html( $pattern['distinct_types'] ); ?> distinct suspicious actions</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3>IP Behavior Issues</h3>
            <?php if ( empty( $results['ip_issues'] ) ) : ?>
                <p>None detected.</p>
            <?php else : ?>
                <ul>
                    <?php foreach ( $results['ip_issues'] as $issue ) : ?>
                        <li>IP <?php echo esc_html( $issue['ip'] ); ?>: <?php echo esc_html( $issue['count'] ); ?> events</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
