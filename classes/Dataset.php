<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;

class Dataset {

    /**
     * Default path options to set when first creating the dataset
     */
    const DEFAULT_PATH = [
        'stroke' => true,
        'color' => '#0051C2',
        'weight' => 3,
        'opacity' => 1,
        'fill' => true,
        'fillOpacity' => 0.2
    ];
    /**
     * Default active_path options to set when first creating the dataset
     */
    const DEFAULT_ACTIVE_PATH = [
        'weight' => 5,
        'fillOpacity' => 0.4
    ];
    const DEFAULT_BORDER = [
        'stroke' => true,
        'color' => '#ffffff',
        'weight' => 2,
    ];
    /**
     * For creating icon options in merged (fromTour) dataset: Used if default Leaflet marker icon is used. Same as defaults from Leaflet, but modified to match icon yaml values for cleaner code when dealing with icon options
     */
    const DEFAULT_MARKER_FALLBACKS = [
        'iconUrl' => 'user/plugins/leaflet-tour/images/marker-icon.png',
        // 'iconSize' => [25, 41],
        'width' => 25,
        'height' => 41,
        // 'iconAnchor' => [12, 41],
        'anchor_x' => 12,
        'anchor_y' => 41,
        // 'tooltipAnchor' => [2, 0],
        'tooltip_anchor_x' => 2,
        'tooltip_anchor_y' => 0,
        'shadowUrl' => 'user/plugins/leaflet-tour/images/marker-shadow.png',
        // 'shadowSize' => [41, 41],
        'shadow_width' => 41,
        'shadow_height' => 41,
        'iconRetinaUrl' => 'user/plugins/leaflet-tour/images/marker-icon-2x.png',
        'className' => 'leaflet-marker '
    ];
    /**
     * For creating icon options in merged (fromTour) dataset: Used if custom marker icon is used. Does not have as many values as the default fallbacks - just enough to display an icon and tooltip reasonably without customization
     */
    const CUSTOM_MARKER_FALLBACKS = [
        //'iconSize' => [14, 14],
        'width' => 14,
        'height' => 14,
        //'iconAnchor' => [],
        //'tooltipAnchor' => [7, 0],
        'tooltip_anchor_x' => 7,
        'tooltip_anchor_y' => 0,
        //'shadowSize' => [],
        'className' => 'leaflet-marker '
    ];

    /**
     * Values that are stored in the yaml file but should never be set by the user. True reserved value is 'file'
     */
    private static array $reserved_keys = ['id', 'upload_file_path', 'feature_type', 'feature_count', 'ready_for_update', 'rename_properties'];
    /**
     * Values that are stored in the yaml file and can be set by the user
     */
    private static array $blueprint_keys = ['title', 'attribution', 'legend', 'properties', 'name_property', 'auto_popup_properties', 'features', 'icon', 'path', 'active_path', 'border', 'active_border'];

    /**
     * @var MarkdownFile|null File storing the dataset, typically created on dataset initialization
     */
    private $file;

    /**
     * Unique identifier, created on dataset initialization using the upload file name, never modified once set
     */
    private ?string $id = null;
    /**
     * Optional value to enable deleting unnecessary files if dataset is deleted, only set when parsing original file upload
     */
    private ?string $upload_file_path = null;
    /**
     * Point, LineString, MultiLineString, Polygon, or MultiPolygon, set in fromJson, never modified
     */
    private ?string $feature_type = null;
    /**
     * A running total of all dataset features, including features that have been removed, used to create unique ids for new features, never modified directly
     */
    private ?int $feature_count = null;
    /**
     * Set true whenever a dataset update process (from plugin) begins and false whenever the dataset is updated (from page). Used in dataset update process to indicate that update changes should be reviewed again before confirmation.
     */
    private bool $ready_for_update = false;
    /**
     * Identifies the dataset to users
     */
    private ?string $title = null;
    /**
     * Text to provide attribution for the dataset, will be added automatically to tours that use it
     */
    private ?string $attribution = null;
    /**
     * [text, summary, symbol_alt]
     */
    private array $legend = [];
    /**
     * [$id => Feature, ...], the features contained by the dataset, must have valid coordinates for the dataset feature_type, never directly set, but can be updated
     */
    private array $features = [];
    /**
     * A list of property keys that each feature should have / is allowed to have
     */
    private array $properties = [];
    /**
     * The property used to determine a feature's name when its custom_name is not set, must be in the dataset properties list
     */
    private ?string $name_property = null;
    /**
     * Properties used to generate auto popup content for features. Must be in the dataset properties list
     */
    private array $auto_popup_properties = [];
    /**
     * Icon options, based on Leaflet icon options, but the yaml for storage is slightly different
     */
    private array $icon = [];
    /**
     * Path options from Leaflet path options
     */
    private array $path = [];
    private array $active_path = [];
    private array $border = [];
    private array $active_border = [];

    /**
     * Any values not reserved or part of blueprint
     */
    private array $extras = [];

    /**
     * Sets all provided values. Validation needs differ based on how the dataset is being created and so will be handled outside of this function.
     * 
     * @param array $options
     */
    private function __construct(array $options) {
        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
    }

    // Constructor Methods

    /**
     * Creates a new dataset from an uploaded json file. Determines feature_type, features, and properties. May also set name, name_property, upload_file_path and feature_count if provided.
     * 
     * @param array $json GeoJson array with features
     * 
     * @return Dataset|null New Dataset if at least one feature is valid
     */
    public static function fromJson(array $json): ?Dataset {
        // loop through json features and try creating new Feature objects
        $type = null; // to be set by first valid feature
        $features = $properties = []; // to be filled
        foreach ($json['features'] ?? [] as $feature_json) {
            if ($feature = Feature::fromJson($feature_json, $type)) {
                $type ??= $feature->getType(); // set type if this is the first valid feature
                $features[] = $feature;
                $properties = array_merge($properties, $feature->getProperties());
            }
        }
        if (!empty($features)) {
            $options = [
                'feature_type' => $type,
                'features' => $features,
                'properties' => array_keys($properties)
            ];
            $dataset = new Dataset($options);
            // set optional values
            if ($name = $json['name']) $dataset->setTitle($name);
            if (is_int($count = $json['feature_count'])) $dataset->feature_count = $count;
            if (is_string($path = $json['upload_file_path'])) $dataset->upload_file_path = $path;
            // if name_property, validate first
            $dataset->setNameProperty($json['name_property']);

            // for point dataset, make sure that coordinates are not provided with too many decimal places
            if ($dataset->getType() === 'Point') {
                foreach ($dataset->getFeatures() as $feature) {
                    $coords = $feature->getCoordinatesJson();
                    $feature->setCoordinatesJson([round($coords[0], 7), round($coords[1], 7)]);
                }
            }

            return $dataset;
        }
        else return null;
    }
    /**
     * Builds a dataset from an existing markdown file. Calls fromArray to validate options from the file header.
     * 
     * @param MarkdownFile $file The file with the dataset
     * 
     * @return Dataset|null New Dataset if provided file exists
     */
    public static function fromFile(MarkdownFile $file): ?Dataset {
        if ($file->exists()) {
            $dataset = self::fromArray((array)($file->header()), true);
            $dataset->setFile($file);
            return $dataset;
        }
        else return null;
    }
    /**
     * Builds a dataset from an array. Validates various values. If no feature_type is found, no features will be considered valid. Can also be used to create a blank dataset.
     * 
     * @param array $options (can be blank)
     * @param bool $yaml Indicates whether features are provided with yaml or json coordinates, default true
     * 
     * @return Dataset New Dataset with any provided options
     */
    public static function fromArray(array $options, bool $yaml = true): Dataset {
        $dataset = new Dataset([]);
        // set reserved options
        if ($file = $options['file']) $dataset->setFile($file);
        if ($id = $options['id']) $dataset->setId($id);
        if (is_string($path = $options['upload_file_path'])) $dataset->upload_file_path = $path;
        $dataset->feature_type = Feature::validateFeatureType($options['feature_type'] ?? $options['type']);
        if (is_numeric($count = $options['feature_count'])) $dataset->feature_count = $count;
        if (is_bool($ready = $options['ready_for_update'])) $dataset->ready_for_update = $ready;
        // set standard blueprint options
        $dataset->setValues($options);
        $dataset->setFeatures($options['features'] ?? [], $yaml);
        return $dataset;
    }
    /**
     * Builds a dataset by combining a Dataset object with an array of overrides from a tour
     * 
     * @param Dataset $original The original dataset (added to the tour)
     * @param array $yaml The dataset overrides from the tour header
     * 
     * @return Dataset Dataset with merged icon, path, active_path, attribution, auto_popup_properties, and/or legend
     */
    public static function fromTour(Dataset $original, array $yaml): Dataset {
        $dataset = $original->clone();
        $dataset->setFeatures([]); // no need to store features
        // merge icon and path options
        foreach (['icon', 'path', 'active_path', 'border', 'active_border'] as $key) {
            if ($yaml[$key]) $dataset->$key = self::mergeArrays($dataset->$key, $yaml[$key]);
        }
        // overwrite attribution, auto_popup_properties if set
        foreach (['attribution', 'auto_popup_properties'] as $key) {
            if ($value = $yaml[$key]) $dataset->$key = $value;
        }
        // merge legend options - summary is special
        // todo: provide summary even if not text? use symbol alt as backup?
        if (is_array($yaml['legend'])) $legend = $yaml['legend'];
        else $legend = [];
        $dataset->legend = self::mergeArrays($original->getLegend(), $legend);
        $summary = $legend['summary'] ?: $legend['text'] ?: $original->getLegend()['summary'] ?: $original->getLegend()['text'] ?: $dataset->getLegend()['symbol_alt'];
        if ($summary) $dataset->legend['summary'] = $summary;
        return $dataset;
    }

    // Object Methods

    /**
     * Takes yaml update array from dataset header and validates it.
     * 
     * @param array $yaml Dataset header info
     * 
     * @return array Updated yaml to save
     */
    public function update(array $yaml): array {
        $this->setValues($yaml);
        $this->updateFeatures($yaml['features']);
        $this->renameProperties($yaml['rename_properties']);
        $this->ready_for_update = false; // changes have happened
        return $this->toYaml();
    }
    /**
     * Updates features based on changes from dataset config. Any features not in yaml list will be removed. Any existing features in yaml list will be updated (only valid changes). Any new features will be given new ids and added.
     * 
     * @param array $yaml Features yaml from dataset.md
     */
    public function updateFeatures($features_yaml): void {
        if (null === $features_yaml) {
            $this->features = [];
            return;
        }
        else if (!is_array($features_yaml)) return;
        $features = [];
        // loop through list to find existing and new features
        foreach ($features_yaml as $feature_yaml) {
            $id = $feature_yaml['id'];
            // existing features - call feature's update function and add to list (make sure not a duplicate, though)
            if (($id = $feature_yaml['id']) && ($feature = $this->getFeatures()[$id]) && (!$features[$id])) {
                $feature->update($feature_yaml);
                $features[$id] = $feature;
            }
            // new features
            else {
                if ($feature = Feature::fromArray($feature_yaml, $this->getType())) {
                    if ($id = $this->nextFeatureId()) {
                        $feature->setId($id, true);
                        $feature->setDataset($this);
                        $features[$id] = $feature;
                    }
                }
            }
        }
        $this->features = $features;
    }
    /**
     * Renames any properties where values are provided, assuming the value does not match an existing property name.
     * 
     * @param array $rename_properties
     */
    public function renameProperties($rename_properties): void {
        if (!is_array($rename_properties)) return;
        foreach ($rename_properties as $old => $new) {
            if (!$new) continue;
            // if value, make sure that it does not match an existing property
            $props = $this->getProperties();
            $keys = array_keys($props, $old, true);
            if (!in_array($new, $props) && !empty($keys)) {
                // replace property in $this->properties
                $this->properties[$keys[0]] = $new;
                // replace property in name_property and auto_popup_properties
                if ($this->getNameProperty() === $old) $this->setNameProperty($new);
                $auto = $this->getAutoPopupProperties();
                if (in_array($old, $auto)) {
                    $auto[array_keys($auto, $old)[0]] = $new;
                    $this->setAutoPopupProperties($auto);
                }
                // replace property for all features
                foreach ($this->getFeatures() as $id => $feature) {
                    $props = $feature->getProperties();
                    if ($value = $props[$old]) {
                        unset($props[$old]);
                        $props[$new] = $value;
                        $feature->setProperties($props);
                    }
                }
            }
        }
    }
    /**
     * Turns a temporary dataset created from a json file upload into a proper dataset page - sets id, title, route/file, name_property, etc.
     * 
     * @param string $file_name Name of uploaded file (for creating id and possibly name)
     * @param array $dataset_ids To ensure that created id is unique
     */
    public function initialize(string $file_name, array $dataset_ids): void {
        $id = $this->initId($file_name, $dataset_ids);
        $this->setId($id, true);
        $this->initNameAndRoute();
        // set ids, dataset for features
        $features = [];
        foreach ($this->features as $feature) {
            $id = $this->nextFeatureId();
            $feature->setId($id, true);
            $feature->setDataset($this);
            $features[$id] = $feature;
        }
        $this->features = $features;
        // set path defaults
        if ($this->feature_type !== 'Point') {
            $this->path = self::DEFAULT_PATH;
            $this->active_path = self::DEFAULT_ACTIVE_PATH;
            $this->border = self::DEFAULT_BORDER;
        }
        if (!$this->getNameProperty() || $this->getNameProperty() === 'none') $this->setNameProperty(self::determineNameProperty($this->getProperties()));
    }
    private function initId(string $file_name, array $ids) {
        // clean up file name and create id, make sure id is unique
        $file_name = preg_replace('/(.js|.json)$/', '', $file_name);
        $id = $base_id = str_replace(' ', '-', $file_name);
        $count = 1;
        while (in_array($id, $ids)) {
            $id = "$base_id-$count";
            $count++;
        }
        return $id;
    }
    private function initNameAndRoute() {
        // determine title and route, make sure route is unique
        $name = $base_name = $this->getTitle() ?: $this->getId();
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $pages = Grav::instance()['locator']->findResource('page://');
        $type = 'point_dataset';
        if ($this->getType() !== 'Point') $type = 'shape_dataset';
        $route =  "$pages/datasets/$slug/$type.md";
        $count = 1;
        while (MarkdownFile::instance($route)->exists()) {
            $name = "$base_name-$count";
            $route = "$pages/datasets/$slug-$count/$type.md";
            $count++;
        }
        $this->setTitle($name);
        $this->setFile(MarkdownFile::instance($route));
    }
    /**
     * Only works if the dataset has a file object set. Generates yaml content and saves it to the file header.
     */
    public function save(): void {
        if ($file = $this->file) {
            $file->header($this->toYaml());
            $file->save();
        }
    }
    /**
     * Updates an existing dataset with features, properties, and feature_count from an update. Called after confirmation of an update (from uploading an update file in the admin config)
     * 
     * @param Dataset $update The temporary dataset with the updated information to transfer
     */
    public function applyUpdate(Dataset $update): void {
        // set features, properties, and feature_count
        $this->features = $update->getFeatures();
        $this->setProperties($update->getProperties());
        // trigger property validation
        $this->setNameProperty($this->getNameProperty());
        $this->setAutoPopupProperties($this->getAutoPopupProperties());
        $this->feature_count = $update->getFeatureCount();
        $this->save();
    }
    /**
     * Modifies a temporary dataset with changes from a replacement update.
     * 
     * @param string $dataset_prop The property for identifying features from the dataset
     * @param null|string $file_prop The property for identifying features from the file (if different)
     * @param Dataset $update The parsed upload file defining the changes
     * 
     * @return array [id => name] of matched features
     */
    public function updateReplace(string $dataset_prop, ?string $file_prop, Dataset $update): array {
        $update_features = [];
        $new = [];
        if ($dataset_prop !== 'none') $matches = $this->matchFeatures($dataset_prop, $file_prop, $update->getFeatures(), $new, $update_features);
        else {
            $matches = [];
            $update_features = $update->getFeatures();
        }
        $modified = $this->modifyMatches($matches);
        $features = [];
        // keep features in the order they have in replacement file
        foreach ($update_features as $update_feature) {
            // check if match
            if (($id = $update_feature->getId()) && ($match = $matches[$id])) {
                $feature = $this->getFeatures()[$id]->clone();
                $feature->setProperties(array_merge($feature->getProperties(), $match->getProperties()));
                $feature->setCoordinatesJson($match->getCoordinatesJson());
                $features[$id] = $feature;
            } else {
                // add new feature
                $id = $this->nextFeatureId();
                $update_feature->setId($id, true);
                $update_feature->setDataset($this);
                $features[$id] = $update_feature;
            }
        }
        $this->features = $features;
        $this->properties = $update->getProperties();
        return $modified;
    }
    public function updateRemove(string $dataset_prop, ?string $file_prop, Dataset $update): array {
        $update_features = [];
        $matches = $this->matchFeatures($dataset_prop, $file_prop, $update->getFeatures(), $update_features);
        $modified = $this->modifyMatches($matches);
        $features = [];
        foreach ($this->features as $id => $feature) {
            if (!$matches[$id]) $features[$id] = $feature;
        }
        $this->features = $features;
        return $modified;
    }
    public function updateStandard(string $dataset_prop, ?string $file_prop, ?bool $add, ?bool $modify, ?bool $remove, Dataset $update): array {
        $update_features = [];
        $matches = $this->matchFeatures($dataset_prop, $file_prop, $update->getFeatures(), $update_features);
        $modified = $this->modifyMatches($matches);
        $features = [];
        // modify and remove
        foreach ($this->features as $id => $feature) {
            if ($match = $matches[$id]) {
                if ($modify) {
                    // modify coordinates and properties
                    $feature->setProperties(array_merge($feature->getProperties(), $match->getProperties()));
                    $feature->setCoordinatesJson($match->getCoordinatesJson());
                }
                $features[$id] = $feature;
            } else if (!$remove) {
                $features[$id] = $feature;
            }
        }
        // add features
        if ($add) {
            foreach ($update_features as $feature) {
                $id = $this->nextFeatureId();
                $feature->setId($id);
                $feature->setDataset($this);
                $features[$id] = $feature;
            }
        }
        $this->features = $features;
        if ($modify || $add) $this->properties = array_unique(array_merge($this->properties, $update->getProperties()));
        return $modified;
    }
    private function matchFeatures(string $dataset_prop, ?string $file_prop, array $update_features, &$new, &$all = null): array {
        $matches = [];
        $new = [];
        $has_all = (null !== $all);
        if ($dataset_prop === 'coords') {
            // index current features by coords
            $coords_index = [];
            foreach($this->features as $id => $feature) {
                $coords = json_encode($feature->getCoordinatesJson());
                $coords_index[$coords] = $id;
            }
            // look for matches
            foreach ($update_features as $feature) {
                $coords = json_encode($feature->getCoordinatesJson());
                if ($id = $coords_index[$coords]) {
                    $matches[$id] = $feature;
                    if ($has_all) {
                        $feature->setId($id);
                        $all[] = $feature;
                    }
                } else {
                    $new[] = $feature;
                    if ($has_all) $all[] = $feature;
                }
            }
        } else {
            // set file_prop
            if (!$file_prop) $file_prop = $dataset_prop;
            // index current features by property
            $index = [];
            foreach ($this->features as $id => $feature) {
                if (!empty($val = $feature->getProperty($dataset_prop))) $index[$val] = $id;
            }
            // look for matches
            foreach ($update_features as $feature) {
                if (!empty($val = $feature->getProperty($file_prop)) && ($id = $index[$val])) {
                    $matches[$id] = $feature;
                    if ($has_all) {
                        $feature->setId($id);
                        $all[] = $feature;
                    }
                }
                else{
                    $new[] = $feature;
                    if ($has_all) $all[] = $feature;
                }
            }
        }
        return $matches;
    }
    private function modifyMatches(array $matches): array {
        $new = [];
        foreach (array_keys($matches) as $id) {
            $new[$id] = $this->features[$id]->getName();
        }
        return $new;
    }
    /**
     * The only way feature_count should be modified. Combines dataset id and feature_count, then increments the count
     * 
     * @return string|null A string in the form of dataset_id--number where number is equal to the current feature_count (assuming there is not already a feature with that id), null if the dataset does not have an id
     */
    private function nextFeatureId(): ?string {
        if ($this->id) { // just in case, should be checked before calling
            $this->feature_count ??= 0; // just in case, may not be set when first feature is initialized
            do {
                $id = $this->id . '--' . $this->feature_count;
                $this->feature_count++;
            } while ($this->features[$id]);
            return $id;
        }
        else return null;
    }
    // replace null or empty string with other value
    private static function mergeArrays(?array $a1, ?array $a2): array {
        $a1 ??= [];
        $a2 ??= [];
        $merged = [];
        foreach (array_keys(array_merge($a1, $a2)) as $key) {
            if ($a2[$key] === '') $a2[$key] = null;
            $merged[$key] = $a2[$key] ?? $a1[$key];
        }
        return $merged;
    }
    
    /**
     * Creates an identical copy of the dataset
     */
    public function clone(): Dataset {
        $dataset = new Dataset([]);
        foreach (get_object_vars($this) as $key => $value) {
            $dataset->$key = $value;
        }
        // make sure features is a deep copy and references the correct dataset
        $features = [];
        foreach ($this->features ?? [] as $id => $feature) {
            $feature = $feature->clone();
            $feature->setDataset($dataset);
            $features[$id] = $feature;
        }
        $dataset->features = $features;
        return $dataset;
    }
    public function __toString() {
        return json_encode($this->toYaml());
    }
    public function equals(Dataset $other): bool {
        $vars1 = get_object_vars($this);
        $vars1['features'] = $this->getFeaturesYaml();
        $vars2 = get_object_vars($other);
        $vars2['features'] = $other->getFeaturesYaml();
        return ($vars1 == $vars2);
    }
    /**
     * @return array Dataset yaml array that can be saved in dataset.md. Potentially any and all values from self::YAML
     */
    public function toYaml(): array {
        $yaml = get_object_vars($this);
        // remove file
        unset($yaml['file']);
        $yaml['features'] = $this->getFeaturesYaml();
        // remove and replace extras
        unset($yaml['extras']);
        $yaml = array_merge($this->getExtras() ?? [], $yaml);
        if ($this->getType() === 'Point') {
            unset($yaml['path']);
            unset($yaml['active_path']);
            unset($yaml['border']);
            unset($yaml['active_border']);
        } else {
            unset($yaml['icon']);
        }
        return $yaml;
    }

    // Calculated Getters

    /**
     * @return string|null title or id if available
     */
    public function getName(): ?string {
        return $this->title ?: $this->id;
    }
    /**
     * @return array $this->features in the form they would be saved to the dataset file
     */
    public function getFeaturesYaml(): array {
        $yaml = [];
        foreach ($this->getFeatures() as $id => $feature) {
            $yaml[] = $feature->toYaml();
        }
        return $yaml;
    }
    /**
     * @return array $this->icon with defaults filled in
     */
    public function getIconOptions(): array {
        $icon = $this->icon ?? [];
        // set appropriate defaults to reference
        if ($icon['file']) $icon = array_merge(self::CUSTOM_MARKER_FALLBACKS, $icon);
        else $icon = array_merge(self::DEFAULT_MARKER_FALLBACKS, $icon);

        // merging isn't enough - need to set/modify some things
        // icon urls
        $route = Utils::IMAGE_ROUTE . 'icons/';
        if ($file = $icon['file']) $icon['iconUrl'] = "$route$file";
        if ($file = $icon['retina']) $icon['iconRetinaUrl'] = "$route$file";
        if ($file = $icon['shadow']) $icon['shadowUrl'] = "$route$file";
        // sizes and anchors
        $icon['iconSize'] = [$icon['width'], $icon['height']];
        $icon['tooltipAnchor'] = [$icon['tooltip_anchor_x'], $icon['tooltip_anchor_y']];
        if (is_numeric($x = $icon['anchor_x']) && is_numeric($y = $icon['anchor_y'])) $icon['iconAnchor'] = [$x, $y];
        if ($icon['shadowUrl']) {
            $icon['shadowSize'] = [$icon['shadow_width'] ?? $icon['width'], $icon['shadow_height'] ?? $icon['height']];
            if (is_numeric($x = $icon['shadow_anchor_x']) && is_numeric($y = $icon['shadow_anchor_y'])) $icon['shadowAnchor'] = [$x, $y];
        }
        // other
        if ($class = $icon['class']) $icon['className'] .= " $class";

        return $icon;
    }
    /**
     * @return array [color => string, weight => number] - any other non-fill values will be included
     */
    public function getStrokeOptions(): array {
        $path = array_merge(self::DEFAULT_PATH, $this->path);
        return $this->removeFill($path);
    }
    /**
     * @return array [color => string, weight => number] - any other non-fill values will be included
     */
    public function getActiveStrokeOptions(): array {
        $path = array_merge(self::DEFAULT_PATH, $this->path, $this->active_path);
        return $this->removeFill($path);
    }
    public function getFillOptions(): array {
        if ($this->isLine()) return [];
        else return [
            'fill' => $this->path['fill'] ?? self::DEFAULT_PATH['fill'],
            'fillColor' => $this->path['fillColor'] ?? $this->path['color'] ?? self::DEFAULT_PATH['color'],
            'fillOpacity' => $this->path['fillOpacity'] ?? self::DEFAULT_PATH['fillOpacity']
        ];
    }
    public function getActiveFillOptions(): array {
        if ($this->isLine()) return [];
        $fill = $this->getFillOptions();
        return [
            'fill' => $this->active_path['fill'] ?? $fill['fill'],
            'fillColor' => $this->active_path['fillColor'] ??$this->path['fillColor'] ?? $this->active_path['color'] ?? $fill['fillColor'],
            'fillOpacity' => $this->active_path['fillOpacity'] ?? $fill['fillOpacity']
        ];
    }
    public function getBorderOptions(): array {
        if ($this->hasBorder()) {
            $stroke = $this->getStrokeOptions();
            $path = array_merge($stroke, $this->border);
            // set weight
            $width = $this->border['weight'] ?? self::DEFAULT_BORDER['weight'];
            if ($stroke['stroke']) $path['weight'] = $stroke['weight'] + ($width * 2);
            else $path['weight'] = $width;
            return $this->removeFill($path);
        }
        else return [];
    }
    public function getActiveBorderOptions(): array {
        if ($this->hasBorder()) {
            $border_options = $this->getBorderOptions();
            $active_options = $this->getActiveBorder();
            $border = [
                'stroke' => $active_options['stroke'] ?? true,
                'color' => $active_options['color'] ?? $border_options['color'],
                'opacity' => $active_options['opacity'] ?? $this->getActivePath()['opacity'] ?? $border_options['opacity'],
            ];
            $width = $active_options['weight'] ?? $this->getBorder()['weight'] ?? self::DEFAULT_BORDER['weight']; // can't use getBorderOptions, because weight will have been modified
            $stroke = $this->getStrokeOptions();
            if ($stroke['stroke']) $border['weight'] = $stroke['weight'] + ($width * 2);
            else $border['weight'] = $width;
            return $this->removeFill($border);
        }
        else return [];
    }
    public function hasBorder(): bool {
        return ($this->border['stroke'] && $this->border['color']);
    }
    private function removeFill(array $path): array {
        $path['fill'] = false;
        unset($path['fillColor']);
        unset($path['fillOpacity']);
        return $path;
    }
    private function isLine(): bool {
        return (str_contains($this->getType(), 'LineString'));
    }

    // Getters
    
    /**
     * @return MarkdownFile|null $this->file
     */
    public function getFile(): ?MarkdownFile {
        return $this->file;
    }
    /**
     * @return string|null $this->id
     */
    public function getId(): ?string {
        return $this->id;
    }
    /**
     * @return string|null $this->file_upload_path
     */
    public function getUploadFilePath(): ?string {
        return $this->upload_file_path;
    }
    /**
     * @return string|null $this->feature_type This should never be null, but it theoretically could.
     */
    public function getType(): ?string {
        return $this->feature_type;
    }
    /**
     * @return int $this->feature_count, 0 if null
     */
    public function getFeatureCount(): int {
        return $this->feature_count ??= 0;
    }
    /**
     * @return bool $this->ready_for_update
     */
    public function isReadyForUpdate(): bool {
        return $this->ready_for_update;
    }
    /**
     * @return string|null $this->title
     */
    public function getTitle(): ?string {
        return $this->title;
    }
    /**
     * @return string|null $this->attribution
     */
    public function getAttribution(): ?string {
        return $this->attribution;
    }
    /**
     * @return array $this->legend
     */
    public function getLegend(): array {
        return $this->legend ?? [];
    }
    /**
     * @return array [$id => Feature], $this->features
     */
    public function getFeatures(): array {
        return $this->features;
    }
    /**
     * @return array $this->properties
     */
    public function getProperties(): array {
        return $this->properties ?? [];
    }
    /**
     * @return string|null $this->name_property
     */
    public function getNameProperty(): ?string {
        return $this->name_property;
    }
    /**
     * @return array $this->auto_popup_properties
     */
    public function getAutoPopupProperties(): array {
        return $this->auto_popup_properties;
    }
    /**
     * @return array An array with all non-reserved and non-blueprint properties attached to the object, if any.
     */
    public function getExtras(): array {
        return $this->extras;
    }
    public function getIcon(): array {
        return $this->icon;
    }
    public function getPath(): array {
        return $this->path;
    }
    public function getActivePath(): array {
        return $this->active_path;
    }
    public function getBorder(): array {
        return $this->border;
    }
    public function getActiveBorder(): array {
        return $this->active_border;
    }

    // Setters

    /**
     * Sets blueprint key values (except features) and extras array. Ignores reserved values (and file).
     * 
     * @param array $options Array of values to set
     */
    public function setValues(array $options): void {
        // set blueprint key values (except features)
        $this->setTitle($options['title']);
        $this->setAttribution($options['attribution']);
        $this->setLegend($options['legend']);
        $this->setProperties($options['properties']);
        $this->setNameProperty($options['name_property']);
        $this->setAutoPopupProperties($options['auto_popup_properties']);
        $this->setIcon($options['icon']);
        $this->setPath($options['path']);
        $this->setActivePath($options['active_path']);
        $this->setBorder($options['border']);
        $this->setActiveBorder($options['active_border']);
        // set extras
        $this->setExtras($options);
    }

    // setters
    /**
     * @param MarkdownFile $file Sets $this->file
     */
    public function setFile(MarkdownFile $file): void {
        $this->file = $file;
    }
    /**
     * Will not set id to null
     * 
     * @param string $id Sets $this->id (by default only if not already set)
     * @param bool $overwrite - if true, $this->id will be set even if already set
     */
    public function setId($id, $overwrite = false): void {
        if(is_string($id) && !empty($id)) {
            if (!$this->id || $overwrite) $this->id = $id;
        }
    }
    public function setReadyForUpdate(bool $ready): void {
        $this->ready_for_update = $ready;
    }
    /**
     * @param string $title Sets $this->title (empty string ignored)
     */
    public function setTitle($title): void {
        if (is_string($title) && !empty($title)) $this->title = $title;
    }
    /**
     * @param string|null $text
     */
    public function setAttribution($text): void {
        if (is_string($text)) $this->attribution = $text;
        else $this->attribution = null;
    }
    /**
     * @param array|null $legend
     */
    public function setLegend($legend): void {
        if (is_array($legend)) $this->legend = $legend;
        else $this->legend = [];
    }
    /**
     * Sets features - does not use array of Feature objects but creates them from array. Not used when updating dataset.
     * 
     * @param array $features Array of feature data
     * @param bool $yaml Indicates whether feature coordinates are in yaml or json form, default true
     */
    public function setFeatures(array $features, bool $yaml = true): void {
        $this->features = [];
        foreach ($features as $feature) {
            if ($feature = Feature::fromArray($feature, $this->getType(), $yaml)) {
                // valid feature - add to array and set dataset
                $feature->setDataset($this);
                if ($id = $feature->getId()) $this->features[$id] = $feature;
                else $this->features[] = $feature;
            }
        }
    }
    /**
     * @param array|null $properties
     */
    public function setProperties($properties): void {
        if (is_array($properties)) $this->properties = $properties;
        else $this->properties = [];
    }
    /**
     * @param string|null $property Sets name_property if valid, null, or 'none'
     */
    public function setNameProperty($property): void {
        if (is_string($property)) {
            if ($property === 'none' || in_array($property, $this->getProperties())) $this->name_property = $property;
            else $this->name_property = 'none';
        }
        else $this->name_property = null;
    }
    /**
     * @param array|null $properties - sets and validates
     */
    public function setAutoPopupProperties($properties): void {
        if (is_array($properties)) {
            $this->auto_popup_properties = array_values(array_intersect($properties, $this->getProperties()));
        }
        else $this->auto_popup_properties = [];
    }
    /**
     * @param array|null $icon
     */
    public function setIcon($icon): void {
        if (is_array($icon)) $this->icon = $icon;
        else $this->icon = [];
    }
    /**
     * @param array|null $path
     */
    public function setPath($path): void {
        if (is_array($path)) $this->path = $path;
        else $this->path = [];
    }
    /**
     * @param array|null $path
     */
    public function setActivePath($path): void {
        if (is_array($path)) $this->active_path = $path;
        else $this->active_path = [];
    }
    /**
     * @param array|null $extras
     */
    public function setExtras($extras) {
        if (is_array($extras)) {
            $this->extras = array_diff_key($extras, array_flip(array_merge(self::$reserved_keys, self::$blueprint_keys, ['file'])));
        }
        else $this->extras = [];
    }
    /**
     * @param array|null $path
     */
    public function setBorder($path): void {
        if (is_array($path)) $this->border = $path;
        else $this->border = [];
    }
    /**
     * @param array|null $path
     */
    public function setActiveBorder($path): void {
        if (is_array($path)) $this->active_border = $path;
        else $this->active_border = [];
    }

    // static methods

    /**
     * First priority is property called name, next is property beginning or ending with name, and last resort is first property, if available
     * @return string|null The value for the name_property
     */
    public static function determineNameProperty(array $properties): ?string {
        $name_prop = '';
        foreach ($properties as $prop) {
            if (strcasecmp($prop, 'name') == 0) return $prop;
            else if (empty($name_prop) && preg_match('/^(.*name|name.*)$/i', $prop)) $name_prop = $prop;
        }
        if (empty($name_prop)) $name_prop = $properties[0];
        if ($name_prop) return $name_prop;
        else return null;
    }
}
?>