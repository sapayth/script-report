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

	const GET_PARAM   = 'script_reports';
	const NONCE_ACTION = 'script_report_view';

	/**
	 * Registration source per handle: script_handle => label, style_handle => label.
	 *
	 * @var array
	 */
	private $script_sources = array();
	private $style_sources  = array();

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
		$this->style_sources[ $handle ] = $this->get_registration_source_from_backtrace();
	}

	/**
	 * Determine plugin/theme or core label from the current call stack.
	 *
	 * @return string
	 */
	private function get_registration_source_from_backtrace() {
		$trace      = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 );
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
			$normalized = preg_replace( '/\?.*$/', '', $item->src );
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

		$view = isset( $_GET['view'] ) && $_GET['view'] === 'tree' ? 'tree' : 'list';
		$base_url = remove_query_arg( array( 'view' ) );
		$list_url = add_query_arg( 'view', 'list', $base_url );
		$tree_url = add_query_arg( 'view', 'tree', $base_url );

		global $wp_scripts, $wp_styles, $wp_script_modules;

		$report_css_url = plugin_dir_url( SCRIPT_REPORT_FILE ) . 'assets/report.css';
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title>Script &amp; Style Dependencies</title>
			<link rel="stylesheet" href="<?php echo esc_url( $report_css_url ); ?>?v=<?php echo esc_attr( SCRIPT_REPORT_VERSION ); ?>">
		</head>
		<body>
			<h1>WordPress Script &amp; Style Dependencies Audit</h1>
			<div class="stats">
				<div class="stats-item"><strong>Time:</strong> <?php echo esc_html( current_time( 'Y-m-d H:i:s' ) ); ?></div>
			</div>

			<?php if ( $wp_scripts ) : ?>
				<div class="section">
					<h2>JavaScript Dependencies</h2>
					<?php $this->render_section_toolbar( $view, $list_url, $tree_url ); ?>
					<?php
					$needed_scripts = array();
					foreach ( $wp_scripts->queue as $handle ) {
						$this->collect_needed( $handle, $wp_scripts->registered, 'deps', $needed_scripts );
					}
					$total_size = $this->sum_size( $wp_scripts->registered, array_keys( $needed_scripts ) );
					$print_order_scripts = $this->get_print_order( $wp_scripts );
					$this->render_script_stats( $wp_scripts, count( $needed_scripts ), $total_size );
					if ( $view === 'tree' ) {
						$this->render_script_tree( $wp_scripts );
					} else {
						$this->render_script_list( $wp_scripts, $print_order_scripts );
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( $wp_styles ) : ?>
				<div class="section">
					<h2>CSS Dependencies</h2>
					<?php $this->render_section_toolbar( $view, $list_url, $tree_url ); ?>
					<?php
					$needed_styles = array();
					foreach ( $wp_styles->queue as $handle ) {
						$this->collect_needed( $handle, $wp_styles->registered, 'deps', $needed_styles );
					}
					$total_size = $this->sum_size( $wp_styles->registered, array_keys( $needed_styles ) );
					$print_order_styles = $this->get_print_order( $wp_styles );
					$this->render_style_stats( $wp_styles, count( $needed_styles ), $total_size );
					if ( $view === 'tree' ) {
						$this->render_style_tree( $wp_styles );
					} else {
						$this->render_style_list( $wp_styles, $print_order_styles );
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( $wp_script_modules && method_exists( $wp_script_modules, 'get_enqueued' ) ) : ?>
				<div class="section">
					<h2>Script Module Dependencies</h2>
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
		echo '<a href="' . esc_url( $list_url ) . '" class="' . ( $current_view === 'list' ? 'active' : '' ) . '">List</a>';
		echo '<a href="' . esc_url( $tree_url ) . '" class="' . ( $current_view === 'tree' ? 'active' : '' ) . '">Tree</a>';
		echo '<input type="text" class="filter" placeholder="Filter by handle or src..." aria-label="Filter">';
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

	private function render_script_stats( $wp_scripts, $needed_count, $total_size ) {
		$registered = count( $wp_scripts->registered );
		$enqueued   = count( $wp_scripts->queue );
		echo '<div class="stats">';
		echo '<div class="stats-item"><strong>Total Registered:</strong> ' . (int) $registered . ' <span class="meta">(all scripts WordPress knows about)</span></div>';
		echo '<div class="stats-item"><strong>Directly Enqueued:</strong> ' . (int) $enqueued . ' <span class="meta">(explicitly requested by plugins/themes)</span></div>';
		echo '<div class="stats-item"><strong>Total Scripts Needed:</strong> ' . (int) $needed_count . ' <span class="meta">(enqueued + all their dependencies)</span></div>';
		if ( $total_size > 0 ) {
			echo '<div class="stats-item"><strong>Total Size:</strong> ' . esc_html( $this->format_bytes( $total_size ) ) . ' <span class="meta">(uncompressed file size)</span></div>';
		}
		echo '</div>';
	}

	private function render_style_stats( $wp_styles, $needed_count, $total_size ) {
		$registered = count( $wp_styles->registered );
		$enqueued   = count( $wp_styles->queue );
		echo '<div class="stats">';
		echo '<div class="stats-item"><strong>Total Registered:</strong> ' . (int) $registered . ' <span class="meta">(all styles WordPress knows about)</span></div>';
		echo '<div class="stats-item"><strong>Directly Enqueued:</strong> ' . (int) $enqueued . ' <span class="meta">(explicitly requested by plugins/themes)</span></div>';
		echo '<div class="stats-item"><strong>Total Styles Needed:</strong> ' . (int) $needed_count . ' <span class="meta">(enqueued + all their dependencies)</span></div>';
		if ( $total_size > 0 ) {
			echo '<div class="stats-item"><strong>Total Size:</strong> ' . esc_html( $this->format_bytes( $total_size ) ) . ' <span class="meta">(uncompressed file size)</span></div>';
		}
		echo '</div>';
	}

	private function render_script_tree( $wp_scripts ) {
		echo '<h3>Enqueued Scripts (with dependencies)</h3>';
		echo '<div class="tree-view">';
		if ( empty( $wp_scripts->queue ) ) {
			echo '<p>No scripts enqueued yet.</p>';
		} else {
			foreach ( $wp_scripts->queue as $handle ) {
				$this->render_script_node( $handle, $wp_scripts, 0, array() );
			}
		}
		echo '</div>';
	}

	private function render_script_node( $handle, $wp_scripts, $depth, $visited ) {
		$indent_class = 'indent-' . min( $depth, 5 );

		if ( in_array( $handle, $visited, true ) ) {
			echo '<div class="node ' . esc_attr( $indent_class ) . '"><span class="handle">' . esc_html( $handle ) . '</span><span class="badge badge-circular">CIRCULAR</span></div>';
			return;
		}

		if ( ! isset( $wp_scripts->registered[ $handle ] ) ) {
			echo '<div class="node ' . esc_attr( $indent_class ) . '"><span class="handle">' . esc_html( $handle ) . '</span><span class="badge badge-missing">MISSING</span></div>';
			return;
		}

		$script   = $wp_scripts->registered[ $handle ];
		$visited[] = $handle;

		echo '<div class="node ' . esc_attr( $indent_class ) . '">';
		echo '<span class="handle">' . esc_html( $handle ) . '</span>';
		if ( in_array( $handle, $wp_scripts->queue, true ) ) {
			echo '<span class="badge badge-enqueued">ENQUEUED</span>';
		}
		if ( ! empty( $script->extra['group'] ) ) {
			echo '<span class="badge badge-footer">FOOTER</span>';
		}
		$this->render_inline_badge( $script );
		if ( ! empty( $script->src ) ) {
			echo '<div class="src">↳ ' . esc_html( $script->src );
			if ( ! empty( $script->ver ) ) {
				echo '<span class="meta"> (v' . esc_html( $script->ver ) . ')</span>';
			}
			echo '</div>';
		}
		if ( isset( $this->script_sources[ $handle ] ) ) {
			echo '<div class="src meta">Registered by: ' . esc_html( $this->script_sources[ $handle ] ) . '</div>';
		}
		echo '</div>';

		if ( ! empty( $script->deps ) ) {
			foreach ( $script->deps as $dep ) {
				$this->render_script_node( $dep, $wp_scripts, $depth + 1, $visited );
			}
		}
	}

	private function render_style_tree( $wp_styles ) {
		echo '<h3>Enqueued Styles (with dependencies)</h3>';
		echo '<div class="tree-view">';
		if ( empty( $wp_styles->queue ) ) {
			echo '<p>No styles enqueued yet.</p>';
		} else {
			foreach ( $wp_styles->queue as $handle ) {
				$this->render_style_node( $handle, $wp_styles, 0, array() );
			}
		}
		echo '</div>';
	}

	private function render_style_node( $handle, $wp_styles, $depth, $visited ) {
		$indent_class = 'indent-' . min( $depth, 5 );

		if ( in_array( $handle, $visited, true ) ) {
			echo '<div class="node ' . esc_attr( $indent_class ) . '"><span class="handle">' . esc_html( $handle ) . '</span><span class="badge badge-circular">CIRCULAR</span></div>';
			return;
		}

		if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
			echo '<div class="node ' . esc_attr( $indent_class ) . '"><span class="handle">' . esc_html( $handle ) . '</span><span class="badge badge-missing">MISSING</span></div>';
			return;
		}

		$style    = $wp_styles->registered[ $handle ];
		$visited[] = $handle;

		echo '<div class="node ' . esc_attr( $indent_class ) . '">';
		echo '<span class="handle">' . esc_html( $handle ) . '</span>';
		if ( in_array( $handle, $wp_styles->queue, true ) ) {
			echo '<span class="badge badge-enqueued">ENQUEUED</span>';
		}
		if ( ! empty( $style->src ) ) {
			echo '<div class="src">↳ ' . esc_html( $style->src );
			if ( ! empty( $style->ver ) ) {
				echo '<span class="meta"> (v' . esc_html( $style->ver ) . ')</span>';
			}
			echo '</div>';
		}
		if ( isset( $this->style_sources[ $handle ] ) ) {
			echo '<div class="src meta">Registered by: ' . esc_html( $this->style_sources[ $handle ] ) . '</div>';
		}
		echo '</div>';

		if ( ! empty( $style->deps ) ) {
			foreach ( $style->deps as $dep ) {
				$this->render_style_node( $dep, $wp_styles, $depth + 1, $visited );
			}
		}
	}

	private function render_script_list( $wp_scripts, $print_order ) {
		$dependents   = $this->build_dependents( $wp_scripts->registered, 'deps' );
		$duplicate_src = $this->build_duplicate_src_map( $wp_scripts->registered );
		$needed       = array();
		foreach ( $wp_scripts->queue as $handle ) {
			$this->collect_needed( $handle, $wp_scripts->registered, 'deps', $needed );
		}
		$all_handles = array_keys( $needed );
		sort( $all_handles );
		$order_map = array_flip( $print_order );

		echo '<div class="list-view">';
		foreach ( $all_handles as $handle ) {
			if ( ! isset( $wp_scripts->registered[ $handle ] ) ) {
				continue;
			}
			$script      = $wp_scripts->registered[ $handle ];
			$is_enqueued = in_array( $handle, $wp_scripts->queue, true );
			$order_pos   = isset( $order_map[ $handle ] ) ? $order_map[ $handle ] + 1 : null;

			echo '<div class="list-item">';
			echo '<div class="list-item-main">';
			echo '<span class="handle">' . esc_html( $handle ) . '</span>';
			if ( $order_pos !== null ) {
				echo '<span class="order-badge">#' . (int) $order_pos . '</span>';
			}
			$file_size = $this->get_file_size( $script->src );
			if ( $file_size !== null ) {
				echo '<span class="size-badge">' . esc_html( $this->format_bytes( $file_size ) ) . '</span>';
			}
			if ( $is_enqueued ) {
				echo '<span class="badge badge-enqueued">ENQUEUED</span>';
			}
			if ( ! empty( $script->extra['group'] ) ) {
				echo '<span class="badge badge-footer">FOOTER</span>';
			}
			$this->render_inline_badge( $script );
			if ( isset( $duplicate_src[ preg_replace( '/\?.*$/', '', $script->src ) ] ) ) {
				echo '<span class="badge badge-duplicate">DUPLICATE SRC</span>';
			}
			echo '</div>';
			echo '<div class="list-item-meta">';
			if ( isset( $this->script_sources[ $handle ] ) ) {
				echo '<div class="meta-item"><span class="meta-label">Registered by:</span> <span class="script-list">' . esc_html( $this->script_sources[ $handle ] ) . '</span></div>';
			}
			if ( ! empty( $script->src ) ) {
				$norm = preg_replace( '/\?.*$/', '', $script->src );
				if ( isset( $duplicate_src[ $norm ] ) ) {
					$others = array_diff( $duplicate_src[ $norm ], array( $handle ) );
					if ( ! empty( $others ) ) {
						echo '<div class="meta-item"><span class="meta-label">Same src as:</span> <span class="script-list">' . esc_html( implode( ', ', $others ) ) . '</span></div>';
					}
				}
			}
			if ( ! $is_enqueued ) {
				$visited = array();
				$enqueued_by = $this->find_enqueued_parents( $handle, $wp_scripts->queue, $dependents, $visited );
				if ( ! empty( $enqueued_by ) ) {
					echo '<div class="meta-item"><span class="meta-label">Enqueued by:</span> <span class="script-list">' . esc_html( implode( ', ', $enqueued_by ) ) . '</span></div>';
				}
			}
			if ( isset( $dependents[ $handle ] ) ) {
				$needed_deps = array_intersect( $dependents[ $handle ], $all_handles );
				if ( ! empty( $needed_deps ) ) {
					echo '<div class="meta-item"><span class="meta-label">Required by:</span> <span class="script-list">' . esc_html( implode( ', ', $needed_deps ) ) . '</span></div>';
				}
			}
			echo '</div></div>';
		}
		echo '</div>';
	}

	private function render_style_list( $wp_styles, $print_order ) {
		$dependents    = $this->build_dependents( $wp_styles->registered, 'deps' );
		$duplicate_src = $this->build_duplicate_src_map( $wp_styles->registered );
		$needed        = array();
		foreach ( $wp_styles->queue as $handle ) {
			$this->collect_needed( $handle, $wp_styles->registered, 'deps', $needed );
		}
		$all_handles = array_keys( $needed );
		sort( $all_handles );
		$order_map = array_flip( $print_order );

		echo '<div class="list-view">';
		foreach ( $all_handles as $handle ) {
			if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
				continue;
			}
			$style       = $wp_styles->registered[ $handle ];
			$is_enqueued = in_array( $handle, $wp_styles->queue, true );
			$order_pos   = isset( $order_map[ $handle ] ) ? $order_map[ $handle ] + 1 : null;

			echo '<div class="list-item">';
			echo '<div class="list-item-main">';
			echo '<span class="handle">' . esc_html( $handle ) . '</span>';
			if ( $order_pos !== null ) {
				echo '<span class="order-badge">#' . (int) $order_pos . '</span>';
			}
			$file_size = $this->get_file_size( $style->src );
			if ( $file_size !== null ) {
				echo '<span class="size-badge">' . esc_html( $this->format_bytes( $file_size ) ) . '</span>';
			}
			if ( $is_enqueued ) {
				echo '<span class="badge badge-enqueued">ENQUEUED</span>';
			}
			if ( isset( $duplicate_src[ preg_replace( '/\?.*$/', '', $style->src ) ] ) ) {
				echo '<span class="badge badge-duplicate">DUPLICATE SRC</span>';
			}
			echo '</div>';
			echo '<div class="list-item-meta">';
			if ( isset( $this->style_sources[ $handle ] ) ) {
				echo '<div class="meta-item"><span class="meta-label">Registered by:</span> <span class="script-list">' . esc_html( $this->style_sources[ $handle ] ) . '</span></div>';
			}
			if ( ! empty( $style->src ) ) {
				$norm = preg_replace( '/\?.*$/', '', $style->src );
				if ( isset( $duplicate_src[ $norm ] ) ) {
					$others = array_diff( $duplicate_src[ $norm ], array( $handle ) );
					if ( ! empty( $others ) ) {
						echo '<div class="meta-item"><span class="meta-label">Same src as:</span> <span class="script-list">' . esc_html( implode( ', ', $others ) ) . '</span></div>';
					}
				}
			}
			if ( ! $is_enqueued ) {
				$visited = array();
				$enqueued_by = $this->find_enqueued_parents( $handle, $wp_styles->queue, $dependents, $visited );
				if ( ! empty( $enqueued_by ) ) {
					echo '<div class="meta-item"><span class="meta-label">Enqueued by:</span> <span class="script-list">' . esc_html( implode( ', ', $enqueued_by ) ) . '</span></div>';
				}
			}
			if ( isset( $dependents[ $handle ] ) ) {
				$needed_deps = array_intersect( $dependents[ $handle ], $all_handles );
				if ( ! empty( $needed_deps ) ) {
					echo '<div class="meta-item"><span class="meta-label">Required by:</span> <span class="script-list">' . esc_html( implode( ', ', $needed_deps ) ) . '</span></div>';
				}
			}
			echo '</div></div>';
		}
		echo '</div>';
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
		echo '<span class="badge badge-inline">INLINE ' . esc_html( $this->format_bytes( $len ) ) . '</span>';
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
			echo '<p>Unable to retrieve script module information.</p>';
			return;
		}

		echo '<div class="stats">';
		echo '<div class="stats-item"><strong>Total Registered:</strong> ' . count( $registered ) . ' <span class="meta">(all script modules WordPress knows about)</span></div>';
		echo '<div class="stats-item"><strong>Enqueued:</strong> ' . count( $enqueued ) . ' <span class="meta">(modules marked for loading)</span></div>';
		echo '</div>';
		echo '<div class="list-view">';

		if ( empty( $registered ) ) {
			echo '<p>No script modules registered.</p>';
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
			echo '<span class="badge badge-module">MODULE</span>';
			echo '</div>';
			echo '<div class="list-item-meta">';
			if ( ! empty( $deps ) ) {
				echo '<div class="meta-item"><span class="meta-label">Depends on:</span> <span class="script-list">' . esc_html( implode( ', ', $deps ) ) . '</span></div>';
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
				$parsed = parse_url( $src );
				if ( ! empty( $parsed['path'] ) ) {
					$file_path = wp_normalize_path( ABSPATH . ltrim( $parsed['path'], '/' ) );
				}
			}
		}

		if ( $file_path ) {
			$file_path = preg_replace( '/\?.*$/', '', $file_path );
		}

		if ( $file_path && file_exists( $file_path ) ) {
			return (int) filesize( $file_path );
		}

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
