<?php
/**
 * Miscellaneous API functions.
 *
 * Extensions, licenses, components, data, items, fields.
 */


/**
 * Register an extension.
 */
function simian_register_extension( $args ) {

	$args = wp_parse_args( $args, array(
		'name'    => '',
		'slug'    => '',
		'version' => '',
		'file'    => '',
		'author'  => ''
	) );

	// all args are required
	if ( $args !== array_filter( $args ) )
		return;

	$args['revision'] = simian_get_revision( $args['file'] );

	global $simian_extensions;
	$simian_extensions[] = $args;

}


/**
 * Get the current svn revision.
 *
 * Props Sean.
 */
function simian_get_revision( $file ) {

	if ( !SIMIAN_SHOW_REVISION )
		return;

	$dir = dirname( $file );

	// Priority 1: Check for .revision File
    $val = $dir . '/.revision';
    if( file_exists( $val ) ) { return ' r' . implode( '', file( $val ) ); }

    // Priority 2: Run Shell Command
    if( $val = shell_exec( 'svnversion ' . $dir ) ) { return ' r' . $val; }

    // Priority 3: Unavailable
    return '';

}


/**
 * Get extensions.
 */
function simian_get_extensions() {
	global $simian_extensions;
	return $simian_extensions;
}


/**
 * Get extensions plus Creator.
 */
function simian_get_plugins( $include_base = true ) {

	global $simian_extensions;

	$plugins = $simian_extensions;

	if ( $include_base )
		array_unshift( $plugins, array(
			'name'    => SIMIAN_NAME,
			'slug'    => SIMIAN_SLUG,
			'version' => SIMIAN_VERSION,
			'file'    => SIMIAN_FILE,
			'author'  => SIMIAN_AUTHOR
		) );

	return $plugins;

}


/**
 * Check if any extensions exist.
 */
function simian_has_extensions() {
	return ( simian_get_plugins( $include_base = false ) ) ? true : false;
}


/**
 * Get info for a single SmartSimian plugin by slug.
 */
function simian_get_plugin( $slug ) {

	$plugins = simian_get_plugins();

	foreach( $plugins as $plugin ) {
		if ( $plugin['slug'] == $slug )
			return $plugin;
	}

	return false;

}


/**
 * Check licenses. Really a wrapper that checks and updates a transient.
 *
 * @return array for each plugin.
 */
function simian_check_licenses( $force = false ) {

	$status = get_transient( 'simian_licenses_status' );

	// if expired, or desired, grab fresh
	$new_status = false;
	if ( $status === false || $force )
		$new_status = simian_check_licenses_remote();

	// if new value, save for a day
	if ( $new_status !== false && $new_status !== $status ) {
		$status = $new_status;
		set_transient( 'simian_licenses_status', $status, 86400 );
	}

	return $status;

}


/**
 * Check a single license according to the plugin slug.
 */
function simian_check_single_license( $name ) {

	$licenses = simian_check_licenses();

	if ( isset( $licenses[$name] ) )
		return $licenses[$name];

	return false;

}


/**
 * Check licenses the hard way.
 *
 * @return array for each plugin.
 */
function simian_check_licenses_remote() {

	$status = array();

	foreach( simian_get_plugins( $include_base = false ) as $ext ) {

		$licenses = get_option( 'simian_licenses' );
		$license = trim( isset( $licenses[$ext['slug']] ) ? $licenses[$ext['slug']] : '' );

		$result = simian_check_single_license_remote( $ext['name'], $license );

		// store in slug/status pairs
		$status[$ext['slug']] = $result;

	}

	return $status;

}


/**
 * Check an individual license remotely.
 *
 * @return string - valid, invalid, error message.
 */
function simian_check_single_license_remote( $name, $license ) {

	$api_params = array(
		'edd_action' => 'check_license',
		'license'    => $license,
		'item_name'  => urlencode( $name )
	);

	$response = wp_remote_get( add_query_arg( $api_params, SIMIAN_STORE_URL ), array(
		'timeout' => 15,
		'sslverify' => false
	) );

	if ( is_wp_error( $response ) )
		return $response->get_error_message();

	$license_data = json_decode( wp_remote_retrieve_body( $response ) );

	if ( !isset( $license_data->license ) )
		return 'No license definition found.';

	// should be valid or invalid
	return $license_data->license;

}


/**
 * Conditional - check licenses and return true if all are valid, false
 * if anything else happened.
 */
function simian_are_licenses_valid() {
	$licenses = simian_check_licenses();
	foreach( $licenses as $status ) {
		if ( $status !== 'valid' ) {
			return false;
		}
	}
	return true;
}


/**
 * Flush the license status transient.
 */
function simian_flush_licenses_status() {
	delete_transient( 'simian_licenses_status' );
}


/**
 * Get all components.
 */
function simian_get_components( $with_info = false, $just_defaults = false ) {

	global $simian_components;
	$components = $simian_components;

	if ( $just_defaults ) {
		global $simian_default_components;
		$components = $simian_default_components;
	}

	if ( $with_info )
		return $components;

	return array_keys( $components );

}


/**
 * Get default components.
 */
function simian_get_default_components() {
	return simian_get_components( $with_info = false, $just_defaults = true );
}


/**
 * Check to see if a component currently exists.
 */
function simian_component_exists( $component ) {
	return in_array( sanitize_key( $component ), simian_get_components() );
}


/**
 * Get a specific component.
 */
function simian_get_component( $component ) {

	$all = simian_get_components( $with_info = true );

	if ( isset( $all[$component] ) )
		return $all[$component];

	return false;

}


/**
 * Add a component.
 */
function simian_add_component( $component, $args = array() ) {
	global $simian_components;
	if ( isset( $simian_components[$component] ) )
		return;
	$simian_components[$component] = $args;
}


/**
 * Show a human-readable list of installed components that include db data.
 */
function simian_friendly_component_list() {
	global $simian_components;
	$components = array();
	foreach( $simian_components as $component ) {
		$has_data = isset( $component['data'] ) ? $component['data'] : false;
		if ( $has_data )
			$components[] = $component['plural'];
	}
	$last = array_pop( $components );
	$components[] = 'and ' . $last;
	return implode( ', ', $components );
}


/**
 * Show a human-readable list of import results.
 *
 * @param $type string 'imported' or 'skipped'.
 */
function simian_friendly_import_results( $type = 'imported' ) {

	global $simian_import_results;
	if ( !array_filter( $simian_import_results ) )
		return;

	$output = array();

	foreach( $simian_import_results[$type] as $component => $items ) {
		if ( $component == 'Content' ) $component = 'Content Types';

		if ( count( $items ) )
			$output[] = '<strong>' . count( $items ) . ' ' . $component . '</strong>';

	}

	if ( count( $output ) > 1 ) {
		$last = array_pop( $output );
		$output[] = 'and ' . $last;
	}

	return implode( ', ', $output );

}


/**
 * Pull component data from the database or a manual function.
 */
function simian_get_data( $component = '', $full = true ) {

	// Get configuration data from database
	$data = get_option( 'simian_' . $component );

	if ( !$data )
		$data = array();

	// @deprecated back-compat for taxonomies
	if ( $component == 'taxonomy' )
		$data = apply_filters( 'simian_list_data', $data );

	// Include data added through filters
	$data = apply_filters( 'simian_' . $component . '_data', $data );

	// only return array keys if set to false
	if ( !$full )
		return array_keys( $data );

	return $data;

}


/**
 * Pull component data in name/label pairs, useful for dropdown menus, etc.
 */
function simian_list_data( $component, $args ) {

	$args = wp_parse_args( $args, array(
		'empty_option' => 'Select'
	) );

	$list = array();

	if ( $args['empty_option'] )
		$list[] = sanitize_text_field( $args['empty_options'] );

	$data = simian_get_data( $component );

	foreach( $data as $name => $array ) {

		extract( wp_parse_args( $array, array(
			'plural_label' => '',
			'label' => ''
		) ) );

		// use plural label, then label, then name
		$use_label = $plural_label ? $plural_label : ( $label ? $label : $name );

		$list[$name] = $use_label;

	}

	return $list;

}


/**
 * Get one specific Simian item.
 */
function simian_get_item( $item, $component ) {
	$data = simian_get_data( $component );
	return isset( $data[$item] ) ? $data[$item] : false;
}


/**
 * Check if the given item has the current value, and returns it if so.
 * Empty, false, and 0 values will return false.
 *
 * @param $value The key of the item being checked (i.e. 'singular_label').
 * @param $component The current component.
 * @param $item The current item. If empty, will grab from $_GET.
 *
 * $value is the key of the item (i.e. 'singular_label').
 * $item accepts string or array.  If string, $component is required.
 */
function simian_item_has_value( $value, $item = '', $component ) {

	$post_value = isset( $_POST[$value] ) ? $_POST[$value] : '';
	if ( $post_value !== '' )
		return true;

	if ( !$item )
		$item = isset( $_GET['item'] ) ? sanitize_key( $_GET['item'] ) : '';

	$item = simian_get_item( $item, $component );

	if ( !$item )
		return false;

	$value = isset( $item[$value] ) ? $item[$value] : '';
	if ( $value )
		return $value;

	return false;

}


/**
 * Add one specific Simian item. $args are expected to already be sanitized.
 *
 * If $force_name = false, then the function will check for conflicts and ensure
 * the new sysname to be unique.
 *
 * If $force_name = true, then if a conflict exists, the new item will either
 * overwrite it (if update = true) or fail.
 *
 * @param $component string The current component.
 * @param $args array The db-ready arguments for the new item.
 * @param $update bool Whether or not to overwrite an existing item. If this is
 * true, an existing item *must* exist or an error will be returned.
 *
 * @return The new or updated item name, or an array with an error message. It's
 * possible for the item name to be changed with make_unique, so this will show
 * you what the final name of the item is.
 */
function simian_add_item( $component, $args = array(), $update = false, $force_name = false ) {

	$component = sanitize_key( $component );

	// check for args full of empties
	if ( !array_filter( $args ) )
		return array( '_errors' => 'No information has been submitted.' );

	// check if sysname has been set yet
	$args['sysname'] = isset( $args['sysname'] ) ? $args['sysname'] : '';

	// if no sysname, create one if possible
	if ( !$args['sysname'] ) {

		// generate sysname from singular label or label
		$label = isset( $args['singular_label'] ) ? $args['singular_label'] : '';
		if ( !$label )
			$label = isset( $args['label'] ) ? $args['label'] : '';

		if ( $label )
			$args['sysname'] = simian_generate_sysname( $label );

		// if still no sysname, return error
		if ( !$args['sysname'] )
			return array( '_errors' => 'Please enter a name.' );

	}

	// if $force_name, must use this exact sysname or fail
	if ( $force_name ) {

		// check if exists
		$exists = simian_get_item( $args['sysname'], $component );

		if ( $exists && !$update )
			return array( '_errors' => 'An item with this name already exists.' );

		// check if name can remain the same
		$new_sysname = Simian_Admin_Save::make_unique( $args['sysname'], $component );

		if ( $args['sysname'] != $new_sysname )
			return array( '_errors' => 'The chosen system name is not allowed.' );

	}

	if ( !$update ) {

		// ensure sysname is unique, set $item
		$item = $args['sysname'] = Simian_Admin_Save::make_unique( $args['sysname'], $component );

	} else {
		$item = $args['sysname'];
	}

	// retrieve existing data
	$option = get_option( 'simian_' . $component );

	// if item exists
	if ( isset( $option[$item] ) ) {

		// if overwriting is not allowed
		if ( !$update )
			return array( '_errors' => 'Item already exists.' );

		// save overwritten args in transient
		set_transient( 'simian_' . $component . '_' . $item, $option[$item], 24*360 );

	// if item does not exist
	} else {

		// if $update is set to true, return error
		if ( $update )
			return array( '_errors' => 'The item you are attempting to update could not be found.' );

	}

	// update option array with args
	$option[$item] = $args;

	// update option in db
	update_option( 'simian_' . $component, $option );

	do_action( 'simian_save_item', $item, $args, $component );

	return $item;

}


/**
 * Update one specific Simian item. This fully overwrites the item with the
 * given args, so any args you want to keep the same need to be included.
 *
 * Mainly this just wraps simian_add_item, but will auto-set the sysname
 * since we're given the item name.
 *
 * @param $item string The item name to be updated.
 * @param $component string The current component.
 * @param $args array The db-ready arguments for the new item.
 */
function simian_update_item( $item, $component, $args ) {

	$item = sanitize_key( $item );

	// if no item set, return error
	if ( !$item )
		return array( '_errors' => 'No item specified.' );

	// set sysname as $item no matter what.
	// sysnames are only editable when adding a new item.
	$args['sysname'] = $item;

	// update item (will return item name or error array)
	return simian_add_item( $component, $args, true );

}


/**
 * Delete one specific Simian item.
 *
 * @param $item string The item name to delete.
 * @param $component string The component name.
 *
 * @return False if item is not found to delete, else it returns the
 * result of update_option (true or false).
 */
function simian_delete_item( $item, $component ) {

	$item = sanitize_key( $item );
	$component = sanitize_key( $component );

	// run an action before the options are grabbed
	do_action( 'simian_before_delete_item', $item, $component );

	// get all stored items
	$existing = get_option( 'simian_' . $component, array() );

	// if invalid item, fail
	if ( !isset( $existing[$item] ) )
		return false;

	// keep backup for one day (literally)
	set_transient( 'simian_' . $component . '_' . $item, $existing[$item], 24*360 );

	// remove from existing array
	unset( $existing[$item] );

	// attempt update and return true or false
	update_option( 'simian_' . $component, $existing );

	do_action( 'simian_after_delete_item', $item, $component );

	return true;

}


/**
 * Restore an item that's been saved in a transient.
 */
function simian_restore_item( $item, $component ) {

	$item = sanitize_key( $item );
	$component = sanitize_key( $component );

	do_action( 'simian_before_restore_item', $item, $component );

	// retrieve live data
	$data = get_option( 'simian_' . $component, array() );

	// if item exists
	if ( isset( $data[$item] ) )
		return false;

	// retrieve backup
	$backup = get_transient( 'simian_' . $component . '_' . $item );

	// if none found
	if ( !$backup )
		return false;

	// restore item
	$data[$item] = $backup;

	// re-save data
	update_option( 'simian_' . $component, $data );

	do_action( 'simian_after_restore_item', $item, $component );

	return true;

}


/**
 * Get all content types in sysname => label pairs.
 *
 * @return sysname => args array.
 */
function simian_get_content_types( $args = array() ) {

	extract( wp_parse_args( $args, array(
		'include_user' => false,
		'custom_only'  => false
	) ) );

	$return = array();

	$custom_types = simian_get_data( 'content', false );

	$all_types = get_post_types( array(), 'objects' );
	foreach( $all_types as $type ) {

		if ( $custom_only && $in_array( $type->name, $custom_types ) )
			continue;

		$return[$type->name] = $type->label;

	}

	if ( $include_user )
		$return['user'] = 'Users';

	return $return;

}


/**
 * Get all fields associated with the given content type. Accepts
 * content type name or data array.
 */
function simian_get_fields( $content_type = '', $meta_only = false, $just_labels = false ) {

	if ( !$content_type )
		return array();

	if ( !is_string( $content_type ) )
		return array();

	// store all fields here
	$fields = array();

	// get all fieldsets
	$fieldsets = simian_get_data( 'fieldset' );

	// if user, add default meta fields to top
	if ( $content_type == 'user' ) {
		$fields = simian_get_user_default_fields();
	}

	if ( $fieldsets ) {
		foreach( $fieldsets as $fieldset ) {

			// if content type is used by this fieldset, add fields to array
			if ( in_array( $content_type, $fieldset['content_types'] ) ) {
				$fields = array_merge( $fields, $fieldset['fields'] );
			}

		}
	}

	// remove non-meta
	if ( $meta_only ) {
		foreach( $fields as $key => $field ) {
			if ( in_array( $field['type'], array( 'taxonomy', 'connection', 'instructions' ) ) )
				unset( $fields[$key] );
		}
	}

	if ( $just_labels ) {
		$new = array();
		foreach( $fields as $key => $field ) {
			$field = wp_parse_args( $field, array(
				'name'  => '',
				'label' => ''
			) );
			$new[$field['name']] = $field['label'] ? esc_attr( stripslashes( $field['label'] ) ) : $field['name'];
		}
		$fields = $new;
	}

	// return all added fields
	return $fields;

}


/**
 * Get default meta fields associated with users.
 */
function simian_get_user_default_fields() {
	return array(
		array(
			'name' => 'first_name',
			'label' => 'First Name',
			'type' => 'text'
		),
		array(
			'name' => 'last_name',
			'label' => 'Last Name',
			'type' => 'text'
		),
		array(
			'name' => 'nickname',
			'label' => 'Nickname',
			'type' => 'text'
		),
		array(
			'name' => 'description',
			'label' => 'Biographical Info',
			'type' => 'longtext'
		)
	);
}


/**
 * List all custom fields grouped by fieldset.
 */
function simian_list_grouped_fields( $empty_option = 'Select field' ) {

	// get all meta fields
	$fieldsets = simian_get_data( 'fieldset' );
	$fields = array();

	if ( $empty_option )
		$fields[] = sanitize_text_field( $empty_option );

	foreach( $fieldsets as $name => $array ) {
		$label = esc_attr( $array['label'] ? $array['label'] : $fs_name );
		$this_fields = array();
		foreach( $array['fields'] as $field ) {
			$this_fields[$field['name']] = $field['label'];
		}
		$fields[$label] = $this_fields;
	}

	return $fields;

}


/**
 * Get one specific Simian field array when given the field name, item,
 * and component.
 */
function simian_get_field( $name, $item, $component = 'content' ) {

	$fields = simian_get_fields( $item );

	foreach( $fields as $field ) {
		if ( $field['name'] == $name ) {
			return $field;
		}
	}

	return false;

}


/**
 * Get a universal Simian option stored in the 'simian_options' option.
 */
function simian_get_option( $option = '', $default = false ) {

	if ( !$option )
		return $default;

	$all = get_option( 'simian_options' );

	if ( !isset( $all[$option] ) )
		return $default;

	return $all[$option];

}


/**
 * Filter a block of content.
 *
 * Replaces the removed simian_apply_default_content_filters and simian_apply_default_excerpt_filters.
 *
 * @param $content	The content being filtered.
 * @param $autop	Auto-add paragraphs.
 * @param $rich		Run additonal texture/convert functions for rich text.
 */
function simian_run_html_filters( $content, $autop = false, $rich = false, $add_attachment = false ) {

	// first ensure embeds are working
	global $wp_embed;
	$content = $wp_embed->autoembed( $content );

	// clean up rich text
	if ( $rich ) {
		$content = wptexturize( $content );
		$content = convert_smilies( $content );
		$content = convert_chars( $content );
	}

	// add paragraph tags
	if ( $autop ) {
		$content = wpautop( $content );
		$content = shortcode_unautop( $content );
	}

	// show attachment if this is an attachment
	if ( $add_attachment )
		$content = prepend_attachment( $content );

	return $content;

}


/**
 * Get all connection type p2p objects.
 *
 * @param $format 'names', 'labels', 'objects', or 'args'.
 * @return array.
 */
function simian_get_connection_types( $format = 'names' ) {

	if ( $format == 'objects' )
		return P2P_Connection_Type_Factory::get_all_instances();

	$types = simian_get_data( 'connection' );

	if ( $format == 'names' )
		return array_keys( $types );

	if ( $format == 'labels' ) {
		$pairs = array();
		foreach( $types as $type => $args ) {
			$args = wp_parse_args( $args, array( 'label' => '' ) );
			$pairs[$type] = $args['label'] ? $args['label'] : $type;
		}
		return $pairs;
	}

	// $format = 'args' is all that's left
	return $types;

}


/**
 * Return all connection types associated with the passed content type(s).
 *
 * @param $object_types string or array of post types.
 * @param $return_type string 'names', 'labels', 'objects', or 'args'.
 * @return array.
 */
function simian_get_object_connection_types( $object_types = array(), $return_type = 'names' ) {

	$return = array();

	foreach ( simian_get_connection_types( $return_type ) as $name => $data ) {

		if ( $return_type == 'names' )
			$name = $data;

		// get all object types for this connection
		$content_types = simian_get_connection_object_types( $name, true );

		// if there are any object types that are associated with this connection
		if ( array_intersect( (array) $object_types, $content_types ) ) {
			if ( $return_type === 'names' )
				$return[] = $name;
			else
				$return[$name] = $data;
		}

	}

	return $return;

}


/**
 * Get an array of all post types involved in a given connection type. This
 * function does not handle users.
 *
 * @param $conn string|array The name or p2p object of the connection type.
 * @return An array of post types.
 */
function simian_get_connection_post_types( $conn ) {

	if ( is_string( $conn ) )
		$conn = p2p_type( $conn );

	return array_merge(
		isset( $conn->side['from']->query_vars['post_type'] ) ? (array) $conn->side['from']->query_vars['post_type'] : array(),
		isset( $conn->side['to']->query_vars['post_type'] )   ? (array) $conn->side['to']->query_vars['post_type']   : array()
	);

}


/**
 * Get an array of all objects involved in a given connection type. This is a better
 * version of simian_get_connection_post_types and supports users.
 */
function simian_get_connection_object_types( $connection_type, $remove_duplicates = false ) {

	if ( !$connection_type )
		return false;

	if ( is_object( $connection_type ) )
		$connection_type = $connection_type->sysname;

	$connection_type = simian_get_item( $connection_type, 'connection' );
	$connection_type = wp_parse_args( $connection_type, array(
		'to' => array(),
		'from' => array()
	) );

	$object_types = array_merge( (array) $connection_type['to'], (array) $connection_type['from'] );

	if ( $remove_duplicates )
		return array_unique( $object_types );

	return $object_types;

}


/**
 * Return all connection types involving users in name => label pairs.
 */
function simian_get_user_connection_types() {

	$return = array();

	$ctypes = simian_get_connection_types( 'objects' );
	foreach( $ctypes as $name => $ctype ) {
		if ( simian_is_user_connection( $ctype ) ) {
			$return[$name] = isset( $ctype->label ) ? $ctype->label : $name;
		}
	}

	return $return;

}


/**
 * Given a post type or 'user', return the opposite side of the given
 * connection type.
 *
 * If indeterminate connection, that is the passed content type
 * appears on both sides, show all other object types from both sides.
 */
function simian_get_connection_opposite( $connection_type = '', $content_type = '' ) {

	if ( !$connection_type || !$content_type )
		return false;

	if ( is_object( $connection_type ) )
		$connection_type = $connection_type->sysname;

	$args = simian_get_item( $connection_type, 'connection' );
	$args = wp_parse_args( $args, array(
		'from' => array(),
		'to'   => array()
	) );
	$args['from'] = (array) $args['from'];
	$args['to']   = (array) $args['to'];

	if ( in_array( $content_type, $args['to'] ) && in_array( $content_type, $args['from'] ) )
		$opposite = array_merge( $args['to'], $args['from'] );

	elseif ( in_array( $content_type, $args['to'] ) )
		$opposite = $args['from'];

	elseif ( in_array( $content_type, $args['from'] ) )
		$opposite = $args['to'];

	$opposite = array_unique( $opposite );

	return $opposite;

}


/**
 * Return the object type (only 'post' or 'user') of a given connection type side.
 */
function simian_get_connection_side( $p2p_type, $side ) {

	if ( is_string( $p2p_type ) )
		$p2p_type = p2p_type( $p2p_type );

	if ( !is_object( $p2p_type ) || ( $side !== 'to' && $side !== 'from' ) )
		return false;

	return $p2p_type->side[$side]->get_object_type();

}


/**
 * Check if the current connection type is indeterminate; that is, if one or
 * more post types (or 'user') appear on each side of the connection.
 *
 * @param $connection_type Accepts name, args array, or p2p object.
 * @return true or false.
 */
function simian_connection_is_indeterminate( $connection_type = '' ) {

	if ( is_object( $connection_type ) )
		$connection_type = simian_get_item( $connection_type->sysname, 'connection' );

	elseif( is_string( $connection_type ) )
		$connection_type = simian_get_item( $connection_type, 'connection' );

	$connection_type = wp_parse_args( $connection_type, array(
		'to'   => array(),
		'from' => array()
	) );

	$duplicate = array_intersect( (array) $connection_type['to'], (array) $connection_type['from'] );

	return !empty( $duplicate );

}


/**
 * Check whether either side of a connection includes the passed object.
 */
function simian_is_object_connection( $object_type = '', $p2p_type = '' ) {
	$objects = array();
	foreach( array( 'from', 'to' ) as $side ) {
		$objects[] = simian_get_connection_side( $p2p_type, $side );
	}
	return ( in_array( $object_type, $objects ) );
}


/**
 * Check whether either side of a connection is the user object.
 */
function simian_is_user_connection( $p2p_type ) {
	return simian_is_object_connection( 'user', $p2p_type );
}


/**
 * Check whether either side of a connection is a post type. So, this
 * returns true unless both sides are 'user'.
 */
function simian_is_post_connection( $p2p_type ) {
	return simian_is_object_connection( 'post', $p2p_type );
}


/**
 * Check whether either side of a connection contains any or all of the passed
 * object types (post types or 'user').
 *
 * @param $connection_type string The name of the connection type.
 * @param $object_type string|array One or more post types or 'user'.
 * @param $match string Whether the connection type needs to contain 'any' or 'all' of $object_type.
 */
function simian_connection_has_object_type( $connection_type = '', $object_type = array(), $match = 'all' ) {

	if ( !$connection_type || !$object_type )
		return;

	$type = simian_get_item( $connection_type, 'connection' );
	$type = wp_parse_args( $type, array(
		'to'   => array(),
		'from' => array()
	) );

	$from      = (array) $type['from'];
	$to        = (array) $type['to'];
	$object_type = (array) $object_type;

	if ( $match === 'any' ) {
		if ( array_intersect( $object_type, $to ) || array_intersect( $object_type, $from ) )
			return true;
	}

	if ( $match === 'all' ) {
		if (
		   ( count( $object_type ) === count( array_intersect( $object_type, $to ) ) ) ||
		   ( count( $object_type ) === count( array_intersect( $object_type, $from ) ) )
		   ) return true;
	}

	return false;

}


/**
 * Return name => label pairs for connection types.
 */
function simian_list_connection_types( $top_option = array() ) {

	$list = array();
	foreach( simian_get_data( 'connection' ) as $name => $args ) {
		$args = wp_parse_args( $args, array(
			'label'   => '',
			'sysname' => ''
		) );
		$list[$name] = $args['label'] ? $args['label'] : $args['sysname'];
	}
	asort( $list );

	if ( $top_option )
		$list = array_merge( $top_option, $list );

	return $list;

}


/**
 * If a connection type is being used as a field, we want the field's label,
 * not the connection type's label.  Provide field array to this function,
 * spit out appropriate label if it exists.
 */
function simian_get_connection_label_from_field( $connection_type = '', $fields = array() ) {
	foreach( $fields as $field ) {
		$field = wp_parse_args( $field, array( 'label' => '', 'options' => array() ) );
		$field['options'] = wp_parse_args( $field['options'], array( 'connection_type' => '' ) );
		if ( $connection_type === $field['options']['connection_type'] )
			return $field['label'];
	}
	return '';
}


/**
 * Get all available admin menu icons. Simple hook to add more. Used by at least fields.php and content.php.
 */
function simian_get_content_type_icons( $force_old = false ) {

	if ( simian_is_using_dashicons() && !$force_old )
		return array(
			'f333' => 'menu',
			'f319' => 'site',
			'f226' => 'gauge',
			'f102' => 'admin-dashboard',
			'f109' => 'admin-post',
			'f104' => 'admin-media',
			'f103' => 'admin-links',
			'f105' => 'admin-page',
			'f101' => 'admin-comments',
			'f100' => 'admin-appearance',
			'f106' => 'admin-plugins',
			'f110' => 'admin-users',
			'f107' => 'admin-tools',
			'f108' => 'admin-settings',
			'f112' => 'admin-site',
			'f111' => 'admin-generic',
			'f148' => 'admin-collapse',
			'f119' => 'welcome-write-blog',
			'f133' => 'welcome-add-page',
			'f115' => 'welcome-view-site',
			'f116' => 'welcome-widgets-menus',
			'f117' => 'welcome-comments',
			'f118' => 'welcome-learn-more',
			'f123' => 'format-aside',
			'f128' => 'format-image',
			'f161' => 'format-gallery',
			'f126' => 'format-video',
			'f130' => 'format-status',
			'f122' => 'format-quote',
			'f125' => 'format-chat',
			'f127' => 'format-audio',
			'f306' => 'camera2',
			'f232' => 'images-alt1',
			'f233' => 'images-alt2',
			'f234' => 'video-alt1',
			'f235' => 'video-alt2',
			'f236' => 'video-alt3',
			'f165' => 'imgedit-crop',
			'f166' => 'imgedit-rleft',
			'f167' => 'imgedit-rright',
			'f168' => 'imgedit-flipv',
			'f169' => 'imgedit-fliph',
			'f171' => 'imgedit-undo',
			'f172' => 'imgedit-redo',
			'f135' => 'align-left',
			'f136' => 'align-right',
			'f134' => 'align-center',
			'f138' => 'align-none',
			'f160' => 'lock',
			'f145' => 'calendar',
			'f177' => 'visibility',
			'f173' => 'post-status',
			'f327' => 'edit',
			'f200' => 'editor-bold',
			'f201' => 'editor-italic',
			'f203' => 'editor-ul',
			'f204' => 'editor-ol',
			'f205' => 'editor-quote',
			'f206' => 'editor-alignleft',
			'f207' => 'editor-aligncenter',
			'f208' => 'editor-alignright',
			'f209' => 'editor-insertmore',
			'f210' => 'editor-spellcheck',
			'f211' => 'editor-distractionfree',
			'f212' => 'editor-kitchensink',
			'f213' => 'editor-underline',
			'f214' => 'editor-justify',
			'f215' => 'editor-textcolor',
			'f216' => 'editor-word',
			'f217' => 'editor-plaintext',
			'f218' => 'editor-removeformatting',
			'f219' => 'editor-video',
			'f220' => 'editor-customchar',
			'f221' => 'editor-outdent',
			'f222' => 'editor-indent',
			'f223' => 'editor-help',
			'f224' => 'editor-strikethrough',
			'f225' => 'editor-unlink',
			'f320' => 'editor-rtl',
			'f142' => 'arr-up',
			'f140' => 'arr-down',
			'f139' => 'arr-right',
			'f141' => 'arr-left',
			'f342' => 'arr-alt1-up',
			'f346' => 'arr-alt1-down',
			'f344' => 'arr-alt1-right',
			'f340' => 'arr-alt1-left',
			'f343' => 'arr-alt2-up',
			'f347' => 'arr-alt2-down',
			'f345' => 'arr-alt2-right',
			'f341' => 'arr-alt2-left',
			'f156' => 'sort',
			'f229' => 'leftright',
			'f163' => 'list-view',
			'f164' => 'exerpt-view',
			'f237' => 'share',
			'f240' => 'share2',
			'f242' => 'share3',
			'f301' => 'twitter1',
			'f302' => 'twitter2',
			'f303' => 'rss',
			'f304' => 'facebook1',
			'f305' => 'facebook2',
			'f325' => 'network',
			'f308' => 'jobs-developers',
			'f309' => 'jobs-designers',
			'f310' => 'jobs-migration',
			'f311' => 'jobs-performance',
			'f120' => 'wordpress',
			'f324' => 'wordpress-single-ring',
			'f157' => 'pressthis',
			'f113' => 'update',
			'f180' => 'screenoptions',
			'f348' => 'info',
			'f174' => 'cart',
			'f175' => 'feedback',
			'f176' => 'cloud',
			'f326' => 'translation',
			'f323' => 'tag',
			'f318' => 'category',
			'f147' => 'yes',
			'f158' => 'no',
			'f335' => 'no-alt',
			'f132' => 'plus-small',
			'f153' => 'xit',
			'f159' => 'marker',
			'f155' => 'star-filled',
			'f154' => 'star-empty',
			'f227' => 'flag',
			'f230' => 'location',
			'f231' => 'location-alt',
			'f178' => 'vault',
			'f332' => 'shield',
			'f334' => 'shield-alt',
			'f179' => 'search',
			'f181' => 'slides',
			'f183' => 'analytics',
			'f184' => 'piechart',
			'f185' => 'bargraph',
			'f238' => 'bargraph2',
			'f239' => 'bargraph3',
			'f307' => 'groups',
			'f338' => 'businessman',
			'f336' => 'id',
			'f337' => 'id-alt',
			'f312' => 'products',
			'f313' => 'awards',
			'f314' => 'forms',
			'f322' => 'portfolio',
			'f330' => 'book',
			'f331' => 'book-alt',
			'f316' => 'arrow-down',
			'f317' => 'arrow-up',
			'f321' => 'backup',
			'f339' => 'lightbulb',
			'f328' => 'smiley'
		);

	return array(
		'posts'       => array( 'label' => 'Posts' ),
		'dashboard'   => array( 'label' => 'Dashboard' ),
		'sites'       => array( 'label' => 'Sites' ),
		'media'       => array( 'label' => 'Media' ),
		'links'       => array( 'label' => 'Links' ),
		'pages'       => array( 'label' => 'Pages' ),
		'comments'    => array( 'label' => 'Comments' ),
		'appearance'  => array( 'label' => 'Appearance' ),
		'plugins'     => array( 'label' => 'Plugin' ),
		'users'       => array( 'label' => 'Users' ),
		'tools'       => array( 'label' => 'Tools' ),
		'settings'    => array( 'label' => 'Settings' ),
		'gear'        => array( 'label' => 'Gear',           'loc' => SIMIAN_ASSETS . 'images/icons/gear.png' ),
		'arrows'      => array( 'label' => 'Arrows',         'loc' => SIMIAN_ASSETS . 'images/icons/arrows.png' ),
		'article'     => array( 'label' => 'Article',        'loc' => SIMIAN_ASSETS . 'images/icons/article.png' ),
		'beaker'      => array( 'label' => 'Beaker',         'loc' => SIMIAN_ASSETS . 'images/icons/beaker.png' ),
		'calendar'    => array( 'label' => 'Calendar',       'loc' => SIMIAN_ASSETS . 'images/icons/calendar.png' ),
		'clapper'     => array( 'label' => 'Clapper',        'loc' => SIMIAN_ASSETS . 'images/icons/clapper.png' ),
		'clipboard'   => array( 'label' => 'Clipboard',      'loc' => SIMIAN_ASSETS . 'images/icons/clipboard.png' ),
		'image'       => array( 'label' => 'Image',          'loc' => SIMIAN_ASSETS . 'images/icons/image.png' ),
		'people'      => array( 'label' => 'People',         'loc' => SIMIAN_ASSETS . 'images/icons/people.png' ),
		'person-suit' => array( 'label' => 'Person in Suit', 'loc' => SIMIAN_ASSETS . 'images/icons/person-suit.png' ),
		'person'      => array( 'label' => 'Person',         'loc' => SIMIAN_ASSETS . 'images/icons/person.png' ),
		'question'    => array( 'label' => 'Question',       'loc' => SIMIAN_ASSETS . 'images/icons/question.png' ),
		'quote-tweet' => array( 'label' => 'Tweet Quote',    'loc' => SIMIAN_ASSETS . 'images/icons/quote-tweet.png' ),
		'quote'       => array( 'label' => 'Quote',          'loc' => SIMIAN_ASSETS . 'images/icons/quote.png' ),
		'simian'      => array( 'label' => 'Orangutan',      'loc' => SIMIAN_ASSETS . 'images/icons/simian.png' ),
		'windows'     => array( 'label' => 'Windows',        'loc' => SIMIAN_ASSETS . 'images/icons/windows.png' ),
		'slides'      => array( 'label' => 'Slides',         'loc' => SIMIAN_ASSETS . 'images/icons/slides.png' ),
	);

}


/**
 * Checks if XML string is valid XML. Useful for the 'link' field type.
 */
function simian_is_valid_xml( $xml ) {
	libxml_use_internal_errors( true );
	$doc = new DOMDocument( '1.0', 'utf-8' );
	$doc->loadXML( $xml );
	$errors = libxml_get_errors();
	return empty( $errors );
}


/**
 * Checks if this WP installation is using the new dashicons.
 */
function simian_is_using_dashicons() {

	if ( defined( 'SIMIAN_DASHICONS' ) && SIMIAN_DASHICONS )
		return true;

	if ( version_compare( $GLOBALS['wp_version'], '3.8-alpha', '>' ) )
		return true;

	return false;

}


/**
 * Remove empty elements of array. Recursively finds all subarrays as well.
 */
function simian_array_filter_deep( $top ) {

	foreach( $top as $key => $value ) {

		if ( $value === '' )
			unset( $top[$key] );

		if ( is_array( $value ) )
			$top[$key] = simian_array_filter_deep( $value );

	}

	return $top;

}


/**
 * Save a slug. Like sanitize_title, but we want to allow /.
 */
function simian_sanitize_slug( $slug ) {
	$slug = strtolower( $slug );
	$slug = preg_replace( '/[^a-z0-9_\-\/]/', '', $slug );
	$slug = trim( $slug, '/' );
	return $slug;
}


/**
 * Convert a name attr to an id, replacing brackets with underscores.
 */
function simian_sanitize_indexed_id( $id ) {
	$id = str_replace( '[', '_', $id );
	$id = str_replace( ']', '_', $id );
	return esc_attr( $id );
}


/**
 * Like sanitize_key(), except replace brackets with underscores.
 * Similar to simian_sanitize_indexed_id.
 */
function simian_sanitize_name( $name ) {
	$name = str_replace( '[', '_', $name );
	$name = str_replace( ']', '_', $name );
	$name = str_replace( '__', '_', $name );
	$name = trim( $name, '_' );
	return sanitize_key( $name );
}


/**
 * Get the current page URL.
 */
function simian_get_current_url( $raw = false, $with_query = false ) {

	$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$url = str_replace( '//', '/', $url );

	$protocol = is_ssl() ? 'https://' : 'http://';

	$url = $protocol . $url;

	$parts = parse_url( $url );
	$parts = wp_parse_args( $parts, array(
		'scheme' => '',
		'host'   => '',
		'path'   => '',
		'query'  => ''
	) );

	$url = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];

	if ( $with_query && $parts['query'] )
		$url = $url . '?' . $parts['query'];

	if ( $raw )
		return esc_url_raw( $url );
	else
		return esc_url( $url );

}


/**
 * Quick function to build checklists.
 */
function simian_checklist( $id = '', $name = '', $checked = array(), $checklist = array(), $type = 'checkbox' ) {
	foreach( $checklist as $value => $label ) {
		?><label for="<?php echo $id; ?>-<?php echo sanitize_key( $value ); ?>">
			<input id="<?php echo $id; ?>-<?php echo sanitize_key( $value ); ?>" type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $checked ) ); ?> /><?php
			echo $label;
		?></label><?php
	}
}


/**
 * Quick function to build radio lists.
 */
function simian_radios( $id, $name, $checked, $checklist ) {
	simian_checklist( $id, $name, $checked, $checklist, 'radio' );
}


/**
 * Display:none, or not.
 */
function simian_hide_if( $hide ) {

	if ( !$hide )
		return;

	echo ' style="display:none;"';

}


/**
 * Simian's version of wp_terms_checklist, to accept order/orderby args.
 */
function simian_terms_checklist( $post_id = 0, $taxonomy = '', $orderby = 'name', $order = 'ASC' ) {

	if ( !$post_id || !$taxonomy )
		return;

	$tax = get_taxonomy( $taxonomy );
	$args = array(
		'taxonomy' => $taxonomy,
		'disabled' => !current_user_can( $tax->cap->assign_terms ),
		'popular_cats' => array()
	);

	$categories = (array) get_terms( $taxonomy, array( 'get' => 'all', 'order' => $order, 'orderby' => $orderby ) );

	// get post's terms
	$args['selected_cats'] = wp_get_object_terms( $post_id, $taxonomy, array_merge( $args, array( 'fields' => 'ids' ) ) );
	$checked_categories = array();
	$keys = array_keys( $categories );
	foreach( $keys as $k ) {
		if ( in_array( $categories[$k]->term_id, $args['selected_cats'] ) ) {
			$checked_categories[] = $categories[$k];
			unset( $categories[$k] );
		}
	}

	$walker = new Walker_Category_Checklist;

	// display checked on top
	echo call_user_func_array( array( &$walker, 'walk' ), array( $checked_categories, 0, $args ) );

	// then display the rest
	echo call_user_func_array( array( &$walker, 'walk' ), array( $categories, 0, $args ) );

}


/**
 * Quick print_r.
 */
if ( !function_exists( '_pr' ) ) {
	function _pr( $check ) {
		echo '<pre>'; print_r( $check ); echo '</pre>';
	}
}


/**
 * Quick var_dump.
 */
if ( !function_exists( '_vd' ) ) {
	function _vd( $check ) {
		echo '<pre>'; var_dump( $check ); echo '</pre>';
	}
}