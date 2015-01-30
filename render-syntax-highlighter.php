<?php
/*
* Plugin Name: Render Syntax Highlighter
* Plugin Author: Joel Worsham & Kyle Maurer
* Version: 0.1.0
*/

// Exit if loaded directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

// Define all plugin constants.

/**
 * The version of Render.
 *
 * @since 1.0.0
 */
define( 'RENDER_SYNTAXHIGHLIGHTER_VERSION', '0.1.0' );

/**
 * The absolute server path to Render's root directory.
 *
 * @since 1.0.0
 */
define( 'RENDER_SYNTAXHIGHLIGHTER_PATH', plugin_dir_path( __FILE__ ) );

/**
 * The URI to Render's root directory.
 *
 * @since 1.0.0
 */
define( 'RENDER_SYNTAXHIGHLIGHTER_URL', plugins_url( '', __FILE__ ) );

class Render_SyntaxHighlighter {

	// Create an array of the available brushes (languages)
	private $brushes = array(
		'AppleScript',
		'AS3',
		'Bash',
		'ColdFusion',
		'Cpp',
		'CSharp',
		'Css',
		'Delphi',
		'Diff',
		'Erlang',
		'Groovy',
		'Java',
		'JavaFX',
		'JScript',
		'Perl',
		'Php',
		'Plain',
		'PowerShell',
		'Python',
		'Ruby',
		'Sass',
		'Scala',
		'Sql',
		'Vb',
		'Xml'
	);

	// Create an array of the available styles
	private $styles = array(
		'Default',
		'Django',
		'Eclipse',
		'Emacs',
		'FadeToGrey',
		'MDUltra',
		'Midnight',
		'RDark'
	);

	private $add_scripts = array();
	private $add_styles = array();

	/**
	 * Constructs the plugin.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initializes the plugin.
	 *
	 * @since 0.1.0
	 */
	public function init() {

		// Bail if Render isn't loaded
		if ( ! class_exists( 'Render' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice' ) );

			return;
		}

		self::register_scripts();

		$this->add_shortcodes();

		// Add content attribute to Modal
		add_filter( 'render_att_pre_loop', array( $this, 'add_content_attribute' ), 10, 3 );

		// Get our sripts, and then add them (on the frontend)
		add_action( 'wp_enqueue_scripts', array( $this, 'get_scripts' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ), 11 );

		// #1 No we re-run the shortcode with the correct callback
		add_filter( 'the_content', array( $this, '_run_shortcode' ), 7 );

		// Translation ready
		load_plugin_textdomain( 'Render_SyntaxHighlighter', false, RENDER_SYNTAXHIGHLIGHTER_PATH . '/languages' );

		// Integrate with Render TinyMCE rendering
//		add_action( 'render_tinymce_ajax', array( $this, 'get_scripts' ) );
//		add_action( 'render_tinymce_before_content', array( $this, 'add_tinymce_scripts' ) );

		// Add EDD styles to tinymce
//		add_filter( 'render_editor_styles', array( $this, 'add_editor_styles' ) );
	}

	function add_content_attribute( $atts, $code, $shortcode ) {

		if ( $code == 'render_syntax_highlighter' ) {
			$atts = array_merge(
				array(
					'content' => array(
						'type'     => 'textarea',
						'label'    => __( 'Code', 'Render_SyntaxHighlighter' ),
						'required' => true,
					)
				),
				$atts
			);
		}

		return $atts;
	}

	/**
	 * Adds the shortcodes to the plugin.
	 *
	 * @since 0.1.0
	 */
	private function add_shortcodes() {

		// Integrate with Render
		render_add_shortcode( array(
			'code'        => 'render_syntax_highlighter',
			'function'    => is_admin() ? '_render_sc_syntax_highlighter' : '__return_false',
			'title'       => __( 'Syntax Highlighter', 'Render_SyntaxHighlighter' ),
			'category'    => 'design',
			'description' => __( 'Allows you to display snippets of code with syntax highlighting in your content.', 'Render_SyntaxHighlighter' ),
			'atts'        => array(
				'language' => array(
					'label'      => __( 'Code Language', 'Render_SyntaxHighlighter' ),
					'type'       => 'selectbox',
					'properties' => array(
						// Make keys the lowercase version of the name
						'options' => array_combine( $this->brushes, $this->brushes ),
					),
				),
				'style'    => array(
					'label'      => __( 'Code Style', 'Render_SyntaxHighlighter' ),
					'type'       => 'selectbox',
					'properties' => array(
						// Make keys the lowercase version of the name
						'options' => array_combine( $this->styles, $this->styles ),
					),
				),
			),
			'wrapping'    => true,
			'source'      => 'Render Extension',
			'render'      => array(
				'noStyle'            => true,
				'displayBlock'       => true,
				'contentNonEditable' => true,
			),
		) );
	}

	/**
	 * Registers the scripts and stylesheets needed for Syntax Highlighter to work.
	 *
	 * @since 0.1.0
	 */
	public function register_scripts() {

		// Register the main SyntaxHighlighting script
		wp_register_script(
			'render-syntaxhighlighter-shCore',
			RENDER_SYNTAXHIGHLIGHTER_URL . '/js/shCore.js',
			array(),
			defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : RENDER_SYNTAXHIGHLIGHTER_VERSION,
			true
		);

		// Register the brushes, include main script as a dependency
		foreach ( $this->brushes as $brush ) {
			wp_register_script(
				'render-syntaxhighlighter-sh' . $brush,
				RENDER_SYNTAXHIGHLIGHTER_URL . '/js/brushes/shBrush' . $brush . '.js',
				array( 'render-syntaxhighlighter-shCore' ),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : RENDER_SYNTAXHIGHLIGHTER_VERSION,
				true
			);
		}

		// Register the stylesheets
		foreach ( $this->styles as $style ) {
			wp_register_style(
				'render-syntaxhighlighter-sh' . $style,
				RENDER_SYNTAXHIGHLIGHTER_URL . '/css/shTheme' . $style . '.css',
				array( 'render-syntaxhighlighter-shCore' )
			);
		}

		wp_register_style(
			'render-syntaxhighlighter-shCore',
			RENDER_SYNTAXHIGHLIGHTER_URL . '/css/shCore.css'
		);

		wp_register_style(
			'render-syntaxhighlighter-extend',
			RENDER_SYNTAXHIGHLIGHTER_URL . '/css/render-extend.css'
		);
	}

	public function get_scripts() {

		global $post, $shortcode_tags;

		// Bail if the $post object isn't present, if it has no post content, or if post content is empty
		if ( ! is_object( $post ) || ! isset( $post->post_content ) || empty( $post->post_content ) ) {
			return;
		}

		// Make WP think that syntax highlighter is the only shortocde when getting the shortcode RegEx (then set it back)
		$backup_shortcode_tags = $shortcode_tags;
		$shortcode_tags        = array(
			'render_syntax_highlighter' => null,
		);
		$pattern               = get_shortcode_regex();
		$shortcode_tags        = $backup_shortcode_tags;

		preg_replace_callback( "/$pattern/s", array( $this, '_match_codes' ), $post->post_content );
	}

	/**
	 * Adds our styles and scripts based on the presence of shortcodes
	 *
	 * @since  0.1.0
	 * @access Private
	 *
	 * @param array $matches The found matches.
	 */
	public function _match_codes( $matches ) {

		// "Extract" the atts
		$atts = $matches[3];

		$atts = shortcode_atts( array(
			'style'    => 'Default',
			'language' => 'Php'
		), shortcode_parse_atts( $atts ) );

		// If we've found even one syntax highilghter shortcode, load the dependencies (just once though!)
		static $did_one;
		if ( $did_one !== true ) {

			// Add styles
			$this->add_styles[] = 'render-syntaxhighlighter-shDefault';
			$this->add_styles[] = "render-syntaxhighlighter-sh{$atts['style']}";
			$this->add_styles[] = 'render-syntaxhighlighter-shCore';
			$this->add_styles[] = 'render-syntaxhighlighter-extend';

			// Add the script
			$this->add_scripts[] = 'render-syntaxhighlighter-shCore';

			add_action( 'wp_print_footer_scripts', array( __CLASS__, 'print_highlight_js_function' ), 100 );
			$did_one = true;
		}

		// Add dynamic scripts
		static $scripts;
		if ( ! in_array( $atts['language'], (array) $scripts ) ) {
			$this->add_scripts[] = "render-syntaxhighlighter-sh{$atts['language']}";
			$scripts[]           = $atts['language'];
		}
	}

	public function add_scripts() {

		foreach ( $this->add_scripts as $script ) {
			wp_enqueue_script( $script );
		}

		foreach ( $this->add_styles as $style ) {
			wp_enqueue_style( $style );
		}
	}

	/**
	 * Used in the usl_display_code shortcode callback to run the needed SH function
	 */
	public function print_highlight_js_function() {
		echo '<script type="text/javascript">SyntaxHighlighter.all();console.log(SyntaxHighlighter.defaults);</script>';
	}

	/**
	 * This function temporarily removes the usl_display_code shortcode so that it can run without the standard
	 * the_content filters http://www.viper007bond.com/2009/11/22/wordpress-code-earlier-shortcodes/
	 *
	 * @since  0.1.0
	 * @access Private
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function _run_shortcode( $content ) {

		global $shortcode_tags;

		$orig_shortcode_tags = $shortcode_tags;
		remove_all_shortcodes();

		add_shortcode( 'render_syntax_highlighter', '_render_sc_syntax_highlighter' );
		$content = do_shortcode( $content );

		$shortcode_tags = $orig_shortcode_tags;

		return $content;
	}

	/**
	 * Display a notice in the admin if is not active.
	 *
	 * @since 0.1.0
	 */
	public static function notice() {
		?>
		<div class="error">
			<p>
				<?php
				printf(
					__( 'You have activated a plugin that requires %s. Please install and activate both to continue using Render Syntax Highlighter.', 'Render_SyntaxHighlighter' ),
					'<a href="http://renderwp.com/?utm_source=Render%20EDD&utm_medium=Notice&utm_campaign=Render%20EDD%20Notice
//">Render</a>'
				);
				?>
			</p>
		</div>
	<?php
	}
}

new Render_SyntaxHighlighter();

function _render_sc_syntax_highlighter( $atts = array(), $content = '' ) {

	$atts = shortcode_atts( array(
		'language' => 'Php',
	), $atts );

	$atts = render_esc_atts( $atts );

	return '<pre class="brush: ' . strtolower( $atts['language'] ) . ';">' . $content . '</pre>';
}