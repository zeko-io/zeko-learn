<?php
/**
 * AJAX handlers for Zeko Learn.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Learn_Ajax. */
class Zeko_Learn_Ajax {

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

		$actions = array(
			'zeko_learn_enroll',
			'zeko_learn_refund_course',
			'zeko_learn_purchase',
			'zeko_learn_complete_lesson',
			'zeko_learn_submit_quiz',
			'zeko_learn_submit_assignment',
			'zeko_learn_grade_assignment',
			'zeko_learn_submit_review',
			'zeko_learn_reply_review',
			'zeko_learn_create_discussion',
			'zeko_learn_reply_discussion',
			'zeko_learn_vote_discussion',
			'zeko_learn_best_answer',
			'zeko_learn_save_note',
			'zeko_learn_delete_note',
			'zeko_learn_toggle_bookmark',
			'zeko_learn_toggle_wishlist',
			'zeko_learn_get_notifications',
			'zeko_learn_mark_notification_read',
			'zeko_learn_get_student_progress',
			'zeko_learn_update_course_status',
			'zeko_learn_reorder_sections',
			'zeko_learn_reorder_lessons',
			'zeko_learn_duplicate_course',
			'zeko_learn_export_grades',
			'zeko_learn_pin_announcement',
			'zeko_learn_post_announcement',
			'zeko_learn_delete_announcement',
			'zeko_learn_set_goal',
			'zeko_learn_update_goal',
			'zeko_learn_generate_demo_data',
			'zeko_learn_clear_demo_data',
			'zeko_learn_create_course',
			'zeko_learn_save_section',
			'zeko_learn_save_lesson',
			'zeko_learn_save_instructor_settings',
			'zeko_learn_save_student_settings',
			'zeko_learn_search_skills',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, 'ajax_dispatcher' ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, 'ajax_dispatcher' ) );
		}
	}

	// ─── Rate Limiting ───────────────────────────────────────────.

	/**
	 * Check rate limit.
	 *
	 * @param string $key Key.
	 * @param int    $limit Limit.
	 * @param int    $window Window.
	 */
	private function check_rate_limit( string $key, int $limit = 5, int $window = 60 ): bool {
		$user_id   = get_current_user_id();
		$transient = 'zeko_rl_' . $key . '_' . $user_id;
		$count     = (int) get_transient( $transient );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $transient, $count + 1, $window );
		return true;
	}

	/**
	 * Verify the current user owns the given course or is an admin.
	 *
	 * @param int $course_id Course id.
	 */
	private function verify_course_owner( int $course_id ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( ! current_user_can( 'publish_posts' ) ) {
			return false;
		}
		$course = $this->db->get_course( $course_id );
		return $course && get_current_user_id() === (int) $course['instructor_id'];
	}

	/**
	 * Ajax dispatcher.
	 */
	public function ajax_dispatcher(): void {
		// Accept the public nonce OR an action-specific nonce.
		$nonce       = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$action_name = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		$valid_nonce = wp_verify_nonce( $nonce, 'zeko_learn_public_nonce' )
			|| wp_verify_nonce( $nonce, 'zeko_learn_admin_nonce' )
			|| ( $action_name && wp_verify_nonce( $nonce, $action_name ) );

		if ( ! $valid_nonce ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'zeko-learn' ) ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'zeko-learn' ) ) );
		}

		$method = 'handle_' . str_replace( 'zeko_learn_', '', $action_name );

		if ( method_exists( $this, $method ) ) {
			$this->$method();
			return;
		}

		wp_send_json_error( array( 'message' => __( 'Handler not yet implemented.', 'zeko-learn' ) ) );
	}

	// ─── Enrollment ─────────────────────────────────────────────.

	/**
	 * Handle enroll.
	 */
	public function handle_enroll(): void {
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course.', 'zeko-learn' ) ) );
		}

		$course = $this->db->get_course( $course_id );
		if ( ! $course || 'published' !== $course['status'] ) {
			wp_send_json_error( array( 'message' => __( 'Course not available.', 'zeko-learn' ) ) );
		}

		$user_id = get_current_user_id();
		if ( $this->db->is_enrolled( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Already enrolled.', 'zeko-learn' ) ) );
		}

		if ( ! $course['is_free'] ) {
			wp_send_json_error( array( 'message' => __( 'This course requires payment.', 'zeko-learn' ) ) );
		}

		$this->db->enroll_user( $user_id, $course_id );

		// Notify student.
		$this->db->insert_notification(
			array(
				'user_id'     => $user_id,
				'action'      => 'enrolled',
				'object_id'   => $course_id,
				'object_type' => 'course',
				'actor_id'    => $user_id,
			)
		);

		// Notify instructor.
		$instructor_id = (int) $course['instructor_id'];
		if ( $instructor_id && $instructor_id !== $user_id ) {
			$this->db->insert_notification(
				array(
					'user_id'     => $instructor_id,
					'action'      => 'new_student',
					'object_id'   => $course_id,
					'object_type' => 'course',
					'actor_id'    => $user_id,
				)
			);
		}

		// Fire action — Emails class hooks into this to send enrollment email.
		do_action( 'zeko_learn_enrollment_confirmed', $user_id, $course_id );

		wp_send_json_success(
			array(
				'message'    => __( 'Successfully enrolled!', 'zeko-learn' ),
				'course_url' => get_permalink( $course_id ),
			)
		);
	}

	/**
	 * Handle refund course.
	 */
	public function handle_refund_course(): void {
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course.', 'zeko-learn' ) ) );
		}

		$user_id = get_current_user_id();
		$result  = Zeko_Learn::instance()->get_ecosystem()->refund_course( $user_id, $course_id );

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ?? __( 'Refund failed.', 'zeko-learn' ) ) );
		}

		wp_send_json_success(
			array(
				'message'  => $result['message'] ?? __( 'Course refunded.', 'zeko-learn' ),
				'redirect' => home_url( '/dashboard/' ),
			)
		);
	}

	/**
	 * Handle purchase.
	 */
	public function handle_purchase(): void {
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course.', 'zeko-learn' ) ) );
		}

		$course = $this->db->get_course( $course_id );
		if ( ! $course || 'published' !== $course['status'] ) {
			wp_send_json_error( array( 'message' => __( 'Course not available.', 'zeko-learn' ) ) );
		}

		if ( $course['is_free'] ) {
			wp_send_json_error( array( 'message' => __( 'This course is free.', 'zeko-learn' ) ) );
		}

		$user_id = get_current_user_id();
		if ( $this->db->is_enrolled( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Already enrolled.', 'zeko-learn' ) ) );
		}

		if ( ! class_exists( 'Zeko_Pay_Integrations' ) ) {
			wp_send_json_error( array( 'message' => __( 'Payment system unavailable.', 'zeko-learn' ) ) );
		}

		$result = \Zeko_Pay_Integrations::instance()->learn_purchase_course(
			$user_id,
			$course_id,
			(float) $course['price']
		);

		if ( ! $result['success'] ) {
			wp_send_json_error(
				array(
					'message' => $result['message'] ?? __( 'Payment failed. Please check your wallet balance.', 'zeko-learn' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Payment successful! You are now enrolled.', 'zeko-learn' ),
				'course_url' => home_url( '/courses/' . $course['slug'] . '/learn/' ),
			)
		);
	}

	// ─── Progress ───────────────────────────────────────────────.

	/**
	 * Handle complete lesson.
	 */
	public function handle_complete_lesson(): void {
		$lesson_id = absint( $_POST['lesson_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $lesson_id || ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'zeko-learn' ) ) );
		}

		$user_id = get_current_user_id();
		if ( ! $this->db->is_enrolled( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Not enrolled.', 'zeko-learn' ) ) );
		}

		$lesson = $this->db->get_lesson( $lesson_id );
		if ( ! $lesson || (int) $lesson['course_id'] !== $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid lesson.', 'zeko-learn' ) ) );
		}

		$progress = Zeko_Learn::instance()->get_progress()->complete_lesson( $user_id, $lesson_id, $course_id );

		$course_completed = ! empty( $progress['course_completed'] );
		$certificate_url  = '';
		if ( $course_completed ) {
			$certificates = $this->db->get_user_certificates( $user_id );
			if ( $certificates && (int) $certificates[0]['course_id'] === $course_id ) {
				$certificate_url = home_url( '/certificates/' . $certificates[0]['certificate_number'] );
			}
		}

		wp_send_json_success(
			array(
				'message'          => __( 'Lesson completed!', 'zeko-learn' ),
				'completion_pct'   => $progress['completion_pct'],
				'completed'        => $progress['completed_lessons'],
				'total'            => $progress['total_lessons'],
				'course_completed' => $course_completed,
				'certificate_url'  => $certificate_url,
			)
		);
	}

	// ─── Quiz ───────────────────────────────────────────────────.

	/**
	 * Handle submit quiz.
	 */
	public function handle_submit_quiz(): void {
		$quiz_id   = absint( $_POST['quiz_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$answers   = isset( $_POST['answers'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['answers'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $quiz_id || ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'zeko-learn' ) ) );
		}

		$user_id = get_current_user_id();
		if ( ! $this->db->is_enrolled( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Not enrolled.', 'zeko-learn' ) ) );
		}

		if ( ! $this->check_rate_limit( 'quiz_' . $quiz_id, 10, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many attempts. Please wait a minute.', 'zeko-learn' ) ) );
		}

		$quiz = $this->db->get_quiz( $quiz_id );
		if ( ! $quiz || (int) $quiz['course_id'] !== $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Quiz not found.', 'zeko-learn' ) ) );
		}

		$questions     = $this->db->get_quiz_questions( $quiz_id );
		$total_points  = 0;
		$earned_points = 0;
		$results       = array();

		foreach ( $questions as $q ) {
			$total_points  += (int) $q['points'];
			$user_answer    = $answers[ $q['id'] ] ?? '';
			$is_correct     = strtolower( trim( $user_answer ) ) === strtolower( trim( $q['correct_answer'] ) );
			$earned         = $is_correct ? (int) $q['points'] : 0;
			$earned_points += $earned;

			$results[] = array(
				'question_id' => (int) $q['id'],
				'correct'     => $is_correct,
				'earned'      => $earned,
				'explanation' => $q['explanation'],
			);
		}

		$score     = $total_points > 0 ? round( ( $earned_points / $total_points ) * 100, 2 ) : 0;
		$is_passed = $score >= (float) $quiz['passing_score'];

		$this->db->insert_quiz_attempt(
			array(
				'quiz_id'            => $quiz_id,
				'user_id'            => $user_id,
				'score'              => $score,
				'total_points'       => $total_points,
				'earned_points'      => $earned_points,
				'answers'            => $answers,
				'is_passed'          => $is_passed,
				'time_taken_seconds' => absint( $_POST['time_taken'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
			)
		);

		wp_send_json_success(
			array(
				'message'       => $is_passed ? __( 'Quiz passed!', 'zeko-learn' ) : __( 'Quiz not passed. Try again.', 'zeko-learn' ),
				'score'         => $score,
				'is_passed'     => $is_passed,
				'results'       => $results,
				'passing_score' => (float) $quiz['passing_score'],
			)
		);
	}

	// ─── Assignment ─────────────────────────────────────────────.

	/**
	 * Handle submit assignment.
	 */
	public function handle_submit_assignment(): void {
		$assignment_id = absint( $_POST['assignment_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$course_id     = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $assignment_id || ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'zeko-learn' ) ) );
		}

		$user_id = get_current_user_id();

		if ( ! $this->db->is_enrolled( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not enrolled in this course.', 'zeko-learn' ) ) );
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$assignment = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->db->get_table_assignments()} WHERE id = %d", $assignment_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $assignment || (int) $assignment['course_id'] !== $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid assignment.', 'zeko-learn' ) ) );
		}

		$file_url = esc_url_raw( wp_unslash( $_POST['file_url'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! empty( $file_url ) ) {
			if ( ! filter_var( $file_url, FILTER_VALIDATE_URL ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid file URL.', 'zeko-learn' ) ) );
			}

			$allowed_exts = array( 'pdf', 'docx', 'doc', 'zip', 'txt', 'png', 'jpg', 'jpeg' );
			if ( $assignment && ! empty( $assignment['allowed_file_types'] ) ) {
				$allowed_exts = array_map( 'trim', explode( ',', $assignment['allowed_file_types'] ) );
			}

			$ext = strtolower( pathinfo( wp_parse_url( $file_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed_exts, true ) ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
						/* translators: %s: allowed file types */
							__( 'File type not allowed. Allowed: %s', 'zeko-learn' ),
							implode( ', ', $allowed_exts )
						),
					)
				);
			}

			if ( $assignment && ! empty( $assignment['max_file_size_mb'] ) ) {
				$headers = @get_headers( $file_url );
				if ( $headers ) {
					$size = 0;
					foreach ( $headers as $header ) {
						if ( stripos( $header, 'content-length:' ) === 0 ) {
							$size = (int) trim( substr( $header, 16 ) );
							break;
						}
					}
					$max_bytes = (int) $assignment['max_file_size_mb'] * 1024 * 1024;
					if ( $size > 0 && $size > $max_bytes ) {
						wp_send_json_error(
							array(
								'message' => sprintf(
								/* translators: %d: max file size in MB */
									__( 'File too large. Maximum %d MB.', 'zeko-learn' ),
									$assignment['max_file_size_mb']
								),
							)
						);
					}
				}
			}
		}

		$this->db->insert_assignment_submission(
			array(
				'assignment_id' => $assignment_id,
				'user_id'       => $user_id,
				'course_id'     => $course_id,
				'file_url'      => $file_url,
				'notes'         => wp_kses_post( wp_unslash( $_POST['notes'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
			)
		);

		wp_send_json_success( array( 'message' => __( 'Assignment submitted!', 'zeko-learn' ) ) );
	}

	/**
	 * Handle grade assignment.
	 */
	public function handle_grade_assignment(): void {
		$submission_id = absint( $_POST['submission_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$submission    = $this->db->get_assignment_submission( $submission_id );

		if ( ! $submission || ! $this->verify_course_owner( (int) $submission['course_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$this->db->update_assignment_submission(
			$submission_id,
			array(
				'grade'     => (float) sanitize_text_field( wp_unslash( $_POST['grade'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
				'feedback'  => wp_kses_post( wp_unslash( $_POST['feedback'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
				'status'    => 'graded',
				'graded_by' => get_current_user_id(),
			)
		);

		wp_send_json_success( array( 'message' => __( 'Assignment graded.', 'zeko-learn' ) ) );
	}

	// ─── Reviews ────────────────────────────────────────────────.

	/**
	 * Handle submit review.
	 */
	public function handle_submit_review(): void {
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$rating    = absint( $_POST['rating'] ?? 5 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$text      = wp_kses_post( wp_unslash( $_POST['review_text'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course.', 'zeko-learn' ) ) );
		}

		$user_id = get_current_user_id();
		if ( ! $this->db->is_enrolled( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be enrolled to review.', 'zeko-learn' ) ) );
		}

		$this->db->insert_review(
			array(
				'course_id'   => $course_id,
				'user_id'     => $user_id,
				'rating'      => $rating,
				'review_text' => $text,
			)
		);

		wp_send_json_success( array( 'message' => __( 'Review submitted!', 'zeko-learn' ) ) );
	}

	/**
	 * Handle reply review.
	 */
	public function handle_reply_review(): void {
		$review_id = absint( $_POST['review_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$reply     = wp_kses_post( wp_unslash( $_POST['reply'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $review_id || empty( $reply ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a reply.', 'zeko-learn' ) ) );
		}

		$review = $this->db->get_review( $review_id );
		if ( ! $review || ! $this->verify_course_owner( (int) $review['course_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$this->db->update_review( $review_id, array( 'instructor_reply' => $reply ) );

		wp_send_json_success( array( 'message' => __( 'Reply posted.', 'zeko-learn' ) ) );
	}

	// ─── Discussions ────────────────────────────────────────────.

	/**
	 * Handle create discussion.
	 */
	public function handle_create_discussion(): void {
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$title     = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$content   = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $course_id || empty( $title ) || empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all fields.', 'zeko-learn' ) ) );
		}

		$user_id = get_current_user_id();

		if ( ! $this->check_rate_limit( 'discussion', 5, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many posts. Please wait a minute.', 'zeko-learn' ) ) );
		}

		$is_instructor = current_user_can( 'publish_posts' );

		$discussion_id = $this->db->insert_discussion(
			array(
				'course_id'           => $course_id,
				'lesson_id'           => absint( wp_unslash( $_POST['lesson_id'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
				'user_id'             => $user_id,
				'title'               => $title,
				'content'             => $content,
				'is_instructor_reply' => $is_instructor ? 1 : 0,
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Discussion created.', 'zeko-learn' ),
				'id'      => $discussion_id,
			)
		);
	}

	/**
	 * Handle reply discussion.
	 */
	public function handle_reply_discussion(): void {
		$parent_id = absint( $_POST['parent_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$content   = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $parent_id || empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid reply.', 'zeko-learn' ) ) );
		}

		$user_id       = get_current_user_id();
		$is_instructor = current_user_can( 'publish_posts' );

		$reply_id = $this->db->insert_discussion(
			array(
				'course_id'           => $course_id,
				'user_id'             => $user_id,
				'parent_id'           => $parent_id,
				'content'             => $content,
				'is_instructor_reply' => $is_instructor ? 1 : 0,
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Reply posted.', 'zeko-learn' ),
				'id'      => $reply_id,
			)
		);
	}

	/**
	 * Handle vote discussion.
	 */
	public function handle_vote_discussion(): void {
		$discussion_id = absint( $_POST['discussion_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$vote_type     = sanitize_text_field( wp_unslash( $_POST['vote_type'] ?? 'up' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value allow-listed against 'up'/'down' below.

		if ( ! $discussion_id || ! in_array( $vote_type, array( 'up', 'down' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid vote.', 'zeko-learn' ) ) );
		}

		$this->db->vote_discussion( get_current_user_id(), $discussion_id, $vote_type );

		wp_send_json_success( array( 'message' => __( 'Vote recorded.', 'zeko-learn' ) ) );
	}

	/**
	 * Handle best answer.
	 */
	public function handle_best_answer(): void {
		$discussion_id = absint( $_POST['discussion_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$course_id     = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $discussion_id || ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'zeko-learn' ) ) );
		}

		$discussion = $this->db->get_discussion( $discussion_id );
		if ( ! $discussion || (int) $discussion['course_id'] !== $course_id || ! $this->verify_course_owner( $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$this->db->mark_best_answer( $discussion_id, $course_id );

		wp_send_json_success( array( 'message' => __( 'Marked as best answer.', 'zeko-learn' ) ) );
	}

	// ─── Notes ──────────────────────────────────────────────────.

	/**
	 * Handle save note.
	 */
	public function handle_save_note(): void {
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$lesson_id = absint( $_POST['lesson_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$content   = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $course_id || ! $lesson_id || empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid note.', 'zeko-learn' ) ) );
		}

		$note_id = $this->db->insert_note(
			array(
				'user_id'           => get_current_user_id(),
				'lesson_id'         => $lesson_id,
				'course_id'         => $course_id,
				'content'           => $content,
				'timestamp_seconds' => absint( wp_unslash( $_POST['timestamp_seconds'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Note saved.', 'zeko-learn' ),
				'note_id' => $note_id,
			)
		);
	}

	/**
	 * Handle delete note.
	 */
	public function handle_delete_note(): void {
		$note_id = absint( $_POST['note_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $note_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid note.', 'zeko-learn' ) ) );
		}

		$this->db->delete_note( $note_id, get_current_user_id() );

		wp_send_json_success( array( 'message' => __( 'Note deleted.', 'zeko-learn' ) ) );
	}

	// ─── Bookmarks & Wishlist ───────────────────────────────────.

	/**
	 * Handle toggle bookmark.
	 */
	public function handle_toggle_bookmark(): void {
		$lesson_id = absint( $_POST['lesson_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $lesson_id || ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'zeko-learn' ) ) );
		}

		$added = $this->db->toggle_bookmark( get_current_user_id(), $lesson_id, $course_id );

		wp_send_json_success(
			array(
				'message' => $added ? __( 'Bookmarked.', 'zeko-learn' ) : __( 'Bookmark removed.', 'zeko-learn' ),
				'added'   => $added,
			)
		);
	}

	/**
	 * Handle toggle wishlist.
	 */
	public function handle_toggle_wishlist(): void {
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course.', 'zeko-learn' ) ) );
		}

		$added = $this->db->toggle_wishlist( get_current_user_id(), $course_id );

		wp_send_json_success(
			array(
				'message' => $added ? __( 'Added to wishlist.', 'zeko-learn' ) : __( 'Removed from wishlist.', 'zeko-learn' ),
				'added'   => $added,
			)
		);
	}

	// ─── Notifications ──────────────────────────────────────────.

	/**
	 * Handle get notifications.
	 */
	public function handle_get_notifications(): void {
		global $wpdb;

		$user_id = get_current_user_id();
		$module  = sanitize_text_field( wp_unslash( $_POST['module'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value used only for equality matches against fixed literals.
		$limit   = 20;

		// ── Module config ──────────────────────────────────────.
		$modules_config = array(
			'learn'    => array(
				'icon'  => 'welcome-learn-more',
				'label' => __( 'Learn', 'zeko-learn' ),
			),
			'jobs'     => array(
				'icon'  => 'briefcase',
				'label' => __( 'Jobs', 'zeko-learn' ),
			),
			'qa'       => array(
				'icon'  => 'editor-help',
				'label' => __( 'Q&A', 'zeko-learn' ),
			),
			'pay'      => array(
				'icon'  => 'money-alt',
				'label' => __( 'Pay', 'zeko-learn' ),
			),
			'mentor'   => array(
				'icon'  => 'groups',
				'label' => __( 'Mentor', 'zeko-learn' ),
			),
			'shop'     => array(
				'icon'  => 'cart',
				'label' => __( 'Shop', 'zeko-learn' ),
			),
			'love'     => array(
				'icon'  => 'heart',
				'label' => __( 'Love', 'zeko-learn' ),
			),
			'rewards'  => array(
				'icon'  => 'awards',
				'label' => __( 'Rewards', 'zeko-learn' ),
			),
			'messages' => array(
				'icon'  => 'email-alt',
				'label' => __( 'Messages', 'zeko-learn' ),
			),
		);

		// ── Tables ─────────────────────────────────────────────.
		$learn_table   = $wpdb->prefix . 'zeko_learn_notifications';
		$qa_table      = $wpdb->prefix . 'zeko_qa_notifications';
		$pay_table     = $wpdb->prefix . 'zeko_notifications';
		$jobs_table    = $wpdb->prefix . 'zeko_job_notifications';
		$mentor_table  = $wpdb->prefix . 'zeko_mentor_notifications';
		$shop_table    = $wpdb->prefix . 'zeko_shop_notifications';
		$love_table    = $wpdb->prefix . 'zeko_love_notifications';
		$rewards_table = $wpdb->prefix . 'zeko_rewards_notifications';

		$learn_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $learn_table ) ) === $learn_table;
		$qa_exists      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $qa_table ) ) === $qa_table;
		$pay_exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pay_table ) ) === $pay_table;
		$jobs_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $jobs_table ) ) === $jobs_table;
		$mentor_exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $mentor_table ) ) === $mentor_table;
		$shop_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $shop_table ) ) === $shop_table;
		$love_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $love_table ) ) === $love_table;
		$rewards_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rewards_table ) ) === $rewards_table;

		// ── UNION query across all SQL tables ──────────────────.
		$union_parts = array();

		if ( $learn_exists ) {
			$where_learn = $wpdb->prepare( 'WHERE user_id = %d', $user_id );
			if ( 'learn' === $module ) {
				$where_learn .= $wpdb->prepare( ' AND module = %s', 'learn' );
			} elseif ( $module && 'qa' !== $module && 'pay' !== $module && 'jobs' !== $module && 'mentor' !== $module && 'shop' !== $module && 'love' !== $module ) {
				$where_learn .= $wpdb->prepare( ' AND module = %s', $module );
			}
			$union_parts[] = "SELECT id, user_id, 'learn' AS source, action, object_id, object_type, actor_id, is_read, created_at, '' AS title, '' AS message, module, '' AS link FROM {$learn_table} {$where_learn}";
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $qa_exists && ( ! $module || 'qa' === $module ) ) {
			$union_parts[] = $wpdb->prepare(
				"SELECT id, user_id, 'qa' AS source, action, object_id, object_type, actor_id, is_read, created_at, '' AS title, '' AS message, 'qa' AS module, '' AS link FROM {$qa_table} WHERE user_id = %d",
				$user_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $pay_exists && ( ! $module || 'pay' === $module ) ) {
			$union_parts[] = $wpdb->prepare(
				"SELECT id, user_id, 'pay' AS source, type AS action, 0 AS object_id, '' AS object_type, 0 AS actor_id, is_read, created_at, title, message, 'pay' AS module, '' AS link FROM {$pay_table} WHERE user_id = %d",
				$user_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $jobs_exists && ( ! $module || 'jobs' === $module ) ) {
			$union_parts[] = $wpdb->prepare(
				"SELECT id, user_id, 'jobs' AS source, type AS action, 0 AS object_id, '' AS object_type, 0 AS actor_id, is_read, created_at, title, message, 'jobs' AS module, '' AS link FROM {$jobs_table} WHERE user_id = %d",
				$user_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $mentor_exists && ( ! $module || 'mentor' === $module ) ) {
			$union_parts[] = $wpdb->prepare(
				"SELECT notification_id AS id, user_id, 'mentor' AS source, type AS action, 0 AS object_id, '' AS object_type, 0 AS actor_id, is_read, created_at, title, message, 'mentor' AS module, link FROM {$mentor_table} WHERE user_id = %d",
				$user_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $shop_exists && ( ! $module || 'shop' === $module ) ) {
			$union_parts[] = $wpdb->prepare(
				"SELECT id, user_id, 'shop' AS source, type AS action, 0 AS object_id, '' AS object_type, 0 AS actor_id, is_read, created_at, title, message, 'shop' AS module, '' AS link FROM {$shop_table} WHERE user_id = %d",
				$user_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $love_exists && ( ! $module || 'love' === $module ) ) {
			$union_parts[] = $wpdb->prepare(
				"SELECT notification_id AS id, user_id, 'love' AS source, type AS action, object_id, object_type, actor_id, is_read, created_at, '' AS title, message, 'love' AS module, '' AS link FROM {$love_table} WHERE user_id = %d",
				$user_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $rewards_exists && ( ! $module || 'rewards' === $module ) ) {
			$union_parts[] = $wpdb->prepare(
				"SELECT id, user_id, 'rewards' AS source, type AS action, 0 AS object_id, '' AS object_type, 0 AS actor_id, is_read, created_at, '' AS title, message, 'rewards' AS module, '' AS link FROM {$rewards_table} WHERE user_id = %d",
				$user_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// ── Messaging unread count via conversations table ──────.
		$messages_table  = $wpdb->prefix . 'zeko_conversations';
		$messages_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $messages_table ) ) === $messages_table;

		$notifications = array();

		if ( ! empty( $union_parts ) ) {
			$sql           = implode( "\nUNION ALL\n", $union_parts ) . ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
			$notifications = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// ── Unread counts per module ───────────────────────────.
		$total_unread = 0;
		$unread_by    = array(
			'learn'   => 0,
			'qa'      => 0,
			'pay'     => 0,
			'jobs'    => 0,
			'mentor'  => 0,
			'shop'    => 0,
			'love'    => 0,
			'rewards' => 0,
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $learn_exists ) {
			$unread_by['learn'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$learn_table} WHERE user_id = %d AND is_read = 0", $user_id ) );
		}
		if ( $qa_exists ) {
			$unread_by['qa'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$qa_table} WHERE user_id = %d AND is_read = 0", $user_id ) );
		}
		if ( $pay_exists ) {
			$unread_by['pay'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pay_table} WHERE user_id = %d AND is_read = 0", $user_id ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// zeko-jobs notifications — query the dedicated table.
		if ( $jobs_exists ) {
			$unread_by['jobs'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$jobs_table} WHERE user_id = %d AND is_read = 0", $user_id ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $mentor_exists ) {
			$unread_by['mentor'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$mentor_table} WHERE user_id = %d AND is_read = 0", $user_id ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $shop_exists ) {
			$unread_by['shop'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$shop_table} WHERE user_id = %d AND is_read = 0", $user_id ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $love_exists ) {
			$unread_by['love'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$love_table} WHERE user_id = %d AND is_read = 0", $user_id ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $rewards_exists ) {
			$unread_by['rewards'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$rewards_table} WHERE user_id = %d AND is_read = 0", $user_id ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// Messaging unread count.
		if ( $messages_exists ) {
			$unread_by['messages'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(CASE WHEN user1_id = %d THEN unread_count_user1 WHEN user2_id = %d THEN unread_count_user2 ELSE 0 END)
				FROM {$messages_table} WHERE (user1_id = %d OR user2_id = %d) AND status = 'active'",
					$user_id,
					$user_id,
					$user_id,
					$user_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		$total_unread = array_sum( $unread_by );

		// ── Enrich each notification ───────────────────────────.
		foreach ( $notifications as &$n ) {
			$actor_id = (int) ( $n['actor_id'] ?? 0 );
			if ( $actor_id > 0 ) {
				$actor             = get_userdata( $actor_id );
				$n['actor_name']   = $actor ? $actor->display_name : '';
				$n['actor_avatar'] = $actor ? get_avatar_url( $actor->ID, array( 'size' => 32 ) ) : '';
			} else {
				$n['actor_name']   = '';
				$n['actor_avatar'] = '';
			}

			$source           = $n['source'] ?? 'learn';
			$mod              = $n['module'] ?: $source;
			$n['module_info'] = $modules_config[ $mod ] ?? array(
				'icon'  => 'dashicons-admin-site',
				'label' => ucfirst( $mod ),
			);
			$n['time_ago']    = human_time_diff( strtotime( $n['created_at'] ) ) . ' ago';
			$n['module']      = $mod;

			$obj_id   = (int) ( $n['object_id'] ?? 0 );
			$obj_type = $n['object_type'] ?? '';
			$src      = $n['source'] ?? 'learn';

			// Build human-readable message from action/type if no message.
			if ( empty( $n['message'] ) ) {
				$msg = ucfirst( str_replace( '_', ' ', $n['action'] ?? '' ) );
				// Enrich with course title for enrollment/purchase notifications.
				if ( 'learn' === $src && $obj_id > 0 && 'course' === $obj_type ) {
					$course = $this->db->get_course( $obj_id );
					$title  = $course ? $course['title'] : '';
					if ( $title ) {
						$action = $n['action'] ?? '';
						if ( 'enrolled' === $action ) {
							/* translators: %s: course title */
							$msg = sprintf( __( 'You enrolled in %s', 'zeko-learn' ), $title );
						} elseif ( 'course_purchased' === $action ) {
							/* translators: %s: course title */
							$msg = sprintf( __( 'You purchased %s', 'zeko-learn' ), $title );
						} elseif ( 'new_student' === $action ) {
							$actor_name = $n['actor_name'] ?? '';
							$msg        = $actor_name
								/* translators: 1: user display name. 2: course title */
								? sprintf( __( '%1$s enrolled in %2$s', 'zeko-learn' ), $actor_name, $title )
								/* translators: %s: course title */
								: sprintf( __( 'New student in %s', 'zeko-learn' ), $title );
						} elseif ( 'milestone_25' === $action || 'milestone_50' === $action || 'milestone_75' === $action ) {
							$pct = str_replace( 'milestone_', '', $action );
							/* translators: 1: completion percentage. 2: course title */
							$msg = sprintf( __( '%1$s%% completed in %2$s', 'zeko-learn' ), $pct, $title );
						}
					}
				}
				$n['message'] = $msg;
			}

			// Build link based on source + object_type.
			$n['link'] = '';

			if ( 'learn' === $src && $obj_id > 0 ) {
				if ( 'course' === $obj_type ) {
					$course    = $this->db->get_course( $obj_id );
					$n['link'] = $course ? home_url( '/courses/' . $course['slug'] . '/' ) : '';
				} elseif ( 'lesson' === $obj_type || 'quiz' === $obj_type ) {
					$lesson = $this->db->get_lesson( $obj_id );
					if ( $lesson ) {
						$course  = $this->db->get_course( $lesson['course_id'] );
						$section = $this->db->get_section( $lesson['section_id'] );
						if ( $course && $section ) {
							$n['link'] = home_url( '/courses/' . $course['slug'] . '/learn/' . sanitize_title( $section['title'] ) . '/' . $lesson['slug'] . '/' );
						}
					}
				}
			} elseif ( 'qa' === $src && $obj_id > 0 ) {
				// QA uses slugs. Resolve by object_type.
				$qa_questions = $wpdb->prefix . 'zeko_questions';
				$qa_answers   = $wpdb->prefix . 'zeko_answers';
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
				if ( 'question' === $obj_type ) {
					$slug = $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$qa_questions} WHERE id = %d", $obj_id ) );
					// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
					$n['link'] = $slug ? home_url( '/questions/' . $slug . '/' ) : home_url( '/questions/' );
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
				} elseif ( 'answer' === $obj_type ) {
					$qid  = $wpdb->get_var( $wpdb->prepare( "SELECT question_id FROM {$qa_answers} WHERE id = %d", $obj_id ) );
					$slug = $qid ? $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$qa_questions} WHERE id = %d", $qid ) ) : '';
					// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
					$n['link'] = $slug ? home_url( '/questions/' . $slug . '/#answer-' . $obj_id ) : home_url( '/questions/' );
				} elseif ( 'topic' === $obj_type ) {
					$slug      = $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$wpdb->prefix}zeko_topics WHERE id = %d", $obj_id ) );
					$n['link'] = $slug ? home_url( '/topics/' . $slug . '/' ) : home_url( '/questions/' );
				} else {
					$n['link'] = home_url( '/questions/' );
				}
			} elseif ( 'pay' === $src ) {
				$n['link'] = home_url( '/dashboard/' );
			} elseif ( 'jobs' === $src ) {
				$n['link'] = $n['link'] ?? home_url( '/jobs/' );
			} elseif ( 'mentor' === $src ) {
				$n['link'] = $n['link'] ?: home_url( '/dashboard/' );
			} elseif ( 'shop' === $src ) {
				$n['link'] = $n['link'] ?: ( function_exists( 'zeko_shop_page_url' ) ? zeko_shop_page_url( 'my-orders' ) : home_url( '/my-orders/' ) );
			} elseif ( 'love' === $src ) {
				$n['link'] = $n['link'] ?: ( function_exists( 'zeko_love_page_url' ) ? zeko_love_page_url( 'dating-messages' ) : home_url( '/dating-messages/' ) );
			} elseif ( 'rewards' === $src ) {
				$n['link'] = $n['link'] ?: ( function_exists( 'zeko_rewards_page_url' ) ? zeko_rewards_page_url( 'rewards' ) : home_url( '/rewards/' ) );
			}
		}
		unset( $n );

		wp_send_json_success(
			array(
				'notifications' => $notifications,
				'unread_count'  => $total_unread,
				'total_unread'  => $total_unread,
				'unread_by'     => $unread_by,
				'modules'       => $modules_config,
			)
		);
	}

	/**
	 * Handle mark notification read.
	 */
	public function handle_mark_notification_read(): void {
		global $wpdb;

		$notification_id = absint( $_POST['notification_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$source          = sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value compared against fixed table-name literals.
		$user_id         = get_current_user_id();

		if ( $notification_id ) {
			// Determine which table to mark based on source.
			if ( 'qa' === $source ) {
				$table = $wpdb->prefix . 'zeko_qa_notifications';
				$wpdb->update(
					$table,
					array( 'is_read' => 1 ),
					array(
						'id'      => $notification_id,
						'user_id' => $user_id,
					)
				);
			} elseif ( 'pay' === $source ) {
				$table = $wpdb->prefix . 'zeko_notifications';
				$wpdb->update(
					$table,
					array( 'is_read' => 1 ),
					array(
						'id'      => $notification_id,
						'user_id' => $user_id,
					)
				);
			} elseif ( 'jobs' === $source ) {
				$table = $wpdb->prefix . 'zeko_job_notifications';
				$wpdb->update(
					$table,
					array( 'is_read' => 1 ),
					array(
						'id'      => $notification_id,
						'user_id' => $user_id,
					)
				);
			} elseif ( 'mentor' === $source ) {
				$table = $wpdb->prefix . 'zeko_mentor_notifications';
				$wpdb->update(
					$table,
					array( 'is_read' => 1 ),
					array(
						'notification_id' => $notification_id,
						'user_id'         => $user_id,
					)
				);
			} elseif ( 'shop' === $source ) {
				$table = $wpdb->prefix . 'zeko_shop_notifications';
				$wpdb->update(
					$table,
					array( 'is_read' => 1 ),
					array(
						'id'      => $notification_id,
						'user_id' => $user_id,
					)
				);
			} elseif ( 'love' === $source ) {
				$table = $wpdb->prefix . 'zeko_love_notifications';
				$wpdb->update(
					$table,
					array( 'is_read' => 1 ),
					array(
						'notification_id' => $notification_id,
						'user_id'         => $user_id,
					)
				);
			} elseif ( 'rewards' === $source ) {
				$table = $wpdb->prefix . 'zeko_rewards_notifications';
				$wpdb->update(
					$table,
					array( 'is_read' => 1 ),
					array(
						'id'      => $notification_id,
						'user_id' => $user_id,
					)
				);
			} else {
				$this->db->mark_notification_read( $notification_id );
			}
		} else {
			// Mark all as read across all tables.
			$this->db->mark_notifications_read( $user_id );
			$qa_table      = $wpdb->prefix . 'zeko_qa_notifications';
			$pay_table     = $wpdb->prefix . 'zeko_notifications';
			$jobs_table    = $wpdb->prefix . 'zeko_job_notifications';
			$mentor_table  = $wpdb->prefix . 'zeko_mentor_notifications';
			$shop_table    = $wpdb->prefix . 'zeko_shop_notifications';
			$love_table    = $wpdb->prefix . 'zeko_love_notifications';
			$rewards_table = $wpdb->prefix . 'zeko_rewards_notifications';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $qa_table ) ) === $qa_table ) {
				$wpdb->update(
					$qa_table,
					array( 'is_read' => 1 ),
					array(
						'user_id' => $user_id,
						'is_read' => 0,
					)
				);
			}
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pay_table ) ) === $pay_table ) {
				$wpdb->update(
					$pay_table,
					array( 'is_read' => 1 ),
					array(
						'user_id' => $user_id,
						'is_read' => 0,
					)
				);
			}
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $jobs_table ) ) === $jobs_table ) {
				$wpdb->update(
					$jobs_table,
					array( 'is_read' => 1 ),
					array(
						'user_id' => $user_id,
						'is_read' => 0,
					)
				);
			}
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $mentor_table ) ) === $mentor_table ) {
				$wpdb->update(
					$mentor_table,
					array( 'is_read' => 1 ),
					array(
						'user_id' => $user_id,
						'is_read' => 0,
					)
				);
			}
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $shop_table ) ) === $shop_table ) {
				$wpdb->update(
					$shop_table,
					array( 'is_read' => 1 ),
					array(
						'user_id' => $user_id,
						'is_read' => 0,
					)
				);
			}
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $love_table ) ) === $love_table ) {
				$wpdb->update(
					$love_table,
					array( 'is_read' => 1 ),
					array(
						'user_id' => $user_id,
						'is_read' => 0,
					)
				);
			}
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rewards_table ) ) === $rewards_table ) {
				$wpdb->update(
					$rewards_table,
					array( 'is_read' => 1 ),
					array(
						'user_id' => $user_id,
						'is_read' => 0,
					)
				);
			}
		}

		wp_send_json_success( array( 'message' => __( 'Notifications marked as read.', 'zeko-learn' ) ) );
	}

	// ─── Student Progress (Instructor View) ─────────────────────.

	/**
	 * Handle get student progress.
	 */
	public function handle_get_student_progress(): void {
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$user_id   = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $course_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'zeko-learn' ) ) );
		}

		if ( ! $this->verify_course_owner( $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$enrollment = $this->db->get_enrollment( $user_id, $course_id );
		$lessons    = $this->db->get_course_lessons( $course_id );

		wp_send_json_success(
			array(
				'enrollment' => $enrollment,
				'lessons'    => $lessons,
			)
		);
	}

	// ─── Course Management (Admin) ──────────────────────────────.

	/**
	 * Handle update course status.
	 */
	public function handle_update_course_status(): void {
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $course_id || ! $this->verify_course_owner( $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value allow-listed below.
		$valid  = array( 'published', 'draft', 'pending', 'archived' );

		if ( ! in_array( $status, $valid, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'zeko-learn' ) ) );
		}

		$this->db->update_course( $course_id, array( 'status' => $status ) );

		wp_send_json_success(
			array(
				'message' => __( 'Course status updated.', 'zeko-learn' ),
				'status'  => $status,
			)
		);
	}

	/**
	 * Handle reorder sections.
	 */
	public function handle_reorder_sections(): void {
		$order = isset( $_POST['section_order'] ) ? array_map( 'absint', wp_unslash( $_POST['section_order'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; values absint()'d and intersected with the instructor's own section IDs below.

		if ( empty( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'zeko-learn' ) ) );
		}

		// Verify instructor owns the course that contains these sections.
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $course_id || ! $this->verify_course_owner( $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$valid_ids = array_map( 'absint', array_column( $this->db->get_course_sections( $course_id ), 'id' ) );
		$order     = array_values( array_intersect( $order, $valid_ids ) );
		if ( empty( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'zeko-learn' ) ) );
		}

		foreach ( $order as $position => $section_id ) {
			$this->db->update_section( $section_id, array( 'sort_order' => $position ) );
		}

		wp_send_json_success( array( 'message' => __( 'Sections reordered.', 'zeko-learn' ) ) );
	}

	/**
	 * Handle reorder lessons.
	 */
	public function handle_reorder_lessons(): void {
		$order = isset( $_POST['lesson_order'] ) ? array_map( 'absint', wp_unslash( $_POST['lesson_order'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; values absint()'d and intersected with the instructor's own lesson IDs below.

		if ( empty( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'zeko-learn' ) ) );
		}

		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $course_id || ! $this->verify_course_owner( $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$valid_ids = array_map( 'absint', array_column( $this->db->get_course_lessons( $course_id ), 'id' ) );
		$order     = array_values( array_intersect( $order, $valid_ids ) );
		if ( empty( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'zeko-learn' ) ) );
		}

		foreach ( $order as $position => $lesson_id ) {
			$this->db->update_lesson( $lesson_id, array( 'sort_order' => $position ) );
		}

		wp_send_json_success( array( 'message' => __( 'Lessons reordered.', 'zeko-learn' ) ) );
	}

	/**
	 * Handle duplicate course.
	 */
	public function handle_duplicate_course(): void {
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $course_id || ! $this->verify_course_owner( $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$course = $this->db->get_course( $course_id );

		if ( ! $course ) {
			wp_send_json_error( array( 'message' => __( 'Course not found.', 'zeko-learn' ) ) );
		}

		$new_data           = $course;
		$new_data['title']  = $course['title'] . ' (Copy)';
		$new_data['status'] = 'draft';
		unset( $new_data['id'], $new_data['created_at'], $new_data['updated_at'], $new_data['enrollment_count'], $new_data['avg_rating'], $new_data['review_count'] );

		$new_course_id = $this->db->insert_course( $new_data );

		$sections = $this->db->get_course_sections( $course_id );
		foreach ( $sections as $section ) {
			$new_section_id = $this->db->insert_section(
				array(
					'course_id'   => $new_course_id,
					'title'       => $section['title'],
					'description' => $section['description'],
					'sort_order'  => $section['sort_order'],
				)
			);

			$lessons = $this->db->get_section_lessons( (int) $section['id'] );
			foreach ( $lessons as $lesson ) {
				$this->db->insert_lesson(
					array(
						'section_id'        => $new_section_id,
						'course_id'         => $new_course_id,
						'title'             => $lesson['title'],
						'slug'              => $lesson['slug'],
						'lesson_type'       => $lesson['lesson_type'],
						'content'           => $lesson['content'],
						'video_url'         => $lesson['video_url'],
						'video_duration'    => $lesson['video_duration'],
						'attachment_url'    => $lesson['attachment_url'],
						'sort_order'        => $lesson['sort_order'],
						'is_preview'        => $lesson['is_preview'],
						'estimated_minutes' => $lesson['estimated_minutes'],
					)
				);
			}
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Course duplicated.', 'zeko-learn' ),
				'new_id'   => $new_course_id,
				'edit_url' => admin_url( 'admin.php?page=zeko-learn-add-course&course_id=' . $new_course_id ),
			)
		);
	}

	// ─── Export Grades ──────────────────────────────────────────.

	/**
	 * Handle export grades.
	 */
	public function handle_export_grades(): void {
		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $course_id || ! $this->verify_course_owner( $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$enrollments = $this->db->get_course_enrollments( $course_id );
		if ( empty( $enrollments ) ) {
			wp_send_json_error( array( 'message' => __( 'No enrollments found.', 'zeko-learn' ) ) );
		}

		$csv_rows   = array();
		$csv_rows[] = array( 'Student', 'Email', 'Progress %', 'Status', 'Enrolled' );

		foreach ( $enrollments as $e ) {
			$user       = get_userdata( $e['user_id'] );
			$csv_rows[] = array(
				$user ? $user->display_name : 'User #' . $e['user_id'],
				$user ? $user->user_email : '',
				$e['progress_percent'] ?? 0,
				$e['status'] ?? 'enrolled',
				$e['enrolled_at'] ?? '',
			);
		}

		$csv = '';
		foreach ( $csv_rows as $row ) {
			$csv .= '"' . implode(
				'","',
				array_map(
					function ( $v ) {
						return str_replace( '"', '""', $v );
					},
					$row
				)
			) . '"' . "\n";
		}

		$encoded = base64_encode( $csv );

		wp_send_json_success(
			array(
				'message'  => __( 'Grade export ready.', 'zeko-learn' ),
				'csv'      => $encoded,
				'filename' => 'grades-course-' . $course_id . '.csv',
			)
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		);
	}

	// ─── Announcements ─────────────────────────────────────────.

	/**
	 * Handle post announcement.
	 */
	public function handle_post_announcement(): void {
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$title     = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$content   = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $course_id || empty( $title ) || empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', 'zeko-learn' ) ) );
		}

		$course = $this->db->get_course( $course_id );
		if ( ! $course || get_current_user_id() !== (int) $course['instructor_id'] ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$announcement_id = $this->db->insert_announcement(
			array(
				'course_id'     => $course_id,
				'instructor_id' => get_current_user_id(),
				'title'         => $title,
				'content'       => $content,
			)
		);

		// Send announcement email to enrolled students.
		if ( class_exists( 'Zeko_Learn_Emails' ) ) {
			$emails       = new Zeko_Learn_Emails( $this->db );
			$announcement = array(
				'title'   => $title,
				'content' => $content,
			);
			$emails->send_announcement_email( $course_id, $announcement );
		}

		wp_send_json_success(
			array(
				'message'         => __( 'Announcement posted.', 'zeko-learn' ),
				'announcement_id' => $announcement_id,
			)
		);
	}

	/**
	 * Handle delete announcement.
	 */
	public function handle_delete_announcement(): void {
		$announcement_id = absint( $_POST['announcement_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $announcement_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid announcement.', 'zeko-learn' ) ) );
		}

		$announcement = $this->db->get_announcement( $announcement_id );
		if ( ! $announcement || ! $this->verify_course_owner( (int) $announcement['course_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$this->db->delete_announcement( $announcement_id );

		wp_send_json_success( array( 'message' => __( 'Announcement deleted.', 'zeko-learn' ) ) );
	}

	/**
	 * Handle pin announcement.
	 */
	public function handle_pin_announcement(): void {
		$announcement_id = absint( $_POST['announcement_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! $announcement_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid announcement.', 'zeko-learn' ) ) );
		}

		$announcement = $this->db->get_announcement( $announcement_id );
		if ( ! $announcement || ! $this->verify_course_owner( (int) $announcement['course_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		global $wpdb;
		$table   = $this->db->get_table_course_announcements();
		$current = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT is_pinned FROM {$table} WHERE id = %d", $announcement_id )
		);

		$this->db->update_announcement(
			$announcement_id,
			array(
				'is_pinned' => $current ? 0 : 1,
			)
		);

		wp_send_json_success(
			array(
				'message' => $current ? __( 'Announcement unpinned.', 'zeko-learn' ) : __( 'Announcement pinned.', 'zeko-learn' ),
				'pinned'  => ! $current,
			)
		);
	}

	// ─── Learning Goals ────────────────────────────────────────.

	/**
	 * Handle set goal.
	 */
	public function handle_set_goal(): void {
		$course_id   = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$goal_text   = sanitize_text_field( wp_unslash( $_POST['goal_text'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$target_date = sanitize_text_field( wp_unslash( $_POST['target_date'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $course_id || empty( $goal_text ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a goal.', 'zeko-learn' ) ) );
		}

		$goal_id = $this->db->insert_learning_goal(
			array(
				'user_id'     => get_current_user_id(),
				'course_id'   => $course_id,
				'goal_text'   => $goal_text,
				'target_date' => $target_date ?: null,
				'status'      => 'active',
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Goal set!', 'zeko-learn' ),
				'goal_id' => $goal_id,
			)
		);
	}

	/**
	 * Handle update goal.
	 */
	public function handle_update_goal(): void {
		$goal_id = absint( $_POST['goal_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$status  = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value allow-listed below.

		if ( ! $goal_id || ! in_array( $status, array( 'active', 'completed', 'abandoned' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'zeko-learn' ) ) );
		}

		$goal = $this->db->get_learning_goal( $goal_id );
		if ( ! $goal || get_current_user_id() !== (int) $goal['user_id'] ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$this->db->update_learning_goal( $goal_id, array( 'status' => $status ) );

		wp_send_json_success( array( 'message' => __( 'Goal updated.', 'zeko-learn' ) ) );
	}

	// ─── Course Creation (Frontend) ─────────────────────────────.

	/**
	 * Handle create course.
	 */
	public function handle_create_course(): void {
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$title        = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$subtitle     = sanitize_text_field( wp_unslash( $_POST['subtitle'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$description  = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$category_id  = absint( wp_unslash( $_POST['category_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$level        = sanitize_text_field( wp_unslash( $_POST['level'] ?? 'beginner' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value allow-listed below.
		$price        = (float) sanitize_text_field( wp_unslash( $_POST['price'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$sale_price   = isset( $_POST['sale_price'] ) && '' !== $_POST['sale_price'] ? (float) sanitize_text_field( wp_unslash( $_POST['sale_price'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$thumbnail_id = absint( wp_unslash( $_POST['thumbnail_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$language     = sanitize_text_field( wp_unslash( $_POST['language'] ?? 'en' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$estimated_hours = (float) sanitize_text_field( wp_unslash( $_POST['estimated_hours'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$status          = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'draft' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value allow-listed below.
		$is_featured     = ! empty( $_POST['is_featured'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$is_free         = ( $price <= 0 ) ? 1 : 0;
		$promo_video_url = esc_url_raw( wp_unslash( $_POST['promo_video_url'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$seo_title       = sanitize_text_field( wp_unslash( $_POST['seo_title'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$seo_description = sanitize_text_field( wp_unslash( $_POST['seo_description'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$what_you_learn  = wp_kses_post( wp_unslash( $_POST['what_you_learn'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$requirements    = wp_kses_post( wp_unslash( $_POST['requirements'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$target_audience = wp_kses_post( wp_unslash( $_POST['target_audience'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$skill_ids_raw   = sanitize_text_field( wp_unslash( $_POST['skill_ids'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => __( 'Title is required.', 'zeko-learn' ) ) );
		}

		$valid_statuses = array( 'draft', 'pending', 'published', 'archived' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$status = 'draft';
		}
		$valid_levels = array( 'beginner', 'intermediate', 'advanced', 'all_levels' );
		if ( ! in_array( $level, $valid_levels, true ) ) {
			$level = 'beginner';
		}

		$course_id = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; ownership re-checked below.
		$data      = array(
			'instructor_id'   => get_current_user_id(),
			'title'           => $title,
			'subtitle'        => $subtitle,
			'description'     => $description,
			'category_id'     => $category_id,
			'level'           => $level,
			'price'           => $price,
			'sale_price'      => $sale_price,
			'thumbnail_id'    => $thumbnail_id,
			'status'          => $status,
			'language'        => $language,
			'estimated_hours' => $estimated_hours,
			'is_featured'     => $is_featured,
			'is_free'         => $is_free,
			'promo_video_url' => $promo_video_url,
			'seo_title'       => $seo_title,
			'seo_description' => $seo_description,
			'what_you_learn'  => $what_you_learn,
			'requirements'    => $requirements,
			'target_audience' => $target_audience,
		);

		if ( $course_id ) {
			$course = $this->db->get_course( $course_id );
			if ( ! $course || get_current_user_id() !== (int) $course['instructor_id'] ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
			}
			$this->db->update_course( $course_id, $data );
		} else {
			$course_id = $this->db->insert_course( $data );
		}

		// ── Save skills ───────────────────────────────────────.
		if ( $course_id && '' !== $skill_ids_raw ) {
			$new_skills = array_filter( array_map( 'absint', explode( ',', $skill_ids_raw ) ) );
			$existing   = array_column( $this->db->get_course_skills( $course_id ), 'id' );
			foreach ( array_diff( $existing, $new_skills ) as $remove_id ) {
				$this->db->detach_skill_from_course( $course_id, $remove_id );
			}
			foreach ( array_diff( $new_skills, $existing ) as $add_id ) {
				$this->db->attach_skill_to_course( $course_id, $add_id );
			}
		}

		wp_send_json_success(
			array(
				'message'   => $course_id ? __( 'Course saved.', 'zeko-learn' ) : __( 'Course created.', 'zeko-learn' ),
				'course_id' => $course_id,
			)
		);
	}

	/**
	 * Handle save section.
	 */
	public function handle_save_section(): void {
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$course_id   = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$section_id  = absint( $_POST['section_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$description = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$sort_order  = absint( $_POST['sort_order'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $course_id || empty( $title ) ) {
			wp_send_json_error( array( 'message' => __( 'Course ID and title are required.', 'zeko-learn' ) ) );
		}

		$course = $this->db->get_course( $course_id );
		if ( ! $course || get_current_user_id() !== (int) $course['instructor_id'] ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$payload = array(
			'course_id'   => $course_id,
			'title'       => $title,
			'description' => $description,
			'sort_order'  => $sort_order,
		);

		if ( $section_id ) {
			$this->db->update_section( $section_id, $payload );
		} else {
			$section_id = $this->db->insert_section( $payload );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Section saved.', 'zeko-learn' ),
				'section_id' => $section_id,
			)
		);
	}

	/**
	 * Handle save lesson.
	 */
	public function handle_save_lesson(): void {
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$course_id         = absint( $_POST['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$section_id        = absint( $_POST['section_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$lesson_id         = absint( $_POST['lesson_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$title             = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$lesson_type       = sanitize_text_field( wp_unslash( $_POST['lesson_type'] ?? 'text' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$content           = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$video_url         = esc_url_raw( wp_unslash( $_POST['video_url'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$sort_order        = absint( $_POST['sort_order'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$estimated_minutes = absint( $_POST['estimated_minutes'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		$is_preview        = ! empty( $_POST['is_preview'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.

		if ( ! $course_id || ! $section_id || empty( $title ) ) {
			wp_send_json_error( array( 'message' => __( 'Course ID, section ID, and title are required.', 'zeko-learn' ) ) );
		}

		$course = $this->db->get_course( $course_id );
		if ( ! $course || get_current_user_id() !== (int) $course['instructor_id'] ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$payload = array(
			'section_id'        => $section_id,
			'course_id'         => $course_id,
			'title'             => $title,
			'lesson_type'       => $lesson_type,
			'content'           => $content,
			'video_url'         => $video_url,
			'sort_order'        => $sort_order,
			'estimated_minutes' => $estimated_minutes,
			'is_preview'        => $is_preview,
		);

		if ( $lesson_id ) {
			$this->db->update_lesson( $lesson_id, $payload );
		} else {
			$lesson_id = $this->db->insert_lesson( $payload );
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Lesson saved.', 'zeko-learn' ),
				'lesson_id' => $lesson_id,
			)
		);
	}

	// ─── Settings ────────────────────────────────────────────.

	/**
	 * Handle save instructor settings.
	 */
	public function handle_save_instructor_settings(): void {
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$user_id = get_current_user_id();
		$prefix  = 'zeko_learn_instructor_';

		// ── Text fields ───────────────────────────────────────.
		$text_fields = array(
			'bio',
			'expertise',
			'display_name',
			'hourly_rate',
			'teaching_style',
			'payout_details',
		);
		foreach ( $text_fields as $field ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
			if ( 'bio' === $field ) {
				$value = wp_kses_post( wp_unslash( $_POST[ $field ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
			}
			update_user_meta( $user_id, $prefix . $field, $value );
		}

		// ── URL fields ─────────────────────────────────────────.
		$url_fields = array( 'website', 'linkedin', 'twitter', 'youtube', 'github' );
		foreach ( $url_fields as $field ) {
			$value = isset( $_POST[ $field ] ) ? esc_url_raw( wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
			update_user_meta( $user_id, $prefix . $field, $value );
		}

		// ── Select fields ──────────────────────────────────────.
		$payout_method = sanitize_text_field( wp_unslash( $_POST['payout_method'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value allow-listed below.
		$valid_methods = array( 'paypal', 'bank_transfer', 'wallet' );
		if ( in_array( $payout_method, $valid_methods, true ) ) {
			update_user_meta( $user_id, $prefix . 'payout_method', $payout_method );
		}

		$default_level = sanitize_text_field( wp_unslash( $_POST['default_course_level'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value allow-listed below.
		$valid_levels  = array( 'beginner', 'intermediate', 'advanced' );
		if ( in_array( $default_level, $valid_levels, true ) ) {
			update_user_meta( $user_id, $prefix . 'default_course_level', $default_level );
		}

		// ── Notification checkboxes ────────────────────────────.
		$notif_fields = array(
			'notify_on_enrollment',
			'notify_on_review',
			'notify_on_question',
			'notify_on_completion',
			'weekly_digest',
		);
		foreach ( $notif_fields as $field ) {
			$value = ! empty( $_POST[ $field ] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
			update_user_meta( $user_id, $prefix . $field, $value );
		}

		// ── Update display name globally if provided ───────────.
		$display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! empty( $display_name ) ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->users,
				array( 'display_name' => $display_name ),
				array( 'ID' => $user_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'zeko-learn' ) ) );
	}

	/**
	 * Handle save student settings.
	 */
	public function handle_save_student_settings(): void {
		$user_id = get_current_user_id();
		$prefix  = 'zeko_learn_student_';

		// ── Text fields ───────────────────────────────────────.
		$text_fields = array( 'display_name', 'bio' );
		foreach ( $text_fields as $field ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
			if ( 'bio' === $field ) {
				$value = wp_kses_post( wp_unslash( $_POST[ $field ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
			}
			update_user_meta( $user_id, $prefix . $field, $value );
		}

		// ── Select fields ──────────────────────────────────────.
		$learning_goal = sanitize_text_field( wp_unslash( $_POST['learning_goal'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value allow-listed below.
		$valid_goals   = array( 'casual', 'career', 'academic' );
		if ( in_array( $learning_goal, $valid_goals, true ) ) {
			update_user_meta( $user_id, $prefix . 'learning_goal', $learning_goal );
		}

		$preferred_level = sanitize_text_field( wp_unslash( $_POST['preferred_level'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value allow-listed below.
		$valid_levels    = array( 'beginner', 'intermediate', 'advanced' );
		if ( in_array( $preferred_level, $valid_levels, true ) ) {
			update_user_meta( $user_id, $prefix . 'preferred_level', $preferred_level );
		}

		$daily_goal = absint( $_POST['daily_goal_minutes'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value clamped to 480 below.
		$daily_goal = min( $daily_goal, 480 );
		update_user_meta( $user_id, $prefix . 'daily_goal_minutes', $daily_goal );

		// ── Notification checkboxes ────────────────────────────.
		$notif_fields = array(
			'notify_on_completion',
			'notify_on_announcement',
			'notify_on_reply',
			'notify_weekly_summary',
			'email_notifications',
		);
		foreach ( $notif_fields as $field ) {
			$value = ! empty( $_POST[ $field ] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
			update_user_meta( $user_id, $prefix . $field, $value );
		}

		// ── Theme preference ───────────────────────────────────.
		$theme        = sanitize_text_field( wp_unslash( $_POST['theme_preference'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch; value allow-listed below.
		$valid_themes = array( 'system', 'light', 'dark' );
		if ( in_array( $theme, $valid_themes, true ) ) {
			update_user_meta( $user_id, $prefix . 'theme_preference', $theme );
		}

		// ── Update display name globally if provided ───────────.
		$display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_dispatcher() before handler dispatch.
		if ( ! empty( $display_name ) ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->users,
				array( 'display_name' => $display_name ),
				array( 'ID' => $user_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'zeko-learn' ) ) );
	}

	// ─── Skill Search ─────────────────────────────────────────.

	/**
	 * Handle search skills.
	 */
	public function handle_search_skills(): void {
		$search = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, sanitized skill autocomplete search; no state change, CSRF not applicable.
		if ( mb_strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'skills' => array() ) );
		}

		$skills = $this->db->search_skills( $search );
		wp_send_json_success( array( 'skills' => $skills ) );
	}

	// ─── Demo Data ──────────────────────────────────────────────.

	/**
	 * Handle generate demo data.
	 */
	public function handle_generate_demo_data(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$seeder = new Zeko_Learn_Demo_Data( $this->db );
		$result = $seeder->seed();

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
				/* translators: 1: categories, 2: skills, 3: courses, 4: enrollments */
					__( 'Demo data generated: %1$d categories, %2$d skills, %3$d courses, %4$d enrollments.', 'zeko-learn' ),
					$result['categories'],
					$result['skills'],
					$result['courses'],
					$result['enrollments']
				),
			)
		);
	}

	/**
	 * Handle clear demo data.
	 */
	public function handle_clear_demo_data(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zeko-learn' ) ) );
		}

		$seeder = new Zeko_Learn_Demo_Data( $this->db );
		$seeder->clear();

		wp_send_json_success( array( 'message' => __( 'All demo data cleared.', 'zeko-learn' ) ) );
	}
}
