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
		add_action( 'admin_init', array( __CLASS__, 'add_settings' ) );

		// Add the options page:
		add_action( 'admin_menu', array( __CLASS__, 'add_options_page' ) );

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

	public static function add_settings () {
		add_settings_section(
			'csls_group',
			'Cross Site Link Search',
			array(__CLASS__, 'csls_group_callback'),
			'cross-site-link-search' );

		add_settings_field(
			'csls_expose_search',
			'Expose link search',
			array(__CLASS__, 'csls_expose_search_callback'),
			'cross-site-link-search',
			'csls_group'
		);

		add_settings_field(
			'csls_key',
			'Secret key',
			array(__CLASS__, 'csls_key_callback'),
			'cross-site-link-search',
			'csls_group'
		);

		add_settings_field(
			'csls_routes',
			'Routes to search',
			array(__CLASS__, 'csls_routes_callback'),
			'cross-site-link-search',
			'csls_group'
		);

		register_setting( 'csls_group', 'csls_expose_search' );
		register_setting( 'csls_group', 'csls_key' );
		register_setting( 'csls_group', 'csls_routes' );
	}

	public static function csls_group_callback () {

	}

	public static function csls_expose_search_callback () {
		echo '<input name="csls_expose_search" id="csls_expose_search" type="checkbox" value="1" class="code" ' . checked( 1, get_option( 'csls_expose_search' ), false ) . ' /> Whether this installation should allow other wordpress installs with the same key to see its link search';
	}

	public static function csls_key_callback () {
		echo '<input name="csls_key" id="csls_key" type="text" class="code" value="' . get_option( 'csls_key' )  . '" /> A key which matches that in the other installations. Generate with <code>openssl rand -base64 32</code>';
	}

	public static function csls_routes_callback () {
		echo '<input name="csls_routes" id="csls_routes" type="text" class="code" value="' . get_option( 'csls_routes' )  . '" /> A comma separated list of routes to wordpress installs to search for links in. Relative to the site root (e.g. /blog)';
	}

	public static function add_options_page() {
		add_options_page( 'Cross Site Link Search Options', 'Cross Site Link Search', 'manage_options', 'cross-site-link-search', array( __CLASS__, 'options' ) );
	}

	public static function options() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		echo '<form method="POST" action="options.php">';
		settings_fields( 'csls_group' );
		do_settings_sections( 'cross-site-link-search' );
		submit_button();
		echo '</form>';
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
