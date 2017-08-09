<?php

class Simian_Admin_Connection_Form {


	/**
	 * Add hook.
	 */
	static function init() {

		// set up form
		add_filter( 'simian_admin-connection-form', array( __CLASS__, 'setup' ) );

		// sanitize data
		add_filter( 'simian_admin_save-connection', array( __CLASS__, 'sanitize' ) );

	}


	/**
	 * Register form, tabs and sections.
	 */
	static function setup( $form ) {

		$form->args['name_placeholder'] = 'Enter a name to describe this connection';

		$form->setup( array(
			'default'        => array(
				'sections'   => array(
					'options' => array(
						'fields' => array(
							array(
								'name'    => 'from',
								'label'   => 'From',
								'type'    => 'connection_picker',
								'options' => array( 'narrow_name' => 'from_query_vars', 'args' => array(
									'from' => '',
									'from_query_vars' => ''
								) )
							),
							array(
								'name'    => 'to',
								'label'   => 'To',
								'type'    => 'connection_picker',
								'options' => array( 'narrow_name' => 'to_query_vars', 'args' => array(
									'to' => '',
									'to_query_vars' => ''
								) )
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

		// debug point: what's being sent?
		// echo '<pre>'; print_r( $_POST ); echo '</pre>';
		// die();

		$whitelist = array(
			'label',
			'description',
			'from',
			'to'
		);

		// empty connection args array
		$sanitized = array();

		// sanitize $_POST
		foreach( $whitelist as $key ) {

			if ( !isset( $_POST[$key] ) ) $_POST[$key] = '';

			switch( $key ) {
				case 'label' :
				case 'description' :
					$sanitized[$key] = sanitize_text_field( $_POST[$key] );
					break;
				case 'from' :
				case 'to' :

					// sub-args
					$defaults = array(
						'object'   => '',
						'narrow'   => array()
					);
					$_POST[$key] = wp_parse_args( $_POST[$key], $defaults );

					// to or from object
					$sanitized[$key] = array_map( 'sanitize_key', (array) $_POST[$key]['object'] );

					// handle narrowing
					if ( !empty( $_POST[$key]['narrow'] ) ) {
						$sanitized[$key . '_query_vars'] = Simian_Query_UI::save_args( $_POST[$key]['narrow'], $sanitized[$key] );
					}

					break;
			}

		}

		if ( !isset( $_GET['item'] ) )
			$sanitized['sysname'] = simian_generate_sysname( $_POST['sysname'], 42 );

		// debug point: this should be the cleaned array ready for saving
		// echo '<pre>'; print_r( $sanitized ); echo '</pre>';
		// exit();

		return $sanitized;

	}


}
Simian_Admin_Connection_Form::init();