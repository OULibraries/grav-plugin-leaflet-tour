<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Data\Data;

/**
 * The Tour class stores and handles information for a specific tour. It is used when creating a tour page displayed on the site, and finds/organizes/merges the various required pieces of information that will either be passed on to a twig template or to the tour.js code.
 */
class Tour {

    protected $header; // Data
    protected $views; // [id => header]
    protected $datasets; // [id => Data]
    protected $basemaps; // [file => [file, bounds, minZoom, maxZoom]]
    protected $features;
    protected $allFeatures;
    protected $tileServer;

    protected $config; // Data

    /**
     * Tour constructor. While some information will be set when the getter functions are called, some information is useful in multiple areas, and is therefore set here.
     * @param Page $page - The Page object for the tour. Required in order to pull out the header and the view headers
     * @param Data $config - The plugin config. Required for certain values/defaults.
     */
    function __construct($page, $config) {
        $this->config = $config;
        $this->header = new Data((array)$page->header());
        $this->views = [];
        foreach ($page->children()->modules() as $module) { // pull out all view headers
            if ($module->template() === 'modular/view') $this->views[$module->getCacheKey()] = new Data((array)$module->header());
        }
        $this->datasets = [];
        // loop through all tour datasets and merge the info from the relevant Dataset object with the dataset info from the tour header
        foreach ($this->header->get('datasets') ?? [] as $dataset) {
            $id = $dataset['file'];
            $features = $this->header->get('features') ?? [];
            $this->datasets[$id] = Dataset::getDatasets()[$id]->mergeTourData(new Data($dataset), $features);
        }
        $this->basemaps = $this->setBasemaps(); // set ahead of time because the basemaps list will be used when determining the attribution list, as well as to double-check that view basemaps have all the needed information
        $this->features = $this->setFeatures(); // set ahead of time because the features list will be used to ensure that all view features are valid tour features and to determine which features to check for popups
        $this->allFeatures = $this->setAllFeatures(); // set ahead of time because the list of all features will be used when determining coordinates for starting bounds where a location has been provided - can't use the normal features list, because a feature might be chosen that is included in a dataset where show_all is not enabled, so that feature might not be added to the list of tour features, despite being a valid choice for starting location
        $this->tileServer = $this->setTileServer(); // set ahead of time (needed for returning the general tour options) because the tile server will be used when determining attribution lists
    }

    /**
     * Sets the basemaps array. This array includes all tour and view basemaps, along with the information stored in the plugin config (i.e. bounds, attribution, etc.).
     * @return array - [file => [file, bounds, minZoom, maxZoom]]
     */
    protected function setBasemaps(): array {
        $tourBasemaps = array_column($this->header->get('basemaps') ?? [], 'file'); // turns tour basemaps list from array of ['file'=>'filename'] to ['filename']
        // from views
        foreach (array_values($this->views) as $view) { // turn view basemaps from arrays of ['file=>'filename'] to ['filename'] and add them to the tour basemaps list
            $viewBasemaps = array_column($view->get('basemaps') ?? [], 'file');
            $tourBasemaps = array_merge($tourBasemaps, $viewBasemaps);
        }
        $configBasemaps = array_column($this->config->get('basemaps') ?? [], null, 'file'); // index basemaps from the plugin config using the filename
        if (!empty($tourBasemaps) && !empty($configBasemaps)) { // make sure each basemap in the tour basemaps list is in the plugin config list, and store the bounds and zoom settings
            $basemaps = [];
            foreach ($tourBasemaps as $file) {
                if (empty($basemaps[$file]) && !empty($configBasemaps[$file])) { // check that the new basemaps list does not already have the entry - the tour basemaps list may have duplicates, as it is not an associative array
                    // we do have data for the basemap, but it hasn't been added yet
                    $basemap = $configBasemaps[$file];
                    $bounds = Utils::setBounds($basemap['bounds'] ?? []); // make sure bounds are valid - if they aren't, the basemap cannot be included at all
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

    /**
     * Sets the features array. This array excludes features in tour datasets where show_all is not enabled and the feature in question is not in the tour header features list. (Note that this exclusion is performed by the Dataset->mergeTourData function, and therefore is not performed in this function).
     * @return array - [id => [type, geometry (type, coordinates), properties (name, id, dataSource, hasPopup)]]
     */
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

    /**
     * Sets array with all features - both valid tour features and hidden features. Only used internally.
     * @return array - [id => [geojson]] (some may also have name and popupContent, but these will be ignored)
     */
    protected function setAllFeatures(): array {
        $features = [];
        foreach ($this->datasets as $datasetId => $dataset) {
            $features = array_merge($features, $dataset->get('features') ?? [], $dataset->get('hiddenFeatures') ?? []);
        }
        return $features;
    }

    /**
     * Sets the appropriate tile server. Priority goes to tour selection, then tour URL, the plugin selection, and finally plugin URL.
     * @return array - either [url, attribution_url, attribution_text] or array from Utils TILE_SERVERS list
     */
    protected function setTileServer(): array {
        // check for tileserver selection from tour
        $server = $this->header->get('tile_server_select');
        if (!empty($server) && $server !== 'none') return Utils::TILE_SERVERS[$server];
        // check for tileserver url from tour
        $server =  $this->header->get('tile_server');
        if (!empty($server['url'])) return $server;
        // check for tileserver selection from plugin
        $server = $this->config->get('tile_server_select');
        if (!empty($server) && $server !== 'none') return Utils::TILE_SERVERS[$server];
        // check for tileserver url from plugin
        $server = $this->config->get('tile_server');
        if (!empty($server['url'])) return $server;
        return [];
    }

    /**
     * @return array - [file => [file, bounds, minZoom, maxZoom]]
     */
    public function getBasemaps(): array {
        return $this->basemaps;
    }

    /**
     * Gets list of "short attribution" items - attributions where name and url are provided. Combines items from the plugin config, the tour header, the tile server, and any basemaps included in the tour or views.
     * @return array - [[name, url]]
     */
    public function getAttribution(): array {
        $attribution = [];
        // TODO: Continue indexing by text so that duplicate text from tile server and/or basemap attribution would not be included - tour overwrites tile server overwrites basemaps overwrites plugin
        $configAttribution = array_column($this->config->get('attribution_list') ?? [], null, 'text'); // get attribution from plugin config, index by text
        $tourAttribution = array_column($this->header->get('attribution_list') ?? [], null, 'text'); // get attribution from tour config, index by text
        $serverAttribution = [];
        if ($this->tileServer['url'] && $text=$this->tileServer['attribution_text']) $serverAttribution[$text] = ['text'=>$text, 'url'=>$this->tileServer['attribution_url']]; // get attribution from tile server (if applicable)
        $mapAttribution = [];
        $basemaps = array_column($this->config->get('basemaps') ?? [], null, 'file'); // index basemaps from config by filename
        foreach (array_keys($this->getBasemaps()) as $file) {
            if ($text = $basemaps[$file]['attribution_text']) $mapAttribution[$text] = ['text'=>$text, 'url'=>$basemaps[$file]['attribution_url']]; // get attribution from basemaps
        }
        // Merge the attribution lists. Because attribution lists are indexed by text, identical values of text will overwrite each other. Priority goes to fields from the tour header, then tile server, then basemaps, and finally the plugin config.
        foreach (array_values(array_merge($configAttribution, $mapAttribution, $serverAttribution, $tourAttribution)) as $attr) {
            if (!empty($attr['text'])) $attribution[] = ['name'=>$attr['text'], 'url'=>$attr['url']]; // only attribution entries with name/text provided will be included
        }
        return $attribution;
    }

    /**
     * Gets list of "long attribution" items - attributions where html code is provided. Combines items from the plugin config, tour header, and tile server
     * @return array - [string]
     */
    public function getExtraAttribution(): array {
        $attribution = [];
        if ($this->tileServer['name']) $attribution[] = $this->tileServer['attribution'];
        foreach ($this->config->get('attribution_html') ?? [] as $attr) {
            $attribution[] = $attr['text'];
        }
        foreach ($this->header->get('attribution_html') ?? [] as $attr) {
            $attribution[] = $attr['text'];
        }
        return $attribution;
    }

    /**
     * Gets list of views, indexed by the viewId. Contains all the important information about each view.
     * @return array - [viewId => [basemaps, onlyShowViewFeatures, removeTileServer, noTourBasemaps, zoom, center, features]]
     */
    public function getViews(): array {
        $views = [];
        foreach ($this->views as $viewId => $view) { // get settings/options for each view
            // set initial placeholder arrays and view option settings
            $v = [
                'basemaps'=>[],
                'features'=>[],
                'onlyShowViewFeatures'=>$view->get('only_show_view_features') ?? $this->header->get('only_show_view_features') ?? false, 
                'removeTileServer'=>$view->get('remove_tile_server') ?? $this->header->get('remove_tile_server') ?? true,
                'noTourBasemaps'=>$view->get('no_tour_basemaps') ?? false,
            ];
            $bounds = $this->setStartingBounds($view->get('start'));
            if (!empty($bounds)) $v['bounds'] = $bounds;
            if (!empty($view['features'])) { // add all features listed in the view, but only if those features are included in the overall tour features list
                foreach (array_column($view['features'], 'id') as $featureId) {
                    if ($this->features[$featureId]) $v['features'][] = $featureId;
                }
            }
            if (empty($v['features'])) $v['onlyShowViewFeatures'] = false; // ignore this setting if the view doesn't have features (otherwise, if true, it would simply remove all features)
            // basemaps
            foreach (array_column($view->get('basemaps') ?? [], 'file') as $file) { // add all basemaps, but make sure they are in the list (only reason they wouldn't be is if the needed basemap information doesn't exist/is invalid)
                if (!empty($this->getBasemaps()[$file])) $v['basemaps'][] = $file;
            }
            $views[$viewId] = $v;
        }
        return $views;
    }

    /**
     * Gets list of basic datasest icon/path info for displaying features
     * @return array - [id => [legendAltText, iconOptions, pathOptions, pathActiveOptions]]
     */
    public function getDatasets(): array {
        $datasets = [];
        foreach ($this->datasets as $datasetId => $dataset) {
            $datasets[$datasetId] = [
                'legendAltText' => $dataset->get('legendAltText'),
                'iconOptions' => $dataset->get('iconOptions'),
                'pathOptions' => $dataset->get('pathOptions'),
                'pathActiveOptions' => $dataset->get('pathActiveOptions'),
            ];
        }
        return $datasets;
    }

    /**
     * Gets list of features included in the tour
     * @return array - [id => [type, properties (name, id, dataSource, hasPopup) geometry (type, coordinates)]]
     */
    public function getFeatures(): array {
        return $this->features;
    }

    /**
     * Gets list of legend info for all datasets with legend text and at least one included feature
     * @return array - [[dataSource, legendText, iconFile, iconWidth, iconHeight, iconAltText]]
     */
    public function getLegend(): array {
        if (!($this->header->get('legend') ?? true)) return [];
        $legend = [];
        foreach ($this->datasets as $dataset) {
            if (!empty($dataset['legend'])) $legend[] = $dataset['legend'];
        }
        return $legend;
    }

    /**
     * Gets list of feature popups for creating the popup list
     * @return array - [id => [id, name, popup]]
     */
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

    /**
     * Function to call from template to set id of view elements
     */
    public function getViewId($view) {
        return $view->getCacheKey();
    }

    /**
     * Gets list of feature popups for creating view popup buttons
     * @param string $vieweId - the id of the view, provided by this object previously and generated using the getCacheKey method
     * @param string $content - the HTML content of the view - used to check for existing popup buttons to avoid repetition
     * @return array - [[id, name]]
     */
    public function getViewPopups(string $viewId, string $content): array {
        $view = $this->views[$viewId];
        $showList = $view->get('list_popup_buttons') ?? $this->header->get('list_popup_buttons') ?? true;
        if (!$showList) return []; // no need if shortcodes are being provided instead
        if (empty($view) || empty($view->get('features')) || empty($this->getPopups())) return [];
        // find out if any shortcodes are being provided anyhow
        // note: popup buttons look like <button id="sc_btn_randomChars" onClick="openDialog('feature_id-popup', this) class="..." etc.>Feature Name</button>
        $buttons = explode('onClick="openDialog(\'', $content); // find all buttons by searching for the openDialog onclick method (up to the quotation mark beginning the first argument, which is the feature id)
        $popupButtons = [];
        if (count($buttons) > 1) {
            array_shift($buttons);
            foreach ($buttons as $button) {
                // find the id of the element (by separating content such that the id is in the first element of the array and everything after the openDialog onClick method is in the second element)
                $popupButtons[] = explode('-popup\', this)"', $button)[0];
            }
        }
        $viewPopups = [];
        foreach (array_column($view->get('features'), 'id') as $featureId) {
            $popup = $this->getPopups()[$featureId];
            if (!empty($popup) && !in_array($featureId, $popupButtons)) $viewPopups[] = [
                'id' => $popup['id'],
                'name' => $popup['name'],
            ];
        }
        return $viewPopups;
    }

    /**
     * Gets list of general tour options
     * @return array - [maxZoom, minZoom, removeTileServer, tourMaps, wideCol, showMapLocationInUrl, tileServer, stamenTileServer, bounds, maxBounds]
     */
    public function getOptions(): array {
        $options = [
            'maxZoom' => $this->header->get('zoom_max') ?? 16,
            'minZoom' => $this->header->get('zoom_min') ?? 8,
            'removeTileServer' => $this->header->get('remove_tile_server'),
            'tourMaps' => array_column($this->header->get('basemaps') ?? [], 'file'),
            'wideCol' => $this->header->get('wide_column') ?? $this->config->get('wide_column') ?? false,
            'showMapLocationInUrl' => $this->header->get('show_map_location_in_url') ?? $this->config->get('show_map_location_in_url') ?? true,
        ];
        // tile server
        if ($this->tileServer['url']) $options['tileServer'] = $this->tileServer['url'];
        else if ($this->tileServer['type'] === 'stamen') $options['stamenTileServer'] = $this->tileServer['name'];
        // starting bounds for tour
        $bounds = $this->setStartingBounds($this->header->get('start'));
        if (!empty($bounds)) $options['bounds'] = $bounds;
        // max bounds for tour
        $maxBounds = Utils::setBounds($this->header->get('max_bounds') ?? []);
        if ($maxBounds) $options['maxBounds'] = $maxBounds;
        return $options;
    }

    /**
     * Gets the bounds for tour or view start, assuming valid information has been provided
     * @return array - valid bounds or empty array
     */
    protected function setStartingBounds($start): array {
        $bounds = Utils::setBounds($start['bounds'] ?? []); // check if bounds have been provided directly
        if (empty($bounds) && !empty($start['distance']) && $start['distance'] > 0) { // if not (or provided bounds are invalid), starting location or coordinates could be used, but only if distance has been provided
            if ($start['location']) { // check start location - is it a valid point in the list of all tour features (includes hidden features)
                foreach ($this->allFeatures as $featureId => $feature) {
                    $feature = $feature['geojson']['geometry'];
                    if ($featureId === $start['location'] && $feature['type'] === 'Point' && Utils::setValidCoordinates($feature['coordinates'], 'Point')) {
                        $long = $feature['coordinates'][0];
                        $lat = $feature['coordinates'][1];
                    }
                }
            }
            if (!is_numeric($lat)) { // only need to check lat or long, no need to check both
                // either a location was provided that didn't work, or no location was provided - check if lat and long were provided directly
                $long = $start['long'];
                $lat = $start['lat'];
            }
            if (is_numeric($long) && is_numeric($lat)) { // lat and long provided in some form - use distance to calculate the actual bounds
                $distance = $start['distance'];
                $bounds = ['north'=>Utils::addToLat($lat,$distance), 'south'=>Utils::addToLat($lat, -$distance), 'east'=>Utils::addToLong($long, $distance), 'west'=>Utils::addToLong($long, -$distance)];
                $bounds = Utils::setBounds($bounds); // ensures that the bounds are valid
            }
        }
        return $bounds ?? []; // to prevent throwing error of returning null if Utils::setBounds returns null (due to invalid bounds)
    }

    /**
     * @param string $featureId - the id (created by the plugin) of the feature
     * @param string $buttonId - the id that the view popup button should have
     * @param string $featureName - the name of the feature to include in the button text
     * @return string - html code for a popup button, referenced by shortcode
     */
    public static function getViewPopup(string $featureId, string $buttonId, string $featureName): string {
        return '<button id="'.$buttonId.'" onClick="openDialog(\''.$featureId.'-popup\', this)" class="btn view-popup-btn">View '.$featureName.' popup</button>';
    }

    /**
     * Checks if a feature has a popup - used by the shortcode
     * @param Feature $feature - the feature to check
     * @param array $tourFeatures - list of tour features to ensure that feature is in the list and that popup options have not been overwritten
     * @param bool - whether or not the feature has a popup
     */
    public static function hasPopup(Feature $feature, array $tourFeatures): bool {
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