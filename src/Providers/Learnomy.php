<?php
/**
 * Learnomy: courses with sections and lessons, instructors, enrolments, progress and reviews.
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
		return '2.3.0'; // Latest release; the site check updates an older copy.
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
	public function keeps_logs(): string {
		return __( 'Learnomy keeps an audit log of the removal as a security record, with the sample people removed from it.', 'wbcom-demo-installer' );
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
			'enrollment' => array( $this->enrolment_count(), 'sample', __( 'enrolments', 'wbcom-demo-installer' ) ),
			'quiz'       => array( count( array_filter( array_column( $c['courses'], 'quiz' ) ) ), 'sample', __( 'quizzes', 'wbcom-demo-installer' ) ),
			'question'   => array( array_sum( array_map( static fn( $q ) => count( $q['questions'] ) + ( empty( $q['essay'] ) ? 0 : 1 ), array_filter( array_column( $c['courses'], 'quiz' ) ) ) ), 'sample', __( 'quiz questions', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $c['courses'] ), 'sample', __( 'course images', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * Enrolments learners() makes: 4 to 7 students per course, never more than there are students.
	 */
	private function enrolment_count(): int {
		$students = count( People::indexes( 'student' ) );
		$n        = 0;
		foreach ( array_keys( array_values( $this->content()['courses'] ) ) as $k ) {
			$n += min( 4 + $k % 4, $students );
		}
		return $n;
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
				'thumbnail_url' => $this->thumbnail( $c['image'], $instructor, $ctx ),
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
		if ( ! empty( $c['quiz'] ) && ! empty( $section_id ) ) {
			$this->quiz( $ctx, (int) $course_id, (int) $section_id, $instructor, $c['quiz'] );
		}
	}

	/**
	 * The course's end-of-course quiz in its last section, made by its instructor through Learnomy's REST routes (the
	 * course builder's path, so its quiz defaults apply): single-choice questions and an essay the instructor grades.
	 *
	 * @param Context             $ctx        Context.
	 * @param int                 $course_id  Course.
	 * @param int                 $section_id Last section.
	 * @param int                 $instructor Instructor.
	 * @param array<string,mixed> $q          Quiz content.
	 */
	private function quiz( Context $ctx, int $course_id, int $section_id, int $instructor, array $q ): void {
		$was = get_current_user_id();
		wp_set_current_user( $instructor );
		$rest    = static function ( string $route, array $params ): array {
			$req = new \WP_REST_Request( 'POST', '/learnomy/v1' . $route );
			foreach ( $params as $k => $v ) {
				$req->set_param( $k, $v );
			}
			$res = rest_do_request( $req );
			return $res->is_error() ? array( 'error' => $res->as_error() ) : (array) ( $res->get_data()['data'] ?? array() );
		};
		$quiz    = $rest(
			'/quizzes',
			array(
				'title'         => $q['title'],
				'course_id'     => $course_id,
				'section_id'    => $section_id,
				'status'        => 'published',
				'passing_score' => 70,
			)
		);
		$quiz_id = (int) ( $quiz['id'] ?? 0 );
		if ( ! $quiz_id ) {
			$ctx->warn( $this->id(), 'quiz ' . $q['title'], $quiz['error'] ?? 'not created' );
			wp_set_current_user( $was );
			return;
		}
		$ctx->record( $this->id(), 'quiz', $quiz_id, 'sample', array( 'course' => $course_id ) );
		$questions = array_map(
			static fn( $mc ) => array(
				'title'   => $mc[0],
				'type'    => 'mc_single',
				'answers' => array_map(
					static fn( $text, $i ) => array(
						'content'    => $text,
						'is_correct' => $i === $mc[2],
					),
					$mc[1],
					array_keys( $mc[1] )
				),
			),
			(array) $q['questions']
		);
		if ( ! empty( $q['essay'] ) ) {
			$questions[] = array(
				'title' => $q['essay'][0],
				'type'  => 'essay',
			);
		}
		foreach ( $questions as $question ) {
			$made = $rest( '/questions', $question + array( 'quiz_ids' => array( $quiz_id ) ) );
			$qid  = (int) ( $made['question']['id'] ?? 0 );
			if ( ! $qid ) {
				$ctx->warn( $this->id(), 'quiz question', $made['error'] ?? 'not created' );
				continue;
			}
			$ctx->record( $this->id(), 'question', $qid, 'sample', array( 'quiz' => $quiz_id ) );
		}
		wp_set_current_user( $was );
	}

	/**
	 * A learner takes the course's quiz through Learnomy's quiz service: the right single-choice answers (or one wrong
	 * when $slip) and an essay answer left for the instructor to grade.
	 *
	 * @param int    $uid       Learner.
	 * @param int    $course_id Course.
	 * @param int    $eid       Enrolment.
	 * @param bool   $slip      Answer the last single-choice question wrong.
	 * @param string $essay     The learner's essay answer.
	 * @return int Attempt id, 0 when the course has no quiz.
	 */
	private function take_quiz( int $uid, int $course_id, int $eid, bool $slip, string $essay ): int {
		$quiz = \Learnomy\Models\Quiz::where( array( 'course_id' => $course_id ) );
		$quiz = is_array( $quiz ) ? reset( $quiz ) : null;
		if ( ! $quiz ) {
			return 0;
		}
		$svc     = new \Learnomy\Services\Quiz_Service();
		$attempt = $svc->start_attempt( $uid, (int) $quiz->id, $eid );
		$slots   = \Learnomy\Models\QuizSlot::where( array( 'quiz_id' => (int) $quiz->id ), 'sort_order', 'ASC' );
		$last    = count( $slots ) - 2; // The last single-choice question; the essay comes after it.
		foreach ( array_values( (array) $slots ) as $n => $slot ) {
			$question = \Learnomy\Models\Question::find( (int) $slot->question_id );
			if ( ! $question ) {
				continue;
			}
			if ( 'essay' === $question->type ) {
				$svc->answer_question( $attempt, (int) $question->id, $essay );
				continue;
			}
			$pick = null;
			foreach ( \Learnomy\Models\Answer::get_for_question( (int) $question->id ) as $answer ) {
				$wrong = $slip && $n === $last; // The slip picks a wrong answer.
				if ( $wrong xor (bool) $answer->is_correct ) {
					$pick = (int) $answer->id;
					break;
				}
			}
			if ( $pick ) {
				$svc->answer_question( $attempt, (int) $question->id, $pick );
			}
		}
		$svc->submit_attempt( $attempt );
		return $attempt;
	}

	/**
	 * The instructor grades the attempt's essay with a short note, which finalises it (passed or not).
	 *
	 * @param int $attempt    Attempt.
	 * @param int $instructor Grader.
	 */
	private function grade_essay( int $attempt, int $instructor ): void {
		foreach ( \Learnomy\Models\QuestionResponse::where( array( 'attempt_id' => $attempt ) ) as $response ) {
			$question = \Learnomy\Models\Question::find( (int) $response->question_id );
			if ( $question && 'essay' === $question->type ) {
				( new \Learnomy\Services\Quiz_Service() )->grade_question( (int) $response->id, 1.0, __( 'Specific and doable. Nice work.', 'wbcom-demo-installer' ), $instructor );
			}
		}
	}

	/**
	 * Course thumbnail from the photo pack.
	 *
	 * @param string  $file   Photo path under media/, from the course content.
	 * @param int     $author Instructor.
	 * @param Context $ctx    Context.
	 */
	private function thumbnail( string $file, int $author, Context $ctx ): string {
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
			$take    = array_slice( array_merge( array_slice( $students, $k ), array_slice( $students, 0, $k ) ), 0, 4 + $k % 4 ); // Counted in enrolment_count().
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
				// Learnomy stamps the enrolment "now"; it starts the learner's timeline, before their first lesson.
				\Learnomy\Models\Enrollment::update( (int) $eid, array( 'enrolled_at' => $when ) );
				\WbcomDemo\Core\Clock::set( $when );
				foreach ( array_slice( $lessons, 0, $done ) as $n => $lesson ) {
					$when = $ctx->after( $when, $n + $s );
					$progress->mark_lesson_complete( $uid, (int) $lesson->id, true, 240 + $n * 30, array(), $when );
				}
				// The first two learners take the quiz. The one who finished aces it and the instructor has graded the
				// essay (a result to show); the second slips once and their essay waits in the instructor's queue.
				if ( $s < 2 ) {
					try {
						$attempt = $this->take_quiz( $uid, (int) $course_id, (int) $eid, 1 === $s, (string) ( $this->content()['courses'][ $k ]['quiz']['essay'][1] ?? '' ) );
						$course  = \Learnomy\Models\Course::find( (int) $course_id );
						if ( $attempt && 0 === $s && $course ) {
							$this->grade_essay( $attempt, (int) $course->author_id );
						}
						// Learnomy stamps attempts "now": the quiz follows the learner's last lesson, the grading a day or so later.
						if ( $attempt ) {
							$taken = $ctx->after( $when, 40 + $s );
							$dates = array(
								'started_at'   => $taken,
								'submitted_at' => $taken,
							);
							if ( 0 === $s ) {
								$dates['graded_at'] = $ctx->after( $taken, 41 );
							}
							\Learnomy\Models\QuizAttempt::update( $attempt, $dates );
						}
					} catch ( \Throwable $e ) {
						$ctx->warn( $this->id(), 'quiz attempt', $e->getMessage() );
					}
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
				if ( \Learnomy\Models\Course::find( $id ) ) {
					( new \Learnomy\Services\Course_Service() )->delete( $id );
				}
				return ! \Learnomy\Models\Course::find( $id );
			case 'enrollment':
				// Certificates are kept by Learnomy on purpose; a sample person's certificate goes with them.
				$cert = \Learnomy\Models\Certificate::find_active_for_user_course( (int) ( $row->meta['user'] ?? 0 ), (int) ( $row->meta['course'] ?? 0 ) );
				if ( $cert ) {
					\Learnomy\Models\Certificate::delete( (int) $cert->id );
				}
				if ( \Learnomy\Models\Enrollment::find( $id ) ) {
					( new \Learnomy\Services\Enrollment_Service() )->remove_by_admin( $id );
				}
				// Learnomy keeps an ended enrolment as history (removed with the person); ended is done.
				$after = \Learnomy\Models\Enrollment::find( $id );
				return ! $after || ! in_array( (string) $after->status, \Learnomy\Models\Enrollment::current_statuses(), true );
			case 'category':
				if ( \Learnomy\Models\Category::find( $id ) ) {
					\Learnomy\Models\Category::delete( $id );
				}
				return ! \Learnomy\Models\Category::find( $id );
			case 'attachment':
				return self::delete_post( $id, true );
			case 'question':
				// A bank question outlives its quiz in Learnomy; the model delete clears its answers, slots and responses.
				if ( \Learnomy\Models\Question::find( $id ) ) {
					\Learnomy\Models\Question::delete( $id );
				}
				return ! \Learnomy\Models\Question::find( $id );
			case 'quiz':
				// Attempts and slots go with the quiz; the course delete removes it too when it is already gone.
				if ( \Learnomy\Models\Quiz::find( $id ) ) {
					( new \Learnomy\Services\Quiz_Service() )->delete_quiz( $id );
				}
				return ! \Learnomy\Models\Quiz::find( $id );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'learnomy_activation_redirect' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		// Inside a Wbcom theme the theme's own container sets the width, so Learnomy fills it instead of
		// its narrower 960px default.
		$settings = array(
			'learnomy_setup_complete'       => true,
			'learnomy_onboarding_dismissed' => true,
			'learnomy_settings'             => array(),
		);
		// The test payment method, so a visitor can buy a paid sample course without a real gateway. Never on a
		// site already taking live payments: that would let real buyers check out for free.
		$current = (array) get_option( 'learnomy_settings', array() );
		if ( 'live' !== ( $current['payment_mode'] ?? '' ) ) {
			$settings['learnomy_settings']['enable_test_gateway'] = true;
			$settings['learnomy_settings']['payment_mode']        = 'sandbox';
		}
		if ( in_array( Theme::theme_family(), array( 'BuddyX', 'BuddyX Pro', 'Reign' ), true ) ) {
			$settings['learnomy_settings']['container_width'] = 'none';
		}
		if ( ! $settings['learnomy_settings'] ) {
			unset( $settings['learnomy_settings'] );
		}
		return $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	/**
	 * {@inheritDoc}
	 */
	public function guide(): array {
		if ( ! function_exists( '\\Learnomy\\route_url' ) || ! class_exists( '\\Learnomy\\Models\\Course' ) ) {
			return array();
		}
		$t     = $this->content()['guide'];
		$route = static fn( string $name, array $args = array() ): string => (string) \Learnomy\route_url( $name, $args );
		// The demo's first course, else the paid course with the most students, so "buy it" has something to buy.
		$course = null;
		$built  = Ledger::ids( $this->id(), 'course' );
		$pool   = $built ? \Learnomy\Models\Course::find_published_in( array( (int) min( $built ) ) ) : \Learnomy\Models\Course::list_published( 1, 100 );
		foreach ( $pool as $row ) {
			if ( $built || ( 'paid' === ( $row->access_type ?? '' ) && ( ! $course || (int) $row->student_count > (int) $course->student_count ) ) ) {
				$course = $row;
			}
		}
		$course_url = $course ? $route( 'course', array( 'slug' => (string) $course->slug ) ) : $route( 'catalog' );
		$student    = People::user_id( 'student' );
		$instructor = People::user_id( 'instructor' );
		$admin      = static fn( string $page ): string => admin_url( 'admin.php?page=' . $page );
		return array(
			'name'         => $t['name'],
			'tab'          => $t['tab'],
			'member_label' => $t['member_label'],
			'owner_points' => $t['owner_points'],
			'roles'        => array(
				array(
					'key'    => 'student',
					'user'   => $student,
					'label'  => $t['roles']['student'][0],
					'points' => $t['roles']['student'][1],
				),
				array(
					'key'    => 'instructor',
					'user'   => $instructor,
					'label'  => $t['roles']['instructor'][0],
					'points' => $t['roles']['instructor'][1],
				),
			),
			'member'       => self::guide_steps(
				$t['member'],
				array(
					'catalog' => $route( 'catalog' ),
					'course'  => $course_url,
					'pay'     => $course ? add_query_arg( 'course_id', (int) $course->id, $route( 'enroll' ) ) : $course_url,
					'account' => $route( 'student-dashboard' ),
					'teach'   => $route( 'instructor-grading' ),
				),
				array(
					'catalog' => array(
						'public' => true,
						'as'     => 'student',
					),
					'course'  => array(
						'public' => true,
						'as'     => 'student',
					),
					'pay'     => array(
						'as'        => 'student',
						'highlight' => true,
					),
					'account' => array( 'as' => 'student' ),
					'teach'   => array( 'as' => 'instructor' ),
				)
			),
			'owner'        => self::guide_steps(
				$t['owner'],
				array(
					'courses'      => $admin( 'learnomy-courses' ),
					'students'     => $admin( 'learnomy-students' ),
					'memberships'  => $admin( 'learnomy-memberships' ),
					'transactions' => $admin( 'learnomy-transactions' ),
				)
			),
			'highlights'   => self::guide_highlights( $t['highlights'] ),
		);
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
	 * A learning site: the hero, course and student counts, popular courses and the instructors, in Learnomy's
	 * own blocks.
	 *
	 * @param Context $ctx See parent.
	 */
	public function home( Context $ctx ): array {
		if ( ! function_exists( '\\Learnomy\\route_url' ) || ! class_exists( '\\Learnomy\\Models\\Course' ) || ! class_exists( '\\Learnomy\\Models\\Enrollment' ) ) {
			return array();
		}
		$h       = (array) ( $this->content()['home'] ?? array() );
		$catalog = (string) \Learnomy\route_url( 'catalog' );
		$courses = array(
			'count'   => 6,
			'columns' => 3,
		);
		$people  = array(
			'count'   => 4,
			'columns' => 4,
		);
		return array(
			'hero'     => array(
				'title'   => (string) $h['title'],
				'text'    => (string) $h['text'],
				'image'   => (string) $h['image'],
				'buttons' => array( array( (string) $h['browse'], $catalog ) ),
				'closing' => (string) $h['closing'],
			),
			'stats'    => array(
				array( \Learnomy\Models\Course::count_published( 0 ), __( 'courses', 'wbcom-demo-installer' ) ),
				array( \Learnomy\Models\Enrollment::count_distinct_students(), __( 'students', 'wbcom-demo-installer' ) ),
			),
			'sections' => array(
				Blocks::heading( (string) $h['courses'][0] ) . Blocks::paragraph( (string) $h['courses'][1] )
					. get_comment_delimited_block_content( 'learnomy/course-grid', $courses, '' )
					. Blocks::button( (string) $h['all'], $catalog ),
				Blocks::heading( (string) $h['teachers'][0] ) . Blocks::paragraph( (string) $h['teachers'][1] )
					. get_comment_delimited_block_content( 'learnomy/instructor-grid', $people, '' ),
			),
		);
	}

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
