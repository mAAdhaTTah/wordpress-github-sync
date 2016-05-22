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
