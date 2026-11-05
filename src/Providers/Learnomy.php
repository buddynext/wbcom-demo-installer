<?php
/**
 * Learnomy: courses with sections and lessons, instructors, enrolments, progress and reviews.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Learnomy provider. Uses Learnomy services and models.
 */
final class Learnomy extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'learnomy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Learnomy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'LEARNOMY_VERSION' ) && class_exists( '\Learnomy\Services\Course_Service' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'LEARNOMY_VERSION' ) ? (string) LEARNOMY_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '2.2.0';
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
		return 40;
	}

	/**
	 * {@inheritDoc}
	 */
	public function pro_label(): string {
		return defined( 'LEARNOMY_PRO_VERSION' ) ? __( 'Pro add-on active', 'wbcom-demo-installer' ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		$c       = $this->content();
		$lessons = 0;
		foreach ( $c['courses'] as $course ) {
			foreach ( $course['sections'] as $section ) {
				$lessons += count( $section[1] );
			}
		}
		return sprintf(
			/* translators: 1: courses, 2: lessons */
			__( '%1$d courses with %2$d lessons, instructors, enrolments, progress and reviews', 'wbcom-demo-installer' ),
			count( $c['courses'] ),
			$lessons
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$c = $this->content();
		return array(
			'category'   => array( count( $c['categories'] ), 'setup', __( 'course categories', 'wbcom-demo-installer' ) ),
			'course'     => array( count( $c['courses'] ), 'sample', __( 'courses', 'wbcom-demo-installer' ) ),
			'enrollment' => array( 0, 'sample', __( 'enrolments', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $c['courses'] ), 'sample', __( 'course images', 'wbcom-demo-installer' ) ),
		);
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
	 * Run a step.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		list( $name, $arg ) = array_pad( explode( ':', $step, 2 ), 2, '0' );
		match ( $name ) {
			'categories' => $this->categories( $ctx ),
			'course'     => $this->course( (int) $arg, $ctx ),
			'learners'   => $this->learners( $ctx ),
			default      => null,
		};
	}

	/**
	 * Course categories (reuses existing ones with the same slug).
	 *
	 * @param Context $ctx Context.
	 */
	private function categories( Context $ctx ): void {
		$ids = array();
		foreach ( $this->content()['categories'] as $slug => $name ) {
			$existing = \Learnomy\Models\Category::find_by_slug( $slug );
			if ( $existing ) {
				$ids[ $slug ] = (int) $existing->id;
				continue;
			}
			$id = \Learnomy\Models\Category::create(
				array(
					'name' => $name,
					'slug' => $slug,
					'type' => 'course',
				)
			);
			if ( $id > 0 ) {
				$ids[ $slug ] = $id;
				$ctx->record( $this->id(), 'category', $id, 'setup' );
			}
		}
		$ctx->set( $this->id(), 'categories', $ids );
	}

	/**
	 * One course with its sections and lessons.
	 *
	 * @param int     $i   Course index.
	 * @param Context $ctx Context.
	 */
	private function course( int $i, Context $ctx ): void {
		$c          = $this->content()['courses'][ $i ] ?? null;
		$categories = (array) $ctx->get( $this->id(), 'categories', array() );
		if ( ! $c ) {
			return;
		}
		$instructor = $ctx->person( $c['instructor'] );
		$user       = get_userdata( $instructor );
		if ( $user && ! in_array( 'lrn_instructor', (array) $user->roles, true ) && get_role( 'lrn_instructor' ) ) {
			$user->add_role( 'lrn_instructor' );
		}
		$svc = new \Learnomy\Services\Course_Service();
		\WbcomDemo\Core\Clock::set( $ctx->date( $i, count( $this->content()['courses'] ), 70 ) );
		$course_id = $svc->create(
			array(
				'title'         => $c['title'],
				'slug'          => sanitize_title( $c['title'] ),
				'description'   => $c['desc'],
				'content'       => wpautop( $c['desc'] ),
				'access_type'   => $c['price'] > 0 ? 'paid' : 'free',
				'price'         => $c['price'],
				'category_id'   => $categories[ $c['category'] ] ?? 0,
				'status'        => 'published',
				'difficulty'    => $c['difficulty'],
				'thumbnail_url' => $this->thumbnail( $i, $instructor, $ctx ),
			),
			$instructor
		);
		\WbcomDemo\Core\Clock::reset();
		if ( ! $course_id ) {
			$ctx->warn( $this->id(), 'course ' . $c['title'], 'not created' );
			return;
		}
		$ctx->record( $this->id(), 'course', (int) $course_id, 'sample', array( 'instructor' => $instructor ) );

		$order = 0;
		foreach ( $c['sections'] as $s => $section ) {
			list( $title, $lessons ) = $section;
			$section_id              = \Learnomy\Models\Section::create(
				array(
					'course_id'  => (int) $course_id,
					'title'      => $title,
					'sort_order' => $s + 1,
				)
			);
			foreach ( $lessons as $l => $lesson ) {
				$html = wpautop( $lesson[1] );
				if ( class_exists( '\Learnomy\Content\Content_HTML' ) && \Learnomy\Content\Content_HTML::storage_enabled() ) {
					$html = \Learnomy\Content\Content_HTML::for_storage( $html );
				}
				\Learnomy\Models\Lesson::create(
					array(
						'course_id'        => (int) $course_id,
						'section_id'       => (int) $section_id,
						'author_id'        => $instructor,
						'title'            => $lesson[0],
						'slug'             => sanitize_title( $lesson[0] ) . '-' . $course_id,
						'content'          => $html,
						'content_type'     => 'text',
						'duration_seconds' => 300 + ( $l * 120 ),
						'sort_order'       => ++$order,
						'is_preview'       => 0 === $s && 0 === $l ? 1 : 0,
						'status'           => 'published',
					)
				);
			}
		}
	}

	/**
	 * Course thumbnail from the photo pack.
	 *
	 * @param int     $i      Course index.
	 * @param int     $author Instructor.
	 * @param Context $ctx    Context.
	 */
	private function thumbnail( int $i, int $author, Context $ctx ): string {
		$files = array( 'creative-studio-portrait.jpg', 'developer-desk.jpg', 'festival-friends.jpg', 'tech-workspace.jpg', 'misty-forest-path.jpg' );
		$file  = 'photos/' . $files[ $i % count( $files ) ];
		if ( ! is_readable( $ctx->media( $file ) ) ) {
			return '';
		}
		$id = $ctx->attachment( $file, $author, $this->id() );
		return $id ? (string) wp_get_attachment_url( $id ) : '';
	}

	/**
	 * Students enrol, make progress, a few finish and review.
	 *
	 * @param Context $ctx Context.
	 */
	private function learners( Context $ctx ): void {
		$students = $ctx->people( 'student' );
		$enrol    = new \Learnomy\Services\Enrollment_Service();
		$progress = new \Learnomy\Services\Progress_Service();
		$reviews  = new \Learnomy\Services\Review_Service();
		$bodies   = $this->content()['reviews'];
		$r        = 0;
		foreach ( Ledger::ids( $this->id(), 'course' ) as $k => $course_id ) {
			$lessons = \Learnomy\Models\Lesson::get_for_course( $course_id );
			$lessons = is_array( $lessons ) ? array_values( $lessons ) : array();
			$take    = array_slice( array_merge( array_slice( $students, $k ), array_slice( $students, 0, $k ) ), 0, 4 + $k % 4 );
			foreach ( $take as $s => $uid ) {
				$eid = $enrol->enroll(
					$uid,
					$course_id,
					'manual',
					array(
						'skip_prerequisites' => true,
						'quiet'              => true,
					)
				);
				if ( is_wp_error( $eid ) || ! $eid ) {
					$ctx->warn( $this->id(), 'enrolment', is_wp_error( $eid ) ? $eid : 'not created' );
					continue;
				}
				$ctx->record(
					$this->id(),
					'enrollment',
					(int) $eid,
					'sample',
					array(
						'user'   => $uid,
						'course' => $course_id,
					)
				);
				// First learner finishes, others are part way through.
				$done = 0 === $s ? count( $lessons ) : (int) floor( count( $lessons ) * ( ( $s * 29 ) % 90 ) / 100 );
				$when = $ctx->date( $k * 6 + $s, 40, 40 );
				\WbcomDemo\Core\Clock::set( $when );
				foreach ( array_slice( $lessons, 0, $done ) as $n => $lesson ) {
					$when = $ctx->after( $when, $n + $s );
					$progress->mark_lesson_complete( $uid, (int) $lesson->id, true, 240 + $n * 30, array(), $when );
				}
				if ( 0 === $s || ( 1 === $s && count( $lessons ) === $done ) ) {
					$body = $bodies[ $r % count( $bodies ) ];
					++$r;
					try {
						$reviews->submit( $uid, $course_id, $body[0], $body[1], $body[2] );
					} catch ( \Throwable $e ) {
						$ctx->warn( $this->id(), 'review', $e->getMessage() );
					}
				}
			}
		}
	}

	/**
	 * Remove one object.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id = (int) $row->object_id;
		switch ( $row->object_type ) {
			case 'course':
				( new \Learnomy\Services\Course_Service() )->delete( $id );
				return true;
			case 'enrollment':
				// Certificates are kept by Learnomy on purpose; a sample person's certificate goes with them.
				$cert = \Learnomy\Models\Certificate::find_active_for_user_course( (int) ( $row->meta['user'] ?? 0 ), (int) ( $row->meta['course'] ?? 0 ) );
				if ( $cert ) {
					\Learnomy\Models\Certificate::delete( (int) $cert->id );
				}
				if ( \Learnomy\Models\Enrollment::find( $id ) ) {
					( new \Learnomy\Services\Enrollment_Service() )->remove_by_admin( $id );
				}
				return true;
			case 'category':
				if ( \Learnomy\Models\Category::find( $id ) ) {
					\Learnomy\Models\Category::delete( $id );
				}
				return true;
			case 'attachment':
				if ( get_post( $id ) ) {
					wp_delete_attachment( $id, true );
				}
				return true;
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		// Inside a Wbcom theme the theme's own container sets the width, so Learnomy fills it instead of
		// its narrower 960px default.
		$settings = array(
			'learnomy_setup_complete'                => true,
			'learnomy_onboarding_dismissed'          => true,
			'transient:learnomy_activation_redirect' => null,
		);
		if ( in_array( Theme::theme_family(), array( 'BuddyX', 'BuddyX Pro', 'Reign' ), true ) ) {
			$settings['learnomy_settings'] = array( 'container_width' => 'none' );
		}
		return $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		return function_exists( '\Learnomy\route_url' )
			? array(
				array(
					'title' => __( 'Courses', 'wbcom-demo-installer' ),
					'url'   => \Learnomy\route_url( 'catalog' ),
				),
			)
			: array();
	}
}
