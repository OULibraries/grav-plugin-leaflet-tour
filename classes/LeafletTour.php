<?php
namespace Grav\Plugin\LeafletTour;

//require_once __DIR__ . '/Dataset.php';
require_once __DIR__ . '/Datasets.php';

use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Plugin\LeafletTour\Datasets;
use Grav\Common\Data\Data;
//use Grav\Common\File\CompiledJsonFile;
//use RocketTheme\Toolbox\File\MarkdownFile;

// this is just the class for referencing via twig
class LeafletTour {
    
    //protected $datasets;
    protected $config;

    function __construct($config) {
        $this->config = new Data ($config);
    }
    
    /**
     * Returns list with basemaps, datasets, locations, views, legend, popups, and attribution
     * basemaps - [image, bounds, minZoom, maxZoom]
     * datasets - [json_name => nameProp, iconOptions, iconAlt, legendAlt] TODO: which of legend/icon alt text?
     * locations = [type, properties (name, id, dataSource, hasPopup), geometry (json geometry)]
     * views - [id (view id) => center, zoom, locations, basemaps, onlyViewLocs, removeDefaultBasemap, noTourBasemaps]
     * legend - [data_src, legend_text, icon_alt, icon_file, height, width]
     * popups - [id => id, name, popup]
     * attribution - [name, url]
     */
    public function getTourInfo($page) {
        // set up variables to return
        $basemaps = [];
        $tour_datasets = [];
        $locations = [];
        $tour_views = [];
        $legend = [];
        $popups = [];
        $attribution = [];
        // set up data
        $header = new Data((array)$page->header());
        if (empty($header->get('datasets'))) return []; // quick check
        $views = $page->children()->modules();
        $datasets = Datasets::instance()->datasets;
        $tour_locations = [];
        if (!empty($header->get('locations'))) $tour_locations = array_column($header['locations'], null, 'id'); // make associative array for tour header locations
        // tmp variables
        $view_centers = [];
        // TODO: initial attribution data
        // loop through views - set $tour_views, add to $basemaps
        if (!empty($views)) {
            foreach ($views as $v) {
                $v_head = (array)$v->header();
                $view = [];
                // use list of locations to verify that location exists and grab the coordinates
                $start = $v_head['start'];
                if ($start) {
                    if ($start['location']) $view_centers[$v->getCacheKey()] = $start['location'];
                    if ($start['lat'] && $start['long']) $view['center'] = [$start['lat'], $start['long']];
                    if ($start['zoom']) $view['zoom'] = $start['zoom'];
                }
                if (!empty($v_head['locations'])) $view['locations'] = array_column($v_head['locations'], 'id');
                $view['basemaps'] = [];
                if (!empty($v_head['basemaps'])) {
                    foreach ($v_head['basemaps'] as $map) {
                        $view['basemaps'][] = $map['file'];
                        $basemaps[$map['file']] = [];
                    }
                }
                $view['onlyViewLocs'] = $v_head['only_show_view_locations'] ?? $header->get('only_show_view_locations') ?? false;
                // TODO: See if I actually need the ": null" below
                $view['removeDefaultBasemap'] = (!empty($v_head['default_basemap']) ? $v_head['default_basemap']['remove'] : null) ?? $header->get('default_basemap.remove');
                $view['noTourBasemaps'] = $v_head['no_tour_basemaps'];
                // TODO: Make sure this is actually good as an associative array
                $tour_views[$v->getCacheKey()] = $view;
            }
        }
        // get list of tour basemaps - add to $basemaps
        if (!empty($header->get('basemaps'))) {
            foreach ($header->get('basemaps') as $map) {
                $basemaps[$map['file']] = [];
            }
        }
        // loop through basemaps - set $basemaps, set $attribution
        if (!empty($basemaps) && !empty($this->config->get('basemaps'))) {
            foreach ($this->config->get('basemaps') as $map) {
                if (isset($basemaps[$map['image']])) {
                    // set basemap data
                    try {
                        $basemaps[$file] = [
                            'image' => 'user/data/leaflet-tour/images/basemaps/'.$map['image'],
                            'bounds' => [[$map['bounds']['south'], $map['bounds']['west']],[$map['bounds']['north'], $map['bounds']['east']]],
                            'minZoom' => $map['zoom_min'] ?? 8,
                            'maxZoom' => $map['zoom_max'] ?? 16
                        ];
                    } catch (Exception $e) {
                        unset($basemaps[$file]);
                        continue;
                    }
                    // set attribution data
                    $attribution[] = ['name' => $map['attribution_text'], 'url' => $map['attribution_url']];
                }
            }
        }
        // loop through datasets - set $tour_datasets, set $legend, set $locations, modify $tour_views, set $popups
        foreach ($header->get('datasets') as $header_dataset) {
            $header_dataset = new Data($header_dataset);
            // get the correct dataset to access important information (json file, dataset config, etc.)
            $dataset = Datasets::instance()->datasets[$header_dataset['file']];
            // quick checks
            if (empty($dataset)) return[];
            if (empty($dataset->locations)) return [];
            // start building info to return
            $tour_dataset = [];
            // deal with legend alt text override
            $legend_alt = $header_dataset->get('legend.alt') ?: $header_dataset->get('legend.text') ?: $dataset->legend_alt ?: $dataset->legend_text;
            // icon overrides
            if ($dataset->icon_settings) $icon = $dataset->icon_settings;
            if ($header_dataset['icon']) {
                if ($icon && !$header_dataset->get('icon.use_defaults')) $icon = array_merge($icon, $header_dataset['icon']);
                else $icon = $header_dataset['icon'];
            }
            // format icon options, add options to $tour_dataset
            if ($icon && $icon['file']) {
                $options = [
                    'iconUrl' => 'user/data/leaflet-tour/images/markers/'.$icon['file'],
                    'iconSize' => [$icon['width'] ?? 14, $icon['height'] ?? 14],
                    'className' => 'leaflet-marker',
                ];
                if ($icon['class']) $options['className'] .= ' '.$icon['class'];
                if ($icon['retina']) $options['iconRetinaUrl'] = 'user/data/leaflet-tour/images/markers/'.$icon['retina'];
                if (isset($icon['anchor_x']) && isset($icon['anchor_y'])) $options['iconAnchor'] = [$icon['anchor_x'], $icon['anchor_y']];
                $options['tooltipAnchor'] = [$icon['tooltip_anchor_x'] ?? -5, $icon['tooltip_anchor_y'] ?? 5];
                if ($icon['shadow']) {
                    $options['shadowUrl'] = 'user/data/leaflet-tour/images/markerShadows/'.$icon['shadow'];
                    $options['shadowSize'] = [$icon['shadow_width'] ?? $icon['width'] ?? 14, $icon['shadow_height'] ?? $icon['height'] ?? 14];
                    if (isset($icon['shadow_anchor_x']) && isset($icon['shadow_anchor_y'])) $options['shadowAnchor'] = [$icon['shadow_anchor_x'], $icon['shadow_anchor_y']];
                }
                $tour_dataset['iconOptions'] = $options;
                $tour_dataset['iconAlt'] = $icon['icon_alt'] ?: $legend_alt;
                $tour_dataset['legendAlt'] = $legend_alt;
            }
            // set $tour_datasets
            $tour_datasets[$header_dataset['file']] = $tour_dataset;
            // set $legend
            $legend_text = $header_dataset->get('legend.text') ?? $dataset->legend_text;
            if ($legend_text) {
                $data_legend = [
                    'data_src'=>$header_dataset['file'],
                    'legend_text'=>$legend_text,
                ];
                if ($icon) {
                    $data_legend['icon_alt'] = $icon['icon_alt'] ?: $legend_alt;
                    $data_legend['icon_file'] = $options['iconUrl'];
                    $data_legend['width'] = $icon['width'] ?? 14;
                    $data_legend['height'] = $icon['height'] ?? 14;
                } else {
                    // TODO: icon alt??
                    $data_legend['icon_file'] = 'user/themes/qgis-2-leaflet/images/default/marker-icon.png';
                    $data_legend['width'] = 41;
                    $data_legend['height'] = 12;
                }
                $legend[] = $data_legend;
            }
            // set $locations, modify $tour_views, set $popups
            foreach ($dataset->locations as $id => $loc) {
                // check if the location is a view center
                $keys = array_keys($view_centers, $id);
                if (!empty($keys)) {
                    foreach ($keys as $key) {
                        try {
                            $lat = $loc['geometry']['coordinates'][1];
                            $long = $loc['geometry']['coordinates'][0];
                            if ($lat && $long) $tour_views[$key]['center'] = [$lat, $long];
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                }
                // check if it should be included - show all or in tour location list
                if (!$header_dataset['show_all'] && !$tour_locations[$id]) continue;
                $name = $dataset->location_info[$id]['custom_name'] ?: $loc['properties'][$dataset->name_prop];
                $popup = $dataset->location_info[$id]['popup_content'];
                $hasPopup = !empty($popup);
                $header_loc = $tour_locations[$id];
                if ($header_loc) {
                    // overwrite as needed
                    if ($header_loc['custom_name']) $name = $header_loc['custom_name'];
                    if ($header_loc['popup_content']) {
                        $hasPopup = true;
                        $popup = $header_loc['popup_content'];
                    }
                    else if ($header_loc['remove_popup']) $hasPopup = false;
                }
                $location = [
                    'type' => 'Feature',
                    'properties' => [
                        'name' => $name,
                        'id' => $id,
                        'dataSource' => $dataset->json_name,
                        'hasPopup' => $hasPopup
                    ],
                    'geometry' => $loc['geometry'],
                ];
                $locations[] = $location;
                // popups
                if ($hasPopup) {
                    $popups[$id] = [
                        'id' => $id,
                        'name' => $name,
                        'popup' => $popup
                    ];
                }
            }
        }
        // return everything
        return [
            'basemaps'=>$basemaps,
            'datasets'=>$tour_datasets,
            'locations'=>$locations,
            'views'=>$tour_views,
            'legend'=>$legend,
            'popups'=>$popups,
            'attribution'=>$attribution
        ];
    }
    
    public function getViewId($view) {
        return $view->getCacheKey();
    }
    
    public function getPopupBtns($view_id, $tour_data) {
        $view = $tour_data['views'][$view_id];
        return gettype($tour_data);
        $locations = [];
        if (empty($view['locations'])) return [];
        foreach ($view['locations'] as $loc) {
            if ($tour_data['locations'][$loc['id']]['hasPopup']) {
                $locations[] = ['id'=>$loc['id'], 'name'=>$loc['name']];
            }
        }
        return $locations;
    }
    
}