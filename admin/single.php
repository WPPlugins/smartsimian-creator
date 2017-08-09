<?php
/**
 * Wrapper class for creating complex administration forms. Used for add/edit pages
 * for single component items.
 */
class Simian_Admin_Form {


	/**
	 * The current component.
	 * @var string
	 */
	public $component;


	/**
	 * The current component singular label.
	 * @var string
	 */
	public $label;


	/**
	 * Basic form args - form and submit attributes, etc.
	 * @var array
	 */
	public $args;


	/**
	 * The form's tabs in name => html pairs.
	 * @var array
	 */
	public $tabs;


	/**
	 * The form's sections, tab => ( section => html ).
	 * @var array
	 */
	public $sections;


	/**
	 * Constructor.
	 */
	public function __construct( $component = '', $args = array() ) {

		$this->component = sanitize_key( $component );
		$this->tabs      = array();
		$this->sections  = array();

		$component       = simian_get_component( $component );
		$this->label     = $component['singular'];

		$defaults = array(
			'id'               => '',
			'class'            => '',
			'dual_labels'      => false,
			'name_placeholder' => 'Enter name here',
			'tabs_before'      => '<h2 class="nav-tab-wrapper">',
			'tabs_after'       => '</h2>',
			'submit_value'     => 'Save',
			'submit_class'     => 'button-primary',
			'delete_link'      => simian_single_delete_link( $this->component, 'Delete' ),
			'form_end'         => wp_nonce_field( 'simian_' . $this->component . '_nonce', '_simian_nonce_' . $this->component, true, false )
		);
		$this->args = array_merge( $defaults, $args );

	}


	/**
	 * Setup function. Accepts an array that handily runs through
	 * the add_tab, add_section, add_fields functions.
	 */
	public function setup( $tabs ) {

		foreach( $tabs as $tab_name => $tab_args ) {

			$tab_args = wp_parse_args( $tab_args, array(
				'label'    => '',
				'sections' => ''
			) );

			$this->add_tab( array(
				'name' => $tab_name,
				'label' => $tab_args['label']
			) );

			foreach( $tab_args['sections'] as $section_name => $section_args ) {

				$section_args = wp_parse_args( $section_args, array(
					'label'     => '',
					'container' => 'table',
					'fields'    => array()
				) );

				$this->add_section( $tab_name, array(
					'name'          => $section_name,
					'label'         => $section_args['label'],
					'callback'      => array( $this, 'add_fields' ),
					'callback_args' => array(
						'fields'    => $section_args['fields'],
						'container' => $section_args['container']
					)
				) );

			}

		}

	}


	/**
	 * Add tab html to the $tab object array.
	 */
	public function add_tab( $args ) {

		$num = count( $this->tabs ) + 1;

		$defaults = array(
			'name'  => '',
			'label' => '',
			'class' => 'nav-tab',
		);
		extract( array_merge( $defaults, $args ) );

		$tab = '<a href="#" id="tab-' . $num . '" class="simian-tab ' . $class . '">' . $label . '</a>';

		$this->tabs[$name] = $tab;

	}


	/**
	 * Add section html to the $section object array.
	 */
	public function add_section( $tab, $args ) {

		$defaults = array(
			'name'          => '',
			'before'        => '',
			'after'         => '',
			'class'         => '',
			'callback'      => '',
			'callback_args' => ''
		);

		$label = isset( $args['label'] ) ? ( $args['label'] ? '<h3>' . $args['label'] . '</h3>' : '' ) : '';
		// if ( $label ) {
			$defaults['before'] = '<div class="metabox-holder"><div class="postbox">' . $label . '<div class="inside">';
			$defaults['after'] = '</div></div></div>';
		// }

		extract( wp_parse_args( $args, $defaults ) );

		$section  = '<div class="simian-section simian-section-' . $name . ' ' . $class . '">';
		$section .= $before;

		if ( is_callable( $callback ) ) {
			ob_start();

			if ( $callback_args )
				call_user_func( $callback, $callback_args );
			else
				call_user_func( $callback, $this );
				// back-compat

			$section .= ob_get_clean();
		}

		$section .= $after;
		$section .= '</div>';

		if ( !isset( $this->sections[$tab] ) )
			$this->sections[$tab] = array();

		$this->sections[$tab][$name] = $section;

	}


	/**
	 * Load a group of fields.
	 */
	public function add_fields( $args ) {
		extract( wp_parse_args( $args, array(
			'fields'    => array(),
			'container' => ''
		) ) );
		Simian_Admin_Fields::init( $this->component, $container, $fields );
	}


	/**
	 * Display the form, pulling from tabs and sections.
	 */
	public function display() {

		// debug mode: display saved info at the top of each item if query var simian_debug=true
		$simian_debug = isset( $_GET['simian_debug'] ) ? $_GET['simian_debug'] : '';
		if ( $simian_debug === 'true' )
			_pr( simian_get_item( $_GET['item'], str_replace( 'simian-', '', $_GET['page'] ) ) );

		extract( $this->args );

		?><form action="" method="post" id="<?php echo $id; ?>" class="simian simian-admin-form simian-admin-<?php echo $this->component; ?> <?php echo $class; ?>">

			<div class="simian-single-content">

				<div class="simian-title-box"><?php
					if ( $this->args['dual_labels'] )
						$labels = array(
							array(
								'name'    => 'label',
								'label'   => '',
								'type'    => 'big_labels',
								'options' => array( 'args' => array( 'singular_label' => 'Enter singular name here', 'plural_label' => 'Enter plural name here' ) )
							)
						);
					else
						$labels = array(
							array(
								'name'    => 'label',
								'label'   => '',
								'type'    => 'big_labels',
								'options' => array( 'args' => array( 'label' => $name_placeholder ) )
							)
						);
					$labels = apply_filters( 'simian_admin-' . $this->component . '-title_box', $labels, $this );
					Simian_Admin_Fields::init( $this->component, 'div', $labels );
				?></div><?php

				if ( count( $this->tabs ) > 1 ) {
					echo $tabs_before;
					$this->display_tabs();
					echo $tabs_after;
				}

				$this->display_sections();

			?></div>

			<div class="simian-single-sidebar"><?php
				$this->display_sidebar();
				do_action( 'simian_admin_single-' . $this->component . '-sidebar_boxes' );
				do_action( 'simian_admin_single_sidebar_boxes', $this->component );
			?></div><?php

			echo $form_end;

		?></form><?php

	}


	/**
	 * Display sidebar. Name/description/sysname stuff.
	 */
	private function display_sidebar() {
		?><div class="metabox-holder simian-section">
			<div class="inside">
				<div class="postbox"><?php

					$fields = apply_filters( 'simian_admin_single_sidebar_fields', array(
						array(
							'name'    => 'description',
							'label'   => 'Description',
							'type'    => 'longtext',
							'options' => array(
								'rows' => 2
							)
						),
						array(
							'name'    => 'sysname',
							'label'   => 'System Name',
							'type'    => 'text',
							'options' => array(
								'class'    => isset( $_GET['item'] ) ? ''   : 'simian-apply-name-sys',
								'disabled' => isset( $_GET['item'] ) ? true : false
							)
						)
					), $this->component );

					Simian_Admin_Fields::init( $this->component, 'div', $fields );
					$this->submit_container();
				?></div>
			</div>
		</div><?php
	}


	/**
	 * Display tabs.
	 */
	private function display_tabs() {
		foreach( $this->tabs as $tab_html ) {
			echo $tab_html;
		}
	}


	/**
	 * Display all tabs' sections.
	 */
	private function display_sections() {
		$count = 0;
		// go by $this->tabs and not $this->sections to ensure the same tab order for tabs and tab content
		foreach( array_keys( $this->tabs ) as $tab_name ) {
			$count++;

			if ( isset( $this->sections[$tab_name] ) ) {
				echo '<div id="tab-box-' . $count . '" class="simian-tab-box simian-tab-' . $tab_name . '">';
				foreach( $this->sections[$tab_name] as $section_name => $section_html ) {
					echo $section_html;
				}
				echo '</div>';
			}

		}
	}


	/**
	 * Submit button.
	 */
	private function submit_container( $show_delete = true ) {
		extract( $this->args );
		?><div class="simian-admin-submit"><?php
			if ( $show_delete )
				echo $delete_link;
			?><input type="submit" name="simian_admin_submit" class="<?php echo $submit_class; ?>" value="<?php echo $submit_value; ?>" />
			<div class="spinner" style="display:none;"></div>
		</div><?php
	}

}