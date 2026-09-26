/**
 * Zeko Learn — Unified Notification Center
 *
 * Self-contained component: bell icon + dropdown with module filter.
 * Works across all Zeko ecosystem modules (learn, jobs, qa, pay).
 */
(function($) {
	'use strict';

	var ZL = window.zekoLearnPublic || {};
	var currentModule = '';

	// ─── Toggle Dropdown ──────────────────────────────────────
	$(document).on('click', '.zeko-notif-bell, .zeko-notif-bell-floating', function(e) {
		e.stopPropagation();
		var $dropdown = $(this).find('.zeko-notif-dropdown');
		var isOpen = $dropdown.hasClass('is-open');

		// Close all other dropdowns
		$('.zeko-notif-dropdown.is-open').removeClass('is-open');

		if (!isOpen) {
			$dropdown.addClass('is-open');
			loadNotifications('');
		}
	});

	// Close on outside click
	$(document).on('click', function() {
		$('.zeko-notif-dropdown.is-open').removeClass('is-open');
	});

	$(document).on('click', '.zeko-notif-dropdown', function(e) {
		e.stopPropagation();
	});

	// ─── Module Filter ────────────────────────────────────────
	$(document).on('click', '.zeko-notif-module-btn', function(e) {
		e.preventDefault();
		var module = $(this).data('module') || '';

		$('.zeko-notif-module-btn').removeClass('active');
		$(this).addClass('active');

		currentModule = module;
		loadNotifications(module);
	});

	// ─── Mark Single as Read ──────────────────────────────────
	$(document).on('click', '.zeko-notif-item', function() {
		var $item = $(this);
		var notifId = $item.data('notif-id');
		var link = $item.data('link');

		if ($item.hasClass('is-unread') && notifId) {
			$.post(ZL.ajaxUrl, {
				action: 'zeko_learn_mark_notification_read',
				nonce: ZL.nonce,
				notification_id: notifId,
				source: $item.data('source') || 'learn'
			});
			$item.removeClass('is-unread');
			updateBadge(-1);
		}

		if (link) {
			window.location.href = link;
		}
	});

	// ─── Mark All as Read ─────────────────────────────────────
	$(document).on('click', '.zeko-notif-mark-all', function(e) {
		e.preventDefault();
		$.post(ZL.ajaxUrl, {
			action: 'zeko_learn_mark_notification_read',
			nonce: ZL.nonce
		}, function(res) {
			if (res.success) {
				$('.zeko-notif-item.is-unread').removeClass('is-unread');
				$('.zeko-notif-badge').text('0').hide();
			}
		});
	});

	// ─── Load Notifications ───────────────────────────────────
	function loadNotifications(module) {
		var $list = $('.zeko-notif-list');
		$list.html('<div class="zeko-notif-loading"><span class="dashicons dashicons-update"></span></div>');

		$.post(ZL.ajaxUrl, {
			action: 'zeko_learn_get_notifications',
			nonce: ZL.nonce,
			module: module
		}, function(res) {
			if (!res.success || !res.data.notifications.length) {
				$list.html(
					'<div class="zeko-notif-empty">' +
					'<span class="dashicons dashicons-bell"></span>' +
					'<p>No notifications yet</p>' +
					'</div>'
				);
				return;
			}

			var html = '';
			$.each(res.data.notifications, function(i, n) {
				var unread = n.is_read == 0 ? ' is-unread' : '';
				var modInfo = n.module_info || { icon: 'dashicons-admin-site', label: 'System' };
				var avatar = n.actor_avatar
					? '<img class="zeko-notif-avatar" src="' + n.actor_avatar + '" alt="">'
					: '<div class="zeko-notif-avatar zeko-notif-avatar-placeholder"><span class="dashicons ' + modInfo.icon + '"></span></div>';

				html += '<div class="zeko-notif-item' + unread + '" data-notif-id="' + n.id + '" data-source="' + (n.source || 'learn') + '" data-link="' + (n.link || '') + '">';
				html += avatar;
				html += '<div class="zeko-notif-content">';
				html += '<div class="zeko-notif-item-title">' + escapeHtml(n.action_label || n.action) + '</div>';
				html += '<div class="zeko-notif-item-message">' + escapeHtml(n.message || n.object_type) + '</div>';
				html += '<div class="zeko-notif-item-time">';
				html += '<span class="zeko-notif-module-icon" data-module="' + (n.module || 'learn') + '">';
				html += '<span class="dashicons ' + modInfo.icon + '"></span> ' + modInfo.label;
				html += '</span>';
				html += ' &middot; ' + n.time_ago;
				html += '</div>';
				html += '</div>';
				html += '</div>';
			});

			$list.html(html);

			// Update badge
			if (typeof res.data.total_unread !== 'undefined') {
				$('.zeko-notif-badge').text(res.data.total_unread);
				if (res.data.total_unread > 0) {
					$('.zeko-notif-badge').show();
				} else {
					$('.zeko-notif-badge').hide();
				}
			}
		});
	}

	// ─── Update Badge Count ───────────────────────────────────
	function updateBadge(delta) {
		var $badge = $('.zeko-notif-badge');
		var count = parseInt($badge.text(), 10) || 0;
		count = Math.max(0, count + delta);
		$badge.text(count);
		if (count > 0) {
			$badge.show();
		} else {
			$badge.hide();
		}
	}

	// ─── Escape HTML ──────────────────────────────────────────
	function escapeHtml(str) {
		if (!str) return '';
		return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	// ─── Poll for new notifications (every 60s) ──────────────
	if (ZL.currentUser && ZL.currentUser > 0) {
		setInterval(function() {
			$.post(ZL.ajaxUrl, {
				action: 'zeko_learn_get_notifications',
				nonce: ZL.nonce,
				module: ''
			}, function(res) {
				if (res.success && typeof res.data.total_unread !== 'undefined') {
					$('.zeko-notif-badge').text(res.data.total_unread);
					if (res.data.total_unread > 0) {
						$('.zeko-notif-badge').show();
					} else {
						$('.zeko-notif-badge').hide();
					}
				}
			});
		}, 60000);
	}

})(jQuery);
