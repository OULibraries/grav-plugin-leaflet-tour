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
        'color' => '#3388ff',
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

    private static array $reserved = ['file', 'upload_file_path', 'id', 'feature_type', 'feature_count'];

    /**
     * File storing the dataset, typically created on dataset initialization, never modified once set
     */
    private ?MarkdownFile $file = null;
    /**
     * Optional value to enable deleting unnecessary files if dataset is deleted, only set in fromJson
     */
    private ?string $upload_file_path = null;
    /**
     * Unique identifier, created on dataset initialization using the upload file name, never modified once set
     */
    private ?string $id = null;
    /**
     * Identifies the dataset to users
     */
    private ?string $title = null;
    /**
     * Point, LineString, MultiLineString, Polygon, or MultiPolygon, set in fromJson, never modified
     */
    private ?string $feature_type = null;
    /**
     * [$id => Feature, ...], the features contained by the dataset, must have valid coordinates for the dataset feature_type, never directly set, but can be updated
     */
    private array $features = [];
    /**
     * A running total of all dataset features, includes features that have been removed, used to create unique ids for new features, never modified directly
     */
    private ?int $feature_count = null;
    /**
     * A list of property keys that each feature should have / is allowed to have
     */
    private array $properties = [];
    /**
     * Properties used to generate auto popup content for features. Must be in the dataset properties list
     */
    private ?array $auto_popup_properties = null;
    /**
     * The property used to determine a feature's name when its custom_name is not set, must be in the dataset properties list
     */
    private ?string $name_property = null;
    /**
     * Text to provide attribution for the dataset, will be added automatically to tours that use it
     */
    private ?string $attribution = null;
    /**
     * [text, summary, symbol_alt]
     */
    private ?array $legend = null;
    /**
     * Icon options, based on Leaflet icon options, but the yaml for storage is slightly different
     */
    private ?array $icon = null;
    /**
     * Path options from Leaflet path options
     */
    private ?array $path = null;
    private ?array $active_path = null;

    private bool $ready_for_update = false;

    /**
     * Never called directly by anything but the construction methods (and clone) - construction methods will take care of any necessary validation
     */
    private function __construct(array $options) {
        foreach ($options as $key => $value) {
            try {
                $this->$key = $value;
            } catch (\Throwable $t) {
                // do nothing
            }
        }
    }
    /**
     * Builds a dataset from an existing markdown file. Validates the options set in the header.
     * @param MarkdownFile $file The file with the dataset
     * @return null|Dataset New Dataset if provided file exists
     */
    public static function fromFile(MarkdownFile $file): ?Dataset {
        if ($file->exists()) {
            $options = (array)($file->header());
            $options['file'] = $file;
            return self::fromArray($options, true);
        }
        else return null;
    }
    /**
     * Creates a new dataset from an uploaded json file. Determines feature_type, features, and properties. May also set name, name_property, upload_file_path and feature_count if provided.
     * @param array $json GeoJson array with features
     * @return null|Dataset New Dataset if at least one feature is valid
     */
    public static function fromJson(array $json): ?Dataset {
        // loop through json features and try creating new Feature objects
        $type = null; // set by first valid feature
        $features = $properties = []; // to fill
        foreach ($json['features'] ?? [] as $feature_json) {
            if ($feature = Feature::fromJson($feature_json, $type)) {
                $type ??= $feature->getType();
                $features[] = $feature;
                $properties = array_merge($properties, $feature->getProperties());
            }
        }
        if (!empty($features)) {
            $options = ['feature_type' => $type, 'features' => $features, 'properties' => array_keys($properties)];
            // set optional values
            if ($name = $json['name']) $options['title'] = $name;
            if ($count = $json['feature_count']) $options['feature_count'] = $count;
            if ($path = $json['upload_file_path']) $options['upload_file_path'] = $path;
            $dataset = new Dataset($options);
            // if name_property, validate first
            if ($property = $json['name_property']) $dataset->setNameProperty($property);
            return $dataset;
        }
        // TODO: Possibly go back and check for points that aren't listed as points
        else return null;
    }
    /**
     * Builds a dataset from an array, but does some validation on feature_type and features. Can also be used to create a blank dataset. If no feature_type is found, no features will be considered valid.
     * @param null|array $options Properties the newly created dataset object will have
     * @param bool
     * @return Dataset New Dataset with any provided options
     */
    public static function fromArray(?array $options, bool $yaml = true): Dataset {
        // validate feature type (if provided)
        $options['feature_type'] = Feature::validateFeatureType($options['feature_type'] ?? $options['type'] ?? 'point');
        $features = [];
        foreach ($options['features'] ?? [] as $feature) {
            if (!$yaml) $feature['coordinates'] = Feature::coordinatesToYaml($feature['coordinates'] ?? [], $options['feature_type']);
            if ($feature = Feature::fromDataset($feature, null, $options['feature_type'])) {
                if ($id = $feature->getId()) $features[$id] = $feature;
                else $features[] = $feature;
            }
        }
        $options['features'] = $features;
        $dataset = new Dataset($options);
        foreach ($dataset->getFeatures() as $id => $feature) {
            $feature->setDataset($dataset);
        }
        return $dataset;
    }
    /**
     * Builds a dataset by combining a Dataset object with an array of overrides from a tour
     * @param Dataset $original The original dataset (added to the tour)
     * @param array $yaml The dataset overrides from the tour header
     * @return Dataset Dataset with merged icon, path, active_path, attribution, auto_popup_properties, and/or legend
     */
    public static function fromTour(Dataset $original, array $yaml): Dataset {
        $dataset = $original->clone();
        $dataset->features = [];
        // merge icon and path options
        foreach (['icon', 'path', 'active_path'] as $key) {
            if ($yaml[$key]) $dataset->$key = self::mergeArrays($dataset->$key, $yaml[$key]);
        }
        // overwrite attribution, auto_popup_properties if set
        foreach (['attribution', 'auto_popup_properties'] as $key) {
            if ($value = $yaml[$key]) $dataset->$key = $value;
        }
        // merge legend options - summary is special
        // todo: provide summary even if not text? use symbol alt as backup?
        $legend = $yaml['legend'] ?? [];
        $dataset->legend = self::mergeArrays($original->getLegend(), $legend);
        $summary = $legend['summary'] ?: $legend['text'] ?: $original->getLegend()['summary'] ?: $original->getLegend()['text'] ?: $dataset->getLegend()['symbol_alt'];
        if ($summary) $dataset->legend['summary'] = $summary;
        return $dataset;
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

    // object methods
    
    /**
     * Creates an identical copy of the dataset
     */
    public function clone(): Dataset {
        $options = [];
        foreach (get_object_vars($this) as $key => $value) {
            $options[$key] = $value;
        }
        // make sure features is a deep copy
        $features = [];
        foreach ($this->features ?? [] as $id => $feature) {
            $features[$id] = $feature->clone();
        }
        $options['features'] = $features;
        return new Dataset($options);
    }
    /**
     * @return array Dataset yaml array that can be saved in dataset.md. Potentially any and all values from self::YAML
     */
    public function asYaml(): array {
        $yaml = [
            'id' => $this->getId(),
            'upload_file_path' => $this->getUploadFilePath(),
            'feature_type' => $this->getType(),
            'title' => $this->getTitle(),
            'name_property' => $this->getNameProperty(),
            'properties' => $this->getProperties(),
            'auto_popup_properties' => $this->auto_popup_properties,
            'features' => $this->getFeaturesYaml(),
            'feature_count' => $this->getFeatureCount(),
            'attribution' => $this->attribution,
            'legend' => $this->legend,
            'ready_for_update' => $this->ready_for_update,
        ];
        foreach (['icon', 'path', 'active_path'] as $key) {
            if ($value = $this->$key) $yaml[$key] = $value;
        }
        return $yaml;
    }
    /**
     * Takes yaml update array from dataset header and validates it.
     * @param array $update Dataset header
     * @return array Updated yaml to save
     */
    public function update(array $yaml): array {
        // set certain expected values
        $this->updateFeaturesYaml($yaml['features'] ?? []);
        $this->properties = $yaml['properties'] ?? [];
        $this->setAutoPopupProperties($yaml['auto_popup_properties']);
        $this->setNameProperty($yaml['name_property']);
        $this->attribution = $yaml['attribution'];
        $this->ready_for_update = false;
        // remove reserved values and expected (handled) values
        $remove = array_merge(self::$reserved, ['features', 'properties', 'auto_popup_properties', 'name_property', 'attribution', 'ready_for_update']);
        $yaml = array_diff_key($yaml, array_flip($remove));
        foreach ($yaml as $key => $value) {
            switch ($key) {
                case 'title':
                    $this->setTitle($value);
                    break;
                // generic - no special set methods
                default:
                    $this->$key = $value;
                    break;
            }
        }
        return array_merge($yaml, $this->asYaml());
    }
    /**
     * Updates features based on changes from dataset config. 
     * Updates features based on changes from dataset config. Any features not in yaml list will be removed. Any existing features in yaml list will be updated (only valid changes). Any new features will be given new ids and added.
     * 
     * @param array $yaml Features yaml from dataset.md
     */
    public function updateFeaturesYaml(array $features_yaml): void {
        $features = [];
        // loop through list to find existing and new features
        foreach ($features_yaml as $feature_yaml) {
            $id = $feature_yaml['id'];
            // existing features - call feature's update function and add to list (make sure not a duplicate, though)
            if (($feature = $this->features[$id]) && (!$features[$id])) {
                $feature->update($feature_yaml);
                $features[$id] = $feature;
            }
            // new features - remove id before creating new object
            else {
                unset($feature_yaml['id']);
                if ($feature = Feature::fromDataset($feature_yaml, $this)) {
                    $id = $this->nextFeatureId();
                    $feature->setId($id);
                    $features[$id] = $feature;
                }
            }
        }
        $this->features = $features;
    }
    /**
     * Turns a temporary dataset created from a json file upload into a proper dataset page - sets id, title, route/file, name_property, etc.
     * @param string $file_name Name of uploaded file (for creating id and possibly name)
     * @param array $dataset_ids To ensure that created id is unique
     */
    public function initialize(string $file_name, array $dataset_ids): void {
        // clean up file name and create id, make sure id is unique
        $file_name = preg_replace('/(.js|.json)$/', '', $file_name);
        $id = $base_id = str_replace(' ', '-', $file_name);
        $count = 1;
        while (in_array($id, $dataset_ids)) {
            $id = "$base_id-$count";
            $count++;
        }
        $this->id = $id;
        // determine title and route, make sure route is unique
        $name = $base_name = $this->getTitle() ?: $id;
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
        $this->file = MarkdownFile::instance($route);
        // set ids, dataset for features
        $features = [];
        foreach ($this->features as $feature) {
            $id = $this->nextFeatureId();
            $feature->setId($id);
            $feature->setDataset($this);
            $features[$feature->getId()] = $feature;
        }
        $this->features = $features;
        // set path defaults
        if ($this->feature_type !== 'Point') {
            $this->path = self::DEFAULT_PATH;
            $this->active_path = self::DEFAULT_ACTIVE_PATH;
        }
        $this->name_property ??= self::determineNameProperty($this->properties);
    }
    /**
     * If the dataset's file does not yet exist or has an empty header, first generates yaml content to add to header.
     * 
     * Only works if the dataset has a file object set.
     * 
     * @param bool $generate - If set to true, will generate yaml whether or not file header is empty
     */
    public function save(): void {
        if ($file = $this->file) {
            $file->header($this->asYaml());
            $file->save();
        }
    }
    /**
     * The only way feature_count should be modified. Combines dataset id and feature_count, then increments the count
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

    // updates

    /**
     * Updates an existing dataset with features, properties, and feature_count from an update. Called after confirmation of an update (from uploading an update file in the admin config)
     */
    public function applyUpdate(Dataset $update): void {
        // set features, properties, and feature_count
        $this->features = $update->getFeatures();
        $this->setProperties($update->getProperties());
        $this->feature_count = $update->getFeatureCount();
        $this->save();
    }

    /**
     * Modifies a temporary dataset with changes from a replacement update.
     * @param string $dataset_prop The property for identifying features from the dataset
     * @param null|string $file_prop The property for identifying features from the file (if different)
     * @param Dataset $update The parsed upload file defining the changes
     * @return array [id => name] of matched features
     */
    public function updateReplace(string $dataset_prop, ?string $file_prop, Dataset $update): array {
        $update_features = [];
        if ($dataset_prop !== 'none') $matches = $this->matchFeatures($dataset_prop, $file_prop, $update->getFeatures(), $update_features);
        else {
            $matches = [];
            $update_features = $update->getFeatures();
        }
        $modified = $this->modifyMatches($matches);
        $features = [];
        // add matches first
        foreach ($matches as $id => $match) {
            // modifies coordinates and properties
            $feature = $this->features[$id]->clone();
            $feature->setProperties($match->getProperties());
            $feature->setCoordinatesJson($match->getCoordinatesJson());
            $features[$id] = $feature;
        }
        // then add any new features
        foreach ($update_features as $feature) {
            $id = $this->nextFeatureId();
            $feature->setId($id);
            $feature->setDataset($this);
            $features[$id] = $feature;
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

    private function matchFeatures(string $dataset_prop, ?string $file_prop, array $update_features, &$new): array {
        $matches = [];
        $new = [];
        if ($dataset_prop === 'coords') {
            // index current features by coords
            $coords_index = [];
            // fix php json?
            foreach($this->features as $id => $feature) {
                $coords = json_encode($feature->getCoordinatesJson());
                $coords_index[$coords] = $id;
            }
            // look for matches
            foreach ($update_features as $feature) {
                $coords = json_encode($feature->getCoordinatesJson());
                if ($id = $coords_index[$coords]) {
                    $matches[$id] = $feature;
                } else {
                    $new[] = $feature;
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
                if (!empty($val = $feature->getProperty($file_prop)) && ($id = $index[$val])) $matches[$id] = $feature;
                else $new[] = $feature;
            }
        }
        return $matches;
    }
    private function modifyMatches(array $matches): array {
        $new = [];
        foreach ($matches as $id => $feature) {
            $new[$id] = $this->features[$id]->getName();
        }
        return $new;
    }

    // getters
    
    /**
     * @return null|MarkdownFile $this->file
     */
    public function getFile(): ?MarkdownFile {
        return $this->file;
    }
    /**
     * @return null|string $this->id
     */
    public function getId(): ?string {
        return $this->id;
    }
    /**
     * @return null|string $this->file_upload_path
     */
    public function getUploadFilePath(): ?string {
        return $this->upload_file_path;
    }
    /**
     * @return null|string $this->title
     */
    public function getTitle(): ?string {
        return $this->title;
    }
    public function getName(): ?string {
        return $this->title ?: $this->id;
    }
    /**
     * @return string $this->feature_type This should never be null.
     */
    public function getType(): string {
        return $this->feature_type;
    }
    /**
     * @return array [$id => Feature], $this->features
     */
    public function getFeatures(): array {
        return $this->features;
    }
    public function getFeaturesYaml(): array {
        $yaml = [];
        foreach ($this->getFeatures() as $id => $feature) {
            $yaml[] = $feature->asYaml();
        }
        return $yaml;
    }
    /**
     * @return int $feature_count, 0 if null
     */
    public function getFeatureCount(): int {
        return $this->feature_count ??= 0;
    }
    /**
     * @return array $this->properties
     */
    public function getProperties(): array {
        return $this->properties;
    }
    /**
     * @return null|string $this->name_property
     */
    public function getNameProperty(): ?string {
        return $this->name_property;
    }
    /**
     * @return null|array $this->auto_popup_properties
     */
    public function getAutoPopupProperties(): ?array {
        return $this->auto_popup_properties;
    }
    /**
     * @return null|string $this->attribution
     */
    public function getAttribution(): ?string {
        return $this->attribution;
    }
    /**
     * @return null|array $this->legend
     */
    public function getLegend(): array {
        return $this->legend ?? [];
    }
    /**
     * @return array $this->icon with defaults filled in
     */
    public function getIcon(): array {
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
    public function getPath(bool $defaults = false): array {
        if ($defaults) return $this->getPathByType($this->mergeArrays(self::DEFAULT_PATH, $this->path ?? []));
        else return $this->getPathByType($this->path ?? []);
    }
    public function getActivePath(): array {
        return $this->getPathByType($this->active_path ?? []);
    }
    private function getPathByType(array $path): array {
        if (str_contains(strtolower($this->getType()), 'linestring')) {
            return array_intersect_key($path, array_flip(['stroke', 'weight', 'color', 'opacity']));
        }
        else return $path;
    }
    public function isReadyForUpdate(): bool {
        return $this->ready_for_update;
    }

    // setters
    
    /**
     * @param MarkdownFile $file Sets $this->file
     */
    public function setFile(MarkdownFile $file): void {
        $this->file = $file;
    }
    public function setId(string $id): void {
        $this->id ??= $id;
    }
    /**
     * @param string $title Sets $this->title (empty string ignored)
     */
    public function setTitle(string $title): void {
        $this->title = $title ?: $this->title;
    }
    /**
     * @param null|string $property Sets name_property if valid, null, or 'none'
     */
    public function setNameProperty(?string $property): void {
        if (!$property || $property === 'none' || in_array($property, $this->getProperties())) $this->name_property = $property;
        else $this->name_property = 'none';
    }
    /**
     * Sets $this->properties. Removes name_property if no longer valid.
     * @param null|array $properties Sets $this->properties, null becomes empty array
     */
    public function setProperties(?array $properties): void {
        $this->properties = $properties ?? [];
        $this->setNameProperty($this->getNameProperty());
        $this->setAutoPopupProperties($this->getAutoPopupProperties());
    }
    public function setReadyForUpdate(bool $ready): void {
        $this->ready_for_update = $ready;
    }
    public function setAutoPopupProperties(?array $auto_popup_properties): void {
        if ($auto_popup_properties) $this->auto_popup_properties = array_values(array_intersect($auto_popup_properties, $this->properties));
        else $this->auto_popup_properties = null;
    }

    // static methods

    /**
     * First priority is property called name, next is property beginning or ending with name, and last resort is first property, if available
     * @return null|string The value for the name_property
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