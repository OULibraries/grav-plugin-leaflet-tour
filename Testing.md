# Testing Outline for Leaflet Tour Plugin

1. Setup
2. Plugin config
3. Create tour
4. Create view
5. Dataset
6. Run main suite of tests
7. Check sample popups page
8. Check sample tour page

## Setup

Do this once:

1. Theme is set to the basic theme.
2. Plugin dependencies installed
    1. Admin
    2. Shortcode core
3. Plugin installed and enabled

Do this every time:

1. Clear cache.

## Plugin Config

Do this once:

1. Upload sample datasets.
    - Wymansites.js
    - Wymancamps.js
    - SceneCentroids.js
    - Loci.js
    - water.js
    - points1.json
    - points2.js
    - points3.json
    - Polygons.json
    - Multi Points.json
    - LineStrings.json
    - MultiLineStrings.json
    - MultiPolygons.json
    - test1.js
    - test2.json
    - test3.json
    - Wymansites_copy.js
2. Upload icon markers.
    - Wymancamps.png
    - Wymansites.png
    - PlaceNames.png
    - SceneCentroids.png
3. Upload icon shadows.
    - Wymancamps.png
4. Upload basemaps.
    - Map1873.png
    - Glot18.jpg
    - Small Map.jpg
    - LakeMonroe.jpg
    - VoCo.jpeg
    - Map1873_copy.png
5. Save.
6. Remove Wymansites_copy.js (make sure it is possible to remove a dataset)
7. Save.

Do this every time:

1. Make sure the blueprints look like they should.
2. Save.

## Create Tour

Do this once:

1. Create new tour called tour-0. (Should be able to select template from admin panel).
2. Choose settings.
    1. Pick a few icon settings.
    2. Choose multiple datasets.
        - MultiPolygons (show all)
        - points1 (not show all)
        - points2 (not show all)
3. Save

Do this every time:

1. Make sure the tour blueprints look like they should.
    1. Check icon and path settings (tour 3).
        - points1 icon file set
        - misc path options for MultiPolygons
    2. Check start location dropdown (tour 0)
        - everything in points1 and points 2 - 13 options
    3. Check add feature dropdown (tour 0)
        - everything in points1, points2, and MultiPolygons - 17 options (-1 b/c feature with name: false)
2. Save

## Create View

Do this once:

1. Create new view under tour-0 called view. (Should be able to select template from admin panel).
2. Choose settings.
    1. Pick a few features to add to the view (doesn't matter which).
3. Save.

Do this every time:

1. Go to the view page and make sure the blueprints look like they should (tour 0 - view).
    1. Make sure the shortcodes list is correctly popuplated with all features in the features list.
    2. Start location drodpown has 13 options (points1 and points2).
    3. Add feature dropdown has 17 options (points1, points2, and MultiPolygons) (-1 b/c feature with name: false).
2. Save.

## Dataset

Do this every time:

1. Go to a dataset page (Wymancamps) and make sure the blueprints look like they should.
    1. Check properties dropdown (NAME, TYPE, YEAR_, SOURCE, Y1867).
    2. Check features list (19 features).
    3. Check icon and path options.
2. Save.

## Main Tests

1. Go to the testing page and confirm that all tests have passed

## Popups

1. Go to the popups page for (TODO: tour)
2. TODO: List things to check

## Tour

1. Go to the tour page for (TODO: tour) (need to build out a nice looking tour that has all the features one could want)
2. TODO: List things to check
    - view popups buttons

## Notes for Testing Suite

- required page structure
    - tour-1 as top level page (with folder numeric prefix enabled)
    - _view-1 inside tour-1
    - modules/footer included

Start with setup. This will also serve as a test, but mostly will make sure settings are correct for future tests.
- Add/modify tour pages
- Update popups pages
- Add/modify view pages
- Modify plugin settings
- Modify dataset pages
- Do dataset update
    - check before update that Polygons_1 fruit is apple and that Polygons_2 does not have fruit property
    - (points3_3 - lat - 22 to 42)
    - (MultiPolygons_3 - [[[[1,2],[2,3],[3,4],[1,2]]]] to [[[[1,5],[2,3],[3,4],[1,5]]]])
    - title updates (and changes dataset name)
    - name property updates (and feature names update with it)
    - name property only works if it is a valid property
    - datasetFileRoute updates - try relocating dataset file
    - features update
- at the end, undo the dataset update

### LeafletTourPlugin

- getDatasetFiles
    - returns needed array
- getBasemaps
    - returns all basemaps whose info has been set and none that haven't
- getTileServers
    - returns the appropriate list
    - returns the correct tile server on $key input

### Dataset

## Testing Dataset Notes

Number of valid features and the feature type are included in parantheses.

- Wymansites.js (23, Point)
    - name prop: NAME
    - feature settings
        - 4 - has popup
- Wymancamps.js (19, Point)
    - name prop: NAME
    - feature settings:
        - 2 - has popup
- SceneCentroids.js (9, Point)
    - name prop: Section
    - feature settings:
        - 0 - hide feature
- Loci.js (7, MultiPolygon)
    - name prop: Locus
- water.js (1, MultiPolygon)
    - name prop: OBJECTID
- points1.json (7, Point)
    - name: Points Dataset One (modified from original)
    - name prop: FeatureName
        - also includes prop NameOfFeature
    - invalid features: 2
        - incorrect geometry type
        - invalid coordinates (mixed up long/lat)
    - feature notes:
        - coordinates contain edge cases (min/max, very long, zero)
    - feature settings:
        - 0 - no popup
        - 3
            - name: Point 4
            - has popup
            - lat: 90
    - icon settings:
        - icon file: set
        - tooltip anchor: -5, 5
        - class: 'points1-test-class'
        - shadow size: 10, 8
        - icon width: 20
        - shadow file: set
        - icon anchor: x and y set
        - icon alt: 'points 1 icon alt'
    - legend: none
- points2.js (6, Point)
    - name: Points Dataset Two (modified in update - none to start with)
    - name prop: Feature
    - notes:
        - has additional js code before variable
    - icon settings:
        - icon alt: 'points 2 icon alt'
        - icon file, anchor, size, shadow: not set
        - shadow anchor: only x set
    - legend
        - text: 'points 2 legend text'
        - alt: 'points 2 legend alt - dataset'
- points3.js (5, Point)
    - name: Points Dataset Three
    - name prop: N A M E
        - not the first property
    - invalid features: 2
        - no geometry type
        - no coordinates
    - feature settings:
        - 0 - has popup - 'points 3 0 dataset popup'
        - 1 - has popup
        - 2 - has popup
        - 3 - does not have popup
        - 4 - has popup
    - icon settings:
        - alt: none
        - file: set
        - height: 16 (width not set)
        - anchor: only y set
        - shadow: not set
- Polygons.json (5, Polygon)
    - name: Polygons
    - name prop: NameOfFeature
    - invalid features: 1
        - no geometry
    - feature notes:
        - non-matching properties - 3 standard, 3 random
    - feature settings:
        - no features have popups
    - path settings: default only
- Multi Points.json (4, MultiPoint)
    - name prop: Name
        - featureName ahead of Name
        - nameoffeature after Name
    - invalid features: 1
        - just a string
- LineStrings.json (4, LineString)
    - name prop: Name
    - invalid features: 1
        - empty feature
    - feature notes:
        - includes property specialProp@&%^()!--
        - feature with numeric name
        - feature with bool name (true)
        - legend settings:
            - text: 'Line Strings Legend'
            - alt: none
        - active path options:
            - weight: 3
            - fillOpacity: null
    - feature settings:
        - 1 - no popup
- MultiLineStrings.json (4, MultiLineString)
    - name prop: Name
    - invalid features: 1
        - incorrect type
    - feature notes:
        - feature with empty name
        - feature missing name property
    - path settings
        - stroke: false
        - fillColor: #ffffff
- MultiPolygons.json (4, MultiPolygon)
    - name prop: Name
    - invalid features: 1
        - invalid coordinates
    - feature notes:
        - feature with bool name (false) (first feature)
    - feature settings
        - no popups
    - legend: none
    - path options:
        - stroke: true
        - color: #445566
        - weight: 3
        - opacity: 1
        - fill: true
        - fillColor: null
        - fillOpacity: 0.2
    - active path options:
        - stroke: true
        - weight: 4
        - fillColor: #334455
        - color, opacity, fill, fillOpacity: null
- test1.js (invalid)
- test2.json (invalid)
- test3.json (no valid features?)

## Testing Tour Notes

- tour 0
    - datasets:
        - points1 (not show all)
            - icon - use defaults
                - alt: none
                - anchor: only x set
                - file, height: not set
        - points2 (not show all)
            - legend text: 'tour 0 legend for points2'
            - legend alt: none
        - MultiPolygons (show all)
    - features
        - points1_0
        - points1_3
        - points1_4
        - MultiPolygons_1
        - MultiPolygons_2
    - start
        - coords: none
        - location: points1_2 (lat: 0)
        - distance: 8
    - remove tile server: default
    - long attribution: +1
    - tile server: stamen
- tour 1
    - datasets: none
    - basemaps: none
    - zoom: max 15, min default
    - maxBounds - valid (south: 0)
    - start:
        - bounds: invalid (only three values)
        - distance: 10
        - coordinates: set (lat: 60.111)
    - tile server: not set
    - attribution: overwriting
        - qgis2web - no url
        - QGIS - fakeurl.com
- tour 2
    - datasets:
        - points1 (not show all)
            - legend text: anything
            - legend alt: 'points 1 legend alt - tour'
            - icon
                - file: set
                - anchor: only y set
        - points2 (not show all)
            - legend: none
    - basemaps: 1
        - LakeMonroe.jpg (has attribution)
    - features: none
    - long attribution: +1
    - maxBounds - invalid (only 3 values)
    - start:
        - location: invalid
        - bounds, coordinates: not set
        - distance: set
    - tile server: default url
- tour 3
    - datasets:
        - points1 (show all)
            - icon - use defaults
                - file: set
                - size: not set
        - MultiPolygons (show all)
            - path options
                - stroke: true
                - weight: 2
                - fill: false
                - color, opacity, fillColor, fillOpacity: null
            - active path options
                - stroke: true
                - color: #112233
                - fill: true
                - fillOpacity: 0.5
                - weight, opacity, fillColor: null
    - basemaps: none
    - features: some (doesn't matter which)
    - attribution: +2
    - wideCol: true
    - show map in url: false
    - start: valid coordinates, no distance
    - notes:
        - has 1 view (has 1 basemap with attribution)
        - no features have popups
- tour 4
    - datasets
        - points1 (show all)
            - legend: none
            - icon
                - width: 18
                - shadow height: 2
                - tooltip anchor, shadow width, class, shadow file, icon anchor, icon file: not set
        - points2 (show all)
            - legend: none
            - icon
                - file: set
                - anchor: only x set
                - height: not set
        - MultiPolygons (not show all)
            - legend: none
    - basemaps: 2
        - Map1873.png
        - Small Map.jpg
    - features: none
    - attribution: +2
        - Attribution Item, no url
        - no text, myfakeurl.com
    - start:
        - valid coordinates
        - distance: 3.5
        - location: points2_1 (lat: 11)
        - bounds: invalid (lat too low)
    - notes
        - one view with two basemaps (Small Map.jpg and Glot18.jpg)
- tour 5
    - datasets
        - points2 (not show all)
            - legend: none
            - icon size, shadow anchor, shadow: not set
        - points3 (show all)
            - legend text: 'points3 legend'
            - legend alt: 'points 3 legend alt'
            - icon
                - anchor: only x set
                - width, shadow: not set
        - Polygons (show all)
            - legend: none
            - path options: fill color not set
    - basemaps: none
    - features:
        - Polygons_0 - popup added
        - points3_1 - popup removed
        - points3_0 - in list, no popup settings checked
        - points3_3 - popup added, also remove popup
        - Polygons_1 - popup added
        - points3_4 - popup overwritten ('tour 5 popup for points3_4')
        - add a couple points2 (not points2_0) (1, 2)
    - start:
        - valid location, coordinates, distance
        - valid bounds (west: 123.456)
    - only show view features, remove tile server: default

## Testing View Notes

Two of the tours each have one view. The views listed below all belong to tour 5.

- view 1
    - features: none
    - basemaps: none
    - start: valid coords, no distance
    - only show view features: true
    - remove tile server: not set
- view 2
    - features: at least one
    - start: valid bounds (south: 21.55)
    - only show view features: not set
    - no tour basemaps: true
- view 3
    - features: 5
        - 3 have popups
    - basemaps: 2
    - start:
        - coords: valid
        - location: points3_0 (long: 179.11)
        - distance: 9
    - only show view features: true
    - remove tile server: false
- view 4
    - features: at least one
        - none have popups
    - start:
        - location: points2_0 (from hidden features)
        - distance: set
- view 5
    - features: some
        - 2 valid features that have popups
        - 1 or more valid features without popups
        - several features not in the tour, at least some of which have popups
    - start:
        - location: points1 (any) (not in tour features)
        - distance: set
- view 6
    - start:
        - location: Polygons (any) (not a point)
        - distance: set

## Testing Basemap Notes

- basemap files (and data)
    - Map1873.png
        - bounds: `[[27.474,-83.47],[30.94,-80.35]]`
        - zoom: 11, 8
        - attribution: Map 1873, no url
    - Glot18.jpg
        - bounds: `[[28.873378634,-81.367955392],[28.972581275,-81.262076589]]`
        - zoom: default
        - no attribution
    - Small Map.jpg
        - bounds: `[[28.873,-81.368],[28.973,-81.262]]`
        - zoom: 16, 13
        - attribution: Small Map, libraries.ou.edu
    - LakeMonroe.jpg
        - bounds: `[[27, -83], [30, -80]]`
        - zoom: 15, 10
        - attribution: Lake Monroe, https://libraries.ou.edu
    - VoCo.jpeg
        - bounds: `[[28, -81], [28.3, -80.8]]`
        - zoom: 8, 8
        - no attribution
    - Map1873_copy.png
        - no bounds/data

Tile server:
- url: https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}
- attribution: ArcGIS World, https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer