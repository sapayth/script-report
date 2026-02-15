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
