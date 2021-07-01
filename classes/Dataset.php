<?php
namespace Grav\Plugin\LeafletTour;

// TODO: Is this necessary?
require_once __DIR__ . '/Datasets.php';

use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledJsonFile;
use RocketTheme\Toolbox\File\MarkdownFile;
use Grav\Plugin\LeafletTour\Datasets;

class Dataset {
    
    // TODO: Use a getter instead of making all these variables public
    public $name;
    public $json_name;
    protected $dataset_route;
    protected $properties; // list of all properties available in the dataset
    public $locations; // list of all locations from json file (array) (new id as key)
    public $location_info; // location info from dataset file (associative array)
    protected $location_list; // array of id => name (dataset)

    public $name_prop; // name property
    public $legend_text; // text for the legend description
    public $legend_alt;
    public $icon_file; // filename for the default icon
    public $icon_settings; // list of settings for default icon
    public $has_popup; // TODO: Set this from the dataset file
    
    protected $popup_props; // list of all properties to automatically add to popups
    //protected $grav;
    protected $config;
    
    function __construct($json_name, $config) {
        $this->config = $config;
        try {
            $file = CompiledJsonFile::instance(Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/'.$json_name);
            $this->json_name = $json_name;
            $data = new Data($file->content());
            $this->name = $data->get('name');
            $this->properties = array_keys((array)($data->get('features.0.properties')));
            //$this->locations = (array)($data->get('features'));
            $locations = [];
            $count = 0;
            foreach (((array)($data->get('features'))) as $loc) {
                $locations[$this->name.'_'.$count] = $loc;
                $count++;
            }
            $this->locations = $locations;
            
            // check for dataset file
            $route = Datasets::getDatasetRoute($json_name);
            if ($route) {
                $this->addDatasetFile($route);
            }
            else {
                // check if name and id properties are set in plugin config
                $datasets = $config->get('datasets');
                if (empty($datasets)) return;
                foreach ($datasets as $dataset) {
                    if ($dataset['file'] === $json_name) {
                        $this->name_prop = $dataset['name_prop'];
                    }
                }
            }
            // Check if id and name property are set. If not, set sensible defaults
            $name = $this->name_prop;
            if (empty($name)) {
                // set sensible defaults based on available properties
                foreach ($this->properties as $prop) {
                    //if (strcasecmp($prop, 'id') == 0) $id = $prop;
                    if (strcasecmp($prop, 'name') == 0) $name = $prop;
                    //else if (empty($id) && preg_match('/^(.*id|id.*)$/i', $prop)) $id = $prop;
                    else if (empty($name) && preg_match('/^(.*name|name.*)$/i', $prop)) $name = $prop;
                }
                // deal with no matches found
                if (empty($name)) $name = $this->properties[0];
                $this->name_prop = $name;
            }
        } catch (Exception $e) {
            // TODO: Error handling?
        }
    }
    
    protected function buildLocationList() {
        $locations = [];
        foreach ($this->locations as $id => $loc) {
            $name = $loc['properties'][$this->name_prop];
            if (!empty($name)) $locations[$id] = $name.' ('.$this->name.')';
        }
        $this->location_list = $locations;
    }
    
    public function addDatasetFile($route, $update=false) {
        try {
            $file = MarkdownFile::instance($route);
            $this->dataset_route = $route;
            $data = new Data((array)($file->header()));
            
            if (!empty($data->get('name_prop'))) $this->name_prop = $data->get('name_prop');
            $this->legend_text = $data->get('legend.text');
            $this->legend_alt = $data->get('legend.alt');
            $this->icon_file = $data->get('icon.file');
            $this->popup_props = $data->get('popup_props');
            $this->has_popup = (!empty($data->get('popup_content')));
            if ($this->icon_file) $this->icon_settings = $data->get('icon');
            else $this->icon_settings = [];
            
            if (!$update) {
                if (!empty($data->get('locations'))) $this->location_info = array_column($data->get('locations'), null, 'id');
            } else {
                // TODO: update locations instead
            }
        } catch (Exception $e) {
            // TODO: eror handling?
        }
    }
    
    public function getLocationList() {
        if (empty($this->location_list)) $this->buildLocationList();
        return $this->location_list;
    }
        
    // return array with legend/icon alt text, icon options, id attribute, and name attribute
    public function getDatasetInfo() {
        $info = [
            ['name' => $this->name],
            ['nameProp' => $this->name_prop],
            ['iconAlt' => $this->icon_alt ?: $this->legend_alt ?: $this->legend_text],
            ['name' => $this->name]
        ];
        if ($this->icon_file) $info['iconOptions'] = $this->icon_settings;
        return $info;
    }
}

?>