<?php if ( ! defined( 'ABSPATH' ) ) exit; 

class FooSales_Woo_Helper {
	
    public  $Config;
 
    public function __construct($config) {  
        
        $this->Config = $config;
        add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ) );

    }
    
    /**
     * Adds a settings tab to WooCommerce
     * 
     * @param array $settings_tabs
     */
    public function add_settings_tab($settings_tabs) {

        $settings_tabs['settings_foosales'] = __( 'FooSales', 'foosales' );
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
        
        //see foosales.php
        
    }
    
        
}        