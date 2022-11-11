<?php

namespace Grav\Plugin\LeafletTour;

/**
 * A feature is equivalent to one GeoJSON object - a Point, LineString, Polygon, etc. - that can be displayed on a map.
 * @property string|null $dataset_id from/based on feature's dataset
 * @property string $type from/based on feature's dataset
 * @property string|null $name based on dataset and feature yaml
 * @property string|null $id from yaml
 * @property string|null $custom_name from yaml
 * @property string|null $popup from yaml
 * @property bool $hide from yaml
 * @property array|string $coordinates from yaml
 * @property array $properties from yaml
 * @property array $extras from yaml
 */
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

    private $dataset_id, $type, $name, $id, $custom_name, $popup, $hide, $coordinates, $properties, $extras;
    
    /**
     * Prepares a feature object from a set of options. Provided coordinates are assumed to be valid - should be validated beforehand if necessary.
     * - Sets and validates data type for all expected yaml options (no validation for coords)
     * - Accepts popup as either array (popup with key popup_content) or string (popup_content) (priority: string)
     * - Sets type and dataset_id as provided by dataset
     * - Sets feature name based on custom name, name prop from dataset, and/or feature id
     * - Sets any extra values to allow additional customization
     * 
     * @param array $options Original feature yaml
     * @param string $type Feature type as set by dataset
     * @param string|null $name_prop Name property as set by dataset
     * @param string|null $dataset_id ID from dataset
     */
    public function __construct($options, $type, $name_prop = null, $dataset_id = null) {
        // Set without validation - coordinates, type, dataset_id, original_yaml
        $this->coordinates = Utils::get($options, 'coordinates');
        $this->type = $type;
        $this->dataset_id = $dataset_id;

        // Set with validation
        $this->id = Utils::getStr($options, 'id', null);
        $this->custom_name = Utils::getStr($options, 'custom_name', null);
        $this->popup = Utils::getStr($options, 'popup_content', null);
        if (!$this->popup) {
            $popup = Utils::getArr($options, 'popup');
            $this->popup = Utils::getStr($popup, 'popup_content', null);
        }
        $this->hide = Utils::getType($options, 'hide', 'is_bool', false);
        $this->properties = Utils::getArr($options, 'properties');

        // Set feature name
        if ($this->custom_name) $this->name = $this->custom_name;
        else if ($name_prop && ($prop = $this->getProperty($name_prop))) $this->name = $prop;
        else $this->name = $this->id;

        // Set extras
        $keys = ['id', 'custom_name', 'popup', 'popup_content', 'hide', 'coordinates', 'properties', 'dataset_id', 'default_name', 'type'];
        $this->extras = array_diff_key($options, array_flip($keys));
    }

    /**
     * Creates an initial yaml array for a feature from an entry in a geojson array.
     * - Returns array with coordinates and properties if coordinates are valid (otherwise null)
     * - If dataset_type is provided, coordinates type must match to be considered valid
     * 
     * @param array $json The geojson feature, must be valid
     * @param string|null $dataset_type
     * @return array|null Array with coordinates and properties if valid, null otherwise
     */
    public static function validateJsonFeature($json, $dataset_type = null) {
        $geometry = Utils::getArr($json, 'geometry');
        $type = self::validateFeatureType(Utils::get($geometry, 'type'));
        $coords = self::validateJsonCoordinates(Utils::getArr($geometry, 'coordinates'), $type);
        // coordinates must be valid
        if (!$coords) return null;
        // if provided, type must match dataset type
        if ($dataset_type && ($type !== $dataset_type)) return null;
        // create and return the initial feature array
        return ['coordinates' => self::jsonCoordsToYaml($coords, $type), 'properties' => Utils::getArr($json, 'properties')];
    }

    /**
     * Validates yaml for new or updated feature, called when valdiating a dataset as a whole
     * - Replaces invalid coords with default coords
     * - If no default coords and provided coords are invalid, returns null
     * - Modifies popup content if path is provided
     * - Uses constructor to validate other things
     * 
     * @param array $yaml Feature yaml to validate
     * @param string $type Feature type from dataset
     * @param string|null $name_prop Name property from dataset (for setting 'name' in yaml)
     * @param string|null $path Path to dataset folder (for modifying popup content image paths)
     * @param array|string|null $default_coords Default valid coordinates, provided if this would update an existing feature
     * @return array|null Updated yaml, null if no valid coordinates
     */
    public static function validateUpdate($yaml, $type, $name_prop = null, $path = null, $default_coords = null) {
        // Validate coordinates
        $coords = self::validateYamlCoordinates(Utils::get($yaml, 'coordinates'), $type);
        if (!$coords) {
            if ($default_coords) $coords = $default_coords;
            else return null;
        }
        // use constructor to validate feature, dataset id does not matter
        $feature = new Feature(array_merge($yaml, ['coordinates' => $coords]), $type, $name_prop, null);
        $updated_yaml = $feature->toYaml();
        // modify popup image paths
        if ($path && ($popup = $feature->getPopup())) {
            $updated_yaml['popup'] = ['popup_content' => self::modifyPopupImagePaths($popup, $path)];
        }
        return $updated_yaml;
    }

    /**
     * Returns content that can be included in the dataset page header
     * 
     * @return array [id, name, custom_name, hide, popup => [popup_content], properties, coordinates]
     */
    public function toYaml() {
        return array_merge($this->getExtras(), [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'custom_name' => $this->getCustomName(),
            'hide' => $this->isHidden(),
            'popup' => ['popup_content' => $this->getPopup()],
            'properties' => $this->getProperties(),
            'coordinates' => $this->getYamlCoordinates(),
        ]);
    }

    /**
     * Creates a valid geojson representation of the feature
     * - Returns geojson feature
     * - Adds custom name/popup content to properties array as long as they do not overwrite values
     * 
     * @return array [type => 'Feature', geometry => [coordinates, type], properties]
     */
    public function toGeoJson() {
        $props = $this->getProperties();
        if (($popup = $this->getPopup()) && !$this->getProperty('popup_content')) $props['popup_content'] = $popup;
        if (($name = $this->getCustomName()) && !$this->getProperty('custom_name')) $props['custom_name'] = $name;
        return [
            'type' => 'Feature',
            'geometry' => [
                'coordinates' => $this->getJsonCoordinates(),
                'type' => $this->getType(),
            ],
            'properties' => $props,
        ];
    }

    /**
     * Returns the subset of the feature's properties specified by the input (and only for properties that have values)
     * 
     * @param array $auto_popup_properties
     * @return array [key => value]
     */
    public function getAutoPopupProperties(array $auto_popup_properties) {
        $props = [];
        foreach ($auto_popup_properties as $key) {
            if ($value = $this->getProperty($key)) $props[$key] = $value;
        }
        return $props;
    }

    /**
     * Returns a given feature property, assuming that property exists in the feature's properties list
     * 
     * @param string $key
     * @return mixed
     */
    public function getProperty($key) {
        return Utils::get($this->getProperties(), $key);
    }
    
    // ordinary getters - no logic, just to prevent direct interaction with object properties
    /**
     * @return string|null
     */
    public function getId() { return $this->id; }
    /**
     * @return array
     */
    public function getProperties() { return $this->properties; }
    /**
     * @return array|string
     */
    public function getYamlCoordinates() { return $this->coordinates; }
    /**
     * @return array
     */
    public function getJsonCoordinates() { return self::yamlCoordsToJson($this->coordinates, $this->type); }
    /**
     * @return string|null
     */
    public function getCustomName() { return $this->custom_name; }
    /**
     * @return array
     */
    public function getExtras() { return $this->extras; }
    /**
     * @return bool
     */
    public function isHidden() { return $this->hide; }
    /**
     * @return string|null
     */
    public function getPopup() { return $this->popup; }

    /**
     * @return string|null
     */
    public function getDatasetId() { return $this->dataset_id; }
    /**
     * @return string|null
     */
    public function getName() { return $this->name; }
    /**
     * @return string
     */
    public function getType() { return $this->type; }

    // feature utils

    /**
     * Ensures that type is one of the valid geojson feature types, uses Point if no match (ignoring caps) is found
     * 
     * @param string $type Hopefully a valid feature type
     * @return string $type, possibly with modified capitalization, otherwise (invalid type) return 'Point'
     */
    public static function validateFeatureType($type) {
        if (is_string($type) && ($validated_type = Utils::get(self::FEATURE_TYPES, strtolower($type)))) return $validated_type;
        else return 'Point';
    }
    /**
     * Turns valid json coordinates into yaml. Coordinates are assumed to be valid - does not validate.
     * - Turns points into array with lng and lat, everything else into json encoded strings
     * - Returns null if something goes wrong (but not necessarily if coordinates are invalid)
     * 
     * @param array $coordinates Coordinates in json form, must be valid
     * @param string $type Feature type - determines if returns string or array
     * @return string|array|null ['lng' => float, 'lat' => float] if $type is 'Point', otherwise json encoded string (or null if something goes wrong)
     */
    public static function jsonCoordsToYaml($coordinates, $type) {
        try {
            if ($type === 'Point') return ['lng' => $coordinates[0], 'lat' => $coordinates[1]];
            else return json_encode($coordinates);
        } catch (\Throwable $t) {
            return null;
        }
    }
    /**
     * Turns valid yaml coordinates into json. Coordinates are assumed to be valid - does not validate.
     * - Turns both point and shapes into accurate json
     * - Returns null if something goes wrong (but not necessarily if coordinates are invalid)
     * 
     * @param array|string $coordinates Coordinates in yaml form, must be valid
     * @param string $type Feature type - determines how coordinates will be worked with
     * @return array|null json coordinates (or null if something goes wrong)
     */
    public static function yamlCoordsToJson($coordinates, $type) {
        try {
            // point
            if ($type === 'Point') $coords = [$coordinates['lng'], $coordinates['lat']];
            else {
                // fix php's bad json handling
                ini_set( 'serialize_precision', -1 );
                $coords = json_decode($coordinates);
            }
            return $coords;
        } catch (\Throwable $t) {
            return null;
        }
    }
    /**
     * Takes coordinates in yaml form and validates them
     * 
     * @param mixed $yaml (string with json or ['lng', 'lat'])
     * @param string $type (feature type) Must be a string, if value does not match one of the valid types, will be replaced with 'Point'
     * @return array|string|null yaml coordinates if coordinates and type are valid
     */
    public static function validateYamlCoordinates($coordinates, $type) {
        // turn coordinates into json to use json validation methods
        $coords = self::yamlCoordsToJson($coordinates, $type);
        // validate
        $coords = self::validateJsonCoordinates($coords, $type);
        // turn back into yaml to return (if valid)
        if ($coords) return self::jsonCoordsToYaml($coords, $type);
        else return null;
    }
    /**
     * Validates feature coordinates
     * 
     * @param array $coordinates (json array)
     * @param string $type, should have been validated, will return null if type is not one of the valid feature types
     * @return array|null coordinates array if coordinates and type valid
     */
    public static function validateJsonCoordinates($coordinates, $type) {
        if (!is_array($coordinates)) return null;
        switch ($type) {
            case 'Point': return (Utils::isValidPoint($coordinates)) ? $coordinates : null;
            case 'LineString': return self::validateLineString($coordinates);
            case 'MultiLineString': return self::validateMultiLineString($coordinates);
            case 'Polygon': return self::validatePolygon($coordinates);
            case 'MultiPolygon': return self::validateMultiPolygon($coordinates);
        }
        return null;
    }
    /**
     * Validates coordinates: Must be an array of valid points
     * 
     * @param array $coordinates Should be an array of points (function validates type)
     * @return array|null $coordinates if valid
     */
    private static function validateLineString($coordinates) {
        if (is_array($coordinates) && count($coordinates) >= 2) {
            foreach ($coordinates as $point) {
                if (!Utils::isValidPoint($point)) return null;
            }
            return $coordinates;
        }
        return null;
    }
    /**
     * Validates coordinates: Must be an array of valid LineStrings
     * 
     * @param array $coordinates Should be an array of LineStrings (function validates type)
     * @return array|null $coordinates if valid
     */
    private static function validateMultiLineString($coordinates) {
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
     * @param array $coordinates Should be an array with at least three points (function validates type)
     * @return array|null $coordinates if valid, possibly modified if first and last points did not match (to close the ring)
     */
    private static function validateLinearRing($coordinates) {
        if (self::validateLineString($coordinates) && count($coordinates) >= 3) {
            // add first point to end if needed to close the ring
            if ($coordinates[0] !== $coordinates[count($coordinates) - 1]) $coordinates[] = $coordinates[0];
            // have to check length again, because array of three points may have been modified to be long enough
            if (count($coordinates) >= 4) return $coordinates;
        }
        return null;
    }
    /**
     * Validates coordinates: Must be an array of linear rings
     * 
     * @param array $coordinates Should be an array of linear rings (function validates type)
     * @return array|null $coordinates if valid, possibly modified to close a given linear ring
     */
    private static function validatePolygon($coordinates) {
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
     * Validates coordinates: Must be an array of Polygons
     * 
     * @param array $coordinates Should be an array of polygons (function validates type)
     * @return array|null $coordinates if valid, possibly modified to close linear rings as needed
     */
    private static function validateMultiPolygon($coordinates) {
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

    /**
     * Prepends provided path to image paths for markdown images where the image path does not start with dot, slash, http, or a stream (has ://). Called when dataset or tour page is saved to ensure that any popup images uploaded to the dataset/tour page can be accessed wherever the popup is viewed.
     * 
     * @param string|null $content
     * @param string $path
     * @return string|null
     */
    public static function modifyPopupImagePaths($content, $path) {
        if (!$content) return $content;
        // search for markdown images - format: ![alt text](image_file.ext?action&action2=x&action3=y "title")
        $split = explode('![', $content ?? '');
        $content = array_shift($split); // ignore first, but keep it as the beginning of content
        foreach ($split as $image_start) {
            $added = false;
            // look for beginning of the image url
            $pieces = explode('](', $image_start, 2);
            if (count($pieces) > 1) { // it had better be, but you never know
                $url_start = $pieces[1];
                // decide if new path needs to be applied
                if (!str_starts_with($url_start, '.') && !str_starts_with($url_start, '/') && !str_starts_with($url_start, 'http')) {
                    // also check for stream
                    if (!str_contains($image_start, '://')) {
                        // build back the string, but with the new path added
                        $added = true;
                        $content .= '![' . $pieces[0] . "](page://$path/$url_start";
                    }
                }
            }
            if (!$added) {
                $content .= "![$image_start";
            }
        }
        return $content;
    }
}
?>