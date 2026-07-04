<?php
/**
 * Chiaroscuro child theme functions.
 *
 * @package Chiaroscuro
 */

add_action( 'after_setup_theme', 'chiaroscuro_setup' );
add_action( 'wp_enqueue_scripts', 'chiaroscuro_enqueue_styles' );
add_action( 'wp_head', 'chiaroscuro_print_theme_boot_script', 0 );
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
add_filter( 'render_block_core/navigation', 'chiaroscuro_render_navigation', 10, 2 );
add_filter( 'render_block_core/navigation-link', 'chiaroscuro_render_navigation_link', 10, 2 );
add_filter( 'render_block_core/post-featured-image', 'chiaroscuro_render_featured_image_caption', 10, 2 );
add_filter( 'render_block_core/query', 'chiaroscuro_render_related_query', 10, 2 );

/**
 * Configure theme support.
 */
function chiaroscuro_setup(): void {
	add_editor_style( 'style.css' );
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
 * Print a tiny pre-paint theme resolver to avoid a wrong-theme flash.
 */
function chiaroscuro_print_theme_boot_script(): void {
	?>
	<script>
	(function(){try{var k='pb-theme',t=localStorage.getItem(k);if(t!=='dark'&&t!=='light'){t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.dataset.theme=t;document.documentElement.style.colorScheme=t;}catch(e){document.documentElement.dataset.theme='light';document.documentElement.style.colorScheme='light';}}());
	</script>
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

	if ( ! is_singular() || ! str_contains( $class_name, 'chiaroscuro-post-hero' ) || str_contains( $content, '<figcaption' ) ) {
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

	return preg_replace( '#</figure>\s*$#', $caption_markup . '</figure>', $content, 1 ) ?? $content;
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
		true
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
