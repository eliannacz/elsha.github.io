<?php if ( ! defined( 'ABSPATH' ) ) exit; 

class FooSales_Config {
	
    public $pluginVersion;
    public $pluginDirectory;
    public $path;
    public $classPath;
    public $templatePath;
    public $templatePathTheme;
    public $scriptsPath;
    public $stylesPath;
    public $pluginURL;
	
        /**
         * Initialize configuration variables to be used as object.
         * 
         */
	public function __construct() {

            $this->pluginVersion = '1.8.0';
            $this->pluginDirectory = plugin_basename(__DIR__);
            $this->path = plugin_dir_path( __FILE__ );
            $this->pluginURL = plugin_dir_url(__FILE__);
            $this->classPath = plugin_dir_path( __FILE__ ).'classes/';
            $this->templatePath = plugin_dir_path( __FILE__ ).'templates/';
            $this->templatePathTheme = get_stylesheet_directory().'/'.$this->pluginDirectory.'/templates/';
            $this->scriptsPath = plugin_dir_url(__FILE__) .'js/';
            $this->stylesPath = plugin_dir_url(__FILE__) .'css/';
                
	}

} 