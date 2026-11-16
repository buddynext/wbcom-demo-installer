<?php
/**
 * WB Listora sample content: listings and review texts. Owners are roster indices (content/people.php).
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'listings' => array(
		array( 7, 'Roastworks Coffee Bar', 'Cafe', 'Oranienstraße 21', 'Berlin', 'DE', 52.5009, 13.4220, '$$', 'coffee-cheers.jpg', 'A small roastery bar with single-origin pour-overs, a quiet back room for laptops and cinnamon buns on weekends.', array( 'WiFi', 'Takeout', 'Outdoor Seating' ) ),
		array( 3, 'Kissa Hanabi', 'Cafe', 'Shimogyo-ku 12', 'Kyoto', 'JP', 35.0036, 135.7681, '$', 'matcha-latte.jpg', 'A ten-seat tea room with hand-whisked matcha, wagashi from the shop next door and a record player that never stops.', array( 'Credit Cards', 'Takeout' ) ),
		array( 13, 'Mercado Criativo', 'Coworking', 'Rua de Cedofeita 80', 'Porto', 'PT', 41.1517, -8.6176, '$$', 'creative-studio-portrait.jpg', 'Desks, a print studio and a shared kitchen for freelancers. Day passes available, members get the good chairs.', array( 'WiFi', 'Wheelchair Accessible' ) ),
		array( 17, 'The Sound Room', 'Venue', 'Rainbow Street 5', 'Amman', 'JO', 31.9522, 35.9254, '$$', 'festival-friends.jpg', 'A 120-person room for talks and small gigs, with a proper sound system and the best falafel stand outside.', array( 'Live Music', 'Wheelchair Accessible' ) ),
		array( 9, 'Hanok Lens Studio', 'Studio', 'Bukchon-ro 37', 'Seoul', 'KR', 37.5826, 126.9837, '$$$', 'vintage-camera.jpg', 'A rentable photo studio in a renovated hanok, with daylight from three sides and a darkroom for film.', array( 'Reservations', 'Credit Cards' ) ),
		array( 7, 'Kiez Bakery', 'Bakery', 'Weserstraße 50', 'Berlin', 'DE', 52.4855, 13.4373, '$', 'chocolate-truffles.jpg', 'Sourdough every morning, chocolate truffles every afternoon and a queue that moves faster than it looks.', array( 'Takeout', 'Credit Cards' ) ),
	),
	'reviews'  => array(
		array( 5, 'My favourite spot in town', 'Friendly people, great coffee and quiet enough to get work done.' ),
		array( 4, 'Lovely, gets busy', 'Really good. Go early on weekends, it fills up fast.' ),
		array( 5, 'Worth the trip', 'We came across the city for this and would do it again.' ),
		array( 4, 'Great for small groups', 'Booked it for our team meetup, everything was easy.' ),
		array( 5, 'A real gem', 'The kind of place you want to keep secret and tell everyone about.' ),
	),
	'home'     => array(
		'title'    => 'Find the places locals love',
		'text'     => 'Cafés, studios, venues and bakeries in one directory, with real reviews, opening hours and a live map.',
		'image'    => 'photos/urban-street-night.jpg',
		'browse'   => 'Browse the directory',
		'add'      => 'Add your listing',
		'closing'  => 'Run a place people should know about? Add it to the directory.',
		'cats'     => array( 'Browse by category', 'Pick a category to see every listing in it, with filters for rating and what is open now.' ),
		'featured' => array( 'Places people love', 'The best-rated listings, each with photos, hours, reviews and its spot on the map.' ),
		'all'      => 'See all listings',
	),
	// "Try the demo" page: text only; the provider adds people and links (Provider::guide()).
	'guide'    => array(
		'name'         => 'Listora',
		'tab'          => 'Directory',
		'member_label' => 'As a member',
		'owner_points' => array( 'Approve and edit every listing', 'Sell featured and paid listings', 'Award badges such as Verified' ),
		'roles'        => array(
			'member' => array( 'Member', array( 'Favourites and reviews already saved', 'Add a listing with photos and hours', 'Manage everything from one dashboard' ) ),
		),
		'member'       => array(
			'browse' => array( 'search', 'Search the directory', 'Filter places by type, location and rating.', 'Open the directory' ),
			'open'   => array( 'star', 'Review a listing', 'Save it to your favourites and add your rating.', 'Open a listing' ),
			'submit' => array( 'plus', 'Add your listing', 'Photos, opening hours and a map pin in one form.', 'Add a listing' ),
			'manage' => array( 'list', 'Manage your listings', 'Edit your listings and see your favourites.', 'Open my dashboard' ),
		),
		'owner'        => array(
			'listings' => array( 'list', 'Review listings', 'Approve, edit or feature any listing.', 'Open listings' ),
			'plans'    => array( 'money', 'Sell listing plans', 'Charge for featured or paid listings.', 'Open plans' ),
			'badges'   => array( 'award', 'Award badges', 'Verified, Top rated or your own.', 'Open badges' ),
		),
		'highlights'   => array(
			array( 'pin', 'Map search', 'Find places near any address, with filters.' ),
			array( 'list', 'Listing types', 'Restaurants, hotels, jobs, real estate and more, each with its own fields.' ),
			array( 'star', 'Reviews and ratings', 'Members rate and review every listing.' ),
			array( 'heart', 'Favourites', 'Members save the places they like.' ),
			array( 'money', 'Paid listings', 'Sell plans for featured and paid listings.' ),
			array( 'award', 'Badges', 'Mark trusted listings as Verified or Top rated.' ),
		),
	),
);
