<?php
/**
 * Content packs: the sample text of each product rewritten for the site the owner chose, before the build uses it.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Ai;

use WbcomDemo\Core\Provider;
use WbcomDemo\Core\Registry;
use WbcomDemo\Core\State;

defined( 'ABSPATH' ) || exit;

/**
 * The AI never sees or changes the shape of the content, only its human text.
 *
 * Each distinct text value of content/<id>.php is sent once with an id and comes back rewritten; the translation map
 * (original text => new text) is kept in the build state and applied to every place that value appears, so a text
 * used as a reference elsewhere stays consistent. Slugs, keys, numbers, enums, paths, times, links and a provider's
 * keep list never leave the server. A pack that fails the checks is dropped and the curated text is used.
 */
final class Pack {

	/**
	 * Characters of source text per AI request: one request must finish well inside one build step.
	 */
	private const CHUNK = 4500;

	/**
	 * Keys whose values are never rewritten, in any content file.
	 */
	private const SKIP_KEYS = array( 'slug', 'image', 'file', 'login', 'type', 'icon', 'difficulty', 'roles', 'parent', 'space', 'category', 'url', 'site' );

	/**
	 * Translation maps by provider id for this request (null until read from the state).
	 *
	 * @var array<string,array<string,string>>|null
	 */
	private static ?array $maps = null;

	/**
	 * True while reading the curated content, so the pack is not applied to itself.
	 *
	 * @var bool
	 */
	private static bool $raw = false;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'wbcom_demo_content', array( self::class, 'apply' ), 5, 2 );
	}

	/**
	 * Use the pack's text in place of the curated text (wbcom_demo_content filter).
	 *
	 * @param array<int|string,mixed> $content Content.
	 * @param string                  $id      Provider id.
	 * @return array<int|string,mixed>
	 */
	public static function apply( array $content, string $id ): array {
		if ( self::$raw ) {
			return $content;
		}
		if ( null === self::$maps ) {
			$state      = new State();
			self::$maps = State::BUILDING === $state->get( 'status' ) ? (array) $state->get( 'ai_maps' ) : array();
		}
		$map = (array) ( self::$maps[ $id ] ?? array() );
		return $map ? self::swap( $content, $map ) : $content;
	}

	/**
	 * How many AI requests a provider's text needs (0 when it has none).
	 *
	 * @param string $id Provider id.
	 */
	public static function chunks( string $id ): int {
		return count( self::split( self::texts( $id ) ) );
	}

	/**
	 * Write one chunk of a provider's text for the brief, check it and add it to the build's translation map.
	 *
	 * @param Provider $provider Provider.
	 * @param int      $chunk    Chunk number.
	 * @param State    $state    Build state.
	 * @return string Empty when the chunk was used, else why it was not (for the build's warnings).
	 */
	public static function write( Provider $provider, int $chunk, State $state ): string {
		$parts = self::split( self::texts( $provider->id() ) );
		$part  = $parts[ $chunk ] ?? array();
		if ( ! $part ) {
			return '';
		}
		$brief = (array) $state->get( 'brief' );
		$items = array();
		foreach ( $part as $n => $item ) {
			$items[] = array_filter(
				array(
					'id'    => 't' . $n,
					'text'  => $item['text'],
					'photo' => $item['photo'],
				)
			);
		}
		$answer = Assistant::json( self::system( $brief ), self::prompt( $provider, $brief, $items ), self::schema(), 8000 );
		if ( is_wp_error( $answer ) ) {
			return $answer->get_error_message();
		}
		$map = self::check( $part, $answer );
		if ( count( $map ) < 0.6 * count( $part ) ) {
			return __( 'the AI text did not pass the check, so the sample text was used', 'wbcom-demo-installer' );
		}
		$maps                    = (array) $state->get( 'ai_maps' );
		$maps[ $provider->id() ] = array_merge( (array) ( $maps[ $provider->id() ] ?? array() ), $map );
		$state->set( 'ai_maps', $maps );
		self::$maps = $maps;
		$provider->forget_content(); // WP-CLI runs every step in one process: the next read must see the pack.
		return '';
	}

	/**
	 * Every distinct human text in a provider's curated content, in a stable order, each with the photo shown
	 * beside it (so a caption still describes its picture).
	 *
	 * @param string $id Provider id.
	 * @return array<int,array{text:string,photo:string}>
	 */
	public static function texts( string $id ): array {
		$provider = Registry::get( $id );
		if ( ! $provider ) {
			return array();
		}
		$file = WBCOM_DEMO_DIR . 'content/' . $id . '.php';
		if ( ! is_readable( $file ) ) {
			return array();
		}
		self::$raw = true;
		/** This filter is documented in src/Core/Provider.php */
		$content   = (array) apply_filters( 'wbcom_demo_content', (array) require $file, $id );
		self::$raw = false;
		$skip      = array_merge( self::SKIP_KEYS, $provider->ai_skip_keys() );
		$keep      = array_flip( $provider->ai_keep() );
		$found     = array();
		self::collect( $content, $skip, $keep, '', $found );
		return array_values( $found );
	}

	/**
	 * Walk the content and collect human text.
	 *
	 * @param mixed                                         $node  Value.
	 * @param string[]                                      $skip  Keys never rewritten.
	 * @param array<string,int>                             $keep  Values never rewritten.
	 * @param string                                        $photo Photo of the row this value sits in.
	 * @param array<string,array{text:string,photo:string}> $found Text => item.
	 */
	private static function collect( $node, array $skip, array $keep, string $photo, array &$found ): void {
		if ( is_string( $node ) ) {
			if ( ! isset( $found[ $node ] ) && ! isset( $keep[ $node ] ) && self::is_text( $node ) ) {
				$found[ $node ] = array(
					'text'  => $node,
					'photo' => $photo,
				);
			}
			return;
		}
		if ( ! is_array( $node ) ) {
			return;
		}
		// The row's photo, if it has one, travels with its texts.
		foreach ( $node as $value ) {
			if ( is_string( $value ) && self::is_photo( $value ) ) {
				$photo = basename( $value );
				break;
			}
		}
		foreach ( $node as $key => $value ) {
			if ( is_string( $key ) && in_array( $key, $skip, true ) ) {
				continue;
			}
			self::collect( $value, $skip, $keep, $photo, $found );
		}
	}

	/**
	 * Human text, not a slug, enum, id, path, link, time, number or code.
	 *
	 * @param string $s Value.
	 */
	private static function is_text( string $s ): bool {
		$s = trim( $s );
		// Links, whole email addresses and example domains stay; a mention token like {@3} inside text is fine.
		if ( mb_strlen( $s ) < 2 || self::is_photo( $s ) || preg_match( '#^(https?:|www\.|mailto:)|^[^\s@]+@[^\s@]+\.[a-z]{2,}$|\.example\b#i', $s ) ) {
			return false;
		}
		if ( preg_match( '/^[\d\s:.,$€£¥₹+\-\/%]+$/u', $s ) ) {
			return false; // Times, sizes, prices, numbers.
		}
		if ( ! preg_match( '/\s/u', $s ) ) {
			// One word: only an ordinary capitalised word ("Design", "Books"); slugs, codes and ids stay.
			return (bool) preg_match( '/^\p{Lu}\p{Ll}+$/u', $s );
		}
		return true;
	}

	/**
	 * A media library path or file name.
	 *
	 * @param string $s Value.
	 */
	private static function is_photo( string $s ): bool {
		return (bool) preg_match( '/\.(jpe?g|png|webp|gif)$/i', $s );
	}

	/**
	 * Group texts into requests of about CHUNK characters.
	 *
	 * @param array<int,array{text:string,photo:string}> $texts Texts.
	 * @return array<int,array<int,array{text:string,photo:string}>> Chunks, each keyed by the text's index.
	 */
	private static function split( array $texts ): array {
		$parts = array();
		$n     = 0;
		$size  = 0;
		foreach ( $texts as $i => $item ) {
			$len = mb_strlen( $item['text'] );
			if ( $size && $size + $len > self::CHUNK ) {
				++$n;
				$size = 0;
			}
			$parts[ $n ][ $i ] = $item;
			$size             += $len;
		}
		return $parts;
	}

	/**
	 * Replace every string the map knows, keeping everything else.
	 *
	 * @param mixed                $node Value.
	 * @param array<string,string> $map  Original => new.
	 * @return mixed
	 */
	private static function swap( $node, array $map ) {
		if ( is_string( $node ) ) {
			return $map[ $node ] ?? $node;
		}
		if ( is_array( $node ) ) {
			foreach ( $node as $k => $v ) {
				$node[ $k ] = self::swap( $v, $map );
			}
		}
		return $node;
	}

	/**
	 * Keep each rewritten text that is safe and still fits its place.
	 *
	 * @param array<int,array{text:string,photo:string}> $part   Texts sent, by index.
	 * @param array<string,mixed>                        $answer Decoded answer.
	 * @return array<string,string> Original => new.
	 */
	public static function check( array $part, array $answer ): array {
		$back = array();
		foreach ( (array) ( $answer['items'] ?? array() ) as $row ) {
			if ( is_array( $row ) && isset( $row['id'], $row['text'] ) && is_string( $row['text'] ) ) {
				$back[ (string) $row['id'] ] = $row['text'];
			}
		}
		$map = array();
		foreach ( $part as $n => $item ) {
			$new = $back[ 't' . $n ] ?? null;
			if ( null === $new ) {
				continue;
			}
			$old = $item['text'];
			// Plain text only (content is created as the admin, who may post HTML), line breaks kept.
			$new = trim( implode( "\n", array_map( 'sanitize_text_field', explode( "\n", wp_strip_all_tags( $new ) ) ) ) );
			$max = max( 40, (int) ( mb_strlen( $old ) * 2.5 ) );
			if ( '' === $new || mb_strlen( $new ) > min( $max, 3000 ) ) {
				continue;
			}
			// Mention tokens ({@3}) and printf placeholders (%s, %1$s) must survive exactly.
			if ( self::tokens( $old ) !== self::tokens( $new ) ) {
				continue;
			}
			// Hashtags only where the original had them: each one becomes a site-wide tag the build must record.
			if ( ! preg_match( '/(^|\s)#\p{L}/u', $old ) && preg_match( '/(^|\s)#\p{L}/u', $new ) ) {
				continue;
			}
			$map[ $old ] = $new;
		}
		return $map;
	}

	/**
	 * Tokens a text must keep, sorted.
	 *
	 * @param string $s Text.
	 * @return string[]
	 */
	private static function tokens( string $s ): array {
		preg_match_all( '/\{@\d+\}|%(?:\d+\$)?[sd]/', $s, $m );
		$t = $m[0];
		sort( $t );
		return $t;
	}

	/**
	 * Instructions.
	 *
	 * @param array<string,mixed> $brief The idea the owner picked.
	 */
	private static function system( array $brief ): string {
		return 'You rewrite the sample content of a demo website so it fits one specific site. You receive short texts '
			. 'with ids: names, titles, descriptions, posts, replies and bios. Return every id with its new text. Keep each '
			. 'text\'s role and length close to the original (a title stays a short title, a reply stays a short reply, a '
			. 'one-word category stays one or two words). Keep every token like {@3}, %s or %1$s exactly as it appears, '
			. 'in the same text. Add hashtags only to texts that already have hashtags. When an item has a photo, the new text must still fit what that photo shows (the file '
			. 'name describes it). Write as the real members of this site would: specific, warm, believable, varied voices, '
			. 'no marketing tone, no emoji, no links, no prices or numbers that were not in the original, no real people\'s '
			. 'names. Plain text only, no HTML or Markdown. Write in ' . ( (string) ( $brief['language'] ?? 'English' ) ) . '.';
	}

	/**
	 * The request.
	 *
	 * @param Provider                        $provider Provider.
	 * @param array<string,mixed>             $brief    Brief.
	 * @param array<int,array<string,string>> $items    Texts with ids.
	 */
	private static function prompt( Provider $provider, array $brief, array $items ): string {
		return sprintf(
			"The site: %s, \"%s\".\nAbout it: %s\nWho it is for: %s\nTone: %s\nWhat it has: %s\n\nThese texts are the sample content for %s (%s). Rewrite each one for this site.\n\n%s",
			(string) ( $brief['name'] ?? '' ),
			(string) ( $brief['tagline'] ?? '' ),
			(string) ( $brief['description'] ?? '' ),
			(string) ( $brief['audience'] ?? '' ),
			(string) ( $brief['tone'] ?? '' ),
			implode( '; ', (array) ( $brief['highlights'] ?? array() ) ),
			$provider->label(),
			wp_strip_all_tags( $provider->summary() ),
			(string) wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
		);
	}

	/**
	 * Answer schema (portable keywords only, see Ideas::schema()).
	 *
	 * @return array<string,mixed>
	 */
	private static function schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'items' ),
			'properties'           => array(
				'items' => array(
					'type'        => 'array',
					'description' => 'Every id that was sent, each once.',
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'id', 'text' ),
						'properties'           => array(
							'id'   => array( 'type' => 'string' ),
							'text' => array( 'type' => 'string' ),
						),
					),
				),
			),
		);
	}
}
