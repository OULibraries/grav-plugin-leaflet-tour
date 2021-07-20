<?php
namespace Grav\Plugin\LeafletTour;

use Symfony\Component\Yaml\Yaml;

class FeatureTest extends Test {
    
    protected function setup() {
        parent::setup();
        $this->testHeader = 'Results for Feature Test';
        $this->pointList = [
            // normal feature with strings for all props
            [
                "id"=>'point1',
                "type"=>"Feature",
                "properties"=>[
                    "prop1"=>'feat 1, prop 1',
                    "prop2"=>'feat 1, prop 2',
                    "prop3"=>'feat 1, prop 3',
                ],
                "geometry"=>[
                    "type"=>'Point',
                    "coordinates"=>[-81.638303263709517,29.245448570437471],
                ],
            ],
            // a feature with a string, number, and bool for properties
            [
                "id"=>'point2',
                "type"=>"Feature",
                "properties"=>[
                    "prop1"=>'feat 2, prop 1',
                    "prop2"=>12,
                    "prop3"=>false,
                ],
                "geometry"=>[
                    "type"=>'Point',
                    "coordinates"=>[20.1, 40.2],
                ],
            ],
            // a feature with only one property, but a custom name
            [
                "id"=>'point3',
                "customName"=>'Feature 3 Custom Name',
                "type"=>"Feature",
                "properties"=>[
                    "prop2"=>'feat 3, only property',
                ],
                "geometry"=>[
                    "type"=>'Point',
                    "coordinates"=>[14.32, 83.21],
                ],
            ],
            // a feature with a null, bool, and number property
            [
                "id"=>'point4',
                "type"=>"Feature",
                "properties"=>[
                    "prop1"=>null,
                    "prop2"=>60,
                    "prop3"=>true,
                ],
                "geometry"=>[
                    "type"=>'Point',
                    "coordinates"=>[-81.638434886224047,29.245855658431633],
                ],
            ],
        ];
        $this->polyList = [
            // a normal feature with a strings for all properties
            [
                "id"=>'poly1',
                "type"=>"feature",
                "properties"=>[
                    "prop1"=>'feat 1, prop 1',
                    "prop2"=>'feat 1, prop 2',
                    "prop3"=>'feat 1, prop 3',
                ],
                "geometry"=>[
                    "type"=>'Polygon',
                    "coordinates"=>[[-81.638434886224047,29.245855658431633],[-81.638313526589897,29.245721174930871],[-81.638489633617311,29.245888389150874],[-81.63846168867714,29.245872979284339],[-81.638434886224047,29.245855658431633]],
                ],
            ],
            // a feature with a string, number, and bool for properties
            [
                "id"=>'poly2',
                "type"=>"feature",
                "properties"=>[
                    "prop1"=>'feat 2, prop 1',
                    "prop2"=>12,
                    "prop3"=>false,
                ],
                "geometry"=>[
                    "type"=>'Polygon',
                    "coordinates"=>[[-81.640748500744948,29.24394561743755],[-81.640763740495643,29.243862630098345],[-81.640782047581112,29.243978106260773],[-81.640764717927695,29.243962436200441],[-81.640748500744948,29.24394561743755]],
                ],
            ],
            // a feature with only one property, but a custom name
            [
                "id"=>'poly3',
                "customName"=>'Feature 3 Custom Name',
                "type"=>"feature",
                "properties"=>[
                    "prop2"=>'feat 3, only property',
                ],
                "geometry"=>[
                    "type"=>'Polygon',
                    "coordinates"=>[[-81.642010209887289,29.244133032522903],[-81.641962251008678,29.244022710360593],[-81.642028897347657,29.244155279577644],[-81.642010209887289,29.244133032522903]],
                ],
            ],
            // a feature with a null, bool, and number property
            [
                "id"=>'poly4',
                "type"=>"feature",
                "properties"=>[
                    "prop1"=>null,
                    "prop2"=>60,
                    "prop3"=>true,
                ],
                "geometry"=>[
                    "type"=>'Polygon',
                    "coordinates"=>[[-81.64126191010439,29.242892908356943],[-81.641119071550577,29.242727764596555],[-81.641294851004645,29.242892585727983],[-81.64126191010439,29.242892908356943]],
                ],
            ],
            [
                "id"=>'poly5',
                "type"=>"feature",
                "properties"=>[
                    "prop1"=>'feat 1, prop 1',
                    "prop2"=>'feat 1, prop 2',
                    "prop3"=>'feat 1, prop 3',
                ],
                "geometry"=>[
                    "type"=>'Polygon',
                    "coordinates"=>[[-81.638434886224047,29.245855658431633],[-81.638313526589897,29.245721174930871],[-81.638489633617311,29.245888389150874],[-81.63846168867714,29.245872979284339],[-81.638434886224047,29.245855658431633]],
                ],
            ],
        ];
        $this->randomList = [
            // a feature with only id
            [
                "id"=>'partialBad3',
            ],
            // a feature with everything but id
            [
                "type"=>"feature",
                "properties"=>[
                    "prop1"=>null,
                    "prop2"=>60,
                    "prop3"=>true,
                ],
                "geometry"=>[
                    "type"=>'Point',
                    "coordinates"=>[-81.638434886224047,29.245855658431633],
                ],
            ],
            ['id'=>'bad1', 'type'=>'Point'],
            ['name'=>'meh'],
            ['this', 'that', 'the other'],
            87,
            'string'
        ];
        $this->missingList = [
            // no geometry at all
            [
                "id"=>'point5',
                "type"=>"Feature",
                "properties"=>[
                    "prop1"=>'feat 1, prop 1',
                    "prop2"=>'feat 1, prop 2',
                    "prop3"=>'feat 1, prop 3',
                ],
            ],
            // no properties at all
            [
                "id"=>'point6',
                "type"=>"Feature",
                "geometry"=>[
                    "type"=>'Point',
                    "coordinates"=>[20.1, 40.2],
                ],
            ],
            // no id
            [
                "customName"=>'Feature 3 Custom Name',
                "type"=>"Feature",
                "properties"=>[
                    "prop2"=>'feat 3, only property',
                ],
                "geometry"=>[
                    "type"=>'Point',
                    "coordinates"=>[14.32, 83.21],
                ],
            ],
            // no type
            [
                "id"=>'point8',
                "properties"=>[
                    "prop1"=>null,
                    "prop2"=>60,
                    "prop3"=>true,
                ],
                "geometry"=>[
                    "type"=>'Point',
                    "coordinates"=>[-81.638434886224047,29.245855658431633],
                ],
            ],
            // no geometry type
            [
                "id"=>'point9',
                "type"=>"Feature",
                "properties"=>[
                    "prop1"=>null,
                    "prop2"=>60,
                    "prop3"=>true,
                ],
                "geometry"=>[
                    "coordinates"=>[-81.638434886224047,29.245855658431633],
                ],
            ],
        ];
    }

    public static function getResults(bool $showSuccess=false, $showPrint = true, $test=null): string {
        return self::getTestResults(new FeatureTest(), $showSuccess, $showPrint);
    }

    // tests

    function testBuildFeatureList() {
        $pointList = $this->pointList;
        $polyList = $this->polyList;
        $randomList = $this->randomList;
        $missingList = $this->missingList;
        // testing what actually gets constructed
        // 1 list of valid points, valid nameProperty, type Point
        $p = Feature::buildFeatureList($pointList, 'prop2', 'Point');
        $this->checkNum(4, count($p));
        // 2 list of valid points, valid nameProperty, no type/bad type
        $p = Feature::buildFeatureList($pointList, 'prop2', 'no type');
        $this->checkNum(4, count($p));
        // 3 list of valid points, partially valid nameProperty
        $p = Feature::buildFeatureList($pointList, 'prop1');
        $this->checkNum(3, count($p));
        // 4 list of valid points, invalid nameProperty
        $p = Feature::buildFeatureList($pointList, 'prop4');
        $this->checkNum(1, count($p));
        // 5 list of valid points, valid nameProperty, type Polygon
        $p = Feature::buildFeatureList($pointList, 'prop4', 'Polygon');
        $this->checkNum(0, count($p));
        // 6 list of valid polygons, type Polygon
        $p = Feature::buildFeatureList($polyList, 'prop2', 'Polygon');
        $this->checkNum(5, count($p));
        // 7 list of points with some type, geometry, and/or geometry type missing
        $p = Feature::buildFeatureList(array_merge($pointList, $missingList), 'prop2');
        $this->checkNum(4, count($p));
        // 8 list of points with some random stuff
        $p = Feature::buildFeatureList(array_merge($pointList, $randomList), 'prop2');
        $this->checkNum(4, count($p));
        // 9 list of points and polygons with type Point
        $p = Feature::buildFeatureList(array_merge($pointList, $polyList), 'prop2', 'Point');
        $this->checkNum(4, count($p));
        // 10 list of points and polygons with type Polygon
        $p = Feature::buildFeatureList(array_merge($pointList, $polyList), 'prop2', 'Polygon');
        $this->checkNum(5, count($p));
        // 11 random stuff
        $p = Feature::buildFeatureList($randomList, 'prop2');
        $this->checkNum(0, count($p));
    }

    function testSetDatasetFields() {
        $p = Feature::buildFeatureList($this->pointList, 'prop2', 'Point');
        $d = [
            'popup_content' => '> quote etc etc',
            'hide' => true
        ];
        // 1 popup content transfers over existing
        $f = $p[array_keys($p)[0]];
        $f->setDatasetFields($d);
        $this->isTrue(!empty($f->getPopup()));
        // 2 null popup replaces existing
        $d['popup_content'] = null;
        $f->setDatasetFields($d);
        $this->isFalse(!empty($f->getPopup()));
        // 3 hide transfers
        $this->isTrue($f->asYaml()['hide']);
    }

    function testUpdate() {
        $p = Feature::buildFeatureList($this->pointList, 'prop2', 'Point');
        $d = [
            'popup_content' => '> quote etc etc',
            'hide' => true,
            'custom_name' => 'something',
            'coordinates' => [98, 4.788],
            'props' => ['stuff'=>'eh'],
        ];
        // 1 add custom name
        $f = $p['point1'];
        $f->update($d);
        $this->checkString('something', $f->getName());
        // _ custom name removal
        // $d['custom_name'] = null;
        // $f->update(['custom_name'=>null]);
        // $this->isTrue(empty($f->getName()));
        // 2 add polygon coords to point
        $f->update(["coordinates"=>[[-81.638434886224047,29.245855658431633],[-81.638313526589897,29.245721174930871],[-81.638489633617311,29.245888389150874],[-81.63846168867714,29.245872979284339],[-81.638434886224047,29.245855658431633]]]);
        $this->isTrue(Utils::isValidPoint($f->asJson()['geometry']['coordinates']));
        // 3
        $this->checkNum(98, $f->asJson()['geometry']['coordinates'][0]);
        // 4 add point coords to point
        $f->update(["coordinates"=>[-81.638303263709517,29.245448570437471]]);
        $this->checkNum(-81.638303263709517, $f->asJson()['geometry']['coordinates'][0]);
        // 5 add invalid point coords to point
        $d['coords'] = [4.788, 98];
        $f->update($d);
        $this->checkNum(98, $f->asJson()['geometry']['coordinates'][0]);
        // 6 properties update?
    }

    function testGetName() {
        $pointList = $this->pointList;
        $features = Feature::buildFeatureList($pointList, 'prop3', 'Point');
        // 1 name when prop is bool false
        $this->isTrue(empty($features['point2']));
        // 2 name when prop is bool true
        $this->isTrue(!empty($features['point4']) && $features['point4']->getName());
        // 3 name when prop is null
        $features = Feature::buildFeatureList($pointList, 'prop1', 'Point');
        $this->isTrue(empty($features['point4']));
        // 4 name when prop doesn't exist
        $features = Feature::buildFeatureList($pointList, 'prop8', 'Point');
        $this->isTrue(empty($features['point1']));
        // 5 name when there is custom name
        $this->checkString('Feature 3 Custom Name', $features['point3']->getName());
        // 6 name when prop has string
        $features = Feature::buildFeatureList($pointList, 'prop2', 'Point');
        $this->checkString('feat 1, prop 2', $features['point1']->getName());
    }

    function testAsJson() {
        $points = Feature::buildFeatureList($this->pointList, 'prop2', 'Point');
        $polygons = Feature::buildFeatureList($this->polyList, 'prop2', 'Polygon');
        // 1 print point feature
        $text = "Point Feature\r\n";
        $text .= json_encode($points['point1']->asJson(), JSON_PRETTY_PRINT);
        // 2 print polygon feature
        $text .= "\r\n\r\nPolygon Feature\r\n".json_encode($polygons['poly1']->asJson(), JSON_PRETTY_PRINT);
        return $text;
    }

    function testAsGeoJson() {
        $points = Feature::buildFeatureList($this->pointList, 'prop2', 'Point');
        $polygons = Feature::buildFeatureList($this->polyList, 'prop2', 'Polygon');
        // 1 print point feature
        $text = "Point Feature\r\n";
        $text .= json_encode($points['point1']->asGeoJson(), JSON_PRETTY_PRINT);
        // 2 print polygon feature
        $text .= "\r\n\r\nPolygon Feature\r\n".json_encode($polygons['poly1']->asGeoJson(), JSON_PRETTY_PRINT);
        return $text;
    }

    function testAsYaml() {
        $points = Feature::buildFeatureList($this->pointList, 'prop2', 'Point');
        $polygons = Feature::buildFeatureList($this->polyList, 'prop2', 'Polygon');
        // 1 print point feature
        $text = "Point Feature\r\n";
        $text .= Yaml::dump($points['point1']->asYaml());
        // 2 print polygon feature
        $text .= "\r\n\r\nPolygon Feature\r\n".$polygons['poly1']->asYaml();
        return $text;
    }

    function testBuildJsonList() {
        $points = Feature::buildFeatureList($this->pointList, 'prop2', 'Point');
        $polygons = Feature::buildFeatureList($this->polyList, 'prop2', 'Polygon');
        // 1 print point features
        $text = "Point Features\r\n".json_encode(Feature::buildJsonList($points), JSON_PRETTY_PRINT);
        // 2 print polygon features
        $text .= "\r\n\r\nPolygon Features\r\n".json_encode(Feature::buildJsonList($polygons), JSON_PRETTY_PRINT);
        return $text;
    }

    function testBuildYamlList() {
        $points = Feature::buildFeatureList($this->pointList, 'prop2', 'Point');
        $polygons = Feature::buildFeatureList($this->polyList, 'prop2', 'Polygon');
        // 1 print point features
        $text = "Point Features\r\n".Yaml::dump(Feature::buildYamlList($points));
        // 2 print polygon features
        $text .= "\r\n\r\nPolygon Features\r\n".Yaml::dump(Feature::buildYamlList($polygons));
        return $text;
    }

    function testBuildConfigList() {
        $points = Feature::buildFeatureList($this->pointList, 'prop2', 'Point');
        $polygons = Feature::buildFeatureList($this->polyList, 'prop2', 'Polygon');
        // 1 print both point and polygon features
        $configList = Feature::buildConfigList(array_merge($points, $polygons));
        $text = '';
        foreach ($configList as $id=>$name) {
            $text .= "$id - $name\r\n";
        }
        return $text;
    }
    
}
?>