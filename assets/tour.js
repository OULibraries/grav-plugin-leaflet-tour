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
    adjust_labels: false,
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
    toggleHidden() {
        let hide = (this.hide_dataset || this.hide_view);
        this.elements.attr("aria-hidden", hide).css("display", (hide ? "none" : "block"));
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
class TourPath extends TourFeature {
    buffer; svg;
    focus_element;
    constructor(feature, datasets) {
        super(feature, datasets);
        // if weight is below buffer cutoff, set buffer true
        if (this.dataset.path.weight < BUFFER_WEIGHT) {
            // if polygon, only set buffer true if there is also no fill
            let type = this.type.toLowerCase();
            if (type === 'linestring' || type === 'multilinestring') this.buffer = true;
            else if (!(this.dataset.path.fill ?? true)) this.buffer = true;
        }
    }
    get elements() {
        // if buffer, the hover_element will be the buffer, but svg will still store the original svg, so must also be returned
        if (this.buffer) return super.elements.add(this.svg);
        else return super.elements;
    }
    get hover_element() { 
        if (this.buffer) return this.buffer._path;
        else return this.layer._path;
    }
    addToBufferLayer(layer) {
        this.buffer = layer;
    }
    modify() {
        // tmp
        this.alt_text = this.id;
        // create the focus element
        let focus_element = $('<img class="sr-only" id="' + this.id + '-focus" tabindex="0" alt="' + this.alt_text + '">');
        $(".leaflet-marker-pane").append(this.focus_element);
        this.focus_element = focus_element.get(0);
        super.modify();
        // deal with non-point tooltips - without this, tooltips associated with polygons that are much smaller than the starting view may be bound way too far off
        map.flyToBounds(this.layer.getBounds(), { animate: false });
        this.layer.closeTooltip();
        this.layer.openTooltip();
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
    let layer = L.geoJson(null, {
        pointToLayer: function(json, latlng) {
            let feature = tour.features.get(json.properties.id);
            return L.marker(latlng, {
                icon: new L.Icon(feature.icon_options),
                riseOnHover: true
            });
        },
        style: function(json) {
            return tour.features.get(json.properties.id).dataset.path;
        },
        onEachFeature: function(json, layer) {
            tour.features.get(json.properties.id).addToLayer(layer);
        },
        interactive: true,
    });
    map.addLayer(layer);
    return layer;
}
function createBufferLayer() {
    let layer = L.geoJson(null, {
        style: function(json) {
            return { stroke: true, weight: BUFFER_WEIGHT, opacity: 0, fill: false };
        },
        onEachFeature: function(json, layer) {
            tour.features.get(json.properties.id).addToBufferLayer(layer);
        }
    });
    map.addLayer(layer);
    return layer;
}
function createFeature(value, key, map) {
    let feature;
    if (value.geometry.type === 'Point') {
        feature = new TourPoint(value, tour.datasets);
    } else {
        feature = new TourPath(value, tour.datasets);
    }
    if (feature) {
        map.set(key, feature);
        tour.feature_layer.addData(feature.geoJson);
        // also add feature ref to dataset
        feature.dataset.features.push(feature);
        // also add buffer if applicable
        if (feature.buffer) tour.buffer_layer.addData(feature.geoJson);
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
tour.buffer_layer = createBufferLayer();
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
    map.invalidateSize();
    map.flyToBounds(tour.feature_layer.getBounds(), { padding: FLY_TO_PADDING, animate: false });
    
    // features
    for (let feature of tour.features.values()) {
        feature.modify();
    }

    adjustMap(); // todo: enter view here or above

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
    // legend
    $("#legend-toggle-btn").on("click", function() {
        $("#" + this.getAttribute("data-toggles")).toggleClass("minimized");
        togglePopupButton(this);
    });
    $("#mobile-legend-btn").on("click", toggleMobileLegend);
    $("#legend-close-btn").on("click", toggleMobileLegend);
    $(".legend-checkbox").on("input", function() {
        this.setAttribute("aria-checked", this.checked);
        toggleDataset(this.value, !this.checked);
    })

    $("map-reset-btn").on("click", function() {
        // todo
    });
});

function toggleDataset(id, hide) {
    tour.datasets.get(id).features.forEach(function(feature) {
        feature.hide_dataset = hide;
        feature.toggleHidden();
        if (isMobile()) tour_state.adjust_labels = true;
    });
    resetTourLabels();
}

function toggleMobileLegend() {
    $("#map-nav").toggleClass("hide");
    $("#legend-wrapper").toggleClass("desktop-only");
    $("#map").toggleClass("hide");
    if (tour_state.adjust_labels) {
        tour_state.adjust_labels = false;
        resetTourLabels();
    }
}

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