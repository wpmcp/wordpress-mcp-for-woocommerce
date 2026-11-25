/*
 * WPMCP Connection screen. Progressive enhancement: without this script
 * every panel shows, app cards and forms post normally and the password
 * stays visible. With it: ARIA tabs, Copy buttons, the password hidden on
 * screen by default, create and check over AJAX (the server draws the HTML),
 * and step 4 watching for the app for two minutes.
 */
(function () {
	'use strict';

	var settings = window.wpmcpConnection;
	var root = document.querySelector('.wpmcp-connection');
	if (!root || !settings) {
		return;
	}
	var i18n = settings.i18n;
	root.classList.add('is-enhanced');

	var live = document.createElement('div');
	live.className = 'screen-reader-text';
	live.setAttribute('aria-live', 'polite');
	live.setAttribute('aria-atomic', 'true');
	root.appendChild(live);

	function announce(message) {
		live.textContent = '';
		window.setTimeout(function () {
			live.textContent = message;
		}, 60);
	}

	function reducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	/* ---------- Tabs: the ARIA tabs pattern over the existing links ---------- */

	var tablist = root.querySelector('.wpmcp-tabs');
	var tabs = Array.prototype.slice.call(root.querySelectorAll('.wpmcp-tab'));
	var panels = Array.prototype.slice.call(root.querySelectorAll('.wpmcp-connection-panel'));

	tablist.setAttribute('role', 'tablist');
	tabs.forEach(function (tab) {
		tab.setAttribute('role', 'tab');
		tab.id = 'wpmcp-tablink-' + tab.dataset.tab;
		tab.setAttribute('aria-controls', 'wpmcp-tab-' + tab.dataset.tab);
	});
	panels.forEach(function (panel) {
		panel.setAttribute('role', 'tabpanel');
		panel.setAttribute('aria-labelledby', 'wpmcp-tablink-' + panel.dataset.tab);
	});

	function show(name) {
		if (!panels.some(function (panel) { return panel.dataset.tab === name; })) {
			name = 'connect';
		}
		tabs.forEach(function (tab) {
			var on = tab.dataset.tab === name;
			tab.setAttribute('aria-selected', on ? 'true' : 'false');
			tab.setAttribute('tabindex', on ? '0' : '-1');
		});
		panels.forEach(function (panel) {
			panel.hidden = panel.dataset.tab !== name;
		});
		return name;
	}

	function select(name, focusTab) {
		name = show(name);
		if (window.history && window.history.replaceState) {
			window.history.replaceState(null, '', '#wpmcp-tab-' + name);
		}
		if (focusTab) {
			document.getElementById('wpmcp-tablink-' + name).focus();
		}
	}

	tablist.addEventListener('click', function (event) {
		var tab = event.target.closest('.wpmcp-tab');
		if (!tab) {
			return;
		}
		event.preventDefault();
		select(tab.dataset.tab, false);
	});

	tablist.addEventListener('keydown', function (event) {
		var index = tabs.indexOf(document.activeElement);
		var next = -1;
		if (index < 0) {
			return;
		}
		if ('ArrowRight' === event.key) {
			next = (index + 1) % tabs.length;
		} else if ('ArrowLeft' === event.key) {
			next = (index + tabs.length - 1) % tabs.length;
		} else if ('Home' === event.key) {
			next = 0;
		} else if ('End' === event.key) {
			next = tabs.length - 1;
		}
		if (next < 0) {
			return;
		}
		event.preventDefault();
		select(tabs[next].dataset.tab, true);
	});

	// Links elsewhere on the page to a tab (the MCP chip, the empty state).
	root.addEventListener('click', function (event) {
		var link = event.target.closest('a[href^="#wpmcp-tab-"]');
		if (!link || link.classList.contains('wpmcp-tab')) {
			return;
		}
		event.preventDefault();
		select(link.getAttribute('href').slice('#wpmcp-tab-'.length), true);
	});

	// Back, forward and links to a tab on the same page change only the hash.
	window.addEventListener('hashchange', function () {
		var hash = window.location.hash;
		if (0 === hash.indexOf('#wpmcp-tab-')) {
			show(hash.slice('#wpmcp-tab-'.length));
		}
	});

	(function openFromHash() {
		var hash = window.location.hash;
		if (0 === hash.indexOf('#wpmcp-tab-')) {
			show(hash.slice('#wpmcp-tab-'.length));
			return;
		}
		show('connect');
		if (0 !== hash.indexOf('#wpmcp-step-')) {
			return;
		}
		// The browser scrolls to the fragment after the page loads, which
		// moves focus back to the body, so the step's heading takes focus
		// once that is done.
		var focusStep = function () {
			var heading = document.getElementById(hash.slice(1) + '-title');
			if (heading) {
				heading.focus({ preventScroll: true });
				heading.scrollIntoView({ block: 'start', behavior: reducedMotion() ? 'auto' : 'smooth' });
			}
		};
		if ('complete' === document.readyState) {
			window.setTimeout(focusStep, 0);
		} else {
			window.addEventListener('load', function () {
				window.setTimeout(focusStep, 0);
			});
		}
	}());

	/* ---------- Copy, and the password hidden on screen ---------- */

	function enhance(scope) {
		Array.prototype.forEach.call(scope.querySelectorAll('.wpmcp-copy, .wpmcp-reveal, [data-wpmcp-secret-note]'), function (el) {
			el.hidden = false;
		});
		Array.prototype.forEach.call(scope.querySelectorAll('[data-wpmcp-code]'), function (block) {
			if (block.querySelector('.wpmcp-secret')) {
				mask(block, true);
			}
		});
	}

	function mask(block, hidden) {
		block.classList.toggle('is-masked', hidden);
		Array.prototype.forEach.call(block.querySelectorAll('.wpmcp-secret'), function (secret) {
			secret.setAttribute('aria-hidden', hidden ? 'true' : 'false');
			var label = secret.nextElementSibling && secret.nextElementSibling.classList.contains('wpmcp-secret-label') ? secret.nextElementSibling : null;
			if (hidden && !label) {
				label = document.createElement('span');
				label.className = 'screen-reader-text wpmcp-secret-label';
				label.textContent = i18n.hidden;
				secret.parentNode.insertBefore(label, secret.nextSibling);
			} else if (!hidden && label) {
				label.parentNode.removeChild(label);
			}
		});
		var button = block.querySelector('[data-wpmcp-reveal]');
		if (button) {
			button.setAttribute('aria-pressed', hidden ? 'false' : 'true');
		}
		var note = block.nextElementSibling;
		if (note && note.hasAttribute('data-wpmcp-secret-note')) {
			note.lastChild.textContent = hidden ? i18n.passwordHidden : i18n.passwordShown;
		}
	}

	/*
	 * The text comes from the button (data-wpmcp-copy-text, drawn by the
	 * server with the block), never from what the block shows: while the
	 * password is hidden, the block also holds the screen-reader label
	 * "password hidden" (#1260). The label the button had before the first
	 * copy is kept, so a second click within the "Copied" time puts it back.
	 */
	function copyText(button) {
		var text = button.getAttribute('data-wpmcp-copy-text');
		if (null === text) {
			return;
		}
		var copied = button.getAttribute('data-wpmcp-copied') || i18n.copiedLong;
		var label = button.querySelector('.wpmcp-copy__label');
		if (label && !button.hasAttribute('data-wpmcp-label')) {
			button.setAttribute('data-wpmcp-label', label.textContent);
		}
		function done() {
			button.classList.add('is-copied');
			if (label) {
				label.textContent = i18n.copied;
			}
			announce(copied);
			window.setTimeout(function () {
				button.classList.remove('is-copied');
				if (label) {
					label.textContent = button.getAttribute('data-wpmcp-label');
				}
			}, 1800);
		}
		function fallback() {
			var area = document.createElement('textarea');
			area.value = text;
			area.setAttribute('readonly', '');
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild(area);
			area.select();
			var ok = false;
			try {
				ok = document.execCommand('copy');
			} catch (e) {
				ok = false;
			}
			document.body.removeChild(area);
			if (ok) {
				done();
			} else {
				announce(i18n.copyFailed);
			}
		}
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done, fallback);
		} else {
			fallback();
		}
	}

	/*
	 * Copying by hand (a selection and Ctrl+C or Cmd+C) inside one code
	 * block leaves out the "password hidden" label, so it copies what the
	 * block holds, as Copy does. Any other selection is the browser's.
	 */
	document.addEventListener('copy', function (event) {
		var selection = window.getSelection();
		if (!event.clipboardData || !selection || 1 !== selection.rangeCount || selection.isCollapsed) {
			return;
		}
		var range = selection.getRangeAt(0);
		var node = range.commonAncestorContainer;
		var within = 1 === node.nodeType ? node : node.parentElement;
		var pre = within ? within.closest('[data-wpmcp-code] pre') : null;
		if (!pre || !root.contains(pre) || !pre.querySelector('.wpmcp-secret-label')) {
			return;
		}
		var part = range.cloneContents();
		Array.prototype.forEach.call(part.querySelectorAll('.wpmcp-secret-label'), function (label) {
			label.parentNode.removeChild(label);
		});
		event.clipboardData.setData('text/plain', part.textContent);
		event.preventDefault();
	});

	root.addEventListener('click', function (event) {
		var copy = event.target.closest('.wpmcp-copy');
		if (copy) {
			event.preventDefault();
			copyText(copy);
			return;
		}
		var reveal = event.target.closest('[data-wpmcp-reveal]');
		if (reveal) {
			event.preventDefault();
			var block = reveal.closest('[data-wpmcp-code]');
			var hide = !block.classList.contains('is-masked');
			mask(block, hide);
			announce(hide ? i18n.passwordHidden : i18n.passwordShown);
			return;
		}
		var variant = event.target.closest('[data-wpmcp-variants] a[data-variant]');
		if (variant) {
			event.preventDefault();
			var nav = variant.closest('[data-wpmcp-variants]');
			Array.prototype.forEach.call(nav.querySelectorAll('a[data-variant]'), function (link) {
				if (link === variant) {
					link.setAttribute('aria-current', 'true');
				} else {
					link.removeAttribute('aria-current');
				}
			});
			Array.prototype.forEach.call(root.querySelectorAll('[data-variant-panel]'), function (panel) {
				panel.classList.toggle('is-inactive', panel.dataset.variantPanel !== variant.dataset.variant);
			});
		}
	});

	/* ---------- AJAX ---------- */

	function post(action, form, extra) {
		var body = new FormData(form);
		body.set('action', action);
		Object.keys(extra || {}).forEach(function (key) {
			body.set(key, extra[key]);
		});
		return window.fetch(settings.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (response) {
				return response.json();
			});
	}

	function busy(button, text) {
		button.disabled = true;
		button.dataset.label = button.textContent;
		button.textContent = text;
	}

	function idle(button, text) {
		button.disabled = false;
		button.textContent = text || button.dataset.label || button.textContent;
	}

	function inlineError(container, message) {
		var notice = container.querySelector('.wpmcp-inline-error');
		if (!notice) {
			notice = document.createElement('div');
			notice.className = 'notice notice-error inline wpmcp-inline-error';
			notice.setAttribute('role', 'alert');
			notice.appendChild(document.createElement('p'));
			container.appendChild(notice);
		}
		notice.firstChild.textContent = message;
	}

	/* Create: the server draws the four steps with the new password in them. */
	root.addEventListener('submit', function (event) {
		var form = event.target.closest('[data-wpmcp-create]');
		if (!form) {
			return;
		}
		event.preventDefault();
		var button = form.querySelector('[data-wpmcp-create-button]');
		busy(button, i18n.creating);
		post(settings.actions.create, form).then(function (json) {
			if (!json || !json.success) {
				idle(button);
				inlineError(form, json && json.data && json.data.message ? json.data.message : i18n.failed);
				return;
			}
			var steps = document.getElementById('wpmcp-steps');
			var holder = document.createElement('div');
			holder.innerHTML = json.data.html;
			var fresh = holder.firstElementChild;
			steps.parentNode.replaceChild(fresh, steps);
			enhance(fresh);
			var title = document.getElementById('wpmcp-key-title');
			if (title) {
				title.focus({ preventScroll: true });
				title.scrollIntoView({ block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' });
			}
			announce(i18n.created);
			startWatching();
		}, function () {
			idle(button);
			inlineError(form, i18n.failed);
		});
	});

	/* Check: on click, and every few seconds for two minutes after step 3 opens. */
	var watch = { timer: 0, until: 0, last: '' };

	function checkForm() {
		return root.querySelector('[data-wpmcp-check]');
	}

	function markDone() {
		Array.prototype.forEach.call(root.querySelectorAll('.wpmcp-step[data-step="add"], .wpmcp-step[data-step="check"]'), function (step) {
			step.className = 'wpmcp-step is-done';
			step.dataset.state = 'done';
			step.removeAttribute('aria-current');
			var marker = step.querySelector('.wpmcp-marker');
			if (marker) {
				marker.innerHTML = '<span class="dashicons dashicons-yes"></span>';
			}
		});
	}

	function runCheck(options) {
		var form = checkForm();
		if (!form) {
			return;
		}
		var button = form.querySelector('[data-wpmcp-check-button]');
		var region = form.querySelector('[data-wpmcp-result]');
		if (options.manual) {
			busy(button, i18n.checking);
		}
		post(settings.actions.check, form, { selftest: options.selftest ? '1' : '0' }).then(function (json) {
			if (options.manual) {
				idle(button, i18n.checkAgain);
			}
			if (!json || !json.success) {
				if (options.manual) {
					inlineError(region, json && json.data && json.data.message ? json.data.message : i18n.failed);
				}
				return;
			}
			// While watching, the result only changes when the verdict does,
			// so a screen reader hears each change once.
			if (options.manual || json.data.done || json.data.verdict !== watch.last || options.final) {
				region.innerHTML = json.data.html;
				watch.last = json.data.verdict;
			}
			if (json.data.done) {
				stopWatching(false);
				button.classList.remove('button-primary');
				button.textContent = i18n.checkAgain;
				markDone();
			}
		}, function () {
			if (options.manual) {
				idle(button);
				inlineError(region, i18n.failed);
			}
		});
	}

	function status(text, pulse) {
		var form = checkForm();
		var span = form ? form.querySelector('[data-wpmcp-watch]') : null;
		if (!span) {
			return;
		}
		span.hidden = '' === text;
		span.innerHTML = pulse ? '<span class="wpmcp-pulse" aria-hidden="true"><i></i><i></i><i></i></span>' : '';
		span.appendChild(document.createTextNode(text));
	}

	function tick() {
		if (document.hidden) {
			return;
		}
		if (Date.now() >= watch.until) {
			stopWatching(true);
			return;
		}
		runCheck({ manual: false, selftest: false });
	}

	function startWatching() {
		var form = checkForm();
		if (!form || '1' !== form.dataset.watch) {
			return;
		}
		stopWatching(false);
		watch.until = Date.now() + settings.watch.for * 1000;
		watch.last = '';
		status(i18n.watching.replace('%s', form.dataset.name), true);
		watch.timer = window.setInterval(tick, settings.watch.every * 1000);
	}

	function stopWatching(timedOut) {
		if (watch.timer) {
			window.clearInterval(watch.timer);
			watch.timer = 0;
		}
		if (timedOut) {
			status(i18n.watchEnded, false);
			runCheck({ manual: false, selftest: true, final: true });
		} else {
			status('', false);
		}
	}

	root.addEventListener('submit', function (event) {
		var form = event.target.closest('[data-wpmcp-check]');
		if (!form) {
			return;
		}
		event.preventDefault();
		runCheck({ manual: true, selftest: true });
	});

	document.addEventListener('visibilitychange', function () {
		if (!document.hidden && watch.timer) {
			tick();
		}
	});

	enhance(root);
	startWatching();
}());
