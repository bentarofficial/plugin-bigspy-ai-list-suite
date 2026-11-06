<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bigspy_popular_table() {
    global $wpdb;
    return $wpdb->prefix . 'bigspy_listing_clicks_daily';
}
function bigspy_popular_window_days() { return max( 1, min( 365, absint( bsl_get( 'generator.popular_window_days', 30 ) ) ) ); }
function bigspy_popular_retention_days() { return max( bigspy_popular_window_days(), min( 3650, absint( bsl_get( 'generator.popular_retention', 90 ) ) ) ); }

function bigspy_popular_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = bigspy_popular_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta( "CREATE TABLE {$table} (
        listing_id bigint(20) unsigned NOT NULL,
        click_date date NOT NULL,
        click_count bigint(20) unsigned NOT NULL DEFAULT 0,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (listing_id,click_date),
        KEY click_date (click_date)
    ) {$charset};" );
    if ( ! wp_next_scheduled( 'bigspy_popular_daily_maintenance' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'bigspy_popular_daily_maintenance' );
    update_option( 'bigspy_popular_schema_version', '1.0.0', false );
    bigspy_popular_rebuild_scores();
}
function bigspy_popular_maybe_install() {
    if ( get_option( 'bigspy_popular_schema_version', '' ) !== '1.0.0' ) bigspy_popular_install();
}
add_action( 'plugins_loaded', 'bigspy_popular_maybe_install', 20 );

function bigspy_popular_is_bot( $agent ) {
    return $agent === '' || (bool) preg_match( '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|monitor|headless|lighthouse|pagespeed/i', $agent );
}

function bigspy_popular_rebuild_scores() {
    global $wpdb;
    $table = bigspy_popular_table();
    $from = wp_date( 'Y-m-d', current_time('timestamp') - ( bigspy_popular_window_days() - 1 ) * DAY_IN_SECONDS );
    $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='0' WHERE meta_key='_bigspy_popular_clicks'" );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT listing_id, SUM(click_count) total FROM {$table} WHERE click_date >= %s GROUP BY listing_id", $from ) );
    foreach ( is_array($rows) ? $rows : array() as $row ) update_post_meta( absint($row->listing_id), '_bigspy_popular_clicks', absint($row->total) );
    update_option( 'bigspy_popular_scores_updated_at', current_time( 'mysql' ), false );
}

function bigspy_popular_daily_maintenance() {
    global $wpdb;
    $cutoff = wp_date( 'Y-m-d', current_time('timestamp') - ( bigspy_popular_retention_days() - 1 ) * DAY_IN_SECONDS );
    $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . bigspy_popular_table() . ' WHERE click_date < %s', $cutoff ) );
    bigspy_popular_rebuild_scores();
}
add_action( 'bigspy_popular_daily_maintenance', 'bigspy_popular_daily_maintenance' );

function bigspy_popular_query_args( $args ) {
    $args['bigspy_popular_clicks'] = 1;
    unset( $args['meta_key'] );
    $args['orderby'] = 'date';
    $args['order'] = 'DESC';
    return $args;
}
function bigspy_popular_order_clauses( $clauses, $query ) {
    if ( ! $query->get( 'bigspy_popular_clicks' ) ) return $clauses;
    global $wpdb;
    $alias = 'bigspy_popular_score';
    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} {$alias} ON ({$wpdb->posts}.ID={$alias}.post_id AND {$alias}.meta_key='_bigspy_popular_clicks')";
    $direction = strtoupper( (string) $query->get( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';
    $clauses['orderby'] = "COALESCE(CAST({$alias}.meta_value AS UNSIGNED),0) {$direction}, {$wpdb->posts}.post_date DESC";
    return $clauses;
}
add_filter( 'posts_clauses', 'bigspy_popular_order_clauses', 20, 2 );

function bigspy_popular_track_click() {
    if ( ! check_ajax_referer( 'bigspy_popular_click', 'nonce', false ) ) wp_send_json_error( array('message'=>'Invalid request'), 403 );
    if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) wp_send_json_success( array('counted'=>false,'reason'=>'administrator') );
    $agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
    if ( bigspy_popular_is_bot( $agent ) ) wp_send_json_success( array('counted'=>false,'reason'=>'bot') );
    $listing_id = absint( $_POST['listing_id'] ?? 0 );
    if ( ! $listing_id || get_post_type($listing_id) !== 'product' || get_post_status($listing_id) !== 'publish' ) wp_send_json_error( array('message'=>'Invalid listing'), 400 );

    $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    // Cookieless: repeat clicks are recognised by a salted hash of network and browser that
    // changes every day, so nothing is stored on the visitor's device and no consent is needed.
    $visitor = md5( wp_salt('nonce') . '|' . $ip . '|' . $agent . '|' . current_time( 'Y-m-d' ) );
    if ( isset( $_COOKIE['bigspy_popular_visitor'] ) ) {
        // Remove the identifier cookie set by versions before 2.9.5.
        setcookie( 'bigspy_popular_visitor', '', array('expires'=>time()-YEAR_IN_SECONDS,'path'=>COOKIEPATH ?: '/','domain'=>COOKIE_DOMAIN,'secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax') );
    }
    $dedupe = 'bigspy_click_' . md5( wp_salt('nonce') . '|visitor|' . $visitor . '|' . $listing_id );
    $rapid = 'bigspy_rapid_' . md5( wp_salt('nonce') . '|network|' . ($ip ?: $agent) . '|' . $listing_id );
    if ( get_transient($dedupe) || get_transient($rapid) ) wp_send_json_success( array('counted'=>false,'reason'=>'duplicate') );
    set_transient( $dedupe, 1, DAY_IN_SECONDS );
    set_transient( $rapid, 1, 5 * MINUTE_IN_SECONDS );

    global $wpdb;
    $table = bigspy_popular_table();
    $today = current_time( 'Y-m-d' );
    $now = current_time( 'mysql' );
    $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (listing_id,click_date,click_count,updated_at) VALUES (%d,%s,1,%s) ON DUPLICATE KEY UPDATE click_count=click_count+1,updated_at=VALUES(updated_at)", $listing_id, $today, $now ) );
    if ( false === $result ) { delete_transient($dedupe); delete_transient($rapid); wp_send_json_error( array('message'=>'Unable to save click'), 500 ); }
    $from = wp_date( 'Y-m-d', current_time('timestamp') - ( bigspy_popular_window_days() - 1 ) * DAY_IN_SECONDS );
    $score = absint( $wpdb->get_var( $wpdb->prepare( "SELECT SUM(click_count) FROM {$table} WHERE listing_id=%d AND click_date >= %s", $listing_id, $from ) ) );
    update_post_meta( $listing_id, '_bigspy_popular_clicks', $score );
    wp_send_json_success( array('counted'=>true) );
}
add_action( 'wp_ajax_bigspy_popular_click', 'bigspy_popular_track_click' );
add_action( 'wp_ajax_nopriv_bigspy_popular_click', 'bigspy_popular_track_click' );

function bigspy_popular_admin_product_columns( $columns ) {
    $output = array();
    $inserted = false;
    foreach ( $columns as $key => $label ) {
        $output[$key] = $label;
        if ( 'name' === $key ) {
            $output['bigspy_website_opens'] = sprintf( 'Website Opens (%dd)', bigspy_popular_window_days() );
            $inserted = true;
        }
    }
    if ( ! $inserted ) $output['bigspy_website_opens'] = sprintf( 'Website Opens (%dd)', bigspy_popular_window_days() );
    return $output;
}
add_filter( 'manage_edit-product_columns', 'bigspy_popular_admin_product_columns', 30 );

function bigspy_popular_admin_product_column( $column, $post_id ) {
    if ( 'bigspy_website_opens' !== $column ) return;
    $count = absint( get_post_meta( $post_id, '_bigspy_popular_clicks', true ) );
    $days = bigspy_popular_window_days();
    echo '<strong class="bigspy-admin-opens-count">' . esc_html( number_format_i18n( $count ) ) . '</strong>';
    echo '<span class="bigspy-admin-opens-period"> ' . esc_html( _n( 'click / %d day', 'clicks / %d days', $count, 'bigspy-ai-list-generator' ) ? sprintf( _n( 'click / %d day', 'clicks / %d days', $count, 'bigspy-ai-list-generator' ), $days ) : '' ) . '</span>';
}
add_action( 'manage_product_posts_custom_column', 'bigspy_popular_admin_product_column', 10, 2 );

function bigspy_popular_admin_sortable_columns( $columns ) {
    $columns['bigspy_website_opens'] = 'bigspy_popular_clicks';
    return $columns;
}
add_filter( 'manage_edit-product_sortable_columns', 'bigspy_popular_admin_sortable_columns' );

function bigspy_popular_admin_order( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || 'product' !== $query->get( 'post_type' ) ) return;
    if ( 'bigspy_popular_clicks' !== $query->get( 'orderby' ) ) return;
    $query->set( 'bigspy_popular_clicks', 1 );
}
add_action( 'pre_get_posts', 'bigspy_popular_admin_order' );

function bigspy_popular_admin_column_style() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || 'edit-product' !== $screen->id ) return;
    echo '<style>.column-bigspy_website_opens{width:145px}.bigspy-admin-opens-count{display:block;color:#003366;font-size:15px;line-height:1.2}.bigspy-admin-opens-period{display:block;margin-top:3px;color:#646970;font-size:11px;white-space:nowrap}</style>';
}
add_action( 'admin_head', 'bigspy_popular_admin_column_style' );
function bigspy_popular_frontend_assets() {
    if ( ! is_singular('product') ) return;
    wp_enqueue_script( 'bigspy-popular-analytics', plugin_dir_url(__FILE__) . 'assets/popular-analytics.js', array(), BIGSPY_AI_VERSION, true );
    wp_localize_script( 'bigspy-popular-analytics', 'BIGSPY_POPULAR', array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bigspy_popular_click'),'listingId'=>get_queried_object_id()) );
}
add_action( 'wp_enqueue_scripts', 'bigspy_popular_frontend_assets' );