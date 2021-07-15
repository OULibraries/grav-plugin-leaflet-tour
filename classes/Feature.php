<?php
namespace Grav\Plugin\LeafletTour;

/**
 * The Feature class stores and handles all the information for one feature. 
 * This information is compiled from the geoJSON file feature list and the dataset page config feature list.
 */
class Feature {

    protected $properties; // associative array, properties list from json file
    protected $type; // string, geometry.type
    protected $coordinates; // array - may just have two coordinates, may have many
    protected $id;
    protected $customName;

    protected $popupContent;

    /**
     * @param array $jsonData - the feature from the geoJSON file
     * @param string $type - optional parameter to limit feature type
     */
    function __construct($jsonData, $type=null) {
        if (!$jsonData['type'] || strtolower($jsonData['type']) !== 'feature' || !$jsonData['geometry']) return;
        $this->properties = $jsonData['properties'];
        $this->type = $jsonData['geometry']['type'];
        if ($type && $this->type !== $type) return;
        $this->coordinates = $jsonData['geometry']['coordinates'];
        $this->id = $jsonData['id'];
        $this->customName = $jsonData['customName'];
    }

    public function validate() {
        return isset($this->id);
    }

    // setters
    public function setPopupContent($popupContent) {
        $this->popupContent = $popupContent;
    }

    // getters

    public function getName($nameProperty) {
        return $this->customName ?? $this->properties[$nameProperty];
    }

    /**
     * Returns the feature in the format needed for the json file/geoJSON object
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
     * Returns the feature in the format needed for the dataset page header
     */
    public function asYaml($nameProperty): array {
        // TODO: Change as more options provided in dataset config
        return [
            'id'=>$this->id,
            'name'=>$this->getName($nameProperty),
            'custom_name'=>$this->customName,
            'popup_content'=>$this->popupContent,
        ];
    }

    // utilities

    /**
     * @param array $features - array of features from json file
     * @param string $nameProperty - optional, if provided, only features with a custom name or a value under nameProperty will be included
     * @param string $type - optional, if provided, only features with the given type will be included
     */
    public static function buildFeatureList(array $features, string $nameProperty=null, string $type=null): array {
        $featureList = [];
        foreach ($features as $jsonFeature) {
            if ($type) $feature = new Feature($jsonFeature, $type);
            else $feature = new Feature($jsonFeature);
            if ($feature->validate() && (!$nameProperty || !empty($feature->getName($nameProperty)))) $featureList[$feature->id] = $feature;
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

    public static function buildYamlList(array $features, string $nameProperty): array {
        $yamlList = [];
        foreach ($features as $id=>$feature) {
            $yamlList[] = $feature->asYaml($nameProperty);
        }
        return $yamlList;
    }

    public static function buildConfigList(array $features, string $nameProperty): array {
        $configList = [];
        foreach ($features as $id=>$feature) {
            $configList[$id] = $feature->getName($nameProperty);
        }
        return $configList;
    }
}

?>