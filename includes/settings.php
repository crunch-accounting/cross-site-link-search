<?php

class CSLS_Settings {

	public static function load () {
		// Register the settings:
		add_action( 'admin_init', array( __CLASS__, 'add_settings' ) );

		// Add the options page:
		add_action( 'admin_menu', array( __CLASS__, 'add_options_page' ) );
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
		echo '<input name="csls_expose_search" id="csls_expose_search" type="checkbox" value="1" class="code" ' . checked( 1, get_option( 'csls_expose_search' ), false ) . ' />';
		echo 'Whether this installation should allow other wordpress installs with the same key to see its link search';
	}

	public static function csls_key_callback () {
		echo '<input name="csls_key" id="csls_key" type="text" class="code" value="' . get_option( 'csls_key' )  . '" />';
		echo 'A key which matches that in the other installations. Generate with <code>openssl rand -base64 32</code>';
	}

	public static function csls_routes_callback () {
		echo '<input name="csls_routes" id="csls_routes" type="text" class="code" value="' . get_option( 'csls_routes' )  . '" />';
		echo 'A comma separated list of routes to wordpress installs to search for links in.';
		echo 'These can either be absolute (e.g. https://myblog.wordpress.com) or relative to the site root (e.g. /blog).';
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
	
}