<?php
/**
 * Plugin Name: Simple SEO
 * Plugin URI:  https://briangarder.com/simple-seo/
 * Description: Set custom title, meta description, robots, and canonical URLs for posts and pages, with built-in Open Graph support.
 * Version:     0.5
 * Author URI:  https://briangarder.com/
 * Text Domain: simple-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Remove default robots.
add_filter( 'wp_robots', '__return_empty_array' );
remove_action( 'wp_head', 'wp_robots' );

// Remove default canonical.
remove_action( 'wp_head', 'rel_canonical' );

// Register meta fields.
add_action( 'init', function() {
    $common_args = [
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ];

    // For posts
    register_post_meta( 'post', 'simple_seo_seo_title',       $common_args );
    register_post_meta( 'post', 'simple_seo_seo_description', $common_args );
    register_post_meta( 'post', 'simple_seo_seo_robots',      $common_args );
    register_post_meta( 'post', 'simple_seo_seo_canonical',   $common_args );

    // For pages
    register_post_meta( 'page', 'simple_seo_seo_title',       $common_args );
    register_post_meta( 'page', 'simple_seo_seo_description', $common_args );
    register_post_meta( 'page', 'simple_seo_seo_robots',      $common_args );
    register_post_meta( 'page', 'simple_seo_seo_canonical',   $common_args );
} );

// Override title.
add_filter( 'pre_get_document_title', 'simple_seo_custom_title', 10, 1 );
function simple_seo_custom_title( $title ) {
    if ( is_singular() ) {
        $custom = get_post_meta( get_queried_object_id(), 'simple_seo_seo_title', true );
        return $custom ?: $title;
    }
    return $title;
}

// Only enqueue our sidebar script in the post/page editor.
add_action( 'enqueue_block_editor_assets', function() {
    // bail if get_current_screen() not available or not editing a post/page
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

// Output robots tag.
add_action( 'wp_head', 'simple_seo_robots_output', 1 );
function simple_seo_robots_output() {
    if ( ! is_singular() ) {
        return;
    }

    // Get the saved value or default to "index,follow"
    $raw = get_post_meta( get_queried_object_id(), 'simple_seo_seo_robots', true );
    if ( ! $raw ) {
        $raw = 'index,follow';
    }

    // Normalize and append preview/snippet rules
    $parts          = array_map( 'trim', explode( ',', $raw ) );
    $robots_content = implode( ', ', $parts ) . ', max-image-preview:large, max-snippet:-1, max-video-preview:-1';

    echo '<meta name="robots" content="' . esc_attr( $robots_content ) . "\" />\n";
}

// Output other SEO meta.
add_action( 'wp_head', 'simple_seo_head_output', 1 );
function simple_seo_head_output() {
    if ( ! is_singular() ) {
        return;
    }

    $id          = get_queried_object_id();
    $description = get_post_meta( $id, 'simple_seo_seo_description', true ) ?: get_the_excerpt();
    $canonical   = get_post_meta( $id, 'simple_seo_seo_canonical', true ) ?: get_permalink( $id );

    $thumb_id = get_post_thumbnail_id( $id );
    $img_url  = $thumb_id ? wp_get_attachment_url( $thumb_id ) : '';
    $img_meta = $thumb_id ? wp_get_attachment_metadata( $thumb_id ) : [];
    $img_type = $thumb_id ? get_post_mime_type( $thumb_id ) : '';

    $content    = strip_tags( get_post_field( 'post_content', $id ) );
    $word_count = str_word_count( $content );
    $reading    = max( 1, ceil( $word_count / 200 ) ) . ' minutes';

    echo "\n    <!-- Optimized with Simple SEO -->\n";

    echo '    <meta name="description" content="' . esc_attr( $description ) . "\" />\n";
    echo '    <link rel="canonical" href="' . esc_url( $canonical ) . "\" />\n";

    echo '    <meta property="og:locale" content="' . esc_attr( get_locale() ) . "\" />\n";
    echo '    <meta property="og:type" content="article" />' . "\n";
    echo '    <meta property="og:title" content="' . esc_attr( simple_seo_custom_title( '' ) ) . "\" />\n";
    echo '    <meta property="og:description" content="' . esc_attr( $description ) . "\" />\n";
    echo '    <meta property="og:url" content="' . esc_url( get_permalink( $id ) ) . "\" />\n";
    echo '    <meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . "\" />\n";

    if ( $img_url ) {
        echo '    <meta property="og:image" content="' . esc_url( $img_url ) . "\" />\n";
        if ( ! empty( $img_meta['width'] ) ) {
            echo '    <meta property="og:image:width" content="' . esc_attr( $img_meta['width'] ) . "\" />\n";
        }
        if ( ! empty( $img_meta['height'] ) ) {
            echo '    <meta property="og:image:height" content="' . esc_attr( $img_meta['height'] ) . "\" />\n";
        }
        if ( $img_type ) {
            echo '    <meta property="og:image:type" content="' . esc_attr( $img_type ) . "\" />\n";
        }
    }

    // Output Twitter Cards.
    echo "    <meta name=\"twitter:card\" content=\"" . ( $img_url ? 'summary_large_image' : 'summary' ) . "\" />\n";
    echo "    <meta name=\"twitter:label1\" content=\"Est. reading time\" />\n";
    echo "    <meta name=\"twitter:data1\" content=\"" . esc_attr( $reading ) . "\" />\n";

    // Output JSON-LD.
    $schema = [
        "@context"      => "https://schema.org",
        "@type"         => "WebPage",
        "@id"           => get_permalink( $id ),
        "url"           => get_permalink( $id ),
        "name"          => simple_seo_custom_title( '' ),
        "datePublished" => get_the_date( 'c', $id ),
        "dateModified"  => get_the_modified_date( 'c', $id ),
    ];
    $json_ld = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    echo "    <script type=\"application/ld+json\">{$json_ld}</script>\n";

    echo "    <!-- / Simple SEO -->\n\n";
}

// Exclude noindex and custom‐canonical posts/pages from sitemap.
add_filter( 'wp_sitemaps_posts_query_args', 'simple_seo_sitemap_exclusions', 10, 2 );
function simple_seo_sitemap_exclusions( $args, $post_type ) {
    if ( in_array( $post_type, [ 'post', 'page' ], true ) ) {
        // Remove items marked “noindex”
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

        // Remove items with custom canonical URL
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
    }
    return $args;
}
