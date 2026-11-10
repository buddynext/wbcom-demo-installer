<?php
/**
 * WP Career Board: employers' companies, jobs and applications from the sample candidates.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Career Board provider. Career Board stores jobs and companies as post types; applications go
 * through its own REST route so every hook runs.
 */
final class CareerBoard extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'careerboard';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'WP Career Board';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'WCB_VERSION' ) && post_type_exists( 'wcb_job' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'WCB_VERSION' ) ? (string) WCB_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '1.8.2';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'brief';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 50;
	}

	/**
	 * {@inheritDoc}
	 */
	public function pro_label(): string {
		return defined( 'WCBP_VERSION' ) ? __( 'Pro add-on active', 'wbcom-demo-installer' ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return sprintf(
			/* translators: 1: companies, 2: jobs */
			__( '%1$d companies, %2$d jobs and applications from the sample candidates', 'wbcom-demo-installer' ),
			count( $this->content()['companies'] ),
			count( $this->content()['jobs'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'wcb_activation_redirect' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		return array( 'wcb_setup_complete' => true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$jobs  = $this->content()['jobs'];
		$terms = array();
		foreach ( $jobs as $j ) {
			$terms['wcb_category'][]   = $j[2];
			$terms['wcb_job_type'][]   = $j[3];
			$terms['wcb_location'][]   = $j[4];
			$terms['wcb_experience'][] = $j[5];
		}
		if ( class_exists( '\WCB\Core\Locations' ) ) {
			// Career Board files each company under its HQ location too (Locations::sync_company_hq()).
			$terms['wcb_location'] = array_merge( $terms['wcb_location'], array_column( $this->content()['companies'], 5 ) );
		}
		$applications = 0;
		foreach ( array_keys( array_values( $jobs ) ) as $k ) {
			$applications += 1 + $k % 3; // As applications() does.
		}
		$candidates = count( People::indexes( 'candidate' ) );
		return array(
			'term'        => array( self::missing_terms( $terms ), 'setup', __( 'job categories, types and locations', 'wbcom-demo-installer' ) ),
			'page'        => array( class_exists( '\WCB\Admin\Pages' ) ? count( \WCB\Admin\Pages::missing() ) : 0, 'setup', __( 'job board pages', 'wbcom-demo-installer' ) ),
			'company'     => array( count( $this->content()['companies'] ), 'sample', __( 'companies', 'wbcom-demo-installer' ) ),
			'job'         => array( count( $jobs ), 'sample', __( 'jobs', 'wbcom-demo-installer' ) ),
			'application' => array( $candidates ? $applications : 0, 'sample', __( 'applications', 'wbcom-demo-installer' ) ),
			'attachment'  => array( $candidates, 'sample', __( 'resume files', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'companies', 'jobs', 'applications' );
	}

	/**
	 * Run a step.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		match ( $step ) {
			'companies'    => $this->companies( $ctx ),
			'jobs'         => $this->jobs( $ctx ),
			'applications' => $this->applications( $ctx ),
			default        => null,
		};
	}

	/**
	 * Companies owned by the employers.
	 *
	 * @param Context $ctx Context.
	 */
	private function companies( Context $ctx ): void {
		// The job board pages the Career Board setup wizard would create (kept as setup).
		if ( class_exists( '\WCB\Admin\Pages' ) ) {
			$missing = \WCB\Admin\Pages::missing();
			foreach ( \WCB\Admin\Pages::create_missing( $missing ) as $page_id ) {
				$ctx->record( $this->id(), 'page', (int) $page_id, 'setup' );
			}
		}
		$ids = array();
		foreach ( $this->content()['companies'] as $i => $c ) {
			list( $who, $name, $tagline, $industry, $size, $hq, $site ) = $c;
			$owner = $ctx->person( $who );
			$user  = get_userdata( $owner );
			if ( $user && class_exists( '\WCB\Core\Roles' ) ) {
				\WCB\Core\Roles::grant_member_role( $user, 'wcb_employer' );
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'wcb_company',
					'post_status'  => 'publish',
					'post_title'   => $name,
					'post_content' => wpautop( $tagline . '. ' . sprintf( 'Based in %s, %s people.', $hq, $size ) ),
					'post_author'  => $owner,
					'post_date'    => get_date_from_gmt( $ctx->date( $i, 6, 70 ) ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'company ' . $name, $id );
				continue;
			}
			update_post_meta( $id, '_wcb_tagline', $tagline );
			update_post_meta( $id, '_wcb_website', $site );
			update_post_meta( $id, '_wcb_industry', $industry );
			update_post_meta( $id, '_wcb_company_size', $size );
			update_post_meta( $id, '_wcb_hq_location', $hq );
			if ( class_exists( '\WCB\Core\Locations' ) ) {
				$ctx->watch_terms( $this->id(), array( 'wcb_location' ), static fn() => \WCB\Core\Locations::sync_company_hq( (int) $id, $hq ) );
			}
			update_user_meta( $owner, '_wcb_company_id', (int) $id );
			$ctx->record( $this->id(), 'company', (int) $id );
			$ids[ $i ] = array( (int) $id, $owner, $name );
		}
		$ctx->set( $this->id(), 'companies', $ids );
	}

	/**
	 * Jobs.
	 *
	 * @param Context $ctx Context.
	 */
	private function jobs( Context $ctx ): void {
		$companies = (array) $ctx->get( $this->id(), 'companies', array() );
		$currency  = class_exists( '\WCB\Modules\Jobs\JobsMeta' ) ? \WCB\Modules\Jobs\JobsMeta::default_currency() : 'USD';
		foreach ( $this->content()['jobs'] as $i => $j ) {
			list( $ci, $title, $cat, $type, $loc, $exp, $min, $max, $remote, $body ) = $j;
			if ( empty( $companies[ $ci ] ) ) {
				continue;
			}
			list( $company_id, $owner, $company ) = $companies[ $ci ];
			\WbcomDemo\Core\Clock::set( $ctx->date( $i, count( $this->content()['jobs'] ), 30 ) );
			$id = wp_insert_post(
				array(
					'post_type'    => 'wcb_job',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => wpautop( $body ),
					'post_author'  => $owner,
					'post_date'    => get_date_from_gmt( $ctx->date( $i, count( $this->content()['jobs'] ), 30 ) ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'job ' . $title, $id );
				continue;
			}
			$meta = array(
				'_wcb_salary_min'      => (string) $min,
				'_wcb_salary_max'      => (string) $max,
				'_wcb_salary_currency' => $currency,
				'_wcb_salary_type'     => 'Contract' === $type && $max < 1000 ? 'daily' : 'annual',
				'_wcb_remote'          => $remote ? '1' : '0',
				'_wcb_deadline'        => gmdate( 'Y-m-d', strtotime( '+' . ( 20 + $i * 5 ) . ' days' ) ),
				'_wcb_featured'        => 0 === $i % 3 ? '1' : '0',
				'_wcb_company_id'      => (string) $company_id,
				'_wcb_company_name'    => $company,
			);
			foreach ( $meta as $key => $value ) {
				update_post_meta( $id, $key, $value );
			}
			foreach ( array(
				'wcb_category'   => $cat,
				'wcb_job_type'   => $type,
				'wcb_location'   => $loc,
				'wcb_experience' => $exp,
			) as $tax => $name ) {
				$tid = taxonomy_exists( $tax ) ? $this->ensure_term( $name, $tax, $ctx ) : 0;
				if ( $tid ) {
					wp_set_post_terms( (int) $id, array( $tid ), $tax );
				}
			}
			$ctx->record( $this->id(), 'job', (int) $id );
			\WbcomDemo\Core\Clock::reset();
		}
	}

	/**
	 * Candidates apply through Career Board's own REST route.
	 *
	 * @param Context $ctx Context.
	 */
	private function applications( Context $ctx ): void {
		$candidates = $ctx->people( 'candidate' );
		$letters    = array(
			'I have followed your work for a while and this role fits what I want to do next. Portfolio linked in my profile.',
			'I would love to help with this. I have done similar work for two years and I learn fast.',
			'This is exactly the kind of team I want to join. Happy to do a short paid trial.',
		);
		$resumes    = array();
		foreach ( $candidates as $uid ) {
			$user = get_userdata( $uid );
			if ( $user && class_exists( '\WCB\Core\Roles' ) ) {
				\WCB\Core\Roles::grant_member_role( $user, 'wcb_candidate' );
			}
			$resumes[ $uid ] = $ctx->attachment( 'docs/sample-resume.pdf', $uid, $this->id() );
		}
		$was = get_current_user_id();
		foreach ( Ledger::ids( $this->id(), 'job' ) as $k => $job_id ) {
			for ( $a = 0; $a < 1 + $k % 3; $a++ ) {
				$uid = $candidates[ ( $k + $a * 2 ) % max( 1, count( $candidates ) ) ] ?? 0;
				if ( ! $uid ) {
					continue;
				}
				wp_set_current_user( $uid );
				$req = new \WP_REST_Request( 'POST', '/wcb/v1/jobs/' . $job_id . '/apply' );
				$req->set_param( 'cover_letter', $letters[ ( $k + $a ) % count( $letters ) ] );
				if ( ! empty( $resumes[ $uid ] ) ) {
					$req->set_param( 'resume_attachment_id', $resumes[ $uid ] );
				}
				$res = rest_do_request( $req );
				if ( $res->is_error() ) {
					$ctx->warn( $this->id(), 'application', $res->as_error() );
					continue;
				}
				$data = $res->get_data();
				$aid  = (int) ( $data['id'] ?? $data['application_id'] ?? 0 );
				if ( $aid ) {
					$ctx->record( $this->id(), 'application', $aid );
				}
			}
		}
		wp_set_current_user( $was );
	}

	/**
	 * Remove one object.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id = (int) $row->object_id;
		if ( 'term' === $row->object_type ) {
			return self::remove_unused_term( $id, (string) ( $row->meta['taxonomy'] ?? '' ) );
		}
		if ( 'attachment' === $row->object_type ) {
			return self::delete_post( $id, true );
		}
		if ( get_post( $id ) ) {
			// Career Board links a company to its HQ location term outside the post type's
			// registered taxonomies, so wp_delete_post() leaves that link behind.
			wp_delete_object_term_relationships( $id, array( 'wcb_location' ) );
		}
		return self::delete_post( $id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function late_types(): array {
		return array( 'term' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		if ( ! class_exists( '\WCB\Admin\Pages' ) ) {
			return array();
		}
		$page = (int) \WCB\Admin\Pages::get_id( 'jobs_archive_page' );
		return $page && 'publish' === get_post_status( $page )
			? array(
				array(
					'title'   => __( 'Jobs', 'wbcom-demo-installer' ),
					'page_id' => $page,
				),
			)
			: array();
	}
}
