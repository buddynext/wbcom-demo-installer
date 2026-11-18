<?php
/**
 * Learnomy sample courses. Instructors are roster indices.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'categories' => array(
		'design'      => 'Design',
		'development' => 'Development',
		'community'   => 'Community Building',
		'wellbeing'   => 'Wellbeing',
	),
	'courses'    => array(
		array(
			'title'      => 'Design Systems from Scratch',
			'image'      => 'photos/wireframe-sketches.jpg',
			'category'   => 'design',
			'instructor' => 0,
			'difficulty' => 'intermediate',
			'price'      => 0,
			'desc'       => 'Build a small design system your whole team will actually use: tokens, components and the habits that keep it alive.',
			// End-of-course quiz: [ question, answers, correct index ] x2, then an essay the instructor grades.
			'quiz'       => array(
				'title'     => 'Design systems check-in',
				'questions' => array(
					array( 'What should come before components in a new design system?', array( 'Tokens for colour, spacing, type and radius', 'A full icon set', 'A dark mode for every page' ), 0 ),
					array( 'Which token name follows the naming advice in this course?', array( 'grey-500', 'text-muted', 'colour-3' ), 1 ),
				),
				'essay'     => array( 'Pick one component your team rebuilds most often. How would you make it the single shared version?', 'Our date picker. I would build one with the shared tokens, document the right and wrong use, and give it an owner.' ), // Prompt, then a learner's answer.
			),
			'sections'   => array(
				array(
					'Foundations',
					array(
						array( 'Why design systems fail', 'Most design systems die quietly. Not because the components are bad, but because nobody owns them and nobody knows when to use them. In this lesson we look at three systems that stalled and the one habit that would have saved each.' ),
						array( 'Tokens before components', 'Colours, spacing, type and radius come first. When every component reads from the same small set of tokens, a rebrand becomes a one-file change. We will set up eight colour tokens and a four-step spacing scale.' ),
						array( 'Naming things so people find them', 'Name tokens by role, not by value: text-muted, not grey-500. You will rename a real palette and see how much easier it is to reason about.' ),
					),
				),
				array(
					'Components that last',
					array(
						array( 'The button ladder', 'One primary, one secondary, one quiet button, three sizes. That is the whole ladder. Anything else is a variant you probably do not need.' ),
						array( 'Documenting with real examples', 'A component page needs one example of the right use, one of the wrong use and a sentence on why. Screenshots of real screens beat abstract demos.' ),
						array( 'Keeping it alive', 'A monthly review, a changelog people read and a clear way to ask for a new component. Ownership is a calendar invite, not a title.' ),
					),
				),
			),
		),
		array(
			'title'      => 'Accessible Web Pages in a Weekend',
			'image'      => 'photos/developer-workspace.jpg',
			'category'   => 'development',
			'instructor' => 1,
			'difficulty' => 'beginner',
			'price'      => 29,
			'desc'       => 'The ten fixes that solve most accessibility problems, with a screen reader walkthrough for each.',
			// End-of-course quiz: [ question, answers, correct index ] x2, then an essay the instructor grades.
			'quiz'       => array(
				'title'     => 'Accessibility check-in',
				'questions' => array(
					array( 'What is the minimum contrast ratio for normal body text under WCAG AA?', array( '3:1', '4.5:1', '7:1' ), 1 ),
					array( 'What should an icon-only button always have?', array( 'A tooltip on hover', 'An accessible name, such as aria-label', 'A coloured border' ), 1 ),
				),
				'essay'     => array( 'Describe one page on your own site that a keyboard-only visitor would struggle with, and how you would fix it.', 'Our pricing page: the plan tabs only open on click. I would make them real buttons with a visible focus ring.' ), // Prompt, then a learner's answer.
			),
			'sections'   => array(
				array(
					'The basics',
					array(
						array( 'Headings are a map', 'Screen reader users jump between headings to understand a page. One h1, then h2 for each section, never skip a level. We will fix a real landing page in ten minutes.' ),
						array( 'Labels on every input', 'A placeholder is not a label. Every input needs a visible label linked with for and id. We will rebuild a sign-up form the right way.' ),
						array( 'Colour contrast that passes', 'Body text needs 4.5 to 1 against its background. We will check a palette with a contrast tool and fix the two colours that fail.' ),
					),
				),
				array(
					'Keyboard and focus',
					array(
						array( 'Everything reachable by keyboard', 'Unplug your mouse and try to buy something on your own site. Whatever you cannot reach, a keyboard user cannot either.' ),
						array( 'Focus you can see', 'Never remove the focus outline without a replacement. A two pixel ring in your accent colour is enough.' ),
					),
				),
			),
		),
		array(
			'title'      => 'Running a Community People Come Back To',
			'image'      => 'photos/cafe-friends.jpg',
			'category'   => 'community',
			'instructor' => 10,
			'difficulty' => 'beginner',
			'price'      => 0,
			'desc'       => 'Rituals, moderation and the first hundred members. What we learned growing a community from zero.',
			// End-of-course quiz: [ question, answers, correct index ] x2, then an essay the instructor grades.
			'quiz'       => array(
				'title'     => 'Community check-in',
				'questions' => array(
					array( 'What makes a new member most likely to come back after joining?', array( 'A welcome and a first conversation within days', 'A long list of rules', 'Badges for logging in' ), 0 ),
					array( 'What is a healthy sign in a growing community?', array( 'Only the founders post', 'Members answer each other\'s questions', 'Every post is moderated before it shows' ), 1 ),
				),
				'essay'     => array( 'Write the welcome message you would send a new member on their first day.', 'Welcome in! Tell us what you are working on this week, and say hello in the introductions thread.' ), // Prompt, then a learner's answer.
			),
			'sections'   => array(
				array(
					'The first hundred members',
					array(
						array( 'Invite people, not audiences', 'Your first members should be people you could message by name. Write ten personal invites before you write one announcement.' ),
						array( 'A reason to come back each week', 'One weekly ritual beats ten features. A Friday show and tell, a Monday goals thread, a monthly call.' ),
						array( 'Saying hello properly', 'Reply to every introduction in the first month, by name, with a question. It is the single biggest predictor of a second post.' ),
					),
				),
				array(
					'Keeping it healthy',
					array(
						array( 'Guidelines in plain words', 'Five short rules everyone can remember beat a page of legal text. Write them with your first members.' ),
						array( 'Moderating without drama', 'Act early, act privately, explain once. Most problems are misunderstandings that a direct message fixes.' ),
					),
				),
			),
		),
		array(
			'title'      => 'Data Visualisation for Busy People',
			'image'      => 'photos/developer-desk.jpg',
			'category'   => 'development',
			'instructor' => 4,
			'difficulty' => 'intermediate',
			'price'      => 49,
			'desc'       => 'Choose the right chart, cut the clutter and tell one clear story with your numbers.',
			// End-of-course quiz: [ question, answers, correct index ] x2, then an essay the instructor grades.
			'quiz'       => array(
				'title'     => 'Charts check-in',
				'questions' => array(
					array( 'Which chart fits comparing sales across five regions?', array( 'A pie chart with five slices', 'A bar chart sorted by value', 'A 3D area chart' ), 1 ),
					array( 'What should a chart title tell the reader?', array( 'The name of the data source', 'The point the chart makes', 'The software used to draw it' ), 1 ),
				),
				'essay'     => array( 'Pick a chart you saw at work this month. What one change would make its message clearer?', 'Our monthly sales pie chart. I would make it a sorted bar chart titled with the point: the north region doubled.' ), // Prompt, then a learner's answer.
			),
			'sections'   => array(
				array(
					'Pick the chart',
					array(
						array( 'One question per chart', 'Before you draw anything, write the question the chart answers in one sentence. If you need two sentences, you need two charts.' ),
						array( 'Bars, lines and almost nothing else', 'Bars compare, lines show change over time. Pie charts and 3D effects rarely help. We will redraw five common charts.' ),
						array( 'Colour with a purpose', 'Grey for context, one colour for the thing that matters. Your reader should see the point before they read the labels.' ),
					),
				),
			),
		),
		array(
			'title'      => 'Calm Focus: Ten Minutes a Day',
			'image'      => 'photos/misty-forest-path.jpg',
			'category'   => 'wellbeing',
			'instructor' => 14,
			'difficulty' => 'beginner',
			'price'      => 0,
			'desc'       => 'Short daily practices for focus and calm, designed for people who think they do not have time.',
			// End-of-course quiz: [ question, answers, correct index ] x2, then an essay the instructor grades.
			'quiz'       => array(
				'title'     => 'Focus check-in',
				'questions' => array(
					array( 'How long is the daily practice this course builds?', array( 'Ten minutes', 'One hour', 'Only on weekends' ), 0 ),
					array( 'What helps most when your mind wanders during practice?', array( 'Stopping for the day', 'Noticing it and gently coming back', 'Practising faster' ), 1 ),
				),
				'essay'     => array( 'When in your day could you protect ten quiet minutes, and what usually gets in the way?', 'Right after lunch, before meetings start. Messages are what usually get in the way, so I will mute them for those ten minutes.' ), // Prompt, then a learner's answer.
			),
			'sections'   => array(
				array(
					'Start small',
					array(
						array( 'The two minute start', 'Sit, breathe out longer than you breathe in, and count ten breaths. That is the whole practice for the first week.' ),
						array( 'Same time, same place', 'Attach the practice to something you already do: after the first coffee, before opening email.' ),
						array( 'When your mind wanders', 'It will. Noticing that it wandered is the practice, not a failure of it.' ),
					),
				),
			),
		),
	),
	'reviews'    => array(
		array( 5, 'Exactly what our team needed', 'Clear, practical and short. We set up our tokens the same week.' ),
		array( 4, 'Great foundations', 'Very useful. I would have liked one more example on documentation.' ),
		array( 5, 'Finally made it click', 'I have read about this topic for years. This is the first time I actually did it.' ),
		array( 5, 'Short lessons, big results', 'Each lesson takes ten minutes and changes something real.' ),
		array( 4, 'Recommended', 'Good pace and friendly tone. Worth it.' ),
	),
	'home'       => array(
		'title'    => 'Learn the skills that move your career forward',
		'text'     => 'Hands-on courses taught by working professionals. Learn at your own pace, earn a certificate and keep access on every device.',
		'image'    => 'photos/open-books.jpg',
		'browse'   => 'Browse all courses',
		'closing'  => 'Your next skill is one lesson away.',
		'courses'  => array( 'Popular courses to start with', 'Every course has preview lessons, quizzes and a certificate when you finish.' ),
		'all'      => 'See every course',
		'teachers' => array( 'Learn from people who do the work', 'Instructors who teach what they use every day.' ),
	),
	// "Try the demo" page: text only; the provider adds people and links (Provider::guide()).
	'guide'      => array(
		'name'         => 'Learnomy',
		'tab'          => 'Learn',
		'member_label' => 'As a student',
		'owner_points' => array( 'Build courses with lessons and quizzes', 'See students, revenue and certificates', 'Sell memberships and coupons' ),
		'roles'        => array(
			'student'    => array( 'Student', array( 'Courses in progress and certificates', 'Buy a course with Test payment', 'Notes, quizzes and progress' ) ),
			'instructor' => array( 'Instructor', array( 'Published courses and students', 'Earnings at a glance', 'Quiz answers to grade' ) ),
		),
		'member'       => array(
			'catalog' => array( 'search', 'Browse the catalog', 'Filter courses by category, level and price.', 'Open the catalog' ),
			'course'  => array( 'play', 'Preview a course', 'Watch the free preview and read the reviews.', 'Open the course' ),
			'pay'     => array( 'card', 'Buy it with Test payment', 'Checkout goes through at once and costs nothing.', 'Enroll now' ),
			'account' => array( 'cap', 'Keep learning', 'Progress, notes, quiz results and certificates.', 'Open my courses' ),
			'teach'   => array( 'check', 'Grade as the instructor', 'Switches you to the instructor to review students and grade answers.', 'Open grading' ),
		),
		'owner'        => array(
			'courses'      => array( 'book', 'Build a course', 'Sections, lessons, quizzes and drip schedules.', 'Open courses' ),
			'students'     => array( 'users', 'Follow your students', 'Progress, scores and certificates for every learner.', 'Open students' ),
			'memberships'  => array( 'money', 'Sell memberships', 'Plans, subscriptions, coupons and gift codes.', 'Open memberships' ),
			'transactions' => array( 'chart', 'Track revenue', 'Payments, refunds and instructor earnings.', 'Open transactions' ),
		),
		'highlights'   => array(
			array( 'book', 'Course builder', 'Sections, lessons, quizzes, assignments and drip content.' ),
			array( 'card', 'Payments built in', 'Stripe, PayPal or WooCommerce, with coupons and refunds.' ),
			array( 'money', 'Memberships', 'Plans that unlock groups of courses.' ),
			array( 'users', 'Many instructors', 'Applications, co-instructors and commissions.' ),
			array( 'award', 'Certificates', 'Issued on completion and verifiable by anyone.' ),
		),
	),
);
