<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Plugin\LeafletTourPlugin;
use Grav\Common\File\CompiledYamlFile;
//use Symfony\Component\Yaml\Yaml;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\File\MarkdownFile;

/**
 * This class is for testing the site and should probably not be included in the production site. I know it's possible to set up unit testing in Grav, but it ended up being easier to throw this together than continue trying to figure it out.
 * The site still requires a certain amount of manual testing - uploading, saving, looking at the pages - but this should allow for testing the bulk of the options/functionality. It may also help pinpoint what is causing a specific issue.
 */
class Test {

    protected $errors = 0;
    protected $results = [];
    protected $showPrint;
    protected $showSuccess;
    
    function __construct($showSuccess, $showPrint) {
        // fix php's bad json handling
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set( 'serialize_precision', -1 );
        }
        $this->showSuccess = $showSuccess;
        $this->showPrint = $showPrint;
    }

    /**
     * Method to be called by page.
     * @return string - raw HTML with result content.
     */
    public static function getResults(bool $showSuccess=true, bool $showPrint=true): string {
        $test = new Test($showSuccess, $showPrint);
        try {
            $test->setup();
        } catch (\Throwable $t) {
            return self::printFailure("Failure on setup: ".$t->getMessage());
        }
        $test->runAllTests();
        $test->teardown();
        return $test->printResults();
    }

    /**
     * Once tests have finished running, this function compiles the information into solid HTML output. Includes an overall summary and a summary for each function.
     */
    protected function printResults(): string {
        // total everything up
        $summary = '';
        $results = '';
        $errors = 0;
        $tests = 0;
        foreach ($this->results as $result) {
            $resultArray = $this->printMethodResults($result);
            $results .= $resultArray['text'];
            $errors += $resultArray['errors'] ?? 0;
            $tests += $resultArray['tests'] ?? 0;
        }
        $summary = "<h2>Test Results</h2>";
        if ($errors === 0) {
            $summary .= '<p>'.self::printSuccess("Success: All tests passed ($tests).").'</p>';
        } else {
            $summary .= '<p>'.self::printFailure("$errors of $tests failed.").'</p>';
        }
        return $summary.$results;
    }
    protected function printMethodResults($result) {
        $text = '<h3>'.$result['name'].'</h3>';
        // check if any errors halted the continuation of tests
        if (!empty($result['haltedError'])) {
            $text .= '<p>'.self::printFailure("Failure.")." Method halted with message: ".self::printFailure($result['haltedError']).'</p>';
        }
        $err = $result['errors'];
        $err = $result['errors'];
        if (is_numeric($err) && $err > 0) {
            $text.="<p>".self::printFailure("Error(s) encountered.")." $err of ".count($result['results'])." failed.</p>";
        } else {
            $text .= "<p>".self::printSuccess("Success: All tests passed").'</p>';
        }
        if (!empty($result['results'])) {
            $resultList = '';
            foreach ($result['results'] as $testResult) {
                $resultList .= $this->printSingleResult($testResult);
            }
        }
        if (!empty($resultList)) {
            $text .= "<ol>$resultList</ol>";
        }
        return ['text'=>"<div>".$text."</div>", 'errors'=>$err, 'tests'=>count($result['results'])];
    }
    protected function printSingleResult($result) {
        // was the result success or failure?
        if ($result['error']) {
            $str = self::printFailure("Test failed. ".$result['error']);
        } else if ($result['print'] && $this->showPrint) {
            $str = '<pre><code>'.$result['print'].'</code></pre>';
        } else if ($this->showSuccess) {
            $str = self::printSuccess("Test passed.").' '.$result['success'];
        }
        $str = "<li>$str</li>";
        if ($result['inject']) $str = $result['inject'].$str;
        if ($result['injectEnd']) $str .= $result['injectEnd'];
        return $str;
    }

    // wrapper functions to make HTML generation easier
    protected static function printFailure(string $text): string {
        return "<span style='color: red'>$text</span>";
    }
    protected static function printSuccess(string $text): string {
        return "<span style='color: green'>$text</span>";
    }

    /**
     * Loops through all methods and runs any that being with the word test. Method is executed in a try-catch statement so that if it fails utterly the other methods can still be run (and so that the error message for the failure will be displayed nicely).
     */
    protected function runAllTests() {
        foreach(get_class_methods($this) as $methodName) {
            if (str_starts_with($methodName, 'test')) {
                $this->previousMethod = $this->currentMethod;
                $this->currentMethod = $methodName;
                $this->currentResults = [];
                $this->errors = 0;
                try {
                    $this->$methodName();
                    $this->results[$methodName] = ['name'=>$this->name, 'results'=>$this->currentResults, 'errors'=>$this->errors];
                } catch (\Throwable $t) {
                    $this->results[$methodName] = ['name'=>$this->name, 'results'=>$this->currentResults, 'errors'=>$this->errors, 'haltedError'=>$t->getMessage()];
                }
            }
        }
    }

    /**
     * Checks the value against the required parameters. Throws exception with explanation if the value does not match what is expected.
     * @return array - On standard success, this is an empty array. If there was an error, this is indicated with the 'error' key. If the test provided content to print (for manual observation), this is indicated with the 'print' key.
     */
    protected function tryTest($function, $result, $expected=null) {
        $error = '';
        $success = '';
        try {
            switch ($function) {
                case "true":
                    if ($result !== true) throw new \Exception("Expected true. Received ".var_export($result, true).'.');
                    else $success = "Value is true.";
                    break;
                case "false":
                    if ($result !== false) throw new \Exception("Expected false. Received ".var_export($result, true).'.');
                    else $success = "Value is false.";
                    break;
                case "empty":
                    if (!empty($result)) throw new \Exception("Expected empty argument. Received non-empty ".gettype($result).'.');
                    else $success = "Value is empty.";
                    break;
                case "notEmpty":
                    if (empty($result)) throw new \Exception("Received empty argument.");
                    else $success = "Value is not empty.";
                    break;
                case "equals":
                    if ($result != $expected) throw new \Exception("Expected '$expected'. Received '$result'.");
                    else $success = "Value equals $result.";
                    break;
                case "size":
                    if (count($result) !== $expected) throw new \Exception("Expected size of $expected. Received ".count($result).'.');
                    else $success = "Value has size of $expected.";
                    break;
                case "print":
                    $print = $result;
                default:
                    break;
            }
        } catch (\Throwable $t) {
            $error = $t->getMessage();
            $this->errors++;
        }
        $result = [];
        if (!empty($error)) $result['error'] = $error;
        else if (!empty($success)) $result['success'] = $success;
        if (!empty($print)) $result['print'] = $print;
        if (!empty($this->inject)) {
            $result['inject'] = $this->inject;
            $this->inject = '';
        }
        if (!empty($this->injectPrev)) {
            if (!empty($this->currentResults)) {
                $this->currentResults[count($this->currentResults)-1]['injectEnd'] = $this->injectPrev;
            } else if (!empty($this->previousMethod)) {
                $prev = $this->results[$this->previousMethod] ?? [];
                $prev[count($prev)-1]['injectEnd'] = $this->injectPrev;
            }
            $this->injectPrev = '';
        }
        $this->currentResults[] = $result;
    }

    /**
     * Allows printing out some results for manual observation. The results will be formatted inside of an HTML <pre><code> block, so JSON and YAML content will be easy to read.
     */
    public function print($result) {
        $this->tryTest("print", $result);
    }

    /**
     * Allows injecting some text/content into the results, most added this to be used by the start and end header functions below.
     * The start and end header functions wrap up sections within a test function. This will hopefully make it easier to find individual errors and where they are located in the code.
     */
    public function startHeader($header) {
        $this->inject .= "<li><h4>$header</h4><ol>";
    }
    public function endHeader() {
        $this->injectPrev .= "</ol></li>";
    }

    /**
     * A set of assertion statements for testing. They all call tryTest, which will run a check inside of a try-catch statement.
     * The names should be self-explanatory.
     */
    public function assertTrue($result) {
        $this->tryTest("true", $result);
    }
    public function assertFalse($result) {
        $this->tryTest("false", $result);
    }
    public function assertEmpty($result) {
        $this->tryTest("empty", $result);
    }
    public function assertNotEmpty($result) {
        $this->tryTest("notEmpty", $result);
    }
    public function assertEquals($result, $expected) {
        $this->tryTest("equals", $result, $expected);
    }
    public function assertSize($result, $expected) {
        $this->tryTest("size", $result, $expected);
    }

    /**
     * This is a complicated function so that it can handle initial creation of most of the pages. (So I don't have to keep a bunch of files around and manually upload them when I want to test)
     */
    protected function setup() {
        $pagesRoute = Grav::instance()['locator']->findResource('page://');
        $this->buildTours();
        $this->buildViews();
        $this->modifyPlugin();
        $this->modifyDatasets();
        // make sure modules/footer is included
        $mdFile = MarkdownFile::instance($pagesRoute.'/modules/footer/default.md');
        $mdFile->header(['title'=>'footer', 'routable'=>false, 'visible'=>false]);
        if (empty($mdFile->markdown())) $mdFile->markdown('Custom footer content goes here.');
        $mdFile->save();
        $this->setVars();
    }
    protected function buildTours() {
        
        $pagesRoute = Grav::instance()['locator']->findResource('page://');
        // add/modify tour pages
        $tour0 = [
            'title'=>'Tour 0',
            'content'=>['items'=>'@self.modular'],
            'datasets'=>[
                [
                    'file'=>'MultiPolygons.json',
                    'show_all'=>true,
                ],
                [
                    'file'=>'points1.json',
                    'show_all'=>false,
                    'legend_text'=>'test',
                    'icon'=>[
                        'use_defaults'=>true,
                        'anchor_x'=>5,
                    ],
                ],
                [
                    'file'=>'points2.json',
                    'show_all'=>false,
                    'legend_text'=>'tour 0 legend for points2',
                    'legend_alt'=>null,
                ],
            ],
            'start'=>[
                'location'=>'points1_2', // lat: 0
                'distance'=>8,
            ],
            'legend'=>true,
            'legend_toggles'=>false,
            'only_show_view_features'=>false,
            'list_popup_buttons'=>true,
            'remove_tileserver'=>true,
            'features'=>[
                [
                    'id'=>'points1_0',
                    'remove_popup'=>false,
                    'popup_content'=>null,
                ],
                [
                    'id'=>'points1_3',
                    'remove_popup'=>false,
                    'popup_content'=>null,
                ],
                [
                    'id'=>'points1_4',
                    'remove_popup'=>false,
                    'popup_content'=>null,
                ],
                [
                    'id'=>'MultiPolygons_1',
                    'remove_popup'=>false,
                    'popup_content'=>null,
                ],
                [
                    'id'=>'MultiPolygons_2',
                    'remove_popup'=>false,
                    'popup_content'=>null,
                ],
            ],
            'zoom_min'=>8,
            'zoom_max'=>16,
            'tileserver'=>[
                'select'=>'stamenWatercolor',
                'url'=>'placeholder.com',
                'attribution_text'=>'placeholder',
                'attribution_url'=>null
            ],
            'attribution_long'=>[
                ['text'=>'some stuff goes here'],
            ],
        ];
        $tour1 = [
            'title'=>'Tour 1',
            'content'=>['items'=>'@self.modular'],
            'datasets'=>[],
            'start'=>[
                'location'=>'none',
                'lat'=>60.111,
                'long'=>111.60,
                'distance'=>10,
                'bounds'=>[
                    'north'=>24,
                    'south'=>14,
                    'east'=>16
                ],
            ],
            'legend'=>true,
            'legend_toggles'=>false,
            'only_show_view_features'=>false,
            'list_popup_buttons'=>true,
            'remove_tileserver'=>true,
            'max_bounds'=>[
                'north'=>80,
                'south'=>0,
                'east'=>170,
                'west'=>-170
            ],
            'zoom_max'=>15,
            'tileserver'=>[
                'select'=>'none',
                'url'=>null,
                'attribution_text'=>null,
                'attribution_url'=>null
            ],
            'attribution_list'=>[
                ['text'=>'qgis2web', 'url'=>null],
                ['text'=>'QGIS', 'url'=>'fakeurl.com'],
            ]
        ];
        $tour2 = [
            'title'=>'Tour 2',
            'content'=>['items'=>'@self.modular'],
            'datasets'=>[
                [
                    'file'=>'points1.json',
                    'show_all'=>false,
                    'legend_text'=>'anything',
                    'legend_alt'=>'points 1 legend alt - tour',
                    'icon'=>[
                        'file'=>'Wymansites.png',
                        'anchor_y'=>3,
                        'use_defaults'=>true,
                    ],
                ],
                [
                    'file'=>'points2.json',
                    'show_all'=>false,
                    'legend_text'=>null,
                    'legend_alt'=>null,
                    'icon_alt'=>null,
                    'icon'=>[
                        'file'=>null,
                        'width'=>null,
                        'height'=>null,
                        'use_defaults'=>false,
                    ],
                ],
            ],
            'start'=>[
                'location'=>'points3_1', // invalid - points3.json not included
                'distance'=>18,
            ],
            'legend'=>true,
            'legend_toggles'=>false,
            'only_show_view_features'=>false,
            'list_popup_buttons'=>true,
            'remove_tileserver'=>true,
            'basemaps'=>[
                ['file'=>'LakeMonroe.jpg'],
            ],
            'max_bounds'=>[
                'north'=>85,
                'south'=>-60,
                'east'=>65,
            ],
            'zoom_min'=>8,
            'zoom_max'=>16,
            'tileserver'=>[
                'select'=>'none',
            ],
            'attribution_long'=>[
                ['text'=>'stuff']
            ]
        ];
        $tour3 = [
            'title'=>'Tour 3',
            'content'=>['items'=>'@self.modular'],
            'datasets'=>[
                [
                    'file'=>'points1.json',
                    'show_all'=>true,
                    'icon'=>[
                        'use_defaults'=>true,
                        'file'=>'Wymancamps.png'
                    ],
                ],
                [
                    'file'=>'MultiPolygons.json',
                    'show_all'=>true,
                    'svg'=>[
                        'stroke'=>true,
                        'weight'=>2,
                        'opacity'=>null,
                        'fill'=>false,
                        'fillColor'=>'',
                    ],
                    'svg_active'=>[
                        'stroke'=>true,
                        'color'=>'#112233',
                        'fill'=>true,
                        'fillOpacity'=>0.5,
                    ]
                ],
            ],
            'start'=>[
                'location'=>'none',
                'lat'=>50,
                'long'=>50,
            ],
            'legend'=>true,
            'legend_toggles'=>false,
            'only_show_view_features'=>false,
            'list_popup_buttons'=>true,
            'remove_tileserver'=>true,
            'features'=>[
                [
                    'id'=>'points1_0',
                    'remove_popup'=>false,
                    'popup_content'=>null,
                ],
                [
                    'id'=>'points1_1',
                    'remove_popup'=>false,
                    'popup_content'=>null,
                ],
                [
                    'id'=>'points1_3',
                    'remove_popup'=>true,
                ]
            ],
            'zoom_min'=>8,
            'zoom_max'=>16,
            'wide_column'=>true,
            'show_map_location_in_url'=>false,
            'tileserver'=>[
                'select'=>null,
                'url'=>null,
                'attribution_text'=>null,
                'attribution_url'=>null
            ],
            'attribution_list'=>[
                ['text'=>'item 1', 'url'=>'libraries.ou.edu'],
                ['text'=>'item 2']
            ]
        ];
        $tour4 = [
            'title'=>'Tour 4',
            'content'=>['items'=>'@self.modular'],
            'datasets'=>[
                [
                    'file'=>'points1.json', // 7 valid features
                    'show_all'=>true,
                    'legend_text'=>'test',
                    'icon'=>[
                        'width'=>18,
                        'shadow_height'=>2,
                        'use_defaults'=>false,
                    ],
                ],
                [
                    'file'=>'points2.json', // 6 valid features
                    'show_all'=>true,
                    'icon'=>[
                        'file'=>'PlaceNames.png',
                        'use_defaults'=>false,
                        'anchor_x'=>3,
                    ],
                ],
                [
                    'file'=>'MultiPolygons.json', // 4 valid features
                    'show_all'=>false,
                ],
            ],
            'start'=>[
                'location'=>'points2_1', // lat: 11
                'lat'=>24,
                'long'=>24,
                'distance'=>3.5,
                'bounds'=>[
                    'north'=>80,
                    'south'=>-92,
                    'east'=>60,
                    'west'=>-60
                ],
            ],
            'legend'=>true,
            'legend_toggles'=>false,
            'only_show_view_features'=>false,
            'list_popup_buttons'=>true,
            'remove_tileserver'=>true,
            'basemaps'=>[
                ['file'=>'Map1873.png'],
                ['file'=>'Small Map.jpg'],
            ],
            'zoom_min'=>8,
            'zoom_max'=>16,
            'attribution_list'=>[
                ['text'=>'Attribution Item', 'url'=>null],
                ['text'=>null, 'url'=>'myfakeurl.com']
            ]
        ];
        $tour5 = [
            'title'=>'Tour 5',
            'content'=>['items'=>'@self.modular'],
            'datasets'=>[
                [
                    'file'=>'points2.json',
                    'show_all'=>false,
                    'icon'=>[
                        'use_defaults'=>false,
                    ],
                ],
                [
                    'file'=>'points3.json',
                    'show_all'=>true,
                    'legend_text'=>'points3 legend',
                    'legend_alt'=>'points 3 legend alt',
                    'icon_alt'=>null,
                    'icon'=>[
                        'anchor_x'=>81,
                        'use_defaults'=>false,
                    ],
                ],
                [
                    'file'=>'Polygons.json',
                    'show_all'=>true,
                ],
            ],
            'start'=>[
                'location'=>'points3_1',
                'lat'=>60.12,
                'long'=>70.13,
                'distance'=>5,
                'bounds'=>[
                    'north'=>30,
                    'south'=>10,
                    'east'=>165,
                    'west'=>123.456
                ],
            ],
            'legend'=>true,
            'legend_toggles'=>false,
            'only_show_view_features'=>false,
            'list_popup_buttons'=>true,
            'remove_tileserver'=>true,
            'features'=>[
                [
                    'id'=>'Polygons_0',
                    'remove_popup'=>false,
                    'popup_content'=>'Popup added by the tour',
                ],
                [
                    'id'=>'points3_1',
                    'remove_popup'=>true,
                    'popup_content'=>null,
                ],
                [
                    'id'=>'points3_0',
                    'remove_popup'=>false,
                    'popup_content'=>null,
                ],
                [
                    'id'=>'points3_3',
                    'remove_popup'=>true,
                    'popup_content'=>'Popup from the tour. Remove popup is also set.',
                ],
                [
                    'id'=>'Polygons_1',
                    'remove_popup'=>false,
                    'popup_content'=>'Popup added by the tour',
                ],
                [
                    'id'=>'points3_4',
                    'remove_popup'=>false,
                    'popup_content'=>'tour 5 popup for points3_4',
                ],
                [
                    'id'=>'points2_1',
                    'remove_popup'=>false,
                    'popup_content'=>null,
                ],
                [
                    'id'=>'points2_2',
                    'remove_popup'=>false,
                    'popup_content'=>null,
                ],
            ],
            'zoom_min'=>8,
            'zoom_max'=>16,
            'tileserver'=>[
                'select'=>'none',
                'url'=>null,
                'attribution_text'=>null,
                'attribution_url'=>null
            ],
        ];
        $tours = [
            ['folder'=>'01.tour-0', 'index'=>'0', 'data'=>$tour0],
            ['folder'=>'tour-1', 'index'=>'1',  'data'=>$tour1],
            ['folder'=>'02.tour-2', 'index'=>'2',  'data'=>$tour2],
            ['folder'=>'tour-3', 'index'=>'3',  'data'=>$tour3],
            ['folder'=>'tour-4', 'index'=>'4',  'data'=>$tour4],
            ['folder'=>'tour-5', 'index'=>'5',  'data'=>$tour5]
        ];
        // loop through all of the tours
        foreach ($tours as $tour) {
            // get the tour file (doesn't matter if it already exists or not)
            $mdFile = MarkdownFile::instance($pagesRoute.'/'.$tour['folder'].'/tour.md');
            $mdFile->header($tour['data']);
            $mdFile->save();
            // update popups pages (since onAdminSave isn't being called)
            $mdFile = MarkdownFile::instance($pagesRoute.'/popups/tour-'.$tour['index'].'/default.md');
            if (!$mdFile->exists() || empty($mdFile->markdown())) {
                $content = "";
                // add link, uri rootUrl - http.../site-name
                $content .= '<a href="'.Grav::instance()['uri']->rootUrl(true).'/tour-'.$tour['index'].'" class="btn">Return to Tour</a>';
                // add shortcode
                $content .= "\n\n".'[list-tour-popups-route="'.$pagesRoute.'/'.$tour['folder'].'/tour.md"][/list-tour-popups]';
                $mdFile->markdown($content);
                $mdFile->header(['title'=>$tour['title'], 'visible'=>0]);
                $mdFile->save();
            }
        }
    }
    protected function buildViews() {
        $pagesRoute = Grav::instance()['locator']->findResource('page://');
        // add/modify view pages
        $tour0_view1 = [
            'title'=>'View for Tour 0',
            'body_classes'=>'modular'
        ];
        $tour3_view = [
            'title'=>'View for Tour 3',
            'body_classes'=>'modular',
            'basemaps'=>[['file'=>'Map1873.png']],
        ];
        $tour4_view = [
            'title'=>'View for Tour 4',
            'body_classes'=>'modular',
            'basemaps'=>[
                ['file'=>'Small Map.jpg'],
                ['file'=>'Glot18.jpg']
            ],
        ];
        $view1 = [
            'title'=>'View 1',
            'body_classes'=>'modular',
            'only_show_view_features'=>true,
            'start'=>[
                'location'=>'none',
                'lat'=>18.19,
                'long'=>17.16,
            ]
        ];
        $view2 = [
            'title'=>'View 2',
            'body_classes'=>'modular',
            'features'=>[
                ['id'=>'points3_1']
            ],
            'no_tour_basemaps'=>true,
            'start'=>[
                'bounds'=>[
                    'south'=>21.55,
                    'north'=>50,
                    'east'=>-20,
                    'west'=>-40
                ]
            ]
        ];
        $view3 = [
            'title'=>'View 3',
            'body_classes'=>'modular',
            'basemaps'=>[
                ['file'=>'Small Map.jpg'],
                ['file'=>'Map1873.png']
            ],
            'features'=>[
                ['id'=>'Polygons_0'], // popup
                ['id'=>'points3_1'], // no popup
                ['id'=>'points3_2'], // popup
                ['id'=>'points3_3'], // popup
                ['id'=>'points2_1'] // no popup
            ],
            'only_show_view_features'=>true,
            'remove_tileserver'=>false,
            'start'=>[
                'location'=>'points3_0', // long: 179.11
                'lat'=>9,
                'long'=>9,
                'distance'=>9,
            ]
        ];
        $view4 = [
            'title'=>'View 4',
            'body_classes'=>'modular',
            'features'=>[
                ['id'=>'Polygons_2'],
                ['id'=>'points2_2'],
                ['id'=>'points2_1']
            ],
            'only_show_view_features'=>true,
            'start'=>[
                'location'=>'points2_0', // from hidden features
                'distance'=>2
            ]
        ];
        $view5 = [
            'title'=>'View 5',
            'body_classes'=>'modular',
            'features'=>[
                ['id'=>'Polygons_1'], // valid - popup
                ['id'=>'points3_4'], // valid - popup
                ['id'=>'points3_1'], // valid - no popup
                ['id'=>'points1_0'], // invalid - no popup
                ['id'=>'points1_3'], // invalid - popup
                ['id'=>'Wymansites_4'], // invalid - popup
                ['id'=>'LineStrings_1'] // invalid - no popup
            ],
            'start'=>[
                'location'=>'Wymancamps_3', // not in tour features
                'distance'=>12
            ]
        ];
        $view6 = [
            'title'=>'View 6',
            'body_classes'=>'modular',
            'start'=>[
                'location'=>'Polygons_2',
                'distance'=>10
            ]
        ];
        // loop through all views
        $views = [
            ['tour'=>'/01.tour-0', 'data'=>$tour0_view1],
            ['tour'=>'/tour-3', 'data'=>$tour3_view],
            ['tour'=>'/tour-4', 'data'=>$tour4_view],
            ['view'=>'1', 'data'=>$view1],
            ['view'=>'2', 'data'=>$view2],
            ['view'=>'3', 'data'=>$view3],
            ['view'=>'4', 'data'=>$view4],
            ['view'=>'5', 'data'=>$view5],
            ['view'=>'6', 'data'=>$view6]];
        foreach ($views as $view) {
            // get the view file
            $view['tour'] ??= '/tour-5';
            $view['view'] ??= '0';
            $mdFile = MarkdownFile::instance($pagesRoute.$view['tour'].'/_view-'.$view['view'].'/view.md');
            $mdFile->header($view['data']);
            $mdFile->save();
        }
    }
    protected function modifyPlugin() {
        // modify plugin settings
        $plugin = [
            'basemaps'=>[
                [
                    'file'=>'Map1873.png',
                    'bounds'=>[
                        'south'=>27.474,
                        'west'=>-83.47,
                        'north'=>30.94,
                        'east'=>-80.35,
                    ],
                    'zoom_max'=>11,
                    'zoom_min'=>8,
                    'attribution_text'=>'Map 1873',
                    'attribution_url'=>null,
                ],
                [
                    'file'=>'Glot18.jpg',
                    'bounds'=>[
                        'south'=>28.873378634,
                        'west'=>-81.367955392,
                        'north'=>28.972581275,
                        'east'=>-81.262076589,
                    ],
                    'zoom_max'=>16,
                    'zoom_min'=>8,
                ],
                [
                    'file'=>'Small Map.jpg',
                    'bounds'=>[
                        'south'=>28.873,
                        'west'=>-81.368,
                        'north'=>28.973,
                        'east'=>-81.262,
                    ],
                    'zoom_max'=>16,
                    'zoom_min'=>13,
                    'attribution_text'=>'Small Map',
                    'attribution_url'=>'libraries.ou.edu',
                ],
                [
                    'file'=>'LakeMonroe.jpg',
                    'bounds'=>[
                        'south'=>27,
                        'west'=>-83,
                        'north'=>30,
                        'east'=>-80,
                    ],
                    'zoom_max'=>15,
                    'zoom_min'=>10,
                    'attribution_text'=>'Lake Monroe',
                    'attribution_url'=>'https://libraries.ou.edu',
                ],
                [
                    'file'=>'VoCo.jpeg',
                    'bounds'=>[
                        'south'=>28,
                        'west'=>-81,
                        'north'=>28.3,
                        'east'=>-80.8,
                    ],
                    'zoom_max'=>8,
                    'zoom_min'=>8,
                    'attribution_text'=>null,
                    'attribution_url'=>null,
                ]
            ],
            'attribution_long'=>[
                ['text'=>'stuff']
            ]
        ];
        $configFile = CompiledYamlFile::instance(Grav::instance()['locator']->findResource('user://').'/config/plugins/leaflet-tour.yaml');
        $pluginData = array_merge($configFile->content(), $plugin);
        // TODO: See if I need to do something more specific than array_merge
        $configFile->content($pluginData);
        $configFile->save();
    }
    protected function modifyDatasets() {
        // modify dataset settings
        $datasets = [
            'Wymansites'=>[
                'features'=>[
                    ['id'=>'Wymansites_4', 'popup_content'=>'words words words'],
                ],
            ],
            'Wymancamps'=>[
                'features'=>[
                    ['id'=>'Wymancamps_2', 'popup_content'=>'Wymancamps popup content for feature id 2'],
                ],
            ],
            'SceneCentroids'=>[
                'features'=>[
                    ['id'=>'SceneCentroids_0', 'hide'=>true],
                ],
            ],
            'points1'=>[
                'title'=>'Points Dataset One',
                'features'=>[
                    ['id'=>'points1_0', 'popup_content'=>null],
                    ['id'=>'points1_3', 'popup_content'=>'popup content for Point 4']
                ],
                'icon_alt'=>'points 1 icon alt',
                'icon'=>[
                    'file'=>'PlaceNames.png',
                    'width'=>20,
                    'anchor_x'=>8,
                    'anchor_y'=>9,
                    'tooltip_anchor_x'=>-5,
                    'tooltip_anchor_y'=>5,
                    'shadow'=>'Wymancamps.png',
                    'shadow_width'=>10,
                    'shadow_height'=>8,
                    'class'=>'points1-test-class'
                ]
            ],
            'points2'=>[
                'title'=>'Points Dataset Two',
                'legend_text'=>'points 2 legend text',
                'legend_alt'=>'points 2 legend alt - dataset',
                'icon_alt'=>'points 2 icon alt',
                'icon'=>[
                    'shadow_anchor_x'=>4
                ]
            ],
            'points3'=>[
                'name_prop'=>'N A M E',
                'features'=>[
                    ['id'=>'points3_0', 'popup_content'=>'points 3 0 dataset popup'],
                    ['id'=>'points3_1', 'popup_content'=>'popup content'],
                    ['id'=>'points3_2', 'popup_content'=>'more popup content'],
                    ['id'=>'points3_3', 'popup_content'=>null],
                    ['id'=>'points3_4', 'popup_content'=>'even more popup content'],
                ],
                'icon'=>[
                    'file'=>'SceneCentroids.png',
                    'height'=>16,
                    'anchor_y'=>2,
                ]
            ],
            'LineStrings'=>[
                'legend_text'=>'Line Strings Legend',
                'svg_active'=>[
                    'weight'=>3,
                ]
            ],
            'MultiLineStrings'=>[
                'svg'=>[
                    'stroke'=>false,
                    'fillColor'=>'#ffffff'
                ]
            ],
            'MultiPolygons'=>[
                'svg'=>[
                    'stroke'=>true,
                    'color'=>'#445566',
                    'weight'=>3,
                    'opacity'=>1,
                    'fill'=>true,
                    'fillColor'=>null,
                    'fillOpacity'=>0.2
                ],
                'svg_active'=>[
                    'stroke'=>true,
                    'weight'=>4,
                    'fillColor'=>'#334455',
                    'color'=>null,
                    'opacity'=>null,
                    'fill'=>null,
                    'fillOpacity'=>null,
                ]
            ]
        ];
        // loop through datasets
        foreach ($datasets as $id=>$data) {
            // get dataset
            $dataset = Dataset::getDatasets()[$id.'.json'];
            // update (automatically saves)
            $dataset->updateDataset(new Data($data), $dataset->getDatasetRoute());
        }
    }
    protected function setVars() {
        // datasets
        $datasets = Dataset::getDatasets();
        $this->wymansites = $datasets['Wymansites.json'];
        $this->wymancamps = $datasets['Wymancamps.json'];
        $this->sceneCentroids = $datasets['SceneCentroids.json'];
        $this->loci = $datasets['Loci.json'];
        $this->water = $datasets['water.json'];
        $this->points1 = $datasets['points1.json'];
        $this->points2 = $datasets['points2.json'];
        $this->points3 = $datasets['points3.json'];
        $this->polygons = $datasets['Polygons.json'];
        $this->multiPoints = $datasets['Multi-Points.json'];
        $this->lineStrings = $datasets['LineStrings.json'];
        $this->multiLineStrings = $datasets['MultiLineStrings.json'];
        $this->multiPolygons = $datasets['MultiPolygons.json'];
        // tours
        $config = new Data(Grav::instance()['config']->get('plugins.leaflet-tour'));
        // technically Utils::getPageRoute (with the last / removed) is only necessary for any tours that have folder numeric prefix enabled
        $pages = Grav::instance()['pages']->instances();
        $this->tour0 = new Tour($pages[substr(Utils::getPageRoute(['tour-0']),0,-1)], $config);
        $this->tour1 = new Tour($pages[substr(Utils::getPageRoute(['tour-1']),0,-1)], $config);
        $this->tour2 = new Tour($pages[substr(Utils::getPageRoute(['tour-2']),0,-1)], $config);
        $this->tour3 = new Tour($pages[substr(Utils::getPageRoute(['tour-3']),0,-1)], $config);
        $this->tour4 = new Tour($pages[substr(Utils::getPageRoute(['tour-4']),0,-1)], $config);
        $this->tour5 = new Tour($pages[substr(Utils::getPageRoute(['tour-5']),0,-1)], $config);
        // views
        $views = array_column($this->tour5->getViews(), null);
        $this->view1 = $views[0];
        $this->view2 = $views[1];
        $this->view3 = $views[2];
        $this->view4 = $views[3];
        $this->view5 = $views[4];
        $this->view6 = $views[5];
    }

    /**
     * So I can have a clean slate for the next volley of tests.
     */
    protected function teardown() {
    }

    // TODO
    /*protected function testDatasetUpdate() {
        // check that Polygons_1 fruit is apple and Polygons_2 does not have fruit property
        $this->assertEquals($this->polygons->getFeatures()['Polygons_1']->asJson()['properties']['fruit'], 'apple');
        $this->assertEmpty($this->polygons->getFeatures()['Polygons_2']->asJson()['properties']['fruit']);
        // make modifications
        $this->points3->updateDataset(new Data(['features'=>[['id'=>'points3_3', 'coordinates'=>[11, 42]]]]), $this->points3->getDatasetRoute());
        $this->multiPolygons->updateDataset(new Data([
            'features'=>[
                ['id'=>'MultiPolygons_3', 'coordinates'=>[[[[1,5],[2,3],[3,4],[1,5]]]]]
            ]
        ]), $this->multiPolygons->getDatasetRoute());
        // TODO: test coordinates update
        // TODO: title updates (and changes dataset name)
        // TODO: name property updates (and feature names update with it)
        // TODO: name property only works if it is a valid property
        // TODO: datasetFileRoute updates - try relocating dataset file
        // TODO: undo update
    }*/

    /**
     * General tests for the output of the tour functions, excluding functions relating to the views (getViews, getViewPopups) and shortcodes (hasPopup)
     */
    protected function testTour() {
        $this->name = "Results for Tours";

        $this->startHeader('Datasets');
        // datasets - [id => [legendAltText, iconOptions, pathOptions, pathActiveOptions]]
        // tour with no datasets (tour 1)
        $this->assertEmpty($this->tour1->getDatasets());
        // legendAltText set by dataset (tour 2)
        $t2_datasets = $this->tour2->getDatasets();
        $this->assertEquals($t2_datasets['points2.json']['legendAltText'], 'points 2 legend alt - dataset');
        // legendAltText set by tour
        $this->assertEquals($t2_datasets['points1.json']['legendAltText'], 'points 1 legend alt - tour');
        // icon and path options will be tested by Dataset test
        $this->endHeader();

        $this->startHeader("Basemaps");
        // basemaps - [file => [file, bounds, minZoom, maxZoom]]
        // no basemaps (tour 1)
        $this->assertEmpty($this->tour1->getBasemaps());
        // basemap added to tour (tour 2 - 1 basemap)
        $this->assertSize($this->tour2->getBasemaps(), 1);
        // basemap added to view (tour 3 - 1 basemap)
        $this->assertSize($this->tour3->getBasemaps(), 1);
        // basemap added to tour and view (tour 4 - 2 each with 1 overlap)
        $t4_basemaps = $this->tour4->getBasemaps();
        $this->assertSize($t4_basemaps, 3);
        // basemap bounds (tour 4 - Map1873)
        $this->assertEquals($t4_basemaps['Map1873.png']['bounds'][0][0], 27.474);
        // basemap minZoom (tour 4 - Small Map)
        $this->assertEquals($t4_basemaps['Small Map.jpg']['minZoom'], 13);
        // basemap maxZoom (tour 4 - Glot18)
        $this->assertEquals($t4_basemaps['Glot18.jpg']['maxZoom'], 16);
        $this->endHeader();

        $this->startHeader('Legend');
        // legend [[dataSource, legendText, iconFile, iconWidth, iconHeight, iconAltText]]
        // tour with datasets and features but no legend (tour 3)
        $this->assertEmpty($this->tour3->getLegend());
        // tour with datasets, legend info, but no features (tour 2)
        $this->assertEmpty($this->tour2->getLegend());
        // tour with >1 datasets, two legends (tour 4)
        $this->assertSize($this->tour4->getLegend(), 2);
        // tour with >=2 datasets, one has legend in dataset, one from tour (tour 5)
        $this->assertSize($this->tour5->getLegend(), 2);
        // dataSource
        $this->assertEquals($this->tour5->getLegend()[0]['dataSource'], 'points2.json');
        // legend/icon settings will be tested by Dataset test
        $this->endHeader();

        $this->startHeader('Features');
        // features - [id => [type, properties (name, id, dataSource, hasPopup) geometry (type, coordinates)]]
        // tour with no datasets (tour 1)
        $this->assertEmpty($this->tour1->getFeatures());
        // tour with datasets but no features (tour 2)
        $this->assertEmpty($this->tour2->getFeatures());
        // tour with one dataset show all and one dataset with three features (tour 0)
        $this->assertSize($this->tour0->getFeatures(), 6);
        // tour with feature that does not have a value for the name property (tour 0)
        $this->assertEmpty($this->tour0->getFeatures()['MultiPolygons_0']);
        // check name
        $point = $this->tour0->getFeatures()['points1_3'];
        $this->assertEquals($point['properties']['name'], 'Point 4');
        // check dataSource
        $this->assertEquals($point['properties']['dataSource'], 'points1.json');
        // check hasPopup
        $this->assertTrue($point['properties']['hasPopup']);
        // check geometry type
        $this->assertEquals($point['geometry']['type'], 'Point');
        // check coordinates
        $this->assertEquals($point['geometry']['coordinates'][1], 90);
        $this->endHeader();

        $this->startHeader('Popups');
        // popups - [id => [id, name, popup]]
        // no features with popups (tour 3)
        $this->assertEmpty($this->tour3->getPopups());
        // features with popups (tour 5 - 5 popups from dataset (1 removed), 4 popups from tour (1 overlaps))
        // correct: 6
        $t5_popups = $this->tour5->getPopups();
        $this->assertSize($t5_popups, 6);
        // popup added by tour
        $this->assertNotEmpty($t5_popups['Polygons_0']);
        // popup removed by tour
        $this->assertEmpty($t5_popups['points3_1']);
        // overwritten feature with popup from dataset
        $this->assertNotEmpty($t5_popups['points3_0']);
        // feature with popup from dataset
        $this->assertNotEmpty($t5_popups['points3_2']);
        // popup added by tour (also remove popup checked)
        $this->assertNotEmpty($t5_popups['points3_3']);
        $this->endHeader();

        $this->startHeader('Attribution');
        // attribution - [[name, url]]
        // note: 3 attribution pieces added by plugin config, 1 from plugin tileserver
        // defaults (tour 1)
        $this->assertSize($this->tour1->getAttribution(), 4);
        // defaults + 1 from basemap (tour 2)
        $this->assertSize($this->tour2->getAttribution(), 5);
        // defaults + 1 from basemap + 2 from tour (tour 3)
        $this->assertSize($this->tour3->getAttribution(), 7);
        // defaults - 1 because tile server selected (tour 0)
        $this->assertSize($this->tour0->getAttribution(), 3);
        // attribution with text - no url
        $this->assertNotEmpty(array_column($this->tour4->getAttribution(), null, 'name')['Attribution Item']);
        // attribution with url - no text
        $this->assertEmpty(array_column($this->tour4->getAttribution(), null, 'url')['myfakeurl.com']);
        // attribution url when overwriting with no url (tour 1)
        $t1_attribution = array_column($this->tour1->getAttribution(), null, 'name');
        $this->assertEmpty($t1_attribution['qgis2web']['url']);
        // attribution url when overwriting with url
        $this->assertEquals($t1_attribution['QGIS']['url'], 'fakeurl.com');
        $this->endHeader();

        $this->startHeader('Extra Attribution');
        // extra attribution - [string]
        // attribution added in plugin config (tour 1)
        $this->assertSize($this->tour1->getExtraAttribution(), 1);
        // attribution added in tour config (tour 2)
        $this->assertSize($this->tour2->getExtraAttribution(), 2);
        // attribution added from tile server and tour config (tour 0)
        $this->assertSize($this->tour0->getExtraAttribution(), 3);
        $this->endHeader();

        $this->startHeader('Options');
        // getOptions - [maxZoom, minZoom, removeTileServer, tourMaps, wideCol, showMapLocationInUrl, tileServer, stamenTileServer, bounds, maxBounds]
        // maxZoom - set in header (tour 1)
        $this->assertEquals($this->tour1->getOptions()['maxZoom'], 15);
        // minZoom - default (tour 1)
        $this->assertEquals($this->tour1->getOptions()['minZoom'], 8);
        // removeTileServer - default (tour 0)
        $this->assertTrue($this->tour0->getOptions()['removeTileServer']);
        // tourMaps - 2 added (tour 4)
        $this->assertSize($this->tour4->getOptions()['tourMaps'], 2);
        // wideCol - set to true (tour 3)
        $this->assertTrue($this->tour3->getOptions()['wideCol']);
        // showMapLocationInUrl - set to false (tour 3)
        $this->assertFalse($this->tour3->getOptions()['showMapLocationInUrl']);
        // tileServer - default (tour 2)
        $this->assertNotEmpty($this->tour2->getOptions()['tileServer']);
        $this->assertEmpty($this->tour2->getOptions()['stamenTileServer']);
        // stamen tile server (tour 0)
        $this->assertEmpty($this->tour0->getOptions()['tileServer']);
        $this->assertNotEmpty($this->tour0->getOptions()['stamenTileServer']);
        // maxBounds - valid bounds (tour 1)
        $this->assertNotEmpty($this->tour1->getOptions()['maxBounds']);
        // maxBounds - invalid bounds - only three values provided (tour 2)
        $this->assertEmpty($this->tour2->getOptions()['maxBounds']);
        // bounds - feature set as center (tour 0)
        $this->assertEquals($this->tour0->getOptions()['bounds'][0][0], -8);
        // bounds - coordinates set as center, invalid bounds - only three values (tour 1)
        $this->assertEquals($this->tour1->getOptions()['bounds'][0][0], 50.111);
        // bounds - feature and coordinates set as center, invalid bounds - lat too low (tour 4 - points2_1)
        $this->assertEquals($this->tour4->getOptions()['bounds'][0][0], 7.5);
        // bounds - feature, coordinates, and bounds set as center (tour 5)
        $this->assertEquals($this->tour5->getOptions()['bounds'][0][1], 123.456);
        // bounds - invalid feature set (tour 2)
        $this->assertEmpty($this->tour2->getOptions()['bounds']);
        // bounds - coordinates set, no distance (tour 3)
        $this->assertEmpty($this->tour3->getOptions()['bounds']);
        $this->endHeader();
    }

    /**
     * General tests for the output of the tour views (Tour->getViews and Tour->getViewPopups).
     */
    protected function testViews() {
        $this->name = "Results for Views";
        // [viewId => [basemaps, onlyShowViewFeatures, removeTileServer, noTourBasemaps, bounds, features]]

        // no basemaps
        $this->assertEmpty($this->view1['basemaps']);
        // two basemaps
        $this->assertSize($this->view3['basemaps'], 2);
        // no features
        $this->assertEmpty($this->view1['features']);
        // five features
        $this->assertSize($this->view3['features'], 5);

        $this->startHeader('Misc Options');
        // only show view features (not set) (tour says false)
        $this->assertFalse($this->view2['onlyShowViewFeatures']);
        // only show view features (true) (but no features so should be false)
        $this->assertFalse($this->view1['onlyShowViewFeatures']);
        // only show view features (true)
        $this->assertTrue($this->view3['onlyShowViewFeatures']);
        // remove default basemap (not set) (tour says true)
        $this->assertTrue($this->view1['removeTileServer']);
        // remove default basemap (false)
        $this->assertFalse($this->view3['removeTileServer']);
        // no tour basemaps (true)
        $this->assertTrue($this->view2['noTourBasemaps']);
        $this->endHeader();

        $this->startHeader('Bounds');
        // coordinates set but no distance
        $this->assertEmpty($this->view1['bounds']);
        // coordinates and feature set (points3_0)
        $this->assertEquals($this->view3['bounds'][0][1], 170.11); // west
        // distance causing wraparound
        $this->assertEquals($this->view3['bounds'][1][1], -171.89); // east
        // feature set - only in hidden features for tour (points2_0)
        $this->assertNotEmpty($this->view4['bounds']);
        // feature set - not in tour features
        $this->assertEmpty($this->view5['bounds']);
        // feature set - not a point
        $this->assertEmpty($this->view6['bounds']);
        // bounds set
        $this->assertEquals($this->view2['bounds'][0][0], 21.55);
        $this->endHeader();

        $this->startHeader('Popups');
        $keys = array_keys($this->tour5->getViews());
        // view with no features (view 1)
        $this->assertEmpty($this->tour5->getViewPopups($keys[0]));
        // view with features, no popups (view 4)
        $this->assertEmpty($this->tour5->getViewPopups($keys[3]));
        // view with some popups (view 3 - 3 popups)
        $this->assertSize($this->tour5->getViewPopups($keys[2]), 3);
        // view with some popups, some of which are not for features included in the tour (view 5 - 2 valid popups)
        $this->assertSize($this->tour5->getViewPopups($keys[4]), 2);
        $this->endHeader();
    }

    /**
     * Tests function(s) called by shortcodes (Tour::hasPopup).
     */
    protected function testShortcodeFunctions() {
        $this->name = "Results for Shortcode Functions";

        $this->startHeader('Tour - hasPopup');
        // feature that has a popup, no tourFeatures (Wymancamps_2)
        $this->assertTrue(Tour::hasPopup($this->wymancamps->getFeatures()['Wymancamps_2'], []));
        // feature with popup set in tourFeatures (Polygons_0, tour5)
        $features = MarkdownFile::instance(Utils::getPageRoute(['tour-5']).'/tour.md')->header()['features'];
        $this->assertTrue(Tour::hasPopup($this->polygons->getFeatures()['Polygons_0'], $features));
        // feature with remove_popup (points3_1, tour5)
        $this->assertFalse(Tour::hasPopup($this->points3->getFeatures()['points3_1'], $features));
        // feature with remove_popup and popup_content (points3_3, tour5)
        $this->assertTrue(Tour::hasPopup($this->points3->getFeatures()['points3_3'], $features));
        $this->endHeader();
    }

    /**
     * Tests a few miscellaneous functions provided in leaflet-tour.php.
     * Functions not tested include event handlers and functions that must be called from specific configuration pages. These will have to be tested manually.
     */
    protected function testLeafletTourPlugin() {
        $this->name = "Results for Leaflet Tour Plugin";

        // get dataset files - array of 13 datasets
        $datasets = LeafletTourPlugin::getDatasetFiles();
        $this->assertSize($datasets, 13);
        // dataset Multi-Points exists
        $this->assertNotEmpty($datasets['Multi-Points.json']);
        // dataset test1 does not exist
        $this->assertEmpty($datasets['test1.json']);
        // get basemaps - array of 5
        $basemaps = LeafletTourPlugin::getBasemaps();
        $this->assertSize($basemaps, 5);
        // LakeMonroe exists
        $this->assertNotEmpty($basemaps['LakeMonroe.jpg']);
        // VoCo exists
        $this->assertNotEmpty($basemaps['VoCo.jpeg']);
        // Map1873_copy does not exist
        $this->assertEmpty($basemaps['Map1873_copy.png']);
        // get tile servers - array of 3 (+1 for option: none)
        $servers = LeafletTourPlugin::getTileServers();
        $this->assertSize($servers, 4);
        // tile server array - select
        $this->assertEquals($servers['stamenWatercolor'], 'Stamen Watercolor');
        // get specific tile server - success
        $this->assertEquals(LeafletTourPlugin::getTileServers('stamenTerrain')['name'], 'terrain');
    }

    /**
     * Tests for the Dataset class to make sure that values have been set and updated correctly. The big function, mergeTourData, is tested separately.
     */
    protected function testDataset() {
        $this->name = "Results for Datasets";

        // get dataset list - 13
        $this->assertSize(Dataset::getDatasetList(), 13);
        // get datasets - 13
        $this->assertSize(Dataset::getDatasets(), 13);

        $this->startHeader('Dataset Name');
        // original name (points3) - Points Dataset Three
        $this->assertEquals($this->points3->getName(), 'Points Dataset Three');
        // default name (Polygons) - Polygons
        $this->assertEquals($this->polygons->getName(), 'Polygons');
        // modified (points1) - Points Dataset One
        $this->assertEquals($this->points1->getName(), 'Points Dataset One');
        // modified from default (points2) - Points Dataset Two
        $this->assertEquals($this->points2->getName(), 'Points Dataset Two');
        $this->endHeader();

        $this->startHeader('Name Property');
        // property with spaces (points3) - "N A M E"
        $this->assertEquals($this->points3->getNameProperty(), 'N A M E');
        // property "name" where "x_name" or "name_x" comes before and the other after (MultiPoints) - "Name"
        $this->assertEquals($this->multiPoints->getNameProperty(), 'Name');
        // property "x_name" or "name_x" where "x_name" or "name_x" comes after (points1) - "FeatureName"
        $this->assertEquals($this->points1->getNameProperty(), 'FeatureName');
        // property with no "name" in list (points2)
        $this->assertEquals($this->points2->getNameProperty(), 'Feature');
        // property where the first feature's name property has value of false (MultiPolygons)
        $this->assertEquals($this->multiPolygons->getNameProperty(), 'name');
        $this->endHeader();

        $this->startHeader('Properties');
        // mis-matched properties (polygons)
        // TODO: Ideally recognize all properties and return 6 instead of 4
        $this->assertSize($this->polygons->getProperties(), 4);
        // standard (points1)
        $this->assertSize($this->points1->getProperties(), 4);
        // properties where one includes spaces (points3) N A M E
        $this->assertTrue(in_array("N A M E", $this->points3->getProperties()));
        // properties where one includes special symbols (LineStrings) specialProp@&%^()!--
        $this->assertTrue(in_array("specialProp@&%^()!--", $this->lineStrings->getProperties()));
        $this->endHeader();

        $this->startHeader('Features');
        // feature type - points1
        $this->assertEquals($this->points1->getFeatureType(), 'Point');
        // feature type - Loci
        $this->assertEquals($this->loci->getFeatureType(), 'MultiPolygon');
        // other things will be tested when testing Feature class
        $this->endHeader();

        $this->startHeader('Legend, Icon, Path');
        // legend text
        $this->assertEquals($this->points2->asYaml()['legend_text'], 'points 2 legend text');
        // legend alt text
        $this->assertEquals($this->points2->asYaml()['legend_alt'], 'points 2 legend alt - dataset');
        // icon alt text
        $this->assertEquals($this->points2->asYaml()['icon_alt'], 'points 2 icon alt');
        $this->assertEmpty($this->points3->asYaml()['icon_alt']);
        // icon options - height
        $this->assertEquals($this->points3->asYaml()['icon']['height'], 16);
        // path defaults (pathActiveOptions)
        $this->assertEquals($this->polygons->asYaml()['svg_active']['weight'], 5);
        $this->assertEquals($this->polygons->asYaml()['svg_active']['fillOpacity'], 0.4);
        // path defaults - don't overwrite existing pathActiveOptions (weight, fillOpacity)
        $this->assertEquals($this->lineStrings->asYaml()['svg_active']['weight'], 3);
        $this->assertEmpty($this->lineStrings->asYaml()['svg_active']['fillOpacity']);
        // path - stroke
        $this->assertFalse($this->multiLineStrings->asYaml()['svg']['stroke']);
        // path - fillColor
        $this->assertEquals($this->multiLineStrings->asYaml()['svg']['fillColor'], '#ffffff');
        $this->endHeader();
    }

    /**
     * Tests for the Dataset->mergeTourData function.
     */
    protected function testMergeTourData() {
        $this->name = "Results for MergeTourData";

        $t0_points1 = $this->tour0->getDatasets()['points1.json'];
        $t0_points2 = $this->tour0->getDatasets()['points2.json'];
        $t2_points1 = $this->tour2->getDatasets()['points1.json'];
        $t3_points1 = $this->tour3->getDatasets()['points1.json'];
        $t4_points1 = $this->tour4->getDatasets()['points1.json'];
        $t4_points2 = $this->tour4->getDatasets()['points2.json'];
        $t5_points2 = $this->tour5->getDatasets()['points2.json'];
        $t5_points3 = $this->tour5->getDatasets()['points3.json'];

        $this->startHeader('Icon - General');
        // use defaults, icon file not set (tour 0, points1)
        $this->assertEquals($t0_points1['iconOptions']['iconUrl'], 'user/plugins/leaflet-tour/images/marker.png');
        // use defaults, icon file set, icon size not set (tour 3, points1) - width
        $this->assertEquals($t3_points1['iconOptions']['iconSize'][0], 14);
        // icon width (tour - tour 4, points1)
        $this->assertEquals($t4_points1['iconOptions']['iconSize'][0], 18);
        // tooltip anchor (dataset - tour 4, points1)
        $this->assertEquals($t4_points1['iconOptions']['tooltipAnchor'][0], -5);
        // shadow width (dataset - tour 4, points1)
        $this->assertEquals($t4_points1['iconOptions']['shadowSize'][0], 10);
        // shadow height (dataset and tour - tour 4, points1)
        $this->assertEquals($t4_points1['iconOptions']['shadowSize'][1], 2);
        // class (dataset - tour 4, points1)
        $this->assertEquals($t4_points1['iconOptions']['className'], 'leaflet-marker points1-test-class');
        // retina (default - tour 0, points1)
        $this->assertNotEmpty($t0_points1['iconOptions']['iconRetinaUrl']);
        $this->endHeader();

        $this->startHeader('Marker Fallbacks');
        // icon file must be set for all
        // icon file set in tour (height) (tour 4, points2)
        $this->assertEquals($t4_points2['iconOptions']['iconSize'][1], 14);
        // icon file set in dataset (not tour) (width) (tour 5, points3)
        $this->assertEquals($t5_points3['iconOptions']['iconSize'][0], 14);
        // anchor - only x (tour 4, points2)
        $this->assertEmpty($t4_points2['iconOptions']['iconAnchor']);
        // anchor - only y (tour 2, points1)
        $this->assertEmpty($t2_points1['iconOptions']['iconAnchor']);
        // anchor - x in tour, y in dataset (tour 5, points3)
        $this->assertNotEmpty($t5_points3['iconOptions']['iconAnchor']);
        // anchor - x and y in dataset (tour 4, points1)
        $this->assertNotEmpty($t4_points1['iconOptions']['iconAnchor']);
        // shadow - none (tour 5, points 3)
        $this->assertEmpty($t5_points3['iconOptions']['shadowUrl']);
        $this->endHeader();

        $this->startHeader('Default Marker Options');
        // icon file must be default for all
        // icon file in dataset, use defaults in tour (height) (tour 0, points1)
        $this->assertEquals($t0_points1['iconOptions']['iconSize'][1], 41);
        // icon file not set (width) (tour 5, points2)
        $this->assertEquals($t5_points2['iconOptions']['iconSize'][0], 25);
        // anchor - only x (tour 0, points1)
        $this->assertNotEmpty($t0_points1['iconOptions']['iconAnchor']);
        // shadow anchor - only x (tour 5, points2)
        $this->assertEmpty($t5_points2['iconOptions']['shadowAnchor']);
        // shadow (tour 5 points2)
        $this->assertNotEmpty($t5_points2['iconOptions']['shadowUrl']);
        $this->endHeader();

        $this->startHeader('Path');
        // item not set in tour or dataset (fill color) (tour 5, polygons)
        $this->assertEmpty($this->tour5->getDatasets()['Polygons.json']['pathOptions']['fillColor']);
        // (tour 3, multiPolygons)
        $pathOptions = $this->tour3->getDatasets()['MultiPolygons.json']['pathOptions'];
        $pathActiveOptions = $this->tour3->getDatasets()['MultiPolygons.json']['pathActiveOptions'];
        // svg stroke (true, true)
        $this->assertTrue($pathOptions['stroke']);
        // svg color (null, 445566)
        $this->assertEquals($pathOptions['color'], '#445566');
        // svg weight (2, 3)
        $this->assertEquals($pathOptions['weight'], 2);
        // svg opacity (null, 1)
        $this->assertEquals($pathOptions['opacity'], 1);
        // svg fill (false, true)
        $this->assertFalse($pathOptions['fill']);
        // svg fillColor (null, null)
        $this->assertEmpty($pathOptions['fillColor']);
        // svg fillOpacity (null, 0.2)
        $this->assertEquals($pathOptions['fillOpacity'], 0.2);
        // svg active stroke (true, true)
        $this->assertTrue($pathActiveOptions['stroke']);
        // svg active color ('#112233', null)
        $this->assertEquals($pathActiveOptions['color'], '#112233');
        // svg active weight (null, 4)
        $this->assertEquals($pathActiveOptions['weight'], 4);
        // svg active opacity (null, null)
        $this->assertEmpty($pathActiveOptions['opacity']);
        // svg active fill (true, null)
        $this->assertTrue($pathActiveOptions['fill']);
        // svg active fillColor (null, 334455)
        $this->assertEquals($pathActiveOptions['fillColor'], '#334455');
        // svg active fillOpacity (0.5, null)
        $this->assertEquals($pathActiveOptions['fillOpacity'], 0.5);
        $this->endHeader();

        $this->startHeader('Legend');
        $legend0 = $this->tour0->getLegend();
        $legend4 = $this->tour4->getLegend();
        // test icon when icon set only in dataset and use_defaults is true (tour 0, points1)
        $this->assertEquals($legend0[0]['iconHeight'], 41);
        // icon set in dataset (not tour) and use_defaults is false (tour 4, points1)
        $this->assertEquals($legend4[0]['iconHeight'], 14);
        // legend text in dataset (tour 4, points2)
        $this->assertEquals($legend4[1]['legendText'], 'points 2 legend text');
        // legend text in tour and dataset (no show all, features list not empty, none of this dataset's features in the feature list) (tour 0, points2) (legend count should be one - points1, not points2)
        $this->assertSize($legend0, 1);
        // legend alt text not in tour, legend text in tour (dataset has legend and legend alt) (tour 0, points2)
        $this->assertEquals($t0_points2['legendAltText'], 'tour 0 legend for points2');
        // legend alt text set (tour 5, points3)
        $this->assertEquals($t5_points3['legendAltText'], 'points 3 legend alt');
        // icon alt text (tour 4, points2)
        $this->assertEquals($legend4[1]['iconAltText'], 'points 2 icon alt');
        // TODO: icon alt text follows use_defaults
        // icon alt text set only in dataset, use defaults (tour 0, points1)
        //$this->assertEmpty($legend0[0]['iconAltText']);
        $this->endHeader();

        $this->startHeader('Features');
        // some show all, some not, no features list (tour 4 - 6+7)
        $this->assertSize($this->tour4->getFeatures(), 13);
        // no show all, no features list (tour 2) - already tested in testTour
        // tour popup and dataset popup (tour 5 - points3_4)
        $this->assertEquals($this->tour5->getPopups()['points3_4']['popup'], 'tour 5 popup for points3_4');
        // remove popup and dataset popup (tour 5 - points3_1)
        $this->assertEmpty($this->tour5->getPopups()['points3_1']);
        // remove popup and popup content (tour 5 - points3_3)
        $this->assertNotEmpty($this->tour5->getPopups()['points3_3']);
        // hidden feature does not exist (same feature as is set for coordinates for view 4 start) (tour 5, points2_0)
        $this->assertEmpty($this->tour5->getFeatures()['points2_0']);
        // legend only provided if features are actually included (tour 2)
        $this->assertEmpty($this->tour2->getLegend());
        $this->endHeader();
    }

    /**
     * General tests for features. Includes some tests to make sure the correct number of features have been generated for various datasets and some functions to make sure that feature properties are being set and updated correctly.
     */
    protected function testFeature() {
        $this->name = "Results for Feature";

        $this->startHeader('Dataset Features');
        // count of features for several different datasets
        // Wymansites - 23 (-1, name of null)
        $this->assertSize($this->wymansites->getFeatures(), 22);
        // Loci - 7
        $this->assertSize($this->loci->getFeatures(), 7);
        // points1 - 7
        $this->assertSize($this->points1->getFeatures(), 7);
        // Multi Points - 4
        $this->assertSize($this->multiPoints->getFeatures(), 4);
        // count of features for dataset that includes feature without a valid name (MultiLineStrings) (only 2 with valid names)
        $this->assertSize($this->multiLineStrings->getFeatures(), 2);
        // TODO: Allow non-existing names on upload
        // update custom name for feature without a valid name MultiLineStrings_2 - 'MultiLineString 39 - new name'
        //$yaml = $this->multiLineStrings->asYaml();
        //$yaml['features'][] = ['id'=>'MultiLineStrings_2', 'custom_name'=>'MultiLineString 39 - new name'];
        //$this->multiLineStrings->updateDataset(new Data($yaml), $this->multiLineStrings->getDatasetRoute());
        // check name
        //$this->assertNotEmpty($this->multiLineStrings->getFeatures()['MultiLineStrings_2']);
        // check count (3)
        //$this->assertSize($this->multiLineStrings->getFeatures(), 3);
        // update custom name again to be null
        $features = [];
        foreach ($this->multiLineStrings->getFeatures() as $id=>$feature) {
            if ($id !== 'MultiLineStrings_2') $features[] = $feature->asYaml();
        }
        $features[] = ['id'=>'MultiLineStrings_2', 'custom_name'=>''];
        $yaml['features'] = $features;
        $this->multiLineStrings->updateDataset(new Data($yaml), $this->multiLineStrings->getDatasetRoute());
        // check name
        $this->assertEmpty($this->multiLineStrings->getFeatures()['MultiLineStrings_2']);
        // check count (2)
        $this->assertSize($this->multiLineStrings->getFeatures(), 2);
        $this->endHeader();

        $this->startHeader('Name');
        // standard string (points1)
        $this->assertEquals($this->points1->getFeatures()['points1_0']->getName(), 'Point 1');
        // prop is bool false (MultiPolygons)
        $this->assertEmpty($this->multiPolygons->getFeatures()['MultiPolygons_0']);
        // prop is bool true (LineStrings)
        $this->assertTrue($this->lineStrings->getFeatures()['LineStrings_2']->getName());
        // prop is null/doesn't exist (MultiLineStrings)
        $this->assertEmpty($this->multiLineStrings->getFeatures()['MultiLineStrings_1']);
        // custom name
        $this->endHeader();

        // TODO: test updateDataset()
        /*$this->startHeader('Set and Update');
        // popup_content set from dataset (points3_0) - 'points 3 0 dataset popup'
        $this->assertEquals($this->points3->getFeatures()['points3_0']->getPopup(), 'points 3 0 dataset popup');
        // hide feature set from dataset (SceneCentroids_0)
        $this->assertTrue($this->sceneCentroids->getFeatures()['SceneCentroids_0']->asYaml()['hide']);
        // point coordinates updated (points3_3 - lat - 22 to 42)
        $this->assertEquals($this->points3->getFeatures()['points3_3']->asGeoJson()['geometry']['coordinates'][1], 42);
        // MultiPolygon coordinates updated (MultiPolygons_3 - [[[[1,2],[2,3],[3,4],[1,2]]]] to [[[[1,5],[2,3],[3,4],[1,5]]]])
        $this->assertEquals($this->multiPolygons->getFeatures()['MultiPolygons_3']->asGeoJson()['geometry']['coordinates'][0][0][0][0][1], 5);
        // property values updated (Polygons_1 - fruit to banana)
        $this->assertEquals($this->polygons->getFeatures()['Polygons_1']->asJson()['properties']['fruit'], 'banana');
        // property values updated (Polygons_2 - add fruit - lime)
        $this->assertEquals($this->polygons->getFeatures()['Polygons_2']->asJson()['properties']['fruit'], 'lime');
        $this->endHeader();*/
    }

    /**
     * Tests for the Utils class, which primarily provides functions for checking the validity of coordinates.
     */
    protected function testUtils() {
        $this->name = "Results for Utils";

        $this->startHeader('Point');
        // correct long and lat
        $this->assertTrue(Utils::isValidPoint([-96.2, 45.3]));
        // lat and long switched
        $this->assertFalse(Utils::isValidPoint([45.3, -96.2]));
        // switched but with reverse flag
        $this->assertTrue(Utils::isValidPoint([45.3, -96.2], true));
        // edge cases -90/90, -180/180
        $this->assertTrue(Utils::isValidPoint([-180, 90]));
        // incorrect edge cases -90.000001
        $this->assertFalse(Utils::isValidPoint([76, 90.00001]));
        // array with only one number
        $this->assertFalse(Utils::isValidPoint([87]));
        // array with three numbers
        $this->assertFalse(Utils::isValidPoint([87, 64, 12]));
        // array with a non-numeric value
        $this->assertFalse(Utils::isValidPoint([87, 'not a number']));
        // passing number - not an array
        $this->assertFalse(Utils::isValidPoint(87));
        // point with really long decimal values
        $this->assertTrue(Utils::isValidPoint([95.32952385683943, 10.328943899523]));
        $this->endHeader();

        $this->startHeader('MultiPoint');
        // valid MultiPoint with two points
        $this->assertTrue(Utils::isValidMultiPoint([[-96.2, 45.3],[-180, 90]]));
        // empty array
        $this->assertFalse(Utils::isValidMultiPoint([]));
        // array of arrays (array of MultiPoints)
        $this->assertFalse(Utils::isValidMultiPoint([[[-96.2, 45.3],[-180, 90]],[[-96.2, 45.3],[-180, 90]]]));
        // Point (would be valid if it were encased in an array)
        $this->assertFalse(Utils::isValidMultiPoint([-96.2, 45.3]));
        $this->endHeader();

        $this->startHeader('Polygon'); // todo
        // linear ring - an array of valid points where the first and last points match
        $linearRing = [[180, 80], [120, -80], [-89, 0], [0, 22.3453], [180, 80]];
        // pass linear ring as polygon
        $this->assertFalse(Utils::isValidPolygon($linearRing));
        // linear ring inside of an array
        $this->assertTrue(Utils::isValidPolygon([$linearRing]));
        // array of linear rings
        $this->assertTrue(Utils::isValidPolygon([$linearRing, $linearRing, $linearRing]));
        // array of valid polygons (array of array of linear rings)
        $this->assertFalse(Utils::isValidPolygon([[$linearRing, $linearRing, $linearRing]]));
        // point
        $this->assertFalse(Utils::isValidPolygon([67, 80]));
        // input with fewer than three points (technically a minimum of four is required, but I hope to allow for three as long as all three are unique - that is, turning the array into a linear ring (where the starting point matches the end) would bump it up to four points)
        $this->assertFalse(Utils::isValidPolygon([[[180, 80], [120, -80]]]));
        // polygon with an invalid point - latitude 91
        $this->assertFalse(Utils::isValidPolygon([[[180, 80], [120, -80], [-89, 91], [0, 22.3453]]]));
        // start and end don't match - will change this to assertTrue once the feature mentioned just above is implemented
        $this->assertFalse(Utils::isValidPolygon([[[180, 80], [120, -80], [-89, 0], [0, 22.3453]]]));
        $this->endHeader();

        $this->startHeader('MultiPolygon');
        $polygon = [$linearRing];
        $hugeMultiPolygon = [
            [[[-81.638434886224047,29.245855658431633],[-81.638313526589897,29.245721174930871],[-81.638303875754076,29.245697653601848],[-81.638295902030137,29.245673512115424],[-81.638289644863093,29.245648869887187],[-81.638285135206374,29.245623848804822],[-81.638282395357223,29.24559857263375],[-81.638281438874387,29.245573166399478],[-81.638282270490876,29.245547755771437],[-81.638284886088499,29.245522466440885],[-81.638289272734241,29.245497423498815],[-81.63829540872392,29.245472750817495],[-81.638303263709517,29.245448570437471],[-81.638312798841056,29.245425001964573],[-81.638323966944867,29.245402161978742],[-81.638336712789155,29.24538016345377],[-81.63835097332047,29.245359115204334],[-81.638366678005681,29.245339121343928],[-81.638383749159388,29.245320280768279],[-81.638402102344102,29.2453026866724],[-81.638421646777701,29.245286426082199],[-81.638442285780897,29.245271579428842],[-81.638463917268368,29.245258220151296],[-81.638486434243518,29.24524641432799],[-81.638509725325918,29.245236220356198],[-81.638533675310185,29.245227688659281],[-81.638558165727943,29.245220861439087],[-81.638673882046291,29.245159710760859],[-81.63879049023538,29.245100278428705],[-81.638907964781524,29.245042577450473],[-81.639026279975937,29.244986620448795],[-81.6391454099344,29.244932419668199],[-81.639265328589985,29.24487998696965],[-81.639386009698924,29.244829333824459],[-81.639468963861674,29.244801155633191],[-81.639552729009822,29.244775488468346],[-81.639637229156492,29.24475235561567],[-81.63972238764471,29.244731778060665],[-81.639808127214721,29.244713774468888],[-81.639894370091298,29.244698361175956],[-81.639981038035089,29.244685552162206],[-81.639990500683098,29.24469097709515],[-81.639999583764805,29.244697016071321],[-81.640008247497448,29.244703642640157],[-81.640016453922712,29.244710827773812],[-81.640024167103533,29.244718540001482],[-81.640031353254003,29.244726745541808],[-81.640037980891051,29.244735408451557],[-81.640044020990885,29.244744490787522],[-81.640049447090846,29.244753952764764],[-81.640054235431293,29.24476375294034],[-81.640058365032004,29.24477384838519],[-81.640061817804948,29.244784194880591],[-81.640064578627047,29.244794747106422],[-81.64006663540566,29.244805458841252],[-81.64006797913315,29.24481628316607],[-81.64006860391963,29.244827172668007],[-81.640068507032979,29.244838079647703],[-81.640067688898839,29.244848956332131],[-81.640066153093343,29.24485975507832],[-81.640063906350392,29.244870428585269],[-81.640060958507078,29.244880930101314],[-81.640057322478228,29.244891213626939],[-81.640053014187274,29.244901234117602],[-81.64004805250805,29.244910947683817],[-81.640042459171312,29.244920311775338],[-81.640036258685768,29.24492928537801],[-81.640029478198258,29.244937829184142],[-81.639977163074406,29.244976436273216],[-81.639924500749686,29.245014568404713],[-81.639871495546672,29.245052222457158],[-81.639818151790664,29.245089395346437],[-81.63976447385815,29.245126084027561],[-81.639710466147704,29.245162285499216],[-81.639656133071639,29.245197996796318],[-81.639615020545108,29.245236076288219],[-81.639576319038866,29.245276603820905],[-81.63954017405193,29.245319427029241],[-81.639506721461643,29.245364384918499],[-81.639476087040208,29.245411308470914],[-81.639448385959923,29.245460021277776],[-81.639423722357861,29.245510340203204],[-81.639357446078847,29.245577700385187],[-81.639287479576979,29.245641219102712],[-81.639214045160756,29.245700694526018],[-81.63913737617257,29.24575593767349],[-81.639057716218758,29.245806773009921],[-81.638975318423817,29.245853039009493],[-81.638890444604527,29.245894588664228],[-81.638861091892991,29.245907110716924],[-81.638830937503982,29.245917555642787],[-81.638800128224815,29.245925872597578],[-81.638768814020281,29.245932021097357],[-81.638737147317428,29.245935971213836],[-81.638705282257419,29.245937703719388],[-81.638673373952756,29.24593721017936],[-81.638641577719667,29.245934492998455],[-81.638610048332325,29.245929565400726],[-81.638578939262501,29.245922451374096],[-81.638548401941065,29.245913185545756],[-81.638518585015831,29.24590181301846],[-81.638489633617311,29.245888389150874],[-81.63846168867714,29.245872979284339],[-81.638434886224047,29.245855658431633]]]];
        // array with one polygon
        $this->assertTrue(Utils::isValidMultiPolygon([$polygon]));
        // array of polygons
        $this->assertTrue(Utils::isValidMultiPolygon([$polygon, $polygon, $polygon, $polygon]));
        // array of array of polygons
        $this->assertFalse(Utils::isValidMultiPolygon([[$polygon, $polygon, $polygon, $polygon], [$polygon]]));
        // polygon (not in an array)
        $this->assertFalse(Utils::isValidMultiPolygon($polygon));
        // array of polygons with one invalid polygon (only two points)
        $this->assertFalse(Utils::isValidMultiPolygon([$polygon, $polygon, $polygon, [[[180, 80], [120, -80]]]]));
        // really big polygon
        $this->assertTrue(Utils::isValidMultiPolygon($hugeMultiPolygon));
        $this->endHeader();

        // Note: isValidLineString is covered by isValidMultiPoint, and isValidMultiLineString should also be covered by these tests already.

        $this->startHeader('Coordinates');
        // valid point
        $this->assertTrue(Utils::areValidCoordinates([-96.2, 45.3], 'Point'));
        // valid point with bad type
        $this->assertTrue(Utils::areValidCoordinates([-96.2, 45.3], 'this is not a geojson type'));
        // invalid point
        $this->assertFalse(Utils::areValidCoordinates([76, 90.00001], 'Point'));
        // valid point but with type MultiPoint (or in other words, invalid MultiPoint)
        $this->assertFalse(Utils::areValidCoordinates([-96.2, 45.3], 'MultiPoint'));
        // valid MultiPoint
        $this->assertTrue(Utils::areValidCoordinates([[-96.2, 45.3],[-180, 90]], 'MultiPoint'));
        // valid polygon
        $this->assertTrue(Utils::areValidCoordinates($polygon, 'Polygon'));
        // invalid MultiPolygon
        $this->assertFalse(Utils::areValidCoordinates([[$polygon, $polygon, $polygon], [$polygon]], 'MultiPolygon'));
        // invalid MultiPolygon with type LineString (or invalid LineString)
        $this->assertFalse(Utils::areValidCoordinates([[$polygon, $polygon, $polygon], [$polygon]], 'LineString'));
        $this->endHeader();

        $this->startHeader('Type');
        // Point
        $this->assertEquals(Utils::setValidType('Point'), 'Point');
        // multipoint (no caps)
        $this->assertEquals(Utils::setValidType('multipoint'), 'MultiPoint');
        // lineString (first letter not capitalized)
        $this->assertEquals(Utils::setValidType('lineString'), 'LineString');
        // multilinestrings - invalid input, extra 's' at the end prevents it from being MultiLineString
        $this->assertEquals(Utils::setValidType('multilinestrings'), 'Point');
        // empty string
        $this->assertEquals(Utils::setValidType(''), 'Point');
        $this->endHeader();

        $this->startHeader('Bounds');
        // valid bounds array
        $this->assertNotEmpty(Utils::setBounds(['south' => 87, 'west' => -100, 'north'=> -0.1, 'east'=> 50]));
        // invalid bounds - south outside -90/90 range
        $this->assertEmpty(Utils::setBounds(['south'=>-100,'west'=> 87, 'north'=>-0.1,'east'=> 50]));
        // only three points - missing east
        $this->assertEmpty(Utils::setBounds(['south'=>87,'west'=> -100, 'north'=>-0.1]));
        // not an array
        $this->assertEmpty(Utils::setBounds('87'));
        // array without the necessary keys
        $this->assertEmpty(Utils::setBounds([87, -100, -0.1, 50]));
        $this->endHeader();

        $this->startHeader('Page Route');
        // modular subpage of top level page (top level page has folder numeric prefix, but provided input does not)
        $route = Utils::getPageRoute(['tour-0', '_view-0']);
        $this->assertTrue(File::instance($route.'view.md')->exists());
        // top level page with folder numeric prefix
        $route = Utils::getPageRoute(['02.tour-2']);
        $this->assertTrue(File::instance($route.'tour.md')->exists());
        // keys array starting with 'pages'
        $route = Utils::getPageRoute(['pages', 'tour-1']);
        $this->assertFalse(File::instance($route.'tour.md')->exists());
        // hidden subpage of top level folder (hidden and non-routable "page")
        $route = Utils::getPageRoute(['modules', 'footer']);
        $this->assertTrue(File::instance($route.'default.md')->exists());
        // keys array with empty string
        $route = Utils::getPageRoute(['']);
        $this->assertFalse(File::instance($route.'default.md')->exists());
        // keys array ending with the name of the file
        $route = Utils::getPageRoute(['tour-1', '_view-1', 'view.md']);
        $this->assertFalse(File::instance($route)->exists());
        $this->assertFalse(File::instance($route.'view.md')->exists());
        $this->endHeader();
    }
}
?>