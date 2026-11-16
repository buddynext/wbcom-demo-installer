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
	'home'     => array(
		'title'   => 'Find the right freelancer for your next project',
		'text'    => 'Design, development, writing and marketing. Fixed prices, clear delivery dates and payment held safely until the work is done.',
		'image'   => 'photos/creative-studio-portrait.jpg',
		'browse'  => 'Browse services',
		'sell'    => 'Start selling',
		'closing' => 'Good at something? Turn it into a service people can buy.',
		'cats'    => array( 'Browse by category', 'Every service sorted by what you need done.' ),
		'popular' => array( 'Fresh services', 'The newest offers, each with a fixed price, a delivery date and the seller behind it.' ),
		'all'     => 'See all services',
	),
	// "Try the demo" page: text only; the provider adds people and links (Provider::guide()).
	'guide'    => array(
		'name'         => 'WP Sell Services',
		'tab'          => 'Marketplace',
		'member_label' => 'As a buyer',
		'owner_points' => array( 'Approve sellers and their services', 'Hold payments until the work is accepted', 'Set your commission and pay sellers out' ),
		'roles'        => array(
			'buyer'  => array( 'Buyer', array( 'Order any service in two clicks', 'Pay with Test Gateway, no real money', 'Message sellers and review their work' ) ),
			'seller' => array( 'Seller', array( 'Services, packages and orders to deliver', 'Earnings and payout requests', 'Reviews from real buyers' ) ),
		),
		'member'       => array(
			'browse' => array( 'search', 'Find a service', 'Search and filter by category, price and delivery time.', 'Browse services' ),
			'order'  => array( 'store', 'Order a package', 'Compare Basic, Standard and Premium, then order the one you want.', 'Open a service' ),
			'pay'    => array( 'card', 'Pay with Test Gateway', 'Checkout goes through at once and costs nothing.', 'Go to checkout' ),
			'follow' => array( 'message', 'Follow your order', 'Message the seller, accept the delivery and leave a review.', 'Open my orders' ),
			'sell'   => array( 'package', 'Deliver as the seller', 'Switches you to the seller to deliver work and check earnings.', 'Open the seller dashboard' ),
		),
		'owner'        => array(
			'orders'      => array( 'list', 'See every order', 'Status of each order and the money held until the buyer accepts.', 'Open orders' ),
			'vendors'     => array( 'users', 'Manage sellers', 'Earnings, commission and approval for each seller.', 'Open sellers' ),
			'withdrawals' => array( 'money', 'Pay sellers out', 'Approve the payouts sellers request.', 'Open payouts' ),
			'payments'    => array( 'card', 'Choose how buyers pay', 'Stripe, PayPal or the Test Gateway.', 'Open payment settings' ),
		),
		'highlights'   => array(
			array( 'store', 'Services with packages', 'Basic, Standard and Premium tiers with delivery times and revisions.' ),
			array( 'message', 'Order conversations', 'Buyers and sellers talk on the order, with files and deliveries.' ),
			array( 'shield', 'Payments held safely', 'Money waits until the buyer accepts the work.' ),
			array( 'money', 'Commission and payouts', 'Keep your cut and pay sellers on request.' ),
			array( 'star', 'Reviews and ratings', 'Buyers rate every completed order.' ),
			array( 'flag', 'Disputes', 'Step in when an order goes wrong.' ),
		),
	),
);
