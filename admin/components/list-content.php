<?php

/**
 * Content component filters for admin list pages.
 */
class Simian_Admin_Content_Filters {


	/**
	 * The current fieldset filter.
	 */
	static $fieldset_filter;


	/**
	 * The current taxonomy filter.
	 */
	static $tax_filter;


	/**
	 * The current connection type filter.
	 */
	static $conn_filter;


	/**
	 * The current template filter.
	 */
	static $template_filter;


	/**
	 * Whether or not templates are active.
	 */
	static $templates_on;


	/**
	 * Assign filters to Simian hooks.
	 */
	static public function init() {

		// get current filters
		self::$fieldset_filter = isset( $_GET['fieldset'] )   ? sanitize_title( $_GET['fieldset'] )   : false;
		self::$tax_filter      = isset( $_GET['taxonomy'] )   ? sanitize_title( $_GET['taxonomy'] )   : false;
		self::$conn_filter     = isset( $_GET['connection'] ) ? sanitize_title( $_GET['connection'] ) : false;
		self::$template_filter = isset( $_GET['template'] )   ? sanitize_title( $_GET['template'] )   : false;

		self::$templates_on    = simian_get_plugin( 'simian_template' ) ? true : false;

		// set views
		add_filter( 'simian_admin-content-views',           array( __CLASS__, 'views' ) );

		// filter items and output filters
		add_filter( 'simian_admin-content-filter_items',    array( __CLASS__, 'filter_items' ) );
		add_action( 'simian_admin-content-filters',         array( __CLASS__, 'filters' ) );

		// set columns and column content
		add_filter( 'simian_admin-content-columns',         array( __CLASS__, 'columns' ), 10, 2 );
		add_filter( 'simian_admin-content-columns_content', array( __CLASS__, 'columns_content' ), 10, 4 );

		// set inline links
		add_filter( 'simian_admin-content-inline',          array( __CLASS__, 'inline' ), 10, 2 );

	}


	/**
	 * Filter views. Add built-in view and items.
	 */
	static public function views( $views ) {

		// get all non-built-in items
		$non_built_in = array();
		foreach( $views as $key => $view ) {

			if ( $key != 'built-in' )
				$non_built_in = array_merge( $non_built_in, array_keys( $view ) );

		}

		// build built-in view
		$built_in = isset( $views['built-in'] ) ? $views['built-in'] : array();
		$types = get_post_types( array(), 'objects' );
		ksort( $types );

		// map built-in data to $data keys
		foreach( $types as $key => $object ) {

			// figure out what to show for the icon
			$icon = self::figure_out_icon( $key, $object );

			if ( !in_array( $key, $non_built_in ) ) {
				$built_in[$key] = array(
					'sysname'        => $key,
					'description'    => '<span class="simian-faded">None</span>',
					'singular_label' => $object->labels->singular_name,
					'plural_label'   => $object->labels->name,
					'dashboard'      => array( 'menu_icon' => $icon )
				);
			}
		}

		$views['built-in'] = $built_in;
		return $views;

	}


	/**
	 * Figure out what to show for the icon for built-in content types.
	 */
	static public function figure_out_icon( $key, $object ) {

		// if icon specified, always use that
		$menu_icon = isset( $object->menu_icon ) ? $object->menu_icon : false;
		if ( $menu_icon )
			return $menu_icon;

		if ( simian_is_using_dashicons() )
			$post_icon = 'f109';
		else
			$post_icon = 'post';

		// if show_ui is true, default to post
		$show_ui = isset( $object->show_ui ) ? $object->show_ui : false;
		if ( $show_ui )
			return $post_icon;

		// if public is true and show_ui is not set, default to post
		$public = isset( $object->public ) ? $object->public  : false;
		if ( $public && !isset( $object->show_ui ) )
			return $post_icon;

		// return false by default
		return false;

	}


	/**
	 * Filter items by the current filters before being processed.
	 */
	static public function filter_items( $items ) {

		$conn_post_types = self::$conn_filter ? simian_get_connection_post_types( self::$conn_filter ) : array();
		$fieldsets = simian_get_data( 'fieldset' );

		if ( self::$templates_on )
			$templates = simian_get_data( 'template' );

		foreach( $items as $key => $item ) {

			// remove for fieldsets
			if ( self::$fieldset_filter && !in_array( $key, $fieldsets[self::$fieldset_filter]['content_types'] ) )
				unset( $items[$key] );

			// remove for taxonomies
			$taxonomies = get_object_taxonomies( $item['sysname'] );
			if ( self::$tax_filter && !in_array( self::$tax_filter, $taxonomies ) )
				unset( $items[$key] );

			// remove for connections
			if ( self::$conn_filter && !in_array( $item['sysname'], $conn_post_types ) )
				unset( $items[$key] );

			// remove for templates
			if ( self::$templates_on ) {
				if ( self::$template_filter && ( $key != $templates[self::$template_filter]['template_content_type'] ) )
					unset( $items[$key] );
			}

		}

		return $items;

	}


	/**
	 * Add extra filters (dropdowns) to the header, next to the bulk items dropdown.
	 * This is an action, and is echoed, not returned.
	 */
	static public function filters() {
		?><select name="fieldset">
			<option value="">View all field groups</options><?php
			$fieldsets = simian_get_data( 'fieldset' );
			if ( $fieldsets ) {
				foreach( $fieldsets as $name => $fieldset ) {
					?><option value="<?php echo $name; ?>" <?php selected( self::$fieldset_filter, $name ); ?>><?php echo esc_html( stripslashes( $fieldset['label'] ) ); ?></option><?php
				}
			}
		?></select>
		<select name="taxonomy">
			<option value="">View all taxonomies</options>
			<?php $taxonomies = get_taxonomies( array(), 'objects' );
			foreach( $taxonomies as $taxonomy ) {
				?><option value="<?php echo $taxonomy->name; ?>" <?php selected( self::$tax_filter, $taxonomy->name ); ?>><?php echo esc_html( stripslashes( $taxonomy->labels->name ) ); ?></option><?php
			} ?>
		</select>
		<select name="connection">
			<option value="">View all connections</options><?php
			$conns = P2P_Connection_Type_Factory::get_all_instances();
			foreach( $conns as $name => $conn ) {
				?><option value="<?php echo $name; ?>" <?php selected( self::$conn_filter, $name ); ?>><?php echo isset( $conn->label ) ? esc_html( stripslashes( $conn->label ) ) : $name; ?></option><?php
			}
		?></select><?php
		if ( self::$templates_on ) {
			?><select name="template">
				<option value="">View all templates</options><?php
				$templates = simian_get_data( 'template' );
				foreach( $templates as $name => $template ) {
					?><option value="<?php echo $name; ?>" <?php selected( self::$template_filter, $name ); ?>><?php echo esc_html( stripslashes( $template['label'] ) ); ?></option><?php
				}
			?></select><?php
		}
	}


	/**
	 * Add additional column headings.
	 *
	 * @param  array $columns Column headings in slug -> label pairs.
	 * @return array $columns Updated column array.
	 */
	static public function columns( $columns, $view ) {

		$columns['fieldset']   = 'Field Groups';
		$columns['taxonomy']   = 'Taxonomies';
		$columns['connection'] = 'Connections';

		if ( self::$templates_on )
			$columns['template']   = 'Templates';

		$columns['icon']       = 'Icon';
		return $columns;

	}


	/**
	 * What content to display in each columns.
	 *
	 * @param  string $display     Column content.
	 * @param  array $item         Current row (a post type).
	 * @param  string $column_name Column slug from columns().
	 * @return string $display     Updated column content.
	 */
	static public function columns_content( $display, $item, $column_name, $view ) {

		switch( $column_name ) {

			case 'fieldset' :
				$fieldsets = simian_get_data( 'fieldset' );
				$setlist   = array();
				if ( $fieldsets ) {
					foreach( $fieldsets as $name => $fieldset ) {
						if ( in_array( $item['sysname'], $fieldset['content_types'] ) )
							$setlist[] = '<a title="Show content types related to ' . esc_attr( stripslashes( $fieldset['label'] ) ) . '" href="admin.php?page=simian-content&view=' . $view . '&fieldset=' . $name . '">' . esc_html( stripslashes( $fieldset['label'] ) ) . '</a>';
					}
				}
				$display = implode( ', ', $setlist );
				if ( !$display )
					$display = '<span class="simian-faded">None</span>';
				break;

			case 'taxonomy' :
				$tax_list = array();
				$taxonomies = get_object_taxonomies( $item['sysname'], 'objects' );
				// $lists = simian_get_data( 'list' );
				foreach( $taxonomies as $name => $taxonomy ) {
					// if ( in_array( $item['sysname'], (array) $list['post_types'] ) )
					$tax_list[] = '<a title="Show content types related to ' . esc_attr( stripslashes( $taxonomy->labels->name ) ) . '" href="admin.php?page=simian-content&view=' . $view . '&taxonomy=' . $name . '">' . esc_html( stripslashes( $taxonomy->labels->name ) ) . '</a>';
				}
				$display = implode( ', ', $tax_list );
				if ( !$display )
					$display = '<span class="simian-faded">None</span>';
				break;

			case 'connection' :
				$conn_list = array();

				// $conns = simian_get_data( 'connection' );
				// foreach( $conns as $name => $conn ) {

				foreach ( P2P_Connection_Type_Factory::get_all_instances() as $name => $conn ) {

					$label = isset( $conn->label ) ? $conn->label : $name;
					$types = simian_get_connection_post_types( $conn );
					if ( in_array( $item['sysname'], $types ) )
						$conn_list[] = '<a title="Show all content types related to this connection" href="admin.php?page=simian-content&view=' . $view . '&connection=' . $name . '">' . esc_html( stripslashes( $label ) ) . '</a>';

				}
				$display = implode( ', ', $conn_list );
				if ( !$display )
					$display = '<span class="simian-faded">None</span>';
				break;

			case 'template' :
				$templates = simian_get_data( 'template' );
				$list = array();
				foreach( $templates as $name => $template ) {
					if ( $item['sysname'] == $template['template_content_type'] )
						$list[] = '<a title="Show content types related to ' . esc_attr( stripslashes( $template['label'] ) ) . '" href="admin.php?page=simian-content&view=' . $view . '&template=' . $name . '">' . esc_html( stripslashes( $template['label'] ) ) . '</a>';
				}
				$display = implode( ', ', $list );
				if ( !$display )
					$display = '<span class="simian-faded">None</span>';
				break;

			case 'icon' :

				// library of all icons
				$icons = simian_get_content_type_icons();

				// using dashicons?
				$dashicons = simian_is_using_dashicons();

				// current value
				$icon = isset( $item['dashboard']['menu_icon'] ) ? sanitize_key( $item['dashboard']['menu_icon'] ) : false;

				// set builtins
				if ( $item['sysname'] == 'attachment' )
					$icon = $dashicons ? 'f104' : 'media';
				if ( $item['sysname'] == 'page' )
					$icon = $dashicons ? 'f105' : 'page';

				// if show ui is true, show $icon
				$show_ui = isset( $item['dashboard']['show_ui'] ) ? (bool) $item['dashboard']['show_ui'] : true;

				if ( $dashicons ) {

					$icon_value = isset( $icons[$icon] ) ? $icons[$icon] : '';

					if ( !$icon_value && $show_ui && isset( $item['dashboard']['show_ui'] ) )
						$icon_value = 'admin-post';

					if ( $icon_value && $show_ui )
						$display = '<a href="#" class="dashicons dashicons-' . esc_attr( $icon_value ) . '"></a>';

				} else {

					if ( $icon && $show_ui ) {

						if ( isset( $icons[$icon]['loc'] ) )
							$display = '<img src="' . $icons[$icon]['loc'] . '" alt="" />';
						elseif ( $icon )
							$display = '<div id="adminmenu" style="margin:0;"><div class="menu-icon-' . $icon . '"><div class="wp-menu-image"></div></div></div>';

					}

				}

				if ( !$display )
					$display = '<span class="simian-faded">None</span>';

				break;

		}

		return $display;

	}


	/**
	 * Manage inline action links.
	 */
	static public function inline( $actions, $item ) {

		if ( $item['sysname'] == 'attachment' )
			$actions['view'] = '<a href="' . admin_url( 'upload.php' ) . '">View entries</a>';

		elseif ( $item['sysname'] != 'revision' && $item['sysname'] != 'nav_menu_item' )
			$actions['view'] = '<a href="' . admin_url( 'edit.php?post_type=' . $item['sysname'] ) . '">View entries</a>';

		return $actions;
	}


}
Simian_Admin_Content_Filters::init();