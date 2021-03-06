var current_project_id;
var current_instrument_id;
var current_starting_date;
var current_ending_date;
// var currently_updating = false;
var spinner_opts = {
    lines: 9, // The number of lines to draw
    length: 4, // The length of each line
    width: 2, // The line thickness
    radius: 4, // The radius of the inner circle
    corners: 1, // Corner roundness (0..1)
    rotate: 0, // The rotation offset
    direction: 1, // 1: clockwise, -1: counterclockwise
    color: "#111", // #rgb or #rrggbb or array of colors
    speed: 1, // Rounds per second
    trail: 60, // Afterglow percentage
    shadow: false, // Whether to render a shadow
    hwaccel: true, // Whether to use hardware acceleration
    className: "spinner", // The CSS class to assign to the spinner
    zIndex: 2e9, // The z-index (defaults to 2000000000)
    top: "50%", // Top position relative to parent
    left: "50%" // Left position relative to parent
};
var cookie_base = "myemsl_status_last_";


$(function() {
    if(window.disable_cookies){
        current_project_id = $("#project_selector").val() || initial_project_id;
        current_instrument_id = $("#instrument_selector").val() || initial_instrument_id;
        current_starting_date = $("#timeframe_selector").val() || initial_starting_date;
        current_ending_date = $("#timeframe_selector").val() || initial_ending_date;
        items_per_page = 20;
        page_offset = 0;
    }else{
        current_project_id = $.cookie(cookie_base + "project_selector") || initial_project_id;
        current_instrument_id = $.cookie(cookie_base + "instrument_selector") || initial_instrument_id;
        current_starting_date = $.cookie(cookie_base + "starting_date_selector") || initial_starting_date;
        current_ending_date = $.cookie(cookie_base + "ending_date_selector") || initial_ending_date;
        items_per_page = $.cookie(cookie_base + "items_per_page") || 20;
        page_offset = $.cookie(cookie_base + "page_offset") || 0;
    }
    current_project_id = current_project_id != "null" ? current_project_id : -1;
    current_instrument_id = current_instrument_id != "null" ? current_instrument_id : -1;

    setup_selectors(true);
    if ($("#project_selector").val()) {
        current_project_id = $("#project_selector").val();
        get_instrument_list(current_project_id);
    }
    setup_daterangepicker();

    $("#items_per_page").val(items_per_page);

    $("#items_per_page").on("change", function(event) {
        items_per_page = $(event.target).val();
        $.cookie(cookie_base + "items_per_page", items_per_page);
        // $("#bottom_pager_block").pagination("updateItemsOnPage", items_per_page);
        update_content(event);
    });

});

var setup_daterangepicker = function() {
    function cb(start, end) {
        var datepicker_message = "";
        if(!start._isValid || !end._isValid){
            datepicker_message = "<span class=\"placeholder_text\">Please select a date range&hellip;</span>";
        }else{
            datepicker_message = start.format("MMMM D, YYYY") + " &ndash; " + end.format("MMMM D, YYYY");
        }
        $("#time_range_container span.time_range_display").html(datepicker_message);
    }
    var trc = $("#time_range_container");
    cb(moment(correctTZ(new Date(current_starting_date))), moment(correctTZ(new Date(current_ending_date))));
    trc.daterangepicker({
        parentEl: "#bottom_selector_container",
        startDate: moment(correctTZ(new Date(current_starting_date))).format("MM/DD/YYYY"),
        endDate: moment(correctTZ(new Date(current_ending_date))).format("MM/DD/YYYY"),
        autoUpdateInput: false,
        linkedCalendars:false,
        ranges: {
            "Last 24 Hours": [moment().subtract(24, "hours"), moment()],
            "Last 7 Days": [moment().subtract(6, "days"), moment()],
            "Last 30 Days": [moment().subtract(29, "days"), moment()],
            "Last 60 Days": [moment().subtract(59, "days"), moment()],
            "Last 3 Months": [moment().subtract(3, "months"), moment()],
            "Last 6 Months": [moment().subtract(6, "months"), moment()],
            "Last Year": [moment().subtract(12, "months"), moment()],
        }
    }, cb);

    trc.on("apply.daterangepicker", function(event, picker){
        current_starting_date = picker.startDate.format("YYYY-MM-DD");
        current_ending_date = picker.endDate.format("YYYY-MM-DD");
        $.cookie(cookie_base + "starting_date_selector", current_starting_date);
        $.cookie(cookie_base + "ending_date_selector", current_ending_date);
        reset_page_offset();
        update_content();
    });
    trc.enable();
};

var reset_page_offset = function() {
    $.cookie(cookie_base + "page_offset", 0);
    $.cookie(cookie_base + "page_number", 1);
};

var setup_selectors = function(initial_load) {
    if (current_project_id == undefined || initial_load) {
        $("#instrument_selector")
            .select2({
                placeholder: ui_markup.instrument_selection_desc + "..."
            });
    }

    $("#project_selector")
        .select2({
            ajax: {
                dataType: "json",
                delay: 250,
                cache: true,
                url: function(params) {
                    var myURL = base_url + "ajax_api/get_projects_by_name/";
                    if (params.term != undefined) {
                        myURL += params.term;
                    }
                    return myURL;

                },
                data: function() {
                    return "";
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.items,
                        pagination: {
                            more: (params.page * 300) < data.total_count
                        }
                    };
                }
            },
            escapeMarkup: function(markup) {
                return markup;
            },
            templateResult: formatProject,
            templateSelection: formatProjectSelection
        })
        .off("change")
        .on("change", function(event) {
            reset_page_offset();
            update_content(event);
        });
};

var formatProject = function(item) {
    var markup = false;
    var start_date = moment(item.start_date);
    var end_date = moment(item.end_date);
    var start_date_string = start_date.isValid() ? start_date.format("MM/DD/YYYY") : "&mdash;&mdash;";
    var end_date_string = end_date.isValid() ? end_date.format("MM/DD/YYYY") : "&mdash;&mdash;";

    if (item.loading) return item.text;
    if (item.id.length > 0) {
        markup = "<div id=\"prop_info_" + item.id + " class=\"prop_info\">";
        markup += "   <div class=\"";
        markup += item.currently_active == "yes" ? "active" : "inactive";
        markup += "_project\"><strong>Project " + item.id + "</strong>";
        markup += "   </div>";
        markup += "   <div style=\"float:right;\">";
        markup += "     <span class=\"active_dates\">";
        if (item.currently_active == "yes" && item.state == "active") {
            markup += "Active Through " + end_date_string;
        } else if (item.currently_active == "no") {
            if (item.state == "preactive") {
                markup += "Inactive Until " + start_date_string;
            } else {
                if (!start_date.isValid() || !end_date.isValid()) {
                    markup += "Invalid Start/End Dates";
                } else {
                    markup += "Inactive Since " + end_date_string;
                }
            }
        }
        markup += "     </span>";
        markup += "   </div>";
        markup += "</div>";
        markup += "<div class=\"prop_description\">" + item.title + "</div>";
    }
    return markup;
};

var formatProjectSelection = function(item) {
    var markup =  ui_markup.project_selection_desc + "...";
    if (item.id.length > 0) {
        markup = "<span title=\"" + item.title + "\">" + item.text + "</span>";
    }
    return markup;
};

var get_instrument_list = function(project_id) {
    $("#instrument_selector").off("change");
    var inst_url = base_url + "ajax_api/get_instruments_for_project/" + project_id;
    var target = document.getElementById("instrument_selector_spinner");
    var spinner = new Spinner(spinner_opts).spin(target);
    $("#instrument_selector").empty();
    $.getJSON(
        inst_url,
        function(data) {
            if(data.total_count > 0){
                $("#instrument_selector").select2({
                    data: data.items,
                    placeholder: ui_markup.instrument_selection_desc + "...",
                    templateResult: formatInstrument,
                    templateSelection: formatInstrumentSelection,
                    matcher: my_matcher,
                    escapeMarkup: function(markup) {
                        return markup;
                    }
                });
                $("#instrument_selector").enable();
                setup_daterangepicker();
                $.each(
                    data.items,
                    function(index, item) {
                        initial_instrument_list.push(item.id);
                    }
                );
                if (initial_instrument_list.indexOf(current_instrument_id) < 0) {
                    $("#instrument_selector").val("").trigger("change");
                } else {
                    $("#instrument_selector").val(parseInt(current_instrument_id, 10)).trigger("change");
                    // update_content();
                }
            }else{
                $("#instrument_selector").select2({
                    data: data.items,
                    placeholder: "No Instruments Available for This Project",
                    escapeMarkup: function(markup) {
                        return markup;
                    }
                });
                $("#instrument_selector").disable();
                $("#time_range_container").data("daterangepicker").remove();
                $("#time_range_container").disable();
            }
            initial_instrument_list = [];

            spinner.stop();
        }
    );
    $("#instrument_selector")
        .on("change", function() {
            reset_page_offset();
            update_content();
        });

};

var formatInstrument = function(item) {
    if (item.loading) return item.text;
    var markup = false;
    var current_project_id = $("#project_selector").val();
    var active = item.active == "Y" ? "active" : "inactive";
    if (item.id) {
        if (item.id > 0) {
            markup = "<div id=\"inst_info_" + item.id + "\" class=\"inst_info\">";
            markup += "  <div class=\"" + active + "_instrument\">";
            markup += "     <strong>Instrument " + item.id + "</strong>";
            markup += "  </div>";
            markup += "  <div class=\"inst_description\">" + item.name + "</div>";
            markup += "</div>";
        } else if (item.id == -1) {
            markup = "<div id=\"inst_info_" + item.id + "\" class=\"inst_info\">";
            markup += "<strong>All Instruments for Project " + current_project_id + "</strong>";
            markup += "</div>";
        }
    }

    return markup;
};

var formatInstrumentSelection = function(item) {
    var markup = ui_markup.instrument_selection_desc + "...";
    var current_project_id = $("#project_selector").val();
    if (item.id > 0) {
        markup = item.text;
    } else if (item.id < 0) {
        markup = "All Instruments for Project " + current_project_id;
    }
    return markup;
};

var my_matcher = function(params, data) {
    // Always return the object if there is nothing to compare
    // data.text should only be blank for the placeholder, return the item
    if ($.trim(params.term) === "" || $.trim(data.text) === "") {
        return data;
    }

    var original = data.text.toUpperCase();
    var termstring = params.term.toUpperCase();

    var terms = termstring.split(" ");
    terms = $.isArray(terms) ? terms : [terms];
    var is_match = false;

    $.each(
        terms,
        function(index, term) {
            // Check if the text contains the term
            if (original.indexOf(term) > -1) {
                is_match = true;
            } else {
                is_match = false;
            }
            return is_match;
        }
    );

    if (is_match === true) {
        return data;
    }

    // If it doesn't contain the term, don't return anything
    return null;

};

var update_pager_display = function(){
    total_item_count = parseInt($.cookie(cookie_base + "total_item_count"), 10);
    items_per_page = parseInt($.cookie(cookie_base + "items_per_page"), 10);
    current_page = parseInt($.cookie(cookie_base + "page_number"), 10);
    if(total_item_count > items_per_page){
        // $("#items_per_page").show();
        $("#bottom_pager_block").pagination({
            items: total_item_count,
            itemsOnPage: items_per_page,
            cssStyle: "dark-theme",
            displayedPages: 3,
            edges: 3,
            currentPage: current_page,
            hrefTextPrefix: "",
            selectOnClick: false,
            onPageClick: function(pageNumber, event) {
                page_offset = items_per_page * (pageNumber - 1);
                $.cookie(cookie_base + "page_offset", page_offset);
                $.cookie(cookie_base + "page_number", pageNumber);
                update_content(event);
                return false; //shorts out the new page load since we're using ajax
            }
        });
    }else{
        // debugger;
        if($("#bottom_pager_block").pagination()){
            $("#bottom_pager_block").pagination("destroy");
        }
    }

};

var update_content = function(event) {
    if($("span.bottom_pager_block").text().length){
        $("#bottom_pager_block").pagination("disable");
    }
    var ts = moment().format("YYYYMMDDHHmmss");
    current_project_id = $("#project_selector").val() != null ? $("#project_selector").val() : current_project_id;
    current_instrument_id = $("#instrument_selector").val() != null ? $("#instrument_selector").val() : current_instrument_id;
    // current_timeframe = $("#timeframe_selector").val() != null ? $("#timeframe_selector").val() : current_timeframe;
    // current_starting_date = $("#timeframe_selector").val() != null ? $("#timeframe_selector").val() : current_timeframe;
    setup_selectors(false);
    if (event) {
        var el = $(event.target);
        if (el.val() != null) {
            if (el.prop("id") == "project_selector" && el.val() != null) {
                get_instrument_list(el.val());
            }
            $.cookie(cookie_base + el.prop("id"), el.val());
        }
    }

    if (current_project_id != 0 && current_instrument_id != 0) {
        var url = base_url + "status_api/overview_worker/" + current_project_id + "/" + current_instrument_id + "/" + current_starting_date + "/" + current_ending_date + "?ovr_" + ts;
        $("#item_info_container").hide();
        $("#loading_status").fadeIn(
            "slow",
            function() {
                // $(".criterion_selector").off("change");
                var getting = $.get(url);
                getting.done(
                    function(data) {
                        if (data) {
                            $("#loading_status").fadeOut(
                                200,
                                function() {
                                    $("#item_info_container").html(data);
                                    $("#item_info_container").fadeIn(
                                        "slow",
                                        function() {
                                            update_pager_display();
                                            setup_tree_data();
                                            setup_metadata_disclosure();
                                            get_doi_release_data();
                                            if(typeof setup_staging_buttons == "function"){
                                                setup_staging_buttons();
                                            }
                                        }
                                    );
                                }
                            );
                        }
                    }
                );
                getting.fail(
                    function(jqxhr, textStatus, error) {
                        $("#loading_status").fadeOut(
                            200,
                            function() {
                                $("#info_message_container h2").html("An Error occurred during refresh");
                                $("#info_message_container").append("<span class=\"fineprint\">" + error + "</span>");
                                $("#info_message_container").show();
                            }
                        );
                    }
                );
            }
        );
    }
};
