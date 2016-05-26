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
	return '<a href="' . get_the_github_view_url() . '">' . apply_filters( 'wpghs_view_link_text', 'View this post on GitHub.' ) . '</a>';
}
add_shortcode( 'get_the_github_view_link', 'get_the_github_view_link' );

/**
 * Returns the URL to view the current post on GitHub.
 *
 * @return string
 */
function get_the_github_view_url() {
	$wpghs_post = new WordPress_GitHub_Sync_Post( get_the_ID(), WordPress_GitHub_Sync::$instance->api() );

	return $wpghs_post->github_view_url();
}
add_shortcode( 'get_the_github_view_url', 'get_the_github_view_url' );

/**
 * Returns the HTML markup to edit the current post on GitHub.
 *
 * @return string
 */
function get_the_github_edit_link() {
	return '<a href="' . get_the_github_edit_url() . '">' . apply_filters( 'wpghs_edit_link_text', 'Edit this post on GitHub.' ) . '</a>';
}
add_shortcode( 'get_the_github_edit_link', 'get_the_github_edit_link' );

/**
 * Returns the URL to edit the current post on GitHub.
 *
 * @return string
 */
function get_the_github_edit_url() {
	$wpghs_post = new WordPress_GitHub_Sync_Post( get_the_ID(), WordPress_GitHub_Sync::$instance->api() );

	return $wpghs_post->github_edit_url();
}
add_shortcode( 'get_the_github_edit_url', 'get_the_github_edit_url' );


/**
 * EXAMPLE FROM https://codex.wordpress.org/Shortcode_API
 * // [bartag foo="foo-value"]
 * function bartag_func( $atts ) {
 *	 $a = shortcode_atts( array(
 *		 'foo' => 'something',
 *		 'bar' => 'something else',
 *	 ), $atts );
 * 
 *	 return "foo = {$a['foo']}";
 * }
 * add_shortcode( 'bartag', 'bartag_func' );
 */

/**
 * Function with attributes
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

	switch( $target ) {
		case 'view': {
			$getter = get_the_github_view_url();
			if( ! empty( $text )) {
				$linktext = $text;
			} else {
				$linktext = 'View this post on GitHub';
			}
			break;
		}
		case 'edit': {
			$getter = get_the_github_edit_url();
			if( ! empty( $text ) ) {
				$linktext = $text;
			} else {
				$linktext = 'Edit this post on GitHub';
			}
			break;
		}
		default: {
			$getter = get_the_github_view_url();
			$linktext = 'View this post on GitHub';
			break;
		}
	}

	switch( $type ) {
		case 'link': {
			$output .= '<a href="' . $getter . '">' . $linktext . '</a>';
			break;
			case 'url': 
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
add_shortcode( 'wpghs', 'write_wpghs_link' );
