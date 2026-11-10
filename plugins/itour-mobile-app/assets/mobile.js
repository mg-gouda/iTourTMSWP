/* iTour Mobile App: open / close the "More" sheet (native <dialog>: Escape and focus come for free). */
(function () {
	var sheet = document.querySelector('.itm-sheet');
	if (!sheet || typeof sheet.showModal !== 'function') return;
	document.addEventListener('click', function (e) {
		if (e.target.closest('[data-itm-open]')) { sheet.showModal(); return; }
		if (e.target === sheet || e.target.closest('[data-itm-close]')) sheet.close();
	});
	// Rotating to landscape / a wider window hides the bar: close the sheet with it.
	window.matchMedia('(max-width: 767px)').addEventListener('change', function (m) { if (!m.matches) sheet.close(); });
})();
