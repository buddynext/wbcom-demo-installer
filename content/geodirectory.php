<?php
/**
 * GeoDirectory sample content: places in Philadelphia (GeoDirectory's default location, so its map opens on them),
 * with real street addresses and matching map coordinates. Owners and reviewers are roster indexes
 * (content/people.php); photos are files under media/photos.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

// Opening hours by category, in GeoDirectory's business hours format. Hotels have none (open around the clock).
$wbcom_demo_hours = array(
	'cafe'       => '["Mo 07:00-17:00","Tu 07:00-17:00","We 07:00-17:00","Th 07:00-17:00","Fr 07:00-18:00","Sa 08:00-18:00","Su 08:00-15:00"]',
	'restaurant' => '["Tu 17:00-22:00","We 17:00-22:00","Th 17:00-22:00","Fr 17:00-23:00","Sa 12:00-23:00","Su 12:00-21:00"]',
	'bar'        => '["We 17:00-23:30","Th 17:00-23:30","Fr 16:00-23:59","Sa 16:00-23:59","Su 16:00-22:00"]',
	'books'      => '["Mo 10:00-19:00","Tu 10:00-19:00","We 10:00-19:00","Th 10:00-20:00","Fr 10:00-20:00","Sa 10:00-20:00","Su 11:00-18:00"]',
	'park'       => '["Mo 06:00-22:00","Tu 06:00-22:00","We 06:00-22:00","Th 06:00-22:00","Fr 06:00-22:00","Sa 06:00-22:00","Su 06:00-22:00"]',
	'gallery'    => '["We 11:00-18:00","Th 11:00-18:00","Fr 11:00-20:00","Sa 10:00-18:00","Su 10:00-17:00"]',
	'cowork'     => '["Mo 08:00-20:00","Tu 08:00-20:00","We 08:00-20:00","Th 08:00-20:00","Fr 08:00-18:00"]',
	'hotel'      => '',
);

// Review texts by category: [ stars, text ].
$wbcom_demo_said = array(
	'cafe'       => array(
		array( 5, 'Best flat white in the neighbourhood and the staff remember your order by the second visit.' ),
		array( 4, 'Lovely spot to work for a couple of hours. Gets loud around noon, so come early.' ),
		array( 5, 'The pastries sell out fast for a reason. Get there before ten on weekends.' ),
		array( 4, 'Friendly, quick and the oat milk is free. Only wish they had more seats.' ),
	),
	'restaurant' => array(
		array( 5, 'Booked it for a birthday and every dish was a hit. The dessert alone is worth the trip.' ),
		array( 4, 'Great food and fair prices. Service slowed down when it got busy, but nothing major.' ),
		array( 5, 'Our new Friday spot. Ask for a table by the window.' ),
		array( 4, 'Generous portions and a short, smart menu. Will be back to try the specials.' ),
	),
	'bar'        => array(
		array( 5, 'Great cocktails, a band that actually plays well and no cover before nine.' ),
		array( 4, 'Good drinks and a fun crowd. It fills up after ten, so grab a booth early.' ),
		array( 5, 'The bartenders know their stuff. Tell them what you like and let them surprise you.' ),
		array( 3, 'Nice atmosphere, but the music was a little too loud to talk on a Saturday.' ),
	),
	'books'      => array(
		array( 5, 'The kind of shop where you go in for one book and leave with four. Great staff picks.' ),
		array( 5, 'Cosy, well curated and they will order anything they do not have in stock.' ),
		array( 4, 'A lovely place to browse on a rainy afternoon. The kids corner is a nice touch.' ),
		array( 4, 'Small but packed with good finds. The monthly author nights are worth catching.' ),
	),
	'park'       => array(
		array( 5, 'Our favourite place for a slow Sunday. Clean, green and plenty of shade.' ),
		array( 5, 'Perfect for an early run before the city wakes up.' ),
		array( 4, 'Beautiful in every season. Weekend afternoons get crowded near the entrance.' ),
		array( 4, 'Great for a picnic. Bring a blanket, the benches go fast.' ),
	),
	'gallery'    => array(
		array( 5, 'Small rooms, big ideas. Every visit there is something new on the walls.' ),
		array( 4, 'Free entry and the staff are happy to talk about the work. A real find.' ),
		array( 5, 'The First Friday openings are the best night out in the neighbourhood.' ),
		array( 4, 'Well worth an hour. The print shop at the back has affordable pieces.' ),
	),
	'cowork'     => array(
		array( 5, 'Fast wifi, good coffee and quiet phone booths. Everything a freelancer needs.' ),
		array( 4, 'Friendly community and flexible day passes. Parking nearby is tricky.' ),
		array( 5, 'Moved my small team here last year and have not looked back.' ),
		array( 4, 'Bright space and great events. The meeting room books up quickly.' ),
	),
	'hotel'      => array(
		array( 5, 'Spotless rooms, comfortable beds and the front desk sorted out a late check-out for us.' ),
		array( 4, 'Great location for walking everywhere. Breakfast is good but a little pricey.' ),
		array( 5, 'Quiet at night even though it is right in the middle of everything. Would stay again.' ),
		array( 4, 'Friendly staff and a lovely view from the top floor. Rooms are on the small side.' ),
	),
);

// Category key => [ name, Font Awesome icon, colour, schema type ]: the icon and colour GeoDirectory shows on
// category lists and map pins.
$wbcom_demo_categories = array(
	'cafe'       => array( 'Cafés', 'fas fa-coffee', '#8d5a3b', 'CafeOrCoffeeShop' ),
	'restaurant' => array( 'Restaurants', 'fas fa-utensils', '#bc2b15', 'Restaurant' ),
	'bar'        => array( 'Bars & Nightlife', 'fas fa-glass-martini', '#803fc7', 'BarOrPub' ),
	'books'      => array( 'Bookshops', 'fas fa-book', '#2a6f97', 'BookStore' ),
	'park'       => array( 'Parks', 'fas fa-tree', '#4f9a35', 'Park' ),
	'gallery'    => array( 'Museums & Galleries', 'fas fa-landmark', '#d17b0f', 'ArtGallery' ),
	'cowork'     => array( 'Coworking', 'fas fa-laptop', '#1d8fb0', 'LocalBusiness' ),
	'hotel'      => array( 'Hotels', 'fas fa-bed', '#008972', 'Hotel' ),
);

// [ owner (roster index), name, category, street, zip, latitude, longitude, phone, website, photo, featured, description ].
$wbcom_demo_places = array(
	array( 3, 'Bellwether Coffee Co.', 'cafe', '1621 Sansom St', '19103', 39.9505, -75.1683, '(215) 555-0141', 'https://bellwether-coffee.example', 'coffee-cheers.jpg', 1, "A bright corner roastery a block from Rittenhouse Square, pulling single-origin espresso from beans roasted every Tuesday.\n\nThe long communal table in the back is a favourite with remote workers, and the pastry case is filled each morning by a baker in Fishtown." ),
	array( 7, 'Little Fox Bakehouse', 'cafe', '1314 Frankford Ave', '19125', 39.9700, -75.1345, '(215) 555-0157', 'https://littlefox-bakehouse.example', 'chocolate-truffles.jpg', 0, "Sourdough loaves every morning, chocolate truffles every afternoon and a queue out the door on Saturdays.\n\nEverything is baked on site with flour from a mill in Lancaster County. Pre-order whole cakes two days ahead." ),
	array( 9, 'Kettle & Leaf Tea House', 'cafe', '1036 Race St', '19107', 39.9553, -75.1573, '(215) 555-0163', 'https://kettle-and-leaf.example', 'matcha-latte.jpg', 0, "A calm Chinatown tea room serving hand-whisked matcha, oolong flights and steamed buns.\n\nTea tastings run on the first Sunday of the month, and every tea on the menu is sold loose to take home." ),
	array( 13, 'Passyunk Table', 'restaurant', '1800 E Passyunk Ave', '19148', 39.9282, -75.1640, '(215) 555-0172', 'https://passyunk-table.example', 'gourmet-dish-plating.jpg', 1, "A neighbourhood dining room on East Passyunk's restaurant row with a seasonal tasting menu and a short, natural wine list.\n\nThe menu changes with what the farmers at the Headhouse market bring in. Reservations open thirty days ahead." ),
	array( 18, 'Ninth Street Pizza Co.', 'restaurant', '900 S 9th St', '19147', 39.9380, -75.1578, '(215) 555-0188', 'https://ninthstreet-pizza.example', 'artisan-pizza.jpg', 0, "Wood-fired pies in the heart of the Italian Market, with tomatoes from the stall next door and mozzarella pulled every morning.\n\nWalk-ins only; slices are sold from the window until late on Fridays and Saturdays." ),
	array( 3, 'Old City Small Plates', 'restaurant', '133 Market St', '19106', 39.9497, -75.1428, '(215) 555-0119', 'https://oldcity-smallplates.example', 'tasting-plates.jpg', 0, "Sharing plates in a converted 1880s warehouse two blocks from the Delaware River.\n\nThe kitchen leans Mediterranean, with a big vegetarian section and a chef's counter for six that is worth booking." ),
	array( 7, 'The Ember Room', 'bar', '1100 N Front St', '19123', 39.9672, -75.1365, '(215) 555-0124', 'https://the-ember-room.example', 'neon-light.jpg', 1, "A low-lit cocktail bar in Northern Liberties with live jazz from Wednesday to Saturday.\n\nThe menu is built around Pennsylvania spirits, and the zero-proof list is just as long as the cocktail list." ),
	array( 9, 'Vine & Barrel Wine Bar', 'bar', '1904 South St', '19146', 39.9443, -75.1738, '(215) 555-0136', 'https://vine-and-barrel.example', 'wine-vineyard.jpg', 0, "Forty wines by the glass, cheese and charcuterie boards and a small back garden strung with lights.\n\nThe staff run a relaxed tasting every Thursday where you can try five pours for the price of two." ),
	array( 13, 'Sound Yard', 'bar', '1100 Frankford Ave', '19125', 39.9665, -75.1355, '(215) 555-0145', 'https://soundyard-philly.example', 'festival-friends.jpg', 0, "A 300-person music hall in Fishtown with local bands on weeknights and touring acts at weekends.\n\nThe outdoor yard opens in summer with food trucks and free early shows." ),
	array( 18, 'Spruce Street Books', 'books', '1214 Pine St', '19107', 39.9455, -75.1617, '(215) 555-0151', 'https://sprucestreet-books.example', 'open-books.jpg', 1, "An independent bookshop in Washington Square West with new fiction up front and a used and rare room upstairs.\n\nAuthor talks run every second Thursday, and the staff picks shelf has never steered us wrong." ),
	array( 3, 'Paper Lantern Comics & Zines', 'books', '4352 Main St', '19127', 40.0253, -75.2232, '(215) 555-0168', 'https://paperlantern-comics.example', 'wireframe-sketches.jpg', 0, "Comics, graphic novels and zines from Philadelphia artists on Manayunk's Main Street.\n\nA drawing night for all ages takes over the shop on the last Friday of each month." ),
	array( 7, 'Fairmount Reading Room', 'books', '2201 Fairmount Ave', '19130', 39.9672, -75.1745, '(215) 555-0177', 'https://fairmount-readingroom.example', 'cafe-friends.jpg', 0, "A bookshop and café in one, three blocks from the Art Museum steps, with armchairs by the window and good coffee.\n\nBook clubs meet here most evenings; ask at the counter to join one." ),
	array( 9, 'Rittenhouse Square', 'park', 'W Rittenhouse Square', '19103', 39.9496, -75.1718, '', '', 'sunlight-through-trees.jpg', 1, "One of William Penn's five original squares, shaded by old trees and ringed by cafés.\n\nA farmers market sets up on Saturday mornings, and summer evenings bring free concerts on the lawn." ),
	array( 13, 'Schuylkill Banks', 'park', 'Locust St & S 25th St', '19103', 39.9500, -75.1810, '', '', 'sunlit-forest-trail.jpg', 0, "The riverside path along the Schuylkill, popular with runners, cyclists and dog walkers from dawn to dusk.\n\nThe boardwalk section stretches out over the water, and kayak tours leave from the Walnut Street dock in summer." ),
	array( 18, 'Wissahickon Valley Park', 'park', 'Valley Green Rd', '19128', 40.0563, -75.2196, '', '', 'misty-forest-path.jpg', 0, "Over fifty miles of wooded trails along Wissahickon Creek, a short drive from Center City.\n\nForbidden Drive is flat and wide enough for strollers; the upper trails are steeper and quieter." ),
	array( 3, 'Old City Print Gallery', 'gallery', '152 N 3rd St', '19106', 39.9531, -75.1453, '(215) 555-0183', 'https://oldcity-printgallery.example', 'art-gallery.jpg', 0, "A small gallery of prints, posters and photography by artists from the region, with a working press in the back room.\n\nOpenings are on First Friday of every month, when Old City's galleries stay open late." ),
	array( 7, 'Lens & Light Photo Gallery', 'gallery', '1001 N 2nd St', '19123', 39.9655, -75.1405, '(215) 555-0192', 'https://lensandlight.example', 'vintage-camera.jpg', 1, "A photography gallery and community darkroom in Northern Liberties.\n\nExhibitions change every six weeks, and beginners can book a film developing workshop on Sunday mornings." ),
	array( 9, 'Fairmount Park Japanese Garden', 'gallery', 'Lansdowne Dr & Horticultural Dr', '19131', 39.9817, -75.2125, '(215) 555-0115', 'https://fairmount-japanesegarden.example', 'japanese-garden.jpg', 0, "A traditional Japanese house and garden in West Fairmount Park, with a koi pond and tea ceremonies in spring.\n\nGuided tours run every hour; cherry blossom season is the busiest and most beautiful time to visit." ),
	array( 13, 'The Loom Coworking', 'cowork', '1525 N American St', '19122', 39.9746, -75.1404, '(215) 555-0128', 'https://theloom-cowork.example', 'developer-workspace.jpg', 0, "Hot desks and private studios in a restored textile mill on the edge of Kensington.\n\nMembers get 24-hour access, a print room and a monthly lunch with everyone in the building." ),
	array( 18, 'Walnut Street Commons', 'cowork', '3401 Walnut St', '19104', 39.9530, -75.1920, '(215) 555-0139', 'https://walnut-commons.example', 'tech-workspace.jpg', 1, "A coworking floor in University City for start-ups, researchers and freelancers, five minutes from 30th Street Station.\n\nDay passes include coffee and a meeting room hour, and a demo night runs on the last Thursday of the month." ),
	array( 3, 'Benchmark Studios', 'cowork', '1310 N 5th St', '19122', 39.9718, -75.1465, '(215) 555-0146', 'https://benchmark-studios.example', 'developer-desk.jpg', 0, "Shared workshop and desk space for makers, designers and small product teams.\n\nThe ground floor has a laser cutter and a 3D print bench that members can book by the hour." ),
	array( 7, 'The Sansom House Hotel', 'hotel', '1400 Walnut St', '19102', 39.9490, -75.1650, '(215) 555-0154', 'https://sansomhouse-hotel.example', 'city-skyline-dusk.jpg', 1, "A 90-room boutique hotel on Walnut Street with a rooftop bar looking over City Hall.\n\nRooms have rain showers and blackout blinds, and the Avenue of the Arts theatres are a two-minute walk away." ),
	array( 9, "Riverfront Inn at Penn's Landing", 'hotel', '401 S Columbus Blvd', '19106', 39.9430, -75.1418, '(215) 555-0165', 'https://riverfront-inn.example', 'modern-glass-building.jpg', 0, "A modern hotel on the Delaware River waterfront with views of the Ben Franklin Bridge.\n\nThe riverside park and the summer beer garden are right outside, and Old City is a ten-minute walk." ),
	array( 13, 'The Girard Guesthouse', 'hotel', '401 W Girard Ave', '19123', 39.9705, -75.1450, '(215) 555-0179', 'https://girard-guesthouse.example', 'angular-glass-facade.jpg', 0, "Twelve rooms above a corner café in Northern Liberties, each decorated by a local artist.\n\nGuests get free bike hire and a walking map of the neighbourhood's murals." ),
);

// Reviews from sample people who are not place owners: [ place index, reviewer (roster index), stars, text ].
// Every place gets two reviews and every third place a third.
$wbcom_demo_reviewers = array( 1, 5, 6, 10, 11, 12, 15, 16, 19, 4 );
$wbcom_demo_reviews   = array();
foreach ( $wbcom_demo_places as $wbcom_demo_i => $wbcom_demo_place ) {
	$wbcom_demo_texts = $wbcom_demo_said[ $wbcom_demo_place[2] ];
	for ( $wbcom_demo_r = 0; $wbcom_demo_r < 2 + ( 0 === $wbcom_demo_i % 3 ? 1 : 0 ); $wbcom_demo_r++ ) {
		$wbcom_demo_said_one  = $wbcom_demo_texts[ ( $wbcom_demo_i + $wbcom_demo_r ) % count( $wbcom_demo_texts ) ];
		$wbcom_demo_reviews[] = array(
			$wbcom_demo_i,
			$wbcom_demo_reviewers[ ( $wbcom_demo_i * 3 + $wbcom_demo_r ) % count( $wbcom_demo_reviewers ) ],
			$wbcom_demo_said_one[0],
			$wbcom_demo_said_one[1],
		);
	}
}

return array(
	'categories' => $wbcom_demo_categories,
	'hours'      => $wbcom_demo_hours,
	'places'     => $wbcom_demo_places,
	'reviews'    => $wbcom_demo_reviews,
	// Location of every place: GeoDirectory's own default location.
	'city'       => array( 'Philadelphia', 'Pennsylvania', 'United States' ),
	// "Try the demo" page: text only; the provider adds people and links.
	'home'       => array(
		'title'    => 'Find the best of Philadelphia, picked by locals',
		'text'     => 'Cafés, restaurants, parks and places to stay, all in one directory with a map, search and the people who know where to go.',
		'image'    => 'photos/cafe-friends.jpg',
		'browse'   => 'Browse places',
		'add'      => 'Add your listing',
		'closing'  => 'Know a place everyone should find? Add it to the directory.',
		'cats'     => array( 'Browse by category', 'Pick a category to see every place in it, on a list and on the map.' ),
		'featured' => array( 'Places people love', 'A few of the listings, each with photos, opening hours, reviews and its spot on the map.' ),
		'all'      => 'See all places',
		'map'      => array( 'See it on the map', 'Every place in the directory on one map. Filter by category or search by name.' ),
	),
	'guide'      => array(
		'name'         => 'GeoDirectory',
		'tab'          => 'Places',
		'member_label' => 'As a member',
		'owner_points' => array( 'Approve and edit every place', 'Add fields, categories and maps', 'Moderate reviews and ratings' ),
		'roles'        => array(
			'member' => array( 'Place owner', array( 'Runs places with photos, hours and a map pin', 'Add a new place in one form', 'Reviews and star ratings from other members' ) ),
		),
		'member'       => array(
			'browse' => array( 'search', 'Search the places', 'Filter by category, see them on the map.', 'Open the places' ),
			'open'   => array( 'star', 'Review a place', 'Read the reviews, see opening hours and add your rating.', 'Open a place' ),
			'add'    => array( 'plus', 'Add your place', 'Address, map pin, photos and hours in one form.', 'Add a place' ),
		),
		'owner'        => array(
			'places'   => array( 'list', 'Manage places', 'Approve, edit or feature any place.', 'Open places' ),
			'reviews'  => array( 'star', 'Moderate reviews', 'Approve or remove reviews and ratings.', 'Open reviews' ),
			'settings' => array( 'settings', 'Directory settings', 'Maps, fields, categories and email.', 'Open settings' ),
		),
		'highlights'   => array(
			array( 'pin', 'Map search', 'Every place on a map, with search near any address.' ),
			array( 'list', 'Categories', 'Cafés, restaurants, parks and more, each with its own icon.' ),
			array( 'star', 'Reviews and ratings', 'Members rate and review every place.' ),
			array( 'calendar', 'Opening hours', 'Shows whether a place is open right now.' ),
		),
	),
);
