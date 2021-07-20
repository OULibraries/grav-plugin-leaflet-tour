<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\File;

/**
 * The Utils class is never instantiated as an object. Instead, it contains various functions that are generally useful for a leaflet tour that should not belong to one particular class.
 */
class Utils {

    const FEATURE_TYPES = [
        'point'=>'Point',
        'multipoint'=>'MultiPoint',
        'linestring'=>'LineString',
        'multilinestring'=>'MultiLineString',
        'polygon'=>'Polygon',
        'multipolygon'=>'MultiPolygon'
    ];
    const JSON_VAR_REGEX = '/^(.)*var(\s)+json_(\w)*(\s)+=(\s)+/';

    // default values if the default marker icon is used
    const DEFAULT_MARKER_OPTIONS = [
        'iconAnchor' => [12, 41],
        'iconRetinaUrl' => 'user/plugins/leaflet-tour/images/marker-icon-2x.png',
        'iconSize' => [25, 41],
        'iconUrl' => 'user/plugins/leaflet-tour/images/marker-shadow.png',
        'shadowSize' => [41, 41],
        'shadowUrl' => 'user/plugins/leaflet-tour/images/marker-shadow.png',
        'className' => 'leaflet-marker',
        'tooltipAnchor' => [-12, 20]
    ];
    // default values if the default marker icon is not used
    const MARKER_FALLBACKS = [
        'iconSize' => [14, 14],
        'shadowSize' => [],
        'tooltipAnchor' => [-5, 5],
        'iconAnchor' => [],
    ];

    const BASEMAP_ROUTE = 'user/data/leaflet-tour/images/basemaps/';

    const IMAGE_ROUTE = 'user/data/leaflet-tour/images/';

    // GeoJson Utils

    /**
     * Checks that coordinates are an array of [latitude, longitude]
     */
    public static function isValidPoint($coords, $reverse = false): bool {
        if (!is_array($coords) || count($coords) !== 2) return false;
        // first value should be longitude
        if ($reverse) {
            $long = $coords[1];
            $lat = $coords[0];
        }
        else {
            $long = $coords[0];
            $lat = $coords[1];
        }
        if (!is_numeric($long) || $long < -180 || $long > 180) return false;
        // second value should be latitude
        if (!is_numeric($lat) || $lat < -90 || $lat > 90) return false;
        return true;
    }

    /**
     * Checks that coordinates are an array with one or more points
     */
    public static function isValidMultiPoint($coords): bool {
        if (!is_array($coords) || empty($coords)) return false;
        foreach ($coords as $point) {
            if (!self::isValidPoint($point)) return false;
        }
        return true;
    }

    /**
     * Checks that coordinates are either a simple polygon or an array of simple polygons
     */
    public static function isValidPolygon($coords): bool {
        if (!self::isValidSimplePolygon($coords)) {
            if (!is_array($coords) || count($coords) < 1) return false;
            foreach ($coords as $polygon) {
                if (!self::isValidSimplePolygon($polygon)) return false;
            }
        }
        return true;
    }

    /**
     * Checks that coordinates are an aray with three or more points
     */
    public static function isValidSimplePolygon($coords): bool {
        if (self::isValidMultiPoint($coords) && count($coords) >= 3) return true;
        else return false;
    }

    /**
     * Checks that coordinates are an array with one or more polygons
     */
    public static function isValidMultiPolygon($coords): bool {
        if (!is_array($coords) || count($coords) < 1) return false;
        foreach ($coords as $polygon) {
            if (!self::isValidPolygon($polygon)) return false;
        }
        return true;
    }

    public static function isValidLineString($coords): bool {
        return self::isValidMultiPoint($coords);
    }
    public static function isValidMultiLineString($coords): bool {
        if (!is_array($coords)) return false;
        foreach ($coords as $line) {
            if (!self::isValidLineString($line)) return false;
        }
        return true;
    }

    public static function areValidCoordinates($coords, $type): bool {
        $type = self::setValidType($type); // just in case
        switch ($type) {
            case 'Point': return self::isValidPoint($coords);
            case 'MultiPoint': return self::isValidMultiPoint($coords);
            case 'Polygon': return self::isValidPolygon($coords);
            case 'MultiPolygon': return self::isValidMultiPolygon($coords);
            case 'LineString': return self::isValidLineString($coords);
            case 'MultiLineString': return self::isValidMultiLineString($coords);
            default: return false;
        }
    }

    public static function setValidType(string $type): string {
        $validType = self::FEATURE_TYPES[strtolower($type)];
        if (!$validType) $validType = 'Point';
        return $validType;
    }

    public static function setBounds($bounds): array {
        if (is_array($bounds) && count($bounds) === 4) {
            $bounds = [[$bounds['south'], $bounds['west']], $bounds['north'], $bounds['east']];
            if (self::isValidPoint($bounds[0], true) && self::isValidPoint($bounds[1], true)) return $bounds;
        }
        return [];
    }

    // Route Utils
    
    // takes an array of folder/page names and returns a route
    public static function getPageRoute(array $keys): string {
        $route = Grav::instance()['locator']->findResource('page://').'/';
        for ($i = 0; $i < count($keys); $i++) {
            $glob = glob($route.'*');
            $match = '';
            foreach ($glob as $item) {
                if (strtolower($item) === $route.$keys[$i]) {
                    $match = $item;
                    continue;
                }
            }
            // check for folder numeric prefix
            if (empty($match)) {
                foreach ($glob as $item) {
                    if (preg_match('/^[0-9][0-9]\.'.$keys[$i].'$/', strtolower(str_replace($route, '', $item)))) {
                        $match = $item;
                        continue;
                    }
                }
            }
            // last ditch effort
            if (empty($match)) {
                foreach ($glob as $item) {
                    if (preg_match('/^..\.'.$keys[$i].'$/', strtolower(str_replace($route, '', $item)))) {
                        $match = $item;
                        continue;
                    }
                }
            }
            //if (empty($match)) return implode(',', $glob);
            if (empty($match)) return '';
            $route = $match.'/';
        }
        return $route;
    }
    
    public static function getDatasetRoute(): string {
        $key = Grav::instance()['page']->header()->controller['key']; // current page - the reason why this function only works when called from dataset.yaml
        $keys = explode("/", $key); // break key into sub-components
        return self::getPageRoute($keys).'dataset.md';
    }

    public static function getTourRouteFromTourConfig(): string {
        $key = Grav::instance()['page']->header()->controller['key']; // current page - the reason why this function only works when called from tour.yaml
        $keys = explode("/", $key); // break key into sub-components
        return self::getPageRoute($keys).'tour.md';
    }

    public static function getTourRouteFromViewConfig(): string {
        $keys = explode("/", Grav::instance()['page']->header()->controller['key']); // current page - the reason why this function only works when called from view.yaml
        array_pop($keys); // last element is view folder, which we don't want
        if (count($keys) > 0) {
            return self::getPageRoute($keys).'tour.md';
        }
        return '';
    }

    // Dataset Upload Utils

    public static function parseDatasetUpload($fileData) {
        $jsonFilename = preg_replace('/.js$/', '.json', $fileData['name']);
        $jsonArray = [];
        if ($fileData['type'] === 'text/javascript') {
            $jsonArray = self::readJSFile($fileData['name']);
        }
        // TODO: Check for other file types
        return [$jsonArray, $jsonFilename];
    }
    
    /**
     * Turn Qgis2Web .js file into .json
     * 
     * @param array $fileData - the yaml array for the uploaded file from plugin config data_files [name, type, size, path]
     * @return array - returns array with json data on success, null on failure
     */
    protected static function readJSFile(array $fileData): array {
        $file = File::instance(Grav::instance()['locator']->getBase().'/'.$fileData['path']);
        // find and remove the initial json variable
        $count = 0;
        $jsonRegex = preg_replace(self::JSON_VAR_REGEX, '', $file->content(), 1, $count);
        // if a match was found (and removed), try converting the file contents to json
        if ($count == 1) {
            // fix php's bad json handling
            if (version_compare(phpversion(), '7.1', '>=')) {
            	ini_set( 'serialize_precision', -1 );
            }
            try {
                $jsonData = json_decode($jsonRegex, true);
                return $jsonData;
            } catch (Exception $e) {
                return [];
            }
        }
        return [];
    }

    // Other Utils

    public static function generateShortcodeList(array $features, array $datasets): string {
        $shortcodes = [];
        $features = array_column($features, 'id');
        foreach ($datasets as $dataset) {
            foreach($features as $id) {
                $feature = $dataset->getFeatures()[$id];
                if ($feature) {
                    $shortcodes[] = '[view-popup id="'.$id.'"]'.$feature->getName($dataset->getNameProperty()).'[/view-popup]';
                }
            }
        }
        return implode("\r\n", $shortcodes);
    }
}

?>