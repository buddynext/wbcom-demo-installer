<?php
/**
 * WP Sell Services sample content: services. Sellers are roster indices (content/people.php).
 * [ seller, title, category, base price, description, image ].
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'services' => array(
		array( 0, 'I will design a clear, friendly onboarding flow for your app', 'Design', 300, 'Research-led onboarding design. I review your current flow, interview two of your users and deliver Figma screens your developers can build straight away, with notes on every decision.', 'photos/wireframe-sketches.jpg' ),
		array( 3, 'I will draw a custom illustration set for your brand', 'Illustration', 220, 'A set of six illustrations in one consistent style, delivered as SVG and PNG, ready for your site, app and social posts. We start with a short call about your brand and audience.', 'photos/creative-studio-portrait.jpg' ),
		array( 8, 'I will rewrite your product copy in plain words', 'Writing', 150, 'Up to ten screens or one landing page, rewritten so people understand it on first read. Includes a short voice guide your team can use for every new screen after we finish.', 'photos/developer-desk.jpg' ),
		array( 13, 'I will design a logo and simple brand kit', 'Branding', 350, 'Three logo directions, one refined final logo, colours and type pairings, and a one-page brand guide that shows how to use them on your site, packaging and social posts.', 'photos/spectrum-gradient.jpg' ),
		array( 7, 'I will set up a fast, reliable Go API for your product', 'Development', 600, 'A small, well-tested REST API in Go with clear docs, deployment notes and a week of support after handover, so your team can run and extend it with confidence.', 'photos/developer-workspace.jpg' ),
		array( 17, 'I will run sound and AV for your meetup or conference', 'Events', 250, 'Mics, speakers, mixing and recording for meetups and conferences of up to 300 people. Setup, sound check and teardown are included, and you get the recordings the same week.', 'photos/festival-friends.jpg' ),
	),
);
