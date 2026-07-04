(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.domReady) {
		return;
	}

	wp.domReady(function () {
		wp.blocks.registerBlockVariation('core/query', {
			name: 'chiaroscuro-related-by-tag',
			title: 'Chiaroscuro related posts',
			description: 'Posts sharing the current post tags.',
			icon: 'tag',
			attributes: {
				namespace: 'chiaroscuro-related',
				query: {
					perPage: 4,
					pages: 0,
					offset: 0,
					postType: 'post',
					order: 'desc',
					orderBy: 'date',
					inherit: false
				}
			},
			scope: ['inserter', 'transform'],
			isActive: function (blockAttributes) {
				return blockAttributes.namespace === 'chiaroscuro-related';
			}
		});
	});
}(window.wp));
