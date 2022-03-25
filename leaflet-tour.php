<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Plugin;
// use Grav\Common\Data\Data;
// use Grav\Common\Page\Header;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\MarkdownFile;
// use Grav\Plugin\LeafletTour\Dataset;
use Grav\Plugin\LeafletTour\LeafletTour;
use Grav\Plugin\LeafletTour\Utils;
use Grav\Plugin\LeafletTour\Feature;
use Grav\Plugin\LeafletTour\Tour;

/**
 * @package Grav\Plugin
 */
class LeafletTourPlugin extends Plugin {

    /**
     * @return array - core list of events to listen to, note that some events must be provided in onPluginsInitialized instead
     */
    public static function getSubscribedEvents(): array {
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
    public function autoload(): ClassLoader {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Subscribe to additional events
     */
    public function onPluginsInitialized(): void {
        // Enable admin-only events
        if ($this->isAdmin()) {
            $this->enable([
                'onGetPageTemplates' => ['onGetPageTemplates', 0],
                'onGetPageBlueprints' => ['onGetPageBlueprints', 0],
                'onAdminSave' => ['onAdminSave', 0],
                'onAdminAfterDelete' => ['onAdminAfterDelete', 0],
            ]);
            return;
        }

        // Enable other events
        // $this->enable([
        //     'onShortcodeHandlers' => ['onShortcodeHandlers', 0]
        // ]);
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
        $this->grav['twig']->twig_vars['leaflet_tour'] = new LeafletTour();
    }
    
    /**
     * Add templates to admin panel template list
     */
    public function onGetPageTemplates(Event $event) {
        $types = $event->types;
        $types->register('tour');
        // TODO: What is the point of this? Should I also register other types? Seems like it's automatically pulling things as it is...
    }
    
    /**
     * Add blueprints to admin panel page editor
     */
    public function onGetPageBlueprints(Event $event) {
        $types = $event->types;
        $types->scanBlueprints(__DIR__ . '/blueprints');
    }

    // /**
    //  * Add new shortcodes (from shortcodes directory)
    //  */
    // public function onShortcodeHandlers() {
    //     $this->grav['shortcode']->registerAllShortcodes(__DIR__.'/shortcodes');
    // }

    /**
     * Handle page and plugin data on save. LeafletTour object will deal with this.
     */
    public function onAdminSave(Event $event) {
        $obj = $event['object'];
        // plugin config has method 'get' and should have item 'leaflet_tour'
        if (method_exists($obj, 'get') && $obj->get('leaflet_tour')) LeafletTour::handlePluginConfigSave($obj);
        // page config has method 'template'
        else if (method_exists($obj, 'template')) {
            switch ($obj->template()) {
                // case 'shape_dataset':
                case 'point_dataset':
                    LeafletTour::handleDatasetPageSave($obj);
                    break;
                case 'tour':
                    LeafletTour::handleTourPageSave($obj);
                    break;
                // case 'modular/view':
                //     $plugin->handleViewPageSave($obj);
                //     break;
            }
            // TODO: filter header?
        }
    }

    /**
     * Pass to LeafletTour object to handle page data on deletion.
     */
    public function onAdminAfterDelete(Event $event) {
        $obj = $event['object'];
        if (method_exists($obj, 'template')) {
            switch ($obj->template()) {
                // case 'shape_dataset':
                case 'point_dataset':
                    LeafletTour::handleDatasetDeletion($obj);
                    break;
                case 'tour':
                    LeafletTour::handleTourDeletion($obj);
                    break;
                // case 'modular/view':
                //     $plugin->handleViewDeletion($obj);
                //     break;
            }
        }
    }

    // getters for any blueprints

    public static function getDatasetsList(bool $include_none = false): array {
        $list = [];
        foreach (LeafletTour::getDatasets() as $dataset) {
            $list[$dataset->getId()] = $dataset->getTitle() ?? $dataset->getId();
        }
        if ($include_none) $list = array_merge(['none' => 'None'], $list);
        return $list;
    }

    public static function getTileServerList(): array {
        $servers = Tour::TILE_SERVERS;
        $list = array_combine(array_keys($servers), array_column($servers, 'select'));
        return array_merge($list, ['custom' => 'Custom']);
    }

    public static function getBasemapList(): array {
        $list = [];
        foreach (Grav::instance()['config']->get('plugins.leaflet-tour.basemap_info') ?? [] as $info) {
            $list[$info['file']] = $info['name'] ?: $info['file'];
        }
        return $list;
    }

    // getters for dataset blueprints

    /**
     * Get all properties for a given dataset. Must be called from a dataset page blueprint!
     * - Include option for 'none' (optional, useful for something like name_prop where this might be desirable)
     * @return array [$prop => $prop] TODO: try simple w/strings
     */
    public static function getDatasetPropertyList(bool $include_none = false): array {
        if (($file = Utils::getDatasetFile()) && $file->exists()) {
            $dataset = LeafletTour::getDatasets()[$file->header()['id']];
            $properties = $dataset->getProperties();
            $list = array_combine($properties, $properties);
            if ($include_none) $list = array_merge(['none' => 'None'], $list);
        }
        return $list ?? [];
    }

    public static function getFeaturePropertiesFields(): array {
        // get dataset and list of properties
        $props = Utils::getDatasetFile()->header()['properties'] ?? [];
        $fields = [];
        foreach ($props as $prop) {
            $fields[".$prop"] = [
                'type' => 'text',
                'label' => $prop,
            ];
        }
        return $fields;
    }

    public static function getShapeFillType(string $default): string {
        $type = Feature::validateFeatureType(Utils::getDatasetFile()->header()['feature_type'] ?? 'Polygon');
        if (str_contains($type, 'LineString')) {
            // LineString or MultiLineString
            return 'hidden';
        }
        else return $default;
    }

    // getters for tour blueprints

    public static function getTourDatasetFields(): array {
        $fields = [];
        // get tour
        $route = Utils::getPageRoute(explode('/', Grav::instance()['page']->header()->controller['key']));
        $tour_id = MarkdownFile::instance($route . 'tour.md')->header()['id'];
        $tour = LeafletTour::getTours()[$tour_id];
        // get datasets
        foreach (array_keys($tour->getDatasets()) as $id) {
            $dataset = LeafletTour::getDatasets()[$id];
            $name = "header.dataset_overrides.$id";
            $options = [
                "$name.auto_popup_properties" => [
                    'type' => 'select',
                    'label' => 'Add Properties to Popup Content',
                    'description' => 'Properties selected here will be used instead of properties selected in the dataset header. If only \'None\' is selected, then no properties will be added to popup content.',
                    'options' => array_merge(['none' => 'None'], array_combine($dataset->getProperties(), $dataset->getProperties())),
                    'multiple' => true,
                    'toggleable' => true,
                    'validate' => [
                        'type' => 'array'
                    ],
                ],
                "$name.attribution" => [
                    'type' => 'text',
                    'label' => 'Dataset Attribution',
                    'toggleable' => true,
                ],
                'legend_section' => [
                    'type' => 'section',
                    'title' => 'Legend Options',
                ],
                "$name.legend.text" => [
                    'type' => 'text',
                    'label' => 'Description for Legend',
                    'description' => 'If this field is set then any legend summary from the dataset will be ignored, whether or not the legend summary override is set.',
                    'toggleable' => true,
                ],
                "$name.legend.summary" => [
                    'type' => 'text',
                    'label' => 'Legend Summary',
                    'description' => 'Optional shorter version of the legend description.',
                    'toggleable' => true,
                ],
                "$name.legend.symbol_alt" => [
                    'type' => 'text',
                    'label' => 'Legend Symbol Alt Text',
                    'description' => 'A brief description of the icon/symbol/shape used for each feature.',
                    'toggleable' => true,
                ],
            ];
            // add icon or path options
            if ($dataset->getType() === 'Point') {
                $options["icon_section"] = [
                    'type' => 'section',
                    'title' => 'Icon Options',
                    'text' => 'Only some of the icon options in the dataset configuration are shown here, but any can be customized by directly modifying the page header in expert mode.',
                ];
                $options["$name.icon.file"] = [
                    'type' => 'filepicker',
                    'label' => 'Icon Image File',
                    'description' => 'If not set, the default Leaflet marker will be used',
                    'preview_images' => true,
                    // 'folder' => Grav::instance()['locator']->findResource('user://') . '/data/leaflet-tour/icons',
                    'folder' => 'user://data/leaflet-tour/images/icons',
                    'toggleable' => true,
                ];
                $options["$name.icon.width"] = [
                    'type' => 'number',
                    'label' => 'Icon Width (pixels)',
                    'toggleable' => true,
                    'validate' => [
                        'min' => 1
                    ],
                ];
                $options["$name.icon.height"] = [
                    'type' => 'number',
                    'label' => 'Icon Height (pixels)',
                    'toggleable' => true,
                    'validate' => [
                        'min' => 1
                    ],
                ];
            } else {
                $options['path_section'] = [
                    'type' => 'section',
                    'title' => 'Shape Options',
                    'text' => 'Other shape/path options can be customized by directly modifying the page header in expert mode.'
                ];
                $options["$name.path.color"] = [
                    'type' => 'colorpicker',
                    'label' => 'Shape Color',
                    'toggleable' => true,
                ];
            }
            $fields[$name] = [
                'type' => 'fieldset',
                'title' => $dataset->getTitle(),
                'collapsible' => true,
                'collapsed' => true,
                'fields' => $options,
            ];
        }
        return $fields;
    }

    public static function getTourFeatures(bool $only_points = false): array {
        $list = [];
        // get tour
        $route = Utils::getPageRoute(explode('/', Grav::instance()['page']->header()->controller['key']));
        $tour_id = MarkdownFile::instance($route . 'tour.md')->header()['id'];
        $tour = LeafletTour::getTours()[$tour_id];
        $features = $tour->getAllFeatures();
        if ($only_points) return self::getPoints($features);
        foreach ($features as $id => $feature) {
            $name = $feature->getName();
            $dataset = $feature->getDataset()->getTitle();
            $list[$id] = "$name ... ($dataset)";
        }
        return $list;
    }
    private static function getPoints(array $features): array {
        $list = [];
        foreach ($features as $id => $feature) {
            if ($feature->getType() === 'Point') {
                $name = $feature->getName();
                $coords = implode(',', $feature->getCoordinatesJson());
                $list[$id] = "$name ($coords)";
            }
        }
        return array_merge(['none' => 'None'], $list);
    }
}
