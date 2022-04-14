<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;

class Utils {
    
    const BASEMAP_ROUTE = 'user/data/leaflet-tour/images/basemaps/';
    const UPDATE_ROUTE = '/leaflet-tour/datasets/update/';
    const IMAGE_ROUTE = 'user/data/leaflet-tour/images/';

    const STREAMS = ['user', 'page', 'image', 'account', 'environment', 'asset', 'blueprints', 'config', 'plugins', 'themes', 'theme', 'languages', 'user-data', 'system', 'cache', 'log', 'backup', 'tmp'];

    /**
     * Search a directory recursively for any files matching the provided key.
     * 
     * @param string $key The name of the template file (or other file, really) to look for. Passing '*dataset.md' would get any file ending in 'dataset.md'
     * @param array $results List of pages found so far. If calling this the first time, pass an empty array.
     * @param null|string $dir Optional: Specify the directory to start the search in. If not provided, the pages folder will be used.
     */
    public static function getTemplateFiles(string $key, array $results, ?string $dir = null): array {
        if (!$dir) $dir = Grav::instance()['locator']->findResource('page://');
        // check if directory holds one of the pages we are looking for - if found, add to results
        // recursively search children for pages
        foreach (glob("$dir/*") as $item) {
            if (str_ends_with($item, $key)) $results[] = $item;
            $results = self::getTemplateFiles($key, $results, $item);
        }
        return $results;
    }

    // route utils

    public static function getDatasetFile(): MarkdownFile {
        $keys = explode('/', Grav::instance()['page']->header()->controller['key']);
        $route = self::getPageRoute($keys);
        // try points
        $file = MarkdownFile::instance($route . 'point_dataset.md');
        if ($file->exists()) return $file;
        else return MarkdownFile::instance($route . 'shape_dataset.md');
    }
    public static function getPageRoute(array $keys): string {
        $route = Grav::instance()['locator']->findResource('page://').'/'; // user/pages folder route
        foreach ($keys as $key) {
            $glob = glob($route.'*'); // note: case-sensitive
            // need to find a match inside glob for the current key, then can add to route and keep going
            $new_route = '';
            foreach ($glob as $item) {
                // to make $item potentially match $key, need to do case-insensitive check, also need to strip a potential numeric prefix from $item (and note that item starts with the rest of route, so need to remove that, too)
                // regex: find any number of digits at the beginning of the string followed by a period
                $stripped_item = str_replace($route, '', $item);
                $stripped_item = preg_replace('/^[0-9]+\./', '', $stripped_item);
                if (strcasecmp($stripped_item, $key) === 0) {
                    $new_route = "$item/";
                    break;
                }
            }
            if (empty($new_route)) return '';
            else $route = $new_route;
        }
        return $route;
    }

    // geojson utils

    /**
     * @param mixed $coords Should be array with two floats. First is longitude, second is latitude.
     * @return bool True if is valid, false if not
     */
    public static function isValidPoint($coords): bool {
        if (is_array($coords) && count($coords) === 2) {
            [$lng, $lat] = $coords;
            if (is_numeric($lng) && $lng >= -180 && $lng <= 180 && is_numeric($lat) && $lat >= -90 && $lat <= 90) return true;
        }
        return false;
    }
    /**
     * Checks a parameter to see if it constitutes a valid bounds array. Must be an array with four keys: north, south, east, and west. Southwest and northeast must be valid points.
     * @param mixed $bounds The (hopefully) array to check
     * @return null|array [[float, float], [float, float]] (southwest, northeast) if valid
     */
    public static function getBounds($bounds): ?array {
        if (is_array($bounds) && count($bounds) === 4) { // must be an array with four values
            $bounds = [[$bounds['south'], $bounds['west']], [$bounds['north'], $bounds['east']]]; // values must have keys south, west, north, and east
            // have to reverse direction for checking for valid points
            if (self::isValidPoint([$bounds[0][1], $bounds[0][0]]) && self::isValidPoint([$bounds[1][1], $bounds[1][0]])) return $bounds; // southwest and northeast must form valid points
        }
        return null;
    }
    /**
     * Takes center coordinates and distance. Calculates the dimensions of a box that would hold a circle with radius = distance and center = coordinates
     * @param array $coordinates The center of the circle, should be a valid point,
     * @param float $distance The radius of the circle
     * @return null|array [[float, float], [float, float]] (southwest, northeast) if valid
     */
    public static function calculateBounds($coords, float $dist): ?array {
        if (!self::isValidPoint($coords)) return null;
        return [[max($coords[1] - $dist, -90), $coords[0] - $dist], [min($coords[1] + $dist, 90), $coords[0] + $dist]];
    }
}
?>