<?php
/**
 * Add a Creator node to the WP Toolbar, similar to the default "+ New" node. Runs automatically.
 */
class Simian_Toolbar {


	/**
	 * Add toolbar hooks if necessary.
	 */
	static public function init() {
		if ( SIMIAN_UI && is_admin() ) {
			add_action( 'admin_bar_menu', array( __CLASS__, 'add' ), 999 );
			add_action( 'admin_head',     array( __CLASS__, 'styles' ) );
		}
	}


	/**
	 * Add styles for the Creator toolbar items.
	 * (Just duplicating the "+" on the Add New node.)
	 */
	static public function styles() {
		if ( !current_user_can( 'manage_options' ) )
			return;
		?><style type="text/css">.simian-ab-icon { background-image: url('<?php echo includes_url(); ?>images/admin-bar-sprite.png'); background-position: -2px -182px; background-repeat: no-repeat; } #wp-admin-bar-simian.hover .simian-ab-icon { background-position:-2px -203px; }</style><?php
	}


	/**
	 * Add the Creator node to the WP Toolbar.
	 */
	static public function add( $toolbar ) {

		if ( !current_user_can( 'manage_options' ) )
			return;

		// top-level
		$toolbar->add_node( array(
			'id'    => 'simian',
			'title' => '<span class="ab-icon simian-ab-icon"></span><span class="ab-label">Create</span>',
			'href'  => admin_url( 'admin.php?page=simian-content&action=add-new' ),
			'meta'  => array(
				'class' => 'simian-home-link',
				'title' => 'SmartSimian Creator'
			)
		) );

		foreach( simian_get_components( $with_info = true ) as $component => $info ) {

			$args = wp_parse_args( $info, array(
				'toolbar' => false,
				'singular' => ''
			) );

			if ( $args['toolbar'] ) {
				$toolbar->add_node( array(
					'parent' => 'simian',
					'id'     => 'simian-' . $component,
					'title'  => $args['singular'],
					'href'   => admin_url( 'admin.php?page=simian-' . $component . '&action=add-new' )
				) );
			}
		}

	}


}
Simian_Toolbar::init();