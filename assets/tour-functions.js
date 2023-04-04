/**
 * This document is for: Only functions and classes (and constants) directly related to map (including features). It should not contain code that will run without a function call. Functions will be called by either tour.js or tour-test.js.
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
    /**
     * Set some essential properties. Child classes will extend further.
     * @param {Object} feature json from tour
     * @param {Array} datasets array of json objects from tour
     * @param {Object} map L.map
     */
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
    /**
     * For convenience, returns the HTML <path> for the hover layer (the visible feature that can be hovered over on the map)
     * @returns {Object} HTML <path> element
     */
    get hover_element() { return this.hover_layer.getElement(); }
    /**
     * For convenience, returns all HTML elements associated with the feature. Useful for enabling or disabling a feature on the map. Child classes will extend further if needed.
     * @return {Object} jQuery array of HTML elements
     */
    get elements() { return $(this.focus_element).add(this.hover_element); }
    /**
     * Builds the correct alternative text for the feature to provide to assistive technology.
     * @return {String} alt text
     */
    get alt_text() {
        return this.name + (this.dataset.legend_summary ? ", " + this.dataset.legend_summary : "");
    }
    // because GeoJSON arrays are [lng, lat] and latlngs are [lat, lng]
    coordsToLatLngs(geometry) {} // to be extended by child classes
    /**
     * Create a button or div for the basic element, add necessary properties, then add the element to the correct pane.
     * @param {Object} map L.map
     */
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
        this.focus_element.id = this.id + "-focus-el";
        this.focus_element.classList.add("focus-el");
        // add sr-only class and set text
        this.focus_element.classList.add("sr-only");
        this.focus_element.innerHTML = this.alt_text;
        // add element to "focusPane"
        map.getPane("focusPane").appendChild(this.focus_element);
    }
    // function to do the main work of creating the feature
    createFeatureLayer(feature, map) {} // to be extended by child classes
    /**
     * Creates the tooltip element
     * @param {Object} layer Leaflet layer (for the feature)
     */
    bindTooltip(layer) {
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
    /**
     * Check if the element is hidden due to not being in the current view or due to its dataset being unselected in the legend. Hide the element if either is the case. Only display it if it is not hidden for any reason.
     */
    toggleHidden() {
        // using class "hide" will set any elements to display none
        if (this.hide_dataset || this.hide_view) $(this.elements).addClass("hide");
        else $(this.elements).removeClass("hide");
    }
    /**
     * Called when the hover element is clicked. Will either set focus on the feature or open modal dialog with popup content.
     */
    click() {
        if (this.has_popup) openDialog(this.id + "-popup", this.focus_element);
        else this.focus_element.focus();
    }
    /**
     * Opens tooltip, makes sure feature is visible, and modifies class. Called when feature receives hover/focus.
     * @param {Object} map L.map
     * @param {Event} e from the mouseover or focus event
     */
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
    /**
     * Makes the feature's tooltip visible. Possibly changes its location if needed. Requires additional logic for shape features.
     * @param {Object} map L.map
     * @param {Event} e from the mouseover or focus event
     */
    openTooltip(map, e) {
        this.tooltip.getElement().classList.remove("hide"); // make it visible
        this.hover_layer.openTooltip(); // reset tooltip
        // to be extended by child classes (if needed)
    }
    /**
     * Called when active feature loses focus and hover. Resets classes so the tooltip will be hidden and feature styles will revert to standard.
     */
    deactivate() {
        // hide tooltip
        this.tooltip.getElement().classList.add("hide");
        // remove possible tmp hide class (from pressing esc to hide the tooltip while feature was active)
        this.tooltip.getElement().classList.remove("tmp-hide");
        // remove "active" class
        this.hover_element.classList.remove("active");
        // to be extended by child classes (if needed)
    }
    /**
     * Called when the hover element experiences mouseout. Checks to see if the feature should actually be deactivated (i.e. does not have focus, tooltip does not have hover) - purpose of checking is so moving the mouse to the tooltip or moving the mouse over and off a focused feature does not unexpectedly hide the tooltip.
     */
    mouseoutFeature() {
        // ignore if feature has focus or if tooltip has hover
        if ((this.focus_element === document.activeElement) || $(this.tooltip.getElement()).is(":hover")) return;
        else this.deactivate();
    }
    /**
     * Called when the tooltip element experiences mouseout. Checks to see if the feature should actually be deactivated (i.e. does not have focus or hover) - purpose of checking is so moving the mouse to the tooltip then back or moving the mouse to the tooltip of a focused feature and then away does not unexpectedly hide the tooltip.
     */
    mouseoutTooltip() {
        // ignore if feature has focus or hover element has hover
        if ((this.focus_element === document.activeElement) || $(this.hover_element).is(":hover")) return;
        else this.deactivate();
    }
}
/**
 * Specific modifications for point features
 */
class TourPoint extends TourFeature {
    /**
     * Super sets some essential properties. Calls methods to set up focus element, visible feature, and tooltip. Also makes sure all elements have necessary attributes.
     * @param {Object} feature json from tour
     * @param {Array} datasets array of json objects from tour
     * @param {Object} map L.map
     */
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
    /**
     * For convenience, returns all HTML elements associated with the feature. Useful for enabling or disabling a feature on the map.
     * @return {Object} jQuery array of HTML elements - focus element (div or button), hover element (img), maybe shadow (img)
     */
    get elements() {
        return super.elements.add(this.hover_layer._shadow);
    }
    // because GeoJSON arrays are [lng, lat] and latlngs are [lat, lng]
    coordsToLatLngs(geometry) {
        return L.GeoJSON.coordsToLatLng(geometry.coordinates);
    }
    /**
     * Creates the visible icon, adds it to the map, and makes sure it has the correct attributes.
     * @param {Object} feature json from tour
     * @param {Object} map L.map
     */
    createFeatureLayer(feature, map) {
        // icon marker
        this.hover_layer = L.marker(this.coordinates, {
            icon: L.icon({ ...this.dataset.icon, id: this.id + '-hover' }),
            alt: "", // ignore images
        });
        this.hover_layer.addTo(map);
        // add class
        this.hover_layer.getElement().classList.add("hover-el");
        // remove tabindex
        this.hover_layer.getElement().removeAttribute("tabindex");
    }
    /**
     * Checks if the point is within the current viewport's bounds. If not, pans the map to center on it.
     * @param {Object} map L.map
     * @param {Event} e from the mouseover or focus event
     */
    checkFeatureVisible(map, e) {
        let point = this.hover_layer.getLatLng();
        if (!map.getBounds().contains(point)) map.panTo(point, { animate: true });
    }
}
/**
 * Specific modifications for shape features (lines, polygons). Adds two new properties so visible feature can have up to three components. At the bottom is the border and fill. Then the stroke goes on top of that (for features with no border, the bottom layer will contain stroke instead and this layer will not be added). Then an invisible layer goes on top of all of that (and possibly extends outward) - this last layer is how people will actually interact with the feature.
 * @property {Object} path_layer - Leaflet layer for fill (if it exists) and either stroke or border
 * @property {Object} stroke_layer - Leaflet layer for stroke (path_layer will have border)
 */
class TourShape extends TourFeature {
    path_layer; stroke_layer;
    /**
     * Super sets some essential properties. Calls methods to set up focus element, visible feature, and tooltip. Also makes sure all elements have necessary attributes.
     * @param {Object} feature json from tour
     * @param {Array} datasets array of json objects from tour
     * @param {Object} map L.map
     */
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
    /**
     * For convenience, returns all HTML elements associated with the feature. Useful for enabling or disabling a feature on the map.
     * @return {Object} jQuery array of HTML elements - focus element (div or button), hover element (path), maybe path layer (path), maybe stroke layer (path)
     */
    get elements() {
        let elements = super.elements.add(this.path_layer.getElement()).add(this.hover_layer.getElement());
        if (this.stroke_layer) return elements.add(this.stroke_layer.getElement());
        else return elements;
    }
    /**
     * Required because GeoJSON arrays are [lng, lat] and latlngs are [lat, lng]. Leaflet function requires the correct level of nesting to be provided.
     * @param {Object} geometry GeoJSON
     * @returns Leaflet object
     */
    coordsToLatLngs(geometry) {
        let nesting = 0;
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
                console.log('failed to convert geometry to LatLngs: ' + geometry);
        }
        return L.GeoJSON.coordsToLatLngs(geometry.coordinates, nesting);
    }
    /**
     * Creates the visible layers (paths). Determines if a buffer/top-level hover layer is necessary. Determines if a stroke layer is necessary. Creates path layer, and possibly stroke and buffer/hover layers. Adds all layers to map.
     * @param {Object} feature json from tour
     * @param {Object} map L.map
     */
    createFeatureLayer(feature, map) {
        // determine if buffer is needed
        let buffer = null;
        // need buffer if stroke/border weight less than const - this will make the hover layer larger than the visible feature, providing a wider target for people to hover over with the mouse
        if (this.dataset.path.weight < BUFFER_WEIGHT) buffer = BUFFER_WEIGHT;
        // even if path weight is high, any feature with a border also needs a buffer - this is because features with border will have both path and stroke layers, so a different layer needs to go on top of them and be the interactive layer (otherwise moving mouse across border and stroke will cause issues)
        else if (this.dataset.stroke) buffer = this.dataset.path.weight;

        // determine if feature is line or polygon
        let line = feature.geometry.type.toLowerCase().includes('linestring');

        // create path layer
        this.path_layer = this.createShape(line, { ...this.dataset.path });
        this.path_layer.addTo(map);
        // maybe create stroke layer (dataset info from tour will include stroke if the feature has a border)
        if (this.dataset.stroke) {
            this.stroke_layer = this.createShape(line, { ...this.dataset.stroke });
            this.stroke_layer.addTo(map);
        }
        // maybe create buffer layer (based on decision made earlier) - if so, set as hover layer, otherwise set path layer as the hover layer
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
    }
    /**
     * Simple helper function so I don't have to write an if statement for each layer when making them in createFeatureLayer
     * @param {Boolean} line true if feature is a line, false if polygon
     * @param {Object} options layer options to set
     * @returns Leaflet object (layer), either L.polyline or L.polygon
     */
    createShape(line, options) {
        if (line) return L.polyline(this.coordinates, options);
        else return L.polygon(this.coordinates, options);
    }
    /**
     * Checks tooltip point. If the point is not contained within the current viewport, then none of the feature is visible (openTooltip is called first, and will set the tooltip to a valid location in the viewport if one exists). Pans the map to center of the feature. Then checks that the whole feature is visible in the viewport. If not, zooms out until it is.
     * @param {Object} map L.map
     * @param {Event} e from the mouseover or focus event
     */
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
    /**
     * Opens the tooltip and sets path/stroke active styles. If the current tooltip point is not in the viewport, checks for a valid location to move it to: If feature was activated from hover, tries to attach tooltip to a location on the edge of the feature where the mouse entered it. If feature was activated from focus, tries to attach tooltip to the point closest to the center of the viewport. If new valid coordinates were found, resets the tooltip, opening it at the new point.
     * @param {Object} map L.map
     * @param {Event} e from the mouseover or focus event
     */
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
    /**
     * Finds the closest layer point on the feature from the provided point (provided point may not be part of or within the feature). Changes the layer point to a latLng, and returns it if that point is within the current viewport.
     * @param {Object} layer_point from L.map.xToLayerPoint function
     * @returns L.latLng or null
     */
    getTmpLatLng(layer_point) {
        let point = this.hover_layer.closestLayerPoint(layer_point);
        let latlng = map.layerPointToLatLng([point['x'], point['y']]);
        if (map.getBounds().contains(latlng)) return latlng;
        else return null;
    }
    /**
     * Extends super - reverts path/stroke styles from active to regular
     */
    deactivate() {
        super.deactivate();
        this.path_layer.setStyle(this.dataset.path);
        if (this.stroke_layer) this.stroke_layer.setStyle(this.dataset.stroke);
    }

}

// ---------- Tour Setup Functions ---------- //

/**
 * Builds the map object, sets zoom, initializes bounds
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
/**
 * Sets the appropriate tile server. If one of Leaflet providers is specified, uses that and any additional options specified. If any error occurs, uses the default tile server instead. Otherwise sets tile server using custom url.
 * @param {Object} options options from Tour/template
 * @returns L.tileLayer
 */
function createTileServer(options) {
    if (options.provider) {
        try {
            let server_options = {};
            // id field from blueprint is used for both id and variant, but user could edit the page header to specify variant instead
            if (id = options.id) {
                server_options.id = id;
                server_options.variant = id;
            }
            if (variant = options.variant) server_options.variant = variant;
            // key field from blueprint is used for both api key and access token, but user could edit the page header to specify apiKey and/or accessToken instead
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
/**
 * Possibly sets tile server attribution: Attribution section must exist. Tile server (custom) attribution cannot already be set (html of object is empty). The chosen tile server must have .options.attribution (servers from leaflet-providers will have this).
 * @param {Object} section jQuery (HTML element)
 * @param {Object} server L.tileLayer
 * @returns jQuery (HTML element)
 */
function setTileServerAttr(section, server) {
    if (section && !section.html()) {
        let a = server.options.attribution;
        if (a) section.html("<span>Tile Server: </span>" + a);
    }
    return section;
}
/**
 * Creates new feature object (TourPoint or TourShape). Updates the map to reference the object instead of the original options. Requires global variable map and tour.
 * @param {Object} value feature options from Tour/template (the value of the map)
 * @param {String} key feature id (the key of the map)
 * @param {Map} features map of id => feature options
 */
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
/**
 * Turns a list of basemaps into a map of L.imageOverlay objects.
 * @param {Object} basemap_data basemaps and their options from Tour/template
 * @returns Map of basemap id => L.imageOverlay
 */
function createBasemaps(basemap_data) {
    let basemaps = new Map();
    for (let [key, value] of Object.entries(basemap_data)) {
        basemaps.set(key, L.imageOverlay(value.url, value.bounds, value.options));
    }
    return basemaps;
}
/**
 * Calculate the starting bounds for a tour or view. Start bounds may be provided as actual bounds, in which case they will be returned and nothing needs to be calculated. Start bounds may be provided as distance, lat, and lng, which must be turned into bounds. Or start bounds may be null, in which case bounds should be found that include all features in the list. Or, if the list of features is empty, the default bounds will be returned instead.
 * @param {Object} start_bounds bounds from Tour/template for the tour or view in question
 * @param {Array} feature_ids ids for all features in the tour or view in question
 * @param {Map} features id => feature (TourPoint or TourShape)
 * @param {Object} default_bounds bounds encompassing all features from the tour
 * @returns Object (bounds)
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
/**
 * Modifies basemaps in each view to reference the actual L.imageOverlay objects. Determines the correct starting bounds for each view.
 * @param {Map} views id => view options
 * @param {Map} features id => feature (TourPoint or TourShape)
 * @param {Map} basemaps id => L.imageOverlay
 */
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

/**
 * Modifies current active basemaps based on view and zoom level. Any basemap included must be in the list of view basemaps and the current map zoom must be between the min and max for that basemap. Determines whether or not tile server should be included: Removes it if the view has the appropriate setting and at least one basemap is currently active. Otherwise adds it.
 * @param {Object} view set of view options
 * @param {Object} state tour_state (contains list of active basemaps)
 * @param {Object} map L.map
 * @param {Object} server L.tileLayer (tile server)
 */
function adjustBasemaps(view, state, map, server) {
    if (!view) return;
    // remove currently active basemaps from the map
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
/**
 * Resets the map - may help prevent weird issues from occuring, especially with the map display on mobile.
 */
function adjustMap() {
    map.invalidateSize();
    view = tour_state.view ?? tour.views.get('_tour');
    if ((bounds = view.bounds)) map.flyToBounds(bounds, { padding: FLY_TO_PADDING, animate: false });
}
/**
 * Called whenever the map finishes zooming in or out. Rechecks basemaps and zoom buttons.
 */
function handleMapZoom() {
    adjustBasemaps(tour_state.view, tour_state, map, tour.tile_layer);
    // adjust zoom buttons (should not be disabled unless at max or min zoom)
    let zoom_out = document.getElementById("zoom-out-btn");
    let zoom_in = document.getElementById("zoom-in-btn");
    zoom_out.disabled = false;
    zoom_in.disabled = false;
    if (map.getZoom() <= map.getMinZoom()) zoom_out.disabled = true;
    else if (map.getZoom() >= map.getMaxZoom()) zoom_in.disabled = true;
    // save zoom level
    sessionStorage.setItem(window.location.pathname + '_zoom', map.getZoom());
}
/**
 * Called whenever the map finishes moving. Updates the saved viewpoint center.
 */
function handleMapMove() {
    // save center
    let loc = window.location.pathname;
    sessionStorage.setItem(loc + '_lng', map.getCenter().lng);
    sessionStorage.setItem(loc + '_lat', map.getCenter().lat);
}
/**
 * Exits the current view and enters a new one (assuming the current and new one are actually different). Handles toggling features on the map, panning/zooming to view starting bounds, and adjusting basemaps.
 * @param {String} id view to enter
 * @param {Boolean} fly_to determines if the map should adjust to the view's starting bounds - currently only set false when initially setting the view (document ready function), as zooming/panning is undesirable in that instance
 */
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
            tour_state.map_needs_adjusting = true;
        }
    }
    adjustBasemaps(view, tour_state, map, tour.tile_layer);
    sessionStorage.setItem('tour_view', id);
}
/**
 * Not much is needed when exiting the view, but all features that were hidden by the view should have that "hide" flag removed.
 */
function exitView() {
    // unhide previously hidden features
    toggleViewFeatures(tour_state.view, tour.features, false);
    sessionStorage.setItem('tour_view', '');
}
/**
 * Toggles feature visibility. All features associated with the indicated view are either marked to be hidden (called when view is entered) or unmarked (called when view is exited).
 * @param {Object} view as set in the views map
 * @param {Object} features as set in the features map (TourPoint or TourShape)
 * @param {Boolean} hide indicates whether features are to be hidden (true, view is being entered) or unhidden (false, view is being exited)
 */
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
/**
 * Toggles feature visibility. Called when a dataset checkbox is toggled in the map legend. All features associated with the dataset are either marked to be hidden (called when dataset is unchecked) or unmarked (called when dataset is checked).
 * @param {String} id dataset id
 * @param {Map} datasets id => dataset options
 * @param {Boolean} hide indicates whether features are to be hidden (true, dataset is being unchecked) or unhidden (false, dataset is being checked)
 */
function toggleDataset(id, datasets, hide) {
    datasets.get(id).features.forEach(function(feature) {
        feature.hide_dataset = hide;
        feature.toggleHidden();
    });
}

// a couple of utility functions for getting information from the map
/**
 * Utility function to use when needed. Uncomment and paste the function in the console. Call function from the console to get the current map state (zoom and center point), or add the following line and click kthe map to get the current state. 
 */
// function printMapState() {
//     console.log('zoom: ' + map.getZoom() + ', center: ' + parseFloat(map.getCenter().lat.toFixed(4)) + ', ' + parseFloat(map.getCenter().lng.toFixed(4)));
// }
// map.on("click", printMapState);
/**
 * Utility "function" to use when needed. Uncomment and paste the function in the console. Whenever the map is clicked, the console will indicate the coordinates of the location clicked.
 */
// map.on('click', function(ev){
//     var latlng = map.mouseEventToLatLng(ev.originalEvent);
//     console.log(latlng.lat + ', ' + latlng.lng);
// });