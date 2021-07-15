<?php
namespace Grav\Plugin\LeafletTour;

//require_once __DIR__ . '/Dataset.php';
require_once __DIR__ . '/Datasets.php';

use Grav\Common\Grav;
//use Grav\Common\Page\Page;
use Grav\Plugin\LeafletTour\Datasets;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledJsonFile;
//use RocketTheme\Toolbox\File\MarkdownFile;
use Symfony\Component\Yaml\Yaml;

// this is just the class for referencing via twig
class LeafletTour {

    // default values if the default marker icon is used
    const DEFAULT_MARKER_OPTIONS = [
        'iconAnchor' => [12, 41],
        'iconRetinaUrl' => 'user/plugins/leaflet-tour/images/marker-icon-2x.png',
        'iconSize' => [25, 41],
        'iconUrl' => 'user/plugins/leaflet-tour/images/marker-shadow.png',
        'shadowSize' => [41, 41],
        'shadowUrl' => 'user/plugins/leaflet-tour/images/marker-shadow.png',
        'className' => 'leaflet-marker',
        'tooltipAnchor' => [-12, 20]
    ];
    // default values if the default marker icon is not used
    const MARKER_FALLBACKS = [
        'iconSize' => [14, 14],
        'shadowSize' => [],
        'tooltipAnchor' => [-5, 5],
        'iconAnchor' => [],
    ];
    
    //protected $datasets;
    protected $config;

    function __construct($config) {
        $this->config = new Data ($config);
    }
    
    /**
     * Returns list with basemaps, datasets, locations, views, legend, popups, and attribution
     * basemaps - [image, bounds, minZoom, maxZoom]
     * datasets - [jsonFilename => nameProperty, iconOptions, legendAltText]
     * features = [type, properties (name, id, dataSource, hasPopup), geometry (json geometry)]
     * views - [id => center, zoom, features, basemaps, onlyShowViewFeatures, removeDefaultBasemap, noTourBasemaps]
     * legend - [dataSource, legendText, iconAltText, iconFile, iconHeight, iconWidth]
     * popups - [id => id, name, popup]
     * attribution - [name, url]
     */
    public function getTourData($page) {
        // set up
        $data = ['basemaps'=>[], 'datasets'=>[], 'features'=>[], 'views'=>[], 'legend'=>[], 'popups'=>[], 'attribution'=>[]];
        $header = new Data((array)$page->header());
        if (empty($header->get('datasets'))) return []; // quick check
        $modules = $page->children()->modules();
        $headerFeatures = [];
        if (!empty($header->get('features'))) $headerFeatures = array_column($header['features'], null, 'id'); // make associative array for tour header features
        $viewCenters = [];
        // loop through views - set views and add to basemaps
        if (!empty($modules)) {
            foreach ($modules as $module) {
                // make sure these are views
                if ($module->template() !== 'modular/view') return;
                $viewHeader = new Data((array)$module->header());
                $view = [
                    'basemaps'=>[],
                    'onlyShowViewFeatures'=>$viewHeader->get('only_show_view_features') ?? $header->get('only_show_view_features'),
                    'removeDefaultBasemap'=>$viewHeader->get('remove_default_basemap') ?? $header->get('remove_default_basemap'),
                    'noTourBasemaps'=>$viewHeader->get('to_tour_basemaps')
                ];
                $zoom = $viewHeader->get('start.zoom');
                if (is_numeric($zoom) && $zoom >= 0) {
                    // check for start location - will need to use list of features to verify that the location exists and to grab the coordinates
                    if (!empty($viewHeader->get('start.location'))) $viewCenters[$module->getCacheKey()] = $viewHeader->get('start.location');
                    if (!empty($viewHeader->get('start.lat')) && !empty($viewHeader->get('start.long'))) $view['center'] = [$viewHeader->get('start.lat'), $viewHeader->get('start.long')];
                    $view['zoom'] = $zoom;
                }
                if (!empty($viewHeader['features'])) $view['features'] = array_column($viewHeader['features'], 'id');
                // loop through basemaps to add to both view basemaps and to the full basemap collection
                if (!empty($viewHeader['basemaps'])) {
                    foreach ($viewHeader['basemaps'] as $basemap) {
                        $view['basemaps'][] = $basemap['file'];
                        $data['basemaps'][$basemap['file']] = [1];
                    }
                }
                $data['views'][$module->getCacheKey()] = $view;
            }
        }
        // get list of tour basemaps and add to basemaps
        if (!empty($header->get('basemaps'))) {
            foreach ($header->get('basemaps') as $basemap) {
                $data['basemaps'][$basemap['file']] = [1];
            }
        }
        // set initial attribution
        $attribution = [];
        foreach ($this->config->get('attribution_list') ?? [] as $attr) {
            if (!empty($attr['text'])) $attribution[] = ['name'=>$attr['text'], 'url'=>$attr['url']];
        }
        if (!empty($header->get('tileserver.url'))) $tileserver = $header->get('tileserver');
        else $tileserver = $this->config->get('tileserver');
        if ($tileserver['attribution_text']) $attribution[] = ['name'=>$tileserver['attribution_text'], 'url'=>$tileserver['attribution_url']];
        // loop through basemaps - set $basemaps, set $attribution
        if (!empty($data['basemaps']) && !empty($this->config->get('basemaps'))) {
            foreach ($this->config->get('basemaps') as $basemap) {
                if (!empty($data['basemaps'][$basemap['file']])) {
                    // set basemap data
                    try {
                        $data['basemaps'][$basemap['file']] = [
                            'file' => 'user/data/leaflet-tour/images/basemaps/'.$basemap['file'],
                            'bounds' => [[$basemap['bounds']['south'], $basemap['bounds']['west']],[$basemap['bounds']['north'], $basemap['bounds']['east']]],
                            'minZoom' => $basemap['zoom_min'] ?? 8,
                            'maxZoom' => $basemap['zoom_max'] ?? 16
                        ];
                    } catch (Exception $e) {
                        unset($data['basemaps'][$basemap['file']]);
                        continue;
                    }
                    // set attribution data
                    if (!empty($basemap['attribution_text'])) $attribution[] = ['name' => $basemap['attribution_text'], 'url' => $basemap['attribution_url']];
                }
            }
        }
        $data['attribution'] = $attribution;
        // loop through datasets - set datasets, legend, features and popups, modify views
        foreach ($header->get('datasets') as $headerDataset) {
            $headerDataset = new Data($headerDataset);
            // get the correct dataset to access important information (json file, dataset config, etc.)
            $dataset = Datasets::instance()->getDatasets()[$headerDataset['file']];
            // quick checks
            if (empty($dataset)) return[];
            if (empty($dataset->features)) return [];
            // start building info to return
            $datasetData = [];
            // icon overrides
            $datasetData['iconOptions'] = self::setIconOptions($headerDataset->get('icon') ?? [], $dataset->iconOptions ?? []);
            // legend
            $legendText = $headerDataset->get('legend_text') ?? $dataset->legendText;
            $iconAltText = $headerDataset->get('icon_alt') ?? $dataset->iconAltText;
            if (!empty($legendText)) {
                // set alt text for map icons (legend alt text)
                $datasetData['legendAltText'] = $headerDataset->get('legend_alt') ?? $headerDataset->get('legend_text') ?? $dataset->legendAltText ?? $dataset->legendText;
                // set legend
                $legend = [
                    'dataSource'=>$headerDataset['file'],
                    'legendText'=>$legendText,
                ];
                // legend icon
                if (!empty($iconAltText)) $legend['iconAltText'] = $iconAltText;
                $legend['iconFile'] = $datasetData['iconOptions']['iconUrl'];
                $legend['iconWidth'] = $datasetData['iconOptions']['iconSize'][0];
                $legend['iconHeight'] = $datasetData['iconOptions']['iconSize'][1];
                // add legend
                $data['legend'][] = $legend;
            }/* else if (!empty($iconAltText)) {
                $datasetData['legendAltText'] = $iconAltText;
            }*/
            // add dataset
            $data['datasets'][$headerDataset['file']] = $datasetData;
            // set features, modify views, set popups
            foreach ($dataset->features as $featureId => $feature) {
                // check if the location is a view center
                $viewIds = array_keys($viewCenters, $featureId);
                if (!empty($viewIds)) {
                    foreach ($viewIds as $viewId) {
                        try {
                            $lat = $feature['geometry']['coordinates'][1];
                            $long = $feature['geometry']['coordinates'][0];
                            if ($lat && $long) $data['views'][$viewId]['center'] = [$lat, $long];
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                }
                // doublecheck that a given feature should be included - has to be a tour location
                if (!$headerDataset['show_all'] && !$data['features'][$featureId]) continue;
                $name = $dataset->features[$featureId]['customName'] ?? $feature['properties'][$dataset->nameProperty];
                $popup = $dataset->features[$featureId]['popupContent'];
                $hasPopup = !empty($popup);
                $featureOverride = $headerFeatures[$featureId];
                if ($featureOverride) {
                    // overwrite as needed
                    $name = $featureOverride['custom_name'] ?? $name;
                    if (!empty($featureOverride['popup_content'])) {
                        $hasPopup = true;
                        $popup = $featureOverride['popup_content'];
                    }
                    else if ($featureOverride['remove_popup']) {
                        $hasPopup = false;
                        $popup = null;
                    }
                }
                $featureData = [
                    'type' => 'Feature',
                    'properties' => [
                        'name' => $name,
                        'id' => $featureId,
                        'dataSource' => $dataset->jsonFilename,
                        'hasPopup' => $hasPopup
                    ],
                    'geometry' => $feature['geometry'],
                ];
                $data['features'][] = $featureData;
                // popups
                if ($hasPopup) {
                    $data['popups'][$featureId] = [
                        'id' => $featureId,
                        'name' => $name,
                        'popup' => $popup
                    ];
                }
            }
        }
        // return everything
        return $data;
    }

    protected static function setIconOptions(array $headerOptions, array $datasetOptions) {
        if (empty($headerOptions) && empty($datasetOptions)) return self::DEFAULT_MARKER_OPTIONS; // no icon options
        else if ($headerOptions['use_defaults']) $datasetOptions = []; // ignore icon options from dataset file
        $options = array_merge($datasetOptions, $headerOptions);
        // determine which set of default values to use
        if (!empty($options['file'])) $defaults = self::MARKER_FALLBACKS;
        else $defaults = self::DEFAULT_MARKER_OPTIONS;
        $iconOptions = [];
        $iconOptions['iconUrl'] = !empty($options['file']) ? 'user/data/leaflet-tour/images/markers/'.$options['file'] : $defaults['iconUrl'];
        $iconOptions['iconSize'] =[$options['height'] ?? $defaults['iconSize'][0], $options['width'] ?? $defaults['iconSize'][1]];
        // iconAnchor is all or nothing unless default marker is used
        if (isset($options['anchor_x']) && isset($options['anchor_y']) || empty($options['file'])) $iconOptions['iconAnchor'] = [$options['anchor_x'] ?? $defaults['iconAnchor'][0], $options['anchor_y'] ?? $defaults['iconAnchor'][1]];
        $retinaUrl = $options['retina'] ? 'user/data/leaflet-tour/images/markers/'.$options['retina'] : $defaults['iconRetinaUrl'];
        if (!empty($retinaUrl)) $iconOptions['iconRetinaUrl'] = $retinaUrl;
        $iconOptions['className'] = 'leaflet-marker '.($options['class'] ?? '');
        $iconOptions['tooltipAnchor'] = [$options['tooltip_anchor_x'] ?? $defaults['tooltipAnchor'][0], $options['tooltip_anchor_y'] ?? $defaults['tooltipAnchor'][1]];
        if (!empty($options['shadow']) || empty($options['file'])) {
            $iconOptions['shadowUrl'] = $options['shadow'] ? 'user/data/leaflet-tour/images/markerShadows/'.$options['shadow'] : $defaults['shadowUrl'];
            $iconOptions['shadowSize'] = [$options['shadow_width'] ?? $defaults['shadowSize'][0], $options['shadow_height'] ?? $defaults['shadowSize'][1]];
            if (isset($options['shadow_anchor_x']) && isset($options['shadow_anchor_y'])) $iconOptions['shadowAnchor'] = [$options['shadow_anchor_x'], $options['shadow_anchor_y']];
        }
        return $iconOptions;
    }
    
    public function getViewId($view) {
        return $view->getCacheKey();
    }

    public function getViewPopups($viewFeatures, $popupList) {
        if (empty($viewFeatures) or empty($popupList)) return [];
        //return 'viewFeatures: '.count($viewFeatures).'<br />popupList: '.count($popupList);
        $viewPopups = [];
        foreach ($viewFeatures as $featureId) {
            $popup = $popupList[$featureId];
            if (!empty($popup)) {
                $viewPopups[] = ['id'=>$popup['id'], 'name'=>$popup['name']];
            }
        }
        return $viewPopups;
    }

    // temp
    public function getDatasetsTest() {
        foreach ($this->config->get('data_files') as $fileData) {
            $tmp = [
                'id1'=>['name'=>'name1', 'file'=>'file1'],
                'id2'=>['name'=>'name2', 'file'=>'file2'],
            ];
            $tmp2 = array_column($tmp, null);
            return implode(",", array_keys($tmp2));
            //return Grav::instance()['uri']->rootUrl(true);
            $route1 = Grav::instance()['locator']->findResource('user://').'/data/leaflet-tour/datasets/uploads/'.$fileData['name'];
            $file1 = CompiledJsonFile::instance($route1);
            $route2 = Grav::instance()['locator']->getBase().'/'.$fileData['path'];
            $file2 = CompiledJsonFile::instance($route2);
            //return Grav::instance()['locator']->getBase();
            return "Route 1: ".$file1->exists()."\r\n\r\nRoute 2:".$file2->exists()."\r\n";
            return Yaml::dump($fileData);
        }
    }

    // URI Notes
    // Use Grav::instance()['uri']
    // On tour page:
    // ->paths() returns tour-1
    // ->path() -- /tour-1
    // ->route() -- /tour-1
    // ->route(true) -- /wyman-travels/tour-1
    // ->route(true, true) -- http://testing.digischolar.oucreate.com/wyman-travels/tour-1
    // ->host() -- testing.digischolar.oucreate.com
    
    /*public function getPopupBtns($viewId, $tourData) {
        $view = $tourData['views'][$viewId];
        return gettype($tourData);
        $locations = [];
        if (empty($view['features'])) return [];
        foreach ($view['features'] as $loc) {
            if ($tour_data['features'][$loc['id']]['hasPopup']) {
                $locations[] = ['id'=>$loc['id'], 'name'=>$loc['name']];
            }
        }
        return $locations;
    }*/
    
}