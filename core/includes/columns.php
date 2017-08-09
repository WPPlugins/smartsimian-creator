<?php
/**
 * Build out custom columns on the admin list page of a content type.
 * Static-based helper class for Simian_Content.
 *
 * Instead of letting the client choose which fields should be columns,
 * this class attempts to streamline column creation by marking all*
 * fields as columns, then hiding all but the first ten. The clients
 * could then manually change the columns via the Screen Options tab if
 * they wish.
 *
 * In addition to the custom fields, will also include core WP columns
 * and connection info where appropriate. *Does NOT include wysiwyg or
 * textarea fields.
 */
class Simian_Columns {

	/**
	 * Array of admin columns in $name => array( $label, $type ) or $name => $label format.
	 */
	public static $columns;


	/**
	 * Build the columns array and hook it in to the required WordPress hooks.
	 */
	static public function init( $args = array() ) {

		// build columns
		self::$columns = self::build_columns( $args );

		// register the columns
		add_filter( 'manage_edit-' . $args['type'] . '_columns', array( __CLASS__, 'return_column_headings' ) );

		// echo each column's content
		add_action( 'manage_' . $args['type'] . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );

		// set columns as sortable
		add_filter( 'manage_edit-' . $args['type'] . '_sortable_columns', array( __CLASS__, 'return_sortable_columns' ) );

		// set sorting rules
		add_filter( 'request', array( __CLASS__, 'sortable_columns_orderby' ) );

	}


	/**
	 * Create array of post columns for this content type based on fields, connections,
	 * and which WordPress components were included.  Register everything as a column
	 * except textareas and wysiwygs. If there are too many columns, hide some in the
	 * Screen Options tab.
	 */
	static private function build_columns( $args ) {
		extract( $args );

		// get all types of columns
		$default_columns    = array( 'cb' => '<input type="checkbox" />', 'title' => 'Title' );
		$field_columns      = self::get_field_columns( $fields, $title_template );
		$connection_columns = self::get_connection_columns( $args );
		$core_columns       = self::get_core_columns( $supports );

		// Merge all columns
		$columns = array_merge( $default_columns, $field_columns, $connection_columns, $core_columns );

		$total_columns = count( $columns );

		// If less than ten total, return all
		if ( $total_columns <= 10 )
			return apply_filters( 'simian_manage_columns', $columns );

		// If default/core/connections are 0-10, fill with some fields, then hide the rest
		$leftover_space = 10 - ( count( $default_columns ) + count( $core_columns ) + count( $connection_columns ) );

		if ( $leftover_space >= 0 ) {
			self::hide_leftovers( $leftover_space, $field_columns, $type, $total_columns );
			return apply_filters( 'simian_manage_columns', $columns );
		}

		// We're still here if there are too many default/core/connections, so hide some connections
		$leftover_space = 10 - count( $default_columns ) + count( $core_columns );
		self::hide_leftovers( $leftover_space, $connection_columns, $type, $total_columns );

		return apply_filters( 'simian_manage_columns', $columns );

	}


	/**
	 * Hide leftover columns.
	 */
	static private function hide_leftovers( $leftover_space, $columns, $type, $total_columns ) {

		$user = wp_get_current_user();

		// get existing user hidden columns
		$already_hidden = get_user_option( 'manageedit-' . $type . 'columnshidden', $user->ID );

		if ( !$already_hidden )
			$already_hidden = array();

		// okay, some of the already hiddens don't even correspond to real fields anymore
		// this file is a quagmire.

		// remove any columns with no name
		foreach( $already_hidden as $key => $col ) {
			if ( $col == '' )
				unset( $already_hidden[$key] );
		}

		// if total columns minus what they've already hidden is more than 15, hide
		// @todo the logic of this and build_columns needs to be given a second look.
		$to_be_shown = $total_columns - count( $already_hidden );

		// var_dump( $already_hidden );

		// if they exist, this has already been run, no need to set it again
		if ( ( $to_be_shown > 10 ) ) {

			$count = 0;
			$hidden = array();
			foreach( $columns as $key => $label ) {
				$count++;

				if ( $count > $leftover_space )
					$hidden[] = $key;

			}

			update_user_option( $user->ID, 'manageedit-' . $type . 'columnshidden', $hidden, true );

		}

	}


	/**
	 * Populate field columns array.
	 */
	static private function get_field_columns( $fields = array(), $title_template = '' ) {

		$field_columns = array();
		$is_first_field = true;

		// don't allow text boxes or instructions
		$ignore = array( 'longtext', 'rich_text', 'textarea', 'wysiwyg', 'instructions' );

		foreach( $fields as $field ) {

			if ( in_array( $field['type'], $ignore ) )
				continue;

			// default handling, i.e. $columns['last_name'] = 'Last Name';
			if ( $field['type'] == 'taxonomy' )
				$field_columns[$field['options']['taxonomy']] = array(
					'label' => stripslashes( $field['label'] ),
					'type' => 'taxonomy',
					'args' => $field
				);
			elseif( $field['type'] == 'connection' )
				$field_columns[$field['options']['connection_type']] = array(
					'label' => stripslashes( $field['label'] ),
					'type' => 'connection',
					'args' => $field
				);
			else
				$field_columns[$field['name']] = array(
					'label' => stripslashes( $field['label'] ),
					'type' => 'meta',
					'args' => $field
				);

		}

		return $field_columns;

	}


	/**
	 * Populate connection columns array.
	 */
	static private function get_connection_columns( $args ) {

		$args = wp_parse_args( $args, array(
			'type'   => '',
			'fields' => array()
		) );
		$columns = array();
		$connections = simian_get_object_connection_types( $args['type'], 'objects' );

		foreach( $connections as $object ) {

			$label = esc_html( stripslashes( $object->label ) );

			// if a field is using this ctype, use the field's label instead
			if ( $new_label = simian_get_connection_label_from_field( $object->sysname, $args['fields'] ) )
				$label = $new_label;

			$columns[$object->sysname] = array(
				'label' => $label,
				'type'  => 'connection',
				'args'  => $object
			);
		}

		return $columns;

	}


	/**
	 * Populate core columns array.
	 */
	static private function get_core_columns( $supports ) {

		// Populate core columns array
		$core_columns = array();

		$maybe_core = array(
			'thumbnail' => 'Thumbnail',
			'author' => 'Author',
			'comments' => '<span class="vers"><img alt="Comments" src="' . esc_url( admin_url( 'images/comment-grey-bubble.png' ) ) . '" /></span>'
		);

		foreach( $maybe_core as $name => $label ) {
			if ( in_array( $name, $supports ) )
				$core_columns[$name] = $label;
		}

		// Two custom core columns below replace WP's "date" column

		// Always include created info
		$core_columns['simian_published'] = array(
			'label' => 'Published',
			'type' => 'simian_published',
			'args' => ''
		);

		// Also include latest revision info
		$core_columns['simian_modified'] = array(
			'label' => 'Modified',
			'type' => 'simian_modified',
			'args' => ''
		);

		// With core, WP handles everything, no need to make some big array in place of the label
		return apply_filters( 'simian_core_column_args', $core_columns );

	}


	/**
	 * Return column array to build headings.
	 */
	static public function return_column_headings() {
		$cleaned_columns = array();

		// only keep $key => $label pairs
		foreach( self::$columns as $key => $maybe_array ) {
			if ( is_array( $maybe_array ) )
				$cleaned_columns[$key] = $maybe_array['label'];
			else
				$cleaned_columns[$key] = $maybe_array;
		}

		return $cleaned_columns;
	}


	/**
	 * Return columns that can be sortable.
	 */
	static public function return_sortable_columns( $sortable_columns ) {

		foreach( self::$columns as $key => $value ) {

			// skip core columns
			if ( !is_array( $value ) )
				continue;

			// skip taxonomy and connection columns
			if ( isset( $value['type'] ) ) {
				if ( $value['type'] == 'taxonomy' || $value['type'] == 'connection' )
					continue;
			}

			// stick remainder into sortable array
			if ( isset( $value['label'] ) )
				$sortable_columns[$key] = $key;

		}

		return $sortable_columns;

	}


	/**
	 * Rules for how to order sortable columns.
	 */
	static public function sortable_columns_orderby( $vars ) {

		if ( isset( $vars['orderby'] ) ) {

			$type = isset( self::$columns[$vars['orderby']]['type'] ) ? self::$columns[$vars['orderby']]['type'] : '';
			if ( $type == 'meta' ) {

				$vars = array_merge( $vars, array(
					'meta_key' => $vars['orderby'],
					'orderby' => 'meta_value'
				) );

			}

		}

		return $vars;

	}


	/**
	 * Action function used to return the current column
	 * set in the admin_columns_content() loop.
	 */
	static public function column_content( $column, $post_id ) {

		global $post;

		// First determine type, then build output based on type.
		// Valid types: thumbnail, meta, taxonomy, connection

		// If it's a core WP column, WP will handle it
		if ( in_array( $column, array( 'cb', 'title', 'author', 'comments', 'date' ) ) )
			return;

		// Special case: thumbnails
		if ( $column == 'thumbnail' )
			$type = 'thumbnail';

		// The only ones left should have the type specified in the $columns array
		if ( !isset( $type ) ) {
			$column_info = self::$columns[$column];
			if ( is_array( $column_info ) )
				$type = $column_info['type'];
		}

		$output = '';

		// Now that we know the data type, determine how the column should populate
		switch( $type ) :

			case 'thumbnail' :
				if ( has_post_thumbnail() ) {
					echo wp_get_attachment_image( get_post_thumbnail_id(), array( 80, 60 ), true );
				}
				break;

			case 'simian_published' :

				if ( $post->post_date == '0000-00-00 00:00:00' ) {
					$output .= 'Not yet<br />published';

				} else {
					$t_time = get_the_time( 'Y/m/d\<\b\r \/\>g:i:s A' );
					$time = get_post_time( 'G', true, $post );
					$time_diff = time() - $time;
				}

				if ( $post->post_status == 'publish' ) {
					$output .= $t_time;

				} elseif( $post->post_status == 'future' ) {

					if ( $time_diff > 0 )
						$output .= '<strong class="attention">Missed schedule</strong> on<br />' . $t_time;
					else
						$output .= 'Scheduled for<br /> ' . $t_time;

				} else {
					$output .= 'Not yet<br />published';
				}

				break;

			case 'simian_modified' :

				$mod_time = get_the_modified_time( 'Y/m/d g:i:s A' );

				if ( $post->post_status == 'publish' && $post->post_date == $post->post_modified ) {
					$output .= 'Not since published';
				} else {
					$output .= $mod_time;

					$revisions = wp_get_post_revisions( $post->ID );
					if ( $revisions ) {
						$revision = array_shift( $revisions );
						$revision_author = get_the_author_meta( 'display_name', $revision->post_author );
						$output .= '<br />by <a href="user-edit.php/?user_id=' . $revision->post_author . '">' . $revision_author . '</a>';
					}
				}

				break;

			case 'meta' :

				// Get the post meta
				$post_meta = get_post_meta( $post_id, $column, true );

				$output = '';

				if ( isset( self::$columns[$column]['args'] ) ) {
					$field = self::$columns[$column]['args'];
					if ( $field['type'] == 'bool' ) {

						$true = ( isset( $field['options']['true_label'] ) ) ? $field['options']['true_label'] : 'Yes';
						$false  = ( isset( $field['options']['false_label'] ) ) ? $field['options']['false_label'] : 'No';
						$output = ( !$post_meta ) ? $false : $true;

					} elseif ( $field['type'] == 'file' ) {

						if ( $post_meta ) {
							// post_meta contains attachment id or array of ids
							if ( is_array( $post_meta ) ) {
								$attachment_ids = array();
								foreach( $post_meta as $attachment_id ) {
									$attachment_ids[] = (int) trim( $attachment_id );
								}
							} else {
								$attachment_ids = array( (int) trim( $post_meta ) );
							}

							// output attachment name
							$a_names = array();
							foreach( $attachment_ids as $a_id ) {
								$a_names[] = '<a href="' . admin_url( 'post.php?post=' . $a_id . '&action=edit' ) . '">' . get_the_title( $a_id ) . '</a>';
							}
							$output = implode( ', ', $a_names );
						}

					} elseif( $field['type'] == 'datetime' ) {

						if ( $post_meta ) {
							$which = isset( $field['options']['select'] ) ? $field['options']['select'] : 'both';
							$timestamp = strtotime( $post_meta );
							if ( $which == 'date' )
								$output = date( get_option( 'date_format' ), $timestamp );
							elseif ( $which == 'time' )
								$output = date( get_option( 'time_format' ), $timestamp );
							else
								$output = date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
						}

					}

				}

				if ( !$output ) {
					if ( !empty( $post_meta ) ) $output = $post_meta;
					else $output = '<span class="simian-faded">None</span>';
				}

				break;

			case 'taxonomy' :

				// Get the taxonomy terms
				$terms = get_the_terms( $post_id, $column );

				if ( !empty( $terms ) ) {

					$out = array();

					// Loop through each term, linking to the 'edit posts' page for the specific term.
					foreach ( $terms as $term ) {
						$out[] = sprintf( '<a href="%s">%s</a>',
							esc_url( add_query_arg( array( 'post_type' => $post->post_type, $column => $term->slug ), 'edit.php' ) ),
							esc_html( sanitize_term_field( 'name', $term->name, $term->term_id, $column, 'display' ) )
						);
					}

					// Join multiple terms with commas
					$output = join( ', ', $out );

				} else {

					$output = '<span class="simian-faded">None</span>';

				}

				break;

			// Support for p2p connections
			case 'connection' :

				$connected_posts = array();
				$connected_users = array();

				if ( simian_is_user_connection( $column ) ) {

					$connected = get_users( array(
						'connected_type' => $column,
						'number' => 11, // don't overload the column with too many
						'connected_items' => $post_id
					) );

					if ( $connected ) {
						$user_count = 0;
						foreach( $connected as $user ) {
							$user_count++;
							if ( $user_count > 10 )
								continue;
							$connected_users[] = '<a href="' . admin_url( 'user-edit.php?user_id=' . $user->ID ) . '">' . $user->display_name . '</a>';
						}
						$output = implode( ', ', $connected_users );
						if ( $user_count > 10 )
							$output .= ' (more)';

					} else {
						$output = '<span class="simian-faded">None</span>';
					}

				} else {

					global $post;
					$original_post = $post;
					$post = null;
					$more = false;

					$connected = get_posts( array(
						'post_type' => get_post_types(),
						'connected_type' => $column,
						'posts_per_page' => 3, // don't overload the column with too many
						'connected_items' => $post_id,
						'suppress_filters' => false
					) );

					if ( $connected ) {
						foreach( $connected as $post ){
							$connected_posts[] = '<a href="' . admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) . '">' . get_the_title( $post->ID ) . '</a>';
						}
						if ( isset( $connected_posts[2] ) ) {
							unset( $connected_posts[2] );
							$more = true;
						}
						$output = implode( ', ', $connected_posts );
					} else {
						$output = '<span class="simian-faded">None</span>';
					}

					if ( $more )
						$output .= ' ...';

					// reset $post
					$post = $original_post;

				}

				break;

			default :

				$output = apply_filters( 'simian_custom_column_content', $output, $column, $post_id );
				break;

		endswitch;

		echo $output;

	}

}