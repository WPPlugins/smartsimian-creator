<?php
/**
 * Simian Query UI. A system that allows users to create complex WP_Query and WP_User_Query args.
 * Each new instantiation is a separate query UI instance, but many methods are static (ajax callbacks,
 * form generation, etc).
 */
class Simian_Query_UI {


	/**
	 * Current row's objects: user or post types.
	 * @var array
	 */
	public $objects;


	/**
	 * Current row's allowed query rules.
	 */
	public $rules;


	/**
	 * When selecting specific posts, terms, authors, field values, etc., show or hide option
	 * to use the global post to get those values.
	 *
	 * @var bool
	 */
	public $dynamic;


	/**
	 * Init ajax hooks. Static, and runs automatically.
	 */
	static public function init() {
		add_action( 'wp_ajax_simian_query_ui_inner', array( __CLASS__, 'inner_ajax' ) );
		add_action( 'wp_ajax_simian_select2_search', array( __CLASS__, 'select2_ajax_load' ) );
	}


	/**
	 * Constructor.
	 */
	public function __construct( $objects = array(), $rules = array(), $dynamic = false ) {

		// define object properties
		$this->objects         = (array) $objects;
		$this->rules           = $rules;
		$this->dynamic = $dynamic;

		// use default rules if necessary
		if ( empty( $this->rules ) && in_array( 'user', $this->objects ) )
			$this->rules = array(
				'role',
				'meta_query',
				'search',
				'connections',
				'specific_items'
			);
		elseif( empty( $this->rules ) && !in_array( 'user', $this->objects ) )
			$this->rules = array(
				'post_type',
				'post_status',
				'author',
				'published',
				'post_parent',
				'meta_query',
				'tax_query',
				'connections',
				'specific_items'
			);

	}


	/**
	 * Before the new Simian_Repeater instance is created, retrieve values and reformat
	 * for this class to recognize. Specifically: we receive an array formatted into
	 * WP_Query or WP_User_Query args, and we need to convert them to Query_UI rows.
	 */
	static public function format_values( $values = array(), $use_post_type = false ) {

		$rules = array();

		// cast existing query var values into $rules array format
		if ( $values ) {

			$default_args = array( 'post_status', 'author', 'post_parent', 'meta_query', 'tax_query', 'role', 'search' );

			if ( $use_post_type )
				$default_args[] = 'post_type';

			foreach( $default_args as $arg ) {
				if ( isset( $values[$arg] ) ) {
					$rules[$arg] = $values[$arg];
				}
			}

			$published_args = array( 'year', 'monthnum', 'w', 'day', 'hour', 'minute', 'second' );
			foreach( $published_args as $arg ) {
				if ( isset( $values[$arg] ) ) {
					if ( !isset( $rules['published'] ) ) $rules['published'] = array();
					$rules['published'][$arg] = $values[$arg];
				}
			}

			$connection_args = array( 'connected_type', 'connected_items', 'connected_direction' );
			foreach( $connection_args as $arg ) {
				if ( isset( $values[$arg] ) ) {
					if ( !isset( $rules['connections'] ) ) $rules['connections'] = array();
					$shortened_arg = str_replace( 'connected_', '', $arg );
					$rules['connections'][$shortened_arg] = $values[$arg];
				}
			}

			$clude_args = array( 'include', 'exclude', 'post__in', 'post__not_in' );
			foreach( $clude_args as $arg ) {
				if ( isset( $values[$arg] ) ) {
					$rules['specific_items'] = array(
						'clude' => $arg,
						'items' => $values[$arg]
					);
				}
			}

		}

		if ( !empty( $rules ) )
			return $rules;

	}


	/**
	 * Row display. Values have been sent to the Simian Repeater instance, and a row has been created.
	 * Simian Repeater then returns to us to populate the row.
	 *
	 * @param $id		Unique slug for the repeater instance.
	 * @param $key   	If a prepopulated row, this is the value key sent along with the value.
	 * @param $value	Values sent to this row to prepopulate.
	 * @param $counter	The row index number.
	 */
	public function row( $id, $item = '', $key = '', $value = '', $counter = 0, $extra_args = array() ) {

		// show default first section of row
		?><select class="<?php echo $this->dynamic ? 'show-dynamic ' : ''; ?>simian-narrow-by" name="<?php echo $id; ?>[narrow][<?php echo $counter; ?>][rule]">
			<option value="">Select Rule</option><?php

			// labels to match to allowed rules
			$labels = array(
				'post_type'      => 'Content Type',
				'post_status'    => 'Status',
				'published'      => 'Published Date',
				'post_parent'    => 'Parent',
				'author'         => 'Author',
				'specific_items' => 'Specific Items',
				'role'           => 'Role',
				'search'         => 'Search Phrase',
				'meta_query'     => 'Custom Field',
				'tax_query'      => 'Taxonomy',
				'connections'    => 'Direct Connections',
			);

			// build rule dropdown
			foreach( $this->rules as $rule_item ) {
				?><option value="<?php echo $rule_item; ?>" <?php selected( $rule_item, $key ); ?>><?php echo $labels[$rule_item]; ?></option><?php
			}

		?></select><?php

		// if $key (rule slug) and $value were sent, this is a populated row, so show the rest
		if ( $key && $value ) {
			?><div class="simian-rule"><?php
				$name = $id . '[narrow][' . $counter . '][' . $key . ']';
				call_user_func( array( __CLASS__, $key . '_rule' ), $name, $value, $this->objects, '', $this->dynamic );
			?></div><?php
		}

	}


	/**
	 * Ajax callback function for loading the inner Query UI rule HTML.
	 */
	static public function inner_ajax() {

		// build form field name
		$name = sanitize_key( $_REQUEST['id'] ) . '[narrow][' . (int) $_REQUEST['counter'] . '][' . sanitize_key( $_REQUEST['rule'] ) . ']';

		// call appropriate function
		call_user_func(
			array( __CLASS__, sanitize_key( $_REQUEST['func'] ) ),
			$name,
			null,
			isset( $_REQUEST['objects'] ) ? array_map( 'sanitize_key', (array) $_REQUEST['objects'] ) : null,
			isset( $_REQUEST['inner_obj'] ) ? sanitize_key( $_REQUEST['inner_obj'] ) : null,
			isset( $_REQUEST['dynamic'] ) ? (bool) $_REQUEST['dynamic'] : false
		);

		exit();

	}


	/**
	 * Post type multi-select.
	 */
	static public function post_type_rule( $name, $value = array(), $objects = array() ) {
		$value = wp_parse_args( $value, array() );
		?><select class="select2" multiple="multiple" name="<?php echo $name; ?>[]"><?php
			foreach( get_post_types( array(), 'objects' ) as $post_type ) {
				?><option value="<?php echo $post_type->name; ?>" <?php selected( in_array( $post_type->name, $value ) ); ?>>
					<?php echo $post_type->labels->name; ?>
				</option><?php
			}
		?></select><?php
	}


	/**
	 * Post status multi-select.
	 */
	static public function post_status_rule( $name, $value = array(), $objects = array() ) {
		global $wp_post_statuses;
		$value = wp_parse_args( $value, array() );
		?><select class="select2" multiple="multiple" name="<?php echo $name; ?>[]" placeholder="Select status"><?php
			foreach( $wp_post_statuses as $post_status => $obj ) {
				if ( $obj->show_in_admin_status_list ) {
					?><option value="<?php echo $post_status; ?>" <?php selected( in_array( $post_status, $value ) ); ?>>
						<?php echo $obj->label; ?>
					</option><?php
				}
			}
		?></select><?php
	}


	/**
	 * Published data options.
	 */
	static public function published_rule( $name, $value = '', $objects = array() ) {
		$periods = array(
			'year'     => array( 'Year',   1, 9999 ),
			'monthnum' => array( 'Month',  1, 12 ),
			/* 'w'        => array( 'Week',   0, 53 ), */
			'day'      => array( 'Date',   1, 31 ),
			'hour'     => array( 'Hour',   0, 23 ),
			'minute'   => array( 'Minute', 0, 59 ),
			/* 'second'   => array( 'Second', 0, 60 ) */
		);
		foreach( $periods as $time_period => $args ) {
			?><input
				type="number"
				class="<?php echo $time_period; ?>"
				name="<?php echo $name; ?>[<?php echo $time_period; ?>]"
				value="<?php echo $value[$time_period]; ?>"
				placeholder="<?php echo $args[0]; echo ( $time_period != 'year' ) ? ' (' . $args[1] . '-' . $args[2] . ')' : ''; ?>"
				min="<?php echo $args[1]; ?>"
				max="<?php echo $args[2]; ?>"
			/><?php
		}
	}


	/**
	 * Post parent dropdown.
	 */
	static public function post_parent_rule( $name, $value = '', $objects = array(), $inner_obj = '', $dynamic = false ) {
		self::get_ajax_select( $name, $value, 'content', $objects, false, $dynamic, 'post', 'post' );
	}


	/**
	 * Post author includes/excludes multi-select.
	 */
	static public function author_rule( $name, $value = '', $objects = array(), $inner_obj = '', $dynamic = false ) {

		if ( $value ) {
			$clude = ( strpos( $value, '-' ) !== false ) ? 'exclude' : 'include';

			// convert to all-positive array
			if ( strpos( $value, '[global]' ) === false ) {
				$value = explode( ',', $value );
				$value = array_map( 'absint', $value );
			}

			$value = array(
				'clude' => $clude,
				'items' => $value
			);
		}

		self::clude_multiselect_rule( $name, $value, 'user', $dynamic, 'post\'s author', 'user' );

	}


	/**
	 * Specific content or users multi-select.
	 */
	static public function specific_items_rule( $name, $value = array(), $objects = array(), $inner_obj = '', $dynamic = false ) {
		self::clude_multiselect_rule( $name, $value, $objects, $dynamic, 'post', 'posts' );
	}


	/**
	 * Role dropdown.
	 */
	static public function role_rule( $name, $value = '', $objects = array() ) {
		?><select name="<?php echo $name; ?>">
			<option value="">Select Role</option>
			<?php global $wp_roles;
			foreach( $wp_roles->get_names() as $this_value => $label ) {
				?><option value="<?php echo $this_value; ?>" <?php selected( $value, $this_value ); ?>><?php echo esc_attr( stripslashes( $label ) ); ?></option><?php
			} ?>
		</select><?php
	}


	/**
	 * User search field.
	 */
	static public function search_rule( $name, $value = '', $objects = array() ) {
		$value = trim( $value, '*' );
		?><input type="text" name="<?php echo $name; ?>" value="<?php echo esc_attr( stripslashes( $value ) ); ?>" placeholder="Search terms" /><?php
	}


	/**
	 * Generic meta field rule for content and users.
	 */
	static public function meta_query_rule( $name, $value = array(), $objects = array(), $inner_obj = '', $dynamic = false ) {

		$defaults = array(
			'key'     => '',
			'compare' => '=',
			'value'   => ''
		);
		$value = wp_parse_args( $value, $defaults );

		?><input type="text" name="<?php echo $name; ?>[key]" value="<?php echo esc_attr( $value['key'] ); ?>" class="field-key" placeholder="Field Name" />

		<select class="field-compare" name="<?php echo $name; ?>[compare]">
			<?php $options = array(
				array( '=',               'Equal To' ),
				array( '!=',              'Not Equal To' ),
				array( '&gt;',            'Greater Than' ),
				array( '&lt;',            'Less Than' ),
				array( '&gt;=',           'Greater Than or Equal To' ),
				array( '&lt;=',           'Less Than or Equal To' ),
				array( 'LIKE',            'Like' ),
				array( 'NOT&nbsp;LIKE',   'Not Like' ),
				array( 'EXISTS',          'Field Exists (no value needed)' ),
				array( 'NOT&nbsp;EXISTS', 'Field Doesn\'t Exist (no value needed)' )
			);
			foreach( $options as $operator ) {
				?><option value="<?php echo $operator[0]; ?>" <?php selected( $value['compare'], html_entity_decode( $operator[0] ) ); ?>><?php echo $operator[1]; ?></option><?php
			} ?>
		</select><?php

		if ( $dynamic ) {
			self::dynamic_value_options( $name, $value, 'post\'s field value', 'field value' );
			if ( $value['value'] === '[global]' )
				$value['value'] = '';
		}

		?><input type="text" name="<?php echo $name; ?>[value]" value="<?php echo esc_attr( stripslashes( $value['value'] ) ); ?>" class="field-value" placeholder="Field Value" /><?php

	}


	/**
	 * Content taxonomy term fields.
	 */
	static public function tax_query_rule( $name, $value = '', $objects = array(), $inner_obj = '', $dynamic = false ) {

		$objects = (array) $objects;

		// get only relevant taxonomies if possible
		if ( empty( $objects ) )
			$taxonomies = get_taxonomies( array(), 'objects' );
		elseif ( !in_array( 'user', $objects ) )
			$taxonomies = get_object_taxonomies( $objects, 'objects' );
		else
			$taxonomies = get_taxonomies( array(), 'objects' );

		// taxonomy dropdown
		?><select class="simian-query-ui-taxonomy taxonomy-select" name="<?php echo $name; ?>[taxonomy]">
			<option value="">Select Taxonomy</option><?php
			foreach( $taxonomies as $taxonomy ) {
				?><option value="<?php echo $taxonomy->name; ?>" <?php selected( $value['taxonomy'], $taxonomy->name ); ?>><?php echo $taxonomy->labels->name; ?></option><?php
			}
		?></select><?php

		if ( array_filter( (array) $value ) ) {
			if ( !isset( $value['taxonomy'] ) ) $value['taxonomy'] = '';
			?><div class="taxonomy-rule"><?php
				self::tax_query_rule_inner( $name, $value, $objects, $value['taxonomy'], $dynamic );
			?></div><?php
		}

	}


	/**
	 * Content taxonomy term inner fields.
	 */
	static public function tax_query_rule_inner( $name, $value = array(), $objects, $taxonomy, $dynamic = false ) {

		$tax_obj = get_taxonomy( $taxonomy );
		$label   = $tax_obj->label;

		$value = wp_parse_args( $value, array(
			'taxonomy' => '',
			'operator' => '',
			'terms' => array()
		) );

		?><select name="<?php echo $name; ?>[operator]">
			<option value="IN" <?php selected( $value['operator'], 'IN' ); ?>>Includes any of...</option>
			<option value="AND" <?php selected( $value['operator'], 'AND' ); ?>>Includes all of...</option>
			<option value="NOT IN" <?php selected( $value['operator'], 'NOT IN' ); ?>>Does not include...</option>
		</select><?php

		if ( $dynamic )
			self::dynamic_value_options( $name, $value, 'post\'s ' . $label, $label );

		?><select name="<?php echo $name; ?>[terms][]" class="select2" multiple="multiple" placeholder="Select <?php echo $label; ?>"><?php
			$terms = get_terms( array( $taxonomy ), array( 'hide_empty' => false, ) );
			if ( $terms ) {
				foreach( $terms as $term ) {
					?><option value="<?php echo $term->term_id; ?>" <?php selected( in_array( $term->term_id, $value['terms'] ) ); ?>><?php echo $term->name; ?></option><?php
				}
			} else {
				?><option value="" disabled="disabled">No terms found.</option><?php
			}
		?></select><?php

	}


	/**
	 * Show radio buttons to choose from specific items or use the global post
	 * to get those items.
	 */
	static private function dynamic_value_options( $name, $value, $label1, $label2 ) {

		$id = simian_sanitize_name( $name );

		$dynamic = false;
		foreach( (array) $value as $val ) {
			if ( strpos( (string) $val, '[global]' ) !== false ) {
				$dynamic = true;
				break;
			}
		}

		?><label class="query-ui-radio specific-dynamic" for="<?php echo $id; ?>-dynamic-type-dynamic">
			<input id="<?php echo $id; ?>-dynamic-type-dynamic" type="radio" name="<?php echo $name; ?>[dynamic_type]" value="dynamic" <?php checked( $dynamic ); ?> />
			Current global <?php echo $label1; ?>
		</label>
		<label class="query-ui-radio specific-dynamic" for="<?php echo $id; ?>-dynamic-type-specific">
			<input id="<?php echo $id; ?>-dynamic-type-specific" type="radio" name="<?php echo $name; ?>[dynamic_type]" value="specific" <?php checked( !$dynamic ); ?> />
			Specific <?php echo $label2; ?>
		</label><?php

	}


	/**
	 * Connections fields - ctype and multi-select of posts/users.
	 */
	static public function connections_rule( $name, $value = '', $objects = array(), $inner_obj = '', $dynamic = false ) {

		$defaults = array(
			'type'  => '',
			'items' => array()
		);
		$value = wp_parse_args( $value, $defaults );

		// get all ctypes
		$ctypes = simian_get_data( 'connection' );
		$relevant = simian_get_object_connection_types( $objects );

		// display relevant ctypes
		?><select class="ctype" name="<?php echo $name; ?>[type]">
			<option value="">Select connection type</option><?php
			foreach( $relevant as $sysname ) {
				$ctype = $ctypes[$sysname];
				?><option value="<?php echo $sysname; ?>" <?php selected( $sysname, $value['type'] ); ?>>
					<?php echo isset( $ctype['label'] ) ? $ctype['label'] : $sysname; ?>
				</option><?php
			}
		?></select><?php

		if ( $value['items'] ) {

			?><div class="connection-rule"><?php

				if ( $dynamic )
					self::dynamic_value_options( $name, $value, 'post', 'post' );

				if ( $value['items'] === '[global]' )
					$value['items'] = array();

				$connection_type = $value['type'];
				$opposite = simian_get_connection_opposite( $connection_type, array_shift( $objects ) );

				self::get_ajax_select( $name, $value['items'], in_array( 'user', $opposite ) ? 'user' : 'content', $opposite, true );

			?></div><?php

		}

		/*
		// if existing items, display here
		if ( $value['items'] && $value['type'] ) {
			$items = array();

			// show user multi-select
			if ( $ctype[$value['type']]['from'] == 'user' && $ctype[$value['type']]['to'] == 'user' ) {

				foreach( $value['items'] as $item ) {
					$item = get_user_by( 'id', $item );
					$items[] = $item->user_login;
				}
				$items = rtrim( $items, ',' );

				// existing hidden usernames
				?><input type="hidden" class="existing-usernames" name="<?php echo $name; ?>[existing_usernames]" value="<?php echo esc_attr( $items ); ?>" /><?php

				// this triggers the select2
				?><input type="hidden" class="bigdrop select2-ajax-user" name="<?php echo $name; ?>[items]" value="<?php echo esc_attr( implode( ',', $value['items'] ) ); ?>" /><?php

			// show content multi-select
			} else {

				foreach( $value['items'] as $item ) {
					$items[] = get_the_title( $item );
				}
				$items = rtrim( $items, ',' );

				// existing hidden post titles
				?><input type="hidden" class="existing-content" name="<?php echo $name; ?>[items]" value="<?php echo esc_attr( $items ); ?>" /><?php

				// this triggers the select2
				?><input type="hidden" class="bigdrop select2-ajax-content" name="<?php echo $name; ?>[items]" value="<?php echo esc_attr( implode( ',', $value['items'] ) ); ?>" /><?php

			}

		}
		*/

		// if no items exist yet, multi-select should be generated with ajax function

	}


	/**
	 * Inner connection rule. After a connection type has been selected.
	 *
	 * This assumes a single post type (or 'user') in $objects.
	 */
	static public function connections_rule_inner( $name, $value = '', $objects = array(), $inner_obj = '', $dynamic = false ) {

		if ( $dynamic )
			self::dynamic_value_options( $name, $value, 'post', 'post' );

		$connection_type = $inner_obj;
		$opposite = simian_get_connection_opposite( $connection_type, array_shift( $objects ) );

		self::get_ajax_select(
			$name,
			$value,
			in_array( 'user', (array) $opposite ) ? 'user' : 'content',
			$opposite,
			true
		);

	}


	/**
	 * Build generic multi-select dropdown with include/exclude option.
	 */
	static public function clude_multiselect_rule( $name, $value, $objects = array(), $dynamic = false, $label1 = '', $label2 = '' ) {

		$defaults = array(
			'clude' => 'include',
			'items' => array(),
		);
		$value = wp_parse_args( $value, $defaults );

		// include/exclude
		self::clude_dropdown( $name, $value['clude'], $objects );

		// user multi-select
		self::get_ajax_select( $name, $value['items'], in_array( 'user', (array) $objects ) ? 'user' : 'content', $objects, true, $dynamic, $label1, $label2 );

	}


	/**
	 * Output include/exclude dropdown.
	 */
	static public function clude_dropdown( $name, $value, $objects = array() ) {
		$include = in_array( 'user', (array) $objects ) ? 'include' : 'post__in';
		$exclude = in_array( 'user', (array) $objects ) ? 'exclude' : 'post__not_in';

		?><select class="clude" name="<?php echo $name; ?>[clude]">
			<option value="<?php echo $include; ?>" <?php selected( $value, $include ); ?>>Includes</option>
			<option value="<?php echo $exclude; ?>" <?php selected( $value, $exclude ); ?>>Excludes</option>
		</select><?php
	}


	/**
	 * Generate an ajaxified select2 box. Can handle content, users, or terms,
	 * and multi-select or single-select.
	 *
	 * (Note: not currently being used for terms, that prepopulates. But this
	 * function is still context-neutral.)
	 */
	static public function get_ajax_select( $name, $value, $context = 'content', $types = array(), $multi = false, $dynamic = false, $label1 = '', $label2 = '' ) {

		if ( $dynamic ) {
			self::dynamic_value_options( $name, $value, $label1, $label2 );
			if ( strpos( (string) $value, '[global]' ) !== false )
				$value = '';
		}

		// if existing values
		if ( $value ) {

			// get values' labels
			if ( $multi ) {
				$label = '';
				foreach( $value as $item ) {
					$label .= self::get_item_label( $item, $context ) . ',';
				}
				$labels = rtrim( $label, ',' );
			} else {
				$labels = self::get_item_label( $value, $context );
			}

			// save labels in hidden input for select2 to read
			?><input
				type="hidden"
				class="<?php echo $context; ?>-labels"
				name="<?php echo $name . '[' . $context . '-labels]'; ?>"
				value="<?php echo esc_attr( $labels ); ?>"
			/><?php

		}

		// handle single-selects
		if ( $value && !is_array( $value ) )
			$value = array( $value );

		// hidden input for select2 to transform ?>
		<input
			title="<?php echo esc_attr( implode( ',', (array) $types ) ); ?>"
			type="hidden"
			class="bigdrop <?php echo $multi ? 'multi-select' : 'single-select'; ?> select2-ajax-search select2-ajax-<?php echo $context; ?>"
			name="<?php echo $name; ?>[items]"
			value="<?php echo $value ? esc_attr( implode( ',', $value ) ) : ''; ?>"
		/><?php

	}


	/**
	 * Get the label of an item, given the item ID and object type.
	 * ($object==term is not current in use.)
	 */
	static public function get_item_label( $id, $object = 'content' ) {

		if ( $object == 'content' ) {
			$title = get_the_title( $id );
			return $title ? $title : '(no title - id #' . $id . ')';
		}

		if ( $object == 'user' ) {
			$obj = get_user_by( 'id', $id );
			return $obj->user_login;
		}

		if ( $object == 'term' ) {
			global $wpdb;
			$name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $wpdb->terms WHERE term_id = %d", $id ) );
			return $name;
		}

	}


	/**
	 * Ajax callback. Generate list of content or users for Select2 ajax search.
	 */
	static public function select2_ajax_load(){

		// clean up data passed
		$search = sanitize_text_field( $_REQUEST['q'] );
		$count  = (int) $_REQUEST['count'];
		$offset = ( (int) $_REQUEST['page_number'] - 1 ) * $count;
		$object = ( $_REQUEST['object'] == 'content' ) ? 'content' : 'user';
		$types  = array_map( 'sanitize_key', (array) $_REQUEST['types'] );

		switch ( $object ) {

			case 'user' :

				// run user search
				$users = new WP_User_Query( array(
					'number' => $count,
					'offset' => $offset,
					'search' => '*' . $search . '*',
					'search_columns' => array( 'ID', 'user_login', 'user_nicename', 'user_email' )
				) );

				$results = array();
				foreach( $users->get_results() as $user ) {
					$results[] = array(
						'id' => $user->ID,
						'item_label' => $user->user_login
					);
				}

				$total = $users->get_total();

				break;

			case 'content' :

				// run content search
				$posts = new WP_Query( array(
					'post_type'      => $types ? $types : get_post_types(),
					'posts_per_page' => $count,
					'paged'          => (int) $_REQUEST['page_number'],
					's'              => $search
				) );

				/* // output query
				$results[] = array(
					'id' => 1,
					'item_label' => print_r( $posts, true )
				);
				$total = 1; */

				$results = array();
				foreach( $posts->posts as $post ) {
					$results[] = array(
						'id' => $post->ID,
						'item_label' => $post->post_title ? $post->post_title : '(no title - id #' . $post->ID . ')'
					);
				}

				$total = $posts->found_posts;

				break;

		}

		$json = array(
			'total' => $total,
			'results' => $results
		);

		echo json_encode( $json );

		exit();

	}


	/**
	 * Build query args from post data. Static function. Essentially the reverse of format_values().
	 *
	 * $object not used it appears.
	 */
	static public function save_args( $args, $objects = array() ) {

		// debug point
		// echo '<pre>'; print_r( $args ); echo '</pre>';
		// exit();

		/* // sample $args format:
		$args = array(
        	array(
            	'rule' => 'post_status',
            	'post_status' => 'pending'
        	),
        	array(
        		'rule' => 'meta_query',
        		'meta_query' => array(
        			'key' => 'metakey',
        			'compare' => 'LIKE',
        			'value' => 'Meta Value'
        		)
        	)
        ); */

		$query = array();

		foreach( $args as $arg ) {

			if ( !$arg['rule'] )
				continue;

			$dynamic = isset( $arg[$arg['rule']]['dynamic_type'] ) ? $arg[$arg['rule']]['dynamic_type'] : '';

			switch( $arg['rule'] ) {

				case 'post_type' :
				case 'post_status' :
					if ( !empty( $arg[$arg['rule']] ) )
						$query[$arg['rule']] = array_map( 'sanitize_key', $arg[$arg['rule']] );
					break;

				case 'published' :
					$published = array();

					// sanitize all intervals
					$logic = array(
						'year'     => array( 0, 9999 ),
						'monthnum' => array( 1, 12 ),
						'day'      => array( 1, 31 ),
						'hour'     => array( 0, 23 ),
						'minute'   => array( 0, 59 )
					);
					foreach( $logic as $interval => $span ) {
						$published[$interval] = simian_save_in_range( $arg['published'][$interval], $span[0], $span[1] );
					}

					// remove false entries
					foreach( $published as $span => $num ) {
						if ( $num === false ) unset( $published[$span] );
					}

					// save clean array
					if ( !empty( $published ) )
						$query = array_merge( $query, $published );

					break;

				case 'post_parent' :

					if ( !empty( $arg['post_parent']['items'] ) )
						$query['post_parent'] = (int) $arg['post_parent']['items'];

					if ( $dynamic === 'dynamic' )
						$query['post_parent'] = '[global]';

					break;

				case 'author' :
					if ( $arg['author']['items'] ) {
						$arg['author']['items'] = explode( ',', $arg['author']['items'] );
						if ( $arg['author']['clude'] == 'exclude' ) {
							foreach( $arg['author']['items'] as $key => $item ) {
								$arg['author']['items'][$key] = -$item;
							}
						}
						$query['author'] = implode( ',', $arg['author']['items'] );
					}

					if ( $dynamic === 'dynamic' ) {
						if ( $arg['author']['clude'] == 'exclude' )
							$query['author'] = '-[global]';
						else
							$query['author'] = '[global]';
					}

					break;

				case 'specific_items' :

					$defaults = array(
						'clude' => 'include',
						'items' => ''
					);
					$si_args = wp_parse_args( $arg['specific_items'], $defaults );

					if ( $si_args['items'] ) {

						$items = array_map( 'absint', explode( ',', $si_args['items'] ) );

						if ( !empty( $items ) && in_array( $si_args['clude'], array( 'include', 'exclude', 'post__in', 'post__not_in' ) ) ) {
							$query[$si_args['clude']] = $items;
						}

					}

					if ( $dynamic === 'dynamic' )
						$query[$si_args['clude']] = '[global]';

					break;

				case 'role' :
					if ( $arg['role'] )
						$query['role'] = sanitize_key( $arg['role'] );
					break;

				case 'search' :
					if ( $arg['search'] )
						$query['search'] = '*' . sanitize_text_field( $arg['search'] ) . '*';
					break;

				case 'meta_query' :

					$defaults = array(
						'key'     => '',
						'compare' => '=',
						'value'   => ''
					);
					$mq_args = wp_parse_args( $arg['meta_query'], $defaults );

					$key     = sanitize_key( $mq_args['key'] );
					$value   = wp_filter_post_kses( $mq_args['value'] );

					if ( $dynamic === 'dynamic' )
						$value = '[global]';

					$compare = in_array( $mq_args['compare'], array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS' ) ) ? $mq_args['compare'] : '=';
					$type    = is_numeric( $mq_args['value'] ) ? 'NUMERIC' : 'CHAR';

					if ( $key && $value !== '' ) {

						// might be more than one of these, so only define if necessary
						if ( !isset( $query['meta_query'] ) ) $query['meta_query'] = array();

						$query['meta_query'][] = array(
							'key'     => $key,
							'value'   => $value,
							'compare' => $compare,
							'type'    => $type
						);

					}

					// echo '<pre>'; print_r( $query['meta_query'] ); echo '</pre>';
					// exit();

					break;

				case 'tax_query' :

					// standardize args
					$defaults = array(
						'taxonomy' => '',
						'terms'    => array(),
						'operator' => 'IN'
					);
					$tq_args = wp_parse_args( $arg['tax_query'], $defaults );

					if ( $dynamic === 'dynamic' )
						$tq_args['terms'] = '[global]';

					if ( !empty( $tq_args['terms'] ) && $tq_args['taxonomy'] ) {

						// might be more than one of these, so only define if necessary
						if ( !isset( $query['tax_query'] ) ) $query['tax_query'] = array();

						if ( $dynamic !== 'dynamic' )
							$terms = array_map( 'absint', $tq_args['terms'] );
						else
							$terms = $tq_args['terms'];

						// sanitize args/build query
						$query['tax_query'][] = array(
							'taxonomy' => sanitize_key( $tq_args['taxonomy'] ),
							'terms'    => $terms,
							'operator' => in_array( $tq_args['operator'], array( 'IN', 'NOT IN', 'AND' ) ) ? $tq_args['operator'] : 'IN',
							'field'    => 'id'
						);

					}

					break;

				case 'connections' :

					$defaults = array(
						'type'  => '',
						'items' => array()
					);
					$conn_args = wp_parse_args( $arg['connections'], $defaults );

					if ( $conn_args['items'] ) {

						$items = array_map( 'absint', explode( ',', $conn_args['items'] ) );

						if ( !empty( $items ) && $conn_args['type'] ) {
							$query['connected_type']  = $conn_args['type'];
							$query['connected_items'] = $items;
						}

					}

					if ( $dynamic === 'dynamic' && $conn_args['type'] ) {
						$query['connected_type']  = $conn_args['type'];
						$query['connected_items'] = '[global]';
					}

					break;

			}

		}

		// remove tax_query and meta_query if empty
		if ( empty( $query['meta_query'] ) ) unset( $query['meta_query'] );
		if ( empty( $query['tax_query'] ) ) unset( $query['tax_query'] );

		// debug point
		// echo '<pre>'; print_r( $query ); echo '</pre>';
		// exit();

		return $query;

	}


}
Simian_Query_UI::init();