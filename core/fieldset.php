<?php
/**
 * Simian Custom Fields Creator.
 *
 * This loads a custom field set and assigns it to its content type.
 *
 * includes/fields.php, meanwhile, is a static class containing the functions
 * used to build all the fields themselves.
 */
class Simian_Fieldset {


	/**
	 * System name of the fieldset.
	 * @var string
	 */
	public $name;


	/**
	 * Fields array of the fieldset.
	 * @var array
	 */
	public $fields;


	/**
	 * Additional args.
	 * @var array
	 */
	public $args;


	/**
	 * Constructor. Define vars.
	 */
	public function __construct( $name = '', $args = array() ) {

		// define args
		$args = wp_parse_args( $args, array(
			'label'          => $name,
			'description'    => '',
			'content_types'  => array(),
			'context'        => 'normal',
			'priority'       => 'high',
			'fields'         => array()
		) );

		foreach( $args['fields'] as $key => $field ) {

			// define field args
			$field = wp_parse_args( $field, array(
				'name'    => '',
				'label'   => '',
				'type'    => '',
				'note'    => '',
				'options' => array()
			) );

			// fields must have a name
			if ( !$field['name'] )
				unset( $args['fields'][$key] );

			$args['fields'][$key] = $field;

		}

		// define properties
		$this->name = $name;
		$this->fields = $args['fields'];
		unset( $args['fields'] );
		$this->args = $args;

	}


	/**
	 * Add hooks.
	 */
	public function init() {

		// display meta boxes
		add_action( 'add_meta_boxes', array( &$this, 'register' ) );

		if ( in_array( 'attachment', $this->args['content_types'] ) ) {
			add_action( 'edit_attachment', array( &$this, 'save_fields' ) );
			add_action( 'add_attachment',  array( &$this, 'save_fields' ) );
		}

		// save meta boxes
		add_action( 'save_post', array( &$this, 'save_fields' ), 10, 2 );

	}


	/**
	 * Add meta boxes per post type.
	 */
	public function register() {

		// add meta box to each related content type and init Simian_Fields to generate the fields
		foreach( $this->args['content_types'] as $post_type ) {
			add_meta_box(
				$this->name . '-fieldset',
				$this->args['label'] ? stripslashes( $this->args['label'] ) : '&nbsp;',
				array( 'Simian_Fields', 'init' ),
				$post_type,
				$this->args['context'],
				$this->args['priority'],
				array(
					'name'   => $this->name,
					'fields' => $this->fields
				)
			);
		}

		// remove any duplicated meta boxes like taxonomies
		$this->remove();

	}


	/**
	 * Remove meta boxes that are overridden. For example, taxonomy boxes will
	 * appear by default, but if you add a taxonomy into a fieldset, we want the
	 * original box to disappear.
	 */
	public function remove() {

		foreach( $this->args['content_types'] as $post_type ) {
			foreach( $this->fields as $field ) {
				switch ( $field['type'] ) :

					case 'taxonomy' :

						// backwards compat
						if ( !isset( $field['options']['taxonomy'] ) ) $field['options']['taxonomy'] = '';
						$taxonomy = isset( $field['options']['name'] ) ? $field['options']['name'] : $field['options']['taxonomy'];

						$tax = get_taxonomy( $taxonomy );

						if ( $tax ) {
							if ( $tax->hierarchical )
								remove_meta_box( $taxonomy . 'div', $post_type, 'side' );
							else
								remove_meta_box( 'tagsdiv-' . $taxonomy, $post_type, 'side' );
						}

						break;

					case 'author' :

						// 'author' field type doesn't exist anymore, backwards compat
						remove_meta_box( $field['name'] . 'div', $post_type, 'normal' );

						break;

					case 'connection' :

						if ( isset( $field['options']['connection_type'] ) ) {
							remove_meta_box( 'p2p-from-' . $field['options']['connection_type'], $post_type, 'side' );
							remove_meta_box( 'p2p-to-' . $field['options']['connection_type'], $post_type, 'side' );
							remove_meta_box( 'p2p-any-' . $field['options']['connection_type'], $post_type, 'side' );
						}
						break;

				endswitch;

			}
		}

	}


	/**
	 * Save all fields in this meta box. This will only fully run on backend add new/edit pages
	 * and checks user permissions, nonce, etc.
	 */
	public function save_fields( $post_id, $post = null ) {

		// handle attachments
		if ( is_null( $post ) ) {
			$post = get_post( $post_id );
		}

		// is this a relevant post type?
		if ( !in_array( $post->post_type, $this->args['content_types'] ) )
			return $post_id;

		// get post type object
		$post_type = get_post_type_object( $post->post_type );

		// can user edit the post?
		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
			return $post_id;

		// check this fieldset's nonce
		if ( !isset( $_POST['_simian_nonce_fields_' . $this->name] ) || !wp_verify_nonce( $_POST['_simian_nonce_fields_' . $this->name], 'simian_fields_nonce' ) )
			return $post_id;

		// loop through each field
		foreach( $this->fields as $field ) {

			// if a meta field, save. other fields will save automatically.
			if ( !in_array( $field['type'], array( 'taxonomy', 'connection', 'core' ) ) )
				Simian_Save::meta( $post_id, $field );

			// non-hierarchical taxonomies need to be handled manually.
			// otherwise WP won't inval the term ids, resulting in WP thinking the id is the term name. it's stupid.
			if ( $field['type'] == 'taxonomy' ) {

				$tax_name = isset( $field['options']['taxonomy'] ) ? sanitize_key( $field['options']['taxonomy'] ) : false;
				if ( !$tax_name )
					continue;

				$tax = get_taxonomy( $tax_name );
				if ( !$tax )
					continue;

				if ( !$tax->hierarchical ) {

					// get selected terms
					$values = isset( $_POST['simian_tag_input'][$tax_name] ) ? (array) $_POST['simian_tag_input'][$tax_name] : array();

					if ( !$values )
						continue;

					// sanitize
					$values = array_unique( array_map( 'intval', $values ) );

					// save
					wp_set_object_terms( $post_id, $values, $tax_name );

				}

			}

		}

	}

}