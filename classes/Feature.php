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
     * Polyline coords: [[x,y], [x,y], [x,y]]
     * Polygon coords: [[[x,y], [x,y], [x,y]]]
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
        $this->name = $this->customName ?? $this->properties[$nameProperty];
        if (empty($this->name)) return;
        $this->id = $jsonData['id'];
    }

    public function isValid(): bool {
        return isset($this->id);
    }

    // setters
    /**
     * Sets additional info from dataset config that is not found in json
     * @param array $featureData - yaml data from dataset config file
     */
    public function setDatasetFields($featureData): void {
        $this->popupContent = $featureData['popup_content'];
        $id = $featureData['custom_id'];
        if ($id) $this->customId = $id;
        $this->hide = $featureData['hide'];
    }
    /**
     * Updates information based on changes made in the dataset config file
     * @param array $featureData - yaml data from dataset config file
     */
    public function update($featureData): void {
        // everything also stored in json file
        $this->customName = $featureData['custom_name'];
        if ($this->customName) $this->name = $this->customName;
        $coords = $featureData['coordinates'];
        // Option: Fix coordinates - doesn't actually work with yaml format (only matters if we're actually adding coordinates as a thing)
        if ($coords && $coords !== $this->coordinates && Utils::areValidCoordinates($coords, $this->type)) $this->coordinates = $coords;
        $props = $featureData['properties'];
        if (!empty($props) && $props !== $this->properties) $this->properties = $props;
        // everything not stored in json file
        $this->setDatasetFields($featureData);
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
     * Returns the feature in the format needed for the json file
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
        // coordinates
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
     * @param array $features - array of features from json file
     * @param string $nameProperty - only features with a custom name or a value under nameProperty will be included
     * @param string $type - optional, if provided, only features with the given type will be included
     */
    public static function buildFeatureList(array $features, $nameProperty, string $type=null): array {
        $featureList = [];
        foreach ($features as $jsonFeature) {
            if (!is_array($jsonFeature)) continue;
            if ($type) $feature = new Feature($jsonFeature, $nameProperty, $type);
            else $feature = new Feature($jsonFeature, $nameProperty);
            if ($feature->isValid()) $featureList[$feature->id] = $feature;
        }
        return $featureList;
    }

    public static function buildJsonList(array $features): array {
        $jsonList = [];
        foreach ($features as $id=>$feature) {
            $jsonList[] = $feature->asJson();
        }
        return $jsonList;
    }

    public static function buildYamlList(array $features): array {
        $yamlList = [];
        foreach ($features as $id=>$feature) {
            $yamlList[] = $feature->asYaml();
        }
        return $yamlList;
    }

    public static function buildConfigList(array $features): array {
        $configList = [];
        foreach ($features as $id=>$feature) {
            $configList[$id] = $feature->getName();
        }
        return $configList;
    }
}

?>