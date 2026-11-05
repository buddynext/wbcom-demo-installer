<?php
/**
 * Eventonomy sample events (cherry-picked from the Eventonomy demo set and fitted to the cast).
 * [ title, category, venue, address, city, country, organizer index, days from today, time, description, virtual ].
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	array( 'Monthly Community Call', 'Community', '', '', '', '', 2, 6, '17:00-18:00', 'Our monthly call: wins from the month, one short talk and open questions. Everyone is welcome, cameras optional.', true ),
	array( 'Design Critique Night', 'Design', 'The Studio', '12 Rua das Flores', 'Lisbon', 'PT', 0, 9, '18:30-20:30', 'Bring a screen you are stuck on. Three rounds of friendly, specific feedback, then drinks.', false ),
	array( 'Trail Run and Coffee', 'Outdoors', 'River Loop Trailhead', 'Skatås Motionscentrum', 'Gothenburg', 'SE', 4, 4, '08:00-10:00', 'An easy 8km loop at conversation pace, followed by coffee and cinnamon buns. All levels.', false ),
	array( 'Accessibility Clinic', 'Technology', '', '', '', '', 1, 13, '16:00-17:30', 'Share a page or app and we will review it live with a screen reader and a keyboard. Learn by watching real fixes.', true ),
	array( 'Indie Game Dev Meetup', 'Technology', 'Pixel Works', 'Calle Durango 210', 'Mexico City', 'MX', 17, 16, '18:30-20:30', 'Show your work, get feedback and meet other indie developers. Lightning demos and a friendly crowd.', false ),
	array( 'Founders Breakfast', 'Business', 'The Grand Hall', 'MG Road 44', 'Kochi', 'IN', 15, 20, '08:00-09:30', 'An intimate breakfast where three founders break down how they found their first hundred customers.', false ),
	array( 'Street Photography Walk', 'Photography', 'Gwanghwamun Square', 'Sejong-daero 172', 'Seoul', 'KR', 9, 24, '15:00-18:00', 'A slow walk through the old city with one rule: one lens, no zoom. We share our best frame at the end.', false ),
	array( 'Remote Hiring Workshop', 'Career', '', '', '', '', 12, 30, '14:00-15:30', 'How remote-first teams write job posts, run fair interviews and onboard people they have never met in person.', true ),
	array( 'Spring Members Meetup', 'Community', 'Riverside Commons', 'Rua do Infante 11', 'Porto', 'PT', 17, -12, '18:00-21:00', 'Our first in-person meetup: lightning talks, a photo wall and far too much food.', false ),
	array( 'Typography Evening', 'Design', 'Letterpress Lab', 'Shimogyo-ku 3-1', 'Kyoto', 'JP', 3, -25, '19:00-21:00', 'A hands-on evening setting type by hand, with a short talk on how letters are drawn.', false ),
);
