<?php
/**
 * "Try the demo" page: who a visitor can be, a guided tour, and what the product includes.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the `wbcom-demo/tour` block from each product's Provider::guide(), and runs its smart links.
 *
 * Everything is rendered for the person viewing it: the role buttons and tour links carry a nonce for that
 * person, and signed-in pages are not served from a page cache. A smart link (`?wdi_go=1`) switches to the
 * account a step belongs to before opening it, so a step is never a dead end: a member who opens an owner step
 * is switched back first, and the owner who opens a member step is switched to that member. Switching uses the
 * User Switching plugin; without it the links open the pages as they are. Signed-out visitors (the public model
 * site) get the launch link instead of a switch.
 */
final class Guide {

	public const SHORTCODE = 'wbcom_demo_guide';
	public const BLOCK     = 'wbcom-demo/tour';

	/**
	 * Option for demos built outside the installer (`wp wbcom-demo guide --products --accounts --launch`):
	 * [ 'products' => string[], 'accounts' => [ provider id => user id[] ], 'launch' => url ].
	 */
	public const OPTION = 'wbcom_demo_guide';

	/**
	 * Register the block, the shortcode kept for pages made by the first version, and the smart-link handler.
	 */
	public static function init(): void {
		add_shortcode( self::SHORTCODE, array( self::class, 'render' ) );
		add_action( 'init', array( self::class, 'register_block' ) );
		add_action( 'init', array( self::class, 'go' ), 20 );
	}

	/**
	 * Register the server-rendered block, with an editor preview so the page edits like any other.
	 */
	public static function register_block(): void {
		wp_register_script(
			'wbcom-demo-tour-editor',
			WBCOM_DEMO_URL . 'assets/js/tour-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n', 'wp-block-editor' ),
			(string) filemtime( WBCOM_DEMO_DIR . 'assets/js/tour-block.js' ),
			true
		);
		wp_register_style( 'wbcom-demo-tour', WBCOM_DEMO_URL . 'assets/css/tour.css', array(), (string) filemtime( WBCOM_DEMO_DIR . 'assets/css/tour.css' ) );
		wp_register_script(
			'wbcom-demo-tour-view',
			WBCOM_DEMO_URL . 'assets/js/tour.js',
			array(),
			(string) filemtime( WBCOM_DEMO_DIR . 'assets/js/tour.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		register_block_type(
			self::BLOCK,
			array(
				'api_version'         => '3',
				'title'               => __( 'Demo tour', 'wbcom-demo-installer' ),
				'category'            => 'widgets',
				'editor_script'       => 'wbcom-demo-tour-editor',
				// WordPress loads these only on pages that show the block.
				'style_handles'       => array( 'wbcom-demo-tour' ),
				'view_script_handles' => array( 'wbcom-demo-tour-view' ),
				'render_callback'     => array( self::class, 'render' ),
				'supports'            => array( 'html' => false ),
			)
		);
	}

	/**
	 * Block markup for the guide page.
	 */
	public static function page_content(): string {
		return '<!-- wp:' . self::BLOCK . ' /-->';
	}

	/**
	 * Products in this demo that offer a guide: available, and built by the installer or named on the option.
	 *
	 * @return array<string,array{label:string,guide:array<string,mixed>}>
	 */
	public static function products(): array {
		$extra = (array) get_option( self::OPTION, array() );
		$built = array_merge(
			array_map( static fn( $r ) => (string) $r->provider, Ledger::counts() ),
			array_map( 'strval', (array) ( $extra['products'] ?? array() ) )
		);
		$out   = array();
		foreach ( Registry::all() as $id => $p ) {
			if ( ! in_array( $id, $built, true ) || ! $p->available() ) {
				continue;
			}
			$guide = $p->guide();
			if ( ! $guide ) {
				continue;
			}
			// Named accounts take the product's role slots in order.
			foreach ( array_values( (array) ( $extra['accounts'][ $id ] ?? array() ) ) as $i => $user_id ) {
				if ( isset( $guide['roles'][ $i ] ) ) {
					$guide['roles'][ $i ]['user'] = (int) $user_id;
				}
			}
			// Steps name the role they belong to; resolve it to whoever fills that role on this site.
			$people = array();
			foreach ( (array) ( $guide['roles'] ?? array() ) as $role ) {
				$people[ (string) ( $role['key'] ?? '' ) ] = (int) ( $role['user'] ?? 0 );
			}
			foreach ( (array) ( $guide['member'] ?? array() ) as $k => $step ) {
				if ( isset( $step['as'] ) && is_string( $step['as'] ) ) {
					$guide['member'][ $k ]['as'] = $people[ $step['as'] ] ?? 0;
				}
			}
			$out[ $id ] = array(
				'label' => $p->label(),
				'guide' => $guide,
			);
		}
		return $out;
	}

	/**
	 * The site owner for this visitor: the account they switched from, else themselves when they run the site.
	 */
	private static function owner(): ?\WP_User {
		$old = class_exists( 'user_switching' ) ? \user_switching::get_old_user() : false;
		if ( $old instanceof \WP_User && user_can( $old, 'manage_options' ) ) {
			return $old; // Only a site administrator's switched session counts as the owner.
		}
		return current_user_can( 'manage_options' ) ? wp_get_current_user() : null;
	}

	/**
	 * User ids of every account the guides offer (the people on the role cards).
	 *
	 * @return int[]
	 */
	public static function account_ids(): array {
		return array_keys( self::roles( self::products() ) );
	}

	/**
	 * Every account the guides offer, user id => role data.
	 *
	 * @param array<string,array{label:string,guide:array<string,mixed>}> $products Products.
	 * @return array<int,array<string,mixed>>
	 */
	private static function roles( array $products ): array {
		$out = array();
		foreach ( $products as $p ) {
			foreach ( (array) ( $p['guide']['roles'] ?? array() ) as $role ) {
				$id = (int) ( $role['user'] ?? 0 );
				if ( $id && ! isset( $out[ $id ] ) && get_userdata( $id ) ) {
					$out[ $id ] = $role;
				}
			}
		}
		return $out;
	}

	/**
	 * A link that opens `$to` as `$account` (0 = the site owner), switching on the way when needed.
	 *
	 * @param string $to      Destination.
	 * @param int    $account Account the destination belongs to.
	 */
	private static function link( string $to, int $account ): string {
		if ( ! is_user_logged_in() || ! function_exists( 'switch_to_user' ) ) {
			return $to;
		}
		if ( ( 0 === $account && current_user_can( 'manage_options' ) ) || get_current_user_id() === $account ) {
			return $to;
		}
		return add_query_arg(
			array(
				'wdi_go'   => 1,
				'as'       => $account,
				'to'       => rawurlencode( $to ),
				'_wpnonce' => wp_create_nonce( 'wdi_go' ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Run a smart link: switch to the account the step belongs to, then open the step.
	 */
	public static function go(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the nonce is checked below before anything changes.
		if ( empty( $_GET['wdi_go'] ) ) {
			return;
		}
		$to = isset( $_GET['to'] ) ? esc_url_raw( rawurldecode( sanitize_text_field( wp_unslash( $_GET['to'] ) ) ) ) : '';
		$as = isset( $_GET['as'] ) ? absint( $_GET['as'] ) : 0;
		$ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'wdi_go' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$to = wp_validate_redirect( $to, home_url( '/' ) );
		if ( ! $ok ) {
			// A link from an earlier session (browser back after a switch): its nonce no longer matches, so opening the
			// step would show it as the wrong person. Back to the guide, which renders fresh links for this session.
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : $to );
			exit;
		}
		$owner = self::owner();
		// Only the site owner, or someone switched from them, may switch, and only to accounts the guide offers.
		if ( $owner && function_exists( 'switch_to_user' ) && ( 0 === $as || isset( self::roles( self::products() )[ $as ] ) ) ) {
			$target = 0 === $as ? $owner : get_userdata( $as );
			if ( $target instanceof \WP_User && get_current_user_id() !== $target->ID ) {
				if ( get_current_user_id() !== $owner->ID ) {
					switch_to_user( $owner->ID, false, false ); // Back to the owner; clears the switched-from marker.
				}
				if ( $target->ID !== $owner->ID ) {
					switch_to_user( $target->ID, false, true );
				}
			}
		}
		wp_safe_redirect( $to );
		exit;
	}

	/**
	 * Block and shortcode output.
	 */
	public static function render(): string {
		$products = self::products();
		if ( ! $products ) {
			return '';
		}
		$extra  = (array) get_option( self::OPTION, array() );
		$launch = (string) ( $extra['launch'] ?? '' );
		$first  = reset( $products );
		ob_start();
		?>
		<div class="wdi-tour">
			<?php
			self::role_cards( $products, $launch );
			self::tour( $products, $launch );
			self::highlights( $products, (string) ( $first['guide']['name'] ?? $first['label'] ) );
			?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * A person's picture: their photo from the installer's pack, else a real avatar, else their initials.
	 *
	 * @param int $user_id User.
	 */
	private static function face( int $user_id ): string {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}
		$file = 'media/avatars/' . substr( (string) $user->user_login, 5 ) . '.jpg';
		if ( str_starts_with( (string) $user->user_login, 'demo.' ) && is_readable( WBCOM_DEMO_DIR . $file ) ) {
			return '<img class="wdi-tour__face" src="' . esc_url( WBCOM_DEMO_URL . $file ) . '" alt="" width="56" height="56" loading="lazy">';
		}
		$data    = get_avatar_data( $user_id, array( 'size' => 112 ) );
		$uploads = (string) wp_parse_url( (string) wp_get_upload_dir()['baseurl'], PHP_URL_PATH );
		// Only an uploaded photo counts: Gravatar's silhouette and a theme's default member image (Reign) both report
		// "found", and for a demo card initials read better than a placeholder.
		if ( ! empty( $data['found_avatar'] ) && '' !== $uploads && str_contains( (string) wp_parse_url( (string) $data['url'], PHP_URL_PATH ), $uploads . '/' ) ) {
			return '<img class="wdi-tour__face" src="' . esc_url( (string) $data['url'] ) . '" alt="" width="56" height="56" loading="lazy">';
		}
		$parts    = preg_split( '/\s+/', trim( (string) $user->display_name ) );
		$parts    = $parts ? $parts : array( '' );
		$initials = strtoupper( substr( (string) $parts[0], 0, 1 ) . substr( (string) end( $parts ), 0, 1 ) );
		return '<span class="wdi-tour__face wdi-tour__face--icon" aria-hidden="true">' . esc_html( $initials ) . '</span>';
	}

	/**
	 * "Pick who you want to be": the site owner and each product's people, one button each.
	 *
	 * @param array<string,array{label:string,guide:array<string,mixed>}> $products Products.
	 * @param string                                                      $launch   Launch link for signed-out visitors.
	 */
	private static function role_cards( array $products, string $launch ): void {
		$owner = self::owner();
		$first = reset( $products );
		$cards = array(
			array(
				'user'   => $owner ? $owner->ID : 0,
				'name'   => __( 'Site owner', 'wbcom-demo-installer' ),
				'label'  => __( 'Runs the site', 'wbcom-demo-installer' ),
				'points' => (array) ( $first['guide']['owner_points'] ?? array() ),
				'as'     => 0,
				'to'     => (string) ( $first['guide']['owner'][0]['url'] ?? admin_url() ),
			),
		);
		foreach ( self::roles( $products ) as $id => $role ) {
			$to = '';
			foreach ( $products as $p ) {
				foreach ( (array) ( $p['guide']['member'] ?? array() ) as $step ) {
					if ( '' === $to && (int) ( $step['as'] ?? 0 ) === $id ) {
						$to = (string) ( $step['url'] ?? '' );
					}
				}
			}
			$cards[] = array(
				'user'   => $id,
				'name'   => (string) get_userdata( $id )->display_name,
				'label'  => (string) $role['label'],
				'points' => (array) ( $role['points'] ?? array() ),
				'as'     => $id,
				'to'     => '' !== $to ? $to : home_url( '/' ),
			);
		}
		?>
		<section class="wdi-tour__section" id="roles">
			<h2><?php esc_html_e( 'Pick who you want to be', 'wbcom-demo-installer' ); ?></h2>
			<p class="wdi-tour__lede"><?php esc_html_e( 'One click puts you in that person\'s account, with their content, activity and messages already in place.', 'wbcom-demo-installer' ); ?></p>
			<div class="wdi-tour__roles">
				<?php
				foreach ( $cards as $card ) :
					$here = is_user_logged_in() && get_current_user_id() === (int) $card['user'];
					if ( ! is_user_logged_in() ) {
						$href  = ''; // One launch call below the cards, not one per card.
						$label = '';
					} elseif ( $here ) {
						$href  = '#tour';
						$label = __( 'Start the tour', 'wbcom-demo-installer' );
					} elseif ( 0 === $card['as'] ) {
						$href  = self::link( (string) $card['to'], 0 );
						$label = __( 'Open the dashboard', 'wbcom-demo-installer' );
					} else {
						$href = self::link( (string) $card['to'], (int) $card['as'] );
						/* translators: %s: person's first name */
						$label = sprintf( __( 'Explore as %s', 'wbcom-demo-installer' ), (string) strtok( (string) $card['name'], ' ' ) );
					}
					?>
					<article class="wdi-tour__role<?php echo $here ? ' is-here' : ''; ?>">
						<div class="wdi-tour__who">
							<?php if ( 0 === $card['as'] ) : ?>
								<span class="wdi-tour__face wdi-tour__face--icon"><?php echo Icons::svg( 'home', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?></span>
							<?php else : ?>
								<?php echo self::face( (int) $card['user'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
							<?php endif; ?>
							<div>
								<strong><?php echo esc_html( (string) $card['name'] ); ?></strong>
								<span class="wdi-tour__badge<?php echo $here ? ' is-here' : ''; ?>"><?php echo esc_html( $here ? __( 'You are here', 'wbcom-demo-installer' ) : (string) $card['label'] ); ?></span>
							</div>
						</div>
						<?php if ( $card['points'] ) : ?>
							<ul class="wdi-tour__points">
								<?php foreach ( $card['points'] as $point ) : ?>
									<li><?php echo Icons::svg( 'tick', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?><span><?php echo esc_html( (string) $point ); ?></span></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<?php if ( '' !== $href ) : ?>
							<a class="wdi-tour__btn" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
			<?php if ( ! is_user_logged_in() && '' !== $launch ) : ?>
				<div class="wdi-tour__launch">
					<div>
						<strong><?php esc_html_e( 'Every one of these accounts is ready in your own copy', 'wbcom-demo-installer' ); ?></strong>
						<span><?php esc_html_e( 'Your private demo opens in about a minute, signed in as the site owner. Nothing is charged.', 'wbcom-demo-installer' ); ?></span>
					</div>
					<a class="wdi-tour__btn" href="<?php echo esc_url( $launch ); ?>"><?php esc_html_e( 'Launch your free demo', 'wbcom-demo-installer' ); ?></a>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * The guided tour: a tab of member steps and a tab of owner steps; every step opens as the right account.
	 *
	 * @param array<string,array{label:string,guide:array<string,mixed>}> $products Products.
	 * @param string                                                      $launch   Launch link for signed-out visitors.
	 */
	private static function tour( array $products, string $launch ): void {
		$panels = self::panels( $products );
		$uid    = wp_unique_id( 'wdi-tour-' );
		?>
		<section class="wdi-tour__section" id="tour">
			<h2><?php esc_html_e( 'Guided tour', 'wbcom-demo-installer' ); ?></h2>
			<p class="wdi-tour__lede"><?php esc_html_e( 'Each step opens the right screen as the right person, so you can follow the whole story in a few minutes.', 'wbcom-demo-installer' ); ?></p>
			<div class="wdi-tour__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Guided tour', 'wbcom-demo-installer' ); ?>">
				<?php foreach ( array_keys( $panels ) as $i => $key ) : ?>
					<button type="button" role="tab" id="<?php echo esc_attr( $uid . '-t-' . $i ); ?>" aria-controls="<?php echo esc_attr( $uid . '-p-' . $i ); ?>" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"><?php echo esc_html( $panels[ $key ]['label'] ); ?></button>
				<?php endforeach; ?>
			</div>
			<?php
			foreach ( array_values( $panels ) as $i => $panel ) :
				$count = count( $panel['steps'] );
				// Columns from the step count, so no row ends with one card on its own (6 steps = 3 + 3).
				$cols = $count <= 5 ? max( 1, $count ) : min( 4, (int) ceil( $count / 2 ) );
				?>
				<div style="--wdi-cols:<?php echo (int) $cols; ?>" class="wdi-tour__steps" role="tabpanel" id="<?php echo esc_attr( $uid . '-p-' . $i ); ?>" aria-labelledby="<?php echo esc_attr( $uid . '-t-' . $i ); ?>"<?php echo 0 === $i ? '' : ' hidden'; ?>>
					<?php
					foreach ( $panel['steps'] as $n => $step ) {
						self::step( (array) $step, $n + 1, $panel['side'], $launch );
					}
					?>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
	}

	/**
	 * Tour tabs. One product: a member tab and an owner tab. Several products that name a tab: one tab per
	 * product (a product without a tab name, such as MediaShield, joins the first), then the owner tab with each
	 * product's first owner steps (two, or one past four products), so a complete-community demo reads as one story without a wall of cards.
	 *
	 * @param array<string,array{label:string,guide:array<string,mixed>}> $products Products.
	 * @return array<string,array{label:string,side:string,steps:array<int,mixed>}>
	 */
	private static function panels( array $products ): array {
		$tabbed = array_filter( $products, static fn( $p ) => ! empty( $p['guide']['tab'] ) && ! empty( $p['guide']['member'] ) );
		$multi  = count( $tabbed ) > 1;
		$first  = reset( $products );
		$out    = array();
		$owner  = array();
		foreach ( $products as $id => $p ) {
			$key = $multi && ! empty( $p['guide']['tab'] ) ? (string) $id : 'member';
			if ( 'member' === $key && ! isset( $out['member'] ) && $multi ) {
				$key = (string) array_key_first( $tabbed );
			}
			if ( ! empty( $p['guide']['member'] ) ) {
				if ( ! isset( $out[ $key ] ) ) {
					$label       = $multi ? (string) ( $products[ $key ]['guide']['tab'] ?? $p['guide']['tab'] ?? '' ) : (string) ( $first['guide']['member_label'] ?? __( 'As a member', 'wbcom-demo-installer' ) );
					$out[ $key ] = array(
						'label' => $label,
						'side'  => 'member',
						'steps' => array(),
					);
				}
				$out[ $key ]['steps'] = array_merge( $out[ $key ]['steps'], (array) $p['guide']['member'] );
			}
			$steps = (array) ( $p['guide']['owner'] ?? array() );
			$owner = array_merge( $owner, $multi ? array_slice( $steps, 0, count( $products ) > 4 ? 1 : 2 ) : $steps );
		}
		if ( $owner ) {
			$out['owner'] = array(
				'label' => __( 'As the owner', 'wbcom-demo-installer' ),
				'side'  => 'owner',
				'steps' => $owner,
			);
		}
		return $out;
	}

	/**
	 * One tour card.
	 *
	 * @param array<string,mixed> $step   Step: icon, title, text, url, go, as, public, highlight.
	 * @param int                 $n      Step number.
	 * @param string              $side   "member" or "owner".
	 * @param string              $launch Launch link for signed-out visitors.
	 */
	private static function step( array $step, int $n, string $side, string $launch ): void {
		$url = (string) ( $step['url'] ?? '' );
		if ( ! is_user_logged_in() ) {
			$url = ( 'member' === $side && ! empty( $step['public'] ) ) ? $url : $launch;
		} elseif ( '' !== $url && ( 'owner' === $side || ! empty( $step['as'] ) ) ) {
			// A member step without an account (a public page) opens as whoever is viewing.
			$url = self::link( $url, 'owner' === $side ? 0 : (int) $step['as'] );
		}
		$class = 'wdi-tour__step' . ( empty( $step['highlight'] ) ? '' : ' is-key' );
		// Concatenated, never printf: smart links carry percent-encoded URLs.
		echo '' !== $url ? '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' : '<div class="' . esc_attr( $class ) . '">';
		$close = '' !== $url ? '</a>' : '</div>';
		?>
		<span class="wdi-tour__top">
			<span class="wdi-tour__icon"><?php echo Icons::svg( (string) ( $step['icon'] ?? 'arrow' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?></span>
			<?php /* translators: %d: step number */ ?>
			<span class="wdi-tour__n"><?php echo esc_html( sprintf( __( 'Step %d', 'wbcom-demo-installer' ), $n ) ); ?></span>
		</span>
		<strong class="wdi-tour__title"><?php echo esc_html( (string) ( $step['title'] ?? '' ) ); ?></strong>
		<span class="wdi-tour__text"><?php echo esc_html( (string) ( $step['text'] ?? '' ) ); ?></span>
		<?php if ( '' !== $url ) : ?>
			<span class="wdi-tour__go"><?php echo esc_html( (string) ( $step['go'] ?? __( 'Open', 'wbcom-demo-installer' ) ) ); ?><?php echo Icons::svg( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?></span>
		<?php endif; ?>
		<?php
		echo $close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup.
	}

	/**
	 * What comes with the product: the highlights each guide lists, with line icons.
	 *
	 * @param array<string,array{label:string,guide:array<string,mixed>}> $products Products.
	 * @param string                                                      $name     Product name for the heading.
	 */
	private static function highlights( array $products, string $name ): void {
		$items = array();
		$multi = count( $products ) > 2;
		foreach ( $products as $p ) {
			$list  = (array) ( $p['guide']['highlights'] ?? array() );
			$items = array_merge( $items, $multi ? array_slice( $list, 0, 2 ) : $list );
		}
		if ( ! $items ) {
			return;
		}
		?>
		<section class="wdi-tour__section">
			<?php if ( $multi ) : ?>
				<h2><?php esc_html_e( 'Everything in this demo', 'wbcom-demo-installer' ); ?></h2>
				<p class="wdi-tour__lede"><?php esc_html_e( 'Each part is its own plugin, and they work together on one site.', 'wbcom-demo-installer' ); ?></p>
			<?php else : ?>
				<?php /* translators: %s: product name */ ?>
				<h2><?php echo esc_html( sprintf( __( 'What comes with %s', 'wbcom-demo-installer' ), $name ) ); ?></h2>
				<p class="wdi-tour__lede"><?php esc_html_e( 'Everything you clicked through above ships in the plugin.', 'wbcom-demo-installer' ); ?></p>
			<?php endif; ?>
			<div class="wdi-tour__feats">
				<?php foreach ( array_slice( $items, 0, 12 ) as $item ) : ?>
					<div class="wdi-tour__feat">
						<span class="wdi-tour__icon"><?php echo Icons::svg( (string) ( $item['icon'] ?? 'tick' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?></span>
						<div><strong><?php echo esc_html( (string) $item['title'] ); ?></strong><span><?php echo esc_html( (string) ( $item['text'] ?? '' ) ); ?></span></div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}
}
