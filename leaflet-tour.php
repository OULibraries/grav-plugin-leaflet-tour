<?php
namespace Grav\Plugin;

// TODO: Is this necessary?
//require_once __DIR__ . '/classes/Dataset.php';
require_once __DIR__ . '/classes/Datasets.php';
require_once __DIR__ . '/classes/LeafletTour.php';

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Data\Data;
use Grav\Common\Config\Config;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\MarkdownFile;
//use Grav\Plugin\LeafletTour\Dataset;
use Grav\Plugin\LeafletTour\Datasets;
use Grav\Plugin\LeafletTour\LeafletTour;

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
        //self::testSaveFile();
        $obj = $event['object'];
        // handle plugin config data
        if (method_exists($obj, 'get') && $obj->get('leaflet_tour')) {
            // TODO: if statement first?
            // check for new data files
            foreach ($obj->get('data_files') as $key=>$data) {
                // TODO: temporarily always true for testing. Will ultimately need to check if the particular file has been uploaded before, and perhaps if so if it is being updated (future goals?)
                if (!$this->config->get('plugins.leaflet-tour.data_files') || true) {
                    // new upload - save as json (if possible)
                    Datasets::instance()->parseDatasetUpload($data);
                }
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
        }
        // handle view config data
        else if (method_exists($obj, 'template') && $obj->template() === 'modular/view') {
            // update shortcodes_list as needed
            if ($obj->header()->get('features') != $obj->getOriginal()->header()->get('features')) {
                $features = array_fill_keys(array_column($obj->header()->get('features'), 'id'), '');
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
                $obj->header()->set('shortcodes_list', implode("\r\n", $shortcodeList));
            }
        }
        // handle dataset config data
        else if (method_exists($obj, 'template') && $obj->template() === 'dataset') {
            $header = $obj->header();
            $original = $obj->getOriginal()->header();
            $datasetFileRoute = self::getDatasetRoute();
            if ($header->get('title') !== $original->get('title')) Datasets::instance()->updateMetadata($header->get('dataset_file'), $header->get('title'));
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
            Datasets::instance()->getDatasets()[$header->get('dataset_file')]->updateDataset($datasetFileRoute, $name ?? null, $nameProperty ?? null);
        }
    }

    public function onShortcodeHandlers() {
        $this->grav['shortcode']->registerAllShortcodes(__DIR__.'/shortcodes');
    }
    
    public static function getDatasetFiles() {
        return Datasets::getDatasetFiles();
    }
    
    // takes an array of folder/page names and returns a route
    public static function getPageRoute($keys) {
        $route = Grav::instance()['locator']->findResource('page://').'/';
        for ($i = 0; $i < count($keys); $i++) {
            $glob = glob($route.'*');
            $match = '';
            foreach ($glob as $item) {
                if (strtolower($item) === $route.$keys[$i]) {
                    $match = $item;
                    continue;
                }
            }
            // check for folder numeric prefix
            if (empty($match)) {
                foreach ($glob as $item) {
                    if (preg_match('/^[0-9][0-9]\.'.$keys[$i].'$/', strtolower(str_replace($route, '', $item)))) {
                        $match = $item;
                        continue;
                    }
                }
            }
            // last ditch effort
            if (empty($match)) {
                foreach ($glob as $item) {
                    if (preg_match('/^..\.'.$keys[$i].'$/', strtolower(str_replace($route, '', $item)))) {
                        $match = $item;
                        continue;
                    }
                }
            }
            if (empty($match)) return implode(',', $glob);
            $route = $match.'/';
        }
        return $route;
    }

    public static function getTourFromTour() {
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
    }
    
    // only return points, have separate for all features
    public static function getTourFeatures($onlyPoints=false) {
        $data = self::getTourFromTour();
        if (!$data) return [];
        // get list of datasets
        $datasets = $data->get('datasets');
        if (empty($datasets)) return [];
        $featureList = [];
        foreach ($datasets as $dataset) {
            $dataset = Datasets::instance()->getDatasets()[$dataset['file']];
            if ($onlyPoints) $features = $dataset->getPointList();
            else $features = $dataset->getFeatureList();
            if (!empty($features)) $featureList = array_merge($featureList, $features);
            else $featureList = [];
        }
        if ($onlyPoints) {
            // list of point locations are for start location - should have a default null value
            $featureList = array('default' => 'None') + $featureList;
        }
        return $featureList;
    }

    public static function getTourFeaturesForView($onlyPoints=false) {
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
        else return ['empty'];
    }

    // list of properties for the dataset associated with the dataset config file
    public static function getPropertyList() {
        $route = self::getDatasetRoute();
        $file = MarkdownFile::instance($route);
        if (!$file->exists()) return [];
        // get dataset
        $dataset = Datasets::instance()->getDatasets()[((array)$file->header())['dataset_file']];
        return array_combine($dataset->properties, $dataset->properties);
    }
}
