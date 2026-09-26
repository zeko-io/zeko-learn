<?php
/**
 * Progress tracker for Zeko Learn.
 *
 * Handles lesson/course completion logic, certificate auto-issue, and learning streaks.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Learn_Progress. */
class Zeko_Learn_Progress {

	/**
	 * Db.
	 *
	 * @var Zeko_Learn_DB Db.
	 */
	private Zeko_Learn_DB $db;

	/**
	 * Construct.
	 *
	 * @param Zeko_Learn_DB $db Db.
	 */
	public function __construct( Zeko_Learn_DB $db ) {
		$this->db = $db;
	}

	/**
	 * Mark a lesson as complete for a user, recalculate course progress, and trigger course completion if 100%.
	 *
	 * @param int $user_id User id.
	 * @param int $lesson_id Lesson id.
	 * @param int $course_id Course id.
	 */
	public function complete_lesson( int $user_id, int $lesson_id, int $course_id ): array {
		$previous = $this->db->get_course_progress( $user_id, $course_id );
		$prev_pct = $previous ? (float) $previous['completion_pct'] : 0.0;

		$this->db->complete_lesson( $user_id, $lesson_id, $course_id );

		$progress = $this->db->recalculate_course_progress( $user_id, $course_id );
		$this->db->update_learning_streak( $user_id );

		$this->maybe_send_milestone_notification( $user_id, $course_id, $progress, $prev_pct );

		if ( 100 <= (float) $progress['completion_pct'] ) {
			$this->complete_course( $user_id, $course_id );
			return array_merge( $progress, array( 'course_completed' => true ) );
		}

		return $progress;
	}

	/**
	 * Handle full course completion: update enrollment, issue certificate, fire action.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	private function complete_course( int $user_id, int $course_id ): void {
		$enrollment = $this->db->get_enrollment( $user_id, $course_id );
		if ( ! $enrollment ) {
			return;
		}

		global $wpdb;
		$wpdb->update(
			$this->db->get_table_enrollments(),
			array(
				'status'       => 'completed',
				'completed_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $enrollment['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$this->db->issue_certificate( $user_id, $course_id, (int) $enrollment['id'] );

		do_action( 'zeko_learn_course_completed', $user_id, $course_id );
	}

	/**
	 * Send milestone notifications at 25%, 50%, 75% completion.
	 *
	 * @param int   $user_id User id.
	 * @param int   $course_id Course id.
	 * @param array $progress Progress.
	 * @param float $prev_pct Prev pct.
	 */
	private function maybe_send_milestone_notification( int $user_id, int $course_id, array $progress, float $prev_pct ): void {
		$pct    = (float) $progress['completion_pct'];
		$course = $this->db->get_course( $course_id );
		if ( ! $course ) {
			return;
		}

		$milestones = array( 25, 50, 75 );
		$crossed    = 0;
		foreach ( $milestones as $milestone ) {
			if ( $pct >= $milestone && $prev_pct < $milestone ) {
				$crossed = $milestone;
			}
		}

		if ( $crossed ) {
			$this->db->insert_notification(
				array(
					'user_id'     => $user_id,
					'action'      => 'milestone_' . $crossed,
					'object_id'   => $course_id,
					'object_type' => 'course',
					'actor_id'    => $course['instructor_id'],
				)
			);
		}
	}

	/**
	 * Check if a course is complete for a user based on completion requirements.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function check_course_completion( int $user_id, int $course_id ): bool {
		$progress = $this->db->get_course_progress( $user_id, $course_id );
		if ( ! $progress ) {
			return false;
		}

		if ( (float) $progress['completion_pct'] >= 100 ) {
			return true;
		}

		$enrollment = $this->db->get_enrollment( $user_id, $course_id );
		if ( ! $enrollment || 'completed' === $enrollment['status'] ) {
			return false;
		}

		$all_lessons = $this->db->get_course_lessons( $course_id );
		$required    = array_filter(
			$all_lessons,
			function ( $lesson ) {
				return 'quiz' !== $lesson['lesson_type'] && 'assignment' !== $lesson['lesson_type'];
			}
		);

		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->db->get_table_lesson_progress()}
				WHERE user_id = %d AND course_id = %d AND status = 'completed'",
				$user_id,
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return $completed >= count( $required );
	}

	/**
	 * Update learning streak: track consecutive active days.
	 *
	 * @param int $user_id User id.
	 */
	public function update_streak( int $user_id ): array {
		return $this->db->update_learning_streak( $user_id );
	}

	/**
	 * Issue a certificate for course completion.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 * @param int $enrollment_id Enrollment id.
	 */
	public function issue_certificate( int $user_id, int $course_id, int $enrollment_id ): int {
		return $this->db->issue_certificate( $user_id, $course_id, $enrollment_id );
	}
}
