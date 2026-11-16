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
	// Designed home page: text only; the provider adds the links, counts and LearnDash's course list.
	'home'       => array(
		'title'    => 'Learn a new skill, one short lesson at a time',
		'text'     => 'Practical courses in design, development, community building and wellbeing, with lessons you can finish in a lunch break and progress you can see.',
		'image'    => 'photos/open-books.jpg',
		'browse'   => 'Browse courses',
		'second'   => 'Try a free lesson',
		'closing'  => 'Your next skill starts with one lesson. Pick a course and begin.',
		'featured' => array( 'Popular courses', 'Each course has a free first lesson, so you can see how it is taught before you enrol.' ),
		'cats'     => array( 'Browse by topic', 'Pick a topic to see every course in it.' ),
		'all'      => 'See all courses',
	),
);
