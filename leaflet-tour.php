<?php
namespace Grav\Plugin;

// TODO: Is this necessary?
//require_once __DIR__ . '/classes/Dataset.php';
require_once __DIR__ . '/classes/LeafletTour.php';

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Data\Data;
use Grav\Common\Config\Config;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\MarkdownFile;
use Grav\Plugin\LeafletTour\Dataset;
use Grav\Plugin\LeafletTour\LeafletTour;
use Grav\Plugin\LeafletTour\Utils;
use Grav\Plugin\LeafletTour\Feature;

/**
 * Class LeafletTourPlugin
 * @package Grav\Plugin
 */
class LeafletTourPlugin extends Plugin
{
    
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0]
            ],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
        ];
    }

    /**
     * Composer autoload
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        // Admin panel events
        if ($this->isAdmin()) {
            $this->enable([
                'onGetPageTemplates' => ['onGetPageTemplates', 0],
                'onGetPageBlueprints' => ['onGetPageBlueprints', 0],
                'onAdminSave' => ['onAdminSave', 0],
                'onShortcodeHandlers' => ['onShortcodeHandlers', 0] // TODO: which
            ]);
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0]
        ]);
    }
    
    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths() {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onTwigSiteVariables() {
        $this->grav['twig']->twig_vars['leafletTour'] = new LeafletTour($this->config->get('plugins.leaflet-tour'));
    }
    
    /**
     * Add templates to admin panel template list
     */
    public function onGetPageTemplates(Event $event) {
        $types = $event->types;
        $types->register('tour');
    }
    
    /**
     * Add blueprints to admin panel page editor
     */
    public function onGetPageBlueprints(Event $event) {
        $types = $event->types;
        $types->scanBlueprints(__DIR__ . '/blueprints');
    }

    /**
     * Handle page and plugin metadata on save
     */
    public function onAdminSave(Event $event) {
        $obj = $event['object'];
        // handle plugin config data
        if (method_exists($obj, 'get') && $obj->get('leaflet_tour')) {
            // check for new data files
            $update = $this->config->get('plugins.leaflet-tour.data_update');
            foreach ($obj->get('data_files') ?? [] as $key=>$fileData) {
                // TODO: temporarily always true for testing. Will ultimately need to check if the particular file has been uploaded before, and perhaps if so if it is being updated (future goals?)
                if ($update || !($this->config->get('plugins.leaflet-tour.data_files') ?? [])[$key]) {
                    // new upload - save as json (if possible)
                    $jsonData = Utils::parseDatasetUpload($fileData);
                    if (!empty($jsonData)) Dataset::createNewDataset($jsonData[0], $jsonData[1]);
                }
                // make sure update never stays checked
                $obj->set('update', false);
            }
        }
        // handle tour config data
        else if (method_exists($obj, 'template') && $obj->template() === 'tour') {
            // handle fieldsets inside lists
            $header = $obj->header();
            $datasets = [];
            if (!empty($header->get('datasets'))) {
                foreach ($header->get('datasets') as $dataset) {
                    $data = $dataset;
                    if ($dataset['icon'] && $dataset['icon']['icon']) {
                        $data['icon'] = $dataset['icon']['icon'];
                    }
                    $datasets[] = $data;
                }
                $header->set('datasets', $datasets);
            }
            // handle popups page
            Utils::createPopupsPage($header->get('title'));
        }
        // handle view config data
        else if (method_exists($obj, 'template') && $obj->template() === 'modular/view') {
            // update shortcodes_list as needed
            if ($obj->header()->get('features') != $obj->getOriginal()->header()->get('features')) {
                /*$features = array_fill_keys(array_column($obj->header()->get('features'), 'id'), '');
                // loop through datasets to get all the needed information
                foreach (Datasets::instance()->getDatasets() as $dataset) {
                    foreach (array_keys($features) as $id) {
                        $feature = $dataset->features[$id];
                        if ($feature) {
                            $features[$id] = $feature['customName'] ?? $feature['properties'][$dataset->nameProperty];
                        }
                    }
                }
                // loop through all view features to generate shortcodes
                $shortcodeList = [];
                foreach ($features as $id=>$name) {
                    if (!empty($name)) {
                        $shortcodeList[] = '[view-popup id="'.$id.'"]'.$name.'[/view-popup]';
                    }
                }
                $obj->header()->set('shortcodes_list', implode("\r\n", $shortcodeList));*/
                $obj->header()->set('shortcodes_list', Utils::generateShortcodeList($obj->header()->get('features'), Dataset::getDatasets()));
            }
        }
        // handle dataset config data
        else if (method_exists($obj, 'template') && $obj->template() === 'dataset') {
            $datasetFileRoute = Utils::getDatasetRoute();
            $header = Dataset::getDatasets()[$obj->header()->get('dataset_file')]->updateDataset($obj->header(), Utils::getDatasetRoute());
            $obj->header($header);

            /**$original = $obj->getOriginal()->header();
            $datasetFileRoute = self::getDatasetRoute();

            $headerFeatures = Datasets::instance()->getDatasets()[$header->get('dataset_file')]->updateDataset($header, $datasetFileRoute, $original->get('features'));
            $header->set('features', $headerFeatures);

            // reconcile features
            if (count($header->get('features') ?? []) < count($original->get('features') ?? [])) {
                if (empty($header->get('features'))) $header->set('features', $original->get('features'));
                else {
                    $features = $header->get('features');
                    $featureIds = array_column($features, 'id');
                    foreach ($original->get('features') as $feature) {
                        if (!in_array($feature['id'], $featureIds)) {
                            $features[] = $feature;
                        }
                    }
                    $header->set('features', $features);
                }
            }
            // update dataset
            Datasets::instance()->getDatasets()[$header->get('dataset_file')]->updateDataset($datasetFileRoute, $name ?? null, $nameProperty ?? null);*/
        }
    }

    public function onShortcodeHandlers() {
        $this->grav['shortcode']->registerAllShortcodes(__DIR__.'/shortcodes');
    }

    // yaml functions
    
    // TODO: temp - see if possible to call directly
    
    public static function getDatasetFiles() {
        return Dataset::getDatasetList();
    }

    /*public static function getTourFromTour() {
        $key = Grav::instance()['page']->header()->controller['key']; // current page - the reason why this function only works when called from tour.yaml
        $keys = explode("/", $key); // break key into sub-components
        $route = self::getPageRoute($keys).'tour.md';
        $file = MarkdownFile::instance($route);
        if ($file->exists()) return new Data((array)$file->header());
        else return null;
    }

    public static function getDatasetRoute() {
        $key = Grav::instance()['page']->header()->controller['key']; // current page - the reason why this function only works when called from dataset.yaml
        $keys = explode("/", $key); // break key into sub-components
        return self::getPageRoute($keys).'dataset.md';
    }

    public static function getTourFromView() {
        $keys = explode("/", Grav::instance()['page']->header()->controller['key']); // current page - the reason why this function only works when called from view.yaml
        array_pop($keys); // last element is view folder, which we don't want
        if (count($keys) > 0) {
            $file = MarkdownFile::instance(self::getPageRoute($keys).'tour.md');
            if ($file->exists()) return new Data((array)$file->header());
        }
        return null;
    }*/
    
    // only return points, have separate for all features
    public static function getTourFeatures($onlyPoints=false, $fromView=false) {
        $featureList = [];
        if ($fromView) $file = MarkdownFile::instance(Utils::getTourRouteFromViewConfig());
        else $file = MarkdownFile::instance(Utils::getTourRouteFromTourConfig());
        if ($file->exists()) {
            $data = new Data((array)$file->header());
            foreach ($data->get('datasets') ?? [] as $dataset) {
                $dataset = Dataset::getDatasets()[$dataset['file']];
                if (!$onlyPoints || $dataset->getFeatureType()==='Point') {
                    $featureList = array_merge($featureList, Feature::buildConfigList($dataset->getFeatures(), $dataset->getNameProperty()));
                }
            }
            if ($onlyPoints) {
                // list of point locations are for start location - should have a default null value
                $featureList = array('default' => 'None') + $featureList;
            }
        }
        return $featureList;
    }

    /*public static function getTourFeaturesForView($onlyPoints=false) {
        $featureList = [];
        $file = MarkdownFile::instance(Utils::getTourRouteFromViewConfig());
        if ($file->exists()) {
            $data = new Data((array)$file->header());
        }
        $data = self::getTourFromView();
        if (!$data) return [];
        // get list of datasets - check for show all
        $datasets = $data->get('datasets');
        if (empty($datasets)) return [];
        $featureList = [];
        foreach ($datasets as $dataset) {
            $dataset = Datasets::instance()->getDatasets()[$dataset['file']];
            if ($onlyPoints) {
                $features = $dataset->getPointList();
            } else {
                $features = $dataset->getFeatureList();
                // TODO: check for show all - if not show all, only include features that are included in the $data->get('features')
            }
            if (!empty($features)) $featureList = array_merge($featureList, $features);
        }
        if ($onlyPoints) {
            // list of point locations are for start location - should have a default null value
            $featureList = array('default' => 'None') + $featureList;
        }
        return $featureList;
    }*/
    public static function getTourFeaturesForView($onlyPoints=false) {
        return self::getTourFeatures($onlyPoints, true);
    }

    public static function getTourLocations() {
        return self::getTourFeatures(true);
    }

    public static function getTourLocationsForView() {
        return self::getTourFeaturesForView(true);
    }

    // returns list of basemap files from the plugin config instead of from the uploads folder
    public static function getBasemaps() {
        $basemaps = Grav::instance()['config']->get('plugins.leaflet-tour')['basemaps'];
        if (!empty($basemaps)) return array_column($basemaps, 'file', 'file');
        else return [];
    }

    // list of properties for the dataset associated with the dataset config file
    public static function getPropertyList() {
        $file = MarkdownFile::instance(Utils::getDatasetRoute());
        if ($file->exists()) {
            $dataset = Dataset::getDatasets()[((array)$file->header())['dataset_file']];
            return $dataset->getProperties();
        }
        return [];
    }

    public static function getTileServers($key = null) {
        if (empty($key)) {
            $servers = ['none'=>'None'];
            foreach (Utils::TILESERVERS as $key=>$tileserver) {
                $servers[$key] = $tileserver['select'];
            }
            return $servers;
        } else {
            return Utils::TILESERVERS[$key];
        }
    }
}
