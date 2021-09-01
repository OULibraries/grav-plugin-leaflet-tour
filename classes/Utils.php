<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use Grav\Common\Data\Data;
use RocketTheme\Toolbox\File\File;
use Grav\Common\Filesystem\Folder;
use Grav\Common\File\CompiledJsonFile;
use RocketTheme\Toolbox\File\MarkdownFile;

// TODO: Note that for update properties - coordinates and other properties kind of assume that these are being used as unique identifiers. Currently, you can't use this to remove all features that have a value of x for property y, just as you cannot set multiple properties as the identifier or anything else so complicated.

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

    const MSGS = [
        'no_dataset_selected'=>'Select an existing dataset to update.',
        'no_dataset_prop'=>'Select a property from the existing dataset to identify features to update.',
        'invalid_dataset_prop'=>'The property selected is not a valid property for the dataset. Select a property from this dataset to identify features to update.',
        'no_update_prop'=>'Indicate that the same property selected for the dataset will be used to identify features in the update file, or provide a different property for this pupose.',
        'invalid_json'=>'The uploaded file is invalid. Provide an update file with valid json, as described in the readme.', // Note: doesn't necessarily have to provide valid geojson data.

        'issue_list'=>"The following issues have been identified. Correct them and try saving again, or cancel the update:\r\n",
        
        'update_warning'=>'Warning! Once confirmed, the update cannot be undone. Please read the information below carefully before making changes.',
        'update_confirm_instructions'=>'To complete the update, toggle the Confirm option below. To cancel the update, toggle the Cancel option instead.',

        'update_replace'=>"You have chosen a total dataset replacement. All content from the original file upload will be removed and replaced with the new file content.\r\n",
        'no_dataset_name'=>'Because no dataset name has been specified, the old name will be preserved',
        'dataset_name_prop'=>'If valid, the old name property setting will be preserved',
        'prop_coords'=>'Custom names and popup content for features with matching coordinates will be preserved.',
        'prop_props'=>'Custom names and popup content for features with matching properties for the chosen properties in the original upload in the update file will be preserved.',
        'replace_no_prop'=>'Warning! No valid properties have been selected for preserving features. All custom names and popup content will be removed. Features selected from the old dataset in tours or views will no longer be valid.',
        'replace_no_matches'=>'No feature matches were found between the original dataset and the update file. All custom names and popup content will be removed. Features selected from the old dataset in tours or views will no longer be valid.',
        'replace_matches'=>'Matches were found for the following features. Custom names and popup content for only these features will be preserved. Only these features will remain as valid selections in tours or views:',

        'update_remove'=>'You have chosen to remove all features that match the features provided in the update file.',
        'remove_no_matches'=>'No feature matches were found between the original dataset and the update file. No features will be removed.',
        'remove_matches'=>'The following features will be removed:',

        'update_standard'=>'You have chosen a standard update with the following options:',
        'modify_existing'=>'Existing features will be modified based on matching features in the update file.',
        'overwrite_blank'=>' Any values/properties that are blank in the update file will replace existing content in the dataset.',
        'not_overwrite_blank'=>' Any values/properties that are blank in the update file will be ignored.',
        'add_new'=>'Features from the update file that do not have a match in the existing dataset will be added as new features.',
        'remove_empty'=>'Features from the existing dataset that do not have a match in the update file will be removed from the existing dataset.',
        'modify_existing_no_matches'=>'No feature matches were found between the original dataset and the update file. No features will be updated.',
        'modify_existing_matches'=>'The following features will be updated:',
        'add_new_none'=>'No new features were found in the update file.',
        'add_new_some'=>'The following new features will be added:',
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
                    $jsonArray = self::parseJsFile($fileData['path']);
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

    /**
     * Called when a file has been detected to be updated, this function ensures that all the necessary settings have been checked, that the user is given a confirmation message before continuing, and that the update occurs.
     * @param array $update - the update settings from the plugin config page
     * @param array $prevUpdate - the saved update settings from the plugin config (not yet replaced)
     * @return array - modified update settings to be merged with the plugin config object before saving
     */
    public static function handleDatasetUpdate(array $update, array $prevUpdate): array {
        $updateFile = $update['file'][array_keys($update['file'])[0]] ?? []; // have to make sure we grab the first one, even though there can only be one
        $issues = []; // for storing any issues found to easily return them to the user
        if ($update['cancel']) return self::clearUpdate($updateFile['path'] ?? '');
        // if update status is confirm, confirm and complete the update
        if ($update['status'] === 'confirm') {
            $changed = false; // make sure no settings have changed
            $keys = ['file', 'dataset', 'type', 'dataset_prop']; // always check these settings
            if ($update['dataset_prop'] !== 'tour_none' && $update['dataset_prop'] !== 'tour_coords') {
                $keys = array_merge($keys, ['same_prop']); // same_prop only matters if dataset_prop is set to a property
                if (!$update['same_prop']) $keys = array_merge($keys, ['file_prop']); // file_prop only matters if same_prop matters and same_prop is false
            }
            if ($update['type'] === 'standard') $keys = array_merge($keys, ['modify_existing', 'add_new', 'remove_empty', 'overwrite_blank']); // these settings only apply for standard updates
            foreach ($keys as $key) {
                if ($update[$key] !== $prevUpdate[$key]) { // an important setting has changed
                    $changed = true;
                    break;
                }
            }
            if (!$changed && $update['confirm']) {
                // no important settings have changed and the user has confirmed the update - complete the update
                try {
                    $jsonFile = Dataset::getJsonFile($update['dataset']);
                    $tmpFile = CompiledJsonFile::instance(Grav::instance()['locator']->findResource('user-data://').'/leaflet-tour/datasets/update/tmp.json');
                    $jsonFile->content($tmpFile->content());
                    $jsonFile->save();
                    Dataset::resetDatasets();
                    Dataset::getDatasets()[$update['dataset']]->saveDatasetPage();
                    // TODO: ensure that props list will be regenerated
                    return self::clearUpdate($updateFile['path'] ?? '');
                } catch (\Throwable $t) {
                    $issues[] = 'Issue Updating: '.$t->getMessage();
                }
            }
            else if (!$changed) return $update; // nothing has changed, but the update has been neither confirmed nor canceled - all settings should remain the same until the user changes them
        }
        // set some variables
        $issues = []; // for storing any issues found to easily return them to the user
        $dataset = Dataset::getDatasets()[$update['dataset']];
        $propertySettings = [];
        $datasetProp = $update['dataset_prop'];
        if ($datasetProp === 'tour_coords') $propertySettings = ['tour_coords'];
        else if (!empty($dataset) && !empty($dataset->getProperties()[$datasetProp])) {
            $updateProp = $update['same_prop'] ? $datasetProp : $update['file_prop'];
            if (!empty($updateProp)) $propertySettings = [$datasetProp, $updateProp];
        }
        $parsedJsonData = self::parseDatasetUpload($updateFile); // have  to make sure we grab the first one, even though there can only be one
        // checks
        if (empty($dataset)) $issues[] = self::MSGS['no_dataset_selected'];
        if (empty($propertySettings) && $update['type'] !== 'replace') {
            if ($datasetProp === 'tour_none') $issues[] = self::MSGS['no_dataset_prop'];
            else if (!empty($dataset) && empty($dataset->getProperties()[$datasetProp])) $issues[] = self::MSGS['invalid_dataset_prop'];
            if (!$update['same_prop'] && empty($update['file_prop'])) $issues[] = self::MSGS['no_update_prop'];
        }
        if (empty($parsedJsonData)) $issues[] = self::MSGS['invalid_json'];

        if (empty($issues)) {
            $datasetFeatures = $dataset->getFeatures() ?? [];
            try {
                // create detailed message noting the chosen options and what will be changed, as well as a temporary json file so that the work of checking and modifying various features does not have to be redone when actually updating
                [$jsonArray, $jsonFilename] = $parsedJsonData;
                //$jsonFeatures = (new Data($jsonArray))->get('features') ?? []; // TODO: JsonData neded?
                $jsonFeatures = $jsonArray['features'] ?? [];
                switch ($update['type']) {
                    case 'replace':
                        [$msg, $tmpJson] = self::updateReplace($dataset, $jsonArray, $propertySettings);
                        break;
                    case 'remove':
                        [$msg, $tmpJson] = self::updateRemove($dataset, $jsonArray['features'], $propertySettings);
                        break;
                    default:
                        [$msg, $tmpJson] = self::updateStandard($update, $dataset, $jsonArray['features'] ?? [], $propertySettings);
                }
                // save the tmp json file
                $tmpJsonFile = CompiledJsonFile::instance(Grav::instance()['locator']->findResource('user-data://').'/leaflet-tour/datasets/update/tmp.json');
                $tmpJsonFile->content($tmpJson);
                $tmpJsonFile->save();
                // prep message, settings
                $msg = self::MSGS['update_warning']."\r\n\r\n".$msg."\r\n".self::MSGS['update_confirm_instructions'];
                return ['msg'=>$msg, 'confirm'=>false, 'status'=>'confirm'];
            } catch (\Throwable $t) {
                $issues[] = $t->getMessage();
            }
        }
        // issues exist
        $msg = self::MSGS['issue_list'];
        foreach ($issues as $issue) {
            $msg .= "\t- $issue\r\n";
        }
        return ['msg'=>$msg, 'confirm'=>false, 'status'=>'corrections'];
    }

    /**
     * Removes uploaded and temporary files, as applicable. Also returns an array that will reset update settings to their defaults.
     * @param string $filepath - the path to the uploaded update file
     * @return array - empty update settings
     */
    protected static function clearUpdate(string $filepath): array {
        // remove update file
        $file = File::instance(Grav::instance()['locator']->getBase().'/'.$filepath);
        if ($file->exists()) $file->delete();
        // remove temporary file
        $file = File::instance(Grav::instance()['locator']->findResource('user-data://').'/leaflet-tour/datasets/update/tmp.json');
        if ($file->exists()) $file->delete();
        // return empty/default update settings array
        return [
            'msg'=>'Upload a file, select options, and save to begin.',
            'status'=>'none',
            'confirm'=>false,
            'cancel'=>false,
            'dataset'=>null,
            'file'=>null,
            'dataset_prop'=>'none',
            'same_prop'=>true,
            'file_prop'=>null,
            'type'=>'standard',
            'file'=>[]
        ];
    }

    /**
     * Handles replace update type, which replaces all features from the old dataset with features features from the update file.
     * @param Dataset $dataset - the Dataset object associated with the chosen dataset
     * @param array $jsonArray - json content from the update file
     * @param array $propertySettings - settings for identifying matches between original dataset and update file
     * @return array - [$msg, $datasestJson] - the msg to present to the user informing them of what changes will occur, and the json data to save in the tmp file for easier updating once the update is confirmed
     */
    protected static function updateReplace(Dataset $dataset, array $jsonArray, array $propertySettings) {
        $msg = self::MSGS['update_replace'];
        // check for name and name property
        if (empty($jsonArray['name'])) {
            $jsonArray['name'] = $dataset->getName();
            $msg .= "\t- ".self::MSGS['no_dataset_name'].' ('.$dataset->getName().").\r\n";
        }
        $jsonArray['nameProperty'] = $dataset->getNameProperty();
        $msg .= "\t- ".self::MSGS['dataset_name_prop'].' ('.$dataset->getNameProperty().").\r\n";
        $jsonArray['featureCounter'] = $dataset->getFeatureCounter(); // prevents different features with same ids as deleted features
        // check for property settings - not required, but can be useful for preserving some content
        switch (count($propertySettings)) {
            case 1:
                $msg .= self::MSGS['prop_coords']."\r\n";
                break;
            case 2:
                $msg .= self::MSGS['prop_props']."\r\n";
                break;
            default:
                $msg .= self::MSGS['replace_no_prop'];
        }
        if (!empty($propertySettings)) {
            // possibility that some features will be preserved
            $datasetFeatures = $dataset->getFeatures();
            [$featureMatches, $jsonFeatures] = self::matchFeatures($datasetFeatures, $jsonArray['features'], $propertySettings);
            if (empty($featureMatches)) $msg .= "\r\n".self::MSGS['replace_no_matches'];
            else {
                $msg .= "\r\n".self::MSGS['replace_matches']."\r\n".self::listFeatures($featureMatches, $datasetFeatures);
                $jsonArray['features'] = $jsonFeatures;
            }
        }
        $datasetJson = self::buildNewDataset($jsonArray, $dataset->getJsonFilename());
        return [$msg, $datasetJson];
    }

    /**
     * Handles remove update type, which will remove all feature matches in the update file from the dataset.
     * @param Dataset $dataset - the Dataset object associated with the chosen dataset
     * @param array $jsonFeatures - list of features from the update file
     * @param array $propertySettings - settings for identifying matches between original dataset and update file
     * @return array - [$msg, $datasestJson] - the msg to present to the user informing them of what changes will occur, and the json data to save in the tmp file for easier updating once the update is confirmed
     */
    protected static function updateRemove(Dataset $dataset, array $jsonFeatures, array $propertySettings) {
        $datasetFeatures = $dataset->getFeatures();
        $featureMatches = self::matchFeatures($datasetFeatures, $jsonFeatures, $propertySettings)[0];
        $msg = self::MSGS['update_remove']."\r\n";
        if (empty($featureMatches)) $msg .= "\r\n".self::MSGS['remove_no_matches']."\r\n";
        else $msg .= "\r\n".self::MSGS['remove_matches']."\r\n".self::listFeatures($featureMatches, $datasetFeatures);
        // make a list of json features for only those features that would remain
        $datasetJson = $dataset->asJson();
        $features = [];
        foreach ($datasetFeatures as $id=>$feature) {
            if (!in_array($id, $featureMatches)) $features[] = $feature->asJson();
        }
        $datasetJson['features'] = $features;
        return [$msg, $datasetJson];
    }

    /**
     * Handles standard update type, which may include a combination of modify_existing, overwrite_blank, add_new, and remove_empty.
     * @param array $update - the update settings from the plugin config page
     * @param Dataset $dataset - the Dataset object associated with the chosen dataset
     * @param array $jsonFeatures - list of features from the update file
     * @param array $propertySettings - settings for identifying matches between original dataset and update file
     * @return array - [$msg, $datasestJson] - the msg to present to the user informing them of what changes will occur, and the json data to save in the tmp file for easier updating once the update is confirmed
     */
    protected static function updateStandard(array $update, Dataset $dataset, array $jsonFeatures, array $propertySettings): array {
        $datasetFeatures = $dataset->getFeatures();
        // set initial message
        $msg = self::MSGS['update_standard']."\r\n";
        if ($update['modify_existing']) {
            $msg .= "\t- ".self::MSGS['modify_existing'];
            if ($update['overwrite_blank']) $msg .= self::MSGS['overwrite_blank']."\r\n";
            else $msg .= self::MSGS['not_overwrite_blank']."\r\n";
        }
        if ($update['add_new']) $msg .= "\t- ".self::MSGS['add_new']."\r\n";
        if ($update['remove_empty']) $msg .= "\t- ".self::MSGS['remove_empty']."\r\n";
        // get list of features to be modified
        [$featureMatches, $jsonFeatures] = self::matchFeatures($datasetFeatures, $jsonFeatures, $propertySettings);
        $features = []; // the features that will actually be saved
        // the three major options: standard update can include any combination of these
        if ($update['modify_existing']) {
            if (empty($featureMatches)) $msg .="\r\n".self::MSGS['modify_existing_no_matches']."\r\n";
            else $msg .= "\r\n".self::MSGS['modify_existing_matches']."\r\n".self::listFeatures($featureMatches, $datasetFeatures);
            foreach ($jsonFeatures as $jsonFeature) {
                if (empty($jsonFeature['id'])) continue; // not a match, no modification
                $newFeature = $datasetFeatures[$jsonFeature['id']]->asJson();
                // only update properties and coordinates
                $props = [];
                foreach ($newFeature['properties'] ?? [] as $prop=>$val) {
                    $newVal = ($jsonFeature['properties'] ?? [])[$prop];
                    if ($newVal !== null && ($update['overwrite_blank'] || !empty($newVal))) $props[$prop] = $newVal;
                    else $props[$prop] = $val;
                }
                $newFeature['properties'] = $props;
                // coords
                $coords = ($jsonFeature['geometry'] ?? [])['coordinates'];
                $coords = Utils::setValidCoordinates($coords, $dataset->getFeatureType());
                if (!empty($coords)) $newFeature['geometry']['coordinates'] = $coords; // coords will still be valid otherwise, because the old (valid) coords will be kept
                $features[] = $newFeature;
            }
        } else {
            // make sure that existing features are kept
            // make sure existing features are kept
            foreach ($datasetFeatures as $id=>$feature) {
                if (in_array($id, $featureMatches)) $features[] = $feature->asJson();
            }
        }
        if ($update['add_new']) {
            // everything in jsonFeatures that didn't get an id will be added
            $addedFeatures = [];
            $unnamedCount = 0; // in case some don't have a name for their name property
            $count = $dataset->asJson()['featureCounter'];
            $dataId = str_replace('.json', '', $dataset->asYaml()['dataset_file']);
            foreach ($jsonFeatures as $feature) {
                if (!empty($feature['id'])) continue;
                if ($feature=Feature::setValidFeature($feature, $dataset->getFeatureType())) {
                    $feature['id'] = $dataId.'_'.$count;
                    $count++;
                    $name = ($feature['properties'] ?? [])[$dataset->getNameProperty()];
                    $features[] = $feature; // add to the features json list
                    if (!empty($name)) $addedFeatures[] = $name;
                    else $unnamedCount++;
                }
            }
            if (empty($addedFeatures) && $unnamedCount < 0) $msg .="\r\n".self::MSGS['add_new_none']."\r\n";
            else {
                $msg .="\r\n".self::MSGS['add_new_some']."\r\n";
                foreach ($addedFeatures as $feat) {
                    $msg .= "\t- $feat\r\n";
                }
                if ($unnamedCount > 0) $msg .= "\t- $unnamedCount unnamed features\r\n";
            }
        }
        if ($update['remove_empty']) {
            // everything in dataset features not in the foundFeatures list will be removed
            $removedFeatures = [];
            $jsonFeaturesIds = array_column($jsonFeatures, 'id');
            foreach (array_keys($datasetFeatures) as $id) {
                if (!in_array($id, $jsonFeaturesIds)) $removedFeatures[] = $id;
            }
            if (empty($removedFeatures)) $msg .= "\r\nAll existing features had matches in the update file. No features will be removed.\r\n";
            else $msg .= "\r\nThe following ".count($removedFeatures)." will be removed:\r\n".self::listFeatures($removedFeatures, $datasetFeatures);
        } else {
            // make sure existing features are kept
            foreach ($datasetFeatures as $id=>$feature) {
                if (!in_array($id, $featureMatches)) $features[] = $feature->asJson();
            }
        }
        // update features, featureCounter
        $datasetJson = $dataset->asJson();
        $datasetJson['features'] = $features;
        if (!empty($count)) $datasetJson['featureCounter'] = $count;
        return [$msg, $datasetJson];
    }

    /**
     * Returns all features from the first dataset that match features in the second dataset based on parameters provided. Also modifies features in the second dataset slightly.
     * @param array $datasetFeatures - list of Feature objects from the dataset
     * @param array $jsonFeatures - list of features from the newly uploaded json content
     * @param array $propertySettings - an array with one item ['tour_coords'] or an array with two items [$datasetProp, $fileProp]
     * @return array - array with list of feature matches (just ids) and list of new features with added ids [$matches, $jsonFeatures]
     */
    protected static function matchFeatures(array $datasetFeatures, array $jsonFeatures, array $propertySettings): array {
        if (empty($propertySettings)) return [[], $jsonFeatures];
        $coords = $propertySettings[0] === 'tour_coords' ? true : false;
        if (!$coords && count($propertySettings) !=2) return [[], $jsonFeatures]; // may change in the future if allowing additional params
        $matches = [];
        $newJson = [];
        if ($coords) {
            $coordsList = []; // make list using coordinates from the dataset features as index i.e. [coords=>id]
            foreach ($datasetFeatures as $id=>$feature) {
                $x = ($feature->asJson())['geometry']['coordinates'];
                $coordsList[(new Data($x))->toYaml()] = $id;
            }
            foreach ($jsonFeatures as $feature) { // look for matches
                $x = ($feature['geometry'] ?? [])['coordinates'];
                $id = $coordsList[(new Data($x))->toYaml()];
                if (!empty($id)) $matches[] = $feature['id'] = $id;
                $newJson[] = $feature;
            }
        } else {
            $propsList = []; // make list using property from the dataset features as index i.e. [propValue=>id] (slightly more complicated because the feature may not have that property (or even any properties))
            foreach ($datasetFeatures as $id=>$feature) {
                $prop = ($feature->asJson()['properties'] ?? [])[$propertySettings[0]];
                if ($prop) $propsList[$prop] = $id;
            }
            foreach ($jsonFeatures as $feature) {
                $id = $propsList[($feature['properties'] ?? [])[$propertySettings[1]]];
                if (!empty($id)) $matches[] = $feature['id'] = $id;
                $newJson[] = $feature;
            }
        }
        return [$matches, $newJson];
    }
    /**
     * List names of a subset of features from the dataset
     * @param array $featureIds - list of ids for the features to display
     * @param array $features - full list of features from the dataset
     * @return string - list of feature names as a string (for easy output)
     */
    protected static function listFeatures(array $featureIds, array $features): string {
        $list = '';
        foreach ($featureIds as $id) {
            $feature = $features[$id];
            if (empty($feature)) $name = $id;
            else $name = $feature->getName();
            $list .= "\t- ".$name." ($id)\r\n";
        }
        return $list;
    }

    /**
     * Used to build a new dataset from a json file, including validating the json/features and setting sensible defaults. Can be used when a new dataset file has been uploaded, or when a total replacement update is initiated.
     * @param array $jsonArray - json content from the new file
     * @param string $jsonFilename - the name of the new file
     */
    protected static function buildNewDataset(array $jsonArray, string $jsonFilename): array {
        $jsonData = new Data($jsonArray); // $jsonData is used to get values, because the 'get' function is useful and there may have been some problems using $jsonArray. $jsonArray is necessary for setting values, because setting values for a Data object is extremely annoying (have to use merge, not set)
        $id = str_replace('.json', '', $jsonFilename); // json filename is primarily used as the id, but this is used to generate default dataset name and feature ids
        if (empty($jsonData)) return []; // just in case
        if (empty($jsonData->get('name'))) $jsonArray['name'] = $id; // set dataset name, if not already set
        if ($jsonData->get('features.0.geometry.type')) $featureType = Utils::setValidType($jsonData->get('features.0.geometry.type')); // first feature might be invalid, hence the if statement
        // set feature ids and get properties list
        $count = $jsonData->get('featureCounter') ?? 0; // will already be set if this is an update
        $features = [];
        $propList = [];
        foreach ($jsonData->get('features') as $feature) {
            if (!$featureType && $feature['geometry'] && $feature['geometry']['type']) $featureType = Utils::setValidType($feature['geometry']['type']); // in case first feature(s) was/were invalid
            if (isset($featureType) && $feature=Feature::setValidFeature($feature, $featureType)) {
                if (empty($feature['id'])) { // allows for setting ids ahead of time, if necessary (updates)
                    $feature['id'] = $id.'_'.$count;
                    $count++;
                }
                $features[] = $feature;
                if (is_array($feature['properties'])) {
                    $propList = array_merge($propList, $feature['properties']); // make sure to add any properties that previous features didn't have - ensures that all properties are listed, not just the properties of the first feature
                }
            }
        }
        $propList = array_keys($propList); // only need the keys, which are the property names - values are set per feature
        $jsonArray['propertyList'] = $propList;
        $jsonArray['featureType'] = $featureType ?? '';
        $jsonArray['featureCounter'] = $count; // if features are added in future updates, will be used to generate ids for them
        $jsonArray['features'] = $features;
        // set default name property - first priority is already set name prop, next is property called name, next is property beginning or ending with name, and last resort is first property
        $nameProperty = $jsonData->get('nameProperty') ?? '';
        if (empty($nameProperty) || !in_array($nameProperty, $propList)) {
            foreach ($propList as $prop) {
                if (strcasecmp($prop, 'name') == 0) $nameProperty = $prop;
                else if (empty($nameProperty) && preg_match('/^(.*name|name.*)$/i', $prop)) $nameProperty = $prop;
            }
            if (empty($nameProperty) && !empty($propList)) $nameProperty = $propList[0];
            $jsonArray['nameProperty'] = $nameProperty;
        }
        // set dataset file route, if necessary
        if (empty($jsonData->get('datasetFileRoute'))) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $jsonArray['name']), '-')); // trying to save folders with capital letters or special symbols may cause issues
            $jsonArray['datasetFileRoute'] = Grav::instance()['locator']->findResource('page://').'/datasets/'.$slug.'/dataset.md';
        }
        return $jsonArray;
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