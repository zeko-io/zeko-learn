<?php
/**
 * Single course landing page template.
 *
 * @package Zeko_Learn
 * @var array $course Course row from DB.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$db = $this->db;

$course_id   = (int) $course['id'];
$sections    = $db->get_course_sections( $course_id );
$reviews     = $db->get_course_reviews( $course_id );
$skills      = $db->get_course_skills( $course_id );
$enrollment  = is_user_logged_in() ? $db->get_enrollment( get_current_user_id(), $course_id ) : null;
$is_enrolled = null !== $enrollment;
$instructor  = get_userdata( $course['instructor_id'] );
$thumb_url   = $course['thumbnail_id'] ? wp_get_attachment_url( $course['thumbnail_id'] ) : '';
$course_url  = home_url( '/courses/' . $course['slug'] . '/' );
$learn_url   = home_url( '/courses/' . $course['slug'] . '/learn/' );
$currency    = get_option( 'zeko_learn_currency_symbol', '$' );

$total_lessons = 0;
foreach ( $sections as $search_term ) {
	$total_lessons += count( $db->get_section_lessons( (int) $search_term['id'] ) );
}

$cat_obj = $course['category_id'] ? $wpdb->get_row( $wpdb->prepare( "SELECT name, slug FROM {$wpdb->prefix}zeko_categories WHERE id = %d", $course['category_id'] ) ) : null;

// Schema.org Course JSON-LD.
$schema = array(
	'@context'            => 'https://schema.org',
	'@type'               => 'Course',
	'name'                => $course['title'],
	'description'         => wp_strip_all_tags( $course['description'] ?? $course['subtitle'] ?? '' ),
	'provider'            => array(
		'@type' => 'Organization',
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url(),
	),
	'educationalLevel'    => ucfirst( str_replace( '_', ' ', $course['level'] ) ),
	'inLanguage'          => $course['language'] ?: 'English',
	'isAccessibleForFree' => (bool) $course['is_free'],
);
if ( $thumb_url ) {
	$schema['image'] = $thumb_url;
}
if ( ! $course['is_free'] ) {
	$schema['offers'] = array(
		'@type'         => 'Offer',
		'price'         => (float) ( $course['sale_price'] ?: $course['price'] ),
		'priceCurrency' => get_option( 'zeko_learn_currency', 'USD' ),
		'availability'  => 'https://schema.org/InStock',
	);
}
if ( $instructor ) {
	$schema['instructor'] = array(
		'@type' => 'Person',
		'name'  => $instructor->display_name,
	);
}
if ( $course['avg_rating'] > 0 ) {
	$schema['aggregateRating'] = array(
		'@type'       => 'AggregateRating',
		'ratingValue' => (float) $course['avg_rating'],
		'reviewCount' => (int) $course['review_count'],
		'bestRating'  => 5,
	);
}
if ( ! empty( $skills ) ) {
	$schema['occupationalCredentialCategory'] = wp_list_pluck( $skills, 'name' );
}
?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $course['title'] ); ?> — <?php esc_html_e( 'Course', 'zeko-learn' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'zeko-learn-body' ); ?>>
<?php wp_body_open(); ?>

<div class="zeko-single-course" style="max-width:1200px;margin:0 auto;padding:0 16px;">

	<!-- Hero -->
	<div class="zeko-course-hero">
		<div class="zeko-course-hero-inner">
			<?php if ( $cat_obj ) : ?>
				<span class="zeko-breadcrumb"><?php echo esc_html( $cat_obj->name ); ?></span>
			<?php endif; ?>
			<h1><?php echo esc_html( $course['title'] ); ?></h1>
			<?php if ( $course['subtitle'] ) : ?>
				<p class="zeko-course-subtitle"><?php echo esc_html( $course['subtitle'] ); ?></p>
			<?php endif; ?>

			<div class="zeko-hero-meta">
				<span class="zeko-rating"><?php echo esc_html( number_format( (float) $course['avg_rating'], 1 ) ); ?> ★</span>
				<span>(<?php echo esc_html( $course['review_count'] ); ?> <?php esc_html_e( 'reviews', 'zeko-learn' ); ?>)</span>
				<span>·</span>
				<span><?php echo esc_html( $course['enrollment_count'] ); ?> <?php esc_html_e( 'students', 'zeko-learn' ); ?></span>
				<span>·</span>
				<span><?php echo esc_html( ucfirst( str_replace( '_', ' ', $course['level'] ) ) ); ?></span>
				<span>·</span>
				<span><?php echo esc_html( $course['estimated_hours'] ); ?> <?php esc_html_e( 'hours', 'zeko-learn' ); ?></span>
			</div>

			<?php if ( $instructor ) : ?>
				<div class="zeko-hero-instructor">
					<?php echo wp_kses_post( get_avatar( $course['instructor_id'], 32 ) ); ?>
					<span><?php esc_html_e( 'Created by', 'zeko-learn' ); ?> <?php echo esc_html( $instructor->display_name ); ?></span>
				</div>
			<?php endif; ?>

			<div class="zeko-hero-updated">
				<?php /* translators: %s: last updated date */ printf( esc_html__( 'Last updated %s', 'zeko-learn' ), esc_html( mysql2date( get_option( 'date_format' ), $course['updated_at'] ) ) ); ?>
				<span>·</span>
				<span><?php echo esc_html( $course['language'] ? $course['language'] : 'English' ); ?></span>
			</div>
		</div>

		<div class="zeko-course-sidebar-card">
			<div class="zeko-sidebar-thumb">
				<?php if ( $thumb_url ) : ?>
					<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $course['title'] ); ?>">
				<?php endif; ?>
			</div>
			<div class="zeko-sidebar-body">
				<div class="zeko-sidebar-price">
					<?php if ( $course['is_free'] ) : ?>
						<span class="zeko-price free"><?php esc_html_e( 'Free', 'zeko-learn' ); ?></span>
					<?php else : ?>
						<span class="zeko-price"><?php echo esc_html( $currency . number_format( (float) $course['price'], 2 ) ); ?></span>
						<?php if ( $course['sale_price'] ) : ?>
							<span class="zeko-sale-price"><?php echo esc_html( $currency . number_format( (float) $course['sale_price'], 2 ) ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<?php if ( $is_enrolled ) : ?>
					<a href="<?php echo esc_url( $learn_url ); ?>" class="button button-primary zeko-btn zeko-btn-full"><?php esc_html_e( 'Continue Learning', 'zeko-learn' ); ?></a>
				<?php elseif ( is_user_logged_in() ) : ?>
					<button class="button button-primary zeko-btn zeko-btn-full zeko-enroll-btn" data-course-id="<?php echo esc_attr( $course_id ); ?>" aria-label="<?php echo esc_attr( $course['is_free'] ? __( 'Enroll in this course', 'zeko-learn' ) : __( 'Buy this course', 'zeko-learn' ) ); ?>">
						<?php echo esc_html( $course['is_free'] ? __( 'Enroll Now', 'zeko-learn' ) : __( 'Buy Now', 'zeko-learn' ) ); ?>
					</button>
				<?php else : ?>
					<a href="<?php echo esc_url( wp_login_url( $course_url ) ); ?>" class="button button-primary zeko-btn zeko-btn-full"><?php esc_html_e( 'Log in to Enroll', 'zeko-learn' ); ?></a>
				<?php endif; ?>

				<div class="zeko-sidebar-highlights">
					<?php if ( $course['estimated_hours'] ) : ?>
						<div class="zeko-highlight"><span class="dashicons dashicons-clock"></span> <?php echo esc_html( $course['estimated_hours'] ); ?> <?php esc_html_e( 'hours of content', 'zeko-learn' ); ?></div>
					<?php endif; ?>
					<div class="zeko-highlight"><span class="dashicons dashicons-welcome-learn-more"></span> <?php echo esc_html( $total_lessons ); ?> <?php esc_html_e( 'lessons', 'zeko-learn' ); ?></div>
					<div class="zeko-highlight"><span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Certificate of completion', 'zeko-learn' ); ?></div>
					<?php if ( $course['level'] ) : ?>
						<div class="zeko-highlight"><span class="dashicons dashicons-chart-bar"></span> <?php echo esc_html( ucfirst( str_replace( '_', ' ', $course['level'] ) ) ); ?> <?php esc_html_e( 'level', 'zeko-learn' ); ?></div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Tabs -->
	<div class="zeko-course-tabs">
		<nav class="zeko-tab-nav" role="tablist" aria-label="<?php esc_attr_e( 'Course information', 'zeko-learn' ); ?>">
			<button class="zeko-tab-btn active" data-tab="overview" role="tab" aria-selected="true" aria-controls="zeko-tab-overview"><?php esc_html_e( 'Overview', 'zeko-learn' ); ?></button>
			<button class="zeko-tab-btn" data-tab="curriculum" role="tab" aria-selected="false" aria-controls="zeko-tab-curriculum"><?php esc_html_e( 'Curriculum', 'zeko-learn' ); ?> (<?php echo esc_html( $total_lessons ); ?>)</button>
			<button class="zeko-tab-btn" data-tab="reviews" role="tab" aria-selected="false" aria-controls="zeko-tab-reviews"><?php esc_html_e( 'Reviews', 'zeko-learn' ); ?> (<?php echo esc_html( count( $reviews ) ); ?>)</button>
			<?php if ( $instructor ) : ?>
				<button class="zeko-tab-btn" data-tab="instructor" role="tab" aria-selected="false" aria-controls="zeko-tab-instructor"><?php esc_html_e( 'Instructor', 'zeko-learn' ); ?></button>
			<?php endif; ?>
		</nav>

		<!-- Overview Tab -->
		<div class="zeko-tab-panel active" id="zeko-tab-overview" role="tabpanel" aria-labelledby="tab-overview">
			<?php if ( $course['what_you_learn'] ) : ?>
				<section class="zeko-section-block">
					<h2><?php esc_html_e( "What You'll Learn", 'zeko-learn' ); ?></h2>
					<div class="zeko-what-you-learn-content"><?php echo wp_kses_post( $course['what_you_learn'] ); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( $course['description'] ) : ?>
				<section class="zeko-section-block">
					<h2><?php esc_html_e( 'Description', 'zeko-learn' ); ?></h2>
					<div class="zeko-description-content"><?php echo wp_kses_post( $course['description'] ); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( $course['requirements'] ) : ?>
				<section class="zeko-section-block">
					<h2><?php esc_html_e( 'Requirements', 'zeko-learn' ); ?></h2>
					<div class="zeko-content"><?php echo wp_kses_post( $course['requirements'] ); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( $course['target_audience'] ) : ?>
				<section class="zeko-section-block">
					<h2><?php esc_html_e( 'Who This Course Is For', 'zeko-learn' ); ?></h2>
					<div class="zeko-content"><?php echo wp_kses_post( $course['target_audience'] ); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $skills ) ) : ?>
				<section class="zeko-section-block">
					<h2><?php esc_html_e( 'Skills You Will Gain', 'zeko-learn' ); ?></h2>
					<div class="zeko-skill-tags">
						<?php foreach ( $skills as $skill ) : ?>
							<span class="zeko-skill-tag"><?php echo esc_html( $skill['name'] ); ?></span>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</div>

		<!-- Curriculum Tab -->
		<div class="zeko-tab-panel" id="zeko-tab-curriculum">
			<?php if ( empty( $sections ) ) : ?>
				<p><?php esc_html_e( 'No curriculum available yet.', 'zeko-learn' ); ?></p>
			<?php else : ?>
				<?php
				$lesson_num = 0; foreach ( $sections as $section ) :
					$section_lessons = $db->get_section_lessons( (int) $section['id'] );
					$section_minutes = 0;
					foreach ( $section_lessons as $sl ) {
						$section_minutes += (int) ( $sl['estimated_minutes'] ?? 0 );
					}
					?>
					<div class="zeko-curriculum-section">
						<div class="zeko-section-header" role="button" tabindex="0">
							<div class="zeko-section-info">
								<h4><?php echo esc_html( $section['title'] ); ?></h4>
								<?php if ( $section['description'] ) : ?>
									<p><?php echo esc_html( wp_trim_words( $section['description'], 15 ) ); ?></p>
								<?php endif; ?>
							</div>
							<div class="zeko-section-meta">
								<span><?php echo esc_html( count( $section_lessons ) ); ?> <?php esc_html_e( 'lessons', 'zeko-learn' ); ?></span>
								<?php if ( $section_minutes ) : ?>
									<span> · <?php echo esc_html( $section_minutes ); ?> <?php esc_html_e( 'min', 'zeko-learn' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<ul class="zeko-lesson-list">
							<?php
							foreach ( $section_lessons as $lesson ) :
								++$lesson_num;
								$icon = 'video' === $lesson['lesson_type'] ? 'dashicons-video-alt3' : ( 'quiz' === $lesson['lesson_type'] ? 'dashicons-feedback' : ( 'assignment' === $lesson['lesson_type'] ? 'dashicons-edit' : 'dashicons-media-text' ) );
								?>
								<li class="zeko-lesson-item">
									<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
									<span class="zeko-lesson-title"><?php echo esc_html( $lesson['title'] ); ?></span>
									<?php if ( $lesson['is_preview'] ) : ?>
										<a href="#" class="zeko-preview-link" data-lesson-id="<?php echo esc_attr( $lesson['id'] ); ?>"><?php esc_html_e( 'Preview', 'zeko-learn' ); ?></a>
									<?php endif; ?>
									<?php if ( $lesson['estimated_minutes'] ) : ?>
										<span class="zeko-lesson-duration"><?php echo esc_html( $lesson['estimated_minutes'] ); ?> min</span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<!-- Reviews Tab -->
		<div class="zeko-tab-panel" id="zeko-tab-reviews">
			<?php if ( ! empty( $reviews ) ) : ?>
				<div class="zeko-reviews-summary">
					<div class="zeko-summary-left">
						<span class="zeko-big-rating"><?php echo esc_html( number_format( (float) $course['avg_rating'], 1 ) ); ?></span>
						<span class="zeko-big-stars"><?php echo esc_html( str_repeat( '★', round( (float) $course['avg_rating'] ) ) ); ?></span>
						<span class="zeko-total-reviews"><?php echo esc_html( $course['review_count'] ); ?> <?php esc_html_e( 'reviews', 'zeko-learn' ); ?></span>
					</div>
					<div class="zeko-rating-bars">
						<?php
						for ( $i = 5; $i >= 1; $i-- ) :
							$count = 0;
							foreach ( $reviews as $r ) {
								if ( (int) $r['rating'] === $i ) {
									++$count;
								}
							}
							$pct = $course['review_count'] > 0 ? round( $count / $course['review_count'] * 100 ) : 0;
							?>
							<div class="zeko-rating-bar">
								<span class="zeko-bar-label"><?php echo esc_html( $i ); ?> ★</span>
								<div class="zeko-bar-track"><div class="zeko-bar-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
								<span class="zeko-bar-count">(<?php echo esc_html( $count ); ?>)</span>
							</div>
						<?php endfor; ?>
					</div>
				</div>

				<div class="zeko-reviews-list">
					<?php foreach ( $reviews as $review ) : ?>
						<div class="zeko-review">
							<div class="zeko-review-header">
								<?php echo wp_kses_post( get_avatar( $review['user_id'], 40 ) ); ?>
								<div class="zeko-review-author">
									<strong><?php echo esc_html( $review['author_name'] ); ?></strong>
									<span class="zeko-review-rating"><?php echo esc_html( str_repeat( '★', (int) $review['rating'] ) ); ?></span>
									<span class="zeko-review-date"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $review['created_at'] ) ); ?></span>
								</div>
							</div>
							<div class="zeko-review-body"><?php echo wp_kses_post( $review['review_text'] ); ?></div>
							<?php if ( ! empty( $review['instructor_reply'] ) ) : ?>
								<div class="zeko-review-reply">
									<strong><?php esc_html_e( 'Instructor Reply', 'zeko-learn' ); ?>:</strong>
									<p><?php echo wp_kses_post( $review['instructor_reply'] ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No reviews yet. Be the first to review this course!', 'zeko-learn' ); ?></p>
			<?php endif; ?>

			<?php if ( $is_enrolled ) : ?>
				<div class="zeko-submit-review">
					<h2><?php esc_html_e( 'Write a Review', 'zeko-learn' ); ?></h2>
					<form class="zeko-review-form">
						<div class="zeko-rating-input">
							<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
								<span class="zeko-star" data-rating="<?php echo esc_attr( $i ); ?>">★</span>
							<?php endfor; ?>
						</div>
						<textarea name="review_text" rows="4" placeholder="<?php esc_attr_e( 'Share your learning experience...', 'zeko-learn' ); ?>" required></textarea>
						<input type="hidden" name="rating" value="0">
						<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Submit Review', 'zeko-learn' ); ?></button>
					</form>
				</div>
			<?php endif; ?>
		</div>

		<!-- Instructor Tab -->
		<?php if ( $instructor ) : ?>
			<div class="zeko-tab-panel" id="zeko-tab-instructor">
				<div class="zeko-instructor-profile">
					<?php echo wp_kses_post( get_avatar( $course['instructor_id'], 96 ) ); ?>
					<div class="zeko-instructor-info">
						<h2><?php echo esc_html( $instructor->display_name ); ?></h2>
						<?php
						$inst_stats = $db->get_instructor_stats( $course['instructor_id'] );
						?>
						<div class="zeko-instructor-stats">
							<span><strong><?php echo esc_html( $inst_stats['total_courses'] ); ?></strong> <?php esc_html_e( 'Courses', 'zeko-learn' ); ?></span>
							<span><strong><?php echo esc_html( $inst_stats['total_students'] ); ?></strong> <?php esc_html_e( 'Students', 'zeko-learn' ); ?></span>
							<span><strong><?php echo esc_html( number_format( (float) $inst_stats['avg_rating'], 1 ) ); ?></strong> <?php esc_html_e( 'Avg Rating', 'zeko-learn' ); ?></span>
						</div>
						<?php if ( $instructor->description ) : ?>
							<div class="zeko-instructor-bio"><?php echo wp_kses_post( $instructor->description ); ?></div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
