<?php
/**
 * Set of methods to used when a post is saved, to save custom meta, taxonomy, and connection data.
 */
class Simian_Save {


	/**
	 * Save postmeta.
	 *
	 * Keep in mind:
	 * -Values are always saved as strings in the database even if they're set as something else during sanitization.
	 * -That's why, when comparing old/new values, we use loose comparisons.
	 * -Blank '' deletes the meta. All other values (0, 0.00, etc.) are stored.
	 */
	static public function meta( $post_id, $field ) {

		$dirty_value = isset( $_POST[$field['name']] ) ? $_POST[$field['name']] : '';

		$clean_value = '';

		// run sanitization function based on field type
		if ( is_callable( array( __CLASS__, $field['type'] ) ) )
			$clean_value = call_user_func( array( __CLASS__, $field['type'] ), $field, $dirty_value );

		// clean up any whitespace that might exist
		if ( !is_array( $clean_value ) )
			$clean_value = trim( $clean_value );

		// get current value
		$current_value = get_post_meta( $post_id, $field['name'], true );

		// instead of below method, allow postmeta to exist no matter what.
		// helpful for meta_key querys, etc.
		update_post_meta( $post_id, $field['name'], $clean_value );

		/*
		// if new value is blank, delete the meta (if meta doesn't exist yet, nothing will happen)
		if ( $clean_value === '' ) {
			delete_post_meta( $post_id, $field['name'], $current_value );

		// else if there's new information
		} elseif ( $clean_value !== '' && $clean_value != $current_value ) {
			update_post_meta( $post_id, $field['name'], $clean_value );
		}
		*/

	}


	/**
	 * Deprecated fields below. Do not delete.
	 * @deprecated
	 ********************************************************************
	 */
	public static function wysiwyg( $field, $dirty_value ) {
		if ( !isset( $field['options'] ) ) $field['options'] = array();
		$field['options']['display'] = 'wysiwyg';
		return self::longtext( $field, $dirty_value );
	}
	public static function textarea( $field, $dirty_value ) {
		if ( !isset( $field['options'] ) ) $field['options'] = array();
		$field['options']['display'] = 'textarea';
		return self::longtext( $field, $dirty_value );
	}
	public static function url( $field, $dirty_value ) {
		if ( !isset( $field['options'] ) ) $field['options'] = array();
		$field['options']['data_type'] = 'url';
		return self::text( $field, $dirty_value );
	}
	public static function date( $field, $dirty_value ) {
		if ( !isset( $field['options'] ) ) $field['options'] = array();
		$field['options']['select'] = 'date';
		return self::datetime( $field, $dirty_value );
	}


	/**
	 * Save taxonomy data.
	 *
	 * The value will either come in as an int or an array of ints. It only updates the terms for
	 * the given post, and can't edit term data, etc.
	 */
	static public function taxonomy( $post_id, $field ) {

		$taxonomy = isset( $field['options']['taxonomy'] ) ? sanitize_key( $field['options']['taxonomy'] ) : '';
		if ( !$taxonomy )
			return;
		$tax = get_taxonomy( $taxonomy );

		if ( !$tax->hierarchical ) {

			// get selected terms
			$dirty_value = isset( $_POST['simian_tag_input'][$taxonomy] ) ? (array) $_POST['simian_tag_input'][$taxonomy] : array();

		} else {

			// could be int or array of ints
			$dirty_value = isset( $_POST['tax_input'][$taxonomy] ) ? $_POST['tax_input'][$taxonomy] : array();

		}

		if ( !$dirty_value )
				return;

		if ( !is_array( $dirty_value ) )
			$dirty_value = array( $dirty_value );

		// sanitize
		$clean_value = array_unique( array_map( 'intval', $dirty_value ) );

		if ( empty( $clean_value ) )
			return;

		// associate term/taxonomy pair with post, overwrites existing
		wp_set_object_terms( $post_id, $clean_value, $taxonomy );

	}


	/**
	 * Save connection data.
	 *
	 * Like on the backend edit screen, this can only create or remove connections. It can't edit
	 * data on the other side of the connection.
	 *
	 * Like taxonomies, value will be an int or an array of ints.
	 */
	static public function connection( $post_id, $field ) {

		// could be int or array of ints
		$dirty_value = isset( $_POST['simian_conn'][$field['options']['connection_type']] ) ? $_POST['simian_conn'][$field['options']['connection_type']] : array();

		if ( !is_array( $dirty_value ) )
			$dirty_value = array( $dirty_value );

		// sanitize
		$clean_value = array_map( 'intval', $dirty_value );

		if ( empty( $clean_value ) || !$field['options']['connection_type'] )
			return;

		// p2p object
		$type = p2p_type( $field['options']['connection_type'] );

		// get 'from' post type
		$from = isset( $conn->side['from']->query_vars['post_type'] ) ? $conn->side['from']->query_vars['post_type'] : 'user';

		// get post data
		$post = get_post( $post_id );

		if ( $from == $post->post_type ) {

			// delete existing
			p2p_delete_connections( $field['options']['connection_type'], array(
				'direction' => 'from',
				'from'      => $post_id,
				'to'        => 'any'
			) );

			// create connections when from=post_id, to=values
			foreach( $clean_value as $conn_id ) {
				if ( $conn_id )
					p2p_type( $field['options']['connection_type'] )->connect( $post_id, $conn_id );
			}

		} else {

			// delete existing
			p2p_delete_connections( $field['options']['connection_type'], array(
				'direction' => 'to',
				'from'      => 'any',
				'to'        => $post_id
			) );

			// create connections when from=values, to=post_id
			foreach( $clean_value as $conn_id ) {
				if ( $conn_id )
					p2p_type( $field['options']['connection_type'] )->connect( $conn_id, $post_id );
			}

		}

	}



	/**
	 * Sanitize text field as postmeta.
	 */
	static public function text( $field, $dirty_value ) {

		// email, url, dollar, phone, decimal, integer, and default
		$data_type = isset( $field['options']['data_type'] ) ? $field['options']['data_type'] : 'default';

		// special case: get rid of possible '$'
		if ( $data_type == 'dollar' )
			$dirty_value = str_replace( '$', '', $dirty_value );

		// start sanitizing. "!==" often used below to distinguish a blank field ("") from a 0, etc.

		if ( $data_type == 'email' )
			$clean_value = $dirty_value ? sanitize_email( $dirty_value ) : '';

		elseif ( $data_type == 'url' )
			$clean_value = ( $dirty_value && $dirty_value != 'http://' ) ? esc_url_raw( $dirty_value ) : '';

		elseif ( $data_type == 'dollar' )
			$clean_value = ( $dirty_value !== '' && $dirty_value != '0.00' ) ? number_format( (float) $dirty_value, 2, '.', '' ) : '';

		elseif ( $data_type == 'phone' )
			$clean_value = ( $dirty_value !== '' && $dirty_value != '000-000-0000' && preg_match( "/^([1]-)?[0-9]{3}-[0-9]{3}-[0-9]{4}$/", $dirty_value ) ) ? $dirty_value : '';

		elseif ( $data_type == 'decimal' )
			$clean_value = ( $dirty_value !== '' ) ? (float) $dirty_value : '';

		elseif ( $data_type == 'integer' || $data_type == 'int' )
			$clean_value = ( $dirty_value !== '' ) ? (int) $dirty_value : '';

		else
			$clean_value = ( $dirty_value !== '' ) ? wp_filter_post_kses( $dirty_value ) : '';

		return $clean_value;

	}


	/**
	 * Sanitize link field as postmeta.
	 */
	static public function link( $field, $dirty_value ) {

		if ( !is_array( $dirty_value ) )
			return '';

		$dirty_url = isset( $dirty_value['url'] ) ? $dirty_value['url'] : '';
		if ( !$dirty_url || $dirty_url == 'http://' )
			return '';

		$url    = esc_url_raw( $dirty_value['url'] );

		$dirty_label = isset( $dirty_value['label'] ) ? $dirty_value['label'] : '';
		if ( !$dirty_label || $dirty_label == 'Label' )
			$label = esc_url_raw( $dirty_value['url'] );
		else
			$label = sanitize_text_field( $dirty_value['label'] );

		return '<a href="' . $url . '">' . $label . '</a>';

	}


	/**
	 * Sanitize rich_text as postmeta.
	 */
	static public function rich_text( $field, $dirty_value ) {
		return ( $dirty_value !== '' ) ? wp_filter_post_kses( $dirty_value ) : '';
	}


	/**
	 * Sanitize longtext field as postmeta.
	 */
	static public function longtext( $field, $dirty_value ) {
		return ( $dirty_value !== '' ) ? wp_filter_post_kses( $dirty_value ) : '';
	}


	/**
	 * Sanitize file field as postmeta.
	 *
	 * Files have already been uploaded and attached to the post; our job here is to
	 * save the attachment IDs as meta.
	 */
	static public function file( $field, $dirty_value ) {

		// multiple attachments
		if ( strpos( $dirty_value, ',' ) !== false ) {
			$attachment_ids = array();
			foreach( explode( ',', $dirty_value ) as $attachment_id ) {
				$attachment_ids[] = (int) trim( $attachment_id );
			}

		// single attachment
		} else {
			$attachment_ids = (int) trim( $dirty_value );
		}

		// double-check file amount
		$max_files = isset( $field['options']['max_files'] ) ? (int) $field['options']['max_files'] : 0;
		if( $max_files && is_array( $attachment_ids ) ) {

			if ( count( $attachment_ids ) > $max_files ) {

				// array got too big, let's trim it down
				foreach( $attachment_ids as $key => $ids ) {
					if ( $key >= $max_files )
						unset( $attachment_ids[$key] );
				}

				if ( count( $attachment_ids ) === 1 )
					$attachment_ids = array_shift( $attachment_ids );

			}

		}

		// meta will be either saved as string, or as array - NOT multiple meta values per key
		$clean_value = $dirty_value ? $attachment_ids : '';

		return $clean_value;

	}


	/**
	 * Sanitize bool field as postmeta.
	 */
	static public function bool( $field, $dirty_value ) {
		return $dirty_value ? (bool) $dirty_value : false;
	}


	/**
	 * Sanitize datetime field as postmeta.
	 *
	 * Dates and times saved in specified user format.
	 *
	 * @todo this needs testing, not sure it works right yet
	 */
	static public function datetime( $field, $dirty_value ) {

		if ( !isset( $field['options'] ) ) $field['options'] = array();
		$defaults = array(
			'select' => 'both'
		);
		extract( wp_parse_args( $field['options'], $defaults ) );

		// if value is blank
		if ( !$dirty_value )
			return '';

		// if strtotiming doesn't work
		if ( !strtotime( $dirty_value ) )
			return '';

		if ( $select == 'date' )
			$clean_value = date( 'Y-m-d', strtotime( $dirty_value ) );

		elseif( $select == 'time' )
			$clean_value = date( 'H:i:s', strtotime( $dirty_value ) );

		else
			$clean_value = date( 'Y-m-d H:i:s', strtotime( $dirty_value ) );

		return $clean_value;

	}


	/**
	 * Save instructions. Or not.
	 */
	static public function instructions( $field, $dirty_value ) {}


}