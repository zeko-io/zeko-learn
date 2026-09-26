<?php
/**
 * Course catalog template.
 *
 * Renders course grid with filter sidebar.
 *
 * @package Zeko_Learn
 * @var array $args Template args (category_slug, etc.)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$db_table_courses = $wpdb->prefix . 'zeko_courses';
$db_table_cats    = $wpdb->prefix . 'zeko_categories';

$current_category = isset( $args['category_slug'] ) ? sanitize_title_for_query( $args['category_slug'] ) : '';
$current_level    = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only catalog GET filter, sanitized and used only for equality matches below.
$current_price    = isset( $_GET['price'] ) ? sanitize_text_field( wp_unslash( $_GET['price'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only catalog GET filter, sanitized and used only for equality matches below.
$current_search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only catalog GET filter, sanitized and escaped via esc_like()/esc_html() below.
$current_sort     = isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : 'newest'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only catalog GET filter, sanitized and matched against a fixed switch allow-list below.
$page_num            = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only catalog pagination flag; absint() coerced.
$items_per_page         = 12;

$where  = "WHERE c.status = 'published'";
$params = array();

if ( $current_category ) {
	$where   .= ' AND cat.slug = %s';
	$params[] = $current_category;
}
if ( $current_level ) {
	$where   .= ' AND c.level = %s';
	$params[] = $current_level;
}
if ( 'free' === $current_price ) {
	$where .= ' AND c.is_free = 1';
} elseif ( 'paid' === $current_price ) {
	$where .= ' AND c.is_free = 0';
}
if ( $current_search ) {
	$where      .= ' AND (c.title LIKE %s OR c.description LIKE %s)';
	$search_term = '%' . $wpdb->esc_like( $current_search ) . '%';
	$params[]    = $search_term;
	$params[]    = $search_term;
}

$sort_order = 'ORDER BY c.created_at DESC';
switch ( $current_sort ) {
	case 'popular':
		$sort_order = 'ORDER BY c.enrollment_count DESC';
		break;
	case 'rating':
		$sort_order = 'ORDER BY c.avg_rating DESC';
		break;
	case 'price_low':
		$sort_order = 'ORDER BY c.price ASC';
		break;
	case 'price_high':
		$sort_order = 'ORDER BY c.price DESC';
		break;
}

$count_query = "SELECT COUNT(*) FROM {$db_table_courses} c LEFT JOIN {$db_table_cats} cat ON c.category_id = cat.id {$where}";
$total       = ! empty( $params ) ? (int) $wpdb->get_var( $wpdb->prepare( $count_query, $params ) ) : (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

$offset         = ( $page_num - 1 ) * $items_per_page;
$params[]       = $items_per_page;
$params[]       = $offset;
$prepare_format = array_merge( array_fill( 0, count( $params ) - 2, '%s' ), array( '%d', '%d' ) );

$query = "SELECT c.*, cat.name AS category_name, cat.slug AS category_slug
	FROM {$db_table_courses} c
	LEFT JOIN {$db_table_cats} cat ON c.category_id = cat.id
	{$where}
	{$sort_order}
	LIMIT %d OFFSET %d";

$courses   = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
$max_pages = ceil( $total / $items_per_page );

$all_categories = $wpdb->get_results( "SELECT name, slug, course_count FROM {$db_table_cats} ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
$currency       = get_option( 'zeko_learn_currency_symbol', '$' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php /* translators: %s: course category name */ echo $current_category ? esc_html( sprintf( __( '%s Courses', 'zeko-learn' ), ucwords( str_replace( '-', ' ', $current_category ) ) ) ) : esc_html__( 'Course Catalog', 'zeko-learn' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'zeko-learn-body' ); ?>>
<?php wp_body_open(); ?>

<div class="zeko-learn-catalog-page" style="max-width:1200px;margin:0 auto;padding:0 16px;">
	<header class="zeko-catalog-header">
		<h1><?php /* translators: %s: course category name */ echo $current_category ? esc_html( sprintf( __( '%s Courses', 'zeko-learn' ), ucwords( str_replace( '-', ' ', $current_category ) ) ) ) : esc_html__( 'Course Catalog', 'zeko-learn' ); ?></h1>
		<?php if ( $current_search ) : ?>
			<p><?php /* translators: %s: search keyword */ /* translators: %s: search keyword */ printf( esc_html__( 'Showing results for "%s"', 'zeko-learn' ), esc_html( $current_search ) ); ?> (<?php echo esc_html( $total ); ?>)</p>
		<?php endif; ?>
	</header>

	<form class="zeko-catalog-filters" method="get" action="" role="search" aria-label="<?php esc_attr_e( 'Filter courses', 'zeko-learn' ); ?>">
		<?php if ( $current_category ) : ?>
			<input type="hidden" name="category" value="<?php echo esc_attr( $current_category ); ?>">
		<?php endif; ?>

		<div class="zeko-filter-row">
			<div class="zeko-filter-group">
				<label for="zeko-filter-search"><?php esc_html_e( 'Search', 'zeko-learn' ); ?></label>
				<input type="search" id="zeko-filter-search" name="s" value="<?php echo esc_attr( $current_search ); ?>" placeholder="<?php esc_attr_e( 'Search courses...', 'zeko-learn' ); ?>">
			</div>
			<div class="zeko-filter-group">
				<label for="zeko-filter-level"><?php esc_html_e( 'Level', 'zeko-learn' ); ?></label>
				<select id="zeko-filter-level" name="level">
					<option value=""><?php esc_html_e( 'All Levels', 'zeko-learn' ); ?></option>
					<option value="beginner" <?php selected( $current_level, 'beginner' ); ?>><?php esc_html_e( 'Beginner', 'zeko-learn' ); ?></option>
					<option value="intermediate" <?php selected( $current_level, 'intermediate' ); ?>><?php esc_html_e( 'Intermediate', 'zeko-learn' ); ?></option>
					<option value="advanced" <?php selected( $current_level, 'advanced' ); ?>><?php esc_html_e( 'Advanced', 'zeko-learn' ); ?></option>
				</select>
			</div>
			<div class="zeko-filter-group">
				<label for="zeko-filter-price"><?php esc_html_e( 'Price', 'zeko-learn' ); ?></label>
				<select id="zeko-filter-price" name="price">
					<option value=""><?php esc_html_e( 'All', 'zeko-learn' ); ?></option>
					<option value="free" <?php selected( $current_price, 'free' ); ?>><?php esc_html_e( 'Free', 'zeko-learn' ); ?></option>
					<option value="paid" <?php selected( $current_price, 'paid' ); ?>><?php esc_html_e( 'Paid', 'zeko-learn' ); ?></option>
				</select>
			</div>
			<div class="zeko-filter-group">
				<label for="zeko-filter-sort"><?php esc_html_e( 'Sort', 'zeko-learn' ); ?></label>
				<select id="zeko-filter-sort" name="sort">
					<option value="newest" <?php selected( $current_sort, 'newest' ); ?>><?php esc_html_e( 'Newest', 'zeko-learn' ); ?></option>
					<option value="popular" <?php selected( $current_sort, 'popular' ); ?>><?php esc_html_e( 'Most Popular', 'zeko-learn' ); ?></option>
					<option value="rating" <?php selected( $current_sort, 'rating' ); ?>><?php esc_html_e( 'Highest Rated', 'zeko-learn' ); ?></option>
					<option value="price_low" <?php selected( $current_sort, 'price_low' ); ?>><?php esc_html_e( 'Price: Low to High', 'zeko-learn' ); ?></option>
					<option value="price_high" <?php selected( $current_sort, 'price_high' ); ?>><?php esc_html_e( 'Price: High to Low', 'zeko-learn' ); ?></option>
				</select>
			</div>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'zeko-learn' ); ?></button>
		</div>

		<?php if ( ! empty( $all_categories ) ) : ?>
			<div class="zeko-category-nav">
				<a href="<?php echo esc_url( remove_query_arg( 'category' ) ); ?>" class="zeko-cat-link <?php echo $current_category ? '' : 'active'; ?>"><?php esc_html_e( 'All', 'zeko-learn' ); ?></a>
				<?php foreach ( $all_categories as $category_id ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'category', $category_id['slug'] ) ); ?>" class="zeko-cat-link <?php echo $current_category === $category_id['slug'] ? 'active' : ''; ?>">
						<?php echo esc_html( $category_id['name'] ); ?> <span class="zeko-cat-count">(<?php echo esc_html( $category_id['course_count'] ); ?>)</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</form>

	<?php if ( empty( $courses ) ) : ?>
		<div class="zeko-catalog-empty">
			<p><?php esc_html_e( 'No courses found matching your criteria.', 'zeko-learn' ); ?></p>
		</div>
	<?php else : ?>
		<div class="zeko-catalog-grid">
			<?php
			foreach ( $courses as $course ) :
				$thumb_url  = $course['thumbnail_id'] ? wp_get_attachment_url( $course['thumbnail_id'] ) : '';
				$course_url = home_url( '/courses/' . $course['slug'] . '/' );
				?>
				<div class="zeko-course-card">
					<a href="<?php echo esc_url( $course_url ); ?>" class="zeko-card-thumb">
						<?php if ( $thumb_url ) : ?>
							<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $course['title'] ); ?>">
						<?php else : ?>
							<div class="zeko-card-placeholder"></div>
						<?php endif; ?>
						<?php if ( $course['is_free'] ) : ?>
							<span class="zeko-card-badge zeko-free"><?php esc_html_e( 'Free', 'zeko-learn' ); ?></span>
						<?php endif; ?>
					</a>
					<div class="zeko-card-body">
						<?php if ( $course['category_name'] ) : ?>
							<span class="zeko-card-category"><?php echo esc_html( $course['category_name'] ); ?></span>
						<?php endif; ?>
						<h3><a href="<?php echo esc_url( $course_url ); ?>"><?php echo esc_html( $course['title'] ); ?></a></h3>
						<?php if ( $course['subtitle'] ) : ?>
							<p class="zeko-card-subtitle"><?php echo esc_html( wp_trim_words( $course['subtitle'], 12 ) ); ?></p>
						<?php endif; ?>
						<div class="zeko-card-instructor">
							<?php
							$instructor = get_userdata( $course['instructor_id'] );
							if ( $instructor ) {
								echo wp_kses_post( get_avatar( $course['instructor_id'], 24 ) ) . ' ' . esc_html( $instructor->display_name );
							}
							?>
						</div>
						<div class="zeko-card-meta">
							<span class="zeko-card-rating"><?php echo esc_html( number_format( (float) $course['avg_rating'], 1 ) ); ?> ★</span>
							<span class="zeko-card-students"><?php echo esc_html( $course['enrollment_count'] ); ?> <?php esc_html_e( 'students', 'zeko-learn' ); ?></span>
							<span class="zeko-card-hours"><?php echo esc_html( $course['estimated_hours'] ); ?> <?php esc_html_e( 'hrs', 'zeko-learn' ); ?></span>
						</div>
						<div class="zeko-card-footer">
							<?php if ( $course['is_free'] ) : ?>
								<span class="zeko-card-price free"><?php esc_html_e( 'Free', 'zeko-learn' ); ?></span>
							<?php else : ?>
								<span class="zeko-card-price"><?php echo esc_html( $currency . number_format( (float) $course['price'], 2 ) ); ?></span>
								<?php if ( $course['sale_price'] ) : ?>
									<span class="zeko-card-sale-price"><?php echo esc_html( $currency . number_format( (float) $course['sale_price'], 2 ) ); ?></span>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $max_pages > 1 ) : ?>
			<div class="zeko-pagination">
				<?php
				for ( $i = 1; $i <= $max_pages; $i++ ) {
					$url = add_query_arg( 'paged', $i );
					printf(
						'<a href="%s" class="page-numbers %s">%d</a>',
						esc_url( $url ),
						$i === $page_num ? 'current' : '',
						esc_html( $i )
					);
				}
				?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
