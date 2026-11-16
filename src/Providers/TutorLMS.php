<?php
/**
 * Tutor LMS: courses with topics and lessons, course photos, categories, instructors, and sample students part way
 * through them, with star ratings.
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
 * Tutor LMS provider. Courses, topics and lessons are posts in the shape Tutor's own course builder saves (a topic's
 * parent is the course, a lesson's parent is its topic); enrolment, lesson progress, course completion and the
 * instructor role go through Tutor's models and utilities. Tutor's bundled sample import is not used: it downloads a
 * remote file and reports only course ids, so its lessons could not be recorded in the ledger.
 */
final class TutorLMS extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'tutorlms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Tutor LMS';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'TUTOR_VERSION' ) && function_exists( 'tutor' ) && function_exists( 'tutor_utils' )
			&& class_exists( '\Tutor\Models\EnrollmentModel' ) && class_exists( '\Tutor\Models\LessonModel' ) && class_exists( '\Tutor\Models\CourseModel' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'TUTOR_VERSION' ) ? (string) TUTOR_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '3.0.0';
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
		return 42; // Next to Learnomy and LearnDash.
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		$c = $this->content();
		return sprintf(
			/* translators: 1: courses, 2: categories */
			__( '%1$d courses in %2$d categories, with lessons, ratings and students making progress', 'wbcom-demo-installer' ),
			count( $c['courses'] ),
			count( $c['categories'] )
		);
	}

	/**
	 * Tutor redirects to its setup wizard on the first admin visit, then once to its "what's new" page. The demo
	 * already has the courses and pages the wizard sets up, so both are marked as seen. A new install also lacks the
	 * course page options Tutor's settings screen saves as on by default (reviews, students enrolled, level,
	 * duration), so the ratings and class sizes would not show; they are set to those defaults.
	 */
	public function settings(): array {
		return array(
			'tutor_option'      => array(
				'enable_course_review'         => 'on',
				'enable_course_total_enrolled' => 'on',
				'enable_course_level'          => 'on',
				'enable_course_duration'       => 'on',
				'enable_course_update_date'    => 'on',
			),
			'tutor_wizard'      => 'active',
			'tutor-new-feature' => defined( 'TUTOR_VERSION' ) ? (string) TUTOR_VERSION : '',
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
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$c       = $this->content();
		$topics  = 0;
		$lessons = 0;
		$reviews = 0;
		foreach ( array_values( $c['courses'] ) as $k => $course ) {
			$topics += count( $course['sections'] );
			foreach ( $course['sections'] as $section ) {
				$lessons += count( $section[1] );
			}
			$reviews += min( 2, self::class_size( $k ) );
		}
		$plan  = array(
			'course'     => array( count( $c['courses'] ), 'sample', __( 'courses', 'wbcom-demo-installer' ) ),
			'topic'      => array( $topics, 'sample', __( 'topics', 'wbcom-demo-installer' ) ),
			'lesson'     => array( $lessons, 'sample', __( 'lessons', 'wbcom-demo-installer' ) ),
			'enrollment' => array( count( $c['courses'] ), 'sample', __( 'courses with sample students', 'wbcom-demo-installer' ) ),
			'review'     => array( $reviews, 'sample', __( 'course ratings', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $c['courses'] ), 'sample', __( 'course photos', 'wbcom-demo-installer' ) ),
		);
		$terms = taxonomy_exists( 'course-category' ) ? self::missing_terms( array( 'course-category' => array_values( (array) $c['categories'] ) ) ) : 0;
		if ( $terms ) {
			$plan['term'] = array( $terms, 'setup', __( 'course categories', 'wbcom-demo-installer' ) );
		}
		return $plan;
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
	 * One course: the course with its price, level, duration and "what you will learn", a topic per section and a
	 * lesson per section lesson. The course author is a sample instructor, who gets Tutor's instructor role.
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
		// The instructor role and its approval live on the sample person and go with them on Finish.
		tutor_utils()->add_instructor_role( $teacher );
		self::tour_seen( $teacher );

		$learn = array();
		foreach ( $c['sections'] as $section ) {
			foreach ( $section[1] as $item ) {
				$learn[] = $item[0];
			}
		}
		$course_id = $this->post( tutor()->course_post_type, $c['title'], wpautop( $c['desc'] ), $teacher, $date, 0, 0, $c['desc'] );
		if ( ! $course_id ) {
			$ctx->warn( $this->id(), 'course ' . $c['title'], __( 'Tutor LMS did not save it.', 'wbcom-demo-installer' ) );
			return;
		}
		$ctx->record( $this->id(), 'course', $course_id );
		$price = (float) $c['price'];
		update_post_meta( $course_id, '_tutor_course_price_type', $price > 0 ? 'paid' : 'free' );
		if ( $price > 0 ) {
			update_post_meta( $course_id, 'tutor_course_price', $price );
		}
		$levels = array( 'beginner', 'intermediate', 'expert' );
		update_post_meta( $course_id, '_tutor_course_level', in_array( $c['difficulty'], $levels, true ) ? $c['difficulty'] : 'all_levels' );
		update_post_meta( $course_id, '_tutor_course_benefits', implode( "\n", $learn ) );
		$minutes = count( $learn ) * 12; // About twelve minutes a lesson.
		update_post_meta(
			$course_id,
			'_course_duration',
			array(
				'hours'   => (string) intdiv( $minutes, 60 ),
				'minutes' => (string) ( $minutes % 60 ),
				'seconds' => '0',
			)
		);
		if ( ! empty( $cats[ $c['category'] ] ) ) {
			wp_set_object_terms( $course_id, array( (int) $cats[ $c['category'] ] ), 'course-category' );
		}
		$image = $ctx->attachment( $c['image'], $teacher, $this->id() );
		if ( $image ) {
			set_post_thumbnail( $course_id, $image );
		}

		foreach ( $c['sections'] as $s => $section ) {
			list( $title, $items ) = $section;
			$topic_id              = $this->post( tutor()->topics_post_type, $title, '', $teacher, $ctx->after( $date, $s ), $course_id, $s + 1 );
			if ( ! $topic_id ) {
				continue;
			}
			$ctx->record( $this->id(), 'topic', $topic_id );
			foreach ( $items as $t => $item ) {
				$lesson_id = $this->post( tutor()->lesson_post_type, $item[0], wpautop( $item[1] ), $teacher, $ctx->after( $date, $s * 10 + $t + 1 ), $topic_id, $t + 1 );
				if ( $lesson_id ) {
					$ctx->record( $this->id(), 'lesson', $lesson_id );
				}
			}
		}
	}

	/**
	 * Tutor opens a "take the tour" modal over the dashboard and lessons until a person dismisses it, which would
	 * cover the demo when the visitor switches to a sample person. Marked as seen on the sample person only (it goes
	 * with them on Finish), the user meta Tutor's own Skip button sets.
	 *
	 * @param int $user_id Sample person.
	 */
	private static function tour_seen( int $user_id ): void {
		update_user_meta( $user_id, defined( '\TUTOR\User::TOUR_COMPLETED_META' ) ? \TUTOR\User::TOUR_COMPLETED_META : '_tutor_tour_completed', true );
	}

	/**
	 * Insert a Tutor post owned by a sample person.
	 *
	 * @param string $type      Post type.
	 * @param string $title     Title.
	 * @param string $content   Content (HTML).
	 * @param int    $author    Author.
	 * @param string $date      Date, UTC.
	 * @param int    $parent_id Parent post (course for a topic, topic for a lesson).
	 * @param int    $order     Position under the parent.
	 * @param string $excerpt   Excerpt.
	 * @return int Post id, 0 on failure.
	 */
	private function post( string $type, string $title, string $content, int $author, string $date, int $parent_id = 0, int $order = 0, string $excerpt = '' ): int {
		$id = wp_insert_post(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'post_title'     => $title,
				'post_content'   => $content,
				'post_excerpt'   => $excerpt,
				'post_author'    => $author,
				'post_parent'    => $parent_id,
				'menu_order'     => $order,
				'comment_status' => 'open',
				'post_date_gmt'  => $date,
				'post_date'      => get_date_from_gmt( $date ),
			),
			true
		);
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * A course's lessons in course order: its topics by position, then each topic's lessons by position.
	 *
	 * @param int $course_id Course.
	 * @return int[]
	 */
	private static function lessons( int $course_id ): array {
		$children = static fn( int $parent_id, string $type ): array => array_map(
			'intval',
			get_posts(
				array(
					'post_type'   => $type,
					'post_parent' => $parent_id,
					'post_status' => 'publish',
					'orderby'     => 'menu_order',
					'order'       => 'ASC',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
		$out      = array();
		foreach ( $children( $course_id, tutor()->topics_post_type ) as $topic_id ) {
			$out = array_merge( $out, $children( $topic_id, tutor()->lesson_post_type ) );
		}
		return $out;
	}

	/**
	 * Students take courses: the first in each course finishes it, the others are part way through, and the first
	 * two rate it.
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
			$lessons = self::lessons( (int) $course_id );
			$take    = array_slice( array_merge( array_slice( $students, $k ), array_slice( $students, 0, $k ) ), 0, self::class_size( $k ) );
			$start   = (string) get_post_field( 'post_date_gmt', $course_id );
			// One ledger row per course lists its students (the ledger keys rows by object id).
			$ctx->record( $this->id(), 'enrollment', (int) $course_id, 'sample', array( 'users' => array_map( 'intval', $take ) ) );
			foreach ( $take as $s => $uid ) {
				$when = $ctx->after( $start, $s + 1 );
				// Paid courses enrol as "pending" until an order is paid; a sample student is enrolled outright,
				// dated on the build timeline.
				$data = static function ( array $data ) use ( $when ): array {
					$data['post_status']   = 'completed';
					$data['post_date_gmt'] = $when;
					$data['post_date']     = get_date_from_gmt( $when );
					return $data;
				};
				add_filter( 'tutor_enroll_data', $data );
				$enrolled = \Tutor\Models\EnrollmentModel::do_enroll( (int) $course_id, 0, (int) $uid );
				remove_filter( 'tutor_enroll_data', $data );
				if ( ! $enrolled ) {
					$ctx->warn( $this->id(), 'enrolment', __( 'Tutor LMS did not enrol the student.', 'wbcom-demo-installer' ) );
					continue;
				}
				self::tour_seen( (int) $uid );
				$share = 0 === $s ? 1 : ( ( $s * 37 ) % 90 ) / 100;
				foreach ( array_slice( $lessons, 0, (int) floor( count( $lessons ) * $share ) ) as $lesson_id ) {
					\Tutor\Models\LessonModel::mark_lesson_complete( $lesson_id, (int) $uid );
				}
				if ( 0 === $s ) {
					\Tutor\Models\CourseModel::mark_course_as_completed( (int) $course_id, (int) $uid );
				}
				if ( $s < 2 && $bodies ) {
					$this->review( (int) $course_id, (int) $uid, (array) $bodies[ $r % count( $bodies ) ], $ctx->after( $when, 40 + $s ), $ctx );
					++$r;
				}
			}
		}
	}

	/**
	 * A course rating as Tutor stores one: an approved "tutor_course_rating" comment with a "tutor_rating" value.
	 * Tutor's own review handler answers an AJAX request and needs a logged-in student, so the same row is written
	 * with WordPress's comment API.
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
				'comment_post_ID'  => $course_id,
				'comment_author'   => $user->user_login,
				'comment_content'  => (string) $body[2],
				'comment_type'     => 'tutor_course_rating',
				'comment_approved' => 'approved',
				'comment_agent'    => 'TutorLMSPlugin',
				'user_id'          => $uid,
				'comment_date_gmt' => $date,
				'comment_date'     => get_date_from_gmt( $date ),
				'comment_meta'     => array( 'tutor_rating' => max( 1, min( 5, (int) $body[0] ) ) ),
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
				$gone = true;
				foreach ( (array) ( $meta['users'] ?? array() ) as $uid ) {
					tutor_utils()->delete_course_progress( $id, (int) $uid );
					tutor_utils()->cancel_course_enrol( $id, (int) $uid, 'delete' );
					$gone = $gone && ! \Tutor\Models\EnrollmentModel::is_enrolled( $id, (int) $uid, false );
				}
				return $gone;
			case 'review':
				if ( get_comment( $id ) ) {
					wp_delete_comment( $id, true );
				}
				return ! get_comment( $id );
			case 'course': // Tutor deletes a course's topics, lessons and enrolments with it.
			case 'topic':
			case 'lesson':
				return self::delete_post( $id );
			case 'attachment':
				return self::delete_post( $id, true );
			case 'term':
				return self::remove_unused_term( $id, (string) ( $meta['taxonomy'] ?? 'course-category' ) );
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
	 * The course site's part of the designed home page. Tutor LMS has no course list block, so the newest courses
	 * are cards in core blocks (photo, title, summary) the owner can edit, then the course topics.
	 *
	 * @param Context $ctx See parent.
	 */
	public function home( Context $ctx ): array {
		if ( ! $this->available() ) {
			return array();
		}
		$h         = (array) ( $this->content()['home'] ?? array() );
		$type      = (string) tutor()->course_post_type;
		$catalog   = self::catalog();
		$dashboard = (int) tutor_utils()->get_option( 'tutor_dashboard_page_id' ) ? (string) tutor_utils()->tutor_dashboard_url() : '';
		$buttons   = array_values( array_filter( array( $catalog ? array( (string) $h['browse'], $catalog ) : null, $dashboard ? array( (string) $h['second'], $dashboard ) : null ) ) );
		$stats     = array(
			array( (int) ( wp_count_posts( $type )->publish ?? 0 ), __( 'courses', 'wbcom-demo-installer' ) ),
			array( (int) ( wp_count_posts( (string) tutor()->lesson_post_type )->publish ?? 0 ), __( 'lessons', 'wbcom-demo-installer' ) ),
			array( count( $ctx->people( 'student' ) ), __( 'students', 'wbcom-demo-installer' ) ),
		);
		$courses   = get_posts(
			array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		$topics    = taxonomy_exists( 'course-category' ) ? LearnDash::course_topics( 'course-category' ) : array();

		$sections = array();
		if ( $courses ) {
			$sections[] = Blocks::heading( (string) $h['featured'][0] ) . Blocks::paragraph( (string) $h['featured'][1] ) . Blocks::query( $type, 3 )
				. ( $catalog ? Blocks::button( (string) $h['all'], $catalog ) : '' );
		}
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
	 * Course catalogue: Tutor's course archive.
	 */
	private static function catalog(): string {
		return function_exists( 'tutor' ) ? (string) get_post_type_archive_link( tutor()->course_post_type ) : '';
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
		$t       = $this->content()['guide'];
		$built   = Ledger::ids( $this->id(), 'course' );
		$course  = $built ? (int) min( $built ) : 0;
		$lessons = $course ? self::lessons( $course ) : array();
		$admin   = static fn( string $page ): string => admin_url( 'admin.php?page=' . $page );
		$roles   = array();
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
					'dashboard' => (string) tutor_utils()->tutor_dashboard_url( 'enrolled-courses' ),
					'teach'     => (string) tutor_utils()->tutor_dashboard_url( 'my-courses' ),
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
					'teach'     => array( 'as' => 'instructor' ),
				)
			),
			'owner'        => self::guide_steps(
				$t['owner'],
				array(
					'courses'     => $admin( 'tutor' ),
					'students'    => $admin( 'tutor-students' ),
					'instructors' => $admin( 'tutor-instructors' ),
					'settings'    => $admin( 'tutor_settings' ),
				)
			),
			'highlights'   => self::guide_highlights( $t['highlights'] ),
		);
	}
}
