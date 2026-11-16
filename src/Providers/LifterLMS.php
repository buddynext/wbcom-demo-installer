<?php
/**
 * LifterLMS: courses with sections and lessons, access plans, course photos, categories, instructors, the catalog,
 * dashboard and checkout pages, and sample students part way through with reviews.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Blocks;
use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * LifterLMS provider. Courses are built by LifterLMS's own course generator (the importer behind its sample course
 * and course cloning), which reports every course, section, lesson, access plan and term it creates, so each one is
 * recorded. Enrolment and progress go through llms_enroll_student() and llms_mark_complete(); Finish deletes the
 * enrolment and the student's progress rows through LifterLMS's user postmeta functions. LifterLMS's bundled sample
 * course is about LifterLMS itself and loads its photo from a remote site, so the shared catalogue is used instead.
 */
final class LifterLMS extends Provider {

	/**
	 * Options of the setup wizard pages a course site needs (LLMS_Install::get_pages()). The membership catalog is
	 * left out; the demo has no memberships.
	 */
	private const PAGES = array( 'lifterlms_shop_page_id', 'lifterlms_myaccount_page_id', 'lifterlms_checkout_page_id' );

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'lifterlms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'LifterLMS';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'LLMS_VERSION' ) && class_exists( 'LLMS_Generator' ) && class_exists( 'LLMS_Install' )
			&& function_exists( 'llms_enroll_student' ) && function_exists( 'llms_mark_complete' ) && function_exists( 'llms_delete_user_postmeta' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'LLMS_VERSION' ) ? (string) LLMS_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '7.0.0';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'cap';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 43; // Next to the other course plugins.
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		$c = $this->content();
		return sprintf(
			/* translators: 1: courses, 2: categories */
			__( '%1$d courses in %2$d categories, with lessons, reviews and students making progress', 'wbcom-demo-installer' ),
			count( $c['courses'] ),
			count( $c['categories'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'option:_llms_first_time_setup_redirect' );
	}

	/**
	 * The demo creates the catalog, dashboard and checkout pages the setup wizard would, so the wizard is marked as
	 * done (the flag its own Finish sets). Manual payments are switched on as the wizard's payments step offers:
	 * LifterLMS hides a paid course's price and enrol button while no payment method is on.
	 */
	public function settings(): array {
		return array(
			'lifterlms_first_time_setup'  => 'yes',
			'llms_gateway_manual_enabled' => 'yes',
		);
	}

	/**
	 * Students per course, the same rule learners() takes them by: 3 to 6, never more than there are students.
	 *
	 * @param int $k Course position.
	 */
	private static function class_size( int $k ): int {
		return min( 3 + $k % 4, count( People::indexes( 'student' ) ) );
	}

	/**
	 * Difficulty term name for a content level ("beginner" becomes "Beginner", LifterLMS's own term).
	 *
	 * @param string $level Content difficulty.
	 */
	private static function difficulty( string $level ): string {
		return ucfirst( $level );
	}

	/**
	 * The wizard pages this site lacks: the option points at no published page, and LifterLMS would not adopt an
	 * existing page for it (one with its slug, or with its shortcode). Those are left to the owner.
	 *
	 * @return array<int,array<string,string>> LLMS_Install::get_pages() rows.
	 */
	private static function missing_pages(): array {
		$out = array();
		foreach ( \LLMS_Install::get_pages() as $page ) {
			if ( ! in_array( $page['option'], self::PAGES, true ) ) {
				continue;
			}
			$current = (int) get_option( $page['option'] );
			if ( $current && 'publish' === get_post_status( $current ) ) {
				continue;
			}
			if ( get_page_by_path( $page['slug'] ) ) {
				continue;
			}
			if ( '' !== $page['content'] && get_posts(
				array(
					'post_type'   => 'page',
					's'           => $page['content'],
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			) ) {
				continue;
			}
			$out[] = $page;
		}
		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$c        = $this->content();
		$sections = 0;
		$lessons  = 0;
		$reviews  = 0;
		$levels   = array();
		foreach ( array_values( $c['courses'] ) as $k => $course ) {
			$sections += count( $course['sections'] );
			foreach ( $course['sections'] as $section ) {
				$lessons += count( $section[1] );
			}
			$reviews += min( 2, self::class_size( $k ) );
			$levels[] = self::difficulty( (string) $course['difficulty'] );
		}
		$plan  = array(
			'course'      => array( count( $c['courses'] ), 'sample', __( 'courses', 'wbcom-demo-installer' ) ),
			'section'     => array( $sections, 'sample', __( 'sections', 'wbcom-demo-installer' ) ),
			'lesson'      => array( $lessons, 'sample', __( 'lessons', 'wbcom-demo-installer' ) ),
			'access_plan' => array( count( $c['courses'] ), 'sample', __( 'access plans', 'wbcom-demo-installer' ) ),
			'enrollment'  => array( count( $c['courses'] ), 'sample', __( 'courses with sample students', 'wbcom-demo-installer' ) ),
			'review'      => array( $reviews, 'sample', __( 'course reviews', 'wbcom-demo-installer' ) ),
			'attachment'  => array( count( $c['courses'] ), 'sample', __( 'course photos', 'wbcom-demo-installer' ) ),
		);
		$pages = class_exists( 'LLMS_Install' ) ? count( self::missing_pages() ) : 0;
		if ( $pages ) {
			$plan['page'] = array( $pages, 'setup', __( 'course catalog, dashboard and checkout pages', 'wbcom-demo-installer' ) );
		}
		$terms = self::missing_terms(
			array(
				'course_cat'        => array_values( (array) $c['categories'] ),
				'course_difficulty' => $levels,
			)
		);
		if ( $terms ) {
			$plan['term'] = array( $terms, 'setup', __( 'course categories and levels', 'wbcom-demo-installer' ) );
		}
		return $plan;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		$steps = array( 'setup' );
		foreach ( array_keys( $this->content()['courses'] ) as $i ) {
			$steps[] = 'course:' . $i;
		}
		$steps[] = 'learners';
		return $steps;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $step See parent.
	 * @param Context $ctx  See parent.
	 */
	public function run( string $step, Context $ctx ): void {
		list( $name, $arg ) = array_pad( explode( ':', $step, 2 ), 2, '0' );
		switch ( $name ) {
			case 'setup':
				$this->pages( $ctx );
				foreach ( (array) $this->content()['categories'] as $label ) {
					$this->ensure_term( $label, 'course_cat', $ctx );
				}
				foreach ( $this->content()['courses'] as $course ) {
					$this->ensure_term( self::difficulty( (string) $course['difficulty'] ), 'course_difficulty', $ctx );
				}
				break;
			case 'course':
				$this->course( (int) $arg, $ctx );
				break;
			case 'learners':
				$this->learners( $ctx );
				break;
		}
	}

	/**
	 * The catalog, student dashboard and checkout pages, through LifterLMS's own page function, only where the site
	 * has none. Recorded as setup with the option's previous value, which Finish puts back.
	 *
	 * @param Context $ctx Context.
	 */
	private function pages( Context $ctx ): void {
		if ( ! function_exists( 'llms_create_page' ) ) {
			require_once LLMS_PLUGIN_DIR . 'includes/admin/llms.functions.admin.php';
		}
		foreach ( self::missing_pages() as $page ) {
			$before = get_option( $page['option'], null );
			$id     = (int) llms_create_page( $page['slug'], $page['title'], $page['content'], $page['option'] );
			if ( $id ) {
				$ctx->record(
					$this->id(),
					'page',
					$id,
					'setup',
					array(
						'option' => $page['option'],
						'before' => $before,
					)
				);
			}
		}
	}

	/**
	 * One course through LifterLMS's course generator: the course with its category, level, access plan and reviews
	 * switched on, a section per content section and a lesson per section lesson (the first one a free preview).
	 * Every object the generator reports is recorded; the course photo is added from the media pack.
	 *
	 * @param int     $i   Course index.
	 * @param Context $ctx Context.
	 */
	private function course( int $i, Context $ctx ): void {
		$all = $this->content()['courses'];
		$c   = $all[ $i ] ?? null;
		if ( ! $c ) {
			return;
		}
		$teacher = $ctx->person( (int) $c['instructor'] );
		$date    = $ctx->date( $i, count( $all ), 70 );
		// time-ok: WordPress stores this field in site time beside its _gmt column; the build works in UTC.
		$local = static fn( string $gmt ): string => get_date_from_gmt( $gmt );
		// LifterLMS's instructor role lives on the sample person and goes with them on Finish.
		$user = get_userdata( $teacher );
		if ( $user && get_role( 'instructor' ) ) {
			$user->add_role( 'instructor' );
		}

		$sections = array();
		foreach ( $c['sections'] as $s => $section ) {
			$lessons = array();
			foreach ( $section[1] as $t => $item ) {
				$lessons[] = array(
					'title'       => $item[0],
					'content'     => wpautop( $item[1] ),
					'date'        => $local( $ctx->after( $date, $s * 10 + $t + 1 ) ),
					'free_lesson' => 0 === $s && 0 === $t ? 'yes' : 'no',
				);
			}
			$sections[] = array(
				'title'   => $section[0],
				'date'    => $local( $ctx->after( $date, $s ) ),
				'lessons' => $lessons,
			);
		}
		$price = (float) $c['price'];
		$raw   = array(
			'title'        => $c['title'],
			'content'      => wpautop( $c['desc'] ),
			'excerpt'      => $c['desc'],
			'date'         => $local( $date ),
			'modified'     => $local( $date ),
			'status'       => 'publish',
			'author'       => array( 'id' => $teacher ),
			'categories'   => array( (string) ( $this->content()['categories'][ $c['category'] ] ?? '' ) ),
			'difficulty'   => self::difficulty( (string) $c['difficulty'] ),
			'length'       => sprintf(
				/* translators: %d: minutes */
				__( '%d minutes', 'wbcom-demo-installer' ),
				12 * array_sum( array_map( static fn( array $s ): int => count( $s[1] ), $c['sections'] ) )
			),
			'custom'       => array(
				'_llms_reviews_enabled'           => array( 'yes' ),
				'_llms_display_reviews'           => array( 'yes' ),
				'_llms_num_reviews'               => array( '10' ),
				'_llms_multiple_reviews_disabled' => array( 'yes' ),
			),
			'access_plans' => array(
				array(
					'title'             => $price > 0 ? __( 'Lifetime access', 'wbcom-demo-installer' ) : __( 'Free', 'wbcom-demo-installer' ),
					'is_free'           => $price > 0 ? 'no' : 'yes',
					'price'             => $price,
					'frequency'         => 0,
					'access_expiration' => 'lifetime',
					'availability'      => 'open',
					'on_sale'           => 'no',
					'trial_offer'       => 'no',
					'enroll_text'       => $price > 0 ? __( 'Buy now', 'wbcom-demo-installer' ) : __( 'Enroll for free', 'wbcom-demo-installer' ),
				),
			),
			'sections'     => $sections,
		);

		$generator = new \LLMS_Generator( $raw );
		$generator->set_generator( 'LifterLMS/SingleCourseGenerator' );
		$generator->set_default_post_status( 'publish' );
		$generator->generate();
		if ( $generator->is_error() ) {
			$ctx->warn( $this->id(), 'course ' . $c['title'], $generator->get_results() );
			return;
		}
		$made = (array) $generator->get_generated_content();
		// Recorded parent first, so Finish (newest first) deletes lessons and sections before their course.
		foreach ( array( 'term', 'course', 'access_plan', 'section', 'lesson' ) as $type ) {
			foreach ( (array) ( $made[ $type ] ?? array() ) as $id ) {
				if ( 'term' === $type ) {
					$ctx->record( $this->id(), 'term', (int) $id, 'setup', array( 'taxonomy' => '' ) );
				} else {
					$ctx->record( $this->id(), $type, (int) $id );
				}
			}
		}
		$course_id = (int) ( $made['course'][0] ?? 0 );
		$image     = $course_id ? $ctx->attachment( $c['image'], $teacher, $this->id() ) : 0;
		if ( $image ) {
			set_post_thumbnail( $course_id, $image );
		}
	}

	/**
	 * Students take courses: the first in each course finishes it, the others are part way through, and the first
	 * two leave a review.
	 *
	 * @param Context $ctx Context.
	 */
	private function learners( Context $ctx ): void {
		$students = $ctx->people( 'student' );
		$bodies   = (array) $this->content()['reviews'];
		if ( ! $students ) {
			return;
		}
		$r = 0;
		foreach ( Ledger::ids( $this->id(), 'course' ) as $k => $course_id ) {
			$course  = new \LLMS_Course( $course_id );
			$lessons = array_map( 'intval', (array) $course->get_lessons( 'ids' ) );
			$posts   = array_merge( array( (int) $course_id ), array_map( 'intval', (array) $course->get_sections( 'ids' ) ), $lessons );
			$take    = array_slice( array_merge( array_slice( $students, $k ), array_slice( $students, 0, $k ) ), 0, self::class_size( $k ) );
			$start   = (string) get_post_field( 'post_date_gmt', $course_id );
			// One ledger row per course lists its students and the posts they have progress on.
			$ctx->record(
				$this->id(),
				'enrollment',
				(int) $course_id,
				'sample',
				array(
					'users' => array_map( 'intval', $take ),
					'posts' => $posts,
				)
			);
			foreach ( $take as $s => $uid ) {
				if ( ! llms_enroll_student( (int) $uid, (int) $course_id, 'wbcom_demo' ) ) {
					$ctx->warn( $this->id(), 'enrolment', __( 'LifterLMS did not enrol the student.', 'wbcom-demo-installer' ) );
					continue;
				}
				// Enrolled on the build timeline, not on the day of the build.
				// time-ok: WordPress stores this field in site time beside its _gmt column; the build works in UTC.
				llms_update_user_postmeta( (int) $uid, (int) $course_id, '_start_date', get_date_from_gmt( $ctx->after( $start, $s + 1 ) ) );
				// Completing every lesson completes its sections and the course, as LifterLMS works it out.
				$share = 0 === $s ? 1 : ( ( $s * 37 ) % 90 ) / 100;
				foreach ( array_slice( $lessons, 0, (int) floor( count( $lessons ) * $share ) ) as $lesson_id ) {
					llms_mark_complete( (int) $uid, $lesson_id, 'lesson', 'wbcom_demo' );
				}
				if ( $s < 2 && $bodies ) {
					$this->review( (int) $course_id, (int) $uid, (array) $bodies[ $r % count( $bodies ) ], $ctx->after( $start, 60 + $k * 3 + $s ), $ctx );
					++$r;
				}
			}
		}
	}

	/**
	 * A course review as LifterLMS stores one from its review form: an "llms_review" post under the course,
	 * written by the student.
	 *
	 * @param int                   $course_id Course.
	 * @param int                   $uid       Student.
	 * @param array<int,int|string> $body      [ rating, title, text ]; LifterLMS reviews have no rating.
	 * @param string                $date      Date, UTC.
	 * @param Context               $ctx       Context.
	 */
	private function review( int $course_id, int $uid, array $body, string $date, Context $ctx ): void {
		$id = wp_insert_post(
			array(
				'post_type'     => 'llms_review',
				'post_status'   => 'publish',
				'post_title'    => (string) $body[1],
				'post_excerpt'  => (string) $body[1],
				'post_content'  => (string) $body[2],
				'post_parent'   => $course_id,
				'post_author'   => $uid,
				'post_date_gmt' => $date,
				// time-ok: WordPress stores this field in site time beside its _gmt column; the build works in UTC.
				'post_date'     => get_date_from_gmt( $date ),
			),
			true
		);
		if ( ! is_wp_error( $id ) ) {
			$ctx->record( $this->id(), 'review', (int) $id );
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
			case 'enrollment': // Object id is the course; meta lists the students and the posts they have progress on.
				$gone = true;
				foreach ( (array) ( $meta['users'] ?? array() ) as $uid ) {
					llms_delete_student_enrollment( (int) $uid, $id );
					foreach ( (array) ( $meta['posts'] ?? array( $id ) ) as $post_id ) {
						llms_delete_user_postmeta( (int) $uid, (int) $post_id );
					}
					self::delete_notifications( (int) $uid );
					$gone = $gone && ! llms_is_user_enrolled( (int) $uid, $id, 'all', false );
				}
				return $gone;
			case 'page':
				$option = (string) ( $meta['option'] ?? '' );
				if ( '' !== $option && (int) get_option( $option ) === $id ) {
					if ( null === ( $meta['before'] ?? null ) ) {
						delete_option( $option );
					} else {
						update_option( $option, $meta['before'] );
					}
				}
				return self::delete_post( $id );
			case 'course':
			case 'section':
			case 'lesson':
			case 'access_plan':
			case 'review':
				return self::delete_post( $id );
			case 'attachment':
				return self::delete_post( $id, true );
			case 'term':
				$taxonomy = (string) ( $meta['taxonomy'] ?? '' );
				if ( '' === $taxonomy ) { // A term the generator made: find its taxonomy.
					$term     = get_term( $id );
					$taxonomy = $term instanceof \WP_Term ? $term->taxonomy : '';
				}
				return self::remove_unused_term( $id, $taxonomy );
		}
		return true;
	}

	/**
	 * The notifications LifterLMS made about a sample student's enrolment and progress (the student's own, and the
	 * instructor's "course completed" email about them). LifterLMS has no delete for a single notification; its
	 * personal data eraser removes every notification by or for one person, and every one a sample student has comes
	 * from the demo.
	 *
	 * @param int $uid Sample student.
	 */
	private static function delete_notifications( int $uid ): void {
		$user = get_userdata( $uid );
		if ( $user && class_exists( 'LLMS_Privacy_Erasers' ) ) {
			\LLMS_Privacy_Erasers::notification_data( $user->user_email, 1 );
		}
	}

	/**
	 * Course catalog: the catalog page, else LifterLMS's course archive.
	 */
	private static function catalog(): string {
		$page = (int) get_option( 'lifterlms_shop_page_id' );
		return $page && 'publish' === get_post_status( $page ) ? (string) get_permalink( $page ) : (string) get_post_type_archive_link( 'course' );
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
	 * The course site's part of the designed home page: LifterLMS's own Courses block and the course topics.
	 *
	 * @param Context $ctx See parent.
	 */
	public function home( Context $ctx ): array {
		$h         = (array) ( $this->content()['home'] ?? array() );
		$catalog   = self::catalog();
		$account   = (int) get_option( 'lifterlms_myaccount_page_id' );
		$dashboard = $account && 'publish' === get_post_status( $account ) ? (string) get_permalink( $account ) : '';
		$buttons   = array_values( array_filter( array( $catalog ? array( (string) $h['browse'], $catalog ) : null, $dashboard ? array( (string) $h['second'], $dashboard ) : null ) ) );
		$stats     = array(
			array( (int) ( wp_count_posts( 'course' )->publish ?? 0 ), __( 'courses', 'wbcom-demo-installer' ) ),
			array( (int) ( wp_count_posts( 'lesson' )->publish ?? 0 ), __( 'lessons', 'wbcom-demo-installer' ) ),
			array( count( $ctx->people( 'student' ) ), __( 'students', 'wbcom-demo-installer' ) ),
		);
		$topics    = taxonomy_exists( 'course_cat' ) ? LearnDash::course_topics( 'course_cat' ) : array();

		$sections = array(
			Blocks::heading( (string) $h['featured'][0] ) . Blocks::paragraph( (string) $h['featured'][1] )
				. '<!-- wp:llms/courses {"orderby":"date","order":"DESC","posts_per_page":6} /-->'
				. ( $catalog ? Blocks::button( (string) $h['all'], $catalog ) : '' ),
		);
		if ( $topics ) {
			$sections[] = Blocks::heading( (string) $h['cats'][0] ) . Blocks::paragraph( (string) $h['cats'][1] ) . Blocks::columns( $topics );
		}
		return array(
			'hero'     => array(
				'title'   => (string) $h['title'],
				'text'    => (string) $h['text'],
				'image'   => (string) $h['image'],
				'buttons' => $buttons,
				'closing' => (string) $h['closing'],
			),
			'stats'    => $stats,
			'sections' => $sections,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		$url = self::catalog();
		return $url
			? array(
				array(
					'title' => __( 'Courses', 'wbcom-demo-installer' ),
					'url'   => $url,
				),
			)
			: array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function guide(): array {
		if ( ! $this->available() ) {
			return array();
		}
		$t         = $this->content()['guide'];
		$built     = Ledger::ids( $this->id(), 'course' );
		$course    = $built ? (int) min( $built ) : 0;
		$lessons   = $course ? (array) ( new \LLMS_Course( $course ) )->get_lessons( 'ids' ) : array();
		$dashboard = (int) get_option( 'lifterlms_myaccount_page_id' );
		$roles     = array();
		foreach ( array( 'student', 'instructor' ) as $key ) {
			$roles[] = array(
				'key'    => $key,
				'user'   => People::user_id( $key ),
				'label'  => $t['roles'][ $key ][0],
				'points' => $t['roles'][ $key ][1],
			);
		}
		return array(
			'name'         => $t['name'],
			'tab'          => $t['tab'],
			'member_label' => $t['member_label'],
			'owner_points' => $t['owner_points'],
			'roles'        => $roles,
			'member'       => self::guide_steps(
				$t['member'],
				array(
					'catalog'   => self::catalog(),
					'course'    => $course ? (string) get_permalink( $course ) : null,
					'lesson'    => $lessons ? (string) get_permalink( (int) reset( $lessons ) ) : null,
					'dashboard' => $dashboard && 'publish' === get_post_status( $dashboard ) ? (string) get_permalink( $dashboard ) : null,
				),
				array(
					'catalog'   => array(
						'public'    => true,
						'as'        => 'student',
						'highlight' => true,
					),
					'course'    => array(
						'public' => true,
						'as'     => 'student',
					),
					'lesson'    => array( 'as' => 'student' ),
					'dashboard' => array( 'as' => 'student' ),
				)
			),
			'owner'        => self::guide_steps(
				$t['owner'],
				array(
					'courses'   => admin_url( 'edit.php?post_type=course' ),
					'reporting' => admin_url( 'admin.php?page=llms-reporting' ),
					'settings'  => admin_url( 'admin.php?page=llms-settings' ),
				)
			),
			'highlights'   => self::guide_highlights( $t['highlights'] ),
		);
	}
}
