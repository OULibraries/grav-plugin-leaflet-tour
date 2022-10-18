<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;

/**
 * @property MarkdownFile|null $file Provided in constructor
 * @property array $views Provided in constructor, ['id' => View]
 * @property array $plugin_config Provided in constructor
 * @property string $id From yaml
 * @property string|null $title From yaml
 * @property string|null $attribution From yaml
 * @property array $datasets [id => [id, add_all, include_all], ...]: From yaml, modified: indexed by id
 * @property array $dataset_overrides [id => array]: From yaml
 * @property array $features [id => [id, popup_content, remove_popup], ...]: From yaml, modified: indexed by id
 * @property array $legend [include, toggles, basemaps, dark]: From yaml
 * @property array $tile_server [select, url, name, key, id, attribution]: From yaml
 * @property array $basemaps [string]: From yaml
 * @property array $start [bounds => array, distance, units, location, lng, lat]: From yaml
 * @property array $overrides [map_on_right, show_map_location_in_url]: From yaml
 * @property array $view_options [remove_tile_server, only_show_view_features, list_popup_buttons]: From yaml
 * @property array $max_bounds [north, south, east, west]: From yaml
 * @property array $extras [key => value]: From yaml
 * @property bool $no_attribution From yaml
 * @property int|null $column_width From yaml
 * @property int|null $max_zoom From yaml
 * @property int|null $min_zoom From yaml
 * @property array $valid_basemaps [string]: Calculated in constructor, for future validation
 * @property array $point_ids [string]: Calculated in constructor, for future validation
 * @property array $included_features [id => Feature]: Calculated in constructor, for returning data
 * @property array $merged_datasets [id => Dataset]: Calculated in constructor, for returning data
 * @property array $feature_popups [id => [name, auto, popup]]: Calculated in constructor, for returning data
 * @property array $basemap_info [filename => array]: Calculated in constructor, for returning data
 * @property array $tile_server_options [provider or url, key, id, attribution]: Calculated in constructor, for returning data
 * @property array|null $starting_bounds Calculated in constructor, for returning data
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

    private $file, $views, $plugin_config, $id, $title, $attribution, $datasets, $dataset_overrides, $features, $legend, $tile_server, $basemaps, $start, $overrides, $view_options, $max_bounds, $extras, $no_attribution, $column_width, $max_zoom, $min_zoom, $valid_basemaps, $point_ids, $included_features, $merged_datasets, $feature_popups, $basemap_info, $tile_server_options, $starting_bounds;

    /**
     * Sets and validates all provided options, and sets some additional values for future reference/validation.
     * - All tour datasets must be included in $datasets
     * - All tour dataset overrides must reference datasets in tour datasets, must be otherwise valid
     * - All tour features must be valid, also modified tour features list for any datasets with 'add_all' true
     * - Start location (if set) must be valid Point feature
     * - All tour basemaps must be included in plugin basemap info list
     * - Build list of point ids (all Point features) for future tour/view start.location validation
     * - Build included features list for ... all the reasons, really
     * - Build merged datasets list for generating dataset info, legend info, and attribution info
     * - Build feature popups list for generating info for tour and for views
     * - Build valid basemaps list for future tour/view validation
     * - Build basemap info list for generating basemap data and attributions (and include all basemaps from views, too)
     * - Build tile server options for future reference
     * - Set starting bounds for future reference
     * 
     * @param array $options Yaml from tour file header
     * @param array $views [id => MarkdownFile] All views included in the tour
     * @param array $plugin_config Yaml from plugin config file, for determining valid basemaps and providing various default tour options (tile server, attribution, etc.)
     * @param array $datasets [id => MarkdownFile] All datasets
     */
    public function __construct($options, $views, $plugin_config, $datasets) {
        // set and validate all the basic/easy stuff first
        // validate file - file does not have to exist, but must be an object with a valid function called "exists" - There is probably a better way to check that this is a MarkdownFile, but this works for now
        try {
            $file = Utils::get($options, 'file');
            $file->exists();
            $this->file = $file;
        } catch (\Throwable $t) {
            $this->file = null;
        }
        $this->plugin_config = $plugin_config;
        $this->id = Utils::getStr($options, 'id'); // just in case, but should always be provided
        // strings
        foreach (['title', 'attribution'] as $key) {
            $this->$key = Utils::getStr($options, $key, null);
        }
        // arrays
        foreach (['legend', 'tile_server', 'overrides', 'view_options', 'max_bounds'] as $key) {
            $this->$key = Utils::getArr($options, $key);
        }
        // no attribution
        $this->no_attribution = (Utils::get($options, 'no_attribution') === true);
        // ints
        foreach (['column_width', 'max_zoom', 'min_zoom'] as $key) {
            $this->$key = Utils::getType($options, $key, 'is_int');
        }
        // extras
        $keys = ['file', 'id', 'title', 'attribution', 'datasets', 'dataset_overrides', 'features', 'legend', 'tile_server', 'basemaps', 'start', 'overrides', 'view_options', 'max_bounds', 'no_attribution', 'column_width', 'max_zoom', 'min_zoom'];
        $this->extras = array_diff_key($options, array_flip($keys));

        // start with datasets
        // datasets - must be array, can only contain ids that have valid files
        $this->datasets = array_intersect_key(array_column(Utils::getArr($options, 'datasets'), null, 'id'), $datasets);
        // dataset objects (temporary) - turn file into object, only for datasets that are included in tour datasets list
        $datasets = array_map(function($file) { return Dataset::fromFile($file); }, array_intersect_key($datasets, $this->datasets));
        // dataset overrides
        $this->dataset_overrides = self::validateDatasetOverrides(Utils::getArr($options, 'dataset_overrides'), $datasets);

        // start with features
        $features = []; // all feature objects (temporary)
        foreach (array_values($datasets) as $dataset) {
            $features = array_merge($features, $dataset->getFeatures());
        }
        $this->features = self::buildFeaturesList(Utils::getArr($options, 'features'), $features, $this->datasets, $datasets);
        $this->point_ids = self::buildPointIDList($datasets);
        $this->included_features = self::buildIncludedFeaturesList($this->features, $datasets, $this->datasets);
        $this->merged_datasets = self::buildTourDatasets($datasets, $this->datasets, $this->dataset_overrides, $this->included_features);
        $this->feature_popups = self::buildPopupsList($this->included_features, $this->features, $this->merged_datasets);

        // validate start - must be array, location must be valid point feature or 'none'
        $this->start = Utils::getArr($options, 'start');
        // location must be a string, must reference a valid feature, and that feature must be a point
        if (!Utils::getStr($this->start, 'location') || !in_array(Utils::get($this->start, 'location'), $this->point_ids)) $this->start['location'] = 'none';
        // get starting bounds
        $this->starting_bounds = View::calculateStartingBounds($this->start, Utils::get($features, $this->start['location']));

        // basemaps
        // basemap info (temporary) - straight from plugin config
        $basemap_info = array_column(Utils::getArr($plugin_config, 'basemap_info'), null ,'file');
        // valid basemaps
        $this->valid_basemaps = array_keys($basemap_info);
        // basemaps - validate
        $this->basemaps = array_values(array_intersect(Utils::getArr($options, 'basemaps'), $this->valid_basemaps));

        // views (and basemap info list)
        $basemaps = $this->basemaps;
        $this->views = [];
        foreach ($views as $id => $file) {
            $view = View::fromTour($file, $this->valid_basemaps, $this->point_ids, $features, array_keys($this->included_features), $this->feature_popups);
            $basemaps = array_merge($basemaps, $view->getBasemaps());
            $this->views[$id] = $view;
        }
        $info = array_merge(array_flip($basemaps), $basemap_info);
        $this->basemap_info = array_intersect_key($info, array_flip($basemaps));

        $this->tile_server_options = self::calculateTileServer($this->tile_server, $plugin_config);
    }

    /**
     * Creates a new tour from a valid markdown file
     * 
     * @param MarkdownFile $file The tour file
     * @param array $views [id => file]
     * @param array $plugin_config Yaml
     * @param array $datasets [id => file]
     * @return Tour
     */
    public static function fromFile($file, $views, $plugin_config, $datasets) {
        $options = array_merge($file->header(), ['file' => $file]);
        return new Tour($options, $views, $plugin_config, $datasets);
    }
    /**
     * Creates a new tour from an array (equivalent to yaml from markdown file header)
     * 
     * @param array $options Yaml from tour file header
     * @param array $views [id => file]
     * @param array $plugin_config Yaml
     * @param array $datasets [id => file]
     * @return Tour
     */
    public static function fromArray($options, $views, $plugin_config, $datasets) {
        return new Tour($options, $views, $plugin_config, $datasets);
    }

    /**
     * Validates a view yaml array by creating new View with it and necessary validation data.
     * 
     * @param array $view_update Yaml update data
     * @param string $view_id The view to update - only matters in that this would overwrite any new id value provided in $view_update
     * @return array Yaml content generated by the new View object
     */
    public function validateViewUpdate($view_update, $view_id) {
        $view = View::fromArray(array_merge($view_update, ['id' => $view_id]), $this->getValidBasemaps(), $this->getPointIds(), array_keys($this->getIncludedFeatures()), $this->getFeaturePopups());
        return $view->toYaml();
    }

    /**
     * Returns content for the tour page header
     * 
     * @return array
     */
    public function toYaml() {
        return array_merge($this->getExtras(), [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'datasets' => array_values($this->datasets),
            'dataset_overrides' => $this->dataset_overrides,
            'legend' => $this->legend,
            'column_width' => $this->column_width,
            'overrides' => $this->getOverrides(),
            'attribution' => $this->getAttribution(),
            'tile_server' => $this->tile_server,
            'basemaps' => $this->getBasemaps(),
            'start' => $this->start,
            'view_options' => $this->view_options,
            'max_bounds' => $this->getMaxBounds(),
            'max_zoom' => $this->getMaxZoom(),
            'min_zoom' => $this->getMinZoom(),
            'no_attribution' => $this->no_attribution,
            'features' => array_values($this->features),
        ]);
    }

    // calculated getters for template
    /**
     * If tour legend is to be included, combines appropriate data from each included/merged dataset to pass to template for creating legend. Note: Datasets must have legend text in order to be included.
     * - all datasets: [id, symbol_alt, text, class]
     * - point datasets: [icon, width, height] (and modified class)
     * - shape datasets: [polygon => bool, stroke => array, fill => array, border => array]
     * 
     * @return array [[array of dataset legend info], ...]
     */
    public function getLegendDatasets() {
        $legend = [];
        if ($this->getLegendOptions()['include']) {
            foreach ($this->getMergedDatasets() as $id => $dataset) {
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
     * 
     * @return array [file, text, icon, class]
     */
    public function getLegendBasemaps() {
        $legend = [];
        $legend_options = $this->getLegendOptions();
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
     * Returns information to pass to template/javascript in order to set up tour.
     * 
     * @return array [map_on_right, show_map_location_in_url, tile_server, max_zoom, min_zoom, max_bounds]
     */
    public function getTourData() {
        return array_merge(self::calculateOverrides($this->getOverrides(), $this->getPluginConfig()), [
            'tile_server' => $this->getTileServerOptions(),
            'max_zoom' => $this->getMaxZoom(),
            'min_zoom' => $this->getMinZoom(),
            'max_bounds' => Utils::getBounds($this->getMaxBounds()),
        ]);
    }
    /**
     * Combines appropriate information from basemap info list (all basemap in tour and views) to pass to template/javascript in order to add them to the map
     * 
     * @return array [id => [url, bounds, options => [max_zoom, min_zoom]], ...]
     */
    public function getBasemapData() {
        return array_map(function($info) {
            return [
                'url' => Utils::BASEMAP_ROUTE . $info['file'],
                'bounds' => Utils::getBounds(Utils::getArr($info, 'bounds')),
                'options' => [
                    'max_zoom' => Utils::getType($info, 'max_zoom', 'is_int'),
                    'min_zoom' => Utils::getType($info, 'min_zoom', 'is_int'),
                ],
            ];
        }, $this->getBasemapInfo());
    }
    /**
     * Combines appropriate information from datasets to pass to template/javascript in order to set up tour
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
        }, $this->getMergedDatasets());
    }
    /**
     * Combines appropriate information from all included features to pass to template/javascript in order to set up tour
     * 
     * @return array [id => [type, geometry => [type, coordinates], properties => [id, name, dataset, has_popup]], ...]
     */
    public function getFeatureData() {
        return array_map(function($feature) {
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => $feature->getType(),
                    'coordinates' => $feature->getCoordinates(),
                ],
                'properties' => [
                    'id' => $feature->getId(),
                    'name' => $feature->getName(),
                    'dataset' => $feature->getDatasetId(),
                    'has_popup' => in_array($feature->getId(), array_keys($this->getFeaturePopups())),
                ],
            ];
        }, $this->getIncludedFeatures());
    }
    /**
     * Combines appropriate data for tour and its views to pass to template/javascript for setting up scrollama/views. Tour uses id '_tour'
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
            $views[$id] = $view->getViewData($this->getViewOptions(), $this->getBasemaps());
        }
        return $views;
    }
    /**
     * Checks if user set additional value 'body_classes' in tour yaml. Also adds class for 'map-on-left' if setting is applicable
     * 
     * @return string
     */
    public function getBodyClasses() {
        $classes = Utils::getStr($this->getExtras(), 'body_classes');
        if (!self::calculateOverrides($this->getOverrides(), $this->getPluginConfig())['map_on_right']) $classes .= ' map-on-left';
        return $classes;
    }
    /**
     * Pretty useless. Remove eventually.
     * 
     * @return bool
     */
    public function getLegendToggles() {
        return $this->getLegendOptions()['toggles'];
    }
    /**
     * Returns value for this tour's attribution if set, otherwise default value from plugin config
     * 
     * @return string|null
     */
    public function getTourAttribution() {
        return $this->getAttribution() ?? Utils::getStr(Utils::getArr($this->getPluginConfig(), 'tour_options'), 'attribution');
    }
    /**
     * Pretty useless. Remove eventually.
     * 
     * @return string|null
     */
    public function getTileServerAttribution() {
        return Utils::getStr($this->getTileServerOptions(), 'attribution', null);
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
        foreach ($this->getMergedDatasets() as $id => $dataset) {
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
    public function getLegendOptions() {
        return array_merge(self::DEFAULT_LEGEND, $this->legend);
    }

    // simple getters
    /**
     * @return MarkdownFile|null
     */
    public function getFile() { return $this->file; }
    /**
     * @return array
     */
    public function getViews() { return $this->views; }
    /**
     * @return array
     */
    protected function getPluginConfig() { return $this->plugin_config; }
    // Getters for values from yaml
    /**
     * @return string
     */
    public function getId() { return $this->id; }
    /**
     * @return string|null
     */
    public function getTitle() { return $this->title; }
    /**
     * @return string|null
     */
    public function getAttribution() { return $this->attribution; }
    /**
     * @return array
     */
    public function getBasemaps() { return $this->basemaps; }
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
     * @return int|null
     */
    public function getMaxZoom() { return $this->max_zoom; }
    /**
     * @return int|null
     */
    public function getMinZoom() { return $this->min_zoom; }
    // Getters for values generated in constructor
    /**
     * @return array
     */
    public function getIncludedFeatures() { return $this->included_features; }
    /**
     * @return array
     */
    public function getFeaturePopups() { return $this->feature_popups; }
    /**
     * @return array
     */
    public function getMergedDatasets() { return $this->merged_datasets; }
    /**
     * @return array
     */
    public function getTileServerOptions() { return $this->tile_server_options; }
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
    protected function getPointIds() { return $this->point_ids; }
    /**
     * @return array
     */
    protected function getValidBasemaps() { return $this->valid_basemaps; }

    /**
     * Combines tour overrides with options from plugin config tour options with defaults
     * 
     * @param array $tour_overrides Tour yaml 'overrides' array
     * @param array $plugin_config Plugin config yaml, only care about 'tour_options' array (if it exists)
     * @return array [map_on_right, show_map_location_in_url]
     */
    public static function calculateOverrides($tour_overrides, $plugin_config) {
        $config = Utils::getArr($plugin_config, 'tour_options');
        $overrides = [];
        foreach (self::DEFAULT_OVERRIDES as $key => $value) {
            $overrides[$key] = Utils::getType($tour_overrides, $key, 'is_bool') ?? Utils::getType($config, $key, 'is_bool') ?? $value;
        }
        return $overrides;
    }
    /**
     * Validates selected options, combines with defaults, and only returns relevent information
     * - Only uses settings from plugin config as default if tile server itself is not set in the tour
     * 
     * @param array $tile_server Tour yaml 'tile_server' array
     * @param array $plugin_config Plugin config yaml, only care about 'tile_server' array (if it exists)
     * @return array [provider or url, attribution, key, id]
     */
    public static function calculateTileServer($tile_server, $plugin_config) {
        $tour_options = is_array($tile_server) ? $tile_server : [];
        // set placeholder attribution, too
        // is tile server set by tour?
        if ($server = self::getServerSelection($tour_options)) {
            // only return settings from tour
            $settings = $tour_options;
        } else {
            // combine tour and plugin options
            $config = Utils::getArr($plugin_config, 'tile_server');
            $server = self::getServerSelection($config) ?? ['provider' => self::DEFAULT_TILE_SERVER];
            $settings = array_merge($config, $tour_options);
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
     * Validates dataset_overrides from tour yaml: Must be an array, keys must match ids for valid datasets included in the tour, auto popup properties must only contain values from the relevent dataset's properties list (or 'none')
     * 
     * @param array $overrides [id => [dataset override yaml], ...]
     * @param array $datasets [id => Dataset] All datasets in tour datasets list (default, not merged)
     * @return array
     */
    public static function validateDatasetOverrides($overrides, $datasets) {
        $dataset_overrides = [];
        // must be array, can only contain ids that match tour datasets
        if (!empty($datasets) && is_array($overrides)) $dataset_overrides = array_intersect_key($overrides, $datasets);
        // auto popup props must be valid
        foreach ($dataset_overrides as $id => $override) {
            if (!is_array($override)) {
                $dataset_overrides[$id] = [];
                continue;
            }
            $dataset = Utils::get($datasets, $id);
            // props should only contain values from dataset properties, could also contain none
            // but only modify this value if it was already set, otherwise it will cause problems
            if (isset($override['auto_popup_properties'])) {
                $props = Utils::getArr($override, 'auto_popup_properties', []);
                $props = array_values(array_intersect($props, array_merge($dataset->getProperties(), ['none'])));
                $dataset_overrides[$id]['auto_popup_properties'] = $props;
            }
        }
        return $dataset_overrides;
    }
    /**
     * Validates features from tour yaml: Must be an array, ids must match ids for valid Features from datasets included in the tour. Also modifies features list so it is indexed by id. Also modifies features list based on any datasets with 'add_all' true.
     * 
     * For datasets with add_all: Any features in the dataset that are not hidden and are not already in the features list are added to the end of the features list
     * 
     * @param array $features From tour yaml
     * @param array $all_features [id => Feature] for all features from all datasets in the tour
     * @param array $dataset_options [id => [id, add_all, include_all]]: Validated and indexed tour yaml 'datasets' list
     * @param array $datasets [id => Dataset] for all datasets included in the tour (does not matter if it has additional datasets, though)
     * @return array [id => [id, popup_content, remove_popup], ...]
     */
    public static function buildFeaturesList($features, $all_features, $dataset_options, $datasets) {
        // if features is an array, index it by id and make sure only valid features are included
        $tour_features = is_array($features) ? array_intersect_key(array_column($features, null, 'id'), $all_features) : [];
        // handle datasets add all
        foreach ($dataset_options as $id => $dataset) {
            if (Utils::get($dataset, 'add_all')) {
                // add features to tour features list - must not already be in list and must not be hidden
                foreach ($datasets[$id]->getFeatures() as $feature_id => $feature) {
                    if (!Utils::get($tour_features, $feature_id) && !$feature->isHidden()) $tour_features[$feature_id] = ['id' => $feature_id];
                }
            } // otherwise ignore
        }
        return $tour_features;
    }
    /**
     * Creates list of ids for all features from all Point datasets included in the tour - features do not have to actually be included in the tour (i.e. can be hidden, not in list, etc.)
     * 
     * @param array $datasets [id => Dataset] For all datasets included in the tour
     * @return array [string]
     */
    public static function buildPointIDList($datasets) {
        $ids = [];
        foreach (array_values($datasets) as $dataset) {
            if ($dataset->getType() === 'Point') $ids = array_merge($ids, array_keys($dataset->getFeatures()));
        }
        return $ids;
    }
    /**
     * Create list of all features to be included in the tour: In features list or not hidden and in dataset with include_all true
     * 
     * @param array $tour_features [id => [id, popup_content, remove_popup], ...]: Validated and indexed tour yaml 'features' list
     * @param array $datasets [id => Dataset]: All datasets included in the tour (could have more, doesn't matter)
     * @param array $tour_datasets [id => [id, add_all, include_all], ...]: Validated and indexed tour yaml 'datasets' list
     * @return array [id => Feature]
     */
    public static function buildIncludedFeaturesList($tour_features, $datasets, $tour_datasets) {
        $included = $tour_features; // values will be replaced, ids ensure that the features have been added in the correct order
        // loop through all features from all datasets
        foreach ($tour_datasets as $dataset_id => $dataset_options) {
            foreach ($datasets[$dataset_id]->getFeatures() as $id => $feature) {
                // add feature if: feature is already in the list (needs to be updated from tour feature options to actual Feature object) or the dataset has include_all and the feature is not hidden
                if (Utils::get($included, $id) || (Utils::get($dataset_options, 'include_all') && !$feature->isHidden())) $included[$id] = $feature;
            }
        }
        return $included;
    }
    /**
     * Create list of all datasets to be included in the tour (must have at least one included feature). Creates new Dataset objects from included datasets using tour dataset overrides
     * 
     * @param array $datasets [id => Dataset]: All datasets included in the tour (could have more, doesn't matter)
     * @param array $tour_datasets [id => [tour dataset options], ...]: Validated and inexed tour yaml 'datasets' list
     * @param array $dataset_overrides [id => [tour dataset override options]]: Validate tour yaml 'dataset_overrides' list
     * @param array $included_feaures [id => Feature]: All features actually included in the tour
     * @return array [id => Dataset]
     */
    public static function buildTourDatasets($datasets, $tour_datasets, $dataset_overrides, $included_features) {
        $merged_datasets = [];
        foreach (array_keys($tour_datasets) as $id) {
            // is the dataset actually used? (at least one feature included)
            if (!empty(array_intersect(array_keys($datasets[$id]->getFeatures()), array_keys($included_features)))) {
                // create and add merged dataset
                $merged_datasets[$id] = Dataset::fromTour($datasets[$id], Utils::getArr($dataset_overrides, $id));
            }
        }
        return $merged_datasets;
    }
    /**
     * Create list of all features that have popup content, based on dataset and tour settings:
     * - Auto popup properties for determining existence/content of auto popup are determined by merged dataset
     * - Tour 'popup_content' overrides popup content or lack thereof provided by feature
     * - If tour does not have 'popup_content' but does have 'remove_popup' any content provided by feature is ignored
     * - No tour popup settings - include popup content if provided by feature
     * 
     * @param array $features [id => Feature]: All features included in tour
     * @param array $tour_features [id => [id, popup_content, remove_popup]]: Validated and indexed tour yaml 'features' list
     * @param array $datasets [id => Dataset]: Merged datasets
     * @return array
     */
    public static function buildPopupsList($features, $tour_features, $datasets) {
        $popups = [];
        foreach ($features as $id => $feature) {
            $tour_feature = Utils::getArr($tour_features, $id);
            $popup = Utils::getStr($tour_feature, 'popup_content');
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
     * Loops through tour features list and modifies popup content (if it exists) - allows images to be uploaded to tour and still used displayed accurately on tour and tour popups pages
     * 
     * @param array $features Tour yaml 'features' list
     * @param string $path Path to tour markdown file
     * @return array Modified tour yaml 'features' list
     */
    public static function validateFeaturePopups($features, $path) {
        $new_list = [];
        foreach ($features as $feature) {
            $content = Feature::modifyPopupImagePaths(Utils::get($feature, 'popup_content'), $path);
            $new_list[] = array_merge($feature, ['popup_content' => $content]);
        }
        return $new_list;
    }
    /**
     * Checks auto popup properties for one dataset override, and renames any properties as needed.
     * 
     * @param string $id The dataset that has been updated
     * @param array $properties [old_prop => new_prop, ...] (validated renamed properties)
     * @param array $overrides Tour yaml 'dataset_overrides' list
     * @return array Tour yaml 'dataset_overrides' list, with entry for $id modified (if needed)
     */
    public static function renameAutoPopupProps($id, $properties, $overrides) {
        if (!isset($overrides[$id]['auto_popup_properties'])) return $overrides; // no change if not set
        try {
            $old_props = $overrides[$id]['auto_popup_properties'];
            $new_props = [];
            foreach ($old_props as $prop) {
                if ($p = Utils::getStr($properties, $prop)) $new_props[] = $p;
            }
            return array_merge($overrides, [$id => array_merge($overrides[$id], ['auto_popup_properties' => $new_props])]);
        } catch (\Throwable $t) {
            return $overrides;
        }
    }
}
?>