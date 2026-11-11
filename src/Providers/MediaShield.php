<?php
/**
 * MediaShield: protected videos (Blender open movies on YouTube) and a playlist.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * MediaShield provider. Videos are a post type; playlist items go through MediaShield's REST route.
 */
final class MediaShield extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'mediashield';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'MediaShield';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'MEDIASHIELD_VERSION' ) && post_type_exists( 'mediashield_video' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'MEDIASHIELD_VERSION' ) ? (string) MEDIASHIELD_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '1.4.1';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'shield';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 70;
	}

	/**
	 * {@inheritDoc}
	 */
	public function ai_skip_keys(): array {
		return array( 'videos' ); // Real open films: titles and descriptions describe them.
	}

	/**
	 * {@inheritDoc}
	 */
	public function pro_label(): string {
		return defined( 'MEDIASHIELD_PRO_VERSION' ) ? __( 'Pro add-on active', 'wbcom-demo-installer' ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return sprintf(
			/* translators: %d: videos */
			__( '%d protected videos (Blender open movies) and a playlist', 'wbcom-demo-installer' ),
			count( $this->content()['videos'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'ms_activation_redirect' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		return array( 'ms_wizard_completed' => true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		return array(
			'video'    => array( count( $this->content()['videos'] ), 'sample', __( 'videos', 'wbcom-demo-installer' ) ),
			'playlist' => array( 1, 'sample', __( 'playlist', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'videos' );
	}

	/**
	 * Videos and a playlist.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		$author = $ctx->person( 0, 'instructor' );
		foreach ( $this->content()['videos'] as $i => $v ) {
			list( $yt, $title, $desc, $secs ) = $v;
			$id                               = wp_insert_post(
				array(
					'post_type'    => 'mediashield_video',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => wpautop( $desc ),
					'post_author'  => $author,
					'post_date'    => get_date_from_gmt( $ctx->date( $i, 4, 40 ) ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'video ' . $title, $id );
				continue;
			}
			foreach ( array(
				'_ms_platform'          => 'youtube',
				'_ms_platform_video_id' => $yt,
				'_ms_source_url'        => 'https://www.youtube.com/watch?v=' . $yt,
				'_ms_duration'          => $secs,
				'_ms_protection_level'  => 0 === $i ? 'standard' : 'basic',
			) as $key => $value ) {
				update_post_meta( $id, $key, $value );
			}
			if ( class_exists( '\MediaShield\CPT\VideoPostType' ) ) {
				\MediaShield\CPT\VideoPostType::normalize_platform( (int) $id );
			}
			$ctx->record( $this->id(), 'video', (int) $id );
		}

		$playlist = wp_insert_post(
			array(
				'post_type'   => 'mediashield_playlist',
				'post_status' => 'publish',
				'post_title'  => __( 'Open Movie Night', 'wbcom-demo-installer' ),
				'post_author' => $author,
			),
			true
		);
		if ( is_wp_error( $playlist ) ) {
			return;
		}
		$ctx->record( $this->id(), 'playlist', (int) $playlist );
		update_post_meta( $playlist, '_ms_autoplay', '1' );
		foreach ( Ledger::ids( $this->id(), 'video' ) as $video_id ) {
			$req = new \WP_REST_Request( 'POST', '/mediashield/v1/playlists/' . $playlist . '/items' );
			$req->set_param( 'video_id', $video_id );
			$res = rest_do_request( $req );
			if ( $res->is_error() ) {
				$ctx->warn( $this->id(), 'playlist item', $res->as_error() );
			}
		}
	}

	/**
	 * Remove one object (MediaShield cascades tags, sessions and playlist items).
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		return self::delete_post( (int) $row->object_id );
	}
}
