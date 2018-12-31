<?php if ( ! defined( 'ABSPATH' ) ) exit; 
/**
 * Plugin Name: FooSales
 * Description: FooSales is an app-based point of sale (POS) system for WooCommerce that turns your iOS or Android tablet into a mobile cash register. It's the simplest and fastest way to take your online store from clicks to bricks. The FooSales WordPress plugin connects your website to your FooSales Account and the FooSales iOS and Android apps.
 * Version: 1.8.0
 * Author: FooSales
 * Author URI: https://www.foosales.com/
 * Developer: FooSales
 * Developer URI: https://www.foosales.com/
 * Text Domain: foosales
 *
 * Copyright: 2009-2017 Grenade Technologies.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

//include config
require('config.php');


class FooSales {
    
    private $Config;
    private $WooHelper;
    private $XMLRPCHelper;
    
    public function __construct() {
        
        add_action( 'init', array( $this, 'plugin_init' ) );
        add_action( 'admin_notices', array( $this, 'check_woocommerce' ) );
        add_action( 'woocommerce_settings_tabs_settings_foosales', array( $this, 'add_settings_tab_settings' ) );
        add_action( 'woocommerce_update_options_settings_foosales', array( $this, 'update_settings_tab_settings' ) );
        add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ) );
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_action_links' ) );        
        add_action( 'activated_plugin', array( $this, 'foosales_activation_redirect' ) );
        
        add_action( 'admin_init', array(&$this, 'assign_admin_caps' ) );
        register_deactivation_hook( __FILE__, array( &$this, 'remove_admin_caps' ) );
    }
    
    /**
     *  Initialize plugin and helpers.
     * 
     */
    public function plugin_init() {
        
        //Main config
        $this->Config = new FooSales_Config();
        
        //WooHelper
        require_once($this->Config->classPath.'woohelper.php');
        $this->WooHelper = new FooSales_Woo_Helper($this->Config);
        
        //XMLRPCHelper
        require_once($this->Config->classPath.'xmlrpchelper.php');
        $this->XMLRPCHelper = new FooSales_XMLRPC_Helper($this->Config);
        
    }
    
    /**
     * Checks if WooCommerce is active.
     * 
     */
    public function check_woocommerce() {

        if ( !class_exists( 'WooCommerce' ) ) {

                $this->output_notices(array(__( 'WooCommerce is not active. Please install and activate it.', 'foosales' )));

        } 
        
    }
    
    /**
     * Outputs notices to screen.
     * 
     * @param array $notices
     */
    private function output_notices($notices) {

        foreach ($notices as $notice) {

                echo "<div class='updated'><p>$notice</p></div>";

        }

    }
    
    public function assign_admin_caps() {
        
        $role = get_role( 'administrator' );
        
        $role->add_cap('publish_foosales');
        
    }
    
    public function remove_admin_caps() {
        
        $delete_caps = array(
            'publish_foosales'
        );
        
        global $wp_roles;
	foreach ($delete_caps as $cap) {

            foreach (array_keys($wp_roles->roles) as $role) {

                $wp_roles->remove_cap($role, $cap);

            }
                
	}
        
    }
    
    
    /**
     * Adds a settings tab to WooCommerce
     * 
     * @param array $settings_tabs
     */
    public function add_settings_tab($settings_tabs) {

        $settings_tabs['settings_foosales'] = __( 'FooSales', 'woocommerce-settings-foosales' );
        return $settings_tabs;


    }

    /**
     * Adds the WooCommerce tab settings
     * 
     */
    public function add_settings_tab_settings() {

        woocommerce_admin_fields( $this->get_tab_settings() );

    }

    /**
     * Gets the WooCommerce tab settings
     * 
     * @return array $settings
     */
    public function get_tab_settings() {
        
        $settings = array(
            'section_start' => array(
                'type' => 'sectionstart',
                'id' => 'wc_settings_tab_foosales_section_start'
            ),
            'section_title' => array(
                'name'      => __( 'Receipts / Tax Invoices', 'foosales' ),
                'type'      => 'title', 
                'desc' => __( 'These optional details will be displayed on the receipts / tax invoices printed from within the FooSales for WooCommerce app.', 'foosales' ),
                'id'        => 'wc_settings_foosales_settings_title'
            ),
            'globalFooSalesStoreLogo' => array(
                'name' => __( 'Store logo URL', 'foosales' ),
                'type' => 'text',
                'id' => 'globalFooSalesStoreLogoURL',
                'desc' => __( 'The URL to a black on white version of your store logo.', 'foosales' ),
                'class' => 'text'
            ),
            'globalFooSalesStoreName' => array(
                'name' => __( 'Store name', 'foosales' ),
                'type' => 'text',
                'id' => 'globalFooSalesStoreName',
                'class' => 'text'
            ),
            'globalFooSalesHeaderContent' => array(
                'name' => __( 'Header content', 'foosales' ),
                'type' => 'textarea',
                'id' => 'globalFooSalesHeaderContent',
                'class' => 'text'
            ),
            'globalFooSalesFooterContent' => array(
                'name' => __( 'Footer content', 'foosales' ),
                'type' => 'textarea',
                'id' => 'globalFooSalesFooterContent',
                'class' => 'text'
            ),
            'section_end' => array(
                'type' => 'sectionend',
                'id' => 'wc_settings_tab_foosales_section_end'
            )
        );

        return $settings;
    }

    /**
     * Saves the WooCommerce tab settings
     * 
     */
    public function update_settings_tab_settings() {

        woocommerce_update_options( $this->get_tab_settings() );

    }

    /**
     * Adds settings link to plugin listing
     * 
     */
    public function add_action_links ( $links ) {
        $mylinks = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=settings_foosales' ) . '">Settings</a>',
        );
        return array_merge( $links, $mylinks );
    }

    public function foosales_activation_redirect( $plugin ) {
        if( $plugin == plugin_basename( __FILE__ ) ) {
            exit( wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=settings_foosales' ) ) );
        }
    }
    
}

$FooSales = new FooSales();

function foosales_check_plugin_active( $plugin ) {

    return in_array( $plugin, (array) get_option( 'active_plugins', array() ) );

}