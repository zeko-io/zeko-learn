<?php
/**
 * Public frontend handler for Zeko Learn.
 *
 * Shortcodes, rewrite rules, templates, SEO, asset enqueuing.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Learn_Public. */
class Zeko_Learn_Public {

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
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_action( 'template_redirect', array( $this, 'handle_template_redirect' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'output_meta_tags' ) );
		if ( is_user_logged_in() ) {
			add_action( 'wp_footer', array( $this, 'render_notification_bell' ) );
		}
	}

	/**
	 * Shortcodes.
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'zeko_learn_catalog', array( $this, 'shortcode_catalog' ) );
		add_shortcode( 'zeko_learn_featured', array( $this, 'shortcode_featured' ) );
		add_shortcode( 'zeko_learn_student_dashboard', array( $this, 'shortcode_student_dashboard' ) );
		add_shortcode( 'zeko_learn_instructor_dashboard', array( $this, 'shortcode_instructor_dashboard' ) );
		add_shortcode( 'zeko_learn_my_courses', array( $this, 'shortcode_my_courses' ) );
		add_shortcode( 'zeko_learn_certificates', array( $this, 'shortcode_certificates' ) );
		add_shortcode( 'zeko_learn_verify_certificate', array( $this, 'shortcode_verify_certificate' ) );
		add_shortcode( 'zeko_notifications', array( $this, 'shortcode_notifications' ) );
		add_shortcode( 'zeko_notifications_widget', array( $this, 'shortcode_notifications' ) );
		add_shortcode( 'zeko_learn_categories', array( $this, 'shortcode_categories' ) );
		add_shortcode( 'zeko_learn_course', array( $this, 'shortcode_course' ) );
		add_shortcode( 'zeko_learn_progress', array( $this, 'shortcode_progress' ) );
		add_shortcode( 'zeko_learn_instructor', array( $this, 'shortcode_instructor' ) );
	}

	/**
	 * Rewrite rules.
	 */
	public function register_rewrite_rules(): void {
		add_rewrite_rule( '^courses/([^/]+)/learn/([^/]+)/([^/]+)/?$', 'index.php?zeko_learn_slug=$matches[1]&zeko_learn_view=lesson&zeko_section_slug=$matches[2]&zeko_lesson_slug=$matches[3]', 'top' );
		add_rewrite_rule( '^courses/([^/]+)/learn/?$', 'index.php?zeko_learn_slug=$matches[1]&zeko_learn_view=learn', 'top' );
		add_rewrite_rule( '^courses/([^/]+)/discussions/?$', 'index.php?zeko_learn_slug=$matches[1]&zeko_learn_view=discussions', 'top' );
		add_rewrite_rule( '^courses/([^/]+)/reviews/?$', 'index.php?zeko_learn_slug=$matches[1]&zeko_learn_view=reviews', 'top' );
		add_rewrite_rule( '^courses/([^/]+)/?$', 'index.php?zeko_learn_slug=$matches[1]', 'top' );
		add_rewrite_rule( '^courses/?$', 'index.php?zeko_learn_archive=1', 'top' );
		add_rewrite_rule( '^categories/([^/]+)/?$', 'index.php?zeko_category_slug=$matches[1]', 'top' );
		add_rewrite_rule( '^skills/([^/]+)/?$', 'index.php?zeko_skill_slug=$matches[1]', 'top' );
		add_rewrite_rule( '^instructors/([^/]+)/?$', 'index.php?zeko_instructor_slug=$matches[1]', 'top' );
		add_rewrite_rule( '^certificates/([^/]+)/?$', 'index.php?zeko_certificate_number=$matches[1]', 'top' );

		add_rewrite_tag( '%zeko_learn_slug%', '([^/]+)' );
		add_rewrite_tag( '%zeko_learn_view%', '([^/]+)' );
		add_rewrite_tag( '%zeko_section_slug%', '([^/]+)' );
		add_rewrite_tag( '%zeko_lesson_slug%', '([^/]+)' );
		add_rewrite_tag( '%zeko_learn_archive%', '([0-9]+)' );
		add_rewrite_tag( '%zeko_category_slug%', '([^/]+)' );
		add_rewrite_tag( '%zeko_skill_slug%', '([^/]+)' );
		add_rewrite_tag( '%zeko_instructor_slug%', '([^/]+)' );
		add_rewrite_tag( '%zeko_certificate_number%', '([^/]+)' );
	}

	/**
	 * Handle template redirect.
	 */
	public function handle_template_redirect(): void {
		// Single course landing page.
		if ( get_query_var( 'zeko_learn_slug' ) && ! get_query_var( 'zeko_learn_view' ) ) {
			$slug   = sanitize_title_for_query( get_query_var( 'zeko_learn_slug' ) );
			$course = $this->db->get_course_by_slug( $slug );

			if ( $course ) {
				$this->render_course_page( $course );
				exit;
			}
		}

		// Course player (learn view).
		if ( get_query_var( 'zeko_learn_slug' ) && 'learn' === get_query_var( 'zeko_learn_view' ) ) {
			$slug   = sanitize_title_for_query( get_query_var( 'zeko_learn_slug' ) );
			$course = $this->db->get_course_by_slug( $slug );

			if ( $course && is_user_logged_in() && $this->db->is_enrolled( get_current_user_id(), (int) $course['id'] ) ) {
				$this->render_course_player( $course );
				exit;
			} elseif ( $course ) {
				wp_safe_redirect( get_permalink() );
				exit;
			}
		}

		// Lesson view.
		if ( get_query_var( 'zeko_learn_slug' ) && 'lesson' === get_query_var( 'zeko_learn_view' ) ) {
			$slug         = sanitize_title_for_query( get_query_var( 'zeko_learn_slug' ) );
			$section_slug = sanitize_title_for_query( get_query_var( 'zeko_section_slug' ) );
			$lesson_slug  = sanitize_title_for_query( get_query_var( 'zeko_lesson_slug' ) );
			$course       = $this->db->get_course_by_slug( $slug );

			if ( $course ) {
				$this->render_lesson_view( $course, $lesson_slug );
				exit;
			}
		}

		// Certificate verification page.
		if ( get_query_var( 'zeko_certificate_number' ) ) {
			$number = sanitize_text_field( get_query_var( 'zeko_certificate_number' ) );
			$this->render_certificate_page( $number );
			exit;
		}

		// Course archive.
		if ( get_query_var( 'zeko_learn_archive' ) ) {
			$this->render_catalog_page();
			exit;
		}

		// Category archive.
		if ( get_query_var( 'zeko_category_slug' ) ) {
			$slug = sanitize_title_for_query( get_query_var( 'zeko_category_slug' ) );
			$this->render_catalog_page( array( 'category_slug' => $slug ) );
			exit;
		}
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_assets(): void {
		if ( $this->is_learn_page() ) {
			wp_enqueue_style( 'zeko-learn', ZEKO_LEARN_PLUGIN_URL . 'assets/css/zeko-learn-public.css', array( 'zeko-core' ), ZEKO_LEARN_VERSION );
			wp_enqueue_script( 'zeko-learn', ZEKO_LEARN_PLUGIN_URL . 'assets/js/zeko-learn-public.js', array( 'jquery' ), ZEKO_LEARN_VERSION, true );
			wp_enqueue_script( 'html2pdf', ZEKO_LEARN_PLUGIN_URL . 'assets/vendor/html2pdf/html2pdf.bundle.min.js', array(), '0.10.1', true );
			wp_localize_script(
				'zeko-learn',
				'zekoLearnPublic',
				array(
					'ajax'        => admin_url( 'admin-ajax.php' ),
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'zeko_learn_public_nonce' ),
					'baseUrl'     => home_url( '/courses/' ),
					'nonces'      => array(
						'grade'        => wp_create_nonce( 'zeko_learn_grade_assignment' ),
						'announcement' => wp_create_nonce( 'zeko_learn_post_announcement' ),
						'course'       => wp_create_nonce( 'zeko_learn_update_course_status' ),
					),
					'strings'     => array(
						'enrolled'            => __( 'Enrolled!', 'zeko-learn' ),
						'completed'           => __( 'Completed!', 'zeko-learn' ),
						'submitQuiz'          => __( 'Submit Quiz', 'zeko-learn' ),
						'loginToEnroll'       => __( 'Please log in to enroll.', 'zeko-learn' ),
						'confirmMark'         => __( 'Mark this lesson as complete?', 'zeko-learn' ),
						'confirmRefund'       => __( 'Refund this course and remove your access?', 'zeko-learn' ),
						'bookmarked'          => __( 'Bookmarked!', 'zeko-learn' ),
						'notBookmarked'       => __( 'Bookmark removed.', 'zeko-learn' ),
						'reviewSubmitted'     => __( 'Review submitted!', 'zeko-learn' ),
						'discussionReply'     => __( 'Reply posted.', 'zeko-learn' ),
						'replyPosted'         => __( 'Reply posted.', 'zeko-learn' ),
						'goalSet'             => __( 'Goal set!', 'zeko-learn' ),
						'goalUpdated'         => __( 'Goal updated.', 'zeko-learn' ),
						'announcementPosted'  => __( 'Announcement posted.', 'zeko-learn' ),
						'error'               => __( 'An error occurred.', 'zeko-learn' ),
						'tooManyRequests'     => __( 'Too many requests. Please wait.', 'zeko-learn' ),
						'confirmGoalComplete' => __( 'Mark this goal as completed?', 'zeko-learn' ),
						'confirmGoalAbandon'  => __( 'Abandon this goal?', 'zeko-learn' ),
						'congrats'            => __( 'Congratulations! You completed the course!', 'zeko-learn' ),
						'reviewReplyPosted'   => __( 'Reply posted.', 'zeko-learn' ),
						'bestAnswerMarked'    => __( 'Marked as best answer.', 'zeko-learn' ),
						'loading'             => __( 'Loading...', 'zeko-learn' ),
						'confirm'             => __( 'Are you sure?', 'zeko-learn' ),
						'confirmDelete'       => __( 'Are you sure you want to delete this?', 'zeko-learn' ),
						'submit'              => __( 'Submit', 'zeko-learn' ),
					),
					'currentUser' => get_current_user_id(),
				)
			);
		}

		// Notification center assets (for logged-in users everywhere).
		if ( is_user_logged_in() ) {
			wp_enqueue_style( 'zeko-learn-notifications', ZEKO_LEARN_PLUGIN_URL . 'assets/css/zeko-learn-notifications.css', array(), ZEKO_LEARN_VERSION );
			wp_enqueue_script( 'zeko-learn-notifications', ZEKO_LEARN_PLUGIN_URL . 'assets/js/zeko-learn-notifications.js', array( 'jquery' ), ZEKO_LEARN_VERSION, true );
			wp_localize_script(
				'zeko-learn-notifications',
				'zekoLearnPublic',
				array(
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'zeko_learn_public_nonce' ),
					'currentUser' => get_current_user_id(),
				)
			);
		}
	}

	// ─── Meta Tags (OpenGraph + Twitter Card) ──────────────────.

	/**
	 * Output meta tags.
	 */
	public function output_meta_tags(): void {
		$slug = get_query_var( 'zeko_learn_slug' );
		if ( ! $slug ) {
			return;
		}

		$course = $this->db->get_course_by_slug( sanitize_title_for_query( $slug ) );
		if ( ! $course ) {
			return;
		}

		$thumb_url  = $course['thumbnail_id'] ? wp_get_attachment_url( $course['thumbnail_id'] ) : '';
		$course_url = home_url( '/courses/' . $course['slug'] . '/' );
		$desc       = wp_strip_all_tags( $course['subtitle'] ?: $course['description'] ?? '' );
		$desc       = wp_trim_words( $desc, 30, '...' );

		echo "\n<!-- Zeko Learn OpenGraph -->\n";
		echo '<meta property="og:type" content="product">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $course['title'] . ' — ' . get_bloginfo( 'name' ) ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $course_url ) . '">' . "\n";
		if ( $thumb_url ) {
			echo '<meta property="og:image" content="' . esc_url( $thumb_url ) . '">' . "\n";
		}
		echo '<meta property="product:price:amount" content="' . esc_attr( $course['is_free'] ? '0' : $course['price'] ) . '">' . "\n";
		echo '<meta property="product:price:currency" content="' . esc_attr( get_option( 'zeko_learn_currency', 'USD' ) ) . '">' . "\n";

		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $course['title'] ) . '">' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
		if ( $thumb_url ) {
			echo '<meta name="twitter:image" content="' . esc_url( $thumb_url ) . '">' . "\n";
		}
		echo "<!-- /Zeko Learn OpenGraph -->\n";
	}

	/**
	 * Learn page.
	 */
	private function is_learn_page(): bool {
		if ( get_query_var( 'zeko_learn_slug' ) || get_query_var( 'zeko_learn_archive' ) || get_query_var( 'zeko_category_slug' ) ) {
			return true;
		}
		if ( is_page() ) {
			$page_slug = get_post_field( 'post_name', get_the_ID() );
			return in_array( $page_slug, array( 'courses', 'course-dashboard', 'instructor-dashboard', 'certificates', 'my-courses' ), true );
		}
		return false;
	}

	// ─── Page Renderers ─────────────────────────────────────────.

	/**
	 * Render catalog page.
	 *
	 * @param array $args Args.
	 */
	private function render_catalog_page( array $args = array() ): void {
		unset( $args );
		$page_template = get_page_template();
		if ( 'template-blank.php' !== $page_template ) {
			global $wp_query;
			$wp_query->is_404 = false;
			status_header( 200 );
		}

		include ZEKO_LEARN_PLUGIN_PATH . 'templates/catalog.php';
	}

	/**
	 * Render course page.
	 *
	 * @param array $course Course.
	 */
	private function render_course_page( array $course ): void {
		global $wp_query;
		$wp_query->is_404 = false;
		status_header( 200 );

		$template = ZEKO_LEARN_PLUGIN_PATH . 'templates/single-course.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			$this->fallback_course_page( $course );
		}
	}

	/**
	 * Render course player.
	 *
	 * @param array $course Course.
	 */
	private function render_course_player( array $course ): void {
		global $wp_query;
		$wp_query->is_404 = false;
		status_header( 200 );

		$template = ZEKO_LEARN_PLUGIN_PATH . 'templates/course-player.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			$this->fallback_course_player( $course );
		}
	}

	/**
	 * Render lesson view.
	 *
	 * @param array  $course Course.
	 * @param string $lesson_slug Lesson slug.
	 */
	private function render_lesson_view( array $course, string $lesson_slug ): void {
		global $wp_query;
		$wp_query->is_404 = false;
		status_header( 200 );

		$lessons = $this->db->get_course_lessons( (int) $course['id'] );
		$lesson  = null;
		foreach ( $lessons as $l ) {
			if ( sanitize_title( $l['slug'] ) === $lesson_slug || sanitize_title( $l['title'] ) === $lesson_slug ) {
				$lesson = $l;
				break;
			}
		}

		if ( ! $lesson ) {
			$wp_query->is_404 = true;
			status_header( 404 );
			return;
		}

		$template = ZEKO_LEARN_PLUGIN_PATH . 'templates/lesson.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			$this->fallback_lesson( $course, $lesson, $lessons );
		}
	}

	/**
	 * Render certificate page.
	 *
	 * @param string $number Number.
	 */
	private function render_certificate_page( string $number ): void {
		global $wp_query;
		$wp_query->is_404 = false;
		status_header( 200 );

		$cert = $this->db->get_certificate_by_number( $number );

		$template = ZEKO_LEARN_PLUGIN_PATH . 'templates/certificate.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			$this->fallback_certificate( $cert );
		}
	}

	// ─── Fallback Renderers (when templates/ not present) ───────.

	/**
	 * Fallback course page.
	 *
	 * @param array $course Course.
	 */
	private function fallback_course_page( array $course ): void {
		$sections    = $this->db->get_course_sections( (int) $course['id'] );
		$reviews     = $this->db->get_course_reviews( (int) $course['id'] );
		$skills      = $this->db->get_course_skills( (int) $course['id'] );
		$is_enrolled = is_user_logged_in() && $this->db->is_enrolled( get_current_user_id(), (int) $course['id'] );

		$instructor = get_userdata( $course['instructor_id'] );
		$thumb_url  = $course['thumbnail_id'] ? wp_get_attachment_url( $course['thumbnail_id'] ) : '';

		echo '<div class="zeko-learn-course-page">';
		echo '<div class="zeko-course-hero">';
		if ( $thumb_url ) {
			echo '<img src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr( $course['title'] ) . '" class="zeko-course-thumb">';
		}
		echo '<div class="zeko-course-hero-content">';
		echo '<h1>' . esc_html( $course['title'] ) . '</h1>';
		if ( $course['subtitle'] ) {
			echo '<p class="zeko-course-subtitle">' . esc_html( $course['subtitle'] ) . '</p>';
		}
		echo '<div class="zeko-course-meta">';
		echo '<span class="zeko-rating">' . esc_html( number_format( (float) $course['avg_rating'], 1 ) ) . ' (' . esc_html( $course['review_count'] ) . ' ' . esc_html__( 'reviews', 'zeko-learn' ) . ')</span>';
		echo '<span class="zeko-enrollments">' . esc_html( $course['enrollment_count'] ) . ' ' . esc_html__( 'students', 'zeko-learn' ) . '</span>';
		echo '<span class="zeko-level">' . esc_html( ucfirst( str_replace( '_', ' ', $course['level'] ) ) ) . '</span>';
		echo '<span class="zeko-hours">' . esc_html( $course['estimated_hours'] ) . ' ' . esc_html__( 'hours', 'zeko-learn' ) . '</span>';
		echo '</div>';
		if ( $instructor ) {
			echo '<p class="zeko-instructor">' . esc_html__( 'By', 'zeko-learn' ) . ' ' . esc_html( $instructor->display_name ) . '</p>';
		}
		echo '<div class="zeko-course-price">';
		if ( $course['is_free'] ) {
			echo '<span class="zeko-price-free">' . esc_html__( 'Free', 'zeko-learn' ) . '</span>';
		} else {
			echo '<span class="zeko-price">' . esc_html( get_option( 'zeko_learn_currency_symbol', '$' ) . number_format( (float) $course['price'], 2 ) ) . '</span>';
			if ( $course['sale_price'] ) {
				echo '<span class="zeko-sale-price">' . esc_html( get_option( 'zeko_learn_currency_symbol', '$' ) . number_format( (float) $course['sale_price'], 2 ) ) . '</span>';
			}
		}
		echo '</div>';
		if ( is_user_logged_in() ) {
			if ( $is_enrolled ) {
				echo '<a href="' . esc_url( home_url( '/courses/' . $course['slug'] . '/learn/' ) ) . '" class="button button-primary zeko-btn">' . esc_html__( 'Continue Learning', 'zeko-learn' ) . '</a>';
			} else {
				echo '<button class="button button-primary zeko-btn zeko-enroll-btn" data-course-id="' . esc_attr( $course['id'] ) . '">' . esc_html( $course['is_free'] ? __( 'Enroll Now', 'zeko-learn' ) : __( 'Buy Now', 'zeko-learn' ) ) . '</button>';
			}
		} else {
			echo '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="button button-primary">' . esc_html__( 'Log in to Enroll', 'zeko-learn' ) . '</a>';
		}
		echo '</div></div>';

		// Tabs: Overview, Curriculum, Reviews.
		echo '<div class="zeko-course-tabs">';
		echo '<nav class="zeko-tab-nav">';
		echo '<button class="zeko-tab-btn active" data-tab="overview">' . esc_html__( 'Overview', 'zeko-learn' ) . '</button>';
		echo '<button class="zeko-tab-btn" data-tab="curriculum">' . esc_html__( 'Curriculum', 'zeko-learn' ) . '</button>';
		echo '<button class="zeko-tab-btn" data-tab="reviews">' . esc_html__( 'Reviews', 'zeko-learn' ) . ' (' . esc_html( count( $reviews ) ) . ')</button>';
		echo '</nav>';

		// Overview.
		echo '<div class="zeko-tab-panel active" id="zeko-tab-overview">';
		if ( $course['what_you_learn'] ) {
			echo '<div class="zeko-what-you-learn"><h3>' . esc_html__( 'What You\'ll Learn', 'zeko-learn' ) . '</h3><div class="zeko-content">' . wp_kses_post( $course['what_you_learn'] ) . '</div></div>';
		}
		if ( $course['description'] ) {
			echo '<div class="zeko-description"><h3>' . esc_html__( 'Description', 'zeko-learn' ) . '</h3><div class="zeko-content">' . wp_kses_post( $course['description'] ) . '</div></div>';
		}
		if ( $course['requirements'] ) {
			echo '<div class="zeko-requirements"><h3>' . esc_html__( 'Requirements', 'zeko-learn' ) . '</h3><div class="zeko-content">' . wp_kses_post( $course['requirements'] ) . '</div></div>';
		}
		if ( ! empty( $skills ) ) {
			echo '<div class="zeko-skills"><h3>' . esc_html__( 'Skills', 'zeko-learn' ) . '</h3>';
			foreach ( $skills as $skill ) {
				echo '<span class="zeko-skill-tag">' . esc_html( $skill['name'] ) . '</span> ';
			}
			echo '</div>';
		}
		echo '</div>';

		// Curriculum.
		echo '<div class="zeko-tab-panel" id="zeko-tab-curriculum">';
		echo '<div class="zeko-curriculum">';
		$lesson_count = 0;
		foreach ( $sections as $section ) {
			$section_lessons = $this->db->get_section_lessons( (int) $section['id'] );
			echo '<div class="zeko-section">';
			echo '<div class="zeko-section-header"><h4>' . esc_html( $section['title'] ) . '</h4><span class="zeko-section-count">' . esc_html( count( $section_lessons ) ) . ' ' . esc_html__( 'lessons', 'zeko-learn' ) . '</span></div>';
			echo '<ul class="zeko-lesson-list">';
			foreach ( $section_lessons as $lesson ) {
				++$lesson_count;
				$type_icon     = 'text' === $lesson['lesson_type'] ? '&#128196;' : ( 'video' === $lesson['lesson_type'] ? '&#127909;' : '&#9998;' );
				$preview_badge = $lesson['is_preview'] ? ' <span class="zeko-preview-badge">' . esc_html__( 'Preview', 'zeko-learn' ) . '</span>' : '';
				echo '<li class="zeko-lesson-item">';
				echo '<span class="zeko-lesson-icon">' . wp_kses( $type_icon, array() ) . '</span>';
				echo '<span class="zeko-lesson-title">' . esc_html( $lesson['title'] ) . wp_kses_post( $preview_badge ) . '</span>';
				if ( $lesson['estimated_minutes'] ) {
					echo '<span class="zeko-lesson-duration">' . esc_html( $lesson['estimated_minutes'] ) . ' min</span>';
				}
				echo '</li>';
			}
			echo '</ul></div>';
		}
		echo '<p class="zeko-total-lessons">' . esc_html( $lesson_count ) . ' ' . esc_html__( 'total lessons', 'zeko-learn' ) . '</p>';
		echo '</div></div>';

		// Reviews.
		echo '<div class="zeko-tab-panel" id="zeko-tab-reviews">';
		if ( empty( $reviews ) ) {
			echo '<p>' . esc_html__( 'No reviews yet.', 'zeko-learn' ) . '</p>';
		} else {
			echo '<div class="zeko-reviews-summary">';
			echo '<span class="zeko-big-rating">' . esc_html( number_format( (float) $course['avg_rating'], 1 ) ) . '</span>';
			echo '<span class="zeko-rating-stars">' . esc_html( str_repeat( '&#9733;', round( (float) $course['avg_rating'] ) ) ) . '</span>';
			echo '<span class="zeko-review-count">' . esc_html( $course['review_count'] ) . ' ' . esc_html__( 'reviews', 'zeko-learn' ) . '</span>';
			echo '</div>';
			echo '<div class="zeko-reviews-list">';
			foreach ( $reviews as $review ) {
				echo '<div class="zeko-review">';
				echo '<div class="zeko-review-header"><strong>' . esc_html( $review['author_name'] ) . '</strong> <span class="zeko-review-rating">' . esc_html( str_repeat( '&#9733;', (int) $review['rating'] ) ) . '</span> <span class="zeko-review-date">' . esc_html( mysql2date( get_option( 'date_format' ), $review['created_at'] ) ) . '</span></div>';
				echo '<div class="zeko-review-text">' . wp_kses_post( $review['review_text'] ) . '</div>';
				echo '</div>';
			}
			echo '</div>';
		}

		if ( $is_enrolled ) {
			echo '<div class="zeko-submit-review">';
			echo '<h4>' . esc_html__( 'Write a Review', 'zeko-learn' ) . '</h4>';
			echo '<form class="zeko-review-form">';
			echo '<div class="zeko-rating-input">';
			for ( $i = 1; $i <= 5; $i++ ) {
				echo '<span class="zeko-star" data-rating="' . esc_attr( $i ) . '">&#9733;</span>';
			}
			echo '</div>';
			echo '<textarea name="review_text" rows="4" placeholder="' . esc_attr__( 'Share your experience...', 'zeko-learn' ) . '"></textarea>';
			echo '<input type="hidden" name="rating" value="5">';
			echo '<input type="hidden" name="course_id" value="' . esc_attr( $course['id'] ) . '">';
			echo '<button type="submit" class="button button-primary zeko-submit-review-btn">' . esc_html__( 'Submit Review', 'zeko-learn' ) . '</button>';
			echo '</form></div>';
		}

		echo '</div>';
		echo '</div></div>';
	}

	/**
	 * Fallback course player.
	 *
	 * @param array $course Course.
	 */
	private function fallback_course_player( array $course ): void {
		$sections       = $this->db->get_course_sections( (int) $course['id'] );
		$all_lessons    = $this->db->get_course_lessons( (int) $course['id'] );
		$current_lesson = ! empty( $all_lessons ) ? $all_lessons[0] : null;

		$user_id         = get_current_user_id();
		$lesson_progress = array();
		if ( $user_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'zeko_lesson_progress';
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT lesson_id, status FROM {$table} WHERE user_id = %d AND course_id = %d", $user_id, $course['id'] ),
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			foreach ( $rows as $row ) {
				$lesson_progress[ $row['lesson_id'] ] = $row['status'];
			}
		}

		echo '<div class="zeko-learn-player">';
		echo '<div class="zeko-player-sidebar">';
		echo '<h3>' . esc_html( $course['title'] ) . '</h3>';
		echo '<div class="zeko-curriculum-nav">';
		foreach ( $sections as $section ) {
			$s_lessons = $this->db->get_section_lessons( (int) $section['id'] );
			echo '<div class="zeko-nav-section">';
			echo '<div class="zeko-nav-section-title">' . esc_html( $section['title'] ) . '</div>';
			echo '<ul>';
			foreach ( $s_lessons as $sl ) {
				$status = $lesson_progress[ $sl['id'] ] ?? 'not_started';
				$icon   = 'completed' === $status ? '&#10003;' : ( 'in_progress' === $status ? '&#9654;' : '&#9675;' );
				$class  = 'completed' === $status ? ' completed' : '';
				echo '<li class="zeko-nav-lesson' . esc_attr( $class ) . '">';
				echo '<a href="' . esc_url( home_url( '/courses/' . $course['slug'] . '/learn/' . sanitize_title( $section['title'] ) . '/' . $sl['slug'] . '/' ) ) . '">';
				echo '<span class="zeko-nav-icon">' . wp_kses( $icon, array() ) . '</span> ' . esc_html( $sl['title'] );
				echo '</a></li>';
			}
			echo '</ul></div>';
		}
		echo '</div></div>';

		echo '<div class="zeko-player-content">';
		if ( $current_lesson ) {
			echo '<h2>' . esc_html( $current_lesson['title'] ) . '</h2>';
			if ( 'video' === $current_lesson['lesson_type'] && $current_lesson['video_url'] ) {
				echo '<div class="zeko-video-container">' . zeko_learn_render_video_embed( $current_lesson['video_url'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zeko_learn_render_video_embed() returns only an allow-listed, fully-escaped YouTube/Vimeo iframe or ''.
			} elseif ( $current_lesson['content'] ) {
				echo '<div class="zeko-lesson-content">' . wp_kses_post( $current_lesson['content'] ) . '</div>';
			}
			echo '<div class="zeko-lesson-actions">';
			echo '<button class="button button-primary zeko-complete-lesson-btn" data-lesson-id="' . esc_attr( $current_lesson['id'] ) . '" data-course-id="' . esc_attr( $course['id'] ) . '">' . esc_html__( 'Mark as Complete', 'zeko-learn' ) . '</button>';
			echo '<button class="button zeko-bookmark-btn" data-lesson-id="' . esc_attr( $current_lesson['id'] ) . '" data-course-id="' . esc_attr( $course['id'] ) . '">' . esc_html__( 'Bookmark', 'zeko-learn' ) . '</button>';
			echo '</div>';
		} else {
			echo '<p>' . esc_html__( 'No lessons available.', 'zeko-learn' ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Fallback lesson.
	 *
	 * @param array $course Course.
	 * @param array $lesson Lesson.
	 * @param array $all_lessons All lessons.
	 */
	private function fallback_lesson( array $course, array $lesson, array $all_lessons ): void {
		$user_id     = get_current_user_id();
		$is_enrolled = $user_id ? $this->db->is_enrolled( $user_id, (int) $course['id'] ) : false;
		$is_preview  = (int) ( $lesson['is_preview'] ?? 0 );
		$can_access  = $is_enrolled || $is_preview || ( $user_id && current_user_can( 'manage_options' ) );

		if ( ! $can_access ) {
			echo '<div class="zeko-locked-content">';
			echo '<span class="dashicons dashicons-lock"></span>';
			echo '<h2>' . esc_html__( 'This content is locked', 'zeko-learn' ) . '</h2>';
			echo '<p>' . esc_html__( 'Enroll in this course to access this lesson.', 'zeko-learn' ) . '</p>';
			echo '</div>';
			return;
		}

		$prev_lesson = null;
		$next_lesson = null;
		$found       = false;

		foreach ( $all_lessons as $idx => $l ) {
			if ( (int) $l['id'] === (int) $lesson['id'] ) {
				$found       = true;
				$prev_lesson = $idx > 0 ? $all_lessons[ $idx - 1 ] : null;
				continue;
			}
			if ( $found ) {
				$next_lesson = $l;
				break;
			}
		}

		echo '<div class="zeko-lesson-page">';
		echo '<h1>' . esc_html( $lesson['title'] ) . '</h1>';

		if ( 'video' === $lesson['lesson_type'] && $lesson['video_url'] ) {
			echo '<div class="zeko-video-container"><iframe src="' . esc_url( $lesson['video_url'] ) . '" frameborder="0" allowfullscreen></iframe></div>';
		} elseif ( $lesson['content'] ) {
			echo '<div class="zeko-lesson-content">' . wp_kses_post( $lesson['content'] ) . '</div>';
		}

		if ( $lesson['attachment_url'] ) {
			echo '<p><a href="' . esc_url( $lesson['attachment_url'] ) . '" class="button" download>' . esc_html__( 'Download Attachment', 'zeko-learn' ) . '</a></p>';
		}

		echo '<div class="zeko-lesson-nav">';
		if ( $prev_lesson ) {
			echo '<a href="' . esc_url( home_url( '/courses/' . $course['slug'] . '/learn/' . $prev_lesson['slug'] . '/' ) ) . '" class="button">&larr; ' . esc_html( $prev_lesson['title'] ) . '</a>';
		}
		if ( $next_lesson ) {
			echo '<a href="' . esc_url( home_url( '/courses/' . $course['slug'] . '/learn/' . $next_lesson['slug'] . '/' ) ) . '" class="button button-primary">' . esc_html( $next_lesson['title'] ) . ' &rarr;</a>';
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Fallback certificate.
	 *
	 * @param ?array $cert Cert.
	 */
	private function fallback_certificate( ?array $cert ): void {
		echo '<div class="zeko-certificate-page">';
		if ( ! $cert ) {
			echo '<h1>' . esc_html__( 'Certificate Not Found', 'zeko-learn' ) . '</h1>';
			echo '<p>' . esc_html__( 'The certificate number you entered is invalid.', 'zeko-learn' ) . '</p>';
		} else {
			echo '<div class="zeko-certificate">';
			echo '<h1>' . esc_html__( 'Certificate of Completion', 'zeko-learn' ) . '</h1>';
			echo '<p>' . esc_html__( 'This is to certify that', 'zeko-learn' ) . '</p>';
			echo '<h2 class="zeko-cert-name">' . esc_html( $cert['student_name'] ) . '</h2>';
			echo '<p>' . esc_html__( 'has successfully completed the course', 'zeko-learn' ) . '</p>';
			echo '<h3 class="zeko-cert-course">' . esc_html( $cert['course_title'] ) . '</h3>';
			echo '<p>' . esc_html__( 'Certificate Number:', 'zeko-learn' ) . ' <code>' . esc_html( $cert['certificate_number'] ) . '</code></p>';
			echo '<p>' . esc_html__( 'Issued on:', 'zeko-learn' ) . ' ' . esc_html( mysql2date( get_option( 'date_format' ), $cert['issued_at'] ) ) . '</p>';
			echo '</div>';
		}
		echo '</div>';
	}

	// ─── Shortcodes ─────────────────────────────────────────────.

	/**
	 * Shortcode catalog.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_catalog( $atts ): string {
		$a = shortcode_atts(
			array(
				'category' => '',
				'level'    => '',
				'per_page' => 12,
			),
			$atts
		);
		return '<div class="zeko-learn-catalog" id="zeko-learn-catalog" data-category="' . esc_attr( $a['category'] ) . '" data-level="' . esc_attr( $a['level'] ) . '" data-per-page="' . esc_attr( $a['per_page'] ) . '"></div>';
	}

	/**
	 * Shortcode featured.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_featured( $atts ): string {
		$a       = shortcode_atts( array( 'per_page' => 6 ), $atts );
		$courses = $this->db->get_courses(
			array(
				'is_featured' => 1,
				'limit'       => absint( $a['per_page'] ),
			)
		);

		if ( empty( $courses ) ) {
			return '<p>' . esc_html__( 'No featured courses.', 'zeko-learn' ) . '</p>';
		}

		$html = '<div class="zeko-learn-featured-grid">';
		foreach ( $courses as $course ) {
			$html .= $this->render_course_card( $course );
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Shortcode student dashboard.
	 *
	 * @param _ $_atts atts.
	 */
	public function shortcode_student_dashboard( $_atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your learning dashboard.', 'zeko-learn' ) . '</p>';
		}

		$template = ZEKO_LEARN_PLUGIN_PATH . 'templates/student-dashboard.php';
		if ( file_exists( $template ) ) {
			ob_start();
			include $template;
			return ob_get_clean();
		}

		return '<div class="zeko-learn-student-dashboard" id="zeko-learn-student-dashboard"></div>';
	}

	/**
	 * Shortcode instructor dashboard.
	 *
	 * @param _ $_atts atts.
	 */
	public function shortcode_instructor_dashboard( $_atts ): string {
		if ( ! is_user_logged_in() || ! current_user_can( 'publish_posts' ) ) {
			return '<p>' . esc_html__( 'You do not have instructor access.', 'zeko-learn' ) . '</p>';
		}

		$template = ZEKO_LEARN_PLUGIN_PATH . 'templates/instructor-dashboard.php';
		if ( file_exists( $template ) ) {
			ob_start();
			include $template;
			return ob_get_clean();
		}

		return '<div class="zeko-learn-instructor-dashboard" id="zeko-learn-instructor-dashboard"></div>';
	}

	/**
	 * Shortcode my courses.
	 *
	 * @param _ $_atts atts.
	 */
	public function shortcode_my_courses( $_atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your courses.', 'zeko-learn' ) . '</p>';
		}

		$courses = $this->db->get_user_enrolled_courses( get_current_user_id() );

		if ( empty( $courses ) ) {
			return '<p>' . esc_html__( 'You are not enrolled in any courses yet.', 'zeko-learn' ) . '</p>';
		}

		$html  = '<div class="zeko-learn-my-courses">';
		$html .= '<h2>' . esc_html__( 'My Learning', 'zeko-learn' ) . '</h2>';
		$html .= '<div class="zeko-my-courses-grid">';
		foreach ( $courses as $course ) {
			$html .= '<div class="zeko-my-course-card">';
			$html .= '<div class="zeko-my-course-info">';
			$html .= '<h3><a href="' . esc_url( home_url( '/courses/' . $course['slug'] . '/learn/' ) ) . '">' . esc_html( $course['title'] ) . '</a></h3>';
			$pct   = (float) ( $course['completion_pct'] ?? 0 );
			$html .= '<div class="zeko-progress-bar"><div class="zeko-progress-fill" style="width:' . esc_attr( $pct ) . '%"></div></div>';
			$html .= '<span class="zeko-progress-text">' . esc_html( round( $pct ) ) . '% ' . esc_html__( 'complete', 'zeko-learn' ) . '</span>';
			$html .= '</div></div>';
		}
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Shortcode certificates.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_certificates( $atts ): string {
		unset( $atts );
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your certificates.', 'zeko-learn' ) . '</p>';
		}

		$certs = $this->db->get_user_certificates( get_current_user_id() );

		if ( empty( $certs ) ) {
			return '<p>' . esc_html__( 'No certificates earned yet.', 'zeko-learn' ) . '</p>';
		}

		$html  = '<div class="zeko-learn-certificates"><h2>' . esc_html__( 'My Certificates', 'zeko-learn' ) . '</h2>';
		$html .= '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__( 'Course', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Certificate #', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Issued', 'zeko-learn' ) . '</th><th>' . esc_html__( 'Verify', 'zeko-learn' ) . '</th></tr></thead><tbody>';
		foreach ( $certs as $cert ) {
			$html .= '<tr>';
			$html .= '<td>' . esc_html( $cert['course_title'] ) . '</td>';
			$html .= '<td><code>' . esc_html( $cert['certificate_number'] ) . '</code></td>';
			$html .= '<td>' . esc_html( mysql2date( get_option( 'date_format' ), $cert['issued_at'] ) ) . '</td>';
			$html .= '<td><a href="' . esc_url( home_url( '/certificates/' . $cert['certificate_number'] ) ) . '">' . esc_html__( 'View', 'zeko-learn' ) . '</a></td>';
			$html .= '</tr>';
		}
		$html .= '</tbody></table></div>';
		return $html;
	}

	/**
	 * Shortcode verify certificate.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_verify_certificate( $atts ): string {
		$atts   = shortcode_atts( array( 'number' => '' ), $atts );
		$number = sanitize_text_field( $atts['number'] ?? '' );

		if ( ! empty( $number ) ) {
			$cert = $this->db->get_certificate_by_number( $number );
			if ( $cert ) {
				ob_start();
				$template = ZEKO_LEARN_PLUGIN_PATH . 'templates/certificate-verify-result.php';
				if ( file_exists( $template ) ) {
					include $template;
				} else {
					echo '<div class="zeko-cert-verify-result" style="max-width:600px;margin:20px auto;padding:30px;background:#f0fdf4;border:2px solid #16a34a;border-radius:12px;text-align:center;">';
					echo '<span class="dashicons dashicons-yes-alt" style="font-size:48px;width:48px;height:48px;color:#16a34a;"></span>';
					echo '<h3 style="margin:12px 0 8px;color:#16a34a;">' . esc_html__( 'Certificate Verified', 'zeko-learn' ) . '</h3>';
					echo '<p><strong>' . esc_html( $cert['student_name'] ) . '</strong> ' . esc_html__( 'completed', 'zeko-learn' ) . ' <strong>' . esc_html( $cert['course_title'] ) . '</strong></p>';
					echo '<p>' . esc_html__( 'Issued:', 'zeko-learn' ) . ' ' . esc_html( mysql2date( get_option( 'date_format' ), $cert['issued_at'] ) ) . '</p>';
					echo '<p><a href="' . esc_url( home_url( '/certificates/' . $cert['certificate_number'] ) ) . '">' . esc_html__( 'View Full Certificate', 'zeko-learn' ) . '</a></p>';
					echo '</div>';
				}
				return ob_get_clean();
			}
			return '<div class="zeko-cert-verify-result" style="max-width:600px;margin:20px auto;padding:30px;background:#fef2f2;border:2px solid #dc2626;border-radius:12px;text-align:center;">'
				. '<span class="dashicons dashicons-warning" style="font-size:48px;width:48px;height:48px;color:#dc2626;"></span>'
				. '<h3 style="margin:12px 0 8px;color:#dc2626;">' . esc_html__( 'Certificate Not Found', 'zeko-learn' ) . '</h3>'
				. '<p>' . esc_html__( 'No certificate matches that number.', 'zeko-learn' ) . '</p></div>';
		}

		$form  = '<div class="zeko-cert-verify-form" style="max-width:500px;margin:20px auto;padding:30px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">';
		$form .= '<h3 style="margin:0 0 16px;text-align:center;">' . esc_html__( 'Verify a Certificate', 'zeko-learn' ) . '</h3>';
		$form .= '<form method="get" action="' . esc_url( home_url( '/' ) ) . '">';
		$form .= '<input type="hidden" name="page_type" value="verify">';
		$form .= '<input type="text" name="cert_number" aria-label="' . esc_attr__( 'Certificate number', 'zeko-learn' ) . '" placeholder="' . esc_attr__( 'Enter certificate number', 'zeko-learn' ) . '" style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;font-size:15px;margin-bottom:12px;" required>';
		$form .= '<button type="submit" style="width:100%;padding:12px;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:15px;cursor:pointer;">' . esc_html__( 'Verify', 'zeko-learn' ) . '</button>';
		$form .= '</form></div>';
		return $form;
	}

	// ─── Notification Bell Shortcode (legacy, now rendered via wp_footer) ────.
	/**
	 * Shortcode notifications.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_notifications( $atts ): string {
		unset( $atts );
		// Bell is now rendered globally via wp_footer. This shortcode returns empty.
		return '';
	}

	// ─── Floating Notification Bell (wp_footer) ─────────────.
	/**
	 * Render notification bell.
	 */
	public function render_notification_bell(): void {
		// Skip when zeko-core provides its own bell.
		if ( class_exists( 'Zeko_Core_Notifications' ) ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$ajax_url = admin_url( 'admin-ajax.php' );
		$nonce    = wp_create_nonce( 'zeko_learn_public_nonce' );
		?>
<style>
#zl-notif-wrap{position:fixed;bottom:32px;left:32px;z-index:99999}
#zl-notif-btn{width:60px;height:60px;border-radius:50%;border:none;background:var(--color-primary,#4f46e5);color:var(--color-text-on-primary,#fff);font-size:0;cursor:pointer;box-shadow:0 4px 20px rgba(79,70,229,.4);display:flex;align-items:center;justify-content:center;transition:transform .2s,box-shadow .2s;position:relative}
#zl-notif-btn:hover{transform:scale(1.1);box-shadow:0 6px 28px rgba(79,70,229,.55)}
#zl-notif-btn .dashicons{font-size:28px;width:28px;height:28px;color:var(--color-text-on-primary,#fff)}
#zl-notif-badge{position:absolute;top:-2px;right:-2px;background:var(--color-danger,#ef4444);color:var(--color-text-on-primary,#fff);font-size:12px;font-weight:700;min-width:22px;height:22px;line-height:22px;text-align:center;border-radius:11px;padding:0 5px;border:3px solid var(--color-bg-white,#fff);display:none}
#zl-notif-drop{display:none;position:absolute;bottom:72px;left:0;width:400px;max-height:500px;background:var(--color-bg-white,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.18);overflow:hidden;flex-direction:column}
#zl-notif-drop.open{display:flex}
.zl-n-head{padding:16px 18px;border-bottom:1px solid var(--color-border,#e5e7eb);font-size:16px;font-weight:700;color:var(--color-text,#111827)}
.zl-n-tabs{display:flex;gap:4px;padding:8px 12px;border-bottom:1px solid var(--color-border,#e5e7eb);overflow-x:auto}
.zl-n-tab{padding:5px 14px;border:1px solid var(--color-border-light,#d1d5db);border-radius:20px;background:var(--color-bg-white,#fff);font-size:12px;cursor:pointer;white-space:nowrap;color:var(--color-text-secondary,#6b7280);transition:all .15s}
.zl-n-tab:hover{background:var(--color-bg-muted,#f3f4f6)}
.zl-n-tab.on{background:var(--color-primary,#4f46e5);color:var(--color-text-on-primary,#fff);border-color:var(--color-primary,#4f46e5)}
.zl-n-list{max-height:380px;overflow-y:auto}
.zl-n-load{text-align:center;padding:36px;color:var(--color-text-muted,#9ca3af);font-size:14px}
.zl-n-item{display:flex;gap:12px;padding:14px 18px;border-bottom:1px solid var(--color-bg-muted,#f3f4f6);cursor:pointer;transition:background .1s}
.zl-n-item:hover{background:var(--color-bg-light,#f9fafb)}
.zl-n-item.unread{background:var(--color-primary-light,#eef2ff);border-left:3px solid var(--color-primary,#4f46e5)}
.zl-n-item.unread:hover{background:var(--color-primary-light,#e0e7ff)}
.zl-n-av{width:38px;height:38px;border-radius:50%;flex-shrink:0;object-fit:cover}
.zl-n-avph{width:38px;height:38px;border-radius:50%;background:var(--color-border,#e5e7eb);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.zl-n-avph .dashicons{font-size:16px;width:16px;height:16px;color:var(--color-text-muted,#9ca3af)}
.zl-n-body{flex:1;min-width:0}
.zl-n-title{font-size:13px;font-weight:600;color:var(--color-text,#111827);margin:0 0 2px;line-height:1.3}
.zl-n-msg{font-size:12px;color:var(--color-text-secondary,#6b7280);margin:0 0 4px;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.zl-n-meta{font-size:11px;color:var(--color-text-muted,#9ca3af);display:flex;align-items:center;gap:8px}
.zl-n-mod{display:inline-flex;align-items:center;gap:3px;padding:1px 8px;border-radius:8px;background:var(--color-bg-muted,#f3f4f6);font-size:10px}
.zl-n-mod .dashicons{font-size:10px;width:10px;height:10px}
.zl-n-empty{text-align:center;padding:44px 20px;color:var(--color-text-muted,#9ca3af);font-size:14px}
.zl-n-empty .dashicons{font-size:36px;width:36px;height:36px;display:block;margin:0 auto 12px}
.zl-n-foot{padding:12px 18px;border-top:1px solid var(--color-border,#e5e7eb);text-align:center}
.zl-n-foot a{font-size:13px;color:var(--color-primary,#4f46e5);text-decoration:none;font-weight:500}
.zl-n-foot a:hover{text-decoration:underline}
@media(max-width:480px){#zl-notif-drop{position:fixed;bottom:0;left:0;right:0;width:100%;max-height:70vh;border-radius:12px 12px 0 0}#zl-notif-wrap{bottom:20px;left:20px}}
</style>

<div id="zl-notif-wrap">
	<button id="zl-notif-btn" aria-label="<?php echo esc_attr__( 'Notifications', 'zeko-learn' ); ?>">
		<span class="dashicons dashicons-bell"></span>
		<span id="zl-notif-badge">0</span>
	</button>
	<div id="zl-notif-drop">
		<div class="zl-n-head"><?php esc_html_e( 'Notifications', 'zeko-learn' ); ?></div>
		<div class="zl-n-tabs">
			<button class="zl-n-tab on" data-m=""><?php esc_html_e( 'All', 'zeko-learn' ); ?></button>
			<button class="zl-n-tab" data-m="learn"><?php esc_html_e( 'Learn', 'zeko-learn' ); ?></button>
			<button class="zl-n-tab" data-m="jobs"><?php esc_html_e( 'Jobs', 'zeko-learn' ); ?></button>
			<button class="zl-n-tab" data-m="qa"><?php esc_html_e( 'Q&A', 'zeko-learn' ); ?></button>
			<button class="zl-n-tab" data-m="pay"><?php esc_html_e( 'Pay', 'zeko-learn' ); ?></button>
			<button class="zl-n-tab" data-m="mentor"><?php esc_html_e( 'Mentor', 'zeko-learn' ); ?></button>
			<button class="zl-n-tab" data-m="shop"><?php esc_html_e( 'Shop', 'zeko-learn' ); ?></button>
			<button class="zl-n-tab" data-m="love"><?php esc_html_e( 'Love', 'zeko-learn' ); ?></button>
			<button class="zl-n-tab" data-m="rewards"><?php esc_html_e( 'Rewards', 'zeko-learn' ); ?></button>
		</div>
		<div class="zl-n-list" id="zl-n-list"><div class="zl-n-load"><?php esc_html_e( 'Loading...', 'zeko-learn' ); ?></div></div>
		<div class="zl-n-foot"><a href="#" id="zl-n-markall"><?php esc_html_e( 'Mark all as read', 'zeko-learn' ); ?></a></div>
	</div>
</div>

<script>
(function(){
var a='<?php echo esc_js( $ajax_url ); ?>',
	n='<?php echo esc_js( $nonce ); ?>',
	loaded=false;

var btn   = document.getElementById('zl-notif-btn');
var drop  = document.getElementById('zl-notif-drop');
var badge = document.getElementById('zl-notif-badge');
var list  = document.getElementById('zl-n-list');

if (!btn) return;

btn.addEventListener('click', function(e) {
	e.stopPropagation();
	var wasOpen = drop.classList.contains('open');
	drop.classList.toggle('open');
	if (!wasOpen && !loaded) { load(''); loaded = true; }
});

document.addEventListener('click', function() { drop.classList.remove('open'); });
drop.addEventListener('click', function(e) { e.stopPropagation(); });

var tabs = document.querySelectorAll('.zl-n-tab');
for (var i = 0; i < tabs.length; i++) {
	tabs[i].addEventListener('click', function() {
		for (var j = 0; j < tabs.length; j++) tabs[j].classList.remove('on');
		this.classList.add('on');
		load(this.getAttribute('data-m') || '');
	});
}

document.getElementById('zl-n-markall').addEventListener('click', function(e) {
	e.preventDefault();
	var fd = new FormData();
	fd.append('action', 'zeko_learn_mark_notification_read');
	fd.append('nonce', n);
	fetch(a, {method:'POST', body:fd}).then(function(r){return r.json()}).then(function(res){
		if (res.success) {
			var items = list.querySelectorAll('.zl-n-item.unread');
			for (var i = 0; i < items.length; i++) items[i].classList.remove('unread');
			badge.style.display = 'none';
			badge.textContent = '0';
		}
	});
});

function esc(s) {
	if (!s) return '';
	var d = document.createElement('div');
	d.appendChild(document.createTextNode(s));
	return d.innerHTML;
}

function load(m) {
	list.innerHTML = '<div class="zl-n-load"><?php echo esc_js( __( 'Loading...', 'zeko-learn' ) ); ?></div>';
	var fd = new FormData();
	fd.append('action', 'zeko_learn_get_notifications');
	fd.append('nonce', n);
	fd.append('module', m);
	fetch(a, {method:'POST', body:fd}).then(function(r){return r.json()}).then(function(res){
		if (!res.success || !res.data.notifications.length) {
			list.innerHTML = '<div class="zl-n-empty"><span class="dashicons dashicons-bell"></span><p><?php echo esc_js( __( 'No notifications yet', 'zeko-learn' ) ); ?></p></div>';
			return;
		}
		var ml = 
		<?php
		echo wp_json_encode(
			array(
				'learn'   => __( 'Learn', 'zeko-learn' ),
				'jobs'    => __( 'Jobs', 'zeko-learn' ),
				'qa'      => __( 'Q&A', 'zeko-learn' ),
				'pay'     => __( 'Pay', 'zeko-learn' ),
				'mentor'  => __( 'Mentor', 'zeko-learn' ),
				'shop'    => __( 'Shop', 'zeko-learn' ),
				'love'    => __( 'Love', 'zeko-learn' ),
				'rewards' => __( 'Rewards', 'zeko-learn' ),
			)
		);
		?>
		;
		var mi = {learn:'welcome-learn-more',jobs:'briefcase','qa':'editor-help',pay:'money-alt',mentor:'groups',shop:'cart',love:'heart'};
		var ns = res.data.notifications;
		var h = '';
		for (var i = 0; i < ns.length; i++) {
			var s = ns[i];
			var u = s.is_read == 0 ? ' unread' : '';
			var src = s.source || 'learn';
			var L = ml[src] || '<?php echo esc_js( __( 'System', 'zeko-learn' ) ); ?>';
			var I = mi[src] || 'dashicons-admin-site';
			var av = s.actor_avatar
				? '<img class="zl-n-av" src="' + s.actor_avatar + '">'
				: '<div class="zl-n-avph"><span class="dashicons ' + I + '"></span></div>';
			h += '<div class="zl-n-item' + u + '" data-id="' + s.id + '" data-s="' + src + '" data-link="' + (s.link || '') + '">'
				+ av
				+ '<div class="zl-n-body">'
				+ '<p class="zl-n-title">' + esc(s.action || '') + '</p>'
				+ '<p class="zl-n-msg">' + esc(s.message || s.title || '') + '</p>'
				+ '<div class="zl-n-meta">'
				+ '<span class="zl-n-mod"><span class="dashicons ' + I + '"></span> ' + L + '</span>'
				+ '<span>' + (s.time_ago || '') + '</span>'
				+ '</div></div></div>';
		}
		list.innerHTML = h;
		badge.textContent = res.data.total_unread;
		badge.style.display = res.data.total_unread > 0 ? '' : 'none';

		var items = list.querySelectorAll('.zl-n-item');
		for (var j = 0; j < items.length; j++) {
			items[j].addEventListener('click', function() {
				var id  = this.getAttribute('data-id');
				var sr  = this.getAttribute('data-s');
				var lnk = this.getAttribute('data-link');
				if (this.classList.contains('unread')) {
					this.classList.remove('unread');
					var fd2 = new FormData();
					fd2.append('action', 'zeko_learn_mark_notification_read');
					fd2.append('nonce', n);
					fd2.append('notification_id', id);
					fd2.append('source', sr);
					fetch(a, {method:'POST', body:fd2});
					var c = parseInt(badge.textContent) - 1;
					badge.textContent = Math.max(0, c);
					if (c <= 0) badge.style.display = 'none';
				}
				if (lnk) { window.location.href = lnk; }
			});
		}
	});
}
})();
</script>
		<?php
	}

	/**
	 * Shortcode categories.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_categories( $atts ): string {
		unset( $atts );
		$categories = $this->db->get_categories();

		if ( empty( $categories ) ) {
			return '<p>' . esc_html__( 'No categories yet.', 'zeko-learn' ) . '</p>';
		}

		$html = '<div class="zeko-learn-categories-grid">';
		foreach ( $categories as $cat ) {
			$url   = home_url( '/categories/' . $cat['slug'] . '/' );
			$icon  = $cat['icon'] ? '<span class="dashicons ' . esc_attr( $cat['icon'] ) . '"></span>' : '';
			$html .= '<a href="' . esc_url( $url ) . '" class="zeko-category-card">';
			$html .= '<div class="zeko-cat-icon">' . $icon . '</div>';
			$html .= '<h3>' . esc_html( $cat['name'] ) . '</h3>';
			if ( $cat['description'] ) {
				$html .= '<p>' . esc_html( wp_trim_words( $cat['description'], 12 ) ) . '</p>';
			}
			$html .= '</a>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Shortcode course.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_course( $atts ): string {
		$a = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts
		);

		$course = null;
		if ( $a['id'] ) {
			$course = $this->db->get_course( absint( $a['id'] ) );
		} elseif ( $a['slug'] ) {
			$course = $this->db->get_course_by_slug( sanitize_title( $a['slug'] ) );
		}

		if ( ! $course ) {
			return '<p>' . esc_html__( 'Course not found.', 'zeko-learn' ) . '</p>';
		}

		ob_start();
		$this->fallback_course_page( $course );
		return ob_get_clean();
	}

	/**
	 * Shortcode progress.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_progress( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$a         = shortcode_atts( array( 'course_id' => 0 ), $atts );
		$course_id = absint( $a['course_id'] );

		if ( ! $course_id ) {
			$courses = $this->db->get_user_enrolled_courses( get_current_user_id(), array( 'limit' => 5 ) );
			if ( empty( $courses ) ) {
				return '<p>' . esc_html__( 'No enrolled courses.', 'zeko-learn' ) . '</p>';
			}

			$html = '<div class="zeko-learn-progress">';
			foreach ( $courses as $course ) {
				$pct   = (float) ( $course['completion_pct'] ?? 0 );
				$html .= '<div class="zeko-progress-item">';
				$html .= '<h4>' . esc_html( $course['title'] ) . '</h4>';
				$html .= '<div class="zeko-progress-bar"><div class="zeko-progress-fill" style="width:' . esc_attr( $pct ) . '%"></div></div>';
				$html .= '<span>' . esc_html( round( $pct ) ) . '% (' . esc_html( $course['completed_lessons'] ?? 0 ) . '/' . esc_html( $course['total_lessons'] ?? 0 ) . ')</span>';
				$html .= '</div>';
			}
			$html .= '</div>';
			return $html;
		}

		return '<div class="zeko-learn-progress" data-course-id="' . esc_attr( $course_id ) . '"></div>';
	}

	/**
	 * Shortcode instructor.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_instructor( $atts ): string {
		$a       = shortcode_atts( array( 'id' => 0 ), $atts );
		$user_id = absint( $a['id'] );

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '<p>' . esc_html__( 'Instructor not found.', 'zeko-learn' ) . '</p>';
		}

		$stats   = $this->db->get_instructor_stats( $user_id );
		$courses = $this->db->get_courses(
			array(
				'instructor_id' => $user_id,
				'status'        => 'published',
			)
		);

		$html  = '<div class="zeko-learn-instructor">';
		$html .= '<div class="zeko-instructor-header">';
		$html .= get_avatar( $user_id, 96 );
		$html .= '<h2>' . esc_html( $user->display_name ) . '</h2>';
		$html .= '</div>';
		$html .= '<div class="zeko-instructor-stats">';
		$html .= '<div class="zeko-stat"><strong>' . esc_html( $stats['total_courses'] ) . '</strong> ' . esc_html__( 'Courses', 'zeko-learn' ) . '</div>';
		$html .= '<div class="zeko-stat"><strong>' . esc_html( $stats['total_students'] ) . '</strong> ' . esc_html__( 'Students', 'zeko-learn' ) . '</div>';
		$html .= '<div class="zeko-stat"><strong>' . esc_html( number_format( (float) $stats['avg_rating'], 1 ) ) . '</strong> ' . esc_html__( 'Avg Rating', 'zeko-learn' ) . '</div>';
		$html .= '</div>';

		if ( ! empty( $courses ) ) {
			$html .= '<h3>' . esc_html__( 'Courses by', 'zeko-learn' ) . ' ' . esc_html( $user->display_name ) . '</h3>';
			$html .= '<div class="zeko-instructor-courses">';
			foreach ( $courses as $course ) {
				$html .= $this->render_course_card( $course );
			}
			$html .= '</div>';
		}

		$html .= '</div>';
		return $html;
	}

	// ─── Helpers ────────────────────────────────────────────────.

	/**
	 * Render course card.
	 *
	 * @param array $course Course.
	 */
	private function render_course_card( array $course ): string {
		$thumb_url  = $course['thumbnail_id'] ? wp_get_attachment_url( $course['thumbnail_id'] ) : '';
		$course_url = home_url( '/courses/' . $course['slug'] . '/' );

		$html  = '<div class="zeko-course-card">';
		$html .= '<a href="' . esc_url( $course_url ) . '" class="zeko-card-thumb">';
		if ( $thumb_url ) {
			$html .= '<img src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr( $course['title'] ) . '">';
		} else {
			$html .= '<div class="zeko-card-placeholder"></div>';
		}
		if ( $course['is_free'] ) {
			$html .= '<span class="zeko-card-badge zeko-free">' . esc_html__( 'Free', 'zeko-learn' ) . '</span>';
		}
		$html .= '</a>';
		$html .= '<div class="zeko-card-body">';
		$html .= '<h3><a href="' . esc_url( $course_url ) . '">' . esc_html( $course['title'] ) . '</a></h3>';
		if ( $course['subtitle'] ) {
			$html .= '<p class="zeko-card-subtitle">' . esc_html( $course['subtitle'] ) . '</p>';
		}
		$html .= '<div class="zeko-card-meta">';
		$html .= '<span class="zeko-card-rating">' . esc_html( number_format( (float) $course['avg_rating'], 1 ) ) . ' &#9733;</span>';
		$html .= '<span class="zeko-card-students">' . esc_html( $course['enrollment_count'] ) . ' ' . esc_html__( 'students', 'zeko-learn' ) . '</span>';
		$html .= '</div>';
		if ( ! $course['is_free'] ) {
			$html .= '<div class="zeko-card-price">' . esc_html( get_option( 'zeko_learn_currency_symbol', '$' ) . number_format( (float) $course['price'], 2 ) ) . '</div>';
		}
		$html .= '</div></div>';
		return $html;
	}
}
