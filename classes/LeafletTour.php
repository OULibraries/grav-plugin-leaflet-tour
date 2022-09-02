<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\File;
use Grav\Common\File\CompiledJsonFile;
use RocketTheme\Toolbox\File\MarkdownFile;
use Grav\Common\Filesystem\Folder;

class LeafletTour {
    
    const JSON_VAR_REGEX = '/^.*var(\s)+json_(\w)*(\s)+=(\s)+/';

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

    public function __construct() {}

    public static function getDatasets(): array {
        return self::getFiles('_dataset', 'dataset');
    }
    public static function getTours(): array {
        return self::getFiles('tour', 'tour');
    }
    public static function getFiles(string $key, string $default_id) {
        $all_files = Utils::findTemplateFiles("$key.md");
        $files = $new_files = [];
        foreach ($all_files as $file) {
            $file = MarkdownFile::instance($file);
            // make sure the dataset has a valid id
            $id = $file->header()['id'];
            if (self::isValidId($id, array_keys($files))) $files[$id] = $file;
            else $new_files[] = $file; // wait to create new ids until all existing datasets are found to make sure a duplicate is not generated
        }
        foreach ($new_files as $file) {
            $name = $file->header()['title'] ?: $default_id;
            $id = self::generateId($file, $name, array_keys($files));
            $files[$id] = $file;
        }
        return $files;
    }
    public static function generateId(?MarkdownFile $file, string $name, array $ids): string {
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
    public static function isValidId($id, array $ids): bool {
        return ($id && $id !== 'tmp  id' && $id !== '_tour' && !in_array($id, $ids, true));
    }
    public static function getTour($id): ?Tour {
        $file = self::getTours()[$id];
        if ($file) return self::buildTour($file);
        else return null;
    }
    public static function getTourViews(MarkdownFile $file): array {
        // get views
        $views = [];
        // $dir = substr($file->filename(), 0, -8);
        $dir = dirname($file->filename());
        $id = $file->header()['id'];
        foreach (glob("$dir/*") as $item) {
            // look for view module folders - folder must start with underscore or numeric prefix plus underscore
            $no_dir = str_replace("$dir/", '', $item);
            if (str_starts_with($no_dir, '_') || str_starts_with(preg_replace('/^[0-9]+\./', '', $no_dir), '_')) {
                // now check to see if there is actually a view file here
                $view = MarkdownFile::instance("$item/view.md");
                if ($view->exists()) {
                    // we have a view, make sure the view has a valid id to use
                    $view_id = $view->header()['id'];
                    if (!self::isValidId($view_id, array_keys($views))) {
                        $name = $id . '_' . ($view->header()['title'] ?: 'view');
                        $view_id = self::generateId($view, $name, array_keys($views));
                    }
                    $views[$view_id] = $view;
                }
            }
        }
        return $views;
    }
    public static function buildTour(MarkdownFile $file): Tour {
        return Tour::fromFile($file, self::getTourViews($file), self::getConfig(), self::getDatasets());
    }

    public static function getConfig(): array {
        return Grav::instance()['config']->get('plugins.leaflet-tour');
    }
    public static function getBasemapInfo() {
        $basemaps = self::getConfig()['basemap_info'] ?? [];
        return array_column($basemaps, null, 'file');
    }

    // update methods

    /**
     * Called when plugin config is saved. Handles special situations, and passes updates to other pages.
     * Could do some validation, but shouldn't be necessary.
     * @param $obj The update object, used to access old and new config values.
     */
    /**
     * could rewrite this to accept an array (from the object data), then return an array that would be used to set things for the object, theoretically this would allow me to do auto tests for much of the functionality
     */
    public static function handlePluginConfigSave($obj): void {
        // make sure all dataset files exist
        $data_files = [];
        foreach ($obj->get('data_files') ?? [] as $key => $file_data) {
            $filepath = Grav::instance()['locator']->getBase() . '/' . $file_data['path'];
            if (File::instance($filepath)->exists()) $data_files[$key] = $file_data;
        }
        $obj->set('data_files', $data_files);
        // handle dataset uploads - loop through new files, look for files that don't exist in old files list and turn any found into new datasets (prev: checkDatasetUploads)
        $old_config = self::getConfig();
        $old_files = $old_config['data_files'] ?? [];
        $dataset_ids = array_keys(self::getDatasets());
        foreach($data_files as $key => $file_data) {
            if (!$old_files[$key] && ($json = self::parseDatasetUpload($file_data))) {
                $dataset = Dataset::fromJson($json);
                if ($dataset) {
                    $file = $dataset->initialize($file_data['name'], $dataset_ids);
                    $dataset_ids[] = $file->header()['id']; // in case there are multiple new dataset files
                    $file->save();
                }
            }
        }
        // make sure all basemap files exist
        $basemap_files = [];
        $filenames = [];
        // $basemap_info = [];
        foreach ($obj->get('basemap_files') ?? [] as $key => $file_data) {
            $filepath = Grav::instance()['locator']->getBase() . '/' . $file_data['path'];
            if (File::instance($filepath)->exists()) {
                $basemap_files[$key] = $file_data;
                $filenames[] = $file_data['name'];
            }
        }
        $basemap_info = [];
        foreach ($obj->get('basemap_info') ?? [] as $info) {
            if (in_array($info['file'], $filenames)) $basemap_info[] = $info;
        }
        $obj->set('basemap_files', $basemap_files);
        $obj->set('basemap_info', $basemap_info);
        // validate tours
        $valid_basemaps = array_column($obj->get('basemap_info') ?? [], 'file');
        foreach (array_values(self::getTours()) as $file) {
            // all we care about are the tour/view basemaps list - don't need to worry about other info
            $basemaps = array_intersect($file->header('basemaps') ?? [], $valid_basemaps);
            $file->header(array_merge($file->header(), ['basemaps' => $basemaps]));
            $file->save();
            // validate views
            foreach (array_values(self::getTourViews($file)) as $view_file) {
                $basemaps = array_intersect($view_file->header('basemaps') ?? [], $valid_basemaps);
                $view_file->header(array_merge($view_file->header(), ['basemaps' => $basemaps]));
                $view_file->save();
            }
        }
        // handle dataset updates
        $update = $obj->get('update') ?? [];
        $update = array_merge($update, self::handleDatasetUpdate($old_config['update'] ?? [], $update));
        $obj->set('update', $update);
    }
    /**
     * Called when dataset page is saved. Performs validation and passes updates to tours and views.
     * @param PageObject $page The update object, used to access (and modify) the new values
     */
    public static function handleDatasetPageSave($page): void {
        // make sure dataset has a valid id
        $id = $page->getOriginalData()['id']; // use old id - id should never be modified
        $datasets = self::getDatasets();
        if ($file = $datasets[$id]) {
            // dataset exists and needs to be updated and validated
            $dataset = Dataset::fromFile($file);
            // validate
            $yaml = $page->header()->jsonSerialize();
            $properties = Dataset::validateUpdateProperties($page->value('rename_properties'), $yaml['properties']);
            $update = $dataset->validateUpdate($yaml, $properties);
            // TODO: Need to also pass rename props to tour
            // check for export - will export the new content
            if ($page->value('export_geojson')) {
                $export_file = CompiledJsonFile::instance(dirname($file->filename()) . "/$id.json");
                $export_file->content(Dataset::createExport($update));
                $export_file->save();
            }
            // modify update object with valid update
            $page->header($update);
            // validate tours
            self::validateTours($id, $update, $datasets, $properties);
        } else {
            // generate valid id
            $name = $page->header()->get('title') ?: 'dataset';
            $id = self::generateId(null, $name, array_keys($datasets));
            // validate using constructor (since there are no changes to reconcile)
            $update = Dataset::fromArray(array_merge($page->header()->jsonSerialize(), ['id' => $id]))->toYaml();
            // modify update object with valid update
            $page->header($update);
        }
    }
    public static function handleTourPageSave($page): void {
        // make sure tour has a valid id
        $id = $page->getOriginalData()['id']; // use old id - id should never be modified
        $tours = self::getTours();
        if ($file = $tours[$id]) {
            // tour exists and needs to be updated and validated
            $datasets = self::getDatasets();
            $views = self::getTourViews($file);
            // validate using constructor
            $tour = Tour::fromArray($page->header()->jsonSerialize(), $views, self::getConfig(), $datasets);
            // handle popup content images
            $yaml = $tour->toYaml();
            $features = Tour::validateFeaturePopups($yaml['features'], str_replace(Grav::instance()['locator']->findResource('page://'), '', $file->filename()));
            $page->header(array_merge($yaml, ['features' => $features]));
            // and then validate all views, too
            foreach ($tour->getViews() as $id => $view) {
                // views were validated on tour creation, so update file contents to match the validated view contents
                $file = $view->getFile();
                $file->header($view->toYaml());
                $file->save();
            }
        } else {
            // generate valid id
            $name = $page->header()->get('title') ?: 'tour';
            $id = self::generateId(null, $name, array_keys($tours));
            // validate using constructor
            $tour = Tour::fromArray(array_merge($page->header()->jsonSerialize(), ['id' => $id]), [], self::getConfig(), self::getDatasets());
            $update = $tour->toYaml();
            $page->header($update);
            // no need to validate views because the tour is new and should not yet have any views
        }
        // popups page (if possible)
        if ($tour) {
            $file = MarkdownFile::instance($page->path() . '/popups/popups_page.md');
            // if tour has popups and page does not exist: create page
            if (!empty($tour->getFeaturePopups()) && !$file->exists()) {
                $file->header(['visible' => 0]);
                $file->save();
            }
            // if tour does not have popups and page does exist: remove page
            else if (empty($tour->getFeaturePopups()) && $file->exists()) {
                $file->delete();
            }
        }
    }
    // todo: need to make sure that view blueprint includes a spot to stick the tour id (hidden, generated, not saved)
    public static function handleViewPageSave($page): void {
        // try to get view's tour
        if (($tour_id = $page->value('tour_id')) && ($tour_file = self::getTours()[$tour_id])) {
            $tour = self::buildTour($tour_file);
            // make sure view has a valid id
            $id = $page->getOriginalData()['id']; // use old id - id should never be modified
            if (!$tour->getViews()[$id]) {
                // need to generate a valid id
                $name = $tour_id . '_' . ($page->header()->get('title') ?: 'view');
                $id = self::generateId(null, $name, array_keys($tour->getViews()));
            }
            // validate view - tour function will treat the content correctly depending on whether or not it recognizes the id
            $update = $tour->validateViewUpdate($page->header()->jsonSerialize(), $id);
            $page->header($update);
        }
    }
    public static function validateTours(string $dataset_id, array $update, array $datasets = [], ?array $properties = null): void {
        if (empty($datasets)) $datasets = self::getDatasets();
        // make sure the file has the correct content
        if ($file = $datasets[$dataset_id]) $file->header($update); // file might not exist, esp. if this is called b/c of dataset deletion
        foreach (array_values(self::getTours()) as $file) {
            $ids = array_column($file->header()['datasets'] ?? [], 'id'); // dataset ids from tour
            if (!in_array($dataset_id, $ids)) continue; // ignore if tour doesn't have the dataset
            // handle property renaming, if applicable
            if ($properties) {
                $overrides = Tour::renameAutoPopupProps($dataset_id, $properties, $file->header()['dataset_overrides']);
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

    // removal methods
    public static function handleDatasetDeletion($page): void {
        // essentially can treat this the same as a dataset update
        $id = $page->header()->get('id');
        // remove original uploaded file
        if ($path = $page->header()->get('upload_file_path')) {
            File::instance(Grav::instance()['locator']->getBase() . "/$path")->delete();
        }
        // validate tours
        $datasets = array_diff_key(self::getDatasets(), array_flip($id)); // all datasets except the one that is being removed (might not be necessary, but might as well make sure)
        self::validateTours($id, [], $datasets);
    }

    // dataset upload

    public static function parseDatasetUpload(array $file_data): ?array {
        // fix php's bad json handling
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set( 'serialize_precision', -1 );
        }
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
     */
    // public static function buildPopupButton(string $feature_id, string $button_id, string $name, ?string $text = null): string {
    //     $text = trim($text) ?: $name; // TODO: Determine default text?
    //     // return "<button id='$button_id' aria-haspopup='true' onClick=\"openDialog('$feature_id-popup', this)\" class='btn view-popup-btn'>$text</button>";
    //     return "<button type='button' id='$button_id' aria-haspopup='true' data-feature='$feature_id' class='btn view-popup-btn'>$text</button>";
    // }
    public static function stripParagraph(string $text): string {
        if (str_starts_with($text, '<p>') && str_ends_with($text, '</p>')) return substr($text, 3, -4);
        else return $text;
    }

    // todo: remove cancel update toggle
    public static function handleDatasetUpdate(array $old_update, array $new_update): array {
        // cancel update?
        $file_yaml = $new_update['file'] ?? [];
        if (empty($file_yaml)) return array_merge($new_update, ['msg' => self::UPDATE_MSGS['start']]);

        // parse file upload (or get the previously parsed upload)
        $upload_dataset = self::getParsedUpdateDataset($file_yaml, $old_update['file'] ?? []);
        if (!$upload_dataset) {
            return ['msg' => self::UPDATE_MSGS['invalid_file_upload'], 'confirm' => false, 'status' => 'corrections'];
        }

        // if status is confirm and no significant changes have been made:
        if ($old_update['status'] === 'confirm' && !self::hasUpdateChanged($old_update, $new_update)) {
            $datasets = self::getDatasets();
            if ($update = self::checkForConfirmIssues($new_update, $upload_dataset, $new_update['dataset'], $datasets)) return $update;
            // check for confirmation
            if (!$new_update['confirm']) {
                // no change, but make sure to remove any issue messages
                $msg = $new_update['msg'];
                foreach (['dataset_modified_no_issues', 'file_not_created'] as $key) {
                    $msg = str_replace(self::UPDATE_MSGS[$key] . "\n\n", '', $msg);
                    $msg = str_replace(self::UPDATE_MSGS[$key] . "\r\n\r\n", '', $msg);
                }
                return array_merge($new_update, ['msg' => $msg]);
            }
            // do the update
            else {
                $id = $new_update['dataset'];
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
        $dataset_id = $new_update['dataset'];
        $datasets = self::getDatasets();
        if ($dataset_id) $dataset = $datasets[$dataset_id];
        if ($dataset) $dataset = Dataset::fromFile($dataset);
        else $dataset = null;
        if ($update = self::checkForIssues($new_update, $upload_dataset, $dataset)) return $update;
        else return self::buildUpdate($new_update, $dataset, $upload_dataset);
        
    }
    private static function getUpdateFolder(): string {
        return Grav::instance()['locator']->findResource('user-data://') . '/leaflet-tour/datasets/update';
    }
    /**
     * removes id and '--prop--'
     */
    private static function getDatasetProp($prop): ?string {
        if (!$prop || !is_string($prop)) return null;
        $prop = explode('--prop--', $prop, 2);
        if (count($prop) > 1) return $prop[1]; // property selected
        else return $prop[0]; // presumably 'none' or 'coords'
    }
    
    public static function getParsedUpdateDataset(array $file_yaml, array $old_file_yaml): ?Dataset {
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
    public static function checkForIssues(array $update, Dataset $upload_dataset, ?Dataset $dataset): ?array {
        $issues = [];
        // check for issue: no dataset selected
        if (!$dataset) $issues[] = self::UPDATE_MSGS['no_dataset_selected'];
        else {
            // check for issue: invalid feature type
            if ($dataset->getType() !== $upload_dataset->getType()) $issues[] = sprintf(self::UPDATE_MSGS['invalid_feature_type'], $upload_dataset->getType(), $dataset->getType());
            // check for issue: no dataset property (only applies if this is not a replacement update)
            $prop = self::getDatasetProp($update['dataset_prop']);
            if ((!$prop || $prop === 'none') && $update['type'] !== 'replacement') $issues[] = self::UPDATE_MSGS['no_dataset_prop'];
            // check for other property issues
            else if ($prop && !in_array($prop, ['none', 'coords'])) {
                // check for issue: invalid dataset property
                if (!in_array($prop, $dataset->getProperties())) $issues[] = sprintf(self::UPDATE_MSGS['invalid_dataset_prop'], $prop, $dataset->getName());
                // check for issue: invalid dataset property used as default file property
                if (!$update['file_prop'] && !in_array($prop, $upload_dataset->getProperties())) $issues[] = sprintf(self::UPDATE_MSGS['invalid_file_prop'], $prop);
                // check for issue: invalid file property
                else if ($update['file_prop'] && !in_array($update['file_prop'], $upload_dataset->getProperties())) $issues[] = sprintf(self::UPDATE_MSGS['invalid_file_prop'], $update['file_prop']);
            }
        }
        // check for issue: standard update with no settings
        if ($update['type'] === 'standard' && !$update['modify'] && !$update['add'] && !$update['remove']) $issues[] = self::UPDATE_MSGS['no_standard_settings'];
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
    public static function checkForConfirmIssues(array $new_update, Dataset $upload_dataset, ?string $dataset_id, array $datasets): ?array {
        // check for issue: dataset has been removed
        if (!$dataset_id || !$datasets[$dataset_id]) return ['msg' => self::UPDATE_MSGS['dataset_removed'], 'confirm' => false, 'status' => 'corrections'];
        // implied else
        $dataset = Dataset::fromFile($datasets[$dataset_id]);
        // check for issue: dataset is not ready for update (has been updated in some way since last save)
        if (!$dataset->isReadyForUpdate()) {
            // has the update caused new issues? if so, inform the user
            if ($update = self::checkForIssues($new_update, $upload_dataset, $dataset)) {
                return array_merge($update, ['msg' => self::UPDATE_MSGS['dataset_modified_issues'] . "\r\n\r\n" . $update['msg']]);
            }
            // if update has not created issues, still not ready to finish update, inform user
            else {
                $update = self::buildUpdate($new_update, $dataset, $upload_dataset);
                return array_merge($update, ['msg' => self::UPDATE_MSGS['dataset_modified_no_issues'] . "\r\n\r\n" . $update['msg']]);
            }
        }
        // check for issue: tmp file does not exist
        $tmp_file = MarkdownFile::instance(self::getUpdateFolder() . '/tmp.md');
        if (!$tmp_file->exists()) {
            $update = self::buildUpdate($new_update, $dataset, $upload_dataset);
            return array_merge($update, ['msg' => self::UPDATE_MSGS['file_not_created'] . "\r\n\r\n" . $update['msg']]);
        }
        // if we got to this point, no issues
        return null;
    }
    // will save a file to the update folder
    public static function buildUpdate(array $update, Dataset $dataset, Dataset $upload_dataset): array {
        $dataset_prop = self::getDatasetProp($update['dataset_prop']);
        // match features and get matching message
        $match_method_msg = self::getMatchingMsg($dataset_prop, $update['file_prop']);
        $matches = Dataset::matchFeatures($dataset_prop ?? 'none', $update['file_prop'], $dataset->getFeatures(), $upload_dataset->getFeatures());
        $matches_msg = self::printMatches($matches, $dataset->getFeatures());
        switch ($update['type']) {
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
                if ($update['add']) $msg .= ' Add.';
                if ($update['modify']) $msg .= ' Modify.';
                if ($update['remove']) $msg .= ' Remove.';
                // note which settings are being applied
                $msg .= "\r\n\r\n$match_method_msg ";
                if ($update['add']) {
                    $msg .= "\r\n\r\n " . self::UPDATE_MSGS['standard_add'] . ': ' . sprintf(self::UPDATE_MSGS['standard_added'], (count($upload_dataset->getFeatures()) - count($matches)));
                }
                if ($update['modify']) {
                    $msg .= "\r\n\r\n " . self::UPDATE_MSGS['standard_modify'] . ' ';
                    if ($matches_msg) $msg .= self::UPDATE_MSGS['standard_matches'] . "\r\n$matches_msg";
                    else $msg .= self::UPDATE_MSGS['standard_no_matches'];
                }
                if ($update['remove']) {
                    $msg .= "\r\n\r\n " . self::UPDATE_MSGS['standard_remove'] . ' ';
                    $removed = array_diff(array_keys($dataset->getFeatures()), array_values($matches));
                    $removed_msg = self::printMatches($removed, $dataset->getFeatures());
                    if ($removed_msg) $msg .= self::UPDATE_MSGS['standard_removed'] . "\r\n$removed_msg";
                    else $msg .= self::UPDATE_MSGS['standard_removed_none'];
                }
                $tmp_dataset = Dataset::fromUpdateStandard($matches, $dataset, $upload_dataset, $update['add'], $update['modify'], $update['remove']);
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
     * @param array $old The previous "update" array (plugin config yaml)
     * @param array $new The new (to be saved) "update" array (plugin config yaml)
     * 
     * Determines if any significant changes have been made that would cause the tmp dataset (used to store potential changes) to require updating.
     */
    public static function hasUpdateChanged(array $old, array $new): bool {
        // values that need to be checked for changes
        $keys = ['file', 'dataset', 'type', 'dataset_prop'];
        // if dataset_prop is not 'none' or 'coords' then also need to check file_prop
        if (!in_array($new['dataset_prop'], ['none', 'coords'])) $keys[] = 'file_prop';
        // if update is standard then also need to check standard options
        if ($new['type'] === 'standard') $keys = array_merge($keys, ['modify', 'add', 'remove']);
        // check for changes
        foreach ($keys as $key) {
            if ($new[$key] !== $old[$key]) return true;
        }
        return false;
    }
    public static function printMatches(array $matches, array $features): ?string {
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
    public static function getMatchingMsg(?string $dataset_prop, ?string $file_prop): string {
        if (!$dataset_prop || ($dataset_prop === 'none')) return '';
        else if ($dataset_prop === 'coords') return self::UPDATE_MSGS['match_coords'];
        else if (!$file_prop) return sprintf(self::UPDATE_MSGS['match_props_same'], $dataset_prop);
        else return sprintf(self::UPDATE_MSGS['match_props_diff'], $dataset_prop, $file_prop);
    }
}
?>