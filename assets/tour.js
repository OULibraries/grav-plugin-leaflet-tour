// constants
const SCROLLAMA_OFFSET = 0.33; // note: There are a couple things that rely on this number, so be careful if modifying
const SCROLLAMA_DEBUG = false; // Never modify this - setting to true makes it impossible to scroll
const SCROLLAMA_ENTER_VIEW_WAIT =  500; // half a second

const FLY_TO_PADDING = [10, 10];

// tour state
const tour = {
    tile_layer: null,
    feature_layer: null,
}
const page_state = {
    save_scroll_pos: true, // save the (vertical) scroll position
    scroll_pos: 0, // the saved (vertical) scroll position
}
const tour_state = {
    map_needs_adjusting: true,
    animation: true,
    view: null,
    basemaps: [], // active basemaps
}

function adjustMap() {
    map.invalidateSize();
    view = tour_state.view ?? tour.views.get('_tour');
    if ((bounds = view.bounds)) map.flyToBounds(bounds, { padding: FLY_TO_PADDING, animate: false });
}

// ---------- Map/Tour Initialization ---------- //
const map = createMap(tour_options);
map.fitBounds([[1,1],[2,2]]);
if (tour_options.show_map_location_in_url) hash = new L.Hash(map);
tour.tile_layer = createTileServer(tour_options.tile_server);
map.addLayer(tour.tile_layer);
tour.basemaps = createBasemaps(tour_basemaps);
tour.datasets = tour_datasets;
tour.point_layer = createPointLayer();
map.addLayer(tour.point_layer);
tour.shape_layer = createShapeLayer();
map.addLayer(tour.shape_layer);
tour.features = tour_features;
tour.features.forEach(createFeature);
tour.views = tour_views;
setupViews(tour.views, tour.features, tour.basemaps);

// ---------- Scrollama ---------- //
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
// function printMapState() {
//     console.log('zoom: ' + map.getZoom() + ', center: ' + parseFloat(map.getCenter().lat.toFixed(4)) + ', ' + parseFloat(map.getCenter().lng.toFixed(4)));
// }
// map.on("click", printMapState);
// print click location
// map.on('click', function(ev){
//     var latlng = map.mouseEventToLatLng(ev.originalEvent);
//     console.log(latlng.lat + ', ' + latlng.lng);
// });

map.on("zoomend", function() {
    adjustBasemaps(tour_state.view, tour_state, map, tour.tile_layer);
    // adjust zoom buttons
    let zoom_out = document.getElementById("zoom-out-btn");
    let zoom_in = document.getElementById("zoom-in-btn");
    zoom_out.disabled = false;
    zoom_in.disabled = false;
    if (map.getZoom() <= map.getMinZoom()) zoom_out.disabled = true;
    else if (map.getZoom() >= map.getMaxZoom()) zoom_in.disabled = true;
    // save zoom level
    sessionStorage.setItem(window.location.pathname + '_zoom', map.getZoom());
});
map.on("moveend", function() {
    // save center
    let loc = window.location.pathname;
    sessionStorage.setItem(loc + '_lng', map.getCenter().lng);
    sessionStorage.setItem(loc + '_lat', map.getCenter().lat);
});

let window_scroll_tick = false;

// ---------- General Setup ---------- //
$(document).ready(function() {
    let loc = window.location.pathname;
    let lng = parseFloat(sessionStorage.getItem(loc + '_lng'));
    let lat = parseFloat(sessionStorage.getItem(loc + '_lat'));
    let zoom = parseInt(sessionStorage.getItem(loc + '_zoom'));

    // set tile server attribution if needed
    setTileServerAttr($("#server-attribution"), tour.tile_layer);
    // let section = $("#server-attribution");
    // if (!section.html()) {
    //     let a = tour.tile_layer.options.attribution;
    //     if (a) section.html("<span>Tile Server: </span>" + a);
    // }
    if (!isMobile()) {
        // make sure "tour" size and last view size are sufficient for all views to be enterable via scrollama
        // "tour" view
        let top_height = document.getElementById("top").offsetHeight + document.getElementById("main-nav").offsetHeight + document.getElementById("tour").offsetHeight;
        let target = (window.innerHeight * 2) / 5;
        let diff = target - top_height;
        if (diff > 0) {
            $("#tour").css("padding-bottom", diff + "px");
        }
        // last view
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
    // return to previous scroll position if applicable
    let scroll_top = sessionStorage.getItem(loc + '_scroll_top');
    document.getElementById("tour-wrapper").scrollTop = scroll_top ?? 0;
    // check for saved view, use '_tour' if no valid view is saved
    let view_id = sessionStorage.getItem('tour_view') ?? '_tour';
    if (!tour.views.get(view_id)) view_id = '_tour';

    // go to view bounds or saved bounds - need to set map center and zoom before modifying features
    if (lng && lat) {
        if (!zoom) zoom = map.getZoom();
        map.flyTo([lat, lng], zoom, { animate: false });
    }
    else {
        map.flyToBounds(tour.views.get(view_id).bounds, { padding: FLY_TO_PADDING, animate: false, duration: 0 });
        if (zoom) map.setZoom(zoom, { animate: false });
    }
    
    // features
    // for (let feature of tour.features.values()) {
    //     feature.modify();
    // }
    modifyFeatures(tour.features);

    // set view - no flyTo, but need to handle other aspects of setting view
    enterView(view_id, false);

    // move map controls for more sensible DOM order
    let controls = $(".leaflet-control-container");
    controls.remove();
    $("#map").prepend(controls);

    // interaction
    $("#nav-toggle-btn").on("click", checkMapToggleScroll);
    $("#map-toggle-btn").on("click", function() {
        if (this.getAttribute("data-map-active") === "false") switchToMap(this.id);
        else switchToContent();
    });
    $("#map-animation-toggle").on("click", function() {
        let checked = this.getAttribute("aria-checked") === 'true' ? false : true;
        this.setAttribute("aria-checked", checked);
        tour_state.animation = checked;
        sessionStorage.setItem('animation', checked);
    });
    // load animation settings
    if (sessionStorage.getItem('animation') === 'false') $("#map-animation-toggle").click();
    // legend (and zoom buttons)
    $("#zoom-out-btn").on("click", function() {
        map.zoomOut();
    });
    $("#zoom-in-btn").on("click", function() {
        map.zoomIn();
    });
    $("#legend-toggle-btn").on("click", function() {
        $("#" + this.getAttribute("aria-controls")).toggleClass("minimized");
        toggleExpanded(this);
    });
    $("#mobile-legend-btn").on("click", toggleMobileLegend);
    $("#legend-close-btn").on("click", toggleMobileLegend);
    $(".legend-checkbox").on("input", function() {
        toggleDataset(this.value, tour.datasets, !this.checked);
    })
    $("#legend-basemaps-toggle").on("click", function() {
        this.parentElement.parentElement.classList.toggle("expanded");
        toggleExpanded(this);
    })
    // features
    $(".leaflet-pane .hover-el").on("click", function(e) {
        e.stopPropagation();
        getFeature(this).openPopup();
    }).on("mouseover", function(e) {
        e.stopPropagation();
        getFeature(this).activate(map, e);
    }).on("mouseout", function(e) {
        e.stopPropagation();
        // only deactivate if feature does not have focus
        let feature = getFeature(this);
        if (!(feature.focus_element === document.activeElement))feature.deactivate();
    });
    $(".leaflet-pane .focus-el").on("keypress", function(e) {
        e.stopPropagation();
        if (e.which === 32 || e.which === 13) {
            getFeature(this).openPopup();
        }
    }).on("focus", function(e) {
        e.stopPropagation();
        getFeature(this).activate(map, e);
    }).on("blur", function(e) {
        e.stopPropagation(e);
        getFeature(this).deactivate();
    });
    // views
    $(".show-view-btn").on("click", function() {
        enterView(this.getAttribute("data-view"));
    });
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
    $("#back-to-view-btn").on("click", function() {
        this.classList.remove("active");
        $("#" + this.getAttribute("data-view")).focus();
    });
    $(".reset-view-btn").on("click", function() {
        enterView('_tour');
    });
    $(".view-popup-btn").on("click", function() {
        let feature_id = this.getAttribute("data-feature");
        openDialog(feature_id + "-popup", this);
        $(this).one("focus", function(e) {
            // when focus returns, make sure the feature is activated
            tour.features.get(this.getAttribute("data-feature")).activate(map, e);
            $(this).one("blur", function() {
                // when focus leaves deactivate the feature
                tour.features.get(this.getAttribute("data-feature")).deactivate();
            });
        });
    });
    // scrolling (desktop)
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

function enterView(id, fly_to = true) {
    let view = tour.views.get(id);
    if (!view) return;
    // If the new view is differnt, exit the old one
    if (tour_state.view && (tour_state.view.id !== view)) exitView();
    // set new view
    tour_state.view = view;
    toggleViewFeatures(view, tour.features, true);
    // if applicable, fly to view bounds
    if (fly_to) {
        if (view.bounds && !isMobile()) {
            map.flyToBounds(view.bounds, { padding: FLY_TO_PADDING });
        }
        else if (isMobile()) {
            map.flyToBounds(view.bounds, { padding: FLY_TO_PADDING, animate: false });
            tour_state.map_needs_adjusting = true; // TODO: maybe?
        }
    }
    adjustBasemaps(view, tour_state, map, tour.tile_layer);
    sessionStorage.setItem('tour_view', id);
}
function exitView() {
    // unhide previously hidden features
    toggleViewFeatures(tour_state.view, tour.features, false);
    sessionStorage.setItem('tour_view', '');
}

function toggleMobileLegend() {
    $("body").toggleClass("legend-active");
    $("#legend-wrapper").toggleClass("tour-desktop-only");
}

// Modify window.onscroll function from theme
function doWindowScrollAction() {
    let scroll_top = document.getElementById("tour-wrapper").scrollTop;
    if (isMobile()) {
        if (page_state.save_scroll_pos) {
            // save scroll position for mobile
            page_state.scroll_pos = scroll_top;
            checkMapToggleScroll();
        } else return;
    }
    // toggle back to top and save scroll position for session
    if (scroll_top > BACK_TO_TOP_Y) $("#back-to-top").addClass("active");
    else $("#back-to-top").removeClass("active");
    sessionStorage.setItem(window.location.pathname + '_scroll_top', scroll_top);
}

function checkMapToggleScroll() {
    // make sure that map-nav does not become sticky/absolute until the main navigation has been passed (whether or not main nav is expanded)
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
 * 
 * @param focus_id - (String) The id of the element calling the function, which will be saved so that focus can be returned to that element when switching back to content.
 */
function switchToMap(focus_id) {
    $("body").addClass("map-active");
    page_state.save_scroll_pos = false; // don't want scrolling to affect position when returning to content
    $("#map").focus(); // TODO: Ensure that this is the sensible/expected decision
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

function getFeature(el) {
    return tour.features.get(el.getAttribute("data-feature"));
}
function isMobile() {
    return (window.innerWidth < 799);
}