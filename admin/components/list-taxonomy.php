<?php

/**
 * Taxonomy component filters for admin list pages.
 */
class Simian_Admin_Taxonomy_Filters {


	/**
	 * What content type to filter by.
	 */
	static $content_filter;


	/**
	 * What hierarchy to filter by.
	 */
	static $hierarchical_filter;


	/**
	 * Init. Grab query args and set filters.
	 */
	static public function init() {

		// get current filters
		self::$content_filter      = isset( $_GET['content_type'] ) ? sanitize_title( $_GET['content_type'] ) : false;

		/*
		self::$hierarchical_filter = isset( $_GET['hierarchical'] ) ? $_GET['hierarchical']                   : false;

		if ( !is_numeric( self::$hierarchical_filter ) )
			self::$hierarchical_filter = false;
		else
			self::$hierarchical_filter = (int) self::$hierarchical_filter;
		*/

		// set views
		add_filter( 'simian_admin-taxonomy-views',           array( __CLASS__, 'views' ) );

		// filter items and output dropdown filters
		add_filter( 'simian_admin-taxonomy-filter_items',    array( __CLASS__, 'filter_items' ) );
		add_action( 'simian_admin-taxonomy-filters',         array( __CLASS__, 'filters' ) );

		// set sortable columns and add data to sort by
		add_filter( 'simian_admin-taxonomy-prepare',         array( __CLASS__, 'sortable_data' ) );
		add_filter( 'simian_admin-taxonomy-sortable',        array( __CLASS__, 'sortable' ) );

		// set columns, and column content
		add_filter( 'simian_admin-taxonomy-columns',         array( __CLASS__, 'columns' ), 10, 2 );
		add_filter( 'simian_admin-taxonomy-columns_content', array( __CLASS__, 'columns_content' ), 10, 4 );

		// set inline links
		add_filter( 'simian_admin-taxonomy-inline',          array( __CLASS__, 'inline' ), 10, 2 );

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
		$taxonomies = get_taxonomies( array(), 'objects' );
		ksort( $taxonomies );
		foreach( $taxonomies as $key => $object ) {
			if ( !in_array( $key, $non_built_in ) ) {
				$built_in[$key] = array(
					'sysname'        => $key,
					'description'    => '<span class="simian-faded">None</span>',
					'singular_label' => $object->labels->singular_name,
					'plural_label'   => $object->labels->name
				);
			}
		}

		$views['built-in'] = $built_in;
		return $views;

	}


	/**
	 * Filter items by the current filter.
	 */
	static public function filter_items( $items ) {

		// handle filters (content type)
		foreach( $items as $key => $item ) {

			$tax = get_taxonomy( $key );

			// remove items without the current content type
			if ( self::$content_filter && !in_array( self::$content_filter, $tax->object_type ) )
				unset( $items[$key] );

			// remove items without the current hierarchy
			/* if ( is_int( self::$hierarchical_filter ) && self::$hierarchical_filter !== (int) $tax->hierarchical )
				unset( $items[$key] ); */

		}

		return $items;

	}


	/**
	 * Add extra filters (dropdowns) to the header, next to the bulk items dropdown.
	 * This is an action, and is echoed, not returned.
	 */
	static public function filters() {
		?><select name="content_type">
			<option value="">View all content types</options>
			<?php $types = get_post_types( array(), 'objects' );
			ksort( $types );
			foreach( $types as $type ) {
				?><option value="<?php echo $type->name; ?>" <?php selected( self::$content_filter, $type->name ); ?>><?php echo esc_html( stripslashes( $type->labels->name ) ); ?></option><?php
			} ?>
		</select>
		<!-- <select name="hierarchical">
			<option value="">View all hierarchies</options>
			<?php foreach( array( 1 => 'Hierarchical', 0 => 'Non-Hierarchical' ) as $key => $label ) {
				?><option value="<?php echo $key; ?>" <?php if ( self::$hierarchical_filter === $key ) echo 'selected="selected"'; ?>><?php echo esc_html( stripslashes( $label ) ); ?></option><?php
			} ?>
		</select> --><?php
	}


	/**
	 * Add sortable data so columns can properly sort.
	 */
	static public function sortable_data( $items ) {

		foreach( $items as $key => $data ) {
			$taxonomy = get_taxonomy( $key );
			if ( !isset( $data['slug'] ) )
				$items[$key]['slug'] = isset( $taxonomy->rewrite['slug'] ) ? $taxonomy->rewrite['slug'] : '<span class="simian-faded">None</span>';
		}
		return $items;

	}


	/**
	 * Add sortable columns.
	 */
	static public function sortable( $sortable ) {
		$sortable['slug'] = array( 'slug', false );
		return $sortable;
	}


	/**
	 * Add additional column headings.
	 *
	 * @param  array $columns Column headings in slug -> label pairs.
	 * @return array $columns Updated column array.
	 */
	static public function columns( $columns, $view ) {

		if ( $view == 'built-in' )
			unset( $columns['description'] );

		$columns['slug'] = 'URL Base';
		$columns['content_type'] = 'Related Content Types';
		// $columns['hierarchical'] = 'Hierarchical';
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

		$taxonomy = get_taxonomy( $item['sysname'] );

		switch( $column_name ) {

			case 'content_type' :
				$content_type_list = array();
				foreach( $taxonomy->object_type as $type ) {
					if ( post_type_exists( $type ) ) {
						$post_type = get_post_type_object( $type );
						$content_type_list[] = '<a title="Show taxonomies related to ' . esc_attr( stripslashes( $post_type->labels->name ) ) . '" href="admin.php?page=simian-taxonomy&view=' . $view . '&content_type=' . $type . '">' . esc_html( stripslashes( $post_type->labels->name ) ) . '</a>';
					} elseif( $type == 'link' ) {
						$content_type_list[] = 'Links';
					} elseif ( $type ==  'user' ) {
						$content_type_list[] = 'Users';
					}
				}
				$display = implode( ', ', $content_type_list );
				if ( !$display )
					$display = '<span class="simian-faded">None</span>';
				break;

			/* case 'hierarchical' :
				if ( $taxonomy->hierarchical )
					$display = '<a title="Show hierarchical taxonomies" href="admin.php?page=simian-taxonomy&view=' . $view . '&hierarchical=1">Yes</a>';
				else
					$display = '<a title="Show non-hierarchical taxonomies" href="admin.php?page=simian-taxonomy&view=' . $view . '&hierarchical=0">No</a>';
				break; */

			case 'slug' :
				if ( isset( $taxonomy->rewrite['slug'] ) )
					$display = $taxonomy->rewrite['slug'];
				else
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
			$actions['view'] = '<a href="' . admin_url( 'edit-tags.php?taxonomy=' . $item['sysname'] ) . '">View terms</a>';

		return $actions;
	}


}
Simian_Admin_Taxonomy_Filters::init();