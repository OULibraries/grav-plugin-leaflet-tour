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
     * 
     * Only some of the subscribed events can be added here. The others are in onPluginsInitialized.
     * See the corresponding functions for what they do.
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
     * Initialize the plugin - subscribe to more events
     */
    public function onPluginsInitialized(): void
    {
        // Admin panel events
        if ($this->isAdmin()) {
            $this->enable([
                'onGetPageTemplates' => ['onGetPageTemplates', 0],
                'onGetPageBlueprints' => ['onGetPageBlueprints', 0],
                'onAdminSave' => ['onAdminSave', 0],
                //'onShortcodeHandlers' => ['onShortcodeHandlers', 0] // shouldn't be necessary
            ]);
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0]
        ]);
    }
    
    /**
     * Add the plugin's templates folder to the twig lookup paths so that any templates there will be included.
     */
    public function onTwigTemplatePaths() {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Add a LeafletTour object as a variable that can be accessed via twig (necessary for calling certain functions from templates).
     */
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
        $obj = $event['object']; // the object with all of the data being saved
        // handle plugin config data - the leaflet_tour plugin will always have the line 'leaflet_tour: true'
        if (method_exists($obj, 'get') && $obj->get('leaflet_tour')) {
            // check for new data files
            $originalFiles = $this->config->get('plugins.leaflet-tour.data_files') ?? []; // get former list - the save has not happened, so it is possible to access the existing config
            $newFiles = 0; // keep track of how many new files there are (this comes in handy later)
            // llop through all data files in the config we are saving to search for any that are new (i.e. for any that do not exist in the old set of files)
            foreach ($obj->get('data_files') ?? [] as $key=>$fileData) {
                if (!($originalFiles)[$key]) {
                    $newFiles++;
                    // new upload - save as json and create a dataset (if possible)
                    $jsonData = Utils::parseDatasetUpload($fileData);
                    if (!empty($jsonData)) Dataset::createNewDataset($jsonData[0], $jsonData[1]);
                }
            }
            // check for deleted data files - requires storing a filecount variable
            $obj->set('filecount', count($obj->get('data_files') ?? []));
            if ($obj->get('filecount') < (($this->config->get('plugins.leaflet-tour.filecount') ?? 0)+$newFiles)) {
                Utils::deleteDatasets($obj->get('data_files') ?? []);
            }
            // check for updates
            if (!empty($obj->get('update.file'))) $obj->merge(['update'=>Utils::handleDatasetUpdate($obj->get('update'), $this->config->get('plugins.leaflet-tour.update') ?? [])]);
        }
        // handle tour config data - object must have the template method and that method must return 'tour'
        else if (method_exists($obj, 'template') && $obj->template() === 'tour') {
            // handle fieldsets inside lists
            $header = $obj->header();
            /*$datasets = [];
            if (!empty($header->get('datasets'))) {
                foreach ($header->get('datasets') as $dataset) {
                    $data = $dataset;
                    if ($dataset['icon'] && $dataset['icon']['icon']) {
                        $data['icon'] = $dataset['icon']['icon'];
                    }
                    $datasets[] = $data;
                }
                $header->set('datasets', $datasets);
            }*/
            // handle popups page and make sure that header is filtered
            Utils::createPopupsPage($header->get('title'));
            $header = Utils::filter_header($header);
        }
        // handle view config data
        else if (method_exists($obj, 'template') && $obj->template() === 'modular/view') {
            // update shortcodes_list as needed and make sure header is filtered
            $header = $obj->header();
            if ($header->get('features') != $obj->getOriginal()->header()->get('features')) {
                $header->set('shortcodes_list', Utils::generateShortcodeList($header->get('features') ?? []));
            }
            $header = Utils::filter_header($header);
        }
        // handle dataset config data
        else if (method_exists($obj, 'template') && $obj->template() === 'dataset') {
            // make sure json file is updated as needed and that header is filtered
            $header = Dataset::getDatasets()[$obj->header()->get('dataset_file')]->updateDataset($obj->header());
            $header = Utils::filter_header($header);
            $obj->header($header);
        }
    }

    /**
     * Add new shortcodes (from shortcodes directory)
     */
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
    /**
     * A function to get a list of all features in a tour, with several important parameters determining which features are included.
     * Warning! if $fromView is false, do not call this function anywhere except directly from tour.yaml
     * Warning! if $fromView is true, do not call this function anywhere except directly from view.yaml
     * The above two warnings can be ignored if a proper $fileRoute is provided.
     * 
     * @param bool $onlyPoints - Set to true when generating list of features that can be used as the start location center. Only point features will be returned (ignores all LineString, MultiLineString, Polygon, and MultiPolygon features)
     * @param bool $fromView - Determines the file route that will be used to access the tour (set to true when calling this function from view.yaml, set to false when calling from tour.yaml). Also, when $onlyPoints is false, ensures that only features properly included in the tour are shown - that is, if a dataset has show_all=false, then only features specifically listed in the tour features list will be returned for that dataset.
     * @param string $fileRoute - Provide this if you want to call this from somewhere other than tour.yaml or view.yaml, such as for testing purposes.
     */
    public static function getTourFeatures(bool $onlyPoints=false, bool $fromView=false, string $fileRoute=null): array {
        $featureList = []; // empty list of features to return
        // if no file route has been provided (the default), then determine the file route based on whether we are calling this from the tour or view config
        if (!$fileRoute) {
            if ($fromView) $fileRoute = Utils::getTourRouteFromViewConfig();
            else $fileRoute = Utils::getTourRouteFromTourConfig();
        }
        // get the tour file - required for everything else
        $file = MarkdownFile::instance($fileRoute);
        if ($file->exists()) {
            $data = new Data((array)$file->header());
            $extraFeatures = [];
            // loop through each dataset included in the tour
            foreach ($data->get('datasets') ?? [] as $index=>$tour_dataset) {
                $dataset = Dataset::getDatasets()[$tour_dataset['file']]; // get the actual Dataset object
                // handle deleted dataset by unsetting it from the tour config and saving (prevents exceptions from being thrown, instead)
                if ($dataset === null) {
                    $datasets = $data->get('datasets');
                    unset($datasets[$index]);
                    $data->set('datasets', $datasets);
                    $file->header($data->toArray());
                    $file->save();
                    continue;
                }
                // For features that can be included in the view features list, only features that are going to be _shown_ in the view should be included. If dataset has show_all, then all features can be added. Otherwise, the features will be stored in a temporary array.
                if ($fromView && !$onlyPoints) {
                    // only add all features if show all
                    if ($tour_dataset['show_all']) $featureList = array_merge($featureList, Feature::buildConfigList($dataset->getFeatures(), $dataset->getNameProperty()));
                    else $extraFeatures = array_merge($extraFeatures, $dataset->getFeatures());
                }
                // For features that can be included in the tour features list, all features can be added, regardless of show_all status. This also applies for any features used for tour or view starting location, as long as the features are Point features.
                else if (!$onlyPoints || $dataset->getFeatureType()==='Point') {
                    $featureList = array_merge($featureList, Feature::buildConfigList($dataset->getFeatures(), $dataset->getNameProperty()));
                }
            }
            if ($onlyPoints) {
                // list of point locations are for start location - should have a default null value
                $featureList = array('default' => 'None') + $featureList;
            }
            // For features that can be included in the view features list, go through all features in the tour features list and add any that are from datasets with show_all=false
            if ($fromView && !$onlyPoints) {
                foreach ($data->get('features') ?? [] as $feature) {
                    // make sure handle the case when a feature in the tour features list does not exist (by ignoring it)
                    if (empty($featureList[$feature['id']]) && $extraFeatures[$feature['id']]) $featureList[$feature['id']] = $extraFeatures[$feature['id']]->getName();
                }
            }
        }
        return $featureList;
    }

    /**
     * Only call this function from view.yaml to populate the view features list
     */
    public static function getTourFeaturesForView($onlyPoints=false): array {
        return self::getTourFeatures($onlyPoints, true);
    }

    /**
     * Only call from tour.yaml to populate the starting locations list
     */
    public static function getTourLocations(): array {
        return self::getTourFeatures(true);
    }

    /**
     * Only call this function from view.yaml to populate the starting locations list
     */
    public static function getTourLocationsForView(): array {
        return self::getTourFeatures(true, true);
    }

    /**
     * returns list of basemap files from the plugin config instead of from the uploads folder, thus ensuring that only basemaps with all the needed data (i.e. bounds) are included
     * 
     * @return array - list of basemaps ['filename'=>'filename']
     */
    public static function getBasemaps(): array {
        $basemaps = Grav::instance()['config']->get('plugins.leaflet-tour')['basemaps'];
        if (!empty($basemaps)) return array_column($basemaps, 'file', 'file');
        else return [];
    }

    /**
     * Get a list of all properties from the dataset associated with the dataset config file.
     * Warning! Only call this function directly from dataset.yaml
     * 
     * @return array - list of properties ['prop'=>'prop']
     */
    public static function getPropertyList(): array {
        $file = MarkdownFile::instance(Utils::getDatasetRoute());
        if ($file->exists()) {
            $dataset = Dataset::getDatasets()[((array)$file->header())['dataset_file']];
            return $dataset->getProperties();
        }
        return [];
    }
    
    /**
     * Get all properties from all datasets (for convenience when updating a dataset from the plugin config)
     * 
     * @return array - list of properties from all datasets ['prop'=>'prop'], also includes an option for none and an option for coordinates
     */
    public static function getAllPropertiesList(): array {
        $props = ['tour_none'=>'None', 'tour_coords'=>'Coordinates (not a property)'];
        foreach (Dataset::getDatasets() as $dataset) {
            $props = array_merge($props, $dataset->getProperties());
        }
        return $props;
    }

    /**
     * Get a list of tile servers or a specific tile server
     * 
     * @param string $key - the identifier for the tile server - if provided, the array for the tileserver requested will be returned
     * @return array - either a list of all tile servers (if key is null) that included an option for 'none', or the array for the tileserver indicated by the key
     * TODO: Make this handle when the key doesn't match anything
     */
    public static function getTileServers(string $key = null): array {
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
