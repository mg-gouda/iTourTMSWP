<?php
/**
 * Admin screens: Overview, Channels (connect each network), Schedule, Queue, Preview.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_menu_page( 'Social Auto Pilot', 'Social Auto Pilot', 'manage_options', 'itour-social', 'itsoc_page', 'dashicons-share', 58 );
} );

add_action( 'admin_head', function () {
	if ( 'toplevel_page_itour-social' !== ( get_current_screen()->id ?? '' ) ) {
		return;
	}
	?>
	<style>
		.itsoc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:16px;margin-top:12px}
		.itsoc-card{background:var(--rl-card,#fff);color:var(--rl-fg,#1d2327);border:1px solid var(--rl-border,#dcdcde);border-radius:var(--rl-radius,6px);padding:16px 18px}
		.itsoc-card .widefat{background:transparent}
		.itsoc-card h3{margin:0 0 6px;display:flex;justify-content:space-between;align-items:center}
		.itsoc-card label{display:block;margin:8px 0 2px;font-weight:600}
		.itsoc-card input[type=text],.itsoc-card input[type=password],.itsoc-card select{width:100%}
		.itsoc-pill{font-size:11px;padding:2px 8px;border-radius:10px;background:var(--rl-muted,#f0f0f1);color:var(--rl-muted-fg,#50575e);font-weight:600;white-space:nowrap}
		.itsoc-pill.ok{background:rgba(34,197,94,.18);color:#22c55e}.itsoc-pill.bad{background:rgba(248,113,113,.18);color:#f87171}.itsoc-pill.warn{background:rgba(247,150,34,.18);color:#f79622}
		.itsoc-steps{margin:8px 0 0 18px;color:var(--rl-muted-fg,#50575e)}.itsoc-steps li{margin-bottom:4px;color:inherit}
		.itsoc-actions{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
		.itsoc-caption{white-space:pre-wrap;background:var(--rl-muted,#f6f7f7);color:var(--rl-fg,#1d2327);border:1px solid var(--rl-border,#e0e0e0);border-radius:4px;padding:10px;font-size:13px;max-height:340px;overflow:auto}
		.itsoc-slides{display:flex;gap:8px;overflow-x:auto;padding-bottom:6px}.itsoc-slides img{height:260px;border-radius:4px}
		.itsoc-big{font-size:28px;font-weight:700;line-height:1.1}
		.wp-admin td.itsoc-st-done{color:#22c55e;font-weight:600}.wp-admin td.itsoc-st-failed{color:#f87171;font-weight:600}.wp-admin td.itsoc-st-queued{color:#f79622}.wp-admin td.itsoc-st-skipped{color:var(--rl-muted-fg,#787c82)}
	</style>
	<?php
} );

function itsoc_status_pill( $channel ) {
	$on = ! empty( itsoc_opt( 'channels' )[ $channel ] );
	if ( itsoc_ready( $channel ) ) {
		return '<span class="itsoc-pill ok">Connected · on</span>';
	}
	if ( in_array( $channel, array( 'tiktok', 'youtube' ), true ) && ! itsoc_ffmpeg() ) {
		return '<span class="itsoc-pill warn">Needs ffmpeg for video</span>';
	}
	return $on ? '<span class="itsoc-pill bad">On · not connected</span>' : '<span class="itsoc-pill">Off</span>';
}

function itsoc_page() {
	$tab  = sanitize_key( $_GET['tab'] ?? 'overview' ); // phpcs:ignore WordPress.Security.NonceVerification
	$tabs = array( 'overview' => 'Overview', 'channels' => 'Channels', 'schedule' => 'Schedule', 'queue' => 'Queue', 'preview' => 'Preview & post now' );
	echo '<div class="wrap"><h1>Social autopilot</h1>';
	$n = get_transient( 'itsoc_notice_' . get_current_user_id() );
	if ( $n ) {
		delete_transient( 'itsoc_notice_' . get_current_user_id() );
		echo '<div class="notice notice-' . esc_attr( $n[1] ) . ' is-dismissible"><p>' . esc_html( $n[0] ) . '</p></div>';
	}
	foreach ( array( 'itsoc_ok' => 'success', 'itsoc_err' => 'error' ) as $k => $type ) {
		if ( ! empty( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html( wp_unslash( $_GET[ $k ] ) ) . '</p></div>'; // phpcs:ignore
		}
	}
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $label ) {
		echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( itsoc_admin_url( array( 'tab' => $k ) ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
	call_user_func( 'itsoc_tab_' . ( isset( $tabs[ $tab ] ) ? $tab : 'overview' ) );
	echo '</div>';
}

function itsoc_form_open( $action, array $hidden = array() ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( $action );
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	foreach ( $hidden as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
}

function itsoc_btn_link( $action, array $args, $label, $class = 'button' ) {
	$url = wp_nonce_url( add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) ), $action . '_' . ( $args['provider'] ?? $args['channel'] ?? $args['do'] ?? '' ) );
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
}

/* ---------- Overview ---------- */

function itsoc_tab_overview() {
	global $wpdb;
	$t      = $wpdb->prefix . 'itsoc_jobs';
	$counts = array();
	foreach ( $wpdb->get_results( "SELECT status, COUNT(*) n FROM $t GROUP BY status" ) as $r ) {
		$counts[ $r->status ] = (int) $r->n;
	}
	$week   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'done' AND done_at >= %s", gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS ) ) );
	$left   = count( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_itsoc_started', 'compare' => 'NOT EXISTS' ) ) ) ) ); // phpcs:ignore
	$next   = wp_next_scheduled( 'itsoc_daily' );
	$ready  = array_filter( array_keys( itsoc_channels() ), 'itsoc_ready' );
	$on     = itsoc_opt( 'enabled' );
	echo '<div class="itsoc-grid">';

	echo '<div class="itsoc-card"><h3>Autopilot ' . ( $on ? '<span class="itsoc-pill ok">Running</span>' : '<span class="itsoc-pill">Paused</span>' ) . '</h3>';
	echo '<p>' . ( $on ? 'Posting to ' . count( $ready ) . ' connected channel(s). Switch off to pause everything – nothing is lost; due posts wait.' : 'Nothing is posted while paused. Connect the channels first, preview a post, then switch on.' ) . '</p>';
	itsoc_form_open( 'itsoc_save', array( 'section' => 'toggle' ) );
	echo '<input type="hidden" name="enabled" value="' . ( $on ? 0 : 1 ) . '">';
	submit_button( $on ? 'Pause autopilot' : 'Switch autopilot on', $on ? 'secondary' : 'primary', 'submit', false );
	echo '</form></div>';

	echo '<div class="itsoc-card"><h3>Numbers</h3><p><span class="itsoc-big">' . (int) $week . '</span> posts published in the last 7 days</p>';
	echo '<p>Waiting: <b>' . (int) ( $counts['queued'] ?? 0 ) . '</b> · Done: <b>' . (int) ( $counts['done'] ?? 0 ) . '</b> · Failed: <b>' . (int) ( $counts['failed'] ?? 0 ) . '</b> · Skipped: <b>' . (int) ( $counts['skipped'] ?? 0 ) . '</b></p>';
	echo '<p>Older blog posts not shared yet: <b>' . (int) $left . '</b> (one every ' . (int) itsoc_opt( 'backfill_days' ) . ' days)</p>';
	echo '<p>Next daily product post: <b>' . ( $next ? esc_html( wp_date( 'D j M, H:i', $next ) ) : '—' ) . '</b> – today it would be: <b>' . esc_html( itsoc_key_label( itsoc_pick_product() ) ) . '</b></p></div>';

	echo '<div class="itsoc-card"><h3>Channels</h3><table class="widefat striped"><tbody>';
	foreach ( itsoc_channels() as $k => $c ) {
		echo '<tr><td>' . esc_html( $c['label'] ) . '</td><td>' . itsoc_status_pill( $k ) . '</td></tr>'; // phpcs:ignore
	}
	echo '</tbody></table><p><a href="' . esc_url( itsoc_admin_url( array( 'tab' => 'channels' ) ) ) . '">Connect channels →</a></p></div>';

	echo '<div class="itsoc-card"><h3>What happens to each blog post</h3><ol class="itsoc-steps">'
		. '<li><b>Right away:</b> link post on Facebook, LinkedIn and X + Google Business Profile update.</li>'
		. '<li><b>+3 h:</b> reel on Facebook and Instagram (video made from the post).</li>'
		. '<li><b>+6 h:</b> TikTok and YouTube Short.</li>'
		. '<li><b>Next day:</b> carousel on Instagram, LinkedIn and Facebook; story on Instagram and Facebook.</li>'
		. '<li><b>+2 days:</b> email to the Mailchimp list.</li>'
		. '<li><b>+7 and +14 days:</b> Google Business Profile again (3 times in total).</li></ol>'
		. '<p>Every day at ' . (int) itsoc_opt( 'daily_hour' ) . ':00 one tour, activity or transfer route is posted – <b>new ones first</b>.</p></div>';
	echo '</div>';
}

/* ---------- Channels ---------- */

function itsoc_field( $key, $label, $secret = false, $help = '' ) {
	$set = $secret && itsoc_opt( $key );
	echo '<label for="itsoc-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
	echo '<input id="itsoc-' . esc_attr( $key ) . '" type="' . ( $secret ? 'password' : 'text' ) . '" name="' . esc_attr( $key ) . '" value="' . ( $secret ? '' : esc_attr( (string) itsoc_opt( $key ) ) ) . '" placeholder="' . ( $set ? '•••••••• saved – leave empty to keep' : '' ) . '" autocomplete="off">';
	if ( $help ) {
		echo '<p class="description">' . wp_kses_post( $help ) . '</p>';
	}
}

function itsoc_switch( $channel ) {
	$on = ! empty( itsoc_opt( 'channels' )[ $channel ] );
	echo '<label><input type="checkbox" name="channels[' . esc_attr( $channel ) . ']" value="1"' . checked( $on, true, false ) . '> Post to ' . esc_html( itsoc_channels()[ $channel ]['label'] ) . '</label>';
}

function itsoc_card_open( $title, array $channels ) {
	echo '<div class="itsoc-card"><h3>' . esc_html( $title ) . ' <span>';
	foreach ( $channels as $c ) {
		echo itsoc_status_pill( $c ) . ' '; // phpcs:ignore
	}
	echo '</span></h3>';
}

function itsoc_card_buttons( $provider, array $tests, $connected ) {
	echo '<div class="itsoc-actions">';
	submit_button( 'Save', 'primary', 'submit', false );
	if ( 'x' !== $provider && 'mailchimp' !== $provider ) {
		echo itsoc_btn_link( 'itsoc_connect', array( 'provider' => $provider ), $connected ? 'Reconnect' : 'Connect ' . ucfirst( $provider ), $connected ? 'button' : 'button button-secondary' ); // phpcs:ignore
	}
	if ( $connected ) {
		foreach ( $tests as $t ) {
			echo itsoc_btn_link( 'itsoc_test', array( 'channel' => $t ), 'Test ' . itsoc_channels()[ $t ]['label'] ); // phpcs:ignore
		}
		if ( 'mailchimp' !== $provider ) {
			echo itsoc_btn_link( 'itsoc_disconnect', array( 'provider' => $provider ), 'Disconnect', 'button-link-delete' ); // phpcs:ignore
		}
	}
	echo '</div></form></div>';
}

function itsoc_tab_channels() {
	$redirect = itsoc_redirect_uri();
	echo '<p>Each network is connected through its <b>official API</b>: you sign in on the network’s own page and approve, and the site receives an access token. Passwords are never stored – networks block logins from servers, and a password would break with two-step verification. Keys and tokens are kept encrypted.</p>';
	echo '<p>Redirect / callback URL to enter in every developer app: <code>' . esc_html( $redirect ) . '</code></p>';
	echo '<div class="itsoc-grid">';

	// Facebook + Instagram.
	itsoc_card_open( 'Facebook Page + Instagram', array( 'facebook', 'instagram' ) );
	itsoc_form_open( 'itsoc_save', array( 'section' => 'facebook' ) );
	echo '<ol class="itsoc-steps"><li>Instagram must be a <b>professional</b> account linked to your Facebook Page.</li><li><a href="https://developers.facebook.com/apps/creation/" target="_blank">developers.facebook.com</a> → Create app → use case “Manage everything on your Page” and “Manage messaging & content on Instagram”.</li><li>Facebook Login for Business → Settings → Valid OAuth Redirect URIs: the URL above.</li><li>App settings → Basic: copy App ID and App secret here, Save, then <b>Connect Facebook</b> and tick the Page and its Instagram account.</li></ol>';
	itsoc_switch( 'facebook' );
	itsoc_switch( 'instagram' );
	itsoc_field( 'fb_app_id', 'App ID' );
	itsoc_field( 'fb_app_secret', 'App secret', true );
	itsoc_field( 'fb_config_id', 'Login configuration ID', false, 'Facebook Login for Business → Configurations (needed for apps created with “use cases”).' );
	$pages = (array) itsoc_opt( 'fb_pages' );
	if ( count( $pages ) > 1 ) {
		echo '<label>Page</label><select name="fb_page_pick">';
		foreach ( $pages as $id => $p ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( itsoc_opt( 'fb_page_id' ), $id, false ) . '>' . esc_html( $p['name'] . ( $p['igu'] ? ' + @' . $p['igu'] : '' ) ) . '</option>';
		}
		echo '</select>';
	}
	if ( itsoc_opt( 'fb_page_id' ) ) {
		echo '<p>Connected Page: <b>' . esc_html( itsoc_opt( 'fb_page_name' ) ) . '</b>' . ( itsoc_opt( 'ig_username' ) ? ' · Instagram <b>@' . esc_html( itsoc_opt( 'ig_username' ) ) . '</b>' : ' · <span class="itsoc-pill bad">no Instagram linked</span>' ) . '</p>';
	}
	itsoc_card_buttons( 'facebook', array( 'facebook', 'instagram' ), (bool) itsoc_opt( 'fb_page_token' ) );

	// LinkedIn.
	itsoc_card_open( 'LinkedIn Page', array( 'linkedin' ) );
	itsoc_form_open( 'itsoc_save', array( 'section' => 'linkedin' ) );
	echo '<ol class="itsoc-steps"><li><a href="https://www.linkedin.com/developers/apps/new" target="_blank">linkedin.com/developers</a> → Create app, linked to your company Page.</li><li>Products: request <b>Community Management API</b> (needed to post as the Page; LinkedIn reviews it).</li><li>Auth → Authorized redirect URLs: the URL above. Copy Client ID and Client secret here, Save, <b>Connect LinkedIn</b>.</li></ol>';
	itsoc_switch( 'linkedin' );
	itsoc_field( 'li_client_id', 'Client ID' );
	itsoc_field( 'li_client_secret', 'Client secret', true );
	$orgs = (array) itsoc_opt( 'li_orgs' );
	if ( count( $orgs ) > 1 ) {
		echo '<label>Company Page</label><select name="li_org_pick">';
		foreach ( $orgs as $id => $name ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( itsoc_opt( 'li_org_id' ), $id, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
	}
	if ( itsoc_opt( 'li_token' ) ) {
		$exp = (int) itsoc_opt( 'li_token_expires' );
		echo '<p>Posting as <b>' . esc_html( itsoc_opt( 'li_org_name' ) ) . '</b> · connection valid until <b>' . esc_html( wp_date( 'j M Y', $exp ) ) . '</b>' . ( ! itsoc_opt( 'li_refresh' ) && $exp - time() < 10 * DAY_IN_SECONDS ? ' <span class="itsoc-pill warn">reconnect soon</span>' : '' ) . '</p>';
	}
	itsoc_card_buttons( 'linkedin', array( 'linkedin' ), (bool) itsoc_opt( 'li_token' ) );

	// X.
	itsoc_card_open( 'X (Twitter)', array( 'x' ) );
	itsoc_form_open( 'itsoc_save', array( 'section' => 'x' ) );
	echo '<ol class="itsoc-steps"><li><a href="https://developer.x.com/en/portal/dashboard" target="_blank">developer.x.com</a> → your app → User authentication settings: <b>Read and write</b>.</li><li>Keys and tokens: copy <b>API Key + Secret</b>, then generate <b>Access Token + Secret</b> (after setting Read and write) and paste all four here.</li></ol>';
	itsoc_switch( 'x' );
	itsoc_field( 'x_api_key', 'API Key (consumer key)' );
	itsoc_field( 'x_api_secret', 'API Key Secret', true );
	itsoc_field( 'x_access_token', 'Access Token', true );
	itsoc_field( 'x_access_secret', 'Access Token Secret', true );
	itsoc_card_buttons( 'x', array( 'x' ), (bool) itsoc_opt( 'x_access_token' ) );

	// TikTok.
	itsoc_card_open( 'TikTok', array( 'tiktok' ) );
	itsoc_form_open( 'itsoc_save', array( 'section' => 'tiktok' ) );
	echo '<ol class="itsoc-steps"><li><a href="https://developers.tiktok.com/apps" target="_blank">developers.tiktok.com</a> → Create app → add <b>Login Kit</b> and <b>Content Posting API</b> (Direct Post on).</li><li>Redirect URI: the URL above. Copy Client key and Client secret here, Save, <b>Connect TikTok</b>.</li><li>Until TikTok approves the app (audit), videos are posted as <b>private</b> – that is TikTok’s rule.</li></ol>';
	itsoc_switch( 'tiktok' );
	itsoc_field( 'tt_client_key', 'Client key' );
	itsoc_field( 'tt_client_secret', 'Client secret', true );
	echo '<label>How to post</label><select name="tt_mode">';
	foreach ( array( 'draft' => 'Send as draft to the TikTok inbox (you add a sound and tap Post)', 'direct' => 'Post directly (needs TikTok’s app audit for public accounts)' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( itsoc_opt( 'tt_mode' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select>';
	echo '<label>Who can see the videos (direct posts)</label><select name="tt_privacy">';
	foreach ( array( 'PUBLIC_TO_EVERYONE' => 'Everyone (after TikTok’s audit)', 'MUTUAL_FOLLOW_FRIENDS' => 'Friends', 'FOLLOWER_OF_CREATOR' => 'Followers', 'SELF_ONLY' => 'Only me' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( itsoc_opt( 'tt_privacy' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select>';
	itsoc_card_buttons( 'tiktok', array( 'tiktok' ), (bool) itsoc_opt( 'tt_refresh' ) );

	// Google.
	itsoc_card_open( 'Google: YouTube Shorts + Business Profile', array( 'youtube', 'gbp' ) );
	itsoc_form_open( 'itsoc_save', array( 'section' => 'google' ) );
	echo '<ol class="itsoc-steps"><li><a href="https://console.cloud.google.com/projectcreate" target="_blank">console.cloud.google.com</a> → new project → enable <b>YouTube Data API v3</b>, <b>My Business Account Management API</b>, <b>My Business Business Information API</b> and <b>Google My Business API</b> (Business Profile APIs need Google’s <a href="https://developers.google.com/my-business/content/prereqs" target="_blank">access request</a>).</li><li>OAuth consent screen: External, then <b>Publish app</b> (in “Testing” Google stops the connection after 7 days).</li><li>Credentials → OAuth client ID → Web application → redirect URI: the URL above. Copy Client ID and secret here, Save, <b>Connect Google</b> with the account that owns the YouTube channel and the Business Profile.</li></ol>';
	itsoc_switch( 'youtube' );
	itsoc_switch( 'gbp' );
	itsoc_field( 'g_client_id', 'Client ID' );
	itsoc_field( 'g_client_secret', 'Client secret', true );
	echo '<label>YouTube visibility</label><select name="yt_privacy">';
	foreach ( array( 'public' => 'Public', 'unlisted' => 'Unlisted', 'private' => 'Private' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( itsoc_opt( 'yt_privacy' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select>';
	$locs = (array) itsoc_opt( 'gbp_locations' );
	if ( $locs ) {
		echo '<label>Business Profile</label><select name="gbp_pick">';
		foreach ( $locs as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( itsoc_opt( 'gbp_account' ) . '|' . itsoc_opt( 'gbp_location' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
	}
	if ( itsoc_opt( 'g_refresh' ) ) {
		echo '<p>Connected Google account: <b>' . esc_html( itsoc_opt( 'g_email' ) ) . '</b></p>';
	}
	itsoc_card_buttons( 'google', array( 'youtube', 'gbp' ), (bool) itsoc_opt( 'g_refresh' ) );

	// Mailchimp.
	itsoc_card_open( 'Email list (Mailchimp)', array( 'mailchimp' ) );
	itsoc_form_open( 'itsoc_save', array( 'section' => 'mailchimp' ) );
	$from_mc4wp = ! ( itsoc_settings()['mc_api_key'] ?? '' ) && itsoc_opt( 'mc_api_key' );
	echo '<p>' . ( $from_mc4wp ? 'Uses the API key already saved in <b>Mailchimp for WP</b>.' : 'Mailchimp → Profile → Extras → API keys.' ) . ' Each blog post is emailed to the chosen audience 2 days after it is published.</p>';
	itsoc_switch( 'mailchimp' );
	itsoc_field( 'mc_api_key', 'API key' . ( $from_mc4wp ? ' (optional – overrides Mailchimp for WP)' : '' ), true );
	$lists = array();
	if ( itsoc_opt( 'mc_api_key' ) ) {
		try {
			$lists = itsoc_mc_lists();
		} catch ( Itsoc_Error $e ) {
			echo '<p class="itsoc-pill bad">' . esc_html( $e->getMessage() ) . '</p>';
		}
	}
	echo '<label>Audience</label><select name="mc_list"><option value="">— choose —</option>';
	foreach ( $lists as $id => $name ) {
		echo '<option value="' . esc_attr( $id ) . '"' . selected( itsoc_opt( 'mc_list' ), $id, false ) . '>' . esc_html( $name ) . '</option>';
	}
	echo '</select>';
	itsoc_field( 'mc_from_name', 'From name (empty = the audience’s default)' );
	itsoc_field( 'mc_reply_to', 'Reply-to email (empty = the audience’s default)' );
	itsoc_card_buttons( 'mailchimp', array( 'mailchimp' ), (bool) itsoc_opt( 'mc_api_key' ) );

	echo '</div>';
}

/* ---------- Schedule ---------- */

function itsoc_tab_schedule() {
	$steps = array( 'link' => 'Link post – Facebook, LinkedIn, X', 'reel' => 'Reel – Facebook & Instagram (video)', 'short' => 'TikTok & YouTube Short (video)', 'carousel' => 'Carousel – Instagram, LinkedIn, Facebook', 'story' => 'Story – Facebook & Instagram', 'email' => 'Email to the list', 'gbp' => 'Google Business Profile ×3 (day 0, 7, 14)' );
	itsoc_form_open( 'itsoc_save', array( 'section' => 'schedule' ) );
	echo '<div class="itsoc-grid"><div class="itsoc-card"><h3>Blog posts</h3>';
	foreach ( $steps as $k => $l ) {
		echo '<label style="font-weight:400"><input type="checkbox" name="post_steps[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, (array) itsoc_opt( 'post_steps' ), true ), true, false ) . '> ' . esc_html( $l ) . '</label>';
	}
	echo '<label>Older posts: share one every … days</label><input type="number" min="1" max="30" name="backfill_days" value="' . (int) itsoc_opt( 'backfill_days' ) . '">';
	echo '<p class="description">Existing posts are shared newest first. A new post also resets this timer so the feed is not flooded.</p></div>';

	echo '<div class="itsoc-card"><h3>Daily product post</h3><label>Time (Cairo)</label><select name="daily_hour">';
	for ( $h = 6; $h <= 22; $h++ ) {
		echo '<option value="' . $h . '"' . selected( (int) itsoc_opt( 'daily_hour' ), $h, false ) . '>' . sprintf( '%02d:00', $h ) . '</option>'; // phpcs:ignore
	}
	echo '</select><label>Products</label>';
	foreach ( array( 'tour' => 'Tours', 'activity' => 'Activities', 'transfer' => 'Transfer routes' ) as $k => $l ) {
		echo '<label style="font-weight:400"><input type="checkbox" name="product_types[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, (array) itsoc_opt( 'product_types' ), true ), true, false ) . '> ' . esc_html( $l ) . '</label>';
	}
	echo '<label>Post it as</label>';
	foreach ( itsoc_product_formats() as $k => $l ) {
		echo '<label style="font-weight:400"><input type="checkbox" name="product_channels[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, (array) itsoc_opt( 'product_channels' ), true ), true, false ) . '> ' . esc_html( $l ) . '</label>';
	}
	echo '<p class="description">New products (added after the plugin was installed) are posted first; after that tours, activities and transfers take turns and the one posted longest ago goes next. Tick “Skip social autopilot” on a tour or activity to leave it out.</p></div>';

	echo '<div class="itsoc-card"><h3>Text</h3><label>Fixed hashtags (Instagram, LinkedIn, TikTok)</label><input type="text" name="hashtags" value="' . esc_attr( itsoc_opt( 'hashtags' ) ) . '">';
	echo '<p class="description">Place hashtags (#Luxor, #Hurghada …) and topic hashtags are added automatically. Per network: Instagram 15, TikTok 5, LinkedIn 4, Facebook 3, YouTube 3, X 2, Google Business Profile none.</p>';
	echo '<label style="font-weight:400"><input type="checkbox" name="utm" value="1"' . checked( (bool) itsoc_opt( 'utm' ), true, false ) . '> Tag links for Google Analytics (utm_source = network)</label>';
	echo '<label>ffmpeg path (videos)</label><input type="text" name="ffmpeg" value="' . esc_attr( itsoc_opt( 'ffmpeg' ) ) . '" placeholder="found automatically">';
	echo '<p class="description">' . ( itsoc_ffmpeg() ? 'ffmpeg found: <code>' . esc_html( itsoc_ffmpeg() ) . '</code>' : '<b>ffmpeg is not installed on the server</b> – reels, TikTok and Shorts wait until it is.' ) . '</p></div></div>';
	submit_button( 'Save schedule' );
	echo '</form>';
}

/* ---------- Queue ---------- */

function itsoc_tab_queue() {
	global $wpdb;
	$status = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$where  = $status ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';
	$rows   = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}itsoc_jobs $where ORDER BY ( status = 'queued' ) DESC, IF( status = 'queued', run_at, NULL ) ASC, id DESC LIMIT 200" ); // phpcs:ignore
	echo '<p>';
	foreach ( array( '' => 'All', 'queued' => 'Waiting', 'done' => 'Done', 'failed' => 'Failed', 'skipped' => 'Skipped' ) as $k => $l ) {
		echo '<a class="button' . ( $k === $status ? ' button-primary' : '' ) . '" href="' . esc_url( itsoc_admin_url( array( 'tab' => 'queue', 'status' => $k ) ) ) . '">' . esc_html( $l ) . '</a> ';
	}
	echo '</p><table class="widefat striped"><thead><tr><th>When</th><th>What</th><th>Channel</th><th>Status</th><th>Result</th><th></th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="6">Nothing here yet.</td></tr>';
	}
	$labels = array();
	foreach ( $rows as $r ) {
		$key = $r->kind . ':' . $r->object_id;
		if ( ! isset( $labels[ $key ] ) ) {
			$labels[ $key ] = itsoc_key_label( $key );
		}
		$when = strtotime( ( 'queued' === $r->status ? $r->run_at : ( $r->done_at ?: $r->run_at ) ) . ' UTC' );
		echo '<tr><td>' . esc_html( wp_date( 'j M H:i', $when ) ) . '</td><td>' . esc_html( $labels[ $key ] ) . '</td><td>' . esc_html( itsoc_channels()[ $r->channel ]['label'] ?? $r->channel ) . ' – ' . esc_html( $r->format ) . '</td>';
		echo '<td class="itsoc-st-' . esc_attr( $r->status ) . '">' . esc_html( $r->status ) . ( $r->attempts ? ' (' . (int) $r->attempts . ')' : '' ) . '</td><td>';
		if ( $r->remote && 0 === strpos( $r->remote, 'http' ) ) {
			echo '<a href="' . esc_url( $r->remote ) . '" target="_blank">View post</a> ';
		}
		echo esc_html( $r->message ) . '</td><td style="white-space:nowrap">';
		if ( in_array( $r->status, array( 'queued', 'failed', 'skipped' ), true ) ) {
			echo itsoc_btn_link( 'itsoc_job', array( 'do' => 'run', 'id' => $r->id ), 'failed' === $r->status ? 'Retry now' : 'Post now', 'button button-small' ) . ' '; // phpcs:ignore
		}
		if ( 'queued' === $r->status ) {
			echo itsoc_btn_link( 'itsoc_job', array( 'do' => 'cancel', 'id' => $r->id ), 'Cancel', 'button-link-delete' ); // phpcs:ignore
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

/* ---------- Preview ---------- */

function itsoc_tab_preview() {
	$key = sanitize_text_field( wp_unslash( $_GET['item'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $key ) {
		$posts = get_posts( array( 'numberposts' => 1, 'fields' => 'ids' ) );
		$key   = $posts ? 'post:' . $posts[0] : itsoc_pick_product();
	}
	echo '<form method="get"><input type="hidden" name="page" value="itour-social"><input type="hidden" name="tab" value="preview"><select name="item" onchange="this.form.submit()">';
	$groups = array( 'Blog posts' => array(), 'Tours' => array(), 'Activities' => array(), 'Transfers' => array() );
	foreach ( get_posts( array( 'numberposts' => 50, 'post_status' => 'publish' ) ) as $p ) {
		$groups['Blog posts'][ 'post:' . $p->ID ] = get_the_title( $p );
	}
	foreach ( itsoc_product_keys() as $k ) {
		$g                  = array( 'tour' => 'Tours', 'activity' => 'Activities', 'transfer' => 'Transfers' )[ strtok( $k, ':' ) ];
		$groups[ $g ][ $k ] = 'transfer' === strtok( $k, ':' ) ? itsoc_key_label( $k ) : get_the_title( (int) substr( $k, strpos( $k, ':' ) + 1 ) );
	}
	foreach ( $groups as $g => $opts ) {
		echo '<optgroup label="' . esc_attr( $g ) . '">';
		foreach ( $opts as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $key, $k, false ) . '>' . esc_html( wp_strip_all_tags( $l ) ) . '</option>';
		}
		echo '</optgroup>';
	}
	echo '</select></form>';

	list( $kind, $id ) = array_pad( explode( ':', $key, 2 ), 2, '' );
	$item              = $kind ? itsoc_item( $kind, $id ) : null;
	if ( ! $item ) {
		echo '<p>Nothing to preview.</p>';
		return;
	}
	echo '<div class="itsoc-actions">';
	if ( 'post' === $kind ) {
		$started = get_post_meta( (int) $id, '_itsoc_started', true );
		echo $started ? '<span class="itsoc-pill ok">Plan started ' . esc_html( wp_date( 'j M Y', (int) $started ) ) . '</span>' : '';
		echo itsoc_btn_link( 'itsoc_job', array( 'do' => 'start', 'id' => $id ), $started ? 'Run the whole plan again now' : 'Start the whole plan now', 'button button-primary' ); // phpcs:ignore
	} else {
		echo itsoc_btn_link( 'itsoc_job', array( 'do' => 'product', 'id' => $key ), 'Post this product now (daily channels)', 'button button-primary' ); // phpcs:ignore
	}
	echo ' <span class="description">Only channels that are switched on and connected are used; the autopilot must be on.</span></div>';

	try {
		echo '<h2>Carousel (Instagram, LinkedIn, Facebook)</h2><div class="itsoc-slides">';
		foreach ( itsoc_slides( $item ) as $s ) {
			echo '<a href="' . esc_url( $s[1] ) . '" target="_blank"><img src="' . esc_url( $s[1] ) . '" alt=""></a>';
		}
		echo '</div><h2>Story · Google Business Profile photo</h2><div class="itsoc-slides">';
		foreach ( array( itsoc_story( $item ), itsoc_card( $item ), itsoc_card( $item, 1200, 900 ) ) as $s ) {
			echo '<a href="' . esc_url( $s[1] ) . '" target="_blank"><img src="' . esc_url( $s[1] ) . '" alt=""></a>';
		}
		echo '</div>';
		if ( itsoc_ffmpeg() ) {
			try {
				$v = itsoc_video( $item );
				echo '<h2>Video (reels, Shorts, TikTok)</h2><video src="' . esc_url( $v[1] ) . '" controls style="height:420px"></video>';
			} catch ( Itsoc_Error $e ) {
				echo '<h2>Video (reels, Shorts, TikTok)</h2><p class="description">The video for this item has not been made yet – the background worker makes it when the first video post is due.</p>';
			}
		}
	} catch ( Itsoc_Error $e ) {
		echo '<p class="itsoc-pill bad">' . esc_html( $e->getMessage() ) . '</p>';
	}

	echo '<h2>Captions</h2><div class="itsoc-grid">';
	$show = array( 'facebook' => 'post' === $kind ? 'link' : 'image', 'instagram' => 'post' === $kind ? 'carousel' : 'image', 'linkedin' => 'link', 'x' => 'link', 'tiktok' => 'short', 'youtube' => 'short', 'gbp' => 'post' );
	foreach ( $show as $ch => $fmt ) {
		$text = itsoc_caption( $item, $ch, $fmt );
		$len  = 'x' === $ch ? mb_strlen( preg_replace( '#https?://\S+#', str_repeat( 'x', 23 ), $text ) ) : mb_strlen( $text );
		echo '<div class="itsoc-card"><h3>' . esc_html( itsoc_channels()[ $ch ]['label'] ) . ' <span class="itsoc-pill">' . (int) $len . ' characters</span></h3>';
		if ( 'youtube' === $ch ) {
			echo '<p><b>Title:</b> ' . esc_html( itsoc_youtube_title( $item ) ) . '</p>';
		}
		if ( 'gbp' === $ch ) {
			echo '<p><b>Button:</b> ' . esc_html( 'BOOK' === itsoc_gbp_action( $item ) ? 'Book' : 'Learn more' ) . ' → ' . esc_html( $item['url'] ) . '</p>';
		}
		echo '<div class="itsoc-caption">' . esc_html( $text ) . '</div></div>';
	}
	echo '<div class="itsoc-card"><h3>Email <span class="itsoc-pill">Mailchimp</span></h3><p><b>Subject:</b> ' . esc_html( itsoc_email_subject( $item ) ) . '</p><iframe style="width:100%;height:420px;border:1px solid #ddd" srcdoc="' . esc_attr( itsoc_email_html( $item ) ) . '"></iframe></div>';
	echo '</div>';
}

/* ---------- Actions ---------- */

add_action( 'admin_post_itsoc_save', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'itsoc_save' ) ) {
		wp_die( 'Not allowed.' );
	}
	$in      = wp_unslash( $_POST );
	$section = sanitize_key( $in['section'] ?? '' );
	$tab     = 'channels';
	$text    = function ( $k ) use ( $in ) {
		return sanitize_text_field( (string) ( $in[ $k ] ?? '' ) );
	};
	$fields  = array(
		'facebook'  => array( 'fb_app_id', 'fb_app_secret', 'fb_config_id' ),
		'linkedin'  => array( 'li_client_id', 'li_client_secret' ),
		'x'         => array( 'x_api_key', 'x_api_secret', 'x_access_token', 'x_access_secret' ),
		'tiktok'    => array( 'tt_client_key', 'tt_client_secret', 'tt_privacy', 'tt_mode' ),
		'google'    => array( 'g_client_id', 'g_client_secret', 'yt_privacy' ),
		'mailchimp' => array( 'mc_api_key', 'mc_list', 'mc_from_name', 'mc_reply_to' ),
	);
	$save    = array();
	if ( 'toggle' === $section ) {
		$save['enabled'] = (int) ! empty( $in['enabled'] );
		$tab             = 'overview';
	} elseif ( 'schedule' === $section ) {
		$save = array(
			'post_steps'       => array_map( 'sanitize_key', (array) ( $in['post_steps'] ?? array() ) ),
			'product_types'    => array_map( 'sanitize_key', (array) ( $in['product_types'] ?? array() ) ),
			'product_channels' => array_values( array_intersect( (array) ( $in['product_channels'] ?? array() ), array_keys( itsoc_product_formats() ) ) ),
			'daily_hour'       => max( 0, min( 23, (int) ( $in['daily_hour'] ?? 11 ) ) ),
			'backfill_days'    => max( 1, min( 30, (int) ( $in['backfill_days'] ?? 3 ) ) ),
			'hashtags'         => $text( 'hashtags' ),
			'utm'              => (int) ! empty( $in['utm'] ),
			'ffmpeg'           => $text( 'ffmpeg' ),
		);
		$tab  = 'schedule';
	} elseif ( isset( $fields[ $section ] ) ) {
		foreach ( $fields[ $section ] as $k ) {
			$save[ $k ] = $text( $k );
		}
		$channels = (array) itsoc_opt( 'channels' );
		foreach ( array( 'facebook' => array( 'facebook', 'instagram' ), 'linkedin' => array( 'linkedin' ), 'x' => array( 'x' ), 'tiktok' => array( 'tiktok' ), 'google' => array( 'youtube', 'gbp' ), 'mailchimp' => array( 'mailchimp' ) )[ $section ] as $c ) {
			$channels[ $c ] = (int) ! empty( $in['channels'][ $c ] );
		}
		$save['channels'] = $channels;
	}
	itsoc_save( $save );
	if ( ! empty( $in['fb_page_pick'] ) ) {
		itsoc_select_page( $text( 'fb_page_pick' ) );
	}
	if ( ! empty( $in['li_org_pick'] ) && isset( itsoc_opt( 'li_orgs' )[ $text( 'li_org_pick' ) ] ) ) {
		itsoc_save( array( 'li_org_id' => $text( 'li_org_pick' ), 'li_org_name' => itsoc_opt( 'li_orgs' )[ $text( 'li_org_pick' ) ] ) );
	}
	if ( ! empty( $in['gbp_pick'] ) ) {
		itsoc_select_location( $text( 'gbp_pick' ) );
	}
	if ( 'schedule' === $section ) {
		itsoc_schedule();
	}
	itsoc_notice( 'toggle' === $section ? ( itsoc_opt( 'enabled' ) ? 'Autopilot is on.' : 'Autopilot paused.' ) : 'Saved.' );
	wp_safe_redirect( itsoc_admin_url( array( 'tab' => $tab ) ) );
	exit;
} );

add_action( 'admin_post_itsoc_test', function () {
	$ch = sanitize_key( $_GET['channel'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'itsoc_test_' . $ch ) || ! isset( itsoc_channels()[ $ch ] ) ) {
		wp_die( 'Not allowed.' );
	}
	try {
		itsoc_notice( '✓ ' . call_user_func( 'itsoc_test_' . $ch ) );
	} catch ( Throwable $e ) {
		itsoc_notice( itsoc_channels()[ $ch ]['label'] . ': ' . $e->getMessage(), 'error' );
	}
	wp_safe_redirect( itsoc_admin_url( array( 'tab' => 'channels' ) ) );
	exit;
} );

add_action( 'admin_post_itsoc_job', function () {
	global $wpdb;
	$do = sanitize_key( $_GET['do'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'itsoc_job_' . $do ) ) {
		wp_die( 'Not allowed.' );
	}
	$id   = sanitize_text_field( wp_unslash( $_GET['id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$back = itsoc_admin_url( array( 'tab' => 'queue' ) );
	if ( ! itsoc_opt( 'enabled' ) && in_array( $do, array( 'run', 'start', 'product' ), true ) ) {
		itsoc_notice( 'Switch the autopilot on first (Overview tab).', 'error' );
		wp_safe_redirect( wp_get_referer() ?: $back );
		exit;
	}
	if ( 'run' === $do ) {
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}itsoc_jobs WHERE id = %d", (int) $id ) );
		if ( $job && in_array( $job->format, array( 'reel', 'short' ), true ) && ! itsoc_can_exec() ) {
			// Videos can only be rendered by the background worker on this host.
			itsoc_job_update( $job->id, array( 'status' => 'queued', 'attempts' => 0, 'run_at' => current_time( 'mysql', true ), 'message' => 'Queued – the background worker makes the video and posts it within 15 minutes.' ) );
			itsoc_notice( 'Video queued – the background worker will make and post it within 15 minutes.' );
		} elseif ( $job ) {
			itsoc_job_update( $job->id, array( 'status' => 'queued', 'attempts' => 0 ) );
			$job->attempts = 0;
			itsoc_run_job( $job );
			$after = $wpdb->get_row( $wpdb->prepare( "SELECT status, message FROM {$wpdb->prefix}itsoc_jobs WHERE id = %d", $job->id ) );
			itsoc_notice( 'Result: ' . $after->status . ( $after->message ? ' – ' . $after->message : '' ), 'done' === $after->status ? 'success' : 'error' );
		}
	} elseif ( 'cancel' === $do ) {
		itsoc_job_update( (int) $id, array( 'status' => 'skipped', 'message' => 'Cancelled by ' . wp_get_current_user()->display_name, 'done_at' => current_time( 'mysql', true ) ) );
		itsoc_notice( 'Cancelled.' );
	} elseif ( 'start' === $do ) {
		$n = itsoc_start_post( (int) $id );
		itsoc_notice( $n ? "$n posts planned for this article – the first ones go out within 10 minutes." : 'No channel is switched on and connected yet.', $n ? 'success' : 'error' );
	} elseif ( 'product' === $do ) {
		$n = itsoc_post_product( $id );
		itsoc_notice( $n ? "$n posts planned – they go out within 10 minutes." : 'No channel is switched on and connected yet.', $n ? 'success' : 'error' );
	}
	wp_safe_redirect( $back );
	exit;
} );

/* ---------- Per-post box ---------- */

add_action( 'add_meta_boxes', function () {
	foreach ( array( 'post', 'itour_tour', 'itour_excursion' ) as $type ) {
		add_meta_box( 'itsoc-box', 'Social Auto Pilot', 'itsoc_meta_box', $type, 'side' );
	}
} );

function itsoc_meta_box( $post ) {
	global $wpdb;
	wp_nonce_field( 'itsoc_box', 'itsoc_box_nonce' );
	echo '<label><input type="checkbox" name="itsoc_skip" value="1"' . checked( (bool) get_post_meta( $post->ID, '_itsoc_skip', true ), true, false ) . '> Skip social autopilot</label>';
	$kind = array( 'post' => 'post', 'itour_tour' => 'tour', 'itour_excursion' => 'activity' )[ $post->post_type ];
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) n FROM {$wpdb->prefix}itsoc_jobs WHERE kind = %s AND object_id = %s GROUP BY status", $kind, (string) $post->ID ) );
	if ( $rows ) {
		$parts = array();
		foreach ( $rows as $r ) {
			$parts[] = (int) $r->n . ' ' . $r->status;
		}
		echo '<p>' . esc_html( implode( ' · ', $parts ) ) . '</p>';
	}
	echo '<p><a href="' . esc_url( itsoc_admin_url( array( 'tab' => 'preview', 'item' => $kind . ':' . $post->ID ) ) ) . '">Preview social posts →</a></p>';
}

add_action( 'save_post', function ( $post_id ) {
	if ( ! isset( $_POST['itsoc_box_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['itsoc_box_nonce'] ), 'itsoc_box' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! empty( $_POST['itsoc_skip'] ) ) {
		update_post_meta( $post_id, '_itsoc_skip', 1 );
	} else {
		delete_post_meta( $post_id, '_itsoc_skip' );
	}
}, 5 );
