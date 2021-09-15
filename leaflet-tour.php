<?php
namespace Grav\Plugin;

// TODO: better commenting
// TODO: comment shortcode files

// TODO: Is this necessary?
require_once __DIR__ . '/classes/LeafletTour.php';

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Data\Data;
use Grav\Common\Page\Header;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\MarkdownFile;
use Grav\Plugin\LeafletTour\Dataset;
use Grav\Plugin\LeafletTour\LeafletTour;
use Grav\Plugin\LeafletTour\Utils;
use Grav\Plugin\LeafletTour\Feature;

/**
 * Class LeafletTourPlugin
 * This is the main plugin class. It handles events and functions called by admin panel config pages.
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
            $originalFiles = $this->config->get('plugins.leaflet-tour.data_files') ?? [];
            $newFiles = 0;
            foreach ($obj->get('data_files') ?? [] as $key=>$fileData) {
                if (!($originalFiles)[$key]) {
                    $newFiles++;
                    // new upload - save as json (if possible)
                    $jsonData = Utils::parseDatasetUpload($fileData);
                    if (!empty($jsonData)) Dataset::createNewDataset($jsonData[0], $jsonData[1]);
                }
            }
            // check for deleted data files
            $obj->set('filecount', count($obj->get('data_files') ?? []));
            if ($obj->get('filecount') < (($this->config->get('plugins.leaflet-tour.filecount') ?? 0)+$newFiles)) {
                Utils::deleteDatasets($obj->get('data_files') ?? []);
            }
            // check for updates
            if (!empty($obj->get('update.file'))) $obj->merge(['update'=>Utils::handleDatasetUpdate($obj->get('update'), $this->config->get('plugins.leaflet-tour.update') ?? [])]);
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
            $header = Utils::filter_header($header);
        }
        // handle view config data
        else if (method_exists($obj, 'template') && $obj->template() === 'modular/view') {
            // update shortcodes_list as needed
            $header = $obj->header();
            if ($header->get('features') != $obj->getOriginal()->header()->get('features')) {
                $header->set('shortcodes_list', Utils::generateShortcodeList($header->get('features') ?? []));
            }
            $header = Utils::filter_header($header);
        }
        // handle dataset config data
        else if (method_exists($obj, 'template') && $obj->template() === 'dataset') {
            $header = Dataset::getDatasets()[$obj->header()->get('dataset_file')]->updateDataset($obj->header());
            $header = Utils::filter_header($header);
            $obj->header($header);
        }
    }

    public function onShortcodeHandlers() {
        $this->grav['shortcode']->registerAllShortcodes(__DIR__.'/shortcodes');
    }

    // yaml functions
    
    // TODO: temp - see if possible to call directly
    
    public static function getDatasetFiles(): array {
        return Dataset::getDatasetList();
    }

    // TODO: Make this a parameter for the above function, instead
    public static function getDatasetFilesNone(): array {
        return array_merge(['none'=>'None'], Dataset::getDatasetList());
    }
    
    // only call directly from tour.yaml
    public static function getTourFeatures($onlyPoints=false, $fromView=false): array {
        $featureList = [];
        if ($fromView) $file = MarkdownFile::instance(Utils::getTourRouteFromViewConfig());
        else $file = MarkdownFile::instance(Utils::getTourRouteFromTourConfig());
        if ($file->exists()) {
            $data = new Data((array)$file->header());
            $extraFeatures = [];
            foreach ($data->get('datasets') ?? [] as $index=>$tour_dataset) {
                $dataset = Dataset::getDatasets()[$tour_dataset['file']];
                // handle deleted dataset
                if ($dataset === null) {
                    $datasets = $data->get('datasets');
                    unset($datasets[$index]);
                    $data->set('datasets', $datasets);
                    $file->header($data->toArray());
                    $file->save();
                    continue;
                }
                if ($fromView && !$onlyPoints) {
                    // only add all features if show all
                    if ($tour_dataset['show_all']) $featureList = array_merge($featureList, Feature::buildConfigList($dataset->getFeatures(), $dataset->getNameProperty()));
                    else $extraFeatures = array_merge($extraFeatures, $dataset->getFeatures());
                }
                else if (!$onlyPoints || $dataset->getFeatureType()==='Point') {
                    $featureList = array_merge($featureList, Feature::buildConfigList($dataset->getFeatures(), $dataset->getNameProperty()));
                }
            }
            if ($onlyPoints) {
                // list of point locations are for start location - should have a default null value
                $featureList = array('default' => 'None') + $featureList;
            }
            if ($fromView && !$onlyPoints) {
                // add any features from datasets without show all
                foreach ($data->get('features') ?? [] as $feature) {
                    if (empty($featureList[$feature['id']])) $featureList[$feature['id']] = $extraFeatures[$feature['id']]->getName();
                }
            }
        }
        return $featureList;
    }

    // only call from view.yaml
    public static function getTourFeaturesForView($onlyPoints=false): array {
        return self::getTourFeatures($onlyPoints, true);
    }

    // only call from tour.yaml
    public static function getTourLocations(): array {
        return self::getTourFeatures(true);
    }

    // only call from view.yaml
    public static function getTourLocationsForView(): array {
        return self::getTourFeaturesForView(true);
    }

    // returns list of basemap files from the plugin config instead of from the uploads folder
    public static function getBasemaps(): array {
        $basemaps = Grav::instance()['config']->get('plugins.leaflet-tour')['basemaps'];
        if (!empty($basemaps)) return array_column($basemaps, 'file', 'file');
        else return [];
    }

    // list of properties for the dataset associated with the dataset config file
    // only call from dataset.yaml
    public static function getPropertyList(): array {
        $file = MarkdownFile::instance(Utils::getDatasetRoute());
        if ($file->exists()) {
            $dataset = Dataset::getDatasets()[((array)$file->header())['dataset_file']];
            return $dataset->getProperties();
        }
        return [];
    }
    
    // list of properties from all datasets for convenience when updating a dataset
    public static function getAllPropertiesList(): array {
        $props = ['tour_none'=>'None', 'tour_coords'=>'Coordinates (not a property)'];
        foreach (Dataset::getDatasets() as $dataset) {
            $props = array_merge($props, $dataset->getProperties());
        }
        return $props;
    }

    public static function getTileServers($key = null) {
        if (empty($key)) {
            $servers = ['none'=>'None'];
            foreach (Utils::TILE_SERVERS as $key=>$tileserver) {
                $servers[$key] = $tileserver['select'];
            }
            return $servers;
        } else {
            return Utils::TILE_SERVERS[$key];
        }
    }
}
