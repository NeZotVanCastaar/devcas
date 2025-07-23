<?php
/**
 * Plugin Name: Devcas
 * Description: Een verzameling handige SEO-tools, bulk editors en custom functionaliteit.
 * Version: 1.0
 * Author: Alec Meganck
 */

defined('ABSPATH') || exit;

// Laad alle submodules uit de /modules folder
foreach (glob(plugin_dir_path(__FILE__) . 'modules/*.php') as $module) {
    include_once $module;
}