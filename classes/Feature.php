<?php
namespace Grav\Plugin\LeafletTour;

/**
 * The Feature class stores and handles all the information for one feature. 
 * This information is compiled from the geoJSON file feature list and the dataset page config feature list.
 */
class Feature {

    protected $properties; // associative array, properties list from json file
    protected $type; // string, geometry.type (Point, Polyline, Polygon, MultiPolygon)
    protected $coordinates; // array - may just have two coordinates, may have many
    /* Coordinates:
     * Point coords: [x, y]
     * LineString coords: [[x,y], [x,y], [x,y]] (array of points)
     * Polygon coords: [[[x,y], [x,y], [x,y]]] (array of LineStrings, identical to)
     * Polygon coords with holes: [
     *      [[x,y], [x,y], [x,y]], (polygon)
     *      [[x,y], [x,y], [x,y]], (first hole)
     *      [[x,y], [x,y], [x,y]], (second hole, etc.)
     * ]
     * MultiPolygon coords: [
     *      [[[x,y], [x,y], [x,y]]], (first polygon)
     *      [[[x,y], [x,y], [x,y]]] (second polygon)
     * ]
     */
    protected $id;
    protected $customName;
    protected $name;

    protected $popupContent;

    /**
     * @param array $jsonData - the feature from the geoJSON file
     * @param string $type - optional parameter to limit feature type - type will always be assumed to be point if not provided
     */
    function __construct(array $jsonData, string $nameProperty, string $type='point') {
        // type has only a few accepted values
        $type = Utils::setValidType($type);
        if (!$jsonData['type'] || strtolower($jsonData['type']) !== 'feature' || !$jsonData['geometry']) return;
        $this->properties = $jsonData['properties'];
        $this->type = $jsonData['geometry']['type'];
        if ($this->type !== $type) return;
        $this->coordinates = $jsonData['geometry']['coordinates'];
        $this->customName = $jsonData['customName'];
        $this->id = $jsonData['id'];
        $this->name = $this->customName ?? $this->properties[$nameProperty] ?? $this->id;
    }

    public function isValid(): bool {
        return isset($this->id);
    }

    // setters
    /**
     * Sets additional info from dataset config that is not found in json - popup content, other options that may be implemented in the future
     * @param array $featureData - yaml data from dataset config file
     */
    public function addDatasetInfo(array $featureData): void {
        $this->popupContent = $featureData['popup_content'];
        // $id = $featureData['custom_id'];
        // if ($id) $this->customId = $id;
        $this->hide = $featureData['hide']; // not really implemented yet
    }
    /**
     * Updates information based on changes made in the dataset config file. Will update fields that will also modify the JSON file and call addDatasetInfo to update fields only stored in the dataset YAML file.
     * @param array $featureData - yaml data from dataset config file
     */
    public function update(array $featureData): void {
        // update everything also stored in the json file - everything else will be set through addDatasetInfo
        $this->customName = $featureData['custom_name'];
        if ($this->customName) $this->name = $this->customName;
        // coordinates - some base code that can be expanded to actually implement coordinate editing in the future
        $coords = $featureData['coordinates'];
        // Option: Fix coordinates - doesn't actually work with yaml format (only matters if we're actually adding coordinates as a thing)
        if ($coords && $coords !== $this->coordinates && Utils::areValidCoordinates($coords, $this->type)) $this->coordinates = $coords;
        // properties are currently not editable, but may be added to dataset.yaml in the future
        $props = $featureData['properties'];
        if (!empty($props) && $props !== $this->properties) $this->properties = $props;
        // everything not stored in json file
        $this->addDatasetInfo($featureData);
    }

    public function updateName($nameProperty): void {
        $this->name = $this->customName ?? $this->properties[$nameProperty] ?? $this->id;
    }

    // getters

    public function getName() {
        return $this->name;
    }
    public function getPopup() {
        return $this->popupContent;
    }
    public function getId() {
        return $this->id;
    }

    /**
     * Returns the feature in the format needed for the dataset json file
     */
    public function asJson(): array {
        return [
            'type'=>'Feature',
            'id'=>$this->id,
            'customName'=>$this->customName,
            'properties'=>$this->properties,
            'geometry'=>[
                'type'=>$this->type,
                'coordinates'=>$this->coordinates,
            ],
        ];
    }

    /**
     * Returns the feature in the format needed for Leaflet's GeoJSON layer
     */
    public function asGeoJson(): array {
        return [
            'type'=>'Feature',
            'properties'=>[
                'id'=>$this->id,
                'name'=>$this->name,
            ],
            'geometry'=>[
                'type'=>$this->type,
                'coordinates'=>$this->coordinates,
            ],
        ];
    }

    /**
     * Returns the feature in the format needed for the dataset page header
     */
    public function asYaml(): array {
        // Option: Change as more options provided in dataset config
        $yaml = [
            'id'=>$this->id,
            'name'=>$this->getName(),
            'custom_name'=>$this->customName,
            'popup_content'=>$this->popupContent,
            'properties'=>$this->properties,
            'hide'=>$this->hide,
        ];
        // coordinates - not really implemented at the moment, though
        if ($this->type === 'Point') {
            $yaml['coordinates'] = ['long'=>$this->coordinates[0], 'lat'=>$this->coordinates[1]];
        } else if ($this->type === 'MultiPoint' || $this->type === 'LineString') {
            $coords = [];
            foreach ($this->coordinates as $point) {
                $coords[] = ['long'=>$point[0], 'lat'=>$point[1]];
            }
            $yaml['coordinates'] = $coords;
        }
        return $yaml;
    }

    // utilities

    /**
     * Takes an array of features from the dataset JSON file and turns them into Feature objects for the Dataset.
     * @param array $features - array of features from json file
     * @param string $nameProperty - for setting the feature's name
     * @param string $type - only features with the given type will be included
     */
    public static function buildFeatureList(array $features, string $nameProperty, string $type='Point'): array {
        $featureList = [];
        foreach ($features as $jsonFeature) {
            if (!is_array($jsonFeature)) continue;
            $feature = new Feature($jsonFeature, $nameProperty, $type);
            if ($feature->isValid()) $featureList[$feature->id] = $feature;
        }
        return $featureList;
    }

    /**
     * Builds the list of features for saving the dataset's JSON file
     * @param array $features - array of Feature objects
     */
    public static function buildJsonList(array $features): array {
        $jsonList = [];
        foreach ($features as $id=>$feature) {
            $jsonList[] = $feature->asJson();
        }
        return $jsonList;
    }

    /**
     * Builds the list of features for saving the dataset's config (YAML) file
     * @param array $features - array of Feature objects
     */
    public static function buildYamlList(array $features): array {
        $yamlList = [];
        foreach ($features as $id=>$feature) {
            $yamlList[] = $feature->asYaml();
        }
        return $yamlList;
    }

    /**
     * Builds an array of id=>name for use in admin panel config dropdowns.
     * @param array $features - array of Feature objects
     */
    public static function buildConfigList(array $features): array {
        $configList = [];
        foreach ($features as $id=>$feature) {
            $configList[$id] = $feature->getName();
        }
        return $configList;
    }

    // feature must be array with type Feature, geometry type of featureType, geometry coordinates valid for featureType
    /**
     * Checks that a feature provided by the JSON file is valid: It must be an array with geometry type of featureType, and geometry coordinates valid for featureType. Corrects any linear rings (for Polygon and MultiPolygon features) that do not have matching first and last points.
     * @param $feature - Should be an array, but function will accept any type.
     * @param string $featureType - The GeoJSON type to use. Accepts: Point, Polygon, MultiPolygon, LineString, MultiLineString. If invalid, will default to Point.
     * @return array|null - Returns the feature (possibly with improvements) if it is valid. Otherwise returns null.
     */
    public static function setValidFeature($feature, string $featureType): ?array {
        try {
            // if ($feature['type'] !== "Feature") return null;
            $featureType = self::setValidType($featureType);
            if ($feature['geometry']['type'] !== $featureType) return null;
            $coords = self::setValidCoordinates($feature['geometry']['coordinates'], $featureType);
            if ($coords) $feature['geometry']['coordinates'] = $coords;
            else return null;
        } catch (\Throwable $t) {
            return null;
        }
        return $feature;
    }
}

?>