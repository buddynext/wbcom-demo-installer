<?php
/**
 * WP Career Board sample content: companies and jobs. Owners are roster indices (content/people.php). Company industry is one of WP Career Board's registered slugs (core/class-industries.php).
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'companies' => array(
		array( 7, 'Roastworks', 'Coffee subscriptions for people who care about the bean', 'retail', '11-50', 'Berlin, DE', 'https://roastworks.example' ),
		array( 12, 'Paperplane Studio', 'A remote-first product studio for small brands', 'design', '11-50', 'Lagos, NG', 'https://paperplane.example' ),
		array( 15, 'Crumb', 'Ordering and stock software for independent bakeries', 'technology', '1-10', 'Kochi, IN', 'https://crumb.example' ),
	),
	'jobs'      => array(
		array( 0, 'Backend Engineer (Go)', 'Engineering', 'Full-time', 'Berlin, DE', 'Mid Level', 65000, 85000, true, "We are rebuilding our subscription engine in Go and need a second backend engineer.\n\nYou will own billing and delivery scheduling, work closely with our two-person ops team, and help us choose what not to build.\n\nYou have shipped a Go service to production, you like boring reliable systems, and you write things down." ),
		array( 0, 'Customer Happiness Lead', 'Customer Success', 'Part-time', 'Remote', 'Mid Level', 30000, 38000, true, 'Help 9,000 coffee lovers get the right beans. You will answer email and chat, spot patterns in what people ask, and tell product what to fix first.' ),
		array( 1, 'Senior Product Designer', 'Design', 'Full-time', 'Remote', 'Senior', 70000, 90000, true, "Design end to end for two client products at a time.\n\nYou run research, sketch quickly, prototype, and stay with the work until it ships. You are comfortable presenting to founders and saying no with reasons." ),
		array( 1, 'Frontend Developer', 'Engineering', 'Full-time', 'Remote', 'Mid Level', 55000, 72000, true, 'Build accessible, fast interfaces with our designers. Modern CSS, a little JavaScript, a lot of care. We test with real keyboards and screen readers.' ),
		array( 1, 'UX Writer (Contract)', 'Design', 'Contract', 'Remote', 'Mid Level', 50, 70, true, 'Three months, two products, every word on screen. Error messages, onboarding, empty states. Hourly rate, flexible hours.' ),
		array( 2, 'Full-stack Developer', 'Engineering', 'Full-time', 'Kochi, IN', 'Mid Level', 21000, 31000, false, 'Join employee number three. Laravel and Vue today, whatever we need tomorrow. You will talk to bakers every week.' ),
		array( 2, 'Mobile Developer (Android)', 'Engineering', 'Contract', 'Remote', 'Senior', 30000, 45000, true, 'Our ordering app has to work in kitchens with flour on the screen and no wifi. Offline-first experience is a must.' ),
		array( 2, 'Junior Support Engineer', 'Customer Success', 'Full-time', 'Kochi, IN', 'Entry Level', 7000, 9500, false, 'Learn our product inside out, fix small bugs, and help bakeries get set up. Great first role, we will teach you a lot.' ),
	),
	'home'      => array(
		'title'    => 'The smarter way to hire and get hired',
		'text'     => 'Search open roles from companies that invest in their people. One profile, quick applications and a clear view of every step.',
		'image'    => 'photos/tech-workspace.jpg',
		'browse'   => 'Browse jobs',
		'post'     => 'Post a job',
		'closing'  => 'Hiring? Post your first job in two minutes.',
		'search'   => array( 'Find your next role', 'Search by title, category, location or job type.' ),
		'featured' => 'Featured opportunities',
	),
	// "Try the demo" page: text only; the provider adds people and links (Provider::guide()).
	'guide'     => array(
		'name'       => 'WP Career Board',
		'tab'        => 'Jobs',
		'roles'      => array(
			'candidate' => array( 'Candidate', array( 'Applications already sent', 'A resume ready to share', 'Job alerts and saved jobs' ) ),
			'employer'  => array( 'Employer', array( 'Jobs live on the board', 'Applicants to review', 'A company page' ) ),
		),
		'member'     => array(
			'jobs'  => array( 'search', 'Find a job', 'Filter jobs by type, location and salary.', 'Browse jobs' ),
			'apply' => array( 'check', 'Track applications', 'See every job you applied for and where it stands.', 'Open my applications' ),
			'post'  => array( 'plus', 'Post a job', 'As the employer, publish a job and review applicants.', 'Post a job' ),
		),
		'owner'      => array(
			'jobs'         => array( 'list', 'Manage jobs', 'Approve, edit or feature any job.', 'Open jobs' ),
			'applications' => array( 'users', 'See applications', 'Every application across every employer.', 'Open applications' ),
		),
		'highlights' => array(
			array( 'package', 'Job board', 'Jobs, companies and applications in one place.' ),
			array( 'users', 'Both sides of the hire', 'Dashboards for candidates and employers.' ),
		),
	),
);
