<?php
/**
 * Gamification sample content: kudos messages between sample people.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'kudos' => array(
		'Thanks for the feedback on my design, it really helped.',
		'Your answer saved me hours. Thank you!',
		'Great event, thanks for hosting.',
		'Love how you welcome every new member.',
		'Thanks for the course, I finally get it.',
	),
	// "Try the demo" page: text only; the provider adds the links (Provider::guide()).
	'guide' => array(
		'name'       => 'WB Gamification',
		'tab'        => 'Rewards',
		'member'     => array(
			'leaderboard' => array( 'trophy', 'Climb the leaderboard', 'Points for posts, answers, courses and reviews.', 'Open the leaderboard' ),
			'badges'      => array( 'award', 'Earn badges', 'Milestones members unlock as they take part.', 'See badges' ),
		),
		'owner'      => array(
			'rules'  => array( 'chart', 'Set the rules', 'Choose what earns points and how much.', 'Open Gamification' ),
			'badges' => array( 'award', 'Badge library', 'Create badges and award them by hand or by rule.', 'Open badges' ),
		),
		'highlights' => array(
			array( 'trophy', 'Points and leaderboards', 'Reward members for taking part across every plugin.' ),
			array( 'award', 'Badges', 'Milestones members collect and show on their profile.' ),
		),
	),
);
