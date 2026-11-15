<?php
/**
 * BuddyPress / BuddyBoss Platform sample content. Groups, posts, photos, links and comments come from the BuddyNext
 * set (one source, the same voice on every community demo); only the guide text is BuddyPress's own.
 *
 * Groups:   BuddyNext spaces [ slug, image, name, type (open|private), owner index, members, desc ].
 * posts:    [ author index, group slug or '', text with {@N} mentions ].
 * photos:   [ author index, group slug or '', text, image under media/ ].
 * links:    [ author index, group slug or '', text, url ].
 * comments: short replies.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

$wbcom_demo_bn = require __DIR__ . '/buddynext.php';

return array(
	'groups'   => $wbcom_demo_bn['spaces'],
	'posts'    => $wbcom_demo_bn['posts'],
	'photos'   => $wbcom_demo_bn['photos'],
	'links'    => $wbcom_demo_bn['links'],
	'comments' => $wbcom_demo_bn['comments'],
	'guide'    => array(
		'tab'          => 'Community',
		'member_label' => 'As a member',
		'owner_points' => array( 'Members, groups and activity', 'Profile fields and privacy', 'Which community features are on' ),
		'roles'        => array(
			'member' => array( 'Member', array( 'Updates, photos and comments of their own', 'Friends and groups already joined', 'Mentions and notifications' ) ),
		),
		'member'       => array(
			'activity'      => array( 'home', 'Post to the activity feed', 'Share an update, then like and reply to others.', 'Open the activity' ),
			'groups'        => array( 'message', 'Join a group', 'Public and private groups with their own feeds.', 'Open groups' ),
			'members'       => array( 'users', 'Meet the members', 'Profiles, friends and private messages.', 'Open members' ),
			'notifications' => array( 'bell', 'See your notifications', 'Mentions, replies and friend requests.', 'Open notifications' ),
		),
		'owner'        => array(
			'components' => array( 'settings', 'Community features', 'Turn activity, groups, messages and more on or off.', 'Open components' ),
			'groups'     => array( 'users', 'Manage groups', 'Every group, its members and settings.', 'Open groups' ),
			'activity'   => array( 'shield', 'Moderate activity', 'Edit, mark as spam or delete any update.', 'Open activity' ),
		),
		'highlights'   => array(
			array( 'home', 'Activity feed', 'Updates, photos, comments and favourites from across the site.' ),
			array( 'users', 'Profiles, friends and groups', 'Member profiles, a friend network and groups.' ),
		),
	),
);
