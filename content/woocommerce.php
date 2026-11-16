<?php
/**
 * WooCommerce sample content: a small community store. Photos are CC0, in media/products (media/CREDITS.json).
 *
 * Products: name, photo slug (media/products/<slug>.jpg), category key, regular price, sale price ('' for none),
 * stock (null: not tracked), short description, description, featured, virtual.
 * Reviews: product index, roster index of the reviewer, rating 1-5, text.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'categories' => array(
		'apparel'     => 'Apparel',
		'accessories' => 'Accessories',
		'home'        => 'Home',
		'learning'    => 'Books & Learning',
	),
	'products'   => array(
		array( 'Everyday Grey Hoodie', 'hoodie', 'apparel', '48', '39', 24, 'Heavyweight cotton hoodie with a lined hood and a front pocket.', 'Our best seller for cold mornings and long meetups. Brushed fleece inside, a hood that keeps its shape, and ribbed cuffs that do not stretch out. Machine washable.', true, false ),
		array( 'Striped Cotton Tee', 'striped-tee', 'apparel', '24', '', 40, 'A soft, breathable tee in navy and white stripes.', 'Organic cotton jersey with a relaxed fit. Pairs with everything from jeans to shorts, and gets softer with every wash.', false, false ),
		array( 'Wool Felt Hat', 'felt-hat', 'apparel', '42', '', 12, 'A wide-brim hat in warm brown wool felt.', 'Pure wool felt with a grosgrain band. Packs into a bag without losing its shape and keeps the sun and light rain off.', false, false ),
		array( 'Sunny Days Sunglasses', 'sunglasses', 'accessories', '35', '', 30, 'Lightweight frames with UV400 lenses.', 'Bright frames that make every photo better, with lenses that block 100% of UVA and UVB light. Comes with a soft pouch.', true, false ),
		array( 'Camera Day Backpack', 'camera-backpack', 'accessories', '129', '109', 8, 'A padded backpack with room for a camera, two lenses and a laptop.', 'Built for photo walks and travel days: padded dividers you can move, a side opening for quick access, and a sleeve that fits a 15 inch laptop.', false, false ),
		array( 'Studio Headphones', 'headphones', 'accessories', '89', '', 15, 'Closed-back headphones for focus, calls and music.', 'Soft ear cushions for long sessions, a detachable cable and a fold-flat design. Great for editing podcasts or tuning out the coffee shop.', false, false ),
		array( 'Enamel Camp Mug', 'camp-mug', 'home', '16', '', 60, 'A sturdy enamel mug for coffee by the fire or at your desk.', 'Steel with a glossy enamel coat, light enough to pack and tough enough to survive the trail. Holds 350 ml.', false, false ),
		array( 'Soy Jar Candle', 'jar-candle', 'home', '22', '', 35, 'Hand-poured soy wax with cedar and vanilla.', 'A clean-burning soy candle in a reusable glass jar, about 40 hours of burn time. Poured in small batches.', false, false ),
		array( 'Desk Plant in Ceramic Pot', 'desk-plant', 'home', '28', '', 18, 'An easy-care spider plant in a white ceramic pot.', 'Spider plants forgive missed waterings and brighten any desk. The pot has a drainage hole and a cork base to protect surfaces.', false, false ),
		array( 'Wooden Breakfast Tray', 'serving-tray', 'home', '38', '32', 14, 'A beech serving tray with carved handles.', 'Breakfast in bed, a coffee table organiser or a serving board for friends. Oiled beech that is easy to wipe clean.', false, false ),
		array( 'The Community Field Guide', 'field-guide', 'learning', '19', '', 50, 'A paperback guide to starting and growing a local community.', 'Practical chapters on first meetups, welcoming newcomers, running events and keeping members coming back, with stories from organisers around the world.', true, false ),
		array( 'Dotted Hardcover Notebook', 'notebook', 'learning', '14', '', 70, 'A5 notebook with dotted pages and a lay-flat binding.', '192 pages of thick, ink-friendly paper, a ribbon marker and an elastic closure. For sketches, plans and meeting notes.', false, false ),
		array( 'Remote Work Masterclass', 'online-course', 'learning', '59', '45', null, 'A self-paced online class on working well from anywhere.', 'Six short video lessons and worksheets on focus, async communication and healthy routines. Instant access after purchase, watch at your own pace.', false, true ),
	),
	'home'       => array(
		'title'   => 'Good things for everyday life, made with care',
		'text'    => 'Hoodies, bags, homewares and books from a small store that knows its customers, with honest reviews on every product.',
		'image'   => 'photos/matcha-latte.jpg',
		'shop'    => 'Shop now',
		'account' => 'Your account',
		'closing' => 'Find something you will use every day.',
		'cats'    => array( 'Shop by category', 'Pick a category to see everything in it, with prices, stock and reviews.' ),
		'top'     => array( 'Top rated', 'The products our customers rate highest.' ),
		'all'     => 'See all products',
	),
	'reviews'    => array(
		array( 0, 1, 5, 'Warm without being bulky. I wear it to every meetup now.' ),
		array( 0, 4, 4, 'Great hoodie, runs slightly large so I sized down.' ),
		array( 1, 2, 5, 'Soft from day one and the stripes have not faded.' ),
		array( 3, 6, 5, 'Fun colour, and the lenses are properly dark.' ),
		array( 4, 3, 5, 'Fits my camera, two lenses and my laptop with room to spare.' ),
		array( 5, 7, 4, 'Comfortable for long editing sessions. Cable could be longer.' ),
		array( 6, 9, 5, 'Survived a week of camping and still looks new.' ),
		array( 7, 5, 4, 'Lovely scent, not overpowering.' ),
		array( 10, 8, 5, 'Every new organiser in our group gets a copy.' ),
		array( 12, 10, 5, 'Short lessons I could fit around work. The async chapter changed how our team meets.' ),
	),
);
