<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;

class Tour {

    const DEFAULT_LEGEND = [
        'include' => true,
        'toggles' => false,
        'basemaps' => false,
    ];
    const DEFAULT_OVERRIDES = [
        'map_on_right' => true,
        'show_map_location_in_url' => false,
    ];
    const DEFAULT_VIEW_OPTIONS = [
        'remove_tile_server' =>  true,
        'only_show_view_features' => false,
        'list_popup_buttons' => false,
    ];
    const DEFAULT_TILE_SERVER = 'OpenTopoMap';

    /**
     * Values that should not be stored in the yaml file or modified by the user
     */
    private static array $reserved_keys = ['file', 'views', 'all_features', 'included_features', 'merged_features', 'included_datasets', 'merged_datasets', 'tile_server_options', 'basemap_info'];
    private static array $blueprint_keys = ['id', 'title', 'datasets', 'dataset_overrides', 'features', 'attribution', 'legend', 'tile_server', 'basemaps', 'start', 'overrides', 'view_options', 'max_bounds', 'max_zoom', 'min_zoom', 'column_width', 'no_attribution'];
    /**
     * Generated in constructor if not yet set. Only modified when the plugin config page is saved.
     */
    private static $plugin_config;

    /**
     * tour.md file, never modified
     */
    private ?MarkdownFile $file = null;
    /**
     * [$id => View, ...] for all view modules in the tour
     */
    private array $views = [];

    /**
     * Unique tour identifier generated by plugin on initial tour save, set in constructor, should not be modified
     */
    private ?string $id = null;
    private ?string $title = null;
    /**
     * [id => [id, include_all, add_all], ...], List of datasets included in the tour, indexed for convenience
     */
    private array $datasets = [];
    /**
     * id => [popup props, attribution, legend, icon/path]
     */
    private array $dataset_overrides = [];
    /**
     * [id => [id, popup_content, remove_popup]], indexed for convenience
     */
    private array $features = [];
    private ?string $attribution = null;
    private bool $no_attribution = false;
    /**
     * [include, toggles, basemaps]
     */
    private array $legend = [];
    /**
     * [select, provider, url, attribution, key, id]
     */
    private array $tile_server = [];
    /**
     * Array of file names
     */
    private array $basemaps = [];
    /**
     * [location, lng, lat, distance, bounds]
     */
    private array $start = [];
    /**
     * [map_on_right, show_map_location_in_url]
     */
    private array $overrides = [];
    /**
     * [remove_tile_server, only_show_view_features, list_popup_buttons]
     */
    private array $view_options = [];
    private ?int $column_width = null;
    private array $max_bounds = [];
    private ?int $max_zoom = null;
    private ?int $min_zoom = null;

    /**
     * Any values not reserved or part of blueprint
     */
    private array $extras = [];

    /**
     * [$id => Feature] for features from all datasets in the tour - set when needed, cleared with features when tour or datasets change
     */
    private ?array $all_features = null;
    /**
     * list of feature ids for all included features in tour (from datasets with include_all and not hidden or included in tour features list) - set when needed, cleared with features when tour or datasets change
     */
    private ?array $included_features = null;
    /**
     * list of all features in the tour, features have modifications from tour options
     */
    private ?array $merged_features = null;
    /**
     * list of dataset ids of all datasets with at least one feature included in tour - cleared when features are cleared, generated when included_features is generated
     */
    private ?array $included_datasets = null;
    /**
     * [$id => Dataset] for all datasets in tour with at least one included feature] - set when needed, cleared with features when tour or datasets change
     */
    private ?array $merged_datasets = null;
    /**
     * ['url' => string] + options or ['provider' => string] + options - set when needed, cleared when plugin/config or tour changes
     */
    private ?array $tile_server_options = null;
    /**
     * [$id => array from plugin config basemap_info] for all basemaps in tour and views combined - set when needed, cleared when plugin/config, tour, or views change
     */
    private ?array $basemap_info = null;
    
    /**
     * Sets and validates all provided values. Sets plugin config if it is null
     * 
     * @param array $options
     */
    private function __construct(array $options) {
        if (!self::$plugin_config) self::updatePluginConfig();
        $this->setValues($options);
    }

    // Constructor Methods

    /**
     * Builds a tour from an existing markdown file. Calls fromArray.
     * 
     * @param MarkdownFile $file the file with the tour
     * 
     * @return Tour|null new tour if provided file exists
     */
    public static function fromFile(MarkdownFile $file): ?Tour {
        if ($file->exists()) {
            $tour = self::fromArray((array)($file->header()));
            $tour->setFile($file);
            return $tour;
        }
        else return null;
    }
    /**
     * Builds a tour from an array
     * 
     * @param array $options
     * 
     * @return Tour
     */
    public static function fromArray(array $options): Tour {
        return new Tour($options);
    }

    // Object Methods

    /**
     * Takes yaml update array from tour header and validates it. Passes changes to views.
     * 
     * @param array $yaml Tour header info
     * 
     * @return array updated yaml to save
     */
    public function update(array $yaml): array {
        $this->setValues($yaml);
        $this->handleDatasetsAddAll();
        $this->updateViews();
        return $this->toYaml();
    }
    /**
     * Called after a dataset page has been deleted. If the dataset is in the tour, removes any references to it or its features and passes the information on to views.
     * 
     * @param string $id The id of the dataset that was deleted
     * 
     * @return bool True if dataset was in tour and had to be removed
     */
    public function removeDataset(string $id): bool {
        if ($this->getDatasets()[$id]) {
            unset($this->datasets[$id]);
            $this->datasets = array_values($this->datasets);
            return $this->updateDataset($id, true);
        }
        else return false;
    }
    /**
     * Called after a dataset page has been updated. If the dataset is in the tour, updates any references to it or its features and passes the information on to views.
     * 
     * @param string $id The id of the dataset that was updated
     * 
     * @return bool True if dataset is in tour
     */
    public function updateDataset(string $id, bool $removed = false): bool {
        if ($removed || $this->getDatasets()[$id]) {
            $this->setFeatures($this->getFeatures());
            $this->setDatasetOverrides($this->getDatasetOverrides());
            $this->save();
            $this->updateViews();
            return true;
        }
        else return false;
    }
    /**
     * Called when a view is deleted or moved. Clears basemaps since view may have added additional basemaps.
     * 
     * @param string $id The id of the view to remove
     */
    public function removeView(string $id): void {
        unset($this->views[$id]);
        $this->clearBasemaps();
    }
    /**
     * Instructs views to validate their content.
     */
    public function updateViews(): void {
        foreach ($this->getViews() as $id => $view) {
            $view->updateAll();
        }
    }
    /**
     * Called by LeafletTour after updating the plugin config (static method) or by the setConfig method. Lets tour know to validate basemaps.
     */
    public function updateConfig(): void {
        $this->setBasemaps($this->getBasemaps());
        $this->updateViews();
        $this->save();
    }
    /**
     * Only works if the tour has a file object set. Generates yaml content and saves it to the file header
     */
    public function save(): void {
        if ($this->file) {
            $this->file->header($this->toYaml());
            $this->file->save();
        }
    }
    /**
     * Determines the bounds for any 'start' options in tour or view.
     * 
     * @param array $start The yaml options for starting bounds [bounds, location, lat, lng, distance]
     * 
     * @return array|null Returns start.bounds if they are valid. Otherwise returns calculated bounds using location and distance if valid. Otherwise returns calculated bounds using lat, lng, and distance if valid. Otherwise returns null.
     */
    public function calculateStartingBounds(array $start): ?array {
        // first priority: manually set bounds
        $bounds = Utils::getBounds($start['bounds'] ?? []);
        if (!$bounds && ($dist = $start['distance']) && $dist > 0) {
            // next priority: point location
            if (($id = $start['location']) && ($feature = $this->getAllFeatures()[$id]) && ($feature->getType() === 'Point')) {
                $bounds = [
                    'lng' => $feature->getCoordinatesJson()[0],
                    'lat' => $feature->getCoordinatesJson()[1]
                ];
            }
            // otherwise try coordinates
            if (!$bounds && ($lng = $start['lng']) && ($lat = $start['lat'])) $bounds = ['lng' => $lng, 'lat' => $lat];
            // if something was valid, make sure distance is in meters
            if ($bounds) {
                switch ($start['units']) {
                    case 'kilometers':
                        $bounds['distance'] = $dist * 1000;
                        break;
                    case 'feet':
                        $bounds['distance'] = $dist / 0.3048;
                        break;
                    case 'miles':
                        $bounds['distance'] = $dist * 1609.34;
                        break;
                    default:
                        $bounds['distance'] = $dist;
                }
            }
        }
        return $bounds;
    }

    /**
     * called after setting datasets and features - checks any datasets for "add_all" and updates features and datasets lists accordingly
     * clears datasets and features lists
     * does not save the file!
     */
    private function handleDatasetsAddAll() {
        foreach ($this->getDatasets() as $id => $yaml) {
            if ($yaml['add_all'] && ($dataset = LeafletTour::getDatasets()[$id])) {
                // unset add_all
                $this->datasets[$id]['add_all'] = false;
                // add features
                foreach ($dataset->getFeatures() as $id => $feature) {
                    if (!$this->features[$id] && !$feature->getHide()) {
                        // add non-hidden features not yet in $this->features
                        $this->features[$id] = [
                            'id' => $id,
                            'remove_popup' => false,
                            'popup_content' => null,
                        ];
                    }
                }
            }
        }
        $this->clearFeatures();
    }
    /**
     * clears all stored values relating to datasets and features (too interconnected to bother with separate methods)
     */
    private function clearFeatures(): void {
        $this->included_features = $this->all_features = $this->included_datasets = $this->merged_datasets = $this->merged_features = null;
    }
    public function clearBasemaps(): void {
        $this->tile_server_options = $this->basemap_info = null;
    }

    /**
     * @return Tour An identical copy of the tour
     * 
     * Feature and Dataset objects are only referenced, never modified, so would be counterproductive to create deep copies
     */
    public function clone(): Tour {
        $tour = new Tour([]);
        foreach (get_object_vars($this) as $key => $value) {
            $tour->$key = $value;
        }
        return $tour;
    }
    public function __toString() {
        return json_encode($this->toYaml());
    }
    /**
     * Only checks view ids. Ignores reserved values (besides views)
     */
    public function equals(Tour $other): bool {
        $vars1 = array_diff_key(get_object_vars($this), array_flip(self::$reserved_keys));
        $vars1['views'] = array_keys($this->getViews());
        $vars2 = array_diff_key(get_object_vars($other), array_flip(self::$reserved_keys));
        $vars2['views'] = array_keys($other->getViews());
        return ($vars1 == $vars2);
    }
    /**
     * @return array Tour yaml array that can be saved in tour.md
     */
    public function toYaml(): array {
        $yaml = array_diff_key(get_object_vars($this), array_flip(self::$reserved_keys));
        // un-index datasets and features
        $yaml['datasets'] = array_values($this->getDatasets());
        $yaml['features'] = array_values($this->getFeatures());
        // remove and replace extras
        unset($yaml['extras']);
        $yaml = array_merge($this->getExtras() ?? [], $yaml);
        return $yaml;
    }

    // Calculated Getters

    /**
     * General options, apply to tour and all views
     * 
     * @return array
     *  - int values for max_zoom and min_zoom if set
     *  - int value for column_width
     *  - bool values for show_map_location_in_url, and map_on_right
     *  - 'tile_server' => ['url' => string] or value from self::TILE_SERVERS + ['attribution' => string]
     *  - 'max_bounds' => bounds array (Utils::getBounds)
     */
    public function getTourData(): array {
        $data = array_merge($this->getOverrides(), [
            'tile_server' => $this->getTileServerOptions(),
            'column_width' => $this->column_width ?? ($this->getConfig()['tour_options'] ?? [])['column_width'] ?? 33,
            'max_zoom' => $this->max_zoom,
            'min_zoom' => $this->min_zoom,
        ]);
        if ($bounds = Utils::getBounds($this->max_bounds)) $data['max_bounds'] = $bounds;
        return $data;
    }
    /**
     * @return array [$id => array]
     *  - 'id' => string
     *  - 'features' => empty array
     *  - 'legend_summary' => string
     *  - 'icon' => array (Leaflet icon options)
     *  - 'path' => array (Leaflet path options)
     *  - 'active_path' => array (Leaflet path options)
     */
    public function getDatasetData(): array {
        $datasets = [];
        foreach ($this->getMergedDatasets() as $id => $dataset) {
            $info = [
                'id' => $id,
                'features' => [],
                'legend_summary' => $dataset->getLegend()['summary'],
            ];
            if ($dataset->getType() === 'Point') {
                $info['icon'] = $dataset->getIconOptions();
            } else {
                if ($dataset->hasBorder()) {
                    $info['path'] = array_merge($dataset->getBorderOptions(), $dataset->getFillOptions());
                    $info['active_path'] = array_merge($dataset->getActiveBorderOptions(), $dataset->getActiveFillOptions());
                    $info['stroke'] = $dataset->getStrokeOptions();
                    $info['active_stroke'] = $dataset->getActiveStrokeOptions();
                }
                else {
                    $info['path'] = array_merge($dataset->getStrokeOptions(), $dataset->getFillOptions());
                    $info['active_path'] = array_merge($dataset->getActiveStrokeOptions(), $dataset->getActiveFillOptions());
                }
            }
            $datasets[$id] = $info;
        }
        return $datasets;
    }
    /**
     * @return array [$file => array]
     *  - 'bounds' => bounds array (Utils::getBounds)
     *  - 'options' => ['max_zoom' => int, 'min_zoom' => int]
     */
    public function getBasemapData(): array {
        $basemaps = [];
        foreach ($this->getBasemapInfo() as $file => $info) {
            $basemaps[$file] = [
                'url' => Utils::BASEMAP_ROUTE . $file,
                'bounds' => Utils::getBounds($info['bounds']),
                'options' => [
                    'max_zoom' => $info['max_zoom'] ?? 16,
                    'min_zoom' => $info['min_zoom'] ?? 8,
                ],
            ];
        }
        return $basemaps;
    }
    /**
     * @return array [$id => array]
     *  - 'type' => 'Feature'
     *  - 'properties' => ['id' => string, 'name' => string, 'dataset' => string, 'has_popup' => bool]
     *  - 'geometry' => ['type' => string, 'coordinates' => array]
     */
    public function getFeatureData(): array {
        $features = [];
        foreach ($this->getIncludedFeatures() as $id) {
            $feature = $this->getMergedFeatures()[$id];
            $features[$id] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => $feature->getType(),
                    'coordinates' => $feature->getCoordinatesJson(),
                ],
                'properties' => [
                    'id' => $id,
                    'name' => $feature->getName(),
                    'dataset' => $feature->getDataset()->getId(),
                    'has_popup' => !empty($feature->getFullPopup()),
                ],
            ];
        }
        return $features;
    }
    /**
     * @return array [$id => array (from view)]
     */
    public function getViewData(): array {
        $views = [];
        // set tour as view
        $views['tour'] = [
            'features' => [],
            'basemaps' => $this->getBasemaps(),
            'remove_tile_server' => $this->getViewOptions()['remove_tile_server'],
        ];
        if ($bounds = $this->calculateStartingBounds($this->start ?? [])) $views['tour']['bounds'] = $bounds;
        // regular views
        foreach ($this->getViews() as $id => $view) {
            $views[$id] = $view->getViewData();
        }
        return $views;
    }
    /**
     * @return array
     *  - 'id' => string
     *  - 'name' => string
     *  - 'popup' => string
     */
    public function getFeaturePopups(): array {
        $popups = [];
        foreach ($this->getMergedFeatures() as $id => $feature) {
            if ($popup = $feature->getFullPopup()) $popups[] = [
                'id' => $id,
                'name' => $feature->getName(),
                'popup' => $popup,
            ];
        }
        return $popups;
    }
    /**
     * @return array [string $attr, ...]
     */
    public function getDatasetsAttribution(): array {
        $datasets = [];
        foreach ($this->getMergedDatasets() as $id => $dataset) {
            if ($attr = $dataset->getAttribution()) $datasets[] = $attr;
        }
        return $datasets;
    }
    /**
     * @return array [string $attr, ...]
     */
    public function getBasemapsAttribution(): array {
        return array_filter(array_column($this->getBasemapInfo(), 'attribution'));
    }
    /**
     * @return string|null if tour attribution is set in tour or plugin
     */
    public function getTourAttribution(): ?string {
        return $this->attribution ?? (self::$plugin_config['tour_options'] ?? [])['attribution'];
    }
    /**
     * @return string|null attribution if custom tile server attribution is set, placeholder if not but tile server provider is used, null otherwise
     */
    public function getTileServerAttribution(): ?string {
        if ($attr = $this->getTileServerOptions()['attribution']) return $attr;
        else if ($this->getTileServerOptions()['provider']) return 'placeholder';
        else return null;
    }
    /**
     * @return bool whether or not the legend datasets should have checkboxes next to them to allow toggling features
     */
    public function getLegendToggles(): bool {
        return $this->getLegend()['toggles'] ?? self::DEFAULT_LEGEND['toggles'];
    }
    /**
     * entry for each dataset with legend info and at least one included feature (assuming legend is included - should be checked by template before calling but will be checked again)
     * @return array [[id, symbol_alt, text, icon, path], ...] (icon or path, not both)
     */
    public function getLegendDatasets(): array {
        $legend = [];
        if ($this->legend['include'] ?? self::DEFAULT_LEGEND['include']) {
            foreach ($this->getMergedDatasets() as $id => $dataset) {
                if ($text = $dataset->getLegend()['text']) {
                    $info = [
                        'id' => $id,
                        'symbol_alt' => $dataset->getLegend()['symbol_alt'],
                        'text' => $text,
                        'class' => 'dataset',
                    ];
                    if ($dataset->getType() === 'Point') {
                        $info['icon'] = $dataset->getIconOptions()['iconUrl'];
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
     * entry for each basemap with legend info (assuming legend basemaps are included - should be checked by template before calling but will be checked again)
     * 
     * @return array [[file, icon, text], ...]
     */
    public function getLegendBasemaps(): array {
        $legend = [];
        if (($this->getLegend()['include'] ?? self::DEFAULT_LEGEND['include']) && ($this->getLegend()['basemaps'] ?? self::DEFAULT_LEGEND['basemaps'])) {
            foreach ($this->getBasemapInfo() as $file => $basemap) {
                if ($text = $basemap['legend'] ?: $basemap['name']) {
                    $info = [
                        'file' => $file,
                        'text' => $text,
                        'icon' => Utils::BASEMAP_ROUTE,
                        'class' => 'basemap',
                    ];
                    // Use icon if provided
                    if ($icon = $basemap['icon']) $info['icon'] .= "icons/$icon";
                    else $info['icon'] .= $file;
                    $legend[] = $info;
                }
            }
        }
        return $legend;
    }
    /**
     * @return array defaults for view to use
     */
    public function getViewOptions(): array {
        return array_merge(self::DEFAULT_VIEW_OPTIONS, $this->view_options);
    }
    /**
     * @return string any additional classes to attach to the <body> element
     */
    public function getBodyClasses(): string {
        $classes = $this->getExtras()['body_classes'] ?? '';
        $overrides = $this->getOverrides();
        if (!$overrides['map_on_right']) $classes .= ' map-on-left';
        return $classes;
    }
    /**
     * Sets $this->all_features if not set, then returns it.
     * does not generate $this->included_features
     * 
     * @return array [$id => Feature] for every feature from every dataset added to the tour
     */
    public function getAllFeatures(): array {
        if (!$this->all_features) {
            $this->all_features = [];
            foreach (array_keys($this->getDatasets()) as $id) {
                if ($dataset = LeafletTour::getDatasets()[$id]) {
                    $this->all_features = array_merge($this->all_features, $dataset->getFeatures());
                }
            }
        }
        return $this->all_features;
    }
    /**
     * Sets $this->included_features (and $this->included_datasets) if not set, then returns it
     * 
     * @return array [$id, ...] for all features that will actually be included in the tour (displayed on map, etc.)
     */
    public function getIncludedFeatures(): array {
        if (!$this->included_features) {
            $this->included_datasets = [];
            // start with all features in the features list
            $this->included_features = array_keys($this->getFeatures());
            foreach ($this->getDatasets() as $dataset_id => $header_dataset) {
                if ($dataset = LeafletTour::getDatasets()[$dataset_id]) {
                    // check for included features
                    $features = [];
                    foreach ($dataset->getFeatures() as $feature_id => $feature) {
                        // all non-hidden features from datasets with include_all should be added
                        if ($header_dataset['include_all'] && !$feature->getHide()) $features[] = $feature_id;
                        // also, any features in the tour features list should be added, regardless of status
                        else if ($this->getFeatures()[$feature_id]) $features[] = $feature_id;
                    }
                    // if features, also add included dataset
                    if (!empty($features)) {
                        // first clear out any features already in the included features list
                        $features = array_diff($features, $this->included_features);
                        $this->included_features = array_merge($this->included_features, $features);
                        $this->included_datasets[] = $dataset_id;
                    }
                }
            }
        }
        return $this->included_features;
    }
    /**
     * Sets $this->included_datasets if not set, then returns it
     * 
     * @return array [$id, ...] for all datasets that have at least one feature included
     */
    private function getIncludedDatasets(): array {
        if (!$this->included_datasets) {
            $this->included_features = null;
            $this->getIncludedFeatures(); // will set $this->included_datasets, too
        }
        return $this->included_datasets;
    }
    /**
     * Sets $this->merged_datasets if not set, then returns it
     * 
     * @return array [$id => Dataset, ...] with merged dataset/tour info for all included datasets
     */
    private function getMergedDatasets(): array {
        if (!$this->merged_datasets) {
            $this->merged_datasets = [];
            foreach ($this->getIncludedDatasets() as $id) {
                if ($dataset = LeafletTour::getDatasets()[$id]) {
                    // should merge icon, path, legend, attribution, auto popup properties
                    $this->merged_datasets[$id] = Dataset::fromTour($dataset, $this->dataset_overrides[$id] ?? []);
                }
            }
        }
        return $this->merged_datasets;
    }
    /**
     * Sets $this->merged_features if not set, then returns it
     * 
     * @return array [$id => Feature, ...] with merged feature/tour info for all included features
     */
    private function getMergedFeatures(): array {
        if (!$this->merged_features) {
            $this->merged_features = [];
            foreach ($this->getIncludedFeatures() as $feature_id) {
                $feature = $this->getAllFeatures()[$feature_id];
                if (!$feature) continue;
                $dataset_id = $feature->getDataset()->getId();
                // popup content/settings, dataset reference
                if ($file = $this->getFile()) $filename = $file->filename();
                $this->merged_features[$feature_id] = Feature::fromTour($feature, $this->getFeatures()[$feature_id] ?? [], $this->getMergedDatasets()[$dataset_id], $filename);
            }
        }
        return $this->merged_features;
    }
    /**
     * Check if server set by tour or plugin. If neither, use default provider. If set, use appropriate selection/other provider name/custom url. If set by tour, use tour settings, otherwise combine tour and plugin settings.
     * 
     * @return array  [url or provider, key, id, attribution]
     */
    private function getTileServerOptions(): array {
        if (!$this->tile_server_options) {
            // check if tile server set by tour or plugin
            ;
            if ($server = $this->getServerSelection($this->tile_server ?? [])) { // set by tour
                // return only tour settings
                $settings = $this->tile_server;
            } else {
                $tile_server = self::$plugin_config['tile_server'] ?? [];
                $server = ($this->getServerSelection($tile_server)) ?? ['provider' => self::DEFAULT_TILE_SERVER];
                $settings = array_merge($tile_server, $this->tile_server ?? []);
            }
            // remove extraneous values
            $settings = array_diff_key($settings, array_flip(['select', 'url', 'name']));
            $this->tile_server_options = array_merge($settings, $server);
        }
        return $this->tile_server_options;
    }
    private function getServerSelection(array $options): ?array {
        if ($select = $options['select']) {
            if ($select === 'custom') {
                if ($url = $options['url']) return ['url' => $url];
            } else if ($select === 'other') {
                if ($name = $options['name']) return ['provider' => $name];
            } else {
                return ['provider' => $select];
            }
        }
        return null;
    }
    /**
     * @return [id => [info from basemap_info list in plugin config]] for all basemaps in tour and its views
     */
    private function getBasemapInfo(): array {
        if (!$this->basemap_info) {
            $this->basemap_info = [];
            $files = $this->getBasemaps();
            foreach ($this->getViews() as $id => $view) {
                $files = array_merge($files, $view->getBasemaps());
            }
            foreach (self::$plugin_config['basemap_info'] ?? [] as $info) {
                $file = $info['file'];
                if (in_array($file, $files)) $this->basemap_info[$file] = $info;
            }
        }
        return $this->basemap_info;
    }
    /**
     * @return array
     */
    private function getOverrides(): array {
        $overrides = [];
        foreach (self::DEFAULT_OVERRIDES as $key => $value) {
            $overrides[$key] = $this->overrides[$key] ?? (self::$plugin_config['tour_options'] ?? [])[$key] ?? $value;
        }
        return $overrides;
    }

    // Getters

    /**
     * @return MarkdownFile|null $this->file
     */
    public function getFile(): ?MarkdownFile {
        return $this->file;
    }
    /**
     * @return array [$id => View, ...] - $this->views
     */
    public function getViews(): array {
        return $this->views ?? [];
    }
    /**
     * @return string|null $this->id (should always be set)
     */
    public function getId(): ?string {
        return $this->id;
    }
    /**
     * @return string|null
     */
    public function getTitle(): ?string {
        return $this->title;
    }
    public function getDatasets(): array {
        return $this->datasets ?? [];
    }
    public function getDatasetOverrides(): array {
        return $this->dataset_overrides ?? [];
    }
    public function getFeatures(): array {
        return $this->features ?? [];
    }
    public function getLegend(): array {
        return $this->legend ?? [];
    }
    public function getBasemaps(): array {
        return $this->basemaps ?? [];
    }
    /**
     * @return array An array with all non-reserved and non-blueprint properties attached to the object, if any.
     */
    public function getExtras(): array {
        return $this->extras;
    }

    // Setters

    /**
     * Sets all non-reserved values
     * 
     * @param array $options
     */
    public function setValues(array $options): void {
        $this->setId($options['id']);
        $this->setTitle($options['title']);
        $this->setDatasets($options['datasets']);
        $this->setDatasetOverrides($options['dataset_overrides']);
        $this->setFeatures($options['features']);
        $this->setAttribution($options['attribution']);
        $this->setLegend($options['legend']);
        $this->setTileServer($options['tile_server']);
        $this->setBasemaps($options['basemaps']);
        $this->setStart($options['start']);
        $this->setOverrides($options['overrides']);
        $this->setViewOptions($options['view_options']);
        $this->setColumnWidth($options['column_width']);
        $this->setMaxBounds($options['max_bounds']);
        $this->setMaxZoom($options['max_zoom']);
        $this->setMinZoom($options['min_zoom']);
        $this->setNoAttribution($options['no_attribution']);
        $this->setExtras($options);
    }
    /**
     * @param MarkdownFile $file
     */
    public function setFile(MarkdownFile $file): void {
        $this->file = $file;
    }
    /**
     * Sets views. Assumes that the tour has already been set for the views.
     * 
     * @param array $views [id => View, ...]
     */
    public function setViews(array $views): void {
        $this->views = $views;
        $this->clearBasemaps();
    }
    /**
     * Will not set id to null
     * 
     * @param string $id Sets $this->id (by default only if not already set)
     * @param bool $overwrite - if true, $this->id will be set even if already set
     */
    public function setId($id, $overwrite = false): void {
        if(is_string($id) && !empty($id)) {
            if (!$this->id || $overwrite) $this->id = $id;
        }
    }
    /**
     * @param string $title Sets $this->title (empty string ignored)
     */
    public function setTitle($title): void {
        if (is_string($title) && !empty($title)) $this->title = $title;
    }
    /**
     * Sets and validates $this->datasets, indexes the array for convenience. Warning! Does not update features. Make sure to call setFeatures after calling this.
     * 
     * @param array|null $datasets Tour datasets list [id, include_all, add_all] from the tour file header
     */
    public function setDatasets($datasets): void {
        $this->datasets = [];
        if (is_array($datasets)) {
            foreach ($datasets as $dataset) {
                $id = $dataset['id'];
                // make sure dataset exists
                if (LeafletTour::getDatasets()[$id]) {
                    $this->datasets[$id] = $dataset;
                }
            }
        }
        $this->clearFeatures();
    }
    /**
     * Makes sure overrides are relevant for existing datasets. Assumes $this->datasets is valid
     * 
     * @param array|null $overrides
     */
    public function setDatasetOverrides($overrides): void {
        $this->dataset_overrides = [];
        if (is_array($overrides)) {
            foreach ($overrides as $id => $values) {
                if ($this->getDatasets()[$id] && ($dataset = LeafletTour::getDatasets()[$id])) {
                    // validate auto popup properties
                    if (!empty($props = $values['auto_popup_properties'])) {
                        $allowed = array_merge($dataset->getProperties(), ['none']);
                        $values['auto_popup_properties'] = array_values(array_intersect($props, $allowed));
                    }
                    $this->dataset_overrides[$id] = $values;
                }
            }
        }
    }
    /**
     * Does not validate start location. If needed, call setStart after this.
     * 
     * @param array|null $features Tour features list [id, popup_content, remove_popup] from the tour file header
     */
    public function setFeatures($features): void {
        $this->features = [];
        $this->clearFeatures();
        if (is_array($features)) {
            $all_features = $this->getAllFeatures();
            $features = array_column($features, null, 'id');
            $this->features = array_values(array_intersect_key($features, $all_features));
        }
    }
    /**
     * @param string|null $text
     */
    public function setAttribution($text): void {
        if (is_string($text)) $this->attribution = $text;
        else $this->attribution = null;
    }
    /**
     * @param array|null $legend
     */
    public function setLegend($legend): void {
        if (is_array($legend)) $this->legend = $legend;
        else $this->legend = [];
    }
    /**
     * @param array|null $server
     */
    public function setTileServer($server): void {
        if (is_array($server)) $this->tile_server = $server;
        else $this->tile_server = [];
        $this->clearBasemaps();
    }
    /**
     * @param array|null $basemaps
     */
    public function setBasemaps($basemaps): void {
        if (is_array($basemaps)) {
            // validate basemaps (make sure they exist in plugin config)
            $files = array_column(self::$plugin_config['basemap_info'] ?? [], 'file');
            $this->basemaps = array_values(array_intersect($basemaps, $files));
        }
        else $this->basemaps = [];
        $this->clearBasemaps();
    }
    /**
     * @param array|null $start
     */
    public function setStart($start): void {
        if (is_array($start)) {
            // validate location, if set
            if ($location = $start['location']) {
                if (!(($feature = $this->getAllFeatures()[$location]) && ($feature->getType() == 'Point')))  $start['location'] = 'none';
            }
            $this->start = $start;
        }
        else $this->start = [];
    }
    /**
     * @param array|null $overrides
     */
    public function setOverrides($overrides): void {
        if (is_array($overrides)) $this->overrides = $overrides;
        else $this->overrides = [];
    }
    /**
     * @param array|null $options
     */
    public function setViewOptions($options): void {
        if (is_array($options)) $this->view_options = $options;
        else $this->view_options = [];
    }
    /**
     * @param int|null $width
     */
    public function setColumnWidth($width): void {
        if (is_int($width)) $this->column_width = $width;
        else $this->column_width = null;
    }
    /**
     * @param array|null $bounds
     */
    public function setMaxBounds($bounds): void {
        if (is_array($bounds)) $this->max_bounds = $bounds;
        else $this->max_bounds = [];
    }
    /**
     * @param int|null $zoom
     */
    public function setMaxZoom($zoom): void {
        if (is_int($zoom)) $this->max_zoom = $zoom;
        else $this->max_zoom = null;
    }
    /**
     * @param int|null $zoom
     */
    public function setMinZoom($zoom): void {
        if (is_int($zoom)) $this->min_zoom = $zoom;
        else $this->min_zoom = null;
    }
    /**
     * @param bool|null $no_attribution
     */
    public function setNoAttribution($no_attribution): void {
        if (is_bool($no_attribution)) $this->no_attribution = $no_attribution;
        else $this->no_attribution = false;
    }
    /**
     * @param array|null $extras
     */
    public function setExtras($extras) {
        if (is_array($extras)) {
            $this->extras = array_diff_key($extras, array_flip(array_merge(self::$reserved_keys, self::$blueprint_keys)));
        }
        else $this->extras = [];
    }

    /**
     * Should be called from LeafletTour when the plugin config is saved. Tours will still need to be looped through to deal with the actual update.
     */
    public static function updatePluginConfig(?array $config = null): void {
        self::$plugin_config = $config ?? (Grav::instance()['config']->get('plugins.leaflet-tour'));
    }
    public static function getConfig(): array {
        return self::$plugin_config;
    }
}
?>