<?php
/*
 * Plugin Name:       SG Multiple Addresses for WordPress
 * Plugin URI:        https://savana-soft.com
 * Description:       SG Multiple Addresses for WordPress
 * Version:           1
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            V.Dafinov
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sgma
 * Domain Path:       /languages
 */
namespace SGMA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SGMA{
	public function __construct(){
		add_action('plugins_loaded', [$this, 'init']);
	}
	
	public function init() {
        $this->include_files();
        $this->init_classes();
    }
	
	private function include_files() {
		require_once WP_PLUGIN_DIR  . '/sg-multiple-addresses/includes/sgma-admin.php';
		require_once WP_PLUGIN_DIR  . '/sg-multiple-addresses/includes/Utils.php';
	}
	
	private function init_classes() {

	    new SGMA_Admin();
		//new ARFW_Frontend();
	}
}

new SGMA();
