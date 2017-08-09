<?php
/**
 * On activation, redirect to home screen with Welcome message.
 */
class Simian_Activate {

	static public function init() {
		register_activation_hook( SIMIAN_FILE, array( __CLASS__, 'activate' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect' ) );
	}

	static public function activate() {
		add_option( 'simian_redirect', true );
	}

	static public function redirect() {
		if ( get_option( 'simian_redirect', false ) ) {
			delete_option( 'simian_redirect' );
			wp_redirect( admin_url( 'admin.php?page=simian-home&welcome=with-open-arms' ) );
		}
	}

}
Simian_Activate::init();