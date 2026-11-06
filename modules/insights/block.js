/**
 * Site Insights block. Category "bigspy-list", so BIGSPY AI Theme WP locks it while its List mode is off.
 */
(function (wp) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;

	wp.blocks.registerBlockType('bsl/site-insights', {
		title: __('Site Insights', 'bigspy-ai-list-suite'),
		description: __('Uptime, SSL, technology and Core Web Vitals of the website behind this listing.', 'bigspy-ai-list-suite'),
		category: 'bigspy-list',
		icon: 'chart-area',
		attributes: { postId: { type: 'number', default: 0 } },
		edit: function (props) {
			var postId = props.attributes.postId;
			var current = wp.data.select('core/editor') ? wp.data.select('core/editor').getCurrentPostId() : 0;
			return el('div', be.useBlockProps(),
				el(be.InspectorControls, null,
					el(c.PanelBody, { title: __('Listing', 'bigspy-ai-list-suite') },
						el(c.TextControl, {
							label: __('Listing ID (empty: this listing)', 'bigspy-ai-list-suite'),
							type: 'number',
							value: postId || '',
							onChange: function (v) { props.setAttributes({ postId: parseInt(v, 10) || 0 }); }
						})
					)
				),
				el(wp.serverSideRender, {
					block: 'bsl/site-insights',
					attributes: { postId: postId || current || 0 },
					EmptyResponsePlaceholder: function () {
						return el(c.Placeholder, { icon: 'chart-area', label: __('Site Insights', 'bigspy-ai-list-suite'), instructions: __('No website data for this listing yet. Data is collected in the background.', 'bigspy-ai-list-suite') });
					}
				})
			);
		},
		save: function () { return null; }
	});
})(window.wp);
