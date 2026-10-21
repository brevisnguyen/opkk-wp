jQuery(function ($) {
    if (!$(".crawlkk_wrap").length) {
        return;
    }

    var filterType = JSON.parse(localStorage.getItem("kk_filterType")) != null ? JSON.parse(localStorage.getItem("kk_filterType")) : [];
    var filterCategory = JSON.parse(localStorage.getItem("kk_filterCategory")) != null ? JSON.parse(localStorage.getItem("kk_filterCategory")) : [];
    var filterCountry = JSON.parse(localStorage.getItem("kk_filterCountry")) != null ? JSON.parse(localStorage.getItem("kk_filterCountry")) : [];

    var page_from = localStorage.getItem("kk_page_from") ? localStorage.getItem("kk_page_from") : 10;
    var page_to = localStorage.getItem("kk_page_to") ? localStorage.getItem("kk_page_to") : 1;
    $("input[name=kk_page_from]").val(page_from);
    $("input[name=kk_page_to]").val(page_to);

    var timeout_from = localStorage.getItem("kk_timeout_from") ? localStorage.getItem("kk_timeout_from") : 1000;
    var timeout_to = localStorage.getItem("kk_timeout_to") ? localStorage.getItem("kk_timeout_to") : 3000;
    $("input[name=kk_timeout_from]").val(timeout_from);
    $("input[name=kk_timeout_to]").val(timeout_to);

    $("input[name='kk_filter_type[]']").each(function () {
        if (filterType.includes($(this).val())) {
            $(this).attr("checked", true);
        }
    });
    $("input[name='kk_filter_category[]']").each(function () {
        if (filterCategory.includes($(this).val())) {
            $(this).attr("checked", true);
        }
    });
    $("input[name='kk_filter_country[]']").each(function () {
        if (filterCountry.includes($(this).val())) {
            $(this).attr("checked", true);
        }
    });

    const buttonGetListMovies = $("div#kk_get_list_movies");
    const inputPageFrom = $("input[name=kk_page_from]");
    const inputPageTo = $("input[name=kk_page_to]");
    const divMsg = $("div#kk_msg");
    const divMsgText = $("p#kk_msg_text");
    const textArealistMovies = $("textarea#kk_result_list_movies");
    const buttonCrawlMovies = $("div#kk_crawl_movies");
    const buttonRollMovies = $("div#kk_roll_movies");
    const divMsgCrawlSuccess = $("div#kk_result_success");
    const divMsgCrawlError = $("div#kk_result_error");
    const textAreaResultSuccess = $("textarea#kk_list_crawl_success");
    const textAreaResultError = $("textarea#kk_list_crawl_error");

    buttonRollMovies.on("click", () => {
        var listLink = textArealistMovies.val();
        listLink = listLink.split("\n");
        listLink.sort(() => Math.random() - 0.5);
        listLink = listLink.join("\n");
        textArealistMovies.val(listLink);
    });

    buttonGetListMovies.on("click", () => {
        divMsg.show(300);
        textArealistMovies.show(300);
        crawl_page_callback(parseInt(inputPageFrom.val()));
    });

    const crawl_page_callback = (currentPage) => {
        var url_api = $("#kk_url_api").val();
        var urlPageCrawl = url_api + `?page=${currentPage}`;

        if (currentPage < parseInt(inputPageTo.val())) {
            var text = $("#kk_result_list_movies").val();
            var lines = text.split(/\r|\r\n|\n/);
            var count = lines.length;
            divMsgText.html("Done! " + count + " Phim");
            buttonCrawlMovies.show(300);
            return false;
        }
        divMsgText.html(`Crawl Page: ${urlPageCrawl}`);
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "crawl_kk_page",
                url: urlPageCrawl,
            },
            beforeSend: function () {
                buttonGetListMovies.hide(300);
            },
            success: function (res) {
                let currentList = textArealistMovies.val();
                if (currentList != "") currentList += "\n" + res;
                else currentList += res;

                textArealistMovies.val(currentList);
                currentPage--;
                crawl_page_callback(currentPage);
            },
        });
    };

    var inputFilterType = [];
    var inputFilterCategory = [];
    var inputFilterCountry = [];

    buttonCrawlMovies.on("click", () => {
        divMsg.show(300);
        divMsgCrawlSuccess.show(300);
        divMsgCrawlError.show(300);

        inputFilterType = [];
        inputFilterCategory = [];
        inputFilterCountry = [];

        $("input[name='kk_filter_type[]']:checked").each(function () {
            inputFilterType.push($(this).val());
        });
        $("input[name='kk_filter_category[]']:checked").each(function () {
            inputFilterCategory.push($(this).val());
        });
        $("input[name='kk_filter_country[]']:checked").each(function () {
            inputFilterCountry.push($(this).val());
        });

        crawl_kk_movies_loop();
    });

    const crawl_kk_movies_loop = () => {
        var listLink = textArealistMovies.val();
        listLink = listLink.split("\n");
        let linkCurrent = listLink.shift();
        if (linkCurrent == "") {
            divMsgText.html(`Crawl Done!`);
            return false;
        }
        listLink = listLink.join("\n");
        textArealistMovies.val(listLink);
        divMsgText.html(`Crawl Movies: <b>${linkCurrent}</b>`);

        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "crawl_kk_movies",
                url: linkCurrent,
                filterType: inputFilterType,
                filterCategory: inputFilterCategory,
                filterCountry: inputFilterCountry,
            },
            beforeSend: function () {
                buttonCrawlMovies.hide(300);
                buttonRollMovies.hide(300);
            },
            success: function (res) {
                let data = JSON.parse(res);
                if (data.status) {
                    let currentList = textAreaResultSuccess.val();
                    if (currentList != "") currentList += "\n" + linkCurrent;
                    else currentList += linkCurrent;
                    textAreaResultSuccess.val(currentList);
                } else {
                    let currentList = textAreaResultError.val();
                    if (currentList != "") currentList += "\n" + linkCurrent;
                    else currentList += linkCurrent;
                    textAreaResultError.val(currentList + "=====>>" + data.msg);
                }

                var wait_timeout = 1000;
                if (data.wait) {
                    let timeout_from_val = $("input[name=kk_timeout_from]").val();
                    let timeout_to_val = $("input[name=kk_timeout_to]").val();
                    let maximum = Math.max(timeout_from_val, timeout_to_val);
                    let minimum = Math.min(timeout_from_val, timeout_to_val);
                    wait_timeout = Math.floor(Math.random() * (maximum - minimum + 1)) + minimum;
                }
                divMsgText.html(`Wait timeout ${wait_timeout}ms`);
                setTimeout(() => {
                    crawl_kk_movies_loop();
                }, wait_timeout);
            },
            error: function () {
                let currentList = textAreaResultError.val();
                if (currentList != "") currentList += "\n" + linkCurrent;
                else currentList += linkCurrent;
                textAreaResultError.val(currentList);

                crawl_kk_movies_loop();
            },
        });
    };

    $("input[name='kk_filter_type[]']").change(() => {
        var saveFilterData = [];
        $("input[name='kk_filter_type[]']:checked").each(function () {
            saveFilterData.push($(this).val());
        });
        localStorage.setItem("kk_filterType", JSON.stringify(saveFilterData));
    });

    $("input[name='kk_filter_category[]']").change(() => {
        var saveFilterData = [];
        $("input[name='kk_filter_category[]']:checked").each(function () {
            saveFilterData.push($(this).val());
        });
        localStorage.setItem("kk_filterCategory", JSON.stringify(saveFilterData));
    });

    $("input[name='kk_filter_country[]']").change(() => {
        var saveFilterData = [];
        $("input[name='kk_filter_country[]']:checked").each(function () {
            saveFilterData.push($(this).val());
        });
        localStorage.setItem("kk_filterCountry", JSON.stringify(saveFilterData));
    });

    $("input[name=kk_page_from]").change(() => {
        localStorage.setItem("kk_page_from", $("input[name=kk_page_from]").val());
    });
    $("input[name=kk_page_to]").change(() => {
        localStorage.setItem("kk_page_to", $("input[name=kk_page_to]").val());
    });
    $("input[name=kk_timeout_from]").change(() => {
        localStorage.setItem("kk_timeout_from", $("input[name=kk_timeout_from]").val());
    });
    $("input[name=kk_timeout_to]").change(() => {
        localStorage.setItem("kk_timeout_to", $("input[name=kk_timeout_to]").val());
    });

    $("#kk_save_crawl_schedule").on("click", () => {
        let pageFrom = $("input[name=kk_page_from]").val();
        let pageTo = $("input[name=kk_page_to]").val();
        let crawl_resize_size_thumb = $("input[name=kk_crawl_resize_size_thumb]:checked").val();
        let crawl_resize_size_thumb_w = $("input[name=kk_crawl_resize_size_thumb_w]").val();
        let crawl_resize_size_thumb_h = $("input[name=kk_crawl_resize_size_thumb_h]").val();
        let crawl_resize_size_poster = $("input[name=kk_crawl_resize_size_poster]:checked").val();
        let crawl_resize_size_poster_w = $("input[name=kk_crawl_resize_size_poster_w]").val();
        let crawl_resize_size_poster_h = $("input[name=kk_crawl_resize_size_poster_h]").val();
        let crawl_convert_webp = $("input[name=kk_crawl_convert_webp]:checked").val();
        let filterTypeSave = [];
        $("input[name='kk_filter_type[]']:checked").each(function () {
            filterTypeSave.push($(this).val());
        });

        let filterCategorySave = [];
        $("input[name='kk_filter_category[]']:checked").each(function () {
            filterCategorySave.push($(this).val());
        });

        let filterCountrySave = [];
        $("input[name='kk_filter_country[]']:checked").each(function () {
            filterCountrySave.push($(this).val());
        });

        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "crawl_kk_save_settings",
                pageFrom,
                pageTo,
                filterType: filterTypeSave,
                filterCategory: filterCategorySave,
                filterCountry: filterCountrySave,
                crawl_resize_size_thumb,
                crawl_resize_size_thumb_w,
                crawl_resize_size_thumb_h,
                crawl_resize_size_poster,
                crawl_resize_size_poster_w,
                crawl_resize_size_poster_h,
                crawl_convert_webp,
            },
            success: function () {
                alert("Lưu thành công!");
            },
            error: function () {
                alert("Lưu cấu hình thất bại!");
            },
        });
    });

    $("#kk_crawl_schedule_enable").on("click", () => {
        let enable = $("#kk_crawl_schedule_enable").is(":checked");
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "crawl_kk_schedule_enable",
                enable
            },
        });
    });

    $("#kk_save_crawl_schedule_secret").on("click", () => {
        let secret_key = $("input[name='crawl_kk_schedule_secret']").val();
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "save_crawl_kk_schedule_secret",
                secret_key
            },
            success: function () {
                alert("Lưu thành công!");
            },
            error: function () {
                alert("Lưu thất bại!");
            },
        });
    });

    // Crawl một phim theo slug
    $("#kk_single_crawl").on("click", function () {
        const slug = $("#kk_single_slug").val().trim();
        const box = $("#kk_single_msg");
        const text = $("#kk_single_msg_text");
        const btn = $(this);
        const show = (cls, html) => {
            box.removeClass("notice-success notice-error notice-info").addClass(cls).show(200);
            text.html(html);
        };
        if (!slug) {
            show("notice-error", "Hãy nhập slug của phim");
            return;
        }
        btn.prop("disabled", true);
        show("notice-info", "Đang crawl: <b></b>");
        text.find("b").text(slug);
        $.ajax({
            url: ajaxurl,
            type: "POST",
            dataType: "json",
            data: {
                action: "crawl_kk_single",
                slug: slug,
                force: $("#kk_single_force").is(":checked") ? "1" : "0",
            },
            success: function (data) {
                if (data && data.status) {
                    const link = data.edit_link ? ` <a href="${data.edit_link}" target="_blank">Mở bài viết</a>` : "";
                    const title = $("<span>").text(data.title || "").html();
                    show("notice-success", `${title ? "<b>" + title + "</b> - " : ""}${$("<span>").text(data.msg || "Crawl thành công").html()}${link}`);
                } else {
                    show("notice-error", $("<span>").text((data && data.msg) || "Crawl lỗi").html());
                }
            },
            error: function () {
                show("notice-error", "Lỗi kết nối hoặc server trả về lỗi");
            },
            complete: function () {
                btn.prop("disabled", false);
            },
        });
    });
});
