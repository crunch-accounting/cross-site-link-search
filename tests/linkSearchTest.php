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
    }

    public function tearDown() {
        \WP_Mock::tearDown();
    }

    private static function setUpOptions ($csls_key = 'clef', $csls_routes = '/', $csls_expose_search = true) {
        \WP_Mock::wpFunction( 'get_option', array('args' => 'csls_key','return' => $csls_key) );
        \WP_Mock::wpFunction( 'get_option', array('args' => 'csls_routes','return' => $csls_routes) );
        \WP_Mock::wpFunction( 'get_option', array('args' => 'csls_expose_search','return' => $csls_expose_search) );

        CSLS_Link_Searcher::load();
    }

    public function testLinkSearchFailsWithoutKey() {
        \WP_Mock::wpFunction( 'wp_die', array('args' => -1, 'times' => 1));
        $result = CSLS_Link_Searcher::cross_site_link_search();
    }

    public function testLinkSearchReturnsJsonResults() {
        self::setUpOptions();
        \WP_Mock::wpFunction( 'wp_die', array('times' => 1));
        \WP_Mock::wpPassthruFunction( 'wp_unslash' );
        $_POST['key'] = 'clef';
        $_POST['search'] = 'Crunch';

        $result = CSLS_Link_Searcher::cross_site_link_search();

        $this->expectOutputString("[{\"testOutput\":\"success\"}]\n");
    }

    private static function setUpRequiredWpFunctions ($options = array('wpRemoteShouldError' => false)) {
        \WP_Mock::wpPassthruFunction( 'wp_unslash' );
        \WP_Mock::wpPassthruFunction( 'check_ajax_referer', array('times' => 1) );
        \WP_Mock::wpFunction( 'wp_die', array('times' => 1));

        \WP_Mock::wpFunction( 'is_wp_error', array('times' => 1, 'return' => $options['wpRemoteShouldError']));
    }

    public function testAjaxGetLinkSearchResultsMergesQueries() {
        // Arrange:
        self::setUpOptions();
        self::setUpRequiredWpFunctions();

        $_SERVER['SERVER_NAME'] = 'my.test.domain';
        $_POST['search'] = 'Crunch';

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

    public function testRemoteSiteError() {
        // Arrange:
        self::setUpOptions();
        self::setUpRequiredWpFunctions(array('wpRemoteShouldError' => true));

        $_SERVER['SERVER_NAME'] = 'my.test.domain';
        $_POST['search'] = 'Crunch';

        \WP_Mock::wpFunction(
            'wp_remote_post',
            array('times' => 1)
        );

        // Act:
        $result = CSLS_Link_Searcher::ajax_get_link_search_results();

        // Assert (should contain responses from the original query but not the wp_remote call which errored):
        $this->expectOutputString("[{\"testOutput\":\"success\"}]\n");
    }

    public function testAbsolutePaths() {
        // Arrange:
        self::setUpOptions('clef', 'https://absolute.path/sub/path', true);
        self::setUpRequiredWpFunctions();

        $_POST['search'] = 'Crunch';

        \WP_Mock::wpFunction(
            'wp_remote_post',
            array(
                'times' => 1,
                'args' => array(
                    'https://absolute.path/sub/path/wp-admin/admin-ajax.php',
                    \WP_Mock\Functions::type( 'array' )
                ),
                'return' => array('body' => "[{\"testOutput\":\"otherSuccess\"}]")
            )
        );

        // Act:
        $result = CSLS_Link_Searcher::ajax_get_link_search_results();

        // Assert (should contain responses from the original query and the wp_remote call):
        $this->expectOutputString("[{\"testOutput\":\"otherSuccess\"},{\"testOutput\":\"success\"}]\n");
    }

    public function testRootPath() {
        // Arrange:
        self::setUpOptions('clef', '/', true);
        self::setUpRequiredWpFunctions();

        $_POST['search'] = 'Crunch';
        $_SERVER['SERVER_NAME'] = 'my.test.domain';

        \WP_Mock::wpFunction(
            'wp_remote_post',
            array(
                'times' => 1,
                'args' => array(
                    'https://my.test.domain/wp-admin/admin-ajax.php',
                    \WP_Mock\Functions::type( 'array' )
                ),
                'return' => array('body' => "[{\"testOutput\":\"otherSuccess\"}]")
            )
        );

        // Act:
        $result = CSLS_Link_Searcher::ajax_get_link_search_results();

        // Assert (should contain responses from the original query and the wp_remote call):
        $this->expectOutputString("[{\"testOutput\":\"otherSuccess\"},{\"testOutput\":\"success\"}]\n");
    }

    public function testRelativePath() {
        // Arrange:
        self::setUpOptions('clef', '/blog', true);
        self::setUpRequiredWpFunctions();

        $_POST['search'] = 'Crunch';
        $_SERVER['SERVER_NAME'] = 'my.test.domain';

        \WP_Mock::wpFunction(
            'wp_remote_post',
            array(
                'times' => 1,
                'args' => array(
                    'https://my.test.domain/blog/wp-admin/admin-ajax.php',
                    \WP_Mock\Functions::type( 'array' )
                ),
                'return' => array('body' => "[{\"testOutput\":\"otherSuccess\"}]")
            )
        );

        // Act:
        $result = CSLS_Link_Searcher::ajax_get_link_search_results();

        // Assert (should contain responses from the original query and the wp_remote call):
        $this->expectOutputString("[{\"testOutput\":\"otherSuccess\"},{\"testOutput\":\"success\"}]\n");
    }
}