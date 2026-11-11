<?php
/**
 * Captions written for each network's own rules and habits:
 * - Facebook: friendly, short bullets, 3 hashtags, link in the text (or the preview card for link posts).
 * - Instagram: no clickable links (“link in bio”), save/share prompts, 15 hashtags at the end, 2,200 characters.
 * - LinkedIn: professional tone, a question to start comments, 4 hashtags, 3,000 characters.
 * - X: 280 characters (a link counts as 23), 2 hashtags.
 * - TikTok: short hook, follow prompt, 5 hashtags.
 * - YouTube Shorts: title up to 100 characters with #Shorts, description with link, subscribe prompt, 3 hashtags.
 * - Google Business Profile: no hashtags (Google ignores them), plain text up to 1,500 characters, a Book / Learn more button.
 * The call to action changes with the kind (guide, tour, day trip, transfer) and rotates between a few wordings so feeds do not repeat.
 */

defined( 'ABSPATH' ) || exit;

/** How many hashtags each network should get. */
function itsoc_tag_count( $channel ) {
	return array( 'facebook' => 3, 'instagram' => 15, 'linkedin' => 4, 'x' => 2, 'tiktok' => 5, 'youtube' => 3 )[ $channel ] ?? 0;
}

/** Hashtags, most specific first: places in the text, the kind of post, the site's own tags, then network favourites. */
function itsoc_hashtags( array $item, $channel ) {
	$max = itsoc_tag_count( $channel );
	if ( ! $max ) {
		return '';
	}
	$text = $item['title'] . ' ' . $item['summary'];
	$tags = array();
	foreach ( array( 'Giza' => 'GizaPyramids', 'Pyramid' => 'Pyramids', 'Sphinx' => 'Sphinx', 'Cairo' => 'Cairo', 'Luxor' => 'Luxor', 'Aswan' => 'Aswan', 'Abu Simbel' => 'AbuSimbel', 'Hurghada' => 'Hurghada', 'Sharm' => 'SharmElSheikh', 'Marsa Alam' => 'MarsaAlam', 'El Gouna' => 'ElGouna', 'Alexandria' => 'Alexandria', 'Dahab' => 'Dahab', 'Siwa' => 'Siwa', 'Nile' => 'NileCruise', 'Red Sea' => 'RedSea', 'Sinai' => 'Sinai', 'Fayoum' => 'Fayoum', 'Safaga' => 'Safaga', 'Abydos' => 'Abydos', 'Dendera' => 'Dendera', 'Museum' => 'GrandEgyptianMuseum', 'Snorkel' => 'Snorkeling', 'Diving' => 'Diving', 'Safari' => 'DesertSafari', 'Balloon' => 'HotAirBalloon' ) as $word => $tag ) {
		if ( false !== stripos( $text, $word ) ) {
			$tags[] = '#' . $tag;
		}
	}
	$by_kind = array(
		'post'     => array( '#EgyptTravelGuide', '#TravelTips' ),
		'tour'     => array( '#EgyptTours', '#EgyptHoliday' ),
		'activity' => array( '#ThingsToDoInEgypt', '#EgyptDayTrip' ),
		'transfer' => array( '#AirportTransfer', '#EgyptTransfers' ),
	);
	$by_channel = array(
		'instagram' => array( '#TravelGram', '#ExploreEgypt', '#Wanderlust', '#InstaTravel', '#TravelPhotography', '#BucketList', '#AncientEgypt', '#Holiday' ),
		'tiktok'    => array( '#EgyptTok', '#TravelTok' ),
		'linkedin'  => array( '#Tourism', '#Travel' ),
		'youtube'   => array( '#Travel' ),
		'x'         => array(),
		'facebook'  => array(),
	);
	if ( in_array( $channel, array( 'x', 'facebook', 'youtube' ), true ) ) { // Few tags: one place, then the most relevant general one.
		$tags = array_merge( array_slice( $tags, 0, 1 ), array( '#EgyptTravel' ), $by_kind[ $item['kind'] ] );
	} elseif ( 'linkedin' === $channel ) { // Two places, then topic and brand.
		$tags = array_merge( array_slice( $tags, 0, 2 ), array( $by_kind[ $item['kind'] ][0], '#EgyptTravel' ) );
	} else {
		$tags = array_merge( $tags, $by_kind[ $item['kind'] ], preg_split( '/\s+/', trim( (string) itsoc_opt( 'hashtags' ) ) ), $by_channel[ $channel ] ?? array() );
	}
	return implode( ' ', array_slice( array_values( array_unique( array_filter( $tags ) ) ), 0, $max ) );
}

/** One of a few wordings, fixed per item+channel+format so a retry posts the same text. */
function itsoc_pick( array $options, array $item, $salt ) {
	return $options[ crc32( $item['key'] . '|' . $salt ) % count( $options ) ];
}

/** The call to action. $link is '' where links are not clickable (Instagram, TikTok) or shown as a card. */
function itsoc_cta( array $item, $channel, $format, $link ) {
	$k   = $item['kind'];
	$url = $link ? ' ' . $link : '';
	if ( in_array( $channel, array( 'instagram', 'tiktok' ), true ) ) {
		$base = array(
			'post'     => array( 'Full guide via the link in our bio 🔗', 'Read the whole guide – link in bio 🔗', 'More tips on ' . itsoc_domain() . ' (link in bio) 🔗' ),
			'tour'     => array( 'Check dates & book via the link in bio 📲', 'Book with a deposit, pay the rest later – link in bio 📲', 'DM us or tap the link in bio to book 📩' ),
			'activity' => array( 'Book via the link in bio – at least 48h ahead 📲', 'Tap the link in bio to book your spot 📲', 'Questions? DM us · Book via the link in bio 📩' ),
			'transfer' => array( 'Book your transfer via the link in bio ✈️', 'Fixed price per car – book via the link in bio 🚐', 'Landing soon? Book via the link in bio ✈️' ),
		)[ $k ];
		$extra = 'tiktok' === $channel
			? 'Follow for more Egypt travel tips ✈️'
			: ( 'carousel' === $format ? 'Swipe ➡️ · Save 📌 for your trip · Share with your travel buddy' : 'Save 📌 & share with someone who needs this trip' );
		return itsoc_pick( $base, $item, $channel . $format ) . "\n" . $extra;
	}
	if ( 'linkedin' === $channel ) {
		$q = array(
			'post'     => array( 'Have you visited? What would you add?', 'Which of these would you put first on an Egypt itinerary?' ),
			'tour'     => array( 'Planning a group or company trip to Egypt? Message us.', 'Tour operators and travel agents: ask us about partner rates.' ),
			'activity' => array( 'Organising a team trip? We arrange private groups too.', 'Travel agents: ask us about partner rates.' ),
			'transfer' => array( 'Arranging transfers for a group or event? We can help.', 'Travel agents: ask us about partner rates for transfers.' ),
		)[ $k ];
		$go = array( 'post' => 'Read the full guide:', 'tour' => 'Itinerary, prices and booking:', 'activity' => 'Details and booking:', 'transfer' => 'Prices and booking:' )[ $k ];
		return itsoc_pick( $q, $item, 'li' ) . ( $link ? "\n\n" . $go . $url : '' );
	}
	if ( 'x' === $channel ) {
		return itsoc_pick( array(
			'post'     => array( 'Full guide 👇', 'Read more 👇', 'Plan your visit 👇' ),
			'tour'     => array( 'Book now 👇', 'Dates & prices 👇', 'Pay a deposit, rest later 👇' ),
			'activity' => array( 'Book now 👇', 'Book 48h ahead 👇', 'Details 👇' ),
			'transfer' => array( 'Book in 2 minutes 👇', 'Fixed price 👇', 'Book now 👇' ),
		)[ $k ], $item, 'x' ) . $url;
	}
	if ( 'youtube' === $channel ) {
		return array( 'post' => '📖 Read the full guide:', 'tour' => '🗓️ Dates, prices and booking:', 'activity' => '🎟️ Book this trip:', 'transfer' => '🚐 Book your transfer:' )[ $k ] . $url . "\n🔔 Subscribe for more Egypt travel tips.";
	}
	if ( 'gbp' === $channel ) {
		return array( 'post' => 'Read the full guide on our website.', 'tour' => 'Book online – pay a deposit, the rest later.', 'activity' => 'Book online at least 48 hours ahead.', 'transfer' => 'Book online in 2 minutes – fixed price per car.' )[ $k ];
	}
	// Facebook (and anything else with clickable links).
	return itsoc_pick( array(
		'post'     => array( '📖 Read the full guide:', '👉 All the tips in our guide:', '🗺️ Plan your visit:' ),
		'tour'     => array( '🗓️ Check dates & book – pay a deposit, the rest later:', '👉 Full itinerary & booking:', '📩 Questions? Message us, or book here:' ),
		'activity' => array( '🎟️ Book your spot (at least 48h ahead):', '👉 Details & booking:', '📩 Message us, or book online:' ),
		'transfer' => array( '✈️ Book your transfer in 2 minutes:', '👉 Prices & booking:', '🚐 Fixed price per car – book here:' ),
	)[ $k ], $item, 'fb' . $format ) . ( $url ?: ' ' . itsoc_domain() );
}

function itsoc_hook_emoji( array $item ) {
	return array( 'post' => '📍', 'tour' => '🌍', 'activity' => '☀️', 'transfer' => '🚐' )[ $item['kind'] ];
}

/** Bullet list of the item's points in a network's style. */
function itsoc_bullets( array $item, $max, $mark ) {
	$out = '';
	foreach ( array_slice( $item['points'], 0, $max ) as $p ) {
		$out .= "\n" . $mark . ' ' . $p[0];
	}
	return $out;
}

/** Caption for one channel and format. */
function itsoc_caption( array $item, $channel, $format ) {
	$tags  = itsoc_hashtags( $item, $channel );
	$hook  = itsoc_hook_emoji( $item ) . ' ' . $item['title'];
	$price = $item['price'] ? "\n💰 " . $item['price'] : '';

	switch ( $channel ) {
		case 'x': // 280 characters, a link counts as 23.
			$cta  = itsoc_cta( $item, 'x', $format, 'LINK' );
			$room = 280 - ( mb_strlen( $cta ) - 4 + 23 ) - mb_strlen( $tags ) - 4;
			$text = $hook . ( $item['price'] ? ' – ' . $item['price'] : '' );
			if ( mb_strlen( $text ) + 2 + mb_strlen( $item['summary'] ) <= $room ) {
				$text .= "\n\n" . $item['summary'];
			} elseif ( mb_strlen( $text ) > $room ) {
				$text = mb_substr( $text, 0, $room - 1 ) . '…';
			}
			return $text . "\n\n" . str_replace( 'LINK', itsoc_link( $item, 'x' ), $cta ) . "\n" . $tags;

		case 'instagram':
		case 'tiktok':
			$body = 'tiktok' === $channel ? wp_trim_words( $item['summary'], 25, '…' ) :$item['summary'] . itsoc_bullets( $item, 5, '✨' );
			$text = $hook . $price . "\n\n" . $body . "\n\n" . itsoc_cta( $item, $channel, $format, '' );
			return mb_substr( $text, 0, 2200 - mb_strlen( $tags ) - 6 ) . "\n.\n.\n" . $tags;

		case 'linkedin':
			$link = 'link' === $format ? '' : itsoc_link( $item, 'linkedin' ); // Link posts show the article card.
			$text = $item['title'] . ( $item['price'] ? ' – ' . $item['price'] : '' ) . "\n\n" . $item['summary'] . itsoc_bullets( $item, 5, '→' ) . "\n\n" . itsoc_cta( $item, 'linkedin', $format, $link );
			return mb_substr( $text, 0, 2900 ) . "\n\n" . $tags;

		case 'gbp':
			$text = $item['title'] . ( $item['price'] ? "\n" . $item['price'] : '' ) . "\n\n" . $item['summary'] . itsoc_bullets( $item, 4, '•' ) . "\n\n" . itsoc_cta( $item, 'gbp', $format, '' );
			return mb_substr( $text, 0, 1490 );

		case 'youtube':
			return $item['summary'] . itsoc_bullets( $item, 5, '•' ) . "\n\n" . itsoc_cta( $item, 'youtube', $format, itsoc_link( $item, 'youtube' ) ) . "\n\n" . $tags;

		default: // Facebook.
			$link = 'link' === $format ? '' : itsoc_link( $item, 'facebook' ); // Link posts show the preview card.
			return $hook . $price . "\n\n" . $item['summary'] . itsoc_bullets( $item, 4, '✅' ) . "\n\n" . itsoc_cta( $item, 'facebook', $format, $link ) . "\n\n" . $tags;
	}
}

/** YouTube Shorts title: up to 100 characters, ending in #Shorts. */
function itsoc_youtube_title( array $item ) {
	$t = $item['title'];
	if ( $item['price'] && 'post' !== $item['kind'] && mb_strlen( $t ) < 60 ) {
		$t .= ' | ' . $item['price'];
	}
	return mb_substr( $t, 0, 90 ) . ' #Shorts';
}

/** Google Business Profile button: Book for products, Learn more for guides. */
function itsoc_gbp_action( array $item ) {
	return 'post' === $item['kind'] ? 'LEARN_MORE' : 'BOOK';
}

/** Email subject (about 60 characters shows in full on phones) and preview text. */
function itsoc_email_subject( array $item ) {
	$s = 'post' === $item['kind'] ? 'New guide: ' . $item['title'] : $item['title'];
	return mb_strlen( $s ) > 70 ? mb_substr( $s, 0, 67 ) . '…' : $s;
}
