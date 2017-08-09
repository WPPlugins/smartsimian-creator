<?php
/**
 * Simian Taxonomy Creator.
 *
 * Taxonomies are groups of terms that can be assigned to
 * one or more content type.
 *
 * Sample array entry:
 *
 * 'deadline_type' => array(
 *		'post_types' => 'deadline',
 *		'slug' => 'deadlines/types',
 *		'singular_label' => 'Deadline Type',
 *		'plural_label' => 'Deadline Types'
 * );
 */
class Simian_Taxonomy {

	/**
	 * Taxonomy name.
	 */
	public $name;

	/**
	 * Post types taxonomy is placed on.
	 *
	 * @var array or string
	 */
	public $post_types;

	/**
	 * Args accepted by register() method.
	 */
	public $args;

	/**
	 * Constructor. Extract post_types from args array and declare all properties.
	 */
	public function __construct( $name = '', $args = array() ) {

		$this->name = $name;

		$this->post_types = isset( $args['post_types'] ) ? $args['post_types'] : '';
		unset( $args['post_types'] );

		if ( !is_array( $this->post_types ) )
			$this->post_types = array( $this->post_types );

		$this->args = $args;

	}

	/**
	 * Add the register() method to the init hook.
	 */
	public function init() {
		add_action( 'init', array( &$this, 'register' ), 100 );
	}

	/**
	 * Register the taxonomy. Take basic args, fold into advanced, fold into register_taxonomy.
	 */
	public function register() {

		// skip if taxonomy already exists
		if ( get_taxonomy( $this->name ) )
			return;

		// basic taxonomy options - defaults
		$defaults = array(
			'description' => '',
			'singular_label' => $this->name,
			'plural_label' => $this->name,
			'slug' => $this->name,
			'hierarchical' => true,
			'advanced' => array()
		);

		// fold defaults into $args
		$this->args = wp_parse_args( $this->args, $defaults );

		$this->args['singular_label'] = stripslashes( $this->args['singular_label'] );
		$this->args['plural_label']   = stripslashes( $this->args['plural_label'] );

		// force hierarchy
		$this->args['hierarchical']   = true;

		// build 'advanced' based on basic defaults
		$advanced_defaults = array(
			'public' => true,
			'show_in_nav_menus' => true,
			'hierarchical' => $this->args['hierarchical'],
			'rewrite' => $this->args['slug'] ? array( 'slug' => $this->args['slug'] ) : false,
			'label' => $this->args['plural_label'],
			'labels' => array(
				'name' => $this->args['plural_label'],
				'singular_name' => $this->args['singular_label'],
				'search_items' => 'Search ' . $this->args['plural_label'],
				'popular_items' => 'Popular ' . $this->args['plural_label'],
				'all_items' => 'All ' . $this->args['plural_label'],
				'parent_item' => 'Parent ' . $this->args['singular_label'],
				'parent_item_colon' => 'Parent ' . $this->args['singular_label'] . ':',
				'edit_item' => 'Edit ' . $this->args['singular_label'],
				'update_item' => 'Update ' . $this->args['singular_label'],
				'add_new_item' => 'Add New ' . $this->args['singular_label'],
				'new_item_name' => 'New ' . $this->args['singular_label'] . ' Name',
				'separate_items_with_commas' => 'Separate ' . $this->args['plural_label'] . ' with commas',
				'add_or_remove_items' => 'Add or remove ' . $this->args['plural_label'],
				'choose_from_most_used' => 'Choose from the most used ' . $this->args['plural_label'],
				'menu_name' => $this->args['plural_label']
			)
		);

		// fold advanced defaults into 'advanced' section in $args
		$finalized_args = wp_parse_args( $this->args['advanced'], $advanced_defaults );

		// register the taxonomy - WordPress will handle all defaults not yet set
		register_taxonomy( $this->name, $this->post_types, $finalized_args );

	}

}