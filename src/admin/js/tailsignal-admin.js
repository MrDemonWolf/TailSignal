/**
 * TailSignal Admin JavaScript
 *
 * Handles AJAX operations for send, device management, groups, and meta box.
 */
(function($) {
	'use strict';

	// ── Helpers ─────────────────────────────────────────────────

	/**
	 * Show a WordPress-style admin notice inside #tailsignal-app.
	 *
	 * @param {string} message The message to display.
	 * @param {string} type    Notice type: 'error', 'success', 'warning', 'info'.
	 */
	function tailsignalNotice(message, type) {
		type = type || 'error';
		var $existing = $('#tailsignal-app > .notice');
		$existing.remove();
		var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p></p></div>');
		$notice.find('p').text(message);
		var $dismiss = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text"></span></button>');
		$dismiss.find('.screen-reader-text').text(tailsignal.strings.dismiss || 'Dismiss');
		$notice.append($dismiss);
		$('#tailsignal-app .tailsignal-page-header').after($notice);
		$dismiss.on('click', function() { $notice.fadeOut(200, function() { $(this).remove(); }); });
		setTimeout(function() { $notice.fadeOut(200, function() { $(this).remove(); }); }, 5000);
	}

	/**
	 * Show a styled confirmation modal using existing .tailsignal-modal-* CSS.
	 *
	 * @param {string}   message   The confirmation message.
	 * @param {Function} onConfirm Callback executed when user clicks Confirm.
	 */
	function tailsignalConfirm(message, onConfirm) {
		var $overlay = $('<div class="tailsignal-modal-overlay" style="position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;" role="dialog" aria-modal="true" aria-label="Confirmation dialog"></div>');
		var $panel = $('<div class="tailsignal-modal-panel" style="max-width:400px;width:90%;"></div>');
		var $header = $('<div class="tailsignal-modal-header"></div>');
		$header.append($('<h3></h3>').text(tailsignal.strings.confirm || 'Confirm'));
		$panel.append($header);
		var $body = $('<div class="tailsignal-modal-body"></div>');
		$body.append($('<p></p>').text(message));
		var $actions = $('<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;"></div>');
		$actions.append($('<button type="button" class="button ts-confirm-cancel"></button>').text(tailsignal.strings.cancel || 'Cancel'));
		$actions.append($('<button type="button" class="button button-primary ts-confirm-ok"></button>').text(tailsignal.strings.confirm || 'Confirm'));
		$body.append($actions);
		$panel.append($body);
		$overlay.append($panel);
		$('body').append($overlay);
		$overlay.on('click', '.ts-confirm-cancel', function() { $overlay.remove(); });
		$overlay.on('click', '.ts-confirm-ok', function() { $overlay.remove(); onConfirm(); });
	}

	// ── Send Notification Page ──────────────────────────────────

	// Target type radio toggle
	$(document).on('change', '.tailsignal-target-radio', function() {
		var value = $(this).val();
		$('#tailsignal-specific-devices').toggle(value === 'specific');
		$('#tailsignal-group-select').prop('disabled', value !== 'group');
	});

	// Schedule radio toggle
	$(document).on('change', '.tailsignal-when-radio', function() {
		var isSchedule = $(this).val() === 'schedule';
		var $btn = $('#tailsignal-send-btn');
		if (!$btn.data('original-text')) {
			$btn.data('original-text', $btn.text());
		}
		$('#tailsignal-schedule-datetime').prop('disabled', !isSchedule);
		$btn.text(isSchedule ?
			(tailsignal.strings.schedule || 'Schedule') :
			$btn.data('original-text')
		);
	});

	// Send notification form
	$(document).on('submit', '#tailsignal-send-form', function(e) {
		e.preventDefault();

		var $btn = $('#tailsignal-send-btn');
		var $status = $('#tailsignal-send-status');
		var originalText = $btn.text();

		// Require a datetime when scheduling.
		var sendWhen = $('[name="send_when"]:checked').val();
		if (sendWhen === 'schedule' && !$('#tailsignal-schedule-datetime').val()) {
			tailsignalNotice(tailsignal.strings.schedule_datetime_required || 'Please choose a date and time before scheduling.', 'error');
			return;
		}

		$btn.prop('disabled', true).text(tailsignal.strings.sending);
		$status.text('').removeClass('tailsignal-status-success tailsignal-status-error');

		var data = {
			action: 'tailsignal_send_notification',
			nonce: tailsignal.nonce,
			title: $('[name="title"]', this).val(),
			body: $('[name="body"]', this).val(),
			image_url: $('[name="image_url"]', this).val(),
			data: $('[name="data"]', this).val(),
			target_type: $('[name="target_type"]:checked', this).val()
		};

		// Target IDs
		if (data.target_type === 'group') {
			data.target_ids = [$('#tailsignal-group-select').val()];
		} else if (data.target_type === 'specific') {
			data.target_ids = [];
			$('.tailsignal-device-checkbox:checked').each(function() {
				data.target_ids.push($(this).val());
			});
		}

		// Schedule
		if (sendWhen === 'schedule') {
			data.scheduled_at = $('#tailsignal-schedule-datetime').val();
		}

		$.post(tailsignal.ajax_url, data, function(response) {
			if (response.success) {
				$status.text(response.data.message).addClass('tailsignal-status-success');
				if (sendWhen !== 'schedule') {
					$btn.text(tailsignal.strings.sent);
				} else {
					$btn.text(tailsignal.strings.scheduled);
					setTimeout(function() { location.reload(); }, 1500);
				}
			} else {
				$status.text(response.data.message).addClass('tailsignal-status-error');
				$btn.text(originalText);
			}
			$btn.prop('disabled', false);
		}).fail(function() {
			$status.text(tailsignal.strings.error).addClass('tailsignal-status-error');
			$btn.prop('disabled', false).text(originalText);
		});
	});

	// Cancel scheduled notification
	$(document).on('click', '.tailsignal-cancel-scheduled', function() {
		var $btn = $(this);
		var id = $btn.data('id');

		tailsignalConfirm(tailsignal.strings.confirm_delete, function() {
			$.post(tailsignal.ajax_url, {
				action: 'tailsignal_cancel_scheduled',
				nonce: tailsignal.nonce,
				notification_id: id
			}, function(response) {
				if (response.success) {
					$btn.closest('tr').fadeOut(function() { $(this).remove(); });
				} else {
					tailsignalNotice(response.data.message, 'error');
				}
			}).fail(function() {
				tailsignalNotice(tailsignal.strings.error, 'error');
			});
		});
	});

	// Placeholder quick-fill buttons
	$(document).on('click', '.tailsignal-placeholder-btn', function() {
		var targetId = $(this).data('target');
		var value = $(this).data('value');
		var $field = $('#' + targetId);
		var el = $field[0];

		if (el && el.setSelectionRange) {
			var start = el.selectionStart;
			var end = el.selectionEnd;
			var text = $field.val();
			$field.val(text.substring(0, start) + value + text.substring(end));
			el.selectionStart = el.selectionEnd = start + value.length;
			$field.trigger('input');
		} else {
			$field.val($field.val() + value);
			$field.trigger('input');
		}
		$field.focus();
	});

	// Character counters
	function updateCharCount($field) {
		var $counter = $('.tailsignal-char-count[data-target="' + $field.attr('id') + '"]');
		if (!$counter.length) return;
		var len = $field.val().length;
		var limit = parseInt($counter.data('limit'), 10);
		$counter.text(len + ' / ' + limit);
		$counter.toggleClass('tailsignal-char-over', len > limit);
	}

	$(document).on('input', '#tailsignal-title, #tailsignal-body', function() {
		updateCharCount($(this));
	});

	// Live preview update (iOS + Android)
	$(document).on('input', '#tailsignal-title, #tailsignal-body, #tailsignal-image-url', function() {
		var title = $('#tailsignal-title').val();
		var body = $('#tailsignal-body').val();
		var imageUrl = $('#tailsignal-image-url').val();
		var defaultTitle = 'Notification Title';
		var defaultBody = 'Notification body text will appear here...';
		var sanitizedUrl = imageUrl ? 'url("' + imageUrl.replace(/["()]/g, '') + '")' : '';
		var hasImage = imageUrl && /^https?:\/\/.+/i.test(imageUrl);

		// iOS preview
		$('#tailsignal-preview-title').text(title || defaultTitle);
		$('#tailsignal-preview-body').text(body || defaultBody);
		var $img = $('#tailsignal-preview-image');
		if (hasImage) {
			$img.css('background-image', sanitizedUrl).show();
		} else {
			$img.hide().css('background-image', '');
		}

		// Android preview
		$('#tailsignal-preview-title-android').text(title || defaultTitle);
		$('#tailsignal-preview-body-android').text(body || defaultBody);
		var $imgAndroid = $('#tailsignal-preview-image-android');
		if (hasImage) {
			$imgAndroid.css('background-image', sanitizedUrl).show();
		} else {
			$imgAndroid.hide().css('background-image', '');
		}
	});

	// Preview platform toggle (iOS / Android)
	$(document).on('click', '.tailsignal-preview-toggle-btn', function() {
		var platform = $(this).data('preview');
		$('.tailsignal-preview-toggle-btn').removeClass('active');
		$(this).addClass('active');
		$('.tailsignal-preview-variant').hide();
		$('#tailsignal-preview-' + platform).show();
	});

	// Fill Test Data button
	$(document).on('click', '#tailsignal-fill-test', function() {
		$('#tailsignal-title').val('Test Notification from TailSignal').trigger('input');
		$('#tailsignal-body').val('This is a test push notification. If you received this, TailSignal is working correctly!').trigger('input');
		$('#tailsignal-image-url').val('https://placehold.co/1200x630/0FACED/white?text=TailSignal+Test').trigger('input');
		$('input[name="target_type"][value="dev"]').prop('checked', true).trigger('change');
	});

	// WordPress Media Library picker
	$(document).on('click', '#tailsignal-choose-image', function(e) {
		e.preventDefault();

		if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
			console.warn('TailSignal: wp.media not available. Media library scripts may not be loaded.');
			return;
		}

		var frame = wp.media({
			title: tailsignal.strings.choose_image || 'Choose Image',
			button: { text: tailsignal.strings.use_image || 'Use this image' },
			multiple: false,
			library: { type: 'image' }
		});

		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			$('#tailsignal-image-url').val(attachment.url).trigger('input');
		});

		frame.open();
	});

	// ── History Page ───────────────────────────────────────────

	// Delete All History
	$(document).on('click', '#tailsignal-delete-all-history', function() {
		var $btn = $(this);

		tailsignalConfirm(tailsignal.strings.confirm_delete_all, function() {
			$btn.prop('disabled', true).text(tailsignal.strings.deleting);

			$.post(tailsignal.ajax_url, {
				action: 'tailsignal_delete_all_notifications',
				nonce: tailsignal.nonce
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					tailsignalNotice(response.data.message, 'error');
					$btn.prop('disabled', false).text(tailsignal.strings.delete_all_history);
				}
			}).fail(function() {
				tailsignalNotice(tailsignal.strings.error, 'error');
				$btn.prop('disabled', false).text(tailsignal.strings.delete_all_history);
			});
		});
	});

	// ── Dashboard Clear All Recent ────────────────────────────

	$(document).on('click', '#tailsignal-clear-recent', function() {
		var $btn = $(this);

		tailsignalConfirm(tailsignal.strings.confirm_delete_all, function() {
			$btn.prop('disabled', true);

			$.post(tailsignal.ajax_url, {
				action: 'tailsignal_delete_all_notifications',
				nonce: tailsignal.nonce
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					tailsignalNotice(response.data.message, 'error');
					$btn.prop('disabled', false);
				}
			}).fail(function() {
				tailsignalNotice(tailsignal.strings.error, 'error');
				$btn.prop('disabled', false);
			});
		});
	});

	// ── Dashboard Chart ────────────────────────────────────────

	if (typeof Chart !== 'undefined' && typeof tailsignal_chart !== 'undefined' && tailsignal_chart.labels.length) {
		var ctx = document.getElementById('tailsignal-chart');
		if (ctx) {
			// Format month labels (2025-01 → Jan)
			var monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
			var formattedLabels = tailsignal_chart.labels.map(function(label) {
				var parts = label.split('-');
				return monthNames[parseInt(parts[1], 10) - 1] || label;
			});

			new Chart(ctx, {
				type: 'bar',
				data: {
					labels: formattedLabels,
					datasets: [
						{
							label: 'Successful',
							data: tailsignal_chart.success,
							backgroundColor: 'rgba(34, 197, 94, 0.8)',
							hoverBackgroundColor: '#22c55e',
							borderRadius: 4,
							borderSkipped: false
						},
						{
							label: 'Failed',
							data: tailsignal_chart.failed,
							backgroundColor: 'rgba(239, 68, 68, 0.8)',
							hoverBackgroundColor: '#ef4444',
							borderRadius: 4,
							borderSkipped: false
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						intersect: false,
						mode: 'index'
					},
					plugins: {
						legend: {
							position: 'bottom',
							labels: {
								boxWidth: 10,
								boxHeight: 10,
								padding: 20,
								font: { size: 12, weight: '500' },
								usePointStyle: true,
								pointStyle: 'rectRounded'
							}
						},
						tooltip: {
							backgroundColor: '#1e293b',
							titleFont: { size: 13, weight: '600' },
							bodyFont: { size: 12 },
							padding: 10,
							cornerRadius: 6,
							displayColors: true,
							boxPadding: 4
						}
					},
					scales: {
						x: {
							stacked: true,
							grid: { display: false },
							border: { display: false },
							ticks: {
								font: { size: 11, weight: '500' },
								color: '#94a3b8'
							}
						},
						y: {
							stacked: true,
							beginAtZero: true,
							grid: {
								color: 'rgba(0, 0, 0, 0.04)',
								drawBorder: false
							},
							border: { display: false },
							ticks: {
								stepSize: 1,
								font: { size: 11 },
								color: '#94a3b8',
								padding: 8
							}
						}
					}
				}
			});
		}
	}

	// ── Devices Page ────────────────────────────────────────────

	// Edit device label
	$(document).on('click', '.tailsignal-edit-device', function(e) {
		e.preventDefault();
		var id = $(this).data('id');
		var label = $(this).data('label') || '';
		$('#tailsignal-edit-device-id').val(id);
		$('#tailsignal-edit-label').val(label);
		$('#tailsignal-edit-dialog').show();
	});

	$('#tailsignal-edit-cancel').on('click', function() {
		$('#tailsignal-edit-dialog').hide();
	});

	$('#tailsignal-edit-save').on('click', function() {
		var id = $('#tailsignal-edit-device-id').val();
		var label = $('#tailsignal-edit-label').val();

		$.post(tailsignal.ajax_url, {
			action: 'tailsignal_update_device',
			nonce: tailsignal.nonce,
			device_id: id,
			user_label: label
		}, function(response) {
			if (response.success) {
				location.reload();
			} else {
				tailsignalNotice(response.data.message, 'error');
			}
		});
	});

	// Toggle dev flag
	$(document).on('click', '.tailsignal-toggle-dev', function(e) {
		e.preventDefault();
		var id = $(this).data('id');
		var isDev = $(this).data('dev');

		$.post(tailsignal.ajax_url, {
			action: 'tailsignal_toggle_dev',
			nonce: tailsignal.nonce,
			device_id: id,
			is_dev: isDev
		}, function(response) {
			if (response.success) {
				location.reload();
			} else {
				tailsignalNotice(response.data.message, 'error');
			}
		});
	});

	// Import CSV toggle
	$('#tailsignal-import-btn').on('click', function() {
		$('#tailsignal-import-form').toggle();
	});

	$('#tailsignal-import-cancel').on('click', function() {
		$('#tailsignal-import-form').hide();
	});

	// Import CSV upload
	$(document).on('submit', '#tailsignal-import-upload', function(e) {
		e.preventDefault();
		var formData = new FormData(this);
		var $status = $('#tailsignal-import-status');

		$.ajax({
			url: tailsignal.rest_url + 'devices/import',
			method: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			headers: { 'X-WP-Nonce': tailsignal.rest_nonce },
			success: function(response) {
				$status.text(response.message).removeClass('tailsignal-status-error').addClass('tailsignal-status-success');
				setTimeout(function() { location.reload(); }, 2000);
			},
			error: function(xhr) {
				var msg = xhr.responseJSON ? xhr.responseJSON.message : tailsignal.strings.error;
				$status.text(msg).removeClass('tailsignal-status-success').addClass('tailsignal-status-error');
			}
		});
	});

	// ── Groups Page ─────────────────────────────────────────────

	// Show create group form
	$('#tailsignal-create-group-btn').on('click', function() {
		$('#tailsignal-group-form').show();
		$('#tailsignal-group-form input[name="group_id"]').val('');
		$('#tailsignal-group-name').val('');
		$('#tailsignal-group-description').val('');
		$('#tailsignal-group-form input[type="checkbox"]').prop('checked', false);
		updateDeviceSelectedCount();
	});

	// Update the selected device count display
	function updateDeviceSelectedCount() {
		var $counter = $('#tailsignal-device-selected-count');
		if ( ! $counter.length ) return;
		var checked = $('#tailsignal-group-save-form input[name="device_ids[]"]:checked').length;
		var total   = $('#tailsignal-group-save-form input[name="device_ids[]"]').length;
		var template = tailsignal.strings.selected_count || '%1$s / %2$s selected';
		$counter.text( template.replace('%1$s', checked).replace('%2$s', total) );
	}

	// Device search in groups
	$('#tailsignal-group-device-search').on('input', function() {
		var query = $(this).val().toLowerCase();
		$('.tailsignal-device-option').each(function() {
			var label = $(this).data('label') || '';
			$(this).toggle(label.indexOf(query) !== -1);
		});
	});

	// Select all / Deselect all
	$('#tailsignal-select-all-devices').on('click', function() {
		$('.tailsignal-device-option:visible input[type="checkbox"]').prop('checked', true);
		updateDeviceSelectedCount();
	});

	$('#tailsignal-deselect-all-devices').on('click', function() {
		$('.tailsignal-device-option:visible input[type="checkbox"]').prop('checked', false);
		updateDeviceSelectedCount();
	});

	// Update count on any checkbox change
	$(document).on('change', '#tailsignal-group-save-form input[name="device_ids[]"]', function() {
		updateDeviceSelectedCount();
	});

	// Save group
	$(document).on('submit', '#tailsignal-group-save-form', function(e) {
		e.preventDefault();

		var $status = $('#tailsignal-group-status');
		var deviceIds = [];
		$('#tailsignal-group-save-form input[name="device_ids[]"]:checked').each(function() {
			deviceIds.push($(this).val());
		});

		$.post(tailsignal.ajax_url, {
			action: 'tailsignal_save_group',
			nonce: tailsignal.nonce,
			group_id: $('[name="group_id"]', this).val(),
			name: $('[name="name"]', this).val(),
			description: $('[name="description"]', this).val(),
			device_ids: deviceIds
		}, function(response) {
			if (response.success) {
				$status.text(response.data.message).removeClass('tailsignal-status-error').addClass('tailsignal-status-success');
				setTimeout(function() {
					window.location.href = tailsignal.ajax_url.replace('admin-ajax.php', 'admin.php?page=tailsignal-groups');
				}, 1000);
			} else {
				$status.text(response.data.message).removeClass('tailsignal-status-success').addClass('tailsignal-status-error');
			}
		});
	});

	// Delete group
	$(document).on('click', '.tailsignal-delete-group', function() {
		var $btn = $(this);
		var id = $btn.data('id');

		tailsignalConfirm(tailsignal.strings.confirm_delete, function() {
			$.post(tailsignal.ajax_url, {
				action: 'tailsignal_delete_group',
				nonce: tailsignal.nonce,
				group_id: id
			}, function(response) {
				if (response.success) {
					$btn.closest('tr').fadeOut(function() { $(this).remove(); });
				} else {
					tailsignalNotice(response.data.message, 'error');
				}
			});
		});
	});

	// ── Meta Box (Quick Send) ───────────────────────────────────

	$(document).on('click', '.tailsignal-quick-send-btn', function() {
		var $btn = $(this);
		var originalText = $btn.text();
		var $status = $btn.siblings('.tailsignal-quick-send-status');
		var postId = $btn.data('post-id');
		var $metaBox = $btn.closest('.tailsignal-meta-box');

		var targetType = $metaBox.find('[name="tailsignal_quick_target"]:checked').val();
		var targetIds = null;

		if (targetType === 'group') {
			targetIds = [$metaBox.find('.tailsignal-quick-group-select').val()];
		}

		$btn.prop('disabled', true).text(tailsignal.strings.sending);
		$status.text('');

		$.post(tailsignal.ajax_url, {
			action: 'tailsignal_quick_send',
			nonce: tailsignal.nonce,
			post_id: postId,
			title: $metaBox.find('.tailsignal-quick-title').val(),
			body: $metaBox.find('.tailsignal-quick-body').val(),
			target_type: targetType,
			target_ids: targetIds,
			include_image: $metaBox.find('[name="tailsignal_include_image"]:checked').length ? '1' : '0'
		}, function(response) {
			if (response.success) {
				$status.text(response.data.message).removeClass('tailsignal-status-error').addClass('tailsignal-status-success');
			} else {
				$status.text(response.data.message).removeClass('tailsignal-status-success').addClass('tailsignal-status-error');
			}
			$btn.prop('disabled', false).text(originalText);
		}).fail(function() {
			$status.text(tailsignal.strings.error).removeClass('tailsignal-status-success').addClass('tailsignal-status-error');
			$btn.prop('disabled', false).text(originalText);
		});
	});

})(jQuery);
