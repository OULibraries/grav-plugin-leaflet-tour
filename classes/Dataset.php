<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;
use RocketTheme\Toolbox\File\File;

/**
 * @property MarkdownFile|null $file The dataset page
 * @property string $type Point, LineString, MultiLineString, Polygon, or MultiPolygon
 * @property string $id Unique identifier for the dataset
 * @property string|null $title Name of the dataset
 * @property string|null $upload_file_path Path to the original uploaded file (only if the dataset was automatically generated from an uploaded file)
 * @property string|null $attribution Attribution for the source of the dataset
 * @property string|null $name_property Which property to use for generating default feature names
 * @property int $feature_count Used for generating new feature ids - may not actually track the number of features, since features may be deleted
 * @property bool $ready_for_update Indicates if dataset is set to be updated by file upload in the plugin config, will be set to false if the dataset page is saved since new settings might interfere with the prepared update
 * @property array $legend ['text' => string, 'summary' => string, 'symbol_alt' => string], text is displayed in the legend; summary is added to feature alt text; symbol_alt is used as alt text for the dataset symbol in the legend (but not for symbols on the map); not all fields may be set
 * @property array $features [id => Feature]
 * @property array $properties [string] Properties that all features can have
 * @property array $auto_popup_properties [string] Can only include values from $properties; indicates which properties should be added to automatically generated feature popup content
 * @property array $icon - ['file' => string, 'rounding' => bool, 'width' => int, 'height' => int, 'anchor_x' => int, 'anchor_y' => int, 'tooltip_anchor_x' => int, 'tooltip_anchor_y' => int, 'shadow' => string, 'shadow_width' => int, 'shadow_height' => int, 'shadow_anchor_x' => int, 'shadow_anchor_y' => int, 'class' => string, 'retina' => string]
 * - also accepts items in the form of Leaflet icon options, e.g. 'iconSize' => [int, int] instead of 'height' and 'width'
 * - not all fields may be set
 * @property array $path ['stroke' => bool, 'color' => string, 'weight' => int, 'opacity' => float, 'lineCap' => string, 'lineJoin' => string, 'dashArray' => string, 'dashOffset' => int, 'fill' => bool, 'fillColor' => string, 'fillOpacity' => float], options correspond to Leaflet path options, not all fields may be set
 * @property array $active_path same options as $path
 * @property array $border same options as $path, except no fill, fillColor, or fillOpacity
 * @property array $active_border same options as $border
 * @property array $extras [key => value]
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
    const DEFAULT_ACTIVE_BORDER = [
        'weight' => 4,
    ];
    // Default options for icon when no file is defined (default icon used), used when getting icon options
    const DEFAULT_MARKER_FALLBACKS = [
        'iconUrl' => 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.6.0/images/marker-icon.png',
        'width' => 25,
        'height' => 41,
        'anchor_x' => 12,
        'anchor_y' => 41,
        'tooltip_anchor_x' => 2,
        'tooltip_anchor_y' => 0,
        'shadowUrl' => 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.6.0/images/marker-shadow.png',
        'shadow_width' => 41,
        'shadow_height' => 41,
        'iconRetinaUrl' => 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.6.0/images/marker-icon-2x.png',
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
     * Prepares a dataset object from a set of options. Features and other such values are assumed to be valid - should be validated beforehand if necessary.
     * - Sets and validates data type for all expected yaml options
     * - Accepts type as either "feature_type" or "type" (feature_type has priority)
     * - Creates feature objects from feature yaml
     * - Sets and validates file (if provided)
     * - Sets any extra values to allow additional customization
     * 
     * @param array $options Original dataset yaml, possibly with file
     */
    private function __construct($options) {
        // Validate file - probably a better way in the future
        try {
            $file = Utils::get($options, 'file');
            $file->exists();
            $this->file = $file;
        } catch (\Throwable $t) {
            $this->file = null;
        }
        // type
        $this->type = Feature::validateFeatureType(Utils::get($options, 'feature_type') ?? Utils::get($options, 'type'));
        // id, title, upload_file_path, attribution, name_property
        $this->id = Utils::getStr($options, 'id', '');
        foreach (['title', 'upload_file_path', 'attribution', 'name_property'] as $key) {
            $this->$key = Utils::getStr($options, $key, null);
        }
        // feature_count, ready_for_update
        $this->feature_count = Utils::getType($options, 'feature_count', 'is_int', 0);
        $this->ready_for_update = Utils::getType($options, 'ready_for_update', 'is_bool', false);
        // legend, features, properties, auto_popup_properties, icon/path/border options
        foreach (['legend', 'features', 'properties', 'auto_popup_properties', 'icon', 'path', 'active_path', 'border', 'active_border'] as $key) {
            $this->$key = Utils::getArr($options, $key);
        }
        // Set features
        $features = [];
        foreach ($this->features as $feature_yaml) {
            $feature = new Feature($feature_yaml, $this->type, $this->name_property, $this->id);
            if ($id = $feature->getId()) $features[$id] = $feature;
            else $features[] = $feature;
        }
        $this->features = $features;
        // Set extras
        $keys = ['feature_type', 'type', 'id', 'title', 'upload_file_path', 'attribution', 'legend','properties', 'icon', 'path', 'active_path', 'border', 'active_border', 'name_property', 'auto_popup_properties', 'features', 'feature_count', 'ready_for_update', 'file'];
        $this->extras = array_diff_key($options, array_flip($keys));
    }
    /**
     * Creates a new dataset from a valid markdown file
     * - Sets all options from file, plus file
     * 
     * @param MarkdownFile $file
     * @return Dataset
     */
    public static function fromFile($file) {
        return new Dataset(array_merge($file->header(), ['file' => $file]));
    }
    /**
     * Creates a new dataset from an array (equivalent to header data from a markdown file)
     * - Sets all options from array
     * 
     * @param array $options
     * @return Dataset
     */
    public static function fromArray($options) {
        return new Dataset($options);
    }
    /**
     * Creates a dataset from an array, but only using the keys specified
     * - Sets all options from array that are specified in input keys
     * 
     * @param array $options All dataset options
     * @param array $keys The keys to actually include
     * @return Dataset
     */
    public static function fromLimitedArray($options, $keys) {
        return new Dataset(array_intersect_key($options, array_flip($keys)));
    }

    /**
     * Validates yaml for new or updated dataset, called when a dataset page is saved
     * - Validates feature type: Only allows changing if: both old and new types are shapes (i.e. not 'Point'), old and new types are different, and either current/old features is empty or new features is empty
     * - Only modifies id or upload_file_path if not already set
     * - Sets ready_for_update to false
     * - Renames properties (retaining order) (i.e. sets properties to values of provided list)
     * - Validates/renames name property and auto popup properties
     * - Updates features list: Adds and removes features as specified
     * - Validates updates for existing features
     * - Validates new features before adding (only adds if valid)
     * - Generates new unique ids for new features (and updates feature_count accordingly)
     * - Renames feature properties if needed
     * - Provides correct path for modifying feature popup content images
     * - Sets names for new features (yaml 'name')
     * - Uses constructor to validate all other values
     * 
     * @param array $yaml Dataset yaml to validate
     * @param array $new_properties List of potentially renamed properties [old_name => new_name]
     * @param string|null $path Path to dataset folder (for modifying popup content image paths)
     * @param array $original_yaml The dataset yaml before the update (if there was any)
     * @return array Updated yaml
     */
    public static function validateUpdate($new_yaml, $new_properties, $path = null, $original_yaml = []) {
        $yaml = self::fromArray($new_yaml)->toYaml(); // validate all data types
        // don't let it modify features, though
        $yaml['features'] = Utils::getArr($new_yaml, 'features');
        // id, upload_file_path, ready_for_update
        if ($id = Utils::getStr($original_yaml, 'id')) $yaml['id'] = $id;
        if ($upload_path = Utils::getStr($original_yaml, 'upload_file_path')) $yaml['upload_file_path'] = $upload_path;
        $yaml['ready_for_update'] = false;

        // feature type
        // validate first
        $new_type = Feature::validateFeatureType($yaml['feature_type']);
        // check if feature type should revert: must be original type to revert to, and either one of the types is 'Point' or both original and new yaml have features
        if ($old_type = Utils::getStr($original_yaml, 'feature_type')) {
            if (($old_type === 'Point') || ($new_type === 'Point') || (!empty(Utils::getArr($original_yaml, 'features')) && !empty($yaml['features']))) {
                // cannot change type - revert
                $yaml['feature_type'] = $old_type;
            }
        }

        // use new properties list to validate name prop and auto popup property
        // name prop: must be set in yaml, must match one of the keys (old property values) in properties list - if found, set to value (new property values) of key in properties list
        if ($name_prop = $yaml['name_property']) $yaml['name_property'] = Utils::getStr($new_properties, $name_prop, 'none');
        // auto popup properties: must be set in yaml, each must match one of the keys (old values) in props list - for each found, set to value (new values) of key in props list
        $auto = [];
        foreach ($yaml['auto_popup_properties'] as $prop) {
            if ($prop = Utils::getStr($new_properties, $prop, null)) $auto[] = $prop;
        }
        $yaml['auto_popup_properties'] = $auto;
        // set properties - just the values
        $yaml['properties'] = array_values($new_properties);

        // update features list
        // set path to correct value to pass to features on validation
        $path = str_replace(Grav::instance()['locator']->findResource('page://') . '/', '', $path);
        // set array of old features [id => coordinates] for providing default coordinates for feature validation
        $old_features = array_column(Utils::getArr($original_yaml, 'features'), 'coordinates', 'id');
        $features = [];
        $feature_count = $yaml['feature_count'];
        foreach ($yaml['features'] as $feature_yaml) {
            // determine if this is a new feature and therefore requires a new unique id
            $id = Utils::getStr($feature_yaml, 'id');
            if ((!$id) || (!Utils::get($old_features, $id)) || isset($features[$id])) {
                // no id, not a matching id, or id of already added feature - check feature validity
                $feature_yaml = Feature::validateUpdate($feature_yaml, $yaml['feature_type'], $yaml['name_property'], $path, null);
                if ($feature_yaml) {
                    // new feature is valid, generate id and add
                    $feature_count = self::nextFeatureCount($yaml['id'], array_keys($old_features), $feature_count);
                    $feature_yaml['id'] = $yaml['id'] . "--$feature_count";
                    // set name if no custom name or name prop
                    if (!$feature_yaml['name']) $feature_yaml['name'] = $feature_yaml['id'];
                }
                else continue; // leave this iteration of the for loop - no feature to finish validating and adding
            } else {
                // feature already exists - don't generate new id, but do validate update
                $feature_yaml = Feature::validateUpdate($feature_yaml, $yaml['feature_type'], $yaml['name_property'], $path, Utils::get($old_features, $id));
            }
            // rename feature properties if needed
            $props = [];
            foreach ($feature_yaml['properties'] as $old_key => $value) {
                // only rename the property if there is actually a value in the list, keep as is otherwise (allows for setting any properties you like in expert mode)
                $key = Utils::getStr($new_properties, $old_key, null) ?? $old_key;
                $props[$key] = $value;
            }
            $feature_yaml['properties'] = $props;
            // add feature to list (use index to ensure no new features will be added with same id)
            $features[$feature_yaml['id']] = $feature_yaml;
        }
        $yaml['features'] = array_values($features);
        $yaml['feature_count'] = $feature_count;

        return $yaml;
    }
    /**
     * Builds a dataset from parsed json content
     * - Creates new unique id for the dataset (based on cleaned up file name)
     * - Sets type to type of first valid feature
     * - Validates features - only includes features with valid coordinates
     * - Creates unique id for each valid feature
     * - Returns array if there is at least one valid feature, null otherwise
     * - Sets yaml 'name' for each feature
     * - Sets properties list to properties from all valid features (invalid features don't contribute)
     * - Sets title/name, feature_count, and/or upload_file_path if provided in json
     * - Uses id if no title/name is provided
     * - Sets path/border defaults for shape datasets
     * - Determines and sets a best guess for name property
     * 
     * @param array $json Parsed json content
     * @param string $file_name The name of the uploaded file with the original dataset content
     * @param array $dataset_ids All existing dataset ids (to prevent duplicates)
     * @return array|null Dataset yaml if at least one valid feature, otherwise null
     */
    public static function initializeJsonDataset($json, $file_name, $dataset_ids) {
        // Create new unique id
        $id = preg_replace('/\.[^.]+$/', '', $file_name); // remove file extension
        $id = Utils::cleanUpString($id);
        // make sure the id is unique - add number to end and increment until an id is found that is not in the array of existing ids
        $count = 1;
        $base_id = $id;
        while (in_array($id, $dataset_ids)) {
            $id = "$base_id-$count";
            $count++;
        }

        // Validate features
        $type = null; // to be set by first valid feature
        $features = $properties = []; // empty arrays to add to
        $feature_count = Utils::getType($json, 'feature_count', 'is_int', 0);
        foreach (Utils::getArr($json, 'features') as $feature_json) {
            // validate - only add if valid
            if ($feature = Feature::validateJsonFeature($feature_json, $type)) {
                // set type if not already set (i.e. if this is the first valid feature)
                if (!$type) $type = Feature::validateFeatureType(Utils::get($feature_json['geometry'], 'type'));
                // create unique id for feature
                $feature_count = self::nextFeatureCount($id, [], $feature_count);
                $feature['id'] = "$id--$feature_count";
                // add to features list
                $features[] = $feature;
                // add any properties to properties list
                $properties = array_merge($properties, $feature['properties']);
            }
        }
        // only proceed if there is at least one valid feature
        if (empty($features)) return null;
        $properties = array_keys($properties);
        // set best guess for name property
        $name_prop = self::determineNameProperty($properties);
        // set feature names
        $named_features = [];
        foreach ($features as $feature) {
            // id - default
            $name = $feature['id'];
            // name prop?
            if ($name_prop && ($prop = Utils::getStr($feature['properties'], $name_prop))) $name = $prop;
            $named_features[] = array_merge(['name' => $name], $feature);
        }
        $dataset = [
            'id' => $id,
            'feature_type' => $type,
            'features' => $named_features,
            'properties' => $properties,
            'name_property' => $name_prop,
            'title' => Utils::getStr($json, 'name', null) ?? Utils::getStr($json, 'title', null) ?? $id,
            'feature_count' => $feature_count,
            'upload_file_path' => Utils::getStr($json, 'upload_file_path', null),
        ];   
        // set path/border defaults for shape datasets
        if ($type !== 'Point') {
            $dataset['path'] = self::DEFAULT_PATH;
            $dataset['active_path'] = self::DEFAULT_ACTIVE_PATH;
            $dataset['border'] = self::DEFAULT_BORDER;
            $dataset['active_border'] = self::DEFAULT_ACTIVE_BORDER;
        }
        // return array
        return $dataset;
    }
    
    /**
     * Creates a new dataset by merging options from an existing dataset with overrides set by a tour
     * - Uses attribution and auto popup properties from tour options if set, otherwise from dataset
     * - Merges icon and shape options from tour options and dataset (as appropriate for type)
     * - Returns dataset with only the necessary values
     * - Uses legend text from tour options if set, otherwise dataset
     * - Uses legend summary from tour options if set, otherwise from dataset only if legend text is not set by tour
     * - Uses legend symbol alt from tour options if set, otherwise from dataset only if icon file/path color not set by tour
     * - Uses legend text or symbol alt as default for legend summary
     * 
     * @param Dataset $dataset A valid existing dataset
     * @param array $tour_options Dataset overrides set for the dataset in the tour
     * @return Dataset
     */
    public static function fromTour($dataset, $tour_options) {
        $options = [
            'id' => $dataset->getId(),
            'type' => $dataset->getType(),
        ];
        // attribution
        $options['attribution'] = Utils::getStr($tour_options, 'attribution', null) ?? $dataset->getAttribution();
        // auto popup properties
        $options['auto_popup_properties'] = Utils::getArr($tour_options, 'auto_popup_properties', null) ?? $dataset->getAutoPopupProperties();
        // icon / shape options
        if ($options['type'] === 'Point') {
            $options['icon'] = array_merge($dataset->getIcon(), Utils::getArr($tour_options, 'icon'));
        } else {
            foreach (['path' => $dataset->getPath(), 'active_path' => $dataset->getActivePath(), 'border' => $dataset->getBorder(), 'active_border' => $dataset->getActiveBorder()] as $key => $dataset_options) {
                $options[$key] = array_merge($dataset_options, Utils::getArr($tour_options, $key));
            }
        }

        // legend text
        $options['legend'] = [];
        $tour_legend = Utils::getArr($tour_options, 'legend');
        $text = Utils::getStr($tour_legend, 'text', null);
        $options['legend']['text'] = $text ?? $dataset->getLegend('text');
        // legend summary - only if text not in tour
        $summary = Utils::getStr($tour_legend, 'summary', null);
        if (!$text && !$summary) $summary = $dataset->getLegend('summary');
        $options['legend']['summary'] = $summary;
        // legend symbol alt
        $symbol = Utils::getStr($tour_legend, 'symbol_alt', null);
        if (!$symbol) {
            // determine if symbol alt should be replaced
            // for points - only if icon file not set by tour
            if ($options['type'] === 'Point') {
                $icon = Utils::getArr($tour_options, 'icon');
                if (!Utils::getStr($icon, 'file')) $symbol = $dataset->getLegend('symbol_alt');
            } else {
                // for shapes - only if path/stroke color not set by tour
                // could also include path fill color, border color, etc. as desired
                $path = Utils::getArr($tour_options, 'path');
                if (!Utils::getStr($path, 'color')) $symbol = $dataset->getLegend('symbol_alt');
            }
        }
        $options['legend']['symbol_alt'] = $symbol;
        // use legend text or symbol alt as default for legend summary
        if (!$options['legend']['summary']) $options['legend']['summary'] = $options['legend']['text'] ?: $symbol;

        return new Dataset($options);
    }
    /**
     * Creates a new dataset with only those features that do not have matches
     * - Removes all/only matching features from dataset features list
     * - Changes nothing besides features list
     * 
     * @param array $matches List of all matches: [update_id => old_id] where update_id identifies the new feature data in the update dataset and old_id identifies the existing feature in the old dataset. In this case, only the old ids matter, as features are not being modified. Order does not matter
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
     * - Iff modify, updates coordinates and properties for matching features
     * - Keeps features (modified or not) in same order as from original dataset
     * - Iff remove, removes non-matching features from original dataset
     * - Iff add, adds new features to end of list - generates new unique ids
     * - Iff add, updates feature_count based on new features added
     * - Iff modify and/or add, merges original and update properties lists
     * - Does not modify anything else besides features, feature_count, and properties
     * - Can perform multiple options (add, modify, remove) in one update without interference
     * 
     * @param array $matches List of all matches: [update_id => old_id] where update_id identifies the new feature data in the update dataset and old_id identifies the existing feature in the old dataset, order does not matter
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

    // blueprint methods
    /**
     * Get all properties of the given dataset
     * - Includes all properties, indexed by themselves, and option for 'none'
     * - Includes name_property if set and invalid
     * 
     * @param array $yaml
     * @return array [$prop => $prop]
     */
    public static function getBlueprintPropertyList($yaml) {
        $props = Utils::getArr($yaml, 'properties');
        $list = array_combine($props, $props);
        $list = array_merge(['none' => 'None'], $list);
        // invalid?
        $name_prop = Utils::getStr($yaml, 'name_property');
        if ($name_prop && !in_array($name_prop, array_keys($list))) $list[$name_prop] = 'Invalid, please remove';
        return $list;
    }
    /**
     * Get all properties for a given dataset as text input fields
     * 
     * @param array $yaml
     * @return array [.$prop => [type, label]]
     */
    public static function getBlueprintPropertiesFields($yaml) {
        $fields = [];
        // get dataset and list of properties
        $props = Dataset::fromLimitedArray($yaml, ['properties'])->getProperties();
        foreach ($props as $prop) {
            $fields[".$prop"] = [
                'type' => 'text',
                'label' => $prop,
            ];
        }
        return $fields;
    }
    /**
     * Get all properties for a given dataset, but with order possibly modified by existing auto popup properties. Essentially: Current options will be displayed in the order the list is given. So if a dataset has auto props 'f, b, a, c' and props 'a, b, c, d, e, f' the auto props will be shown as 'a, b, c, f'. Instead, the list would need to go 'f, b, a, c, d, e' so that the existing auto props are displayed correctly.
     * - Includes all properties, indexed by themselves
     * - If auto popup props is currently set, keeps those properties in order and at front of list
     * - Includes any invalid auto popup props (at end of list)
     * 
     * @param array $yaml
     * @return array [$prop => $prop]
     */
    public static function getBlueprintAutoPopupOptions($yaml) {
        $props = Utils::getArr($yaml, 'properties');
        $auto_props = Utils::getArr($yaml, 'auto_popup_properties');
        $list = array_combine($props, $props); // the basics
        // move valid auto popup props to front
        $valid = array_intersect($auto_props, $props);
        $list = array_merge(array_flip($valid), $list);
        // invalid?
        $invalid = array_diff($auto_props, $props);
        foreach ($invalid as $key) {
            $list[$key] = 'Invalid, please remove';
        }
        return $list;
    }
    /**
     * Return 'hidden' for line datasts, default value for others (allows selectively hiding shape options in blueprint)
     * 
     * @param array $yaml
     * @param string $default
     * @return string
     */
    public static function getBlueprintFillType($yaml, $default) {
        $type = Feature::validateFeatureType($yaml['feature_type']);
        if (str_contains($type, 'LineString')) {
            // LineString or MultiLineString
            return 'hidden';
        }
        return $default;
    }
    /**
     * Dynamically set default colors for path fillColor, active path color, and active path fillColor
     * - Sets default path.fillColor to path.color if set, otherwise standard default
     * - Sets default active_path.color to path.color if set, otherwise standard default
     * - Sets default active_path.fillColor to path.fillColor if set, otherwise active path color, path color, or standard default
     * 
     * @param array $yaml
     * @param string $key
     * @return string
     */
    public static function getBlueprintDefaults($yaml, $key) {
        $path = Utils::getArr($yaml, 'path');
        switch ($key) {
            case 'path_fillColor':
            case 'active_path_color':
                // default: path color ?? default color
                return Utils::getStr($path, 'color') ?: Dataset::DEFAULT_PATH['color'];
            case 'active_path_fillColor':
                // default: regular fill color ?? active path color ?? default for active path color
                return Utils::getStr($path, 'fillColor') ?: Utils::getStr(Utils::getArr($yaml, 'active_path'), 'color') ?: self::getBlueprintDefaults($yaml, 'path_fillColor');
        }
        return '';
    }

    /**
     * Returns content for the dataset page header
     * - Returns all expected values
     * - Returns features as unindexed yaml (instead of indexed Feature objects)
     * - Returns extras merged, not as separate array
     * 
     * @return array dataset yaml
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
     * 
     * iconUrl: Can be set by user as icon 'file' (from associated folder) or as iconUrl, otherwise default; determines which set of defaults are used
     * - Can be set by user as icon 'file' (from associated folder)
     * - Can be set by user as iconUrl (if icon.file invalid or not set)
     * - Default icon file used if neither icon.file nor icon.iconUrl are valid
     * 
     * iconRetinaUrl: Can be set by user as icon 'retina' (from associated folder) or as iconRetinaUrl, otherwise default if it exists (no default for custom icons)
     * - Can be set by user as icon 'retina' (from associated folder)
     * - Can be set by user as iconRetinaUrl (if icon.retina invalid or not set)
     * - Default retina file used if neither icon.retina nor icon.iconRetinaUrl are valid (and icon is default)
     * - Nothing used if neither icon.retina nor icon.iconRetinaUrl are valid (and icon is custom)
     * 
     * shadowUrl: Can be set by user as icon 'shadow' (from associated folder) or as shadowUrl, otherwise default if it exists (no default for custom icons)
     * - Can be set by user as icon 'shadow' (from associated folder)
     * - Can be set by user as shadowUrl (if icon.shadow invalid or not set)
     * - Default shadow file used if neither icon.shadow nor icon.shadowUrl are valid (and icon is default)
     * - Nothing used if neither icon.shadow nor icon.shadowUrl are valid (and icon is custom)
     * 
     * iconSize: Can be set by user individually as 'height' and/or 'width' (must be positive integers) or as iconSize (only if no values for 'height' or 'width' are provided); valid values for 'height' and 'width' are combined with defaults
     * - Can be set by user as icon 'height' and/or icon 'width' (must be positive integers)
     * - Can be set by user as iconSize (only if both icon.height and icon.width are not set - invalid counts as set)
     * - Uses correct default values for height and/or width - default icon
     * - Uses correct default values for height and/or width - custom icon
     * 
     * shadowSize: Can be set by user individually as 'shadow_height' and/or 'shadow_width' (must be positive integers) or as shadowSize (only if no values for 'height' or 'width' are provided); valid values for height and width are combined with defaults; if defaults array does not include shadow size options, the icon size determined above is used as defaults instead
     * - Can be set by user as icon 'shadow_height' and/or icon 'shadow_width' (must be positive integers)
     * - Can be set by user as shadowSize (only if both icon.shadow_height and icon.shadow_width are not set - invalid counts as set)
     * - Uses correct default values for height and/or width - default icon
     * - Uses regular icon height/width (if set) as defaults - custom icon
     * 
     * iconAnchor: Can be set by user individually as 'anchor_x' and/or 'anchor_y' (must be integers) or as iconAnchor (only if no x or y values are provided); valid values for x and y are combined with defaults (if they exist); iconAnchor is only included if there are valid values for both x and y
     * - Can be set by user as 'anchor_x' and/or 'anchor_y' (must be integers)
     * - Can be set by user as iconAnchor (only if both anchor_x and anchor_y are not set - invalid counts as set)
     * - Uses correct defaults for x and y - default icon
     * - Only includes values for custom icon if x and y are both set and valid (i.e. no defaults needed)
     * 
     * tooltipAnchor: Can be set by user individually as 'tooltip_anchor_x' and/or 'tooltip_anchor_y' (must be integers) or as tooltipAnchor (only if no x or y values are provided); valid values for x and y are combined with defaults
     * - Can be set by user as 'tooltip_anchor_x' and/or 'tooltip_anchor_y' (must be integers)
     * - Can be set by user as tooltipAnchor (only if both tooltip_anchor_x and tooltip_anchor_y are not set - invalid counts as set)
     * - Uses correct defaults for x and y - default icon
     * - Uses correct defaults for x and y - custom icon
     * 
     * shadowAnchor: Can be set by user individually as 'shadow_anchor_x' and 'shadow_anchor_y' (must be integers) or as shadowAnchor (only if no x or y values are provided); no defaults; shadowAnchor is only included if there are valid values for both x and y
     * - Can be set by user as 'shadow_anchor_x' and/or 'shadow_anchor_y' (must be integers)
     * - Can be set by user as shadowAnchor (only if both shadow_anchor_x and shadow_anchor_y are not set - invalid counts as set)
     * - Only includes values for if x and y are both set and valid (i.e. no defaults needed)
     * 
     * className: Created by combining default class name with user provided values for 'className' and 'class'; additional class is added if user has set 'rounding' as true
     * - Class name starts with default class name
     * - User can add values to class name using icon.className
     * - If icon.rounding is true, class name ends with 'round'
     * 
     * Includes extra options but not unused yaml options - The yaml keys will not be included in the returned array (i.e. iconSize will be included, but not 'height' or 'width'). Any values set that are not in the leaflet or blueprint/yaml keys will be added to the returned array, allowing for potential additional customization.
     * 
     * @return array ['iconUrl' => string, 'iconSize' => [int, int], 'iconAnchor' => [int, int], 'tooltipAnchor' => [int, int], 'shadowUrl' => string, 'shadowSize' => [int, int], 'shadowAnchor' => [int, int], 'iconRetinaUrl' => string, 'className' => string]
     */
    public function getIconOptions() {
        $icon = $this->getIcon(); // icon options as provided by user
        $route = Utils::ICON_ROUTE; // route for checking if file exists
        $full_route = Utils::getRoot() . "/$route"; // route for accessing file from frontend
        $defaults = []; // fallback values for invalid/null values in icon, needs to be set
        $options = []; // icon options to return
        // determine correct defaults to use and set icon url
        if (($file = Utils::getStr($icon, 'file')) && File::instance("$route/$file")->exists()) {
            // valid icon file provided by user
            $options['iconUrl'] = "$full_route/$file";
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
        if (($file = Utils::getStr($icon, 'retina')) && File::instance("$route/$file")->exists()) $options['iconRetinaUrl'] = "$full_route/$file";
        else if ($url = Utils::getStr($icon, 'iconRetinaUrl') ?: Utils::getStr($defaults, 'iconRetinaUrl')) $options['iconRetinaUrl'] = $url;
        if (($file = Utils::getStr($icon, 'shadow')) && File::instance("$route/$file")->exists()) $options['shadowUrl'] = "$full_route/$file";
        else if ($url = Utils::getStr($icon, 'shadowUrl') ?: Utils::getStr($defaults, 'shadowUrl')) $options['shadowUrl'] = $url;
        // icon size can be set directly, only use if neither height nor width is set
        if (!isset($icon['height']) && !isset($icon['width']) && ($size = Utils::get($icon, 'iconSize')) && is_array($size) && (count($size) === 2) && is_int($size[0]) && is_int($size[1]) && $size[0] >= 0 && $size[1] >= 0) $options['iconSize'] = $size;
        else $options['iconSize'] = [self::getPositiveInt($icon, 'width') ?? $defaults['width'], self::getPositiveInt($icon, 'height') ?? $defaults['height']];
        // shadow size can be set directly, only use if neither height nor width is set
        if (!isset($icon['shadow_width']) && !isset($icon['shadow_height']) && ($size = Utils::get($icon, 'shadowSize')) && is_array($size) && (count($size) == 2) && is_int($size[0]) && is_int($size[1]) && $size[0] >= 0 && $size[1] >= 0) $options['shadowSize'] = $size;
        // default shadow size may not be set - use icon size instead in that case
        else $options['shadowSize'] = [
            self::getPositiveInt($icon, 'shadow_width') ?? Utils::get($defaults, 'shadow_width') ?? $options['iconSize'][0],
            self::getPositiveInt($icon, 'shadow_height') ?? Utils::get($defaults, 'shadow_height') ?? $options['iconSize'][1],
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
     * @param array $array The array to check
     * @param string $key The key to look for
     * @return int|null
     */
    public static function getPositiveInt($array, $key) {
        $int = Utils::getType($array, $key, 'is_int');
        if ($int && ($int >= 0)) return $int;
        else return null;
    }
    /**
     * Determines if the provided array has a value for the key that is a number greater than or equal to zero and less than or equal to one. Returns value if valid, otherwise null.
     * 
     * @param array $array The array to check
     * @param string $key The key to look for
     * @return ?float
     */
    public static function checkOpacity($array, $key) {
        $opacity = Utils::get($array, $key);
        if (!is_numeric($opacity) || $opacity < 0 || $opacity > 1) return null;
        else return $opacity;
    }
    /**
     * Keep any additional values set that aren't in the standard list of accepted options
     * 
     * @param array $options The options so far
     * @param array $original Where to look for extras
     * @return array The options with extras added
     */
    public static function addExtras($options, $original) {
        $keys = ['stroke', 'color', 'opacity', 'weight', 'fill', 'fillColor', 'fillOpacity'];
        return array_merge($options, array_diff_key($original, array_flip($keys)));
    }
    /**
     * Combines all the necessary shape options that will actually be applied to features on the map. This function is important because any "border" will have to go below the stroke. Therefore, the base object (including any fill for polygons) should use border settings if applicable, with stroke provided in a separate array.
     * - Returns empty array for point datasets
     * - If feature has border, this includes a 'path' array with border and fill options (and 'active_path'); a 'stroke' (and 'active_stroke') array is included with stroke options only (the switcharound is necessary for appropriate layering on the map)
     * - Otherwise if feature has no border, this includes a 'path' array with stroke and fill options (and 'active_path')
     * 
     * @return array [] or ['path' => array, 'active_path' => array, 'stroke' => array|unset, 'active_stroke' => array|unset]
     */
    public function getShapeOptions() {
        if ($this->getType() === 'Point') return [];
        $border = $this->getBorderOptions();
        $options = [];
        if (!empty($border)) {
            $options['path'] = array_merge($border, $this->getFillOptions());
            $options['active_path'] = array_merge($this->getActiveBorderOptions(), $this->getActiveFillOptions());
            $options['stroke'] = $this->getStrokeOptions();
            $options['active_stroke'] = $this->getActiveStrokeOptions();
        } else {
            $options['path'] = array_merge($this->getStrokeOptions(), $this->getFillOptions());
            $options['active_path'] = array_merge($this->getActiveStrokeOptions(), $this->getActiveFillOptions());
        }
        return $options;
    }
    /**
     * Validates path stroke settings (stroke, opacity, weight, color) and merges with defaults. Sets 'fill' to false. Does not include any other fill settings, but does include any additional settings added by the user.
     * - Validates stroke - false only if set to false and feature type is polygon; true otherwise
     * - Uses validation function to set opacity, weight, color, and fill (with defaults from the default_path constant)
     * - Adds extras - uses function
     * 
     * @return array ['stroke' => bool, 'opacity' => float, 'weight' => int, 'color' => string, 'fill' => false], plus possible extra key-value pairs
     */
    public function getStrokeOptions() {
        $path = $this->getPath();
        $default = self::DEFAULT_PATH;
        $options = self::validateStrokeOptions($path, $default);
        // set stroke (can only be false for polygons)
        if (str_contains($this->getType(), 'Polygon') && (Utils::get($path, 'stroke') === false)) $options['stroke'] = false;
        else $options['stroke'] = true;
        return self::addExtras($options, $path);
    }
    /**
     * Validates active path stroke settings (stroke, opacity, weight, color) and merges with defaults (which are the regular stroke options). Sets 'fill' to false. Does not include any other fill settings, but does include any additional settings added by the user.
     * - Validates stroke - uses default value
     * - Uses validation function to set opacity, weight, color, and fill (with defaults from the stroke options)
     * - Adds extras - uses function (includes values from stroke options)
     * 
     * @return array ['stroke' => bool, 'opacity' => float, 'weight' => int, 'color' => string, 'fill' => false], plus possible extra key-value pairs
     */
    public function getActiveStrokeOptions() {
        $path = $this->getActivePath();
        $default = $this->getStrokeOptions();
        $options = self::validateStrokeOptions($path, $default);
        $options['stroke'] = $default['stroke'];
        return self::addExtras($options, array_merge($default, $path));
    }
    /**
     * Validates path stroke settings (stroke, opacity, weight, color) and merges with defaults. Sets 'fill' to false. Does not include any other fill settings, but does include any additional settings added by the user.
     * - Validates opacity: Must be between 0 and 1 inclusive (uses function)
     * - Validates weight: Must be positive int (uses function)
     * - Replaces any invalid stroke options (opacity, weight, color) with provided defaults (assumes options to be set in defaults, will throw error if they are not)
     * - Sets 'fill' to false
     * 
     * @param array $path
     * @param array $default Must have valid values for stroke, opacity, weight, and color
     * @return array ['opacity' => float, 'weight' => int, 'color' => string, 'fill' => false]
     */
    public static function validateStrokeOptions($path, $default) {
        return [
            'opacity' => self::checkOpacity($path, 'opacity') ?? $default['opacity'],
            'weight' => self::getPositiveInt($path, 'weight') ?? $default['weight'],
            'color' => Utils::getStr($path, 'color') ?: $default['color'],
            'fill' => false
        ];
    }
    /**
     * Validates path fill settings (fill, fillOpacity, and fillColor) and merges with defaults.
     * - Returns empty array for points and lines
     * - Validates fill: Must be bool, replaces invalid with value from defaults const
     * - Validates fillOpacity: Must be number between 0 and 1 inclusive, replaces invalid with value from defaults const (use function to validate)
     * - Validates fillColor: Must be string, replaces invalid with value from stroke options
     * - Does not include any stroke settings, but does include any additional settings added by the user
     * 
     * @return array [] or ['fill' => bool, 'fillOpacity' => float, 'fillColor' => string] plus possible extra key-value pairs
     */
    public function getFillOptions() {
        // empty array if line
        if (!str_contains($this->getType(), 'Polygon')) return [];
        else {
            $default = self::DEFAULT_PATH;
            $path = $this->getPath();
            $options = [
                // fill must be bool
                'fill' => Utils::getType($path, 'fill', 'is_bool') ?? $default['fill'],
                // validate fill opacity with function
                'fillOpacity' => self::checkOpacity($path, 'fillOpacity') ?? $default['fillOpacity'],
                // fill color must be string
                'fillColor' => Utils::getStr($path, 'fillColor') ?: $this->getStrokeOptions()['color']
            ];
            // add extras
            return self::addExtras($options, $path);
        }
    }
    /**
     * Validates active path fill settings (fill, fillOpacity, and fillColor) and merges with defaults.
     * - Returns empty array for points and lines
     * - Uses fill value from regular fill options
     * - Validates fillOpacity: Must be number between 0 and 1 inclusive, replaces invalid with value from fill options (use function to validate)
     * - Validates fillColor: Must be string, replaces invalid with path fillColor if set, otherwise color from active stroke options
     * - Does not include any stroke settings, but does include any additional settings added by the user
     * 
     * @return array [] or ['fill' => bool, 'fillOpacity' => float, 'fillColor' => string] plus possible extra key-value pairs
     */
    public function getActiveFillOptions() {
        // empty array if line
        if (!str_contains($this->getType(), 'Polygon')) return [];
        else {
            $path = $this->getActivePath();
            $default = $this->getFillOptions();
            $options = [
                'fill' => $default['fill'],
                'fillOpacity' => self::checkOpacity($path, 'fillOpacity') ?? $default['fillOpacity'],
                // use regular fill as default, then whatever stroke color is used for active stroke
                'fillColor' => Utils::getStr($path, 'fillColor',) ?: Utils::getStr($this->getPath(), 'fillColor') ?: $this->getActiveStrokeOptions()['color']
            ];
            // add extras (including extras from regular fill options)
            return self::addExtras($options, array_merge($default, $path));
        }
    }
    /**
     * Validates border settings (stroke, color, opacity, weight) and merges with defaults.
     * - Returns empty array if stroke is not true for both stroke options and border (otherwise sets 'stroke' to true)
     * - Validates color - must be string, returns empty array if not set and valid
     * - Validates opacity: Must be number between 0 and 1 inclusive, replaces invalid with value from stroke options (uses function)
     * - Validates weight: Must be positive int, replaces invalid with value from defaults const (uses function), then must be doubled and added to path weight
     * - Sets 'fill' to false
     * - Does not include other fill options, but does include any additional settings added by the user
     * 
     * @return array [] or ['stroke' => true, 'color' => string, 'opacity' => float, 'weight' => int, 'fill' => false] plust possible extra key-value pairs
     */
    public function getBorderOptions() {
        $border = $this->getBorder();
        $path = $this->getStrokeOptions();
        if (!$path['stroke'] || !Utils::getType($border, 'stroke', 'is_bool')) return [];
        $color = Utils::getStr($border, 'color', null);
        // color must be set and valid
        if ($color) {
            $options = [
                'stroke' => true,
                'color' => $color,
                'opacity' => $this->checkOpacity($border, 'opacity') ?? $path['opacity'],
                // validate and modify weight
                'weight' => ((self::getPositiveInt($border, 'weight') ?? self::DEFAULT_BORDER['weight']) * 2) + $path['weight'],
                'fill' => false,
            ];
            // add extras
            return self::addExtras($options, $border);
        }
        else return [];
    }
    /**
     * Validates active border settings (stroke, color, opacity, weight) and merges with defaults.
     * - Returns empty array if regular border is empty (otherwise sets 'stroke' to true)
     * - Validates color: Must be string, replaces invalid/empty with value from border
     * - Validates opacity: Must be number between 0 and 1 inclusive, replaces invalid with value from border if valid, otherwise with value from active stroke options (use function to validate)
     * - Validates weight: Must be positive int, replaces invalid with value from border if valid, otherwise with value from defaults const; then doubles weight and adds active path weight
     * - Sets 'fill' to false
     * - Does not include other fill options, but does include any additional settings added by the user
     * 
     * @return array [] or ['stroke' => true, 'color' => string, 'opacity' => float, 'weight' => int, 'fill' => false] plust possible extra key-value pairs
     */
    public function getActiveBorderOptions() {
        $border_options = $this->getBorderOptions();
        if (empty($border_options)) return [];
        $active_border = $this->getActiveBorder();
        $border = $this->getBorder();
        $active_stroke = $this->getActiveStrokeOptions();
        $options = [
            'stroke' => true,
            'color' => Utils::getStr($active_border, 'color', null) ?? $border_options['color'],
            // opacity - use active border if valid, otherwise regular border if set, otherwise active path/regular/default
            'opacity' => $this->checkOpacity($active_border, 'opacity') ?? $this->checkOpacity($border, 'opacity') ?? $active_stroke['opacity'],
            // validate and modify weight
            'weight' => ((self::getPositiveInt($active_border, 'weight') ?? self::getPositiveInt($border, 'weight') ?? self::DEFAULT_BORDER['weight']) * 2) + $active_stroke['weight'],
            'fill' => false,
        ];
        // add extras
            return self::addExtras($options, array_merge($border_options, $active_border));
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
     * - Returns normal legend if key is not provided
     * - Returns value for key provided only if key exists and is a string
     * - Returns null if key is provided but does not exist or is not a string
     * 
     * @param string|null $key
     * @return array|mixed ['text' => string, 'summary' => string, 'symbol_alt' => string], not all values may be set
     */
    public function getLegend($key = null) {
        if ($key) return Utils::getStr($this->legend, $key, null);
        else return $this->legend;
    }

    // ordinary getters - no logic, just to prevent directy interaction with object properties
    /**
     * @return MarkdownFile|null The dataset page
     */
    public function getFile() { return $this->file; }
    /**
     * @return string Point, LineString, MultiLineString, Polygon, or MultiPolygon
     */
    public function getType() { return $this->type; }
    /**
     * @return string Unique identifier for the dataset
     */
    public function getId() { return $this->id; }
    /**
     * @return string|null Name of the dataset
     */
    public function getTitle() { return $this->title; }
    /**
     * @return string|null Path to the original uploaded file (only if the dataset was automatically generated from an uploaded file)
     */
    public function getUploadFilePath() { return $this->upload_file_path; }
    /**
     * @return string|null Attribution for the source of the dataset
     */
    public function getAttribution() { return $this->attribution; }
    /**
     * @return string|null Which property to use for generating default feature names
     */
    public function getNameProperty() { return $this->name_property; }
    /**
     * Used for generating new feature ids - may not actually track the number of features, since features may be deleted
     * @return int
     */
    public function getFeatureCount() { return $this->feature_count; }
    /**
     * Indicates if dataset is set to be updated by file upload in the plugin config, will be set to false if the dataset page is saved since new settings might interfere with the prepared update
     * @return bool
     */
    public function isReadyForUpdate() { return $this->ready_for_update; }
    /**
     * @return array [id => Feature]
     */
    public function getFeatures() { return $this->features; }
    /**
     * @return array [string] Properties that all features can have
     */
    public function getProperties() { return $this->properties; }
    /**
     * @return array [string] Can only include values from $properties; indicates which properties should be added to automatically generated feature popup content
     */
    public function getAutoPopupProperties() { return $this->auto_popup_properties; }
    /**
     * @return array ['file' => string, 'rounding' => bool, 'width' => int, 'height' => int, 'anchor_x' => int, 'anchor_y' => int, 'tooltip_anchor_x' => int, 'tooltip_anchor_y' => int, 'shadow' => string, 'shadow_width' => int, 'shadow_height' => int, 'shadow_anchor_x' => int, 'shadow_anchor_y' => int, 'class' => string, 'retina' => string]
     */
    public function getIcon() { return $this->icon; }
    /**
     * @return array ['stroke' => bool, 'color' => string, 'weight' => int, 'opacity' => float, 'lineCap' => string, 'lineJoin' => string, 'dashArray' => string, 'dashOffset' => int, 'fill' => bool, 'fillColor' => string, 'fillOpacity' => float], options correspond to Leaflet path options, not all fields may be set
     */
    public function getPath() { return $this->path; }
    /**
     * @return array ['stroke' => bool, 'color' => string, 'weight' => int, 'opacity' => float, 'lineCap' => string, 'lineJoin' => string, 'dashArray' => string, 'dashOffset' => int, 'fill' => bool, 'fillColor' => string, 'fillOpacity' => float], options correspond to Leaflet path options, not all fields may be set
     */
    public function getActivePath() { return $this->active_path; }
    /**
     * @return array ['stroke' => bool, 'color' => string, 'weight' => int, 'opacity' => float, 'lineCap' => string, 'lineJoin' => string, 'dashArray' => string, 'dashOffset' => int], options correspond to Leaflet path options, not all fields may be set
     */
    public function getBorder() { return $this->border; }
    /**
     * @return array ['stroke' => bool, 'color' => string, 'weight' => int, 'opacity' => float, 'lineCap' => string, 'lineJoin' => string, 'dashArray' => string, 'dashOffset' => int], options correspond to Leaflet path options, not all fields may be set
     */
    public function getActiveBorder() { return $this->active_border; }
    /**
     * @return array [key => value]
     */
    public function getExtras() { return $this->extras; }

    // utility functions

    /**
     * Creates GeoJSON array for the dataset
     * - Returns GeoJSON array with all features
     * - Sets 'name' to dataset name
     * - Sets 'type' to 'FeatureCollection'
     * 
     * @param array $yaml
     * @return array ['type' => 'FeatureCollection', 'name' => string, 'features' => array]
     */
    public static function createExport($yaml) {
        // create array of geojson features
        $features = [];
        $id = Utils::getStr($yaml, 'id');
        foreach (Utils::getArr($yaml, 'features') as $feature) {
            $features[] = (new Feature($feature, Utils::get($yaml, 'feature_type'), null, $id))->toGeoJson();
        }
        // set the other settings and return the json array
        return [
            'type' => 'FeatureCollection',
            'name' => Utils::getStr($yaml, 'title') ?: $id,
            'features' => $features,
        ];
    }
    /**
     * Determines the appropriate number to use for a new unique feature id. Feature id will be created as "$id--$number". Starts with number from feature count, then increments by one until the generated id would be unique. Returns that number.
     * - Only returns count for unique feature id (increments count as much as necessary)
     * - if feature count is invalid, starts at 1
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
     * - Matches point features via coordinates correctly
     * - Matches shape features via coordinates correctly
     * - Matches features via properties correctly (dataset prop only)
     * - Matches features via properties correctly (dataset prop and file prop)
     * - Returns features in order of original features from dataset
     * 
     * @param string $dataset_prop Either 'coords' or a valid dataset property
     * @param string|null $file_prop Irrelevent if dataset prop is coords. If provided, is used as the 'dataset prop' for the update features (i.e. match dataset property 'x' to update property 'y')
     * @param array $original_features [id => Feature] from existing dataset
     * @param array $update_features [id => Feature] from temporary update dataset
     * @return array [tmp/update_id => original_id] (in original feature order)
     */
    public static function matchFeatures($dataset_prop, $file_prop, $original_features, $update_features) {
        $matches = [];
        if ($dataset_prop === 'coords') {
            // match features based on coordinates, must be exact match
            // first create index of new feature coordinates to reference
            $index = array_flip(array_map(function($update_feature) {
                return json_encode($update_feature->getJsonCoordinates());
            }, $update_features)); // returns coords => tmp id, due to array_flip
            // then look for matches
            foreach ($original_features as $id => $feature) {
                $coords = json_encode($feature->getJsonCoordinates());
                if ($tmp_id = Utils::get($index, $coords)) $matches[$tmp_id] = $id;
            }
        } else {
            // match features based on properties
            // first create index of original feature property values to reference
            $index = [];
            foreach ($update_features as $tmp_id => $update_feature) {
                // note that this won't work well if more than one feature has the same value for the property
                if ($value = $update_feature->getProperty($file_prop ?: $dataset_prop)) $index[$value] = $tmp_id;
            }
            // then look for matches
            foreach ($original_features as $id => $feature) {
                // use dataset prop as default file prop
                $value = $feature->getProperty($dataset_prop);
                if ($tmp_id = Utils::get($index, $value)) $matches[$tmp_id] = $id;
            }
        }
        return $matches;
    }
    /**
     * Determines a unique route for a dataset
     * - Creates route consisting of: pages/datasets/[cleaned up name]
     * - Increments slug to avoid duplicates (regardless of type)
     * 
     * @param string $name The title or id of the dataset
     * @param string $type The feature type of the dataset
     * @return MarkdownFile with the unique route determined
     */
    public static function createFile($name, $type) {
        $route = $base_route = Grav::instance()['locator']->findResource('page://') . '/datasets/' . Utils::cleanUpString($name);
        $count = 1;
        while (MarkdownFile::instance("$route/point_dataset.md")->exists() || MarkdownFile::instance("$route/shape_dataset.md")->exists()) {
            $route = "$base_route-$count";
            $count++;
        }
        // set the correct page type for the file
        if ($type === 'Point') $route = "$route/point_dataset.md";
        else $route = "$route/shape_dataset.md";
        return MarkdownFile::instance($route);
    }

    /**
     * Returns the best guess for dataset name property (if properties exist)
     * - Returns null if no properties exist
     * - Returns property called 'name' if one exists (ignore caps)
     * - Returns the first property beginning or ending with 'name' if no property is called 'name' (ignore caps)
     * - Returns the first property is no property is called 'name' or begins or ends with 'name' (ignore caps)
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
     * - Renames properties in properties list, preserving order (or adding to end if removed)
     * - Cannot rename properties to empty value or existing value
     * - Only the first property will be renamed if attempting to rename two properties to the same value
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