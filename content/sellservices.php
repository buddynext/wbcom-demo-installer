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
	// "Try the demo" page: step text; the provider adds the links (Provider::guide()).
	'guide'    => array(
		'member'   => array(
			'browse' => 'Browse services and narrow them by category, price and delivery time.',
			'order'  => 'Open a service, compare its packages and order one.',
			'pay'    => 'At checkout choose Test Gateway. The order is paid at once and no real money moves.',
			'follow' => 'Follow the order in your dashboard: message the seller, accept the delivery and leave a review.',
		),
		'owner'    => array(
			'orders'      => 'See every order, its status and the money held until the buyer accepts the work.',
			'vendors'     => 'Check each seller, their earnings and the commission you keep.',
			'withdrawals' => 'Approve the payouts sellers request.',
			'payments'    => 'Choose the payment gateways buyers can pay with.',
		),
		'accounts' => array(
			'buyer'  => 'A buyer with orders in progress and delivered work to review.',
			'seller' => 'A seller with services, orders to deliver and earnings to withdraw.',
		),
	),
);
