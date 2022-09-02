<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;

class Utils {
    
    const BASEMAP_ROUTE = 'user/data/leaflet-tour/images/basemaps/';
    const UPDATE_ROUTE = '/leaflet-tour/datasets/update/';
    const IMAGE_ROUTE = 'user/data/leaflet-tour/images/';

    const STREAMS = ['user', 'page', 'image', 'account', 'environment', 'asset', 'blueprints', 'config', 'plugins', 'themes', 'theme', 'languages', 'user-data', 'system', 'cache', 'log', 'backup', 'tmp'];

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
     * Search a directory recursively for any files matching the provided key.
     * 
     * @param string $key The name of the template file (or other file, really) to look for. Passing 'dataset.md' would get any file ending in 'dataset.md'
     * @param array $results List of pages found so far. Only to be used when calling recursively.
     * @param null|string $dir Optional: Specify the directory to start the search in. Only to be used when calling recursively.
     * @return array A list of routes for all files found
     */
    public static function findTemplateFiles(string $key, array $results = [], string $dir = ''): array {
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
     * Returns the dataset page currently being edited
     * 
     * @return MarkdownFile The file for the dataset page
     */
    public static function getDatasetFile(): MarkdownFile {
        $file = self::getPageFromKey(self::getCurrentBlueprintKey(), 'point_dataset');
        if (!$file->exists()) return self::getPageFromKey(self::getCurrentBlueprintKey(), 'shape_dataset');
    }
    /**
     * Returns the tour page currently being edited
     * 
     * @return MarkdownFile The file for the tour page
     */
    public static function getTourFile(): MarkdownFile {
        return self::getPageFromKey(self::getCurrentBlueprintKey(), 'tour');
    }
    /**
     * Returns the tour page for the view page currently being edited
     * 
     * @return ?MarkdownFile The file for the tour page, or null if no such file exists
     */
    public static function getTourFileFromView(): ?MarkdownFile {
        try {
            $keys = explode('/', self::getCurrentBlueprintKey());
            array_pop($keys);
            $file = self::getPageFromKey($keys, 'tour');
            if ($file->exists()) return $file;
        } catch (\Throwable $t) {}
        return null;
    }

    // purely a getter for something from Grav
    private static function getCurrentBlueprintKey() {
        return Grav::instance()['page']->header()->controller['key'];
    }
    /**
     * Processes input from the key(s) to check for a matching route in the file system. Deals with potential for files to have the folder numeric prefix option set.
     * 
     * @param string|array $key Can be the string or the already exploded string representing the page route
     * @param string $template_name The name of the markdown file to look for (do not include '.md')
     * @return ?MarkdownFile The file for the page if a route is found, null otherwise
     */
    public static function getPageFromKey($key, string $template_name): ?MarkdownFile {
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
     * @return ?string The full route (as recognized by the file system) of the page if a route was found, null otherwise
     */
    private static function parseKeys(string $dir, array $keys): ?string {
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
     * Checks if input is an array with two numeric inputs that fall in the correct range for longitude (-180 to 180) and latitude (-90 to 90)
     * 
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
     * Checks if input is an array with keys north, south, east, and west, each pointing to a valid numeric value (lng/lat)
     * 
     * @param mixed $bounds The (hopefully) array to check
     * @return null|array [[float, float], [float, float]] (southwest, northeast) if valid
     */
    public static function getBounds($bounds): ?array {
        if (is_array($bounds) && count($bounds) >= 4) { // must be an array with four values, can handle extra values, though
            $bounds = [[$bounds['south'], $bounds['west']], [$bounds['north'], $bounds['east']]]; // values must have keys south, west, north, and east
            // have to reverse direction for checking for valid points
            if (self::isValidPoint([$bounds[0][1], $bounds[0][0]]) && self::isValidPoint([$bounds[1][1], $bounds[1][0]])) return $bounds; // southwest and northeast must form valid points
        }
        return null;
    }

    /**
     * Removes whitepsace, makes everything lower case, and handles special characters
     * 
     * @param string $string The string to clean
     * @return string The cleaned string
     */
    public static function cleanUpString(string $string): string {
        $output = strtolower(trim($string)); // remove whitespace on ends, make everything lowercase
        $output = preg_replace('/\s+/', '-', $output);  // replace whitespace with dashes
        $output = preg_replace('/[^a-z0-9_-]+/', '', $output); // remove special characters (anything not a letter, number, underscore, or dash)
        return $output;
    }

    // used for any blueprints
    public static function getDatasetsList(bool $include_none): array {
        $list = [];
        foreach (LeafletTour::getDatasets() as $id => $file) {
            $dataset = Dataset::fromLimitedArray($file->header(), ['title', 'id']);
            $name = $dataset->getName();
            $list[$id] = $name;
        }
        if ($include_none) $list = array_merge(['none' => 'None'], $list);
        return $list;
    }

    public static function getBasemapList(): array {
        $list = [];
        foreach (LeafletTour::getBasemapInfo() as $file => $info) {
            $list[$file] = $info['name'] ?: $file;
        }
        return $list;
    }

    /**
     * Returns select_optgroup options
     */
    public static function getUpdatePropertiesList(): array {
        $list = [];
        foreach (LeafletTour::getDatasets() as $id => $file) {
            $dataset = Dataset::fromLimitedArray($file->header(), ['id', 'title', 'properties']);
            $name = $dataset->getName();
            $sublist = [];
            foreach ($dataset->getProperties() as $prop) {
                $sublist["$id--prop--$prop"] = $prop;
            }
            $list[] = [$name => $sublist];
        }
        return array_merge(['none' => 'None', 'coords' => 'Coordinates'], $list);
    }

    // used for datasets

    /**
     * Get all properties for a given dataset.
     * - Include option for 'none' (optional, useful for something like name_prop where this might be desirable)
     * @return array [$prop => $prop]
     */
    public static function getDatasetPropertyList(MarkdownFile $file, bool $include_none): array {
        $props = Dataset::fromLimitedArray($file->header(), ['properties'])->getProperties();
        $list = array_combine($props, $props);
        if ($include_none) $list = array_merge(['none' => 'None'], $list);
        return $list ?? [];
    }

    public static function getFeaturePropertiesFields(MarkdownFile $file): array {
        $fields = [];
        // get dataset and list of properties
        $props = Dataset::fromLimitedArray($file->header(), ['properties'])->getProperties();
        foreach ($props as $prop) {
            $fields[".$prop"] = [
                'type' => 'text',
                'label' => $prop,
            ];
        }
        return $fields;
    }

    public static function getShapeFillType(MarkdownFile $file, string $default): string {
        $type = Feature::validateFeatureType($file->header()['feature_type']);
        if (str_contains($type, 'LineString')) {
            // LineString or MultiLineString
            return 'hidden';
        }
        return $default;
    }

    public static function getDatasetDefaults(MarkdownFile $file, string $key): string {
        $header = $file->header();
        switch ($key) {
            case 'path_fillColor':
            case 'active_path_color':
                // default: path color ?? default color
                return ($header['path'] ?? [])['color'] ?? Dataset::DEFAULT_PATH['color'];
            case 'active_path_fillColor':
                // default: regular fill color
                return ($header['path'] ?? [])['fillColor'] ?? self::getDatasetDefaults($file, 'path_fillColor');
        }
        return '';
    }

    // getters for tour blueprints

    public static function getTourDatasetFields(MarkdownFile $file): array {
        $fields = [];
        $datasets = $file->header()['datasets'] ?? [];
        $overrides = $file->header()['dataset_overrides'] ?? [];
        foreach (array_column($datasets, 'id') as $id) {
            if ($dataset_file = LeafletTour::getDatasets()[$id]) {
                $dataset = Dataset::fromArray(array_diff_key($dataset_file->header(), array_flip(['features']))); // just because we don't need features
                $name = "header.dataset_overrides.$id";
                $options = [
                    "$name.auto_popup_properties" => [
                        'type' => 'select',
                        'label' => 'Add Properties to Popup Content',
                        'description' => 'Properties selected here will be used instead of properties selected in the dataset header. If only \'None\' is selected, then no properties will be added to popup content.',
                        'options' => array_merge(['none' => 'None'], array_combine($dataset->getProperties(), $dataset->getProperties())),
                        'multiple' => true,
                        'toggleable' => true,
                        'validate' => [
                            'type' => 'array'
                        ],
                        'default' => $dataset->getAutoPopupProperties(),
                    ],
                    "$name.attribution" => [
                        'type' => 'text',
                        'label' => 'Dataset Attribution',
                        'toggleable' => true,
                        'default' => $dataset->getAttribution(),
                    ],
                    'legend_section' => [
                        'type' => 'section',
                        'title' => 'Legend Options',
                    ],
                    "$name.legend.text" => [
                        'type' => 'text',
                        'label' => 'Description for Legend',
                        'description' => 'If this field is set then any legend summary from the dataset will be ignored, whether or not the legend summary override is set.',
                        'toggleable' => true,
                        'default' => $dataset->getLegend()['text'],
                    ],
                    "$name.legend.summary" => [
                        'type' => 'text',
                        'label' => 'Legend Summary',
                        'description' => 'Optional shorter version of the legend description.',
                        'toggleable' => true,
                        'default' => $dataset->getLegend()['summary'],
                    ],
                    "$name.legend.symbol_alt" => [
                        'type' => 'text',
                        'label' => 'Legend Symbol Alt Text',
                        'description' => 'A brief description of the icon/symbol/shape used for each feature.',
                        'toggleable' => true,
                        'default' => $dataset->getLegend()['symbol_alt'],
                    ],
                ];
                // add icon or path options
                if ($dataset->getType() === 'Point') {
                    $options["icon_section"] = [
                        'type' => 'section',
                        'title' => 'Icon Options',
                        'text' => 'Only some of the icon options in the dataset configuration are shown here, but any can be customized by directly modifying the page header in expert mode.',
                    ];
                    $options["$name.icon.file"] = [
                        'type' => 'filepicker',
                        'label' => 'Icon Image File',
                        'description' => 'If not set, the default Leaflet marker will be used',
                        'preview_images' => true,
                        // 'folder' => Grav::instance()['locator']->findResource('user://') . '/data/leaflet-tour/icons',
                        'folder' => 'user://data/leaflet-tour/images/icons',
                        'toggleable' => true,
                    ];
                    $file = $dataset->getIcon(true)['file'];
                    // determine appropriate defaults for icon height/width if not directly set by dataset
                    try {
                        $file ??= $overrides[$dataset->getId()]['icon']['file'];
                    } catch (\Throwable $t) {} // do nothing
                    if ($file) $default = Dataset::CUSTOM_MARKER_FALLBACKS;
                    else $default = Dataset::DEFAULT_MARKER_FALLBACKS;
                    $height = $dataset->getIcon()['height'] ?? $default['height'];
                    $width = $dataset->getIcon()['width'] ?? $default['width'];
                    if ($file) $options["$name.icon.file"]['default'] = $file;
                    $options["$name.icon.width"] = [
                        'type' => 'number',
                        'label' => 'Icon Width (pixels)',
                        'toggleable' => true,
                        'validate' => [
                            'min' => 1
                        ],
                        'default' => $width,
                    ];
                    $options["$name.icon.height"] = [
                        'type' => 'number',
                        'label' => 'Icon Height (pixels)',
                        'toggleable' => true,
                        'validate' => [
                            'min' => 1
                        ],
                        'default' => $height,
                    ];
                } else {
                    $options['path_section'] = [
                        'type' => 'section',
                        'title' => 'Shape Options',
                        'text' => 'Other shape/path options can be customized by directly modifying the page header in expert mode.'
                    ];
                    $options["$name.path.color"] = [
                        'type' => 'colorpicker',
                        'label' => 'Shape Color',
                        'default' => $dataset->getStrokeOptions()['color'],
                        'toggleable' => true,
                    ];
                    $options["$name.border.color"] = [
                        'type' => 'colorpicker',
                        'label' => 'Border Color',
                        'toggleable' => true,
                        'default' => $dataset->getBorderOptions()['color'],
                    ];
                }
                $fields[$name] = [
                    'type' => 'fieldset',
                    'title' => $dataset->getName(),
                    'collapsible' => true,
                    'collapsed' => true,
                    'fields' => $options,
                ];
            }
        }
        return $fields;
    }

    public static function getTourFeatures(MarkdownFile $file, bool $only_points): array {
        $list = [];
        $ids = array_column($file->header()['datasets'] ?? [], 'id');
        $datasets = LeafletTour::getDatasets();
        $datasets = array_merge($ids, $datasets); // to keep order from tour
        $datasets = array_intersect_key($datasets, $ids); // to limit to only tour datasets
        $datasets = array_map(function($dataset_file) { return Dataset::fromArray($dataset_file->header()); }, $datasets);
        if ($only_points) return self::getPoints($datasets);
        // implied else
        foreach (array_values($datasets) as $dataset) {
            foreach ($dataset->getFeatures() as $id => $feature) {
                $list[$id] = $feature->getName() . ' ... (' . $dataset->getName() . ')';
            }
        }
        return $list;
    }

    public static function getPoints(array $datasets): array {
        $list = [];
        foreach (array_values($datasets) as $dataset) {
            if ($dataset->getType() === 'Point') {
                $features = array_map(function($feature) {
                    return $feature->getName() . ' (' . implode(',', $feature->getCoordinates()) . ')';
                }, $dataset->getFeatures());
                $list = array_merge($list, $features);
            }
        }
        return array_merge(['none' => 'None'], $list);
    }

    // getters for view blueprints

    public static function getViewFeatures(MarkdownFile $file, bool $only_points): array {
        $list = [];
        $ids = array_column($file->header()['datasets'] ?? [], 'id');
        $datasets = array_merge($ids, LeafletTour::getDatasets());
        $datasets = array_map(function($dataset_file) {
            return Dataset::fromArray($dataset_file->header());
        }, array_intersect_key($datasets, $ids));
        if ($only_points) {
            return self::getPoints($datasets);
        }
        // implied else
        $tour = Tour::fromFile($file, [], [], $datasets);
        foreach ($tour->getIncludedFeatures() as $id => $feature) {
            $list[$id] = $feature->getName() . ' ... (' . $datasets[$feature->getDatasetId()]->getName() . ')';
        }
        return $list;
    }

    public static function getTourIdForView(MarkdownFile $file): string {
        $id = $file->header()['id'] ?? '';
        return $id;
    }
}
?>