<?php
/**
 * Set of helper functions dedicated to Simian's implementation of PlUpload.
 */
class Simian_Uploads {

	/**
	 * Add always-on hooks.
	 */
	public static function init() {

		// enable ajax handler for uploads
		add_action( 'wp_ajax_simian_plupload', array( __CLASS__, 'handler' ) );
		add_action( 'wp_ajax_nopriv_simian_plupload', array( __CLASS__, 'handler' ) );

	}

	public static function head() {

		global $post;

		// get currently-editing ID
		$display = isset( $_GET['display'] ) ? $_GET['display'] : '';
		$parent_id = 0;
		if ( $display == 'form' ) {
			if ( isset( $_GET['entry'] ) )
				$parent_id = (int) $_GET['entry'];
		} else {
			global $post;
			if ( $post )
				$parent_id = $post->ID;
		}

		// @todo can use the localize function to only load this when submission js is loaded
		$plupload_init = array(
			'runtimes'            => 'html5,silverlight,flash,html4',
			'browse_button'       => '-plupload-browse-button',
			'container'           => '-plupload-upload-ui',
			'drop_element'        => 'drag-drop-area',
			'file_data_name'      => '-async-upload',
			'multiple_queues'     => true,
			'max_file_size'       => is_admin() ? wp_max_upload_size() . 'b' : self::wp_max_upload_size() . 'b',
			'url'                 => admin_url( 'admin-ajax.php' ),
			'flash_swf_url'       => includes_url( 'js/plupload/plupload.flash.swf' ),
			'silverlight_xap_url' => includes_url( 'js/plupload/plupload.silverlight.xap' ),
			'filters'             => array( array( 'title' => 'Allowed Files', 'extensions' => '*' ) ),
			'multipart'           => true,
			'urlstream_upload'    => true,
			'multi_selection'     => false, // added per-uploader
			// additional post data to send to our ajax hook
			'multipart_params'    => array(
				'_ajax_nonce' => '', // added per-uploader
				'action'      => 'simian_plupload', // the ajax action name
				'fileid'      => 0, // added per-uploader
				'parent_id'   => $parent_id
			),
		);

		?><script type="text/javascript">
			var base_plupload_config=<?php echo json_encode( $plupload_init ); ?>;
		</script><?php

	}

	public static function handler() {

		// check ajax nonce
		$fileid = $_POST["fileid"];
		$parent_id = $_POST['parent_id'];
		check_ajax_referer( $fileid . '-plupload_nonce' );

		global $simian_post_type;

		// get post type of attachment parent
		// in backend - get global $post, in frontend - if new, get simian_post_type, if existing, get from $_GET['entry']
		$post = get_post( $parent_id );
		if ( $post )
			$parent_post_type = $post->post_type;
		else
			$parent_post_type = $simian_post_type;

		// get field data
		$the_field = simian_get_field( $fileid, $parent_post_type );

		// check file type
		$file_type = wp_check_filetype( basename( $_FILES[$fileid . '-async-upload']['name'] ) );
		$allowed = isset( $the_field['options']['file_types'] ) ? $the_field['options']['file_types'] : array();
		if ( !empty( $allowed ) ) {
			if ( !in_array( $file_type['type'], $allowed ) ) {
				echo 'File extension error.';
				exit;
			}
		}

		// check file size
		$bytes = $_FILES[$fileid . '-async-upload']['size'];
		$max_size = isset( $the_field['options']['file_size'] ) ? (int) $the_field['options']['file_size'] : 0;
		if ( $max_size ) {
			$max_size_bytes = $max_size * 1024 * 1024;
			if ( $max_size_bytes < $bytes ) {
				echo 'File size error.';
				exit;
			}
		}

		// handle file upload
		$status = wp_handle_upload( $_FILES[$fileid . '-async-upload'], array(
			'test_form' => false,
			'action' => 'simian_plupload'
		) );

		// errors?
		if ( isset( $status['error'] ) ) {
			echo $status['error'];
			exit;
		}

		// insert file as attachment
		$attachment = array(
			'guid'           => $status['url'],
			'post_mime_type' => $status['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $status['url'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit'
		);
		$id = wp_insert_attachment( $attachment, $status['file'], $parent_id );

		// generate image sizes
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$attach_data = wp_generate_attachment_metadata( $id, $status['file'] );
		wp_update_attachment_metadata( $id, $attach_data );

		// send the uploaded file attachment ID and URL in response
		$response = array(
			'attach_id' => $id,
			'url' => $status['url']
		);
		echo json_encode( $response );
		exit;

	}


	/**
	 * WP's version of this only loads in the admin, so we just duplicate it here.
	 */
	private static function wp_max_upload_size() {
		$u_bytes = self::wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
		$p_bytes = self::wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
		$bytes = apply_filters( 'simian_upload_size_limit', min($u_bytes, $p_bytes), $u_bytes, $p_bytes );
		return $bytes;
	}


	/**
	 * Same with this.
	 */
	private static function wp_convert_hr_to_bytes( $size ) {
		$size = strtolower($size);
		$bytes = (int) $size;
		if ( strpos($size, 'k') !== false )
			$bytes = intval($size) * 1024;
		elseif ( strpos($size, 'm') !== false )
			$bytes = intval($size) * 1024 * 1024;
		elseif ( strpos($size, 'g') !== false )
			$bytes = intval($size) * 1024 * 1024 * 1024;
		return $bytes;
	}

}
Simian_Uploads::init();