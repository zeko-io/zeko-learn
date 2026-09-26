<?php
/**
 * Zeko Learn — WordPress personal-data exporter and eraser.
 *
 * Registers with Tools > Export Personal Data / Erase Personal Data so site
 * owners can fulfil data-protection requests for course enrollments, learning
 * progress, quiz attempts, assignment submissions, certificates, notes,
 * bookmarks, learning goals and streak data. Community content (reviews,
 * discussion threads and votes) and instructor content (courses, course
 * announcements, payouts) are handled as follows: a user's own per-student
 * rows are deleted, while publicly-facing rows authored by the user (course
 * reviews, discussion threads) are anonymized to user_id 0 so the live course
 * catalogs and discussions keep their structure; instructor-owned content
 * survives with instructor_id scrubbed to 0.
 *
 * Table schemas byte-verified 2026-09-25 against class-zeko-learn-db.php DDL:
 *   {prefix}zeko_learn_courses            instructor_id / created_at / title
 *   {prefix}zeko_learn_enrollments        user_id / course_id / status
 *   {prefix}zeko_learn_lesson_progress    user_id / lesson_id / course_id
 *   {prefix}zeko_learn_course_progress    user_id / course_id / completion_pct
 *   {prefix}zeko_learn_quiz_attempts      user_id / quiz_id / score / answers
 *   {prefix}zeko_learn_assignment_submissions user_id / file_url / graded_by
 *   {prefix}zeko_learn_reviews            user_id (scrubbed) / course_id
 *   {prefix}zeko_learn_discussions        user_id (scrubbed) / parent_id
 *   {prefix}zeko_learn_discussion_votes   user_id / discussion_id / vote_type
 *   {prefix}zeko_learn_certificates       user_id / certificate_number
 *   {prefix}zeko_learn_notes              user_id / lesson_id / content
 *   {prefix}zeko_learn_bookmarks          user_id / lesson_id / course_id
 *   {prefix}zeko_learn_learning_goals     user_id / goal_text / target_date
 *   {prefix}zeko_learn_course_announcements instructor_id (scrubbed)
 *   {prefix}zeko_learn_instructor_payouts instructor_id / amount / paid_at
 *   {prefix}zeko_learn_notifications      user_id / actor_id (scrubbed)
 *   {prefix}zeko_learn_wishlist           user_id / course_id
 *   {prefix}zeko_learn_learning_streaks   user_id / streak columns
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the exporter, eraser and retention-table callbacks.
 */
function zeko_learn_privacy_register(): void {
	add_filter( 'wp_privacy_personal_data_exporters', 'zeko_learn_privacy_register_exporter' );
	add_filter( 'wp_privacy_personal_data_erasers', 'zeko_learn_privacy_register_eraser' );
}
add_action( 'init', 'zeko_learn_privacy_register', 11 );

/**
 * Register the personal-data exporter.
 *
 * @param array $exporters Exporters.
 */
function zeko_learn_privacy_register_exporter( array $exporters ): array {
	$exporters['zeko-learn'] = array(
		'exporter_friendly_name' => __( 'Zeko Learn data', 'zeko-learn' ),
		'callback'               => 'zeko_learn_privacy_export',
	);
	return $exporters;
}

/**
 * Register the personal-data eraser.
 *
 * @param array $erasers Erasers.
 */
function zeko_learn_privacy_register_eraser( array $erasers ): array {
	$erasers['zeko-learn'] = array(
		'eraser_friendly_name' => __( 'Zeko Learn data', 'zeko-learn' ),
		'callback'             => 'zeko_learn_privacy_erase',
	);
	return $erasers;
}

/**
 * Get a prepared DB instance (null when the plugin is not active).
 */
function zeko_learn_privacy_db(): ?Zeko_Learn_DB {
	if ( ! class_exists( 'Zeko_Learn_DB' ) ) {
		return null;
	}
	return new Zeko_Learn_DB();
}

/**
 * Whether a table exists (guards every touch of a table).
 *
 * @param string $table Table.
 */
function zeko_learn_privacy_table_exists( string $table ): bool {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
}

/**
 * Export a user's Zeko Learn data, 20 rows per table per page.
 *
 * @return array{data: array, done: bool}
 * @param string $email_address User who requested the export.
 * @param int    $page Export page (batching).
 */
function zeko_learn_privacy_export( string $email_address, int $page = 1 ): array {
	$user = get_user_by( 'email', $email_address );
	if ( ! $user ) {
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	$db = zeko_learn_privacy_db();
	if ( ! $db ) {
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	global $wpdb;

	$user_id   = (int) $user->ID;
	$per_page  = 20;
	$offset    = ( max( 1, (int) $page ) - 1 ) * $per_page;
	$data      = array();
	$tables    = 0;
	$exhausted = 0;

	if ( zeko_learn_privacy_table_exists( $db->get_table_enrollments() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, course_id, status, enrolled_at, completed_at FROM {$db->get_table_enrollments()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-enrollments',
				'group_label' => __( 'Zeko Learn — Enrollments', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-enrollment-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'Status', 'zeko-learn' ),
						'value' => (string) $row->status,
					),
					array(
						'name'  => __( 'Enrolled at', 'zeko-learn' ),
						'value' => (string) $row->enrolled_at,
					),
					array(
						'name'  => __( 'Completed at', 'zeko-learn' ),
						'value' => (string) $row->completed_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_lesson_progress() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, lesson_id, course_id, status, started_at, completed_at FROM {$db->get_table_lesson_progress()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-progress',
				'group_label' => __( 'Zeko Learn — Lesson progress', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-progress-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Lesson ID', 'zeko-learn' ),
						'value' => (string) $row->lesson_id,
					),
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'Status', 'zeko-learn' ),
						'value' => (string) $row->status,
					),
					array(
						'name'  => __( 'Started at', 'zeko-learn' ),
						'value' => (string) $row->started_at,
					),
					array(
						'name'  => __( 'Completed at', 'zeko-learn' ),
						'value' => (string) $row->completed_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_course_progress() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, course_id, completion_pct, last_activity_at FROM {$db->get_table_course_progress()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-course-progress',
				'group_label' => __( 'Zeko Learn — Course progress', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-course-progress-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'Completion', 'zeko-learn' ),
						'value' => (string) $row->completion_pct,
					),
					array(
						'name'  => __( 'Last activity at', 'zeko-learn' ),
						'value' => (string) $row->last_activity_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_quiz_attempts() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, quiz_id, score, is_passed, answers, started_at, completed_at FROM {$db->get_table_quiz_attempts()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-quiz-attempts',
				'group_label' => __( 'Zeko Learn — Quiz attempts', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-quiz-attempt-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Quiz ID', 'zeko-learn' ),
						'value' => (string) $row->quiz_id,
					),
					array(
						'name'  => __( 'Score', 'zeko-learn' ),
						'value' => (string) $row->score,
					),
					array(
						'name'  => __( 'Passed', 'zeko-learn' ),
						'value' => (string) $row->is_passed,
					),
					array(
						'name'  => __( 'Answers', 'zeko-learn' ),
						'value' => (string) $row->answers,
					),
					array(
						'name'  => __( 'Started at', 'zeko-learn' ),
						'value' => (string) $row->started_at,
					),
					array(
						'name'  => __( 'Completed at', 'zeko-learn' ),
						'value' => (string) $row->completed_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_assignment_submissions() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, assignment_id, course_id, file_url, notes, status, grade, feedback, submitted_at FROM {$db->get_table_assignment_submissions()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-assignments',
				'group_label' => __( 'Zeko Learn — Assignment submissions', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-assignment-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Assignment ID', 'zeko-learn' ),
						'value' => (string) $row->assignment_id,
					),
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'File URL', 'zeko-learn' ),
						'value' => (string) $row->file_url,
					),
					array(
						'name'  => __( 'Notes', 'zeko-learn' ),
						'value' => (string) $row->notes,
					),
					array(
						'name'  => __( 'Status', 'zeko-learn' ),
						'value' => (string) $row->status,
					),
					array(
						'name'  => __( 'Grade', 'zeko-learn' ),
						'value' => (string) $row->grade,
					),
					array(
						'name'  => __( 'Feedback', 'zeko-learn' ),
						'value' => (string) $row->feedback,
					),
					array(
						'name'  => __( 'Submitted at', 'zeko-learn' ),
						'value' => (string) $row->submitted_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_reviews() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, course_id, rating, review_text, created_at FROM {$db->get_table_reviews()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-reviews',
				'group_label' => __( 'Zeko Learn — Reviews', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-review-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'Rating', 'zeko-learn' ),
						'value' => (string) $row->rating,
					),
					array(
						'name'  => __( 'Review', 'zeko-learn' ),
						'value' => (string) $row->review_text,
					),
					array(
						'name'  => __( 'Created at', 'zeko-learn' ),
						'value' => (string) $row->created_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_discussions() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, course_id, lesson_id, title, content, created_at FROM {$db->get_table_discussions()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-discussions',
				'group_label' => __( 'Zeko Learn — Discussions', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-discussion-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'Lesson ID', 'zeko-learn' ),
						'value' => (string) $row->lesson_id,
					),
					array(
						'name'  => __( 'Title', 'zeko-learn' ),
						'value' => (string) $row->title,
					),
					array(
						'name'  => __( 'Content', 'zeko-learn' ),
						'value' => (string) $row->content,
					),
					array(
						'name'  => __( 'Created at', 'zeko-learn' ),
						'value' => (string) $row->created_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_discussion_votes() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, discussion_id, vote_type FROM {$db->get_table_discussion_votes()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-discussion-votes',
				'group_label' => __( 'Zeko Learn — Discussion votes', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-vote-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Discussion ID', 'zeko-learn' ),
						'value' => (string) $row->discussion_id,
					),
					array(
						'name'  => __( 'Vote', 'zeko-learn' ),
						'value' => (string) $row->vote_type,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_certificates() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, course_id, certificate_number, pdf_url, issued_at FROM {$db->get_table_certificates()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-certificates',
				'group_label' => __( 'Zeko Learn — Certificates', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-certificate-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'Certificate number', 'zeko-learn' ),
						'value' => (string) $row->certificate_number,
					),
					array(
						'name'  => __( 'PDF URL', 'zeko-learn' ),
						'value' => (string) $row->pdf_url,
					),
					array(
						'name'  => __( 'Issued at', 'zeko-learn' ),
						'value' => (string) $row->issued_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_notes() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, lesson_id, course_id, content, created_at, updated_at FROM {$db->get_table_notes()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-notes',
				'group_label' => __( 'Zeko Learn — Notes', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-note-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Lesson ID', 'zeko-learn' ),
						'value' => (string) $row->lesson_id,
					),
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'Content', 'zeko-learn' ),
						'value' => (string) $row->content,
					),
					array(
						'name'  => __( 'Created at', 'zeko-learn' ),
						'value' => (string) $row->created_at,
					),
					array(
						'name'  => __( 'Updated at', 'zeko-learn' ),
						'value' => (string) $row->updated_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_bookmarks() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, lesson_id, course_id, created_at FROM {$db->get_table_bookmarks()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-bookmarks',
				'group_label' => __( 'Zeko Learn — Bookmarks', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-bookmark-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Lesson ID', 'zeko-learn' ),
						'value' => (string) $row->lesson_id,
					),
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'Created at', 'zeko-learn' ),
						'value' => (string) $row->created_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_learning_goals() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, course_id, goal_text, target_date, status FROM {$db->get_table_learning_goals()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-goals',
				'group_label' => __( 'Zeko Learn — Learning goals', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-goal-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'Goal', 'zeko-learn' ),
						'value' => (string) $row->goal_text,
					),
					array(
						'name'  => __( 'Target date', 'zeko-learn' ),
						'value' => (string) $row->target_date,
					),
					array(
						'name'  => __( 'Status', 'zeko-learn' ),
						'value' => (string) $row->status,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_notifications() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, module, action, object_type, is_read, created_at FROM {$db->get_table_notifications()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-notifications',
				'group_label' => __( 'Zeko Learn — Notifications', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-notification-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Module', 'zeko-learn' ),
						'value' => (string) $row->module,
					),
					array(
						'name'  => __( 'Action', 'zeko-learn' ),
						'value' => (string) $row->action,
					),
					array(
						'name'  => __( 'Object type', 'zeko-learn' ),
						'value' => (string) $row->object_type,
					),
					array(
						'name'  => __( 'Read', 'zeko-learn' ),
						'value' => (string) $row->is_read,
					),
					array(
						'name'  => __( 'Created at', 'zeko-learn' ),
						'value' => (string) $row->created_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_wishlist() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, course_id, created_at FROM {$db->get_table_wishlist()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-wishlist',
				'group_label' => __( 'Zeko Learn — Wishlist', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-wishlist-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'Created at', 'zeko-learn' ),
						'value' => (string) $row->created_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_learning_streaks() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, current_streak, longest_streak, last_active_date, total_days_active FROM {$db->get_table_learning_streaks()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-streaks',
				'group_label' => __( 'Zeko Learn — Learning streaks', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-streak-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Current streak', 'zeko-learn' ),
						'value' => (string) $row->current_streak,
					),
					array(
						'name'  => __( 'Longest streak', 'zeko-learn' ),
						'value' => (string) $row->longest_streak,
					),
					array(
						'name'  => __( 'Last active', 'zeko-learn' ),
						'value' => (string) $row->last_active_date,
					),
					array(
						'name'  => __( 'Total days active', 'zeko-learn' ),
						'value' => (string) $row->total_days_active,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_instructor_payouts() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, course_id, amount, period_start, period_end, status, paid_at FROM {$db->get_table_instructor_payouts()} WHERE instructor_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-payouts',
				'group_label' => __( 'Zeko Learn — Instructor payouts', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-payout-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->course_id,
					),
					array(
						'name'  => __( 'Amount', 'zeko-learn' ),
						'value' => (string) $row->amount,
					),
					array(
						'name'  => __( 'Period', 'zeko-learn' ),
						'value' => (string) $row->period_start . ' — ' . $row->period_end,
					),
					array(
						'name'  => __( 'Status', 'zeko-learn' ),
						'value' => (string) $row->status,
					),
					array(
						'name'  => __( 'Paid at', 'zeko-learn' ),
						'value' => (string) $row->paid_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_learn_privacy_table_exists( $db->get_table_courses() ) ) {
		++$tables;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, status, created_at FROM {$db->get_table_courses()} WHERE instructor_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-learn-courses',
				'group_label' => __( 'Zeko Learn — Courses you created', 'zeko-learn' ),
				'item_id'     => 'zeko-learn-course-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Course ID', 'zeko-learn' ),
						'value' => (string) $row->id,
					),
					array(
						'name'  => __( 'Title', 'zeko-learn' ),
						'value' => (string) $row->title,
					),
					array(
						'name'  => __( 'Status', 'zeko-learn' ),
						'value' => (string) $row->status,
					),
					array(
						'name'  => __( 'Created at', 'zeko-learn' ),
						'value' => (string) $row->created_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	return array(
		'data' => $data,
		'done' => $exhausted === $tables,
	);
}

/**
 * Erase a user's Zeko Learn data.
 * Per-student rows are deleted 20 per pass; publicly-facing community rows
 * (course reviews, discussion threads) and instructor content (courses, course
 * announcements) are anonymized — their authoring user columns are scrubbed to
 * 0 so the course catalog and discussion forums do not lose their content — and
 * payout records owned by the user are deleted. Called repeatedly with an
 * incremental page until done is true.
 *
 * @return array{items_removed: int, items_retained: int, messages: array, done: bool}
 * @param string $email_address User who requested erasure.
 * @param int    $_page page.
 */
function zeko_learn_privacy_erase( string $email_address, int $_page = 1 ): array {
	$user = get_user_by( 'email', $email_address );
	if ( ! $user ) {
		return array(
			'items_removed'  => 0,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}

	$db = zeko_learn_privacy_db();
	if ( ! $db ) {
		return array(
			'items_removed'  => 0,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}

	global $wpdb;

	$user_id = (int) $user->ID;
	$removed = 0;
	$paged   = 20;

	// Edits to rows owned by OTHER users are attributed via an actor column —
	// those columns are scrubbed so no personal identifier survives. The owned
	// rows themselves are deleted below.
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( zeko_learn_privacy_table_exists( $db->get_table_notifications() ) ) {
		$removed += (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$db->get_table_notifications()} SET actor_id = 0 WHERE actor_id = %d",
				$user_id
			)
		);
	}
	if ( zeko_learn_privacy_table_exists( $db->get_table_assignment_submissions() ) ) {
		$removed += (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$db->get_table_assignment_submissions()} SET graded_by = 0 WHERE graded_by = %d",
				$user_id
			)
		);
	}
	if ( zeko_learn_privacy_table_exists( $db->get_table_reviews() ) ) {
		$removed += (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$db->get_table_reviews()} SET user_id = 0 WHERE user_id = %d",
				$user_id
			)
		);
	}
	if ( zeko_learn_privacy_table_exists( $db->get_table_discussions() ) ) {
		$removed += (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$db->get_table_discussions()} SET user_id = 0 WHERE user_id = %d",
				$user_id
			)
		);
	}
	if ( zeko_learn_privacy_table_exists( $db->get_table_course_announcements() ) ) {
		$removed += (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$db->get_table_course_announcements()} SET instructor_id = 0 WHERE instructor_id = %d",
				$user_id
			)
		);
	}
	if ( zeko_learn_privacy_table_exists( $db->get_table_courses() ) ) {
		$removed += (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$db->get_table_courses()} SET instructor_id = 0 WHERE instructor_id = %d",
				$user_id
			)
		);
	}
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	foreach ( array(
		$db->get_table_enrollments(),
		$db->get_table_lesson_progress(),
		$db->get_table_course_progress(),
		$db->get_table_quiz_attempts(),
		$db->get_table_assignment_submissions(),
		$db->get_table_discussion_votes(),
		$db->get_table_certificates(),
		$db->get_table_notes(),
		$db->get_table_bookmarks(),
		$db->get_table_learning_goals(),
		$db->get_table_notifications(),
		$db->get_table_wishlist(),
		$db->get_table_learning_streaks(),
	) as $table ) {
		if ( ! zeko_learn_privacy_table_exists( $table ) ) {
			continue;
		}
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$removed += (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE user_id = %d LIMIT %d",
				$user_id,
				$paged
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// Instructor payout records are keyed on instructor_id, not user_id.
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( zeko_learn_privacy_table_exists( $db->get_table_instructor_payouts() ) ) {
		$removed += (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$db->get_table_instructor_payouts()} WHERE instructor_id = %d LIMIT %d",
				$user_id,
				$paged
			)
		);
	}
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	// Reviews and discussions authored by this user were scrubbed; the rows
	// themselves belong to the community. Skip deleting them to avoid draining
	// course feedback — but still delete this user's discussion VOTES (done
	// above), which are private preference data.

	// Re-check every owned per-student table for stragglers so `done` is only
	// true once the user's rows are fully drained.
	$remaining = 0;
	foreach ( array(
		$db->get_table_enrollments(),
		$db->get_table_lesson_progress(),
		$db->get_table_course_progress(),
		$db->get_table_quiz_attempts(),
		$db->get_table_assignment_submissions(),
		$db->get_table_discussion_votes(),
		$db->get_table_certificates(),
		$db->get_table_notes(),
		$db->get_table_bookmarks(),
		$db->get_table_learning_goals(),
		$db->get_table_notifications(),
		$db->get_table_wishlist(),
		$db->get_table_learning_streaks(),
	) as $table ) {
		if ( ! zeko_learn_privacy_table_exists( $table ) ) {
			continue;
		}
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$remaining += (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( zeko_learn_privacy_table_exists( $db->get_table_instructor_payouts() ) ) {
		$remaining += (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$db->get_table_instructor_payouts()} WHERE instructor_id = %d",
				$user_id
			)
		);
	}
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$removed += (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
			$user_id,
			'zeko_learn_%'
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	$messages = array();
	if ( 0 !== $remaining || $removed > 0 ) {
		$messages[] = __( 'Your Zeko Learn enrollment, progress, assignment, certificate, note and bookmark records were removed. Public course reviews and discussion threads you posted were kept but detached from your account.', 'zeko-learn' );
	}

	return array(
		'items_removed'  => $removed,
		'items_retained' => 0,
		'messages'       => $messages,
		'done'           => 0 === $remaining,
	);
}
