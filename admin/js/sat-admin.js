/* global SAT, jQuery */
(function ($) {
	'use strict';

	// ── State ─────────────────────────────────────────────────────────────────
	var media = {
		page: 1,
		pages: 1,
		total: 0,
		bulkRunning: false,
		bulkCancelled: false,
	};

	// ── Init ──────────────────────────────────────────────────────────────────
	$(function () {
		bindTabs();
		bindMediaEvents();
		bindThemeEvents();
		bindRescanAll();

		// Load both panels immediately
		loadMediaPage(1);
		loadThemeScan(false); // false = use cache if available
	});

	// =========================================================================
	// TABS
	// =========================================================================
	function bindTabs() {
		$('.sat-tab').on('click', function () {
			var $btn   = $(this);
			var target = $btn.attr('aria-controls');

			$('.sat-tab').attr('aria-selected', 'false').removeClass('sat-tab--active');
			$('.sat-panel').attr('hidden', true).removeClass('sat-panel--active');

			$btn.attr('aria-selected', 'true').addClass('sat-tab--active');
			$('#' + target).removeAttr('hidden').addClass('sat-panel--active');
		});
	}

	// =========================================================================
	// RESCAN ALL
	// =========================================================================
	function bindRescanAll() {
		$('#sat-rescan-all-btn').on('click', function () {
			if (!confirm(SAT.i18n.confirmRescan)) return;
			var $btn = $(this);
			$btn.prop('disabled', true).find('.dashicons').addClass('sat-spin');

			// Run both rescans in parallel, re-enable button when both complete
			var done = 0;
			function onDone() {
				done++;
				if (done >= 2) {
					$btn.prop('disabled', false).find('.dashicons').removeClass('sat-spin');
				}
			}

			loadMediaPage(1, onDone);
			loadThemeScan(true, onDone); // true = force rescan
		});
	}

	// =========================================================================
	// MEDIA LIBRARY
	// =========================================================================
	function bindMediaEvents() {
		$('#sat-rescan-media-btn').on('click', function () {
			loadMediaPage(1);
		});

		$('#sat-prev-page').on('click', function () {
			if (media.page > 1) loadMediaPage(media.page - 1);
		});

		$('#sat-next-page').on('click', function () {
			if (media.page < media.pages) loadMediaPage(media.page + 1);
		});

		$('#sat-bulk-generate-btn').on('click', function () {
			if (!confirm(SAT.i18n.confirmBulk)) return;
			bulkGenerate();
		});

		$('#sat-cancel-bulk').on('click', function () {
			media.bulkCancelled = true;
		});

		// Delegate row events
		$('#sat-media-list')
			.on('click', '.sat-save-btn',     function () { saveAltTag($(this).closest('tr')); })
			.on('click', '.sat-generate-btn', function () { generateAltTag($(this).closest('tr')); })
			.on('keydown', '.sat-alt-input', function (e) {
				if (e.key === 'Enter') { e.preventDefault(); saveAltTag($(this).closest('tr')); }
			});
	}

	// ── Load a page of media images ──────────────────────────────────────────
	function loadMediaPage(page, callback) {
		var $tbody = $('#sat-media-list');
		$tbody.html(loadingRow(4));
		$('#sat-media-pagination').hide();

		$.post(SAT.ajaxUrl, { action: 'sat_get_media_images', nonce: SAT.nonce, page: page },
		function (res) {
			if (!res.success) {
				$tbody.html(msgRow(4, res.data ? res.data.message : SAT.i18n.error));
				if (typeof callback === 'function') callback();
				return;
			}
			media.page  = res.data.page;
			media.pages = res.data.pages;
			media.total = res.data.total;

			renderMediaRows(res.data.images);
			updateMediaCount(res.data.total);
			updateMediaPagination();

			// Update summary bar
			$('#sat-media-count').text(res.data.total);
			$('#sat-tab-media-badge').text(res.data.total > 0 ? res.data.total : '');

			if (typeof callback === 'function') callback();
		}).fail(function () {
			$tbody.html(msgRow(4, SAT.i18n.error));
			if (typeof callback === 'function') callback();
		});
	}

	// ── Render media rows from template ─────────────────────────────────────
	function renderMediaRows(images) {
		var $tbody = $('#sat-media-list');
		$tbody.empty();

		if (!images || images.length === 0) {
			$tbody.html(
				'<tr><td colspan="4" class="sat-empty">' +
				'<span class="dashicons dashicons-yes-alt"></span> ' +
				'All images in the media library have alt tags.' +
				'</td></tr>'
			);
			return;
		}

		var tpl = $('#sat-media-row-tpl').html();

		$.each(images, function (i, img) {
			var html = tpl
				.replace(/\{\{id\}\}/g,         escAttr(img.id))
				.replace(/\{\{full_url\}\}/g,   escAttr(img.full_url))
				.replace(/\{\{thumb_url\}\}/g,  escAttr(img.thumb_url || ''))
				.replace(/\{\{filename\}\}/g,   esc(img.filename))
				.replace(/\{\{dimensions\}\}/g, esc(img.dimensions))
				.replace(/\{\{file_size\}\}/g,  esc(img.file_size))
				.replace(/\{\{date\}\}/g,       esc(img.date))
				.replace(/\{\{alt\}\}/g,        escAttr(img.alt));
			$tbody.append(html);
		});
	}

	// ── Save a single alt tag ────────────────────────────────────────────────
	function saveAltTag($row) {
		var id      = $row.data('id');
		var altText = $row.find('.sat-alt-input').val();
		var $btn    = $row.find('.sat-save-btn');
		var $status = $row.find('.sat-status');

		setRowBusy($btn, 'dashicons-saved');
		setStatus($status, SAT.i18n.saving, '');

		$.post(SAT.ajaxUrl, {
			action: 'sat_save_alt_tag', nonce: SAT.nonce,
			attachment_id: id, alt_text: altText
		}, function (res) {
			setRowIdle($btn, 'dashicons-saved');
			if (res.success) {
				var savedMsg = SAT.i18n.saved;
				if (res.data.posts_updated > 0) {
					savedMsg += ' Updated in ' + res.data.posts_updated + ' post' + (res.data.posts_updated !== 1 ? 's' : '') + '.';
				}
				setStatus($status, savedMsg, 'sat-ok');
				if (altText.trim()) {
					setTimeout(function () {
						$row.addClass('sat-fade-out');
						setTimeout(function () {
							$row.remove();
							media.total = Math.max(0, media.total - 1);
							updateMediaCount(media.total);
							$('#sat-media-count').text(media.total);
							$('#sat-tab-media-badge').text(media.total > 0 ? media.total : '');
							if ($('#sat-media-list tr').length === 0) {
								loadMediaPage(Math.max(1, media.page - 1));
							}
						}, 400);
					}, 800);
				} else {
					clearStatusAfter($status, 2000);
				}
			} else {
				setStatus($status, res.data ? res.data.message : SAT.i18n.error, 'sat-error');
			}
		}).fail(function () {
			setRowIdle($btn, 'dashicons-saved');
			setStatus($status, SAT.i18n.error, 'sat-error');
		});
	}

	// ── Generate a single alt tag via AI ────────────────────────────────────
	function generateAltTag($row, callback) {
		var id      = $row.data('id');
		var $btn    = $row.find('.sat-generate-btn');
		var $input  = $row.find('.sat-alt-input');
		var $status = $row.find('.sat-status');

		setRowBusy($btn, 'dashicons-superhero-alt');
		setStatus($status, SAT.i18n.generating, '');

		$.post(SAT.ajaxUrl, {
			action: 'sat_generate_alt_tag', nonce: SAT.nonce, attachment_id: id
		}, function (res) {
			setRowIdle($btn, 'dashicons-superhero-alt');
			if (res.success) {
				$input.val(res.data.alt_text);
				setStatus($status, 'Generated — click Save to apply.', 'sat-ok');
			} else {
				setStatus($status, res.data ? res.data.message : SAT.i18n.error, 'sat-error');
			}
			if (typeof callback === 'function') callback(res.success);
		}).fail(function () {
			setRowIdle($btn, 'dashicons-superhero-alt');
			setStatus($status, SAT.i18n.error, 'sat-error');
			if (typeof callback === 'function') callback(false);
		});
	}

	// ── Bulk generate: fetch ALL IDs site-wide, then generate + save each ────
	function bulkGenerate() {
		media.bulkRunning   = true;
		media.bulkCancelled = false;

		$('#sat-bulk-generate-btn').prop('disabled', true);
		$('#sat-bulk-progress').show();
		$('#sat-cancel-bulk').show();
		$('#sat-bulk-error').hide().text('');
		$('#sat-progress-label').text('Fetching images…');
		$('#sat-progress-bar').css('width', '0%');

		// Step 1: get every attachment ID missing an alt tag (all pages)
		$.post(SAT.ajaxUrl, { action: 'sat_get_all_media_ids', nonce: SAT.nonce },
		function (res) {
			if (!res.success || !res.data.ids.length) {
				finishBulk(0, 0, null);
				return;
			}

			var ids        = res.data.ids;
			var total      = ids.length;
			var done       = 0;
			var saved      = 0;
			var firstError = null;

			var INTER_REQUEST_DELAY = 500; // ms pause between every request
			var RATE_LIMIT_DELAY    = 15000; // ms to wait after a 429
			var MAX_RETRIES         = 3;

			updateBulkProgress(0, total);

			// Step 2: generate + save each ID sequentially, with delay + 429 retry
			function next(idx, retries) {
				if (media.bulkCancelled || idx >= total) {
					finishBulk(saved, total - saved, firstError);
					return;
				}

				var id = ids[idx];

				$.post(SAT.ajaxUrl, {
					action: 'sat_generate_alt_tag', nonce: SAT.nonce, attachment_id: id
				}, function (genRes) {
					if (!genRes.success) {
						var errMsg = genRes.data ? genRes.data.message : SAT.i18n.error;

						// Rate-limited — pause then retry the same image
						if (errMsg.indexOf('429') !== -1 && retries < MAX_RETRIES) {
							$('#sat-progress-label').text('Rate limited — pausing 15s then retrying…');
							setTimeout(function () { next(idx, retries + 1); }, RATE_LIMIT_DELAY);
							return;
						}

						if (!firstError) firstError = errMsg;
						done++;
						updateBulkProgress(done, total);
						setTimeout(function () { next(idx + 1, 0); }, INTER_REQUEST_DELAY);
						return;
					}

					var altText = genRes.data.alt_text;

					// Update the input if the row happens to be visible on this page
					var $row = $('#sat-media-list tr[data-id="' + id + '"]');
					if ($row.length) {
						$row.find('.sat-alt-input').val(altText);
					}

					$.post(SAT.ajaxUrl, {
						action: 'sat_save_alt_tag', nonce: SAT.nonce,
						attachment_id: id, alt_text: altText
					}, function (saveRes) {
						if (saveRes.success) {
							saved++;
							if ($row.length) {
								$row.addClass('sat-fade-out');
								setTimeout(function () { $row.remove(); }, 400);
							}
						}
						done++;
						updateBulkProgress(done, total);
						setTimeout(function () { next(idx + 1, 0); }, INTER_REQUEST_DELAY);
					}).fail(function () {
						done++;
						updateBulkProgress(done, total);
						setTimeout(function () { next(idx + 1, 0); }, INTER_REQUEST_DELAY);
					});
				}).fail(function () {
					if (!firstError) firstError = SAT.i18n.error;
					done++;
					updateBulkProgress(done, total);
					setTimeout(function () { next(idx + 1, 0); }, INTER_REQUEST_DELAY);
				});
			}

			next(0, 0);
		}).fail(function () {
			finishBulk(0, 0, SAT.i18n.error);
		});
	}

	function updateBulkProgress(done, total) {
		var pct = total > 0 ? Math.round((done / total) * 100) : 0;
		$('#sat-progress-bar').css('width', pct + '%');
		$('#sat-progress-label').text(
			SAT.i18n.bulkProgress.replace('%1$d', done).replace('%2$d', total)
		);
	}

	function finishBulk(saved, failed, firstError) {
		media.bulkRunning = false;
		$('#sat-bulk-generate-btn').prop('disabled', false);
		$('#sat-cancel-bulk').hide();
		$('#sat-progress-label').text(SAT.i18n.bulkDone.replace('%d', saved));

		if (firstError) {
			var msg = firstError;
			if (failed > 1) {
				msg += ' (' + failed + ' image' + (failed > 1 ? 's' : '') + ' failed)';
			}
			$('#sat-bulk-error').text(msg).show();
		}

		setTimeout(function () {
			$('#sat-bulk-progress').hide();
			$('#sat-bulk-error').hide().text('');
			if (saved > 0) loadMediaPage(media.page);
		}, saved > 0 || !firstError ? 2000 : 6000);
	}

	// ── Pagination helpers ───────────────────────────────────────────────────
	function updateMediaCount(total) {
		var label = total === 1 ? '1 image missing an alt tag' : total + ' images missing alt tags';
		$('#sat-media-panel-count').text(label);
	}

	function updateMediaPagination() {
		if (media.pages <= 1) { $('#sat-media-pagination').hide(); return; }
		$('#sat-media-pagination').show();
		$('#sat-page-info').text('Page ' + media.page + ' of ' + media.pages);
		$('#sat-prev-page').prop('disabled', media.page <= 1);
		$('#sat-next-page').prop('disabled', media.page >= media.pages);
	}

	// =========================================================================
	// THEME SCANNER
	// =========================================================================
	function bindThemeEvents() {
		$('#sat-rescan-theme-btn').on('click', function () {
			loadThemeScan(true); // force rescan
		});

		// Toggle file-group collapsing
		$('#sat-theme-results').on('click', '.sat-file-header', function () {
			$(this).closest('.sat-file-group').toggleClass('sat-collapsed');
		});
	}

	// ── Load (or force-rescan) theme results ─────────────────────────────────
	function loadThemeScan(force, callback) {
		var $results = $('#sat-theme-results');
		var $btn     = $('#sat-rescan-theme-btn');

		$results.html('<div class="sat-loading-row"><span class="spinner is-active"></span> ' + SAT.i18n.scanning + '</div>');
		$btn.prop('disabled', true).find('.dashicons').addClass('sat-spin');

		var action = force ? 'sat_rescan_theme' : 'sat_scan_theme';

		$.post(SAT.ajaxUrl, { action: action, nonce: SAT.nonce },
		function (res) {
			$btn.prop('disabled', false).find('.dashicons').removeClass('sat-spin');

			if (!res.success) {
				$results.html('<div class="sat-error-msg">' + esc(res.data ? res.data.message : SAT.i18n.error) + '</div>');
				if (typeof callback === 'function') callback();
				return;
			}

			renderThemeResults(res.data);
			if (typeof callback === 'function') callback();
		}).fail(function () {
			$btn.prop('disabled', false).find('.dashicons').removeClass('sat-spin');
			$results.html('<div class="sat-error-msg">' + SAT.i18n.error + '</div>');
			if (typeof callback === 'function') callback();
		});
	}

	// ── Render theme scan results ─────────────────────────────────────────────
	function renderThemeResults(data) {
		var $results = $('#sat-theme-results');
		var total    = data.total_issues || 0;

		// Update counts
		$('#sat-theme-count').text(total);
		$('#sat-tab-theme-badge').text(total > 0 ? total : '');

		// Update panel header
		var countLabel = total === 0
			? 'No issues found'
			: (total === 1 ? '1 issue in theme templates' : total + ' issues in theme templates');
		$('#sat-theme-panel-count').text(countLabel);

		// Scan time
		if (data.scanned_at) {
			$('#sat-scan-time').text('Last scanned: ' + data.scanned_at);
		}

		if (total === 0) {
			$results.html(
				'<div class="sat-empty"><span class="dashicons dashicons-yes-alt"></span> ' +
				'No alt tag issues found in <strong>' + esc(data.theme_name) + '</strong> templates. ' +
				'(' + (data.files_scanned || 0) + ' files scanned)</div>'
			);
			return;
		}

		var html = '';

		// Build one collapsible group per file
		$.each(data.files, function (filePath, issues) {
			var errorCount   = 0, warnCount = 0, noticeCount = 0;
			$.each(issues, function (i, issue) {
				if (issue.severity === 'error')   errorCount++;
				if (issue.severity === 'warning') warnCount++;
				if (issue.severity === 'notice')  noticeCount++;
			});

			var badgesHtml = '';
			if (errorCount)   badgesHtml += '<span class="sat-badge sat-badge--error">'   + errorCount   + ' error'   + (errorCount   > 1 ? 's' : '') + '</span>';
			if (warnCount)    badgesHtml += '<span class="sat-badge sat-badge--warning">' + warnCount    + ' warning' + (warnCount    > 1 ? 's' : '') + '</span>';
			if (noticeCount)  badgesHtml += '<span class="sat-badge sat-badge--notice">'  + noticeCount  + ' notice'  + (noticeCount  > 1 ? 's' : '') + '</span>';

			html += '<div class="sat-file-group">';
			html += '<div class="sat-file-header">';
			html += '<span class="sat-file-toggle dashicons dashicons-arrow-down-alt2"></span>';
			html += '<code class="sat-file-path">' + esc(filePath) + '</code>';
			html += '<span class="sat-file-badges">' + badgesHtml + '</span>';
			html += '</div>'; // .sat-file-header

			html += '<div class="sat-file-issues">';
			$.each(issues, function (i, issue) {
				html += '<div class="sat-issue sat-issue--' + esc(issue.severity) + '">';
				html += '<span class="sat-issue-line">Line ' + parseInt( issue.line, 10 ) + '</span>';
				html += '<span class="sat-badge sat-badge--' + esc(issue.severity) + '">' + esc(issue.label) + '</span>';
				html += '<code class="sat-issue-snippet">' + esc(issue.snippet) + '</code>';
				html += '</div>';
			});
			html += '</div>'; // .sat-file-issues
			html += '</div>'; // .sat-file-group
		});

		$results.html(html);
	}

	// =========================================================================
	// SHARED HELPERS
	// =========================================================================

	function loadingRow(cols) {
		return '<tr class="sat-loading-row"><td colspan="' + cols + '"><span class="spinner is-active"></span> Loading…</td></tr>';
	}

	function msgRow(cols, msg) {
		return '<tr><td colspan="' + cols + '">' + esc(msg) + '</td></tr>';
	}

	function setRowBusy($btn, icon) {
		$btn.prop('disabled', true).find('.dashicons').attr('class', 'dashicons dashicons-update sat-spin');
	}

	function setRowIdle($btn, icon) {
		$btn.prop('disabled', false).find('.dashicons').attr('class', 'dashicons ' + icon);
	}

	function setStatus($el, msg, cls) {
		$el.text(msg).attr('class', 'sat-status' + (cls ? ' ' + cls : ''));
	}

	function clearStatusAfter($el, ms) {
		setTimeout(function () { $el.text('').attr('class', 'sat-status'); }, ms);
	}

	function esc(str) {
		return $('<div>').text(str == null ? '' : String(str)).html();
	}

	function escAttr(str) {
		return String(str == null ? '' : str)
			.replace(/&/g,  '&amp;')
			.replace(/"/g,  '&quot;')
			.replace(/'/g,  '&#39;')
			.replace(/</g,  '&lt;')
			.replace(/>/g,  '&gt;');
	}

})(jQuery);
