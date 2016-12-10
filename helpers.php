<?php
/**
 * Theme helper functions.
 *
 * @package WordPress_GitHub_SYnc
 */

/**
 * Returns the HTML markup to view the current post on GitHub.
 *
 * @return string
 */
function get_the_github_view_link() {
	return '<a href="' . get_the_github_view_url() . '">' . apply_filters( 'wpghs_view_link_text', __( 'View this post on GitHub.', 'wp-github-sync' ) ) . '</a>';
}

/**
 * Returns the URL to view the current post on GitHub.
 *
 * @return string
 */
function get_the_github_view_url() {
	$wpghs_post = new WordPress_GitHub_Sync_Post( get_the_ID(), WordPress_GitHub_Sync::$instance->api() );

	return $wpghs_post->github_view_url();
}

/**
 * Returns the HTML markup to edit the current post on GitHub.
 *
 * @return string
 */
function get_the_github_edit_link() {
	return '<a href="' . get_the_github_edit_url() . '">' . apply_filters( 'wpghs_edit_link_text', __( 'Edit this post on GitHub.', 'wp-github-sync' ) ) . '</a>';
}

/**
 * Returns the URL to edit the current post on GitHub.
 *
 * @return string
 */
function get_the_github_edit_url() {
	$wpghs_post = new WordPress_GitHub_Sync_Post( get_the_ID(), WordPress_GitHub_Sync::$instance->api() );

	return $wpghs_post->github_edit_url();
}


/**
 * Common WPGHS function with attributes and shortcode
 *   - type: 'link' (default) to return a HTML anchor tag with text, or 'url' for bare URL.
 *   - target: 'view' (default) or 'edit' to return the respective link/url.
 *   - text: text to be included in the link. Ignored if type='url'.
 *
 * Returns either a HTML formatted anchor tag or the bare URL of the current post on GitHub.
 *
 * @return string
 */
function write_wpghs_link( $atts ) {

	$args = shortcode_atts(
		array(
			'type'   => 'link',
			'target' => 'view',
			'text'   => '',
		),
		$atts
	);
	$type   = esc_attr( $args['type'] );
	$target = esc_attr( $args['target'] );
	$text   = esc_attr( $args['text'] );

	$output = '';

	switch ( $target ) {
		case 'view': {
			$getter = get_the_github_view_url();
			if ( ! empty( $text ) ) {
				$linktext = $text;
			} else {
				$linktext = __( 'View this post on GitHub', 'wp-github-sync' );
			}
			break;
		}
		case 'edit': {
			$getter = get_the_github_edit_url();
			if ( ! empty( $text ) ) {
				$linktext = $text;
			} else {
				$linktext = __( 'Edit this post on GitHub', 'wp-github-sync' );
			}
			break;
		}
		default: {
			$getter = get_the_github_view_url();
			$linktext = __( 'View this post on GitHub', 'wp-github-sync' );
			break;
		}
	}

	switch ( $type ) {
		case 'link': {
			$output .= '<a href="' . $getter . '">' . $linktext . '</a>';
			break;
		}
		case 'url': {
			$output .= $getter;
			break;
		}
		default: {
			$output .= '<a href="' . $getter . '">' . $linktext . '</a>';
			break;
		}
	}

	return $output;

}
