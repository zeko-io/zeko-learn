<?php
/**
 * Plugin Name:       Zeko Learn
 * Plugin URI:        https://ozconsultz.com/zeko-learn
 * Description:       Full-featured learning management system with courses, quizzes, certificates, and deep ecosystem integration.
 * Version:           1.0.0
 * Author:            Zeko Team
 * Author URI:        https://ozconsultz.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zeko-learn
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      7.1.2
 *
 * @package Zeko_ZEKO_LEARN
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ZEKO_LEARN_VERSION' ) ) {
	define( 'ZEKO_LEARN_VERSION', '1.0.0' );
}

if ( ! defined( 'ZEKO_LEARN_PLUGIN_PATH' ) ) {
	define( 'ZEKO_LEARN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ZEKO_LEARN_PLUGIN_URL' ) ) {
	define( 'ZEKO_LEARN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'ZEKO_LEARN_PLUGIN_BASENAME' ) ) {
	define( 'ZEKO_LEARN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'ZEKO_LEARN_DB_VERSION' ) ) {
	define( 'ZEKO_LEARN_DB_VERSION', '1.1.0' );
}

require_once ZEKO_LEARN_PLUGIN_PATH . 'includes/class-zeko-learn.php';
require_once ZEKO_LEARN_PLUGIN_PATH . 'includes/class-zeko-learn-embeds.php';
require_once ZEKO_LEARN_PLUGIN_PATH . 'includes/privacy/class-zeko-learn-privacy.php';

/**
 * Boot the plugin on plugins_loaded.
 */
function zeko_learn_init() {
	return Zeko_Learn::instance();
}
add_action( 'plugins_loaded', 'zeko_learn_init' );

/**
 * Helper to access the singleton.
 *
 * @return Zeko_Learn
 */
function zeko_learn() {
	return Zeko_Learn::instance();
}

/**
 * Activation: create schema + pages + flush rewrites.
 */
function zeko_learn_activate() {
	require_once ZEKO_LEARN_PLUGIN_PATH . 'includes/db/class-zeko-learn-db.php';
	$db = new Zeko_Learn_DB();
	$db->create_tables();

	zeko_learn_create_shortcode_pages();
	flush_rewrite_rules();
}

/**
 * Deactivation: flush rewrites.
 */
function zeko_learn_deactivate() {
	flush_rewrite_rules();
}

/**
 * Create default shortcode pages.
 */
function zeko_learn_create_shortcode_pages() {
	$pages = array(
		'courses'              => array(
			'title'   => __( 'Courses', 'zeko-learn' ),
			'content' => '[zeko_learn_catalog]',
		),
		'course-dashboard'     => array(
			'title'   => __( 'My Learning', 'zeko-learn' ),
			'content' => '[zeko_learn_student_dashboard]',
		),
		'instructor-dashboard' => array(
			'title'   => __( 'Instructor Dashboard', 'zeko-learn' ),
			'content' => '[zeko_learn_instructor_dashboard]',
		),
		'certificates'         => array(
			'title'   => __( 'My Certificates', 'zeko-learn' ),
			'content' => '[zeko_learn_certificates]',
		),
	);

	foreach ( $pages as $slug => $page ) {
		$existing = class_exists( 'Zeko_Core_Helpers' )
			? Zeko_Core_Helpers::get_instance()->get_page_by_slug( $slug )
			: get_page_by_path( $slug );
		if ( ! $existing ) {
			$result = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_content' => $page['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_name'    => $slug,
				)
			);
			if ( is_wp_error( $result ) ) {
				error_log( 'Zeko Learn: Failed to create page "' . $slug . '": ' . $result->get_error_message() );
			} elseif ( function_exists( 'zeko_mark_plugin_page' ) ) {
					zeko_mark_plugin_page( $result, 'learn' );
			}
		}
	}
}

/**
 * Ensure pages exist on admin_init (in case activation hook missed).
 */
function zeko_learn_maybe_create_pages() {
	$pages_created = get_option( 'zeko_learn_pages_created', false );
	if ( ! $pages_created ) {
		zeko_learn_create_shortcode_pages();
		update_option( 'zeko_learn_pages_created', true );
	}
}

register_activation_hook( __FILE__, 'zeko_learn_activate' );
register_deactivation_hook( __FILE__, 'zeko_learn_deactivate' );
add_action( 'admin_init', 'zeko_learn_maybe_create_pages' );
