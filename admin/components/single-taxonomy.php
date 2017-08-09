<?php

class Simian_Admin_Taxonomy_Form {


	/**
	 * Add hook.
	 */
	static function init() {

		// set up form
		add_filter( 'simian_admin-taxonomy-form', array( __CLASS__, 'setup' ) );

		// sanitize data
		add_filter( 'simian_admin_save-taxonomy', array( __CLASS__, 'sanitize' ) );

	}


	/**
	 * Register form, tabs and sections.
	 */
	static function setup( $form ) {
		$form->args['dual_labels'] = true;
		$form->setup( array(
			'default'        => array(
				'sections'   => array(
					'options' => array(
						'fields' => array(
							array(
								'name'  => 'post_types',
								'label' => 'Available for',
								'type'  => 'post_type_checklist'
							),
							/* array(
								'name'    => 'hierarchical',
								'label'   => 'Hierarchy',
								'type'    => 'bool',
								'options' => array( 'true_label' => 'Enable sub-terms' )
							), */
							array(
								'name'    => 'slug',
								'label'   => 'Archives',
								'type'    => 'taxonomy_rewrite',
								'options' => array( 'true_label' => 'Enable term archive pages' )
							)
						)
					)
				)
			)
		) );
		return $form;
	}


	/**
	 * Sanitize $_POST data upon form submission, and return.
	 * For new items, will include 'sysname'.
	 */
	static function sanitize() {

		$whitelist = array(
			'singular_label',
			'plural_label',
			'description',
			// 'hierarchical',
			'slug_checkbox',
			'slug',
			'post_types'
		);

		// sanitize $_POST
		$sanitized = array();
		foreach( $whitelist as $key ) {

			if ( !isset( $_POST[$key] ) ) $_POST[$key] = '';

			switch( $key ) {
				case 'singular_label' :
				case 'plural_label' :
				case 'description' :
					$sanitized[$key] = sanitize_text_field( $_POST[$key] );
					break;
				/* case 'hierarchical' :
					$sanitized[$key] = (bool) $_POST[$key];
					break; */
				case 'slug' :
					$checked = (bool) $_POST['slug_checkbox'];
					if ( !$checked ) {
						$sanitized[$key] = false;
					} else {
						$sanitized[$key] = preg_replace( '/[^a-z0-9_\-\/]/', '', strtolower( $_POST['slug'] ) );
						$sanitized[$key] = trim( $sanitized[$key], '/' );
					}
					break;
				case 'post_types' :
					$sanitized[$key] = is_array( $_POST[$key] ) ? array_map( 'sanitize_key', $_POST[$key] ) : array();
					break;
			}

		}

		// don't leave one of the labels empty
		if ( $sanitized['singular_label'] && !$sanitized['plural_label'] )
			$sanitized['plural_label'] = $sanitized['singular_label'];
		if ( $sanitized['plural_label'] && !$sanitized['singular_label'] )
			$sanitized['singular_label'] = $sanitized['plural_label'];

		if ( !isset( $_GET['item'] ) )
			$sanitized['sysname'] = simian_generate_sysname( $_POST['sysname'], 30 );

		return $sanitized;

	}


}
Simian_Admin_Taxonomy_Form::init();