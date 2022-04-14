<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;

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
     * Values that should not be stored in the yaml at all.
     */
    private static array $reserved_keys = ['dataset', 'name', 'type'];
    /**
     * Values that are specifically meant to be stored in the yaml
     */
    private static array $blueprint_keys = ['id', 'coordinates', 'properties', 'custom_name', 'hide', 'popup_content'];

    /**
     * @var string|null Unique identifier created on dataset initialization in the form of dataset_id--number. Should exist for any features from standard dataset pages. Should never be modified once set.
     */
    private $id;
    /**
     * @var array coordinates in json form, required, must be valid
     */
    private $coordinates;
    /**
     * @var array [$key => $value, ...], contents are entirely dynamic
     */
    private $properties;
    /**
     * @var string|null
     */
    private $custom_name;
    /**
     * @var bool|null sets behavior for feature in tours when include_all or add_all is set for a dataset
     */
    private $hide;
    /** 
     * @var string|null can be overridden by tour value, not the full popup (auto popup is not stored)
     */
    private $popup_content;

    /**
     * @var Dataset|null Reference to the dataset that created the feature. Will always exist for saved features, but may not initially exist for new or temporary features.
     */
    private $dataset;
    /**
     * @var string|null Optional temporary property, only used when feature is created from uploaded json file and therefore does not have a dataset to reference to get type
     */
    private $type;

    /**
     * @var array|null Any values not included in reserved or blueprint keys
     */
    private $extras;

    /**
     * Sets and validates all provided values. Extra values will be placed into the extras array.
     * 
     * @param array $options
     * @param bool $yaml Indicates whether coordinates are in yaml or json format, default true
     */
    private function __construct(array $options, bool $yaml = true) {
        // set reserved values
        if ($dataset = $options['dataset']) $this->dataset = $dataset;
        else if ($type = $options['type']) $this->type = $type;
        $this->setValues($options, $yaml);
    }

    // Constructor Methods

    /**
     * Builds a feature from json data. Called when a new json file has been uploaded and the initial dataset is being built. Sets properties, coordinates, and type.
     * 
     * @param array $json Should contain properties array of $key => $value pairs, geometry array with type and coordinates
     * @param string|null $dataset_type Optional, geometry type must match this if provided
     * 
     * @return Feature|null New Feature if provided geometry is valid
     */
    public static function fromJson(array $json, ?string $dataset_type = null): ?Feature {
        try {
            $options = [];
            $options['type'] = self::validateFeatureType($json['geometry']['type'], false);
            $options['coordinates'] = $json['geometry']['coordinates'];
            $options['properties'] = $json['properties'];

            // test type vs. dataset type
            if ($dataset_type && ($options['type'] !== self::validateFeatureType($dataset_type, false))) return null;

            $feature = new Feature($options, false);
            // feature will only have coordinates set if type was provided (valid) and coordinates were valid json for the type
            if ($feature->getCoordinatesJson()) return $feature;
        } catch (\Throwable $t) {}
        return null; // error encountered or invalid geometry
    }

    /**
     * Builds a feature from yaml (or other array) data. Called when a dataset object is being loaded from a file or a new feature has been created as part of a dataset.md update. Also serves as the general-case feature constructor.
     * 
     * @param array $options The yaml (or other array) data, must contain 'coordinates'
     * @param string|null $type Used to validate coordinates, not necessary if type is already in $options
     * @param bool $yaml Indicated whether coordinates in $options are in yaml or json format, default true
     * 
     * @return Feature|null New Feature object if coordinates and type are valid
     */
    public static function fromArray(array $options, ?string $type = null, bool $yaml = true): ?Feature {
        if ($type) $options['type'] = $type; 
        $feature = new Feature($options, $yaml);

        // feature will only have coordinates set if type and coordinates were valid
        if ($feature->getCoordinatesJson()) return $feature;
        else return null;
    }

    /**
     * Merges a feature from a dataset with feature settings from a tour. Determines whether original popup_content will be used, ignored, or replaced. Also searches for markdown images in the popup_content without set paths and sets the path to the dataset or tour, depending on which is providing the content.
     * 
     * @param Feature $original The original feature object from the dataset
     * @param array $tour_options The yaml data from the tour
     * @param Dataset $merged_dataset The new dataset object created by combining the original dataset with tour options, mostly necessary because auto popup properties may have been modified
     * @param string|null $tour_filename The path to the tour file, if applicable
     * 
     * @return Feature The new merged feature
     */
    public static function fromTour(Feature $original, array $tour_options, Dataset $merged_dataset, ?string $tour_filename = null): Feature {
        $feature = $original->clone();
        if ($content = $tour_options['popup_content']) {
            $feature->setPopupContent($content);
            $image_path = $tour_filename;
        }
        else if ($tour_options['remove_popup']) $feature->setPopupContent(null);
        else $image_path = $feature->getDataset()->getFile()->filename();
        if ($image_path) {
            $pages = Grav::instance()['locator']->findResource('page://');
            $image_path = str_replace($pages, '', $image_path);
            $feature->modifyImagePaths(dirname($image_path));
        }
        $feature->setDataset($merged_dataset);
        return $feature;
    }

    // Object Methods

    /**
     * Validates update content and updates the object
     * 
     * @param array $yaml Update data
     */
    public function update(array $yaml): void {
        $this->setValues($yaml, true);
    }

    /**
     * Checks popup_content for any markdown images that do not have a path set (indicated by image name starting with a stream, a dot, or a slash). For any found, the image name is appended by page stream and provided $path
     * 
     * @param string $path The path (starting after the user/pages directory) to the tour or dataset folder where the popup content is from
     */
    private function modifyImagePaths(string $path): void {
        // search for markdown images - format: ![alt text](image_file.ext?action&action2=x&action3=y "title")
        $split = explode('![', $this->getPopupContent() ?? '');
        $content = array_shift($split); // ignore first
        foreach ($split as $image_start) {
            // look for beginning of the image url
            $pieces = explode('](', $image_start, 2);
            if (count($pieces) > 1) { // it had better be, but you never know
                $url_start = $pieces[1];
                // decide if new path needs to be applied
                if (!str_starts_with($url_start, '.') && !str_starts_with($url_start, '/')) {
                    // also check for stream
                    $stream_split = explode('://', $url_start);
                    if (!in_array($stream_split[0], Utils::STREAMS)) {
                        // build back the string, but with the new path added
                        $added = true;
                        $content .= '![' . $pieces[0] . "](page:/$path/$url_start";
                    }
                }
            }
            if (!$added) {
                $content .= "![$image_start";
            }
        }
        $this->setPopupContent($content);
    }

    /**
     * Creates an identical copy of the object. $feature->dataset will continue pointing to the same dataset object.
     * 
     * @return Feature
     */
    public function clone(): Feature {
        $feature = new Feature([]);
        foreach (get_object_vars($this) as $key => $value) {
            $feature->$key = $value;
        }
        return $feature;
    }
    public function __toString() {
        $vars = get_object_vars($this);
        // change dataset to just id
        if ($dataset = $vars['dataset']) $vars['dataset'] = $dataset->getId();
        return json_encode($vars);
    }
    public function equals(Feature $other): bool {
        $vars1 = get_object_vars($this);
        if ($dataset = $vars1['dataset']) $vars1['dataset'] = $dataset->getId();
        $vars2 = get_object_vars($other);
        if ($dataset = $vars2['dataset']) $vars2['dataset'] = $dataset->getId();
        return ($vars1 == $vars2);
    }
    /**
     * @return array The yaml array that can be saved in the dataset features list (will not contain any reserved values)
     */
    public function toYaml(): array {
        $yaml = get_object_vars($this);
        // remove reserved
        $yaml = array_diff_key($yaml, array_flip(self::$reserved_keys));
        // replace coordinates with yaml
        $yaml['coordinates'] = $this->getCoordinatesYaml();
        // add name
        $yaml['name'] = $this->getName();
        // remove and replace extras
        unset($yaml['extras']);
        $yaml = array_merge($this->getExtras() ?? [], $yaml);

        return $yaml;
    }
    /**
     * @return string JSON encoded string (coordinates in JSON form)
     */
    public function toJson(): string {
        $array = $this->toYaml();
        $array['coordinates'] = $this->getCoordinatesJson();
        return json_encode($array);
    }
    /**
     * @return array GeoJSON array
     */
    public function toGeoJson(): array {
        return [
            'type' => 'Feature',
            'geometry' => [
                'coordinates' => $this->getCoordinatesJson(),
                'type' => $this->getType(),
            ],
            'properties' => $this->getProperties(),
        ];
    }

    // Calculated Getters

    /**
     * @return string|null Formatted auto popup (using dataset auto popup properties) if the feature has any auto popup property values
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
     * @return string|null popup content generated from auto popup and regular popup if either exist
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
     * @return string|null Feature name. Priority goes to custom_name, then properties[name_property], then id. Returns null if nothing is found.
     */
    public function getName(): ?string {
        if ($name = $this->getCustomName()) return $name;
        if (($dataset = $this->getDataset()) && ($prop = $dataset->getNameProperty()) && ($name = $this->getProperty($prop))) return $name;
        return $this->getId();
    }

    // Getters
    
    /**
     * @return string|null $this->id
     */
    public function getId(): ?string {
        return $this->id;
    }
    /**
     * @return array $this->coordinates
     */
    public function getCoordinatesJson(): ?array {
        return $this->coordinates;
    }
    /**
     * @return mixed coordinates: Point feature will return ['lng' => float, 'lat' => float], non-point will return json-encoded string
     */
    public function getCoordinatesYaml() {
        return self::coordinatesToYaml($this->getCoordinatesJson(), $this->getType());
    }
    /**
     * @return array $this->properties, [$key => $value]
     */
    public function getProperties(): array {
        return $this->properties ?? [];
    }
    /**
     * @param string $property Property key to look for
     * 
     * @return mixed $this->properties[$property]
     */
    public function getProperty(string $property) {
        return $this->getProperties()[$property];
    }
    /**
     * @return string|null $this->custom_name
     */
    public function getCustomName(): ?string {
        return $this->custom_name;
    }
    /**
     * @return bool|null $this->hide
     */
    public function getHide(): ?bool {
        return $this->hide;
    }
    /**
     * @return string|null $this->popup_content
     */
    public function getPopupContent(): ?string {
        return $this->popup_content;
    }
    /**
     * @return Dataset|null dataset represented by $this->dataset
     */
    public function getDataset(): ?Dataset {
        return $this->dataset;
    }
    /**
     * @return string|null $this->dataset->type or $this->type
     */
    public function getType(): ?string {
        if ($dataset = $this->getDataset()) return $dataset->getType();
        else return $this->type;
    }
    /**
     * @return array|null An array with all non-reserved and non-blueprint properties attached to the object, if any.
     */
    public function getExtras(): ?array {
        return $this->extras;
    }

    // Setters

    /**
     * Sets blueprint key values and extras array. Ignores reserved values.
     * 
     * @param array $options Array of values to set
     * @param bool $yaml Indicates whether coordinates are in yaml or json format, default true
     */
    private function setValues(array $options, bool $yaml): void {
        // set blueprint key values
        $this->setId($options['id']);
        if ($yaml) $this->setCoordinatesYaml($options['coordinates']);
        else $this->setCoordinatesJson($options['coordinates']);
        $this->setProperties($options['properties']);
        $this->setCustomName($options['custom_name']);
        $this->setHide($options['hide']);
        $this->setPopupContent($options['popup_content']);
        // set extras
        $this->setExtras($options);
    }
    
    /**
     * Will not set id to null
     * 
     * @param string $id Sets $this->id (by default only if not already set)
     * @param bool $overwrite - if true, $this->id will be set even if already set
     */
    public function setId($id, $overwrite = false): void {
        if(is_string($id) && !empty($id)) {
            if (!$this->id || $overwrite) $this->id = $id;
        }
    }
    /**
     * Only sets coordinates if valid
     * 
     * @param array $coordinates in json form
     */
    public function setCoordinatesJson($coordinates): void {
        // must have type to set coordinates
        if ($type = $this->getType()) {
            // coordinates must be valid
            if ($coordinates = self::validateJsonCoordinates($coordinates, $type)) {
                $this->coordinates = $coordinates;
            }
        }
    }
    /**
     * Only sets coordinates if valid
     * 
     * @param mixed $coordinates Json-encoded string (for non-points) or array with lng and lat (for points)
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
     * @param array|null $properties
     */
    public function setProperties($properties): void {
        if (is_array($properties) || null === $properties) $this->properties = $properties;
    }
    /**
     * @param string|null $name
     */
    public function setCustomName($name): void {
        if (is_string($name) || null === $name) $this->custom_name = $name;
    }
    /**
     * @param bool|null $hide
     */
    public function setHide($hide): void {
        if (is_bool($hide) || null === $hide) $this->hide = $hide;
    }
    /**
     * @param string|null $content
     */
    public function setPopupContent($content): void {
        if (is_string($content) || null === $content) $this->popup_content = $content;
    }
    /**
     * Sets $this->dataset and unsets $this->type
     * 
     * @param Dataset $dataset
     */
    public function setDataset(Dataset $dataset): void {
        $this->dataset = $dataset;
        unset($this->type);
    }
    /**
     * @param array|null $extras
     */
    public function setExtras($extras) {
        if (is_array($extras)) {
            $this->extras = array_diff_key($extras, array_flip(array_merge(self::$reserved_keys, self::$blueprint_keys)));
            if (empty($this->extras)) $this->extras = null;
        }
        else if (null === $extras) $this->extras = null;
    }

    // static methods

    /**
     * @param string $type Hopefully a valid feature type
     * @param bool $loose Determines behavior if $type is not valid - return 'Point' vs. null
     * 
     * @return string|null $type, possibly with modified capitalization, or Point if $type was not valid - if loose was false, return null instead if $type was not valid
     */
    public static function validateFeatureType($type, bool $loose = true): ?string {
        if (is_string($type) && ($validated_type = self::FEATURE_TYPES[strtolower($type)])) return $validated_type;
        else if ($loose) return 'Point';
        else return null;
    }
    /**
     * @param array $geometry ['coordinates' => array, 'type' => string]
     * @param null|string $feature_type If provided, geometry['type'] must match
     * @return null|array ['coordinates' => valid coordinates array, 'type' => string] if coordinates and type are valid
     */
    // public static function validateJsonGeometry($geometry, $feature_type = null): ?array {
    //     if (($type = ($geometry['type'])) && ($coordinates = self::validateJsonCoordinates($geometry['coordinates'] ?? [], $type))) {
    //         $type = self::validateFeatureType($type);
    //         // also make sure that types match, if $feature_type was provided
    //         if (!$feature_type || ($type === self::validateFeatureType($feature_type))) {
    //             return ['coordinates' => $coordinates, 'type' => $type];
    //         }
    //     }
    //     return null;
    // }
    /**
     * @param array $coordinates (json array)
     * @param string $type (feature type)
     * @return null|array coordinates array if valid
     */
    public static function validateJsonCoordinates($coordinates, $type): ?array {
        if (!is_string($type) && is_array($coordinates)) return null;
        switch (self::validateFeatureType($type)) {
            case 'Point':
                if (Utils::isValidPoint($coordinates)) return $coordinates;
                break;
            case 'LineString': return self::validateLineString($coordinates);
            case 'MultiLineString': return self::validateMultiLineString($coordinates);
            case 'Polygon': return self::validatePolygon($coordinates);
            case 'MultiPolygon': return self::validateMultiPolygon($coordinates);
        }
        return null;
    }
    /**
     * @param mixed $yaml (string with json or ['lng', 'lat'])
     * @param string $type (feature type)
     * @return null|array coordinates array if valid (json)
     */
    public static function validateYamlCoordinates($yaml, $type): ?array {
        // turn $coordinates into a json array to pass to validateJsonCoordinates
        if (is_array($yaml)) $coordinates = [$yaml['lng'], $yaml['lat']];
        else {
            try {
                $coordinates = json_decode($yaml);
            } catch (\Throwable $t) {
                return null;
            }
        }
        return self::validateJsonCoordinates($coordinates, $type);
    }
    /**
     * @param array $coordinates Coordinates in json form, assumed to be valid
     * @param string $type Feature type - determines if returns string or array
     * @return string|array|null ['lng' => float, 'lat' => float] if $type is 'Point', otherwise json encoded string (or null if something goes wrong)
     */
    public static function coordinatesToYaml($coordinates, $type) {
        if (!is_string($type)) return null;
        try {
            $type = self::validateFeatureType($type);
            // Point => make array
            if ($type === 'Point') return ['lng' => $coordinates[0], 'lat' => $coordinates[1]];
            // not point
            else return json_encode($coordinates);
        } catch (\Throwable $t) {
            return null;
        }
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