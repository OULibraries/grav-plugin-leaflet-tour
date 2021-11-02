<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use Grav\Common\Data\Data;

class TourTest extends Test {

    /**
     * Set up the two basic tours to be called throughout the rest of the tests.
     * 
     * Requires: tour-0 and tour-1 in pages
     */
    protected function setup() {
        $config = new Data(Grav::instance()['config']->get('plugins.leaflet-tour'));
        // technically Utils::getPageRoute (with the last / removed) is only necessary for any tours that have folder numeric prefix enabled
        $pages = Grav::instance()['pages']->instances();
        $this->tour0 = new Tour($pages[substr(Utils::getPageRoute(['tour-0']),0,-1)], $config);
        $this->tour1 = new Tour($pages[substr(Utils::getPageRoute(['tour-1']),0,-1)], $config);
    }

    /**
     * Test the getBasemaps (and by extension the setBasemaps) method
     * 
     * Requires:
     *  - tour0 basemaps:
     *      - Map1873.png, Small Map.jpg, and Glot18.jpg
     *      - 2 of the above are in the tour config
     *      - 2 of the above are in one of the tour's views
     *      - no other views have basemaps
     *  - Map1873.png - South: 27.474
     *  - Small Map.jpg - minZoom: 13
     *  - Glot18.jp - maxZoom: 16
     */
    protected function testGetBasemaps() {
        $basemaps = $this->tour0->getBasemaps();
        // check number of basemaps (2 from tour, 2 from view, 1 overlap)
        $this->assertSize($basemaps, 3);
        // check basemap bounds
        $this->assertEquals($basemaps['Map1873.png']['bounds'][0][0], 27.474);
        // check basemap minZoom - set by config
        $this->assertEquals($basemaps['Small Map.jpg']['minZoom'], 13);
        // check basemap maxZoom - default
        $this->assertEquals($basemaps['Glot18.jpg']['maxZoom'], 16);
    }

    /**
     * Test the getAttribution method
     * 
     * Requires:
     *  - plugin config tile server set, and attribution provided
     *  - tour 0:
     *      - 1 attribution overwrites Map1873 attribution with the url 'google.com'
     *      - 1 additional attribution provided from one of the other basemaps
     *  - tour 1:
     *      - tile server selected from dropdown
     *      - three new attribution items: 1 only text (no url), 1 only url (no text), 1 both url and text
     *      - qgis2web attribution overwritten, no url
     *      - QGIS attribution overwritten, new-qgis-url.com
     */
    protected function testGetAttribution() {
        $attr = array_column($this->tour0->getAttribution(), null, 'name');
        // 4 defaults (4 from plugin config, 1 from plugin tile server) + 2 from basemaps
        $this->assertSize($attr, 6);
        // attribution from tour overwrites basemap attribution
        $this->assertEquals($attr['Map 1873']['url'], 'google.com');
        // defaults - 1 because of custom tile server and + 2 (one no url, one no text, one url and text, two overwriting config)
        $attr = array_column($this->tour1->getAttribution(), null, 'name');
        // 4 defaults - 1 (tile server set in tour) + 2 from tour
        $this->assertSize($attr, 5);
        // attribution item with only text is included
        $this->assertNotEmpty($attr['Attribution Item']);
        // attribution item with only url (no text) is not included
        $this->assertEmpty(array_column($this->tour1->getAttribution(), null, 'url')['no-text-url.com']);
        // attribution overwrites default attribution text with no url
        $this->assertEmpty($attr['qgis2web']['url']);
        // attribution overwrites default attribution text with new url
        $this->assertEquals($attr['QGIS']['url'], 'new-qgis-url.com');
    }

    /**
     * Test the getExtraAttribution method
     * 
     * Requires:
     *  - plugin adds one item
     *  - tour 0 adds one item
     *  - tour 1 does not add an item, but does use tile server that adds one
     */
    protected function testGetExtraAttribution() {
        // 1 from plugin, 1 from tour
        $this->assertSize($this->tour0->getExtraAttribution(), 2);
        // 1 from plugin, 1 from tile server
        $this->assertSize($this->tour1->getExtraAttribution(), 2);
    }

    /**
     * Test the getViews method
     * 
     * Requires:
     *  - tour 0, view 0:
     *      - has two basemaps
     *      - has no features
     *      - only_show_view_features: true
     *      - remove_tile_server: not set
     *  - tour 0, view 1:
     *      - has no basemaps
     *      - has 5 valid features
     *      - only_show_view_features: true
     *      - remove_tile_server: false
     *  - tour 0: remove_tile_server keeps default value of true
     */
    protected function testGetViews() {
        $views = array_values($this->tour0->getViews());
        $view0 = $views[0];
        $view1 = $views[1];
        // view basemaps
        $this->assertEmpty($view1['basemaps']);
        $this->assertSize($view0['basemaps'], 2);
        // view features
        $this->assertEmpty($view0['features']);
        $this->assertSize($view1['features'], 5); // 5 valid out of 7
        // onlyShowViewFeatures
        $this->assertFalse($view0['onlyShowViewFeatures']); // true, but no features, so false
        $this->assertTrue($view1['onlyShowViewFeatures']);
        // removeTileServer
        $this->assertTrue($view1['removeTileServer']); // default from tour
        $this->assertFalse($view0['removeTileServer']);
    }

    /**
     * Test the getDatasets method
     * 
     * Requires:
     *  - tour 0
     *      - 4 datasets
     *      - points3 dataset has a legend
     *      - points1 dataset does not have a legend
     */
    protected function testGetDatasets() {
        $datasets = $this->tour0->getDatasets();
        // correct number of datasets (4)
        $this->assertSize($datasets, 4);
        // dataset with legend (points3)
        $this->assertNotEmpty($datasets['points3.json']['legendAltText']);
        // dataset without legend (points1)
        $this->assertEmpty($datasets['points1.json']['legendAltText']);
    }

    /**
     * Test the getFeatures method
     * 
     * Requires:
     *  - tour 0
     *      - datasets: points1, points3, multiPolygons
     *      - 17 total features
     *  - features (dataset/tour)
     *      - points1_0 name: Point 0
     *      - points1_1 lat: 90
     *      - points1_3: has popup
     *      - points3_0 and multiPolygons_1 are included in tour
     */
    protected function testGetFeatures() {
        $features = $this->tour0->getFeatures();
        // correct number of features
        $this->assertSize($features, 17);
        // name
        $this->assertEquals($features['points1_0']['properties']['name'], 'Point 0');
        // dataSource
        $this->assertEquals($features['points3_0']['properties']['dataSource'], 'points3.json');
        // hasPopup
        $this->assertTrue($features['points1_3']['properties']['hasPopup']);
        // geometry type
        $this->assertEquals($features['multiPolygons_1']['geometry']['type'], 'MultiPolygon');
        // coordinates
        $this->assertEquals($features['points1_1']['geometry']['coordinates'][1], 90);
    }

    /**
     * Test the getLegend method
     * 
     * Requires:
     *  - tour 0
     *      - has legend with size of 1
     *      - legend item is for points3 dataset
     */
    protected function testGetLegend() {
        $legend = $this->tour0->getLegend();
        $this->assertSize($legend, 1);
        $this->assertEquals($legend[0]['dataSource'], 'points3.json');
    }

    /**
     * Test the getPopups method
     * 
     * Requires:
     *  - tour 0:
     *      - has 3 total popups
     *      - points1_0 popup removed
     *      - points3_0 popup provided
     *  - points1: points1_0 and points1_3 have popups
     *  - points3: points3_0 does not have popup
     */
    protected function testGetPopups() {
        // features, but none with popups
        $this->assertEmpty($this->tour1->getPopups());
        // features, some with popups
        $popups = $this->tour0->getPopups();
        $this->assertSize($popups, 3);
        // check removed popup
        $this->assertEmpty($popups['points1_0']);
        // check popup from tour
        $this->assertNotEmpty($popups['points3_0']);
        // check popup from dataset
        $this->assertNotEmpty($popups['points1_3']);
    }

    /**
     * Test the getViewPopups method
     * 
     * Requires:
     *  - tour 0 view 0: no features
     *  - tour 0 view 1: 3 valid popups
     *      - variety of valid vs. invalid features (see PluginTest.php setViews for comments with more info)
     */
    protected function testGetViewPopups() {
        $keys = array_keys($this->tour0->getViews());
        // view with no features
        $this->assertEmpty($this->tour0->getViewPopups($keys[0]));
        // view with features, some of which are valid, some of which have popups
        $this->assertSize($this->tour0->getViewPopups($keys[1]), 3);
    }

    /**
     * Test the getOptions method
     * 
     * Requires:
     *  - tour 0
     *      - maxZoom: 15, minZoom: default/not set
     *      - remove_tile_server: default/not set
     *      - basemaps: 2 added
     *      - wide_col: true
     *      - show_map_location_in_url: false
     *      - uses default tile server
     *  - tour 1 with Stamen tile server
     */
    protected function testGetOptions() {
        $options = $this->tour0->getOptions();
        $this->assertEquals($options['maxZoom'], 15); // maxZoom set by tour
        $this->assertEquals($options['minZoom'], 8); // minZoom - default
        $this->assertTrue($options['removeTileServer']); // removeTileServer - default
        $this->assertSize($options['tourMaps'], 2); // tourMaps - 2 (extra from view not included)
        $this->assertTrue($options['wideCol']); // wideCol - set to true
        $this->assertFalse($options['showMapLocationInUrl']); // showMapLocationInUrl - set to false
        // default tile server, no stamen
        $this->assertNotEmpty($this->tour0->getOptions()['tileServer']);
        $this->assertEmpty($this->tour0->getOptions()['stamenTileServer']);
        // stamen tile server, no default
        $this->assertEmpty($this->tour1->getOptions()['tileServer']);
        $this->assertNotEmpty($this->tour1->getOptions()['stamenTileServer']);
    }

    /**
     * Test the setStartingBounds method
     * 
     * Requires:
     *  - tour 0:
     *      - valid max bounds
     *      - valid bounds, start feature, and start coordinates
     *      - bounds West: 78.43
     *  - tour 1:
     *      - invalid max bounds
     *      - feature/coords set, no distance or bounds
     *  - tour 0 view 0:
     *      - start location: points3_3 (coords [11, 22])
     *      - distance: 5
     *  - tour 0 view 1: invalid start location (points2_0) (no bounds)
     *  - tour 0 view 2:
     *      - coordinates for start bounds: longitude 179.11
     *      - distance: 9
     */
    protected function testSetStartingBounds() {
        // from tour
        $this->assertNotEmpty($this->tour0->getOptions()['maxBounds']); // valid max bounds
        $this->assertEmpty($this->tour1->getOptions()['maxBounds']); // invalid max bounds (only three values provided)
        // valid bounds (feature and coordinates also valid)
        $this->assertEquals($this->tour0->getOptions()['bounds'][0][1], 78.43); // west
        // feature/coords set, but no distance
        $this->assertEmpty($this->tour1->getOptions()['bounds']);

        // from view
        $views = array_values($this->tour0->getViews());
        // feature as center (hidden feature) (invalid bounds also set, and valid coordinates)
        $bounds = Utils::setBounds(['north'=>27.0, 'south'=>17.0, 'east'=>16.0, 'west'=>6.0]);
        $this->assertNotEmpty($bounds);
        $this->assertEquals($views[0]['bounds'], $bounds); // points3_3 [11, 22], distance 5
        // invalid feature (not included in any tour dataset)
        $this->assertEmpty($views[1]['bounds']); //
        // coordinates set as center, distance causing wraparound
        $this->assertEquals($views[2]['bounds'][1][1], -171.89); // east
    }

    /**
     * Test the hasPopup method
     * 
     * Requires:
     *  - points1_2 has popup
     *  - points3_0 does not have popup
     */
    protected function testHasPopup() {
        // feature that has a popup from dataset, empty tour features list
        $this->assertTrue(Tour::hasPopup(Dataset::getDatasets()['points1.json']->getFeatures()['points1_2'], []));
        // feature that has a popup from the tour
        $this->assertTrue(Tour::hasPopup(Dataset::getDatasets()['points3.json']->getFeatures()['points3_0'], [
            ['id'=>'points1_1', 'remove_popup'=>true],
            ['id'=>'points3_0', 'popup_content'=>'exists'],
        ]));
        // feature with remove popup
        $this->assertFalse(Tour::hasPopup(Dataset::getDatasets()['points1.json']->getFeatures()['points1_2'], [['id'=>'points1_2', 'remove_popup'=>true]]));
    }
}
?>