<?php
/**
 * Dev only: a demo home page built from the theme's patterns, a few pages and a main menu.
 * Usage: docker cp tools/demo-content.php itourwp-wp:/var/www/html/ && bin/dev.sh wp eval-file demo-content.php
 *
 * @package ITour
 */

update_option( 'blogname', 'Nile & Sea Travel' );
update_option( 'blogdescription', 'Tours, day trips and transfers in Egypt' );

$content = '';
foreach ( array( 'hero', 'why-book', 'call-to-action' ) as $p ) {
	$content .= '<!-- wp:pattern {"slug":"wp-itourtms/' . $p . '"} /-->' . "\n";
}
$home = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Home',
		'post_content' => $content,
	)
);
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home );

$menu = wp_create_nav_menu( 'Main' );
foreach ( array( 'Tours', 'Day trips', 'Transfers', 'About', 'Contact' ) as $title ) {
	$id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $title,
		)
	);
	wp_update_nav_menu_item(
		$menu,
		0,
		array(
			'menu-item-object-id' => $id,
			'menu-item-object'    => 'page',
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		)
	);
}
set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu ) );
echo esc_html( "Demo ready: home page {$home}, menu {$menu}\n" );
