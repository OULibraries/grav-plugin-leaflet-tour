<?php

namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\File;
use Grav\Common\File\CompiledJsonFile;
use RocketTheme\Toolbox\File\MarkdownFile;

class LeafletTour {
    
    const JSON_VAR_REGEX = '/^.*var(\s)+json_(\w)*(\s)+=(\s)+/';

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

    public function testing() {
        return Test::getResults();
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
        $files = self::getFiles('dataset');
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
                    if ($view = View::fromFile(MarkdownFile::instance("$folder/view.md"))) {
                        $view->setTour($tour);
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
    private static function getFiles(string $type, ?string $dir = null): array {
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
            if ($tour = $view->getTour()) $tour->updateConfig(); // to clear basemaps list
        }
    }

    // removal methods
    public static function handleDatasetDeletion($page): void {
        $dataset_id = $page->header()->get('id');
        // remove original uploaded file
        if ($path = $page->header()->get('upload_file_path')) {
            File::instance(Grav::instance()['locator']->getBase() . "/$path")->delete();
        }
        // TODO: Need to remove file from config, or will that happen automatically when file is removed?
        // Optional: move page to backup location
        // $id = $page->header()->get('id');
        // $dataset = self::getDatasets()[$id];
        // $dataset->getFile()->filename(Grav::instance()['locator']->findResource('page://') . '/deleted_datasets/' . $dataset->getName() ?? $dataset->getId() . '/default.md');
        // $dataset->getFile()->save();
        // update tours
        foreach (self::getTours() as $id => $tour) {
            $tour->removeDataset($dataset_id);
        }
        // update self
        unset(self::$datasets[$page->header()->get('id')]);
    }
    public static function handleTourDeletion($page): void {
        unset(self::$tours[$page->header()->get('id')]);
    }
    public static function handleViewDeletion($page): void {
        $id = $page->header()->get('id');
        if ($tour = self::$views[$id]->getTour()) $tour->removeView($id);
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

    /**
     * Builds an HTML string for a feature popup button
     */
    public static function buildPopupButton(string $feature_id, string $button_id, string $name, ?string $text = null): string {
        $text = trim($text) ?: $name; // TODO: Determine default text?
        return "<button id='$button_id' onClick=\"openDialog('$feature_id-popup', this)\" class='btn view-popup-btn'>$text</button>";
    }
}
?>