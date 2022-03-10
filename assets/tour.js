const BUFFER_WEIGHT = 21;
const FLY_TO_PADDING = [10, 10];

// a couple state variables to keep track of
let save_scroll_pos = true; // save the (vertical) scroll position
let scroll_pos = 0; // the saved (vertical) scroll position

// create map
const map = L.map('map', {
    zoomControl: true,
    attributionControl: false,
});

// TODO: add the things

// ---------- Scrollama ---------- //
const SCROLLAMA_OFFSET = 0.33;
const SCROLLAMA_DEBUG = false;
const SCROLLAMA_ENTER_VIEW_WAIT = 500; // half a second

let scrolly_temp_view = null;

if ($("#scrolly .step").length > 0) {
    scroller = scrollama();
    scroller.setup({
        step: "#scrolly .step",
        offset: SCROLLAMA_OFFSET,
        debug: SCROLLAMA_DEBUG,
    }).onStepEnter(function(e) {
        // TODO
    }).onStepExit(function() {
        // TODO: Try removing
    });
}

// map "on" functions
// todo layeradd
// todo layerremove
map.on("zoomend", function() {
    // reset labels and basemaps
    // adjust zoom buttons
    $(".leaflet-control-zoom a").removeAttr("aria-disabled");
    $(".leaflet-disabled").attr("aria-disabled", true);
});

// enter view

// ---------- General Setup ---------- //
$(document).ready(function() {
    
    // features

    // move map controls for more sensible DOM order
    let controls = $(".leaflet-control-container");
    controls.remove();
    $("#map").prepend(controls);

    // interaction
    $("#nav-toggle-btn").on("click", checkMapToggleScroll);
    $("#mobile-map-toggle-btn").on("click", function() {
        $(this).parent().toggleClass('expanded');
        // $('#' + this.getAttribute('data-toggles')).toggle('hide');
        togglePopupButton(this); // function from theme
    });
    $("#map-toggle-btn").on("click", function() {
        if (this.getAttribute("data-map-active") === "false") switchToMap(this.id);
        else switchToContent();
    });
    $("#mobile-legend-btn").on("click", function() {
        // todo
    });
    $("map-reset-btn").on("click", function() {
        // todo
    });
});

// Modify window.onscroll function from theme
function doWindowScrollAction() {
    toggleBackToTop();
    // save scroll position for mobile
    if (isMobile() && save_scroll_pos) scroll_pos = window.scrollY;
    checkMapToggleScroll();
}

function checkMapToggleScroll() {
    if (isMobile()) {
        let height = $("header").get(0).offsetHeight + parseFloat($(".tour-wrapper").first().css("padding-top")) + document.getElementById("main-nav").offsetHeight;
        if (window.scrollY >= height) {
            $("#map-nav").addClass('scrolled');
        } else $('#map-nav').removeClass('scrolled');
    }
}

// overwrite function from theme to do nothing
function expandNav() {
    // do nothing
}

/**
 * For mobile, switch to viewing the leaflet map. Called by the content toggle button and any show view buttons.
 * 
 * @param focus_id - (String) The id of the element calling the function, which will be saved so that focus can be returned to that element when switching back to content.
 */
function switchToMap(focus_id) {
    $("body").addClass("map-active");
    save_scroll_pos = false; // don't want scrolling to affect position when returning to content
    $("#map").focus(); // TODO: Ensure that this is the sensible/expected decision
    $("#map-toggle-btn").attr("data-focus", focus_id).attr("data-map-active", "true").text("Leave Map");
    // todo: adjust map if needed
}

/**
 * For mobile, switch to viewing the narrative content. Called by the map toggle button.
 */
function switchToContent() {
    $("body").removeClass("map-active");
    // make sure scroll position is saved and remembered
    save_scroll_pos = true;
    window.scrollTo(0, scroll_pos);
    // remember and return to previous focus (the id of the element used to switch to the map should have been saved in the content toggle button)
    let btn = $("#map-toggle-btn");
    // TODO: Will using "this" work?
    $("#" + btn.attr("data-focus")).focus();
    btn.attr("data-focus", "").attr("data-map-active", "false").text("View Map");
}