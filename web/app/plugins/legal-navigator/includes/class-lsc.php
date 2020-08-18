<?php

namespace LSC\Includes;

if (!defined('ABSPATH')) {
    die('-1');
}

class LSC
{

    public $version = '0.1.0';

    protected static $_instance = null;

    public static function instance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Cloning is forbidden.
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'legal-navigator'), '1.0');
    }

    /**
     * Unserializing instances of this class is forbidden.
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'legal-navigator'), '1.0');
    }


    /**
     * Legal Navigator Constructor.
     */
    public function __construct()
    {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks()
    {
        add_action('init', array($this, 'init'), 0);
    }

    /**
     * Init Legal Navigator when WordPress Initialises.
     */
    public function init()
    {

        // Set up localisation.
        // $this->load_plugin_textdomain();

        new LSC_Assets();
        LSC_AJAX::init();


        if ($this->is_request('admin')) {
            new LSC_Curated_Experiences();
        }
    }

    /**
     * Include required core files used in admin and on the frontend.
     */
    public function includes()
    {
        /**
         * Core classes.
         */
        new LSC_Setup();
        new LSC_Resources();
        new LSC_Options();
        new LSC_Topics();

        include_once LSC_ABSPATH . 'includes/lsc-core-functions.php';
    }

    /**
     * Define constant if not already set.
     *
     * @param  string      $name
     * @param  string|bool $value
     */
    private function define($name, $value)
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    private function define_constants()
    {
        $this->define('LSC_ABSPATH', dirname(LSC_PLUGIN_FILE) . '/');
        $this->define('LSC_PLUGIN_BASENAME', plugin_basename(LSC_PLUGIN_FILE));
        $this->define('LSC_VERSION', $this->version);
    }

    /**
     * What type of request is this?
     *
     * @param  string $type admin, ajax, cron or frontend.
     *
     * @return bool
     */
    private function is_request($type)
    {
        switch ($type) {
            case 'admin':
                return is_admin();
            case 'ajax':
                return wp_doing_ajax();
            case 'cron':
                return defined('DOING_CRON');
            case 'frontend':
                return (!is_admin() || wp_doing_ajax()) && !defined('DOING_CRON');
            default:
                return false;
        }
    }

    /**
     * Load Localisation files.
     *
     * Note: the first-loaded translation file overrides any following ones if the same translation is present.
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain('legal-navigator', false, plugin_basename(dirname(LSC_PLUGIN_FILE)) . '/languages');
    }

    /**
     * Get the plugin url.
     * @return string
     */
    public function plugin_url()
    {
        return untrailingslashit(plugins_url('/', LSC_PLUGIN_FILE));
    }

    /**
     * Get the plugin path.
     * @return string
     */
    public function plugin_path()
    {
        return untrailingslashit(plugin_dir_path(LSC_PLUGIN_FILE));
    }

    /**
     * Get Ajax URL.
     * @return string
     */
    public function ajax_url()
    {
        return admin_url('admin-ajax.php', 'relative');
    }
}
