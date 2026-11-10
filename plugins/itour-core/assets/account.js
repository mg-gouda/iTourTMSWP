/* iTour account popup: open from the header link, tabs, show-password, AJAX sign-in / sign-up. */
(function () {
	var dlg = document.querySelector('.itour-auth');
	if (!dlg || typeof dlg.showModal !== 'function') return; // No <dialog>: the link opens the login page.
	var cfg = window.itourAccount || {};
	var tabs = Array.prototype.slice.call(dlg.querySelectorAll('[role="tab"]'));

	function show(tab) {
		tabs.forEach(function (t) {
			var on = t === tab;
			t.setAttribute('aria-selected', on);
			t.tabIndex = on ? 0 : -1;
			document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
		});
	}
	function firstField() {
		var f = dlg.querySelector('.itour-auth__form:not([hidden]) input:not([type="hidden"])');
		if (f) f.focus();
	}

	document.addEventListener('click', function (e) {
		var a = e.target.closest('[data-itour-auth]');
		if (!a) return;
		e.preventDefault();
		dlg.querySelector('input[name="redirect_to"]').value = location.href;
		dlg.showModal();
		firstField();
	});
	dlg.addEventListener('click', function (e) {
		if (e.target === dlg || e.target.closest('.itour-auth__close')) dlg.close();
	});

	tabs.forEach(function (t, i) {
		t.addEventListener('click', function () { show(t); firstField(); });
		t.addEventListener('keydown', function (e) {
			var d = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
			if (!d) return;
			if (document.dir === 'rtl' || document.documentElement.dir === 'rtl') d = -d;
			var n = tabs[(i + d + tabs.length) % tabs.length];
			show(n);
			n.focus();
		});
	});

	dlg.querySelectorAll('.itour-auth__eye').forEach(function (b) {
		b.addEventListener('click', function () {
			var input = b.previousElementSibling, on = input.type === 'password';
			input.type = on ? 'text' : 'password';
			input.classList.toggle('is-shown', on);
			b.setAttribute('aria-pressed', on);
		});
	});

	dlg.querySelectorAll('.itour-auth__form').forEach(function (form) {
		var btn = form.querySelector('.itour-auth__submit'), label = btn.textContent, msg = form.querySelector('.itour-auth__msg');
		var signin = form.dataset.action === 'itour_signin';
		function fail(text) {
			msg.textContent = text;
			btn.removeAttribute('aria-busy');
			btn.disabled = false;
			btn.textContent = label;
			form.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"])').forEach(function (i) { i.setAttribute('aria-invalid', 'true'); });
		}
		form.addEventListener('input', function () {
			msg.textContent = '';
			form.querySelectorAll('[aria-invalid]').forEach(function (i) { i.removeAttribute('aria-invalid'); });
		});
		form.addEventListener('submit', function (e) {
			if (form.dataset.native) return; // Two-factor: let the login page take over.
			e.preventDefault();
			var data = new FormData(form);
			data.append('action', form.dataset.action);
			btn.disabled = true;
			btn.setAttribute('aria-busy', 'true');
			btn.textContent = signin ? cfg.busyIn : cfg.busyUp;
			fetch(cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					if (!r.success) return fail(r.data && r.data.message ? r.data.message : cfg.offline);
					if (r.data && r.data.native) {
						form.dataset.native = '1';
						return form.submit();
					}
					location.reload();
				})
				.catch(function () { fail(cfg.offline); });
		});
	});
})();
