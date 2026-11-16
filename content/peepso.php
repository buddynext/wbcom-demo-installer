<?php
/**
 * PeepSo sample content: the BuddyNext posts, links and replies, the same voice as the other community demos.
 * Its home page text drops groups (a separate PeepSo plugin the demo does not build).
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

$wbcom_demo_peepso         = require __DIR__ . '/buddypress.php';
$wbcom_demo_peepso['home'] = array(
	'title'   => 'A community that knows each other',
	'text'    => 'Members share updates and links, react and reply, and keep up with each other in one stream.',
	'image'   => 'photos/festival-friends.jpg',
	'join'    => 'Join the conversation',
	'closing' => 'Come and say hello. The community is waiting.',
	'people'  => array( 'Meet the members', 'Members share tips, swap links and cheer each other on.' ),
);
return $wbcom_demo_peepso;
