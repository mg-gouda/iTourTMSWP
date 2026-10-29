<?php
/**
 * Built-in documentation and user manual. One source renders the Appearance → WP iTourTMS page
 * and the static HTML documentation shipped in the download package.
 *
 * Content blocks: [ 'p', text ], [ 'steps', [ text … ] ], [ 'list', [ text … ] ], [ 'code', text ], [ 'note', text ].
 * Plugins add sections with the `wp_itourtms_manual` filter.
 *
 * @package WP_iTourTMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manual content: tab => [ 'title' => …, 'sections' => [ [ 'id', 'title', 'blocks' ] … ] ].
 *
 * @return array<string,array{title:string,sections:array<int,array{id:string,title:string,blocks:array<int,array{0:string,1:string|array<int,string>}>}>}>
 */
function wp_itourtms_manual(): array {
	$manual = array(
		'start'  => array(
			'title'    => __( 'Getting started', 'wp-itourtms' ),
			'sections' => array(
				array(
					'id'     => 'requirements',
					'title'  => __( 'Requirements', 'wp-itourtms' ),
					'blocks' => array(
						array( 'list', array( __( 'WordPress 6.5 or newer (tested up to 7.1).', 'wp-itourtms' ), __( 'PHP 8.1 or newer.', 'wp-itourtms' ), __( 'No other plugins are required. The theme works on its own.', 'wp-itourtms' ) ) ),
					),
				),
				array(
					'id'     => 'install',
					'title'  => __( 'Install and activate', 'wp-itourtms' ),
					'blocks' => array(
						array(
							'steps',
							array(
								__( 'In the dashboard go to Appearance → Themes → Add New Theme → Upload Theme.', 'wp-itourtms' ),
								__( 'Choose wp-itourtms.zip and click Install Now.', 'wp-itourtms' ),
								__( 'Click Activate.', 'wp-itourtms' ),
								__( 'To change templates or styles safely, also install wp-itourtms-child.zip and activate the child theme instead (see “Child theme” below).', 'wp-itourtms' ),
							),
						),
					),
				),
				array(
					'id'     => 'first-setup',
					'title'  => __( 'First setup (10 minutes)', 'wp-itourtms' ),
					'blocks' => array(
						array(
							'steps',
							array(
								__( 'Logo: Appearance → Customize → Site Identity → Select logo. A transparent PNG or SVG about 200 × 48 px works best.', 'wp-itourtms' ),
								__( 'Menus: Appearance → Menus. Create a menu, add pages, then tick “Main menu” (header) or “Footer menu” under Menu Settings.', 'wp-itourtms' ),
								__( 'Home page: Settings → Reading → “A static page”, then choose your Homepage and Posts page.', 'wp-itourtms' ),
								__( 'Pretty links: Settings → Permalinks → “Post name” → Save.', 'wp-itourtms' ),
								__( 'Language: Settings → General → Site Language. Arabic and other right-to-left languages flip the layout automatically.', 'wp-itourtms' ),
							),
						),
					),
				),
				array(
					'id'     => 'child-theme',
					'title'  => __( 'Child theme', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'Updates replace the theme’s files. Put your own changes in the WP iTourTMS Child theme (included in the package) so they survive updates. Activate the child theme; it loads the parent automatically.', 'wp-itourtms' ) ),
					),
				),
			),
		),
		'manual' => array(
			'title'    => __( 'User manual', 'wp-itourtms' ),
			'sections' => array(
				array(
					'id'     => 'pages',
					'title'  => __( 'Create and edit pages', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'Pages are built with the WordPress block editor. No page builder is needed. Ready-made sections (hero banner, reasons to book, call to action) are under + → Patterns → “WP iTourTMS”.', 'wp-itourtms' ) ),
						array(
							'steps',
							array(
								__( 'Go to Pages → Add New Page and type a title.', 'wp-itourtms' ),
								__( 'Click + (top left) to add a block: Paragraph, Heading, Image, Columns, Buttons, Cover and so on. Type / in an empty line to search blocks quickly.', 'wp-itourtms' ),
								__( 'Select a block to change its colour, spacing or width in the right-hand panel. The theme’s colours appear as swatches.', 'wp-itourtms' ),
								__( 'Use “Wide width” or “Full width” on Image, Cover and Group blocks for banners and galleries.', 'wp-itourtms' ),
								__( 'Click Publish (or Update). Use Preview first to check it on the site.', 'wp-itourtms' ),
							),
						),
					),
				),
				array(
					'id'     => 'styles',
					'title'  => __( 'Colours and fonts for the whole site', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'Appearance → Design → Styles changes the colours, fonts and button style everywhere at once, and the change shows in the editor too. Appearance → Fonts adds or removes font families. To undo, open Styles → ⋮ → Reset.', 'wp-itourtms' ) ),
						array( 'note', __( 'Keep text readable: dark text on light backgrounds (or the reverse) with strong contrast.', 'wp-itourtms' ) ),
					),
				),
				array(
					'id'     => 'images',
					'title'  => __( 'Images', 'wp-itourtms' ),
					'blocks' => array(
						array( 'list', array( __( 'Upload photos at least 1600 px wide for full-width banners; WordPress makes the smaller sizes for you.', 'wp-itourtms' ), __( 'Always fill in “Alternative text” to describe the photo for screen readers and search engines.', 'wp-itourtms' ), __( 'Featured image (right-hand panel on posts and pages) is the picture shown in blog lists and when the page is shared.', 'wp-itourtms' ) ) ),
					),
				),
				array(
					'id'     => 'blog',
					'title'  => __( 'Blog posts', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'Posts → Add New Post. Give each post a featured image and a category. Posts appear on the page chosen as “Posts page” under Settings → Reading. To keep one post at the top, tick “Stick to the top of the blog” in the post settings.', 'wp-itourtms' ) ),
					),
				),
				array(
					'id'     => 'menus',
					'title'  => __( 'Menus', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'Appearance → Menus. Drag items to reorder them; drag an item slightly right to make it a drop-down under the item above. The header shows two levels; the footer shows one.', 'wp-itourtms' ) ),
					),
				),
				array(
					'id'     => 'comments',
					'title'  => __( 'Comments', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'Turn comments on or off for the whole site under Settings → Discussion, or per post in the post settings. Approve or delete them under Comments.', 'wp-itourtms' ) ),
					),
				),
				array(
					'id'     => 'help',
					'title'  => __( 'Getting help', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'Check this manual and the Documentation tab first. For support, contact the author through the Author URI in the theme details (Appearance → Themes → WP iTourTMS → Theme Details).', 'wp-itourtms' ) ),
					),
				),
			),
		),
		'docs'   => array(
			'title'    => __( 'Documentation', 'wp-itourtms' ),
			'sections' => array(
				array(
					'id'     => 'files',
					'title'  => __( 'Theme files', 'wp-itourtms' ),
					'blocks' => array(
						array( 'code', "style.css              theme header\nfunctions.php          setup, assets\ntheme.json             colours, fonts, spacing, layout (editor + site)\nheader.php, footer.php site header and footer\nindex.php              posts, pages, archives, search\n404.php, comments.php\ntemplate-parts/        content-single, content-summary, content-none\nassets/css/main.css    styles (uses theme.json presets)\nassets/js/main.js      front-end script (vanilla, deferred)\npatterns/              block patterns (hero, why-book, call-to-action)\ninc/                   admin page and this manual\nlanguages/             wp-itourtms.pot" ),
					),
				),
				array(
					'id'     => 'tokens',
					'title'  => __( 'Colours, fonts and spacing', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'All design values live in theme.json and become CSS custom properties such as --wp--preset--color--brand. Change them in a child theme’s theme.json and both the editor and the site update; you don’t need to edit CSS.', 'wp-itourtms' ) ),
						array( 'code', "{\n  \"version\": 3,\n  \"settings\": { \"color\": { \"palette\": [\n    { \"slug\": \"brand\", \"name\": \"Brand\", \"color\": \"#0a7d6b\" }\n  ] } }\n}" ),
						array( 'note', __( 'Keep at least 4.5:1 contrast between text and its background. The default palette meets WCAG 2.2 AA.', 'wp-itourtms' ) ),
					),
				),
				array(
					'id'     => 'templates',
					'title'  => __( 'Overriding templates', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'Copy any template or template part into the child theme at the same path and edit the copy. For example, to change how posts look in lists, copy template-parts/content-summary.php.', 'wp-itourtms' ) ),
					),
				),
				array(
					'id'     => 'hooks',
					'title'  => __( 'Hooks for developers', 'wp-itourtms' ),
					'blocks' => array(
						array( 'list', array( __( 'wp_itourtms_manual (filter): add tabs or sections to this manual, e.g. from a plugin.', 'wp-itourtms' ), __( 'Standard WordPress hooks: wp_head, wp_body_open, wp_footer, and the_content.', 'wp-itourtms' ) ) ),
						array( 'code', "add_filter( 'wp_itourtms_manual', function ( \$manual ) {\n\t\$manual['manual']['sections'][] = array(\n\t\t'id'     => 'my-section',\n\t\t'title'  => 'My section',\n\t\t'blocks' => array( array( 'p', 'Text…' ) ),\n\t);\n\treturn \$manual;\n} );" ),
					),
				),
				array(
					'id'     => 'i18n',
					'title'  => __( 'Translation and right-to-left', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'Every string uses the wp-itourtms text domain. Translate languages/wp-itourtms.pot with Poedit or Loco Translate and save wp-itourtms-{locale}.mo in wp-content/languages/themes/. Layout uses CSS logical properties, so right-to-left languages need no extra stylesheet.', 'wp-itourtms' ) ),
					),
				),
				array(
					'id'     => 'a11y',
					'title'  => __( 'Accessibility', 'wp-itourtms' ),
					'blocks' => array(
						array( 'list', array( __( 'Skip link to the main content as the first focusable element.', 'wp-itourtms' ), __( 'Visible focus outline on every link, button and form field.', 'wp-itourtms' ), __( 'Menus work with the keyboard; drop-downs open on focus.', 'wp-itourtms' ), __( 'Animations are switched off when the visitor prefers reduced motion.', 'wp-itourtms' ) ) ),
					),
				),
				array(
					'id'     => 'privacy',
					'title'  => __( 'Privacy', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'The theme stores no personal data, sets no cookies, loads nothing from other servers and does no tracking.', 'wp-itourtms' ) ),
					),
				),
				array(
					'id'     => 'credits',
					'title'  => __( 'Credits and licence', 'wp-itourtms' ),
					'blocks' => array(
						array( 'p', __( 'WP iTourTMS © 2026 Mohamed Gouda, licensed under the GNU GPL v2 or later. The theme uses no third-party code, fonts or images; the system font stack is used.', 'wp-itourtms' ) ),
					),
				),
				array(
					'id'     => 'changelog',
					'title'  => __( 'Changelog', 'wp-itourtms' ),
					'blocks' => array(
						array( 'list', array( __( '0.1.0: first release.', 'wp-itourtms' ) ) ),
					),
				),
			),
		),
	);

	/**
	 * Filter the manual (add tabs or sections).
	 *
	 * @param array $manual Manual content.
	 */
	return (array) apply_filters( 'wp_itourtms_manual', $manual );
}

/**
 * Render a list of sections as HTML (escaped).
 *
 * @param array<int,array<string,mixed>> $sections Sections.
 */
function wp_itourtms_manual_sections_html( array $sections ): string {
	$html = '';
	foreach ( $sections as $s ) {
		$html .= '<section class="wp-itourtms-doc" id="' . esc_attr( $s['id'] ) . '"><h2>' . esc_html( $s['title'] ) . '</h2>';
		foreach ( $s['blocks'] as $b ) {
			list( $type, $content ) = $b;
			switch ( $type ) {
				case 'steps':
				case 'list':
					$tag   = 'steps' === $type ? 'ol' : 'ul';
					$html .= '<' . $tag . '>' . implode( '', array_map( static fn( $i ) => '<li>' . esc_html( $i ) . '</li>', (array) $content ) ) . '</' . $tag . '>';
					break;
				case 'code':
					$html .= '<pre><code>' . esc_html( (string) $content ) . '</code></pre>';
					break;
				case 'note':
					$html .= '<p class="wp-itourtms-note"><strong>' . esc_html__( 'Note:', 'wp-itourtms' ) . '</strong> ' . esc_html( (string) $content ) . '</p>';
					break;
				default:
					$html .= '<p>' . esc_html( (string) $content ) . '</p>';
			}
		}
		$html .= '</section>';
	}
	return $html;
}
