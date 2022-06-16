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
        'replace_no_prop' => 'Warning! No properties (or coordinates) have been selected to identify and match features. No additional content (custom name, popup content, etc.) will be preserved. All features from the dataset will be removed from tours or views using them.',
        'replace_no_matches' => 'No matches were found between features from the existing dataset and features from the file upload. Additional content and feature identification will not be preserved.',
        'replace_matches' => "Matches have been found for %d features in the existing dataset. Additional content and identification will be preserved for these (and only these) features:",
        // removal
        'removal' => 'You have chosen to remove all features from the existing dataset that match the features provided in the update file.',
        'remove_matches' => "Matches have been found for %d features in the existing dataset. These features will be removed:",
        'remove_no_matches' => 'No matches were found between features in the existing dataset and features in the file upload. No features will be removed.',
        // standard
        'standard' => 'You have chosen a standard update with the following options:',
        'standard_add' => 'Features from the update file that do not match any existing dataset features will be added.',
        'standard_modify' => 'Features from the existing dataset will be modified if they have a match in the update file.',
        'standard_remove' => 'Features from the existing dataset that have no match in the update file will be removed.',
        'standard_added' => "%d new features will be added.",
        'standard_matches' => "Matches have been found for %d features in the existing dataset. These features will be modified:",
        'standard_no_matches' => 'No matches were found between features in the existing dataset and features in the file upload. No features will be modified.',
        'standard_removed' => "%d features from the existing dataset have no match in the upload file and will be removed:",
        'standard_removed_none' => 'All features from the existing dataset have matches in the upload file. No features will be removed.',
        // other
        'update_warning' => 'The update is ready. To complete the update, review the provided information, toggle the Confirm option, and save. To cancel the update, toggle Cancel instead. Warning! Once confirmed the update cannot be undone. Make sure to carefully review the expected changes and create a backup (the Git Sync plugin is strongly recommended).',
    ];

    /**
     * [$id => Dataset]
     */
    private static ?array $datasets = null;
    /**
     * [$id => Tour]
     */
    private static ?array $tours = null;
    /**
     * [$id => View]
     */
    private static ?array $views = null;

    public function __construct() {
    }
    
    public function getTour($id): ?Tour {
        return self::getTours()[$id];
    }

    // getters

    /**
     * @return [$id => Dataset]
     */
    public static function getDatasets(): array {
        if (!self::$datasets) self::setDatasets();
        return self::$datasets;
    }
    public static function getTours(): array {
        if (!self::$tours) self::setTours();
        return self::$tours;
    }
    public static function getViews(): array {
        if (!self::$views) self::setTours();
        return self::$views;
    }

    // set/reset methods

    /**
     * Build self::$datasets: Find all dataset pages, turn into dataset objects
     */
    public static function setDatasets(): void {
        $files = self::getFiles('_dataset', null, 'dataset');
        // turn into Dataset objects
        self::$datasets = [];
        foreach ($files as $id => $file) {
            if ($dataset = Dataset::fromFile($file)) self::$datasets[$id] = $dataset;
        }
    }
    public static function setTours(): void {
        $files = self::getFiles('tour');
        self::$tours = [];
        // prepare to store view files
        self::$views = [];
        $new_views = []; // Views
        foreach ($files as $id => $file) {
            if ($tour = Tour::fromFile($file)) {
                $tmp_views = [];
                self::$tours[$id] = $tour;
                // find views
                $dir = substr($file->filename(), 0, -8);
                // self::$test[] = $dir;
                $folders = glob("$dir/*");
                $modules = [];
                foreach ($folders as $folder) {
                    $test = str_replace("$dir/", '', $folder);
                    if (str_starts_with($test, '_') || str_starts_with(preg_replace('/^[0-9]+\./', '', $test), '_')) $modules[] = $folder;
                }
                foreach ($modules as $folder) {
                    if ($view = View::fromFile(MarkdownFile::instance("$folder/view.md"), $tour)) {
                        // $view->setTour($tour);
                        // view must have an id that is not the default 'tmp  id', does not already exist (either in this tour's views or in all views as a whole) and does not equal the reserved id 'tour'
                        if (($id = $view->getId()) && 
                            $id !== 'tmp  id' && 
                            $id !== 'tour' && 
                            !self::$views[$id] && 
                            !$tmp_views[$id]
                        ) $tmp_views[$id] = $view;
                        else $new_views[] = $view;
                    }
                }
                // add the views found so far
                $tour->setViews($tmp_views);
                self::$views = array_merge(self::$views, $tmp_views);
            }
        }
        // deal with new views
        foreach ($new_views as $view) {
            $tour = $view->getTour();
            $id = self::generateId($tour->getId() . '-' . ($view->getTitle() ?: 'view'), array_keys(self::$views));
            $view->setId($id);
            $view->save();
            self::$views[$id] = $view;
            // make sure the view is added to the tour
            $tour->setViews(array_merge($tour->getViews(), [$id => $view]));
        }
    }
    // accepts tour or dataset
    private static function getFiles(string $type, ?string $dir = null, ?string $id_type = null): array {
        // find all relevant files inside the pages folder (at any level)
        $files = Utils::getTemplateFiles("$type.md", [], $dir);
        // deal with ids
        $tmp_files = $new_files = [];
        foreach ($files as $file) {
            $file = MarkdownFile::instance($file);
            // file must have id that is not the same as an existing file's id and is not equal to the default 'tmp  id' - otherwise a new id will be generated
            if (($id = $file->header()['id']) && ($id !== 'tmp  id') && (!$tmp_files[$id])) $tmp_files[$id] = $file;
            else $new_files[] = $file;
        }
        if ($id_type) $type = $id_type;
        foreach ($new_files as $file) {
            $id = self::generateId($file->header()['title'] ?: $type, array_keys($tmp_files));
            $file->header(array_merge($file->header(), ['id' => $id]));
            $file->save();
            $tmp_files[$id] = $file;
        }
        return $tmp_files;
    }

    // update methods

    /**
     * Called when plugin config is saved. Handles special situations, and passes updates to other pages.
     * Could do some validation, but shouldn't be necessary.
     * @param $obj The update object, used to access old and new config values.
     */
    public static function handlePluginConfigSave($obj): void {
        // make sure all dataset files exist
        $data_files = [];
        foreach ($obj->get('data_files') ?? [] as $key => $file_data) {
            $filepath = Grav::instance()['locator']->getBase() . '/' . $file_data['path'];
            if (File::instance($filepath)->exists()) $data_files[$key] = $file_data;
        }
        $obj->set('data_files', $data_files);
        $old_config = Grav::instance()['config']->get('plugins.leaflet-tour');
        // handle dataset uploads - loop through new files, look for files that don't exist in old files list and turn any found into new datasets (prev: checkDatasetUploads)
        $old_files = $old_config['data_files'] ?? [];
        foreach($data_files as $key => $file_data) {
            if (!$old_files[$key] && ($json = self::parseDatasetUpload($file_data))) {
                $dataset = Dataset::fromJson($json);
                if ($dataset) {
                    $dataset->initialize($file_data['name'], array_keys(self::getDatasets()));
                    $dataset->save();
                    self::$datasets[$dataset->getId()] = $dataset;
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
        $id = $page->header()->get('id');
        if ($id === 'tmp  id' || !self::getDatasets()[$id]) {
            $id = self::generateId($page->header()->get('title') ?: 'view', array_keys(self::getViews()));
            $page->header()->set('id', $id);
            self::$datasets = null; // reset so that new dataset will be added next time getDatasets is called
        } else {
            // perform validation, modify page header
            $dataset = self::getDatasets()[$id];
            $update = $dataset->update($page->header()->jsonSerialize());
            $page->header($update);
            // update tours
            foreach (self::getTours() as $tour_id => $tour) {
                $tour->updateDataset($id);
            }
            // check for export toggle
            if ($page->value('export_geojson')) {
                $dataset->export();
            }
        }
    }
    public static function handleTourPageSave($page): void {
        // check if new - make sure has id
        $id = $page->header()->get('id');
        if ($id === 'tmp  id' || !self::getTours()[$id]) {
            $id = self::generateId($page->header()->get('title') ?: 'tour', array_keys(self::getTours()));
            $page->header()->set('id', $id);
            self::$tours = null;
        }
        else {
            // perform validation
            $tour = self::getTours()[$id];
            $update = $tour->update($page->header()->jsonSerialize());
            $page->header($update);
            // popups page
            $file = MarkdownFile::instance($page->path() . '/popups/popups_page.md');
            // if tour has popups and page does not exist, create page
            if (!empty($tour->getFeaturePopups()) && !$file->exists()) {
                $file->header(['visible' => 0]);
                $file->save();
            }
            // if tour does not have popups and page exists, remove page
            else if (empty($tour->getFeaturePopups()) && $file->exists()) {
                $file->delete();
            }
        }
    }
    public static function handleViewPageSave($page): void {
        // check if new - make sure has id
        $id = $page->header()->get('id');
        if ($id === 'tmp  id' || !self::getViews()[$id] || $id === 'tour') {
            $id = self::generateId($page->header()->get('title') ?: 'view', array_keys(self::getViews()));
            $page->header()->set('id', $id);
            self::$views = self::$tours = null;
        } else {
            // validate
            $view = self::getViews()[$id];
            $page->header($view->update($page->header()->jsonSerialize()));
            // if ($tour = $view->getTour()) $tour->updateConfig(); // to clear basemaps list
        }
    }

    // removal methods
    public static function handleDatasetDeletion($page): void {
        $dataset_id = $page->header()->get('id');
        // remove original uploaded file
        if ($path = $page->header()->get('upload_file_path')) {
            File::instance(Grav::instance()['locator']->getBase() . "/$path")->delete();
        }
        // update tours
        foreach (self::getTours() as $id => $tour) {
            $tour->removeDataset($dataset_id);
            $tour->save();
            $tour->updateViews();
        }
        // update self
        unset(self::$datasets[$page->header()->get('id')]);
    }
    public static function handleTourDeletion($page): void {
        unset(self::$tours[$page->header()->get('id')]);
        // popups page
        MarkdownFile::instance($page->path() . '/popups/popups_page.md')->delete();
    }
    public static function handleViewDeletion($page): void {
        $id = $page->header()->get('id');
        if ($tour = self::$tours[$page->parent()->header()->get('id')]) $tour->removeView($id);
        unset(self::$views[$id]);
    }

    // id generation

    private static function generateId(string $title, array $ids): string {
        $id = $base_id = str_replace(' ', '-', strtolower($title));
        $count = 1;
        while (in_array($id, $ids)) {
            $id = "$base_id-$count";
            $count++;
        }
        return $id;
    }

    // dataset upload

    private static function parseDatasetUpload(array $file_data): ?array {
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
                    $json = CompiledJsonFile::instance($filepath)->content();
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
    public static function buildPopupButton(string $feature_id, string $button_id, string $name, ?string $text = null): string {
        $text = trim($text) ?: $name; // TODO: Determine default text?
        // return "<button id='$button_id' aria-haspopup='true' onClick=\"openDialog('$feature_id-popup', this)\" class='btn view-popup-btn'>$text</button>";
        return "<button type='button' id='$button_id' aria-haspopup='true' data-feature='$feature_id' class='btn view-popup-btn'>$text</button>";
    }
    public static function stripParagraph(string $text): string {
        if (str_starts_with($text, '<p>')) return substr($text, 3, -4);
    }

    // dataset update
    public static function handleDatasetUpdate(array $old, array $new): array {
        // cancel update?
        if ($new['cancel']) return self::clearUpdate();
        // is dataset uploaded?
        if (empty($new['file'])) return array_merge($new, ['msg' => self::UPDATE_MSGS['start']]);
        // get/parse uploaded dataset
        $update_dataset = self::getUpdateDataset($old, $new);
        if (!$update_dataset) {
            return [
                'msg' => self::UPDATE_MSGS['invalid_file_upload'],
                'confirm' => false,
                'status' => 'corrections'
            ];
        }
        // confirm
        if ($old['status'] === 'confirm') {
            if ($update = self::confirmUpdate($old, $new, $update_dataset)) return $update;
        }
        // check for issues
        if ($issues = self::checkForIssues($new, $update_dataset)) return $issues;
        else return self::buildUpdate($new, $update_dataset);
    }
    /**
     * Removes extra files and resets update settings to defaults
     */
    private static function clearUpdate(): array {
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
    private static function getUpdateFolder(): string {
        return Grav::instance()['locator']->findResource('user-data://') . '/leaflet-tour/datasets/update';
    }
    /**
     * Checks for uploaded file and parses it if it has not yet been parsed
     */
    private static function getUpdateDataset(array $old, array $new): ?Dataset {
        $file = MarkdownFile::instance(self::getUpdateFolder() . '/parsed_upload.md');
        if (!$file->exists() || ($new['file'] !== $old['file'])) {
            try {
                if (($json = self::parseDatasetUpload(array_values($new['file'])[0])) && ($dataset = Dataset::fromJson($json))) {
                    $dataset->setFile($file);
                    $dataset->save();
                }
                else return null;
            } catch (\Throwable $t) {
                return null;
            }
        } else {
            $dataset = Dataset::fromFile($file);
        }
        return $dataset;
    }
    /**
     * If confirm is set and there are no changes or issues, completes update and returns cleared array.
     * If there are no changes but there are issues, returns previous array with additional indication of issues.
     * If confirm is not set but there are no changes, returns previous array
     * If there are changes, returns null
     */
    private static function confirmUpdate(array $old, array $new, Dataset $update_dataset): ?array {
        // Check for changes
        if (self::hasUpdateChanged($old, $new)) return null;

        // Check for issue: dataset has been removed
        $dataset = self::getDatasets()[$new['dataset']];
        if (!$dataset) return [
            'msg' => self::UPDATE_MSGS['dataset_removed'],
            'confirm' => false,
            'status' => 'corrections'
        ];
        // Check for issue: dataset has previously been updated
        if (!$dataset->isReadyForUpdate()) {
            if ($issues = self::checkForIssues($new, $update_dataset)) {
                $issues['msg'] = self::UPDATE_MSGS['dataset_modified_issues'] . "\r\n\r\n" . $issues['msg'];
                return $issues;
            } else {
                $update = self::buildUpdate($new, $update_dataset);
                $update['msg'] = self::UPDATE_MSGS['dataset_modified_no_issues'] . "\r\n\r\n" . $update['msg'];
                return $update;
            }
        }
        // Check for issue: tmp update file does not exist
        $tmp_dataset = Dataset::fromFile(MarkdownFile::instance(self::getUpdateFolder() . '/tmp.md'));
        if (!$tmp_dataset) {
            $update = self::buildUpdate($new, $update_dataset);
            $update['msg'] = self::UPDATE_MSGS['file_not_created'] . "\r\n\r\n" . $update['msg'];
            return $update;
        }
        // Check for confirmation
        if (!$new['confirm']) {
            // make sure to remove any issue messages
            $msg = $new['msg'];
            foreach (['dataset_modified_no_issues', 'file_not_created'] as $key) {
                $msg = str_replace(self::UPDATE_MSGS[$key] . "\n\n", '', $msg);
                $msg = str_replace(self::UPDATE_MSGS[$key] . "\r\n\r\n", '', $msg);
            }
            $new['msg'] = $msg;
            return $new;
        }
        // apply update
        $dataset->applyUpdate($tmp_dataset);
        $dataset->save();
        foreach (self::getTours() as $id => $tour) {
            $tour->updateDataset($new['dataset']);
        }
        return self::clearUpdate();
    }
    private static function hasUpdateChanged(array $old, array $new): bool {
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
    /**
     * Returns update array with appropriate msg if issues were found. Returns null if no issues were found.
     */
    private static function checkForIssues(array $new, Dataset $update_dataset): ?array {
        $issues = [];
        $dataset = self::getDatasets()[$new['dataset']];
        // Check for issue: No dataset selected
        if (empty($dataset)) $issues[] = self::UPDATE_MSGS['no_dataset_selected'];
        else {
            // Check for issue: Invalid feature type
            if ($dataset->getType() !== $update_dataset->getType()) $issues[] = sprintf(self::UPDATE_MSGS['invalid_feature_type'], $update_dataset->getType(), $dataset->getType());
            // Check for issue: No dataset property (only applies if this is not a replacement update)
            $prop = self::getDatasetProp($new['dataset_prop']);
            if ((empty($prop) || $prop === 'none') && $new['type'] !== 'replacement') $issues[] = self::UPDATE_MSGS['no_dataset_prop'];
            else if (!empty($prop) && !in_array($prop, ['none', 'coords'])) {
                // Check for issue: Invalid dataset property
                if (!in_array($prop, $dataset->getProperties())) $issues[] = sprintf(self::UPDATE_MSGS['invalid_dataset_prop'], $prop, $dataset->getName());
                // Check for issue: Invalid file property
                if ($new['file_prop'] && !in_array($new['file_prop'], $update_dataset->getProperties())) $issues[] = sprintf(self::UPDATE_MSGS['invalid_file_prop'], $new['file_prop']);
            }
            // Check for issue: Standard update with no settings
            if ($new['type'] === 'standard' && !$new['modify'] && !$new['add'] && !$new['remove']) $issues[] = self::UPDATE_MSGS['no_standard_settings'];
        }
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
     * removes id and '--prop--'
     */
    private static function getDatasetProp(string $prop): string {
        $prop = explode('--prop--', $prop, 2);
        if (count($prop) > 1) return $prop[1]; // property selected
        else return $prop[0]; // presumably 'none' or 'coords'
    }
    /**
     * Returns update settings with confirmation request
     */
    private static function buildUpdate(array $update, Dataset $update_dataset): array {
        $dataset = self::getDatasets()[$update['dataset']];
        $tmp_dataset = $dataset->clone();
        $prop = self::getDatasetProp($update['dataset_prop']);
        // update tmp dataset and set msg
        switch ($update['type']) {
            case 'replacement':
                $msg = self::UPDATE_MSGS['replacement'] . "\r\n\r\n";
                if ($prop && ($prop !== 'none')) {
                    $msg .= self::getMatchingMsg($prop, $update['file_prop']) . ' ' . self::UPDATE_MSGS['replace_prop'] . "\r\n\r\n";
                    $matches = $tmp_dataset->updateReplace($prop, $update['file_prop'], $update_dataset);
                    $msg .= self::printMatches($matches, 'replace_matches', 'replace_no_matches');
                }
                else {
                    $msg .= self::UPDATE_MSGS['replace_no_prop'];
                    $tmp_dataset->updateReplace('none', null, $update_dataset);
                }
                break;
            case 'removal':
                $msg = self::UPDATE_MSGS['removal'] . "\r\n\r\n" . self::getMatchingMsg($prop, $update['file_prop']) . "\r\n\r\n";
                $matches = $tmp_dataset->updateRemove($prop,  $update['file_prop'], $update_dataset);
                $msg .= self::printMatches($matches, 'remove_matches', 'remove_no_matches');
                break;
            default: // standard
                $msg = self::UPDATE_MSGS['standard'];
                if ($update['add']) $msg .= ' ' . self::UPDATE_MSGS['standard_add'];
                if ($update['modify']) $msg .= ' ' . self::UPDATE_MSGS['standard_modify'];
                if ($update['remove']) $msg .= ' ' . self::UPDATE_MSGS['standard_remove'];
                $msg .= "\r\n\r\n" . self::getMatchingMsg($prop, $update['file_prop']);
                $matches = $tmp_dataset->updateStandard($prop, $update['file_prop'], $update['add'], $update['modify'], $update['remove'], $update_dataset);
                if ($update['add']) {
                    $added = count($update_dataset->getFeatures()) - count($matches);
                    $msg .= "\r\n\r\n" . sprintf(self::UPDATE_MSGS['standard_added'], $added);
                }
                if ($update['modify']) {
                    $msg .= "\r\n\r\n" . self::printMatches($matches, 'standard_matches', 'standard_no_matches');
                }
                if ($update['remove']) {
                    $removed = [];
                    foreach ($dataset->getFeatures() as $id => $feature) {
                        if (!$matches[$id]) $removed[$id] = $feature->getName();
                    }
                    $msg .= "\r\n\r\n" . self::printMatches($removed, 'standard_removed', 'standard_removed_none');
                }
        }
        $tmp_dataset->setFile(MarkdownFile::instance(self::getUpdateFolder() . '/tmp.md'));
        $tmp_dataset->save();
        $msg = self::UPDATE_MSGS['update_warning'] . "\r\n\r\n" . $msg;
        $dataset->setReadyForUpdate(true);
        $dataset->save();
        return [
            'msg' => $msg,
            'confirm' => false,
            'status' => 'confirm',
        ];
    }
    private static function getMatchingMsg(string $dataset_prop, ?string $file_prop): string {
        if ($dataset_prop === 'coords') return self::UPDATE_MSGS['match_coords'];
        else if (!$file_prop) return sprintf(self::UPDATE_MSGS['match_props_same'], $dataset_prop);
        else return sprintf(self::UPDATE_MSGS['match_props_diff'], $dataset_prop, $file_prop);
    }
    private static function printMatches(array $matches, string $matches_msg, string $no_matches_msg): string {
        if (!empty($matches)) {
            $msg = sprintf(self::UPDATE_MSGS[$matches_msg], count($matches)) . "\r\n";
            $count = 0;
            foreach ($matches as $id => $name) {
                $msg .= "\r\n\t- $name ($id)";
                $count++;
                if (($count >= 15) && count($matches) > 15) {
                    $number = count($matches) - 15;
                    $msg .= "\r\n\t- ...and $number more";
                    break;
                }
            }
            return $msg;
        }
        else return self::UPDATE_MSGS[$no_matches_msg];
    }
}
?>