<?php
/**
 * Extension of the Simian_Fields class used for backend forms.
 *
 * Whereas Simian Fields is built for $post info, Simian_Admin_Fields pulls $_POST or saved Simian data.
 *
 * Sample implementation:
 * Simian_Admin_Fields::init( 'content', 'div', $fields );
 */
class Simian_Admin_Fields extends Simian_Fields {


	/**
	 * Override the init() class. Get current component, container element, and importantly $fields array.
	 */
	public static function init( $component, $element, $fields = '' ) {

		// sanitize $fields
		foreach( (array) $fields as $key => $field ) {

			// ignore empties
			if ( !array_filter( $field ) ) {
				unset( $fields[$key] );
				continue;
			}

			// whitelisted args
			$defaults = array(
				'name'      => '',
				'label'     => '',
				'type'      => '',
				'note'      => '',
				'options'   => array(),
			);
			$fields[$key] = wp_parse_args( $field, $defaults );

		}

		// reindex
		$fields = array_values( $fields );

		// get table or list container, pass current component
		call_user_func( array( __CLASS__, $element . '_container' ), $component, $fields, __CLASS__ );

	}


	/**
	 * Override the output wrapper function. Get value, call correct field type function.
	 */
	public static function output_field( $component, $field ) {

		$type = $field['type'];
		$name = $field['name'];
		if ( $type == 'ghost' ) return;

		// normalize options
		$defaults = array(
			'true_label'  => 'Yes',
			'false_label' => 'No',
			'default'     => false,
			'args'        => array(),
			'find'        => array(),
			'all_values'         => false
		);
		$options = wp_parse_args( $field['options'], $defaults );

		$value = '';

		// attempt to find a value from page data sent back after an error
		global $simian_single_page_data;
		if ( $simian_single_page_data ) {
			if ( $options['all_values'] )
				$value = $simian_single_page_data;
			else
				$value = simian_get_form_value( $simian_single_page_data, $name, $options );
		}

		// attempt to find a value from $_POST data
		if ( $value === '' ) {
			if ( $options['all_values'] )
				$value = !empty( $_POST ) ? $_POST : '';
			else
				$value = simian_get_form_value( $_POST, $name, $options );
		}

		// attempt to find a value from existing item
		if ( $value === '' && isset( $_GET['item'] ) ) {
			$item = simian_get_item( sanitize_key( $_GET['item'] ), $component );
			if ( $item ) {
				if ( $options['all_values'] )
					$value = $item;
				else
					$value = simian_get_form_value( $item, $name, $options );
			}
		}

		// fix: if there's no 'label', default to sysname
		// fieldset is allowed to not have a title
		if ( !$value && $name == 'label' && isset( $_GET['item'] ) && $component != 'fieldset' )
			$value = sanitize_title( $_GET['item'] );

		// fix: if no taxonomy hierarchy is specified (deprecated behavior) set to true
		if ( isset( $_GET['item'] ) && $component == 'taxonomy' && $name == 'hierarchy' && $value === '' )
			$value = true;

		// attempt to find a value from the default setting
		if ( $value === '' && $options['default'] )
			$value = $options['default'];

		// return the field with output buffering
		ob_start();
		if ( method_exists( __CLASS__, $type ) )
			call_user_func( array( __CLASS__, $type ), $value, $name, $options );
		else
			echo apply_filters( 'simian_admin_fields-' . $type, $value, $name, $options );
		$returned = ob_get_clean();

		// filter output of any existing field type before echoing
		echo apply_filters( 'simian_admin_field_' . $type, $returned, $name, $options );

	}


	/**
	 * Field for big-style labels.
	 */
	public static function big_labels( $value, $name, $options ) {

		if ( !isset( $options['args'] ) )
			$options['args'] = array( $name => '' );

		foreach( $options['args'] as $name => $placeholder ) {

			if ( is_array( $value ) ) {
				if ( !isset( $value[$name] ) )
					$value[$name] = '';
			}

			?><input
				type="text"
				maxlength="32"
				id="<?php echo simian_sanitize_name( $name ); ?>"
				name="<?php echo $name; ?>"
				class="simian-text"
				value="<?php echo ( is_array( $value ) ) ? esc_attr( stripslashes( $value[$name] ) ) : esc_attr( stripslashes( $value ) ); ?>"
				placeholder="<?php echo $placeholder; ?>"
			/><?php
		}

	}


	/**
	 * List of registered post types.
	 * It says checklist, but it's a dropdown.
	 */
	public static function post_type_checklist( $value, $name, $options ) {

		$id = simian_sanitize_name( $name );

		$placeholder = isset( $options['select'] ) ? ( $options['select'] == 'multi' ? 'Content Types' : 'Select Content Type' ) : 'Content Types';

		$options = wp_parse_args( $options, array(
			'select' => 'multi', // multi or single
			'limit' => false, // false or 'custom'
			'include_user' => false,
			'placeholder' => $placeholder
		) );

		if ( !is_array( $value ) ) $value = array( $value );
		$checked = array();
		$unchecked = array();

		if ( $options['limit'] == 'custom' ) {

			// get custom only
			$types = array();
			foreach( simian_get_data( 'content' ) as $ct_name => $ct_array ) {
				$types[$ct_name] = get_post_type_object( $ct_name );
			}

		} else {

			// get all
			$types = get_post_types( array(), 'objects' );

		}

		ksort( $types );

		?><select id="<?php echo $id; ?>" name="<?php echo $name; ?><?php echo ( $options['select'] == 'multi' ) ? '[]' : ''; ?>" <?php echo ( $options['select'] == 'multi' ) ? 'class="select2 ' . $id . '" multiple="multiple" placeholder="' . $options['placeholder'] . '"' : 'class="' . $id . '"'; ?>><?php
			echo ( $options['select'] != 'multi' ) ? '<option value="">' . $options['placeholder'] . '</option>' : '';
			foreach( $types as $type ) {
				?><option value="<?php echo $type->name; ?>" <?php selected( in_array( $type->name, $value ) ); ?>><?php echo esc_html( stripslashes( $type->labels->name ) ); ?></option><?php
			}
			if ( $options['include_user'] ) { ?><option value="user" <?php selected( in_array( 'user', $value ) ); ?>>Users</option><?php }
		?></select><?php

	}


	/**
	 * Field to set the taxonomy slug.
	 */
	public static function taxonomy_rewrite( $value, $name, $options ) {

		$id = simian_sanitize_name( $name );

		$bool_value = (bool) $value;

		$defaults = array(
			'true_label' => 'Yes',
			'false_label' => 'No',
			'display' => 'checkbox'
		);
		$options = wp_parse_args( $options, $defaults );
		extract( $options );

		// class used for generating slug from singular label, only when box starts out unchecked
		$apply_name_class = '';
		if ( !$bool_value )
			$apply_name_class = 'simian-apply-name';

		?><div class="taxonomy_rewrite">

			<label for="<?php echo esc_attr( $id ); ?>" class="simian-if-checked">
				<input
					type="checkbox"
					class="simian-bool simian-checkbox"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>_checkbox"
					value="1"
					<?php if ( $bool_value )
						checked( $bool_value, 1 ); ?>
				/>
				<?php echo $true_label; ?>
			</label>

			<p class="simian-hidden-box" <?php echo !$bool_value ? 'style="display:none;"' : ''; ?>>
				at
				<code><?php echo home_url(); ?>/</code>
				<input type="text" class="<?php echo $apply_name_class; ?> simian-url-input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo $value; ?>" />
				<code>/sample-term</code>
			</p>

		</div><?php

	}


	/**
	 * Dashboard content options.
	 */
	public static function dashboard_options( $value, $name, $options ) {

		$value = wp_parse_args( $value, array(
			'show_ui' => false,
			'menu_icon' => 'post'
		) );

		?><div class="content-visibility simian-manual-checklist">
			<label for="show_ui" class="simian-if-checked">
				<input type="checkbox" class="simian-bool simian-checkbox" id="show_ui" name="<?php echo $name; ?>[show_ui]" value="1" <?php checked( $value['show_ui'] ); ?> />
				Show in dashboard
			</label><?php

			$icons = simian_get_content_type_icons();

			// new icons
			if ( simian_is_using_dashicons() ) {

				$icon_value = isset( $icons[$value['menu_icon']] ) ? $icons[$value['menu_icon']] : '';
				if ( !$icon_value ) {
					$icon_value = 'admin-post';
					$value['menu_icon'] = 'f109';
				}

				?><div class="simian-hidden-box"<?php echo $value['show_ui'] ? '' : ' style="display:none;"'; ?>>

					<input type="hidden" name="<?php echo $name; ?>[menu_icon]" value="<?php echo $value['menu_icon']; ?>" />

					Icon: <a href="#" class="dashicons dashicons-<?php echo esc_attr( $icon_value ); ?>"></a>
					<span class="button menu-icon-inline-dialog-open">Change</span>

					<div class="menu-icon-inline-dialog" title="Select Icon" style="display:none;"><?php
						foreach( $icons as $icon_id => $icon_name ) {
							?><a href="#" alt="<?php echo esc_attr( $icon_id ); ?>" title="<?php echo esc_attr( $icon_name ); ?>" class="dashicons dashicons-<?php echo esc_attr( $icon_name ); ?>"></a><?php
						}
					?></div>

				</div><?php

			} else {

				?><div class="simian-hidden-box"<?php echo $value['show_ui'] ? '' : ' style="display:none;"'; ?>>
					<div class="icon-radio-boxes">
						<div class="icon-radio-boxes-label">Select menu icon:</div><?php

						foreach( $icons as $icon_name => $icon_args ) {

							$src = isset( $icon_args['loc'] ) ? $icon_args['loc'] : false;

							$icon_args = wp_parse_args( $icon_args, array(
								'loc' => admin_url( 'images/menu.png' )
							) );

							?><label for="simian-<?php echo $icon_name; ?>"<?php echo ( $value['menu_icon'] == $icon_name ) ? ' class="icon-highlighted"' : ''; ?>>
								<input id="simian-<?php echo $icon_name; ?>" type="radio" name="<?php echo $name; ?>[menu_icon]" value="<?php echo $icon_name; ?>" <?php checked( $value['menu_icon'], $icon_name ); ?> />
								<div class="menu-icon-display menu-icon-<?php echo $icon_name; ?>" <?php echo $src ? 'style="background:url(\'' . $src . '\') 6px 6px no-repeat;"' : ''; ?>></div>
							</label><?php

						}
						?><div class="simian-clear"></div>
					</div>
				</div><?php

			}


		?></div><!-- .content-visibility --><?php

	}


	/**
	 * Public content visibility options.
	 */
	public static function public_options( $value, $name, $options ){

		$id = simian_sanitize_name( $name );

		$value = wp_parse_args( $value, array(
			'public' => false,
			'slug' => '',
			'has_archive' => false,
			'has_archive_slug' => '',
			'include_in_search' => false
		) );

		?><div class="content-visibility simian-manual-checklist">

			<label for="publicly_queryable" class="simian-if-checked">
				<input type="checkbox" class="simian-bool simian-checkbox" id="publicly_queryable" name="<?php echo $name; ?>[public]" value="1" <?php checked( $value['public'] ); ?> />
				Generate a public page for each entry
			</label>
			<div class="simian-hidden-box"<?php echo $value['public'] ? '' : ' style="display:none;"'; ?>>

				<div class="single-page-slug">
					at
					<code><?php echo home_url(); ?>/</code>
					<input type="text" class="<?php echo !$value['public'] ? 'simian-apply-name' : ''; ?> simian-url-input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo $name; ?>[slug]" value="<?php echo $value['slug']; ?>" />
					<code>/sample-entry</code>
				</div>

				<label for="has_archive" class="simian-if-checked">
					<input type="checkbox" class="simian-bool simian-checkbox" id="has_archive" name="<?php echo $name; ?>[has_archive]" value="1" <?php checked( $value['has_archive'] ); ?> />
					Generate an archive page that links to all entries
				</label>
				<div class="simian-hidden-box"<?php echo $value['has_archive'] ? '' : ' style="display:none;"'; ?>>
					<div class="archive-page-slug">
						at
						<code><?php echo home_url(); ?>/</code>
						<input type="text" class="<?php echo !$value['has_archive'] ? 'simian-apply-name-plural' : ''; ?> simian-url-input" id="has_archive_slug" name="<?php echo $name; ?>[has_archive_slug]" value="<?php echo $value['has_archive_slug']; ?>" />
					</div>
				</div>

				<label for="include_in_search">
					<input type="checkbox" class="simian-bool simian-checkbox" id="include_in_search" name="<?php echo $name; ?>[include_in_search]" value="1" <?php checked( $value['include_in_search'] ); ?> />
					Allow entries to appear in the general site search
				</label>

			</div>
		</div><!-- .content-visibility --><?php

	}


	/**
	 * Set 'from' and 'to' connections. A select2 dropdown of objects.
	 * Also a narrowing slideout for extra query args.
	 *
	 * $value will be a p2p-formatted arg array.
	 */
	public static function query_creator( $value, $name, $options ) {

		$id = simian_sanitize_name( $name );

		// get options
		$options = wp_parse_args( $options, array(
			'empty_label' => 'Select',
			'narrow_name' => 'narrow'
		) );

		// define side
		if ( !isset( $value[$name] ) )
			$value[$name] = array();

		$value[$name] = array_filter( (array) $value[$name] );

		// define side_query_vars
		if ( !isset( $value[$options['narrow_name']] ) )
			$value[$options['narrow_name']] = '';

		$value_is_user = in_array( 'user', (array) $value[$name] );
		$value_is_content = ( !$value_is_user && $value[$name] );

		?><select class="simian_content_or_user" style="min-width:100px;">
			<option value="">Select</option>
			<option value="content" <?php selected( $value_is_content ); ?>>Content</option>
			<option value="user" <?php selected( $value_is_user ); ?>>Users</option>
		</select> <?php

		// get objects to select
		$objects = get_post_types( array(), 'objects' );
		ksort( $objects );

		?><select class="select2 query-ui-object-dropdown" multiple="multiple" placeholder="Select content type(s)" name="<?php echo esc_attr( $name ); ?>[object][]" <?php echo $value_is_content ? '' : ' style="display:none;"'; ?>>
			<?php foreach( $objects as $object ) {
				?><option value="<?php echo $object->name; ?>" <?php selected( in_array( $object->name, (array) $value[$name] ) ); ?>><?php echo esc_html( stripslashes( $object->labels->name ) ); ?></option><?php
			} ?>
		</select> <?php

		if ( $value_is_user ) {
			?><input class="query-ui-object-dropdown" type="hidden" name="<?php echo esc_attr( $name ); ?>[object][]" value="user" /><?php
		}

		?><span id="narrow-<?php echo $id; ?>" class="simian-narrow-further"<?php echo $value[$name] ? ' style="display:inline;"' : ''; ?>><a class="button-secondary" href="#">Narrow...</a></span><?php

		// check for existing narrowing rules
		if ( $value[$options['narrow_name']] ) {

			// output repeater, send rows to prepopulate
			simian_generate_query_ui( $value[$name], $name, $value[$options['narrow_name']], false, false );

		}

	}


	/**
	 * Query Creator when forcing a single post type.
	 * No content/user dropdown, no Select2.
	 */
	public static function single_query_creator( $value, $name, $options, $dynamic = false ) {

		$id = simian_sanitize_name( $name );

		// get options
		$options = wp_parse_args( $options, array(
			'empty_label' => 'Select',
			'narrow_name' => 'narrow'
		) );

		// get values
		/* $value = wp_parse_args( $value, array(
			'type' => '', // post type or 'user'
		) ); */
		if ( !isset( $value[$options['narrow_name']] ) )
			$value[$options['narrow_name']] = '';
		if ( !isset( $value[$name] ) )
			$value[$name] = '';

		// get objects to select
		$objects = get_post_types( array(), 'objects' );
		ksort( $objects );
		$user = new stdClass();
		$user->name = 'user';
		$user->labels = new stdClass();
		$user->labels->name = 'Users';
		$objects[] = $user;

		?><select class="<?php echo $dynamic ? 'show-dynamic ' : ''; ?>query-ui-object-dropdown" name="<?php echo esc_attr( $name ); ?>[object]">
			<option value=""><?php echo $options['empty_label']; ?></option>
			<?php foreach( $objects as $object ) {
				?><option value="<?php echo $object->name; ?>" <?php selected( $value[$name], $object->name ); ?>><?php echo esc_html( stripslashes( $object->labels->name ) ); ?></option><?php
			} ?>
		</select>

		<span id="narrow-<?php echo $id; ?>" class="simian-narrow-further"<?php echo $value[$name] ? ' style="display:inline;"' : ''; ?>><a class="button-secondary" href="#">Narrow...</a></span><?php

		// check for existing narrowing rules
		if ( $value[$options['narrow_name']] ) {

			// output repeater, send rows to prepopulate
			simian_generate_query_ui( $value[$name], $name, $value[$options['narrow_name']], false, $dynamic );

		}

		?><div class="simian-reveal"<?php echo $value[$name] ? ' style="display:block;"' : ''; ?>></div><?php

	}


	/**
	 * Connection picker. Uses query_creator.
	 */
	public static function connection_picker( $value, $name, $options ) {

		// show query creator
		self::query_creator( $value, $name, $options );

	}


	/**
	 * Generate the field repeater meta box.
	 */
	public static function field_repeater( $value, $name, $options ) {

		$options = wp_parse_args( $options, array(
			'fields' => array()
		) );

		$fields_ui = new Simian_Fields_UI();

		if ( $options['fields'] )
			$fields_ui->fields = $options['fields'];

		// instantiate repeater
		$repeater = new Simian_Repeater( array(
			'sort'      => true,
			'display'   => true,
			'callback'  => array( $fields_ui, 'row' ),
			'add_class' => 'button-secondary',
			'add_label' => 'Add Field'
		) );

		// output fields headings & list
		?><ul class="field-repeater-headings simian-repeater-headings">
			<li class="sort"><span>Sort</span></li>
			<li class="label"><span>Label</span></li>
			<li class="type"><span>Type</span></li>
			<li class="options">
				<span>Options</span>
				<div class="simian-toggle-all-options">
					<a class="expand" href="#">Expand All</a> / <a class="collapse" href="#">Collapse All</a>
				</div>
			</li>
			<li class="delete"><span>Delete</span></li>
		</ul><?php

		// grab current component
		$component = isset( $_GET['page'] ) ? $_GET['page'] : '';
		$component = str_replace( 'simian-', '', $component );

		?><div id="<?php echo $component; ?>-field-repeater" class="simian-field-repeater"><?php

			// format values
			$value = Simian_Fields_UI::format_values( $value );

			// generate fields and send values
			$repeater->html( $value );

		?></div><?php

	}


	/**
	 * Title template. Construct titles from field or custom.
	 */
	/* public static function title_slug_template( $value, $name, $options ) {

		$id = simian_sanitize_name( $name );

		// get fields
		$item = isset( $_GET['item'] ) ? sanitize_key( $_GET['item'] ) : '';
		$fields = array();
		if ( $item )
			$fields = simian_get_fields( $item );

		?><select class="<?php echo $id; ?>-dropdown simian-text-template-dropdown" name="<?php echo $name; ?>_dropdown">
			<option value="">Select Field</option><?php
			if ( $fields ) {
				foreach( $fields as $field ) {

					// disqualify certain fields
					if ( !in_array( $field['type'], array( 'text', 'datetime' ) ) )
						continue;

					?><option value="<?php echo $field['name']; ?>"><?php echo esc_html( stripslashes( $field['label'] ) ); ?></option><?php
				}
			}
		?></select>
		<input class="simian-text-template" type="text" name="<?php echo $name; ?>" value="<?php echo $value; ?>" />
		<?php
	} *?


	public static function connection_narrowing() {}


	/**
	 * Display a dropdown of items from a component.
	 */
	static public function list_items( $value, $name, $options ) {

		extract( wp_parse_args( $options, array(
			'component' => 'content',
			'label'     => 'Select',
			'labels'    => 'label'
		) ) );

		// get all items
		$items = simian_get_data( $component );

		?><select name="<?php echo esc_attr( $name ); ?>">
			<option value=""><?php echo esc_html( $label ); ?></option><?php
			foreach( $items as $sysname => $array ) {
				?><option value="<?php echo $sysname; ?>" <?php selected( $sysname, $value ); ?>><?php echo isset( $array[$labels] ) ? esc_html( stripslashes( $array[$labels] ) ) : $sysname; ?></option><?php
			}
		?></select><?php

	}


	/**
	 * Display the content type components.
	 *
	 * Also includes options for setting title and slug if normal title is not set.
	 */
	static public function content_type_components( $value, $name, $options ) {

		// if no value has been set yet, even an empty array
		if ( $value === '' )
			$value = array(
				'components' => array( 'title', 'editor', 'author' )
			);

		// set various values handled by this field
		$value = wp_parse_args( $value, array(
			'components'     => array(),
			'title_template' => '',
			'slug_template'  => ''
		) );

		// default features
		$options = wp_parse_args( $options, array(
			'values' => array(
				'title'           => 'Title field',
				'editor'          => 'Content box',
				'author'          => 'Author',
				'excerpt'         => 'Excerpt box',
				'comments'        => 'Comments',
				'thumbnail'       => 'Featured image',
				'revisions'       => 'Revisions',
				'page-attributes' => 'Sub-pages and ordering'
			)
		) );

		$id = simian_sanitize_name( $name );

		?><div class="simian-manual-checklist"><?php
			foreach( $options['values'] as $key => $label ) {

				if ( is_int( $key ) )
					$key = $label;

				?><label for="<?php echo esc_attr( $id . '-' . $key ); ?>"<?php echo $key == 'title' ? ' class="simian-if-unchecked"' : ''; ?>>
					<input
						type="checkbox"
						id="<?php echo esc_attr( $id . '-' . $key ); ?>"
						name="components[]"
						value="<?php echo esc_attr( $key ); ?>"
						<?php checked( in_array( $key, (array) $value['components'] ) ); ?>
					/>
					<?php echo $label; ?>
				</label><?php

				// special handling of title
				if ( $key == 'title' ) {
					?><div class="simian-hidden-box simian-clear-on-hide simian-indented-box"<?php echo in_array( $key, (array) $value['components'] ) ? ' style="display:none;"' : ''; ?>><?php
						self::title_slug_options( array(
							'title_template' => $value['title_template'],
							'slug_template' => $value['slug_template']
						) );
					?></div><?php
				}

			}
		?></div><?php

	}


	/**
	 * Display options if the default title field is unchecked.
	 */
	static private function title_slug_options( $value ) {

		$value = wp_parse_args( $value, array(
			'title_template' => '',
			'slug_template'  => ''
		) );

		?><p>Titles are displayed by themes and in the dashboard, so choose what to use as the title instead:</p>
		<div class="simian-title-candidates"><?php
			self::title_slug_candidates( $value['title_template'], 'title_template', 'Custom Title' );
		?></div>

		<label for="simian-also-use-for-url" class="simian-if-unchecked">
			<input id="simian-also-use-for-url" type="checkbox" value="1" <?php checked( !$value['slug_template'] ); ?> />
			Also use when generating URL slug
		</label>
		<div class="simian-slug-candidates simian-hidden-box simian-clear-on-hide" <?php echo !$value['slug_template'] ? ' style="display:none;"' : ''; ?>><?php
			self::title_slug_candidates( $value['slug_template'], 'slug_template', 'Custom Slug' );
		?></div><?php

	}


	/**
	 * Display options for setting the title or slug.
	 */
	static private function title_slug_candidates( $value, $name, $label = 'Select' ) {

		// handle back-compat

		// if template == '': first available field
		// if template doesn't have brackets: check if meta key, if so, wrap brackets around

		// get fields
		$item = isset( $_GET['item'] ) ? sanitize_key( $_GET['item'] ) : '';
		$fields = array();
		if ( $item )
			$fields = simian_get_fields( $item );

		// value for dropdowns
		$dropval = trim( $value, '[] ' );

		?><select class="simian-show-custom-input" name="<?php echo $name; ?>_field">
			<option value=""><?php echo $label; ?></option>
			<optgroup label="Fields"><?php
			if ( $fields ) {
				$first = true;
				foreach( $fields as $field ) {
					if ( !in_array( $field['type'], array( 'text', 'datetime' ) ) )
						continue;

					// back-compat:
					// -if value is blank, use first field
					// -if value is identical to a field name, use that field
					if ( ( $first && $value === '' ) || ( $value === $field['name'] ) )
						$value = '[' . $field['name'] . ']';

					$first = false;

					?><option value="<?php echo $field['name']; ?>" <?php selected( $field['name'], $dropval ); ?>><?php echo esc_html( stripslashes( $field['label'] ) ); ?></option><?php
				}
			} else {
				?><option value="" disabled="disabled">No fields yet.</option><?php
			}
			?></optgroup>
			<optgroup label="Other"><?php
				$other_values = array(
					'post_date' => 'Date Published',
					'post_date_gmt' => 'Date Published (GMT)',
					'post_modified' => 'Date Modified',
					'post_modified_gmt' => 'Date Modified (GMT)',
					'ID' => 'Post ID'
				);
				foreach( $other_values as $other_v => $other_l ) {
					?><option value="<?php echo $other_v; ?>" <?php selected( $other_v, $dropval ); ?>><?php echo $other_l; ?></options><?php
				}
			?></optgroup>
		</select>

		<input type="text" name="<?php echo $name; ?>" value="<?php echo esc_html( stripslashes( $value ) ); ?>" /><?php

	}


}