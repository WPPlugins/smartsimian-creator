<?php
/**
 * Miscellaneous hooks.
 */


/**
 * Save content index.
 *
 * This grabs all meta fields along with default info (title, slug, content, excerpt)
 * and stores them in a single meta key.
 */
function simian_save_content_index( $post_id, $post ) {

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) )
		return;

	$index = array();

	// get title, slug, content, excerpt
	if ( post_type_supports( $post->post_type, 'title' ) ) {
		$index[] = $post->post_title;
		$index[] = $post->post_name;
	}

	if ( post_type_supports( $post->post_type, 'editor' ) )
		$index[] = $post->post_content;

	if ( post_type_supports( $post->post_type, 'excerpt' ) )
		$index[] = $post->post_excerpt;

	// get all custom
	foreach( get_post_custom( $post_id ) as $key => $value ) {

		// ignore keys prefixed with _
		if ( substr( $key, 0, 1 ) !== '_' )
			$index[] = $value[0];

	}

	// need a string
	$index = implode( ' ', $index );

	// get rid of all newlines, tags, etc.
	$index = sanitize_text_field( $index );

	update_post_meta( $post_id, '_simian_index', $index );

}
add_action( 'save_post', 'simian_save_content_index', 11, 2 );


/**
 * If pretty permalinks are not enabled, put up a warning.
 */
function simian_permalinks_check() {
	$permalinks = get_option( 'permalink_structure', false );
	if ( !$permalinks ) {
		$page = get_current_screen();
		if ( isset( $page->base ) && $page->base == 'options-permalink' ) {
			?><div class="message updated"><p>To enable permalinks for <?php echo SIMIAN_NAME; ?>, select a permalink setting other than "Default", then hit "Save Changes" below.</p></div><?php
		} else {
			?><div class="message updated"><p><?php echo SIMIAN_NAME; ?> requires permalinks to be turned on. Visit <a href="options-permalink.php">Settings &rarr; Permalinks</a> to activate them.</p></div><?php
		}
	}
}
add_action( 'admin_notices', 'simian_permalinks_check' );


/**
 * Check licenses and spit out notice if any are expired.
 */
function simian_recurring_license_check() {
	if ( !simian_has_extensions() )
		return;

	if ( !simian_are_licenses_valid() ) {
		?><div class="message updated"><p>One or more of your SmartSimian licenses is invalid or expired. <a href="admin.php?page=simian-home&amp;tab=licenses">Manage your licenses here.</a></p></div><?php
	}
}
if ( SIMIAN_UPDATES )
	add_action( 'admin_notices', 'simian_recurring_license_check' );


/**
 * Check for plugin updates.
 */
function simian_check_for_updates() {

	$plugins = simian_get_plugins( $include_base = false );
	$licenses = get_option( 'simian_licenses' );

	foreach( $plugins as $plugin ) {

		// retrieve our license key from the DB
		$license = isset( $licenses[$plugin['slug']] ) ? trim( $licenses[$plugin['slug']] ) : '';

		// set up the updater
		$simian_updater = new Simian_Updater( SIMIAN_STORE_URL, $plugin['file'], array(
			'version' 	=> $plugin['version'],
			'license' 	=> $license,
			'item_name' => $plugin['name'],
			'author' 	=> $plugin['author']
		) );

		// add hooks to call the updater api
		$simian_updater->hook();

	}

}
if ( SIMIAN_UPDATES )
	add_action( 'admin_init', 'simian_check_for_updates' );


/**
 * Enqueues everywhere in the admin.
 */
function simian_global_admin_enqueues() {

	// general admin stylesheet
	wp_enqueue_style( 'simian-admin', SIMIAN_ASSETS . 'css/admin.css', array(), SIMIAN_VERSION );

}
add_action( 'admin_enqueue_scripts', 'simian_global_admin_enqueues' );


/**
 * Load hooks to only run on post add/edit pages.
 */
function simian_single_hooks() {

	// ajax args added to head for uploads
	add_action( 'admin_head', array( 'Simian_Uploads', 'head' ) );

	// enqueues
	add_action( 'admin_enqueue_scripts', 'simian_single_enqueues' );

}
add_action( 'load-post-new.php', 'simian_single_hooks' );
add_action( 'load-post.php',     'simian_single_hooks' );


/**
 * Enqueues for post add/edit pages.
 *
 * @todo the enqueue system will prevent a file from loading twice, but this might mess
 * with the order on built-in pages (i.e. for regular posts, if 'postbox' loads in the wrong
 * order, may create errors), so look out for issues.
 */
function simian_single_enqueues() {

	// needed defaults
	wp_enqueue_script( 'post' );
	wp_enqueue_script( 'postbox' );

	// add timepicker to jQuery UI Datepicker
	wp_register_script( 'jquery-ui-timepicker', SIMIAN_ASSETS . 'lib/timepicker/timepicker.js', array( 'jquery-ui-datepicker', 'jquery-ui-slider' ), SIMIAN_VERSION );

	// add our datetimepicker launcher
	wp_enqueue_script( 'simian-datetimepicker', SIMIAN_ASSETS . 'js/datetimepicker.js', array( 'jquery-ui-timepicker' ), SIMIAN_VERSION );

	// add our plupload launcher
	wp_enqueue_script( 'simian-plupload', SIMIAN_ASSETS . 'js/uploads.js', array( 'jquery', 'plupload-all' ), SIMIAN_VERSION );

	// general jQuery UI styles, plus specific styles for content edit screen

	if ( simian_get_option( 'toggle_jquery_ui_css', true ) )
		wp_enqueue_style( 'simian-jquery-ui-wp',  SIMIAN_ASSETS . 'css/jquery-ui.min.css', array(), SIMIAN_VERSION );

	wp_enqueue_style( 'simian-edit', SIMIAN_ASSETS . 'css/content-edit.css', array(), SIMIAN_VERSION );
	wp_enqueue_style( 'simian-uploads', SIMIAN_ASSETS . 'css/uploads.css', array(), SIMIAN_VERSION );
	wp_enqueue_style( 'jquery-datetime', SIMIAN_ASSETS . 'lib/timepicker/timepicker.css', array(), SIMIAN_VERSION );

}


/**
 * If any fields arrays exist in content types, copy them into the new fieldset component.
 *
 * @see core/init.php
 * @private
 */
function _simian_update_old_fields_arrays( $fieldsets ) {

	$content_types    = simian_get_data( 'content' );
	$editable_content = get_option( 'simian_content', array() );
	$built_in_keys    = array_diff( array_keys( $content_types ), array_keys( $editable_content ) );

	$fieldsets = get_option( 'simian_fieldset' );

	$more_fields = array();
	foreach( $built_in_keys as $name ) {
		$fields = isset( $content_types[$name]['fields'] ) ? $content_types[$name]['fields'] : array();
		if ( $fields && !isset( $fieldsets['_' . $name . '_fields'] ) ) {

			$more_fields['_' . $name . '_fields'] = array(
				'sysname' => $name . '_fields',
				'plural_label' => $name . ' fields',
				'description' => '',
				'label' => $name . ' fields',
				'content_types' => array( $name ),
				'context' => 'normal',
				'priority' => 'high',
				'box' => true,
				'fields' => $fields
			);

		}
	}
	if ( $more_fields )
		return array_merge( $fieldsets, $more_fields );
	else
		return $fieldsets;

}
add_filter( 'simian_fieldset_data', '_simian_update_old_fields_arrays' );