<?php
/**
 * PHPStan bootstrap: plugin constants.
 *
 * @package WbcomDemo
 */

define( 'WBCOM_DEMO_VERSION', '0.1.0' );
define( 'WBCOM_DEMO_FILE', __DIR__ . '/../wbcom-demo-installer.php' );
define( 'WBCOM_DEMO_DIR', __DIR__ . '/../' );
define( 'WBCOM_DEMO_URL', 'https://example.test/wp-content/plugins/wbcom-demo-installer/' );

// WordPress's cookie names, defined at runtime by wp_cookie_constants().
define( 'AUTH_COOKIE', 'wordpress_test' );
define( 'SECURE_AUTH_COOKIE', 'wordpress_sec_test' );
define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_test' );
