<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\File;

class UtilsTest extends Test {

    // put together some datasets for testing
    protected function setup() {
        $this->lateGeometrySet = [
            'type'=>'FeatureCollection',
            'name'=>'Late Geometry',
            'features'=>[
                [
                    'type'=>'Feature',
                    'properties'=>['name'=>'invalid feature 0'],
                    'geometry'=>['coordinates'=>[[1.001, -1.001], [2, 2], [3, 3]]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['name'=>'invalid feature 1'],
                ],[
                    'invalid feature 2',
                ],[
                    'type'=>'Feature',
                    'properties'=>['name'=>'Valid Feature 0'],
                    'geometry'=>['type'=>'LineString', 'coordinates'=>[[-2.002, 2.002], [2, 2], [3, 3]]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['name'=>'Valid Feature 1'],
                    'geometry'=>['type'=>'LineString', 'coordinates'=>[[3.003, -3.003], [2, 2], [3, 3]]],
                ],
            ],
        ];
        $this->featureCounterSet = [
            'type'=>'FeatureCollection',
            'name'=>'Feature Counter Already Set',
            'featureCounter'=>12,
            'features'=>[
                [
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 0'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-4.004, 4.004]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 1'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[5.005, -5.005]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 2'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-6.006, 6.006]],
                ],
            ]
        ];
        // some invalid features, property variation, property of name
        $this->pointSet = Dataset::getDatasets()['points1.json'];
        $this->nameXSet = [
            'type'=>'FeatureCollection',
            'name'=>'nameOfFeature',
            'features'=>[
                [
                    'type'=>'Feature',
                    'properties'=>['x'=>'x0', 'nameOfFeature'=>'Point 0'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-4.004, 4.004]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['x'=>'x1', 'nameOfFeature'=>'Point 1'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[5.005, -5.005]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['x'=>'x2', 'nameOfFeature'=>'Point 2'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-6.006, 6.006]],
                ],
            ]
        ];
        $this->xNameNameXSet = [
            'type'=>'FeatureCollection',
            'name'=>'featureName then nameOfFeature',
            'features'=>[
                [
                    'type'=>'Feature',
                    'properties'=>['x'=>'x0', 'featureName'=>'Feature 0', 'nameOfFeature'=>'Point 0'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-4.004, 4.004]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['x'=>'x1', 'featureName'=>'Feature 1', 'nameOfFeature'=>'Point 1'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[5.005, -5.005]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['x'=>'x2', 'featureName'=>'Feature 2', 'nameOfFeature'=>'Point 2'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-6.006, 6.006]],
                ],
            ]
        ];
        $this->xNameNameSet = [
            'type'=>'FeatureCollection',
            'name'=>'featureName then name',
            'features'=>[
                [
                    'type'=>'Feature',
                    'properties'=>['x'=>'x0', 'featureName'=>'Feature 0', 'name'=>'Point 0'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-4.004, 4.004]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['x'=>'x1', 'featureName'=>'Feature 1', 'name'=>'Point 1'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[5.005, -5.005]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['x'=>'x2', 'featureName'=>'Feature 2', 'name'=>'Point 2'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-6.006, 6.006]],
                ],
            ]
        ];
        $this->noNameSet = [
            'type'=>'FeatureCollection',
            'name'=>'No Name',
            'features'=>[
                [
                    'type'=>'Feature',
                    'properties'=>['x'=>'x0'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-4.004, 4.004]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['x'=>'x1'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[5.005, -5.005]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['x'=>'x2'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-6.006, 6.006]],
                ],
            ]
        ];
        $this->noPropSet = [
            'type'=>'FeatureCollection',
            'name'=>'No Properties',
            'features'=>[
                [
                    'type'=>'Feature',
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-4.004, 4.004]],
                ],[
                    'type'=>'Feature',
                    'geometry'=>['type'=>'Point', 'coordinates'=>[5.005, -5.005]],
                ],[
                    'type'=>'Feature',
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-6.006, 6.006]],
                ],
            ]
        ];

        $this->updateArray = [
            'type'=>'FeatureCollection',
            'name'=>'Points Replacement',
            'features'=>[
                [
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 2'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[5, 6.007]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 12'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-8.009, 10]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 13', 'fruit'=>'orange'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-11, -12.013]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 5', 'fruit'=>'banana'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[14.015, -16]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 11', 'fruit'=>''],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[17, -18.019]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 14'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-20, 21.022]],
                ],
            ],
        ];
    }
    
    protected function testIsValidPoint() {
        // general invalid input
        $this->assertNull(Utils::setValidCoordinates('string', 'point'));
        $this->assertNull(Utils::setValidCoordinates([1], 'point'));
        $this->assertNull(Utils::setValidCoordinates([5, 10, 15], 'point'));
        $this->assertNull(Utils::setValidCoordinates(['string1', 'string2'], 'point'));
        // invalid latitude or longitude
        $this->assertNull(Utils::setValidCoordinates([180.01, 64], 'point'));
        $this->assertNull(Utils::setValidCoordinates([-200, 64], 'point'));
        $this->assertNull(Utils::setValidCoordinates([64, 120], 'point'));
        $this->assertNull(Utils::setValidCoordinates([64, -90.05], 'point'));
        // general case
        $this->assertNotEmpty(Utils::setValidCoordinates([120, 64], 'point'));
        $this->assertNotEmpty(Utils::setValidCoordinates([-120, -64], 'point'));
        // edge cases
        $this->assertEquals(Utils::setValidCoordinates([180, 64], 'point'), [180, 64]);
        $this->assertNotEmpty(Utils::setValidCoordinates([64, -90], 'point'));
        $this->assertNotEmpty(Utils::setValidCoordinates([-180, 90], 'x'));
        $this->assertNotEmpty(Utils::setValidCoordinates([0, 0], 'point'));
        $this->assertNotEmpty(Utils::setValidCoordinates([95.32952385683943, 10.328943899523], 'point'));
        // reverse
        // $this->assertNotEmpty(Utils::setValidCoordinates([64, 120], 'point'));
        // $this->assertNull(Utils::setValidCoordinates([120, 64], 'point'));
    }

    protected function testIsValidLineString() {
        $point = [120, 64];
        $invalidPoint = [64, 120];
        $this->assertNull(Utils::setValidCoordinates([$point, $point, 'string'], 'linestring'));
        $this->assertNull(Utils::setValidCoordinates([$point], 'linestring'));
        $this->assertNull(Utils::setValidCoordinates([$point, $invalidPoint], 'linestring'));
        $this->assertNotEmpty(Utils::setValidCoordinates([$point, $point], 'linestring'));
        $this->assertSize(Utils::setValidCoordinates([$point, $point, $point, $point, $point], 'linestring'), 5);
    }

    protected function testIsValidMultiLineString() {
        $line = [[120, 64], [64, 64], [20, -30]];
        $point = [120, 64];
        $this->assertNull(Utils::setValidCoordinates([[$point, $point, [64, 120]], $line, $line], 'multilinestring'));
        $this->assertNull(Utils::setValidCoordinates($line, 'multilinestring'));
        $this->assertNotEmpty(Utils::setValidCoordinates([$line], 'multilinestring'));
    }

    protected function testSetValidLinearRing() {
        $this->assertNull(Utils::setValidCoordinates([[[112, 60], [64, 64]]], 'polygon'));
        $this->assertNull(Utils::setValidCoordinates([[[112, 60], [64, 64], [112, 60]]], 'polygon'));
        $line1 = [[112, 60], [64, 64], [20, -30]];
        $this->assertNull(Utils::setValidCoordinates([[$line1, $line1, $line1, $line1]], 'polygon'));
        $this->assertNull(Utils::setValidCoordinates([[[112, 60], [64, 64], [64, 120], [112, 60]]], 'polygon'));
        $line2 = [[112, 60], [64, 64], [20, -30], [112, 60]];
        $this->assertEquals(Utils::setValidCoordinates([$line1], 'polygon'), [$line2]);
        $this->assertEquals(Utils::setValidCoordinates([$line2], 'polygon'), [$line2]);
    }

    protected function testSetValidPolygon() {
        $ring = [[180, 80], [120, -80], [-89, 0], [0, 22.3453], [180, 80]];
        $line = [[112, 60], [64, 64], [20, -30]];
        $this->assertNull(Utils::setValidCoordinates([], 'polygon'));
        $this->assertNull(Utils::setValidCoordinates([$line, $ring, [50, 20]], 'polygon'));
        $this->assertNotEmpty(Utils::setValidCoordinates([$ring], 'polygon'));
        $this->assertNotEmpty(Utils::setValidCoordinates([$ring, $line, $ring, $line], 'polygon'));
    }

    // huge MultiPolygon will be tested in one of the sample datasets
    protected function testSetValidMultiPolygon() {
        $ring = [[180, 80], [120, -80], [-89, 0], [0, 22.3453], [180, 80]];
        $line = [[112, 60], [64, 64], [20, -30]];
        $polygon1 = [$ring];
        $polygon2 = [$ring, $line, $line];
        $this->assertNull(Utils::setValidCoordinates(54, 'multipolygon'));
        $this->assertNull(Utils::setValidCoordinates([[$line], [[[112, 60], [64, 64], [20, -30], [64, 120]]]], 'multipolygon'));
        $this->assertNotEmpty(Utils::setValidCoordinates([$polygon1], 'multipolygon'));
        $this->assertNotEmpty(Utils::setValidCoordinates([$polygon1, $polygon2, $polygon2, $polygon1], 'multipolygon'));
    }

    protected function testSetValidCoordinates() {
        $line = [[112, 60], [64, 64], [20, -30]];
        $this->assertNull(Utils::setValidCoordinates($line, 'point'));
        $this->assertNull(Utils::setValidCoordinates([$line, $line], 'linestring'));
        $this->assertNull(Utils::setValidCoordinates([112, 60], 'multipolygon'));
    }

    protected function testSetValidType() {
        $this->assertEquals(Utils::setValidType('random string'), 'Point');
        $this->assertEquals(Utils::setValidType('multilinestring'), 'MultiLineString');
        $this->assertEquals(Utils::setValidType('multiLINEstring'), 'MultiLineString');
    }

    protected function testSetBounds() {
        $tooShort = ['south'=>87, 'west'=>-100, 'east'=>50];
        $noKeys = [87, -100, -0.1, 50];
        $incorrectKeys = ['s'=>87, 'w'=>-100, 'n'=>-0.1, 'e'=>50];
        $bounds = [[87, -100], [-0.1, 50]];
        $invalidBounds = ['south'=>-100, 'west'=> 87, 'north'=>-0.1, 'east'=> 50];
        $validBounds = ['south' => 87, 'east'=> 50, 'west' => -100, 'north'=> -0.1];
        $this->assertNull(Utils::setBounds($tooShort));
        $this->assertNull(Utils::setBounds($noKeys));
        $this->assertNull(Utils::setBounds($incorrectKeys));
        $this->assertNull(Utils::setBounds($bounds));
        $this->assertNull(Utils::setBounds($invalidBounds));
        $this->assertEquals(Utils::setBounds($validBounds), $bounds);
    }

    protected function testAddToLat() {
        $this->assertEquals(Utils::addToLat(60, 60), -60);
        $this->assertEquals(Utils::addToLat(80, 10), 90);
        $this->assertEquals(Utils::addToLat(80, 110), 10);
        $this->assertEquals(Utils::addToLat(-80, 20), -60);
        $this->assertEquals(Utils::addToLat(80, -30), 50);
        $this->assertEquals(Utils::addToLat(-170, -20), -10);
    }

    protected function testAddToLong() {
        $this->assertEquals(Utils::addToLong(60, 60), 120);
        $this->assertEquals(Utils::addToLong(110, 100), -150);
        $this->assertEquals(Utils::addToLong(-100, -120), 140);
    }

    /**
     * Ensure the following folder structure is included:
     * - 01.home (default)
     *      - 01.subpage-1 (routable - default)
     *      - subpage-2 (non-routable)
     *          - 01.subpage-2-1
     *          - 02.subpage-2-2
     * - test-folder
     *      - 01.test-subpage-1 (default)
     *      - test-subpage-2
     *          - test-subpage-2-1 (default)
     */
    protected function testGetPageRoute() {
        $topLevelPrefix = Utils::getPageRoute(['home']).'default.md';
        $prefixPrefix = Utils::getPageRoute(['home', '01.subpage-1']).'default.md';
        $regPrefix = Utils::getPageRoute(['test-folder', 'test-subpage-1']).'default.md';
        $regRegReg = Utils::getPageRoute(['test-folder', 'test-subpage-2', 'test-subpage-2-1']).'default.md';
        $prefixRegPrefix = Utils::getPageRoute(['home', 'subpage-2', 'subpage-2-2']).'default.md';
        $includesFileName1 = Utils::getPageRoute(['home', '01.subpage-1', 'default.md']).'default.md';
        $includesFileName2 = Utils::getPageRoute(['home', '01.subpage-1', 'default.md']);
        $incorrectPrefix = Utils::getPageRoute(['home', '02.subpage-1']).'default.md';
        $prefixForReg = Utils::getPageRoute(['02.test-folder', 'test-subpage-1']).'default.md';
        $this->assertTrue(File::instance($topLevelPrefix)->exists());
        $this->assertTrue(File::instance($prefixPrefix)->exists());
        $this->assertTrue(File::instance($regPrefix)->exists());
        $this->assertTrue(File::instance($regRegReg)->exists());
        $this->assertTrue(File::instance($prefixRegPrefix)->exists());
        $this->assertFalse(File::instance($includesFileName1)->exists());
        $this->assertFalse(File::instance($includesFileName2)->exists());
        $this->assertFalse(File::instance($incorrectPrefix)->exists());
        $this->assertFalse(File::instance($prefixForReg)->exists());
    }

    // also serves as setup for update functions
    protected function testBuildNewDataset() {
        $this->lateGeometrySet = Utils::buildNewDataset($this->lateGeometrySet, 'lateGeometry.json');
        $this->featureCounterSet = Utils::buildNewDataset($this->featureCounterSet, 'featureCounter.json');
        $this->nameXSet = Utils::buildNewDataset($this->nameXSet, 'nameX.json');
        $this->xNameNameXSet = Utils::buildNewDataset($this->xNameNameXSet, 'xNameNameX.json');
        $this->xNameNameSet = Utils::buildNewDataset($this->xNameNameSet, 'xNameName.json');
        $this->noNameSet = Utils::buildNewDataset($this->noNameSet, 'noName.json');
        $this->noPropSet = Utils::buildNewDataset($this->noPropSet, 'noProp.json');
        // geometry type not set until third feature - check number of features and geometry type
        $this->assertSize($this->lateGeometrySet['features'], 2);
        $this->assertEquals($this->lateGeometrySet['featureType'], 'LineString');
        // featureCounter already set - check feature ids and featureCounter
        $this->assertEquals($this->featureCounterSet['features'][1]['id'], 'featureCounter_13');
        $this->assertEquals($this->featureCounterSet['featureCounter'], 15);
        // points dataset - check number of features (some invalid), check properties list, check name, check name property
        $this->assertSize($this->pointSet->getFeatures(), 12);
        $this->assertSize($this->pointSet->getProperties(), 6);
        $this->assertEquals($this->pointSet->getNameProperty(), 'name');
        $this->assertEquals($this->pointSet->getFeatures()['points1_2']->getName(), 'Point 2');
        // name prop variations - name prop
        $this->assertEquals($this->nameXSet['nameProperty'], 'nameOfFeature');
        $this->assertEquals($this->xNameNameXSet['nameProperty'], 'featureName');
        $this->assertEquals($this->xNameNameSet['nameProperty'], 'name');
        $this->assertEquals($this->noNameSet['nameProperty'], 'x');
        $this->assertEmpty($this->noPropSet['nameProperty']);
    }

    // matchFeatures - tested within the various testUpdate methods

    protected function testUpdateReplace() {
        [$msg1, $result1] = Utils::testUpdateReplace($this->pointSet, $this->updateArray, ['name', 'name']);
        // test: 6 features total, msg indicates 3 matches, feature 0 has id of point 2 but new coords, feature 1 has id of point 12, feature 3 has fruit of banana, feature 4 has id of point 11
        $this->assertSize($result1['features'], 6);
        $this->assertTrue(str_contains($msg1, '3 matches found'));
        $this->assertEquals($result1['features'][0]['id'], 'points1_2');
        $this->assertEquals($result1['features'][0]['geometry']['coordinates'][0], 5);
        $this->assertEquals($result1['features'][1]['id'], 'points1_12');
        $this->assertEquals($result1['features'][3]['properties']['fruit'], 'banana');
        $this->assertEquals($result1['features'][4]['id'], 'points1_11');
        [$msg2, $result2] = Utils::testUpdateReplace($this->pointSet, $this->updateArray, ['tour_coords']);
        // test: msg indicates 2 matches, feature 0 has id of point 12, feature 1 has id of point 0, feature 4 has id of point 7
        $this->assertTrue(str_contains($msg2, '2 matches found'));
        $this->assertEquals($result2['features'][0]['id'], 'points1_12');
        $this->assertEquals($result2['features'][1]['id'], 'points1_0');
        $this->assertEquals($result2['features'][3]['id'], 'points1_7');
    }

    protected function testUpdateRemove() {
        $result = Utils::testUpdateRemove($this->pointSet, $this->updateArray['features'], ['name', 'name'])[1];
        // 3 matches, 12-3 = 9, points1_7 exists, points1_2 does not
        $this->assertSize($result['features'], 9);
        $features = array_column($result['features'], null, 'id');
        $this->assertNotEmpty($features['points1_7']);
        $this->assertEmpty($features['points1_2']);
    }

    protected function testUpdateStandard() {
        // modify existing - name match - point 5 fruit changed, point 2 coords changed, point 11 fruit unchanged, number of features stayed the same, point 7 exists
        $result = Utils::testUpdateStandard(['modify_existing'=>true], $this->pointSet, $this->updateArray['features'], ['name', 'name'])[1];
        $features = array_column($result['features'], null, 'id');
        $this->assertSize($features, 12);
        $this->assertEquals($features['points1_2']['geometry']['coordinates'][0], 5);
        $this->assertEquals($features['points1_5']['properties']['fruit'], 'banana');
        $this->assertNotEmpty($features['points1_7']);
        $this->assertEquals($features['points1_11']['properties']['fruit'], 'pear');
        // ensure the order has stayed the same
        $this->assertEquals($result['features'][0]['id'], 'points1_0');
        $this->assertEquals($result['features'][2]['id'], 'points1_2');
        // add new - coords match - 4 new features, feature 7 fruit unchanged (pineapple)
        $result = Utils::testUpdateStandard(['add_new'=>true, 'modify_existing'=>false], $this->pointSet, $this->updateArray['features'], ['tour_coords'])[1];
        $features = array_column($result['features'], null, 'id');
        $this->assertSize($features, 16);
        $this->assertEquals($features['points1_7']['properties']['fruit'], 'pineapple');
        // ensure correct order - add new features at the end
        $this->assertEquals($result['features'][0]['id'], 'points1_0');
        $this->assertEquals($result['features'][2]['id'], 'points1_2');
        $this->assertEquals($result['features'][12]['id'], 'points1_12');
        // remove empty - name match - only 3 features left, point 2 fruit and coords unchanged
        $result = Utils::testUpdateStandard(['remove_empty'=>true], $this->pointSet, $this->updateArray['features'], ['name', 'name'])[1];
        $features = array_column($result['features'], null, 'id');
        $this->assertSize($features, 3);
        $this->assertEquals($features['points1_2']['properties']['fruit'], 'kiwi');
        $this->assertEquals($features['points1_2']['geometry']['coordinates'][0], -20);
        // modify existing, overwrite blank - name match - point 11 fruit empty, number of features stayed the same, point 2 fruit unchanged
        $result = Utils::testUpdateStandard(['modify_existing'=>true, 'overwrite_blank'=>true], $this->pointSet, $this->updateArray['features'], ['name', 'name'])[1];
        $features = array_column($result['features'], null, 'id');
        $this->assertSize($features, 12);
        $this->assertEquals($features['points1_2']['properties']['fruit'], 'kiwi');
        $this->assertEmpty($features['points1_11']['properties']['fruit']);
    }

    protected function testHandleDatasetUpdate() {
        // cancel resets all settings
        $update1 = [
            'msg'=>'blah blah blah',
            'status'=>'none',
            'confirm'=>false, 'cancel'=>true,
            'dataset'=>'points1.json',
            'dataset_prop'=>'name',
            'same_prop'=>false, 'file_prop'=>'featureName',
            'type'=>'standard',
            'modify_existing'=>true,
            'file'=>[['name'=>'points1.json']]
        ];
        $result = Utils::handleDatasetUpdate($update1, []);
        $this->assertFalse($result['cancel']);
        $this->assertEmpty($result['dataset']);
        $this->assertEquals($result['dataset_prop'], 'none');
        $this->assertEmpty($result['file_prop']);
        // confirm (when not ready) does nothing and is unset
        $update2 = array_merge($update1, [
            'status'=>'corrections', 
            'confirm'=>true, 'cancel'=>false, 
            'file'=>[[
                'name'=>'points1.json', 
                'type'=>'application/json', 
                'path'=>'user/data/leaflet-tour/datasets/points1.json']]
        ]);
        $result = array_merge($update2, Utils::handleDatasetUpdate($update2, []));
        $this->assertFalse($result['confirm']);
        $this->assertEquals($result['dataset'], 'points1.json');
        $this->assertNotEmpty($result['file_prop']);
        $this->assertEquals($result['status'], 'confirm');
        // issues: no dataset selected, no property selected, invalid property selected, matching property not selected
        $update = array_merge($update2, ['status'=>'none', 'confirm'=>false, 'dataset'=>'']);
        $this->assertEquals(Utils::handleDatasetUpdate($update, [])['status'], 'corrections');
        $update = array_merge($update2, ['dataset_prop'=>'none']);
        $this->assertEquals(Utils::handleDatasetUpdate($update, [])['status'], 'corrections');
        $update = array_merge($update2, ['file_prop'=>'']);
        $this->assertEquals(Utils::handleDatasetUpdate($update, [])['status'], 'corrections');
        $update = array_merge($update2, ['dataset_prop'=>'fu']);
        $this->assertEquals(Utils::handleDatasetUpdate($update, [])['status'], 'corrections');
        // cancel
        Utils::handleDatasetUpdate($update1, []);
    }

    /**
     * Ensure the following:
     * - tour-0 exists
     *      - datasets include points1.json, exclude points2.json, points3.json not show all
     *      - features:
     *          - points3_0 - popup added
     *          - points1_0 - popup removed
     *          - points2_0 - popup added
     */
    protected function testGetAllPopups() {
        $tourRoute = Grav::instance()['locator']->findResource('page://').'/tour-0/tour.md';
        $popups = Utils::getAllPopups($tourRoute);
        // feature with popup - points1_2
        $this->assertNotEmpty($popups['points1_2']);
        // feature with popup added by tour - points3_0
        $this->assertNotEmpty($popups['points3_0']);
        // feature with popup in features list but not actually in tour datasets list - points2_0
        $this->assertEmpty($popups['points2_0']);
        // feature without popup - points1_1
        $this->assertEmpty($popups['points1_1']);
        // feature with popup, but turned off by tour - points1_0
        $this->assertEmpty($popups['points1_0']);
        // feature with popup but not show all or in features list - points3_1
        $this->assertEmpty($popups['points3_1']);
    }

    protected function testArrayFilter() {
        $testArray = [
            'nullItem'=>null,
            'emptyArray'=>[],
            'emptyString'=>'',
            'array0_0'=>['x'=>6, 'y'=>'test'],
            'array0_1'=>[
                'x'=>9,
                'level2Item'=>null,
                'array1_0'=>[
                    'level3Item'=>null,
                    'x'=>7,
                ]
            ],
            'number'=>22,
            'bool'=>false,
            'array0_2'=>['item'=>null],
        ];
        $result = Utils::array_filter($testArray);
        $this->assertSize($result, 5); // nullItem and two empty arrays removed
        $this->assertSize($result['array0_1'], 2);
        $this->assertSize($result['array0_1']['array1_0'], 1);
        $this->assertNull($result['array0_2']);
        $this->assertNull($result['emptyArray']);
        $this->assertEquals($result['emptyString'], '');
        $this->assertFalse($result['bool']);
    }
}
?>