<?php
/**
 * Dokan sample stores, run by the sample people with the vendor role (in roster order). The store photo is the
 * person's own photo; banners come from media/covers (media/CREDITS.json). The WooCommerce sample products are
 * shared out among the stores in turn.
 *
 * Stores: name, URL slug, about, street, city, state, postcode, country, phone, banner.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'stores' => array(
		array( 'Northwind Outfitters', 'northwind-outfitters', 'Hoodies, tees and hats made in small batches, built for meetups and long weekends.', '120 Market Street', 'Portland', 'OR', '97205', 'US', '+1 555 0102 410', 'covers/cover-02.jpg' ),
		array( 'Harbor & Pine', 'harbor-and-pine', 'Everyday accessories for people on the move: bags, sunglasses and headphones.', '155 Pike Street', 'Seattle', 'WA', '98101', 'US', '+1 555 0103 417', 'covers/cover-04.jpg' ),
		array( 'Studio Lumen', 'studio-lumen', 'Homewares with warm light and natural materials: candles, mugs, trays and plants.', '190 Congress Avenue', 'Austin', 'TX', '78701', 'US', '+1 555 0104 424', 'covers/cover-06.jpg' ),
		array( 'Maple Thread Books', 'maple-thread-books', 'Notebooks, field guides and online classes for curious people and the groups they run.', '225 King Street West', 'Toronto', 'ON', 'M5V 2T6', 'CA', '+1 555 0105 431', 'covers/cover-08.jpg' ),
	),
);
