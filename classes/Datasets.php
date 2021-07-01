<?php
namespace Grav\Plugin\LeafletTour;

// TODO: Is this necessary?
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
    
    public $datasets;
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
        $datasets = [];
        foreach(array_keys(self::getDatasets()) as $json_name) {
            $datasets[$json_name] = new Dataset($json_name, $this->config);
        }
        $this->datasets = $datasets;
    }

    /**
     * TODO: Ensure the json file has all required characteristics
     * TODO: ensure type is point? ensure geometry has lat and long with appropriate values?
     * @return bool
     */
    protected function validateJson($data) {
        if ($data->get('type') !== 'FeatureCollection') return false;
        if (empty($data->get('name'))) return false;
        if (empty($data->get('features'))) return false;
        if (empty($data->get('features.0.properties'))) return false;
        if (empty($data->get('features.0.geometry'))) return false;
        return true;
    }
    
    // static methods
    
    /**
     * Handle a file upload and create a new dataset
     * 
     * @param array $file - the yaml for the uploaded file
     * @return Dataset
     */
    public static function createDataset($file) {
        // check file type and pass to appropriate handler
        if ($file['type'] === 'text/javascript') {
            $json_data = self::readJSFile($file['name']);
        }
        // TODO: validate json
        $name = $json_data['name'];
        if ($name) {
            try {
                $json_file = CompiledJsonFile::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/'.$name.'.json');
                $json_file->content($json_data);
                $json_file->save();
                // update metadata
                $meta_file = CompiledYamlFile::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/meta.yaml');
                $content = $meta_file->content();
                $filename = $name.'.json';
                if (empty($content) || empty($content['datasets'])) $content = ['datasets'=>[$filename=>'']];
                else {
                    if ($content['datasets'][$filename]) return;
                    $content['datasets'][$filename] = '';
                }
                $meta_file->content($content);
                $meta_file->save();
                // TODO: add to $this->datasets?
                // TODO: Return statement?
            } catch (Exception $e) {
                return; // TODO: Return null?
            }
        }
    }
    
    /**
     * Turn Qgis2Web .js file into .json
     * 
     * @return array/null
     */
    protected static function readJSFile($filename) {
        $file = File::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/uploads/'.$filename);
        // find and remove the initial json variable
        $count = 0;
        $str = preg_replace('/^(.)*var(\s)+json_(\w)*(\s)+=(\s)+/', '', $file->content(), 1, $count);
        // if a match was found (and removed), try converting the file contents to json
        if ($count == 1) {
            // fix php's bad json handling
            if (version_compare(phpversion(), '7.1', '>=')) {
            	ini_set( 'serialize_precision', -1 );
            }
            try {
                $json_data = json_decode($str, true);
                return $json_data;
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
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

    // returns a list of all datasets included in the yaml meta file
    public static function getDatasets() {
        $file = CompiledYamlFile::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/meta.yaml');
        $content = $file->content();
        if (empty($content) || empty($content['datasets'])) return [];
        else {
            $datasets = [];
            foreach (array_keys($content['datasets']) as $dataset) {
                $datasets[$dataset] = preg_replace('/\.json$/', '', $dataset);
            }
            return $datasets;
        }
    }
    
    public static function getDatasetRoute($json_name) {
        $file = CompiledYamlFile::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/meta.yaml');
        $content = $file->content();
        if (empty($content) || empty($content['datasets'])) return '';
        return $content['datasets'][$json_name];
    }
    
}