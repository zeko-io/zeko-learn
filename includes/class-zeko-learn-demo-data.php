<?php
/**
 * Demo data seeder for Zeko Learn.
 *
 * Generates instructors, students, categories, skills, courses with
 * sections/lessons/quizzes, enrollments, progress, quiz attempts,
 * reviews, discussions, announcements, certificates, and payouts.
 *
 * @package Zeko_Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Learn_Demo_Data. */
class Zeko_Learn_Demo_Data {

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
	}

	/**
	 * Seed all demo data.
	 */
	public function seed(): array {
		$counts = array(
			'instructors'     => 0,
			'students'        => 0,
			'categories'      => 0,
			'skills'          => 0,
			'courses'         => 0,
			'enrollments'     => 0,
			'lesson_progress' => 0,
			'quiz_attempts'   => 0,
			'reviews'         => 0,
			'discussions'     => 0,
			'announcements'   => 0,
			'certificates'    => 0,
			'payouts'         => 0,
		);

		$instructors           = $this->seed_instructors();
		$counts['instructors'] = count( $instructors );

		$students           = $this->seed_students();
		$counts['students'] = count( $students );

		$categories           = $this->seed_categories();
		$counts['categories'] = count( $categories );

		$skills           = $this->seed_skills();
		$counts['skills'] = count( $skills );

		$quiz_ids    = array();
		$course_ids  = array();
		$course_data = $this->get_course_data();

		foreach ( $course_data as $cd ) {
			$instructor = $instructors[ array_rand( $instructors ) ];
			$cat_id     = $categories[ $cd['category'] ] ?? 0;
			$course_id  = $this->db->insert_course(
				array(
					'instructor_id'   => $instructor['id'],
					'title'           => $cd['title'],
					'slug'            => sanitize_title( $cd['title'] ),
					'subtitle'        => $cd['subtitle'],
					'description'     => $cd['description'],
					'what_you_learn'  => $cd['what_you_learn'],
					'level'           => $cd['level'],
					'language'        => 'English',
					'estimated_hours' => $cd['estimated_hours'],
					'category_id'     => $cat_id,
					'price'           => $cd['price'],
					'status'          => 'published',
					'is_featured'     => $cd['is_featured'] ?? 0,
				)
			);

			if ( ! $course_id ) {
				continue;
			}

			$course_ids[] = $course_id;
			++$counts['courses'];

			if ( ! empty( $cd['skills'] ) ) {
				foreach ( $cd['skills'] as $skill_name ) {
					if ( isset( $skills[ $skill_name ] ) ) {
						$this->db->attach_skill_to_course( $course_id, $skills[ $skill_name ] );
					}
				}
			}

			$all_lesson_ids = array();
			$section_order  = 0;
			foreach ( $cd['sections'] as $sd ) {
				$section_id = $this->db->insert_section(
					array(
						'course_id'   => $course_id,
						'title'       => $sd['title'],
						'description' => $sd['description'] ?? '',
						'sort_order'  => $section_order++,
					)
				);

				$lesson_order = 0;
				foreach ( $sd['lessons'] as $ld ) {
					$lesson_id = $this->db->insert_lesson(
						array(
							'section_id'        => $section_id,
							'course_id'         => $course_id,
							'title'             => $ld['title'],
							'slug'              => sanitize_title( $ld['title'] ),
							'lesson_type'       => $ld['type'] ?? 'text',
							'content'           => $ld['content'] ?? '',
							'video_url'         => $ld['video_url'] ?? '',
							'is_preview'        => $ld['is_preview'] ?? 0,
							'estimated_minutes' => $ld['minutes'] ?? 10,
							'sort_order'        => $lesson_order++,
						)
					);

					if ( $lesson_id ) {
						$all_lesson_ids[] = $lesson_id;
					}

					if ( 'quiz' === ( $ld['type'] ?? '' ) && ! empty( $ld['quiz'] ) ) {
						$quiz_id = $this->db->insert_quiz(
							array(
								'lesson_id'          => $lesson_id,
								'course_id'          => $course_id,
								'title'              => $ld['quiz']['title'] ?? $ld['title'] . ' Quiz',
								'description'        => $ld['quiz']['description'] ?? '',
								'time_limit_minutes' => $ld['quiz']['time_limit'] ?? 15,
								'passing_score'      => $ld['quiz']['passing_score'] ?? 70,
								'max_attempts'       => $ld['quiz']['max_attempts'] ?? 3,
								'shuffle_questions'  => 1,
								'show_answers'       => 'on_complete',
							)
						);

						if ( $quiz_id ) {
							$quiz_ids[] = $quiz_id;
						}

						if ( ! empty( $ld['quiz']['questions'] ) ) {
							$q_order = 0;
							foreach ( $ld['quiz']['questions'] as $q ) {
								$this->db->insert_quiz_question(
									array(
										'quiz_id'        => $quiz_id,
										'question_type'  => $q['type'] ?? 'single_choice',
										'question_text'  => $q['text'],
										'options'        => $q['options'] ?? array(),
										'correct_answer' => $q['correct'] ?? '',
										'explanation'    => $q['explanation'] ?? '',
										'points'         => $q['points'] ?? 1,
										'sort_order'     => $q_order++,
									)
								);
							}
						}
					}
				}
			}

			$enrollment_count  = wp_rand( 5, 15 );
			$shuffled_students = $students;
			if ( count( $shuffled_students ) > $enrollment_count ) {
				shuffle( $shuffled_students );
				$shuffled_students = array_slice( $shuffled_students, 0, $enrollment_count );
			}

			$total_lessons = count( $all_lesson_ids );
			$enrolled_data = array();

			foreach ( $shuffled_students as $student ) {
				$enrollment_status = ( wp_rand( 1, 10 ) <= 3 ) ? 'completed' : 'active';
				$enrollment_id     = $this->db->enroll_user( $student['id'], $course_id, $enrollment_status );
				++$counts['enrollments'];

				if ( 'completed' === $enrollment_status ) {
					$completed_count = $total_lessons;
				} else {
					$completed_count = (int) round( $total_lessons * wp_rand( 10, 80 ) / 100 );
				}
				$completed_count = min( $completed_count, $total_lessons );

				$pick = $all_lesson_ids;
				if ( $completed_count < $total_lessons && $completed_count > 0 ) {
					shuffle( $pick );
					$pick = array_slice( $pick, 0, $completed_count );

					foreach ( $pick as $lid ) {
						$this->db->complete_lesson( $student['id'], $lid, $course_id );
						++$counts['lesson_progress'];
					}

					$this->db->recalculate_course_progress( $student['id'], $course_id );

					$enrolled_data[] = array(
						'student'       => $student,
						'enrollment_id' => $enrollment_id,
						'status'        => $enrollment_status,
					);
				}

				if ( ! empty( $quiz_ids ) ) {
					$attempted_quizzes = array_slice( $quiz_ids, 0, wp_rand( 1, min( 3, count( $quiz_ids ) ) ) );
					foreach ( $enrolled_data as $ed ) {
						$attempts = wp_rand( 2, 5 );
						for ( $i = 0; $i < $attempts; $i++ ) {
							$qid      = $attempted_quizzes[ array_rand( $attempted_quizzes ) ];
							$quiz_row = $this->db->get_quiz( $qid );
							if ( ! $quiz_row ) {
								continue;
							}
							$questions  = $this->db->get_quiz_questions( $qid );
							$total_pts  = 0;
							$earned_pts = 0;
							foreach ( $questions as $question ) {
								$total_pts += $question['points'];
								if ( wp_rand( 1, 10 ) <= 7 ) {
									$earned_pts += $question['points'];
								}
							}
							$score = $total_pts > 0 ? round( ( $earned_pts / $total_pts ) * 100, 2 ) : 0;

							$this->db->insert_quiz_attempt(
								array(
									'quiz_id'            => $qid,
									'user_id'            => $ed['student']['id'],
									'score'              => $score,
									'total_points'       => $total_pts,
									'earned_points'      => $earned_pts,
									'is_passed'          => $score >= $quiz_row['passing_score'] ? 1 : 0,
									'time_taken_seconds' => wp_rand( 120, 900 ),
								)
							);
							++$counts['quiz_attempts'];
						}
					}
				}
			}
		}

		$counts['reviews']       = $this->seed_reviews( $course_ids, $students );
		$counts['discussions']   = $this->seed_discussions( $course_ids, $instructors, $students );
		$counts['announcements'] = $this->seed_announcements( $course_ids, $instructors );
		$counts['certificates']  = $this->seed_certificates( $course_ids, $students );
		$counts['payouts']       = $this->seed_payouts( $course_ids, $instructors );

		return $counts;
	}

	/**
	 * Remove all demo data.
	 */
	public function clear(): bool {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_course_skills" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_course_sections" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_lessons" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_enrollments" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_lesson_progress" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_course_progress" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_quizzes" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_quiz_questions" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_quiz_attempts" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_reviews" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_discussions" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_discussion_votes" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_notes" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_bookmarks" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_certificates" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_course_announcements" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_instructor_payouts" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_categories WHERE id > 0" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_skills WHERE id > 0" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}zeko_courses WHERE title LIKE 'Demo:%'" );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// Demo users (tagged by zeko_demo_user meta). Never delete the current admin.
		$demo_user_ids = get_users(
			array(
				'meta_key'   => 'zeko_demo_user',
				'meta_value' => '1',
				'fields'     => 'ID',
				'number'     => 200,
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		// Legacy accounts seeded before the zeko_demo_user tag existed.
		$legacy_users  = get_users(
			array(
				'fields' => 'ID',
				'number' => 200,
				'search' => 'demo_instructor*',
			)
		);
		$legacy_users  = array_merge(
			$legacy_users,
			get_users(
				array(
					'fields' => 'ID',
					'number' => 200,
					'search' => 'demo_student*',
				)
			)
		);
		$demo_user_ids = array_unique( array_merge( $demo_user_ids, $legacy_users ) );

		$current_user_id = get_current_user_id();
		foreach ( $demo_user_ids as $user_id ) {
			if ( $current_user_id !== (int) $user_id ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( (int) $user_id );
			}
		}

		return true;
	}

	// ─── Seed Instructors ──────────────────────────────────────.

	/**
	 * Seed instructors.
	 */
	private function seed_instructors(): array {
		$instructors_data = array(
			array(
				'login'     => 'demo_instructor_sarah',
				'email'     => 'sarah.chen@demo.local',
				'name'      => 'Sarah Chen',
				'bio'       => 'Senior full-stack engineer with 12 years of experience building scalable web applications. Previously at Google and Stripe.',
				'expertise' => 'Web Development,JavaScript,React,Node.js',
			),
			array(
				'login'     => 'demo_instructor_james',
				'email'     => 'james.mitchell@demo.local',
				'name'      => 'James Mitchell',
				'bio'       => 'Data scientist and Python enthusiast. PhD in Computer Science from MIT. Published researcher in machine learning.',
				'expertise' => 'Data Science,Python,Machine Learning,Statistics',
			),
			array(
				'login'     => 'demo_instructor_olivia',
				'email'     => 'olivia.park@demo.local',
				'name'      => 'Olivia Park',
				'bio'       => 'Lead UX designer at a Fortune 500 company. Passionate about creating accessible and beautiful digital experiences.',
				'expertise' => 'UX Design,Figma,User Research,Prototyping',
			),
			array(
				'login'     => 'demo_instructor_marcus',
				'email'     => 'marcus.johnson@demo.local',
				'name'      => 'Marcus Johnson',
				'bio'       => 'DevOps architect and cloud infrastructure expert. AWS Solutions Architect Pro certified. 10+ years in IT operations.',
				'expertise' => 'DevOps,Docker,AWS,Cloud Infrastructure,CI/CD',
			),
			array(
				'login'     => 'demo_instructor_emma',
				'email'     => 'emma.williams@demo.local',
				'name'      => 'Emma Williams',
				'bio'       => 'Digital marketing strategist who has helped 200+ startups grow. Former Head of Marketing at HubSpot.',
				'expertise' => 'Marketing,SEO,Content Strategy,Growth Hacking',
			),
		);

		$instructors = array();
		foreach ( $instructors_data as $data ) {
			$existing = get_users(
				array(
					'role'   => 'author',
					'login'  => $data['login'],
					'fields' => 'ID',
					'number' => 1,
				)
			);

			if ( ! empty( $existing ) ) {
				update_user_meta( (int) $existing[0], 'zeko_demo_user', 1 );
				$instructors[] = array(
					'id'   => (int) $existing[0],
					'name' => $data['name'],
				);
				continue;
			}

			$user_id = wp_insert_user(
				array(
					'user_login'   => $data['login'],
					'user_pass'    => wp_generate_password(),
					'user_email'   => $data['email'],
					'display_name' => $data['name'],
					'role'         => 'author',
				)
			);

			if ( is_wp_error( $user_id ) ) {
				continue;
			}

			update_user_meta( $user_id, 'zeko_demo_user', 1 );
			update_user_meta( $user_id, 'zeko_learn_instructor_display_name', $data['name'] );
			update_user_meta( $user_id, 'zeko_learn_instructor_bio', $data['bio'] );
			update_user_meta( $user_id, 'zeko_learn_instructor_expertise', $data['expertise'] );

			$instructors[] = array(
				'id'   => $user_id,
				'name' => $data['name'],
			);
		}

		return $instructors;
	}

	// ─── Seed Students ─────────────────────────────────────────.

	/**
	 * Seed students.
	 */
	private function seed_students(): array {
		$students_data = array(
			array(
				'login' => 'demo_alice_jones',
				'email' => 'alice.jones@demo.local',
				'name'  => 'Alice Jones',
			),
			array(
				'login' => 'demo_bob_smith',
				'email' => 'bob.smith@demo.local',
				'name'  => 'Bob Smith',
			),
			array(
				'login' => 'demo_carlos_garcia',
				'email' => 'carlos.garcia@demo.local',
				'name'  => 'Carlos Garcia',
			),
			array(
				'login' => 'demo_diana_lee',
				'email' => 'diana.lee@demo.local',
				'name'  => 'Diana Lee',
			),
			array(
				'login' => 'demo_ethan_brown',
				'email' => 'ethan.brown@demo.local',
				'name'  => 'Ethan Brown',
			),
			array(
				'login' => 'demo_fiona_davis',
				'email' => 'fiona.davis@demo.local',
				'name'  => 'Fiona Davis',
			),
			array(
				'login' => 'demo_george_wilson',
				'email' => 'george.wilson@demo.local',
				'name'  => 'George Wilson',
			),
			array(
				'login' => 'demo_hannah_martin',
				'email' => 'hannah.martin@demo.local',
				'name'  => 'Hannah Martin',
			),
			array(
				'login' => 'demo_ian_thompson',
				'email' => 'ian.thompson@demo.local',
				'name'  => 'Ian Thompson',
			),
			array(
				'login' => 'demo_julia_white',
				'email' => 'julia.white@demo.local',
				'name'  => 'Julia White',
			),
			array(
				'login' => 'demo_kevin_clark',
				'email' => 'kevin.clark@demo.local',
				'name'  => 'Kevin Clark',
			),
			array(
				'login' => 'demo_linda_hall',
				'email' => 'linda.hall@demo.local',
				'name'  => 'Linda Hall',
			),
			array(
				'login' => 'demo_mike_young',
				'email' => 'mike.young@demo.local',
				'name'  => 'Mike Young',
			),
			array(
				'login' => 'demo_nina_king',
				'email' => 'nina.king@demo.local',
				'name'  => 'Nina King',
			),
			array(
				'login' => 'demo_oscar_wright',
				'email' => 'oscar.wright@demo.local',
				'name'  => 'Oscar Wright',
			),
			array(
				'login' => 'demo_patricia_lopez',
				'email' => 'patricia.lopez@demo.local',
				'name'  => 'Patricia Lopez',
			),
			array(
				'login' => 'demo_quinn_scott',
				'email' => 'quinn.scott@demo.local',
				'name'  => 'Quinn Scott',
			),
			array(
				'login' => 'demo_rachel_green',
				'email' => 'rachel.green@demo.local',
				'name'  => 'Rachel Green',
			),
			array(
				'login' => 'demo_sam_adams',
				'email' => 'sam.adams@demo.local',
				'name'  => 'Sam Adams',
			),
			array(
				'login' => 'demo_tina_nguyen',
				'email' => 'tina.nguyen@demo.local',
				'name'  => 'Tina Nguyen',
			),
			array(
				'login' => 'demo_uma_patel',
				'email' => 'uma.patel@demo.local',
				'name'  => 'Uma Patel',
			),
			array(
				'login' => 'demo_victor_chang',
				'email' => 'victor.chang@demo.local',
				'name'  => 'Victor Chang',
			),
			array(
				'login' => 'demo_wendy_baker',
				'email' => 'wendy.baker@demo.local',
				'name'  => 'Wendy Baker',
			),
			array(
				'login' => 'demo_xavier_reed',
				'email' => 'xavier.reed@demo.local',
				'name'  => 'Xavier Reed',
			),
			array(
				'login' => 'demo_yuki_tanaka',
				'email' => 'yuki.tanaka@demo.local',
				'name'  => 'Yuki Tanaka',
			),
		);

		$students = array();
		foreach ( $students_data as $data ) {
			$existing = get_users(
				array(
					'role'   => 'subscriber',
					'login'  => $data['login'],
					'fields' => 'ID',
					'number' => 1,
				)
			);

			if ( ! empty( $existing ) ) {
				update_user_meta( (int) $existing[0], 'zeko_demo_user', 1 );
				$students[] = array(
					'id'   => (int) $existing[0],
					'name' => $data['name'],
				);
				continue;
			}

			$user_id = wp_insert_user(
				array(
					'user_login'   => $data['login'],
					'user_pass'    => wp_generate_password(),
					'user_email'   => $data['email'],
					'display_name' => $data['name'],
					'role'         => 'subscriber',
				)
			);

			if ( is_wp_error( $user_id ) ) {
				continue;
			}

			update_user_meta( $user_id, 'zeko_demo_user', 1 );

			$students[] = array(
				'id'   => $user_id,
				'name' => $data['name'],
			);
		}

		return $students;
	}

	// ─── Seed Categories ───────────────────────────────────────.

	/**
	 * Seed categories.
	 */
	private function seed_categories(): array {
		global $wpdb;
		$categories = array(
			'Web Development' => 'Courses on HTML, CSS, JavaScript, and modern web frameworks.',
			'Python'          => 'Python programming from basics to advanced applications.',
			'Data Science'    => 'Data analysis, visualization, machine learning, and AI.',
			'UX Design'       => 'User experience and interface design principles and tools.',
			'Business'        => 'Business strategy, management, entrepreneurship, and finance.',
			'Photography'     => 'Photography techniques, composition, editing, and post-processing.',
			'Marketing'       => 'Digital marketing, SEO, social media, and growth strategies.',
			'DevOps'          => 'Continuous integration, deployment, cloud, and infrastructure.',
		);

		$ids = array();
		foreach ( $categories as $name => $desc ) {
			$slug     = sanitize_title( $name );
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}zeko_categories WHERE slug = %s", $slug ) );
			if ( $existing ) {
				$ids[ $name ] = (int) $existing;
				continue;
			}
			$ids[ $name ] = $this->db->insert_category(
				array(
					'name'        => $name,
					'slug'        => $slug,
					'description' => $desc,
				)
			);
		}
		return $ids;
	}

	// ─── Seed Skills ───────────────────────────────────────────.

	/**
	 * Seed skills.
	 */
	private function seed_skills(): array {
		global $wpdb;
		$skills_list = array(
			'HTML',
			'CSS',
			'JavaScript',
			'React',
			'PHP',
			'Python',
			'Django',
			'Pandas',
			'NumPy',
			'Machine Learning',
			'Figma',
			'Sketch',
			'Photoshop',
			'SEO',
			'Marketing',
			'Data Analysis',
			'SQL',
			'Node.js',
			'TypeScript',
			'REST API',
			'Docker',
			'AWS',
			'Git',
			'Agile',
			'Video Editing',
		);

		$ids = array();
		foreach ( $skills_list as $name ) {
			$slug     = sanitize_title( $name );
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}zeko_skills WHERE slug = %s", $slug ) );
			if ( $existing ) {
				$ids[ $name ] = (int) $existing;
				continue;
			}
			$ids[ $name ] = $this->db->insert_skill(
				array(
					'name' => $name,
					'slug' => $slug,
				)
			);
		}
		return $ids;
	}

	// ─── Seed Reviews ──────────────────────────────────────────.

	/**
	 * Seed reviews.
	 *
	 * @param array $course_ids Course ids.
	 * @param array $students Students.
	 */
	private function seed_reviews( array $course_ids, array $students ): int {
		$count = 0;
		$texts = array(
			'Absolutely fantastic course. The instructor explains complex topics in a way that is easy to understand.',
			'I learned so much in just a few weeks. The projects really helped solidify my understanding.',
			'Great content and well-structured. Would highly recommend to anyone starting out.',
			'The best online course I have ever taken. Clear, concise, and practical.',
			'Good course overall. Some sections could use more depth, but a solid introduction.',
			'Very comprehensive and well-paced. I appreciated the real-world examples.',
			'I went from complete beginner to building my own projects. Incredibly valuable.',
			'Well worth the money. The quality of instruction is top-notch.',
			'Excellent course with a great curriculum. The quizzes really helped me retain information.',
			'This course exceeded my expectations. The instructor is clearly an expert in the field.',
			'I particularly enjoyed the hands-on projects. Learning by doing is the best approach.',
			'The course material is up to date and relevant to what employers are looking for.',
			'Clear explanations and great pacing. I never felt lost or overwhelmed.',
			'I would definitely recommend this course to my colleagues. It covers all the essentials.',
			'Outstanding course. I have recommended it to several friends already.',
			'Well organized with a logical progression of topics. Made learning enjoyable.',
			'Great value for the price. I learned skills that I use every day at work.',
			'The instructor is passionate and engaging. Makes even dry topics interesting.',
		);

		foreach ( $course_ids as $course_id ) {
			$review_count = wp_rand( 3, 8 );
			$pool         = $students;
			if ( count( $pool ) > $review_count ) {
				shuffle( $pool );
				$pool = array_slice( $pool, 0, $review_count );
			}

			foreach ( $pool as $student ) {
				$this->db->insert_review(
					array(
						'course_id'   => $course_id,
						'user_id'     => $student['id'],
						'rating'      => wp_rand( 3, 5 ),
						'review_text' => $texts[ array_rand( $texts ) ],
					)
				);
				++$count;
			}
		}

		return $count;
	}

	// ─── Seed Discussions ──────────────────────────────────────.

	/**
	 * Seed discussions.
	 *
	 * @param array $course_ids Course ids.
	 * @param array $instructors Instructors.
	 * @param array $students Students.
	 */
	private function seed_discussions( array $course_ids, array $instructors, array $students ): int {
		$count  = 0;
		$topics = array(
			array(
				'title'   => 'Confused about flexbox alignment',
				'content' => 'Can someone explain when to use align-items vs align-content? I keep mixing them up.',
			),
			array(
				'title'   => 'Best practices for responsive images',
				'content' => 'What is the recommended approach for handling responsive images in 2025?',
			),
			array(
				'title'   => 'How to handle errors in async/await?',
				'content' => 'Should I use try/catch or .catch() when working with async functions?',
			),
			array(
				'title'   => 'Difference between let and const',
				'content' => 'I understand var and let, but when should I use const vs let?',
			),
			array(
				'title'   => 'Deploying to production',
				'content' => 'What is the simplest way to deploy a Node.js app to a cloud provider?',
			),
			array(
				'title'   => 'Struggling with CSS Grid',
				'content' => 'Grid layout is confusing me. Are there any good visual tools to practice?',
			),
			array(
				'title'   => 'Redux vs Context API',
				'content' => 'When is Redux actually needed over React Context for state management?',
			),
			array(
				'title'   => 'How to structure a Django project?',
				'content' => 'What is the best directory structure for a medium-sized Django project?',
			),
			array(
				'title'   => 'Understanding Pandas groupby',
				'content' => 'I am struggling to understand multi-level groupby operations. Any tips?',
			),
			array(
				'title'   => 'Figma component best practices',
				'content' => 'How do you organize your Figma components for a large design system?',
			),
			array(
				'title'   => 'Tips for portfolio websites',
				'content' => 'What should I include in my developer portfolio to stand out?',
			),
			array(
				'title'   => 'SQL JOIN types explained',
				'content' => 'I always get confused between LEFT JOIN and INNER JOIN. Any simple examples?',
			),
			array(
				'title'   => 'Docker compose networking',
				'content' => 'How do services in docker-compose discover each other by name?',
			),
			array(
				'title'   => 'Machine learning project workflow',
				'content' => 'What is the standard workflow for an ML project from data to deployment?',
			),
			array(
				'title'   => 'Git rebase vs merge',
				'content' => 'When should I use git rebase instead of git merge?',
			),
			array(
				'title'   => 'SEO meta tags question',
				'content' => 'Which meta tags are most important for SEO in 2025?',
			),
			array(
				'title'   => 'React useEffect cleanup',
				'content' => 'I am getting memory leaks in my useEffect. How do I properly clean up?',
			),
			array(
				'title'   => 'Learning path for full-stack',
				'content' => 'What order should I learn frontend, backend, and databases?',
			),
			array(
				'title'   => 'AWS free tier limitations',
				'content' => 'What are the main limitations of the AWS free tier I should know about?',
			),
			array(
				'title'   => 'Photography composition rules',
				'content' => 'What are the most important composition rules every photographer should know?',
			),
			array(
				'title'   => 'Business model canvas tips',
				'content' => 'Has anyone found the business model canvas useful for real startups?',
			),
			array(
				'title'   => 'CSS animations performance',
				'content' => 'Are CSS animations slower than JavaScript animations? Which should I prefer?',
			),
			array(
				'title'   => 'TypeScript generics confusion',
				'content' => 'Can someone explain TypeScript generics with a simple real-world example?',
			),
		);

		$replies = array(
			'Thanks for asking! I had the same question and this thread helped a lot.',
			'Great question. I think the key is to experiment and see what works for your use case.',
			'I found this article really helpful for understanding this topic better.',
			'The instructor covered this in the bonus lecture. Highly recommend watching that section.',
			'Practice is the best way to learn. Try building a small project with this concept.',
		);

		$instructor_replies = array(
			'Great question! The key difference is that align-items aligns children along the cross axis, while align-content distributes space between flex lines.',
			'I recommend using the srcset attribute with the picture element for optimal responsive images.',
			'Try/catch is generally preferred with async/await as it provides cleaner error handling.',
			'Use const by default and only switch to let when you need to reassign the variable.',
			'The simplest path is usually a PaaS like Vercel or Railway for quick deployments.',
			'Check out CSS Grid Garden - it is an excellent interactive tool for learning grid layouts.',
			'For most apps, Context API with useReducer is sufficient. Add Redux when you have complex cross-cutting state.',
		);

		$course_pool = $course_ids;
		$topic_idx   = 0;

		foreach ( $course_pool as $course_id ) {
			$discussion_count = wp_rand( 2, 4 );
			$topic_count      = count( $topics );
			for ( $i = 0; $i < $discussion_count && $topic_idx < $topic_count; $i++ ) {
				$topic         = $topics[ $topic_idx++ ];
				$student       = $students[ array_rand( $students ) ];
				$discussion_id = $this->db->insert_discussion(
					array(
						'course_id' => $course_id,
						'user_id'   => $student['id'],
						'title'     => $topic['title'],
						'content'   => $topic['content'],
					)
				);
				++$count;

				if ( wp_rand( 1, 3 ) === 1 ) {
					$instructor = $instructors[ array_rand( $instructors ) ];
					$this->db->insert_discussion(
						array(
							'course_id'           => $course_id,
							'user_id'             => $instructor['id'],
							'parent_id'           => $discussion_id,
							'title'               => '',
							'content'             => $instructor_replies[ array_rand( $instructor_replies ) ],
							'is_instructor_reply' => 1,
						)
					);
					++$count;
				} elseif ( wp_rand( 1, 4 ) === 1 ) {
					$reply_student = $students[ array_rand( $students ) ];
					$this->db->insert_discussion(
						array(
							'course_id' => $course_id,
							'user_id'   => $reply_student['id'],
							'parent_id' => $discussion_id,
							'title'     => '',
							'content'   => $replies[ array_rand( $replies ) ],
						)
					);
					++$count;
				}
			}
		}

		return $count;
	}

	// ─── Seed Announcements ────────────────────────────────────.

	/**
	 * Seed announcements.
	 *
	 * @param array $course_ids Course ids.
	 * @param array $instructors Instructors.
	 */
	private function seed_announcements( array $course_ids, array $instructors ): int {
		$count         = 0;
		$announcements = array(
			array(
				'title'   => 'Welcome to the course!',
				'content' => '<p>I am thrilled to have you here. Make sure to introduce yourself in the discussions section. This course is regularly updated with new content.</p>',
				'pinned'  => 1,
			),
			array(
				'title'   => 'New section added!',
				'content' => '<p>I just added a brand new section with advanced topics. Check it out and let me know your feedback in the Q&A.</p>',
				'pinned'  => 0,
			),
			array(
				'title'   => 'Office hours this Friday',
				'content' => '<p>Join me for a live Q&A session this Friday at 3 PM EST. Bring your questions and project ideas!</p>',
				'pinned'  => 0,
			),
			array(
				'title'   => 'Course update - 2025 refresh',
				'content' => '<p>I have updated all lessons to reflect the latest best practices and tools. Existing students get free access to all new material.</p>',
				'pinned'  => 1,
			),
			array(
				'title'   => 'Bonus resources available',
				'content' => '<p>Download the supplementary cheat sheets and reference guides from the resources section of the course.</p>',
				'pinned'  => 0,
			),
		);

		$idx = 0;
		foreach ( $course_ids as $course_id ) {
			$count_per_course    = wp_rand( 1, 2 );
			$announcement_count = count( $announcements );
			for ( $i = 0; $i < $count_per_course && $idx < $announcement_count; $i++ ) {
				$ann   = $announcements[ $idx++ ];
				$instr = $instructors[ array_rand( $instructors ) ];
				$this->db->insert_announcement(
					array(
						'course_id'     => $course_id,
						'instructor_id' => $instr['id'],
						'title'         => $ann['title'],
						'content'       => $ann['content'],
					)
				);
				++$count;
			}
		}

		return $count;
	}

	// ─── Seed Certificates ─────────────────────────────────────.

	/**
	 * Seed certificates.
	 *
	 * @param array $course_ids Course ids.
	 * @param array $students Students.
	 */
	private function seed_certificates( array $course_ids, array $students ): int {
		unset( $students );
		unset( $course_ids );
		global $wpdb;
		$count = 0;

		$completed = $wpdb->get_results(
			"SELECT e.id AS enrollment_id, e.user_id, e.course_id
			FROM {$wpdb->prefix}zeko_enrollments e
			WHERE e.status = 'completed'
			ORDER BY RAND()
			LIMIT 8",
			ARRAY_A
		);

		if ( ! $completed ) {
			return 0;
		}

		foreach ( $completed as $row ) {
			$cert_number = strtoupper( 'ZL' ) . '-' . wp_generate_uuid4();
			$wpdb->insert(
				$wpdb->prefix . 'zeko_certificates',
				array(
					'user_id'            => (int) $row['user_id'],
					'course_id'          => (int) $row['course_id'],
					'enrollment_id'      => (int) $row['enrollment_id'],
					'certificate_number' => $cert_number,
					'issued_at'          => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%d', '%s', '%s' )
			);
			++$count;
		}

		return $count;
	}

	// ─── Seed Payouts ──────────────────────────────────────────.

	/**
	 * Seed payouts.
	 *
	 * @param array $course_ids Course ids.
	 * @param array $instructors Instructors.
	 */
	private function seed_payouts( array $course_ids, array $instructors ): int {
		global $wpdb;
		$count = 0;

		$periods = array(
			array( '2025-01-01', '2025-01-31' ),
			array( '2025-02-01', '2025-02-28' ),
			array( '2025-03-01', '2025-03-31' ),
			array( '2025-04-01', '2025-04-30' ),
		);

		$paid_course_ids = array_slice( $course_ids, 0, 5 );

		foreach ( $paid_course_ids as $course_id ) {
			$instructor = $instructors[ array_rand( $instructors ) ];
			$period     = $periods[ array_rand( $periods ) ];
			$amount     = round( wp_rand( 5000, 25000 ) / 100, 2 );

			$payout_id = $this->db->insert_payout(
				array(
					'instructor_id' => $instructor['id'],
					'course_id'     => $course_id,
					'amount'        => $amount,
					'period_start'  => $period[0] . ' 00:00:00',
					'period_end'    => $period[1] . ' 23:59:59',
				)
			);

			if ( $payout_id ) {
				$wpdb->update(
					$wpdb->prefix . 'zeko_instructor_payouts',
					array(
						'status'  => 'paid',
						'paid_at' => current_time( 'mysql', true ),
					),
					array( 'id' => $payout_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				++$count;
			}
		}

		return $count;
	}

	// ─── Course Data ───────────────────────────────────────────.

	/**
	 * Course data.
	 */
	private function get_course_data(): array {
		return array(
			// ── Course 1 ──────────────────────────────────────.
			array(
				'title'           => 'Demo: Complete Web Development Bootcamp',
				'subtitle'        => 'Master HTML, CSS, JavaScript, React and Node.js',
				'description'     => '<p>A comprehensive course covering modern web development from front-end to back-end.</p><p>You will build real-world projects and learn best practices used by professional developers every day.</p>',
				'what_you_learn'  => '<ul><li>Build responsive websites with HTML5 and CSS3</li><li>Create interactive apps with JavaScript and React</li><li>Build REST APIs with Node.js and Express</li><li>Deploy projects to production</li><li>Write clean, maintainable code</li></ul>',
				'level'           => 'beginner',
				'estimated_hours' => 42,
				'category'        => 'Web Development',
				'price'           => 49.99,
				'is_featured'     => 1,
				'skills'          => array( 'HTML', 'CSS', 'JavaScript', 'React', 'Node.js' ),
				'sections'        => array(
					array(
						'title'       => 'Getting Started',
						'description' => 'Set up your development environment and learn how the web works.',
						'lessons'     => array(
							array(
								'title'      => 'Course Overview',
								'type'       => 'video',
								'minutes'    => 5,
								'is_preview' => 1,
								'video_url'  => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
							array(
								'title'      => 'Setting Up Your Environment',
								'type'       => 'text',
								'content'    => '<p>Install VS Code, Chrome, and Node.js to follow along with the course.</p>',
								'minutes'    => 15,
								'is_preview' => 1,
							),
							array(
								'title'   => 'How the Web Works',
								'type'    => 'text',
								'content' => '<p>Understanding HTTP, DNS, browsers, and servers in depth.</p>',
								'minutes' => 20,
							),
							array(
								'title'   => 'Your First Web Page',
								'type'    => 'text',
								'content' => '<p>Create a simple HTML page and open it in your browser.</p>',
								'minutes' => 15,
							),
						),
					),
					array(
						'title'       => 'HTML & CSS Fundamentals',
						'description' => 'Build the structure and style of web pages from scratch.',
						'lessons'     => array(
							array(
								'title'   => 'HTML Elements & Tags',
								'type'    => 'text',
								'content' => '<p>Learn the most common HTML elements: headings, paragraphs, links, images, forms.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'CSS Selectors & Properties',
								'type'    => 'text',
								'content' => '<p>CSS selectors, box model, flexbox, and grid layout explained.</p>',
								'minutes' => 40,
							),
							array(
								'title'     => 'Responsive Design',
								'type'      => 'video',
								'minutes'   => 25,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
							array(
								'title'   => 'Project: Build a Portfolio Page',
								'type'    => 'text',
								'content' => '<p>Apply what you learned to create a personal portfolio page from scratch.</p>',
								'minutes' => 60,
							),
							array(
								'title'   => 'HTML & CSS Quiz',
								'type'    => 'quiz',
								'minutes' => 15,
								'quiz'    => array(
									'title'         => 'HTML & CSS Fundamentals Quiz',
									'time_limit'    => 15,
									'passing_score' => 70,
									'questions'     => array(
										array(
											'text'    => 'What does HTML stand for?',
											'type'    => 'single_choice',
											'options' => array( 'Hyper Text Markup Language', 'High Tech Modern Language', 'Home Tool Markup Language', 'Hyper Transfer Markup Language' ),
											'correct' => 'Hyper Text Markup Language',
										),
										array(
											'text'    => 'Which CSS property controls text size?',
											'type'    => 'single_choice',
											'options' => array( 'font-size', 'text-size', 'font-style', 'text-style' ),
											'correct' => 'font-size',
										),
										array(
											'text'        => 'HTML is a programming language.',
											'type'        => 'true_false',
											'correct'     => 'False',
											'explanation' => 'HTML is a markup language, not a programming language.',
										),
										array(
											'text'    => 'Which tag is used for the largest heading?',
											'type'    => 'single_choice',
											'options' => array( '<h6>', '<heading>', '<h1>', '<head>' ),
											'correct' => '<h1>',
										),
									),
								),
							),
						),
					),
					array(
						'title'       => 'JavaScript Essentials',
						'description' => 'Make your pages interactive with modern JavaScript.',
						'lessons'     => array(
							array(
								'title'   => 'Variables & Data Types',
								'type'    => 'text',
								'content' => '<p>var, let, const. Strings, numbers, booleans, arrays, and objects.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'Functions & Scope',
								'type'    => 'text',
								'content' => '<p>Function declarations, arrow functions, closures, and lexical scope.</p>',
								'minutes' => 35,
							),
							array(
								'title'     => 'DOM Manipulation',
								'type'      => 'video',
								'minutes'   => 25,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
							array(
								'title'   => 'Async JavaScript',
								'type'    => 'text',
								'content' => '<p>Promises, async/await, and the fetch API for network requests.</p>',
								'minutes' => 40,
							),
							array(
								'title'   => 'Event Handling Deep Dive',
								'type'    => 'text',
								'content' => '<p>Event listeners, bubbling, delegation, and custom events.</p>',
								'minutes' => 30,
							),
						),
					),
				),
			),

			// ── Course 2 ──────────────────────────────────────.
			array(
				'title'           => 'Demo: Python for Data Science',
				'subtitle'        => 'Learn Python, Pandas, NumPy, and Matplotlib',
				'description'     => '<p>Start your data science journey with Python, the most popular language in the field.</p><p>This course takes you from Python basics to data analysis with real-world datasets.</p>',
				'what_you_learn'  => '<ul><li>Write Python programs from scratch</li><li>Analyze data with Pandas DataFrames</li><li>Create stunning visualizations with Matplotlib</li><li>Perform numerical computing with NumPy</li></ul>',
				'level'           => 'intermediate',
				'estimated_hours' => 28,
				'category'        => 'Data Science',
				'price'           => 0,
				'is_featured'     => 1,
				'skills'          => array( 'Python', 'Pandas', 'NumPy', 'Data Analysis', 'SQL' ),
				'sections'        => array(
					array(
						'title'       => 'Python Basics for Data Science',
						'description' => 'Get up to speed with Python fundamentals.',
						'lessons'     => array(
							array(
								'title'      => 'Installing Python',
								'type'       => 'text',
								'content'    => '<p>Download and install Python 3.10+ from python.org.</p>',
								'minutes'    => 10,
								'is_preview' => 1,
							),
							array(
								'title'   => 'Python Syntax Crash Course',
								'type'    => 'text',
								'content' => '<p>Variables, loops, conditionals, and functions in Python.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'Lists, Tuples & Dictionaries',
								'type'    => 'text',
								'content' => '<p>Python data structures and when to use each one.</p>',
								'minutes' => 25,
							),
							array(
								'title'   => 'Working with Files',
								'type'    => 'text',
								'content' => '<p>Reading and writing CSV, JSON, and text files in Python.</p>',
								'minutes' => 20,
							),
						),
					),
					array(
						'title'       => 'Data Analysis with Pandas',
						'description' => 'Master the most important library for data manipulation.',
						'lessons'     => array(
							array(
								'title'   => 'Intro to Pandas',
								'type'    => 'text',
								'content' => '<p>DataFrames, Series, reading CSVs and exploring datasets.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'Data Cleaning',
								'type'    => 'text',
								'content' => '<p>Handling missing values, duplicates, and data type conversions.</p>',
								'minutes' => 35,
							),
							array(
								'title'     => 'Data Visualization',
								'type'      => 'video',
								'minutes'   => 20,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
							array(
								'title'   => 'Pandas Quiz',
								'type'    => 'quiz',
								'minutes' => 10,
								'quiz'    => array(
									'title'         => 'Pandas Basics Quiz',
									'time_limit'    => 10,
									'passing_score' => 60,
									'questions'     => array(
										array(
											'text'    => 'What does pd.read_csv() do?',
											'type'    => 'single_choice',
											'options' => array( 'Reads a CSV file into a DataFrame', 'Creates a CSV file', 'Deletes a CSV file', 'Compresses a CSV file' ),
											'correct' => 'Reads a CSV file into a DataFrame',
										),
										array(
											'text'    => 'A DataFrame is a 2-dimensional data structure.',
											'type'    => 'true_false',
											'correct' => 'True',
										),
										array(
											'text'    => 'Which method removes duplicate rows?',
											'type'    => 'single_choice',
											'options' => array( 'df.drop_duplicates()', 'df.remove_dupes()', 'df.unique()', 'df.nodups()' ),
											'correct' => 'df.drop_duplicates()',
										),
									),
								),
							),
						),
					),
				),
			),

			// ── Course 3 ──────────────────────────────────────.
			array(
				'title'           => 'Demo: UI/UX Design Fundamentals',
				'subtitle'        => 'Design beautiful, user-friendly interfaces',
				'description'     => '<p>Learn the principles of great user interface and experience design from the ground up.</p><p>You will master industry tools like Figma and Sketch while building a professional design portfolio.</p>',
				'what_you_learn'  => '<ul><li>Understand core UX design principles</li><li>Create wireframes and interactive prototypes</li><li>Use Figma for professional UI design</li><li>Conduct user research and usability testing</li></ul>',
				'level'           => 'beginner',
				'estimated_hours' => 18,
				'category'        => 'UX Design',
				'price'           => 29.99,
				'is_featured'     => 0,
				'skills'          => array( 'Figma', 'Sketch', 'Photoshop' ),
				'sections'        => array(
					array(
						'title'       => 'UX Principles & Research',
						'description' => 'Understand the foundation of user experience design.',
						'lessons'     => array(
							array(
								'title'      => 'What is UX Design?',
								'type'       => 'text',
								'content'    => '<p>Understanding user experience and why it matters for every product.</p>',
								'minutes'    => 15,
								'is_preview' => 1,
							),
							array(
								'title'   => 'User Research Methods',
								'type'    => 'text',
								'content' => '<p>Interviews, surveys, personas, and journey maps explained.</p>',
								'minutes' => 25,
							),
							array(
								'title'   => 'Information Architecture',
								'type'    => 'text',
								'content' => '<p>Organizing content and navigation for intuitive user flows.</p>',
								'minutes' => 20,
							),
						),
					),
					array(
						'title'       => 'Design Tools & Prototyping',
						'description' => 'Get hands-on with professional design tools.',
						'lessons'     => array(
							array(
								'title'     => 'Figma Interface Overview',
								'type'      => 'video',
								'minutes'   => 20,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
							array(
								'title'   => 'Wireframing Techniques',
								'type'    => 'text',
								'content' => '<p>Low-fidelity wireframes to quickly test ideas and layouts.</p>',
								'minutes' => 25,
							),
							array(
								'title'   => 'Building Interactive Prototypes',
								'type'    => 'text',
								'content' => '<p>Create clickable prototypes that simulate real user interactions.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'Design Systems Introduction',
								'type'    => 'text',
								'content' => '<p>How to build and maintain a scalable design system.</p>',
								'minutes' => 20,
							),
						),
					),
				),
			),

			// ── Course 4 ──────────────────────────────────────.
			array(
				'title'           => 'Demo: Advanced React & Redux',
				'subtitle'        => 'Build scalable apps with React hooks, Redux Toolkit, and TypeScript',
				'description'     => '<p>Take your React skills to the next level with advanced patterns, Redux Toolkit, and TypeScript integration.</p><p>Build production-ready applications with testing, performance optimization, and deployment.</p>',
				'what_you_learn'  => '<ul><li>Master advanced React hooks and custom hooks</li><li>State management with Redux Toolkit</li><li>Type-safe components with TypeScript</li><li>Performance optimization techniques</li><li>Write tests with React Testing Library</li></ul>',
				'level'           => 'advanced',
				'estimated_hours' => 35,
				'category'        => 'Web Development',
				'price'           => 59.99,
				'is_featured'     => 1,
				'skills'          => array( 'React', 'TypeScript', 'JavaScript', 'REST API', 'Git' ),
				'sections'        => array(
					array(
						'title'       => 'Advanced React Patterns',
						'description' => 'Deep dive into modern React architecture.',
						'lessons'     => array(
							array(
								'title'   => 'Custom Hooks in Depth',
								'type'    => 'text',
								'content' => '<p>Building reusable custom hooks for complex logic extraction.</p>',
								'minutes' => 35,
							),
							array(
								'title'   => 'Context API & Performance',
								'type'    => 'text',
								'content' => '<p>When and how to use Context API without killing performance.</p>',
								'minutes' => 30,
							),
							array(
								'title'     => 'Render Optimization',
								'type'      => 'video',
								'minutes'   => 25,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
							array(
								'title'   => 'Error Boundaries & Suspense',
								'type'    => 'text',
								'content' => '<p>Graceful error handling and code splitting with React Suspense.</p>',
								'minutes' => 25,
							),
						),
					),
					array(
						'title'       => 'Redux Toolkit & State Management',
						'description' => 'Modern Redux with less boilerplate.',
						'lessons'     => array(
							array(
								'title'   => 'Redux Toolkit Fundamentals',
								'type'    => 'text',
								'content' => '<p>createSlice, configureStore, and the modern Redux approach.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'RTK Query for APIs',
								'type'    => 'text',
								'content' => '<p>Auto-generated API slices with caching and optimistic updates.</p>',
								'minutes' => 35,
							),
							array(
								'title'   => 'Middleware & Side Effects',
								'type'    => 'text',
								'content' => '<p>Handling async operations with createAsyncThunk and middleware.</p>',
								'minutes' => 25,
							),
						),
					),
					array(
						'title'       => 'Testing & Deployment',
						'description' => 'Ship with confidence.',
						'lessons'     => array(
							array(
								'title'   => 'Unit Testing Components',
								'type'    => 'text',
								'content' => '<p>Write reliable tests with React Testing Library and Jest.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'Integration Testing',
								'type'    => 'text',
								'content' => '<p>Test complex user flows and Redux interactions.</p>',
								'minutes' => 25,
							),
							array(
								'title'     => 'CI/CD & Production Deploy',
								'type'      => 'video',
								'minutes'   => 20,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
							array(
								'title'   => 'Advanced React Quiz',
								'type'    => 'quiz',
								'minutes' => 15,
								'quiz'    => array(
									'title'         => 'Advanced React Quiz',
									'time_limit'    => 15,
									'passing_score' => 75,
									'questions'     => array(
										array(
											'text'    => 'What does useMemo do?',
											'type'    => 'single_choice',
											'options' => array( 'Memoizes expensive calculations', 'Creates new state', 'Handles side effects', 'Routes navigation' ),
											'correct' => 'Memoizes expensive calculations',
										),
										array(
											'text'    => 'useEffect runs after every render by default.',
											'type'    => 'true_false',
											'correct' => 'True',
										),
										array(
											'text'    => 'Which Redux method creates a slice of state?',
											'type'    => 'single_choice',
											'options' => array( 'createSlice', 'createReducer', 'createStore', 'createAction' ),
											'correct' => 'createSlice',
										),
										array(
											'text'    => 'RTK Query handles API caching automatically.',
											'type'    => 'true_false',
											'correct' => 'True',
										),
										array(
											'text'    => 'What is the purpose of React.lazy()?',
											'type'    => 'single_choice',
											'options' => array( 'Code splitting and lazy loading', 'Memoizing components', 'Optimizing renders', 'Handling errors' ),
											'correct' => 'Code splitting and lazy loading',
										),
									),
								),
							),
						),
					),
				),
			),

			// ── Course 5 ──────────────────────────────────────.
			array(
				'title'           => 'Demo: Digital Marketing Masterclass',
				'subtitle'        => 'SEO, social media, content marketing, and analytics',
				'description'     => '<p>Master the complete digital marketing ecosystem from SEO to social media and paid advertising.</p><p>Learn data-driven strategies that real companies use to grow their online presence.</p>',
				'what_you_learn'  => '<ul><li>Master SEO and organic search strategies</li><li>Create effective social media campaigns</li><li>Build content marketing funnels</li><li>Analyze marketing data with Google Analytics</li></ul>',
				'level'           => 'beginner',
				'estimated_hours' => 25,
				'category'        => 'Marketing',
				'price'           => 39.99,
				'is_featured'     => 0,
				'skills'          => array( 'SEO', 'Marketing', 'Data Analysis' ),
				'sections'        => array(
					array(
						'title'       => 'SEO Fundamentals',
						'description' => 'Get found on Google and drive organic traffic.',
						'lessons'     => array(
							array(
								'title'      => 'How Search Engines Work',
								'type'       => 'text',
								'content'    => '<p>Crawling, indexing, and ranking algorithms explained.</p>',
								'minutes'    => 20,
								'is_preview' => 1,
							),
							array(
								'title'   => 'Keyword Research',
								'type'    => 'text',
								'content' => '<p>Find the right keywords with tools like Ahrefs and SEMrush.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'On-Page SEO',
								'type'    => 'text',
								'content' => '<p>Optimizing titles, meta descriptions, headings, and content.</p>',
								'minutes' => 25,
							),
							array(
								'title'   => 'Link Building Strategies',
								'type'    => 'text',
								'content' => '<p>Ethical link building tactics that actually work in 2025.</p>',
								'minutes' => 20,
							),
						),
					),
					array(
						'title'       => 'Social Media & Content Marketing',
						'description' => 'Build an audience and drive engagement.',
						'lessons'     => array(
							array(
								'title'   => 'Platform Strategy',
								'type'    => 'text',
								'content' => '<p>Choosing the right platforms for your target audience.</p>',
								'minutes' => 20,
							),
							array(
								'title'   => 'Content Calendar Planning',
								'type'    => 'text',
								'content' => '<p>Plan and schedule content for maximum reach and engagement.</p>',
								'minutes' => 25,
							),
							array(
								'title'     => 'Video Marketing Essentials',
								'type'      => 'video',
								'minutes'   => 15,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
							array(
								'title'   => 'Email Marketing Automation',
								'type'    => 'text',
								'content' => '<p>Build email funnels that convert leads into customers.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'Marketing Analytics Quiz',
								'type'    => 'quiz',
								'minutes' => 10,
								'quiz'    => array(
									'title'         => 'Digital Marketing Quiz',
									'time_limit'    => 10,
									'passing_score' => 70,
									'questions'     => array(
										array(
											'text'    => 'What does SEO stand for?',
											'type'    => 'single_choice',
											'options' => array( 'Search Engine Optimization', 'Social Engagement Online', 'Site Enhancement Operations', 'Search and Explore Online' ),
											'correct' => 'Search Engine Optimization',
										),
										array(
											'text'    => 'Bounce rate measures how many visitors leave after one page.',
											'type'    => 'true_false',
											'correct' => 'True',
										),
										array(
											'text'    => 'Which is the most important on-page SEO element?',
											'type'    => 'single_choice',
											'options' => array( 'Title tag', 'Image alt text', 'Meta keywords', 'Page color' ),
											'correct' => 'Title tag',
										),
									),
								),
							),
						),
					),
				),
			),

			// ── Course 6 ──────────────────────────────────────.
			array(
				'title'           => 'Demo: DevOps with Docker & AWS',
				'subtitle'        => 'Containers, CI/CD pipelines, and cloud deployment',
				'description'     => '<p>Learn modern DevOps practices with Docker, Kubernetes basics, and AWS cloud services.</p><p>Automate your deployment pipeline and scale applications with confidence.</p>',
				'what_you_learn'  => '<ul><li>Containerize applications with Docker</li><li>Set up CI/CD pipelines with GitHub Actions</li><li>Deploy to AWS EC2, ECS, and Lambda</li><li>Monitor and log production systems</li></ul>',
				'level'           => 'intermediate',
				'estimated_hours' => 30,
				'category'        => 'DevOps',
				'price'           => 44.99,
				'is_featured'     => 0,
				'skills'          => array( 'Docker', 'AWS', 'Git', 'Agile' ),
				'sections'        => array(
					array(
						'title'       => 'Docker Fundamentals',
						'description' => 'Package and ship applications consistently.',
						'lessons'     => array(
							array(
								'title'      => 'What is Docker?',
								'type'       => 'text',
								'content'    => '<p>Understanding containers, images, and the Docker ecosystem.</p>',
								'minutes'    => 15,
								'is_preview' => 1,
							),
							array(
								'title'   => 'Writing Dockerfiles',
								'type'    => 'text',
								'content' => '<p>Best practices for creating efficient Docker images.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'Docker Compose',
								'type'    => 'text',
								'content' => '<p>Multi-container applications with docker-compose.yml.</p>',
								'minutes' => 25,
							),
							array(
								'title'     => 'Docker Networking & Volumes',
								'type'      => 'video',
								'minutes'   => 20,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
						),
					),
					array(
						'title'       => 'AWS Cloud Deployment',
						'description' => 'Deploy and scale in the cloud.',
						'lessons'     => array(
							array(
								'title'   => 'AWS Overview & Setup',
								'type'    => 'text',
								'content' => '<p>Setting up an AWS account and navigating the console.</p>',
								'minutes' => 20,
							),
							array(
								'title'   => 'EC2 & Security Groups',
								'type'    => 'text',
								'content' => '<p>Launch and configure virtual servers on AWS.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'CI/CD with GitHub Actions',
								'type'    => 'text',
								'content' => '<p>Automate testing and deployment with GitHub Actions workflows.</p>',
								'minutes' => 35,
							),
							array(
								'title'   => 'Monitoring & Logging',
								'type'    => 'text',
								'content' => '<p>CloudWatch, centralized logging, and alerting setup.</p>',
								'minutes' => 25,
							),
						),
					),
				),
			),

			// ── Course 7 ──────────────────────────────────────.
			array(
				'title'           => 'Demo: Machine Learning A-Z',
				'subtitle'        => 'Algorithms, neural networks, and real-world ML projects',
				'description'     => '<p>A complete machine learning course covering supervised learning, unsupervised learning, and deep learning fundamentals.</p><p>Build real ML projects with scikit-learn, TensorFlow, and real datasets.</p>',
				'what_you_learn'  => '<ul><li>Understand and implement core ML algorithms</li><li>Build models with scikit-learn and TensorFlow</li><li>Evaluate model performance properly</li><li>Deploy ML models to production</li><li>Handle real-world messy datasets</li></ul>',
				'level'           => 'advanced',
				'estimated_hours' => 50,
				'category'        => 'Data Science',
				'price'           => 79.99,
				'is_featured'     => 1,
				'skills'          => array( 'Python', 'Machine Learning', 'NumPy', 'Pandas', 'Data Analysis' ),
				'sections'        => array(
					array(
						'title'       => 'Supervised Learning',
						'description' => 'Predict outcomes with labeled data.',
						'lessons'     => array(
							array(
								'title'   => 'Linear Regression',
								'type'    => 'text',
								'content' => '<p>Fitting lines to data, cost functions, and gradient descent.</p>',
								'minutes' => 35,
							),
							array(
								'title'   => 'Logistic Regression',
								'type'    => 'text',
								'content' => '<p>Binary and multiclass classification with logistic models.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'Decision Trees & Random Forests',
								'type'    => 'text',
								'content' => '<p>Tree-based models for classification and regression tasks.</p>',
								'minutes' => 40,
							),
							array(
								'title'     => 'Model Evaluation Metrics',
								'type'      => 'video',
								'minutes'   => 25,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
							array(
								'title'   => 'Supervised Learning Quiz',
								'type'    => 'quiz',
								'minutes' => 15,
								'quiz'    => array(
									'title'         => 'Supervised Learning Quiz',
									'time_limit'    => 15,
									'passing_score' => 70,
									'questions'     => array(
										array(
											'text'    => 'What is overfitting?',
											'type'    => 'single_choice',
											'options' => array( 'Model performs well on training but poorly on test data', 'Model performs well on all data', 'Model cannot learn patterns', 'Model is too simple' ),
											'correct' => 'Model performs well on training but poorly on test data',
										),
										array(
											'text'    => 'Random Forest is an ensemble method.',
											'type'    => 'true_false',
											'correct' => 'True',
										),
										array(
											'text'    => 'What does a confusion matrix show?',
											'type'    => 'single_choice',
											'options' => array( 'TP, TN, FP, FN counts', 'Model parameters', 'Training loss', 'Feature importance' ),
											'correct' => 'TP, TN, FP, FN counts',
										),
									),
								),
							),
						),
					),
					array(
						'title'       => 'Unsupervised Learning',
						'description' => 'Find hidden patterns in unlabeled data.',
						'lessons'     => array(
							array(
								'title'   => 'K-Means Clustering',
								'type'    => 'text',
								'content' => '<p>Partitioning data into clusters with the K-Means algorithm.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'PCA for Dimensionality Reduction',
								'type'    => 'text',
								'content' => '<p>Reduce features while preserving variance with PCA.</p>',
								'minutes' => 35,
							),
							array(
								'title'   => 'Anomaly Detection',
								'type'    => 'text',
								'content' => '<p>Identifying outliers in data using statistical methods.</p>',
								'minutes' => 25,
							),
						),
					),
					array(
						'title'       => 'Deep Learning Introduction',
						'description' => 'Neural networks and TensorFlow.',
						'lessons'     => array(
							array(
								'title'   => 'Neural Network Basics',
								'type'    => 'text',
								'content' => '<p>Perceptrons, activation functions, and forward propagation.</p>',
								'minutes' => 40,
							),
							array(
								'title'     => 'Building with TensorFlow',
								'type'      => 'video',
								'minutes'   => 30,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
							array(
								'title'   => 'Training & Tuning Networks',
								'type'    => 'text',
								'content' => '<p>Backpropagation, learning rates, and regularization techniques.</p>',
								'minutes' => 35,
							),
							array(
								'title'   => 'Deep Learning Quiz',
								'type'    => 'quiz',
								'minutes' => 15,
								'quiz'    => array(
									'title'         => 'Deep Learning Quiz',
									'time_limit'    => 15,
									'passing_score' => 70,
									'questions'     => array(
										array(
											'text'    => 'What is backpropagation?',
											'type'    => 'single_choice',
											'options' => array( 'Algorithm to compute gradients for weight updates', 'Data preprocessing method', 'Activation function', 'Loss function' ),
											'correct' => 'Algorithm to compute gradients for weight updates',
										),
										array(
											'text'    => 'ReLU stands for Rectified Linear Unit.',
											'type'    => 'true_false',
											'correct' => 'True',
										),
										array(
											'text'        => 'What prevents overfitting in neural networks?',
											'type'        => 'fill_blank',
											'correct'     => 'Dropout',
											'explanation' => 'Dropout randomly deactivates neurons during training.',
										),
									),
								),
							),
						),
					),
				),
			),

			// ── Course 8 ──────────────────────────────────────.
			array(
				'title'           => 'Demo: Business Strategy Fundamentals',
				'subtitle'        => 'Business models, competitive analysis, and strategic planning',
				'description'     => '<p>Learn the core concepts of business strategy that every entrepreneur and manager needs.</p><p>From business model canvas to competitive analysis, build your strategic thinking toolkit.</p>',
				'what_you_learn'  => '<ul><li>Analyze industry competitive forces</li><li>Create and evaluate business models</li><li>Develop strategic growth plans</li><li>Understand financial metrics for business</li></ul>',
				'level'           => 'beginner',
				'estimated_hours' => 15,
				'category'        => 'Business',
				'price'           => 0,
				'is_featured'     => 0,
				'skills'          => array( 'Agile' ),
				'sections'        => array(
					array(
						'title'       => 'Strategy Foundations',
						'description' => 'Core frameworks for strategic thinking.',
						'lessons'     => array(
							array(
								'title'      => 'What is Strategy?',
								'type'       => 'text',
								'content'    => '<p>Understanding the difference between strategy and tactics.</p>',
								'minutes'    => 15,
								'is_preview' => 1,
							),
							array(
								'title'   => 'Porter\'s Five Forces',
								'type'    => 'text',
								'content' => '<p>Analyze industry competition with Porter\'s classic framework.</p>',
								'minutes' => 20,
							),
							array(
								'title'   => 'SWOT Analysis',
								'type'    => 'text',
								'content' => '<p>Identifying strengths, weaknesses, opportunities, and threats.</p>',
								'minutes' => 15,
							),
						),
					),
					array(
						'title'       => 'Business Models & Growth',
						'description' => 'Design models that scale.',
						'lessons'     => array(
							array(
								'title'   => 'Business Model Canvas',
								'type'    => 'text',
								'content' => '<p>Map out your entire business on a single page canvas.</p>',
								'minutes' => 25,
							),
							array(
								'title'   => 'Revenue Model Strategies',
								'type'    => 'text',
								'content' => '<p>Subscription, freemium, marketplace, and other revenue models.</p>',
								'minutes' => 20,
							),
							array(
								'title'   => 'Growth Frameworks',
								'type'    => 'text',
								'content' => '<p>Growth hacking, product-market fit, and scaling strategies.</p>',
								'minutes' => 20,
							),
						),
					),
				),
			),

			// ── Course 9 ──────────────────────────────────────.
			array(
				'title'           => 'Demo: Python Django Web Framework',
				'subtitle'        => 'Build production-ready web apps with Django',
				'description'     => '<p>Learn Django, the most popular Python web framework, from project setup to deployment.</p><p>Build a complete web application with authentication, REST API, and database modeling.</p>',
				'what_you_learn'  => '<ul><li>Build web applications with Django</li><li>Create REST APIs with Django REST Framework</li><li>Design efficient database models</li><li>Implement authentication and authorization</li><li>Deploy Django apps to production</li></ul>',
				'level'           => 'intermediate',
				'estimated_hours' => 22,
				'category'        => 'Python',
				'price'           => 34.99,
				'is_featured'     => 0,
				'skills'          => array( 'Python', 'Django', 'REST API', 'SQL' ),
				'sections'        => array(
					array(
						'title'       => 'Django Basics',
						'description' => 'Set up your Django project and understand the MVC pattern.',
						'lessons'     => array(
							array(
								'title'      => 'Django Project Setup',
								'type'       => 'text',
								'content'    => '<p>Create a new Django project and understand the project structure.</p>',
								'minutes'    => 15,
								'is_preview' => 1,
							),
							array(
								'title'   => 'Models & Migrations',
								'type'    => 'text',
								'content' => '<p>Define database models and run migrations with Django ORM.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'Views & URL Routing',
								'type'    => 'text',
								'content' => '<p>Function-based and class-based views with URL configuration.</p>',
								'minutes' => 25,
							),
							array(
								'title'     => 'Templates & Forms',
								'type'      => 'video',
								'minutes'   => 20,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
						),
					),
					array(
						'title'       => 'Advanced Django',
						'description' => 'APIs, authentication, and deployment.',
						'lessons'     => array(
							array(
								'title'   => 'Django REST Framework',
								'type'    => 'text',
								'content' => '<p>Build REST APIs with serializers, viewsets, and routers.</p>',
								'minutes' => 35,
							),
							array(
								'title'   => 'Authentication & Permissions',
								'type'    => 'text',
								'content' => '<p>Token-based auth, JWT, and custom permission classes.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'Testing & Deployment',
								'type'    => 'text',
								'content' => '<p>Write tests with Django TestCase and deploy to production.</p>',
								'minutes' => 25,
							),
						),
					),
				),
			),

			// ── Course 10 ─────────────────────────────────────.
			array(
				'title'           => 'Demo: Photography Masterclass',
				'subtitle'        => 'Composition, lighting, and post-processing techniques',
				'description'     => '<p>Master the art and science of photography from camera settings to advanced editing.</p><p>Whether you shoot with a DSLR or a smartphone, this course will elevate your photography skills.</p>',
				'what_you_learn'  => '<ul><li>Master camera settings and exposure triangle</li><li>Apply composition rules for stunning photos</li><li>Work with natural and artificial lighting</li><li>Edit photos professionally in Lightroom</li><li>Build a photography portfolio</li></ul>',
				'level'           => 'all_levels',
				'estimated_hours' => 20,
				'category'        => 'Photography',
				'price'           => 24.99,
				'is_featured'     => 0,
				'skills'          => array( 'Photoshop', 'Video Editing' ),
				'sections'        => array(
					array(
						'title'       => 'Camera Fundamentals',
						'description' => 'Understand your camera and master exposure.',
						'lessons'     => array(
							array(
								'title'      => 'The Exposure Triangle',
								'type'       => 'text',
								'content'    => '<p>ISO, aperture, and shutter speed explained with examples.</p>',
								'minutes'    => 20,
								'is_preview' => 1,
							),
							array(
								'title'   => 'Composition Rules',
								'type'    => 'text',
								'content' => '<p>Rule of thirds, leading lines, framing, and symmetry.</p>',
								'minutes' => 25,
							),
							array(
								'title'     => 'Natural Lighting Techniques',
								'type'      => 'video',
								'minutes'   => 20,
								'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
							),
							array(
								'title'   => 'Portrait Photography',
								'type'    => 'text',
								'content' => '<p>Posing, depth of field, and flattering light for portraits.</p>',
								'minutes' => 25,
							),
						),
					),
					array(
						'title'       => 'Post-Processing',
						'description' => 'Edit your photos to professional quality.',
						'lessons'     => array(
							array(
								'title'   => 'Lightroom Essentials',
								'type'    => 'text',
								'content' => '<p>Import, organize, and batch edit your photos efficiently.</p>',
								'minutes' => 30,
							),
							array(
								'title'   => 'Color Grading & Tone',
								'type'    => 'text',
								'content' => '<p>Create stunning color grades with curves, HSL, and split toning.</p>',
								'minutes' => 35,
							),
							array(
								'title'   => 'Advanced Retouching',
								'type'    => 'text',
								'content' => '<p>Frequency separation, dodge & burn, and skin retouching.</p>',
								'minutes' => 25,
							),
						),
					),
				),
			),
		);
	}
}
