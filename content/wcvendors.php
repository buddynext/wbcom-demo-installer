<?php
/**
 * WC Vendors sample shops: the same stores as the Dokan demo (content/dokan.php), so every marketplace reads the
 * same; the home page copy too. Run by the sample people with the vendor role, in roster order. The shop URL
 * comes from the name.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

$wbcom_demo_dokan = require __DIR__ . '/dokan.php';

return array(
	// Same stores and buttons as the Dokan demo; its own hero, so each marketplace demo looks like itself.
	'home'   => array(
		'title' => 'Buy straight from the people who make it',
		'text'  => 'Each vendor runs their own shop here: handmade goods, everyday gear and books, with one cart for every shop.',
		'image' => 'photos/wireframe-sketches.jpg',
	) + $wbcom_demo_dokan['home'],
	'stores' => $wbcom_demo_dokan['stores'],
);
