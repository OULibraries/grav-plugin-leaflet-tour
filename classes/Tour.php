<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;
use RocketTheme\Toolbox\File\File;

/**
 * @property array $views Provided in constructor, ['id' => View]
 * @property array $plugin_config Provided in constructor
 * 
 * @property string|null $attribution From yaml
 * @property array $datasets [id => Dataset]: Calculated in constructor
 * @property array $features [id => Feature]: Calculated in constructor
 * @property array $feature_popups [id => [name, auto, popup]]: Calculated in constructor
 * @property array $legend [include, toggles, basemaps, dark]: From yaml
 * @property array $tile_server [provider or url, key, id, attribution]: Calculated in constructor
 * @property array $basemaps [string]: From yaml
 * @property array $basemap_info [filename => array]: Calculated in constructor
 * @property array|null $starting_bounds Calculated in constructor
 * @property array $overrides [map_on_right, show_map_location_in_url]: From yaml
 * @property array $view_options [remove_tile_server, only_show_view_features, list_popup_buttons]: From yaml
 * @property array $max_bounds [north, south, east, west]: From yaml
 * @property array $extras [key => value]: From yaml
 * @property bool $no_attribution From yaml
 * @property int|null $column_width From yaml
 * @property int|null $max_zoom From yaml
 * @property int|null $min_zoom From yaml
 */
class Tour {

    const DEFAULT_LEGEND = [
        'include' => true,
        'toggles' => false,
        'basemaps' => false,
        'dark' => false,
    ];
    const DEFAULT_OVERRIDES = [
        'map_on_right' => true,
        'show_map_location_in_url' => false,
    ];
    const DEFAULT_TILE_SERVER = 'OpenTopoMap';

    private $views, $plugin_config, $attribution, $datasets, $features, $feature_popups, $legend, $tile_server, $basemaps, $basemap_info, $starting_bounds, $overrides, $view_options, $max_bounds, $extras, $no_attribution, $column_width, $max_zoom, $min_zoom;

    /**
     * constructor
     * - Uses function to validate yaml data types
     * - Sets some values from yaml - attribution, legend, view options, max bounds, no attribution, column width, min zoom, max zoom
     * - Sets extras and plugin config
     * - Validates and sets basemaps
     * - Sets basemap info - all basemaps in tour and views
     * - Builds and sets merged datasets
     * - Builds and sets included features
     * - Builds and sets tile server options
     * - Builds and sets feature popups
     * - Builds and sets starting bounds
     * - Builds and sets views
     * - Builds and sets overrides
     * 
     * @param array $yaml
     * @param array $datasets [id => file]
     * @param array $plugin_config
     * @param array $views [id => file]
     */
    private function __construct($yaml, $datasets, $plugin_config, $views) {
        // validate yaml data types - call function
        $options = self::validateYaml($yaml);
        // set some values from yaml
        foreach (['attribution', 'legend', 'view_options', 'max_bounds', 'no_attribution', 'column_width', 'min_zoom', 'max_zoom'] as $key) {
            // data type already validated
            $this->$key = Utils::get($options, $key);
        }
        // set extras and plugin config
        $this->plugin_config = $plugin_config;
        $keys = ['file', 'id', 'title', 'attribution', 'datasets', 'dataset_overrides', 'features', 'legend', 'tile_server', 'basemaps', 'start', 'overrides', 'view_options', 'max_bounds', 'no_attribution', 'column_width', 'max_zoom', 'min_zoom'];
        $this->extras = array_diff_key($options, array_flip($keys));
        // get info for all basemaps
        $basemap_info = self::getConfigBasemapInfo($plugin_config);
        // validate and set basemaps
        $this->basemaps = array_values(array_intersect($options['basemaps'], array_keys($basemap_info)));
        // included features... needs tour features and tour datasets
        $tour_datasets = self::validateTourDatasets($options, $datasets);
        $datasets = self::getDatasetObjects($tour_datasets, $datasets);
        $features = self::getFeatureObjects($datasets);
        $tour_features = self::validateTourFeatures($options, $features);
        $this->features = self::prepareIncludedFeatures($tour_features, $tour_datasets, $datasets);
        // merged datasets
        $dataset_overrides = self::validateDatasetOverrides($options, $datasets);
        $this->datasets = self::prepareMergedDatasets($this->features, $datasets, $dataset_overrides);
        // feature popups
        $this->feature_popups = self::prepareFeaturePopupContent($this->features, $tour_features, $this->datasets);
        // tile server options
        $this->tile_server = self::prepareTileServerOptions($options['tile_server'], $plugin_config);
        // starting bounds (note: location calculation will verify that provided feature is point, so can use all feature ids instead of just point ids when validating start location)
        $location = self::validateStartLocation($options, array_keys($features))['location'];
        $this->starting_bounds = View::calculateStartingBounds($options['start'], Utils::get($features, $location));
        // views and basemap info
        $basemaps = $this->basemaps; // start with just tour basemaps
        $this->views = [];
        // temp values for building views
        $basemap_ids = array_keys($basemap_info);
        $point_ids = array_keys($features); // as above, can use all features, not just points, because start bounds calculation will verify
        $included_ids = array_keys($this->features);
        $popups = array_column($this->feature_popups, 'name', 'id');
        foreach ($views as $id => $file) {
            $view = View::fromFile($file, $basemap_ids, $point_ids, $included_ids, $popups, $features, $this->view_options);
            $basemaps = array_merge($basemaps, $view->getBasemaps());
            $this->views[$id] = $view;
        }
        // finish basemap info - order correctly and set correct values, then filter
        $info = array_merge(array_flip($basemaps), $basemap_info);
        $this->basemap_info = array_intersect_key($info, array_flip($basemaps), $basemap_info);
        // overrides
        $this->overrides = self::prepareOverrides($options['overrides'], $plugin_config);
    }
    /**
     * calls constructor
     * 
     * @param array $yaml
     * @param array $datasets [id => file]
     * @param array $plugin_config
     * @param array $views [id => file]
     * @return Tour
     */
    public static function fromArray($yaml, $datasets, $plugin_config, $views) {
        return new Tour($yaml, $datasets, $plugin_config, $views);
    }
    /**
     * Calls constructor using file data, assuming file is valid (otherwise returns null)
     * 
     * @param MarkdownFile $file
     * @param array $datasets [id => file]
     * @param array $plugin_config
     * @param array $views [id => file]
     * @return Tour|null
     */
    public static function fromFile($file, $datasets, $plugin_config, $views) {
        try {
            $yaml = $file->header();
            return new Tour($yaml, $datasets, $plugin_config, $views);
        } catch (\Throwable $t) {
            return null;
        }
    }

    // public facing validation methods
    /**
     * called when tour is saved
     * - Uses function to validate all yaml (including values in arrays)
     * - Validates basemaps
     * - Validates datasets
     * - Validates dataset overrides (and auto popup properties)
     * - Validates features (and popup content)
     * - Adds features from datasets with add_all true
     * - Validates start location
     * - Adds additional item 'has_popups' (true if at least one included feature has popup content of any kind)
     * - Validates and saves views (builds temp values)
     * 
     * @param array $yaml
     * @param array $datasets [id => file]
     * @param array $plugin_config
     * @param array $views [id => file]
     * @param string $path
     * @return array
     */
    public static function validateTourUpdate($yaml, $datasets, $plugin_config, $views, $path) {
        // validate yaml data types (standard and in arrays)
        $options = self::validateYamlFull($yaml);
        // basemaps
        $basemap_info = self::getConfigBasemapInfo($plugin_config);
        $options['basemaps'] = array_values(array_intersect($options['basemaps'], array_keys($basemap_info)));
        // datasets
        $tour_datasets = self::validateTourDatasets($options, $datasets);
        $options['datasets'] = array_values($tour_datasets);
        // dataset overrides
        $datasets = self::getDatasetObjects($tour_datasets, $datasets);
        $options['dataset_overrides'] = self::validateDatasetOverrides($options, $datasets);
        // features
        $features = self::getFeatureObjects($datasets);
        $tour_features = self::validateTourFeatures($options, $features);
        $tour_features = self::addAllFeatures($tour_features, $tour_datasets, $datasets);
        // feature popup content
        $options['features'] = array_values(self::validateFeaturePopupContent($tour_features, $path));
        // start location
        $point_ids = self::preparePointIds($datasets);
        $options['start'] = self::validateStartLocation($options, $point_ids);
        // temporary values
        $included_features = self::prepareIncludedFeatures($tour_features, $tour_datasets, $datasets);
        $popups = self::prepareFeaturePopups($included_features, $tour_features, $datasets, $options['dataset_overrides']);
        // views
        self::updateViews($views, $point_ids, array_keys($included_features), $popups);
        // set popup indication
        $options['has_popups'] = !empty($popups);
        // return validated tour yaml
        return $options;
    }
    /**
     * called when view is saved
     * - validates view - calls function (Uses tour yaml to generate temp values: point ids, included ids, popups)
     * 
     * @param array $view_yaml
     * @param array $tour_yaml
     * @param array $datasets [id => file]
     * @param array $plugin_config
     * @return array
     */
    public static function validateViewUpdate($view_yaml, $tour_yaml, $datasets, $plugin_config) {
        // prepare temp values for view validation
        // point ids
        $tour_datasets = self::validateTourDatasets($tour_yaml, $datasets);
        $datasets = self::getDatasetObjects($tour_datasets, $datasets);
        $point_ids = self::preparePointIds($datasets);
        // included ids
        $features = self::getFeatureObjects($datasets);
        $tour_features = self::validateTourFeatures($tour_yaml, $features);
        $included_features = self::prepareIncludedFeatures($tour_features, $tour_datasets, $datasets);
        // popups
        $popups = self::prepareFeaturePopups($included_features, $tour_features, $datasets, self::validateDatasetOverrides($tour_yaml, $datasets));
        // basemap ids
        $basemap_ids = array_keys(self::getConfigBasemapInfo($plugin_config));
        return View::validateUpdate($view_yaml, $basemap_ids, $point_ids, array_keys($included_features), $popups);
    }
    /**
     * called when dataset is saved, updated, deleted or otherwise modified
     * - Returns null if tour does not use dataset
     * - Validates datasets, dataset overrides, features, start location
     * - Renames auto popup properties (if set)
     * - Validates and saves views (creates temp values needed)
     * 
     * @param array $yaml
     * @param array $datasets
     * @param array $views [id => file]
     * @param string $dataset_id
     * @param array|null $renamed_properties [old => new]
     * @return array|null
     */
    public static function validateDatasetUpdate($yaml, $datasets, $views, $dataset_id, $renamed_properties = null) {
        // does tour use dataset?
        $tour_datasets = self::validateTourDatasets($yaml, $datasets);
        if (!Utils::get($tour_datasets, $dataset_id)) {
            // check in case dataset was removed - only return null if dataset is in datasets list or not in original tour yaml
            if (isset($datasets[$dataset_id]) || !in_array($dataset_id, array_column(Utils::getArr($yaml, 'datasets'), 'id'))) return null;
        }
        // at this point, tour does use dataset - validate stuff
        $datasets = self::getDatasetObjects($tour_datasets, $datasets);
        // dataset overrides - either rename properties or validate full overrides (important thing is auto popup properties, which either method would take care of - both would be overkill)
        $overrides = Utils::getArr($yaml, 'dataset_overrides');
        if ($renamed_properties) $overrides = self::validateRenamedProperties($overrides, $dataset_id, $renamed_properties);
        else $overrides = self::validateDatasetOverrides($yaml, $datasets);
        // features, start location
        $features = self::getFeatureObjects($datasets);
        $tour_features = self::validateTourFeatures($yaml, $features);
        $point_ids = self::preparePointIds($datasets);
        $start = self::validateStartLocation($yaml, $point_ids);
        // update views
        $included_features = self::prepareIncludedFeatures($tour_features, $tour_datasets, $datasets);
        $popups = self::prepareFeaturePopups($included_features, $tour_features, $datasets, $overrides);
        self::updateViews($views, $point_ids, array_keys($included_features), $popups);
        // return validated tour yaml
        return  array_merge($yaml, [
            'datasets' => array_values($tour_datasets),
            'features' => array_values($tour_features),
            'dataset_overrides' => $overrides,
            'start' => $start,
        ]);
    }

    // public facing blueprint methods
    /**
     * Called by tour blueprint to generate list of available datasets
     * - Provides list of all datasets as [id => name]
     * - Includes any additional ids currently used with name "Invalid, please remove"
     * 
     * @param array $yaml
     * @param array $datasets [id => file]
     * @return array
     */
    public static function getBlueprintDatasetOptions($yaml, $datasets) {
        $list = [];
        // list of valid datasets
        foreach ($datasets as $id => $file) {
            $dataset = Dataset::fromLimitedArray($file->header(), ['title', 'id']);
            $list[$id] = $dataset->getName();
        }
        // list of ids currently used
        $ids = array_column(Utils::getArr($yaml, 'datasets'), 'id');
        // add invalid
        $invalid = array_diff($ids, array_keys($list));
        if (!empty($invalid)) {
            foreach ($invalid as $id) {
                $list[$id] = 'Invalid, please remove';
            }
        }
        return $list;
    }
    /**
     * Called by tour blueprint to generate the full fields for all dataset overrides
     * - Return array of fieldsets, one for each dataset in the tour's 'datasets' list (assuming dataset is valid)
     * - Uses function to set correct auto popup properties field
     * - Sets attribution field with default from dataset
     * - Uses function to set correct legend fields
     * - Uses function to set correct icon/shape fields, as appropriate for dataset type
     * 
     * @param array $yaml
     * @param array $datasets [id => file]
     * @return array
     */
    public static function getBlueprintDatasetOverrides($yaml, $datasets) {
        $fields = [];
        // get tour datasets and dataset overrides
        $datasets = self::getDatasetObjects(self::validateTourDatasets($yaml, $datasets), $datasets);
        $dataset_overrides = Utils::getArr($yaml, 'dataset_overrides');
        // loop through datasets, set fields
        foreach ($datasets as $id => $dataset) {
            // get overrides for the specific dataset (if already set)
            $overrides = Utils::getArr($dataset_overrides, $id);
            // set prefix for all field names
            $prefix = "header.dataset_overrides.$id";
            // options
            $auto_popup_props = self::getAutoPopupOverridesField($dataset, $overrides, $prefix);
            $attribution = ["$prefix.attribution" => [
                'type' => 'text',
                'label' => 'Dataset Attribution',
                'toggleable' => true,
                'default' => $dataset->getAttribution(),
            ]];
            $legend = self::getLegendOverridesFields($dataset, $overrides, $prefix);
            $type_options = [];
            if ($dataset->getType() === 'Point') {
                $type_options = self::getIconOverridesFields($dataset, $overrides, $prefix);
            } else {
                $type_options = self::getShapeOverridesFields($dataset, $prefix);
            }
            $options = array_merge($auto_popup_props, $attribution, $legend, $type_options);
            // add options as fieldset
            $fields[$prefix] = [
                'type' => 'fieldset',
                'title' => $dataset->getName(),
                'collapsible' => true,
                'collapsed' => true,
                'fields' => $options,
            ];
        }
        return $fields;
    }
    /**
     * Called by tour blueprint to generate list of all available features
     * - Provides list of all features as [id => name]
     * - Adds dataset name to feature name for convenience
     * - Includes any additional (invalid) features currently set with name "Invalid, please remove"
     * 
     * @param array $yaml
     * @param array $datasets [id => file]
     * @return array
     */
    public static function getBlueprintFeatureOptions($yaml, $datasets) {
        $list = [];
        // get tour datasets
        $datasets = self::getDatasetObjects(self::validateTourDatasets($yaml, $datasets), $datasets);
        // list features
        foreach ($datasets as $dataset) {
            foreach ($dataset->getFeatures() as $id => $feature) {
                $list[$id] = $feature->getName() . ' ... (' . $dataset->getName() . ')';
            }
        }
        // list of ids currently used
        $ids = array_column(Utils::getArr($yaml, 'features'), 'id');
        // add invalid
        $invalid = array_diff($ids, array_keys($list));
        if (!empty($invalid)) {
            foreach ($invalid as $id) {
                $list[$id] = 'Invalid, please remove';
            }
        }
        return $list;
    }
    /**
     * Called by tour or view blueprint to generate list of all available point features
     * - Provides list of all point features as [id => name] (plus none)
     * - Adds feature coordinates to feature name for convenience
     * - If currently value used by yaml (view if set, tour otherwise) is invalid, includes that with name "Invalid, please remove"
     * 
     * @param array $tour_yaml
     * @param array $datasets [id => file]
     * @param array|null $view_yaml
     * @return array
     */
    public static function getBlueprintPointOptions($tour_yaml, $datasets, $view_yaml = null) {
        $list = [];
        // get tour datasets
        $datasets = self::getDatasetObjects(self::validateTourDatasets($tour_yaml, $datasets), $datasets);
        // list features (only points)
        foreach ($datasets as $dataset) {
            if ($dataset->getType() === 'Point') {
                foreach ($dataset->getFeatures() as $id => $feature) {
                    $coords = $feature->getYamlCoordinates();
                    $list[$id] = $feature->getName() . ' (' . Utils::get($coords, 'lng') . ', ' . Utils::get($coords, 'lat') . ')'; 
                }
            }
        }
        // include none
        $list = array_merge(['none' => 'None'], $list);
        // get id currently used (if any)
        $start = Utils::getArr($view_yaml ?? $tour_yaml, 'start');
        $id = Utils::getStr($start, 'location', null);
        // add if invalid
        if (($id !== null) && !Utils::get($list, $id)) {
            $list[$id] = 'Invalid, please remove';
        }
        return $list;
    }
    /**
     * Called by view blueprint to generate list of all included features
     * - Provides list of all included features as [id => name]
     * - Adds dataset name to feature name for convenience
     * - Includes any additional (invalid) features currently set with name "Invalid, please remove"
     * 
     * @param array $tour_yaml
     * @param array $view_yaml
     * @param array $datasets [id => file]
     * @return array
     */
    public static function getBlueprintViewFeatureOptions($tour_yaml, $view_yaml, $datasets) {
        $list = [];
        // get included features
        $tour_datasets = self::validateTourDatasets($tour_yaml, $datasets);
        $datasets = self::getDatasetObjects($tour_datasets, $datasets);
        $features = self::getFeatureObjects($datasets);
        $features = self::prepareIncludedFeatures(self::validateTourFeatures($tour_yaml, $features), $tour_datasets, $datasets);
        // list features
        foreach ($features as $id => $feature) {
            $list[$id] = $feature->getName() . ' ... (' . $datasets[$feature->getDatasetId()]->getName() . ')';
        }
        // list of ids currently used
        $ids = array_column(Utils::getArr($view_yaml, 'features'), 'id');
        // add invalid
        $invalid = array_diff($ids, array_keys($list));
        if (!empty($invalid)) {
            foreach ($invalid as $id) {
                $list[$id] = 'Invalid, please remove';
            }
        }
        return $list;
    }
    /**
     * Called by tour or view blueprint to generate list of valid basemaps
     * - Provides list of all valid basemaps (in dataset info and file exists)
     * - Indexes list by file, but references by name instead if set
     * - Includes any invalid 'basemaps' currently set
     * 
     * @param array $yaml
     * @param array $basemap_info
     * @return array
     */
    public static function getBlueprintBasemapsOptions($yaml, $basemap_info) {
        $list = [];
        $data = Grav::instance()['locator']->findResource('user-data://');
        foreach ($basemap_info as $info) {
            if (!is_array($info)) continue;
            $file = Utils::getStr($info, 'file');
            if (File::instance("$data/$file")->exists()) $list[$file] = Utils::getStr($info, 'name') ?: $file;
        }
        // invalid?
        $invalid = array_diff(Utils::getArr($yaml, 'basemaps'), array_keys($list));
        foreach ($invalid as $key) {
            $list[$key] = 'Invalid, please remove';
        }
        return $list;
    }

    // internal methods for blueprint methods
    /**
     * Get field for setting auto popup properties for a given dataset override
     * - Sets defaults using values from dataset
     * - Provides list of auto popup properties options by combining dataset properties with any additional (no longer valid) properties currently set by tour (name for invalid ones is "Invalid, please remove")
     * - If values or defaults are currently set, ensures that they will be displayed in the correct order by placing them at the front of the list of options
     * 
     * @param Dataset $dataset
     * @param array $overrides The override yaml for the dataset provided
     * @param string $prefix
     * @return array
     */
    public static function getAutoPopupOverridesField($dataset, $overrides, $prefix) {
        $default = $dataset->getAutoPopupProperties();
        $list = ['none' => 'None'];
        // get override
        if ($overrides && is_array($overrides)) $overrides = Utils::getArr($overrides, 'auto_popup_properties', null);
        else $overrides = null;
        // get current (valid) - override or dataset values
        $valid = array_intersect($overrides ?? $default, $dataset->getProperties());
        // set valid first
        if (!empty($valid)) $list = array_merge($list, array_combine($valid, $valid));
        // then the rest
        $list = array_merge($list, array_combine($dataset->getProperties(), $dataset->getProperties()));
        // invalid?
        if ($overrides) {
            $invalid = array_diff($overrides, array_keys($list));
            foreach ($invalid as $prop) {
                $list[$prop] = 'Invalid, please remove';
            }
        }
        return [
            "$prefix.auto_popup_properties" => [
                'type' => 'select',
                'label' => 'Add Properties to Popup Content',
                'description' => 'Properties selected here will be used instead of properties selected in the dataset header. If only \'None\' is selected, then no properties will be added to popup content.',
                'options' => $list,
                'multiple' => true,
                'toggleable' => true,
                'validate' => [
                    'type' => 'array'
                ],
                'default' => $default,
            ],
        ];
    }
    /**
     * Get fields for setting legend values for a given dataset override
     * - Sets defaults using values from dataset
     * - Only includes legend summary default if tour override does not set legend text
     * - Only includes legend symbol alt default if tour override does not set icon file (points) or path color (shapes)
     * 
     * @param Dataset $dataset
     * @param array $overrides
     * @param string $prefix
     * @return array
     */
    public static function getLegendOverridesFields($dataset, $overrides, $prefix) {
        // validate overrides, just in case
        if (!is_array($overrides)) $overrides = [];
        // set defaults from dataset
        $text = $dataset->getLegend('text');
        $summary = $dataset->getLegend('summary');
        $symbol_alt = $dataset->getLegend('symbol_alt');
        // include summary default? - not if override legend text is set (and valid)
        if (($legend = Utils::getArr($overrides, 'legend', null)) && Utils::getStr($legend, 'text', null)) $summary = null;
        // include symbol alt default?
        if ($dataset->getType() === 'Point') {
            // not if override icon file is set (and valid)
            if (($icon = Utils::getArr($overrides, 'icon', null)) && Utils::getStr($icon, 'file', null)) $symbol_alt = null;
        } else {
            // not if override path color is set (and valid) (could also include path fillColor, border color, or anything else in that list if desired)
            if (($path = Utils::getArr($overrides, 'path', null)) && Utils::getStr($path, 'color', null)) $symbol_alt = null;
        }
        // build and return
        return [
            'legend_section' => [
                'type' => 'section',
                'title' => 'Legend Options',
            ],
            "$prefix.legend.text" => [
                'type' => 'text',
                'label' => 'Description for Legend',
                'description' => 'If this field is set then any legend summary from the dataset will be ignored, whether or not the legend summary override is set.',
                'toggleable' => true,
                'default' => $text,
            ],
            "$prefix.legend.summary" => [
                'type' => 'text',
                'label' => 'Legend Summary',
                'description' => 'Optional shorter version of the legend description.',
                'toggleable' => true,
                'default' => $summary,
            ],
            "$prefix.legend.symbol_alt" => [
                'type' => 'text',
                'label' => 'Legend Symbol Alt Text',
                'description' => 'A brief description of the icon/symbol/shape used for each feature.',
                'toggleable' => true,
                'default' => $symbol_alt,
            ],
        ];
    }
    /**
     * - Sets defaults using values from dataset
     * - If dataset does not set icon height/width and neither tour nor dataset set icon file, uses defaults for default icon
     * - If dataset does not set icon height/width but either tour or dataset set icon file, uses default for custom icon
     * 
     * @param Dataset $dataset
     * @param array $overrides
     * @param string $prefix
     * @return array
     */
    public static function getIconOverridesFields($dataset, $overrides, $prefix) {
        $icon = Utils::getArr($overrides, 'icon');
        // icon file from tour if set, otherwise dataset if set, otherwise null
        $file = Utils::getStr($icon, 'file', null) ?? Utils::getStr($dataset->getIcon(), 'file', null);
        // set defaults based on icon file
        if ($file) $default = Dataset::CUSTOM_MARKER_FALLBACKS;
        else $default = Dataset::DEFAULT_MARKER_FALLBACKS;
        // now set actual defaults for file, height, and width
        $file = Utils::getStr($dataset->getIcon(), 'file', null);
        $height = Utils::getType($dataset->getIcon(), 'height', 'is_int') ?? $default['height'];
        $width = Utils::getType($dataset->getIcon(), 'width', 'is_int') ?? $default['width'];
        // return options
        return [
            'icon_section' => [
                'type' => 'section',
                'title' => 'Icon Options',
                'text' => 'Only some of the icon options in the dataset configuration are shown here, but any can be customized by directly modifying the page header in expert mode.',
            ],
            "$prefix.icon.file" => [
                'type' => 'filepicker',
                'label' => 'Icon Image File',
                'description' => 'If not set, the default Leaflet marker will be used',
                'preview_images' => true,
                'folder' => 'user://data/leaflet-tour/images/icons',
                'toggleable' => true,
                'default' => $file,
            ],
            "$prefix.icon.width" => [
                'type' => 'number',
                'label' => 'Icon Width (pixels)',
                'toggleable' => true,
                'validate' => [
                    'min' => 1
                ],
                'default' => $width,
            ],
            "$prefix.icon.height" => [
                'type' => 'number',
                'label' => 'Icon Height (pixels)',
                'toggleable' => true,
                'validate' => [
                    'min' => 1
                ],
                'default' => $height,
            ],
        ];
    }
    /**
     * - Sets defaults using values from dataset
     * 
     * @param Dataset $dataset
     * @param array $overrides
     * @param string $prefix
     * @return array
     */
    public static function getShapeOverridesFields($dataset, $prefix) {
        return [
            'path_section' => [
                'type' => 'section',
                'title' => 'Shape Options',
                'text' => 'Other shape/path options can be customized by directly modifying the page header in expert mode.'
            ],
            "$prefix.path.color" => [
                'type' => 'colorpicker',
                'label' => 'Shape Color',
                'default' => Utils::getStr($dataset->getStrokeOptions(), 'color', null),
                'toggleable' => true,
            ],
            "$prefix.border.color" => [
                'type' => 'colorpicker',
                'label' => 'Border Color',
                'toggleable' => true,
                'default' => Utils::getStr($dataset->getBorder(), 'color', null), // shows default even if no stroke - can change to getBorderOptions() if only want default when border stroke is true
            ],
        ];
    }

    // validation methods
    /**
     * Validate the data type for all expected yaml values (does not check array values)
     * - Checks data types for all yaml values
     * - Keeps extra options (not as separate array)
     * 
     * @param array $yaml
     * @return array
     */
    public static function validateYaml($yaml) {
        $options = $yaml;
        // id: string
        $options['id'] = Utils::getStr($yaml, 'id');
        // title, attribution: string or null
        foreach (['title', 'attribution'] as $key) {
            $options[$key] = Utils::getStr($yaml, $key, null);
        }
        // datasets, dataset overrides, features, legend, tile server, basemaps, start, overrides, view options, max bounds: array
        foreach (['datasets', 'dataset_overrides', 'features', 'legend', 'tile_server', 'basemaps', 'start', 'overrides', 'view_options', 'max_bounds'] as $key) {
            $options[$key] = Utils::getArr($yaml, $key);
        }
        // no attribution: bool or null
        $options['no_attribution'] = Utils::getType($yaml, 'no_attribution', 'is_bool');
        // column width, max zoom, min zoom: int or null
        foreach (['column_width', 'max_zoom', 'min_zoom'] as $key) {
            $options[$key] = Utils::getType($yaml, $key, 'is_int');
        }
        return $options;
    }
    /**
     * Validate data types of values inside yaml arrays for extra thoroughness
     * - Uses function to validate basic data types
     * - Validates datasets - id: string, add_all and include_all: bool or null
     * - Validates dataset_overrides - attribution: string or null if set, other values (legend, icon, path, etc.): array if set
     * - Validates features - id: string, remove_popup: bool or null, popup_content: string or null
     * - Validates legend - all values (include, toggles, basemaps, dark): bool or null
     * - Validates tile_server - all values (select, url, name, key, id, attribution): string or null if set
     * - Validates basemaps - strings (no keys)
     * - Validates start - distance, lng, lat: int or null if set, units: string or null if set, bounds: array
     * - Validates start bounds - all values (north, south, east, west): int or null if set
     * - Validates overrides - all values (map_on_right, show_map_location_in_url): bool or null if set
     * - Validates view_options - all values (remove_tile_server, only_show_view_features, list_popup_buttons): bool or null if set
     * - Validates max_bounds - all values (north, south, east, west): int or null if set
     * 
     * @param array $yaml
     * @return array
     */
    public static function validateYamlFull($yaml) {
        // validate data types - basic
        $options = self::validateYaml($yaml);
        // datasets - id, add_all, include_all
        $options['datasets'] = array_filter(array_map(function($input) {
            if (is_array($input)) {
                $output = $input;
                $output['id'] = Utils::getStr($input, 'id');
                foreach (['add_all', 'include_all'] as $key) {
                    $output[$key] = Utils::getType($input, $key, 'is_bool');
                }
                return $output;
            }
        }, $options['datasets']));
        // dataset overrides
        $options['dataset_overrides'] = array_filter(array_map(function($input) {
            if (is_array($input)) {
                $output = $input;
                if (array_key_exists('attribution', $input)) $output['attribution'] = Utils::getStr($input, 'attribution', null);
                // legend, icon, path - all toggleable, so just make sure they are arrays (also auto popup props)
                foreach (['legend', 'icon', 'path', 'active_path', 'border', 'active_border', 'auto_popup_properties'] as $key) {
                    if (array_key_exists($key, $input)) $output[$key] = Utils::getArr($input, $key);
                }
                return $output;
            }
        }, $options['dataset_overrides']));
        // features - id, remove_popup, popup_content
        $options['features'] = array_filter(array_map(function($input) {
            if (is_array($input)) {
                $output = $input;
                $output['id'] = Utils::getStr($input, 'id');
                $output['remove_popup'] = Utils::getType($input, 'remove_popup', 'is_bool');
                $output['popup_content'] = Utils::getStr($input, 'popup_content', null);
                return $output;
            }
        }, $options['features']));
        // legend
        foreach (['include', 'toggles', 'basemaps', 'dark'] as $key) {
            $options['legend'][$key] = Utils::getType($options['legend'], $key, 'is_bool');
        }
        // tile server - select, url, name, key, id, attribution --- all strings
        foreach (['select', 'url', 'name', 'key', 'id', 'attribution'] as $key) {
            if (array_key_exists($key, $options['tile_server'])) {
                // toggleable, only do something if set
                $options['tile_server'][$key] = Utils::getStr($options['tile_server'], $key, null);
            }
        }
        // basemaps - array of strings
        $options['basemaps'] = array_values(array_filter($options['basemaps'], function($input) {
            return is_string($input);
        }));
        // $options['basemaps'] = array_map(function($input) {
        //     if (is_string($input)) return $input;
        // }, $options['basemaps']);
        // start - location, distance, units, lng, lat, bounds (location already validated)
        foreach (['distance', 'lng', 'lat'] as $key) {
            if (array_key_exists($key, $options['start'])) $options['start'][$key] = Utils::getType($options['start'], $key, 'is_numeric');
        }
        $options['start']['units'] = Utils::getStr($options['start'], 'units', 'meters');
        $options['start']['bounds'] = Utils::getArr($options['start'], 'bounds');
        foreach (['north', 'south', 'east', 'west'] as $key) {
            if (array_key_exists($key, $options['start']['bounds'])) $options['start']['bounds'][$key] = Utils::getType($options['start']['bounds'], $key, 'is_numeric');
        }
        // overrides
        foreach (['map_on_right', 'show_map_location_in_url'] as $key) {
            if (array_key_exists($key, $options['overrides'])) $options['overrides'][$key] = Utils::getType($options['overrides'], $key, 'is_bool');
        }
        // view options
        foreach (['remove_tile_server', 'only_show_view_features', 'list_popup_buttons'] as $key) {
            if (array_key_exists($key, $options['view_options'])) $options['view_options'][$key] = Utils::getType($options['view_options'], $key, 'is_bool');
        }
        // max bounds
        foreach (['north', 'south', 'east', 'west'] as $key) {
            if (array_key_exists($key, $options['max_bounds'])) $options['max_bounds'][$key] = Utils::getType($options['max_bounds'], $key, 'is_numeric');
        }
        return $options;
    }
    /**
     * Returns only features with entries in all_features, indexed by id
     * 
     * @param array $yaml
     * @param array $all_features [id => Feature]
     * @return array modified and indexed yaml
     */
    public static function validateTourFeatures($yaml, $all_features) {
        return array_intersect_key(array_column(Utils::getArr($yaml, 'features'), null, 'id'), $all_features);
    }
    /**
     * Returns only datasets with entries in datasets, indexed by id
     * 
     * @param array $yaml
     * @param array $datasets [id => file] (all datasets)
     * @return array modified and indexed yaml
     */
    public static function validateTourDatasets($yaml, $datasets) {
        return array_intersect_key(array_column(Utils::getArr($yaml, 'datasets'), null, 'id'), $datasets);
    }
    /**
     * Returns only datasets with entries in datasets
     * Validates auto popup properties if set
     * 
     * @param array $yaml
     * @param array $datasets [id => Datasets] (only tour datasets)
     * @return array modified and indexed yaml
     */
    public static function validateDatasetOverrides($yaml, $datasets) {
        $overrides = [];
        // only tour datasets
        foreach (array_intersect_key(Utils::getArr($yaml, 'dataset_overrides'), $datasets) as $id => $override) {
            // if (!is_array($override)) continue; // TODO: Useful?
            // validate auto popup props (if set)
            $dataset = Utils::get($datasets, $id);
            // props should only contain values from dataset properties, could also contain none
            // but only modify this value if it was already set, otherwise it will cause problems
            if (array_key_exists('auto_popup_properties', $override)) {
                $props = Utils::getArr($override, 'auto_popup_properties', []);
                $props = array_values(array_intersect($props, array_merge($dataset->getProperties(), ['none'])));
                $overrides[$id] = array_merge($override, ['auto_popup_properties' => $props]);
            }
            else $overrides[$id] = $override;
        }
        return $overrides;
    }
    /**
     * Returns start with location equal to valid id or null
     * 
     * @param array $yaml
     * @param array $point_ids [string]
     * @return array modified yaml
     */
    public static function validateStartLocation($yaml, $point_ids) {
        $start = Utils::getArr($yaml, 'start');
        $start['location'] = Utils::getStr($start, 'location');
        if (!in_array($start['location'], $point_ids)) $start['location'] = null;
        return $start;
    }
    /**
     * Modifies popup content markdown image paths
     * 
     * @param array $features [id => yaml]
     * @param string $path
     * @return array modified yaml
     */
    public static function validateFeaturePopupContent($features, $path) {
        $new_list = [];
        foreach ($features as $id => $feature) {
            $content = Feature::modifyPopupImagePaths(Utils::get($feature, 'popup_content'), $path);
            $new_list[$id] = array_merge($feature, ['popup_content' => $content]);
        }
        return $new_list;
    }
    /**
     * Modifies auto popup properties to match renamed properties
     * 
     * @param array $dataset_overrides [id => yaml]
     * @param string $dataset_id
     * @param array $renamed_properties
     * @return array modified yaml
     */
    public static function validateRenamedProperties($dataset_overrides, $dataset_id, $renamed_properties) {
        // get override for the dataset
        $override = Utils::getArr($dataset_overrides, $dataset_id);
        if (array_key_exists('auto_popup_properties', $override)) {
            $old_props = Utils::getArr($override, 'auto_popup_properties');
            $new_props = [];
            foreach ($old_props as $prop) {
                if ($p = Utils::getStr($renamed_properties, $prop)) $new_props[] = $p;
            }
            $override['auto_popup_properties'] = $new_props;
            return array_merge($dataset_overrides, [$dataset_id => $override]);
        }
        else return $dataset_overrides;
    }

    // get/build/prep values - for validation or otherwise
    /**
     * Get basemap info from plugin config, indexed by file
     * 
     * @param array $plugin_config
     * @return array
     */
    public static function getConfigBasemapInfo($plugin_config) {
        return array_column(Utils::getArr($plugin_config, 'basemap_info'), null , 'file');
    }
    /**
     * turn [id => file] into limited list of [id => Dataset]
     * - Returns only datasets included in tour (as objects)
     * 
     * @param $tour_datasets [id => yaml]
     * @param $datasets [id => file]
     * @return [id => Dataset] (tour only)
     */
    public static function getDatasetObjects($tour_datasets, $datasets) {
        // order datasets correctly
        $tmp_datasets = array_merge($tour_datasets, $datasets);
        // limit to only tour datasets
        $datasets = array_intersect_key($tmp_datasets, $datasets, $tour_datasets);
        // turn into dataset objects
        $datasets = array_map(function($file) { return Dataset::fromFile($file); }, $datasets);
        return $datasets;
    }
    /**
     * all features from datasets provided
     * - Returns all feature objects from all datasets included in tour
     * 
     * @param array $datasets [id => Dataset] (tour only)
     * @return array
     */
    public static function getFeatureObjects($datasets) {
        $features = [];
        foreach ($datasets as $id => $dataset) {
            $features = array_merge($features, $dataset->getFeatures());
        }
        return $features;
    }
    /**
     * included features
     * - Returns feature objects for all features included in tour features list
     * - Also adds any non-hidden features from datasets with include_all to the end (assuming feature not already in list)
     * 
     * @param array $tour_features [id => yaml]
     * @param array $tour_datasets [id => yaml]
     * @param array $datasets [id => Dataset] (tour only)
     * @return array [id => Feature]
     */
    public static function prepareIncludedFeatures($tour_features, $tour_datasets, $datasets) {
        $included = [];
        // loop through all features from all datasets
        foreach ($tour_datasets as $dataset_id => $dataset_options) {
            foreach ($datasets[$dataset_id]->getFeatures() as $id => $feature) {
                // add feature if: feature is already in the list (needs to be updated from tour feature options to actual Feature object) or the dataset has include_all and the feature is not hidden
                if (array_key_exists($id, $tour_features) || (Utils::get($dataset_options, 'include_all') && !$feature->isHidden())) $included[$id] = $feature;
            }
        }
        // put included in correct order
        $correct_order = array_merge($tour_features, $included);
        // limit to only included (just in case)
        $included = array_intersect_key($correct_order, $included);
        return $included;
    }
    /**
     * Merges dataset overrides with dataset objects
     * Only returns datasets with at least one included feature
     * 
     * @param array $included_features [id => Feature]
     * @param array $datasets [id => Dataset] (tour only)
     * @param array $dataset_overrides [id => yaml]
     * @return array
     */
    public static function prepareMergedDatasets($included_features, $datasets, $dataset_overrides) {
        $merged_datasets = [];
        foreach ($datasets as $id => $dataset) {
            // is the dataset actually used? (at least one feature included)
            if (!empty(array_intersect(array_keys($datasets[$id]->getFeatures()), array_keys($included_features)))) {
                // create and add merged dataset
                $merged_datasets[$id] = Dataset::fromTour($datasets[$id], Utils::getArr($dataset_overrides, $id));
            }
        }
        return $merged_datasets;
    }
    /**
     * Validates selected options, combines with defaults, and only returns relevent information
     * - Validates tile server selection - must have url for custom, must have name for provider
     * - Only uses settings from plugin config as default if tile server itself is not set in the tour
     * - Only returns relevant values
     * - Uses 'placeholder' as default attribution if server is anything other than custom
     * 
     * @param array $tile_server (yaml)
     * @param array $plugin_config
     * @return array
     */
    public static function prepareTileServerOptions($tile_server, $plugin_config) {
        // tour options
        $settings = $tile_server;
        // is tile server set by tour?
        $server = self::getServerSelection($tile_server);
        if (!$server) {
            // combine tour and plugin options
            $config = Utils::getArr($plugin_config, 'tile_server');
            $server = self::getServerSelection($config) ?? ['provider' => self::DEFAULT_TILE_SERVER];
            $settings = array_merge($config, $settings);
        }
        // remove extraneous settings values
        $settings = array_diff_key($settings, array_flip(['select', 'name', 'url']));
        // set default attribution
        if (Utils::getStr($server, 'provider') && !Utils::getStr($settings, 'attribution')) {
            $settings['attribution'] = 'placeholder';
        }
        // combine settings and selected server
        return array_merge($settings, $server);
    }
    /**
     * Validates tile server selection: If custom, url must be provided, return url. If other, name must be provided, return as provider. Otherwise return selection as provider.
     * 
     * @param array $options [select, url, name, ...]
     * @return array|null
     */
    private static function getServerSelection($options) {
        if ($select = Utils::getStr($options, 'select')) {
            if ($select === 'custom') {
                if ($url = Utils::getStr($options, 'url')) return ['url' => $url];
            } else if ($select === 'other') {
                if ($name = Utils::getStr($options, 'name')) return ['provider' => $name];
            } else {
                return ['provider' => $select];
            }
        }
        return null;
    }
    /**
     * Returns list of feature ids for all Point datasets
     * 
     * @param array $datasets [id => Dataset] (tour only)
     * @return array
     */
    public static function preparePointIds($datasets) {
        $ids = [];
        foreach (array_values($datasets) as $dataset) {
            if ($dataset->getType() === 'Point') $ids = array_merge($ids, array_keys($dataset->getFeatures()));
        }
        return $ids;
    }
    /**
     * Returns id => name for any feature that has popup content, based on dataset and tour settings:
     * - If popup content set by tour
     * - If popup content set by dataset and not removed by tour
     * - If auto popup set by tour/dataset
     * 
     * @param array $included_features [id => Feature]
     * @param array $tour_features [id => yaml]
     * @param array $datasets [id => Dataset] (tour only, not merged)
     * @param array $dataset_overrides [id => yaml]
     * @return array [id => name]
     */
    public static function prepareFeaturePopups($included_features, $tour_features, $datasets, $dataset_overrides) {
        $popups = [];
        foreach ($included_features as $id => $feature) {
            $tour_feature = Utils::getArr($tour_features, $id);
            // popup from tour or dataset?
            if ((Utils::getStr($tour_feature, 'popup_content')) || (!Utils::getType($tour_feature, 'remove_popup', 'is_bool') && $feature->getPopup())) $popups[$id] = $feature->getName();
            // auto popup from tour or dataset?
            else {
                $dataset_id = $feature->getDatasetId();
                $overrides = Utils::getArr($dataset_overrides, $dataset_id);
                $props = Utils::getArr($overrides, 'auto_popup_properties', null) ?? $datasets[$dataset_id]->getAutoPopupProperties();
                if (!empty($feature->getAutoPopupProperties($props))) $popups[$id] = $feature->getName();
            }
        }
        return $popups;
    }
    /**
     * Create list of all features that have popup content, based on dataset and tour settings:
     * - Only includes features that have auto or regular popup set
     * - Auto popup properties for determining existence/content of auto popup are determined by merged dataset
     * - Tour 'popup_content' overrides popup content or lack thereof provided by feature
     * - If tour does not have 'popup_content' but does have 'remove_popup' any content provided by feature is ignored, otherwise used
     * 
     * @param array $included_features [id => Feature]
     * @param array $tour_features [id => yaml]
     * @param array $datasets [id => Dataset] (merged datasets)
     * @return array [id => [name, auto, x]]
     */
    public static function prepareFeaturePopupContent($included_features, $tour_features, $datasets) {
        $popups = [];
        foreach ($included_features as $id => $feature) {
            $tour_feature = Utils::getArr($tour_features, $id);
            $popup = Utils::getStr($tour_feature, 'popup_content', null);
            if (!$popup && !Utils::get($tour_feature, 'remove_popup')) $popup = $feature->getPopup();
            $auto = $feature->getAutoPopupProperties($datasets[$feature->getDatasetId()]->getAutoPopupProperties());
            if ($popup || !empty($auto)) {
                $popups[$id] = [
                    'name' => $feature->getName(),
                    'auto' => $auto,
                    'popup' => $popup,
                ];
            }
        }
        return $popups;
    }
    /**
     * Combines tour overrides with options from plugin config and defaults
     * 
     * @param array $overrides from yaml
     * @param array $plugin_config
     * @return array
     */
    public static function prepareOverrides($tour_overrides, $plugin_config) {
        $config = Utils::getArr($plugin_config, 'tour_options');
        $overrides = [];
        foreach (self::DEFAULT_OVERRIDES as $key => $value) {
            $overrides[$key] = Utils::getType($tour_overrides, $key, 'is_bool') ?? Utils::getType($config, $key, 'is_bool') ?? $value;
        }
        return $overrides;
    }

    // other
    /**
     * Loop through all views, validate yaml, and save file
     * 
     * @param array $views [id => file]
     * @param array $point_ids
     * @param array $included_ids
     * @param array $popups [id => name]
     * @return void
     */
    public static function updateViews($views, $point_ids, $included_ids, $popups) {
        foreach ($views as $id => $file) {
            $file->header(View::validateTourUpdate($file->header(), $point_ids, $included_ids, $popups));
            $file->save();
        }
    }
    /**
     * For datasets with add_all: Any features in the dataset that are not hidden and are not already in the features list are added to the end of the features list
     * 
     * @param array $tour_features yaml, already validated, indexed by id
     * @param array $features [id => Feature]
     * @param array $tour_datasets yaml, already validated, indexed by id
     * @param array $datasets [id => Dataset] (only tour)
     * @return array
     */
    public static function addAllFeatures($tour_features, $tour_datasets, $datasets) {
        $list = $tour_features;
        foreach ($tour_datasets as $id => $options) {
            if (Utils::getType($options, 'add_all', 'is_bool')) {
                // add features to tour features list - must not already be in list and must not be hidden
                foreach ($datasets[$id]->getFeatures() as $feature_id => $feature) {
                    if (!array_key_exists($feature_id, $tour_features) && !$feature->isHidden()) $list[$feature_id] = ['id' => $feature_id];
                }
            }
        }
        return $list;
    }

    // calculated getters for template
    /**
     * If tour legend is to be included, combines appropriate data from each included/merged dataset to pass to template for creating legend. Note: Datasets must have legend text in order to be included.
     * - Returns empty array if legend 'include' is false
     * - Only returns datasets with legend text
     * - All datasets have id, symbol_alt, text, and class
     * - Point datasets have icon, width, and height
     * - Point datasets have modified class
     * - Shape datasets have polygon, stroke, fill, and border
     * 
     * @return array [[array of dataset legend info], ...]
     */
    public function getLegendDatasets() {
        $legend = [];
        if ($this->getLegend()['include']) {
            foreach ($this->getDatasets() as $id => $dataset) {
                if ($text = $dataset->getLegend('text')) {
                    $info = [
                        'id' => $id,
                        'symbol_alt' => $dataset->getLegend('symbol_alt'),
                        'text' => $text,
                        'class' => 'dataset',
                    ];
                    if ($dataset->getType() === 'Point') {
                        $options = $dataset->getIconOptions();
                        $info['icon'] = $options['iconUrl'];
                        $info['width'] = $options['iconSize'][0];
                        $info['height'] = $options['iconSize'][1];
                        $info['class'] .= ' ' . $options['className'];
                    } else {
                        $info['polygon'] = str_contains($dataset->getType(), 'Polygon');
                        $info['stroke'] = $dataset->getStrokeOptions();
                        $info['fill'] = $dataset->getFillOptions();
                        $info['border'] = $dataset->getBorderOptions();
                    }
                    $legend[] = $info;
                }
            }
        }
        return $legend;
    }
    /**
     * If tour legend is to be included and basemaps are to be included on it, combines appropriate data from each basemap in the previously generated info list to pass to template for creating legend. Note: Basemaps must have name or legend in order to be included.
     * - Returns empty array if legend 'include' or 'basemaps' is false
     * - Only returns basemaps with name or legend
     * - All basemaps have file, text, icon, and class
     * - Basemaps use icon if provided, otherwise the file itself
     * 
     * @return array [file, text, icon, class]
     */
    public function getLegendBasemaps() {
        $legend = [];
        $legend_options = $this->getLegend();
        if ($legend_options['include'] && $legend_options['basemaps']) {
            foreach ($this->getBasemapInfo() as $file => $basemap) {
                if ($text = Utils::getStr($basemap, 'legend') ?: Utils::getStr($basemap, 'name')) {
                    $info = [
                        'file' => $file,
                        'text' => $text,
                        'icon' => Utils::BASEMAP_ROUTE,
                        'class' => 'basemap',
                    ];
                    // Use icon if provided
                    if ($icon = Utils::getStr($basemap, 'icon')) $info['icon'] .= "icons/$icon";
                    else $info['icon'] .= $file;
                    $legend[] = $info;
                }
            }
        }
        return $legend;
    }
    /**
     * Returns information to pass to template/javascript in order to set up tour
     * 
     * @return array [map_on_right, show_map_location_in_url, tile_server, max_zoom, min_zoom, max_bounds]
     */
    public function getTourData() {
        return array_merge($this->getOverrides(), [
            'tile_server' => $this->getTileServer(),
            'max_zoom' => $this->getMaxZoom(),
            'min_zoom' => $this->getMinZoom(),
            'max_bounds' => Utils::getBounds($this->getMaxBounds()),
        ]);
    }
    /**
     * Combines appropriate information from basemap info list (all basemap in tour and views) to pass to template/javascript in order to add them to the map
     * - Uses function to validate bounds
     * 
     * @return array [id => [url, bounds, options => [max_zoom, min_zoom]], ...]
     */
    public function getBasemapData() {
        return array_filter(array_map(function($info) {
            if ($bounds = Utils::getBounds(Utils::getArr($info, 'bounds'))) return [
                'url' => Utils::BASEMAP_ROUTE . $info['file'],
                'bounds' => Utils::getBounds(Utils::getArr($info, 'bounds')),
                'options' => [
                    'max_zoom' => Utils::getType($info, 'max_zoom', 'is_int'),
                    'min_zoom' => Utils::getType($info, 'min_zoom', 'is_int'),
                ],
            ];
            else return null;
        }, $this->getBasemapInfo()));
    }
    /**
     * Combines appropriate information from datasets to pass to template/javascript in order to set up tour
     * - Returns icon or shape options as appropriate for dataset type
     * 
     * @return array [id => [id, features => [], legend_summary, icon or various shape options], ...]
     */
    public function getDatasetData() {
        return array_map(function($dataset) {
            $info = [
                'id' => $dataset->getId(),
                'features' => [],
                'legend_summary' => $dataset->getLegend('summary'),
            ];
            if ($dataset->getType() === 'Point') {
                return array_merge($info, ['icon' => $dataset->getIconOptions()]);
            } else {
                return array_merge($info, $dataset->getShapeOptions());
            }
        }, $this->getDatasets());
    }
    /**
     * Combines appropriate information from all included features to pass to template/javascript in order to set up tour
     * - Returns valid geojson features
     * - Includes properties: id, name, dataset, has_popup
     * 
     * @return array [id => [type, geometry => [type, coordinates], properties => [id, name, dataset, has_popup]], ...]
     */
    public function getFeatureData() {
        return array_map(function($feature) {
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => $feature->getType(),
                    'coordinates' => $feature->getJsonCoordinates(),
                ],
                'properties' => [
                    'id' => $feature->getId(),
                    'name' => $feature->getName(),
                    'dataset' => $feature->getDatasetId(),
                    'has_popup' => in_array($feature->getId(), array_keys($this->getFeaturePopups())),
                ],
            ];
        }, $this->getFeatures());
    }
    /**
     * Combines appropriate data for tour and its views to pass to template/javascript for setting up scrollama/views. Tour uses id '_tour'
     * - Begins with basic tour data, using id '_tour'
     * - Includes view data from all views
     * 
     * @return array [id => [features, basemaps, remove_tile_server, bounds, only_show_view_features], ...]
     */
    public function getViewData() {
        // start with tour
        $views = ['_tour' => [
            'features' => [],
            'basemaps' => $this->getBasemaps(),
            'remove_tile_server' => $this->getViewOptions()['remove_tile_server'],
            'bounds' => $this->getStartingBounds(),
        ]];
        // add views
        foreach ($this->getViews() as $id => $view) {
            $views[$id] = $view->getViewData($this->getBasemaps());
        }
        return $views;
    }
    /**
     * Checks if user set additional value 'body_classes' in tour yaml. Also adds class for 'map-on-left' if setting is applicable
     * 
     * @return string
     */
    public function getBodyClasses() {
        // TODO: Accept body_classes as an array
        $classes = Utils::getStr($this->getExtras(), 'body_classes');
        if (!$this->getOverrides()['map_on_right']) $classes .= ' map-on-left';
        return $classes;
    }
    /**
     * Pretty useless. Remove eventually.
     * 
     * @return bool
     */
    public function getLegendToggles() {
        return $this->getLegend()['toggles'];
    }
    /**
     * Returns value for this tour's attribution if set, otherwise default value from plugin config
     * 
     * @return string|null
     */
    public function getTourAttribution() {
        return $this->getAttribution() ?? Utils::getStr(Utils::getArr($this->getPluginConfig(), 'tour_options'), 'attribution', null);
    }
    /**
     * Pretty useless. Remove eventually.
     * 
     * @return string|null
     */
    public function getTileServerAttribution() {
        return Utils::getStr($this->getTileServer(), 'attribution', null);
    }
    /**
     * Returns attribution info for all included basemaps (if set)
     * 
     * @return array [string]
     */
    public function getBasemapsAttribution() {
        return array_filter(array_column($this->getBasemapInfo(), 'attribution'));
    }
    /**
     * Returns attribution info for all included datasets (if set)
     * 
     * @return array [string]
     */
    public function getDatasetsAttribution() {
        $datasets = [];
        foreach ($this->getDatasets() as $id => $dataset) {
            if ($attr = $dataset->getAttribution()) $datasets[] = $attr;
        }
        return $datasets;
    }
    /**
     * Returns column width if set, otherwise value from plugin config if set, otherwise 33
     * 
     * @return int
     */
    public function getColumnWidth() {
        return $this->column_width ?? Utils::getType(Utils::getArr($this->plugin_config, 'tour_options'), 'column_width', 'is_numeric', 33);
    }
    /**
     * Returns tour's view options merged with defaults
     * 
     * @return array
     */
    public function getViewOptions() {
        return array_merge(View::DEFAULT_OPTIONS, $this->view_options);
    }
    /**
     * Returns legend options merged with defaults
     * 
     * @return array
     */
    public function getLegend() {
        return array_merge(self::DEFAULT_LEGEND, $this->legend);
    }

    // simple getters
    /**
     * @return array
     */
    public function getViews() { return $this->views; }
    /**
     * @return array
     */
    public function getPluginConfig() { return $this->plugin_config; }
    // Getters for values from yaml
    /**
     * @return string|null
     */
    public function getAttribution() { return $this->attribution; }
    /**
     * @return array
     */
    public function getDatasets() { return $this->datasets; }
    /**
     * @return array
     */
    public function getFeatures() { return $this->features; }
    /**
     * @return array
     */
    public function getFeaturePopups() { return $this->feature_popups; }
    /**
     * @return array
     */
    public function getTileServer() { return $this->tile_server; }
    /**
     * @return array
     */
    public function getBasemaps() { return $this->basemaps; }
    /**
     * @return array
     */
    public function getBasemapInfo() { return $this->basemap_info; }
    /**
     * @return array|null
     */
    public function getStartingBounds() { return $this->starting_bounds; }
    /**
     * @return array
     */
    public function getOverrides() { return $this->overrides; }
    /**
     * @return array
     */
    public function getMaxBounds() { return $this->max_bounds; }
    /**
     * @return array
     */
    public function getExtras() { return $this->extras; }
    /**
     * @return bool|null
     */
    public function hasNoAttribution() { return $this->no_attribution; }
    /**
     * @return int|null
     */
    public function getMaxZoom() { return $this->max_zoom; }
    /**
     * @return int|null
     */
    public function getMinZoom() { return $this->min_zoom; }
}
?>