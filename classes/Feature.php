<?php

namespace Grav\Plugin\LeafletTour;

/**
 * @property string|null $dataset_id from/based on feature's dataset
 * @property string|null $default_name from/based on feature's dataset
 * @property string $type from/based on feature's dataset
 * @property string|null $id from yaml
 * @property string|null $custom_name from yaml
 * @property string|null $popup from yaml
 * @property bool $hide from yaml
 * @property array $coordinates from yaml
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

    private $dataset_id, $default_name, $type, $id, $custom_name, $popup, $hide, $coordinates, $properties, $extras;

    /**
     * Sets and validates all provided options
     * 
     * @param array $options All the feature data. Type and coordinates should always be set and valid (use json for coordinates).
     */
    private function __construct($options) {
        // type and coordinates
        $this->type = $options['type']; // must be set and valid
        $this->coordinates = $options['coordinates']; // must be set and valid
        // validate strings
        foreach (['dataset_id', 'default_name', 'id', 'custom_name'] as $key) {
            $this->$key = Utils::getStr($options, $key, null);
        }
        // popup: string or array
        $this->popup = $this->validatePopupContent(Utils::get($options, 'popup'));
        // hide must always be bool
        $this->hide = (Utils::get($options, 'hide') === true);
        // validate properties
        $this->properties = Utils::getArr($options, 'properties');
        // extras
        $keys = ['id', 'custom_name', 'popup', 'hide', 'coordinates', 'properties', 'dataset_id', 'default_name', 'type'];
        $this->extras = array_diff_key($options, array_flip($keys));
    }
    /**
     * Allows popup content to be provided in either of two forms: As ['popup' => 'some popup content'] or ['popup' => ['popup_content' => 'some popup content']]
     * 
     * @param array|string|null $popup
     * @return string|null
     */
    private function validatePopupContent($popup) {
        $content = is_array($popup) ? Utils::get($popup, 'popup_content') : $popup;
        if (is_string($content)) return $content;
        else return null;
    }

    /**
     * Creates a new feature object from an entry in a geojson array. Coordinates must be valid for the type.
     * 
     * @param array $json The geojson feature - must be valid (geometry.type, geometry.coordinates)
     * @param string|null $dataset_type If provided, the feature type must match the dataset type
     * @return Feature|null A new object if coordinates and type are valid, null otherwise
     */
    public static function fromJson($json, $dataset_type = null) {
        try {
            $type = self::validateFeatureType(Utils::get($json['geometry'], 'type'));
            $coords = self::validateJsonCoordinates($json['geometry']['coordinates'], $type);
            // coordinates must be valid
            if (!$coords) return null;
            // if dataset type, make sure feature type matches
            if ($dataset_type && ($type !== $dataset_type)) return null;
            // create and return the new feature
            return new Feature([
                'type' => $type,
                'coordinates' => $coords,
                'properties' => Utils::getArr($json, 'properties'),
            ]);
        } catch (\Throwable $t) {}
        return null; // if this point is reached an error was encountered or the feature geometry was invalid
    }

    /**
     * Creates a new feature object from an entry in a dataset page header's "features" list
     * 
     * @param array $options Feature yaml from the dataset page
     * @param string $type The dataset's feature_type (should already have been validated)
     * @param string|null $dataset_id The dataset's id
     * @param string|null $name_property The dataset's name property, if one is set (used to determine feature's default name)
     * @return Feature|null A new object if coordinates and type are valid, null otherwise
     */
    public static function fromDataset($options, $type, $dataset_id = null, $name_property = null) {
        $coords = self::validateYamlCoordinates(Utils::get($options, 'coordinates'), $type);
        if ($name_property && ($props = Utils::getArr($options, 'properties'))) $default_name = Utils::getStr($props, $name_property, null);
        else $default_name = null;
        if ($coords) return new Feature(array_merge($options, [
            'coordinates' => $coords,
            'type' => $type,
            'default_name' => $default_name,
            'dataset_id' => $dataset_id,
        ]));
        else return null;
    }

    /**
     * Validates potential feature update yaml, called when validating a dataset update as a whole
     * 
     * @param array $update
     * @param string $path
     * @return array Modified/validated input array
     */
    public function validateUpdate($update, $path) {
        // validate coordinates, replace if invalid
        $coords = self::validateYamlCoordinates(Utils::get($update, 'coordinates'), $this->getType()) ?? $this->getCoordinates();
        // potentially modify popup content
        $popup = $this->validatePopupContent(Utils::get($update, 'popup'));
        if ($popup && $popup !== $this->getPopup()) {
            $popup = ['popup_content' => self::modifyPopupImagePaths($popup, $path)];
        }
        return array_merge($update, ['coordinates' => self::coordinatesToYaml($coords, $this->getType()), 'popup' => $popup]);
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
                'coordinates' => $this->getCoordinates(),
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
        return array_filter(array_intersect_key($this->getProperties(), array_flip($auto_popup_properties)));
    }

    /**
     * Checks various possibilities to return the best feature name
     * 
     * @return string|null
     */
    public function getName() {
        return $this->getCustomName() ?: $this->getDefaultName() ?: $this->getId();
    }

    /**
     * Returns the feature's coordinates as yaml, instead of json
     * 
     * @return array|string
     */
    public function getYamlCoordinates() {
        return self::coordinatesToYaml($this->getCoordinates(), $this->getType());
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
     * @return array
     */
    public function getCoordinates() { return $this->coordinates; }
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
    public function getDefaultName() { return $this->default_name; }
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
     * Turns valid json coordinates into yaml
     * 
     * @param array $coordinates Coordinates in json form, must be valid
     * @param string $type Feature type - determines if returns string or array
     * @return string|array|null ['lng' => float, 'lat' => float] if $type is 'Point', otherwise json encoded string (or null if something goes wrong)
     */
    public static function coordinatesToYaml($coordinates, $type) {
        $coords = self::validateJsonCoordinates($coordinates, $type);
        if ($coords) {
            try {
                if ($type === 'Point') return ['lng' => $coords[0], 'lat' => $coords[1]];
                else return json_encode($coords);
            } catch (\Throwable $t) {}
        }
        return null; // if this point is reached, something was invalid
    }
    /**
     * Takes coordinates in yaml form and validates them, returning json coordinates if valid
     * 
     * @param mixed $yaml (string with json or ['lng', 'lat'])
     * @param string $type (feature type) Must be a string, if value does not match one of the valid types, will be replaced with 'Point'
     * @return array|null coordinates array (json) if coordinates and type are valid
     */
    public static function validateYamlCoordinates($yaml, $type) {
        try {
            // turn $coordinates into a json array to pass to validateJsonCoordinates
            if (($type === 'Point') && is_array($yaml)) $coordinates = [$yaml['lng'], $yaml['lat']];
            else {
                // fix php's bad json handling
                ini_set( 'serialize_precision', -1 );
                $coordinates = json_decode($yaml);
            }
            return self::validateJsonCoordinates($coordinates, $type);
        } catch (\Throwable $t) {
            return null;
        }
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