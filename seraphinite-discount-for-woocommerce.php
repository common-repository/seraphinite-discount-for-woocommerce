<?php
/*
Plugin Name: Seraphinite Bulk Discounts for WooCommerce (Base)
Plugin URI: http://wordpress.org/plugins/seraphinite-discount-for-woocommerce
Description: Increase your sales by providing products bulk discounts.
Text Domain: seraphinite-discount-for-woocommerce
Domain Path: /languages
Version: 2.4.5
Author: Seraphinite Solutions
Author URI: https://www.s-sols.com
License: GPLv2 or later (if another license is not provided)
Requires PHP: 5.4
Requires at least: 4.5
WC requires at least: 3.2
WC tested up to: 8.2



 */




























if( defined( 'SERAPH_WD_VER' ) )
	return;

define( 'SERAPH_WD_VER', '2.4.5' );

include( __DIR__ . '/main.php' );

// #######################################################################

register_activation_hook( __FILE__, 'seraph_wd\\Plugin::OnActivate' );
register_deactivation_hook( __FILE__, 'seraph_wd\\Plugin::OnDeactivate' );
//register_uninstall_hook( __FILE__, 'seraph_wd\\Plugin::OnUninstall' );

// #######################################################################
// #######################################################################
