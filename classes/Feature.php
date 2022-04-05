<?php

namespace Grav\Plugin\LeafletTour;

class Feature {

    /**
     * Standard GeoJson feature types for reference
     * 
     * Point: [lng, lat]
     * LineString: array of two or more points
     * MultiLineString: array of one or more LineStrings
     * Polygon: Basically a MultiLineString, but each line must have at least four points, with the first and last points being identical to each other (linear ring). Note that the first line is the basic polygon, while any additional lines are holes added to the polygon.
     * MultiPolygon: array of one or more Polygons
     */
    const FEATURE_TYPES = [
        'point'=>'Point', // [x, y]
        'linestring'=>'LineString', // [[x,y], [x,y], [x,y]]
        'multilinestring'=>'MultiLineString', // [[[x,y], [x,y], [x,y]]]
        'polygon'=>'Polygon', // [[[x,y], [x,y], [x,y], [x,y]]]
        'multipolygon'=>'MultiPolygon' // [[[[x,y], [x,y], [x,y], [x,y]]]]
    ];

    /**
     * unique identifier, created on dataset initialization or feature creation in the form of dataset_id--number, stored in yaml once set, not required for initially created feature, never modified once set
     */
    private ?string $id = null;
    /**
     * [$key => $value, ...], contents are entirely dynamic, always set, but can be empty
     */
    private array $properties = [];
    /**
     * geoJson coordinates - details listed with self::FEATURE_TYPES, always set, must be valid
     */
    private array $coordinates = [];
    /**
     * overrides the value of the current name property
     */
    private ?string $custom_name = null;
    /**
     * determines if feature is included in tours when include_all or add_all is set for a dataset
     */
    private ?bool $hide = null;
    /**
     * can be overridden by value from tour, may not be the full feature popup (if auto popup exists)
     */
    private ?string $popup_content = null;
    /**
     * reference to the dataset that created the feature, assuming there is one
     */
    private ?Dataset $dataset = null;
    /**
     * optional temporary property, only provided when feature is created from uploaded json file and therefore does not have a dataset to reference, never set in any other circumstances
     */
    private ?string $type = null;

    /**
     * Never called directly by anything but the construction methods (and clone) - construction methods will validate coordinates before calling.
     * @param array $options - sets object properties
     */
    private function __construct(array $options) {
        // set all values
        foreach ($options as $key => $value) {
            try {
                $this->$key = $value;
            } catch (\Throwable $t) {
                // do nothing
            }
        }
    }
    /**
     * Builds a feature from json data. Called when a new json file has been uploaded and the initial dataset is being built. Sets properties, coordinates, and type.
     * @param array $json Should contain properties array of $key => $value pairs, geometry array with type and coordinates
     * @param null|string $type Optional, geometry type must match this if provided
     * @return null|Feature New Feature if provided geometry is valid
     */
    public static function fromJson(array $json, ?string $type = null): ?Feature {
        // if valid, options will contain 'coordinates' and 'type'
        if ($options = self::validateJsonGeometry($json['geometry'] ?? [], $type)) {
            // add properties to options and return
            $options['properties'] = $json['properties'] ?? [];
            return new Feature($options);
        }
        else return null;
    }
    /**
     * Builds a feature from yaml data. Called when a dataset object is being loaded from a file or a new feature has been created as part of a dataset.md update. Sets coordinates, dataset for certain, and should set a number of other values.
     * @param array $yaml Must contain 'coordinates' with 'lng' and 'lat' or a json string. May also contain any other yaml values - properties, id, custom_name, hide, popup_content
     * @param null|Dataset $dataset
     * @param null|string $type
     * @return null|Feature New Feature object if dataset_id is valid and coordinates from yaml are valid for dataset type
     */
    public static function fromDataset(array $yaml, ?Dataset $dataset, ?string $type = null): ?Feature {
        if ((($dataset && ($type = $dataset->getType())) || $type) && ($coordinates = self::validateYamlCoordinates($yaml['coordinates'], $type))) {
            $yaml['coordinates'] = $coordinates;
            $yaml['dataset'] = $dataset;
            return new Feature($yaml);
        }
        else return null;
    }
    /**
     * Builds a feature purely from an array, assuming coordinates and type are valid. No other limitations are imposed.
     * @param array $options Must have coordinates (json format) and type
     * @return null|Feature New Feature object if coordinates are valid
     */
    public static function fromArray(array $options): ?Feature {
        // if valid, options will contain 'coordinates' and 'type'
        if ($geometry = self::validateJsonGeometry($options)) {
            // create new feature by merging $options and $geometry (in case coordinates had to be changed to be validated)
            return new Feature(array_merge($options, $geometry));
        }
        else return null;
    }
    public static function fromTour(Feature $original, array $yaml, Dataset $dataset): Feature {
        $feature = $original->clone();
        if ($yaml['popup_content']) $feature->popup_content = $yaml['popup_content'];
        else if ($yaml['remove_popup']) $feature->popup_content = null;
        $feature->dataset = $dataset; // auto popup properties may have changed
        return $feature;
    }

    // object methods

    /**
     * Creates an identical copy of the object
     * @return Feature
     */
    public function clone(): Feature {
        $options = [];
        foreach (get_object_vars($this) as $key => $value) {
            $options[$key] = $value;
        }
        return new Feature($options);
    }
    public function __toString() {
        $vars = get_object_vars($this);
        // change dataset to just id
        if ($dataset = $vars['dataset']) $vars['dataset'] = $dataset->getId();
        return json_encode($vars);
    }

    /**
     * @return array Feature yaml array that can be saved in dataset features list. [id, name, custom_name, hide, popup_content, properties, coordinates]
     */
    public function asYaml(): array {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'custom_name' => $this->custom_name,
            'coordinates' => $this->getCoordinatesYaml(),
            'properties' => $this->getProperties(),
            'hide' => $this->isHidden(),
            'popup_content' => $this->popup_content,
        ];
    }
    /**
     * Validates update content and updates the object
     * @param array $yaml Update data
     */
    public function update(array $yaml): void {
        $yaml = array_diff_key($yaml, array_flip(['id', 'dataset', 'type']));
        foreach ($yaml as $key => $value) {
            switch ($key) {
                case 'coordinates':
                    $this->setCoordinatesYaml($value);
                    break;
                case 'properties':
                    $this->properties = array_merge($this->properties, $value);
                    break;
                default:
                    $this->$key = $value;
                    break;
            }
        }
    }

    // getters
    
    /**
     * @return null|string $this->id
     */
    public function getId(): ?string {
        return $this->id;
    }
    /**
     * @return array $this->properties
     */
    public function getProperties(): array {
        return $this->properties;
    }
    /**
     * @param string $property Property key to look for
     * @return mixed $this->properties[$property]
     */
    public function getProperty(string $property) {
        return $this->getProperties()[$property];
    }
    /**
     * @return array $this->coordinates
     */
    public function getCoordinatesJson(): array {
        return $this->coordinates;
    }
    /**
     * @return mixed $coordinates Point feature will return ['lng' => float, 'lat' => float], non-point will return json-encoded string
     */
    public function getCoordinatesYaml() {
        return self::coordinatesToYaml($this->getCoordinatesJson(), $this->getType());
    }
    /**
     * @return null|bool $this->hide
     */
    public function isHidden(): ?bool {
        return $this->hide;
    }
    /**
     * @return null|string $this->popup_content
     */
    public function getPopupContent(): ?string {
        return $this->popup_content;
    }
    /**
     * Generates and returns auto popup, using dataset
     * @return null|string Formatted auto popup if the feature has any auto popup property values
     */
    public function getAutoPopup(): ?string {
        if ($this->getDataset() && ($props = $this->getDataset()->getAutoPopupProperties())) {
            // create list of feature properties, filtered by dataset property keys
            $properties = array_intersect_key($this->getProperties(), array_flip($props));
            // format with html into string
            $content = '';
            foreach ($properties as $name => $value) {
                if ($value) $content .= "<li><span class='auto-popup-prop-name'>$name:</span> $value</li>";
            }
            if ($content) return "<ul>$content</ul>";
        }
        return null;
    }
    /**
     * Generates and returns auto popup + popup content
     * @param bool $remove If true, $this->popup_content will be ignored. Default false.
     * @param null|string $replace If provided, replaces $this->popup_content, ignores $remove. Default null.
     * @return string popup content generated from auto popup and regular popup
     */
    public function getFullPopup(): ?string {
        $auto = $this->getAutoPopup();
        $popup = $this->getPopupContent();
        // return null if no popup
        if (!$auto && !$popup) return null;
        // if both, put them together, otherwise return one
        $full = '';
        if ($auto) $full .= $auto;
        // TODO: Maybe use shortcode to put div around it?
        if ($popup) $full .= "\n\n$popup";
        return $full;
    }
    /**
     * @return null|Dataset dataset represented by $this->dataset
     */
    public function getDataset(): ?Dataset {
        return $this->dataset;
    }
    /**
     * @return null|string $this->type or $this->dataset->type
     */
    public function getType(): ?string {
        if ($dataset = $this->getDataset()) return $dataset->getType();
        else return $this->type;
    }
    /**
     * @return string Feature name. Priority goes to custom_name, then properties[name_property], then id. Returns empty string if nothing is found.
     */
    public function getName(): ?string {
        if ($name = $this->custom_name) return $name;
        if (($dataset = $this->getDataset()) && ($prop = $dataset->getNameProperty()) && ($name = $this->getProperty($prop))) return $name;
        return $this->id;
    }

    // setters
    
    /**
     * @param string Sets $this->id if not already set
     */
    public function setId(string $id): void {
        $this->id ??= $id;
    }
    /**
     * @param string $dataset Sets $this->dataset if not already set
     */
    public function setDataset(Dataset $dataset): void {
        $this->dataset = $dataset;
    }
    /**
     * @param mixed $coordinates Json-encoded string or array with lng and lat to determine new coordinates, only set if valid
     */
    public function setCoordinatesYaml($coordinates): void {
        // must have type to set coordinates
        if ($type = $this->getType()) {
            // coordinates must be valid
            if ($coordinates = self::validateYamlCoordinates($coordinates, $type)) {
                $this->coordinates = $coordinates;
            }
        }
    }
    /**
     * @param array $coordinates GeoJson coordinates to replace $this->coordinates if valid
     */
    public function setCoordinatesJson(array $coordinates): void {
        // must have type to set coordinates
        if ($type = $this->getType()) {
            // coordinates must be valid
            if ($coordinates = self::validateJsonCoordinates($coordinates, $type)) {
                $this->coordinates = $coordinates;
            }
        }
    }

    // static methods

    /**
     * @param string $type Hopefully a valid feature type
     * @return string $type, possibly with modified capitalization, or Point if $type was not valid
     */
    public static function validateFeatureType(?string $type): string {
        return self::FEATURE_TYPES[strtolower($type)] ?: 'Point';
    }
    /**
     * @param array $geometry ['coordinates' => array, 'type' => string]
     * @param null|string $feature_type If provided, geometry['type'] must match
     * @return null|array ['coordinates' => valid coordinates array, 'type' => string] if coordinates and type are valid
     */
    public static function validateJsonGeometry(array $geometry, ?string $feature_type = null): ?array {
        if (($type = ($geometry['type'])) && $coordinates = self::validateJsonCoordinates($geometry['coordinates'] ?? [], $type)) {
            $type = self::validateFeatureType($type);
            // also make sure that types match, if $feature_type was provided
            if (!$feature_type || ($type === self::validateFeatureType($feature_type))) {
                return ['coordinates' => $coordinates, 'type' => $type];
            }
        }
        return null;
    }
    /**
     * @param array $coordinates (json array)
     * @param string $type (feature type)
     * @return null|array coordinates array if valid
     */
    public static function validateJsonCoordinates(array $coordinates, string $type): ?array {
        switch (self::validateFeatureType($type)) {
            case 'Point': if (Utils::isValidPoint($coordinates)) return $coordinates;
            case 'LineString': return self::validateLineString($coordinates);
            case 'MultiLineString': return self::validateMultiLineString($coordinates);
            case 'Polygon': return self::validatePolygon($coordinates);
            case 'MultiPolygon': return self::validateMultiPolygon($coordinates);
        }
        return null;
    }
    /**
     * @param mixed $coordinates (string with json or ['lng', 'lat'])
     * @param string $type (feature type)
     * @return null|array coordinates array if valid (json)
     */
    public static function validateYamlCoordinates($coordinates, string $type): ?array {
        // todo: fix php's bad json handling?
        // turn $coordinates into a json array to pass to validateJsonCoordinates
        if (is_array($coordinates)) $coordinates = [$coordinates['lng'], $coordinates['lat']];
        else {
            try {
                $coordinates = json_decode($coordinates);
                if (!$coordinates) throw new Exception();
            } catch (\Throwable $t) {
                return null;
            }
        }
        return self::validateJsonCoordinates($coordinates, $type);
    }
    /**
     * @param array $coordinates Coordinates in json form, assumed to be valid
     * @param string $type Feature type - determines if returns string or array
     * @return string|array ['lng' => float, 'lat' => float] if $type is 'Point', otherwise json encoded string
     */
    public static function coordinatesToYaml(array $coordinates, string $type) {
        // fix php's bad json handling?
        $type = self::validateFeatureType($type);
        // Point => make array
        if ($type === 'Point') return ['lng' => $coordinates[0], 'lat' => $coordinates[1]];
        // not point
        else return json_encode($coordinates);
    }
    /**
     * @param array|mixed $coordinates Should be an array of points
     * @return null|array $coordinates if valid
     */
    private static function validateLineString($coordinates): ?array {
        if (is_array($coordinates) && count($coordinates) >= 2) {
            foreach ($coordinates as $point) {
                if (!Utils::isValidPoint($point)) return null;
            }
            return $coordinates;
        }
        return null;
    }
    /**
     * @param mixed $coordinates Should be an array of LineStrings
     * @return null|array $coordinates if valid
     */
    private static function validateMultiLineString($coordinates): ?array {
        if (is_array($coordinates) && count($coordinates) >= 1) {
            foreach ($coordinates as $line)  {
                if (!self::validateLineString($line)) return null;
            }
            return $coordinates;
        }
        return null;
    }
    /**
     * Linear ring requires at least four points, with the first and last matching.
     * 
     * @param mixed $coordinates Should be an array with at least three points
     * @return null|array $coordinates if valid, possibly modified if first and last points did not match (to close the ring)
     */
    private static function validateLinearRing($coordinates): ?array {
        if (self::validateLineString($coordinates) && count($coordinates) >= 3) {
            // add first point to end if needed to close the ring
            if ($coordinates[0] !== $coordinates[count($coordinates) - 1]) $coordinates[] = $coordinates[0];
            // have to check length again, because array of three points may have been modified to be long enough
            if (count($coordinates) >= 4) return $coordinates;
        }
        return null;
    }
    /**
     * @param mixed $coordinates Should be an array of linear rings
     * @return null|array $coordinates if valid, possibly modified to close a given linear ring
     */
    private static function validatePolygon($coordinates): ?array {
        if (is_array($coordinates) && count($coordinates) >= 1) {
            $polygon = [];
            foreach ($coordinates as $ring) {
                if ($ring = self::validateLinearRing($ring)) $polygon[] = $ring;
                else return null;
            }
            return $polygon;
        }
        return null;
    }
    /**
     * @param mixed $coordinates Should be an array of polygons
     * @return null|array $coordinates if valid, possibly modified to close linear rings as needed
     */
    private static function validateMultiPolygon($coordinates): ?array {
        if (is_array($coordinates) && count($coordinates) >= 1) {
            $multi = [];
            foreach ($coordinates as $polygon) {
                if ($polygon = self::validatePolygon($polygon)) $multi[] = $polygon;
                else return null;
            }
            return $multi;
        }
        return null;
    }
}
?>