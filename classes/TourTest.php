<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use Grav\Common\Data\Data;

class TourTest extends Test {

    protected $tour1;
    protected $tour2;
    protected $tour3;
    protected $tour4;
    protected $tour5;

    protected function setup() {
        parent::setup();
        $this->testHeader = 'Results for Tour Test';
    }

    public static function getResults(bool $showSuccess = false, $showPrint = true, $test = null): string
    {
        return self::getTestResults(new TourTest(), $showSuccess, $showPrint);
    }

    // tests

    function testSetup() {
        // update datasets to meet requirements
        $points1Update = new Data([
            'title'=>'Points One',
            'legend_text'=>null,
            'features'=>[
                ['id'=>'points1_0', 'coordinates'=>[12.3, 23.45]],
            ],
        ]);
        $points1 = Dataset::getDatasets()['points1.json'];
        $points1->updateDataset($points1Update, $points1->asJson()['datasetFileRoute']);
        $points2Update = new Data([
            'title'=>'Points 2',
            'features'=>[
                ['id'=>'points2_0', 'popup_content'=>'Orange Bluff Popup'],
                ['id'=>'points2_3', 'properties'=>['featureName'=>null]],
            ],
            'legend_alt'=>'points 2 alt',
        ]);
        $points2 = Dataset::getDatasets()['points2.json'];
        $points2->updateDataset($points2Update, $points2->asJson()['datasetFileRoute']);
        $points3Update = new Data([
            'title'=>'Points 3',
            'legend_text'=>'points 3',
            'features'=>[
                ['id'=>'points3_0', 'popup_content'=> 'points 3 - 0', 'coordinates'=>[30.12, 30.42]],
                ['id'=>'points3_1', 'popup_content'=>'points 3 - 1'],
                ['id'=>'points3_2', 'popup_content'=>'points 3 -2'],
            ],
        ]);
        $points3 = Dataset::getDatasets()['points3.json'];
        $points3->updateDataset($points3Update, $points3->asJson()['datasetFileRoute']);
        //$text = "before: ".implode(',', array_keys(Dataset::getDatasets()))."\r\n";
        Dataset::resetDatasets();
        //return $text."count after: ".implode(',',array_keys(Dataset::getDatasets()));
        // set tours
        $pages = Grav::instance()['pages']->instances();
        $route = Grav::instance()['locator']->findResource('page://').'/test-tours/test-tour-';
        $config = new Data(Grav::instance()['config']->get('plugins.leaflet-tour'));
        $this->tour1 = new Tour($pages[$route.'1'], $config);
        $this->tour2 = new Tour($pages[$route.'2'], $config);
        $this->tour3 = new Tour($pages[$route.'3'], $config);
        $this->tour4 = new Tour($pages[$route.'4'], $config);
        $this->tour5 = new Tour($pages[$route.'5'], $config);
    }

    function testGetBasemaps() {
        // 1 no basemaps (tour 1)
        $this->isEmpty($this->tour1->getBasemaps());
        // 2 test with basemap added to tour (tour 2 - 1)
        $this->checkNum(1, count($this->tour2->getBasemaps()));
        // 3 basemap added to view (tour 3)
        $this->checkNum(1, count($this->tour3->getBasemaps()));
        // 4 basemap in tour and in view (tour 4)
        $this->checkNum(2, count($this->tour4->getBasemaps()));
    }

    function testGetAttribution() {
        // 1 attribution in plugin config (tour 2 - three from config, one from tileserver, and one additional from basemap)
        $this->checkNum(5, count($this->tour2->getAttribution()));
        // 2 attribution in tour config (tour 1 - 1)
        $this->checkNum(5, count($this->tour1->getAttribution()));
        // 3 attribution for basemaps (tour 3)
        $this->checkNum(6, count($this->tour3->getAttribution()));
        // 4 attribution with text, no url (tour 4)
        $a = $this->tour4->getAttribution();
        $this->isNotEmpty(array_column($a, null, 'name')['Attribution Item']);
        // 5 attribution with url, no text (tour 4)
        $this->isEmpty(array_column($a, null, 'url')['myfakeurl.com']);
        // 6 check attribution url when overwriting with no url (tour 1)
        $a = array_column($this->tour1->getAttribution(), null, 'name');
        $this->isEmpty($a['qgis2web']['url']);
        // 7 check attribution url when overwriting with url (tour 1)
        $this->checkString('fakeurl.com', $a['QGIS']['url']);
    }

    function testViewOne() {
        $views = $this->tour5->getViews();
        $v = $views[array_keys($views)[0]];
        // 1 basemaps (none)
        $this->isEmpty($v['basemaps']);
        // 2 only show view features (not set) (tour says false)
        $this->isFalse($v['onlyShowViewFeatures']);
        // 3 remove default basemap (not set) (tour says true)
        $this->isTrue($v['removeDefaultBasemap']);
        // 4, 5 starting zoom (-1 - no start) (but coords are set)
        $this->isEmpty($v['zoom']);
        $this->isEmpty($v['center']);
        // 6 features (none)
        $this->isEmpty($v['features']);
    }

    function testViewTwo() {
        $views = $this->tour5->getViews();
        $v = $views[array_keys($views)[1]];
        // 1 basemaps (one)
        $this->checkNum(1, count($v['basemaps']));
        // 2 only show view features (true) (but no features, so should be false)
        $this->isFalse($v['onlyShowViewFeatures']);
        // 3 remove default basemap (true)
        $this->isTrue($v['removeDefaultBasemap']);
        // 4 starting zoom (12)
        $this->checkNum(12, $v['zoom']);
        // 5 starting location (coordinates and feature set)
        $this->checkNum(30.12, $v['center'][0]);
        // 6 features (none)
        $this->isEmpty($v['features']);
    }

    function testViewThree() {
        $views = $this->tour5->getViews();
        $v = $views[array_keys($views)[2]];
        // 1 basemaps (two)
        $this->checkNum(2, count($v['basemaps']));
        // 2 only show view features (true)
        $this->isTrue($v['onlyShowViewFeatures']);
        // 3 remove default basemap (false)
        $this->isFalse($v['removeDefaultBasemap']);
        // 4 starting zoom (10)
        $this->checkNum(10, $v['zoom']);
        // 5 starting location (only coordinates are set)
        $this->checkNum(71.2, $v['center'][0]);
        // 6 features (five)
        $this->checkNum(5, count($v['features']));
    }

    function testOtherViews() {
        $views = $this->tour5->getViews();
        $v = $views[array_keys($views)[3]];
        // 1 View four: only show view features (false)
        $this->isFalse($v['onlyShowViewFeatures']);
        // 2 View four: starting location (coordinates and feature set, feature is only in hiddenFeatures for tour)
        $this->checkNum(12.3, $v['center'][0]);
        // 3 View five: starting location (only feature set, feature not in tour)
        $v = $views[array_keys($views)[4]];
        $this->isEmpty($v['center']);
        // 4 View six: starting location (not a point)
        $v = $views[array_keys($views)[5]];
        $this->isEmpty($v['center']);
    }

    function testGetDatasets() {
        // 1 tour with no datasets (tour 1)
        $this->isEmpty($this->tour1->getDatasets());
        // 2 legendAltText set by dataset (tour 2 - 2)
        $d = $this->tour2->getDatasets();
        $this->checkString('points 2', $d['points2.json']['legendAltText']);
        // 3 legendAltText set by tour header (tour 2 - 1)
        $this->checkString('points 1 alt', $d['points1.json']['legendAltText']);
    }

    function testGetFeatures() {
        // 1 tour with no datasets (tour 1)
        $this->isEmpty($this->tour1->getFeatures());
        // 2 tour with datasets but no features (tour 2)
        $this->isEmpty($this->tour2->getFeatures());
        // tour with one dataset show all and one dataset with three features (tour 3)
        $f = $this->tour3->getFeatures();
        // 3 tour with feature that does not exist in dataset (tour 3)
        $this->isEmpty($f['polygons_1']);
        // tour with feature that overrides feature in show all dataset (tour 3) (in test 3)
        // 4 tour with feature that does not have a value for the name property (tour 3)
        $this->isEmpty($f['points2_3']);
        // 5 check overall count
        $this->checkNum(11, count($f));
        // 6 check hasPopup (tour 3)
        $this->isFalse($f['points2_0']['properties']['hasPopup']);
        // 7 check dataSource (tour 3)
        $this->checkString('points1.json', $f['points1_7']['properties']['dataSource']);
    }

    function testGetLegend() {
        // 1 tour with datasets but no legend (tour 3)
        $this->isEmpty($this->tour3->getLegend());
        // 2 tour with two datasets, only one legend (tour 4)
        $this->checkNum(1, count($this->tour4->getLegend()));
        // 3 tour with two datasets, one has legend only from tour, one only from dataset (tour 5)
        $this->checkNum(2, count($this->tour5->getLegend()));
        // 4 tour with datasets, legend info, but no features (tour 2)
        $this->isEmpty($this->tour2->getLegend());
    }

    function testGetPopups() {
        // 1 no features with popups (tour 3)
        $this->isEmpty($this->tour3->getPopups());
        // features with popups (count) (tour 5)
        $p = $this->tour5->getPopups();
        // 2 check added popup exists
        $this->isNotEmpty($p['polygons_0']);
        // 3 check removed popup does not exist
        $this->isEmpty($p['points3_1']);
        // 4 check overwritten feature (not overwritten popup) exists
        $this->isNotEmpty($p['points3_0']);
        // 5 check non-overwritten feature popup exists
        $this->isNotEmpty($p['points3_2']);
        // 6 check count
        $this->checkNum(4, count($p));
    }

    function testGetViewPopups() {
        $views = $this->tour5->getViews();
        $v1 = array_keys($views)[0];
        $v3 = array_keys($views)[2];
        $v4 = array_keys($views)[3];
        $v5 = array_keys($views)[4];
        // 1 view with no features (view 1)
        $this->isEmpty($this->tour5->getViewPopups($v1));
        // 2 view with features, no popups (view 4)
        $this->isEmpty($this->tour5->getViewPopups($v4));
        // 3 view with some popups (count) (view 3 - 3)
        $this->checkNum(3, count($this->tour5->getViewPopups($v3)));
        // 4 view with some popups, some of which are not for features included in tour (view 5)
        $this->checkNum(1, count($this->tour5->getViewPopups($v5)));
    }

}
?>