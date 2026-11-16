<?php
/**
 * LifterLMS sample courses: the same catalogue as Learnomy, LearnDash and Tutor LMS (a site runs one LMS, so the demo
 * reads the same either way). Each Learnomy section becomes a LifterLMS section and each of its lessons a lesson.
 * LifterLMS reviews have a title and text, no stars. Photos are in media/photos (media/CREDITS.json).
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
		'title'    => 'Learn a new skill, one short lesson at a time',
		'text'     => 'Practical courses in design, development, community building and wellbeing, taught by people who do the work every day.',
		'image'    => 'photos/open-books.jpg',
		'browse'   => 'Browse courses',
		'second'   => 'Student dashboard',
		'closing'  => 'Your next skill starts with one lesson. Pick a course and begin.',
		'featured' => array( 'Popular courses', 'Each course shows its syllabus, its instructor and a free preview lesson.' ),
		'cats'     => array( 'Browse by topic', 'Pick a topic to see every course in it.' ),
		'all'      => 'See all courses',
	),
	// "Try the demo" page: text only; the provider adds people and links.
	'guide'      => array(
		'name'         => 'LifterLMS',
		'tab'          => 'Courses',
		'member_label' => 'As a student',
		'owner_points' => array( 'Courses, sections and lessons', 'Students, progress and reports', 'Access plans and pricing' ),
		'roles'        => array(
			'student'    => array( 'Student', array( 'Courses in progress and one finished', 'Reviews left on courses', 'A dashboard with progress' ) ),
			'instructor' => array( 'Instructor', array( 'Published courses and students', 'Reviews from students', 'Course reports' ) ),
		),
		'member'       => array(
			'catalog'   => array( 'search', 'Browse the courses', 'Every course with its photo, price and instructor.', 'Open the courses' ),
			'course'    => array( 'play', 'Open a course', 'The syllabus, a free preview lesson and student reviews.', 'Open the course' ),
			'lesson'    => array( 'book', 'Take a lesson', 'Read a lesson and mark it complete as the student.', 'Open the lesson' ),
			'dashboard' => array( 'cap', 'Keep learning', 'Courses in progress, the finished course and progress bars.', 'Open my dashboard' ),
		),
		'owner'        => array(
			'courses'   => array( 'book', 'Build a course', 'Sections, lessons and quizzes in the course builder.', 'Open courses' ),
			'reporting' => array( 'chart', 'Follow your students', 'Enrolments, progress and grades for every student.', 'Open reporting' ),
			'settings'  => array( 'settings', 'LifterLMS settings', 'Catalog, dashboard, checkout and payments.', 'Open settings' ),
		),
		'highlights'   => array(
			array( 'book', 'Course builder', 'Sections, lessons and quizzes with a drag and drop builder.' ),
			array( 'cap', 'Student dashboard', 'Courses, progress, achievements and certificates.' ),
			array( 'card', 'Access plans', 'Free, one-time and recurring plans on every course.' ),
		),
	),
);
