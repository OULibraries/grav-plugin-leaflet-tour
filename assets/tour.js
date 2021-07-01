var tourState = {
    view: null,
    tmpView: null,
    popup: '',
    basemaps: [],
    activeBasemaps: [],
    mapAnimation: true,
    content: 'scrolly',
    storedFocus: null,
    scrollyPos: 0,
    mapNeedsAdjusting: true,
};

// TODO - potential customization options
var scrollamaOptions = {
    debug: false,
    offset: 0.33,
};

// create map
var map = L.map('map', {
    center: L.latLng(tourOptions.center),
    zoom: tourOptions.zoom,
    zoomControl: true,
    maxZoom: tourOptions.maxZoom,
    minZoom: tourOptions.minZoom,
    attributionControl: false,
})


var locationList = new Map();

$(document).ready(function() {

    $('body').addClass("scrolly-active");

    if (tourOptions.wideCol) $("body").addClass("wide-column");

if (tourOptions.revealLocation) var hash = new L.Hash(map);

// Basemaps - Tile Server
map.createPane('pane_basemap');
map.getPane('pane_basemap').style.zIndex = 400;
layer_basemap = L.tileLayer(tourOptions.tileServer, {
    pane: 'pane_basemap',
    opacity: 1.0,
    attribution: '',
    minZoom: tourOptions.minZoom,
    maxZoom: tourOptions.maxZoom,
});
map.addLayer(layer_basemap);

// set up additional image basemaps
for (let [key, basemap] of Object.entries(tourOptions.basemaps)) {
    map.createPane('pane_' + key);
    map.getPane('pane_' + key).style.zIndex = 400;
    basemap.layer = new L.imageOverlay(basemap.image, basemap.bounds, {pange: 'pane_' + key});
}
setBasemaps();

// set up map data pane
map.createPane('pane_tour');
map.getPane('pane_tour').style.zIndex = 401;
map.getPane('pane_tour').style['mix-blend-mode'] = 'normal';
// set up data layer
layer_tour = new L.geoJson(geoJson, {
    pointToLayer: function(location, latlng) {
        return L.marker(latlng, { icon: styleMarker(location)});
    },
    pane: 'pane_tour',
    attribution: '',
    interactive: true,
    dataVar: 'geoJson',
    layerName: 'layer_tour',
});
function styleMarker(location) {
    let options = tourOptions.datasets[location.properties.dataSource].iconOptions;
    // TODO: What happens if options is null?
    if (options) return L.icon(options);
    else return L.icon({
        iconAnchor: [12, 41],
        iconRetinaUrl: "user/plugins/leaflet-tour/images/marker-icon-2x.png",
        iconSize: [25, 41],
        iconUrl: "user/plugins/leaflet-tour/images/marker-icon.png",
        shadowSize: [41, 41],
        shadowUrl: "user/plugins/leaflet-tour/images/marker-shadow.png",
        className: "leaflet-marker",
        tooltipAnchor: [-12, 20]
    });
}
map.addLayer(layer_tour);

// max bounds
if (tourOptions['bounds']) map.setMaxBounds(tourOptions['bounds']);

// set up labels/deal with locations
layer_tour.eachLayer(function(layer) {
    let props = layer.feature.properties;
    let id = props.id;
    locationList.set(id, { layer: layer });
    // labels
    layer.bindTooltip((props.name !== null?String('<div aria-hidden="true">' + props.name) + '</div>':''), {
        permanent: true,
        offset: [-0, -16],
        className: props.dataSource,
    });
    labels.push(layer);
    addLabel(layer, totalMarkers);
    layer.added = true;
    totalMarkers++;
    // icons
    let icon = layer._icon;
    $(icon).attr('data-location', id);
    $(icon).attr('id', id + '-marker-icon');
    let iconAlt = props.name;
    if (tourOptions.legend) {
        let legendAlt = tourOptions.datasets[props.dataSource].legendAlt;
        if (legendAlt) iconAlt = iconAlt + ", " + legendAlt;
    }
    if (props.hasPopup) {
        iconAlt = iconAlt + ", open popup";
        $(icon).attr("role", "button");
        $(icon).addClass("has-popup");
    } else {
        $(icon).addClass("no-popup");
    }
    $(icon).attr("alt", iconAlt);
});
resetLabels([layer_tour]);

// icon shadows
$(".leaflet-marker-shadow").attr("aria-hidden", "true");

// map "on" functions
map.on("layeradd", function(){
    resetLabels([layer_tour]);
});
map.on("layerremove", function(){
    resetLabels([layer_tour]);
});
map.on("zoomend", function(){
    resetLabels([layer_tour]);
    checkBasemaps();
    $(".leaflet-control-zoom a").removeAttr("aria-disabled");
    $(".leaflet-disabled").attr("aria-disabled", "true");
});

// move controls for more sensible DOM order
let controls = $(".leaflet-control-container");
controls.remove();
$("#map").prepend(controls);

// scrollama
scroller = scrollama();
scroller.setup({
    step: "#scrolly #scroll-text .step",
    offset: scrollamaOptions.offset,
    debug: scrollamaOptions.debug
}).onStepEnter(function(e) {
    // use timeout function so that if multiple views are scrolled through at once, only the last view will be truly entered
    tourState.tmpView = e.element.getAttribute("id");
    setTimeout(function(id) {
        if (tourState.tmpView === id) {
            enterView(id);
            tourState.mapNeedsAdjusting = true;
        }
    }, 500, tourState.tmpView);
}).onStepExit(function(e) {
    // use timeout function to ensure that exitView is only called when a view is exited but no new view is entered
    tourState.tmpView = null;
    setTimeout(function(id) {
        if (!tourState.tmpView) {
            exitView();
            tourState.mapNeedsAdjusting = true;
        }
    }, 600, e.element.getAttribute("id"));
});

// ensure that first view doesn't start too soon and that last view can be triggered
let targetHeight = Math.round(scrollamaOptions.offset*window.innerHeight+40);
let scrolltopHeight = $(".scroll-top")[0].offsetHeight + $("header")[0].offsetHeight;
if (targetHeight > scrolltopHeight) $(".scroll-top").css("padding-top", targetHeight-scrolltopHeight);
// last view
targetHeight = Math.round((1 - scrollamaOptions.offset)*window.innerHeight + 40);
let viewHeight = $("footer")[0].offsetHeight + $(".step")[$(".step").length-1].offsetHeight;
if (targetHeight > viewHeight) $("footer").css("margin-bottom", targetHeight-viewHeight);

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
            else if (tourState.content === "scrolly") tourState.scrollyPos = window.scrollY;
            current.scrollTick = false;
        }, 100);
    }
    current.scrollTick = true;
}

$("#header-toggle").on("click", function(e) {
    $('body').toggleClass('menu-hidden-mobile');
    $(this).toggleClass('expanded');
    let expanded = ($(this).attr('aria-expanded')==='true');
    $(this).attr('aria-expanded', expanded);
    window.localStorage.setItem('headerExpanded', expanded);
});
if (window.localStorage.getItem('headerExpanded') === 'false') $("#header-toggle").click();

$("#scrolly-toggle").on("click", function(e) {
    toggleContent("scrolly");
    window.scrollTo(0, tourState.scrollyPos);
});
$("#map-toggle").on("click", function(e) {
    toggleContent("map");
    adjustMap();
});
$("#popups-toggle").on("click", function(e) {
    toggleContent("popups");
    window.scrollTo(0, 0);
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
    let dataset = $(this).attr("value");
    for (let location of locationList.values()) {
        if (location.layer.feature.properties.dataSource === dataset) {
            location['hideDataSource'] = !this.checked;
            toggleHideFeature(location);
        }
    }
    resetLabels([layer_tour]);
});

// TODO: settings/modal - https://www.w3.org/TR/wai-aria-practices-1.1/examples/dialog-modal/dialog.html
$("#open-settings-btn").on("click", function(e) {
    // TODO:
});
$("#close-settings-btn").on("click", function(e) {
    // TODO:
});

// other buttons
$("#reset-view-btn").on("click", exitView);

$(".show-view-btn").on("click", function(e) {
    if (window.innerWidth < mobileWidth) { // constant from theme
        // on mobile, store button focus and toggle content
        let tmpFocus = document.activeElement;
        tourState.storedFocus = null;
        toggleContent("map");
    }
    enterView($(this).attr("data-view"));
    if (typeof tmpFocus !== "undefined") tourState.storedFocus = tmpFocus;
});

// map icon focus and active tooltips
// allow focusing on map icons that aren't buttons
$(".leaflet-marker-pane .leaflet-marker.no-popup").on("click keydown", function(e) {
    if (e.type === "click" || e.which === 32 || e.which === 13) {
        let id = $(this).attr("data-location");
        locationList.get(id).layer._icon.focus();
        setActiveTooltip(id);
    }
});

// make tooltip active when focusing/hovering over icon
$(".leaflet-marker-pane .leaflet-marker").on("focus mouseover", function(e) {
    setActiveTooltip($(this).attr("data-location"));
});
// make tooltip inactive when ending focus/hover over icon
$(".leaflet-marker-pane .leaflet-marker").on("blur mouseout", function(e) {
    if (!(this === document.activeElement)) endActiveTooltip($(this).attr("data-location"));
});

// popups
$("#all-popups-btn").on("click", function(e) {
    $("#popup-list-wrapper").addClass("show-all");
    toggleContent('popups');
});
$(".view-popup-btn").on("click", function(e) {
    openPopup($(this).attr("data-location"));
});
// make icons open popups from map
$(".leaflet-marker-pane .leaflet-marker.has-popup").on("click keydown", function(e) {
    if (e.type === "click" || e.which === 32 || e.which === 13) {
        openPopup($(this).attr("data-location"));
    }
});
// popups
$(".popup-back-btn").on("click", leavePopup);
$(".popup-close-btn").on("click", closePopup);

});

// functions for setting and ending active tooltip/icon
function setActiveTooltip(id) {
    locationList.get(id).layer.getTooltip().getElement().classList.add("active");
}
function endActiveTooltip(id) {
    if (window.innerWidth >= mobileWidth && tourState.popup === id) return;
    locationList.get(id).layer.getTooltip().getElement().classList.remove("active");
}

// popup functions
function openPopup(id) {
    let tmpFocus = document.activeElement;
    current.storedFocus = null;
    if (!tourState.popup || tourState.popup !== id) {
        closePopup(false);
        tourState.popup = id;
        $("#" + id + "-popup").addClass("active");
        setActiveTooltip(id);
    }
    if (window.innerWidth < mobileWidth) {
        toggleContent('popups');
        $("#popup-list-wrapper").removeClass("show-all");
    }
    // can't use returnFocus, need to focus specifically on the popup
    $("#" + id + "-popup h3").focus();
    tourState.storedFocus = tmpFocus;
}
function leavePopup() {
    // while map vs. scrolly isn't important for desktop, calling toggleContent() will also call returnFocus()
    if ($(tourState.storedFocus).parents("#map-wrapper").length > 0) toggleContent("map");
    else toggleContent("scrolly");
}
function closePopup(leave=true) {
    if (tourState.popup) {
        let id = tourState.popup;
        $("#"+id+"-popup").removeClass("active");
        tourState.popup = null;
        // remove active from associated tooltip - calling method has to happen after tourState.popup is set to null
        endActiveTooltip(id);
        // leave might not be true if the popup is only being closed so that a new one can be opened
        if (leave) leavePopup();
    }
}

/**
 * 
 * @param {string} id - scrolly, map, or popups
 */
function toggleContent(id) {
    $("#toggle-list button").removeClass("btn-disabled").removeAttr("aria-disabled aria-current");
    $("#"+id + "-toggle").addClass("btn-disabled").attr("aria-disabled", "true").attr("aria-current", "true");
    $("body").removeClass("scrolly-active map-active popups-active").addClass(id+"-active");
    tourState.content = id;
    returnFocus();
    // TODO: should prev element be stored for each section, or just most recent?
}

function toggleHideFeature(feature) {
    let hide = (feature.hideDataSource || feature.hideView);
    let tooltip = feature.layer.getTooltip().getElement();
    let icon = feature.layer._icon;
    let shadow = feature.layer._shadow;
    $(tooltip).add(icon).add(shadow).attr("aria-hidden", hide).css("display", (hide ? 'none' : 'block'));
}

function toggleHideNonViewFeatures(viewId, hide) {
    let view = tourViews[viewId];
    for (let [featureId, feature] of locationlist) {
        if (!view.locations.includes(featureId)) {
            // toggle
            location['hideNonView'] = hide;
            toggleHideFeature(feature);
        }
    }
}

function enterView(id) {
    tourState.view = id;
    let view = tourViews[id];
    if (view.onlyViewLocs) toggleHideNonViewFeatures(true);
    // TODO: should map animation toggle the animation of flyTo or should it toggle changing the zoom at all?
    let zoom = (view.zoom > tourOptions.maxZoom ? tourOptions.maxZoom : (view.zoom < tourOptions.minZoom ? tourOptions.minZoom : view.zoom));
    let coords = (view.center ? L.latLng(view.center) : null);
    // TODO: either wrap this in "if (animate)" clause or add { animate: boolean } to flyTo args
    if (zoom && coords) {
        map.flyTo(coords, zoom);
        // TODO: If zoom doesn't change, does onZoomEnd function still get called and checkBasemaps()? And if it does, should I do something to prevent it?
    }
    // adjust basemaps - call here because we need setBasemaps(), not just checkBasemaps()
    setBasemaps();
}

// should only really be used when exiting views completely - not entering a new view
function exitView() {
    if (!tourState.view) return; // already no view
    if (tourViews[tourState.view].onlyViewLocs) toggleHideNonViewFeatures(false);
    tourState.view = null;
    closePopup();
    // TODO: do this only if animate or pass animate as an option
    map.flyTo(L.latLng(tourOptions.center), tourOptions.zoom);
}

// Determines the correct basemaps based on the view
function setBasemaps() {
    // clear out basemap list
    tourState.basemaps = [];
    // tour basemaps
    if (!tourState.view || !tourViews[tourState.view].noTourBasemaps) {
        for (let basemap of tourOptions.tourMaps) {
            tourState.basemaps.push(basemap);
        }
    }
    // view basemaps
    if (tourState.view) {
        for (let basemap of tourViews[tourState.view].basemaps) {
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
        let basemap_info = tourOptions.basemaps[basemap];
        if (map.getZoom() >= basemap_info.minZoom && map.getZoom() <= basemap_info.maxZoom) newBasemaps.push(basemap);
    }
    // remove basemaps if necessary
    for (let basemap of tourState.activeBasemaps) {
        if (!newBasemaps.includes(basemap)) map.removeLayer(tourOptions.basemaps[basemap].layer);
    }
    // add basemaps
    tourState.activeBasemaps = [];
    for (let basemap of newBasemaps) {
        tourState.activeBasemaps.push(basemap);
        let layer = tourOptions.basemaps[basemap].layer;
        if (!map.hasLayer(layer)) map.addLayer(layer);
    }
    // check default basemap
    if (tourState.activeBasemaps.length > 0 && ((tourState.view && tourViews[tourState.view].removeDefaultBasemap) || (tourOptions.removeDefaultBasemap))) {
        if (map.hasLayer(layer_basemap)) map.removeLayer(layer_basemap);
    } else {
        if (!map.hasLayer(layer_basemap)) map.addLayer(layer_basemap);
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

function returnFocus() {
    if (!tourState.storedFocus || tourState.content === 'popups') return;
    let content = "#scrolly";
    if (tourState.content === "map") content = "#map-wrapper";
    if ($(tourState.storedFocus).parents(content).length > 0) {
        tourState.storedFocus.focus();
        tourState.storedFocus = null;
    }
}