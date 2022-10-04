<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;

class Dataset {

    // Default options for initializing a shape dataset and potentially when getting stroke/fill/border options
    const DEFAULT_PATH = [
        'stroke' => true,
        'color' => '#0051C2',
        'weight' => 3,
        'opacity' => 1,
        'fill' => true,
        'fillOpacity' => 0.2
    ];
    const DEFAULT_ACTIVE_PATH = [
        'weight' => 5,
        'fillOpacity' => 0.4
    ];
    const DEFAULT_BORDER = [
        'stroke' => true,
        'color' => '#ffffff',
        'weight' => 2,
    ];
    // Default options for icon when no file is defined (default icon used), used when getting icon options
    const DEFAULT_MARKER_FALLBACKS = [
        'iconUrl' => 'user/plugins/leaflet-tour/images/marker-icon.png',
        'width' => 25,
        'height' => 41,
        'anchor_x' => 12,
        'anchor_y' => 41,
        'tooltip_anchor_x' => 2,
        'tooltip_anchor_y' => 0,
        'shadowUrl' => 'user/plugins/leaflet-tour/images/marker-shadow.png',
        'shadow_width' => 41,
        'shadow_height' => 41,
        'iconRetinaUrl' => 'user/plugins/leaflet-tour/images/marker-icon-2x.png',
        'className' => 'leaflet-marker'
    ];
    // Default options for icon when file is defined (custom icon provided), used when getting icon options
    const CUSTOM_MARKER_FALLBACKS = [
        'width' => 14,
        'height' => 14,
        'tooltip_anchor_x' => 7,
        'tooltip_anchor_y' => 0,
        'className' => 'leaflet-marker'
    ];

    private ?MarkdownFile $file;
    private string $type, $id;
    private ?string $title, $upload_file_path, $attribution, $name_property;
    private int $feature_count;
    private bool $ready_for_update;
    private array $legend, $features, $properties, $auto_popup_properties, $icon, $path, $active_path, $border, $active_border, $extras;

    /**
     * Sets and validates all provided options
     * @param array $options Dataset yaml, possibly with file
     */
    private function __construct(array $options) {
        // validate file
        try { $this->file = $options['file']; }
        catch (\Throwable $t) { $this->file = null; }
        // validate type just in case (should be provided as valid, though)
        $this->type = Feature::validateFeatureType($options['feature_type'] ?? $options['type']);
        // validate id
        $this->id = is_string($options['id']) ? $options['id'] : '';
        // validate strings
        foreach (['title', 'upload_file_path', 'attribution'] as $key) {
            $this->$key = is_string($options[$key]) ? $options[$key] : null;
        }
        // validate feature count
        $this->feature_count = is_int($options['feature_count']) ? $options['feature_count'] : 0;
        // validate ready for update
        $this->ready_for_update = ($options['ready_for_update'] === true);
        // validate arrays
        foreach (['legend', 'properties', 'icon', 'path', 'active_path', 'border', 'active_border'] as $key) {
            $this->$key = is_array($options[$key]) ? $options[$key] : [];
        }
        // validate name property and auto popup properties
        $this->name_property = self::validateNameProperty($options['name_property'], $this->properties);
        $this->auto_popup_properties = self::validateAutoPopupProperties($options['auto_popup_properties'], $this->properties);
        // validate features
        $features = [];
        if (is_array($options['features'])) {
            foreach ($options['features'] as $feature_yaml) {
                $feature = Feature::fromDataset($feature_yaml, $this->type, $this->id, $this->name_property);
                // add feature to array, index by id if possible
                if ($feature && ($id = $feature->getId())) $features[$id] = $feature;
                else if ($feature) $features[] = $feature;
            }
        }
        $this->features = $features;
        // extras
        $keys = ['feature_type', 'id', 'title', 'upload_file_path', 'attribution', 'legend','properties', 'icon', 'path', 'active_path', 'border', 'active_border', 'name_property', 'auto_popup_properties', 'features', 'feature_count', 'ready_for_update', 'file'];
        $this->extras = array_diff_key($options, array_flip($keys));
    }

    /**
     * Builds a dataset from parsed json content
     * @param array $json Parsed json content
     * @return Dataset|null Dataset if at least one valid feature, otherwise null
     */
    public static function fromJson(array $json): ?Dataset {
        // loop through json features and try creating new Feature objects
        $type = null; // to be set by first valid feature
        $features = $properties = []; // to be filled
        foreach ($json['features'] ?? [] as $feature_json) {
            if ($feature = Feature::fromJson($feature_json, $type)) {
                $type ??= $feature->getType(); // set type if this is the first valid feature
                $features[] = $feature->toYaml();
                $properties = array_merge($properties, $feature->getProperties());
            }
        }
        if (!empty($features)) {
            // provide values from json when creating dataset
            return new Dataset([
                'feature_type' => $type,
                'features' => $features,
                'properties' => array_keys($properties),
                'title' => $json['name'],
                'feature_count' => $json['feature_count'],
                'upload_file_path' => $json['upload_file_path'],
                'name_property' => $json['name_property'], array_keys($properties),
            ]);
        }
        else return null;
    }
    public static function fromFile(MarkdownFile $file): Dataset {
        return new Dataset(array_merge($file->header(), ['file' => $file]));
    }
    public static function fromArray(array $options): Dataset {
        return new Dataset($options);
    }
    public static function fromLimitedArray(array $options, array $keys): Dataset {
        return new Dataset(array_intersect_key($options, array_flip($keys)));
    }
    /**
     * Creates a new dataset by merging options from an existing dataset with overrides set by a tour
     */
    public static function fromTour(Dataset $dataset, array $tour_options): Dataset {
        $options = $dataset->toYaml();
        // overwrite attribution adn auto popup properties
        if ($attr = $tour_options['attribution']) $options['attribution'] = $attr;
        if (($props = $tour_options['auto_popup_properties'])) $options['auto_popup_properties'] = $props;
        // merge icon and shape options
        foreach (['icon', 'path', 'active_path', 'border', 'active_border'] as $key) {
            $options[$key] = array_merge($options[$key] ?? [], $tour_options[$key] ?? []);
        }
        // legend
        $legend = $tour_options['legend'] ?? [];
        if (!$legend['text']) {
            $legend['text'] = $dataset->getLegend()['text'];
            $legend['summary'] ??= $dataset->getLegend()['summary'];
        }
        // only use symbol alt from dataset if icon file or stroke/fill/border color also comes from dataset (or default)
        if (!$legend['symbol_alt'] && !($tour_options['icon'] ?? [])['file'] && !($tour_options['path'] ?? [])['color'] && !($tour_options['path'] ?? [])['fillColor'] && !($tour_options['border'] ?? [])['color']) $legend['symbol_alt'] = $dataset->getLegend()['symbol_alt'];
        // default for legend summary
        $legend['summary'] ??= $legend['text'] ?: $legend['symbol_alt'];
        $options['legend'] = $legend;

        $options['features'] = []; // may as well remove features to reduce unneeded validation
        return new Dataset($options);
    }
    /**
     * Creates a new dataset with most settings from the old dataset but features from the new dataset. Matched features retain some values.
     */
    public static function fromUpdateReplace(array $matches, Dataset $old_dataset, Dataset $update_dataset): Dataset {
        $features = [];
        $feature_count = $old_dataset->getFeatureCount();
        // keep features in the order they have in replacement file, but treat new features and existing (matched) features differently
        foreach ($update_dataset->getFeatures() as $update_id => $update_feature) {
            // match feature will have id in matches
            if (($old_id = $matches[$update_id]) && ($old_feature = $old_dataset->getFeatures()[$old_id])) {
                // preserve everything except coordinates and properties (and partially preserve properties)
                $features[] = array_merge($old_feature->toYaml(), [
                    'coordinates' => $update_feature->getYamlCoordinates(),
                    'properties' => array_merge($old_feature->getProperties(), $update_feature->getProperties()),
                ]);
            } else {
                // create new feature id
                // get the correct feature count for the new id - no need to pass in the ids of any other new features, since feature count is being incremented, meaning there is no danger of duplication
                $feature_count = self::nextFeatureCount($old_dataset->getId(), array_keys($old_dataset->getFeatures()), $feature_count);
                $id = $old_dataset->getId() . "--$feature_count";
                $features[] = array_merge($update_feature->toYaml(), ['id' => $id]);
            }
        }
        return new Dataset(array_merge($old_dataset->toYaml(), [
            'features' => $features,
            'feature_count' => $feature_count,
            'properties' => array_unique(array_merge($old_dataset->getProperties(), $update_dataset->getProperties())),
        ]));
    }
    /**
     * Creates a new dataset with only those features that do not have matches
     */
    public static function fromUpdateRemove(array $matches, Dataset $old_dataset): Dataset {
        // only include old features without matches (note that old feature ids are the values, not the keys, for matches array)
        $features = array_diff_key($old_dataset->getFeatures(), array_flip(array_values($matches)));
        return new Dataset(array_merge($old_dataset->toYaml(), [
            'features' => array_values(array_map(function($feature) {
                return $feature->toYaml();
            }, $features)), // turn array of id => Feature, into non-indexed array of feature yaml content
        ]));
    }
    /**
     * Creates a new dataset with most settings from old dataset but features modified by new. Potentially: Adds new (from update, no match) features. Remove old (from original, no match) features. Modify coordinates and properties for matching features.
     */
    public static function fromUpdateStandard(array $matches, Dataset $old_dataset, Dataset $update_dataset, ?bool $add, ?bool $modify, ?bool $remove): Dataset {
        $features = [];
        $feature_count = $old_dataset->getFeatureCount();
        $old_match_ids = array_flip($matches); // change [update id => old id] to [old id => update id]
        foreach ($old_dataset->getFeatures() as $old_id => $old_feature) {
            // if feature matches, either modify the feature (if modify is true) or just make sure to keep the feature
            if ($update_id = $old_match_ids[$old_id]) {
                if ($modify) {
                    $update_feature = $update_dataset->getFeatures()[$update_id];
                    $features[] = array_merge($old_feature->toYaml(), [
                        'coordinates' => $update_feature->getYamlCoordinates(),
                        'properties' => array_merge($old_feature->getProperties(), $update_feature->getProperties()),
                    ]);
                }
                // not modify, keep the feature but don't change it
                else $features[] = $old_feature->toYaml();
            }
            // modify or not, doesn't matter - feature has no match, make sure to keep it if remove is false
            else if (!$remove) $features[] = $old_feature->toYaml();
        }
        // if add is true, loop through all update features and add any that are not new (not matches)
        if ($add) {
            foreach ($update_dataset->getFeatures() as $update_id => $update_feature) {
                if (!$matches[$update_id]) {
                    // create new unique id
                    $feature_count = self::nextFeatureCount($old_dataset->getId(), array_keys($old_dataset->getFeatures()), $feature_count);
                    $id = $old_dataset->getId() . "--$feature_count";
                    $features[] = array_merge($update_feature->toYaml(), ['id' => $id]);
                }
            }
        }
        // combine properties if modify or add is true
        $properties = $old_dataset->getProperties();
        if ($modify || $add) $properties = array_unique(array_merge($properties, $update_dataset->getProperties()));
        return new Dataset(array_merge($old_dataset->toYaml(), [
            'features' => array_values($features),
            'feature_count' => $feature_count,
            'properties' => $properties,
        ]));
    }

    /**
     * Validates potential dataset update - feature_type, properties, features, etc.
     * @param array $update Dataset yaml from update
     * @param array $properties [old name => new name] (from renaming properties, potentially)
     * @return array updated Dataset yaml
     */
    public function validateUpdate(array $update, $properties): array {
        if (!is_array($properties)) $properties = [];
        // validate feature type - to change type: both old and new types must be shape (i.e. not 'Point'), old and new types should be different, either current features or new features should be empty
        $new_type = Feature::validateFeatureType($update['feature_type']);
        if (($this->getType() !== 'Point') && ($new_type !== $this->getType()) && ($new_type !== 'Point') && (empty($this->getFeatures()) || empty($update['features']))) $type = $new_type;
        else $type = $this->getType();
        // validate properties
        $name_property = $properties[$update['name_property']]; // will return a new value if renamed or null if invalid
        // replace auto popup properties with new values if needed
        $auto_popup_properties = [];
        foreach ($update['auto_popup_properties'] ?? [] as $prop) { $auto_popup_properties[] = $properties[$prop] ?? ''; }
        // validate features, reconcile changes
        $features = [];
        $feature_count = $this->getFeatureCount();
        if (is_array($update['features'])) {
            foreach ($update['features'] as $feature_yaml) {
                $feature_array = null;
                // modified feature has id for feature in dataset that has not yet been added to features list (i.e. not a duplicate)
                if (($id = $feature_yaml['id']) && ($old_feature = $this->getFeatures()[$id]) && (!$features[$id])) {
                    // validate feature update (coordinates and popup content)
                    $path = $this->getFile() ? $this->getFile()->filename() : '';
                    $feature_array = $old_feature->validateUpdate($feature_yaml, str_replace(Grav::instance()['locator']->findResource('page://'), '', $path));
                } else {
                    // new feature: make sure feature has valid coordinates (otherwise ignore it) and give it a proper unique id
                    if ($coords = Feature::validateYamlCoordinates($feature_yaml['coordinates'], $type)) {
                        $feature_count = self::nextFeatureCount($this->getId(), array_keys($this->getFeatures()), $feature_count);
                        $id = $this->getId() . "--$feature_count";
                        $feature_array = array_merge($feature_yaml, ['coordinates' => Feature::coordinatesToYaml($coords, $type), 'id' => $id]);
                    }
                }
                // if feature (either new or modified) perform additional validation for renamed properties
                if ($feature_array) {
                    // check for renamed properties
                    $props = [];
                    foreach ($feature_array['properties'] ?? [] as $old_key => $value) {
                        $new_key = $properties[$old_key] ?? $old_key;
                        $props[$new_key] = $value;
                    }
                    $features[$feature_array['id']] = array_merge($feature_array, ['properties' => $props]);
                }
            }
        }
        $options = array_merge($update, [
            'id' => $this->getId(), // cannot change id
            'feature_type' => $type,
            'upload_file_path' => $this->getUploadFilePath(), // cannot change
            'feature_count' => $feature_count,
            'properties' => array_values($properties), // only need the new values
            'name_property' => $name_property,
            'auto_popup_properties' => $auto_popup_properties,
            'features' => array_values($features),
            'ready_for_update' => false,
        ]);
        // TODO: should not be necessary: unset($options['rename_properties']);
        // validate dataset fully by using constructor (also validates all features fully using constructor)
        return self::fromArray($options)->toYaml();
    }
    /**
     * Creates an id, title, route, and file for a (presumably) new dataset. Sets ids for all dataset features.
     * @param string $file_name The name of the uploaded file with the original dataset content
     * @param array $dataset_ids All existing dataset ids (to prevent duplicates)
     * @return MarkdownFile A file with all dataset content set in the header
     */
    public function initialize(string $file_name, array $dataset_ids): MarkdownFile {
        // first, determine a unique id for the dataset
        $id = preg_replace('/\.[^.]+$/', '', $file_name); // remove file extension
        $id = Utils::cleanUpString($id);
        // make sure the id is unique - add number to end and increment until an id is found that is not in the array of existing ids
        $count = 1;
        $base_id = $id;
        while (in_array($id, $dataset_ids)) {
            $id = "$base_id-$count";
            $count++;
        }
        // next, determine a title and folder name (slug) for the dataset
        $title = $this->getTitle() ?: $id;
        $slug = Utils::cleanUpString($title);
        $datasets_folder = Grav::instance()['locator']->findResource('page://') . '/datasets';
        // make sure the route is unique - add number to end and increment until a route is found that does not already contain a dataset (also increment the title so that it is more likely to be unique)
        $route = "$datasets_folder/$slug";
        $base_title = $title;
        $count = 1;
        while (MarkdownFile::instance("$route/point_dataset.md")->exists() || MarkdownFile::instance("$route/shape_dataset.md")->exists()) {
            $title = "$base_title-$count";
            $route = "$datasets_folder/$slug-$count";
            $count++;
        }
        // set the correct page type for the file
        if ($this->getType() === 'Point') $route = "$route/point_dataset.md";
        else $route = "$route/shape_dataset.md";
        // create ids for all features
        $features = [];
        $feature_count = 0;
        foreach (array_values($this->getFeatures()) as $feature) {
            $feature_count = self::nextFeatureCount($id, [], $feature_count);
            $feature_id = "$id--$feature_count";
            $features[$feature_id] = array_merge($feature->toYaml(), ['id' => $feature_id]);
        }
        // set name property, if there is not currently one
        $name_property = $this->getNameProperty() ?: self::determineNameProperty($this->getProperties());
        // set options to go in file
        $options = array_merge($this->toYaml(), [
            'id' => $id,
            'title' => $title,
            'features' => array_values($features),
            'feature_count' => $feature_count,
            'name_property' => $name_property,
        ]);
        // for shape datasets, set some path defaults
        if ($this->getType() !== 'Point') {
            $options['path'] = self::DEFAULT_PATH;
            $options['active_path'] = self::DEFAULT_ACTIVE_PATH;
            $options['border'] = self::DEFAULT_BORDER;
        }
        // set file contents and return file
        $file = MarkdownFile::instance($route);
        $file->header($options);
        return $file;
    }
    public function toYaml(): array {
        return array_merge($this->getExtras(), [
            'feature_type' => $this->getType(),
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'upload_file_path' => $this->getUploadFilePath(),
            'attribution' => $this->getAttribution(),
            'properties' => $this->getProperties(),
            'name_property' => $this->getNameProperty(),
            'auto_popup_properties' => $this->getAutoPopupProperties(),
            'legend' => $this->getLegend(),
            'ready_for_update' => $this->isReadyForUpdate(),
            'feature_count' => $this->getFeatureCount(),
            'features' => array_values(array_map(function($feature) { return $feature->toYaml(); }, $this->getFeatures())), // features need to be unindexed yaml
            'icon' => $this->getIcon(),
            'path' => $this->getPath(),
            'active_path' => $this->getActivePath(),
            'border' => $this->getBorder(),
            'active_border' => $this->getActiveBorder(),
        ]);
    }
    /**
     * Merges defaults with icon settings and modifies so they are the appropriate format for Leaflet IconOptions
     */
    public function getIconOptions(): array {
        $icon = $this->getIcon();
        if ($icon['file']) $icon = array_merge(self::CUSTOM_MARKER_FALLBACKS, $icon);
        else $icon = array_merge(self::DEFAULT_MARKER_FALLBACKS, $icon);
        $route = Utils::IMAGE_ROUTE . 'icons';
        $options = [
            'iconUrl' => $icon['iconUrl'] ?? "$route/" . $icon['file'],
            'iconRetinaUrl' => $icon['iconRetinaUrl'],
            'shadowUrl' => $icon['shadowUrl'],
            'iconSize' => [$icon['width'], $icon['height']],
            'shadowSize' => [$icon['shadow_width'] ?? $icon['width'], $icon['shadow_height'] ?? $icon['height']],
            'tooltipAnchor' => [$icon['tooltip_anchor_x'], $icon['tooltip_anchor_y']],
            'className' => $icon['className'] . ' ' . $icon['class'],
        ];
        if ($icon['retina']) $options['iconRetinaUrl'] = "$route/" . $icon['retina'];
        if ($icon['shadow']) $options['shadowUrl'] = "$route/" . $icon['shadow'];
        if (is_numeric($x = $icon['anchor_x']) && is_numeric($y = $icon['anchor_y'])) $options['iconAnchor'] = [$x, $y];
        if (is_numeric($x = $icon['shadow_anchor_x']) && is_numeric($y = $icon['shadow_anchor_y'])) $options['shadowAnchor'] = [$x, $y];
        if ($icon['rounding']) $options['className'] .= ' round';
        // allow for passing non-specified values
        $extras = array_diff_key($icon, array_flip(['file', 'retina', 'shadow', 'width', 'height', 'shadow_width', 'shadow_height', 'tooltip_anchor_x', 'tooltip_anchor_y', 'anchor_x', 'anchor_y', 'shadow_anchor_x', 'shadow_anchor_y', 'class', 'rounding']));
        return array_merge($extras, $options);
    }
    /**
     * Combines all the necessary shape options that will actually be applied to features on the map
     */
    public function getShapeOptions(): array {
        if ($this->getType() === 'Point') return [];
        $border = $this->getBorderOptions();
        $active_border = $this->getActiveBorderOptions();
        $options = [];
        if ($border) {
            $options['path'] = array_merge($border, $this->getFillOptions());
            $options['stroke'] = $this->getStrokeOptions();
        } else {
            $options['path'] = array_merge($this->getStrokeOptions(), $this->getFillOptions());
        }
        if ($active_border) {
            $options['active_path'] = array_merge($active_border, $this->getActiveFillOptions());
            $options['active_stroke'] = $this->getActiveStrokeOptions();
        } else {
            $options['active_path'] = array_merge($this->getActiveStrokeOptions(), $this->getActiveFillOptions());
        }
        return $options;
    }
    /**
     * Merges defaults with path settings (stroke, not fill)
     */
    public function getStrokeOptions(): array {
        // path plus defaults, fill false
        return array_merge(self::DEFAULT_PATH, $this->getPath(), ['fill' => false]);
    }
    /**
     * Merges defaults with active path settings (stroke, not fill)
     */
    public function getActiveStrokeOptions(): array {
        // path plus defaults, fill false
        return array_merge($this->getStrokeOptions(), $this->getActivePath(), ['fill' => false]);
    }
    /**
     * For polygons, returns path fill options (merged with defaults)
     */
    public function getFillOptions(): array {
        // empty array if line
        if (str_contains($this->getType(), 'LineString')) return [];
        else {
            $path = $this->getPath();
            $default = self::DEFAULT_PATH;
            return [
                'fill' => $path['fill'] ?? $default['fill'],
                'fillOpacity' => $path['fillOpacity'] ?? $default['fillOpacity'],
                'fillColor' => $path['fillColor'] ?? $path['color'] ?? $default['color'],
            ];
        }
    }
    /**
     * For polygons, returns active path fill options (merged with defaults)
     */
    public function getActiveFillOptions(): array {
        // empty array if line
        if (str_contains($this->getType(), 'LineString')) return [];
        else {
            $active = $this->getActivePath();
            $fill = $this->getFillOptions();
            return [
                'fill' => $active['fill'] ?? $fill['fill'],
                'fillOpacity' => $active['fillOpacity'] ?? $fill['fillOpacity'],
                'fillColor' => $active['fillColor'] ?? $this->getPath()['fillColor'] ?? $active['color'] ?? $fill['fillColor'],
            ];
        }
    }
    public function getBorderOptions(): array {
        $border = $this->getBorder();
        // must have border
        if ($border['stroke'] && $border['color']) {
            $stroke = $this->getStrokeOptions();
            $options = array_merge($stroke, ['weight' => self::DEFAULT_BORDER['weight']], $border, ['fill' => false]);
            // modify weight if needed
            if ($stroke['stroke']) $options['weight'] = ($options['weight'] * 2) + $stroke['weight'];
            return $options;
        }
        else return [];
    }
    public function getActiveBorderOptions(): array {
        $active = $this->getActiveBorder();
        $regular = $this->getBorderOptions();
        $border = array_merge($regular, $active, ['fill' => false]);
        if ($border['stroke'] && $border['color']) {
            $border['opacity'] = $active['opacity'] ?? $this->getActivePath()['opacity'] ?? $regular['opacity'];
            $border['weight'] = $active['weight'] ?? $this->getBorder()['weight'] ?? self::DEFAULT_BORDER['weight'];
            $stroke = $this->getActiveStrokeOptions();
            if ($stroke['stroke']) $border['weight'] = ($border['weight'] * 2) + $stroke['weight'];
            return $border;
        }
        else return [];
    }
    public function getName(): string {
        return $this->getTitle() ?: $this->getId();
    }

    // ordinary getters - no logic, just to prevent directy interaction with object properties
    public function getFile(): ?MarkdownFile { return $this->file; }
    public function getType(): string { return $this->type; }
    public function getId(): string { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function getUploadFilePath(): ?string { return $this->upload_file_path; }
    public function getAttribution(): ?string { return $this->attribution; }
    public function getNameProperty(): ?string { return $this->name_property; }
    public function getFeatureCount(): int { return $this->feature_count; }
    public function isReadyForUpdate(): bool { return $this->ready_for_update; }
    public function getLegend(): array { return $this->legend; }
    public function getFeatures(): array { return $this->features; }
    public function getProperties(): array { return $this->properties; }
    public function getAutoPopupProperties(): array { return $this->auto_popup_properties; }
    public function getIcon(): array { return $this->icon; }
    public function getPath(): array { return $this->path; }
    public function getActivePath(): array { return $this->active_path; }
    public function getBorder(): array { return $this->border; }
    public function getActiveBorder(): array { return $this->active_border; }
    public function getExtras(): array { return $this->extras; }

    // utility functions

    public static function createExport($yaml): array {
        // create array of geojson features
        $features = [];
        foreach ($yaml['features'] ?? [] as $feature) {
            $features[] = Feature::fromDataset($feature, $yaml['feature_type'], $yaml['id'] ?? '', null)->toGeoJson();
        }
        // set the other settings and return the json array
        return [
            'type' => 'FeatureCollection',
            'name' => $yaml['title'] ?: $yaml['id'] ?? '',
            'features' => $features,
        ];
    }
    public static function validateNameProperty($name_prop, $properties): ?string {
        if (!is_string($name_prop) || !is_array($properties)) return null; // check input types
        if (in_array($name_prop, $properties) || $name_prop === 'none') return $name_prop;
        else return null;
    }
    public static function validateAutoPopupProperties($auto_popup_props, $properties): array {
        if (!is_array($auto_popup_props) || !is_array($properties)) return []; // check input types
        return array_values(array_intersect($auto_popup_props, $properties));
    }
    public static function nextFeatureCount($id, $feature_ids, $feature_count): int {
        $dataset_id = is_string($id) ? $id : '';
        $count = is_numeric($feature_count) ? $feature_count + 1 : 1;
        while(in_array("$dataset_id--$count", $feature_ids)) {
            // as long as a feature exists with the proposed id, keep incrementing count
            $count++;
        }
        return $count;
    }
    // return [tmp id => original id]
    public static function matchFeatures(string $dataset_prop, ?string $file_prop, array $original_features, array $update_features): array {
        $matches = [];
        if ($dataset_prop === 'coords') {
            // match features based on coordinates, must be exact match
            // first create index of original feature coordinates to reference
            $index = array_flip(array_map(function($feature) {
                return json_encode($feature->getCoordinates());
            }, $original_features)); // returns coords => id, due to array_flip
            // then look for matches
            foreach ($update_features as $tmp_id => $update_feature) {
                $coords = json_encode($update_feature->getCoordinates());
                if ($id = $index[$coords]) $matches[$tmp_id] = $id;
            }
        } else {
            // match features based on properties
            // first create index of original feature property values to reference
            $index = [];
            foreach ($original_features as $id => $feature) {
                // note that this won't work well if more than one feature has the same value for the property
                if ($value = $feature->getProperties()[$dataset_prop]) $index[$value] = $id;
            }
            // then look for matches
            foreach ($update_features as $tmp_id => $update_feature) {
                // use dataset prop as default file prop
                $value = $update_feature->getProperties()[$file_prop ?: $dataset_prop];
                if ($id = $index[$value]) $matches[$tmp_id] = $id;
            }
        }
        return $matches;
    }

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
    /**
     * Note that the return array may include an old property (as included in the rename_properties fieldset) that has been removed from the properties list. Any such values will be added to the end of the properties list.
     * @param $rename rename_properties from dataset yaml
     * @param $properties properties from dataset yaml
     * @return array [key => value] where keys are all property values before renaming and values are all property values after renaming (may be exactly the same)
     */
    public static function validateUpdateProperties($rename, $properties): array {
        $renamed = [];
        $props = is_array($properties) ? $properties : [];
        // loop through to compile a list of properties to actually rename
        foreach ((is_array($rename) ? $rename : []) as $old => $new) {
            // to change: must have new value, value cannot match existing value, value cannot match a newly renamed value (i.e. cannot rename two properties to the same name)
            if ($new && !in_array($new, $props) && !in_array($new, array_values($renamed))) $renamed[$old] = $new;
        }
        $props = array_combine($props, $props); // "old" names pointing to old names
        // replace any old names (values) with new names, preserves order of properties
        return array_merge($props, $renamed);
    }

}
?>