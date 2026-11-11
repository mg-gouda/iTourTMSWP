/* iTour Trips block tabs (arrow keys move between tabs; RTL aware). */
(function () {
	document.querySelectorAll('.itc-tabs').forEach(function (list) {
		var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
		function show(tab) {
			tabs.forEach(function (t) {
				var on = t === tab;
				t.setAttribute('aria-selected', on);
				t.tabIndex = on ? 0 : -1;
				document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
			});
		}
		tabs.forEach(function (t, i) {
			t.addEventListener('click', function () { show(t); });
			t.addEventListener('keydown', function (e) {
				var d = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
				if (!d) return;
				if (getComputedStyle(list).direction === 'rtl') d = -d;
				var n = tabs[(i + d + tabs.length) % tabs.length];
				show(n);
				n.focus();
			});
		});
	});
})();

/* Listing filters: apply on change (price on release), sort select, live price label. */
(function () {
	var form = document.querySelector('[data-itc-filters]');
	if (!form) return;
	var range = form.querySelector('[data-itc-range]');
	var out = form.querySelector('[data-itc-out]');
	if (range && out) {
		var fmt = out.textContent.replace(/[\d.,]+/, '#');
		range.addEventListener('input', function () { out.textContent = fmt.replace('#', Number(range.value).toLocaleString()); });
	}
	form.addEventListener('change', function (e) {
		if (e.target.type !== 'search') form.submit();
	});
	var sort = document.querySelector('[data-itc-sort]');
	if (sort) {
		sort.addEventListener('change', function () {
			form.querySelector('input[name="sort"]').value = sort.value;
			form.submit();
		});
	}
})();

/* Phones: the filters start folded so the results come first. */
(function () {
	var more = document.querySelector('[data-itc-more]');
	if (more && window.matchMedia('(max-width: 959px)').matches && !/[?&](dest|cat|max)/.test(location.search)) more.open = false;
})();
