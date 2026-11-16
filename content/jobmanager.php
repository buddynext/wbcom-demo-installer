<?php
/**
 * WP Job Manager sample jobs: the same companies and jobs as WP Career Board (a site runs one job board, so the
 * demo reads the same either way), plus a company logo per company from media/photos (media/CREDITS.json).
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

$wbcom_demo_careerboard = require __DIR__ . '/careerboard.php';

return array(
	'companies' => $wbcom_demo_careerboard['companies'],
	'jobs'      => $wbcom_demo_careerboard['jobs'],
	// Designed home page: text only; the provider adds the links, counts and the latest jobs.
	'home'      => array(
		'title'          => 'Find work you will look forward to',
		'text'           => 'Roles from small, friendly teams, many of them remote, with the salary on every listing.',
		'image'          => 'photos/city-skyline-dusk.jpg',
		'browse'         => 'Browse jobs',
		'post'           => 'Post a job',
		'closing'        => 'Hiring? Post your job and meet people who want to work with you.',
		'browse_closing' => 'New roles every week. Find the one that fits you.',
		'latest'         => array( 'Latest jobs', 'New roles this month. Open one to see the salary, the team and how to apply.' ),
		'all'            => 'See all jobs',
	),
	// Company index => logo photo.
	'logos'     => array( 'photos/coffee-cheers.jpg', 'photos/wireframe-sketches.jpg', 'photos/chocolate-truffles.jpg' ),
	// WP Career Board job type => WP Job Manager's default job types.
	'types'     => array(
		'Full-time' => 'Full Time',
		'Part-time' => 'Part Time',
		'Contract'  => 'Freelance',
	),
);
