<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Plugin\LeafletTour\Feature;
use Symfony\Component\Yaml\Yaml;

class FeatureTest {
    // setup functions
    /**
     * provides a list of Point features
     */
    protected static function buildPointList(): array {
        return [
            // a normal feature with a strings for all properties
            [
                "id"=>'set1_feat1',
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
                "id"=>'set1_feat2',
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
                "id"=>'set1_feat3',
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
                "id"=>'set1_feat4',
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
    }
    // provides a list of MultiPolygon features
    protected static function buildMultiPolyList(): array {
        return [
            // a normal feature with a strings for all properties
            [
                "id"=>'set2_feat1',
                "type"=>"feature",
                "properties"=>[
                    "prop1"=>'feat 1, prop 1',
                    "prop2"=>'feat 1, prop 2',
                    "prop3"=>'feat 1, prop 3',
                ],
                "geometry"=>[
                    "type"=>'MultiPolygon',
                    "coordinates"=>[[[[-81.638434886224047,29.245855658431633],[-81.638313526589897,29.245721174930871],[-81.638489633617311,29.245888389150874],[-81.63846168867714,29.245872979284339],[-81.638434886224047,29.245855658431633]]]],
                ],
            ],
            // a feature with a string, number, and bool for properties
            [
                "id"=>'set2_feat2',
                "type"=>"feature",
                "properties"=>[
                    "prop1"=>'feat 2, prop 1',
                    "prop2"=>12,
                    "prop3"=>false,
                ],
                "geometry"=>[
                    "type"=>'MultiPolygon',
                    "coordinates"=>[[[[-81.640748500744948,29.24394561743755],[-81.640763740495643,29.243862630098345],[-81.640782047581112,29.243978106260773],[-81.640764717927695,29.243962436200441],[-81.640748500744948,29.24394561743755]]]],
                ],
            ],
            // a feature with only one property, but a custom name
            [
                "id"=>'set2_feat3',
                "customName"=>'Feature 3 Custom Name',
                "type"=>"feature",
                "properties"=>[
                    "prop2"=>'feat 3, only property',
                ],
                "geometry"=>[
                    "type"=>'MultiPolygon',
                    "coordinates"=>[[[[-81.642010209887289,29.244133032522903],[-81.641962251008678,29.244022710360593],[-81.642028897347657,29.244155279577644],[-81.642010209887289,29.244133032522903]]]],
                ],
            ],
            // a feature with a null, bool, and number property
            [
                "id"=>'set2_feat4',
                "type"=>"feature",
                "properties"=>[
                    "prop1"=>null,
                    "prop2"=>60,
                    "prop3"=>true,
                ],
                "geometry"=>[
                    "type"=>'MultiPolygon',
                    "coordinates"=>[[[[-81.64126191010439,29.242892908356943],[-81.641119071550577,29.242727764596555],[-81.641294851004645,29.242892585727983],[-81.64126191010439,29.242892908356943]]]],
                ],
            ],
        ];
    }
    // provides a list with both Point and MultiPolygon features
    protected static function buildMultiList(): array {
        return [
            // a normal feature with a strings for all properties
            [
                "id"=>'set3_feat1',
                "type"=>"feature",
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
                "id"=>'set3_feat2',
                "type"=>"feature",
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
                "id"=>'set3_feat3',
                "customName"=>'Feature 3 Custom Name',
                "type"=>"feature",
                "properties"=>[
                    "prop2"=>'feat 3, only property',
                ],
                "geometry"=>[
                    "type"=>'MultiPolygon',
                    "coordinates"=>[[[[-81.642010209887289,29.244133032522903],[-81.641962251008678,29.244022710360593],[-81.641959443421726,29.243976969325828],[-81.642070594072706,29.24419570325426],[-81.64204905661903,29.244176202209459],[-81.642028897347657,29.244155279577644],[-81.642010209887289,29.244133032522903]]]],
                ],
            ],
            // a feature with a null, bool, and number property
            [
                "id"=>'set3_feat4',
                "type"=>"feature",
                "properties"=>[
                    "prop1"=>null,
                    "prop2"=>60,
                    "prop3"=>true,
                ],
                "geometry"=>[
                    "type"=>'MultiPolygon',
                    "coordinates"=>[[[[-81.64126191010439,29.242892908356943],[-81.641119071550577,29.242727764596555],[-81.641120468656439,29.242711448501886],[-81.641513367449065,29.242826527547546],[-81.641484559357181,29.242842505687801],[-81.641454691136445,29.242856401599962],[-81.641423913046296,29.24286814537481],[-81.641392379930025,29.242877677930661],[-81.641360250428974,29.242884951311677],[-81.641327686176268,29.242889928923894],[-81.641294851004645,29.242892585727983],[-81.64126191010439,29.242892908356943]]]],
                ],
            ],
        ];
    }
    // provides a list with two good Point features and two random things
    protected static function buildPartialBadList(): array {
        return [
            // a normal feature with a strings for all properties
            [
                "id"=>'set4_feat1',
                "type"=>"feature",
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
                "id"=>'set4_feat2',
                "type"=>"feature",
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
            // a feature with only id
            [
                "id"=>'set4_feat3',
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
        ];
    }
    // provides a non geoJSON list
    protected static function buildBadList(): array {
        return [
            ['id'=>'set5_item1', 'type'=>'Point'],
            ['name'=>'meh'],
            ['this', 'that', 'the other'],
        ];
    }

    public static function test(): string {
        // set up
        $points = self::buildPointList();
        $multiPoly = self::buildMultiPolyList();
        $multi= self::buildMultiList();
        $partialBad = self::buildPartialBadList();
        $bad = self::buildBadList();

        // prepare return value
        $results = "Feature Test Results:";
        // building feature list with type specified works as expected
        $results .="</br>Testing buildFeatureList with type specified";
        // build point list with Point - all good
        $results .= "</br>Build point list with type='Point': ";
        try {
            $features = Feature::buildFeatureList($points, null, 'Point');
            if (count($features) !== 4) throw new \Exception("Incorrect count: ".count($features));
            $results .= self::success();
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        // build multi list with MultiPoly - only MultiPoly
        $results .= "</br>Build multi list with type='MultiPolygon': ";
        try {
            $features = Feature::buildFeatureList($multi, null, 'MultiPolygon');
            if (count($features) !== 2) throw new \Exception("Incorrect count: ".count($features));
            $results .= self::success();
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        // building feature list with a bad list works as expected
        $results .="</br>Testing buildFeatureList with a bad list";
        // partial bad list creates two features
        $results .= "</br>Build partial bad list: ";
        try {
            $features = Feature::buildFeatureList($partialBad);
            if (count($features) !== 2) throw new \Exception("Incorrect count: ".count($features));
            $results .= self::success();
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        // bad list creates nothing
        $results .= "</br>Build bad list: ";
        try {
            $features = Feature::buildFeatureList($bad);
            if (!empty($features)) throw new \Exception("Not empty: ".count($features));
            $results .= self::success();
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        // building feature list with name property works as expected
        $results .="</br>Testing buildFeatureList with nameProperty specified";
        // build point list with prop1 - three results
        $results .= "</br>Build point list with prop1: ";
        try {
            $features = Feature::buildFeatureList($points, 'prop1');
            if (count($features) !== 3) throw new \Exception("Incorrect count: ".count($features));
            $results .= self::success();
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        // build mulitpoly list with prop2 - four results
        $results .= "</br>Build multipoly list with prop2: ";
        try {
            $features = Feature::buildFeatureList($multiPoly, 'prop2');
            if (count($features) !== 4) throw new \Exception("Incorrect count: ".count($features));
            $results .= self::success();
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        // build multi list with point and prop3 - one result
        $results .= "</br>Build multi list with 'prop3' and 'Point': ";
        try {
            $features = Feature::buildFeatureList($multi, 'prop3', 'Point');
            if (count($features) !== 1) throw new \Exception("Incorrect count: ".count($features));
            $results .= self::success();
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        // built multi list with prop4 - no results
        $results .= "</br>Build multi list with 'prop4': ";
        try {
            $features = Feature::buildFeatureList($multi, 'prop4');
            if (count($features) !== 1) throw new \Exception("Incorrect count: ".count($features));
            $results .= self::success();
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        // getName returns correct name
        $results .="</br>Testing getName()";
        // multipoly list with prop2 - 1 is feat 1, prop 2, 3 is Custom Name
        $results .= "</br>multipoly list with prop2: ";
        try {
            $features = Feature::buildFeatureList($multiPoly, 'prop2');
            $name1 = $features['set2_feat1']->getName('prop2');
            $name2 = $features['set2_feat3']->getName('prop2');
            if ($name1 !== 'feat 1, prop 2' || $name2 !== 'Feature 3 Custom Name') throw new \Exception("Incorrect names: $name1 and $name2");
            $results .= self::success();
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        // point list with prop1 - 1 is feat 1, prop 1, 3 is Custom Name
        $results .= "</br>point list with 'prop1': ";
        try {
            $features = Feature::buildFeatureList($points, 'prop1');
            $name1 = $features['set1_feat1']->getName('prop1');
            $name2 = $features['set1_feat3']->getName('prop1');
            if ($name1 !== 'feat 1, prop 1' || $name2 !== 'Feature 3 Custom Name') throw new \Exception("Incorrect names: $name1 and $name2");
            $results .= self::success();
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        $pointList = Feature::buildFeatureList($points);
        $multiPolyList = Feature::buildFeatureList($multiPoly);
        // asJson/buildJson works - print out point and multipoly lists
        $results .="</br>Testing buildJson";
        $results .= "</br>With points: ";
        try {
            $json = json_encode(Feature::buildJsonList($pointList), JSON_PRETTY_PRINT);
            $results .= self::success();
            $results .= "<pre><code>$json</code></pre></br>";
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        $results .= "</br>With multipoly: ";
        try {
            $json = json_encode(Feature::buildJsonList($multiPolyList), JSON_PRETTY_PRINT);
            $results .= self::success();
            $results .= "<pre><code>$json</code></pre></br>";
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        // asYaml/buildYaml works - print out point and multipoly lists
        $results .="</br>Testing buildYaml";
        $results .= "</br>With points: ";
        try {
            $yaml = Yaml::dump(Feature::buildYamlList($multiPolyList, 'prop1'));
            $results .= self::success();
            $results .= "<pre><code>$yaml</pre></code></br>";
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        $results .= "</br>With multipoly: ";
        try {
            $yaml = Yaml::dump(Feature::buildYamlList($multiPolyList, 'prop1'));
            $results .= self::success();
            $results .= "<pre><code>$yaml</pre></code></br>";
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        // buildConfig works - print out combined first three lists
        $results .="</br>Testing buildConfig";
        try {
            $features = array_merge(Feature::buildFeatureList($points), Feature::buildFeatureList($multiPoly), Feature::buildFeatureList($multi));
            $results .="</br>";
            foreach (Feature::buildConfigList($features, 'prop1') as $id=>$name) {
                $results .="\t$id - $name</br>";
            }
            $results .= self::success();
        } catch (\Throwable $e) {
            $results .= self::errMsg($e);
        }
        return $results;
    }

    protected static function errMsg($e) {
        return "<span style='color: red;'>Failure</br>\t".$e->getMessage()."</span>";
    }
    protected static function success() {
        return "<span style='color: green;'>Success</span>";
    }
}