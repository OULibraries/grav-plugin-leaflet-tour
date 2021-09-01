<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledJsonFile;
use RocketTheme\Toolbox\File\MarkdownFile;

/**
 * The Dataset class stores and handles all the information for one dataset.
 * It combines data from the json file and the dataset page config when created, and ensures that this data can be provided in a useful format.
 * It also handles creating and updating the json file and dataset page when the dataset page config or plugin config is modified.
 */
class Dataset {

    protected static $datasets;

    // dataset properties
    protected $name; // string
    protected $jsonFilename; // string, also serves as dataset id
    protected $datasetFileRoute; // string
    protected $featureCounter; // number, only needed when adding new features in a dataset update
    // protected $crs;

    // features
    protected $propertyList; // [string]
    protected $nameProperty; // string
    protected $featureType; // string
    protected $features; // [string (id) => Feature]
    
    // legend and icon options
    protected $legendText; // string
    protected $legendAltText; // string
    protected $iconAltText; // string
    protected $iconOptions; // array, options specified in readme
    protected $pathOptions; // array, options specified in readme
    protected $pathActiveOptions; // array, options specified in readme
    
    // other
    // protected $autoPopupProperties; // list of all properties to automatically add to popups

    protected $config; // Data
    
    /**
     * @param string $jsonFilename - the name of the json file (not the route to it), also serves as the dataset identifier
     * @param Data $config
     */
    function __construct(string $jsonFilename, Data $config=null) {
        $this->jsonFilename = $jsonFilename;
        if (empty($config)) $config = new Data(Grav::instance()['config']->get('plugins.leaflet-tour'));
        $this->config = $config;

        // read json file
        $jsonFile = self::getJsonFile($jsonFilename);
        if (!$jsonFile->exists()) return;
        try {
            $jsonData = new Data($jsonFile->content());
    
            // add fields from json file
            $this->name = $jsonData->get('name');
            $this->datasetFileRoute = $jsonData->get('datasetFileRoute');
            $this->featureCounter = $jsonData->get('featureCounter');
            // $this->crs = $jsonData->get('crs');
            $this->nameProperty = $jsonData->get('nameProperty');
            $this->properties = $jsonData->get('propertyList') ?? [];
            $this->featureType = $jsonData->get('featureType');
            $this->features = Feature::buildFeatureList((array)$jsonData->get('features') ?? [], $this->nameProperty, $this->featureType);
    
            // check dataset file to add legend, icon, and popup options
            if (!empty($this->datasetFileRoute)) $this->addDatasetFileInfo();
        } catch (\Throwable $t) {
            return;
        }
    }

    // a short wrapper so I don't have to type it out every time
    public static function getJsonFile(string $jsonFilename): CompiledJsonFile {
        return CompiledJsonFile::instance(Grav::instance()['locator']->findResource('user-data://').'/leaflet-tour/datasets/'.$jsonFilename);
    }

    /**
     * Finds the dataset.md file associated with the dataset. Adds general information from the header and adds information specific to each of the features.
     */
    protected function addDatasetFileInfo(): void {
        $file = MarkdownFile::instance($this->datasetFileRoute);
        if (!$file->exists()) return;
        $header = new Data((array)($file->header()));
        $this->addDatasetInfo($header);
        // add dataset header feature information - popup content, custom name, possibly other info in the future
        if (!empty($header->get('features'))) {
            foreach ($header->get('features') as $headerFeature) {
                $id = $headerFeature['id'];
                if ($this->features[$id]) {
                    $this->features[$id]->addDatasetInfo($headerFeature);
                }
            }
        }
    }
    
    /**
     * Adds info stored only in the dataset page header and not in the json file, such as legend/icon options and popup content.
     */
    protected function addDatasetInfo($header): void {
        $this->legendText = $header->get('legend_text');
        $this->legendAltText = $header->get('legend_alt');
        $this->iconAltText = $header->get('icon_alt');
        $this->iconOptions = Utils::array_filter($header->get('icon') ?? $this->iconOptions ?? []);
        $this->pathOptions = Utils::array_filter($header->get('svg') ?? $this->pathOptions ?? []);
        $this->pathActiveOptions = Utils::array_filter($header->get('svg_active') ?? $this->pathActiveOptions ?? []);
    }

    /**
     * Called whenever the dataset config is saved. Updates properties in the object and in the json file.
     * @param Header $header - the header for the dataset page
     * @return Header - the header with any needed modifications
     */
    public function updateDataset($header) {
        // update basic properties
        $name = $header->get('title');
        if (empty($name)) $header->set('title', $this->name); // empty titles will revert to previous title
        else if ($name !== $this->name) $this->name = $name;
        $nameProperty = $header->get('name_prop');
        // Option: Make sure property list is updated first if allowing users to add/remove properties
        if ($nameProperty !== $this->nameProperty && in_array($nameProperty, $this->properties)) {
            // update nameProperty for the dataset and for each of the features (only matters for features without custom names)
            $this->nameProperty = $nameProperty;
            foreach ($this->features as $feature) {
                $feature->updateName($nameProperty);
            }
        }
        else $header->set('name_prop', $this->nameProperty); // just in case
        // reconcile feature list
        $headerFeatures = $header->get('features');
        if (!empty($headerFeatures)) { // loop through features from the header to find any features in the Dataset that need to be updated
            foreach($headerFeatures as $feature) {
                if ($this->features[$feature['id']]) {
                    // update the feature
                    $this->features[$feature['id']]->update($feature);
                }
                // Option: potentially allow adding/removing features
            }
        }
        $header->set('features', Feature::buildYamlList($this->features)); // build feature list from dataset - that way any removals/additions to the feature list will be reverted
        // Option: allow add/remove features properties
        // update legend, icon properties
        $this->addDatasetInfo($header);
        $this->saveDataset();
        return $header;
    }

    /**
     * Saves the dataset.md file and the json file
     */
    public function saveDataset(): void {
        $this->saveDatasetPage($this->datasetFileRoute);
        $jsonFile = self::getJsonFile($this->jsonFilename);
        $jsonFile->content($this->asJson());
        $jsonFile->save();
    }

    /**
     * Saves the dataset.md file
     */
    public function saveDatasetPage(): void {
        $mdFile = MarkdownFile::instance($this->datasetFileRoute);
        $mdFile->header($this->asYaml());
        $mdFile->save();
    }

    // get name - for list of datasets for admin panel dropdowns
    public function getName(): string {
        return $this->name;
    }

    public function getNameProperty() {
        return $this->nameProperty;
    }

    public function getFeatureType() {
        return $this->featureType;
    }

    public function getFeatures(): array {
        return $this->features;
    }

    public function getDatasetRoute(): string {
        return $this->datasetFileRoute;
    }

    public function getFeatureCounter(): int { return $this->featureCounter; }
    public function getJsonFilename(): string { return $this->jsonFilename; }

    // for config
    public function getProperties(): array {
        return array_combine($this->properties, $this->properties);
    }

    // get yaml - for saving to dataset page header
    public function asYaml(): array {
        return [
            'routable'=>0,
            'visible'=>0,
            'dataset_file'=>$this->jsonFilename,
            'title'=>$this->name,
            'name_prop'=>$this->nameProperty,
            'features'=>Feature::buildYamlList($this->features),
            'legend_text'=>$this->legendText,
            'legend_alt'=>$this->legendAltText,
            'icon_alt'=>$this->iconAltText,
            'icon'=>$this->iconOptions ?? [],
            'svg'=>$this->pathOptions ?? [],
            'svg_active'=>$this->pathActiveOptions ?? [],
        ];
    }

    // get json - for saving to json file
    public function asJson(): array {
        return [
            'type'=>'FeatureCollection',
            'name'=>$this->name,
            'crs'=>$this->crs,
            'featureCounter'=>$this->featureCounter,
            'datasetFileRoute'=>$this->datasetFileRoute,
            'featureType'=>$this->featureType,
            'nameProperty'=>$this->nameProperty,
            'propertyList'=>$this->properties,
            'features'=>Feature::buildJsonList($this->features),
        ];
    }

    /**
     * A few defaults for when the page is initially created. Normally these would be saved when the page was first saved, since they would be defaults in the blueprint file. Since the page is saved in the backend, however, this doesn't happen, so this function corrects for that.
     */
    public function setDefaults(): void {
        $this->pathOptions ??= ['stroke'=>true, 'color'=>'#3388ff', 'weight'=>3, 'opacity'=>1, 'fill'=>true, 'fillOpacity'=>0.2];
        $this->pathActiveOptions ??= ['weight'=>5, 'fillOpacity' => 0.4];
    }

    /**
     * Takes dataset info from a tour configuration and merges it with this dataset to return the needed information for the tour. Merges icon and path options, legend options, and features.
     * @param Data $tour - dataset info (yaml) from tour config
     * @param array $tourFeatures - features list (yaml) from tour config
     * @return Data - object with all the data needed for including the datasest in the tour: [id, featureType, iconOptions[], pathOptions[], pathActiveOptions[], features (id => [name, geojson, popupContent]), hiddenFeatures (id => [geojson]), legend[], legendAltText]. Details for what is included in icon and path options can be found in the documentation or in the merge functions used to reconcile the options. The legend will have [dataSource, legendText]. For points, it will also have [iconFile, iconWidth, iconHeight, iconAaltText]. For lines/polygons it will also have featureType and some amount of path options.
     */
    public function mergeTourData(Data $tour, array $tourFeatures): Data {
        $data = [
            'id'=>$this->jsonFilename,
            'featureType'=>$this->featureType,
        ];
        if ($this->featureType === 'Point') {
            // use icon options for Point datasets
            $data['iconOptions'] = $this->mergeIconOptions($tour->get('icon') ?? []);
        } else {
            // use path options for LineString/Polygon datasets
            $data = array_merge($data, $this->mergePathOptions($tour->get('svg') ?? [], $tour->get('svg_active') ?? []));
        }
        $data = array_merge($data, $this->mergeFeatures($tour->get('show_all') ?? true, $tourFeatures ?? []));
        // keeping legend merge here since it would be a bit complicated to outsource
        // only add legend if there is legend text for the dataset and the dataset actually has features
        $legendText = $tour->get('legend_text') ?? $this->legendText;
        if (!empty($legendText) && !empty($data['features'])) {
            $data['legendAltText'] = $tour->get('legend_alt') ?? $tour->get('legend_text') ?? $this->legendAltText ?? $this->legendText;
            $legend = [
                'dataSource' => $this->jsonFilename,
                'legendText' => $legendText,
            ];
            // Points - get needed info to display icon in legend
            if ($this->featureType === 'Point') {
                $iconOptions = $data['iconOptions'];
                $legend['iconFile'] = $iconOptions['iconUrl'];
                $legend['iconWidth'] = $iconOptions['iconSize'][0];
                $legend['iconHeight'] = $iconOptions['iconSize'][1];
                $iconAltText = $tour->get('icon_alt') ?? $this->iconAltText;
                if (!empty($iconAltText)) $legend['iconAltText'] = $iconAltText;
            } else if ($this->featureType === 'LineString' || $this->featureType === 'MultiLineString') {
                // lines - all we really need is the color to display a square of that color in the legend
                $legend['featureType'] = 'line';
                $legend['color'] = $data['pathOptions']['color'] ?? '#3388ff';
            } else {
                // polygons - only include stroke options and fill options if they are applicable - existence of the options will be used to determine if they should be used when drawing svg in legend
                $pathOptions = $data['pathOptions'];
                $legend['featureType'] = 'polygon';
                if ($pathOptions['stroke'] ?? true) {
                    $legend['color'] = $pathOptions['color'] ?? '#3388ff';
                    $legend['weight'] = $pathOptions['weight'] ?? 3;
                    $legend['opacity'] = $pathOptions['opacity'] ?? 1;
                }
                if ($pathOptions['fill'] ?? true) {
                    $legend['fillColor'] = $pathOptions['fillColor'] ?? $pathOptions['color'] ?? '#3388ff';
                    $legend['fillOpacity'] = $pathOptions['fillOpacity'] ?? 0.2;
                }
            }
            $data['legend'] = $legend;
        }
        return new Data($data);
    }
    
    /**
     * Reconciles dataset icon options with tour overrides and default values.
     * @param array $tourIcon - icon options from the dataset information in the tour header
     * @return array - merged options: [iconUrl, iconSize, className, tooltipAnchor, iconAnchor, iconRetinaUrl, shadowUrl, shadowSize, shadowAnchor]
     */
    protected function mergeIconOptions(array $tourIcon): array {
        // if (empty($tourIcon) && empty($this->iconOptions)) $iconOptions = Utils::DEFAULT_MARKER_OPTIONS; // shouldn't happen - tour icon options should always at least have true/false for use_defaults
        // Set basic options: If use_defaults is enabled, ignore the icon options from this dataset; otherwise merge the options from the dataset and the tour header
        if ($tourIcon['use_defaults']) $options = $tourIcon;
        else $options = array_merge($this->iconOptions ?? [], $tourIcon);
        // Set appropriate defaults to reference
        if (!empty($options['file'])) $defaults = Utils::MARKER_FALLBACKS;
        else $defaults = Utils::DEFAULT_MARKER_OPTIONS;
        // Set up initial icon options
        $iconOptions = [
            'iconUrl' => !empty($options['file']) ? Utils::IMAGE_ROUTE.'markers/'.$options['file'] : $defaults['iconUrl'],
            'iconSize' => [$options['width'] ?? $defaults['iconSize'][0], $options['height'] ?? $defaults['iconSize'][1]],
            'className' => 'leaflet-marker '.($options['class'] ?? ''),
            'tooltipAnchor' => [$options['tooltip_anchor_x'] ?? $defaults['tooltipAnchor'][0], $options['tooltip_anchor_y'] ?? $defaults['tooltipAnchor'][1]],
        ];
        // Add anchor options if either x and y are both set or the default marker icon is being used (as those defaults set both x and y)
        $anchorX = $options['anchor_x'];
        $anchorY = $options['anchor_y'];
        if (is_numeric($anchorX) && is_numeric($anchorY) || empty($options['file'])) $iconOptions['iconAnchor'] = [$anchorX ?? $defaults['iconAnchor'][0], $options['anchor_y'] ?? $defaults['iconAnchor'][1]];
        // Add url for retina file if applicable
        $retinaUrl = $options['retina'] ? Utils::IMAGE_ROUTE.'markers/'.$options['retina'] : $defaults['iconRetinaUrl'];
        if (!empty($retinaUrl)) $iconOptions['iconRetinaUrl'] = $retinaUrl;
        // Add shadow url and additional options if applicable
        if (!empty($options['shadow']) || empty($options['file'])) {
            $iconOptions['shadowUrl'] = $options['shadow'] ? Utils::IMAGE_ROUTE.'markerShadows/'.$options['shadow'] : $defaults['shadowUrl'];
            $iconOptions['shadowSize'] = [$options['shadow_width'] ?? $defaults['shadowSize'][0] ?? $iconOptions['iconSize'][0], $options['shadow_height'] ?? $defaults['shadowSize'][1]] ?? $iconOptions['iconSize'][1]; // use icon size as fallback if shadow size is not provided in either the options or the defaults
            if (is_numeric($options['shadow_anchor_x']) && is_numeric($options['shadow_anchor_y'])) $iconOptions['shadowAnchor'] = [$options['shadow_anchor_x'], $options['shadow_anchor_y']];
        }
        return $iconOptions;
    }

    /**
     * Reconciles dataset path options with tour overrides. Default values are unnecessary, as Leaflet will fill in the blanks.
     * @param array $tourPath - tour svg options from the dataset information in the tour header
     * @param array $tourActivePath - tour svg_active options from the dataset information in the tour header
     * @return array - array with pathOptions and pathActiveOptions that can be merged with the main dataset array returned by mergeTourData, both sets include (as applicable): [stroke, weight, color, opacity, fill, fillColor, fillOpacity]
     */
    protected function mergePathOptions(array $tourPath, array $tourActivePath): array {
        // Provide list of path keys so we can loop through them instead of checking each manually in the code
        $pathKeys = ['stroke', 'weight', 'color', 'opacity', 'fill', 'fillColor', 'fillOpacity']; // all - polygon
        if ($this->featureType === 'LineString' || $this->featureType === 'MultiLineString') $pathKeys = ['stroke', 'weight', 'color', 'opacity']; // remove fill options for lines
        $pathOptions = [];
        foreach ($pathKeys as $key) {
            $option = ($tourPath)[$key] ?? ($this->pathOptions ?? [])[$key];
            if ($option) $pathOptions[$key] = $option;
        }
        $pathActiveOptions = [];
        foreach ($pathKeys as $key) {
            $option = ($tourActivePath)[$key] ?? ($this->pathActiveOptions ?? [])[$key];
            if ($option) $pathActiveOptions[$key] = $option;
        }
        return ['pathOptions'=>$pathOptions, 'pathActiveOptions'=>$pathActiveOptions];
    }

    /**
     * Reconciles dataset features with tour overrides.
     * @param bool $showAll - dataset show_all value from tour header
     * @param array $tourFeatures - features list from tour header
     * @return array - array with features and hiddenFeatures that can be merged with the main dataset array returned by mergeTourData (see that functions comment for what these arrays include)
     */
    protected function mergeFeatures(bool $showAll, array $tourFeatures): array {
        $tourFeatures = array_column($tourFeatures, null, 'id'); // index tour features list by id for ease of reference
        $features = [];
        $hiddenFeatures = [];
        // Loop through all features and either add to features list or hiddenFeatures list. If adding to features list, also check on popup content.
        foreach ($this->features as $featureId => $feature) {
            // add to features list if show all is enabled or if feature is included in the tour features list
            if ($showAll || $tourFeatures[$featureId]) {
                // initial feature content
                $featArray = [
                    'name' => $feature->getName(),
                    'geojson' => $feature->asGeoJson(),
                    'popupContent' => $feature->getPopup(),
                ];
                // check for existence in tour features list, modify popup content accordingly
                $tourFeature = $tourFeatures[$featureId];
                if ($tourFeature) {
                    // overwrite as needed
                    if (!empty($tourFeature['popup_content'])) {
                        $featArray['popupContent']  = $tourFeature['popup_content'];
                    } else if ($tourFeature['remove_popup']) {
                        unset($featArray['popupContent']);
                    }
                }
                $features[$featureId] = $featArray; // add to features list
            }
            else $hiddenFeatures[$featureId] = ['geojson' => $feature->asGeoJson()]; // add to hidden features list
        }
        return ['features'=>$features, 'hiddenFeatures'=>$hiddenFeatures];
    }

    // utilities

// TODO: Use utils function for most of this
    /**
     * Used to build a new dataset from a json file, including validating the json and setting sensible defaults
     */
    public static function createNewDataset(array $jsonArray, string $jsonFilename): void {
        $jsonData = new Data($jsonArray); // $jsonData is used to get values, because the 'get' function is useful and there may have been some problems using $jsonArray. $jsonArray is necessary for setting values, because for some reason $jsonData was giving problems and $jsonArray was fine.
        $id = str_replace('.json', '', $jsonFilename); // json filename is primarily used as the id, but this is used to generate default dataset name and feature ids
        // some basic json validation
        if (empty($jsonData) || empty($jsonData->get('features.0.geometry'))) return;
        // set dataset name
        if (empty($jsonData->get('name'))) $jsonArray['name'] = $id;
        // set feature type
        if ($jsonData->get('features.0.geometry.type')) $featureType = Utils::setValidType($jsonData->get('features.0.geometry.type')); // first feature might be invalid
        // set feature ids and get properties list
        $count = 0;
        $features = [];
        $propList = [];
        foreach ($jsonData->get('features') as $feature) {
            if (!$featureType && $feature['geometry'] && $feature['geometry']['type']) $featureType = Utils::setValidType($feature['geometry']['type']); // just in case
            if (isset($featureType) && $feature=Feature::setValidFeature($feature, $featureType)) {
                $feature['id'] = $id.'_'.$count;
                $features[] = $feature;
                $count++;
                if (is_array($feature['properties'])) {
                    $propList = array_merge($propList, $feature['properties']); // make sure to add any properties that previous features didn't have - ensures that all properties are listed, not just the properties of the first feature
                }
            }
        }
        $jsonArray['featureType'] = $featureType ?? '';
        $jsonArray['featureCounter'] = $count; // if features are added in future updates, will be used to generate ids for them
        $jsonArray['features'] = $features;
        // set default name property
        $nameProperty = '';
        $propList = array_keys($propList); // only need the keys, which are the property names - values are set per feature
        $jsonArray['propertyList'] = $propList;
        // search for sensible default for name property - first priority is property name, second is property beginning or ending with name, last resort is first property
        foreach ($propList as $prop) {
            if (strcasecmp($prop, 'name') == 0) $nameProperty = $prop;
            else if (empty($nameProperty) && preg_match('/^(.*name|name.*)$/i', $prop)) $nameProperty = $prop;
        }
        if (empty($nameProperty) && !empty($propList)) $nameProperty = $propList[0]; // possible that nameProperty will still be blank if no features have properties
        $jsonArray['nameProperty'] = $nameProperty;
        // set dataset file route
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $jsonArray['name']), '-')); // trying to save folders with capital letters or special symbols may cause issues
        $jsonArray['datasetFileRoute'] = Grav::instance()['locator']->findResource('page://').'/datasets/'.$slug.'/dataset.md';
        // save the file
        $jsonFile = self::getJsonFile($jsonFilename);
        $jsonFile->content($jsonArray);
        $jsonFile->save();
        // create the dataset (needs to happen after the json file is saved, since the constructor requires the file)
        $dataset = new Dataset($jsonFilename, new Data(Grav::instance()['config']->get('plugins.leaflet-tour')));
        $dataset->setDefaults(); // only ever called during dataset creation (useful because the page is created on the backend, rather by saving a new page from the admin panel where the defaults in the blueprints would be used)
        $dataset->saveDatasetPage();
        self::$datasets[$jsonFilename] = $dataset;
    }

    /**
     * @return array - list of datasets for admin panel dropdowns in the form of [id => name]
     */
    public static function getDatasetList(): array {
        $datasetList = [];
        foreach(self::getDatasets() as $id=>$dataset) {
            $datasetList[$id] = $dataset->getName();
        }
        return $datasetList;
    }

    /**
     * @return array - list of Dataset objects in the form of [id => Dataset]
     */
    public static function getDatasets(): array {
        if (null === self::$datasets) self::$datasets = self::buildDatasets();
        return self::$datasets;
    }

    /**
     * Just in case, rebuilds the dataset list
     */
    public static function resetDatasets(): void {
        self::$datasets = self::buildDatasets();
    }

    /**
     * Creates list of Dataset objects by going through all json files in the user/data/leaflet-tour/datasets/ folder.
     * @return array - list of Dataset objects in the form of [id => Dataset]
     */
    protected static function buildDatasets(): array {
        $datasets = [];
        $route = Grav::instance()['locator']->findResource('user-data://')."/leaflet-tour/datasets/";
        $files = glob($route."*.json");
        $config = new Data(Grav::instance()['config']->get('plugins.leaflet-tour'));
        foreach ($files as $file) {
            $jsonFilename = str_replace($route, '', $file);
            $dataset = new Dataset($jsonFilename, $config);
            if (!empty($dataset->getName())) $datasets[$jsonFilename] = $dataset;
        }
        return $datasets;
    }
}

?>