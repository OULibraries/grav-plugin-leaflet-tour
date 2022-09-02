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

    // some information from/based on the feature's dataset
    private ?string $dataset_id, $default_name;
    private string $type;
    // yaml content
    private ?string $id, $custom_name, $popup;
    private bool $hide;
    private array $coordinates, $properties, $extras;

    /**
     * Sets and validates all provided options
     * 
     * @param array $options All the feature data. Type and coordinates should always be set and valid (use json for coordinates).
     */
    private function __construct(array $options) {
        // type and coordinates
        $this->type = $options['type'];
        $this->coordinates = $options['coordinates'];
        // validate strings
        foreach (['dataset_id', 'default_name', 'id', 'custom_name'] as $key) {
            $this->$key = is_string($options[$key]) ? $options[$key] : null;
        }
        // popup: string or array
        $this->popup = $this->validatePopupContent($options['popup']);
        // hide must always be bool
        $this->hide = ($options['hide'] === true);
        // validate properties
        $this->properties = is_array($options['properties']) ? $options['properties'] : [];
        // extras
        $keys = ['id', 'custom_name', 'popup', 'hide', 'coordinates', 'properties', 'dataset_id', 'default_name', 'type'];
        $this->extras = array_diff_key($options, array_flip($keys));
    }
    private function validatePopupContent($popup): ?string {
        $content = is_array($popup) ? $popup['popup_content'] : $popup;
        if (is_string($content)) return $content;
        else return null;
    }

    /**
     * Creates a new feature object from an entry in a geojson array. Coordinates must be valid for the type.
     * 
     * @param array $json The geojson feature - must be valid (geometry.type, geometry.coordinates)
     * @param ?string $dataset_type If provided, the feature type must match the dataset type
     * @return ?Feature A new object if coordinates and type are valid, null otherwise
     */
    public static function fromJson(array $json, ?string $dataset_type = null): ?Feature {
        try {
            $type = self::validateFeatureType($json['geometry']['type']);
            $coords = self::validateJsonCoordinates($json['geometry']['coordinates'], $type);
            // coordinates must be valid
            if (!$coords) return null;
            // if dataset type, make sure feature type matches
            if ($dataset_type && ($type !== $dataset_type)) return null;
            // create and return the new feature
            return new Feature([
                'type' => $type,
                'coordinates' => $coords,
                'properties' => $json['properties'],
            ]);
        } catch (\Throwable $t) {}
        return null; // if this point is reached an error was encountered or the feature geometry was invalid
    }

    /**
     * Creates a new feature object from an entry in a dataset page header's "features" list
     * 
     * @param array $options Feature yaml from the dataset page
     * @param string $type The dataset's feature_type (should already have been validated)
     * @param string $dataset_id The dataset's id
     * @param ?string $name_property The dataset's name property, if one is set (used to determine feature's default name)
     * @return ?Feature A new object if coordinates and type are valid, null otherwise
     */
    public static function fromDataset(array $options, string $type, ?string $dataset_id = null, ?string $name_property = null): ?Feature {
        $coords = self::validateYamlCoordinates($options['coordinates'], $type);
        if ($name_property && ($props = $options['properties'])) $default_name = $props[$name_property];
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
     * @return array Modified/validated input array
     */
    public function validateUpdate(array $update, string $path): array {
        // validate coordinates, replace if invalid
        $coords = self::validateYamlCoordinates($update['coordinates'], $this->getType()) ?? $this->getYamlCoordinates();
        // potentially modify popup content
        $popup = $this->validatePopupContent($update['popup']);
        if ($popup && $popup !== $this->getPopup()) {
            $popup = ['popup_content' => self::modifyPopupImagePaths($popup, $path)];
        }
        return array_merge($update, ['coordinates' => $coords, 'popup' => $popup]);
    }

    /**
     * @return array [id, name, custom_name, hide, popup => [popup_content], properties, coordinates]
     */
    public function toYaml(): array {
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
     * @return array [type => 'Feature', geometry => [coordinates, type], properties]
     */
    public function toGeoJson(): array {
        $props = $this->getProperties();
        if (($popup = $this->getPopup()) && !$props['popup_content']) $props['popup_content'] = $popup;
        if (($name = $this->getCustomName()) && !$props['custom_name']) $props['custom_name'] = $name;
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
     * @return array [key => value]
     */
    public function getAutoPopupProperties(array $auto_popup_properties): array {
        return array_filter(array_intersect_key($this->getProperties(), array_flip($auto_popup_properties)));
        // TODO: remove
        // $properties = [];
        // // make sure that only properties with values are returned
        // foreach ($auto_popup_properties as $key) {
        //     if ($value = $this->getProperties()[$key]) $properties[$key] = $value;
        // }
        // return $properties;
    }

    public function getName(): ?string {
        return $this->getCustomName() ?: $this->getDefaultName() ?: $this->getId();
    }

    public function getYamlCoordinates() {
        return self::coordinatesToYaml($this->getCoordinates(), $this->getType());
    }
    
    // ordinary getters - no logic, just to prevent direct interaction with object properties
    public function getId(): ?string { return $this->id; }
    public function getProperties(): array { return $this->properties; }
    public function getCoordinates(): array { return $this->coordinates; }
    public function getCustomName(): ?string { return $this->custom_name; }
    public function getExtras(): array { return $this->extras; }
    public function isHidden(): bool { return $this->hide; }
    public function getPopup(): ?string { return $this->popup; }

    public function getDatasetId(): ?string { return $this->dataset_id; }
    public function getDefaultName(): ?string { return $this->default_name; }
    public function getType(): string { return $this->type; }

    // feature utils

    /**
     * Ensures that type is one of the valid geojson feature types, uses Point if no match (ignoring caps) is found
     * @param string $type Hopefully a valid feature type
     * @return string $type, possibly with modified capitalization, otherwise (invalid type) return 'Point'
     */
    public static function validateFeatureType($type): string {
        if (is_string($type) && ($validated_type = self::FEATURE_TYPES[strtolower($type)])) return $validated_type;
        else return 'Point';
    }
    /**
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
     * @param mixed $yaml (string with json or ['lng', 'lat'])
     * @param string $type (feature type) Must be a string, if value does not match one of the valid types, will be replaced with 'Point'
     * @return null|array coordinates array (json) if coordinates and type are valid
     */
    public static function validateYamlCoordinates($yaml, $type): ?array {
        // turn $coordinates into a json array to pass to validateJsonCoordinates
        if (($type === 'Point') && is_array($yaml)) $coordinates = [$yaml['lng'], $yaml['lat']];
        else {
            try {
                // fix php's bad json handling
                if (version_compare(phpversion(), '7.1', '>=')) {
                    ini_set( 'serialize_precision', -1 );
                }
                $coordinates = json_decode($yaml);
            } catch (\Throwable $t) {
                return null;
            }
        }
        return self::validateJsonCoordinates($coordinates, $type);
    }
    /**
     * @param array $coordinates (json array)
     * @param string $type, should have been validated, will return null if type is not one of the valid feature types
     * @return null|array coordinates array if coordinates and type valid
     */
    public static function validateJsonCoordinates($coordinates, $type): ?array {
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

    /**
     * Prepends provided path to image paths for markdown images where the image path does not start with dot, slash, http, or a stream (has ://)
     */
    public static function modifyPopupImagePaths(?string $content, string $path): ?string {
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
                        $content .= '![' . $pieces[0] . "](page:/$path/$url_start";
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