<?php
/**
 * Product page (tour package, excursion…): the theme's header and footer around the chosen layout.
 *
 * @package ITour\Core
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) {
	the_post();
	\ITour\Core\Layouts::render_current();
}
get_footer();
