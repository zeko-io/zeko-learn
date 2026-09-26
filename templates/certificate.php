<?php
/**
 * Template: Certificate of Completion
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$number = get_query_var( 'zeko_certificate_number' );
$cert   = $this->db->get_certificate_by_number( sanitize_text_field( $number ) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo $cert ? esc_html( $cert['course_title'] . ' ' . __( '— Certificate', 'zeko-learn' ) ) : esc_html__( 'Certificate', 'zeko-learn' ); ?></title>
	<?php
	if ( $cert ) :
		$cert_url   = home_url( '/certificates/' . $cert['certificate_number'] );
		$cert_title = $cert['student_name'] . ' — ' . $cert['course_title'] . ' ' . __( 'Certificate', 'zeko-learn' );
		$cert_desc  = sprintf(
			/* translators: 1: student name, 2: course title */
			__( '%1$s has completed %2$s — verify this certificate.', 'zeko-learn' ),
			$cert['student_name'],
			$cert['course_title']
		);
		?>
	<meta property="og:title" content="<?php echo esc_attr( $cert_title ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( $cert_desc ); ?>">
	<meta property="og:url" content="<?php echo esc_url( $cert_url ); ?>">
	<meta property="og:type" content="website">
	<?php endif; ?>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body { font-family: Georgia, 'Times New Roman', serif; background: #f5f5f5; padding: 40px 20px; }
		.zl-cert {
			max-width: 800px; margin: 0 auto; padding: 60px; background: #fff;
			border: 3px solid #4f46e5; text-align: center; position: relative;
		}
		.zl-cert::before {
			content: ''; position: absolute; top: 12px; left: 12px; right: 12px; bottom: 12px;
			border: 1px solid #c7d2fe; pointer-events: none;
		}
		.zl-cert-logo { margin-bottom: 24px; }
		.zl-cert h1 { font-size: 28px; color: #4f46e5; margin-bottom: 8px; letter-spacing: 2px; text-transform: uppercase; }
		.zl-cert h2 { font-size: 16px; color: #6b7280; font-weight: 400; margin-bottom: 32px; }
		.zl-cert-preamble { font-size: 14px; color: #6b7280; margin-bottom: 12px; }
		.zl-cert-name { font-size: 32px; color: #111827; margin: 16px 0; padding-bottom: 8px; border-bottom: 2px solid #4f46e5; display: inline-block; }
		.zl-cert-course { font-size: 20px; color: #374151; margin: 20px 0; }
		.zl-cert-details { font-size: 13px; color: #6b7280; margin-top: 32px; }
		.zl-cert-details p { margin: 4px 0; }
		.zl-cert-number { font-family: monospace; background: #f3f4f6; padding: 4px 12px; border-radius: 4px; }
		.zl-cert-qr { margin-top: 24px; }
		.zl-cert-footer { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
		.zl-cert-sig { text-align: center; flex: 1; }
		.zl-cert-sig-line { width: 180px; border-bottom: 1px solid #374151; margin: 0 auto 8px; padding-bottom: 4px; font-style: italic; color: #374151; }
		.zl-cert-sig-label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
		@media print {
			body { background: #fff; padding: 0; }
			.zl-cert { border: none; box-shadow: none; }
			.zl-cert::before { border: 2px solid #4f46e5; }
		}
	</style>
</head>
<body>
<?php if ( ! $cert ) : ?>
	<div class="zl-cert">
		<h1><?php esc_html_e( 'Certificate Not Found', 'zeko-learn' ); ?></h1>
		<p><?php esc_html_e( 'The certificate number you entered could not be found.', 'zeko-learn' ); ?></p>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return Home', 'zeko-learn' ); ?></a></p>
	</div>
<?php else : ?>
	<div class="zl-cert" id="zl-certificate">
		<div class="zl-cert-logo">
			<?php
			$logo = get_option( 'zeko_learn_logo_url', '' );
			if ( $logo ) {
				echo '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" height="60">';
			} else {
				echo '<h2 style="color:#4f46e5;">' . esc_html( get_bloginfo( 'name' ) ) . '</h2>';
			}
			?>
		</div>

		<h1><?php esc_html_e( 'Certificate of Completion', 'zeko-learn' ); ?></h1>
		<h2><?php esc_html_e( 'This is to certify that', 'zeko-learn' ); ?></h2>

		<div class="zl-cert-name"><?php echo esc_html( $cert['student_name'] ); ?></div>

		<p class="zl-cert-preamble"><?php esc_html_e( 'has successfully completed the course', 'zeko-learn' ); ?></p>

		<div class="zl-cert-course"><?php echo esc_html( $cert['course_title'] ); ?></div>

		<div class="zl-cert-details">
			<p><?php esc_html_e( 'Certificate Number:', 'zeko-learn' ); ?> <span class="zl-cert-number"><?php echo esc_html( $cert['certificate_number'] ); ?></span></p>
			<p><?php esc_html_e( 'Date of Issue:', 'zeko-learn' ); ?> <?php echo esc_html( mysql2date( get_option( 'date_format' ), $cert['issued_at'] ) ); ?></p>
			<p><?php esc_html_e( 'Verify at:', 'zeko-learn' ); ?> <a href="<?php echo esc_url( home_url( '/certificates/' . $cert['certificate_number'] ) ); ?>"><?php echo esc_url( home_url( '/certificates/' . $cert['certificate_number'] ) ); ?></a></p>
		</div>

		<div class="zl-cert-footer">
			<div class="zl-cert-sig">
				<div class="zl-cert-sig-line">
				<?php
					$course     = $this->db->get_course( (int) $cert['course_id'] );
					$instructor = $course ? get_userdata( (int) $course['instructor_id'] ) : false;
				if ( $instructor ) {
					echo esc_html( $instructor->display_name );
				}
				?>
				</div>
				<div class="zl-cert-sig-label"><?php esc_html_e( 'Instructor', 'zeko-learn' ); ?></div>
			</div>
			<div class="zl-cert-sig">
				<div class="zl-cert-sig-line"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
				<div class="zl-cert-sig-label"><?php esc_html_e( 'Organization', 'zeko-learn' ); ?></div>
			</div>
		</div>
	</div>

	<div style="text-align:center; margin-top:24px;">
		<button id="zl-cert-print" style="padding:10px 24px; background:#4f46e5; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:15px;">
			<?php esc_html_e( 'Print Certificate', 'zeko-learn' ); ?>
		</button>
		<button id="zl-cert-download-pdf" style="padding:10px 24px; background:#059669; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:15px; margin-left:8px;">
			<?php esc_html_e( 'Download PDF', 'zeko-learn' ); ?>
		</button>
		<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo rawurlencode( home_url( '/certificates/' . $cert['certificate_number'] ) ); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-block; margin-left:8px; padding:10px 24px; background:#0A66C2; color:#fff; border:none; border-radius:6px; text-decoration:none; font-size:15px;">
			<?php esc_html_e( 'Share on LinkedIn', 'zeko-learn' ); ?>
		</a>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline-block; margin-left:12px; padding:10px 24px; color:#4f46e5; text-decoration:none; font-size:15px;">
			<?php esc_html_e( 'Back to Home', 'zeko-learn' ); ?>
		</a>
	</div>
	<!-- html2pdf.bundle.min.js is enqueued in Zeko_Learn_Public::enqueue_assets(). -->
	<script>
	(function() {
		var btn = document.getElementById('zl-cert-print');
		if (btn) btn.addEventListener('click', function() { window.print(); });

		var dlBtn = document.getElementById('zl-cert-download-pdf');
		if (dlBtn) {
			dlBtn.addEventListener('click', function() {
				var el = document.getElementById('zl-certificate');
				if (!el || typeof html2pdf === 'undefined') return;
				dlBtn.disabled = true;
				dlBtn.textContent = '<?php echo esc_js( __( 'Generating...', 'zeko-learn' ) ); ?>';
				html2pdf().set({
					margin: 10,
					filename: 'certificate-<?php echo esc_js( $cert['certificate_number'] ); ?>.pdf',
					image: { type: 'jpeg', quality: 0.98 },
					html2canvas: { scale: 2, useCORS: true },
					jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
				}).from(el).save().then(function() {
					dlBtn.disabled = false;
					dlBtn.textContent = '<?php echo esc_js( __( 'Download PDF', 'zeko-learn' ) ); ?>';
				});
			});
		}
	})();
	</script>
<?php endif; ?>
</body>
</html>
