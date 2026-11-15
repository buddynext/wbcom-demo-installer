<?php
/**
 * "Try the demo" page: how a visitor tries every product in the demo as a member and as the site owner.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the guide page from each built product's Provider::guide().
 *
 * The page holds one core Shortcode block, so it stays valid in the editor, and everything is rendered for the person
 * viewing it: the switch buttons depend on who is signed in, and signed-in pages are not served from a page cache.
 * Switching uses the User Switching plugin; without it the account cards show who to try but no button.
 */
final class Guide {

	public const SHORTCODE = 'wbcom_demo_guide';

	/**
	 * Option for demos built outside the installer (`wp wbcom-demo guide --products --accounts`):
	 * [ 'products' => string[], 'accounts' => [ provider id => user id[] ] ].
	 */
	public const OPTION = 'wbcom_demo_guide';

	/**
	 * Register the shortcode.
	 */
	public static function init(): void {
		add_shortcode( self::SHORTCODE, array( self::class, 'render' ) );
	}

	/**
	 * Block markup for the guide page.
	 */
	public static function page_content(): string {
		return '<!-- wp:shortcode -->[' . self::SHORTCODE . ']<!-- /wp:shortcode -->';
	}

	/**
	 * Products in this demo that offer a guide: available, and with sample content in the ledger.
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
			// Named accounts take the product's account slots in order and keep their descriptions.
			foreach ( array_values( (array) ( $extra['accounts'][ $id ] ?? array() ) ) as $i => $user_id ) {
				$slots                   = (array) ( $guide['accounts'] ?? array() );
				$desc                    = $slots ? $slots[ min( $i, count( $slots ) - 1 ) ][1] : '';
				$guide['accounts'][ $i ] = array( (int) $user_id, (string) $desc );
			}
			if ( $guide ) {
				$out[ $id ] = array(
					'label' => $p->label(),
					'guide' => $guide,
				);
			}
		}
		return $out;
	}

	/**
	 * Shortcode output.
	 */
	public static function render(): string {
		$products = self::products();
		if ( ! $products ) {
			return '';
		}
		$here = (string) get_permalink();
		ob_start();
		?>
		<div class="wdi-guide">
			<style>
				.wdi-guide{display:grid;gap:2.5rem;margin-block:1rem 3rem}
				.wdi-guide h2{margin:0 0 1rem}
				.wdi-guide h3{margin:0 0 .75rem;font-size:1.05em}
				.wdi-guide__note{margin:0;padding:.85rem 1rem;border-radius:10px;background:color-mix(in srgb,currentColor 6%,transparent)}
				.wdi-guide__cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,250px),1fr));gap:1rem}
				.wdi-guide__card{display:flex;flex-direction:column;gap:.85rem;padding:1.25rem;border:1px solid color-mix(in srgb,currentColor 14%,transparent);border-radius:14px}
				.wdi-guide__card.is-current{border-color:currentColor}
				.wdi-guide__who{display:flex;align-items:center;gap:.75rem}
				.wdi-guide__who img{border-radius:50%;flex:none}
				.wdi-guide__who strong{display:block}
				.wdi-guide__who span,.wdi-guide__desc{font-size:.9em;opacity:.8}
				.wdi-guide__desc{margin:0;flex:1}
				.wdi-guide__card .wp-block-button__link{display:inline-block;text-decoration:none}
				.wdi-guide__cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:2rem}
				.wdi-guide__product + .wdi-guide__product{margin-block-start:1.5rem}
				.wdi-guide ol{margin:0;padding-inline-start:1.4rem;display:grid;gap:.6rem}
				.wdi-guide li a{text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:3px}
				.wdi-guide li a::after{content:" \2192";text-decoration:none;display:inline-block;margin-inline-start:.25em}
				[dir=rtl] .wdi-guide li a::after{content:" \2190"}
			</style>
			<p class="wdi-guide__note"><?php esc_html_e( 'Everything here is sample content made for this demo. Try it from both sides: as a member on the site, and as the site owner in the dashboard. Nothing you do costs real money.', 'wbcom-demo-installer' ); ?></p>
			<?php self::accounts( $products, $here ); ?>
			<div class="wdi-guide__cols">
				<?php
				self::steps( $products, 'member', __( 'Try it as a member', 'wbcom-demo-installer' ) );
				self::steps( $products, 'owner', __( 'Run it as the site owner', 'wbcom-demo-installer' ) );
				?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * "Who you can be": the site owner plus each product's sample accounts, with a switch button.
	 *
	 * @param array<string,array{label:string,guide:array<string,mixed>}> $products Products.
	 * @param string                                                      $here     Guide page URL.
	 */
	private static function accounts( array $products, string $here ): void {
		$switching = class_exists( 'user_switching' );
		$old       = $switching ? \user_switching::get_old_user() : false;
		$me        = wp_get_current_user();
		$owner     = $old ? $old : ( current_user_can( 'manage_options' ) ? $me : null );
		$cards     = array();
		if ( $owner ) {
			$cards[ $owner->ID ] = __( 'The site owner: runs the site from the dashboard, with every setting and report.', 'wbcom-demo-installer' );
		}
		foreach ( $products as $p ) {
			foreach ( (array) ( $p['guide']['accounts'] ?? array() ) as $a ) {
				if ( (int) $a[0] && ! isset( $cards[ (int) $a[0] ] ) ) {
					$cards[ (int) $a[0] ] = (string) $a[1];
				}
			}
		}
		?>
		<section>
			<h2><?php esc_html_e( 'Who you can be', 'wbcom-demo-installer' ); ?></h2>
			<?php if ( ! is_user_logged_in() ) : ?>
				<p class="wdi-guide__note"><?php esc_html_e( 'Sign in as the site owner first (Magic Login on your launch screen), then come back here to switch between these accounts with one click.', 'wbcom-demo-installer' ); ?></p>
			<?php endif; ?>
			<div class="wdi-guide__cards">
				<?php
				foreach ( $cards as $user_id => $desc ) :
					$user = get_userdata( (int) $user_id );
					if ( ! $user ) {
						continue;
					}
					$current = (int) $me->ID === (int) $user->ID;
					$label   = '';
					$url     = '';
					if ( $current ) {
						$url = ''; // The account in use needs no button.
					} elseif ( $old && (int) $old->ID === (int) $user->ID ) {
						$url = (string) \user_switching::switch_back_url( $old );
						/* translators: %s: person's name */
						$label = sprintf( __( 'Switch back to %s', 'wbcom-demo-installer' ), $user->display_name );
					} elseif ( $switching && is_user_logged_in() ) {
						$url = (string) \user_switching::maybe_switch_url( $user );
						/* translators: %s: person's first name */
						$label = sprintf( __( 'Switch to %s', 'wbcom-demo-installer' ), strtok( $user->display_name, ' ' ) );
					}
					?>
					<div class="wdi-guide__card<?php echo $current ? ' is-current' : ''; ?>">
						<div class="wdi-guide__who">
							<?php echo get_avatar( $user->ID, 48, '', '' ); ?>
							<div>
								<strong><?php echo esc_html( $user->display_name ); ?></strong>
								<?php if ( $current ) : ?>
									<span><?php esc_html_e( 'You are signed in as this account', 'wbcom-demo-installer' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<p class="wdi-guide__desc"><?php echo esc_html( $desc ); ?></p>
						<?php if ( ! $current && ! $url && $old ) : ?>
							<p class="wdi-guide__desc"><em><?php esc_html_e( 'Switch back to the site owner first, then switch to this account.', 'wbcom-demo-installer' ); ?></em></p>
						<?php endif; ?>
						<?php if ( $url && $label ) : ?>
							<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( $here ), $url ) ); ?>"><?php echo esc_html( $label ); ?></a></div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * One column of numbered steps, grouped by product when the demo has more than one.
	 *
	 * @param array<string,array{label:string,guide:array<string,mixed>}> $products Products.
	 * @param string                                                      $side     "member" or "owner".
	 * @param string                                                      $title    Column heading.
	 */
	private static function steps( array $products, string $side, string $title ): void {
		$groups = array_filter( $products, static fn( $p ) => ! empty( $p['guide'][ $side ] ) );
		if ( ! $groups ) {
			return;
		}
		// Owner screens only open for the site owner; a switched member sees the steps and how to get back.
		$linked = 'owner' !== $side || current_user_can( 'manage_options' );
		?>
		<section>
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( ! $linked ) : ?>
				<p class="wdi-guide__note"><?php esc_html_e( 'Switch back to the site owner above to open these screens.', 'wbcom-demo-installer' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $groups as $p ) : ?>
				<div class="wdi-guide__product">
					<?php if ( count( $groups ) > 1 ) : ?>
						<h3><?php echo esc_html( $p['label'] ); ?></h3>
					<?php endif; ?>
					<ol>
						<?php foreach ( $p['guide'][ $side ] as $step ) : ?>
							<li>
								<?php if ( $linked && '' !== (string) $step[1] ) : ?>
									<a href="<?php echo esc_url( (string) $step[1] ); ?>"><?php echo esc_html( (string) $step[0] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( (string) $step[0] ); ?>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
	}
}
