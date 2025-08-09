<?php
/**
 * Frontend calendar shortcode
 */
defined('ABSPATH') || exit;

function lte_calendar_shortcode($atts) {
    $atts = shortcode_atts(['month'=>date('n'), 'year'=>date('Y')], $atts);
    $month = intval($atts['month']);
    $year = intval($atts['year']);
    $first = new DateTime("$year-$month-01");
    $start_weekday = intval($first->format('w')); // 0 (dim) - 6
    $days_in_month = intval($first->format('t'));

    // Fetch events
    $args = [
        'post_type'=>'product',
        'meta_query'=>[[
            'key'=>'_lte_excursion_dates',
            'compare'=>'EXISTS'
        ]],
        'posts_per_page'=>-1,
    ];
    $query = new WP_Query($args);
    $events = [];
    while ($query->have_posts()) {
        $query->the_post();
        $dates = get_post_meta(get_the_ID(), '_lte_excursion_dates', true);
        foreach ($dates as $d) {
            list($y,$m,$d2) = explode('-', $d['date']);
            if ($m==$month && $y==$year) {
                $events[intval($d2)][] = [
                    'title'=>get_the_title(),
                    'image'=>wp_get_attachment_image_url($d['image'],'thumbnail'),
                    'desc'=>esc_html($d['desc']),
                    'link'=>get_permalink(),
                ];
            }
        }
    }
    wp_reset_postdata();

    ob_start();
    echo '<div class="lte-calendar"><table><thead><tr>';
    $days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    foreach($days as $d) echo '<th>'.$d.'</th>';
    echo '</tr></thead><tbody><tr>';
    // empty cells
    for($i=0;$i<$start_weekday;$i++) echo '<td></td>';
    for($day=1;$day<=$days_in_month;$day++) {
        $class='';
        if (date('j')==$day && date('n')==$month && date('Y')==$year) $class='today';
        elseif(isset($events[$day])) $class='has-event';
        echo "<td class='$class'>".$day;
        if(isset($events[$day])){
            foreach($events[$day] as $e){
                echo "<div class='event'><a href='{$e['link']}'><img src='{$e['image']}' alt='' /><span>{$e['title']}</span></a></div>";
            }
        }
        echo '</td>';
        if ((($i+$day)%7)==0) echo '</tr><tr>';
    }
    echo '</tr></tbody></table></div>';
    return ob_get_clean();
}
add_shortcode('lte_calendar', 'lte_calendar_shortcode');
