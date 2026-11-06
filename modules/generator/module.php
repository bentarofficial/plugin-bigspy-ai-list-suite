<?php
/** Listing Generator module: loads the generator and adds its settings and Create Listing page to the suite. */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/generator.php';
bigspy_popular_maybe_install();

/** True on the suite's Create Listing screen. */
function bsl_is_create_page( $hook ) {
	return 'admin_page_bsl-create-listing' === $hook || ( is_string( $hook ) && '_page_bsl-create-listing' === substr( $hook, -24 ) );
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		BSL_PAGE,
		__( 'Create listing', 'bigspy-ai-list-suite' ),
		__( 'Create listing', 'bigspy-ai-list-suite' ),
		current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options',
		'bsl-create-listing',
		function () {
			echo '<div class="wrap"><h1>' . esc_html__( 'Create listing', 'bigspy-ai-list-suite' ) . '</h1>';
			if ( ! class_exists( 'WooCommerce' ) ) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Listings are WooCommerce products. Activate WooCommerce first.', 'bigspy-ai-list-suite' ) . '</p></div></div>';
				return;
			}
			bigspy_render_listing_box( (object) array( 'ID' => 0 ) );
			echo '</div>';
		}
	);
}, 20 );

add_action( 'bsl_settings_sections', function ( $s ) {
	$g = $s['generator'];
	?>
	<h2><?php esc_html_e( 'Listing Generator', 'bigspy-ai-list-suite' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="bsl-max-labels"><?php esc_html_e( 'Maximum dynamic labels', 'bigspy-ai-list-suite' ); ?></label></th>
			<td><select id="bsl-max-labels" name="bsl[generator][max_dynamic_labels]">
				<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( (int) $g['max_dynamic_labels'], $i ); ?>><?php echo esc_html( $i ); ?></option>
				<?php endfor; ?>
			</select>
			<p class="description"><?php esc_html_e( 'The AI picks the most relevant attributes for each listing type. Default: 6.', 'bigspy-ai-list-suite' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Web research', 'bigspy-ai-list-suite' ); ?></th>
			<td><label><input type="checkbox" name="bsl[generator][web_research_enabled]" value="1" <?php checked( ! empty( $g['web_research_enabled'] ) ); ?>> <?php esc_html_e( 'Use Google web and news results (needs a Serper key)', 'bigspy-ai-list-suite' ); ?></label>
			<p><label><?php esc_html_e( 'Results per search', 'bigspy-ai-list-suite' ); ?> <input type="number" min="3" max="10" name="bsl[generator][web_research_results]" value="<?php echo esc_attr( $g['web_research_results'] ); ?>" style="width:70px"></label></p></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Popular ranking', 'bigspy-ai-list-suite' ); ?></th>
			<td><label><?php esc_html_e( 'Period (days)', 'bigspy-ai-list-suite' ); ?> <input type="number" min="1" max="365" name="bsl[generator][popular_window_days]" value="<?php echo esc_attr( $g['popular_window_days'] ); ?>" style="width:80px"></label>
			<label style="margin-left:16px"><?php esc_html_e( 'Keep click data (days)', 'bigspy-ai-list-suite' ); ?> <input type="number" min="1" max="3650" name="bsl[generator][popular_retention]" value="<?php echo esc_attr( $g['popular_retention'] ); ?>" style="width:80px"></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="bsl-system-prompt"><?php esc_html_e( 'System prompt', 'bigspy-ai-list-suite' ); ?></label></th>
			<td><textarea id="bsl-system-prompt" name="bsl[generator][system_prompt]" rows="8" class="large-text" placeholder="<?php echo esc_attr( bigspy_default_prompt() ); ?>"><?php echo esc_textarea( $g['system_prompt'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Leave empty to use the built-in prompt, which uses your site name.', 'bigspy-ai-list-suite' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Test connection', 'bigspy-ai-list-suite' ); ?></th>
			<td><button type="button" class="button" id="bsl-test-api"><?php esc_html_e( 'Send a test request', 'bigspy-ai-list-suite' ); ?></button> <span id="bsl-test-result" aria-live="polite"></span></td>
		</tr>
	</table>
	<script>
	( function () {
		var btn = document.getElementById( 'bsl-test-api' ), out = document.getElementById( 'bsl-test-result' );
		if ( ! btn ) return;
		btn.addEventListener( 'click', function () {
			var fd = new FormData();
			fd.append( 'action', 'bigspy_test_api' );
			fd.append( 'nonce', <?php echo wp_json_encode( wp_create_nonce( 'bigspy_ai_nonce' ) ); ?> );
			btn.disabled = true;
			out.textContent = <?php echo wp_json_encode( __( 'Calling the API…', 'bigspy-ai-list-suite' ) ); ?>;
			fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( d ) {
				out.style.color = d.success ? '#008a20' : '#b32d2e';
				out.textContent = d.success ? ( d.data.sample || 'OK' ) : String( d.data || 'Error' );
			} ).catch( function ( e ) {
				out.style.color = '#b32d2e';
				out.textContent = e.message;
			} ).finally( function () { btn.disabled = false; } );
		} );
	} )();
	</script>
	<?php
} );

add_filter( 'bsl_sanitize_settings', function ( $s, $in, $old ) {
	$g = (array) ( $in['generator'] ?? array() );
	if ( ! $g ) {
		return $s;
	}
	$window                  = max( 1, min( 365, absint( $g['popular_window_days'] ?? 30 ) ) );
	$s['generator']          = array_merge( $s['generator'], array(
		'max_dynamic_labels'   => max( 1, min( 10, absint( $g['max_dynamic_labels'] ?? 6 ) ) ),
		'web_research_enabled' => empty( $g['web_research_enabled'] ) ? 0 : 1,
		'web_research_results' => max( 3, min( 10, absint( $g['web_research_results'] ?? 5 ) ) ),
		'popular_window_days'  => $window,
		'popular_retention'    => max( $window, min( 3650, absint( $g['popular_retention'] ?? 90 ) ) ),
		'system_prompt'        => sanitize_textarea_field( (string) ( $g['system_prompt'] ?? '' ) ),
	) );
	if ( $old['generator']['popular_window_days'] !== $window ) {
		add_action( 'shutdown', 'bigspy_popular_rebuild_scores' );
	}
	return $s;
}, 10, 3 );
