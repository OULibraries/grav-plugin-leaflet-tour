<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use Grav\Common\File\CompiledJsonFile;
use RocketTheme\Toolbox\File\MarkdownFile;

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
        $this->assertTrue(MarkdownFile::instance($topLevelPrefix)->exists());
        $this->assertTrue(MarkdownFile::instance($prefixPrefix)->exists());
        $this->assertTrue(MarkdownFile::instance($regPrefix)->exists());
        $this->assertTrue(MarkdownFile::instance($regRegReg)->exists());
        $this->assertTrue(MarkdownFile::instance($prefixRegPrefix)->exists());
        $this->assertFalse(MarkdownFile::instance($includesFileName1)->exists());
        $this->assertFalse(MarkdownFile::instance($includesFileName2)->exists());
        $this->assertFalse(MarkdownFile::instance($incorrectPrefix)->exists());
        $this->assertFalse(MarkdownFile::instance($prefixForReg)->exists());
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

    protected function testHandleDatasetUpdate() {
        // set up update
        $this->prepareUpdate([]);
        $update = array_merge($this->updateData['settings'], ['status'=>'corrections', 'confirm'=>false, 'dataset_prop'=>'name', 'same_prop'=>false, 'file_prop'=>'featureName']);
        // confirm (when not ready) does nothing and is unset
        $settings = array_merge($update, ['confirm'=>true]);
        $result = array_merge($settings, Utils::handleDatasetUpdate($settings, []));
        $this->assertFalse($result['confirm']);
        $this->assertEquals($result['dataset'], 'points1.json');
        $this->assertNotEmpty($result['file_prop']);
        $this->assertEquals($result['status'], 'confirm');
        // confirm (when things have changed) does nothing and is unset
        $settings = array_merge($update, ['confirm'=>true, 'status'=>'confirm']);
        $result = array_merge($settings, Utils::handleDatasetUpdate($settings, array_merge($settings, ['dataset_prop'=>'tour_coords'])));
        $this->assertFalse($result['confirm']);
        $this->assertEquals($result['status'], 'confirm');
        // the following issues require correction: no dataset selected, no property selected, invalid property selected, matching property not selected
        $settings = array_merge($update, ['dataset'=>'']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'corrections');
        $settings = array_merge($update, ['dataset_prop'=>'none']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'corrections');
        $settings = array_merge($update, ['file_prop'=>'']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'corrections');
        $settings = array_merge($update, ['dataset_prop'=>'fu']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'corrections');
        // standard update requires at least one option selected
        $settings = array_merge($update, ['modify_existing'=>false]); // add and remove options are already not set
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'corrections');
        // the following issues do not require correction when the update is replacement: no property selected, matching property not selected
        $settings = array_merge($update, ['type'=>'replace', 'dataset_prop'=>'none']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'confirm');
        $settings = array_merge($update, ['type'=>'replace', 'file_prop'=>'']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'confirm');
        // cancel resets all settings
        $settings = array_merge($update, ['cancel'=>true]);
        $result = array_merge($settings, Utils::handleDatasetUpdate($settings, []));
        $this->assertFalse($result['cancel']);
        $this->assertEmpty($result['dataset']);
        $this->assertEquals($result['dataset_prop'], 'none');
        $this->assertEmpty($result['file_prop']);
    }
    protected function testUpdateReplace() {
        $updateContent = [
            'type'=>'FeatureCollection',
            'name'=>'Points Replacement Update',
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
        // set up some custom names
        $features = Dataset::getDatasets()['points1.json']->getFeatures();
        $features['points1_0']->update(['custom_name'=>'Point 0 Custom Name']);
        $features['points1_5']->update(['custom_name'=>'Point 5 Custom Name']);
        // set up update (name)
        $this->prepareUpdate($updateContent);
        $update = array_merge($this->updateData['settings'], ['type'=>'replace', 'dataset_prop'=>'name', 'add_new'=>false]);
        // check: msg indicates 3 matches
        $settings = array_merge($update, Utils::handleDatasetUpdate($update, []));
        $this->assertTrue(str_contains($settings['msg'], '3 matches found'));
        $this->assertEquals($settings['status'], 'confirm');
        // change standard update options - shouldn't affect confirmation
        $result = Utils::handleDatasetUpdate(array_merge($settings, ['confirm'=>true, 'modify_existing'=>false, 'remove_empty'=>true]), $settings);
        $this->assertEquals($result['status'], 'none');
        // update: 6 features total, feat0 has id 2 but new coords, feat1 has id 12, feat3 has fruit banana, feat4 has id 11
        $features = Dataset::getDatasets()['points1.json']->getFeatures();
        $this->assertSize($features, 6);
        $this->assertEquals(array_keys($features)[0], 'points1_2');
        $this->assertEquals($features['points1_2']->asJson()['geometry']['coordinates'][0], 5);
        $this->assertEquals(array_keys($features)[1], 'points1_12');
        $this->assertEquals($features['points1_5']->getProperties()['fruit'], 'banana');
        $this->assertEquals(array_keys($features)[4], 'points1_11');
        // check custom names
        $this->assertEquals($features['points1_2']->getName(), 'Point 2');
        $this->assertEquals($features['points1_5']->getName(), 'Point 5 Custom Name');
        // revert changes
        $this->undoUpdate();
        // set up update (coordinates)
        $this->prepareUpdate($updateContent);
        $update = array_merge($update, ['status'=>'confirm', 'dataset_prop'=>'tour_coords', 'confirm'=>true]);
        // check: msg indicates 2 matches
        $this->assertTrue(str_contains(Utils::handleDatasetUpdate($update, [])['msg'], '2 matches found'));
        // update: feat0 has id 12, feat1 has id 0, feat3 has id 7
        Utils::handleDatasetUpdate($update, $update);
        $features = array_keys(Dataset::getDatasets()['points1.json']->getFeatures());
        $this->assertEquals($features[0], 'points1_12');
        $this->assertEquals($features[1], 'points1_0');
        $this->assertEquals($features[3], 'points1_7');
        // revert changes
        $this->undoUpdate();
    }
    protected function testUpdateRemove() {
        // set up update
        $updateContent = ['features'=>[
            ['properties'=>['id'=>2]],
            ['properties'=>['id'=>12]],
            ['properties'=>['id'=>13]],
            ['properties'=>['id'=>5]],
            ['properties'=>['id'=>11]],
            ['properties'=>['id'=>14]],
        ]];
        $this->prepareUpdate($updateContent);
        $update = array_merge($this->updateData['settings'], ['type'=>'remove']);
        // update: 3 features removed, point 7 exists, point 2 does not
        Utils::handleDatasetUpdate($update, []);
        Utils::handleDatasetUpdate($update, $update);
        $features = Dataset::getDatasets()['points1.json']->getFeatures();
        $this->assertSize($features, 9);
        $this->assertNotEmpty($features['points1_7']);
        $this->assertEmpty($features['points1_2']);
        // revert changes
        $this->undoUpdate();
    }
    protected function testUpdateStandard_modify_existing() {
        $this->prepareUpdate($this->updateContent);
        $update = array_merge($this->updateData['settings'], ['overwrite_blank'=>true]);
        Utils::handleDatasetUpdate($update, []);
        Utils::handleDatasetUpdate($update, $update);
        $features = Dataset::getDatasets()['points1.json']->getFeatures();
        // property added to feature (point 1, veggie)
        $this->assertEquals($features['points1_1']->getProperties()['veggie'], 'carrot');
        // coordinates changed (point 9)
        $this->assertEquals($features['points1_9']->asJson()['geometry']['coordinates'][0], 17);
        // coordinates changed despite invalid geometry type (point 11)
        $this->assertEquals($features['points1_11']->asJson()['geometry']['coordinates'][1], -8);
        // invalid coordinates not changed (point 1)
        $this->assertEquals($features['points1_1']->asJson()['geometry']['coordinates'][0], 180);
        // invalid geometry type not changed (point 11)
        $this->assertEquals($features['points1_11']->asJson()['geometry']['type'], 'Point');
        // property overwritten by blank (point 11, fruit)
        $this->assertEmpty($features['points1_11']->getProperties()['fruit']);
        // property not included in property list
        $this->assertEquals($features['points1_9']->getProperties()['name'], 'Point 9');
        // no property list
        $this->assertEquals($features['points1_2']->getProperties()['fruit'], 'kiwi');
        // no features added or removed
        $this->assertSize($features, 12);
        // check existence of feature that would be removed (point 8)
        $this->assertNotEmpty($features['points1_8']);
        $this->undoUpdate();
    }
    protected function testUpdateStandard_add_new() {
        $this->prepareUpdate($this->updateContent);
        $update = array_merge($this->updateData['settings'], ['modify_existing'=>false, 'add_new'=>true]);
        Utils::handleDatasetUpdate($update, []);
        Utils::handleDatasetUpdate($update, $update);
        $features = Dataset::getDatasets()['points1.json']->getFeatures();
        // 3 new features added
        $this->assertSize($features, 15);
        // point 7 fruit unchanged
        $this->assertEquals($features['points1_7']->getProperties()['fruit'], 'pineapple');
        // check new feature added with name property
        $this->assertEquals($features['points1_13']->getName(), 'Point 13');
        // check new feature added without name property
        $this->assertEquals($features['points1_14']->getName(), 'points1_14');
        // check correct order - features added at the end
        $features = array_keys($features);
        $this->assertEquals($features[0], 'points1_0');
        $this->assertEquals($features[2], 'points1_2');
        $this->assertEquals($features[12], 'points1_12');
        $this->undoUpdate();
    }
    protected function testUpdateStandard_remove_empty() {
        $this->prepareUpdate($this->updateContent);
        $update = array_merge($this->updateData['settings'], ['modify_existing'=>false, 'remove_empty'=>true]);
        Utils::handleDatasetUpdate($update, []);
        Utils::handleDatasetUpdate($update, $update);
        $features = Dataset::getDatasets()['points1.json']->getFeatures();
        // 4 features removed
        $this->assertSize($features, 8);
        // point 7 exists with fruit unchanged
        $this->assertEquals($features['points1_7']->getProperties()['fruit'], 'pineapple');
        // point 8 does not exist
        $this->assertEmpty($features['points1_8']);
        // features are in the correct order
        $features = array_keys($features);
        $this->assertEquals($features[0], 'points1_1');
        $this->assertEquals($features[4], 'points1_6');
        $this->assertEquals($features[7], 'points1_11');
        $this->undoUpdate();
    }
    protected function testUpdateStandard_all() {

        // test all (without overwrite blank)
        $this->prepareUpdate($this->updateContent);
        $update = array_merge($this->updateData['settings'], ['add_new'=>true, 'remove_empty'=>true]);
        // save with correct settings, then confirm
        Utils::handleDatasetUpdate($update, []);
        $result = Utils::handleDatasetUpdate($update, $update);
        $result = array_merge($update, $result);
        // options are cleared
        $this->assertEmpty($result['dataset']);
        $this->assertEmpty($result['file']);
        // check features
        $features = Dataset::getDatasets()['points1.json']->getFeatures();
        $this->assertSize($features, 11);
        $this->assertEquals($features['points1_7']->getProperties()['veggie'], 'broccoli'); // updated feature
        $this->assertEquals($features['points1_11']->getProperties()['fruit'], 'pear'); // not overwrite blank

        // check property list update
        $this->assertNotEmpty(Dataset::getDatasets()['points1.json']->getProperties()['veggie']);

        // check yaml to ensure features have been updated
        $features = array_column(MarkdownFile::instance(Dataset::getDatasets()['points1.json']->getDatasetRoute())->header()['features'], null, 'id');
        $this->assertSize($features, 11);
        $this->assertNotEmpty($features['points1_12']); // added feature
        $this->assertEmpty($features['points1_0']); // removed feature
        $this->assertEquals($features['points1_11']['name'], 'Point 11 Name Update'); // changed name
        // correct feature order
        $features = array_keys($features);
        $this->assertEquals($features[0], 'points1_1');
        $this->assertEquals($features[4], 'points1_6');
        $this->assertEquals($features[7], 'points1_11');
        $this->assertEquals($features[9], 'points1_13');

        $this->undoUpdate();
    }

    protected function prepareUpdate(array $updateContent) {
        // set some needed variables
        if (empty($this->updateData)) {
            $fileData = ['path'=>'user/data/leaflet-tour/datasets/update/pointsUpdate.json', 'name'=>'pointsUpdate.json', 'type'=>'application/json'];
            $updateFile = CompiledJsonFile::instance(Grav::instance()['locator']->getBase().'/'.$fileData['path']);
            $jsonFile = CompiledJsonFile::instance(Grav::instance()['locator']->getBase().'/user/data/leaflet-tour/datasets/points1.json');
            $savedJson = CompiledJsonFile::instance(Grav::instance()['locator']->getBase().'/user/data/leaflet-tour/datasets/points1.json')->content();
            $savedDataset = MarkdownFile::instance(Dataset::getDatasets()['points1.json']->getDatasetRoute())->header();
            $settings = ['status'=>'confirm', 'confirm'=>true, 'dataset'=>'points1.json', 'dataset_prop'=>'id', 'same_prop'=>true, 'file'=>[$fileData], 'type'=>'standard', 'modify_existing'=>true];
            $this->updateData = ['updateFile'=>$updateFile, 'jsonFile'=>$jsonFile, 'savedJson'=>$savedJson, 'savedDataset'=>$savedDataset, 'settings'=>$settings];
            $this->updateContent = [
                'type'=>'FeatureCollection',
                'features'=>[ // some features that are the same (2, 3, 5, 6), some features modified (1, 7, 9, 11), some removed (0, 4, 8, 10), some added (12, 13, 14), some invalid (new or modified) (2 new, 7, 9)
                    // these four features should remain the same - all that should be necessary is that the appropriate propertry match - they shouldn't have to be valid features as long as they already exist as valid features
                    ['properties'=>['id'=>2]],
                    ['properties'=>['id'=>5]],
                    ['properties'=>['id'=>6]],
                    ['properties'=>['id'=>3]],
                    // these five features are new - only three should be added, as the other two are invalid
                    [
                        'type'=>'Feature',
                        'properties'=>['type'=>'valid', 'id'=>12, 'name'=>'Point 12'],
                        'geometry'=>['type'=>'Point', 'coordinates'=>[5, 60]],
                    ],[
                        'type'=>'Feature',
                        'properties'=>['type'=>'invalid', 'id'=>0.6, 'name'=>'Invalid point'],
                        'geometry'=>['type'=>'Point', 'coordinates'=>[92, 92]],
                    ],[
                        'type'=>'Feature',
                        'properties'=>['type'=>'valid', 'id'=>13, 'name'=>'Point 13'],
                        'geometry'=>['type'=>'Point', 'coordinates'=>[-5, 10.5]],
                    ],[
                        'properties'=>['type'=>'valid', 'id'=>14],
                        'geometry'=>['type'=>'Point', 'coordinates'=>[7, -14]],
                    ],[
                        'type'=>'Feature',
                        'properties'=>['type'=>'invalid', 'id'=>0.7, 'name'=>'No geometry'],
                    ],
                    // these four features are modified, two have some invalid modifications that should be ignored, but valid modifications that should not be
                    [ // invalid geometry modification
                        'properties'=>['id'=>1, 'fruit'=>'watermelon', 'veggie'=>'carrot'],
                        'geometry'=>['coordinates'=>[7, 92.5]],
                    ],[ // invalid geometry type modification
                        'properties'=>['id'=>11, 'fruit'=>'', 'name'=>'Point 11 Name Update'],
                        'geometry'=>['type'=>'Polygon', 'coordinates'=>[8, -8]],
                    ],[
                        'properties'=>['id'=>9],
                        'geometry'=>['type'=>'Point', 'coordinates'=>[17, 10.7]],
                    ],[
                        'properties'=>['id'=>7, 'veggie'=>'broccoli', 'fruit'=>'orange'],
                    ],
                ],
            ];
        }
        // "upload" the update file
        if (empty($updateContent)) $updateContent = $this->updateData['savedJson'];
        $this->updateData['updateFile']->content($updateContent);
        $this->updateData['updateFile']->save();
    }
    protected function undoUpdate() {
        // reset json and dataset content
        $points = Dataset::getDatasets()['points1.json'];
        $jsonFile = $points->getJsonFile('points1.json');
        $jsonFile->content($this->updateData['savedJson']);
        $jsonFile->save();
        $datasetFile = MarkdownFile::instance($points->getDatasetRoute());
        $datasetFile->header($this->updateData['savedDataset']);
        $datasetFile->save();
        Dataset::resetDatasets();
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

        $this->undoUpdate();
    }
}
?>