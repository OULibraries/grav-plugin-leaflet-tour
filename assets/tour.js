/**
 * global variable that will hold all relevant tour information, including basemaps, datasets, features, and views
 */
var tour = {
    tile_layer: null,
    feature_layer: null,
}
/**
 * global variable that will indicate the state of the page - current scroll position and whether or not the scroll position should be updated whenever a change is detected
 */
var page_state = {
    save_scroll_pos: true, // save the (vertical) scroll position
    scroll_pos: 0, // the saved (vertical) scroll position
}
/**
 * global variable that will indicate the state of the tour - whether or not the map should be adjusted when switched to (only relevant for mobile view), whether or not views should be entered by scrolling, the current view, and any currently active basemaps
 */
var tour_state = {
    map_needs_adjusting: true,
    animation: true, // enter views by scrolling
    view: null,
    basemaps: [], // active basemaps
}

// ---------- Map/Tour Initialization ---------- //
const map = createMap(tour_options);
if (tour_options.show_map_location_in_url) hash = new L.Hash(map);
tour.tile_layer = createTileServer(tour_options.tile_server);
map.addLayer(tour.tile_layer);
tour.basemaps = createBasemaps(tour_basemaps);
tour.datasets = tour_datasets;
tour.features = tour_features;
tour.features.forEach(createFeature);
tour.views = tour_views;
setupViews(tour.views, tour.features, tour.basemaps);

// ---------- Scrollama Initialization ---------- //
let scrolly_temp_view = null;
// for some reason scrollama is triggered twice at the beginning, needs to be ignored both times
let scrolly_wait = 2;

if ($("#scrolly .step").length > 0) {
    scroller = scrollama();
    scroller.setup({
        step: "#scrolly .step",
        offset: SCROLLAMA_OFFSET,
        debug: SCROLLAMA_DEBUG,
        container: document.getElementById("tour-wrapper"),
    }).onStepEnter(function(e) {
        if (scrolly_wait) {
            scrolly_wait--;
        }
        else if (!isMobile() && tour_state.animation) {
            scrolly_temp_view = e.element.getAttribute("data-view");
            // use timeout function so that if multiple views are scrolled through at once, only the last view will be truly entered
            setTimeout(function() {
                enterView(scrolly_temp_view);
            }, SCROLLAMA_ENTER_VIEW_WAIT);
        }
    });
}

// ---------- Set up everything else ---------- //
map.on("zoomend", handleMapZoom);
map.on("moveend", handleMapMove);

let window_scroll_tick = false;

$(document).ready(function() {
    let loc = window.location.pathname;
    let lng = parseFloat(sessionStorage.getItem(loc + '_lng'));
    let lat = parseFloat(sessionStorage.getItem(loc + '_lat'));
    let zoom = parseInt(sessionStorage.getItem(loc + '_zoom'));

    // set tile server attribution if needed
    setTileServerAttr($("#server-attribution"), tour.tile_layer);
    /**
     * For desktop view (i.e. both column and map are displayed side by side), make sure that the initial "tour" view can be entered by scrolling up from view 1 and that the final view can be entered by scrolling down (adds whitespace where needed)
     */
    if (!isMobile()) {
        // "tour" view - add space to the bottom if it is not long enough to be entered by scrolling up from view 1
        let top_height = document.getElementById("top").offsetHeight + document.getElementById("main-nav").offsetHeight + document.getElementById("tour").offsetHeight;
        let target = (window.innerHeight * 2) / 5;
        let diff = target - top_height;
        if (diff > 0) {
            $("#tour .bottom-step").css("height", (diff + 30) + "px");
        }
        // last view - add space to bottom if it + the rest of the column content together is not long enough for the view to be entered by scrolling down
        let last_height = Array.from(document.getElementsByClassName("step")).pop().offsetHeight;
        for (let id of ['attribution', 'footer']) {
            let el = document.getElementById(id);
            if (el) last_height += el.offsetHeight;
        }
        target = (window.innerHeight * 3) / 4;
        diff = target - last_height;
        if (diff > 0) {
            $("#view-content").css("padding-bottom", diff + "px");
        }
    }

    // return to previous scroll position if applicable - this typically happens automatically on page refresh, but because of the way the tour is setup, it needs to be handled manually, instead
    let scroll_top = sessionStorage.getItem(loc + '_scroll_top');
    document.getElementById("tour-wrapper").scrollTop = scroll_top ?? 0;
    // check for saved view, use '_tour' if no valid view is saved
    let view_id = sessionStorage.getItem('tour_view') ?? '_tour';
    if (!tour.views.get(view_id)) view_id = '_tour';
    // go to view bounds or saved bounds
    if (lng && lat) {
        if (!zoom) zoom = map.getZoom();
        map.flyTo([lat, lng], zoom, { animate: false });
    }
    else {
        map.flyToBounds(tour.views.get(view_id).bounds, { padding: FLY_TO_PADDING, animate: false, duration: 0 });
        if (zoom) map.setZoom(zoom, { animate: false });
    }

    // set view - no flyTo (map bounds were set above and should not be adjusted), but need to handle other aspects of setting view
    enterView(view_id, false);

    // move map controls for more sensible DOM order
    let controls = $(".leaflet-control-container");
    controls.remove();
    $("#map").prepend(controls);

    // interaction
    // map and nav
    // click event toggles nav menu (set in theme); because the map toggle button (if visible) is below the nav and should only be given a fixed location once it is passed (i.e. it should be "sticky"), it is necessary to check and possibly update its status whenever the nav is expanded or collapsed
    $("#nav-toggle-btn").on("click", checkMapToggleScroll);
    // toggle between map view and content view (mobile only)
    $("#map-toggle-btn").on("click", function() {
        if (this.getAttribute("data-map-active") === "false") switchToMap(this.id);
        else switchToContent();
    });
    // update ARIA, set and store state - indicated whether or not views should be entered by scrolling (i.e. whether or not the map will animate when the user scrolls)
    $("#map-animation-toggle").on("click", function() {
        let checked = this.getAttribute("aria-checked") === 'true' ? false : true;
        this.setAttribute("aria-checked", checked);
        tour_state.animation = checked;
        sessionStorage.setItem('animation', checked);
    });
    // load animation settings
    if (sessionStorage.getItem('animation') === 'false') $("#map-animation-toggle").click();
    // zoom buttons - zoom the map in or out when clicked
    $("#zoom-out-btn").on("click", function() {
        map.zoomOut();
    });
    $("#zoom-in-btn").on("click", function() {
        map.zoomIn();
    });

    // legend
    // expand/collapse the legend (desktop only)
    $("#legend-toggle-btn").on("click", function() {
        $("#" + this.getAttribute("aria-controls")).toggleClass("minimized");
        toggleExpanded(this);
    });
    // show the legend (mobile only)
    $("#mobile-legend-btn").on("click", toggleMobileLegend);
    // hide the legend (mobile only)
    $("#legend-close-btn").on("click", toggleMobileLegend);
    // toggle feature visibility whenever a dataset in the legend is toggled
    $(".legend-checkbox").on("input", function() {
        toggleDataset(this.value, tour.datasets, !this.checked);
    });
    // expand/collapse the basemaps section of the legend (desktop only)
    $("#legend-basemaps-toggle").on("click", function() {
        this.parentElement.parentElement.classList.toggle("expanded");
        toggleExpanded(this);
    });

    // features
    // focus element
    // feature receives focus - activate feature
    $(".leaflet-pane .focus-el").on("focus", function(e) {
        e.stopPropagation();
        tour.features.get(this.getAttribute("data-feature")).activate(map, e);
    })
    // feature loses focus - deactivate feature
    .on("blur", function(e) {
        e.stopPropagation();
        tour.features.get(this.getAttribute("data-feature")).deactivate();
    })
    // feature focus element is "clicked" - open popup (if applicable)
    .on("click", function(e) {
        e.stopPropagation();
        tour.features.get(this.getAttribute("data-feature")).click();
    });
    // hover element
    // feature receives hover - activate feature
    $(".leaflet-pane .hover-el").on("mouseover", function(e) {
        e.stopPropagation();
        tour.features.get(this.getAttribute("data-feature")).activate(map, e);
    })
    // feature loses hover - maybe deactivate feature (function will check if tooltip is hovered over or feature has focus)
    .on("mouseout", function(e) {
        e.stopPropagation();
        tour.features.get(this.getAttribute("data-feature")).mouseoutFeature();
    })
    // hover element is clicked - open popup (if applicable), otherwise give feature focus
    .on("click", function(e) {
        e.stopPropagation();
        tour.features.get(this.getAttribute("data-feature")).click();
    });
    // tooltip
    // tooltip loses hover - maybe deactivate feature (function will check if hover element is hovered over or feature has focus)
    $(".leaflet-pane .leaflet-tooltip").on("mouseout", function(e) {
        e.stopPropagation();
        tour.features.get(this.getAttribute("data-feature")).mouseoutTooltip();
    })
    // tooltip is clicked - open popup (if applicable), otherwise give feature focus
    .on("click", function(e) {
        e.stopPropagation();
        tour.features.get(this.getAttribute("data-feature")).click();
    });
    // Esc is pressed - hide any active tooltips (tooltips should be dismissable without removing hover or focus)
    $(document).on("keyup", function(e) {
        if ((e.which || e.keyCode) === aria.KeyCode.ESC) {
            $(".leaflet-tooltip:not(.hide)").addClass("tmp-hide");
        }
    });

    // views
    // "show view" button is clicked - enter that view
    $(".show-view-btn").on("click", function() {
        enterView(this.getAttribute("data-view"));
    });
    // "go to view" button is clicked (mobile) - enter that view and switch to map view ("show view" button does not exist)
    // "go to view" button is clicked (desktop) - move focus to map and set hidden "skip link" at end of map so keyboard users can easily return
    $(".go-to-view-btn").on("click", function() {
        if (isMobile()) {
            enterView(this.getAttribute("data-view"));
            switchToMap(this.id);
        }
        else {
            $("#back-to-view-btn").attr("data-view", this.id).addClass("active");
            $("#map").focus();
        }
    });
    // "back to view" (skip link) is clicked - return focus to previously clicked "go to view" button
    $("#back-to-view-btn").on("click", function() {
        this.classList.remove("active");
        $("#" + this.getAttribute("data-view")).focus();
    });

    // other
    // reset button clicked - reset the default/tour view
    $(".reset-view-btn").on("click", function() {
        enterView('_tour');
    });
    // popup button clicked - open the corresponding popup
    $(".view-popup-btn").on("click", function() {
        let feature_id = this.getAttribute("data-feature");
        openDialog(feature_id + "-popup", this);
    });

    // scrolling (desktop) - make sure the modified doWindowScrollAction function is used and set scrolly_wait so it doesn't interfere with entering views (deals with initial triggering of scrollama, which is why it exists at all)
    $("#tour-wrapper").on("scroll", function() {
        if (!window_scroll_tick) {
            setTimeout(function() {
                scrolly_wait = 0;
                doWindowScrollAction();
                window_scroll_tick = false;
            }, 100);
        }
        window_scroll_tick = true;
    });

    // call theme method (there may be new links to modify)
    modifyLinks();
});
/**
 * Toggles classes to show/hide various non-legend elements and to show/hide the legend (on mobile).
 */
function toggleMobileLegend() {
    $("body").toggleClass("legend-active");
    $("#legend-wrapper").toggleClass("tour-desktop-only");
}

// Modify window.onscroll function from theme
/**
 * Modifies function from theme. Saves scroll position for mobile so that switching to map view doesn't reset the scroll position from content view and for both mobile and desktop so that refreshing the page doesn't reset scroll position.
 */
function doWindowScrollAction() {
    let scroll_top = document.getElementById("tour-wrapper").scrollTop;
    if (isMobile()) {
        if (page_state.save_scroll_pos) {
            // save scroll position for mobile (will be preserved when switching to map view and back)
            page_state.scroll_pos = scroll_top;
            checkMapToggleScroll();
        } else return;
    }
    // toggle back to top and save scroll position for session
    if (scroll_top > BACK_TO_TOP_Y) $("#back-to-top").addClass("active");
    else $("#back-to-top").removeClass("active");
    sessionStorage.setItem(window.location.pathname + '_scroll_top', scroll_top);
}

/**
 * The map-nav (toggle to switch to map view on mobile) should behave like a sticky element, but CSS "sticky" doesn't work. Whenever scroll changes (or the y-location of the default button position changes due to the nav being expanded/collapsed), check to see if the map-nav should be in its normal default position or absolute.
 */
function checkMapToggleScroll() {
    let height = $("header").get(0).offsetHeight + parseFloat($(".tour-wrapper").first().css("padding-top")) + document.getElementById("main-nav").offsetHeight;
    if (page_state.scroll_pos >= height) {
        $("#map-nav").addClass('scrolled');
    } else $('#map-nav').removeClass('scrolled');
}

// overwrite function from theme to do nothing
function expandNav() {
    // do nothing
}

/**
 * For mobile, switch to viewing the leaflet map. Called by the content toggle button and any show view buttons.
 * @param {String} focus_id - The id of the element calling the function, which will be saved so that focus can be returned to that element when switching back to content.
 */
function switchToMap(focus_id) {
    $("body").addClass("map-active");
    page_state.save_scroll_pos = false; // don't want scrolling to affect position when returning to content
    $("#map").focus();
    $("#map-toggle-btn").attr("data-focus", focus_id).attr("data-map-active", "true").text("Leave Map");
    if (tour_state.map_needs_adjusting) {
        adjustMap();
        tour_state.map_needs_adjusting = false;
    }
}

/**
 * For mobile, switch to viewing the narrative content. Called by the map toggle button.
 */
function switchToContent() {
    $("body").removeClass("map-active");
    // make sure scroll position is saved and remembered
    page_state.save_scroll_pos = true;
    document.getElementById("tour-wrapper").scrollTop = page_state.scroll_pos;
    // remember and return to previous focus (the id of the element used to switch to the map should have been saved in the content toggle button)
    let btn = $("#map-toggle-btn");
    $("#" + btn.attr("data-focus")).focus();
    btn.attr("data-focus", "").attr("data-map-active", "false").text("View Map");
    // collapse map toggle button
    let btn2 = $("#mobile-map-toggle-btn");
    btn2.parent().removeClass("expanded");
    btn2.removeAttr("aria-expanded");
}

// quick check for whether the page should be considered in "mobile" or "desktop" view
function isMobile() {
    return (window.innerWidth < 799);
}