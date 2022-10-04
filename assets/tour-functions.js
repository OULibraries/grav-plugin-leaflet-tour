/**
 * This document is for: Only functions (and classes, I guess), particularly only ones that need to be tested. It should not contain code that will run without a function call. Functions will be called by either tour.js or tour-test.js.
 */

const BUFFER_WEIGHT = 21;
const DEFAULT_TILE_SERVER = 'OpenTopoMap';

/**
 * Things common to all features
 */
class TourFeature {
    // properties
    id; name; has_popup; geometry;
    // leaflet objects
    point_layer; dataset;
    // status
    hide_view; hide_dataset;
    /**
     * @param {Object} feature Data provided from Tour
     * @param {Map} datasets Map of id to dataset data
     */
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
    get icon_options() {
        if (this.dataset.icon) return this.dataset.icon;
        else {
            // shape feature, needs some basic options and visibly hidden class
            return {
                iconUrl: 'user/plugins/leaflet-tour/images/marker-icon.png',
                iconSize: [14, 14],
                className: 'focus-marker sr-only'
            };
        }
    }
    get alt_text() {
        return this.name + (this.dataset.legend_summary ? ", " + this.dataset.legend_summary : "") + (this.has_popup ? ", open popup" : "");
    }
    get focus_element() { return this.point_layer._icon; }
    get elements() {
        return $(this.tooltip).add(this.focus_element);
    }
    addToLayer(layer) {
        this.point_layer = layer;
        // Note: point features will bind tooltip to this layer, shape featurs will not
    }
    bindTooltip(layer) {
        // TODO: aria-hidden on each tooltip, or on the whole tooltip pane?
        layer.bindTooltip(String('<div aria-hidden="true">' + this.name) + '</div>', {
            permanent: false,
            className: this.dataset.id,
            sticky: false,
        });
    }
    addToLayers(point_layer, shape_layer) {} // to be defined by point vs. shape
    // called after feature is created and document is ready
    modify() {
        let el = this.hover_element;
        // hover element needs id, additional classes, and data-feature attribute
        el.id = this.id;
        el.classList.add(this.dataset.id);
        el.classList.add("hover-el");
        el.classList.add(this.has_popup ? "has-popup" : "no-popup");
        el.setAttribute("data-feature", this.id);
        // focus element needs class, data-feature attribute, and possibly role/aria-haspopup attributes (for point, this will actually be the same element)
        let focus = this.focus_element;
        focus.classList.add("focus-el");
        focus.setAttribute("data-feature", this.id); // redundant for points
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
    activate(map, e) {
        this.layer.openTooltip();
    }
    deactivate() {
        this.layer.closeTooltip();
    }
}
class TourPoint extends TourFeature {
    constructor(feature, datasets) { super(feature, datasets); }
    get hover_element() { return this.point_layer._icon; }
    get elements() {
        return super.elements.add(this.point_layer._shadow);
    }
    get tooltip() {
        return this.point_layer.getTooltip().getElement();
    }
    get main_layer() { return this.point_layer; }
    get main_element() { return this.point_layer._icon; }
    addToLayer(layer) {
        super.addToLayer(layer);
        this.bindTooltip(layer);
    }
    // called when creating feature, add the feature geojson to point layer
    addtoLayers(point_layer, shape_layer) {
        point_layer.addData({
            type: 'Feature',
            geometry: this.geometry,
            properties: { id: this.id },
        });
        // ignore shape layer
    }
    modify() {
        super.modify();
        this.focus_element.id = this.id + '-focus';
    }
    activate(map, e) {
        super.activate(map, e);
        if (!map.getBounds().contains(this.layer.getLatLng())) {
            map.panTo(this.layer.getLatLng(), { animate: true });
        }
    }
}
class TourShape extends TourFeature {
    // svg layers (may have just one, may have all three)
    main_layer; stroke_layer; buffer_layer;
    // only set if feature should have buffer
    buffer_weight;
    tmp_tooltip;
    constructor(feature, datasets) {
        super(feature, datasets);
        // buffer for: anything with stroke under buffer weight and/or any line/polygon with both stroke and border
        if (this.dataset.path.weight < BUFFER_WEIGHT) {
            // add buffer, use default buffer weight
            this.buffer_weight = BUFFER_WEIGHT;
        }
        else if (this.dataset.stroke) {
            // add buffer, set buffer weight
            this.buffer_weight = this.dataset.path.weight;
        }
    }
    get is_line() {
        return this.geometry.type.toLowerCase().includes('linestring');
    }
    get hover_element() {
        if (this.buffer_layer) return this.buffer_layer._path;
        else return this.main_layer._path;
    }
    get elements() {
        let elements = super.elements.add(this.main_layer._path);
        if (this.stroke_layer) elements = elements.add(this.stroke_layer._path);
        if (this.buffer_layer) elements = elements.add(this.buffer_layer._path);
        return elements;
    }
    get tooltip() {
        return this.main_layer.getTooltip().getElement();
    }
    getStyle(layer_type) {
        switch (layer_type) {
            case 'main':
                return this.dataset.path;
            case 'stroke':
                return this.dataset.stroke;
            case 'buffer':
                let options = {
                    stroke: true,
                    weight: this.buffer_weight,
                    opacity: 0,
                };
                // only include fill if feature is not a line and has fill
                if (this.is_line || !this.dataset.path.fill) return { ...options, fill: false };
                else return { ...options, fill: true, fillColor: 'transparent' };
        }
    }
    getGeoJson(layer_type) {
        if (layer_type === 'Point') {
            return {
                type: 'Feature',
                geometry: {}
            }
        } else {
            let json = this.geoJson;
            json.properties.layer_type = layer_type;
            return json;
        }
    }
    addToShapeLayer(layer, layer_type) {
        switch (layer_type) {
            case 'main':
                this.main_layer = layer;
                this.bindTooltip(main_layer);
                break;
            case 'stroke':
                this.stroke_layer = layer;
                break;
            case 'buffer':
                this.buffer_layer = layer;
                break;
        }
    }
    // called when creating feature, add to shape layer up to three times, then add to point layer
    addtoLayers(point_layer, shape_layer) {
        let json = {
            type: 'Feature',
            geometry: this.geometry,
        };
        // first add the basic shape geometry data
        shape_layer.addData({
            ...json,
            properties: { id: this.id, layer_type: 'main' }
        });
        // then add stroke if applicable
        if (this.dataset.stroke) shape_layer.addData({ ...json, properties: { id: this.id, layer_type: 'stroke' }});
        // then add buffer if applicable
        if (this.buffer_weight) shape_layer.addData({ ...json, properties: { id: this.id, layer_type: 'buffer' }});
        // then add point, using center from main layer
        point_layer.addData({
            type: 'Feature',
            geometry: {
                type: 'Point',
                coordinates: this.main_layer.getCenter(),
            },
            properties: { id: this.id }
        });
    }
    activate(map, e) {
        // open initial tooltip
        if (this.tmp_tooltip) this.main_layer.openTooltip(this.tmp_tooltip);
        else this.main_layer.openTooltip();
        this.main_layer.setStyle(this.dataset.active_path);
        if (this.stroke) this.stroke.setStyle(this.dataset.active_stroke);

        if (!map.getBounds().contains(this.main_layer.getTooltip().getLatLng())) {
            this.main_layer.openTooltip(); // reset tooltip
            // if hover: move tooltip to mouse location (by modifying offset)
            if (this.focus_element !== document.activeElement) {
                this.tmp_tooltip = map.mouseEventToLatLng(e);
            } 
            else {
                // if bounding box is in view, try moving tooltip to the closest layer point
                if (map.getBounds().intersects(this.main_layer.getBounds())) {
                    try {
                        let point = this.main_layer.closestLayerPoint(map.latLngToLayerPoint(map.getCenter()));
                        let latlng = map.layerPointToLatLng([point['x'], point['y']]);
                        if (map.getBounds().contains(latlng)) this.tmp_tooltip = latlng;
                    } catch (e) {
                        // do nothing
                    }
                }
                if (!this.tmp_tooltip) {
                    // either bounding box is not in view or it is, but the closest layer point is not - pan and possibly zoom to feature
                    map.panTo(this.main_layer.getCenter(), { animate: true });
                    tour_state.tmp_feature = this;
                    setTimeout(function() {
                        let feature = tour_state.tmp_feature;
                        tour_state.tmp_feature = null;
                        if (!map.getBounds().contains(feature.layer.getBounds())) map.flyToBounds(feature.layer.getBounds(), { animate: true, noMoveStart: true });
                    }, 250); // wait for .25s (animation duration) before checking
                }
            }

            if (this.tmp_tooltip) {
                this.main_layer.openTooltip(this.tmp_tooltip);
            }
        } 
    }
    deactivate() {
        super.deactivate();
        this.main_layer.setStyle(this.dataset.path);
        if (this.stroke) this.stroke.setStyle(this.dataset.stroke);
        this.tmp_tooltip = null;
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
    return m;
}
function createTileServer(options) {
    if (options.provider) {
        try {
            return new L.tileLayer.provider(options.provider, {
                id: options.id,
                variant: options.variant ?? options.id,
                key: options.key,
                apiKey: options.apiKey ?? options.key,
                accessToken: options.accessToken ?? options.key,
            });
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
/**
 * Create layer for all features. Point features will use the correct icon options. Shape features will have hidden points at their center.
 * - tour.features must exist
 */
function createPointLayer() {
    return L.geoJson(null, {
        pointToLayer: function(json, latlng) {
            let feature = tour.features.get(json.properties.id);
            return L.marker(latlng, {
                icon: new L.Icon(feature.icon_options),
                alt: feature.alt_text,
                riseOnHover: true,
            });
        },
        onEachFeature: function(json, layer) {
            tour.features.get(json.properties.id).addToLayer(layer);
        },
        interactive: true,
    });
}
/**
 * Create layer for all feature paths, each feature may be added multiple times.
 * - tour.features must exist
 */
function createShapeLayer() {
    return L.geoJson(null, {
        style: function(json) {
            return tour.features.get(json.properties.id).getStyle(json.properties.layer_type);
        },
        onEachFeature: function(json, layer) {
            tour.featrues.get(json.properties.id).addToShapeLayer(layer, json.properties.layer_type);
        }
    });
}
/**
 * Called via forEach on tour features list - transforms feature data from Tour into TourFeature objects with necessary settings
 * - tour.datasets must exist
 * - tour.point_layer and tour.shape_layer must exist
 */
function createFeature(value, key, map) {
    let feature;
    if (value.geometry.type === 'Point') {
        feature = new TourPoint(value, tour.datasets);
    } else {
        feature = new TourShape(value, tour.datasets);
    }
    if (feature) {
        feature.addToLayers(tour.point_layer, tour.shape_layer);
        map.set(key, feature);
        feature.dataset.features.push(feature);
    }
}
// to be called after map is ready modifies html values as needed
function modifyFeatures(features) {
    features.forEach(feature => feature.modify());
}
function createBasemaps(basemap_data) {
    let basemaps = new Map();
    for (let [key, value] of Object.entries(basemap_data)) {
        // make sure image exists
        $.get(value.url).done(function() {
            basemaps.set(key, L.imageOverlay(value.url, value.bounds, value.options));
        });
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
            group.addLayer(features.get(id).main_layer);
        }
        return group.getBounds();
    }
    else return default_bounds;
}
function setupViews(views, features, basemaps) {
    default_bounds = createBounds(null, features.keys(), features, null);
    for (let view of views) {
        // replace view basemap files with references to the actual basemaps
        let view_basemaps = [];
        for (let file of view.basemaps) {
            let basemap = basemaps.get(file);
            if (basemap) view_basemaps.push(basemap);
        }
        view.basemaps = view_basemaps;
        // set bounds
        view.bounds = createBounds(view.bounds, view.features, features, default_bounds);
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
        if (map.getZoom() >= basemap.options.min_zoom && map.getZoom() <= basemap.options.max_zoom) {
            state.basemaps.push(basemap);
            map.addLayer(basemap);
        }
    }
    // tile server
    if (!state.basemaps.length || !view.remove_tile_server) map.addLayer(server);
    else map.removeLayer(server);
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