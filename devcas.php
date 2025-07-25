<?php
/*
    Plugin Name: DevCas
    Plugin URI: https://github.com/NeZotVanCastaar/devcas
    Description: Een verzameling handige SEO-tools, bulk editors en custom functionaliteit.
    Version: 1.0.1
    Author: Castaar – Alec Meganck & Robbe Cooman
    Author URI: https://castaar.com
*/

defined('ABSPATH') || exit;

// 1. Laad Plugin Update Checker
require plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// 2. Stel de update checker in (zonder token!)
$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/NeZotVanCastaar/devcas/',
    __FILE__,
    'devcas'
);

// 3. Branch instellen (meestal 'main')
$updateChecker->setBranch('main');

// 4. Submodules laden
foreach (glob(plugin_dir_path(__FILE__) . 'modules/*.php') as $module) {
    include_once $module;
}
