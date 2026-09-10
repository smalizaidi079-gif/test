<?php
/**
 * Plugin Name: Instant Quote Widget
 * Description: Embeds the standalone React instant-quote & booking widget via the [instant_quote_widget] shortcode. Renders inside a Shadow DOM so its styles never clash with the active theme. Prototype/demo — no external APIs required.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 *
 * @package InstantQuoteWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'IQW_VERSION', '1.0.0' );
define( 'IQW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IQW_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Register and enqueue the widget's built JS bundle.
 * The bundle self-mounts into any element with [data-instant-quote-widget]
 * and injects its own CSS into a Shadow DOM, so no separate stylesheet
 * enqueue is required and no theme collision is possible.
 */
function iqw_register_assets() {
	$js_path = IQW_PLUGIN_PATH . 'assets/instant-quote-widget.js';

	wp_register_script(
		'instant-quote-widget',
		IQW_PLUGIN_URL . 'assets/instant-quote-widget.js',
		array(),
		file_exists( $js_path ) ? filemtime( $js_path ) : IQW_VERSION,
		true // load in the footer
	);
}
add_action( 'wp_enqueue_scripts', 'iqw_register_assets' );

/**
 * Shortcode: [instant_quote_widget]
 * Outputs the responsive mount container and enqueues the script only on
 * pages/posts that actually use the shortcode.
 */
function iqw_render_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'max_width' => '640px',
		),
		$atts,
		'instant_quote_widget'
	);

	wp_enqueue_script( 'instant-quote-widget' );

	$max_width = esc_attr( $atts['max_width'] );

	return sprintf(
		'<div class="iqw-container" style="width:100%%;max-width:%s;margin:0 auto;">' .
			'<div data-instant-quote-widget></div>' .
		'</div>',
		$max_width
	);
}
add_shortcode( 'instant_quote_widget', 'iqw_render_shortcode' );

/**
 * Elementor "HTML" widget support: if a user pastes
 *   <div data-instant-quote-widget></div>
 * into an Elementor HTML widget, the script still needs to be on the page.
 * Since Elementor HTML widgets bypass the shortcode enqueue above, we fall
 * back to always loading the script when this plugin is active and the
 * page contains an Elementor-built layout. This keeps footer weight low
 * (a single small script) while guaranteeing the widget mounts correctly
 * regardless of how the container div was inserted.
 */
function iqw_maybe_enqueue_for_elementor() {
	if ( is_admin() ) {
		return;
	}
	global $post;
	if ( $post instanceof WP_Post && get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
		wp_enqueue_script( 'instant-quote-widget' );
	}
}
add_action( 'wp_enqueue_scripts', 'iqw_maybe_enqueue_for_elementor', 20 );
