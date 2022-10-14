<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\File;
use Grav\Common\File\CompiledJsonFile;
use RocketTheme\Toolbox\File\MarkdownFile;
use Grav\Common\Filesystem\Folder;

class LeafletTour {
    
    const JSON_VAR_REGEX = '/^.*var(\s)+json_(\w)*(\s)+=(\s)+/';

    // There may be a better way to store these, but it definitely doesn't make sense to hardcode them into the functions
    const UPDATE_MSGS = [
        'start' => 'Upload a file, select options, save, and reload the page to begin.',
        // initial/confirm issues
        'invalid_file_upload' => 'The uploaded file does not exist or could not be parsed.',
        'dataset_removed' => 'Issue updating: The selected dataset does not exist. Please modify settings before continuing.',
        'dataset_modified_issues' => 'Issue updating: The selected dataset has been modified and one or more issues have been found.',
        'dataset_modified_no_issues' => 'Issue updating: The selected dataset has been modified. Please double check the expected changes before confirming the update.',
        'file_not_created' => 'Issue updating: Required temporary file did not exist, so something might have gone wrong. Please double check the expected changes before confirming the update.',
        // general issues
        'issues_list' => 'The following issues have been identified. Correct them and try saving again, or cancel the update:',
        'no_dataset_selected' => 'No dataset is selected. Select an existing dataset to update.',
        'invalid_feature_type' => "The uploaded file has features of type %s, but the selected dataset has features of type %s. Feature types for both datasets must match.",
        'no_dataset_prop' => 'The property (or coordinates) to identify features from the existing dataset must be set. Select a dataset property to continue.',
        'invalid_dataset_prop' => "The property %s is not a valid property for the dataset %s. Select a valid dataset property to continue.",
        'invalid_file_prop' => "The property %s is not a valid property for the features in the uploaded file. Set a valid file property to continue.",
        'no_standard_settings' => 'At least one of the following must be selected to perform a standard update: Update Existing Features, Add New Features, Remove Missing Features.',
        // matching
        'match_coords' => 'Features will be identified by their coordinates. Features with identical coordinates in the existing dataset and the update file will be considered matching.',
        'match_props_same' => "Features from the existing dataset and the upload file will be identified by the property %s. Features with identical ids will be considered matching.",
        'match_props_diff' => "Features in the existing dataset will be identified by the property %s, while features in the upload file will be identifid by the property %s. Features with identical ids will be considered matching.",
        // replacement
        'replacement' => 'You have chosen a total dataset replacement. Features from the existing dataset will be completely replaced by features from the uploaded file.',
        'replace_prop' => 'Settings like custom name and popup content will be preserved for matching features. Existing tours or views using matching features will retain those features.',
        'replace_no_prop' => 'Warning! No settings have been provided to identify and match features. Feature content from the original dataset will not be preserved. All features from the dataset will be removed from tours or views using them.',
        'replace_no_matches' => 'No matches were found between features from the existing dataset and features from the file upload. Additional content and feature identification will not be preserved.',
        'replace_matches' => 'The following feature(s) have matches and will be preserved:',
        // removal
        'removal' => 'You have chosen to remove all features from the existing dataset that match the features provided in the update file.',
        'remove_matches' => 'The following feature(s) have matches and will be removed:',
        'remove_no_matches' => 'No matches were found between features in the existing dataset and features in the file upload. No features will be removed.',
        // standard
        'standard' => 'You have chosen a standard update with the following options:',
        'standard_add' => 'Features from the update file that do not match any existing dataset features will be added.',
        'standard_modify' => 'Features from the existing dataset will be modified if they have a match in the update file.',
        'standard_remove' => 'Features from the existing dataset that have no match in the update file will be removed.',
        'standard_added' => "%d new features will be added.",
        'standard_matches' => 'The following feature(s) have matches and will be modified:',
        'standard_no_matches' => 'No matches were found between features in the existing dataset and features in the file upload. No features will be modified.',
        'standard_removed' => "%d features from the existing dataset have no match in the upload file and will be removed:",
        'standard_removed_none' => 'All features from the existing dataset have matches in the upload file. No features will be removed.',
        // other
        'update_warning' => 'The update is ready. To complete the update, review the provided information, toggle the Confirm option, and save. To cancel the update, simply delete the uploaded file. Warning! Once confirmed the update cannot be undone. Make sure to carefully review the expected changes and create a backup (the Git Sync plugin is strongly recommended).',
    ];

    public function __construct() {}

    /**
     * Return all files in user/pages (any level of nesting) that end with '_dataset.md', indexed by id
     * 
     * @return array
     */
    public static function getDatasets() {
        return self::getFiles('_dataset', 'dataset');
    }
    /**
     * Return all files in user/pages (any level of nesting) that end with 'tour.md', indexed by id
     * 
     * @return array
     */
    public static function getTours() {
        return self::getFiles('tour', 'tour');
    }
    /**
     * Finds all markdown files ending with the key provided. If a file does not have a valid id (no id, special reserved value, repeat), then a new id is generated for the file (and added to file header).
     * 
     * @param string $key Determines which files are returned - everything in the user/pages folder ending in $key.md
     * @param string $default_id If id is invalid and the file header does not have a 'title' set, this is used to generate the id instead
     * @return array A list of all files found, indexed by id
     */
    public static function getFiles($key, $default_id) {
        $all_files = Utils::findTemplateFiles("$key.md");
        $files = $new_files = [];
        foreach ($all_files as $file) {
            $file = MarkdownFile::instance($file);
            // make sure the dataset has a valid id
            $id = Utils::getStr($file->header(), 'id', null);
            if (self::isValidId($id, array_keys($files))) $files[$id] = $file;
            else $new_files[] = $file; // wait to create new ids until all existing datasets are found to make sure a duplicate is not generated
        }
        foreach ($new_files as $file) {
            $name = Utils::getStr($file->header(), 'title') ?: $default_id;
            $id = self::generateId($file, $name, array_keys($files));
            $files[$id] = $file;
        }
        return $files;
    }
    /**
     * Creates a new id for a given file
     * 
     * @param MarkdownFile|null $file The file that needs the new id. If provided, the id will be added to the file header, and the file will be saved.
     * @param string $name The name to use to generate the id. If the name is a duplicate for an existing id, a count will be added at the end and incremented until a unique id is found
     * @param array $ids All currently existing valid ids - i.e. ids that cannot be used (determines if the generated id is a duplicate and needs to be incremented)
     * @return string The new valid id
     */
    public static function generateId($file, $name, $ids) {
        $id = Utils::cleanUpString($name);
        $count = 1;
        while (in_array($id, $ids)) {
            $id = "$name-$count";
            $count++;
        }
        if ($file) {
            $file->header(array_merge($file->header(), ['id' => $id]));
            $file->save();
        }
        return $id;
    }
    /**
     * Checks if an id is actually valid: Must be a non-empty string not equal lto 'tmp  id' or '_tour' and not in the array of exiting ids
     * 
     * @param string $id Hopefully a string, but might be something... less valid
     * @param array $ids List of existing ids - $id must not be a duplicated of any of these
     * @return bool false if id is falsey, uses restricted value (tmp  id, _tour) or is a duplicated; true otherwise
     */
    public static function isValidId($id, $ids) {
        return ($id && is_string($id) && $id !== 'tmp  id' && $id !== '_tour' && !in_array($id, $ids, true));
    }
    /**
     * If a tour file exists for the provided id, builds and returns a Tour from that file. Otherwise returns null
     * 
     * @param string $id
     * @return Tour|null
     */
    public static function getTour($id) {
        $file = Utils::get(self::getTours(), $id);
        if ($file) return self::buildTour($file);
        else return null;
    }
    /**
     * Create an array with all view modules belonging to the tour file provided, generates valid id for any views that do not have one
     * 
     * @param MarkdownFile $file The tour markdown file
     * @return array MarkdownFile objects for any view modules in the tour file's parent folder, indexed by id
     */
    public static function getTourViews($file) {
        // get views
        $views = [];
        // $dir = substr($file->filename(), 0, -8);
        $dir = dirname($file->filename());
        $id = Utils::getStr($file->header(), 'id');
        foreach (glob("$dir/*") as $item) {
            // look for view module folders - folder must start with underscore or numeric prefix plus underscore
            $no_dir = str_replace("$dir/", '', $item);
            if (str_starts_with($no_dir, '_') || str_starts_with(preg_replace('/^[0-9]+\./', '', $no_dir), '_')) {
                // now check to see if there is actually a view file here
                $view = MarkdownFile::instance("$item/view.md");
                if ($view->exists()) {
                    // we have a view, make sure the view has a valid id to use
                    $view_id = Utils::getStr($view->header(), 'id');
                    if (!self::isValidId($view_id, array_keys($views))) {
                        $name = $id . '_' . (Utils::getStr($view->header(), 'title') ?: 'view');
                        $view_id = self::generateId($view, $name, array_keys($views));
                    }
                    $views[$view_id] = $view;
                }
            }
        }
        return $views;
    }
    /**
     * Build a Tour object from the provided file - provide views, plugin config, and datasets list
     * 
     * @param MarkdownFile $file
     * @return Tour
     */
    public static function buildTour($file) {
        return Tour::fromFile($file, self::getTourViews($file), self::getConfig(), self::getDatasets());
    }

    /**
     * Getter for something from Grav (plugin config)
     * 
     * @return array
     */
    public static function getConfig(): array {
        return Grav::instance()['config']->get('plugins.leaflet-tour');
    }
    /**
     * Get basemap_info list (if it exists) from plugin config, index by 'file'
     * 
     * @return array [file => [info], ...]
     */
    public static function getBasemapInfo() {
        $basemaps = Utils::getArr(self::getConfig(), 'basemap_info');
        return array_column($basemaps, null, 'file');
    }

    // update methods

    /**
     * Called when plugin config is saved. Handles special situations, and passes updates to other pages.
     * 
     * @param $obj The update object, used to access old and new config values.
     * @return void
     */
    public static function handlePluginConfigSave($obj) {
        // make sure all dataset files exist
        $obj->set('data_files', self::validateFiles($obj->get('data_files') ?? []));
        // handle dataset uploads
        self::createDatasetPages($obj->get('data_files'), Utils::getArr(self::getConfig(), 'data_files'));
        // make sure all basemap files exist
        $obj->set('basemap_files', self::validateFiles($obj->get('basemap_files') ?? []));
        $obj->set('basemap_info', self::validateBasemapInfo($obj->get('basemap_files'), $obj->get('basemap_info' ?? [])));
        // validate tours
        self::validateTourBasemaps($obj->get('basemap_info'), self::getTours());
        // handle dataset updates
        $update = $obj->get('update') ?? [];
        $update = array_merge($update, self::handleDatasetUpdate(Utils::getArr(self::getConfig(), 'update'), $update));
        $obj->set('update', $update);
    }
    /**
     * Removes any files from input that do not actually exist
     * 
     * @param array $input An array of uploaded files in the form: key => ['path', 'name', 'size', 'type'] (from yaml file upload)
     * @return array Modified input array - only includes files that exist
     */
    public static function validateFiles($input) {
        $files = [];
        foreach ($input as $key => $file_data) {
            $filepath = Grav::instance()['locator']->getBase() . '/' . $file_data['path'];
            if (File::instance($filepath)->exists()) $files[$key] = $file_data;
        }
        return $files;
    }
    /**
     * Loop through new files, look for files that don't exist in old files list and turn any found into new datasets
     * 
     * @param array $data_files All uploaded dataset files from plugin yaml (should be json and js files only)
     * @param array $old_files The previous value for uploaded dataset files, before the plugin config was modified and saved
     * @return void
     */
    public static function createDatasetPages($data_files, $old_files) {
        $dataset_ids = array_keys(self::getDatasets());
        foreach($data_files as $key => $file_data) {
            if (!Utils::get($old_files, $key) && ($json = self::parseDatasetUpload($file_data))) {
                $dataset = Dataset::fromJson($json);
                if ($dataset) {
                    $file = $dataset->initialize($file_data['name'], $dataset_ids);
                    $dataset_ids[] = $file->header()['id']; // in case there are multiple new dataset files
                    $file->save();
                }
            }
        }
    }
    /**
     * Removes any entries where the selected file does not actually exist
     * 
     * @param array $files All basemap uploads from plugin yaml - file uploads should have name, path, type, and size, but only name matters here
     * @param array $basemap_info The current array that needs checking, each entry includes value for 'file'
     * @return array Modified $basemap_info array, any entries where value for 'file' did not exist in the provided file names is removed
     */
    public static function validateBasemapInfo($files, $basemap_info) {
        $filenames = array_column($files, 'name');
        $new_list = [];
        foreach ($basemap_info as $info) {
            if (in_array($info['file'], $filenames)) $new_list[] = $info;
        }
        return $new_list;
    }
    /**
     * Loops through all tours and their views, makes sure that all added basemaps are actually valid - removes any that are not (must exist in basemap_info list to be valid)
     * 
     * @param array $info The basemap info list from plugin yaml, each entry contains value for 'file'
     * @param array $tours All tour.md files in user/pages folder
     */
    public static function validateTourBasemaps($info, $tours) {
        $valid_basemaps = array_column($info, 'file');
        foreach (array_values($tours) as $file) {
            // all we care about are the tour/view basemaps list - don't need to worry about other info
            $basemaps = array_intersect(Utils::getArr($file->header(), 'basemaps'), $valid_basemaps);
            $basemaps = array_values($basemaps);
            $file->header(array_merge($file->header(), ['basemaps' => $basemaps]));
            $file->save();
            // validate views
            foreach (array_values(self::getTourViews($file)) as $view_file) {
                $basemaps = array_intersect(Utils::getArr($view_file->header(), 'basemaps'), $valid_basemaps);
                $basemaps = array_values($basemaps);
                $view_file->header(array_merge($view_file->header(), ['basemaps' => $basemaps]));
                $view_file->save();
            }
        }
    }
    /**
     * Called when dataset page is saved. Performs validation and passes updates to tours and views.
     * 
     * @param PageObject $page The update object, used to access (and modify) the new values
     */
    public static function handleDatasetPageSave($page) {
        // make sure dataset has a valid id
        $id = Utils::getStr($page->getOriginalData()['header'], 'id'); // use old id - id should never be modified
        $rename_properties = $page->value('rename_properties');
        $yaml = $page->header()->jsonSerialize();
        $export = $page->value('export_geojson');
        $update = self::updateDatasetPage($id, $rename_properties, $yaml, $export);
        $page->header($update);
    }
    /**
     * Generates new id for new datasets and validates using constructor. Validates existing datasets (but does not generate new id), possibly creates GeoJSON export file, validates all tours containing the updated dataset
     * 
     * @param string $id Hopefully a string, hopefully valid - will determine whether this is a new or existing dataset
     * @param array $rename_properties Hopefully an array - will be used to update properties if any values have been renamed
     * @param array $yaml The actual updated dataset yaml (which requires validation)
     * @param bool $export Hopefully a bool - if true, a GeoJSON export file will be created and saved in the dataset's parent folder
     * @return array The updated dataset yaml with any modifications needed
     */
    public static function updateDatasetPage($id, $rename_properties, $yaml, $export) {
        $datasets = self::getDatasets();
        if ($file = Utils::get($datasets, $id)) {
            // dataset exists and needs to be updated and validated
            $dataset = Dataset::fromFile($file);
            $properties = Dataset::validateUpdateProperties($rename_properties, Utils::getArr($yaml, 'properties'));
            $update = $dataset->validateUpdate($yaml, $properties);
            // check for export - will export the new content
            if ($export) {
                $export_file = CompiledJsonFile::instance(dirname($file->filename()) . "/$id.json");
                $export_file->content(Dataset::createExport($update));
                $export_file->save();
            }
            // validate tours
            self::validateTours($id, $update, $datasets, $properties);
        } else {
            // generate valid id
            $name = Utils::getStr($yaml, 'title') ?: 'dataset';
            $id = self::generateId(null, $name, array_keys($datasets));
            // validate using constructor (since there are no changes to reconcile)
            $update = Dataset::fromArray(array_merge($yaml, ['id' => $id]))->toYaml();
        }
        return $update;
    }
    /**
     * Called when tour page is saved. Passes info to updateTourPage to perform validation nad pass updates to views
     * 
     * @param PageObject $page
     * @return void
     */
    public static function handleTourPageSave($page) {
        // make sure tour has a valid id
        $id = Utils::getStr($page->getOriginalData()['header'], 'id'); // use old id - id should never be modified
        $header = $page->header()->jsonSerialize();
        $update = self::updateTourPage($id, $header, $page->path());
        $page->header($update);
    }
    /**
     * Generates new id for new tours and validates them. Validates existing tours and all their views. Also determines whether or not a tour popups page should exist.
     * 
     * @param string $id Hopefully a string, hopefully valid  - will determine whether this is a new or existing tour
     * @param array $header The actual updated tour yaml (which requires validation)
     * @param string $path Hopefully a string, the path for the tour's parent folder - necessary for modifying markdown images in feature overrides for popup content and for creating/removing tour popups page
     * @return array The updated tour yaml with any modifications needed (updated views will just be saved directly)
     */
    public static function updateTourPage($id, $header, $path) {
        $update = [];
        $tours = self::getTours();
        if ($file = Utils::get($tours, $id)) {
            // tour exists and needs to be updated and validated
            $datasets = self::getDatasets();
            $views = self::getTourViews($file);
            // validate using constructor
            $tour = Tour::fromArray($header, $views, self::getConfig(), $datasets);
            // handle popup content images
            $yaml = $tour->toYaml();
            // $features = Tour::validateFeaturePopups($yaml['features'], str_replace(Grav::instance()['locator']->findResource('page://'), '', $file->filename()));
            $features = Tour::validateFeaturePopups(Utils::getArr($yaml, 'features'), $path);
            $update = array_merge($yaml, ['features' => $features]);
            // and then validate all views, too
            foreach ($tour->getViews() as $view_id => $view) {
                // views were validated on tour creation, so update file contents to match the validated view contents
                $file = $view->getFile();
                $file->header($view->toYaml());
                $file->save();
            }
        } else {
            // generate valid id
            $name = Utils::getStr($header, 'title') ?: 'tour';
            $id = self::generateId(null, $name, array_keys($tours));
            // validate using constructor
            $tour = Tour::fromArray(array_merge($header, ['id' => $id]), [], self::getConfig(), self::getDatasets());
            $update = $tour->toYaml();
            // no need to validate views because the tour is new and should not yet have any views
        }
        // popups page (if possible)
        if ($tour) {
            $file = MarkdownFile::instance("$path/popups/popups_page.md");
            // if tour has popups and page does not exist: create page
            if (!empty($tour->getFeaturePopups()) && !$file->exists()) {
                $file->header(['visible' => 0, 'title' => $tour->getTitle() . ' Popup Content']);
                $file->save();
            }
            // if tour does not have popups and page does exist: remove page
            else if (empty($tour->getFeaturePopups()) && $file->exists()) {
                $file->delete();
            }
        }
        return array_merge($update, ['id' => $id]); // make sure the old correct id or the new valid id is the one used
    }
    /**
     * Called when view page/module is saved. Passes info to updateViewPage for validation and such
     * 
     * @param PageObject $page
     * @return void
     */
    public static function handleViewPageSave($page): void {
        $id = Utils::getStr($page->getOriginalData()['header'], 'id'); // use old id - id should never be modified
        $update = self::updateViewPage($id, $page->value('tour_id'), $page->header()->jsonSerialize());
        $page->header($update);
    }
    /**
     * Generates new id for new views. Validates views using the view's tour (if it exists).
     * 
     * @param string $id Hopefully a string - determines whether this is a new or existing view
     * @param string $tour_id Hopefully a string, hopefully vlid - used to find the the correct tour file for validating the view (view cannot be validated otherwise)
     * @param array $header The actual updated view yaml (which requires validation)
     * @return array The updated view yaml with any modifications needed
     */
    public static function updateViewPage($id, $tour_id, $header) {
        if ($tour_file = Utils::get(self::getTours(), $tour_id)) {
            $tour = self::buildTour($tour_file);
            if (!Utils::get($tour->getViews(), $id)) {
                // need to generate a valid id
                $name = $tour_id . '_' . (Utils::getStr($header, 'title') ?: 'view');
                $id = self::generateId(null, $name, array_keys($tour->getViews()));
            }
            return $tour->validateViewUpdate($header, $id);
        } else return $header;
    }
    /**
     * Loops through all tours. Validates any that use the dataset with the provided id (as well as all of their views). Handles any renamed properties (dataset overrides for auto popup properties).
     * 
     * @param string $dataset_id The id for the dataset that has been modified or deleted. Only tours that contain this dataset id will be validated.
     * @param array $update The new updated content for the dataset (to make sure that the correct values are checked). Provide an empty array if the dataset has been deleted.
     * @param array $datasets Array of all dataset files in user/pages, indexed by id
     * @param array|null $properties Provided if any properties may have been renamed. Contains entries in the form of 'old_prop_name' => 'new_prop_name'
     * @return void
     */
    public static function validateTours($dataset_id, $update, $datasets = [], $properties = null) {
        if (empty($datasets)) $datasets = self::getDatasets();
        // make sure the file has the correct content
        if ($file = Utils::get($datasets, $dataset_id)) $file->header($update); // file might not exist, esp. if this is called b/c of dataset deletion
        foreach (array_values(self::getTours()) as $file) {
            $ids = array_column(Utils::getArr($file->header(), 'datasets'), 'id'); // dataset ids from tour
            if (!in_array($dataset_id, $ids)) continue; // ignore if tour doesn't have the dataset
            // handle property renaming, if applicable
            if ($properties) {
                $overrides = Tour::renameAutoPopupProps($dataset_id, $properties, Utils::getArr($file->header(), 'dataset_overrides'));
                $file->header(array_merge($file->header(), ['dataset_overrides' => $overrides]));
            }
            // use constructor to validate tour (and view) content
            $tour = Tour::fromFile($file, self::getTourViews($file), self::getConfig(), $datasets);
            $file->header($tour->toYaml()); // only valid options will actually be set and then returned from the object
            $file->save();
            // validate views
            foreach (array_values($tour->getViews()) as $view) {
                $view->getFile()->header($view->toYaml()); // only valid options set and returned
                $view->getFile()->save();
            }
        }
    }

    // removal method(s)
    /**
     * Called when dataset page is deleted
     * 
     * @param PageObject
     * @return void
     */
    public static function handleDatasetDeletion($page) {
        self::deleteDatasetPage($page->header()->get('id'), $page->header()->get('upload_file_path'));
    }
    /**
     * Potentially deletes the original upload file the dataset was created from. Validates all tours that previously used the deleted dataset.
     * 
     * @param string $id Hopefully a string - indicates the removed dataset
     * @param string|null $path Hopefully either a string or null - if provided, should be an accurate path (starting with 'user/' to the original upload file that should now be deleted)
     * @return void
     */
    public static function deleteDatasetPage($id, $path) {
        if ($path) {
            File::instance(Grav::instance()['locator']->getBase() . "/$path")->delete();
        }
        // validate tours
        if (is_string($id)) {
            $datasets = array_diff_key(self::getDatasets(), array_flip([$id])); // all datasets except the one that is being removed (might not be necessary, but might as well make sure)
            self::validateTours($id, [], $datasets);
        }
    }

    // dataset upload

    /**
     * Take an uploaded file and parse any valid JSON content
     * 
     * @param array $file_data The data for the uploaded file from plugin yaml (name, path, size, type)
     * @return array|null Valid JSON content if it exists, otherwise null
     */
    public static function parseDatasetUpload($file_data) {
        // fix php's bad json handling
        ini_set( 'serialize_precision', -1 );
        // parse the file data based on file type
        try {
            $json = [];
            $filepath = Grav::instance()['locator']->getBase() . '/' . $file_data['path'];
            switch ($file_data['type']) {
                case 'text/javascript':
                    $file = File::instance($filepath);
                    if ($file->exists()) {
                        $json_regex = preg_replace(self::JSON_VAR_REGEX . 's', '', $file->content(), 1, $count);
                        if ($count !== 1) $json_regex = preg_replace(self::JSON_VAR_REGEX, '', $file->content(), 1, $count); // not sure why this might be necessary sometimes, but I had a file giving me trouble without it
                        $json = json_decode($json_regex, true);
                    }
                    break;
                case 'application/json':
                    if (($file = CompiledJsonFile::instance($filepath)) && $file->exists()) $json = $file->content();
                    break;
            }
            if (!empty($json)) {
                // add upload_file_path to data before returning
                $json['upload_file_path'] = $file_data['path'];
                return $json;
            }
        } catch (\Throwable $t) {
            // do nothing
        }
        return null;
    }

    // other

    /**
     * Builds an HTML string for a feature popup button
     * 
     * @param string $feature_id
     * @param string $button_id
     * @param string $name
     * @param string|null $text
     * @return string
     */
    public static function buildPopupButton($feature_id, $button_id, $name, $text = null) {
        $text = trim($text) ?: $name; // TODO: Determine default text?
        // return "<button id='$button_id' aria-haspopup='true' onClick=\"openDialog('$feature_id-popup', this)\" class='btn view-popup-btn'>$text</button>";
        return "<button type='button' id='$button_id' aria-haspopup='true' data-feature='$feature_id' class='btn view-popup-btn'>$text</button>";
    }
    /**
     * Removes starting and ending paragraph tags from the provided text
     * 
     * @param string $text The text to modify
     * @return string $text, but with starting and ending paragraph tags removed (if they existed)
     */
    public static function stripParagraph($text) {
        if (str_starts_with($text, '<p>') && str_ends_with($text, '</p>')) return substr($text, 3, -4);
        else return $text;
    }

    /**
     * Updates an existing dataset based on plugin options.
     * 
     * @param array $old_update The previous plugin options - used to check what values (if any) have changed and whether or not the user has been given a chance to review potential changes to the dataset
     * @param array $new_update The new plugin options - used to determine what should happen next
     * @return array $new_update with any needed modifications to indicate the current status of the update
     */
    public static function handleDatasetUpdate($old_update, $new_update) {
        // cancel update?
        $file_yaml = Utils::getArr($new_update, 'file');
        if (empty($file_yaml)) return array_merge($new_update, ['msg' => self::UPDATE_MSGS['start']]);

        // parse file upload (or get the previously parsed upload)
        $upload_dataset = self::getParsedUpdateDataset($file_yaml, Utils::getArr($old_update, 'file'));
        if (!$upload_dataset) {
            return ['msg' => self::UPDATE_MSGS['invalid_file_upload'], 'confirm' => false, 'status' => 'corrections'];
        }

        // if status is confirm and no significant changes have been made:
        if (Utils::getStr($old_update, 'status') === 'confirm' && !self::hasUpdateChanged($old_update, $new_update)) {
            $datasets = self::getDatasets();
            if ($update = self::checkForConfirmIssues($new_update, $upload_dataset, Utils::getStr($new_update, 'dataset', null), $datasets)) return $update;
            // check for confirmation
            if (!Utils::get($new_update, 'confirm')) {
                // no change, but make sure to remove any issue messages
                $msg = Utils::getStr($new_update, 'msg');
                foreach (['dataset_modified_no_issues', 'file_not_created'] as $key) {
                    $msg = str_replace(self::UPDATE_MSGS[$key] . "\n\n", '', $msg);
                    $msg = str_replace(self::UPDATE_MSGS[$key] . "\r\n\r\n", '', $msg);
                }
                return array_merge($new_update, ['msg' => $msg]);
            }
            // do the update
            else {
                $id = Utils::getStr($new_update, 'dataset');
                $file = $datasets[$id];
                $tmp_file = MarkdownFile::instance(self::getUpdateFolder() . '/tmp.md');
                $file->header($tmp_file->header());
                $file->save();
                self::validateTours($id, $tmp_file->header(), $datasets);
                // remove all files (by removing the folder that holds them)
                Folder::delete(self::getUpdateFolder());
                // return default settings
                return [
                    'msg' => self::UPDATE_MSGS['start'],
                    'status' => 'none',
                    'confirm' => false, 'cancel' => false,
                    'dataset' => null, 'file' => [],
                    'dataset_prop' => 'none', 'file_prop' => null,
                ];
            }
        }

        // check for issues
        $dataset_id = Utils::getStr($new_update, 'dataset');
        $datasets = self::getDatasets();
        if ($dataset_id) $dataset = Utils::get($datasets, $dataset_id);
        if ($dataset) $dataset = Dataset::fromFile($dataset);
        else $dataset = null;
        if ($update = self::checkForIssues($new_update, $upload_dataset, $dataset)) return $update;
        else return self::buildUpdate($new_update, $dataset, $upload_dataset);
        
    }
    /**
     * Returns the folder used by the blueprint - should contain all update information
     * 
     * @return string
     */
    private static function getUpdateFolder(): string {
        return Grav::instance()['locator']->findResource('user-data://') . '/leaflet-tour/datasets/update';
    }
    /**
     * removes id and '--prop--'
     * 
     * @param string $prop
     * @return string|null
     */
    private static function getDatasetProp($prop) {
        if (!$prop || !is_string($prop)) return null;
        $prop = explode('--prop--', $prop, 2);
        if (count($prop) > 1) return $prop[1]; // property selected
        else return $prop[0]; // presumably 'none' or 'coords'
    }
    
    /**
     * Returns parsed upload markdown file if it exists and uploaded file has not changed. Otherwise (re)generates the parsed upload file. Returns file, or returns null if something went wrong and file was not parsed.
     * 
     * @param array $file_yaml
     * @param array $old_file_yaml
     * @return Dataset|null
     */
    public static function getParsedUpdateDataset($file_yaml, $old_file_yaml) {
        $file = MarkdownFile::instance(self::getUpdateFolder() . '/parsed_upload.md');
        if (!$file->exists() || ($file_yaml !== $old_file_yaml)) {
            // (re)generate the dataset from the file
            try {
                $json = self::parseDatasetUpload(array_values($file_yaml)[0]);
                if ($json) $json_dataset = Dataset::fromJson($json);
                if ($json_dataset) {
                    $init_file = $json_dataset->initialize('tmp', []);
                    $file->header($init_file->header());
                    $file->save();
                    return Dataset::fromFile($file);
                }
            } catch (\Throwable $t) {}
            return null;
        }
        else return Dataset::fromFile($file);
    }
    /**
     * Checks update settings for a variety of potential issues: Dataset no selected, invalid feature type (type for selected dataset does not match type for uploaded dataset), no dataset property (only if not replacement update), invalid dataset property (i.e. not in selected dataset properties list), invalid dataset property as default file property (i.e. not in uploaded dataset properties list), invalid file property (ditto), standard update with no settings (add, remove, modify all null or false)
     * @param array $update
     * @param Dataset $upload_dataset
     * @param Dataset|null $dataset
     * @return array|null
     */
    public static function checkForIssues($update, $upload_dataset, $dataset) {
        $issues = [];
        // check for issue: no dataset selected
        if (!$dataset) $issues[] = self::UPDATE_MSGS['no_dataset_selected'];
        else {
            // check for issue: invalid feature type
            if ($dataset->getType() !== $upload_dataset->getType()) $issues[] = sprintf(self::UPDATE_MSGS['invalid_feature_type'], $upload_dataset->getType(), $dataset->getType());
            // check for issue: no dataset property (only applies if this is not a replacement update)
            $prop = self::getDatasetProp(Utils::getStr($update, 'dataset_prop', null));
            if ((!$prop || $prop === 'none') && Utils::getStr($update, 'type') !== 'replacement') $issues[] = self::UPDATE_MSGS['no_dataset_prop'];
            // check for other property issues
            else if ($prop && !in_array($prop, ['none', 'coords'])) {
                // check for issue: invalid dataset property
                if (!in_array($prop, $dataset->getProperties())) $issues[] = sprintf(self::UPDATE_MSGS['invalid_dataset_prop'], $prop, $dataset->getName());
                // check for issue: invalid dataset property used as default file property
                if (!Utils::getStr($update, 'file_prop') && !in_array($prop, $upload_dataset->getProperties())) $issues[] = sprintf(self::UPDATE_MSGS['invalid_file_prop'], $prop);
                // check for issue: invalid file property
                else if (Utils::getStr($update, 'file_prop') && !in_array(Utils::getStr($update, 'file_prop'), $upload_dataset->getProperties())) $issues[] = sprintf(self::UPDATE_MSGS['invalid_file_prop'], $update['file_prop']);
            }
        }
        // check for issue: standard update with no settings
        if (Utils::getStr($update, 'type') === 'standard' && !Utils::getType($update, 'modify', 'is_bool') && !Utils::getType($update, 'add', 'is_bool') && !Utils::getType($update, 'remove', 'is_bool')) $issues[] = self::UPDATE_MSGS['no_standard_settings'];
        if ($issues) {
            $msg = self::UPDATE_MSGS['issues_list'] . "\r\n";
            foreach ($issues as $issue) {
                $msg .= "\r\n\t- $issue";
            }
            return [
                'msg' => $msg,
                'confirm' => false,
                'status' => 'corrections',
            ];
        }
        else return null;
    }
    /**
     * Checks update settings for a variety of potential issues pertaining to confirming an update: selected dataset has since been removed or updated, temporary update file does not exist (should have already been created)
     * 
     * @param array $new_update
     * @param Dataset $upload_dataset
     * @param string|null $dataset_id
     * @param array $datasets
     * @return array|null
     */
    public static function checkForConfirmIssues($new_update, $upload_dataset, $dataset_id, $datasets) {
        // check for issue: dataset has been removed
        if (!$dataset_id || !Utils::get($datasets, $dataset_id)) return ['msg' => self::UPDATE_MSGS['dataset_removed'], 'confirm' => false, 'status' => 'corrections'];
        // implied else
        $dataset = Dataset::fromFile($datasets[$dataset_id]);
        // check for issue: dataset is not ready for update (has been updated in some way since last save)
        if (!$dataset->isReadyForUpdate()) {
            // has the update caused new issues? if so, inform the user
            if ($update = self::checkForIssues($new_update, $upload_dataset, $dataset)) {
                return array_merge($update, ['msg' => self::UPDATE_MSGS['dataset_modified_issues'] . "\r\n\r\n" . Utils::getStr($update, 'msg')]);
            }
            // if update has not created issues, still not ready to finish update, inform user
            else {
                $update = self::buildUpdate($new_update, $dataset, $upload_dataset);
                return array_merge($update, ['msg' => self::UPDATE_MSGS['dataset_modified_no_issues'] . "\r\n\r\n" . Utils::getStr($update, 'msg')]);
            }
        }
        // check for issue: tmp file does not exist
        $tmp_file = MarkdownFile::instance(self::getUpdateFolder() . '/tmp.md');
        if (!$tmp_file->exists()) {
            $update = self::buildUpdate($new_update, $dataset, $upload_dataset);
            return array_merge($update, ['msg' => self::UPDATE_MSGS['file_not_created'] . "\r\n\r\n" . Utils::getStr($update, 'msg')]);
        }
        // if we got to this point, no issues
        return null;
    }
    // will save a file to the update folder
    /**
     * Matches features, creates a Dataset object using update settings, saves the dataset file as a temporary update file, and provides a detailed message indicating update options: Type of update, if standard - also what settings (add, modify, remove), replacement settings, matches found, what will happen to various features (matches, non-matches, ...)
     * 
     * @param array $update
     * @param Dataset $dataset
     * @param Dataset $upload_dataset
     * @return array
     */
    public static function buildUpdate($update, $dataset, $upload_dataset) {
        $dataset_prop = self::getDatasetProp(Utils::getStr($update, 'dataset_prop'));
        // match features and get matching message
        $match_method_msg = self::getMatchingMsg($dataset_prop, Utils::getStr($update, 'file_prop'));
        $matches = Dataset::matchFeatures($dataset_prop ?? 'none', Utils::getStr($update, 'file_prop'), $dataset->getFeatures(), $upload_dataset->getFeatures());
        $matches_msg = self::printMatches($matches, $dataset->getFeatures());
        switch (Utils::getStr($update, 'type')) {
            case 'replacement':
                $msg = self::UPDATE_MSGS['replacement'] . "\r\n\r\n";
                if ($dataset_prop && ($dataset_prop !== 'none')) {
                    // features will be matched, include the appropriate messaging
                    $msg .= "$match_method_msg " . self::UPDATE_MSGS['replace_prop'] . ' ';
                    if ($matches_msg) $msg .= self::UPDATE_MSGS['replace_matches'] . "\r\n$matches_msg";
                    else $msg .= self::UPDATE_MSGS['replace_no_matches'];
                }
                else $msg .= self::UPDATE_MSGS['replace_no_prop'];
                $tmp_dataset = Dataset::fromUpdateReplace($matches, $dataset, $upload_dataset);
                break;
            case 'removal':
                $msg = self::UPDATE_MSGS['removal'] . "\r\n\r\n$match_method_msg ";
                if ($matches_msg) $msg .= self::UPDATE_MSGS['remove_matches'] . "\r\n$matches_msg";
                else $msg .= self::UPDATE_MSGS['remove_no_matches'];
                $tmp_dataset = Dataset::fromUpdateRemove($matches, $dataset);
                break;
            default: // standard
                $msg = self::UPDATE_MSGS['standard'];
                $add = Utils::getType($update, 'add', 'is_bool');
                $modify = Utils::getType($update, 'modify', 'is_bool');
                $remove = Utils::getType($update, 'remove', 'is_bool');
                if ($add) $msg .= ' Add.';
                if ($modify) $msg .= ' Modify.';
                if ($remove) $msg .= ' Remove.';
                // note which settings are being applied
                $msg .= "\r\n\r\n$match_method_msg ";
                if ($add) {
                    $msg .= "\r\n\r\n " . self::UPDATE_MSGS['standard_add'] . ': ' . sprintf(self::UPDATE_MSGS['standard_added'], (count($upload_dataset->getFeatures()) - count($matches)));
                }
                if ($modify) {
                    $msg .= "\r\n\r\n " . self::UPDATE_MSGS['standard_modify'] . ' ';
                    if ($matches_msg) $msg .= self::UPDATE_MSGS['standard_matches'] . "\r\n$matches_msg";
                    else $msg .= self::UPDATE_MSGS['standard_no_matches'];
                }
                if ($remove) {
                    $msg .= "\r\n\r\n " . self::UPDATE_MSGS['standard_remove'] . ' ';
                    $removed = array_diff(array_keys($dataset->getFeatures()), array_values($matches));
                    $removed_msg = self::printMatches($removed, $dataset->getFeatures());
                    if ($removed_msg) $msg .= self::UPDATE_MSGS['standard_removed'] . "\r\n$removed_msg";
                    else $msg .= self::UPDATE_MSGS['standard_removed_none'];
                }
                $tmp_dataset = Dataset::fromUpdateStandard($matches, $dataset, $upload_dataset, $add, $modify, $remove);
        }
        $file = MarkdownFile::instance(self::getUpdateFolder() . '/tmp.md');
        $file->header($tmp_dataset->toYaml());
        $file->save();
        $msg = self::UPDATE_MSGS['update_warning'] . "\r\n\r\n" . $msg;
        $dataset->getFile()->header(array_merge($dataset->getFile()->header(), ['ready_for_update' => true]));
        $dataset->getFile()->save();

        return ['msg' => $msg, 'confirm' => false, 'status' => 'confirm'];
    }
    /**
     * Determines if any significant changes have been made that would cause the tmp dataset (used to store potential changes) to require updating.
     * 
     * @param array $old The previous "update" array (plugin config yaml)
     * @param array $new The new (to be saved) "update" array (plugin config yaml)
     * @return bool
     */
    public static function hasUpdateChanged($old, $new) {
        // values that need to be checked for changes
        $keys = ['file', 'dataset', 'type', 'dataset_prop'];
        // if dataset_prop is not 'none' or 'coords' then also need to check file_prop
        if (!in_array(Utils::getStr($new, 'dataset_prop'), ['none', 'coords'])) $keys[] = 'file_prop';
        // if update is standard then also need to check standard options
        if (Utils::getStr($new, 'type') === 'standard') $keys = array_merge($keys, ['modify', 'add', 'remove']);
        // check for changes
        foreach ($keys as $key) {
            if (Utils::get($new, $key) !== Utils::get($old, $key)) return true;
        }
        return false;
    }
    /**
     * Creates a reasonably well-formatted list indicating all features listed in matches: Uses name and id, lists maximum of 15 features
     * 
     * @param array $matches
     * @param array $features
     * @return string|null
     */
    public static function printMatches($matches, $features) {
        // $msg = sprintf(self::UPDATE_MSGS[$matches_msg], count($matches)) . "\r\n";
        if (empty($matches)) return null;
        $count = 0;
        $msg = '';
        foreach (array_values($matches) as $id) {
            $msg .= "\r\n\t- " . $features[$id]->getName() . " ($id)";
            $count++;
            if (($count >= 15) && count($matches) > 15) {
                $number = count($matches) - 15;
                $msg .= "\r\n\t- ...and $number more";
                break;
            }
        }
        return $msg;
    }
    /**
     * Provides a message indicating what settings are being used to match features
     * 
     * @param string|null $dataset_prop
     * @param string|null $file_prop
     * @return string
     */
    public static function getMatchingMsg($dataset_prop, $file_prop) {
        if (!$dataset_prop || ($dataset_prop === 'none')) return '';
        else if ($dataset_prop === 'coords') return self::UPDATE_MSGS['match_coords'];
        else if (!$file_prop) return sprintf(self::UPDATE_MSGS['match_props_same'], $dataset_prop);
        else return sprintf(self::UPDATE_MSGS['match_props_diff'], $dataset_prop, $file_prop);
    }

    // blueprint functions

    // used for any blueprints
    /**
     * List of all datasets, indexed by name (title or id), possibly with option for 'none'
     * 
     * @param bool $include_none
     * @return array
     */
    public static function getDatasetsList($include_none) {
        $list = [];
        foreach (LeafletTour::getDatasets() as $id => $file) {
            $dataset = Dataset::fromLimitedArray($file->header(), ['title', 'id']);
            $name = $dataset->getName();
            $list[$id] = $name;
        }
        if ($include_none) $list = array_merge(['none' => 'None'], $list);
        return $list;
    }

    /**
     * List of all valid basemaps, indexed by name or file
     * 
     * @return array
     */
    public static function getBasemapList() {
        $list = [];
        foreach (LeafletTour::getBasemapInfo() as $file => $info) {
            $list[$file] = Utils::getStr($info, 'name') ?: $file;
        }
        return $list;
    }

    /**
     * Returns select_optgroup options - opt group for each dataset, list of properties from each dataset, indexed by $dataset_id--$property, includes 'none' and 'coords'
     * 
     * @return array
     */
    public static function getUpdatePropertiesList() {
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
     * 
     * @param MarkdownFile $file
     * @param bool $include_none
     * @return array [$prop => $prop]
     */
    public static function getDatasetPropertyList($file, $include_none) {
        $props = Dataset::fromLimitedArray($file->header(), ['properties'])->getProperties();
        $list = array_combine($props, $props);
        if ($include_none) $list = array_merge(['none' => 'None'], $list);
        return $list ?? [];
    }

    /**
     * Get all properties for a given dataset as text input fields
     * 
     * @param MarkdownFile $file
     * @return array
     */
    public static function getFeaturePropertiesFields($file) {
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

    /**
     * Return 'hidden' for line datasts, default value for others (allows selectively hiding shape options in blueprint)
     * 
     * @param MarkdownFile $file
     * @param string $default
     * @return string
     */
    public static function getShapeFillType($file, $default) {
        $type = Feature::validateFeatureType($file->header()['feature_type']);
        if (str_contains($type, 'LineString')) {
            // LineString or MultiLineString
            return 'hidden';
        }
        return $default;
    }

    /**
     * Dynamically set default colors for path fillColor, active path color, and active path fillColor
     * 
     * @param MarkdownFile $file
     * @param string $key
     * @return string
     */
    // TODO: Make sure this still matches settings
    public static function getDatasetDefaults($file, $key) {
        $header = $file->header();
        $path = Utils::getArr($header, 'path');
        switch ($key) {
            case 'path_fillColor':
            case 'active_path_color':
                // default: path color ?? default color
                return Utils::getStr($path, 'color', null) ?? Dataset::DEFAULT_PATH['color'];
            case 'active_path_fillColor':
                // default: regular fill color
                return Utils::getStr($path, 'fillColor', null) ?? self::getDatasetDefaults($file, 'path_fillColor');
        }
        return '';
    }

    // getters for tour blueprints

    /**
     * Return an array of fieldsets, one for each dataset in the tour's 'datasets' list. Dynamically determines if icon or shape options should be included. Dynamically sets defaults based on current dataset values. Dynamically determines if legend summary/symbol_alt defaults should be included (based on other tour override settings). Dynamically determines appropriate icon/shape option defaults (based on dataset and tour override settings).
     * 
     * @param MarkdownFile $file
     * @return array
     */
    public static function getTourDatasetFields($file) {
        $fields = [];
        $datasets = Utils::getArr($file->header(), 'datasets');
        $dataset_overrides = Utils::getArr($file->header(), 'dataset_overrides');
        foreach (array_column($datasets, 'id') as $id) {
            if ($dataset_file = Utils::get(LeafletTour::getDatasets(), $id)) {
                $overrides = Utils::getArr($dataset_overrides, $id);
                $dataset = Dataset::fromArray(array_diff_key($dataset_file->header(), array_flip(['features']))); // just because we don't need features
                // legend summary default: only if legend text is not set in tour
                $legend_summary_default = $dataset->getLegend('summary');
                if ($legend_summary_default && Utils::getStr((Utils::getArr($overrides, 'legend')), 'text')) $legend_summary_default = null;
                // legend symbol alt default: only set if path color/icon file is not set in tour (could also include path fillColor, border color, or anything else in that list if desired)
                $symbol_default = $dataset->getLegend('symbol_alt');
                if ($symbol_default && (Utils::getStr((Utils::getArr($overrides, 'icon')), 'file', null) || Utils::getStr((Utils::getArr($overrides, 'path')), 'color', null))) $symbol_default = null;
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
                        'default' => $dataset->getLegend('text'),
                    ],
                    "$name.legend.summary" => [
                        'type' => 'text',
                        'label' => 'Legend Summary',
                        'description' => 'Optional shorter version of the legend description.',
                        'toggleable' => true,
                        'default' => $legend_summary_default,
                    ],
                    "$name.legend.symbol_alt" => [
                        'type' => 'text',
                        'label' => 'Legend Symbol Alt Text',
                        'description' => 'A brief description of the icon/symbol/shape used for each feature.',
                        'toggleable' => true,
                        'default' => $symbol_default,
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
                    $file = Utils::getStr($dataset->getIcon(), 'file');
                    // determine appropriate defaults for icon height/width if not directly set by dataset
                    try {
                        if (!$file) $file = $overrides['icon']['file'];
                    } catch (\Throwable $t) {} // do nothing
                    if ($file) $default = Dataset::CUSTOM_MARKER_FALLBACKS;
                    else $default = Dataset::DEFAULT_MARKER_FALLBACKS;
                    $height = Utils::getType($dataset->getIcon(), 'height', 'is_int') ?? $default['height'];
                    $width = Utils::getType($dataset->getIcon(), 'width', 'is_int') ?? $default['width'];
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
                        'default' => Utils::getStr($dataset->getStrokeOptions(), 'color'),
                        'toggleable' => true,
                    ];
                    $options["$name.border.color"] = [
                        'type' => 'colorpicker',
                        'label' => 'Border Color',
                        'toggleable' => true,
                        'default' => Utils::getStr($dataset->getBorder(), 'color'), // shows default even if no stroke - can change to getBorderOptions() if only want default when border stroke is true
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

    /**
     * Return list of all features from datasets in the tour (with dataset name for convenience). Optionally return only Point features instead, with coordinates instead of dataset name.
     * 
     * @param MarkdownFile $file
     * @param bool $only_points
     * @return array
     */
    public static function getTourFeatures($file, $only_points) {
        $list = [];
        $ids = array_column(Utils::getArr($file->header(), 'datasets'), 'id');
        $all_datasets = LeafletTour::getDatasets();
        $datasets = [];
        foreach ($ids as $id) {
            if ($file = Utils::get($all_datasets, $id)) $datasets[$id] = Dataset::fromArray($file->header());
        }
        if ($only_points) return self::getPoints($datasets);
        // implied else
        foreach (array_values($datasets) as $dataset) {
            foreach ($dataset->getFeatures() as $id => $feature) {
                $list[$id] = $feature->getName() . ' ... (' . $dataset->getName() . ')';
            }
        }
        return $list;
    }

    /**
     * Return list of all features from all Point datasets with coordinates included for convenience
     * 
     * @param array $datasets
     * @return array
     */
    public static function getPoints($datasets) {
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

    /**
     * Return list of all included features with dataset name for convenience (or all Point features instead, not just included)
     * 
     * @param MarkdownFile $file
     * @param bool $only_points
     * @return array
     */
    public static function getViewFeatures($file, $only_points) {
        $list = [];
        $ids = array_column(Utils::getArr($file->header(), 'datasets'), 'id');
        $all_datasets = LeafletTour::getDatasets();
        $datasets = [];
        foreach ($ids as $id) {
            if ($dataset_file = Utils::get($all_datasets, $id)) $datasets[$id] = Dataset::fromArray($dataset_file->header());
        }
        if ($only_points) {
            return self::getPoints($datasets);
        }
        // implied else
        $tour = Tour::fromFile($file, [], [], $all_datasets);
        foreach ($tour->getIncludedFeatures() as $id => $feature) {
            $list[$id] = $feature->getName() . ' ... (' . $datasets[$feature->getDatasetId()]->getName() . ')';
        }
        return $list;
    }

    /**
     * Return the id for the view's tour
     * 
     * @param MarkdownFile $file Tour markdown file
     * @return string
     */
    public static function getTourIdForView($file) {
        $id = Utils::getStr($file->header(), 'id');
        return $id;
    }
}
?>