<?php
/**
 * Demos for the Starting point gallery, in two sections:
 * - theme: a whole site for the active theme (Reign, BuddyX, BuddyX Pro): a community platform and a mix of products;
 * - addon: one per Reign add-on, built around the plugin that add-on presents.
 *
 * Each demo: theme family (Providers\Theme::theme_family()), the plugins it needs (catalog ids, see
 * Core\Installer::catalog(); "community" means any community plugin, else BuddyPress), the Reign add-on it presents
 * (addon demos) or the add-ons that improve it (theme demos, optional), the products it builds (provider ids) and
 * its live sandbox, its niche (content/niches/<niche>/<provider>.php replaces those keys of the provider's sample
 * content), and its setup film per theme family ('*': any; YouTube id and length). The card image is that sandbox's image on wbcomdesigns.com/demos, or media/demos/<id>.webp for a demo with no sandbox of its own.
 *
 * Filter: wbcom_demo_demos.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

$wbcom_demo_ours = array( 'learnomy', 'eventonomy', 'jetonomy', 'careerboard' );
$wbcom_demo_bn   = array( 'buddynext', 'mediaverse', 'jetonomy', 'eventonomy', 'gamification', 'careerboard' );

return array(
	// Our products, one per purpose. Shown first on any theme; a third-party demo for the same purpose takes the
	// place only when the site already has that plugin or its Reign add-on. "members" is the community plugin the
	// site runs, else BuddyNext with WPMediaVerse, Jetonomy and WB Gamification.
	'community'                 => array(
		'group'    => 'ours',
		'purpose'  => 'community',
		'theme'    => '*',
		'name'     => 'BuddyNext community',
		'desc'     => 'Members, spaces, a feed with photos and video, forums, a roadmap and points.',
		'requires' => array( 'buddynext', 'mediaverse', 'jetonomy', 'roadmap', 'gamification', 'pages' ),
		'preview'  => 'https://buddynext.instawp.link/',
		'film'     => array( 'Reign' => array( 'vxM1m22RxJ8', '3:11' ) ),
	),
	'learning'                  => array(
		'group'    => 'ours',
		'purpose'  => 'learning',
		'theme'    => '*',
		'name'     => 'Learnomy courses',
		'desc'     => 'Courses with lessons, instructors and students making progress, inside a member community.',
		'requires' => array( 'members', 'learnomy', 'mediashield', 'pages' ),
		'preview'  => 'https://learnomy.instawp.co/',
		'film'     => array( 'Reign' => array( 'csWX1FwJ3XY', '3:01' ) ),
	),
	'events'                    => array(
		'group'    => 'ours',
		'purpose'  => 'events',
		'theme'    => '*',
		'name'     => 'Eventonomy events',
		'desc'     => 'Meetups with tickets, RSVPs and venues, and the members who come.',
		'requires' => array( 'members', 'eventonomy', 'pages' ),
		'preview'  => 'https://eventonomy.instawp.co/',
		'film'     => array( 'Reign' => array( 'IQkm4nDu4IM', '2:50' ) ),
	),
	'jobs'                      => array(
		'group'    => 'ours',
		'purpose'  => 'jobs',
		'theme'    => '*',
		'name'     => 'WP Career Board jobs',
		'desc'     => 'Employers, job listings and candidate profiles, with members around them.',
		'requires' => array( 'members', 'careerboard', 'pages' ),
		'preview'  => 'https://wpcareerboard.instawp.co/',
		'film'     => array( 'Reign' => array( 'I_lc8uBm8Q8', '2:56' ) ),
	),
	'directory'                 => array(
		'group'    => 'ours',
		'purpose'  => 'directory',
		'theme'    => '*',
		'name'     => 'WB Listora directory',
		'desc'     => 'Listings with photos, reviews, maps and favorites, and the members who use them.',
		'requires' => array( 'members', 'listora', 'pages' ),
		'preview'  => 'https://wblistora.instawp.co/',
		'film'     => array( 'Reign' => array( '6vXdtmoZ-uc', '3:03' ) ),
	),
	'services'                  => array(
		'group'    => 'ours',
		'purpose'  => 'services',
		'theme'    => '*',
		'name'     => 'WP Sell Services marketplace',
		'desc'     => 'Freelancers selling services, with orders, messages and reviews.',
		'requires' => array( 'members', 'sellservices', 'pages' ),
		'preview'  => 'https://wpsellservices.instawp.co/',
		'film'     => array( 'Reign' => array( 'WmfIJOV8Tgc', '2:57' ) ),
	),
	'shop'                      => array(
		'group'    => 'ours',
		'purpose'  => 'shop',
		'theme'    => '*',
		'name'     => 'WooCommerce store',
		'desc'     => 'A store with product photos, sale prices, stock and reviews.',
		'requires' => array( 'woocommerce', 'pages' ),
		'preview'  => 'https://reign-shop.instawp.xyz/',
	),
	// Theme demos: Reign.
	'reign-buddypress'          => array(
		'purpose'  => 'community',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'buddypress' ),
		'group'    => 'theme',
		'theme'    => 'Reign',
		'name'     => 'Reign + BuddyPress',
		'desc'     => 'A BuddyPress community with courses, events, forums, jobs and a marketplace of member stores.',
		'requires' => array_merge( array( 'buddypress' ), $wbcom_demo_ours, array( 'woocommerce', 'dokan', 'pages' ) ),
		'optional' => array( array( 'reign-dokan-addon', 'Reign Dokan Addon' ) ),
		'preview'  => 'https://dmine-base-nice.instawp.xyz/',
	),
	'reign-buddyboss'           => array(
		'purpose'  => 'community',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'buddyboss' ),
		'group'    => 'theme',
		'theme'    => 'Reign',
		'name'     => 'Reign + BuddyBoss',
		'desc'     => 'A BuddyBoss Platform community with LearnDash courses and a job board.',
		'requires' => array( 'buddyboss', 'learndash', 'jobmanager', 'pages' ),
		'optional' => array( array( 'reign-learndash-addon', 'Reign LearnDash Addon' ), array( 'reign-wp-job-manager-addon', 'Reign WP Job Manager Addon' ) ),
		'preview'  => 'https://buddyboss.reigntheme.com/',
	),
	'reign-peepso'              => array(
		'purpose'  => 'community',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'peepso' ),
		'group'    => 'theme',
		'theme'    => 'Reign',
		'name'     => 'Reign + PeepSo',
		'desc'     => 'A PeepSo community stream with courses, events, forums and jobs around it.',
		'requires' => array_merge( array( 'peepso' ), $wbcom_demo_ours, array( 'pages' ) ),
		'preview'  => 'https://ark-babies-kipi.instawp.xyz/',
	),
	'reign-buddynext'           => array(
		'group'    => 'theme',
		'theme'    => 'Reign',
		'name'     => 'Reign + BuddyNext',
		'desc'     => 'The full Wbcom suite: BuddyNext community, media, forums, courses, events, jobs, a directory and services.',
		'requires' => array( 'buddynext', 'mediaverse', 'jetonomy', 'learnomy', 'eventonomy', 'careerboard', 'listora', 'sellservices', 'gamification', 'pages' ),
		'preview'  => 'https://buddynext.instawp.link/',
		'film'     => array( '*' => array( 'jLANmm3uAGI', '3:10' ) ),
	),
	'reign-buddynext-learndash' => array(
		'group'    => 'theme',
		'theme'    => 'Reign',
		'name'     => 'Reign + BuddyNext + LearnDash + Dokan',
		'desc'     => 'A BuddyNext community with LearnDash courses and a Dokan marketplace.',
		'requires' => array_merge( $wbcom_demo_bn, array( 'learndash', 'woocommerce', 'dokan', 'pages' ) ),
		'optional' => array( array( 'reign-learndash-addon', 'Reign LearnDash Addon' ), array( 'reign-dokan-addon', 'Reign Dokan Addon' ) ),
		'preview'  => 'https://reign-buddynext-learndash.instawp.xyz/',
	),
	'reign-buddynext-tutorlms'  => array(
		'group'    => 'theme',
		'theme'    => 'Reign',
		'name'     => 'Reign + BuddyNext + Tutor LMS + WCFM',
		'desc'     => 'A BuddyNext community with Tutor LMS courses and a WCFM marketplace.',
		'requires' => array_merge( $wbcom_demo_bn, array( 'tutorlms', 'woocommerce', 'wcfm-frontend', 'wcfm', 'pages' ) ),
		'optional' => array( array( 'reign-tutorlms-addon', 'Reign TutorLMS Addon' ), array( 'reign-wcfm-addon', 'Reign WCFM Addon' ) ),
		'preview'  => 'https://reign-buddynext-tutorlms.instawp.xyz/',
	),
	'reign-buddynext-lifterlms' => array(
		'group'    => 'theme',
		'theme'    => 'Reign',
		'name'     => 'Reign + BuddyNext + LifterLMS + WC Vendors',
		'desc'     => 'A BuddyNext community with LifterLMS courses and WC Vendors shops.',
		'requires' => array_merge( $wbcom_demo_bn, array( 'lifterlms', 'woocommerce', 'wcvendors', 'pages' ) ),
		'optional' => array( array( 'reign-lifterlms-addon', 'Reign LifterLMS Addon' ), array( 'reign-wc-vendor-addon', 'Reign WC Vendors Addon' ) ),
		'preview'  => 'https://reign-buddynext-lifterlms.instawp.xyz/',
	),
	'reign-shop'                => array(
		'group'    => 'theme',
		'theme'    => 'Reign',
		'name'     => 'Reign Shop',
		'desc'     => 'A WooCommerce store with product photos, sale prices, stock and reviews.',
		'requires' => array( 'woocommerce', 'pages' ),
		'preview'  => 'https://reign-shop.instawp.xyz/',
	),
	// Theme demos: BuddyX and BuddyX Pro.
	'buddyx-buddypress'         => array(
		'purpose'  => 'community',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'buddypress' ),
		'group'    => 'theme',
		'theme'    => 'BuddyX',
		'name'     => 'BuddyX + BuddyPress',
		'desc'     => 'A BuddyPress community with courses, events, forums, jobs and a shop.',
		'requires' => array_merge( array( 'buddypress' ), $wbcom_demo_ours, array( 'woocommerce', 'pages' ) ),
		'preview'  => 'https://x-baseball-dota.instawp.xyz/',
	),
	'buddyx-buddynext'          => array(
		'group'    => 'theme',
		'theme'    => 'BuddyX',
		'name'     => 'BuddyX + BuddyNext',
		'desc'     => 'A BuddyNext community with media, forums, courses, events and jobs.',
		'requires' => array_merge( $wbcom_demo_bn, array( 'learnomy', 'pages' ) ),
		'preview'  => 'https://buddynext-buddyx.instawp.co/',
		'film'     => array( '*' => array( 'Q1MPFOxc388', '3:47' ) ),
	),
	'buddyxpro-buddypress'      => array(
		'purpose'  => 'community',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'buddypress' ),
		'group'    => 'theme',
		'theme'    => 'BuddyX Pro',
		'name'     => 'BuddyX Pro + BuddyPress',
		'desc'     => 'A BuddyPress community with courses, events, forums, jobs and a Dokan marketplace.',
		'requires' => array_merge( array( 'buddypress' ), $wbcom_demo_ours, array( 'woocommerce', 'dokan', 'pages' ) ),
		'preview'  => 'https://buddyxpro-buddypress.instawp.xyz/',
	),
	'buddyxpro-buddyboss'       => array(
		'purpose'  => 'community',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'buddyboss' ),
		'group'    => 'theme',
		'theme'    => 'BuddyX Pro',
		'name'     => 'BuddyX Pro + BuddyBoss',
		'desc'     => 'A BuddyBoss Platform community with courses, events and forums.',
		'requires' => array_merge( array( 'buddyboss' ), $wbcom_demo_ours, array( 'pages' ) ),
		'preview'  => 'https://bb-buddyxpro.instawp.xyz/',
	),
	'buddyxpro-buddynext'       => array(
		'group'    => 'theme',
		'theme'    => 'BuddyX Pro',
		'name'     => 'BuddyX Pro + BuddyNext',
		'desc'     => 'The full Wbcom suite on BuddyX Pro: community, media, forums, courses, events, jobs, a directory and services.',
		'requires' => array( 'buddynext', 'mediaverse', 'jetonomy', 'learnomy', 'eventonomy', 'careerboard', 'listora', 'sellservices', 'gamification', 'pages' ),
		'preview'  => 'https://buddynext-buddyxpro.instawp.co/',
		'film'     => array( '*' => array( 'W2rs9rpKEYk', '3:12' ) ),
	),
	// Add-on demos: one per Reign add-on.
	'reign-learndash'           => array(
		'purpose'  => 'learning',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'learndash' ),
		'group'    => 'addon',
		'theme'    => 'Reign',
		'name'     => 'Reign LearnDash',
		'desc'     => 'Courses with lessons and topics, students part way through, and the members around them.',
		'addon'    => array( 'reign-learndash-addon', 'Reign LearnDash Addon' ),
		'requires' => array( 'learndash', 'community', 'pages' ),
		'preview'  => 'https://reign-learndash-dashboard.instawp.xyz/',
	),
	'reign-tutorlms'            => array(
		'purpose'  => 'learning',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'tutorlms' ),
		'group'    => 'addon',
		'theme'    => 'Reign',
		'name'     => 'Reign Tutor LMS',
		'desc'     => 'Tutor LMS courses with topics, lessons, ratings and students, and the members around them.',
		'addon'    => array( 'reign-tutorlms-addon', 'Reign TutorLMS Addon' ),
		'requires' => array( 'tutorlms', 'community', 'pages' ),
		'preview'  => 'https://reign-tutorlms.instawp.xyz/',
	),
	'reign-lifterlms'           => array(
		'purpose'  => 'learning',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'lifterlms' ),
		'group'    => 'addon',
		'theme'    => 'Reign',
		'name'     => 'Reign LifterLMS',
		'desc'     => 'LifterLMS courses with access plans, reviews and students making progress.',
		'addon'    => array( 'reign-lifterlms-addon', 'Reign LifterLMS Addon' ),
		'requires' => array( 'lifterlms', 'community', 'pages' ),
		'preview'  => 'https://reign-wcfm.instawp.xyz/',
	),
	'reign-sensei'              => array(
		'purpose'  => 'learning',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'sensei' ),
		'group'    => 'addon',
		'theme'    => 'Reign',
		'name'     => 'Reign Sensei LMS',
		'desc'     => 'Sensei courses with modules and lessons, teachers, students and course ratings.',
		'addon'    => array( 'reign-sensei-addon', 'Reign Sensei Addon' ),
		'requires' => array( 'sensei', 'community', 'pages' ),
		'preview'  => '',
	),
	'reign-dokan'               => array(
		'niche'    => 'store', // The community talks about what the shops sell (content/niches/store).
		'purpose'  => 'shop',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'dokan' ),
		'group'    => 'addon',
		'theme'    => 'Reign',
		'name'     => 'Reign Dokan',
		'desc'     => 'A marketplace of independent stores selling real products, with sellers and members.',
		'addon'    => array( 'reign-dokan-addon', 'Reign Dokan Addon' ),
		'requires' => array( 'woocommerce', 'dokan', 'community', 'pages' ),
		'preview'  => 'https://dmine-base-nice.instawp.xyz/',
	),
	'reign-wcfm'                => array(
		'niche'    => 'store', // The community talks about what the shops sell (content/niches/store).
		'purpose'  => 'shop',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'wcfm' ),
		'group'    => 'addon',
		'theme'    => 'Reign',
		'name'     => 'Reign WCFM',
		'desc'     => 'A WCFM marketplace with store logos and banners, products and the members around them.',
		'addon'    => array( 'reign-wcfm-addon', 'Reign WCFM Addon' ),
		'requires' => array( 'woocommerce', 'wcfm-frontend', 'wcfm', 'community', 'pages' ),
		'preview'  => 'https://reign-wcfm.instawp.xyz/',
	),
	'reign-wc-vendors'          => array(
		'niche'    => 'store', // The community talks about what the shops sell (content/niches/store).
		'purpose'  => 'shop',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'wcvendors' ),
		'group'    => 'addon',
		'theme'    => 'Reign',
		'name'     => 'Reign WC Vendors',
		'desc'     => 'Vendor shops selling the sample products, with a become-a-vendor path and members.',
		'addon'    => array( 'reign-wc-vendor-addon', 'Reign WC Vendors Addon' ),
		'requires' => array( 'woocommerce', 'wcvendors', 'community', 'pages' ),
		'preview'  => 'https://reign-wc-vendor.instawp.xyz/',
	),
	'reign-geodirectory'        => array(
		'purpose'  => 'directory',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'geodirectory' ),
		'group'    => 'addon',
		'theme'    => 'Reign',
		'name'     => 'Reign GeoDirectory',
		'desc'     => 'A local directory: places with photos, hours, reviews and a map, and the neighbours who use it.',
		'addon'    => array( 'reign-geodirectory', 'Reign GeoDirectory Addon' ),
		'requires' => array( 'geodirectory', 'community', 'pages' ),
		'preview'  => 'https://reign-geodirectory.instawp.xyz/',
	),
	'reign-wp-job-manager'      => array(
		'purpose'  => 'jobs',
		// Shown in place of ours when this plugin is active or its Reign add-on is uploaded.
		'trigger'  => array( 'jobmanager' ),
		'group'    => 'addon',
		'theme'    => 'Reign',
		'name'     => 'Reign WP Job Manager',
		'desc'     => 'A job board with listings from sample companies, and the members who apply.',
		'addon'    => array( 'reign-wp-job-manager-addon', 'Reign WP Job Manager Addon' ),
		'requires' => array( 'jobmanager', 'community', 'pages' ),
		'preview'  => 'https://buddyboss.reigntheme.com/',
	),
);
