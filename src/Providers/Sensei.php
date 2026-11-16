<?php
/**
 * Sensei LMS: courses with modules and lessons, course photos, categories, teachers, and sample students part way
 * through them, with ratings where the Reign Sensei add-on shows them.
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
 * Sensei LMS provider. A course's modules and lesson order are saved through Sensei_Course_Structure, the service
 * behind the course outline block; the lessons are added first as posts of the sample teacher, so the structure
 * only links them (it would otherwise make draft lessons, each with an empty quiz). Enrolment goes through
 * Sensei_Course_Enrolment (manual enrolment) and progress through Sensei_Utils and Sensei's progress repositories,
 * which store it in comments or in Sensei's progress tables, whichever the site uses. Finish resets each sample
 * student's progress and withdraws them the same way; deleting a lesson or course removes its progress rows, and
 * deleting a sample person removes their student term.
 */
final class Sensei extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'sensei';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Sensei LMS';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'SENSEI_LMS_VERSION' ) && function_exists( 'Sensei' ) && class_exists( 'Sensei_Course_Structure' )
			&& class_exists( 'Sensei_Course_Enrolment' ) && class_exists( 'Sensei_Utils' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'SENSEI_LMS_VERSION' ) ? (string) SENSEI_LMS_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '4.20.0';
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
		return 44; // Next to the other course plugins.
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		$c = $this->content();
		return sprintf(
			/* translators: 1: courses, 2: categories */
			__( '%1$d courses in %2$d categories, with modules, lessons and students making progress', 'wbcom-demo-installer' ),
			count( $c['courses'] ),
			count( $c['categories'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'option:sensei_activation_redirect' );
	}

	/**
	 * Sensei makes its Courses, My Courses and Course Completed pages on activation and then suggests its setup
	 * wizard on every Sensei screen; the demo has the courses the wizard leads to, so the suggestion is marked done
	 * (the flag the wizard's own Skip sets). With the Reign Sensei add-on, its course reviews are switched on (they
	 * are off by default), so the ratings show on course pages and cards.
	 */
	public function settings(): array {
		$out = array( 'sensei_suggest_setup_wizard' => 0 );
		if ( self::reviews_supported() ) {
			$reign                = get_option( 'reign_options', array() );
			$sensei               = is_array( $reign ) && isset( $reign['sensei'] ) && is_array( $reign['sensei'] ) ? $reign['sensei'] : array();
			$out['reign_options'] = array( 'sensei' => array_merge( $sensei, array( 'reviews' => 'on' ) ) );
		}
		return $out;
	}

	/**
	 * Course ratings: Sensei has none of its own; the Reign Sensei add-on stores them as course comments with a
	 * "rating" and a "title".
	 */
	private static function reviews_supported(): bool {
		return class_exists( 'Reign_SN_Reviews' );
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
	 * Modules a build makes: Sensei names a teacher's module by the teacher and its title, so one per distinct
	 * teacher and section title (a teacher's two courses share a module of the same name).
	 *
	 * @return array<string,true>
	 */
	private function modules(): array {
		$out = array();
		foreach ( $this->content()['courses'] as $course ) {
			foreach ( $course['sections'] as $section ) {
				$out[ (int) $course['instructor'] . '|' . sanitize_title( (string) $section[0] ) ] = true;
			}
		}
		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$c       = $this->content();
		$lessons = 0;
		$reviews = 0;
		foreach ( array_values( $c['courses'] ) as $k => $course ) {
			foreach ( $course['sections'] as $section ) {
				$lessons += count( $section[1] );
			}
			$reviews += min( 2, self::class_size( $k ) );
		}
		$plan = array(
			'course'     => array( count( $c['courses'] ), 'sample', __( 'courses', 'wbcom-demo-installer' ) ),
			'module'     => array( count( $this->modules() ), 'sample', __( 'modules', 'wbcom-demo-installer' ) ),
			'lesson'     => array( $lessons, 'sample', __( 'lessons', 'wbcom-demo-installer' ) ),
			'enrollment' => array( count( $c['courses'] ), 'sample', __( 'courses with sample students', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $c['courses'] ), 'sample', __( 'course photos', 'wbcom-demo-installer' ) ),
		);
		if ( self::reviews_supported() && $c['reviews'] ) {
			$plan['review'] = array( $reviews, 'sample', __( 'course ratings', 'wbcom-demo-installer' ) );
		}
		$terms = self::missing_terms( array( 'course-category' => array_values( (array) $c['categories'] ) ) );
		if ( $terms ) {
			$plan['term'] = array( $terms, 'setup', __( 'course categories', 'wbcom-demo-installer' ) );
		}
		return $plan;
	}

	/**
	 * {@inheritDoc}
	 */
	public function late_types(): array {
		return array( 'module' ); // A module is removed once no course or lesson uses it.
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		$steps = array( 'categories' );
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
			case 'categories':
				$map = array();
				foreach ( (array) $this->content()['categories'] as $key => $label ) {
					$map[ $key ] = $this->ensure_term( $label, 'course-category', $ctx );
				}
				$ctx->set( $this->id(), 'categories', $map );
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
	 * One course: the course with its category and photo, a lesson per section lesson, then Sensei's course
	 * structure puts the lessons in a module per section, in order. The author is a sample person with Sensei's
	 * teacher role.
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
		$cats    = (array) $ctx->get( $this->id(), 'categories', array() );
		// Sensei's teacher role lives on the sample person and goes with them on Finish.
		$user = get_userdata( $teacher );
		if ( $user && get_role( 'teacher' ) ) {
			$user->add_role( 'teacher' );
		}

		$course_id = $this->post( 'course', $c['title'], Blocks::paragraph( (string) $c['desc'] ), $teacher, $date, $c['desc'] );
		if ( ! $course_id ) {
			$ctx->warn( $this->id(), 'course ' . $c['title'], __( 'Sensei LMS did not save it.', 'wbcom-demo-installer' ) );
			return;
		}
		$ctx->record( $this->id(), 'course', $course_id );
		if ( ! empty( $cats[ $c['category'] ] ) ) {
			wp_set_object_terms( $course_id, array( (int) $cats[ $c['category'] ] ), 'course-category' );
		}
		$image = $ctx->attachment( $c['image'], $teacher, $this->id() );
		if ( $image ) {
			set_post_thumbnail( $course_id, $image );
		}

		$structure = array();
		$before    = array();
		foreach ( $c['sections'] as $s => $section ) {
			list( $title, $items ) = $section;
			$lessons               = array();
			foreach ( $items as $t => $item ) {
				$lesson_id = $this->post( 'lesson', $item[0], Blocks::paragraph( (string) $item[1] ), $teacher, $ctx->after( $date, $s * 10 + $t + 1 ) );
				if ( ! $lesson_id ) {
					continue;
				}
				$ctx->record( $this->id(), 'lesson', $lesson_id );
				update_post_meta( $lesson_id, '_lesson_course', $course_id );
				update_post_meta( $lesson_id, '_lesson_length', 12 ); // Minutes.
				$lessons[] = array(
					'type'  => 'lesson',
					'id'    => $lesson_id,
					'title' => $item[0],
				);
			}
			$structure[] = array(
				'type'    => 'module',
				'title'   => $title,
				'lessons' => $lessons,
			);
			$slug        = $teacher . '-' . sanitize_title( $title ); // Sensei's slug for a teacher's module.
			$found       = get_term_by( 'slug', $slug, 'module' );
			$before[]    = $found instanceof \WP_Term ? (int) $found->term_id : 0;
		}
		$saved = \Sensei_Course_Structure::instance( $course_id )->save( $structure );
		if ( true !== $saved ) {
			$ctx->warn( $this->id(), 'course outline ' . $c['title'], is_wp_error( $saved ) ? $saved->get_error_message() : __( 'Sensei LMS did not save it.', 'wbcom-demo-installer' ) );
		}
		$modules = wp_get_object_terms( $course_id, 'module', array( 'fields' => 'ids' ) );
		foreach ( is_array( $modules ) ? $modules : array() as $module_id ) {
			if ( ! in_array( (int) $module_id, $before, true ) && ! Ledger::has( $this->id(), 'module', (int) $module_id ) ) {
				$ctx->record( $this->id(), 'module', (int) $module_id, 'sample', array( 'taxonomy' => 'module' ) );
			}
		}
	}

	/**
	 * Insert a course or lesson owned by a sample person.
	 *
	 * @param string $type    Post type.
	 * @param string $title   Title.
	 * @param string $content Content (blocks).
	 * @param int    $author  Author.
	 * @param string $date    Date, UTC.
	 * @param string $excerpt Excerpt.
	 * @return int Post id, 0 on failure.
	 */
	private function post( string $type, string $title, string $content, int $author, string $date, string $excerpt = '' ): int {
		$id = wp_insert_post(
			array(
				'post_type'     => $type,
				'post_status'   => 'publish',
				'post_title'    => $title,
				'post_content'  => $content,
				'post_excerpt'  => $excerpt,
				'post_author'   => $author,
				'post_date_gmt' => $date,
				// time-ok: WordPress stores this field in site time beside its _gmt column; the build works in UTC.
				'post_date'     => get_date_from_gmt( $date ),
			),
			true
		);
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * A course's published lessons in course order (modules by position, then their lessons).
	 *
	 * @param int $course_id Course.
	 * @return int[]
	 */
	private static function lessons( int $course_id ): array {
		return array_map( 'intval', (array) Sensei()->course->course_lessons( $course_id, 'publish', 'ids' ) );
	}

	/**
	 * A UTC date string as a date object.
	 *
	 * @param string $gmt Date, UTC.
	 */
	private static function at( string $gmt ): \DateTimeImmutable {
		return new \DateTimeImmutable( $gmt, new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Students take courses: the first in each course finishes it, the others are part way through, and with the
	 * Reign Sensei add-on the first two rate it. Start and completion dates are moved onto the build timeline.
	 *
	 * @param Context $ctx Context.
	 */
	private function learners( Context $ctx ): void {
		$students = $ctx->people( 'student' );
		$bodies   = self::reviews_supported() ? (array) $this->content()['reviews'] : array();
		if ( ! $students ) {
			return;
		}
		// Completing a course redirects the visitor to the Course Completed page and ends the request; outside REST
		// (WP-CLI) that would stop the build at the first finished course.
		$redirect = array( Sensei()->frontend, 'redirect_to_course_completed_page' );
		$restore  = is_object( Sensei()->frontend ) && remove_action( 'sensei_course_status_updated', $redirect, 1000 );
		$r        = 0;
		foreach ( Ledger::ids( $this->id(), 'course' ) as $k => $course_id ) {
			$course_id = (int) $course_id;
			$lessons   = self::lessons( $course_id );
			$take      = array_slice( array_merge( array_slice( $students, $k ), array_slice( $students, 0, $k ) ), 0, self::class_size( $k ) );
			$start     = (string) get_post_field( 'post_date_gmt', $course_id );
			// One ledger row per course lists its students (the ledger keys rows by object id).
			$ctx->record( $this->id(), 'enrollment', $course_id, 'sample', array( 'users' => array_map( 'intval', $take ) ) );
			$enrolment = \Sensei_Course_Enrolment::get_course_instance( $course_id );
			foreach ( $take as $s => $uid ) {
				$uid  = (int) $uid;
				$when = $ctx->after( $start, $s + 1 );
				if ( ! $enrolment->enrol( $uid ) ) {
					$ctx->warn( $this->id(), 'enrolment', __( 'Sensei LMS did not enrol the student.', 'wbcom-demo-installer' ) );
					continue;
				}
				$share = 0 === $s ? 1 : ( ( $s * 37 ) % 90 ) / 100;
				foreach ( array_slice( $lessons, 0, (int) floor( count( $lessons ) * $share ) ) as $n => $lesson_id ) {
					// Completing every lesson completes the course, as Sensei works it out.
					\Sensei_Utils::sensei_start_lesson( $lesson_id, $uid, true );
					$progress = Sensei()->lesson_progress_repository->get( $lesson_id, $uid );
					if ( $progress ) {
						$done = self::at( $ctx->after( $when, $n + 1 ) );
						$progress->set_started_at( $done );
						$progress->complete( $done );
						Sensei()->lesson_progress_repository->save( $progress );
					}
				}
				$course = Sensei()->course_progress_repository->get( $course_id, $uid );
				if ( $course ) {
					$course->set_started_at( self::at( $when ) );
					if ( 'complete' === $course->get_status() ) {
						$course->complete( self::at( $ctx->after( $when, count( $lessons ) + 1 ) ) );
					}
					Sensei()->course_progress_repository->save( $course );
				}
				if ( $s < 2 && $bodies ) {
					$this->review( $course_id, $uid, (array) $bodies[ $r % count( $bodies ) ], $ctx->after( $when, 40 + $s ), $ctx );
					++$r;
				}
			}
		}
		if ( $restore ) {
			add_action( 'sensei_course_status_updated', $redirect, 1000, 3 );
		}
	}

	/**
	 * A course rating as the Reign Sensei add-on stores one from its review form: an approved course comment by
	 * the student with "rating" and "title" comment meta. The add-on's handler answers a form post as the logged-in
	 * student, so the same row is written with WordPress's comment API.
	 *
	 * @param int                   $course_id Course.
	 * @param int                   $uid       Student.
	 * @param array<int,int|string> $body      [ rating, title, text ].
	 * @param string                $date      Date, UTC.
	 * @param Context               $ctx       Context.
	 */
	private function review( int $course_id, int $uid, array $body, string $date, Context $ctx ): void {
		$user = get_userdata( $uid );
		if ( ! $user ) {
			return;
		}
		$id = wp_insert_comment(
			array(
				'comment_post_ID'      => $course_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => (string) $body[2],
				'comment_type'         => 'comment',
				'comment_approved'     => 1,
				'user_id'              => $uid,
				'comment_date_gmt'     => $date,
				// time-ok: WordPress stores this field in site time beside its _gmt column; the build works in UTC.
				'comment_date'         => get_date_from_gmt( $date ),
				'comment_meta'         => array(
					'rating' => max( 1, min( 5, (int) $body[0] ) ),
					'title'  => (string) $body[1],
				),
			)
		);
		if ( $id ) {
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
			case 'enrollment': // Object id is the course; meta lists the students.
				$gone      = true;
				$enrolment = \Sensei_Course_Enrolment::get_course_instance( $id );
				foreach ( (array) ( $meta['users'] ?? array() ) as $uid ) {
					\Sensei_Utils::sensei_remove_user_from_course( $id, (int) $uid );
					$enrolment->withdraw( (int) $uid );
					$gone = $gone && ! $enrolment->is_enrolled( (int) $uid, false );
				}
				return $gone;
			case 'review':
				if ( get_comment( $id ) ) {
					wp_delete_comment( $id, true );
				}
				return ! get_comment( $id );
			case 'course': // Sensei removes the course's and lesson's progress rows with them.
			case 'lesson':
				return self::delete_post( $id );
			case 'attachment':
				return self::delete_post( $id, true );
			case 'module':
				return self::remove_unused_term( $id, 'module' );
			case 'term':
				return self::remove_unused_term( $id, (string) ( $meta['taxonomy'] ?? 'course-category' ) );
		}
		return true;
	}

	/**
	 * Sensei's Courses page (its course list, the course archive), 0 when the site has none.
	 */
	private static function courses_page(): int {
		$page = function_exists( 'Sensei' ) ? (int) Sensei()->settings->get( 'course_page' ) : 0;
		return $page && 'publish' === get_post_status( $page ) ? $page : 0;
	}

	/**
	 * Course catalogue: the Courses page, else the course archive.
	 */
	private static function catalog(): string {
		$page = self::courses_page();
		return $page ? (string) get_permalink( $page ) : (string) get_post_type_archive_link( 'course' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function front_page(): int {
		return self::courses_page();
	}

	/**
	 * {@inheritDoc}
	 */
	public function full_width_archives(): array {
		return array( 'course' );
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
	 * The course site's part of the designed home page: the newest courses as a live course list (a query loop the
	 * owner can edit) and the course topics.
	 *
	 * @param Context $ctx See parent.
	 */
	public function home( Context $ctx ): array {
		if ( ! $this->available() ) {
			return array();
		}
		$h        = (array) ( $this->content()['home'] ?? array() );
		$catalog  = self::catalog();
		$mine     = (int) Sensei()->settings->get_my_courses_page_id();
		$mine_url = $mine && 'publish' === get_post_status( $mine ) ? (string) get_permalink( $mine ) : '';
		$buttons  = array_values( array_filter( array( $catalog ? array( (string) $h['browse'], $catalog ) : null, $mine_url ? array( (string) $h['second'], $mine_url ) : null ) ) );
		$stats    = array(
			array( (int) ( wp_count_posts( 'course' )->publish ?? 0 ), __( 'courses', 'wbcom-demo-installer' ) ),
			array( (int) ( wp_count_posts( 'lesson' )->publish ?? 0 ), __( 'lessons', 'wbcom-demo-installer' ) ),
			array( count( $ctx->people( 'student' ) ), __( 'students', 'wbcom-demo-installer' ) ),
		);
		$topics   = taxonomy_exists( 'course-category' ) ? LearnDash::course_topics( 'course-category' ) : array();

		$sections = array(
			Blocks::heading( (string) $h['featured'][0] ) . Blocks::paragraph( (string) $h['featured'][1] ) . Blocks::query( 'course', 3 )
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
		$page = self::courses_page();
		$url  = self::catalog();
		if ( ! $url ) {
			return array();
		}
		return array(
			$page
				? array(
					'title'   => __( 'Courses', 'wbcom-demo-installer' ),
					'page_id' => $page,
				)
				: array(
					'title' => __( 'Courses', 'wbcom-demo-installer' ),
					'url'   => $url,
				),
		);
	}

	/**
	 * The lesson the guide's student continues with: their first lesson not done in the first course they are part
	 * way through, else the first lesson of the first course.
	 *
	 * @param int[] $courses Built courses.
	 * @param int   $uid     Sample student, 0 when none.
	 */
	private static function next_lesson( array $courses, int $uid ): int {
		sort( $courses );
		foreach ( $uid ? $courses : array() as $course_id ) {
			if ( ! \Sensei_Course_Enrolment::get_course_instance( (int) $course_id )->is_enrolled( $uid ) || \Sensei_Utils::user_completed_course( (int) $course_id, $uid ) ) {
				continue;
			}
			foreach ( self::lessons( (int) $course_id ) as $lesson_id ) {
				if ( ! \Sensei_Utils::user_completed_lesson( $lesson_id, $uid ) ) {
					return $lesson_id;
				}
			}
		}
		$lessons = $courses ? self::lessons( (int) $courses[0] ) : array();
		return $lessons ? $lessons[0] : 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function guide(): array {
		if ( ! $this->available() ) {
			return array();
		}
		$t      = $this->content()['guide'];
		$built  = Ledger::ids( $this->id(), 'course' );
		$course = $built ? (int) min( $built ) : 0;
		$lesson = self::next_lesson( $built, People::user_id( 'student' ) );
		$mine   = (int) Sensei()->settings->get_my_courses_page_id();
		$admin  = static fn( string $page ): string => admin_url( 'admin.php?page=' . $page );
		$roles  = array();
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
					'lesson'    => $lesson ? (string) get_permalink( $lesson ) : null,
					'dashboard' => $mine && 'publish' === get_post_status( $mine ) ? (string) get_permalink( $mine ) : null,
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
					'courses'  => admin_url( 'edit.php?post_type=course' ),
					'students' => $admin( 'sensei_learners' ),
					'reports'  => $admin( 'sensei_reports' ),
					'settings' => $admin( 'sensei-settings' ),
				)
			),
			'highlights'   => self::guide_highlights( $t['highlights'] ),
		);
	}
}
