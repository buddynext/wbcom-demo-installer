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
			'category'   => 'design',
			'instructor' => 0,
			'difficulty' => 'intermediate',
			'price'      => 0,
			'desc'       => 'Build a small design system your whole team will actually use: tokens, components and the habits that keep it alive.',
			'sections'   => array(
				array( 'Foundations', array(
					array( 'Why design systems fail', 'Most design systems die quietly. Not because the components are bad, but because nobody owns them and nobody knows when to use them. In this lesson we look at three systems that stalled and the one habit that would have saved each.' ),
					array( 'Tokens before components', 'Colours, spacing, type and radius come first. When every component reads from the same small set of tokens, a rebrand becomes a one-file change. We will set up eight colour tokens and a four-step spacing scale.' ),
					array( 'Naming things so people find them', 'Name tokens by role, not by value: text-muted, not grey-500. You will rename a real palette and see how much easier it is to reason about.' ),
				) ),
				array( 'Components that last', array(
					array( 'The button ladder', 'One primary, one secondary, one quiet button, three sizes. That is the whole ladder. Anything else is a variant you probably do not need.' ),
					array( 'Documenting with real examples', 'A component page needs one example of the right use, one of the wrong use and a sentence on why. Screenshots of real screens beat abstract demos.' ),
					array( 'Keeping it alive', 'A monthly review, a changelog people read and a clear way to ask for a new component. Ownership is a calendar invite, not a title.' ),
				) ),
			),
		),
		array(
			'title'      => 'Accessible Web Pages in a Weekend',
			'category'   => 'development',
			'instructor' => 1,
			'difficulty' => 'beginner',
			'price'      => 29,
			'desc'       => 'The ten fixes that solve most accessibility problems, with a screen reader walkthrough for each.',
			'sections'   => array(
				array( 'The basics', array(
					array( 'Headings are a map', 'Screen reader users jump between headings to understand a page. One h1, then h2 for each section, never skip a level. We will fix a real landing page in ten minutes.' ),
					array( 'Labels on every input', 'A placeholder is not a label. Every input needs a visible label linked with for and id. We will rebuild a sign-up form the right way.' ),
					array( 'Colour contrast that passes', 'Body text needs 4.5 to 1 against its background. We will check a palette with a contrast tool and fix the two colours that fail.' ),
				) ),
				array( 'Keyboard and focus', array(
					array( 'Everything reachable by keyboard', 'Unplug your mouse and try to buy something on your own site. Whatever you cannot reach, a keyboard user cannot either.' ),
					array( 'Focus you can see', 'Never remove the focus outline without a replacement. A two pixel ring in your accent colour is enough.' ),
				) ),
			),
		),
		array(
			'title'      => 'Running a Community People Come Back To',
			'category'   => 'community',
			'instructor' => 10,
			'difficulty' => 'beginner',
			'price'      => 0,
			'desc'       => 'Rituals, moderation and the first hundred members. What we learned growing a community from zero.',
			'sections'   => array(
				array( 'The first hundred members', array(
					array( 'Invite people, not audiences', 'Your first members should be people you could message by name. Write ten personal invites before you write one announcement.' ),
					array( 'A reason to come back each week', 'One weekly ritual beats ten features. A Friday show and tell, a Monday goals thread, a monthly call.' ),
					array( 'Saying hello properly', 'Reply to every introduction in the first month, by name, with a question. It is the single biggest predictor of a second post.' ),
				) ),
				array( 'Keeping it healthy', array(
					array( 'Guidelines in plain words', 'Five short rules everyone can remember beat a page of legal text. Write them with your first members.' ),
					array( 'Moderating without drama', 'Act early, act privately, explain once. Most problems are misunderstandings that a direct message fixes.' ),
				) ),
			),
		),
		array(
			'title'      => 'Data Visualisation for Busy People',
			'category'   => 'development',
			'instructor' => 4,
			'difficulty' => 'intermediate',
			'price'      => 49,
			'desc'       => 'Choose the right chart, cut the clutter and tell one clear story with your numbers.',
			'sections'   => array(
				array( 'Pick the chart', array(
					array( 'One question per chart', 'Before you draw anything, write the question the chart answers in one sentence. If you need two sentences, you need two charts.' ),
					array( 'Bars, lines and almost nothing else', 'Bars compare, lines show change over time. Pie charts and 3D effects rarely help. We will redraw five common charts.' ),
					array( 'Colour with a purpose', 'Grey for context, one colour for the thing that matters. Your reader should see the point before they read the labels.' ),
				) ),
			),
		),
		array(
			'title'      => 'Calm Focus: Ten Minutes a Day',
			'category'   => 'wellbeing',
			'instructor' => 14,
			'difficulty' => 'beginner',
			'price'      => 0,
			'desc'       => 'Short daily practices for focus and calm, designed for people who think they do not have time.',
			'sections'   => array(
				array( 'Start small', array(
					array( 'The two minute start', 'Sit, breathe out longer than you breathe in, and count ten breaths. That is the whole practice for the first week.' ),
					array( 'Same time, same place', 'Attach the practice to something you already do: after the first coffee, before opening email.' ),
					array( 'When your mind wanders', 'It will. Noticing that it wandered is the practice, not a failure of it.' ),
				) ),
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
);
