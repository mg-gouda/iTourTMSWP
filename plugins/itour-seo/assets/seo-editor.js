/* iTour SEO editor: live Google preview, character counts and the checklist. The score ("7/10") goes into a
   hidden field that saves with the SEO tab and shows as its badge. Reads the page text from the block
   editor when present, else from the classic editor. */
( function () {
	var cfg = window.itourSeo || {};
	var pane = document.getElementById( 'itp-sec-seo' );
	if ( ! pane ) {
		return;
	}
	var $ = function ( sel ) {
		return pane.querySelector( sel );
	};
	var kp = $( '[name="itp[seo][keyphrase]"]' );
	var title = $( '[name="itp[seo][title]"]' );
	var desc = $( '[name="itp[seo][desc]"]' );
	var preview = $( '[data-seo-preview]' );
	var list = $( '[data-seo-checks]' );
	var score = $( '[data-seo-score]' );

	function editor() {
		var wp = window.wp;
		if ( wp && wp.data && wp.data.select( 'core/editor' ) ) {
			var e = wp.data.select( 'core/editor' );
			return {
				content: e.getEditedPostContent() || '',
				slug: e.getEditedPostAttribute( 'slug' ) || preview.dataset.slug,
				title: e.getEditedPostAttribute( 'title' ) || '',
			};
		}
		var c = document.getElementById( 'content' );
		var t = document.getElementById( 'title' );
		return { content: c ? c.value : '', slug: preview.dataset.slug, title: t ? t.value : '' };
	}

	function norm( s ) {
		return ( s || '' ).toLowerCase().normalize( 'NFKD' ).replace( /[̀-ͯ]/g, '' ).replace( /[^\p{L}\p{N}]+/gu, ' ' ).trim();
	}

	function has( hay, needle ) {
		return '' !== needle && ( ' ' + norm( hay ) + ' ' ).indexOf( ' ' + needle + ' ' ) > -1;
	}

	function cut( s, n ) {
		return s.length > n ? s.slice( 0, n - 1 ).replace( /\s+\S*$/, '' ) + '…' : s;
	}

	function run() {
		var ed = editor();
		var t = title.value.trim() || preview.dataset.defaultTitle.replace( /^\s+|\s+$/g, '' );
		t = t.replace( /\{title\}/g, ed.title ).replace( /\{\w+\}/g, '' ).trim();
		var d = desc.value.trim() || preview.dataset.defaultDesc;
		var key = norm( kp.value );
		var doc = new DOMParser().parseFromString( ed.content, 'text/html' );
		var text = doc.body.textContent || '';
		var first = doc.querySelector( 'p' );
		var imgs = doc.querySelectorAll( 'img' );
		var links = Array.prototype.filter.call( doc.querySelectorAll( 'a[href]' ), function ( a ) {
			return 0 === a.getAttribute( 'href' ).indexOf( '/' ) || a.href.indexOf( location.host ) > -1;
		} );
		var checks = {
			keyphrase: '' !== key,
			in_title: has( t, key ),
			in_desc: has( d, key ),
			in_slug: '' !== key && norm( ed.slug.replace( /-/g, ' ' ) ).indexOf( key ) > -1,
			in_intro: has( first ? first.textContent : text.slice( 0, 400 ), key ),
			title_len: t.length >= 30 && t.length <= 60,
			desc_len: d.length >= 120 && d.length <= 156,
			words: text.trim().split( /\s+/ ).filter( Boolean ).length >= 250,
			alt: Array.prototype.every.call( imgs, function ( i ) {
				return '' !== ( i.getAttribute( 'alt' ) || '' ).trim();
			} ),
			link: links.length > 0,
		};
		var ok = 0;
		list.replaceChildren();
		Object.keys( checks ).forEach( function ( k ) {
			var li = document.createElement( 'li' );
			li.className = checks[ k ] ? 'is-ok' : 'is-todo';
			li.textContent = cfg.checks[ k ];
			list.appendChild( li );
			ok += checks[ k ] ? 1 : 0;
		} );
		score.value = ok + '/' + Object.keys( checks ).length;
		$( '[data-seo-preview-title]' ).textContent = cut( t, 61 );
		$( '[data-seo-preview-desc]' ).textContent = cut( d, 158 );
		$( '[data-seo-count="title"]' ).textContent = ( cfg.chars || '%d' ).replace( '%d', t.length );
		$( '[data-seo-count="desc"]' ).textContent = ( cfg.chars || '%d' ).replace( '%d', d.length );
	}

	[ kp, title, desc ].forEach( function ( el ) {
		el.addEventListener( 'input', run );
	} );
	run();
	// The page text changes in the editor too: refresh now and then (cheap; no save).
	setInterval( run, 4000 );
}() );
