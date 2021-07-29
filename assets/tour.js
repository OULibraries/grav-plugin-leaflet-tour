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
if (tourOptions.stamenTileServer) var tileLayer = new L.StamenTileLayer(tourOptions.stamenTileServer, tileLayerOptions);
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
setBasemaps();

// set up datasets (without features, to start with - just creating the list and defining the options)
for (let [key, dataset] of tourDatasets) {
    // TODO: temp
    /*dataset.pathOptions = {
        color: "#ffffff",
        weight: 3,
        opacity: .65,
        bubblingMouseEvents: false,
    };
    dataset.pathActiveOptions = {
        opacity: 1,
        fillOpacity: .4,
        weight: 5
    };*/
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
    // TODO: Is there any reason I would want easy access to the marker object, rather than just the icon element?
    // tourFeatures.get(props.id).marker = marker;
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
        if (!feature.point) {
            let props = feature.layer.feature.properties;
            let altText = props.name;
            let dataset = tourDatasets.get(props.dataSource).options.dataset;
            if (dataset.legendAltText) altText += ", " + dataset.legendAltText;
            feature.layer._path.id = id;
            let element = document.createElement("img");
            element.classList.add("sr-only");
            element.id = id + "-element";
            element.setAttribute("data-feature", id);
            element.setAttribute("tabindex", "0");
            if (props.hasPopup) {
                feature.layer._path.classList.add("path-has-popup");
                element.classList.add("element-has-popup");
                element.setAttribute("role", "button");
                altText += ", open popup";
            } else {
                feature.layer._path.classList.add("path-no-popup");
                element.classList.add("element-no-popup");
            }
            element.setAttribute("alt", altText);
            $(".leaflet-marker-pane").append(element);
        }
    }
    // path functions
    // let clicking on path open popup
    $(".path-has-popup").on("click", function(e) {
        e.stopPropagation();
        openDialog(this.id+"-popup", document.getElementById(this.id+"-element"));
    });
    // let clicking on path without popup move focus to special element
    $(".path-no-popup").on("click", function(e) {
        setActivePath(this.id);
        document.getElementById(this.id+"-element").focus();
    });
    // let keypress on special element open popup
    $(".element-has-popup").on("keypress", function(e) {
        if (e.which === 32 || e.which === 13) {
            e.stopPropagation();
            openDialog(this.getAttribute("data-feature")+"-popup", this);
        }
    });
    // make tooltip active and change style when hovering over path
    $(".path-has-popup, .path-no-popup").on("mouseover", function(e) {
        setActivePath(this.id);
    }).on("mouseout", function(e) {
        // make tooltip inactive and revert style when ending hover over path
        if (!(document.getElementById(this.id+"-element") === document.activeElement)) endActivePath(this.id);
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
        point: (feature.geometry.type === "Point"),
    });
    tourDatasets.get(feature.properties.dataSource).addData(feature);
}

// set map and view bounds
if (!tourOptions.bounds) {
    let tmpFeatureGroup = L.featureGroup(Array.from(tourDatasets.values()));
    tourOptions.bounds = tmpFeatureGroup.getBounds();
}
for (let [viewId, view] of tourViews) {
    if (!view.bounds && view.features.length > 0) {
        let tmpFeatureGroup = L.featureGroup();
        for (let id of view.features) {
            tmpFeatureGroup.addLayer(tourFeatures.get(id).layer);
        }
        view.bounds = tmpFeatureGroup.getBounds();
    }
}

// set map bounds
if (tourOptions.bounds) map.fitBounds(tourOptions.bounds);

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
    // TODO: trying something slightly different
    /*setTimeout(function(id) {
        if (tourState.tmpView === id) {
            enterView(id);
            tourState.mapNeedsAdjusting = true;
        }
    }, 500, tourState.tmpView);*/
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

    // TODO: Move to theme
    $(".dialog-backdrop").on("click", function(e) {
        closeDialog($(this).children()[1])
    });
    $(".dialog-backdrop").children().on("click", function(e) {
        e.stopPropagation();
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
            // TODO: explicitly set focus to map?
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
        //console.log(on);
        //$(this).attr("value", (on ? "off" : "on"));
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
    console.log(focusElement);
    $("#content-toggle-btn").attr("data-focus", focusElement).attr("data-current", "map").text("View Content");
}
function switchToContent(focusElement) {
    $("body").removeClass("map-active");
    tourState.scrolly = true;
    window.scrollTo(0, tourState.scrollyPos);
    // TODO: If no contentFocus, do I need to explicitly put focus somewhere? (e.g. used map toggle button to go to map)
    if (focusElement) document.getElementById(focusElement).focus(); 
    // change button text/value
    $("#content-toggle-btn").attr("data-focus", "").attr("data-current", "scrolly").text("View Map");
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
        // let zoom = (view.zoom > tourOptions.maxZoom ? tourOptions.maxZoom : (view.zoom < tourOptions.minZoom ? tourOptions.minZoom : view.zoom));
        // let coords = (view.center ? L.GeoJSON.coordsToLatLng(view.center) : null);
        // if (zoom && coords) {
        if (view.bounds) {
            // TODO: Any instance where I would want to add { animate: boolean } to flyTo args? (if so, add to else flyTo statement, too)
            // map.flyTo(coords, zoom);
            map.flyToBounds(view.bounds);
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
        // map.flyTo(L.GeoJSON.coordsToLatLng(tourOptions.center), tourOptions.zoom);
        if (tourOptions.bounds) map.flyToBounds(tourOptions.bounds);
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