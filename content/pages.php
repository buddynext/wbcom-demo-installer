<?php
/**
 * Pages and blog sample content (built with core blocks plus Wbcom Essential's dynamic blocks). Authors and quote
 * givers are roster indices (content/people.php); photos come from media/photos. Each blog category has a photo (shown
 * by Wbcom Essential's category grid), so no card falls back to a placeholder. Only photos with a CC0 entry in
 * media/CREDITS.json are used here.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'about'      => array(
		'title'  => 'About us',
		'image'  => 'photos/creative-studio-portrait.jpg',
		'intro'  => 'We are a community of designers, developers, writers and curious people who learn faster together. Members share work in progress, ask the questions they would not ask at work, and meet up when they can.',
		'values' => array(
			array( 'Kind, specific feedback', 'Critique the work, not the person. Say what works, what does not, and one thing to try next.' ),
			array( 'Learn in the open', 'Post the messy version. Half-finished ideas get the most useful replies.' ),
			array( 'Show up for each other', 'Answer one question for every one you ask, and welcome the newest member in the room.' ),
		),
		'quote'  => array( 'I joined to get feedback on one prototype. Two years later this is where I learn, hire and find my people.', 2 ),
	),
	'join'       => array(
		'title'  => 'Join the community',
		'image'  => 'photos/tasting-plates.jpg',
		'intro'  => 'Membership is free. Create an account, say hello, and find the spaces that match what you are working on.',
		'steps'  => array(
			array( 'Create your account', 'Pick a username and add a photo, so people know who they are talking to.' ),
			array( 'Introduce yourself', 'Tell us what you are working on and one thing you want to learn.' ),
			array( 'Join three spaces', 'Follow the topics you care about. The feed fills up with them from day one.' ),
		),
		'button' => 'Create your account',
	),
	'faq'        => array(
		'title' => 'Questions and answers',
		'menu'  => 'FAQ',
		'intro' => 'The questions new members ask most. Cannot find yours? Ask in the forums and someone will answer.',
		'items' => array(
			array( 'Is membership free?', 'Yes. Everything in the community is free. Some courses and events are paid, and they say so up front.' ),
			array( 'Who can see what I post?', 'Posts in open spaces are visible to members. Private spaces are visible only to the people in them.' ),
			array( 'How do I find people to learn with?', 'Join a few spaces, then look at who answers questions there. Following them puts their posts in your feed.' ),
			array( 'Can I share my work for feedback?', 'Please do. Post it in the space for that kind of work and say what kind of feedback you want.' ),
			array( 'What if someone is unkind?', 'Report the post with the flag icon. Moderators read every report, usually within a day.' ),
			array( 'Can I run an event or a meetup?', 'Yes. Suggest it in the announcements forum and the team will help you set it up.' ),
		),
	),
	'blog'       => array(
		'title' => 'Blog',
		'intro' => 'Stories from members, guides from the team and news about what is coming next.',
	),
	'categories' => array(
		'news'    => array( 'Community news', 'photos/abstract-light.jpg' ),
		'guides'  => array( 'Guides', 'photos/open-books.jpg' ),
		'stories' => array( 'Member stories', 'photos/matcha-latte.jpg' ),
	),
	'posts'      => array(
		array(
			'author'   => 10,
			'category' => 'news',
			'title'    => 'What we decided at the community call',
			'image'    => 'photos/cafe-friends.jpg',
			'excerpt'  => 'Monthly critique sessions, a mentoring pilot and a quiet hour on Fridays: here is what changes and when.',
			'body'     => array(
				array( '', 'Thanks to everyone who joined the call last week. Forty-two of you came, which is a record, and the chat was full of good ideas. Here is what we agreed to try.' ),
				array( 'Monthly critique sessions', 'On the first Thursday of every month, bring one piece of work you are stuck on. Three rounds of feedback, ten minutes each, then open questions.' ),
				array( 'A mentoring pilot', 'Twenty new members will be paired with someone who has been here a while, for their first month. Sign up in the announcements forum.' ),
				array( 'A quiet hour on Fridays', 'From four to five, no notifications from the community. Use it to make something.' ),
			),
		),
		array(
			'author'   => 0,
			'category' => 'guides',
			'title'    => 'How to ask for feedback that actually helps',
			'image'    => 'photos/wireframe-sketches.jpg',
			'excerpt'  => 'Say what stage the work is at, what you want to know and what you will ignore. Five minutes of setup saves hours.',
			'body'     => array(
				array( '', 'The fastest way to get useless feedback is to post a screenshot and ask what people think. The fastest way to get great feedback is to set the frame.' ),
				array( 'Say what stage it is at', 'A sketch needs different feedback from a finished design. Tell people which one they are looking at.' ),
				array( 'Ask one question', 'Is the main action obvious? Does the copy make sense to someone new? One question gets real answers.' ),
				array( 'Thank people, then decide', 'You do not have to take every suggestion. Thank everyone, then pick the two that matter most.' ),
			),
		),
		array(
			'author'   => 16,
			'category' => 'stories',
			'title'    => 'Six months into my first developer job',
			'image'    => 'photos/neon-grid.jpg',
			'excerpt'  => 'What I wish someone had told me on day one, and the three people here who helped me most.',
			'body'     => array(
				array( '', 'Six months ago I started my first developer job and I was sure everyone could tell I did not know what I was doing. Here is what helped.' ),
				array( 'Asking in public', 'The questions I was too shy to ask at work, I asked here. Every single one had been asked before, and every one got a kind answer.' ),
				array( 'Reading other people\'s code reviews', 'The feedback threads in the web development space taught me more about clean code than any course.' ),
				array( 'Finding a mentor', 'One member answered three of my questions in a row, so I asked if we could talk once a month. We still do.' ),
			),
		),
		array(
			'author'   => 4,
			'category' => 'guides',
			'title'    => 'A beginner\'s guide to our photo walks',
			'image'    => 'photos/vintage-camera.jpg',
			'excerpt'  => 'Any camera works, phones included. Here is how a walk runs and how to share your best frames afterwards.',
			'body'     => array(
				array( '', 'Our photo walks are the easiest way to meet members in person. You do not need a fancy camera or any experience.' ),
				array( 'How a walk runs', 'We meet at a cafe, pick a theme for the day, walk for two hours and finish with coffee and a look at what everyone shot.' ),
				array( 'What to bring', 'A camera or a phone, comfortable shoes and something warm. That is it.' ),
				array( 'Sharing afterwards', 'Post your three best frames in the photography space. Everyone picks a favourite from someone else\'s set.' ),
			),
		),
		array(
			'author'   => 6,
			'category' => 'stories',
			'title'    => 'From a sketchbook to my first client',
			'image'    => 'photos/art-gallery.jpg',
			'excerpt'  => 'I posted one illustration for feedback. A member asked if I took commissions. Here is what happened next.',
			'body'     => array(
				array( '', 'A year ago I drew only for myself. I posted one piece in the illustration space because I was stuck on the colours.' ),
				array( 'The feedback', 'Three people answered within a day. One suggested a warmer palette, one asked about my process, and one asked if I took commissions.' ),
				array( 'The first job', 'It was a set of six drawings for a bakery menu. I priced it too low, and the members who freelance told me so, kindly.' ),
				array( 'What I would tell you', 'Post the work before you think it is ready. The people here are generous, and the right reply can change your year.' ),
			),
		),
		array(
			'author'   => 12,
			'category' => 'news',
			'title'    => 'New spaces for remote workers and founders',
			'image'    => 'photos/sunlit-forest-trail.jpg',
			'excerpt'  => 'Two new spaces open this month, chosen by your votes on the roadmap. Here is what each one is for.',
			'body'     => array(
				array( '', 'You voted, and two new spaces open this month. Both were among the top ideas on the community roadmap.' ),
				array( 'Remote work', 'For everyone who works away from an office: routines, tools, and finding people in your time zone to co-work with.' ),
				array( 'Founders', 'For people building a business: early customers, pricing, hiring the first person and the hard weeks in between.' ),
				array( 'How to join', 'Open the Spaces page, pick the space and choose Join. Say hello in the welcome thread so the hosts know you are there.' ),
			),
		),
	),
);
