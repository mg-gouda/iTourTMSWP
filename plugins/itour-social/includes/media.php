<?php
/**
 * Images and videos made from a post or product: carousel slides (1080×1350), a story (1080×1920),
 * and a vertical video for reels / Shorts / TikTok (needs ffmpeg on the server).
 * Files go to uploads/itour-social/<item>/ – the networks fetch them from there by URL.
 */

defined( 'ABSPATH' ) || exit;

/** Theme colour as [r, g, b]: brand (accent) or brand-dark (backgrounds); iTour plum when unknown. */
function itsoc_rgb( $slug ) {
	$hex = 'brand' === $slug ? '#a64ca6' : '#3d003d';
	foreach ( (array) wp_get_global_settings( array( 'color', 'palette', 'theme' ) ) as $c ) {
		if ( $slug === ( $c['slug'] ?? '' ) && preg_match( '/^#[0-9a-f]{6}$/i', (string) $c['color'] ) ) {
			$hex = (string) $c['color'];
		}
	}
	return array_map( 'hexdec', str_split( ltrim( $hex, '#' ), 2 ) );
}

/** The site address without http(s):// and www., e.g. "example.com". */
function itsoc_domain() {
	return preg_replace( '#^www\.#', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
}

function itsoc_font( $bold = false ) {
	foreach ( $bold ? array( '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', ITSOC_DIR . 'assets/DejaVuSans-Bold.ttf', WP_PLUGIN_DIR . '/itour-documents/vendor/mpdf/mpdf/ttfonts/DejaVuSans-Bold.ttf' ) : array( '/usr/share/fonts/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', ITSOC_DIR . 'assets/DejaVuSans.ttf', WP_PLUGIN_DIR . '/itour-documents/vendor/mpdf/mpdf/ttfonts/DejaVuSans.ttf' ) as $f ) {
		if ( is_readable( $f ) ) {
			return $f;
		}
	}
	throw new Itsoc_Error( 'No TrueType font found for the images.' );
}

/** Folder and URL for one item; the version hash changes when the title, text or photo changes. */
function itsoc_media_dir( array $item ) {
	$up   = wp_upload_dir();
	$slug = sanitize_file_name( $item['kind'] . '-' . $item['id'] );
	$dir  = trailingslashit( $up['basedir'] ) . 'itour-social/' . $slug;
	wp_mkdir_p( $dir );
	$ver = substr( md5( wp_json_encode( array( $item['title'], $item['summary'], $item['points'], $item['image_ids'], $item['price'], ITSOC_VER, filemtime( __FILE__ ) ) ) ), 0, 8 );
	if ( @file_get_contents( "$dir/.ver" ) !== $ver ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- Content changed: old images and videos go.
		array_map( 'unlink', array_merge( glob( "$dir/*.jpg" ) ?: array(), glob( "$dir/*.mp4" ) ?: array() ) ); // No GLOB_BRACE: missing on musl (Alpine) PHP.
		file_put_contents( "$dir/.ver", $ver );
	}
	return array( $dir, trailingslashit( $up['baseurl'] ) . 'itour-social/' . $slug, $ver );
}

function itsoc_gd_load_file( $file ) {
	if ( ! $file || ! is_readable( $file ) ) {
		return null;
	}
	$data = file_get_contents( $file );
	$im   = $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	return $im ?: null;
}

function itsoc_gd_photo( $attachment_id ) {
	return $attachment_id ? itsoc_gd_load_file( get_attached_file( (int) $attachment_id ) ) : null;
}

/** New $w×$h canvas filled with $src scaled to cover it (centre crop), or navy when there is no photo. */
function itsoc_gd_cover( $src, $w, $h ) {
	$im = imagecreatetruecolor( $w, $h );
	imagefill( $im, 0, 0, imagecolorallocate( $im, ...itsoc_rgb( 'brand-dark' ) ) );
	if ( $src ) {
		$sw    = imagesx( $src );
		$sh    = imagesy( $src );
		$scale = max( $w / $sw, $h / $sh );
		$cw    = (int) round( $w / $scale );
		$ch    = (int) round( $h / $scale );
		imagecopyresampled( $im, $src, 0, 0, (int) ( ( $sw - $cw ) / 2 ), (int) ( ( $sh - $ch ) / 2 ), $w, $h, $cw, $ch );
	}
	return $im;
}

/** Darkens from $from (0..1 of height) downwards so white text stays readable on any photo. */
function itsoc_gd_shade( $im, $from = 0.35, $max = 105, $top = 0 ) {
	$w = imagesx( $im );
	$h = imagesy( $im );
	imagealphablending( $im, true );
	$start = (int) ( $h * $from );
	for ( $y = $start; $y < $h; $y++ ) {
		$a = 127 - (int) ( $max * ( ( $y - $start ) / max( 1, $h - $start ) ) );
		imageline( $im, 0, $y, $w, $y, imagecolorallocatealpha( $im, 0, 0, 0, max( 0, $a ) ) );
	}
	if ( $top ) { // A light band at the top for the logo.
		for ( $y = 0; $y < $top; $y++ ) {
			imageline( $im, 0, $y, $w, $y, imagecolorallocatealpha( $im, 0, 0, 0, 127 - (int) ( 70 * ( 1 - $y / $top ) ) ) );
		}
	}
}

function itsoc_gd_wrap( $text, $size, $font, $max_w ) {
	$lines = array();
	$line  = '';
	foreach ( preg_split( '/\s+/u', trim( $text ) ) as $word ) {
		$try = '' === $line ? $word : $line . ' ' . $word;
		$box = imagettfbbox( $size, 0, $font, $try );
		if ( ( $box[2] - $box[0] ) > $max_w && '' !== $line ) {
			$lines[] = $line;
			$line    = $word;
		} else {
			$line = $try;
		}
	}
	if ( '' !== $line ) {
		$lines[] = $line;
	}
	return $lines;
}

/** Writes wrapped text; shrinks the font until it fits in $max_lines. Returns the y below the text. */
function itsoc_gd_text( $im, $text, $size, $bold, $x, $y, $max_w, $color, $max_lines = 4, $line_h = 1.25 ) {
	$font = itsoc_font( $bold );
	do {
		$lines = itsoc_gd_wrap( $text, $size, $font, $max_w );
		$size -= count( $lines ) > $max_lines ? 3 : 0;
	} while ( count( $lines ) > $max_lines && $size > 18 );
	$lines = array_slice( $lines, 0, $max_lines );
	$c     = imagecolorallocate( $im, ...$color );
	foreach ( $lines as $l ) {
		$y += (int) ( $size * $line_h );
		imagettftext( $im, $size, 0, $x, $y, $c, $font, $l );
	}
	return $y + (int) ( $size * 0.5 );
}

/** Height a block of text would take (same shrinking as itsoc_gd_text). */
function itsoc_gd_text_h( $text, $size, $bold, $max_w, $max_lines = 4, $line_h = 1.25 ) {
	$font = itsoc_font( $bold );
	do {
		$lines = itsoc_gd_wrap( $text, $size, $font, $max_w );
		$size -= count( $lines ) > $max_lines ? 3 : 0;
	} while ( count( $lines ) > $max_lines && $size > 18 );
	return (int) ( min( $max_lines, count( $lines ) ) * $size * $line_h + $size * 0.5 );
}

/** Orange pill label, e.g. TRAVEL GUIDE. Returns its height. */
function itsoc_gd_label( $im, $text, $x, $y, $size = 22 ) {
	$font = itsoc_font( true );
	$box  = imagettfbbox( $size, 0, $font, $text );
	$w    = $box[2] - $box[0] + 40;
	$h    = $size + 26;
	imagefilledrectangle( $im, $x, $y, $x + $w, $y + $h, imagecolorallocate( $im, ...itsoc_rgb( 'brand' ) ) );
	imagettftext( $im, $size, 0, $x + 20, $y + $size + 12, imagecolorallocate( $im, 255, 255, 255 ), $font, $text );
	return $h;
}

/** The light logo from Theme Options (white, for dark photos), else the main one, else the site address. */
function itsoc_gd_logo( $im, $x, $y, $max_w = 230 ) {
	$o    = function_exists( 'wp_itourtms_options' ) ? wp_itourtms_options() : array();
	$id   = (int) ( ( $o['logo_light'] ?? 0 ) ?: ( $o['logo_image'] ?? 0 ) ?: get_theme_mod( 'custom_logo' ) );
	$file = $id ? (string) get_attached_file( $id ) : '';
	$logo = itsoc_gd_load_file( $file );
	if ( $logo ) {
		$lw = imagesx( $logo );
		$lh = imagesy( $logo );
		$w  = min( $max_w, $lw );
		$h  = (int) round( $lh * $w / $lw );
		imagealphablending( $im, true );
		imagecopyresampled( $im, $logo, $x, $y, 0, 0, $w, $h, $lw, $lh );
		return;
	}
	imagettftext( $im, 30, 0, $x, $y + 34, imagecolorallocate( $im, 255, 255, 255 ), itsoc_font( true ), strtoupper( itsoc_domain() ) );
}

function itsoc_kind_label( array $item ) {
	return array( 'post' => 'TRAVEL GUIDE', 'tour' => 'TOUR PACKAGE', 'activity' => 'DAY TRIP', 'transfer' => 'AIRPORT TRANSFER' )[ $item['kind'] ] ?? 'TRAVEL';
}

/** Slide 1 / product card / story: photo, label, title, price, site. */
function itsoc_draw_cover( array $item, $w, $h, $photo_id = 0 ) {
	$im  = itsoc_gd_cover( itsoc_gd_photo( $photo_id ?: $item['image_id'] ), $w, $h );
	$pad = (int) ( $w * 0.07 );
	$tw  = $w - 2 * $pad;
	itsoc_gd_shade( $im, $h > $w * 1.5 ? 0.40 : ( $w > $h ? 0.15 : 0.30 ), 122, 190 );
	itsoc_gd_logo( $im, $pad, (int) ( $pad * 0.8 ) );

	$title_size = $h > $w * 1.5 ? 70 : ( $w > $h ? 50 : 64 );
	$block      = 70 + itsoc_gd_text_h( $item['title'], $title_size, true, $tw, 4 ) + ( $item['price'] ? 60 : 0 ) + ( $h > $w * 1.5 ? itsoc_gd_text_h( $item['summary'], 34, false, $tw, 4 ) : 0 ) + 90;
	$y          = $h - $pad - $block;
	$y         += itsoc_gd_label( $im, itsoc_kind_label( $item ), $pad, $y ) + 10;
	$y          = itsoc_gd_text( $im, $item['title'], $title_size, true, $pad, $y, $tw, array( 255, 255, 255 ), 4, 1.2 );
	if ( $item['price'] ) {
		$y = itsoc_gd_text( $im, $item['price'], 34, true, $pad, $y, $tw, itsoc_rgb( 'brand' ), 1 );
	}
	if ( $h > $w * 1.5 ) {
		$y = itsoc_gd_text( $im, $item['summary'], 34, false, $pad, $y, $tw, array( 235, 235, 235 ), 4, 1.35 );
	}
	itsoc_gd_text( $im, ( 'post' === $item['kind'] ? 'Read more on ' : 'Book on ' ) . itsoc_domain(), 30, true, $pad, $h - $pad - 60, $tw, array( 255, 255, 255 ), 1 );
	return $im;
}

/** Slides 2..n: one section per slide on a darkened, blurred photo. */
function itsoc_draw_point( array $item, $n, $total, $head, $text, $w, $h, $photo_id ) {
	$im = itsoc_gd_cover( itsoc_gd_photo( $photo_id ), $w, $h );
	for ( $i = 0; $i < 12; $i++ ) {
		imagefilter( $im, IMG_FILTER_GAUSSIAN_BLUR );
	}
	imagefilledrectangle( $im, 0, 0, $w, $h, imagecolorallocatealpha( $im, ...array_merge( itsoc_rgb( 'brand-dark' ), array( 30 ) ) ) );
	$pad = (int) ( $w * 0.09 );
	$tw  = $w - 2 * $pad;
	itsoc_gd_logo( $im, $pad, $pad, 180 );

	$font = itsoc_font( true );
	$num  = (string) $n;
	$cy   = (int) ( $h * 0.30 );
	imagefilledellipse( $im, $pad + 55, $cy, 110, 110, imagecolorallocate( $im, ...itsoc_rgb( 'brand' ) ) );
	$box = imagettfbbox( 48, 0, $font, $num );
	imagettftext( $im, 48, 0, $pad + 55 - (int) ( ( $box[2] - $box[0] ) / 2 ), $cy + 24, imagecolorallocate( $im, 255, 255, 255 ), $font, $num );

	$y = itsoc_gd_text( $im, $head, 58, true, $pad, $cy + 90, $tw, array( 255, 255, 255 ), 3, 1.2 );
	if ( '' !== $text ) {
		itsoc_gd_text( $im, $text, 36, false, $pad, $y + 10, $tw, array( 230, 230, 230 ), 6, 1.4 );
	}
	$foot = $n . ' / ' . $total . '   ·   ' . itsoc_domain();
	imagettftext( $im, 26, 0, $pad, $h - $pad, imagecolorallocate( $im, ...itsoc_rgb( 'brand' ) ), $font, $foot );
	return $im;
}

/** Last slide: call to action. */
function itsoc_draw_cta( array $item, $w, $h ) {
	$im = imagecreatetruecolor( $w, $h );
	imagefill( $im, 0, 0, imagecolorallocate( $im, ...itsoc_rgb( 'brand-dark' ) ) );
	$pad = (int) ( $w * 0.09 );
	$tw  = $w - 2 * $pad;
	itsoc_gd_logo( $im, $pad, $pad, 260 );
	$y = (int) ( $h * 0.32 );
	$y = itsoc_gd_text( $im, 'post' === $item['kind'] ? 'Plan your trip' : 'Ready to go?', 66, true, $pad, $y, $tw, array( 255, 255, 255 ), 2 );
	$y = itsoc_gd_text( $im, 'post' === $item['kind'] ? 'Read the full guide and book tours, day trips and airport transfers with local experts.' : 'Book online in minutes with local experts.', 38, false, $pad, $y + 10, $tw, array( 225, 225, 225 ), 5, 1.4 );
	$y += 40;
	imagefilledrectangle( $im, $pad, $y, $pad + 520, $y + 100, imagecolorallocate( $im, ...itsoc_rgb( 'brand' ) ) );
	imagettftext( $im, 40, 0, $pad + 40, $y + 67, imagecolorallocate( $im, 255, 255, 255 ), itsoc_font( true ), itsoc_domain() );
	return $im;
}

function itsoc_save_jpg( $im, $file ) {
	imageinterlace( $im, true );
	imagejpeg( $im, $file, 88 );
	imagedestroy( $im );
}

/** Slides as [file, url] pairs. $w×$h 1080×1350 for feeds, 1080×1920 for the video. */
function itsoc_slides( array $item, $w = 1080, $h = 1350, $max_points = 6 ) {
	list( $dir, $url, $ver ) = itsoc_media_dir( $item );
	$tag    = $w . 'x' . $h;
	$points = array_slice( $item['points'], 0, $max_points );
	$photos = $item['image_ids'] ?: array( 0 );
	$total  = count( $points ) + 2;
	$out    = array();
	for ( $i = 0; $i < $total; $i++ ) {
		$name = sprintf( '%s-%s-%02d.jpg', $tag, $ver, $i + 1 );
		$file = $dir . '/' . $name;
		if ( ! file_exists( $file ) ) {
			if ( 0 === $i ) {
				$im = itsoc_draw_cover( $item, $w, $h );
			} elseif ( $i === $total - 1 ) {
				$im = itsoc_draw_cta( $item, $w, $h );
			} else {
				$im = itsoc_draw_point( $item, $i, $total - 2, $points[ $i - 1 ][0], $points[ $i - 1 ][1], $w, $h, $photos[ $i % count( $photos ) ] );
			}
			itsoc_save_jpg( $im, $file );
		}
		$out[] = array( $file, $url . '/' . $name );
	}
	return $out;
}

/** Single feed image: 1080×1350 portrait for social feeds, 1200×900 for Google Business Profile. */
function itsoc_card( array $item, $w = 1080, $h = 1350 ) {
	list( $dir, $url, $ver ) = itsoc_media_dir( $item );
	$name = 'card-' . $w . 'x' . $h . '-' . $ver . '.jpg';
	if ( ! file_exists( "$dir/$name" ) ) {
		itsoc_save_jpg( itsoc_draw_cover( $item, $w, $h ), "$dir/$name" );
	}
	return array( "$dir/$name", "$url/$name" );
}

function itsoc_story( array $item ) {
	list( $dir, $url, $ver ) = itsoc_media_dir( $item );
	$name = 'story-' . $ver . '.jpg';
	if ( ! file_exists( "$dir/$name" ) ) {
		itsoc_save_jpg( itsoc_draw_cover( $item, 1080, 1920 ), "$dir/$name" );
	}
	return array( "$dir/$name", "$url/$name" );
}

/**
 * Path to ffmpeg, or '' when the server has none.
 * Web requests on this host cannot run programs (exec is disabled for PHP-FPM), but the background cron (command-line PHP) can,
 * so videos are always made by the worker; see itsoc_can_exec().
 */
function itsoc_ffmpeg() {
	static $found = null;
	if ( null !== $found ) {
		return $found;
	}
	$found = '';
	foreach ( array_filter( array( itsoc_opt( 'ffmpeg' ), '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', dirname( ABSPATH ) . '/bin/ffmpeg' ) ) as $f ) {
		if ( @is_executable( $f ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$found = $f;
			break;
		}
	}
	return $found;
}

/** Can this PHP process start programs? (No in web requests on this host, yes in the cron worker.) */
function itsoc_can_exec() {
	return function_exists( 'exec' ) && ! in_array( 'exec', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true );
}

/** Vertical MP4 (1080×1920, ~4 s per slide, slow pan, silent audio track) for reels, Shorts and TikTok. */
function itsoc_video( array $item ) {
	$ff = itsoc_ffmpeg();
	if ( ! $ff ) {
		throw new Itsoc_Error( 'Videos need ffmpeg on the server – not installed yet.' );
	}
	list( $dir, $url, $ver ) = itsoc_media_dir( $item );
	$name = 'video-' . $ver . '.mp4';
	if ( file_exists( "$dir/$name" ) ) {
		return array( "$dir/$name", "$url/$name" );
	}
	if ( ! itsoc_can_exec() ) {
		throw new Itsoc_Error( 'The video is made by the background worker – it will be ready within 15 minutes.', true );
	}
	// One short clip per slide (a slow pan across a slightly larger image – light on memory and CPU), then joined without re-encoding.
	$slides = itsoc_slides( $item, 1080, 1920, 5 );
	$per    = 4;
	$list   = '';
	$parts  = array();
	foreach ( $slides as $i => $s ) {
		$part    = "$dir/tmp-part-$i.mp4";
		$dx      = 0 === $i % 2 ? "(iw-ow)*t/$per" : "(iw-ow)*(1-t/$per)";
		$vf      = "scale=1188:2112,crop=1080:1920:'$dx':'(ih-oh)*t/$per',fps=30,format=yuv420p";
		$parts[] = $part;
		itsoc_ffmpeg_run( '-loop 1 -t ' . $per . ' -i ' . escapeshellarg( $s[0] ) . ' -f lavfi -t ' . $per . ' -i anullsrc=r=44100:cl=stereo -vf ' . escapeshellarg( $vf ) . ' -c:v libx264 -preset veryfast -crf 23 -threads 2 -c:a aac -b:a 96k -shortest ' . escapeshellarg( $part ), $parts );
		$list .= "file '" . basename( $part ) . "'\n";
	}
	file_put_contents( "$dir/tmp-list.txt", $list );
	$parts[] = "$dir/tmp-list.txt";
	$tmp     = "$dir/tmp-$name";
	$parts[] = $tmp;
	itsoc_ffmpeg_run( '-f concat -safe 0 -i ' . escapeshellarg( "$dir/tmp-list.txt" ) . ' -c copy -movflags +faststart ' . escapeshellarg( $tmp ), $parts );
	rename( $tmp, "$dir/$name" );
	array_map( 'unlink', array_filter( $parts, 'file_exists' ) );
	return array( "$dir/$name", "$url/$name" );
}

/** Runs ffmpeg with the given arguments; on failure removes $cleanup files and throws with ffmpeg's message. */
function itsoc_ffmpeg_run( $args, array $cleanup = array() ) {
	// Few threads: the hosting account has a low process/thread limit shared with the website.
	exec( escapeshellarg( itsoc_ffmpeg() ) . ' -y -loglevel error -threads 2 -filter_threads 1 -filter_complex_threads 1 ' . $args . ' 2>&1', $log, $code );
	if ( 0 !== $code ) {
		array_map( 'unlink', array_filter( $cleanup, 'file_exists' ) );
		throw new Itsoc_Error( 'ffmpeg failed: ' . mb_substr( implode( ' ', (array) $log ), 0, 300 ) );
	}
}
