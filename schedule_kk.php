<?php
require_once __DIR__ . '/helpers/drop-root.php';
require_once __DIR__ . '/../../../wp-load.php';
require_once __DIR__ . '/../../../wp-admin/includes/taxonomy.php';
require_once __DIR__ . '/../../../wp-admin/includes/image.php';

set_time_limit(0);
require_once CRAWL_OPHIM_PATH . 'define.php';

if (!isset($argv[1])) return;
if ($argv[1] != get_option(CRAWL_KK_OPTION_SECRET_KEY, 'secret_key')) return;

require_once CRAWL_OPHIM_PATH . 'helpers/functions.php';
require_once CRAWL_OPHIM_PATH . 'crawl_movies.php';
require_once CRAWL_OPHIM_PATH . 'crawl_kk_movies.php';

// Get & Check Settings
$crawl_kk_settings = json_decode(get_option(CRAWL_KK_OPTION_SETTINGS, false));
if (!$crawl_kk_settings) return;

// Check enable
if (crawl_kk_get_enable() === false) {
    update_option(CRAWL_KK_OPTION_RUNNING, 0);
    return;
}
// Check running
if ((int)get_option(CRAWL_KK_OPTION_RUNNING, 0) === 1) return;

// Update Running
update_option(CRAWL_KK_OPTION_RUNNING, 1);

$countDone = 0;
$countMovies = 0;
$countStatus = array(0, 0, 0, 0, 0);

try {
    // Crawl Pages
    $pageFrom = $crawl_kk_settings->pageFrom;
    $pageTo = $crawl_kk_settings->pageTo;
    $listMovies = array();
    for ($i = $pageFrom; $i >= $pageTo; $i--) {
        if (crawl_kk_get_enable() === false) {
            update_option(CRAWL_KK_OPTION_RUNNING, 0);
            return;
        }
        $result = crawl_kk_page_handle(API_DOMAIN_KK . "/danh-sach/phim-moi-cap-nhat?page=$i");
        $result = explode("\n", $result);
        $listMovies = array_merge($listMovies, $result);
    }
    shuffle($listMovies);

    $countMovies = count($listMovies);

    crawl_kk_write_log("Start crawler {$countMovies} movies");
    // Crawl Movies
    foreach ($listMovies as $key => $data_post) {
        if (crawl_kk_get_enable() === false) {
            update_option(CRAWL_KK_OPTION_RUNNING, 0);
            crawl_kk_write_log("Force Stop => Done {$countDone}/{$countMovies} movies (Nothing Update: {$countStatus[0]} | Insert: {$countStatus[1]} | Update: {$countStatus[2]} | Error: {$countStatus[3]} | Filter: {$countStatus[4]})");
            return;
        }

        $url = explode('|', $data_post)[0];
        $ophim_id = explode('|', $data_post)[1];
        $ophim_update_time = explode('|', $data_post)[2];

        $result = crawl_kk_movies_handle($url, $ophim_id, $ophim_update_time, $crawl_kk_settings->filterType, $crawl_kk_settings->filterCategory, $crawl_kk_settings->filterCountry);
        $result = json_decode($result);
        if ($result->schedule_code == SCHEDULE_CRAWLER_TYPE_ERROR) crawl_kk_write_log(sprintf("ERROR: %s ==>>> %s", $url, $result->msg));
        $countStatus[$result->schedule_code]++;
        $countDone++;
    }

} catch (\Throwable $th) {
    crawl_kk_write_log(sprintf("ERROR: THROW ==>>> %s", $th->getMessage()));
}

// Update Running
update_option(CRAWL_KK_OPTION_RUNNING, 0);

crawl_kk_write_log("Done {$countDone}/{$countMovies} movies (Nothing Update: {$countStatus[0]} | Insert: {$countStatus[1]} | Update: {$countStatus[2]} | Error: {$countStatus[3]} | Filter: {$countStatus[4]})");

function crawl_kk_get_enable()
{
    $schedule = json_decode(file_get_contents(CRAWL_KK_PATH_SCHEDULE_JSON));
    if ($schedule && $schedule->enable) {
        return $schedule->enable;
    }
    return false;
}

function crawl_kk_write_log($log_msg, $new_line = "\n")
{
    $log_filename = WP_CONTENT_DIR . '/crawl_kk_logs';
    if (!file_exists($log_filename)) {
        mkdir($log_filename, 0777, true);
    }
    $log_file_data = $log_filename . '/log_' . date('d-m-Y') . '.log';
    file_put_contents($log_file_data, '[' . date("d-m-Y H:i:s") . '] ' . $log_msg . $new_line, FILE_APPEND);
}
