<?php
/**
 * Load built-in implementation of Posts 2 Posts, which enables post-to-post and
 * post-to-user connections.
 */

define( 'P2P_PLUGIN_VERSION', '1.6.2' );
// version noted for reference
// actual usage of this constant removed in favor of SIMIAN_VERSION.
// see: box.php, tools-page.php

define( 'P2P_TEXTDOMAIN', 'simian' );

require_once dirname( __FILE__ ) . '/scb/load.php';

/**
 * Load P2P files upon scbFramework init.
 */
function simian_p2p_core_init() {

	// core
	require_once dirname( __FILE__ ) . '/core/init.php';

	// uninstall
	// register_uninstall_hook( SIMIAN_PATH . 'creator.php', array( 'P2P_Storage', 'uninstall' ) );

	// admin
	if ( is_admin() ) {
		P2P_Autoload::register( 'P2P_', dirname( __FILE__ ) . '/admin' );
		new P2P_Box_Factory;
		new P2P_Column_Factory;
		new P2P_Dropdown_Factory;
		// new P2P_Tools_Page;
	}

}
scb_init( 'simian_p2p_core_init' );


/**
 * Safe hook for calling p2p_register_connection_type().
 */
function _p2p_init() {
	do_action( 'p2p_init' );
}
add_action( 'wp_loaded', '_p2p_init' );


/**
 * Maybe install tables.
 */
function simian_p2p_maybe_install() {
	if ( !current_user_can( 'manage_options' ) )
		return;

	$current_ver = get_option( 'p2p_storage' );

	if ( $current_ver == P2P_Storage::$version )
		return;

	P2P_Storage::install();

	update_option( 'p2p_storage', P2P_Storage::$version );
}
add_action( 'admin_notices', 'simian_p2p_maybe_install' );