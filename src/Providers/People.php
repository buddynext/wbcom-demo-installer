<?php
/**
 * The shared cast: 20 sample people every other provider builds on.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Provider;
use WbcomDemo\Core\QuietMode;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the sample people. Always first in a build, always last to be removed.
 */
final class People extends Provider {

	/** User meta flag holding the build id. */
	public const FLAG = 'wbcom_demo_user';

	/** User meta holding the profile photo's attachment ID on sites without BuddyNext (issue #2). */
	public const AVATAR = 'wbcom_demo_avatar';

	/**
	 * Roster.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function roster(): array {
		/** This filter is documented in src/Core/Provider.php */
		return (array) apply_filters( 'wbcom_demo_content', (array) require WBCOM_DEMO_DIR . 'content/people.php', 'people' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function ai_skip_keys(): array {
		return array( 'name', 'topics' ); // Names match the people's photos; topics are interest slugs.
	}

	/**
	 * Roster indexes of the people with a role, in roster order (the order Context::people() returns).
	 *
	 * @param string $role Role tag.
	 * @return int[]
	 */
	public static function indexes( string $role ): array {
		return array_keys( array_filter( self::roster(), static fn( $p ) => in_array( $role, (array) ( $p['roles'] ?? array() ), true ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'people';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Sample people', 'wbcom-demo-installer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return true;
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
	public function summary(): string {
		return __( 'One cast of 20 people shared by every product.', 'wbcom-demo-installer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$plan = array( 'user' => array( count( self::roster() ), 'sample', __( 'sample people', 'wbcom-demo-installer' ) ) );
		if ( ! self::buddynext_active() ) {
			// BuddyNext stores the photos itself; elsewhere the installer adds them to the media library.
			$plan['attachment'] = array( count( self::roster() ), 'sample', __( 'profile photos', 'wbcom-demo-installer' ) );
		}
		return $plan;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'people:0', 'people:10' );
	}

	/**
	 * Create ten people per step.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		$offset = (int) substr( $step, 7 );
		$people = (array) $ctx->state->get( 'people' );
		foreach ( array_slice( self::roster(), $offset, 10, true ) as $i => $person ) {
			$login = 'demo.' . $person['login'];
			if ( username_exists( $login ) ) {
				// Never adopt an existing account: skip the slot instead.
				continue;
			}
			$user_id = wp_insert_user(
				array(
					'user_login'      => $login,
					'user_email'      => $person['login'] . '@' . QuietMode::DOMAIN,
					'user_pass'       => wp_generate_password( 32, true, true ),
					// Profile URLs and @handles read the nicename: show "@lucia-ferrari", not the "demo." login prefix. Core adds -2 on a clash.
					'user_nicename'   => sanitize_title( str_replace( '.', '-', $person['login'] ) ),
					'display_name'    => $person['name'],
					'first_name'      => strtok( $person['name'], ' ' ),
					'last_name'       => trim( (string) strstr( $person['name'], ' ' ) ),
					'nickname'        => $person['name'],
					'description'     => sprintf( '%s based in %s. Into %s.', $person['job'], $person['location'], $person['note'] ),
					'role'            => 'subscriber',
					'user_registered' => $ctx->date( $i, 20, 120 ),
				)
			);
			if ( is_wp_error( $user_id ) ) {
				continue;
			}
			update_user_meta( $user_id, self::FLAG, $ctx->build_id() );
			$ctx->record( $this->id(), 'user', (int) $user_id );
			// BuddyNext saves the photos when it is part of this build; otherwise core avatars need them.
			if ( ! ( $ctx->includes( 'buddynext' ) && self::buddynext_active() ) ) {
				$photo = $ctx->attachment( 'avatars/' . $person['login'] . '.jpg', (int) $user_id, $this->id() );
				if ( $photo ) {
					update_user_meta( $user_id, self::AVATAR, $photo );
				}
			}
			$people[] = array(
				'id'       => (int) $user_id,
				'index'    => $i,
				'login'    => $person['login'],
				'name'     => $person['name'],
				'headline' => $person['headline'],
				'job'      => $person['job'],
				'location' => $person['location'],
				'note'     => $person['note'],
				'skills'   => $person['skills'] ?? '',
				'school'   => $person['school'] ?? '',
				'degree'   => $person['degree'] ?? '',
				'field'    => $person['field'] ?? '',
				'topics'   => $person['topics'] ?? array(),
				'roles'    => array_merge( array( 'member' ), $person['roles'] ),
				// One photo per person, named by login, so no two people share a face.
				'avatar'   => 'avatars/' . $person['login'] . '.jpg',
				'cover'    => sprintf( 'covers/cover-%02d.jpg', ( $i % 8 ) + 1 ),
			);
		}
		$ctx->state->set( 'people', $people );
	}

	/**
	 * Remove a sample person. Products clean their own rows on delete_user.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		if ( 'attachment' === $row->object_type ) {
			return self::delete_post( (int) $row->object_id, true );
		}
		$user_id = (int) $row->object_id;
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return true;
		}
		// Safety: only accounts this installer flagged.
		if ( ! get_user_meta( $user_id, self::FLAG, true ) ) {
			return true;
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		// Anything still credited to the person after their sample content is gone is the owner's
		// (written by the owner as them, or kept): give it to the admin running Finish, never delete it.
		$owner = (int) get_current_user_id();
		if ( ! is_multisite() ) {
			// deleted_user still fires, so BuddyNext and the other products purge their own rows.
			wp_delete_user( $user_id, $owner && $owner !== $user_id ? $owner : null );
		} elseif ( function_exists( 'buddynext_remove_member' ) ) {
			buddynext_remove_member( $user_id );
		} else {
			wp_delete_user( $user_id, $owner && $owner !== $user_id ? $owner : null );
		}
		clean_user_cache( $user_id );
		return ! get_userdata( $user_id ) || ! is_user_member_of_blog( $user_id );
	}

	/**
	 * Flagged people left behind.
	 *
	 * @return string[]
	 */
	public function verify(): array {
		$left = get_users(
			array(
				'meta_key' => self::FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'   => 'ID',
				'number'   => 1,
			)
		);
		return $left ? array( __( 'Sample people are still on the site.', 'wbcom-demo-installer' ) ) : array();
	}

	/**
	 * Whether BuddyNext will store the photos (it has its own avatar service).
	 */
	private static function buddynext_active(): bool {
		return defined( 'BUDDYNEXT_VERSION' ) && class_exists( '\BuddyNext\Profile\AvatarService' );
	}

	/**
	 * Serve a sample person's profile photo through core avatars when no other plugin already set one.
	 *
	 * @param array<string, mixed> $args        Avatar data.
	 * @param mixed                $id_or_email User ID, email, WP_User, WP_Post or WP_Comment.
	 * @return array<string, mixed>
	 */
	public static function avatar_data( array $args, $id_or_email ): array {
		if ( ! empty( $args['url'] ) ) {
			return $args;
		}
		$user_id = 0;
		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( $id_or_email instanceof \WP_User ) {
			$user_id = (int) $id_or_email->ID;
		} elseif ( $id_or_email instanceof \WP_Post ) {
			$user_id = (int) $id_or_email->post_author;
		} elseif ( $id_or_email instanceof \WP_Comment ) {
			$user_id = (int) $id_or_email->user_id;
		} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user    = get_user_by( 'email', $id_or_email );
			$user_id = $user ? (int) $user->ID : 0;
		}
		$photo = $user_id ? (int) get_user_meta( $user_id, self::AVATAR, true ) : 0;
		if ( ! $photo ) {
			return $args;
		}
		$size = max( 1, (int) ( $args['size'] ?? 96 ) );
		$url  = wp_get_attachment_image_url( $photo, array( $size, $size ) );
		if ( $url ) {
			$args['url']          = $url;
			$args['found_avatar'] = true;
		}
		return $args;
	}
}
