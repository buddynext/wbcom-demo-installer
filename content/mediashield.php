<?php
/**
 * MediaShield sample content: videos (YouTube ids of Blender open movies).
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'videos' => array(
		array( 'aqz-KE-bpKQ', 'Big Buck Bunny', 'A short open movie by the Blender Foundation, used here to show a protected video with watch tracking.', 635 ),
		array( 'eRsGyueVLvQ', 'Sintel', 'An open movie about a girl searching for her dragon. Try the protection levels on a longer video.', 888 ),
		array( 'R6MlUcmOul8', 'Tears of Steel', 'A live action and visual effects open movie, good for testing chapters and milestones.', 734 ),
		array( 'WhWc3b3KhnY', 'Spring', 'A short animated open movie about a shepherd girl and her dog.', 464 ),
	),
	// "Try the demo" page: text only; the provider adds the links (Provider::guide()).
	'guide'  => array(
		'name'       => 'MediaShield',
		'member'     => array(
			'watch' => array( 'shield', 'Watch a protected lesson', 'A free lesson streamed through MediaShield.', 'Play the lesson' ),
		),
		'owner'      => array(
			'protect' => array( 'shield', 'Protect your videos', 'Watermarks, domain lock and playback analytics.', 'Open MediaShield' ),
		),
		'highlights' => array(
			array( 'shield', 'Protected video', 'MediaShield keeps lesson videos on your site and away from downloaders.' ),
		),
	),
);
