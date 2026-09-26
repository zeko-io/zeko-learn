<?php
/**
 * Email notification system for Zeko Learn.
 *
 * Sends enrollment, completion, grading, discussion, and announcement emails.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Learn_Emails. */
class Zeko_Learn_Emails {

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
		add_action( 'zeko_learn_enrollment_confirmed', array( $this, 'send_enrollment_email' ), 10, 2 );
		add_action( 'zeko_learn_course_completed', array( $this, 'send_completion_email' ), 10, 2 );
		add_action( 'zeko_learn_quiz_graded', array( $this, 'send_quiz_graded_email' ), 10, 4 );
		add_action( 'zeko_learn_assignment_graded', array( $this, 'send_assignment_graded_email' ), 10, 3 );
		add_action( 'zeko_learn_discussion_reply', array( $this, 'send_discussion_reply_email' ), 10, 3 );
		add_action( 'zeko_learn_announcement', array( $this, 'send_announcement_email' ), 10, 2 );
	}

	/**
	 * From email.
	 */
	private function get_from_email(): string {
		return get_option( 'zeko_learn_from_email', get_option( 'admin_email' ) );
	}

	/**
	 * Whether an email address belongs to a demo-generated account.
	 * Blocks mailouts to @demo.local addresses and to users tagged with the
	 * zeko_demo_user meta so demo seed data is never used for real delivery.
	 *
	 * @return bool True when the recipient is demo-generated.
	 * @param string $to Email address.
	 */
	private function is_demo_recipient( string $to ): bool {
		if ( 'demo.local' === strtolower( (string) wp_parse_url( $to, PHP_URL_HOST ) ) ) {
			return true;
		}

		$user = get_user_by( 'email', $to );
		return $user && (bool) get_user_meta( (int) $user->ID, 'zeko_demo_user', true );
	}

	/**
	 * From name.
	 */
	private function get_from_name(): string {
		return get_option( 'zeko_learn_from_name', get_bloginfo( 'name' ) );
	}

	/**
	 * Headers.
	 */
	private function get_headers(): array {
		return array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>',
		);
	}

	/**
	 * Wrap template.
	 * Polished, brand-consistent wrapper shared by all Learn emails.
	 *
	 * @param string $title Title.
	 * @param string $body Body.
	 */
	private function wrap_template( string $title, string $body ): string {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();
		$tagline   = __( 'Learn something new, every day.', 'zeko-learn' );

		$brand      = '#4f46e5';
		$brand_dark = '#4338ca';
		$bg         = '#f1f5f9';
		$ink        = '#0f172a';
		$muted      = '#64748b';
		$border     = '#e2e8f0';

		return '<div style="background:' . $bg . ';padding:24px 16px;font-family:Arial,Helvetica,sans-serif;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;">'
			. '<tr><td style="background:' . $brand . ';height:6px;line-height:6px;font-size:0;">&nbsp;</td></tr>'
			. '<tr><td style="background:' . $brand_dark . ';padding:26px 30px;text-align:center;">'
			. '<h1 style="margin:0;font-size:20px;font-weight:700;color:#ffffff;letter-spacing:0.3px;">' . esc_html( $site_name ) . '</h1>'
			. '<p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,0.9);">' . esc_html( $tagline ) . '</p>'
			. '</td></tr>'
			. '<tr><td style="background:#ffffff;padding:32px 30px;">'
			. '<h2 style="margin:0 0 18px;font-size:18px;font-weight:700;color:' . $ink . ';">' . esc_html( $title ) . '</h2>'
			. $body
			. '</td></tr>'
			. '<tr><td style="background:#ffffff;border-top:1px solid ' . $border . ';padding:16px 30px;text-align:center;">'
			. '<p style="margin:0;font-size:12px;color:' . $muted . ';">&copy; ' . esc_html( gmdate( 'Y' ) ) . ' ' . esc_html( $site_name ) . ' &middot; <a href="' . esc_url( $site_url ) . '" style="color:' . $muted . ';">' . esc_html__( 'Visit site', 'zeko-learn' ) . '</a></p>'
			. '</td></tr>'
			. '</table></div>';
	}

	/**
	 * Send enrollment email.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function send_enrollment_email( int $user_id, int $course_id ): void {
		$user   = get_userdata( $user_id );
		$course = $this->db->get_course( $course_id );
		if ( ! $user || ! $course ) {
			return;
		}

		$course_url = home_url( '/courses/' . $course['slug'] . '/learn/' );
		$title      = __( 'Welcome to', 'zeko-learn' ) . ' ' . $course['title'];
		/* translators: %s: recipient display name */
		$body = '<p>' . sprintf( __( 'Hi %s,', 'zeko-learn' ), esc_html( $user->display_name ) ) . '</p>'
			/* translators: %s: course title */
			. '<p>' . sprintf( __( 'You have been enrolled in <strong>%s</strong>. Start learning now!', 'zeko-learn' ), esc_html( $course['title'] ) ) . '</p>'
			. '<p style="text-align:center;margin:32px 0;">'
			. '<a href="' . esc_url( $course_url ) . '" style="background:#4f46e5;color:#fff;padding:14px 32px;text-decoration:none;border-radius:6px;font-weight:bold;display:inline-block;">'
			. esc_html__( 'Start Learning', 'zeko-learn' ) . '</a></p>';

		if ( $this->is_demo_recipient( $user->user_email ) ) {
			return;
		}

		wp_mail( $user->user_email, $title, $this->wrap_template( $title, $body ), $this->get_headers() );
	}

	/**
	 * Send completion email.
	 *
	 * @param int $user_id User id.
	 * @param int $course_id Course id.
	 */
	public function send_completion_email( int $user_id, int $course_id ): void {
		$user   = get_userdata( $user_id );
		$course = $this->db->get_course( $course_id );
		if ( ! $user || ! $course ) {
			return;
		}

		$cert     = $this->db->get_user_certificates( $user_id );
		$cert_url = '';
		if ( ! empty( $cert ) ) {
			$cert     = end( $cert );
			$cert_url = home_url( '/certificates/' . $cert['certificate_number'] );
		}

		$title = __( 'Congratulations! Course Completed', 'zeko-learn' );
		/* translators: %s: recipient display name */
		$body = '<p>' . sprintf( __( 'Hi %s,', 'zeko-learn' ), esc_html( $user->display_name ) ) . '</p>'
			/* translators: %s: course title */
			. '<p>' . sprintf( __( 'You have successfully completed <strong>%s</strong>!', 'zeko-learn' ), esc_html( $course['title'] ) ) . '</p>';

		if ( $cert_url ) {
			$body .= '<p style="text-align:center;margin:32px 0;">'
				. '<a href="' . esc_url( $cert_url ) . '" style="background:#2e7d32;color:#fff;padding:14px 32px;text-decoration:none;border-radius:6px;font-weight:bold;">'
				. esc_html__( 'View Certificate', 'zeko-learn' ) . '</a></p>';
		}

		$body .= '<p>' . esc_html__( 'Keep up the great work!', 'zeko-learn' ) . '</p>';

		if ( $this->is_demo_recipient( $user->user_email ) ) {
			return;
		}

		wp_mail( $user->user_email, $title, $this->wrap_template( $title, $body ), $this->get_headers() );
	}

	/**
	 * Send quiz graded email.
	 *
	 * @param int   $user_id User id.
	 * @param int   $quiz_id Quiz id.
	 * @param float $score Score.
	 * @param bool  $passed Passed.
	 */
	public function send_quiz_graded_email( int $user_id, int $quiz_id, float $score, bool $passed ): void {
		$user = get_userdata( $user_id );
		$quiz = $this->db->get_quiz( $quiz_id );
		if ( ! $user || ! $quiz ) {
			return;
		}

		$course = $this->db->get_course( (int) $quiz['course_id'] );
		$status = $passed ? __( 'PASSED', 'zeko-learn' ) : __( 'not passed', 'zeko-learn' );
		/* translators: 1: quiz title. 2: quiz status */
		$title = sprintf( __( 'Quiz Graded: %1$s — %2$s', 'zeko-learn' ), $quiz['title'], $status );
		/* translators: %s: recipient display name */
		$body = '<p>' . sprintf( __( 'Hi %s,', 'zeko-learn' ), esc_html( $user->display_name ) ) . '</p>'
			/* translators: 1: quiz title. 2: course title */
			. '<p>' . sprintf( __( 'Your quiz <strong>%1$s</strong> in <strong>%2$s</strong> has been graded.', 'zeko-learn' ), esc_html( $quiz['title'] ), esc_html( $course ? $course['title'] : '' ) ) . '</p>'
			. '<p><strong>' . esc_html__( 'Score:', 'zeko-learn' ) . '</strong> ' . esc_html( round( $score, 1 ) ) . '% — ' . $status . '</p>';

		if ( $this->is_demo_recipient( $user->user_email ) ) {
			return;
		}

		wp_mail( $user->user_email, $title, $this->wrap_template( $title, $body ), $this->get_headers() );
	}

	/**
	 * Send assignment graded email.
	 *
	 * @param int   $user_id User id.
	 * @param int   $assignment_id Assignment id.
	 * @param array $submission Submission.
	 */
	public function send_assignment_graded_email( int $user_id, int $assignment_id, array $submission ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		global $wpdb;
		$assignment = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}zeko_assignments WHERE id = %d LIMIT 1", $assignment_id ),
			ARRAY_A
		);
		if ( ! $assignment ) {
			return;
		}

		$course = $this->db->get_course( (int) $assignment['course_id'] );
		$title  = __( 'Assignment Graded', 'zeko-learn' );
		/* translators: %s: recipient display name */
		$body = '<p>' . sprintf( __( 'Hi %s,', 'zeko-learn' ), esc_html( $user->display_name ) ) . '</p>'
			/* translators: 1: assignment title. 2: course title */
			. '<p>' . sprintf( __( 'Your submission for <strong>%1$s</strong> in <strong>%2$s</strong> has been graded.', 'zeko-learn' ), esc_html( $assignment['title'] ), esc_html( $course ? $course['title'] : '' ) ) . '</p>';

		if ( isset( $submission['grade'] ) && null !== $submission['grade'] ) {
			$body .= '<p><strong>' . esc_html__( 'Grade:', 'zeko-learn' ) . '</strong> ' . esc_html( $submission['grade'] ) . '</p>';
		}
		if ( ! empty( $submission['feedback'] ) ) {
			$body .= '<p><strong>' . esc_html__( 'Feedback:', 'zeko-learn' ) . '</strong></p><div style="background:#f8fafc;border-left:3px solid #4f46e5;padding:12px 16px;border-radius:0 6px 6px 0;">' . wp_kses_post( $submission['feedback'] ) . '</div>';
		}

		if ( $this->is_demo_recipient( $user->user_email ) ) {
			return;
		}

		wp_mail( $user->user_email, $title, $this->wrap_template( $title, $body ), $this->get_headers() );
	}

	/**
	 * Send discussion reply email.
	 *
	 * @param int    $author_id Author id.
	 * @param int    $discussion_id Discussion id.
	 * @param string $reply_content Reply content.
	 */
	public function send_discussion_reply_email( int $author_id, int $discussion_id, string $reply_content ): void {
		$discussion = $this->db->get_discussion( $discussion_id );
		if ( ! $discussion ) {
			return;
		}

		$course    = $this->db->get_course( (int) $discussion['course_id'] );
		$author    = get_userdata( $author_id );
		$recipient = get_userdata( (int) $discussion['user_id'] );
		if ( ! $author || ! $recipient || (int) $discussion['user_id'] === $author_id ) {
			return;
		}

		$course_url = home_url( '/courses/' . ( $course ? $course['slug'] : '' ) . '/discussions/' );
		/* translators: %s: discussion post title */
		$title = sprintf( __( 'New reply to: %s', 'zeko-learn' ), $discussion['title'] );
		/* translators: %s: recipient display name */
		$body = '<p>' . sprintf( __( 'Hi %s,', 'zeko-learn' ), esc_html( $recipient->display_name ) ) . '</p>'
			/* translators: 1: author display name. 2: discussion post title */
			. '<p>' . sprintf( __( '<strong>%1$s</strong> replied to your discussion post <strong>%2$s</strong>.', 'zeko-learn' ), esc_html( $author->display_name ), esc_html( $discussion['title'] ) ) . '</p>'
			. '<div style="background:#f8fafc;border-left:3px solid #4f46e5;padding:12px 16px;border-radius:0 6px 6px 0;margin:16px 0;">' . wp_kses_post( wp_trim_words( $reply_content, 50 ) ) . '</div>'
			. '<p style="text-align:center;margin:24px 0;">'
			. '<a href="' . esc_url( $course_url ) . '" style="background:#4f46e5;color:#fff;padding:12px 28px;text-decoration:none;border-radius:6px;display:inline-block;">'
			. esc_html__( 'View Discussion', 'zeko-learn' ) . '</a></p>';

		if ( $this->is_demo_recipient( $recipient->user_email ) ) {
			return;
		}

		wp_mail( $recipient->user_email, $title, $this->wrap_template( $title, $body ), $this->get_headers() );
	}

	/**
	 * Send announcement email.
	 *
	 * @param int   $course_id Course id.
	 * @param array $announcement Announcement.
	 */
	public function send_announcement_email( int $course_id, array $announcement ): void {
		$course = $this->db->get_course( $course_id );
		if ( ! $course ) {
			return;
		}

		global $wpdb;
		$students = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}zeko_enrollments WHERE course_id = %d AND status = 'active'",
				$course_id
			)
		);

		$course_url = home_url( '/courses/' . $course['slug'] . '/' );
		/* translators: %s: course title */
		$title = sprintf( __( 'New announcement in %s', 'zeko-learn' ), $course['title'] );
		$body  = '<p>' . esc_html__( 'Hello,', 'zeko-learn' ) . '</p>'
			/* translators: %s: course title */
			. '<p>' . sprintf( __( 'There is a new announcement in <strong>%s</strong>:', 'zeko-learn' ), esc_html( $course['title'] ) ) . '</p>'
			. '<h3 style="color:#4f46e5;">' . esc_html( $announcement['title'] ) . '</h3>'
			. '<div style="background:#f8fafc;border-left:3px solid #4f46e5;padding:12px 16px;border-radius:0 6px 6px 0;">' . wp_kses_post( $announcement['content'] ) . '</div>'
			. '<p style="text-align:center;margin:24px 0;">'
			. '<a href="' . esc_url( $course_url ) . '" style="background:#4f46e5;color:#fff;padding:12px 28px;text-decoration:none;border-radius:6px;display:inline-block;">'
			. esc_html__( 'View Course', 'zeko-learn' ) . '</a></p>';

		foreach ( $students as $student_id ) {
			$student = get_userdata( (int) $student_id );
			if ( $student && ! $this->is_demo_recipient( $student->user_email ) ) {
				wp_mail( $student->user_email, $title, $this->wrap_template( $title, $body ), $this->get_headers() );
			}
		}
	}
}
