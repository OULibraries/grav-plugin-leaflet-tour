<?php
namespace Grav\Plugin\LeafletTour;

require_once __DIR__ . '/Datasets.php';

use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledJsonFile;
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\File\MarkdownFile;
//use Grav\Plugin\LeafletTour\Datasets;

class Dataset {
    
    public $name;
    public $jsonFilename;
    public $nameProperty;
    public $datasetFileRoute;
    public $properties;
    public $features;

    public $legendText;
    public $legendAltText;
    public $iconFilename;
    public $iconOptions;
    public $hasPopup;
    
    public $autoPopupProperties; // list of all properties to automatically add to popups

    protected $config;
    
    function __construct($jsonFilename, $name, $config) {
        $this->config = $config;
        try {
            // read json file and get data
            $jsonFile = CompiledJsonFile::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/'.$jsonFilename);
            $jsonData = new Data($jsonFile->content());

            // set basic properties
            $this->name = $name;
            $this->jsonFilename = $jsonFilename;
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
            
        } catch (Exception $e) {
            // TODO: Error handling?
        }
    }
    
    /*protected function buildLocationList() {
        $locations = [];
        foreach ($this->locations as $id => $loc) {
            $name = $loc['properties'][$this->name_prop];
            if (!empty($name)) $locations[$id] = $name.' ('.$this->name.')';
        }
        $this->location_list = $locations;
    }*/
    
    protected function addDatasetFileInfo() {
        try {
            $file = MarkdownFile::instance($this->datasetFileRoute);
            $header = new Data((array)($file->header()));
            
            $this->legendText = $header->get('legend_text');
            $this->legendAltText = $header->get('legend_alt');
            $this->iconFilename = $header->get('icon.file');
            $this->iconAltText = $header->get('icon_alt');
            //$this->autoPopupProperties = $data->get('popup_props');
            $this->hasPopup = (!empty($header->get('popup_content')));
            $this->iconOptions = $header->get('icon') ?? [];
        } catch (Exception $e) {
            // TODO: error handling?
        }
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
        }
        $jsonFile->content($jsonData);
        $jsonFile->save();
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
            if ($feature['geometry']['type'] === 'Point') $points[$id] = $feature['properties'][$this->nameProperty].' ('.$this->name.')';
        }
        return $points;
    }

    public function getFeatureList() {
        $features = [];
        foreach ($this->features as $id => $feature) {
            $features[$id] = $feature['properties'][$this->nameProperty].'('.$this->name.')';
        }
        return $features;
    }
}

?>