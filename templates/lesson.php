<?php
/**
 * Single lesson viewer template.
 *
 * Handles text, video, quiz, assignment, and download lesson types.
 *
 * @package Zeko_Learn
 * @var array $course  Course row.
 * @var array $lesson  Lesson row.
 * @var array $all_lessons  All lessons in the course (ordered).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = $this->db;

$user_id     = get_current_user_id();
$course_id   = (int) $course['id'];
$lesson_id   = (int) $lesson['id'];
$lesson_type = $lesson['lesson_type'];
$is_enrolled = $user_id ? $db->is_enrolled( $user_id, $course_id ) : false;
$is_complete = false;
$is_preview  = (int) $lesson['is_preview'];

if ( $user_id ) {
	$progress    = $db->get_lesson_progress( $user_id, $lesson_id );
	$is_complete = 'completed' === ( $progress['status'] ?? '' );
}

// Find prev/next lessons.
$prev_lesson  = null;
$next_lesson  = null;
$found        = false;
$sections     = $db->get_course_sections( $course_id );
$flat_lessons = array();
foreach ( $sections as $section ) {
	$s_lessons = $db->get_section_lessons( (int) $section['id'] );
	foreach ( $s_lessons as $sl ) {
		$sl['section_slug'] = sanitize_title( $section['title'] );
		$flat_lessons[]     = $sl;
	}
}

foreach ( $flat_lessons as $idx => $fl ) {
	if ( (int) $fl['id'] === $lesson_id ) {
		$found       = true;
		$prev_lesson = $idx > 0 ? $flat_lessons[ $idx - 1 ] : null;
		continue;
	}
	if ( $found ) {
		$next_lesson = $fl;
		break;
	}
}

$lesson_url_base = home_url( '/courses/' . $course['slug'] . '/learn/' );

// Quiz data if this is a quiz lesson.
$quiz    = null;
$quiz_id = null;
if ( 'quiz' === $lesson_type ) {
	$quiz    = $db->get_quiz_by_lesson( $lesson_id );
	$quiz_id = $quiz ? (int) $quiz['id'] : null;
}

// Assignment data if this is an assignment lesson.
$assignment = null;
if ( 'assignment' === $lesson_type ) {
	$assignment = $db->get_assignment_by_lesson( $lesson_id );
}

// Notes for this lesson.
$notes = $user_id ? $db->get_lesson_notes( $user_id, $lesson_id ) : array();

// Sidebar sections for curriculum nav.
$lesson_progress_map = array();
if ( $user_id ) {
	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT lesson_id, status FROM {$wpdb->prefix}zeko_lesson_progress WHERE user_id = %d AND course_id = %d", $user_id, $course_id ),
		ARRAY_A
	);
	foreach ( $rows as $row ) {
		$lesson_progress_map[ $row['lesson_id'] ] = $row['status'];
	}
}

$can_access = $is_enrolled || $is_preview || ( $user_id && current_user_can( 'manage_options' ) );
/** Zeko PRO hook: drip/scheduling policy may lock an enrolled student (no-op by default). */
$can_access = apply_filters( 'zeko_learn_lesson_access', (bool) $can_access, $lesson, $course_id, $user_id );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $lesson['title'] ); ?> — <?php echo esc_html( $course['title'] ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'zeko-learn-body' ); ?>>
<?php wp_body_open(); ?>

<div class="zeko-lesson-viewer" data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>" data-course-id="<?php echo esc_attr( $course_id ); ?>" data-lesson-type="<?php echo esc_attr( $lesson_type ); ?>">

	<!-- Sidebar -->
	<aside class="zeko-player-sidebar">
		<div class="zeko-sidebar-header">
			<a href="<?php echo esc_url( home_url( '/courses/' . $course['slug'] . '/' ) ); ?>" class="zeko-back-to-course">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php echo esc_html( $course['title'] ); ?>
			</a>
			<?php
			$progress_data = $db->get_course_progress( $user_id, $course_id );
			$pct           = $progress_data ? (float) $progress_data['completion_pct'] : 0;
			?>
			<div class="zeko-progress-wrap">
				<div class="zeko-progress-bar"><div class="zeko-progress-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
				<span class="zeko-progress-text"><?php echo esc_html( round( $pct ) ); ?>% <?php esc_html_e( 'complete', 'zeko-learn' ); ?></span>
			</div>
		</div>
		<nav class="zeko-curriculum-nav">
			<?php
			foreach ( $sections as $section ) :
				$s_lessons = $db->get_section_lessons( (int) $section['id'] );
				?>
				<div class="zeko-nav-section">
					<div class="zeko-nav-section-title"><?php echo esc_html( $section['title'] ); ?></div>
					<ul>
						<?php
						foreach ( $s_lessons as $sl ) :
							$item_status     = $lesson_progress_map[ $sl['id'] ] ?? 'not_started';
							$is_current = (int) $sl['id'] === $lesson_id;
							$icon_class = 'completed' === $item_status ? 'zeko-nav-completed' : ( $is_current ? 'zeko-nav-current' : 'zeko-nav-pending' );
							$icon       = 'completed' === $item_status ? '&#10003;' : ( $is_current ? '&#9654;' : '&#9675;' );
							$lesson_url = $lesson_url_base . sanitize_title( $section['title'] ) . '/' . $sl['slug'] . '/';
							?>
							<li class="zeko-nav-lesson <?php echo esc_attr( $icon_class ); ?> <?php echo esc_attr( $is_current ? 'current' : '' ); ?>">
								<a href="<?php echo esc_url( $lesson_url ); ?>">
									<span class="zeko-nav-icon"><?php echo wp_kses( $icon, array() ); ?></span>
									<span class="zeko-nav-title"><?php echo esc_html( $sl['title'] ); ?></span>
									<?php if ( 'quiz' === $sl['lesson_type'] ) : ?>
										<span class="zeko-nav-type dashicons dashicons-feedback"></span>
									<?php elseif ( 'assignment' === $sl['lesson_type'] ) : ?>
										<span class="zeko-nav-type dashicons dashicons-edit"></span>
									<?php endif; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</nav>
	</aside>

	<!-- Main Content -->
	<main class="zeko-lesson-main">

		<?php if ( ! $can_access ) : ?>
			<div class="zeko-locked-content">
				<span class="dashicons dashicons-lock"></span>
				<h2><?php esc_html_e( 'This content is locked', 'zeko-learn' ); ?></h2>
				<p><?php esc_html_e( 'Enroll in this course to access this lesson.', 'zeko-learn' ); ?></p>
				<?php
				/** Zeko PRO hook: extra lock context (e.g. an unlock date), no-op by default. */
				do_action( 'zeko_learn_access_locked_extra', $lesson_id, $course_id, $user_id );
				?>
				<?php if ( is_user_logged_in() ) : ?>
					<button class="button button-primary zeko-enroll-btn" data-course-id="<?php echo esc_attr( $course_id ); ?>"><?php esc_html_e( 'Enroll Now', 'zeko-learn' ); ?></button>
				<?php else : ?>
					<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="button button-primary"><?php esc_html_e( 'Log in to Enroll', 'zeko-learn' ); ?></a>
				<?php endif; ?>
			</div>

		<?php else : ?>

			<!-- Lesson Header -->
			<div class="zeko-lesson-header">
				<h1><?php echo esc_html( $lesson['title'] ); ?></h1>
				<div class="zeko-lesson-meta">
					<?php if ( $lesson['estimated_minutes'] ) : ?>
						<span class="dashicons dashicons-clock"></span> <?php echo esc_html( $lesson['estimated_minutes'] ); ?> <?php esc_html_e( 'min', 'zeko-learn' ); ?>
					<?php endif; ?>
					<span class="zeko-lesson-type-badge"><?php echo esc_html( ucfirst( $lesson_type ) ); ?></span>
				</div>
			</div>

			<!-- Lesson Content by Type -->
			<div class="zeko-lesson-body">

				<?php if ( 'video' === $lesson_type ) : ?>
					<div class="zeko-video-wrapper">
						<?php
						$embed = $lesson['video_url'] ? zeko_learn_render_video_embed( $lesson['video_url'] ) : '';
						if ( '' !== $embed ) :
							echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						elseif ( $lesson['video_url'] ) :
							?>
							<video controls>
								<source src="<?php echo esc_url( $lesson['video_url'] ); ?>">
							</video>
						<?php endif; ?>
					</div>
					<?php if ( $lesson['content'] ) : ?>
						<div class="zeko-lesson-text"><?php echo wp_kses_post( $lesson['content'] ); ?></div>
					<?php endif; ?>

				<?php elseif ( 'quiz' === $lesson_type && $quiz ) : ?>
					<div class="zeko-quiz-container" id="zeko-quiz-container" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
						<div class="zeko-quiz-instructions" id="zeko-quiz-instructions">
							<h2><?php echo esc_html( $quiz['title'] ); ?></h2>
							<?php if ( $quiz['description'] ) : ?>
								<div class="zeko-quiz-desc"><?php echo wp_kses_post( $quiz['description'] ); ?></div>
							<?php endif; ?>
							<div class="zeko-quiz-info">
								<p><strong><?php esc_html_e( 'Time Limit:', 'zeko-learn' ); ?></strong> <?php echo $quiz['time_limit_minutes'] ? esc_html( $quiz['time_limit_minutes'] . ' minutes' ) : esc_html__( 'No time limit', 'zeko-learn' ); ?></p>
								<p><strong><?php esc_html_e( 'Passing Score:', 'zeko-learn' ); ?></strong> <?php echo esc_html( $quiz['passing_score'] ); ?>%</p>
								<p><strong><?php esc_html_e( 'Max Attempts:', 'zeko-learn' ); ?></strong> <?php echo $quiz['max_attempts'] ? esc_html( $quiz['max_attempts'] ) : esc_html__( 'Unlimited', 'zeko-learn' ); ?></p>
							</div>
							<button class="button button-primary" id="zeko-start-quiz"><?php esc_html_e( 'Start Quiz', 'zeko-learn' ); ?></button>
						</div>

						<div class="zeko-quiz-active" id="zeko-quiz-active" style="display:none;">
							<div class="zeko-quiz-timer" id="zeko-quiz-timer"></div>
							<div class="zeko-quiz-progress" id="zeko-quiz-progress"></div>
							<div class="zeko-quiz-questions" id="zeko-quiz-questions"></div>
							<div class="zeko-quiz-nav">
								<button class="button" id="zeko-quiz-prev" disabled><?php esc_html_e( 'Previous', 'zeko-learn' ); ?></button>
								<button class="button button-primary" id="zeko-quiz-next"><?php esc_html_e( 'Next', 'zeko-learn' ); ?></button>
								<button class="button button-primary" id="zeko-quiz-submit" style="display:none;"><?php esc_html_e( 'Submit Quiz', 'zeko-learn' ); ?></button>
							</div>
						</div>

						<div class="zeko-quiz-results" id="zeko-quiz-results" style="display:none;"></div>
					</div>

				<?php elseif ( 'assignment' === $lesson_type && $assignment ) : ?>
					<div class="zeko-assignment-container">
						<h2><?php echo esc_html( $assignment['title'] ); ?></h2>
						<?php if ( $assignment['instructions'] ) : ?>
							<div class="zeko-assignment-instructions"><?php echo wp_kses_post( $assignment['instructions'] ); ?></div>
						<?php endif; ?>

						<div class="zeko-assignment-details">
							<?php if ( $assignment['max_file_size_mb'] ) : ?>
								<p><strong><?php esc_html_e( 'Max File Size:', 'zeko-learn' ); ?></strong> <?php echo esc_html( $assignment['max_file_size_mb'] ); ?> MB</p>
							<?php endif; ?>
							<?php if ( $assignment['allowed_file_types'] ) : ?>
								<p><strong><?php esc_html_e( 'Allowed File Types:', 'zeko-learn' ); ?></strong> <?php echo esc_html( $assignment['allowed_file_types'] ); ?></p>
							<?php endif; ?>
							<?php if ( $assignment['due_date'] ) : ?>
								<p><strong><?php esc_html_e( 'Due Date:', 'zeko-learn' ); ?></strong> <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $assignment['due_date'] ) ); ?></p>
							<?php endif; ?>
						</div>

						<?php
						if ( $is_enrolled || current_user_can( 'manage_options' ) ) :
							$user_submission = $user_id ? $db->get_user_assignment_submission( $user_id, (int) $assignment['id'] ) : null;
							?>
							<?php if ( $user_submission && 'submitted' === $user_submission['status'] ) : ?>
								<div class="zeko-assignment-submitted">
									<span class="dashicons dashicons-yes-alt"></span>
									<p><strong><?php esc_html_e( 'Submitted', 'zeko-learn' ); ?></strong> — <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $user_submission['submitted_at'] ) ); ?></p>
									<?php if ( $user_submission['notes'] ) : ?>
										<p class="zeko-submission-notes"><?php echo esc_html( $user_submission['notes'] ); ?></p>
									<?php endif; ?>
								</div>
							<?php elseif ( $user_submission && 'graded' === $user_submission['status'] ) : ?>
								<div class="zeko-assignment-graded">
									<h3><?php esc_html_e( 'Graded', 'zeko-learn' ); ?></h3>
									<p><strong><?php esc_html_e( 'Grade:', 'zeko-learn' ); ?></strong> <?php echo esc_html( $user_submission['grade'] ); ?></p>
									<?php if ( $user_submission['feedback'] ) : ?>
										<p><strong><?php esc_html_e( 'Feedback:', 'zeko-learn' ); ?></strong></p>
										<div class="zeko-feedback"><?php echo wp_kses_post( $user_submission['feedback'] ); ?></div>
									<?php endif; ?>
								</div>
							<?php else : ?>
								<form class="zeko-assignment-form" id="zeko-assignment-form">
									<input type="hidden" name="assignment_id" value="<?php echo esc_attr( $assignment['id'] ); ?>">
									<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>">
									<div class="zeko-file-upload">
										<label for="zeko-file-input"><?php esc_html_e( 'Upload your submission', 'zeko-learn' ); ?></label>
										<input type="file" id="zeko-file-input" name="submission_file" class="zeko-file-input">
										<div class="zeko-dropzone" id="zeko-dropzone">
											<span class="dashicons dashicons-upload"></span>
											<p><?php esc_html_e( 'Drag & drop your file here or click to browse', 'zeko-learn' ); ?></p>
										</div>
										<div class="zeko-file-name" id="zeko-file-name"></div>
									</div>
									<div class="zeko-notes-field">
										<label for="zeko-submission-notes"><?php esc_html_e( 'Notes (optional)', 'zeko-learn' ); ?></label>
										<textarea id="zeko-submission-notes" name="notes" rows="3" placeholder="<?php esc_attr_e( 'Add any notes about your submission...', 'zeko-learn' ); ?>"></textarea>
									</div>
									<button type="submit" class="button button-primary"><?php esc_html_e( 'Submit Assignment', 'zeko-learn' ); ?></button>
								</form>
							<?php endif; ?>
						<?php endif; ?>
					</div>

				<?php elseif ( 'download' === $lesson_type ) : ?>
					<div class="zeko-lesson-text"><?php echo wp_kses_post( $lesson['content'] ?? '' ); ?></div>
					<?php if ( $lesson['attachment_url'] ) : ?>
						<div class="zeko-download-box">
							<span class="dashicons dashicons-download"></span>
							<a href="<?php echo esc_url( $lesson['attachment_url'] ); ?>" class="button button-primary" download><?php esc_html_e( 'Download File', 'zeko-learn' ); ?></a>
						</div>
					<?php endif; ?>

				<?php else : ?>
					<div class="zeko-lesson-text">
						<?php echo wp_kses_post( $lesson['content'] ?? '' ); ?>
					</div>
				<?php endif; ?>

			</div>

			<!-- Lesson Actions -->
			<?php if ( $is_enrolled || current_user_can( 'manage_options' ) ) : ?>
				<div class="zeko-lesson-footer">
					<?php if ( 'quiz' !== $lesson_type && 'assignment' !== $lesson_type ) : ?>
						<?php if ( $is_complete ) : ?>
							<span class="zeko-completed-badge"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Completed', 'zeko-learn' ); ?></span>
						<?php else : ?>
							<button class="button button-primary zeko-complete-lesson-btn" data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>" data-course-id="<?php echo esc_attr( $course_id ); ?>" aria-label="<?php esc_attr_e( 'Mark lesson as complete', 'zeko-learn' ); ?>">
								<?php esc_html_e( 'Mark as Complete', 'zeko-learn' ); ?>
							</button>
						<?php endif; ?>
					<?php endif; ?>
					<button class="button zeko-bookmark-btn" data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>" data-course-id="<?php echo esc_attr( $course_id ); ?>">
						<span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Bookmark', 'zeko-learn' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<!-- Notes Panel -->
			<?php if ( $is_enrolled && 'video' === $lesson_type ) : ?>
				<div class="zeko-notes-panel">
					<h3><?php esc_html_e( 'My Notes', 'zeko-learn' ); ?></h3>
					<div class="zeko-notes-list" id="zeko-notes-list">
						<?php foreach ( $notes as $note ) : ?>
							<div class="zeko-note" data-note-id="<?php echo esc_attr( $note['id'] ); ?>">
								<span class="zeko-note-timestamp"><?php echo esc_html( gmdate( 'i:s', (int) $note['timestamp_seconds'] ) ); ?></span>
								<p><?php echo esc_html( $note['content'] ); ?></p>
								<button class="zeko-delete-note" data-note-id="<?php echo esc_attr( $note['id'] ); ?>">&times;</button>
							</div>
						<?php endforeach; ?>
					</div>
					<form class="zeko-note-form" id="zeko-note-form">
						<input type="hidden" name="lesson_id" value="<?php echo esc_attr( $lesson_id ); ?>">
						<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>">
						<input type="hidden" name="timestamp_seconds" id="zeko-note-timestamp" value="0">
						<textarea name="content" rows="2" placeholder="<?php esc_attr_e( 'Take a note at current timestamp...', 'zeko-learn' ); ?>" required></textarea>
						<button type="submit" class="button"><?php esc_html_e( 'Save Note', 'zeko-learn' ); ?></button>
					</form>
				</div>
			<?php endif; ?>

		<?php endif; // end can_access. ?>

		<!-- Navigation -->
		<div class="zeko-lesson-nav">
			<?php if ( $prev_lesson ) : ?>
				<a href="<?php echo esc_url( $lesson_url_base . $prev_lesson['section_slug'] . '/' . $prev_lesson['slug'] . '/' ); ?>" class="button zeko-nav-prev">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
					<?php echo esc_html( $prev_lesson['title'] ); ?>
				</a>
			<?php else : ?>
				<span></span>
			<?php endif; ?>
			<?php if ( $next_lesson ) : ?>
				<a href="<?php echo esc_url( $lesson_url_base . $next_lesson['section_slug'] . '/' . $next_lesson['slug'] . '/' ); ?>" class="button button-primary zeko-nav-next">
					<?php echo esc_html( $next_lesson['title'] ); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</a>
			<?php endif; ?>
		</div>
	</main>
</div>

<?php wp_footer(); ?>
</body>
</html>
