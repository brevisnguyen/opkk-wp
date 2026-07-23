<?php
$default_tab = null;
$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;
$schedule_enable = false;
if (file_exists(CRAWL_KK_PATH_SCHEDULE_JSON)) {
    $schedule_json = json_decode(file_get_contents(CRAWL_KK_PATH_SCHEDULE_JSON));
    $schedule_enable = isset($schedule_json->enable) && $schedule_json->enable === true;
}
?>
<div class="wrap crawlkk_wrap">
    <nav class="nav-tab-wrapper">
        <a href="?page=ofim-manager-crawlkk" class="nav-tab <?php if ($tab === null): ?>nav-tab-active<?php endif; ?>">Thủ
            công</a>
        <a href="?page=ofim-manager-crawlkk&tab=schedule"
           class="nav-tab <?php if ($tab === 'schedule'): ?>nav-tab-active<?php endif; ?>">Tự động</a>
    </nav>
    <div class="tab-content">
        <?php


        switch ($tab) :
            case 'schedule':
                $crawl_kk_settings = json_decode(get_option(CRAWL_KK_OPTION_SETTINGS, '[]'));
                ?>

                <div class="crawl_page">
                    <div class="postbox">
                        <div class="inside">
                            <b>Hưỡng dẫn cấu hình crontab</b>
                            <div>
                                <p>
                                    Thời gian thực hiện (<a href="https://crontab.guru/" target="_blank">Xem thêm</a>)
                                </p>
                                <p>
                                    Cấu hình crontab: <code><i style="color:blueviolet">*/10 * * * *</i> cd <i
                                                style="color:blueviolet">/path/to/</i>wp-content/plugins/opkk-wp/ &&
                                        php -q schedule_kk.php <i style="color:blueviolet">{secret_key}</i></code>
                                </p>
                                <p>
                                    Ví dụ:
                                    <br/>
                                    Mỗi 5 phút: <code>*/5 * * * * cd <?php echo OFIM_PLUGIN_PATCH; ?> && php -q
                                        schedule_kk.php <i
                                                style="color:blueviolet"><?php echo get_option(CRAWL_KK_OPTION_SECRET_KEY, ''); ?></i></code>
                                    <br/>
                                    Mỗi 10 phút: <code>*/10 * * * * cd <?php echo OFIM_PLUGIN_PATCH; ?> && php -q
                                        schedule_kk.php <i
                                                style="color:blueviolet"><?php echo get_option(CRAWL_KK_OPTION_SECRET_KEY, ''); ?></i></code>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="crawl_page">
                    <div class="postbox">
                        <div class="inside">
                            <b>Cấu hình tự động</b>
                            <div>
                                <p>
                                    Secret Key: <input type="text" name="crawl_kk_schedule_secret"
                                                       value="<?php echo get_option(CRAWL_KK_OPTION_SECRET_KEY, ''); ?>">
                                    <button id="kk_save_crawl_schedule_secret" class="button">Lưu mật khẩu</button>
                                </p>
                            </div>
                            <div>
                                <p>
                                    Kích hoạt:
                                    <input type="checkbox" class="wppd-ui-toggle" id="kk_crawl_schedule_enable"
                                           name="crawl_kk_schedule_enable"
                                           value=""
                                        <?php echo $schedule_enable ? 'checked' : ''; ?>
                                    >
                                </p>
                            </div>
                            <div>
                                <p>Trạng
                                    thái: <?php echo (int)get_option(CRAWL_KK_OPTION_RUNNING, 0) === 1 ? "<code style='color: blue'>Đang chạy...</code>" : "<code style='color: chocolate'>Dừng</code>"; ?></p>
                            </div>
                            <div>
                                <p>Bỏ qua định dạng: <code
                                            style="color: red"><?php echo join(', ', $crawl_kk_settings->filterType ?? []); ?></code>
                                </p>
                                <p>Bỏ qua thể loại: <code
                                            style="color: red"><?php echo join(', ', $crawl_kk_settings->filterCategory ?? []); ?></code>
                                </p>
                                <p>Bỏ qua quốc gia: <code
                                            style="color: red"><?php echo join(', ', $crawl_kk_settings->filterCountry ?? []); ?></code>
                                </p>
                            </div>
                            <div>
                                <p>Page đầu: <code
                                            style="color: blue"><?php echo $crawl_kk_settings->pageFrom ?? "N/A"; ?></code>
                                </p>
                                <p>Page cuối: <code
                                            style="color: blue"><?php echo $crawl_kk_settings->pageTo ?? "N/A"; ?></code></p>
                            </div>

                            <div class="notice notice-success">
                                <p>File logs: <code
                                            style="color:brown"><?php echo $schedule_log['log_filename']; ?></code></p>
                                <textarea style="width: 100%" rows="10" id="kk_schedule_log" class=""
                                          readonly><?php echo $schedule_log['log_data']; ?></textarea>
                            </div>

                        </div>
                    </div>
                </div>
                <?php
                break;
            default:
                $crawl_kk_settings = json_decode(get_option(CRAWL_KK_OPTION_SETTINGS, '[]'));
                ?>
                <div class="crawl_main crawlkk_main">
                    <div class="crawl_filter notice notice-info">
                        <div class="filter_title"><strong>Bỏ qua định dạng</strong></div>
                        <div class="filter_item">
                            <label><input type="checkbox" class="" name="kk_filter_type[]" value="single"> Phim lẻ</label>
                            <label><input type="checkbox" class="" name="kk_filter_type[]" value="series"> Phim bộ</label>
                            <label><input type="checkbox" class="" name="kk_filter_type[]" value="hoathinh"> Hoạt
                                hình</label>
                            <label><input type="checkbox" class="" name="kk_filter_type[]" value="tvshows"> Tv
                                shows</label>
                        </div>

                        <div class="filter_title"><strong>Bỏ qua thể loại</strong></div>
                        <div class="filter_item">
                            <?php
                            if (is_array($categoryFromApi) || is_object($categoryFromApi)) {
                                foreach ($categoryFromApi as $category) {
                                    ?>
                                    <label><input type="checkbox" class="" name="kk_filter_category[]"
                                                  value="<?php echo $category->name; ?>"> <?php echo $category->name; ?>
                                    </label>
                                    <?php
                                }
                            }
                            ?>
                        </div>

                        <div class="filter_title"><strong>Bỏ qua quốc gia</strong></div>
                        <div class="filter_item">
                            <?php
                            if (is_array($countryFromApi) || is_object($countryFromApi)) {
                                foreach ($countryFromApi as $country) {
                                    ?>
                                    <label><input type="checkbox" class="" name="kk_filter_country[]"
                                                  value="<?php echo $country->name; ?>"> <?php echo $country->name; ?>
                                    </label>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        <div class="filter_title"><strong>Hình ảnh</strong></div>
                        <div>
                            <label> <input type="checkbox"
                                           name="kk_crawl_resize_size_thumb" <?php if(oIsset($crawl_kk_settings, 'crawl_resize_size_thumb', 'off') == 'on') {
                                    echo 'checked';
                                } ?> />Tải & Resize Thumb => </label>
                            <label> Width (px): <input style="max-width: 80px" type="number" name="kk_crawl_resize_size_thumb_w" value="<?php echo oIsset($crawl_kk_settings, 'crawl_resize_size_thumb_w', 0); ?>"/></label>
                            <label> Height (px): <input style="max-width: 80px" type="number" name="kk_crawl_resize_size_thumb_h" value="<?php echo oIsset($crawl_kk_settings, 'crawl_resize_size_thumb_h', 0); ?>"/></label>
                        </div>
                        <div style="margin-top: 5px">
                            <label> <input type="checkbox"
                                           name="kk_crawl_resize_size_poster" <?php if(oIsset($crawl_kk_settings, 'crawl_resize_size_poster', 'off') == 'on') {
                                    echo 'checked';
                                } ?> />Tải & Resize Poster =></label>
                            <label> Width (px): <input style="max-width: 80px" type="number" name="kk_crawl_resize_size_poster_w" value="<?php echo oIsset($crawl_kk_settings, 'crawl_resize_size_poster_w', 0); ?>"/></label>
                            <label> Height (px): <input style="max-width: 80px" type="number" name="kk_crawl_resize_size_poster_h" value="<?php echo oIsset($crawl_kk_settings, 'crawl_resize_size_poster_h', 0); ?>"/></label>
                        </div>
                        <div style="margin-top: 5px">
                            <label> <input type="checkbox"
                                           name="kk_crawl_convert_webp" <?php if(oIsset($crawl_kk_settings, 'crawl_convert_webp', 'off') == 'on') {
                                    echo 'checked';
                                } ?> />Lưu định dạng webp</label>
                        </div>
                        <p>

                        <div id="kk_save_crawl_schedule" class="button">Lưu cấu hình</div>
                        </p>
                    </div>

                    <div class="crawl_page">
                        Page Crawl: From <input type="number" name="kk_page_from" value="">
                        To <input type="number" name="kk_page_to" value="">
                    </div>

                    <div class="crawl_page">
                        Url API <input type="text" id="kk_url_api"
                                       value="<?php echo API_DOMAIN_KK; ?>/danh-sach/phim-moi-cap-nhat" style="width: 70%">
                        <div id="kk_get_list_movies" class="primary">Get List Movies</div>
                    </div>

                    <div class="crawl_page">
                        Wait Timeout Random: From <input type="number" name="kk_timeout_from" value="">(ms) -
                        To <input type="number" name="kk_timeout_to" value=""> (ms)
                    </div>

                    <div class="crawl_page">
                        <div style="display: none" id="kk_msg" class="notice notice-success">
                            <p id="kk_msg_text"></p>
                        </div>
                        <textarea style="width: 100%" rows="10" id="kk_result_list_movies" class="list_movies"></textarea>
                        <div id="kk_roll_movies" class="roll">Trộn Link</div>
                        <div id="kk_crawl_movies" class="primary">Crawl Movies</div>

                        <div style="display: none;" id="kk_result_success" class="notice notice-success">
                            <p>Crawl Thành Công</p>
                            <textarea style="width: 100%" rows="10" id="kk_list_crawl_success"></textarea>
                        </div>

                        <div style="display: none;" id="kk_result_error" class="notice notice-error">
                            <p>Crawl Lỗi</p>
                            <textarea style="width: 100%" rows="10" id="kk_list_crawl_error"></textarea>
                        </div>
                    </div>
                </div>


                <?php
                break;
        endswitch;
        ?>
    </div>
</div>
