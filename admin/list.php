<?php

/**
 * Create list pages for each necessary Simian component. Uses WP_List_Table for UI consistency.
 */
class Simian_Admin_List extends WP_List_Table {


	/**
	 * The current Simian component.
	 */
	public $component;


	/**
	 * All items of the current $component.
	 */
	public $data;


	/**
	 * The name of the page.
	 */
	public $label;


	/**
	 * The current 'view'.
	 */
	public $current_view;


	/**
	 * Constructor.
	 *
	 * @param $component string Content, Search, or Submission.
	 * @param $data array Array of user entries for this component.
	 */
	function __construct( $component = '', $labels = array(), $data = array() ) {
		global $status, $page;

		if ( !$data )
			$data = array();

		// get basic info fields, generally default to $key (system name) or 'None'
		// the 'core' stuff is in there for back-compat content type arrays
		foreach( $data as $key => $array ) {
			$defaults = array(
				'sysname'        => is_numeric( $key ) ? ( isset( $array['core']['name'] ) ? $array['core']['name'] : $key ) : $key,
				'description'    => isset( $array['core']['description'] )    ? $array['core']['description']    : '<span class="simian-faded">None</span>',
				'singular_label' => isset( $array['core']['singular_label'] ) ? $array['core']['singular_label'] : $key,
				'plural_label'   => isset( $array['core']['plural_label'] )   ? $array['core']['plural_label']   : $key,
			);
			$data[$key] = wp_parse_args( $array, $defaults );

			// allow for generic 'label' instead of immediately reverting singular and plural to sysname
			foreach( array( 'singular', 'plural' ) as $label ) {
				if ( $data[$key][$label . '_label'] == $data[$key]['sysname'] && isset( $data[$key]['label'] ) )
					$data[$key][$label . '_label'] = $data[$key]['label'];
			}

		}

		// set class vars
		$this->data         = $data;
		$this->component    = $component;
		$this->label        = $labels['plural'];
		$this->current_view = isset( $_GET['view'] ) ? sanitize_title( $_GET['view'] ) : 'available';

		// Define parent construct args
		parent::__construct( array(
			'singular' => sanitize_title( $labels['singular'] ),
			'plural'   => sanitize_title( $labels['plural'] ),
			'ajax'     => false
		) );

	}


	/**
	 * Process any actions and display messages. Typically this will be "delete" or "undelete".
	 */
	function simian_process_actions() {

		// get current action
		$action = sanitize_key( parent::current_action() );

		// query args to hide
		$remove_these = array( '_wp_http_referer', '_wpnonce', 'action', 'action2', 'restored', 'item' );

		// if bulk action exists
		if ( $action ) {

			// verify nonce
			check_admin_referer( 'bulk-' . $this->component );

			// build url to send back to user
			$sendback = remove_query_arg( $remove_these, wp_get_referer() );

			switch( $action ) {

				case 'delete' :

					/* Sample params passed:
					Array (
					    [s] =>
					    [_wpnonce] => a3e2068bac
					    [_wp_http_referer] => /beta/wp-admin/admin.php?page=simian-content
					    [action] => delete
					    [page] => simian-content
					    [view] => available
					    [taxonomy] =>
					    [connection] =>
					    [status] =>
					    [paged] => 1
					    [content] => Array
					        (
					            [0] => deadline
					        )

					    [action2] => -1
					) */

					// get items to delete
					$items = isset( $_GET[$this->component . '_action'] ) ? $_GET[$this->component . '_action'] : array();
					$items = array_unique( array_map( 'sanitize_key', (array) $items ) );

					// for each item
					$deleted_items = array();
					foreach( $items as $item ) {

						// delete it
						$deleted = simian_delete_item( $item, $this->component );

						if ( $deleted )
							$deleted_items[] = $item;


					}

					// send back url: page=simian-content&deleted=deadline,announcement[&anyotherargs]
					$sendback = add_query_arg( 'deleted', implode( ',', $deleted_items ), $sendback );

					break;

				case 'undelete' :

					// get deleted items
					$deleted = isset( $_GET['deleted'] ) ? explode( ',', $_GET['deleted'] ) : array();
					$deleted = array_unique( array_map( 'sanitize_key', (array) $deleted ) );

					// for each deleted item
					$restored = array();
					foreach( $deleted as $item ) {

						// restore item
						$result = simian_restore_item( $item, $this->component );

						if ( $result )
							$restored[] = $item;

					}

					$sendback = remove_query_arg( 'deleted', $sendback );
					$sendback = add_query_arg( 'restored', implode( ',', $restored ), $sendback );

					break;

			}

			// remove empty query args
			$remove = array();
			foreach( $_GET as $key => $val ) {
				if ( $val === '' ) $remove[] = $key;
			}
			$sendback = remove_query_arg( $remove, $sendback );

			wp_redirect( $sendback );
			exit;

		// if no action
		} else {

			// if filter or any other non-action has happened from this page, clean up url
			if ( isset( $_GET['_wp_http_referer'] ) ) {

				// current uri
				$sendback = $_SERVER['REQUEST_URI'];

				// remove empty query args
				foreach( $_GET as $key => $val ) {
					if ( $val === '' )
						$sendback = remove_query_arg( $key, $sendback );
				}

				// remove ignorable query args
				$sendback = remove_query_arg( $remove_these, $sendback );

				wp_redirect( $sendback );
				exit;

			}

		}

	}


	/**
	 * Output messages upon user actions.
	 */
	function get_messages() {

		$messages = array();

		if ( isset( $_GET['deleted'] ) ) {
			$deleted = array_map( 'sanitize_key', explode( ',', $_GET['deleted'] ) );
			$list_deleted = implode( ',', $deleted );
			$messages['_updates']  = 'The selected ' . _n( 'item has', 'items have', count( $deleted ) ) . ' been deleted.';
			$messages['_updates'] .= ' <a href="' . esc_url( wp_nonce_url( 'admin.php?page=simian-' . $this->component . '&action=undelete&deleted=' . $list_deleted, 'bulk-' . $this->component ) ) . '">Undo</a>';
		}

		if ( isset( $_GET['restored'] ) ) {
			if ( strpos( $_GET['restored'], ',' ) !== false )
				$messages['_updates'] = 'The deleted items have been restored.';
			else
				$messages['_updates'] = 'The deleted item has been restored.';
		}

		return $messages;

	}


	/**
	 * Handle bulk actions, pagination, visible/hidden columns, sorting.
	 */
	function prepare_items() {

		// Get all views - defaults are available, read-only, built-in
		$simian_items    = simian_get_data( $this->component );
		$custom_items    = get_option( 'simian_' . $this->component, array() );
		if ( !$simian_items )
			$simian_items = array();
		$built_in_keys   = array_diff( array_keys( $simian_items ), array_keys( $custom_items ) );
		$built_in_items  = array();
		foreach( $built_in_keys as $key ) {
			$built_in_items[$key] = $simian_items[$key];
		}

		$default_views  = array(
			'available' => $custom_items,
			'built-in'  => $built_in_items,
		);
		$this->views = apply_filters( "simian_admin-{$this->component}-views", $default_views );

		// add built-in items to possible data
		if ( isset( $this->views['built-in'] ) )
			$this->data = array_merge( $this->data, $this->views['built-in'] );

		// Filter data before any other default preparing
		$this->data = apply_filters( "simian_admin-{$this->component}-prepare", $this->data );

		// Limit items by current view
		foreach( $this->data as $key => $item ) {
			if ( !in_array( $key, array_keys( $this->views[$this->current_view] ) ) )
				unset( $this->data[$key] );
		}

		// Limit items by current filters
		$this->data = apply_filters( "simian_admin-{$this->component}-filter_items", $this->data );

		// Check search
		$search_term = ( isset( $_GET['s'] ) ) ? sanitize_text_field( $_GET['s'] ) : '';
		if ( $search_term ) {
			foreach( $this->data as $key => $data ) {

				// Quick and dirty search. Store array in a string, then check it with strpos.
				$array_string = print_r( $data, true );
				if ( strpos( strtolower( $array_string ), strtolower( $search_term ) ) === false )
					unset( $this->data[$key] );

			}
		}

		// Get visible and hidden columns
		$this->_column_headers = $this->get_column_info();

		// Sort data
		usort( $this->data, array( &$this, 'sorting' ) );

		// Get items per page
		$per_page = $this->get_items_per_page( 'simian_' . $this->component . '_per_page', 20 );

		// Get current page number
		$current_page = $this->get_pagenum();

		// Data to show on only this page (params: array, offset, length)
		$this->found_data = array_slice( $this->data, ( ( $current_page - 1 ) * $per_page ), $per_page );

		// Set pagination args
		$this->set_pagination_args( array(
			'total_items' => count( $this->data ),
			'per_page' => $per_page
		) );

		// Items to output - we're now officially passing off to WP_List_Table
		$this->items = $this->found_data;

	}


	/**
	 * Generate the table navigation above or below the table.
	 */
	function display_tablenav( $which ) {
		if ( 'top' == $which ) wp_nonce_field( 'bulk-' . $this->component );
		?><div class="tablenav <?php echo esc_attr( $which ); ?>">
			<div class="alignleft actions">
				<?php $this->bulk_actions(); ?>
			</div>
			<?php $this->extra_tablenav( $which );
			$this->pagination( $which ); ?>
			<br class="clear" />
		</div><?php
	}


	/**
	 * Define column slugs and labels. A few columns by default, filterable.
	 */
	function get_columns(){
		$columns = array(
			'cb'          => '<input type="checkbox" />',
			'simian_name' => 'Name',
			'description' => 'Description'
		);
		return apply_filters( "simian_admin-{$this->component}-columns", $columns, $this->current_view );
	}


	/**
	 * Define sortable columns.
	 *
	 * WP_List_Table handles this function, and it's processed in get_column_info() in prepare_items().
	 * A few columns are sortable by default. All filterable.
	 */
	function get_sortable_columns() {
		$sortable = array(
			'simian_name' => array( 'simian_name',  false ),
			'description' => array( 'description',  false )
		);
		return apply_filters( "simian_admin-{$this->component}-sortable", $sortable );
	}


	/**
	 * Define views (links directly under title).
	 */
	function get_views() {
		$views = array();
		foreach( $this->views as $view => $items ) {
			// hide read-only and built-in if empty
			if ( count( $items ) < 1 && ( $view == 'read-only' || $view == 'built-in' ) ) {
			} else {
				$views[$view] = '<a ' . ( $this->current_view == $view ? 'class="current" ' : '' ) . 'href="admin.php?page=simian-' . $this->component . '&view=' . $view . '">' . ucwords( $view ) . ' <span class="count">(' . count( $items ) . ')</span></a>';
			}
		}
		return $views;
	}


	/**
	 * Handle column sorting.
	 */
	function sorting( $key, $value ) {

		// If no sorting specified, sort by name
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'name';

		// And by name, we actually mean plural label
		if ( !$orderby || $orderby == 'name' )
			$orderby = 'plural_label';

		// If no order specified, sort ascending
		$order = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'asc';

		// Determine sort order
		$result = strcmp( strtolower( $key[$orderby] ), strtolower( $value[$orderby] ) );

		// Send final sort direction to usort
		return ( $order === 'desc' ) ? -$result : $result;

	}


	/**
	 * Display the checkbox column.
	 *
	 * Items created in the UI (in wp_options) can be deleted, but items created
	 * programmatically (in plugins) can, obviously, not be. Generally the code won't
	 * stay in plugins (it will be added to the options array upon activation), but
	 * some will.
	 */
	function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="' . $this->component . '_action[]" value="%s" />', $item['sysname'] );
	}


	/**
	 * Display contents for the custom columns.
	 */
	function column_default( $item, $column_name ) {

		$display = '';

		if ( $column_name == 'simian_name' )
			$display = $item['sysname'];

		if ( $column_name == 'description' )
			$display = $item['description'] ? stripslashes( $item['description'] ) : '<span class="simian-faded">None</span>';

		$display = apply_filters( 'simian_admin_columns_content', $display, $item, $column_name, $this->component );
		$display = apply_filters( "simian_admin-{$this->component}-columns_content", $display, $item, $column_name, $this->current_view );

		return $display;

	}


	/**
	 * Display "no items found" text.
	 */
	function no_items() {
		echo '<div class="simian-list-padding">No ' . $this->label . ' found.&nbsp; <a href="admin.php?page=simian-' . $this->component . '&amp;action=add-new">Add New</a></div>';
	}


	/**
	 * Define Bulk Actions.
	 */
	function get_bulk_actions() {

		// Delete is the only default bulk action
		$actions = array( 'delete' => 'Delete' );

		// Hide bulk actions on read-only and built-in views
		if ( $this->current_view == 'built-in' || $this->current_view == 'read-only' )
			$actions = array();

		return apply_filters( "simian_admin-{$this->component}-bulk", $actions );
	}


	/**
	 * Define additional filters next to the bulk actions. Echoed, not returned.
	 */
	function extra_tablenav( $location ) {
		if ( $location == 'bottom' )
			return;

		?><input type="hidden" name="page" value="simian-<?php echo $this->component; ?>" />
		<input type="hidden" name="view" value="<?php echo $this->current_view; ?>" />
		<?php if ( has_action( "simian_admin-{$this->component}-filters" ) ) { ?>
			<div class="alignleft actions simian-filters simian-<?php echo $this->current_view; ?>-filters">
				<?php do_action( "simian_admin-{$this->component}-filters" ); ?>
				<?php submit_button( 'Filter', 'button', false, false, array( 'id' => 'simian-' . $this->component . '-query-submit' ) ); ?>
			</div>
		<?php }
	}


	/**
	 * Define stuff in the Name field.
	 */
	function column_simian_name( $item ) {

		// Build the bold name and filter
		if ( $this->current_view != 'built-in' )
			$name_link = '<a href="admin.php?page=simian-' . $this->component . '&amp;action=edit&amp;item=' . $item['sysname'] . '"><strong>' . ( $item['plural_label'] ? stripslashes( $item['plural_label'] ) : '(no name)' ) . '</strong></a>';
		else
			$name_link = '<strong>' . stripslashes( $item['plural_label'] ) . '</strong>';
		$name_link = apply_filters( "simian_admin-{$this->component}-name_link", $name_link, $item );

		// Build the inline links and filter
		$actions = array();
		if ( $this->current_view != 'built-in' )
			$actions['edit'] = '<a href="admin.php?page=simian-' . $this->component . '&amp;action=edit&amp;item=' . $item['sysname'] . '">Edit</a>';
		if ( $this->current_view != 'built-in' && $this->current_view != 'read-only' )
			$actions['delete'] = '<a href="' . esc_url( wp_nonce_url( 'admin.php?page=simian-' . $this->component . '&amp;action=delete&amp;' . $this->component . '_action=' . $item['sysname'], 'bulk-' . $this->component ) ) . '">Delete</a>';
		$actions = apply_filters( "simian_admin-{$this->component}-inline", $actions, $item );

		return sprintf( '%1$s %2$s', $name_link, $this->row_actions( $actions ) );

	}


}