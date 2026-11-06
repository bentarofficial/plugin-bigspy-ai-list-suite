<?php
/** Post Writer module: loads the writer and adds a Write post page to the suite. */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/writer.php';

add_action( 'admin_menu', function () {
	add_submenu_page(
		BSL_PAGE,
		__( 'Write post', 'bigspy-ai-list-suite' ),
		__( 'Write post', 'bigspy-ai-list-suite' ),
		'edit_posts',
		'bsl-write-post',
		function () {
			echo '<div class="wrap"><h1>' . esc_html__( 'Write post', 'bigspy-ai-list-suite' ) . '</h1>';
			if ( ! bigspy_pw_ready() ) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Post Writer uses the Listing Generator module for the AI connection, models and web research. Turn it on in AI List Suite.', 'bigspy-ai-list-suite' ) . '</p></div></div>';
				return;
			}
			bigspy_pw_render_meta_box( (object) array( 'ID' => 0 ) );
			echo '</div>';
		}
	);
}, 21 );
