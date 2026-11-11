/*
 * iTour AI Mode: floating assistant. The conversation lives in sessionStorage (survives page changes within a
 * visit); each turn posts the recent messages + the booking draft to admin-ajax (itour_ai_chat).
 */
(function () {
	'use strict';
	if (!window.itourAi) { return; }
	var T = itourAi.i18n;
	var ICON = {
		spark: '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 2.5l1.8 5.4 5.4 1.8-5.4 1.8L12 16.9l-1.8-5.4L4.8 9.7l5.4-1.8zM18.5 14l.85 2.4 2.4.85-2.4.85-.85 2.4-.85-2.4-2.4-.85 2.4-.85z"/></svg>',
		send: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
		mic: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21"/></svg>',
		vol: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4zM15.5 9a4.5 4.5 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11"/></svg>',
		mute: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4zM21 9.5l-5 5M16 9.5l5 5"/></svg>',
		reset: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 12a8.5 8.5 0 1 0 2.8-6.3L3.5 8.5"/><path d="M3.5 3.5v5h5"/></svg>',
		close: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>'
	};
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function uid() { return 'i' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10); }

	// Launcher + panel.
	var launcher = document.createElement('button');
	launcher.type = 'button';
	launcher.className = 'itai-launcher';
	launcher.setAttribute('aria-haspopup', 'dialog');
	launcher.setAttribute('aria-expanded', 'false');
	launcher.innerHTML = '<span class="itai-launcher__icon">' + ICON.spark + '</span><span class="itai-launcher__label">' + esc(T.open) + '</span>';
	var panel = document.createElement('section');
	panel.className = 'itai';
	panel.setAttribute('role', 'dialog');
	panel.setAttribute('aria-label', T.title);
	panel.hidden = true;
	panel.innerHTML =
		'<header class="itai__head"><span class="itai__avatar">' + ICON.spark + '</span>' +
			'<div class="itai__id"><strong>' + esc(T.title) + '</strong><small>' + esc(T.subtitle) + '</small></div>' +
			'<button type="button" class="itai__icon itai__voice" hidden></button>' +
			'<button type="button" class="itai__icon itai__reset" aria-label="' + esc(T.restart) + '" title="' + esc(T.restart) + '">' + ICON.reset + '</button>' +
			'<button type="button" class="itai__icon itai__close" aria-label="' + esc(T.close) + '" title="' + esc(T.close) + '">' + ICON.close + '</button>' +
		'</header>' +
		'<div class="itai__log" aria-live="polite"></div>' +
		'<div class="itai__input"><textarea rows="1" maxlength="1000" aria-label="' + esc(T.placeholder) + '" placeholder="' + esc(T.placeholder) + '"></textarea>' +
			'<button type="button" class="itai__icon itai__mic" hidden aria-label="' + esc(T.mic) + '" title="' + esc(T.mic) + '">' + ICON.mic + '</button>' +
			'<button type="button" class="itai__send" aria-label="' + esc(T.send) + '">' + ICON.send + '</button></div>' +
		'<p class="itai__note">' + esc(T.disclaimer) + '</p>';
	document.body.appendChild(panel);
	document.body.appendChild(launcher);

	var log = panel.querySelector('.itai__log');
	var input = panel.querySelector('textarea');
	var sendBtn = panel.querySelector('.itai__send');
	var micBtn = panel.querySelector('.itai__mic');
	var voiceBtn = panel.querySelector('.itai__voice');
	var phone = window.matchMedia('(max-width: 767px)');

	function open() {
		panel.hidden = false;
		document.body.classList.add('itai-open');
		launcher.setAttribute('aria-expanded', 'true');
		markAuto();
		setTimeout(function () { scroll(); if (!phone.matches) { input.focus(); } }, 60);
	}
	function close() {
		if (panel.hidden) { return; }
		panel.hidden = true;
		document.body.classList.remove('itai-open');
		launcher.setAttribute('aria-expanded', 'false');
		stopSpeaking();
		launcher.focus();
	}
	launcher.addEventListener('click', function () { if (panel.hidden) { open(); } else { close(); } });
	panel.querySelector('.itai__close').addEventListener('click', close);
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });

	// Opens by itself once per visit on the home page (if switched on).
	function autoDone() { try { return sessionStorage.getItem('itai_auto') === '1'; } catch (e) { return true; } }
	function markAuto() { try { sessionStorage.setItem('itai_auto', '1'); } catch (e) {} }
	if (itourAi.autoOpen && !autoDone()) {
		setTimeout(function () { if (!autoDone() && panel.hidden) { open(); } }, phone.matches ? 2500 : 5000);
	}

	var state = load() || { messages: [], draft: null, session: uid() };
	var busy = false;
	function load() { try { return JSON.parse(sessionStorage.getItem('itai_state') || 'null'); } catch (e) { return null; } }
	function save() { try { sessionStorage.setItem('itai_state', JSON.stringify(state)); } catch (e) {} }
	function scroll() { log.scrollTop = log.scrollHeight; }

	function bubble(role, text, cards) {
		var row = document.createElement('div');
		row.className = 'itai__row itai__row--' + role;
		row.innerHTML = (role === 'assistant' ? '<span class="itai__avatar itai__avatar--sm">' + ICON.spark + '</span>' : '') + '<div class="itai__bubble">' + esc(text) + '</div>';
		log.appendChild(row);
		if (cards && cards.length) {
			var wrap = document.createElement('div');
			wrap.className = 'itai__cards';
			cards.forEach(function (c) {
				var card = document.createElement('article');
				card.className = 'itai__card';
				card.innerHTML = '<span class="itai__card-img"></span><span class="itai__card-body"><strong>' + esc(c.title) + '</strong>' +
					(c.meta ? '<small>' + esc(c.meta) + '</small>' : '') +
					(c.price ? '<em>' + esc(T.from) + ' ' + esc(c.price) + '</em>' : '') +
					'<span class="itai__card-btns">' + (c.book ? '<button type="button" class="itai__book">' + esc(T.book) + '</button>' : '') +
					'<a href="' + esc(c.url) + '">' + esc(T.view) + '</a></span></span>';
				if (c.image && /^https?:\/\//.test(c.image)) {
					card.querySelector('.itai__card-img').style.backgroundImage = 'url("' + encodeURI(c.image).replace(/"/g, '%22') + '")';
				}
				var b = card.querySelector('.itai__book');
				if (b) { b.addEventListener('click', function () { send(T.book + ': ' + c.title); }); }
				wrap.appendChild(card);
			});
			log.appendChild(wrap);
		}
		scroll();
	}
	function intro() {
		log.innerHTML = '';
		bubble('assistant', T.intro);
		if (!state.messages.length && T.chips && T.chips.length) {
			var chips = document.createElement('div');
			chips.className = 'itai__chips';
			T.chips.forEach(function (t) {
				var b = document.createElement('button');
				b.type = 'button';
				b.textContent = t;
				b.addEventListener('click', function () { send(t); });
				chips.appendChild(b);
			});
			log.appendChild(chips);
		}
	}
	function render() {
		intro();
		state.messages.forEach(function (m) { bubble(m.role, m.content, m.cards); });
	}
	function thinking(on) {
		var el = log.querySelector('.itai__thinking');
		if (on && !el) {
			el = document.createElement('div');
			el.className = 'itai__row itai__row--assistant itai__thinking';
			el.innerHTML = '<span class="itai__avatar itai__avatar--sm">' + ICON.spark + '</span><div class="itai__bubble"><span class="itai__dots" aria-hidden="true"><i></i><i></i><i></i></span> ' + esc(T.thinking) + '</div>';
			log.appendChild(el);
			scroll();
		} else if (!on && el) {
			el.remove();
		}
		sendBtn.disabled = on;
	}

	function send(override) {
		var text = (override != null ? override : input.value).trim();
		if (!text || busy) { return; }
		busy = true;
		var chips = log.querySelector('.itai__chips');
		if (chips) { chips.remove(); }
		state.messages.push({ role: 'user', content: text });
		bubble('user', text);
		input.value = '';
		autosize();
		save();
		thinking(true);
		var body = new FormData();
		body.append('action', 'itour_ai_chat');
		body.append('payload', JSON.stringify({
			messages: state.messages.slice(-14).map(function (m) { return { role: m.role, content: String(m.content).slice(0, 3500) }; }),
			draft: state.draft,
			locale: document.documentElement.lang || '',
			session: state.session
		}));
		fetch(itourAi.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				thinking(false);
				var reply = (res && res.reply) || T.error;
				if (res && res.draft) { state.draft = res.draft; }
				if (res && res.intent === 'complete' && res.redirect) {
					stopSpeaking();
					bubble('assistant', reply + '\n\n' + T.checkout);
					speak(reply, res.lang);
					state = { messages: [], draft: null, session: uid() };
					save();
					setTimeout(function () { window.location.href = res.redirect; }, 1200);
					return;
				}
				state.messages.push({ role: 'assistant', content: reply, cards: (res && res.cards) || [] });
				save();
				bubble('assistant', reply, res && res.cards);
				speak(reply, res && res.lang);
			})
			.catch(function () { thinking(false); bubble('assistant', T.error); })
			.then(function () { busy = false; });
	}
	function autosize() {
		input.style.height = 'auto';
		input.style.height = Math.min(input.scrollHeight, 110) + 'px';
	}
	input.addEventListener('input', autosize);
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
	});
	sendBtn.addEventListener('click', function () { send(); });
	panel.querySelector('.itai__reset').addEventListener('click', function () {
		stopSpeaking();
		state = { messages: [], draft: null, session: uid() };
		save();
		render();
	});

	// Voice (browser Web Speech API, free): the mic types what the guest says; replies can be read aloud.
	var LANGS = { en: 'en-GB', ar: 'ar-EG', de: 'de-DE', fr: 'fr-FR', it: 'it-IT', es: 'es-ES', pl: 'pl-PL', ru: 'ru-RU' };
	var lang = (document.documentElement.lang || 'en').slice(0, 2).toLowerCase();
	var device = (navigator.language || '').replace('_', '-');
	var speechLang = device && device.slice(0, 2).toLowerCase() === lang && device.indexOf('-') > 0 ? device : (LANGS[lang] || 'en-GB');
	var Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
	function pickVoice(tag) {
		var want = String(tag || speechLang).toLowerCase(), base = want.slice(0, 2), best = null, score = -1;
		window.speechSynthesis.getVoices().forEach(function (v) {
			var vl = String(v.lang || '').toLowerCase().replace('_', '-');
			if (vl.slice(0, 2) !== base) { return; }
			var s = (vl === want ? 4 : 1) + (/natural|neural|online|google|premium|enhanced/i.test(v.name) ? 1 : 0);
			if (s > score) { best = v; score = s; }
		});
		return best;
	}
	var voiceOn = false;
	try { voiceOn = localStorage.getItem('itai_voice') === '1'; } catch (e) {}
	function setVoice(on) {
		voiceOn = on;
		try { localStorage.setItem('itai_voice', on ? '1' : '0'); } catch (e) {}
		voiceBtn.innerHTML = on ? ICON.vol : ICON.mute;
		voiceBtn.classList.toggle('is-on', on);
		voiceBtn.title = on ? T.voiceOn : T.voiceOff;
		voiceBtn.setAttribute('aria-label', voiceBtn.title);
		voiceBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
		if (!on) { stopSpeaking(); }
	}
	function stopSpeaking() { if (window.speechSynthesis) { window.speechSynthesis.cancel(); } }
	function speak(text, tag) {
		if (tag && /^[a-z]{2,3}(-[a-z0-9]{2,4})?$/i.test(tag)) { speechLang = tag; }
		if (!voiceOn || !window.speechSynthesis) { return; }
		var clean = String(text).replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}️]/gu, '').trim();
		if (!clean) { return; }
		stopSpeaking();
		var u = new SpeechSynthesisUtterance(clean);
		u.lang = speechLang;
		var v = pickVoice(speechLang);
		if (v) { u.voice = v; u.lang = v.lang; }
		window.speechSynthesis.speak(u);
	}
	if (window.speechSynthesis) {
		voiceBtn.hidden = false;
		setVoice(voiceOn);
		voiceBtn.addEventListener('click', function () { setVoice(!voiceOn); });
	}
	if (Rec) {
		micBtn.hidden = false;
		var rec = null;
		micBtn.addEventListener('click', function () {
			if (rec) { rec.stop(); return; }
			stopSpeaking();
			rec = new Rec();
			rec.lang = speechLang;
			rec.interimResults = true;
			rec.continuous = false;
			micBtn.classList.add('is-on');
			micBtn.setAttribute('aria-pressed', 'true');
			input.placeholder = T.listening;
			rec.onresult = function (e) {
				var t = '', fin = false;
				for (var i = e.resultIndex; i < e.results.length; i++) {
					t += e.results[i][0].transcript;
					if (e.results[i].isFinal) { fin = true; }
				}
				input.value = t;
				autosize();
				if (fin && t.trim()) {
					if (!voiceOn && window.speechSynthesis) { setVoice(true); } // Speaking implies spoken replies.
					send(t);
				}
			};
			rec.onend = rec.onerror = function () {
				micBtn.classList.remove('is-on');
				micBtn.setAttribute('aria-pressed', 'false');
				input.placeholder = T.placeholder;
				rec = null;
			};
			rec.start();
		});
	}
	render();
})();
