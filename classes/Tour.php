<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Data\Data;

/**
 * The Tour class stores and handles information for a specific tour
 */
class Tour {

    public $header; // Data
    public $views; // [id => header]
    public $datasets; // [id => Data]
    public $basemaps; // [file => [file, bounds, minZoom, maxZoom]]

    protected $config;

    function __construct($page, $config) {
        $this->config = $config;
        $this->header = new Data((array)$page->header());
        $this->views = [];
        foreach ($page->children()->modules() as $module) {
            if ($module->template() === 'modular/view') $this->views[$module->getCacheKey()] = new Data((array)$module->header());
        }
        $this->datasets = [];
        foreach ($this->header->get('datasets') as $dataset) {
            $id = $dataset['file'];
            $features = $this->header->get('features') ?? [];
            $this->datasets[$id] = Dataset::getDatasets()[$id]->mergeTourData(new Data($dataset), $features);
        }
        $this->basemaps = $this->setBasemaps();
    }

    protected function setBasemaps(): array {
        $basemaps = $this->header->get('basemaps');
        // from views
        foreach (array_values($this->views) as $view) {
            $basemaps = array_merge($basemaps, $view->get('basemaps'));
        }
        if (!empty($basemaps) && !empty($this->config->get('basemaps'))) {
            $basemaps = array_column($basemaps, null, 'file');
            foreach ($this->config->get('basemaps') as $basemap) {
                $file = $basemap['file'];
                if ($basemaps[$file]) {
                    if (!empty($basemap['bounds'])) {
                        $basemaps[$file] = [
                            'file' => Utils::BASEMAP_ROUTE.$file,
                            'bounds' => Utils::setBounds($basemap['bounds'] ?? []),
                            'minZoom' => $basemap['zoom_min'] ?? 8,
                            'maxZoom' => $basemap['zoom_max'] ?? 16
                        ];
                    } else {
                        unset($basemaps[$file]);
                    }
                }
            }
            return $basemaps;
        }
        return [];
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
        foreach (array_merge($this->config->get('attribution_list') ?? [], $this->header->get('attribution_list') ?? []) as $attr) {
            if (!empty($attr['text'])) $attribution[] = ['name'=>$attr['text'], 'url'=>$attr['url']];
        }
        $tileserver = $this->header->get('tileserver');
        if (empty($tileserver['url'])) $tileserver = $this->config->get('tileserver');
        if (!empty($tileserver['attribution_text'])) $attribution[] = ['name'=>$tileserver['attribution_text'], 'url'=>$tileserver['attribution_url']];
        // basemap attribution
        foreach ($this->basemaps as $basemap) {
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
                'onlyShowViewFeatures'=>$view->get('only_show_view_features') ?? $this->header->get('only_show_view_features'),
                'removeDefaultBasemap'=>$view->get('remove_default_basemap') ?? $this->header->get('remove_default_basemap'),
                'noTourBasemaps'=>$view->get('to_tour_basemaps')
            ];
            $zoom = $view->get('start.zoom');
            if (is_numeric($zoom) && $zoom >= 0) {
                // check for start location - will need to use list of features to verify that the location exists and to grab the coordinates
                if (!empty($view->get('start.location'))) $viewCenters[$viewId] = $view->get('start.location');
                if (!empty($view->get('start.lat')) && !empty($view->get('start.long'))) $v['center'] = [$view->get('start.lat'), $view->get('start.long')];
                $v['zoom'] = $zoom;
            }
            if (!empty($view['features'])) $v['features'] = array_column($view['features'], 'id');
            $views[$viewId] = $v;
        }
        // check for view centers
        if (!empty($viewCenters)) {
            // build list of all features, including hidden ones
            $features = [];
            foreach ($this->datasets as $datasetId => $dataset) {
                $features = array_merge($features, $dataset->get('features') ?? [], $dataset->get('hiddenFeatures') ?? []);
            }
            foreach ($features as $featureId => $feature) {
                $viewIds = array_keys($viewCenters, $featureId);
                if (!empty($viewIds) && $feature['geojson']['geometry']['type'] === 'Point') {
                    $feature = $feature['geojson'];
                    foreach ($viewIds as $viewId) {
                        try {
                            $lat = $feature['geometry']['coordinates'][1];
                            $long = $feature['geometry']['coordinates'][0];
                            if (is_numeric($lat) && is_numeric($long)) $views[$viewId]['center'] = [$lat, $long];
                        } catch (\Exception $e) {
                            continue;
                        }
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

    // [[type, properties (name, id, dataSource, hasPopup) geometry (type, coordinates)]]
    public function getFeatures(): array {
        $features = [];
        foreach ($this->datasets as $datasetId => $dataset) {
            foreach ($dataset->get('features') as $featureId => $feature) {
                $features[$featureId] = $feature['geojson'];
                $feature[$featureId]['properties']['hasPopup'] = !empty($feature['popupContent']);
                $feature[$featureId]['properties']['dataSource'] = $datasetId;
            }
        }
        return $features;
    }

    // [[dataSource, legendText, iconFile, iconWidth, iconHeight, iconAltText]]
    public function getLegend(): array {
        $legend = [];
        foreach ($this->datasets as $dataset) {
            if (!empty($dataset['legend'])) $legend[] = $dataset['legend'];
        }
        return $legend;
    }

    public function getPopups(): array {
        $popups = [];
        foreach ($this->datasets as $datasetId => $dataset) {
            foreach ($dataset->get('features') as $featureId => $feature) {
                if (!empty($feature['popupContent'])) {
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

    // TODO: see if this function can be removed
    public function getViewId($view) {
        return $view->getCacheKey();
    }

    // returns popups for the list of view popup buttons
    public function getViewPopups(string $viewId): array {
        $viewPopups = [];
        foreach ($this->views[$viewId]->get('features') as $featureId) {
            $popup = $this->getPopups()[$featureId];
            if (!empty($popup)) $viewPopups[] = [
                'id' => $popup['id'],
                'name' => $popup['name'],
            ];
        }
        return $viewPopups;
    }
}

?>