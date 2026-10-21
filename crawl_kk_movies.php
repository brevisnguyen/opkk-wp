<?php

function kk_swap_thumb_poster(&$sourcePage)
{
    if (!isset($sourcePage['movie'])) return;
    $movie = &$sourcePage['movie'];
    $thumb = $movie['thumb_url'] ?? null;
    $poster = $movie['poster_url'] ?? null;

    // Thiếu một trong hai thì dùng ảnh còn lại; thiếu cả hai thì giữ null
    if (empty($thumb) && empty($poster)) {
        $movie['thumb_url'] = null;
        $movie['poster_url'] = null;
        return;
    }
    if (empty($thumb)) {
        // Thumb lấy từ poster -> ảnh thumb cần crop; poster giữ nguyên
        $movie['thumb_url'] = $poster;
        $movie['poster_url'] = $poster;
        $movie['thumb_crop'] = true;
        return;
    }
    if (empty($poster)) {
        $movie['thumb_url'] = $thumb;
        $movie['poster_url'] = $thumb;
        return;
    }
    // Hoán đổi thumb <-> poster
    $movie['thumb_url'] = $poster;
    $movie['poster_url'] = $thumb;
}

function crawl_kk_page_handle($url)
{
    $sourcePage = file_get_contents($url);
    $sourcePage = json_decode($sourcePage);
    $listMovies = [];
    if (isset($sourcePage->items)) {
        $sourcePage = $sourcePage->items;
    }
    if (isset($sourcePage->data->items)) {
        $sourcePage = $sourcePage->data->items;
    }

    if (count($sourcePage) > 0) {
        foreach ($sourcePage as $key => $item) {
            array_push($listMovies, API_DOMAIN_KK . "/phim/{$item->slug}|{$item->_id}|{$item->modified->time}|{$item->name}|{$item->origin_name}|{$item->year}");
        }
        return join("\n", $listMovies);
    }
    return $listMovies;
}

add_action('wp_ajax_crawl_kk_page', 'crawl_kk_page');
function crawl_kk_page()
{
    echo crawl_kk_page_handle($_POST['url']);
    die();
}

add_action('wp_ajax_crawl_kk_movies', 'crawl_kk_movies');
function crawl_kk_movies()
{
    $data_post = $_POST['url'];
    $url = explode('|', $data_post)[0];
    $ophim_id = explode('|', $data_post)[1];
    $ophim_update_time = explode('|', $data_post)[2];

    $filterType = oIsset($_POST, 'filterType', []);
    $filterCategory = oIsset($_POST, 'filterCategory', []);
    $filterCountry = oIsset($_POST, 'filterCountry', []);

    $result = crawl_kk_movies_handle($url, $ophim_id, $ophim_update_time, $filterType, $filterCategory, $filterCountry);
    echo $result;
    die();
}

function crawl_kk_movies_handle($url, $ophim_id, $ophim_update_time, $filterType, $filterCategory, $filterCountry)
{
    try {
        $args = array(
            'post_type' => 'ophim',
            'meta_query' => array(
                array(
                    'key' => 'ophim_fetch_ophim_id',
                    'value' => $ophim_id,
                    'compare' => '=',
                )
            )
        );
        $wp_query = new WP_Query($args);

        $total = $wp_query->found_posts;

        if ($total > 0) {
            $args = array(
                'post_type' => 'ophim',
                'meta_query' => array(
                    array(
                        'key' => 'ophim_fetch_ophim_id',
                        'value' => $ophim_id,
                        'compare' => '=',
                    )
                )
            );
            $wp_query = new WP_Query($args);
            if ($wp_query->have_posts()) : while ($wp_query->have_posts()) : $wp_query->the_post();
                global $post;
                $get_fetch_time = get_post_meta($post->ID, 'ophim_fetch_ophim_update_time', true);
                if ($get_fetch_time == $ophim_update_time) {
                    $result = array(
                        'status' => true,
                        'post_id' => $post->ID,
                        'list_episode' => [],
                        'msg' => 'Nothing needs updating!',
                        'wait' => false,
                        'schedule_code' => SCHEDULE_CRAWLER_TYPE_NOTHING
                    );
                    return json_encode($result);
                }

                $sourcePage = file_get_contents($url);
                $sourcePage = json_decode($sourcePage, true);
                kk_swap_thumb_poster($sourcePage);
                $data = create_data($sourcePage, $url, $ophim_id, $ophim_update_time);

                $status = getStatus($data['status']);
                $formality = ($data['type'] == 'tv_series') ? 'tv_series' : 'single_movies';
                $post_id = $post->ID;

                update_post_meta($post_id, 'ophim_movie_formality', $formality);
                update_post_meta($post_id, 'ophim_movie_status', $status);
                update_post_meta($post_id, 'ophim_fetch_info_url', $data['fetch_url']);
                update_post_meta($post_id, 'ophim_fetch_ophim_id', $data['fetch_ophim_id']);
                update_post_meta($post_id, 'ophim_fetch_ophim_update_time', $data['fetch_ophim_update_time']);
                update_post_meta($post_id, 'ophim_original_title', $data['org_title']);
                update_post_meta($post_id, 'ophim_trailer_url', $data['trailer_url']);
                update_post_meta($post_id, 'ophim_runtime', $data['duration']);
                update_post_meta($post_id, 'ophim_episode', $data['episode']);
                update_post_meta($post_id, 'ophim_total_episode', $data['total_episode']);
                update_post_meta($post_id, 'ophim_quality', $data['quality']);
                update_post_meta($post_id, 'ophim_lang', $data['lang']);
                update_post_meta($post_id, 'ophim_showtime_movies', $data['showtime']);
                $updatepost = array(
                    'post_modified' => current_time('mysql'),
                    'post_modified_gmt' => current_time('mysql', 1),
                    'ID' => $post_id,
                );
                wp_update_post($updatepost);

                $crawl_settings = json_decode(get_option(CRAWL_KK_OPTION_SETTINGS, false));
                refresh_post_images($data, $post_id, $crawl_settings);

                $list_episode = get_list_episode($sourcePage, $post->ID);
                $result = array(
                    'status' => true,
                    'post_id' => $post->ID,
                    'data' => $data,
                    'list_episode' => $list_episode,
                    'wait' => true,
                    'schedule_code' => SCHEDULE_CRAWLER_TYPE_UPDATE
                );
                return json_encode($result);
            endwhile;
            endif;
        }

        $sourcePage = file_get_contents($url);
        $sourcePage = json_decode($sourcePage, true);
        kk_swap_thumb_poster($sourcePage);
        $data = create_data($sourcePage, $url, $ophim_id, $ophim_update_time, $filterType, $filterCategory, $filterCountry);

        if (!empty($data['crawl_filter'])) {
            $result = array(
                'status' => true,
                'post_id' => null,
                'data' => null,
                'list_episode' => null,
                'msg' => 'Filtered!',
                'wait' => false,
                'schedule_code' => SCHEDULE_CRAWLER_TYPE_FILTER
            );
            return json_encode($result);
        }

        $post_id = add_posts($data);

        // Re-apply images with KK crawl settings (add_posts uses Ophim option key)
        $crawl_settings = json_decode(get_option(CRAWL_KK_OPTION_SETTINGS, false));
        $thumb_image_url = download_resize_thumb($data, $post_id, $crawl_settings);
        $poster_image_url = download_resize_poster($data, $post_id, $crawl_settings);
        save_image_meta($post_id, 'ophim_thumb_url', $thumb_image_url);
        save_image_meta($post_id, 'ophim_poster_url', $poster_image_url);

        $list_episode = get_list_episode($sourcePage, $post_id);
        $result = array(
            'status' => true,
            'post_id' => $post_id,
            'data' => $url,
            'list_episode' => 'Add new',
            'wait' => true,
            'schedule_code' => SCHEDULE_CRAWLER_TYPE_INSERT
        );
        return json_encode($result);
    } catch (Exception $e) {
        $result = array(
            'status' => false,
            'post_id' => null,
            'data' => null,
            'list_episode' => null,
            'msg' => $e->getMessage(),
            'wait' => false,
            'schedule_code' => SCHEDULE_CRAWLER_TYPE_ERROR
        );
        return json_encode($result);
    }
}

add_action('wp_ajax_save_crawl_kk_schedule_secret', 'save_crawl_kk_schedule_secret');
function save_crawl_kk_schedule_secret()
{
    update_option(CRAWL_KK_OPTION_SECRET_KEY, $_POST['secret_key']);
    die();
}

add_action('wp_ajax_crawl_kk_schedule_enable', 'crawl_kk_schedule_enable');
function crawl_kk_schedule_enable()
{
    $schedule = array(
        'enable' => $_POST['enable'] === 'true' ? true : false
    );
    file_put_contents(CRAWL_KK_PATH_SCHEDULE_JSON, json_encode($schedule));
    die();
}

add_action('wp_ajax_crawl_kk_save_settings', 'crawl_kk_save_settings');
function crawl_kk_save_settings()
{
    $data = array(
        'pageFrom' => $_POST['pageFrom'] ?? 5,
        'pageTo' => $_POST['pageTo'] ?? 1,
        'crawl_resize_size_thumb' => $_POST['crawl_resize_size_thumb'] ?? null,
        'crawl_resize_size_thumb_w' => $_POST['crawl_resize_size_thumb_w'] ?? 0,
        'crawl_resize_size_thumb_h' => $_POST['crawl_resize_size_thumb_h'] ?? 0,
        'crawl_resize_size_poster' => $_POST['crawl_resize_size_poster'] ?? null,
        'crawl_resize_size_poster_w' => $_POST['crawl_resize_size_poster_w'] ?? 0,
        'crawl_resize_size_poster_h' => $_POST['crawl_resize_size_poster_h'] ?? 0,
        'crawl_convert_webp' => $_POST['crawl_convert_webp'] ?? null,
        'filterType' => $_POST['filterType'] ?? array(),
        'filterCategory' => $_POST['filterCategory'] ?? array(),
        'filterCountry' => $_POST['filterCountry'] ?? array(),
    );
    if (!get_option(CRAWL_KK_OPTION_SETTINGS)) {
        add_option(CRAWL_KK_OPTION_SETTINGS, json_encode($data));
    } else {
        update_option(CRAWL_KK_OPTION_SETTINGS, json_encode($data));
    }
    die();
}
