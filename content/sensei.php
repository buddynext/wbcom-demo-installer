<?php
/**
 * Sensei LMS sample courses: the same catalogue as Learnomy, LearnDash, Tutor LMS and LifterLMS (a site runs one LMS,
 * so the demo reads the same either way). Each Learnomy section becomes a Sensei module and each of its lessons a
 * lesson. Reviews are written only where the Reign Sensei add-on, which shows them, is active. Photos are in
 * media/photos (media/CREDITS.json).
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

$wbcom_demo_learnomy = require __DIR__ . '/learnomy.php';

return array(
	'categories' => $wbcom_demo_learnomy['categories'],
	'courses'    => $wbcom_demo_learnomy['courses'],
	'reviews'    => $wbcom_demo_learnomy['reviews'],
	// Designed home page: text only; the provider adds the links, counts and the course cards.
	'home'       => array(
		'title'    => 'Learn at your own pace, one module at a time',
		'text'     => 'Calm, well structured courses in modules and lessons, from teachers who answer. Pick up where you left off whenever you come back.',
		'image'    => 'photos/misty-forest-path.jpg',
		'browse'   => 'Browse courses',
		'second'   => 'My courses',
		'closing'  => 'Your next skill starts with one lesson. Pick a course and begin.',
		'featured' => array( 'Popular courses', 'A few of the courses. Open one to see its modules, its teacher and what students say.' ),
		'cats'     => array( 'Browse by topic', 'Pick a topic to see every course in it.' ),
		'all'      => 'See all courses',
	),
	// "Try the demo" page: text only; the provider adds people and links.
	'guide'      => array(
		'name'         => 'Sensei LMS',
		'tab'          => 'Courses',
		'member_label' => 'As a student',
		'owner_points' => array( 'Courses, modules and lessons', 'Students and their progress', 'Reports for every course' ),
		'roles'        => array(
			'student'    => array( 'Student', array( 'Courses in progress and one finished', 'Lessons marked complete', 'My Courses with progress' ) ),
			'instructor' => array( 'Teacher', array( 'Published courses and students', 'Modules and lessons', 'Course reports' ) ),
		),
		'member'       => array(
			'catalog'   => array( 'search', 'Browse the courses', 'Every course with its photo, teacher and category.', 'Open the courses' ),
			'course'    => array( 'play', 'Take a course', 'Modules, lessons, the teacher and your progress.', 'Open the course' ),
			'lesson'    => array( 'book', 'Continue a lesson', 'Read a lesson and mark it complete as the student.', 'Open the lesson' ),
			'dashboard' => array( 'cap', 'My courses', 'Courses in progress, the finished course and progress bars.', 'Open my courses' ),
		),
		'owner'        => array(
			'courses'  => array( 'book', 'Build a course', 'Modules and lessons in the course outline block.', 'Open courses' ),
			'students' => array( 'users', 'Manage students', 'Enrol, remove and follow every student.', 'Open students' ),
			'reports'  => array( 'chart', 'Read the reports', 'Progress and completion for each course and lesson.', 'Open reports' ),
			'settings' => array( 'settings', 'Sensei settings', 'Course pages, learning mode and emails.', 'Open settings' ),
		),
		'highlights'   => array(
			array( 'book', 'Course outline', 'Modules and lessons built right in the block editor.' ),
			array( 'cap', 'Learning mode', 'A focused lesson view with the course outline beside it.' ),
			array( 'chart', 'Reports', 'Progress and completion for every student.' ),
		),
	),
);
