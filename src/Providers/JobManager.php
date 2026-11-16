<?php
/**
 * WP Job Manager: job listings from sample companies, with logos, job types, locations, salaries and a few
 * featured and remote roles, posted by the sample people who run the companies. Replaces the guide-only
 * integration of the same id and keeps its "Try the demo" text.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Blocks;
use WbcomDemo\Core\Context;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * WP Job Manager provider. A listing is a job_listing post with the meta fields WP Job Manager's own submit
 * form saves, so lists, filters and single pages show them as any posted job.
 */
final class JobManager extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'jobmanager';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'WP Job Manager';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return class_exists( 'WP_Job_Manager' ) && post_type_exists( 'job_listing' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'JOB_MANAGER_VERSION' ) ? (string) JOB_MANAGER_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '1.40.0';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'search';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 51; // Next to WP Career Board.
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		$c = $this->content();
		return sprintf(
			/* translators: 1: jobs, 2: companies */
			__( '%1$d job listings from %2$d companies, with logos, salaries and remote roles', 'wbcom-demo-installer' ),
			count( $c['jobs'] ),
			count( $c['companies'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$c     = $this->content();
		$plan  = array(
			'job'        => array( count( $c['jobs'] ), 'sample', __( 'job listings', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $c['companies'] ), 'sample', __( 'company logos', 'wbcom-demo-installer' ) ),
		);
		$types = self::missing_terms( array( 'job_listing_type' => array_values( array_unique( $this->types() ) ) ) );
		if ( $types ) {
			$plan['term'] = array( $types, 'setup', __( 'job types', 'wbcom-demo-installer' ) );
		}
		if ( ! $this->jobs_page() ) {
			$plan['page'] = array( 1, 'setup', __( 'Jobs page', 'wbcom-demo-installer' ) );
		}
		return $plan;
	}

	/**
	 * The WP Job Manager job type for each job in the content.
	 *
	 * @return string[]
	 */
	private function types(): array {
		$c   = $this->content();
		$out = array();
		foreach ( $c['jobs'] as $job ) {
			$out[] = $c['types'][ $job[3] ] ?? 'Full Time';
		}
		return $out;
	}

	/**
	 * The page WP Job Manager lists jobs on, if it has one.
	 */
	private function jobs_page(): int {
		$id = (int) get_option( 'job_manager_jobs_page_id' );
		return $id && 'publish' === get_post_status( $id ) ? $id : 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'setup', 'jobs' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $step See parent.
	 * @param Context $ctx  See parent.
	 */
	public function run( string $step, Context $ctx ): void {
		if ( 'setup' === $step ) {
			$this->setup( $ctx );
		} elseif ( 'jobs' === $step ) {
			$this->jobs( $ctx );
		}
	}

	/**
	 * Job types and, when the setup wizard was skipped, the Jobs page WP Job Manager lists jobs on.
	 *
	 * @param Context $ctx Context.
	 */
	private function setup( Context $ctx ): void {
		$map = array();
		foreach ( array_unique( $this->types() ) as $type ) {
			$map[ $type ] = $this->ensure_term( $type, 'job_listing_type', $ctx );
		}
		$ctx->set( $this->id(), 'types', $map );
		if ( $this->jobs_page() ) {
			return;
		}
		$page = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Jobs', 'wbcom-demo-installer' ),
				'post_content' => '<!-- wp:shortcode -->[jobs]<!-- /wp:shortcode -->',
			),
			true
		);
		if ( ! is_wp_error( $page ) ) {
			update_option( 'job_manager_jobs_page_id', (int) $page );
			$ctx->record( $this->id(), 'page', (int) $page, 'setup' );
		}
	}

	/**
	 * The job listings, posted by the people who run the companies.
	 *
	 * @param Context $ctx Context.
	 */
	private function jobs( Context $ctx ): void {
		$c     = $this->content();
		$types = (array) $ctx->get( $this->id(), 'types', array() );
		$job_t = $this->types();
		$logos = array();
		$first = array();
		foreach ( $c['jobs'] as $i => $job ) {
			list( $company_i, $title, , , $location, , $min, $max, $remote, $desc ) = $job;
			$company = $c['companies'][ $company_i ] ?? null;
			if ( ! $company ) {
				continue;
			}
			list( $owner_i, $name, $tagline, , , $based, $site ) = $company;
			$currency = array(
				'DE' => 'EUR',
				'IN' => 'INR',
			)[ substr( (string) $based, -2 ) ] ?? 'USD'; // Salaries are in the company's currency.
			$owner    = $ctx->person( (int) $owner_i );
			$date     = $ctx->date( $i, count( $c['jobs'] ), 21 );
			$id       = wp_insert_post(
				array(
					'post_type'     => 'job_listing',
					'post_status'   => 'publish',
					'post_title'    => $title,
					'post_content'  => wpautop( $desc ),
					'post_author'   => $owner,
					'post_date_gmt' => $date,
					// time-ok: WordPress stores this field in site time beside its _gmt column; the build works in UTC.
					'post_date'     => get_date_from_gmt( $date ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'job ' . $title, $id );
				continue;
			}
			$ctx->record( $this->id(), 'job', (int) $id );
			$user = get_userdata( $owner );
			$meta = array(
				'_company_name'        => $name,
				'_company_tagline'     => $tagline,
				'_company_website'     => $site,
				'_job_location'        => 'Remote' === $location ? '' : $location,
				'_remote_position'     => $remote ? 1 : 0,
				'_application'         => $user ? $user->user_email : '',
				'_job_salary'          => $min . ' - ' . $max,
				'_job_salary_currency' => $currency,
				'_job_salary_unit'     => $max < 1000 ? 'DAY' : 'YEAR',
				'_featured'            => isset( $first[ $company_i ] ) ? 0 : 1, // Each company's first job is featured.
				'_filled'              => 0,
				'_job_expires'         => gmdate( 'Y-m-d', strtotime( $date . ' UTC' ) + 45 * DAY_IN_SECONDS ),
			);
			foreach ( $meta as $key => $value ) {
				update_post_meta( (int) $id, $key, $value );
			}
			$first[ $company_i ] = true;
			if ( ! isset( $logos[ $company_i ] ) && ! empty( $c['logos'][ $company_i ] ) ) {
				$logos[ $company_i ] = $ctx->attachment( $c['logos'][ $company_i ], $owner, $this->id() );
			}
			if ( ! empty( $logos[ $company_i ] ) ) {
				set_post_thumbnail( (int) $id, $logos[ $company_i ] );
			}
			if ( ! empty( $types[ $job_t[ $i ] ] ) ) {
				wp_set_object_terms( (int) $id, array( (int) $types[ $job_t[ $i ] ] ), 'job_listing_type' );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id   = (int) $row->object_id;
		$meta = is_array( $row->meta ?? null ) ? $row->meta : (array) json_decode( (string) ( $row->meta ?? '' ), true );
		switch ( $row->object_type ) {
			case 'job':
				return self::delete_post( $id );
			case 'attachment':
				return self::delete_post( $id, true );
			case 'term':
				return self::remove_unused_term( $id, (string) ( $meta['taxonomy'] ?? 'job_listing_type' ) );
			case 'page':
				if ( (int) get_option( 'job_manager_jobs_page_id' ) === $id ) {
					delete_option( 'job_manager_jobs_page_id' );
				}
				return self::delete_post( $id );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function offers_home(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * The job board's part of the designed home page. WP Job Manager has no job list block (only its [jobs]
	 * shortcode), so the newest jobs are cards in core blocks (title, company, place, type) the owner can edit.
	 *
	 * @param Context $ctx See parent.
	 */
	public function home( Context $ctx ): array {
		global $wpdb;
		$h         = (array) ( $this->content()['home'] ?? array() );
		$board     = $this->jobs_page() ? (string) get_permalink( $this->jobs_page() ) : '';
		$submit    = (int) get_option( 'job_manager_submit_job_form_page_id' );
		$submit    = $submit && 'publish' === get_post_status( $submit ) ? (string) get_permalink( $submit ) : '';
		$buttons   = array_values( array_filter( array( $board ? array( (string) $h['browse'], $board ) : null, $submit ? array( (string) $h['post'], $submit ) : null ) ) );
		$companies = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one count per build; WP Job Manager has no company count.
			"SELECT COUNT(DISTINCT pm.meta_value) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_company_name' AND pm.meta_value <> '' AND p.post_type = 'job_listing' AND p.post_status = 'publish'"
		);
		$stats     = array(
			array( (int) ( wp_count_posts( 'job_listing' )->publish ?? 0 ), __( 'open jobs', 'wbcom-demo-installer' ) ),
			array( $companies, __( 'companies hiring', 'wbcom-demo-installer' ) ),
		);
		if ( ! $stats[0][0] ) {
			return array();
		}
		$section = Blocks::heading( (string) $h['latest'][0] ) . Blocks::paragraph( (string) $h['latest'][1] ) . Blocks::query( 'job_listing', 6, false );
		return array(
			'hero'     => array(
				'title'   => (string) $h['title'],
				'text'    => (string) $h['text'],
				'image'   => (string) $h['image'],
				'buttons' => $buttons,
				'closing' => (string) ( $submit ? $h['closing'] : $h['browse_closing'] ), // No submit page: invite browsing.
				'cta'     => $submit ? array( (string) $h['post'], $submit ) : ( $buttons[0] ?? array() ),
			),
			'stats'    => $stats,
			'sections' => array( $section . ( $board ? Blocks::button( (string) $h['all'], $board ) : '' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		$page = $this->jobs_page();
		return $page
			? array(
				array(
					'title' => __( 'Jobs', 'wbcom-demo-installer' ),
					'url'   => (string) get_permalink( $page ),
				),
			)
			: array();
	}

	/**
	 * The "Try the demo" text stays in content/integrations.php, shared with the guide-only integration.
	 */
	public function guide(): array {
		return ( new Integration( 'jobmanager' ) )->guide();
	}
}
