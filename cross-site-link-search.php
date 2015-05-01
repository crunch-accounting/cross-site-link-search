<?php
/**
 * Plugin Name: Cross Site Link Search
 * Description: Add other wordpress sites to the link search results.
 * Version: 0.2.0
 * Author: Crunch
 * Author URI: http://www.crunch.co.uk/
 *
 * @package CrossSiteLinkSearch
 * @author Crunch Accounting <info@crunch.co.uk>
 * @copyright Copyright (c) 2015, E-Crunch Ltd.
 */

require 'includes/settings.php';
require 'includes/link-search.php';

/**
 * Load the plugin.
 */
if ( is_admin() ) {
	add_action( 'plugins_loaded', array( 'Cross_Site_Link_Search', 'load' ) );
}

class Cross_Site_Link_Search {

	public static function load() {
		CSLS_Settings::load();
		CSLS_Link_Searcher::load();
	}
}
