<?php
namespace Grav\Plugin\LeafletTour;

use RocketTheme\Toolbox\File\MarkdownFile;

/**
 * @property MarkdownFile|null $file
 * @property string $id
 * @property string|null $title
 * @property array $basemaps
 * @property array $overrides
 * @property array $start
 * @property array $features
 * @property array $extras
 * @property bool $no_tour_basemaps
 * @property array|null $starting_bounds
 * @property array $popups id => name
 */
class View {

    const DEFAULT_SHORTCODES = 'There is nothing here. Add some features to the view first.';
    const DEFAULT_OPTIONS = [
        'remove_tile_server' =>  true,
        'only_show_view_features' => false,
        'list_popup_buttons' => false,
    ];

    private $file, $id, $title, $basemaps, $overrides, $start, $features, $extras, $no_tour_basemaps, $starting_bounds, $popups;

    /**
     * Sets and validates all provided options
     * - All view basemaps must be included in $valid_basemaps
     * - All view features must be included in $included_features
     * - Start location (if set) must be included in $point_ids
     * - Sets starting bounds for future reference (only if $all_features is provided)
     * - Sets popups list for future reference (using $feature_popups)
     * 
     * @param array $options Yaml from view file header
     * @param array $valid_basemaps For validating view basemaps: [string]
     * @param array $point_ids For validating view start location: [string]
     * @param array $included_features For validating view features: [string]
     * @param array $feature_popups For generating popups list: [id => ['name' => string], ...]
     * @param array|null $all_features For generating starting bounds [id => Feature]
     */
    private function __construct($options, $valid_basemaps, $point_ids, $included_features, $feature_popups, $all_features) {
        // validate file - file does not have to exist, but must be an object with a valid function called "exists" - There is probably a better way to check that this is a MarkdownFile, but this works for now
        try {
            $file = Utils::get($options, 'file');
            $file->exists();
            $this->file = $file;
        } catch (\Throwable $t) {
            $this->file = null;
        }
        // id: must be string
        $this->id = Utils::getStr($options, 'id');
        // title: must be string or null
        $this->title = Utils::getStr($options, 'title', null);
        // arrays
        foreach (['basemaps', 'overrides', 'start'] as $key) {
            $this->$key = Utils::getArr($options, $key);
        }
        // no_tour_basemaps: must be bool
        $this->no_tour_basemaps = (Utils::get($options, 'no_tour_basemaps') === true);
        // extras
        $keys = ['file', 'id', 'title', 'basemaps', 'overrides', 'start', 'features', 'no_tour_basemaps'];
        $this->extras = array_diff_key($options, array_flip($keys));
        // basemaps: must be valid
        $this->basemaps = array_values(array_intersect($this->basemaps, $valid_basemaps));
        // features: must be valid
        $this->features = array_values(array_intersect(array_column(Utils::getArr($options, 'features'), 'id'), $included_features));
        // start: must be valid
        if (!Utils::getStr($this->start, 'location') || !in_array(Utils::getStr($this->start, 'location'), $point_ids)) $this->start['location'] = 'none';
        // get starting bounds
        if ($all_features) $this->starting_bounds = self::calculateStartingBounds($this->start, Utils::get($all_features, $this->start['location']));
        else $this->starting_bounds = null;

        // get popups
        $this->popups = [];
        foreach ($this->features as $id) {
            if ($popup = Utils::getArr($feature_popups, $id, null)) {
                $this->popups[$id] = Utils::getStr($popup, 'name');
            }
        }
    }

    /**
     * Creates a new view from a valid markdown file
     * 
     * @param MarkdownFile $file The view file
     * @param array $valid_basemaps [string]
     * @param array $point_ids [string]
     * @param array $all_features [id => Feature]
     * @param array $included_features [string]
     * @param array $feature_popups [id => ['name' => string]]
     * @return View
     */
    public static function fromTour($file, $valid_basemaps, $point_ids, $all_features, $included_features, $feature_popups) {
        return new View(array_merge($file->header(), ['file' => $file]), $valid_basemaps, $point_ids, $included_features, $feature_popups, $all_features);
    }
    /**
     * Creates a new view from an array (equivalent to yaml from markdown file header)
     * 
     * @param array $options Yaml from view file header
     * @param array $valid_basemaps [string]
     * @param array $point_ids [string]
     * @param array $included_features [string]
     * @param array $feature_popups [id => ['name' => string]]
     * @return View
     */
    public static function fromArray($options, $valid_basemaps, $point_ids, $included_features, $feature_popups) {
        return new View($options, $valid_basemaps, $point_ids, $included_features, $feature_popups, null);
    }

    /**
     * Generates a set of options to pass to the tour template/javascript
     * - remove_tile_server and only_show_view_features: Options from view if set, otherwise options from tour if set, otherwise options from view defaults
     * - features: [string]
     * - basemaps: [string] Modified to include any additional tour basemaps unless no_tour_basemaps is true
     * - bounds: starting bounds
     * 
     * @param array $tour_options ['remove_tile_server' => bool, 'only_show_view_features' => bool]
     * @param array $tour_basemaps [string]
     * @return array [remove_tile_server, only_show_view_features, features, basemaps, bounds]
     */
    public function getViewData($tour_options, $tour_basemaps) {
        $options = array_merge(self::DEFAULT_OPTIONS, $tour_options, $this->getOverrides());
        $basemaps = $this->getBasemaps();
        if (!$this->hasNoTourBasemaps()) $basemaps = array_values(array_unique(array_merge($basemaps, $tour_basemaps)));
        return [
            'remove_tile_server' => $options['remove_tile_server'],
            'only_show_view_features' => $options['only_show_view_features'],
            'features' => $this->getFeatures(),
            'basemaps' => $basemaps,
            'bounds' => $this->getStartingBounds(),
        ];
    }
    /**
     * Generates a list of all features to include in the view popup buttons list (which is created by the tour template). Mostly returns everything from the popups list generated in constructor, but removes any features that already have a popup button provided via shortcode in the view content
     * 
     * @return array [id => name]
     */
    public function getPopupButtonsList() {
        $content = ($file = $this->getFile()) ? $file->markdown() : '';
        $buttons = [];
        foreach ($this->getPopups() as $id => $name) {
            if (!preg_match("/\[popup-button\\s+id\\s*=\\s*\"?$id/", $content)) $buttons[$id] = $name;
        }
        return $buttons;
    }
    /**
     * Turns the list of popups (generated by constructor) into a simple string that can be displayed by the view blueprint in the admin panel page editor. Each feature gets a line with a shortcode that can be copied and then pasted wherever.
     * 
     * @return string
     */
    public function getShortcodesList() {
        $features = $this->getPopups();
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
     * Returns content for the view page header
     * 
     * @return array
     */
    public function toYaml() {
        return array_merge($this->getExtras(), [
            'id' => $this->getId(),
            'title' => $this->title,
            'basemaps' => $this->getBasemaps(),
            'overrides' => $this->getOverrides(),
            'start' => $this->start,
            'features' => array_map(function($id) { return ['id' => $id]; }, $this->getFeatures()),
            'no_tour_basemaps' => $this->hasNoTourBasemaps(),
            'shortcodes_list' => $this->getShortcodesList(),
        ]);
    }

    // Simple getters
    /**
     * @return MarkdownFile|null
     */
    public function getFile() { return $this->file; }
    // Getters for values from yaml
    /**
     * @return string
     */
    public function getId() { return $this->id; }
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
    protected function getStartingBounds() { return $this->starting_bounds; }
    /**
     * @return array
     */
    protected function getPopups() { return $this->popups; }

    /**
     * Generates starting bounds to be used (if valid settings are provided)
     * - First priority: If start.bounds are provided and can be turned into valid bounds array, return that
     * - Second priority: If valid distance is provided and valid Point feature is also provided, return distance, lng, and lat
     * - Third priority: If valid distance is provided and valid lng and lat are also provided, return distance, lng, and lat
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
                $bounds = [
                    'lng' => $feature->getCoordinates()[0],
                    'lat' => $feature->getCoordinates()[1]
                ];
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
                        $bounds['distance'] = $dist / 0.3048;
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
}

?>