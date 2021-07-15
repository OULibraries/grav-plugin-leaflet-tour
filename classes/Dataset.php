<?php
namespace Grav\Plugin\LeafletTour;

//require_once __DIR__ . '/Datasets.php';

use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledJsonFile;
//use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\File\MarkdownFile;
use Grav\Plugin\LeafletTour\Feature;

/**
 * The Dataset class stores and handles all the information for one GeoJSON dataset.
 * It combines data from the json file and the dataset page when created, and ensures that it is provided in a useful format.
 * It also handles updates when the dataset page config is modified.
 */
class Dataset {
    
    public $name;
    public $jsonFilename; // serves as dataset id, too
    public $nameProperty;
    public $datasetFileRoute;
    public $properties;
    public $features; // Feature array

    public $legendText;
    public $legendAltText;
    public $iconAltText;
    public $iconFilename;
    public $iconOptions;
    
    public $autoPopupProperties; // list of all properties to automatically add to popups

    protected $config;
    
    function __construct($jsonFilename, $name, $config) {
        $this->config = $config;
        $this->jsonFilename = $jsonFilename;
        $this->name = $name;
        // read json file and get data
        $this->jsonRoute = Grav::instance()['locator']->findResource('user-data://').'/leaflet-tour/datasets/'.$jsonFilename;
        $jsonFile = CompiledJsonFile::instance($this->jsonRoute);
        if (!$jsonFile->exists()) return;
        $jsonData = new Data($jsonFile->content());

        $this->nameProperty = $jsonData->get('nameProperty');
        $this->datasetFileRoute = $jsonData->get('datasetFileRoute');
        $this->properties = array_keys((array)($jsonData->get('features.0.properties')));
        $this->features = array_column(((array)($jsonData->get('features'))), null, 'id');

        // remove unnamed features
        foreach ($this->features as $featureId => $feature) {
            if (empty($feature['properties'][$this->nameProperty]) && empty($feature['customName'])) unset($this->features[$featureId]);
        }

        // check for dataset file to add legend, icon, and popup options
        if (!empty($this->datasetFileRoute)) $this->addDatasetFileInfo();
    }
    
    /**
     * Adds info stored only in the dataset page header and not in the json file, such as legend/icon options and popup content.
     */
    protected function addDatasetFileInfo(): void {
        $file = MarkdownFile::instance($this->datasetFileRoute);
        if (!$file->exists()) return;
        $header = new Data((array)($file->header()));
        
        $this->legendText = $header->get('legend_text');
        $this->legendAltText = $header->get('legend_alt');
        $this->iconFilename = $header->get('icon.file');
        $this->iconAltText = $header->get('icon_alt');
        //$this->autoPopupProperties = $data->get('popup_props');
        $this->iconOptions = $header->get('icon') ?? [];
        // feature list - popupContent
        if (!empty($header->get('features'))) {
            foreach ($header->get('features') as $headerFeature) {
                $id = $headerFeature['id'];
                if (!empty($this->features[$id])) {
                    $this->features[$id]['popupContent'] = $headerFeature['popup_content'];
                    //$feature['customName'] = $headerFeature['custom_name'];
                    //$this->features[$headerFeature['id']] = $feature;
                }
            }
        }
    }

    /**
     * Called whenever the dataset config is saved. Updates properties in the object and in the json file.
     * @param Header $header - the header for the dataset page
     * @param string $datasetFileRoute - full route to the dataset page
     * @param array $originalFeatures - list of previous features - used to check if changes need to be made and to deal with any potential issues, like deleted features
     * @return array - list of features from header with any needed changes
     */
    public function updateDataset($header, $datasetFileRoute, $originalFeatures) {
        // open json file for updating
        $jsonFile = CompiledJsonFile::instance($this->jsonRoute);
        $jsonData = $jsonFile->content();
        // update basic properties
        $name = $header->get('title');
        if ($name !== $this->name) {
            $this->name = $name;
            $jsonData['name'] = $name;
        }
        $nameProperty = $header->get('name_prop');
        // TODO: Make sure property list is updated first if allowing users to add/remove properties
        if ($nameProperty !== $this->nameProperty && in_array($nameProperty, $this->properties)) {
            $this->nameProperty = $nameProperty;
            $jsonData['nameProperty'] = $nameProperty;
        }
        if ($datasetFileRoute !== $this->datasetFileRoute) {
            $this->datasetFileRoute = $datasetFileRoute;
            $jsonData['datasetFileRoute'] = $datasetFileRoute;
        }
        // reconcile feature list
        $headerFeatures = $header->get('features');
        if ($headerFeatures !== $originalFeatures) {
            if (empty($headerFeatures)) {
                $headerFeatures = $originalFeatures;
            } else {
                // make originalFeatures an associative array for ease of use
                $originalFeatures = array_column($originalFeatures, null, 'id');
                foreach ($headerFeatures as $feature) {
                    // added, updated, or no change
                    $original = $originalFeatures[$feature['id']];
                    if ($original) {
                        // not an added feature
                        if ($original !== $feature) {
                            // updated - check custom name
                            // TODO: Allow updating additional fields
                        }

                        // remove feature from originals so we can check if any feature are left (removed)
                    }
                }
            }
        }
        // TODO: allow add/remove features, add (and remove?) properties
        // update legend, icon, and popup properties
        // return array
        return $headerFeatures;
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

    /*public static function updateMetadata($jsonFilename, $name) {
        $metaFile = CompiledYamlFile::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/meta.yaml');
        $metaData = $metaFile->content();
        if (empty($metaData) || empty($metaData['datasets'])) $metaData = ['datasets'=>[$jsonFilename=>$name]];
        else {
            $metaData['datasets'][$jsonFilename] = $name;
        }
        $metaFile->content($metaData);
        $metaFile->save();
    }*/

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