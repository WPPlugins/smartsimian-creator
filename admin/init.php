<?php

/**
 * Register an individual Simian admin page.
 */
class Simian_Admin {


	/**
	 * The page being created.
	 */
	public $component;


	/**
	 * Labeling information for this page.
	 */
	public $labels;


	/**
	 * The data that goes on this particular page.
	 */
	public $data;


	/**
	 * Whether we're on a single or list page.
	 */
	public $context;


	/**
	 * The current page object, either an instance of
	 * WP_List_Table or Simian_Admin_Form.
	 */
	public $page;


	/**
	 * Any messages to output at the top of the page.
	 * Will be in '_errors' and '_updates' keys.
	 * @var array
	 */
	public $messages;


	/**
	 * Constructor. Define class variables.
	 */
	public function __construct( $component, $labels, $data = array() ) {
		$this->component = $component;
		$this->labels    = $labels;
		$this->data      = $data;
		$this->messages  = array();
	}


	/**
	 * Class init method. Add hooks for registering any
	 * screen options and registering the admin page.
	 */
	function init() {

		// Declare the custom Screen Options we're using
		add_filter( 'set-screen-option', array( &$this, 'whitelist_screen_options' ), 10, 3 );

		// Register new admin page
		add_action( 'admin_menu', array( &$this, 'register_admin_page' ) );

	}


	/**
	 * Whitelist each component's custom screen options.
	 *
	 * @see 'set-screen-option' filter in core
	 */
	function whitelist_screen_options( $status, $option, $value ) {
		switch ( $option ) {
			case 'simian_' . $this->component . '_per_page' :
				$status = $value;
			break;
		}
		return $status;
	}


	/**
	 * Create dashboard menu items and pages, and add screen
	 * options for each component.
	 */
	public function register_admin_page() {

		// Load current component's submenu item
		$hook = add_submenu_page(
			'simian-home',
			$this->labels['plural'] . ' &lsaquo; SmartSimian Creator',
			$this->labels['plural'],
			'manage_options',
			'simian-' . $this->component,
			array( &$this, 'admin_page' )
		);

		// Add setup function to run only on correct pages thanks to $hook.
		// This function runs *before* the admin_page() callback.
		add_action( 'load-' . $hook, array( &$this, 'admin_page_setup' ) );

	}


	/**
	 * Admin page setup. Runs for each created submenu.
	 *
	 * Determine what type of page to build, load screen
	 * options, enqueue scripts and styles, and instantiate
	 * our List Page or Edit Page class.
	 */
	public function admin_page_setup() {

		// enqueues for all simian admin pages
		add_action( 'admin_enqueue_scripts', array( &$this, 'general_enqueues' ) );

		// Get the type of page we're currently on
		$this->context = ( isset( $_GET['action'] ) ) ? sanitize_key( $_GET['action'] ) : '';

		// Single pages only
		if ( $this->context == 'edit' || $this->context == 'add-new' ) {

			// Enqueue scripts and styles
			add_action( 'admin_enqueue_scripts', array( &$this, 'single_page_enqueues' ) );

			// Instantiate single page class
			$this->load_single_class();

		// List pages only
		} else {

			// Enqueue scripts and styles
			add_action( 'admin_enqueue_scripts', array( &$this, 'list_page_enqueues' ) );

			// Register screen options
			$this->register_screen_options();

			// Instantiate list page class
			$this->load_list_class();

		}

	}


	/**
	 * General enqueues.
	 */
	public function general_enqueues() {

		// scripts we want to be available to any Simian page that might need it
		wp_enqueue_script( 'placeholder', SIMIAN_ASSETS . 'lib/placeholder/placeholder.min.js', array(), SIMIAN_VERSION );
		wp_enqueue_script( 'simian-admin', SIMIAN_ASSETS . 'js/admin.js', array(), SIMIAN_VERSION );

	}


	/**
	 * Enqueue scripts and styles for Edit/Add New pages.
	 */
	public function single_page_enqueues() {

		// general single page stylesheet
		wp_enqueue_style( 'simian-admin-single', SIMIAN_ASSETS . 'css/admin-single.css', array(), SIMIAN_VERSION );
		wp_enqueue_style( 'jquery-select2', SIMIAN_ASSETS . 'lib/select2/select2.css', array(), SIMIAN_VERSION );
		wp_enqueue_style( 'simian-jquery-ui-css', SIMIAN_ASSETS . 'css/jquery-ui.min.css', array(), SIMIAN_VERSION );

		// select2
		wp_register_script( 'select2', SIMIAN_ASSETS . 'lib/select2/select2.js', array( 'jquery'), SIMIAN_VERSION, true );
		wp_enqueue_script( 'simian-select2', SIMIAN_ASSETS . 'js/select2.js', array( 'select2' ), SIMIAN_VERSION, true );

		// autoResize
		wp_register_script( 'simian-autosize', SIMIAN_ASSETS . 'lib/autosize/autosize.min.js', array( 'jquery' ), SIMIAN_VERSION, true );

		// repeaters
		wp_enqueue_script( 'simian-repeater-js', SIMIAN_ASSETS . 'js/repeater.js', array(
			'jquery',
			'jquery-ui-core',
			'jquery-ui-sortable'
		), SIMIAN_VERSION, true );

		// tabs for single pages
		wp_enqueue_script( 'simian-admin-single', SIMIAN_ASSETS . 'js/admin-single.js', array(
			'jquery',
			'jquery-ui-core',
			'jquery-ui-sortable',
			'jquery-ui-tooltip',
			'simian-repeater-js',
			'simian-autosize'
		), SIMIAN_VERSION, true );

		// query ui
		wp_enqueue_script( 'simian-query-ui', SIMIAN_ASSETS . 'js/query-ui.js', array(
			'jquery',
			'simian-select2'
		), SIMIAN_VERSION, true );

	}


	/**
	 * Load the single page class and instantiate it,
	 * passing the current component and the relevant
	 * data.
	 */
	public function load_single_class() {

		// load and initialize component filters
		if ( file_exists( SIMIAN_ADMIN . 'components/single-' . $this->component . '.php' ) ) {
			require_once( SIMIAN_ADMIN . 'components/single-' . $this->component . '.php' );
		}

		// save submission (will redirect or return error)
		if ( isset( $_POST['simian_admin_submit'] ) )
			$this->messages = Simian_Admin_Save::save( $this->component );

		// revert submission (will always redirect)
		if ( isset( $_GET['revert'] ) )
			Simian_Admin_Save::revert( $this->component );

		// single admin form wrapper object
		require_once( SIMIAN_ADMIN . 'single.php' );

		// Check if this is an Edit or an Add New
		$item = isset( $_GET['item'] ) ? sanitize_title( $_GET['item'] ) : false;

		// If Edit, get data
		$data = ( $item && isset( $this->data[$item] ) ) ? $this->data[$item] : false;

		// Instantiate edit page class
		$this->page = new Simian_Admin_Form( $this->component );

	}


	/**
	 * Enqueue scripts and styles for list pages.
	 */
	public function list_page_enqueues() {

		// miscellaneous list page js
		wp_enqueue_script( 'simian-admin-list', SIMIAN_ASSETS . 'js/admin-list.js', array( 'jquery' ), SIMIAN_VERSION );

	}


	/**
	 * Register custom screen options for our list pages.
	 */
	private function register_screen_options() {

		// add "Per Page" screen option
		$screen_option = array(
			'label' => $this->labels['plural'],
			'default' => 20,
			'option' => 'simian_' . $this->component . '_per_page'
		);
		add_screen_option( 'per_page', $screen_option );

	}


	/**
	 * Load WP_List_Table and our extender class, and
	 * instantiate it, passing in the current component
	 * and the relevant data.
	 */
	private function load_list_class() {

		// load component filters
		if ( file_exists( SIMIAN_ADMIN . 'components/list-' . $this->component . '.php' ) ) {
			require_once( SIMIAN_ADMIN . 'components/list-' . $this->component . '.php' );
		}

		// ensure WP_List_Table is available
		if( !class_exists( 'WP_List_Table' ) )
			require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

		// Simian's WP_List_Table extender
		require_once( SIMIAN_ADMIN . 'list.php' );

		// instantiate Simian_List_Page
		$this->page = new Simian_Admin_List( $this->component, $this->labels, $this->data );

		// run actions (delete/undo)
		$this->page->simian_process_actions();

	}


	/**
	 * Admin page callback function. Decides whether it's
	 * a list page, an edit page, or an add new page, and
	 * loads the appropriate function.
	 */
	public function admin_page() {
		?><div class="wrap"><?php
			if ( $this->context == 'edit' || $this->context == 'add-new' )
				$this->single_page( $this->page );
			else
				$this->list_page( $this->page );
		?></div><!-- .wrap --><?php
	}


	/**
	 * Callback function to display Edit and Add New pages. Essentially a wrapper for
	 * a new Simian_Admin_Form instance.
	 */
	private function single_page( $form ) {

		$form->component = $this->component;

		// Title and Add New link
		?><?php screen_icon( 'simian' ); ?>
		<h2>
			<?php $label = ( isset( $_GET['item'] ) ) ? 'Edit ' : 'Add New ';
			echo $label . $this->labels['singular']; ?>
			<a class="add-new-h2" href="admin.php?page=simian-<?php echo $this->component; ?>">View&nbsp;All</a>
			<?php if ( $label == 'Edit ' ) { ?>
				<a class="add-new-h2" href="admin.php?page=simian-<?php echo $this->component; ?>&amp;action=add-new">Add&nbsp;New</a>
			<?php } ?>
		</h2><?php

		// messaging system is overcomplicated
		if ( !$this->messages ) {
			if ( isset( $_GET['reverted'] ) )
				$this->messages = array( '_updates' => 'Changes reverted.' );
			if ( isset( $_GET['updated'] ) ) {
				if ( $_GET['updated'] == 'add-new' )
					$this->messages = array( '_updates' => 'Item added successfully.' );
				if ( $_GET['updated'] == 'edit' ) {
					$this->messages = array( '_updates' => 'Item updated successfully.' );
					$this->messages['_updates'] .= ' <a href="' . esc_url( wp_nonce_url( 'admin.php?page=simian-' . $this->component . '&action=edit&revert=' . sanitize_key( $_GET['item'] ), 'revert-' . $this->component ) ) . '">Undo changes</a>';
				}
			}
		}
		$this->messages();

		// flush rewrite rules if asked
		$rewrite = isset( $_GET['rewrite'] ) ? (bool) $_GET['rewrite'] : false;
		if ( $rewrite )
			flush_rewrite_rules();

		// set form args, and add tabs/sections
		$form = apply_filters( 'simian_admin-'  . $this->component . '-form', $form );

		// display the form
		$form->display();

	}


	/**
	 * Callback function to display list pages.
	 * Essentially a wrapper for a Simian_List_Page instance,
	 * which already got instantiated in pre_list_page().
	 */
	private function list_page( $page ) {

		// Title and Add New link
		?><?php screen_icon( 'simian' ); ?> <h2>
			<?php echo $this->labels['plural']; ?>
			<a class="add-new-h2" href="admin.php?page=simian-<?php echo $this->component; ?>&amp;action=add-new">Add New</a>
		</h2><?php

		// echo messages
		$this->messages( $page->get_messages() );

		// get relevant items
		$page->prepare_items();

		?><form class="simian-list-page simian-<?php echo $this->component; ?>-filters simian-view-<?php echo isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'custom'; ?>" action="" method="get"><?php

			$page->views();
			$page->search_box( 'Search', 'search_id' );
			$page->display();

		?></form><?php

	}


	/**
	 * Output updates and error messages.
	 */
	private function messages( $messages = false ) {

		if ( $messages )
			$this->messages = $messages;

		if ( isset( $this->messages['_updates'] ) ) {
			$updates = is_array( $this->messages['_updates'] ) ? $this->messages['_updates'] : array( $this->messages['_updates'] );
			?><div id="message" class="updated"><?php
			foreach( $updates as $update ) {
				?><p><?php echo $update; ?></p><?php
			}
			?></div><?php
		}

		if ( isset( $this->messages['_errors'] ) ) {
			$errors = is_array( $this->messages['_errors'] ) ? $this->messages['_errors'] : array( $this->messages['_errors'] );
			?><div id="message" class="error"><?php
			foreach( $errors as $error ) {
				?><p><?php echo $error; ?></p><?php
			}
			?></div><?php
		}

	}


}