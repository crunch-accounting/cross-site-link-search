<?php

class _WP_Editors {
    public static function wp_link_query () {
        return [array("testOutput" => "success")];
    }
}

class LinkSearchTest extends PHPUnit_Framework_TestCase
{
    public function setUp() {
        \WP_Mock::setUp();

        \WP_Mock::wpFunction( 'get_option', array('args' => 'csls_key','return' => 'clef') );
        \WP_Mock::wpFunction( 'get_option', array('args' => 'csls_routes','return' => '/') );
        \WP_Mock::wpFunction( 'get_option', array('args' => 'csls_expose_search','return' => true) );

        CSLS_Link_Searcher::load();
    }

    public function tearDown() {
        \WP_Mock::tearDown();
    }

    public function testLinkSearchFailsWithoutKey()
    {
        \WP_Mock::wpFunction( 'wp_die', array('args' => -1, 'times' => 1));
        $result = CSLS_Link_Searcher::cross_site_link_search();
    }

    public function testLinkSearchReturnsJsonResults()
    {
        \WP_Mock::wpFunction( 'wp_die', array('times' => 1));
        \WP_Mock::wpPassthruFunction( 'wp_unslash' );
        $_POST['key'] = 'clef';
        $_POST['search'] = 'Crunch';

        $result = CSLS_Link_Searcher::cross_site_link_search();

        $this->expectOutputString("[{\"testOutput\":\"success\"}]\n");
    }

    public function testAjaxGetLinkSearchResultsMergesQueries()
    {
        // Arrange:
        $_SERVER['SERVER_NAME'] = 'my.test.domain';
        $_POST['search'] = 'Crunch';
        \WP_Mock::wpPassthruFunction( 'wp_unslash' );
        \WP_Mock::wpPassthruFunction( 'check_ajax_referer', array('times' => 1) );
        \WP_Mock::wpFunction( 'wp_die', array('times' => 1));

        \WP_Mock::wpFunction(
            'wp_remote_post',
            array(
                'times' => 1,
                'return' => array('body' => "[{\"testOutput\":\"otherSuccess\"}]")
            )
        );

        // Act:
        $result = CSLS_Link_Searcher::ajax_get_link_search_results();

        // Assert (should contain responses from the original query and the wp_remote call):
        $this->expectOutputString("[{\"testOutput\":\"otherSuccess\"},{\"testOutput\":\"success\"}]\n");
    }
}