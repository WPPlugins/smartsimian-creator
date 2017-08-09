<?php

/**
 * Saving functions for admin forms. Miscellaneous.
 */
class Simian_Admin_Save {


	/**
	 * Save new or edited item.
	 */
	public static function save( $component ) {

		// verify submission
		if ( !isset( $_POST['simian_admin_submit'] ) )
			return;

		// verify nonce
		if ( !check_admin_referer( 'simian_' . $component . '_nonce', '_simian_nonce_' . $component ) )
			return array( '_errors' => 'Security check failed.' );

		// retrieve data. up to component to sanitize and return $_POST data and 'sysname'
		$data = apply_filters( 'simian_admin_save-' . $component, array() );

		// globalize data array
		global $simian_single_page_data;
		$simian_single_page_data = $data;

		// check for errors in returned data, might want to quit now
		if ( array_key_exists( '_errors', (array) $data ) )
			return $data;

		// debug point - we have the formatted data
		// echo '<pre>'; print_r( $data ); echo '</pre>';

		// determine Add New or Edit
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'add-new';

		// edit situations
		if ( $action == 'edit' ) {

			// retrieve item
			$item = isset( $_GET['item'] ) ? sanitize_key( $_GET['item'] ) : '';

			// update item
			$result = simian_update_item( $item, $component, $data );

		// add new situations
		} else {

			// add item
			$result = simian_add_item( $component, $data );

		}

		// check for errors in returned data
		if ( array_key_exists( '_errors', (array) $result ) )
			return $result;

		// flush rewrite rules when needed
		// must be done after the redirect when the content type or taxonomy has already been registered
		$append = '';
		if ( $component == 'content' || $component == 'taxonomy' )
			$append = '&rewrite=true';

		// update successful, so reload
		$sendback = admin_url( 'admin.php?page=simian-' . $component . '&action=edit&item=' . $result . '&updated=' . $action . $append );
		wp_redirect( $sendback );
		exit;

	}


	/**
	 * Ensure a sysname or slug is unique.
	 */
	public static function make_unique( $term, $component ) {

		// handle content types and taxonomies differently
		if ( $component == 'content' )
			$pool = self::get_reserved_terms( true );
		elseif( $component == 'taxonomy' )
			$pool = self::get_reserved_terms( false, true );
		else
			$pool = array_keys( (array) get_option( 'simian_' . $component ) );

		// if pool returned empty db entry
		if ( !array_filter( $pool ) )
			return $term;

		// if sysname already exists
		if ( in_array( $term, $pool ) ) {

			$i = 2;
			$sysname_attempt = $term . '-2';

			// increment sysname until new
			while( in_array( $sysname_attempt, $pool ) ) {
				$i++;
				$sysname_attempt = $term . '-' . $i;
			}

			$term = $sysname_attempt;

		}

		return $term;

	}


	/**
	 * Revert to previous save state of current item.
	 */
	public static function revert( $component ) {

		// verify nonce
		check_admin_referer( 'revert-' . $component );

		// get overwritten item
		$item = sanitize_key( $_GET['revert'] );

		// get overwritten data
		$overwritten = get_transient( 'simian_' . $component . '_' . $item );

		// replace in live data
		$option = get_option( 'simian_' . $component );
		$option[$item] = $overwritten;
		update_option( 'simian_' . $component, $option );

		// redirect
		$sendback = admin_url( 'admin.php?page=simian-' . $component . '&action=edit&item=' . $item . '&reverted=true' );
		wp_redirect( $sendback );
		exit;

	}


	/**
	 * Reserved WP terms.
	 */
	public static function get_reserved_terms( $include_types = false, $include_tax = false ) {

		$reserved = array(
			'ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title',
			'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password',
			'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt',
			'post_content_filtered', 'post_parent', 'guid', 'menu_order', 'post_type',
			'post_mime_type', 'comment_count',
			'attachment', 'attachment_id', 'author', 'author_name', 'calendar', 'cat',
			'category', 'category__and', 'category__in', 'category__not_in',
			'category_name', 'comments_per_page', 'comments_popup',
			'customize_messenger_channel', 'customized', 'cpage', 'day', 'date', 'debug',
			'error', 'exact', 'feed', 'hour', 'link_category', 'm', 'minute', 'monthnum',
			'more', 'name', 'nav_menu', 'network', 'nonce', 'nopaging', 'offset', 'order',
			'orderby', 'p', 'page', 'page_id', 'paged', 'pagename', 'pb', 'perm', 'post',
			'post__in', 'post__not_in', 'post_format', 'post_mime_type', 'post_tag',
			'posts', 'posts_per_archive_page', 'posts_per_page', 'preview', 'robots', 's',
			'search', 'second', 'sentence', 'showposts', 'static', 'subpost', 'subpost_id',
			'tag', 'tag__and', 'tag__in', 'tag__not_in', 'tag_id', 'tag_slug__and',
			'tag_slug__in', 'taxonomy', 'tb', 'term', 'type', 'user', 'w', 'withcomments',
			'withoutcomments', 'year'
		);

		if ( $include_types )
			$reserved = array_unique( array_merge( $reserved, get_post_types() ) );

		if ( $include_tax )
			$reserved = array_unique( array_merge( $reserved, get_taxonomies() ) );

		return $reserved;

	}



	/**
	 * Sanitize field args.
	 */
	static public function sanitize_fields( $dirty_fields = array(), $content_types = array() ) {

		if ( !$dirty_fields )
			$dirty_fields = array();

		$clean_fields = array();
		foreach( $dirty_fields as $field_index => $field ) {

			$defaults = array(
				'name'    => '',
				'label'   => '',
				'type'    => 'text',
				'note'    => '',
				'options' => array()
			);
			$field = wp_parse_args( $field, $defaults );

			// save featured images as meta even though they're identified as core
			if ( $field['name'] == '_thumbnail_id' ) {
				$field['type'] = 'file';
				$field['options']['max_files'] = '1';
			}

			// sanitize each field arg
			foreach( $field as $arg => $val ) {

				switch( $arg ) {

					// each field 'name'
					case 'name' :

						// first, basic sanitization.
						$field['name'] = sanitize_key( $val );

						if ( !$field['name'] )
							$field['name'] = sanitize_key( str_replace( ' ', '_', $field['label'] ) );

						// the name isn't really used for taxonomy and conn, so sync with the tax/conn type being used
						if ( $field['type'] == 'taxonomy' ) {
							$field['name'] = $field['options']['taxonomy'];
						} elseif ( $field['type'] == 'connection' ) {
							$field['name'] = isset( $field['options']['connection_type'] ) ? $field['options']['connection_type'] : '';

						// for core types -- used in submission forms, maybe admin fieldsets down the road --
						// the name should be one of a few specific things
						} elseif ( $field['type'] == 'core' ) {

							$core_names = array(
								'post_title',
								'post_status',
								'post_author',
								'post_date',
								'post_date_gmt',
								'post_content',
								'post_excerpt',
								'post_parent',
								'post_password',
								'menu_order',
								'comment_status',
								'ping_status'
							);

							// by unsetting $field['name'] here, the field as a whole won't be saved
							if ( !in_array( $field['name'], $core_names ) )
								$field['name'] = '';

						// for everything else, check reserved terms plus post type and taxonomy names
						} else {

							$reserved = self::get_reserved_terms( true, true );

							if ( in_array( $field['name'], $reserved ) ) {

								$i = 2;
								$attempt = $field['name'] . '-2';

								// increment sysname until new
								while( in_array( $attempt, $reserved ) ) {
									$i++;
									$attempt = $field['name'] . '-' . $i;
								}

								$field['name'] = $attempt;

							}

						}

						break;
					case 'label' :
						$field['label'] = sanitize_text_field( $val );
						break;
					case 'type' :
						$field['type'] = sanitize_key( $val );
						break;
					case 'note' :
						$field['note'] = wp_filter_post_kses( $val );
						break;
					case 'options' :
						$field['options'] = self::sanitize_options( $val, $field, $content_types );
						break;
					default:
						unset( $field[$arg] );
						break;
				}

			}

			// give instructions a name field if they don't have one
			if ( $field['type'] == 'instructions' && !$field['name'] )
				$field['name'] = 'instructions_' . $field_index;

			// if there wasn't one of these before, there will be now
			if ( $field['type'] == 'connection' && !$field['name'] )
				$field['name'] = isset( $field['options']['connection_type'] ) ? $field['options']['connection_type'] : '';
				// if connection_type isn't even set, then the person didn't even get that far, so the field doesn't have enough info to stick.
				// so we will ignore it with the if statement below.

			// same thing for taxonomies
			if ( $field['type'] == 'taxonomy' && $field['name'] === '_add_new' )
				$field['name'] = isset( $field['options']['taxonomy'] ) ? $field['options']['taxonomy'] : '';

			// fold into $clean_fields (if there's still no field name or type, it's an empty/incomplete row, so don't add it)
			if ( $field['name'] && $field['type'] )
				$clean_fields[] = $field;

		}

		return $clean_fields;

	}


	/**
	 * Sanitize options within field args.
	 */
	static function sanitize_options( $options, $field = array(), $content_types = array() ) {

		$clean_options = array();

		foreach( $options as $option => $value ) {

			// don't save blank options (except for those in the array)
			if ( $value === '' && !in_array( $option, array( 'empty_option', 'connection_type' ) ) )
				continue;

			switch( $option ) {

				case 'connection_type' :
				case 'data_type' :
				case 'display' :
				case 'select' :
				case 'taxonomy' :
				case 'opp_content_type' :
					$clean_options[$option] = sanitize_key( $value );
					break;

				case 'order' :
				case 'orderby' :
				case 'false_label' :
				case 'placeholder' :
				case 'true_label' :
				case 'empty_option' :
					$clean_options[$option] = sanitize_text_field( $value );
					break;

				case 'file_size' :
				case 'max' :
				case 'max_files' :
				case 'min' :
				case 'rows' :
				case 'step' :
					$clean_options[$option] = (int) $value;
					break;

				case 'file_types' :
					$clean_options[$option] = array_map( 'sanitize_text_field', $value );
					break;

				default :

					$component = str_replace( 'simian-', '', isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '' );

					// options created by non-core components can run this filter to sanitize.
					// by default it will return blank ($value is the *second* parameter) so
					// if no sanitizing function is hooked, it won't save.

					$result = '';
					if ( $component )
						$result = apply_filters( 'simian_custom-' . $component . '-option', '', $value, $option );

					if ( $result !== '' )
						$clean_options[$option] = $result;

					break;

			}

			// special taxonomy abilities
			if ( $option === 'taxonomy' ) {

				// add a taxonomy and terms
				if ( $value === '_add_new' )
					$clean_options['taxonomy'] = self::add_taxonomy_async( $field );

				// make sure this taxonomy is assigned to the fieldset's post types
				if ( $value && $value !== '_add_new' )
					self::assign_missing_taxonomies( $value );

			}

			// special connection abilities
			if ( $option === 'connection_type' ) {

				// define needed keys
				$options = wp_parse_args( $options, array(
					'connect_to_type' => '',
					'connect_to'      => array()
				) );

				// no connection type set - add new
				if ( !$value ) {

					// find what to connect to, either 'user' or an array of post types
					$connect_to = ( $options['connect_to_type'] === 'user' ) ? 'user' : '';
					if ( !$connect_to )
						$connect_to = $options['connect_to'];

					// create a new connection type, will overwrite blank value saved above
					$clean_options['connection_type'] = self::add_connection_async( $connect_to, $field, $content_types );

				// connection type set - maybe adjust title
				} else {

					// connection type label
					$ct_args = simian_get_item( $value, 'connection' );

					// get fields of connected-to type
					$connect_to = ( $options['connect_to'] === 'user' ) ? array( 'user' ) : $options['connect_to'];

					if ( count( (array) $connect_to ) === 1 ) {

						$ct_args = wp_parse_args( $ct_args, array( 'label' => '' ) );
						$field   = wp_parse_args( $field,   array( 'label' => '' ) );

						// Customize the title to reflect both sides of the connection,
						// but only if the connection title is identical to only one side and not the same
						// on both sides
						$connect_to = array_shift( $connect_to );
						$opp_fields = simian_get_fields( $connect_to );
						foreach( $opp_fields as $opp_field ) {
							$opp_field = wp_parse_args( $opp_field, array(
								'label' => '',
								'type'  => ''
							) );
							if ( $opp_field['type'] !== 'connection' )
								continue;
							if ( ( $ct_args['label'] === $opp_field['label'] ) && ( $field['label'] !== $opp_field['label'] ) ) {
								$ct_args['label'] = $ct_args['label'] . '/' . $field['label'];
								simian_update_item( $value, 'connection', $ct_args );
								break;
							}
						}

					}


				}

			}

		}

		return $clean_options;

	}


	/**
	 * Add a taxonomy in the process of saving a fieldset.
	 */
	static private function add_taxonomy_async( $field )  {

		// add the taxonomy
		if ( !$field['label'] )
			$field['label'] = '(no name)';

		$label = sanitize_text_field( $field['label'] );

		$sysname    = Simian_Admin_Save::make_unique( simian_generate_sysname( $label, 30 ), 'taxonomy' );

		$post_types = array();
		if ( isset( $_POST['content_types'] ) )
			$post_types = $_POST['content_types'];
		elseif( isset( $_POST['content_type'] ) )
			$post_types = $_POST['content_type'];

		$post_types = array_map( 'sanitize_key', (array) $post_types );

		// add tax to db
		$taxonomy = simian_add_item( 'taxonomy', array(
			'sysname'        => $sysname,
			'singular_label' => $label,
			'plural_label'   => $label,
			'hierarchical'   => true,
			'slug'           => $sysname,
			'post_types'     => $post_types
		) );

		// register_taxonomy manually right now so wp_insert_term works
		register_taxonomy( $taxonomy, $post_types, array(
			'rewrite' => array( 'slug' => $sysname ),
			'hierarchical' => true
		) );

		// add the terms
		$terms = isset( $field['options']['terms'] ) ? $field['options']['terms'] : '';
		$terms = explode( "\n", $terms );
		$terms = array_map( 'sanitize_text_field', $terms );
		foreach( $terms as $term )
			wp_insert_term( $term, $taxonomy );

		// send the newly-created taxonomy back
		return $taxonomy;

	}


	/**
	 * Make sure this taxonomy is assigned to the fieldset's post types.
	 */
	static private function assign_missing_taxonomies( $taxonomy ) {

		$all = get_taxonomy( $taxonomy );
		$taxonomy = simian_get_item( $taxonomy, 'taxonomy' );

		// built-in taxonomy
		if ( $all && !$taxonomy )
			return;

		// post types being saved
		$post_types = isset( $_POST['content_types'] ) ? array_map( 'sanitize_key', (array) $_POST['content_types'] ) : array();

		// post types already saved
		$saved_types = isset( $taxonomy['post_types'] ) ? $taxonomy['post_types'] : array();

		// keep all saved types and add any that might be new
		$new_types = array_unique( array_merge( $post_types, $saved_types ) );
		$taxonomy['post_types'] = $new_types;
		simian_update_item( $taxonomy['sysname'], 'taxonomy', $taxonomy );

	}


	/**
	 * Add a connection type (or types) in the process of saving a fieldset.
	 */
	static private function add_connection_async( $to = '', $field = array(), $content_types ) {

		$to = array_map( 'sanitize_key', (array) $to );
		if ( $to === array( 'user' ) )
			$to = 'user';

		if ( !$to || !$field )
			return '';

		// content type(s) this fieldset will display on
		$from = array();
		if ( isset( $_POST['content_types'] ) )
			$from = $_POST['content_types'];
		elseif( isset( $_POST['content_type'] ) )
			$from = $_POST['content_type'];
		$from = array_map( 'sanitize_key', (array) $from );

		if ( empty( $from ) )
			return '';

		// build label and sysname for connection type
		$label = sanitize_text_field( $field['label'] );
		if ( !$label )
			$label = 'field_connection';

		if ( count( $content_types ) === 1 ) {
			$content_type = sanitize_key( array_shift( $content_types ) );
			$sysname = simian_generate_sysname( $content_type . ' ' . $label, 42 );
		} else {
			$sysname = simian_generate_sysname( $label, 42 );
		}

		$sysname = Simian_Admin_Save::make_unique( $sysname, 'connection' );

		$connection_type = simian_add_item( 'connection', array(
			'sysname' => $sysname,
			'label'   => $label,
			'from'    => $from,
			'to'      => $to
		) );

		return $connection_type;

	}


}