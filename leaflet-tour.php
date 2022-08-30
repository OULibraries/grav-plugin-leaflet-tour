<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Plugin;
// use Grav\Common\Data\Data;
// use Grav\Common\Page\Header;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\MarkdownFile;
use Grav\Plugin\LeafletTour\Dataset;
use Grav\Plugin\LeafletTour\LeafletTour;
use Grav\Plugin\LeafletTour\Utils;
use Grav\Plugin\LeafletTour\Feature;
// use Grav\Plugin\LeafletTour\Tour;

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
                'onAdminPageTypes' => ['onAdminPageTypes', 0],
            ]);
            return;
        }

        // Enable other events
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
        $this->grav['twig']->twig_vars['leaflet_tour'] = new LeafletTour();
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
     * Add new shortcode(s) (from shortcodes directory)
     */
    public function onShortcodeHandlers() {
        $this->grav['shortcode']->registerAllShortcodes(__DIR__.'/shortcodes');
    }

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
                case 'shape_dataset':
                case 'point_dataset':
                    LeafletTour::handleDatasetPageSave($obj);
                    break;
                case 'tour':
                    LeafletTour::handleTourPageSave($obj);
                    break;
                case 'modular/view':
                    LeafletTour::handleViewPageSave($obj);
                    break;
            }
        }
    }

    /**
     * Pass to LeafletTour object to handle page data on deletion.
     */
    public function onAdminAfterDelete(Event $event) {
        $obj = $event['object'];
        if (method_exists($obj, 'template')) {
            switch ($obj->template()) {
                case 'point_dataset':
                case 'shape_dataset':
                    LeafletTour::handleDatasetDeletion($obj);
                    break;
                // case 'tour':
                //     LeafletTour::handleTourDeletion($obj);
                //     break;
                // case 'modular/view':
                //     LeafletTour::handleViewDeletion($obj);
                //     break;
            }
        }
    }

    /**
     * Hide certain page types
     */
    public function onAdminPageTypes(Event $event) {
        $types = $event['types'];
        unset($types['dataset']);
        $event['types'] = $types;
    }

    // getters for any blueprints

    public static function getDatasetsList(bool $include_none = false): array {
        $list = [];
        foreach (LeafletTour::getDatasets() as $id => $file) {
            $dataset = Dataset::fromLimitedArray($file->header(), ['title', 'id']);
            $name = $dataset->getName();
            $list[$id] = $name;
        }
        if ($include_none) $list = array_merge(['none' => 'None'], $list);
        return $list;
    }

    public static function getTileServerList(): array {
        return LeafletTour::TILE_SERVER_LIST;
    }

    public static function getBasemapList(): array {
        $list = [];
        foreach (LeafletTour::getBasemapInfo() as $file => $info) {
            $list[$file] = $info['name'] ?: $file;
        }
        return $list;
    }

    /**
     * Returns select_optgroup options
     */
    public static function getUpdatePropertiesList(): array {
        $list = [];
        foreach (LeafletTour::getDatasets() as $id => $file) {
            $dataset = Dataset::fromLimitedArray($file->header(), ['id', 'title', 'properties']);
            $name = $dataset->getName();
            $sublist = [];
            foreach ($dataset->getProperties() as $prop) {
                $sublist["$id--prop--$prop"] = $prop;
            }
            $list[] = [$name => $sublist];
        }
        return array_merge(['none' => 'None', 'coords' => 'Coordinates'], $list);
    }

    // getters for dataset blueprints

    /**
     * Get all properties for a given dataset. Must be called from a dataset page blueprint!
     * - Include option for 'none' (optional, useful for something like name_prop where this might be desirable)
     * @return array [$prop => $prop]
     */
    public static function getDatasetPropertyList(bool $include_none = false): array {
        $file = Utils::getDatasetFile();
        if ($file) {
            $props = Dataset::fromLimitedArray($file->header(), ['properties'])->getProperties();
            $list = array_combine($props, $props);
            if ($include_none) $list = array_merge(['none' => 'None'], $list);
        }
        return $list ?? [];
    }

    public static function getFeaturePropertiesFields(): array {
        $file = Utils::getDatasetFile();
        $fields = [];
        if ($file) {
            // get dataset and list of properties
            $props = Dataset::fromLimitedArray($file->header(), ['properties'])->getProperties();
            foreach ($props as $prop) {
                $fields[".$prop"] = [
                    'type' => 'text',
                    'label' => $prop,
                ];
            }
        }
        return $fields;
    }

    public static function getShapeFillType(string $default): string {
        $file = Utils::getDatasetFile();
        if ($file) {
            $type = Feature::validateFeatureType($file->header()['feature_type']);
            if (str_contains($type, 'LineString')) {
                // LineString or MultiLineString
                return 'hidden';
            }
        }
        return $default;
    }

    public static function getDatasetDefaults(string $key): string {
        $file = Utils::getDatasetFile();
        if ($file) {
            $header = $file->header();
            switch ($key) {
                case 'path_fillColor':
                case 'active_path_color':
                    // default: path color ?? default color
                    return ($header['path'] ?? [])['color'] ?? Dataset::DEFAULT_PATH['color'];
                case 'active_path_fillColor':
                    // default: regular fill color
                    return ($header['path'] ?? [])['fillColor'] ?? self::getDatasetDefaults('path_fillColor');
            }
        }
        return '';
    }

    // getters for tour blueprints

    public static function getTourDatasetFields(): array {
        $file = Utils::getTourFile();
        $fields = [];
        if ($file) {
            $datasets = $file->header()['datasets'] ?? [];
            $overrides = $file->header()['dataset_overrides'] ?? [];
            foreach (array_column($datasets, 'id') as $id) {
                if ($dataset_file = LeafletTour::getDatasets()[$id]) {
                    $dataset = Dataset::fromArray(array_diff_key($dataset_file->header(), array_flip(['features']))); // just because we don't need features
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
                            'default' => $dataset->getAutoPopupProperties(),
                        ],
                        "$name.attribution" => [
                            'type' => 'text',
                            'label' => 'Dataset Attribution',
                            'toggleable' => true,
                            'default' => $dataset->getAttribution(),
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
                            'default' => $dataset->getLegend()['text'],
                        ],
                        "$name.legend.summary" => [
                            'type' => 'text',
                            'label' => 'Legend Summary',
                            'description' => 'Optional shorter version of the legend description.',
                            'toggleable' => true,
                            'default' => $dataset->getLegend()['summary'],
                        ],
                        "$name.legend.symbol_alt" => [
                            'type' => 'text',
                            'label' => 'Legend Symbol Alt Text',
                            'description' => 'A brief description of the icon/symbol/shape used for each feature.',
                            'toggleable' => true,
                            'default' => $dataset->getLegend()['symbol_alt'],
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
                        $file = $dataset->getIcon(true)['file'];
                        // determine appropriate defaults for icon height/width if not directly set by dataset
                        try {
                            $file ??= $overrides[$dataset->getId()]['icon']['file'];
                        } catch (\Throwable $t) {} // do nothing
                        if ($file) $default = Dataset::CUSTOM_MARKER_FALLBACKS;
                        else $default = Dataset::DEFAULT_MARKER_FALLBACKS;
                        $height = $dataset->getIcon()['height'] ?? $default['height'];
                        $width = $dataset->getIcon()['width'] ?? $default['width'];
                        if ($file) $options["$name.icon.file"]['default'] = $file;
                        $options["$name.icon.width"] = [
                            'type' => 'number',
                            'label' => 'Icon Width (pixels)',
                            'toggleable' => true,
                            'validate' => [
                                'min' => 1
                            ],
                            'default' => $width,
                        ];
                        $options["$name.icon.height"] = [
                            'type' => 'number',
                            'label' => 'Icon Height (pixels)',
                            'toggleable' => true,
                            'validate' => [
                                'min' => 1
                            ],
                            'default' => $height,
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
                            'default' => $dataset->getStrokeOptions()['color'],
                            'toggleable' => true,
                        ];
                        $options["$name.border.color"] = [
                            'type' => 'colorpicker',
                            'label' => 'Border Color',
                            'toggleable' => true,
                            'default' => $dataset->getBorderOptions()['color'],
                        ];
                    }
                    $fields[$name] = [
                        'type' => 'fieldset',
                        'title' => $dataset->getName(),
                        'collapsible' => true,
                        'collapsed' => true,
                        'fields' => $options,
                    ];
                }
            }
        }
        return $fields;
    }

    public static function getTourFeatures(bool $only_points = false): array {
        $file = Utils::getTourFile();
        $list = [];
        if ($file) {
            $ids = array_column($file->header()['datasets'] ?? [], 'id');
            $datasets = LeafletTour::getDatasets();
            $datasets = array_merge($ids, $datasets); // to keep order from tour
            $datasets = array_intersect_key($datasets, $ids); // to limit to only tour datasets
            $datasets = array_map(function($dataset_file) { return Dataset::fromArray($dataset_file->header()); }, $datasets);
            if ($only_points) return self::getPoints($datasets);
            // implied else
            foreach (array_values($datasets) as $dataset) {
                foreach ($dataset->getFeatures() as $id => $feature) {
                    $list[$id] = $feature->getName() . ' ... (' . $dataset->getName() . ')';
                }
            }
        }
        return $list;
    }

    public static function getPoints(array $datasets): array {
        $list = [];
        foreach (array_values($datasets) as $dataset) {
            if ($dataset->getType() === 'Point') {
                $features = array_map(function($feature) {
                    return $feature->getName() . ' (' . implode(',', $feature->getCoordinates()) . ')';
                }, $dataset->getFeatures());
                $list = array_merge($list, $features);
            }
        }
        return array_merge(['none' => 'None'], $list);
    }

    // getters for view blueprints

    public static function getViewFeatures(bool $only_points = false): array {
        $file = Utils::getTourFileFromView();
        $list = [];
        if ($file) {
            $ids = array_column($file->header()['datasets'] ?? [], 'id');
            $datasets = array_merge($ids, LeafletTour::getDatasets());
            $datasets = array_map(function($dataset_file) {
                return Dataset::fromArray($dataset_file->header());
            }, array_intersect_key($datasets, $ids));
            if ($only_points) {
                return self::getPoints($datasets);
            }
            // implied else
            $tour = Tour::fromFile($file, [], [], $datasets);
            foreach ($tour->getIncludedFeatures() as $id => $feature) {
                $list[$id] = $feature->getName() . ' ... (' . $datasets[$feature->getDatasetId()]->getName() . ')';
            }
        }
        return $list;
    }

    public static function getTourIdForView(): string {
        $file = Utils::getTourFileFromView();
        $id = $file->header()['id'] ?? '';
        return $id;
    }
}
