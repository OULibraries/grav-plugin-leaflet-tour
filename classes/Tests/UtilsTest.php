<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use Grav\Common\File\CompiledJsonFile;
use RocketTheme\Toolbox\File\MarkdownFile;

class UtilsTest extends Test {

    /**
     * Put together some fake datasets for testing to reduce uploading needs
     * 
     * Requires: points1 dataset
     */
    protected function setup() {
        // dataset that does not have valid geometry until the fourth feature
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
        // dataset where the featureCount has already been set
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
        // dataset with a property called nameOfFeature that is not the first property
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
        // dataset where the featureName property comes before the nameOfFeature property
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
        // dataset where the featureName property comes before the name property
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
        // dataset where there is no property with 'name' in the title
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
        // dataset where no features have any properties
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
    
    /**
     * Test the isValidPoint dataset
     */
    protected function testIsValidPoint() {
        // general invalid input
        // cannot set point with string provided for coordinates
        $this->assertNull(Utils::setValidCoordinates('string', 'point'));
        // cannot set point with only one number in coordinates array
        $this->assertNull(Utils::setValidCoordinates([1], 'point'));
        // cannot set point with more than two numbers in coordinates array
        $this->assertNull(Utils::setValidCoordinates([5, 10, 15], 'point'));
        // cannot set point with non-numbers in coordinates array
        $this->assertNull(Utils::setValidCoordinates(['string1', 'string2'], 'point'));

        // cannot set point when latitude or longitude is invalid
        $this->assertNull(Utils::setValidCoordinates([180.01, 64], 'point'));
        $this->assertNull(Utils::setValidCoordinates([-200, 64], 'point'));
        $this->assertNull(Utils::setValidCoordinates([64, 120], 'point'));
        $this->assertNull(Utils::setValidCoordinates([64, -90.05], 'point'));

        // general case - set two valid points
        $this->assertNotEmpty(Utils::setValidCoordinates([120, 64], 'point'));
        $this->assertNotEmpty(Utils::setValidCoordinates([-120, -64], 'point'));

        // edge cases - set points where lat/long are highest/lowest possible values, are 0, or are really long decimals
        $this->assertEquals(Utils::setValidCoordinates([180, 64], 'point'), [180, 64]);
        $this->assertNotEmpty(Utils::setValidCoordinates([64, -90], 'point'));
        $this->assertNotEmpty(Utils::setValidCoordinates([-180, 90], 'x'));
        $this->assertNotEmpty(Utils::setValidCoordinates([0, 0], 'point'));
        $this->assertNotEmpty(Utils::setValidCoordinates([95.32952385683943, 10.328943899523], 'point'));
        // reverse
        // $this->assertNotEmpty(Utils::setValidCoordinates([64, 120], 'point'));
        // $this->assertNull(Utils::setValidCoordinates([120, 64], 'point'));
    }

    /**
     * Test the isValidLineString method
     */
    protected function testIsValidLineString() {
        $point = [120, 64]; // longitude = 120, valid
        $invalidPoint = [64, 120]; // latitude = 120, invalid
        // cannot set lineString with non-points in coordinates array
        $this->assertNull(Utils::setValidCoordinates([$point, $point, 'string'], 'linestring'));
        // cannot set linestring with only one point in coordinates array
        $this->assertNull(Utils::setValidCoordinates([$point], 'linestring'));
        // cannot set linestring with an invalid point in coordinates array
        $this->assertNull(Utils::setValidCoordinates([$point, $invalidPoint], 'linestring'));
        // general case - set valid linestrings, ensure length equals number of points provided
        $this->assertNotEmpty(Utils::setValidCoordinates([$point, $point], 'linestring'));
        $this->assertSize(Utils::setValidCoordinates([$point, $point, $point, $point, $point], 'linestring'), 5);
    }

    /**
     * Test the isValidMultiLineString method
     */
    protected function testIsValidMultiLineString() {
        $line = [[120, 64], [64, 64], [20, -30]]; // valid line
        $point = [120, 64]; // valid point
        // cannot set multiLineString where one of the points in one of the lines is invalid
        $this->assertNull(Utils::setValidCoordinates([[$point, $point, [64, 120]], $line, $line], 'multilinestring'));
        // cannot set multiLineString with a line (must be array of lines)
        $this->assertNull(Utils::setValidCoordinates($line, 'multilinestring'));
        // one line in line array is enough
        $this->assertNotEmpty(Utils::setValidCoordinates([$line], 'multilinestring'));
    }

    /**
     * Test the setValidLinearRing method
     */
    protected function testSetValidLinearRing() {
        // cannot set linear ring with only two points
        $this->assertNull(Utils::setValidCoordinates([[[112, 60], [64, 64]]], 'polygon'));
        // cannot set linear ring with only two unique points
        $this->assertNull(Utils::setValidCoordinates([[[112, 60], [64, 64], [112, 60]]], 'polygon'));
        $line1 = [[112, 60], [64, 64], [20, -30]]; // valid unclosed line
        // cannot turn array of lines into a linear ring
        $this->assertNull(Utils::setValidCoordinates([[$line1, $line1, $line1, $line1]], 'polygon'));
        // cannot create linear ring with any invalid points
        $this->assertNull(Utils::setValidCoordinates([[[112, 60], [64, 64], [64, 120], [112, 60]]], 'polygon'));
        $line2 = [[112, 60], [64, 64], [20, -30], [112, 60]]; // valid, closed line (linear ring)
        // unclosed line is turned into a linear ring
        $this->assertEquals(Utils::setValidCoordinates([$line1], 'polygon'), [$line2]);
        // linear ring does not need to be adjusted
        $this->assertEquals(Utils::setValidCoordinates([$line2], 'polygon'), [$line2]);
    }

    /**
     * Test the setValidPolygon method
     */
    protected function testSetValidPolygon() {
        $ring = [[180, 80], [120, -80], [-89, 0], [0, 22.3453], [180, 80]]; // valid linear ring
        $line = [[112, 60], [64, 64], [20, -30]]; // valid unclosed line
        // cannot set polygon with empty array
        $this->assertNull(Utils::setValidCoordinates([], 'polygon'));
        // cannot set polygon with a point in the array of lines/lineaer rings
        $this->assertNull(Utils::setValidCoordinates([$line, $ring, [50, 20]], 'polygon'));
        // set polygon with one linear ring
        $this->assertNotEmpty(Utils::setValidCoordinates([$ring], 'polygon'));
        // set polygon with multiple linear rings, some of which start as unclosed lines
        $this->assertNotEmpty(Utils::setValidCoordinates([$ring, $line, $ring, $line], 'polygon'));
    }

    /**
     * Test the setValidMultiPolygon method
     */
    protected function testSetValidMultiPolygon() {
        $ring = [[180, 80], [120, -80], [-89, 0], [0, 22.3453], [180, 80]]; // valid linear ring
        $line = [[112, 60], [64, 64], [20, -30]]; // valid unclosed line
        $polygon1 = [$ring]; // valid polygon of one ring
        $polygon2 = [$ring, $line, $line]; // valid polygon of three rings
        // cannot set multiPolygon with a number for coordinates array
        $this->assertNull(Utils::setValidCoordinates(54, 'multipolygon'));
        // cannot sete multiPolygon if any of the points are invalid
        $this->assertNull(Utils::setValidCoordinates([[$line], [[[112, 60], [64, 64], [20, -30], [64, 120]]]], 'multipolygon'));
        // set multiPolygon with only one polygon
        $this->assertNotEmpty(Utils::setValidCoordinates([$polygon1], 'multipolygon'));
        // set multiPolygon with multiple polygons
        $this->assertNotEmpty(Utils::setValidCoordinates([$polygon1, $polygon2, $polygon2, $polygon1], 'multipolygon'));
    }

    /**
     * Test the setValidCoordinates method
     */
    protected function testSetValidCoordinates() {
        $line = [[112, 60], [64, 64], [20, -30]]; // valid unclosed line
        // cannot set point with line coordinates
        $this->assertNull(Utils::setValidCoordinates($line, 'point'));
        // cannot set line with array of lines
        $this->assertNull(Utils::setValidCoordinates([$line, $line], 'linestring'));
        // cannot set multiPolygon with point
        $this->assertNull(Utils::setValidCoordinates([112, 60], 'multipolygon'));
    }

    /**
     * Test the setValidType method
     */
    protected function testSetValidType() {
        // any value that doesn't match will default to Point
        $this->assertEquals(Utils::setValidType('random string'), 'Point');
        // capitalization is adjusted appropriately
        $this->assertEquals(Utils::setValidType('multilinestring'), 'MultiLineString');
        $this->assertEquals(Utils::setValidType('multiLINEstring'), 'MultiLineString');
    }

    /**
     * Test the setBounds method
     */
    protected function testSetBounds() {
        // invalid bounds: too short (missing north)
        $this->assertNull(Utils::setBounds(['south'=>87, 'west'=>-100, 'east'=>50]));
        // invalid bounds: numbers mights be fine, but no keys are provided
        $this->assertNull(Utils::setBounds([87, -100, -0.1, 50]));
        // invalid bounds: keys must be south, north, west, and east
        $this->assertNull(Utils::setBounds(['s'=>87, 'w'=>-100, 'n'=>-0.1, 'e'=>50]));
        // invalid bounds: this could be a valid result from the function, but function requires an array with four keys
        $this->assertNull(Utils::setBounds([[87, -100], [-0.1, 50]]));
        // invalid bounds: south (latitude) cannot go below -90
        $this->assertNull(Utils::setBounds(['south'=>-100, 'west'=> 87, 'north'=>-0.1, 'east'=> 50]));
        // valid bounds array is returns as [[south, west], [north, east]]
        $this->assertEquals(Utils::setBounds(
            ['south' => 87, 'east'=> 50, 'west' => -100, 'north'=> -0.1]),
            [[87, -100], [-0.1, 50]]);
    }

    /**
     * Test the addToLat method
     */
    protected function testAddToLat() {
        // positive wraparound: 120 becomes -90 + 30 = -60
        $this->assertEquals(Utils::addToLat(60, 60), -60);
        // max value: 90
        $this->assertEquals(Utils::addToLat(80, 10), 90);
        // positive wraparound, twice: 190 becomes -90 + 100 = 10
        $this->assertEquals(Utils::addToLat(80, 110), 10);
        // normal: -80 + 20 = -60
        $this->assertEquals(Utils::addToLat(-80, 20), -60);
        // normal: 80 - 30 = 50
        $this->assertEquals(Utils::addToLat(80, -30), 50);
        // negative wraparound, twice: -190 becomes 90 - 100 = -10
        $this->assertEquals(Utils::addToLat(-170, -20), -10);
    }

    /**
     * Test the addToLong method
     */
    protected function testAddToLong() {
        // normal: 60 + 60 = 120
        $this->assertEquals(Utils::addToLong(60, 60), 120);
        // positive wraparound: 210 becomes -180 + 30 = -150
        $this->assertEquals(Utils::addToLong(110, 100), -150);
        // negative wraparound: -220 becomes 180 - 40 = 140
        $this->assertEquals(Utils::addToLong(-100, -120), 140);
    }

    /**
     * Test the getPageRoute method
     * 
     * Requires the following folder structure (all default pages)
     * - 01.home
     *      - 01.subpage-1
     *      - subpage-2 (non-routable)
     *          - 01.subpage-2-1
     *          - 02.subpage-2-2
     * - test-folder
     *      - 01.test-subpage-1
     *      - test-subpage-2
     *          - test-subpage-2-1
     */
    protected function testGetPageRoute() {
        // route exists for a top level page where page has prefix but route array does not
        $this->assertTrue(MarkdownFile::instance(Utils::getPageRoute(['home']).'default.md')->exists());
        // route exists for a subpage where both subpage and top level page have prefixes, but only one has the prefix provided in the route array
        $this->assertTrue(MarkdownFile::instance(Utils::getPageRoute(['home', '01.subpage-1']).'default.md')->exists());
        // route exists for a subpage where only the subpage has a prefix (top level page does not), and no prefixes are provided in the route array
        $this->assertTrue(MarkdownFile::instance(Utils::getPageRoute(['test-folder', 'test-subpage-1']).'default.md')->exists());
        // route exists for a sub-subpage where no pages have prefixes
        $this->assertTrue(MarkdownFile::instance(Utils::getPageRoute(['test-folder', 'test-subpage-2', 'test-subpage-2-1']).'default.md')->exists());
        // route exists for a sub-subpage with mix of prefixes and no prefixes, but no prefixes are provided in the route array
        $this->assertTrue(MarkdownFile::instance(Utils::getPageRoute(['home', 'subpage-2', 'subpage-2-2']).'default.md')->exists());
        // route does not exist when file name is provided in route array, whether repeated afterwards or not
        $this->assertFalse(MarkdownFile::instance(Utils::getPageRoute(['home', '01.subpage-1', 'default.md']).'default.md')->exists());
        $this->assertFalse(MarkdownFile::instance(Utils::getPageRoute(['home', '01.subpage-1', 'default.md']))->exists());
        // route does not exist for page with prefix when the incorrect prefix is provided in the route array
        $this->assertFalse(MarkdownFile::instance(Utils::getPageRoute(['home', '02.subpage-1']).'default.md')->exists());
        // route does not exist for page without prefix when a prefix is provided in the route array
        $this->assertFalse(MarkdownFile::instance(Utils::getPageRoute(['02.test-folder', 'test-subpage-1']).'default.md')->exists());
    }

    /**
     * Test the buildNewDataset method
     * 
     * Requires points1 dataset:
     *  - 12 valid features
     *  - 6 total property types
     *  - property exists called 'name'
     *  - points1_2 name: Point 2
     */
    protected function testBuildNewDataset() {
        // build datasets to test (here and in the update functions)
        $this->lateGeometrySet = Utils::buildNewDataset($this->lateGeometrySet, 'lateGeometry.json');
        $this->featureCounterSet = Utils::buildNewDataset($this->featureCounterSet, 'featureCounter.json');
        $this->nameXSet = Utils::buildNewDataset($this->nameXSet, 'nameX.json');
        $this->xNameNameXSet = Utils::buildNewDataset($this->xNameNameXSet, 'xNameNameX.json');
        $this->xNameNameSet = Utils::buildNewDataset($this->xNameNameSet, 'xNameName.json');
        $this->noNameSet = Utils::buildNewDataset($this->noNameSet, 'noName.json');
        $this->noPropSet = Utils::buildNewDataset($this->noPropSet, 'noProp.json');
        // geometry type not set until third or fourth feature should still result in valid dataset - check number of features and geometry type
        $this->assertSize($this->lateGeometrySet['features'], 2);
        $this->assertEquals($this->lateGeometrySet['featureType'], 'LineString');
        // featureCounter already set
        // feature ids respect existing counter (start at 12 instead of 0)
        $this->assertEquals($this->featureCounterSet['features'][1]['id'], 'featureCounter_13');
        // featureCounter is incremented: 12 + 3 new features
        $this->assertEquals($this->featureCounterSet['featureCounter'], 15);
        // should be 12 valid features in points dataset - no invalid features included
        $this->assertSize($this->pointSet->getFeatures(), 12);
        // should be 6 total property types (scattered throughout) - all should be reocgnized
        $this->assertSize($this->pointSet->getProperties(), 6);
        // check name property and feature name
        $this->assertEquals($this->pointSet->getNameProperty(), 'name');
        $this->assertEquals($this->pointSet->getFeatures()['points1_2']->getName(), 'Point 2');
        // nameOfFeature is chosen as default name prop
        $this->assertEquals($this->nameXSet['nameProperty'], 'nameOfFeature');
        // first feature with name is chosen as default name prop: featureName, not nameOfFeature
        $this->assertEquals($this->xNameNameXSet['nameProperty'], 'featureName');
        // name is chosen as default name prop, even when not the first property with name in it
        $this->assertEquals($this->xNameNameSet['nameProperty'], 'name');
        // if no properties have name, the first property is chosen as default name prop
        $this->assertEquals($this->noNameSet['nameProperty'], 'x');
        // if no properties, then there is no name prop, but no errors occur
        $this->assertEmpty($this->noPropSet['nameProperty']);
    }

    /**
     * Update utility function that can be called before each update
     * 
     * @param array $updateContent - a dataset that will be included as the contents of the update file. If empty array, the original contents of points1.json will be used
     */
    protected function prepareUpdate(array $updateContent) {
        // set some needed variables
        // Theoretically, this part could be done in the first method that calls prepareUpdate, since $this->updateData and $this->updateContent can be accessed from then on by any functions. Doing it this way, however, means that there is no reliance of function order.
        if (empty($this->updateData)) {
            // set defaults to use for update data
            $fileData = ['path'=>'user/data/leaflet-tour/datasets/update/pointsUpdate.json', 'name'=>'pointsUpdate.json', 'type'=>'application/json'];
            $settings = ['status'=>'confirm', 'confirm'=>true, 'dataset'=>'points1.json', 'dataset_prop'=>'id', 'same_prop'=>true, 'file'=>[$fileData], 'type'=>'standard', 'modify_existing'=>true];
            // set some files for ease of access
            $updateFile = CompiledJsonFile::instance(Grav::instance()['locator']->getBase().'/'.$fileData['path']);
            $jsonFile = CompiledJsonFile::instance(Grav::instance()['locator']->getBase().'/user/data/leaflet-tour/datasets/points1.json');
            // save the points1 json data and the points1 dataset configuration so the update can be reverted, afterwards
            $savedJson = CompiledJsonFile::instance(Grav::instance()['locator']->getBase().'/user/data/leaflet-tour/datasets/points1.json')->content();
            $savedDataset = MarkdownFile::instance(Dataset::getDatasets()['points1.json']->getDatasetRoute())->header();
            // provide a variable that can be used to access all of these settings
            $this->updateData = ['updateFile'=>$updateFile, 'jsonFile'=>$jsonFile, 'savedJson'=>$savedJson, 'savedDataset'=>$savedDataset, 'settings'=>$settings];
            // provide a set of default update content (will serve for updating the points1 dataset)
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
        // "upload" the update file (create or save over the file that would normally be uploaded using the admin panel)
        if (empty($updateContent)) $updateContent = $this->updateData['savedJson'];
        $this->updateData['updateFile']->content($updateContent);
        $this->updateData['updateFile']->save();
    }

    /**
     * Update utility function that can be called after each update. It reverts the points1 json file and dataset page to their previous values. It does not clear out update files - that will be accomplished when an update is successfully completed or cancelled.
     */
    protected function undoUpdate() {
        // reset json and dataset content
        $points = Dataset::getDatasets()['points1.json'];
        // save over the json file with the initial points1 json content
        $jsonFile = $points->getJsonFile('points1.json');
        $jsonFile->content($this->updateData['savedJson']);
        $jsonFile->save();
        // save over the dataset file (header/config) with the initial points1 datset page header/config
        $datasetFile = MarkdownFile::instance($points->getDatasetRoute());
        $datasetFile->header($this->updateData['savedDataset']);
        $datasetFile->save();
        // make sure changes are reflected in the actual set of datasets
        Dataset::resetDatasets();
    }

    // matchFeatures - tested within the various testUpdate methods

    /**
     * Test the handleDatasetUpdate method
     * 
     * Requires (points1.json):
     *  - no property called "fu"
     */
    protected function testHandleDatasetUpdate() {
        $this->prepareUpdate([]);
        // default update settings to use
        $update = array_merge($this->updateData['settings'], ['status'=>'corrections', 'confirm'=>false, 'dataset_prop'=>'name', 'same_prop'=>false, 'file_prop'=>'featureName']);
        // confirm='true' (when not ready) does nothing and is unset
        $settings = array_merge($update, ['confirm'=>true]);
        $result = array_merge($settings, Utils::handleDatasetUpdate($settings, []));
        $this->assertFalse($result['confirm']); // confirm has been unset
        $this->assertEquals($result['dataset'], 'points1.json'); // dataset is not unset
        $this->assertNotEmpty($result['file_prop']); // file_prop is not unset
        $this->assertEquals($result['status'], 'confirm'); // update is valid, so now is ready for confirmation
        // confirm (when things have changed) does nothing and is unset
        $settings = array_merge($update, ['confirm'=>true, 'status'=>'confirm']);
        $result = array_merge($settings, Utils::handleDatasetUpdate($settings, array_merge($settings, ['dataset_prop'=>'tour_coords'])));
        $this->assertFalse($result['confirm']); // confirm has been unset
        $this->assertEquals($result['status'], 'confirm'); // still a valid update, still waiting for confirmation
        // empty dataset: update requires correction
        $settings = array_merge($update, ['dataset'=>'']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'corrections');
        // no dataset property: update requires correction
        $settings = array_merge($update, ['dataset_prop'=>'none']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'corrections');
        // no file property (and same_prop=false)e: update requires correction
        $settings = array_merge($update, ['file_prop'=>'']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'corrections');
        // invalid dataset property: update requires correction
        $settings = array_merge($update, ['dataset_prop'=>'fu']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'corrections');
        // standard update requires at least one option selected (add, remove, modify)
        $settings = array_merge($update, ['modify_existing'=>false]); // add and remove options are already not set
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'corrections');
        // replacement update does not require dataset property to be set
        $settings = array_merge($update, ['type'=>'replace', 'dataset_prop'=>'none']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'confirm');
        // replace update does not require matching file property to be set
        $settings = array_merge($update, ['type'=>'replace', 'file_prop'=>'']);
        $this->assertEquals(Utils::handleDatasetUpdate($settings, [])['status'], 'confirm');
        // cancel resets all settings
        $settings = array_merge($update, ['cancel'=>true]);
        $result = array_merge($settings, Utils::handleDatasetUpdate($settings, []));
        // cancel, dataset, dataset_prop, file_prop, etc. have been unset
        $this->assertFalse($result['cancel']);
        $this->assertEmpty($result['dataset']);
        $this->assertEquals($result['dataset_prop'], 'none');
        $this->assertEmpty($result['file_prop']);
    }

    /**
     * Test handleDatasetUpdate with replacement update
     * 
     * Requires (points1.json):
     *  - 12 valid features
     *  - name properties in form "Point <id>" (e.g. Point 0, Point 1, etc.)
     *  - points1_0 coords: [-8.009, 10]
     *  - points1_2 coords: x != 5
     *  - points 1_7 coords: [14.015, -16]
     *  - no points with coordinates: [5, 6.007], [-11, -12.013], [17, -18.019], [-20, 21.022]
     */
    protected function testUpdateReplace() {
        // create content to replace points1.json
        $updateContent = [
            'type'=>'FeatureCollection',
            'name'=>'Points Replacement Update',
            'features'=>[
                [
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 2'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[5, 6.007]],
                ],[ // matches points1_0 coordinates
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 12'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-8.009, 10]],
                ],[
                    'type'=>'Feature',
                    'properties'=>['name'=>'Point 13', 'fruit'=>'orange'],
                    'geometry'=>['type'=>'Point', 'coordinates'=>[-11, -12.013]],
                ],[ // matches points1_7 coordinates
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
        // set up some custom names (to test that they are preserved when requested)
        $features = Dataset::getDatasets()['points1.json']->getFeatures();
        $features['points1_0']->update(['custom_name'=>'Point 0 Custom Name']);
        $features['points1_5']->update(['custom_name'=>'Point 5 Custom Name']);
        // set up update (name)
        $this->prepareUpdate($updateContent);
        $update = array_merge($this->updateData['settings'], ['type'=>'replace', 'dataset_prop'=>'name', 'add_new'=>false]);
        // first go-through (move from empty to awaiting confirmation)
        $settings = array_merge($update, Utils::handleDatasetUpdate($update, []));
        // message should confirm 3 matches (Points 2, 5, and 11)
        $this->assertTrue(str_contains($settings['msg'], '3 matches found'));
        // awaiting confirmation
        $this->assertEquals($settings['status'], 'confirm');
        // change standard update options - shouldn't affect confirmation, because these are ignored for replacement updates
        $result = Utils::handleDatasetUpdate(array_merge($settings, ['confirm'=>true, 'modify_existing'=>false, 'remove_empty'=>true]), $settings);
        $this->assertEquals($result['status'], 'none'); // update has completed successfully and been cleared
        // update: 6 features total, feat0 has id 2 but new coords, feat1 has id 12, feat3 has fruit banana, feat4 has id 11
        $features = Dataset::getDatasets()['points1.json']->getFeatures();
        // 6 features provided in replacement dataset
        $this->assertSize($features, 6);
        // first feature has name Point 2 and therefore id points1_2
        $this->assertEquals(array_keys($features)[0], 'points1_2');
        // points1_2 geometry has changed
        $this->assertEquals($features['points1_2']->asJson()['geometry']['coordinates'][0], 5);
        // second feature is totally new point - id incremented, so points1_12
        $this->assertEquals(array_keys($features)[1], 'points1_12');
        // points1_5 still exists, now has property.fruit = banana
        $this->assertEquals($features['points1_5']->getProperties()['fruit'], 'banana');
        // fifth feature is points1_11
        $this->assertEquals(array_keys($features)[4], 'points1_11');
        // points1_2 did not have custom name, name is the same as before
        $this->assertEquals($features['points1_2']->getName(), 'Point 2');
        // points1_5 custom name has been preserved
        $this->assertEquals($features['points1_5']->getName(), 'Point 5 Custom Name');
        // revert changes
        $this->undoUpdate();

        // set up update (coordinates)
        $this->prepareUpdate($updateContent);
        $update = array_merge($update, ['status'=>'confirm', 'dataset_prop'=>'tour_coords', 'confirm'=>true]);
        // check: msg indicates 2 matches (points 0 and 7)
        $this->assertTrue(str_contains(Utils::handleDatasetUpdate($update, [])['msg'], '2 matches found'));
        Utils::handleDatasetUpdate($update, $update);
        $features = array_keys(Dataset::getDatasets()['points1.json']->getFeatures());
        $this->assertEquals($features[0], 'points1_12'); // first feature is a new feature, not points1_2
        $this->assertEquals($features[1], 'points1_0'); // second feature matches points1_0 coords
        $this->assertEquals($features[3], 'points1_7'); // fourth feature matches points1_7 coords
        // revert changes
        $this->undoUpdate();
    }

    /**
     * Test handleDatasetUpdate with removal update
     * 
     * Requires: points1.json with 12 valid features that have id properties
     */
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
        Utils::handleDatasetUpdate($update, []); // need to do this first in order to create the temporary json file
        Utils::handleDatasetUpdate($update, $update);
        $features = Dataset::getDatasets()['points1.json']->getFeatures();
        // 9 features: were 12, 3 removed
        $this->assertSize($features, 9);
        // point 7 still exists
        $this->assertNotEmpty($features['points1_7']);
        // point 2 has been removed
        $this->assertEmpty($features['points1_2']);
        // revert changes
        $this->undoUpdate();
    }

    /**
     * Test handleDatasetUpdate with standard (modify existing) update
     * 
     * Requires (points1.json):
     *  - 12 features
     *  - name property in form Point 0, Point 1, etc.
     *  - point 1
     *      - does not have veggie property
     *      - coords: x = 180
     *  - point 2
     *      - has properties.fruit = kiwi
     *  - point 9 coords: x != 17
     *  - point 11
     *      - has fruit property
     *      - coords: y != -8
     */
    protected function testUpdateStandard_modify_existing() {
        // set up and perform the update
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

    /**
     * Test handleDatasetUpdate with standard (add new) update
     * 
     * Requires (points1.json):
     *  - 12 features
     *  - point 7 - properties.fruit = pineapple
     */
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

    /**
     * Test handleDatasetUpdate with standard (remove empty) update
     * 
     * Requires (points1.json):
     *  - 12 features
     *  - point 7 has properties.fruit = pineapple
     */
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

    /**
     * Test handleDatasetUpdate with standard (all options) update
     * 
     * Requires (points1.json):
     *  - no veggie property
     *  - point 11 has properties.fruit = pear
     */
    protected function testUpdateStandard_all() {

        // test all (without overwrite blank)
        $this->prepareUpdate($this->updateContent);
        $update = array_merge($this->updateData['settings'], ['add_new'=>true, 'remove_empty'=>true]);
        // save with correct settings, then confirm
        Utils::handleDatasetUpdate($update, []);
        $result = Utils::handleDatasetUpdate($update, $update);
        $result = array_merge($update, $result);
        // options are cleared (successful update)
        $this->assertEmpty($result['dataset']);
        $this->assertEmpty($result['file']);
        // check features
        $features = Dataset::getDatasets()['points1.json']->getFeatures();
        // 11 features: 12 + 3 - 4
        $this->assertSize($features, 11);
        // point 7 property added
        $this->assertEquals($features['points1_7']->getProperties()['veggie'], 'broccoli');
        // point 11 property  not overwritten by blank
        $this->assertEquals($features['points1_11']->getProperties()['fruit'], 'pear');

        // property list has updated - new property 'veggie' added
        $this->assertNotEmpty(Dataset::getDatasets()['points1.json']->getProperties()['veggie']);

        // check yaml to ensure features have been updated
        $features = array_column(MarkdownFile::instance(Dataset::getDatasets()['points1.json']->getDatasetRoute())->header()['features'], null, 'id');
        // correct number of features
        $this->assertSize($features, 11);
        // new feature successfully added
        $this->assertNotEmpty($features['points1_12']);
        // feature successfully removed
        $this->assertEmpty($features['points1_0']);
        // feature updated (changed name)
        $this->assertEquals($features['points1_11']['name'], 'Point 11 Name Update');
        // correct feature order
        $features = array_keys($features);
        $this->assertEquals($features[0], 'points1_1');
        $this->assertEquals($features[4], 'points1_6');
        $this->assertEquals($features[7], 'points1_11');
        $this->assertEquals($features[9], 'points1_13');

        $this->undoUpdate();
    }

    /**
     * Test the getAllPopups method
     * 
     * Requires:
     *  - tour 0:
     *      - datasets include points1 and points3
     *      - datasets excludes points2
     *      - points3_0 and points2_0: popup added
     *      - point1_0: popup removed
     *  - points1:
     *      - point 1 does not have popup
     *      - point 0 has popup
     *  - points3:
     *      - point 1 has popup
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

    /**
     * Test the arrayFilter method
     */
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
        // initial size is 8, null item and two empty arrays (emptyArray and array0_2) removed
        $this->assertSize($result, 5);
        // initial size 3, null item removed
        $this->assertSize($result['array0_1'], 2);
        // initial size 2, null item removed
        $this->assertSize($result['array0_1']['array1_0'], 1);
        // removed (empty, null item removed)
        $this->assertNull($result['array0_2']);
        // removed (empty)
        $this->assertNull($result['emptyArray']);
        // not removed - only nulls and empty arrays are removed
        $this->assertEquals($result['emptyString'], '');
        // not removed - only nulls and empty arrays are removed
        $this->assertFalse($result['bool']);

        $this->undoUpdate();
    }
}
?>