<?php
/**
 * Base class every provider extends: one product's sample content.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Provider contract.
 *
 * A provider creates its product's content only through that product's public services,
 * asks the roster for people and the timeline for dates, records every object it creates,
 * and removes objects through the product's own delete functions.
 */
abstract class Provider {

	/**
	 * Loaded content, see content().
	 *
	 * @var array<int|string,mixed>|null
	 */
	private ?array $content = null;

	/**
	 * This provider's sample content: content/<id>.php, through the wbcom_demo_content filter so an add-on or a
	 * generated content pack can replace or extend it without touching the provider.
	 *
	 * @return array<int|string,mixed>
	 */
	protected function content(): array {
		if ( null === $this->content ) {
			$file = WBCOM_DEMO_DIR . 'content/' . $this->id() . '.php';
			$data = is_readable( $file ) ? require $file : array();
			/**
			 * Sample content for one provider.
			 *
			 * @param array  $data Content, in the shape of content/<id>.php.
			 * @param string $id   Provider id.
			 */
			$this->content = (array) apply_filters( 'wbcom_demo_content', (array) $data, $this->id() );
		}
		return $this->content;
	}

	/** Bump when the contract changes; the registry skips providers on another version. */
	public const CONTRACT = 1;

	/**
	 * Unique id, e.g. "buddynext".
	 */
	abstract public function id(): string;

	/**
	 * Product name shown to the owner.
	 */
	abstract public function label(): string;

	/**
	 * Whether the product is active.
	 */
	abstract public function available(): bool;

	/**
	 * Active product version, '' when unknown.
	 */
	public function version(): string {
		return '';
	}

	/**
	 * Lowest product version this provider was tested with. Older versions are not offered.
	 */
	public function min_version(): string {
		return '';
	}

	/**
	 * Whether the active version is supported.
	 */
	public function supported(): bool {
		$min = $this->min_version();
		$ok  = '' === $min || '' === $this->version() || version_compare( $this->version(), $min, '>=' );
		/**
		 * Whether a provider may run on the active product version.
		 *
		 * @param bool     $ok       Supported.
		 * @param Provider $provider Provider.
		 */
		return (bool) apply_filters( 'wbcom_demo_provider_supported', $ok, $this );
	}

	/**
	 * Lucide icon id for the wizard.
	 */
	public function icon(): string {
		return 'plug';
	}

	/**
	 * One line: what the owner gets.
	 */
	abstract public function summary(): string;

	/**
	 * Label of the Pro extras when the Pro add-on is active, '' otherwise.
	 */
	public function pro_label(): string {
		return '';
	}

	/**
	 * Build order. Lower runs first; finish runs in reverse.
	 */
	public function order(): int {
		return 50;
	}

	/**
	 * What a build creates, for the review screen and Finish choices.
	 *
	 * @return array<string,array{0:int,1:string,2:string}> object_type => [count, kind, label]
	 */
	abstract public function plan(): array;

	/**
	 * Ordered step keys. Each step must finish well inside one request.
	 *
	 * @param Context $ctx Build context.
	 * @return string[]
	 */
	abstract public function steps( Context $ctx ): array;

	/**
	 * Run one step.
	 *
	 * @param string  $step Step key.
	 * @param Context $ctx  Build context.
	 */
	abstract public function run( string $step, Context $ctx ): void;

	/**
	 * Remove one recorded object through the product's own delete function.
	 *
	 * @param object $row Ledger row (object_type, object_id, meta).
	 * @return bool True when removed or already gone.
	 */
	abstract public function remove( object $row ): bool;

	/**
	 * Pages and menu items this product offers to the theme adapter.
	 *
	 * @return array<int,array{title:string,url?:string,page_id?:int}>
	 */
	public function menu_items(): array {
		return array();
	}

	/**
	 * Page id this product would make a good front page, 0 for none.
	 */
	public function front_page(): int {
		return 0;
	}

	/**
	 * Optional verify issues after a finish (orphans the product can detect).
	 *
	 * @return string[]
	 */
	public function verify(): array {
		return array();
	}

	/**
	 * Hand kept items over to the site owner before the sample people who own them are removed
	 * (for example: a kept space is owned by a sample person).
	 *
	 * @param string[] $kept     Kept "provider:type" keys.
	 * @param int      $owner_id Site owner who runs Finish.
	 */
	public function handover( array $kept, int $owner_id ): void {}

	/**
	 * Records the product keeps about removed people on its own (logs, transactions), named in the finish
	 * report. The installer never deletes product rows directly; empty when the product keeps none.
	 */
	public function keeps_logs(): string {
		return '';
	}

	/**
	 * Settings that present this product at its best in a demo, applied before its content.
	 * Option name => value; an array value is merged into an array option; "transient:<name>" => null
	 * clears a one-time activation redirect. "Keep my setup" keeps
	 * them, "Remove everything" restores the values the owner has not changed since.
	 *
	 * @return array<string,mixed>
	 */
	public function settings(): array {
		return array();
	}

	/**
	 * One-time redirects the product sets when it is activated (to its own setup wizard), as transient names,
	 * or "option:<name>" for one kept in an option. Cleared when the owner opens this wizard, and again on build.
	 *
	 * @return string[]
	 */
	public function activation_redirects(): array {
		return array();
	}

	/**
	 * Clear this product's one-time activation redirects.
	 */
	public function clear_redirects(): void {
		foreach ( $this->activation_redirects() as $key ) {
			if ( str_starts_with( $key, 'option:' ) ) {
				delete_option( substr( $key, 7 ) );
			} else {
				delete_transient( $key );
			}
		}
	}

	/**
	 * A term's id, created and recorded when the site does not have it yet (an existing term is used as is and
	 * never recorded, so Finish leaves it).
	 *
	 * @param string  $name     Term name.
	 * @param string  $taxonomy Taxonomy.
	 * @param Context $ctx      Context.
	 * @param string  $slug     Slug to look up and create with; the name when empty.
	 * @param string  $type     Ledger object type.
	 * @return int Term id, 0 when it could not be created.
	 */
	protected function ensure_term( string $name, string $taxonomy, Context $ctx, string $slug = '', string $type = 'term' ): int {
		$found = term_exists( '' !== $slug ? $slug : $name, $taxonomy );
		if ( is_array( $found ) ) {
			return (int) $found['term_id'];
		}
		$new = wp_insert_term( $name, $taxonomy, '' !== $slug ? array( 'slug' => $slug ) : array() );
		if ( is_wp_error( $new ) ) {
			return 0;
		}
		$ctx->record( $this->id(), $type, (int) $new['term_id'], 'setup', array( 'taxonomy' => $taxonomy ) );
		return (int) $new['term_id'];
	}

	/**
	 * Remove a recorded term, but only while no content uses it (the owner may have used it since).
	 *
	 * @param int    $id       Term id.
	 * @param string $taxonomy Taxonomy.
	 */
	protected static function remove_unused_term( int $id, string $taxonomy ): bool {
		$term = '' !== $taxonomy ? get_term( $id, $taxonomy ) : null;
		if ( $term instanceof \WP_Term && 0 === (int) $term->count ) {
			wp_delete_term( $id, $taxonomy );
			clean_term_cache( $id, $taxonomy );
			return ! term_exists( $id, $taxonomy );
		}
		// Gone already, or in use by the owner's content since the build: either way not ours to delete.
		return true;
	}

	/**
	 * Delete a post or attachment the demo made; true only when it is gone afterwards.
	 *
	 * @param int  $id         Post id.
	 * @param bool $attachment Delete as an attachment (files too).
	 */
	protected static function delete_post( int $id, bool $attachment = false ): bool {
		if ( ! get_post( $id ) ) {
			return true;
		}
		$attachment ? wp_delete_attachment( $id, true ) : wp_delete_post( $id, true );
		clean_post_cache( $id );
		return false === get_post_status( $id ); // Read again after the delete.
	}

	/**
	 * How many of these terms a build would create: the ones not on the site yet. Builds create a term only
	 * when it is missing, so Review counts the same way.
	 *
	 * @param array<string,string[]> $terms Taxonomy => term names.
	 */
	protected static function missing_terms( array $terms ): int {
		$n = 0;
		foreach ( $terms as $taxonomy => $names ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			foreach ( array_unique( $names ) as $name ) {
				$n += term_exists( $name, $taxonomy ) ? 0 : 1;
			}
		}
		return $n;
	}

	/**
	 * Object types to remove only after everything else (e.g. a tag registry that counts posts).
	 *
	 * @return string[]
	 */
	public function late_types(): array {
		return array();
	}

	/**
	 * Called once at the start of every build/finish request, before steps run.
	 * Providers attach capture listeners here.
	 *
	 * @param Context $ctx Context.
	 */
	public function boot( Context $ctx ): void {}

	/**
	 * Labels for object types (Finish screen).
	 *
	 * @return array<string,string>
	 */
	public function type_labels(): array {
		$labels = array();
		foreach ( $this->plan() as $type => $row ) {
			$labels[ $type ] = $row[2];
		}
		return $labels;
	}
}
