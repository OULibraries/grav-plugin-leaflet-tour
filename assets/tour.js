// TODO: Note that as a matter of standards, since geoJson must be x,y (or long, lat), all coord will be stored as long, lat. While this may be weird in some cases (and may not match the visual display), it will allow for consistency on the backend.

// TODO: Use Leaflet.markerCluster to allow a given dataset to be clustered

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
    center: L.GeoJSON.coordsToLatLng(tourOptions.center),
    zoom: tourOptions.zoom,
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
var tileLayer = L.tileLayer(tourOptions.tileServer, {
    minZoom: tourOptions.minZoom,
    maxZoom: tourOptions.maxZoom,
    // TODO: updateWhenZooming: default true
    // TOOD: bounds: tiles will only be loaded inside bounds provided
});
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
setBasemaps();

// set up datasets (without features, to start with - just creating the list and defining the options)
for (let [key, dataset] of tourDatasets) {
    let layer = L.geoJson(null, {
        // marker icons - for points
        pointToLayer: function(feature, latlng) {
            return createMarker(feature.properties, latlng, dataset);
        },
        // style - for paths
        style: function(geoJsonFeature) {
            return createPath(geoJsonFeature, dataset);
        },
        // initial setup
        onEachFeature: function (feature, layer) {
            setupFeature(feature, layer);
        },
        interactive: true,
        // TODO: try removing
        dataVar: 'tourFeaturesJson',
        layerName: key + 'Layer',
    });
    tourDatasets.set(key, layer);
    map.addLayer(layer);
}

// function I can modify as needed for styling markers
function createMarker(props, latlng, dataset) {
    // handle alt text and popup existence
    let altText = props.name;
    if (dataset.legendAltText) altText += ", " + dataset.legendAltText;
    if (props.hasPopup) {
        altText += ", open popup";
        dataset.iconOptions.className += " has-popup";
        dataset.iconOptions.role = "button";
    }
    else dataset.iconOptions.className += " no-popup";

    dataset.iconOptions.id = props.id;
    // create marker
    let marker = L.marker(latlng, {
        icon: modalIcon(dataset.iconOptions),
        alt: altText,
        riseOnHover: true,
        id: props.id
    });
    // allow focusing on map icons that aren't buttons
    if (!props.hasPopup) {
        marker.on('click keydown', function(e) {
            if (e.type === "click" || e.which === 32 || e.which === 13) {
                let feature = tourFeatures.get(this.options.id);
                feature.layer._icon.focus();
                feature.layer.getTooltip().getElement().classList.add("active");
            }
        }, marker);
    } else {
        // make icons open popups from map
        marker.on('click keydown', function(e) {
            if (e.type === "click" || e.which === 32 || e.which === 13) {
                openDialog(this.options.id+"-popup", document.getElementById(this.options.id));
            }
        }, marker);
    }
    // make tooltip active when focusing/hovering over icon
    marker.on('focus mouseover', function(e) {
        tourFeatures.get(this.options.id).layer.getTooltip().getElement().classList.add("active");
    }, marker);
    // make tooltip inactive when ending focus/hover onver icon
    marker.on('blur mouseout', function(e) {
        if (!(document.getElementById(this.options.id) === document.activeElement)) tourFeatures.get(this.options.id).layer.getTooltip().getElement().classList.remove("active");
    }, marker);
    // TODO: Is there any reason I would want easy access to the marker object, rather than just the icon element?
    // tourFeatures.get(props.id).marker = marker;
    return marker;
}
function createPath(geoJsonFeature, dataset) {
    // TODO
}
function setupFeature(geoJsonFeature, layer) {
    let props = geoJsonFeature.properties;
    let featureId = props.id;
    let feature = tourFeatures.get(featureId);
    feature.layer = layer;
    createTooltip(props.name, props.dataSource, layer);
    // TODO: May need to move functions from createMarker here when working on path functionality
}
function createTooltip(name, datasetId, layer) {
    if (name !== null) { // just in case
        layer.bindTooltip(String('<div aria-hidden="true">' + name) + '</div>', {
            permanent: true,
            offset: [-0, -16],
            className: datasetId,
            // TODO: interactive true?
        });
        labels.push(layer);
        addLabel(layer, totalMarkers);
        layer.added = true;
        totalMarkers++;
    }
}

var tourFeatures = new Map();

// loop through features and add where relevant
for (let [featureId, feature] of Object.entries(tourFeaturesJson)) {
    tourFeatures.set(featureId, {
        name: feature.properties.name,
        dataset: feature.properties.dataSource,
    });
    tourDatasets.get(feature.properties.dataSource).addData(feature);
}
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
    if (tourState.activeBasemaps.length > 0 && ((tourState.view && tourViews.get(tourState.view).removeDefaultBasemap) || (tourOptions.removeDefaultBasemap))) {
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
    // TODO: trying something slightly different
    /*setTimeout(function(id) {
        if (tourState.tmpView === id) {
            enterView(id);
            tourState.mapNeedsAdjusting = true;
        }
    }, 500, tourState.tmpView);*/
    setTimeout(function() {
        if (tourState.tmpView !== tourState.view) {
            enterView(tourState.tmpView);
            tourState.mapNeedsAdjusting = true;
        }
    }, 500);
}).onStepExit(function(e) {
    // use timeout function to ensure that exitView is only called when a view is exited but no new view is entered
    tourState.tmpView = null;
    setTimeout(function() {
        if (!tourState.tmpView) {
            exitView();
            tourState.mapNeedsAdjusting = true;
        }
    }, 600);
});

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
}

$(document).ready(function() {
    if (tourOptions.wideCol) $("body").addClass("wide-column");
    // move map controls for more sensible DOM order
    let controls = $(".leaflet-control-container");
    controls.remove();
    $("#map").prepend(controls);

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
            // TODO: explicitly set focus to map?
            adjustMap();
        } else {
           switchToScrolly(this.getAttribute("data-focus"));
        }
    });
    
    $("#legend-toggle-btn").on("click", function(e) {
        $(".legend").toggleClass("minimized");
        $(e).attr("aria-expanded", ($(e).attr("aria-expanded")==="true"));
    });

    $("#map-animation-toggle-btn").on("input", function(e) {
        let on = ($(this).attr("value") === "on");
        $(this).attr("value", (on ? "off" : "on"));
        tourState.mapAnimation = on;
        window.localStorage.setItem("mapAnimation", on);
    });
    if (window.localStorage.getItem("mapAnimation") === "false") $("#map-animation-toggle-btn").click();

    // legend checkboxes
    $(".legend-checkbox").on("input", function(e) {
        tourDatasets.get(this.getAttribute("value")).eachLayer(function(layer) {
            let feature = tourFeatures.get(layer.feature.properties.id);
            feature.hideDataSource = !this.checked;
            toggleHideFeature(feature);
        });
        // TODO: Is this good to have, or no?
        resetAllLabels();
    });

    // other buttons
    $("#reset-view-btn").on("click", exitView);

    $(".show-view-btn").on("click", function(e) {
        if (window.innerWidth < mobileWidth) { // constant from theme
            // TODO: Is the change of focus to map expected? Should I also change focus on desktop?
            switchToMap(this.getAttribute("id"));
            $("#map").focus();
        }
        enterView(this.getAttribute("data-view"));
    });
});

function switchToMap(focusElement) {
    $("body").addClass("map-active");
    // TODO: explicitly set focus to map?
    // change button text/value
    // set button data-focus to focusElement
    $("#toggle-content-btn").attr("data-focus", focusElement).attr("data-current", "map").text("View Content");
}
function switchToContent(focusElement) {
    $("body").removeClass("map-active");
    tourState.scrolly = true;
    window.scrollTo(0, tourState.scrollyPos);
    // TODO: If no contentFocus, do I need to explicitly put focus somewhere? (e.g. used map toggle button to go to map)
    if (focusElement) document.getElementById(focusElement).focus(); 
    // change button text/value
    $("#toggle-content-btn").attr("data-focus", "").attr("data-current", "scrolly").text("View Map");
}

function toggleHideFeature(feature) {
    let hide = (feature.hideDataSource || feature.hideNonView);
    let tooltip = feature.layer.getTooltip().getElement();
    let icon = feature.layer._icon;
    let shadow = feature.layer._shadow;
    $(tooltip).add(icon).add(shadow).attr("aria-hidden", hide).css("display", (hide ? 'none' : 'block'));
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
        // TODO: should map animation toggle the animation of flyTo or should it toggle changing the zoom at all?
        let zoom = (view.zoom > tourOptions.maxZoom ? tourOptions.maxZoom : (view.zoom < tourOptions.minZoom ? tourOptions.minZoom : view.zoom));
        let coords = (view.center ? L.GeoJSON.coordsToLatLng(view.center) : null);
        // TODO: either wrap this in "if (animate)" clause or add { animate: boolean } to flyTo args
        if (zoom && coords) {
            map.flyTo(coords, zoom);
            // TODO: If zoom doesn't change, does onZoomEnd function still get called and checkBasemaps()? And if it does, should I do something to prevent it?
        }
        // adjust basemaps - call here because we need setBasemaps(), not just checkBasemaps()
        setBasemaps();
        // just in case
        if (tourState.mapNeedsAdjusting) adjustMap();
    } else {
        // exit view
        if (!tourState.view) return; // already no view
        tourState.view = null;
        // TODO: do this only if animate or pass animate as an option
        map.flyTo(L.GeoJSON.coordsToLatLng(tourOptions.center), tourOptions.zoom);
    }
}

function exitView() {
    enterView(null);
}

// TODO: CSS
// - make minimum height of .scroll-top 33% (scrollamaOptions.offset) + 40px - var
// - for desktop, var is 100 or 110
// - for mobile, var is 0
//
// - make minimum height of footer 67% (1-scrollamaOptions.offset) + 40px - var
// - for desktop and mobile, var will need to be determined by using a reasonable minimum for last view height