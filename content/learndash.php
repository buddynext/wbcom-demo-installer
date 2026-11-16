<?php
/**
 * LearnDash sample courses: the same catalogue as Learnomy (a site runs one LMS, so the demo reads the same
 * either way). Each Learnomy section becomes a LearnDash lesson and each of its lessons a topic. Photos are in
 * media/photos (media/CREDITS.json).
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

$wbcom_demo_learnomy = require __DIR__ . '/learnomy.php';

return array(
	'categories' => $wbcom_demo_learnomy['categories'],
	'courses'    => $wbcom_demo_learnomy['courses'],
);
