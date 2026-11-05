<?php
/**
 * Jetonomy sample content: forum categories, spaces, discussions and replies.
 * Authors are roster indices (content/people.php).
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'categories' => array(
		array(
			'name'   => 'Start Here',
			'slug'   => 'start-here',
			'desc'   => 'Say hello, read the guidelines and find your way around.',
			'spaces' => array(
				array(
					'slug'  => 'announcements',
					'title' => 'Announcements',
					'type'  => 'feed',
					'icon'  => 'megaphone',
					'desc'  => 'News from the community team.',
				),
				array(
					'slug'  => 'introduce-yourself',
					'title' => 'Introduce Yourself',
					'type'  => 'forum',
					'icon'  => 'hand',
					'desc'  => 'Tell us who you are and what brought you here.',
				),
			),
		),
		array(
			'name'   => 'Learn and Build',
			'slug'   => 'learn-and-build',
			'desc'   => 'Ask questions, share work and help each other improve.',
			'spaces' => array(
				array(
					'slug'  => 'help-and-questions',
					'title' => 'Help and Questions',
					'type'  => 'qa',
					'icon'  => 'circle-help',
					'desc'  => 'Stuck on something? Ask here and mark the answer that helped.',
				),
				array(
					'slug'  => 'show-and-tell',
					'title' => 'Show and Tell',
					'type'  => 'forum',
					'icon'  => 'sparkles',
					'desc'  => 'Share what you made this week, big or small.',
				),
				array(
					'slug'  => 'career-corner',
					'title' => 'Career Corner',
					'type'  => 'forum',
					'icon'  => 'briefcase',
					'desc'  => 'Job hunting, interviews, pay and changing careers.',
				),
			),
		),
		array(
			'name'   => 'Shape the Community',
			'slug'   => 'shape-the-community',
			'desc'   => 'Ideas and feedback that decide what we do next.',
			'spaces' => array(
				array(
					'slug'  => 'ideas',
					'title' => 'Ideas',
					'type'  => 'ideas',
					'icon'  => 'lightbulb',
					'desc'  => 'Suggest an idea and vote for the ones you want.',
				),
			),
		),
	),
	'tags'       => array( 'Getting started', 'Design', 'CSS', 'Accessibility', 'Career', 'Remote work', 'Feedback', 'Events', 'Tools' ),
	// [ space slug, author index, title, body, reply count, accepted? ].
	'topics'     => array(
		array( 'announcements', 10, 'Welcome to the community forums', 'This is the place for longer conversations than the feed allows. Start a discussion, ask a question, or suggest an idea. Please read the guidelines in the pinned post and be kind.', 6, false ),
		array( 'announcements', 2, 'Community call recap: what we decided', 'Thanks to everyone who joined. We agreed on monthly critique sessions, a mentoring pilot for new members and a quiet hour each Friday. Notes are below, add anything we missed.', 4, false ),
		array( 'introduce-yourself', 16, 'Hi from Lyon, junior developer here', 'Six months into my first developer job. I mostly write JavaScript and I am trying to get better at testing. Happy to be here!', 7, false ),
		array( 'introduce-yourself', 13, 'Brand designer from Porto', 'I design logos and packaging, mostly for small food brands. Looking for people to swap feedback with.', 5, false ),
		array( 'introduce-yourself', 19, 'Mobile developer in Accra', 'I build Android and iOS apps that have to work on slow networks. Ask me anything about offline-first.', 4, false ),
		array( 'help-and-questions', 16, 'How do you structure CSS in a large project?', 'Our stylesheet is 6,000 lines and every change breaks something. Do you use layers, BEM, utility classes? What actually worked for you?', 6, true ),
		array( 'help-and-questions', 5, 'Best way to learn pixel art animation?', 'I can draw a static sprite but walking cycles look stiff. Any courses or exercises you would recommend?', 4, true ),
		array( 'help-and-questions', 18, 'How do you say no to feature requests politely?', 'We get dozens of requests a week from good customers. I want to say no without sounding like we do not care.', 5, true ),
		array( 'help-and-questions', 3, 'Which font licence do I need for a client logo?', 'The client wants to use a typeface I licensed for my portfolio in their logo. Is a desktop licence enough?', 3, true ),
		array( 'help-and-questions', 11, 'Soldering iron recommendations for beginners?', 'Building my first synth kit. Is a cheap iron fine or will it ruin the board?', 4, false ),
		array( 'show-and-tell', 0, 'Redesigned our onboarding: before and after', 'We cut onboarding from seven screens to three and moved account setup to after the first win. Completion went from 41% to 68%. Screens inside.', 8, false ),
		array( 'show-and-tell', 4, 'I charted every run from this year', 'A small data viz project: elevation, pace and mood for 140 runs. The mood axis is very scientific.', 5, false ),
		array( 'show-and-tell', 3, 'Finished my first typeface', 'Two years of evenings. It is a soft serif with 380 glyphs. Specimen and the story behind it inside.', 6, false ),
		array( 'show-and-tell', 9, 'Street photography from one month in Seoul', 'Thirty frames, all shot with a 35mm lens and no editing beyond crop. Which one is your favourite?', 4, false ),
		array( 'career-corner', 12, 'What we look for when hiring remote developers', 'I hire for a remote-first studio. Here is what actually makes a candidate stand out, and what does not matter as much as people think.', 7, false ),
		array( 'career-corner', 8, 'Moving from copywriting to UX writing', 'I made the switch two years ago. Here is the portfolio that got me hired and the mistakes I made along the way.', 5, false ),
		array( 'career-corner', 16, 'How did you negotiate your first raise?', 'My review is next month. I have no idea how to bring up money. Any scripts that worked for you?', 6, false ),
		array( 'ideas', 17, 'Monthly in-person meetups in more cities', 'We have enough members in Lisbon, Berlin and Lagos to start small monthly meetups. I can help with the format.', 3, false ),
		array( 'ideas', 14, 'A mentoring programme for new members', 'Pair every new member with someone who has been here a while, for their first month. Light touch, one call a week.', 4, false ),
		array( 'ideas', 1, 'Accessibility review swap', 'Members post a page and two others review it for accessibility, then swap. Learning and helping at once.', 2, false ),
	),
	'replies'    => array(
		'Thanks for writing this up, really useful.',
		'We had the same problem last year. What fixed it for us was smaller steps and a clear owner.',
		'Welcome! You will like it here.',
		'I would love to see the before and after side by side.',
		'Great question. I asked the same thing when I started.',
		'Plus one to this. Count me in.',
		'Have you tried breaking it into smaller pieces first?',
		'This is brilliant work. How long did it take?',
		'I disagree a little: in my experience it depends on the team size.',
		'Saving this thread for later.',
		'Happy to help if you want to pair on it.',
		'The second option worked better for us.',
	),
	'answers'    => array(
		'Use cascade layers for the big buckets (reset, base, components, utilities) and keep component styles next to the component. It took us a sprint to migrate and the breakages stopped.',
		'Do the classic exercise: animate a bouncing ball in four frames, then eight. Once timing clicks, walking cycles get much easier.',
		'Thank them, say what you are focused on instead and why, and tell them where to follow progress. People mostly want to be heard.',
		'Usually no. Logos need a specific logo or extended licence. Check the foundry terms or ask them directly, most are friendly about it.',
	),
);
