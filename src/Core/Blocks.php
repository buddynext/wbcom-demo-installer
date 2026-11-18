<?php
/**
 * Core block markup the demo writes into pages (Wbcom Essential pages, the designed home page). Each method
 * returns markup as the block editor itself saves it, so the owner can open and edit the page without
 * "This block contains unexpected content".
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Block markup helpers.
 */
final class Blocks {

	/**
	 * Escaped text that can never run as a shortcode: names and descriptions can come from members (a group's
	 * description), and the page content is rendered through do_shortcode().
	 *
	 * @param string $text Text.
	 */
	private static function text( string $text ): string {
		return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), esc_html( $text ) );
	}

	/**
	 * Core heading block.
	 *
	 * @param string $text  Text.
	 * @param int    $level Level.
	 * @param bool   $white White text (over a photo: themes that colour headings would otherwise win). An inline
	 *                      colour, not the palette's "white": BuddyX defines that slug as #ecf0f1, and a plugin that
	 *                      drops the core palette (WPMediaVerse) would leave the heading uncoloured.
	 * @param bool   $flush No top margin: the first line of a card, where themes' heading margins would pad the top.
	 */
	public static function heading( string $text, int $level = 2, bool $white = false, bool $flush = false ): string {
		$style = array_filter(
			array(
				'color'   => $white ? array( 'text' => '#ffffff' ) : null,
				'spacing' => $flush ? array( 'margin' => array( 'top' => '0' ) ) : null,
			)
		);
		$json  = array_filter(
			array(
				'level' => 2 === $level ? null : $level,
				'style' => $style ? $style : null,
			)
		);
		$css   = trim( ( $white ? 'color:#ffffff;' : '' ) . ( $flush ? 'margin-top:0' : '' ), ';' );
		return sprintf(
			'<!-- wp:heading%1$s --><h%2$d class="wp-block-heading%3$s"%4$s>%5$s</h%2$d><!-- /wp:heading -->',
			$json ? ' ' . wp_json_encode( $json ) : '',
			$level,
			$white ? ' has-text-color' : '',
			'' !== $css ? ' style="' . $css . '"' : '',
			self::text( $text )
		);
	}

	/**
	 * Core paragraph block.
	 *
	 * @param string $text Text.
	 */
	public static function paragraph( string $text ): string {
		return '<!-- wp:paragraph --><p>' . self::text( $text ) . '</p><!-- /wp:paragraph -->';
	}

	/**
	 * Core image block, a wide (21:9) banner crop.
	 *
	 * @param int $id Attachment id.
	 */
	public static function image( int $id ): string {
		return sprintf(
			// A wide crop (21:9) keeps the page photo a banner, not a screenful, on any theme width.
			'<!-- wp:image {"id":%1$d,"aspectRatio":"21/9","scale":"cover","sizeSlug":"full","linkDestination":"none"} --><figure class="wp-block-image size-full"><img src="%2$s" alt="%3$s" class="wp-image-%1$d" style="aspect-ratio:21/9;object-fit:cover"/></figure><!-- /wp:image -->',
			$id,
			esc_url( (string) wp_get_attachment_image_url( $id, 'full' ) ),
			esc_attr( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) )
		);
	}

	/**
	 * Core columns block, one column per inner markup.
	 *
	 * @param string[] $inner Inner blocks per column.
	 * @param bool     $stack Stack the columns on phones (false keeps them side by side, for small items).
	 */
	public static function columns( array $inner, bool $stack = true ): string {
		$cols = array_map( static fn( string $c ): string => '<!-- wp:column --><div class="wp-block-column">' . $c . '</div><!-- /wp:column -->', $inner );
		return ( $stack ? '<!-- wp:columns --><div class="wp-block-columns">' : '<!-- wp:columns {"isStackedOnMobile":false} --><div class="wp-block-columns is-not-stacked-on-mobile">' ) . implode( '', $cols ) . '</div><!-- /wp:columns -->';
	}

	/**
	 * Core group block drawn as a card: a thin border and rounded corners, no fill, so it reads on the theme's light
	 * and dark backgrounds alike (a grey at 24% opacity works on both).
	 *
	 * @param string $inner Inner blocks.
	 */
	public static function card( string $inner ): string {
		return '<!-- wp:group {"style":{"border":{"radius":"12px","width":"1px","color":"#8080803d"},"spacing":{"padding":{"top":"24px","bottom":"24px","left":"24px","right":"24px"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group has-border-color" style="border-color:#8080803d;border-width:1px;border-radius:12px;padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px">' . $inner . '</div><!-- /wp:group -->';
	}

	/**
	 * Core quote block.
	 *
	 * @param string $text Quote.
	 * @param string $who  Who said it.
	 */
	public static function quote( string $text, string $who ): string {
		return '<!-- wp:quote --><blockquote class="wp-block-quote">' . self::paragraph( $text ) . ( '' !== $who ? '<cite>' . self::text( $who ) . '</cite>' : '' ) . '</blockquote><!-- /wp:quote -->';
	}

	/**
	 * Core details block (question and answer).
	 *
	 * @param string $q Question.
	 * @param string $a Answer.
	 */
	public static function details( string $q, string $a ): string {
		return '<!-- wp:details --><details class="wp-block-details"><summary>' . self::text( $q ) . '</summary>' . self::paragraph( $a ) . '</details><!-- /wp:details -->';
	}

	/**
	 * Core buttons block with one button.
	 *
	 * @param string $text Label.
	 * @param string $url  Link.
	 */
	public static function button( string $text, string $url ): string {
		return self::buttons( array( array( $text, $url ) ) );
	}

	/**
	 * Core buttons block: the first button filled, the others outlined.
	 *
	 * @param array<int,array{0:string,1:string}> $links Label and link per button.
	 */
	public static function buttons( array $links ): string {
		$out = '';
		foreach ( array_values( $links ) as $i => $link ) {
			$outline = $i > 0;
			$out    .= ( $outline ? '<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline">' : '<!-- wp:button --><div class="wp-block-button">' )
				. '<a class="wp-block-button__link wp-element-button" href="' . esc_url( $link[1] ) . '">' . self::text( $link[0] ) . '</a></div><!-- /wp:button -->';
		}
		return '<!-- wp:buttons --><div class="wp-block-buttons">' . $out . '</div><!-- /wp:buttons -->';
	}

	/**
	 * Core shortcode block, for a product's own server-rendered output (search, listings, map).
	 *
	 * @param string $code Shortcode with its brackets.
	 */
	public static function shortcode( string $code ): string {
		return '<!-- wp:shortcode -->' . $code . '<!-- /wp:shortcode -->';
	}

	/**
	 * A server-rendered block (a product's own, or Wbcom Essential's): only its name and settings are saved, so it is
	 * valid in the editor whatever the block's code does later.
	 *
	 * @param string              $name  Block name.
	 * @param array<string,mixed> $attrs Settings.
	 */
	public static function dynamic( string $name, array $attrs = array() ): string {
		return '<!-- wp:' . $name . ( $attrs ? ' ' . wp_json_encode( $attrs ) : '' ) . ' /-->';
	}

	/**
	 * Core Query Loop of the newest posts of one type, in a grid: live, so it shows what the site has now (not the
	 * build's copy), and the owner changes count, order and look in the editor.
	 *
	 * @param string $type    Post type.
	 * @param int    $count   How many.
	 * @param bool   $image   Show the featured image.
	 * @param int    $columns Columns.
	 */
	public static function query( string $type, int $count, bool $image = true, int $columns = 3 ): string {
		$query = array(
			'queryId' => 0,
			'query'   => array(
				'perPage'  => $count,
				'pages'    => 0,
				'offset'   => 0,
				'postType' => $type,
				'order'    => 'desc',
				'orderBy'  => 'date',
				'author'   => '',
				'search'   => '',
				'exclude'  => array(),
				'sticky'   => '',
				'inherit'  => false,
			),
		);
		return '<!-- wp:query ' . wp_json_encode( $query ) . ' --><div class="wp-block-query">'
			. '<!-- wp:post-template {"layout":{"type":"grid","columnCount":' . $columns . '}} -->'
			. ( $image ? '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->' : '' )
			. '<!-- wp:post-title {"level":3,"isLink":true} /--><!-- wp:post-excerpt {"excerptLength":18} /-->'
			. '<!-- /wp:post-template --></div><!-- /wp:query -->';
	}

	/**
	 * Whether a block is registered on this site (a Wbcom Essential block when that plugin is active).
	 *
	 * @param string $name Block name.
	 */
	public static function has( string $name ): bool {
		return \WP_Block_Type_Registry::get_instance()->is_registered( $name );
	}

	/**
	 * Core group block holding one page section, spaced from the next. Spacing only: no colours, so the theme's
	 * light and dark modes both read.
	 *
	 * @param string $inner Inner blocks.
	 */
	public static function section( string $inner ): string {
		return '<!-- wp:group {"style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group" style="padding-top:48px;padding-bottom:48px">' . $inner . '</div><!-- /wp:group -->';
	}

	/**
	 * Core cover block: a photo with a dark overlay and the hero text over it, the full width of the page
	 * (BuddyX, BuddyX Pro and Reign support wide alignments; other themes keep it in their content column). The text
	 * keeps to a 1140px content width, and the next block starts 40px below it on every theme.
	 *
	 * @param int    $id    Attachment id.
	 * @param string $inner Inner blocks.
	 */
	public static function cover( int $id, string $inner ): string {
		$url = (string) wp_get_attachment_image_url( $id, 'full' );
		return sprintf(
			'<!-- wp:cover {"url":"%1$s","id":%2$d,"dimRatio":60,"minHeight":420,"isDark":true,"align":"full","style":{"color":{"text":"#ffffff"},"spacing":{"margin":{"bottom":"40px"}}},"layout":{"type":"constrained"}} --><div class="wp-block-cover alignfull is-dark has-text-color" style="color:#ffffff;margin-bottom:40px;min-height:420px"><img class="wp-block-cover__image-background wp-image-%2$d" alt="" src="%1$s" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-60 has-background-dim"></span><div class="wp-block-cover__inner-container">%3$s</div></div><!-- /wp:cover -->',
			esc_url( $url ),
			$id,
			// In a group of its own: BuddyX caps a cover's direct children at its own --content-width, which overrides
			// the cover's layout; the group's children keep to 1140px on every theme.
			'<!-- wp:group {"layout":{"type":"constrained","contentSize":"1140px"}} --><div class="wp-block-group">' . $inner . '</div><!-- /wp:group -->'
		);
	}

	/**
	 * Core heading block whose text links somewhere.
	 *
	 * @param string $text  Text.
	 * @param string $url   Link.
	 * @param int    $level Level.
	 */
	public static function link_heading( string $text, string $url, int $level = 3 ): string {
		$attrs = 2 === $level ? '' : ' {"level":' . $level . '}';
		return sprintf( '<!-- wp:heading%1$s --><h%2$d class="wp-block-heading"><a href="%3$s">%4$s</a></h%2$d><!-- /wp:heading -->', $attrs, $level, esc_url( $url ), self::text( $text ) );
	}

	/**
	 * Rows of four people: a round photo and the name, both linking to the profile.
	 *
	 * @param array<int,array{0:string,1:string,2:string}> $people Name, profile link and photo link per person.
	 */
	public static function people( array $people ): string {
		$out = '';
		foreach ( array_chunk( $people, 4 ) as $row ) {
			$cols = array();
			foreach ( $row as $p ) {
				$cols[] = '<!-- wp:image {"width":"96px","height":"96px","scale":"cover","sizeSlug":"thumbnail","linkDestination":"custom","align":"center","className":"is-style-rounded"} --><figure class="wp-block-image aligncenter size-thumbnail is-resized is-style-rounded"><a href="' . esc_url( $p[1] ) . '"><img src="' . esc_url( $p[2] ) . '" alt="' . esc_attr( $p[0] ) . '" style="object-fit:cover;width:96px;height:96px"/></a></figure><!-- /wp:image -->'
					. '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"><a href="' . esc_url( $p[1] ) . '">' . self::text( $p[0] ) . '</a></p><!-- /wp:paragraph -->';
			}
			$out .= self::columns( $cols, false ); // Four small faces fit a phone's width.
		}
		return $out;
	}
}
