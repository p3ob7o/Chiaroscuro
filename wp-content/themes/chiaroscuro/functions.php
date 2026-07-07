<?php
/**
 * Chiaroscuro child theme functions.
 *
 * @package Chiaroscuro
 */

add_action( 'after_setup_theme', 'chiaroscuro_setup' );
add_action( 'wp_enqueue_scripts', 'chiaroscuro_enqueue_styles' );
add_action( 'wp_enqueue_scripts', 'chiaroscuro_optimize_frontend_assets', 1000 );
add_action( 'wp_head', 'chiaroscuro_print_theme_boot_script', 0 );
add_action( 'wp_head', 'chiaroscuro_print_newsreader_font_faces', 1 );
add_action( 'wp_footer', 'chiaroscuro_start_footer_performance_buffer', 0 );
add_action( 'admin_head', 'chiaroscuro_print_newsreader_font_faces' );
add_action( 'init', 'chiaroscuro_register_blocks' );
add_action( 'init', 'chiaroscuro_register_pattern_category' );
add_action( 'init', 'chiaroscuro_unregister_non_theme_patterns', 100 );
add_action( 'init', 'chiaroscuro_disable_emoji_support' );
add_action( 'enqueue_block_editor_assets', 'chiaroscuro_enqueue_editor_assets' );
add_filter( 'should_load_remote_block_patterns', '__return_false' );
add_filter( 'query_loop_block_query_vars', 'chiaroscuro_related_query_vars', 10, 3 );
add_filter( 'comment_form_defaults', 'chiaroscuro_comment_form_defaults' );
add_filter( 'comment_form_default_fields', 'chiaroscuro_comment_form_fields' );
add_filter( 'pre_option_use_smilies', '__return_zero' );
add_filter( 'render_block_core/comments-title', 'chiaroscuro_render_comments_title', 10, 2 );
add_filter( 'comment_text', 'chiaroscuro_normalize_comment_smilies', 99 );
add_filter( 'render_block_core/navigation', 'chiaroscuro_render_navigation', 10, 2 );
add_filter( 'render_block_core/navigation-link', 'chiaroscuro_render_navigation_link', 10, 2 );
add_filter( 'render_block_core/post-featured-image', 'chiaroscuro_render_featured_image_caption', 10, 2 );
add_filter( 'render_block_core/query', 'chiaroscuro_render_river_query', 10, 2 );
add_filter( 'render_block_core/query', 'chiaroscuro_render_related_query', 10, 2 );
add_filter( 'script_loader_tag', 'chiaroscuro_defer_frontend_script_tag', 10, 3 );

/**
 * Configure theme support.
 */
function chiaroscuro_setup(): void {
	add_editor_style( 'style.css' );
	add_image_size( 'chiaroscuro-river-thumbnail', 104, 132, true );
}

/**
 * Load the child theme stylesheet and register the toggle script handle.
 */
function chiaroscuro_enqueue_styles(): void {
	$theme = wp_get_theme();

	wp_enqueue_style(
		'chiaroscuro-style',
		get_stylesheet_uri(),
		array(),
		$theme->get( 'Version' )
	);

	wp_register_script(
		'chiaroscuro-theme-toggle',
		get_theme_file_uri( 'assets/js/theme-toggle.js' ),
		array(),
		$theme->get( 'Version' ),
		array( 'strategy' => 'defer' )
	);

	wp_enqueue_script( 'chiaroscuro-theme-toggle' );
}

/**
 * Trim non-visual frontend asset work that PageSpeed reports on read views.
 */
function chiaroscuro_optimize_frontend_assets(): void {
	$defer_handles = array(
		'iawm-link-fixer-front-link-checker',
		'jetpack-carousel',
		'no-orphan-words',
		'wp-dom-ready',
		'wp-polyfill',
	);

	foreach ( $defer_handles as $handle ) {
		if ( wp_script_is( $handle, 'enqueued' ) ) {
			wp_script_add_data( $handle, 'strategy', 'defer' );
		}
	}

	$unused_style_handles = array(
		'jetpack-carousel',
		'jetpack-swiper-library',
		'tiled-gallery',
	);

	foreach ( $unused_style_handles as $handle ) {
		wp_dequeue_style( $handle );
	}

	if ( ! is_search() ) {
		wp_dequeue_style( 'jetpack-instant-search' );
		wp_dequeue_script( 'jetpack-instant-search' );
	}

	wp_dequeue_script( 'jp-tracks' );
	remove_action( 'wp_footer', 'gauges', 99 );
}

/**
 * Force defer on dependency handles that do not receive strategy attributes.
 *
 * @param string $tag    Script tag markup.
 * @param string $handle Script handle.
 * @param string $src    Script source URL.
 * @return string
 */
function chiaroscuro_defer_frontend_script_tag( string $tag, string $handle, string $src ): string {
	unset( $src );

	$defer_handles = array(
		'wp-dom-ready',
		'wp-polyfill',
	);

	if ( is_admin() || ! in_array( $handle, $defer_handles, true ) || preg_match( '/\s(?:async|defer)(?:\s|=|>)/', $tag ) ) {
		return $tag;
	}

	return str_replace( '<script ', '<script defer ', $tag );
}

/**
 * Start a scoped footer buffer so third-party snippets can be filtered safely.
 */
function chiaroscuro_start_footer_performance_buffer(): void {
	if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
		return;
	}

	ob_start( 'chiaroscuro_filter_footer_performance_markup' );
}

/**
 * Remove the Gauges tracker snippet whose third-party cache lifetime is fixed.
 *
 * @param string $markup Footer markup.
 * @param int    $phase  Output buffering phase.
 * @return string
 */
function chiaroscuro_filter_footer_performance_markup( string $markup, int $phase = 0 ): string {
	unset( $phase );

	if ( ! str_contains( $markup, 'gauges-tracker' ) || ! str_contains( $markup, 'secure.gaug.es/track.js' ) ) {
		return $markup;
	}

	$filtered = preg_replace(
		'#\s*<script\b[^>]*>(?:(?!</script>).)*gauges-tracker(?:(?!</script>).)*secure\.gaug\.es/track\.js(?:(?!</script>).)*</script>#s',
		'',
		$markup,
		1
	);

	return is_string( $filtered ) ? $filtered : $markup;
}

/**
 * Render a tightly sized thumbnail for the homepage river.
 *
 * @param int $attachment_id Featured image attachment ID.
 * @return string
 */
function chiaroscuro_render_river_thumbnail( int $attachment_id ): string {
	$size = 'chiaroscuro-river-thumbnail';

	chiaroscuro_ensure_attachment_image_size( $attachment_id, $size );

	return wp_get_attachment_image(
		$attachment_id,
		$size,
		false,
		array(
			'sizes' => '52px',
		)
	);
}

/**
 * Generate a registered image sub-size for existing uploads when it is missing.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $size          Registered image size name.
 */
function chiaroscuro_ensure_attachment_image_size( int $attachment_id, string $size ): void {
	if ( image_get_intermediate_size( $attachment_id, $size ) ) {
		return;
	}

	if ( ! function_exists( 'wp_update_image_subsizes' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	if ( function_exists( 'wp_update_image_subsizes' ) ) {
		wp_update_image_subsizes( $attachment_id );
	}
}

/**
 * Print a tiny pre-paint theme resolver to avoid a wrong-theme flash.
 */
function chiaroscuro_print_theme_boot_script(): void {
	?>
	<script>
	(function () {
		'use strict';

		try {
			var key = 'pb-theme';
			var theme = localStorage.getItem( key );

			if ( theme !== 'dark' && theme !== 'light' ) {
				theme = window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches
					? 'dark'
					: 'light';
			}

			document.documentElement.dataset.theme = theme;
			document.documentElement.style.colorScheme = theme;
		} catch {
			// Fall back to light mode when storage or media queries are unavailable.
			document.documentElement.dataset.theme = 'light';
			document.documentElement.style.colorScheme = 'light';
		}
	}());
	</script>
	<?php
}

/**
 * Prefer installed Newsreader before falling back to the bundled webfont.
 */
function chiaroscuro_print_newsreader_font_faces(): void {
	$normal_url = get_theme_file_uri( 'assets/fonts/newsreader/Newsreader-Variable.woff2' );
	$italic_url = get_theme_file_uri( 'assets/fonts/newsreader/Newsreader-Italic-Variable.woff2' );
	?>
	<style id="chiaroscuro-newsreader-font-face">
	@font-face {
		font-family: "Chiaroscuro Newsreader";
		font-style: normal;
		font-weight: 200 800;
		font-display: swap;
		src:
			local("Newsreader 16pt Regular"),
			local("Newsreader16pt-Regular"),
			local("Newsreader 24pt Regular"),
			local("Newsreader24pt-Regular"),
			local("Newsreader Regular"),
			local("Newsreader-Regular"),
			local("Newsreader"),
			url("<?php echo esc_url( $normal_url ); ?>") format("woff2");
	}

	@font-face {
		font-family: "Chiaroscuro Newsreader";
		font-style: italic;
		font-weight: 200 800;
		font-display: swap;
		src:
			local("Newsreader 16pt Italic"),
			local("Newsreader16pt-Italic"),
			local("Newsreader 24pt Italic"),
			local("Newsreader24pt-Italic"),
			local("Newsreader Italic"),
			local("Newsreader-Italic"),
			local("Newsreader"),
			url("<?php echo esc_url( $italic_url ); ?>") format("woff2");
	}
	</style>
	<?php
}

/**
 * Register custom blocks.
 */
function chiaroscuro_register_blocks(): void {
	wp_register_script(
		'chiaroscuro-theme-toggle',
		get_theme_file_uri( 'assets/js/theme-toggle.js' ),
		array(),
		wp_get_theme()->get( 'Version' ),
		array( 'strategy' => 'defer' )
	);

	register_block_type( __DIR__ . '/blocks/theme-toggle' );
}

/**
 * Keep comments and frontend chrome in the theme's text-only monochrome system.
 */
function chiaroscuro_disable_emoji_support(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_print_styles', 'wp_enqueue_emoji_styles' );
	remove_filter( 'comment_text', 'convert_smilies', 20 );
}

/**
 * Render the theme toggle button.
 *
 * @return string
 */
function chiaroscuro_render_theme_toggle(): string {
	return sprintf(
		'<button type="button" class="wp-block-chiaroscuro-theme-toggle" aria-label="%1$s" aria-pressed="false"><span class="chiaroscuro-theme-toggle__glyph" aria-hidden="true"></span></button>',
		esc_attr__( 'Toggle light and dark mode', 'chiaroscuro' )
	);
}

/**
 * Inject the toggle into the rendered navigation element.
 *
 * @param string $content Rendered block content.
 * @param array  $block   Parsed block.
 * @return string
 */
function chiaroscuro_render_navigation( string $content, array $block ): string {
	$class_name = $block['attrs']['className'] ?? '';

	if ( ! str_contains( $class_name, 'chiaroscuro-nav' ) || str_contains( $content, 'wp-block-chiaroscuro-theme-toggle' ) ) {
		return $content;
	}

	return preg_replace( '#</nav>#', chiaroscuro_render_theme_toggle() . '</nav>', $content, 1 ) ?? $content;
}

/**
 * Add current-menu-item to custom navigation links when paths match.
 *
 * @param string $content Rendered block content.
 * @param array  $block   Parsed block.
 * @return string
 */
function chiaroscuro_render_navigation_link( string $content, array $block ): string {
	$url = $block['attrs']['url'] ?? '';

	if ( '' === $url || str_starts_with( $url, 'http' ) ) {
		return $content;
	}

	$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$link_path    = wp_parse_url( home_url( $url ), PHP_URL_PATH );
	$current_path = wp_parse_url( $request_uri, PHP_URL_PATH );
	$link_path    = trailingslashit( is_string( $link_path ) && '' !== $link_path ? $link_path : '/' );
	$current_path = trailingslashit( is_string( $current_path ) && '' !== $current_path ? $current_path : '/' );

	if ( $link_path !== $current_path || str_contains( $content, 'current-menu-item' ) ) {
		return $content;
	}

	return preg_replace( '/class="([^"]*wp-block-navigation-item[^"]*)"/', 'class="$1 current-menu-item"', $content, 1 ) ?? $content;
}

/**
 * Add the featured image attachment caption to the post hero.
 *
 * @param string $content Rendered block content.
 * @param array  $block   Parsed block.
 * @return string
 */
function chiaroscuro_render_featured_image_caption( string $content, array $block ): string {
	$class_name = $block['attrs']['className'] ?? '';
	$is_hero    = str_contains( $class_name, 'chiaroscuro-post-hero' ) || str_contains( $content, 'chiaroscuro-post-hero' );

	if ( ! is_singular() || ! $is_hero || str_contains( $content, '<figcaption' ) ) {
		return $content;
	}

	$thumbnail_id = get_post_thumbnail_id();

	if ( ! $thumbnail_id ) {
		return $content;
	}

	$caption = wp_get_attachment_caption( $thumbnail_id );

	if ( '' === $caption ) {
		return $content;
	}

	$caption_markup = sprintf(
		'<figcaption class="wp-element-caption">%s</figcaption>',
		esc_html( $caption )
	);

	return preg_replace( '#</figure>#', $caption_markup . '</figure>', $content, 1 ) ?? $content;
}

/**
 * Keep common comment smileys as text instead of rendered emoji glyphs.
 *
 * @param string $content Rendered comment text.
 * @return string
 */
function chiaroscuro_normalize_comment_smilies( string $content ): string {
	return str_replace(
		html_entity_decode( '&#x1f642;', ENT_QUOTES, 'UTF-8' ),
		':)',
		$content
	);
}

/**
 * Register the theme pattern category.
 */
function chiaroscuro_register_pattern_category(): void {
	register_block_pattern_category(
		'chiaroscuro',
		array( 'label' => esc_html__( 'Chiaroscuro', 'chiaroscuro' ) )
	);
}

/**
 * Keep the inserter focused on this child theme's patterns.
 */
function chiaroscuro_unregister_non_theme_patterns(): void {
	if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
		return;
	}

	$registry = WP_Block_Patterns_Registry::get_instance();

	foreach ( $registry->get_all_registered() as $pattern ) {
		if ( empty( $pattern['name'] ) || str_starts_with( $pattern['name'], 'chiaroscuro/' ) ) {
			continue;
		}

		$registry->unregister( $pattern['name'] );
	}
}

/**
 * Register the related-post Query Loop variation in the editor.
 */
function chiaroscuro_enqueue_editor_assets(): void {
	wp_enqueue_script(
		'chiaroscuro-editor',
		get_theme_file_uri( 'assets/js/editor.js' ),
		array( 'wp-blocks', 'wp-dom-ready', 'wp-i18n' ),
		wp_get_theme()->get( 'Version' ),
		array( 'in_footer' => true )
	);
}

/**
 * Convert the related Query Loop variation into a current-post tag query.
 *
 * @param array    $query Query vars.
 * @param WP_Block $block Query block instance.
 * @param int      $page  Current query page.
 * @return array
 */
function chiaroscuro_related_query_vars( array $query, WP_Block $block, int $page ): array {
	unset( $page );

	$namespace = $block->parsed_block['attrs']['namespace'] ?? '';

	if ( 'chiaroscuro-related' !== $namespace ) {
		return $query;
	}

	$post_id = get_queried_object_id();

	if ( ! $post_id ) {
		$query['post__in'] = array( 0 );
		return $query;
	}

	$tag_ids = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );

	if ( empty( $tag_ids ) ) {
		$query['post__in'] = array( 0 );
		return $query;
	}

	$query['tag__in']             = $tag_ids;
	$query['post__not_in']        = array( $post_id );
	$query['posts_per_page']      = 4;
	$query['ignore_sticky_posts'] = true;
	$query['orderby']             = 'date';
	$query['order']               = 'DESC';

	return $query;
}

/**
 * Render the homepage post river with year group markers.
 *
 * @param string $content Rendered block content.
 * @param array  $block   Parsed block.
 * @return string
 */
function chiaroscuro_render_river_query( string $content, array $block ): string {
	$namespace = $block['attrs']['namespace'] ?? '';

	if ( 'chiaroscuro-river' !== $namespace ) {
		return $content;
	}

	$query_attrs = $block['attrs']['query'] ?? array();
	$per_page    = max( 1, absint( $query_attrs['perPage'] ?? 10 ) );
	$base_offset = max( 0, absint( $query_attrs['offset'] ?? 0 ) );
	$post_type   = $query_attrs['postType'] ?? 'post';
	$order       = $query_attrs['order'] ?? 'desc';
	$order_by    = $query_attrs['orderBy'] ?? 'date';
	$query_id    = isset( $block['attrs']['queryId'] ) ? absint( $block['attrs']['queryId'] ) : 0;
	$page_key    = $query_id ? 'query-' . $query_id . '-page' : 'query-page';
	$current     = filter_input( INPUT_GET, $page_key, FILTER_VALIDATE_INT );
	$current     = is_int( $current ) && $current > 0 ? $current : 1;
	$offset      = $base_offset + ( ( $current - 1 ) * $per_page );

	$river = new WP_Query(
		array(
			'post_type'           => is_string( $post_type ) && '' !== $post_type ? $post_type : 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'offset'              => $offset,
			'ignore_sticky_posts' => true,
			'orderby'             => is_string( $order_by ) && '' !== $order_by ? $order_by : 'date',
			'order'               => 'asc' === strtolower( (string) $order ) ? 'ASC' : 'DESC',
		)
	);

	if ( ! $river->have_posts() ) {
		return '<div class="wp-block-query chiaroscuro-river__query"><p>' . esc_html__( 'No posts found.', 'chiaroscuro' ) . '</p></div>';
	}

	ob_start();
	?>
	<div class="wp-block-query chiaroscuro-river__query">
		<?php
		$active_year = '';
		$is_first    = true;

		while ( $river->have_posts() ) :
			$river->the_post();
			$post_year = get_the_date( 'Y' );

			if ( $post_year !== $active_year ) :
				if ( '' !== $active_year ) :
					?>
					</ul>
					</div>
					<?php
				endif;

				$active_year = $post_year;
				?>
				<div class="chiaroscuro-river-group">
					<div class="chiaroscuro-section-heading chiaroscuro-river-year-heading">
						<?php if ( $is_first ) : ?>
							<p class="chiaroscuro-section-label"><?php esc_html_e( 'All articles', 'chiaroscuro' ); ?></p>
						<?php else : ?>
							<span class="chiaroscuro-river-year-spacer" aria-hidden="true"></span>
						<?php endif; ?>
						<time class="chiaroscuro-river-year" datetime="<?php echo esc_attr( $post_year ); ?>"><?php echo esc_html( $post_year ); ?></time>
					</div>
					<ul class="wp-block-post-template">
				<?php
				$is_first = false;
			endif;

			$thumbnail_label = sprintf(
				/* translators: %s: post title. */
				__( 'Read %s', 'chiaroscuro' ),
				get_the_title()
			);
			$thumbnail_id = get_post_thumbnail_id();
			?>
			<li <?php post_class( 'wp-block-post' ); ?>>
				<div class="wp-block-group chiaroscuro-river-row">
					<div class="wp-block-post-date"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date( 'M j' ) ); ?></time></div>
					<h2 class="wp-block-post-title"><a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a></h2>
					<?php if ( $thumbnail_id ) : ?>
						<figure class="wp-block-post-featured-image">
							<a href="<?php echo esc_url( get_permalink() ); ?>" aria-label="<?php echo esc_attr( $thumbnail_label ); ?>"><?php echo wp_kses_post( chiaroscuro_render_river_thumbnail( $thumbnail_id ) ); ?></a>
						</figure>
					<?php endif; ?>
				</div>
			</li>
			<?php
		endwhile;
		wp_reset_postdata();
		?>
			</ul>
		</div>
		<?php echo wp_kses_post( chiaroscuro_render_river_pagination( $river, $current, $per_page, $base_offset, $page_key ) ); ?>
	</div>
	<?php

	return (string) ob_get_clean();
}

/**
 * Render pagination for the homepage post river.
 *
 * @param WP_Query $river       Homepage river query.
 * @param int      $current     Current query page.
 * @param int      $per_page    Posts per page.
 * @param int      $base_offset Initial query offset.
 * @param string   $page_key    Query pagination key.
 * @return string
 */
function chiaroscuro_render_river_pagination( WP_Query $river, int $current, int $per_page, int $base_offset, string $page_key ): string {
	$total_posts = max( 0, (int) $river->found_posts - $base_offset );
	$total_pages = (int) ceil( $total_posts / $per_page );

	if ( $total_pages < 2 ) {
		return '';
	}

	$pagination_placeholder = 999999999;
	$pagination_base        = str_replace(
		(string) $pagination_placeholder,
		'%#%',
		esc_url_raw( add_query_arg( $page_key, $pagination_placeholder, chiaroscuro_get_current_url_without_query_page( $page_key ) ) )
	);
	$number_links           = paginate_links(
		array(
			'base'      => $pagination_base,
			'current'   => $current,
			'end_size'  => 1,
			'format'    => '',
			'mid_size'  => 1,
			'prev_next' => false,
			'total'     => $total_pages,
			'type'      => 'array',
		)
	);

	ob_start();
	?>
	<nav class="wp-block-query-pagination chiaroscuro-pagination" aria-label="<?php esc_attr_e( 'Posts pagination', 'chiaroscuro' ); ?>">
		<?php if ( $current > 1 ) : ?>
			<a href="<?php echo esc_url( chiaroscuro_get_river_page_url( $page_key, $current - 1 ) ); ?>"><?php esc_html_e( 'newer posts', 'chiaroscuro' ); ?></a>
		<?php else : ?>
			<span class="chiaroscuro-pagination__spacer" aria-hidden="true"></span>
		<?php endif; ?>

		<?php if ( is_array( $number_links ) ) : ?>
			<div class="wp-block-query-pagination-numbers">
				<?php echo wp_kses_post( implode( "\n", $number_links ) ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $current < $total_pages ) : ?>
			<a href="<?php echo esc_url( chiaroscuro_get_river_page_url( $page_key, $current + 1 ) ); ?>"><?php esc_html_e( 'older posts', 'chiaroscuro' ); ?></a>
		<?php else : ?>
			<span class="chiaroscuro-pagination__spacer" aria-hidden="true"></span>
		<?php endif; ?>
	</nav>
	<?php

	return (string) ob_get_clean();
}

/**
 * Get the current URL without a specific query pagination argument.
 *
 * @param string $page_key Query pagination key.
 * @return string
 */
function chiaroscuro_get_current_url_without_query_page( string $page_key ): string {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	return remove_query_arg( $page_key, home_url( $request_uri ) );
}

/**
 * Build a URL for a river page.
 *
 * @param string $page_key Query pagination key.
 * @param int    $page     Target page.
 * @return string
 */
function chiaroscuro_get_river_page_url( string $page_key, int $page ): string {
	$url = chiaroscuro_get_current_url_without_query_page( $page_key );

	if ( $page > 1 ) {
		$url = add_query_arg( $page_key, $page, $url );
	}

	return $url;
}

/**
 * Render related posts for the Chiaroscuro query namespace.
 *
 * WordPress still receives a real Query Loop variation in the template/editor;
 * this render fallback keeps frontend behavior stable across query internals.
 *
 * @param string $content Rendered block content.
 * @param array  $block   Parsed block.
 * @return string
 */
function chiaroscuro_render_related_query( string $content, array $block ): string {
	$namespace = $block['attrs']['namespace'] ?? '';

	if ( 'chiaroscuro-related' !== $namespace ) {
		return $content;
	}

	$post_id = get_queried_object_id();

	if ( ! $post_id ) {
		return '';
	}

	$tag_ids = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );

	if ( empty( $tag_ids ) ) {
		return '';
	}

	$related = new WP_Query(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => 4,
			'tag__in'             => $tag_ids,
			'post__not_in'        => array( $post_id ),
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		)
	);

	if ( ! $related->have_posts() ) {
		return '';
	}

	ob_start();
	?>
	<div class="wp-block-query">
		<ul class="wp-block-post-template">
			<?php
			while ( $related->have_posts() ) :
				$related->the_post();
				$thumbnail_label = sprintf(
					/* translators: %s: post title. */
					__( 'Read %s', 'chiaroscuro' ),
					get_the_title()
				);
				?>
				<li <?php post_class( 'wp-block-post' ); ?>>
					<?php if ( has_post_thumbnail() ) : ?>
						<figure class="wp-block-post-featured-image">
							<a href="<?php echo esc_url( get_permalink() ); ?>" aria-label="<?php echo esc_attr( $thumbnail_label ); ?>"><?php the_post_thumbnail( 'medium_large' ); ?></a>
						</figure>
					<?php else : ?>
						<div class="wp-block-post-featured-image chiaroscuro-related__placeholder" aria-hidden="true"></div>
					<?php endif; ?>
					<div class="wp-block-post-date"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date( 'M j, Y' ) ); ?></time></div>
					<h3 class="wp-block-post-title"><a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a></h3>
					<div class="wp-block-post-excerpt"><p class="wp-block-post-excerpt__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 12 ) ); ?></p></div>
				</li>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</ul>
	</div>
	<?php

	return (string) ob_get_clean();
}

/**
 * Set accessible placeholder-oriented comment form defaults.
 *
 * @param array $defaults Comment form defaults.
 * @return array
 */
function chiaroscuro_comment_form_defaults( array $defaults ): array {
	$defaults['title_reply']          = esc_html__( 'Leave a reply', 'chiaroscuro' );
	$defaults['title_reply_before']   = '<h3 id="reply-title" class="comment-reply-title">';
	$defaults['title_reply_after']    = '</h3>';
	$defaults['label_submit']         = esc_html__( 'Post comment', 'chiaroscuro' );
	$defaults['comment_notes_before'] = '';
	$defaults['comment_notes_after']  = '';
	$defaults['comment_field']        = sprintf(
		'<p class="comment-form-comment"><label class="screen-reader-text" for="comment">%1$s</label><textarea id="comment" name="comment" cols="45" rows="4" maxlength="65525" required placeholder="%2$s"></textarea></p>',
		esc_html__( 'Comment', 'chiaroscuro' ),
		esc_attr__( 'Write a comment...', 'chiaroscuro' )
	);

	return $defaults;
}

/**
 * Add placeholders while keeping labels available to assistive tech.
 *
 * @param array $fields Comment form fields.
 * @return array
 */
function chiaroscuro_comment_form_fields( array $fields ): array {
	$commenter = wp_get_current_commenter();

	$fields['author'] = sprintf(
		'<p class="comment-form-author"><label class="screen-reader-text" for="author">%1$s</label><input id="author" name="author" type="text" value="%2$s" size="30" autocomplete="name" placeholder="%1$s" /></p>',
		esc_html__( 'Name', 'chiaroscuro' ),
		esc_attr( $commenter['comment_author'] )
	);

	$fields['email'] = sprintf(
		'<p class="comment-form-email"><label class="screen-reader-text" for="email">%1$s</label><input id="email" name="email" type="email" value="%2$s" size="30" autocomplete="email" placeholder="%1$s" /></p>',
		esc_html__( 'Email', 'chiaroscuro' ),
		esc_attr( $commenter['comment_author_email'] )
	);

	if ( isset( $fields['url'] ) ) {
		unset( $fields['url'] );
	}

	if ( isset( $fields['cookies'] ) ) {
		unset( $fields['cookies'] );
	}

	return $fields;
}

/**
 * Render the comments title in the prototype's wording.
 *
 * @param string $content Rendered block content.
 * @param array  $block   Parsed block.
 * @return string
 */
function chiaroscuro_render_comments_title( string $content, array $block ): string {
	unset( $block );

	if ( ! is_singular() ) {
		return $content;
	}

	return sprintf(
		'<h2 class="wp-block-comments-title">%s</h2>',
		esc_html(
			sprintf(
				/* translators: %s: formatted number of comments. */
				__( 'Responses — %s', 'chiaroscuro' ),
				number_format_i18n( get_comments_number() )
			)
		)
	);
}
