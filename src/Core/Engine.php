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

	/**
	 * Queue step that applies a provider's settings().
	 */
	private const SETTINGS_STEP = '__settings';

	private const LOCK = 'wbcom_demo_lock';

	/**
	 * Queue step prefix that writes one chunk of a provider's text with AI ("__ai:0").
	 */
	private const AI_STEP = '__ai:';

	/**
	 * The brief kept in the state, plain text only.
	 *
	 * @param array<string,mixed> $raw Idea and description from the wizard or WP-CLI.
	 * @return array<string,mixed>
	 */
	private static function brief( array $raw ): array {
		$text = static fn( $v, int $max ): string => mb_substr( trim( sanitize_text_field( is_scalar( $v ) ? (string) $v : '' ) ), 0, $max );
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		$all  = 'en_US' === get_locale() ? array() : wp_get_available_translations();
		return array(
			'description' => $text( $raw['description'] ?? '', 600 ),
			'name'        => $text( $raw['name'] ?? '', 60 ),
			'tagline'     => $text( $raw['tagline'] ?? '', 120 ),
			'audience'    => $text( $raw['audience'] ?? '', 160 ),
			'tone'        => $text( $raw['tone'] ?? '', 60 ),
			'highlights'  => array_slice( array_filter( array_map( static fn( $h ) => $text( $h, 120 ), (array) ( $raw['highlights'] ?? array() ) ) ), 0, 6 ),
			'language'    => (string) ( $all[ get_locale() ]['english_name'] ?? 'English' ),
		);
	}

	/**
	 * Signs that people already use this site: members other than the admin and sample people, blog posts
	 * beyond "Hello world!", comments. The installer is made for a fresh demonstration site, so a build here
	 * needs the admin's explicit consent.
	 *
	 * @return string[] Readable findings, empty for a fresh site.
	 */
	public static function live_signals(): array {
		$found  = array();
		$sample = ( new \WP_User_Query(
			array(
				'meta_key' => People::FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one count, wizard only.
			'fields'       => 'ID',
			'number'       => 1,
			'count_total'  => true,
			)
		) )->get_total();
		$others = (int) count_users()['total_users'] - (int) $sample - 1;
		if ( $others > 0 ) {
			/* translators: %d: number of members. */
			$found[] = sprintf( _n( '%d other member', '%d other members', $others, 'wbcom-demo-installer' ), $others );
		}
		// A fresh install has "Hello world!" (post 1) and its comment (comment 1): discount them only while they exist.
		$hello = get_post( 1 );
		$posts = (int) wp_count_posts( 'post' )->publish - ( $hello && 'post' === $hello->post_type && 'publish' === $hello->post_status ? 1 : 0 );
		if ( $posts > 0 ) {
			/* translators: %d: number of blog posts. */
			$found[] = sprintf( _n( '%d blog post', '%d blog posts', $posts, 'wbcom-demo-installer' ), $posts );
		}
		$first    = get_comment( 1 );
		$comments = (int) wp_count_comments()->approved - ( $first && '1' === (string) $first->comment_approved ? 1 : 0 );
		if ( $comments > 0 ) {
			/* translators: %d: number of comments. */
			$found[] = sprintf( _n( '%d comment', '%d comments', $comments, 'wbcom-demo-installer' ), $comments );
		}
		return $found;
	}

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
			'links'       => $links,
			'theme'       => array(
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'adapter' => $theme_provider ? $theme_provider->label() : '',
			),
			'products'    => $products,
			'recipes'     => Recipes::available(),
			'users'       => (int) $users['total_users'] - count( (array) ( new State() )->get( 'people' ) ),
			'posts'       => $posts,
			'leftovers'   => self::other_demos(),
			'live'        => State::IDLE === $state->get( 'status' ) ? self::live_signals() : array(),
			'people'      => count( People::roster() ),
			'catalog'     => Installer::overview(),
			'memory'      => self::memory(),
			'ai'          => \WbcomDemo\Ai\Assistant::overview(),
			// Names of every starting point, also those whose product is not installed yet (AI ideas name them).
			'all_recipes' => array_map( static fn( array $r ): array => array( 'name' => $r['name'] ), Recipes::all() ),
			'identity'    => array(
				'name'    => (string) get_option( 'blogname' ),
				'tagline' => (string) get_option( 'blogdescription' ),
			),
		);
	}

	/**
	 * PHP memory for the build. The wizard runs each step over REST, so this request's limit is the one the steps
	 * get. With all products active WordPress itself needs about 165 MB before a step starts (card 10487106723),
	 * so under 256 MB a step can stop the request.
	 *
	 * @return array{limit:int,used:int,low:bool}
	 */
	public static function memory(): array {
		$limit = (int) wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		return array(
			'limit' => $limit,
			'used'  => (int) memory_get_peak_usage(),
			'low'   => $limit > 0 && $limit < 256 * MB_IN_BYTES,
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
	 * @param string              $recipe  Recipe id.
	 * @param string[]            $products Provider ids.
	 * @param string              $look    'demo' to set home page, menu and logo; 'keep' to leave them.
	 * @param bool                $consent The admin confirmed a build on a site that already has members or content.
	 * @param string              $name    Site name typed on Review; empty keeps the current one.
	 * @param string              $tagline Tagline typed on Review; empty keeps the current one.
	 * @param array<string,mixed> $brief The AI idea the owner picked (description, name, tagline, audience,
	 *                          tone, highlights); empty builds with the curated text.
	 * @return array<string,mixed>|\WP_Error Status.
	 */
	public static function start_build( string $recipe, array $products, string $look, bool $consent = false, string $name = '', string $tagline = '', array $brief = array() ) {
		$state = new State();
		if ( State::IDLE !== $state->get( 'status' ) ) {
			return new \WP_Error( 'wbcom_demo_exists', __( 'A demo is already on this site. Finish it before building another.', 'wbcom-demo-installer' ), array( 'status' => 409 ) );
		}
		$live = self::live_signals();
		if ( $live && ! $consent ) {
			return new \WP_Error(
				'wbcom_demo_live_site',
				/* translators: %s: what the site already has, e.g. "3 other members, 12 blog posts". */
				sprintf( __( 'This site already has %s. The demo is made for a fresh site; confirm that you want sample content added here.', 'wbcom-demo-installer' ), implode( ', ', $live ) ),
				array( 'status' => 409 )
			);
		}
		if ( ! isset( Recipes::available()[ $recipe ] ) ) {
			return new \WP_Error( 'wbcom_demo_recipe', __( 'Choose one of the starting points shown on this site.', 'wbcom-demo-installer' ), array( 'status' => 400 ) );
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
			->set( 'fresh_site', (string) get_option( 'fresh_site', '0' ) )
			->set( 'started_at', current_time( 'mysql', true ) )
			->set( 'failed', array() )
			->set( 'people', array() )
			->set( 'refs', array() );

		// The owner's own name and tagline, through the snapshot so Remove everything puts the old ones back.
		$name    = trim( sanitize_text_field( $name ) );
		$tagline = trim( sanitize_text_field( $tagline ) );
		if ( '' !== $name && get_option( 'blogname' ) !== $name ) {
			Snapshot::write( 'blogname', $name );
			$state->set( 'own_name', true ); // The theme shows this name instead of the sample logo.
		}
		if ( '' !== $tagline && get_option( 'blogdescription' ) !== $tagline ) {
			Snapshot::write( 'blogdescription', $tagline );
		}
		$brief = $brief && \WbcomDemo\Ai\Assistant::available() ? self::brief( $brief ) : array();
		$state->set( 'brief', $brief )->set( 'ai_maps', array() );
		$ctx   = new Context( $state );
		$queue = array();
		if ( $brief ) {
			// The text is written first, so every content step already reads it.
			foreach ( Registry::all() as $id => $p ) {
				if ( ( 'people' === $id || in_array( $id, $products, true ) ) && $p->available() ) {
					for ( $n = 0, $chunks = \WbcomDemo\Ai\Pack::chunks( $id ); $n < $chunks; $n++ ) {
						$queue[] = array( $id, self::AI_STEP . $n );
					}
				}
			}
		}
		foreach ( Registry::all() as $id => $p ) {
			if ( 'people' !== $id && 'theme' !== $id && ! in_array( $id, $products, true ) ) {
				continue;
			}
			if ( 'theme' === $id && 'keep' === $state->get( 'look' ) ) {
				continue;
			}
			if ( $p->available() && ( $p->settings() || $p->activation_redirects() ) ) {
				$queue[] = array( $id, self::SETTINGS_STEP );
			}
			foreach ( $p->steps( $ctx ) as $step ) {
				$queue[] = array( $id, $step );
			}
		}
		// Product pages without a sidebar, also when the owner keeps their own home page and menu.
		$queue[] = array( 'theme', 'pages' );
		$state->set( 'queue', $queue )->set( 'total', count( $queue ) )->set( 'done', 0 )->save();
		return self::status();
	}

	/**
	 * Run the next build or finish step.
	 *
	 * @return array<string,mixed> Status after the step.
	 */
	public static function step(): array {
		// The lock holds "<time>|<token>": only the request that owns the token releases it, and a lock older
		// than two minutes belongs to a request that died, so it is taken over.
		$token = wp_generate_uuid4();
		$mine  = time() . '|' . $token;
		if ( ! add_option( self::LOCK, $mine, '', false ) ) {
			wp_cache_delete( self::LOCK, 'options' );
			$held = (string) get_option( self::LOCK );
			if ( time() - (int) strtok( $held, '|' ) < 120 ) {
				return self::status() + array( 'busy' => true );
			}
			update_option( self::LOCK, $mine, false );
		}
		$release = static function () use ( $mine ): void {
			wp_cache_delete( self::LOCK, 'options' );
			if ( get_option( self::LOCK ) === $mine ) {
				delete_option( self::LOCK );
			}
		};
		// A fatal error (memory, a product bug) skips finally; shutdown still runs, so the next request is not held.
		register_shutdown_function( $release );
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
			$release();
		}
		return self::status();
	}

	/**
	 * Apply a provider's presentation settings through the snapshot.
	 *
	 * @param Provider $provider Provider.
	 */
	private static function apply_settings( Provider $provider ): void {
		$provider->clear_redirects();
		foreach ( $provider->settings() as $name => $value ) {
			// A one-time activation redirect is only cleared: bringing it back on Remove everything would bounce the admin.
			if ( str_starts_with( $name, 'transient:' ) ) {
				delete_transient( substr( $name, 10 ) );
				continue;
			}
			if ( is_array( $value ) && ! str_contains( $name, ':' ) ) {
				$value = array_merge( (array) get_option( $name, array() ), $value );
			}
			Snapshot::write( $name, $value );
		}
	}

	/**
	 * Run one build step.
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
			// A request that dies inside a step (memory, a fatal error) never returns, so the same step would
			// run again forever. Count attempts on the queue head; the third ends that product's build.
			$trying   = (array) $state->get( 'trying' );
			$attempts = ( $trying['item'] ?? null ) === $item ? (int) $trying['n'] + 1 : 1;
			if ( $provider && $attempts > 2 && ! in_array( $id, $failed, true ) ) {
				$failed[] = $id;
				$errors   = (array) $state->get( 'errors' );
				/* translators: %s: product name. */
				$errors[] = sprintf( __( '%s: a step stopped the request twice (often the PHP memory limit), so the rest of this product was skipped.', 'wbcom-demo-installer' ), $provider->label() );
				$state->set( 'failed', $failed )->set( 'errors', $errors );
			}
			$state->set(
				'trying',
				array(
					'item' => $item,
					'n'    => $attempts,
				)
			)->save();
			if ( $provider && ! in_array( $id, $failed, true ) ) {
				try {
					if ( str_starts_with( (string) $step, self::AI_STEP ) ) {
						// AI never stops a build: a chunk that fails keeps the curated text and says so.
						$why = \WbcomDemo\Ai\Pack::write( $provider, (int) substr( (string) $step, strlen( self::AI_STEP ) ), $state );
						if ( '' !== $why ) {
							$warnings   = (array) $state->get( 'warnings' );
							$warnings[] = sprintf( '%s: %s', $provider->label(), $why );
							$state->set( 'warnings', array_values( array_unique( $warnings ) ) );
						}
					} elseif ( self::SETTINGS_STEP === $step ) {
						self::apply_settings( $provider );
					} else {
						$provider->run( $step, $ctx );
					}
				} catch ( \Throwable $e ) {
					$failed[] = $id;
					$errors   = (array) $state->get( 'errors' );
					$errors[] = sprintf( '%s: %s', $provider->label(), $e->getMessage() );
					$state->set( 'failed', $failed )->set( 'errors', $errors );
				}
			}
			$state->set( 'queue', $queue )->set( 'done', (int) $state->get( 'done' ) + 1 )->set( 'trying', array() );
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
			if ( str_starts_with( (string) $next[1], self::AI_STEP ) ) {
				/* translators: %s: product name. */
				$label = sprintf( __( 'the text for %s', 'wbcom-demo-installer' ), $label );
			}
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
