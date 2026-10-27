<?php
/**
 * Plugin Name: Tech Akhil SEO Audit Tool
 * Description: Lightweight on-page SEO auditing with focus keyword analysis and free SEO tool links.
 * Version: 1.9.14
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Akhilesh Kumar Meena
 * Author URI: https://techakhil.com/akhilesh-kumar-meena/
 * Plugin URI: https://techakhil.com/free-seo-audit-tool/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tech-akhil-seo-audit-tool
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'TA_SACT_VERSION', '1.9.14' );
define( 'TA_SACT_PATH', plugin_dir_path( __FILE__ ) );
define( 'TA_SACT_URL', plugin_dir_url( __FILE__ ) );
require_once TA_SACT_PATH . 'includes/class-sact-crawler.php';
require_once TA_SACT_PATH . 'includes/class-sact-admin.php';
require_once TA_SACT_PATH . 'includes/class-sact-gsc.php';
require_once TA_SACT_PATH . 'includes/class-sact-history.php';
add_action( 'plugins_loaded', static function () { TA_SACT_Admin::init(); TA_SACT_GSC::init(); TA_SACT_History::init(); } );

/**
 * Add website, documentation, and review links to the plugin metadata row,
 * not the action links row (which should contain Deactivate and Settings).
 *
 * @param string[] $plugin_meta Existing plugin metadata links.
 * @param string   $plugin_file Plugin file path relative to the plugins directory.
 * @param array    $plugin_data Plugin data from the plugin header.
 * @param string   $status      Plugin status filter currently being viewed.
 * @return string[]
 */
function ta_sact_plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
    if ( plugin_basename( __FILE__ ) !== $plugin_file ) {
        return $plugin_meta;
    }

    $plugin_meta[] = '<a href="https://techakhil.com/">Tech Akhil</a>';
    $plugin_meta[] = '<a href="https://techakhil.com/free-seo-audit-tool/">Documentation</a>';
    $plugin_meta[] = '<a href="https://wordpress.org/support/plugin/tech-akhil-seo-audit-tool/reviews/#new-post">Rate plugin ★★★★★</a>';

    return $plugin_meta;
}
add_filter( 'plugin_row_meta', 'ta_sact_plugin_row_meta', 10, 4 );
