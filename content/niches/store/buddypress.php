<?php
/**
 * Store niche for BuddyPress / BuddyBoss Platform: groups and feed from the store set (content/niches/store/
 * buddynext.php), mapped the way content/buddypress.php maps the base set. Home and guide stay the base ones.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

$wbcom_demo_bn = require __DIR__ . '/buddynext.php';

return array(
	'groups'   => $wbcom_demo_bn['spaces'],
	'posts'    => $wbcom_demo_bn['posts'],
	'photos'   => $wbcom_demo_bn['photos'],
	'links'    => $wbcom_demo_bn['links'],
	'comments' => $wbcom_demo_bn['comments'],
);
