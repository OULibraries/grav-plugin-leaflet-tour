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
                'onAdminSave' => ['onAdminSave', 0]
            ]);
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            //
        ]);
    }
    
    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths() {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onTwigSiteVariables() {
        $this->grav['twig']->twig_vars['leafletTour'] = new LeafletTour($this->config->get('themes.qgis-2-leaflet'));
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
        if (method_exists($obj, 'get') && $obj->get('leaflet-tour')) {
            // TODO: if statement first?
            // check for new data files
            foreach ($obj->get('data_files') as $key=>$data) {
                // TODO: temporarily always true for testing
                if (!$this->config->get('plugins.leaflet-tour.data_files') || true) {
                    // new upload - save as json (if possible)
                    Datasets::createDataset($data);
                    // TODO: add to datasets list?
                }
            }
        } else if (method_exists($obj, 'template') && $obj->template() === 'tour') {
            // handle fieldsets inside lists
            $header = $obj->header();
            $datasets = [];
            if (!empty($header->get('datasets'))) {
                foreach ($header->get('datasets') as $dataset) {
                    $data = $dataset;
                    if ($dataset['icon'] && $dataset['icon']['icon']) {
                        $data['icon'] = $dataset['icon']['icon'];
                    }
                    if ($dataset['legend'] && $dataset['legend']['legend']) {
                        $data['legend'] = $dataset['legend']['legend'];
                    }
                    $datasets[] = $data;
                }
                $header->set('datasets', $datasets);
            }
        }
    }
    
    public static function getDatasets() {
        return Datasets::getDatasets();
    }
    
    // takes an array of folder/page names and returns a route
    public static function getPageRoute($keys) {
        $route = Grav::instance()['locator']->findResource('page://').'/';
        for ($i = 0; $i < count($keys); $i++) {
            $glob = glob($route.$keys[$i].'*');
            if (count($glob) === 0) {
                $glob = glob($route.'??.'.$keys[$i]);
                if (count($glob) === 0) {
                    // last ditch effort
                    $glob = glob($route.'*.'.$keys[$i]);
                    if (count($glob) === 0) return [];
                }
            }
            $route = $glob[0].'/';
        }
        return $route;
    }
    
    public function getTourLocations() {
        // which tour are we on?
        $key = Grav::instance()['page']->header()->controller['key']; // get page route (sort of)
        $keys = explode("/", $key); // break key into sub-components
        $route = self::getPageRoute($keys).'tour.md';
        
        $file = MarkdownFile::instance($route);
        if ($file->exists()) {
            // get list of datasets
            $data = new Data((array)$file->header());
            $datasets = $data->get('datasets');
            if (empty($datasets)) return [];
            $location_list = [];
            foreach ($datasets as $dataset) {
                $json_name = $dataset['file'];
                $locations = Datasets::instance()->datasets[$json_name]->getLocationList();
                if (!empty($locations)) $location_list = array_merge($location_list, $locations);
            }
            return $location_list;
        } else {
            return [];
        }
    }
    
    // TODO: add param to allow ignoring show_all (for view center location)
    public static function getTourLocationsForView() {
        // which tour are we on?
        $keys = explode("/", Grav::instance()['page']->header()->controller['key']);
        array_pop($keys); // last element is view folder, which we don't want
        if (count($keys) > 0) $route = self::getPageRoute($keys).'tour.md';
        else return [];
        
        $file = MarkdownFile::instance($route);
        if ($file->exists()) {
            $data = new Data((array)$file->header());
            // get list of datasets - check for show all
            $datasets = $data->get('datasets');
            if (empty($datasets)) return [];
            $location_list = [];
            foreach ($datasets as $dataset) {
                // TODO: Check for show all - only include all locations if show all
                $json_name = $dataset['file'];
                $locations = Datasets::instance()->datasets[$json_name]->getLocationList();
                if (!empty($locations)) $location_list = array_merge($location_list, $locations);
            }
            // TODO: Loop through any locations in header.locations and add them if they are not already in the location list - or do something in case show_all wasn't checked - might be more sensible to do this in the for loop...
            return $location_list;
        } else {
            return [];
        }
    }
}
