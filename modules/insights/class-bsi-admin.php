<?php
defined( 'ABSPATH' ) || exit;

/**
 * Site Insights section of the AI List Suite settings page and the "Site Insights" box on each listing.
 */
class BSI_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_post_bsi_tool', array( __CLASS__, 'handle_tool' ) );
	}

	/* ---------------------------------------------------------- Settings page */

	public static function page() {
		// Printed inside the suite's settings form (bsl_settings_sections).
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = BSI_Settings::all();
		$counts = BSI_Store::counts();
		$name   = 'bsl[insights]';
		$notice = isset( $_GET['bsi_done'] ) ? sanitize_text_field( wp_unslash( $_GET['bsi_done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<h2>Site Insights</h2>
			<?php if ( '' !== $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<p>
				Shows free insights for the website behind each listing's <strong>Visit Website</strong> button.
				Place <code>[bigspy_insights]</code> in a listing, or turn on automatic placement below.
				Data is collected in the background, never while a visitor waits.
			</p>
			<p>
				<strong><?php echo esc_html( number_format( $counts['sites'] ) ); ?></strong> websites tracked,
				<strong><?php echo esc_html( number_format( $counts['fetched'] ) ); ?></strong> with data,
				<strong><?php echo esc_html( number_format( $counts['checks'] ) ); ?></strong> uptime checks stored.
			</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bsi-post-types">Listing post types</label></th>
						<td><input id="bsi-post-types" class="regular-text" name="<?php echo esc_attr( $name ); ?>[post_types]" value="<?php echo esc_attr( $s['post_types'] ); ?>">
						<p class="description">Comma separated. WooCommerce listings use <code>product</code>.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="bsi-meta-keys">"Visit Website" link fields</label></th>
						<td><input id="bsi-meta-keys" class="large-text" name="<?php echo esc_attr( $name ); ?>[url_meta_keys]" value="<?php echo esc_attr( $s['url_meta_keys'] ); ?>">
						<p class="description">Post meta keys checked in order. WooCommerce external products are detected automatically. Affiliate redirects are followed to find the real website.</p></td>
					</tr>
					<tr>
						<th scope="row">Automatic placement</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[auto_insert]" value="1" <?php checked( $s['auto_insert'], 1 ); ?>> Add the insights after the content of every single listing</label>
						<p class="description">Listings that already contain the shortcode are not duplicated. Themes can also call <code>do_action( 'bigspy_insights' )</code>.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="bsi-refresh">Refresh every</label></th>
						<td><input id="bsi-refresh" type="number" min="1" max="60" name="<?php echo esc_attr( $name ); ?>[refresh_days]" value="<?php echo esc_attr( $s['refresh_days'] ); ?>"> days</td>
					</tr>
					<tr>
						<th scope="row"><label for="bsi-monitor">Uptime check every</label></th>
						<td><input id="bsi-monitor" type="number" min="15" max="1440" step="15" name="<?php echo esc_attr( $name ); ?>[monitor_minutes]" value="<?php echo esc_attr( $s['monitor_minutes'] ); ?>"> minutes</td>
					</tr>
				</table>

				<h3>Optional free API keys</h3>
				<p><strong>Public on listings:</strong> the listed website itself, this site's uptime monitoring and, with a Google key, Chrome UX Report (CC BY 4.0, credited to Google).<br><strong>Admin only</strong> (Products list columns and the Site Insights box, never on the front end): Tranco rank and visit estimate, RDAP domain data and, with a key, Open PageRank. Cloudflare Radar (CC BY-NC 4.0) is not used.</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bsi-google">Google API key</label></th>
						<td><input id="bsi-google" class="regular-text" type="password" autocomplete="off" name="<?php echo esc_attr( $name ); ?>[google_api_key]" value="" placeholder="<?php echo esc_attr( bsl_mask_key( $s['google_api_key'] ) ); ?>">
						<p class="description">Adds <strong>Core Web Vitals</strong> from the Chrome UX Report. Create a free key in Google Cloud Console and enable the "Chrome UX Report API".</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="bsi-opr">Open PageRank key</label></th>
						<td><input id="bsi-opr" class="regular-text" type="password" autocomplete="off" name="<?php echo esc_attr( $name ); ?>[openpagerank_key]" value="" placeholder="<?php echo esc_attr( bsl_mask_key( $s['openpagerank_key'] ) ); ?>">
						<p class="description">Admin only: adds an <strong>Open PageRank score</strong> (0–10) column to the Products list.</p></td>
					</tr>
				</table>

			<h3>Tools</h3>
			<p>
				<?php self::tool_button( 'scan', 'Find websites for all listings', 'Walks every listing in the background and registers its website.' ); ?>
				<?php self::tool_button( 'refresh', 'Refresh the stalest websites now', 'Fetches up to 5 websites now (may take a minute).' ); ?>
				<?php self::tool_button( 'monitor', 'Run uptime checks now', 'Checks up to 20 websites that are due.' ); ?>
			</p>
		<?php
	}

	private static function tool_button( $tool, $label, $title ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=bsi_tool&tool=' . $tool ), 'bsi_tool_' . $tool );
		echo '<a class="button" style="margin-right:8px" href="' . esc_url( $url ) . '" title="' . esc_attr( $title ) . '">' . esc_html( $label ) . '</a>';
	}

	public static function handle_tool() {
		$tool = isset( $_GET['tool'] ) ? sanitize_key( wp_unslash( $_GET['tool'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'bsi_tool_' . $tool ) ) {
			wp_die( 'Not allowed.' );
		}
		$done = '';
		if ( 'scan' === $tool ) {
			BSI_Cron::start_queue_all();
			$done = 'Scanning listings in the background. Data appears within the next hour.';
		} elseif ( 'refresh' === $tool ) {
			delete_transient( 'bsi_refresh_lock' );
			$done = sprintf( 'Refreshed %d website(s).', BSI_Cron::refresh_due() );
		} elseif ( 'monitor' === $tool ) {
			$done = sprintf( 'Checked %d website(s).', BSI_Monitor::run() );
		}
		wp_safe_redirect( add_query_arg( 'bsi_done', rawurlencode( $done ), admin_url( 'admin.php?page=' . BSL_PAGE ) ) );
		exit;
	}

	/* ---------------------------------------------------------- Listing box */

	public static function meta_box() {
		foreach ( BSI_Cron::post_types() as $type ) {
			add_meta_box( 'bsi-insights', 'Site Insights', array( __CLASS__, 'render_meta_box' ), $type, 'normal', 'default' );
		}
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'bsi_meta_' . $post->ID, 'bsi_meta_nonce' );
		$visit    = BSI_Domain::visit_url( $post->ID );
		$override = (string) get_post_meta( $post->ID, '_bsi_domain', true );
		$domain   = BSI_Domain::for_post( $post->ID, false );
		$manual   = get_post_meta( $post->ID, '_bsi_manual', true );
		$manual   = wp_parse_args(
			is_array( $manual ) ? $manual : array(),
			array(
				'visits'    => '',
				'duration'  => '',
				'pages'     => '',
				'bounce'    => '',
				'countries' => '',
				'sources'   => '',
			)
		);
		$site = '' !== $domain ? BSI_Store::get_site( $domain ) : null;
		?>
		<p>
			<strong>Visit Website link:</strong>
			<?php echo '' !== $visit ? '<code>' . esc_html( $visit ) . '</code>' : '<em>not found — set the domain below</em>'; ?><br>
			<strong>Website analysed:</strong>
			<?php echo '' !== $domain ? '<code>' . esc_html( $domain ) . '</code>' : '<em>none</em>'; ?>
			<?php if ( $site && ! empty( $site['fetched_at'] ) ) : ?>
				— updated <?php echo esc_html( human_time_diff( strtotime( $site['fetched_at'] . ' UTC' ) ) ); ?> ago
				<?php if ( ! empty( $site['data']['tranco']['rank'] ) && current_user_can( 'manage_options' ) ) : ?>
					· global rank #<?php echo esc_html( number_format( (int) $site['data']['tranco']['rank'] ) ); ?> <em>(admin only)</em>
				<?php endif; ?>
			<?php elseif ( '' !== $domain ) : ?>
				— waiting for the first fetch
			<?php endif; ?>
		</p>
		<p>
			<label for="bsi-domain"><strong>Domain override</strong> (optional, e.g. <code>writesonic.com</code>)</label><br>
			<input id="bsi-domain" class="regular-text" name="bsi_domain" value="<?php echo esc_attr( $override ); ?>">
			<label style="margin-left:12px"><input type="checkbox" name="bsi_refresh_now" value="1"> Refresh data when I save</label>
		</p>
		<details>
			<summary><strong>Manual metrics</strong> (optional)</summary>
				<p class="description">Only enter figures you have the right to publish, such as numbers the company shared with you in writing or its own public statements. Do not copy data from Similarweb, Semrush, Ahrefs or other paid tools.</p>
			<table class="form-table" role="presentation">
				<tr><th><label for="bsi-m-visits">Monthly visits</label></th><td><input id="bsi-m-visits" type="number" min="0" name="bsi_manual[visits]" value="<?php echo esc_attr( $manual['visits'] ); ?>"></td></tr>
				<tr><th><label for="bsi-m-duration">Avg. visit duration</label></th><td><input id="bsi-m-duration" placeholder="00:03:13" name="bsi_manual[duration]" value="<?php echo esc_attr( $manual['duration'] ); ?>"></td></tr>
				<tr><th><label for="bsi-m-pages">Pages per visit</label></th><td><input id="bsi-m-pages" placeholder="2.75" name="bsi_manual[pages]" value="<?php echo esc_attr( $manual['pages'] ); ?>"></td></tr>
				<tr><th><label for="bsi-m-bounce">Bounce rate (%)</label></th><td><input id="bsi-m-bounce" placeholder="55.84" name="bsi_manual[bounce]" value="<?php echo esc_attr( $manual['bounce'] ); ?>"></td></tr>
				<tr><th><label for="bsi-m-countries">Top countries</label></th><td><textarea id="bsi-m-countries" rows="4" cols="30" placeholder="US 15.27&#10;IN 12.45" name="bsi_manual[countries]"><?php echo esc_textarea( $manual['countries'] ); ?></textarea><p class="description">One per line: 2-letter country code and share %.</p></td></tr>
				<tr><th><label for="bsi-m-sources">Traffic sources</label></th><td><textarea id="bsi-m-sources" rows="4" cols="30" placeholder="Direct 65.47&#10;Search Organic 19.60" name="bsi_manual[sources]"><?php echo esc_textarea( $manual['sources'] ); ?></textarea><p class="description">One per line: source name and share %.</p></td></tr>
			</table>
		</details>
		<?php
	}

	public static function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['bsi_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bsi_meta_nonce'] ) ), 'bsi_meta_' . $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$override = BSI_Domain::normalize( isset( $_POST['bsi_domain'] ) ? sanitize_text_field( wp_unslash( $_POST['bsi_domain'] ) ) : '' );
		if ( '' === $override ) {
			delete_post_meta( $post_id, '_bsi_domain' );
		} else {
			update_post_meta( $post_id, '_bsi_domain', $override );
		}

		$raw    = isset( $_POST['bsi_manual'] ) && is_array( $_POST['bsi_manual'] ) ? wp_unslash( $_POST['bsi_manual'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field below.
		$manual = array(
			'visits'    => isset( $raw['visits'] ) && '' !== $raw['visits'] ? absint( $raw['visits'] ) : '',
			'duration'  => isset( $raw['duration'] ) ? sanitize_text_field( $raw['duration'] ) : '',
			'pages'     => isset( $raw['pages'] ) ? sanitize_text_field( $raw['pages'] ) : '',
			'bounce'    => isset( $raw['bounce'] ) ? sanitize_text_field( $raw['bounce'] ) : '',
			'countries' => isset( $raw['countries'] ) ? sanitize_textarea_field( $raw['countries'] ) : '',
			'sources'   => isset( $raw['sources'] ) ? sanitize_textarea_field( $raw['sources'] ) : '',
		);
		if ( array_filter( $manual ) ) {
			update_post_meta( $post_id, '_bsi_manual', $manual );
		} else {
			delete_post_meta( $post_id, '_bsi_manual' );
		}

		if ( ! empty( $_POST['bsi_refresh_now'] ) ) {
			$domain = BSI_Domain::for_post( $post_id, true );
			if ( '' !== $domain ) {
				BSI_Collector::refresh( $domain );
				BSI_Monitor::check( $domain );
			}
		}
	}
}
