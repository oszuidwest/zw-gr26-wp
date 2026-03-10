<?php
/**
 * Plugin Name: ZuidWest GR 2026
 * Description: Gemeenteraadsverkiezingen 2026 — shortcodes voor de verkiezingspagina op ZuidWest Update.
 * Version: 1.2.0
 * Author: ZuidWest Update
 * Text Domain: zw-gr26
 * License: GPL-2.0-or-later
 *
 * @package ZWGR26
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZWGR26_VERSION', '1.3.0' );
define( 'ZWGR26_PATH', plugin_dir_path( __FILE__ ) );
define( 'ZWGR26_URL', plugin_dir_url( __FILE__ ) );

require_once ZWGR26_PATH . 'includes/class-assets.php';
require_once ZWGR26_PATH . 'includes/class-renderer.php';
require_once ZWGR26_PATH . 'includes/class-bunny-api.php';
require_once ZWGR26_PATH . 'includes/class-data-provider.php';
require_once ZWGR26_PATH . 'includes/class-post-type-uitslag.php';
require_once ZWGR26_PATH . 'includes/class-admin-import.php';
require_once ZWGR26_PATH . 'includes/class-schema.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-pagina.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-livestream.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-debatten.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-explainers.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-nieuws.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-programmas.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-resultaten.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-stemlocaties.php';
require_once ZWGR26_PATH . 'shortcodes/class-shortcode-tekst.php';
require_once ZWGR26_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, [ 'ZWGR26\Post_Type_Uitslag', 'activate' ] );

ZWGR26\Plugin::instance();
ZWGR26\Admin_Import::register();
