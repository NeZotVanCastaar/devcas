<?php
/*
    Plugin Name: DevCas
    Plugin URI: https://github.com/NeZotVanCastaar/devcas
    Description: Een verzameling handige SEO-tools, bulk editors en custom functionaliteit.
    Version: 1.0.0
    Author: Alec Meganck
    Author URI: https://castaar.com
*/

defined('ABSPATH') || exit;

// 1. Laad Plugin Update Checker
require plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// 2. Stel de update checker in
$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/NeZotVanCastaar/devcas/',
    __FILE__,
    'devcas'
);

// Optioneel: Branch instellen (meestal main)
$updateChecker->setBranch('main');

// Als het een privé GitHub repo is, voeg token toe:
// $updateChecker->setAuthentication('YOUR_PERSONAL_ACCESS_TOKEN');

// 3. Laad alle submodules uit de /modules map
foreach (glob(plugin_dir_path(__FILE__) . 'modules/*.php') as $module) {
    include_once $module;
}
