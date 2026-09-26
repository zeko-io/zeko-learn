<?php
/**
 * Template: Instructor Dashboard (Frontend)
 *
 * Full-featured instructor hub with card-based UI: stats, course management,
 * students, revenue, announcements, and advanced settings.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id = get_current_user_id();
if ( ! $user_id || ! current_user_can( 'publish_posts' ) ) {
	echo '<div class="zeko-learn-container"><div class="zeko-empty-state"><span class="dashicons dashicons-lock"></span><p>' . esc_html__( 'You do not have instructor access.', 'zeko-learn' ) . '</p></div></div>';
	return;
}

$user = get_userdata( $user_id );
if ( ! $user ) {
	echo '<p>' . esc_html__( 'User not found.', 'zeko-learn' ) . '</p>';
	return;
}
$stats    = $this->db->get_instructor_stats( $user_id );
$courses  = $this->db->get_courses(
	array(
		'instructor_id' => $user_id,
		'status'        => '',
		'limit'         => 50,
	)
);
$payouts  = $this->db->get_instructor_payouts( $user_id, 10 );
$currency = get_option( 'zeko_learn_currency_symbol', '$' );

$active_tab        = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only dashboard tab flag; sanitized and allow-listed against $valid_tabs below.
$valid_tabs = array( 'overview', 'courses', 'create', 'students', 'revenue', 'announcements', 'settings' );
if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
	$active_tab = 'overview';
}

// Load all instructor settings up front.
$inst      = array();
$meta_keys = array(
	'bio',
	'expertise',
	'display_name',
	'hourly_rate',
	'teaching_style',
	'payout_method',
	'payout_details',
	'default_course_level',
	'website',
	'linkedin',
	'twitter',
	'youtube',
	'github',
	'notify_on_enrollment',
	'notify_on_review',
	'notify_on_question',
	'notify_on_completion',
	'weekly_digest',
);
foreach ( $meta_keys as $mk ) {
	$inst[ $mk ] = get_user_meta( $user_id, 'zeko_learn_instructor_' . $mk, true );
}

// Pending submissions count.
global $wpdb;
$course_ids          = array_column( $courses, 'id' );
$pending_count       = 0;
$pending_submissions = array();
if ( ! empty( $course_ids ) ) {
	$placeholders        = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );
	$pending_count       = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}zeko_assignment_submissions s
			INNER JOIN {$wpdb->prefix}zeko_assignments a ON s.assignment_id = a.id
			INNER JOIN {$wpdb->prefix}zeko_courses c ON a.course_id = c.id
			WHERE c.instructor_id = %d AND s.status = 'submitted'",
			$user_id
		)
	);
	$pending_submissions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT s.*, a.title AS assignment_title, c.title AS course_title, u.display_name AS student_name
			FROM {$wpdb->prefix}zeko_assignment_submissions s
			INNER JOIN {$wpdb->prefix}zeko_assignments a ON s.assignment_id = a.id
			INNER JOIN {$wpdb->prefix}zeko_courses c ON a.course_id = c.id
			INNER JOIN {$wpdb->users} u ON s.user_id = u.ID
			WHERE c.instructor_id = %d AND s.status = 'submitted'
			ORDER BY s.submitted_at DESC LIMIT 20",
			$user_id
		),
		ARRAY_A
	) ?: array();
}

// Students across all courses.
$all_students = array();
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
if ( ! empty( $course_ids ) ) {
	$all_students = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT e.*, c.title AS course_title, u.display_name AS student_name, u.user_email AS student_email,
				 lp.completion_pct, lp.completed_lessons, lp.total_lessons
			FROM {$wpdb->prefix}zeko_enrollments e
			INNER JOIN {$wpdb->prefix}zeko_courses c ON e.course_id = c.id
			INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
			LEFT JOIN {$wpdb->prefix}zeko_course_progress lp ON lp.user_id = e.user_id AND lp.course_id = e.course_id
			WHERE e.course_id IN ({$placeholders})
			ORDER BY e.enrolled_at DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
			...$course_ids
		),
		ARRAY_A
	) ?: array();
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
}

// Announcements for instructor's courses.
$announcements = array();
if ( ! empty( $course_ids ) ) {
	$announcements = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.*, c.title AS course_title
			FROM {$wpdb->prefix}zeko_course_announcements a
			INNER JOIN {$wpdb->prefix}zeko_courses c ON a.course_id = c.id
			WHERE c.instructor_id = %d
			ORDER BY a.created_at DESC LIMIT 20",
			$user_id
		),
		ARRAY_A
	) ?: array();
}
?>
<div class="zeko-instructor-dashboard">

	<!-- ── Profile Hero Card ─────────────────────────────────── -->
	<div class="zeko-inst-hero-card">
		<div class="zeko-inst-hero-bg"></div>
		<div class="zeko-inst-hero-content">
			<div class="zeko-inst-hero-avatar">
				<?php echo get_avatar( $user_id, 96, '', '', array( 'class' => 'zeko-inst-avatar-lg' ) ); ?>
			</div>
			<div class="zeko-inst-hero-info">
				<h1><?php echo esc_html( $inst['display_name'] ? $inst['display_name'] : $user->display_name ); ?></h1>
				<?php if ( $inst['expertise'] ) : ?>
					<p class="zeko-inst-hero-expertise"><?php echo esc_html( $inst['expertise'] ); ?></p>
				<?php endif; ?>
				<?php if ( $inst['bio'] ) : ?>
					<p class="zeko-inst-hero-bio"><?php echo esc_html( wp_trim_words( $inst['bio'], 30 ) ); ?></p>
				<?php endif; ?>
				<div class="zeko-inst-hero-badges">
					<?php if ( $inst['hourly_rate'] ) : ?>
						<span class="zeko-inst-badge-chip">
							<span class="dashicons dashicons-money-alt"></span>
							<?php echo esc_html( $currency . number_format( (float) $inst['hourly_rate'], 0 ) ); ?>/hr
						</span>
					<?php endif; ?>
					<span class="zeko-inst-badge-chip zeko-badge-courses">
						<span class="dashicons dashicons-welcome-learn-more"></span>
						<?php echo esc_html( $stats['total_courses'] ); ?> <?php esc_html_e( 'courses', 'zeko-learn' ); ?>
					</span>
					<span class="zeko-inst-badge-chip zeko-badge-students">
						<span class="dashicons dashicons-groups"></span>
						<?php echo esc_html( $stats['total_students'] ); ?> <?php esc_html_e( 'students', 'zeko-learn' ); ?>
					</span>
					<?php
					$social_urls  = array(
						'website'  => $inst['website'],
						'linkedin' => $inst['linkedin'],
						'twitter'  => $inst['twitter'],
						'youtube'  => $inst['youtube'],
						'github'   => $inst['github'],
					);
					$social_icons = array(
						'website'  => 'admin-links',
						'linkedin' => 'linkedin',
						'twitter'  => 'twitter',
						'youtube'  => 'video-alt3',
						'github'   => 'backup',
					);
					foreach ( $social_urls as $key => $url ) :
						if ( ! empty( $url ) ) :
							?>
							<a href="<?php echo esc_url( $url ); ?>" class="zeko-inst-badge-chip zeko-badge-social" target="_blank" rel="noopener">
								<span class="dashicons dashicons-<?php echo esc_attr( $social_icons[ $key ] ); ?>"></span>
							</a>
							<?php
						endif;
					endforeach;
					?>
				</div>
			</div>
			<div class="zeko-inst-hero-actions">
				<a href="?tab=settings" class="button zeko-btn-outline-white">
					<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Edit Profile', 'zeko-learn' ); ?>
				</a>
				<a href="?tab=create" class="button button-primary">
					<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'New Course', 'zeko-learn' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- ── Stats Cards ────────────────────────────────────────── -->
	<div class="zeko-inst-stats-grid">
		<div class="zeko-inst-stat-card">
			<div class="zeko-inst-stat-icon" style="background:rgba(26,35,126,0.08);color:var(--zl-primary);">
				<span class="dashicons dashicons-welcome-learn-more"></span>
			</div>
			<div class="zeko-inst-stat-content">
				<strong><?php echo esc_html( $stats['total_courses'] ); ?></strong>
				<span><?php esc_html_e( 'Total Courses', 'zeko-learn' ); ?></span>
			</div>
		</div>
		<div class="zeko-inst-stat-card">
			<div class="zeko-inst-stat-icon" style="background:rgba(22,163,74,0.08);color:#16a34a;">
				<span class="dashicons dashicons-groups"></span>
			</div>
			<div class="zeko-inst-stat-content">
				<strong><?php echo esc_html( $stats['total_students'] ); ?></strong>
				<span><?php esc_html_e( 'Total Students', 'zeko-learn' ); ?></span>
			</div>
		</div>
		<div class="zeko-inst-stat-card">
			<div class="zeko-inst-stat-icon" style="background:rgba(234,179,8,0.08);color:#ca8a04;">
				<span class="dashicons dashicons-star-filled"></span>
			</div>
			<div class="zeko-inst-stat-content">
				<strong><?php echo esc_html( number_format( (float) $stats['avg_rating'], 1 ) ); ?></strong>
				<span><?php esc_html_e( 'Avg Rating', 'zeko-learn' ); ?></span>
			</div>
		</div>
		<div class="zeko-inst-stat-card">
			<div class="zeko-inst-stat-icon" style="background:rgba(124,58,237,0.08);color:#7c3aed;">
				<span class="dashicons dashicons-money-alt"></span>
			</div>
			<div class="zeko-inst-stat-content">
				<strong><?php echo esc_html( $currency . number_format( (float) $stats['total_revenue'], 2 ) ); ?></strong>
				<span><?php esc_html_e( 'Total Revenue', 'zeko-learn' ); ?></span>
			</div>
		</div>
		<?php if ( $pending_count > 0 ) : ?>
		<div class="zeko-inst-stat-card zeko-inst-stat-alert">
			<div class="zeko-inst-stat-icon" style="background:rgba(220,38,38,0.08);color:#dc2626;">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="zeko-inst-stat-content">
				<strong><?php echo esc_html( $pending_count ); ?></strong>
				<span><?php esc_html_e( 'Pending Reviews', 'zeko-learn' ); ?></span>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<!-- ── Tab Navigation ─────────────────────────────────────── -->
	<div class="zeko-inst-tabs">
		<nav class="zeko-inst-tab-nav" role="tablist" aria-label="<?php esc_attr_e( 'Instructor dashboard sections', 'zeko-learn' ); ?>">
			<a href="?tab=overview" class="zeko-inst-tab-btn <?php echo 'overview' === $active_tab ? 'active' : ''; ?>" role="tab">
				<span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Overview', 'zeko-learn' ); ?>
			</a>
			<a href="?tab=courses" class="zeko-inst-tab-btn <?php echo 'courses' === $active_tab ? 'active' : ''; ?>" role="tab">
				<span class="dashicons dashicons-welcome-learn-more"></span> <?php esc_html_e( 'Courses', 'zeko-learn' ); ?>
			</a>
			<a href="?tab=create" class="zeko-inst-tab-btn <?php echo 'create' === $active_tab ? 'active' : ''; ?>" role="tab">
				<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Create', 'zeko-learn' ); ?>
			</a>
			<a href="?tab=students" class="zeko-inst-tab-btn <?php echo 'students' === $active_tab ? 'active' : ''; ?>" role="tab">
				<span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Students', 'zeko-learn' ); ?>
			</a>
			<a href="?tab=revenue" class="zeko-inst-tab-btn <?php echo 'revenue' === $active_tab ? 'active' : ''; ?>" role="tab">
				<span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Revenue', 'zeko-learn' ); ?>
			</a>
			<a href="?tab=announcements" class="zeko-inst-tab-btn <?php echo 'announcements' === $active_tab ? 'active' : ''; ?>" role="tab">
				<span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Announcements', 'zeko-learn' ); ?>
			</a>
			<a href="?tab=settings" class="zeko-inst-tab-btn <?php echo 'settings' === $active_tab ? 'active' : ''; ?>" role="tab">
				<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Settings', 'zeko-learn' ); ?>
			</a>
		</nav>
	</div>

	<!-- ── Tab Content ────────────────────────────────────────── -->
	<div class="zeko-inst-tab-content">

	<?php if ( 'overview' === $active_tab ) : ?>
		<!-- ═══ Overview Tab ═══════════════════════════════════════ -->
		<div class="zeko-inst-panel">

			<!-- Pending Submissions Card -->
			<div class="zeko-inst-card">
				<div class="zeko-inst-card-header">
					<div class="zeko-inst-card-title">
						<span class="dashicons dashicons-clipboard"></span>
						<h2><?php esc_html_e( 'Pending Assignment Reviews', 'zeko-learn' ); ?></h2>
					</div>
					<?php if ( $pending_count > 0 ) : ?>
						<span class="zeko-inst-badge"><?php echo esc_html( $pending_count ); ?></span>
					<?php endif; ?>
				</div>
				<div class="zeko-inst-card-body">
					<?php if ( empty( $pending_submissions ) ) : ?>
						<div class="zeko-inst-empty-inline">
							<span class="dashicons dashicons-yes-alt"></span>
							<p><?php esc_html_e( 'All caught up! No pending submissions.', 'zeko-learn' ); ?></p>
						</div>
					<?php else : ?>
						<div class="zeko-inst-table-wrap">
							<table class="zeko-inst-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Student', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Assignment', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Course', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Submitted', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Action', 'zeko-learn' ); ?></th>
									</tr>
								</thead>
								<tbody>
								<?php foreach ( $pending_submissions as $sub ) : ?>
									<tr>
										<td>
											<div class="zeko-inst-student-cell">
												<?php echo get_avatar( $sub['user_id'] ?? 0, 32 ); ?>
												<span><?php echo esc_html( $sub['student_name'] ); ?></span>
											</div>
										</td>
										<td><?php echo esc_html( $sub['assignment_title'] ); ?></td>
										<td><?php echo esc_html( $sub['course_title'] ); ?></td>
										<td><?php echo esc_html( human_time_diff( strtotime( $sub['submitted_at'] ) ) . ' ago' ); ?></td>
										<td>
											<div class="zeko-inst-row-actions">
												<?php if ( ! empty( $sub['file_url'] ) ) : ?>
													<a href="<?php echo esc_url( $sub['file_url'] ); ?>" class="button button-small" target="_blank">
														<span class="dashicons dashicons-download"></span>
													</a>
												<?php endif; ?>
												<button type="button" class="button button-small button-primary zeko-grade-btn"
													data-submission-id="<?php echo esc_attr( $sub['id'] ); ?>"
													data-student="<?php echo esc_attr( $sub['student_name'] ); ?>"
													data-assignment="<?php echo esc_attr( $sub['assignment_title'] ); ?>">
													<span class="dashicons dashicons-clipboard"></span>
												</button>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Recent Enrollments Card -->
			<div class="zeko-inst-card">
				<div class="zeko-inst-card-header">
					<div class="zeko-inst-card-title">
						<span class="dashicons dashicons-groups"></span>
						<h2><?php esc_html_e( 'Recent Enrollments', 'zeko-learn' ); ?></h2>
					</div>
					<button type="button" class="button button-small zeko-export-grades" data-course-id="0">
						<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export CSV', 'zeko-learn' ); ?>
					</button>
				</div>
				<div class="zeko-inst-card-body">
					<?php if ( empty( $all_students ) ) : ?>
						<div class="zeko-inst-empty-inline">
							<span class="dashicons dashicons-groups"></span>
							<p><?php esc_html_e( 'No students enrolled yet.', 'zeko-learn' ); ?></p>
						</div>
					<?php else : ?>
						<div class="zeko-inst-table-wrap">
							<table class="zeko-inst-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Student', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Course', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Progress', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Status', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Enrolled', 'zeko-learn' ); ?></th>
									</tr>
								</thead>
								<tbody>
								<?php
								foreach ( array_slice( $all_students, 0, 10 ) as $search_term ) :
									$pct = (float) ( $search_term['completion_pct'] ?? 0 );
									?>
									<tr>
										<td>
											<div class="zeko-inst-student-cell">
												<?php echo get_avatar( $search_term['user_id'], 32 ); ?>
												<div>
													<strong><?php echo esc_html( $search_term['student_name'] ); ?></strong>
													<small><?php echo esc_html( $search_term['student_email'] ); ?></small>
												</div>
											</div>
										</td>
										<td><?php echo esc_html( $search_term['course_title'] ); ?></td>
										<td>
											<div class="zeko-inst-progress-mini">
												<div class="zeko-inst-progress-bar"><div class="zeko-inst-progress-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
												<small><?php echo esc_html( round( $pct ) ); ?>%</small>
											</div>
										</td>
										<td>
											<span class="zeko-status-badge zeko-status-<?php echo esc_attr( $search_term['status'] ); ?>">
												<?php echo esc_html( ucfirst( $search_term['status'] ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( human_time_diff( strtotime( $search_term['enrolled_at'] ) ) . ' ago' ); ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

	<?php elseif ( 'courses' === $active_tab ) : ?>
		<!-- ═══ Courses Tab ═════════════════════════════════════════ -->
		<div class="zeko-inst-panel">
			<div class="zeko-inst-card">
				<div class="zeko-inst-card-header">
					<div class="zeko-inst-card-title">
						<span class="dashicons dashicons-welcome-learn-more"></span>
						<h2><?php esc_html_e( 'My Courses', 'zeko-learn' ); ?></h2>
					</div>
					<a href="?tab=create" class="button button-primary button-small">
						<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'New Course', 'zeko-learn' ); ?>
					</a>
				</div>
				<div class="zeko-inst-card-body">
					<?php if ( empty( $courses ) ) : ?>
						<div class="zeko-inst-empty-state">
							<div class="zeko-inst-empty-icon">
								<span class="dashicons dashicons-welcome-learn-more"></span>
							</div>
							<h3><?php esc_html_e( 'No courses yet', 'zeko-learn' ); ?></h3>
							<p><?php esc_html_e( 'Start creating courses to share your knowledge with the world.', 'zeko-learn' ); ?></p>
							<a href="?tab=create" class="button button-primary">
								<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Create Your First Course', 'zeko-learn' ); ?>
							</a>
						</div>
					<?php else : ?>
						<div class="zeko-inst-courses-grid">
						<?php
						foreach ( $courses as $course ) :
							$lesson_count = $this->db->count_lessons( (int) $course['id'] );
							$thumb        = ! empty( $course['thumbnail_id'] ) ? wp_get_attachment_url( $course['thumbnail_id'] ) : '';
							?>
							<div class="zeko-inst-course-card">
								<div class="zeko-inst-course-thumb">
									<?php if ( $thumb ) : ?>
										<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $course['title'] ); ?>">
									<?php else : ?>
										<div class="zeko-inst-course-thumb-placeholder">
											<span class="dashicons dashicons-welcome-learn-more"></span>
										</div>
									<?php endif; ?>
									<span class="zeko-status-badge zeko-status-<?php echo esc_attr( $course['status'] ); ?>">
										<?php echo esc_html( ucfirst( $course['status'] ) ); ?>
									</span>
								</div>
								<div class="zeko-inst-course-body">
									<h3><?php echo esc_html( $course['title'] ); ?></h3>
									<div class="zeko-inst-course-meta">
										<span><span class="dashicons dashicons-groups"></span> <?php echo esc_html( $course['enrollment_count'] ); ?></span>
										<span><span class="dashicons dashicons-superhero"></span> <?php echo esc_html( $lesson_count ); ?></span>
										<span><span class="dashicons dashicons-star-filled"></span> <?php echo esc_html( number_format( (float) $course['avg_rating'], 1 ) ); ?> (<?php echo esc_html( $course['review_count'] ); ?>)</span>
									</div>
									<div class="zeko-inst-course-price">
										<?php if ( $course['is_free'] ) : ?>
											<span class="zeko-free-badge"><?php esc_html_e( 'Free', 'zeko-learn' ); ?></span>
										<?php else : ?>
											<strong><?php echo esc_html( $currency . number_format( (float) $course['price'], 2 ) ); ?></strong>
										<?php endif; ?>
									</div>
									<div class="zeko-inst-course-actions">
										<a href="?tab=create&course_id=<?php echo esc_attr( $course['id'] ); ?>" class="button button-small">
											<span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'zeko-learn' ); ?>
										</a>
										<button type="button" class="button button-small zeko-toggle-course-status" data-course-id="<?php echo esc_attr( $course['id'] ); ?>" data-current-status="<?php echo esc_attr( $course['status'] ); ?>">
											<span class="dashicons dashicons-visibility"></span>
											<?php echo 'published' === $course['status'] ? esc_html__( 'Unpublish', 'zeko-learn' ) : esc_html__( 'Publish', 'zeko-learn' ); ?>
										</button>
										<button type="button" class="button button-small zeko-duplicate-course" data-course-id="<?php echo esc_attr( $course['id'] ); ?>">
											<span class="dashicons dashicons-admin-page"></span>
										</button>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php
	elseif ( 'create' === $active_tab ) :
		$course_id_to_edit = absint( wp_unslash( $_GET['course_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit flag; absint() coerced and ownership re-checked against the current instructor below.
		$edit_course       = $course_id_to_edit ? $this->db->get_course( $course_id_to_edit ) : null;
		$categories        = $this->db->get_categories();
		if ( $edit_course && (int) $edit_course['instructor_id'] !== $user_id ) {
			$edit_course       = null;
			$course_id_to_edit = 0;
		}
		$sections         = $course_id_to_edit ? $this->db->get_course_sections( $course_id_to_edit ) : array();
		$course_skills    = $course_id_to_edit ? $this->db->get_course_skills( $course_id_to_edit ) : array();
		$course_skill_ids = array_column( $course_skills, 'id' );
		?>
		<!-- ═══ Create / Edit Tab (5-Tab Form) ═══════════════════ -->
		<div class="zeko-inst-panel">
			<div class="zeko-inst-card">
				<div class="zeko-inst-card-header">
					<div class="zeko-inst-card-title">
						<span class="dashicons dashicons-plus-alt2"></span>
						<h2><?php echo $course_id_to_edit ? esc_html__( 'Edit Course', 'zeko-learn' ) : esc_html__( 'Create New Course', 'zeko-learn' ); ?></h2>
					</div>
					<?php if ( $course_id_to_edit ) : ?>
						<span class="zeko-status-badge zeko-status-<?php echo esc_attr( $edit_course['status'] ?? 'draft' ); ?>"><?php echo esc_html( ucfirst( $edit_course['status'] ?? 'draft' ) ); ?></span>
					<?php endif; ?>
				</div>

				<!-- Sub-Tab Navigation -->
				<div class="zeko-cr-tabs">
					<button type="button" class="zeko-cr-tab-btn active" data-cr-tab="basic">
						<span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Basic Info', 'zeko-learn' ); ?>
					</button>
					<button type="button" class="zeko-cr-tab-btn" data-cr-tab="curriculum">
						<span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Curriculum', 'zeko-learn' ); ?>
					</button>
					<button type="button" class="zeko-cr-tab-btn" data-cr-tab="pricing">
						<span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Pricing', 'zeko-learn' ); ?>
					</button>
					<button type="button" class="zeko-cr-tab-btn" data-cr-tab="media">
						<span class="dashicons dashicons-format-image"></span> <?php esc_html_e( 'Media', 'zeko-learn' ); ?>
					</button>
					<button type="button" class="zeko-cr-tab-btn" data-cr-tab="seo">
						<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'SEO & Details', 'zeko-learn' ); ?>
					</button>
				</div>

				<form id="zeko-create-course-form">
					<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id_to_edit ); ?>">

					<!-- ═══ Tab: Basic Info ═══════════════════════════════ -->
					<div class="zeko-cr-tab-panel active" data-cr-panel="basic">
						<div class="zeko-inst-card-body">
							<div class="zeko-inst-form-row">
								<div class="zeko-inst-form-group">
									<label for="zl-course-title"><?php esc_html_e( 'Course Title', 'zeko-learn' ); ?> <span class="zeko-required">*</span></label>
									<input type="text" id="zl-course-title" name="title" required value="<?php echo esc_attr( $edit_course['title'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Complete Web Development Bootcamp', 'zeko-learn' ); ?>">
								</div>
								<div class="zeko-inst-form-group">
									<label for="zl-course-subtitle"><?php esc_html_e( 'Subtitle', 'zeko-learn' ); ?></label>
									<input type="text" id="zl-course-subtitle" name="subtitle" value="<?php echo esc_attr( $edit_course['subtitle'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Learn web development from scratch', 'zeko-learn' ); ?>">
								</div>
							</div>

							<div class="zeko-inst-form-group">
								<label for="zl-course-description"><?php esc_html_e( 'Description', 'zeko-learn' ); ?></label>
								<div class="zeko-rich-editor">
									<div class="zeko-rich-toolbar" data-target="zl-course-description">
										<button type="button" data-cmd="bold" title="<?php esc_attr_e( 'Bold', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-bold"></span></button>
										<button type="button" data-cmd="italic" title="<?php esc_attr_e( 'Italic', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-italic"></span></button>
										<button type="button" data-cmd="underline" title="<?php esc_attr_e( 'Underline', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-underline"></span></button>
										<button type="button" data-cmd="insertUnorderedList" title="<?php esc_attr_e( 'Bullet List', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-ul"></span></button>
										<button type="button" data-cmd="insertOrderedList" title="<?php esc_attr_e( 'Numbered List', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-ol"></span></button>
										<button type="button" data-cmd="createLink" title="<?php esc_attr_e( 'Insert Link', 'zeko-learn' ); ?>"><span class="dashicons dashicons-admin-links"></span></button>
									</div>
									<div class="zeko-rich-content" id="zl-course-description" contenteditable="true" data-name="description"><?php echo wp_kses_post( $edit_course['description'] ?? '' ); ?></div>
									<textarea name="description" class="zeko-rich-hidden" aria-hidden="true"></textarea>
									<?php if ( class_exists( 'Zeko_AI_Writer_UI' ) ) : ?>
										<?php
										echo wp_kses_post(
											(string) Zeko_AI_Writer_UI::button(
												array(
													'preset' => 'course_description',
													'target' => 'textarea[name="description"]',
												)
											)
										);
										?>
									<?php endif; ?>
								</div>
							</div>

							<div class="zeko-inst-form-row zeko-form-row-4">
								<div class="zeko-inst-form-group">
									<label for="zl-course-category"><?php esc_html_e( 'Category', 'zeko-learn' ); ?></label>
									<select id="zl-course-category" name="category_id">
										<option value="0"><?php esc_html_e( '— Select —', 'zeko-learn' ); ?></option>
										<?php foreach ( $categories as $category_id ) : ?>
											<option value="<?php echo esc_attr( $category_id['id'] ); ?>" <?php selected( $edit_course['category_id'] ?? 0, $category_id['id'] ); ?>><?php echo esc_html( $category_id['name'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="zeko-inst-form-group">
									<label for="zl-course-level"><?php esc_html_e( 'Level', 'zeko-learn' ); ?></label>
									<select id="zl-course-level" name="level">
										<option value="beginner" <?php selected( $edit_course['level'] ?? $inst['default_course_level'] ?? 'beginner', 'beginner' ); ?>><?php esc_html_e( 'Beginner', 'zeko-learn' ); ?></option>
										<option value="intermediate" <?php selected( $edit_course['level'] ?? '', 'intermediate' ); ?>><?php esc_html_e( 'Intermediate', 'zeko-learn' ); ?></option>
										<option value="advanced" <?php selected( $edit_course['level'] ?? '', 'advanced' ); ?>><?php esc_html_e( 'Advanced', 'zeko-learn' ); ?></option>
										<option value="all_levels" <?php selected( $edit_course['level'] ?? '', 'all_levels' ); ?>><?php esc_html_e( 'All Levels', 'zeko-learn' ); ?></option>
									</select>
								</div>
								<div class="zeko-inst-form-group">
									<label for="zl-course-language"><?php esc_html_e( 'Language', 'zeko-learn' ); ?></label>
									<input type="text" id="zl-course-language" name="language" value="<?php echo esc_attr( $edit_course['language'] ?? 'en' ); ?>" placeholder="en">
								</div>
								<div class="zeko-inst-form-group">
									<label for="zl-course-hours"><?php esc_html_e( 'Est. Hours', 'zeko-learn' ); ?></label>
									<input type="number" id="zl-course-hours" name="estimated_hours" step="0.1" min="0" value="<?php echo esc_attr( $edit_course['estimated_hours'] ?? '' ); ?>" placeholder="0">
								</div>
							</div>

							<div class="zeko-inst-form-row">
								<div class="zeko-inst-form-group">
									<label for="zl-course-status"><?php esc_html_e( 'Status', 'zeko-learn' ); ?></label>
									<select id="zl-course-status" name="status">
										<option value="draft" <?php selected( $edit_course['status'] ?? 'draft', 'draft' ); ?>><?php esc_html_e( 'Draft', 'zeko-learn' ); ?></option>
										<option value="pending" <?php selected( $edit_course['status'] ?? '', 'pending' ); ?>><?php esc_html_e( 'Pending Review', 'zeko-learn' ); ?></option>
										<option value="published" <?php selected( $edit_course['status'] ?? '', 'published' ); ?>><?php esc_html_e( 'Published', 'zeko-learn' ); ?></option>
									</select>
								</div>
								<div class="zeko-inst-form-group zeko-checkbox-field">
									<label>
										<input type="checkbox" name="is_featured" value="1" <?php checked( $edit_course['is_featured'] ?? 0, 1 ); ?>>
										<?php esc_html_e( 'Featured Course', 'zeko-learn' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Featured courses are highlighted on the catalog page.', 'zeko-learn' ); ?></p>
								</div>
							</div>
						</div>
					</div>

					<!-- ═══ Tab: Curriculum ═══════════════════════════════ -->
					<div class="zeko-cr-tab-panel" data-cr-panel="curriculum">
						<div class="zeko-inst-card-body">
							<div class="zeko-inst-section-builder">
								<div id="zeko-sections-list">
								<?php
								foreach ( $sections as $sec ) :
									$lessons = $this->db->get_section_lessons( (int) $sec['id'] );
									?>
									<div class="zeko-inst-section-item" data-section-id="<?php echo esc_attr( $sec['id'] ); ?>">
										<div class="zeko-inst-section-item-header">
											<span class="dashicons dashicons-move zeko-drag-handle"></span>
											<input type="text" class="zeko-section-title-input" value="<?php echo esc_attr( $sec['title'] ); ?>" placeholder="<?php esc_attr_e( 'Section title', 'zeko-learn' ); ?>">
											<input type="text" class="zeko-section-desc-input" value="<?php echo esc_attr( $sec['description'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Section description (optional)', 'zeko-learn' ); ?>">
											<div class="zeko-inst-section-actions">
												<button type="button" class="button button-small zeko-save-section-btn" title="<?php esc_attr_e( 'Save', 'zeko-learn' ); ?>"><span class="dashicons dashicons-saved"></span></button>
												<button type="button" class="button button-small zeko-move-up" title="<?php esc_attr_e( 'Up', 'zeko-learn' ); ?>"><span class="dashicons dashicons-arrow-up"></span></button>
												<button type="button" class="button button-small zeko-move-down" title="<?php esc_attr_e( 'Down', 'zeko-learn' ); ?>"><span class="dashicons dashicons-arrow-down"></span></button>
												<button type="button" class="button button-small button-link-delete zeko-remove-section" title="<?php esc_attr_e( 'Delete', 'zeko-learn' ); ?>"><span class="dashicons dashicons-trash"></span></button>
											</div>
										</div>
										<div class="zeko-inst-lessons-list">
										<?php foreach ( $lessons as $les ) : ?>
											<div class="zeko-inst-lesson-item" data-lesson-id="<?php echo esc_attr( $les['id'] ); ?>">
												<div class="zeko-inst-lesson-item-header">
													<span class="dashicons dashicons-move zeko-drag-handle"></span>
													<input type="text" class="zeko-lesson-title-input" value="<?php echo esc_attr( $les['title'] ); ?>" placeholder="<?php esc_attr_e( 'Lesson title', 'zeko-learn' ); ?>">
													<select class="zeko-lesson-type-select">
														<option value="text" <?php selected( $les['lesson_type'], 'text' ); ?>><?php esc_html_e( 'Text', 'zeko-learn' ); ?></option>
														<option value="video" <?php selected( $les['lesson_type'], 'video' ); ?>><?php esc_html_e( 'Video', 'zeko-learn' ); ?></option>
														<option value="url" <?php selected( $les['lesson_type'], 'url' ); ?>><?php esc_html_e( 'URL', 'zeko-learn' ); ?></option>
														<option value="quiz" <?php selected( $les['lesson_type'], 'quiz' ); ?>><?php esc_html_e( 'Quiz', 'zeko-learn' ); ?></option>
														<option value="assignment" <?php selected( $les['lesson_type'], 'assignment' ); ?>><?php esc_html_e( 'Assignment', 'zeko-learn' ); ?></option>
													</select>
													<input type="number" class="zeko-lesson-minutes-input" value="<?php echo esc_attr( $les['estimated_minutes'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Min', 'zeko-learn' ); ?>" min="0" title="<?php esc_attr_e( 'Estimated minutes', 'zeko-learn' ); ?>">
													<label class="zeko-lesson-preview-toggle" title="<?php esc_attr_e( 'Free preview', 'zeko-learn' ); ?>">
														<input type="checkbox" class="zeko-is-preview-check" <?php checked( $les['is_preview'] ?? 0, 1 ); ?>>
														<span class="dashicons dashicons-visibility"></span>
													</label>
													<div class="zeko-inst-lesson-actions">
														<button type="button" class="button button-small zeko-save-lesson-btn" title="<?php esc_attr_e( 'Save', 'zeko-learn' ); ?>"><span class="dashicons dashicons-saved"></span></button>
														<button type="button" class="button button-small zeko-move-up" title="<?php esc_attr_e( 'Up', 'zeko-learn' ); ?>"><span class="dashicons dashicons-arrow-up"></span></button>
														<button type="button" class="button button-small zeko-move-down" title="<?php esc_attr_e( 'Down', 'zeko-learn' ); ?>"><span class="dashicons dashicons-arrow-down"></span></button>
														<button type="button" class="button button-small button-link-delete zeko-remove-lesson" title="<?php esc_attr_e( 'Delete', 'zeko-learn' ); ?>"><span class="dashicons dashicons-trash"></span></button>
													</div>
												</div>
											</div>
										<?php endforeach; ?>
										</div>
										<div class="zeko-inst-add-lesson-row">
											<button type="button" class="button button-small zeko-add-lesson-btn">
												<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Lesson', 'zeko-learn' ); ?>
											</button>
										</div>
									</div>
								<?php endforeach; ?>
								</div>
								<button type="button" class="button zeko-add-section-btn-main" id="zeko-add-section-btn">
									<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Section', 'zeko-learn' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- ═══ Tab: Pricing ═══════════════════════════════════ -->
					<div class="zeko-cr-tab-panel" data-cr-panel="pricing">
						<div class="zeko-inst-card-body">
							<div class="zeko-inst-form-group zeko-checkbox-field">
								<label>
									<input type="checkbox" name="is_free" id="zl-course-is-free" value="1" <?php checked( ( $edit_course['is_free'] ?? 1 ), 1 ); ?>>
									<?php esc_html_e( 'Free Course', 'zeko-learn' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Toggle if this course is free to enroll.', 'zeko-learn' ); ?></p>
							</div>
							<div class="zeko-inst-form-row" id="zeko-pricing-fields" style="<?php echo ( $edit_course['is_free'] ?? 1 ) ? 'display:none;' : ''; ?>">
								<div class="zeko-inst-form-group">
									<label for="zl-course-price"><?php esc_html_e( 'Price', 'zeko-learn' ); ?></label>
									<div class="zeko-input-with-prefix">
										<span class="zeko-input-prefix"><?php echo esc_html( $currency ); ?></span>
										<input type="number" id="zl-course-price" name="price" step="0.01" min="0" value="<?php echo esc_attr( $edit_course['price'] ?? '0' ); ?>">
									</div>
								</div>
								<div class="zeko-inst-form-group">
									<label for="zl-course-sale-price"><?php esc_html_e( 'Sale Price', 'zeko-learn' ); ?></label>
									<div class="zeko-input-with-prefix">
										<span class="zeko-input-prefix"><?php echo esc_html( $currency ); ?></span>
										<input type="number" id="zl-course-sale-price" name="sale_price" step="0.01" min="0" value="<?php echo esc_attr( $edit_course['sale_price'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Optional', 'zeko-learn' ); ?>">
									</div>
									<p class="description"><?php esc_html_e( 'Leave empty for no sale.', 'zeko-learn' ); ?></p>
								</div>
							</div>
						</div>
					</div>

					<!-- ═══ Tab: Media ════════════════════════════════════ -->
					<div class="zeko-cr-tab-panel" data-cr-panel="media">
						<div class="zeko-inst-card-body">
							<div class="zeko-inst-form-group">
								<label><?php esc_html_e( 'Course Thumbnail', 'zeko-learn' ); ?></label>
								<div class="zeko-thumb-upload-area">
									<div id="zeko-thumb-preview" class="zeko-thumb-preview-box">
										<?php
										if ( ! empty( $edit_course['thumbnail_id'] ) ) :
											$thumb_url = wp_get_attachment_url( $edit_course['thumbnail_id'] );
											if ( $thumb_url ) :
												?>
												<img src="<?php echo esc_url( $thumb_url ); ?>">
												<?php
											endif;
										endif;
										?>
										<?php if ( empty( $edit_course['thumbnail_id'] ) ) : ?>
											<div class="zeko-thumb-placeholder">
												<span class="dashicons dashicons-format-image"></span>
												<p><?php esc_html_e( 'No thumbnail set', 'zeko-learn' ); ?></p>
											</div>
										<?php endif; ?>
									</div>
									<input type="hidden" id="zl-course-thumbnail-id" name="thumbnail_id" value="<?php echo esc_attr( $edit_course['thumbnail_id'] ?? 0 ); ?>">
									<div class="zeko-thumb-actions">
										<button type="button" class="button" id="zeko-upload-thumb"><span class="dashicons dashicons-format-image"></span> <?php esc_html_e( 'Choose Image', 'zeko-learn' ); ?></button>
										<button type="button" class="button" id="zeko-remove-thumb" style="display:none;"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Remove', 'zeko-learn' ); ?></button>
									</div>
									<p class="description"><?php esc_html_e( 'Recommended: 1280×720px. Max 2MB.', 'zeko-learn' ); ?></p>
								</div>
							</div>
							<div class="zeko-inst-form-group">
								<label for="zl-course-promo-video"><?php esc_html_e( 'Promo Video URL', 'zeko-learn' ); ?></label>
								<input type="url" id="zl-course-promo-video" name="promo_video_url" value="<?php echo esc_attr( $edit_course['promo_video_url'] ?? '' ); ?>" placeholder="https://youtube.com/watch?v=...">
								<p class="description"><?php esc_html_e( 'YouTube or Vimeo URL for the course promo video.', 'zeko-learn' ); ?></p>
							</div>
						</div>
					</div>

					<!-- ═══ Tab: SEO & Details ════════════════════════════ -->
					<div class="zeko-cr-tab-panel" data-cr-panel="seo">
						<div class="zeko-inst-card-body">
							<div class="zeko-inst-form-row">
								<div class="zeko-inst-form-group">
									<label for="zl-course-seo-title"><?php esc_html_e( 'SEO Title', 'zeko-learn' ); ?></label>
									<input type="text" id="zl-course-seo-title" name="seo_title" value="<?php echo esc_attr( $edit_course['seo_title'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Custom title for search engines', 'zeko-learn' ); ?>" maxlength="255">
									<p class="description"><?php esc_html_e( 'Defaults to course title if empty.', 'zeko-learn' ); ?></p>
								</div>
								<div class="zeko-inst-form-group">
									<label for="zl-course-seo-desc"><?php esc_html_e( 'SEO Description', 'zeko-learn' ); ?></label>
									<textarea id="zl-course-seo-desc" name="seo_description" rows="3" maxlength="500" placeholder="<?php esc_attr_e( 'Brief description for search engine results', 'zeko-learn' ); ?>"><?php echo esc_textarea( $edit_course['seo_description'] ?? '' ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Max 500 characters.', 'zeko-learn' ); ?></p>
								</div>
							</div>

							<div class="zeko-inst-form-group">
								<label for="zl-course-what-learn"><?php esc_html_e( 'What You\'ll Learn', 'zeko-learn' ); ?></label>
								<div class="zeko-rich-editor">
									<div class="zeko-rich-toolbar" data-target="zl-course-what-learn">
										<button type="button" data-cmd="bold" aria-label="<?php esc_attr_e( 'Bold', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-bold"></span></button>
										<button type="button" data-cmd="italic" aria-label="<?php esc_attr_e( 'Italic', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-italic"></span></button>
										<button type="button" data-cmd="insertUnorderedList" aria-label="<?php esc_attr_e( 'Unordered List', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-ul"></span></button>
										<button type="button" data-cmd="insertOrderedList" aria-label="<?php esc_attr_e( 'Ordered List', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-ol"></span></button>
									</div>
									<div class="zeko-rich-content" id="zl-course-what-learn" contenteditable="true" data-name="what_you_learn"><?php echo wp_kses_post( $edit_course['what_you_learn'] ?? '' ); ?></div>
									<textarea name="what_you_learn" class="zeko-rich-hidden" aria-hidden="true"></textarea>
									<?php if ( class_exists( 'Zeko_AI_Writer_UI' ) ) : ?>
										<?php
										echo wp_kses_post(
											(string) Zeko_AI_Writer_UI::button(
												array(
													'preset' => 'what_you_learn',
													'target' => 'textarea[name="what_you_learn"]',
												)
											)
										);
										?>
									<?php endif; ?>
								</div>
								<p class="description"><?php esc_html_e( 'List the key skills and knowledge students will gain.', 'zeko-learn' ); ?></p>
							</div>

							<div class="zeko-inst-form-row">
								<div class="zeko-inst-form-group">
									<label for="zl-course-requirements"><?php esc_html_e( 'Requirements', 'zeko-learn' ); ?></label>
									<div class="zeko-rich-editor zeko-rich-compact">
									<div class="zeko-rich-toolbar" data-target="zl-course-requirements">
										<button type="button" data-cmd="bold" aria-label="<?php esc_attr_e( 'Bold', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-bold"></span></button>
										<button type="button" data-cmd="italic" aria-label="<?php esc_attr_e( 'Italic', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-italic"></span></button>
										<button type="button" data-cmd="insertUnorderedList" aria-label="<?php esc_attr_e( 'Unordered List', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-ul"></span></button>
									</div>
										<div class="zeko-rich-content" id="zl-course-requirements" contenteditable="true" data-name="requirements"><?php echo wp_kses_post( $edit_course['requirements'] ?? '' ); ?></div>
										<textarea name="requirements" class="zeko-rich-hidden" aria-hidden="true"></textarea>
									</div>
									<p class="description"><?php esc_html_e( 'Prerequisites students need before enrolling.', 'zeko-learn' ); ?></p>
								</div>
								<div class="zeko-inst-form-group">
									<label for="zl-course-target"><?php esc_html_e( 'Target Audience', 'zeko-learn' ); ?></label>
									<div class="zeko-rich-editor zeko-rich-compact">
									<div class="zeko-rich-toolbar" data-target="zl-course-target">
										<button type="button" data-cmd="bold" aria-label="<?php esc_attr_e( 'Bold', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-bold"></span></button>
										<button type="button" data-cmd="italic" aria-label="<?php esc_attr_e( 'Italic', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-italic"></span></button>
										<button type="button" data-cmd="insertUnorderedList" aria-label="<?php esc_attr_e( 'Unordered List', 'zeko-learn' ); ?>"><span class="dashicons dashicons-editor-ul"></span></button>
									</div>
										<div class="zeko-rich-content" id="zl-course-target" contenteditable="true" data-name="target_audience"><?php echo wp_kses_post( $edit_course['target_audience'] ?? '' ); ?></div>
										<textarea name="target_audience" class="zeko-rich-hidden" aria-hidden="true"></textarea>
									</div>
									<p class="description"><?php esc_html_e( 'Who is this course for?', 'zeko-learn' ); ?></p>
								</div>
							</div>

							<div class="zeko-inst-form-group">
								<label><?php esc_html_e( 'Skills / Tags', 'zeko-learn' ); ?></label>
								<div class="zeko-skills-area">
									<div id="zeko-course-skills" class="zeko-skills-tags">
										<?php foreach ( $course_skills as $sk ) : ?>
											<span class="zeko-skill-tag" data-skill-id="<?php echo esc_attr( $sk['id'] ); ?>">
												<?php echo esc_html( $sk['name'] ); ?>
												<button type="button" class="zeko-remove-skill" aria-label="<?php esc_attr_e( 'Remove skill', 'zeko-learn' ); ?>">&times;</button>
											</span>
										<?php endforeach; ?>
									</div>
									<input type="text" id="zeko-skill-search" class="zeko-skill-search-input" placeholder="<?php esc_attr_e( 'Search skills... (min 2 chars)', 'zeko-learn' ); ?>">
									<input type="hidden" name="skill_ids" id="skill_ids" value="<?php echo esc_attr( implode( ',', $course_skill_ids ) ); ?>">
									<div id="zeko-skill-results" class="zeko-skill-results" style="display:none;"></div>
								</div>
							</div>
						</div>
					</div>

					<!-- ═══ Form Actions (always visible) ════════════════ -->
					<div class="zeko-cr-form-footer">
						<div class="zeko-inst-form-actions">
							<button type="submit" class="button button-primary" id="zeko-save-course-btn">
								<span class="dashicons dashicons-saved"></span> <?php echo $course_id_to_edit ? esc_html__( 'Update Course', 'zeko-learn' ) : esc_html__( 'Create Course', 'zeko-learn' ); ?>
							</button>
							<span id="zeko-course-save-status" class="zeko-settings-status"></span>
						</div>
					</div>
				</form>
			</div>
		</div>

	<?php elseif ( 'students' === $active_tab ) : ?>
		<!-- ═══ Students Tab ═══════════════════════════════════════ -->
		<div class="zeko-inst-panel">
			<div class="zeko-inst-card">
				<div class="zeko-inst-card-header">
					<div class="zeko-inst-card-title">
						<span class="dashicons dashicons-groups"></span>
						<h2><?php esc_html_e( 'My Students', 'zeko-learn' ); ?></h2>
					</div>
					<button type="button" class="button button-small zeko-export-grades" data-course-id="0">
						<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export CSV', 'zeko-learn' ); ?>
					</button>
				</div>
				<div class="zeko-inst-card-body">
					<?php if ( empty( $all_students ) ) : ?>
						<div class="zeko-inst-empty-state">
							<div class="zeko-inst-empty-icon">
								<span class="dashicons dashicons-groups"></span>
							</div>
							<h3><?php esc_html_e( 'No students yet', 'zeko-learn' ); ?></h3>
							<p><?php esc_html_e( 'Students will appear here once they enroll in your courses.', 'zeko-learn' ); ?></p>
						</div>
					<?php else : ?>
						<div class="zeko-inst-students-grid">
						<?php
						foreach ( $all_students as $search_term ) :
							$pct = (float) ( $search_term['completion_pct'] ?? 0 );
							?>
							<div class="zeko-inst-student-card">
								<div class="zeko-inst-student-card-avatar">
									<?php echo get_avatar( $search_term['user_id'], 48 ); ?>
								</div>
								<div class="zeko-inst-student-card-info">
									<strong><?php echo esc_html( $search_term['student_name'] ); ?></strong>
									<small><?php echo esc_html( $search_term['course_title'] ); ?></small>
								</div>
								<div class="zeko-inst-student-card-progress">
									<div class="zeko-inst-progress-ring" data-pct="<?php echo esc_attr( round( $pct ) ); ?>">
										<svg viewBox="0 0 36 36">
											<path class="ring-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e5e7eb" stroke-width="3"/>
											<path class="ring-fill" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--zl-primary)" stroke-width="3" stroke-dasharray="<?php echo esc_attr( $pct ); ?>, 100"/>
										</svg>
										<span><?php echo esc_html( round( $pct ) ); ?>%</span>
									</div>
								</div>
								<div class="zeko-inst-student-card-meta">
									<span class="zeko-status-badge zeko-status-<?php echo esc_attr( $search_term['status'] ); ?>">
										<?php echo esc_html( ucfirst( $search_term['status'] ) ); ?>
									</span>
									<small><?php echo esc_html( ( $search_term['completed_lessons'] ?? 0 ) . ' / ' . ( $search_term['total_lessons'] ?? 0 ) ); ?> <?php esc_html_e( 'lessons', 'zeko-learn' ); ?></small>
								</div>
							</div>
						<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

	<?php elseif ( 'revenue' === $active_tab ) : ?>
		<!-- ═══ Revenue Tab ═════════════════════════════════════════ -->
		<div class="zeko-inst-panel">
			<div class="zeko-inst-revenue-cards">
				<div class="zeko-inst-revenue-highlight-card">
					<div class="zeko-inst-revenue-highlight-icon">
						<span class="dashicons dashicons-money-alt"></span>
					</div>
					<div class="zeko-inst-revenue-highlight-info">
						<span class="zeko-inst-revenue-label"><?php esc_html_e( 'Total Earned', 'zeko-learn' ); ?></span>
						<strong><?php echo esc_html( $currency . number_format( (float) $stats['total_revenue'], 2 ) ); ?></strong>
					</div>
				</div>
				<div class="zeko-inst-card zeko-inst-revenue-mini">
					<div class="zeko-inst-card-body zeko-revenue-mini-content">
						<?php
						$total_enrollments = (int) array_sum( array_column( $courses, 'enrollment_count' ) );
						$avg               = $total_enrollments > 0 ? (float) $stats['total_revenue'] / $total_enrollments : 0;
						$rev_pct           = get_option( 'zeko_learn_instructor_revenue_pct', '70' );
						?>
						<div class="zeko-revenue-mini-item">
							<span class="dashicons dashicons-chart-bar"></span>
							<div>
								<strong><?php echo esc_html( $rev_pct ); ?>%</strong>
								<span><?php esc_html_e( 'Revenue Share', 'zeko-learn' ); ?></span>
							</div>
						</div>
						<div class="zeko-revenue-mini-item">
							<span class="dashicons dashicons-groups"></span>
							<div>
								<strong><?php echo esc_html( $total_enrollments ); ?></strong>
								<span><?php esc_html_e( 'Enrollments', 'zeko-learn' ); ?></span>
							</div>
						</div>
						<div class="zeko-revenue-mini-item">
							<span class="dashicons dashicons-money-alt"></span>
							<div>
								<strong><?php echo esc_html( $currency . number_format( $avg, 2 ) ); ?></strong>
								<span><?php esc_html_e( 'Avg / Student', 'zeko-learn' ); ?></span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="zeko-inst-card">
				<div class="zeko-inst-card-header">
					<div class="zeko-inst-card-title">
						<span class="dashicons dashicons-money-alt"></span>
						<h2><?php esc_html_e( 'Payout History', 'zeko-learn' ); ?></h2>
					</div>
				</div>
				<div class="zeko-inst-card-body">
					<?php if ( empty( $payouts ) ) : ?>
						<div class="zeko-inst-empty-inline">
							<span class="dashicons dashicons-money-alt"></span>
							<p><?php esc_html_e( 'No payouts recorded yet. Payouts are created when students purchase your courses.', 'zeko-learn' ); ?></p>
						</div>
					<?php else : ?>
						<div class="zeko-inst-table-wrap">
							<table class="zeko-inst-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Course', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Amount', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Period', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Status', 'zeko-learn' ); ?></th>
										<th><?php esc_html_e( 'Date', 'zeko-learn' ); ?></th>
									</tr>
								</thead>
								<tbody>
								<?php foreach ( $payouts as $p ) : ?>
									<tr>
										<td><?php echo esc_html( $p['course_title'] ?? '—' ); ?></td>
										<td><strong><?php echo esc_html( $currency . number_format( (float) $p['amount'], 2 ) ); ?></strong></td>
										<td><?php echo esc_html( mysql2date( 'M Y', $p['period_start'] ) ); ?></td>
										<td>
											<span class="zeko-status-badge zeko-status-<?php echo esc_attr( $p['status'] ); ?>">
												<?php echo esc_html( ucfirst( $p['status'] ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $p['created_at'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

	<?php elseif ( 'announcements' === $active_tab ) : ?>
		<!-- ═══ Announcements Tab ═══════════════════════════════════ -->
		<div class="zeko-inst-panel">
			<div class="zeko-inst-card">
				<div class="zeko-inst-card-header">
					<div class="zeko-inst-card-title">
						<span class="dashicons dashicons-megaphone"></span>
						<h2><?php esc_html_e( 'Course Announcements', 'zeko-learn' ); ?></h2>
					</div>
					<button type="button" class="button button-primary button-small zeko-post-announcement-btn" data-course-id="<?php echo esc_attr( $course_ids[0] ?? 0 ); ?>">
						<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'New Announcement', 'zeko-learn' ); ?>
					</button>
				</div>
				<div class="zeko-inst-card-body">
					<?php if ( empty( $announcements ) ) : ?>
						<div class="zeko-inst-empty-inline">
							<span class="dashicons dashicons-megaphone"></span>
							<p><?php esc_html_e( 'No announcements posted yet.', 'zeko-learn' ); ?></p>
						</div>
					<?php else : ?>
						<div class="zeko-inst-announcements-list">
						<?php foreach ( $announcements as $ann ) : ?>
							<div class="zeko-inst-announcement-item <?php echo $ann['is_pinned'] ? 'is-pinned' : ''; ?>" data-id="<?php echo esc_attr( $ann['id'] ); ?>">
								<div class="zeko-inst-announcement-header">
									<div class="zeko-inst-announcement-title-row">
										<?php if ( $ann['is_pinned'] ) : ?>
											<span class="dashicons dashicons-push-pin zeko-pin-icon"></span>
										<?php endif; ?>
										<h3><?php echo esc_html( $ann['title'] ); ?></h3>
									</div>
									<div class="zeko-inst-announcement-meta">
										<span class="zeko-inst-announcement-course"><?php echo esc_html( $ann['course_title'] ); ?></span>
										<span class="zeko-sep">·</span>
										<span class="zeko-inst-announcement-date"><?php echo esc_html( human_time_diff( strtotime( $ann['created_at'] ) ) . ' ago' ); ?></span>
									</div>
								</div>
								<div class="zeko-inst-announcement-body">
									<?php echo wp_kses_post( wp_trim_words( $ann['content'], 60 ) ); ?>
								</div>
								<div class="zeko-inst-announcement-actions">
									<button type="button" class="button button-small zeko-pin-announcement" data-id="<?php echo esc_attr( $ann['id'] ); ?>" data-course-id="<?php echo esc_attr( $ann['course_id'] ); ?>">
										<span class="dashicons dashicons-thumb-up"></span> <?php echo $ann['is_pinned'] ? esc_html__( 'Unpin', 'zeko-learn' ) : esc_html__( 'Pin', 'zeko-learn' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete zeko-delete-announcement" data-id="<?php echo esc_attr( $ann['id'] ); ?>">
										<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete', 'zeko-learn' ); ?>
									</button>
								</div>
							</div>
						<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

	<?php elseif ( 'settings' === $active_tab ) : ?>
		<!-- ═══ Settings Tab ═══════════════════════════════════════ -->
		<div class="zeko-inst-panel">
			<form id="zeko-instructor-settings-form">

				<!-- Profile & Identity Card -->
				<div class="zeko-inst-card">
					<div class="zeko-inst-card-header">
						<div class="zeko-inst-card-title">
							<span class="dashicons dashicons-admin-users"></span>
							<h2><?php esc_html_e( 'Profile & Identity', 'zeko-learn' ); ?></h2>
						</div>
					</div>
					<div class="zeko-inst-card-body">
						<div class="zeko-settings-profile-row">
							<div class="zeko-settings-avatar-section">
								<?php echo get_avatar( $user_id, 80, '', '', array( 'class' => 'zeko-settings-avatar-preview' ) ); ?>
								<div class="zeko-settings-avatar-info">
									<span class="zeko-settings-avatar-name"><?php echo esc_html( $user->display_name ); ?></span>
									<span class="zeko-settings-avatar-email"><?php echo esc_html( $user->user_email ); ?></span>
								</div>
							</div>
						</div>
						<div class="zeko-inst-form-row">
							<div class="zeko-inst-form-group">
								<label for="zeko-inst-display-name"><?php esc_html_e( 'Display Name', 'zeko-learn' ); ?></label>
								<input type="text" id="zeko-inst-display-name" name="display_name" value="<?php echo esc_attr( $inst['display_name'] ? $inst['display_name'] : $user->display_name ); ?>" placeholder="<?php esc_attr_e( 'Your public name', 'zeko-learn' ); ?>">
								<p class="description"><?php esc_html_e( 'This will update your WordPress display name.', 'zeko-learn' ); ?></p>
							</div>
							<div class="zeko-inst-form-group">
								<label for="zeko-inst-expertise"><?php esc_html_e( 'Expertise / Specialization', 'zeko-learn' ); ?></label>
								<input type="text" id="zeko-inst-expertise" name="expertise" value="<?php echo esc_attr( $inst['expertise'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Web Development, Data Science', 'zeko-learn' ); ?>">
							</div>
						</div>
						<div class="zeko-inst-form-group">
							<label for="zeko-inst-bio"><?php esc_html_e( 'Bio / About', 'zeko-learn' ); ?></label>
							<textarea id="zeko-inst-bio" name="bio" rows="5" placeholder="<?php esc_attr_e( 'Tell students about yourself, your experience, and what you teach...', 'zeko-learn' ); ?>"><?php echo esc_textarea( $inst['bio'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown on your public profile and course pages.', 'zeko-learn' ); ?></p>
						</div>
						<div class="zeko-inst-form-group">
							<label for="zeko-inst-hourly-rate"><?php esc_html_e( 'Hourly Rate (for 1-on-1 consulting)', 'zeko-learn' ); ?></label>
							<div class="zeko-input-with-prefix">
								<span class="zeko-input-prefix"><?php echo esc_html( $currency ); ?></span>
								<input type="number" id="zeko-inst-hourly-rate" name="hourly_rate" step="0.01" min="0" value="<?php echo esc_attr( $inst['hourly_rate'] ); ?>" placeholder="0.00">
							</div>
							<p class="description"><?php esc_html_e( 'Leave empty if you do not offer consulting.', 'zeko-learn' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Teaching Preferences Card -->
				<div class="zeko-inst-card">
					<div class="zeko-inst-card-header">
						<div class="zeko-inst-card-title">
							<span class="dashicons dashicons-welcome-learn-more"></span>
							<h2><?php esc_html_e( 'Teaching Preferences', 'zeko-learn' ); ?></h2>
						</div>
					</div>
					<div class="zeko-inst-card-body">
						<div class="zeko-inst-form-row">
							<div class="zeko-inst-form-group">
								<label for="zeko-inst-default-level"><?php esc_html_e( 'Default Course Level', 'zeko-learn' ); ?></label>
								<select id="zeko-inst-default-level" name="default_course_level">
									<option value="beginner" <?php selected( $inst['default_course_level'], 'beginner' ); ?>><?php esc_html_e( 'Beginner', 'zeko-learn' ); ?></option>
									<option value="intermediate" <?php selected( $inst['default_course_level'], 'intermediate' ); ?>><?php esc_html_e( 'Intermediate', 'zeko-learn' ); ?></option>
									<option value="advanced" <?php selected( $inst['default_course_level'], 'advanced' ); ?>><?php esc_html_e( 'Advanced', 'zeko-learn' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Pre-selected level when creating new courses.', 'zeko-learn' ); ?></p>
							</div>
							<div class="zeko-inst-form-group">
								<label for="zeko-inst-teaching-style"><?php esc_html_e( 'Teaching Style', 'zeko-learn' ); ?></label>
								<input type="text" id="zeko-inst-teaching-style" name="teaching_style" value="<?php echo esc_attr( $inst['teaching_style'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Hands-on, Project-based, Theoretical', 'zeko-learn' ); ?>">
							</div>
						</div>
					</div>
				</div>

				<!-- Notification Preferences Card -->
				<div class="zeko-inst-card">
					<div class="zeko-inst-card-header">
						<div class="zeko-inst-card-title">
							<span class="dashicons dashicons-bell"></span>
							<h2><?php esc_html_e( 'Notification Preferences', 'zeko-learn' ); ?></h2>
						</div>
					</div>
					<div class="zeko-inst-card-body">
						<div class="zeko-settings-toggle-grid">
							<label class="zeko-toggle-item">
								<input type="checkbox" name="notify_on_enrollment" value="1" <?php checked( $inst['notify_on_enrollment'] ?? '1', '1' ); ?>>
								<span class="zeko-toggle-switch"></span>
								<span class="zeko-toggle-label">
									<strong><?php esc_html_e( 'New Enrollment', 'zeko-learn' ); ?></strong>
									<small><?php esc_html_e( 'Get notified when a student enrolls in your course.', 'zeko-learn' ); ?></small>
								</span>
							</label>
							<label class="zeko-toggle-item">
								<input type="checkbox" name="notify_on_review" value="1" <?php checked( $inst['notify_on_review'] ?? '1', '1' ); ?>>
								<span class="zeko-toggle-switch"></span>
								<span class="zeko-toggle-label">
									<strong><?php esc_html_e( 'New Review', 'zeko-learn' ); ?></strong>
									<small><?php esc_html_e( 'Get notified when a student leaves a review.', 'zeko-learn' ); ?></small>
								</span>
							</label>
							<label class="zeko-toggle-item">
								<input type="checkbox" name="notify_on_question" value="1" <?php checked( $inst['notify_on_question'] ?? '1', '1' ); ?>>
								<span class="zeko-toggle-switch"></span>
								<span class="zeko-toggle-label">
									<strong><?php esc_html_e( 'Student Question', 'zeko-learn' ); ?></strong>
									<small><?php esc_html_e( 'Get notified when a student asks a question.', 'zeko-learn' ); ?></small>
								</span>
							</label>
							<label class="zeko-toggle-item">
								<input type="checkbox" name="notify_on_completion" value="1" <?php checked( $inst['notify_on_completion'] ?? '1', '1' ); ?>>
								<span class="zeko-toggle-switch"></span>
								<span class="zeko-toggle-label">
									<strong><?php esc_html_e( 'Course Completion', 'zeko-learn' ); ?></strong>
									<small><?php esc_html_e( 'Get notified when a student completes your course.', 'zeko-learn' ); ?></small>
								</span>
							</label>
							<label class="zeko-toggle-item">
								<input type="checkbox" name="weekly_digest" value="1" <?php checked( $inst['weekly_digest'] ?? '0', '1' ); ?>>
								<span class="zeko-toggle-switch"></span>
								<span class="zeko-toggle-label">
									<strong><?php esc_html_e( 'Weekly Digest', 'zeko-learn' ); ?></strong>
									<small><?php esc_html_e( 'Receive a weekly summary of your course activity.', 'zeko-learn' ); ?></small>
								</span>
							</label>
						</div>
					</div>
				</div>

				<!-- Payout Information Card -->
				<div class="zeko-inst-card">
					<div class="zeko-inst-card-header">
						<div class="zeko-inst-card-title">
							<span class="dashicons dashicons-money-alt"></span>
							<h2><?php esc_html_e( 'Payout Information', 'zeko-learn' ); ?></h2>
						</div>
					</div>
					<div class="zeko-inst-card-body">
						<div class="zeko-inst-form-row">
							<div class="zeko-inst-form-group">
								<label for="zeko-inst-payout-method"><?php esc_html_e( 'Payout Method', 'zeko-learn' ); ?></label>
								<select id="zeko-inst-payout-method" name="payout_method">
									<option value="" <?php selected( $inst['payout_method'], '' ); ?>><?php esc_html_e( '— Select —', 'zeko-learn' ); ?></option>
									<option value="paypal" <?php selected( $inst['payout_method'], 'paypal' ); ?>><?php esc_html_e( 'PayPal', 'zeko-learn' ); ?></option>
									<option value="bank_transfer" <?php selected( $inst['payout_method'], 'bank_transfer' ); ?>><?php esc_html_e( 'Bank Transfer', 'zeko-learn' ); ?></option>
									<option value="wallet" <?php selected( $inst['payout_method'], 'wallet' ); ?>><?php esc_html_e( 'Platform Wallet', 'zeko-learn' ); ?></option>
								</select>
							</div>
							<div class="zeko-inst-form-group">
								<label for="zeko-inst-payout-details"><?php esc_html_e( 'Payout Details', 'zeko-learn' ); ?></label>
								<input type="text" id="zeko-inst-payout-details" name="payout_details" value="<?php echo esc_attr( $inst['payout_details'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. PayPal email or bank account info', 'zeko-learn' ); ?>">
							</div>
						</div>
						<p class="description"><?php esc_html_e( 'Revenue share percentage is set by the platform administrator.', 'zeko-learn' ); ?></p>
					</div>
				</div>

				<!-- Social Profiles Card -->
				<div class="zeko-inst-card">
					<div class="zeko-inst-card-header">
						<div class="zeko-inst-card-title">
							<span class="dashicons dashicons-admin-links"></span>
							<h2><?php esc_html_e( 'Social Profiles', 'zeko-learn' ); ?></h2>
						</div>
					</div>
					<div class="zeko-inst-card-body">
						<div class="zeko-settings-social-grid">
							<div class="zeko-inst-form-group zeko-social-field">
								<label for="zeko-inst-website">
									<span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Website', 'zeko-learn' ); ?>
								</label>
								<input type="url" id="zeko-inst-website" name="website" value="<?php echo esc_attr( $inst['website'] ); ?>" placeholder="https://example.com">
							</div>
							<div class="zeko-inst-form-group zeko-social-field">
								<label for="zeko-inst-linkedin">
									<span class="dashicons dashicons-linkedin"></span> <?php esc_html_e( 'LinkedIn', 'zeko-learn' ); ?>
								</label>
								<input type="url" id="zeko-inst-linkedin" name="linkedin" value="<?php echo esc_attr( $inst['linkedin'] ); ?>" placeholder="https://linkedin.com/in/...">
							</div>
							<div class="zeko-inst-form-group zeko-social-field">
								<label for="zeko-inst-twitter">
									<span class="dashicons dashicons-twitter"></span> <?php esc_html_e( 'Twitter / X', 'zeko-learn' ); ?>
								</label>
								<input type="url" id="zeko-inst-twitter" name="twitter" value="<?php echo esc_attr( $inst['twitter'] ); ?>" placeholder="https://x.com/...">
							</div>
							<div class="zeko-inst-form-group zeko-social-field">
								<label for="zeko-inst-youtube">
									<span class="dashicons dashicons-video-alt3"></span> <?php esc_html_e( 'YouTube', 'zeko-learn' ); ?>
								</label>
								<input type="url" id="zeko-inst-youtube" name="youtube" value="<?php echo esc_attr( $inst['youtube'] ); ?>" placeholder="https://youtube.com/...">
							</div>
							<div class="zeko-inst-form-group zeko-social-field">
								<label for="zeko-inst-github">
									<span class="dashicons dashicons-backup"></span> <?php esc_html_e( 'GitHub', 'zeko-learn' ); ?>
								</label>
								<input type="url" id="zeko-inst-github" name="github" value="<?php echo esc_attr( $inst['github'] ); ?>" placeholder="https://github.com/...">
							</div>
						</div>
					</div>
				</div>

				<!-- Save Actions -->
				<div class="zeko-inst-form-actions">
					<button type="submit" class="button button-primary" id="zeko-save-instructor-settings">
						<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save All Settings', 'zeko-learn' ); ?>
					</button>
					<span id="zeko-inst-settings-status" class="zeko-settings-status"></span>
				</div>

			</form>
		</div>
	<?php endif; ?>

	</div><!-- .zeko-inst-tab-content -->

	<!-- ── Grade Submission Modal ──────────────────────────────── -->
	<div class="zeko-inst-modal" id="zeko-grade-modal" style="display:none;">
		<div class="zeko-inst-modal-overlay"></div>
		<div class="zeko-inst-modal-content">
			<div class="zeko-inst-modal-header">
				<h3><?php esc_html_e( 'Grade Submission', 'zeko-learn' ); ?></h3>
				<button type="button" class="zeko-inst-modal-close" aria-label="<?php esc_attr_e( 'Close', 'zeko-learn' ); ?>">&times;</button>
			</div>
			<div class="zeko-inst-modal-body">
				<div class="zeko-inst-form-group">
					<label><?php esc_html_e( 'Student', 'zeko-learn' ); ?></label>
					<div class="zeko-modal-info" id="zeko-grade-student"></div>
				</div>
				<div class="zeko-inst-form-group">
					<label><?php esc_html_e( 'Assignment', 'zeko-learn' ); ?></label>
					<div class="zeko-modal-info" id="zeko-grade-assignment"></div>
				</div>
				<div class="zeko-inst-form-group">
					<label for="zeko-grade-input"><?php esc_html_e( 'Grade', 'zeko-learn' ); ?></label>
					<input type="text" id="zeko-grade-input" placeholder="<?php esc_attr_e( 'e.g. A, B+, 85/100', 'zeko-learn' ); ?>">
				</div>
				<div class="zeko-inst-form-group">
					<label for="zeko-grade-feedback"><?php esc_html_e( 'Feedback', 'zeko-learn' ); ?></label>
					<textarea id="zeko-grade-feedback" rows="4" placeholder="<?php esc_attr_e( 'Provide constructive feedback...', 'zeko-learn' ); ?>"></textarea>
				</div>
			</div>
			<div class="zeko-inst-modal-footer">
				<button type="button" class="button zeko-inst-modal-close"><?php esc_html_e( 'Cancel', 'zeko-learn' ); ?></button>
				<button type="button" class="button button-primary" id="zeko-submit-grade">
					<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Submit Grade', 'zeko-learn' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- ── Post Announcement Modal ─────────────────────────────── -->
	<div class="zeko-inst-modal" id="zeko-announcement-modal" style="display:none;">
		<div class="zeko-inst-modal-overlay"></div>
		<div class="zeko-inst-modal-content">
			<div class="zeko-inst-modal-header">
				<h3><?php esc_html_e( 'Post Announcement', 'zeko-learn' ); ?></h3>
				<button type="button" class="zeko-inst-modal-close" aria-label="<?php esc_attr_e( 'Close', 'zeko-learn' ); ?>">&times;</button>
			</div>
			<div class="zeko-inst-modal-body">
				<div class="zeko-inst-form-group">
					<label for="zeko-ann-course"><?php esc_html_e( 'Course', 'zeko-learn' ); ?></label>
					<select id="zeko-ann-course">
						<?php foreach ( $courses as $c ) : ?>
							<option value="<?php echo esc_attr( $c['id'] ); ?>"><?php echo esc_html( $c['title'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="zeko-inst-form-group">
					<label for="zeko-ann-title"><?php esc_html_e( 'Title', 'zeko-learn' ); ?></label>
					<input type="text" id="zeko-ann-title" required placeholder="<?php esc_attr_e( 'Announcement title', 'zeko-learn' ); ?>">
				</div>
				<div class="zeko-inst-form-group">
					<label for="zeko-ann-content"><?php esc_html_e( 'Content', 'zeko-learn' ); ?></label>
					<textarea id="zeko-ann-content" rows="6" required placeholder="<?php esc_attr_e( 'Write your announcement...', 'zeko-learn' ); ?>"></textarea>
				</div>
			</div>
			<div class="zeko-inst-modal-footer">
				<button type="button" class="button zeko-inst-modal-close"><?php esc_html_e( 'Cancel', 'zeko-learn' ); ?></button>
				<button type="button" class="button button-primary" id="zeko-submit-announcement">
					<span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Publish', 'zeko-learn' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>
