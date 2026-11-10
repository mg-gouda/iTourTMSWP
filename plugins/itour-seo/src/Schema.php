<?php
/**
 * Structured data (JSON-LD graph) for Google: the company (TravelAgency by default), the website with its
 * search box, the page, breadcrumbs, and TouristTrip for tour packages and excursions with the "from"
 * price as an offer and the itinerary stops.
 *
 * @package ITour\Seo
 */

namespace ITour\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Graph builder.
 */
final class Schema {

	/**
	 * Print the graph.
	 */
	public static function print(): void {
		$graph = self::graph();
		if ( $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded.
		}
	}

	/**
	 * Image URL of an attachment ('' when none).
	 *
	 * @param int $id Attachment id.
	 */
	private static function image( int $id ): string {
		$src = $id ? wp_get_attachment_image_src( $id, 'large' ) : false;
		return $src ? (string) $src[0] : '';
	}

	/**
	 * The nodes for the current page.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function graph(): array {
		$s     = Seo::settings();
		$home  = home_url( '/' );
		$org   = array_filter(
			array(
				'@type'     => in_array( $s['org_type'], array( 'TravelAgency', 'Organization', 'LocalBusiness' ), true ) ? $s['org_type'] : 'TravelAgency',
				'@id'       => $home . '#organization',
				'name'      => '' !== $s['org_name'] ? $s['org_name'] : Seo::fill( '{site}' ),
				'url'       => $home,
				'logo'      => self::image( (int) $s['org_logo'] ),
				'telephone' => (string) $s['org_phone'],
				'email'     => (string) $s['org_email'],
				'sameAs'    => array_values( array_filter( array_map( 'esc_url_raw', array_map( 'trim', preg_split( '/\R/u', (string) $s['org_socials'] ) ) ) ) ),
			)
		);
		$graph = array(
			$org,
			array(
				'@type'           => 'WebSite',
				'@id'             => $home . '#website',
				'url'             => $home,
				'name'            => Seo::fill( '{site}' ),
				'publisher'       => array( '@id' => $home . '#organization' ),
				'inLanguage'      => get_bloginfo( 'language' ),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => $home . '?s={search_term_string}',
					'query-input' => 'required name=search_term_string',
				),
			),
		);
		if ( ! is_singular() ) {
			return $graph;
		}
		$id                   = (int) get_queried_object_id();
		$url                  = (string) get_permalink( $id );
		list( $title, $desc ) = Head::current();
		$img                  = self::image( (int) get_post_thumbnail_id( $id ) );
		$graph[]              = array_filter(
			array(
				'@type'              => 'WebPage',
				'@id'                => $url . '#webpage',
				'url'                => $url,
				'name'               => $title,
				'description'        => $desc,
				'isPartOf'           => array( '@id' => $home . '#website' ),
				'primaryImageOfPage' => $img,
				'datePublished'      => get_post_time( 'c', true, $id ),
				'dateModified'       => get_post_modified_time( 'c', true, $id ),
				'breadcrumb'         => is_front_page() ? '' : array( '@id' => $url . '#breadcrumb' ),
			)
		);
		if ( ! is_front_page() ) {
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $url . '#breadcrumb',
				'itemListElement' => array(
					array(
						'@type'    => 'ListItem',
						'position' => 1,
						'name'     => __( 'Home', 'itour-seo' ),
						'item'     => $home,
					),
					array(
						'@type'    => 'ListItem',
						'position' => 2,
						'name'     => Seo::fill( '{title}', $id ),
					),
				),
			);
		}
		if ( in_array( get_post_type( $id ), array( 'itour_tour', 'itour_excursion' ), true ) ) {
			$graph[] = self::trip( $id, $url, $desc );
		}
		return $graph;
	}

	/**
	 * TouristTrip for a tour package or excursion.
	 *
	 * @param int    $id   Post id.
	 * @param string $url  Page address.
	 * @param string $desc Description.
	 * @return array<string,mixed>
	 */
	private static function trip( int $id, string $url, string $desc ): array {
		$images = array_values( array_filter( array_map( array( self::class, 'image' ), array_merge( array( (int) get_post_thumbnail_id( $id ) ), array_map( 'intval', (array) ( get_post_meta( $id, '_itour_info', true )['gallery'] ?? array() ) ) ) ) ) );
		$from   = (float) get_post_meta( $id, '_itour_price_from', true );
		$trip   = array(
			'@type'       => 'TouristTrip',
			'@id'         => $url . '#trip',
			'name'        => Seo::fill( '{title}', $id ),
			'description' => $desc,
			'url'         => $url,
			'image'       => array_slice( array_unique( $images ), 0, 6 ),
			'provider'    => array( '@id' => home_url( '/' ) . '#organization' ),
		);
		if ( $from > 0 && function_exists( 'itour' ) ) {
			$trip['offers'] = array(
				'@type'         => 'Offer',
				'price'         => number_format( $from, 2, '.', '' ),
				'priceCurrency' => itour()->currencies()->base(),
				'availability'  => 'https://schema.org/InStock',
				'url'           => $url,
			);
		}
		$days = (array) ( get_post_meta( $id, '_itour_tour', true )['itinerary'] ?? array() );
		if ( $days ) {
			$trip['itinerary'] = array(
				'@type'           => 'ItemList',
				'numberOfItems'   => count( $days ),
				'itemListElement' => array_map(
					static fn( $d, $i ) => array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'item'     => array_filter(
							array(
								'@type'       => 'TouristDestination',
								'name'        => (string) ( $d['title'] ?? '' ),
								'description' => (string) ( $d['text'] ?? '' ),
							)
						),
					),
					array_values( $days ),
					array_keys( array_values( $days ) )
				),
			);
		}
		return array_filter( $trip );
	}
}
