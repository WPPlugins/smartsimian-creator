<?php
/**
 * Miscellaneous admin API.
 */

/**
 * Generate a sysname from the given field.
 *
 * Convert spaces and dashes to underscores, then run sanitize_key.
 * If $chars, sysname will be abbreviated to that many characters.
 */
function simian_generate_sysname( $field, $chars = 0 ) {

	$sysname = sanitize_key( str_replace( '-', '_', str_replace( ' ', '_', $field ) ) );

	for( $i = 0; $i < 3; $i++ )
		$sysname = str_replace( '__', '_', $sysname );

	// limit length by $chars amount
	if ( $chars ) {
		$chars = (int) $chars;
		if ( strlen( $sysname ) >= $chars )
			$sysname = substr( $sysname, 0, ( $chars - 1 ) );
	}

	return $sysname;

}


/**
 * Get variable. If not blank, determine if between two numbers. If so, return
 * the variable, cast as an int.
 */
function simian_save_in_range( $var, $min, $max ) {

	if ( $var === '' )
		return false;

	$var = (int) $var;

	if ( $var >= $min && $var <= $max )
		return $var;

	return false;

}


/**
 * Generate single item delete link with nonce.
 */
function simian_single_delete_link( $component = 'content', $label = 'Delete Content Type' ) {
	return isset( $_GET['item'] ) ? '<a class="submitdelete simian-delete-item" href="' . wp_nonce_url( 'admin.php?page=simian-' . $component . '&action=delete&' . $component . '_action=' . sanitize_key( $_GET['item'] ), 'bulk-' . $component ) . '">' . $label . '</a>' : '';
}


/**
 * Create the Simian Repeater instance loaded with a Query UI instance. Used by
 * the Ajax callback (when Narrow Further is selected) and by the query_creator
 * field type (when there are already $values that need to be displayed).
 *
 * @note Tried to stick this in single-connection.php but it wasn't loading in time
 * for the ajax call. Used by ajax.php and admin/fields.php, here is fine.
 */
function simian_generate_query_ui( $objects = array(), $name = '', $values = array(), $include_post_type = false, $dynamic = false ) {

	// get rules
	$rules = array();
	if ( in_array( 'user', (array) $objects ) )
		$rules = array( 'role', 'meta_query', 'search', 'specific_items' );
	else
		$rules = array( 'post_status', 'author', 'published', 'post_parent', 'meta_query', 'tax_query', 'connections', 'specific_items' );

	if ( $include_post_type && !in_array( 'user', $objects ) )
		array_unshift( $rules, 'post_type' );

	$rules = apply_filters( 'simian_query_ui_rules', $rules, $objects, $name );

	// set up query ui
	$query_ui = new Simian_Query_UI( $objects, $rules, $dynamic );

	// reformat values before sending through repeater and to query_ui's row()
	if ( !empty( $values ) )
		$values = Simian_Query_UI::format_values( $values );

	// build new repeater
	$repeater = new Simian_Repeater( array(
		'id'        => $name,
		'sort'      => false,
		'display'   => false,
		'callback'   => array( $query_ui, 'row' ),
		'add_class' => 'button-secondary'
	) );

	$repeater->html( $values, array( 'meta_query', 'tax_query' ) );

}


/**
 * Given an array where a value might reside, find the value.
 */
function simian_get_form_value( $array, $name, $options ) {

	$options = wp_parse_args( $options, array(
		'find' => array(),
		'args' => array()
	) );

	// find a single deep value
	if ( $options['find'] )
		$value = simian_find_value_deep( $array, $options['find'] );

	// get a collection of values
	elseif ( $options['args'] )
		$value = simian_find_value_args( $array, $options['args'] );

	// get a basic value
	else
		$value = isset( $array[$name] ) ? $array[$name] : '';

	return $value;

}


/**
 * Given an  array, and a breadcrumb path of keys, find a value deep within the array.
 *
 * @see admin/includes/fields.php
 */
function simian_find_value_deep( $array, $path ) {

	$path = array_values( $path );
	$depth = count( $path );

	for( $i = 0; $i < $depth; $i++ ) {

		if ( isset( $array[$path[$i]] ) ) {

			$array = $array[$path[$i]];

		} else {

			$array = '';
			break;

		}

	}

	return $array;

}


/**
 * Given an array and a set of args, grab all the args from that array.
 *
 * @see admin/includes/fields.php
 */
function simian_find_value_args( $array, $args ) {

	$values = array();
	foreach( $args as $key => $placeholder ) {
		$values[$key] = isset( $array[$key] ) ? $array[$key] : '';
	}

	foreach( $values as $key => $value ) {
		if ( !$value && !is_array( $value ) )
			unset( $values[$key] );
	}

	if ( empty( $values ) )
		return '';
	else
		return $values;

}


/**
 * Display a tooltip.
 */
function simian_tooltip( $text, $echo = true ) {
	$output = '<div class="dashicons dashicons-editor-help simian-tooltip" title="' . esc_attr( $text ) . '"></div>';
	if ( $echo )
		echo $output;
	else
		return $output;
}


/**
 * Determine connect_to, connect_to_type, connection_type. Used in field UIs.
 */
function simian_determine_connection_values( $value ) {

	// only connection_type will actually pass a value here
	$value = wp_parse_args( $value, array(
		'connect_to_type' => '', // 'content' or 'user'
		'connect_to'      => '', // content types
		'connection_type' => ''  // connection type
	) );

	// need to backwards-engineer the "Connect to" values here
	if ( $value['connection_type'] ) {

		$connection_type = simian_get_item( $value['connection_type'], 'connection' );

		if ( !$connection_type ) {
			echo '<div class="howto"><em>This field uses a connection type that no longer exists. It may have been deleted. You may add a new one below.</em></div><br />';
			$value['connection_type'] = '';

		} else {

			$value['connect_to_type'] = simian_is_user_connection( $value['connection_type'] ) ? 'user' : 'content';

			$field_group = simian_get_item( isset( $_GET['item'] ) ? sanitize_key( $_GET['item'] ) : '', 'fieldset' );
			$field_group = wp_parse_args( $field_group, array(
				'content_types' => array()
			) );

			$connection_type = simian_get_item( $value['connection_type'], 'connection' );
			$connection_type = wp_parse_args( $connection_type, array(
				'to'   => array(),
				'from' => array()
			) );

			if ( (array) $connection_type['to'] === (array) $field_group['content_types'] )
				$value['connect_to'] = $connection_type['from'];
			else // elseif ( (array) $connection_type['from'] === (array) $field_group['content_types'] )
				$value['connect_to'] = $connection_type['to'];

		}

	}

	return $value;

}


/**
 * Get default orderby values for posts or users.
 */
function simian_get_orderby( $type = 'post', $include_meta = false ) {
	if ( $type === 'user' )
		$orderby = array(
			'login'        => 'Username',
			'display_name' => 'Display name',
			'email'        => 'Email Address',
			'registered'   => 'Date Registered',
			'post_count'   => 'Authored Posts',
			'ID'           => 'ID (order added)'
		);
	else
		$orderby = array(
			'title'      => 'Title',
			'date'       => 'Date Published',
			'modified'   => 'Date Modified',
			'menu_order' => 'Menu Order',
			'author'     => 'Author',
			'rand'       => 'Random',
			'name'       => 'Slug',
			'ID'         => 'ID (order added)'
		);

	if ( $type !== 'user' && $include_meta )
		$orderby['meta_value'] = 'Custom field';

	return $orderby;
}