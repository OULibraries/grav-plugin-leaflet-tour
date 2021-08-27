<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use Grav\Common\Data\Data;
use RocketTheme\Toolbox\File\File;
use Grav\Common\Filesystem\Folder;
use Grav\Common\File\CompiledJsonFile;
use RocketTheme\Toolbox\File\MarkdownFile;

/**
 * The Utils class is never instantiated as an object. Instead, it contains various functions that are generally useful for a leaflet tour that should not belong to one particular class.
 */
class Utils {

    /**
     * A list of all available GeoJSON feature types. This allows any string to be converted to lowercase and checked against this array, which means that, for example, lineString, LineString, and linestring will all be accepted features of type LineString.
     */
    const FEATURE_TYPES = [
        'point'=>'Point', // [x, y]
        // 'multipoint'=>'MultiPoint',
        'linestring'=>'LineString', // [[x,y], [x,y], [x,y]]
        'multilinestring'=>'MultiLineString', // [[[x,y], [x,y], [x,y]]]
        'polygon'=>'Polygon', // [[[x,y], [x,y], [x,y], [x,y]]]
        'multipolygon'=>'MultiPolygon' // [[[[x,y], [x,y], [x,y], [x,y]]]]
    ];
    /**
     * Feature Type Coordinates
     * LineString is an array of points (2 or more)
     * MultiLineString is an array of LineStrings (1 or more)
     * Polygon is basically a MultiLineString, but each "line" needs four points, with the first and last point being identical to each other
     * - the first entry in the array is the basic polygon, while additional entries can be used to add holes to the polygon
     * MultiPolygon is an array of polygons (1 or more)
     */

    /**
     * A regular expression used when parsing JavaScript dataset uploads. The purpose is to match out all of the content not part of the JSON data so the remaining content can be read as JSON.
     * ^.* - any number of 0 or more characters from the beginning of the file
     * var(\s)+ - the characters "var" with any amount of whitespace after it (must be at least one whitespace character)
     * json_(\w)* - the characters "json_" with any number of 0 or more word characters after it (a-z, A-Z, 0-9, _)
     * (\s)+=(\s)+ - one or more whitespace characters followed by "=" and then one or more whitespace characters
     * Simple example: In a file of "var json_my_dataset1 = { GeoJSON }" the regex should match "var json_my_dataset1 = ", leaving "{ GeoJSON }" to be parsed.
     */
    const JSON_VAR_REGEX = '/^.*var(\s)+json_(\w)*(\s)+=(\s)+/';

    /**
     * Default marker icon values if the default marker icon is used.
     * These are the defaults provided by Leaflet. In many cases they may not be necessary, since if iconAnchor is null Leaflet will fill in the details, but providing them here allows for simpler code when dealing with tour/dataset icon options.
     */
    const DEFAULT_MARKER_OPTIONS = [
        'iconUrl' => 'user/plugins/leaflet-tour/images/marker-icon.png',
        'iconSize' => [25, 41],
        'iconAnchor' => [12, 41],
        'tooltipAnchor' => [-12, 20],
        'shadowUrl' => 'user/plugins/leaflet-tour/images/marker-shadow.png',
        'shadowSize' => [41, 41],
        'iconRetinaUrl' => 'user/plugins/leaflet-tour/images/marker-icon-2x.png',
        'className' => 'leaflet-marker'
    ];
    /**
     * Default values if the default marker icon is not used.
     * Not much is provided here - just enough to display an icon and tooltip reasonably if nothing else is customized.
     */
    const MARKER_FALLBACKS = [
        'iconSize' => [14, 14],
        'iconAnchor' => [],
        'tooltipAnchor' => [7, 0],
        'shadowSize' => [],
    ];

    // routes for ease of reference
    const BASEMAP_ROUTE = 'user/data/leaflet-tour/images/basemaps/';
    const IMAGE_ROUTE = 'user/data/leaflet-tour/images/';

    /**
     * List of tile servers provided by this plugin.
     *  - key is what will be recorded in the YAML file to indicate the current selection
     *  - type will become relevant when there are more than just Stamen maps
     *  - select is the text that will be shown to the user in the admin panel dropdown
     *  - name is how the individual Stamen map is selected in the JavaScript code
     *  - attribution is HTML content provided by the map provider
     * Currently only has the three Stamen maps.
     */
    const TILE_SERVERS = [
        'stamenWatercolor' => [
            'type'=>'stamen',
            'select'=>'Stamen Watercolor',
            'name'=>'watercolor',
            'attribution'=>'Map tiles by <a href="http://stamen.com">Stamen Design</a>, under <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>. Data by <a href="http://openstreetmap.org">OpenStreetMap</a>, under <a href="http://creativecommons.org/licenses/by-sa/3.0">CC BY SA</a>.',
        ],
        'stamenToner' => [
            'type' => 'stamen',
            'select' => 'Stamen Toner',
            'name' => 'toner',
            'attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, under <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>. Data by <a href="http://openstreetmap.org">OpenStreetMap</a>, under <a href="http://www.openstreetmap.org/copyright">ODbL</a>.',
        ],
        'stamenTerrain' => [
            'type' => 'stamen',
            'select' => 'Stamen Terrain',
            'name' => 'terrain',
            'attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, under <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>. Data by <a href="http://openstreetmap.org">OpenStreetMap</a>, under <a href="http://www.openstreetmap.org/copyright">ODbL</a>.',
        ],
    ];

    // GeoJson Utils - Utility functions for dealing with coordinates and GeoJSON feature types

    /**
     * Checks that coordinates are an array of [longitude, latitude].
     * @param $coords - The variable to test. The function will accept any type, but will return false for anything but an array. Longitude and latitude values must be in degrees.
     * @param bool $reverse - By default the function expects longitude first. Use $reverse to indicate that latitude should be first.
     * @return bool - true if $coords is a valid point, false otherwise
     */
    protected static function isValidPoint($coords, bool $reverse = false): bool {
        if (!is_array($coords) || count($coords) !== 2) return false; // must be array with length of two
        if ($reverse) {
            // expect [latitude, longitude]
            $long = $coords[1];
            $lat = $coords[0];
        }
        else {
            // expect [longitude, latitude]
            $long = $coords[0];
            $lat = $coords[1];
        }
        if (!is_numeric($long) || $long < -180 || $long > 180) return false; // longitude must be a number between -180 and 180
        if (!is_numeric($lat) || $lat < -90 || $lat > 90) return false; // latitude must be a number between -90 and 90
        return true; // all checks passed
    }

    /**
     * Checks that coordinates are an array with one or more points
     */
    /*public static function isValidMultiPoint($coords): bool {
        if (!is_array($coords) || empty($coords)) return false;
        foreach ($coords as $point) {
            if (!self::isValidPoint($point)) return false;
        }
        return true;
    }*/

    /**
     * Check that coordinates are any array with two or more points.
     * @param $coords - The variable to test. The function will accept any type, but will return false for anything but an array. Longitude and latitude values must be in degrees.
     * @return bool - true if $coords is a valid LineString, false otherwise
     */
    protected static function isValidLineString($coords): bool {
        if (!is_array($coords) || count($coords) < 2) return false; // must be an array with length of 2 or greater
        foreach ($coords as $point) {
            if (!self::isValidPoint($point)) return false; // every item in the array must be a Point
        }
        return true; // all checks passed
    }

    /**
     * Checks that coordinates are an array of one or more LineStrings.
     * @param $coords - The variable to test. The function will accept any type, but will return false for anything but an array. Longitude and latitude values must be in degrees.
     * @return bool - true if $coords is a valild MultiLineString, false otherwise
     */
    protected static function isValidMultiLineString($coords): bool {
        if (!is_array($coords) || count($coords) < 1) return false; // must be an array with at least one item
        foreach ($coords as $line) {
            if (!self::isValidLineString($line)) return false; // every item in the array must be a LineString
        }
        return true; // all checks passed
    }

    /**
     * Checks that coordinates are an array of linear rings (line strings with four or more points where the first and last points are the same).
     */
    /*public static function isValidPolygon($coords): bool {
        if (!is_array($coords) || count($coords) < 1) return false; // must be an array with at least one item
        foreach ($coords as $polygon) {
            if (!self::isValidLineString($polygon) || count($polygon) < 4 || ($polygon[0] !== $polygon[count($polygon)-1])) return false; // every item must be a valid linear ring
        }
        return true; // all checks passed
    }*/

    /**
     * Checks that coordinates are an array with one or more polygons.
     * @param $coords - The variable to test. The function will accept any type, but will return false for anything but an array. Longitude and latitude values must be in degrees.
     * @return bool - true if $coords is a valid MultiPolygon, false otherwise
     */
    /*public static function isValidMultiPolygon($coords): bool {
        if (!is_array($coords) || count($coords) < 1) return false;
        foreach ($coords as $polygon) {
            if (!self::isValidPolygon($polygon)) return false;
        }
        return true;
    }*/

    // TODO: Replace isValidPolygon and isValidMultiPolygon with the new "set" functions

    /**
     * Checks that coordinates are an array of linear rings (line strings with four or more points where the first and last points are the same).
     * If the first and last points are not the same, the first point will be added to the end.
     * @param $coords - The variable to test. The function will accept any type, but will return false for anything but an array. Longitude and latitude values must be in degrees.
     * @return array|null - The valid coordinates if possible, otherwise null
     */
    protected static function setValidLinearRing($coords): ?array {
        if (!self::isValidLineString($coords) || count($coords) < 3) return null; // must be an array of points with at least three values to start with
        if ($coords[0] !== $coords[count($coords)-1]) $coords[] = $coords[0]; // Check first and last points, add first to the end if needed
        if (count($coords) < 4) return null; // this will only fail if an array of three points was provided, but the first and last points were already the same
        return $coords;
    }

    /**
     * Checks that coordinates are an array with one or more linear rings (line strings with four or more points where the first and last points are the same). For each linear ring, if first and last points are not the same, the first point will be added to the end.
     * @param $coords - The variable to test. The function will accept any type, but will return false for anything but an array. Longitude and latitude values must be in degrees.
     * @return array|null - The valid coordinates if possible, otherwise null
     */
    protected static function setValidPolygon($coords): ?array {
        if (!is_array($coords) || count($coords) < 1) return null; // must be an array with at least one item
        $newCoords = []; // new variable to store coordinates in, just in case
        foreach ($coords as $linearRing) {
            $ring = self::setValidLinearRing($linearRing);
            if ($ring === null) return null; // no invalid linear rings allowed
            else $newCoords[] = $ring;
        }
        return $newCoords;
    }

    /**
     * Checks that coordinates are an array with one or more polygons. Will correct any linear rings that do not have matching first and last points.
     * @param $coords - The variable to test. The function will accept any type, but will return false for anything but an array. Longitude and latitude values must be in degrees.
     * @return array|null - The valid coordinates if possible, otherwise null
     */
    protected static function setValidMultiPolygon($coords): ?array {
        if (!is_array($coords) || count($coords) < 1) return false; // must be an array with at least one item
        $newCoords = []; // new variable to store coordinates in, just in case
        foreach ($coords as $polygon) {
            $poly = self::setValidPolygon($polygon);
            if ($poly === null) return null; // no invalid polygons allowed
            else $newCoords[] = $poly;
        }
        return $newCoords;
    }

    /*public static function areValidCoordinates($coords, $type): bool {
        $type = self::setValidType($type); // just in case
        switch ($type) {
            case 'Point': return self::isValidPoint($coords);
            //case 'MultiPoint': return self::isValidMultiPoint($coords);
            case 'Polygon': return self::isValidPolygon($coords);
            case 'MultiPolygon': return self::isValidMultiPolygon($coords);
            case 'LineString': return self::isValidLineString($coords);
            case 'MultiLineString': return self::isValidMultiLineString($coords);
            default: return false;
        }
    }*/
    
    /**
     * Checks that the coordinates provided are valid for the GeoJSON type provided. (If the type is invalid, will default to Point). Corrects any linear rings that do not have matching first and last points.
     * @param $coords - The variable to test. The function will accept any type, but will return false for anything but an array. Longitude and latitude values must be in degrees.
     * @param string $type - The GeoJSON type to use. Accepts: Point, Polygon, MultiPolygon, LineString, MultiLineString
     * @return array|null - Returns the coordinates if they are valid, otherwise null.
     */
    public static function setValidCoordinates($coords, string $type): ?array {
        $type = self::setValidType($type); // just in case
        switch ($type) {
            case 'Point':
                if (self::isValidPoint($coords)) return $coords;
                else return null;
            case 'LineString':
                if (self::isValidLineString($coords)) return $coords;
                else return null;
            case 'MultiLineString':
                if (self::isValidMultiLineString($coords)) return $coords;
                else return null;
            case 'Polygon': return self::setValidPolygon($coords);
            case 'MultiPolygon': return self::setValidMultiPolygon($coords);
            default: return null;
        }
    }

    /**
     * @param string $type - A string that may or may not be a valid GeoJSON type. Valid options (not case-sensitive) are included in the class constant FEATURE_TYPES.
     * @return string - If the type is valid, returns the properly capitalized version. Otherwise returns 'Point'.
     */
    public static function setValidType(string $type): string {
        $validType = self::FEATURE_TYPES[strtolower($type)];
        if (!$validType) $validType = 'Point';
        return $validType;
    }

    /**
     * Takes an array with four values (keys of north, south, east, and west) and ensure that is a valid bounds array.
     * @param $bounds - Accepts any type, but will return null for anything that is not an associative array
     * @return array|null - Returns valid bounds array (indexed, not associative) [[south, west], [north, east]]. The returned array has points that begin with latitude, not longitude, as this is what Leaflet accepts when setting bounds.
     */
    public static function setBounds($bounds): ?array {
        if (is_array($bounds) && count($bounds) === 4) { // must be an array with four values
            $bounds = [[$bounds['south'], $bounds['west']], [$bounds['north'], $bounds['east']]]; // values must have keys south, west, north, and east
            if (self::isValidPoint($bounds[0], true) && self::isValidPoint($bounds[1], true)) return $bounds; // southwest and northeast must form valid points
        }
        return null;
    }

    /**
     * Adds a given amount to existing latitude (in degrees).
     * @param float $lat - The latitude to modify. This should be a valid latitude!
     * @param float $amount - The amount by which to increase/decrease the latitude
     * @return float - The modified latitude
     */
    // TODO: test
    public static function addToLat(float $lat, float $amount): float {
        $amount = fmod($amount, 180); // Don't add or subtract by more than 180. Then the range of possible values is -270 to 270 after initial addition.
        $result = $lat + $amount;
        if ($result < 90 && $result > -90) return $result; // The amount added did not move the latitude outside of valid range, so no additional work is needed.
        else if ($result > 90) return $result - 180; // Value could be anywhere from 90+ to 270. Subtracting 180 ensures that this range becomes -90 to 90. It also ensures that the value wraps the way it should (e.g. 120 becomes -60, as would be expected).
        else return $result + 180; // Same general idea as before, but the value is negative, so 180 needs to be added, not subtracted.
    }

    /**
     * Adds a given amount to existing longitude (in degrees).
     * @param float $long - The longitude to modify. This should be a valid longitude!
     * @param float $amount - The amount by which to increase/decrease the longitude
     * @return float - The modified longitude
     */
    // TODO: test
    public static function addToLong($long, $amount) {
        $amount = fmod($amount, 360); // Don't add or subtract by more than 360. Then the range of possible values is -540 to 540 after initial addition.
        $result = $long + $amount;
        if ($result < 180 && $result > -180) return $result; // The amount added did not move the longitude outside of valid range, so no additional work is needed.
        else if ($result > 180) return $result - 360; // Value could be anywhere from 180+ to 540. Subtracting 360 ensures that this range becomes -180 to 180. It also ensures that the value wraps the way it should (e.g. 210 becomes -150, as would be expected).
        else return $result + 360; // Same general idea as before, but the value is negative, so 360 needs to be added, not subtracted.
    }

    // Route Utils - Utility functions for finding page routes, because I couldn't find what I needed in Grav (entirely likely that it exists, though)
    
    /**
     * Takes an array of folder/page names and returns a route, taking into account the possibility that folder numeric prefix is enabled (which greatly complicates matters).
     * @param array $keys - An array of folder/page names. The final page name (e.g. default.md or tour.md) should not be included.
     * @return string - The route if one was found, otherwise an empty string.
     */
    public static function getPageRoute(array $keys): string {
        $route = Grav::instance()['locator']->findResource('page://').'/'; // user/pages/ folder route
        for ($i = 0; $i < count($keys); $i++) {
            $glob = glob($route.'*'); // glob is case-sensitive, and it's probably best for this not to be
            $match = '';
            foreach ($glob as $item) {
                if (strcasecmp($item, $route.$keys[$i]) === 0) {
                    $match = $item;
                    continue;
                }
            }
            // check for folder numeric prefix - have to use a different method to test equality because we need to use a regular expression
            if (empty($match)) {
                foreach ($glob as $item) {
                    if (preg_match('/^[0-9][0-9]\.'.strtolower($keys[$i]).'$/', strtolower(str_replace($route, '', $item)))) {
                        $match = $item;
                        continue;
                    }
                }
            }
            if (empty($match)) return '';
            $route = $match.'/';
        }
        return $route;
    }
    
    /**
     * Gets the route for the dataset.md page when on the corresponding admin page editor.
     * @return string - Route to dataset.md page
     */
    public static function getDatasetRoute(): string {
        $key = Grav::instance()['page']->header()->controller['key']; // current page - the reason why this function only works when called from dataset.yaml
        return self::getPageRoute(explode("/", $key)).'dataset.md';
    }

    /**
     * Gets the route for the tour.md page when on the corresponding admin page editor.
     * @return string - Route to tour.md page
     */
    public static function getTourRouteFromTourConfig(): string {
        $key = Grav::instance()['page']->header()->controller['key']; // current page - the reason why this function only works when called from tour.yaml
        return self::getPageRoute(explode("/", $key)).'tour.md';
    }

    /**
     * Gets the route for the tour.md page when on the admin page editor for one of its views.
     * @return string - Route to tour.md page
     */
    public static function getTourRouteFromViewConfig(): string {
        $keys = explode("/", Grav::instance()['page']->header()->controller['key']); // current page - the reason why this function only works when called from view.yaml
        array_pop($keys); // last element is view folder, which we don't want
        if (count($keys) > 0) return self::getPageRoute($keys).'tour.md';
        else return '';
    }

    // Dataset Upload Utils - Utility functions for handling new/removed/updated dataset files after the plugin config is saved (specifics of dataset creation will be handled by the Dataset class)

    /**
     * Checks the data for a new file upload, creates the json filename that will be used to refer to it, and sends it to the appropriate parsing function based on file type.
     * @param array $fileData - the info added to the plugin config file when a file is uploaded [name, type, size, path] (size isn't used, here)
     * @return array - An array containing the array of json data and the json filename, or an empty array if nothing was generated
     */
    public static function parseDatasetUpload($fileData): array {
        // fix php's bad json handling
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set( 'serialize_precision', -1 );
        }
        $jsonFilename = preg_replace('/.js$/', '.json', $fileData['name']); // if .js file, change the name to a json file (will be used when creating the new json file)
        $jsonFilename = str_replace(' ', '-', $jsonFilename); // remove any spaces from the filename
        $jsonArray = [];
        try {
            switch ($fileData['type']) {
                case 'text/javascript':
                    $jsonArray = self::parsJsFile($fileData['path']);
                    break;
                case 'application/json':
                    $jsonArray = self::parseJsonFile($fileData['path']);
                    break;
                default:
                    break;
            }
            // Future option: Check for other file types
            if (empty($jsonArray)) return [];
            return [$jsonArray, $jsonFilename];
        } catch (\Throwable $t) {
            return [];
        }
    }

    /**
     * Called after a dataset upload has been removed. Searches the current Dataset list for one created using that upload. If found, makes a copy of the dataset.md page as a default page in the deleted_datasets folder, removes the associated json file, and removes the dataset.md page and the folder that contains it.
     */
    public static function deleteDatasets() {
        $datasetFiles = [];
        // get list of jsonFilenames
        foreach ((Grav::instance()['config']->get('plugins.leaflet-tour.data_files') ?? []) as $key=>$fileData) {
            $datasetFiles[] = str_replace(' ', '-', preg_replace('/.js$/', '.json', $fileData['name']));
        }
        // loop through datasets and look for ones that don't belong
        foreach (Dataset::getDatasets() as $key=>$dataset) {
            if (!in_array($key, $datasetFiles)) {
                // move dataset page to deleted_datasets
                $dataset = Dataset::getDatasets()[$key];
                $mdFile = MarkdownFile::instance($dataset->getDatasetRoute());
                $mdFile->filename(Grav::instance()['locator']->findResource('page://').'/deleted_datasets/'.$dataset->getName().'/default.md');
                $mdFile->save();
                // delete the json file
                Dataset::getJsonFile($key)->delete();
                // delete the old dataset page
                Folder::delete(str_replace('/dataset.md','', $dataset->getDatasetRoute()));
            }
        }
    }
    
    /**
     * Turn qgis2web .js file into .json
     * 
     * @param string $filePath - path to the js file
     * @return array - returns array with json data on success, empty array on failure
     */
    protected static function parseJsFile(string $filePath): array {
        $file = File::instance(Grav::instance()['locator']->getBase().'/'.$filePath);
        if (!$file->exists()) return [];
        // find and remove the initial json variable
        $count = 0;
        $jsonRegex = preg_replace(self::JSON_VAR_REGEX.'s', '', $file->content(), 1, $count);
        if ($count !== 1) $jsonRegex = preg_replace(self::JSON_VAR_REGEX, '', $file->content(), 1, $count); // not sure why this might be necessary sometimes, but I had a file giving me trouble without it
        // if a match was found (and removed), try converting the file contents to json
        if ($count == 1) {
            try {
                $jsonData = json_decode($jsonRegex, true);
                return $jsonData ?? [];
            } catch (\Exception $e) {
                return [];
            }
        }
        return [];
    }

    /**
     * Doesn't have to do much - just read the content of a json file and return it
     * @param string $filePath - path to the uploaded json file
     * @return array - json content on success, empty array on failure
     */
    protected static function parseJsonFile(string $filePath): array {
        $file = CompiledJsonFile::instance(Grav::instance()['locator']->getBase().'/'.$filePath);
        if (!$file->exists()) return [];
        return $file->content() ?? [];
    }

    // Other Utils

    /**
     * Creates a list of popup buttons for all features contained in a view. This list will be saved in the view.md header so it can be referenced when creating/modifying view content.
     * @param array $features - list of all features from the view header
     * @return string - A string with one shortcode per line
     */
    public static function generateShortcodeList(array $features): string {
        $shortcodes = [];
        $features = array_column($features, 'id'); // change features from array of ['id'=>'feature_id',...] to ['feature_id',...] for easier reference
        foreach (Dataset::getDatasets() as $dataset) {
            foreach($features as $id) {
                $feature = $dataset->getFeatures()[$id];
                if ($feature) {
                    $shortcodes[] = '[view-popup id="'.$id.'"]'.$feature->getName($dataset->getNameProperty()).'[/view-popup]'; // shortcode text
                }
            }
        }
        return implode("\r\n", $shortcodes);
    }

    /**
     * Creates/modifies the associated popups page when a tour is saved. The popups page will contain a shortcode referring to the tour, which can then be used to grab all popup content for features included in the tour. Must be called from the admin panel tour page editor.
     * @param string $tourTitle
     */
    public static function createPopupsPage(string $tourTitle): void {
        $uri = Grav::instance()['uri'];
        $mdFile = MarkdownFile::instance(Grav::instance()['locator']->findResource('page://').'/popups/'.$uri->basename().'/default.md'); // page folder will exist in user/pages/popups/, page folder will be the same as the tour folder, page template will be default
        // add link
        $content ="<a href='".str_replace("/admin/pages", "", $uri->route(true, true))."' class='btn'>Return to Tour</a>"; // first: link back to the associated tour - requires the url used to access the tour page from the internet
        // add shortcode
        $content .="\n\n".'[list-tour-popups route="'.self::getTourRouteFromTourConfig().'"][/list-tour-popups]'; // add shortcode referencing the tour - requires the url that serves as the actual route to the tour page from the file system
        $mdFile->markdown($content);
        // set title
        $mdFile->header(['title'=>"Popup Content for $tourTitle", 'visible'=>0]); // page should not be visible from the main navigation
        $mdFile->save();
    }

    /**
     * Finds all features in the tour that have popup content. Called by the list-tour-popups shortcode.
     * @param string $tourRoute - the route provided in the shortcode
     * @return array - [id=>[name, popup]] for all features with popups in the tour
     */
    public static function getAllPopups(string $tourRoute): array {
        $tourHeader = new Data((array)(MarkdownFile::instance($tourRoute)->header())); // get the tour data
        $popups = []; // [id => name, popup]
        $tourFeatures = array_column($tourHeader->get('features') ?? [], null, 'id'); // create index for tour features list using the "id" column
        foreach ($tourHeader->get("datasets") ?? [] as $dataset) { // loop through tour datasets to pull out content
            $showAll = $dataset['show_all'];
            $dataset = Dataset::getDatasets()[$dataset['file']];
            // add all features
            foreach ($dataset->getFeatures() as $featureId => $feature) { // loop through all features in each dataset
                if ($showAll || $tourFeatures[$featureId]) { // check if feature is included in the tour - either the dataset has show_all enabled, or the feature is included in the tour features list
                    $tourFeature = $tourFeatures[$featureId];
                    $popup = $feature->getPopup(); // get the popup for the Feature in the Dataset
                    if ($tourFeature) { // if the feature is in the tour, check for popup content override/removal
                        if (!empty($tourFeature['popup_content'])) $popup = $tourFeature['popup_content'];
                        else if ($tourFeature['remove_popup']) $popup = null;
                    }
                    if (!empty($popup)) { // if popup content was found, add to the popups array
                        $popups[$featureId] = [
                            'name' => $feature->getName(),
                            'popup' => $popup,
                        ];
                    }
                }
            }
        }
        return $popups;
    }

    /**
     * Because PHP's array_filter function is really stupid, and it won't even accept isset as a callback!
     * Figured I might as well just put the nested functionality in here, too.
     * @param array $array - the array to filter (function will return an empty array if no array is provided)
     * @return array - the new, filtered array
     */
    public static function array_filter($array): array {
        $newArray = [];
        foreach($array ?? [] as $key=>$item) {
            if (is_array($item)) $item = self::array_filter($item); // recursively filter all sub-arrays
            else if ($item === null) continue; // ignore any content that is null
            if (is_array($item) && empty($item)) continue; // ignore any empty arrays
            $newArray[$key] = $item; // for non-empty array and non-null items, add the item to the new array
        }
        return $newArray;
    }

    /**
     * Function to be called when dataset, tour, or view pages are saved from the admin panel to make sure their headers are filtered. Otherwise they can be quite messy indeed. This is also especially necessary for tours, as included null values end up overwriting non-null dataset values when options are merged.
     * @param Header $header - the Header object of the page
     * @return Header - the modified Header object
     */
    public static function filter_header($header) {
        foreach ($header->toArray() as $key=>$item) {
            if (is_array($item)) $item = Utils::array_filter($item); // filter all arrays in the header
            $header->set($key, $item);
        }
        return $header;
    }
}

?>