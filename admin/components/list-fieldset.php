<?php

/**
 * Fieldset component filters for admin list pages.
 */
class Simian_Admin_Fieldset_Filters {


	/**
	 * The current content type filter.
	 */
	static $content_filter;


	/**
	 * Assign filters to Simian hooks.
	 */
	static public function init() {

		// get current filters
		self::$content_filter = isset( $_GET['content_type'] ) ? sanitize_title( $_GET['content_type'] ) : false;

		// filter items and output filters
		add_filter( 'simian_admin-fieldset-filter_items',    array( __CLASS__, 'filter_items' ) );
		add_action( 'simian_admin-fieldset-filters',         array( __CLASS__, 'filters' ) );

		// set columns and column content
		add_filter( 'simian_admin-fieldset-columns',         array( __CLASS__, 'columns' ), 10, 2 );
		add_filter( 'simian_admin-fieldset-columns_content', array( __CLASS__, 'columns_content' ), 10, 4 );

	}


	/**
	 * Filter items by the current filters before being processed.
	 */
	static public function filter_items( $items ) {

		foreach( $items as $key => $item ) {

			// if filter exists and current item is not related to it, unset
			if ( self::$content_filter && !in_array( self::$content_filter, $item['content_types'] ) )
				unset( $items[$key] );

		}

		return $items;

	}


	/**
	 * Add extra filters (dropdowns) to the header, next to the bulk items dropdown.
	 * This is an action, and is echoed, not returned.
	 */
	static public function filters() {
		?><select name="content_type">
			<option value="">View all content types</options><?php
			$types = get_post_types( array(), 'objects' );
			ksort( $types );
			foreach( $types as $type ) {
				?><option value="<?php echo $type->name; ?>" <?php selected( self::$content_filter, $type->name ); ?>><?php echo esc_html( stripslashes( $type->labels->name ) ); ?></option><?php
			}
		?></select><?php
	}


	/**
	 * Add additional column headings.
	 *
	 * @param  array $columns Column headings in slug -> label pairs.
	 * @return array $columns Updated column array.
	 */
	static public function columns( $columns, $view ) {
		$columns['fields']        = 'Fields';
		$columns['content_types'] = 'Related Content Types';
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

			case 'fields' :
				$field_list = array();
				foreach( $item['fields'] as $field )
					$field_list[] = esc_html( stripslashes( $field['label'] ) );
				$display = implode( ', ', $field_list );
				if ( !$display )
					$display = '<span class="simian-faded">None</span>';
				break;

			case 'content_types' :
				$ct_list = array();
				foreach( $item['content_types'] as $content_type ) {
					$obj = get_post_type_object( $content_type );
					if ( $obj )
						$ct_list[] = '<a title="Show field groups related to ' . esc_attr( stripslashes( $obj->labels->name ) ) . '" href="admin.php?page=simian-fieldset&view=' . $view . '&content_type=' . $content_type . '">' . esc_html( stripslashes( $obj->labels->name ) ) . '</a>';
				}
				$display = implode( ', ', $ct_list );
				if ( !$display )
					$display = '<span class="simian-faded">None</span>';
				break;

		}

		return $display;

	}


}
Simian_Admin_Fieldset_Filters::init();