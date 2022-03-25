<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;

class Tour {

    /**
     * List of tile servers provided by this plugin. Will change as leaflet providers is implemented.
     *  - key => [options?, attribution, type?, select, name?]
     */
    const TILE_SERVERS = [
        'stamenWatercolor' => [
            'type'=>'stamen',
            'select'=>'Stamen Watercolor',
            'name'=>'watercolor',
            'attribution'=>'Map tiles by <a href="http://stamen.com">Stamen Design</a>, under <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>. Data by <a href="http://openstreetmap.org">OpenStreetMap</a>, under <a href="http://creativecommons.org/licenses/by-sa/3.0">CC BY SA</a>.',
        ],
        'stamenToner' => [
            'type' => 'stamen',
            'select' => 'Stamen Toner',
            'name' => 'toner',
            'attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, under <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>. Data by <a href="http://openstreetmap.org">OpenStreetMap</a>, under <a href="http://www.openstreetmap.org/copyright">ODbL</a>.',
        ],
        'stamenTerrain' => [
            'type' => 'stamen',
            'select' => 'Stamen Terrain',
            'name' => 'terrain',
            'attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, under <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>. Data by <a href="http://openstreetmap.org">OpenStreetMap</a>, under <a href="http://www.openstreetmap.org/copyright">ODbL</a>.',
        ],
    ];
    const DEFAULT_LEGEND = [
        'include' => true,
        'toggles' => false,
        'basemaps' => false,
    ];
    const DEFAULT_OVERRIDES = [
        'wide_column' => false,
        'map_on_right' => true,
        'show_map_location_in_url' => false,
    ];
    const DEFAULT_VIEW_OPTIONS = [
        'remove_tile_server' =>  true,
        'only_show_view_features' => false,
        'list_popup_buttons' => false,
    ];

    /**
     * Generated in constructor if not yet set. Only modified when the plugin config page is saved.
     */
    private static array $plugin_config = [];

    private static array $reserved = ['file', 'all_features', 'included_features', 'merged_features', 'included_datasets', 'merged_datasets', 'tile_server_options', 'basemap_info', 'views'];

    /**
     * tour.md file, never modified
     */
    private ?MarkdownFile $file = null;
    /**
     * [$id => View, ...] for all view modules in the tour
     */
    private array $views = [];

    // properties from yaml
    /**
     * Unique tour identifier generated by plugin on initial tour save, set in constructor and never modified
     */
    private ?string $id = null;
    private ?string $title = null;
    private array $datasets = [];
    private array $dataset_overrides = [];
    private array $features = [];
    private array $tile_server = [];
    private ?string $attribution = null;
    private array $start = [];
    private ?array $basemaps = null;
    private array $legend = []; // include, toggles, basemaps
    private array $overrides = []; // wide_column, map_on_right, show_map_location_in_url
    private array $view_options = []; // remove_tile_server, only_show_view_features, list_popup_buttons
    private ?array $max_bounds = null;
    private ?int $max_zoom = null;
    private ?int $min_zoom = null;

    // calculated and stored properties
    /**
     * [$id => Feature] for features from all datasets in the tour - set when needed, cleared with features when tour or datasets change
     */
    private ?array $all_features = null;
    /**
     * list of feature ids for all included features in tour (from datasets with include_all and not hidden or included in tour features list) - set when needed, cleared with features when tour or datasets change
     */
    private ?array $included_features = null;
    /**
     * list of dataset ids of all datasets with at least one feature included in tour - cleared when features are cleared, generated when included_features is generated
     */
    private ?array $included_datasets = null;
    private ?array $merged_features = null;
    /**
     * [$id => Dataset] for all datasets in tour with at least one included feature] - set when needed, cleared with features when tour or datasets change
     */
    private ?array $merged_datasets = null;
    /**
     * 'url' => string] or value from self::TILE_SERVERS + ['attribution' => string] - set when needed, cleared when plugin/config or tour changes
     */
    private ?array $tile_server_options = null;
    /**
     * [$id => array from plugin config basemap_info] for all basemaps in tour and views combined - set when needed, cleared when plugin/config, tour, or views change
     */
    private ?array $basemap_info = null;
    
    private function __construct(array $options) {
        if (!self::$plugin_config) self::updatePluginConfig();
        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
        // make datasets a bit nicer
        $this->datasets = array_column($this->datasets ?? [], null, 'id');
        $this->features = array_column($this->features ?? [], null, 'id');
    }
    public static function fromFile(MarkdownFile $file): ?Tour {
        if ($file->exists()) {
            $options = array_diff_key((array)($file->header()), array_flip(self::$reserved));
            $options['file'] = $file;
            return new Tour($options);
        }
        else return null;
    }
    public static function fromArray(array $options): Tour {
        return new Tour($options);
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

    // object methods

    /**
     * @return Tour An identical copy of the tour
     * 
     * Feature and Dataset objects are only referenced, never modified, so would be counterproductive to create deep copies
     */
    public function clone(): Tour {
        $options = [];
        foreach (get_object_vars($this) as $key => $value) {
            $options[$key] = $value;
        }
        return new Tour($options);
    }
    /**
     * Takes yaml update array from tour header and validates it. Also clears some options that will need to be regenerated.
     */
    public function update(array $yaml): array {
        // order matters for a few things - update datasets before features, check for/update dataset add_all after features
        if ($datasets = $yaml['datasets']) $this->setDatasets($datasets);
        if ($features = $yaml['features']) $this->updateFeatures($features);
        $this->handleDatasetsAddAll();
        // remove a few properties that should not be included in the yaml, just in case, as well as properties previously dealt with
        $yaml = array_diff_key($yaml, array_flip(self::$reserved));
        foreach ($yaml as $key => $value) {
            switch ($key) {
                // TODO: a few properties with specific setters
                case 'id':
                    $this->setId($value);
                    break;
                case 'dataset_overrides':
                    $this->dataset_overrides = array_merge($this->dataset_overrides, $value);
                    break;
                case 'tile_server':
                    $this->tile_server = $value;
                    $this->clearBasemaps();
                    break;
                case 'start':
                    $this->updateStart($value);
                    break;
                case 'basemaps':
                    $this->updateBasemaps($value);
                    break;
                case 'datasets':
                case 'features':
                    break; // already handled
                // everything else
                default:
                    $this->$key = $value;
            }
        }
        $this->updateDatasetOverrides();
        $this->clearFeatures();
        return array_merge($yaml, $this->asYaml());
    }
    /**
     * Most values from the tour header are stored as they were retrived, and can be directly returned. datasets and features are indexed by id, so only array values should be returned
     * @return array yaml
     */
    public function asYaml(): array {
        $yaml = get_object_vars($this);
        $yaml = array_diff_key($yaml, array_flip(self::$reserved));
        $yaml['datasets'] = array_values($this->datasets);
        $yaml['features'] = array_values($this->features);
        return $yaml;
    }
    /**
     * Called after a dataset page has been deleted.
     * @return bool True if dataset was in tour and had to be removed
     */
    public function removeDataset(string $id): bool {
        if ($this->getDatasets()[$id]) {
            unset($this->datasets[$id]);
            // $this->updateFeatures();
            // $this->save();
            // return true;
            return $this->updateDataset($id, true);
        }
        else return false;
    }
    /**
     * Called after a dataset page has been updated
     * @return bool True if dataset is in tour
     */
    public function updateDataset(string $id, bool $removed = false): bool {
        if ($removed || $this->getDatasets()[$id]) {
            $this->updateFeatures();
            $this->updateDatasetOverrides($id);
            $this->save();
            return true;
        }
        else return false;
    }
    public function removeView(string $id): void {
        unset($this->views[$id]);
        $this->clearBasemaps();
    }
    /**
     * Called by LeafletTour after updating the plugin config (static method) or by the setConfig method. Lets the tour know to clear some values that need to be regenrated
     */
    public function updateConfig(): void {
        $this->updateBasemaps();
        $this->save();
    }
    public function save(): void {
        if ($this->file) {
            $this->file->header($this->asYaml());
            $this->file->save();
        }
    }
    /**
     * Determines the bounds for any 'start' options in tour or view.
     * 
     * @param array $start The yaml options for starting bounds [bounds, location, lat, lng, distance]
     * 
     * @return null|array Returns start.bounds if they are valid. Otherwise returns calculated bounds using location and distance if valid. Otherwise returns calculated bounds using lat, lng, and distance if valid. Otherwise returns null.
     */
    public function calculateStartingBounds(array $start): ?array {
        // first priority: manually set bounds
        $bounds = Utils::getBounds($start['bounds'] ?? []);
        if (!$bounds && ($dist = $start['distance']) && $dist > 0) {
            // next priority: point location
            if (($id = $start['location']) && ($feature = $this->getAllFeatures()[$id]) && ($feature->getType() === 'Point')) {
                $bounds = Utils::calculateBounds($feature->getCoordinatesJson(), $dist);
            }
            // otherwise try coordinates
            if (!$bounds) $bounds = Utils::calculateBounds([$start['lng'], $start['lat']], $dist);
        }
        return $bounds;
    }
    
    // "getters"
    /**
     * General options, apply to tour and all views
     * @return array
     *  - int values for max_zoom and min_zoom if set
     *  - bool values for show_map_location_in_url, wide_column, and map_on_right
     *  - 'tile_server' => ['url' => string] or value from self::TILE_SERVERS + ['attribution' => string]
     *  - 'max_bounds' => bounds array (Utils::getBounds)
     */
    public function getTourData(): array {
        $data = array_merge($this->getOverrides(), [
            'tile_server' => $this->getTileServer(),
            'max_zoom' => $this->max_zoom,
            'min_zoom' => $this->min_zoom,
        ]);
        if ($bounds = Utils::getBounds($this->max_bounds ?? [])) $data['max_bounds'] = $bounds;
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
                $info['icon'] = $dataset->getIcon();
            } else {
                $info['path'] = $dataset->getPath();
                $info['active_path'] = $dataset->getActivePath();
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
            $feature = $this->getAllFeatures()[$id];
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
        $views['tour'] = $this->getTourAsView();
        foreach ($this->views as $id => $view) {
            $views[$id] = $view->getViewData();
        }
        return $views;
    }
    /**
     * for each popup: id, name, content
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
     * @return array 
     * - remove_tile_server => bool
     * - features => [] (empty)
     * - basemaps [string $filename, ...]
     * - bounds => bounds array (Utils::getBounds) if set
     */
    public function getTourAsView(): array {
        $options = [
            'features' => [],
            'basemaps' => $this->getBasemaps(),
            'remove_tile_server' => $this->getViewOptions()['remove_tile_server'],
        ];
        if ($bounds = $this->calculateStartingBounds($this->start ?? [])) $options['bounds'] = $bounds;
        return $options;
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
    public function getBasemapsAttribution(): array {
        return array_filter(array_column($this->getBasemapInfo(), 'attribution'));
    }
    /**
     * for each dataset with legend info and at least one included feature (assuming legend is included - should be checked by template before calling but will be checked again): id, symbol_alt, text, icon, path
     * @return array
     *  - 'id' => string
     *  - 'symbol_alt' => string
     *  - 'text' => string
     *  - 'icon' => string
     *  - 'path' => array (Leaflet path options)
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
                    ];
                    if ($dataset->getType() === 'Point') {
                        $info['icon'] = $dataset->getIcon()['iconUrl'];
                    } else {
                        $info['path'] = $dataset->getPath();
                    }
                    $legend[] = $info;
                }
            }
        }
        return $legend;
    }
    /**
     * for each basemap with legend info (assuming legend basemaps are included - should be checked by template before calling but will be checked again)
     * @return array
     *  - 'file' => string
     *  - 'icon' => string
     *  - 'text' => string
     */
    public function getLegendBasemaps(): array {
        $legend = [];
        if ($this->legend['include'] ?? self::DEFAULT_LEGEND['include'] && $this->legend['basemaps'] ?? self::DEFAULT_LEGEND['basemaps']) {
            foreach ($this->getBasemapInfo() as $file => $basemap) {
                if ($text = $basemap['legend'] ?: $basemap['name']) {
                    $info = [
                        'text' => $text,
                        'icon' => Utils::BASEMAP_ROUTE . $file,
                    ];
                    // if ($icon = $basemap['icon']) $info['icon'] .= "icons/$icon";
                    // else $info['icon'] .= $basemap['file']; // TODO: crop or something?
                    $legend[] = $info;
                }
            }
        }
        return $legend;
    }

    // private methods

    /**
     * Possibly sets current features list. Ensures that the current features list contains only features that exist in datasets added to the tour.
     */
    private function updateFeatures(?array $features = null): void {
        $this->clearFeatures();
        if ($features) $features = array_column($features, null, 'id');
        else $features = $this->features;
        // getAllFeatures only gets features from datasets - does not pay attention to features list
        $all_features = $this->getAllFeatures();
        $this->features = [];
        foreach ($features as $id => $feature) {
            if ($all_features[$id]) $this->features[$id] = $feature;
        }
        // validate start location
        $this->updateStart();
    }
    private function updateDatasetOverrides(?string $id = null): void {
        if ($id) $ids = [$id];
        else $ids = array_keys($this->dataset_overrides);
        foreach ($ids as $id) {
            // check if dataset exists
            if ($dataset = LeafletTour::getDatasets()[$id]) {
                // validate auto popup properties
                $overrides = $this->dataset_overrides[$id];
                if (!empty($props = $overrides['auto_popup_properties'])) {
                    $this->dataset_overrides[$id]['auto_popup_properties'] = array_values(array_intersect($props, $dataset->getProperties()));
                }
            }
            else unset($this->dataset_overrides[$id]);
        }
    }
    private function updateStart(?array $start = null): void {
        $start ??= $this->start;
        if ($start) {
            if ($location = $start['location']) {
                if (!$this->getAllFeatures()[$location]) $start['location'] = 'none';
            }
            $this->start = $start;
        }
    }
    private function updateBasemaps(?array $basemaps = null): void {
        $this->clearBasemaps();
        $files = array_column(self::$plugin_config['basemap_info'] ?? [], 'file');
        $this->basemaps = array_intersect($basemaps ?? $this->basemaps ?? [], $files);
        // if ($basemaps) $this->basemaps = $basemaps;
        // // basemap_info gets all basemaps that have info, so can be checked to validate
        // $basemaps = [];
        // foreach ($this->basemaps ?? [] as $file) {
        //     if ($this->getBasemapInfo()[$file]) $basemaps[] = $file;
        // }
        // $this->basemaps = $basemaps;
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
                    if (!$this->features[$id] && !$feature->isHidden()) {
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
    private function clearBasemaps(): void {
        $this->tile_server_options = $this->basemap_info = null;
    }

    // calculated and stored getters

    /**
     * Sets $this->all_features if not set, then returns it.
     * does not generate $this->included_features
     * @return array [$id => Feature]
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
     * Sets $this->included_features (and $this->included_datasets) if not set, then returns it.
     * @return array [$id, ...]
     */
    public function getIncludedFeatures(): array {
        if (!$this->included_features) {
            $this->included_features = [];
            $this->included_datasets = [];
            foreach ($this->datasets as $dataset_id => $header_dataset) {
                if ($dataset = LeafletTour::getDatasets()[$dataset_id]) {
                    // check for included features
                    $features = [];
                    foreach ($dataset->getFeatures() as $feature_id => $feature) {
                        // all non-hidden features from datasets with include_all should be added
                        if ($header_dataset['include_all'] && !$feature->isHidden()) $features[] = $feature_id;
                        // also, any features in the tour features list should be added, regardless of status
                        else if ($this->getFeatures()[$feature_id]) $features[] = $feature_id;
                    }
                    // if features, also add included dataset
                    if (!empty($features)) {
                        $this->included_features = array_merge($this->included_features, $features);
                        $this->included_datasets[] = $dataset_id;
                    }
                }
            }
        }
        return $this->included_features;
    }
    private function getIncludedDatasets(): array {
        if (!$this->included_datasets) {
            $this->included_features = null;
            $this->getIncludedFeatures();
        }
        return $this->included_datasets;
    }
    private function getMergedDatasets(): array {
        if (!$this->merged_datasets) {
            $ids = $this->getIncludedDatasets();
            $this->merged_datasets = [];
            foreach ($ids as $id) {
                if ($dataset = LeafletTour::getDatasets()[$id]) {
                    // should merge icon, path, legend, attribution, auto popup properties
                    $this->merged_datasets[$id] = Dataset::fromTour($dataset, $this->dataset_overrides[$id] ?? []);
                    // $this->merged_datasets[$id] = Dataset::fromTour($dataset, []);
                }
            }
        }
        return $this->merged_datasets;
    }
    private function getMergedFeatures(): array {
        if (!$this->merged_features) {
            $this->merged_features = [];
            foreach ($this->getIncludedFeatures() as $feature_id) {
                $feature = $this->getAllFeatures()[$feature_id];
                $dataset_id = $feature->getDataset()->getId();
                // popup content/settings, dataset reference
                $this->merged_features[$feature_id] = Feature::fromTour($feature, $this->getFeatures()[$feature_id] ?? [], $this->getMergedDatasets()[$dataset_id]);
            }
        }
        return $this->merged_features;
    }
    private function getTileServer(): array {
        if (!$this->tile_server_options) {
            // tour header
            $server = $this->tileServerHelper($this->tile_server);
            $attr = ($this->tile_server)['attribution'];
            // plugin config
            if (!$server) {
                $server = $this->tileServerHelper(self::$plugin_config['tile_server'] ?? []);
                $attr ??= (self::$plugin_config['tile_server'] ?? [])['attribution'];
            }
            if (!$server) $server = array_values(self::TILE_SERVERS)[0]; // default
            if ($attr) $server['attribution'] = $attr;
            $this->tile_server_options = $server;
        }
        return $this->tile_server_options;
    }
    private function tileServerHelper(array $options): ?array {
        if (($select = $options['select']) && ($server = self::TILE_SERVERS[$select])) return $server;
        else if ($options['select'] === 'custom' && ($url = $options['url'])) return ['url' => $url];
        return null;
    }
    private function getBasemapInfo(): array {
        if (!$this->basemap_info) {
            $this->basemap_info = [];
            $files = $this->getBasemaps();
            foreach (self::$plugin_config['basemap_info'] ?? [] as $info) {
                $file = $info['file'];
                if (in_array($file, $files)) $this->basemap_info[$file] = $info;
            }
        }
        return $this->basemap_info;
    }

    // setters

    /**
     * @param string $id Sets $this->id if not yet set
     */
    public function setId(string $id): void {
        $this->id ??= $id;
    }
    public function setFile(MarkdownFile $file): void {
        $this->file ??= $file;
    }
    private function setDatasets(array $yaml) {
        $datasets = [];
        foreach ($yaml as $dataset_yaml) {
            $id = $dataset_yaml['id'];
            if (LeafletTour::getDatasets()[$id]) {
                $datasets[$id] = $dataset_yaml;
            }
        }
        $this->datasets = $datasets;
        $this->clearFeatures();
    }
    public function setViews(array $views): void {
        $this->views = $views;
        $this->clearBasemaps();
    }

    // getters
    
    /**
     * @return null|MarkdownFile $this->file
     */
    public function getFile(): ?MarkdownFile {
        return $this->file;
    }
    /**
     * @return null|string $this->id (should always be set)
     */
    public function getId(): ?string {
        return $this->id;
    }
    public function getTitle(): ?string {
        return $this->title;
    }
    public function getDatasets(): array {
        return $this->datasets;
    }
    public function getFeatures(): array {
        return $this->features;
    }
    public function getBasemaps(): array {
        return $this->basemaps ?? [];
    }
    /**
     * @return array [$id => View, ...] - $this->views
     */
    public function getViews(): array {
        return $this->views;
    }
    // note: most stored file values do not require separate getters

    // a few getters with a tiny bit of logic
    public function getTourAttribution(): ?string {
        return $this->attribution ?? (self::$plugin_config['tour_options'] ?? [])['attribution'];
    }
    public function getTileServerAttribution(): ?string {
        return $this->getTileServer()['attribution'];
    }
    public function getLegendToggles(): bool {
        return $this->legend['toggles'] ?? self::DEFAULT_LEGEND['toggles'];
    }
    private function getOverrides(): array {
        $overrides = [];
        foreach (self::DEFAULT_OVERRIDES as $key => $value) {
            $overrides[$key] = $this->overrides[$key] ?? (self::$plugin_config['tour_options'] ?? [])[$key] ?? $value;
        }
        return $overrides;
    }
    public function getViewOptions(): array {
        return array_merge(self::DEFAULT_VIEW_OPTIONS, $this->view_options);
    }
}
?>