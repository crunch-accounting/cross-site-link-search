<?php
/**
 * Plugin Name: Extra Link Search
 * Description: Allows one to add extra APIs to the link search box
 * Version: 0.0.1
 * Author: Crunch
 * Author URI: http://www.crunch.co.uk/
 *
 * @package ExtraLinkSearch
 * @author Crunch Accounting <info@crunch.co.uk>
 * @copyright Copyright (c) 2015, E-Crunch Ltd.
 */


/**
 * Load the plugin.
 */
if ( is_admin() ) {
	add_action( 'plugins_loaded', array( 'Extra_Link_Search', 'load' ) );
}

class Extra_Link_Search {

	private static $key;
	private static $routesToSearch;

	public static function load() {

		// Register the settings:
		add_action( 'admin_init', array( __CLASS__, 'add_settings' ) );

		// Add the options page:
		add_action( 'admin_menu', array( __CLASS__, 'add_options_page' ) );

		self::$key = get_option( 'els_key' );
		self::$routesToSearch = explode(',', get_option( 'els_routes' ));
		$exposeSearch = get_option( 'els_expose_search' );

		if ($exposeSearch) {
			// Expose the link search to other WP installs with the correct key:
			add_action( 'wp_ajax_extra_get_link_search_results', array( __CLASS__, 'extra_get_link_search_results' ) );
			add_action( 'wp_ajax_nopriv_extra_get_link_search_results', array( __CLASS__, 'extra_get_link_search_results' ) );
		}

		// Replace the default wp-link-ajax action.
		if ( isset( $_POST['search'] ) ) {
			remove_action( 'wp_ajax_wp-link-ajax', 'wp_link_ajax', 1 );
			add_action( 'wp_ajax_wp-link-ajax', array( __CLASS__, 'ajax_get_link_search_results' ), 1 );
		}

	}

	public static function add_settings () {
		add_settings_section(
			'els_group',
			'Extra Link Search',
			array(__CLASS__, 'els_group_callback'),
			'extra-link-search' );

		add_settings_field(
			'els_expose_search',
			'Expose link search',
			array(__CLASS__, 'els_expose_search_callback'),
			'extra-link-search',
			'els_group'
		);

		add_settings_field(
			'els_key',
			'Secret key',
			array(__CLASS__, 'els_key_callback'),
			'extra-link-search',
			'els_group'
		);

		add_settings_field(
			'els_routes',
			'Routes to search',
			array(__CLASS__, 'els_routes_callback'),
			'extra-link-search',
			'els_group'
		);

		register_setting( 'els_group', 'els_expose_search' );
		register_setting( 'els_group', 'els_key' );
		register_setting( 'els_group', 'els_routes' );
	}

	public static function els_group_callback () {

	}

	public static function els_expose_search_callback () {
		echo '<input name="els_expose_search" id="els_expose_search" type="checkbox" value="1" class="code" ' . checked( 1, get_option( 'els_expose_search' ), false ) . ' /> Whether this installation should allow other wordpress installs with the same key to see its link search';
	}

	public static function els_key_callback () {
		echo '<input name="els_key" id="els_key" type="text" class="code" value="' . get_option( 'els_key' )  . '" /> A key which matches that in the other installations. Generate with <code>openssl rand -base64 32</code>';
	}

	public static function els_routes_callback () {
		echo '<input name="els_routes" id="els_routes" type="text" class="code" value="' . get_option( 'els_routes' )  . '" /> A comma separated list of routes to wordpress installs to search for links in. Relative to the site root (e.g. /blog)';
	}

	public static function add_options_page() {
		add_options_page( 'Extra Link Search Options', 'Extra Link Search', 'manage_options', 'extra-link-search', array( __CLASS__, 'options' ) );
	}

	public static function options() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		echo '<form method="POST" action="options.php">';
		settings_fields( 'els_group' );
		do_settings_sections( 'extra-link-search' );
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

	public static function extra_get_link_search_results() {
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

		$request['action'] = 'extra_get_link_search_results';
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