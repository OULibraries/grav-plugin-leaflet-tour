<?php
namespace Grav\Plugin\LeafletTour;

require_once __DIR__ . '/Dataset.php';

use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledJsonFile;
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\File\File;
use Grav\Plugin\LeafletTour\Dataset;
//use RocketTheme\Toolbox\File\MarkdownFile;

class Datasets {
    
    protected static $instance;
    
    protected $datasets;
    protected $grav;
    protected $config;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new Datasets();
        }
        return self::$instance;
    }
    
    protected function __construct() {
        $this->grav = Grav::instance();
        $this->config = new Data($this->grav['config']->get('plugins.leaflet-tour'));
        $this->datasets = [];
        foreach(self::getDatasetFiles() as $jsonFilename => $name) {
            $this->datasets[$jsonFilename] = new Dataset($jsonFilename, $name, $this->config);
        }
    }
    
    /**
     * Handle a file upload and create a new dataset
     * 
     * @param array $file - the yaml for the uploaded file
     * @return Dataset
     */
    public function createDataset($fileData) {
        $tmpNameArray = explode('/', preg_replace('/.js$/', '.json', $fileData['name']));
        $jsonFilename = $tmpNameArray[count($tmpNameArray)-1];
        $id = preg_replace('/.json$/', '', $jsonFilename);

        // check file type and pass to appropriate handler
        if ($fileData['type'] === 'text/javascript') {
            $jsonArray = $this->readJSFile($fileData['name']);
            $jsonData = new Data($jsonArray);
        }
        // some basic json validation
        if (empty($jsonData->get('features'))) return false; // TODO: remove?
        if (empty($jsonData->get('features.0.properties'))) return false;
        if (empty($jsonData->get('features.0.geometry'))) return false;

        // set dataset name
        $name = $jsonData['name'];
        if (empty($name)) {
            $name = $id;
            $jsonArray['name'] = $name;
        }

        // set default name property
        $nameProperty = '';
        $propertyList = array_keys($jsonData->get('features.0.properties'));
        foreach ($propertyList as $prop) {
            if (strcasecmp($prop, 'name') == 0) $nameProperty = $prop;
            else if (empty($nameProperty) && preg_match('/^(.*name|name.*)$/i', $prop)) $nameProperty = $prop;
        }
        if (empty($nameProperty)) $nameProperty = $propertyList[0];
        $jsonArray['nameProperty'] = $nameProperty;

        // set dataset file route
        // TODO: should I really be checking this?
        if (empty($jsonData->get('datasetFileRoute'))) {
            $jsonArray['datasetFileRoute'] = $this->grav['locator']->findResource('page://').'/datasets/'.$id.'/dataset.md';
        }

        // set feature ids
        $count = 0;
        // TODO: needs array cast?
        $features = [];
        foreach ($jsonData->get('features') as $feature) {
            $feature['id'] = $id.'_'.$count;
            $features[] = $feature;
            $count++;
        }
        $jsonArray['features'] = $features;

        // try saving the file
        try {
            $jsonFile = CompiledJsonFile::instance($this->grav['locator']->findResource('user://').'/data/leaflet-tour/datasets/'.$jsonFilename);
            $jsonFile->content($jsonArray);
            $jsonFile->save();
            // update metadata
            Dataset::updateMetadata($jsonFilename, $name);
            /*$metaFile = CompiledYamlFile::instance($this->grav['locator']->findResource('user://').'/data/leaflet-tour/datasets/meta.yaml');
            $content = $metaFile->content();
            if (empty($content) || empty($content['datasets'])) $content = ['datasets'=>[$jsonFilename=>$name]];
            else {
                $content['datasets'][$jsonFilename] = $name;
            }
            $metaFile->content($content);
            $metaFile->save();*/
        } catch (Exception $e) {
            return false;
        }
        
        // add to datasets
        $newDataset = new Dataset($jsonFilename, $name, $this->config);
        $this->datasets[] = $newDataset;

        $newDataset->createDatasetPage($jsonArray['datasetFileRoute']);
        return true;
    }
    
    /**
     * Turn Qgis2Web .js file into .json
     * 
     * @return array/null
     */
    protected function readJSFile($filename) {
        $file = File::instance($this->grav['locator']->findResource('user://').'/data/leaflet-tour/datasets/uploads/'.$filename);
        // find and remove the initial json variable
        $count = 0;
        // TODO: Document file requirements - json file must have a variable beginning with 'json_'
        $jsonRegex = preg_replace('/^(.)*var(\s)+json_(\w)*(\s)+=(\s)+/', '', $file->content(), 1, $count);
        // if a match was found (and removed), try converting the file contents to json
        if ($count == 1) {
            // fix php's bad json handling
            if (version_compare(phpversion(), '7.1', '>=')) {
            	ini_set( 'serialize_precision', -1 );
            }
            try {
                $jsonData = json_decode($jsonRegex, true);
                return $jsonData;
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }

    public function getDatasets() {
        return $this->datasets;
    }
    
    /*protected function updateMetadata($filename) {
        $file = CompiledYamlFile::instance($this->grav['locator']->findResource('user://').'/data/leaflet-tour/datasets/meta.yaml');
        $content = $file->content();
        if (empty($content) || empty($content['datasets'])) $content = ['datasets'=>[$filename=>'']];
        else {
            if ($content['datasets'][$filename]) return;
            $content['datasets'][$filename] = '';
        }
        $file->content($content);
        $file->save();
    }*/

    // returns a list of all datasets included in the yaml meta file [jsonFilename => dataset name]
    public static function getDatasetFiles() {
        $file = CompiledYamlFile::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/meta.yaml');
        $content = $file->content();
        if (empty($content) || empty($content['datasets'])) return [];
        else return $content['datasets'];
    }
}