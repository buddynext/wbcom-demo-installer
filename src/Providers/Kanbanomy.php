<?php
/**
 * Kanbanomy: a small agency's client projects, a public support board and an internal estimates board.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Guide;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Kanbanomy provider. Projects, tasks, members and discussions go through Kanbanomy's own services; milestones,
 * comments, logged time, approvals and column names through its REST routes as the person who would do them.
 * Labels are Kanbanomy's predefined ones, looked up by name and never created, so Finish never touches labels the
 * owner uses (Kanbanomy has no label usage count to check against).
 */
final class Kanbanomy extends Provider {

	/**
	 * The sample person who runs the projects (content `cast`).
	 *
	 * @param Context $ctx Context.
	 */
	private function manager( Context $ctx ): int {
		return self::who( (array) $this->content()['cast']['manager'], $ctx );
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'kanbanomy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Kanbanomy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'KANBANOMY_VERSION' ) && class_exists( '\\ProjectFlow\\Core\\Projects' ) && class_exists( '\\ProjectFlow\\Core\\Tasks' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'KANBANOMY_VERSION' ) ? (string) KANBANOMY_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '1.3.0';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'list';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 82;
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return sprintf(
			/* translators: 1: projects, 2: tasks */
			__( '%1$d projects with %2$d tasks: client work with approvals, a public support board and an internal estimates board', 'wbcom-demo-installer' ),
			count( $this->content()['projects'] ),
			$this->task_count()
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'kanbanomy_wizard_redirect' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function keeps_logs(): string {
		return __( 'Kanbanomy keeps one "project deleted" entry per sample project in its activity log, as a record of who removed it.', 'wbcom-demo-installer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		// The demo creates the projects and pages its own wizard would; skipping it hides no step the site needs.
		return array( 'kanbanomy_wizard_completed' => true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		return array(
			'project' => array( count( $this->content()['projects'] ), 'sample', __( 'projects', 'wbcom-demo-installer' ) ),
			'task'    => array( $this->task_count(), 'sample', __( 'tasks', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * Top-level tasks across every project in the content.
	 */
	private function task_count(): int {
		return (int) array_sum( array_map( static fn( array $p ): int => count( $p['tasks'] ), $this->content()['projects'] ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		$steps = array( 'people', 'projects' );
		foreach ( array_keys( $this->content()['projects'] ) as $i ) {
			$steps[] = 'tasks:' . $i;
		}
		$steps[] = 'discussions';
		return $steps;
	}

	/**
	 * Run a step.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		if ( 'people' === $step ) {
			$this->people( $ctx );
		} elseif ( 'projects' === $step ) {
			$this->projects( $ctx );
		} elseif ( str_starts_with( $step, 'tasks:' ) ) {
			$this->tasks( (int) substr( $step, 6 ), $ctx );
		} elseif ( 'discussions' === $step ) {
			$this->discussions( $ctx );
		}
	}

	/**
	 * A cast reference ([ role tag, index ]) as a sample person's user id.
	 *
	 * @param array<int,mixed> $who Role tag and index.
	 * @param Context          $ctx Context.
	 */
	private static function who( array $who, Context $ctx ): int {
		return $ctx->person( (int) ( $who[1] ?? 0 ), (string) ( $who[0] ?? '' ) );
	}

	/**
	 * Give the manager and the clients their Kanbanomy roles. Sample people are removed on Finish, roles with them.
	 *
	 * @param Context $ctx Context.
	 */
	private function people( Context $ctx ): void {
		$grant = static function ( int $user_id, string $role ): void {
			$user = $user_id ? get_userdata( $user_id ) : false;
			if ( $user && get_role( $role ) && ! in_array( $role, (array) $user->roles, true ) ) {
				$user->add_role( $role );
			}
		};
		$grant( $this->manager( $ctx ), 'kanbanomy_manager' );
		foreach ( $this->content()['projects'] as $p ) {
			foreach ( $p['clients'] as $c ) {
				$grant( self::who( $c, $ctx ), 'kanbanomy_client' );
			}
			foreach ( $p['team'] as $t ) {
				$grant( self::who( $t, $ctx ), 'kanbanomy_member' );
			}
		}
	}

	/**
	 * Projects with their team, clients, milestones and column names, created by the manager.
	 *
	 * @param Context $ctx Context.
	 */
	private function projects( Context $ctx ): void {
		$manager = $this->manager( $ctx );
		$ids     = array();
		$was     = get_current_user_id();
		wp_set_current_user( $manager );
		foreach ( $this->content()['projects'] as $i => $p ) {
			$pid = \ProjectFlow\Core\Projects::create(
				array(
					'title'       => $p['title'],
					'description' => $p['desc'],
					'status'      => 'in_progress',
					'visibility'  => $p['visibility'],
					'start_date'  => self::day( (int) $p['start'] ),
					'end_date'    => self::day( (int) $p['end'] ),
					'color'       => $p['color'],
				),
				$manager
			);
			if ( is_wp_error( $pid ) || ! $pid ) {
				$ctx->warn( $this->id(), 'project ' . $p['title'], is_wp_error( $pid ) ? $pid : __( 'not created', 'wbcom-demo-installer' ) );
				continue;
			}
			$pid       = (int) $pid;
			$ids[ $i ] = array(
				'id'     => $pid,
				'ledger' => $ctx->record( $this->id(), 'project', $pid ),
			);
			foreach ( $p['team'] as $t ) {
				\ProjectFlow\Core\MemberAccess::add_member( $pid, self::who( $t, $ctx ), 'member' );
			}
			foreach ( $p['clients'] as $c ) {
				\ProjectFlow\Core\MemberAccess::add_member( $pid, self::who( $c, $ctx ), 'viewer' );
			}
			$milestones = array();
			foreach ( $p['milestones'] as $m => $ms ) {
				$res              = self::rest(
					'POST',
					'/kanbanomy/v1/projects/' . $pid . '/milestones',
					array(
						'title'      => $ms[0],
						'due_date'   => self::day( (int) $ms[1] ),
						'status'     => $ms[2],
						'color'      => $p['color'],
						'sort_order' => $m,
					)
				);
				$milestones[ $m ] = (int) ( $res['id'] ?? ( $res['milestone']['id'] ?? 0 ) );
			}
			$ids[ $i ]['milestones'] = $milestones;
			if ( ! empty( $p['columns'] ) ) {
				$this->rename_columns( $pid, (array) $p['columns'] );
			}
		}
		wp_set_current_user( $was );
		$ctx->set( $this->id(), 'projects', $ids );
	}

	/**
	 * Rename a project's board columns by the status each one maps to (New, Investigating, ...).
	 *
	 * @param int                  $project_id Project.
	 * @param array<string,string> $names      Column title by status.
	 */
	private function rename_columns( int $project_id, array $names ): void {
		// The board route lists the columns; each one is renamed through /kanban/columns/{id}.
		$board = self::rest( 'GET', '/kanbanomy/v1/projects/' . $project_id . '/kanban' );
		foreach ( (array) ( $board['columns'] ?? array() ) as $col ) {
			$col    = (array) $col;
			$status = (string) ( $col['maps_to_status'] ?? '' );
			if ( isset( $names[ $status ], $col['id'] ) ) {
				self::rest( 'POST', '/kanbanomy/v1/kanban/columns/' . (int) $col['id'], array( 'title' => $names[ $status ] ) );
			}
		}
	}

	/**
	 * One project's tasks, then their comments, logged time and approval requests as the people who would do them.
	 *
	 * @param int     $i   Project index.
	 * @param Context $ctx Context.
	 */
	private function tasks( int $i, Context $ctx ): void {
		$p   = $this->content()['projects'][ $i ] ?? null;
		$ids = (array) $ctx->get( $this->id(), 'projects', array() );
		if ( ! $p || empty( $ids[ $i ]['id'] ) ) {
			return;
		}
		$pid     = (int) $ids[ $i ]['id'];
		$ms      = (array) ( $ids[ $i ]['milestones'] ?? array() );
		$manager = $this->manager( $ctx );
		$labels  = $this->labels();
		$made    = array();
		$was     = get_current_user_id();
		wp_set_current_user( $manager );
		foreach ( $p['tasks'] as $t => $task ) {
			list( $title, $status, $priority, $due, $who, $hours, $milestone ) = $task;
			$o    = (array) ( $task[7] ?? array() );
			$deps = array();
			foreach ( (array) ( $o['dep'] ?? array() ) as $d ) {
				if ( isset( $made[ $d ] ) ) {
					$deps[] = $made[ $d ];
				}
			}
			$args = array(
				'project_id'      => $pid,
				'title'           => $title,
				'description'     => (string) ( $o['desc'] ?? '' ),
				'milestone_id'    => $ms[ $milestone ] ?? null,
				'status'          => $status,
				'priority'        => $priority,
				'start_date'      => self::day( (int) $due - max( 2, (int) ceil( $hours / 4 ) ) ),
				'due_date'        => self::day( (int) $due ),
				'estimated_hours' => $hours,
				'is_internal'     => ! empty( $o['internal'] ),
				'assignee_ids'    => array( self::who( $who, $ctx ) ),
				'created_by'      => $manager,
				'label_ids'       => array_values( array_filter( array_map( static fn( $n ) => $labels[ strtolower( (string) $n ) ] ?? 0, (array) ( $o['labels'] ?? array() ) ) ) ),
				'dependencies'    => $deps,
			);
			if ( ! empty( $o['check'] ) ) {
				$args['checklist'] = array_map(
					static fn( array $c ): array => array(
						'text' => (string) $c[0],
						'done' => (bool) $c[1],
					),
					(array) $o['check']
				);
			}
			if ( ! empty( $o['recur'] ) ) {
				$args['recurrence_rule'] = (string) $o['recur'];
			}
			$tid = \ProjectFlow\Core\Tasks::create(
				$args,
				$manager,
				array(
					'source'           => 'demo',
					'check_access'     => false,
					'strict_assignees' => false,
				)
			);
			if ( is_wp_error( $tid ) ) {
				$ctx->warn( $this->id(), 'task ' . $title, $tid );
				continue;
			}
			$made[ $t ] = (int) $tid;
			$ctx->record( $this->id(), 'task', (int) $tid, 'sample', array(), (int) ( $ids[ $i ]['ledger'] ?? 0 ) );

			foreach ( (array) ( $o['comments'] ?? array() ) as $c ) {
				wp_set_current_user( self::who( $c[0], $ctx ) );
				self::rest( 'POST', '/kanbanomy/v1/tasks/' . (int) $tid . '/task-comments', array( 'content' => (string) $c[1] ) );
			}
			foreach ( (array) ( $o['log'] ?? array() ) as $l ) {
				wp_set_current_user( self::who( $l[0], $ctx ) );
				self::rest(
					'POST',
					'/kanbanomy/v1/tasks/' . (int) $tid . '/time-entries',
					array(
						'duration_minutes' => (int) $l[1],
						'entry_type'       => 'manual',
						'billable'         => 'private' !== $p['visibility'] && empty( $o['internal'] ),
					)
				);
			}
			wp_set_current_user( $manager );
			if ( ! empty( $o['approval'] ) && $p['clients'] ) {
				self::rest( 'POST', '/kanbanomy/v1/tasks/' . (int) $tid . '/approval/request', array() );
			}
		}
		wp_set_current_user( $was );
	}

	/**
	 * Project discussions and their replies, through Kanbanomy's discussion service.
	 *
	 * @param Context $ctx Context.
	 */
	private function discussions( Context $ctx ): void {
		if ( ! class_exists( '\\ProjectFlow\\Core\\Discussions' ) ) {
			return;
		}
		$ids = (array) $ctx->get( $this->id(), 'projects', array() );
		foreach ( $this->content()['projects'] as $i => $p ) {
			if ( empty( $ids[ $i ]['id'] ) ) {
				continue;
			}
			foreach ( (array) $p['discussions'] as $d ) {
				list( $title, $client_visible, $by, $body, $replies ) = $d;
				$thread = \ProjectFlow\Core\Discussions::create(
					(int) $ids[ $i ]['id'],
					array(
						'title'          => $title,
						'body'           => $body,
						'client_visible' => (bool) $client_visible,
					),
					self::who( $by, $ctx )
				);
				if ( is_wp_error( $thread ) ) {
					$ctx->warn( $this->id(), 'discussion ' . $title, $thread );
					continue;
				}
				foreach ( (array) $replies as $r ) {
					\ProjectFlow\Core\Discussions::reply( (int) $thread->id, (string) $r[1], self::who( $r[0], $ctx ) );
				}
			}
		}
	}

	/**
	 * Kanbanomy's labels by lower-case name, read through its REST route.
	 *
	 * @return array<string,int>
	 */
	private function labels(): array {
		$out = array();
		foreach ( (array) self::rest( 'GET', '/kanbanomy/v1/labels' ) as $l ) {
			$l = (array) $l;
			if ( isset( $l['name'], $l['id'] ) ) {
				$out[ strtolower( (string) $l['name'] ) ] = (int) $l['id'];
			}
		}
		return $out;
	}

	/**
	 * Remove one object. Tasks are recorded after their project, so Finish deletes them first; a task already gone
	 * (deleted with its project or by the owner) counts as removed.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id = (int) $row->object_id;
		if ( 'task' === $row->object_type ) {
			if ( ! \ProjectFlow\Core\Tasks::get( $id ) ) {
				return true;
			}
			$done = \ProjectFlow\Core\Tasks::delete( $id, get_current_user_id(), array( 'check_access' => false ) );
			return true === $done;
		}
		if ( 'project' === $row->object_type ) {
			if ( ! \ProjectFlow\Core\Projects::get( $id ) ) {
				return true;
			}
			$req = new \WP_REST_Request( 'DELETE', '/kanbanomy/v1/projects/' . $id );
			$req->set_param( 'force', true );
			$res = rest_do_request( $req );
			return $res->get_status() < 300 || ! \ProjectFlow\Core\Projects::get( $id );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function late_types(): array {
		return array( 'project' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		if ( ! class_exists( '\\ProjectFlow\\Core\\Permalinks' ) ) {
			return array();
		}
		return array(
			array(
				'title' => __( 'Workspace', 'wbcom-demo-installer' ),
				'url'   => \ProjectFlow\Core\Permalinks::get_hub_url(),
			),
			array(
				'title' => __( 'Projects', 'wbcom-demo-installer' ),
				'url'   => \ProjectFlow\Core\Permalinks::get_archive_url(),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * The project the steps open is the first one both the manager and the client can see, read through Kanbanomy's
	 * own projects route as each of them, so the links also work on a site whose projects the installer did not
	 * build (accounts named with `wp wbcom-demo guide --accounts=kanbanomy:<manager>,<client>`).
	 */
	public function guide(): array {
		if ( ! $this->available() || ! class_exists( '\\ProjectFlow\\Core\\Permalinks' ) ) {
			return array();
		}
		$t       = $this->content()['guide'];
		$named   = array_values( (array) ( ( (array) get_option( Guide::OPTION, array() ) )['accounts'][ $this->id() ] ?? array() ) );
		$cast    = (array) $this->content()['cast'];
		$manager = (int) ( $named[0] ?? People::user_id( '', (int) ( $cast['manager'][1] ?? -1 ) ) );
		$client  = (int) ( $named[1] ?? People::user_id( '', (int) ( $cast['client'][1] ?? -1 ) ) );
		$shared  = self::first_project( $client, array( 'visibility' => 'members_only' ) );
		$shared  = $shared ? $shared : self::first_project( $manager, array( 'visibility' => 'members_only' ) );
		$public  = self::first_project( 0, array( 'visibility' => 'public' ), 'support' );
		$project = $shared ? \ProjectFlow\Core\Permalinks::get_project_url( $shared ) : null;
		$admin   = static fn( string $page ): string => admin_url( 'admin.php?page=' . $page );
		return array(
			'name'         => $t['name'],
			'tab'          => $t['tab'],
			'member_label' => $t['member_label'],
			'owner_points' => $t['owner_points'],
			'roles'        => array(
				array(
					'key'    => 'manager',
					'user'   => $manager,
					'label'  => $t['roles']['manager'][0],
					'points' => $t['roles']['manager'][1],
				),
				array(
					'key'    => 'client',
					'user'   => $client,
					'label'  => $t['roles']['client'][0],
					'points' => $t['roles']['client'][1],
				),
			),
			'member'       => self::guide_steps(
				$t['member'],
				array(
					'hub'     => \ProjectFlow\Core\Permalinks::get_hub_url(),
					'board'   => $project ? add_query_arg( 'tab', 'kanban', $project ) : null,
					'gantt'   => $project ? add_query_arg( 'tab', 'gantt', $project ) : null,
					'client'  => $client ? $project : null,
					'support' => $public ? \ProjectFlow\Core\Permalinks::get_project_url( $public ) : null,
				),
				array(
					'hub'     => array( 'as' => 'manager' ),
					'board'   => array(
						'as'        => 'manager',
						'highlight' => true,
					),
					'gantt'   => array( 'as' => 'manager' ),
					'client'  => array( 'as' => 'client' ),
					'support' => array( 'public' => true ),
				)
			),
			'owner'        => self::guide_steps(
				$t['owner'],
				array(
					'projects' => $admin( 'kanbanomy-projects' ),
					'forms'    => $admin( 'kanbanomy-forms' ),
					'import'   => $admin( 'kanbanomy-import-export' ),
					'settings' => $admin( 'kanbanomy-settings' ),
				)
			),
			'highlights'   => self::guide_highlights( $t['highlights'] ),
		);
	}

	/**
	 * Slug of the first project a user may read, through Kanbanomy's projects route as that user ('' for none).
	 * Master projects are left out: their Gantt tab shows only the master bar (Kanbanomy card 10528452490).
	 *
	 * @param int                  $user_id User (0 for a signed-out visitor).
	 * @param array<string,string> $filter  Route filters.
	 * @param string               $prefer  A word to prefer in the title.
	 */
	private static function first_project( int $user_id, array $filter, string $prefer = '' ): string {
		static $cache = array();
		$key          = $user_id . wp_json_encode( $filter ) . $prefer;
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$was = get_current_user_id();
		wp_set_current_user( $user_id );
		$rows = self::rest( 'GET', '/kanbanomy/v1/projects', $filter + array( 'per_page' => 50 ) );
		wp_set_current_user( $was );
		// The route can return more than the filter asks for (public projects to everyone), and projects built in the
		// same second share a created time: filter here and take the lowest id, the one made first.
		$pick = array();
		foreach ( (array) $rows as $row ) {
			$row = (array) $row;
			if ( 'master' === ( $row['project_type'] ?? '' ) || empty( $row['slug'] ) || empty( $row['id'] ) ) {
				continue;
			}
			if ( isset( $filter['visibility'] ) && ( $row['visibility'] ?? '' ) !== $filter['visibility'] ) {
				continue;
			}
			$rank          = ( '' !== $prefer && false !== stripos( (string) ( $row['title'] ?? '' ), $prefer ) ) ? 0 : 1;
			$pick[ $rank ] = isset( $pick[ $rank ] ) && (int) $pick[ $rank ]['id'] < (int) $row['id'] ? $pick[ $rank ] : $row;
		}
		ksort( $pick );
		$slug          = $pick ? (string) reset( $pick )['slug'] : '';
		$cache[ $key ] = $slug;
		return $slug;
	}

	/**
	 * Call a Kanbanomy REST route as the current user and return its data as arrays.
	 *
	 * @param string              $method Method.
	 * @param string              $route  Route.
	 * @param array<string,mixed> $params Parameters.
	 * @return array<int|string,mixed>
	 */
	private static function rest( string $method, string $route, array $params = array() ): array {
		$req = new \WP_REST_Request( $method, $route );
		foreach ( $params as $k => $v ) {
			$req->set_param( $k, $v );
		}
		$res = rest_do_request( $req );
		if ( $res->is_error() ) {
			return array();
		}
		return (array) json_decode( (string) wp_json_encode( $res->get_data() ), true );
	}

	/**
	 * A date relative to today, in the Y-m-d form Kanbanomy stores for start, due and milestone dates.
	 *
	 * @param int $days Days from today (negative for the past).
	 */
	private static function day( int $days ): string {
		return gmdate( 'Y-m-d', time() + $days * DAY_IN_SECONDS );
	}
}
