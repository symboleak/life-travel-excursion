<?php
/**
 * Template for displaying a message when comments are not available
 *
 * @package Life_Travel
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="comments" class="comments-area comments-disabled">
    <div class="no-comments-message">
        <p class="no-access-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="#CCCCCC"/>
                <path d="M12 6.5c-1.11 0-2 .89-2 2v2c0 1.11.89 2 2 2s2-.89 2-2v-2c0-1.11-.89-2-2-2zm0 10c-.83 0-1.5-.67-1.5-1.5S11.17 13.5 12 13.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z" fill="#CCCCCC"/>
            </svg>
        </p>
        <h3><?php _e('Commentaires réservés', 'life-travel'); ?></h3>
        <p><?php _e('Seuls les participants à cette excursion peuvent voir et publier des commentaires.', 'life-travel'); ?></p>
        
        <?php if (!is_user_logged_in()): ?>
            <p><?php _e('Connectez-vous pour vérifier si vous avez accès à ce contenu.', 'life-travel'); ?></p>
            <p>
                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="button">
                    <?php _e('Se connecter', 'life-travel'); ?>
                </a>
            </p>
        <?php else: ?>
            <p><?php _e('Il semble que vous n\'avez pas participé à cette excursion.', 'life-travel'); ?></p>
            <p>
                <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" class="button">
                    <?php _e('Découvrir nos excursions', 'life-travel'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</div>
