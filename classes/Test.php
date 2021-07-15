<?php
namespace Grav\Plugin\LeafletTour;

use Symfony\Component\Yaml\Yaml;

class Test {

    protected static $instance;

    protected $results;
    protected $errors;

    public function test(): void {
        $this->testFeature();
    }

    public function getResults(): string {
        // fix php's bad json handling
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set( 'serialize_precision', -1 );
        }

        $this->test();
        $text = "";
        if ($this->errors > 0) {
            $text .= "<div style='color: red'>Failure: $this->errors of ".count($this->results)." tests failed.</div>";
        } else {
            $text .= "<div style='color: green'>Success: All tests passed.</div>";
        }
        foreach ($this->results as $result) {
            $text .= $this->printResult($result);
        }
        return $text;
    }

    function __construct() {
        $this->results = [];
        $this->errors = 0;
    }

    public function instance() {
        if (null === self::$instance) {
            self::$instance = new Test();
        }
        return self::$instance;
    }

    public function runTest($name, $function, $args = []) {
        $result = ['name'=>$name, 'errorMsg'=>null];
        try {
            $print = $function($args);
            if ($print) {
                $result['text'] = $print;
            }
        } catch (\Throwable $t) {
            $result['errorMsg'] = $t->getMessage();
            $this->errors += 1;
        } finally {
            $this->results[] = $result;
        }
    }

    public function runCountTest($name, $count, $function, $args=[]) {
        $args[] = $count;
        $args[] = $function;
        $this->runTest(
            $name,
            function($args) {
                $count = $args[count($args)-2];
                $function = $args[count($args)-1];
                $array = $function($args);
                if (count($array) !== $count) throw new \Exception("Incorrect count: Expected ".$count.", received ".count($array));
            },
            $args
        );
    }

    public function runStringTest($name, $string, $function, $args=[]) {
        $args[] = $string;
        $args[] = $function;
        $this->runTest(
            $name,
            function($args) {
                $string = $args[count($args)-2];
                $function = $args[count($args)-1];
                $testString = $function($args);
                if ($testString !== $string) throw new \Exception("String match failed: Expected '$string', received '$testString'");
            },
            $args
        );
    }

    protected function printResult($result) {
        $text = "<div>".$result['name'].": ";
        if ($result['errorMsg']) {
            $text .= "<span style='color: red'>Failure<span><p style='color: red'>".$result['errorMsg']."</p></div>";
        } else {
            $text .= "<span style='color: green'>Success</span>";
            if ($result['text']) {
                $text .= "<pre><code>".$result['text']."</code></pre>";
            }
        }
        return $text;
    }

    function testFeature() {
        // setup
        // provides a list of Point features
        $points = [
            // a normal feature with a strings for all properties
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
        // provides a list of MultiPolygon features
        $multiPoly = [
            // a normal feature with a strings for all properties
            [
                "id"=>'multiPoly1',
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
                "id"=>'multiPoly2',
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
                "id"=>'multiPoly3',
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
                "id"=>'multiPoly4',
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
        // provides a list with both Point and MultiPolygon features
        $multi = [
            // a normal feature with a strings for all properties
            [
                "id"=>'multi1',
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
                "id"=>'multi2',
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
                "id"=>'multi3',
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
                "id"=>'multi4',
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
        // provides a list with two good Point features and two random things
        $partialBad = [
            // a normal feature with a strings for all properties
            [
                "id"=>'partialBad1',
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
                "id"=>'partialBad2',
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
        ];
        // provides a non geoJSON list
        $bad = [
            ['id'=>'bad1', 'type'=>'Point'],
            ['name'=>'meh'],
            ['this', 'that', 'the other'],
        ];

        $this->runCountTest(
            "Feature: buildFeatureList() with point list and type='Point'",
            4,
            function($args) {
                return Feature::buildFeatureList($args[0], null, 'Point');
            },
            [$points]
        );
        $this->runCountTest(
            "Feature: buildFeatureList() with multi list and type='MultiPolygon'",
            2,
            function($args) {
                return Feature::buildFeatureList($args[0], null, 'MultiPolygon');
            },
            [$multi]
        );
        $this->runCountTest(
            "Feature: buildFeatureList() with a partial bad list",
            2,
            function($args) {
                return Feature::buildFeatureList($args[0]);
            },
            [$partialBad]
        );
        $this->runCountTest(
            "Feature: buildFeatureList() with a bad list",
            0,
            function($args) {
                return Feature::buildFeatureList($args[0]);
            },
            [$bad]
        );
        $this->runCountTest(
            "Feature: buildFeatureList() with points and nameProperty='prop1'",
            3,
            function($args) {
                return Feature::buildFeatureList($args[0], 'prop1');
            },
            [$points]
        );
        $this->runCountTest(
            "Feature: buildFeatureList() with multi poly list and nameProp='prop2'",
            4,
            function($args) {
                return Feature::buildFeatureList($args[0],'prop2');
            },
            [$multiPoly]
        );
        $this->runCountTest(
            "Feature: buildFeatureList() with multi list, nameProp='prop3', and type='Point'",
            1,
            function($args) {
                return Feature::buildFeatureList($args[0], 'prop3', 'Point');
            },
            [$multi]
        );
        $this->runCountTest(
            "Feature: buildFeatureList() with multi list and nameProp='prop4'",
            1,
            function($args) {
                return Feature::buildFeatureList($args[0], 'prop4');
            },
            [$multi]
        );
        $this->runStringTest(
            "Feature: getName() with multiPoly1 and prop2",
            "feat 1, prop 2",
            function($args) {
                return Feature::buildFeatureList($args[0], 'prop2')['multiPoly1']->getName('prop2');
            },
            [$multiPoly]
        );
        $this->runStringTest(
            "Feature: getName() with multiPoly3 and prop2",
            "Feature 3 Custom Name",
            function($args) {
                return Feature::buildFeatureList($args[0], 'prop2')['multiPoly3']->getName('prop2');
            },
            [$multiPoly]
        );
        $this->runStringTest(
            "Feature: getName() with point1 and prop1",
            "feat 1, prop 1",
            function($args) {
                return Feature::buildFeatureList($args[0], 'prop1')['point1']->getName('prop1');
            },
            [$points]
        );
        $this->runStringTest(
            "Feature: getName() with point3 and prop1",
            "Feature 3 Custom Name",
            function($args) {
                return Feature::buildFeatureList($args[0], 'prop1')['point3']->getName('prop1');
            },
            [$points]
        );
        $this->runTest(
            "Feature: buildJson() with points",
            function($args) {
                return json_encode(Feature::buildJsonList(Feature::buildFeatureList($args[0])), JSON_PRETTY_PRINT);
            },
            [$points]
        );
        $this->runTest(
            "Feature: buildJson() with multi poly",
            function($args) {
                return json_encode(Feature::buildJsonList(Feature::buildFeatureList($args[0])), JSON_PRETTY_PRINT);
            },
            [$multiPoly]
        );
        $this->runTest(
            "Feature: buildYaml() with points and prop1",
            function($args) {
                return Yaml::dump(Feature::buildYamlList(Feature::buildFeatureList($args[0], 'prop1'), 'prop1'));
            },
            [$points]
        );
        $this->runTest(
            "Feature: buildYaml() with multi poly and prop1",
            function($args) {
                return Yaml::dump(Feature::buildYamlList(Feature::buildFeatureList($args[0], 'prop1'), 'prop1'));
            },
            [$multiPoly]
        );
        $this->runTest(
            "Feature: buildConfig() with all three lists and prop1",
            function($args) {
                $f = array_merge(Feature::buildFeatureList($args[0]), Feature::buildFeatureList($args[1]), Feature::buildFeatureList($args[2]));
                $results = '';
                foreach(Feature::buildConfigList($f, 'prop1') as $id=>$name) {
                    $results .= "$id - $name\r\n";
                }
                return $results;
            },
            [$points, $multiPoly, $multi]
        );
    }
}

?>