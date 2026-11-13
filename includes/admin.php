<?php
/** Settings page: modules, AI connection, web research key. */
defined( 'ABSPATH' ) || exit;


add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'BIGSPY AI List Suite', 'bigspy-ai-list-suite' ),
		__( 'AI List Suite', 'bigspy-ai-list-suite' ),
		'manage_options',
		BSL_PAGE,
		'bsl_settings_page',
		'dashicons-list-view',
		58
	);
} );

add_filter( 'plugin_action_links_' . plugin_basename( BSL_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . BSL_PAGE ) ) . '">' . esc_html__( 'Settings', 'bigspy-ai-list-suite' ) . '</a>' );
	return $links;
} );

function bsl_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = bsl_settings();
	$source  = bsl_ai_source();
	$own_key = bsl_own_key();
	$saved   = ! empty( $_GET['bsl_saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'BIGSPY AI List Suite', 'bigspy-ai-list-suite' ); ?> <small style="font-size:13px;color:#646970">v<?php echo esc_html( BSL_VERSION ); ?></small></h1>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'bigspy-ai-list-suite' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Listings are WooCommerce products. Install and activate WooCommerce to use the Listing Generator.', 'bigspy-ai-list-suite' ); ?></p></div>
		<?php endif; ?>
		<?php do_action( 'bsl_settings_top' ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bsl_save_settings">
			<?php wp_nonce_field( 'bsl_save_settings' ); ?>

			<h2><?php esc_html_e( 'Modules', 'bigspy-ai-list-suite' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( bsl_modules() as $id => $module ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $module['label'] ); ?></th>
						<td>
							<label><input type="checkbox" name="bsl[modules][<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( ! empty( $s['modules'][ $id ] ) ); ?>> <?php esc_html_e( 'On', 'bigspy-ai-list-suite' ); ?></label>
							<p class="description"><?php echo esc_html( $module['description'] ); ?></p>
							<?php if ( bsl_legacy_loaded( $id ) ) : ?>
								<p class="description" style="color:#b32d2e"><?php esc_html_e( 'The old plugin for this module is still active, so this module is paused. Deactivate the old plugin.', 'bigspy-ai-list-suite' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'AI connection', 'bigspy-ai-list-suite' ); ?></h2>
			<?php if ( 'theme' === $source ) : ?>
				<p><?php esc_html_e( 'Using the AI connection of BIGSPY AI Theme WP (AI Designer). Change the key there; the fields below are only used if that key is removed.', 'bigspy-ai-list-suite' ); ?></p>
			<?php elseif ( function_exists( 'bst_aid_key' ) ) : ?>
				<p><?php esc_html_e( 'Tip: add your key once in the theme’s AI Designer settings and the suite will use it too.', 'bigspy-ai-list-suite' ); ?></p>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bsl-api-url"><?php esc_html_e( 'API endpoint', 'bigspy-ai-list-suite' ); ?></label></th>
					<td><input type="url" class="regular-text" id="bsl-api-url" name="bsl[api_url]" value="<?php echo esc_attr( $s['api_url'] ); ?>">
						<p class="description"><?php esc_html_e( 'OpenAI-compatible chat completions URL.', 'bigspy-ai-list-suite' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="bsl-api-key"><?php esc_html_e( 'API key', 'bigspy-ai-list-suite' ); ?></label></th>
					<td><input type="password" class="regular-text" id="bsl-api-key" name="bsl[api_key]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $own_key ? bsl_mask_key( $own_key ) : '' ); ?>">
						<?php if ( $own_key ) : ?>
							<label style="margin-left:8px"><input type="checkbox" name="bsl[api_key_clear]" value="1"> <?php esc_html_e( 'Remove', 'bigspy-ai-list-suite' ); ?></label>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Leave empty to keep the saved key.', 'bigspy-ai-list-suite' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="bsl-serper-key"><?php esc_html_e( 'Serper key (web research)', 'bigspy-ai-list-suite' ); ?></label></th>
					<td><input type="password" class="regular-text" id="bsl-serper-key" name="bsl[serper_key]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( bsl_mask_key( $s['serper_key'] ) ); ?>">
						<?php if ( $s['serper_key'] ) : ?>
							<label style="margin-left:8px"><input type="checkbox" name="bsl[serper_key_clear]" value="1"> <?php esc_html_e( 'Remove', 'bigspy-ai-list-suite' ); ?></label>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Optional. Used by Listing Generator and Post Writer to research the web.', 'bigspy-ai-list-suite' ); ?>
							<?php
							printf(
								/* translators: %s: link to the Serper API key page */
								esc_html__( 'Get a free key at %s (sign up, then copy the key from the API Key page).', 'bigspy-ai-list-suite' ),
								'<a href="https://serper.dev/" target="_blank" rel="noopener noreferrer">serper.dev</a>'
							);
							?></p></td>
				</tr>
			</table>
			<?php do_action( 'bsl_settings_sections', $s ); ?>
			<h2><?php esc_html_e( 'Data', 'bigspy-ai-list-suite' ); ?></h2>
			<p><label><input type="checkbox" name="bsl[delete_data]" value="1" <?php checked( ! empty( $s['delete_data'] ) ); ?>> <?php esc_html_e( 'Delete all suite data when the plugin is deleted (website insights, click statistics, settings). Listings and posts are never deleted.', 'bigspy-ai-list-suite' ); ?></label></p>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_bsl_save_settings', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to change these settings.', 'bigspy-ai-list-suite' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'bsl_save_settings' );
	$in  = isset( $_POST['bsl'] ) && is_array( $_POST['bsl'] ) ? wp_unslash( $_POST['bsl'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized below.
	$old = bsl_settings();
	$s   = $old;

	foreach ( array_keys( bsl_module_ids() ) as $id ) {
		$s['modules'][ $id ] = empty( $in['modules'][ $id ] ) ? 0 : 1;
	}
	$url          = esc_url_raw( trim( (string) ( $in['api_url'] ?? '' ) ) );
	$s['api_url'] = ( $url && 0 === strpos( $url, 'https://' ) ) ? $url : bsl_settings_defaults()['api_url'];
	foreach ( array( 'api_key', 'serper_key' ) as $key ) {
		$value = sanitize_text_field( (string) ( $in[ $key ] ?? '' ) );
		if ( ! empty( $in[ $key . '_clear' ] ) ) {
			$s[ $key ] = '';
		} elseif ( '' !== $value ) {
			$s[ $key ] = $value;
		}
	}
	$s['delete_data'] = empty( $in['delete_data'] ) ? 0 : 1;
	$s = apply_filters( 'bsl_sanitize_settings', $s, $in, $old );
	bsl_update_settings( $s );

	foreach ( array_keys( bsl_module_ids() ) as $id ) {
		if ( ! empty( $old['modules'][ $id ] ) && empty( $s['modules'][ $id ] ) ) {
			bsl_module_disabled( $id );
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=' . BSL_PAGE . '&bsl_saved=1' ) );
	exit;
} );
