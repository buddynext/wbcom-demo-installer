<?php
/**
 * Tutor LMS sample courses: the same catalogue as Learnomy and LearnDash (a site runs one LMS, so the demo reads the
 * same either way). Each Learnomy section becomes a Tutor topic and each of its lessons a Tutor lesson. Reviews carry
 * a star rating, which Tutor shows on the course card. Photos are in media/photos (media/CREDITS.json).
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
		'title'    => 'Courses taught by people who do the work',
		'text'     => 'Design, development, community building and wellbeing, taught by working practitioners. Watch, practise, and track your progress on your own dashboard.',
		'image'    => 'photos/developer-desk.jpg',
		'browse'   => 'Browse courses',
		'second'   => 'Your dashboard',
		'closing'  => 'Your next skill starts with one lesson. Pick a course and begin.',
		'featured' => array( 'Popular courses', 'A few of the courses. Open one to see its lessons, its instructor and what students say.' ),
		'cats'     => array( 'Browse by topic', 'Pick a topic to see every course in it.' ),
		'all'      => 'See all courses',
	),
	// "Try the demo" page: text only; the provider adds people and links.
	'guide'      => array(
		'name'         => 'Tutor LMS',
		'tab'          => 'Courses',
		'member_label' => 'As a student',
		'owner_points' => array( 'Courses, topics and lessons', 'Students, progress and reviews', 'Instructors and earnings' ),
		'roles'        => array(
			'student'    => array( 'Student', array( 'Courses in progress and one finished', 'Ratings left on courses', 'A dashboard with progress' ) ),
			'instructor' => array( 'Instructor', array( 'Published courses and students', 'Reviews from students', 'The instructor dashboard' ) ),
		),
		'member'       => array(
			'catalog'   => array( 'search', 'Browse the courses', 'Filter by category and level, with ratings on every card.', 'Open the courses' ),
			'course'    => array( 'play', 'Open a course', 'Topics, lessons, the instructor and student reviews.', 'Open the course' ),
			'lesson'    => array( 'book', 'Take a lesson', 'Read a lesson and mark it complete as the student.', 'Open the lesson' ),
			'dashboard' => array( 'cap', 'Keep learning', 'Enrolled courses, progress bars and the finished course.', 'Open my dashboard' ),
			'teach'     => array( 'users', 'Teach as the instructor', 'Switches you to the instructor to see courses and students.', 'Open my courses' ),
		),
		'owner'        => array(
			'courses'     => array( 'book', 'Build a course', 'Topics, lessons, quizzes and pricing in the course builder.', 'Open courses' ),
			'students'    => array( 'users', 'Follow your students', 'Every student, their courses and progress.', 'Open students' ),
			'instructors' => array( 'award', 'Manage instructors', 'Approve instructors and see what they teach.', 'Open instructors' ),
			'settings'    => array( 'settings', 'Tutor settings', 'Course pages, monetization and the dashboard.', 'Open settings' ),
		),
		'highlights'   => array(
			array( 'book', 'Course builder', 'Topics, lessons and quizzes with a drag and drop builder.' ),
			array( 'cap', 'Student dashboard', 'Enrolled courses, progress and reviews in one place.' ),
			array( 'users', 'Many instructors', 'Instructor sign-up, approval and earnings.' ),
		),
	),
);
