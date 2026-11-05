<?php
/**
 * The step engine: builds and finishes run one small step per call, from the wizard or WP-CLI.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

use WbcomDemo\Providers\People;

defined( 'ABSPATH' ) || exit;

/**
 * Build and Finish.
 *
 * The wizard calls step() repeatedly over REST; WP-CLI calls it in a loop. All state lives in
 * the State option, so closing the browser mid-way loses nothing: the next call resumes.
 */
final class Engine {

	private const LOCK = 'wbcom_demo_lock';

	/**
	 * What the site looks like now (wizard site check).
	 *
	 * @return array<string,mixed>
	 */
	public static function site(): array {
		$theme    = wp_get_theme();
		$products = array();
		foreach ( Registry::all() as $id => $p ) {
			if ( in_array( $id, array( 'people', 'theme' ), true ) || ! $p->available() ) {
				continue;
			}
			$products[] = array(
				'id'        => $id,
				'label'     => $p->label(),
				'version'   => $p->version(),
				'supported' => $p->supported(),
				'summary'   => $p->summary(),
				'pro'       => $p->pro_label(),
				'icon'      => $p->icon(),
				'plan'      => $p->plan(),
			);
		}
		$users          = count_users();
		$posts          = (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish;
		$theme_provider = Registry::get( 'theme' );
		$links          = array();
		$state          = new State();
		foreach ( Registry::all() as $id => $p ) {
			if ( ! in_array( $id, (array) $state->get( 'include' ), true ) || ! $p->available() ) {
				continue;
			}
			foreach ( $p->menu_items() as $item ) {
				$url = ! empty( $item['page_id'] ) ? get_permalink( (int) $item['page_id'] ) : ( $item['url'] ?? '' );
				if ( $url ) {
					$links[] = array(
						'title' => $item['title'],
						'url'   => $url,
					);
				}
			}
		}
		return array(
			'links'     => $links,
			'theme'     => array(
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'adapter' => $theme_provider ? $theme_provider->label() : '',
			),
			'products'  => $products,
			'recipes'   => Recipes::available(),
			'users'     => (int) $users['total_users'] - count( (array) ( new State() )->get( 'people' ) ),
			'posts'     => $posts,
			'leftovers' => self::other_demos(),
			'people'    => count( People::roster() ),
		);
	}

	/**
	 * Demo data left by a product's own demo tool (they would mix with ours).
	 *
	 * @return string[]
	 */
	private static function other_demos(): array {
		$found = array();
		$map   = array(
			'bn_demo_manifest'             => 'BuddyNext',
			'jetonomy_demo_data'           => 'Jetonomy',
			'learnomy_demo_data'           => 'Learnomy',
			'wcb_sample_data_ids'          => 'WP Career Board',
			'wb_listora_pro_demo_manifest' => 'WB Listora Pro',
			'learnomy_pro_demo_manifest'   => 'Learnomy Pro',
		);
		foreach ( $map as $option => $label ) {
			if ( get_option( $option ) ) {
				$found[] = $label;
			}
		}
		return (array) apply_filters( 'wbcom_demo_other_demos', $found );
	}

	/**
	 * Start a build.
	 *
	 * @param string   $recipe  Recipe id.
	 * @param string[] $products Provider ids.
	 * @param string   $look    'demo' to set home page, menu and logo; 'keep' to leave them.
	 * @return array<string,mixed>|\WP_Error Status.
	 */
	public static function start_build( string $recipe, array $products, string $look ) {
		$state = new State();
		if ( State::IDLE !== $state->get( 'status' ) ) {
			return new \WP_Error( 'wbcom_demo_exists', __( 'A demo is already on this site. Finish it before building another.', 'wbcom-demo-installer' ), array( 'status' => 409 ) );
		}
		$choosable = Registry::choosable();
		$products  = array_values( array_filter( $products, static fn( $id ) => isset( $choosable[ $id ] ) && $choosable[ $id ]->supported() ) );
		if ( ! $products ) {
			return new \WP_Error( 'wbcom_demo_nothing', __( 'Choose at least one product.', 'wbcom-demo-installer' ), array( 'status' => 400 ) );
		}
		$state->set( 'status', State::BUILDING )
			->set( 'build_id', wp_generate_uuid4() )
			->set( 'recipe', sanitize_key( $recipe ) )
			->set( 'include', $products )
			->set( 'look', 'keep' === $look ? 'keep' : 'demo' )
			->set( 'errors', array() )
			->set( 'warnings', array() )
			->set( 'failed', array() )
			->set( 'people', array() )
			->set( 'refs', array() );

		$ctx   = new Context( $state );
		$queue = array();
		foreach ( Registry::all() as $id => $p ) {
			if ( 'people' !== $id && 'theme' !== $id && ! in_array( $id, $products, true ) ) {
				continue;
			}
			if ( 'theme' === $id && 'keep' === $state->get( 'look' ) ) {
				continue;
			}
			foreach ( $p->steps( $ctx ) as $step ) {
				$queue[] = array( $id, $step );
			}
		}
		$state->set( 'queue', $queue )->set( 'total', count( $queue ) )->set( 'done', 0 )->save();
		return self::status();
	}

	/**
	 * Run the next build or finish step.
	 *
	 * @return array<string,mixed> Status after the step.
	 */
	public static function step(): array {
		if ( ! add_option( self::LOCK, time(), '', false ) ) {
			if ( time() - (int) get_option( self::LOCK ) < 120 ) {
				return self::status() + array( 'busy' => true );
			}
			update_option( self::LOCK, time(), false );
		}
		try {
			QuietMode::on();
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			$state = new State();
			if ( State::BUILDING === $state->get( 'status' ) ) {
				self::build_step( $state );
			} elseif ( State::FINISHING === $state->get( 'status' ) ) {
				Finisher::step( $state );
			}
		} finally {
			delete_option( self::LOCK );
		}
		return self::status();
	}

	/**
	 * One build step.
	 *
	 * @param State $state State.
	 */
	private static function build_step( State $state ): void {
		$queue = (array) $state->get( 'queue' );
		$ctx   = new Context( $state );
		foreach ( Registry::all() as $p ) {
			$p->boot( $ctx );
		}
		$item = array_shift( $queue );
		if ( $item ) {
			list( $id, $step ) = $item;
			$provider          = Registry::get( $id );
			$failed            = (array) $state->get( 'failed' );
			if ( $provider && ! in_array( $id, $failed, true ) ) {
				try {
					$provider->run( $step, $ctx );
				} catch ( \Throwable $e ) {
					$failed[] = $id;
					$errors   = (array) $state->get( 'errors' );
					$errors[] = sprintf( '%s: %s', $provider->label(), $e->getMessage() );
					$state->set( 'failed', $failed )->set( 'errors', $errors );
				}
			}
			$state->set( 'queue', $queue )->set( 'done', (int) $state->get( 'done' ) + 1 );
		}
		if ( ! $queue ) {
			$state->set( 'status', State::BUILT )->set( 'built_at', current_time( 'mysql', true ) );
			/**
			 * A demo build finished.
			 *
			 * @param string $build_id Build id.
			 */
			do_action( 'wbcom_demo_built', $state->get( 'build_id' ) );
		}
		$state->save();
	}

	/**
	 * Current status for the wizard and CLI.
	 *
	 * @return array<string,mixed>
	 */
	public static function status(): array {
		$state = new State();
		$queue = (array) $state->get( 'queue' );
		$next  = $queue[0] ?? null;
		$label = '';
		if ( $next ) {
			$p     = Registry::get( $next[0] );
			$label = $p ? $p->label() : $next[0];
		}
		$counts = array();
		foreach ( Ledger::counts() as $row ) {
			$counts[] = array(
				'provider' => $row->provider,
				'type'     => $row->object_type,
				'kind'     => $row->kind,
				'count'    => $row->n,
				'label'    => self::type_label( $row->provider, $row->object_type ),
				'product'  => Registry::get( $row->provider ) ? Registry::get( $row->provider )->label() : $row->provider,
			);
		}
		$done  = (int) $state->get( 'done' );
		$total = (int) $state->get( 'total' );
		if ( State::FINISHING === $state->get( 'status' ) ) {
			$f     = (array) $state->get( 'finish' );
			$done  = (int) ( $f['removed'] ?? 0 );
			$total = max( $done, (int) ( $f['total'] ?? 0 ) );
			$label = __( 'sample content', 'wbcom-demo-installer' );
		}
		return array(
			'status'           => $state->get( 'status' ),
			'recipe'           => $state->get( 'recipe' ),
			'include'          => $state->get( 'include' ),
			'look'             => $state->get( 'look' ),
			'done'             => $done,
			'total'            => $total,
			'current'          => $label,
			'current_provider' => $next[0] ?? '',
			'errors'           => $state->get( 'errors' ),
			'warnings'         => (array) $state->get( 'warnings' ),
			'built_at'         => $state->get( 'built_at' ),
			'ledger'           => $counts,
			'report'           => $state->get( 'report' ),
		);
	}

	/**
	 * Human label of an object type.
	 *
	 * @param string $provider Provider id.
	 * @param string $type     Object type.
	 */
	public static function type_label( string $provider, string $type ): string {
		$p = Registry::get( $provider );
		if ( ! $p ) {
			return $type;
		}
		$labels = $p->type_labels();
		return $labels[ $type ] ?? str_replace( '_', ' ', $type );
	}
}
