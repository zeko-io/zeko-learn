<?php
/**
 * Core singleton orchestrator for Zeko Learn.
 *
 * Wires all subsystems: DB, public, admin, AJAX, REST, ecosystem.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Learn. */
final class Zeko_Learn {

	/**
	 * Instance.
	 *
	 * @var ?self Instance.
	 */
	private static ?self $instance = null;

	/**
	 * Db.
	 *
	 * @var Zeko_Learn_DB Db.
	 */
	private Zeko_Learn_DB $db;

	/**
	 * Public.
	 *
	 * @var Zeko_Learn_Public Public.
	 */
	private Zeko_Learn_Public $public;

	/**
	 * Admin.
	 *
	 * @var Zeko_Learn_Admin Admin.
	 */
	private Zeko_Learn_Admin $admin;

	/**
	 * Ajax.
	 *
	 * @var Zeko_Learn_Ajax Ajax.
	 */
	private Zeko_Learn_Ajax $ajax;

	/**
	 * Rest api.
	 *
	 * @var Zeko_Learn_REST_API Rest api.
	 */
	private Zeko_Learn_REST_API $rest_api;

	/**
	 * Ecosystem.
	 *
	 * @var Zeko_Learn_Ecosystem Ecosystem.
	 */
	private Zeko_Learn_Ecosystem $ecosystem;

	/**
	 * Emails.
	 *
	 * @var Zeko_Learn_Emails Emails.
	 */
	private Zeko_Learn_Emails $emails;

	/**
	 * Progress.
	 *
	 * @var Zeko_Learn_Progress Progress.
	 */
	private Zeko_Learn_Progress $progress;

	/**
	 * Singleton accessor.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->init_hooks();
	}

	/**
	 * Load all class files.
	 */
	private function load_dependencies(): void {
		$base = ZEKO_LEARN_PLUGIN_PATH . 'includes/';

		// Core.
		require_once $base . 'db/class-zeko-learn-db.php';

		// Subsystems.
		require_once $base . 'public/class-zeko-learn-public.php';
		require_once $base . 'admin/class-zeko-learn-admin.php';
		require_once $base . 'class-zeko-learn-ajax.php';
		require_once $base . 'class-zeko-learn-rest-api.php';
		require_once $base . 'class-zeko-learn-ecosystem.php';
		require_once $base . 'class-zeko-learn-emails.php';
		require_once $base . 'class-zeko-learn-progress.php';
		require_once $base . 'class-zeko-learn-demo-data.php';

		// Instantiate with shared DB layer.
		$this->db        = new Zeko_Learn_DB();
		$this->public    = new Zeko_Learn_Public( $this->db );
		$this->admin     = new Zeko_Learn_Admin( $this->db );
		$this->ajax      = new Zeko_Learn_Ajax( $this->db );
		$this->rest_api  = new Zeko_Learn_REST_API( $this->db );
		$this->ecosystem = new Zeko_Learn_Ecosystem( $this->db );
		$this->emails    = new Zeko_Learn_Emails( $this->db );
		$this->progress  = new Zeko_Learn_Progress( $this->db );
	}

	/**
	 * Load text domain for translations.
	 */
	private function set_locale(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'zeko-learn', false, dirname( ZEKO_LEARN_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Register core hooks.
	 */
	private function init_hooks(): void {
		// DB version check / auto-upgrade.
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade_db' ), 5 );

		// Flush rewrites after all CPTs registered.
		add_action( 'init', array( $this, 'flush_rewrites_late' ), 999 );
	}

	/**
	 * Check DB version and upgrade if needed.
	 */
	public function maybe_upgrade_db(): void {
		$installed = get_option( 'zeko_learn_db_version', '0' );
		if ( version_compare( $installed, ZEKO_LEARN_DB_VERSION, '<' ) ) {
			$this->db->create_tables();
			$this->db->add_missing_indexes();
			update_option( 'zeko_learn_db_version', ZEKO_LEARN_DB_VERSION );
		}
	}

	/**
	 * Flush rewrite rules on init priority 999 (after all CPTs registered).
	 */
	public function flush_rewrites_late(): void {
		if ( get_option( 'zeko_learn_flush_rewrites' ) ) {
			flush_rewrite_rules();
			delete_option( 'zeko_learn_flush_rewrites' );
		}
	}

	/**
	 * Get the DB layer.
	 */
	public function get_db(): Zeko_Learn_DB {
		return $this->db;
	}

	/**
	 * Get the public frontend handler.
	 */
	public function get_public(): Zeko_Learn_Public {
		return $this->public;
	}

	/**
	 * Get the admin handler.
	 */
	public function get_admin(): Zeko_Learn_Admin {
		return $this->admin;
	}

	/**
	 * Get the AJAX handler.
	 */
	public function get_ajax(): Zeko_Learn_Ajax {
		return $this->ajax;
	}

	/**
	 * Get the REST API handler.
	 */
	public function get_rest_api(): Zeko_Learn_REST_API {
		return $this->rest_api;
	}

	/**
	 * Get the ecosystem integration handler.
	 */
	public function get_ecosystem(): Zeko_Learn_Ecosystem {
		return $this->ecosystem;
	}

	/**
	 * Get the email notification handler.
	 */
	public function get_emails(): Zeko_Learn_Emails {
		return $this->emails;
	}

	/**
	 * Get the progress tracker.
	 */
	public function get_progress(): Zeko_Learn_Progress {
		return $this->progress;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \LogicException When an error occurs.
	 */
	public function __wakeup() {
		throw new \LogicException( 'Cannot unserialize singleton.' );
	}
}
