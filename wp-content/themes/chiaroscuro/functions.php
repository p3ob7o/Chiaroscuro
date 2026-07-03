<?php
/**
 * Chiaroscuro child theme functions.
 */

add_action( 'wp_enqueue_scripts', 'chiaroscuro_enqueue_styles' );

/**
 * Load the child theme stylesheet.
 */
function chiaroscuro_enqueue_styles(): void {
	wp_enqueue_style(
		'chiaroscuro-style',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
