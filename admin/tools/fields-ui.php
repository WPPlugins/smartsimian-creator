<?php

/**
 * Fields UI for editing content types. Generates default row and any extra rules.
 * Uses the Simian_Repeater.
 *
 * Init function runs automatically, which sets up the ajax hook.
 */
class Simian_Fields_UI {


	/**
	 * Custom fields can be passed through here.
	 * @var array
	 */
	public $fields;


	/**
	 * Init ajax hooks.
	 */
	static public function init() {

		// load options column
		add_action( 'wp_ajax_simian_content_field_options', array( __CLASS__, 'options_ajax' ) );

		// load Sync With dropdown
		add_action( 'wp_ajax_simian_connection_sync', array( __CLASS__, 'connection_sync_ajax' ) );

	}


	/**
	 * Before values are sent through the repeater and back to row() below, format any that need
	 * formatting so that the UI can output them properly.
	 *
	 * Called from Simian_Admin_Fields.
	 */
	static public function format_values( $values = array() ) {

		/* foreach( $values as $index => $field ) {
			if ( isset( $field['options']['file_types'] ) ) {

				$extensions = array();
				$mimes = $field['options']['file_types'];
				foreach( $mimes as $mime ) {
					$ext = array_search( $mime, wp_get_mime_types() );
					if ( $ext ) {
						$exts = explode( '|', $ext );
						$extensions = array_merge( $extensions, $exts );
					}
				}
				$values[$index]['options']['file_types'] = implode( ', ', array_unique( $extensions ) );

			}
		} */

		return $values;
	}


	/**
	 * Default row. Receives id, key, value, and row count from the Repeater. For the Fields UI
	 * the key is irrelevant.  ID is to differentiate between multiple Field UIs on the same page if necessary.
	 *
	 * @note $id doesn't really do anything here. If there are actually going to be multiple Field UIs on the same
	 * page then would be better to take the Query UI approach and not have an all-static class. Can create
	 * separate Field UI instances.
	 */
	public function row( $id = '', $item = '', $type = null, $value = array(), $count = 0, $extra_args = array() ) {

		if ( is_null( $this->fields ) ) {
			$this->fields = array(
				'text'         => 'Text',
				'rich_text'    => 'Rich Text Box',
				'longtext'     => 'Basic Text Box',
				'bool'         => 'True or False',
				'link'         => 'Link',
				'file'         => 'File Uploader',
				'datetime'     => 'Date/Time',
				'taxonomy'     => 'Taxonomy',
				'connection'   => 'Connection',
				'instructions' => 'Instructions'
			);
		}

		$name = 'fields[' . $count . ']';
		$value = wp_parse_args( $value, array(
			'name'    => '',
			'label'   => '',
			'type'    => '',
			'note'    => '',
			'options' => array()
		) );

		// backwards compatibility for wysiwygs
		// (text box type with an option for rich text is now its own rich text type)
		if ( $value['type'] == 'longtext' ) {
			$longtext_display = isset( $value['options']['display'] ) ? $value['options']['display'] : '';
			if ( $longtext_display == 'wysiwyg' )
				$value['type'] = 'rich_text';
		}

		?><div class="simian-field-repeater-col simian-field-repeater-label">
			<input class="simian-field-label" type="text" placeholder="Label" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( stripslashes( $value['label'] ) ); ?>" />
		</div>
		<div class="simian-field-repeater-col simian-field-repeater-type"><?php
			?><select name="<?php echo $name; ?>[type]">
				<option value="">Select Field Type</option><?php
				foreach( $this->fields as $type_value => $type_label ) {
					?><option value="<?php echo $type_value; ?>" <?php selected( $type_value, $value['type'] ); ?>><?php echo esc_attr( stripslashes( $type_label ) ); ?></label><?php
				}
			?></select>
		</div>
		<div class="simian-field-repeater-col simian-field-repeater-options simian-repeater-options"><?php
			if ( !$value['type'] ) {
				?><div class="simian-faded">Select a field type to view additional options</div><?php
			} else {
				$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
				$component = str_replace( 'simian-', '', $page );
				self::load_callback( $value['type'], $name, $value, $component );
			} ?>
		</div><?php

	}


	/**
	 * Ajax callback function for displaying the options column.
	 */
	static public function options_ajax() {

		$type = sanitize_text_field( $_REQUEST['type'] );
		$name = sanitize_text_field( $_REQUEST['name'] );
		$component = isset( $_REQUEST['component'] ) ? sanitize_key( $_REQUEST['component'] ) : '';

		// ajax might also send values (so far this is just used by submissions)
		$value = isset( $_REQUEST['values'] ) ? $_REQUEST['values'] : array();
		if ( !$value )
			$value = array();

		self::load_callback( $type, $name, $value, $component );

		exit();

	}


	/**
	 * Load field type callback function and Notes field.
	 */
	static public function load_callback( $type, $name, $value = array(), $component = '', $existing_flag = false ) {

		?><div class="simian-options-toggle right"><a href="#">Show Options</a></div>
		<div class="simian-options-showhide" style="display:none;"><?php

			$value = wp_parse_args( $value, array(
				'name' => '',
				'note' => '',
				'options' => array()
			) );

			// custom options by type
			if ( is_callable( array( __CLASS__, $type . '_options' ) ) )
				call_user_func( array( __CLASS__, $type . '_options' ), $name . '[options]', $value['options'] );

			// action to add options for specific field types
			do_action( 'simian_field_ui_options-' . $type, $name . '[options]', $value['options'], $component );

			// action to add options to all fields
			do_action( 'simian_field_ui_general_options', $name . '[options]', $value['options'], $type, $component );

			// set system name except for instructions, taxonomies, and connections
			if ( !in_array( $type, array( 'instructions', 'taxonomy', 'connection' ) ) ) {

				$name_class = !$value['name'] ? 'simian-apply-name' : '';
				$sysname_args = array(
					'class' => $name_class . ' simian-sysname'
				);

				// if inheriting the field, don't allow sysname to be edited (currently only useful for submissions)
				if ( $existing_flag )
					$sysname_args['disabled'] = true;

				self::build_option( 'name', 'text', 'System Name', $name, $value, $sysname_args );

			}

			if ( $type == 'instructions' )
				$place = 'Enter instructions here.';
			else
				$place = 'Enter additional notes or instructions here.';

			// set notes
			?><textarea name="<?php echo $name; ?>[note]" rows="1" cols="35" placeholder="<?php echo $place; ?>"><?php
				echo esc_textarea( stripslashes( $value['note'] ) );
			?></textarea>

		</div><!-- .simian-options-showhide --><?php

	}


	/**
	 * Get item or return false on add new.
	 */
	static public function get_item() {
		return isset( $_GET['item'] ) ? sanitize_key( $_GET['item'] ) : false;
	}


	/**
	 * Build an option field.
	 */
	static public function build_option( $id = '', $type = 'text', $label = '', $name = '', $value = array(), $args = array() ) {

		$args = wp_parse_args( $args, array(
			'note' => '',
			'tooltip' => '',
			'class' => '',
			'empty_option' => '',
			'select_options' => array(),
			'placeholder' => '',
			'disabled' => false,
			'checked' => false,
			'multiple' => false,
			'bool_label' => '',
			'hide' => false
		) );

		?><div class="option-container <?php echo sanitize_key( $id ); ?>-container"<?php echo $args['hide'] ? ' style="display:none;"' : ''; ?>>
			<span class="option-label"><?php echo esc_html( $label ); if ( $args['tooltip'] ) { echo '&nbsp;'; simian_tooltip( $args['tooltip'] ); } ?></span><?php
				call_user_func( array( __CLASS__, 'build_' . $type . '_option' ), $id, $name, $value, $args );
			?><div class="simian-clear"></div><?php
				if ( $args['note'] ) {
					?><div class="option-note"><?php echo $args['note']; ?></div><?php
				}
		?></div><?php
	}


	/**
	 * Build the text option input.
	 */
	static public function build_text_option( $id, $name, $value, $args ) {
		?><input
			type="text"
			name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $id ); ?>]"
			value="<?php echo esc_attr( stripslashes( $value[$id] ) ); ?>"
			<?php echo $args['class'] ? 'class="' . esc_attr( $args['class'] ) . '"' : ''; ?>
			<?php echo $args['disabled'] ? 'disabled="disabled"' : ''; ?>
			<?php echo $args['placeholder'] ? 'placeholder="' . esc_attr( $args['placeholder'] ) . '"' : ''; ?>
		/><?php
	}


	/**
	 * Build the textarea option.
	 */
	static public function build_textarea_option( $id, $name, $value, $args = array() ) {
		?><textarea name="<?php echo $name; ?>[<?php echo esc_attr( $id ); ?>]"><?php
			echo esc_textarea( stripslashes( $value[$id] ) );
		?></textarea><?php
	}


	/**
	 * Build the bool option input.
	 */
	static public function build_bool_option( $id, $name, $value, $args ) {
		?><label for="<?php echo esc_attr( $name . '-' . $id ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $name . '-' . $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $id ); ?>]"
				value="1"
				<?php echo $args['class'] ? 'class="' . esc_attr( $args['class'] ) . '"' : ''; ?>
				<?php echo $args['disabled'] ? 'disabled="disabled"' : ''; ?>
				<?php checked( $args['checked'] ); ?>
			/>
			<?php echo $args['bool_label']; ?>
		</label><?php
	}


	/**
	 * Build the select option input.
	 */
	static public function build_select_option( $id, $name, $value, $args ) {
		?><select <?php echo $args['multiple'] ? 'multiple="multiple"' : ''; ?> <?php echo $args['class'] ? 'class="' . esc_attr( $args['class'] ) . '" ' : ' '; ?>name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $id ); ?>]<?php echo $args['multiple'] ? '[]' : ''; ?>" <?php echo $args['placeholder'] ? 'placeholder="' . esc_attr( $args['placeholder'] ) . '" ' : ''; ?>><?php

			if ( $args['empty_option'] ) {
				?><option value=""><?php echo esc_attr( stripslashes( $args['empty_option'] ) ); ?></option><?php
			}

			foreach( $args['select_options'] as $option_value => $option_label ) {

				// optgroup support
				if ( is_array( $option_label ) ) {
					?><optgroup label="<?php echo esc_attr( stripslashes( $option_value ) ); ?>"><?php
					foreach( $option_label as $opt_value => $opt_label ) {
						$selected = '';
						if ( $args['multiple'] && is_array( $value[$id] ) )
							$selected = selected( in_array( $opt_value, $value[$id] ), true, false );
						else
							$selected = selected( $opt_value, $value[$id], false );
						?><option value="<?php echo $opt_value ? $opt_value : ''; ?>" <?php echo $selected; ?>><?php echo esc_attr( stripslashes( $opt_label ) ); ?></label><?php
					}
					?></optgroup><?php

				} else {

					$selected = '';
					if ( $args['multiple'] && is_array( $value[$id] ) )
						$selected = selected( in_array( $option_value, $value[$id] ), true, false );
					else
						$selected = selected( $option_value, $value[$id], false );

					?><option value="<?php echo $option_value ? $option_value : ''; ?>" <?php echo $selected; ?>><?php echo esc_attr( stripslashes( $option_label ) ); ?></label><?php

				}
			}
		?></select><?php
	}


	/**
	 * Options column for text fields.
	 */
	static public function text_options( $name, $value = array() ) {

		$value = wp_parse_args( $value, array(
			'data_type'   => 'default',
			'placeholder' => '',
			'min'         => '',
			'max'         => '',
			'step'        => ''
		) );

		self::build_option( 'placeholder', 'text', 'Placeholder Text', $name, $value );
		self::build_option( 'data_type', 'select', 'Data Type', $name, $value, array(
			'class' => 'data-type-select',
			'select_options' => array(
				'default' => 'Basic Text',
				'email'   => 'Email Address',
				'url'     => 'URL',
				'address' => 'Physical Address',
				'phone'   => 'Phone Number',
				'dollar'  => 'Dollar Amount',
				'integer' => 'Integer',
				'decimal' => 'Decimal'
			)
		) );

		?><div class="num-options"<?php echo ( $value['data_type'] == 'integer' || $value['data_type'] == 'decimal' ) ? '' : ' style="display:none;"'; ?>><?php
			self::build_option( 'min', 'text', 'Minimum #', $name, $value );
			self::build_option( 'max', 'text', 'Maximum #', $name, $value );
			self::build_option( 'step', 'text', 'Interval', $name, $value );
		?></div><?php

	}


	/**
	 * Options column for rich_text fields.
	 */
	static public function rich_text_options( $name, $value = array() ) {
		self::build_all_textboxes( $name, $value );
	}


	/**
	 * Options column for longtext fields.
	 */
	static public function longtext_options( $name, $value = array() ) {
		self::build_all_textboxes( $name, $value );
	}


	/**
	 * Rich text and longtext fields.
	 */
	static public function build_all_textboxes( $name, $value ) {
		$value = wp_parse_args( $value, array(
			'placeholder' => '',
			'rows'        => 10
		) );
		self::build_option( 'placeholder', 'text', 'Placeholder text', $name, $value );
		self::build_option( 'rows', 'text', 'Rows', $name, $value, array(
			'tooltip' => 'Increase the height of the box by raising this number.'
		) );
	}


	/**
	 * Options column for bool fields.
	 */
	static public function bool_options( $name, $value = array() ) {

		$value = wp_parse_args( $value, array(
			'display'     => 'checkbox',
			'true_label'  => 'Yes',
			'false_label' => 'No'
		) );

		self::build_option( 'display', 'select', 'Type', $name, $value, array(
			'class' => 'display-type-select',
			'select_options' => array(
				'checkbox' => 'Checkbox',
				'radio'    => 'Radio Buttons',
				'dropdown' => 'Dropdown Menu'
			)
		) );
		self::build_option( 'true_label', 'text', 'True Label', $name, $value );
		self::build_option( 'false_label', 'text', 'False Label', $name, $value, array(
			'disabled' => ( $value['display'] == 'checkbox' || !$value['display'] ) ? true : false
		) );

	}


	/**
	 * Options column for file fields.
	 */
	static public function file_options( $name, $value = array() ) {

		// format mime type list
		$select_options = array_flip( wp_get_mime_types() );
		foreach( $select_options as $mime => $ext ) {
			$select_options[$mime] = str_replace( '|', ' / ', $ext );
		}

		// get server max mb
		$max_size = ( (int) wp_max_upload_size() / 1024 ) / 1024;

		$value = wp_parse_args( $value, array(
			'max_files'  => 1,
			'file_size'  => 5,
			'file_types' => ''
		) );

		self::build_option( 'max_files', 'text', 'Max Files', $name, $value );
		self::build_option( 'file_size', 'text', 'Max File Size (MB)', $name, $value, array(
			'note' => 'Server Limit: ' . $max_size
		) );
		self::build_option( 'file_types', 'select', 'Allowed Extensions', $name, $value, array(
			'note' => 'Leave empty for all',
			'class' => 'select2 file-type-select2',
			'placeholder' => 'Select',
			'empty_option' => false,
			'multiple' => true,
			'select_options' => $select_options
		) );

	}


	/**
	 * Options column for datetime fields.
	 */
	static public function datetime_options( $name, $value = array() ) {

		$value = wp_parse_args( $value, array(
			'select' => 'both'
		) );

		self::build_option( 'select', 'select', 'Type', $name, $value, array( 'select_options' => array(
			'datetime' => 'Date & Time',
			'date'     => 'Just Date',
			'time'     => 'Just Time'
		) ) );

	}


	/**
	 * Options column for taxonomy fields.
	 */
	static public function taxonomy_options( $name, $value = array() ) {

		if ( !isset( $value['empty_option'] ) )
			$value['empty_option'] = '&mdash; Select &mdash;';

		$value = wp_parse_args( $value, array(
			'taxonomy'     => '',
			'options'      => '',
			'display'      => 'dropdown',
			'empty_option' => '',
			'orderby'      => 'id',
			'order'        => 'asc'
		) );

		// check if taxonomy is valid
		if ( !taxonomy_exists( $value['taxonomy'] ) )
			$value['taxonomy'] = '';

		// list existing
		$taxonomies = get_taxonomies( array(), 'objects' );
		$tax_list = array();
		foreach( $taxonomies as $tax ) {
			$tax_list[$tax->name] = $tax->labels->name;
		}
		asort( $tax_list );

		// add "Add New" to the top
		$tax_list = array_merge( array( '_add_new' => 'Add New' ), $tax_list );

		self::build_option( 'taxonomy', 'select', 'Taxonomy', $name, $value, array(
			'select_options' => $tax_list,
			'note' => ( !$value['taxonomy'] || $value['taxonomy'] === '_add_new' ) ? '' : '' // '<a target="_blank" href="edit-tags.php?taxonomy=' . $value['taxonomy'] . '">Manage terms here.</a>',
		) );

		$value['terms'] = '';
		self::build_option( 'terms', 'textarea', 'Terms', $name, $value, array(
			'hide'    => ( !$value['taxonomy'] || $value['taxonomy'] === '_add_new' ) ? false : true,
			'tooltip' => 'Add your initial terms for this taxonomy here, one per line.'
		) );

		self::build_option( 'display', 'select', 'Display', $name, $value, array(
			'class' => 'simian-if-changed',
			'select_options' => array(
				'dropdown'   => 'Dropdown Menu',
				'checkboxes' => 'Checklist',
				'radio'      => 'Radio Buttons'
			)
		) );

		?><div class="simian-hidden-box" title="dropdown"<?php echo ( $value['display'] === 'dropdown' ) ? '' : ' style="display:none;"'; ?>><?php
			self::build_option( 'empty_option', 'text', 'Dropdown Label', $name, $value );
		?></div><?php

		self::build_option( 'orderby', 'select', 'Order By', $name, $value, array( 'select_options' => array(
			'id'    => 'ID (Order Created)',
			'name'  => 'Name',
			'count' => 'Popularity',
			'slug'  => 'Slug'
		) ) );
		self::build_option( 'order', 'select', 'Order', $name, $value, array( 'select_options' => array(
			'asc'  => 'Ascending',
			'desc' => 'Descending'
		) ) );

	}


	/**
	 * Options column for connection fields.
	 */
	static public function connection_options( $name, $value = array() ) {

		$value = simian_determine_connection_values( $value );

		self::build_option( 'connect_to_type', 'select', 'Connect to', $name, $value, array(
			'empty_option' => 'Select',
			'class' => 'simian-if-changed connect-to-type',
			'select_options' => array(
				'content' => 'Content',
				'user' => 'Users'
			)
		) );

		?><div class="simian-hidden-box simian-clear-on-hide" title="content"<?php simian_hide_if( !$value['connection_type'] || ( $value['connect_to_type'] === 'user' ) ); ?>><?php

			self::build_option( 'connect_to', 'select', '', $name, $value, array(
				'empty_option' => '',
				'placeholder' => 'Select content type(s)',
				'class' => 'connect-to select2',
				'multiple' => true,
				'select_options' => simian_get_content_types()
			) );

		?></div><?php

		?><div class="connect-to-box"<?php simian_hide_if( !$value['connection_type'] ); ?>><?php

			if ( $value['connection_type'] )
				self::connection_sync( $name, $value );

		?></div><?php

	}


	/**
	 * Grab ajax data.
	 */
	static function connection_sync_ajax() {
		$name  = isset( $_REQUEST['name'] ) ? sanitize_text_field( $_REQUEST['name'] ) : '';
		$value = array(
			'connect_to' => isset( $_REQUEST['connect_to'] ) ? array_map( 'sanitize_key', (array) $_REQUEST['connect_to'] ) : array()
		);
		self::connection_sync( $name, $value );
		exit;
	}


	/**
	 * Display Sync With option.
	 */
	static function connection_sync( $name, $value = array() ) {

		$value = wp_parse_args( $value, array(
			'connect_to'      => array(),
			'connection_type' => ''
		) );

		$types = simian_list_connection_types( array( '' => 'None (new connection)' ) );

		// limit to connection types involving selected object
		if ( $value['connect_to'] ) {
			foreach( $types as $type => $label ) {
				if ( $type === '' ) continue;
				if ( !simian_connection_has_object_type( $type, (array) $value['connect_to'] ) )
					unset( $types[$type] );
			}
		}

		self::build_option( 'connection_type', 'select', 'Sync with', $name, $value, array(
			'select_options' => $types
		) );

	}


	static public function link_options( $name, $value = array() ) { /* no options */ }
	static public function instructions_options( $name, $value = array() ) { /* no options */ }


}
Simian_Fields_UI::init();