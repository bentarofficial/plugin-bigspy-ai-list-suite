<?php
/**
 * Embed badge module: a "Featured on <site>" badge generator on every listing page.
 * Ported from the old BIGSPY AI List theme, so badges keep working after a theme switch.
 * The copied code is plain HTML with inline styles that links back to the listing.
 */
defined( 'ABSPATH' ) || exit;

/** Badge HTML for one listing in the light, neutral or dark style. */
function bsl_badge_code( $post_id, $style = 'light' ) {
	$palettes = array(
		'light'   => array( '#ffffff', '#d8e2f0', '#003366', '#008080' ),
		'neutral' => array( '#f1f5f9', '#cbd5e1', '#334155', '#006f73' ),
		'dark'    => array( '#0f172a', '#334155', '#f8fafc', '#5eead4' ),
	);
	list( $bg, $border, $text, $accent ) = $palettes[ $style ] ?? $palettes['light'];
	$site   = get_bloginfo( 'name' );
	$kicker = trim( (string) bsl_get( 'badge.label', '' ) );
	$kicker = '' === $kicker ? __( 'Featured on', 'bigspy-ai-list-suite' ) : $kicker;
	$icon   = get_site_icon_url( 128 );
	$logo   = $icon
		? '<img src="' . esc_url( $icon ) . '" alt="' . esc_attr( $site ) . '" width="38" height="38" style="display:block;width:38px;height:38px;object-fit:contain;flex:0 0 38px">'
		: '<span aria-hidden="true" style="display:grid;width:38px;height:38px;place-items:center;border-radius:50%;background:' . esc_attr( $accent ) . ';color:#fff;font:700 18px/1 Arial,sans-serif">' . esc_html( strtoupper( mb_substr( $site, 0, 1 ) ) ) . '</span>';
	return '<a href="' . esc_url( get_permalink( $post_id ) ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $kicker . ' ' . $site ) . '" style="box-sizing:border-box;display:inline-flex;align-items:center;gap:10px;max-width:100%;padding:9px 12px;border:1px solid ' . esc_attr( $border ) . ';border-radius:9px;background:' . esc_attr( $bg ) . ';color:' . esc_attr( $text ) . ';font-family:Arial,sans-serif;text-decoration:none;box-shadow:0 3px 12px rgba(15,23,42,.08)">' . $logo . '<span style="display:flex;min-width:0;flex-direction:column;line-height:1.15"><span style="color:' . esc_attr( $accent ) . ';font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase">' . esc_html( $kicker ) . '</span><strong style="overflow:hidden;font-size:15px;white-space:nowrap;text-overflow:ellipsis">' . esc_html( $site ) . '</strong></span><span aria-hidden="true" style="margin-left:auto;color:' . esc_attr( $accent ) . ';font-size:20px;line-height:1">★</span></a>';
}

/** The generator section: style switch, preview, code and copy button. */
function bsl_badge_html( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( ! $post_id || 'product' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		return '';
	}
	$heading = trim( (string) bsl_get( 'badge.heading', '' ) );
	$heading = '' === $heading ? __( 'Display your featured badge', 'bigspy-ai-list-suite' ) : $heading;
	$styles  = array(
		'light'   => __( 'Light', 'bigspy-ai-list-suite' ),
		'neutral' => __( 'Neutral', 'bigspy-ai-list-suite' ),
		'dark'    => __( 'Dark', 'bigspy-ai-list-suite' ),
	);
	$buttons = '';
	$sources = '';
	foreach ( $styles as $style => $label ) {
		$on       = 'light' === $style;
		$buttons .= '<button type="button" class="' . ( $on ? 'is-active' : '' ) . '" data-bsl-badge-style="' . esc_attr( $style ) . '" aria-pressed="' . ( $on ? 'true' : 'false' ) . '">' . esc_html( $label ) . '</button>';
		$sources .= '<textarea data-bsl-badge-source="' . esc_attr( $style ) . '">' . esc_textarea( bsl_badge_code( $post_id, $style ) ) . '</textarea>';
	}
	$light = bsl_badge_code( $post_id, 'light' );
	return '<section class="bsl-badge" data-bsl-badge><div class="bsl-badge__intro"><h2>' . esc_html( $heading ) . '</h2><p>' . esc_html__( 'Copy this badge into your website footer. Visitors who click it will open this listing page.', 'bigspy-ai-list-suite' ) . '</p>'
		. '<div class="bsl-badge__styles" role="group" aria-label="' . esc_attr__( 'Badge style', 'bigspy-ai-list-suite' ) . '">' . $buttons . '</div>'
		. '<div class="bsl-badge__preview" data-bsl-badge-preview>' . $light . '</div></div>'
		. '<div class="bsl-badge__builder"><textarea class="bsl-badge__code" data-bsl-badge-output readonly aria-label="' . esc_attr__( 'Embed code', 'bigspy-ai-list-suite' ) . '">' . esc_textarea( $light ) . '</textarea>'
		. '<div class="bsl-badge__actions"><button type="button" class="button bsl-badge__copy" data-bsl-badge-copy data-done="' . esc_attr__( 'Copied!', 'bigspy-ai-list-suite' ) . '">' . esc_html__( 'Copy embed code', 'bigspy-ai-list-suite' ) . '</button>'
		. '<details><summary>' . esc_html__( 'How to install?', 'bigspy-ai-list-suite' ) . '</summary><p>' . esc_html__( 'Paste the copied HTML into a Custom HTML block or the footer code area on your website.', 'bigspy-ai-list-suite' ) . '</p></details></div>'
		. '<div hidden>' . $sources . '</div></div></section>';
}

/** The old BIGSPY AI List theme still shows its own badge. */
function bsl_badge_theme_has_own() {
	return function_exists( 'bigspy_render_listing_embed_badge' );
}

add_action(
	'woocommerce_after_single_product_summary',
	function () {
		if ( ! bsl_badge_theme_has_own() ) {
			echo bsl_badge_html( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while building.
		}
	},
	13
);

add_shortcode( 'bigspy_embed_badge', fn( $atts ) => bsl_badge_html( absint( shortcode_atts( array( 'id' => 0 ), $atts )['id'] ) ) );

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'is_product' ) || ! is_product() || bsl_badge_theme_has_own() ) {
			return;
		}
		wp_register_style( 'bsl-badge', false, array(), BSL_VERSION );
		wp_enqueue_style( 'bsl-badge' );
		wp_add_inline_style( 'bsl-badge', '.bsl-badge{clear:both;display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,440px);gap:clamp(20px,4vw,48px);margin:32px 0;padding:24px;border:1px solid rgba(0,0,0,.1);border-radius:14px}.bsl-badge h2{margin:0 0 8px}.bsl-badge p{margin:0;opacity:.8}.bsl-badge__styles{display:inline-grid;grid-template-columns:repeat(3,auto);margin-top:18px;padding:3px;border-radius:999px;background:#f1f5f9}.bsl-badge__styles button{padding:6px 14px;border:0;border-radius:999px;background:transparent;color:#475569;cursor:pointer}.bsl-badge__styles button.is-active{background:#008080;color:#fff}.bsl-badge__preview{display:flex;align-items:center;min-height:80px;margin-top:12px;padding:14px;border:1px dashed #dbe7f0;border-radius:12px;background:#f8fafc}.bsl-badge__code{width:100%;height:140px;padding:10px;border:1px solid #dbe7f0;border-radius:10px;font:12px/1.45 ui-monospace,Consolas,monospace;resize:vertical}.bsl-badge__actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:10px}.bsl-badge__actions summary{cursor:pointer}@media(max-width:760px){.bsl-badge{grid-template-columns:1fr}}' );
		wp_register_script( 'bsl-badge', false, array(), BSL_VERSION, true );
		wp_enqueue_script( 'bsl-badge' );
		wp_add_inline_script( 'bsl-badge', "document.querySelectorAll('[data-bsl-badge]').forEach(function(b){var out=b.querySelector('[data-bsl-badge-output]'),pre=b.querySelector('[data-bsl-badge-preview]'),btns=[].slice.call(b.querySelectorAll('[data-bsl-badge-style]'));btns.forEach(function(x){x.addEventListener('click',function(){var s=b.querySelector('[data-bsl-badge-source=\"'+x.dataset.bslBadgeStyle+'\"]');if(!s)return;btns.forEach(function(y){y.classList.toggle('is-active',y===x);y.setAttribute('aria-pressed',y===x?'true':'false')});out.value=s.value;pre.innerHTML=s.value})});var c=b.querySelector('[data-bsl-badge-copy]');c&&c.addEventListener('click',function(){var t=c.textContent,done=function(){c.textContent=c.dataset.done;setTimeout(function(){c.textContent=t},1500)};if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(out.value).then(done)}else{out.select();document.execCommand('copy');done()}})});" );
	}
);

// Settings: heading and top label of the badge.
add_action(
	'bsl_settings_sections',
	function ( $s ) {
		echo '<h2>' . esc_html__( 'Embed badge', 'bigspy-ai-list-suite' ) . '</h2><table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="bsl-badge-heading">' . esc_html__( 'Section heading', 'bigspy-ai-list-suite' ) . '</label></th><td><input type="text" class="regular-text" id="bsl-badge-heading" name="bsl[badge][heading]" value="' . esc_attr( $s['badge']['heading'] ?? '' ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="bsl-badge-label">' . esc_html__( 'Badge top label', 'bigspy-ai-list-suite' ) . '</label></th><td><input type="text" class="regular-text" id="bsl-badge-label" name="bsl[badge][label]" value="' . esc_attr( $s['badge']['label'] ?? '' ) . '"><p class="description">' . esc_html__( 'Shown above the site name, for example "Featured on". The badge uses the Site Icon as its logo.', 'bigspy-ai-list-suite' ) . '</p></td></tr></table>';
	}
);

add_filter(
	'bsl_sanitize_settings',
	function ( $s, $in ) {
		if ( isset( $in['badge'] ) && is_array( $in['badge'] ) ) {
			$s['badge'] = array(
				'heading' => sanitize_text_field( (string) ( $in['badge']['heading'] ?? '' ) ),
				'label'   => sanitize_text_field( (string) ( $in['badge']['label'] ?? '' ) ),
			);
		}
		return $s;
	},
	10,
	2
);

// Old theme settings become the defaults the first time.
add_action(
	'admin_init',
	function () {
		if ( get_option( 'bsl_badge_migrated' ) ) {
			return;
		}
		update_option( 'bsl_badge_migrated', 1, false );
		$old = get_option( 'theme_mods_bigspy-ai-list', array() );
		$s   = get_option( BSL_OPTION, array() );
		if ( ! is_array( $old ) || ! is_array( $s ) || isset( $s['badge'] ) ) {
			return;
		}
		$s['badge'] = array(
			'heading' => sanitize_text_field( (string) ( $old['bigspy_single_badge_heading'] ?? 'Display your featured badge' ) ),
			'label'   => sanitize_text_field( (string) ( $old['bigspy_single_badge_label'] ?? 'Featured on' ) ),
		);
		update_option( BSL_OPTION, $s );
	}
);
