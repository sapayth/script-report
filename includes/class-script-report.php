<?php
/**
 * Main plugin class: audit and report script/style dependencies.
 *
 * @package Script_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Script_Report class.
 */
class Script_Report {

	const GET_PARAM             = 'script_report';
	const NONCE_ACTION          = 'script_report_view';
	const BACKTRACE_FRAME_LIMIT = 15;
	const MAX_INDENT_DEPTH      = 5;

	/**
	 * Registration source per handle: script_handle => label, style_handle => label.
	 *
	 * @var array
	 */
	private $script_sources = array();
	private $style_sources  = array();

	/**
	 * Cache for get_file_size() by normalized src.
	 *
	 * @var array
	 */
	private $file_size_cache = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_start_buffer' ) );
		add_action( 'admin_footer', array( $this, 'maybe_dump_dependencies' ), PHP_INT_MAX );
		add_action( 'template_redirect', array( $this, 'maybe_start_buffer' ) );
		add_action( 'wp_footer', array( $this, 'maybe_dump_dependencies' ), PHP_INT_MAX );
		add_action( 'wp_register_script', array( $this, 'record_script_registration' ), 10, 5 );
		add_action( 'wp_register_style', array( $this, 'record_style_registration' ), 10, 5 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_link' ), 100 );
	}

	/**
	 * Whether the current request should show the report.
	 *
	 * @return bool
	 */
	public function is_report_request() {
		if ( ! isset( $_GET[ self::GET_PARAM ] ) || $_GET[ self::GET_PARAM ] !== 'true' ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( defined( 'SCRIPT_REPORT_DEBUG' ) && SCRIPT_REPORT_DEBUG ) {
			return true;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		return $nonce && wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * URL for the report with optional query args. Adds nonce so the link can be used by non-admins with the link.
	 * Uses the current request URL as base so the report shows scripts for the page you're on.
	 *
	 * @param array $args Optional query args (e.g. array( 'view' => 'tree' )).
	 * @return string
	 */
	public function get_report_url( $args = array() ) {
		$args[ self::GET_PARAM ] = 'true';
		$args['_wpnonce']        = wp_create_nonce( self::NONCE_ACTION );
		$base = is_admin()
			? admin_url( 'index.php' )
			: home_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' );
		return add_query_arg( $args, $base );
	}

	/**
	 * Add Script Report link to the admin bar (only for users who can view the report).
	 */
	public function add_admin_bar_link( $wp_admin_bar ) {
		if ( ! $wp_admin_bar ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) && ( ! defined( 'SCRIPT_REPORT_DEBUG' ) || ! SCRIPT_REPORT_DEBUG ) ) {
			return;
		}
		$wp_admin_bar->add_node(
			array(
				'id'     => 'script-report',
				'title'  => __( 'Script Report', 'script-report' ),
				'href'   => $this->get_report_url(),
				'parent' => 'site-name',
				'meta'   => array( 'class' => 'script-report-link' ),
			)
		);
	}

	/**
	 * Record script registration source from backtrace.
	 *
	 * @param string $handle    Handle.
	 * @param string $src       Source URL.
	 * @param array  $deps      Dependencies.
	 * @param mixed  $ver       Version.
	 * @param bool   $in_footer In footer.
	 */
	public function record_script_registration( $handle, $src, $deps, $ver, $in_footer ) {
		if ( ! $this->is_report_request() ) {
			return;
		}
		$this->script_sources[ $handle ] = $this->get_registration_source_from_backtrace();
	}

	/**
	 * Record style registration source from backtrace.
	 *
	 * @param string $handle Handle.
	 * @param string $src    Source URL.
	 * @param array  $deps   Dependencies.
	 * @param mixed  $ver    Version.
	 * @param string $media  Media.
	 */
	public function record_style_registration( $handle, $src, $deps, $ver, $media ) {
		if ( ! $this->is_report_request() ) {
			return;
		}
		$this->style_sources[ $handle ] = $this->get_registration_source_from_backtrace();
	}

	/**
	 * Determine plugin/theme or core label from the current call stack.
	 *
	 * @return string
	 */
	private function get_registration_source_from_backtrace() {
		$trace      = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, self::BACKTRACE_FRAME_LIMIT ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$wp_content = wp_normalize_path( WP_CONTENT_DIR );
		$wp_includes = wp_normalize_path( ABSPATH . WPINC );

		foreach ( $trace as $frame ) {
			if ( empty( $frame['file'] ) ) {
				continue;
			}
			$file = wp_normalize_path( $frame['file'] );
			if ( strpos( $file, $wp_includes ) === 0 ) {
				continue;
			}
			if ( strpos( $file, $wp_content ) !== 0 ) {
				return 'unknown';
			}
			$rel = substr( $file, strlen( $wp_content ) );
			$rel = ltrim( $rel, '/' );
			if ( preg_match( '#^plugins/([^/]+)/#', $rel, $m ) ) {
				return 'plugin: ' . $m[1];
			}
			if ( preg_match( '#^themes/([^/]+)/#', $rel, $m ) ) {
				return 'theme: ' . $m[1];
			}
		}
		return 'WordPress core';
	}

	/**
	 * Normalize a script/style src URL by stripping query string.
	 *
	 * @param string $src Source URL.
	 * @return string
	 */
	private function normalize_src( $src ) {
		if ( ! is_string( $src ) || $src === '' ) {
			return '';
		}
		return preg_replace( '/\?.*$/', '', $src );
	}

	/**
	 * Build map: normalized_src => [ handle1, handle2, ... ] for handles that share the same src.
	 *
	 * @param array $registered Registered items (handle => object with src).
	 * @return array Map of normalized_src => array of handles (only entries with more than one handle).
	 */
	private function build_duplicate_src_map( $registered ) {
		$src_to_handles = array();
		foreach ( $registered as $handle => $item ) {
			if ( empty( $item->src ) ) {
				continue;
			}
			$normalized = $this->normalize_src( $item->src );
			if ( $normalized === '' ) {
				continue;
			}
			if ( ! isset( $src_to_handles[ $normalized ] ) ) {
				$src_to_handles[ $normalized ] = array();
			}
			$src_to_handles[ $normalized ][] = $handle;
		}
		return array_filter( $src_to_handles, function ( $handles ) {
			return count( $handles ) > 1;
		} );
	}

	/**
	 * Start output buffering when report is requested.
	 */
	public function maybe_start_buffer() {
		if ( $this->is_report_request() ) {
			ob_start();
		}
	}

	/**
	 * Output report and exit when report is requested.
	 */
	public function maybe_dump_dependencies() {
		if ( ! $this->is_report_request() ) {
			return;
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		$view_raw = '';
		if ( current_user_can( 'manage_options' ) || ( defined( 'SCRIPT_REPORT_DEBUG' ) && SCRIPT_REPORT_DEBUG ) ) {
			$view_raw = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		} elseif ( isset( $_GET['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
			if ( wp_verify_nonce( $nonce, self::NONCE_ACTION ) && isset( $_GET['view'] ) ) {
				$view_raw = sanitize_key( wp_unslash( $_GET['view'] ) );
			}
		}
		$view     = $view_raw === 'tree' ? 'tree' : 'list';
		$base_url = remove_query_arg( array( 'view' ) );
		$list_url = add_query_arg( 'view', 'list', $base_url );
		$tree_url = add_query_arg( 'view', 'tree', $base_url );

		global $wp_scripts, $wp_styles, $wp_script_modules;

		$report_css_url = plugin_dir_url( SCRIPT_REPORT_FILE ) . 'assets/report.css';
		wp_register_style( 'script-report-report', $report_css_url, array(), SCRIPT_REPORT_VERSION );
		wp_enqueue_style( 'script-report-report' );
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( __( 'Script & Style Report', 'script-report' ) ); ?></title>
			<?php wp_print_styles( 'script-report-report' ); ?>
		</head>
		<body>
			<h1><?php echo esc_html( __( 'Script & Style Report', 'script-report' ) ); ?></h1>
			<div class="stats">
				<div class="stats-item"><strong><?php echo esc_html( __( 'Generated', 'script-report' ) ); ?></strong> <?php echo esc_html( current_time( 'Y-m-d H:i:s' ) ); ?></div>
			</div>

			<?php if ( $wp_scripts ) : ?>
				<?php $scripts_data = $this->get_deps_report_data( $wp_scripts ); ?>
				<div class="section">
					<h2><?php esc_html_e( 'JavaScript', 'script-report' ); ?></h2>
					<?php $this->render_section_toolbar( $view, $list_url, $tree_url ); ?>
					<?php
					$this->render_deps_stats( $wp_scripts, count( $scripts_data['needed'] ), $scripts_data['total_size'], __( 'Scripts', 'script-report' ) );
					if ( $view === 'tree' ) {
						$this->render_script_tree( $wp_scripts );
					} else {
						$this->render_deps_list( $wp_scripts, $scripts_data['print_order'], $this->script_sources, true, $scripts_data );
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( $wp_styles ) : ?>
				<?php $styles_data = $this->get_deps_report_data( $wp_styles ); ?>
				<div class="section">
					<h2><?php esc_html_e( 'CSS', 'script-report' ); ?></h2>
					<?php $this->render_section_toolbar( $view, $list_url, $tree_url ); ?>
					<?php
					$this->render_deps_stats( $wp_styles, count( $styles_data['needed'] ), $styles_data['total_size'], __( 'Styles', 'script-report' ) );
					if ( $view === 'tree' ) {
						$this->render_style_tree( $wp_styles );
					} else {
						$this->render_deps_list( $wp_styles, $styles_data['print_order'], $this->style_sources, false, $styles_data );
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( $wp_script_modules && method_exists( $wp_script_modules, 'get_enqueued' ) ) : ?>
				<div class="section">
					<h2><?php esc_html_e( 'Modules', 'script-report' ); ?></h2>
					<?php $this->render_script_modules( $wp_script_modules ); ?>
				</div>
			<?php endif; ?>

			<script>
			(function(){
				var inputs = document.querySelectorAll('.report-toolbar input.filter');
				inputs.forEach(function(input) {
					var section = input.closest('.section');
					var list = section.querySelector('.list-view');
					var tree = section.querySelector('.tree-view');
					var items = list ? list.querySelectorAll('.list-item') : [];
					var nodes = tree ? tree.querySelectorAll('.node') : [];
					input.addEventListener('input', function() {
						var q = (this.value || '').toLowerCase();
						items.forEach(function(el) { el.classList.toggle('hidden', q && el.textContent.toLowerCase().indexOf(q) === -1); });
						nodes.forEach(function(el) { el.classList.toggle('hidden', q && el.textContent.toLowerCase().indexOf(q) === -1); });
					});
				});
			})();
			</script>
		</body>
		</html>
		<?php
		die();
	}

	/**
	 * Toolbar with view toggle and filter input.
	 *
	 * @param string $current_view 'list' or 'tree'.
	 * @param string $list_url URL for list view.
	 * @param string $tree_url URL for tree view.
	 */
	private function render_section_toolbar( $current_view, $list_url, $tree_url ) {
		echo '<div class="report-toolbar">';
		echo '<a href="' . esc_url( $list_url ) . '" class="' . ( $current_view === 'list' ? 'active' : '' ) . '">' . esc_html__( 'List', 'script-report' ) . '</a>';
		echo '<a href="' . esc_url( $tree_url ) . '" class="' . ( $current_view === 'tree' ? 'active' : '' ) . '">' . esc_html__( 'Tree', 'script-report' ) . '</a>';
		echo '<input type="text" class="filter" placeholder="' . esc_attr__( 'Search…', 'script-report' ) . '" aria-label="' . esc_attr__( 'Search', 'script-report' ) . '">';
		echo '</div>';
	}

	/**
	 * Collect handles recursively (scripts or styles).
	 *
	 * @param string   $handle     Handle.
	 * @param array    $registered Registered items (by handle => object with deps).
	 * @param string   $deps_key   Property name for deps (e.g. 'deps').
	 * @param array    $needed     Output set of needed handles.
	 */
	private function collect_needed( $handle, $registered, $deps_key, &$needed ) {
		if ( isset( $needed[ $handle ] ) ) {
			return;
		}
		$needed[ $handle ] = true;
		if ( ! isset( $registered[ $handle ] ) ) {
			return;
		}
		$item = $registered[ $handle ];
		$deps = isset( $item->$deps_key ) ? $item->$deps_key : array();
		if ( ! empty( $deps ) ) {
			foreach ( $deps as $dep ) {
				$this->collect_needed( $dep, $registered, $deps_key, $needed );
			}
		}
	}

	/**
	 * Sum file size for given handles from registered items that have src.
	 *
	 * @param array $registered Registered items (handle => object with src).
	 * @param array $handles    Handles to sum.
	 * @return int
	 */
	private function sum_size( $registered, $handles ) {
		$total = 0;
		foreach ( $handles as $handle ) {
			if ( isset( $registered[ $handle ] ) && ! empty( $registered[ $handle ]->src ) ) {
				$size = $this->get_file_size( $registered[ $handle ]->src );
				if ( $size !== null ) {
					$total += $size;
				}
			}
		}
		return $total;
	}

	/**
	 * Get print order (ordered handles). WP_Dependencies::all_deps() returns bool, so we compute order from queue + deps.
	 *
	 * @param WP_Scripts|WP_Styles $wp_deps Scripts or styles dependency object (queue + registered with deps).
	 * @return array Ordered list of handles (dependencies before dependents).
	 */
	private function get_print_order( $wp_deps ) {
		$ordered = array();
		$visited = array();
		$queue   = $wp_deps->queue;
		$reg     = $wp_deps->registered;

		$visit = function( $handle ) use ( &$visit, $reg, &$ordered, &$visited ) {
			if ( isset( $visited[ $handle ] ) ) {
				return;
			}
			$visited[ $handle ] = true;
			if ( isset( $reg[ $handle ] ) && ! empty( $reg[ $handle ]->deps ) ) {
				foreach ( $reg[ $handle ]->deps as $dep ) {
					$visit( $dep );
				}
			}
			$ordered[] = $handle;
		};

		foreach ( $queue as $handle ) {
			$visit( $handle );
		}

		return $ordered;
	}

	/**
	 * Build report data for scripts or styles (needed set, print order, sizes, dependents, duplicate src).
	 * Extracted so logic can be unit tested without rendering.
	 *
	 * @param WP_Scripts|WP_Styles $wp_deps Dependency object.
	 * @return array{ needed: array, print_order: array, total_size: int, dependents: array, duplicate_src: array }
	 */
	public function get_deps_report_data( $wp_deps ) {
		$needed = array();
		foreach ( $wp_deps->queue as $handle ) {
			$this->collect_needed( $handle, $wp_deps->registered, 'deps', $needed );
		}
		$needed_handles = array_keys( $needed );
		return array(
			'needed'        => $needed,
			'print_order'   => $this->get_print_order( $wp_deps ),
			'total_size'    => $this->sum_size( $wp_deps->registered, $needed_handles ),
			'dependents'    => $this->build_dependents( $wp_deps->registered, 'deps' ),
			'duplicate_src' => $this->build_duplicate_src_map( $wp_deps->registered ),
		);
	}

	/**
	 * Build dependents map: dep_handle => [ handles that list it as dep ].
	 *
	 * @param array  $registered Registered items (handle => object with deps).
	 * @param string $deps_key   Property name for deps.
	 * @return array
	 */
	private function build_dependents( $registered, $deps_key = 'deps' ) {
		$dependents = array();
		foreach ( $registered as $handle => $item ) {
			$deps = isset( $item->$deps_key ) ? $item->$deps_key : array();
			foreach ( $deps as $dep ) {
				if ( ! isset( $dependents[ $dep ] ) ) {
					$dependents[ $dep ] = array();
				}
				$dependents[ $dep ][] = $handle;
			}
		}
		return $dependents;
	}

	/**
	 * Find top-level enqueued handles that caused this handle to load.
	 *
	 * @param string $handle      Handle.
	 * @param array  $queue       Enqueued handles.
	 * @param array  $dependents  Dependents map.
	 * @param array  $visited     Visited set (internal).
	 * @return array
	 */
	private function find_enqueued_parents( $handle, $queue, $dependents, &$visited = array() ) {
		if ( in_array( $handle, $visited, true ) ) {
			return array();
		}
		$visited[] = $handle;
		if ( in_array( $handle, $queue, true ) ) {
			return array( $handle );
		}
		$parents = array();
		if ( isset( $dependents[ $handle ] ) ) {
			foreach ( $dependents[ $handle ] as $parent ) {
				$parents = array_merge( $parents, $this->find_enqueued_parents( $parent, $queue, $dependents, $visited ) );
			}
		}
		return array_unique( $parents );
	}

	/**
	 * Render stats block for scripts or styles.
	 *
	 * @param WP_Scripts|WP_Styles $wp_deps     Dependency object.
	 * @param int                  $needed_count Number of needed items (enqueued + deps).
	 * @param int                  $total_size   Total file size in bytes.
	 * @param string               $item_name    Label for the asset type (e.g. 'Scripts', 'Styles').
	 */
	private function render_deps_stats( $wp_deps, $needed_count, $total_size, $item_name ) {
		$registered   = count( $wp_deps->registered );
		$enqueued     = count( $wp_deps->queue );
		$meta_reg     = __( 'registered on this site', 'script-report' );
		$meta_enq     = __( 'requested by theme or plugins', 'script-report' );
		$meta_loaded  = __( 'actually loaded (with dependencies)', 'script-report' );
		$label_loaded = sprintf(
			// Translators: %s is the item name (e.g. 'Scripts', 'Styles').
			 __( '%s loaded', 'script-report' ),
			 esc_html( $item_name )
			);
		echo '<div class="stats">';
		echo '<div class="stats-item"><strong>' . esc_html__( 'Registered', 'script-report' ) . '</strong> ' . (int) $registered . ' <span class="meta">' . esc_html( $meta_reg ) . '</span></div>';
		echo '<div class="stats-item"><strong>' . esc_html__( 'Enqueued', 'script-report' ) . '</strong> ' . (int) $enqueued . ' <span class="meta">' . esc_html( $meta_enq ) . '</span></div>';
		echo '<div class="stats-item"><strong>' . esc_html( $label_loaded ) . '</strong> ' . (int) $needed_count . ' <span class="meta">' . esc_html( $meta_loaded ) . '</span></div>';
		if ( $total_size > 0 ) {
			echo '<div class="stats-item"><strong>' . esc_html__( 'Size', 'script-report' ) . '</strong> ' . esc_html( $this->format_bytes( $total_size ) ) . '</div>';
		}
		echo '</div>';
	}

	/**
	 * Render tree view for scripts or styles.
	 *
	 * @param WP_Scripts|WP_Styles $wp_deps       Dependency object.
	 * @param array                $sources      Handle => registration source label.
	 * @param bool                 $is_script   True to show script-specific badges.
	 * @param string               $title       Section title.
	 * @param string               $empty_message Message when queue is empty.
	 */
	private function render_deps_tree( $wp_deps, $sources, $is_script, $title, $empty_message ) {
		echo '<h3>' . esc_html( $title ) . '</h3>';
		echo '<div class="tree-view">';
		if ( empty( $wp_deps->queue ) ) {
			echo '<p>' . esc_html( $empty_message ) . '</p>';
		} else {
			foreach ( $wp_deps->queue as $handle ) {
				$this->render_deps_node( $handle, $wp_deps, 0, array(), $sources, $is_script );
			}
		}
		echo '</div>';
	}

	/**
	 * Render a single node in the dependency tree.
	 *
	 * @param string               $handle    Handle.
	 * @param WP_Scripts|WP_Styles $wp_deps   Dependency object.
	 * @param int                  $depth     Depth for indentation.
	 * @param array                $visited   Visited handles (passed by reference).
	 * @param array                $sources   Handle => registration source label.
	 * @param bool                 $is_script True to show script-specific badges.
	 */
	private function render_deps_node( $handle, $wp_deps, $depth, $visited, $sources, $is_script ) {
		$indent_class = 'indent-' . min( $depth, self::MAX_INDENT_DEPTH );

		if ( in_array( $handle, $visited, true ) ) {
			echo '<div class="node ' . esc_attr( $indent_class ) . '"><span class="handle">' . esc_html( $handle ) . '</span><span class="badge badge-circular">' . esc_html__( 'CIRCULAR', 'script-report' ) . '</span></div>';
			return;
		}

		if ( ! isset( $wp_deps->registered[ $handle ] ) ) {
			echo '<div class="node ' . esc_attr( $indent_class ) . '"><span class="handle">' . esc_html( $handle ) . '</span><span class="badge badge-missing">' . esc_html__( 'MISSING', 'script-report' ) . '</span></div>';
			return;
		}

		$item     = $wp_deps->registered[ $handle ];
		$visited[] = $handle;

		$label_registered = __( 'Added by', 'script-report' );

		echo '<div class="node ' . esc_attr( $indent_class ) . '">';
		echo '<span class="handle">' . esc_html( $handle ) . '</span>';
		if ( in_array( $handle, $wp_deps->queue, true ) ) {
			echo '<span class="badge badge-enqueued">' . esc_html__( 'ENQUEUED', 'script-report' ) . '</span>';
		}
		if ( $is_script && ! empty( $item->extra['group'] ) ) {
			echo '<span class="badge badge-footer">' . esc_html__( 'FOOTER', 'script-report' ) . '</span>';
		}
		if ( $is_script ) {
			$this->render_inline_badge( $item );
		}
		if ( ! empty( $item->src ) ) {
			echo '<div class="src">↳ ' . esc_html( $item->src );
			if ( ! empty( $item->ver ) ) {
				echo '<span class="meta"> (v' . esc_html( $item->ver ) . ')</span>';
			}
			echo '</div>';
		}
		if ( isset( $sources[ $handle ] ) ) {
			echo '<div class="src meta">' . esc_html( $label_registered ) . ': ' . esc_html( $sources[ $handle ] ) . '</div>';
		}
		echo '</div>';

		if ( ! empty( $item->deps ) ) {
			foreach ( $item->deps as $dep ) {
				$this->render_deps_node( $dep, $wp_deps, $depth + 1, $visited, $sources, $is_script );
			}
		}
	}

	private function render_script_tree( $wp_scripts ) {
		$this->render_deps_tree(
			$wp_scripts,
			$this->script_sources,
			true,
			__( 'Scripts loaded on this page', 'script-report' ),
			__( 'No scripts loaded.', 'script-report' )
		);
	}

	private function render_style_tree( $wp_styles ) {
		$this->render_deps_tree(
			$wp_styles,
			$this->style_sources,
			false,
			__( 'Styles loaded on this page', 'script-report' ),
			__( 'No styles loaded.', 'script-report' )
		);
	}

	/**
	 * Render list view for scripts or styles.
	 *
	 * @param WP_Scripts|WP_Styles $wp_deps     Dependency object.
	 * @param array                $print_order Ordered handles.
	 * @param array                $sources     Handle => registration source label.
	 * @param bool                 $is_script   True to show script-specific badges (footer, inline).
	 * @param array                $report_data Precomputed data from get_deps_report_data().
	 */
	private function render_deps_list( $wp_deps, $print_order, $sources, $is_script, $report_data ) {
		$dependents    = $report_data['dependents'];
		$duplicate_src = $report_data['duplicate_src'];
		$needed        = $report_data['needed'];
		$all_handles   = array_keys( $needed );
		sort( $all_handles );
		$order_map = array_flip( $print_order );

		$label_registered   = __( 'Added by', 'script-report' );
		$label_same_src     = __( 'Same file as', 'script-report' );
		$label_enqueued_by  = __( 'Loaded because of', 'script-report' );
		$label_required_by  = __( 'Used by', 'script-report' );

		echo '<div class="list-view">';
		foreach ( $all_handles as $handle ) {
			if ( ! isset( $wp_deps->registered[ $handle ] ) ) {
				continue;
			}
			$item        = $wp_deps->registered[ $handle ];
			$is_enqueued = in_array( $handle, $wp_deps->queue, true );
			$order_pos   = isset( $order_map[ $handle ] ) ? $order_map[ $handle ] + 1 : null;

			echo '<div class="list-item">';
			echo '<div class="list-item-main">';
			echo '<span class="handle">' . esc_html( $handle ) . '</span>';
			if ( $order_pos !== null ) {
				echo '<span class="order-badge">#' . (int) $order_pos . '</span>';
			}
			$file_size = $this->get_file_size( $item->src );
			if ( $file_size !== null ) {
				echo '<span class="size-badge">' . esc_html( $this->format_bytes( $file_size ) ) . '</span>';
			}
			if ( $is_enqueued ) {
				echo '<span class="badge badge-enqueued">' . esc_html__( 'ENQUEUED', 'script-report' ) . '</span>';
			}
			if ( $is_script && ! empty( $item->extra['group'] ) ) {
				echo '<span class="badge badge-footer">' . esc_html__( 'FOOTER', 'script-report' ) . '</span>';
			}
			if ( $is_script ) {
				$this->render_inline_badge( $item );
			}
			if ( isset( $duplicate_src[ $this->normalize_src( $item->src ) ] ) ) {
				echo '<span class="badge badge-duplicate">' . esc_html__( 'DUPLICATE SRC', 'script-report' ) . '</span>';
			}
			echo '</div>';
			echo '<div class="list-item-meta">';
			if ( isset( $sources[ $handle ] ) ) {
				echo '<div class="meta-item"><span class="meta-label">' . esc_html( $label_registered ) . ':</span> <span class="script-list">' . esc_html( $sources[ $handle ] ) . '</span></div>';
			}
			if ( ! empty( $item->src ) ) {
				$norm = $this->normalize_src( $item->src );
				if ( isset( $duplicate_src[ $norm ] ) ) {
					$others = array_diff( $duplicate_src[ $norm ], array( $handle ) );
					if ( ! empty( $others ) ) {
						echo '<div class="meta-item"><span class="meta-label">' . esc_html( $label_same_src ) . ':</span> <span class="script-list">' . esc_html( implode( ', ', $others ) ) . '</span></div>';
					}
				}
			}
			if ( ! $is_enqueued ) {
				$visited     = array();
				$enqueued_by = $this->find_enqueued_parents( $handle, $wp_deps->queue, $dependents, $visited );
				if ( ! empty( $enqueued_by ) ) {
					echo '<div class="meta-item"><span class="meta-label">' . esc_html( $label_enqueued_by ) . ':</span> <span class="script-list">' . esc_html( implode( ', ', $enqueued_by ) ) . '</span></div>';
				}
			}
			if ( isset( $dependents[ $handle ] ) ) {
				$needed_deps = array_intersect( $dependents[ $handle ], $all_handles );
				if ( ! empty( $needed_deps ) ) {
					echo '<div class="meta-item"><span class="meta-label">' . esc_html( $label_required_by ) . ':</span> <span class="script-list">' . esc_html( implode( ', ', $needed_deps ) ) . '</span></div>';
				}
			}
			echo '</div></div>';
		}
		echo '</div>';
	}

	private function render_script_list( $wp_scripts, $print_order ) {
		$this->render_deps_list( $wp_scripts, $print_order, $this->script_sources, true, $this->get_deps_report_data( $wp_scripts ) );
	}

	private function render_style_list( $wp_styles, $print_order ) {
		$this->render_deps_list( $wp_styles, $print_order, $this->style_sources, false, $this->get_deps_report_data( $wp_styles ) );
	}

	/**
	 * Output badge for inline/localized script data if present.
	 *
	 * @param object $script Script dependency object (may have extra['data']).
	 */
	private function render_inline_badge( $script ) {
		if ( empty( $script->extra['data'] ) ) {
			return;
		}
		$len = strlen( $script->extra['data'] );
		echo '<span class="badge badge-inline">' . esc_html__( 'INLINE', 'script-report' ) . ' ' . esc_html( $this->format_bytes( $len ) ) . '</span>';
	}

	private function render_script_modules( $wp_script_modules ) {
		$registered = array();
		$enqueued   = array();

		try {
			if ( method_exists( $wp_script_modules, 'get_enqueued' ) ) {
				$enqueued = $wp_script_modules->get_enqueued();
			}
			if ( method_exists( $wp_script_modules, 'get_registered' ) ) {
				$registered = $wp_script_modules->get_registered();
			} else {
				$reflection = new ReflectionClass( $wp_script_modules );
				if ( $reflection->hasProperty( 'registered' ) ) {
					$prop = $reflection->getProperty( 'registered' );
					$prop->setAccessible( true );
					$registered = $prop->getValue( $wp_script_modules );
				}
			}
		} catch ( Exception $e ) {
			echo '<p>' . esc_html__( 'Could not load module data.', 'script-report' ) . '</p>';
			return;
		}

		echo '<div class="stats">';
		echo '<div class="stats-item"><strong>' . esc_html__( 'Registered', 'script-report' ) . '</strong> ' . count( $registered ) . ' <span class="meta">' . esc_html__( 'modules on this site', 'script-report' ) . '</span></div>';
		echo '<div class="stats-item"><strong>' . esc_html__( 'Enqueued', 'script-report' ) . '</strong> ' . count( $enqueued ) . ' <span class="meta">' . esc_html__( 'loaded on this page', 'script-report' ) . '</span></div>';
		echo '</div>';
		echo '<div class="list-view">';

		if ( empty( $registered ) ) {
			echo '<p>' . esc_html__( 'No modules registered.', 'script-report' ) . '</p>';
			echo '</div>';
			return;
		}

		$enqueued_list = is_array( $enqueued ) ? $enqueued : array_keys( $enqueued );
		foreach ( $registered as $id => $module_data ) {
			$is_enqueued = in_array( $id, $enqueued_list, true );
			$src = null;
			if ( is_array( $module_data ) && isset( $module_data['src'] ) ) {
				$src = $module_data['src'];
			} elseif ( is_object( $module_data ) && isset( $module_data->src ) ) {
				$src = $module_data->src;
			}
			$deps = array();
			if ( is_array( $module_data ) && isset( $module_data['dependencies'] ) ) {
				$deps = $module_data['dependencies'];
			} elseif ( is_object( $module_data ) && isset( $module_data->dependencies ) ) {
				$deps = $module_data->dependencies;
			}

			echo '<div class="list-item">';
			echo '<div class="list-item-main">';
			echo '<span class="handle">' . esc_html( $id ) . '</span>';
			if ( $src ) {
				$file_size = $this->get_file_size( $src );
				if ( $file_size !== null ) {
					echo '<span class="size-badge">' . esc_html( $this->format_bytes( $file_size ) ) . '</span>';
				}
			}
			if ( $is_enqueued ) {
				echo '<span class="badge badge-enqueued">ENQUEUED</span>';
			}
			echo '<span class="badge badge-module">' . esc_html__( 'MODULE', 'script-report' ) . '</span>';
			echo '</div>';
			echo '<div class="list-item-meta">';
			if ( ! empty( $deps ) ) {
				echo '<div class="meta-item"><span class="meta-label">' . esc_html__( 'Depends on', 'script-report' ) . ':</span> <span class="script-list">' . esc_html( implode( ', ', $deps ) ) . '</span></div>';
			}
			echo '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * Resolve script/style URL to local file path and return size.
	 *
	 * @param string $src URL or path.
	 * @return int|null File size in bytes or null.
	 */
	private function get_file_size( $src ) {
		if ( empty( $src ) ) {
			return null;
		}

		$normalized_src = $this->normalize_src( $src );
		if ( isset( $this->file_size_cache[ $normalized_src ] ) ) {
			return $this->file_size_cache[ $normalized_src ];
		}

		$file_path = null;

		if ( strpos( $src, 'http' ) !== 0 ) {
			$file_path = wp_normalize_path( ABSPATH . ltrim( $src, '/' ) );
		} else {
			$wp_content_url  = content_url();
			$wp_includes_url = includes_url();

			if ( strpos( $src, $wp_content_url ) === 0 ) {
				$rel = ltrim( str_replace( $wp_content_url, '', $src ), '/' );
				$file_path = wp_normalize_path( WP_CONTENT_DIR . '/' . $rel );
			} elseif ( strpos( $src, $wp_includes_url ) === 0 ) {
				$rel = ltrim( str_replace( $wp_includes_url, '', $src ), '/' );
				$file_path = wp_normalize_path( ABSPATH . WPINC . '/' . $rel );
			} else {
				$parsed = wp_parse_url( $src );
				if ( ! empty( $parsed['path'] ) ) {
					$file_path = wp_normalize_path( ABSPATH . ltrim( $parsed['path'], '/' ) );
				}
			}
		}

		if ( $file_path ) {
			$file_path = $this->normalize_src( $file_path );
		}

		if ( $file_path && file_exists( $file_path ) ) {
			$size = (int) filesize( $file_path );
			$this->file_size_cache[ $normalized_src ] = $size;
			return $size;
		}

		$this->file_size_cache[ $normalized_src ] = null;
		return null;
	}

	private function format_bytes( $bytes ) {
		if ( $bytes === null ) {
			return '';
		}
		$bytes = (int) $bytes;
		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		}
		if ( $bytes < 1024 * 1024 ) {
			return round( $bytes / 1024, 1 ) . ' KB';
		}
		return round( $bytes / ( 1024 * 1024 ), 1 ) . ' MB';
	}
}
