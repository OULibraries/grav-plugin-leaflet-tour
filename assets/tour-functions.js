/**
 * This document is for: Only functions and classes (and constants, I guess), particularly only ones that need to be tested (and only functions related to leaflet). It should not contain code that will run without a function call. Functions will be called by either tour.js or tour-test.js.
*/

const BUFFER_WEIGHT = 21;
const DEFAULT_TILE_SERVER = 'OpenTopoMap';
const SCROLLAMA_OFFSET = 0.33; // note: There are a couple things that rely on this number, so be careful if modifying
const SCROLLAMA_DEBUG = false; // Never modify this - setting to true makes it impossible to scroll
const SCROLLAMA_ENTER_VIEW_WAIT =  500; // half a second

const FLY_TO_PADDING = [10, 10];

/**
 * @property {String} id - unique identifier for feature
 * @property {String} name
 * @property {Boolean} has_popup - if the feature has associated popup content
 * @property {Object} dataset - data provided from Tour
 * @property {Object} coordinates - Leaflet coordinates, LatLng form (transformed from GeoJSON)
 * @property {Boolean} hide_view - feature should be hidden, not in the current view
 * @property {Boolean} hide_dataset - feature should be hidden, dataset toggled off in legend
 * @property {Object} focus_element - HTML object that will receive focus
 * @property {Object} hover_layer - Leaflet layer for the object that will receive hover: its element could be the same as focus_element, could be a child of focus_element, could be totally different - implementation specific
 * @property {Object} tooltip - Leaflet layer for the object's tooltip
 */
class TourFeature {
    id; name; has_popup; dataset; coordinates; hide_view; hide_dataset; focus_element; hover_layer; tooltip;
    // todo
    constructor(feature, datasets, map) {
        // set properties
        this.id = feature.properties.id;
        this.name = feature.properties.name;
        this.has_popup = feature.properties.has_popup;
        this.dataset = datasets.get(feature.properties.dataset);
        this.coordinates = this.coordsToLatLngs(feature.geometry);
        // and state
        this.hide_view = false;
        this.hide_dataset = false;
        // build
    }
    // todo some getters for convenience
    get hover_element() { return this.hover_layer.getElement(); }
    get elements() { return $(this.focus_element).add(this.hover_element); }
    get alt_text() {
        return this.name + (this.dataset.legend_summary ? ", " + this.dataset.legend_summary : "");
    }
    // because GeoJSON arrays are [lng, lat] and latlngs are [lat, lng] - don't ask me why
    coordsToLatLngs(geometry) {} // to be extended by child classes
    // todo
    createFocusElement(map) {
        // create basic html element
        if (this.has_popup) {
            this.focus_element = document.createElement("button");
            // accessibility info: type, aria-haspopup
            this.focus_element.setAttribute("type", "button");
            this.focus_element.setAttribute("aria-haspopup", "true");
        }
        else {
            this.focus_element = document.createElement("div");
            // make focusable
            this.focus_element.setAttribute("tabindex", "0");
        }
        // add id, classes, reference to feature, etc.
        this.focus_element.id = this.id + "-focus";
        this.focus_element.classList.add("focus-el");
        // implementation specific: add sr-only class and set text
        this.focus_element.classList.add("sr-only");
        this.focus_element.text = this.alt_text;
        // implementation specific: add element to "focusPane"
        map.getPane("focusPane").appendChild(this.focus_element);

        // implementation specific: give class "leaflet-pane", add to map as pane
        // this.focus_element.classList.add("leaflet-pane");
        // map._panes[this.id] = this.focus_element; // adds to map object
        // map.getPane("markerPane").appendChild(this.focus_element); // adds to html
        // implementation specific: other considered option: give class sr-only, add text, and add to an already existing map pane
    }
    createFeatureLayer(feature, map) {} // to be extended by child classes
    // todo
    bindTooltip(layer) {
        // todo: possibly remove the aria-hidden, if it works best on the pane as a whole
        layer.bindTooltip(String('<div class="tooltip" aria-hidden="true">' + this.name) + '</div>', {
            permanent: true,
            className: "hide", // hidden by default (display: none)
            sticky: false,
            interactive: true,
        });
        // have to open tooltip to actually create and add the element
        layer.openTooltip();
        this.tooltip = layer.getTooltip();
        if (this.has_popup) this.tooltip.getElement().classList.add("has-popup");
    }
    // Interaction Methods
    // todo
    toggleHidden() {
        // only show element if it is not hidden by any method, otherwise hide it
        // using class "hide" will set any elements to display none
        if (this.hide_dataset || this.hide_view) $(this.elements).addClass("hide");
        else $(this.elements).removeClass("hide");
    }
    // call when hover element is clicked
    click() {
        // handles click event - will either set focus on feature or open popup dialog
        if (this.has_popup) openDialog(this.id + "-popup", this.focus_element);
        else this.focus_element.focus();
    }
    // call when feature receives hover/focus
    activate(map, e) {
        // do nothing if feature is hidden
        if (this.hide_dataset || this.hide_view) return;
        // open tooltip if it isn't already
        if (this.tooltip.getElement().classList.contains("hide")) this.openTooltip(map, e);
        // make sure feature is visible (only if event called because focus)
        if (this.focus_element === document.activeElement) this.checkFeatureVisible(map, e);
        // give feature "active" class
        this.hover_element.classList.add("active");
    }
    checkFeatureVisible(map, e) {} // to be extended by child classes
    openTooltip(map, e) {
        this.tooltip.getElement().classList.remove("hide"); // make it visible
        this.hover_layer.openTooltip(); // reset tooltip
        // to be extended by child classes (if needed)
    }
    deactivate() {
        // hide tooltip
        this.tooltip.getElement().classList.add("hide");
        // remove possible tmp hide class
        this.tooltip.getElement().classList.remove("tmp-hide");
        // remove "active" class
        this.hover_element.classList.remove("active");
        // to be extended by child classes (if needed)
    }
    // to be called when the hover element experiences mouseout
    mouseoutFeature() {
        // ignore if feature has focus or if tooltip has hover
        if ((this.focus_element === document.activeElement) || $(this.tooltip.getElement()).is(":hover")) return;
        else this.deactivate();
    }
    // to be called when the tooltip experiences mouseout
    mouseoutTooltip() {
        // ignore if feature has focus or hover element has hover
        if ((this.focus_element === document.activeElement) || $(this.hover_element).is(":hover")) return;
        else this.deactivate();
    }
}
class TourPoint extends TourFeature {
    constructor(feature, datasets, map) {
        super(feature, datasets, map);
        // creates standalone html element that will be used as focus element, either added as a pane or to a pane or whatever depending on implementation decisions; also adds the element to the map
        this.createFocusElement(map);
        // creates feature layer(s), adds to map, and modifies as needed - some of the specifics will depend on implementation decisions
        this.createFeatureLayer(feature, map);
        // create tooltip and open it for the first time (binds it to hover_layer)
        this.bindTooltip(this.hover_layer);

        // make sure all elements reference feature (hover, focus, tooltip)
        this.focus_element.setAttribute("data-feature", this.id);
        this.hover_element.setAttribute("data-feature", this.id);
        this.tooltip.getElement().setAttribute("data-feature", this.id);
        if (this.has_popup) this.hover_element.classList.add("has-popup");
    }
    get elements() {
        return super.elements.add(this.hover_layer._shadow);
    }
    coordsToLatLngs(geometry) {
        return L.GeoJSON.coordsToLatLng(geometry.coordinates);
    }
    createFeatureLayer(feature, map) {
        // icon marker
        this.hover_layer = L.marker(this.coordinates, {
            icon: L.icon({ ...this.dataset.icon, id: this.id + '-hover' }),
            alt: "", // implementation specific, ignore images
            // pane: this.id, // implementation specific, use default pane
        });
        this.hover_layer.addTo(map);
        // add class
        this.hover_layer.getElement().classList.add("hover-el");
        // remove tabindex
        this.hover_layer.getElement().removeAttribute("tabindex");
    }
    checkFeatureVisible(map, e) {
        let point = this.hover_layer.getLatLng();
        if (!map.getBounds().contains(point)) map.panTo(point, { animate: true });
    }
}
class TourShape extends TourFeature {
    path_layer; stroke_layer;
    constructor(feature, datasets, map) {
        super(feature, datasets, map);
        // creates standalone html element that will be used as focus element, either added as a pane or to a pane or whatever depending on implementation decisions; also adds the element to the map
        this.createFocusElement(map);
        // creates feature layer(s), adds to map, and modifies as needed - some of the specifics will depend on implementation decisions
        this.createFeatureLayer(feature, map);
        // create tooltip and open it for the first time (binds it to hover_layer)
        this.bindTooltip(this.hover_layer);

        // make sure all elements reference feature (hover, focus, tooltip)
        this.focus_element.setAttribute("data-feature", this.id);
        this.hover_element.setAttribute("data-feature", this.id);
        this.tooltip.getElement().setAttribute("data-feature", this.id);
        if (this.has_popup) this.hover_element.classList.add("has-popup");
    }
    get elements() {
        // redundancy if hover and path aren't the same doesn't matter
        let elements = super.elements.add(this.path_layer.getElement()).add(this.hover_layer.getElement());
        if (this.stroke_layer) return elements.add(this.stroke_layer.getElement());
        else return elements;
    }
    coordsToLatLngs(geometry) {
        let nesting;
        switch (geometry.type.toLowerCase()) {
            case "linestring":
                nesting = 0;
                break;
            case "multilinestring":
            case "polygon":
                nesting = 1;
                break;
            case "multipolygon":
                nesting = 2;
                break;
            default:
                console.log('uh-oh'); // TODO tmp
        }
        return L.GeoJSON.coordsToLatLngs(geometry.coordinates, nesting);
    }
    createFeatureLayer(feature, map) {
        // determine if buffer is needed
        let buffer = null;
        // need buffer if stroke/border weight less than const
        if (this.dataset.path.weight < BUFFER_WEIGHT) buffer = BUFFER_WEIGHT;
        // need buffer if weight greater than or equal, but has border (use the path weight)
        else if (this.dataset.stroke) buffer = this.dataset.path.weight;

        // determine if feature is line or polygon
        let line = feature.geometry.type.toLowerCase().includes('linestring');

        // create path layer
        this.path_layer = this.createShape(line, { ...this.dataset.path });
        this.path_layer.addTo(map);
        // maybe create stroke layer
        if (this.dataset.stroke) {
            this.stroke_layer = this.createShape(line, { ...this.dataset.stroke });
            this.stroke_layer.addTo(map);
        }
        // maybe create buffer layer - if so, set as hover layer, otherwise set path layer
        if (buffer) {
            this.hover_layer = this.createShape(line, {
                stroke: true,
                color: 'transparent',
                weight: buffer,
                opacity: 0,
                fill: this.dataset.path.fill,
                fillColor: 'transparent',
                fillOpacity: 0,
            });
            this.hover_layer.addTo(map);
        } else {
            this.hover_layer = this.path_layer;
        }
        // add class to hover element
        this.hover_element.classList.add("hover-el");
        // implementation specific: ignore svg
        // // add svg accessibility info (since not ignoring), role, title, aria-labelledby
        // let svg = this.hover_element.parentElement.parentElement; // nesting: svg - g - path
        // let title = document.createElement("title");
        // title.innerHTML = this.alt_text;
        // title.id = this.id + "-title";
        // svg.insertBefore(title, svg.firstChild); // place title as first element in svg
        // svg.setAttribute("role", "img");
        // svg.setAttribute("aria-labelledby", this.id + "-title");
    }
    createShape(line, options) {
        if (line) return L.polyline(this.coordinates, options);
        else return L.polygon(this.coordinates, options);
    }
    checkFeatureVisible(map, e) {
        // after opening tooltip, the tooltip location should be within viewport - if not, feature is not visible
        if (!map.getBounds().contains(this.tooltip.getLatLng())) {
            // pan to feature
            map.panTo(this.hover_layer.getCenter(), { animate: true });
            // save feature to store state (so timeout function can reference it)
            tour_state.tmp_feature = this;
            // set timeout: wait until map finishes panning before checking if feature is fully in view
            setTimeout(function() {
                let feature = tour_state.tmp_feature;
                tour_state.tmp_feature = null;
                // all of feature not in viewport? - zoom out
                if (!map.getBounds().contains(feature.hover_layer.getBounds())) map.flyToBounds(feature.hover_layer.getBounds(), { animate: true, noMoveStart: true });
            }, 250); // .25s = animation duration
            // reopen tooltip, just in case
            this.hover_layer.openTooltip();
        }
    }
    openTooltip(map, e) {
        super.openTooltip(map, e);
        // this is a good place to update style, too, I guess
        this.path_layer.setStyle(this.dataset.active_path);
        if (this.stroke_layer) this.stroke_layer.setStyle(this.dataset.active_stroke);
        // current tooltip location not in viewport?
        if (!map.getBounds().contains(this.tooltip.getLatLng())) {
            let latlng = null;
            // activation from hover event?
            if (this.focus_element !== document.activeElement) {
                // tooltip moves to point closest to mouse location (note: simply moving tooltip to mouse location doesn't work well because of the buffer element)
                latlng = this.getTmpLatLng(map.mouseEventToLayerPoint(e));
            }
            // activation from focus event?
            else {
                // move tooltip to closest layer point to map center
                try {
                    latlng = this.getTmpLatLng(map.latLngToLayerPoint(map.getCenter()));
                } catch (e) {} // do nothing
            }
            // if new coords generated for tooltip, reset it
            if (latlng) this.hover_layer.openTooltip(latlng);
        }
    }
    getTmpLatLng(layer_point) {
        let point = this.hover_layer.closestLayerPoint(layer_point);
        let latlng = map.layerPointToLatLng([point['x'], point['y']]);
        if (map.getBounds().contains(latlng)) return latlng;
        else return null;
    }
    deactivate() {
        super.deactivate();
        this.path_layer.setStyle(this.dataset.path);
        if (this.stroke_layer) this.stroke_layer.setStyle(this.dataset.stroke);
    }

}

// ---------- Tour Setup Functions ---------- //

/**
 * @param {Object} options tour_options from Tour/template
 * @returns L.map
 */
function createMap(options) {
    let m = L.map('map', {
        zoomControl: false,
        attributionControl: false,
        maxBoundsViscosity: 0.5,
    });
    if (max = options.max_zoom) m.setMaxZoom(max);
    if (min = options.min_zoom) m.setMinZoom(min);
    if (bounds = options.max_bounds) m.setMaxBounds(bounds);
    // go ahead and give the map some bounds - may prevent some errors/bugs
    m.fitBounds([[1,1],[2,2]]);
    // implementation specific: add "focusPane" to map
    m.createPane("focusPane");
    return m;
}
function createTileServer(options) {
    if (options.provider) {
        try {
            let server_options = {};
            if (id = options.id) {
                server_options.id = id;
                server_options.variant = id;
            }
            if (variant = options.variant) server_options.variant = variant;
            if (key = options.key) {
                server_options.key = key;
                server_options.apiKey = key;
                server_options.accessToken = key;
            }
            if (key = options.apiKey) server_options.apiKey = key;
            if (key = options.accessToken) server_options.accessToken = key;
            return new L.tileLayer.provider(options.provider, server_options);
        } catch (error) {
            // use default tile server instead
            return new L.tileLayer.provider(DEFAULT_TILE_SERVER, {});
        }
    } else {
        return new L.tileLayer(options.url);
    }
}
function setTileServerAttr(section, server) {
    if (section && !section.html()) {
        let a = server.options.attribution;
        if (a) section.html("<span>Tile Server: </span>" + a);
    }
    return section;
}
// requires map, tour
function createFeature(value, key, features) {
    let feature;
    if (value.geometry.type === 'Point') feature = new TourPoint(value, tour.datasets, map);
    else feature = new TourShape(value, tour.datasets, map);
    if (feature) {
        features.set(key, feature);
        // add reference to dataset (for easy toggling)
        feature.dataset.features.push(feature);
    }
}
function createBasemaps(basemap_data) {
    let basemaps = new Map();
    for (let [key, value] of Object.entries(basemap_data)) {
        // make sure image exists
        // $.get(value.url).done(function() {
            basemaps.set(key, L.imageOverlay(value.url, value.bounds, value.options));
        // });
    }
    return basemaps;
}
/**
 * @param {array} feature_ids
 * @param {array} default_bounds 
 */
 function createBounds(start_bounds, feature_ids, features, default_bounds) {
    if (start_bounds) {
        // either calculate from distance or pass on the already set bounds
        if (start_bounds.distance) {
            return L.latLng(start_bounds.lat, start_bounds.lng).toBounds(start_bounds.distance * 2);
        }
        else return start_bounds;
    }
    else if (feature_ids.length > 0) {
    // set bounds based on feature ids
    let group = new L.FeatureGroup();
    for (let id of feature_ids) {
        let feature = features.get(id);
        group.addLayer(feature.hover_layer);
    }
    return group.getBounds();
    }
    else {
        return default_bounds;
    }
}
function setupViews(views, features, basemaps) {
    default_bounds = createBounds(null, Array.from(features.keys()), features, null);
    for (let [id, view] of views) {
        // replace view basemap files with references to the actual basemaps
        let view_basemaps = [];
        for (let file of view.basemaps ?? []) {
            let basemap = basemaps.get(file);
            if (basemap) view_basemaps.push(basemap);
        }
        view.basemaps = view_basemaps;
        // set bounds
        view.bounds = createBounds(view.bounds, view.features ?? [], features, default_bounds);
        views.set(id, view);
    }
}

// ---------- Other Functions... ---------- //

function adjustBasemaps(view, state, map, server) {
    if (!view) return;
    // remove currently active basemaps
    for (let basemap of state.basemaps) {
        map.removeLayer(basemap);
    }
    state.basemaps = [];
    // use view and zoom level to determine which basemaps to add
    for (let basemap of view.basemaps) {
        if (map.getZoom() >= (basemap.options.min_zoom ?? 0) && map.getZoom() <= (basemap.options.max_zoom ?? 500)) {
            state.basemaps.push(basemap);
            map.addLayer(basemap);
        }
    }
    // tile server
    if (!state.basemaps.length || !view.remove_tile_server) map.addLayer(server);
    else map.removeLayer(server);
}
function adjustMap() {
    map.invalidateSize();
    view = tour_state.view ?? tour.views.get('_tour');
    if ((bounds = view.bounds)) map.flyToBounds(bounds, { padding: FLY_TO_PADDING, animate: false });
}
function handleMapZoom() {
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
}
function handleMapMove() {
    // save center
    let loc = window.location.pathname;
    sessionStorage.setItem(loc + '_lng', map.getCenter().lng);
    sessionStorage.setItem(loc + '_lat', map.getCenter().lat);
}

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
function toggleViewFeatures(view, features, hide) {
    if (view.only_show_view_features && (view.features.length > 0)) {
        features.forEach(function(feature) {
            if (!view.features.includes(feature.id)) {
                feature.hide_view = hide;
                feature.toggleHidden();
            }
        });
    }
}
function toggleDataset(id, datasets, hide) {
    datasets.get(id).features.forEach(function(feature) {
        feature.hide_dataset = hide;
        feature.toggleHidden();
    });
}

// a couple of utility functions I might want for testing or something

// function printMapState() {
//     console.log('zoom: ' + map.getZoom() + ', center: ' + parseFloat(map.getCenter().lat.toFixed(4)) + ', ' + parseFloat(map.getCenter().lng.toFixed(4)));
// }
// map.on("click", printMapState);
// print click location
// map.on('click', function(ev){
//     var latlng = map.mouseEventToLatLng(ev.originalEvent);
//     console.log(latlng.lat + ', ' + latlng.lng);
// });