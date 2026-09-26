<?php
/**
 * Student Dashboard template.
 *
 * Shows enrolled courses, learning stats, certificates, bookmarks,
 * and advanced student settings.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = $this->db;

$user_id = get_current_user_id();
if ( ! $user_id ) {
	return;
}

$user = get_userdata( $user_id );

$enrolled_courses = $db->get_user_enrolled_courses( $user_id );
$certificates     = $db->get_user_certificates( $user_id );
$streak           = $db->get_learning_streak( $user_id );
$wishlist         = $db->get_user_wishlist( $user_id );

$filter        = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only dashboard tab flag; sanitized and allow-listed against $valid_filters below.
$valid_filters = array( 'all', 'in_progress', 'completed', 'certificates', 'wishlist', 'settings' );
if ( ! in_array( $filter, $valid_filters, true ) ) {
	$filter = 'all';
}

// Load student settings.
$stu       = array();
$meta_keys = array(
	'display_name',
	'bio',
	'learning_goal',
	'preferred_level',
	'daily_goal_minutes',
	'email_notifications',
	'notify_on_completion',
	'notify_on_announcement',
	'notify_on_reply',
	'notify_weekly_summary',
	'theme_preference',
);
foreach ( $meta_keys as $mk ) {
	$stu[ $mk ] = get_user_meta( $user_id, 'zeko_learn_student_' . $mk, true );
}

// Calculate stats.
$total_completed = 0;
$total_hours     = 0.0;
foreach ( $enrolled_courses as $ec ) {
	if ( 'completed' === ( $ec['enrollment_status'] ?? '' ) ) {
		++$total_completed;
	}
	$total_hours += (float) ( $ec['estimated_hours'] ?? 0 );
}

$currency = get_option( 'zeko_learn_currency_symbol', '$' );
?>
<div class="zeko-student-dashboard">

	<!-- ── Welcome Banner ─────────────────────────────────────── -->
	<div class="zeko-stu-hero-card">
		<div class="zeko-stu-hero-bg"></div>
		<div class="zeko-stu-hero-content">
			<div class="zeko-stu-hero-left">
				<div class="zeko-stu-hero-avatar">
					<?php echo get_avatar( $user_id, 72, '', '', array( 'class' => 'zeko-stu-avatar-lg' ) ); ?>
				</div>
				<div class="zeko-stu-hero-info">
					<h1>
					<?php
						$name = $stu['display_name'] ? $stu['display_name'] : ( $user ? $user->display_name : '' );
						/* translators: %s: user display name */
						echo esc_html( sprintf( __( 'Welcome back, %s!', 'zeko-learn' ), $name ) );
					?>
					</h1>
					<?php if ( $stu['bio'] ) : ?>
						<p class="zeko-stu-hero-bio"><?php echo esc_html( wp_trim_words( $stu['bio'], 20 ) ); ?></p>
					<?php endif; ?>
					<div class="zeko-stu-hero-chips">
						<?php if ( $stu['learning_goal'] ) : ?>
							<span class="zeko-stu-chip">
								<span class="dashicons dashicons-aim"></span>
								<?php
								$goals = array(
									'casual'   => __( 'Casual Learning', 'zeko-learn' ),
									'career'   => __( 'Career Development', 'zeko-learn' ),
									'academic' => __( 'Academic Studies', 'zeko-learn' ),
								);
								echo esc_html( $goals[ $stu['learning_goal'] ] ?? $stu['learning_goal'] );
								?>
							</span>
						<?php endif; ?>
						<?php if ( $stu['daily_goal_minutes'] ) : ?>
							<span class="zeko-stu-chip">
								<span class="dashicons dashicons-clock"></span>
								<?php echo esc_html( $stu['daily_goal_minutes'] ); ?> <?php esc_html_e( 'min/day goal', 'zeko-learn' ); ?>
							</span>
						<?php endif; ?>
						<?php if ( $streak && $streak['current_streak'] > 0 ) : ?>
							<span class="zeko-stu-chip zeko-stu-chip-fire">
								<span class="dashicons dashicons-fire"></span>
								<?php echo esc_html( $streak['current_streak'] ); ?> <?php esc_html_e( 'day streak', 'zeko-learn' ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="zeko-stu-hero-actions">
				<a href="?filter=settings" class="button zeko-btn-outline-white">
					<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Settings', 'zeko-learn' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/courses/' ) ); ?>" class="button button-primary">
					<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Browse Courses', 'zeko-learn' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- ── Stats Cards ────────────────────────────────────────── -->
	<div class="zeko-stu-stats-grid">
		<div class="zeko-stu-stat-card">
			<div class="zeko-stu-stat-icon" style="background:rgba(26,35,126,0.08);color:var(--zl-primary);">
				<span class="dashicons dashicons-welcome-learn-more"></span>
			</div>
			<div class="zeko-stu-stat-content">
				<strong><?php echo esc_html( count( $enrolled_courses ) ); ?></strong>
				<span><?php esc_html_e( 'Enrolled', 'zeko-learn' ); ?></span>
			</div>
		</div>
		<div class="zeko-stu-stat-card">
			<div class="zeko-stu-stat-icon" style="background:rgba(22,163,74,0.08);color:#16a34a;">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="zeko-stu-stat-content">
				<strong><?php echo esc_html( $total_completed ); ?></strong>
				<span><?php esc_html_e( 'Completed', 'zeko-learn' ); ?></span>
			</div>
		</div>
		<div class="zeko-stu-stat-card">
			<div class="zeko-stu-stat-icon" style="background:rgba(234,179,8,0.08);color:#ca8a04;">
				<span class="dashicons dashicons-clock"></span>
			</div>
			<div class="zeko-stu-stat-content">
				<strong><?php echo esc_html( round( $total_hours, 1 ) ); ?></strong>
				<span><?php esc_html_e( 'Hours Learned', 'zeko-learn' ); ?></span>
			</div>
		</div>
		<div class="zeko-stu-stat-card">
			<div class="zeko-stu-stat-icon" style="background:rgba(124,58,237,0.08);color:#7c3aed;">
				<span class="dashicons dashicons-awards"></span>
			</div>
			<div class="zeko-stu-stat-content">
				<strong><?php echo esc_html( count( $certificates ) ); ?></strong>
				<span><?php esc_html_e( 'Certificates', 'zeko-learn' ); ?></span>
			</div>
		</div>
		<?php if ( $streak && $streak['current_streak'] > 0 ) : ?>
		<div class="zeko-stu-stat-card">
			<div class="zeko-stu-stat-icon" style="background:rgba(249,115,22,0.08);color:#f97316;">
				<span class="dashicons dashicons-fire"></span>
			</div>
			<div class="zeko-stu-stat-content">
				<strong><?php echo esc_html( $streak['current_streak'] ); ?></strong>
				<span><?php esc_html_e( 'Day Streak', 'zeko-learn' ); ?></span>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<!-- ── Tab Navigation ─────────────────────────────────────── -->
	<div class="zeko-stu-tabs">
		<nav class="zeko-stu-tab-nav">
			<a href="?filter=all" class="zeko-stu-tab-btn <?php echo 'all' === $filter ? 'active' : ''; ?>">
				<span class="dashicons dashicons-welcome-learn-more"></span> <?php esc_html_e( 'All Courses', 'zeko-learn' ); ?>
			</a>
			<a href="?filter=in_progress" class="zeko-stu-tab-btn <?php echo 'in_progress' === $filter ? 'active' : ''; ?>">
				<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'In Progress', 'zeko-learn' ); ?>
			</a>
			<a href="?filter=completed" class="zeko-stu-tab-btn <?php echo 'completed' === $filter ? 'active' : ''; ?>">
				<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Completed', 'zeko-learn' ); ?>
			</a>
			<a href="?filter=certificates" class="zeko-stu-tab-btn <?php echo 'certificates' === $filter ? 'active' : ''; ?>">
				<span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Certificates', 'zeko-learn' ); ?>
			</a>
			<a href="?filter=wishlist" class="zeko-stu-tab-btn <?php echo 'wishlist' === $filter ? 'active' : ''; ?>">
				<span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Wishlist', 'zeko-learn' ); ?>
			</a>
			<a href="?filter=settings" class="zeko-stu-tab-btn <?php echo 'settings' === $filter ? 'active' : ''; ?>">
				<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Settings', 'zeko-learn' ); ?>
			</a>
		</nav>
	</div>

	<!-- ── Tab Content ────────────────────────────────────────── -->
	<div class="zeko-stu-tab-content">

	<?php if ( 'certificates' === $filter ) : ?>
		<!-- ═══ Certificates Tab ═══════════════════════════════════ -->
		<div class="zeko-stu-panel">
			<div class="zeko-inst-card">
				<div class="zeko-inst-card-header">
					<div class="zeko-inst-card-title">
						<span class="dashicons dashicons-awards"></span>
						<h2><?php esc_html_e( 'My Certificates', 'zeko-learn' ); ?></h2>
					</div>
				</div>
				<div class="zeko-inst-card-body">
					<?php if ( empty( $certificates ) ) : ?>
						<div class="zeko-inst-empty-state">
							<div class="zeko-inst-empty-icon"><span class="dashicons dashicons-awards"></span></div>
							<h3><?php esc_html_e( 'No certificates yet', 'zeko-learn' ); ?></h3>
							<p><?php esc_html_e( 'Complete courses to earn certificates!', 'zeko-learn' ); ?></p>
						</div>
					<?php else : ?>
						<div class="zeko-stu-certificates-grid">
							<?php foreach ( $certificates as $cert ) : ?>
								<div class="zeko-stu-cert-card">
									<div class="zeko-stu-cert-icon">
										<span class="dashicons dashicons-awards"></span>
									</div>
									<div class="zeko-stu-cert-body">
										<h3><?php echo esc_html( $cert['course_title'] ); ?></h3>
										<div class="zeko-stu-cert-meta">
											<span><?php esc_html_e( 'Issued:', 'zeko-learn' ); ?> <?php echo esc_html( mysql2date( get_option( 'date_format' ), $cert['issued_at'] ) ); ?></span>
											<code><?php echo esc_html( $cert['certificate_number'] ); ?></code>
										</div>
									</div>
									<div class="zeko-stu-cert-actions">
										<a href="<?php echo esc_url( home_url( '/certificates/' . $cert['certificate_number'] ) ); ?>" class="button button-small" target="_blank">
											<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'View', 'zeko-learn' ); ?>
										</a>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

	<?php elseif ( 'wishlist' === $filter ) : ?>
		<!-- ═══ Wishlist Tab ═══════════════════════════════════════ -->
		<div class="zeko-stu-panel">
			<div class="zeko-inst-card">
				<div class="zeko-inst-card-header">
					<div class="zeko-inst-card-title">
						<span class="dashicons dashicons-heart"></span>
						<h2><?php esc_html_e( 'My Wishlist', 'zeko-learn' ); ?></h2>
					</div>
				</div>
				<div class="zeko-inst-card-body">
					<?php if ( empty( $wishlist ) ) : ?>
						<div class="zeko-inst-empty-state">
							<div class="zeko-inst-empty-icon"><span class="dashicons dashicons-heart"></span></div>
							<h3><?php esc_html_e( 'Wishlist is empty', 'zeko-learn' ); ?></h3>
							<p><?php esc_html_e( 'Browse courses and save them for later!', 'zeko-learn' ); ?></p>
							<a href="<?php echo esc_url( home_url( '/courses/' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Browse Courses', 'zeko-learn' ); ?></a>
						</div>
					<?php else : ?>
						<div class="zeko-stu-courses-grid">
							<?php
							foreach ( $wishlist as $wish ) :
								$wish_course = $db->get_course( (int) $wish['course_id'] );
								if ( ! $wish_course ) {
									continue;
								}
								$thumb = $wish_course['thumbnail_id'] ? wp_get_attachment_url( $wish_course['thumbnail_id'] ) : '';
								?>
								<div class="zeko-stu-course-card">
									<a href="<?php echo esc_url( home_url( '/courses/' . $wish_course['slug'] . '/' ) ); ?>" class="zeko-stu-course-thumb">
										<?php if ( $thumb ) : ?>
											<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $wish_course['title'] ); ?>">
										<?php else : ?>
											<div class="zeko-stu-thumb-placeholder"><span class="dashicons dashicons-welcome-learn-more"></span></div>
										<?php endif; ?>
									</a>
									<div class="zeko-stu-course-info">
										<h4><a href="<?php echo esc_url( home_url( '/courses/' . $wish_course['slug'] . '/' ) ); ?>"><?php echo esc_html( $wish_course['title'] ); ?></a></h4>
										<div class="zeko-stu-course-meta-row">
											<?php if ( $wish_course['is_free'] ) : ?>
												<span class="zeko-free-badge"><?php esc_html_e( 'Free', 'zeko-learn' ); ?></span>
											<?php else : ?>
												<span class="zeko-stu-course-price"><?php echo esc_html( $currency . number_format( (float) $wish_course['price'], 2 ) ); ?></span>
											<?php endif; ?>
											<button class="button button-small zeko-remove-wishlist" data-course-id="<?php echo esc_attr( $wish_course['id'] ); ?>">
												<span class="dashicons dashicons-no-alt"></span>
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

	<?php elseif ( 'settings' === $filter ) : ?>
		<!-- ═══ Settings Tab ═══════════════════════════════════════ -->
		<div class="zeko-stu-panel">
			<form id="zeko-student-settings-form">

				<!-- Profile Card -->
				<div class="zeko-inst-card">
					<div class="zeko-inst-card-header">
						<div class="zeko-inst-card-title">
							<span class="dashicons dashicons-admin-users"></span>
							<h2><?php esc_html_e( 'Profile', 'zeko-learn' ); ?></h2>
						</div>
					</div>
					<div class="zeko-inst-card-body">
						<div class="zeko-settings-profile-row">
							<div class="zeko-settings-avatar-section">
								<?php echo get_avatar( $user_id, 72, '', '', array( 'class' => 'zeko-settings-avatar-preview' ) ); ?>
								<div class="zeko-settings-avatar-info">
									<span class="zeko-settings-avatar-name"><?php echo esc_html( $user->display_name ); ?></span>
									<span class="zeko-settings-avatar-email"><?php echo esc_html( $user->user_email ); ?></span>
								</div>
							</div>
						</div>
						<div class="zeko-inst-form-row">
							<div class="zeko-inst-form-group">
								<label for="zeko-stu-display-name"><?php esc_html_e( 'Display Name', 'zeko-learn' ); ?></label>
								<input type="text" id="zeko-stu-display-name" name="display_name" value="<?php echo esc_attr( $stu['display_name'] ? $stu['display_name'] : ( $user ? $user->display_name : '' ) ); ?>" placeholder="<?php esc_attr_e( 'Your public name', 'zeko-learn' ); ?>">
								<p class="description"><?php esc_html_e( 'This will update your WordPress display name.', 'zeko-learn' ); ?></p>
							</div>
							<div class="zeko-inst-form-group">
								<label for="zeko-stu-bio"><?php esc_html_e( 'Bio', 'zeko-learn' ); ?></label>
								<textarea id="zeko-stu-bio" name="bio" rows="3" placeholder="<?php esc_attr_e( 'Tell us about yourself...', 'zeko-learn' ); ?>"><?php echo esc_textarea( $stu['bio'] ); ?></textarea>
							</div>
						</div>
					</div>
				</div>

				<!-- Learning Preferences Card -->
				<div class="zeko-inst-card">
					<div class="zeko-inst-card-header">
						<div class="zeko-inst-card-title">
							<span class="dashicons dashicons-learn"></span>
							<h2><?php esc_html_e( 'Learning Preferences', 'zeko-learn' ); ?></h2>
						</div>
					</div>
					<div class="zeko-inst-card-body">
						<div class="zeko-inst-form-row">
							<div class="zeko-inst-form-group">
								<label for="zeko-stu-learning-goal"><?php esc_html_e( 'Learning Goal', 'zeko-learn' ); ?></label>
								<select id="zeko-stu-learning-goal" name="learning_goal">
									<option value="" <?php selected( $stu['learning_goal'], '' ); ?>><?php esc_html_e( '— Select —', 'zeko-learn' ); ?></option>
									<option value="casual" <?php selected( $stu['learning_goal'], 'casual' ); ?>><?php esc_html_e( 'Casual Learning', 'zeko-learn' ); ?></option>
									<option value="career" <?php selected( $stu['learning_goal'], 'career' ); ?>><?php esc_html_e( 'Career Development', 'zeko-learn' ); ?></option>
									<option value="academic" <?php selected( $stu['learning_goal'], 'academic' ); ?>><?php esc_html_e( 'Academic Studies', 'zeko-learn' ); ?></option>
								</select>
							</div>
							<div class="zeko-inst-form-group">
								<label for="zeko-stu-preferred-level"><?php esc_html_e( 'Preferred Difficulty', 'zeko-learn' ); ?></label>
								<select id="zeko-stu-preferred-level" name="preferred_level">
									<option value="" <?php selected( $stu['preferred_level'], '' ); ?>><?php esc_html_e( '— Select —', 'zeko-learn' ); ?></option>
									<option value="beginner" <?php selected( $stu['preferred_level'], 'beginner' ); ?>><?php esc_html_e( 'Beginner', 'zeko-learn' ); ?></option>
									<option value="intermediate" <?php selected( $stu['preferred_level'], 'intermediate' ); ?>><?php esc_html_e( 'Intermediate', 'zeko-learn' ); ?></option>
									<option value="advanced" <?php selected( $stu['preferred_level'], 'advanced' ); ?>><?php esc_html_e( 'Advanced', 'zeko-learn' ); ?></option>
								</select>
							</div>
						</div>
						<div class="zeko-inst-form-group">
							<label for="zeko-stu-daily-goal"><?php esc_html_e( 'Daily Learning Goal (minutes)', 'zeko-learn' ); ?></label>
							<div class="zeko-input-with-prefix">
								<input type="number" id="zeko-stu-daily-goal" name="daily_goal_minutes" min="0" max="480" step="5" value="<?php echo esc_attr( $stu['daily_goal_minutes'] ?: '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. 30', 'zeko-learn' ); ?>">
								<span class="zeko-input-suffix"><?php esc_html_e( 'minutes', 'zeko-learn' ); ?></span>
							</div>
							<p class="description"><?php esc_html_e( 'Set a daily target to stay on track. Leave empty for no reminder.', 'zeko-learn' ); ?></p>
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
								<input type="checkbox" name="email_notifications" value="1" <?php checked( $stu['email_notifications'] ?? '1', '1' ); ?>>
								<span class="zeko-toggle-switch"></span>
								<span class="zeko-toggle-label">
									<strong><?php esc_html_e( 'Email Notifications', 'zeko-learn' ); ?></strong>
									<small><?php esc_html_e( 'Master switch for all email notifications.', 'zeko-learn' ); ?></small>
								</span>
							</label>
							<label class="zeko-toggle-item">
								<input type="checkbox" name="notify_on_completion" value="1" <?php checked( $stu['notify_on_completion'] ?? '1', '1' ); ?>>
								<span class="zeko-toggle-switch"></span>
								<span class="zeko-toggle-label">
									<strong><?php esc_html_e( 'Course Completion', 'zeko-learn' ); ?></strong>
									<small><?php esc_html_e( 'Get notified when you complete a course.', 'zeko-learn' ); ?></small>
								</span>
							</label>
							<label class="zeko-toggle-item">
								<input type="checkbox" name="notify_on_announcement" value="1" <?php checked( $stu['notify_on_announcement'] ?? '1', '1' ); ?>>
								<span class="zeko-toggle-switch"></span>
								<span class="zeko-toggle-label">
									<strong><?php esc_html_e( 'Course Announcements', 'zeko-learn' ); ?></strong>
									<small><?php esc_html_e( 'Get notified about new announcements from instructors.', 'zeko-learn' ); ?></small>
								</span>
							</label>
							<label class="zeko-toggle-item">
								<input type="checkbox" name="notify_on_reply" value="1" <?php checked( $stu['notify_on_reply'] ?? '1', '1' ); ?>>
								<span class="zeko-toggle-switch"></span>
								<span class="zeko-toggle-label">
									<strong><?php esc_html_e( 'Discussion Replies', 'zeko-learn' ); ?></strong>
									<small><?php esc_html_e( 'Get notified when someone replies to your discussion post.', 'zeko-learn' ); ?></small>
								</span>
							</label>
							<label class="zeko-toggle-item">
								<input type="checkbox" name="notify_weekly_summary" value="1" <?php checked( $stu['notify_weekly_summary'] ?? '0', '1' ); ?>>
								<span class="zeko-toggle-switch"></span>
								<span class="zeko-toggle-label">
									<strong><?php esc_html_e( 'Weekly Summary', 'zeko-learn' ); ?></strong>
									<small><?php esc_html_e( 'Receive a weekly learning progress summary.', 'zeko-learn' ); ?></small>
								</span>
							</label>
						</div>
					</div>
				</div>

				<!-- Display Preferences Card -->
				<div class="zeko-inst-card">
					<div class="zeko-inst-card-header">
						<div class="zeko-inst-card-title">
							<span class="dashicons dashicons-admin-appearance"></span>
							<h2><?php esc_html_e( 'Display Preferences', 'zeko-learn' ); ?></h2>
						</div>
					</div>
					<div class="zeko-inst-card-body">
						<div class="zeko-inst-form-group">
							<label><?php esc_html_e( 'Theme', 'zeko-learn' ); ?></label>
							<div class="zeko-settings-radio-cards">
								<label class="zeko-radio-card">
									<input type="radio" name="theme_preference" value="system" <?php checked( $stu['theme_preference'] ?: 'system', 'system' ); ?>>
									<div class="zeko-radio-card-inner">
										<span class="dashicons dashicons-desktop"></span>
										<strong><?php esc_html_e( 'System', 'zeko-learn' ); ?></strong>
										<small><?php esc_html_e( 'Match OS', 'zeko-learn' ); ?></small>
									</div>
								</label>
								<label class="zeko-radio-card">
									<input type="radio" name="theme_preference" value="light" <?php checked( $stu['theme_preference'], 'light' ); ?>>
									<div class="zeko-radio-card-inner">
										<span class="dashicons dashicons-sunny"></span>
										<strong><?php esc_html_e( 'Light', 'zeko-learn' ); ?></strong>
										<small><?php esc_html_e( 'Bright mode', 'zeko-learn' ); ?></small>
									</div>
								</label>
								<label class="zeko-radio-card">
									<input type="radio" name="theme_preference" value="dark" <?php checked( $stu['theme_preference'], 'dark' ); ?>>
									<div class="zeko-radio-card-inner">
										<span class="dashicons dashicons-controls-pause"></span>
										<strong><?php esc_html_e( 'Dark', 'zeko-learn' ); ?></strong>
										<small><?php esc_html_e( 'Easy on eyes', 'zeko-learn' ); ?></small>
									</div>
								</label>
							</div>
						</div>
					</div>
				</div>

				<!-- Save Actions -->
				<div class="zeko-inst-form-actions">
					<button type="submit" class="button button-primary" id="zeko-save-student-settings">
						<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save All Settings', 'zeko-learn' ); ?>
					</button>
					<span id="zeko-stu-settings-status" class="zeko-settings-status"></span>
				</div>

			</form>
		</div>

	<?php else : ?>
		<!-- ═══ Enrolled Courses Tab ═══════════════════════════════ -->
		<div class="zeko-stu-panel">
			<?php
			$filtered = $enrolled_courses;
			if ( 'in_progress' === $filter ) {
				$filtered = array_filter(
					$enrolled_courses,
					function ( $ec ) {
						return 'completed' !== ( $ec['enrollment_status'] ?? '' );
					}
				);
			} elseif ( 'completed' === $filter ) {
				$filtered = array_filter(
					$enrolled_courses,
					function ( $ec ) {
						return 'completed' === ( $ec['enrollment_status'] ?? '' );
					}
				);
			}

			if ( empty( $filtered ) ) :
				?>
				<div class="zeko-inst-card">
					<div class="zeko-inst-card-body">
						<div class="zeko-inst-empty-state">
							<div class="zeko-inst-empty-icon">
								<span class="dashicons dashicons-welcome-learn-more"></span>
							</div>
							<?php if ( 'completed' === $filter ) : ?>
								<h3><?php esc_html_e( 'No completed courses yet', 'zeko-learn' ); ?></h3>
								<p><?php esc_html_e( 'Keep learning — you\'re doing great!', 'zeko-learn' ); ?></p>
							<?php else : ?>
								<h3><?php esc_html_e( 'No courses here', 'zeko-learn' ); ?></h3>
								<p><?php esc_html_e( 'Browse our catalog to get started!', 'zeko-learn' ); ?></p>
								<a href="<?php echo esc_url( home_url( '/courses/' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Browse Courses', 'zeko-learn' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php else : ?>
				<div class="zeko-stu-courses-grid">
					<?php
					foreach ( $filtered as $ec ) :
						$ec_course = $db->get_course( (int) $ec['id'] );
						if ( ! $ec_course ) {
							continue;
						}
						$thumb     = $ec_course['thumbnail_id'] ? wp_get_attachment_url( $ec_course['thumbnail_id'] ) : '';
						$pct       = (float) ( $ec['completion_pct'] ?? 0 );
						$ec_status = $ec['enrollment_status'] ?? 'active';
						$last      = ! empty( $ec['last_accessed_at'] ) ? mysql2date( get_option( 'date_format' ), $ec['last_accessed_at'] ) : '';
						?>
						<div class="zeko-stu-course-card">
							<a href="<?php echo esc_url( home_url( '/courses/' . $ec_course['slug'] . '/learn/' ) ); ?>" class="zeko-stu-course-thumb">
								<?php if ( $thumb ) : ?>
									<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $ec_course['title'] ); ?>">
								<?php else : ?>
									<div class="zeko-stu-thumb-placeholder"><span class="dashicons dashicons-welcome-learn-more"></span></div>
								<?php endif; ?>
								<?php if ( 'completed' === $ec_status ) : ?>
									<span class="zeko-stu-completed-badge"><span class="dashicons dashicons-yes-alt"></span></span>
								<?php endif; ?>
								<div class="zeko-stu-progress-overlay">
									<div class="zeko-stu-progress-ring" data-pct="<?php echo esc_attr( round( $pct ) ); ?>">
										<svg viewBox="0 0 36 36">
											<path class="ring-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2.5"/>
											<path class="ring-fill" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#fff" stroke-width="2.5" stroke-dasharray="<?php echo esc_attr( $pct ); ?>, 100"/>
										</svg>
										<span><?php echo esc_html( round( $pct ) ); ?>%</span>
									</div>
								</div>
							</a>
							<div class="zeko-stu-course-info">
								<h4><a href="<?php echo esc_url( home_url( '/courses/' . $ec_course['slug'] . '/learn/' ) ); ?>"><?php echo esc_html( $ec_course['title'] ); ?></a></h4>
								<div class="zeko-progress-wrap">
									<div class="zeko-progress-bar"><div class="zeko-progress-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
									<span class="zeko-progress-text"><?php echo esc_html( round( $pct ) ); ?>% <?php esc_html_e( 'complete', 'zeko-learn' ); ?></span>
								</div>
								<?php if ( $last ) : ?>
									<span class="zeko-stu-last-accessed"><?php esc_html_e( 'Last accessed:', 'zeko-learn' ); ?> <?php echo esc_html( $last ); ?></span>
								<?php endif; ?>
								<a href="<?php echo esc_url( home_url( '/courses/' . $ec_course['slug'] . '/learn/' ) ); ?>" class="button button-primary button-small">
									<?php echo esc_html( $pct > 0 ? __( 'Continue', 'zeko-learn' ) : __( 'Start', 'zeko-learn' ) ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span>
								</a>
								<?php
								if ( 'active' === $ec_status ) :
									$is_paid = empty( $ec_course['is_free'] ) && (float) $ec_course['price'] > 0;
									?>
									<button type="button" class="button button-small zeko-refund-course-btn" data-course-id="<?php echo esc_attr( (int) $ec_course['id'] ); ?>">
										<?php echo esc_html( $is_paid ? __( 'Refund & Unenroll', 'zeko-learn' ) : __( 'Unenroll', 'zeko-learn' ) ); ?>
									</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	</div><!-- .zeko-stu-tab-content -->
</div>
