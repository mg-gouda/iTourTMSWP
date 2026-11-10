<?php
/**
 * Product edit panel: one box with vertical tabs, one tab per group of related fields. Every field
 * saves on its own (on change, or when a text field loses focus) through admin-ajax; the post's
 * Update button saves everything too. Used by Tour Packages, Excursions and any later product.
 *
 * A section: id => [ 'label' => …, 'render' => fn( WP_Post ), 'save' => fn( int $post_id, array $input ),
 * 'badge' => fn( int $post_id ): string ]. Inputs are named itp[section][field] (Panel::name()).
 *
 * @package ITour\Core
 */

namespace ITour\Core\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registration, rendering, saving.
 */
final class Panel {

	/**
	 * Sections per post type.
	 *
	 * @var array<string,array{title:string,sections:array<string,array<string,mixed>>}>
	 */
	private static array $panels = array();

	/**
	 * Add a panel to a post type (call on init or earlier).
	 *
	 * @param string                            $post_type Post type.
	 * @param string                            $title     Box title, e.g. "Tour details".
	 * @param array<string,array<string,mixed>> $sections  Sections in tab order.
	 */
	public static function register( string $post_type, string $title, array $sections ): void {
		if ( ! self::$panels ) {
			add_action( 'add_meta_boxes', array( self::class, 'box' ) );
			add_action( 'save_post', array( self::class, 'save_post' ) );
			if ( ! self::$screens ) {
				add_action( 'wp_ajax_itour_panel_save', array( self::class, 'ajax' ) );
			}
		}
		self::$panels[ $post_type ] = array(
			'title'    => $title,
			'sections' => $sections,
		);
	}

	/**
	 * Add tabs to a post type's panel (e.g. SEO tabs on tours), or give the type a panel when it has none.
	 * Call on init after the type's own panel is registered (priority > 20).
	 *
	 * @param string                            $post_type Post type.
	 * @param string                            $title     Title used when the type has no panel yet.
	 * @param array<string,array<string,mixed>> $sections  Extra sections.
	 */
	public static function extend( string $post_type, string $title, array $sections ): void {
		if ( isset( self::$panels[ $post_type ] ) ) {
			self::$panels[ $post_type ]['sections'] += $sections;
			return;
		}
		self::register( $post_type, $title, $sections );
	}

	/**
	 * Run one section's own save code with panel-shaped input (used by the bulk import, so imported
	 * data goes through exactly the same checks as the edit screen).
	 *
	 * @param string              $post_type Post type.
	 * @param string              $section   Section id.
	 * @param int                 $post_id   Post id.
	 * @param array<string,mixed> $input     Input as the panel would post it.
	 */
	public static function save_section( string $post_type, string $section, int $post_id, array $input ): void {
		$s = self::$panels[ $post_type ]['sections'][ $section ] ?? null;
		if ( $s ) {
			( $s['save'] )( $post_id, $input );
		}
	}

	/**
	 * Input name for a field: itp[section][field].
	 *
	 * @param string $section Section id.
	 * @param string $field   Field key.
	 */
	public static function name( string $section, string $field ): string {
		return 'itp[' . $section . '][' . $field . ']';
	}

	/**
	 * Register the box.
	 *
	 * @param string $post_type Post type.
	 */
	public static function box( $post_type ): void {
		if ( isset( self::$panels[ $post_type ] ) ) {
			add_meta_box( 'itour-panel', self::$panels[ $post_type ]['title'], array( self::class, 'render' ), $post_type, 'normal', 'high' );
		}
	}

	/**
	 * The panel.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function render( \WP_Post $post ): void {
		$panel = self::$panels[ $post->post_type ];
		self::markup( $panel['title'], $panel['sections'], $post, 'itour_panel_' . $post->ID, ' data-post="' . (int) $post->ID . '"' );
	}

	/**
	 * Settings screens (no post) that use the same tabs and save-as-you-go, e.g. Transfers → Rules.
	 *
	 * @var array<string,array{title:string,cap:string,sections:array<string,array<string,mixed>>}>
	 */
	private static array $screens = array();

	/**
	 * Add a settings screen panel. Its sections get `null` instead of a post and save with post id 0.
	 *
	 * @param string                            $id       Screen id.
	 * @param string                            $title    Screen title.
	 * @param string                            $cap      Capability needed to save.
	 * @param array<string,array<string,mixed>> $sections Sections in tab order.
	 */
	public static function register_screen( string $id, string $title, string $cap, array $sections ): void {
		if ( ! self::$panels && ! self::$screens ) {
			add_action( 'wp_ajax_itour_panel_save', array( self::class, 'ajax' ) );
		}
		self::$screens[ $id ] = array(
			'title'    => $title,
			'cap'      => $cap,
			'sections' => $sections,
		);
	}

	/**
	 * Print a settings screen panel (call from the admin page callback).
	 *
	 * @param string $id Screen id.
	 */
	public static function render_screen( string $id ): void {
		$s = self::$screens[ $id ];
		echo '<div class="wrap itp-screen"><h1>' . esc_html( $s['title'] ) . '</h1>';
		self::markup( $s['title'], $s['sections'], null, 'itour_panel_screen_' . $id, ' data-screen="' . esc_attr( $id ) . '"' );
		echo '</div>';
	}

	/**
	 * Shared markup: bar, tab rail, panes.
	 *
	 * @param string                            $title    Accessible name of the tab list.
	 * @param array<string,array<string,mixed>> $sections Sections.
	 * @param \WP_Post|null                     $post     Post, or null on a settings screen.
	 * @param string                            $nonce    Nonce action.
	 * @param string                            $attrs    Data attributes for the root (escaped).
	 */
	private static function markup( string $title, array $sections, ?\WP_Post $post, string $nonce, string $attrs ): void {
		$panel = array(
			'title'    => $title,
			'sections' => $sections,
		);
		wp_enqueue_style( 'itour-panel', plugins_url( 'assets/panel.css', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION );
		wp_enqueue_script( 'itour-panel', plugins_url( 'assets/panel.js', ITOUR_CORE_FILE ), array(), ITOUR_CORE_VERSION, true );
		wp_localize_script(
			'itour-panel',
			'itourPanel',
			array(
				'url'    => admin_url( 'admin-ajax.php' ),
				'saving' => __( 'Saving…', 'itour-core' ),
				'saved'  => __( 'All changes saved', 'itour-core' ),
				'failed' => __( 'Couldn’t save. Check your connection.', 'itour-core' ),
				'retry'  => __( 'Retry', 'itour-core' ),
			)
		);
		wp_nonce_field( $nonce, '_itour_panel_nonce' );
		$first = (string) array_key_first( $panel['sections'] );
		echo '<div class="itp" data-itp' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by callers.
		echo '<div class="itp-bar"><p class="itp-hint">' . esc_html__( 'Changes save as you go.', 'itour-core' ) . '</p>';
		echo '<p class="itp-status" role="status" aria-live="polite" data-itp-status></p></div>';
		echo '<div class="itp-split"><div class="itp-nav" role="tablist" aria-orientation="vertical" aria-label="' . esc_attr( $panel['title'] ) . '">';
		foreach ( $panel['sections'] as $id => $s ) {
			$badge = isset( $s['badge'] ) ? (string) ( $s['badge'] )( $post ? $post->ID : 0 ) : '';
			printf(
				'<button type="button" role="tab" class="itp-tab" id="itp-tab-%1$s" aria-controls="itp-sec-%1$s" aria-selected="%2$s" tabindex="%3$s" data-itp-tab="%1$s"><span>%4$s</span><span class="itp-badge" data-itp-badge="%1$s"%5$s>%6$s</span></button>',
				esc_attr( $id ),
				$id === $first ? 'true' : 'false',
				$id === $first ? '0' : '-1',
				esc_html( $s['label'] ),
				'' === $badge ? ' hidden' : '',
				esc_html( $badge )
			);
		}
		echo '</div><div class="itp-panes">';
		foreach ( $panel['sections'] as $id => $s ) {
			printf( '<section class="itp-pane" role="tabpanel" id="itp-sec-%1$s" aria-labelledby="itp-tab-%1$s" data-itp-section="%1$s"%2$s>', esc_attr( $id ), $id === $first ? '' : ' hidden' );
			echo '<h3 class="itp-pane-title">' . esc_html( $s['label'] ) . '</h3>';
			( $s['render'] )( $post );
			echo '</section>';
		}
		echo '</div></div></div>';
	}

	/**
	 * One labelled field row.
	 *
	 * @param string $label   Label text.
	 * @param string $control Control HTML (already escaped).
	 * @param string $help    Help text.
	 * @param string $control_id     Id of the control the label points to ('' = a group, uses a heading instead).
	 */
	public static function row( string $label, string $control, string $help = '', string $control_id = '' ): string {
		$lbl = '' !== $control_id ? '<label class="itp-label" for="' . esc_attr( $control_id ) . '">' . esc_html( $label ) . '</label>' : '<p class="itp-label">' . esc_html( $label ) . '</p>';
		return '<div class="itp-row"><div class="itp-row-text">' . $lbl . ( '' !== $help ? '<p class="itp-help">' . esc_html( $help ) . '</p>' : '' ) . '</div><div class="itp-control">' . $control . '</div></div>';
	}

	/**
	 * A text/number/date input.
	 *
	 * @param string               $section Section id.
	 * @param string               $field   Field key.
	 * @param mixed                $value   Value.
	 * @param string               $type    Input type.
	 * @param array<string,string> $attrs   Extra attributes.
	 */
	public static function input( string $section, string $field, $value, string $type = 'text', array $attrs = array() ): string {
		$a = '';
		foreach ( $attrs as $k => $v ) {
			$a .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}
		return '<input class="itp-input itp-input--' . esc_attr( $type ) . '" type="' . esc_attr( $type ) . '" id="itp-' . esc_attr( $section . '-' . $field ) . '" name="' . esc_attr( self::name( $section, $field ) ) . '" value="' . esc_attr( (string) $value ) . '"' . $a . '>';
	}

	/**
	 * A select.
	 *
	 * @param string                   $section Section id.
	 * @param string                   $field   Field key.
	 * @param array<int|string,string> $options value => label.
	 * @param mixed                    $value   Selected.
	 */
	public static function select( string $section, string $field, array $options, $value ): string {
		$h = '<select class="itp-input itp-select" id="itp-' . esc_attr( $section . '-' . $field ) . '" name="' . esc_attr( self::name( $section, $field ) ) . '">';
		foreach ( $options as $k => $label ) {
			$h .= '<option value="' . esc_attr( (string) $k ) . '"' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $h . '</select>';
	}

	/**
	 * Toggle chips (checkboxes) or a segmented choice (radios).
	 *
	 * @param string                   $section  Section id.
	 * @param string                   $field    Field key.
	 * @param array<int|string,string> $options  value => label.
	 * @param array<int|string>|string $value    Checked value(s).
	 * @param bool                     $multiple Checkboxes (true) or radios.
	 */
	public static function choices( string $section, string $field, array $options, $value, bool $multiple = true ): string {
		$on = array_map( 'strval', (array) $value );
		$h  = '<div class="itp-chips' . ( $multiple ? '' : ' itp-chips--one' ) . '">';
		if ( $multiple ) {
			$h .= '<input type="hidden" name="' . esc_attr( self::name( $section, $field ) ) . '[]" value="">'; // So "none ticked" still posts.
		}
		foreach ( $options as $k => $label ) {
			$h .= '<label class="itp-chip"><input type="' . ( $multiple ? 'checkbox' : 'radio' ) . '" name="' . esc_attr( self::name( $section, $field ) . ( $multiple ? '[]' : '' ) ) . '" value="' . esc_attr( (string) $k ) . '"' . checked( in_array( (string) $k, $on, true ), true, false ) . '><span>' . esc_html( $label ) . '</span></label>';
		}
		return $h . '</div>';
	}

	/**
	 * Section input from a request.
	 *
	 * @param string $section Section id.
	 * @return array<string,mixed>
	 */
	private static function input_for( string $section ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- callers verify the nonce; each section's saver sanitizes.
		return isset( $_POST['itp'][ $section ] ) && is_array( $_POST['itp'][ $section ] ) ? wp_unslash( $_POST['itp'][ $section ] ) : array();
	}

	/**
	 * Save one section from the panel (admin-ajax). Answers with the section's new badge.
	 */
	public static function ajax(): void {
		$section = sanitize_key( wp_unslash( $_POST['section'] ?? '' ) );
		$screen  = sanitize_key( wp_unslash( $_POST['screen'] ?? '' ) );
		if ( '' !== $screen ) {
			$sc = self::$screens[ $screen ] ?? null;
			if ( ! $sc || ! isset( $sc['sections'][ $section ] ) || ! current_user_can( $sc['cap'] ) || ! check_ajax_referer( 'itour_panel_screen_' . $screen, '_itour_panel_nonce', false ) ) {
				wp_send_json_error( array( 'message' => __( 'You can’t edit this item, or the page expired. Reload it.', 'itour-core' ) ), 403 );
			}
			$s = $sc['sections'][ $section ];
			( $s['save'] )( 0, self::input_for( $section ) );
			wp_send_json_success( array( 'badge' => isset( $s['badge'] ) ? (string) ( $s['badge'] )( 0 ) : '' ) );
		}
		$post_id = absint( $_POST['post'] ?? 0 );
		$panel   = self::$panels[ (string) get_post_type( $post_id ) ] ?? null;
		if ( ! $panel || ! isset( $panel['sections'][ $section ] ) || ! current_user_can( 'edit_post', $post_id ) || ! check_ajax_referer( 'itour_panel_' . $post_id, '_itour_panel_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You can’t edit this item, or the page expired. Reload it.', 'itour-core' ) ), 403 );
		}
		$s = $panel['sections'][ $section ];
		( $s['save'] )( $post_id, self::input_for( $section ) );
		wp_send_json_success( array( 'badge' => isset( $s['badge'] ) ? (string) ( $s['badge'] )( $post_id ) : '' ) );
	}

	/**
	 * Save every section when the post itself is saved (Update / Publish).
	 *
	 * @param int $post_id Post id.
	 */
	public static function save_post( $post_id ): void {
		$panel = self::$panels[ (string) get_post_type( $post_id ) ] ?? null;
		if ( ! $panel || ! isset( $_POST['_itour_panel_nonce'], $_POST['itp'] ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_itour_panel_nonce'] ) ), 'itour_panel_' . $post_id ) ) {
			return;
		}
		foreach ( $panel['sections'] as $id => $s ) {
			if ( isset( $_POST['itp'][ $id ] ) ) {
				( $s['save'] )( (int) $post_id, self::input_for( $id ) );
			}
		}
	}
}
