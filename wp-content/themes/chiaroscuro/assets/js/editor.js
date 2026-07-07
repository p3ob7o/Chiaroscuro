/**
 * Registers editor-only block variations for Chiaroscuro.
 *
 * @package Chiaroscuro
 */

(function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.domReady ) {
		return;
	}

	wp.domReady(
		function () {
			wp.blocks.registerBlockVariation(
				'core/query',
				{
					name: 'chiaroscuro-related-by-tag',
					title: 'Chiaroscuro related posts',
					description: 'Posts sharing the current post tags.',
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
