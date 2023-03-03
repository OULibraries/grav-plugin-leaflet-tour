<?php
namespace Grav\Plugin\LeafletTour;

use RocketTheme\Toolbox\File\MarkdownFile;

/**
 * @property MarkdownFile|null $file
 * @property array|null $starting_bounds
 * @property array $overrides
 * @property array $basemaps
 * @property array $features
 * @property bool $no_tour_basemaps
 * @property array $popups [id => name]
 * @property array $extras
 */
class View {

    const DEFAULT_SHORTCODES = 'There is nothing here. Add some features to the view first.';
    const DEFAULT_OPTIONS = [
        'remove_tile_server' =>  true,
        'only_show_view_features' => false,
        'list_popup_buttons' => false,
    ];

    private $file, $basemaps, $overrides, $features, $extras, $no_tour_basemaps, $starting_bounds, $popups;

    /**
     * Prepares a view object from a set of options. Options are not assumed to have been validated (except for file, if added to options). Should only be called when actually building a view for a tour, not just performing validation. Does not need to worry about storing all values for future saving.
     * - Uses validateYaml to validate data types for all expected values
     * - Uses validateTourUpdate to validate start location and features
     * - Sets starting bounds and overrides (calls functions)
     * - Sets and validates basemaps
     * - Sets features, popups, and no_tour_basemaps (and file if provided)
     * - Sets extra values to allow additional customization (includes id and title)
     * 
     * @param array $options Original view yaml, possibly with file (if so, file should be valid)
     * @param array $basemap_ids
     * @param array $point_ids
     * @param array $included_ids
     * @param array $popups [id => name]
     * @param array $all_features
     * @param array $tour_overrides
     */
    private function __construct($options, $basemap_ids, $point_ids, $included_ids, $popups, $all_features, $tour_overrides) {
        // Use validation function to validate all data types
        $options = self::validateYaml($options);
        // validate start location and features (do not pass popups values, because we do not care about shortcodes list)
        $options = self::validateTourUpdate($options, $point_ids, $included_ids, []);
        // Set starting bounds and overrides
        if (is_array($all_features)) $this->starting_bounds = self::calculateStartingBounds($options['start'], Utils::get($all_features, $options['start']['location']));
        else $this->starting_bounds = null;
        $this->overrides = self::buildOverrides($options['overrides'], $tour_overrides);
        // validate basemaps
        $this->basemaps = array_values(array_intersect($options['basemaps'], $basemap_ids));
        // set features, popups, no tour basemaps (and file)
        $this->features = array_column($options['features'], 'id');
        $this->no_tour_basemaps = $options['no_tour_basemaps'] ?? false;
        $this->popups = $popups;
        $this->file = Utils::get($options, 'file');
        // set extras
        $keys = ['file', 'basemaps', 'overrides', 'start', 'features', 'no_tour_basemaps', 'shortcodes_list'];
        $this->extras = array_diff_key($options, array_flip($keys));
    }
    /**
     * - Creates view from file
     * - Sets file
     * 
     * @param MarkdownFile $file
     * @param array $basemap_ids
     * @param array $point_ids
     * @param array $included_ids
     * @param array $popups [id => name]
     * @param array $all_features
     * @param array $tour_overrides
     * @return View|null
     */
    public static function fromFile($file, $basemap_ids, $point_ids, $included_ids, $popups, $all_features, $tour_overrides) {
        try {
            return new View(array_merge($file->header(), ['file' => $file]), $basemap_ids, $point_ids, $included_ids, $popups, $all_features, $tour_overrides);
        } catch (\Throwable $t) {
            return null;
        }
    }
    /**
     * Creates view from array
     * 
     * @param array $options Original view yaml, possibly with file (if so, file should be valid)
     * @param array $basemap_ids
     * @param array $point_ids
     * @param array $included_ids
     * @param array $popups [id => name]
     * @param array $all_features
     * @param array $tour_overrides
     * @return View
     */
    public static function fromArray($options, $basemap_ids, $point_ids, $included_ids, $popups, $all_features, $tour_overrides) {
        return new View($options, $basemap_ids, $point_ids, $included_ids, $popups, $all_features, $tour_overrides);
    }

    /**
     * Validates data types for all expected values
     * - Checks data types for all yaml values
     * - Keeps extra options (not as separate array)
     * 
     * @param array $yaml Original view yaml
     * @return array Modified yaml
     */
    public static function validateYaml($yaml) {
        $options = $yaml;
        $options['id'] = Utils::getStr($yaml, 'id');
        $options['title'] = Utils::getStr($yaml, 'title', null);
        $options['no_tour_basemaps'] = Utils::getType($yaml, 'no_tour_basemaps', 'is_bool');
        foreach (['basemaps', 'overrides', 'start', 'features'] as $key) {
            $options[$key] = Utils::getArr($yaml, $key);
        }
        return $options;
    }
    /**
     * Validates view after tour save - no need to check data types (hopefully)
     * - Validates start location and features array
     * - Regenerates shortcodes list
     * 
     * @param array $yaml The original view yaml
     * @param array $point_ids From tour
     * @param array $included_ids From tour
     * @param array $popups [id => name]
     * @return array Modified yaml
     */
    public static function validateTourUpdate($yaml, $point_ids, $included_ids, $popups) {
        $start = Utils::getArr($yaml, 'start');
        if (!Utils::getStr($start, 'location') || !in_array(Utils::getStr($start, 'location'), $point_ids)) $start['location'] = 'none';
        $features = [];
        // TODO: Test for features - correct input and output [[id => string], ...]
        foreach (Utils::getArr($yaml, 'features') as $feature_yaml) {
            if (($id = Utils::getStr($feature_yaml, 'id')) && in_array($id, $included_ids)) $features[] = ['id' => $id];
        }
        return array_merge($yaml, [
            'start' => $start,
            'features' => $features,
            'shortcodes_list' => self::buildShortcodesList($popups, array_column($features, 'id')),
        ]);
    }
    /**
     * Validates view yaml on save
     * - Uses validateYaml to perform basic data type validation
     * - Uses validateTourUpdate to check start location and features and to generate shortcodes list
     * - Validates data types for items in arrays - overrides, start
     * - Validates basemaps
     * 
     * @param array $yaml
     * @param array $basemap_ids
     * @param array $point_ids
     * @param array $included_ids
     * @param array $popups [id => name]
     * @return array Modified yaml
     */
    public static function validateUpdate($yaml, $basemap_ids, $point_ids, $included_ids, $popups) {
        // data types, keep extra values
        $options = self::validateYaml($yaml);
        // validate basemaps, features, and start location, also generate shortcodes list
        $options = self::validateTourUpdate($options, $point_ids, $included_ids, $popups);
        // basemaps: only valid
        $options['basemaps'] = array_values(array_intersect($options['basemaps'], $basemap_ids));
        // overrides: check values
        foreach (['remove_tile_server', 'only_show_view_features', 'list_popup_buttons'] as $key) {
            if (isset($options['overrides'][$key])) {
                $options['overrides'][$key] = Utils::getType($options['overrides'], $key, 'is_bool');
            }
        }
        // start: check other values (distance, units, lat, lng, bounds)
        foreach (['distance', 'lat', 'lng']  as $key) {
            $options['start'][$key] = Utils::getType($options['start'], $key, 'is_numeric');
        }
        $options['start']['bounds'] = Utils::getArr($options['start'], 'bounds');
        return $options;
    }
    /**
     * Combines tour and view override options with defaults
     * 
     * @param array $view_options
     * @param array $tour_options
     * @return array
     */
    public static function buildOverrides($view_options, $tour_options) {
        $overrides = [];
        foreach (self::DEFAULT_OPTIONS as $key => $value) {
            $overrides[$key] = Utils::getType($view_options, $key, 'is_bool') ?? Utils::getType($tour_options, $key, 'is_bool') ?? $value;
        }
        return $overrides;
    }
    /**
     * Turns the list of popups (generated by constructor) into a simple string that can be displayed by the view blueprint in the admin panel page editor. Each feature gets a line with a shortcode that can be copied and then pasted wherever.
     * - No popups - return default string
     * - Return entry for each feature in view with popup
     * 
     * @param array $popups
     * @param array $features
     * @return string
     */
    public static function buildShortcodesList($popups, $features) {
        $features = array_intersect_key($popups, array_flip($features));
        // if empty, return default message
        if (empty($features)) return self::DEFAULT_SHORTCODES;
        else {
            // turn into array of shortcodes
            $shortcodes = array_map(function($id, $name) {
                return "[popup-button id=\"$id\"] $name [/popup-button]";
            }, array_keys($features), array_values($features));
            // return as string
            return implode("\r\n", $shortcodes);
        }
    }
    /**
     * Generates starting bounds to be used (if valid settings are provided)
     * - First priority: If start.bounds are provided and can be turned into valid bounds array, return that
     * - Second priority: If valid distance is provided and valid Point feature is also provided, return distance, lng, and lat
     * - Third priority: If valid distance is provided and valid lng and lat are also provided, return distance, lng, and lat (otherwise null)
     * - Modifies distance from provided units (default meters) to meters
     * 
     * @param array $start Start options from tour/view yaml
     * @param Feature|null $feature A Point feature if start.location is valid
     * @return array|null
     */
    public static function calculateStartingBounds($start, $feature) {
        // first priority: manually set bounds
        $bounds = Utils::getBounds(Utils::getArr($start, 'bounds'));
        if (!$bounds && ($dist = Utils::getType($start, 'distance', 'is_numeric')) && $dist > 0) {
            // next priority: point location
            if (($feature) && ($feature->getType() === 'Point')) {
                $bounds = $feature->getYamlCoordinates();
            }
            // otherwise try coordinates
            if (!$bounds && ($lng = Utils::getType($start, 'lng', 'is_numeric')) && ($lat = Utils::getType($start, 'lat', 'is_numeric'))) $bounds = ['lng' => $lng, 'lat' => $lat];
            // if something was valid, make sure distance is in meters
            if ($bounds) {
                switch (Utils::getStr($start, 'units', 'meters')) {
                    case 'kilometers':
                        $bounds['distance'] = $dist * 1000;
                        break;
                    case 'feet':
                        $bounds['distance'] = $dist * 0.3048;
                        break;
                    case 'miles':
                        $bounds['distance'] = $dist * 1609.34;
                        break;
                    default:
                        $bounds['distance'] = $dist;
                }
            }
        }
        return $bounds;
    }

    /**
     * Return options formatted for tour
     * - Returns value expected by tour
     * - Modifies basemaps to include any additional tour basemaps unless no_tour_basemaps is true
     * 
     * @param array $tour_basemaps [string]
     * @return array [remove_tile_server, only_show_view_features, features, basemaps, bounds]
     */
    public function getViewData($tour_basemaps) {
        $basemaps = $this->getBasemaps();
        if (!$this->hasNoTourBasemaps()) $basemaps = array_values(array_unique(array_merge($basemaps, $tour_basemaps)));
        return [
            'remove_tile_server' => $this->getOverrides()['remove_tile_server'],
            'only_show_view_features' => $this->getOverrides()['only_show_view_features'],
            'features' => $this->getFeatures(),
            'basemaps' => $basemaps,
            'bounds' => $this->getStartingBounds(),
        ];
    }
    /**
     * Generates a list of all features to include in the view popup buttons list (which is created by the tour template).
     * - Returns list only if list_popup_buttons is true, otherwise empty array
     * - Returns [id => name] for all features in popus list and view features list
     * - Removes any features from list that already have a popup button provided via shortcode in the view content
     * - Returns feature in order set by view features list
     * 
     * @return array [id => name]
     */
    public function getPopupButtonsList() {
        if (!$this->getOverrides()['list_popup_buttons']) return [];
        $content = ($file = $this->getFile()) ? $file->markdown() : '';
        $buttons = [];
        // all popups [id => name] from tour, view features [id]
        // put features from view at front of popups list
        $popups = array_merge(array_flip($this->getFeatures()), $this->getPopups());
        // filter - only feature from view and in popups list should be included
        $popups = array_intersect_key($popups, $this->getPopups(), array_flip($this->getFeatures()));
        foreach ($popups as $id => $name) {
            if (!preg_match("/\[popup-button\\s+id\\s*=\\s*\"?$id/", $content)) $buttons[$id] = $name;
        }
        return $buttons;
    }

    // Simple getters
    /**
     * @return MarkdownFile|null
     */
    public function getFile() { return $this->file; }
    /**
     * @return array
     */
    public function getBasemaps() { return $this->basemaps; }
    /**
     * @return array
     */
    public function getOverrides() { return $this->overrides; }
    /**
     * @return array
     */
    public function getFeatures() { return $this->features; }
    /**
     * @return array
     */
    public function getExtras() { return $this->extras; }
    /**
     * @return bool
     */
    public function hasNoTourBasemaps() { return $this->no_tour_basemaps; }
    // Getters for values generated in constructor
    /**
     * @return array|null
     */
    public function getStartingBounds() { return $this->starting_bounds; }
    /**
     * @return array
     */
    public function getPopups() { return $this->popups; }
}

?>