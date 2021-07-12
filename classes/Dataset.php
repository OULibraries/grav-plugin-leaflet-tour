<?php
namespace Grav\Plugin\LeafletTour;

require_once __DIR__ . '/Datasets.php';

use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledJsonFile;
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\File\MarkdownFile;

class Dataset {
    
    public $name;
    public $jsonFilename;
    public $id; // jsonFilename is often used for id, but this is the id used to develop location ids and folder name for dataset page
    public $nameProperty;
    public $datasetFileRoute;
    public $properties;
    public $features;

    public $legendText;
    public $legendAltText;
    public $iconFilename;
    public $iconOptions;
    
    public $autoPopupProperties; // list of all properties to automatically add to popups

    protected $config;
    
    function __construct($jsonFilename, $name, $config) {
        $this->config = $config;
        // read json file and get data
        $jsonFile = CompiledJsonFile::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/'.$jsonFilename);
        if (!$jsonFile->exists()) return;
        $jsonData = new Data($jsonFile->content());

        // set basic properties
        $this->name = $name;
        $this->jsonFilename = $jsonFilename;
        $this->id = preg_replace('/.json$/', '', $jsonFilename);
        $this->nameProperty = $jsonData->get('nameProperty');
        $this->datasetFileRoute = $jsonData->get('datasetFileRoute');
        $this->properties = array_keys((array)($jsonData->get('features.0.properties')));
        $this->features = array_column(((array)($jsonData->get('features'))), null, 'id');

        // remove unnamed features
        foreach ($this->features as $id => $feature) {
            if (empty($feature['properties'][$this->nameProperty])) unset($this->features[$id]);
        }

        // check for dataset file to add legend, icon, and popup options
        if (!empty($this->datasetFileRoute)) $this->addDatasetFileInfo();
    }
    
    protected function addDatasetFileInfo() {
        $file = MarkdownFile::instance($this->datasetFileRoute);
        if (!$file->exists()) return;
        $header = new Data((array)($file->header()));
        
        $this->legendText = $header->get('legend_text');
        $this->legendAltText = $header->get('legend_alt');
        $this->iconFilename = $header->get('icon.file');
        $this->iconAltText = $header->get('icon_alt');
        //$this->autoPopupProperties = $data->get('popup_props');
        $this->iconOptions = $header->get('icon') ?? [];
        // feature list - popupContent and customName
        if (!empty($header->get('features'))) {
            foreach ($header->get('features') as $headerFeature) {
                $feature = $this->features[$headerFeature['id']];
                if (!empty($feature)) {
                    $feature['popupContent'] = $headerFeature['popup_content'];
                    $feature['customName'] = $headerFeature['custom_name'];
                    $this->features[$headerFeature['id']] = $feature;
                }
            }
        }
    }

    public function createDatasetPage($datasetFileRoute): void {
        $file = MarkdownFile::instance($datasetFileRoute);
        // check if file already exists (don't overwrite)
        if ($file->exists()) return;
        // build header array
        $header = [
            'routable'=>0,
            'visible'=>0,
            'dataset_file'=>$this->jsonFilename,
            'title'=>$this->name,
            'name_prop'=>$this->nameProperty,
        ];
        // add feature list
        $features = [];
        foreach ($this->features as $id => $feature) {
            $features[$id] = [
                'id'=>$id,
                'name'=>$feature['properties'][$this->nameProperty]
            ];
        }
        $header['features'] = $features;
        // save
        $file->header($header);
        $file->save();
    }

    // called when dataset config is saved - updates info in the json file - nameProperty, datasetFileRoute - also updates dataset name in the metadata file
    public function updateDataset($datasetFileRoute, $name=null, $nameProperty=null) {
        // update metadata file
        if (!empty($name)) {
            self::updateMetadata($this->jsonFilename, $name);
        }
        // update json file
        $jsonFile = CompiledJsonFile::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/'.$this->jsonFilename);
        $jsonData = $jsonFile->content();
        $jsonData['datasetFileRoute'] = $datasetFileRoute;
        if (!empty($nameProperty) && in_array($nameProperty, $this->properties)) {
            $jsonData['nameProperty'] = $nameProperty;
            $this->nameProperty = $nameProperty;
        }
        if (!empty($name)) {
            $jsonData['name'] = $name;
            $this->name = $name;
        }
        $jsonFile->content($jsonData);
        $jsonFile->save();
        // update dataset
        $this->$datasetFileRoute = $datasetFileRoute;
        $this->addDatasetFileInfo();
    }

    public static function updateMetadata($jsonFilename, $name) {
        $metaFile = CompiledYamlFile::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/meta.yaml');
        $metaData = $metaFile->content();
        if (empty($metaData) || empty($metaData['datasets'])) $metaData = ['datasets'=>[$jsonFilename=>$name]];
        else {
            $metaData['datasets'][$jsonFilename] = $name;
        }
        $metaFile->content($metaData);
        $metaFile->save();
    }

    public function getPointList() {
        $points = [];
        foreach ($this->features as $id => $feature) {
            if ($feature['geometry']['type'] === 'Point') $points[$id] = ($feature['customName'] ?? $feature['properties'][$this->nameProperty]).' ('.$this->name.')';
        }
        return $points;
    }

    public function getFeatureList() {
        $features = [];
        foreach ($this->features as $id => $feature) {
            $features[$id] = ($feature['customName'] ?? $feature['properties'][$this->nameProperty]).' ('.$this->name.')';
        }
        return $features;
    }
}

?>