<?php
/**
 * Database schema and query layer for Zeko Learn.
 *
 * 26 custom tables for courses, lessons, quizzes, enrollments, progress,
 * certificates, discussions, reviews, and more.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Learn_DB. */
class Zeko_Learn_DB {

	/**
	 * Db version.
	 *
	 * @var string Db version.
	 */
	private string $db_version = '1.0.0';

	/**
	 * Wpdb.
	 *
	 * @var \wpdb Wpdb.
	 */
	private \wpdb $wpdb;

	/**
	 * Table courses.
	 *
	 * @var string Table courses.
	 */
	private string $table_courses;
	/**
	 * Table course sections.
	 *
	 * @var string Table course sections.
	 */
	private string $table_course_sections;
	/**
	 * Table lessons.
	 *
	 * @var string Table lessons.
	 */
	private string $table_lessons;
	/**
	 * Table enrollments.
	 *
	 * @var string Table enrollments.
	 */
	private string $table_enrollments;
	/**
	 * Table lesson progress.
	 *
	 * @var string Table lesson progress.
	 */
	private string $table_lesson_progress;
	/**
	 * Table course progress.
	 *
	 * @var string Table course progress.
	 */
	private string $table_course_progress;
	/**
	 * Table quizzes.
	 *
	 * @var string Table quizzes.
	 */
	private string $table_quizzes;
	/**
	 * Table quiz questions.
	 *
	 * @var string Table quiz questions.
	 */
	private string $table_quiz_questions;
	/**
	 * Table quiz attempts.
	 *
	 * @var string Table quiz attempts.
	 */
	private string $table_quiz_attempts;
	/**
	 * Table assignments.
	 *
	 * @var string Table assignments.
	 */
	private string $table_assignments;
	/**
	 * Table assignment submissions.
	 *
	 * @var string Table assignment submissions.
	 */
	private string $table_assignment_submissions;
	/**
	 * Table reviews.
	 *
	 * @var string Table reviews.
	 */
	private string $table_reviews;
	/**
	 * Table categories.
	 *
	 * @var string Table categories.
	 */
	private string $table_categories;
	/**
	 * Table skills.
	 *
	 * @var string Table skills.
	 */
	private string $table_skills;
	/**
	 * Table course skills.
	 *
	 * @var string Table course skills.
	 */
	private string $table_course_skills;
	/**
	 * Table discussions.
	 *
	 * @var string Table discussions.
	 */
	private string $table_discussions;
	/**
	 * Table discussion votes.
	 *
	 * @var string Table discussion votes.
	 */
	private string $table_discussion_votes;
	/**
	 * Table certificates.
	 *
	 * @var string Table certificates.
	 */
	private string $table_certificates;
	/**
	 * Table notes.
	 *
	 * @var string Table notes.
	 */
	private string $table_notes;
	/**
	 * Table bookmarks.
	 *
	 * @var string Table bookmarks.
	 */
	private string $table_bookmarks;
	/**
	 * Table learning goals.
	 *
	 * @var string Table learning goals.
	 */
	private string $table_learning_goals;
	/**
	 * Table course announcements.
	 *
	 * @var string Table course announcements.
	 */
	private string $table_course_announcements;
	/**
	 * Table instructor payouts.
	 *
	 * @var string Table instructor payouts.
	 */
	private string $table_instructor_payouts;
	/**
	 * Table notifications.
	 *
	 * @var string Table notifications.
	 */
	private string $table_notifications;
	/**
	 * Table wishlist.
	 *
	 * @var string Table wishlist.
	 */
	private string $table_wishlist;
	/**
	 * Table learning streaks.
	 *
	 * @var string Table learning streaks.
	 */
	private string $table_learning_streaks;

	/**
	 * Sanitize rich-text (Quill) content with the narrow ecosystem
	 * allow-list; falls back to wp_kses_post() when zeko-core is absent.
	 *
	 * @param mixed $html Html.
	 */
	private static function sanitize_rich( $html ): string {
		if ( class_exists( 'Zeko_Core_Sanitize' ) ) {
			return Zeko_Core_Sanitize::rich_text( (string) $html );
		}
		return wp_kses_post( (string) $html );
	}

	/**
	 * Construct.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;

		$p                                  = $wpdb->prefix . 'zeko_';
		$this->table_courses                = $p . 'courses';
		$this->table_course_sections        = $p . 'course_sections';
		$this->table_lessons                = $p . 'lessons';
		$this->table_enrollments            = $p . 'enrollments';
		$this->table_lesson_progress        = $p . 'lesson_progress';
		$this->table_course_progress        = $p . 'course_progress';
		$this->table_quizzes                = $p . 'quizzes';
		$this->table_quiz_questions         = $p . 'quiz_questions';
		$this->table_quiz_attempts          = $p . 'quiz_attempts';
		$this->table_assignments            = $p . 'assignments';
		$this->table_assignment_submissions = $p . 'assignment_submissions';
		$this->table_reviews                = $p . 'reviews';
		$this->table_categories             = $p . 'categories';
		$this->table_skills                 = $p . 'skills';
		$this->table_course_skills          = $p . 'course_skills';
		$this->table_discussions            = $p . 'discussions';
		$this->table_discussion_votes       = $p . 'discussion_votes';
		$this->table_certificates           = $p . 'certificates';
		$this->table_notes                  = $p . 'notes';
		$this->table_bookmarks              = $p . 'bookmarks';
		$this->table_learning_goals         = $p . 'learning_goals';
		$this->table_course_announcements   = $p . 'course_announcements';
		$this->table_instructor_payouts     = $p . 'instructor_payouts';
		$this->table_notifications          = $p . 'learn_notifications';
		$this->table_wishlist               = $p . 'wishlist';
		$this->table_learning_streaks       = $p . 'learning_streaks';
	}

	/**
	 * Create or upgrade all tables via dbDelta.
	 */
	public function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $this->get_charset_collate();
		$sql     = array();

		// ─── Core Content Tables ───────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_courses} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			instructor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			subtitle varchar(255) NOT NULL DEFAULT '',
			slug varchar(255) NOT NULL DEFAULT '',
			description longtext NOT NULL,
			what_you_learn longtext DEFAULT NULL,
			requirements longtext DEFAULT NULL,
			target_audience longtext DEFAULT NULL,
			thumbnail_id bigint(20) unsigned DEFAULT NULL,
			promo_video_url varchar(500) NOT NULL DEFAULT '',
			level varchar(20) NOT NULL DEFAULT 'beginner',
			language varchar(50) NOT NULL DEFAULT 'en',
			estimated_hours decimal(5,1) NOT NULL DEFAULT 0.0,
			category_id bigint(20) unsigned DEFAULT NULL,
			price decimal(12,2) NOT NULL DEFAULT 0.00,
			sale_price decimal(12,2) DEFAULT NULL,
			is_free tinyint(1) NOT NULL DEFAULT 1,
			enrollment_count bigint(20) unsigned NOT NULL DEFAULT 0,
			avg_rating decimal(3,2) NOT NULL DEFAULT 0.00,
			review_count bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'draft',
			is_featured tinyint(1) NOT NULL DEFAULT 0,
			seo_title varchar(255) NOT NULL DEFAULT '',
			seo_description varchar(500) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY instructor_id (instructor_id),
			KEY category_id (category_id),
			KEY status (status),
			KEY is_featured (is_featured),
			KEY level (level),
			KEY is_free (is_free),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$this->table_course_sections} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			description text DEFAULT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY course_id (course_id),
			KEY sort_order (sort_order)
		) {$charset};";

		$sql[] = "CREATE TABLE {$this->table_lessons} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			section_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			slug varchar(255) NOT NULL DEFAULT '',
			lesson_type varchar(20) NOT NULL DEFAULT 'text',
			content longtext DEFAULT NULL,
			video_url varchar(500) NOT NULL DEFAULT '',
			video_duration int(11) NOT NULL DEFAULT 0,
			attachment_url varchar(500) NOT NULL DEFAULT '',
			sort_order int(11) NOT NULL DEFAULT 0,
			is_preview tinyint(1) NOT NULL DEFAULT 0,
			estimated_minutes int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY section_id (section_id),
			KEY course_id (course_id),
			KEY sort_order (sort_order),
			KEY lesson_type (lesson_type)
		) {$charset};";

		// ─── Enrollment & Progress Tables ──────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_enrollments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			enrolled_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			completed_at datetime DEFAULT NULL,
			last_accessed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY course_id (course_id),
			KEY status (status),
			KEY enrolled_at (enrolled_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$this->table_lesson_progress} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'not_started',
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_lesson (user_id, lesson_id),
			KEY course_id (course_id),
			KEY status (status)
		) {$charset};";

		$sql[] = "CREATE TABLE {$this->table_course_progress} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			completion_pct decimal(5,2) NOT NULL DEFAULT 0.00,
			total_lessons int(11) NOT NULL DEFAULT 0,
			completed_lessons int(11) NOT NULL DEFAULT 0,
			last_activity_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY course_id (course_id)
		) {$charset};";

		// ─── Quiz Tables ───────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_quizzes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			description text DEFAULT NULL,
			time_limit_minutes int(11) NOT NULL DEFAULT 0,
			passing_score decimal(5,2) NOT NULL DEFAULT 60.00,
			max_attempts int(11) NOT NULL DEFAULT 0,
			shuffle_questions tinyint(1) NOT NULL DEFAULT 0,
			show_answers varchar(20) NOT NULL DEFAULT 'on_complete',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY lesson_id (lesson_id),
			KEY course_id (course_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$this->table_quiz_questions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			quiz_id bigint(20) unsigned NOT NULL DEFAULT 0,
			question_type varchar(20) NOT NULL DEFAULT 'single_choice',
			question_text longtext NOT NULL,
			options longtext DEFAULT NULL,
			correct_answer text DEFAULT NULL,
			explanation longtext DEFAULT NULL,
			points int(11) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY quiz_id (quiz_id),
			KEY sort_order (sort_order)
		) {$charset};";

		$sql[] = "CREATE TABLE {$this->table_quiz_attempts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			quiz_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			score decimal(5,2) NOT NULL DEFAULT 0.00,
			total_points int(11) NOT NULL DEFAULT 0,
			earned_points int(11) NOT NULL DEFAULT 0,
			answers longtext DEFAULT NULL,
			is_passed tinyint(1) NOT NULL DEFAULT 0,
			started_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			completed_at datetime DEFAULT NULL,
			time_taken_seconds int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY quiz_id (quiz_id),
			KEY user_id (user_id),
			KEY is_passed (is_passed),
			KEY completed_at (completed_at)
		) {$charset};";

		// ─── Assignment Tables ─────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_assignments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			instructions longtext DEFAULT NULL,
			rubric longtext DEFAULT NULL,
			max_file_size_mb int(11) NOT NULL DEFAULT 10,
			allowed_file_types varchar(255) NOT NULL DEFAULT 'pdf,docx,zip',
			due_date datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY lesson_id (lesson_id),
			KEY course_id (course_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$this->table_assignment_submissions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			assignment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			file_url varchar(500) NOT NULL DEFAULT '',
			notes longtext DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'submitted',
			grade decimal(5,2) DEFAULT NULL,
			feedback longtext DEFAULT NULL,
			graded_by bigint(20) unsigned DEFAULT NULL,
			submitted_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			graded_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY assignment_id (assignment_id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY status (status)
		) {$charset};";

		// ─── Reviews ───────────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_reviews} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			rating tinyint(3) unsigned NOT NULL DEFAULT 5,
			review_text longtext DEFAULT NULL,
			instructor_reply longtext DEFAULT NULL,
			is_approved tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY course_id (course_id),
			KEY rating (rating),
			KEY is_approved (is_approved),
			KEY created_at (created_at)
		) {$charset};";

		// ─── Taxonomy Tables ───────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_categories} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL DEFAULT '',
			slug varchar(100) NOT NULL DEFAULT '',
			description text DEFAULT NULL,
			icon varchar(50) NOT NULL DEFAULT '',
			parent_id bigint(20) unsigned DEFAULT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			course_count bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY parent_id (parent_id),
			KEY sort_order (sort_order)
		) {$charset};";

		$sql[] = "CREATE TABLE {$this->table_skills} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL DEFAULT '',
			slug varchar(100) NOT NULL DEFAULT '',
			course_count bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) {$charset};";

		$sql[] = "CREATE TABLE {$this->table_course_skills} (
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			skill_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (course_id, skill_id),
			KEY skill_id (skill_id)
		) {$charset};";

		// ─── Discussion Tables ─────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_discussions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lesson_id bigint(20) unsigned DEFAULT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned DEFAULT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			content longtext NOT NULL,
			upvotes bigint(20) unsigned NOT NULL DEFAULT 0,
			is_instructor_reply tinyint(1) NOT NULL DEFAULT 0,
			is_best_answer tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY course_id (course_id),
			KEY lesson_id (lesson_id),
			KEY user_id (user_id),
			KEY parent_id (parent_id),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$this->table_discussion_votes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			discussion_id bigint(20) unsigned NOT NULL DEFAULT 0,
			vote_type varchar(10) NOT NULL DEFAULT 'up',
			PRIMARY KEY (id),
			UNIQUE KEY user_discussion (user_id, discussion_id),
			KEY discussion_id (discussion_id)
		) {$charset};";

		// ─── Certificates ──────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_certificates} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			enrollment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			certificate_number varchar(36) NOT NULL DEFAULT '',
			issued_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			pdf_url varchar(500) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			UNIQUE KEY certificate_number (certificate_number),
			KEY user_id (user_id),
			KEY course_id (course_id)
		) {$charset};";

		// ─── Notes & Bookmarks ─────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_notes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			content longtext NOT NULL,
			timestamp_seconds int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY lesson_id (lesson_id),
			KEY course_id (course_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$this->table_bookmarks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			UNIQUE KEY user_lesson (user_id, lesson_id),
			KEY course_id (course_id)
		) {$charset};";

		// ─── Goals & Wishlist ──────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_learning_goals} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			goal_text varchar(500) NOT NULL DEFAULT '',
			target_date datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY status (status)
		) {$charset};";

		// ─── Announcements ─────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_course_announcements} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			instructor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			content longtext NOT NULL,
			is_pinned tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY course_id (course_id),
			KEY instructor_id (instructor_id),
			KEY is_pinned (is_pinned),
			KEY created_at (created_at)
		) {$charset};";

		// ─── Payouts ───────────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_instructor_payouts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			instructor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			amount decimal(12,2) NOT NULL DEFAULT 0.00,
			period_start datetime NOT NULL,
			period_end datetime NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			paid_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY instructor_id (instructor_id),
			KEY course_id (course_id),
			KEY status (status)
		) {$charset};";

		// ─── Notifications ─────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_notifications} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			module varchar(30) NOT NULL DEFAULT 'learn',
			action varchar(50) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(50) NOT NULL DEFAULT '',
			actor_id bigint(20) unsigned DEFAULT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY user_unread (user_id, is_read),
			KEY is_read (is_read),
			KEY module (module),
			KEY created_at (created_at)
		) {$charset};";

		// ─── Wishlist ──────────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_wishlist} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY course_id (course_id)
		) {$charset};";

		// ─── Learning Streaks ──────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_learning_streaks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			current_streak int(11) NOT NULL DEFAULT 0,
			longest_streak int(11) NOT NULL DEFAULT 0,
			last_active_date date DEFAULT NULL,
			total_days_active int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id)
		) {$charset};";

		// Execute all queries.
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Add missing hot-path indexes to existing tables.
	 */
	public function add_missing_indexes(): void {
		global $wpdb;

		$indexes = array(
			$this->table_notifications => array( 'user_unread', '`user_id`, `is_read`' ),
		);

		foreach ( $indexes as $table => $key ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s',
					$table,
					$key[0]
				)
			);

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			if ( ! $exists ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD KEY `{$key[0]}` ({$key[1]})" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
	}

	// ─── Table Getters ───────────────────────────────────────────.

	/**
	 * Table courses.
	 */
	public function get_table_courses(): string {
		return $this->table_courses; }
	/**
	 * Table course sections.
	 */
	public function get_table_course_sections(): string {
		return $this->table_course_sections; }
	/**
	 * Table lessons.
	 */
	public function get_table_lessons(): string {
		return $this->table_lessons; }
	/**
	 * Table enrollments.
	 */
	public function get_table_enrollments(): string {
		return $this->table_enrollments; }
	/**
	 * Table lesson progress.
	 */
	public function get_table_lesson_progress(): string {
		return $this->table_lesson_progress; }
	/**
	 * Table course progress.
	 */
	public function get_table_course_progress(): string {
		return $this->table_course_progress; }
	/**
	 * Table quizzes.
	 */
	public function get_table_quizzes(): string {
		return $this->table_quizzes; }
	/**
	 * Table quiz questions.
	 */
	public function get_table_quiz_questions(): string {
		return $this->table_quiz_questions; }
	/**
	 * Table quiz attempts.
	 */
	public function get_table_quiz_attempts(): string {
		return $this->table_quiz_attempts; }
	/**
	 * Table assignments.
	 */
	public function get_table_assignments(): string {
		return $this->table_assignments; }
	/**
	 * Table assignment submissions.
	 */
	public function get_table_assignment_submissions(): string {
		return $this->table_assignment_submissions; }
	/**
	 * Table reviews.
	 */
	public function get_table_reviews(): string {
		return $this->table_reviews; }
	/**
	 * Table categories.
	 */
	public function get_table_categories(): string {
		return $this->table_categories; }
	/**
	 * Table skills.
	 */
	public function get_table_skills(): string {
		return $this->table_skills; }
	/**
	 * Table course skills.
	 */
	public function get_table_course_skills(): string {
		return $this->table_course_skills; }
	/**
	 * Table discussions.
	 */
	public function get_table_discussions(): string {
		return $this->table_discussions; }
	/**
	 * Table discussion votes.
	 */
	public function get_table_discussion_votes(): string {
		return $this->table_discussion_votes; }
	/**
	 * Table certificates.
	 */
	public function get_table_certificates(): string {
		return $this->table_certificates; }
	/**
	 * Table notes.
	 */
	public function get_table_notes(): string {
		return $this->table_notes; }
	/**
	 * Table bookmarks.
	 */
	public function get_table_bookmarks(): string {
		return $this->table_bookmarks; }
	/**
	 * Table learning goals.
	 */
	public function get_table_learning_goals(): string {
		return $this->table_learning_goals; }
	/**
	 * Table course announcements.
	 */
	public function get_table_course_announcements(): string {
		return $this->table_course_announcements; }
	/**
	 * Table instructor payouts.
	 */
	public function get_table_instructor_payouts(): string {
		return $this->table_instructor_payouts; }
	/**
	 * Table notifications.
	 */
	public function get_table_notifications(): string {
		return $this->table_notifications; }
	/**
	 * Table wishlist.
	 */
	public function get_table_wishlist(): string {
		return $this->table_wishlist; }
	/**
	 * Table learning streaks.
	 */
	public function get_table_learning_streaks(): string {
		return $this->table_learning_streaks; }

	// ─── Object Cache Helpers ─────────────────────────────────────.

	/**
	 * Cache get.
	 *
	 * @param string $key Key.
	 */
	private function cache_get( string $key ) {
		return wp_cache_get( $key, 'zeko_learn' );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Cache set.
	 *
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl Ttl.
	 */
	private function cache_set( string $key, $value, int $ttl = 300 ): void {
		wp_cache_set( $key, $value, 'zeko_learn', $ttl );
	}

	/**
	 * Flush cache.
	 */
	public function flush_cache(): void {
		wp_cache_flush_group( 'zeko_learn' );
	}

	// ─── Course Queries ──────────────────────────────────────────.

	/**
	 * Get a course by ID.
	 *
	 * @param int $course_id Course id.
	 */
	public function get_course( int $course_id ): ?array {
		$cache_key = 'course_' . $course_id;
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached ?: null;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_courses} WHERE id = %d LIMIT 1", $course_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->cache_set( $cache_key, $row ?: array() );
		return $row ?: null;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get a course by slug.
	 *
	 * @param string $slug Slug.
	 */
	public function get_course_by_slug( string $slug ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_courses} WHERE slug = %s LIMIT 1", $slug ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	/**
	 * Get courses with filters.
	 *
	 * @param array $args Args.
	 */
	public function get_courses( array $args = array() ): array {
		$defaults = array(
			'status'        => 'published',
			'category_id'   => 0,
			'level'         => '',
			'instructor_id' => 0,
			'is_free'       => null,
			'is_featured'   => null,
			'search'        => '',
			'orderby'       => 'created_at',
			'order'         => 'DESC',
			'limit'         => 20,
			'offset'        => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( $args['category_id'] ) {
			$where[]  = 'category_id = %d';
			$values[] = $args['category_id'];
		}
		if ( $args['level'] ) {
			$where[]  = 'level = %s';
			$values[] = $args['level'];
		}
		if ( $args['instructor_id'] ) {
			$where[]  = 'instructor_id = %d';
			$values[] = $args['instructor_id'];
		}
		if ( null !== $args['is_free'] ) {
			$where[]  = 'is_free = %d';
			$values[] = (int) $args['is_free'];
		}
		if ( null !== $args['is_featured'] ) {
			$where[]  = 'is_featured = %d';
			$values[] = (int) $args['is_featured'];
		}
		if ( $args['search'] ) {
			$where[]  = '(title LIKE %s OR subtitle LIKE %s)';
			$values[] = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$values[] = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
		}

		$allowed_orderby = array( 'created_at', 'enrollment_count', 'avg_rating', 'title', 'price' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit           = absint( $args['limit'] );
		$offset          = absint( $args['offset'] );

		$where_sql = implode( ' AND ', $where );
		$prepare   = array_merge( $values, array( $limit, $offset ) );

		$query = "SELECT * FROM {$this->table_courses} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( ! empty( $values ) ) {
			return $this->wpdb->get_results( $this->wpdb->prepare( $query, ...$prepare ), ARRAY_A ) ?: array();
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		return $this->wpdb->get_results( $this->wpdb->prepare( $query, $limit, $offset ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Insert a course.
	 *
	 * @param array $data Data.
	 */
	public function insert_course( array $data ): int {
		$this->wpdb->insert(
			$this->table_courses,
			array(
				'instructor_id'   => absint( $data['instructor_id'] ),
				'title'           => sanitize_text_field( $data['title'] ),
				'subtitle'        => sanitize_text_field( $data['subtitle'] ?? '' ),
				'slug'            => sanitize_title( $data['slug'] ?? $data['title'] ),
				'description'     => self::sanitize_rich( $data['description'] ?? '' ),
				'what_you_learn'  => self::sanitize_rich( $data['what_you_learn'] ?? '' ),
				'requirements'    => self::sanitize_rich( $data['requirements'] ?? '' ),
				'target_audience' => self::sanitize_rich( $data['target_audience'] ?? '' ),
				'thumbnail_id'    => absint( $data['thumbnail_id'] ?? 0 ) ?: null,
				'promo_video_url' => esc_url_raw( $data['promo_video_url'] ?? '' ),
				'level'           => sanitize_text_field( $data['level'] ?? 'beginner' ),
				'language'        => sanitize_text_field( $data['language'] ?? 'en' ),
				'estimated_hours' => (float) ( $data['estimated_hours'] ?? 0 ),
				'category_id'     => absint( $data['category_id'] ?? 0 ) ?: null,
				'price'           => (float) ( $data['price'] ?? 0 ),
				'sale_price'      => ! empty( $data['sale_price'] ) ? (float) $data['sale_price'] : null,
				'is_free'         => ( $data['price'] ?? 0 ) <= 0 ? 1 : 0,
				'status'          => sanitize_text_field( $data['status'] ?? 'draft' ),
				'is_featured'     => ! empty( $data['is_featured'] ) ? 1 : 0,
				'seo_title'       => sanitize_text_field( $data['seo_title'] ?? '' ),
				'seo_description' => sanitize_text_field( $data['seo_description'] ?? '' ),
				'created_at'      => current_time( 'mysql', true ),
				'updated_at'      => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%f', '%d', '%f', '%f', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		$this->flush_cache();
		$course_id = (int) $this->wpdb->insert_id;
		do_action( 'zeko_learn_course_created', $course_id, (int) $data['instructor_id'], $data );
		return $course_id;
	}

	// ─── Section Queries ─────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get sections for a course.
	 *
	 * @param int $course_id Course id.
	 */
	public function get_course_sections( int $course_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_course_sections} WHERE course_id = %d ORDER BY sort_order ASC",
				$course_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Insert a course section.
	 *
	 * @param array $data Data.
	 */
	public function insert_section( array $data ): int {
		$this->wpdb->insert(
			$this->table_course_sections,
			array(
				'course_id'   => absint( $data['course_id'] ),
				'title'       => sanitize_text_field( $data['title'] ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'sort_order'  => absint( $data['sort_order'] ?? 0 ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	// ─── Lesson Queries ──────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get lessons for a section.
	 *
	 * @param int $section_id Section id.
	 */
	public function get_section_lessons( int $section_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_lessons} WHERE section_id = %d ORDER BY sort_order ASC",
				$section_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get all lessons for a course.
	 *
	 * @param int $course_id Course id.
	 */
	public function get_course_lessons( int $course_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT l.*, cs.title AS section_title
				FROM {$this->table_lessons} l
				LEFT JOIN {$this->table_course_sections} cs ON l.section_id = cs.id
				WHERE l.course_id = %d
				ORDER BY cs.sort_order ASC, l.sort_order ASC",
				$course_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get a lesson by ID.
	 *
	 * @param int $lesson_id Lesson id.
	 */
	public function get_lesson( int $lesson_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_lessons} WHERE id = %d LIMIT 1", $lesson_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	/**
	 * Insert a lesson.
	 *
	 * @param array $data Data.
	 */
	public function insert_lesson( array $data ): int {
		$this->wpdb->insert(
			$this->table_lessons,
			array(
				'section_id'        => absint( $data['section_id'] ),
				'course_id'         => absint( $data['course_id'] ),
				'title'             => sanitize_text_field( $data['title'] ),
				'slug'              => sanitize_title( $data['slug'] ?? $data['title'] ),
				'lesson_type'       => sanitize_text_field( $data['lesson_type'] ?? 'text' ),
				'content'           => self::sanitize_rich( $data['content'] ?? '' ),
				'video_url'         => esc_url_raw( $data['video_url'] ?? '' ),
				'video_duration'    => absint( $data['video_duration'] ?? 0 ),
				'attachment_url'    => esc_url_raw( $data['attachment_url'] ?? '' ),
				'sort_order'        => absint( $data['sort_order'] ?? 0 ),
				'is_preview'        => ! empty( $data['is_preview'] ) ? 1 : 0,
				'estimated_minutes' => absint( $data['estimated_minutes'] ?? 0 ),
				'created_at'        => current_time( 'mysql', true ),
				'updated_at'        => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	// ─── Enrollment Queries ──────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Check if a user is enrolled in a course.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function is_enrolled( int $user_id, int $course_id ): bool {
		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_enrollments} WHERE user_id = %d AND course_id = %d AND status IN ('active','completed')",
				$user_id,
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $count > 0;
	}

	/**
	 * Enroll a user in a course.
	 *
	 * @param int    $user_id User id.
	 * @param int    $course_id Course id.
	 * @param string $status Status.
	 */
	public function enroll_user( int $user_id, int $course_id, string $status = 'active' ): int {
		$this->wpdb->insert(
			$this->table_enrollments,
			array(
				'user_id'     => $user_id,
				'course_id'   => $course_id,
				'status'      => $status,
				'enrolled_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// Increment enrollment count.
		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table_courses} SET enrollment_count = enrollment_count + 1 WHERE id = %d",
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get user's enrolled courses.
	 *
	 * @param int   $user_id User id.
	 * @param array $args Args.
	 */
	public function get_user_enrolled_courses( int $user_id, array $args = array() ): array {
		$defaults = array(
			'status' => '',
			'limit'  => 20,
			'offset' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = 'e.user_id = %d';
		$values = array( $user_id );

		if ( $args['status'] ) {
			$where   .= ' AND e.status = %s';
			$values[] = $args['status'];
		}

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				"SELECT c.*, e.status AS enrollment_status, e.enrolled_at, e.completed_at,
					cp.completion_pct, cp.completed_lessons, cp.total_lessons
				FROM {$this->table_enrollments} e
				INNER JOIN {$this->table_courses} c ON e.course_id = c.id
				LEFT JOIN {$this->table_course_progress} cp ON cp.user_id = e.user_id AND cp.course_id = e.course_id
				WHERE {$where}
				ORDER BY e.last_accessed_at DESC
				LIMIT %d OFFSET %d",
				...$values
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// ─── Progress Queries ────────────────────────────────────────.

	/**
	 * Mark a lesson as complete for a user.
	 *
	 * @param int $user_id User id.
	 * @param int $lesson_id Lesson id.
	 * @param int $course_id Course id.
	 */
	public function complete_lesson( int $user_id, int $lesson_id, int $course_id ): void {
		$this->wpdb->replace(
			$this->table_lesson_progress,
			array(
				'user_id'      => $user_id,
				'lesson_id'    => $lesson_id,
				'course_id'    => $course_id,
				'status'       => 'completed',
				'completed_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		$this->recalculate_course_progress( $user_id, $course_id );
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Recalculate course completion percentage.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function recalculate_course_progress( int $user_id, int $course_id ): array {
		$total = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_lessons} WHERE course_id = %d",
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$completed = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_lesson_progress} WHERE user_id = %d AND course_id = %d AND status = 'completed'",
				$user_id,
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$pct = $total > 0 ? round( ( $completed / $total ) * 100, 2 ) : 0;

		$this->wpdb->replace(
			$this->table_course_progress,
			array(
				'user_id'           => $user_id,
				'course_id'         => $course_id,
				'completion_pct'    => $pct,
				'total_lessons'     => $total,
				'completed_lessons' => $completed,
				'last_activity_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%f', '%d', '%d', '%s' )
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// Auto-complete enrollment if 100%.
		if ( $pct >= 100 ) {
			$this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$this->table_enrollments} SET status = 'completed', completed_at = %s WHERE user_id = %d AND course_id = %d AND status = 'active'",
					current_time( 'mysql', true ),
					$user_id,
					$course_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		return array(
			'completion_pct'    => $pct,
			'total_lessons'     => $total,
			'completed_lessons' => $completed,
		);
	}

	// ─── Review Queries ──────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get reviews for a course.
	 *
	 * @param int $course_id Course id.
	 * @param int $limit Limit.
	 * @param int $offset Offset.
	 */
	public function get_course_reviews( int $course_id, int $limit = 20, int $offset = 0 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT r.*, u.display_name AS author_name
				FROM {$this->table_reviews} r
				LEFT JOIN {$this->wpdb->users} u ON r.user_id = u.ID
				WHERE r.course_id = %d AND r.is_approved = 1
				ORDER BY r.created_at DESC
				LIMIT %d OFFSET %d",
				$course_id,
				$limit,
				$offset
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// ─── Category Queries ────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get all categories.
	 */
	public function get_categories(): array {
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->table_categories} ORDER BY sort_order ASC, name ASC",
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Insert a category.
	 *
	 * @param array $data Data.
	 */
	public function insert_category( array $data ): int {
		$this->wpdb->insert(
			$this->table_categories,
			array(
				'name'        => sanitize_text_field( $data['name'] ),
				'slug'        => sanitize_title( $data['slug'] ?? $data['name'] ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'icon'        => sanitize_text_field( $data['icon'] ?? '' ),
				'parent_id'   => absint( $data['parent_id'] ?? 0 ) ?: null,
				'sort_order'  => absint( $data['sort_order'] ?? 0 ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	// ─── Discussion Queries ──────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get discussions for a course.
	 *
	 * @param int $course_id Course id.
	 * @param int $limit Limit.
	 * @param int $offset Offset.
	 */
	public function get_course_discussions( int $course_id, int $limit = 20, int $offset = 0 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT d.*, u.display_name AS author_name
				FROM {$this->table_discussions} d
				LEFT JOIN {$this->wpdb->users} u ON d.user_id = u.ID
				WHERE d.course_id = %d AND d.parent_id IS NULL
				ORDER BY d.created_at DESC
				LIMIT %d OFFSET %d",
				$course_id,
				$limit,
				$offset
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Insert a discussion post.
	 *
	 * @param array $data Data.
	 */
	public function insert_discussion( array $data ): int {
		$this->wpdb->insert(
			$this->table_discussions,
			array(
				'course_id'           => absint( $data['course_id'] ),
				'lesson_id'           => absint( $data['lesson_id'] ?? 0 ) ?: null,
				'user_id'             => absint( $data['user_id'] ),
				'parent_id'           => absint( $data['parent_id'] ?? 0 ) ?: null,
				'title'               => sanitize_text_field( $data['title'] ?? '' ),
				'content'             => self::sanitize_rich( $data['content'] ),
				'is_instructor_reply' => ! empty( $data['is_instructor_reply'] ) ? 1 : 0,
				'created_at'          => current_time( 'mysql', true ),
				'updated_at'          => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	// ─── Notification Queries ────────────────────────────────────.

	/**
	 * Insert a notification.
	 *
	 * @param array $data Data.
	 */
	public function insert_notification( array $data ): int {
		$this->wpdb->insert(
			$this->table_notifications,
			array(
				'user_id'     => absint( $data['user_id'] ),
				'module'      => sanitize_text_field( $data['module'] ?? 'learn' ),
				'action'      => sanitize_text_field( $data['action'] ),
				'object_id'   => absint( $data['object_id'] ?? 0 ),
				'object_type' => sanitize_text_field( $data['object_type'] ?? '' ),
				'actor_id'    => absint( $data['actor_id'] ?? 0 ) ?: null,
				'is_read'     => 0,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get user notifications, optionally filtered by module.
	 *
	 * @param int    $user_id User id.
	 * @param int    $limit Limit.
	 * @param string $module Module.
	 */
	public function get_user_notifications( int $user_id, int $limit = 15, string $module = '' ): array {
		$where = 'WHERE user_id = %d';
		$args  = array( $user_id );

		if ( '' !== $module ) {
			$where .= ' AND module = %s';
			$args[] = $module;
		}

		$args[] = $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_notifications} {$where} ORDER BY created_at DESC LIMIT %d",
				...$args
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Count unread notifications, optionally by module.
	 *
	 * @param int    $user_id User id.
	 * @param string $module Module.
	 */
	public function count_unread_notifications( int $user_id, string $module = '' ): int {
		$where = 'WHERE user_id = %d AND is_read = 0';
		$args  = array( $user_id );

		if ( '' !== $module ) {
			$where .= ' AND module = %s';
			$args[] = $module;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_notifications} {$where}", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				...$args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Mark a single notification as read.
	 *
	 * @param int $notification_id Notification id.
	 */
	public function mark_notification_read( int $notification_id ): bool {
		return (bool) $this->wpdb->update(
			$this->table_notifications,
			array( 'is_read' => 1 ),
			array( 'id' => $notification_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get unread notification count.
	 *
	 * @param int $user_id User id.
	 */
	public function get_unread_notification_count( int $user_id ): int {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_notifications} WHERE user_id = %d AND is_read = 0",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Mark all notifications as read.
	 *
	 * @param int $user_id User id.
	 */
	public function mark_notifications_read( int $user_id ): bool {
		return (bool) $this->wpdb->update(
			$this->table_notifications,
			array( 'is_read' => 1 ),
			array(
				'user_id' => $user_id,
				'is_read' => 0,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	// ─── Quiz Queries ────────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get a quiz by ID.
	 *
	 * @param int $quiz_id Quiz id.
	 */
	public function get_quiz( int $quiz_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_quizzes} WHERE id = %d LIMIT 1", $quiz_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	/**
	 * Get quiz questions.
	 *
	 * @param int  $quiz_id Quiz id.
	 * @param bool $shuffle Shuffle.
	 */
	public function get_quiz_questions( int $quiz_id, bool $shuffle = false ): array {
		$order = $shuffle ? 'RAND()' : 'sort_order ASC';
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_quiz_questions} WHERE quiz_id = %d ORDER BY {$order}",
				$quiz_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get quiz attempts for a user.
	 *
	 * @param int $quiz_id Quiz id.
	 * @param int $user_id User id.
	 * @param int $limit Limit.
	 */
	public function get_quiz_attempts( int $quiz_id, int $user_id, int $limit = 10 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_quiz_attempts} WHERE quiz_id = %d AND user_id = %d ORDER BY started_at DESC LIMIT %d",
				$quiz_id,
				$user_id,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// ─── Learning Streak Queries ─────────────────────────────────.

	/**
	 * Update learning streak for a user.
	 *
	 * @param int $user_id User id.
	 */
	public function update_learning_streak( int $user_id ): array {
		$today     = current_time( 'Y-m-d' );
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$streak = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_learning_streaks} WHERE user_id = %d LIMIT 1",
				$user_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $streak ) {
			$this->wpdb->insert(
				$this->table_learning_streaks,
				array(
					'user_id'           => $user_id,
					'current_streak'    => 1,
					'longest_streak'    => 1,
					'last_active_date'  => $today,
					'total_days_active' => 1,
					'created_at'        => current_time( 'mysql', true ),
					'updated_at'        => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
			);
			return array(
				'current_streak'    => 1,
				'longest_streak'    => 1,
				'total_days_active' => 1,
			);
		}

		if ( $streak['last_active_date'] === $today ) {
			return $streak;
		}

		if ( $streak['last_active_date'] === $yesterday ) {
			$new_current = (int) $streak['current_streak'] + 1;
		} else {
			$new_current = 1;
		}

		$new_longest = max( $new_current, (int) $streak['longest_streak'] );
		$new_total   = (int) $streak['total_days_active'] + 1;

		$this->wpdb->update(
			$this->table_learning_streaks,
			array(
				'current_streak'    => $new_current,
				'longest_streak'    => $new_longest,
				'last_active_date'  => $today,
				'total_days_active' => $new_total,
				'updated_at'        => current_time( 'mysql', true ),
			),
			array( 'user_id' => $user_id ),
			array( '%d', '%d', '%s', '%d', '%s' ),
			array( '%d' )
		);

		return array(
			'current_streak'    => $new_current,
			'longest_streak'    => $new_longest,
			'total_days_active' => $new_total,
		);
	}

	// ─── Certificate Queries ─────────────────────────────────────.

	/**
	 * Issue a certificate.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 * @param int $enrollment_id Enrollment id.
	 */
	public function issue_certificate( int $user_id, int $course_id, int $enrollment_id ): int {
		$prefix = get_option( 'zeko_learn_certificate_prefix', 'ZL' );
		// Keep the number within varchar(36): uppercase prefix + 32-char hex.
		// UUID without dashes (e.g. "ZL-9f8e..."). A raw uuid4() is 36 chars.
		// and would overflow the column, silently failing the insert.
		$number = strtoupper( $prefix ) . '-' . str_replace( '-', '', wp_generate_uuid4() );

		$this->wpdb->insert(
			$this->table_certificates,
			array(
				'user_id'            => $user_id,
				'course_id'          => $course_id,
				'enrollment_id'      => $enrollment_id,
				'certificate_number' => $number,
				'issued_at'          => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get a certificate by number.
	 *
	 * @param string $number Number.
	 */
	public function get_certificate_by_number( string $number ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT c.*, co.title AS course_title, co.slug AS course_slug, u.display_name AS student_name
				FROM {$this->table_certificates} c
				INNER JOIN {$this->table_courses} co ON c.course_id = co.id
				INNER JOIN {$this->wpdb->users} u ON c.user_id = u.ID
				WHERE c.certificate_number = %s LIMIT 1",
				$number
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get user certificates.
	 *
	 * @param int $user_id User id.
	 * @param int $limit Limit.
	 */
	public function get_user_certificates( int $user_id, int $limit = 20 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT c.*, co.title AS course_title, co.slug AS course_slug
				FROM {$this->table_certificates} c
				INNER JOIN {$this->table_courses} co ON c.course_id = co.id
				WHERE c.user_id = %d
				ORDER BY c.issued_at DESC
				LIMIT %d",
				$user_id,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// ─── Course Update / Delete ───────────────────────────────────.

	/**
	 * Update course.
	 *
	 * @param int   $course_id Course id.
	 * @param array $data Data.
	 */
	public function update_course( int $course_id, array $data ): bool {
		$update = array();
		$format = array();

		$fields = array(
			'title'           => '%s',
			'subtitle'        => '%s',
			'slug'            => '%s',
			'description'     => '%s',
			'what_you_learn'  => '%s',
			'requirements'    => '%s',
			'target_audience' => '%s',
			'thumbnail_id'    => '%d',
			'promo_video_url' => '%s',
			'level'           => '%s',
			'language'        => '%s',
			'estimated_hours' => '%f',
			'category_id'     => '%d',
			'price'           => '%f',
			'sale_price'      => '%f',
			'is_free'         => '%d',
			'status'          => '%s',
			'is_featured'     => '%d',
			'seo_title'       => '%s',
			'seo_description' => '%s',
		);

		foreach ( $fields as $field => $fmt ) {
			if ( array_key_exists( $field, $data ) ) {
				$update[ $field ] = $data[ $field ];
				$format[]         = $fmt;
			}
		}

		// Keep is_free in sync with price.
		if ( array_key_exists( 'price', $update ) ) {
			$was_is_free_set   = array_key_exists( 'is_free', $update );
			$update['is_free'] = ( (float) $update['price'] ) <= 0 ? 1 : 0;
			if ( ! $was_is_free_set ) {
				$format[] = '%d';
			}
		}

		if ( empty( $update ) ) {
			return false;
		}

		$update['updated_at'] = current_time( 'mysql', true );
		$format[]             = '%s';

		$result = (bool) $this->wpdb->update( $this->table_courses, $update, array( 'id' => $course_id ), $format, array( '%d' ) );
		if ( $result ) {
			wp_cache_delete( 'course_' . $course_id, 'zeko_learn' );
			$this->flush_cache();
		}
		return $result;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Delete course.
	 *
	 * @param int $course_id Course id.
	 */
	public function delete_course( int $course_id ): bool {
		$quiz_ids = $this->wpdb->get_col(
			$this->wpdb->prepare( "SELECT id FROM {$this->table_quizzes} WHERE course_id = %d", $course_id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( ! empty( $quiz_ids ) ) {
			$quiz_placeholders = implode( ',', array_fill( 0, count( $quiz_ids ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->table_quiz_questions} WHERE quiz_id IN ({$quiz_placeholders})", ...$quiz_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
			$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->table_quiz_attempts} WHERE quiz_id IN ({$quiz_placeholders})", ...$quiz_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
		}

		$assignment_ids = $this->wpdb->get_col(
			$this->wpdb->prepare( "SELECT id FROM {$this->table_assignments} WHERE course_id = %d", $course_id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( ! empty( $assignment_ids ) ) {
			$assignment_placeholders = implode( ',', array_fill( 0, count( $assignment_ids ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->table_assignment_submissions} WHERE assignment_id IN ({$assignment_placeholders})", ...$assignment_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
		}

		$discussion_ids = $this->wpdb->get_col(
			$this->wpdb->prepare( "SELECT id FROM {$this->table_discussions} WHERE course_id = %d", $course_id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( ! empty( $discussion_ids ) ) {
			$disc_placeholders = implode( ',', array_fill( 0, count( $discussion_ids ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->table_discussion_votes} WHERE discussion_id IN ({$disc_placeholders})", ...$discussion_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
		}

		$this->wpdb->delete( $this->table_course_skills, array( 'course_id' => $course_id ), array( '%d' ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->wpdb->delete( $this->table_course_sections, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_lessons, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_enrollments, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_lesson_progress, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_course_progress, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_reviews, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_discussions, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_certificates, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_notes, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_bookmarks, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_wishlist, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_learning_goals, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_course_announcements, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_notifications, array( 'object_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_quizzes, array( 'course_id' => $course_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_assignments, array( 'course_id' => $course_id ), array( '%d' ) );

		$result = (bool) $this->wpdb->delete( $this->table_courses, array( 'id' => $course_id ), array( '%d' ) );
		if ( $result ) {
			wp_cache_delete( 'course_' . $course_id, 'zeko_learn' );
			$this->flush_cache();
		}
		return $result;
	}

	// ─── Section Update / Delete ─────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Section.
	 *
	 * @param int $section_id Section id.
	 */
	public function get_section( int $section_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_course_sections} WHERE id = %d LIMIT 1", $section_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	/**
	 * Update section.
	 *
	 * @param int   $section_id Section id.
	 * @param array $data Data.
	 */
	public function update_section( int $section_id, array $data ): bool {
		$update = array();
		$format = array();
		if ( isset( $data['title'] ) ) {
			$update['title'] = sanitize_text_field( $data['title'] );
			$format[]        = '%s'; }
		if ( isset( $data['description'] ) ) {
			$update['description'] = sanitize_textarea_field( $data['description'] );
			$format[]              = '%s'; }
		if ( isset( $data['sort_order'] ) ) {
			$update['sort_order'] = absint( $data['sort_order'] );
			$format[]             = '%d'; }
		if ( empty( $update ) ) {
			return false; }
		return (bool) $this->wpdb->update( $this->table_course_sections, $update, array( 'id' => $section_id ), $format, array( '%d' ) );
	}

	/**
	 * Delete section.
	 *
	 * @param int $section_id Section id.
	 */
	public function delete_section( int $section_id ): bool {
		$lessons = $this->get_section_lessons( $section_id );
		foreach ( $lessons as $lesson ) {
			$this->delete_lesson( (int) $lesson['id'] );
		}
		$section   = $this->get_section( $section_id );
		$course_id = $section ? (int) $section['course_id'] : 0;
		$deleted   = (bool) $this->wpdb->delete( $this->table_course_sections, array( 'id' => $section_id ), array( '%d' ) );
		if ( $deleted && $course_id ) {
			$this->recalculate_lesson_counts( $course_id );
		}
		return $deleted;
	}

	// ─── Lesson Update / Delete ──────────────────────────────────.

	/**
	 * Update lesson.
	 *
	 * @param int   $lesson_id Lesson id.
	 * @param array $data Data.
	 */
	public function update_lesson( int $lesson_id, array $data ): bool {
		$update = array();
		$format = array();
		$fields = array(
			'title'             => '%s',
			'slug'              => '%s',
			'lesson_type'       => '%s',
			'content'           => '%s',
			'video_url'         => '%s',
			'video_duration'    => '%d',
			'attachment_url'    => '%s',
			'sort_order'        => '%d',
			'is_preview'        => '%d',
			'estimated_minutes' => '%d',
		);
		foreach ( $fields as $field => $fmt ) {
			if ( array_key_exists( $field, $data ) ) {
				$update[ $field ] = $data[ $field ];
				$format[]         = $fmt;
			}
		}
		if ( empty( $update ) ) {
			return false; }
		$update['updated_at'] = current_time( 'mysql', true );
		$format[]             = '%s';
		return (bool) $this->wpdb->update( $this->table_lessons, $update, array( 'id' => $lesson_id ), $format, array( '%d' ) );
	}

	/**
	 * Delete lesson.
	 *
	 * @param int $lesson_id Lesson id.
	 */
	public function delete_lesson( int $lesson_id ): bool {
		$this->wpdb->delete( $this->table_lesson_progress, array( 'lesson_id' => $lesson_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_quiz_questions, array( 'lesson_id' => $lesson_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_quizzes, array( 'lesson_id' => $lesson_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_assignments, array( 'lesson_id' => $lesson_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_notes, array( 'lesson_id' => $lesson_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_bookmarks, array( 'lesson_id' => $lesson_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_discussions, array( 'lesson_id' => $lesson_id ), array( '%d' ) );

		$lesson    = $this->get_lesson( $lesson_id );
		$course_id = $lesson ? (int) $lesson['course_id'] : 0;
		$deleted   = (bool) $this->wpdb->delete( $this->table_lessons, array( 'id' => $lesson_id ), array( '%d' ) );
		if ( $deleted && $course_id ) {
			$this->recalculate_lesson_counts( $course_id );
		}
		return $deleted;
	}

	// ─── Lesson Count Recalculation ──────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Recalculate lesson counts.
	 *
	 * @param int $course_id Course id.
	 */
	public function recalculate_lesson_counts( int $course_id ): void {
		$total = (int) $this->wpdb->get_var(
			$this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_lessons} WHERE course_id = %d", $course_id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->wpdb->update(
			$this->table_course_progress,
			array( 'total_lessons' => $total ),
			array( 'course_id' => $course_id ),
			array( '%d' ),
			array( '%d' )
		);
		$this->wpdb->update(
			$this->table_courses,
			array( 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $course_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	// ─── Category CRUD ───────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Category.
	 *
	 * @param int $category_id Category id.
	 */
	public function get_category( int $category_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_categories} WHERE id = %d LIMIT 1", $category_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	/**
	 * Update category.
	 *
	 * @param int   $category_id Category id.
	 * @param array $data Data.
	 */
	public function update_category( int $category_id, array $data ): bool {
		$update = array();
		$format = array();
		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
			$format[]       = '%s'; }
		if ( isset( $data['slug'] ) ) {
			$update['slug'] = sanitize_title( $data['slug'] );
			$format[]       = '%s'; }
		if ( isset( $data['description'] ) ) {
			$update['description'] = sanitize_textarea_field( $data['description'] );
			$format[]              = '%s'; }
		if ( isset( $data['icon'] ) ) {
			$update['icon'] = sanitize_text_field( $data['icon'] );
			$format[]       = '%s'; }
		if ( isset( $data['parent_id'] ) ) {
			$update['parent_id'] = absint( $data['parent_id'] ) ?: null;
			$format[]            = '%d'; }
		if ( isset( $data['sort_order'] ) ) {
			$update['sort_order'] = absint( $data['sort_order'] );
			$format[]             = '%d'; }
		if ( empty( $update ) ) {
			return false; }
		return (bool) $this->wpdb->update( $this->table_categories, $update, array( 'id' => $category_id ), $format, array( '%d' ) );
	}

	/**
	 * Delete category.
	 *
	 * @param int $category_id Category id.
	 */
	public function delete_category( int $category_id ): bool {
		return (bool) $this->wpdb->delete( $this->table_categories, array( 'id' => $category_id ), array( '%d' ) );
	}

	// ─── Skill Queries ───────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Skills.
	 *
	 * @param int $limit Limit.
	 * @param int $offset Offset.
	 */
	public function get_skills( int $limit = 50, int $offset = 0 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_skills} ORDER BY name ASC LIMIT %d OFFSET %d", $limit, $offset ),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Skill.
	 *
	 * @param int $skill_id Skill id.
	 */
	public function get_skill( int $skill_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_skills} WHERE id = %d LIMIT 1", $skill_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Skill by slug.
	 *
	 * @param string $slug Slug.
	 */
	public function get_skill_by_slug( string $slug ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_skills} WHERE slug = %s LIMIT 1", $slug ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	/**
	 * Insert skill.
	 *
	 * @param array $data Data.
	 */
	public function insert_skill( array $data ): int {
		$this->wpdb->insert(
			$this->table_skills,
			array(
				'name'       => sanitize_text_field( $data['name'] ),
				'slug'       => sanitize_title( $data['slug'] ?? $data['name'] ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Delete skill.
	 *
	 * @param int $skill_id Skill id.
	 */
	public function delete_skill( int $skill_id ): bool {
		$this->wpdb->delete( $this->table_course_skills, array( 'skill_id' => $skill_id ), array( '%d' ) );
		return (bool) $this->wpdb->delete( $this->table_skills, array( 'id' => $skill_id ), array( '%d' ) );
	}

	/**
	 * Attach skill to course.
	 *
	 * @param int $course_id Course id.
	 * @param int $skill_id Skill id.
	 */
	public function attach_skill_to_course( int $course_id, int $skill_id ): void {
		$this->wpdb->replace(
			$this->table_course_skills,
			array(
				'course_id' => $course_id,
				'skill_id'  => $skill_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Detach skill from course.
	 *
	 * @param int $course_id Course id.
	 * @param int $skill_id Skill id.
	 */
	public function detach_skill_from_course( int $course_id, int $skill_id ): void {
		$this->wpdb->delete(
			$this->table_course_skills,
			array(
				'course_id' => $course_id,
				'skill_id'  => $skill_id,
			),
			array( '%d', '%d' )
		);
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Course skills.
	 *
	 * @param int $course_id Course id.
	 */
	public function get_course_skills( int $course_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT s.* FROM {$this->table_skills} s
				INNER JOIN {$this->table_course_skills} cs ON s.id = cs.skill_id
				WHERE cs.course_id = %d ORDER BY s.name ASC",
				$course_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Search skills.
	 *
	 * @param string $search Search.
	 * @param int    $limit Limit.
	 */
	public function search_skills( string $search, int $limit = 10 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_skills} WHERE name LIKE %s ORDER BY name ASC LIMIT %d",
				'%' . $this->wpdb->esc_like( $search ) . '%',
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get all enrollments for a course.
	 *
	 * @param int $course_id Course id.
	 */
	public function get_course_enrollments( int $course_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT e.*, u.display_name AS student_name, u.user_email AS student_email,
					cp.completion_pct AS progress_percent, cp.completed_lessons, cp.total_lessons
				FROM {$this->table_enrollments} e
				INNER JOIN {$this->wpdb->users} u ON e.user_id = u.ID
				LEFT JOIN {$this->table_course_progress} cp ON cp.user_id = e.user_id AND cp.course_id = e.course_id
				WHERE e.course_id = %d
				ORDER BY e.enrolled_at DESC",
				$course_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// ─── Enrollment Single + Cancel ──────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Enrollment.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function get_enrollment( int $user_id, int $course_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_enrollments} WHERE user_id = %d AND course_id = %d LIMIT 1",
				$user_id,
				$course_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	/**
	 * Cancel enrollment.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function cancel_enrollment( int $user_id, int $course_id ): bool {
		return $this->set_enrollment_status( $user_id, $course_id, 'cancelled' );
	}

	/**
	 * Mark an enrollment refunded (paid course money returned).
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function refund_enrollment( int $user_id, int $course_id ): bool {
		return $this->set_enrollment_status( $user_id, $course_id, 'refunded' );
	}

	/**
	 * Set an enrollment status, keeping the course enrollment_count in sync.
	 * The count is decremented only when moving out of a counted status
	 * ('active'/'completed'), and is clamped so it can never go negative on the
	 * unsigned column.
	 *
	 * @param int    $user_id User id.
	 * @param int    $course_id Course id.
	 * @param string $status Status.
	 */
	public function set_enrollment_status( int $user_id, int $course_id, string $status ): bool {
		$current = $this->get_enrollment( $user_id, $course_id );
		if ( ! $current ) {
			return false;
		}

		$counted = in_array( $current['status'], array( 'active', 'completed' ), true );
		$result  = $this->wpdb->update(
			$this->table_enrollments,
			array( 'status' => $status ),
			array(
				'user_id'   => $user_id,
				'course_id' => $course_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $counted && ! in_array( $status, array( 'active', 'completed' ), true ) ) {
			$this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$this->table_courses} SET enrollment_count = GREATEST(enrollment_count, 1) - 1 WHERE id = %d",
					$course_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		return true;
	}

	// ─── Review Insert / Update ──────────────────────────────────.

	/**
	 * Insert review.
	 *
	 * @param array $data Data.
	 */
	public function insert_review( array $data ): int {
		$this->wpdb->insert(
			$this->table_reviews,
			array(
				'course_id'   => absint( $data['course_id'] ),
				'user_id'     => absint( $data['user_id'] ),
				'rating'      => min( 5, max( 1, absint( $data['rating'] ) ) ),
				'review_text' => self::sanitize_rich( $data['review_text'] ?? '' ),
				'is_approved' => 1,
				'created_at'  => current_time( 'mysql', true ),
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
		);
		$this->update_course_rating( absint( $data['course_id'] ) );
		return (int) $this->wpdb->insert_id;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Update course rating.
	 *
	 * @param int $course_id Course id.
	 */
	public function update_course_rating( int $course_id ): void {
		$stats = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count
				FROM {$this->table_reviews} WHERE course_id = %d AND is_approved = 1",
				$course_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->wpdb->update(
			$this->table_courses,
			array(
				'avg_rating'   => round( (float) ( $stats['avg_rating'] ?? 0 ), 2 ),
				'review_count' => (int) ( $stats['review_count'] ?? 0 ),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $course_id ),
			array( '%f', '%d', '%s' ),
			array( '%d' )
		);
	}

	// ─── Discussion CRUD + Replies ───────────────────────────────.

	/**
	 * Update review.
	 *
	 * @param int   $review_id Review id.
	 * @param array $data Data.
	 */
	public function update_review( int $review_id, array $data ): bool {
		$update = array( 'updated_at' => current_time( 'mysql', true ) );
		$format = array( '%s' );
		if ( isset( $data['instructor_reply'] ) ) {
			$update['instructor_reply'] = self::sanitize_rich( $data['instructor_reply'] );
			$format[]                   = '%s';
		}
		if ( isset( $data['is_approved'] ) ) {
			$update['is_approved'] = absint( $data['is_approved'] );
			$format[]              = '%d';
		}
		return (bool) $this->wpdb->update( $this->table_reviews, $update, array( 'id' => $review_id ), $format, array( '%d' ) );
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Review.
	 *
	 * @param int $review_id Review id.
	 */
	public function get_review( int $review_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_reviews} WHERE id = %d LIMIT 1", $review_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Discussion.
	 *
	 * @param int $discussion_id Discussion id.
	 */
	public function get_discussion( int $discussion_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_discussions} WHERE id = %d LIMIT 1", $discussion_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Discussion replies.
	 *
	 * @param int $parent_id Parent id.
	 */
	public function get_discussion_replies( int $parent_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT d.*, u.display_name AS author_name
				FROM {$this->table_discussions} d
				LEFT JOIN {$this->wpdb->users} u ON d.user_id = u.ID
				WHERE d.parent_id = %d
				ORDER BY d.created_at ASC",
				$parent_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Update discussion.
	 *
	 * @param int   $discussion_id Discussion id.
	 * @param array $data Data.
	 */
	public function update_discussion( int $discussion_id, array $data ): bool {
		$update = array( 'updated_at' => current_time( 'mysql', true ) );
		$format = array( '%s' );
		if ( isset( $data['content'] ) ) {
			$update['content'] = self::sanitize_rich( $data['content'] );
			$format[]          = '%s'; }
		if ( isset( $data['title'] ) ) {
			$update['title'] = sanitize_text_field( $data['title'] );
			$format[]        = '%s'; }
		if ( isset( $data['is_best_answer'] ) ) {
			$update['is_best_answer'] = absint( $data['is_best_answer'] );
			$format[]                 = '%d'; }
		return (bool) $this->wpdb->update( $this->table_discussions, $update, array( 'id' => $discussion_id ), $format, array( '%d' ) );
	}

	/**
	 * Mark best answer.
	 *
	 * @param int $discussion_id Discussion id.
	 * @param int $course_id Course id.
	 */
	public function mark_best_answer( int $discussion_id, int $course_id ): bool {
		$this->wpdb->update(
			$this->table_discussions,
			array(
				'is_best_answer' => 0,
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'course_id' => $course_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		return (bool) $this->wpdb->update(
			$this->table_discussions,
			array(
				'is_best_answer' => 1,
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => $discussion_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete discussion.
	 *
	 * @param int $discussion_id Discussion id.
	 */
	public function delete_discussion( int $discussion_id ): bool {
		$this->wpdb->delete( $this->table_discussion_votes, array( 'discussion_id' => $discussion_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_discussions, array( 'parent_id' => $discussion_id ), array( '%d' ) );
		return (bool) $this->wpdb->delete( $this->table_discussions, array( 'id' => $discussion_id ), array( '%d' ) );
	}

	// ─── Discussion Vote ─────────────────────────────────────────.

	/**
	 * Vote discussion.
	 *
	 * @param int    $user_id User id.
	 * @param int    $discussion_id Discussion id.
	 * @param string $vote_type Vote type.
	 */
	public function vote_discussion( int $user_id, int $discussion_id, string $vote_type ): void {
		$this->wpdb->replace(
			$this->table_discussion_votes,
			array(
				'user_id'       => $user_id,
				'discussion_id' => $discussion_id,
				'vote_type'     => $vote_type,
			),
			array( '%d', '%d', '%s' )
		);
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$upvotes = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_discussion_votes} WHERE discussion_id = %d AND vote_type = 'up'",
				$discussion_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->wpdb->update(
			$this->table_discussions,
			array( 'upvotes' => $upvotes ),
			array( 'id' => $discussion_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	// ─── Quiz CRUD ───────────────────────────────────────────────.

	/**
	 * Insert quiz.
	 *
	 * @param array $data Data.
	 */
	public function insert_quiz( array $data ): int {
		$this->wpdb->insert(
			$this->table_quizzes,
			array(
				'lesson_id'          => absint( $data['lesson_id'] ),
				'course_id'          => absint( $data['course_id'] ),
				'title'              => sanitize_text_field( $data['title'] ),
				'description'        => sanitize_textarea_field( $data['description'] ?? '' ),
				'time_limit_minutes' => absint( $data['time_limit_minutes'] ?? 0 ),
				'passing_score'      => (float) ( $data['passing_score'] ?? 60 ),
				'max_attempts'       => absint( $data['max_attempts'] ?? 0 ),
				'shuffle_questions'  => ! empty( $data['shuffle_questions'] ) ? 1 : 0,
				'show_answers'       => sanitize_text_field( $data['show_answers'] ?? 'on_complete' ),
				'created_at'         => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%f', '%d', '%d', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update quiz.
	 *
	 * @param int   $quiz_id Quiz id.
	 * @param array $data Data.
	 */
	public function update_quiz( int $quiz_id, array $data ): bool {
		$update = array();
		$format = array();
		$fields = array(
			'title'              => '%s',
			'description'        => '%s',
			'time_limit_minutes' => '%d',
			'passing_score'      => '%f',
			'max_attempts'       => '%d',
			'shuffle_questions'  => '%d',
			'show_answers'       => '%s',
		);
		foreach ( $fields as $field => $fmt ) {
			if ( array_key_exists( $field, $data ) ) {
				$update[ $field ] = $data[ $field ];
				$format[]         = $fmt; }
		}
		if ( empty( $update ) ) {
			return false; }
		return (bool) $this->wpdb->update( $this->table_quizzes, $update, array( 'id' => $quiz_id ), $format, array( '%d' ) );
	}

	/**
	 * Delete quiz.
	 *
	 * @param int $quiz_id Quiz id.
	 */
	public function delete_quiz( int $quiz_id ): bool {
		$this->wpdb->delete( $this->table_quiz_questions, array( 'quiz_id' => $quiz_id ), array( '%d' ) );
		$this->wpdb->delete( $this->table_quiz_attempts, array( 'quiz_id' => $quiz_id ), array( '%d' ) );
		return (bool) $this->wpdb->delete( $this->table_quizzes, array( 'id' => $quiz_id ), array( '%d' ) );
	}

	/**
	 * Insert quiz question.
	 *
	 * @param array $data Data.
	 */
	public function insert_quiz_question( array $data ): int {
		$this->wpdb->insert(
			$this->table_quiz_questions,
			array(
				'quiz_id'        => absint( $data['quiz_id'] ),
				'question_type'  => sanitize_text_field( $data['question_type'] ?? 'single_choice' ),
				'question_text'  => self::sanitize_rich( $data['question_text'] ),
				'options'        => wp_json_encode( $data['options'] ?? array() ),
				'correct_answer' => sanitize_text_field( $data['correct_answer'] ?? '' ),
				'explanation'    => self::sanitize_rich( $data['explanation'] ?? '' ),
				'points'         => absint( $data['points'] ?? 1 ),
				'sort_order'     => absint( $data['sort_order'] ?? 0 ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update quiz question.
	 *
	 * @param int   $question_id Question id.
	 * @param array $data Data.
	 */
	public function update_quiz_question( int $question_id, array $data ): bool {
		$update = array();
		$format = array();
		$fields = array(
			'question_type'  => '%s',
			'question_text'  => '%s',
			'correct_answer' => '%s',
			'explanation'    => '%s',
			'points'         => '%d',
			'sort_order'     => '%d',
		);
		foreach ( $fields as $field => $fmt ) {
			if ( array_key_exists( $field, $data ) ) {
				$update[ $field ] = $data[ $field ];
				$format[]         = $fmt; }
		}
		if ( isset( $data['options'] ) ) {
			$update['options'] = wp_json_encode( $data['options'] );
			$format[]          = '%s'; }
		if ( empty( $update ) ) {
			return false; }
		return (bool) $this->wpdb->update( $this->table_quiz_questions, $update, array( 'id' => $question_id ), $format, array( '%d' ) );
	}

	/**
	 * Delete quiz question.
	 *
	 * @param int $question_id Question id.
	 */
	public function delete_quiz_question( int $question_id ): bool {
		return (bool) $this->wpdb->delete( $this->table_quiz_questions, array( 'id' => $question_id ), array( '%d' ) );
	}

	// ─── Quiz Attempt ────────────────────────────────────────────.

	/**
	 * Insert quiz attempt.
	 *
	 * @param array $data Data.
	 */
	public function insert_quiz_attempt( array $data ): int {
		$this->wpdb->insert(
			$this->table_quiz_attempts,
			array(
				'quiz_id'            => absint( $data['quiz_id'] ),
				'user_id'            => absint( $data['user_id'] ),
				'score'              => (float) ( $data['score'] ?? 0 ),
				'total_points'       => absint( $data['total_points'] ?? 0 ),
				'earned_points'      => absint( $data['earned_points'] ?? 0 ),
				'answers'            => wp_json_encode( $data['answers'] ?? array() ),
				'is_passed'          => ! empty( $data['is_passed'] ) ? 1 : 0,
				'started_at'         => $data['started_at'] ?? current_time( 'mysql', true ),
				'completed_at'       => $data['completed_at'] ?? current_time( 'mysql', true ),
				'time_taken_seconds' => absint( $data['time_taken_seconds'] ?? 0 ),
			),
			array( '%d', '%d', '%f', '%d', '%d', '%s', '%d', '%s', '%s', '%d' )
		);
		return (int) $this->wpdb->insert_id;
	}

	// ─── Assignment CRUD + Submissions ───────────────────────────.

	/**
	 * Insert assignment.
	 *
	 * @param array $data Data.
	 */
	public function insert_assignment( array $data ): int {
		$this->wpdb->insert(
			$this->table_assignments,
			array(
				'lesson_id'          => absint( $data['lesson_id'] ),
				'course_id'          => absint( $data['course_id'] ),
				'title'              => sanitize_text_field( $data['title'] ),
				'instructions'       => self::sanitize_rich( $data['instructions'] ?? '' ),
				'rubric'             => self::sanitize_rich( $data['rubric'] ?? '' ),
				'max_file_size_mb'   => absint( $data['max_file_size_mb'] ?? 10 ),
				'allowed_file_types' => sanitize_text_field( $data['allowed_file_types'] ?? 'pdf,docx,zip' ),
				'due_date'           => sanitize_text_field( $data['due_date'] ?? '' ),
				'created_at'         => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update assignment.
	 *
	 * @param int   $assignment_id Assignment id.
	 * @param array $data Data.
	 */
	public function update_assignment( int $assignment_id, array $data ): bool {
		$update = array();
		$format = array();
		$fields = array(
			'title'              => '%s',
			'instructions'       => '%s',
			'rubric'             => '%s',
			'max_file_size_mb'   => '%d',
			'allowed_file_types' => '%s',
			'due_date'           => '%s',
		);
		foreach ( $fields as $field => $fmt ) {
			if ( array_key_exists( $field, $data ) ) {
				$update[ $field ] = $data[ $field ];
				$format[]         = $fmt; }
		}
		if ( empty( $update ) ) {
			return false; }
		return (bool) $this->wpdb->update( $this->table_assignments, $update, array( 'id' => $assignment_id ), $format, array( '%d' ) );
	}

	/**
	 * Delete assignment.
	 *
	 * @param int $assignment_id Assignment id.
	 */
	public function delete_assignment( int $assignment_id ): bool {
		$this->wpdb->delete( $this->table_assignment_submissions, array( 'assignment_id' => $assignment_id ), array( '%d' ) );
		return (bool) $this->wpdb->delete( $this->table_assignments, array( 'id' => $assignment_id ), array( '%d' ) );
	}

	/**
	 * Insert assignment submission.
	 *
	 * @param array $data Data.
	 */
	public function insert_assignment_submission( array $data ): int {
		$this->wpdb->insert(
			$this->table_assignment_submissions,
			array(
				'assignment_id' => absint( $data['assignment_id'] ),
				'user_id'       => absint( $data['user_id'] ),
				'course_id'     => absint( $data['course_id'] ),
				'file_url'      => esc_url_raw( $data['file_url'] ?? '' ),
				'notes'         => self::sanitize_rich( $data['notes'] ?? '' ),
				'status'        => 'submitted',
				'submitted_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update assignment submission.
	 *
	 * @param int   $submission_id Submission id.
	 * @param array $data Data.
	 */
	public function update_assignment_submission( int $submission_id, array $data ): bool {
		$update = array();
		$format = array();
		if ( isset( $data['grade'] ) ) {
			$update['grade'] = (float) $data['grade'];
			$format[]        = '%f'; }
		if ( isset( $data['feedback'] ) ) {
			$update['feedback'] = self::sanitize_rich( $data['feedback'] );
			$format[]           = '%s'; }
		if ( isset( $data['status'] ) ) {
			$update['status'] = sanitize_text_field( $data['status'] );
			$format[]         = '%s'; }
		if ( isset( $data['graded_by'] ) ) {
			$update['graded_by'] = absint( $data['graded_by'] );
			$format[]            = '%d'; }
		if ( ! empty( $data['grade'] ) || ! empty( $data['feedback'] ) ) {
			$update['graded_at'] = current_time( 'mysql', true );
			$format[]            = '%s'; }
		if ( empty( $update ) ) {
			return false; }
		return (bool) $this->wpdb->update( $this->table_assignment_submissions, $update, array( 'id' => $submission_id ), $format, array( '%d' ) );
	}

	// ─── Notes CRUD ──────────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Notes.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 * @param int $limit Limit.
	 */
	public function get_notes( int $user_id, int $course_id, int $limit = 50 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT n.*, l.title AS lesson_title
				FROM {$this->table_notes} n
				LEFT JOIN {$this->table_lessons} l ON n.lesson_id = l.id
				WHERE n.user_id = %d AND n.course_id = %d
				ORDER BY n.created_at DESC LIMIT %d",
				$user_id,
				$course_id,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Insert note.
	 *
	 * @param array $data Data.
	 */
	public function insert_note( array $data ): int {
		$this->wpdb->insert(
			$this->table_notes,
			array(
				'user_id'           => absint( $data['user_id'] ),
				'lesson_id'         => absint( $data['lesson_id'] ),
				'course_id'         => absint( $data['course_id'] ),
				'content'           => self::sanitize_rich( $data['content'] ),
				'timestamp_seconds' => absint( $data['timestamp_seconds'] ?? 0 ),
				'created_at'        => current_time( 'mysql', true ),
				'updated_at'        => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update note.
	 *
	 * @param int   $note_id Note id.
	 * @param array $data Data.
	 */
	public function update_note( int $note_id, array $data ): bool {
		$update = array( 'updated_at' => current_time( 'mysql', true ) );
		$format = array( '%s' );
		if ( isset( $data['content'] ) ) {
			$update['content'] = self::sanitize_rich( $data['content'] );
			$format[]          = '%s'; }
		return (bool) $this->wpdb->update(
			$this->table_notes,
			$update,
			array(
				'id'      => $note_id,
				'user_id' => $data['user_id'] ?? 0,
			),
			$format,
			array( '%d', '%d' )
		);
	}

	/**
	 * Delete note.
	 *
	 * @param int $note_id Note id.
	 * @param int $user_id User id.
	 */
	public function delete_note( int $note_id, int $user_id ): bool {
		return (bool) $this->wpdb->delete(
			$this->table_notes,
			array(
				'id'      => $note_id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);
	}

	// ─── Bookmarks ───────────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Bookmarks.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function get_bookmarks( int $user_id, int $course_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT b.*, l.title AS lesson_title, l.slug AS lesson_slug
				FROM {$this->table_bookmarks} b
				LEFT JOIN {$this->table_lessons} l ON b.lesson_id = l.id
				WHERE b.user_id = %d AND b.course_id = %d
				ORDER BY b.created_at DESC",
				$user_id,
				$course_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Toggle bookmark.
	 *
	 * @param int $user_id User id.
	 * @param int $lesson_id Lesson id.
	 * @param int $course_id Course id.
	 */
	public function toggle_bookmark( int $user_id, int $lesson_id, int $course_id ): bool {
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table_bookmarks} WHERE user_id = %d AND lesson_id = %d",
				$user_id,
				$lesson_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $existing ) {
			$this->wpdb->delete( $this->table_bookmarks, array( 'id' => $existing ), array( '%d' ) );
			return false;
		}
		$this->wpdb->insert(
			$this->table_bookmarks,
			array(
				'user_id'    => $user_id,
				'lesson_id'  => $lesson_id,
				'course_id'  => $course_id,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s' )
		);
		return true;
	}

	// ─── Wishlist ────────────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Wishlist.
	 *
	 * @param int $user_id User id.
	 * @param int $limit Limit.
	 */
	public function get_wishlist( int $user_id, int $limit = 20 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT w.*, c.title, c.slug, c.thumbnail_id, c.price, c.is_free, c.avg_rating, c.enrollment_count
				FROM {$this->table_wishlist} w
				INNER JOIN {$this->table_courses} c ON w.course_id = c.id
				WHERE w.user_id = %d
				ORDER BY w.created_at DESC
				LIMIT %d",
				$user_id,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Toggle wishlist.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function toggle_wishlist( int $user_id, int $course_id ): bool {
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table_wishlist} WHERE user_id = %d AND course_id = %d",
				$user_id,
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $existing ) {
			$this->wpdb->delete( $this->table_wishlist, array( 'id' => $existing ), array( '%d' ) );
			return false;
		}
		$this->wpdb->insert(
			$this->table_wishlist,
			array(
				'user_id'    => $user_id,
				'course_id'  => $course_id,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s' )
		);
		return true;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Wishlisted.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function is_wishlisted( int $user_id, int $course_id ): bool {
		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_wishlist} WHERE user_id = %d AND course_id = %d",
				$user_id,
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $count > 0;
	}

	// ─── Learning Goals ──────────────────────────────────────────.

	/**
	 * Learning goals.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function get_learning_goals( int $user_id, int $course_id = 0 ): array {
		$where  = 'user_id = %d';
		$values = array( $user_id );
		if ( $course_id ) {
			$where .= ' AND course_id = %d';
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			$values[] = $course_id; }
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_learning_goals} WHERE {$where} ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				...$values
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Insert learning goal.
	 *
	 * @param array $data Data.
	 */
	public function insert_learning_goal( array $data ): int {
		$this->wpdb->insert(
			$this->table_learning_goals,
			array(
				'user_id'     => absint( $data['user_id'] ),
				'course_id'   => absint( $data['course_id'] ),
				'goal_text'   => sanitize_text_field( $data['goal_text'] ),
				'target_date' => sanitize_text_field( $data['target_date'] ?? '' ),
				'status'      => 'active',
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Delete learning goal.
	 *
	 * @param int $goal_id Goal id.
	 * @param int $user_id User id.
	 */
	public function delete_learning_goal( int $goal_id, int $user_id ): bool {
		return (bool) $this->wpdb->delete(
			$this->table_learning_goals,
			array(
				'id'      => $goal_id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);
	}

	// ─── Announcements ───────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Announcements.
	 *
	 * @param int $course_id Course id.
	 * @param int $limit Limit.
	 */
	public function get_announcements( int $course_id, int $limit = 20 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT a.*, u.display_name AS instructor_name
				FROM {$this->table_course_announcements} a
				LEFT JOIN {$this->wpdb->users} u ON a.instructor_id = u.ID
				WHERE a.course_id = %d
				ORDER BY a.created_at DESC
				LIMIT %d",
				$course_id,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Announcement.
	 *
	 * @param int $announcement_id Announcement id.
	 */
	public function get_announcement( int $announcement_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_course_announcements} WHERE id = %d LIMIT 1",
				$announcement_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	/**
	 * Insert announcement.
	 *
	 * @param array $data Data.
	 */
	public function insert_announcement( array $data ): int {
		$this->wpdb->insert(
			$this->table_course_announcements,
			array(
				'course_id'     => absint( $data['course_id'] ),
				'instructor_id' => absint( $data['instructor_id'] ),
				'title'         => sanitize_text_field( $data['title'] ),
				'content'       => self::sanitize_rich( $data['content'] ),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Course announcements.
	 *
	 * @param int $course_id Course id.
	 * @param int $limit Limit.
	 */
	public function get_course_announcements( int $course_id, int $limit = 10 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT a.*, u.display_name AS instructor_name
				FROM {$this->table_course_announcements} a
				LEFT JOIN {$this->wpdb->users} u ON a.instructor_id = u.ID
				WHERE a.course_id = %d
				ORDER BY a.created_at DESC LIMIT %d",
				$course_id,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Delete announcement.
	 *
	 * @param int $announcement_id Announcement id.
	 */
	public function delete_announcement( int $announcement_id ): bool {
		return (bool) $this->wpdb->delete( $this->table_course_announcements, array( 'id' => $announcement_id ), array( '%d' ) );
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Course quizzes.
	 *
	 * @param int $course_id Course id.
	 */
	public function get_course_quizzes( int $course_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT q.*, l.title AS lesson_title
				FROM {$this->table_quizzes} q
				LEFT JOIN {$this->table_lessons} l ON q.lesson_id = l.id
				WHERE q.course_id = %d
				ORDER BY q.id ASC",
				$course_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Quiz with questions.
	 *
	 * @param int $quiz_id Quiz id.
	 */
	public function get_quiz_with_questions( int $quiz_id ): ?array {
		$quiz = $this->get_quiz( $quiz_id );
		if ( ! $quiz ) {
			return null;
		}
		$quiz['questions'] = $this->get_quiz_questions( $quiz_id );
		return $quiz;
	}

	// ─── Instructor Stats ────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Instructor stats.
	 *
	 * @param int $instructor_id Instructor id.
	 */
	public function get_instructor_stats( int $instructor_id ): array {
		$courses        = (int) $this->wpdb->get_var(
			$this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_courses} WHERE instructor_id = %d", $instructor_id )
		);
		$total_students = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(DISTINCT e.user_id) FROM {$this->table_enrollments} e
				INNER JOIN {$this->table_courses} c ON e.course_id = c.id
				WHERE c.instructor_id = %d",
				$instructor_id
			)
		);
		$total_revenue  = (float) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$this->table_instructor_payouts}
				WHERE instructor_id = %d AND status = 'paid'",
				$instructor_id
			)
		);
		$avg_rating     = (float) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COALESCE(AVG(c.avg_rating), 0) FROM {$this->table_courses} c
				WHERE c.instructor_id = %d AND c.review_count > 0",
				$instructor_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return array(
			'total_courses'  => $courses,
			'total_students' => $total_students,
			'total_revenue'  => $total_revenue,
			'avg_rating'     => round( $avg_rating, 2 ),
		);
	}

	// ─── Count Methods ───────────────────────────────────────────.

	/**
	 * Count courses.
	 *
	 * @param array $args Args.
	 */
	public function count_courses( array $args = array() ): int {
		$where  = array( '1=1' );
		$values = array();
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status']; }
		if ( ! empty( $args['instructor_id'] ) ) {
			$where[]  = 'instructor_id = %d';
			$values[] = $args['instructor_id']; }
		$where_sql = implode( ' AND ', $where );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( ! empty( $values ) ) {
			return (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_courses} WHERE {$where_sql}", ...$values ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
		}
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_courses} WHERE {$where_sql}" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Count enrollments.
	 *
	 * @param int $course_id Course id.
	 */
	public function count_enrollments( int $course_id = 0 ): int {
		if ( $course_id ) {
			return (int) $this->wpdb->get_var(
				$this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_enrollments} WHERE course_id = %d", $course_id )
			);
		}
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_enrollments}" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Count categories.
	 */
	public function count_categories(): int {
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_categories}" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Count lessons.
	 *
	 * @param int $course_id Course id.
	 */
	public function count_lessons( int $course_id ): int {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_lessons} WHERE course_id = %d", $course_id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// ─── Start Lesson ────────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Start lesson.
	 *
	 * @param int $user_id User id.
	 * @param int $lesson_id Lesson id.
	 * @param int $course_id Course id.
	 */
	public function start_lesson( int $user_id, int $lesson_id, int $course_id ): void {
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table_lesson_progress} WHERE user_id = %d AND lesson_id = %d",
				$user_id,
				$lesson_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( ! $existing ) {
			$this->wpdb->insert(
				$this->table_lesson_progress,
				array(
					'user_id'    => $user_id,
					'lesson_id'  => $lesson_id,
					'course_id'  => $course_id,
					'status'     => 'in_progress',
					'started_at' => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%d', '%s', '%s' )
			);
		}
	}

	// ─── Quiz / Assignment Helpers ──────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Quiz by lesson.
	 *
	 * @param int $lesson_id Lesson id.
	 */
	public function get_quiz_by_lesson( int $lesson_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_quizzes} WHERE lesson_id = %d LIMIT 1", $lesson_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Assignment by lesson.
	 *
	 * @param int $lesson_id Lesson id.
	 */
	public function get_assignment_by_lesson( int $lesson_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_assignments} WHERE lesson_id = %d LIMIT 1", $lesson_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * User assignment submission.
	 *
	 * @param int $user_id User id.
	 * @param int $assignment_id Assignment id.
	 */
	public function get_user_assignment_submission( int $user_id, int $assignment_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_assignment_submissions} WHERE user_id = %d AND assignment_id = %d LIMIT 1",
				$user_id,
				$assignment_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Assignment submission.
	 *
	 * @param int $submission_id Submission id.
	 */
	public function get_assignment_submission( int $submission_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_assignment_submissions} WHERE id = %d LIMIT 1",
				$submission_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// ─── Lesson Progress Helpers ────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Lesson progress.
	 *
	 * @param int $user_id User id.
	 * @param int $lesson_id Lesson id.
	 */
	public function get_lesson_progress( int $user_id, int $lesson_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_lesson_progress} WHERE user_id = %d AND lesson_id = %d LIMIT 1",
				$user_id,
				$lesson_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Course progress.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function get_course_progress( int $user_id, int $course_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_course_progress} WHERE user_id = %d AND course_id = %d LIMIT 1",
				$user_id,
				$course_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// ─── Notes by Lesson ────────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Lesson notes.
	 *
	 * @param int $user_id User id.
	 * @param int $lesson_id Lesson id.
	 */
	public function get_lesson_notes( int $user_id, int $lesson_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_notes} WHERE user_id = %d AND lesson_id = %d ORDER BY timestamp_seconds ASC",
				$user_id,
				$lesson_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// ─── Learning Streaks ───────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Learning streak.
	 *
	 * @param int $user_id User id.
	 */
	public function get_learning_streak( int $user_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_learning_streaks} WHERE user_id = %d LIMIT 1",
				$user_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// ─── Wishlist Alias ─────────────────────────────────────────.

	/**
	 * User wishlist.
	 *
	 * @param int $user_id User id.
	 */
	public function get_user_wishlist( int $user_id ): array {
		return $this->get_wishlist( $user_id );
	}

	// ─── Learning Goals CRUD ────────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Learning goal.
	 *
	 * @param int $goal_id Goal id.
	 */
	public function get_learning_goal( int $goal_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_learning_goals} WHERE id = %d LIMIT 1",
				$goal_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	/**
	 * Update learning goal.
	 *
	 * @param int   $goal_id Goal id.
	 * @param array $data Data.
	 */
	public function update_learning_goal( int $goal_id, array $data ): bool {
		$update = array();
		$format = array();
		if ( isset( $data['goal_text'] ) ) {
			$update['goal_text'] = sanitize_text_field( $data['goal_text'] );
			$format[]            = '%s'; }
		if ( isset( $data['target_date'] ) ) {
			$update['target_date'] = sanitize_text_field( $data['target_date'] );
			$format[]              = '%s'; }
		if ( isset( $data['status'] ) ) {
			$update['status'] = sanitize_text_field( $data['status'] );
			$format[]         = '%s'; }
		if ( empty( $update ) ) {
			return false; }
		return (bool) $this->wpdb->update( $this->table_learning_goals, $update, array( 'id' => $goal_id ), $format, array( '%d' ) );
	}

	// ─── Announcements CRUD ────────────────────────────────────.

	/**
	 * Update announcement.
	 *
	 * @param int   $announcement_id Announcement id.
	 * @param array $data Data.
	 */
	public function update_announcement( int $announcement_id, array $data ): bool {
		$update = array();
		$format = array();
		if ( isset( $data['title'] ) ) {
			$update['title'] = sanitize_text_field( $data['title'] );
			$format[]        = '%s'; }
		if ( isset( $data['content'] ) ) {
			$update['content'] = self::sanitize_rich( $data['content'] );
			$format[]          = '%s'; }
		if ( isset( $data['is_pinned'] ) ) {
			$update['is_pinned'] = absint( $data['is_pinned'] );
			$format[]            = '%d'; }
		if ( empty( $update ) ) {
			return false; }
		return (bool) $this->wpdb->update( $this->table_course_announcements, $update, array( 'id' => $announcement_id ), $format, array( '%d' ) );
	}

	// ─── Notification Delete ───────────────────────────────────.

	/**
	 * Delete notification.
	 *
	 * @param int $notification_id Notification id.
	 */
	public function delete_notification( int $notification_id ): bool {
		return (bool) $this->wpdb->delete( $this->table_notifications, array( 'id' => $notification_id ), array( '%d' ) );
	}

	// ─── Instructor Payouts CRUD ────────────────────────────────.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Instructor payouts.
	 *
	 * @param int $instructor_id Instructor id.
	 * @param int $limit Limit.
	 */
	public function get_instructor_payouts( int $instructor_id, int $limit = 20 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT p.*, c.title AS course_title
				FROM {$this->table_instructor_payouts} p
				LEFT JOIN {$this->table_courses} c ON p.course_id = c.id
				WHERE p.instructor_id = %d
				ORDER BY p.created_at DESC
				LIMIT %d",
				$instructor_id,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Insert payout.
	 *
	 * @param array $data Data.
	 */
	public function insert_payout( array $data ): int {
		$this->wpdb->insert(
			$this->table_instructor_payouts,
			array(
				'instructor_id' => absint( $data['instructor_id'] ),
				'course_id'     => absint( $data['course_id'] ),
				'amount'        => (float) $data['amount'],
				'period_start'  => sanitize_text_field( $data['period_start'] ),
				'period_end'    => sanitize_text_field( $data['period_end'] ),
				'status'        => 'pending',
			),
			array( '%d', '%d', '%f', '%s', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update payout.
	 *
	 * @param int   $payout_id Payout id.
	 * @param array $data Data.
	 */
	public function update_payout( int $payout_id, array $data ): bool {
		$update = array();
		$format = array();
		if ( isset( $data['status'] ) ) {
			$update['status'] = sanitize_text_field( $data['status'] );
			$format[]         = '%s'; }
		if ( isset( $data['paid_at'] ) ) {
			$update['paid_at'] = sanitize_text_field( $data['paid_at'] );
			$format[]          = '%s'; }
		if ( isset( $data['amount'] ) ) {
			$update['amount'] = (float) $data['amount'];
			$format[]         = '%f'; }
		if ( empty( $update ) ) {
			return false; }
		return (bool) $this->wpdb->update( $this->table_instructor_payouts, $update, array( 'id' => $payout_id ), $format, array( '%d' ) );
	}

	/**
	 * Delete payout.
	 *
	 * @param int $payout_id Payout id.
	 */
	public function delete_payout( int $payout_id ): bool {
		return (bool) $this->wpdb->delete( $this->table_instructor_payouts, array( 'id' => $payout_id ), array( '%d' ) );
	}

	// ─── Helper ──────────────────────────────────────────────────.

	/**
	 * Charset collate.
	 */
	private function get_charset_collate(): string {
		global $wpdb;
		return 'DEFAULT CHARACTER SET ' . $wpdb->charset . ' COLLATE ' . $wpdb->collate;
	}
}
