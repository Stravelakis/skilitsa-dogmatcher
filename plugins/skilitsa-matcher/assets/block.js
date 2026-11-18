/**
 * Editor side of the Skilitsa matcher block.
 *
 * Plain browser JavaScript on purpose: this plugin has no build step, and
 * adding one so a placeholder can be written in JSX would be a poor trade.
 *
 * The editor deliberately shows a placeholder rather than the live quiz. The
 * quiz fetches its questions over REST and writes into its own mount point;
 * running that inside the editor would fire real requests and record real
 * telemetry every time someone opened the page for editing.
 */
(function (blocks, element, blockEditor) {
	'use strict';

	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType('skilitsa/matcher', {
		edit: function () {
			return el(
				'div',
				useBlockProps({ className: 'skilitsa-matcher-placeholder' }),
				el('strong', {}, 'Skilitsa matcher'),
				el(
					'p',
					{ style: { margin: '4px 0 0', fontSize: '13px' } },
					'The breed quiz renders here on the published page.'
				)
			);
		},

		// Dynamic block: the markup is a mount point a script fills, so there
		// is nothing meaningful to save into post content.
		save: function () {
			return null;
		},
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor);
