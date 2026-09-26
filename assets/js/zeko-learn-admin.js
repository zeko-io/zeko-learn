/**
 * Zeko Learn — Admin JS
 */
(function($) {
	'use strict';

	var ZL = window.zekoLearnAdmin || {};
	var sectionIndex = 1;

	// ─── Tab Switching ────────────────────────────────────────
	$(document).on('click', '.zeko-tab-btn', function() {
		var $btn    = $(this);
		var tab     = $btn.data('tab');
		var $parent = $btn.closest('.zeko-learn-form-tabs');

		$parent.find('.zeko-tab-btn').removeClass('active');
		$btn.addClass('active');

		$parent.find('.zeko-learn-tab-panel').removeClass('active');
		$parent.find('#zeko-tab-' + tab).addClass('active');
	});

	// ─── Curriculum Builder ───────────────────────────────────
	$('#zeko-add-section').on('click', function() {
		var html = buildSection(sectionIndex);
		$('#zeko-curriculum-builder').append(html);
		sectionIndex++;
	});

	$(document).on('click', '.zeko-add-lesson', function() {
		var $section = $(this).closest('.zeko-section');
		var sIdx     = $section.data('section-index');
		var $lessons = $section.find('.zeko-lessons');
		var lIdx     = $lessons.children().length;

		$lessons.append(buildLesson(sIdx, lIdx));
	});

	$(document).on('click', '.zeko-remove-lesson', function() {
		$(this).closest('.zeko-lesson').remove();
	});

	$(document).on('click', '.zeko-remove-section', function() {
		if (confirm(ZL.strings.confirmDelete || 'Remove this section and all its lessons?')) {
			$(this).closest('.zeko-section').remove();
		}
	});

	function buildSection(idx) {
		return '<div class="zeko-section" data-section-index="' + idx + '">'
			+ '<div class="zeko-section-header">'
			+ '<span class="zeko-drag-handle">&#9776;</span>'
			+ '<input type="hidden" name="sections[' + idx + '][id]" value="0">'
			+ '<input type="text" name="sections[' + idx + '][title]" value="" class="regular-text" placeholder="Section Title">'
			+ '<input type="text" name="sections[' + idx + '][description]" value="" class="regular-text" placeholder="Description (optional)">'
			+ '<input type="hidden" name="sections[' + idx + '][sort_order]" value="' + idx + '">'
			+ '<button type="button" class="button zeko-remove-section">Remove</button>'
			+ '</div>'
			+ '<div class="zeko-lessons">' + buildLesson(idx, 0) + '</div>'
			+ '<button type="button" class="button zeko-add-lesson">+ Add Lesson</button>'
			+ '</div>';
	}

	function buildLesson(sIdx, lIdx) {
		return '<div class="zeko-lesson">'
			+ '<span class="zeko-drag-handle">&#9776;</span>'
			+ '<input type="hidden" name="sections[' + sIdx + '][lessons][' + lIdx + '][id]" value="0">'
			+ '<input type="text" name="sections[' + sIdx + '][lessons][' + lIdx + '][title]" value="" class="regular-text" placeholder="Lesson Title">'
			+ '<select name="sections[' + sIdx + '][lessons][' + lIdx + '][lesson_type]">'
			+ '<option value="text">Text</option>'
			+ '<option value="video">Video</option>'
			+ '<option value="quiz">Quiz</option>'
			+ '<option value="assignment">Assignment</option>'
			+ '</select>'
			+ '<input type="number" name="sections[' + sIdx + '][lessons][' + lIdx + '][estimated_minutes]" value="" min="0" class="small-text" placeholder="Min">'
			+ '<label><input type="checkbox" name="sections[' + sIdx + '][lessons][' + lIdx + '][is_preview]" value="1"> Preview</label>'
			+ '<button type="button" class="button zeko-remove-lesson">Remove</button>'
			+ '</div>';
	}

	// Sortable for curriculum builder is initialized at bottom of file

	// ─── Thumbnail Upload ─────────────────────────────────────
	$('#zeko-upload-thumbnail').on('click', function(e) {
		e.preventDefault();
		var frame = wp.media({
			title: 'Select Thumbnail',
			button: { text: 'Use this image' },
			multiple: false
		});
		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			$('#thumbnail_id').val(attachment.id);
			$('#zeko-thumbnail-preview').html('<img src="' + attachment.url + '" style="max-width:300px;">');
		});
		frame.open();
	});

	// ─── Course Delete ────────────────────────────────────────
	$(document).on('click', '.zeko-delete-course', function(e) {
		if (!confirm(ZL.strings.confirmDeleteCourse || 'Delete this course?')) {
			e.preventDefault();
		}
	});

	// ─── Category Delete ──────────────────────────────────────
	$(document).on('click', '.zeko-delete-category', function(e) {
		if (!confirm(ZL.strings.confirmDelete || 'Delete this category?')) {
			e.preventDefault();
		}
	});

	// ─── Skill Search ─────────────────────────────────────────
	var skillSearchTimeout;
	$('#zeko-skill-search').on('input', function() {
		var search = $(this).val().trim();
		if (search.length < 2) {
			$('#zeko-skill-suggestions').empty();
			return;
		}
		clearTimeout(skillSearchTimeout);
		skillSearchTimeout = setTimeout(function() {
			$.get(ZL.ajaxUrl.replace('admin-ajax.php', 'wp-json/zeko-learn/v1/skills'), { search: search }, function(skills) {
				var $suggestions = $('#zeko-skill-suggestions');
				$suggestions.empty();
				$.each(skills, function(i, skill) {
					$suggestions.append('<div class="zeko-skill-suggestion" data-skill-id="' + skill.id + '" data-skill-name="' + skill.name + '">' + skill.name + '</div>');
				});
			});
		}, 300);
	});

	$(document).on('click', '.zeko-skill-suggestion', function() {
		var id   = $(this).data('skill-id');
		var name = $(this).data('skill-name');
		var ids  = $('#skill_ids').val();
		var arr  = ids ? ids.split(',') : [];
		if (arr.indexOf(String(id)) === -1) {
			arr.push(id);
			$('#skill_ids').val(arr.join(','));
			$('#zeko-course-skills').append('<span class="zeko-skill-tag" data-skill-id="' + id + '">' + name + ' <button type="button" class="zeko-remove-skill">&times;</button></span>');
		}
		$(this).remove();
		$('#zeko-skill-search').val('');
	});

	$(document).on('click', '.zeko-remove-skill', function() {
		var id   = $(this).parent().data('skill-id');
		var ids  = $('#skill_ids').val();
		var arr  = ids ? ids.split(',') : [];
		arr = arr.filter(function(v) { return v !== String(id); });
		$('#skill_ids').val(arr.join(','));
		$(this).parent().remove();
	});

	// ─── Quiz Question Builder ────────────────────────────────
	var qIdx = $('#zeko-questions-builder .zeko-question').length;

	$('#zeko-add-question').on('click', function() {
		$('#zeko-questions-builder').append(buildQuestion(qIdx));
		qIdx++;
	});

	$(document).on('click', '.zeko-remove-question', function() {
		$(this).closest('.zeko-question').remove();
	});

	function buildQuestion(idx) {
		return '<div class="zeko-question" data-q-idx="' + idx + '">'
			+ '<div class="zeko-question-header">'
			+ '<span class="zeko-drag-handle">&#9776;</span>'
			+ '<input type="hidden" name="questions[' + idx + '][id]" value="0">'
			+ '<input type="text" name="questions[' + idx + '][question_text]" value="" class="regular-text" placeholder="Question text...">'
			+ '<select name="questions[' + idx + '][question_type]">'
			+ '<option value="single_choice">Single Choice</option>'
			+ '<option value="multiple_choice">Multiple Choice</option>'
			+ '<option value="true_false">True/False</option>'
			+ '<option value="fill_blank">Fill in Blank</option>'
			+ '</select>'
			+ '<input type="number" name="questions[' + idx + '][points]" value="1" min="1" class="small-text" placeholder="Points">'
			+ '<button type="button" class="button zeko-remove-question">Remove</button>'
			+ '</div>'
			+ '<div class="zeko-question-options">'
			+ '<label>Options (one per line, first = correct):</label>'
			+ '<textarea name="questions[' + idx + '][options]" rows="4" class="large-text"></textarea>'
			+ '</div>'
			+ '<div class="zeko-question-explanation">'
			+ '<label>Explanation (shown after quiz):</label>'
			+ '<textarea name="questions[' + idx + '][explanation]" rows="2" class="large-text"></textarea>'
			+ '</div>'
			+ '<div class="zeko-question-correct">'
			+ '<label>Correct Answer:</label>'
			+ '<input type="text" name="questions[' + idx + '][correct_answer]" value="" class="regular-text">'
			+ '</div>'
			+ '</div>';
	}

	if ($.fn.sortable && $('#zeko-questions-builder').length) {
		$('#zeko-questions-builder').sortable({ handle: '.zeko-drag-handle', items: '.zeko-question' });
	}

	// Export grades CSV
	$(document).on('click', '.zeko-export-grades', function(e) {
		e.preventDefault();
		var btn = $(this);
		var courseId = btn.data('course-id');
		if (!courseId) return;
		btn.text('Exporting...').prop('disabled', true);
		$.post(zekoLearnAdmin.ajax, {
			action: 'zeko_learn_export_grades',
			nonce: zekoLearnAdmin.nonce,
			course_id: courseId
		}, function(r) {
			if (r.success && r.data.csv) {
				var decoded = atob(r.data.csv);
				var blob = new Blob([decoded], { type: 'text/csv' });
				var url = URL.createObjectURL(blob);
				var a = document.createElement('a');
				a.href = url;
				a.download = r.data.filename || 'grades.csv';
				a.click();
				URL.revokeObjectURL(url);
				btn.text('Export CSV').prop('disabled', false);
			} else {
				alert(r.data ? r.data.message : 'Export failed');
				btn.text('Export CSV').prop('disabled', false);
			}
		});
	});

	// ─── Course Status Toggle ─────────────────────────────────
	$(document).on('click', '.zeko-status-toggle', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var courseId = $btn.data('course-id');
		var newStatus = $btn.data('new-status');
		$btn.prop('disabled', true).text('Updating...');

		$.post(zekoLearnAdmin.ajax, {
			action: 'zeko_learn_update_course_status',
			nonce: zekoLearnAdmin.nonce,
			course_id: courseId,
			status: newStatus
		}, function(r) {
			if (r.success) {
				$btn.text('Updated!').removeClass('zeko-status-toggle');
				setTimeout(function() { location.reload(); }, 800);
			} else {
				alert(r.data ? r.data.message : 'Error');
				$btn.prop('disabled', false).text('Try again');
			}
		});
	});

	// ─── Post Announcement ────────────────────────────────────
	$(document).on('submit', '.zeko-admin-announcement-form', function(e) {
		e.preventDefault();
		var $form = $(this);
		var $btn = $form.find('button[type="submit"]');
		$btn.prop('disabled', true);

		$.post(zekoLearnAdmin.ajax, {
			action:    'zeko_learn_pin_announcement',
			nonce:     zekoLearnAdmin.nonce,
			course_id: $form.find('[name="course_id"]').val(),
			title:     $form.find('[name="title"]').val(),
			content:   $form.find('[name="content"]').val()
		}, function(r) {
			$btn.prop('disabled', false);
			if (r.success) {
				$form.find('[name="title"]').val('');
				$form.find('[name="content"]').val('');
				var $list = $form.closest('.zeko-announcements-panel').find('.zeko-announcements-list');
				if ($list.length) {
					$list.prepend(
						'<div class="zeko-announcement-item">' +
						'<strong>' + r.data.title + '</strong>' +
						'<p>' + r.data.content + '</p>' +
						'</div>'
					);
				}
				alert(r.data.message);
			} else {
				alert(r.data ? r.data.message : 'Error');
			}
		});
	});

	// ─── Grade Assignment Inline ──────────────────────────────
	$(document).on('submit', '.zeko-grade-form', function(e) {
		e.preventDefault();
		var $form = $(this);
		var submissionId = $form.data('submission-id');
		$form.find('button').prop('disabled', true);

		$.post(zekoLearnAdmin.ajax, {
			action:       'zeko_learn_grade_assignment',
			nonce:        zekoLearnAdmin.nonce,
			submission_id: submissionId,
			grade:        $form.find('[name="grade"]').val(),
			feedback:     $form.find('[name="feedback"]').val()
		}, function(r) {
			if (r.success) {
				$form.closest('tr').find('.zeko-grade-status').text('Graded').addClass('graded');
				alert(r.data.message);
			} else {
				alert(r.data ? r.data.message : 'Error');
				$form.find('button').prop('disabled', false);
			}
		});
	});

	// ─── Reorder Sections (drag & drop) ───────────────────────
	if ($.fn.sortable && $('#zeko-curriculum-builder').length) {
		$('#zeko-curriculum-builder').sortable({
			handle: '.zeko-drag-handle',
			items: '.zeko-section',
			update: function() {
				var order = [];
				$('#zeko-curriculum-builder .zeko-section').each(function(i) {
					var id = $(this).find('[name*="[id]"]').val();
					if (id) order.push(id);
				});
				if (order.length) {
					$.post(zekoLearnAdmin.ajax, {
						action: 'zeko_learn_reorder_sections',
						nonce: zekoLearnAdmin.nonce,
						order: order
					});
				}
			}
		});
		$('.zeko-lessons').sortable({
			handle: '.zeko-drag-handle',
			items: '.zeko-lesson'
		});
	}

	// ─── Demo Data: Generate ──────────────────────────────────
	$(document).on('click', '#zeko-generate-demo', function() {
		var $btn = $(this);
		var $status = $('#zeko-demo-generate-status');

		if (!confirm('Generate demo data? This may take a moment.')) return;

		$btn.prop('disabled', true).text('Generating...');
		$status.show().html('<p><span class="dashicons dashicons-update" style="animation:spin 1s linear infinite"></span> Generating demo data...</p>');

		$.post(zekoLearnAdmin.ajaxUrl, {
			action: 'zeko_learn_generate_demo_data',
			nonce: zekoLearnAdmin.nonce
		}, function(res) {
			$btn.prop('disabled', false).html('<span class="dashicons dashicons-performance" style="margin-top:5px;"></span> Generate Demo Data');
			if (res.success) {
				$status.html('<div class="notice notice-success inline"><p>' + res.data.message + '</p></div>');
				setTimeout(function() { location.reload(); }, 1500);
			} else {
				$status.html('<div class="notice notice-error inline"><p>' + (res.data ? res.data.message : 'Error') + '</p></div>');
			}
		}).fail(function() {
			$btn.prop('disabled', false).html('<span class="dashicons dashicons-performance" style="margin-top:5px;"></span> Generate Demo Data');
			$status.html('<div class="notice notice-error inline"><p>Network error.</p></div>');
		});
	});

	// ─── Demo Data: Clear ─────────────────────────────────────
	$(document).on('click', '#zeko-clear-demo', function() {
		var $btn = $(this);
		var $status = $('#zeko-demo-clear-status');

		if (!confirm('Are you sure you want to clear ALL demo data? This cannot be undone.')) return;

		$btn.prop('disabled', true).text('Clearing...');
		$status.show().html('<p><span class="dashicons dashicons-update" style="animation:spin 1s linear infinite"></span> Clearing demo data...</p>');

		$.post(zekoLearnAdmin.ajaxUrl, {
			action: 'zeko_learn_clear_demo_data',
			nonce: zekoLearnAdmin.nonce
		}, function(res) {
			$btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-top:5px;"></span> Clear All Demo Data');
			if (res.success) {
				$status.html('<div class="notice notice-success inline"><p>' + res.data.message + '</p></div>');
				setTimeout(function() { location.reload(); }, 1500);
			} else {
				$status.html('<div class="notice notice-error inline"><p>' + (res.data ? res.data.message : 'Error') + '</p></div>');
			}
		}).fail(function() {
			$btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-top:5px;"></span> Clear All Demo Data');
			$status.html('<div class="notice notice-error inline"><p>Network error.</p></div>');
		});
	});

})(jQuery);
