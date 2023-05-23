<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;

class Utils {
    
    /** Access uploaded basemap image paths as 'route/filename' or icon paths as 'route/icons/filename */
    const BASEMAP_ROUTE = 'user/data/leaflet-tour/images/basemaps';
    /** Access files in update folder as 'base/route/filename' */
    const UPDATE_ROUTE = 'user/data/leaflet-tour/datasets/update';
    /** Access uploaded icon image paths as 'route/filename' */
    const ICON_ROUTE = 'user/data/leaflet-tour/images/icons';

    // a selected handful of tile servers from leaflet-providers
    const TILE_SERVER_LIST = [
        'custom' => 'Custom URL',
        'other' => 'Other Leaflet Providers Tile Server',
        'Esri.WorldImagery' => 'Esri World Imagery',
        'OpenTopoMap' => 'OpenTopoMap',
        'OPNVKarte' => 'OPNVKarte',
        'Stamen.Toner' => 'Stamen Toner',
        'Stamen.TonerBackground' => 'Stamen Toner Background',
        'Stamen.TonerLight' => 'Stamen Toner - Light',
        'Stamen.Watercolor' => 'Stamen Watercolor',
        'Stamen.Terrain' => 'Stamen Terrain',
        'Stamen.TerrainBackground' => 'Stamen Terrain Background',
        'USGS.USTopo' => 'USGS: US Topo',
        'USGS.USImageryTopo' => 'USGS: US Imagery',
        'USGS.USImagery' => 'USGS: US Imagery Background',
    ];

    /**
     * Check if a key exists within an array.
     * - Return value if key exists in array, default value otherwise
     * 
     * @param array $list The array to check
     * @param string $key The key to check for
     * @param mixed $default The default value to use if the key does not exist
     * @return mixed
     */
    public static function get($list, $key, $default = null) {
        if (array_key_exists($key, $list)) return $list[$key];
        else return $default;
    }
    /**
     * Check if a key exists in an array and if a given function applied to its value returns true.
     * - Return value if key exists and function evaluates to true, default value otherwise
     * 
     * @param array $list The array to check
     * @param string $key The key to check for
     * @param function $callback The function to apply. Examples: is_numeric, is_int, is_bool, etc.
     * @param mixed $default The default value to use if the key does not exist or the function does not evaluate to true
     * @return mixed
     */
    public static function getType($list, $key, $callback, $default = null) {
        if (array_key_exists($key, $list) && $callback($list[$key])) return $list[$key];
        else return $default;
    }
    /**
     * Check if a key exists in an array and its value is a string.
     * - Return value if key exists and value is string, default value otherwise
     * 
     * @param array $list The array to check
     * @param string $key The key to check for
     * @param string|null $default The default value to use if the key does not exist or its value is not a string
     * @return string|null
     */
    public static function getStr($list, $key, $default = '') {
        if (array_key_exists($key, $list) && is_string($list[$key])) return $list[$key];
        else return $default;
    }
    /**
     * Check if a key exists within an array and its value is an array.
     * - Return value if key exists and value is array, default value otherwise
     * 
     * @param array $list The array to check
     * @param string $key The key to check for
     * @param array $default The default value to use if the key does not exist or its value is not an array
     * @return array
     */
    public static function getArr($list, $key, $default = []) {
        if (array_key_exists($key, $list) && is_array($list[$key])) return $list[$key];
        else return $default;
    }

    /**
     * When generating paths to images from other folders, need to make sure the path is absolute, not relative. Relative paths beginning with 'user/data' will only work when the page in question is a top-level page.
     * 
     * @return string '/top-level-folder' or '' (top-level-folder is where the pages, user, etc. folders are contained, assuming they are stored in their own folder)
     */
    public static function getRoot() {
        $page = Grav::instance()['page'];
        $root = str_replace($page->route(), '', $page->url());
        if ($root !== '/') return $root;
        else return '';
    }

    /**
     * Search a directory recursively for any files that have routes ending with the provided key.
     * - Result includes all files ending with the input key (and nothing else)
     * 
     * @param string $key The name of the template file (or other file, really) to look for. Passing 'dataset.md' would get any file ending in 'dataset.md'
     * @param array $results List of pages found so far. Only to be used when calling recursively.
     * @param null|string $dir Optional: Specify the directory to start the search in. Only to be used when calling recursively.
     * @return array A list of routes for all files found
     */
    public static function findTemplateFiles($key, $results = [], $dir = '') {
        if (!$dir) $dir = Grav::instance()['locator']->findResource('page://'); // can't pass as default, so doing this
        foreach (glob("$dir/*") as $item) {
            // Does the directory contain an item ending with the input key? If so, add it to the results
            if (str_ends_with($item, $key)) $results[] = $item;
            // Recursively search the directory's children for pages
            $results = self::findTemplateFiles($key, $results, $item);
        }
        return $results;
    }

    /**
     * Purely a getter for something from Grav. When called from the admin panel, the blueprint key contains all information needed to recreate the page path of the page currently being modified.
     * 
     * @return string
     */
    private static function getCurrentBlueprintKey() {
        return Grav::instance()['page']->header()->controller['key'];
    }
    /**
     * Processes input from the key(s) to check for a matching route in the file system. Deals with potential for files to have the folder numeric prefix option set.
     * - Returns the correct page for the key provided, if it exists (null otherwise)
     * - Works for pages with numeric prefix whether or not prefix is included in the key
     * 
     * @param string|array $key Can be the string or the already exploded string representing the page route
     * @param string $template_name The name of the markdown file to look for (do not include '.md')
     * @return MarkdownFile|null The file for the page if a route is found, null otherwise
     */
    public static function getPageFromKey($key, $template_name) {
        // use parse keys to find a valid route, if one exists, look in the pages folder, and break the key up into its individual components so each one can be checked
        $keys = $key;
        if (is_string($keys)) $keys = explode('/', $key);
        // recursive function
        $route = self::parseKeys(Grav::instance()['locator']->findResource('page://').'/', $keys);
        if ($route) {
            return MarkdownFile::instance($route . $template_name . '.md'); // build the file from the route
        }
        else return null;
    }
    /**
     * Recursive helper function: Checks the first key to see if there is a suitable match in the directory specified, then keeps going until all keys have been checked
     * 
     * @param string $dir The directory to search: Either the pages directory or the directory specified by the route so-far (when calling recursively)
     * @param array $keys The array of keys that have not yet been parsed
     * @return string|null The full route (as recognized by the file system) of the page if a route was found, null otherwise
     */
    private static function parseKeys($dir, $keys) {
        // if no keys are left then return the directory
        if (empty($keys)) return $dir;
        $key = $keys[0]; // only check the first key
        // check each item in the directory to see if any match the first key
        $test = '';
        foreach (glob($dir . '*') as $item_route) {
            $item = str_replace($dir, '', $item_route); // get just the folder or page, not the full route for the folder or page
            // check for case-insensitive match between the current folder or page and the key - if match is found, then the current item is the folder we want
            if ((strcasecmp($item, $key) === 0) || (strcasecmp(preg_replace('/^[0-9]+\./', '', $item), $key) === 0)) {
                // recursively call self to process any remaining keys
                return self::parseKeys("$item_route/", array_slice($keys, 1));
            } // otherwise keep looking
        }
        foreach (glob($dir . '*') as $item_route) {
            $test .= $item . '   ';
        }
        // if this stage is reached, then all items were searched and none matched the key, meaning the keys to not reference an existing route
        return null;
    }
    /**
     * Returns the dataset page currently being edited
     * 
     * @return MarkdownFile The file for the dataset page
     */
    public static function getDatasetFile() {
        $file = self::getPageFromKey(self::getCurrentBlueprintKey(), 'point_dataset');
        if (!$file || !$file->exists()) return self::getPageFromKey(self::getCurrentBlueprintKey(), 'shape_dataset');
        else return $file;
    }
    /**
     * Returns the tour page currently being edited
     * 
     * @return MarkdownFile The file for the tour page
     */
    public static function getTourFile() {
        return self::getPageFromKey(self::getCurrentBlueprintKey(), 'tour');
    }
    /**
     * Returns the tour page for the view page currently being edited
     * 
     * @return MarkdownFile|null The file for the tour page, or null if no such file exists
     */
    public static function getTourFileFromView() {
        try {
            $keys = explode('/', self::getCurrentBlueprintKey());
            array_pop($keys);
            $file = self::getPageFromKey($keys, 'tour');
            if ($file->exists()) return $file;
        } catch (\Throwable $t) {}
        return null;
    }
    /**
     * Returns the view page currently being edited
     * 
     * @return MarkdownFile|null The file for the view page, or null if no such file exists
     */
    public static function getViewFile() {
        return self::getPageFromKey(self::getCurrentBlueprintKey(), 'view');
    }

    /**
     * Checks if input is an array with two numeric inputs that fall in the correct range for longitude (-180 to 180) and latitude (-90 to 90)
     * - Input must be an array with two numeric values
     * - Values must be valid for longitude and latitude
     * 
     * @param array $coords Should be array with two floats (function validates type). First is longitude, second is latitude.
     * @return bool True if is valid, false if not
     */
    public static function isValidPoint($coords) {
        if (is_array($coords) && count($coords) === 2) {
            [$lng, $lat] = $coords;
            if (is_numeric($lng) && $lng >= -180 && $lng <= 180 && is_numeric($lat) && $lat >= -90 && $lat <= 90) return true;
        }
        return false;
    }
    /**
     * Checks if input is an array with keys north, south, east, and west, each pointing to a valid numeric value (lng/lat)
     * - Input must be an array with all four keys: north, south, east, west
     * - Returns modified array if valid (southwest, northeast) or null
     * 
     * @param array $bounds The (hopefully) array to check (function validates type)
     * @return null|array [[float, float], [float, float]] (southwest, northeast) if valid
     */
    public static function getBounds($bounds) {
        if (is_array($bounds) && count($bounds) >= 4) { // must be an array with four values, can handle extra values, though
            $bounds = [[Utils::get($bounds, 'south'), Utils::get($bounds, 'west')], [Utils::get($bounds, 'north'), Utils::get($bounds, 'east')]]; // values must have keys south, west, north, and east
            // have to reverse direction for checking for valid points
            if (self::isValidPoint([$bounds[0][1], $bounds[0][0]]) && self::isValidPoint([$bounds[1][1], $bounds[1][0]])) return $bounds; // southwest and northeast must form valid points
        }
        return null;
    }

    /**
     * Returns a string that would make an acceptable folder name
     * - Trims leading/trailing whitespace
     * - Replaces groups of whitespace with dash
     * - Changes capital letters to lowercase
     * - Removes special chars (only include letters, numbers, underscores, or dashes)
     * 
     * @param string $string The string to clean
     * @return string The cleaned string
     */
    public static function cleanUpString($string) {
        $output = strtolower(trim($string)); // remove whitespace on ends, make everything lowercase
        $output = preg_replace('/\s+/', '-', $output);  // replace whitespace with dashes
        $output = preg_replace('/[^a-z0-9_-]+/', '', $output); // remove special characters (anything not a letter, number, underscore, or dash)
        return $output;
    }
}
?>