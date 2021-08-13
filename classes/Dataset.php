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
    protected $jsonFilename; // string; also serves as dataset id
    protected $datasetFileRoute; // string
    protected $crs;

    // features
    protected $propertyList; // [string]
    protected $nameProperty; // string
    protected $featureType; // string
    protected $features; // [id => Feature]
    
    // legend and icon options
    protected $legendText;
    protected $legendAltText;
    protected $iconAltText;
    protected $iconOptions;
    protected $pathOptions;
    protected $pathActiveOptions;
    
    // other
    protected $autoPopupProperties; // list of all properties to automatically add to popups

    protected $config;
    
    /**
     * @param string $jsonFilename - the name of the json file (not the route to it), also serves as the dataset identifier
     * @param Data $config
     */
    function __construct($jsonFilename, $config=null) {
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
            $this->crs = $jsonData->get('crs');
            $this->nameProperty = $jsonData->get('nameProperty');
            $this->properties = array_keys((array)($jsonData->get('features.0.properties')));
            $this->featureType = $jsonData->get('featureType');
            $this->features = Feature::buildFeatureList((array)$jsonData->get('features'), $this->nameProperty, $this->featureType);
    
            // check dataset file to add legend, icon, and popup options
            if (!empty($this->datasetFileRoute)) $this->addDatasetFileInfo();
        } catch (\Throwable $t) {
            return;
        }
    }

    public static function getJsonFile($jsonFilename): CompiledJsonFile {
        return CompiledJsonFile::instance(Grav::instance()['locator']->findResource('user-data://').'/leaflet-tour/datasets/'.$jsonFilename);
    }

    protected function addDatasetFileInfo(): void {
        $file = MarkdownFile::instance($this->datasetFileRoute);
        if (!$file->exists()) return;
        $header = new Data((array)($file->header()));
        $this->addDatasetInfo($header);
        // add info stored only in dataset page header
        if (!empty($header->get('features'))) {
            foreach ($header->get('features') as $headerFeature) {
                $id = $headerFeature['id'];
                if ($this->features[$id]) {
                    $this->features[$id]->setDatasetFields($headerFeature);
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
        $this->iconOptions = $header->get('icon') ?? $this->iconOptions ?? [];
        $this->pathOptions = $header->get('svg') ?? $this->pathOptions ?? [];
        $this->pathActiveOptions = $header->get('svg_active') ?? $this->pathActiveOptions ?? [];
    }

    /**
     * Called whenever the dataset config is saved. Updates properties in the object and in the json file.
     * @param Header $header - the header for the dataset page
     * @param string $datasetFileRoute - full route to the dataset page
     * @param array $originalFeatures - list of previous features - used to check if changes need to be made and to deal with any potential issues, like deleted features
     * @return array - list of features from header with any needed changes
     */
    public function updateDataset($header, $datasetFileRoute) {
        // update basic properties
        $name = $header->get('title');
        if (empty($name)) $header->set('title', $this->name);
        else if ($name !== $this->name) $this->name = $name;
        $nameProperty = $header->get('name_prop');
        // Option: Make sure property list is updated first if allowing users to add/remove properties
        if ($nameProperty !== $this->nameProperty && in_array($nameProperty, $this->properties)) $this->nameProperty = $nameProperty;
        else $header->set('name_prop', $this->nameProperty);
        if ($datasetFileRoute !== $this->datasetFileRoute) $this->datasetFileRoute = $datasetFileRoute;
        // reconcile feature list
        $headerFeatures = $header->get('features');
        if (!empty($headerFeatures)) {
            foreach($headerFeatures as $feature) {
                if ($this->features[$feature['id']]) {
                    // update
                    $this->features[$feature['id']]->update($feature);
                }
                // Option: potentially allow adding/removing features
            }
        }
        $header->set('features', Feature::buildYamlList($this->features));
        // Option: allow add/remove features properties
        // update legend, icon properties
        $this->addDatasetInfo($header);
        $this->saveDataset();
        return $header;
    }

    public function saveDataset(string $datasetFileRoute = null): void {
        $this->saveDatasetPage($datasetFileRoute ?? $this->datasetFileRoute);
        $jsonFile = self::getJsonFile($this->jsonFilename);
        $jsonFile->content($this->asJson());
        $jsonFile->save();
    }

    public function saveDatasetPage(string $datasetFileRoute = null): void {
        $mdFile = MarkdownFile::instance($datasetFileRoute ?? $this->datasetFileRoute);
        $mdFile->header($this->asYaml());
        $mdFile->save();
    }

    // get name - for list of datasets
    public function getName() {
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

    // for config
    public function getProperties(): array {
        return array_combine($this->properties, $this->properties);
    }

    // get yaml - for saving to dataset page (including creating dataset page)
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
            'icon'=>$this->iconOptions,
            'svg'=>$this->pathOptions,
            'svg_active'=>$this->pathActiveOptions,
        ];
    }

    // get json - for saving to json file (including creating json file)
    public function asJson(): array {
        return [
            'type'=>'FeatureCollection',
            'name'=>$this->name,
            'crs'=>$this->crs,
            'datasetFileRoute'=>$this->datasetFileRoute,
            'featureType'=>$this->featureType,
            'nameProperty'=>$this->nameProperty,
            'features'=>Feature::buildJsonList($this->features),
        ];
    }

    public function setDefaults(): void {
        $this->pathActiveOptions ??= ['weight'=>5, 'fillOpacity' => 0.4];
    }

    /**
     * @param Data $dataset - dataset info (yaml) from tour config
     * @param array $features - features list (yaml) from tour config
     * @return Data - [id, featureType, showAll, features (id => [name, geojson, popupContent], hiddenFeatures (id => [geojson]) legend, iconAltText, iconOptions, pathOptions, pathActiveOptions]
     */
    public function mergeTourData(Data $dataset, array $features): Data {
        $data = [
            'id'=>$this->jsonFilename,
            'featureType'=>$this->featureType,
        ];
        // from tour dataset - show all, legend and icon options
        // icon options
        if (empty($dataset->get('icon')) && empty($this->iconOptions)) $iconOptions = Utils::DEFAULT_MARKER_OPTIONS;
        else {
            // merge options from dataset and tour header
            if ($dataset->get('icon.use_defaults')) $options = $dataset->get('icon');
            else $options = array_merge($this->iconOptions ?? [], $dataset->get('icon') ?? []);
            // determine appropriate defaults to reference
            if (!empty($options['file'])) $defaults = Utils::MARKER_FALLBACKS;
            else $defaults = Utils::DEFAULT_MARKER_OPTIONS;
            // set up icon options
            $iconOptions = [
                'iconUrl' => !empty($options['file']) ? Utils::IMAGE_ROUTE.'markers/'.$options['file'] : $defaults['iconUrl'],
                'iconSize' => [$options['width'] ?? $defaults['iconSize'][0], $options['height'] ?? $defaults['iconSize'][1]],
                'className' => 'leaflet-marker '.($options['class'] ?? ''),
                'tooltipAnchor' => [$options['tooltip_anchor_x'] ?? $defaults['tooltipAnchor'][0], $options['tooltip_anchor_y'] ?? $defaults['tooltipAnchor'][1]],
            ];
            // anchor - either both x and y must be set, or the default file must be used, since the anchor x and y are both set for the default file's options
            if (is_numeric($options['anchor_x']) && is_numeric($options['anchor_y']) || empty($options['file'])) $iconOptions['iconAnchor'] = [$options['anchor_x'] ?? $defaults['iconAnchor'][0], $options['anchor_y'] ?? $defaults['iconAnchor'][1]];
            $retinaUrl = $options['retina'] ? Utils::IMAGE_ROUTE.'markers/'.$options['retina'] : $defaults['iconRetinaUrl'];
            if (!empty($retinaUrl)) $iconOptions['iconRetinaUrl'] = $retinaUrl;
            if (!empty($options['shadow']) || empty($options['file'])) {
                $iconOptions['shadowUrl'] = $options['shadow'] ? Utils::IMAGE_ROUTE.'markerShadows/'.$options['shadow'] : $defaults['shadowUrl'];
                $iconOptions['shadowSize'] = [$options['shadow_width'] ?? $defaults['shadowSize'][0] ?? $iconOptions['iconSize'][0], $options['shadow_height'] ?? $defaults['shadowSize'][1]] ?? $iconOptions['iconSize'][1];
                if (is_numeric($options['shadow_anchor_x']) && is_numeric($options['shadow_anchor_y'])) $iconOptions['shadowAnchor'] = [$options['shadow_anchor_x'], $options['shadow_anchor_y']];
            }
        }
        $data['iconOptions'] = $iconOptions;
        // path options
        $pathKeys = ['stroke', 'weight', 'color', 'opacity', 'fill', 'fillColor', 'fillOpacity'];
        $pathOptions = [];
        foreach ($pathKeys as $key) {
            $option = ($dataset->get('svg') ?? [])[$key] ?? ($this->pathOptions ?? [])[$key];
            if ($option) $pathOptions[$key] = $option;
        }
        $data['pathOptions'] = $pathOptions;
        $pathActiveOptions = [];
        foreach ($pathKeys as $key) {
            $option = ($dataset->get('svg_active') ?? [])[$key] ?? ($this->pathActiveOptions ?? [])[$key];
            if ($option) $pathActiveOptions[$key] = $option;
        }
        $data['pathActiveOptions'] = $pathActiveOptions;
        // legend
        $legendText = $dataset->get('legend_text') ?? $this->legendText;
        if (!empty($legendText)) {
            $data['legendAltText'] = $dataset->get('legend_alt') ?? $dataset->get('legend_text') ?? $this->legendAltText ?? $this->legendText;
            $legend = [
                'dataSource' => $this->jsonFilename,
                'legendText' => $legendText,
                'iconFile' => $iconOptions['iconUrl'],
                'iconWidth' => $iconOptions['iconSize'][0],
                'iconHeight' => $iconOptions['iconSize'][1],
            ];
            $iconAltText = $dataset->get('icon_alt') ?? $this->iconAltText;
            if (!empty($iconAltText)) $legend['iconAltText'] = $iconAltText;
            $data['legend'] = $legend;
        }
        // from tour features - id, remove popup, popup content
        $tourFeatures = array_column($features, null, 'id');
        $features = [];
        $hiddenFeatures = [];
        foreach ($this->features as $featureId => $feature) {
            // check show all
            if ($dataset->get('show_all') || $tourFeatures[$featureId]) {
                $featArray = [
                    'name' => $feature->getName(),
                    'geojson' => $feature->asGeoJson(),
                    'popupContent' => $feature->getPopup(),
                ];
                $tourFeature = $tourFeatures[$featureId];
                if ($tourFeature) {
                    // overwrite as needed
                    if (!empty($tourFeature['popup_content'])) {
                        $featArray['popupContent']  = $tourFeature['popup_content'];
                    } else if ($tourFeature['remove_popup']) {
                        unset($featArray['popupContent']);
                    }
                }
                $features[$featureId] = $featArray;
            }
            else {
                $hiddenFeatures[$featureId] = ['geojson' => $feature->asGeoJson()];
            }
        }
        $data['features'] = $features;
        $data['hiddenFeatures'] = $hiddenFeatures;
        if (!empty($data['legend']) && empty($data['features'])) unset($data['legend']);
        return new Data($data);
    }

    // utilities

    /**
     * Used to build a new dataset from a json file, including validating the json and setting sensible defaults
     */
    public static function createNewDataset(array $jsonArray, string $jsonFilename): void {
        $jsonData = new Data($jsonArray);
        $id = str_replace('.json', '', $jsonFilename);
        // some basic json validation
        if (empty($jsonData) || empty($jsonData->get('features.0.properties')) || empty($jsonData->get('features.0.geometry'))) return;
        // set dataset name
        if (empty($jsonData->get('name'))) $jsonArray['name'] = $id;
        // set default name property
        $nameProperty = '';
        $propList = array_keys($jsonData->get('features.0.properties'));
        foreach ($propList as $prop) {
            if (strcasecmp($prop, 'name') == 0) $nameProperty = $prop;
            else if (empty($nameProperty) && preg_match('/^(.*name|name.*)$/i', $prop)) $nameProperty = $prop;
        }
        if (empty($nameProperty)) $nameProperty = $propList[0];
        $jsonArray['nameProperty'] = $nameProperty;
        // set dataset file route
        $jsonArray['datasetFileRoute'] = Grav::instance()['locator']->findResource('page://').'/datasets/'.$jsonArray['name'].'/dataset.md';
        // set feature type
        $featureType = Utils::setValidType($jsonData->get('features.0.geometry.type'));
        $jsonArray['featureType'] = $featureType;
        // set feature ids
        $count = 0;
        $features = [];
        foreach ($jsonData->get('features') as $feature) {
            if (is_array($feature) && $feature['geometry'] && Utils::areValidCoordinates($feature['geometry']['coordinates'], $featureType)) {
                $feature['id'] = $id.'_'.$count;
                $features[] = $feature;
                $count++;
            }
        }
        $jsonArray['features'] = $features;
        // save the file
        $jsonFile = self::getJsonFile($jsonFilename);
        $jsonFile->content($jsonArray);
        $jsonFile->save();
        // create the dataset
        $dataset = new Dataset($jsonFilename, Grav::instance()['config']->get('plugins.leaflet-tour'));
        $dataset->setDefaults();
        $dataset->saveDatasetPage();
        self::$datasets[$jsonFilename] = $dataset;
    }

    public static function getDatasetList(): array {
        $datasetList = [];
        foreach(self::getDatasets() as $id=>$dataset) {
            $datasetList[$id] = $dataset->getName();
        }
        return $datasetList;
    }

    public static function getDatasets(): array {
        if (null === self::$datasets) self::$datasets = self::buildDatasets();
        return self::$datasets;
    }

    public static function resetDatasets(): void {
        self::$datasets = self::buildDatasets();
    }

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