<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;
use RocketTheme\Toolbox\File\File;

/**
 * @property MarkdownFile|null $file
 * @property string $type
 * @property string $id
 * @property string|null $title
 * @property string|null $upload_file_path
 * @property string|null $attribution
 * @property string|null $name_property
 * @property int $feature_count
 * @property bool $ready_for_update
 * @property array $legend
 * @property array $features
 * @property array $properties
 * @property array $auto_popup_properties
 * @property array $icon
 * @property array $path
 * @property array $active_path
 * @property array $border
 * @property array $active_border
 * @property array $extras
 */
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

    private $file, $type, $id, $title, $upload_file_path, $attribution, $name_property, $feature_count, $ready_for_update, $legend, $features, $properties, $auto_popup_properties, $icon, $path, $active_path, $border, $active_border, $extras;

    /**
     * Sets and validates all provided options
     * 
     * @param array $options Dataset yaml, possibly with file
     */
    private function __construct($options) {
        // validate file - file does not have to exist, but must be an object with a valid function called "exists" - There is probably a better way to check that this is a MarkdownFile, but this works for now
        try {
            $file = Utils::get($options, 'file');
            $file->exists();
            $this->file = $file;
        } catch (\Throwable $t) {
            $this->file = null;
        }
        // validate type just in case (should be provided as valid, though)
        $this->type = Feature::validateFeatureType(Utils::get($options, 'feature_type') ?? Utils::get($options, 'type'));
        // validate id
        $this->id = Utils::getStr($options, 'id', '');
        // validate strings
        foreach (['title', 'upload_file_path', 'attribution'] as $key) {
            $this->$key = Utils::getStr($options, $key, null);
        }
        // validate feature count
        $this->feature_count = Utils::getType($options, 'feature_count', 'is_int', 0);
        // validate ready for update
        $this->ready_for_update = (Utils::get($options, 'ready_for_update') === true);
        // validate arrays
        foreach (['legend', 'properties', 'icon', 'path', 'active_path', 'border', 'active_border'] as $key) {
            $this->$key = Utils::getArr($options, $key);
        }
        // validate name property and auto popup properties
        $this->name_property = self::validateNameProperty(Utils::getStr($options, 'name_property'), $this->properties);
        $this->auto_popup_properties = self::validateAutoPopupProperties(Utils::getArr($options, 'auto_popup_properties'), $this->properties);
        // validate features
        $features = [];
        foreach (Utils::getArr($options, 'features') as $feature_yaml) {
            $feature = Feature::fromDataset($feature_yaml, $this->type, $this->id, $this->name_property);
            // add feature to array, index by id if possible
            if ($feature && ($id = $feature->getId())) $features[$id] = $feature;
            else if ($feature) $features[] = $feature;
        }
        $this->features = $features;
        // extras
        $keys = ['feature_type', 'id', 'title', 'upload_file_path', 'attribution', 'legend','properties', 'icon', 'path', 'active_path', 'border', 'active_border', 'name_property', 'auto_popup_properties', 'features', 'feature_count', 'ready_for_update', 'file'];
        $this->extras = array_diff_key($options, array_flip($keys));
    }

    /**
     * Builds a dataset from parsed json content
     * 
     * @param array $json Parsed json content
     * @return Dataset|null Dataset if at least one valid feature, otherwise null
     */
    public static function fromJson($json) {
        // loop through json features and try creating new Feature objects
        $type = null; // to be set by first valid feature
        $features = $properties = []; // to be filled
        foreach (Utils::getArr($json, 'features') as $feature_json) {
            if ($feature = Feature::fromJson($feature_json, $type)) {
                $type ??= $feature->getType(); // set type if this is the first valid feature
                $features[] = $feature->toYaml();
                $properties = array_merge($properties, $feature->getProperties());
            }
        }
        if (!empty($features)) {
            // compat with PHP 8
            $json = array_merge(array_flip(['name', 'feature_count', 'upload_file_path', 'name_property']), $json);
            $props = array_keys($properties);
            // provide values from json when creating dataset
            return new Dataset([
                'feature_type' => $type,
                'features' => $features,
                'properties' => $props,
                'title' => Utils::getStr($json, 'name', null),
                'feature_count' => Utils::getType($json, 'feature_count', 'is_int', 0),
                'upload_file_path' => Utils::getStr($json, 'upload_file_path', null),
                'name_property' => Dataset::validateNameProperty(Utils::getStr($json, 'name_property'), $props),
            ]);
        }
        else return null;
    }
    /**
     * Creates a new dataset from a valid markdown file
     * 
     * @param MarkdownFile $file
     * @return Dataset
     */
    public static function fromFile($file) {
        return new Dataset(array_merge($file->header(), ['file' => $file]));
    }
    /**
     * Creates a new dataset from an array (equivalent to header data from a markdown file)
     * 
     * @param array $options
     * @return Dataset
     */
    public static function fromArray($options) {
        return new Dataset($options);
    }
    /**
     * Creates a dataset from an array, but only using the keys specified
     * 
     * @param array $options All dataset options
     * @param array $keys The keys to actually include
     * @return Dataset
     */
    public static function fromLimitedArray($options, $keys) {
        return new Dataset(array_intersect_key($options, array_flip($keys)));
    }
    /**
     * Creates a new dataset by merging options from an existing dataset with overrides set by a tour
     * 
     * @param Dataset $dataset A valid existing dataset
     * @param array $tour_options Dataset overrides set for the dataset in the tour
     * @return Dataset
     */
    public static function fromTour($dataset, $tour_options) {
        $options = $dataset->toYaml();
        // overwrite attribution and auto popup properties
        if ($attr = Utils::getStr($tour_options, 'attribution')) $options['attribution'] = $attr;
        if (($props = Utils::getArr($tour_options, 'auto_popup_properties', null))) $options['auto_popup_properties'] = $props;
        // merge icon and shape options
        foreach (['icon', 'path', 'active_path', 'border', 'active_border'] as $key) {
            $options[$key] = array_merge(Utils::getArr($options, $key), Utils::getArr($tour_options, $key));
        }
        // legend
        $legend = Utils::getArr($tour_options, 'legend');
        if (!Utils::getStr($legend, 'text')) {
            $legend['text'] = $dataset->getLegend('text');
            // only provide legend summary from dataset if tour options has neither legend text nor legend summary
            if (!Utils::get($legend, 'summary')) $legend['summary'] = $dataset->getLegend('summary');
        }
        if (!Utils::get($legend, 'symbol_alt')) {
            // for points: only use symbol alt from dataset if tour options do not include icon file
            if ($dataset->getType() === 'Point') {
                if (!Utils::get(Utils::getArr($tour_options, 'icon'), 'file')) $legend['symbol_alt'] = $dataset->getLegend('symbol_alt');
            }
            // for shapes: only use symbol alt from datset if tour ooptions do not include path color (could also include path fill color, border color, etc. as desired)
            else {
                if (!Utils::get(Utils::getArr($tour_options, 'path'), 'color')) $legend['symbol_alt'] = $dataset->getLegend('symbol_alt');
            }
        }
        // default for legend summary
        if (!Utils::get($legend, 'summary')) $legend['summary'] = Utils::get($legend, 'text') ?: Utils::get($legend, 'symbol_alt');
        $options['legend'] = $legend;

        $options['features'] = []; // may as well remove features to reduce unneeded validation
        return new Dataset($options);
    }
    /**
     * Creates a new dataset with most settings from the old dataset but features from the new dataset. Matched features retain some values.
     * 
     * @param array $matches List of all matches: [update_id => old_id] where update_id identifies the new feature data in the update dataset and old_id identifies the existing feature in the old dataset
     * @param Dataset $old_dataset The existing dataset to be updated
     * @param Dataset $update_dataset A temporary dataset containing update options
     * @return Dataset
     */
    public static function fromUpdateReplace($matches, $old_dataset, $update_dataset) {
        $features = [];
        $feature_count = $old_dataset->getFeatureCount();
        // keep features in the order they have in replacement file, but treat new features and existing (matched) features differently
        foreach ($update_dataset->getFeatures() as $update_id => $update_feature) {
            // match feature will have id in matches
            if (($old_id = Utils::get($matches, $update_id)) && ($old_feature = Utils::get($old_dataset->getFeatures(), $old_id))) {
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
     * 
     * @param array $matches List of all matches: [update_id => old_id] where update_id identifies the new feature data in the update dataset and old_id identifies the existing feature in the old dataset. In this case, only the old ids matter, as features are not being modified.
     * @param Dataset $old_dataset The existing dataset to be updated
     * @return Dataset
     */
    public static function fromUpdateRemove($matches, $old_dataset) {
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
     * 
     * @param array $matches List of all matches: [update_id => old_id] where update_id identifies the new feature data in the update dataset and old_id identifies the existing feature in the old dataset
     * @param Dataset $old_dataset The existing dataset to be updated
     * @param Dataset $update_dataset A temporary dataset containing update options
     * @param bool|null $add Indicates if "add" flag is set (add new features to dataset)
     * @param bool|null $modify Indicates if "modify" flag is set (modify existing features in dataset)
     * @param bool|null $remove Indicates if "remove" flag is set (remove features from old dataset that have no match in the update dataset)
     * @return Dataset
     */
    public static function fromUpdateStandard($matches, $old_dataset, $update_dataset, $add, $modify, $remove) {
        $features = [];
        $feature_count = $old_dataset->getFeatureCount();
        $old_match_ids = array_flip($matches); // change [update id => old id] to [old id => update id]
        foreach ($old_dataset->getFeatures() as $old_id => $old_feature) {
            // if feature matches, either modify the feature (if modify is true) or just make sure to keep the feature
            if ($update_id = Utils::get($old_match_ids, $old_id)) {
                if ($modify) {
                    $update_feature = Utils::get($update_dataset->getFeatures(), $update_id);
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
                if (!Utils::get($matches, $update_id)) {
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
     * 
     * @param array $update Dataset yaml from update
     * @param array $properties [old name => new name] (from renaming properties, potentially)
     * @return array updated Dataset yaml
     */
    public function validateUpdate($update, $properties, $path = '') {
        if (!is_array($properties)) $properties = [];
        // validate feature type - to change type: both old and new types must be shape (i.e. not 'Point'), old and new types should be different, either current features or new features should be empty
        $new_type = Feature::validateFeatureType(Utils::get($update, 'feature_type'));
        if (($this->getType() !== 'Point') && ($new_type !== $this->getType()) && ($new_type !== 'Point') && (empty($this->getFeatures()) || empty(Utils::getArr($update, 'features')))) $type = $new_type;
        else $type = $this->getType();
        // validate properties
        $update_name_prop = Utils::getStr($update, 'name_property', 'none');
        $name_property = Utils::getStr($properties, $update_name_prop, 'none');
        // replace auto popup properties with new values if needed
        $auto_popup_properties = [];
        foreach (Utils::getArr($update, 'auto_popup_properties') as $prop) {
            // will return new value and add the property only if it exists
            if ($prop = Utils::getStr($properties, $prop)) $auto_popup_properties[] = $prop;
        }
        // validate features, reconcile changes
        $features = [];
        $feature_count = $this->getFeatureCount();
        // set path - will be useful
        $path = str_replace(Grav::instance()['locator']->findResource('page://') . '/', '', $path);
        foreach (Utils::getArr($update, 'features') as $feature_yaml) {
            $feature_array = null;
            // modified feature has id for feature in dataset that has not yet been added to features list (i.e. not a duplicate)
            if (($id = Utils::getStr($feature_yaml, 'id')) && ($old_feature = Utils::get($this->getFeatures(), $id)) && (!isset($features[$id]))) {
                // validate feature update (coordinates and popup content)
                $feature_array = $old_feature->validateUpdate($feature_yaml, $path);
            } else {
                // new feature: make sure feature has valid coordinates (otherwise ignore it) and give it a proper unique id
                if ($coords = Feature::validateYamlCoordinates(Utils::get($feature_yaml, 'coordinates'), $type)) {
                    $feature_count = self::nextFeatureCount($this->getId(), array_keys($this->getFeatures()), $feature_count);
                    $id = $this->getId() . "--$feature_count";
                    $feature_array = array_merge($feature_yaml, ['coordinates' => Feature::coordinatesToYaml($coords, $type), 'id' => $id]);
                    $popup = Feature::validatePopupContent(Utils::get($feature_yaml, 'popup'));
                    $popup = Feature::modifyPopupImagePaths($popup, $path);
                    if ($popup) $feature_array['popup'] = ['popup_content' => $popup];
                }
            }
            // if feature (either new or modified) perform additional validation for renamed properties
            if ($feature_array) {
                // check for renamed properties
                $props = [];
                foreach (Utils::getArr($feature_array, 'properties') as $old_key => $value) {
                    $new_key = Utils::get($properties, $old_key) ?? $old_key;
                    $props[$new_key] = $value;
                }
                $features[$feature_array['id']] = array_merge($feature_array, ['properties' => $props]);
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
        // validate dataset fully by using constructor (also validates all features fully using constructor)
        return self::fromArray($options)->toYaml();
    }
    /**
     * Creates an id, title, route, and file for a (presumably) new dataset. Sets ids for all dataset features.
     * 
     * @param string $file_name The name of the uploaded file with the original dataset content
     * @param array $dataset_ids All existing dataset ids (to prevent duplicates)
     * @return MarkdownFile A file with all dataset content set in the header
     */
    public function initialize($file_name, $dataset_ids) {
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
    /**
     * Returns content for the dataset page header
     * 
     * @return array
     */
    public function toYaml() {
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
     * Merges defaults with icon settings and modifies them so they are the appropriate format for Leaflet IconOptions
     * - iconUrl: Can be set by user as icon 'file' (from associated folder) or as iconUrl, otherwise default; determines which set of defaults are used
     * - iconRetinaUrl: Can be set by user as icon 'retina' (from associated folder) or as iconRetinaUrl, otherwise default if it exists (no default for custom icons)
     * - shadowUrl: Can be set by user as icon 'shadow' (from associated folder) or as shadowUrl, otherwise default if it exists (no default for custom icons)
     * - iconSize: Can be set by user individually as 'height' and/or 'width' (must be positive integers) or as iconSize (only if no values for 'height' or 'width' are provided); valid values for 'height' and 'width' are combined with defaults
     * - shadowSize: Can be set by user individually as 'shadow_height' and/or 'shadow_width' (must be positive integers) or as shadowSize (only if no values for 'height' or 'width' are provided); valid values for height and width are combined with defaults; if defaults array does not include shadow size options, the icon size determined above is used as defaults instead
     * - tooltipAnchor: Can be set by user individually as 'tooltip_anchor_x' and/or 'tooltip_anchor_y' (must be integers) or as tooltipAnchor (only if no x or y values are provided); valid values for x and y are combined with defaults
     * - iconAnchor: Can be set by user individually as 'anchor_x' and/or 'anchor_y' (must be integers) or as iconAnchor (only if no x or y values are provided); valid values for x and y are combined with defaults (if they exist); iconAnchor is only included if there are valid values for both x and y
     * - shadowAnchor: Can be set by user individually as 'shadow_anchor_x' and 'shadow_anchor_y' (must be integers) or as shadowAnchor (only if no x or y values are provided); no defaults; shadowAnchor is only included if there are valid values for both x and y
     * - className: Created by combining default class name with user provided values for 'className' and 'class'; additional class is added if user has set 'rounding' as true
     * 
     * The yaml keys will not be included in the returned array (i.e. iconSize will be included, but not 'height' or 'width'). Any values set that are not in the leaflet or blueprint/yaml keys will be added to the returned array, allowing for potential additional customization.
     * 
     * @return array
     */
    public function getIconOptions() {
        $icon = $this->getIcon(); // icon options as provided by user
        $route = Utils::IMAGE_ROUTE . 'icons';
        $defaults = []; // fallback values for invalid/null values in icon, needs to be set
        $options = []; // icon options to return
        // determine correct defaults to use and set icon url
        if (($file = Utils::getStr($icon, 'file')) && File::instance("$route/$file")->exists()) {
            // valid icon file provided by user
            $options['iconUrl'] = "$route/$file";
            $defaults = self::CUSTOM_MARKER_FALLBACKS;
        } else if ($url = Utils::getStr($icon, 'iconUrl')) {
            // iconUrl set by user
            $options['iconUrl'] = $url;
            $defaults = self::CUSTOM_MARKER_FALLBACKS;
        } else {
            $defaults = self::DEFAULT_MARKER_FALLBACKS;
            $options['iconUrl'] = $defaults['iconUrl'];
        }
        // retina and shadow urls
        if (($file = Utils::getStr($icon, 'retina')) && File::instance("$route/$file")->exists()) $options['iconRetinaUrl'] = "$route/$file";
        else if ($url = Utils::getStr($icon, 'iconRetinaUrl') ?: Utils::getStr($defaults, 'iconRetinaUrl')) $options['iconRetinaUrl'] = $url;
        if (($file = Utils::getStr($icon, 'shadow')) && File::instance("$route/$file")->exists()) $options['shadowUrl'] = "$route/$file";
        else if ($url = Utils::getStr($icon, 'shadowUrl') ?: Utils::getStr($defaults, 'shadowUrl')) $options['shadowUrl'] = $url;
        // icon size can be set directly, only use if neither height nor width is set
        if (!isset($icon['height']) && !isset($icon['width']) && ($size = Utils::get($icon, 'iconSize')) && is_array($size) && (count($size) === 2) && is_int($size[0]) && is_int($size[1]) && $size[0] >= 0 && $size[1] >= 0) $options['iconSize'] = $size;
        else $options['iconSize'] = [$this->getPositiveInt($icon, 'width') ?? $defaults['width'], $this->getPositiveInt($icon, 'height') ?? $defaults['height']];
        // shadow size can be set directly, only use if neither height nor width is set
        if (!isset($icon['shadow_width']) && !isset($icon['shadow_height']) && ($size = Utils::get($icon, 'shadowSize')) && is_array($size) && (count($size) == 2) && is_int($size[0]) && is_int($size[1]) && $size[0] >= 0 && $size[1] >= 0) $options['shadowSize'] = $size;
        // default shadow size may not be set - use icon size instead in that case
        else $options['shadowSize'] = [
            $this->getPositiveInt($icon, 'shadow_width') ?? Utils::get($defaults, 'shadow_width') ?? $options['iconSize'][0],
            $this->getPositiveInt($icon, 'shadow_height') ?? Utils::get($defaults, 'shadow_height') ?? $options['iconSize'][1],
        ];
        // tooltip anchor
        if (!isset($icon['tooltip_anchor_x']) && !isset($icon['tooltip_anchor_y']) && ($anchor = Utils::get($icon, 'tooltipAnchor')) && is_array($anchor) && (count($anchor) === 2) && is_int($anchor[0]) && is_int($anchor[1])) $options['tooltipAnchor'] = $anchor;
        else $options['tooltipAnchor'] = [Utils::getType($icon, 'tooltip_anchor_x', 'is_int') ?? $defaults['tooltip_anchor_x'], Utils::getType($icon, 'tooltip_anchor_y', 'is_int') ?? $defaults['tooltip_anchor_y']];
        // icon anchor
        if (!isset($icon['anchor_x']) && !isset($icon['anchor_y']) && ($anchor = Utils::get($icon, 'iconAnchor')) && is_array($anchor) && (count($anchor) === 2) && is_int($anchor[0]) && is_int($anchor[1])) $options['iconAnchor'] = $anchor;
        else if (($x = Utils::getType($icon, 'anchor_x', 'is_int') ?? Utils::get($defaults, 'anchor_x')) && ($y = Utils::getType($icon, 'anchor_y', 'is_int') ?? Utils::get($defaults, 'anchor_y'))) $options['iconAnchor'] = [$x, $y];
        // shadow anchor
        if (!isset($icon['shadow_anchor_x']) && !isset($icon['shadow_anchor_y']) && ($anchor = Utils::get($icon, 'shadowAnchor')) && is_array($anchor) && (count($anchor) === 2) && is_int($anchor[0]) && is_int($anchor[1])) $options['shadowAnchor'] = $anchor;
        else if (($x = Utils::getType($icon, 'shadow_anchor_x', 'is_int') ?? Utils::get($defaults, 'shadow_anchor_x')) && ($y = Utils::getType($icon, 'shadow_anchor_y', 'is_int') ?? Utils::get($defaults, 'shadow_anchor_y'))) $options['shadowAnchor'] = [$x, $y];
        // class name (and rounding)
        $class = $defaults['className'];
        if ($str = Utils::getStr($icon, 'className')) $class = "$class $str";
        if ($str = Utils::getStr($icon, 'class')) $class = "$class $str";
        if (Utils::get($icon, 'rounding') === true) $class = "$class round";
        $options['className'] = $class;
        // allow for passing non-specified values
        $extras = array_diff_key($icon, array_flip(['file', 'iconUrl', 'retina', 'iconRetinaUrl', 'shadow', 'shadowUrl', 'width', 'height', 'iconSize', 'shadow_width', 'shadow_height', 'shadowSize', 'tooltip_anchor_x', 'tooltip_anchor_y', 'tooltipAnchor', 'anchor_x', 'anchor_y', 'iconAnchor', 'shadow_anchor_x', 'shadow_anchor_y', 'shadowAnchor', 'class', 'className', 'rounding']));
        return array_merge($extras, $options);
    }
    /**
     * Determines if the provided array has a key with a value that is a positive integer. Returns the value if so, otherwise null.
     * 
     * @param array $icon The array to check
     * @param string $key The key to look for
     * @return int|null
     */
    private function getPositiveInt($icon, $key) {
        $int = Utils::getType($icon, $key, 'is_int');
        if ($int && ($int >= 0)) return $int;
        else return null;
    }
    // TODO: Need to test situation where feature only has active border, not regular border: Should work fine up through this point, but the JS is not prepared to handle that
    /**
     * Combines all the necessary shape options that will actually be applied to features on the map.
     * - If feature has border, this includes a 'path' array with border and fill options
     * - If feature has border and stroke, a 'stroke' array is included with stroke options only (the switcharound is necessary for appropriate layering on the map)
     * - Otherwise if feature has no border, this includes a 'path' array with stroke and fill options
     * - If feature has active border, this includes an 'active_path' array with active border and active fill options
     * - If feature has active border and active stroke, an 'active_stroke' array is included with active stroke options only (the switcharound is necessary for appropriate layering on the map)
     * - Otherwise if feature has no active border, this includes an 'active_path' array with active stroke and active fill options
     * 
     * @return array
     */
    public function getShapeOptions() {
        if ($this->getType() === 'Point') return [];
        $border = $this->getBorderOptions();
        $active_border = $this->getActiveBorderOptions();
        $options = [];
        if ($border) {
            $options['path'] = array_merge($border, $this->getFillOptions());
            $stroke = $this->getStrokeOptions();
            if ($stroke['stroke']) $options['stroke'] = $stroke;
        } else {
            $options['path'] = array_merge($this->getStrokeOptions(), $this->getFillOptions());
        }
        if ($active_border) {
            $options['active_path'] = array_merge($active_border, $this->getActiveFillOptions());
            $stroke = $this->getActiveStrokeOptions();
            if ($stroke['stroke']) $options['active_stroke'] = $stroke;
        } else {
            $options['active_path'] = array_merge($this->getActiveStrokeOptions(), $this->getActiveFillOptions());
        }
        return $options;
    }
    /**
     * Validates path stroke settings (stroke, opacity, weight, color) and merges with defaults. Sets 'fill' to false. Does not include any other fill settings, but does include any additional settings added by the user.
     * 
     * @return array
     */
    public function getStrokeOptions() {
        return self::validateStrokeOptions(self::DEFAULT_PATH, $this->getPath());
    }
    /**
     * Validates active path stroke settings (stroke, opacity, weight, color) and merges with defaults (which are the regular stroke options). Sets 'fill' to false. Does not include an other fill settings, but does include any additional settings added by the user.
     * 
     * @return array
     */
    public function getActiveStrokeOptions() {
        return self::validateStrokeOptions($this->getStrokeOptions(), $this->getActivePath());
    }
    /**
     * Validates path stroke settings (stroke, opacity, weight, color) and merges with defaults. Sets 'fill' to false. Does not include any other fill settings, but does include any additional settings added by the user.
     * 
     * @param array $default Must have valid values for stroke, opacity, weight, and color
     * @param array $path
     * @return array
     */
    public static function validateStrokeOptions($default, $path) {
        $options = [];
        // stroke must be bool
        $options['stroke'] = Utils::getType($path, 'stroke', 'is_bool') ?? $default['stroke'];
        // opacity must be number between 0 and 1 inclusive
        $opacity = Utils::getType($path, 'opacity', 'is_numeric', 100);
        if ($opacity < 0 || $opacity > 1) $opacity = $default['opacity'];
        $options['opacity'] = $opacity;
        // weight must be positive integer
        $weight = Utils::getType($path, 'weight', 'is_int', -7);
        if ($weight < 1) $weight = $default['weight'];
        $options['weight'] = $weight;
        // color must be string
        $options['color'] = Utils::getStr($path, 'color') ?: $default['color'];
        // fill should be false
        $options['fill'] = false;
        // add extras (including extras from default)
        $keys = ['stroke', 'color', 'opacity', 'weight', 'fill', 'fillColor', 'fillOpacity'];
        $options = array_merge($options, array_diff_key(array_merge($default, $path), array_flip($keys)));
        return $options;
    }
    /**
     * Validates path fill settings (fill, fillOpacity, and fillColor) and merges with defaults. Only for polygons - returns empty array for points and lines. Does not include any stroke settings, but does include any additional settings added by the user.
     * - defaults for fill and fillOpacity come from the dataset defaults array
     * - default for fillColor comes from stroke options
     * 
     * @return array
     */
    public function getFillOptions() {
        // empty array if line
        if (!str_contains($this->getType(), 'Polygon')) return [];
        else {
            $path = $this->getPath();
            $default = self::DEFAULT_PATH;
            $options = [];
            // fill - use default if not set
            $options['fill'] = Utils::getType($path, 'fill', 'is_bool') ?? $default['fill'];
            // fill opacity - use default if not set
            $opacity = Utils::getType($path, 'fillOpacity', 'is_numeric', 100);
            if ($opacity < 0 || $opacity > 1) $opacity = $default['fillOpacity'];
            $options['fillOpacity'] = $opacity;
            // fill color - use stroke color (or default for stroke color) if not set
            $options['fillColor'] = Utils::getStr($path, 'fillColor',) ?: $this->getStrokeOptions()['color'];
            // add extras
            $keys = ['stroke', 'color', 'opacity', 'weight', 'fill', 'fillColor', 'fillOpacity'];
            $options = array_merge($options, array_diff_key($path, array_flip($keys)));
            return $options;
        }
    }
    /**
     * Validates active path fill settings (fill, fillOpacity, and fillColor) and merges with defaults. Only for polygons - returns empty array for points and lines. Does not include any stroke settings, but does include any additional settings added by the user.
     * - defaults for fill and fillOpacity come from regular fill options
     * - default for fillColor: Use regular fillColor if set by user, otherwise use color determined for active stroke options
     * 
     * @return array
     */
    public function getActiveFillOptions() {
        // empty array if line
        if (!str_contains($this->getType(), 'Polygon')) return [];
        else {
            $path = $this->getActivePath();
            $default = $this->getFillOptions();
            $options = [];
            // fill - must be bool, use default as default
            $options['fill'] = Utils::getType($path, 'fill', 'is_bool') ?? $default['fill'];
            // fill opacity - must be number between 0 and 1 inclusive, use default as default
            $opacity = Utils::getType($path, 'fillOpacity', 'is_numeric', 100);
            if ($opacity < 0 || $opacity > 1) $opacity = $default['fillOpacity'];
            $options['fillOpacity'] = $opacity;
            // fill color - must be string, use regular fill as default, then active/regular/default color
            $options['fillColor'] = Utils::getStr($path, 'fillColor',) ?: Utils::getStr($this->getPath(), 'fillColor') ?: $this->getActiveStrokeOptions()['color'];
            // add extras (including extras from regular fill options)
            $keys = ['stroke', 'color', 'opacity', 'weight', 'fill', 'fillColor', 'fillOpacity'];
            $options = array_merge($options, array_diff_key(array_merge($this->getPath(), $path), array_flip($keys)));
            return $options;
        }
    }
    /**
     * Validates border settings (stroke, color, opacity, weight) and merges with defaults. Border 'stroke' must be true and color must be a non-empty string, otherwise returns empty array. Sets 'fill' to false. Does not include other fill options, but does include any additional settings added by the user. Modifies weight if path stroke is true to equal weight + path weight + weight.
     * - defaults for stroke and color: none
     * - default for opacity: opacity determined for stroke options
     * - default for weight: dataset const
     * 
     * @return array
     */
    public function getBorderOptions() {
        $border = $this->getBorder();
        $path = $this->getStrokeOptions();
        $stroke = Utils::getType($border, 'stroke', 'is_bool');
        $color = Utils::getStr($border, 'color', null);
        // stroke and color must be set and valid
        if ($stroke && $color) {
            // add weight and opacity
            $opacity = $this->checkOpacity(Utils::get($border, 'opacity')) ?? $path['opacity'];
            $weight = Utils::getType($border, 'weight', 'is_int', -7);
            if ($weight < 1) $weight = self::DEFAULT_BORDER['weight'];
            // modify weight if path stroke is true
            if ($path['stroke']) {
                $weight = ($weight * 2) + $path['weight'];
            }
            // fill should be false
            $options = ['stroke' => true, 'color' => $color, 'opacity' => $opacity, 'weight' => $weight, 'fill' => false];
            // add extras
            $keys = ['stroke', 'color', 'opacity', 'weight', 'fill', 'fillColor', 'fillOpacity'];
            $options = array_merge($options, array_diff_key($border, array_flip($keys)));
            return $options;
        }
        else return [];
    }
    /**
     * Checks provided value: Must be a number greater than or equal to zero and less than or equal to one. Returns value if valid, otherwise null.
     * 
     * @param float $opacity
     * @return ?float
     */
    private static function checkOpacity($opacity) {
        if (!is_numeric($opacity) || $opacity < 0 || $opacity > 1) return null;
        else return $opacity;
    }
    /**
     * Validates active border settings (stroke, color, opacity, weight) and merges with defaults. Validated/merged 'stroke' must be true and color must be non-empty string, otherwise returns empty array. Sets 'fill' to false. Does not include other fill options, but does include any additional settings added by the user. Modifies weight if active path stroke is true to equal weight + active path weight + weight.
     * - defaults for stroke and color: $this->border
     * - default for opacity: regular border opacity if set/valid, otherwise opacity determined for active stroke options
     * - default for weight: regular border weight if set/valid, otherwise dataset const
     * 
     * @return array
     */
    public function getActiveBorderOptions() {
        $active = $this->getActiveBorder();
        $border = $this->getBorder();
        $stroke = Utils::getType($active, 'stroke', 'is_bool') ?? Utils::getType($border, 'stroke', 'is_bool');
        $color = Utils::getStr($active, 'color', null) ?: Utils::getStr($border, 'color', null);
        if ($stroke && $color) {
            $active_path = $this->getActiveStrokeOptions();
            // opacity - use active border if valid, otherwise regular border, otherwise active path/regular/default
            $opacity = $this->checkOpacity(Utils::get($active, 'opacity')) ?? $this->checkOpacity(Utils::get($border, 'opacity')) ?? $active_path['opacity'];
            // weight - active border, regular border, default
            $weight = Utils::getType($active, 'weight', 'is_int', -7);
            if ($weight < 1) $weight = Utils::getType($border, 'weight', 'is_int', -7);
            if ($weight < 1) $weight = self::DEFAULT_BORDER['weight'];
            // modify weight if path stroke is true
            if ($active_path['stroke']) {
                $weight = ($weight * 2) + $active_path['weight'];
            }
            // fill should be false
            $options = ['stroke' => true, 'color' => $color, 'opacity' => $opacity, 'weight' => $weight, 'fill' => false];
            // add extras
            $keys = ['stroke', 'color', 'opacity', 'weight', 'fill', 'fillColor', 'fillOpacity'];
            $options = array_merge($options, array_diff_key(array_merge($border, $active), array_flip($keys)));
            return $options;
            
        }
        else return [];
    }
    /**
     * Returns title or id
     * 
     * @return string
     */
    public function getName() {
        return $this->getTitle() ?: $this->getId();
    }
    /**
     * If no key is provided, returns normal legend. If key is provided and value exists and is string, returns value. Otherwise returns null.
     * 
     * @param string|null $key
     * @return array|mixed
     */
    public function getLegend($key = null) {
        if ($key) return Utils::getStr($this->legend, $key);
        else return $this->legend;
    }

    // ordinary getters - no logic, just to prevent directy interaction with object properties
    /**
     * @return MarkdownFile|null
     */
    public function getFile() { return $this->file; }
    /**
     * @return string
     */
    public function getType() { return $this->type; }
    /**
     * @return string
     */
    public function getId() { return $this->id; }
    /**
     * @return string|null
     */
    public function getTitle() { return $this->title; }
    /**
     * @return string|null
     */
    public function getUploadFilePath() { return $this->upload_file_path; }
    /**
     * @return string|null
     */
    public function getAttribution() { return $this->attribution; }
    /**
     * @return string|null
     */
    public function getNameProperty() { return $this->name_property; }
    /**
     * @return int
     */
    public function getFeatureCount() { return $this->feature_count; }
    /**
     * @return bool
     */
    public function isReadyForUpdate() { return $this->ready_for_update; }
    /**
     * @return array
     */
    public function getFeatures() { return $this->features; }
    /**
     * @return array
     */
    public function getProperties() { return $this->properties; }
    /**
     * @return array
     */
    public function getAutoPopupProperties() { return $this->auto_popup_properties; }
    /**
     * @return array
     */
    public function getIcon() { return $this->icon; }
    /**
     * @return array
     */
    public function getPath() { return $this->path; }
    /**
     * @return array
     */
    public function getActivePath() { return $this->active_path; }
    /**
     * @return array
     */
    public function getBorder() { return $this->border; }
    /**
     * @return array
     */
    public function getActiveBorder() { return $this->active_border; }
    /**
     * @return array
     */
    public function getExtras() { return $this->extras; }

    // utility functions

    /**
     * Creates GeoJSON array for the dataset
     * 
     * @param array $yaml
     * @return array
     */
    public static function createExport($yaml) {
        // create array of geojson features
        $features = [];
        $id = Utils::getStr($yaml, 'id');
        foreach (Utils::getArr($yaml, 'features') as $feature) {
            $features[] = Feature::fromDataset($feature, Utils::get($yaml, 'feature_type'), $id, null)->toGeoJson();
        }
        // set the other settings and return the json array
        return [
            'type' => 'FeatureCollection',
            'name' => Utils::getStr($yaml, 'title') ?: $id,
            'features' => $features,
        ];
    }
    /**
     * Ensures the provided the value exists in the provided properties array or is equal to 'none'. Returns the value if so, otherwise null.
     * 
     * @param string $name_prop The value to validate
     * @param array $properties The array to check
     * @return string|null
     */
    public static function validateNameProperty($name_prop, $properties) {
        if (!is_string($name_prop) || !is_array($properties)) return null; // check input types
        if (in_array($name_prop, $properties) || $name_prop === 'none') return $name_prop;
        else return null;
    }
    /**
     * Ensures each value in the auto popup props array exists in the properties array. Removes any values that do not, and returns modified array.
     * 
     * @param $auto_popup_props The array to validate
     * @param $properties The array to check
     * @return array
     */
    public static function validateAutoPopupProperties($auto_popup_props, $properties) {
        if (!is_array($auto_popup_props) || !is_array($properties)) return []; // check input types
        return array_values(array_intersect($auto_popup_props, $properties));
    }
    /**
     * Determines the appropriate number to use for a new unique feature id. Feature id will be created as "$id--$number". Starts with number from feature count, then increments by one until the generated id would be unique. Returns that number.
     * 
     * @param string $id The dataset id, used for the first part of the feature id
     * @param array $feature_ids Existing feature ids - the new id must not match any of these
     * @param int $feature_count The number to start at, use one if not set/valid
     * @return int
     */
    public static function nextFeatureCount($id, $feature_ids, $feature_count) {
        $dataset_id = is_string($id) ? $id : '';
        $count = is_int($feature_count) ? $feature_count + 1 : 1;
        while(in_array("$dataset_id--$count", $feature_ids)) {
            // as long as a feature exists with the proposed id, keep incrementing count
            $count++;
        }
        return $count;
    }
    /**
     * Lists any matches between original and update features, using either coordinates or a chosen property. Does not verify that coordinates/property is unique for all features. Results are undefined if the values are not, so choose carefully. Returns an array linking the ids from the update features to the ids of their matches in the original features.
     * 
     * @param string $dataset_prop Either 'coords' or a valid dataset property
     * @param string|null $file_prop Irrelevent if dataset prop is coords. If provided, is used as the 'dataset prop' for the update features (i.e. match dataset property 'x' to update property 'y')
     * @param array $original_features [id => Feature] from existing dataset
     * @param array $update_features [id => Feature] from temporary update dataset
     * @return array [tmp/update_id => original_id]
     */
    public static function matchFeatures($dataset_prop, $file_prop, $original_features, $update_features) {
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
                if ($id = Utils::get($index, $coords)) $matches[$tmp_id] = $id;
            }
        } else {
            // match features based on properties
            // first create index of original feature property values to reference
            $index = [];
            foreach ($original_features as $id => $feature) {
                // note that this won't work well if more than one feature has the same value for the property
                if ($value = $feature->getProperty($dataset_prop)) $index[$value] = $id;
            }
            // then look for matches
            foreach ($update_features as $tmp_id => $update_feature) {
                // use dataset prop as default file prop
                $value = $update_feature->getProperty($file_prop ?: $dataset_prop);
                if ($id = Utils::get($index, $value)) $matches[$tmp_id] = $id;
            }
        }
        return $matches;
    }

    /**
     * First priority is property called name, next is property beginning or ending with name, and last resort is first property, if available
     * 
     * @param array $properties
     * @return string|null The value for the name_property
     */
    public static function determineNameProperty($properties) {
        if (!is_array($properties) || count($properties) < 1) return 'none';
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
     * 
     * @param array $rename rename_properties from dataset yaml
     * @param array $properties properties from dataset yaml
     * @return array [key => value] where keys are all property values before renaming and values are all property values after renaming (may be exactly the same)
     */
    public static function validateUpdateProperties($rename, $properties) {
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