<?php
/**
 * Kanbanomy sample content: a small agency's client work, a public support board, an internal estimates board,
 * and the "Try the demo" guide.
 *
 * People are [ '', roster index ] into the shared cast (content/people.php), chosen by job so nobody plays two
 * parts: Emma (product manager) runs the projects, Ravi and Grace are the clients, Mateo, Priya, Tom and Amina are
 * the team. `cast` names the manager and the first client for the "Try the demo" cards. Days are relative to the build day: negative is
 * the past, positive the future. A task is [ title, status, priority, due day, person, estimated hours, milestone
 * index (-1 none), options ]; options: desc, labels (Kanbanomy's predefined labels, by name), check
 * ([ text, done ]), comments ([ person, text ]), log ([ person, minutes ]), dep (indexes of earlier tasks),
 * recur, internal, approval (ask the client to approve).
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

$wbcom_demo_kb_manager = array( '', 18 ); // Emma Johansson, Product Manager.
$wbcom_demo_kb_client1 = array( '', 15 ); // Ravi Menon, Founder.
$wbcom_demo_kb_client2 = array( '', 12 ); // Grace Okafor, Head of People.
$wbcom_demo_kb_design  = array( '', 13 ); // Mateo Silva, Brand Designer.
$wbcom_demo_kb_front   = array( '', 1 );  // Priya Nair, Frontend Engineer.
$wbcom_demo_kb_back    = array( '', 7 );  // Tom Becker, Backend Engineer.
$wbcom_demo_kb_qa      = array( '', 6 );  // Amina Diallo, Researcher.

return array(
	'cast'     => array(
		'manager' => $wbcom_demo_kb_manager,
		'client'  => $wbcom_demo_kb_client1,
	),
	'projects' => array(
		array(
			'title'       => 'Brightpath Patient Portal',
			'color'       => '#0EA5E9',
			'visibility'  => 'members_only',
			'start'       => -60,
			'end'         => 75,
			'desc'        => 'Brightpath Health is replacing its patient portal: online booking, visit history and secure messaging, built for phones first and checked for accessibility.',
			'team'        => array( $wbcom_demo_kb_design, $wbcom_demo_kb_front, $wbcom_demo_kb_back, $wbcom_demo_kb_qa ),
			'clients'     => array( $wbcom_demo_kb_client1 ),
			'milestones'  => array( array( 'Discovery and prototype', -30, 'completed' ), array( 'Build: booking and history', 14, 'in_progress' ), array( 'Accessibility review', 40, 'pending' ), array( 'Launch to patients', 72, 'pending' ) ),
			'tasks'       => array(
				array( 'Interviews with clinic staff', 'done', 'high', -50, $wbcom_demo_kb_manager, 10, 0, array( 'comments' => array( array( $wbcom_demo_kb_manager, 'Five interviews done. Everyone wants fewer phone calls about changing appointments.' ), array( $wbcom_demo_kb_client1, 'Thanks. Our head nurse can join a follow-up if you need a clinical view.' ) ) ) ),
				array(
					'Clickable prototype: booking and rescheduling',
					'done',
					'high',
					-32,
					$wbcom_demo_kb_design,
					22,
					0,
					array(
						'check' => array( array( 'Book an appointment', true ), array( 'Reschedule in two taps', true ), array( 'Cancel with a reason', true ) ),
						'log'   => array( array( $wbcom_demo_kb_design, 300 ), array( $wbcom_demo_kb_design, 240 ) ),
					),
				),
				array(
					'Appointments API and calendar sync',
					'in_progress',
					'high',
					5,
					$wbcom_demo_kb_back,
					28,
					1,
					array(
						'labels'   => array( 'Feature' ),
						'dep'      => array( 1 ),
						'check'    => array( array( 'Availability', true ), array( 'Booking and rescheduling', true ), array( 'Clinic system sync', false ) ),
						'log'      => array( array( $wbcom_demo_kb_back, 270 ), array( $wbcom_demo_kb_back, 180 ) ),
						'comments' => array( array( $wbcom_demo_kb_back, 'The clinic system allows 60 requests a minute, so the sync runs from a queue instead of live calls.' ) ),
					),
				),
				array(
					'Booking screen and appointment list',
					'in_progress',
					'high',
					3,
					$wbcom_demo_kb_front,
					24,
					1,
					array(
						'labels'   => array( 'Feature' ),
						'dep'      => array( 2 ),
						'log'      => array( array( $wbcom_demo_kb_front, 240 ) ),
						'comments' => array( array( $wbcom_demo_kb_front, 'Booking works end to end on staging. Past appointments are next.' ) ),
					),
				),
				array(
					'Date picker is hard to use on small phones',
					'in_progress',
					'urgent',
					1,
					$wbcom_demo_kb_front,
					6,
					1,
					array(
						'labels'   => array( 'Bug', 'Urgent' ),
						'comments' => array( array( $wbcom_demo_kb_qa, 'Reproduced on a 320px screen: the month cannot be changed.' ) ),
					),
				),
				array(
					'Plain-language appointment emails',
					'review',
					'medium',
					2,
					$wbcom_demo_kb_design,
					8,
					1,
					array(
						'labels'   => array( 'Documentation' ),
						'approval' => true,
						'desc'     => 'Confirmation, reminder and change emails, written at a reading age of 12.',
						'comments' => array( array( $wbcom_demo_kb_manager, 'Ready for Brightpath to approve before we wire them in.' ) ),
					),
				),
				array(
					'Secure messaging with the care team',
					'todo',
					'medium',
					22,
					$wbcom_demo_kb_back,
					26,
					1,
					array(
						'labels' => array( 'Feature' ),
						'dep'    => array( 3 ),
					),
				),
				array(
					'Accessibility review of booking and history',
					'todo',
					'high',
					38,
					$wbcom_demo_kb_qa,
					30,
					2,
					array(
						'dep'   => array( 6 ),
						'check' => array( array( 'Keyboard and focus order', false ), array( 'Screen reader pass', false ), array( 'Colour contrast', false ) ),
					),
				),
				array(
					'Internal: hours against the fixed fee',
					'todo',
					'low',
					6,
					$wbcom_demo_kb_manager,
					2,
					-1,
					array(
						'internal' => true,
						'desc'     => 'Team only. Build is about 6% over the estimate; flag it if it passes 10%.',
					),
				),
				array( 'Launch plan and patient announcement', 'todo', 'medium', 68, $wbcom_demo_kb_manager, 12, 3, array( 'dep' => array( 7 ) ) ),
			),
			'discussions' => array(
				array( 'Weekly check-in: Thursday 10:00', true, $wbcom_demo_kb_manager, 'Demo of what shipped, anything blocked, decisions needed from Brightpath. Add your questions here before Thursday.', array( array( $wbcom_demo_kb_client1, 'Added two questions about reminders. See you Thursday.' ) ) ),
			),
		),
		array(
			'title'       => 'Harbor & Co Care Plan',
			'color'       => '#F97316',
			'visibility'  => 'members_only',
			'start'       => -90,
			'end'         => 120,
			'desc'        => 'Monthly care for the Harbor & Co store: updates, backups, uptime and small fixes. This month the focus is the holiday sale.',
			'team'        => array( $wbcom_demo_kb_back, $wbcom_demo_kb_front, $wbcom_demo_kb_qa ),
			'clients'     => array( $wbcom_demo_kb_client2 ),
			'milestones'  => array( array( 'Holiday sale ready', 7, 'in_progress' ), array( 'Monthly report', 12, 'pending' ) ),
			'tasks'       => array(
				array(
					'Monthly plugin and theme updates',
					'done',
					'medium',
					-4,
					$wbcom_demo_kb_back,
					3,
					-1,
					array(
						'recur' => 'monthly',
						'log'   => array( array( $wbcom_demo_kb_back, 140 ) ),
					),
				),
				array( 'Weekly backup restore test', 'in_progress', 'medium', 1, $wbcom_demo_kb_qa, 1, -1, array( 'recur' => 'weekly' ) ),
				array(
					'PayPal button missing on iPhone checkout',
					'done',
					'urgent',
					-2,
					$wbcom_demo_kb_front,
					3,
					-1,
					array(
						'labels'   => array( 'Bug' ),
						'log'      => array( array( $wbcom_demo_kb_front, 120 ) ),
						'comments' => array( array( $wbcom_demo_kb_client2, 'Two customers could not pay with PayPal on their phones this morning.' ), array( $wbcom_demo_kb_front, 'Fixed: a cookie banner script was blocking PayPal. Live now.' ), array( $wbcom_demo_kb_client2, 'Confirmed on my phone, thank you.' ) ),
					),
				),
				array( 'Faster collection pages with page caching', 'review', 'high', 2, $wbcom_demo_kb_back, 4, 0, array( 'labels' => array( 'Enhancement' ) ) ),
				array(
					'Load test checkout for sale traffic',
					'in_progress',
					'urgent',
					4,
					$wbcom_demo_kb_back,
					8,
					0,
					array(
						'dep'   => array( 3 ),
						'check' => array( array( '100 orders an hour', true ), array( '400 orders an hour', false ) ),
					),
				),
				array(
					'Sale banner and discount rules',
					'in_progress',
					'high',
					5,
					$wbcom_demo_kb_front,
					5,
					0,
					array(
						'labels'   => array( 'Feature' ),
						'comments' => array( array( $wbcom_demo_kb_front, 'The banner and 25% rule are on staging. Gift cards and sale items are left out.' ), array( $wbcom_demo_kb_client2, 'Looks great. Please end it Monday at midnight.' ) ),
					),
				),
				array( 'Sales and speed report for the month', 'todo', 'low', 12, $wbcom_demo_kb_manager, 3, 1, array( 'dep' => array( 4 ) ) ),
			),
			'discussions' => array(
				array( 'Sale weekend: who to call', true, $wbcom_demo_kb_manager, 'Changes freeze two days before the sale. Over the weekend the on-call developer answers within 30 minutes.', array( array( $wbcom_demo_kb_client2, 'Thanks, I will share this with our customer service lead.' ) ) ),
			),
		),
		array(
			'title'       => 'Booking Plugin Support',
			'color'       => '#8B5CF6',
			'visibility'  => 'public',
			'start'       => -45,
			'end'         => 60,
			'desc'        => 'Public support board for our booking plugin. Every bug report and feature request is here with its status, who is on it and the release it ships in.',
			'team'        => array( $wbcom_demo_kb_back, $wbcom_demo_kb_front, $wbcom_demo_kb_qa, $wbcom_demo_kb_design ),
			'clients'     => array(),
			'columns'     => array(
				'todo'        => 'New',
				'in_progress' => 'Investigating',
				'review'      => 'Fix ready',
				'done'        => 'Released',
			),
			'milestones'  => array( array( 'Release 2.4.1', -8, 'completed' ), array( 'Release 2.4.2', 6, 'in_progress' ), array( 'Release 2.5.0', 34, 'pending' ) ),
			'tasks'       => array(
				array(
					'Booking emails land in spam on some hosts',
					'done',
					'high',
					-10,
					$wbcom_demo_kb_back,
					6,
					0,
					array(
						'labels'   => array( 'Bug' ),
						'comments' => array( array( $wbcom_demo_kb_qa, 'Fixed in 2.4.1: emails now use your site\'s own sender address.' ) ),
					),
				),
				array(
					'Wrong times after the clocks change',
					'done',
					'urgent',
					-9,
					$wbcom_demo_kb_back,
					8,
					0,
					array(
						'labels'   => array( 'Bug', 'Urgent' ),
						'comments' => array( array( $wbcom_demo_kb_back, 'Times were stored without the time zone. 2.4.1 converts old bookings once on update.' ) ),
					),
				),
				array(
					'Double bookings when two people pay at once',
					'review',
					'urgent',
					4,
					$wbcom_demo_kb_back,
					10,
					1,
					array(
						'labels'   => array( 'Bug' ),
						'log'      => array( array( $wbcom_demo_kb_back, 240 ) ),
						'comments' => array( array( $wbcom_demo_kb_back, 'The slot is now held for 10 minutes while a customer pays.' ), array( $wbcom_demo_kb_qa, 'Tested with 50 checkouts at once, no double bookings. Ships in 2.4.2.' ) ),
					),
				),
				array( 'Form layout breaks in some themes', 'in_progress', 'medium', 6, $wbcom_demo_kb_front, 4, 1, array( 'labels' => array( 'Bug' ) ) ),
				array( 'Time slots hard to pick with a keyboard', 'in_progress', 'high', 6, $wbcom_demo_kb_front, 6, 1, array( 'labels' => array( 'Bug' ) ) ),
				array(
					'Calendar sync stops after a few weeks',
					'todo',
					'high',
					9,
					$wbcom_demo_kb_back,
					8,
					1,
					array(
						'labels'   => array( 'Bug' ),
						'comments' => array( array( $wbcom_demo_kb_qa, 'Thanks for the report. Could you share your PHP version? We cannot reproduce it yet.' ) ),
					),
				),
				array( 'Buffer time between appointments', 'in_progress', 'medium', 20, $wbcom_demo_kb_back, 6, 2, array( 'labels' => array( 'Feature' ) ) ),
				array(
					'Waiting list when a slot is full',
					'todo',
					'medium',
					32,
					$wbcom_demo_kb_design,
					16,
					2,
					array(
						'labels' => array( 'Feature' ),
						'dep'    => array( 6 ),
					),
				),
				array( 'Guide: setting up group classes', 'review', 'low', 3, $wbcom_demo_kb_design, 4, 1, array( 'labels' => array( 'Documentation' ) ) ),
				array(
					'Refund for a customer on the wrong plan',
					'todo',
					'medium',
					1,
					$wbcom_demo_kb_manager,
					1,
					-1,
					array(
						'internal' => true,
						'desc'     => 'Team only. Move them to the right plan and refund the difference.',
					),
				),
			),
			'discussions' => array(),
		),
		array(
			'title'       => 'Studio Estimates and Pricing',
			'color'       => '#0369A1',
			'visibility'  => 'private',
			'start'       => -30,
			'end'         => 45,
			'desc'        => 'Internal. Estimates, change requests and pricing for every client. Not shared with clients.',
			'team'        => array(),
			'clients'     => array(),
			'milestones'  => array(),
			'tasks'       => array(
				array( 'Estimate: Brightpath family accounts', 'in_progress', 'high', 8, $wbcom_demo_kb_manager, 6, -1, array( 'desc' => 'Draft is 96 hours: design 24, back-end 40, front-end 24, testing 8.' ) ),
				array( 'Change request: message attachment scanning', 'review', 'high', 4, $wbcom_demo_kb_manager, 3, -1, array() ),
				array( 'Proposal: Harbor & Co new store', 'done', 'medium', -12, $wbcom_demo_kb_manager, 4, -1, array() ),
				array( 'Renewal: Brightpath support plan for next year', 'todo', 'medium', 28, $wbcom_demo_kb_manager, 6, -1, array( 'dep' => array( 0 ) ) ),
			),
			'discussions' => array(),
		),
	),
	'guide'    => array(
		'name'         => 'Kanbanomy',
		'tab'          => 'Projects',
		'member_label' => 'As the team and as a client',
		'owner_points' => array( 'Every project, task and deadline in one place', 'A client view that shows only what they need', 'Public boards for support and roadmaps' ),
		'roles'        => array(
			'manager' => array( 'Project manager', array( 'Runs every client project', 'Plans the work on the board and the Gantt', 'Asks clients to approve work' ) ),
			'client'  => array( 'Client', array( 'Sees only their own project', 'Approves work waiting on them', 'Follows progress without email threads' ) ),
		),
		'member'       => array(
			'hub'     => array( 'home', 'Open the workspace', 'Your tasks, deadlines and projects across every client.', 'Open the workspace' ),
			'board'   => array( 'list', 'Move work on the board', 'Drag a task from In Progress to Review.', 'Open the board' ),
			'gantt'   => array( 'calendar', 'Plan on the Gantt', 'Milestones, dates and what depends on what.', 'Open the Gantt' ),
			'client'  => array( 'check', 'See the client view', 'The same project as the client sees it, with work waiting for approval.', 'Open as the client' ),
			'support' => array( 'message', 'Visit the public support board', 'Anyone can follow bugs and feature requests, no account needed.', 'Open the support board' ),
		),
		'owner'        => array(
			'projects' => array( 'list', 'Manage projects', 'Every project, its team and its visibility.', 'Open projects' ),
			'forms'    => array( 'plus', 'Take project requests', 'A public form that turns requests into projects.', 'Open forms' ),
			'import'   => array( 'arrow', 'Import from Trello or Asana', 'Bring your boards and tasks across.', 'Open import' ),
			'settings' => array( 'settings', 'Choose what each role sees', 'Features, client access and notifications.', 'Open settings' ),
		),
		'highlights'   => array(
			array( 'list', 'Boards and task lists', 'Statuses, priorities, labels and checklists.' ),
			array( 'calendar', 'Gantt and milestones', 'Dates and dependencies on one timeline.' ),
			array( 'check', 'Client approvals', 'Clients approve work or ask for changes.' ),
			array( 'chart', 'Time tracking', 'Log hours against tasks and budgets.' ),
			array( 'message', 'Discussions', 'Project threads the client can join.' ),
		),
	),
);
