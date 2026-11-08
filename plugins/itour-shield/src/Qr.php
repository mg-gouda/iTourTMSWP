<?php
/**
 * Minimal QR code encoder (byte mode, error correction M, versions 1–10) that returns SVG.
 * Enough for an otpauth:// link, so the 2FA setup needs no outside service or library.
 * Algorithm follows ISO/IEC 18004 as laid out in Project Nayuki's reference encoder.
 *
 * @package ITour\Shield
 */

namespace ITour\Shield;

defined( 'ABSPATH' ) || exit;

/**
 * QR to SVG.
 */
final class Qr {

	/**
	 * Per version (M level): EC codewords per block, blocks in group 1, data codewords per block in group 1, blocks in group 2.
	 * Group 2 blocks hold one data codeword more than group 1.
	 */
	private const TABLE = array(
		1  => array( 10, 1, 16, 0 ),
		2  => array( 16, 1, 28, 0 ),
		3  => array( 26, 1, 44, 0 ),
		4  => array( 18, 2, 32, 0 ),
		5  => array( 24, 2, 43, 0 ),
		6  => array( 16, 4, 27, 0 ),
		7  => array( 18, 4, 31, 0 ),
		8  => array( 22, 2, 38, 2 ),
		9  => array( 22, 3, 36, 2 ),
		10 => array( 26, 4, 43, 1 ),
	);

	/**
	 * Alignment pattern centres per version.
	 */
	private const ALIGN = array(
		1  => array(),
		2  => array( 6, 18 ),
		3  => array( 6, 22 ),
		4  => array( 6, 26 ),
		5  => array( 6, 30 ),
		6  => array( 6, 34 ),
		7  => array( 6, 22, 38 ),
		8  => array( 6, 24, 42 ),
		9  => array( 6, 26, 46 ),
		10 => array( 6, 28, 50 ),
	);

	/**
	 * Modules (true = dark).
	 *
	 * @var array<int,array<int,bool>>
	 */
	private array $m = array();

	/**
	 * Function-pattern flags.
	 *
	 * @var array<int,array<int,bool>>
	 */
	private array $reserved = array();

	/**
	 * Size in modules.
	 *
	 * @var int
	 */
	private int $size = 0;

	/**
	 * Encode text and return an SVG string ('' if too long).
	 *
	 * @param string $text Text (up to ~210 bytes).
	 * @param int    $px   Rendered size in pixels.
	 */
	public static function svg( string $text, int $px = 192 ): string {
		$qr = new self();
		if ( ! $qr->encode( $text ) ) {
			return '';
		}
		$d = '';
		foreach ( $qr->m as $y => $row ) {
			foreach ( $row as $x => $dark ) {
				if ( $dark ) {
					$d .= 'M' . ( $x + 4 ) . ' ' . ( $y + 4 ) . 'h1v1h-1z';
				}
			}
		}
		$n = $qr->size + 8;
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $n . ' ' . $n . '" width="' . $px . '" height="' . $px . '" shape-rendering="crispEdges" role="img"><rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="' . $d . '"/></svg>';
	}

	/**
	 * Build the matrix.
	 *
	 * @param string $text Text.
	 */
	private function encode( string $text ): bool {
		$len     = strlen( $text );
		$version = 0;
		foreach ( self::TABLE as $v => $t ) {
			$cap = ( $t[1] * $t[2] + $t[3] * ( $t[2] + 1 ) ) * 8;
			if ( 4 + ( $v < 10 ? 8 : 16 ) + 8 * $len <= $cap ) {
				$version = $v;
				break;
			}
		}
		if ( ! $version ) {
			return false;
		}
		list( $ec_len, $g1, $g1_len, $g2 ) = self::TABLE[ $version ];
		$data_len                          = $g1 * $g1_len + $g2 * ( $g1_len + 1 );

		// Bit stream: mode, length, bytes, terminator, padding.
		$bits = '0100' . str_pad( decbin( $len ), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT );
		for ( $i = 0; $i < $len; $i++ ) {
			$bits .= str_pad( decbin( ord( $text[ $i ] ) ), 8, '0', STR_PAD_LEFT );
		}
		$bits .= str_repeat( '0', min( 4, $data_len * 8 - strlen( $bits ) ) );
		$bits .= str_repeat( '0', ( 8 - strlen( $bits ) % 8 ) % 8 );
		$data  = array_map( 'bindec', str_split( $bits, 8 ) );
		for ( $pad = 0xEC, $n = count( $data ); $n < $data_len; ++$n, $pad ^= 0xEC ^ 0x11 ) {
			$data[] = $pad;
		}

		// Split into blocks, add error correction, interleave.
		$div    = self::rs_divisor( $ec_len );
		$blocks = array();
		$ecs    = array();
		$pos    = 0;
		for ( $b = 0; $b < $g1 + $g2; $b++ ) {
			$n        = $g1_len + ( $b >= $g1 ? 1 : 0 );
			$blocks[] = array_slice( $data, $pos, $n );
			$ecs[]    = self::rs_remainder( end( $blocks ), $div );
			$pos     += $n;
		}
		$codewords = array();
		for ( $i = 0; $i <= $g1_len; $i++ ) {
			foreach ( $blocks as $blk ) {
				if ( isset( $blk[ $i ] ) ) {
					$codewords[] = $blk[ $i ];
				}
			}
		}
		for ( $i = 0; $i < $ec_len; $i++ ) {
			foreach ( $ecs as $ec ) {
				$codewords[] = $ec[ $i ];
			}
		}

		// Function patterns.
		$this->size     = $version * 4 + 17;
		$s              = $this->size;
		$this->m        = array_fill( 0, $s, array_fill( 0, $s, false ) );
		$this->reserved = $this->m;
		for ( $i = 0; $i < $s; $i++ ) {
			$this->set( 6, $i, 0 === $i % 2 );
			$this->set( $i, 6, 0 === $i % 2 );
		}
		foreach ( array( array( 3, 3 ), array( $s - 4, 3 ), array( 3, $s - 4 ) ) as $c ) {
			for ( $dy = -4; $dy <= 4; $dy++ ) {
				for ( $dx = -4; $dx <= 4; $dx++ ) {
					$x = $c[0] + $dx;
					$y = $c[1] + $dy;
					if ( $x >= 0 && $x < $s && $y >= 0 && $y < $s ) {
						$d = max( abs( $dx ), abs( $dy ) );
						$this->set( $x, $y, 2 !== $d && 4 !== $d );
					}
				}
			}
		}
		$al   = self::ALIGN[ $version ];
		$last = count( $al ) - 1;
		foreach ( $al as $i => $ax ) {
			foreach ( $al as $j => $ay ) {
				if ( ( 0 === $i && 0 === $j ) || ( 0 === $i && $j === $last ) || ( $i === $last && 0 === $j ) ) {
					continue;
				}
				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$ring = max( abs( $dx ), abs( $dy ) );
						$this->set( $ax + $dx, $ay + $dy, 1 !== $ring );
					}
				}
			}
		}
		$this->format( 0 ); // Reserve the format areas.
		if ( $version >= 7 ) {
			$rem = $version;
			for ( $i = 0; $i < 12; $i++ ) {
				$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
			}
			$vbits = $version << 12 | $rem;
			for ( $i = 0; $i < 18; $i++ ) {
				$bit = 1 === ( ( $vbits >> $i ) & 1 );
				$a   = $s - 11 + $i % 3;
				$b   = intdiv( $i, 3 );
				$this->set( $a, $b, $bit );
				$this->set( $b, $a, $bit );
			}
		}

		// Data, zigzag from the bottom right.
		$i     = 0;
		$total = count( $codewords ) * 8;
		for ( $right = $s - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			for ( $vert = 0; $vert < $s; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x  = $right - $j;
					$up = 0 === ( ( $right + 1 ) & 2 );
					$y  = $up ? $s - 1 - $vert : $vert;
					if ( ! $this->reserved[ $y ][ $x ] && $i < $total ) {
						$this->m[ $y ][ $x ] = 1 === ( ( $codewords[ $i >> 3 ] >> ( 7 - ( $i & 7 ) ) ) & 1 );
						++$i;
					}
				}
			}
		}

		// Pick the mask with the lowest penalty.
		$best  = 0;
		$score = PHP_INT_MAX;
		for ( $mask = 0; $mask < 8; $mask++ ) {
			$this->mask( $mask );
			$this->format( $mask );
			$p = $this->penalty();
			if ( $p < $score ) {
				$score = $p;
				$best  = $mask;
			}
			$this->mask( $mask ); // XOR again restores the unmasked grid.
		}
		$this->mask( $best );
		$this->format( $best );
		return true;
	}

	/**
	 * Set a function module.
	 *
	 * @param int  $x    Column.
	 * @param int  $y    Row.
	 * @param bool $dark Dark.
	 */
	private function set( int $x, int $y, bool $dark ): void {
		$this->m[ $y ][ $x ]        = $dark;
		$this->reserved[ $y ][ $x ] = true;
	}

	/**
	 * Draw both copies of the format bits (EC level M).
	 *
	 * @param int $mask Mask 0–7.
	 */
	private function format( int $mask ): void {
		$data = $mask; // Level M = 00.
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}
		$bits = ( $data << 10 | $rem ) ^ 0x5412;
		$bit  = static fn( int $i ): bool => 1 === ( ( $bits >> $i ) & 1 );
		$s    = $this->size;
		for ( $i = 0; $i <= 5; $i++ ) {
			$this->set( 8, $i, $bit( $i ) );
		}
		$this->set( 8, 7, $bit( 6 ) );
		$this->set( 8, 8, $bit( 7 ) );
		$this->set( 7, 8, $bit( 8 ) );
		for ( $i = 9; $i < 15; $i++ ) {
			$this->set( 14 - $i, 8, $bit( $i ) );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$this->set( $s - 1 - $i, 8, $bit( $i ) );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$this->set( 8, $s - 15 + $i, $bit( $i ) );
		}
		$this->set( 8, $s - 8, true );
	}

	/**
	 * XOR a mask over the data modules.
	 *
	 * @param int $mask Mask 0–7.
	 */
	private function mask( int $mask ): void {
		for ( $y = 0; $y < $this->size; $y++ ) {
			for ( $x = 0; $x < $this->size; $x++ ) {
				if ( $this->reserved[ $y ][ $x ] ) {
					continue;
				}
				switch ( $mask ) {
					case 0:
						$inv = 0 === ( $x + $y ) % 2;
						break;
					case 1:
						$inv = 0 === $y % 2;
						break;
					case 2:
						$inv = 0 === $x % 3;
						break;
					case 3:
						$inv = 0 === ( $x + $y ) % 3;
						break;
					case 4:
						$inv = 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2;
						break;
					case 5:
						$inv = 0 === $x * $y % 2 + $x * $y % 3;
						break;
					case 6:
						$inv = 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2;
						break;
					default:
						$inv = 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2;
				}
				if ( $inv ) {
					$this->m[ $y ][ $x ] = ! $this->m[ $y ][ $x ];
				}
			}
		}
	}

	/**
	 * Simplified penalty: long runs, 2×2 blocks and dark/light balance.
	 * Any mask gives a valid code; this only helps scanners.
	 */
	private function penalty(): int {
		$p = 0;
		$s = $this->size;
		$d = 0;
		for ( $a = 0; $a < $s; $a++ ) {
			$run_r = 1;
			$run_c = 1;
			for ( $b = 1; $b < $s; $b++ ) {
				$run_r = $this->m[ $a ][ $b ] === $this->m[ $a ][ $b - 1 ] ? $run_r + 1 : 1;
				$run_c = $this->m[ $b ][ $a ] === $this->m[ $b - 1 ][ $a ] ? $run_c + 1 : 1;
				$p    += ( 5 === $run_r ? 3 : ( $run_r > 5 ? 1 : 0 ) ) + ( 5 === $run_c ? 3 : ( $run_c > 5 ? 1 : 0 ) );
			}
		}
		for ( $y = 0; $y < $s; $y++ ) {
			for ( $x = 0; $x < $s; $x++ ) {
				$d += $this->m[ $y ][ $x ] ? 1 : 0;
				if ( $x < $s - 1 && $y < $s - 1 ) {
					$c = $this->m[ $y ][ $x ];
					if ( $c === $this->m[ $y ][ $x + 1 ] && $c === $this->m[ $y + 1 ][ $x ] && $c === $this->m[ $y + 1 ][ $x + 1 ] ) {
						$p += 3;
					}
				}
			}
		}
		return $p + 10 * intdiv( abs( $d * 20 - $s * $s * 10 ), $s * $s );
	}

	/**
	 * GF(256) multiply.
	 *
	 * @param int $x A.
	 * @param int $y B.
	 */
	private static function mul( int $x, int $y ): int {
		$z = 0;
		for ( $i = 7; $i >= 0; $i-- ) {
			$z  = ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D );
			$z ^= ( ( $y >> $i ) & 1 ) * $x;
		}
		return $z;
	}

	/**
	 * Reed–Solomon generator polynomial.
	 *
	 * @param int $degree Degree.
	 * @return array<int,int>
	 */
	private static function rs_divisor( int $degree ): array {
		$r                = array_fill( 0, $degree, 0 );
		$r[ $degree - 1 ] = 1;
		$root             = 1;
		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$r[ $j ] = self::mul( $r[ $j ], $root );
				if ( $j + 1 < $degree ) {
					$r[ $j ] ^= $r[ $j + 1 ];
				}
			}
			$root = self::mul( $root, 0x02 );
		}
		return $r;
	}

	/**
	 * Reed–Solomon remainder.
	 *
	 * @param array<int,int> $data    Data codewords.
	 * @param array<int,int> $divisor Generator.
	 * @return array<int,int>
	 */
	private static function rs_remainder( array $data, array $divisor ): array {
		$r = array_fill( 0, count( $divisor ), 0 );
		foreach ( $data as $b ) {
			$f   = $b ^ array_shift( $r );
			$r[] = 0;
			foreach ( $divisor as $i => $coef ) {
				$r[ $i ] ^= self::mul( $coef, $f );
			}
		}
		return $r;
	}
}
