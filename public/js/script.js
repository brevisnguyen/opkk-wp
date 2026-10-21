jQuery(function($){
    $(document).ready(function() {
        $("[id*=_media_audio-]").remove();
        $("[id*=_recent-posts-]").remove();
        $("[id*=_recent-comments-]").remove();
        $("[id*=_categories-__i__]").remove();
        $("[id*=_rss-__i__]").remove();
        $("[id*=_media_gallery-__i__]").remove();
        $("[id*=_custom_html-__i__]").remove();
        $("[id*=_archives-__i__]").remove();
        $("[id*=_calendar-__i__]").remove();
        $("[id*=_nav_menu-__i__]").remove();
        $("[id*=_meta-__i__]").remove();
        $("[id*=_tag_cloud-__i__]").remove();
        $("[id*=_pages-__i__]").remove();
        $("[id*=_search-__i__]").remove();
        $("[id*=_media_video-]").remove();
        $("[id*=_text-__i__]").remove();
        $("[id*=_media_image-]").remove();
    });
    /*
     * Select/Upload image(s) event
     */
    $('body').on('click', '.ophim_upload_image_button', function(e){
        e.preventDefault();

        var button = $(this),
            custom_uploader = wp.media({
                title: 'Insert image',
                library : {
                    // uncomment the next line if you want to attach image to the current post
                    // uploadedTo : wp.media.view.settings.post.id,
                    type : 'image'
                },
                button: {
                    text: 'Use this image' // button label text
                },
                multiple: false // for multiple image selection set to true
            }).on('select', function() { // it also has "open" and "close" events
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                document.getElementById("poster").value = attachment.url.replace(window.location.origin, "");
                document.getElementById("imgPoster").src = attachment.url.replace(window.location.origin, "");
                /* if you sen multiple to true, here is some code for getting the image IDs
                var attachments = frame.state().get('selection'),
                    attachment_ids = new Array(),
                    i = 0;
                attachments.each(function(attachment) {
                    attachment_ids[i] = attachment['id'];
                    console.log( attachment );
                    i++;
                });
                */
            })
                .open();
    });
    $('body').on('click', '.ophim_upload_image_thumb_url', function(e){
        e.preventDefault();

        var button = $(this),
            custom_uploader = wp.media({
                title: 'Insert image',
                library : {
                    // uncomment the next line if you want to attach image to the current post
                    // uploadedTo : wp.media.view.settings.post.id,
                    type : 'image'
                },
                button: {
                    text: 'Use this image' // button label text
                },
                multiple: false // for multiple image selection set to true
            }).on('select', function() { // it also has "open" and "close" events
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                document.getElementById("thumb").value = attachment.url.replace(window.location.origin, "");
                document.getElementById("thumb_url").src = attachment.url.replace(window.location.origin, "");
                /* if you sen multiple to true, here is some code for getting the image IDs
                var attachments = frame.state().get('selection'),
                    attachment_ids = new Array(),
                    i = 0;
                attachments.each(function(attachment) {
                    attachment_ids[i] = attachment['id'];
                    console.log( attachment );
                    i++;
                });
                */
            })
                .open();
    });
    $('body').on('click', '.ophim_upload_image_logo_jwplayer', function(e){
        e.preventDefault();

        var button = $(this),
            custom_uploader = wp.media({
                title: 'Insert image',
                library : {
                    // uncomment the next line if you want to attach image to the current post
                    // uploadedTo : wp.media.view.settings.post.id,
                    type : 'image'
                },
                button: {
                    text: 'Use this image' // button label text
                },
                multiple: false // for multiple image selection set to true
            }).on('select', function() { // it also has "open" and "close" events
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                document.getElementById("logo_jwplayer").value = attachment.url.replace(window.location.origin, "");
                /* if you sen multiple to true, here is some code for getting the image IDs
                var attachments = frame.state().get('selection'),
                    attachment_ids = new Array(),
                    i = 0;
                attachments.each(function(attachment) {
                    attachment_ids[i] = attachment['id'];
                    console.log( attachment );
                    i++;
                });
                */
            })
                .open();
    });

});

jQuery(function ($) {
    $('.add-to-featured').click(function() {
        var postid = $(this).data("postid");
        var nonce = $(this).data("nonce");
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                 action: "dt_add_featured",
                 postid,
                 nonce,
            },
            success: function (res) {
                $("#feature-del-"+postid).show();
                $("#feature-add-"+postid).hide();
            },
            error: function (xhr, ajaxOptions, thrownError) {
                alert("Lưu thất bại!");
            },
        });
    });
    $('.del-of-featured').click(function() {
        var postid = $(this).data("postid");
        var nonce = $(this).data("nonce");
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                 action: "dt_remove_featured",
                 postid,
                 nonce,
            },
            success: function (res) {
                $("#feature-del-"+postid).hide();
                $("#feature-add-"+postid).show();
            },
            error: function (xhr, ajaxOptions, thrownError) {
                alert("Lưu thất bại!");
            },
        });
    });
    $('#add-server-btn').click(function() {
        let namesv = $("input[name='name-server-ophim']").val();
        var postid = $(this).data("postid");
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "add_server_phim",
                namesv,
                postid
            },
            success: function (res) {
                location.reload();
            },
            error: function (xhr, ajaxOptions, thrownError) {
                alert("Lưu thất bại!");
            },
        });
    });



    // save css js
    $("#save_config_cssjs").on("click", () => {
        var css = $("#ophim_css").val();
        var js = $("#ophim_js").val();
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "ophim_save_config_cssjs",
                css,
                js,
            },
            success: function (res) {
                alert("Lưu thành công!")
            },
            error: function (xhr, ajaxOptions, thrownError) {
                alert("Lưu cấu hình thất bại!");
            },
        });
    })

});