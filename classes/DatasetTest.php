<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Data\Data;
use Symfony\Component\Yaml\Yaml;


class DatasetTest extends Test {
    protected function setup() {
        parent::setup();
        $this->testHeader = "Results for Dataset Test";
    }

    public static function getResults(bool $showSuccess=false, $showPrint = true, $test=null): string {
        return self::getTestResults(new DatasetTest(), $showSuccess, $showPrint);
    }

    /**
     * Testing Datasets Details:
     * 
     * Points Dataset Number One
     * - basic points dataset
     * - has feature property "name"
     * - has a name
     * - has three properties per feature
     * - has nine features
     * - properties/attributes are not in quotation marks
     * - contains a mix of single quotes and double quotes
     * 
     * Points Dataset Number Two
     * - points dataset with some errors
     * - has feature property "featureName"
     * - has no type
     * - has a name
     * - has five valid points
     * - has three invalid points (one of them just doesn't have type: 'feature')
     * - has up to three properties per feature
     * - all double quotes
     * 
     * Polygons Dataset
     * - basic polygons dataset
     * - has no feature property with "name"
     * - does not have a name
     * - has six valid features
     * - has a long comment at the start
     * 
     * Points Dataset Number Three
     * - badly formatted js file
     * - has random content at the start
     * - has three features
     * 
     * Points Dataset Number Four
     * - badly formatted js file
     * - has random content at the end
     * - has three features
     * 
     * Points Dataset Number Five
     * - json var does not start with 'json_'
     * - has three features
     */

    // tests

    // also tests Utils::readJsFile
    function testCreateNewDataset() {
        // 1 basic points dataset, has property "name", has name
        $file = ['path'=>'/user/data/leaflet-tour/datasets/uploads/points1.js', 'type'=>'text/javascript', 'name'=>'points1.js'];
        $json = Utils::parseDatasetUpload($file);
        $this->isNotEmpty($json);
        Dataset::createNewDataset($json[0], $json[1]);
        $this->pointsDatasetOne = Dataset::getDatasets()['points1.json'];
        // 2 points dataset with some errors, has property "featureName"
        $file = ['path'=>'/user/data/leaflet-tour/datasets/uploads/points2.js', 'type'=>'text/javascript', 'name'=>'points2.js'];
        $json = Utils::parseDatasetUpload($file);
        $this->isNotEmpty($json);
        Dataset::createNewDataset($json[0], $json[1]);
        $this->pointsDatasetTwo = Dataset::getDatasets()['points2.json'];
        // 3 basic polygons dataset, has no property with "name", does not have name
        $file = ['path'=>'/user/data/leaflet-tour/datasets/uploads/polygons.js', 'type'=>'text/javascript', 'name'=>'polygons.js'];
        $json = Utils::parseDatasetUpload($file);
        $this->isNotEmpty($json);
        Dataset::createNewDataset($json[0], $json[1]);
        $this->polygonsDataset = Dataset::getDatasets()['polygons.json'];
        // 4 badly formatted js file (that theoretically should still work)
        $file = ['path'=>'/user/data/leaflet-tour/datasets/uploads/points3.js', 'type'=>'text/javascript', 'name'=>'points3.js'];
        $json = Utils::parseDatasetUpload($file);
        $this->isNotEmpty($json);
        Dataset::createNewDataset($json[0], $json[1]);
        $this->pointsDatasetThree = Dataset::getDatasets()['points3.json'];
        // 5 badly formatted js file (that should not work)
        $file = ['path'=>'/user/data/leaflet-tour/datasets/uploads/points4.js', 'type'=>'text/javascript', 'name'=>'points4.js'];
        $json = Utils::parseDatasetUpload($file);
        $this->isEmpty($json);
        // 6 js file where json var does not start with 'json_'
        $file = ['path'=>'/user/data/leaflet-tour/datasets/uploads/points5.js', 'type'=>'text/javascript', 'name'=>'points5.js'];
        $json = Utils::parseDatasetUpload($file);
        $this->isEmpty($json);
        // 7 loci test file
        $file = ['path'=>'/user/data/leaflet-tour/datasets/uploads/LOCI_2.js', 'type'=>'text/javascript', 'name'=>'LOCI_2.js'];
        $json = Utils::parseDatasetUpload($file);
        $this->isNotEmpty($json);
        // TODO: Other file types
    }

    function testPointsDatasetOne() {
        $d = $this->pointsDatasetOne;
        // 1 number of features
        $this->checkNum(9, count($d->getFeatures()));
        // 2 dataset name
        $this->checkString('Points Dataset Number One', $d->getName());
        // 3 name property
        $this->checkString('name', $d->getNameProperty());
    }

    function testPointsDatasetTwo() {
        $d = $this->pointsDatasetTwo;
        // 1 number of features
        $this->checkNum(5, count($d->getFeatures()));
        // 2 name property
        $this->checkString('featureName', $d->getNameProperty());
        // 3 dataset file route
        $this->isNotEmpty($d->asJson()['datasetFileRoute']);
    }

    function testPolygonsDataset() {
        $d = $this->polygonsDataset;
        // 1 number of features
        $this->checkNum(6, count($d->getFeatures()));
        // 2 name property
        $this->checkString('OBJECTID', $d->getNameProperty());
        // 3 feature type
        $this->checkString('Polygon', $d->getFeatureType());
    }

    function testPointsDatasetThree() {
        $d = $this->pointsDatasetThree;
        // 1 number of features
        $this->checkNum(3, count($d->getFeatures()));
        // 2 properties
        $this->checkNum(3, count($d->getProperties()));
    }

    function testUpdateDataset() {
        $d = $this->pointsDatasetTwo;
        $route = $d->asJson()['datasetFileRoute'];
        // 1 test changing name property (where one has null)
        $update = new Data(['title'=>'Dataset Title', 'name_prop'=>'type']);
        $d->updateDataset($update, $route);
        $this->checkString('type', $d->getNameProperty());
        // 2 test number of features when creating new dataset from the changed dataset
        $d2 = new Dataset('points2.json');
        $this->checkNum(2, count($d2->getFeatures()));
        // 3 test changing name property with an option that does not work
        $update->set('name_prop', 'notaprop');
        $d->updateDataset($update, $route);
        $this->checkString('type', $d->getNameProperty());
        // 4 test update with features removed
        $features = [
            ['id'=>'points2_0'],
            ['id'=>'points2_1'],
            ['id'=>'points2_2'],
            ['id'=>'points2_3']
        ];
        $update->set('features', $features);
        $update->set('name_prop', 'featureName');
        $d->updateDataset($update, $route);
        $this->checkNum(5, count($d->getFeatures()));
        // 5 test update with features added
        $features[] = ['id'=>'1', 'properties'=>['featureName'=>'New 1', 'type'=>'Landing'], 'geometry'=>['type'=>'Point', 'coordinates'=>[-81.888, 29.111]]];
        $features[] = ['id'=>'2', 'properties'=>['featureName'=>'New 2', 'type'=>'Campsite'], 'geometry'=>['type'=>'Point', 'coordinates'=>[-81.777, 29.222]]];
        $update->set('features', $features);
        $d->updateDataset($update, $route);
        $this->checkNum(5, count($d->getFeatures()));
        // 6 test update with features modified (check one of the modifications)
        $features[0] = ['id'=>'points2_0', 'custom_name'=>'Orange Bluff the Second'];
        $update->set('features', $features);
        $d->updateDataset($update, $route);
        $this->checkString('Orange Bluff the Second', $d->getFeatures()['points2_0']->getName());
        // 7 check modification when creating new dataset from the changed dataset
        $d3 = new Dataset('points2.json');
        $this->checkString('Orange Bluff the Second', $d3->getFeatures()['points2_0']->getName());
    }

    function testMergeTourData() {
        $d = $this->pointsDatasetOne;
        $d->updateDataset(new Data(['title'=>'Dataset Title', 'icon'=>null]), $d->asJson()['datasetFileRoute']);
        // 1 test when all icon options are blank
        $dataset = new Data();
        $merge = $d->mergeTourData($dataset, []);
        $this->checkString('user/plugins/leaflet-tour/images/marker-shadow.png', $merge->get('iconOptions.iconUrl'));
        // 2 test icon size (default) when no url is set, but icon options are not blank
        $dataset = new Data(['icon'=>['className'=>'testClass']]);
        $merge = $d->mergeTourData($dataset, []);
        $this->checkNum(25, $merge->get('iconOptions.iconSize')[0]);
        // 3 test icon size (default) when a url is set
        $dataset = new Data(['icon'=>['file'=>'iconFile.png', 'className'=>'testClass']]);
        $merge = $d->mergeTourData($dataset, []);
        $this->checkNum(14, $merge->get('iconOptions.iconSize')[0]);
        // 4 test anchor with only x and non-default url
        $dataset->set('icon.anchor_x', 70);
        $this->isEmpty($d->mergeTourData($dataset, [])->get('iconOptions.iconAnchor'));
        // 5 test anchor with only x and default url
        $dataset->set('icon.file', null);
        $this->checkNum(70, $d->mergeTourData($dataset, [])->get('iconOptions.iconAnchor')[0]);
        // 6 test anchor with both x and y and non-default url (but y from dataset, not tour)
        $update = new Data(['title'=>'Dataset Title', 'icon'=>['anchor_y'=>44]]);
        $d->updateDataset($update, $d->asJson()['datasetFileRoute']);
        $dataset->set('icon.file', 'iconFile.png');
        $this->checkNum(44, $d->mergeTourData($dataset, [])->get('iconOptions.iconAnchor')[1]);
        // 7 test icon shadow with non-default url and no shadow set
        $this->isEmpty($d->mergeTourData($dataset, [])->get('iconOptions.shadowUrl'));
        // 8 test icon shadow with default url and no shadow set
        $dataset->set('icon.file', null);
        $this->checkNum(41, $d->mergeTourData($dataset, [])->get('iconOptions.shadowSize')[0]);
        // 9 test icon shadow with non-default url and shadow set
        $dataset->set('icon.file', 'iconFile.png');
        $dataset->set('icon.shadow', 'shadowFile.png');
        $this->checkNum(14, $d->mergeTourData($dataset, [])->get('iconOptions.shadowSize')[0]);
        // 10 test legend text from dataset
        $update->set('legend_text', "My Test Legend");
        $dataset->set('show_all', true);
        $d->updateDataset($update, $d->asJson()['datasetFileRoute']);
        $this->checkString('My Test Legend', $d->mergeTourData($dataset, [])->get('legend.legendText'));
        // 11 test legend text from tour overrides dataset
        $dataset->set('legend_text', 'legend text');
        $this->checkString('legend text', $d->mergeTourData($dataset, [])->get('legend.legendText'));
        // 12 test legendAltText when tour has legend text but no alt text, but dataset has both
        $update->set('legend_alt', 'Alt');
        $d->updateDataset($update, $d->asJson()['datasetFileRoute']);
        $this->checkString('legend text', $d->mergeTourData($dataset, [])->get('legendAltText'));
        // 13 test tour features when show all and no features list
        $dataset->set('show_all', true);
        $this->checkNum(9, count($d->mergeTourData($dataset, [])->get('features')));
        // 14 test tour features when not show all and no features list
        $dataset->set('show_all', false);
        $this->isEmpty($d->mergeTourData($dataset, [])->get('features'));
        // 15 test hidden features when not show all and some features list
        $features = [
            ['id'=>'points1_1'],
            ['id'=>'points1_2'],
        ];
        $this->checkNum(7, count($d->mergeTourData($dataset, $features)->get('hiddenFeatures')));
        // 16 test that remove_popup overrides dataset popup
        $updateFeatures = [['id'=>'points1_1', 'popup_content'=>'test content']];
        $update->set('features', $updateFeatures);
        $d->updateDataset($update, $d->asJson()['datasetFileRoute']);
        $features[0]['remove_popup'] = true;
        $this->isEmpty($d->mergeTourData($dataset, $features)->get('features')['points1_1']['popupContent']);
        // 17 test that popup_content overrides remove_popup
        $features[0]['popup_content'] = 'test content';
        $this->isNotEmpty($d->mergeTourData($dataset, $features)->get('features')['points1_1']['popupContent']);
    }

    function testGetDatasetList() {
        $text = '';
        foreach(Dataset::getDatasetList() as $id=>$name) {
            $text .= "$id - $name\r\n";
        }
        return $text;
    }

    function testAsYaml() {
        return Yaml::dump($this->pointsDatasetTwo->asYaml());
    }

    function testAsJson() {
        return json_encode($this->pointsDatasetOne->asJson(), JSON_PRETTY_PRINT);
    }

}
?>