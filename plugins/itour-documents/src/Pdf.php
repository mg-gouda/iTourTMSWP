<?php
/**
 * The three documents as HTML, and mPDF to turn them into PDFs. One font (DejaVu Sans: Latin and Arabic),
 * right-to-left when the site language is.
 *
 * @package ITour\Documents
 */

namespace ITour\Documents;

use ITour\Core\Company;
use ITour\Core\Policies;

defined( 'ABSPATH' ) || exit;

/**
 * Document builder.
 */
final class Pdf {

	/**
	 * Render HTML to a PDF string.
	 *
	 * @param string $html   Body HTML.
	 * @param string $format 'A4' or 'A4-L'.
	 */
	public static function render( string $html, string $format = 'A4' ): string {
		require_once dirname( ITOUR_DOCUMENTS_FILE ) . '/vendor/autoload.php';
		$fonts = dirname( ITOUR_DOCUMENTS_FILE ) . '/vendor/mpdf/mpdf/ttfonts';
		$mpdf  = new \Mpdf\Mpdf(
			array(
				'mode'             => 'utf-8',
				'format'           => $format,
				'tempDir'          => self::temp_dir(),
				'fontDir'          => array( $fonts ),
				'fontdata'         => array(
					'dejavusans' => array(
						'R'          => 'DejaVuSans.ttf',
						'B'          => 'DejaVuSans-Bold.ttf',
						'I'          => 'DejaVuSans-Oblique.ttf',
						'BI'         => 'DejaVuSans-BoldOblique.ttf',
						'useOTL'     => 0xFF, // Arabic letter joining.
						'useKashida' => 75,
					),
				),
				'default_font'     => 'dejavusans',
				'autoScriptToLang' => false,
				'autoLangToFont'   => false,
				'margin_left'      => 14,
				'margin_right'     => 14,
				'margin_top'       => 12,
				'margin_bottom'    => 16,
				'margin_footer'    => 8,
			)
		);
		if ( is_rtl() ) {
			$mpdf->SetDirectionality( 'rtl' );
		}
		$mpdf->SetTitle( wp_strip_all_tags( self::company()['name'] ) );
		$mpdf->SetHTMLFooter( self::footer() );
		$mpdf->WriteHTML( '<style>' . self::css() . '</style>' . $html );
		return $mpdf->Output( '', \Mpdf\Output\Destination::STRING_RETURN );
	}

	/**
	 * Private scratch folder in uploads (mPDF cache + email attachments); files older than a day are removed.
	 */
	public static function temp_dir(): string {
		$dir = wp_upload_dir()['basedir'] . '/itour-documents-tmp';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . '/index.php', '<?php // Silence.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '/.htaccess', 'Require all denied' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		foreach ( (array) glob( $dir . '/*.pdf' ) as $f ) {
			if ( filemtime( $f ) < time() - DAY_IN_SECONDS ) {
				wp_delete_file( $f );
			}
		}
		return $dir;
	}

	/**
	 * Company details for the header and footer.
	 *
	 * @return array<string,string>
	 */
	private static function company(): array {
		$c    = Company::get();
		$logo = 0;
		if ( function_exists( 'wp_itourtms_options' ) ) {
			$logo = (int) wp_itourtms_options()['logo_image'];
		}
		$logo = $logo ? $logo : (int) get_theme_mod( 'custom_logo' );
		$file = $logo ? (string) get_attached_file( $logo ) : '';
		return array(
			'name'    => html_entity_decode( (string) $c['name'], ENT_QUOTES ),
			'legal'   => (string) ( $c['legal_name'] ?? '' ),
			'licence' => (string) ( $c['licence'] ?? '' ),
			'phone'   => (string) ( $c['phone'] ?? '' ),
			'email'   => (string) ( $c['email'] ?? get_option( 'admin_email' ) ),
			'web'     => (string) ( ( $c['website'] ?? '' ) ? $c['website'] : home_url( '/' ) ),
			'address' => (string) ( $c['address'] ?? '' ),
			'logo'    => $file && is_readable( $file ) && preg_match( '/\.(png|jpe?g|gif)$/i', $file ) ? $file : '', // mPDF reads local files; SVG/WebP logos fall back to the name.
		);
	}

	/**
	 * Brand colour from the theme (falls back to iTour plum).
	 */
	private static function brand(): string {
		foreach ( (array) wp_get_global_settings( array( 'color', 'palette', 'theme' ) ) as $c ) {
			if ( 'brand-dark' === ( $c['slug'] ?? '' ) && preg_match( '/^#[0-9a-f]{3,6}$/i', (string) $c['color'] ) ) {
				return (string) $c['color'];
			}
		}
		return '#660066';
	}

	/**
	 * Styles (mPDF subset of CSS: tables for layout).
	 */
	private static function css(): string {
		$b = self::brand();
		return "body { font-family: dejavusans; font-size: 9.5pt; color: #111827; line-height: 1.45; }
.muted { color: #6B7280; }
.h { font-size: 7.5pt; font-weight: bold; color: $b; letter-spacing: 0.8pt; text-transform: uppercase; margin: 0 0 2mm; }
.card { border: 0.3mm solid #E5E7EB; border-radius: 3mm; padding: 4mm 5mm; margin-bottom: 4mm; }
.soft { background: #F7F5F8; }
table { border-collapse: collapse; }
table.kv { width: 100%; }
table.kv td { padding: 1.2mm 0; vertical-align: top; }
table.kv td.k { color: #6B7280; width: 34%; }
table.kv td.v { font-weight: bold; }
table.list { width: 100%; }
table.list th { background: $b; color: #fff; font-size: 7.5pt; text-align: left; padding: 2mm 2.2mm; text-transform: uppercase; }
table.list td { padding: 2mm 2.2mm; border-bottom: 0.2mm solid #E5E7EB; vertical-align: top; font-size: 8.5pt; }
table.list td.n, table.list th.n { text-align: right; white-space: nowrap; }
.big { font-size: 14pt; font-weight: bold; }
.pill { font-size: 8pt; font-weight: bold; padding: 1mm 3mm; border-radius: 2mm; }
.ok { color: #166534; background: #DCFCE7; }
.no { color: #991B1B; background: #FEE2E2; }
.note { padding: 4mm 5mm; background: #F7F5F8; border-radius: 3mm; font-size: 9pt; }";
	}

	/**
	 * Escape.
	 *
	 * @param string $s Text.
	 */
	private static function e( string $s ): string {
		return esc_html( $s );
	}

	/**
	 * Escape; text without Arabic letters (phones, e-mails, addresses, refs) is kept left-to-right so an RTL
	 * document doesn't move "+20…" or "@" around.
	 *
	 * @param string $s Text.
	 */
	private static function ltr( string $s ): string {
		return is_rtl() && ! preg_match( '/\p{Arabic}/u', $s ) ? '<bdo dir="ltr">' . esc_html( $s ) . '</bdo>' : esc_html( $s );
	}

	/**
	 * Money.
	 *
	 * @param float  $v    Amount.
	 * @param string $code Currency.
	 */
	private static function money( float $v, string $code ): string {
		return function_exists( 'itour' ) ? itour()->currencies()->format( $v, $code ) : $code . ' ' . number_format_i18n( $v, 2 );
	}

	/**
	 * Header: logo / company, coloured band with title and reference.
	 *
	 * @param string $title Document title.
	 * @param string $ref   Reference ('' = none).
	 * @param string $sub   Line under the title.
	 * @param string $pill  Status pill HTML.
	 */
	private static function header( string $title, string $ref, string $sub = '', string $pill = '' ): string {
		$c     = self::company();
		$b     = self::brand();
		$end   = is_rtl() ? 'left' : 'right';
		$logo  = '' !== $c['logo'] ? '<img src="' . esc_attr( $c['logo'] ) . '" style="height:14mm;" />' : '<span style="font-size:16pt;font-weight:bold;color:' . $b . ';">' . self::e( $c['name'] ) . '</span>';
		$lines = array_filter( array( str_replace( "\n", ', ', $c['address'] ), trim( $c['phone'] . ( $c['phone'] && $c['email'] ? '  ·  ' : '' ) . $c['email'] ), preg_replace( '#^https?://|/$#', '', $c['web'] ) ) );
		$h     = '<table width="100%"><tr><td style="vertical-align:middle;">' . $logo . '</td><td style="vertical-align:middle;text-align:' . $end . ';font-size:8pt;color:#4B5563;line-height:1.5;"><b style="color:#111827;font-size:9.5pt;">' . self::e( $c['name'] ) . '</b><br />' . implode( '<br />', array_map( array( self::class, 'ltr' ), $lines ) ) . '</td></tr></table>';
		$h    .= '<table width="100%" style="margin-top:4mm;background:' . $b . ';color:#fff;"><tr><td style="padding:4mm 5mm;"><div style="font-size:16pt;font-weight:bold;">' . self::e( $title ) . '</div>' . ( '' !== $sub ? '<div style="font-size:8.5pt;color:#EDE4EE;margin-top:1mm;">' . self::e( $sub ) . '</div>' : '' ) . '</td>';
		if ( '' !== $ref ) {
			$h .= '<td style="padding:4mm 5mm;text-align:' . $end . ';font-size:8.5pt;color:#EDE4EE;">' . self::e( __( 'Booking ref.', 'itour-documents' ) ) . '<br /><span style="font-size:15pt;font-weight:bold;color:#fff;">' . self::e( $ref ) . '</span></td>';
		}
		$h .= '</tr></table>';
		/* translators: %s: date. */
		return $h . '<table width="100%" style="margin-bottom:4mm;"><tr><td style="padding:2.5mm 0;font-size:8pt;" class="muted">' . self::e( sprintf( __( 'Issued %s', 'itour-documents' ), wp_date( 'j M Y' ) ) ) . '</td><td style="padding:2.5mm 0;text-align:' . $end . ';">' . $pill . '</td></tr></table>';
	}

	/**
	 * Footer: legal name, licence, page numbers.
	 */
	private static function footer(): string {
		$c    = self::company();
		$bits = array_filter( array( '' !== $c['legal'] ? $c['legal'] : $c['name'], '' !== $c['licence'] ? sprintf( /* translators: %s: licence number. */ __( 'Tourism licence %s', 'itour-documents' ), $c['licence'] ) : '', preg_replace( '#^https?://|/$#', '', $c['web'] ) ) );
		/* translators: 1: page number, 2: page count (mPDF fills them in). */
		$page = sprintf( __( 'Page %1$s of %2$s', 'itour-documents' ), '{PAGENO}', '{nbpg}' );
		return '<table width="100%" style="border-top:0.3mm solid #E5E7EB;font-size:7pt;color:#9CA3AF;"><tr><td style="padding-top:2mm;">' . self::e( implode( '  ·  ', $bits ) ) . '</td><td style="padding-top:2mm;text-align:right;">' . self::e( $page ) . '</td></tr></table>';
	}

	/**
	 * Key / value table.
	 *
	 * @param array<string,string> $kv Pairs.
	 */
	private static function kv( array $kv ): string {
		$h = '<table class="kv">';
		foreach ( array_filter( $kv, static fn( $v ) => '' !== (string) $v ) as $k => $v ) {
			$h .= '<tr><td class="k">' . self::e( (string) $k ) . '</td><td class="v">' . nl2br( self::ltr( (string) $v ) ) . '</td></tr>';
		}
		return $h . '</table>';
	}

	/**
	 * Two cards side by side.
	 *
	 * @param string $left  Left HTML.
	 * @param string $right Right HTML.
	 * @param string $css   Card class.
	 */
	private static function two( string $left, string $right, string $css = 'card' ): string {
		// mPDF ignores box styles on blocks inside cells, so the cells are the cards.
		$cell = 'vertical-align:top;border:0.3mm solid #E5E7EB;padding:4mm 5mm;' . ( 'card soft' === $css ? 'background:#F7F5F8;' : '' );
		return '<table width="100%" style="margin-bottom:4mm;"><tr><td width="49%" style="' . $cell . '">' . $left . '</td><td width="2%"></td><td width="49%" style="' . $cell . '">' . $right . '</td></tr></table>';
	}

	/**
	 * Status pill.
	 *
	 * @param array<string,mixed> $b Booking.
	 */
	private static function pill( array $b ): string {
		return 'cancelled' === $b['status']
			? '<span class="pill no">' . self::e( __( 'Cancelled', 'itour-documents' ) ) . '</span>'
			: '<span class="pill ok">' . self::e( __( 'Confirmed', 'itour-documents' ) ) . '</span>';
	}

	/**
	 * Service card: photo, type, title, details.
	 *
	 * @param array<string,mixed> $b Booking.
	 */
	private static function service( array $b ): string {
		$img = '' !== $b['image'] ? '<td style="width:52mm;padding-right:5mm;vertical-align:top;"><img src="' . esc_attr( $b['image'] ) . '" style="width:52mm;height:36mm;border-radius:2mm;" /></td>' : '';
		return '<div class="card"><table width="100%"><tr>' . $img . '<td style="vertical-align:top;"><div class="h">' . self::e( $b['type'] ) . '</div><div class="big" style="margin-bottom:2mm;">' . self::e( $b['title'] ) . '</div>' . self::kv( $b['details'] ) . '</td></tr></table></div>';
	}

	/**
	 * Cancellation wording for this booking's date.
	 *
	 * @param array<string,mixed> $b Booking.
	 */
	private static function cancel_text( array $b ): string {
		if ( ! $b['cancel'] ) {
			return __( 'Contact us for changes or cancellation.', 'itour-documents' );
		}
		return Policies::summary( $b['cancel'], substr( $b['start'], 0, 10 ) );
	}

	/**
	 * Guest Travel Voucher.
	 *
	 * @param array<string,mixed> $b Booking.
	 */
	public static function voucher( array $b ): string {
		$c   = self::company();
		$cur = $b['currency'];
		/* translators: %s: booking date. */
		$h  = self::header( __( 'Travel Voucher', 'itour-documents' ), $b['ref'], sprintf( __( 'Booking confirmation · booked on %s', 'itour-documents' ), wp_date( 'j M Y', strtotime( $b['created'] ) ) ), self::pill( $b ) );
		$h .= self::service( $b );
		$h .= self::two(
			'<div class="h">' . self::e( __( 'Lead traveller', 'itour-documents' ) ) . '</div>' . self::kv(
				array(
					__( 'Name', 'itour-documents' )  => $b['lead']['name'],
					__( 'Phone', 'itour-documents' ) => $b['lead']['phone'],
					__( 'Email', 'itour-documents' ) => $b['lead']['email'],
				)
			) . ( '' !== $b['notes'] ? '<div class="h" style="margin-top:3mm;">' . self::e( __( 'Your notes', 'itour-documents' ) ) . '</div>' . nl2br( self::e( $b['notes'] ) ) : '' ),
			'<div class="h">' . self::e( __( 'Payment', 'itour-documents' ) ) . '</div>' . self::kv(
				array(
					__( 'Total price', 'itour-documents' ) => self::money( $b['total'], $cur ),
					__( 'Paid', 'itour-documents' )        => self::money( $b['paid'], $cur ),
					__( 'To pay on the day', 'itour-documents' ) => $b['due'] > 0 ? self::money( $b['due'], $cur ) : '',
					__( 'Method', 'itour-documents' )      => $b['order'] ? (string) $b['order']->get_payment_method_title() : '',
				)
			),
			'card soft'
		);
		if ( $b['includes'] || $b['excludes'] ) {
			$list = static fn( array $items, string $mark, string $col ): string => '<table width="100%">' . implode( '', array_map( static fn( $x ) => '<tr><td style="width:5mm;color:' . $col . ';font-weight:bold;vertical-align:top;padding:0.6mm 0;">' . $mark . '</td><td style="padding:0.6mm 0;font-size:9pt;">' . esc_html( $x ) . '</td></tr>', $items ) ) . '</table>';
			$h   .= self::two( '<div class="h">' . self::e( __( 'Included', 'itour-documents' ) ) . '</div>' . $list( $b['includes'], '✓', '#166534' ), '<div class="h">' . self::e( __( 'Not included', 'itour-documents' ) ) . '</div>' . $list( $b['excludes'], '✕', '#991B1B' ) );
		}
		$h .= self::two(
			'<div class="h">' . self::e( __( 'Contact on the day', 'itour-documents' ) ) . '</div>' . self::kv(
				array(
					__( 'Phone', 'itour-documents' ) => $c['phone'],
					__( 'Email', 'itour-documents' ) => $c['email'],
				)
			),
			'<div class="h">' . self::e( __( 'Cancellation', 'itour-documents' ) ) . '</div>' . self::e( self::cancel_text( $b ) )
		);
		/* translators: %s: booking reference. */
		return $h . '<div class="note"><b>' . self::e( __( 'Good to know', 'itour-documents' ) ) . '</b><br />' . self::e( sprintf( __( 'Show this voucher, printed or on your phone, with a photo ID to your guide or driver. Be ready at the pick-up point 10 minutes early. For changes, quote booking ref. %s.', 'itour-documents' ), $b['ref'] ) ) . '</div>';
	}

	/**
	 * Service Order for the driver or supplier: no guest prices, only cash to collect.
	 *
	 * @param array<string,mixed> $b Booking.
	 */
	public static function service_order( array $b ): string {
		$h          = self::header( __( 'Service Order', 'itour-documents' ), $b['ref'], __( 'For the driver / supplier', 'itour-documents' ), self::pill( $b ) );
		$b['image'] = '';
		$h         .= self::service( $b );
		$h         .= self::two(
			'<div class="h">' . self::e( __( 'Guest', 'itour-documents' ) ) . '</div>' . self::kv(
				array(
					__( 'Lead traveller', 'itour-documents' ) => $b['lead']['name'],
					__( 'Phone', 'itour-documents' )  => $b['lead']['phone'],
					__( 'People', 'itour-documents' ) => (string) $b['pax'],
				)
			) . ( '' !== $b['notes'] ? '<div class="h" style="margin-top:3mm;">' . self::e( __( 'Guest notes', 'itour-documents' ) ) . '</div>' . nl2br( self::e( $b['notes'] ) ) : '' ),
			'<div class="h">' . self::e( __( 'Cash to collect', 'itour-documents' ) ) . '</div><div class="big">' . self::e( $b['due'] > 0 ? self::money( $b['due'], $b['currency'] ) : __( 'Nothing: paid online', 'itour-documents' ) ) . '</div>',
			'card soft'
		);
		return $h . '<table width="100%" style="margin-top:10mm;"><tr><td width="48%" style="border-top:0.3mm solid #9CA3AF;padding-top:2mm;font-size:8pt;" class="muted">' . self::e( __( 'Driver / guide name', 'itour-documents' ) ) . '</td><td width="4%"></td><td width="48%" style="border-top:0.3mm solid #9CA3AF;padding-top:2mm;font-size:8pt;" class="muted">' . self::e( __( 'Signature', 'itour-documents' ) ) . '</td></tr></table>';
	}

	/**
	 * Booking Report (A4 landscape).
	 *
	 * @param array<int,array<string,mixed>> $rows Bookings.
	 * @param string                         $from Y-m-d.
	 * @param string                         $to   Y-m-d.
	 */
	public static function report( array $rows, string $from, string $to ): string {
		/* translators: 1: start date, 2: end date. */
		$h    = self::header( __( 'Booking Report', 'itour-documents' ), '', sprintf( __( 'Service dates %1$s to %2$s', 'itour-documents' ), wp_date( 'j M Y', strtotime( $from ) ), wp_date( 'j M Y', strtotime( $to ) ) ) );
		$cols = array( __( 'Date', 'itour-documents' ), __( 'Ref.', 'itour-documents' ), __( 'Service', 'itour-documents' ), __( 'Lead traveller', 'itour-documents' ), __( 'People', 'itour-documents' ), __( 'Total', 'itour-documents' ), __( 'Paid', 'itour-documents' ), __( 'To collect', 'itour-documents' ), __( 'Status', 'itour-documents' ) );
		$h   .= '<table class="list"><thead><tr>';
		foreach ( $cols as $i => $col ) {
			$h .= '<th' . ( $i >= 4 && $i <= 7 ? ' class="n"' : '' ) . '>' . self::e( $col ) . '</th>';
		}
		$h  .= '</tr></thead><tbody>';
		$sum = array();
		foreach ( $rows as $b ) {
			$live = 'cancelled' !== $b['status'];
			if ( $live ) {
				$sum[ $b['currency'] ] = array_map( static fn( $x, $y ) => $x + $y, $sum[ $b['currency'] ] ?? array( 0, 0, 0, 0 ), array( 1, $b['total'], $b['paid'], $b['due'] ) );
			}
			$h .= '<tr' . ( $live ? '' : ' style="color:#9CA3AF;"' ) . '><td>' . self::e( wp_date( 'j M Y H:i', strtotime( $b['start'] ) ) ) . '</td><td>' . self::e( $b['ref'] ) . '</td><td><b>' . self::e( $b['title'] ) . '</b><br /><span class="muted">' . self::e( $b['type'] ) . '</span></td><td>' . self::e( $b['lead']['name'] ) . ( '' !== $b['lead']['phone'] ? '<br /><span class="muted">' . self::e( $b['lead']['phone'] ) . '</span>' : '' ) . '</td>'
				. '<td class="n">' . (int) $b['pax'] . '</td><td class="n">' . self::e( self::money( $b['total'], $b['currency'] ) ) . '</td><td class="n">' . self::e( self::money( $b['paid'], $b['currency'] ) ) . '</td><td class="n">' . self::e( $b['due'] > 0 ? self::money( $b['due'], $b['currency'] ) : '–' ) . '</td><td>' . self::pill( $b ) . '</td></tr>';
		}
		if ( ! $rows ) {
			$h .= '<tr><td colspan="9" class="muted">' . self::e( __( 'No bookings in these dates.', 'itour-documents' ) ) . '</td></tr>';
		}
		$h .= '</tbody></table>';
		if ( $sum ) {
			$h .= '<table class="list" style="width:60%;margin-top:6mm;"><thead><tr><th>' . self::e( __( 'Currency', 'itour-documents' ) ) . '</th><th class="n">' . self::e( __( 'Bookings', 'itour-documents' ) ) . '</th><th class="n">' . self::e( __( 'Total', 'itour-documents' ) ) . '</th><th class="n">' . self::e( __( 'Paid', 'itour-documents' ) ) . '</th><th class="n">' . self::e( __( 'To collect', 'itour-documents' ) ) . '</th></tr></thead><tbody>';
			foreach ( $sum as $cur => $s ) {
				$h .= '<tr><td><b>' . self::e( $cur ) . '</b></td><td class="n">' . (int) $s[0] . '</td><td class="n"><b>' . self::e( self::money( $s[1], $cur ) ) . '</b></td><td class="n">' . self::e( self::money( $s[2], $cur ) ) . '</td><td class="n">' . self::e( self::money( $s[3], $cur ) ) . '</td></tr>';
			}
			$h .= '</tbody></table><p class="muted" style="font-size:7.5pt;">' . self::e( __( 'Totals leave out cancelled bookings.', 'itour-documents' ) ) . '</p>';
		}
		return $h;
	}
}
