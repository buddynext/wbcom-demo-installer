<?php
/**
 * WCFM Marketplace sample stores: the same stores as the Dokan demo (content/dokan.php), so every marketplace
 * reads the same. Run by the sample people with the vendor role, in roster order.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'stores' => ( require __DIR__ . '/dokan.php' )['stores'],
);
