// constants
const SCROLLAMA_OFFSET = 0.33; // note: There are a couple things that rely on this number, so be careful if modifying
const SCROLLAMA_DEBUG = false; // Never modify this - setting to true makes it impossible to scroll
const SCROLLAMA_ENTER_VIEW_WAIT =  500; // half a second
const BUFFER_WEIGHT = 21;
const DEFAULT_TILE_SERVER = 'OpenTopoMap';

tour_padding = parseInt(tour_padding);
if (isNaN(tour_padding)) tour_padding = 10;
const FLY_TO_PADDING = [tour_padding, tour_padding];

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
        // let sticky = (this.type != 'Point');
        // tooltip
        // TODO: aria-hidden here, or on the whole pane?
        this.layer.bindTooltip(String('<div aria-hidden="true">' + this.name) + '</div>', {
            permanent: false,
            className: this.dataset.id,
            // opacity: 0,
            sticky: false,
        });
        // this just comes from the code qgis2web generated
        // labels.push(this.layer);
        // addLabel(this.layer, totalMarkers);
        // this.layer.added = true;
        // totalMarkers++;
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
    activate(e) {
        // this.tooltip.classList.add("active");
        this.layer.openTooltip();
    }
    deactivate() {
        // this.tooltip.classList.remove("active");
        this.layer.closeTooltip();
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
    activate(e) {
        super.activate(e);
        if (!map.getBounds().contains(this.layer.getLatLng())) {
            map.panTo(this.layer.getLatLng(), { animate: true });
        }
    }
}
class TourPath extends TourFeature {
    buffer; svg;
    stroke; // only separate from regular path if the feature has a border
    focus_element;
    tmp_tooltip;
    constructor(feature, datasets) {
        super(feature, datasets);
        if (this.dataset.stroke) this.stroke = true;
        // if weight is below buffer cutoff, set buffer true
        if ((this.dataset.path.weight < BUFFER_WEIGHT)) {
            // if polygon, only set buffer true if there is also no fill
            // let type = this.type.toLowerCase();
            // if (this.is_line) this.buffer = true;
            // else if (!(this.dataset.path.fill ?? true)) this.buffer = true;
            this.buffer = true;
        }
        else if (this.stroke) {
            // if there is stroke and border, even if weight is very large, still want buffer for other reasons
            this.buffer = true;
            this.buffer_weight = this.dataset.path.weight;
        }
    }
    get is_line() {
        let type = this.type.toLowerCase();
        return (type === 'linestring' || type === 'multilinestring');
    }
    get elements() {
        // if buffer, the hover_element will be the buffer, but svg will still store the original svg, so must also be returned
        let elements = super.elements;
        if (this.stroke) elements = elements.add(this.stroke._path);
        if (this.buffer) elements = elements.add(this.layer._path);
        return elements;
    }
    get hover_element() { 
        if (this.buffer) return this.buffer._path;
        else return this.layer._path;
    }
    addToStrokeLayer(layer) {
        this.stroke = layer;
    }
    addToBufferLayer(layer) {
        this.buffer = layer;
    }
    modify() {
        // create the focus element
        let focus;
        if (this.has_popup) {
            focus = $('<button type="button" class="sr-only">' + this.alt_text + '</button>');
        } else {
            focus = $('<div class="sr-only" tabindex="0">' + this.alt_text + '</div>');
        }
        focus.attr("id", this.id + "-focus");
        this.focus_element = focus.get(0);
        $(".leaflet-marker-pane").append(this.focus_element); // TODO: This should go in a better location
        super.modify();
        // deal with non-point tooltips - without this, tooltips associated with polygons that are much smaller than the starting view may be bound way too far off
        // map.flyToBounds(this.layer.getBounds(), { animate: false });
        // this.layer.closeTooltip();
        // this.layer.openTooltip();
    }
    activate(e) {
        // open initial tooltip
        if (this.tmp_tooltip) this.layer.openTooltip(this.tmp_tooltip);
        else this.layer.openTooltip();
        this.layer.setStyle(this.dataset.active_path);
        if (this.stroke) this.stroke.setStyle(this.dataset.active_stroke);

        if (!map.getBounds().contains(this.layer.getTooltip().getLatLng())) {
            this.layer.openTooltip(); // reset tooltip
            // if hover: move tooltip to mouse location (by modifying offset)
            if (this.focus_element !== document.activeElement) {
                this.tmp_tooltip = map.mouseEventToLatLng(e);
            } 
            else {
                // if bounding box is in view, try moving tooltip to the closest layer point
                if (map.getBounds().intersects(this.layer.getBounds())) {
                    try {
                        let point = this.layer.closestLayerPoint(map.latLngToLayerPoint(map.getCenter()));
                        let latlng = map.layerPointToLatLng([point['x'], point['y']]);
                        if (map.getBounds().contains(latlng)) this.tmp_tooltip = latlng;
                    } catch (e) {
                        // do nothing
                    }
                }
                if (!this.tmp_tooltip) {
                    // either bounding box is not in view or it is, but the closest layer point is not - pan and possibly zoom to feature
                    map.panTo(this.layer.getCenter(), { animate: true });
                    tour_state.tmp_feature = this;
                    setTimeout(function() {
                        let feature = tour_state.tmp_feature;
                        tour_state.tmp_feature = null;
                        if (!map.getBounds().contains(feature.layer.getBounds())) map.flyToBounds(feature.layer.getBounds(), { animate: true, noMoveStart: true });
                    }, 250); // wait for .25s (animation duration) before checking
                }
            }

            if (this.tmp_tooltip) {
                this.layer.openTooltip(this.tmp_tooltip);
            }
        } 
    }
    deactivate() {
        super.deactivate();
        this.layer.setStyle(this.dataset.path);
        if (this.stroke) this.stroke.setStyle(this.dataset.stroke);
        // if tooltip offset was previously modified, reset it
        // if (this.old_offset) {
        //     this.layer.getTooltip().options.offset = this.old_offset;
        //     this.old_offset = null;
        // }
        this.tmp_tooltip = null;
    }
}

// ---------- Tour and Tour Setup Functions ---------- //
function createMap() {
    let m = L.map('map', {
        zoomControl: false,
        attributionControl: false,
    });
    if (max = tour_options.max_zoom) m.setMaxZoom(max);
    if (min = tour_options.min_zoom) m.setMinZoom(min);
    if (tour_options.show_map_location_in_url) hash = new L.Hash(m);
    if (bounds = tour_options.max_bounds) m.setMaxBounds(bounds);
    return m;
}
function createTileLayer() {
    let options = tour_options.tile_server;
    let layer;
    if (options.provider) {
        // settings/options
        let settings = {};
        // set options - don't know for sure what value is needed, but seems like providing extra wouldn't hurt
        let id = options.id;
        if (id) {
            settings.id = id;
            settings.variant = id;
        }
        let key = options.key;
        if (key) {
            settings.key = key;
            settings.apiKey = key;
            settings.accessToken = key;
        }
        try {
            layer = new L.tileLayer.provider(options.provider, settings);
        } catch (error) {
            // something went wrong, so use the default tile server instead
            layer = new L.tileLayer.provider(DEFAULT_TILE_SERVER, {});
        }
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
function createStrokeLayer() {
    let layer = L.geoJson(null, {
        style: function(json) {
            return tour.features.get(json.properties.id).dataset.stroke;
        },
        onEachFeature: function(json, layer) {
            tour.features.get(json.properties.id).addToStrokeLayer(layer);
        }
    });
    map.addLayer(layer);
    return layer;
}
function createBufferLayer() {
    let layer = L.geoJson(null, {
        style: function(json) {
            let feature = tour.features.get(json.properties.id);
            let weight = feature.buffer_weight ?? BUFFER_WEIGHT;
            if (tour.features.get(json.properties.id).is_line) {
                return { stroke: true, weight: weight, opacity: 0, fill: false };
            }
            else return { stroke: true, weight: weight, opacity: 0, fill: true, fillColor: 'transparent' };
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
        // also add stroke if applicable
        if (feature.stroke) tour.stroke_layer.addData(feature.geoJson);
        // also add buffer if applicable
        if (feature.buffer) tour.buffer_layer.addData(feature.geoJson);
    }
}
function setupViews(views) {
    // deal with tour "view" first (id = 'tour')
    tour_bounds = views.get('tour').bounds;
    if (!tour_bounds) tour_bounds = tour.feature_layer.getBounds();
    else if (tour_bounds.distance) {
        // I think needs to be multiplied by two to match expectations
        tour_bounds = L.latLng(tour_bounds.lat, tour_bounds.lng).toBounds(tour_bounds.distance * 2);
    }
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
        } else if (view.bounds.distance) {
            view.bounds = L.latLng(view.bounds.lat, view.bounds.lng).toBounds(view.bounds.distance * 2);
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
    view = tour_state.view ?? tour.views.get('tour');
    if ((bounds = view.bounds)) map.flyToBounds(bounds, { padding: FLY_TO_PADDING, animate: false });
    // else map.flyToBounds(tour.feature_layer.getBounds(), { padding: FLY_TO_PADDING, animate: false });
    // resetTourLabels();
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

// ---------- Map/Tour Initialization ---------- //
const map = createMap();
tour.tile_layer = createTileLayer();
tour.basemaps = tour_basemaps;
tour.basemaps.forEach(createBasemap);
tour.datasets = tour_datasets;
tour.feature_layer = createFeatureLayer();
tour.stroke_layer = createStrokeLayer();
tour.buffer_layer = createBufferLayer();
tour.features = tour_features;
tour.features.forEach(createFeature);
tour.views = tour_views;
setupViews(tour.views);

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
    }).onStepEnter(function(e) {
        if (scrolly_wait) {
            scrolly_wait--;
        }
        else if (!isMobile() && tour_state.animation) {
            scrolly_temp_view = e.element.id;
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

map.on("zoomend", function() {
    adjustBasemaps(tour_state.view);
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
    let section = $("#server-attribution");
    if (!section.html()) {
        let a = tour.tile_layer.options.attribution;
        if (a) section.html("<span>Tile Server: </span>" + a);
    }
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
        // return to previous scroll position if applicable
        let scroll_top = sessionStorage.getItem('scroll_top');
        document.getElementById("tour-wrapper").scrollTop = scroll_top ?? 0;
    }
    // check for saved view, use 'tour' if no valid view is saved
    let view_id = sessionStorage.getItem('tour_view') ?? 'tour';
    if (!tour.views.get(view_id)) view_id = 'tour';

    // map.invalidateSize();
    // go to view bounds or saved bounds - need to set map center and zoom before modifying features
    if (lng && lat) map.flyTo([lat, lng], zoom ?? map.getZoom(), { animate: false });
    else {
        map.flyToBounds(tour.views.get(view_id).bounds, { padding: FLY_TO_PADDING, animate: false, duration: 0 });
        if (zoom) map.setZoom(zoom, { animate: false });
    }
    
    // features
    for (let feature of tour.features.values()) {
        feature.modify();
    }

    // set view - no flyTo, but need to handle other aspects of setting view
    enterView(view_id, false);

    // move map controls for more sensible DOM order
    let controls = $(".leaflet-control-container");
    controls.remove();
    $("#map").prepend(controls);

    // interaction
    $("#nav-toggle-btn").on("click", checkMapToggleScroll);
    $("#mobile-map-toggle-btn").on("click", function() {
        $(this).parent().toggleClass('expanded');
        toggleExpanded(this);
    });
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
        // this.setAttribute("aria-checked", this.checked);
        toggleDataset(this.value, !this.checked);
    })
    $("#legend-basemaps-toggle").on("click", function() {
        this.parentElement.classList.toggle("expanded");
        toggleExpanded(this);
    })
    // features
    $(".leaflet-pane .hover-el").on("click", function(e) {
        e.stopPropagation();
        getFeature(this).openPopup();
    }).on("mouseover", function(e) {
        e.stopPropagation();
        getFeature(this).activate(e);
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
        getFeature(this).activate(e);
    }).on("blur", function(e) {
        e.stopPropagation(e);
        getFeature(this).deactivate();
    });
    // views
    $(".show-view-btn").on("click", function() {
        enterView(this.getAttribute("data-view"));
    });
    $(".go-to-view-btn").on("click", function() {
        // enterView(this.getAttribute("data-view"));
        if (isMobile()) {
            enterView(this.getAttribute("data-view"));
            switchToMap(this.id);
        }
        else {
            $("#back-to-view-btn").attr("href", "#" + this.id).addClass("active");
            $("#map").focus();
        }
    });
    $("#back-to-view-btn").on("click", function() {
        this.classList.remove("active");
    });
    $(".reset-view-btn").on("click", function() {
        enterView('tour');
    });
    $(".view-popup-btn").on("click", function() {
        let feature_id = this.getAttribute("data-feature");
        openDialog(feature_id + "-popup", this);
        $(this).one("focus", function() {
            // when focus returns, make sure the feature is activated
            tour.features.get(this.getAttribute("data-feature")).activate();
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

// TODO: This should really move to the theme depending on how I deal with aria-haspopup
function toggleExpanded(btn) {
    btn.setAttribute("aria-expanded", btn.getAttribute("aria-expanded") == "true" ? "false" : "true");
}

function enterView(id, fly_to = true) {
    let view = tour.views.get(id);
    if (!view) return;
    // If the new view is differnt, exit the old one
    if (tour_state.view && (tour_state.view.id !== view)) exitView();
    // set new view
    tour_state.view = view;
    toggleViewFeatures(view, true);
    // if applicable, fly to view bounds
    if (fly_to) {
        if (view.bounds && !isMobile()) {
            map.flyToBounds(view.bounds, { padding: FLY_TO_PADDING });
        }
        // TODO: invalidate map size on mobile?
        else if (isMobile()) {
            map.flyToBounds(view.bounds, { padding: FLY_TO_PADDING, animate: false });
            // map.invalidateSize();
            tour_state.map_needs_adjusting = true; // TODO: maybe?
        }
    }
    adjustBasemaps(view);
    sessionStorage.setItem('tour_view', id);
}
function exitView() {
    // unhide previously hidden features
    toggleViewFeatures(tour_state.view, false);
    sessionStorage.setItem('tour_view', '');
}
function toggleViewFeatures(view, hide) {
    if (view.only_show_view_features && (view.features.length > 0)) {
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
    });
}

function toggleMobileLegend() {
    $("#map-nav").toggleClass("hide");
    $("#legend-wrapper").toggleClass("desktop-only");
    $("#map").toggleClass("hide");
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
        let scroll_top = document.getElementById("tour-wrapper").scrollTop;
        if (scroll_top > BACK_TO_TOP_Y) $("#back-to-top").addClass("active");
        else $("#back-to-top").removeClass("active");
        // save scroll position for session
        sessionStorage.setItem('scroll_top', scroll_top);
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