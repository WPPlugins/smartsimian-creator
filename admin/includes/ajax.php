<?php
/**
 * Miscellaneous Ajax admin functions.
 */


/**
 * Ajax callback to display the entire Repeater/Query UI instance on
 * Connection Type pages.
 *
 * @note tried to put this in single-connection.php but it was loading
 * too late for the ajax call to work. Decided it wasn't worth it.
 */
function simian_load_query_ui_repeater() {

	// get user or post types
	$objects = array_map( 'sanitize_key', (array) $_REQUEST['objects'] );

	// get id slug
	$id = sanitize_key( $_REQUEST['id'] );

	$dynamic = isset( $_REQUEST['dynamic'] ) ? (bool) $_REQUEST['dynamic'] : false;

	$include_post_type = false;
	if ( $objects === 'content' )
		$include_post_type = true;

	simian_generate_query_ui( $objects, $id, array(), $include_post_type, $dynamic );

	exit();

}
add_action( 'wp_ajax_simian_query_ui_repeater', 'simian_load_query_ui_repeater' );