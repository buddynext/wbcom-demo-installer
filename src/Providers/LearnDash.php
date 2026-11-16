<?php
/**
 * LearnDash: courses with lessons and topics, course photos, categories, and sample students part way through
 * them. Replaces the guide-only integration of the same id and keeps its "Try the demo" text.
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
 * LearnDash provider. Everything goes through LearnDash's own functions: course settings, the course builder
 * steps, course access and mark-complete, so progress bars, reports and the profile show the sample work.
 */
final class LearnDash extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'learndash';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'LearnDash';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'LEARNDASH_VERSION' ) && function_exists( 'ld_update_course_access' ) && function_exists( 'learndash_update_setting' ) && class_exists( 'LDLMS_Factory_Post' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'LEARNDASH_VERSION' ) ? (string) LEARNDASH_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '4.0.0';
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
		return 41; // Next to Learnomy.
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		$c = $this->content();
		return sprintf(
			/* translators: 1: courses, 2: categories */
			__( '%1$d courses in %2$d categories, with lessons, topics and students making progress', 'wbcom-demo-installer' ),
			count( $c['courses'] ),
			count( $c['categories'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$c       = $this->content();
		$lessons = 0;
		$topics  = 0;
		foreach ( $c['courses'] as $course ) {
			$lessons += count( $course['sections'] );
			foreach ( $course['sections'] as $section ) {
				$topics += count( $section[1] );
			}
		}
		$plan = array(
			'course'     => array( count( $c['courses'] ), 'sample', __( 'courses', 'wbcom-demo-installer' ) ),
			'lesson'     => array( $lessons, 'sample', __( 'lessons', 'wbcom-demo-installer' ) ),
			'topic'      => array( $topics, 'sample', __( 'topics', 'wbcom-demo-installer' ) ),
			'enrollment' => array( count( $c['courses'] ), 'sample', __( 'courses with sample students', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $c['courses'] ), 'sample', __( 'course photos', 'wbcom-demo-installer' ) ),
		);
		if ( function_exists( 'learndash_get_currency_code' ) && '' === (string) learndash_get_currency_code() ) {
			$plan['currency'] = array( 1, 'setup', __( 'store currency (USD)', 'wbcom-demo-installer' ) );
		}
		$terms = taxonomy_exists( 'ld_course_category' ) ? self::missing_terms( array( 'ld_course_category' => array_values( (array) $c['categories'] ) ) ) : 0;
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
		$steps = array( 'setup', 'categories' );
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
				$this->ensure_tables();
				$this->ensure_currency( $ctx );
				break;
			case 'categories':
				$map = array();
				// Course categories are a LearnDash setting; when it is off the taxonomy does not exist.
				if ( taxonomy_exists( 'ld_course_category' ) ) {
					foreach ( (array) $this->content()['categories'] as $key => $label ) {
						$map[ $key ] = $this->ensure_term( $label, 'ld_course_category', $ctx );
					}
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
	 * LearnDash keeps progress in its own activity tables and creates them from an admin screen, so a site where
	 * LearnDash was turned on without visiting wp-admin (WP-CLI, a host's installer) has none and every bit of
	 * progress would be lost. Create them through LearnDash's own upgrade, only when missing. They belong to
	 * LearnDash, so Finish leaves them.
	 */
	private function ensure_tables(): void {
		global $wpdb;
		$table = class_exists( 'LDLMS_DB' ) ? \LDLMS_DB::get_table_name( 'user_activity' ) : '';
		if ( ! $table || $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check.
			return;
		}
		$upgrade = class_exists( 'Learndash_Admin_Data_Upgrades' ) ? \Learndash_Admin_Data_Upgrades::get_instance( 'Learndash_Admin_Data_Upgrades_User_Activity_DB_Table' ) : null;
		if ( $upgrade && method_exists( $upgrade, 'upgrade_db_tables' ) ) {
			$upgrade->upgrade_db_tables();
			if ( defined( 'LEARNDASH_SETTINGS_DB_VERSION' ) && method_exists( $upgrade, 'set_data_settings' ) ) {
				$upgrade->set_data_settings( 'db_version', LEARNDASH_SETTINGS_DB_VERSION );
			}
		}
	}

	/**
	 * A currency, so paid courses show $49.00 and not a bare 49, only when the site has none. Set through
	 * LearnDash's settings API: its settings object saves its own copy over a plain option write. Recorded as
	 * setup, so "Keep setup" keeps it and "Remove everything" clears it again.
	 *
	 * @param Context $ctx Context.
	 */
	private function ensure_currency( Context $ctx ): void {
		if ( function_exists( 'learndash_get_currency_code' ) && '' === (string) learndash_get_currency_code() && class_exists( 'LearnDash_Settings_Section' ) ) {
			\LearnDash_Settings_Section::set_section_setting( 'LearnDash_Settings_Section_Payments_Defaults', 'currency', 'USD' );
			$ctx->record( $this->id(), 'currency', 1, 'setup' );
		}
	}

	/**
	 * One course: the course, a lesson per section, a topic per section lesson, then the course builder steps.
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

		// The page shows the summary (excerpt) under the title, so the description adds what the course covers.
		$learn = '';
		foreach ( $c['sections'] as $section ) {
			foreach ( $section[1] as $item ) {
				$learn .= '<li>' . esc_html( $item[0] ) . '</li>';
			}
		}
		$body      = wpautop( $c['desc'] ) . '<h3>' . esc_html__( 'What you will learn', 'wbcom-demo-installer' ) . '</h3><ul>' . $learn . '</ul>';
		$course_id = $this->post( 'sfwd-courses', $c['title'], $body, $teacher, $date, $c['desc'] );
		if ( ! $course_id ) {
			$ctx->warn( $this->id(), 'course ' . $c['title'], __( 'LearnDash did not save it.', 'wbcom-demo-installer' ) );
			return;
		}
		$ctx->record( $this->id(), 'course', $course_id );
		$price = (float) $c['price'];
		learndash_update_setting( $course_id, 'course_price_type', $price > 0 ? 'paynow' : 'free' );
		if ( $price > 0 ) {
			learndash_update_setting( $course_id, 'course_price', (string) $price );
		}
		if ( ! empty( $cats[ $c['category'] ] ) ) {
			wp_set_object_terms( $course_id, array( (int) $cats[ $c['category'] ] ), 'ld_course_category' );
		}
		$image = $ctx->attachment( $c['image'], $teacher, $this->id() );
		if ( $image ) {
			set_post_thumbnail( $course_id, $image );
		}

		$steps = array(
			'sfwd-lessons' => array(),
			'sfwd-quiz'    => array(),
		);
		foreach ( $c['sections'] as $s => $section ) {
			list( $title, $items ) = $section;
			$intro                 = sprintf(
				/* translators: %s: course title */
				__( 'Part of %s.', 'wbcom-demo-installer' ),
				$c['title']
			);
			$lesson_id = $this->post( 'sfwd-lessons', $title, wpautop( $intro ), $teacher, $ctx->after( $date, $s ) );
			if ( ! $lesson_id ) {
				continue;
			}
			$ctx->record( $this->id(), 'lesson', $lesson_id );
			learndash_update_setting( $lesson_id, 'course', $course_id );
			if ( 0 === $s ) {
				learndash_update_setting( $lesson_id, 'sample_lesson', 'on' ); // A free preview, as course pages usually offer.
			}
			$topics = array();
			foreach ( $items as $t => $item ) {
				$topic_id = $this->post( 'sfwd-topic', $item[0], wpautop( $item[1] ), $teacher, $ctx->after( $date, $s * 10 + $t + 1 ) );
				if ( ! $topic_id ) {
					continue;
				}
				$ctx->record( $this->id(), 'topic', $topic_id );
				learndash_update_setting( $topic_id, 'course', $course_id );
				learndash_update_setting( $topic_id, 'lesson', $lesson_id );
				$topics[ $topic_id ] = array();
			}
			$steps['sfwd-lessons'][ $lesson_id ] = array(
				'sfwd-topic' => $topics,
				'sfwd-quiz'  => array(),
			);
		}
		\LDLMS_Factory_Post::course_steps( $course_id )->set_steps( $steps );
	}

	/**
	 * Insert a LearnDash post owned by a sample person.
	 *
	 * @param string $type    Post type.
	 * @param string $title   Title.
	 * @param string $content Content (HTML).
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
	 * Students take courses: the first in each course finishes it, the others are part way through.
	 *
	 * @param Context $ctx Context.
	 */
	private function learners( Context $ctx ): void {
		$students = $ctx->people( 'student' );
		if ( ! $students ) {
			return;
		}
		foreach ( Ledger::ids( $this->id(), 'course' ) as $k => $course_id ) {
			$lessons = function_exists( 'learndash_course_get_lessons' ) ? learndash_course_get_lessons( $course_id, array( 'return_type' => 'WP_Post' ) ) : array();
			$lessons = is_array( $lessons ) ? array_values( $lessons ) : array();
			$take    = array_slice( array_merge( array_slice( $students, $k ), array_slice( $students, 0, $k ) ), 0, 3 + $k % 4 );
			// One ledger row per course lists its students (the ledger keys rows by object id).
			$ctx->record( $this->id(), 'enrollment', (int) $course_id, 'sample', array( 'users' => array_map( 'intval', $take ) ) );
			foreach ( $take as $s => $uid ) {
				ld_update_course_access( $uid, $course_id );
				// Progress counted in topics, so short courses get partial progress too: the first student
				// finishes, the others are spread from not started to most of the way.
				$share = 0 === $s ? 1 : ( ( $s * 37 ) % 90 ) / 100;
				$total = 0;
				$plan  = array();
				foreach ( $lessons as $lesson ) {
					$topics              = (array) learndash_get_topic_list( $lesson->ID, $course_id );
					$plan[ $lesson->ID ] = $topics;
					$total              += max( 1, count( $topics ) );
				}
				$left = (int) floor( $total * $share );
				foreach ( $plan as $lesson_id => $topics ) {
					if ( $left <= 0 ) {
						break;
					}
					// A lesson counts as one step when it has no topics, otherwise it is done once all its topics are.
					$finished = true;
					foreach ( ( $topics ? $topics : array( null ) ) as $topic ) {
						if ( $left <= 0 ) {
							$finished = false;
							break;
						}
						if ( $topic ) {
							learndash_process_mark_complete( $uid, $topic->ID, false, $course_id );
						}
						--$left;
					}
					if ( $finished ) {
						learndash_process_mark_complete( $uid, $lesson_id, false, $course_id );
					}
				}
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
			case 'enrollment': // Object id is the course; meta lists the students.
				foreach ( (array) ( $meta['users'] ?? array() ) as $uid ) {
					if ( function_exists( 'learndash_delete_course_progress' ) ) {
						learndash_delete_course_progress( $id, (int) $uid );
					}
					if ( function_exists( 'ld_update_course_access' ) ) {
						ld_update_course_access( (int) $uid, $id, true );
					}
				}
				return true;
			case 'course':
			case 'lesson':
			case 'topic':
				return self::delete_post( $id );
			case 'attachment':
				return self::delete_post( $id, true );
			case 'term':
				return self::remove_unused_term( $id, (string) ( $meta['taxonomy'] ?? 'ld_course_category' ) );
			case 'currency':
				if ( class_exists( 'LearnDash_Settings_Section' ) ) {
					\LearnDash_Settings_Section::set_section_setting( 'LearnDash_Settings_Section_Payments_Defaults', 'currency', '' );
				}
				return true;
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		$url = (string) get_post_type_archive_link( 'sfwd-courses' );
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
	public function offers_home(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * The course site's part of the designed home page: LearnDash's own course list block and the course topics.
	 *
	 * @param Context $ctx See parent.
	 */
	public function home( Context $ctx ): array {
		$h       = (array) ( $this->content()['home'] ?? array() );
		$archive = (string) get_post_type_archive_link( 'sfwd-courses' );
		$lessons = Ledger::ids( $this->id(), 'lesson' );
		$preview = $lessons ? (string) get_permalink( (int) min( $lessons ) ) : ''; // The first course's free first lesson.
		$buttons = array_values( array_filter( array( $archive ? array( (string) $h['browse'], $archive ) : null, $preview ? array( (string) $h['second'], $preview ) : null ) ) );
		$stats   = array(
			array( (int) ( wp_count_posts( 'sfwd-courses' )->publish ?? 0 ), __( 'courses', 'wbcom-demo-installer' ) ),
			array( (int) ( wp_count_posts( 'sfwd-lessons' )->publish ?? 0 ), __( 'lessons', 'wbcom-demo-installer' ) ),
			array( count( $ctx->people( 'student' ) ), __( 'students', 'wbcom-demo-installer' ) ),
		);
		$topics  = taxonomy_exists( 'ld_course_category' ) ? self::course_topics( 'ld_course_category' ) : array();

		$sections = array(
			Blocks::heading( (string) $h['featured'][0] ) . Blocks::paragraph( (string) $h['featured'][1] )
				. '<!-- wp:learndash/ld-course-list {"per_page":"6","col":3} /-->'
				. ( $archive ? Blocks::button( (string) $h['all'], $archive ) : '' ),
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
	 * Up to four course topics with courses, most courses first: the name linking to the topic and its course count,
	 * one column each, in core blocks. Shared by the LMS providers (Tutor LMS and LifterLMS call it too).
	 *
	 * @param string $taxonomy Course category taxonomy.
	 * @return string[] Column markup per topic.
	 */
	public static function course_topics( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 4,
			)
		);
		$cols  = array();
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$link = get_term_link( $t );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$cols[] = Blocks::link_heading( $t->name, $link, 3 )
				/* translators: %s: number of courses */
				. Blocks::paragraph( sprintf( _n( '%s course', '%s courses', (int) $t->count, 'wbcom-demo-installer' ), number_format_i18n( (int) $t->count ) ) );
		}
		return $cols;
	}

	/**
	 * The "Try the demo" text stays in content/integrations.php, shared with the guide-only integration.
	 */
	public function guide(): array {
		return ( new Integration( 'learndash' ) )->guide();
	}
}
