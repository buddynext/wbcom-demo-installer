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

	/**
	 * Roster.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function roster(): array {
		return require WBCOM_DEMO_DIR . 'content/people.php';
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
		return array( 'user' => array( count( self::roster() ), 'sample', __( 'sample people', 'wbcom-demo-installer' ) ) );
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
				'avatar'   => sprintf( 'avatars/avatar-%02d.png', ( $i % 12 ) + 1 ),
				'cover'    => sprintf( 'covers/cover-%02d.png', ( $i % 8 ) + 1 ),
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
		$user_id = (int) $row->object_id;
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return true;
		}
		// Safety: only accounts this installer flagged.
		if ( ! get_user_meta( $user_id, self::FLAG, true ) ) {
			return true;
		}
		if ( function_exists( 'buddynext_remove_member' ) ) {
			return (bool) buddynext_remove_member( $user_id );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		return wp_delete_user( $user_id );
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
}
