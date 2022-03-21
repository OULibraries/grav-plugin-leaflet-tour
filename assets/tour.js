// ---------- Constants ---------- //
const BUFFER_WEIGHT = 21;
const FLY_TO_PADDING = [10, 10];

// and state
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
}

// ---------- Classes ---------- //
class TourFeature {
    // properties
    id; name; has_popup; alt_text; geometry;
    // leaflet objects
    layer; dataset;
    // status
    hide_view; hide_dataset;
    constructor(feature, datasets) {
        this.geometry = feature.geometry;
        let props = feature.properties;
        this.dataset = datasets.get(props.dataset);
        this.id = props.id;
        this.name = props.name;
        this.hide_view = false;
        this.hide_dataset = false;
    }
    // returns all html elements associated with the feature (jquery)
    get elements() {
        return $(this.tooltip).add(this.focus_element).add(this.hover_element);
    }
    get tooltip() {
        return this.layer.getTooltip().getElement();
    }
    get type() {
        return this.geometry.type;
    }
    get geoJson() {
        return({
            type: 'Feature',
            geometry: this.geometry,
            properties: { id: this.id }
        });
    }
    // called by leaflet layer when a feature is added
    addToLayer(layer) {
        this.layer = layer;
        // tooltip
        this.layer.bindTooltip(String('<div aria-hidden="true">' + this.name) + '</div>', {
            permanent: true,
            className: this.dataset.id,
        });
        // this just comes from the code qgis2web generated
        labels.push(this.layer);
        addLabel(this.layer, totalMarkers);
        this.layer.added = true;
        totalMarkers++;
    }
    // to be called after adding layer when document is ready
    modify() {
        this.hover_element.id = this.id;
        this.hover_element.classList.add(this.dataset.id);
        this.hover_element.setAttribute("data-feature", this.id);
        this.focus_element.setAttribute("data-feature", this.id);
        this.hover_element.classList.add("hover-el");
        this.focus_element.classList.add("focus-el");
    }
}
class TourPoint extends TourFeature {
    constructor(feature, datasets) {
        super(feature, datasets);
    }
    get elements() {
        return super.elements.add(this.layer._shadow);
    }
    get hover_element() { return this.layer._icon; }
    get focus_element() { return this.layer._icon; }
    // each point may have slightly different options that the generic dataset icon options
    get icon_options() {
        // don't modify dataset's options!
        let options = { ...this.dataset.icon };
        return options;
    }
    addToLayer(layer) {
        super.addToLayer(layer);
    }
}

// ---------- Tour and Tour Setup Functions ---------- //
function createMap() {
    return L.map('map', {
        zoomControl: true,
        attributionControl: false,
    });
}
function createTileLayer() {
    let options = tour_options.tile_server;
    let layer;
    if (options.name) {
        layer = new L.StamenTileLayer(options.name, {
            minZoom: 1,
            maxZoom: 18,
            maxNativeZoom: 13
        });
    } else {
        layer = new L.tileLayer(options.url);
    }
    map.addLayer(layer);
    return layer;
}
function createFeatureLayer() {
    layer = L.geoJson(null, {
        pointToLayer: function(json, latlng) {
            let feature = tour.features.get(json.properties.id);
            return L.marker(latlng, {
                icon: new L.Icon(feature.icon_options),
                riseOnHover: true
            });
        },
        onEachFeature: function(json, layer) {
            tour.features.get(json.properties.id).addToLayer(layer);
        },
        interactive: true,
    });
    map.addLayer(layer);
    return layer;
}
function createFeature(value, key, map) {
    let feature;
    switch (value.geometry.type) {
        case "Point":
            feature = new TourPoint(value, tour.datasets);
            break;
    }
    if (feature) {
        map.set(key, feature);
        tour.feature_layer.addData(feature.geoJson);
        // also add feature ref to dataset
        feature.dataset.features.push(feature);
    }
}
function adjustMap() {
    map.invalidateSize();
    // TODO: This should invoke the current view bounds
    map.flyToBounds(tour.feature_layer.getBounds(), { padding: FLY_TO_PADDING, animate: false });
}
function resetTourLabels() {
    resetLabels([ tour.feature_layer ]);
}

// ---------- Map/Tour Initialization ---------- //
const map = createMap();
tour.tile_layer = createTileLayer();
tour.datasets = tour_datasets;
tour.feature_layer = createFeatureLayer();
tour.features = tour_features;
tour.features.forEach(createFeature);

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
map.on("layeradd", resetTourLabels);
map.on("layerremove", resetTourLabels);
map.on("zoomend", function() {
    resetTourLabels();
    // todo: reset basemaps
    // todo: adjust zoom buttons?
    $(".leaflet-control-zoom a").removeAttr("aria-disabled");
    $(".leaflet-disabled").attr("aria-disabled", true);
});

// todo: enter view

// ---------- General Setup ---------- //
$(document).ready(function() {
    adjustMap(); // todo: enter view here or above
    
    // features
    for (let feature of tour.features.values()) {
        feature.modify();
    }

    // move map controls for more sensible DOM order
    let controls = $(".leaflet-control-container");
    controls.remove();
    $("#map").prepend(controls);

    // interaction
    $("#nav-toggle-btn").on("click", checkMapToggleScroll);
    $("#mobile-map-toggle-btn").on("click", function() {
        $(this).parent().toggleClass('expanded');
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
    if (isMobile() && page_state.save_scroll_pos) page_state.scroll_pos = window.scrollY;
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
    page_state.save_scroll_pos = false; // don't want scrolling to affect position when returning to content
    $("#map").focus(); // TODO: Ensure that this is the sensible/expected decision
    $("#map-toggle-btn").attr("data-focus", focus_id).attr("data-map-active", "true").text("Leave Map");
    // todo: adjust map if needed
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
    window.scrollTo(0, page_state.scroll_pos);
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