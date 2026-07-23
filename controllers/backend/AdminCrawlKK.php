<?php

class OFim_AdminCrawlKK_Controller{
    private $cache;
    public function __construct(){
        $this->cache = new oCache();
    }

    public function display(){
        $categoryFromApi = $this->cache->remember('kk_the_loai', OFIM_CACHE_TIME, function() {
            try {
                $data = file_get_contents(API_DOMAIN_KK . '/the-loai');
                $data = json_decode($data);
                if (isset($data->data->items)) {
                    return $data->data->items;
                }
                if (isset($data->items)) {
                    return $data->items;
                }
                return is_array($data) ? $data : array();
            } catch (Exception $e) {
                return array();
            }
        });

        $countryFromApi = $this->cache->remember('kk_quoc_gia', OFIM_CACHE_TIME, function() {
            try {
                $data = file_get_contents(API_DOMAIN_KK . '/quoc-gia');
                $data = json_decode($data);
                if (isset($data->data->items)) {
                    return $data->data->items;
                }
                if (isset($data->items)) {
                    return $data->items;
                }
                return is_array($data) ? $data : array();
            } catch (Exception $e) {
                return array();
            }
        });

        $schedule_log = $this->getLastLog();
        include_once(OFIM_TEMPLADE_PATCH."/backend/crawlkk.php");
    }

    public function getLastLog() {
        $log_path = WP_CONTENT_DIR  . '/crawl_kk_logs';
        $log_filename = 'log_' . date('d-m-Y') . '.log';
        $log_data = $log_path.'/'.$log_filename;
        if (file_exists($log_data)) {
            $log = file_get_contents($log_data);
        }else{
            $log = "The file $log_filename does not exist";
        }
        return array(
            'log_filename' => $log_filename,
            'log_data' => $log
        );
    }

}
