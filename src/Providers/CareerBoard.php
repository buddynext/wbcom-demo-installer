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

	/** [ employer index, name, tagline, industry, size, hq, website ]. */
	private const COMPANIES = array(
		array( 7, 'Roastworks', 'Coffee subscriptions for people who care about the bean', 'Food and drink', '11-50', 'Berlin, DE', 'https://roastworks.example' ),
		array( 12, 'Paperplane Studio', 'A remote-first product studio for small brands', 'Design and software', '11-50', 'Lagos, NG', 'https://paperplane.example' ),
		array( 15, 'Crumb', 'Ordering and stock software for independent bakeries', 'Software', '1-10', 'Kochi, IN', 'https://crumb.example' ),
	);

	/** [ company index, title, category, type, location, experience, min, max, remote, body ]. */
	private const JOBS = array(
		array( 0, 'Backend Engineer (Go)', 'Engineering', 'Full-time', 'Berlin, DE', 'Mid Level', 65000, 85000, true, "We are rebuilding our subscription engine in Go and need a second backend engineer.\n\nYou will own billing and delivery scheduling, work closely with our two-person ops team, and help us choose what not to build.\n\nYou have shipped a Go service to production, you like boring reliable systems, and you write things down." ),
		array( 0, 'Customer Happiness Lead', 'Customer Success', 'Part-time', 'Remote', 'Mid Level', 30000, 38000, true, "Help 9,000 coffee lovers get the right beans. You will answer email and chat, spot patterns in what people ask, and tell product what to fix first." ),
		array( 1, 'Senior Product Designer', 'Design', 'Full-time', 'Remote', 'Senior', 70000, 90000, true, "Design end to end for two client products at a time.\n\nYou run research, sketch quickly, prototype, and stay with the work until it ships. You are comfortable presenting to founders and saying no with reasons." ),
		array( 1, 'Frontend Developer', 'Engineering', 'Full-time', 'Remote', 'Mid Level', 55000, 72000, true, "Build accessible, fast interfaces with our designers. Modern CSS, a little JavaScript, a lot of care. We test with real keyboards and screen readers." ),
		array( 1, 'UX Writer (Contract)', 'Design', 'Contract', 'Remote', 'Mid Level', 400, 550, true, "Three months, two products, every word on screen. Error messages, onboarding, empty states. Day rate, flexible hours." ),
		array( 2, 'Full-stack Developer', 'Engineering', 'Full-time', 'Kochi, IN', 'Mid Level', 1800000, 2600000, false, "Join employee number three. Laravel and Vue today, whatever we need tomorrow. You will talk to bakers every week." ),
		array( 2, 'Mobile Developer (Android)', 'Engineering', 'Contract', 'Remote', 'Senior', 30000, 45000, true, "Our ordering app has to work in kitchens with flour on the screen and no wifi. Offline-first experience is a must." ),
		array( 2, 'Junior Support Engineer', 'Customer Success', 'Full-time', 'Kochi, IN', 'Entry Level', 600000, 800000, false, "Learn our product inside out, fix small bugs, and help bakeries get set up. Great first role, we will teach you a lot." ),
	);

	public function id(): string {
		return 'careerboard';
	}

	public function label(): string {
		return 'WP Career Board';
	}

	public function available(): bool {
		return defined( 'WCB_VERSION' ) && post_type_exists( 'wcb_job' );
	}

	public function version(): string {
		return defined( 'WCB_VERSION' ) ? (string) WCB_VERSION : '';
	}

	public function min_version(): string {
		return '1.8.2';
	}

	public function icon(): string {
		return 'brief';
	}

	public function order(): int {
		return 50;
	}

	public function pro_label(): string {
		return defined( 'WCB_PRO_VERSION' ) ? __( 'Pro add-on active', 'wbcom-demo-installer' ) : '';
	}

	public function summary(): string {
		return sprintf(
			/* translators: 1: companies, 2: jobs */
			__( '%1$d companies, %2$d jobs and applications from the sample candidates', 'wbcom-demo-installer' ),
			count( self::COMPANIES ),
			count( self::JOBS )
		);
	}

	public function plan(): array {
		return array(
			'term'        => array( 0, 'setup', __( 'job categories and types', 'wbcom-demo-installer' ) ),
			'company'     => array( count( self::COMPANIES ), 'sample', __( 'companies', 'wbcom-demo-installer' ) ),
			'job'         => array( count( self::JOBS ), 'sample', __( 'jobs', 'wbcom-demo-installer' ) ),
			'application' => array( 0, 'sample', __( 'applications', 'wbcom-demo-installer' ) ),
			'attachment'  => array( 0, 'sample', __( 'resume files', 'wbcom-demo-installer' ) ),
		);
	}

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
		$ids = array();
		foreach ( self::COMPANIES as $i => $c ) {
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
				\WCB\Core\Locations::sync_company_hq( (int) $id, $hq );
			}
			update_user_meta( $owner, '_wcb_company_id', (int) $id );
			$ctx->record( $this->id(), 'company', (int) $id );
			$ids[ $i ] = array( (int) $id, $owner, $name );
		}
		$ctx->set( $this->id(), 'companies', $ids );
	}

	/**
	 * A term id, created (and recorded) when missing.
	 *
	 * @param string  $name     Term name.
	 * @param string  $taxonomy Taxonomy.
	 * @param Context $ctx      Context.
	 */
	private function term( string $name, string $taxonomy, Context $ctx ): int {
		$term = term_exists( $name, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $name, $taxonomy );
			if ( is_wp_error( $term ) ) {
				return 0;
			}
			$ctx->record( $this->id(), 'term', (int) $term['term_id'], 'setup', array( 'taxonomy' => $taxonomy ) );
		}
		return (int) $term['term_id'];
	}

	/**
	 * Jobs.
	 *
	 * @param Context $ctx Context.
	 */
	private function jobs( Context $ctx ): void {
		$companies = (array) $ctx->get( $this->id(), 'companies', array() );
		$currency  = class_exists( '\WCB\Modules\Jobs\JobsMeta' ) ? \WCB\Modules\Jobs\JobsMeta::default_currency() : 'USD';
		foreach ( self::JOBS as $i => $j ) {
			list( $ci, $title, $cat, $type, $loc, $exp, $min, $max, $remote, $body ) = $j;
			if ( empty( $companies[ $ci ] ) ) {
				continue;
			}
			list( $company_id, $owner, $company ) = $companies[ $ci ];
			$id = wp_insert_post(
				array(
					'post_type'    => 'wcb_job',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => wpautop( $body ),
					'post_author'  => $owner,
					'post_date'    => get_date_from_gmt( $ctx->date( $i, count( self::JOBS ), 30 ) ),
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
			foreach ( array( 'wcb_category' => $cat, 'wcb_job_type' => $type, 'wcb_location' => $loc, 'wcb_experience' => $exp ) as $tax => $name ) {
				$tid = taxonomy_exists( $tax ) ? $this->term( $name, $tax, $ctx ) : 0;
				if ( $tid ) {
					wp_set_post_terms( (int) $id, array( $tid ), $tax );
				}
			}
			$ctx->record( $this->id(), 'job', (int) $id );
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
		$resumes = array();
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
			$tax  = (string) ( $row->meta['taxonomy'] ?? '' );
			$term = $tax ? get_term( $id, $tax ) : null;
			if ( $term && ! is_wp_error( $term ) && 0 === (int) $term->count ) {
				wp_delete_term( $id, $tax );
			}
			return true;
		}
		if ( 'attachment' === $row->object_type ) {
			if ( get_post( $id ) ) {
				wp_delete_attachment( $id, true );
			}
			return true;
		}
		if ( get_post( $id ) ) {
			wp_delete_post( $id, true );
		}
		return true;
	}

	public function late_types(): array {
		return array( 'term' );
	}

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
