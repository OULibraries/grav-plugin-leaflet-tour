<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Data\Data;

/**
 * The Tour class stores and handles information for a specific tour
 */
class Tour {

    protected $header; // Data
    protected $views; // [id => header]
    protected $datasets; // [id => Data]
    protected $basemaps; // [file => [file, bounds, minZoom, maxZoom]]
    protected $features;
    protected $allFeatures;

    protected $config;

    function __construct($page, $config) {
        $this->config = $config;
        $this->header = new Data((array)$page->header());
        $this->views = [];
        foreach ($page->children()->modules() as $module) {
            if ($module->template() === 'modular/view') $this->views[$module->getCacheKey()] = new Data((array)$module->header());
        }
        $this->datasets = [];
        foreach ($this->header->get('datasets') ?? [] as $dataset) {
            $id = $dataset['file'];
            $features = $this->header->get('features') ?? [];
            $this->datasets[$id] = Dataset::getDatasets()[$id]->mergeTourData(new Data($dataset), $features);
        }
        $this->basemaps = $this->setBasemaps();
        $this->features = $this->setFeatures();
        $this->allFeatures = $this->setAllFeatures();
    }

    // [file => [file, bounds, minZoom, maxZoom]]
    protected function setBasemaps(): array {
        $tourBasemaps = array_column($this->header->get('basemaps') ?? [], 'file');
        // from views
        foreach (array_values($this->views) as $view) {
            $viewBasemaps = array_column($view->get('basemaps') ?? [], 'file');
            $tourBasemaps = array_merge($tourBasemaps, $viewBasemaps);
        }
        $configBasemaps = array_column($this->config->get('basemaps') ?? [], null, 'file');
        if (!empty($tourBasemaps) && !empty($configBasemaps)) {
            $basemaps = [];
            foreach ($tourBasemaps as $file) {
                if (empty($basemaps[$file]) && !empty($configBasemaps[$file])) {
                    // we do have data for the basemap, but it hasn't been added yet
                    $basemap = $configBasemaps[$file];
                    $bounds = Utils::setBounds($basemap['bounds'] ?? []);
                    if (!empty($bounds)) {
                        $basemaps[$file] = [
                            'file' => Utils::BASEMAP_ROUTE.$file,
                            'bounds' => $bounds,
                            'minZoom' => $basemap['zoom_min'] ?? 8,
                            'maxZoom' => $basemap['zoom_max'] ?? 16
                        ];
                    }
                }
            }
            return $basemaps;
        }
        return [];
    }

    protected function setFeatures(): array {
        $features = [];
        foreach ($this->datasets as $datasetId => $dataset) {
            foreach ($dataset->get('features') as $featureId => $feature) {
                $features[$featureId] = $feature['geojson'];
                $features[$featureId]['properties']['hasPopup'] = !empty($feature['popupContent']);
                $features[$featureId]['properties']['dataSource'] = $datasetId;
            }
        }
        return $features;
    }

    protected function setAllFeatures(): array {
        $features = [];
        foreach ($this->datasets as $datasetId => $dataset) {
            $features = array_merge($features, $dataset->get('features') ?? [], $dataset->get('hiddenFeatures') ?? []);
        }
        return $features;
    }

    // [file => [file, bounds, minZoom, maxZoom]]
    public function getBasemaps(): array {
        return $this->basemaps;
    }

    // [[name, url]]
    public function getAttribution(): array {
        $attribution = [];
        // get attribution from config
        // TODO: allow adding attribution in tour config, too
        $configAttribution = array_column($this->config->get('attribution_list') ?? [], null, 'text');
        $tourAttribution = array_column($this->header->get('attribution_list') ?? [], null, 'text');
        foreach (array_values(array_merge($configAttribution, $tourAttribution)) as $attr) {
            if (!empty($attr['text'])) $attribution[] = ['name'=>$attr['text'], 'url'=>$attr['url']];
        }
        $tileserver = $this->header->get('tileserver');
        if (empty($tileserver['url'])) $tileserver = $this->config->get('tileserver');
        if (!empty($tileserver['attribution_text'])) $attribution[] = ['name'=>$tileserver['attribution_text'], 'url'=>$tileserver['attribution_url']];
        // basemap attribution
        $configBasemaps = array_column($this->config->get('basemaps') ?? [], null, 'file');
        foreach (array_keys($this->getBasemaps()) as $file) {
            $basemap = $configBasemaps[$file];
            if (!empty($basemap['attribution_text'])) $attribution[] = ['name' => $basemap['attribution_text'], 'url' => $basemap['attribution_url']];
        }
        return $attribution;
    }

    // [viewId => [basemaps, onlyShowViewFeatures, removeDefaultBasemap, noTourBasemaps, zoom, center, features]]
    public function getViews(): array {
        $views = [];
        $viewCenters = [];
        foreach ($this->views as $viewId => $view) {
            $v = [
                'basemaps'=>[],
                'features'=>[],
                'onlyShowViewFeatures'=>$view->get('only_show_view_features') ?? $this->header->get('only_show_view_features') ?? false,
                'removeDefaultBasemap'=>$view->get('remove_default_basemap') ?? $this->header->get('remove_default_basemap') ?? true,
                'noTourBasemaps'=>$view->get('no_tour_basemaps') ?? false,
            ];
            $zoom = $view->get('start.zoom');
            if (is_numeric($zoom) && $zoom >= 0) {
                // check for start location - will need to use list of features to verify that the location exists and to grab the coordinates
                if (!empty($view->get('start.location'))) $viewCenters[$viewId] = $view->get('start.location');
                $long = $view->get('start.long');
                $lat = $view->get('start.lat');
                if (Utils::isValidPoint([$long, $lat])) $v['center'] = [$long, $lat];
                $v['zoom'] = $zoom;
            }
            if (!empty($view['features'])) {
                foreach (array_column($view['features'], 'id') as $featureId) {
                    if ($this->features[$featureId]) $v['features'][] = $featureId;
                }
            }
            if (empty($v['features'])) $v['onlyShowViewFeatures'] = false;
            // basemaps
            foreach (array_column($view->get('basemaps') ?? [], 'file') as $file) {
                if (!empty($this->getBasemaps()[$file])) $v['basemaps'][] = $file;
            }
            $views[$viewId] = $v;
        }
        // check for view centers
        if (!empty($viewCenters)) {
            foreach ($this->allFeatures as $featureId => $feature) {
                $viewIds = array_keys($viewCenters, $featureId);
                if (!empty($viewIds) && $feature['geojson']['geometry']['type'] === 'Point' && Utils::isValidPoint($feature['geojson']['geometry']['coordinates'])) {
                    $feature = $feature['geojson'];
                    foreach ($viewIds as $viewId) {
                        $views[$viewId]['center'] = [$feature['geometry']['coordinates'][0], $feature['geometry']['coordinates'][1]];
                    }
                }
            }
        }
        return $views;
    }

    // [id => [legendAltText, iconOptions]]
    public function getDatasets(): array {
        $datasets = [];
        foreach ($this->datasets as $datasetId => $dataset) {
            $datasets[$datasetId] = [
                'legendAltText' => $dataset->get('legendAltText'),
                'iconOptions' => $dataset->get('iconOptions'),
            ];
        }
        return $datasets;
    }

    // [id => [type, properties (name, id, dataSource, hasPopup) geometry (type, coordinates)]]
    public function getFeatures(): array {
        return $this->features;
    }

    // [[dataSource, legendText, iconFile, iconWidth, iconHeight, iconAltText]]
    public function getLegend(): array {
        if (!($this->header->get('legend') ?? true)) return [];
        $legend = [];
        foreach ($this->datasets as $dataset) {
            if (!empty($dataset['legend'])) $legend[] = $dataset['legend'];
        }
        return $legend;
    }

    // [id => [id, name, popup]]
    public function getPopups(): array {
        $popups = [];
        foreach ($this->datasets as $datasetId => $dataset) {
            foreach ($dataset->get('features') as $featureId => $feature) {
                if ($this->features[$featureId] && !empty($feature['popupContent'])) {
                    $popups[$featureId] = [
                        'id' => $featureId,
                        'name' => $feature['name'],
                        'popup' => $feature['popupContent'],
                    ];
                }
            }
        }
        return $popups;
    }

    public function getViewId($view) {
        return $view->getCacheKey();
    }

    // returns popups for the list of view popup buttons
    // [[id, name]]
    public function getViewPopups(string $viewId): array {
        $view = $this->views[$viewId];
        $showList = $view->get('list_popup_buttons') ?? $this->header->get('list_popup_buttons') ?? true;
        if (!$showList) return [];
        if (empty($view) || empty($view->get('features')) || empty($this->getPopups())) return [];
        $viewPopups = [];
        foreach (array_column($view->get('features'), 'id') as $featureId) {
            $popup = $this->getPopups()[$featureId];
            if (!empty($popup)) $viewPopups[] = [
                'id' => $popup['id'],
                'name' => $popup['name'],
            ];
        }
        return $viewPopups;
    }

    // TODO: test
    public function getOptions(): array {
        $options = [
            'zoom' => $this->header->get('start.zoom') ?? 10,
            'maxZoom' => $this->header->get('zoom_max') ?? 16,
            'minZoom' => $this->header->get('zoom_min') ?? 8,
            'tileServer' => $this->header->get('tileserver.url') ?? $this->config->get('tileserver.url'),
            'removeDefaultBasemap' => $this->header->get('remove_default_basemap'),
            'tourMaps' => array_column($this->header->get('basemaps') ?? [], 'file'),
            //'datasets' => $this->getDatasets(),
            'wideCol' => $this->header->get('wide_column') ?? $this->config->get('wide_column') ?? false,
            'showMapLocationInUrl' => $this->header->get('show_map_location_in_url') ?? $this->config->get('show_map_location_in_url') ?? true,
        ];
        // tour center
        if ($this->header->get('start.location')) {
            $id = $this->header->get('start.location');
            foreach ($this->allFeatures as $featureId => $feature) {
                if ($featureId === $id && $feature['geojson']['geometry']['type'] === 'Point' && Utils::isValidPoint($feature['geojson']['geometry']['coordinates'])) {
                    $feature = $feature['geojson'];
                    $options['center'] = [$feature['geometry']['coordinates'][0], $feature['geometry']['coordinates'][1]];
                }
            }
        }
        if (empty($options['center'])) {
            $point = [$this->header->get('start.long'), $this->header->get('start.lat')];
            if (Utils::isValidPoint($point)) $options['center'] = $point;
            else $options['center'] = [0, 0];
        }
        // tour bounds
        $bounds = Utils::setBounds($this->header->get('bounds') ?? []);
        if ($bounds) $options['bounds'] = $bounds;
        return $options;
    }

    // returns html code for a popup button, referenced by shortcode
    public static function getViewPopup(string $featureId, string $buttonId, string $featureName): string {
        return '<button id="'.$buttonId.'" onClick="openDialog(\''.$featureId.'-popup\', this)" class="btn view-popup-btn">View '.$featureName.' popup</button>';
    }

    // TODO: test
    public static function hasPopup($feature, $tourFeatures): bool {
        $hasPopup = !empty($feature->getPopup());
        $id = $feature->getId();
        $tourFeatures = array_column($tourFeatures ?? [], null, 'id');
        $f = $tourFeatures[$id];
        if ($f) {
            if (!empty($f['popup_content'])) $hasPopup = true;
            else if ($f['remove_popup']) $hasPopup = false;
        }
        return $hasPopup;
    }
}

?>