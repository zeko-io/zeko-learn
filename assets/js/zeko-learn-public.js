/**
 * Zeko Learn — Public JS
 */
(function($) {
	'use strict';

	var ZL = window.zekoLearnPublic || {};

	// ─── Tab Switching ────────────────────────────────────────
	$(document).on('click', '.zeko-tab-btn', function() {
		var $btn    = $(this);
		var tab     = $btn.data('tab');
		var $parent = $btn.closest('.zeko-course-tabs, .zeko-learn-form-tabs');

		$parent.find('.zeko-tab-btn').removeClass('active');
		$btn.addClass('active');

		$parent.find('.zeko-tab-panel').removeClass('active');
		$parent.find('#zeko-tab-' + tab).addClass('active');
	});

	// ─── Enroll Button ────────────────────────────────────────
	$(document).on('click', '.zeko-enroll-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var courseId = $btn.data('course-id');
		var isFree = $btn.text().indexOf('Enroll') !== -1;

		$btn.prop('disabled', true).text('...');

		$.post(ZL.ajaxUrl, {
			action: isFree ? 'zeko_learn_enroll' : 'zeko_learn_purchase',
			nonce:  ZL.nonce,
			course_id: courseId
		}, function(res) {
			if (res.success) {
				if (res.data.checkout_url) {
					window.location.href = res.data.checkout_url;
				} else if (res.data.course_url) {
					window.location.href = res.data.course_url;
				} else {
					$btn.text(ZL.strings.enrolled || 'Enrolled!').removeClass('zeko-enroll-btn').addClass('zeko-enrolled');
				}
			} else {
				alert(res.data.message || (ZL.strings.error || 'Error'));
				$btn.prop('disabled', false).text(isFree ? 'Enroll Now' : 'Buy Now');
			}
		});
	});

	// ─── Refund / Unenroll Button ────────────────────────────
	$(document).on('click', '.zeko-refund-course-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var courseId = $btn.data('course-id');

		if (!confirm(ZL.strings.confirmRefund || 'Refund this course and remove access?')) return;

		$btn.prop('disabled', true).text('...');

		$.post(ZL.ajaxUrl, {
			action:    'zeko_learn_refund_course',
			nonce:     ZL.nonce,
			course_id: courseId
		}, function(res) {
			if (res.success) {
				if (res.data.redirect) {
					window.location.href = res.data.redirect;
				} else {
					window.location.reload();
				}
			} else {
				alert(res.data.message || (ZL.strings.error || 'Error'));
				$btn.prop('disabled', false).text(ZL.strings.refundBtn || 'Refund & Unenroll');
			}
		});
	});

	// ─── Complete Lesson ──────────────────────────────────────
	$(document).on('click', '.zeko-complete-lesson-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var lessonId = $btn.data('lesson-id');
		var courseId = $btn.data('course-id');

		if (!confirm(ZL.strings.confirmMark || 'Mark this lesson as complete?')) return;

		$btn.prop('disabled', true);

		$.post(ZL.ajaxUrl, {
			action:    'zeko_learn_complete_lesson',
			nonce:     ZL.nonce,
			lesson_id: lessonId,
			course_id: courseId
		}, function(res) {
			if (res.success) {
				$btn.text(ZL.strings.completed || 'Completed!').addClass('zeko-completed');
				if (res.data.course_completed) {
					alert(ZL.strings.congrats || 'Congratulations! You completed the course!');
				}
			} else {
				alert(res.data.message || (ZL.strings.error || 'Error'));
				$btn.prop('disabled', false);
			}
		});
	});

	// ─── Quiz Submit ──────────────────────────────────────────
	$(document).on('submit', '.zeko-quiz-form', function(e) {
		e.preventDefault();
		var $form = $(this);
		var answers = {};
		var quizId  = $form.find('[name="quiz_id"]').val();
		var courseId = $form.find('[name="course_id"]').val();

		$form.find('[name^="answer_"]').each(function() {
			var qId = $(this).attr('name').replace('answer_', '');
			answers[qId] = $(this).val();
		});

		$.post(ZL.ajaxUrl, {
			action:   'zeko_learn_submit_quiz',
			nonce:    ZL.nonce,
			quiz_id:  quizId,
			course_id: courseId,
			answers:  answers,
			time_taken: 0
		}, function(res) {
			if (res.success) {
				var $results = $form.find('.zeko-quiz-results');
				$results.show();
				$results.find('.zeko-quiz-score').text(res.data.score + '%');
				if (res.data.is_passed) {
					$results.addClass('zeko-passed');
				} else {
					$results.addClass('zeko-failed');
				}
				$.each(res.data.results, function(i, r) {
					var $q = $form.find('[data-question-id="' + r.question_id + '"]');
					if (r.correct) {
						$q.addClass('zeko-correct');
					} else {
						$q.addClass('zeko-incorrect');
					}
				});
			}
		});
	});

	// ─── Review Submission ────────────────────────────────────
	$(document).on('click', '.zeko-star', function() {
		var rating = $(this).data('rating');
		$(this).closest('.zeko-rating-input').find('.zeko-star').removeClass('active');
		$(this).addClass('active').prevAll('.zeko-star').addClass('active');
		$(this).closest('form').find('[name="rating"]').val(rating);
	});

	$(document).on('submit', '.zeko-review-form', function(e) {
		e.preventDefault();
		var $form = $(this);

		$.post(ZL.ajaxUrl, {
			action:      'zeko_learn_submit_review',
			nonce:       ZL.nonce,
			course_id:   $form.find('[name="course_id"]').val(),
			rating:      $form.find('[name="rating"]').val(),
			review_text: $form.find('[name="review_text"]').val()
		}, function(res) {
			if (res.success) {
				alert(ZL.strings.reviewSubmitted || 'Review submitted!');
				$form.find('[name="review_text"]').val('');
			} else {
				alert(res.data.message || (ZL.strings.error || 'Error'));
			}
		});
	});

	// ─── Bookmark Toggle ──────────────────────────────────────
	$(document).on('click', '.zeko-bookmark-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);

		$.post(ZL.ajaxUrl, {
			action:    'zeko_learn_toggle_bookmark',
			nonce:     ZL.nonce,
			lesson_id: $btn.data('lesson-id'),
			course_id: $btn.data('course-id')
		}, function(res) {
			if (res.success) {
				$btn.toggleClass('zeko-bookmarked');
			}
		});
	});

	// ─── Wishlist Toggle ──────────────────────────────────────
	$(document).on('click', '.zeko-wishlist-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);

		$.post(ZL.ajaxUrl, {
			action:    'zeko_learn_toggle_wishlist',
			nonce:     ZL.nonce,
			course_id: $btn.data('course-id')
		}, function(res) {
			if (res.success) {
				$btn.toggleClass('zeko-wishlisted');
			}
		});
	});

	// ─── Quiz Engine (Lesson-based quiz interface) ─────────────
	var quizState = {
		questions: [],
		answers: {},
		currentQ: 0,
		timer: null,
		secondsLeft: 0
	};

	$(document).on('click', '#zeko-start-quiz', function() {
		var quizId = $(this).closest('.zeko-quiz-container').data('quiz-id');
		$.post(ZL.ajaxUrl, {
			action: 'zeko_learn_submit_quiz',
			nonce: ZL.nonce,
			quiz_id: quizId,
			course_id: $('.zeko-lesson-viewer').data('course-id'),
			start_only: 1
		}, function(res) {
			if (res.success && res.data.questions) {
				quizState.questions = res.data.questions;
				quizState.answers = {};
				quizState.currentQ = 0;
				$('#zeko-quiz-instructions').hide();
				$('#zeko-quiz-active').show();
				if (res.data.time_limit_minutes) {
					quizState.secondsLeft = res.data.time_limit_minutes * 60;
					startQuizTimer();
				}
				renderQuizQuestion();
			}
		});
	});

	function startQuizTimer() {
		updateTimerDisplay();
		quizState.timer = setInterval(function() {
			quizState.secondsLeft--;
			updateTimerDisplay();
			if (quizState.secondsLeft <= 0) {
				clearInterval(quizState.timer);
				submitQuiz();
			}
		}, 1000);
	}

	function updateTimerDisplay() {
		var m = Math.floor(quizState.secondsLeft / 60);
		var s = quizState.secondsLeft % 60;
		$('#zeko-quiz-timer').text(m + ':' + (s < 10 ? '0' : '') + s);
	}

	function renderQuizQuestion() {
		var q = quizState.questions[quizState.currentQ];
		var total = quizState.questions.length;
		var idx = quizState.currentQ + 1;

		$('#zeko-quiz-progress').text(idx + ' / ' + total);

		var html = '<div class="zeko-quiz-question" data-q-id="' + q.id + '">';
		html += '<h3>' + idx + '. ' + q.question_text + '</h3>';
		html += '<span class="zeko-q-points">' + q.points + ' pts</span>';

		var opts = q.options || [];
		if (typeof opts === 'string') {
			try { opts = JSON.parse(opts); } catch(e) { opts = opts.split('\n'); }
		}

		if (q.question_type === 'true_false') {
			html += renderRadioOptions(q.id, ['True', 'False']);
		} else if (q.question_type === 'single_choice') {
			html += renderRadioOptions(q.id, opts);
		} else if (q.question_type === 'multiple_choice') {
			html += renderCheckboxOptions(q.id, opts);
		} else if (q.question_type === 'fill_blank') {
			html += '<input type="text" class="zeko-fill-blank" data-q-id="' + q.id + '" value="' + (quizState.answers[q.id] || '') + '" placeholder="Type your answer...">';
		}

		html += '</div>';
		$('#zeko-quiz-questions').html(html);

		// Restore previous answer for radio
		if (quizState.answers[q.id]) {
			$('#zeko-quiz-questions').find('input[value="' + quizState.answers[q.id] + '"]').prop('checked', true);
		}

		// Nav buttons
		$('#zeko-quiz-prev').prop('disabled', quizState.currentQ === 0);
		if (quizState.currentQ === total - 1) {
			$('#zeko-quiz-next').hide();
			$('#zeko-quiz-submit').show();
		} else {
			$('#zeko-quiz-next').show();
			$('#zeko-quiz-submit').hide();
		}
	}

	function renderRadioOptions(qId, opts) {
		var html = '<div class="zeko-options">';
		for (var i = 0; i < opts.length; i++) {
			var val = typeof opts[i] === 'object' ? opts[i].text || opts[i].label || '' : opts[i];
			html += '<label class="zeko-option"><input type="radio" name="q_' + qId + '" value="' + val + '"> ' + val + '</label>';
		}
		html += '</div>';
		return html;
	}

	function renderCheckboxOptions(qId, opts) {
		var html = '<div class="zeko-options">';
		var prev = quizState.answers[qId] ? quizState.answers[qId].split(',') : [];
		for (var i = 0; i < opts.length; i++) {
			var val = typeof opts[i] === 'object' ? opts[i].text || opts[i].label || '' : opts[i];
			var checked = prev.indexOf(val) !== -1 ? ' checked' : '';
			html += '<label class="zeko-option"><input type="checkbox" name="q_' + qId + '" value="' + val + '"' + checked + '> ' + val + '</label>';
		}
		html += '</div>';
		return html;
	}

	function saveCurrentAnswer() {
		var q = quizState.questions[quizState.currentQ];
		if (q.question_type === 'multiple_choice') {
			var vals = [];
			$('#zeko-quiz-questions input[name="q_' + q.id + '"]:checked').each(function() { vals.push($(this).val()); });
			quizState.answers[q.id] = vals.join(',');
		} else if (q.question_type === 'fill_blank') {
			quizState.answers[q.id] = $('#zeko-quiz-questions .zeko-fill-blank').val();
		} else {
			quizState.answers[q.id] = $('#zeko-quiz-questions input[name="q_' + q.id + '"]:checked').val() || '';
		}
	}

	$(document).on('change', '#zeko-quiz-questions input, #zeko-quiz-questions .zeko-fill-blank', function() {
		saveCurrentAnswer();
	});

	$(document).on('click', '#zeko-quiz-next', function() {
		saveCurrentAnswer();
		if (quizState.currentQ < quizState.questions.length - 1) {
			quizState.currentQ++;
			renderQuizQuestion();
		}
	});

	$(document).on('click', '#zeko-quiz-prev', function() {
		saveCurrentAnswer();
		if (quizState.currentQ > 0) {
			quizState.currentQ--;
			renderQuizQuestion();
		}
	});

	$(document).on('click', '#zeko-quiz-submit', function() {
		saveCurrentAnswer();
		submitQuiz();
	});

	function submitQuiz() {
		if (quizState.timer) clearInterval(quizState.timer);

		var quizContainer = $('.zeko-quiz-container');
		var quizId = quizContainer.data('quiz-id');
		var timeTaken = quizState.secondsLeft > 0 ? (quizState.questions.length * 60 - quizState.secondsLeft) : 0;

		$.post(ZL.ajaxUrl, {
			action: 'zeko_learn_submit_quiz',
			nonce: ZL.nonce,
			quiz_id: quizId,
			course_id: $('.zeko-lesson-viewer').data('course-id'),
			answers: quizState.answers,
			time_taken: timeTaken
		}, function(res) {
			$('#zeko-quiz-active').hide();
			var $results = $('#zeko-quiz-results').show();

			if (res.success) {
				var passed = res.data.is_passed;
				var html = '<div class="zeko-results-header ' + (passed ? 'passed' : 'failed') + '">';
				html += '<h2>' + (passed ? 'Congratulations! You passed!' : 'Keep practicing!') + '</h2>';
				html += '<div class="zeko-score">' + res.data.score + '%</div>';
				html += '<p>' + (passed ? 'Passed' : 'Not passed') + ' (need ' + res.data.passing_score + '%)</p>';
				html += '</div>';
				html += '<div class="zeko-results-breakdown">';
				html += '<p>Correct: ' + res.data.correct_count + ' / ' + res.data.total_questions + '</p>';
				html += '<p>Points: ' + res.data.earned_points + ' / ' + res.data.total_points + '</p>';
				html += '</div>';
				$results.html(html);
			} else {
				$results.html('<p>Error: ' + (res.data.message || 'Unknown error') + '</p>');
			}
		});
	}

	// ─── Assignment Submission ────────────────────────────────
	$(document).on('submit', '#zeko-assignment-form', function(e) {
		e.preventDefault();
		var $form = $(this);
		var formData = new FormData($form[0]);
		formData.append('action', 'zeko_learn_submit_assignment');
		formData.append('nonce', ZL.nonce);

		$.ajax({
			url: ZL.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(res) {
				if (res.success) {
					alert(ZL.strings.assignmentSubmitted || 'Assignment submitted successfully!');
					location.reload();
				} else {
					alert(res.data.message || 'Error submitting assignment');
				}
			}
		});
	});

	// File dropzone
	if ($('#zeko-dropzone').length) {
		var $dropzone = $('#zeko-dropzone');
		var $fileInput = $('#zeko-file-input');

		$dropzone.on('click', function() { $fileInput.click(); });
		$dropzone.on('dragover', function(e) { e.preventDefault(); $(this).addClass('dragover'); });
		$dropzone.on('dragleave', function() { $(this).removeClass('dragover'); });
		$dropzone.on('drop', function(e) {
			e.preventDefault();
			$(this).removeClass('dragover');
			var files = e.originalEvent.dataTransfer.files;
			if (files.length) {
				$fileInput[0].files = files;
				$('#zeko-file-name').text(files[0].name);
			}
		});
		$fileInput.on('change', function() {
			if (this.files.length) {
				$('#zeko-file-name').text(this.files[0].name);
			}
		});
	}

	// ─── Notes ───────────────────────────────────────────────
	$(document).on('submit', '#zeko-note-form', function(e) {
		e.preventDefault();
		var $form = $(this);

		$.post(ZL.ajaxUrl, {
			action: 'zeko_learn_save_note',
			nonce: ZL.nonce,
			lesson_id: $form.find('[name="lesson_id"]').val(),
			course_id: $form.find('[name="course_id"]').val(),
			timestamp_seconds: $form.find('[name="timestamp_seconds"]').val(),
			content: $form.find('[name="content"]').val()
		}, function(res) {
			if (res.success) {
				$form.find('textarea').val('');
				$('#zeko-notes-list').append(
					'<div class="zeko-note" data-note-id="' + res.data.note_id + '">' +
					'<span class="zeko-note-timestamp">' + $('#zeko-note-timestamp').val() + 's</span>' +
					'<p>' + $form.find('textarea').val() + '</p>' +
					'</div>'
				);
			}
		});
	});

	$(document).on('click', '.zeko-delete-note', function() {
		var noteId = $(this).data('note-id');
		$.post(ZL.ajaxUrl, {
			action: 'zeko_learn_delete_note',
			nonce: ZL.nonce,
			note_id: noteId
		}, function(res) {
			if (res.success) {
				$('.zeko-note[data-note-id="' + noteId + '"]').remove();
			}
		});
	});

	// ─── Discussion Form ──────────────────────────────────────
	$(document).on('submit', '.zeko-discussion-form', function(e) {
		e.preventDefault();
		var $form = $(this);

		$.post(ZL.ajaxUrl, {
			action:    'zeko_learn_create_discussion',
			nonce:     ZL.nonce,
			course_id: $form.find('[name="course_id"]').val(),
			title:     $form.find('[name="title"]').val(),
			content:   $form.find('[name="content"]').val(),
			lesson_id: $form.find('[name="lesson_id"]').val() || 0
		}, function(res) {
			if (res.success) {
				$form.find('[name="title"]').val('');
				$form.find('[name="content"]').val('');
				alert(res.data.message);
			} else {
				alert(res.data.message || (ZL.strings.error || 'Error'));
			}
		});
	});

	// ─── Discussion Reply ─────────────────────────────────────
	$(document).on('submit', '.zeko-discussion-reply-form', function(e) {
		e.preventDefault();
		var $form = $(this);
		var discussionId = $form.data('discussion-id');

		$.post(ZL.ajaxUrl, {
			action:        'zeko_learn_reply_discussion',
			nonce:         ZL.nonce,
			parent_id:     discussionId,
			content:       $form.find('[name="content"]').val()
		}, function(res) {
			if (res.success) {
				$form.find('[name="content"]').val('');
				var $replies = $form.closest('.zeko-discussion-item').find('.zeko-discussion-replies');
				$replies.append(
					'<div class="zeko-discussion-reply">' +
					'<p>' + $form.find('[name="content"]').val() + '</p>' +
					'<small>' + (res.data.author || 'You') + '</small>' +
					'</div>'
				);
			} else {
				alert(res.data.message || (ZL.strings.error || 'Error'));
			}
		});
	});

	// ─── Discussion Vote ──────────────────────────────────────
	$(document).on('click', '.zeko-vote-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var discussionId = $btn.data('discussion-id');
		var voteType = $btn.data('vote-type');
		var courseId = $btn.data('course-id');

		$.post(ZL.ajaxUrl, {
			action:        'zeko_learn_vote_discussion',
			nonce:         ZL.nonce,
			discussion_id: discussionId,
			vote_type:     voteType,
			course_id:     courseId
		}, function(res) {
			if (res.success && res.data.upvotes !== undefined) {
				$btn.closest('.zeko-discussion-votes').find('.zeko-vote-count').text(res.data.upvotes);
			}
		});
	});

	// ─── Best Answer (Instructor) ─────────────────────────────
	$(document).on('click', '.zeko-mark-best-answer', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var discussionId = $btn.data('discussion-id');
		var courseId = $btn.data('course-id');

		$.post(ZL.ajaxUrl, {
			action:        'zeko_learn_best_answer',
			nonce:         ZL.nonce,
			discussion_id: discussionId,
			course_id:     courseId
		}, function(res) {
			if (res.success) {
				$('.zeko-best-answer-badge').remove();
				$btn.before('<span class="zeko-best-answer-badge">Best Answer</span>');
			}
		});
	});

	// ─── Instructor Reply to Review ───────────────────────────
	$(document).on('submit', '.zeko-review-reply-form', function(e) {
		e.preventDefault();
		var $form = $(this);
		var reviewId = $form.data('review-id');

		$.post(ZL.ajaxUrl, {
			action:    'zeko_learn_reply_review',
			nonce:     ZL.nonce,
			review_id: reviewId,
			reply:     $form.find('[name="reply"]').val()
		}, function(res) {
			if (res.success) {
				$form.find('[name="reply"]').val('');
				var $review = $form.closest('.zeko-review-item');
				$review.find('.zeko-instructor-reply').remove();
				$review.append(
					'<div class="zeko-instructor-reply">' +
					'<strong>Instructor reply:</strong> ' +
					'<p>' + $form.find('[name="reply"]').val() + '</p>' +
					'</div>'
				);
			} else {
				alert(res.data.message || (ZL.strings.error || 'Error'));
			}
		});
	});

	// ─── Announcements ────────────────────────────────────────
	$(document).on('submit', '.zeko-announcement-form', function(e) {
		e.preventDefault();
		var $form = $(this);

		$.post(ZL.ajaxUrl, {
			action:    'zeko_learn_pin_announcement',
			nonce:     ZL.nonce,
			course_id: $form.find('[name="course_id"]').val(),
			title:     $form.find('[name="title"]').val(),
			content:   $form.find('[name="content"]').val()
		}, function(res) {
			if (res.success) {
				$form.find('[name="title"]').val('');
				$form.find('[name="content"]').val('');
				var $list = $('.zeko-announcements-list');
				if ($list.length) {
					$list.prepend(
						'<div class="zeko-announcement-item">' +
						'<h4>' + res.data.title + '</h4>' +
						'<p>' + res.data.content + '</p>' +
						'<small>' + (res.data.date || 'Just now') + '</small>' +
						'</div>'
					);
				}
				alert(res.data.message);
			} else {
				alert(res.data.message || (ZL.strings.error || 'Error'));
			}
		});
	});

	// ─── Learning Goals ───────────────────────────────────────
	$(document).on('submit', '.zeko-goal-form', function(e) {
		e.preventDefault();
		var $form = $(this);

		$.post(ZL.ajaxUrl, {
			action:       'zeko_learn_set_goal',
			nonce:        ZL.nonce,
			course_id:    $form.find('[name="course_id"]').val(),
			goal_text:    $form.find('[name="goal_text"]').val(),
			target_date:  $form.find('[name="target_date"]').val()
		}, function(res) {
			if (res.success) {
				$form.find('[name="goal_text"]').val('');
				$form.find('[name="target_date"]').val('');
				var $list = $('.zeko-goals-list');
				if ($list.length) {
					$list.append(
						'<div class="zeko-goal-item" data-goal-id="' + res.data.goal_id + '">' +
						'<span class="zeko-goal-text">' + $form.find('[name="goal_text"]').val() + '</span>' +
						'<button type="button" class="button zeko-complete-goal">Complete</button>' +
						'<button type="button" class="button zeko-abandon-goal">Abandon</button>' +
						'</div>'
					);
				}
				alert(res.data.message);
			} else {
				alert(res.data.message || (ZL.strings.error || 'Error'));
			}
		});
	});

	$(document).on('click', '.zeko-complete-goal', function(e) {
		e.preventDefault();
		var $item = $(this).closest('.zeko-goal-item');
		var goalId = $item.data('goal-id');
		$.post(ZL.ajaxUrl, {
			action:  'zeko_learn_update_goal',
			nonce:   ZL.nonce,
			goal_id: goalId,
			status:  'completed'
		}, function(res) {
			if (res.success) {
				$item.addClass('zeko-goal-completed').find('button').remove();
				$item.append('<span class="zeko-goal-status">Completed</span>');
			}
		});
	});

	$(document).on('click', '.zeko-abandon-goal', function(e) {
		e.preventDefault();
		var $item = $(this).closest('.zeko-goal-item');
		var goalId = $item.data('goal-id');
		$.post(ZL.ajaxUrl, {
			action:  'zeko_learn_update_goal',
			nonce:   ZL.nonce,
			goal_id: goalId,
			status:  'abandoned'
		}, function(res) {
			if (res.success) {
				$item.addClass('zeko-goal-abandoned').find('button').remove();
				$item.append('<span class="zeko-goal-status">Abandoned</span>');
			}
		});
	});

	// ─── Load Catalog via AJAX (if needed) ────────────────────
	$(document).ready(function() {
		var $catalog = $('#zeko-learn-catalog');
		if ($catalog.length) {
			loadCatalog($catalog);
		}
	});

	function loadCatalog($el) {
		var category = $el.data('category') || '';
		var level    = $el.data('level') || '';
		var perPage  = $el.data('per-page') || 12;

		$.get(ZL.ajaxUrl.replace('admin-ajax.php', 'wp-json/zeko-learn/v1/courses'), {
			status:      'published',
			category_id: category,
			level:       level,
			per_page:    perPage
		}, function(courses) {
			if (!courses || !courses.length) {
				$el.html('<p>No courses found.</p>');
				return;
			}
			var html = '<div class="zeko-learn-featured-grid">';
			$.each(courses, function(i, c) {
				var thumb = c.thumbnail_url ? '<img src="' + c.thumbnail_url + '" alt="">' : '<div class="zeko-card-placeholder"></div>';
				var price = c.is_free ? '<span class="zeko-card-badge zeko-free">Free</span>' : '';
				var priceText = c.is_free ? '' : '<div class="zeko-card-price">' + (c.price || '') + '</div>';
				html += '<div class="zeko-course-card">';
				html += '<a href="' + (ZL.baseUrl || '/courses/') + c.slug + '/" class="zeko-card-thumb">' + thumb + price + '</a>';
				html += '<div class="zeko-card-body">';
				html += '<h3><a href="' + (ZL.baseUrl || '/courses/') + c.slug + '/">' + c.title + '</a></h3>';
				html += '<div class="zeko-card-meta">';
				html += '<span class="zeko-card-rating">' + (parseFloat(c.avg_rating) || 0).toFixed(1) + ' &#9733;</span>';
				html += '<span class="zeko-card-students">' + (c.enrollment_count || 0) + ' students</span>';
				html += '</div>';
				html += priceText;
				html += '</div></div>';
			});
			html += '</div>';
			$el.html(html);
		});
	}

	// ─── Instructor Dashboard: Modals ─────────────────────────
	$(document).on('click', '.zeko-inst-modal-close, .zeko-inst-modal-overlay', function() {
		$(this).closest('.zeko-inst-modal').hide();
	});

	// Grade Submission Modal
	$(document).on('click', '.zeko-grade-btn', function() {
		var $btn = $(this);
		$('#zeko-grade-student').text($btn.data('student'));
		$('#zeko-grade-assignment').text($btn.data('assignment'));
		$('#zeko-grade-input').val('');
		$('#zeko-grade-feedback').val('');
		$('#zeko-grade-modal').data('submission-id', $btn.data('submission-id')).show();
	});

	$(document).on('click', '#zeko-submit-grade', function() {
		var $btn = $(this);
		var submissionId = $('#zeko-grade-modal').data('submission-id');
		var grade = $('#zeko-grade-input').val().trim();
		var feedback = $('#zeko-grade-feedback').val().trim();

		if (!grade) {
			alert(ZL.strings ? ZL.strings.error : 'Please enter a grade.');
			return;
		}

		$btn.prop('disabled', true).text('...');
		$.post(ZL.ajax, {
			action: 'zeko_learn_grade_assignment',
			submission_id: submissionId,
			grade: grade,
			feedback: feedback,
			nonce: ZL.nonce
		}, function(res) {
			$btn.prop('disabled', false).text(ZL.strings ? ZL.strings.submit : 'Submit Grade');
			if (res.success) {
				$('#zeko-grade-modal').hide();
				location.reload();
			} else {
				alert(res.data && res.data.message ? res.data.message : (ZL.strings ? ZL.strings.error : 'Error'));
			}
		}).fail(function() {
			$btn.prop('disabled', false).text(ZL.strings ? ZL.strings.submit : 'Submit Grade');
			alert(ZL.strings ? ZL.strings.error : 'Error');
		});
	});

	// Post Announcement Modal
	$(document).on('click', '.zeko-post-announcement-btn', function() {
		var courseId = $(this).data('course-id');
		$('#zeko-ann-course').val(courseId);
		$('#zeko-ann-title').val('');
		$('#zeko-ann-content').val('');
		$('#zeko-announcement-modal').show();
	});

	$(document).on('click', '#zeko-submit-announcement', function() {
		var $btn = $(this);
		var courseId = parseInt($('#zeko-ann-course').val(), 10);
		var title = $('#zeko-ann-title').val().trim();
		var content = $('#zeko-ann-content').val().trim();

		if (!title || !content || !courseId) {
			alert(ZL.strings ? ZL.strings.error : 'Please fill in all fields.');
			return;
		}

		$btn.prop('disabled', true).text('...');
		$.post(ZL.ajax, {
			action: 'zeko_learn_post_announcement',
			course_id: courseId,
			title: title,
			content: content,
			nonce: ZL.nonce
		}, function(res) {
			$btn.prop('disabled', false).text(ZL.strings ? ZL.strings.submit : 'Publish');
			if (res.success) {
				$('#zeko-announcement-modal').hide();
				location.reload();
			} else {
				alert(res.data && res.data.message ? res.data.message : (ZL.strings ? ZL.strings.error : 'Error'));
			}
		}).fail(function() {
			$btn.prop('disabled', false).text(ZL.strings ? ZL.strings.submit : 'Publish');
			alert(ZL.strings ? ZL.strings.error : 'Error');
		});
	});

	// Delete Announcement
	$(document).on('click', '.zeko-delete-announcement', function() {
		if (!confirm(ZL.strings ? ZL.strings.confirmDelete : 'Are you sure?')) {
			return;
		}
		var $btn = $(this);
		var id = $btn.data('id');

		$btn.prop('disabled', true);
		$.post(ZL.ajax, {
			action: 'zeko_learn_delete_announcement',
			announcement_id: id,
			nonce: ZL.nonce
		}, function(res) {
			if (res.success) {
				$btn.closest('.zeko-inst-announcement-item').fadeOut(300, function() { $(this).remove(); });
			} else {
				$btn.prop('disabled', false);
				alert(res.data && res.data.message ? res.data.message : (ZL.strings ? ZL.strings.error : 'Error'));
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			alert(ZL.strings ? ZL.strings.error : 'Error');
		});
	});

	// Pin/Unpin Announcement
	$(document).on('click', '.zeko-pin-announcement', function() {
		var $btn = $(this);
		var id = $btn.data('id');
		var courseId = $btn.data('course-id');

		$btn.prop('disabled', true);
		$.post(ZL.ajax, {
			action: 'zeko_learn_pin_announcement',
			announcement_id: id,
			course_id: courseId,
			nonce: ZL.nonce
		}, function(res) {
			if (res.success) {
				location.reload();
			} else {
				$btn.prop('disabled', false);
				alert(res.data && res.data.message ? res.data.message : (ZL.strings ? ZL.strings.error : 'Error'));
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			alert(ZL.strings ? ZL.strings.error : 'Error');
		});
	});

	// Toggle Course Status (publish/unpublish)
	$(document).on('click', '.zeko-toggle-course-status', function() {
		var $btn = $(this);
		var courseId = $btn.data('course-id');
		var current = $btn.data('current-status');
		var newStatus = current === 'published' ? 'draft' : 'published';

		$btn.prop('disabled', true);
		$.post(ZL.ajax, {
			action: 'zeko_learn_toggle_course_status',
			course_id: courseId,
			status: newStatus,
			nonce: ZL.nonce
		}, function(res) {
			if (res.success) {
				location.reload();
			} else {
				$btn.prop('disabled', false);
				alert(res.data && res.data.message ? res.data.message : (ZL.strings ? ZL.strings.error : 'Error'));
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			alert(ZL.strings ? ZL.strings.error : 'Error');
		});
	});

	// Duplicate Course
	$(document).on('click', '.zeko-duplicate-course', function() {
		var $btn = $(this);
		var courseId = $btn.data('course-id');

		if (!confirm(ZL.strings ? ZL.strings.confirm : 'Are you sure?')) {
			return;
		}

		$btn.prop('disabled', true);
		$.post(ZL.ajax, {
			action: 'zeko_learn_duplicate_course',
			course_id: courseId,
			nonce: ZL.nonce
		}, function(res) {
			if (res.success) {
				location.reload();
			} else {
				$btn.prop('disabled', false);
				alert(res.data && res.data.message ? res.data.message : (ZL.strings ? ZL.strings.error : 'Error'));
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			alert(ZL.strings ? ZL.strings.error : 'Error');
		});
	});

	// ─── Frontend Course Creator ──────────────────────────────

	// ── Course Creator Sub-Tab Switching ──────────────────────
	$(document).on('click', '.zeko-cr-tab-btn', function() {
		var $btn  = $(this);
		var panel = $btn.data('cr-tab');
		var $wrap = $btn.closest('.zeko-inst-card');

		$wrap.find('.zeko-cr-tab-btn').removeClass('active');
		$btn.addClass('active');

		$wrap.find('.zeko-cr-tab-panel').removeClass('active');
		$wrap.find('.zeko-cr-tab-panel[data-cr-panel="' + panel + '"]').addClass('active');
	});

	// ── Rich Text Editor Toolbar ──────────────────────────────
	$(document).on('click', '.zeko-rich-toolbar button', function(e) {
		e.preventDefault();
		var $btn    = $(this);
		var cmd     = $btn.data('cmd');
		var targetId = $btn.closest('.zeko-rich-toolbar').data('target');
		var $editor  = $('#' + targetId);

		if (!$editor.length) return;

		$editor.focus();

		if (cmd === 'createLink') {
			var url = prompt('Enter URL:', 'https://');
			if (url) {
				document.execCommand('createLink', false, url);
			}
		} else {
			document.execCommand(cmd, false, null);
		}
	});

	// Sync contenteditable → hidden textarea on input
	$(document).on('input', '.zeko-rich-content', function() {
		var name = $(this).data('name');
		if (name) {
			var $hidden = $(this).closest('.zeko-rich-editor').find('.zeko-rich-hidden');
			$hidden.val($(this).html());
		}
	});

	// Sync on tab switch (before form submit)
	$(document).on('click', '.zeko-cr-tab-btn', function() {
		$('.zeko-rich-content').each(function() {
			var name = $(this).data('name');
			if (name) {
				$(this).closest('.zeko-rich-editor').find('.zeko-rich-hidden').val($(this).html());
			}
		});
	});

	// ── Thumbnail Upload via wp.media ─────────────────────────
	$(document).on('click', '#zeko-upload-thumb', function(e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
			alert('WordPress media library not available.');
			return;
		}
		var frame = wp.media({
			title: 'Choose Course Thumbnail',
			button: { text: 'Use This Image' },
			multiple: false,
			library: { type: 'image' }
		});
		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			$('#zl-course-thumbnail-id').val(attachment.id);
			$('#zeko-thumb-preview').html('<img src="' + attachment.url + '">');
			$('#zeko-thumb-preview .zeko-thumb-placeholder').remove();
			$('#zeko-remove-thumb').show();
		});
		frame.open();
	});

	$(document).on('click', '#zeko-remove-thumb', function(e) {
		e.preventDefault();
		$('#zl-course-thumbnail-id').val('');
		$('#zeko-thumb-preview').html('<div class="zeko-thumb-placeholder"><span class="dashicons dashicons-format-image"></span><p>No thumbnail set</p></div>');
		$(this).hide();
	});

	// ── Free Course Toggle → Show/Hide Pricing Fields ─────────
	$(document).on('change', '#zl-course-is-free', function() {
		if ($(this).is(':checked')) {
			$('#zeko-pricing-fields').slideUp(200);
		} else {
			$('#zeko-pricing-fields').slideDown(200);
		}
	});

	// ── Skill Autocomplete ────────────────────────────────────
	var skillSearchTimer = null;

	$(document).on('input', '#zeko-skill-search', function() {
		var query = $(this).val().trim();
		var $results = $('#zeko-skill-results');

		clearTimeout(skillSearchTimer);

		if (query.length < 2) {
			$results.hide().empty();
			return;
		}

		skillSearchTimer = setTimeout(function() {
			$.post(ZL.ajaxUrl, {
				action: 'zeko_learn_search_skills',
				nonce: ZL.nonce,
				q: query
			}, function(res) {
				if (res.success && res.data.skills && res.data.skills.length) {
					var currentIds = ($('#skill_ids').val() || '').split(',').filter(Boolean);
					var html = '';
					$.each(res.data.skills, function(i, skill) {
						if (currentIds.indexOf(String(skill.id)) === -1) {
							html += '<div class="zeko-skill-option" data-skill-id="' + skill.id + '" data-skill-name">' +
								'<span>' + skill.name + '</span>' +
								'<small>' + (skill.group_name || '') + '</small>' +
								'</div>';
						}
					});
					if (html) {
						$results.html(html).show();
					} else {
						$results.hide().empty();
					}
				} else {
					$results.hide().empty();
				}
			});
		}, 350);
	});

	$(document).on('click', '.zeko-skill-option', function() {
		var skillId   = $(this).data('skill-id');
		var skillName = $(this).find('span').text();
		var $container = $('#zeko-course-skills');
		var $hidden    = $('#skill_ids');
		var currentIds = ($hidden.val() || '').split(',').filter(Boolean);

		currentIds.push(String(skillId));
		$hidden.val(currentIds.join(','));

		$container.append(
			'<span class="zeko-skill-tag" data-skill-id="' + skillId + '">' +
			skillName +
			'<button type="button" class="zeko-remove-skill">&times;</button></span>'
		);

		$(this).remove();
		$('#zeko-skill-search').val('');
		$('#zeko-skill-results').hide().empty();
	});

	$(document).on('click', '.zeko-remove-skill', function() {
		var $tag = $(this).closest('.zeko-skill-tag');
		var skillId = String($tag.data('skill-id'));
		var currentIds = ($('#skill_ids').val() || '').split(',').filter(Boolean);
		currentIds = currentIds.filter(function(id) { return id !== skillId; });
		$('#skill_ids').val(currentIds.join(','));
		$tag.remove();
	});

	// Hide skill results when clicking outside
	$(document).on('click', function(e) {
		if (!$(e.target).closest('.zeko-skills-area').length) {
			$('#zeko-skill-results').hide();
		}
	});

	// ── Remove Section ────────────────────────────────────────
	$(document).on('click', '.zeko-remove-section', function() {
		if (!confirm('Delete this section and all its lessons?')) return;
		var $item = $(this).closest('.zeko-inst-section-item');
		var sectionId = $item.data('section-id');

		if (sectionId && sectionId !== 0) {
			$.post(ZL.ajax, {
				action: 'zeko_learn_save_section',
				nonce: ZL.nonce,
				course_id: $('#zeko-create-course-form').find('[name="course_id"]').val(),
				section_id: sectionId,
				title: '__DELETE__'
			}, function(res) {
				$item.slideUp(200, function() { $(this).remove(); });
			});
		} else {
			$item.slideUp(200, function() { $(this).remove(); });
		}
	});

	// ── Remove Lesson ─────────────────────────────────────────
	$(document).on('click', '.zeko-remove-lesson', function() {
		if (!confirm('Delete this lesson?')) return;
		var $item = $(this).closest('.zeko-inst-lesson-item');
		var lessonId = $item.data('lesson-id');

		if (lessonId && lessonId !== 0) {
			$.post(ZL.ajax, {
				action: 'zeko_learn_save_lesson',
				nonce: ZL.nonce,
				course_id: $('#zeko-create-course-form').find('[name="course_id"]').val(),
				section_id: $item.closest('.zeko-inst-section-item').data('section-id'),
				lesson_id: lessonId,
				title: '__DELETE__'
			}, function(res) {
				$item.slideUp(200, function() { $(this).remove(); });
			});
		} else {
			$item.slideUp(200, function() { $(this).remove(); });
		}
	});

	// ── Save Course Form (all 19+ fields) ─────────────────────
	$(document).on('submit', '#zeko-create-course-form', function(e) {
		e.preventDefault();
		var $form = $(this);
		var $btn  = $('#zeko-save-course-btn');
		var $status = $('#zeko-course-save-status');

		// Sync all rich text editors before submit
		$('.zeko-rich-content').each(function() {
			var name = $(this).data('name');
			if (name) {
				$(this).closest('.zeko-rich-editor').find('.zeko-rich-hidden').val($(this).html());
			}
		});

		$btn.prop('disabled', true);
		$status.hide();

		$.post(ZL.ajaxUrl, {
			action:            'zeko_learn_create_course',
			nonce:             ZL.nonce,
			course_id:         $form.find('[name="course_id"]').val(),
			title:             $form.find('[name="title"]').val(),
			subtitle:          $form.find('[name="subtitle"]').val(),
			description:       $form.find('[name="description"]').val(),
			category_id:       $form.find('[name="category_id"]').val(),
			level:             $form.find('[name="level"]').val(),
			language:          $form.find('[name="language"]').val(),
			estimated_hours:   $form.find('[name="estimated_hours"]').val(),
			status:            $form.find('[name="status"]').val(),
			is_featured:       $form.find('[name="is_featured"]').is(':checked') ? '1' : '0',
			is_free:           $form.find('[name="is_free"]').is(':checked') ? '1' : '0',
			price:             $form.find('[name="price"]').val(),
			sale_price:        $form.find('[name="sale_price"]').val(),
			thumbnail_id:      $form.find('[name="thumbnail_id"]').val(),
			promo_video_url:   $form.find('[name="promo_video_url"]').val(),
			seo_title:         $form.find('[name="seo_title"]').val(),
			seo_description:   $form.find('[name="seo_description"]').val(),
			what_you_learn:    $form.find('[name="what_you_learn"]').val(),
			requirements:      $form.find('[name="requirements"]').val(),
			target_audience:   $form.find('[name="target_audience"]').val(),
			skill_ids:         $form.find('[name="skill_ids"]').val()
		}, function(res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$status.text(res.data.message).addClass('is-visible');
				setTimeout(function() { $status.removeClass('is-visible'); }, 3000);
				if ($form.find('[name="course_id"]').val() === '' || $form.find('[name="course_id"]').val() === '0') {
					$form.find('[name="course_id"]').val(res.data.course_id);
					if (window.location.search.indexOf('course_id') === -1) {
						window.location.href = window.location.href + '&course_id=' + res.data.course_id;
					}
				}
			} else {
				alert(res.data && res.data.message ? res.data.message : 'Error');
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			alert('Network error.');
		});
	});

	// ── Add Section ───────────────────────────────────────────
	$(document).on('click', '#zeko-add-section-btn', function() {
		var courseId = $('#zeko-create-course-form').find('[name="course_id"]').val();
		if (!courseId || courseId === '0') {
			alert('Please save the course first.');
			return;
		}
		var html = '<div class="zeko-inst-section-item" data-section-id="0">';
		html += '<div class="zeko-inst-section-item-header">';
		html += '<span class="dashicons dashicons-move zeko-drag-handle"></span>';
		html += '<input type="text" class="zeko-section-title-input" value="" placeholder="Section title">';
		html += '<input type="text" class="zeko-section-desc-input" value="" placeholder="Section description (optional)">';
		html += '<div class="zeko-inst-section-actions">';
		html += '<button type="button" class="button button-small zeko-save-section-btn" title="Save"><span class="dashicons dashicons-saved"></span></button>';
		html += '<button type="button" class="button button-small zeko-move-up" title="Move up"><span class="dashicons dashicons-arrow-up"></span></button>';
		html += '<button type="button" class="button button-small zeko-move-down" title="Move down"><span class="dashicons dashicons-arrow-down"></span></button>';
		html += '<button type="button" class="button button-small button-link-delete zeko-remove-section" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
		html += '</div></div>';
		html += '<div class="zeko-inst-lessons-list"></div>';
		html += '<div class="zeko-inst-add-lesson-row">';
		html += '<button type="button" class="button button-small zeko-add-lesson-btn">';
		html += '<span class="dashicons dashicons-plus-alt2"></span> Add Lesson</button>';
		html += '</div></div>';
		$('#zeko-sections-list').append(html);
	});

	// ── Save Section (with description) ───────────────────────
	$(document).on('click', '.zeko-save-section-btn', function() {
		var $item = $(this).closest('.zeko-inst-section-item');
		var courseId = $('#zeko-create-course-form').find('[name="course_id"]').val();
		var sectionId = $item.data('section-id');
		var title = $item.find('.zeko-section-title-input').val();
		var description = $item.find('.zeko-section-desc-input').val() || '';

		if (!title) {
			alert('Section title is required.');
			return;
		}

		var $btn = $(this).prop('disabled', true);

		$.post(ZL.ajaxUrl, {
			action:      'zeko_learn_save_section',
			nonce:       ZL.nonce,
			course_id:   courseId,
			section_id:  sectionId,
			title:       title,
			description: description,
			sort_order:  $item.index()
		}, function(res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$item.data('section-id', res.data.section_id).attr('data-section-id', res.data.section_id);
			} else {
				alert(res.data && res.data.message ? res.data.message : 'Error');
			}
		}).fail(function() {
			$btn.prop('disabled', false);
		});
	});

	// ── Add Lesson (with minutes + preview toggle) ────────────
	$(document).on('click', '.zeko-add-lesson-btn', function() {
		var $section = $(this).closest('.zeko-inst-section-item');
		var sectionId = $section.data('section-id');
		if (!sectionId || sectionId === 0) {
			alert('Please save the section first.');
			return;
		}
		var html = '<div class="zeko-inst-lesson-item" data-lesson-id="0">';
		html += '<div class="zeko-inst-lesson-item-header">';
		html += '<span class="dashicons dashicons-move zeko-drag-handle"></span>';
		html += '<input type="text" class="zeko-lesson-title-input" value="" placeholder="Lesson title">';
		html += '<select class="zeko-lesson-type-select">';
		html += '<option value="text">Text</option>';
		html += '<option value="video">Video</option>';
		html += '<option value="url">URL</option>';
		html += '<option value="quiz">Quiz</option>';
		html += '<option value="assignment">Assignment</option>';
		html += '</select>';
		html += '<input type="number" class="zeko-lesson-minutes-input" value="" placeholder="Min" min="0" title="Estimated minutes">';
		html += '<label class="zeko-lesson-preview-toggle" title="Free preview">';
		html += '<input type="checkbox" class="zeko-is-preview-check">';
		html += '<span class="dashicons dashicons-visibility"></span>';
		html += '</label>';
		html += '<div class="zeko-inst-lesson-actions">';
		html += '<button type="button" class="button button-small zeko-save-lesson-btn" title="Save"><span class="dashicons dashicons-saved"></span></button>';
		html += '<button type="button" class="button button-small zeko-move-up" title="Move up"><span class="dashicons dashicons-arrow-up"></span></button>';
		html += '<button type="button" class="button button-small zeko-move-down" title="Move down"><span class="dashicons dashicons-arrow-down"></span></button>';
		html += '<button type="button" class="button button-small button-link-delete zeko-remove-lesson" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
		html += '</div></div></div>';
		$section.find('.zeko-inst-lessons-list').append(html);
	});

	// ── Save Lesson (with estimated_minutes + is_preview) ──────
	$(document).on('click', '.zeko-save-lesson-btn', function() {
		var $item = $(this).closest('.zeko-inst-lesson-item');
		var $section = $item.closest('.zeko-inst-section-item');
		var courseId = $('#zeko-create-course-form').find('[name="course_id"]').val();
		var sectionId = $section.data('section-id');
		var lessonId = $item.data('lesson-id');
		var title = $item.find('.zeko-lesson-title-input').val();
		var lessonType = $item.find('.zeko-lesson-type-select').val();
		var minutes = $item.find('.zeko-lesson-minutes-input').val() || '0';
		var isPreview = $item.find('.zeko-is-preview-check').is(':checked') ? '1' : '0';

		if (!title) {
			alert('Lesson title is required.');
			return;
		}

		var $btn = $(this).prop('disabled', true);

		$.post(ZL.ajaxUrl, {
			action:           'zeko_learn_save_lesson',
			nonce:            ZL.nonce,
			course_id:        courseId,
			section_id:       sectionId,
			lesson_id:        lessonId,
			title:            title,
			lesson_type:      lessonType,
			estimated_minutes: minutes,
			is_preview:       isPreview,
			sort_order:       $item.index()
		}, function(res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$item.data('lesson-id', res.data.lesson_id).attr('data-lesson-id', res.data.lesson_id);
			} else {
				alert(res.data && res.data.message ? res.data.message : 'Error');
			}
		}).fail(function() {
			$btn.prop('disabled', false);
		});
	});

	// ── Move up / down reorder ────────────────────────────────
	$(document).on('click', '.zeko-move-up', function() {
		var $item = $(this).closest('.zeko-inst-section-item, .zeko-inst-lesson-item');
		$item.prev().before($item);
	});

	$(document).on('click', '.zeko-move-down', function() {
		var $item = $(this).closest('.zeko-inst-section-item, .zeko-inst-lesson-item');
		$item.next().after($item);
	});

	// ─── Settings: Instructor ─────────────────────────────────
	$(document).on('submit', '#zeko-instructor-settings-form', function(e) {
		e.preventDefault();
		var $form = $(this);
		var $btn = $('#zeko-save-instructor-settings');
		var $status = $('#zeko-inst-settings-status');

		$btn.prop('disabled', true);
		$status.hide().removeClass('is-visible');

		$.post(ZL.ajax, {
			action:               'zeko_learn_save_instructor_settings',
			nonce:                ZL.nonce,
			display_name:         $form.find('[name="display_name"]').val(),
			bio:                  $form.find('[name="bio"]').val(),
			expertise:            $form.find('[name="expertise"]').val(),
			hourly_rate:          $form.find('[name="hourly_rate"]').val(),
			default_course_level: $form.find('[name="default_course_level"]').val(),
			teaching_style:       $form.find('[name="teaching_style"]').val(),
			payout_method:        $form.find('[name="payout_method"]').val(),
			payout_details:       $form.find('[name="payout_details"]').val(),
			website:              $form.find('[name="website"]').val(),
			linkedin:             $form.find('[name="linkedin"]').val(),
			twitter:              $form.find('[name="twitter"]').val(),
			youtube:              $form.find('[name="youtube"]').val(),
			github:               $form.find('[name="github"]').val(),
			notify_on_enrollment: $form.find('[name="notify_on_enrollment"]').is(':checked') ? '1' : '0',
			notify_on_review:     $form.find('[name="notify_on_review"]').is(':checked') ? '1' : '0',
			notify_on_question:   $form.find('[name="notify_on_question"]').is(':checked') ? '1' : '0',
			notify_on_completion: $form.find('[name="notify_on_completion"]').is(':checked') ? '1' : '0',
			weekly_digest:        $form.find('[name="weekly_digest"]').is(':checked') ? '1' : '0'
		}, function(res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$status.text(res.data.message).addClass('is-visible');
				setTimeout(function() { $status.removeClass('is-visible'); }, 3000);
			} else {
				alert(res.data && res.data.message ? res.data.message : 'Error');
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			alert('Network error.');
		});
	});

	// ─── Settings: Student ───────────────────────────────────
	$(document).on('submit', '#zeko-student-settings-form', function(e) {
		e.preventDefault();
		var $form = $(this);
		var $btn = $('#zeko-save-student-settings');
		var $status = $('#zeko-stu-settings-status');

		$btn.prop('disabled', true);
		$status.hide().removeClass('is-visible');

		$.post(ZL.ajax, {
			action:                  'zeko_learn_save_student_settings',
			nonce:                   ZL.nonce,
			display_name:            $form.find('[name="display_name"]').val(),
			bio:                     $form.find('[name="bio"]').val(),
			learning_goal:           $form.find('[name="learning_goal"]').val(),
			preferred_level:         $form.find('[name="preferred_level"]').val(),
			daily_goal_minutes:      $form.find('[name="daily_goal_minutes"]').val(),
			email_notifications:     $form.find('[name="email_notifications"]').is(':checked') ? '1' : '0',
			notify_on_completion:    $form.find('[name="notify_on_completion"]').is(':checked') ? '1' : '0',
			notify_on_announcement:  $form.find('[name="notify_on_announcement"]').is(':checked') ? '1' : '0',
			notify_on_reply:         $form.find('[name="notify_on_reply"]').is(':checked') ? '1' : '0',
			notify_weekly_summary:   $form.find('[name="notify_weekly_summary"]').is(':checked') ? '1' : '0',
			theme_preference:        $form.find('[name="theme_preference"]:checked').val() || 'system'
		}, function(res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$status.text(res.data.message).addClass('is-visible');
				setTimeout(function() { $status.removeClass('is-visible'); }, 3000);
			} else {
				alert(res.data && res.data.message ? res.data.message : 'Error');
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			alert('Network error.');
		});
	});

})(jQuery);
