<?php
/**
 * WCFM Marketplace sample stores: the same stores as the Dokan demo (content/dokan.php), so every marketplace
 * reads the same; the home page copy too. Run by the sample people with the vendor role, in roster order.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

$wbcom_demo_dokan = require __DIR__ . '/dokan.php';

return array(
	// Same stores and buttons as the Dokan demo; its own hero, so each marketplace demo looks like itself.
	'home'   => array(
		'title' => 'Small stores, each with its own shopfront',
		'text'  => 'Every store here has its own logo, banner and story. Browse by maker, add from as many stores as you like, then check out once.',
		'image' => 'photos/coffee-cheers.jpg',
	) + $wbcom_demo_dokan['home'],
	'stores' => $wbcom_demo_dokan['stores'],
);
