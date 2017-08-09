<?php
/**
 * Build a custom content type or modify a built-in type. In addition to registering post types,
 * this class adds the necessary enqueues to add/edit pages, adds the ajax upload handler,
 * generates the title and slug for custom pages, and filters the content and excerpt where required.
 */
class Simian_Content {


	/**
	 * Content type system name.
	 *
	 * @var string
	 */
	public $name;


	/**
	 * All content type args.
	 *
	 * @var array
	 */
	public $core;


	/**
	 * Constructor. Define properties.
	 *
	 * @param $name string Content type name.
	 * @param $args array  Content type info.
	 */
	public function __construct( $name = '', $args = array() ) {

		// sanitize all args (and funnel name through args)
		$args = $this->sanitize( $name, $args );

		// define name
		$this->name = $args['sysname'];

		// define core
		$this->core = $args;

	}


	/**
	 * Normalize all args into the current format. This is fairly messy because it attempts a deep level of
	 * backwards-compatibility.
	 *
	 * So there are some nested ternaries. Sorry.
	 */
	private function sanitize( $name, $args ) {

		// name:
		// this is weird, but basically if $name exists we use that.
		// that's the current version that gets $name from the sysname.
		// if it doesn't exist, we're dealing with an old array so we
		// find it elsewhere.
		$args['name'] = ( $name && !is_numeric( $name ) ) ? $name :
			( isset( $args['name'] ) ? $args['name'] :
				( isset( $args['core']['name'] ) ? $args['core']['name'] : '' ) );
		$args['sysname'] = $args['name'];

		// description
		$args['description'] = isset( $args['description'] ) ? $args['description'] :
			( isset( $args['core']['description'] ) ? $args['core']['description'] : '' );

		// singular_label
		$args['singular_label'] = isset( $args['singular_label'] ) ? $args['singular_label'] :
			( isset( $args['core']['singular_label'] ) ? $args['core']['singular_label'] : '' );

		// plural_label
		$args['plural_label'] = isset( $args['plural_label'] ) ? $args['plural_label'] :
			( isset( $args['core']['plural_label'] ) ? $args['core']['plural_label'] : '' );

		// dashboard
		$args['dashboard'] = isset( $args['dashboard'] ) ? wp_parse_args( $args['dashboard'], array(
			'show_ui' => true,
			'menu_icon' => 'post'
		) ) : array();
		if ( empty( $args['dashboard'] ) ) {
			$args['dashboard'] = array(
				'show_ui'   => isset( $args['core']['advanced']['show_ui'] ) ? (bool) $args['core']['advanced']['show_ui'] : true,
				'menu_icon' => isset( $args['core']['advanced']['menu_icon'] ) ? $args['core']['advanced']['menu_icon'] : ''
			);
		}

		// public
		$args['public'] = isset( $args['public'] ) ? wp_parse_args( $args['public'], array(
			'public' => true,
			'slug' => '',
			'has_archive' => false,
			'has_archive_slug' => '',
			'include_in_search' => false
		) ) : array();
		if ( empty( $args['public'] ) ) {
			$args['public'] = array(
				'public'            => isset( $args['core']['advanced']['public'] ) ? (bool) $args['core']['advanced']['public'] : true,
				'slug'              => isset( $args['core']['slug'] ) ? sanitize_title( $args['core']['slug'] ) : '',
				'has_archive'       => isset( $args['core']['advanced']['has_archive'] ) ? (bool) $args['core']['advanced']['has_archive'] : false,
				'has_archive_slug'  => isset( $args['core']['advanced']['has_archive'] ) ? sanitize_title( $args['core']['advanced']['has_archive'] ) : '',
				'include_in_search' => isset( $args['core']['advanced']['exclude_from_search'] ) ? !( (bool) $args['core']['advanced']['exclude_from_search'] ) : false
			);
		}

		// components
		$args['components'] = isset( $args['components'] ) ? array_map( 'sanitize_key', (array) $args['components'] ) :
			( isset( $args['core']['components'] ) ? array_map( 'sanitize_key', (array) $args['core']['components'] ) : array() );

		// hierarchical
		$args['hierarchical'] = isset( $args['hierarchical'] ) ? (bool) $args['hierarchical'] :
			( isset( $args['core']['hierarchical'] ) ? (bool) $args['core']['hierarchical'] : false );
		if ( !$args['hierarchical'] ) {
			// set as true if page-attributes are included
			if ( in_array( 'page-attributes', $args['components'] ) )
				$args['hierarchical'] = true;
		}

		// templates
		$args['title_template']   = isset( $args['title_template'] ) ? sanitize_text_field( $args['title_template'] ) : '';
		$args['slug_template']    = isset( $args['slug_template'] )  ? sanitize_text_field( $args['slug_template'] )  : '';
		$args['excerpt_template'] = isset( $args['excerpt_template'] ) ? $args['excerpt_template'] : array();
		$args['content_template'] = isset( $args['content_template'] ) ? $args['content_template'] : array();

		return $args;

	}


	/**
	 * Init function. Add hooks.
	 */
	public function init() {

		// register post type (save for late in init)
		add_action( 'init', array( &$this, 'register_post_type' ), 100 );

		// hook for single admin pages for this post type (remove title/slug)
		// add_action( 'load-post-new.php', array( &$this, 'single_page_init' ) );
		// add_action( 'load-post.php',     array( &$this, 'single_page_init' ) );

		// hook for list admin pages for this post type (configure custom columns)
		add_action( 'admin_init', array( &$this, 'list_page_init' ) );

		// add class to simian content type post edit forms
		add_action( 'post_edit_form_tag', array( &$this, 'post_edit_form_class' ) );

		// maybe remove meta boxes
		add_action( 'add_meta_boxes', array( &$this, 'remove_meta_boxes' ), 100 );

		// filter the title
		add_filter( 'the_title', array( &$this, 'customized_title' ), 10, 2 );
		add_filter( 'single_post_title', array( &$this, 'customized_title' ), 10, 2 );

		// save custom title and slug
		add_filter( 'wp_insert_post_data', array( &$this, 'save_custom_title_and_slug' ), 10, 2 );

	}


	/**
	 * Register the content type.
	 *
	 * Formatting is now according to UI standards. This function converts that format into
	 * register_post_type format and registers the content type.
	 *
	 * Thanks to sync_basic_info() all args are defined and stored in $this->core.
	 *
	 * @uses wp_parse_args
	 * @uses register_post_type
	 */
	public function register_post_type() {

		// data
		$d = $this->core;

		$d['singular_label'] = stripslashes( $d['singular_label'] );
		$d['plural_label']   = stripslashes( $d['plural_label'] );

		// array to stick in register_post_type
		$args = array(

			'label' => $d['plural_label'],
			'labels' => array(
				'name'               => $d['plural_label'],
				'singular_name'      => $d['singular_label'],
				'menu_name'          => $d['plural_label'],
				'all_items'          => $d['plural_label'],
				'add_new'            => 'Add New',
				'add_new_item'       => 'Add New ' . $d['singular_label'],
				'edit_item'          => 'Edit ' . $d['singular_label'],
				'new_item'           => 'New ' . $d['singular_label'],
				'view_item'          => 'View ' . $d['singular_label'],
				'items_archive'      => $d['plural_label'],
				'search_items'       => 'Search ' . $d['plural_label'],
				'not_found'          => 'No ' . $d['plural_label'] . ' found',
				'not_found_in_trash' => 'No ' . $d['plural_label'] . ' found in trash',
				'parent_item_colon'  => 'Parent ' . $d['singular_label'],
			),
			'description' => $d['description'],
			'public' => $d['public']['public'],
			'exclude_from_search' => !( $d['public']['include_in_search'] ),
			'show_ui' => true, // show ui is forced true but show_in_menu can be set to false
			'show_in_menu' => $d['dashboard']['show_ui'],
			'menu_icon' => $this->get_icon( $d['dashboard']['menu_icon'] ),
			'hierarchical' => $d['hierarchical'],
			'supports' => $d['components'] ? $d['components'] : false,
			'has_archive' => $d['public']['has_archive'] ? $d['public']['has_archive_slug'] : false,
			'rewrite' => $d['public']['public'] ? array( 'slug' => $d['public']['slug'] ) : false
		);

		register_post_type( $this->name, $args );

	}


	/**
	 * Conditional check for selected components.
	 */
	private function supports( $component ) {
		return in_array( $component, $this->core['components'] );
	}


	/**
	 * Remove standard meta boxes as needed.
	 */
	public function remove_meta_boxes() {
		if ( !$this->supports( 'title' ) )
			remove_meta_box( 'slugdiv', $this->name, 'normal' );
	}


	/**
	 * Grab correct menu icon URL. Used by menu_icon param in register_post_type.
	 */
	public function get_icon( $slug ) {

		if ( simian_is_using_dashicons() ) {
			add_action( 'admin_head', array( &$this, 'show_dashicon' ) );
			return null;
		}

		$icons = simian_get_content_type_icons();

		// not found
		if ( !isset( $icons[$slug] ) )
			return null;

		// no loc set - a default icon
		if ( !isset( $icons[$slug]['loc'] ) ) {
			add_action( 'admin_head', array( &$this, 'icon_offset' ) );
			return null;
		}

		// return loc
		return $icons[$slug]['loc'];

	}


	/**
	 * Add dashicon style.
	 */
	public function show_dashicon() {
		$icon = $this->core['dashboard']['menu_icon'];

		// check for old icon one more time
		if ( in_array( $icon, array_keys( simian_get_content_type_icons( $force_old = true ) ) ) || !$icon )
			return;

		?><style type="text/css">#adminmenu #menu-posts-<?php echo $this->name; ?> div.wp-menu-image:before {content:"\<?php echo $icon; ?>";}</style><?php
	}


	/**
	 * Add style to display correct icon. Hooked to admin_head. This is the method for
	 * old non-dashicons.
	 */
	public function icon_offset() {

		$slug = $this->core['dashboard']['menu_icon'];
		switch( $slug ) {
			case 'posts':      $pos = '-269px'; break;
			case 'dashboard':  $pos = '-59px';  break;
			case 'sites':      $pos = '-359px'; break;
			case 'media':      $pos = '-119px'; break;
			case 'links':      $pos = '-89px';  break;
			case 'pages':      $pos = '-149px'; break;
			case 'comments':   $pos = '-29px';  break;
			case 'appearance': $pos = '1px';    break;
			case 'plugins':    $pos = '-179px'; break;
			case 'users':      $pos = '-300px'; break;
			case 'tools':      $pos = '-209px'; break;
			case 'settings':   $pos = '-239px'; break;
			default:           $pos = '0';      break;
		}

		?><style type="text/css"><?php
			?>#adminmenu #menu-posts-<?php echo $this->name; ?> .wp-menu-image{background-position:<?php echo $pos; ?> -33px;}<?php
			?>#adminmenu #menu-posts-<?php echo $this->name; ?>:hover .wp-menu-image{background-position:<?php echo $pos; ?> -1px;}<?php
		?></style><?php
	}


	/**
	 * Function to run only on add/edit pages for $this content type. Ensure we're on the
	 * correct page, then add hook for enqueues and ajax args.
	 */
	public function single_page_init() {

		// only launch on correct post type
		global $current_screen;
		if ( $current_screen->post_type != $this->name )
			return;

		// enqueue styles and scripts
		add_action( 'admin_enqueue_scripts', array( &$this, 'single_page_enqueues' ) );

	}


	/**
	 * Enqueues for single pages for $this content type.
	 */
	public function single_page_enqueues() {}


	/**
	 * Function to run only on list pages for $this content type. Ensure we're on the correct
	 * page, then add hook for enqueues and admin columns.
	 */
	public function list_page_init() {

		// only launch on edit.php pages and ajax calls
		global $pagenow;
		if ( $pagenow !== 'edit.php' && $pagenow !== 'admin-ajax.php' )
			return;

		// only launch on correct post type
		$post_type = isset( $_REQUEST['post_type'] ) ? $_REQUEST['post_type'] : '';
		if ( $post_type !== $this->name )
			return;

		// instantiate custom columns
		Simian_Columns::init( array(
			'type'           => $this->name,
			'supports'       => $this->core['components'],
			'fields'         => simian_get_fields( $this->name ),
			'title_template' => $this->core['title_template']
		) );

	}


	/**
	 * Add class to simian content type post edit forms.
	 */
	public function post_edit_form_class( $post ) {
		if ( $post->post_type == $this->name )
			echo ' class="simian-content-type"';
	}


	/**
	 * Filter the title.
	 *
	 * @param $id Could be int or post object, depending on the filter.
	 */
	public function customized_title( $title = '', $id = 0 ) {

		if ( $this->supports( 'title' ) )
			return $title;

		if ( !$id )
			return $title;

		if ( !is_object( $id ) )
			$post = get_post( $id );
		else
			$post = $id;

		if ( !$post )
			return $title;

		if ( $post->post_type != $this->name )
			return $title;

		$post_title = $this->generate_text( $this->core['title_template'], $post );

		if ( !$post_title )
			return $title;

		return $post_title;

	}


	/**
	 * Save the title and slug.
	 *
	 * Only if custom title has been set.
	 *
	 * Doesn't run if wrong post type, autosaving, or initial save.
	 */
	public function save_custom_title_and_slug( $data, $postarr ) {

		if ( $this->supports( 'title' ) )
			return $data;

		if ( $data['post_type'] != $this->name )
			return $data;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $data;

		if ( $data['post_status'] === 'auto-draft' )
			return $data;

		$post = get_post( $postarr['ID'] );
		if ( !$post )
			$post = new stdClass();

		// set correct date/modified values
		foreach( array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) as $timestamp ) {
			if ( !empty( $data[$timestamp] ) && $data[$timestamp] !== '0000-00-00 00:00:00' )
				$post->$timestamp = $data[$timestamp];
		}

		// set ID and post_type before attempting generate_text
		if ( !isset( $post->ID ) )
			$post->ID = $postarr['ID'];
		if ( !isset( $post->post_type ) )
			$post->post_type = $this->name;

		$data['post_title'] = $this->generate_text( $this->core['title_template'], $post, $_POST );
		$data['post_name'] = $this->generate_text( $this->core['slug_template'], $post, $_POST );

		// uniqueify
		$data['post_name'] = sanitize_title( $data['post_name'] );
		$data['post_name'] = wp_unique_post_slug( $data['post_name'], $postarr['ID'], $data['post_status'], $data['post_type'], $data['post_parent'] );

		return $data;

	}


	/**
	 * Generate the title or slug by replacing placeholders with correct values.
	 */
	private function generate_text( $template = '', $post = null, $post_array = array() ) {

		// no postdata at all - no title
		if ( !$post || !is_object( $post ) )
			return '(no title)';

		// loop all fields for this content type
		$first = true;
		foreach( simian_get_fields( $post->post_type ) as $field ) {

			// if no template set, assume first field
			if ( $first && !$template ) {
				$template = '[' . $field['name'] . ']';
			}
			$first = false;

			// if one of these, ignore
			if ( in_array( $field['type'], array( 'connection', 'core', 'instructions' ) ) )
				continue;

			// taxonomies
			if ( $field['type'] === 'taxonomy' ) {

				$terms = isset( $post_array[$field['name']] ) ? array_map( 'absint', (array) $post_array[$field['name']] ) : array();
				if ( !$terms )
					$terms = get_the_terms( $post->ID, $field['name'] );

				$term_list = array();
				if ( $terms && !is_wp_error( $terms ) ) {
					foreach( $terms as $term ) {
						$term_list[] = $term->name;
					}
				}
				$value = implode( ', ', $term_list );

			// everything else is meta
			} else {

				// if post array, grab field value
				$value = isset( $post_array[$field['name']] ) ? sanitize_text_field( $post_array[$field['name']] ) : '';
				if ( !$value )
					$value = get_post_meta( $post->ID, $field['name'], true );

				// back-compat: if template matches an existing field, it's supposed to be the value of that field
				if ( $template === $field['name'] )
					$template = ( $value !== '' ) ? $value : get_post_meta( $post->ID, $field['name'], true );

			}

			// convert name or label of a field to the field value
			$template = str_replace( '%%' . $field['label'] . '%%', $value, $template );
			$template = str_replace( '%%' . $field['name']  . '%%', $value, $template );
			$template = str_replace( '[' . $field['label'] . ']',   $value, $template );
			$template = str_replace( '[' . $field['name']  . ']',   $value, $template );

		}

		// other available placeholders
		foreach( array( 'ID', 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) as $more ) {
			$template = str_replace( '%%' . $more . '%%', $post->$more, $template );
			$template = str_replace( '[' . $more . ']',   $post->$more, $template );
		}

		$post_title = $template;

		return $post_title;

	}


}