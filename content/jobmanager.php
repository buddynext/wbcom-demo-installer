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
	// Company index => logo photo.
	'logos'     => array( 'photos/coffee-cheers.jpg', 'photos/wireframe-sketches.jpg', 'photos/chocolate-truffles.jpg' ),
	// WP Career Board job type => WP Job Manager's default job types.
	'types'     => array(
		'Full-time' => 'Full Time',
		'Part-time' => 'Part Time',
		'Contract'  => 'Freelance',
	),
);
