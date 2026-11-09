<?php
/**
 * Dev only: the same demo buyers get from iTour → Overview → Add demo content, plus a demo site name.
 * Usage: docker cp tools/demo-content.php itourwp-wp:/var/www/html/ && bin/dev.sh wp eval-file demo-content.php
 *
 * @package ITour
 */

update_option( 'blogname', 'Nile & Sea Travel' );
update_option( 'blogdescription', 'Tours, day trips and transfers in Egypt' );
( new ITour\Core\Demo() )->add();
echo "Demo ready\n";
