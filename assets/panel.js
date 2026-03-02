/**
 * Script Report panel: open from admin bar, switch tabs, close.
 *
 * @package Script_Report
 */

(function () {
	'use strict';

	var main = document.getElementById('script-report-main');
	if (!main) {
		return;
	}

	var panelMenu = document.getElementById('sr-panel-menu');
	var tabs = main.querySelectorAll('.sr-tab');
	var panels = main.querySelectorAll('.sr-panel');
	var closeBtn = main.querySelector('.sr-close');
	var resizeHandle = main.querySelector('.sr-resize-handle');

	// Panel resize functionality
	var isResizing = false;
	var startY = 0;
	var startHeight = 0;

	function startResize(e) {
		if (e.target !== resizeHandle) {
			return;
		}
		isResizing = true;
		startY = e.clientY;
		startHeight = main.offsetHeight;
		main.classList.add('sr-resizing');
		document.body.style.cursor = 'ns-resize';
		e.preventDefault();
	}

	function doResize(e) {
		if (!isResizing) {
			return;
		}
		var deltaY = startY - e.clientY;
		var newHeight = startHeight + deltaY;
		var minHeight = 150;
		var maxHeight = window.innerHeight * 0.9;

		if (newHeight < minHeight) {
			newHeight = minHeight;
		} else if (newHeight > maxHeight) {
			newHeight = maxHeight;
		}

		main.style.height = newHeight + 'px';
		e.preventDefault();
	}

	function stopResize() {
		if (isResizing) {
			isResizing = false;
			main.classList.remove('sr-resizing');
			document.body.style.cursor = '';
		}
	}

	if (resizeHandle) {
		resizeHandle.addEventListener('mousedown', startResize);
		document.addEventListener('mousemove', doResize);
		document.addEventListener('mouseup', stopResize);
	}

	function showPanel(panelId) {
		var target = typeof panelId === 'string' ? document.querySelector(panelId) : panelId;
		if (!target || !target.id) {
			return;
		}
		var targetHash = '#' + target.id;
		panels.forEach(function (p) {
			p.classList.remove('sr-panel-show');
		});
		tabs.forEach(function (t) {
			t.setAttribute('aria-selected', t.getAttribute('data-sr-panel') === targetHash ? 'true' : 'false');
		});
		target.classList.add('sr-panel-show');
	}

	function openPanel(panelId) {
		main.classList.add('sr-show');
		main.setAttribute('aria-hidden', 'false');
		showPanel(panelId || '#sr-overview');
	}

	function closePanel() {
		main.classList.remove('sr-show');
		main.setAttribute('aria-hidden', 'true');
	}

	if (panelMenu) {
		panelMenu.addEventListener('click', function (e) {
			var tab = e.target.closest('.sr-tab');
			if (!tab) {
				return;
			}
			var panelId = tab.getAttribute('data-sr-panel');
			if (panelId) {
				e.preventDefault();
				showPanel(panelId);
			}
		});
	}

	if (closeBtn) {
		closeBtn.addEventListener('click', closePanel);
	}

	// Handle "See X more" links in overview panel
	var seeMoreLinks = main.querySelectorAll('.sr-see-more a');
	seeMoreLinks.forEach(function (link) {
		link.addEventListener('click', function (e) {
			e.preventDefault();
			var panelId = link.getAttribute('data-sr-panel');
			if (panelId) {
				showPanel(panelId);
			}
		});
	});

	// Source filter and search in panel tabs
	function initPanelFilters(panelEl) {
		var toolbar = panelEl.querySelector('.report-toolbar');
		if (!toolbar) {
			return;
		}

		var input = toolbar.querySelector('input.filter');
		var select = toolbar.querySelector('.sr-source-filter');
		var list = panelEl.querySelector('.list-view');
		var items = list ? list.querySelectorAll('.list-item') : [];
		var statsEl = panelEl.querySelector('.stats');

		// Store original stats HTML so we can restore when filter is cleared
		var originalStatsHTML = statsEl ? statsEl.innerHTML : '';

		// Store the original strong label text for the loaded and size stats
		var loadedLabel = '';
		var sizeLabel = '';
		if (statsEl) {
			var statsItems = statsEl.querySelectorAll('.stats-item');
			if (statsItems.length >= 3) {
				var s = statsItems[2].querySelector('strong');
				if (s) loadedLabel = s.outerHTML;
			}
			if (statsItems.length >= 4) {
				var s2 = statsItems[3].querySelector('strong');
				if (s2) sizeLabel = s2.outerHTML;
			}
		}

		function applyFilters() {
			var q = input ? (input.value || '').toLowerCase() : '';
			var source = select ? select.value : '';
			var visibleCount = 0;
			var visibleEnqueued = 0;
			var visibleSize = 0;

			for (var i = 0; i < items.length; i++) {
				var el = items[i];
				var matchesSearch = !q || el.textContent.toLowerCase().indexOf(q) !== -1;
				var matchesSource = !source || el.getAttribute('data-sr-source') === source;
				var hidden = !matchesSearch || !matchesSource;
				el.classList.toggle('hidden', hidden);
				if (!hidden) {
					visibleCount++;
					visibleSize += parseInt(el.getAttribute('data-sr-size') || '0', 10);
					if (el.getAttribute('data-sr-enqueued') === '1') visibleEnqueued++;
				}
			}

			if (statsEl) {
				if (!source && !q) {
					statsEl.innerHTML = originalStatsHTML;
				} else {
					var currentItems = statsEl.querySelectorAll('.stats-item');
					if (currentItems.length >= 3 && loadedLabel) {
						currentItems[2].innerHTML = loadedLabel + ' ' + visibleCount + ' <span class="meta">filtered</span>';
					}
					if (currentItems.length >= 4 && sizeLabel) {
						currentItems[3].innerHTML = sizeLabel + ' ' + formatBytes(visibleSize);
					}
				}
			}
		}

		if (input) input.addEventListener('input', applyFilters);
		if (select) select.addEventListener('change', applyFilters);
	}

	function formatBytes(bytes) {
		if (bytes < 1024) return bytes + ' B';
		if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
		return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
	}

	// Initialize filters for scripts and styles panels
	var scriptPanel = document.getElementById('sr-scripts');
	var stylePanel = document.getElementById('sr-styles');
	if (scriptPanel) initPanelFilters(scriptPanel);
	if (stylePanel) initPanelFilters(stylePanel);

	// Handle all admin bar links for script report
	var adminBarNode = document.getElementById('wp-admin-bar-script-report');
	if (adminBarNode) {
		// Handle main toggle link
		var mainLink = adminBarNode.querySelector('a');
		if (mainLink && mainLink.getAttribute('href') === '#sr-overview') {
			mainLink.addEventListener('click', function (e) {
				e.preventDefault();
				if (main.classList.contains('sr-show')) {
					closePanel();
				} else {
					openPanel('#sr-overview');
				}
			});
		}

		// Handle submenu links (Overview, JavaScript, CSS)
		var subMenuLinks = adminBarNode.querySelectorAll('.ab-sub-wrapper a');
		subMenuLinks.forEach(function (link) {
			var href = link.getAttribute('href');
			if (href && href.indexOf('#sr-') === 0) {
				link.addEventListener('click', function (e) {
					e.preventDefault();
					openPanel(href);
				});
			}
		});
	}
})();
