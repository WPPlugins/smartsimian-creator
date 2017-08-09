<?php
/**
 * Set of static functions hooked into the WordPress default search which allows all
 * meta values to be searched.
 */
class Simian_WP_Search {


	/**
	 * Check that we're in the admin, running a search.
	 */
	static public function init() {

		if ( !is_admin() ) {

			add_action( 'pre_get_posts', array( __CLASS__, 'check_frontend' ) );

		} else {

			// only run when a search is being performed
			$term = isset( $_GET['s'] ) ? $_GET['s'] : '';
			if ( !$term )
				return;

			// add hook to check for current page
			add_action( 'current_screen', array( __CLASS__, 'check_admin' ) );

		}

	}


	/**
	 * Check the current frontend screen, then maybe add filters.
	 */
	static public function check_frontend( $wp_query ) {

		// only run if there's a query
		if ( !$wp_query )
			return;

		// only main query
		if ( !$wp_query->is_main_query() )
			return;

		// only run on a search page
		if ( !$wp_query->is_search() )
			return;

		// only run when a search is being performed
		if ( !get_query_var( 's' ) )
			return;

		// add filters
		self::add_filters();

	}


	/**
	 * Check the current admin screen, then maybe add filters.
	 */
	static public function check_admin() {

		// only run on content type list pages
		global $current_screen;
		if ( $current_screen->base != 'edit' )
			return;

		// good to go
		self::add_filters();

	}


	/**
	 * Add query filters. Only runs if we're good to go. Each function will also
	 * check to make sure it's running on the main query only.
	 */
	static public function add_filters() {
		add_filter( 'posts_distinct',     array( __CLASS__, 'add_distinct' ),  10, 2 );
		add_filter( 'posts_join_request', array( __CLASS__, 'join_postmeta' ), 10, 2 );
		add_filter( 'posts_search',       array( __CLASS__, 'alter_search' ),  10, 2 );
	}


	/**
	 * Add 'DISTINCT' clause to query.
	 */
	static public function add_distinct( $distinct, $wp_query ) {

		if ( $wp_query->is_main_query() )
			return 'DISTINCT';

		return $distinct;

	}


	/**
	 * Force main post queries in backend to join with postmeta table.
	 */
	static public function join_postmeta( $join, $wp_query ) {

		if ( $wp_query->is_main_query() && strpos( $join, 'postmeta' ) === false ) {
			global $wpdb;
			return $join .= " LEFT JOIN $wpdb->postmeta ON ($wpdb->posts.ID = $wpdb->postmeta.post_id) ";
		}

		return $join;

	}


	/**
	 * Modify the search sql phrase to also search meta_value.
	 *
	 * Example -- change this:
	 *	AND (
	 *		((wp_posts.post_title LIKE '%word1%') OR (wp_posts.post_content LIKE '%word1%'))
	 *		AND
	 *		((wp_posts.post_title LIKE '%word2%') OR (wp_posts.post_content LIKE '%word2%'))
	 *	)
	 *
	 * To this:
	 * AND (
	 * 		((btmx_posts.post_title LIKE '%word1%') OR (btmx_posts.post_content LIKE '%word1%') OR (btmx_postmeta.meta_value LIKE '%word1%'))
	 *   	AND
	 *    	((btmx_posts.post_title LIKE '%word2%') OR (btmx_posts.post_content LIKE '%word2%') OR (btmx_postmeta.meta_value LIKE '%word2%'))
	 * )
	 *
	 * @see wp-includes/query.php above 'posts_search' filter
	 *
	 */
	static public function alter_search( $sql, $wp_query ) {

		if ( $wp_query->is_main_query() ) {

			global $wpdb;

			$search_terms = sanitize_text_field( $_GET['s'] );

			preg_match_all( '/".*?("|$)|((?<=[\r\n\t ",+])|^)[^\r\n\t ",+]+/', $search_terms, $matches );

			// this replaces _search_terms_tidy
			$search_terms = array();
			foreach( $matches[0] as $chunk_candidate ) {
				$search_terms[] = trim( $chunk_candidate, "\"'\n\r " );
			}

			$search = '';
			$searchand = '';
			foreach( (array) $search_terms as $term ) {
				$term = esc_sql( like_escape( $term ) );
				$search .= "{$searchand}(($wpdb->posts.post_title LIKE '%{$term}%') OR " .
					                    "($wpdb->posts.post_content LIKE '%{$term}%') OR " .
					                    "($wpdb->postmeta.meta_value LIKE '%{$term}%') " .
					                    ")";
				$searchand = ' AND ';
			}

			if ( !empty( $search ) ) {
				$search = " AND ({$search}) ";
				if ( !is_user_logged_in() )
					$search .= " AND ($wpdb->posts.post_password = '') ";
			}

			return $search;

		}

		return $sql;

	}


}
Simian_WP_Search::init();