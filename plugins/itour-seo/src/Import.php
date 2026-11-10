<?php
/**
 * Import Yoast SEO's per-page data (titles, descriptions, focus keyphrases, canonical, noindex, social)
 * into iTour SEO. Fields already filled in iTour SEO are kept; Yoast's %%variables%% are translated.
 *
 * @package ITour\Seo
 */

namespace ITour\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Yoast import.
 */
final class Import {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_post_itour_seo_import', array( $this, 'run' ) );
	}

	/**
	 * Posts that carry Yoast data.
	 *
	 * @return int[]
	 */
	private static function ids(): array {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_yoast_wpseo_title','_yoast_wpseo_metadesc','_yoast_wpseo_focuskw','_yoast_wpseo_canonical','_yoast_wpseo_meta-robots-noindex','_yoast_wpseo_opengraph-title','_yoast_wpseo_opengraph-description') AND meta_value <> ''" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * How many posts can be imported.
	 */
	public static function count(): int {
		return count( self::ids() );
	}

	/**
	 * Yoast template → iTour template (unknown %%variables%% are dropped).
	 *
	 * @param string $v Yoast text.
	 */
	public static function vars( string $v ): string {
		$v = strtr(
			$v,
			array(
				'%%title%%'    => '{title}',
				'%%sitename%%' => '{site}',
				'%%sep%%'      => '{sep}',
				'%%sitedesc%%' => '{tagline}',
				'%%excerpt%%'  => '{excerpt}',
			)
		);
		return trim( (string) preg_replace( array( '/%%[a-z_0-9]+%%/i', '/\s{2,}/' ), array( '', ' ' ), $v ) );
	}

	/**
	 * Copy Yoast data into empty iTour SEO fields. Returns how many posts changed.
	 */
	public static function import(): int {
		$n   = 0;
		$map = array(
			'keyphrase' => '_yoast_wpseo_focuskw',
			'title'     => '_yoast_wpseo_title',
			'desc'      => '_yoast_wpseo_metadesc',
			'canonical' => '_yoast_wpseo_canonical',
			'og_title'  => '_yoast_wpseo_opengraph-title',
			'og_desc'   => '_yoast_wpseo_opengraph-description',
			'og_image'  => '_yoast_wpseo_opengraph-image-id',
		);
		foreach ( self::ids() as $id ) {
			$cur = Seo::item( $id );
			$new = array();
			foreach ( $map as $ours => $key ) {
				$v = (string) get_post_meta( $id, $key, true );
				if ( '' === $cur[ $ours ] && '' !== $v ) {
					$new[ $ours ] = in_array( $ours, array( 'title', 'desc', 'og_title', 'og_desc' ), true ) ? self::vars( $v ) : $v;
				}
			}
			if ( '' === $cur['noindex'] && '1' === (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true ) ) {
				$new['noindex'] = '1';
			}
			if ( $new ) {
				Seo::put( $id, $new );
				++$n;
			}
		}
		return $n;
	}

	/**
	 * The import button.
	 */
	public function run(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'itour-seo' ), 403 );
		}
		check_admin_referer( 'itour_seo_import' );
		wp_safe_redirect( admin_url( 'admin.php?page=itour-seo-settings&imported=' . self::import() ) );
		exit;
	}
}
