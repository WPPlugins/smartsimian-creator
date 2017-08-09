<?php

/**
 * Connection component filters for admin list pages.
 */
class Simian_Admin_Connection_Filters {


	/**
	 * Filter by "From".
	 */
	static $from_filter;


	/**
	 * Filter by "To".
	 */
	static $to_filter;


	/**
	 * Init. Grab query args and set filters.
	 */
	static public function init() {

		// get current filters
		self::$from_filter = isset( $_GET['from'] ) ? sanitize_title( $_GET['from'] ) : false;
		self::$to_filter   = isset( $_GET['to'] )   ? sanitize_title( $_GET['to'] )   : false;

		// set views
		add_filter( 'simian_admin-connection-views',           array( __CLASS__, 'views' ) );

		// filter items and output dropdown filters
		add_filter( 'simian_admin-connection-filter_items',    array( __CLASS__, 'filter_items' ) );
		add_action( 'simian_admin-connection-filters',         array( __CLASS__, 'filters' ) );

		// set columns, and column content
		add_filter( 'simian_admin-connection-columns',         array( __CLASS__, 'columns' ), 10, 2 );
		add_filter( 'simian_admin-connection-columns_content', array( __CLASS__, 'columns_content' ), 10, 4 );

	}


	/**
	 * Filter views. Remove built-in view.
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
		$ctypes   = simian_get_data( 'connection' );
		ksort( $ctypes );

		// build data-formatted arrays for built-in ctypes
		foreach( $ctypes as $key => $object ) {
			if ( !in_array( $key, $non_built_in ) ) {
				$built_in[$key] = array(
					'sysname'      => $key,
					'description'  => isset( $object['description'] ) ? $object['description'] : '<span class="simian-faded">None</span>',
					'plural_label' => isset( $object['label'] ) ? $object['label'] : $key,
					'from'         => isset( $object['from'] ) ? $object['from'] : array(),
					'to'           => isset( $object['to'] ) ? $object['to'] : array(),
					'title'        => isset( $object['title'] ) ? $object['title'] : ''
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

		$ctypes = simian_get_data( 'connection' );

		// handle filters (content type)
		foreach( $items as $key => $item ) {

			$from = is_array( $ctypes[$key]['from'] ) ? $ctypes[$key]['from'] : array( $ctypes[$key]['from'] );

			if ( self::$from_filter && !in_array( self::$from_filter, $from ) )
				unset( $items[$key] );

			$to = is_array( $ctypes[$key]['to'] ) ? $ctypes[$key]['to'] : array( $ctypes[$key]['to'] );

			if ( self::$to_filter && !in_array( self::$to_filter, $to ) )
				unset( $items[$key] );

		}

		return $items;

	}


	/**
	 * Add extra filters (dropdowns) to the header, next to the bulk items dropdown.
	 * This is an action, and is echoed, not returned.
	 */
	static public function filters() {

		$options = array();
		$types = get_post_types( array(), 'objects' );
		foreach( $types as $type ) {
			$options[$type->name] = $type->labels->name;
		}
		$options['user'] = 'Users';
		ksort( $options );

		?><select name="from">
			<option value="">From</options>
			<?php foreach( $options as $name => $label ) {
				?><option value="<?php echo $name; ?>" <?php selected( self::$from_filter, $name ); ?>><?php echo esc_html( stripslashes( $label ) ); ?></option><?php
			} ?>
		</select>
		<select name="to">
			<option value="">To</options>
			<?php foreach( $options as $name => $label ) {
				?><option value="<?php echo $name; ?>" <?php selected( self::$to_filter, $name ); ?>><?php echo esc_html( stripslashes( $label ) ); ?></option><?php
			} ?>
		</select><?php

	}


	/**
	 * Add additional column headings.
	 *
	 * @param  array $columns Column headings in slug -> label pairs.
	 * @return array $columns Updated column array.
	 */
	static public function columns( $columns, $view ) {
		$columns['from'] = 'From';
		$columns['to'] = 'To';
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

		if ( $column_name == 'from' || $column_name == 'to' ) {

			$output = array();

			foreach( (array) $item[$column_name] as $object ) {
				if ( $object == 'user' )
					$output[] = 'Users';
				else
					$output[] = ( $obj = get_post_type_object( $object ) ) ? esc_html( stripslashes( $obj->labels->name ) ) : '';
			}
			$output = implode( ', ', array_unique( array_filter( $output ) ) );

			if ( $output )
				return $output;

			return '<span class="simian-faded">None</span>';

		}

		return $display;

	}


}
Simian_Admin_Connection_Filters::init();