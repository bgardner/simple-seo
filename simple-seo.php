<?php
/**
 * Plugin Name: Simple SEO
 * Plugin URI: https://briangardner.com/simple-seo/
 * Description: Set custom title, meta description, robots, and canonical URLs for posts and pages, with built-in Open Graph support.
 * Version: 0.5.1
 * Author: Brian Gardner
 * Author URI: https://briangardner.com/
 * Text Domain: simple-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove core canonical and robots on singular.
 */
add_action( 'template_redirect', function() {

	if ( is_admin() || ! is_singular() ) {
		return;
	}

	remove_action( 'wp_head', 'rel_canonical' );
	remove_action( 'wp_head', 'wp_robots', 1 );

} );

/**
 * Register meta fields.
 */
add_action( 'init', function() {

	$common_args = [
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
	];

	// Posts.
	register_post_meta( 'post', 'simple_seo_seo_title', $common_args );
	register_post_meta( 'post', 'simple_seo_seo_description', $common_args );
	register_post_meta( 'post', 'simple_seo_seo_robots', $common_args );
	register_post_meta( 'post', 'simple_seo_seo_canonical', $common_args );
	register_post_meta( 'post', 'simple_seo_seo_redirect', [
		...$common_args,
		'sanitize_callback' => 'simple_seo_sanitize_redirect',
	] );

	// Pages.
	register_post_meta( 'page', 'simple_seo_seo_title', $common_args );
	register_post_meta( 'page', 'simple_seo_seo_description', $common_args );
	register_post_meta( 'page', 'simple_seo_seo_robots', $common_args );
	register_post_meta( 'page', 'simple_seo_seo_canonical', $common_args );
	register_post_meta( 'page', 'simple_seo_seo_redirect', [
		...$common_args,
		'sanitize_callback' => 'simple_seo_sanitize_redirect',
	] );

} );


/**
 * Sanitize redirect destinations.
 *
 * Supports same-site relative paths such as /suede/ and absolute HTTP(S) URLs.
 */
function simple_seo_sanitize_redirect( $value ) {

	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	if ( str_starts_with( $value, '/' ) && ! str_starts_with( $value, '//' ) ) {
		return sanitize_text_field( $value );
	}

	return esc_url_raw( $value, [ 'http', 'https' ] );
}

/**
 * Redirect singular posts/pages when a redirect URL is set.
 */
add_action( 'template_redirect', 'simple_seo_redirect', 0 );
function simple_seo_redirect() {

	if ( is_admin() || ! is_singular() ) {
		return;
	}

	$id       = get_queried_object_id();
	$redirect = get_post_meta( $id, 'simple_seo_seo_redirect', true );

	if ( ! $redirect ) {
		return;
	}

	if ( str_starts_with( $redirect, '/' ) ) {
		$redirect = home_url( $redirect );
	}

	// Avoid a redirect loop if the destination resolves to the current URL.
	if ( untrailingslashit( $redirect ) === untrailingslashit( get_permalink( $id ) ) ) {
		return;
	}

	wp_redirect( $redirect, 301, 'Simple SEO' );
	exit;
}

/**
 * Override title.
 */
add_filter( 'pre_get_document_title', 'simple_seo_custom_title', 10, 1 );
function simple_seo_custom_title( $title ) {

	if ( ! is_singular() ) {
		return $title;
	}

	$custom = get_post_meta( get_queried_object_id(), 'simple_seo_seo_title', true );

	return $custom ?: $title;
}

/**
 * Only enqueue our sidebar script in the post/page editor.
 */
add_action( 'enqueue_block_editor_assets', function() {

	if ( ! function_exists( 'get_current_screen' ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || ! in_array( $screen->post_type, [ 'post', 'page' ], true ) ) {
		return;
	}

	wp_enqueue_script(
		'simple-seo-sidebar',
		plugin_dir_url( __FILE__ ) . 'simple-seo-sidebar.js',
		[
			'wp-plugins',
			'wp-edit-post',
			'wp-element',
			'wp-components',
			'wp-data',
			'wp-block-editor',
			'wp-compose',
		],
		filemtime( __DIR__ . '/simple-seo-sidebar.js' )
	);

} );

/**
 * Output robots tag.
 */
add_action( 'wp_head', 'simple_seo_robots_output', 1 );
function simple_seo_robots_output() {

	if ( ! is_singular() ) {
		return;
	}

	$raw = get_post_meta( get_queried_object_id(), 'simple_seo_seo_robots', true );

	if ( ! $raw ) {
		$raw = 'index,follow';
	}

	$parts          = array_map( 'trim', explode( ',', $raw ) );
	$robots_content = implode( ', ', $parts ) . ', max-image-preview:large, max-snippet:-1, max-video-preview:-1';

	echo '<meta name="robots" content="' . esc_attr( $robots_content ) . "\" />\n";
}

/**
 * Output other SEO meta.
 */
add_action( 'wp_head', 'simple_seo_head_output', 1 );
function simple_seo_head_output() {

	if ( ! is_singular() ) {
		return;
	}

	$id          = get_queried_object_id();
	$seo_title   = simple_seo_custom_title( get_the_title( $id ) );
	$description = get_post_meta( $id, 'simple_seo_seo_description', true ) ?: get_the_excerpt();
	$canonical   = get_post_meta( $id, 'simple_seo_seo_canonical', true ) ?: get_permalink( $id );

	$thumb_id = get_post_thumbnail_id( $id );
	$img_url  = $thumb_id ? wp_get_attachment_url( $thumb_id ) : '';
	$img_meta = $thumb_id ? wp_get_attachment_metadata( $thumb_id ) : [];
	$img_type = $thumb_id ? get_post_mime_type( $thumb_id ) : '';

	$content    = strip_tags( get_post_field( 'post_content', $id ) );
	$word_count = str_word_count( $content );
	$reading    = max( 1, ceil( $word_count / 200 ) ) . ' minutes';

	echo "\n\t<!-- Optimized with Simple SEO -->\n";

	echo "\t<meta name=\"description\" content=\"" . esc_attr( $description ) . "\" />\n";
	echo "\t<link rel=\"canonical\" href=\"" . esc_url( $canonical ) . "\" />\n";

	echo "\t<meta property=\"og:locale\" content=\"" . esc_attr( get_locale() ) . "\" />\n";
	echo "\t<meta property=\"og:type\" content=\"article\" />\n";
	echo "\t<meta property=\"og:title\" content=\"" . esc_attr( $seo_title ) . "\" />\n";
	echo "\t<meta property=\"og:description\" content=\"" . esc_attr( $description ) . "\" />\n";
	echo "\t<meta property=\"og:url\" content=\"" . esc_url( get_permalink( $id ) ) . "\" />\n";
	echo "\t<meta property=\"og:site_name\" content=\"" . esc_attr( get_bloginfo( 'name' ) ) . "\" />\n";

	if ( $img_url ) {

		echo "\t<meta property=\"og:image\" content=\"" . esc_url( $img_url ) . "\" />\n";

		if ( ! empty( $img_meta['width'] ) ) {
			echo "\t<meta property=\"og:image:width\" content=\"" . esc_attr( $img_meta['width'] ) . "\" />\n";
		}

		if ( ! empty( $img_meta['height'] ) ) {
			echo "\t<meta property=\"og:image:height\" content=\"" . esc_attr( $img_meta['height'] ) . "\" />\n";
		}

		if ( $img_type ) {
			echo "\t<meta property=\"og:image:type\" content=\"" . esc_attr( $img_type ) . "\" />\n";
		}
	}

	// Twitter Cards.
	echo "\t<meta name=\"twitter:card\" content=\"" . ( $img_url ? 'summary_large_image' : 'summary' ) . "\" />\n";
	echo "\t<meta name=\"twitter:label1\" content=\"Est. reading time\" />\n";
	echo "\t<meta name=\"twitter:data1\" content=\"" . esc_attr( $reading ) . "\" />\n";

	// JSON-LD.
	$schema = [
		'@context'      => 'https://schema.org',
		'@type'         => 'WebPage',
		'@id'           => get_permalink( $id ),
		'url'           => get_permalink( $id ),
		'name'          => $seo_title,
		'datePublished' => get_the_date( 'c', $id ),
		'dateModified'  => get_the_modified_date( 'c', $id ),
	];

	$json_ld = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	echo "\t<script type=\"application/ld+json\">{$json_ld}</script>\n";
	echo "\t<!-- / Simple SEO -->\n\n";
}

/**
 * Exclude noindex and custom-canonical posts/pages from sitemap.
 */
add_filter( 'wp_sitemaps_posts_query_args', 'simple_seo_sitemap_exclusions', 10, 2 );
function simple_seo_sitemap_exclusions( $args, $post_type ) {

	if ( in_array( $post_type, [ 'post', 'page' ], true ) ) {

		if ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = [];
		}

		// Remove items marked "noindex".
		$args['meta_query'][] = [
			'relation' => 'OR',
			[
				'key'     => 'simple_seo_seo_robots',
				'value'   => 'noindex',
				'compare' => 'NOT LIKE',
			],
			[
				'key'     => 'simple_seo_seo_robots',
				'compare' => 'NOT EXISTS',
			],
		];

		// Remove items with custom canonical URL.
		$args['meta_query'][] = [
			'relation' => 'OR',
			[
				'key'     => 'simple_seo_seo_canonical',
				'value'   => '',
				'compare' => '=',
			],
			[
				'key'     => 'simple_seo_seo_canonical',
				'compare' => 'NOT EXISTS',
			],
		];

		// Remove items that redirect elsewhere.
		$args['meta_query'][] = [
			'relation' => 'OR',
			[
				'key'     => 'simple_seo_seo_redirect',
				'value'   => '',
				'compare' => '=',
			],
			[
				'key'     => 'simple_seo_seo_redirect',
				'compare' => 'NOT EXISTS',
			],
		];
	}

	return $args;
}
