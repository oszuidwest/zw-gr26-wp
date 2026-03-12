<?php
/**
 * Plugin Name: ZuidWest GR 2026
 * Description: Gemeenteraadsverkiezingen 2026 — shortcodes voor de verkiezingspagina op ZuidWest Update.
 * Version: 0.0.11
 * Author: Streekomroep ZuidWest
 * Text Domain: zw-gr26
 * License: GPL-2.0-or-later
 *
 * @package ZWGR26
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zwgr26_plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
define( 'ZWGR26_VERSION', $zwgr26_plugin_data['Version'] );
define( 'ZWGR26_PATH', plugin_dir_path( __FILE__ ) );
define( 'ZWGR26_URL', plugin_dir_url( __FILE__ ) );

require_once ZWGR26_PATH . 'includes/class-assets.php';
require_once ZWGR26_PATH . 'includes/class-icons.php';
require_once ZWGR26_PATH . 'includes/class-image-proxy.php';
require_once ZWGR26_PATH . 'includes/class-renderer.php';
require_once ZWGR26_PATH . 'includes/class-bunny-api.php';
require_once ZWGR26_PATH . 'includes/class-data-provider.php';
require_once ZWGR26_PATH . 'includes/class-post-type-uitslag.php';
require_once ZWGR26_PATH . 'includes/class-schema.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-pagina.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-livestream.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-debatten.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-explainers.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-nieuws.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-podcast.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-programmas.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-resultaten.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-stemlocaties.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-tekst.php';
require_once ZWGR26_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, [ 'ZWGR26\Post_Type_Uitslag', 'activate' ] );

ZWGR26\Plugin::instance();
