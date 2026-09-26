<?php
/**
 * Ecosystem integration for Zeko Learn.
 *
 * Hooks into Zeko Core, Zeko Pay, Zeko theme, and other modules.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Learn_Ecosystem. */
class Zeko_Learn_Ecosystem {

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
		add_action( 'init', array( $this, 'register_hooks' ) );
	}

	/**
	 * Register cross-plugin hooks.
	 */
	public function register_hooks(): void {
		// Payment → enrollment bridge.
		add_action( 'zeko_learn_enroll_user', array( $this, 'handle_enroll_after_payment' ), 10, 2 );

		// Theme dashboard integration.
		add_filter( 'zeko_dashboard_tabs', array( $this, 'add_dashboard_tab' ) );
		add_action( 'zeko_dashboard_tab_content_learn', array( $this, 'render_dashboard_tab' ) );

		// Theme activity feed integration.
		add_filter( 'zeko_activity_feed_items', array( $this, 'inject_activity_items' ) );

		// Theme profile integration.
		add_action( 'zeko_profile_view_sections', array( $this, 'render_profile_section' ) );

		// Nav items registry.
		add_filter( 'zeko_nav_items', array( $this, 'register_nav_items' ) );

		// Notification source registry.
		add_filter( 'zeko_register_notification_sources', array( $this, 'register_notification_source' ) );
	}

	/**
	 * Handle enrollment + instructor payout after successful payment.
	 * Fired by Zeko Pay's learn_purchase_course() on successful charge.
	 *
	 * @param int $user_id Student user ID.
	 * @param int $course_id Course ID.
	 */
	public function handle_enroll_after_payment( int $user_id, int $course_id ): void {
		$course = $this->db->get_course( $course_id );
		if ( ! $course ) {
			return;
		}

		if ( $this->db->is_enrolled( $user_id, $course_id ) ) {
			return;
		}

		$enrollment_id = $this->db->enroll_user( $user_id, $course_id );

		// Notify student.
		$this->db->insert_notification(
			array(
				'user_id'     => $user_id,
				'action'      => 'course_purchased',
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

		// Instructor payout.
		if ( ! $course['is_free'] && (float) $course['price'] > 0 ) {
			$this->create_instructor_payout( $user_id, $course, (float) $course['price'] );
		}
	}

	/**
	 * Record an instructor payout and credit the instructor's wallet.
	 * The wallet credit must go through Zeko Pay (ledger) so the payout is
	 * real money, not a phantom ledger row. If Pay is unavailable the record
	 * stays 'pending' — money was genuinely not paid out.
	 *
	 * @param int   $student_id Purchasing student user ID.
	 * @param array $course Course row.
	 * @param float $price Gross course price.
	 */
	private function create_instructor_payout( int $student_id, array $course, float $price ): void {
		$instructor_id = (int) $course['instructor_id'];
		if ( ! $instructor_id ) {
			return;
		}

		$revenue_pct = (float) get_option( 'zeko_learn_instructor_revenue_pct', '70' );
		$amount      = round( $price * ( $revenue_pct / 100 ), 2 );

		$payout_id = $this->db->insert_payout(
			array(
				'instructor_id' => $instructor_id,
				'course_id'     => (int) $course['id'],
				'amount'        => $amount,
				'period_start'  => gmdate( 'Y-m-01' ),
				'period_end'    => gmdate( 'Y-m-t' ),
			)
		);

		if ( ! $payout_id ) {
			return;
		}

		if ( ! class_exists( 'Zeko_Pay_Integrations' ) ) {
			return;
		}

		$ref = \Zeko_Pay_Integrations::instance()->learn_credit_instructor_payout(
			$instructor_id,
			(int) $course['id'],
			$student_id,
			(string) $amount
		);

		if ( '' === $ref ) {
			return;
		}

		$this->db->update_payout(
			$payout_id,
			array(
				'status'  => 'paid',
				'paid_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Refund a paid course and revoke access, or unenroll from a free course.
	 * Money always routes through Zeko Pay: the SDK refunds the student's
	 * original charge and the instructor's payout is reversed. For free
	 * courses this simply cancels the enrollment.
	 *
	 * @return array{success: bool, message?: string}
	 * @param int $user_id Student user ID.
	 * @param int $course_id Course ID.
	 */
	public function refund_course( int $user_id, int $course_id ): array {
		$course = $this->db->get_course( $course_id );
		if ( ! $course ) {
			return array(
				'success' => false,
				'message' => __( 'Course not found.', 'zeko-learn' ),
			);
		}

		$enrollment = $this->db->get_enrollment( $user_id, $course_id );
		if ( ! $enrollment || 'active' !== $enrollment['status'] ) {
			return array(
				'success' => false,
				'message' => __( 'You are not actively enrolled in this course.', 'zeko-learn' ),
			);
		}

		if ( ! empty( $course['is_free'] ) || (float) $course['price'] <= 0 ) {
			$this->db->cancel_enrollment( $user_id, $course_id );
		} else {
			if ( ! class_exists( 'Zeko_Pay_Integrations' ) ) {
				return array(
					'success' => false,
					'message' => __( 'Payments are unavailable right now.', 'zeko-learn' ),
				);
			}

			$revenue_pct       = (float) get_option( 'zeko_learn_instructor_revenue_pct', '70' );
			$instructor_amount = round( (float) $course['price'] * ( $revenue_pct / 100 ), 2 );

			$result = \Zeko_Pay_Integrations::instance()->learn_refund_course(
				$user_id,
				$course_id,
				(int) $course['instructor_id'],
				$instructor_amount
			);

			if ( ! $result['success'] ) {
				return $result;
			}

			$this->db->refund_enrollment( $user_id, $course_id );
		}

		// Notify the student.
		$this->db->insert_notification(
			array(
				'user_id'     => $user_id,
				'action'      => 'course_refunded',
				'object_id'   => $course_id,
				'object_type' => 'course',
				'actor_id'    => $user_id,
			)
		);

		/**
		 * Fires after a course refund/unenroll is completed.
		 *
		 * @param int $user_id   Student user ID.
		 * @param int $course_id Course ID.
		 */
		do_action( 'zeko_learn_course_refunded', $user_id, $course_id );

		return array(
			'success' => true,
			'message' => __( 'Course refunded and access removed.', 'zeko-learn' ),
		);
	}

	/**
	 * Add "My Learning" tab to the Zeko dashboard.
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_dashboard_tab( array $tabs ): array {
		$tabs['learn'] = __( 'My Learning', 'zeko-learn' );
		return $tabs;
	}

	/**
	 * Register Learn as a notification source for the core bell.
	 *
	 * @param array $sources Sources.
	 */
	public function register_notification_source( array $sources ): array {
		$sources['learn'] = array(
			'table'       => $this->db->get_table_notifications(),
			'type_column' => 'action',
			'has_object'  => true,
			'icon'        => 'welcome-learn-more',
			'label'       => __( 'Learn', 'zeko-learn' ),
		);
		return $sources;
	}

	/**
	 * Register Learn nav items via the core registry.
	 *
	 * @return array
	 * @param array $locations keyed by location slug.
	 */
	public function register_nav_items( array $locations ): array {
		$locations['primary'][] = array(
			'title'    => __( 'Learn', 'zeko-learn' ),
			'url'      => home_url( '/courses/' ),
			'order'    => 2,
			'children' => array(
				array(
					'title' => __( 'Browse Courses', 'zeko-learn' ),
					'url'   => home_url( '/courses/' ),
				),
				array(
					'title' => __( 'My Learning', 'zeko-learn' ),
					'url'   => home_url( '/course-dashboard/' ),
				),
				array(
					'title' => __( 'Certificates', 'zeko-learn' ),
					'url'   => home_url( '/certificates/' ),
				),
				array(
					'title' => __( 'Teach on Zeko', 'zeko-learn' ),
					'url'   => home_url( '/instructor-dashboard/' ),
				),
			),
		);
		$locations['footer'][]  = array(
			'title' => __( 'Courses', 'zeko-learn' ),
			'url'   => home_url( '/courses/' ),
			'order' => 2,
		);
		return $locations;
	}

	/**
	 * Render the learning dashboard tab content.
	 */
	public function render_dashboard_tab(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user_id = get_current_user_id();
		$courses = $this->db->get_user_enrolled_courses( $user_id, array( 'limit' => 5 ) );
		echo '<div class="zeko-learn-dashboard-tab">';
		echo '<h3>' . esc_html__( 'My Learning', 'zeko-learn' ) . '</h3>';
		if ( empty( $courses ) ) {
			echo '<p>' . esc_html__( 'You are not enrolled in any courses yet.', 'zeko-learn' ) . '</p>';
		} else {
			echo '<ul class="zeko-learn-enrolled-mini">';
			foreach ( $courses as $course ) {
				printf(
					'<li><a href="%s">%s</a> — %s%%</li>',
					esc_url( home_url( '/courses/' . $course['slug'] . '/' ) ),
					esc_html( $course['title'] ),
					esc_html( (int) ( $course['completion_pct'] ?? 0 ) )
				);
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/**
	 * Inject learning activity into the global activity feed.
	 *
	 * @param array $items Items.
	 */
	public function inject_activity_items( array $items ): array {
		if ( ! is_user_logged_in() ) {
			return $items;
		}

		$user_id  = get_current_user_id();
		$enrolled = $this->db->get_user_enrolled_courses( $user_id, array( 'limit' => 5 ) );

		foreach ( $enrolled as $course ) {
			if ( ! empty( $course['completed_at'] ) ) {
				$items[] = array(
					'module'    => 'zeko-learn',
					'action'    => 'course_completed',
					/* translators: %s: course title */
					'message'   => sprintf( __( 'Completed course: %s', 'zeko-learn' ), $course['title'] ),
					'timestamp' => $course['completed_at'],
					'link'      => home_url( '/courses/' . $course['slug'] . '/' ),
				);
			}
		}

		return $items;
	}

	/**
	 * Render courses section on user profile.
	 */
	public function render_profile_section(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$completed = $this->db->get_user_enrolled_courses(
			$user_id,
			array(
				'status' => 'completed',
				'limit'  => 10,
			)
		);
		echo '<div class="zeko-learn-profile-section">';
		echo '<h3>' . esc_html__( 'Courses Completed', 'zeko-learn' ) . '</h3>';
		if ( empty( $completed ) ) {
			echo '<p>' . esc_html__( 'No courses completed yet.', 'zeko-learn' ) . '</p>';
		} else {
			echo '<ul>';
			foreach ( $completed as $course ) {
				printf(
					'<li><a href="%s">%s</a></li>',
					esc_url( home_url( '/courses/' . $course['slug'] . '/' ) ),
					esc_html( $course['title'] )
				);
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/**
	 * Log activity to Zeko Core activity system.
	 *
	 * @param int    $user_id User id.
	 * @param string $action Action.
	 * @param string $message Message.
	 * @param int    $item_id Item id.
	 * @param array  $meta Meta.
	 */
	public function log_activity( int $user_id, string $action, string $message, int $item_id = 0, array $meta = array() ): void {
		if ( class_exists( 'Zeko_Core_Activity' ) ) {
			$activity = \Zeko_Core_Activity::get_instance();
			$activity->log( $user_id, $action, $message, $item_id, $meta );
		}

		/**
		 * Fires when a Learn activity occurs.
		 *
		 * @param int    $user_id  User ID.
		 * @param string $action   Activity type.
		 * @param string $message  Human-readable message.
		 * @param int    $item_id  Related item ID.
		 * @param array  $meta     Extra metadata.
		 */
		do_action( 'zeko_learn_activity_logged', $user_id, $action, $message, $item_id, $meta );
	}
}
