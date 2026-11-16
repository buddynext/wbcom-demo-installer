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
	'home'   => $wbcom_demo_dokan['home'],
	'stores' => $wbcom_demo_dokan['stores'],
);
