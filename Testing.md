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
    1. Admin (and associated)
    2. Shortcode core
3. Plugin installed and enabled
4. Create 'Test Folder'
    - Should be default page
    - Not routable
    - Folder numeric prefix enabled (should be second item)
5. Create 'Test Page'
    - Should be under 'Test Folder'
    - Use 'test' template
    - Folder numeric prefix enabled/otherwise visible
    - Important: Do not go to this page until instructed!
6. Create 'Test Tour'
    - Use 'tour' template
    - Folder numeric prefix enabled (should be third item)
    - Folder named `test-tour` (`03.test-tour` in the file system, but Grav admin won't show that)

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
5. Upload one or more images.
6. Save.
7. Remove Wymansites_copy.js (make sure it is possible to remove a dataset)
    - Ensure that the created json file is also deleted.
    - Ensure that the dataset page is moved from its previous location to the deleted_datasets folder.
8. Save.
9. Check an uploaded file to make sure coordinates have not become overly long.

Do this every time:

1. Make sure the blueprints look like they should.
2. Save.

## Dataset Config

1. Make sure pages have been created for each of the datasets.
2. Go to one of the pages:
    1. Make sure all features are listed.
    2. Make sure options are as they should be. (including all properties in the properties list)

## Create Tour

Do this once:

1. Go to the test page (on the public-facing site, not the admin panel). This will run setup as well as tests, which will populate needed settings.
2. Go to test tour and add some content.
3. Save.
4. Go to expert mode and check the header. Make sure there aren't a bunch of null items. (Utils `filter-header` function)

Do this every time:

1. Make sure the tour blueprints look like they should.
    1. Check start location dropdown. It should have lots of points listed from Wymansites and Wymancamps. It should not have any non-point features.
    2. Check add feature dropdown. It should have all the feature options.
2. Save

## Create Views

Do this once:

1. Go to each of the views, add some content, and save.
2. For view 2, make sure to use shortcodes for popup buttons for Astor Midden and Ropes Island.

Do this every time (for one of the views):

1. Make sure the view blueprints look like they should.
    1. Make sure the shortcodes list is correctly popuplated with all features in the features list.
    2. Check start location dropdown. It should have all the point options.
    3. Check add feature dropdown. It should have only options included in the tour. For example, there should only be two options for MultiPolygons.
2. Save.

## Extra

- Could be good to upload dataset, add it to a tour, add some feature overrides, then delete the dataset and make sure the tour can handle it.
- Also would be good to add a dataset to a tour, add features from the datset to a view, then remove the dataset from the tour and make sure the view can handle it.
- Do the same as above, but instead of removing the dataset, just remove the feature(s) in question (i.e. `show_all: false` and given feature(s) not in features list).

## Main Tests

1. Go to the testing page and confirm that all tests have passed.
2. Go to the tour page for the test tour.
    1. Make sure features are showing up properly (names, icons, svg options, popups work, etc.)
    2. Make sure legend has what it is supposed to.
    3. Scroll through views.
        - All views should be reachable.
        - Views should be able to hide non-view features.
        - Views should show appropriate basemaps.
        - Views should pan and zoom.
        - Shortcodes for popup buttons should work.
        - The first view should have popup buttons listed.
    4. Make sure turning off scrolling animation works.
    5. Make sure link to popups page works.
3. Go to the popups page for the test tour. Make sure the popups all show up and that any popup images (there should be at least one) show up as well. Make sure the link back to the tour also works.

## Testing Requirements

Most of the various requirements are set up in `PluginTest.php`. See the comments for more information.

For requirements related to the dataset files themselves, see below.

### Required Dataset Properties

- points1.json:
    - has 12 valid features
    - name property: name
    - properties:
        - 6 different ones (scattered throughout)
        - no property called "fu" or "veggie"
        - feature names are in the form: Point feature_number (e.g. Point 0, Point 1, etc.)
        - property id in the form: feature_number (e.g. 0, 1, etc.)
    - features:
        - point 0
            - coords: [-8.009, 10]
        - point 1
            - coords [180, 90]
        - point 2
            - coords [-20, 40] (x != 5)
            - properties.fruit: kiwi
        - point 7
            - coords [14.015, -16]
            - properties.fruit: pineapple
        - point 9
            - coords: x != 17
        - point 11
            - coords: y != -8
            - properties.fruit: pear
    - no points with coordinates: [5, 6.007], [-11, -12.013], [17, -18.019], [-20, 21.022]
- points3.json
    - has 5 valid features
- lineStrings.json
    - has 6 valid features
    - name: LineStrings Dataset
    - name property: name
    - properties
        - includes featureName
    - features:
        - line 0
            - properties.name = LineStrings 0
        - line 2
            - no name or featureName
        - line 3
            - no name
- polygons.json
    - has 4 valid features
- multiPolygons.json
    - has 4 valid features