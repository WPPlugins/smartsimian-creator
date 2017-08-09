<?php

class Simian_Admin_Fieldset_Form {


	/**
	 * Add hook.
	 */
	static function init() {

		// set up form
		add_filter( 'simian_admin-fieldset-form', array( __CLASS__, 'setup' ) );

		// sanitize data
		add_filter( 'simian_admin_save-fieldset', array( __CLASS__, 'sanitize' ) );

	}


	/**
	 * Register form, tabs and sections.
	 */
	static function setup( $form ) {

		$form->args['name_placeholder'] = 'Add a label for this field group here';

		$form->setup( array(
			'fields' => array(
				'label' => 'Fields',
				'sections' => array(
					'fields' => array(
						'container' => 'div',
						'fields' => array(
							array(
								'name'    => 'content_types',
								'label'   => 'Add fields to:',
								'type'    => 'post_type_checklist',
								'class'   => 'repeater-heading'
							),
							array(
								'name'    => 'fields',
								'type'    => 'field_repeater'
							)
						)
					)
				)
			),
			'advanced' => array(
				'label' => 'Settings',
				'sections' => array(
					'advanced' => array(
						'fields' => array(
							array(
								'name'    => 'context',
								'label'   => 'Location',
								'type'    => 'manual_list',
								'options' => array(
									'display' => 'radio',
									'values' => array( 'normal' => 'Default', 'side' => 'Sidebar' ),
									'default' => 'normal'
								)
							),
							array(
								'name'    => 'priority',
								'label'   => 'Importance',
								'type'    => 'manual_list',
								'options' => array(
									'display' => 'radio',
									'values' => array( 'high' => 'High', 'default' => 'Middle', 'low' => 'Low' ),
									'default' => 'high'
								)
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

		// debug point: what's being sent?
		// echo '<pre>'; print_r( $_POST ); echo '</pre>'; exit();

		// allowed fields and defaults
		$whitelist = array(
			'label'          => '',
			'description'    => '',
			'content_types'  => array(),
			'context'        => 'normal',
			'priority'       => 'high',
			'fields'         => array()
		);

		// make sure all fields are defined
		$dirty = wp_parse_args( $_POST, $whitelist );

		// sanitize dirty data
		foreach( $whitelist as $key => $default ) {

			switch( $key ) {

				case 'label' :
				case 'description' :
					$sanitized[$key] = sanitize_text_field( $dirty[$key] );
					break;

				case 'content_types' :
					$sanitized[$key] = array_map( 'sanitize_key', $dirty[$key] );
					break;

				case 'context' :
				case 'priority' :
					$sanitized[$key] = sanitize_key( $dirty[$key] );
					break;

				case 'fields' :
					$content_types = array_map( 'sanitize_key', (array) $dirty['content_types'] );
					$sanitized[$key] = Simian_Admin_Save::sanitize_fields( $dirty[$key], $content_types );
					break;

			}

		}

		if ( !isset( $_GET['item'] ) ) {
			if ( $_POST['sysname'] )
				$sanitized['sysname'] = simian_generate_sysname( $_POST['sysname'], 42 );
			// force name
			else
				$sanitized['sysname'] = 'no_name';
		}

		// debug point: this should be the cleaned array ready for saving
		// echo '<pre>'; print_r( $sanitized ); echo '</pre>'; exit();

		return $sanitized;

	}


}
Simian_Admin_Fieldset_Form::init();