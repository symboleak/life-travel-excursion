<?php
/**
 * Customizer settings for Life Travel Excursion visual identity
 */
defined('ABSPATH') || exit;

function lte_customize_register($wp_customize) {
    $wp_customize->add_section('lte_design', [
        'title'    => __('Life Travel Design','life-travel-excursion'),
        'priority' => 30,
    ]);
    // Logo upload
    $wp_customize->add_setting('lte_logo', [
        'sanitize_callback' => 'absint',
    ]);
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'lte_logo', [
        'label'    => __('Logo','life-travel-excursion'),
        'section'  => 'lte_design',
        'mime_type'=> 'image',
    ]));
    // Site visual placeholders (Step 4)
    $wp_customize->add_setting('lte_site_logo', ['default'=>'','sanitize_callback'=>'esc_url_raw']);
    $wp_customize->add_control(new WP_Customize_Image_Control(
        $wp_customize, 'lte_site_logo', [
            'label'=>__('Logo du site','life-travel-excursion'),
            'section'=>'title_tagline',
            'settings'=>'lte_site_logo'
        ]
    ));
    $wp_customize->add_setting('lte_banner_image', ['default'=>'','sanitize_callback'=>'esc_url_raw']);
    $wp_customize->add_control(new WP_Customize_Image_Control(
        $wp_customize, 'lte_banner_image', [
            'label'=>__('Image de bannière','life-travel-excursion'),
            'section'=>'lte_design',
            'settings'=>'lte_banner_image'
        ]
    ));
    $wp_customize->add_setting('lte_blog_placeholder', ['default'=>'','sanitize_callback'=>'esc_url_raw']);
    $wp_customize->add_control(new WP_Customize_Image_Control(
        $wp_customize, 'lte_blog_placeholder', [
            'label'=>__('Image par défaut blog','life-travel-excursion'),
            'section'=>'lte_design',
            'settings'=>'lte_blog_placeholder'
        ]
    ));
    // Colors
    $colors = [
        'primary'   => '#2ecc71',
        'secondary' => '#3498db',
        'background'=> '#ffffff',
    ];
    foreach ($colors as $name => $default) {
        $wp_customize->add_setting("lte_{$name}_color", [
            'default'           => $default,
            'sanitize_callback' => 'sanitize_hex_color',
        ]);
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "lte_{$name}_color", [
            'label'   => ucfirst($name).' '.__('Color','life-travel-excursion'),
            'section' => 'lte_design',
        ]));
    }
    // Search bar settings
    $wp_customize->add_setting('lte_search_placeholder', [
        'default'           => __('Search excursions...','life-travel-excursion'),
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('lte_search_placeholder', [
        'label'   => __('Search Placeholder','life-travel-excursion'),
        'section' => 'lte_design',
        'type'    => 'text',
    ]);
    foreach (['bg' => 'Background', 'input' => 'Input Text', 'placeholder' => 'Placeholder'] as $key => $label) {
        $wp_customize->add_setting("lte_search_{$key}_color", [
            'default'           => $key=='bg' ? '#ffffff' : ($key=='input' ? '#333333' : '#888888'),
            'sanitize_callback' => 'sanitize_hex_color',
        ]);
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "lte_search_{$key}_color", [
            'label'   => __('Search ' . $label . ' Color','life-travel-excursion'),
            'section' => 'lte_design',
        ]));
    }
    // Banner image
    $wp_customize->add_setting('lte_banner_image', ['sanitize_callback'=>'absint']);
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize,'lte_banner_image',[
        'label'=>'Banner Image',
        'section'=>'lte_design',
        'mime_type'=>'image',
    ]));
    // Banner video URL
    $wp_customize->add_setting('lte_banner_video', ['default'=>'', 'sanitize_callback'=>'esc_url_raw']);
    $wp_customize->add_control('lte_banner_video', [
        'label'   => __('Banner Video URL','life-travel-excursion'),
        'section' => 'lte_design',
        'type'    => 'url',
    ]);
    // Footer text
    $wp_customize->add_setting('lte_footer_text', ['default'=>'', 'sanitize_callback'=>'wp_kses_post']);
    $wp_customize->add_control('lte_footer_text', [
        'label'   => __('Footer Text','life-travel-excursion'),
        'section' => 'lte_design',
        'type'    => 'textarea',
    ]);
    // Pixel Meta
    $wp_customize->add_setting('lte_meta_pixel_id', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('lte_meta_pixel_id', [
        'label'   => __('Meta Pixel ID','life-travel-excursion'),
        'section' => 'lte_design',
        'type'    => 'text',
    ]);
    // Authentication settings
    $wp_customize->add_setting('lte_enable_email_login', ['default'=>true,'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_enable_email_login', ['label'=>__('Activer login par email','life-travel-excursion'),'section'=>'lte_design','type'=>'checkbox']);
    $wp_customize->add_setting('lte_enable_sms_login', ['default'=>false,'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_enable_sms_login', ['label'=>__('Activer login par SMS','life-travel-excursion'),'section'=>'lte_design','type'=>'checkbox']);
    $wp_customize->add_setting('lte_enable_facebook_login', ['default'=>false,'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_enable_facebook_login', ['label'=>__('Activer login Facebook','life-travel-excursion'),'section'=>'lte_design','type'=>'checkbox']);
    // Facebook App credentials
    $wp_customize->add_setting('lte_fb_app_id', ['default'=>'','sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_fb_app_id', ['label'=>__('Facebook App ID','life-travel-excursion'),'section'=>'lte_design','type'=>'text']);
    $wp_customize->add_setting('lte_fb_app_secret', ['default'=>'','sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_fb_app_secret', ['label'=>__('Facebook App Secret','life-travel-excursion'),'section'=>'lte_design','type'=>'password']);
    // Login text
    $wp_customize->add_setting('lte_login_title', ['default'=>__('Se connecter','life-travel-excursion'),'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_login_title', ['label'=>__('Titre login','life-travel-excursion'),'section'=>'lte_design','type'=>'text']);
    $wp_customize->add_setting('lte_login_email_button', ['default'=>__('Envoyer code par email','life-travel-excursion'),'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_login_email_button', ['label'=>__('Texte bouton email','life-travel-excursion'),'section'=>'lte_design','type'=>'text']);
    $wp_customize->add_setting('lte_login_sms_button', ['default'=>__('Envoyer code SMS','life-travel-excursion'),'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_login_sms_button', ['label'=>__('Texte bouton SMS','life-travel-excursion'),'section'=>'lte_design','type'=>'text']);
    $wp_customize->add_setting('lte_login_fb_button', ['default'=>__('Se connecter avec Facebook','life-travel-excursion'),'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_login_fb_button', ['label'=>__('Texte bouton Facebook','life-travel-excursion'),'section'=>'lte_design','type'=>'text']);
    $wp_customize->add_setting('lte_login_google_button', ['default'=>__('Se connecter avec Google','life-travel-excursion'),'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_login_google_button', ['label'=>__('Texte bouton Google','life-travel-excursion'),'section'=>'lte_design','type'=>'text']);
    // Login/authentication settings
    $wp_customize->add_setting('lte_login_title', [
        'default'           => __('Se connecter','life-travel-excursion'),
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('lte_login_title', [
        'label'   => __('Login Title','life-travel-excursion'),
        'section' => 'lte_design',
        'type'    => 'text',
    ]);
    foreach (['email'=>'Email','sms'=>'SMS','facebook'=>'Facebook'] as $key=>$label) {
        $wp_customize->add_setting("lte_enable_{$key}_login", [
            'default'           => $key==='email',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        $wp_customize->add_control("lte_enable_{$key}_login", [
            'label'   => sprintf(__('Enable %s login','life-travel-excursion'),$label),
            'section' => 'lte_design',
            'type'    => 'checkbox',
        ]);
    }
    // Google login settings
    $wp_customize->add_setting('lte_enable_google_login', ['default'=>false, 'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_enable_google_login', ['label'=>__('Activer partage Google','life-travel-excursion'), 'section'=>'lte_design', 'type'=>'checkbox']);
    $wp_customize->add_setting('lte_google_client_id', ['default'=>'', 'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_google_client_id', ['label'=>__('Google Client ID','life-travel-excursion'), 'section'=>'lte_design', 'type'=>'text']);
    $wp_customize->add_setting('lte_google_client_secret', ['default'=>'', 'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_google_client_secret', ['label'=>__('Google Client Secret','life-travel-excursion'), 'section'=>'lte_design', 'type'=>'password']);
    // Loyalty settings
    $wp_customize->add_setting('lte_loyalty_enabled', ['default'=>false,'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_loyalty_enabled', ['label'=>__('Activer fidélité','life-travel-excursion'),'section'=>'lte_design','type'=>'checkbox']);
    $wp_customize->add_setting('lte_loyalty_conversion',['default'=>1,'sanitize_callback'=>'absint']);
    $wp_customize->add_control('lte_loyalty_conversion',['label'=>__('Conversion (€ → points)','life-travel-excursion'),'section'=>'lte_design','type'=>'number']);
    $wp_customize->add_setting('lte_loyalty_max_discount_pct',['default'=>20,'sanitize_callback'=>'absint']);
    $wp_customize->add_control('lte_loyalty_max_discount_pct',['label'=>__('Réduction max (%)','life-travel-excursion'),'section'=>'lte_design','type'=>'number']);
    // Share bonus points setting (Step 4)
    $wp_customize->add_setting('lte_loyalty_share_bonus_points', ['default'=>10,'sanitize_callback'=>'absint']);
    $wp_customize->add_control('lte_loyalty_share_bonus_points', ['label'=>__('Points bonus partage','life-travel-excursion'),'section'=>'lte_design','type'=>'number']);
    $wp_customize->add_setting('lte_enable_instagram_share', ['default'=>false,'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_enable_instagram_share', ['label'=>__('Activer partage Instagram','life-travel-excursion'),'section'=>'lte_design','type'=>'checkbox']);
    // Push notifications setting
    $wp_customize->add_setting('lte_push_enabled',['default'=>false,'sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_push_enabled',['label'=>__('Activer notifications push','life-travel-excursion'),'section'=>'lte_design','type'=>'checkbox']);
    $wp_customize->add_setting('lte_push_vapid_key',['default'=>'','sanitize_callback'=>'sanitize_text_field']);
    $wp_customize->add_control('lte_push_vapid_key',['label'=>__('VAPID Public Key','life-travel-excursion'),'section'=>'lte_design','type'=>'text']);
}
add_action('customize_register','lte_customize_register');

function lte_customize_css() {
    ?>
    <style type="text/css">
        :root {
            --lte-primary-color: <?php echo esc_html(get_theme_mod('lte_primary_color','#2ecc71')); ?>;
            --lte-secondary-color: <?php echo esc_html(get_theme_mod('lte_secondary_color','#3498db')); ?>;
            --lte-background-color: <?php echo esc_html(get_theme_mod('lte_background_color','#ffffff')); ?>;
        }
        .button-primary, .lte-button {
            background-color: var(--lte-primary-color) !important;
        }
        .button-secondary, .lte-button-secondary {
            background-color: var(--lte-secondary-color) !important;
        }
        body {
            background-color: var(--lte-background-color);
        }
        /* WooCommerce overrides */
        .woocommerce button.button,
        .woocommerce a.button,
        .woocommerce #respond input#submit,
        .woocommerce input.button {
            background-color: var(--lte-primary-color) !important;
            border-color: var(--lte-primary-color) !important;
            color: #fff !important;
        }
        .woocommerce button.button.alt,
        .woocommerce a.button.alt {
            background-color: var(--lte-secondary-color) !important;
            border-color: var(--lte-secondary-color) !important;
            color: #fff !important;
        }
        .site-header, .site-footer, .woocommerce-breadcrumb {
            background-color: var(--lte-background-color) !important;
        }
        .lte-logo img, .lte-banner, .lte-banner-video {
            max-width: 100%; height: auto;
        }
        /* Search bar styling */
        .lte-search {
            width: 100%;
            max-width: 400px;
            margin: 0 auto 20px;
            background-color: <?php echo esc_html(get_theme_mod('lte_search_bg_color','#ffffff')); ?>;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
            display: flex;
        }
        .lte-search input[type="search"] {
            flex: 1;
            padding: 8px 12px;
            color: <?php echo esc_html(get_theme_mod('lte_search_input_color','#333333')); ?>;
            border: none;
            font-size: 1em;
        }
        .lte-search input[type="search"]::placeholder {
            color: <?php echo esc_html(get_theme_mod('lte_search_placeholder_color','#888888')); ?>;
        }
        .lte-search button {
            background: none;
            border: none;
            padding: 0 12px;
            cursor: pointer;
        }
        /* Checkout styling */
        .woocommerce-checkout .woocommerce-form__label, .woocommerce-checkout .woocommerce-input-wrapper {
            font-family: var(--font-body);
            color: var(--lte-primary-color);
        }
        .woocommerce-checkout input.input-text, .woocommerce-checkout select, .woocommerce-checkout textarea {
            width: 100%; padding: 10px; margin-bottom: 15px;
            border: 1px solid var(--lte-secondary-color);
            border-radius: var(--border-radius);
        }
        .woocommerce-checkout button.button, .woocommerce-checkout .button.alt {
            background-color: var(--lte-primary-color) !important;
            border-color: var(--lte-primary-color) !important;
            color: #fff !important;
            padding: 12px 20px; border-radius: var(--border-radius);
        }
        /* My Account reservations */
        .lte-reservations-list {
            list-style: none; padding: 0; margin: 20px 0;
        }
        .lte-reservations-list li {
            background: var(--lte-background-color); border: 1px solid var(--lte-secondary-color);
            padding: 10px; margin-bottom: 10px; border-radius: var(--border-radius);
        }
        .lte-reservations-list li a {
            color: var(--lte-primary-color); font-weight: 600; text-decoration: none;
        }
        .lte-reservations-list li a:hover {
            text-decoration: underline;
        }
        /* Auth form styling */
        .lte-auth { background: var(--lte-background-color); padding: 20px; border:1px solid var(--lte-secondary-color); border-radius: var(--border-radius); max-width:400px; margin:20px auto; }
        .lte-auth input { width:100%; padding:10px; margin-bottom:15px; border:1px solid var(--lte-secondary-color); border-radius:var(--border-radius); }
        .lte-auth button, .lte-auth .lte-login-send, .lte-auth #lte-login-verify { background: var(--lte-primary-color); color:#fff; padding:10px 20px; border:none; border-radius:var(--border-radius); cursor:pointer; display:block; width:100%; margin-bottom:10px; }
        .lte-auth .lte-fb-login { display:block; margin-top:15px; background:#3b5998; color:#fff; text-align:center; padding:10px; text-decoration:none; border-radius:var(--border-radius); }
        /* Tabs My Account */
        .lte-account-tabs { margin-bottom: 15px; }
        .lte-account-tabs button { background: var(--lte-secondary-color); color: #fff; border: none; padding: 8px 16px; margin-right: 5px; cursor: pointer; border-radius: var(--border-radius); }
        .lte-account-tabs button.active { background: var(--lte-primary-color); }
        .lte-tab-content { margin-top: 10px; }
        /* Tooltips */
        .lte-tooltip { display: inline-block; border-bottom: 1px dotted var(--lte-secondary-color); cursor: help; position: relative; }
        .lte-tooltip:hover:after { content: attr(title); position: absolute; bottom: 100%; left: 0; background: rgba(0,0,0,0.8); color: #fff; padding: 5px 8px; border-radius: 4px; white-space: nowrap; z-index: 10; }
    </style>
    <?php
}
add_action('wp_head','lte_customize_css');

add_action('after_setup_theme', function() {
    add_theme_support('custom-logo');
});

// Shortcode pour afficher le logo configuré
add_shortcode('lte_logo', function() {
    $id = get_theme_mod('lte_logo');
    return $id ? wp_get_attachment_image($id, 'full', false, ['class'=>'lte-logo']) : '';
});

// Shortcode pour afficher la bannière (vidéo ou image)
add_shortcode('lte_banner', function() {
    $video = get_theme_mod('lte_banner_video');
    if ($video) {
        return '<video autoplay muted loop class="lte-banner-video"><source src="'.esc_url($video).'" /></video>';
    }
    $id = get_theme_mod('lte_banner_image');
    return $id ? wp_get_attachment_image($id, 'full', ['class'=>'lte-banner']) : '';
});

// Injection du texte de footer
add_action('wp_footer', function() {
    $text = get_theme_mod('lte_footer_text');
    if ($text) echo '<div class="lte-footer-text">'.wp_kses_post($text).'</div>';
});

add_action('wp_head','lte_meta_pixel_inject');
function lte_meta_pixel_inject() {
    $id = get_theme_mod('lte_meta_pixel_id');
    if (!$id) return;
    echo "<!-- Meta Pixel -->\n";
    echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod? n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js'); fbq('init','". esc_js($id)."'); fbq('track','PageView');</script>\n";
    echo "<noscript><img height=1 width=1 style='display:none' src='https://www.facebook.com/tr?id=". esc_attr($id)."&ev=PageView&noscript=1'/></noscript>\n";
}
