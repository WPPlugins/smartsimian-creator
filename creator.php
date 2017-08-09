<?php
/*
Plugin Name: SmartSimian Creator
Description: Take WordPress to the next level of website management. Design, build, and connect custom content.
Version: 2.0.1
Author: Pongos Interactive
Author URI: http://www.pongos.com
License: GPL2
Requires: PHP 5.3

Copyright 2012-2013 SmartSimian Software, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'SIMIAN_NAME',      'SmartSimian Creator' );
define( 'SIMIAN_SLUG',      'simian' );
define( 'SIMIAN_VERSION',   '2.0.1' );
define( 'SIMIAN_AUTHOR',    'SmartSimian Software, Inc.' );
define( 'SIMIAN_STORE_URL', 'http://www.smartsimian.com' );

// set to false to hide all admin UI
// default: true
if ( !defined( 'SIMIAN_UI' ) )
	define( 'SIMIAN_UI', true );

// set to false to ignore license and update checks
// (note that this means you will no longer be able to receive updates)
// default: true
if ( !defined( 'SIMIAN_UPDATES' ) )
	define( 'SIMIAN_UPDATES', true );

// set to true to show revision info; used in development
// default: false
if ( !defined( 'SIMIAN_SHOW_REVISION' ) )
	define( 'SIMIAN_SHOW_REVISION', false );

$simian_extensions = array();
$simian_components = $simian_default_components = array(
	'content' => array(
		'singular'    => 'Content Type',
		'plural'      => 'Content',
		'description' => 'Create custom content types.',
		'data'        => true,
		'class'       => 'Simian_Content',
		'admin'       => true,
		'toolbar'     => true
	),
	'fieldset' => array(
		'singular'    => 'Field Group',
		'plural'      => 'Fields',
		'description' => 'Create customizable groups of fields for your content types.',
		'data'        => true,
		'class'       => 'Simian_Fieldset',
		'admin'       => true,
		'toolbar'     => true
	),
	'connection' => array(
		'singular'    => 'Connection Type',
		'plural'      => 'Connections',
		'description' => 'Create direct post-to-post, post-to-user, and user-to-user connections.',
		'data'        => true,
		'class'       => 'Simian_Connection',
		'admin'       => true,
		'toolbar'     => true
	),
	'taxonomy' => array(
		'singular'    => 'Taxonomy',
		'plural'      => 'Taxonomies',
		'description' => 'Create lists of terms linked by a common theme, like categories or tags.',
		'data'        => true,
		'class'       => 'Simian_Taxonomy',
		'admin'       => true,
		'toolbar'     => true
	)
);

$abspath = plugin_dir_path( __FILE__ );
$absurl  = plugin_dir_url( __FILE__ );
define( 'SIMIAN_PATH',     $abspath );
define( 'SIMIAN_FILE',     $abspath . 'creator.php' );
define( 'SIMIAN_CORE',     $abspath . 'core/' );
define( 'SIMIAN_INCLUDES', $abspath . 'core/includes/' );
define( 'SIMIAN_ADMIN',    $abspath . 'admin/' );
define( 'SIMIAN_ASSETS',   $absurl  . 'assets/' );

// Load update checker
if ( SIMIAN_UPDATES )
	require_once( SIMIAN_INCLUDES . 'update.php' );

// Set activation/uninstall hooks
require_once( SIMIAN_INCLUDES . '/activation.php' );

// Load general API functions
require_once( SIMIAN_INCLUDES . 'functions.php' );

// Load p2p bootstrap
require_once( SIMIAN_INCLUDES . 'connections/load.php' );

// Load general includes
require_once( SIMIAN_INCLUDES . 'hooks.php' );
require_once( SIMIAN_INCLUDES . 'fields.php' );
require_once( SIMIAN_INCLUDES . 'uploads.php' );
require_once( SIMIAN_INCLUDES . 'columns.php' );
require_once( SIMIAN_INCLUDES . 'save.php' );
require_once( SIMIAN_INCLUDES . 'toolbar.php' );

// Toggle search mod
$options = get_option( 'simian_options', array(
	'toggle_meta_search' => true
) );
if ( $options['toggle_meta_search'] )
	require_once( SIMIAN_INCLUDES . 'wp-search.php' );

// Load default components
require_once( SIMIAN_CORE . 'content.php' );
require_once( SIMIAN_CORE . 'fieldset.php' );
require_once( SIMIAN_CORE . 'taxonomy.php' );
require_once( SIMIAN_CORE . 'connection.php' );

// Run components init
require_once( SIMIAN_CORE . 'init.php' );

if ( is_admin() ) {

	// Load general admin functions
	require_once( SIMIAN_ADMIN . 'includes/functions.php' );

	// Load general includes
	require_once( SIMIAN_ADMIN . 'includes/fields.php' );
	require_once( SIMIAN_ADMIN . 'includes/save.php' );
	require_once( SIMIAN_ADMIN . 'includes/repeater.php' );
	require_once( SIMIAN_ADMIN . 'includes/ajax.php' );

	// Initialize UI tools
	require_once( SIMIAN_ADMIN . 'tools/query-ui.php' );
	require_once( SIMIAN_ADMIN . 'tools/fields-ui.php' );

	// Initialize components UI
	require_once( SIMIAN_ADMIN . 'init.php' );

	// Initialize home screen
	require_once( SIMIAN_ADMIN . 'home.php' );

}

// Load WP's template.php on frontend
if ( !is_admin() )
	require_once( ABSPATH . '/wp-admin/includes/template.php' );