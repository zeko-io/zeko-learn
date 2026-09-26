<?php
/**
 * Zeko Learn — Uninstall
 *
 * Fired when the plugin is deleted via WP admin. Drops all tables and removes options.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	'zeko_courses',
	'zeko_course_sections',
	'zeko_lessons',
	'zeko_enrollments',
	'zeko_lesson_progress',
	'zeko_course_progress',
	'zeko_quizzes',
	'zeko_quiz_questions',
	'zeko_quiz_attempts',
	'zeko_assignments',
	'zeko_assignment_submissions',
	'zeko_reviews',
	'zeko_categories',
	'zeko_skills',
	'zeko_course_skills',
	'zeko_discussions',
	'zeko_discussion_votes',
	'zeko_certificates',
	'zeko_notes',
	'zeko_bookmarks',
	'zeko_learning_goals',
	'zeko_course_announcements',
	'zeko_instructor_payouts',
	'zeko_learn_notifications',
	'zeko_wishlist',
	'zeko_learning_streaks',
);

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// Remove options.
delete_option( 'zeko_learn_db_version' );
// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
delete_option( 'zeko_learn_pages_created' );
delete_option( 'zeko_learn_flush_rewrites' );
delete_option( 'zeko_learn_settings' );
