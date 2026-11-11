<?php
/** Admin: settings, per-post Translations box, overview page, AJAX actions. */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_options_page( 'iTour AI Translations', 'AI Translations', 'manage_options', 'itour-languages', 'itour_lang_settings_page' );
	add_submenu_page( 'tools.php', 'Translations', 'Translations', 'edit_posts', 'itour-translations', 'itour_lang_overview_page' );
} );

function itour_lang_settings_page() {
	$s   = itour_lang_settings();
	$all = itour_lang_all();
	if ( isset( $_POST['itour_lang_save'] ) && check_admin_referer( 'itour_lang_settings' ) ) {
		$s['enabled'] = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['enabled'] ?? array() ) ), array_keys( $all ) ) );
		$s['model']   = sanitize_text_field( wp_unslash( $_POST['model'] ?? 'gemini-2.5-flash' ) );
		$s['live']    = empty( $_POST['live'] ) ? 0 : 1;
		$key          = trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ) );
		if ( '' !== $key && false === strpos( $key, '•' ) ) {
			$s['api_key'] = $key;
		}
		if ( ! empty( $_POST['clear_key'] ) ) {
			$s['api_key'] = '';
		}
		update_option( 'itour_lang_settings', $s, false );
		echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
	}
	$masked = $s['api_key'] ? str_repeat( '•', 12 ) . substr( $s['api_key'], -4 ) : '';
	?>
	<div class="wrap">
		<h1>iTour AI Translations</h1>
		<form method="post">
			<?php wp_nonce_field( 'itour_lang_settings' ); ?>
			<table class="form-table">
				<tr><th>Languages</th><td>
					<p><strong>English</strong> (main language, always on)</p>
					<?php foreach ( $all as $c => $l ) : if ( 'en' === $c ) { continue; } ?>
						<label style="display:inline-block;min-width:150px"><input type="checkbox" name="enabled[]" value="<?php echo esc_attr( $c ); ?>" <?php checked( in_array( $c, $s['enabled'], true ) ); ?>> <?php echo esc_html( $l[0] . ' (/' . $c . '/)' ); ?></label>
					<?php endforeach; ?>
				</td></tr>
				<tr><th>Visible to visitors</th><td>
					<label><input type="checkbox" name="live" value="1" <?php checked( $s['live'] ); ?>> Show the language switcher and translated pages to everyone</label>
					<p class="description">While off, translated pages are only visible to administrators (for checking).</p>
				</td></tr>
				<tr><th><label for="rl-key">Gemini API key</label></th><td>
					<input id="rl-key" type="password" name="api_key" value="<?php echo esc_attr( $masked ); ?>" class="regular-text" autocomplete="off" placeholder="Paste key from aistudio.google.com/apikey">
					<?php if ( $s['api_key'] ) : ?><label style="margin-left:10px"><input type="checkbox" name="clear_key" value="1"> Remove key</label><?php endif; ?>
					<p class="description">Stored on this server only. The full key is never shown again.</p>
				</td></tr>
				<tr><th><label for="rl-model">Gemini model</label></th><td>
					<input id="rl-model" type="text" name="model" value="<?php echo esc_attr( $s['model'] ); ?>" class="regular-text">
					<p class="description">Default <code>gemini-flash-lite-latest</code> (largest free daily quota). Stronger models such as <code>gemini-3.5-flash</code> allow only ~20 requests/day on the free tier; when one is chosen and runs out or is overloaded, Flash-Lite takes over automatically.</p>
				</td></tr>
			</table>
			<p><button class="button button-primary" name="itour_lang_save" value="1">Save settings</button>
			<?php if ( $s['api_key'] ) : ?> <button type="button" class="button" id="rl-test">Test Gemini</button> <span id="rl-test-out"></span><?php endif; ?></p>
		</form>
		<p>Language switcher shortcode: <code>[itour_language_switcher]</code></p>
	</div>
	<script>
	document.getElementById('rl-test') && document.getElementById('rl-test').addEventListener('click', function () {
		var o = document.getElementById('rl-test-out'); o.textContent = 'Testing…';
		fetch(ajaxurl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=itour_lang_test&_wpnonce=<?php echo esc_js( wp_create_nonce( 'itour_lang_ajax' ) ); ?>'})
			.then(function(r){return r.json();}).then(function(r){ o.textContent = r.success ? ('✔ ' + r.data) : ('✖ ' + r.data); });
	});
	</script>
	<?php
}

add_action( 'wp_ajax_itour_lang_test', function () {
	check_ajax_referer( 'itour_lang_ajax' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Not allowed' );
	}
	$r = itour_lang_gemini( array( 'a' => 'Welcome to Egypt! Discover the Pyramids of Giza with us.' ), 'de' );
	is_wp_error( $r ) ? wp_send_json_error( $r->get_error_message() ) : wp_send_json_success( 'Gemini works. German test: ' . ( $r['a'] ?? '' ) );
} );

/* ---------- Translations box on edit screens ---------- */

function itour_lang_post_status( $post_id ) {
	$src = itour_lang_post_source( $post_id );
	$out = array();
	foreach ( array_keys( array_diff_key( itour_lang_enabled(), array( 'en' => 1 ) ) ) as $l ) {
		$have = itour_lang_get( 'post', $post_id, $l );
		$miss = 0;
		$old  = 0;
		foreach ( $src as $f => $v ) {
			if ( ! isset( $have[ $f ] ) ) {
				$miss++;
			} elseif ( $have[ $f ]->source_hash !== md5( $v ) ) {
				$old++;
			}
		}
		$out[ $l ] = ! $have ? 'missing' : ( $miss || $old ? 'outdated' : 'done' );
	}
	return $out;
}

add_action( 'add_meta_boxes', function () {
	foreach ( itour_lang_post_types() as $pt ) {
		add_meta_box( 'itour-lang-box', 'Translations', 'itour_lang_box', $pt, 'side', 'high' );
	}
} );

function itour_lang_box( $post ) {
	itour_lang_src_note( $post );
	if ( 'publish' !== $post->post_status ) {
		echo '<p>Publish the English version first.</p>';
		return;
	}
	$all   = itour_lang_all();
	$label = array( 'done' => '✔ Up to date', 'outdated' => '⟳ Needs update', 'missing' => '— Not translated' );
	$color = array( 'done' => '#00a32a', 'outdated' => '#dba617', 'missing' => '#787c82' );
	echo '<table style="width:100%">';
	foreach ( itour_lang_post_status( $post->ID ) as $l => $st ) {
		$url = itour_lang_prefix_url( get_permalink( $post ), $l );
		echo '<tr><td><strong>' . esc_html( $all[ $l ][0] ) . '</strong><br><span style="color:' . esc_attr( $color[ $st ] ) . '">' . esc_html( $label[ $st ] ) . '</span></td>'
			. '<td style="text-align:right;white-space:nowrap"><a href="' . esc_url( $url ) . '" target="_blank" class="button button-small">View</a> '
			. '<button type="button" class="button button-small rl-tr" data-lang="' . esc_attr( $l ) . '">Translate</button></td></tr>';
	}
	echo '</table><p><button type="button" class="button button-primary rl-tr" data-lang="all" style="width:100%">Translate all languages</button></p><p id="rl-tr-out" class="description"></p>';
	?>
	<script>
	(function(){
		var langs = <?php echo wp_json_encode( array_keys( array_diff_key( itour_lang_enabled(), array( 'en' => 1 ) ) ) ); ?>;
		var out = document.getElementById('rl-tr-out');
		function run(list){
			if(!list.length){ out.textContent += ' Done — reload to see the status.'; return; }
			var l = list.shift(); out.textContent = 'Translating ' + l.toUpperCase() + '…';
			fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=itour_lang_translate&post_id=<?php echo (int) $post->ID; ?>&lang='+l+'&_wpnonce=<?php echo esc_js( wp_create_nonce( 'itour_lang_ajax' ) ); ?>'})
				.then(function(r){return r.json();}).then(function(r){ out.textContent = (r.success?'✔ ':'✖ ')+l.toUpperCase()+': '+r.data; setTimeout(function(){run(list);}, list.length?6500:0); })
				.catch(function(){ out.textContent='✖ '+l.toUpperCase()+': request failed'; });
		}
		document.querySelectorAll('#itour-lang-box .rl-tr').forEach(function(b){ b.addEventListener('click', function(){ run(b.dataset.lang==='all' ? langs.slice() : [b.dataset.lang]); }); });
	})();
	</script>
	<?php
}

add_action( 'wp_ajax_itour_lang_translate', function () {
	check_ajax_referer( 'itour_lang_ajax' );
	$id   = absint( $_POST['post_id'] ?? 0 );
	$lang = sanitize_key( $_POST['lang'] ?? '' );
	if ( ! current_user_can( 'edit_post', $id ) || ! isset( itour_lang_enabled()[ $lang ] ) || 'en' === $lang ) {
		wp_send_json_error( 'Not allowed' );
	}
	@set_time_limit( 300 );
	$r = itour_lang_translate_post( $id, $lang, false, true );
	if ( is_wp_error( $r ) ) {
		itour_lang_queue_add( $id ); // will be retried by the background queue
		wp_send_json_error( $r->get_error_message() . ' — queued for automatic retry in the background.' );
	}
	wp_send_json_success( $r ? "$r field(s) translated" : 'already up to date' );
} );

/* ---------- Overview page ---------- */

function itour_lang_overview_page() {
	global $wpdb;
	$langs = array_keys( array_diff_key( itour_lang_enabled(), array( 'en' => 1 ) ) );
	$posts = get_posts( array( 'post_type' => itour_lang_post_types(), 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'type title', 'order' => 'ASC' ) );
	$tot   = array_fill_keys( $langs, 0 );
	$rows  = '';
	foreach ( $posts as $p ) {
		$st    = itour_lang_post_status( $p->ID );
		$rows .= '<tr><td><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( $p->post_title ) . '</a></td><td>' . esc_html( get_post_type_object( $p->post_type )->labels->singular_name ) . '</td>';
		foreach ( $langs as $l ) {
			$icon  = array( 'done' => '<span style="color:#00a32a">✔</span>', 'outdated' => '<span style="color:#dba617">⟳</span>', 'missing' => '<span style="color:#c3c4c7">—</span>' )[ $st[ $l ] ];
			$rows .= '<td style="text-align:center">' . $icon . '</td>';
			if ( 'done' === $st[ $l ] ) {
				$tot[ $l ]++;
			}
		}
		$rows .= '</tr>';
	}
	$q = get_option( 'itour_lang_queue', array() );
	echo '<div class="wrap"><h1>Translations</h1><p>' . count( $posts ) . ' published items. Background queue: <strong>' . count( $q ) . '</strong> item(s) waiting.</p><table class="widefat striped"><thead><tr><th>Item</th><th>Type</th>';
	foreach ( $langs as $l ) {
		echo '<th style="text-align:center">' . esc_html( strtoupper( $l ) ) . '<br><small>' . (int) $tot[ $l ] . '/' . count( $posts ) . '</small></th>';
	}
	echo '</tr></thead><tbody>' . $rows . '</tbody></table></div>'; // phpcs:ignore
}
