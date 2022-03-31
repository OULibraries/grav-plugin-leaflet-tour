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
    view: null,
    basemaps: [], // active basemaps
}

// ---------- Classes ---------- //
class TourFeature {
    // properties
    id; name; has_popup; geometry;
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
        this.has_popup = props.has_popup;
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
    get alt_text() {
        return this.name + (this.dataset.legend_summary ? ", " + this.dataset.legend_summary : "") + (this.has_popup ? ", open popup" : "");
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
        let hover = this.hover_element;
        let focus = this.focus_element;
        hover.id = this.id;
        hover.classList.add(this.dataset.id);
        hover.classList.add("hover-el");
        hover.classList.add(this.has_popup ? "has-popup" : "no-popup");
        hover.setAttribute("data-feature", this.id);
        focus.classList.add("focus-el");
        focus.setAttribute("data-feature", this.id);
        if (this.has_popup) {
            focus.setAttribute("role", "button");
            focus.setAttribute("aria-haspopup", "true");
        }
    }
    toggleHidden() {
        let hide = (this.hide_dataset || this.hide_view);
        this.elements.attr("aria-hidden", hide).css("display", (hide ? "none" : "block"));
    }
    openPopup() {
        if (this.has_popup) openDialog(this.id+"-popup", this.focus_element);
        else this.focus_element.focus();
    }
    activate() {
        this.tooltip.classList.add("active");
    }
    deactivate() {
        this.tooltip.classList.remove("active");
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
        // let options = { ...this.dataset.icon };
        // return options;
        return this.dataset.icon;
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
        // create the focus element
        let focus;
        if (this.has_popup) {
            focus = $('<button class="sr-only">' + this.alt_text + '</button>');
        } else {
            focus = $('<div class="sr-only" tabindex="0">' + this.alt_text + '</div>');
        }
        focus.attr("id", this.id + "-focus");
        this.focus_element = focus.get(0);
        $(".leaflet-marker-pane").append(this.focus_element); // TODO: This should go in a better location
        super.modify();
        // deal with non-point tooltips - without this, tooltips associated with polygons that are much smaller than the starting view may be bound way too far off
        map.flyToBounds(this.layer.getBounds(), { animate: false });
        this.layer.closeTooltip();
        this.layer.openTooltip();
    }
    activate() {
        super.activate();
        this.layer.setStyle(this.dataset.active_path);
    }
    deactivate() {
        super.deactivate();
        this.layer.setStyle(this.dataset.path);
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
function createBasemap(value, key, map) {
    let layer = L.imageOverlay(value.url, value.bounds, value.options);
    map.set(key, layer);
}
function createFeatureLayer() {
    let layer = L.geoJson(null, {
        pointToLayer: function(json, latlng) {
            let feature = tour.features.get(json.properties.id);
            return L.marker(latlng, {
                icon: new L.Icon(feature.icon_options),
                alt: feature.alt_text,
                riseOnHover: true,
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
function setupViews(views) {
    // deal with tour "view" first (id = 'tour')
    tour_bounds = views.get('tour').bounds;
    if (!tour_bounds) tour_bounds = tour.feature_layer.getBounds();
    // the rest (repeats tour as well, which is fine)
    views.forEach(function(view) {
        // replace view basemap files with references to the actual basemaps
        let basemaps = [];
        for (let file of view.basemaps) {
            basemaps.push(tour.basemaps.get(file));
        }
        view.basemaps = basemaps;
        // make sure that each view has bounds
        if (!view.bounds) {
            view.bounds = setupBounds(view.features, tour_bounds);
        }
    });
}
function setupBounds(feature_ids, bounds) {
    if (feature_ids.length > 0) {
        let group = new L.FeatureGroup();
        for (let id of feature_ids) {
            group.addLayer(tour.features.get(id).layer);
        }
        return group.getBounds();
    }
    return bounds;
}
function adjustMap() {
    map.invalidateSize();
    // TODO: This should invoke the current view bounds
    map.flyToBounds(tour.feature_layer.getBounds(), { padding: FLY_TO_PADDING, animate: false });
}
function adjustBasemaps(view) {
    if (!view) return;
    // remove currently active basemaps
    for (let basemap of tour_state.basemaps) {
        map.removeLayer(basemap);
    }
    tour_state.basemaps = [];
    // use view and zoom level to determine which basemaps to add
    for (let basemap of view.basemaps) {
        if (map.getZoom() >= basemap.options.min_zoom && map.getZoom() <= basemap.options.max_zoom) {
            tour_state.basemaps.push(basemap);
            map.addLayer(basemap);
        }
    }
    // tile server
    if (!tour_state.basemaps.length || !view.remove_tile_server) map.addLayer(tour.tile_layer);
    else map.removeLayer(tour.tile_layer);
}
function resetTourLabels() {
    resetLabels([ tour.feature_layer ]);
}

// ---------- Map/Tour Initialization ---------- //
const map = createMap();
tour.tile_layer = createTileLayer();
tour.basemaps = tour_basemaps;
tour.basemaps.forEach(createBasemap);
tour.datasets = tour_datasets;
tour.feature_layer = createFeatureLayer();
tour.buffer_layer = createBufferLayer();
tour.features = tour_features;
tour.features.forEach(createFeature);
tour.views = tour_views;
setupViews(tour.views);

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
        if (!isMobile()) {
            scrolly_temp_view = e.element.id;
            // use timeout function so that if multiple views are scrolled through at once, only the last view will be truly entered
            setTimeout(function() {
                enterView(scrolly_temp_view);
            }, SCROLLAMA_ENTER_VIEW_WAIT);
        }
    });
}

// map "on" functions
map.on("layeradd", resetTourLabels);
map.on("layerremove", resetTourLabels);
map.on("zoomend", function() {
    resetTourLabels();
    adjustBasemaps(tour_state.view);
    // adjust zoom buttons
    $(".leaflet-control-zoom a").removeAttr("aria-disabled");
    $(".leaflet-disabled").attr("aria-disabled", true);
});

let window_scroll_tick = false;

// ---------- General Setup ---------- //
$(document).ready(function() {
    map.invalidateSize();
    let view = tour.views.get('tour');
    if (view) map.flyToBounds(view.bounds, { padding: FLY_TO_PADDING, animate: false });
    
    // features
    for (let feature of tour.features.values()) {
        feature.modify();
    }

    adjustMap();
    enterView('tour');

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
    $("#legend-basemaps-toggle").on("click", function() {
        this.parentElement.classList.toggle("expanded");
        toggleDisplay(this);
    })
    // features
    $(".leaflet-pane .hover-el").on("click", function(e) {
        e.stopPropagation();
        getFeature(this).openPopup();
    }).on("mouseover", function() {
        getFeature(this).activate();
    }).on("mouseout", function() {
        // only deactivate if feature does not have focus
        let feature = getFeature(this);
        if (!(feature.focus_element === document.activeElement))feature.deactivate();
    });
    $(".leaflet-pane .focus-el").on("keypress", function() {
        if (e.which === 32 || e.which === 13) {
            getFeature(this).openPopup();
        }
    }).on("focus", function() {
        getFeature(this).activate();
    }).on("blur", function() {
        getFeature(this).deactivate();
    });
    // views
    $(".show-view-btn").on("click", function() {
        enterView(this.getAttribute("data-view"));
    });
    $(".go-to-view-btn").on("click", function() {
        enterView(this.getAttribute("data-view"));
        if (isMobile()) switchToMap(this.id);
        else {
            $("#back-to-view-btn").attr("href", "#" + this.id).addClass("active");
            $("#map").focus();
        }
    });
    $("#back-to-view-btn").on("click", function() {
        this.classList.remove("active");
    });
    $("map-reset-btn").on("click", function() {
        // todo
    });

    // scrolling (desktop)
    $("#tour-wrapper").on("scroll", function() {
        if (!window_scroll_tick) {
            setTimeout(function() {
                doWindowScrollAction();
                window_scroll_tick = false;
            }, 100);
        }
        window_scroll_tick = true;
    });
});

// TODO: This should really move to the theme depending on how I deal with aria-haspopup
function toggleDisplay(btn) {
    btn.setAttribute("aria-expanded", btn.getAttribute("aria-expanded") == "true" ? "false" : "true");
}

function enterView(id) {
    let view = tour.views.get(id);
    if (!view) return;
    // If the new view is differnt, exit the old one
    if (tour_state.view && (tour_state.view.id !== view)) exitView();
    // set new view
    tour_state.view = view;
    toggleViewFeatures(view, true);
    // if applicable, fly to view bounds
    if (view.bounds && !isMobile()) {
        map.flyToBounds(view.bounds, { padding: FLY_TO_PADDING });
    }
    // TODO: invalidate map size on mobile?
    else if (isMobile()) {
        // map.invalidateSize();
        tour_state.map_needs_adjusting = true; // TODO: maybe?
    }
    adjustBasemaps(view);
    resetTourLabels();
}
function exitView() {
    // unhide previously hidden features
    toggleViewFeatures(tour_state.view, false);
}
function toggleViewFeatures(view, hide) {
    // console.log(view);
    if (view.only_show_view_features && (view.features.length > 0)) {
        // console.log('hiding');
        tour.features.forEach(function(feature) {
            if (!view.features.includes(feature.id)) {
                feature.hide_view = hide;
                feature.toggleHidden();
            }
        });
    }
}

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
    if (isMobile()) {
        toggleBackToTop();
        if (page_state.save_scroll_pos) page_state.scroll_pos = window.scrollY;
        // save scroll position for mobile
        checkMapToggleScroll();
    } else {
        // have to check a different element for scroll position for back to top
        if (document.getElementById("tour-wrapper").scrollTop > BACK_TO_TOP_Y) $("#back-to-top").addClass("active");
        else $("#back-to-top").removeClass("active");
    }
}

function checkMapToggleScroll() {
    let height = $("header").get(0).offsetHeight + parseFloat($(".tour-wrapper").first().css("padding-top")) + document.getElementById("main-nav").offsetHeight;
    if (window.scrollY >= height) {
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