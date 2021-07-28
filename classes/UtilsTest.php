<?php
namespace Grav\Plugin\LeafletTour;

// use Grav\Common\Grav;
use RocketTheme\Toolbox\File\File;

class UtilsTest extends Test {

    protected $linearRing;
    
    protected function setup() {
        parent::setup();
        $this->linearRing = [[180, 80], [120, -80], [-89, 0], [0, 22.3453], [180, 80]];
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

    function testIsValidPolygon() {
        $l = $this->linearRing;
        // 1 linear ring
        $this->isFalse(Utils::isValidPolygon($l));
        // 2 array of linear ring
        $this->isTrue(Utils::isValidPolygon([$l]));
        // 3 array of linear rings
        $this->isTrue(Utils::isValidPolygon([$l, $l, $l]));
        // 4 array of arrays of polygons
        $this->isFalse(Utils::isValidPolygon([[$l, $l, $l, $l]]));
        // 5 point
        $this->isFalse(Utils::isValidPolygon([67, 80]));
        // 6 less than three points
        $this->isFalse(Utils::isValidPolygon([[[180, 80], [120, -80]]]));
        // 7 invalid points
        $this->isFalse(Utils::isValidPolygon([[[180, 80], [120, -80], [-89, 91], [0, 22.3453]]]));
        // 8 start and end don't match
        $this->isFalse(Utils::isValidPolygon([[[180, 80], [120, -80], [-89, 0], [0, 22.3453]]]));
    }

    function testIsValidMultiPolygon() {
        $p = [$this->linearRing];
        // 1 array with one polygon
        $this->isTrue(Utils::isValidMultiPolygon([$p]));
        // 2 array of polygons
        $this->isTrue(Utils::isValidMultiPolygon([$p, $p, $p, $p]));
        // 3 array of array of polygons
        $this->isFalse(Utils::isValidMultiPolygon([[$p, $p, $p, $p], [$p]]));
        // 4 polygon
        $this->isFalse(Utils::isValidMultiPolygon($p));
        // 6 array of polygons with one invalid polygon
        $a = [$p, $p, $p, $p, [[[180, 80], [120, -80]]]];
        $this->isFalse(Utils::isValidMultiPolygon($a));
        // 7 big example
        $this->isTrue(Utils::isValidMultiPolygon([[[[-81.638434886224047,29.245855658431633],[-81.638313526589897,29.245721174930871],[-81.638303875754076,29.245697653601848],[-81.638295902030137,29.245673512115424],[-81.638289644863093,29.245648869887187],[-81.638285135206374,29.245623848804822],[-81.638282395357223,29.24559857263375],[-81.638281438874387,29.245573166399478],[-81.638282270490876,29.245547755771437],[-81.638284886088499,29.245522466440885],[-81.638289272734241,29.245497423498815],[-81.63829540872392,29.245472750817495],[-81.638303263709517,29.245448570437471],[-81.638312798841056,29.245425001964573],[-81.638323966944867,29.245402161978742],[-81.638336712789155,29.24538016345377],[-81.63835097332047,29.245359115204334],[-81.638366678005681,29.245339121343928],[-81.638383749159388,29.245320280768279],[-81.638402102344102,29.2453026866724],[-81.638421646777701,29.245286426082199],[-81.638442285780897,29.245271579428842],[-81.638463917268368,29.245258220151296],[-81.638486434243518,29.24524641432799],[-81.638509725325918,29.245236220356198],[-81.638533675310185,29.245227688659281],[-81.638558165727943,29.245220861439087],[-81.638673882046291,29.245159710760859],[-81.63879049023538,29.245100278428705],[-81.638907964781524,29.245042577450473],[-81.639026279975937,29.244986620448795],[-81.6391454099344,29.244932419668199],[-81.639265328589985,29.24487998696965],[-81.639386009698924,29.244829333824459],[-81.639468963861674,29.244801155633191],[-81.639552729009822,29.244775488468346],[-81.639637229156492,29.24475235561567],[-81.63972238764471,29.244731778060665],[-81.639808127214721,29.244713774468888],[-81.639894370091298,29.244698361175956],[-81.639981038035089,29.244685552162206],[-81.639990500683098,29.24469097709515],[-81.639999583764805,29.244697016071321],[-81.640008247497448,29.244703642640157],[-81.640016453922712,29.244710827773812],[-81.640024167103533,29.244718540001482],[-81.640031353254003,29.244726745541808],[-81.640037980891051,29.244735408451557],[-81.640044020990885,29.244744490787522],[-81.640049447090846,29.244753952764764],[-81.640054235431293,29.24476375294034],[-81.640058365032004,29.24477384838519],[-81.640061817804948,29.244784194880591],[-81.640064578627047,29.244794747106422],[-81.64006663540566,29.244805458841252],[-81.64006797913315,29.24481628316607],[-81.64006860391963,29.244827172668007],[-81.640068507032979,29.244838079647703],[-81.640067688898839,29.244848956332131],[-81.640066153093343,29.24485975507832],[-81.640063906350392,29.244870428585269],[-81.640060958507078,29.244880930101314],[-81.640057322478228,29.244891213626939],[-81.640053014187274,29.244901234117602],[-81.64004805250805,29.244910947683817],[-81.640042459171312,29.244920311775338],[-81.640036258685768,29.24492928537801],[-81.640029478198258,29.244937829184142],[-81.639977163074406,29.244976436273216],[-81.639924500749686,29.245014568404713],[-81.639871495546672,29.245052222457158],[-81.639818151790664,29.245089395346437],[-81.63976447385815,29.245126084027561],[-81.639710466147704,29.245162285499216],[-81.639656133071639,29.245197996796318],[-81.639615020545108,29.245236076288219],[-81.639576319038866,29.245276603820905],[-81.63954017405193,29.245319427029241],[-81.639506721461643,29.245364384918499],[-81.639476087040208,29.245411308470914],[-81.639448385959923,29.245460021277776],[-81.639423722357861,29.245510340203204],[-81.639357446078847,29.245577700385187],[-81.639287479576979,29.245641219102712],[-81.639214045160756,29.245700694526018],[-81.63913737617257,29.24575593767349],[-81.639057716218758,29.245806773009921],[-81.638975318423817,29.245853039009493],[-81.638890444604527,29.245894588664228],[-81.638861091892991,29.245907110716924],[-81.638830937503982,29.245917555642787],[-81.638800128224815,29.245925872597578],[-81.638768814020281,29.245932021097357],[-81.638737147317428,29.245935971213836],[-81.638705282257419,29.245937703719388],[-81.638673373952756,29.24593721017936],[-81.638641577719667,29.245934492998455],[-81.638610048332325,29.245929565400726],[-81.638578939262501,29.245922451374096],[-81.638548401941065,29.245913185545756],[-81.638518585015831,29.24590181301846],[-81.638489633617311,29.245888389150874],[-81.63846168867714,29.245872979284339],[-81.638434886224047,29.245855658431633]]]]));
    }

    // isValidLineString - covered by isValidMultiPoint

    // isValidMultiLineString - unnecessary, should be covered by the other tests

    function testAreValidCoordinates() {
        $p = [$this->linearRing];
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
        $this->isTrue(Utils::areValidCoordinates($p, 'Polygon'));
        // invalid multipolygon
        $this->isFalse(Utils::areValidCoordinates([[$p, $p, $p, $p], [$p]], 'MultiPolygon'));
        // invalid multipolygon with type linestring
        $this->isFalse(Utils::areValidCoordinates([[$p, $p, $p, $p], [$p]], 'LineString'));
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
        $this->isNotEmpty(Utils::setBounds(['south' => 87, 'west' => -100, 'north'=> -0.1, 'east'=> 50]));
        // 2 invalid bounds
        $this->isEmpty(Utils::setBounds(['south'=>-100,'west'=> 87, 'north'=>-0.1,'east'=> 50]));
        // 3 only three points
        $this->isEmpty(Utils::setBounds(['south'=>87,'west'=> -100, 'north'=>-0.1]));
        // 4 not an array
        $this->isEmpty(Utils::setBounds('87'));
        // 5 array without the necessary keys
        $this->isEmpty(Utils::setBounds([87, -100, -0.1, 50]));
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