<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;

class Utils {
    const IMAGE_ROUTE = 'user/data/leaflet-tour/images/';

    /**
     * Search a directory recursively for any files matching the provided key.
     * 
     * @param string $key The name of the template file (or other file, really) to look for. Passing '*dataset.md' would get any file ending in 'dataset.md'
     * @param array $results List of pages found so far. If calling this the first time, pass an empty array.
     * @param null|string $dir Optional: Specify the directory to start the search in. If not provided, the pages folder will be used.
     */
    public static function getTemplateFiles(string $key, array $results, ?string $dir = null, bool $print = false): array {
        if (!$dir) $dir = Grav::instance()['locator']->findResource('page://');
        // check if directory holds one of the pages we are looking for - if found, add to results
        // if (!empty(glob("$dir/$key"))) $results[] = "$dir/$key";
        // recursively search children for pages
        foreach (glob("$dir/*") as $item) {
            if ($print && ($item == "$dir/*dataset.md")) $results[] = $item;
            if (str_ends_with($item, $key)) $results[] = $item;
            //if ($item === "$dir/$key") var_dump($item);
            $results = self::getTemplateFiles($key, $results, $item, $print);
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
    private static function getPageRoute(array $keys): string {
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
}
?>