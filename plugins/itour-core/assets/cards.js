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
