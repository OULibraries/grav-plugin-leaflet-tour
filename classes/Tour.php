<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;

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
    const DEFAULT_VIEW_OPTIONS = [
        'remove_tile_server' =>  true,
        'only_show_view_features' => false,
        'list_popup_buttons' => false,
    ];
    const DEFAULT_TILE_SERVER = 'OpenTopoMap';

    // passed by constructor(s)
    private ?MarkdownFile $file;
    private array $views;
    private array $plugin_config;
    // from yaml
    private string $id;
    private ?string $title, $attribution;
    private array $datasets, $dataset_overrides, $features, $legend, $tile_server, $basemaps, $start, $overrides, $view_options, $max_bounds, $extras;
    private bool $no_attribution;
    private ?int $column_width, $max_zoom, $min_zoom;
    // calculated in constructor
    private array $valid_basemaps, $point_ids;
    private array $included_features, $merged_datasets, $feature_popups, $basemap_info, $tile_server_options;
    private ?array $starting_bounds;

    // TODO: Note: Add all will not be reset to false, actually

    // all normal values
    // for future validation: valid basemap file names, valid point feature ids, included/merged features
    public function __construct(array $options, array $views, array $plugin_config, array $datasets) {
        // set and validate all the basic/easy stuff first
        try { $this->file = $options['file']; }
        catch (\Throwable $t) { $this->file = null; }
        $this->plugin_config = $plugin_config;
        $this->id = is_string($options['id']) ? $options['id'] : ''; // just in case, but should always be provided
        // strings
        foreach (['title', 'attribution'] as $key) {
            $this->$key = is_string($options[$key]) ? $options[$key] : null;
        }
        // arrays
        foreach (['legend', 'tile_server', 'overrides', 'view_options', 'max_bounds'] as $key) {
            $this->$key = is_array($options[$key]) ? $options[$key] : [];
        }
        // no attribution
        $this->no_attribution = ($options['no_attribution'] === true);
        // ints
        foreach (['column_width', 'max_zoom', 'min_zoom'] as $key) {
            $this->$key = is_int($options[$key]) ? $options[$key] : null;
        }
        // extras
        $keys = ['file', 'id', 'title', 'attribution', 'datasets', 'dataset_overrides', 'features', 'legend', 'tile_server', 'basemaps', 'start', 'overrides', 'view_options', 'max_bounds', 'no_attribution', 'column_width', 'max_zoom', 'min_zoom'];
        $this->extras = array_diff_key($options, array_flip($keys));

        // start with datasets
        // datasets - must be array, can only contain ids that have valid files
        if (is_array($options['datasets'])) $this->datasets = array_intersect_key(array_column($options['datasets'], null, 'id'), $datasets);
        else $this->datasets = [];
        // dataset objects (temporary) - turn file into object, only for datasets that are included in tour datasets list
        $datasets = array_map(function($file) { return Dataset::fromFile($file); }, array_intersect_key($datasets, $this->datasets));
        // dataset overrides
        $this->dataset_overrides = self::validateDatasetOverrides($options['dataset_overrides'], $datasets);

        // start with features
        $features = []; // all feature objects (temporary)
        foreach (array_values($datasets) as $dataset) {
            $features = array_merge($features, $dataset->getFeatures());
        }
        $this->features = self::buildFeaturesList($options['features'], $features, $this->datasets, $datasets);
        $this->point_ids = self::buildPointIDList($datasets);
        $this->included_features = self::buildIncludedFeaturesList($this->features, $datasets, $this->datasets);
        $this->merged_datasets = self::buildTourDatasets($datasets, $this->datasets, $this->dataset_overrides, $this->included_features);
        $this->feature_popups = self::buildPopupsList($this->included_features, $this->features, $this->merged_datasets);

        // validate start - must be array, location must be valid point feature or 'none'
        $this->start = is_array($options['start']) ? $options['start'] : [];
        // location must be a string, must reference a valid feature, and that feature must be a point
        if (!is_string($this->start['location']) || !in_array($this->start['location'], $this->point_ids)) $this->start['location'] = 'none';
        // get starting bounds
        $this->starting_bounds = View::calculateStartingBounds($this->start, $features[$this->start['location']]);

        // basemaps
        // basemap info (temporary) - straight from plugin config
        $basemap_info = is_array($plugin_config['basemap_info']) ? array_column($plugin_config['basemap_info'], null ,'file') : [];
        // valid basemaps
        $this->valid_basemaps = array_keys($basemap_info);
        // basemaps - validate
        $this->basemaps = is_array($options['basemaps']) ? array_values(array_intersect($options['basemaps'], $this->valid_basemaps)) : [];

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

    public static function fromFile(MarkdownFile $file, array $views, array $plugin_config, array $datasets): Tour {
        $options = array_merge($file->header(), ['file' => $file]);
        return new Tour($options, $views, $plugin_config, $datasets);
    }
    public static function fromArray(array $options, array $views, array $plugin_config, array $datasets): Tour {
        return new Tour($options, $views, $plugin_config, $datasets);
    }

    public function validateViewUpdate(array $view_update, string $view_id): array {
        $view = View::fromArray(array_merge($view_update, ['id' => $view_id]), $this->getValidBasemaps(), $this->getPointIds(), array_keys($this->getIncludedFeatures()), $this->getFeaturePopups());
        return $view->toYaml();
    }

    // todo: exactly what I put here will depend on whether or not I need a getter function for a given property
    public function toYaml(): array {
        return array_merge($this->getExtras(), [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'datasets' => array_values($this->datasets),
            'dataset_overrides' => $this->dataset_overrides,
            'legend' => $this->getLegend(),
            'column_width' => $this->getColumnWidth(),
            'overrides' => $this->getOverrides(),
            'attribution' => $this->getAttribution(),
            'tile_server' => $this->tile_server,
            'basemaps' => $this->getBasemaps(),
            'start' => $this->start,
            'view_options' => $this->getViewOptions(),
            'max_bounds' => $this->getMaxBounds(),
            'max_zoom' => $this->getMaxZoom(),
            'min_zoom' => $this->getMinZoom(),
            'no_attribution' => $this->no_attribution,
            'features' => array_values($this->features),
        ]);
    }

    // calculated getters for template
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
    public function getTourData(): array {
        return array_merge(self::calculateOverrides($this->getOverrides(), $this->getPluginConfig()), [
            'tile_server' => $this->getTileServerOptions(),
            'max_zoom' => $this->getMaxZoom(),
            'min_zoom' => $this->getMinZoom(),
            'max_bounds' => Utils::getBounds($this->getMaxBounds()),
        ]);
    }
    public function getBasemapData(): array {
        return array_map(function($info) {
            return [
                'url' => Utils::BASEMAP_ROUTE . $info['file'],
                'bounds' => Utils::getBounds($info['bounds']),
                'options' => [
                    'max_zoom' => $info['max_zoom'],
                    'min_zoom' => $info['min_zoom'],
                ],
            ];
        }, $this->getBasemapInfo());
    }
    public function getDatasetData(): array {
        return array_map(function($dataset) {
            $info = [
                'id' => $dataset->getId(),
                'features' => [],
                'legend_summary' => $dataset->getLegend()['summary'],
            ];
            if ($dataset->getType() === 'Point') {
                return array_merge($info, ['icon' => $dataset->getIconOptions()]);
            } else {
                return array_merge($info, $dataset->getShapeOptions());
            }
        }, $this->getMergedDatasets());
    }
    public function getFeatureData(): array {
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
    public function getViewData(): array {
        // start with tour
        $views = ['_tour' => [
            'features' => [],
            'basemaps' => $this->getBasemaps(),
            'remove_tile_server' => $this->getViewOptions()['remove_tile_server'] ?? self::DEFAULT_VIEW_OPTIONS['remove_tile_server'],
            'bounds' => $this->getStartingBounds(),
        ]];
        // add views
        foreach ($this->getViews() as $id => $view) {
            $views[$id] = $view->getViewData($this->getViewOptions(), $this->getBasemaps());
        }
        return $views;
    }
    public function getBodyClasses(): string {
        $classes = $this->getExtras()['body_classes'] ?? '';
        if (!self::calculateOverrides($this->getOverrides(), $this->getPluginConfig())['map_on_right']) $classes .= ' map-on-left';
        return $classes;
    }
    public function getLegendToggles(): bool {
        return $this->getLegend()['toggles'] ?? self::DEFAULT_LEGEND['toggles'];
    }
    public function getTourAttribution(): ?string {
        return $this->getAttribution() ?? ($this->getPluginConfig()['tour_options'] ?? [])['attribution'];
    }
    public function getTileServerAttribution(): ?string {
        return $this->getTileServerOptions()['attribution'];
    }
    public function getBasemapsAttribution(): array {
        return array_filter(array_column($this->getBasemapInfo(), 'attribution'));
    }
    public function getDatasetsAttribution(): array {
        $datasets = [];
        foreach ($this->getMergedDatasets() as $id => $dataset) {
            if ($attr = $dataset->getAttribution()) $datasets[] = $attr;
        }
        return $datasets;
    }

    // simple getters
    public function getFile(): ?MarkdownFile { return $this->file; }
    public function getViews(): array { return $this->views; }
    protected function getPluginConfig(): array { return $this->plugin_config; }

    public function getId(): string { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function getAttribution(): ?string { return $this->attribution; }
    public function getLegend(): array { return $this->legend; }
    public function getBasemaps(): array { return $this->basemaps; }
    public function getOverrides(): array { return $this->overrides; }
    public function getViewOptions(): array { return $this->view_options; }
    public function getMaxBounds(): array { return $this->max_bounds; }
    public function getExtras(): array { return $this->extras; }
    // public function hasNoAttribution(): bool { return $this->no_attribution; }
    public function getColumnWidth(): ?int { return $this->column_width; }
    public function getMaxZoom(): ?int { return $this->max_zoom; }
    public function getMinZoom(): ?int { return $this->min_zoom; }

    public function getIncludedFeatures(): array { return $this->included_features; }
    public function getFeaturePopups(): array { return $this->feature_popups; }
    public function getMergedDatasets(): array { return $this->merged_datasets; }
    public function getTileServerOptions(): array { return $this->tile_server_options; }
    public function getBasemapInfo(): array { return $this->basemap_info; }
    public function getStartingBounds(): ?array { return $this->starting_bounds; }
    protected function getPointIds(): array { return $this->point_ids; }
    protected function getValidBasemaps(): array { return $this->valid_basemaps; }

    public static function calculateOverrides(array $tour_overrides, array $plugin_config): array {
        $config = $plugin_config['tour_options'] ?? [];
        $overrides = [];
        foreach (self::DEFAULT_OVERRIDES as $key => $value) {
            $overrides[$key] = $tour_overrides[$key] ?? $config[$key] ?? $value;
        }
        return $overrides;
    }
    public static function calculateTileServer($tile_server, array $plugin_config): array {
        $tour_options = is_array($tile_server) ? $tile_server : [];
        // set placeholder attribution, too
        // is tile server set by tour?
        if ($server = self::getServerSelection($tour_options)) {
            // only return settings from tour
            $settings = $tour_options;
        } else {
            // combine tour and plugin options
            $config = $plugin_config['tile_server'] ?? [];
            $server = self::getServerSelection($config) ?? ['provider' => self::DEFAULT_TILE_SERVER];
            $settings = array_merge($config, $tour_options);
        }
        // remove extraneous settings values
        $settings = array_diff_key($settings, array_flip(['select', 'name', 'url']));
        // set default attribution
        if ($server['provider'] && !$settings['attribution']) {
            $settings['attribution'] = 'placeholder';
        }
        // combine settings and selected server
        return array_merge($settings, $server);
    }
    private static function getServerSelection(array $options): ?array {
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
    public static function validateDatasetOverrides($overrides, array $datasets): array {
        $dataset_overrides = [];
        // must be array, can only contain ids that match tour datasets
        if (!empty($datasets) && is_array($overrides)) $dataset_overrides = array_intersect_key($overrides, $datasets);
        // auto popup props must be valid
        foreach ($dataset_overrides as $id => $override) {
            if (!is_array($override)) {
                $dataset_overrides[$id] = [];
                continue;
            }
            $dataset = $datasets[$id];
            $props = $override['auto_popup_properties'];
            if (!is_array($props)) $props = null;
            // props should only contain values from dataset properties, could also contain none
            else $props = array_values(array_intersect($props, array_merge($dataset->getProperties(), ['none'])));
            $dataset_overrides[$id]['auto_popup_properties'] = $props;
        }
        return $dataset_overrides;
    }
    public static function buildFeaturesList($features, array $all_features, array $dataset_options, array $datasets): array {
        // if features is an array, index it by id and make sure only valid features are included
        $tour_features = is_array($features) ? array_intersect_key(array_column($features, null, 'id'), $all_features) : [];
        // handle datasets add all
        foreach ($dataset_options as $id => $dataset) {
            if ($dataset['add_all']) {
                // add features to tour features list - must not already be in list and must not be hidden
                foreach ($datasets[$id]->getFeatures() as $feature_id => $feature) {
                    if (!$tour_features[$feature_id] && !$feature->isHidden()) $tour_features[$id] = ['id' => $feature_id];
                }
            } // otherwise ignore
        }
        return $tour_features;
    }
    public static function buildPointIDList(array $datasets): array {
        $ids = [];
        foreach (array_values($datasets) as $dataset) {
            if ($dataset->getType() === 'Point') $ids = array_merge($ids, array_keys($dataset->getFeatures()));
        }
        return $ids;
    }
    public static function buildIncludedFeaturesList(array $tour_features, array $datasets, array $tour_datasets): array {
        $included = $tour_features; // values will be replaced, ids ensure that the features have been added in the correct order
        // loop through all features from all datasets
        foreach ($tour_datasets as $dataset_id => $dataset_options) {
            foreach ($datasets[$dataset_id]->getFeatures() as $id => $feature) {
                // add feature if: feature is already in the list (needs to be updated from tour feature options to actual Feature object) or the dataset has include_all and the feature is not hidden
                if ($included[$id] || ($dataset_options['include_all'] && !$feature->isHidden())) $included[$id] = $feature;
            }
        }
        return $included;
    }
    public static function buildTourDatasets(array $datasets, array $tour_datasets, array $dataset_overrides, array $included_features): array {
        $merged_datasets = [];
        foreach (array_keys($tour_datasets) as $id) {
            // is the dataset actually used? (at least one feature included)
            if (!empty(array_intersect(array_keys($datasets[$id]->getFeatures()), array_keys($included_features)))) {
                // create and add merged dataset
                $merged_datasets[$id] = Dataset::fromTour($datasets[$id], $dataset_overrides[$id] ?? []);
            }
        }
        return $merged_datasets;
    }
    public static function buildPopupsList(array $features, array $tour_features, array $datasets): array {
        $popups = [];
        foreach ($features as $id => $feature) {
            $popup = $tour_features[$id]['popup_content'];
            if (!$popup && !$tour_features[$id]['remove_popup']) $popup = $feature->getPopup();
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
    public static function validateFeaturePopups(array $features, string $path): array {
        $new_list = [];
        foreach ($features as $feature) {
            $content = Feature::modifyPopupImagePaths($feature['popup_content'], $path);
            $new_list[] = array_merge($feature, ['content' => $content]);
        }
        return $new_list;
    }
    public static function renameAutoPopupProps(string $id, array $properties, $overrides) {
        try {
            $old_props = $overrides[$id]['auto_popup_properties'];
            $new_props = [];
            foreach ($old_props as $prop) { $new_props[] = $properties[$prop] ?? ''; }
            return array_merge($overrides, [$id => array_merge($overrides[$id], ['auto_popup_properties' => $new_props])]);
        } catch (\Throwable $t) {
            return $overrides;
        }
    }
}
?>