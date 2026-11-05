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
