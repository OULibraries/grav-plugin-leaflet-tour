<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Data\Data;

class DatasetTest extends Test {

    protected function setup() {
        $config = new Data(Grav::instance()['config']->get('plugins.leaflet-tour'));
        // technically Utils::getPageRoute (with the last / removed) is only necessary for any tours that have folder numeric prefix enabled
        $pages = Grav::instance()['pages']->instances();
        $this->tour0 = new Tour($pages[substr(Utils::getPageRoute(['tour-0']),0,-1)], $config);
        $this->tour1 = new Tour($pages[substr(Utils::getPageRoute(['tour-1']),0,-1)], $config);
    }

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

    protected function testGetAttribution() {
        // defaults: 3 from plugin config, 1 from plugin tile server
        // defaults + x from basemaps (with one overwriting basemap attribution)
        $attr = array_column($this->tour0->getAttribution(), null, 'name');
        $this->assertSize($attr, 6); // TODO: check
        $this->assertEquals($attr['todo']['url'], 'overwritten-by-tour.doesntexist');
        // defaults - 1 because of custom tile server and + 2 (one no url, one no text, one url and text, two overwriting config)
        $attr = array_column($this->tour1->getAttribution(), null, 'name');
        $this->assertSize($attr, 5);
        $this->assertNotEmpty($attr['Text Only']);
        $this->assertEmpty(array_column($this->tour1->getAttribution(), null, 'url')['no-text-url.com']);
        $this->assertEmpty($attr['qgis2web']['url']); // overwritten with no url
        $this->assertEquals($attr['QGIS']['url'], 'new-qgis-url.com');
    }

    protected function testGetExtraAttribution() {
        // 1 from plugin, 1 from tour
        $this->assertSize($this->tour0->getExtraAttribution(), 2);
        // 1 from plugin, 1 from tile server
        $this->assertSize($this->tour1->getExtraAttribution(), 2);
    }

    protected function testGetViews() {
        $views = array_column($this->tour0->getViews(), null);
        $view0 = $views[0];
        $view1 = $views[1];
        // view basemaps
        $this->assertEmpty($view1['basemaps']);
        $this->assertSize($view0['basemaps'], 2);
        // view features
        $this->assertEmpty($view0['features']);
        $this->assertSize($view1['features'], 5); // 5 valid out of 7
        // onlyShowViewFeatures
        $this->assertFalse($this->view0['onlyShowViewFeatures']); // true, but no features, so false
        $this->assertTrue($this->view1['onlyShowViewFeatures']);
        // removeTileServer
        $this->assertTrue($this->view1['removeTileServer']); // default from tour
        $this->assertFalse($this->view0['removeTileServer']);
    }

    protected function testGetDatasets() {
        $datasets = $this->tour0->getDatasets();
        // correct number of datasets (4)
        $this->assertSize($datasets, 4);
        // dataset with legend (points3)
        $this->assertNotEmpty($datasets['points3.json']['legendAltText']);
        // dataset without legend (points1)
        $this->assertEmpty($datasets['points1.json']['legendAltText']);
    }

    protected function testGetFeatures() {
        $features = $this->tour0->getFeatures();
        // correct number of features (TODO)
        $this->assertSize($features, 0);
        // name
        $this->assertEquals($features['points1_0']['name'], 'Point 0');
        // dataSource
        $this->assertEquals($features['points3_0']['dataSource'], 'points3.json');
        // hasPopup
        $this->assertTrue($features['points1_3']['hasPopup']);
        // geometry type
        $this->assertEquals($features['multiPolygons_1'], 'MultiPolygon');
        // coordinates
        $this->assertEquals($features['points1_1']['geometry']['coordinates'][1], 90);
    }

    protected function testGetLegend() {
        $legend = $this->tour0->getLegend();
        $this->assertSize($legend, 1);
        $this->assertEquals($legend[0]['dataSource'], 'points3.json');
    }

    protected function testGetPopups() {
        // features, but none with popups
        $this->assertEmpty($this->tour1->getPopups());
        // features, some with popups
        $popups = $this->tour0->getPopups();
        $this->assertSize($popups, 0); // todo
        // check removed popup
        $this->assertEmpty($popups['points1_0']);
        // check popup from tour
        $this->assertNotEmpty($popups['points3_0']);
        // check popup from dataset
        $this->assertNotEmpty($popups['points1_3']);
    }

    protected function testGetViewPopups() {
        $keys = array_keys($this->tour0->getViews());
        // view with no features
        $this->assertEmpty($this->tour0->getViewPopups($keys[0]));
        // view with features, some of which are valid, some of which have popups
        $this->assertSize($this->tour0->getViewPopups($keys[1]), 2); // todo
    }

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

    protected function testSetStartingBounds() {
        // from tour
        $this->assertNotEmpty($this->tour0->getOptions()['maxBounds']); // valid max bounds
        $this->assertEmpty($this->tour1->getOptions()['maxBounds']); // invalid max bounds (only three values provided)
        // valid bounds (feature and coordinates also valid)
        $this->assertEquals($this->tour0->getOptions()['bounds'][1][1], -171.89); // east TODO (3)
        // feature/coords set, but no distance
        $this->assertEmpty($this->tour1->getOptions()['bounds']);

        // from view
        $views = array_column($this->tour0->getViews(), null);
        // feature as center (hidden feature) (invalid bounds also set, and valid coordinates)
        $this->assertEquals($views[0]['bounds'], 0); // todo 5-3
        // invalid feature (not included in any tour dataset)
        $this->assertEmpty($views[1]['bounds']); // todo tour 1?
        // coordinates set as center, distance causing wraparound
        $this->assertEquals($views[2]['bounds'], 0); // todo
    }

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