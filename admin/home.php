<?php
/**
 * Add the Creator top-level menu and home screen. The home screen includes the active components
 * list and a few extra helpful meta boxes.
 */
class Simian_Admin_Init {


	/**
	 * The home screen slug.
	 * @var string
	 */
	static public $hook;


	/**
	 * Add admin hooks.
	 */
	static public function init() {

		if ( !is_admin() || !SIMIAN_UI )
			return;

		// register menu pages
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );

		// register settings
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );

		// export or import
		add_action( 'admin_init', array( __CLASS__, 'export' ) );
		add_action( 'admin_init', array( __CLASS__, 'import' ) );

		// register enqueues
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueues' ) );

	}


	/**
	 * Register the top-level menu item and the Home submenu.
	 */
	static public function menu() {

		// add top-level menu item
		add_menu_page(
			'SmartSimian Creator',
			'Creator',
			'manage_options',
			'simian-home',
			'',
			SIMIAN_ASSETS . 'images/icons/simian.png',
			71
		);

		// add home submenu item. different label, callback
		self::$hook = add_submenu_page(
			'simian-home',
			'SmartSimian Creator',
			'Home',
			'manage_options',
			'simian-home',
			array( __CLASS__, 'html' )
		);

		// check for updated licenses and attempt activation
		add_action( 'load-' . self::$hook, array( __CLASS__, 'update_licenses' ) );

	}


	/**
	 * Home screen scripts and styles.
	 *
	 * @param $page The page hook. Check against self::$hook.
	 */
	static public function enqueues( $page ) {

		if ( self::$hook != $page )
			return;

		// add general simian styles
		wp_enqueue_style( 'simian-admin', SIMIAN_ASSETS . 'css/admin.css', array(), SIMIAN_VERSION );
		wp_enqueue_style( 'simian-admin-home', SIMIAN_ASSETS . 'css/admin-home.css', array(), SIMIAN_VERSION );

	}


	/**
	 * Register all settings, settings sections, and settings fields.
	 */
	static public function settings() {

		// general options
		register_setting( 'simian-options-group', 'simian_options', array( __CLASS__, 'sanitize_options' ) );
		add_settings_section( 'simian_options_section', '', '__return_false', self::$hook );
		add_settings_field(
			'simian-options-meta-search',
			'Enhance Search',
			array( __CLASS__, 'options_field' ),
			self::$hook,
			'simian_options_section',
			array(
				'name' => 'toggle_meta_search',
				'description' => 'Allow WordPress searches to search custom fields. For very large sites this may slow down searches.'
			)
		);
		add_settings_field(
			'simian-options-jquery-ui-css',
			'Enhance Styles',
			array( __CLASS__, 'options_field' ),
			self::$hook,
			'simian_options_section',
			array(
				'name' => 'toggle_jquery_ui_css',
				'description' => 'Enable the SmartSimian jQuery UI stylesheet. Turn this off if you\'re using another one.',
			)
		);

		// licenses
		if ( SIMIAN_UPDATES && simian_has_extensions() ) {

			register_setting( 'simian_licenses', 'simian_licenses', array( __CLASS__, 'sanitize_licenses' ) );
			add_settings_section( 'simian_licenses_section', '', '__return_false', self::$hook );

			foreach( simian_get_plugins( $include_base = false ) as $plugin ) {

				$slug  = $plugin['slug'] . '_license';
				$label = $plugin['name'] . ' License';

				// register the field
				add_settings_field( $slug, $label, array( __CLASS__, 'license_field' ), self::$hook, 'simian_licenses_section', array( 'plugin' => $plugin ) );

			}

		}

	}


	/**
	 * If the license tab was just updated, attempt to activate
	 * or deactivate the new license info.
	 */
	static public function update_licenses() {

		// only run on Licenses tab
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : '';
		if ( $tab !== 'licenses' )
			return;

		// only run if it was just updated
		$updated = isset( $_GET['settings-updated'] ) ? $_GET['settings-updated'] : '';
		if ( $updated !== 'true' )
			return;

		// get all SmartSimian plugins
		$plugins = simian_get_plugins( $include_base = false );
		$licenses = get_option( 'simian_licenses' );

		// activate newly entered license
		foreach( $plugins as $plugin ) {
			$plugin['license'] = $licenses[$plugin['slug']];
			self::toggle_license( 'activate_license', $plugin, $rebuild_saved = true );
		}

	}


	/**
	 * Home screen HTML container.
	 */
	static public function html() {
		?><div class="wrap simian">
			<?php screen_icon( 'simian' ); ?>
			<h2>SmartSimian Creator</h2><?php

				// welcome message
				$welcome = isset( $_GET['welcome'] ) ? $_GET['welcome'] : '';
				if ( $welcome == 'with-open-arms' ) {
					?><div class="updated simian-home-activated">
						<p>Thank you for installing the SmartSimian Creator!</p>
					</div><?php
				}

				// error messages
				settings_errors();

				$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'right-now';
				if ( $tab != 'licenses' || ! SIMIAN_UPDATES )
					$tab = 'right-now';

				if ( SIMIAN_UPDATES && simian_has_extensions() ) {
					?><h2 class="nav-tab-wrapper">
						<a href="admin.php?page=simian-home" id="tab-1" class="simian-tab nav-tab<?php echo ( $tab == 'right-now' ) ? ' nav-tab-active' : ''; ?>">Right Now</a>
						<a href="admin.php?page=simian-home&amp;tab=licenses" id="tab-2" class="simian-tab nav-tab<?php echo ( $tab == 'licenses' ) ? ' nav-tab-active' : ''; ?>">Licenses</a>
					</h2><?php
				}

				if ( $tab == 'right-now' ) {

					?><div class="simian-home-widgets metabox-holder">
						<div id="postbox-container-1" class="postbox-container">
							<div class="home-sortables meta-box-sortables"><?php
								self::meta_box( 'components_box', 'Components' );
								self::meta_box( 'options_box', 'Options' );
								// self::meta_box( 'news_box', 'SmartSimian News' );
							?></div><!-- .meta-box-sortables -->
						</div><!-- .postbox-container -->

						<div id='postbox-container-2' class='postbox-container'>
							<div class="home-sortables meta-box-sortables"><?php
								// self::meta_box( 'right_now_box', 'Right Now' );
								self::meta_box( 'resources_box', 'Status' );
								self::meta_box( 'import_export_box', 'Tools' );
							?></div><!-- .meta-box-sortables -->
						</div><!-- .postbox-container -->

					</div><!-- .metabox-holder --><?php

				} elseif ( $tab == 'licenses' ) {

					?><div class="simian-home-widgets metabox-holder">
						<div id="postbox-container-1" class="postbox-container">
							<div class="home-sortables meta-box-sortables"><?php
								self::meta_box( 'licenses_box', 'License Management' );
							?></div><!-- .meta-box-sortables -->
						</div><!-- .postbox-container -->

						<div id='postbox-container-2' class='postbox-container'>
							<div class="home-sortables meta-box-sortables"><?php
								self::meta_box( 'licenses_help_box', 'Help' );
							?></div><!-- .meta-box-sortables -->
						</div><!-- .postbox-container -->

					</div><!-- .metabox-holder --><?php

				}

		?></div><?php

	}


	/**
	 * Licenses tab.
	 */
	static private function licenses_box() {

		?><form action="options.php" method="post"><?php

			// set up options group declared in register_setting
			settings_fields( 'simian_licenses' );
			// wp_nonce_field( 'simian_license_nonce', 'simian_license_nonce' );

			// output settings html
			?><table class="form-table"><?php
				do_settings_fields( self::$hook, 'simian_licenses_section' );
			?></table><?php

			// generate submit and reset buttons
			submit_button( 'Update Licenses' );

		?></form><?php

	}


	/**
	 * Licenses help box.
	 */
	static private function licenses_help_box() {
		?><div class="simian-licenses-intro">
			<p>On this screen you can save your unique licenses for all installed <?php echo SIMIAN_NAME; ?> extensions.</p>
			<p>Your licenses can be found in your purchase receipt emails as well as in your account at <a href="<?php echo SIMIAN_STORE_URL; ?>"><?php echo SIMIAN_STORE_URL; ?></a>.</p>
			<p>Licenses enable you to access software updates for one year. Before a license expires, you will receive a reminder email with instructions on how to renew.</p>
		</div><?php
	}


	/**
	 * Meta box wrapper.
	 */
	static private function meta_box( $func, $label ) {
		?><div id="simian-home-<?php echo sanitize_title( $func ); ?>" class="postbox">
			<h3 class="hndle"><span><?php echo $label; ?></span></h3>
			<div class="inside"><?php
				if ( is_callable( array( __CLASS__, $func ) ) ) call_user_func( array( __CLASS__, $func ) );
			?></div><!-- .inside -->
		</div><!-- .postbox --><?php
	}


	/**
	 * Inside the Components meta box.
	 */
	static private function components_box() {

		?><table class="simian-right-now-table">
			<thead>
				<tr>
					<td>Amount</td>
					<td>Component</td>
					<td>Description</td>
				</tr>
			</thead>
			<tbody><?php
				$total = 0;
				foreach( simian_get_components() as $name ) {
					?><tr><?php

						$comp  = simian_get_component( $name );

						// components without data sets shouldn't be listed here
						$comp = wp_parse_args( $comp, array( 'data' => false ) );
						if ( !$comp['data'] )
							continue;

						$num   = count( ( $result = simian_get_data( $name ) ) ? $result : array() );
						$total = $total + $num;

						?><td class="num"><a href="<?php echo admin_url( 'admin.php?page=simian-' . $name ); ?>"><?php echo $num; ?></a></td>
						<td class="label"><a href="<?php echo admin_url( 'admin.php?page=simian-' . $name ); ?>"><?php echo _n( $comp['singular'], $comp['plural'], $num ); ?></a></td>
						<td class="desc"><?php echo $comp['description']; ?></td><?php

					?></tr><?php
				}
			?></tbody>
		</table><?php

	}


	/**
	 * Inside the general options meta box.
	 */
	static private function options_box() {

		?><form action="options.php" method="post"><?php

			// set up options group declared in register_setting
			settings_fields( 'simian-options-group' );

			// output settings html
			?><table class="form-table"><?php
				do_settings_fields( self::$hook, 'simian_options_section' );
			?></table><?php

			// generate submit and reset buttons
			?><p class="submit">
				<?php submit_button( 'Save Options', 'primary', 'simian_submit[save]', false ); ?> &nbsp;
				<?php submit_button( 'Reset to Defaults', 'secondary', 'simian_submit[reset]', false ); ?>
			</p>

		</form><?php

	}


	/**
	 * Inside the Resources box.
	 */
	static private function resources_box() {

		$c = simian_get_components();
		$e = simian_get_extensions();

		$num_c = count( $c );
		$num_e = count( $e );

		?><p>You are running Creator version <span><?php echo SIMIAN_VERSION . simian_get_revision( SIMIAN_FILE ); ?></span>
		with <span><?php echo $num_c; ?></span> <?php echo _n( 'component', 'components', $num_c ); ?>
		 and <span><?php echo $num_e; ?></span> <?php echo _n( 'extension', 'extensions', $num_e ); ?>.</p><?php

		if ( $num_e ) {
			?><table><?php
				foreach( $e as $ext ) {
					?><tr>
						<td><strong><?php echo $ext['name']; ?></strong></td>
						<td>v<?php echo $ext['version']; echo $ext['revision']; ?></td>
					</tr><?php
				}
			?></table><?php
		}

		?><div class="clear"></div><?php

	}


	/**
	 * Inside the Import/Export box.
	 */
	static private function import_export_box() {

		?><h4>Export</h4>
		<p>Download a file containing all of your SmartSimian <?php echo simian_friendly_component_list(); ?>.</p>
		<form action="" method="post"><?php
			wp_nonce_field( 'simian-export', '_wpnonce_simian_export' );
			?><p><input class="button-secondary" type="submit" name="simian_export" value="Export" /></p>
		</form>

		<h4>Import</h4>
		<form action="" method="post" enctype="multipart/form-data"><?php
			wp_nonce_field( 'simian-import', '_wpnonce_simian_import' );
			?><p><input type="file" name="simian_import_file" /></p>
			<p><input type="submit" class="button-secondary" name="simian_import" value="Import" /></p>
		</form><?php

	}


	/**
	 * Run the export.
	 */
	static public function export() {

		if ( !isset( $_POST['simian_export'] ) || !wp_verify_nonce( $_POST['_wpnonce_simian_export'], 'simian-export' ) )
			return;

		$export = array();
		foreach( simian_get_components( true ) as $component => $args ) {
			$has_data = isset( $args['data'] ) ? $args['data'] : false;
			if ( $has_data )
				$export['simian_' . $component] = get_option( 'simian_' . $component );
		}

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename=export.json.txt' );
		echo json_encode( $export );
		exit;

	}


	/**
	 * Run the import.
	 */
	static public function import() {

		if ( !isset( $_POST['simian_import'] ) )
			return;

		global $simian_import_results;
		$simian_import_results = array();
		add_action( 'admin_notices', array( __CLASS__, 'import_message' ) );

		// verify nonce
		if ( !wp_verify_nonce( $_POST['_wpnonce_simian_import'], 'simian-import' ) ) {
			$simian_import_results = 'The import failed a security check.';
			return;
		}

		// make sure everything's defined
		if ( !isset( $_FILES['simian_import_file'] ) ) {
			$simian_import_results = 'No file selected.';
			return;
		}

		$file_args = wp_parse_args( $_FILES['simian_import_file'], array(
			'type'     => '',
			'tmp_name' => '',
			'error'    => ''
		) );

		// plain text only
		if ( $file_args['type'] !== 'text/plain' ) {
			$simian_import_results = 'The file you\'ve attempted to upload is not valid.';
			return;
		}

		// verify file exists and is plain text and has no errors
		if ( $file_args['error'] !== 0 ) {
			$simian_import_results = 'File error: ' . $file_args['error'];
			return;
		}

		// no tmp_name
		if ( !file_exists( $file_args['tmp_name'] ) ) {
			$simian_import_results = 'File upload failed.';
			return;
		}

		// file as string
		$import = file_get_contents( $file_args['tmp_name'] );

		// set as associative array
		$import = json_decode( $import, true );

		$imported = array();
		$skipped  = array();

		$components = simian_get_components( true );

		foreach( $import as $component => $data ) {

			if ( !$data )
				continue;

			$component = str_replace( 'simian_', '', $component );
			$label = $components[$component]['plural'];
			$imported[$label] = array();

			// only whitelisted components continue
			if ( !in_array( $component, array_keys( $components ) ) )
				continue;

			// add items. will not overwrite existing items with same sysname
			foreach( $data as $item ) {

				// not set to update. force_name = true.
				$result = simian_add_item( $component, $item, false, true );

				if ( $result === array( '_errors' => 'An item with this name already exists.' ) )
					$skipped[$label] = $item;
				elseif( is_string( $result ) )
					$imported[$label][] = $item;
			}

		}

		$simian_import_results = array(
			'imported' => $imported,
			'skipped'  => $skipped
		);

	}


	/**
	 * Import result message.
	 */
	static public function import_message() {
		global $simian_import_results;

		if ( is_string( $simian_import_results ) ) {
			?><div class="error">
				<p><?php echo $simian_import_results; ?></p>
			</div><?php

		} elseif ( is_array( $simian_import_results ) ) {

			$imported = simian_friendly_import_results( 'imported' );
			$skipped  = simian_friendly_import_results( 'skipped' );

			$message = '';

			if ( $imported ) {
				$message .= 'Import complete! ' . $imported . ' were imported.';
				flush_rewrite_rules();
			}

			if ( !$imported && $skipped )
				$message .= 'Import not run. ';

			if ( $skipped )
				$message .= $skipped . ' had the same name as existing items on this site and were skipped.';

			?><div class="updated">
				<p><?php echo $message; ?></p>
			</div><?php

		}

	}


	/**
	 * Inside the News box.
	 */
	static private function news_box() {
		?><p>SmartSimian blog feed could go here.</p><?php
	}


	/**
	 * Field HTML for general options.
	 */
	static public function options_field( $args ) {

		// get field-population info
		$options = get_option( 'simian_options' );

		// item is true by default
		$default = array();
		$default[$args['name']] = 1;

		$options = wp_parse_args( $options, $default );

		?><label for="simian_options_<?php echo esc_attr( $args['name'] ); ?>" />
			<input
				type="checkbox"
				id="simian_options_<?php echo esc_attr( $args['name'] ); ?>"
				name="simian_options[<?php echo $args['name']; ?>]"
				value="1"
				<?php checked( $options[$args['name']] ); ?>
			/>&nbsp;&nbsp;<?php
			echo $args['description'];
		?></label><?php

	}


	/**
	 * Each license field.
	 */
	static public function license_field( $args ) {

		// values
		$values = get_option( 'simian_licenses' );

		// current license
		$name = $args['plugin']['slug'];

		if ( !isset( $values[$name] ) )
			$values[$name] = '';

		// status
		$status = simian_check_single_license( $name );

		?><input
			class="simian-license-field"
			type="text"
			id="simian_licenses_<?php echo $name; ?>"
			name="simian_licenses[<?php echo $name; ?>]"
			value="<?php echo $values[$name]; ?>"
		/><?php

		if ( $status == 'valid' ) {
			?><img class="simian-license-status" src="<?php echo SIMIAN_ASSETS; ?>images/check.png" alt="valid" /><?php
		} else {
			?><img class="simian-license-status" src="<?php echo SIMIAN_ASSETS; ?>images/cross.png" alt="invalid" /><?php
		}

	}


	/**
	 * Sanitize general options.
	 */
	static public function sanitize_options( $input ) {

		// set return value
		$clean = array();

		// default value
		$default = array(
			'toggle_meta_search' => true,
			'toggle_jquery_ui_css' => true
		);

		// save or reset
		$submit = isset( $_POST['simian_submit'] ) ? $_POST['simian_submit'] : false;

		if ( isset( $submit['reset'] ) )
			$clean = $default;

		if ( isset( $submit['save'] ) ) {

			// ensure all values are set
			$input = wp_parse_args( $input, array(
				'toggle_meta_search'   => false,
				'toggle_jquery_ui_css' => false
			) );

			// remove unallowed values and sanitize
			foreach( $input as $key => $value ) {
				if ( in_array( $key, array_keys( $default ) ) )
					$clean[$key] = (bool) $value;

			}

		}

		// return data
		return $clean;

	}


	/**
	 * Sanitize a license.
	 *
	 * Originally we handled the activaton/deactivation here, but now we
	 * check for settings-updated=true and handle it there.
	 */
	static function sanitize_licenses( $new ) {

		$old = get_option( 'simian_licenses', array() );
		foreach( $old as $plugin => $license ) {

			// if new license is changed or blank, deactivate the old while we still have it
			if ( !$new[$plugin] || $new[$plugin] != $license ) {

				$plugin_info = simian_get_plugin( $plugin );
				$plugin_info['license'] = $license;

				self::toggle_license( 'deactivate_license', $plugin_info );

			}

		}

		return array_map( 'sanitize_key', $new );

	}


	/**
	 * Activate or deactive a license.
	 */
	private static function toggle_license( $action, $plugin = array(), $rebuild_saved = false ) {

		$license = $plugin['license'];

		$api_params = array(
			'edd_action' => $action,
			'license'    => $license,
			'item_name'  => urlencode( $plugin['name'] )
		);

		$response = wp_remote_get( add_query_arg( $api_params, SIMIAN_STORE_URL ), array(
			'timeout'   => 15,
			'sslverify' => false
		) );

		// if error, don't do anything
		if ( is_wp_error( $response ) )
			return $response;

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( !isset( $license_data->license ) )
			$license_data->license = 'nonexistent';

		// if 'failed', something went wrong so don't update status
		if ( $license_data->license == 'failed' )
			return;

		// get existing license statuses
		$status = simian_check_licenses( $rebuild_saved );

		// update with new status
		$status[$plugin['slug']] = $license_data->license;

		// update transient
		set_transient( 'simian_licenses_status', $status, 86400 );

	}


}
Simian_Admin_Init::init();