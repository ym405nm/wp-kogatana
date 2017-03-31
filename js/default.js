jQuery(".kogatana_updates").each(function(i, elem) {
    var data_name = jQuery(elem).attr("id");
    var data_name_current = "#" + data_name + "_current";
    var current_version = jQuery(data_name_current).attr("data-version");
    var data_name_result = "#" + data_name + "_result";
    var data_type = jQuery(elem).attr("data-type");
    var themes_update_flag = false;
    var plugins_update_flag = false;
    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: {
            'action': 'kogatana_update_info',
            'data_type' : data_type,
            'target_name' : jQuery(elem).attr("data-name")
        },
        success: function (response) {
            if("ok" == response.result) {
                var latest_version = response.version;
                jQuery(data_name_result).text(latest_version);
                if(current_version != latest_version){
                    if("themes" == data_type && !themes_update_flag){
                        jQuery(".themes-update-notice").show("slow");
                    }
                    if("plugins" == data_type && !plugins_update_flag){
                        jQuery(".plugins-update-notice").show("slow");
                    }
                    jQuery(data_name_result).css("color", "red");
                }
            }else{
                jQuery(data_name_result).text("-");
            }
        }
    });
});

jQuery.ajax(
    {
        type: 'POST',
        url: ajaxurl,
        data: {
            'action': 'kogatana_get_portal',
            'data-type': 'plugins',
            'api_token' : jQuery("#api_token").val()
        },
        success: function (response) {
            output_portal(response, 'plugins');
        }
    }
);
jQuery.ajax(
    {
        type: 'POST',
        url: ajaxurl,
        data: {
            'action': 'kogatana_get_portal',
            'data-type': 'themes',
            'api_token' : jQuery("#api_token").val()
        },
        success: function (response) {
            output_portal(response, 'themes');
        }
    }
);

function output_portal(response, log_data_type){
    var error = false;
    if("error" in response){
        error = true;
    }
    jQuery(".kogatana_updates").each(function(i, elem) {
        var data_type = jQuery(elem).attr("data-type");
        var data_name = jQuery(elem).attr("data-name");
        var elem_id = jQuery(elem).attr("id");
        var target_id = elem_id + "_portal";
        if(data_name in response && data_type == log_data_type && !data_name.startsWith("twenty")){
            // 合致する
            jQuery("#" + target_id).css("color", "red");
            jQuery("#" + target_id).css("font-weight", "bold");
            jQuery("#" + target_id).text("ログあり");
        }else{
            if(data_type == log_data_type) {
                if(error){
                    jQuery("#" + target_id).text("エラー");
                }else{
                    jQuery("#" + target_id).text("ログなし");
                }
            }
        }
    });
}
