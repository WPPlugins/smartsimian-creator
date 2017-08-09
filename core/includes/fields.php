<?php
/**
 * Helper class to display fields within a form.
 *
 * Used in the backend for content type edit screens, and the frontend for
 * submission forms. Extendable, especially for different options. Includes
 * nonce, but not <form> tags.
 */
class Simian_Fields {


	/**
	 * The outline of the Fields meta box.
	 */
	public static function init( $post, $args, $display = '' ) {

		if ( !$display )
			$display = 'table';

		if ( !$post )
			$post = null;

		$name = $args['args']['name'];
		$fields = $args['args']['fields'];

		// output nonce
		wp_nonce_field( 'simian_fields_nonce', '_simian_nonce_fields_' . $name );

		// get table or list container
		call_user_func( array( __CLASS__, $display . '_container' ), $post, $fields );

	}


	/**
	 * Table form container. This is formatted to adhere to the WP admin's 'form-table' class
	 * and so is best used in the backend.
	 */
	public static function table_container( $post, $fields, $class = __CLASS__ ) {

		?><table class="form-table simian-form-table simian">

		<?php foreach( $fields as $field ) {

			$field = wp_parse_args( $field, array(
				'name' => '',
				'type' => '',
				'label' => '',
				'note' => '',
				'class' => '',
				'options' => array()
			) );

			?><tr valign="top" class="<?php echo simian_sanitize_name( $field['name'] ); ?>-container<?php echo ( $field['type'] == 'instructions' ) ? ' simian-instructions' : ''; echo $field['class'] ? ' ' . $field['class'] : ''; ?>">
				<th scope="row">
					<label for="<?php echo esc_attr( simian_sanitize_name( $field['name'] ) ); ?>">
						<?php echo stripslashes( $field['label'] ); ?>
						<?php if ( isset( $field['options']['required'] ) ) {
							echo ( $field['options']['required'] ) ? '<span class="simian-required">*</span>' : '';
						} ?>
					</label>
				</th>
				<td <?php if ( $field['type'] == 'taxonomy' ) echo 'class="list:' . simian_sanitize_name( $field['name'] ) . '"'; ?>>
					<?php call_user_func( array( $class, 'output_field' ), $post, $field ); ?>
					<?php if ( $field['note'] ) { ?><span class="howto"><?php echo stripslashes( $field['note'] ); ?></span><?php } ?>
				</td>
			</tr>
		<?php } ?>

		</table><?php
	}


	/**
	 * UL form container. Doesn't mess with tables, and the <label> tag wraps around the
	 * entire field.
	 */
	public static function ul_container( $post, $fields, $class = __CLASS__ ) {
		?><ul class="simian-form-list simian"><?php

		foreach( $fields as $field ) {

			$field = wp_parse_args( $field, array(
				'name' => '',
				'type' => '',
				'label' => '',
				'note' => '',
				'class' => '',
				'options' => array()
			) );

			?><li class="simian-form-item-<?php echo esc_attr( simian_sanitize_name( $field['name'] ) ); ?> simian-form-item simian-param-container simian-form-<?php echo $field['type']; ?>-item<?php if ( $field['type'] == 'instructions' ) echo ' simian-instructions'; echo $field['class'] ? ' ' . $field['class'] : ''; ?>">
				<label for="<?php echo esc_attr( simian_sanitize_name( $field['name'] ) ); ?>">
					<?php echo stripslashes( $field['label'] ); ?>
					<?php if ( isset( $field['options']['required'] ) ) {
						echo ( $field['options']['required'] ) ? '<span class="simian-required">*</span>' : '';
					} ?>
				</label>
				<div class="simian-form-item-inner<?php if ( $field['type'] == 'taxonomy' ) echo ' simian-list-' . simian_sanitize_name( $field['name'] ); ?>">
					<?php call_user_func( array( $class, 'output_field' ), $post, $field ); ?>
					<?php if ( $field['note'] ) { ?><span class="howto"><?php echo stripslashes( $field['note'] ); ?></span><?php } ?>
				</div>
			</li>
		<?php } ?>

		</ul><?php
	}


	/**
	 * OL form container. Doesn't mess with tables, and the <label> tag wraps around the
	 * entire field.
	 */
	public static function ol_container( $post, $fields, $class = __CLASS__ ) {
		?><ol class="simian-form-list simian"><?php

		foreach( $fields as $field ) {

			$field = wp_parse_args( $field, array(
				'name' => '',
				'type' => '',
				'label' => '',
				'note' => '',
				'class' => '',
				'options' => array()
			) );

			?><li class="simian-form-item-<?php echo esc_attr( simian_sanitize_name( $field['name'] ) ); ?> simian-form-item simian-param-container simian-form-<?php echo $field['type']; ?>-item<?php if ( $field['type'] == 'instructions' ) echo ' simian-instructions'; echo $field['class'] ? ' ' . $field['class'] : ''; ?>">
				<label for="<?php echo esc_attr( simian_sanitize_name( $field['name'] ) ); ?>">
					<?php echo stripslashes( $field['label'] ); ?>
					<?php if ( isset( $field['options']['required'] ) ) {
						echo ( $field['options']['required'] ) ? '<span class="simian-required">*</span>' : '';
					} ?>
				</label>
				<div class="simian-form-item-inner<?php if ( $field['type'] == 'taxonomy' ) echo ' simian-list-' . simian_sanitize_name( $field['name'] ); ?>">
					<?php call_user_func( array( $class, 'output_field' ), $post, $field ); ?>
					<?php if ( $field['note'] ) { ?><span class="howto"><?php echo stripslashes( $field['note'] ); ?></span><?php } ?>
				</div>
			</li>
		<?php } ?>

		</ol><?php
	}


	/**
	 * DIV form container. For when you don't want to use tables or lists.
	 */
	public static function div_container( $post, $fields, $class = __CLASS__, $before = '', $after = '' ) {
		?><div class="simian-form-list simian"><?php

		foreach( $fields as $field ) {

			$field = wp_parse_args( $field, array(
				'name' => '',
				'type' => '',
				'label' => '',
				'note' => '',
				'class' => '',
				'options' => array()
			) );

			echo $before;
			?><div class="simian-form-item-<?php echo esc_attr( simian_sanitize_name( $field['name'] ) ); ?> simian-form-item simian-param-container simian-form-<?php echo $field['type']; ?>-item<?php if ( $field['type'] == 'instructions' ) echo ' simian-instructions'; echo $field['class'] ? ' ' . $field['class'] : ''; ?>">
				<label for="<?php echo esc_attr( simian_sanitize_name( $field['name'] ) ); ?>">
					<?php echo stripslashes( $field['label'] ); ?>
					<?php if ( isset( $field['options']['required'] ) ) {
						echo ( $field['options']['required'] ) ? '<span class="simian-required">*</span>' : '';
					} ?>
				</label>
				<div class="simian-form-item-inner<?php if ( $field['type'] == 'list' ) echo ' simian-list-' . simian_sanitize_name( $field['name'] ); ?>">
					<?php call_user_func( array( $class, 'output_field' ), $post, $field ); ?>
					<?php if ( $field['note'] ) { ?><span class="howto"><?php echo stripslashes( $field['note'] ); ?></span><?php } ?>
				</div>
				<div class="simian-clear"></div>
			</div><?php
			echo $after;

		}

		 ?></div><?php
	}


	/**
	 * Output the form field.
	 */
	public static function output_field( $post, $field ) {
		$type = $field['type'];
		$name = $field['name'];
		$options = isset( $field['options'] ) ? $field['options'] : array();

		// if a $_POST value exists, that takes precedence (helpful in submission forms)
		// this won't affect taxonomies and connections, their $_POST values are checked
		// inside their individual functions
		$value = isset( $_POST[$name] ) ? $_POST[$name] : '';

		// sanitize $_POST value (just for output, not saving or anything)
		if ( $value ) {

			// all arrays should be of ints
			if ( is_array( $value ) && $type !== 'link' )
				$value = array_map( 'absint', $value );

			// link arrays
			elseif( $type === 'link' )
				$value = array_map( 'sanitize_text_field', $value );

			// longtexts allow html
			elseif ( $type == 'longtext' || $type == 'rich_text' )
				$value = esc_textarea( $value );
				// $value = wp_filter_post_kses( $value );

			// everything else just clean up
			else
				$value = sanitize_text_field( $value );

		// pulling from db so no need for this initial sanitization (we still run esc_attr and such)
		} else {

			// connections and taxonomies get the post object, but all other types just need the postmeta value
			if ( $type == 'taxonomy' || $type == 'connection' )
				$value = $post;
			elseif( $type == 'core' )
				$value = isset( $post->{$name} ) ? $post->{$name} : '';
			else
				$value = ( $post ) ? get_post_meta( $post->ID, $name, true ) : '';

		}

		// return the field with output buffering
		ob_start();

		// call method
		if ( method_exists( __CLASS__, $type ) )
			call_user_func( array( __CLASS__, $type ), $value, $name, $options );
		// ...or apply filters for a custom type
		else
			echo apply_filters( 'simian_search_fields-' . $type, $value, $name, $options );

		$returned = ob_get_clean();

		// filter output of any existing field type before echoing, can narrow by name
		echo apply_filters( 'simian_field_' . $type, $returned, $value, $name, $options );

		/*
		Custom function example:

		// //
		// Filter display of my 'custom-name' field
		//
		function my_taxonomy_field( $output, $value, $name, $options ) {

			if ( $name != 'custom-name' )
				return $output;

			$output = 'Custom output';

			return $output;

		}
		add_filter( 'simian_field_taxonomy', 'my_taxonomy_field' );

		*/

	}


	/**
	 * Single-line text field.
	 */
	public static function text( $value, $name, $options ) {

		$id = simian_sanitize_name( $name );

		$default = '';
		$classes = isset( $options['class'] ) ? $options['class'] : '';
		$data_type = isset( $options['data_type'] ) ? $options['data_type'] : 'default';
		$disabled = isset( $options['disabled'] ) ? $options['disabled'] : false;
		$input_type = 'text';

		$min = isset( $options['min'] ) ? (float) $options['min'] : false;
		$max = isset( $options['max'] ) ? (float) $options['max'] : false;
		$step = isset( $options['step'] ) ? (float) $options['step'] : false;
		$placeholder = isset( $options['placeholder'] ) ? sanitize_text_field( $options['placeholder'] ) : false;

		switch( $data_type ) {
			case 'dollar' :
				$default = '0.00';
				break;

			case 'phone' :
				$default = '000-000-0000';
				break;

			case 'url' :
				$default = 'http://';
				// no break

			case 'email' :
			case 'address' :
			case 'default' :
				$classes = $classes . ' widefat';
				break;

			case 'decimal' :
			case 'integer' :
			case 'int' :
				$input_type = 'number';
				break;
		}

		// overwrite default with default val set in options if exists
		$default = isset( $options['default'] ) ? $options['default'] : $default;

		?><input
			type="<?php echo $input_type; ?>"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			class="simian-text <?php echo $classes; ?>"
			<?php if ( $value ) { ?>
			value="<?php echo esc_attr( stripslashes( $value ) ); ?>"
			<?php } else { ?>
			value="<?php echo esc_attr( stripslashes( $default ) ); ?>"
			<?php }
			if ( $disabled ) echo ' disabled="disabled"';
			if ( $placeholder ) echo ' placeholder="' . esc_attr( stripslashes( $placeholder ) ) . '"';
			if ( $min !== false ) echo ' min="' . $min . '"';
			if ( $max !== false ) echo ' max="' . $max . '"';
			if ( $step !== false ) echo ' step="' . $step . '"'; ?>
		/><?php

	}


	/**
	 * Link. Label and URL.
	 */
	public static function link( $value, $name, $options ) {

		// if unsanitized value. since this is a special field that
		// uses simplexmlelement we need to do some massaging.
		if ( is_array( $value ) )
			$value = Simian_Save::link( array(), $value );

		$id = simian_sanitize_name( $name );

		// options['default'] accepts a normally-formatted link
		if ( !$value ) {
			$value = isset( $options['default'] ) ? $options['default'] : '';
		}

		if ( $value ) {
			if ( simian_is_valid_xml( $value ) ) {
				$value = new SimpleXMLElement( $value );
			}
		}

		$href = isset( $value['href'] ) ? $value['href'] : '';
		$target = isset( $value['target'] ) ? $value['target'] : '';

		$target = ( $target == '_blank' ) ? true : false;

		?><span>Label:</span> <input
			type="text"
			id="<?php echo esc_attr( $id ); ?>_label"
			name="<?php echo esc_attr( $name ); ?>[label]"
			class="simian-link-label simian-link"
			<?php if ( $value ) { ?>
			value="<?php echo esc_attr( $value ); ?>"
			<?php } ?>
			placeholder="Label"
		/>&nbsp;&nbsp;
		<span>URL:</span> <input
			type="text"
			id="<?php echo esc_attr( $id ); ?>_url"
			name="<?php echo esc_attr( $name ); ?>[url]"
			class="simian-link-url simian-link"
			<?php if ( $href ) { ?>
			value="<?php echo esc_attr( $href ); ?>"
			<?php } else { ?>
			value="http://"
			<?php } ?>
		/><?php

	}


	/**
	 * Rich text field (wysiwyg editors).
	 */
	public static function rich_text( $value, $name, $options = array() ) {
		$options['display'] = 'wysiwyg';
		self::longtext( $value, $name, $options );
	}


	/**
	 * Long text field (text boxes or wysiwygs).
	 */
	public static function longtext( $value, $name, $options ) {

		$id = simian_sanitize_name( $name );

		$display = isset( $options['display'] ) ? $options['display'] : 'textarea';
		$placeholder = isset( $options['placeholder'] ) ? stripslashes( esc_textarea( $options['placeholder'] ) ) : '';

		if ( !$value ) {
			$value = isset( $options['default'] ) ? $options['default'] : '';
		}

		switch( $display ) {

			case 'textarea' :
				?><textarea
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					class="simian-textarea widefat simian-code-text"
					cols="80"
					rows="<?php echo isset( $options['rows'] ) ? $options['rows'] : 6; ?>"
					<?php echo $placeholder ? 'placeholder="' . $placeholder . '"' : ''; ?>
				><?php
					echo ( $value ) ? stripslashes( $value ) : '';
				?></textarea><?php
				break;

			case 'wysiwyg' :

				$content = $value ? stripslashes( $value ) : $placeholder;

				wp_editor(
					$content,			// default content
					esc_attr( $name ),	// HTML ID and form name
					array(
						'textarea_rows' => isset( $options['rows'] ) ? $options['rows'] : 15,
						'editor_css'    => '<style>.mceIframeContainer iframe { background:#fff; }</style>',
						'editor_class'  => 'simian-wysiwyg'
					)
				);
				break;

		}

	}


	/**
	 * Connection field.
	 *
	 * Some of this is taken from /connections/admin/box-factory.php.
	 *
	 * For this field type, the first parameter is sent the $post object.
	 *
	 * On frontend submission forms, the connection box is not allowed, but taxonomy-style
	 * displays are allowed (checkboxes, radio lists, dropdown, multi-dropdown).
	 */
	public static function connection( $post, $name, $options ) {

		if ( !isset( $options['connection_type'] ) )
			return;

		// get p2p object
		$connection = p2p_type( $options['connection_type'] );

		if ( !$connection ) {
			echo '<span class="howto">Connection type not found.</span>';
			return;
		}

		// we want direction (from/to) to be the same side as the current post type.

		$direction = $connection->direction_from_types( 'post', $post->post_type );
		$directed = $connection->set_direction( $direction );

		if ( $directed === false )
			return;

		// backend - show meta box
		if ( is_admin() ) {

			if ( !isset( $args['admin_box'] ) )
				$args['admin_box'] = array();

			// set up basic box args needed for P2P_Box
			$box_args = array(
				'show' => true, // yeah, the point is to show it
				'context' => 'side', // doesn't matter
				'priority' => 'default', // doesn't matter
				'can_create_post' => false
			);

			$title_class = str_replace( 'P2P_Side_', 'P2P_Field_Title_', get_class( $directed->get( 'opposite', 'side' ) ) );

			$box_columns = array(
				'delete' => new P2P_Field_Delete,
				'title' => new $title_class( $directed->get( 'opposite', 'labels' )->singular_name )
			);
			foreach ( $directed->fields as $key => $data ) {
				$box_columns[ 'meta-' . $key ] = new P2P_Field_Generic( $key, $data );
			}
			if ( $orderby_key = $directed->get_orderby_key() ) {
				$box_columns['order'] = new P2P_Field_Order( $orderby_key );
			}

			$p2p_box = new P2P_Box( (object) $box_args, $box_columns, $directed );

			$p2p_box->render( $post );

		// frontend - show simpler form elements
		} else {

			// determine what to get
			$opposite = $directed->get( 'opposite', 'side' );
			$existing_vars = $opposite->query_vars;

			// order and orderby
			$extra_vars = array( 'orderby', 'order' );
			foreach( $extra_vars as $var ) {
				if ( isset( $options[$var] ) )
					$existing_vars[$var] = $options[$var];
			}

			// don't limit
			$existing_vars['nopaging'] = true;

			// get them
			if ( simian_is_user_connection( $connection ) ) {
				$all_items = get_users( $existing_vars );
			} else {
				$existing_vars['suppress_filters'] = false;
				$existing_vars['post_status'] = 'any';
				$all_items = get_posts( $existing_vars );
			}

			// check for new $_POST data
			$existing = isset( $_POST['simian_conn'][$options['connection_type']] ) ? $_POST['simian_conn'][$options['connection_type']] : '';
			if ( $existing ) {

				// format array of ids as if it were array of posts
				$new_existing = array();
				foreach( $existing as $item ) {
					$class = new stdClass;
					$class->ID = absint( $item );
					$new_existing[] = $class;
				}
				$existing = $new_existing;

			// if no new $_POST data, get connections from post itself
			} else {

				$existing_vars['connected_type'] = $options['connection_type'];
				$existing_vars['connected_items'] = $post;
				$existing_vars['connected_query'] = array( 'post_status' => 'any' );

				if ( simian_is_user_connection( $connection ) ) {
					$existing = get_users( $existing_vars );

				} else {
					$existing = get_posts( $existing_vars );

				}

			}

			// display them
			if ( !isset( $options['display'] ) ) $options['display'] = 'dropdown';
			switch( $options['display'] ) {

				case 'dropdown' :

					// get connected id
					if ( $existing ) {
						$selected = array_shift( $existing );
						$existing_id = $selected->ID;
					} else {
						$existing_id = 0;
					}

					?><select name="simian_conn[<?php echo $options['connection_type']; ?>][]">
						<option value="0">&mdash; Select &mdash;</option><?php
					foreach( $all_items as $item ) {
						if ( isset( $item->display_name ) )
							$title = $item->display_name;
						else
							$title = $item->post_title;
						?><option value="<?php echo $item->ID; ?>" <?php selected( $item->ID, $existing_id ); ?>><?php echo $title; ?></option><?php
					}
					?></select><?php
					break;

				case 'checkboxes' :

					// get connected ids
					$selecteds = array();
					foreach( $existing as $selected ) {
						$selecteds[] = $selected->ID;
					}

					$on_top = array();
					$on_bottom = array();

					?><input type="hidden" name="simian_conn[<?php echo $options['connection_type']; ?>][]" value="0" /><?php // this is just for empties ?>

					<div class="<?php if ( count( $all_items ) >= 10 ) echo 'simian-options-list'; else echo 'simian-options-list-short'; ?>">
						<ul id="<?php echo $options['connection_type']; ?>-checklist" class="simian-checklist simian-conn-checklist"><?php
							foreach( $all_items as $item ) {
								if ( isset( $item->display_name ) )
									$title = $item->display_name;
								else
									$title = $item->post_title;

								if ( in_array( $item->ID, $selecteds ) )
									$on_top[] = '<li id="' . $options['connection_type'] . '-' . $item->ID . '"><label for="in-' . $options['connection_type'] . '-' . $item->ID . '"><input id="in-' . $options['connection_type'] . '-' . $item->ID . '" type="checkbox" name="simian_conn[' . $options['connection_type'] . '][]" value="' . $item->ID . '" checked="checked" /> ' . $title . '</label></li>';
								else
									$on_bottom[] = '<li id="' . $options['connection_type'] . '-' . $item->ID . '"><label for="in-' . $options['connection_type'] . '-' . $item->ID . '"><input id="in-' . $options['connection_type'] . '-' . $item->ID . '" type="checkbox" name="simian_conn[' . $options['connection_type'] . '][]" value="' . $item->ID . '" /> ' . $title . '</label></li>';

							}
							echo implode( "\n", $on_top );
							echo implode( "\n", $on_bottom ); ?>
						</ul>
					</div><?php

					break;

				case 'radio' :

					// get connected id
					if ( $existing ) {
						$selected = array_shift( $existing );
						$existing_id = $selected->ID;
					} else {
						$existing_id = 0;
					}

					$on_top = '';
					$on_bottom = array();

					?><input type="hidden" name="simian_conn[<?php echo $options['connection_type']; ?>][]" value="0" /><?php // this is just for empties ?>

					<div class="<?php if ( count( $all_items ) >= 10 ) echo 'simian-options-list'; else echo 'simian-options-list-short'; ?>">
						<ul id="<?php echo $options['connection_type']; ?>-radio-list" class="simian-radio-list simian-conn-radio-list"><?php
							foreach( $all_items as $item ) {
								if ( isset( $item->display_name ) )
									$title = $item->display_name;
								else
									$title = $item->post_title;

								if ( $item->ID == $existing_id )
									$on_top = '<li id="' . $options['connection_type'] . '-' . $item->ID . '"><label for="in-' . $options['connection_type'] . '-' . $item->ID . '"><input id="in-' . $options['connection_type'] . '-' . $item->ID . '" class="simian-radio" type="radio" name="simian_conn[' . $options['connection_type'] . '][]" value="' . $item->ID . '" checked="checked" /> ' . $title . '</label></li>';
								else
									$on_bottom[] = '<li id="' . $options['connection_type'] . '-' . $item->ID . '"><label for="in-' . $options['connection_type'] . '-' . $item->ID . '"><input id="in-' . $options['connection_type'] . '-' . $item->ID . '" class="simian-radio" type="radio" name="simian_conn[' . $options['connection_type'] . '][]" value="' . $item->ID . '" /> ' . $title . '</label></li>';

							}
							echo $on_top;
							echo implode( "\n", $on_bottom ); ?>
						</ul>
					</div><?php

					break;

			}

		}

	}


	/**
	 * List (taxonomy) field.
	 *
	 * For this field type, the first parameter is sent the $post object.
	 */
	public static function taxonomy( $post, $name, $options ) {

		// backwards compat
		if ( !isset( $options['taxonomy'] ) ) $options['taxonomy'] = '';
		$taxonomy = isset( $options['name'] ) ? $options['name'] : $options['taxonomy'];

		$display = isset( $options['display'] ) ? $options['display'] : 'dropdown';

		$empty_option = isset( $options['empty_option'] ) ? sanitize_text_field( $options['empty_option'] ) : '&mdash; Select &mdash;';

		$tax = get_taxonomy( $taxonomy );
		if( !$tax ) {
			echo '<p class="howto">This field\'s taxonomy does not exist. <a href="admin.php?page=simian-fieldset">Edit this field here.</a></p>';
			return;
		}

		if ( $tax->hierarchical )
			$name = 'tax_input';
		else
			$name = 'simian_tag_input';

		// order
		$orderby = isset( $options['orderby'] ) ? sanitize_key( $options['orderby'] ) : 'name';
		$order   = isset( $options['order'] )   ? strtoupper( sanitize_key( $options['order'] ) ) : 'ASC';

		// check for new $_POST data
		$post_terms = isset( $_POST[$name][$options['taxonomy']] ) ? $_POST[$name][$options['taxonomy']] : '';
		if ( $post_terms ) {

			// will either be int or array of ints
			if ( is_array( $post_terms ) )
				array_walk( $post_terms, 'absint' );
			else
				$post_terms = array( (int) $post_terms );

		// if none, get terms from existing $post
		} else {

			$post_terms = $post ? wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) ) : array();

		}

		// Check for terms, display friendly message if none
		$all_terms = get_terms( $taxonomy, array( 'hide_empty' => 0, 'fields' => 'ids' ) );
		if ( empty( $all_terms ) ) {
			$post_type = is_object( $post ) ? $post->post_type : 'post';
			?><span class="howto">
				No <?php echo $tax->labels->name; ?> to choose from.
				<?php if ( current_user_can( 'manage_categories' ) && is_admin() ) { ?><a target="_blank" href="<?php echo admin_url(); ?>edit-tags.php?taxonomy=<?php echo $taxonomy; ?>&amp;post_type=<?php echo $post_type; ?>">Create some here.</a><?php } ?>
			</span><?php
			return;
		}

		// All displays will push selected items to top
		// Dropdown and Checkboxes respect hierarchy, Radio doesn't
		switch( $display ) {

			case 'dropdown' :

				wp_dropdown_categories( array(
					'taxonomy' => $taxonomy,
					'hide_empty' => 0,
					'name' => $name . '[' . $taxonomy . '][]',
					'id' => $taxonomy . 'dropdown',
					'class' => 'simian-dropdown list:' . $taxonomy,
					'orderby' => $orderby,
					'order' => $order,
					'hierarchical' => 1,
					'show_option_none' => $empty_option,
					'selected' => array_shift( $post_terms )
				) );

				break;

			case 'checkboxes' :
				?><input type="hidden" name="<?php echo $name; ?>[<?php echo $taxonomy; ?>][]" value="0" />
				<ul id="<?php echo $taxonomy; ?>checklist" data-wp-lists="list:<?php echo $taxonomy?>" class="categorychecklist form-no-clear"><?php
					simian_terms_checklist( $post->ID, $taxonomy, $orderby, $order );
				?></ul><?php
				break;

			// radio buttons
			case 'radio' :
				?><input type="hidden" name="<?php echo $name; ?>[<?php echo $taxonomy; ?>][]" value="0" />
				<ul id="<?php echo $taxonomy; ?>checklist" data-wp-lists="list:<?php echo $taxonomy?>" class="categorychecklist form-no-clear"><?php
					ob_start();
					simian_terms_checklist( $post->ID, $taxonomy, $orderby, $order );
					$checklist = ob_get_clean();
					// instead of running the walker, let's keep it simple
					echo str_replace( 'checkbox', 'radio', $checklist );
				?></ul><?php
				break;

		}

	}


	/**
	 * File upload field. Uses Plupload for the upload magic.
	 * See Simian_Uploads for set of helper classes.
	 */
	public static function file( $value, $name, $options ) {

		$id = simian_sanitize_name( $name );

		// get attachment IDs
		$attachment_ids = $value;
		if ( !is_array( $attachment_ids ) )
			$attachment_ids = array( $attachment_ids );

		// get attachment URLs
		$urls = array();
		foreach( $attachment_ids as $aid ) {
			$urls[] = wp_get_attachment_url( $aid );
		}

		// implode
		$attachment_ids = implode( ',', $attachment_ids );
		$urls = implode( ',', $urls );

		$multiple = true;
		$width    = null;
		$height   = null;

		// Hidden field where attachment IDs are placed
		?><input type="hidden" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $attachment_ids ); ?>" /><?php

		// Hidden field where attachment URLs are placed
		?><input type="hidden" name="<?php echo esc_attr( $name ); ?>-urls" id="<?php echo esc_attr( $id ); ?>-urls" value="<?php echo esc_attr( $urls ); ?>" /><?php

		// Hidden field - allowed files
		?><input type="hidden" class="simian-file-amount" name="<?php echo esc_attr( $name ); ?>-amount" id="<?php echo esc_attr( $id ); ?>-amount" value="<?php echo isset( $options['max_files'] ) ? (int) $options['max_files'] : 0; ?>" /><?php

		// Uploader container
		?><div id="<?php echo esc_attr( $id ); ?>-plupload-upload-ui" class="plupload-upload-uic hide-if-no-js <?php if ( $multiple ) { ?>plupload-upload-uic-multiple<?php } ?>">

			<!-- Button -->
			<input id="<?php echo $id; ?>-plupload-browse-button" type="button" value="Upload" class="button" />

			<!-- Nonce -->
			<span class="ajax_nonce_span" id="ajax_nonce_span-<?php echo wp_create_nonce( $id . '-plupload_nonce' ); ?>"></span>

			<?php if ( $width && $height ) { ?>
				<span class="plupload-resize"></span><span class="plupload-width" id="plupload-width-<?php echo $width; ?>"></span>
				<span class="plupload-height" id="plupload-height-<?php echo $height; ?>"></span>
			<?php } ?>
			<div class="filelist"></div>
		</div>

		<!-- Thumbs container -->
		<div class="plupload-thumbs <?php if ( $multiple ) { ?>plupload-thumbs-multiple<?php } ?>" id="<?php echo $id; ?>-plupload-thumbs">
		</div>
		<div class="clear"></div><?php

	}


	/**
	 * True or false field.
	 */
	public static function bool( $value, $name, $options ) {

		$id = simian_sanitize_name( $name );

		// if val = '', it has not yet been set, so check for default.
		if ( $value === '' && isset( $options['default'] ) )
			$value = (bool) $options['default'];

		// now set val to true or false regardless of above.
		$value = (bool) $value;

		$defaults = array(
			'true_label' => 'Yes',
			'false_label' => 'No',
			'display' => 'checkbox'
		);
		$options = wp_parse_args( $options, $defaults );
		extract( $options );

		switch( $display ) {

			case 'checkbox' :
				?><label for="<?php echo esc_attr( $id ); ?>">
					<input
						type="checkbox"
						class="simian-bool simian-checkbox"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="1"
						<?php if ( $value )
							checked( $value, 1 ); ?>
					/>
					<?php echo $true_label; ?>
				</label><?php
				break;

			case 'radio' :

				$current_value = $value ? $value : 0;

				foreach( array( 1 => $true_label, 0 => $false_label ) as $value => $label ) {
					?><label for="<?php echo esc_attr( $id ); ?>_<?php echo $value; ?>">
						<input
							type="radio"
							class="simian-bool simian-radio"
							id="<?php echo esc_attr( $id ); ?>_<?php echo $value; ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo $value; ?>"
							<?php checked( $current_value, $value ); ?>
						/>
						<?php echo $label; ?>&nbsp;&nbsp;
					</label><?php
				}
				break;

			case 'dropdown' :
				?><select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="simian-bool simian-dropdown">
					<option value="1" <?php if ( $value ) selected( $value, true ); ?>>
						<?php echo $true_label; ?>
					</option>
					<option value="0" <?php if ( $value ) selected( $value, false ); ?>>
						<?php echo $false_label; ?>
					</option>
				</select><?php
				break;

		}

	}


	/**
	 * Date/timepicker field.
	 *
	 * Date format is according to jQuery UI date formats, not PHP date formats.
	 */
	public static function datetime( $value, $name, $options ) {

		$id = simian_sanitize_name( $name );

		$defaults = array(
			'select'      => 'both' // date, time, or both
		);
		$options = wp_parse_args( $options, $defaults );

		if( $options['select'] == 'date' ) {
			$class = 'simian-datepicker';
			$format = 'Y-m-d';
		} elseif ( $options['select'] == 'time' ) {
			$class = 'simian-timepicker';
			$format = 'h:i a';
		} else {
			$class = 'simian-datetimepicker';
			$format = 'Y-m-d h:i a';
		}

		?><input
			type="text"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			class="<?php echo $class; ?> simian-datetime"
			<?php if ( $value ) { ?>
			value="<?php echo esc_attr( date( $format, strtotime( $value ) ) ); ?>"
			<?php } else { ?>
			value=""
			<?php } ?>
		/><?php

	}


	/**
	 * Instructional field.
	 */
	public static function instructions( $value, $name, $options ) {
		// nothing needs to go here - label and note are already shown
	}


	/**
	 * Generic list cut into dropdown, checkboxes, or radio buttons.
	 */
	public static function manual_list( $value, $name, $options ) {

		$id = simian_sanitize_name( $name );

		$display = isset( $options['display'] ) ? $options['display'] : 'dropdown';
		$empty_option = isset( $options['empty_option'] ) ? $options['empty_option'] : '';
		$values = isset( $options['values'] ) ? (array) $options['values'] : array();

		switch( $display ) {

			case 'dropdown' :
				?><select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="simian-manual-list"><?php
				if ( $empty_option ) {
					?><option value=""><?php echo $empty_option; ?></option><?php
				}
				foreach( $values as $key => $label ) {
					if ( is_int( $key ) )
						$key = $label;
					?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_attr( stripslashes( $label ) ); ?></option><?php
				}
				?></select><?php
				break;

			case 'checkboxes' :
			case 'radio' :
				?><div class="simian-manual-checklist"><?php
					foreach( $values as $key => $label ) {
						if ( is_int( $key ) )
							$key = $label;
						?><label for="<?php echo esc_attr( $id . '-' . $key ); ?>">
							<input
								type="<?php echo $display == 'radio' ? 'radio' : 'checkbox'; ?>"
								id="<?php echo esc_attr( $id . '-' . $key ); ?>"
								name="<?php echo esc_attr( $name ); echo $display == 'radio' ? '' : '[]'; ?>"
								value="<?php echo esc_attr( $key ); ?>"
								<?php checked( in_array( $key, (array) $value ) ); ?>
							/>
							<?php echo esc_html( stripslashes( $label ) ); ?>
						</label><?php
					}
				?></div><?php
				break;

		}

	}


	/**
	 * Core post field. Not meant to be used in the backend where there's already a UI
	 * for all these. Frontend submission forms, on the other end, may find these useful.
	 */
	public static function core( $value, $name, $options ) {

		// no need for $id since only the whitelist below is accepted

		// name must be one of a few
		$accepted = array(
			'post_title',
			'post_content',
			'post_excerpt',
			'post_type',
			'post_status',
			'menu_order',
			'comment_status',
			'ping_status',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_parent',
			'post_password'
		);

		if ( !in_array( $name, $accepted ) )
			return;

		switch( $name ) {

			case 'post_type' :
				?><select name="<?php echo $name; ?>" id="<?php echo $name; ?>" class="simian-dropdown">
					<?php foreach( get_post_types() as $post_type ) {
						$post_type_obj = get_post_type_object( $post_type );
						?><option value="<?php echo $post_type; ?>" <?php selected( $value, $post_type ); ?>><?php echo $post_type_obj->labels->name; ?></option><?php
					} ?>
				</select><?php
				break;

			case 'post_status' :

				// specified statuses
				if( isset( $options['values'] ) ) {
					if ( $options['values'] ) $statuses = $options['values'];
				}

				// general statuses
				if ( !isset( $statuses ) ) {
					$statuses = array();
					global $wp_post_statuses;
					foreach( $wp_post_statuses as $key => $stat ) {
						if ( $stat->show_in_admin_all_list ) {
							$statuses[$key] = $stat->label;
						}
					}
				}

				?><select name="<?php echo $name; ?>" id="<?php echo $name; ?>" class="simian-dropdown">
					<?php foreach( $statuses as $name => $label ) {
						?><option value="<?php echo $name; ?>" <?php selected( $value, $name ); ?>><?php echo $label; ?></option><?php
					} ?>
				</select><?php
				break;

			case 'post_title' :
				self::text( $value, $name, $options );
				break;

			case 'menu_order' :
				self::text( $value, $name, array( 'data_type' => 'integer' ) );
				break;

			case 'post_content' :
				self::longtext( $value, $name, array( 'display' => 'wysiwyg' ) );
				break;

			case 'post_excerpt' :
				self::longtext( $value, $name, array( 'display' => 'textarea' ) );
				break;

			case 'comment_status' :
			case 'ping_status' :
				?><select name="<?php echo $name; ?>" id="<?php echo $name; ?>" class="simian-dropdown">
					<option value="open" <?php selected( $value, 'open' ); ?>>Open</option>
					<option value="closed" <?php selected( $value, 'closed' ); ?>>Closed</option>
				</select><?php
				break;

			case 'post_author' :
				wp_dropdown_users( array(
					'name'     => $name,
					'id'       => $name,
					'class'    => 'simian-dropdown',
					'selected' => $value
				) );
				break;

			case 'post_date' :
			case 'post_date_gmt' :
				self::datetime( $value, $name, array( 'select' => 'both' ) );
				break;

			case 'post_parent' :
				wp_dropdown_pages( array(
					'post_type' => $options['post_type'],
					'name' => $name,
					'selected' => $value
				) );
				break;

			case 'post_password' :
				self::text( $value, $name, $options );
				break;

		}

	}


	/**
	 * Deprecated fields below. Do not delete.
	 * @deprecated
	 ********************************************************************
	 */
	public static function wysiwyg( $value, $name, $options = array() ) {
		$options['display'] = 'wysiwyg';
		self::longtext( $value, $name, $options );
	}
	public static function textarea( $value, $name, $options = array() ) {
		$options['display'] = 'textarea';
		self::longtext( $value, $name, $options );
	}
	public static function url( $value, $name, $options = array() ) {
		$options['data_type'] = 'url';
		self::text( $value, $name, $options );
	}
	public static function date( $value, $name, $options = array() ) {
		$options['select'] = 'date';
		self::datetime( $value, $name, $options );
	}


}