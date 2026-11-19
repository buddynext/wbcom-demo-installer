<?php
/**
 * Eventonomy sample events (cherry-picked from the Eventonomy demo set and fitted to the cast).
 * events: [ title, category, venue, address, city, country, organizer index, days from today, time, description, virtual, image ].
 * guide: the "Try the demo" page text (Core\Guide).
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'events' => array(
		// Days from the build date: spread over the next eight weeks so the calendar and "Coming up next" stay full for
		// two months after a build, with one recent past event for the "Past events" view.
		array( 'Trail Run and Coffee', 'Outdoors', 'River Loop Trailhead', 'Skatås Motionscentrum', 'Gothenburg', 'SE', 4, 5, '08:00-10:00', 'An easy 8km loop at conversation pace, followed by coffee and cinnamon buns. All levels.', false, 'photos/sunlit-forest-trail.jpg' ),
		array( 'Monthly Community Call', 'Community', '', '', '', '', 2, 8, '17:00-18:00', 'Our monthly call: wins from the month, one short talk and open questions. Everyone is welcome, cameras optional.', true, 'photos/coffee-cheers.jpg' ),
		array( 'Design Critique Night', 'Design', 'The Studio', '12 Rua das Flores', 'Lisbon', 'PT', 0, 11, '18:30-20:30', 'Bring a screen you are stuck on. Three rounds of friendly, specific feedback, then drinks.', false, 'photos/wireframe-sketches.jpg' ),
		array( 'Accessibility Clinic', 'Technology', '', '', '', '', 1, 13, '16:00-17:30', 'Share a page or app and we will review it live with a screen reader and a keyboard. Learn by watching real fixes.', true, 'photos/developer-desk.jpg' ),
		array( 'Indie Game Dev Meetup', 'Technology', 'Pixel Works', 'Calle Durango 210', 'Mexico City', 'MX', 17, 16, '18:30-20:30', 'Show your work, get feedback and meet other indie developers. Lightning demos and a friendly crowd.', false, 'photos/neon-grid.jpg' ),
		array( 'Founders Breakfast', 'Business', 'The Grand Hall', 'MG Road 44', 'Kochi', 'IN', 15, 19, '08:00-09:30', 'An intimate breakfast where three founders break down how they found their first hundred customers.', false, 'photos/cafe-friends.jpg' ),
		array( 'Street Photography Walk', 'Photography', 'Gwanghwamun Square', 'Sejong-daero 172', 'Seoul', 'KR', 9, 22, '15:00-18:00', 'A slow walk through the old city with one rule: one lens, no zoom. We share our best frame at the end.', false, 'photos/vintage-camera.jpg' ),
		array( 'Remote Hiring Workshop', 'Career', '', '', '', '', 12, 25, '14:00-15:30', 'How remote-first teams write job posts, run fair interviews and onboard people they have never met in person.', true, 'photos/developer-workspace.jpg' ),
		array( 'Portfolio Review Hour', 'Career', '', '', '', '', 0, 28, '17:00-18:00', 'Three members share their portfolio and get honest, kind notes on what to cut, what to lead with and what to add.', true, 'photos/creative-studio-portrait.jpg' ),
		array( 'Night Market Food Tour', 'Community', 'Shilin Night Market', 'Jihe Road 101', 'Taipei', 'TW', 5, 31, '19:00-21:30', 'Eight stalls, small plates and the stories behind them. Come hungry; we split everything.', false, 'photos/tasting-plates.jpg' ),
		array( 'Writing Sprint', 'Community', '', '', '', '', 8, 34, '09:00-10:30', 'Ninety quiet minutes of writing together on camera, with a short check-in at the start and the end.', true, 'photos/open-books.jpg' ),
		array( 'Typography Evening', 'Design', 'Letterpress Lab', 'Shimogyo-ku 3-1', 'Kyoto', 'JP', 3, 37, '19:00-21:00', 'A hands-on evening setting type by hand, with a short talk on how letters are drawn.', false, 'photos/open-books.jpg' ),
		array( 'Sunrise Hike', 'Outdoors', 'Highland Trailhead', 'Glen Nevis Visitor Centre', 'Fort William', 'GB', 4, 40, '05:30-09:00', 'A steady climb to catch the sunrise from the ridge, then breakfast at the bottom. Bring layers.', false, 'photos/highland-ridge-sunrise.jpg' ),
		array( 'API Design Q and A', 'Technology', '', '', '', '', 7, 44, '16:00-17:00', 'Bring your API questions: naming, versioning, errors and docs. Answered live by people who ship APIs every week.', true, 'photos/server-room.jpg' ),
		array( 'Gallery Late', 'Design', 'Northside Gallery', 'Strandgade 27', 'Copenhagen', 'DK', 13, 48, '18:00-21:00', 'An after-hours visit to a small gallery with the curator, then a short sketching session in the main hall.', false, 'photos/art-gallery.jpg' ),
		array( 'Founders Office Hours', 'Business', '', '', '', '', 15, 52, '15:00-16:30', 'Fifteen-minute slots with founders who have been there: pricing, first hires and finding customers.', true, 'photos/tech-workspace.jpg' ),
		array( 'Members Picnic', 'Community', 'Parque da Cidade', 'Avenida da Boavista', 'Porto', 'PT', 17, 56, '12:00-16:00', 'Blankets, food to share and lawn games in the park. Partners and kids welcome.', false, 'photos/festival-friends.jpg' ),
		array( 'Spring Members Meetup', 'Community', 'Riverside Commons', 'Rua do Infante 11', 'Porto', 'PT', 17, -12, '18:00-21:00', 'Our first in-person meetup: lightning talks, a photo wall and far too much food.', false, 'photos/festival-friends.jpg' ),
	),
	'home'   => array(
		'title'   => 'Discover events you will love',
		'text'    => 'Music nights, workshops, meetups and food festivals. Find your next experience and save your seat in seconds.',
		'image'   => 'photos/festival-friends.jpg',
		'browse'  => 'Browse all events',
		'host'    => 'Host an event',
		'closing' => 'Have something worth gathering for? Host your own event.',
		'coming'  => array( 'Coming up', 'The next events on the calendar, with tickets, venues and who is going.' ),
		'all'     => 'See every event',
	),
	'guide'  => array(
		'name'         => 'Eventonomy',
		'tab'          => 'Events',
		'member_label' => 'As a member',
		'owner_points' => array( 'Events, venues and organizers', 'Tickets, orders and attendees', 'Payments, emails and approval rules' ),
		'roles'        => array(
			'attendee'  => array( 'Attendee', array( 'RSVPs and tickets already in place', 'Upcoming and past events', 'Calendar files for every event' ) ),
			'organizer' => array( 'Organizer', array( 'Events live on the calendar', 'Attendee lists and check-in', 'Venues and a public profile' ) ),
		),
		'member'       => array(
			'browse'   => array( 'search', 'Find an event', 'Filter by date, category and place.', 'Browse events' ),
			'calendar' => array( 'calendar', 'See the calendar', 'Every event this month at a glance.', 'Open the calendar' ),
			'tickets'  => array( 'card', 'Your tickets', 'RSVPs and tickets for the events you are going to.', 'Open my tickets' ),
			'host'     => array( 'plus', 'Host an event', 'As the organizer, create an event with tickets and a venue.', 'Create an event' ),
		),
		'owner'        => array(
			'events'    => array( 'calendar', 'Manage events', 'Every event, with drafts and approvals.', 'Open events' ),
			'attendees' => array( 'users', 'Attendees and orders', 'Who is coming and what they paid.', 'Open attendees' ),
			'reports'   => array( 'chart', 'Reports', 'Ticket sales and attendance over time.', 'Open reports' ),
			'settings'  => array( 'settings', 'Settings', 'Payments, emails and who can host.', 'Open settings' ),
		),
		'highlights'   => array(
			array( 'calendar', 'Event pages and calendar', 'List, month and map views for every event.' ),
			array( 'card', 'Tickets and RSVPs', 'Free RSVPs or paid tickets with capacity.' ),
			array( 'pin', 'Venues and organizers', 'Reusable venues and organizer profiles.' ),
			array( 'play', 'Online events', 'Virtual events with a join link for attendees.' ),
		),
	),
);
