<?php
/**
 * Frontend checkout field customizations
 */
defined('ABSPATH') || exit;

add_filter('woocommerce_checkout_fields', 'lte_customize_checkout_fields');
function lte_customize_checkout_fields($fields) {
    // Placeholders
    $fields['billing']['billing_first_name']['placeholder'] = __('Prénom', 'life-travel-excursion');
    $fields['billing']['billing_last_name']['placeholder']  = __('Nom', 'life-travel-excursion');
    $fields['billing']['billing_email']['placeholder']      = __('email@exemple.com', 'life-travel-excursion');
    $fields['billing']['billing_phone']['placeholder']      = __('+2376xxxxxxx', 'life-travel-excursion');
    // Tooltips
    $fields['billing']['billing_email']['label'] .= ' <span class="lte-tooltip" title="'.esc_attr(__('Veuillez saisir votre adresse email valide','life-travel-excursion')).'">?</span>';
    $fields['billing']['billing_phone']['label'] .= ' <span class="lte-tooltip" title="'.esc_attr(__('Numéro pour contact','life-travel-excursion')).'">?</span>';
    return $fields;
}
