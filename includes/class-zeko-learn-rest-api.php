<?php
/**
 * REST API handler for Zeko Learn.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Learn_REST_API. */
class Zeko_Learn_REST_API {

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		$namespace = 'zeko-learn/v1';

		register_rest_route(
			$namespace,
			'/courses',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_courses' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'status'        => array(
							'type'    => 'string',
							'default' => 'published',
							'enum'    => array( 'published' ),
						),
						'category_id'   => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'level'         => array(
							'type'    => 'string',
							'default' => '',
						),
						'instructor_id' => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'is_free'       => array( 'type' => 'boolean' ),
						'search'        => array(
							'type'    => 'string',
							'default' => '',
						),
						'orderby'       => array(
							'type'    => 'string',
							'default' => 'created_at',
							'enum'    => array( 'created_at', 'enrollment_count', 'avg_rating', 'title', 'price' ),
						),
						'order'         => array(
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array( 'ASC', 'DESC' ),
						),
						'per_page'      => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
						'page'          => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_course' ),
					'permission_callback' => array( $this, 'is_instructor' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/courses/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_course' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update_course' ),
					'permission_callback' => array( $this, 'is_instructor' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_course' ),
					'permission_callback' => array( $this, 'is_admin' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/courses/(?P<id>\d+)/sections',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_course_sections' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'add_course_section' ),
					'permission_callback' => array( $this, 'is_instructor' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/courses/(?P<id>\d+)/enroll',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'enroll_in_course' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/courses/(?P<id>\d+)/enrolled',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'check_enrollment' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/courses/(?P<id>\d+)/lessons',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_course_lessons' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/courses/(?P<id>\d+)/quizzes',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_course_quizzes' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/quizzes/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_quiz' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/quizzes/(?P<id>\d+)/attempt',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit_quiz_attempt' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/quizzes/(?P<id>\d+)/attempts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_quiz_attempts' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/discussions/(?P<id>\d+)/reply',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reply_discussion' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/lessons/(?P<id>\d+)/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'complete_lesson' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/courses/(?P<id>\d+)/reviews',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_course_reviews' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'submit_review' ),
					'permission_callback' => array( $this, 'is_logged_in' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/courses/(?P<id>\d+)/discussions',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_course_discussions' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$namespace,
			'/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_categories' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/skills',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_skills' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/enrollments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_my_enrollments' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/progress/(?P<course_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_course_progress' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/certificates',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_my_certificates' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/certificates/(?P<number>[A-Za-z0-9\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'verify_certificate' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/instructors',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_instructors' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/instructors/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_instructor' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/users/(?P<id>\d+)/courses',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_user_courses' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/users/(?P<id>\d+)/certificates',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_user_certificates' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/sections/(?P<id>\d+)/lessons',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add_section_lesson' ),
				'permission_callback' => array( $this, 'is_instructor' ),
			)
		);

		register_rest_route(
			$namespace,
			'/lessons/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update_lesson' ),
					'permission_callback' => array( $this, 'is_instructor' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_lesson' ),
					'permission_callback' => array( $this, 'is_instructor' ),
				),
			)
		);
	}

	/**
	 * Logged in.
	 */
	public function is_logged_in(): bool {
		return is_user_logged_in();
	}

	/**
	 * Instructor.
	 */
	public function is_instructor(): bool {
		return is_user_logged_in() && current_user_can( 'publish_posts' );
	}

	/**
	 * Admin.
	 */
	public function is_admin(): bool {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	/**
	 * Whether the current user may access a course's gated content.
	 * Enrolled students, the course instructor, and admins pass. Paid course
	 * content (sections, lessons, quizzes, progress) is otherwise locked.
	 *
	 * @param int $course_id Course id.
	 */
	private function can_access_course( int $course_id ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$course = $this->db->get_course( $course_id );
		if ( $course && get_current_user_id() === (int) $course['instructor_id'] ) {
			return true;
		}
		return $this->db->is_enrolled( get_current_user_id(), $course_id );
	}

	/**
	 * Course denied response.
	 */
	private function course_denied_response(): \WP_REST_Response {
		return new \WP_REST_Response(
			array( 'message' => 'Enrollment required to access course content.' ),
			403
		);
	}

	/**
	 * Whether the current user may edit a course (instructor or admin).
	 *
	 * @param int $course_id Course id.
	 */
	private function can_manage_course( int $course_id ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$course = $this->db->get_course( $course_id );
		return $course && get_current_user_id() === (int) $course['instructor_id'];
	}

	// ─── Courses ────────────────────────────────────────────────.

	/**
	 * Courses.
	 *
	 * @param mixed $request Request.
	 */
	public function get_courses( $request ) {
		// Public route: only published courses are ever returned. Admins may.
		// query other statuses from the admin UI instead of this endpoint.
		$status = 'published';
		if ( current_user_can( 'manage_options' ) ) {
			$status = $request->get_param( 'status' );
		}

		$args = array(
			'status'        => $status,
			'category_id'   => $request->get_param( 'category_id' ),
			'level'         => $request->get_param( 'level' ),
			'instructor_id' => $request->get_param( 'instructor_id' ),
			'is_free'       => $request->get_param( 'is_free' ),
			'search'        => $request->get_param( 'search' ),
			'orderby'       => $request->get_param( 'orderby' ),
			'order'         => $request->get_param( 'order' ),
			'limit'         => $request->get_param( 'per_page' ),
			'offset'        => ( $request->get_param( 'page' ) - 1 ) * $request->get_param( 'per_page' ),
		);

		$courses = $this->db->get_courses( $args );

		$data = array_map( array( $this, 'prepare_course_response' ), $courses );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Create course.
	 *
	 * @param mixed $request Request.
	 */
	public function create_course( $request ) {
		$params = $request->get_json_params();

		if ( empty( $params['title'] ) ) {
			return new \WP_REST_Response( array( 'message' => 'Title is required.' ), 400 );
		}

		$params['instructor_id'] = get_current_user_id();
		$course_id               = $this->db->insert_course( $params );

		return new \WP_REST_Response( $this->prepare_course_response( $this->db->get_course( $course_id ) ), 201 );
	}

	/**
	 * Course.
	 *
	 * @param mixed $request Request.
	 */
	public function get_course( $request ) {
		$course = $this->db->get_course( absint( $request['id'] ) );

		if ( ! $course ) {
			return new \WP_REST_Response( array( 'message' => 'Course not found.' ), 404 );
		}

		$data           = $this->prepare_course_response( $course );
		$data['skills'] = $this->db->get_course_skills( (int) $course['id'] );

		if ( $this->can_access_course( (int) $course['id'] ) ) {
			$data['sections'] = $this->db->get_course_sections( (int) $course['id'] );
		} else {
			$data['sections'] = array();
		}

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Update course.
	 *
	 * @param mixed $request Request.
	 */
	public function update_course( $request ) {
		$course_id = absint( $request['id'] );
		$course    = $this->db->get_course( $course_id );

		if ( ! $course ) {
			return new \WP_REST_Response( array( 'message' => 'Course not found.' ), 404 );
		}

		if ( ! $this->can_manage_course( $course_id ) ) {
			return new \WP_REST_Response( array( 'message' => 'Permission denied.' ), 403 );
		}

		$params = $request->get_json_params();
		$this->db->update_course( $course_id, $params );

		return new \WP_REST_Response( $this->prepare_course_response( $this->db->get_course( $course_id ) ), 200 );
	}

	/**
	 * Delete course.
	 *
	 * @param mixed $request Request.
	 */
	public function delete_course( $request ) {
		$course_id = absint( $request['id'] );
		$course    = $this->db->get_course( $course_id );

		if ( ! $course ) {
			return new \WP_REST_Response( array( 'message' => 'Course not found.' ), 404 );
		}

		$this->db->delete_course( $course_id );

		return new \WP_REST_Response( null, 204 );
	}

	/**
	 * Course sections.
	 *
	 * @param mixed $request Request.
	 */
	public function get_course_sections( $request ) {
		if ( ! $this->can_access_course( absint( $request['id'] ) ) ) {
			return $this->course_denied_response();
		}

		$sections = $this->db->get_course_sections( absint( $request['id'] ) );

		foreach ( $sections as &$section ) {
			$section['lessons'] = $this->db->get_section_lessons( (int) $section['id'] );
		}

		return new \WP_REST_Response( $sections, 200 );
	}

	// ─── Enrollment ─────────────────────────────────────────────.

	/**
	 * Enroll in course.
	 *
	 * @param mixed $request Request.
	 */
	public function enroll_in_course( $request ) {
		$course_id = absint( $request['id'] );
		$course    = $this->db->get_course( $course_id );
		$user_id   = get_current_user_id();

		if ( ! $course || 'published' !== $course['status'] ) {
			return new \WP_REST_Response( array( 'message' => 'Course not available.' ), 404 );
		}

		if ( $this->db->is_enrolled( $user_id, $course_id ) ) {
			return new \WP_REST_Response( array( 'message' => 'Already enrolled.' ), 409 );
		}

		if ( $user_id <= 0 ) {
			return new \WP_REST_Response( array( 'message' => 'Authentication required.' ), 401 );
		}

		if ( ! empty( $course['is_free'] ) ) {
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

			return new \WP_REST_Response( array( 'message' => 'Enrolled successfully.' ), 201 );
		}

		// Paid course — never enroll without a wallet charge. Route the.
		// charge through Zeko Pay's sanctioned integration contract.
		if ( ! class_exists( 'Zeko_Pay_Integrations' ) ) {
			return new \WP_REST_Response( array( 'message' => 'Payment system unavailable.' ), 503 );
		}

		$result = \Zeko_Pay_Integrations::instance()->learn_purchase_course(
			$user_id,
			$course_id,
			(float) $course['price']
		);

		if ( empty( $result['success'] ) ) {
			return new \WP_REST_Response(
				array(
					'message' => $result['message'] ?? 'Payment failed. Please check your wallet balance.',
				),
				402
			);
		}

		return new \WP_REST_Response( array( 'message' => 'Enrolled successfully.' ), 201 );
	}

	// ─── Reviews ────────────────────────────────────────────────.

	/**
	 * Course reviews.
	 *
	 * @param mixed $request Request.
	 */
	public function get_course_reviews( $request ) {
		$reviews = $this->db->get_course_reviews( absint( $request['id'] ) );
		return new \WP_REST_Response( $reviews, 200 );
	}

	/**
	 * Submit review.
	 *
	 * @param mixed $request Request.
	 */
	public function submit_review( $request ) {
		$course_id = absint( $request['id'] );
		$user_id   = get_current_user_id();

		if ( ! $this->db->is_enrolled( $user_id, $course_id ) ) {
			return new \WP_REST_Response( array( 'message' => 'Must be enrolled to review.' ), 403 );
		}

		$params    = $request->get_json_params();
		$review_id = $this->db->insert_review(
			array(
				'course_id'   => $course_id,
				'user_id'     => $user_id,
				'rating'      => absint( $params['rating'] ?? 5 ),
				'review_text' => wp_kses_post( $params['review_text'] ?? '' ),
			)
		);

		return new \WP_REST_Response(
			array(
				'id'      => $review_id,
				'message' => 'Review submitted.',
			),
			201
		);
	}

	// ─── Discussions ────────────────────────────────────────────.

	/**
	 * Course discussions.
	 *
	 * @param mixed $request Request.
	 */
	public function get_course_discussions( $request ) {
		$discussions = $this->db->get_course_discussions( absint( $request['id'] ) );

		foreach ( $discussions as &$discussion ) {
			$discussion['replies'] = $this->db->get_discussion_replies( (int) $discussion['id'] );
		}

		return new \WP_REST_Response( $discussions, 200 );
	}

	// ─── Categories & Skills ────────────────────────────────────.

	/**
	 * Categories.
	 *
	 * @param mixed $request Request.
	 */
	public function get_categories( $request ) {
		unset( $request );
		return new \WP_REST_Response( $this->db->get_categories(), 200 );
	}

	/**
	 * Skills.
	 *
	 * @param mixed $request Request.
	 */
	public function get_skills( $request ) {
		$search = $request->get_param( 'search' );

		if ( $search ) {
			$skills = $this->db->search_skills( $search );
		} else {
			$skills = $this->db->get_skills();
		}

		return new \WP_REST_Response( $skills, 200 );
	}

	// ─── Enrollments ────────────────────────────────────────────.

	/**
	 * My enrollments.
	 *
	 * @param mixed $request Request.
	 */
	public function get_my_enrollments( $request ) {
		unset( $request );
		$courses = $this->db->get_user_enrolled_courses( get_current_user_id() );
		return new \WP_REST_Response( $courses, 200 );
	}

	// ─── Progress ───────────────────────────────────────────────.

	/**
	 * Course progress.
	 *
	 * @param mixed $request Request.
	 */
	public function get_course_progress( $request ) {
		global $wpdb;

		$course_id = absint( $request['course_id'] );
		$user_id   = get_current_user_id();

		if ( ! $this->can_access_course( $course_id ) ) {
			return $this->course_denied_response();
		}

		$table = $wpdb->prefix . 'zeko_course_progress';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$progress = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d",
				$user_id,
				$course_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		return new \WP_REST_Response( $progress ?: array( 'completion_pct' => 0 ), 200 );
	}

	// ─── Certificates ───────────────────────────────────────────.

	/**
	 * My certificates.
	 *
	 * @param mixed $request Request.
	 */
	public function get_my_certificates( $request ) {
		unset( $request );
		$certificates = $this->db->get_user_certificates( get_current_user_id() );
		return new \WP_REST_Response( $certificates, 200 );
	}

	/**
	 * Verify certificate.
	 *
	 * @param mixed $request Request.
	 */
	public function verify_certificate( $request ) {
		$cert = $this->db->get_certificate_by_number( $request['number'] );

		if ( ! $cert ) {
			return new \WP_REST_Response( array( 'message' => 'Certificate not found.' ), 404 );
		}

		return new \WP_REST_Response(
			array(
				'valid'          => true,
				'student_name'   => $cert['student_name'],
				'course_title'   => $cert['course_title'],
				'certificate_no' => $cert['certificate_number'],
				'issued_at'      => $cert['issued_at'],
			),
			200
		);
	}

	// ─── Enrollment Check ────────────────────────────────────────.

	/**
	 * Check enrollment.
	 *
	 * @param mixed $request Request.
	 */
	public function check_enrollment( $request ) {
		$course_id  = absint( $request['id'] );
		$user_id    = get_current_user_id();
		$enrolled   = $this->db->is_enrolled( $user_id, $course_id );
		$enrollment = $enrolled ? $this->db->get_enrollment( $user_id, $course_id ) : null;
		return new \WP_REST_Response(
			array(
				'enrolled' => (bool) $enrolled,
				'progress' => $enrollment ? (float) ( $enrollment['progress_percent'] ?? 0 ) : 0,
				'status'   => $enrollment ? $enrollment['status'] : null,
			),
			200
		);
	}

	// ─── Lessons ────────────────────────────────────────────────.

	/**
	 * Course lessons.
	 *
	 * @param mixed $request Request.
	 */
	public function get_course_lessons( $request ) {
		if ( ! $this->can_access_course( absint( $request['id'] ) ) ) {
			return $this->course_denied_response();
		}

		$lessons = $this->db->get_course_lessons( absint( $request['id'] ) );
		return new \WP_REST_Response( $lessons, 200 );
	}

	/**
	 * Complete lesson.
	 *
	 * @param mixed $request Request.
	 */
	public function complete_lesson( $request ) {
		$lesson_id = absint( $request['id'] );
		$lesson    = $this->db->get_lesson( $lesson_id );
		if ( ! $lesson ) {
			return new \WP_REST_Response( array( 'message' => 'Lesson not found.' ), 404 );
		}
		if ( ! $this->can_access_course( (int) $lesson['course_id'] ) ) {
			return $this->course_denied_response();
		}
		$user_id   = get_current_user_id();
		$course_id = (int) $lesson['course_id'];
		$progress  = Zeko_Learn::instance()->get_progress()->complete_lesson( $user_id, $lesson_id, $course_id );
		return new \WP_REST_Response(
			array(
				'message'          => 'Lesson completed.',
				'completion_pct'   => $progress['completion_pct'],
				'completed'        => $progress['completed_lessons'],
				'total'            => $progress['total_lessons'],
				'course_completed' => ! empty( $progress['course_completed'] ),
			),
			200
		);
	}

	/**
	 * Update lesson.
	 *
	 * @param mixed $request Request.
	 */
	public function update_lesson( $request ) {
		$lesson_id = absint( $request['id'] );
		$lesson    = $this->db->get_lesson( $lesson_id );
		if ( ! $lesson ) {
			return new \WP_REST_Response( array( 'message' => 'Lesson not found.' ), 404 );
		}
		if ( ! $this->can_manage_course( (int) $lesson['course_id'] ) ) {
			return new \WP_REST_Response( array( 'message' => 'Permission denied.' ), 403 );
		}
		$params = $request->get_json_params();
		$this->db->update_lesson( $lesson_id, $params );
		return new \WP_REST_Response( $this->db->get_lesson( $lesson_id ), 200 );
	}

	/**
	 * Delete lesson.
	 *
	 * @param mixed $request Request.
	 */
	public function delete_lesson( $request ) {
		$lesson_id = absint( $request['id'] );
		$lesson    = $this->db->get_lesson( $lesson_id );
		if ( ! $lesson ) {
			return new \WP_REST_Response( array( 'message' => 'Lesson not found.' ), 404 );
		}
		if ( ! $this->can_manage_course( (int) $lesson['course_id'] ) ) {
			return new \WP_REST_Response( array( 'message' => 'Permission denied.' ), 403 );
		}
		$this->db->delete_lesson( $lesson_id );
		return new \WP_REST_Response( null, 204 );
	}

	// ─── Sections ───────────────────────────────────────────────.

	/**
	 * Add course section.
	 *
	 * @param mixed $request Request.
	 */
	public function add_course_section( $request ) {
		$course_id = absint( $request['id'] );
		$course    = $this->db->get_course( $course_id );
		if ( ! $course ) {
			return new \WP_REST_Response( array( 'message' => 'Course not found.' ), 404 );
		}
		if ( ! $this->can_manage_course( $course_id ) ) {
			return new \WP_REST_Response( array( 'message' => 'Permission denied.' ), 403 );
		}
		$params = $request->get_json_params();
		if ( empty( $params['title'] ) ) {
			return new \WP_REST_Response( array( 'message' => 'Title is required.' ), 400 );
		}
		$section_id = $this->db->insert_section( array_merge( $params, array( 'course_id' => $course_id ) ) );
		return new \WP_REST_Response(
			array(
				'id'      => $section_id,
				'message' => 'Section created.',
			),
			201
		);
	}

	/**
	 * Add section lesson.
	 *
	 * @param mixed $request Request.
	 */
	public function add_section_lesson( $request ) {
		$section_id = absint( $request['id'] );
		$section    = $this->db->get_section( $section_id );
		if ( ! $section ) {
			return new \WP_REST_Response( array( 'message' => 'Section not found.' ), 404 );
		}
		if ( ! $this->can_manage_course( (int) $section['course_id'] ) ) {
			return new \WP_REST_Response( array( 'message' => 'Permission denied.' ), 403 );
		}
		$params = $request->get_json_params();
		if ( empty( $params['title'] ) ) {
			return new \WP_REST_Response( array( 'message' => 'Title is required.' ), 400 );
		}
		$lesson_id = $this->db->insert_lesson(
			array_merge(
				$params,
				array(
					'section_id' => $section_id,
					'course_id'  => $section['course_id'],
				)
			)
		);
		return new \WP_REST_Response(
			array(
				'id'      => $lesson_id,
				'message' => 'Lesson created.',
			),
			201
		);
	}

	// ─── Quizzes ────────────────────────────────────────────────.

	/**
	 * Course quizzes.
	 *
	 * @param mixed $request Request.
	 */
	public function get_course_quizzes( $request ) {
		if ( ! $this->can_access_course( absint( $request['id'] ) ) ) {
			return $this->course_denied_response();
		}

		$quizzes = $this->db->get_course_quizzes( absint( $request['id'] ) );
		return new \WP_REST_Response( $quizzes, 200 );
	}

	/**
	 * Quiz.
	 *
	 * @param mixed $request Request.
	 */
	public function get_quiz( $request ) {
		$quiz = $this->db->get_quiz_with_questions( absint( $request['id'] ) );
		if ( ! $quiz ) {
			return new \WP_REST_Response( array( 'message' => 'Quiz not found.' ), 404 );
		}
		if ( ! $this->can_access_course( (int) $quiz['course_id'] ) ) {
			return $this->course_denied_response();
		}
		return new \WP_REST_Response( $quiz, 200 );
	}

	/**
	 * Submit quiz attempt.
	 *
	 * @param mixed $request Request.
	 */
	public function submit_quiz_attempt( $request ) {
		$quiz_id = absint( $request['id'] );
		$quiz    = $this->db->get_quiz( $quiz_id );
		if ( ! $quiz ) {
			return new \WP_REST_Response( array( 'message' => 'Quiz not found.' ), 404 );
		}
		if ( ! $this->can_access_course( (int) $quiz['course_id'] ) ) {
			return $this->course_denied_response();
		}
		$user_id      = get_current_user_id();
		$params       = $request->get_json_params();
		$questions    = $this->db->get_quiz_questions( $quiz_id );
		$user_answers = $params['answers'] ?? array();
		$earned       = 0;
		$total        = 0;
		foreach ( $questions as $q ) {
			$total  += (int) $q['points'];
			$correct = trim( strtolower( $q['correct_answer'] ) );
			$given   = trim( strtolower( $user_answers[ $q['id'] ] ?? '' ) );
			if ( $given === $correct ) {
				$earned += (int) $q['points'];
			}
		}
		$score      = $total > 0 ? round( ( $earned / $total ) * 100, 2 ) : 0;
		$pass       = $score >= (float) $quiz['passing_score'];
		$attempt_id = $this->db->insert_quiz_attempt(
			array(
				'quiz_id'            => $quiz_id,
				'user_id'            => $user_id,
				'score'              => $score,
				'total_points'       => $total,
				'earned_points'      => $earned,
				'answers'            => wp_json_encode( $user_answers ),
				'is_passed'          => $pass ? 1 : 0,
				'started_at'         => current_time( 'mysql', true ),
				'completed_at'       => current_time( 'mysql', true ),
				'time_taken_seconds' => absint( $params['time_taken'] ?? 0 ),
			)
		);
		return new \WP_REST_Response(
			array(
				'attempt_id' => $attempt_id,
				'score'      => $score,
				'earned'     => $earned,
				'total'      => $total,
				'passed'     => $pass,
			),
			201
		);
	}

	/**
	 * Quiz attempts.
	 *
	 * @param mixed $request Request.
	 */
	public function get_quiz_attempts( $request ) {
		$quiz_id = absint( $request['id'] );
		$quiz    = $this->db->get_quiz( $quiz_id );
		if ( ! $quiz ) {
			return new \WP_REST_Response( array( 'message' => 'Quiz not found.' ), 404 );
		}
		if ( ! $this->can_access_course( (int) $quiz['course_id'] ) ) {
			return $this->course_denied_response();
		}
		$user_id  = get_current_user_id();
		$attempts = $this->db->get_quiz_attempts( $quiz_id, $user_id );
		return new \WP_REST_Response( $attempts, 200 );
	}

	// ─── Discussions ────────────────────────────────────────────.

	/**
	 * Reply discussion.
	 *
	 * @param mixed $request Request.
	 */
	public function reply_discussion( $request ) {
		$parent_id = absint( $request['id'] );
		$parent    = $this->db->get_discussion( $parent_id );
		if ( ! $parent ) {
			return new \WP_REST_Response( array( 'message' => 'Discussion not found.' ), 404 );
		}
		if ( ! $this->can_access_course( (int) $parent['course_id'] ) ) {
			return $this->course_denied_response();
		}
		$params = $request->get_json_params();
		if ( empty( $params['content'] ) ) {
			return new \WP_REST_Response( array( 'message' => 'Content is required.' ), 400 );
		}
		$reply_id = $this->db->insert_discussion(
			array(
				'course_id'           => $parent['course_id'],
				'lesson_id'           => $parent['lesson_id'],
				'user_id'             => get_current_user_id(),
				'parent_id'           => $parent_id,
				'title'               => '',
				'content'             => wp_kses_post( $params['content'] ),
				'is_instructor_reply' => current_user_can( 'publish_posts' ) ? 1 : 0,
			)
		);
		return new \WP_REST_Response(
			array(
				'id'      => $reply_id,
				'message' => 'Reply posted.',
			),
			201
		);
	}

	// ─── Instructors ────────────────────────────────────────────.

	/**
	 * Instructors.
	 *
	 * @param mixed $request Request.
	 */
	public function get_instructors( $request ) {
		unset( $request );
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$instructor_ids = $wpdb->get_col(
			"SELECT DISTINCT instructor_id FROM {$this->db->get_table_courses()} WHERE instructor_id > 0 AND status = 'published'"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$instructors = array();
		foreach ( $instructor_ids as $id ) {
			$user = get_userdata( (int) $id );
			if ( ! $user ) {
				continue;
			}
			$stats         = $this->db->get_instructor_stats( (int) $id );
			$instructors[] = array(
				'id'            => (int) $id,
				'name'          => $user->display_name,
				'avatar'        => get_avatar_url( $id, array( 'size' => 96 ) ),
				'course_count'  => $stats['total_courses'] ?? 0,
				'student_count' => $stats['total_students'] ?? 0,
			);
		}
		return new \WP_REST_Response( $instructors, 200 );
	}

	/**
	 * Instructor.
	 *
	 * @param mixed $request Request.
	 */
	public function get_instructor( $request ) {
		$user = get_userdata( absint( $request['id'] ) );
		if ( ! $user || ! user_can( $user->ID, 'publish_posts' ) ) {
			return new \WP_REST_Response( array( 'message' => 'Instructor not found.' ), 404 );
		}
		$stats   = $this->db->get_instructor_stats( $user->ID );
		$courses = $this->db->get_courses(
			array(
				'instructor_id' => $user->ID,
				'status'        => 'published',
			)
		);
		return new \WP_REST_Response(
			array(
				'id'      => $user->ID,
				'name'    => $user->display_name,
				'avatar'  => get_avatar_url( $user->ID, array( 'size' => 256 ) ),
				'bio'     => $user->display_name,
				'stats'   => $stats,
				'courses' => array_map( array( $this, 'prepare_course_response' ), $courses ),
			),
			200
		);
	}

	// ─── Users ──────────────────────────────────────────────────.

	/**
	 * User courses.
	 *
	 * @param mixed $request Request.
	 */
	public function get_user_courses( $request ) {
		$user_id = absint( $request['id'] );
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_REST_Response( array( 'message' => 'Permission denied.' ), 403 );
		}
		$courses = $this->db->get_user_enrolled_courses( $user_id );
		return new \WP_REST_Response( $courses, 200 );
	}

	/**
	 * User certificates.
	 *
	 * @param mixed $request Request.
	 */
	public function get_user_certificates( $request ) {
		$user_id = absint( $request['id'] );
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_REST_Response( array( 'message' => 'Permission denied.' ), 403 );
		}
		$certificates = $this->db->get_user_certificates( $user_id );
		return new \WP_REST_Response( $certificates, 200 );
	}

	// ─── Helpers ────────────────────────────────────────────────.

	/**
	 * Prepare course response.
	 *
	 * @param array $course Course.
	 */
	private function prepare_course_response( array $course ): array {
		$course['instructor_name'] = '';
		$user                      = get_userdata( $course['instructor_id'] );
		if ( $user ) {
			$course['instructor_name'] = $user->display_name;
		}
		$course['thumbnail_url'] = $course['thumbnail_id'] ? wp_get_attachment_url( $course['thumbnail_id'] ) : '';
		return $course;
	}
}
