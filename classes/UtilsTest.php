<?php
namespace Grav\Plugin\LeafletTour;

// use Grav\Common\Grav;
use RocketTheme\Toolbox\File\File;

class UtilsTest extends Test {

    protected $simplePoly;
    
    protected function setup() {
        parent::setup();
        $this->simplePoly = [[180, 80], [120, -80], [-89, 0], [0, 22.3453]];
        $this->testHeader = 'Results for Utils Test';
    }

    public static function getResults(bool $showSuccess=false, $showPrint = true, $test=null): string {
        return self::getTestResults(new UtilsTest(), $showSuccess, $showPrint);
    }

    function testIsValidPoint() {
        // 1 correct long and lat
        $this->isTrue(Utils::isValidPoint([-96.2, 45.3]));
        // 2 lat and long switched
        $this->isFalse(Utils::isValidPoint([45.3, -96.2]));
        // 3 switched but with reverse flag
        $this->isTrue(Utils::isValidPoint([45.3, -96.2], true));
        // 4 edge cases -90/90, -180/180
        $this->isTrue(Utils::isValidPoint([-180, 90]));
        // 5 incorrect edge cases -90.000001
        $this->isFalse(Utils::isValidPoint([76, 90.00001]));
        // 6 too few
        $this->isFalse(Utils::isValidPoint([87]));
        // 7 too many
        $this->isFalse(Utils::isValidPoint([87, 64, 12]));
        // 8 non-numeric
        $this->isFalse(Utils::isValidPoint([87, 'not a number']));
        // 9 not an array
        $this->isFalse(Utils::isValidPoint(87));
        // 10 really long decimals
        $this->isTrue(Utils::isValidPoint([95.32952385683943, 10.328943899523]));
    }

    function testIsValidMultiPoint() {
        // 1 two points
        $this->isTrue(Utils::isValidMultiPoint([[-96.2, 45.3],[-180, 90]]));
        // 2 empty
        $this->isFalse(Utils::isValidMultiPoint([]));
        // 3 array of arrays
        $this->isFalse(Utils::isValidMultiPoint([[[-96.2, 45.3],[-180, 90]],[[-96.2, 45.3],[-180, 90]]]));
        // 4 point
        $this->isFalse(Utils::isValidMultiPoint([-96.2, 45.3]));
    }

    function testIsValidSimplePolygon() {
        // 1 simple polygon
        $this->isTrue(Utils::isValidSimplePolygon($this->simplePoly));
        // 2 less than three points
        $this->isFalse(Utils::isValidSimplePolygon([[180, 80], [120, -80]]));
        // 3 invalid points
        $this->isFalse(Utils::isValidSimplePolygon([[180, 80], [120, -80], [-89, 91], [0, 22.3453]]));
    }

    function testIsValidPolygon() {
        $s = $this->simplePoly;
        // 1 simply polygon
        $this->isTrue(Utils::isValidPolygon($s));
        // 2 array of simple polygons
        $this->isTrue(Utils::isValidPolygon([$s, $s, $s, $s]));
        // 3 array of arrays of simple polygons
        $this->isFalse(Utils::isValidPolygon([[$s, $s, $s, $s]]));
        // 4 point
        $this->isFalse(Utils::isValidPolygon([67, 80]));
        // 5 invalid simple polygon
        $this->isFalse(Utils::isValidPolygon([[180, 80], [120, -80]]));
    }

    function testIsValidMultiPolygon() {
        $s = $this->simplePoly;
        // 1 array with one simple polygon
        $this->isTrue(Utils::isValidMultiPolygon([$s]));
        // 2 array of simple polygons
        $this->isTrue(Utils::isValidMultiPolygon([$s, $s, $s, $s]));
        // 3 array of array of simple polygons
        $this->isTrue(Utils::isValidMultiPolygon([[$s, $s, $s, $s], [$s]]));
        // 4 array of array of array of simple polygons
        $this->isFalse(Utils::isValidMultiPolygon([[[$s, $s, $s, $s], [$s]]]));
        // 5 simple polygon
        $this->isFalse(Utils::isValidMultiPolygon($s));
        // 6 array of polygons with one invalid polygon
        $p = [$s, $s, $s, $s];
        $this->isFalse(Utils::isValidMultiPolygon([$s, $s, $s, $s, [[180, 80], [120, -80]]]));
    }

    // isValidLineString - covered by isValidMultiPoint

    // isValidMultiLineString - unnecessary, should be covered by the other tests

    function testAreValidCoordinates() {
        $s = $this->simplePoly;
        // valid point
        $this->isTrue(Utils::areValidCoordinates([-96.2, 45.3], 'Point'));
        // valid point with bad type
        $this->isTrue(Utils::areValidCoordinates([-96.2, 45.3], 'this is not a geojson type'));
        // invalid point
        $this->isFalse(Utils::areValidCoordinates([76, 90.00001], 'Point'));
        // valid point but with multipoint
        $this->isFalse(Utils::areValidCoordinates([-96.2, 45.3], 'MultiPoint'));
        // valid multipoint
        $this->isTrue(Utils::areValidCoordinates([[-96.2, 45.3],[-180, 90]], 'MultiPoint'));
        // invalid multipoint
        $this->isFalse(Utils::areValidCoordinates([-96.2, 45.3], 'MultiPoint'));
        // valid polygon
        $this->isTrue(Utils::areValidCoordinates($s, 'Polygon'));
        // invalid multipolygon
        $this->isFalse(Utils::areValidCoordinates([[[$s, $s, $s, $s], [$s]]], 'MultiPolygon'));
        // invalid multipolygon with type linestring
        $this->isFalse(Utils::areValidCoordinates([[[$s, $s, $s, $s], [$s]]], 'LineString'));
    }
    
    function testSetValidType() {
        // 1 Point
        $this->checkString(Utils::setValidType('Point'), 'Point');
        // 2 multipoint
        $this->checkString(Utils::setValidType('multipoint'), 'MultiPoint');
        // 3 lineString
        $this->checkString(Utils::setValidType('lineString'), 'LineString');
        // 4 multilinestrings
        $this->checkString(Utils::setValidType('multilinestrings'), 'Point');
        // 5 nada
        $this->checkString(Utils::setValidType(''), 'Point');
    }

    function testSetBounds() {
        // 1 valid bounds
        $this->isTrue(!empty(Utils::setBounds(['south' => 87, 'west' => -100, 'north'=> -0.1, 'east'=> 50])));
        // 2 invalid bounds
        $this->isTrue(empty(Utils::setBounds(['south'=>-100,'west'=> 87, 'north'=>-0.1,'east'=> 50])));
        // 3 only three points
        $this->isTrue(empty(Utils::setBounds(['south'=>87,'west'=> -100, 'north'=>-0.1])));
        // 4 not an array
        $this->isTrue(empty(Utils::setBounds('87')));
        // 5 array without the necessary keys
        $this->isTrue(empty(Utils::setBounds([87, -100, -0.1, 50])));
    }

    function testGetPageRoute() {
        // 1
        $keys1 = ['home', 'subpage-2'];
        $this->isTrue($this->fileExists(Utils::getPageRoute($keys1).'default.md'));
        // 2
        $keys2 = ['tour-1', '_view-1'];
        $this->isTrue($this->fileExists(Utils::getPageRoute($keys2).'view.md'));
        // 3
        $keys3 = ['02.tour-1'];
        $this->isTrue($this->fileExists(Utils::getPageRoute($keys3).'tour.md'));
        // 4
        $keys4 = ['pages', 'tour-1'];
        $this->isFalse($this->fileExists(Utils::getPageRoute($keys4).'tour.md'));
        // 5
        $keys5 = ['modules', 'footer'];
        $this->isTrue($this->fileExists(Utils::getPageRoute($keys5).'default.md'));
        // 6
        $keys6 = [''];
        $this->isFalse($this->fileExists(Utils::getPageRoute($keys6)));
        // 7
        $keys7 = ['home', 'subpage-2', 'default.md'];
        $this->isFalse($this->fileExists(Utils::getPageRoute($keys7)));
    }

    protected function fileExists($route): bool {
        $file = File::instance($route);
        return $file->exists();
    }

    // TODO: Much more complicated test
    /*function testParseDatasetUpload() {
        //
    }*/

    // TODO: Requires Dataset::getDatasets()
    /*function testGenerateShortcodeList() {
        $keys = array_keys(Dataset::getDatasets());
        $datasets = Dataset::getDatasets();
        $features1 = $datasets[$keys[0]]->getFeatures();
        $features2 = $datasets[$keys[1]]->getFeatures();
        // features1 with datasets2
        $text = "Features from dataset 1 with dataset 2:\r\n";
        $text .= Utils::generateShortcodeList($features1, [$datasets[$keys[1]]]);
        // features1 with datasets1
        $text .= "\r\n\r\nFeatures from dataset 1 with dataset 1:\r\n";
        $text .= Utils::generateShortcodeList($features1, [$datasets[$keys[0]]]);
        // features2 with datasets 1 and 2
        $text .= "\r\n\r\nFeatures from dataset 2 with datasets 1 and 2:\r\n";
        $text .= Utils::generateShortcodeList($features2, [$datasets[$keys[0]], $datasets[$keys[1]]]);
    }*/
}

?>