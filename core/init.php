<?php
/**
 * Components init.
 */
function simian_load_components() {

	// load each active component
	foreach( simian_get_components( $with_info = true ) as $component => $info ) {

		// default args
		$defaults = array(
			'title'       => '',
			'description' => '',
			'singular'    => '',
			'plural'      => '',
			'data'        => false,
			'class'       => false,
			'admin'       => false
		);
		$args = wp_parse_args( $info, $defaults );
		extract( $args );

		// get data if requested
		$data = $data ? simian_get_data( $component ) : false;

		// add admin page if requested
		if ( $admin && is_admin() && SIMIAN_UI ) {
			$admin_page = new Simian_Admin( $component, $args, $data );
			$admin_page->init();
		}

		// instantiate class if requested and if data exists
		$all_objects = array();
		if ( $class && $data ) {
			foreach( $data as $name => $argset ) {
				$object = new $class( $name, $argset );
				$object->init();
				$all_objects[] = $object;
			}
		}

		// action for all components to hook into
		do_action( 'simian_load_' . $component, $data, $class, $admin, $all_objects );

	}

}
add_action( 'init', 'simian_load_components', 1 );