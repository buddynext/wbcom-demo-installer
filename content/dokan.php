<?php
/**
 * Dokan sample stores, run by the sample people with the vendor role (in roster order). The store photo is the
 * person's own photo; banners come from media/covers (media/CREDITS.json). The WooCommerce sample products are
 * shared out among the stores in turn.
 *
 * Stores: name, URL slug, about, street, city, state, postcode, country, phone, banner. The street addresses are
 * public city-centre landmarks (Pioneer Courthouse Square, Pike Place Market, the Texas Capitol, Union Station) so
 * store maps geocode them and show pins; the phone numbers stay in the fictional 555-01xx range so no real line rings.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'home'   => array(
		'title'   => 'Shop from independent sellers, all in one place',
		'text'    => 'Clothing, accessories, homewares and books from small stores run by the people who make and pick them. One cart, every seller.',
		'image'   => 'photos/creative-studio-portrait.jpg',
		'stores'  => 'Browse stores',
		'shop'    => 'Shop products',
		'sell'    => 'Become a seller',
		'closing' => 'Make or sell something people love? Open your own store here.',
		'browse'  => 'Find a store you will keep coming back to.',
		'meet'    => array( 'Meet the sellers', 'Every store has its own page with its products, story and reviews.' ),
		'all'     => 'See all stores',
	),
	'stores' => array(
		array( 'Northwind Outfitters', 'northwind-outfitters', 'Hoodies, tees and hats made in small batches, built for meetups and long weekends.', '701 SW 6th Avenue', 'Portland', 'OR', '97204', 'US', '+1 555 0102 410', 'covers/cover-02.jpg' ),
		array( 'Harbor & Pine', 'harbor-and-pine', 'Everyday accessories for people on the move: bags, sunglasses and headphones.', '85 Pike Street', 'Seattle', 'WA', '98101', 'US', '+1 555 0103 417', 'covers/cover-04.jpg' ),
		array( 'Studio Lumen', 'studio-lumen', 'Homewares with warm light and natural materials: candles, mugs, trays and plants.', '1100 Congress Avenue', 'Austin', 'TX', '78701', 'US', '+1 555 0104 424', 'covers/cover-06.jpg' ),
		array( 'Maple Thread Books', 'maple-thread-books', 'Notebooks, field guides and online classes for curious people and the groups they run.', '65 Front Street West', 'Toronto', 'ON', 'M5J 1E6', 'CA', '+1 555 0105 431', 'covers/cover-08.jpg' ),
	),
);
