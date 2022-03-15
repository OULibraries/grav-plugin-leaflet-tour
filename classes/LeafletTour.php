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

    public function __construct() {
    }

    // getters

    /**
     * @return [$id => Dataset]
     */
    public static function getDatasets(): array {
        if (!self::$datasets) self::setDatasets();
        return self::$datasets;
    }

    // set/reset methods

    /**
     * Build self::$datasets: Find all dataset pages, turn into dataset objects
     */
    public static function setDatasets(): void {
        // find all $dataset.md files inside the pages folder (at any level)
        $files = Utils::getTemplateFiles('dataset.md', []);
        // deal with ids
        // first set any with ids and gather any without
        $tmp_files = $new_files = [];
        foreach ($files as $file) {
            $file = MarkdownFile::instance($file);
            if ($id = $file->header()['id']) $tmp_files[$id] = $file;
            else $new_files[] = $file;
        }
        foreach ($new_files as $file) {
            $id = self::generateId($file->header()['title'] ?: 'dataset', array_keys($tmp_files));
            $file->header(array_merge($file->header(), ['id' => $id]));
            $file->save();
            $tmp_files[$id] = $file;
        }
        // turn into Dataset objects
        self::$datasets = [];
        foreach ($tmp_files as $id => $file) {
            if ($dataset = Dataset::fromFile($file)) self::$datasets[$id] = $dataset;
        }
    }

    // update methods

    /**
     * Called when plugin config is saved. Handles special situations, and passes updates to other pages.
     * Could do some validation, but shouldn't be necessary.
     * @param $obj The update object, used to access old and new config values.
     */
    public static function handlePluginConfigSave($obj): void {
        $old_config = (array)(Grav::instance()['config']->get('plugins.leaflet-tour'));
        // handle dataset uploads - loop through new files, look for files that don't exist in old files list and turn any found into new datasets (prev: checkDatasetUploads)
        $old_files = $old_config['data_files'] ?? [];
        foreach($obj->get('data_files') ?? [] as $key => $file_data) {
            if (!$old_files[$key] && ($json = self::parseDatasetUpload($file_data))) {
                $dataset = Dataset::fromJson($json);
                if ($dataset) {
                    $dataset->initialize($file_data['name'], array_keys(self::getDatasets()));
                    $dataset->save();
                    self::$datasets[$dataset->getId()] = $dataset;
                }
            }
        }
    }
    /**
     * Called when dataset page is saved. Performs validation and passes updates to tours and views.
     * @param PageObject $page The update object, used to access (and modify) the new values
     */
    public static function handleDatasetPageSave($page): void {
        $id = $page->header()->get('id');
        // perform validation, modify page header
        $dataset ??= self::getDatasets()[$id];
        $update = $dataset->update((array)($page->header()));
        $page->header($update);
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
}
?>