<?php


//URL
define('OFIM_PLUGIN_URL',    plugin_dir_url(__FILE__));
define('OFIM_PUBLIC_URL',    OFIM_PLUGIN_URL.'public');
define('OFIM_CSS_URL',       OFIM_PUBLIC_URL.'/css');
define('OFIM_IMAGE_URL',     OFIM_PUBLIC_URL.'/image');
define('OFIM_JS_URL',        OFIM_PUBLIC_URL.'/js');

//PATCH
define('DS'                 ,DIRECTORY_SEPARATOR);
define('OFIM_PLUGIN_PATCH'  ,plugin_dir_path(__FILE__));
define('OFIM_CONFIG_PATCH'  ,OFIM_PLUGIN_PATCH.'configs');
define('OFIM_CONTROLLERS_PATCH'  ,OFIM_PLUGIN_PATCH.'controllers');
define('OFIM_HELPERS_PATCH'  ,OFIM_PLUGIN_PATCH.'helpers');
define('OFIM_INCLUDE_PATCH'  ,OFIM_PLUGIN_PATCH.'includes');
define('OFIM_MODELS_PATCH'  ,OFIM_PLUGIN_PATCH.'models');
define('OFIM_TEMPLADE_PATCH'  ,OFIM_PLUGIN_PATCH.'template');
define('OFIM_VALIDATES_PATCH'  ,OFIM_PLUGIN_PATCH.'validates');

//OTHER
define('OFIM_CACHE_TIME', 172800);
define('OFIM_CACHE_DIR', OFIM_PLUGIN_PATCH . 'cache/');

define('OFIM_PREFIX'  ,'OFIM_');

// Version / update (PUC đọc Version: từ header plugin, không đọc OFIM_VERSION)
define('OFIM_VERSION', '1.0.6');
define('OFIM_PLUGIN_SLUG', 'opkk-wp');
define('OFIM_GITHUB_REPO', 'https://github.com/brevisnguyen/opkk-wp/');

//OPHIM
define('API_DOMAIN', 'https://ophim1.com');
define('CRAWL_OPHIM_OPTION_SETTINGS', 'crawl_ophim_schedule_settings');
define('CRAWL_OPHIM_OPTION_RUNNING', 'crawl_ophim_schedule_running');
define('CRAWL_OPHIM_OPTION_SECRET_KEY', 'crawl_ophim_schedule_secret_key');

//KKPhim (phimapi.com)
define('API_DOMAIN_KK', 'https://phimapi.com');
define('CRAWL_KK_OPTION_SETTINGS', 'crawl_kk_schedule_settings');
define('CRAWL_KK_OPTION_RUNNING', 'crawl_kk_schedule_running');
define('CRAWL_KK_OPTION_SECRET_KEY', 'crawl_kk_schedule_secret_key');
define('CRAWL_KK_PATH_SCHEDULE_JSON', plugin_dir_path(__FILE__) . 'schedule_kk.json');

define('SCHEDULE_CRAWLER_TYPE_NOTHING', 0);
define('SCHEDULE_CRAWLER_TYPE_INSERT', 1);
define('SCHEDULE_CRAWLER_TYPE_UPDATE', 2);
define('SCHEDULE_CRAWLER_TYPE_ERROR', 3);
define('SCHEDULE_CRAWLER_TYPE_FILTER', 4);

define('CRAWL_OPHIM_PATH', plugin_dir_path(__FILE__));
define('CRAWL_OPHIM_PATH_SCHEDULE_JSON', CRAWL_OPHIM_PATH . 'schedule.json');