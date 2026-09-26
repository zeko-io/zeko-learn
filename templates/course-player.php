<?php
/**
 * Template: Course Player
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$sections    = $this->db->get_course_sections( (int) $course['id'] );
$all_lessons = $this->db->get_course_lessons( (int) $course['id'] );

// Determine current lesson from query vars or default to first.
$current_lesson = null;
$lesson_slug    = get_query_var( 'zeko_lesson_slug' );
if ( $lesson_slug ) {
	$slug = sanitize_title_for_query( $lesson_slug );
	foreach ( $all_lessons as $l ) {
		if ( sanitize_title( $l['slug'] ) === $slug || sanitize_title( $l['title'] ) === $slug ) {
			$current_lesson = $l;
			break;
		}
	}
}
if ( ! $current_lesson && ! empty( $all_lessons ) ) {
	$current_lesson = $all_lessons[0];
}

// Get user progress.
$user_id         = get_current_user_id();
$lesson_progress = array();
if ( $user_id ) {
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT lesson_id, status FROM {$wpdb->prefix}zeko_lesson_progress WHERE user_id = %d AND course_id = %d",
			$user_id,
			$course['id']
		),
		ARRAY_A
	);
	foreach ( $rows as $row ) {
		$lesson_progress[ $row['lesson_id'] ] = $row['status'];
	}
}

$course_progress = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}zeko_course_progress WHERE user_id = %d AND course_id = %d",
		$user_id,
		$course['id']
	),
	ARRAY_A
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $course['title'] ); ?> — <?php esc_html_e( 'Learn', 'zeko-learn' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'zeko-learn-body' ); ?>>
<?php wp_body_open(); ?>

<div class="zeko-learn-player">
	<!-- Sidebar -->
	<aside class="zeko-player-sidebar">
		<div class="zeko-player-sidebar-header">
			<a href="<?php echo esc_url( home_url( '/courses/' . $course['slug'] . '/' ) ); ?>" class="zeko-sidebar-back">&larr; <?php esc_html_e( 'Course Page', 'zeko-learn' ); ?></a>
			<h3><?php echo esc_html( $course['title'] ); ?></h3>
			<?php if ( $course_progress ) : ?>
			<div class="zeko-sidebar-progress">
				<div class="zeko-progress-bar"><div class="zeko-progress-fill" style="width:<?php echo esc_attr( $course_progress['completion_pct'] ); ?>%"></div></div>
				<span><?php echo esc_html( round( (float) $course_progress['completion_pct'] ) ); ?>% <?php esc_html_e( 'complete', 'zeko-learn' ); ?></span>
			</div>
			<?php endif; ?>
		</div>

		<nav class="zeko-curriculum-nav">
			<?php
			foreach ( $sections as $section ) :
				$s_lessons = $this->db->get_section_lessons( (int) $section['id'] );
				?>
			<div class="zeko-nav-section">
				<div class="zeko-nav-section-title"><?php echo esc_html( $section['title'] ); ?></div>
				<ul>
					<?php
					foreach ( $s_lessons as $sl ) :
						$item_status     = $lesson_progress[ $sl['id'] ] ?? 'not_started';
						$icon       = 'completed' === $item_status ? '&#10003;' : ( 'in_progress' === $item_status ? '&#9654;' : '&#9675;' );
						$cls        = 'completed' === $item_status ? ' completed' : '';
						$is_current = $current_lesson && (int) $sl['id'] === (int) $current_lesson['id'];
						$cls       .= $is_current ? ' current' : '';
						$url        = home_url( '/courses/' . $course['slug'] . '/learn/' . sanitize_title( $section['title'] ) . '/' . $sl['slug'] . '/' );
						?>
					<li class="zeko-nav-lesson<?php echo esc_attr( $cls ); ?>">
						<a href="<?php echo esc_url( $url ); ?>">
							<span class="zeko-nav-icon"><?php echo wp_kses( $icon, array() ); ?></span>
							<span class="zeko-nav-title"><?php echo esc_html( $sl['title'] ); ?></span>
							<?php if ( 'text' === $sl['lesson_type'] ) : ?>
								<span class="zeko-nav-type">&#128196;</span>
							<?php elseif ( 'video' === $sl['lesson_type'] ) : ?>
								<span class="zeko-nav-type">&#127909;</span>
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
	<main class="zeko-player-content">
		<?php if ( ! $current_lesson ) : ?>
			<div class="zeko-player-empty">
				<h2><?php esc_html_e( 'No lessons available', 'zeko-learn' ); ?></h2>
				<p><?php esc_html_e( 'This course does not have any lessons yet.', 'zeko-learn' ); ?></p>
			</div>
			<?php
		else :
			$prev_lesson = null;
			$next_lesson = null;
			$found       = false;
			foreach ( $all_lessons as $idx => $l ) {
				if ( (int) $l['id'] === (int) $current_lesson['id'] ) {
					$found       = true;
					$prev_lesson = $idx > 0 ? $all_lessons[ $idx - 1 ] : null;
					continue;
				}
				if ( $found ) {
					$next_lesson = $l;
					break;
				}
			}
			?>
		<div class="zeko-lesson-wrapper">
			<div class="zeko-lesson-header">
				<h2><?php echo esc_html( $current_lesson['title'] ); ?></h2>
				<?php if ( $current_lesson['estimated_minutes'] ) : ?>
					<span class="zeko-lesson-time"><?php echo esc_html( $current_lesson['estimated_minutes'] ); ?> <?php esc_html_e( 'min', 'zeko-learn' ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( 'video' === $current_lesson['lesson_type'] && $current_lesson['video_url'] ) : ?>
				<div class="zeko-video-container">
					<?php echo zeko_learn_render_video_embed( $current_lesson['video_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php elseif ( $current_lesson['content'] ) : ?>
				<div class="zeko-lesson-content"><?php echo wp_kses_post( $current_lesson['content'] ); ?></div>
			<?php endif; ?>

			<?php if ( $current_lesson['attachment_url'] ) : ?>
				<div class="zeko-lesson-attachment">
					<a href="<?php echo esc_url( $current_lesson['attachment_url'] ); ?>" class="button" download>
						&#128206; <?php esc_html_e( 'Download Attachment', 'zeko-learn' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="zeko-lesson-actions">
				<button class="button button-primary zeko-complete-lesson-btn"
					data-lesson-id="<?php echo esc_attr( $current_lesson['id'] ); ?>"
					data-course-id="<?php echo esc_attr( $course['id'] ); ?>"
					aria-label="<?php esc_attr_e( 'Mark lesson as complete', 'zeko-learn' ); ?>"
					<?php echo esc_attr( 'completed' === ( $lesson_progress[ $current_lesson['id'] ] ?? '' ) ? 'disabled' : '' ); ?>>
					<?php
					echo 'completed' === ( $lesson_progress[ $current_lesson['id'] ] ?? '' )
						? esc_html__( 'Completed', 'zeko-learn' )
						: esc_html__( 'Mark as Complete', 'zeko-learn' );
					?>
				</button>
				<button class="button zeko-bookmark-btn"
					data-lesson-id="<?php echo esc_attr( $current_lesson['id'] ); ?>"
					data-course-id="<?php echo esc_attr( $course['id'] ); ?>">
					&#128278; <?php esc_html_e( 'Bookmark', 'zeko-learn' ); ?>
				</button>
			</div>

			<div class="zeko-lesson-nav">
				<div class="zeko-nav-prev">
					<?php if ( $prev_lesson ) : ?>
						<a href="<?php echo esc_url( home_url( '/courses/' . $course['slug'] . '/learn/' . $prev_lesson['slug'] . '/' ) ); ?>" class="button">
							&larr; <?php echo esc_html( $prev_lesson['title'] ); ?>
						</a>
					<?php endif; ?>
				</div>
				<div class="zeko-nav-next">
					<?php if ( $next_lesson ) : ?>
						<a href="<?php echo esc_url( home_url( '/courses/' . $course['slug'] . '/learn/' . $next_lesson['slug'] . '/' ) ); ?>" class="button button-primary">
							<?php echo esc_html( $next_lesson['title'] ); ?> &rarr;
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</main>
</div>

<?php wp_footer(); ?>
</body>
</html>
