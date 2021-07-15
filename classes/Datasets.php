<?php
namespace Grav\Plugin\LeafletTour;

require_once __DIR__ . '/Dataset.php';

use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledJsonFile;
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\File\File;
use Grav\Plugin\LeafletTour\Dataset;

/**
 * The Datasets class stores the list of all Dataset objects in the plugin.
 * It handles conversion of non-json files to json format as well as interaction with the dataset metadata file.
 */
class Datasets {

    const JSON_VAR_REGEX = '/^(.)*var(\s)+json_(\w)*(\s)+=(\s)+/';
    const META_FILE_ROUTE = '/user/data/leaflet-tour/datasets/meta.yaml';
    
    protected static $instance;
    
    protected $datasets; // Dataset array
    protected $grav; // Grav
    protected $config; // Data
    
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
        foreach($this->getDatasetFiles() as $jsonFilename => $name) {
            $this->datasets[$jsonFilename] = new Dataset($jsonFilename, $name, $this->config);
        }
    }
    
    // TODO: temporary function for compat - remove when possible
    public function createDataset($fileData) { return $this->parseDatasetUpload($fileData); }

    /**
     * Handle a file upload and create a new dataset
     * 
     * @param array $fileData - the yaml array for the uploaded file from plugin config data_files [name, type, size, path]
     * @return Dataset/null (for success/failure)
     */
    public function parseDatasetUpload($fileData) {
        $jsonFilename = preg_replace('/.js$/', '.json', $fileData['name']);
        //$tmpNameArray = explode('/', preg_replace('/.js$/', '.json', $fileData['name']));
        //$jsonFilename = $tmpNameArray[count($tmpNameArray)-1];
        //$id = preg_replace('/.json$/', '', $jsonFilename);

        // check file type and pass to appropriate handler
        if ($fileData['type'] === 'text/javascript') {
            $jsonArray = $this->readJSFile($fileData['name']);
            $jsonData = new Data($jsonArray);
        }
        // TODO: Check for other file types

        // some basic json validation
        if (empty($jsonData) || empty($jsonData->get('features.0.properties')) || empty($jsonData->get('features.0.geometry'))) return null;
        //if (empty($jsonData->get('features'))) return false; // TODO: remove?
        //if (empty($jsonData->get('features.0.properties'))) return false;
        //if (empty($jsonData->get('features.0.geometry'))) return false;

        // TODO: Determine appropriate next steps based on update status --- Is update status set in plugin config, or in page config...? I'm guessing plugin config. Page config might cause problems for uploads.
        // create the dataset (new upload, not update)
        $dataset = Dataset::createNewDataset($jsonFilename, $this->config, $jsonArray, $jsonData);
        if ($dataset) {
            $this->updateMetadata($dataset->id, $dataset->name);
            return $dataset;
        }

        return null;
    }

    /**
     * Called when dataset config page is saved. Handles passing updates to Dataset object (and from there the json file), as well as updating the metadata if needed.
     * @param Header $header - header for the dataset page
     * @param string $datasetFileRoute - full route to the dataset page
     * @param bool $changeFeatures - true if header features are not the same as the original header features
     */
    /*public function updateDatasetConfig($header, $datasetFileRoute, $changeFeatures): void {
        $metaUpdate = $this->datasets[$header->get('dataset_file')]->updateDataset($header, $datasetFileRoute, $changeFeatures);
        if (!empty($metaUpdate)) {
            $this->updateMetadata($header->get('dataset_file'), $metaUpdate);
        }
        
    }*/

    // TODO: remove temp
    public function temp($jsonData, $jsonFilename, $id) {

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
        } catch (Exception $e) {
            return false;
        }
        
        // add to datasets
       /* $newDataset = new Dataset($jsonFilename, $name, $this->config);
        $this->datasets[] = $newDataset;

        $newDataset->createDatasetPage($jsonArray['datasetFileRoute']);*/
        return true;
    }
    
    /**
     * Turn Qgis2Web .js file into .json
     * 
     * @param array $fileData - the yaml array for the uploaded file from plugin config data_files [name, type, size, path]
     * @return array/null - returns array with json data on success, null on failure
     */
    protected function readJSFile($fileData) {
        $file = File::instance($this->grav['locator']->getBase().'/'.$fileData['path']);
        // find and remove the initial json variable
        $count = 0;
        $jsonRegex = preg_replace(self::JSON_VAR_REGEX, '', $file->content(), 1, $count);
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

    // TODO: Other functions to read other types of files - json, csv, excel... others?

    /**
     * Adds/updates entry for the dataset in the metadata yaml file so list of datasets can be easily accessed
     * Parameters can come directly from the Dataset object
     */
    public function updateMetadata($id, $name): void {
        $metaFile = CompiledYamlFile::instance($this->grav['locator']->getBase().self::META_FILE_ROUTE);
        $metaData = $metaFile->content();
        if (empty($metaData) || empty($metaData['datasets'])) {
            $metaData = ['datasets'=>[]];
        }
        $metaData['datasets'][$id] = $name;
        $metaFile->content($metaData);
        $metaFile->save();
    }

    // $datasets getter function
    public function getDatasets(): array {
        return $this->datasets;
    }

    /**
     * returns a list of all datasets included in the yaml meta file [id (jsonFilename) => name]
     */
    public function getDatasetFiles(): array {
        //$metaOptions = ['id', 'name', 'jsonFileName', 'datasetFileRoute'];
        $metaFile = CompiledYamlFile::instance($this->grav['locator']->getBase().self::META_FILE_ROUTE);
        $metaData = $metaFile->content();
        if (empty($metaData) || empty($metaData['datasets'])) return [];
        else {
            //if ($column && !in_array($column, $metaOptions)) $column = null;
            //if (!in_array($key, $metaOptions)) $key='id';
            //return array_column($metaData['datasets'], $column, $key);
            return $metaData['datasets'];
        }
    }
}