<?php
/**
 * WC Vendors sample shops: the same stores as the Dokan demo (content/dokan.php), so every marketplace reads the
 * same. Run by the sample people with the vendor role, in roster order. The shop URL comes from the name.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'stores' => ( require __DIR__ . '/dokan.php' )['stores'],
);
