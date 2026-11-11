<?php
/**
 * The queue: every planned post is a row in {prefix}itsoc_jobs with its due time.
 * - A new blog post starts the 7-step plan 10 minutes after it is published.
 * - Older blog posts are worked through one at a time, every few days (setting).
 * - Every day one tour, activity or transfer is posted: new products first, then whichever was posted longest ago.
 * - The worker (every 10 minutes) sends what is due and retries temporary errors.
 */

defined( 'ABSPATH' ) || exit;

/** The blog-post plan: step => [channel, format, hours after start]. */
function itsoc_post_plan() {
	return array(
		'link'     => array( array( 'facebook', 'link', 0 ), array( 'linkedin', 'link', 0 ), array( 'x', 'link', 0 ) ),
		'reel'     => array( array( 'facebook', 'reel', 3 ), array( 'instagram', 'reel', 3 ) ),
		'short'    => array( array( 'tiktok', 'short', 6 ), array( 'youtube', 'short', 6 ) ),
		'carousel' => array( array( 'instagram', 'carousel', 24 ), array( 'linkedin', 'carousel', 26 ), array( 'facebook', 'carousel', 28 ) ),
		'story'    => array( array( 'instagram', 'story', 30 ), array( 'facebook', 'story', 30 ) ),
		'email'    => array( array( 'mailchimp', 'email', 48 ) ),
		'gbp'      => array( array( 'gbp', 'post', 1 ), array( 'gbp', 'post', 24 * 7 ), array( 'gbp', 'post', 24 * 14 ) ),
	);
}

/** What a daily product post may use. */
function itsoc_product_formats() {
	return array(
		'facebook:image'  => 'Facebook – photo post',
		'facebook:link'   => 'Facebook – link post',
		'facebook:story'  => 'Facebook – story',
		'facebook:reel'   => 'Facebook – reel (video)',
		'instagram:image' => 'Instagram – photo post',
		'instagram:story' => 'Instagram – story',
		'instagram:reel'  => 'Instagram – reel (video)',
		'linkedin:link'   => 'LinkedIn – link post',
		'x:link'          => 'X – post with link',
		'tiktok:short'    => 'TikTok – video',
		'youtube:short'   => 'YouTube – Short',
		'gbp:post'        => 'Google Business Profile – update',
	);
}

function itsoc_enqueue( $kind, $object_id, $channel, $format, $run_at ) {
	global $wpdb;
	$wpdb->insert( $wpdb->prefix . 'itsoc_jobs', array(
		'kind'      => $kind,
		'object_id' => (string) $object_id,
		'channel'   => $channel,
		'format'    => $format,
		'run_at'    => gmdate( 'Y-m-d H:i:s', $run_at ),
		'status'    => 'queued',
		'created'   => current_time( 'mysql', true ),
	) );
	return (int) $wpdb->insert_id;
}

/** Queues the full plan for one blog post. Only channels that are switched on and connected get a job. */
function itsoc_start_post( $post_id, $start = null ) {
	$start = $start ?: time();
	$steps = (array) itsoc_opt( 'post_steps' );
	$n     = 0;
	foreach ( itsoc_post_plan() as $step => $jobs ) {
		if ( ! in_array( $step, $steps, true ) ) {
			continue;
		}
		foreach ( $jobs as $j ) {
			if ( itsoc_ready( $j[0] ) && ( ! in_array( $j[1], array( 'reel', 'short' ), true ) || itsoc_ffmpeg() ) ) {
				itsoc_enqueue( 'post', $post_id, $j[0], $j[1], $start + (int) ( $j[2] * HOUR_IN_SECONDS ) );
				$n++;
			}
		}
	}
	if ( $n ) { // Nothing connected yet: the post keeps its turn.
		update_post_meta( $post_id, '_itsoc_started', $start );
		update_option( 'itsoc_last_post_start', $start, false );
	}
	return $n;
}

// A newly published blog post starts its plan 10 minutes later (time to fix a typo first).
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' !== $new || 'publish' === $old || 'post' !== $post->post_type || ! itsoc_opt( 'enabled' ) ) {
		return;
	}
	if ( get_post_meta( $post->ID, '_itsoc_started', true ) || get_post_meta( $post->ID, '_itsoc_skip', true ) || ! empty( $_POST['itsoc_skip'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- the box is saved (with its nonce) right after this hook.
		return;
	}
	itsoc_start_post( $post->ID, time() + 10 * MINUTE_IN_SECONDS );
}, 10, 3 );

add_action( 'itsoc_daily', 'itsoc_daily' );

function itsoc_daily() {
	if ( ! itsoc_opt( 'enabled' ) ) {
		return;
	}
	itsoc_backfill_next();
	itsoc_post_product();
}

/** Next older blog post (newest first) once the gap since the last one has passed. */
function itsoc_backfill_next( $force = false ) {
	$gap = max( 1, (int) itsoc_opt( 'backfill_days' ) ) * DAY_IN_SECONDS - 2 * HOUR_IN_SECONDS;
	if ( ! $force && time() - (int) get_option( 'itsoc_last_post_start', 0 ) < $gap ) {
		return 0;
	}
	$next = get_posts( array(
		'post_type'   => 'post',
		'post_status' => 'publish',
		'numberposts' => 1,
		'fields'      => 'ids',
		'orderby'     => 'date',
		'order'       => 'DESC',
		'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => '_itsoc_started', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_itsoc_skip', 'compare' => 'NOT EXISTS' ),
		),
	) );
	return $next && itsoc_start_post( $next[0] ) ? $next[0] : 0;
}

/**
 * Today's product. New ones (added after the plugin was installed and never posted) come first, newest first;
 * otherwise the kinds take turns (tour → activity → transfer) and the one posted longest ago wins.
 */
function itsoc_pick_product() {
	$types = (array) itsoc_opt( 'product_types' );
	$log   = (array) get_option( 'itsoc_product_log', array() );
	$seen  = (array) get_option( 'itsoc_seen', array() );
	$keys  = array_values( array_filter( itsoc_product_keys(), function ( $k ) use ( $types ) {
		list( $kind, $id ) = explode( ':', $k, 2 );
		return in_array( $kind, $types, true ) && ( 'transfer' === $kind || ! get_post_meta( (int) $id, '_itsoc_skip', true ) );
	} ) );
	if ( ! $keys ) {
		return '';
	}
	foreach ( $keys as $k ) {
		if ( ! isset( $seen[ $k ] ) && ! isset( $log[ $k ] ) ) {
			return $k;
		}
	}
	$last  = (string) get_option( 'itsoc_last_product_kind', '' );
	$order = array_values( array_intersect( array( 'tour', 'activity', 'transfer' ), $types ) );
	$pos   = array_search( $last, $order, true );
	$turn  = array_merge( array_slice( $order, false === $pos ? 0 : $pos + 1 ), array_slice( $order, 0, false === $pos ? 0 : $pos + 1 ) );
	foreach ( $turn as $kind ) {
		$pool = array_filter( $keys, function ( $k ) use ( $kind ) {
			return 0 === strpos( $k, $kind . ':' );
		} );
		if ( $pool ) {
			usort( $pool, function ( $a, $b ) use ( $log ) {
				return ( $log[ $a ] ?? 0 ) <=> ( $log[ $b ] ?? 0 );
			} );
			return reset( $pool );
		}
	}
	return '';
}

function itsoc_post_product( $key = '' ) {
	$key = $key ?: itsoc_pick_product();
	if ( ! $key ) {
		return 0;
	}
	list( $kind, $id ) = explode( ':', $key, 2 );
	$n                 = 0;
	foreach ( (array) itsoc_opt( 'product_channels' ) as $i => $cf ) {
		list( $channel, $format ) = explode( ':', $cf );
		if ( itsoc_ready( $channel ) && ( ! in_array( $format, array( 'reel', 'short' ), true ) || itsoc_ffmpeg() ) ) {
			itsoc_enqueue( $kind, $id, $channel, $format, time() + $i * 3 * MINUTE_IN_SECONDS );
			$n++;
		}
	}
	if ( ! $n ) { // Nothing connected yet: the product keeps its turn.
		return 0;
	}
	$log         = (array) get_option( 'itsoc_product_log', array() );
	$log[ $key ] = time();
	update_option( 'itsoc_product_log', $log, false );
	$seen         = (array) get_option( 'itsoc_seen', array() );
	$seen[ $key ] = 1;
	update_option( 'itsoc_seen', $seen, false );
	update_option( 'itsoc_last_product_kind', $kind, false );
	return $n;
}

add_action( 'itsoc_tick', 'itsoc_tick' );

/** Sends what is due (a few per run so one slow network never blocks the site's cron). */
function itsoc_tick() {
	global $wpdb;
	if ( ! itsoc_opt( 'enabled' ) || get_transient( 'itsoc_lock' ) ) {
		return;
	}
	set_transient( 'itsoc_lock', 1, 9 * MINUTE_IN_SECONDS );
	$jobs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}itsoc_jobs WHERE status = 'queued' AND run_at <= %s ORDER BY run_at ASC LIMIT 4", current_time( 'mysql', true ) ) );
	foreach ( $jobs as $job ) {
		itsoc_run_job( $job );
	}
	delete_transient( 'itsoc_lock' );
}

function itsoc_job_update( $id, array $data ) {
	global $wpdb;
	$wpdb->update( $wpdb->prefix . 'itsoc_jobs', $data, array( 'id' => $id ) );
}

/** Runs one job now and records the result. */
function itsoc_run_job( $job ) {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$item = itsoc_item( $job->kind, $job->object_id );
	if ( ! $item ) {
		return itsoc_job_update( $job->id, array( 'status' => 'skipped', 'message' => 'No longer published.', 'done_at' => current_time( 'mysql', true ) ) );
	}
	if ( 'transfer' !== $job->kind && get_post_meta( (int) $job->object_id, '_itsoc_skip', true ) ) {
		return itsoc_job_update( $job->id, array( 'status' => 'skipped', 'message' => 'Marked “Skip social autopilot”.', 'done_at' => current_time( 'mysql', true ) ) );
	}
	if ( ! itsoc_ready( $job->channel ) ) {
		return itsoc_job_update( $job->id, array( 'status' => 'skipped', 'message' => 'Channel switched off or not connected.', 'done_at' => current_time( 'mysql', true ) ) );
	}
	$state = $job->state ? (array) json_decode( $job->state, true ) : array();
	try {
		$fn = 'itsoc_send_' . $job->channel;
		$r  = $fn( $job->format, $item, $state );
		if ( ! empty( $r['wait'] ) ) { // Still processing on the network's side (videos): check again later.
			$waits = (int) ( $state['_waits'] ?? 0 ) + 1;
			if ( $waits > 18 ) {
				throw new Itsoc_Error( 'The network did not finish processing the video in time.' );
			}
			return itsoc_job_update( $job->id, array(
				'state'   => wp_json_encode( array_merge( $r['wait'], array( '_waits' => $waits ) ) ),
				'run_at'  => gmdate( 'Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS ),
				'message' => 'Processing on ' . itsoc_channels()[ $job->channel ]['label'] . '…',
			) );
		}
		itsoc_job_update( $job->id, array(
			'status'  => 'done',
			'remote'  => mb_substr( (string) ( $r['remote'] ?? '' ), 0, 250 ),
			'message' => (string) ( $r['note'] ?? '' ),
			'state'   => null,
			'done_at' => current_time( 'mysql', true ),
		) );
	} catch ( Itsoc_Error $e ) {
		$attempts = (int) $job->attempts + 1;
		$again    = $e->retry && $attempts < 4;
		itsoc_job_update( $job->id, array(
			'status'   => $again ? 'queued' : 'failed',
			'attempts' => $attempts,
			'run_at'   => gmdate( 'Y-m-d H:i:s', time() + $attempts * 20 * MINUTE_IN_SECONDS ),
			'message'  => mb_substr( $e->getMessage(), 0, 1000 ),
			'done_at'  => $again ? null : current_time( 'mysql', true ),
		) );
	} catch ( Throwable $e ) {
		itsoc_job_update( $job->id, array(
			'status'   => 'failed',
			'attempts' => (int) $job->attempts + 1,
			'message'  => mb_substr( 'Error: ' . $e->getMessage(), 0, 1000 ),
			'done_at'  => current_time( 'mysql', true ),
		) );
	}
}
