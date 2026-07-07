/**
 * Registers editor-only block variations for Chiaroscuro.
 *
 * @package Chiaroscuro
 */

(function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.domReady || ! wp.i18n ) {
		return;
	}

	var __ = wp.i18n.__;

	wp.domReady(
		function () {
			wp.blocks.registerBlockVariation(
				'core/query',
				{
					name: 'chiaroscuro-related-by-tag',
					title: __( 'Chiaroscuro related posts', 'chiaroscuro' ),
					description: __( 'Posts sharing the current post tags.', 'chiaroscuro' ),
					icon: 'tag',
					attributes: {
						namespace: 'chiaroscuro-related',
						query: {
							inherit: false,
							offset: 0,
							order: 'desc',
							orderBy: 'date',
							pages: 0,
							perPage: 4,
							postType: 'post',
						},
					},
					scope: [ 'inserter', 'transform' ],
					isActive: function ( blockAttributes ) {
						return blockAttributes.namespace === 'chiaroscuro-related';
					},
				}
			);
		}
	);
}( window.wp ));
