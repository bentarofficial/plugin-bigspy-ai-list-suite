<?php
/**
 * Content brief: what a listing or post must give the reader.
 *
 * A product has one listing, so a listing is always a full profile that delivers every
 * customer value. Content routes (one reader question per article) belong to blog posts:
 * the Post Writer lets the editor pick the route and the values for each post.
 */
defined( 'ABSPATH' ) || exit;

/** Content routes a single listing can take. Each answers one reader question. */
function bigspy_content_routes() {
	$routes = array(
		'profile'  => array(
			'label'    => 'Listing profile',
			'question' => 'What is this tool and is it right for me?',
			'prompt'   => 'Write a balanced discovery profile. Open with what the product does and for whom, in two sentences a newcomer understands. Give every other section equal weight.',
		),
		'review'   => array(
			'label'    => 'Review',
			'question' => 'Is this product a fit and worth paying for?',
			'prompt'   => 'Write an evaluation. Open with a short verdict: who should use it, who should not, and why. Judge each key capability by what it lets the user achieve, and state plainly where the product falls short. End with a clear recommendation per user type.',
		),
		'pricing'  => array(
			'label'    => 'Pricing',
			'question' => 'How much will I pay and which plan is enough?',
			'prompt'   => 'Make pricing the core of the article. Place a plan table (plan, price, main limits, who it suits) right after the introduction, then explain what each plan unlocks, which limits users hit first, when upgrading becomes necessary, renewal or overage costs, and the refund policy. Use only prices found in the sources.',
		),
		'trial'    => array(
			'label'    => 'Trial & Demo',
			'question' => 'What should I check before I pay?',
			'prompt'   => 'Write a pre-purchase test guide. Explain what the free plan, trial or demo includes, whether a card is required, and how long it lasts. Give a checklist of what to test during the trial and the signs that the product is or is not a fit.',
		),
		'tutorial' => array(
			'label'    => 'Tutorial',
			'question' => 'How do I get this job done with the product?',
			'prompt'   => 'Write a practical walkthrough of the main job this product is used for. Lead with the outcome, then give numbered steps from sign-up to the first finished result, with common mistakes and how to avoid them. Keep the product overview short.',
		),
		'offers'   => array(
			'label'    => 'Offers & Deals',
			'question' => 'Is there a real offer and does it apply to me?',
			'prompt'   => 'Lead with the current offer: what it is, its exact conditions, who qualifies, and when it ends. Include only offers confirmed by the sources; if none is confirmed, say so and explain the regular lowest-cost way to start. Then cover what the buyer gets and what to check before buying.',
		),
		'testing'  => array(
			'label'    => 'Case Study & Testing',
			'question' => 'What results does it produce and where are the limits?',
			'prompt'   => 'Organize the article around results: documented use cases, published case studies, and measurable outcomes from the sources, each with its conditions and limits. Never present a result as tested by this site unless the editor supplied that test in the short description.',
		),
	);
	return apply_filters( 'bigspy_content_routes', $routes );
}

/** The five customer values a listing can deliver. */
function bigspy_customer_values() {
	$values = array(
		'discover'   => array(
			'label'  => 'Discover a solution they did not know',
			'prompt' => 'Start from the problem or job the reader has, then introduce the product as a way to solve it. Name the situations in which someone would go looking for it.',
		),
		'explain'    => array(
			'label'  => 'Explain a complex product in plain language',
			'prompt' => 'Explain how the product works in plain language. Define each technical term the first time it appears and describe every feature by what the user can do with it.',
		),
		'criteria'   => array(
			'label'  => 'Selection criteria that really matter',
			'prompt' => 'Include an H2 section "What to look for" listing the 4-6 criteria that matter when choosing a product of this type and how this product does on each. Do not name, rank or compare against competing brands.',
		),
		'risks'      => array(
			'label'  => 'Risks, limits and who it is not for',
			'prompt' => 'Include an H2 section "Who it is not for" and make every limitation specific: plan limits, renewal pricing, refund terms, missing features, and complaints repeated across the sources. Generic cons such as "has a learning curve" or "raises ethical considerations" are not allowed unless the sources document them. The cons array must follow the same rule.',
		),
		'onboarding' => array(
			'label'  => 'What to do after signing up',
			'prompt' => 'Include an H2 section "Getting started: your first result" with 3-5 numbered steps from sign-up to a first useful result, and the mistakes new users commonly make.',
		),
	);
	return apply_filters( 'bigspy_customer_values', $values );
}

/** Route key from a request value; falls back to the listing profile. */
function bigspy_sanitize_content_route( $route ) {
	$route = sanitize_key( (string) $route );
	return array_key_exists( $route, bigspy_content_routes() ) ? $route : 'profile';
}

/** Value keys from a comma-separated request value, limited to known keys. */
function bigspy_sanitize_customer_values( $raw ) {
	$keys = array_map( 'sanitize_key', preg_split( '/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY ) );
	return array_values( array_intersect( array_keys( bigspy_customer_values() ), $keys ) );
}

/** Prompt block for the chosen route and values. */
function bigspy_content_brief_prompt( $route, array $values ) {
	$routes = bigspy_content_routes();
	$all    = bigspy_customer_values();
	$r      = $routes[ $route ] ?? $routes['profile'];
	$out    = "CONTENT BRIEF (set by the editor; it overrides the default article structure where they differ):\n"
		. 'Content route: ' . $r['label'] . '. The whole listing must answer this reader question: "' . $r['question'] . "\"\n"
		. $r['prompt'] . "\n";
	if ( $values ) {
		$out .= "Customer values this listing must deliver:\n";
		foreach ( $values as $key ) {
			if ( isset( $all[ $key ] ) ) {
				$out .= '- ' . $all[ $key ]['label'] . ': ' . $all[ $key ]['prompt'] . "\n";
			}
		}
	}
	$out .= 'Evidence rule: today is ' . wp_date( 'F j, Y' ) . '. When you state a price, plan limit or offer, it must come from the sources; add "as of ' . wp_date( 'F Y' ) . '" once in the pricing section. '
		. "If the sources do not give a fact the brief asks for, tell the reader what to verify on the official website instead of filling the gap.\n"
		. 'Also return best_for (array of up to 4 short user profiles), not_for (array of up to 3 short profiles of people who should not choose this product) and getting_started (array of 3-5 short steps). Leave an array empty when the sources do not support it.';
	return $out;
}

/** Editor notes field on the Create listing form. */
function bigspy_render_editor_notes_field() {
	?>
	<div style="margin-top:14px;padding:12px;background:#f0f7f0;border:1px solid #b8d8b8;border-radius:6px">
		<label for="bigspy-editor-notes"><strong>Editor notes: what you saw when you tried it</strong></label>
		<textarea id="bigspy-editor-notes" rows="3" style="width:100%;margin-top:6px" placeholder="Optional, strongly recommended. First-hand findings only: what you tested, the result, what surprised you, what failed."></textarea>
		<p class="description" style="margin:4px 0 0">This is what makes the listing different from every other page about this product. Without notes the AI never claims hands-on testing.</p>
	</div>
	<?php
}

/** Reader-value checkboxes for the Post Writer. */
function bigspy_render_customer_values_field( $saved = null ) {
	$all   = bigspy_customer_values();
	$saved = is_array( $saved ) ? $saved : array_keys( $all );
	foreach ( $all as $key => $value ) {
		echo '<label style="display:block;margin:4px 0"><input type="checkbox" class="bigspy-customer-value" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $saved, true ), true, false ) . '> ' . esc_html( $value['label'] ) . '</label>';
	}
}

/** Prompt block for the editor's own first-hand notes. */
function bigspy_editor_notes_prompt( $notes ) {
	$notes = trim( (string) $notes );
	if ( '' === $notes ) {
		return "EDITOR'S FIRST-HAND NOTES: none supplied. Do not claim or imply that this site tested the product.";
	}
	return "EDITOR'S FIRST-HAND NOTES (the only first-hand material; treat as fact observed by this site's editor):\n" . $notes
		. "\nWork these findings into the relevant sections and give them an H2 section \"Our hands-on notes\". Do not add test results that are not in the notes.";
}

/**
 * Pages the AI may link to from a new listing or post: published listings and posts,
 * newest first. Listings carry their deepest category so the AI can judge relevance.
 */
function bigspy_internal_link_candidates( $exclude_id = 0, $max_listings = 40, $max_posts = 20 ) {
	$out = array();
	if ( post_type_exists( 'product' ) ) {
		$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => $max_listings, 'fields' => 'ids', 'orderby' => 'modified', 'exclude' => array_filter( array( (int) $exclude_id ) ), 'no_found_rows' => true ) );
		foreach ( $ids as $id ) {
			$terms = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'all' ) );
			$cat   = '';
			if ( ! is_wp_error( $terms ) && $terms ) {
				$deepest = function_exists( 'bigspy_deepest_term_id' ) ? (int) bigspy_deepest_term_id( wp_list_pluck( $terms, 'term_id' ) ) : 0;
				foreach ( $terms as $term ) {
					if ( ! $deepest || (int) $term->term_id === $deepest ) {
						$cat       = $term->name;
						$term_link = get_term_link( $term );
						if ( is_string( $term_link ) ) {
							$out[ $term_link ] = array( 'type' => 'category', 'title' => $term->name, 'url' => $term_link );
						}
						break;
					}
				}
			}
			$out[ get_permalink( $id ) ] = array( 'type' => 'listing', 'title' => get_the_title( $id ), 'category' => $cat, 'url' => get_permalink( $id ) );
		}
	}
	$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => $max_posts, 'fields' => 'ids', 'exclude' => array_filter( array( (int) $exclude_id ) ), 'no_found_rows' => true ) );
	foreach ( $posts as $id ) {
		$out[ get_permalink( $id ) ] = array( 'type' => 'article', 'title' => get_the_title( $id ), 'url' => get_permalink( $id ) );
	}
	$out = array_filter( $out, function ( $row ) {
		return is_string( $row['url'] ) && '' !== $row['url'];
	} );
	return apply_filters( 'bigspy_internal_link_candidates', array_values( $out ), $exclude_id );
}

/** Prompt block listing the internal link candidates. */
function bigspy_internal_links_prompt( array $candidates, $min = 3, $max = 6 ) {
	if ( ! $candidates ) {
		return 'INTERNAL LINK CANDIDATES: none yet. Do not link to other pages of this site.';
	}
	return "INTERNAL LINK CANDIDATES (pages of this site):\n" . wp_json_encode( $candidates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. "\nLink to {$min}-{$max} of these inside the article body, only where the page helps the reader at that point: a related tool for the same job, the category the product belongs to, or an article that explains the topic. "
		. 'Use descriptive anchor text taken from the sentence, never "click here" and never the bare URL. Link each URL once. Use the URLs exactly as given and never link to any other page of this site. If fewer candidates are relevant, use fewer.';
}

/** Comparable form of a URL: host and path, no scheme, www, query or trailing slash. */
function bigspy_link_key( $url ) {
	$parts = wp_parse_url( html_entity_decode( (string) $url ) );
	if ( empty( $parts['host'] ) ) {
		return '';
	}
	return preg_replace( '/^www\./', '', strtolower( $parts['host'] ) ) . untrailingslashit( $parts['path'] ?? '' );
}

/** Unwraps links to this site that are not in the allowed list, so an invented URL never becomes a broken link. */
function bigspy_strip_unknown_internal_links( $html, array $allowed_urls ) {
	$site = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) );
	$keys = array_filter( array_map( 'bigspy_link_key', $allowed_urls ) );
	return preg_replace_callback( '/<a\b[^>]*>(.*?)<\/a>/is', function ( $m ) use ( $site, $keys ) {
		if ( ! preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/i', $m[0], $href ) ) {
			return $m[0];
		}
		$key = bigspy_link_key( $href[2] );
		if ( '' === $key || 0 !== strpos( $key, $site ) ) {
			return $m[0];
		}
		return in_array( $key, $keys, true ) ? $m[0] : $m[1];
	}, (string) $html );
}

/** Content routes for a blog post. Comparison (X vs Y) is left out on purpose. */
function bigspy_post_routes() {
	$routes = array(
		'guide'        => array( 'label' => 'Guide', 'question' => 'What do I need to understand about this topic?', 'prompt' => 'Explain the topic for someone new to it, then show how the supplied tools fit in.' ),
		'best_list'    => array( 'label' => 'Best List', 'question' => 'Which products should I consider for this job?', 'prompt' => 'Open with the job to be done and the criteria that matter. Give each supplied product its own section: what it does well, who it suits, who it does not suit, and how to start. Do not number them as a ranking and do not declare an overall winner.' ),
		'tutorial'     => array( 'label' => 'Tutorial', 'question' => 'How do I get this job done?', 'prompt' => 'Lead with the outcome, list what is needed, then give numbered steps to a finished result, with common mistakes and how to check the result.' ),
		'pricing'      => array( 'label' => 'Pricing', 'question' => 'How much will I pay and which plan is enough?', 'prompt' => 'Explain the plans, limits, renewal and overage costs and refund terms from the research, and say which plan suits which user. Use only prices found in the research and say when they were checked.' ),
		'trial'        => array( 'label' => 'Trial & Demo', 'question' => 'What should I check before I pay?', 'prompt' => 'Explain what the free plan, trial or demo includes and give a checklist of what to test before paying.' ),
		'offers'       => array( 'label' => 'Offers & Deals', 'question' => 'Is there a real offer and does it apply to me?', 'prompt' => 'Cover only offers confirmed by the research, with exact conditions, who qualifies and the end date. Say so plainly when an offer cannot be confirmed.' ),
		'alternatives' => array( 'label' => 'Alternatives by need', 'question' => 'What else solves this need?', 'prompt' => 'Organize by reader need ("if you need X"), not by brand. Present each supplied product on its own merits and never describe another product as worse.' ),
		'testing'      => array( 'label' => 'Case Study & Testing', 'question' => 'What results does it produce and where are the limits?', 'prompt' => 'Organize around documented results and their conditions and limits. Present a result as tested by this site only when the article request describes that test.' ),
		'migration'    => array( 'label' => 'Migration', 'question' => 'How do I move to the new system and what do I check?', 'prompt' => 'Give a before, during and after checklist for switching: what to export, what breaks, how to verify, and how to roll back.' ),
	);
	return apply_filters( 'bigspy_post_routes', $routes );
}

/** Route key for a post; falls back to the guide. */
function bigspy_sanitize_post_route( $route ) {
	$route = sanitize_key( (string) $route );
	return array_key_exists( $route, bigspy_post_routes() ) ? $route : 'guide';
}

/** Prompt block for a post's route and the reader values it must deliver. */
function bigspy_post_brief_prompt( $route, $values = null ) {
	$routes = bigspy_post_routes();
	$all    = bigspy_customer_values();
	$r      = $routes[ $route ] ?? $routes['guide'];
	$values = is_array( $values ) ? $values : array_keys( $all );
	$out    = 'CONTENT ROUTE: ' . $r['label'] . '. The whole article must answer this reader question: "' . $r['question'] . '" ' . $r['prompt'];
	if ( $values ) {
		$out .= "\nREADER VALUES this article must deliver (apply them to the topic and to each product covered; adapt the section headings to the article):";
		foreach ( $values as $key ) {
			if ( isset( $all[ $key ] ) ) {
				$out .= "\n- " . $all[ $key ]['label'] . ': ' . $all[ $key ]['prompt'];
			}
		}
	}
	return $out;
}
