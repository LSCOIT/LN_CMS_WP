<?php

/**
 * Plugin Name:     Legal Navigator
 * Text Domain:     legal-navigator
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Legal_Navigator
 */

use LSC\Includes\LSC;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define WPM_PLUGIN_FILE.
if (!defined('LSC_PLUGIN_FILE')) {
    define('LSC_PLUGIN_FILE', __FILE__);
}

// Include the autoloader so we can dynamically include the rest of the classes.
require_once trailingslashit(dirname(__FILE__)) . 'lib/autoloader.php';

function lsc()
{
    return LSC::instance();
}

lsc();
