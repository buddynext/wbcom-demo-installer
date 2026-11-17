<?php
/**
 * Product Roadmap sample content: roadmap items and the "Try the demo" guide.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'items' => array(
		array( 'Monthly critique sessions', 'completed', 'Features', 'A recurring space and event for sharing work in progress and getting kind, specific feedback.' ),
		array( 'Mentoring programme for new members', 'in-progress', 'Features', 'Pair every new member with someone who has been here a while, for their first month.' ),
		array( 'Dark mode across the community', 'completed', 'Improvements', 'Every page follows the reader\'s light or dark setting.' ),
		array( 'Local meetups in more cities', 'planned', 'Features', 'Start small monthly meetups in Lisbon, Berlin and Lagos, with a simple host kit.' ),
		array( 'Better search for old discussions', 'planned', 'Improvements', 'Find answers from past threads by topic, person and date.' ),
		array( 'Accessibility review swap', 'under-review', 'Features', 'Members post a page and two others review it for accessibility, then swap.' ),
		array( 'Faster image uploads on mobile', 'in-progress', 'Improvements', 'Smaller uploads and progress you can see on slow connections.' ),
		array( 'Weekly digest email', 'planned', 'Features', 'One calm email a week with the best discussions you missed.' ),
	),
	'guide' => array(
		'name'         => 'Product Roadmap',
		'tab'          => 'Roadmap',
		'member_label' => 'As a member',
		'owner_points' => array( 'Statuses and categories for every item', 'Member suggestions to approve', 'A changelog for what shipped' ),
		'roles'        => array(
			'member' => array( 'Member', array( 'Votes on the items they want', 'Comments on any item', 'Suggestions for new ideas' ) ),
		),
		'member'       => array(
			'roadmap' => array( 'list', 'See the roadmap', 'Planned, in progress and done, in one board.', 'Open the roadmap' ),
			'vote'    => array( 'star', 'Vote for an idea', 'Upvote the items you want built first.', 'Vote on the roadmap' ),
			'suggest' => array( 'plus', 'Suggest an idea', 'Send a new idea for the team to review.', 'Suggest an idea' ),
		),
		'owner'        => array(
			'items'       => array( 'list', 'Manage items', 'Edit, move or close any roadmap item.', 'Open items' ),
			'suggestions' => array( 'check', 'Review suggestions', 'Approve or reject ideas from members.', 'Open suggestions' ),
			'statuses'    => array( 'flag', 'Set the statuses', 'Name the columns your roadmap shows.', 'Open statuses' ),
			'settings'    => array( 'settings', 'Tune voting', 'Who can vote, comment and suggest.', 'Open settings' ),
		),
		'highlights'   => array(
			array( 'list', 'Roadmap board', 'Items by status, with progress and priority.' ),
			array( 'star', 'Member votes', 'The most wanted items rise to the top.' ),
			array( 'message', 'Comments', 'Members discuss each item.' ),
			array( 'plus', 'Suggestions', 'Members send ideas for you to review.' ),
			array( 'tick', 'Changelog', 'Tell members what shipped.' ),
		),
	),
);
