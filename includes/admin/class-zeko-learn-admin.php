<?php
/**
 * Admin handler for Zeko Learn.
 *
 * Admin menus, settings, course management, CRUD operations.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Learn_Admin. */
class Zeko_Learn_Admin {

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
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_save_course' ) );
		add_action( 'admin_init', array( $this, 'handle_save_category' ) );
		add_action( 'admin_init', array( $this, 'handle_save_quiz' ) );
		add_action( 'admin_init', array( $this, 'handle_save_assignment' ) );
	}

	/**
	 * Settings.
	 */
	public function register_settings(): void {
		register_setting( 'zeko_learn_settings', 'zeko_learn_currency_symbol', array( 'default' => '$' ) );
		register_setting(
			'zeko_learn_settings',
			'zeko_learn_passing_score',
			array(
				'default' => '60',
				'type'    => 'number',
			)
		);
		register_setting(
			'zeko_learn_settings',
			'zeko_learn_instructor_revenue_pct',
			array(
				'default' => '70',
				'type'    => 'number',
			)
		);
		register_setting( 'zeko_learn_settings', 'zeko_learn_certificate_prefix', array( 'default' => 'ZL' ) );
	}

	/**
	 * Add menus.
	 */
	public function add_menus(): void {
		add_menu_page(
			__( 'Zeko Learn', 'zeko-learn' ),
			__( 'Zeko Learn', 'zeko-learn' ),
			'manage_options',
			'zeko-learn',
			array( $this, 'render_dashboard' ),
			'dashicons-welcome-learn-more',
			30
		);

		add_submenu_page( 'zeko-learn', __( 'Dashboard', 'zeko-learn' ), __( 'Dashboard', 'zeko-learn' ), 'manage_options', 'zeko-learn', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'zeko-learn', __( 'Courses', 'zeko-learn' ), __( 'Courses', 'zeko-learn' ), 'manage_options', 'zeko-learn-courses', array( $this, 'render_courses' ) );
		add_submenu_page( 'zeko-learn', __( 'Add Course', 'zeko-learn' ), __( 'Add Course', 'zeko-learn' ), 'manage_options', 'zeko-learn-add-course', array( $this, 'render_add_course' ) );
		add_submenu_page( 'zeko-learn', __( 'Categories', 'zeko-learn' ), __( 'Categories', 'zeko-learn' ), 'manage_options', 'zeko-learn-categories', array( $this, 'render_categories' ) );
		add_submenu_page( 'zeko-learn', __( 'Enrollments', 'zeko-learn' ), __( 'Enrollments', 'zeko-learn' ), 'manage_options', 'zeko-learn-enrollments', array( $this, 'render_enrollments' ) );
		add_submenu_page( 'zeko-learn', __( 'Quizzes', 'zeko-learn' ), __( 'Quizzes', 'zeko-learn' ), 'manage_options', 'zeko-learn-quizzes', array( $this, 'render_quizzes' ) );
		add_submenu_page( 'zeko-learn', __( 'Assignments', 'zeko-learn' ), __( 'Assignments', 'zeko-learn' ), 'manage_options', 'zeko-learn-assignments', array( $this, 'render_assignments' ) );
		add_submenu_page( 'zeko-learn', __( 'Certificates', 'zeko-learn' ), __( 'Certificates', 'zeko-learn' ), 'manage_options', 'zeko-learn-certificates', array( $this, 'render_certificates' ) );
		add_submenu_page( 'zeko-learn', __( 'Settings', 'zeko-learn' ), __( 'Settings', 'zeko-learn' ), 'manage_options', 'zeko-learn-settings', array( $this, 'render_settings' ) );
		add_submenu_page( 'zeko-learn', __( 'Demo Data', 'zeko-learn' ), __( 'Demo Data', 'zeko-learn' ), 'manage_options', 'zeko-learn-demo-data', array( $this, 'render_demo_data' ) );
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'zeko-learn' ) === false ) {
			return;
		}
		wp_enqueue_style( 'zeko-learn-admin', ZEKO_LEARN_PLUGIN_URL . 'assets/css/zeko-learn-admin.css', array(), ZEKO_LEARN_VERSION );
		wp_enqueue_script( 'zeko-learn-admin', ZEKO_LEARN_PLUGIN_URL . 'assets/js/zeko-learn-admin.js', array( 'jquery', 'jquery-ui-sortable' ), ZEKO_LEARN_VERSION, true );
		wp_localize_script(
			'zeko-learn-admin',
			'zekoLearnAdmin',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'zeko_learn_admin_nonce' ),
				'strings' => array(
					'confirmDelete'       => __( 'Are you sure you want to delete this?', 'zeko-learn' ),
					'confirmDeleteCourse' => __( 'Are you sure you want to delete this course? This action cannot be undone.', 'zeko-learn' ),
					'sectionAdded'        => __( 'Section added', 'zeko-learn' ),
					'lessonAdded'         => __( 'Lesson added', 'zeko-learn' ),
					'saved'               => __( 'Saved', 'zeko-learn' ),
					'error'               => __( 'Error occurred', 'zeko-learn' ),
					'deleted'             => __( 'Deleted', 'zeko-learn' ),
				),
			)
		);
	}

	/**
	 * Admin notices.
	 *
	 * @param string $type Type.
	 * @param string $message Message.
	 */
	private function admin_notices( string $type, string $message ): void {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	// ─── Dashboard ──────────────────────────────────────────────.

	/**
	 * Render dashboard.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		$total_courses     = $this->db->count_courses();
		$published         = $this->db->count_courses( array( 'status' => 'published' ) );
		$total_enrollments = $this->db->count_enrollments();
		$total_categories  = $this->db->count_categories();
		$recent_courses    = $this->db->get_courses(
			array(
				'status'  => '',
				'limit'   => 5,
				'orderby' => 'created_at',
				'order'   => 'DESC',
			)
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Zeko Learn Dashboard', 'zeko-learn' ) . '</h1>';

		echo '<div class="zeko-learn-stats-grid">';
		echo '<div class="zeko-learn-stat-card"><div class="stat-number">' . esc_html( $total_courses ) . '</div><div class="stat-label">' . esc_html__( 'Total Courses', 'zeko-learn' ) . '</div></div>';
		echo '<div class="zeko-learn-stat-card"><div class="stat-number">' . esc_html( $published ) . '</div><div class="stat-label">' . esc_html__( 'Published', 'zeko-learn' ) . '</div></div>';
		echo '<div class="zeko-learn-stat-card"><div class="stat-number">' . esc_html( $total_enrollments ) . '</div><div class="stat-label">' . esc_html__( 'Enrollments', 'zeko-learn' ) . '</div></div>';
		echo '<div class="zeko-learn-stat-card"><div class="stat-number">' . esc_html( $total_categories ) . '</div><div class="stat-label">' . esc_html__( 'Categories', 'zeko-learn' ) . '</div></div>';
		echo '</div>';

		echo '<div class="zeko-learn-recent-courses">';
		echo '<h2>' . esc_html__( 'Recent Courses', 'zeko-learn' ) . '</h2>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Title', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Instructor', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Enrollments', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Rating', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'zeko-learn' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		if ( empty( $recent_courses ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No courses yet.', 'zeko-learn' ) . '</td></tr>';
		} else {
			foreach ( $recent_courses as $course ) {
				$edit_url = admin_url( 'admin.php?page=zeko-learn-add-course&course_id=' . $course['id'] );
				echo '<tr>';
				echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html( $course['title'] ) . '</a></td>';
				echo '<td>' . esc_html( $this->get_instructor_name( $course['instructor_id'] ) ) . '</td>';
				echo '<td><span class="zeko-status-badge zeko-status-' . esc_attr( $course['status'] ) . '">' . esc_html( $course['status'] ) . '</span></td>';
				echo '<td>' . esc_html( $course['enrollment_count'] ) . '</td>';
				echo '<td>' . esc_html( number_format( (float) $course['avg_rating'], 1 ) ) . '</td>';
				echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ), $course['created_at'] ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';

		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=zeko-learn-add-course' ) ) . '" class="button button-primary">' . esc_html__( 'Add New Course', 'zeko-learn' ) . '</a></p>';
		echo '</div>';
	}

	// ─── Courses List ───────────────────────────────────────────.

	/**
	 * Render courses.
	 */
	public function render_courses(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		if ( isset( $_GET['message'] ) && 'deleted' === $_GET['message'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag; compared against fixed literal.
			$this->admin_notices( 'success', __( 'Course deleted.', 'zeko-learn' ) );
		}
		if ( isset( $_GET['message'] ) && 'updated' === $_GET['message'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag; compared against fixed literal.
			$this->admin_notices( 'success', __( 'Course saved.', 'zeko-learn' ) );
		}

		$current_page  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination flag; absint() coerced.
		$per_page      = 20;
		$offset        = ( $current_page - 1 ) * $per_page;
		$status_filter = isset( $_GET['course_status'] ) ? sanitize_text_field( wp_unslash( $_GET['course_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter; sanitized and compared against fixed status allow-list below.
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list search; sanitized and bound via $wpdb->prepare() in get_courses().

		$args = array(
			'status'  => $status_filter,
			'limit'   => $per_page,
			'offset'  => $offset,
			'search'  => $search,
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);

		$courses = $this->db->get_courses( $args );
		$total   = $this->db->count_courses( array( 'status' => $status_filter ) );
		$pages   = ceil( $total / $per_page );

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Courses', 'zeko-learn' ) . '</h1>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=zeko-learn-add-course' ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'zeko-learn' ) . '</a>';
		echo '<hr class="wp-header-end">';

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="zeko-learn-courses">';

		echo '<div class="tablenav top">';
		echo '<div class="alignleft actions">';
		echo '<select name="course_status" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'All Statuses', 'zeko-learn' ) . '</option>';
		foreach ( array( 'published', 'draft', 'pending', 'archived' ) as $s ) {
			$selected = $status_filter === $s ? 'selected' : '';
			echo '<option value="' . esc_attr( $s ) . '" ' . esc_attr( $selected ) . '>' . esc_html( ucfirst( $s ) ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="alignright">';
		echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search courses...', 'zeko-learn' ) . '">';
		echo '<input type="submit" class="button" value="' . esc_attr__( 'Search', 'zeko-learn' ) . '">';
		echo '</div>';
		echo '</div>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th class="column-title">' . esc_html__( 'Title', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Instructor', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Enrollments', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Rating', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Price', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'zeko-learn' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $courses ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No courses found.', 'zeko-learn' ) . '</td></tr>';
		} else {
			foreach ( $courses as $course ) {
				$edit_url   = admin_url( 'admin.php?page=zeko-learn-add-course&course_id=' . $course['id'] );
				$delete_url = wp_nonce_url(
					admin_url( 'admin.php?page=zeko-learn-courses&action=delete&course_id=' . $course['id'] ),
					'zeko_learn_delete_course_' . $course['id']
				);
				echo '<tr>';
				echo '<td class="column-title"><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $course['title'] ) . '</a></strong></td>';
				echo '<td>' . esc_html( $this->get_instructor_name( $course['instructor_id'] ) ) . '</td>';
				echo '<td><span class="zeko-status-badge zeko-status-' . esc_attr( $course['status'] ) . '">' . esc_html( $course['status'] ) . '</span></td>';
				echo '<td>' . esc_html( $course['enrollment_count'] ) . '</td>';
				echo '<td>' . esc_html( number_format( (float) $course['avg_rating'], 1 ) ) . ' (' . esc_html( $course['review_count'] ) . ')</td>';
				echo '<td>' . esc_html( $course['is_free'] ? __( 'Free', 'zeko-learn' ) : get_option( 'zeko_learn_currency_symbol', '$' ) . number_format( (float) $course['price'], 2 ) ) . '</td>';
				echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ), $course['created_at'] ) ) . '</td>';
				echo '<td>';
				echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( 'Edit', 'zeko-learn' ) . '</a> ';
				echo '<a href="' . esc_url( $delete_url ) . '" class="button button-small zeko-delete-course" data-course-id="' . esc_attr( $course['id'] ) . '">' . esc_html__( 'Delete', 'zeko-learn' ) . '</a>';
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';

		if ( $pages > 1 ) {
			echo '<div class="tablenav bottom"><div class="tablenav-pages">';
			echo '<span class="displaying-num">' . esc_html( $total ) . ' items</span>';
			echo '<span class="pagination-links">';
			if ( $current_page > 1 ) {
				echo '<a class="prev-page button" href="' . esc_url( add_query_arg( 'paged', $current_page - 1, admin_url( 'admin.php?page=zeko-learn-courses' ) ) ) . '">&lsaquo;</a>';
			}
			echo '<span class="paging-input">' . esc_html( $current_page ) . ' of <span class="total-pages">' . esc_html( $pages ) . '</span></span>';
			if ( $current_page < $pages ) {
				echo '<a class="next-page button" href="' . esc_url( add_query_arg( 'paged', $current_page + 1, admin_url( 'admin.php?page=zeko-learn-courses' ) ) ) . '">&rsaquo;</a>';
			}
			echo '</span></div></div>';
		}

		echo '</form>';
		echo '</div>';
	}

	// ─── Course Form (Add / Edit) ───────────────────────────────.

	/**
	 * Render add course.
	 */
	public function render_add_course(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( $_GET['course_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit flag; absint() coerced.
		$course    = $course_id ? $this->db->get_course( $course_id ) : array();
		$sections  = $course_id ? $this->db->get_course_sections( $course_id ) : array();
		$is_edit   = ! empty( $course );

		if ( isset( $_GET['message'] ) && 'updated' === $_GET['message'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag; compared against fixed literal.
			$this->admin_notices( 'success', __( 'Course saved.', 'zeko-learn' ) );
		}

		$categories       = $this->db->get_categories();
		$course_skills    = $course_id ? $this->db->get_course_skills( $course_id ) : array();
		$course_skill_ids = array_column( $course_skills, 'id' );

		echo '<div class="wrap">';
		echo '<h1>' . ( $is_edit ? esc_html__( 'Edit Course', 'zeko-learn' ) : esc_html__( 'Add New Course', 'zeko-learn' ) ) . '</h1>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="zeko_learn_save_course">';
		wp_nonce_field( 'zeko_learn_save_course', 'zeko_learn_admin_nonce' );
		if ( $course_id ) {
			echo '<input type="hidden" name="course_id" value="' . esc_attr( $course_id ) . '">';
		}

		echo '<div class="zeko-learn-form-tabs">';
		echo '<nav class="zeko-learn-tab-nav">';
		echo '<button type="button" class="zeko-tab-btn active" data-tab="basic">' . esc_html__( '1. Basic Info', 'zeko-learn' ) . '</button>';
		echo '<button type="button" class="zeko-tab-btn" data-tab="curriculum">' . esc_html__( '2. Curriculum', 'zeko-learn' ) . '</button>';
		echo '<button type="button" class="zeko-tab-btn" data-tab="pricing">' . esc_html__( '3. Pricing', 'zeko-learn' ) . '</button>';
		echo '<button type="button" class="zeko-tab-btn" data-tab="media">' . esc_html__( '4. Media', 'zeko-learn' ) . '</button>';
		echo '<button type="button" class="zeko-tab-btn" data-tab="seo">' . esc_html__( '5. SEO & Details', 'zeko-learn' ) . '</button>';
		echo '</nav>';

		// Tab 1: Basic Info.
		echo '<div class="zeko-learn-tab-panel active" id="zeko-tab-basic">';
		echo '<table class="form-table">';
		echo '<tr><th><label for="title">' . esc_html__( 'Course Title', 'zeko-learn' ) . '</label></th><td><input type="text" id="title" name="title" class="regular-text" value="' . esc_attr( $course['title'] ?? '' ) . '" required></td></tr>';
		echo '<tr><th><label for="subtitle">' . esc_html__( 'Subtitle', 'zeko-learn' ) . '</label></th><td><input type="text" id="subtitle" name="subtitle" class="regular-text" value="' . esc_attr( $course['subtitle'] ?? '' ) . '"></td></tr>';
		echo '<tr><th><label for="description">' . esc_html__( 'Description', 'zeko-learn' ) . '</label></th><td>';
		wp_editor(
			$course['description'] ?? '',
			'description',
			array(
				'textarea_name' => 'description',
				'textarea_rows' => 8,
			)
		);
		echo '</td></tr>';
		echo '<tr><th><label for="category_id">' . esc_html__( 'Category', 'zeko-learn' ) . '</label></th><td><select id="category_id" name="category_id"><option value="0">' . esc_html__( '&mdash; Select &mdash;', 'zeko-learn' ) . '</option>';
		foreach ( $categories as $cat ) {
			$sel = (int) ( $course['category_id'] ?? 0 ) === (int) $cat['id'] ? 'selected' : '';
			echo '<option value="' . esc_attr( $cat['id'] ) . '" ' . esc_attr( $sel ) . '>' . esc_html( $cat['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="level">' . esc_html__( 'Level', 'zeko-learn' ) . '</label></th><td><select id="level" name="level">';
		foreach ( array( 'beginner', 'intermediate', 'advanced', 'all_levels' ) as $lvl ) {
			$sel = ( $course['level'] ?? 'beginner' ) === $lvl ? 'selected' : '';
			echo '<option value="' . esc_attr( $lvl ) . '" ' . esc_attr( $sel ) . '>' . esc_html( ucfirst( str_replace( '_', ' ', $lvl ) ) ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="language">' . esc_html__( 'Language', 'zeko-learn' ) . '</label></th><td><input type="text" id="language" name="language" value="' . esc_attr( $course['language'] ?? 'en' ) . '" class="small-text"></td></tr>';
		echo '<tr><th><label for="estimated_hours">' . esc_html__( 'Estimated Hours', 'zeko-learn' ) . '</label></th><td><input type="number" id="estimated_hours" name="estimated_hours" step="0.1" min="0" value="' . esc_attr( $course['estimated_hours'] ?? '' ) . '" class="small-text"></td></tr>';
		echo '<tr><th><label for="status">' . esc_html__( 'Status', 'zeko-learn' ) . '</label></th><td><select id="status" name="status">';
		foreach ( array( 'draft', 'pending', 'published', 'archived' ) as $st ) {
			$sel = ( $course['status'] ?? 'draft' ) === $st ? 'selected' : '';
			echo '<option value="' . esc_attr( $st ) . '" ' . esc_attr( $sel ) . '>' . esc_html( ucfirst( $st ) ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Featured', 'zeko-learn' ) . '</label></th><td><label><input type="checkbox" name="is_featured" value="1" ' . checked( $course['is_featured'] ?? 0, 1, false ) . '> ' . esc_html__( 'Mark as featured course', 'zeko-learn' ) . '</label></td></tr>';
		echo '</table>';
		echo '</div>';

		// Tab 2: Curriculum.
		echo '<div class="zeko-learn-tab-panel" id="zeko-tab-curriculum">';
		echo '<p>' . esc_html__( 'Build your course curriculum by adding sections and lessons.', 'zeko-learn' ) . '</p>';
		echo '<div id="zeko-curriculum-builder">';
		if ( $is_edit && ! empty( $sections ) ) {
			$section_idx = 0;
			foreach ( $sections as $section ) {
				$lessons = $this->db->get_section_lessons( (int) $section['id'] );
				echo '<div class="zeko-section" data-section-index="' . esc_attr( $section_idx ) . '">';
				echo '<div class="zeko-section-header">';
				echo '<span class="zeko-drag-handle">&#9776;</span>';
				echo '<input type="hidden" name="sections[' . esc_attr( $section_idx ) . '][id]" value="' . esc_attr( $section['id'] ) . '">';
				echo '<input type="text" name="sections[' . esc_attr( $section_idx ) . '][title]" value="' . esc_attr( $section['title'] ) . '" class="regular-text" placeholder="' . esc_attr__( 'Section Title', 'zeko-learn' ) . '">';
				echo '<input type="text" name="sections[' . esc_attr( $section_idx ) . '][description]" value="' . esc_attr( $section['description'] ?? '' ) . '" class="regular-text" placeholder="' . esc_attr__( 'Section Description (optional)', 'zeko-learn' ) . '">';
				echo '<input type="hidden" name="sections[' . esc_attr( $section_idx ) . '][sort_order]" value="' . esc_attr( $section['sort_order'] ) . '">';
				echo '<button type="button" class="button zeko-remove-section">' . esc_html__( 'Remove', 'zeko-learn' ) . '</button>';
				echo '</div>';
				echo '<div class="zeko-lessons">';
				$lesson_idx = 0;
				foreach ( $lessons as $lesson ) {
					echo '<div class="zeko-lesson">';
					echo '<span class="zeko-drag-handle">&#9776;</span>';
					echo '<input type="hidden" name="sections[' . esc_attr( $section_idx ) . '][lessons][' . esc_attr( $lesson_idx ) . '][id]" value="' . esc_attr( $lesson['id'] ) . '">';
					echo '<input type="text" name="sections[' . esc_attr( $section_idx ) . '][lessons][' . esc_attr( $lesson_idx ) . '][title]" value="' . esc_attr( $lesson['title'] ) . '" class="regular-text" placeholder="' . esc_attr__( 'Lesson Title', 'zeko-learn' ) . '">';
					echo '<select name="sections[' . esc_attr( $section_idx ) . '][lessons][' . esc_attr( $lesson_idx ) . '][lesson_type]">';
					foreach ( array( 'text', 'video', 'quiz', 'assignment' ) as $lt ) {
										$sel = $lesson['lesson_type'] === $lt ? 'selected' : '';
										echo '<option value="' . esc_attr( $lt ) . '" ' . esc_attr( $sel ) . '>' . esc_html( ucfirst( $lt ) ) . '</option>';
					}
					echo '</select>';
					echo '<input type="number" name="sections[' . esc_attr( $section_idx ) . '][lessons][' . esc_attr( $lesson_idx ) . '][estimated_minutes]" value="' . esc_attr( $lesson['estimated_minutes'] ) . '" min="0" class="small-text" placeholder="' . esc_attr__( 'Minutes', 'zeko-learn' ) . '">';
					echo '<label><input type="checkbox" name="sections[' . esc_attr( $section_idx ) . '][lessons][' . esc_attr( $lesson_idx ) . '][is_preview]" value="1" ' . checked( $lesson['is_preview'], 1, false ) . '> ' . esc_html__( 'Preview', 'zeko-learn' ) . '</label>';
					echo '<button type="button" class="button zeko-remove-lesson">' . esc_html__( 'Remove', 'zeko-learn' ) . '</button>';
					echo '</div>';
					++$lesson_idx;
				}
				echo '</div>';
				echo '<button type="button" class="button zeko-add-lesson">' . esc_html__( '+ Add Lesson', 'zeko-learn' ) . '</button>';
				echo '</div>';
				++$section_idx;
			}
		} else {
			echo '<div class="zeko-section" data-section-index="0">';
			echo '<div class="zeko-section-header">';
			echo '<span class="zeko-drag-handle">&#9776;</span>';
			echo '<input type="hidden" name="sections[0][id]" value="0">';
			echo '<input type="text" name="sections[0][title]" value="" class="regular-text" placeholder="' . esc_attr__( 'Section Title', 'zeko-learn' ) . '">';
			echo '<input type="text" name="sections[0][description]" value="" class="regular-text" placeholder="' . esc_attr__( 'Section Description (optional)', 'zeko-learn' ) . '">';
			echo '<input type="hidden" name="sections[0][sort_order]" value="0">';
			echo '</div>';
			echo '<div class="zeko-lessons">';
			echo '<div class="zeko-lesson">';
			echo '<span class="zeko-drag-handle">&#9776;</span>';
			echo '<input type="hidden" name="sections[0][lessons][0][id]" value="0">';
			echo '<input type="text" name="sections[0][lessons][0][title]" value="" class="regular-text" placeholder="' . esc_attr__( 'Lesson Title', 'zeko-learn' ) . '">';
			echo '<select name="sections[0][lessons][0][lesson_type]">';
			echo '<option value="text">' . esc_html__( 'Text', 'zeko-learn' ) . '</option>';
			echo '<option value="video">' . esc_html__( 'Video', 'zeko-learn' ) . '</option>';
			echo '<option value="quiz">' . esc_html__( 'Quiz', 'zeko-learn' ) . '</option>';
			echo '<option value="assignment">' . esc_html__( 'Assignment', 'zeko-learn' ) . '</option>';
			echo '</select>';
			echo '<input type="number" name="sections[0][lessons][0][estimated_minutes]" value="" min="0" class="small-text" placeholder="' . esc_attr__( 'Minutes', 'zeko-learn' ) . '">';
			echo '<label><input type="checkbox" name="sections[0][lessons][0][is_preview]" value="1"> ' . esc_html__( 'Preview', 'zeko-learn' ) . '</label>';
			echo '</div>';
			echo '</div>';
			echo '<button type="button" class="button zeko-add-lesson">' . esc_html__( '+ Add Lesson', 'zeko-learn' ) . '</button>';
			echo '</div>';
		}
		echo '</div>';
		echo '<button type="button" class="button button-primary" id="zeko-add-section">' . esc_html__( '+ Add Section', 'zeko-learn' ) . '</button>';
		echo '</div>';

		// Tab 3: Pricing.
		echo '<div class="zeko-learn-tab-panel" id="zeko-tab-pricing">';
		echo '<table class="form-table">';
		echo '<tr><th><label for="is_free">' . esc_html__( 'Free Course', 'zeko-learn' ) . '</label></th><td><label><input type="checkbox" id="is_free" name="is_free" value="1" ' . checked( $course['is_free'] ?? 0, 1, false ) . '> ' . esc_html__( 'This is a free course', 'zeko-learn' ) . '</label></td></tr>';
		echo '<tr><th><label for="price">' . esc_html__( 'Price', 'zeko-learn' ) . '</label></th><td><input type="number" id="price" name="price" step="0.01" min="0" value="' . esc_attr( $course['price'] ?? '0' ) . '" class="small-text"> ' . esc_html( get_option( 'zeko_learn_currency_symbol', '$' ) ) . '</td></tr>';
		echo '<tr><th><label for="sale_price">' . esc_html__( 'Sale Price', 'zeko-learn' ) . '</label></th><td><input type="number" id="sale_price" name="sale_price" step="0.01" min="0" value="' . esc_attr( $course['sale_price'] ?? '' ) . '" class="small-text"></td></tr>';
		echo '</table>';
		echo '</div>';

		// Tab 4: Media.
		echo '<div class="zeko-learn-tab-panel" id="zeko-tab-media">';
		echo '<table class="form-table">';
		echo '<tr><th>' . esc_html__( 'Thumbnail', 'zeko-learn' ) . '</th><td>';
		$thumb_url = ! empty( $course['thumbnail_id'] ) ? wp_get_attachment_url( $course['thumbnail_id'] ) : '';
		echo '<div id="zeko-thumbnail-preview">';
		if ( $thumb_url ) {
			echo '<img src="' . esc_url( $thumb_url ) . '" style="max-width:300px;">';
		}
		echo '</div>';
		echo '<input type="hidden" name="thumbnail_id" id="thumbnail_id" value="' . esc_attr( $course['thumbnail_id'] ?? '' ) . '">';
		echo '<button type="button" class="button" id="zeko-upload-thumbnail">' . esc_html__( 'Upload Thumbnail', 'zeko-learn' ) . '</button>';
		echo '</td></tr>';
		echo '<tr><th><label for="promo_video_url">' . esc_html__( 'Promo Video URL', 'zeko-learn' ) . '</label></th><td><input type="url" id="promo_video_url" name="promo_video_url" class="regular-text" value="' . esc_attr( $course['promo_video_url'] ?? '' ) . '"></td></tr>';
		echo '</table>';
		echo '</div>';

		// Tab 5: SEO & Details.
		echo '<div class="zeko-learn-tab-panel" id="zeko-tab-seo">';
		echo '<table class="form-table">';
		echo '<tr><th><label for="seo_title">' . esc_html__( 'SEO Title', 'zeko-learn' ) . '</label></th><td><input type="text" id="seo_title" name="seo_title" class="regular-text" value="' . esc_attr( $course['seo_title'] ?? '' ) . '"></td></tr>';
		echo '<tr><th><label for="seo_description">' . esc_html__( 'SEO Description', 'zeko-learn' ) . '</label></th><td><textarea id="seo_description" name="seo_description" class="large-text" rows="3">' . esc_textarea( $course['seo_description'] ?? '' ) . '</textarea></td></tr>';
		echo '<tr><th><label for="what_you_learn">' . esc_html__( 'What You\'ll Learn', 'zeko-learn' ) . '</label></th><td><textarea id="what_you_learn" name="what_you_learn" class="large-text" rows="5">' . esc_textarea( $course['what_you_learn'] ?? '' ) . '</textarea></td></tr>';
		echo '<tr><th><label for="requirements">' . esc_html__( 'Requirements', 'zeko-learn' ) . '</label></th><td><textarea id="requirements" name="requirements" class="large-text" rows="3">' . esc_textarea( $course['requirements'] ?? '' ) . '</textarea></td></tr>';
		echo '<tr><th><label for="target_audience">' . esc_html__( 'Target Audience', 'zeko-learn' ) . '</label></th><td><textarea id="target_audience" name="target_audience" class="large-text" rows="3">' . esc_textarea( $course['target_audience'] ?? '' ) . '</textarea></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Skills', 'zeko-learn' ) . '</label></th><td>';
		echo '<input type="text" id="zeko-skill-search" placeholder="' . esc_attr__( 'Search skills...', 'zeko-learn' ) . '" class="regular-text">';
		echo '<div id="zeko-course-skills">';
		foreach ( $course_skills as $skill ) {
			echo '<span class="zeko-skill-tag" data-skill-id="' . esc_attr( $skill['id'] ) . '">' . esc_html( $skill['name'] ) . ' <button type="button" class="zeko-remove-skill">&times;</button></span>';
		}
		echo '</div>';
		echo '<input type="hidden" name="skill_ids" id="skill_ids" value="' . esc_attr( implode( ',', $course_skill_ids ) ) . '">';
		echo '<div id="zeko-skill-suggestions"></div>';
		echo '</td></tr>';
		echo '</table>';
		echo '</div>';

		echo '</div>'; // end tabs.

		echo '<p class="submit">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save Course', 'zeko-learn' ) . '</button>';
		echo '</p>';

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render edit course.
	 */
	public function render_edit_course(): void {
		$this->render_add_course();
	}

	// ─── Categories ─────────────────────────────────────────────.

	/**
	 * Render categories.
	 */
	public function render_categories(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		if ( isset( $_GET['message'] ) && 'updated' === $_GET['message'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag; compared against fixed literal.
			$this->admin_notices( 'success', __( 'Category saved.', 'zeko-learn' ) );
		}
		if ( isset( $_GET['message'] ) && 'deleted' === $_GET['message'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag; compared against fixed literal.
			$this->admin_notices( 'success', __( 'Category deleted.', 'zeko-learn' ) );
		}

		$categories = $this->db->get_categories();
		$edit_id    = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit flag; absint() coerced.
		$edit_cat   = $edit_id ? $this->db->get_category( $edit_id ) : array();
		$form_title = $edit_id ? __( 'Edit Category', 'zeko-learn' ) : __( 'Add Category', 'zeko-learn' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Categories', 'zeko-learn' ) . '</h1>';

		echo '<div class="zeko-learn-two-col">';
		echo '<div class="zeko-learn-col-main">';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Slug', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Description', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Icon', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'zeko-learn' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		if ( empty( $categories ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No categories yet.', 'zeko-learn' ) . '</td></tr>';
		} else {
			foreach ( $categories as $cat ) {
				$edit_url   = admin_url( 'admin.php?page=zeko-learn-categories&edit=' . $cat['id'] );
				$delete_url = wp_nonce_url(
					admin_url( 'admin.php?page=zeko-learn-categories&action=delete&id=' . $cat['id'] ),
					'zeko_learn_delete_category_' . $cat['id']
				);
				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $cat['name'] ) . '</a></strong></td>';
				echo '<td>' . esc_html( $cat['slug'] ) . '</td>';
				echo '<td>' . esc_html( wp_trim_words( $cat['description'] ?? '', 10 ) ) . '</td>';
				echo '<td>' . esc_html( $cat['icon'] ) . '</td>';
				echo '<td>';
				echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( 'Edit', 'zeko-learn' ) . '</a> ';
				echo '<a href="' . esc_url( $delete_url ) . '" class="button button-small zeko-delete-category">' . esc_html__( 'Delete', 'zeko-learn' ) . '</a>';
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';

		echo '<div class="zeko-learn-col-side">';
		echo '<div class="zeko-learn-side-panel">';
		echo '<h2>' . esc_html( $form_title ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="zeko_learn_save_category">';
		wp_nonce_field( 'zeko_learn_save_category', 'zeko_learn_admin_nonce' );
		if ( $edit_id ) {
			echo '<input type="hidden" name="category_id" value="' . esc_attr( $edit_id ) . '">';
		}
		echo '<table class="form-table">';
		echo '<tr><th><label for="cat_name">' . esc_html__( 'Name', 'zeko-learn' ) . '</label></th><td><input type="text" id="cat_name" name="name" class="regular-text" value="' . esc_attr( $edit_cat['name'] ?? '' ) . '" required></td></tr>';
		echo '<tr><th><label for="cat_slug">' . esc_html__( 'Slug', 'zeko-learn' ) . '</label></th><td><input type="text" id="cat_slug" name="slug" class="regular-text" value="' . esc_attr( $edit_cat['slug'] ?? '' ) . '"></td></tr>';
		echo '<tr><th><label for="cat_desc">' . esc_html__( 'Description', 'zeko-learn' ) . '</label></th><td><textarea id="cat_desc" name="description" class="large-text" rows="3">' . esc_textarea( $edit_cat['description'] ?? '' ) . '</textarea></td></tr>';
		echo '<tr><th><label for="cat_icon">' . esc_html__( 'Icon', 'zeko-learn' ) . '</label></th><td><input type="text" id="cat_icon" name="icon" class="regular-text" value="' . esc_attr( $edit_cat['icon'] ?? '' ) . '" placeholder="' . esc_attr__( 'dashicons-book', 'zeko-learn' ) . '"></td></tr>';
		echo '<tr><th><label for="cat_sort">' . esc_html__( 'Sort Order', 'zeko-learn' ) . '</label></th><td><input type="number" id="cat_sort" name="sort_order" value="' . esc_attr( $edit_cat['sort_order'] ?? 0 ) . '" class="small-text"></td></tr>';
		echo '</table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html( $edit_id ? __( 'Update Category', 'zeko-learn' ) : __( 'Add Category', 'zeko-learn' ) ) . '</button></p>';
		echo '</form>';
		echo '</div></div>';

		echo '</div>'; // two-col.
		echo '</div>';
	}

	// ─── Enrollments ────────────────────────────────────────────.

	/**
	 * Render enrollments.
	 */
	public function render_enrollments(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		global $wpdb;

		$per_page      = 20;
		$current_page  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination flag; absint() coerced.
		$offset        = ( $current_page - 1 ) * $per_page;
		$filter_course = isset( $_GET['filter_course'] ) ? absint( wp_unslash( $_GET['filter_course'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter; absint() coerced.

		$table_enrollments = $wpdb->prefix . 'zeko_enrollments';
		$table_courses     = $wpdb->prefix . 'zeko_courses';

		$where  = '';
		$params = array();
		if ( $filter_course > 0 ) {
			$where    = 'WHERE e.course_id = %d';
			$params[] = $filter_course;
		}

		$count_query = "SELECT COUNT(*) FROM {$table_enrollments} e " . $where;
		$total       = $filter_course > 0 ? (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$params ) ) : (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$pages       = ceil( $total / $per_page );

		$data_query = "SELECT e.*, c.title AS course_title, u.display_name AS student_name, u.user_email AS student_email
			FROM {$table_enrollments} e
			INNER JOIN {$table_courses} c ON e.course_id = c.id
			INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
			{$where}
			ORDER BY e.enrolled_at DESC
			LIMIT %d OFFSET %d";
		$params[]   = $per_page;
		$params[]   = $offset;

		$enrollments = $wpdb->get_results( $wpdb->prepare( $data_query, ...$params ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$all_courses = $this->db->get_courses(
			array(
				'status'  => 'published',
				'limit'   => 200,
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Enrollments', 'zeko-learn' ) . '</h1>';

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="zeko-learn-enrollments">';
		echo '<div class="tablenav top">';
		echo '<div class="alignleft actions">';
		echo '<select name="filter_course" onchange="this.form.submit()">';
		echo '<option value="0">' . esc_html__( 'All Courses', 'zeko-learn' ) . '</option>';
		foreach ( $all_courses as $c ) {
			$sel = $filter_course === (int) $c['id'] ? 'selected' : '';
			echo '<option value="' . esc_attr( $c['id'] ) . '" ' . esc_attr( $sel ) . '>' . esc_html( $c['title'] ) . '</option>';
		}
		echo '</select>';
		echo '</div></div>';
		echo '</form>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Student', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Course', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Enrolled', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Completed', 'zeko-learn' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		if ( empty( $enrollments ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No enrollments yet.', 'zeko-learn' ) . '</td></tr>';
		} else {
			foreach ( $enrollments as $e ) {
				echo '<tr>';
				echo '<td>' . esc_html( $e['student_name'] ) . '</td>';
				echo '<td>' . esc_html( $e['student_email'] ) . '</td>';
				echo '<td>' . esc_html( $e['course_title'] ) . '</td>';
				echo '<td><span class="zeko-status-badge zeko-status-' . esc_attr( $e['status'] ) . '">' . esc_html( $e['status'] ) . '</span></td>';
				echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ), $e['enrolled_at'] ) ) . '</td>';
				echo '<td>' . ( ! empty( $e['completed_at'] ) ? esc_html( mysql2date( get_option( 'date_format' ), $e['completed_at'] ) ) : '&mdash;' ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		if ( $pages > 1 ) {
			echo '<div class="tablenav bottom"><div class="tablenav-pages">';
			echo '<span class="displaying-num">' . esc_html( $total ) . ' items</span>';
			echo '<span class="pagination-links">';
			if ( $current_page > 1 ) {
				$args = array( 'paged' => $current_page - 1 );
				if ( $filter_course > 0 ) {
					$args['filter_course'] = $filter_course;
				}
				echo '<a class="prev-page button" href="' . esc_url( add_query_arg( $args, admin_url( 'admin.php?page=zeko-learn-enrollments' ) ) ) . '">&lsaquo;</a>';
			}
			echo '<span class="paging-input">' . esc_html( $current_page ) . ' of <span class="total-pages">' . esc_html( $pages ) . '</span></span>';
			if ( $current_page < $pages ) {
				$args = array( 'paged' => $current_page + 1 );
				if ( $filter_course > 0 ) {
					$args['filter_course'] = $filter_course;
				}
				echo '<a class="next-page button" href="' . esc_url( add_query_arg( $args, admin_url( 'admin.php?page=zeko-learn-enrollments' ) ) ) . '">&rsaquo;</a>';
			}
			echo '</span></div></div>';
		}

		echo '</div>';
	}

	// ─── Certificates ───────────────────────────────────────────.

	/**
	 * Render certificates.
	 */
	public function render_certificates(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		global $wpdb;

		$table_certificates = $wpdb->prefix . 'zeko_certificates';
		$table_courses      = $wpdb->prefix . 'zeko_courses';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$certificates = $wpdb->get_results(
			"SELECT cert.*, c.title AS course_title, u.display_name AS student_name
			FROM {$table_certificates} cert
			INNER JOIN {$table_courses} c ON cert.course_id = c.id
			INNER JOIN {$wpdb->users} u ON cert.user_id = u.ID
			ORDER BY cert.issued_at DESC
			LIMIT 50",
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Certificates', 'zeko-learn' ) . '</h1>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Student', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Course', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Certificate #', 'zeko-learn' ) . '</th>';
		echo '<th>' . esc_html__( 'Issued', 'zeko-learn' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		if ( empty( $certificates ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No certificates issued yet.', 'zeko-learn' ) . '</td></tr>';
		} else {
			foreach ( $certificates as $cert ) {
				echo '<tr>';
				echo '<td>' . esc_html( $cert['student_name'] ) . '</td>';
				echo '<td>' . esc_html( $cert['course_title'] ) . '</td>';
				echo '<td><code>' . esc_html( $cert['certificate_number'] ) . '</code></td>';
				echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ), $cert['issued_at'] ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	// ─── Settings ───────────────────────────────────────────────.

	/**
	 * Render settings.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Settings', 'zeko-learn' ) . '</h1>';

		if ( isset( $_GET['message'] ) && 'updated' === $_GET['message'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag; compared against fixed literal.
			$this->admin_notices( 'success', __( 'Settings saved.', 'zeko-learn' ) );
		}

		echo '<form method="post" action="options.php">';
		settings_fields( 'zeko_learn_settings' );
		echo '<table class="form-table">';
		echo '<tr><th><label for="currency_symbol">' . esc_html__( 'Currency Symbol', 'zeko-learn' ) . '</label></th><td><input type="text" id="currency_symbol" name="zeko_learn_currency_symbol" value="' . esc_attr( get_option( 'zeko_learn_currency_symbol', '$' ) ) . '" class="small-text"></td></tr>';
		echo '<tr><th><label for="passing_score">' . esc_html__( 'Default Passing Score (%)', 'zeko-learn' ) . '</label></th><td><input type="number" id="passing_score" name="zeko_learn_passing_score" value="' . esc_attr( get_option( 'zeko_learn_passing_score', '60' ) ) . '" min="0" max="100" class="small-text"></td></tr>';
		echo '<tr><th><label for="instructor_pct">' . esc_html__( 'Instructor Revenue %', 'zeko-learn' ) . '</label></th><td><input type="number" id="instructor_pct" name="zeko_learn_instructor_revenue_pct" value="' . esc_attr( get_option( 'zeko_learn_instructor_revenue_pct', '70' ) ) . '" min="0" max="100" class="small-text"></td></tr>';
		echo '<tr><th><label for="cert_prefix">' . esc_html__( 'Certificate Number Prefix', 'zeko-learn' ) . '</label></th><td><input type="text" id="cert_prefix" name="zeko_learn_certificate_prefix" value="' . esc_attr( get_option( 'zeko_learn_certificate_prefix', 'ZL' ) ) . '" class="regular-text"></td></tr>';
		if ( function_exists( 'zeko_learn_embed_settings_fields' ) ) {
			zeko_learn_embed_settings_fields();
		}
		echo '</table>';
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	// ─── Form Handlers ──────────────────────────────────────────.

	/**
	 * Handle save course.
	 */
	public function handle_save_course(): void {
		if ( ! isset( $_POST['action'] ) || 'zeko_learn_save_course' !== $_POST['action'] ) {
			return;
		}
		if ( ! isset( $_POST['zeko_learn_admin_nonce'] ) || ! wp_verify_nonce( (string) wp_unslash( $_POST['zeko_learn_admin_nonce'] ), 'zeko_learn_save_course' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is a cryptographic token; sanitizing it would corrupt the verification.
			wp_die( esc_html__( 'Security check failed.', 'zeko-learn' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		$course_id = isset( $_POST['course_id'] ) ? absint( wp_unslash( $_POST['course_id'] ) ) : 0;

		$data = array(
			'instructor_id'   => get_current_user_id(),
			'title'           => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'subtitle'        => sanitize_text_field( wp_unslash( $_POST['subtitle'] ?? '' ) ),
			'description'     => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'category_id'     => absint( wp_unslash( $_POST['category_id'] ?? 0 ) ),
			'level'           => sanitize_text_field( wp_unslash( $_POST['level'] ?? 'beginner' ) ),
			'language'        => sanitize_text_field( wp_unslash( $_POST['language'] ?? 'en' ) ),
			'estimated_hours' => (float) sanitize_text_field( wp_unslash( $_POST['estimated_hours'] ?? 0 ) ),
			'status'          => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'draft' ) ),
			'is_featured'     => ! empty( $_POST['is_featured'] ) ? 1 : 0,
			'is_free'         => ! empty( $_POST['is_free'] ) ? 1 : 0,
			'price'           => (float) sanitize_text_field( wp_unslash( $_POST['price'] ?? 0 ) ),
			'sale_price'      => ! empty( $_POST['sale_price'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['sale_price'] ) ) : null,
			'thumbnail_id'    => absint( wp_unslash( $_POST['thumbnail_id'] ?? 0 ) ),
			'promo_video_url' => esc_url_raw( wp_unslash( $_POST['promo_video_url'] ?? '' ) ),
			'seo_title'       => sanitize_text_field( wp_unslash( $_POST['seo_title'] ?? '' ) ),
			'seo_description' => sanitize_text_field( wp_unslash( $_POST['seo_description'] ?? '' ) ),
			'what_you_learn'  => wp_kses_post( wp_unslash( $_POST['what_you_learn'] ?? '' ) ),
			'requirements'    => wp_kses_post( wp_unslash( $_POST['requirements'] ?? '' ) ),
			'target_audience' => wp_kses_post( wp_unslash( $_POST['target_audience'] ?? '' ) ),
		);

		if ( $course_id ) {
			$this->db->update_course( $course_id, $data );
		} else {
			$course_id = $this->db->insert_course( $data );
		}

		// Save sections and lessons.
		if ( ! empty( $_POST['sections'] ) && is_array( $_POST['sections'] ) ) {
			$existing_section_ids  = array_column( $this->db->get_course_sections( $course_id ), 'id' );
			$submitted_section_ids = array();

			foreach ( (array) wp_unslash( $_POST['sections'] ) as $idx => $section_data ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nested form data; each element is individually sanitized with sanitize_text_field/wp_kses_post/absint below.
				$section_title = sanitize_text_field( wp_unslash( $section_data['title'] ?? '' ) );
				if ( empty( $section_title ) ) {
					continue;
				}

				$sid             = absint( $section_data['id'] ?? 0 );
				$section_payload = array(
					'course_id'   => $course_id,
					'title'       => $section_title,
					'description' => sanitize_textarea_field( wp_unslash( $section_data['description'] ?? '' ) ),
					'sort_order'  => absint( $idx ),
				);

				if ( $sid > 0 ) {
					$this->db->update_section( $sid, $section_payload );
					$submitted_section_ids[] = $sid;
				} else {
					$sid                     = $this->db->insert_section( $section_payload );
					$submitted_section_ids[] = $sid;
				}

				// Lessons within section.
				if ( ! empty( $section_data['lessons'] ) && is_array( $section_data['lessons'] ) ) {
					$existing_lesson_ids = array();
					foreach ( $this->db->get_section_lessons( $sid ) as $l ) {
						$existing_lesson_ids[] = (int) $l['id'];
					}
					$submitted_lesson_ids = array();

					foreach ( $section_data['lessons'] as $lidx => $lesson_data ) {
						$lesson_title = sanitize_text_field( wp_unslash( $lesson_data['title'] ?? '' ) );
						if ( empty( $lesson_title ) ) {
							continue;
						}

						$lid            = absint( $lesson_data['id'] ?? 0 );
						$lesson_payload = array(
							'section_id'        => $sid,
							'course_id'         => $course_id,
							'title'             => $lesson_title,
							'lesson_type'       => sanitize_text_field( $lesson_data['lesson_type'] ?? 'text' ),
							'estimated_minutes' => absint( $lesson_data['estimated_minutes'] ?? 0 ),
							'is_preview'        => ! empty( $lesson_data['is_preview'] ) ? 1 : 0,
							'sort_order'        => absint( $lidx ),
						);

						if ( $lid > 0 ) {
							$this->db->update_lesson( $lid, $lesson_payload );
							$submitted_lesson_ids[] = $lid;
						} else {
							$lid                    = $this->db->insert_lesson( $lesson_payload );
							$submitted_lesson_ids[] = $lid;
						}
					}

					// Delete removed lessons.
					$removed_lessons = array_diff( $existing_lesson_ids, $submitted_lesson_ids );
					foreach ( $removed_lessons as $removed_id ) {
						$this->db->delete_lesson( $removed_id );
					}
				}
			}

			// Delete removed sections.
			$removed_sections = array_diff( $existing_section_ids, $submitted_section_ids );
			foreach ( $removed_sections as $removed_id ) {
				$this->db->delete_section( $removed_id );
			}
		}

		// Save skills.
		$skill_ids       = isset( $_POST['skill_ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['skill_ids'] ) ) ) ) ) : array();
		$existing_skills = array_column( $this->db->get_course_skills( $course_id ), 'id' );
		foreach ( array_diff( $existing_skills, $skill_ids ) as $remove_id ) {
			$this->db->detach_skill_from_course( $course_id, $remove_id );
		}
		foreach ( array_diff( $skill_ids, $existing_skills ) as $add_id ) {
			$this->db->attach_skill_to_course( $course_id, $add_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=zeko-learn-courses&message=updated' ) );
		exit;
	}

	/**
	 * Handle save category.
	 */
	public function handle_save_category(): void {
		if ( ! isset( $_POST['action'] ) || 'zeko_learn_save_category' !== $_POST['action'] ) {
			return;
		}
		if ( ! isset( $_POST['zeko_learn_admin_nonce'] ) || ! wp_verify_nonce( (string) wp_unslash( $_POST['zeko_learn_admin_nonce'] ), 'zeko_learn_save_category' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is a cryptographic token; sanitizing it would corrupt the verification.
			wp_die( esc_html__( 'Security check failed.', 'zeko-learn' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		$category_id = isset( $_POST['category_id'] ) ? absint( wp_unslash( $_POST['category_id'] ) ) : 0;

		$data = array(
			'name'        => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'slug'        => sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) ),
			'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'icon'        => sanitize_text_field( wp_unslash( $_POST['icon'] ?? '' ) ),
			'sort_order'  => absint( wp_unslash( $_POST['sort_order'] ?? 0 ) ),
		);

		if ( $category_id ) {
			$this->db->update_category( $category_id, $data );
		} else {
			$this->db->insert_category( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=zeko-learn-categories&message=updated' ) );
		exit;
	}

	/**
	 * Handle delete course.
	 */
	public function handle_delete_course(): void {
		if ( ! isset( $_GET['action'] ) || 'delete' !== $_GET['action'] || ! isset( $_GET['course_id'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		$course_id = absint( $_GET['course_id'] );
		$nonce     = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'zeko_learn_delete_course_' . $course_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'zeko-learn' ) );
		}

		// Delete sections and lessons first.
		$sections = $this->db->get_course_sections( $course_id );
		foreach ( $sections as $section ) {
			$lessons = $this->db->get_section_lessons( (int) $section['id'] );
			foreach ( $lessons as $lesson ) {
				$this->db->delete_lesson( (int) $lesson['id'] );
			}
			$this->db->delete_section( (int) $section['id'] );
		}

		$this->db->delete_course( $course_id );

		wp_safe_redirect( admin_url( 'admin.php?page=zeko-learn-courses&message=deleted' ) );
		exit;
	}

	// ─── Quizzes ───────────────────────────────────────────────.

	/**
	 * Render quizzes.
	 */
	public function render_quizzes(): void {
		if ( isset( $_GET['message'] ) && 'deleted' === $_GET['message'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag; compared against fixed literal.
			$this->admin_notices( 'success', __( 'Quiz deleted.', 'zeko-learn' ) );
		}
		if ( isset( $_GET['message'] ) && 'updated' === $_GET['message'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag; compared against fixed literal.
			$this->admin_notices( 'success', __( 'Quiz saved.', 'zeko-learn' ) );
		}

		global $wpdb;
		$tq = $wpdb->prefix . 'zeko_quizzes';
		$tc = $wpdb->prefix . 'zeko_courses';

		if ( isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit flag; absint() coerced below.
			$this->render_quiz_editor( absint( wp_unslash( $_GET['edit'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit flag; absint() coerced.
			return;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$quizzes = $wpdb->get_results(
			"SELECT q.*, c.title AS course_title
			FROM {$tq} q
			LEFT JOIN {$tc} c ON q.course_id = c.id
			ORDER BY q.created_at DESC",
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Quizzes', 'zeko-learn' ) . '</h1>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . esc_html__( 'Title', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Course', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Passing Score', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Time Limit', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Actions', 'zeko-learn' ) . '</th></tr></thead>';
		echo '<tbody>';
		if ( empty( $quizzes ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No quizzes yet. Create quizzes from the course editor.', 'zeko-learn' ) . '</td></tr>';
		} else {
			foreach ( $quizzes as $q ) {
				$edit_url   = admin_url( 'admin.php?page=zeko-learn-quizzes&edit=' . $q['id'] );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=zeko-learn-quizzes&action=delete&id=' . $q['id'] ), 'zeko_learn_delete_quiz_' . $q['id'] );
				echo '<tr>';
				echo '<td><a href="' . esc_url( $edit_url ) . '"><strong>' . esc_html( $q['title'] ) . '</strong></a></td>';
				echo '<td>' . esc_html( $q['course_title'] ?? '—' ) . '</td>';
				echo '<td>' . esc_html( $q['passing_score'] ) . '%</td>';
				echo '<td>' . esc_html( $q['time_limit_minutes'] ? $q['time_limit_minutes'] . ' min' : '—' ) . '</td>';
				echo '<td><a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( 'Edit', 'zeko-learn' ) . '</a> <a href="' . esc_url( $delete_url ) . '" class="button button-small">' . esc_html__( 'Delete', 'zeko-learn' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render quiz editor.
	 *
	 * @param int $quiz_id Quiz id.
	 */
	private function render_quiz_editor( int $quiz_id ): void {
		$quiz      = $quiz_id ? $this->db->get_quiz( $quiz_id ) : null;
		$questions = $quiz_id ? $this->db->get_quiz_questions( $quiz_id ) : array();
		$is_edit   = ! empty( $quiz );

		echo '<div class="wrap">';
		echo '<h1>' . ( $is_edit ? esc_html__( 'Edit Quiz', 'zeko-learn' ) : esc_html__( 'Add Quiz', 'zeko-learn' ) ) . '</h1>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="zeko_learn_save_quiz">';
		wp_nonce_field( 'zeko_learn_save_quiz', 'zeko_learn_admin_nonce' );
		if ( $quiz_id ) {
			echo '<input type="hidden" name="quiz_id" value="' . esc_attr( $quiz_id ) . '">';
		}

		echo '<table class="form-table">';
		echo '<tr><th><label for="q_title">' . esc_html__( 'Quiz Title', 'zeko-learn' ) . '</label></th><td><input type="text" id="q_title" name="title" class="regular-text" value="' . esc_attr( $quiz['title'] ?? '' ) . '" required></td></tr>';
		echo '<tr><th><label for="q_desc">' . esc_html__( 'Description', 'zeko-learn' ) . '</label></th><td><textarea id="q_desc" name="description" class="large-text" rows="3">' . esc_textarea( $quiz['description'] ?? '' ) . '</textarea></td></tr>';
		echo '<tr><th><label for="q_time">' . esc_html__( 'Time Limit (minutes)', 'zeko-learn' ) . '</label></th><td><input type="number" id="q_time" name="time_limit_minutes" min="0" value="' . esc_attr( $quiz['time_limit_minutes'] ?? 0 ) . '" class="small-text"> ' . esc_html__( '(0 = no limit)', 'zeko-learn' ) . '</td></tr>';
		echo '<tr><th><label for="q_pass">' . esc_html__( 'Passing Score (%)', 'zeko-learn' ) . '</label></th><td><input type="number" id="q_pass" name="passing_score" min="0" max="100" value="' . esc_attr( $quiz['passing_score'] ?? get_option( 'zeko_learn_passing_score', '60' ) ) . '" class="small-text"></td></tr>';
		echo '<tr><th><label for="q_attempts">' . esc_html__( 'Max Attempts', 'zeko-learn' ) . '</label></th><td><input type="number" id="q_attempts" name="max_attempts" min="0" value="' . esc_attr( $quiz['max_attempts'] ?? 0 ) . '" class="small-text"> ' . esc_html__( '(0 = unlimited)', 'zeko-learn' ) . '</td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Shuffle Questions', 'zeko-learn' ) . '</label></th><td><label><input type="checkbox" name="shuffle_questions" value="1" ' . checked( $quiz['shuffle_questions'] ?? 0, 1, false ) . '> ' . esc_html__( 'Randomize question order', 'zeko-learn' ) . '</label></td></tr>';
		echo '<tr><th><label for="q_show">' . esc_html__( 'Show Answers', 'zeko-learn' ) . '</label></th><td><select id="q_show" name="show_answers">';
		foreach ( array(
			'on_complete'    => 'After Submission',
			'never'          => 'Never',
			'after_deadline' => 'After Deadline',
		) as $val => $lbl ) {
			$sel = ( $quiz['show_answers'] ?? 'on_complete' ) === $val ? 'selected' : '';
			echo '<option value="' . esc_attr( $val ) . '" ' . esc_attr( $sel ) . '>' . esc_html( $lbl ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '</table>';

		// Questions.
		echo '<h2>' . esc_html__( 'Questions', 'zeko-learn' ) . '</h2>';
		echo '<div id="zeko-questions-builder">';

		if ( ! empty( $questions ) ) {
			$q_idx = 0;
			foreach ( $questions as $q ) {
				$options = json_decode( $q['options'], true ) ?: array();
				echo '<div class="zeko-question" data-q-idx="' . esc_attr( $q_idx ) . '">';
				echo '<div class="zeko-question-header">';
				echo '<span class="zeko-drag-handle">&#9776;</span>';
				echo '<input type="hidden" name="questions[' . esc_attr( $q_idx ) . '][id]" value="' . esc_attr( $q['id'] ) . '">';
				echo '<input type="text" name="questions[' . esc_attr( $q_idx ) . '][question_text]" value="' . esc_attr( $q['question_text'] ) . '" class="regular-text" placeholder="' . esc_attr__( 'Question text...', 'zeko-learn' ) . '">';
				echo '<select name="questions[' . esc_attr( $q_idx ) . '][question_type]">';
				foreach ( array(
					'single_choice'   => 'Single Choice',
					'multiple_choice' => 'Multiple Choice',
					'true_false'      => 'True/False',
					'fill_blank'      => 'Fill in Blank',
				) as $tval => $tlbl ) {
					$tsel = $q['question_type'] === $tval ? 'selected' : '';
					echo '<option value="' . esc_attr( $tval ) . '" ' . esc_attr( $tsel ) . '>' . esc_html( $tlbl ) . '</option>';
				}
				echo '</select>';
				echo '<input type="number" name="questions[' . esc_attr( $q_idx ) . '][points]" value="' . esc_attr( $q['points'] ) . '" min="1" class="small-text" placeholder="' . esc_attr__( 'Points', 'zeko-learn' ) . '">';
				echo '<button type="button" class="button zeko-remove-question">' . esc_html__( 'Remove', 'zeko-learn' ) . '</button>';
				echo '</div>';

				echo '<div class="zeko-question-options">';
				echo '<label>' . esc_html__( 'Options (one per line, first = correct):', 'zeko-learn' ) . '</label>';
				echo '<textarea name="questions[' . esc_attr( $q_idx ) . '][options]" rows="4" class="large-text">' . esc_textarea( implode( "\n", $options ) ) . '</textarea>';
				echo '</div>';

				echo '<div class="zeko-question-explanation">';
				echo '<label>' . esc_html__( 'Explanation (shown after quiz):', 'zeko-learn' ) . '</label>';
				echo '<textarea name="questions[' . esc_attr( $q_idx ) . '][explanation]" rows="2" class="large-text">' . esc_textarea( $q['explanation'] ?? '' ) . '</textarea>';
				echo '</div>';

				echo '<div class="zeko-question-correct">';
				echo '<label>' . esc_html__( 'Correct Answer:', 'zeko-learn' ) . '</label>';
				echo '<input type="text" name="questions[' . esc_attr( $q_idx ) . '][correct_answer]" value="' . esc_attr( $q['correct_answer'] ) . '" class="regular-text">';
				echo '</div>';
				echo '</div>';
				++$q_idx;
			}
		}

		echo '</div>';
		echo '<button type="button" class="button button-primary" id="zeko-add-question">' . esc_html__( '+ Add Question', 'zeko-learn' ) . '</button>';

		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save Quiz', 'zeko-learn' ) . '</button></p>';
		echo '</form></div>';
	}

	/**
	 * Handle save quiz.
	 */
	public function handle_save_quiz(): void {
		if ( ! isset( $_POST['action'] ) || 'zeko_learn_save_quiz' !== $_POST['action'] ) {
			return;
		}
		if ( ! isset( $_POST['zeko_learn_admin_nonce'] ) || ! wp_verify_nonce( (string) wp_unslash( $_POST['zeko_learn_admin_nonce'] ), 'zeko_learn_save_quiz' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is a cryptographic token; sanitizing it would corrupt the verification.
			wp_die( esc_html__( 'Security check failed.', 'zeko-learn' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;

		$data = array(
			'title'              => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description'        => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'time_limit_minutes' => absint( wp_unslash( $_POST['time_limit_minutes'] ?? 0 ) ),
			'passing_score'      => (float) sanitize_text_field( wp_unslash( $_POST['passing_score'] ?? get_option( 'zeko_learn_passing_score', '60' ) ) ),
			'max_attempts'       => absint( wp_unslash( $_POST['max_attempts'] ?? 0 ) ),
			'shuffle_questions'  => ! empty( $_POST['shuffle_questions'] ) ? 1 : 0,
			'show_answers'       => sanitize_text_field( wp_unslash( $_POST['show_answers'] ?? 'on_complete' ) ),
		);

		if ( $quiz_id ) {
			$this->db->update_quiz( $quiz_id, $data );
		} else {
			$quiz_id = $this->db->insert_quiz( $data );
		}

		// Save questions.
		if ( ! empty( $_POST['questions'] ) && is_array( $_POST['questions'] ) ) {
			$existing_ids  = array_column( $this->db->get_quiz_questions( $quiz_id ), 'id' );
			$submitted_ids = array();

			foreach ( (array) wp_unslash( $_POST['questions'] ) as $idx => $qdata ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nested form data; each element is individually sanitized with sanitize_text_field/wp_kses_post/absint below.
				$question_text = sanitize_text_field( wp_unslash( $qdata['question_text'] ?? '' ) );
				if ( empty( $question_text ) ) {
					continue;
				}

				$options_raw = sanitize_textarea_field( wp_unslash( $qdata['options'] ?? '' ) );
				$options     = array_filter( array_map( 'trim', explode( "\n", $options_raw ) ) );

				$qid     = absint( $qdata['id'] ?? 0 );
				$payload = array(
					'quiz_id'        => $quiz_id,
					'question_type'  => sanitize_text_field( $qdata['question_type'] ?? 'single_choice' ),
					'question_text'  => $question_text,
					'options'        => $options,
					'correct_answer' => sanitize_text_field( $qdata['correct_answer'] ?? '' ),
					'explanation'    => wp_kses_post( wp_unslash( $qdata['explanation'] ?? '' ) ),
					'points'         => absint( $qdata['points'] ?? 1 ),
					'sort_order'     => absint( $idx ),
				);

				if ( $qid > 0 ) {
					$this->db->update_quiz_question( $qid, $payload );
					$submitted_ids[] = $qid;
				} else {
					$new_id          = $this->db->insert_quiz_question( $payload );
					$submitted_ids[] = $new_id;
				}
			}

			// Delete removed questions.
			$removed = array_diff( $existing_ids, $submitted_ids );
			foreach ( $removed as $rid ) {
				$this->db->delete_quiz_question( $rid );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=zeko-learn-quizzes&message=updated' ) );
		exit;
	}

	// ─── Assignments ───────────────────────────────────────────.

	/**
	 * Render assignments.
	 */
	public function render_assignments(): void {
		if ( isset( $_GET['message'] ) && 'deleted' === $_GET['message'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag; compared against fixed literal.
			$this->admin_notices( 'success', __( 'Assignment deleted.', 'zeko-learn' ) );
		}
		if ( isset( $_GET['message'] ) && 'updated' === $_GET['message'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag; compared against fixed literal.
			$this->admin_notices( 'success', __( 'Assignment saved.', 'zeko-learn' ) );
		}

		global $wpdb;
		$ta  = $wpdb->prefix . 'zeko_assignments';
		$tc  = $wpdb->prefix . 'zeko_courses';
		$tas = $wpdb->prefix . 'zeko_assignment_submissions';

		if ( isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit flag; absint() coerced below.
			$this->render_assignment_editor( absint( wp_unslash( $_GET['edit'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit flag; absint() coerced.
			return;
		}

		if ( isset( $_GET['view_submissions'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view flag; absint() coerced below.
			$this->render_assignment_submissions( absint( wp_unslash( $_GET['view_submissions'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view flag; absint() coerced.
			return;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$assignments = $wpdb->get_results(
			"SELECT a.*, c.title AS course_title,
			(SELECT COUNT(*) FROM {$tas} s WHERE s.assignment_id = a.id) AS submission_count
			FROM {$ta} a
			LEFT JOIN {$tc} c ON a.course_id = c.id
			ORDER BY a.created_at DESC",
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Assignments', 'zeko-learn' ) . '</h1>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . esc_html__( 'Title', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Course', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Submissions', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Due Date', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Actions', 'zeko-learn' ) . '</th></tr></thead>';
		echo '<tbody>';
		if ( empty( $assignments ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No assignments yet.', 'zeko-learn' ) . '</td></tr>';
		} else {
			foreach ( $assignments as $a ) {
				$edit_url   = admin_url( 'admin.php?page=zeko-learn-assignments&edit=' . $a['id'] );
				$sub_url    = admin_url( 'admin.php?page=zeko-learn-assignments&view_submissions=' . $a['id'] );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=zeko-learn-assignments&action=delete&id=' . $a['id'] ), 'zeko_learn_delete_assignment_' . $a['id'] );
				echo '<tr>';
				echo '<td><a href="' . esc_url( $edit_url ) . '"><strong>' . esc_html( $a['title'] ) . '</strong></a></td>';
				echo '<td>' . esc_html( $a['course_title'] ?? '—' ) . '</td>';
				echo '<td><a href="' . esc_url( $sub_url ) . '">' . esc_html( $a['submission_count'] ) . '</a></td>';
				echo '<td>' . ( $a['due_date'] ? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $a['due_date'] ) ) : '—' ) . '</td>';
				echo '<td><a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( 'Edit', 'zeko-learn' ) . '</a> <a href="' . esc_url( $sub_url ) . '" class="button button-small">' . esc_html__( 'Submissions', 'zeko-learn' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render assignment editor.
	 *
	 * @param int $assignment_id Assignment id.
	 */
	private function render_assignment_editor( int $assignment_id ): void {
		global $wpdb;
		$assignment = null;
		if ( $assignment_id ) {
			$assignment = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}zeko_assignments WHERE id = %d", $assignment_id ),
				ARRAY_A
			);
		}
		$is_edit = ! empty( $assignment );

		echo '<div class="wrap">';
		echo '<h1>' . ( $is_edit ? esc_html__( 'Edit Assignment', 'zeko-learn' ) : esc_html__( 'Add Assignment', 'zeko-learn' ) ) . '</h1>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="zeko_learn_save_assignment">';
		wp_nonce_field( 'zeko_learn_save_assignment', 'zeko_learn_admin_nonce' );
		if ( $assignment_id ) {
			echo '<input type="hidden" name="assignment_id" value="' . esc_attr( $assignment_id ) . '">';
		}

		echo '<table class="form-table">';
		echo '<tr><th><label for="a_title">' . esc_html__( 'Title', 'zeko-learn' ) . '</label></th><td><input type="text" id="a_title" name="title" class="regular-text" value="' . esc_attr( $assignment['title'] ?? '' ) . '" required></td></tr>';
		echo '<tr><th><label for="a_course">' . esc_html__( 'Course ID', 'zeko-learn' ) . '</label></th><td><input type="number" id="a_course" name="course_id" value="' . esc_attr( $assignment['course_id'] ?? 0 ) . '" class="small-text" required></td></tr>';
		echo '<tr><th><label for="a_lesson">' . esc_html__( 'Lesson ID', 'zeko-learn' ) . '</label></th><td><input type="number" id="a_lesson" name="lesson_id" value="' . esc_attr( $assignment['lesson_id'] ?? 0 ) . '" class="small-text"></td></tr>';
		echo '<tr><th><label for="a_instructions">' . esc_html__( 'Instructions', 'zeko-learn' ) . '</label></th><td>';
		wp_editor(
			$assignment['instructions'] ?? '',
			'a_instructions',
			array(
				'textarea_name' => 'instructions',
				'textarea_rows' => 6,
			)
		);
		echo '</td></tr>';
		echo '<tr><th><label for="a_rubric">' . esc_html__( 'Grading Rubric', 'zeko-learn' ) . '</label></th><td><textarea id="a_rubric" name="rubric" class="large-text" rows="4">' . esc_textarea( $assignment['rubric'] ?? '' ) . '</textarea></td></tr>';
		echo '<tr><th><label for="a_maxsize">' . esc_html__( 'Max File Size (MB)', 'zeko-learn' ) . '</label></th><td><input type="number" id="a_maxsize" name="max_file_size_mb" min="1" value="' . esc_attr( $assignment['max_file_size_mb'] ?? 10 ) . '" class="small-text"></td></tr>';
		echo '<tr><th><label for="a_filetypes">' . esc_html__( 'Allowed File Types', 'zeko-learn' ) . '</label></th><td><input type="text" id="a_filetypes" name="allowed_file_types" value="' . esc_attr( $assignment['allowed_file_types'] ?? 'pdf,docx,zip' ) . '" class="regular-text"></td></tr>';
		echo '<tr><th><label for="a_due">' . esc_html__( 'Due Date', 'zeko-learn' ) . '</label></th><td><input type="datetime-local" id="a_due" name="due_date" value="' . esc_attr( $assignment['due_date'] ? gmdate( 'Y-m-d\TH:i', strtotime( $assignment['due_date'] ) ) : '' ) . '"></td></tr>';
		echo '</table>';

		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save Assignment', 'zeko-learn' ) . '</button></p>';
		echo '</form></div>';
	}

	/**
	 * Render assignment submissions.
	 *
	 * @param int $assignment_id Assignment id.
	 */
	private function render_assignment_submissions( int $assignment_id ): void {
		global $wpdb;
		$tas = $wpdb->prefix . 'zeko_assignment_submissions';

		$assignment = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}zeko_assignments WHERE id = %d", $assignment_id ),
			ARRAY_A
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$submissions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, u.display_name AS student_name, u.user_email AS student_email
				FROM {$tas} s
				INNER JOIN {$wpdb->users} u ON s.user_id = u.ID
				WHERE s.assignment_id = %d
				ORDER BY s.submitted_at DESC",
				$assignment_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $assignment['title'] ?? 'Submissions' ) . ' — ' . esc_html__( 'Submissions', 'zeko-learn' ) . '</h1>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=zeko-learn-assignments' ) ) . '">&larr; ' . esc_html__( 'Back to Assignments', 'zeko-learn' ) . '</a></p>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . esc_html__( 'Student', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Submitted', 'zeko-learn' ) . '</th><th>' . esc_html__( 'File', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Notes', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Grade', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Status', 'zeko-learn' ) . '</th></tr></thead>';
		echo '<tbody>';
		if ( empty( $submissions ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No submissions yet.', 'zeko-learn' ) . '</td></tr>';
		} else {
			foreach ( $submissions as $s ) {
				echo '<tr>';
				echo '<td>' . esc_html( $s['student_name'] ) . '<br><small>' . esc_html( $s['student_email'] ) . '</small></td>';
				echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $s['submitted_at'] ) ) . '</td>';
				echo '<td>' . ( $s['file_url'] ? '<a href="' . esc_url( $s['file_url'] ) . '" target="_blank">' . esc_html__( 'Download', 'zeko-learn' ) . '</a>' : '—' ) . '</td>';
				echo '<td>' . esc_html( wp_trim_words( $s['notes'] ?? '', 10 ) ) . '</td>';
				echo '<td>' . ( null !== $s['grade'] ? esc_html( $s['grade'] ) : '—' ) . '</td>';
				echo '<td><span class="zeko-status-badge zeko-status-' . esc_attr( $s['status'] ) . '">' . esc_html( $s['status'] ) . '</span></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Handle save assignment.
	 */
	public function handle_save_assignment(): void {
		if ( ! isset( $_POST['action'] ) || 'zeko_learn_save_assignment' !== $_POST['action'] ) {
			return;
		}
		if ( ! isset( $_POST['zeko_learn_admin_nonce'] ) || ! wp_verify_nonce( (string) wp_unslash( $_POST['zeko_learn_admin_nonce'] ), 'zeko_learn_save_assignment' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is a cryptographic token; sanitizing it would corrupt the verification.
			wp_die( esc_html__( 'Security check failed.', 'zeko-learn' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		$assignment_id = isset( $_POST['assignment_id'] ) ? absint( wp_unslash( $_POST['assignment_id'] ) ) : 0;

		$data = array(
			'title'              => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'course_id'          => absint( wp_unslash( $_POST['course_id'] ?? 0 ) ),
			'lesson_id'          => absint( wp_unslash( $_POST['lesson_id'] ?? 0 ) ),
			'instructions'       => wp_kses_post( wp_unslash( $_POST['instructions'] ?? '' ) ),
			'rubric'             => wp_kses_post( wp_unslash( $_POST['rubric'] ?? '' ) ),
			'max_file_size_mb'   => absint( wp_unslash( $_POST['max_file_size_mb'] ?? 10 ) ),
			'allowed_file_types' => sanitize_text_field( wp_unslash( $_POST['allowed_file_types'] ?? 'pdf,docx,zip' ) ),
			'due_date'           => sanitize_text_field( wp_unslash( $_POST['due_date'] ?? '' ) ),
		);

		if ( $assignment_id ) {
			$this->db->update_assignment( $assignment_id, $data );
		} else {
			$this->db->insert_assignment( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=zeko-learn-assignments&message=updated' ) );
		exit;
	}

	// ─── Helpers ────────────────────────────────────────────────.

	/**
	 * Instructor name.
	 *
	 * @param int $user_id User id.
	 */
	private function get_instructor_name( int $user_id ): string {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown', 'zeko-learn' );
	}

	// ─── Demo Data Page ────────────────────────────────────────.

	/**
	 * Render demo data.
	 */
	public function render_demo_data(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zeko-learn' ) );
		}

		$demo_count = wp_count_posts( 'zeko_learn_course' ?? 'post' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Demo Data', 'zeko-learn' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Generate realistic demo data for testing the LMS. This will create instructors, students, courses with curriculum, enrollments, quiz attempts, reviews, discussions, announcements, certificates, and payouts.', 'zeko-learn' ); ?></p>

			<div class="zeko-demo-data-wrap" style="max-width:700px;margin-top:20px;">
				<div class="card" style="padding:20px;">
					<h2><?php esc_html_e( 'Generate Demo Data', 'zeko-learn' ); ?></h2>
					<p><?php esc_html_e( 'Creates 5 instructors, 25 students, 10 courses with full curriculum, and related data.', 'zeko-learn' ); ?></p>
					<p><strong><?php esc_html_e( 'Note:', 'zeko-learn' ); ?></strong> <?php esc_html_e( 'If demo data already exists, duplicates may be created. Clear first if needed.', 'zeko-learn' ); ?></p>
					<button type="button" class="button button-primary button-hero" id="zeko-generate-demo">
						<span class="dashicons dashicons-performance" style="margin-top:5px;"></span>
						<?php esc_html_e( 'Generate Demo Data', 'zeko-learn' ); ?>
					</button>
					<div id="zeko-demo-generate-status" style="margin-top:12px;display:none;"></div>
				</div>

				<div class="card" style="padding:20px;margin-top:16px;">
					<h2><?php esc_html_e( 'Clear Demo Data', 'zeko-learn' ); ?></h2>
					<p><?php esc_html_e( 'Removes all demo courses, categories, skills, enrollments, reviews, discussions, and other generated data.', 'zeko-learn' ); ?></p>
					<p><strong><?php esc_html_e( 'Warning:', 'zeko-learn' ); ?></strong> <?php esc_html_e( 'This cannot be undone. Real user data will not be affected.', 'zeko-learn' ); ?></p>
					<button type="button" class="button button-secondary button-hero" id="zeko-clear-demo">
						<span class="dashicons dashicons-trash" style="margin-top:5px;"></span>
						<?php esc_html_e( 'Clear All Demo Data', 'zeko-learn' ); ?>
					</button>
					<div id="zeko-demo-clear-status" style="margin-top:12px;display:none;"></div>
				</div>

				<div class="card" style="padding:20px;margin-top:16px;">
					<h2><?php esc_html_e( 'Current Status', 'zeko-learn' ); ?></h2>
					<table class="widefat" style="max-width:400px;">
						<tbody>
							<tr><td><strong><?php esc_html_e( 'Demo Courses', 'zeko-learn' ); ?></strong></td>
								<td><?php echo esc_html( $this->count_demo_courses() ); ?></td></tr>
							<tr><td><strong><?php esc_html_e( 'Demo Instructors', 'zeko-learn' ); ?></strong></td>
								<td><?php echo esc_html( $this->count_demo_instructors() ); ?></td></tr>
							<tr><td><strong><?php esc_html_e( 'Demo Students', 'zeko-learn' ); ?></strong></td>
								<td><?php echo esc_html( $this->count_demo_students() ); ?></td></tr>
							<tr><td><strong><?php esc_html_e( 'Categories', 'zeko-learn' ); ?></strong></td>
								<td><?php echo esc_html( $this->db->count_categories() ); ?></td></tr>
							<tr><td><strong><?php esc_html_e( 'Enrollments', 'zeko-learn' ); ?></strong></td>
								<td><?php echo esc_html( $this->db->count_enrollments() ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Count demo courses.
	 */
	private function count_demo_courses(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}zeko_courses WHERE title LIKE 'Demo:%'"
		);
	}

	/**
	 * Count demo instructors.
	 */
	private function count_demo_instructors(): int {
		return count(
			get_users(
				array(
					'role'   => 'author',
					'fields' => 'ID',
					'search' => 'demo_instructor',
				)
			)
		);
	}

	/**
	 * Count demo students.
	 */
	private function count_demo_students(): int {
		return count(
			get_users(
				array(
					'role'   => 'subscriber',
					'fields' => 'ID',
					'search' => 'demo_student',
				)
			)
		);
	}
}
