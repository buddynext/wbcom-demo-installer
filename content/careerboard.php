<?php
/**
 * WP Career Board sample content: companies and jobs. Owners are roster indices (content/people.php).
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'companies' => array(
		array( 7, 'Roastworks', 'Coffee subscriptions for people who care about the bean', 'Food and drink', '11-50', 'Berlin, DE', 'https://roastworks.example' ),
		array( 12, 'Paperplane Studio', 'A remote-first product studio for small brands', 'Design and software', '11-50', 'Lagos, NG', 'https://paperplane.example' ),
		array( 15, 'Crumb', 'Ordering and stock software for independent bakeries', 'Software', '1-10', 'Kochi, IN', 'https://crumb.example' ),
	),
	'jobs'      => array(
		array( 0, 'Backend Engineer (Go)', 'Engineering', 'Full-time', 'Berlin, DE', 'Mid Level', 65000, 85000, true, "We are rebuilding our subscription engine in Go and need a second backend engineer.\n\nYou will own billing and delivery scheduling, work closely with our two-person ops team, and help us choose what not to build.\n\nYou have shipped a Go service to production, you like boring reliable systems, and you write things down." ),
		array( 0, 'Customer Happiness Lead', 'Customer Success', 'Part-time', 'Remote', 'Mid Level', 30000, 38000, true, 'Help 9,000 coffee lovers get the right beans. You will answer email and chat, spot patterns in what people ask, and tell product what to fix first.' ),
		array( 1, 'Senior Product Designer', 'Design', 'Full-time', 'Remote', 'Senior', 70000, 90000, true, "Design end to end for two client products at a time.\n\nYou run research, sketch quickly, prototype, and stay with the work until it ships. You are comfortable presenting to founders and saying no with reasons." ),
		array( 1, 'Frontend Developer', 'Engineering', 'Full-time', 'Remote', 'Mid Level', 55000, 72000, true, 'Build accessible, fast interfaces with our designers. Modern CSS, a little JavaScript, a lot of care. We test with real keyboards and screen readers.' ),
		array( 1, 'UX Writer (Contract)', 'Design', 'Contract', 'Remote', 'Mid Level', 400, 550, true, 'Three months, two products, every word on screen. Error messages, onboarding, empty states. Day rate, flexible hours.' ),
		array( 2, 'Full-stack Developer', 'Engineering', 'Full-time', 'Kochi, IN', 'Mid Level', 1800000, 2600000, false, 'Join employee number three. Laravel and Vue today, whatever we need tomorrow. You will talk to bakers every week.' ),
		array( 2, 'Mobile Developer (Android)', 'Engineering', 'Contract', 'Remote', 'Senior', 30000, 45000, true, 'Our ordering app has to work in kitchens with flour on the screen and no wifi. Offline-first experience is a must.' ),
		array( 2, 'Junior Support Engineer', 'Customer Success', 'Full-time', 'Kochi, IN', 'Entry Level', 600000, 800000, false, 'Learn our product inside out, fix small bugs, and help bakeries get set up. Great first role, we will teach you a lot.' ),
	),
);
