<?php
/**
 * BuddyPress and BuddyBoss Platform: member photos, groups, friendships and a mixed, dated activity feed, plus dates
 * for the activity other products post while their content is built.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Clock;
use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;
use WbcomDemo\Core\State;

defined( 'ABSPATH' ) || exit;

/**
 * One provider for both platforms: they share the bp_* API.
 */
final class BuddyPress extends Provider {

	private const UPDATES_PER_STEP = 12;

	/** New things of one type (events, posts, groups) shown site-wide per day; the rest stay on profiles. */
	private const SAME_TYPE_PER_DAY = 3;

	/** Repeat reactions (RSVPs, enrolments, joins, friendships) shown site-wide: one in this many. */
	private const REPEAT_ONE_IN = 4;

	/** Social side effects that are repeats however they are named. */
	private const REPEAT_TYPES = array( 'friendship_created', 'joined_group', 'new_avatar', 'new_member' );

	/** Award announcements (points, badges) shown site-wide: one in this many. */
	private const AWARD_ONE_IN = 30;

	/** Components whose items announce rewards for what members did. */
	private const AWARD_COMPONENTS = array( 'gamipress', 'wb-gamification', 'mycred', 'badgeos' );

	/**
	 * True while this provider creates activity, so the bridge listener leaves our own items alone.
	 *
	 * @var bool
	 */
	private static bool $own = false;

	/**
	 * Whether the listeners are on: boot() runs before every step, and a WP-CLI build runs every step in one process.
	 *
	 * @var bool
	 */
	private static bool $listening = false;

	/**
	 * Bridged items seen this request per "type|day", for thinning.
	 *
	 * @var array<string,int>
	 */
	private static array $seen = array();

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'buddypress';
	}

	/**
	 * Whether this is BuddyBoss Platform rather than BuddyPress.
	 */
	private static function boss(): bool {
		return defined( 'BP_PLATFORM_VERSION' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return self::boss() ? 'BuddyBoss Platform' : 'BuddyPress';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return function_exists( 'bp_activity_add' ) && function_exists( 'bp_is_active' ) && bp_is_active( 'activity' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		if ( self::boss() ) {
			return (string) BP_PLATFORM_VERSION;
		}
		return function_exists( 'bp_get_version' ) ? (string) bp_get_version() : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return self::boss() ? '2.0.0' : '12.0.0';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'users';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 12; // After the people, before the products whose activity it dates.
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return __( 'Member photos, groups, friendships and a feed of updates, photos, links and comments', 'wbcom-demo-installer' );
	}

	/**
	 * Groups this site can have (the groups component may be off).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function groups(): array {
		return bp_is_active( 'groups' ) ? (array) $this->content()['groups'] : array();
	}

	/**
	 * Feed items in posting order: posts, then photo and link posts.
	 *
	 * @return array<int,array<int,mixed>>
	 */
	private function updates(): array {
		$c   = $this->content();
		$out = array();
		foreach ( (array) $c['posts'] as $row ) {
			$out[] = array( $row[0], $row[1], $row[2], '', '' );
		}
		foreach ( (array) $c['photos'] as $row ) {
			$out[] = array( $row[0], $row[1], $row[2], $row[3], '' );
		}
		foreach ( (array) $c['links'] as $row ) {
			$out[] = array( $row[0], $row[1], $row[2], '', $row[3] );
		}
		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$plan = array(
			'avatar'   => array( count( People::roster() ), 'setup', __( 'member photos', 'wbcom-demo-installer' ) ),
			'activity' => array( count( $this->updates() ) + 20, 'sample', __( 'feed updates and comments', 'wbcom-demo-installer' ) ),
		);
		if ( $this->groups() ) {
			$plan['group'] = array( count( $this->groups() ), 'sample', __( 'groups', 'wbcom-demo-installer' ) );
		}
		if ( bp_is_active( 'friends' ) ) {
			$plan['friendship'] = array( count( People::roster() ) * 3, 'sample', __( 'friendships', 'wbcom-demo-installer' ) );
		}
		return $plan;
	}

	/**
	 * Ordered step keys.
	 *
	 * @param Context $ctx Build context.
	 * @return string[]
	 */
	public function steps( Context $ctx ): array {
		$steps = array( 'profiles', 'groups', 'friends' );
		for ( $i = 0, $n = count( $this->updates() ); $i < $n; $i += self::UPDATES_PER_STEP ) {
			$steps[] = 'updates:' . $i;
		}
		return array_merge( $steps, array( 'comments', 'tidy' ) );
	}

	/**
	 * Give activity other products post during a build the date of the thing that caused it, record it, and keep a
	 * product that posts many items at once (one per RSVP or enrolment) from flooding the site-wide feed.
	 *
	 * @param Context $ctx Context.
	 */
	public function boot( Context $ctx ): void {
		if ( self::$listening || State::BUILDING !== $ctx->state->get( 'status' ) || ! $this->available() || ! $ctx->includes( $this->id() ) ) {
			return;
		}
		self::$listening = true;
		add_action(
			'bp_activity_before_save',
			static function ( $activity ) use ( $ctx ): void {
				if ( Clock::now() ) {
					$activity->date_recorded = Clock::now();
				} elseif ( ! self::$own && empty( $activity->id ) ) {
					// No date from the product: a stable place on the timeline, so items do not share one minute.
					$seed                    = abs( crc32( $activity->type . '|' . $activity->item_id . '|' . $activity->secondary_item_id . '|' . $activity->user_id ) );
					$activity->date_recorded = $ctx->date( $seed % 500, 500, 45 );
				}
				// Thin repeats of any automatic item (another product's, or "joined a group" / "became friends"), never
				// members' own updates and replies.
				if ( empty( $activity->id ) && self::thin( (string) $activity->component, (string) $activity->type, (string) $activity->date_recorded ) ) {
					$activity->hide_sitewide = 1;
				}
			}
		);
		add_action(
			'bp_activity_after_save',
			function ( $activity ) use ( $ctx ): void {
				if ( ! self::$own && ! empty( $activity->id ) && ! Ledger::has( $this->id(), 'activity', (int) $activity->id ) ) {
					$ctx->record( $this->id(), 'activity', (int) $activity->id, 'sample', array( 'bridged' => 1 ), Clock::parent() );
				}
			}
		);
	}

	/**
	 * Whether an item should stay off the site-wide feed (it still shows on profiles and in groups), so members' own
	 * posts lead: their updates and replies always show; something new being created (an event, a post, a group)
	 * shows up to SAME_TYPE_PER_DAY a day; repeat reactions one in REPEAT_ONE_IN; award announcements one in
	 * AWARD_ONE_IN.
	 *
	 * @param string $component Component.
	 * @param string $type      Activity type.
	 * @param string $date      Date recorded.
	 */
	private static function thin( string $component, string $type, string $date ): bool {
		if ( in_array( $component, array( 'activity', 'groups' ), true ) && in_array( $type, array( 'activity_update', 'activity_comment' ), true ) ) {
			return false;
		}
		if ( in_array( $component, self::AWARD_COMPONENTS, true ) ) {
			self::$seen[ $component ] = ( self::$seen[ $component ] ?? 0 ) + 1;
			return 1 !== self::$seen[ $component ] % self::AWARD_ONE_IN;
		}
		if ( ! in_array( $type, self::REPEAT_TYPES, true ) && preg_match( '/created|^new_|publish/', $type ) ) {
			$key                = $type . '|' . substr( $date, 0, 10 );
			self::$seen[ $key ] = ( self::$seen[ $key ] ?? 0 ) + 1;
			return self::$seen[ $key ] > self::SAME_TYPE_PER_DAY;
		}
		$key                = $component . '|' . $type;
		self::$seen[ $key ] = ( self::$seen[ $key ] ?? 0 ) + 1;
		return 1 !== self::$seen[ $key ] % self::REPEAT_ONE_IN;
	}

	/**
	 * Run one step.
	 *
	 * @param string  $step Step key.
	 * @param Context $ctx  Build context.
	 */
	public function run( string $step, Context $ctx ): void {
		list( $name, $offset ) = array_pad( explode( ':', $step, 2 ), 2, '0' );
		self::$own             = true;
		try {
			switch ( $name ) {
				case 'profiles':
					$this->profiles( $ctx );
					break;
				case 'groups':
					$this->make_groups( $ctx );
					break;
				case 'friends':
					$this->friends( $ctx );
					break;
				case 'updates':
					$this->post_updates( $ctx, (int) $offset );
					break;
				case 'comments':
					$this->comments( $ctx );
					break;
				case 'tidy':
					self::$own = false;
					$this->tidy( $ctx );
					break;
			}
		} finally {
			self::$own = false;
			Clock::reset();
		}
	}

	/**
	 * Write one image as a BuddyPress avatar (full and thumb) for a member or group.
	 *
	 * @param string $file    Source image path.
	 * @param int    $item_id User or group id.
	 * @param string $kind    'user' or 'group'.
	 */
	private static function write_avatar( string $file, int $item_id, string $kind ): bool {
		if ( ! is_readable( $file ) || ! function_exists( 'bp_core_avatar_upload_path' ) ) {
			return false;
		}
		$dir = trailingslashit( bp_core_avatar_upload_path() ) . ( 'group' === $kind ? 'group-avatars' : 'avatars' ) . '/' . $item_id . '/';
		wp_mkdir_p( $dir );
		$hash = md5( $kind . $item_id . wp_rand() );
		foreach ( array(
			'bpfull'  => max( 300, (int) bp_core_avatar_full_width() ),
			'bpthumb' => max( 100, (int) bp_core_avatar_thumb_width() ),
		) as $suffix => $size ) {
			$editor = wp_get_image_editor( $file );
			if ( is_wp_error( $editor ) ) {
				return false;
			}
			$editor->resize( $size, $size, true );
			$saved = $editor->save( $dir . $hash . '-' . $suffix . '.jpg' );
			if ( is_wp_error( $saved ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Display names in the profile name field and the cast photos as BuddyPress avatars.
	 *
	 * @param Context $ctx Context.
	 */
	private function profiles( Context $ctx ): void {
		foreach ( $ctx->people() as $user_id ) {
			$person = $ctx->profile( $user_id );
			$user   = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}
			if ( function_exists( 'xprofile_set_field_data' ) && function_exists( 'bp_xprofile_fullname_field_id' ) ) {
				xprofile_set_field_data( bp_xprofile_fullname_field_id(), $user_id, $user->display_name );
			}
			if ( ! empty( $person['avatar'] ) && self::write_avatar( $ctx->media( (string) $person['avatar'] ), $user_id, 'user' ) ) {
				$ctx->record( $this->id(), 'avatar', $user_id, 'setup', array( 'object' => 'user' ) );
			}
		}
	}

	/**
	 * Groups from the community spaces: owner, members, photo, created on the timeline.
	 *
	 * @param Context $ctx Context.
	 */
	private function make_groups( Context $ctx ): void {
		$map    = array();
		$groups = $this->groups();
		foreach ( $groups as $i => $g ) {
			$owner = $ctx->person( (int) $g['owner'] );
			if ( ! $owner ) {
				continue;
			}
			Clock::set( $ctx->date( $i, count( $groups ) + 4, 60 ) );
			$id = groups_create_group(
				array(
					'creator_id'   => $owner,
					'name'         => (string) $g['name'],
					'description'  => (string) $g['desc'],
					'slug'         => groups_check_slug( sanitize_title( (string) $g['name'] ) ),
					'status'       => 'private' === ( $g['type'] ?? 'open' ) ? 'private' : 'public',
					'enable_forum' => 0,
					'date_created' => Clock::now(),
				)
			);
			if ( ! $id ) {
				$ctx->warn( $this->id(), 'group', $g['name'] );
				continue;
			}
			$ctx->record( $this->id(), 'group', (int) $id );
			$map[ (string) $g['slug'] ] = (int) $id;
			groups_update_groupmeta( (int) $id, 'last_activity', Clock::now() );
			if ( ! empty( $g['image'] ) && self::write_avatar( $ctx->media( (string) $g['image'] ), (int) $id, 'group' ) ) {
				$ctx->record( $this->id(), 'avatar', (int) $id, 'setup', array( 'object' => 'group' ) );
			}
			$people  = $ctx->people();
			$size    = min( count( $people ), max( 3, (int) ( $g['members'] ?? 6 ) ) );
			$created = (string) Clock::now();
			for ( $m = 1; $m < $size; $m++ ) {
				$member = $people[ ( $i * 3 + $m ) % count( $people ) ];
				if ( $member === $owner ) {
					continue;
				}
				// Members join over the weeks after the group starts, not all on its first day.
				$join = $ctx->date( $i * 7 + $m * 11, count( $groups ) * 7 + $size * 11, 55 );
				Clock::set( $join > $created ? $join : $ctx->after( $created, $m ) );
				groups_join_group( (int) $id, $member );
			}
		}
		$ctx->set( $this->id(), 'groups', $map );
	}

	/**
	 * A friend network: each person is friends with the next three on the roster.
	 *
	 * @param Context $ctx Context.
	 */
	private function friends( Context $ctx ): void {
		if ( ! bp_is_active( 'friends' ) ) {
			return;
		}
		$people = $ctx->people();
		$n      = count( $people );
		foreach ( $people as $i => $a ) {
			for ( $k = 1; $k <= 3 && $k < $n; $k++ ) {
				$b = $people[ ( $i + $k ) % $n ];
				if ( friends_check_friendship( $a, $b ) ) {
					continue;
				}
				Clock::set( $ctx->date( $i * 3 + $k, $n * 3, 75 ) );
				if ( friends_add_friend( $a, $b, true ) ) {
					$ctx->record(
						$this->id(),
						'friendship',
						(int) friends_get_friendship_id( $a, $b ),
						'sample',
						array(
							'a' => $a,
							'b' => $b,
						)
					);
				}
			}
		}
	}

	/**
	 * Turn {@N} into an @mention of sample person N, so the mention links and notifies.
	 *
	 * @param string  $text Text.
	 * @param Context $ctx  Context.
	 */
	private static function mentions( string $text, Context $ctx ): string {
		return (string) preg_replace_callback(
			'/\{@(\d+)\}/',
			static function ( array $m ) use ( $ctx ): string {
				$user = $ctx->person( (int) $m[1] );
				return $user && function_exists( 'bp_activity_get_user_mentionname' ) ? '@' . bp_activity_get_user_mentionname( $user ) : '';
			},
			$text
		);
	}

	/**
	 * Updates, photo posts and link posts, in groups where the post belongs to one, dated across the timeline.
	 *
	 * @param Context $ctx    Context.
	 * @param int     $offset First item.
	 */
	private function post_updates( Context $ctx, int $offset ): void {
		$all    = $this->updates();
		$groups = (array) $ctx->get( $this->id(), 'groups', array() );
		foreach ( array_slice( $all, $offset, self::UPDATES_PER_STEP, true ) as $i => $row ) {
			list( $who, $group, $text, $image, $url ) = $row;
			$user                                     = $ctx->person( (int) $who );
			if ( ! $user ) {
				continue;
			}
			$content = self::mentions( (string) $text, $ctx );
			if ( '' !== $image ) {
				$att = $ctx->attachment( (string) $image, $user, $this->id() );
				$src = $att ? wp_get_attachment_image_url( $att, 'large' ) : '';
				if ( $src ) {
					// The image alone: a link around it counts twice against BuddyBoss's links-per-post moderation limit.
					$content .= "\n\n" . sprintf( '<img src="%1$s" alt="%2$s" />', esc_url( $src ), esc_attr( Context::alt_text( (string) $image ) ) );
				}
			}
			if ( '' !== $url ) {
				$content .= "\n\n" . esc_url( $url );
			}
			// Members' posts fill the last three weeks, so the newest part of the feed is what people wrote.
			Clock::set( $ctx->date( (int) $i, count( $all ), 21 ) );
			$id = 0;
			if ( '' !== $group && isset( $groups[ $group ] ) && function_exists( 'groups_post_update' ) ) {
				$id = groups_post_update(
					array(
						'content'  => $content,
						'user_id'  => $user,
						'group_id' => (int) $groups[ $group ],
					)
				);
			} else {
				$id = bp_activity_post_update(
					array(
						'content' => $content,
						'user_id' => $user,
					)
				);
			}
			if ( $id ) {
				$ctx->record( $this->id(), 'activity', (int) $id );
			} else {
				$ctx->warn( $this->id(), 'activity', wp_trim_words( (string) $text, 6 ) );
			}
		}
	}

	/**
	 * Replies and favourites on the most recent updates, from other members, a little after each update.
	 *
	 * @param Context $ctx Context.
	 */
	private function comments( Context $ctx ): void {
		$replies = (array) $this->content()['comments'];
		$people  = $ctx->people();
		if ( ! $replies || count( $people ) < 2 ) {
			return;
		}
		$ids = array_slice( array_reverse( Ledger::ids( $this->id(), 'activity' ) ), 0, 14 );
		foreach ( $ids as $k => $activity_id ) {
			$activity = new \BP_Activity_Activity( (int) $activity_id );
			if ( empty( $activity->id ) || 'activity_update' !== $activity->type ) {
				continue;
			}
			for ( $r = 0, $count = 1 + $k % 3; $r < $count; $r++ ) {
				$user = $people[ ( $k * 5 + $r * 3 + 1 ) % count( $people ) ];
				if ( $user === (int) $activity->user_id ) {
					continue;
				}
				Clock::set( $ctx->after( (string) $activity->date_recorded, $k * 3 + $r ) );
				$comment = bp_activity_new_comment(
					array(
						'activity_id' => (int) $activity->id,
						'parent_id'   => (int) $activity->id,
						'content'     => (string) $replies[ ( $k + $r ) % count( $replies ) ],
						'user_id'     => $user,
					)
				);
				if ( $comment ) {
					$ctx->record( $this->id(), 'activity', (int) $comment );
				}
			}
			for ( $f = 0; $f < 2 + $k % 4; $f++ ) {
				$fan = $people[ ( $k * 7 + $f * 2 + 2 ) % count( $people ) ];
				if ( $fan !== (int) $activity->user_id && function_exists( 'bp_activity_add_user_favorite' ) ) {
					bp_activity_add_user_favorite( (int) $activity->id, $fan );
				}
			}
		}
	}

	/**
	 * Activity other products posted at the moment they were built (all in one minute, because this provider was not
	 * listening yet, as on a demo extended later): spread over the timeline and thinned like new bridged items. This
	 * provider's own types are excluded, so only other products' items move.
	 *
	 * @param Context $ctx Context.
	 */
	private function tidy( Context $ctx ): void {
		// BuddyPress stamps a group's last activity with the time an update was posted (the build time): use the date
		// of its newest item instead, so groups do not all read "active a minute ago".
		if ( function_exists( 'groups_update_groupmeta' ) ) {
			foreach ( (array) $ctx->get( $this->id(), 'groups', array() ) as $group_id ) {
				$latest = \BP_Activity_Activity::get(
					array(
						'per_page'    => 1,
						'show_hidden' => true,
						'filter'      => array(
							'object'     => 'groups',
							'primary_id' => (int) $group_id,
						),
					)
				);
				if ( ! empty( $latest['activities'][0]->date_recorded ) ) {
					groups_update_groupmeta( (int) $group_id, 'last_activity', $latest['activities'][0]->date_recorded );
				}
			}
		}
		$from = (string) $ctx->state->get( 'started_at' );
		$to   = (string) ( $ctx->state->get( 'built_at' ) ? $ctx->state->get( 'built_at' ) : current_time( 'mysql', true ) );
		if ( '' === $from || ! class_exists( '\BP_Activity_Activity' ) ) {
			return;
		}
		$found = \BP_Activity_Activity::get(
			array(
				'per_page'         => 400,
				'show_hidden'      => true,
				'display_comments' => 'stream',
				'date_query'       => array(
					array(
						'after'     => $from,
						'before'    => $to,
						'inclusive' => true,
					),
				),
				'filter_query'     => array(
					array(
						'column'  => 'type',
						'value'   => array( 'last_activity', 'new_avatar', 'friendship_created', 'joined_group', 'created_group' ),
						'compare' => 'NOT IN',
					),
				),
			)
		);
		$seen  = array();
		foreach ( (array) ( $found['activities'] ?? array() ) as $a ) {
			if ( in_array( $a->component, array( 'activity', 'groups' ), true ) && in_array( $a->type, array( 'activity_update', 'activity_comment' ), true ) ) {
				continue; // Members' own posts keep their dates.
			}
			$item                = new \BP_Activity_Activity( (int) $a->id );
			$seed                = abs( crc32( $item->type . '|' . $item->item_id . '|' . $item->secondary_item_id . '|' . $item->user_id ) );
			$item->date_recorded = $ctx->date( $seed % 500, 500, 45 );
			if ( self::thin( (string) $item->component, (string) $item->type, $item->date_recorded ) ) {
				$item->hide_sitewide = 1;
			}
			$item->save();
		}
	}

	/**
	 * Remove one recorded object through BuddyPress's own delete functions.
	 *
	 * @param object $row Ledger row (object_type, object_id, meta).
	 */
	public function remove( object $row ): bool {
		$id   = (int) $row->object_id;
		$meta = is_array( $row->meta ?? null ) ? $row->meta : (array) json_decode( (string) ( $row->meta ?? '' ), true );
		// True only once the object is gone: with a component switched off its functions are missing, and Finish must
		// say what is left rather than report it removed.
		switch ( $row->object_type ) {
			case 'activity':
				if ( ! function_exists( 'bp_activity_delete' ) ) {
					return false;
				}
				bp_activity_delete( array( 'id' => $id ) );
				$left = new \BP_Activity_Activity( $id );
				return empty( $left->id );
			case 'group':
				if ( ! function_exists( 'groups_delete_group' ) ) {
					return false;
				}
				if ( groups_get_group( $id )->id ) {
					groups_delete_group( $id );
				}
				return ! groups_get_group( $id )->id;
			case 'friendship':
				if ( empty( $meta['a'] ) || empty( $meta['b'] ) || ! get_userdata( (int) $meta['a'] ) || ! get_userdata( (int) $meta['b'] ) ) {
					return true; // A member is gone, and BuddyPress removed their friendships with them.
				}
				if ( ! function_exists( 'friends_remove_friend' ) ) {
					return false;
				}
				friends_remove_friend( (int) $meta['a'], (int) $meta['b'] );
				return ! friends_check_friendship( (int) $meta['a'], (int) $meta['b'] );
			case 'avatar':
				if ( function_exists( 'bp_core_delete_existing_avatar' ) ) {
					bp_core_delete_existing_avatar(
						array(
							'item_id' => $id,
							'object'  => (string) ( $meta['object'] ?? 'user' ),
						)
					);
				}
				return true;
			case 'attachment':
				return self::delete_post( $id, true );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function late_types(): array {
		return array( 'group' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function guide(): array {
		$t = $this->content()['guide'] ?? array();
		if ( ! $t || ! $this->available() ) {
			return array();
		}
		$member = 0;
		foreach ( array( 'member', 'attendee', 'student', 'buyer' ) as $role ) {
			$member = People::user_id( $role );
			if ( $member ) {
				break;
			}
		}
		$admin = static fn( string $page ): string => function_exists( 'bp_get_admin_url' ) ? (string) bp_get_admin_url( 'admin.php?page=' . $page ) : admin_url( 'admin.php?page=' . $page );
		return array(
			'name'         => $this->label(),
			'tab'          => $t['tab'],
			'member_label' => $t['member_label'],
			'owner_points' => $t['owner_points'],
			'roles'        => array(
				array(
					'key'    => 'member',
					'user'   => $member,
					'label'  => $t['roles']['member'][0],
					'points' => $t['roles']['member'][1],
				),
			),
			'member'       => self::guide_steps(
				$t['member'],
				array(
					'activity'      => (string) bp_get_activity_directory_permalink(),
					'groups'        => bp_is_active( 'groups' ) && function_exists( 'bp_get_groups_directory_url' ) ? (string) bp_get_groups_directory_url() : null,
					'members'       => (string) bp_get_members_directory_permalink(),
					'notifications' => bp_is_active( 'notifications' ) && $member && function_exists( 'bp_get_notifications_permalink' ) ? (string) bp_get_notifications_permalink( $member ) : null,
				),
				array(
					'activity'      => array(
						'public'    => true,
						'as'        => 'member',
						'highlight' => true,
					),
					'groups'        => array(
						'public' => true,
						'as'     => 'member',
					),
					'members'       => array(
						'public' => true,
						'as'     => 'member',
					),
					'notifications' => array( 'as' => 'member' ),
				)
			),
			'owner'        => self::guide_steps(
				$t['owner'],
				array(
					'components' => $admin( 'bp-components' ),
					'groups'     => bp_is_active( 'groups' ) ? $admin( 'bp-groups' ) : null,
					'activity'   => $admin( 'bp-activity' ),
				)
			),
			'highlights'   => self::guide_highlights( $t['highlights'] ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		return function_exists( 'bp_get_activity_directory_permalink' )
			? array(
				array(
					'title' => __( 'Community', 'wbcom-demo-installer' ),
					'url'   => (string) bp_get_activity_directory_permalink(),
				),
			)
			: array();
	}
}
