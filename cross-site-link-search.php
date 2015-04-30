<?php
/**
 * Plugin Name: Cross Site Link Search
 * Description: Add other wordpress sites to the link search results.
 * Version: 0.0.3
 * Author: Crunch
 * Author URI: http://www.crunch.co.uk/
 *
 * @package CrossSiteLinkSearch
 * @author Crunch Accounting <info@crunch.co.uk>
 * @copyright Copyright (c) 2015, E-Crunch Ltd.
 */

require 'includes/settings.php';

/**
 * Load the plugin.
 */
if ( is_admin() ) {
	add_action( 'plugins_loaded', array( 'Cross_Site_Link_Search', 'load' ) );
}

class Cross_Site_Link_Search {

	private static $key;
	private static $routesToSearch;

	public static function load() {

		// Register the settings:
		add_action( 'admin_init', array( 'Cross_Site_Link_Search_Settings', 'add_settings' ) );

		// Add the options page:
		add_action( 'admin_menu', array( 'Cross_Site_Link_Search_Settings', 'add_options_page' ) );

		self::$key = get_option( 'csls_key' );
		self::$routesToSearch = explode(',', get_option( 'csls_routes' ));
		$exposeSearch = get_option( 'csls_expose_search' );

		if ($exposeSearch) {
			// Expose the link search to other WP installs with the correct key:
			add_action( 'wp_ajax_cross_site_link_search', array( __CLASS__, 'cross_site_link_search_fn' ) );
			add_action( 'wp_ajax_nopriv_cross_site_link_search', array( __CLASS__, 'cross_site_link_search_fn' ) );
		}

		// Replace the default wp-link-ajax action.
		if ( isset( $_POST['search'] ) ) {
			remove_action( 'wp_ajax_wp-link-ajax', 'wp_link_ajax', 1 );
			add_action( 'wp_ajax_wp-link-ajax', array( __CLASS__, 'ajax_get_link_search_results' ), 1 );
		}

	}

	public static function ajax_get_link_search_results() {
		
		$results = self::original_ajax_get_link_search_results();

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

	public static function cross_site_link_search_fn() {
		if ($_POST['key'] !== self::$key) {
			wp_die( -1 );
		}
		$result = self::original_ajax_get_link_search_results();
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
	private static function original_ajax_get_link_search_results() {
		
		global $wpdb;

		if ( isset( $_POST['search'] ) ) {
			$results = array();

			$args = array();

			$args['s'] = wp_unslash( $_POST['search'] );
			$args['pagenum'] = ! empty( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;;

			require(ABSPATH . WPINC . '/class-wp-editor.php');
			$results = _WP_Editors::wp_link_query( $args );
		}

		return $results;
	}

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
