<?php

class CSLS_Link_Searcher {

	private static $key;
	private static $routesToSearch;

	/**
	 * Registers actions to expose link searching to other sites
	 * and replaces the default link search with one which queries all
	 * the configured sites and returns merged results.
	 *
	 */
	public static function load () {

		// Load the settings:
		self::$key = get_option( 'csls_key' );
		self::$routesToSearch = explode(',', get_option( 'csls_routes' ));
		$exposeSearch = get_option( 'csls_expose_search' );

		if ($exposeSearch) {
			// Expose the link search to other WP installs with the correct key:
			add_action( 'wp_ajax_cross_site_link_search', array( __CLASS__, 'cross_site_link_search' ) );
			add_action( 'wp_ajax_nopriv_cross_site_link_search', array( __CLASS__, 'cross_site_link_search' ) );
		}

		// Replace the default wp-link-ajax action.
		if ( isset( $_POST['search'] ) ) {
			remove_action( 'wp_ajax_wp-link-ajax', 'wp_link_ajax', 1 );
			add_action( 'wp_ajax_wp-link-ajax', array( __CLASS__, 'ajax_get_link_search_results' ), 1 );
		}
	}

	/**
	 * Exposes a version of wp_link_ajax with the nonce check
	 * removed. Checks that the request contains a key matching
	 * the one in the plugin settings to ensure security.
	 *
	 * Note: this is exposed as an AJAX function, but the intention
	 * is for it only to be called from server side.
	 */
	public static function cross_site_link_search() {
		if (!isset($_POST['key']) || $_POST['key'] !== self::$key) {
			wp_die( -1 );
			return;
		}
		$result = self::insecure_wp_link_ajax();
		echo json_encode( $result )."\n";
		wp_die();
	}

	/**
	 * Returns search results.
	 *
	 * Copy of the `wp_ajax_wp_link_ajax` implementation in `wp-admin/includes/ajax-actions.php`
	 * without the nonce check.
	 *
	 */
	private static function insecure_wp_link_ajax() {

		if ( isset( $_POST['search'] ) ) {
			$results = array();

			$args = array();

			$args['s'] = wp_unslash( $_POST['search'] );
			$args['pagenum'] = ! empty( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;;

			if (!class_exists('_WP_Editors')) {
				require(ABSPATH . WPINC . '/class-wp-editor.php');
			}
			$results = _WP_Editors::wp_link_query( $args );

			return $results;
		}

		return null;
	}

	/**
	 * Function to replace the original `wp_link_ajax` function.
	 *
	 * This performs the same query as the original function, but also adds
	 * results from querying the other sites as configured.
	 */
	public static function ajax_get_link_search_results() {

		// Note: the nonce check is back here, because this function is
		// called from the front end so it should have the same security
		// measures as before:
		check_ajax_referer( 'internal-linking', '_ajax_linking_nonce' );

		$results = self::insecure_wp_link_ajax();

		if (!is_array($results)) {
			$results = [];
		}

		foreach (self::$routesToSearch as $route) {
			$otherResults = self::get_other_link_search_results($route);
			if (is_array($otherResults)) {
				$results = array_merge($otherResults, $results);
			}
		}

		if ( ! isset( $results ) || empty( $results ) ) {
			wp_die( 0 );
		}

		echo json_encode( $results )."\n";

		wp_die();
	}

	/**
	 * Makes a query to another wordpress installation running
	 * another copy of this plugin. Returns the results as an
	 * array so they can be merged with the other results.
	 */
	private static function get_other_link_search_results($route) {

		// Note: in PHP arrays are assigned by copy, not by reference.
		// Copy $_POST so we can mutate it without being bad citizens:
		$request = $_POST;

		$request['action'] = 'cross_site_link_search';
		$request['key'] = self::$key;

		// Forward the POST on to the other API:
		$ch = curl_init();
		curl_setopt( $ch , CURLOPT_SSL_VERIFYPEER , false    );
		curl_setopt( $ch , CURLOPT_RETURNTRANSFER , true     );
		curl_setopt( $ch , CURLOPT_POST           , true     );
		curl_setopt( $ch , CURLOPT_POSTFIELDS     , $request );
		curl_setopt( $ch , CURLOPT_URL            , 'https://'.$_SERVER["SERVER_NAME"].$route.'/wp-admin/admin-ajax.php' );

		$result = curl_exec($ch);

		curl_close($ch);

		return json_decode($result);
	}
}