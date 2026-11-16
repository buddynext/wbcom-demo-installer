<?php
/**
 * "Try the demo" text for third-party plugins a demo site runs (Providers\Integration). Guide only: nothing is built.
 * Keys are integration ids; each is shown only when its plugin is active.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

$wbcom_demo_member = array( 'Member', array( 'A profile, friends and groups already set up', 'Activity, messages and notifications', 'Courses, jobs and orders where the site has them' ) );

return array(
	'peepso'      => array(
		'name'         => 'PeepSo',
		'tab'          => 'Community',
		'member_label' => 'As a member',
		'owner_points' => array( 'Members, groups and the stream', 'Profiles and privacy', 'Which PeepSo features are on' ),
		'roles'        => array( 'member' => $wbcom_demo_member ),
		'member'       => array(
			'activity' => array( 'home', 'Post to the stream', 'Share an update, react and comment.', 'Open the stream' ),
			'members'  => array( 'users', 'Meet the members', 'Profiles, friends and messages.', 'Open members' ),
			'groups'   => array( 'message', 'Join a group', 'Groups with their own stream.', 'Open groups' ),
		),
		'owner'        => array(
			'components' => array( 'settings', 'PeepSo settings', 'Features, profiles and privacy.', 'Open PeepSo' ),
			'users'      => array( 'users', 'Members', 'Every member, their role and profile.', 'Open users' ),
		),
		'highlights'   => array(
			array( 'home', 'Activity stream', 'Updates, reactions and comments from across the site.' ),
			array( 'users', 'Profiles and groups', 'Member profiles, friends and groups.' ),
		),
	),
	'bbpress'     => array(
		'name'         => 'bbPress',
		'tab'          => 'Forums',
		'member_label' => 'As a member',
		'owner_points' => array( 'Forums and topics', 'Moderation and spam', 'Who can post where' ),
		'roles'        => array( 'member' => $wbcom_demo_member ),
		'member'       => array(
			'forums' => array( 'message', 'Join a forum', 'Start a topic or reply to one.', 'Open the forums' ),
		),
		'owner'        => array(
			'forums' => array( 'list', 'Manage forums', 'Forums, topics and replies.', 'Open forums' ),
		),
		'highlights'   => array(
			array( 'message', 'Forums', 'Topics and threaded replies for your members.' ),
		),
	),
	'learndash'   => array(
		'name'         => 'LearnDash',
		'tab'          => 'Courses',
		'member_label' => 'As a student',
		'owner_points' => array( 'Courses, lessons and quizzes', 'Students and progress', 'Certificates and access rules' ),
		'roles'        => array( 'member' => $wbcom_demo_member ),
		'member'       => array(
			'courses' => array( 'cap', 'Take a course', 'Lessons, quizzes and your progress.', 'Open the courses' ),
		),
		'owner'        => array(
			'courses' => array( 'book', 'Build courses', 'Courses, lessons, topics and quizzes.', 'Open courses' ),
		),
		'highlights'   => array(
			array( 'cap', 'Online courses', 'Lessons, quizzes and certificates with LearnDash.' ),
		),
	),
	'woocommerce' => array(
		'name'         => 'WooCommerce',
		'tab'          => 'Shop',
		'member_label' => 'As a customer',
		'owner_points' => array( 'Products and stock', 'Orders and customers', 'Payments and shipping' ),
		// A store-only demo has no profiles or groups, so the shared member card would promise them. On a community
		// demo the community provider's card wins, because integrations are ordered last.
		'roles'        => array( 'member' => array( 'Customer', array( 'A cart ready for checkout', 'Past orders and saved addresses', 'Reviews on products they bought' ) ) ),
		'member'       => array(
			'shop'    => array( 'store', 'Browse the shop', 'Products with photos, prices and reviews.', 'Open the shop' ),
			'account' => array( 'card', 'Your orders', 'Past orders, addresses and account details.', 'Open my account' ),
		),
		'owner'        => array(
			'orders'   => array( 'list', 'Orders', 'Every order and its status.', 'Open orders' ),
			'products' => array( 'package', 'Products', 'Add and edit products.', 'Open products' ),
		),
		'highlights'   => array(
			array( 'store', 'Shop', 'A full WooCommerce store styled by the theme.' ),
		),
	),
	'dokan'       => array(
		'name'         => 'Dokan',
		'tab'          => 'Marketplace',
		'member_label' => 'As a vendor',
		'owner_points' => array( 'Vendors and their stores', 'Commissions and withdrawals', 'Products and orders' ),
		'roles'        => array( 'vendor' => array( 'Vendor', array( 'A store with products', 'Orders and earnings', 'A vendor dashboard' ) ) ),
		'member'       => array(
			'stores'    => array( 'store', 'Browse the stores', 'Every vendor and their products.', 'Open the stores' ),
			'dashboard' => array( 'chart', 'Run a store', 'As a vendor, see orders, products and earnings.', 'Open the vendor dashboard' ),
		),
		'owner'        => array(
			'dokan' => array( 'store', 'Marketplace', 'Vendors, commissions and withdrawals.', 'Open Dokan' ),
		),
		'highlights'   => array(
			array( 'store', 'Multi-vendor marketplace', 'Vendors sell from their own stores with Dokan.' ),
		),
	),
	'jobmanager'  => array(
		'name'         => 'WP Job Manager',
		'tab'          => 'Jobs',
		'member_label' => 'As a member',
		'owner_points' => array( 'Job listings', 'Applications and resumes', 'Listing rules and expiry' ),
		'roles'        => array( 'member' => $wbcom_demo_member ),
		'member'       => array(
			'jobs' => array( 'search', 'Find a job', 'Filter listings by keyword and location.', 'Open the jobs' ),
		),
		'owner'        => array(
			'jobs' => array( 'list', 'Job listings', 'Approve, edit and expire listings.', 'Open listings' ),
		),
		'highlights'   => array(
			array( 'search', 'Job board', 'Listings and applications with WP Job Manager.' ),
		),
	),
	'tribeevents' => array(
		'name'         => 'The Events Calendar',
		'tab'          => 'Events',
		'member_label' => 'As a member',
		'owner_points' => array( 'Events and venues', 'Organizers', 'Calendar views' ),
		'roles'        => array( 'member' => $wbcom_demo_member ),
		'member'       => array(
			'events' => array( 'calendar', 'See what is on', 'Upcoming events in list and month views.', 'Open the calendar' ),
		),
		'owner'        => array(
			'events' => array( 'calendar', 'Manage events', 'Events, venues and organizers.', 'Open events' ),
		),
		'highlights'   => array(
			array( 'calendar', 'Events calendar', 'Events with venues and organizers.' ),
		),
	),
);
