<?php

/**
 * Plugin Name: Connect Brevo With Gravity Forms
 * Plugin URI: https://pluginscafe.com/plugin/brevo-for-gravity-forms
 * Description: Automatically send data to Brevo with every Gravity Forms submission.
 * Author: Pluginscafe
 * Author URI: https://pluginscafe.com
 * Version: 1.0.0
 * Text Domain: connect-brevo-gravity-forms
 * Domain Path: /languages/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */


if (! defined('ABSPATH')) {
	exit;
}

if (! defined('PCAFE_GFBR_VERSION_FREE')) {
	define('PCAFE_GFBR_VERSION_FREE', '1.0.0');
}

if (! class_exists('GFForms') || ! pcafe_gfbr_meets_requirements()) {
	add_action('admin_notices', 'pcafe_gfbr_notice_for_missing_requirements');
	return;
}

class pcafe_gfbr_Bootstrap {

	public static function load() {
		if (! method_exists('GFForms', 'include_addon_framework')) {
			return;
		}

		require_once 'class-brevo-feed.php';
		require_once 'includes/class-brevo-api.php';

		GFAddOn::register('PCAFE_GFBR_Brevo_Free');
	}
}

add_action('gform_loaded', array('pcafe_gfbr_Bootstrap', 'load'), 5);

function pcafe_gfbr_brevo() {
	return PCAFE_GFBR_Brevo_Free::get_instance();
}

function pcafe_gfbr_localization_setup() {
	load_plugin_textdomain('connect-brevo-gravity-forms', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('init', 'pcafe_gfbr_localization_setup');

function pcafe_gfbr_meets_requirements() {
	global $wp_version;

	return (
		version_compare(PHP_VERSION, '7.3', '>=') &&
		version_compare($wp_version, '5.5', '>=')
	);
}

function pcafe_gfbr_notice_for_missing_requirements() {
	printf(
		'<div class="notice notice-error"><p>%1$s</p></div>',
		esc_html__('Brevo For Gravity Forms" requires Gravity Forms to be installed and activated.', 'connect-brevo-gravity-forms')
	);
}
