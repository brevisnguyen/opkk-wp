<?php
/**
 * Plugin Name: OPKK WP
 * Description: OPKK WP — CMS xem phim cho WordPress (tương thích dữ liệu oPhim).
 * Version: 1.0.0
 * Author: OPhimCC
 * Author URI: https://ophim.cc
 */
if ( ! defined( 'WPINC' ) ) {
    die;
}
require_once 'define.php';

require_once __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
$ofimUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    OFIM_GITHUB_REPO,
    __FILE__,
    OFIM_PLUGIN_SLUG
);
$ofimUpdateChecker->getVcsApi()->enableReleaseAssets('/\.zip($|[?&#])/i');

require_once OFIM_HELPERS_PATCH.'/cache.php';
require_once OFIM_HELPERS_PATCH.'/functions.php';
require_once OFIM_INCLUDE_PATCH.'/Controller.php';
require_once OFIM_INCLUDE_PATCH.'/Permalink.php';
require_once OFIM_INCLUDE_PATCH.'/Tax.php';
require_once OFIM_INCLUDE_PATCH.'/Shortcuts.php';
require_once OFIM_INCLUDE_PATCH.'/Ajax.php';
require_once 'crawl_movies.php';
require_once 'crawl_kk_movies.php';

global $oController;
$oController = new oController();

if (is_admin()){
    require_once 'backend.php';
    new oFim_Backend();
}else{
    require_once OFIM_INCLUDE_PATCH.'/Episode.php';
}
