/**
 * QuickJump Admin - Frontend JavaScript
 *
 * Handles dropdown toggle, search, pin/unpin, and AJAX interactions.
 *
 * @package QuickJump_Admin
 */

/* global quickjumpAdmin, jQuery */

(function ($) {
	'use strict';

	/**
	 * QuickJump Admin JavaScript Controller
	 */
	const QuickJump = {
		/**
		 * Search timeout reference
		 */
		searchTimeout: null,

		/**
		 * Dropdown element
		 */
		$dropdown: null,

		/**
		 * Trigger element
		 */
		$trigger: null,

		/**
		 * Initialize the module
		 */
		init: function () {
			this.$dropdown = $('#quickjump-dropdown');
			this.$trigger = $('#wp-admin-bar-quickjump-admin');

			if (!this.$dropdown.length || !this.$trigger.length) {
				return;
			}

			this.positionDropdown();
			this.bindEvents();
			this.updateRelativeTimes();

			// Update relative times every minute
			setInterval(this.updateRelativeTimes.bind(this), 60000);
		},

		/**
		 * Position dropdown under the trigger button
		 */
		positionDropdown: function () {
			const self = this;

			function updatePosition() {
				const triggerOffset = self.$trigger.offset();
				const triggerWidth = self.$trigger.outerWidth();
				const dropdownWidth = self.$dropdown.outerWidth();
				const windowWidth = $(window).width();

				let left = triggerOffset.left;

				// Prevent dropdown from going off-screen to the right
				if (left + dropdownWidth > windowWidth) {
					left = windowWidth - dropdownWidth - 10;
				}

				// Prevent going off-screen to the left
				if (left < 0) {
					left = 10;
				}

				self.$dropdown.css({
					left: left + 'px'
				});
			}

			updatePosition();
			$(window).on('resize', updatePosition);
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			const self = this;

			// Show dropdown on hover
			this.$trigger.on('mouseenter', function () {
				self.showDropdown();
			});

			// Hide dropdown when mouse leaves both trigger and dropdown
			this.$trigger.on('mouseleave', function (e) {
				// Check if mouse is moving to dropdown
				setTimeout(function () {
					if (!self.$dropdown.is(':hover') && !self.$trigger.is(':hover')) {
						self.hideDropdown();
					}
				}, 100);
			});

			this.$dropdown.on('mouseleave', function () {
				// Check if mouse is moving to trigger
				setTimeout(function () {
					if (!self.$dropdown.is(':hover') && !self.$trigger.is(':hover')) {
						self.hideDropdown();
					}
				}, 100);
			});

			// Keep dropdown open while hovering over it
			this.$dropdown.on('mouseenter', function () {
				self.showDropdown();
			});

			// Search input
			$(document).on('input', '#quickjump-search', function () {
				self.handleSearch($(this).val());
			});

			// Clear search on escape
			$(document).on('keydown', '#quickjump-search', function (e) {
				if (e.key === 'Escape') {
					$(this).val('');
					self.handleSearch('');
				}
			});

			// Pin/unpin button - use dropdown delegation to avoid stopPropagation issue
			this.$dropdown.on('click', '.quickjump-pin-btn', function (e) {
				e.preventDefault();
				e.stopPropagation();
				self.togglePin($(this));
			});

			// Edit/rename button
			this.$dropdown.on('click', '.quickjump-edit-btn', function (e) {
				e.preventDefault();
				e.stopPropagation();
				self.startRename($(this));
			});

			// Hide button - add URL to excluded patterns
			this.$dropdown.on('click', '.quickjump-hide-btn', function (e) {
				e.preventDefault();
				e.stopPropagation();
				self.hideLink($(this));
			});

			// Prevent clicks inside dropdown from closing it (except pin buttons which are handled above)
			this.$dropdown.on('click', function (e) {
				e.stopPropagation();
			});

			// Close dropdown when clicking outside
			$(document).on('click', function (e) {
				if (!self.$trigger.is(e.target) &&
					!self.$trigger.has(e.target).length &&
					!self.$dropdown.is(e.target) &&
					!self.$dropdown.has(e.target).length) {
					self.hideDropdown();
				}
			});
		},

		/**
		 * Show the dropdown
		 */
		showDropdown: function () {
			this.positionDropdown();
			this.$dropdown.stop().fadeIn(150);
		},

		/**
		 * Hide the dropdown
		 */
		hideDropdown: function () {
			this.$dropdown.stop().fadeOut(100);
		},

		/**
		 * Handle search input
		 *
		 * @param {string} query Search query
		 */
		handleSearch: function (query) {
			const self = this;
			const $results = $('#quickjump-search-results');
			const $resultsList = $('#quickjump-search-results-list');
			const $mainContent = $('#quickjump-main-content');

			// Clear previous timeout
			if (this.searchTimeout) {
				clearTimeout(this.searchTimeout);
			}

			// If query is empty, show main content
			if (query.trim().length < 2) {
				$results.hide();
				$mainContent.show();
				return;
			}

			// Show results container, hide main content
			$results.show();
			$mainContent.hide();

			// Show loading state
			$resultsList.html('<li class="quickjump-loading">' + quickjumpAdmin.i18n.searching + '</li>');

			// Debounce the search
			this.searchTimeout = setTimeout(function () {
				self.performSearch(query);
			}, 300);
		},

		/**
		 * Perform AJAX search
		 *
		 * @param {string} query Search query
		 */
		performSearch: function (query) {
			const self = this;
			const $resultsList = $('#quickjump-search-results-list');

			$.ajax({
				url: quickjumpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'quickjump_search',
					nonce: quickjumpAdmin.nonce,
					search: query
				},
				success: function (response) {
					if (response.success && response.data.results.length > 0) {
						self.renderSearchResults(response.data.results);
					} else {
						$resultsList.html('<li class="quickjump-empty">' + quickjumpAdmin.i18n.noResults + '</li>');
					}
				},
				error: function () {
					$resultsList.html('<li class="quickjump-empty">' + quickjumpAdmin.i18n.noResults + '</li>');
				}
			});
		},

		/**
		 * Render search results
		 *
		 * @param {Array} results Search results
		 */
		renderSearchResults: function (results) {
			const $resultsList = $('#quickjump-search-results-list');
			let html = '';

			results.forEach(function (link) {
				const iconClass = QuickJump.getIconClass(link.url);
				const pinnedClass = link.is_pinned == 1 ? ' is-pinned' : '';
				const pinIcon = link.is_pinned == 1 ? 'dashicons-star-filled' : 'dashicons-star-empty';
				const pinTitle = link.is_pinned == 1 ? 'Unpin' : 'Pin';

				html += '<li class="quickjump-link-item' + pinnedClass + '" data-id="' + link.id + '">';
				html += '<a href="' + QuickJump.escapeHtml(link.url) + '" class="quickjump-link">';
				html += '<span class="quickjump-link-icon dashicons ' + iconClass + '"></span>';
				html += '<span class="quickjump-link-title">' + QuickJump.escapeHtml(link.page_title) + '</span>';
				html += '<span class="quickjump-link-count">' + link.access_count + '</span>';
				html += '</a>';
				html += '<button type="button" class="quickjump-pin-btn" data-id="' + link.id + '" title="' + pinTitle + '">';
				html += '<span class="dashicons ' + pinIcon + '"></span>';
				html += '</button>';
				html += '</li>';
			});

			$resultsList.html(html);
		},

		/**
		 * Toggle pin status for a link
		 *
		 * @param {jQuery} $btn Pin button element
		 */
		togglePin: function ($btn) {
			const linkId = $btn.attr('data-id');
			const $item = $btn.closest('.quickjump-link-item');
			const $icon = $btn.find('.dashicons');
			const isPinned = $item.hasClass('is-pinned');

			// Validate we have a link ID
			if (!linkId) {
				console.error('QuickJump: No link ID found on pin button');
				return;
			}

			// Optimistic UI update
			$item.toggleClass('is-pinned');
			$icon.toggleClass('dashicons-star-empty dashicons-star-filled');

			$.ajax({
				url: quickjumpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'quickjump_toggle_pin',
					nonce: quickjumpAdmin.nonce,
					link_id: linkId
				},
				success: function (response) {
					if (!response.success) {
						// Revert on failure
						$item.toggleClass('is-pinned');
						$icon.toggleClass('dashicons-star-empty dashicons-star-filled');
						console.error('QuickJump: Pin toggle failed', response);
					}
				},
				error: function (xhr, status, error) {
					// Revert on error
					$item.toggleClass('is-pinned');
					$icon.toggleClass('dashicons-star-empty dashicons-star-filled');
					console.error('QuickJump: Pin toggle error', error);
				}
			});
		},

		/**
		 * Start inline rename for a link
		 *
		 * @param {jQuery} $btn Edit button element
		 */
		startRename: function ($btn) {
			const linkId = $btn.attr('data-id');
			const currentTitle = $btn.attr('data-title');
			const $item = $btn.closest('.quickjump-link-item');
			const $titleSpan = $item.find('.quickjump-link-title');

			// Don't start if already editing
			if ($item.hasClass('is-editing')) {
				return;
			}

			$item.addClass('is-editing');

			// Create input field
			const $input = $('<input type="text" class="quickjump-rename-input">')
				.val(currentTitle)
				.attr('data-id', linkId)
				.attr('data-original', currentTitle);

			// Replace title with input
			$titleSpan.hide().after($input);
			$input.focus().select();

			// Save on enter, cancel on escape
			$input.on('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					QuickJump.saveRename($input);
				} else if (e.key === 'Escape') {
					e.preventDefault();
					QuickJump.cancelRename($input);
				}
			});

			// Save on blur
			$input.on('blur', function () {
				// Small delay to allow click events to fire first
				setTimeout(function () {
					if ($input.is(':visible')) {
						QuickJump.saveRename($input);
					}
				}, 100);
			});
		},

		/**
		 * Save renamed link title
		 *
		 * @param {jQuery} $input Input element
		 */
		saveRename: function ($input) {
			const linkId = $input.attr('data-id');
			const originalTitle = $input.attr('data-original');
			const newTitle = $input.val().trim();
			const $item = $input.closest('.quickjump-link-item');
			const $titleSpan = $item.find('.quickjump-link-title');

			// If empty or unchanged, cancel
			if (!newTitle || newTitle === originalTitle) {
				this.cancelRename($input);
				return;
			}

			// Update UI immediately
			$titleSpan.text(newTitle).show();
			$input.remove();
			$item.removeClass('is-editing');

			// Update the edit button's data-title
			$item.find('.quickjump-edit-btn').attr('data-title', newTitle);

			// Save via AJAX
			$.ajax({
				url: quickjumpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'quickjump_rename_link',
					nonce: quickjumpAdmin.nonce,
					link_id: linkId,
					title: newTitle
				},
				success: function (response) {
					if (!response.success) {
						// Revert on failure
						$titleSpan.text(originalTitle);
						$item.find('.quickjump-edit-btn').attr('data-title', originalTitle);
						console.error('QuickJump: Rename failed', response);
					}
				},
				error: function (xhr, status, error) {
					// Revert on error
					$titleSpan.text(originalTitle);
					$item.find('.quickjump-edit-btn').attr('data-title', originalTitle);
					console.error('QuickJump: Rename error', error);
				}
			});
		},

		/**
		 * Cancel rename operation
		 *
		 * @param {jQuery} $input Input element
		 */
		cancelRename: function ($input) {
			const $item = $input.closest('.quickjump-link-item');
			const $titleSpan = $item.find('.quickjump-link-title');

			$titleSpan.show();
			$input.remove();
			$item.removeClass('is-editing');
		},

		/**
		 * Hide a link by adding its URL to excluded patterns
		 *
		 * @param {jQuery} $button Hide button element
		 */
		hideLink: function ($button) {
			const url = $button.attr('data-url');
			const $item = $button.closest('.quickjump-link-item');

			if (!url) {
				console.error('QuickJump: No URL found for hide');
				return;
			}

			// Fade out and remove immediately
			$item.fadeOut(200, function () {
				$(this).remove();
			});

			// Save via AJAX
			$.ajax({
				url: quickjumpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'quickjump_hide_link',
					nonce: quickjumpAdmin.nonce,
					url: url
				},
				success: function (response) {
					if (!response.success) {
						console.error('QuickJump: Hide failed', response);
					}
				},
				error: function (xhr, status, error) {
					console.error('QuickJump: Hide AJAX error', error);
				}
			});
		},

		/**
		 * Update relative time displays
		 */
		updateRelativeTimes: function () {
			$('.quickjump-link-time[data-time]').each(function () {
				const $el = $(this);
				const timestamp = $el.data('time');
				$el.text(QuickJump.formatRelativeTime(timestamp));
			});
		},

		/**
		 * Format timestamp as relative time
		 *
		 * @param {string} timestamp MySQL timestamp
		 * @return {string} Relative time string
		 */
		formatRelativeTime: function (timestamp) {
			const date = new Date(timestamp.replace(' ', 'T'));
			const now = new Date();
			const diffMs = now - date;
			const diffMins = Math.floor(diffMs / 60000);
			const diffHours = Math.floor(diffMs / 3600000);
			const diffDays = Math.floor(diffMs / 86400000);

			if (diffMins < 1) {
				return quickjumpAdmin.i18n.justNow;
			}

			if (diffMins < 60) {
				return diffMins === 1
					? quickjumpAdmin.i18n.minAgo.replace('%d', diffMins)
					: quickjumpAdmin.i18n.minsAgo.replace('%d', diffMins);
			}

			if (diffHours < 24) {
				return diffHours === 1
					? quickjumpAdmin.i18n.hourAgo.replace('%d', diffHours)
					: quickjumpAdmin.i18n.hoursAgo.replace('%d', diffHours);
			}

			if (diffDays < 7) {
				return diffDays === 1
					? quickjumpAdmin.i18n.dayAgo.replace('%d', diffDays)
					: quickjumpAdmin.i18n.daysAgo.replace('%d', diffDays);
			}

			// Return formatted date for older items
			return date.toLocaleDateString();
		},

		/**
		 * Get appropriate icon class for a URL
		 *
		 * @param {string} url Page URL
		 * @return {string} Dashicon class
		 */
		getIconClass: function (url) {
			if (url.includes('edit.php')) {
				if (url.includes('post_type=page')) {
					return 'dashicons-admin-page';
				}
				if (url.includes('post_type=attachment')) {
					return 'dashicons-admin-media';
				}
				return 'dashicons-admin-post';
			}

			if (url.includes('index.php')) return 'dashicons-dashboard';
			if (url.includes('upload.php')) return 'dashicons-admin-media';
			if (url.includes('edit-comments.php')) return 'dashicons-admin-comments';
			if (url.includes('themes.php')) return 'dashicons-admin-appearance';
			if (url.includes('plugins.php')) return 'dashicons-admin-plugins';
			if (url.includes('users.php')) return 'dashicons-admin-users';
			if (url.includes('tools.php')) return 'dashicons-admin-tools';
			if (url.includes('options-')) return 'dashicons-admin-settings';
			if (url.includes('profile.php')) return 'dashicons-admin-users';
			if (url.includes('nav-menus.php')) return 'dashicons-menu';

			return 'dashicons-admin-page';
		},

		/**
		 * Escape HTML entities
		 *
		 * @param {string} text Text to escape
		 * @return {string} Escaped text
		 */
		escapeHtml: function (text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}
	};

	/**
	 * QuickJump Settings Page Controller
	 */
	const QuickJumpSettings = {
		init: function () {
			const $tabs = $('.nav-tab-wrapper .nav-tab');
			if (!$tabs.length) return;

			$tabs.on('click', function (e) {
				e.preventDefault();
				const $this = $(this);
				const tab = $this.data('tab');

				// Update active tab
				$('.nav-tab-active').removeClass('nav-tab-active');
				$this.addClass('nav-tab-active');

				// Update visible content
				$('.qj-tab-content').removeClass('active');
				$('.qj-tab-content[data-tab="' + tab + '"]').addClass('active');

				// Update URL if supported
				if (history.pushState) {
					const newUrl = new URL(window.location);
					newUrl.searchParams.set('tab', tab);
					history.pushState(null, '', newUrl);
				}
			});
		}
	};

	// Initialize when DOM is ready
	$(document).ready(function () {
		QuickJump.init();
		QuickJumpSettings.init();
	});

})(jQuery);
