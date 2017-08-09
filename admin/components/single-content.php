<?php

class Simian_Admin_Content_Form {


	/**
	 * Add hook.
	 */
	static function init() {

		// set up form
		add_filter( 'simian_admin-content-form', array( __CLASS__, 'setup' ) );

		// sanitize data
		add_filter( 'simian_admin_save-content', array( __CLASS__, 'sanitize' ) );

	}


	/**
	 * Register tabs and sections.
	 */
	static function setup( $form ) {
		$form->args['dual_labels'] = true;
		$form->setup( array(
			'default'        => array(
				'sections'   => array(
					'options' => array(
						'label'  => '',
						'fields' => array(
							array(
								'name'    => 'components',
								'label'   => 'Features',
								'type'    => 'content_type_components',
								'options' => array(
									'args' => array(
										'components'     => '',
										'title_template' => '',
										'slug_template'  => ''
									)
								)
							),
							array(
								'name'    => 'dashboard',
								'label'   => 'Visibility',
								'type'    => 'dashboard_options'
							),
							array(
								'name'    => 'public',
								'label'   => '',
								'type'    => 'public_options'
							)
						)
					)
				)
			)
		) );
		return $form;
	}


	/**
	 * Sanitize data before saving as option.
	 */
	static function sanitize() {

		// debug: what's being sent?
		// echo '<pre>'; print_r( $_POST ); echo '</pre>'; exit();

		// allowed fields and defaults
		$whitelist = array(
			'singular_label'   => '',
			'plural_label'     => '',
			'description'      => '',
			'dashboard'        => array(),
			'public'           => array(),
			'hierarchical'     => false,
			'components'       => array(),
			'title_template'   => '',
			'slug_template'    => ''
		);

		// make sure all fields are defined
		$dirty = wp_parse_args( $_POST, $whitelist );

		// sanitize dirty data
		foreach( $whitelist as $key => $default ) {

			switch( $key ) {

				case 'singular_label' :
				case 'plural_label' :
				case 'description' :
					$sanitized[$key] = sanitize_text_field( $dirty[$key] );
					break;

				case 'title_template' :
				case 'slug_template' :
					$sanitized[$key] = sanitize_text_field( $_POST[$key] );
					break;

				case 'dashboard' :

					// define sub-args
					$dirty[$key] = wp_parse_args( $dirty[$key], array(
						'show_ui' => false,
						'menu_icon' => 'post'
					) );

					// sanitize
					$sanitized[$key] = array(
						'show_ui' => (bool) $dirty[$key]['show_ui'],
						'menu_icon' => sanitize_key( $dirty[$key]['menu_icon'] )
					);

					break;

				case 'public' :

					// sub-arg defaults
					$public_defaults = array(
						'public' => false,
						'slug' => '',
						'has_archive' => false,
						'has_archive_slug' => '',
						'include_in_search' => false
					);

					// define sub-args
					$dirty[$key] = wp_parse_args( $dirty[$key], $public_defaults );

					// if public = false, don't save anything else
					if ( (bool) $dirty[$key]['public'] === false ) {
						$sanitized[$key] = $public_defaults;
						break;
					}

					// sanitize
					$sanitized[$key] = array(
						'public' => (bool) $dirty[$key]['public'],
						'slug' => simian_sanitize_slug( $dirty[$key]['slug'] ),
						'has_archive' => (bool) $dirty[$key]['has_archive'],
						'has_archive_slug' => simian_sanitize_slug( $dirty[$key]['has_archive_slug'] ),
						'include_in_search' => (bool) $dirty[$key]['include_in_search']
					);

					// if has archive = false, don't save slug
					if ( (bool) $sanitized[$key]['has_archive'] === false )
						$sanitized[$key]['has_archive_slug']= '';

					break;

				case 'hierarchical' :
					$sanitized[$key] = (bool) $dirty[$key];
					break;

				case 'components' :
					$sanitized[$key] = array_map( 'sanitize_key', $dirty[$key] );
					break;

			}

		}

		// don't leave one of the labels empty
		if ( $sanitized['singular_label'] && !$sanitized['plural_label'] )
			$sanitized['plural_label'] = $sanitized['singular_label'];
		if ( $sanitized['plural_label'] && !$sanitized['singular_label'] )
			$sanitized['singular_label'] = $sanitized['plural_label'];

		if ( !isset( $_GET['item'] ) )
			$sanitized['sysname'] = simian_generate_sysname( $_POST['sysname'], 18 );

		// debug point: this should be the cleaned array ready for saving
		// echo '<pre>'; print_r( $sanitized ); echo '</pre>'; exit();

		return $sanitized;

	}


}
Simian_Admin_Content_Form::init();