var tourState = {
    view: null,
    tmpView: null,
    basemaps: [],
    activeBasemaps: [],
    mapAnimation: true,
    scrollyPos: 0,
    mapNeedsAdjusting: true,
}

// extend icon to all for setting id and button role - basically just allowing me to set some element attributes when the icon is created
L.Icon.ModalIcon = L.Icon.extend({
    createIcon: function(oldIcon) {
        var img = L.Icon.prototype.createIcon.call(this, oldIcon);
        if (this.options.id) img.setAttribute('id', this.options.id);
        if (this.options.role) img.setAttribute('role', this.options.role);
        return img;
    },
    createShadow: function(oldIcon) {
        var img = L.Icon.prototype.createShadow.call(this, oldIcon);
        if (img) img.setAttribute('aria-hidden', 'true');
        return img;
    },
});
function modalIcon(options) {
    return new L.Icon.ModalIcon(options);
}

// create map
var map = L.map('map', {
    zoomControl: true,
    maxZoom: tourOptions.maxZoom,
    minZoom: tourOptions.minZoom,
    attributionControl: false,
});

if (tourOptions.showMapLocationInUrl) var hash = new L.Hash(map);
if (tourOptions['bounds']) map.setMaxBounds(tourOptions['bounds']);

// Basemaps - Tile Server
//map.createPane('tileserverPane');
//map.getPane('tileserverPane').style.zIndex = 400;
let tileLayerOptions = {
    minZoom: tourOptions.minZoom,
    maxZoom: tourOptions.maxZoom,
}
if (tourOptions.stamenTileServer) {
    tileLayerOptions.maxNativeZoom = 13;
    var tileLayer = new L.StamenTileLayer(tourOptions.stamenTileServer, tileLayerOptions);
}
else var tileLayer = L.tileLayer(tourOptions.tileServer, tileLayerOptions);
map.addLayer(tileLayer);

// set up additional image basemaps
for (let [key, basemap] of tourBasemaps) {
    //map.createPane('pane_' + key);
    //map.getPane('pane_' + key).style.zIndex = 400;
    let layer = L.imageOverlay(basemap.file, basemap.bounds, {
        minZoom: basemap.minZoom,
        maxZoom: basemap.maxZoom
    });
    tourBasemaps.set(key, layer);
}

// set up datasets (without features, to start with - just creating the list and defining the options)
for (let [key, dataset] of tourDatasets) {
    let layer = L.geoJson(null, {
        // marker icons - for points
        pointToLayer: function(feature, latlng) {
            return createMarker(feature.properties, latlng, dataset);
        },
        // style - for paths
        style: function(geoJsonFeature) {
            return dataset.pathOptions;
        },
        // initial setup
        onEachFeature: function (feature, layer) {
            setupFeature(feature, layer);
        },
        interactive: true,
        // extra
        dataset: dataset,
    });
    tourDatasets.set(key, layer);
    map.addLayer(layer);
}

// function I can modify as needed for styling markers
function createMarker(props, latlng, dataset) {
    // handle alt text and popup existence
    let altText = props.name;
    let options = { ...dataset.iconOptions };
    if (dataset.legendAltText) altText += ", " + dataset.legendAltText;
    if (props.hasPopup) {
        altText += ", open popup";
        options.className += " has-popup";
        options.role = "button";
    }
    else options.className += " no-popup";

    options.id = props.id;
    // create marker
    let marker = L.marker(latlng, {
        icon: modalIcon(options),
        alt: altText,
        riseOnHover: true,
        id: props.id
    });
    return marker;
}
function setFeatureInteraction() {
    setIconInteraction();
    setPathInteraction();
}
function setIconInteraction() {
    // because trying to use leaflet's setup was a stupid idea
    // allow focusing on map icons that aren't buttons
    $(".has-popup").on("click keypress", function(e) {
        if (e.type === "click" || e.which === 32 || e.which === 13) {
            e.stopPropagation();
            openDialog(this.id+"-popup", this);
        }
    });
    $(".no-popup").on("click", function(e) {
        if (e.type === "click" || e.which === 32 || e.which === 13) {
            let feature = tourFeatures.get(this.id);
            feature.layer._icon.focus();
            feature.layer.getTooltip().getElement().classList.add("active");
        }
    });
    $(".has-popup, .no-popup").on("focus mouseover", function(e) {
        // make tooltip active when focusing/hovering over icon
        tourFeatures.get(this.id).layer.getTooltip().getElement().classList.add("active");
    }).on("blur mouseout", function(e) {
        // make tooltip inactive when ending focus/hover onver icon
        if (!(this === document.activeElement)) tourFeatures.get(this.id).layer.getTooltip().getElement().classList.remove("active");
    });
}
function setPathInteraction() {
    // make sure all paths have id and class
    // also make sure they have an additional element added to the end of the pane that is screen reader only
    for (let [id, feature] of tourFeatures) {
        if (feature.type !== "Point") {
            let props = feature.layer.feature.properties;
            let dataset = tourDatasets.get(props.dataSource).options.dataset;
            // set alt text
            let altText = props.name;
            if (dataset.legendAltText) altText += ", " + dataset.legendAltText;
            // create image element - receives focus
            let element = document.createElement("img");
            element.classList.add("sr-only");
            element.id = id + "-element";
            element.setAttribute("data-feature", id);
            element.setAttribute("tabindex", "0");
            // if feature has popup, img element becomes button to open popup
            if (props.hasPopup) {
                element.classList.add("element-has-popup");
                element.setAttribute("role", "button");
                altText += ", open popup";
            } else {
                element.classList.add("element-no-popup");
            }
            element.setAttribute("alt", altText);
            // treat lines and polygons different so that lines can have extra buffer for hover
            if (feature.type === "LineString" || feature.type === "MultiLineString") {
                // create extra feature
                let extraProps = { lineBuffer: true, featureId: props.id };
                let extra = { type: "Feature", properties: extraProps, geometry: feature.layer.feature.geometry };
                // add extra feature to dataset
                tourDatasets.get(props.dataSource).addData(extra);
                let lineBuffer = tourFeatures.get(id).lineBuffer;
                // set extra feature style
                lineBuffer.setStyle({ stroke: true, weight: 21, opacity: 0});
                // add id to path
                feature.layer._path.id = id;
                // add id to extra path
                lineBuffer._path.id = id+'extra';
                lineBuffer._path.setAttribute('data-featureId', id);
                // add class to extra path based on whether or not feature has popup
                if (props.hasPopup) lineBuffer._path.classList.add("path-has-popup");
                else lineBuffer._path.classList.add("path-no-popup");
                lineBuffer._path.classList.add("line");
            } else {
                // add id to path element
                feature.layer._path.id = id;
                // add class to path based on whether or not feature has popup
                if (props.hasPopup) feature.layer._path.classList.add("path-has-popup");
                else feature.layer._path.classList.add("path-no-popup");
                feature.layer._path.classList.add("polygon");
            }
            $(".leaflet-marker-pane").append(element);
        }
    }
    // path functions
    // clicking on path opens popup - polygon
    $(".polygon.path-has-popup").on("click", function(e) {
        let id = this.id;
        e.stopPropagation();
        openDialog(id+"-popup", document.getElementById(id+"-element"));
    });
    // clicking on path opens popup - line
    $(".line.path-has-popup").on("click", function(e) {
        let id = this.getAttribute("data-featureId");
        e.stopPropagation();
        openDialog(id+"-popup", document.getElementById(id+"-element"));
    });
    // clicking on path without popup moves focus to special element - polygon
    $(".polygon.path-no-popup").on("click", function(e) {
        let id = this.id;
        setActivePath(id);
        document.getElementById(id+"-element").focus();
    });
    // clicking on path without popup moves focus to special element - line
    $(".line.path-no-popup").on("click", function(e) {
        let id = this.getAttribute("data-featureId");
        setActivePath(id);
        document.getElementById(id+"-element").focus();
    });
    // let keypress on special element open popup
    $(".element-has-popup").on("keypress", function(e) {
        if (e.which === 32 || e.which === 13) {
            e.stopPropagation();
            openDialog(this.getAttribute("data-feature")+"-popup", this);
        }
    });
    // make tooltip active and change style when hovering over path - polygon
    $(".polygon.path-has-popup, .polygon.path-no-popup").on("mouseover", function(e) {
        setActivePath(this.id);
    }).on("mouseout", function(e) {
        // make tooltip inactive and revert style when ending hover over path
        if (!(document.getElementById(this.id+"-element") === document.activeElement)) endActivePath(this.id);
    });
    // make tooltip active and change style when hovering over path - line
    $(".line.path-has-popup, .line.path-no-popup").on("mouseover", function(e) {
        setActivePath(this.getAttribute("data-featureId"));
    }).on("mouseout", function(e) {
        // make tooltip inactive and revert style when ending hover over path
        let id = this.getAttribute("data-featureId");
        if (!(document.getElementById(id) === document.activeElement)) endActivePath(id);
    });
    // make tooltip active and change path style when focusing on special element
    $(".element-has-popup, .element-no-popup").on("focus", function(e) {
        setActivePath(this.getAttribute("data-feature"));
    }).on("blur", function(e) {
        // make tooltip inactive and revert path style when ending focus on special element
        endActivePath(this.getAttribute("data-feature"));
    });
}
function setActivePath(id) {
    let feature = tourFeatures.get(id);
    feature.layer.getTooltip().getElement().classList.add("active");
    feature.layer.setStyle(tourDatasets.get(feature.dataset).options.dataset.pathActiveOptions);
}
function endActivePath(id) {
    let feature = tourFeatures.get(id);
    feature.layer.getTooltip().getElement().classList.remove("active");
    feature.layer.setStyle(tourDatasets.get(feature.dataset).options.dataset.pathOptions);
}
function setupFeature(geoJsonFeature, layer) {
    let props = geoJsonFeature.properties;
    if (props.lineBuffer) {
        let featureId = props.featureId;
        tourFeatures.get(featureId).lineBuffer = layer;
        return;
    }
    let featureId = props.id;
    let feature = tourFeatures.get(featureId);
    feature.layer = layer;
    if (props.name !== null) createTooltip(props.name, props.dataSource, layer);
}
function createTooltip(name, datasetId, layer) {
    layer.bindTooltip(String('<div aria-hidden="true">' + name) + '</div>', {
        permanent: true,
        className: datasetId,
        // Option: interactive true
    });
    labels.push(layer);
    addLabel(layer, totalMarkers);
    layer.added = true;
    totalMarkers++;
}

var tourFeatures = new Map();

// loop through features and add where relevant
for (let [featureId, feature] of Object.entries(tourFeaturesJson)) {
    tourFeatures.set(featureId, {
        name: feature.properties.name,
        dataset: feature.properties.dataSource,
        type: feature.geometry.type,
    });
    tourDatasets.get(feature.properties.dataSource).addData(feature);
}

// deal with non-point tooltips - without this, tooltips associated with polygons that are much smaller than the starting view may be bound way too far off
for (let feature of tourFeatures.values()) {
    if (feature.type !== "Point") {
        map.fitBounds(feature.layer.getBounds());
        feature.layer.closeTooltip();
        feature.layer.openTooltip();
    }
}

// set map and view bounds
if (!tourOptions.bounds) {
    let tmpFeatureGroup = L.featureGroup(Array.from(tourDatasets.values()));
    tourOptions.bounds = tmpFeatureGroup.getBounds();
}
for (let [viewId, view] of tourViews) {
    if (!view.bounds && view.features.length > 0) {
        let tmpFeatureGroup = new L.FeatureGroup();
        for (let id of view.features) {
            tmpFeatureGroup.addLayer(tourFeatures.get(id).layer);
        }
        view.bounds = tmpFeatureGroup.getBounds();
    }
}

// set map bounds
if (tourOptions.bounds) map.fitBounds(tourOptions.bounds, { padding: [10, 10] });

setBasemaps();

resetAllLabels();

// map "on" functions
map.on("layeradd", function(){
    resetAllLabels();
});
map.on("layerremove", function(){
    resetAllLabels();
});
map.on("zoomend", function(){
    resetAllLabels();
    checkBasemaps();
    $(".leaflet-control-zoom a").removeAttr("aria-disabled");
    $(".leaflet-disabled").attr("aria-disabled", "true");
});

function resetAllLabels() {
    resetLabels(Array.from(tourDatasets.values()));
}
// Determines the correct basemaps based on the view
function setBasemaps() {
    // clear out basemap list
    tourState.basemaps = [];
    // tour basemaps
    if (!tourState.view || !tourViews.get(tourState.view).noTourBasemaps) {
        for (let basemap of tourOptions.tourMaps) {
            tourState.basemaps.push(basemap);
        }
    }
    // view basemaps
    if (tourState.view) {
        for (let basemap of tourViews.get(tourState.view).basemaps) {
            tourState.basemaps.push(basemap);
        }
    }
    // determine which basemaps should actually be active
    checkBasemaps();
    // default basemaps status will be checked in the above function
}

// Determines the correct basemaps based on the zoom
function checkBasemaps() {
    // make list of which basemaps should be active
    let newBasemaps = [];
    for (let basemap of tourState.basemaps) {
        let layer = tourBasemaps.get(basemap);
        if (map.getZoom() >= layer.options.minZoom && map.getZoom() <= layer.options.maxZoom) newBasemaps.push(basemap);
    }
    // remove basemaps if necessary
    for (let basemap of tourState.activeBasemaps) {
        if (!newBasemaps.includes(basemap)) map.removeLayer(tourBasemaps.get(basemap));
    }
    // add basemaps
    tourState.activeBasemaps = newBasemaps;
    for (let basemap of tourState.activeBasemaps) {
        let layer = tourBasemaps.get(basemap);
        if (!map.hasLayer(layer)) map.addLayer(layer);
    }
    // check default basemap
    if (tourState.activeBasemaps.length > 0 && ((tourState.view && tourViews.get(tourState.view).removeTileServer) || (tourOptions.removeTileServer))) {
        if (map.hasLayer(tileLayer)) map.removeLayer(tileLayer);
    } else {
        if (!map.hasLayer(tileLayer)) map.addLayer(tileLayer);
    }
}

// fix map issues - only call when absolutely necessary
function adjustMap() {
    if (window.innerWidth < mobileWidth && tourState.mapNeedsAdjusting) {
        map.invalidateSize();
        let zoom = map.getZoom();
        map.zoomIn(1, { animate: false });
        map.setZoom(zoom, { animate: false });
        tourState.mapNeedsAdjusting = false;
    }
}

// -------------------------------------------------
// -------------------------------------------------
// --------------- end of map setup ----------------
// -------------------------------------------------
// -------------------------------------------------

// tour setup

// Question - potential customization options
var scrollamaOptions = {
    debug: false,
    offset: 0.33,
};

// scrollama setup
scroller = scrollama();
scroller.setup({
    step: "#scrolly #scroll-text .step",
    offset: scrollamaOptions.offset,
    debug: scrollamaOptions.debug
}).onStepEnter(function(e) {
    // use timeout function so that if multiple views are scrolled through at once, only the last view will be truly entered
    tourState.tmpView = e.element.getAttribute("id");
    setTimeout(function() {
        if (tourState.tmpView !== tourState.view && tourState.mapAnimation) {
            enterView(tourState.tmpView);
            tourState.mapNeedsAdjusting = true;
        }
    }, 500);
}).onStepExit(function(e) {
    // use timeout function to ensure that exitView is only called when a view is exited but no new view is entered
    tourState.tmpView = null;
    setTimeout(function() {
        if (!tourState.tmpView && tourState.mapAnimation) {
            exitView();
            tourState.mapNeedsAdjusting = true;
        }
    }, 600);
});

$(document).ready(function() {

    // this function modified from theme.js - overrides the function there, but relies on variables and function set there
    window.onscroll = function(e) {
        if (!current.scrollTick) {
            setTimeout(function () {
                toggleBackToTop();
                // adjust header for desktop
                if (window.innerWidth >= mobileWidth) {
                    let target = document.getElementById("top").scrollHeight+20;
                    if (document.body.scrollTop > target || document.documentElement.scrollTop > target) $("#top").addClass("scrolled");
                    else $("#top").removeClass("scrolled");
                }
                // save scrollyPos for mobile
                else if (tourState.scrolly) tourState.scrollyPos = window.scrollY;
                current.scrollTick = false;
            }, 100);
        }
        current.scrollTick = true;
    };

    if (tourOptions.wideCol) $("body").addClass("wide-column");
    // move map controls for more sensible DOM order
    let controls = $(".leaflet-control-container");
    controls.remove();
    $("#map").prepend(controls);

    setFeatureInteraction();

    // button functions - toggles
    $("#header-toggle-btn").on("click", function(e) {
        $('body').toggleClass('menu-hidden-mobile');
        $(this).toggleClass('expanded');
        let expanded = ($(this).attr('aria-expanded')==='true');
        $(this).attr('aria-expanded', expanded);
        window.localStorage.setItem('headerExpanded', expanded);
    });
    if (window.localStorage.getItem('headerExpanded') === 'false') $("#header-toggle").click();

    $("#content-toggle-btn").on("click", function(e) {
        if (this.getAttribute("data-current") === "scrolly") {
            switchToMap(this.getAttribute("id"));
            // Question: explicitly set focus to map?
            adjustMap();
        } else {
           switchToContent(this.getAttribute("data-focus"));
        }
    });
    
    $("#legend-toggle-btn").on("click", function(e) {
        $(".legend").toggleClass("minimized");
        $(e).attr("aria-expanded", ($(e).attr("aria-expanded")==="true"));
    });

    $("#map-animation-toggle").on("input", function(e) {
        let on = (this.checked);
        tourState.mapAnimation = on;
        window.localStorage.setItem("mapAnimation", on);
    });
    if (window.localStorage.getItem("mapAnimation") === "false") $("#map-animation-toggle").click();

    // legend checkboxes
    $(".legend-checkbox").on("input", function(e) {
        for (let [featureId, feature] of tourFeatures) {
            if (feature.dataset === this.value) {
                feature.hideDataSource = !this.checked;
                toggleHideFeature(feature);
            }
        }
        // Question: Info to give screen reader?
        resetAllLabels();
    });

    // other buttons
    $("#reset-view-btn").on("click", exitView);

    $(".show-view-btn").on("click", function(e) {
        if (window.innerWidth < mobileWidth) { // constant from theme
            // Question: Is the change of focus to map expected? Should I also change focus on desktop?
            switchToMap(this.getAttribute("id"));
            $("#map").focus();
        }
        enterView(this.getAttribute("data-view"));
    });
});

function switchToMap(focusElement) {
    $("body").addClass("map-active");
    // Question: explicitly set focus to map?
    // change button text/value
    // set button data-focus to focusElement
    $("#content-toggle-btn").attr("data-focus", focusElement).attr("data-current", "map").text("View Content");
}
function switchToContent(focusElement) {
    $("body").removeClass("map-active");
    tourState.scrolly = true;
    window.scrollTo(0, tourState.scrollyPos);
    // Question: If no contentFocus, do I need to explicitly put focus somewhere? (e.g. used map toggle button to go to map)
    if (focusElement) document.getElementById(focusElement).focus(); 
    // change button text/value
    $("#content-toggle-btn").attr("data-focus", "").attr("data-current", "scrolly").text("View Map");
}

function toggleHideFeature(feature) {
    let hide = (feature.hideDataSource || feature.hideNonView);
    let tooltip = feature.layer.getTooltip().getElement();
    if (feature.type === "Point") {
        // hide tooltip, icon, and possibly shadow
        let icon = feature.layer._icon;
        let shadow = feature.layer._shadow;
        $(tooltip).add(icon).add(shadow).attr("aria-hidden", hide).css("display", (hide ? 'none' : 'block'));
    }
    else {
        let img = document.getElementById(feature.layer.feature.properties.id+"-element");
        let path = feature.layer._path;
        if (feature.type === "LineString" || feature.type === "MultiLineString") {
            let buffer = feature.lineBuffer._path;
            $(tooltip).add(img).add(path).add(buffer).attr("aria-hidden", hide).css("display", (hide ? 'none' : 'block'));
        }
        else $(tooltip).add(img).add(path).attr("aria-hidden", hide).css("display", (hide ? 'none' : 'block'));
    }
}

function toggleHideNonViewFeatures(viewId, hide) {
    let view = tourViews.get(viewId);
    // ignore if feature list is empty
    if (!view.features) return;
    for (let [featureId, feature] of tourFeatures) {
        if (!view.features.includes(featureId)) {
            // toggle
            feature.hideNonView = hide;
            toggleHideFeature(feature);
        }
    }
}

function enterView(id) {
    if (tourState.view && tourViews.get(tourState.view).onlyShowViewFeatures) toggleHideNonViewFeatures(tourState.view, false);
    if (id) {
        tourState.view = id;
        let view = tourViews.get(id);
        if (view.onlyShowViewFeatures) toggleHideNonViewFeatures(id, true);
        if (view.bounds) {
            // Option: Any instance where I would want to add { animate: boolean } to flyTo args? (if so, add to else flyTo statement, too)
            map.flyToBounds(view.bounds, { padding: [10, 10] });
        }
        // adjust basemaps - call here because we need setBasemaps(), not just checkBasemaps()
        setBasemaps();
        // just in case
        if (tourState.mapNeedsAdjusting) adjustMap();
    } else {
        // exit view
        //if (!tourState.view) return; // already no view
        tourState.view = null;
        if (tourOptions.bounds) map.flyToBounds(tourOptions.bounds, { padding: [10, 10] });
    }
}

function exitView() {
    enterView(null);
}