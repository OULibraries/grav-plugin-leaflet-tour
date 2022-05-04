<?php
namespace Grav\Plugin\LeafletTour;

use RocketTheme\Toolbox\File\MarkdownFile;

class View {

    /**
     * Values not stored in the yaml file or directly updated by user
     */
    private static array $reserved_keys = ['file', 'tour'];
    /**
     * Values expected for yaml file
     */
    private static array $blueprint_keys = ['id', 'title', 'basemaps', 'no_tour_basemaps', 'overrides', 'start', 'features', 'shortcodes_list'];

    /**
     * File storing the view, should be stored below a tour page
     */
    private ?MarkdownFile $file = null;
    /**
     * The tour this view exists in
     */
    private ?Tour $tour = null;

    /**
     * Unique identifier, created on first view save (should be unique for all views, not just views in a given tour)
     */
    private ?string $id = null;
    private ?string $title = null;
    /**
     * [filename, ...]
     */
    private array $basemaps = [];
    private ?bool $no_tour_basemaps = null;
    /**
     * [remove_tile_server, only_show_view_features, list_popup_buttons]
     */
    private array $overrides = [];
    /**
     * [location, distance, lng, lat, bounds]
     */
    private array $start = [];
    /**
     * In yaml [['id' => string], ...] but [$id, ...] here for convenience (using array_column)
     */
    private array $features = [];
    /**
     * Stores information for user so they know what features can actually use shortcodes and can copy and paste the shortcodes, never modified by the user
     */
    private string $shortcodes_list = ''; // it looks like it's not used but it is

    /**
     * Any values not reserved or part of blueprint
     */
    private array $extras = [];

    /**
     * Sets and validates all provided values. Note that validation will only really occur if a tour has been provided
     * 
     * @param array $options
     */
    private function __construct(array $options) {
        $this->setValues($options);
    }
    
    // Constructor Methods

    /**
     * Builds a view from an existing markdown file. Calls fromArray
     * 
     * @param MarkdownFile $file The file with the tour
     * @param Tour|null $tour
     * 
     * @return View|null New view if the file exists
     */
    public static function fromFile(MarkdownFile $file, ?Tour $tour = null): ?View {
        if ($file->exists()) {
            $view = self::fromArray((array)($file->header()));
            $view->setFile($file);
            if ($tour) $view->setTour($tour);
            return $view;
        }
        else return null;
    }
    /**
     * Builds a view from an array. Literally just a wrapper for the constructor at present.
     * 
     * @param array $options
     * 
     * @return View
     */
    public static function fromArray(array $options): View {
        return new View($options);
    }

    // Object Methods

    /**
     * Takes yaml update array from view header and validates it.
     * 
     * @param array $yaml View header info
     * 
     * @return array Updated yaml to save
     */
    public function update(array $yaml): array {
        $this->setValues($yaml);
        $this->updateShortcodes();
        return $this->toYaml();
    }
    /**
     * Called when tour or dataset is saved. Lets the view know to recheck various values.
     */
    public function updateAll(): void {
        $this->setFeatures($this->getFeatures(), false);
        $this->setBasemaps($this->getBasemaps());
        $this->updateShortcodes();
        $this->save();
    }
    /**
     * Only works if the view has a file object set. Generates yaml content and saves it to the file header
     */
    public function save(): void {
        if ($this->file) {
            $this->file->header($this->toYaml());
            $this->file->save();
        }
    }
    /**
     * Sets $this->shortcodes_list based on view features and tour/dataset features. The list will include an entry for every feature that is in the view, is in the view's tour (view must have a tour), and has a popup (auto and/or regular) in the tour
     */
    private function updateShortcodes(): void {
        $this->shortcodes_list = 'There is nothing here. Add some features to the view first.'; // What to return if nothing is found or there is not a tour
        if ($tour = $this->getTour()) {
            $features = [];
            $popups = array_column($tour->getFeaturePopups(), 'name', 'id');
            foreach ($this->getFeatures() as $id) {
                if ($name = $popups[$id]) {
                    $features[] = "[popup-button id=$id] $name [/popup-button]"; // TODO: Change text??
                }
            }
            // turn array into string
            if (!empty($features)) $this->shortcodes_list = implode("\r\n", $features);
        }
    }

    /**
     * @return View An identical copy of the view
     * 
     * Tour will be a shallow copy, as the view should still reference the same object
     */
    public function clone(): View {
        $view = new View([]);
        foreach (get_object_vars($this) as $key => $value) {
            $view->$key = $value;
        }
        return $view;
    }
    public function __toString() {
        $yaml = $this->toYaml();
        if ($tour = $this->getTour()) $yaml['tour'] = $tour->getId();
        return json_encode($yaml);
    }
    /**
     * Checks tour ids instead of objects
     */
    public function equals(View $other): bool {
        $vars1 = get_object_vars($this);
        if ($tour = $this->getTour()) $vars1['tour'] = $tour->getId();
        $vars2 = get_object_vars($other);
        if ($tour = $other->getTour()) $vars2['tour'] = $tour->getId();
        return ($vars1 == $vars2);
    }
    /**
     * @return array View yaml array that can be saved in view.md
     */
    public function toYaml(): array {
        $yaml = array_diff_key(get_object_vars($this), array_flip(self::$reserved_keys));
        // un-index features
        if ($features = $this->getFeatures()) {
            $yaml['features'] = [];
            foreach ($features as $id) {
                $yaml['features'][] = ['id' => $id];
            }
        }
        unset($yaml['extras']);
        $yaml = array_merge($this->getExtras() ?? [], $yaml);
        return $yaml;
    }

    // Calculated Getters

    /**
     * @return array [remove_tile_server, only_show_view_features, features (array of ids), basemaps (array of filenames), bounds]
     */
    public function getViewData(): array {
        $options = $this->getOptions();
        $data = [
            'remove_tile_server' => $options['remove_tile_server'],
            'only_show_view_features' => $options['only_show_view_features'],
            'features' => $this->getFeatures(),
            'basemaps' => $this->getBasemaps(),
        ];
        if ($tour = $this->getTour()) {
            if (!$options['no_tour_basemaps']) {
                $maps = array_diff($tour->getBasemaps(), $data['basemaps']); // get any not already added
                $data['basemaps'] = array_merge($maps, $data['basemaps']);
            }
            if ($bounds = $tour->calculateStartingBounds($this->start ?? [])) $data['bounds'] = $bounds;
        }
        return $data;
    }
    /**
     * Create list of view popup buttons for at the end of the view content - only features in view and tour with popups and not already included in view content
     * 
     * @return null|array - array if list popup buttons is true, array contains one HTML button entry for each feature needing one
     */
    public function getPopupButtonsList(): array {
        $buttons = [];
        if ($this->getOptions()['list_popup_buttons'] && ($file = $this->getFile()) && ($tour = $this->getTour())) {
            $content = $file->markdown();
            $tour_popups = array_column($tour->getFeaturePopups(), 'name', 'id');
            foreach ($this->getFeatures() as $id) {
                if (($name = $tour_popups[$id]) && !str_contains($content, "[popup-button id=$id") && !str_contains($content, "[popup-button id=\"$id\"")) {
                    $buttons[] = LeafletTour::buildPopupButton($id, "$id-$this->id-popup", $name);
                }
            }
        }
        return $buttons;
    }
    /**
     * @return array $this->overrides merged with tour's view options
     */
    public function getOptions(): array {
        if ($tour = $this->getTour()) {
            $options = array_merge($tour->getViewOptions(), $this->overrides ?? []);
            $options['no_tour_basemaps'] = $this->no_tour_basemaps ?? false;
            return $options;
        }
        return [];
    }

    // Getters
    
    public function getFile(): ?MarkdownFile {
        return $this->file;
    }
    public function getTour(): ?Tour {
        return $this->tour;
    }
    public function getId(): ?string {
        return $this->id;
    }
    public function getTitle(): ?string {
        return $this->title;
    }
    public function getBasemaps(): array { return $this->basemaps ?? []; }
    public function getFeatures(): array { return $this->features ?? []; }
    /**
     * @return array An array with all non-reserved and non-blueprint properties attached to the object, if any.
     */
    public function getExtras(): array {
        return $this->extras;
    }

    // Setters

    /**
     * Sets all non-reserved values
     * 
     * @param array $options
     */
    public function setValues(array $options): void {
        $this->setId($options['id']);
        $this->setTitle($options['title']);
        $this->setBasemaps($options['basemaps']);
        $this->setNoTourBasemaps($options['no_tour_basemaps']);
        $this->setOverrides($options['overrides']);
        $this->setStart($options['start']);
        $this->setFeatures($options['features'], true);
        $this->updateShortcodes();
        $this->setExtras($options);
    }
    /**
     * @param MarkdownFile $file
     */
    public function setFile(MarkdownFile $file): void {
        $this->file = $file;
    }
    /**
     * @param Tour
     */
    public function setTour(Tour $tour): void {
        $this->tour = $tour;
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
    /**
     * @param string $title Sets $this->title (empty string ignored)
     */
    public function setTitle($title): void {
        if (is_string($title) && !empty($title)) $this->title = $title;
    }
    /**
     * Checks to make sure that all basemaps are included in the plugin config (as accessed through tour)
     * 
     * @param array|null $basemaps
     */
    public function setBasemaps($basemaps): void {
        if (is_array($basemaps)) {
            $this->basemaps = $basemaps;
            if ($tour = $this->getTour()) {
                $this->basemaps = array_intersect($this->basemaps, array_column($tour->getConfig()['basemap_info'] ?? [], 'file'));
            }
        }
        else $this->basemaps = [];
    }
    /**
     * @param bool|null $value
     */
    public function setNoTourBasemaps($value): void {
        if (is_bool($value)) $this->no_tour_basemaps = $value;
        else $this->no_tour_basemaps = null;
    }
    /**
     * @param array|null $overrides
     */
    public function setOverrides($overrides): void {
        if (is_array($overrides)) $this->overrides = $overrides;
        else $this->overrides = [];
    }
    /**
     * Sets start and validates location.
     * 
     * @param array|null $start
     */
    public function setStart($start): void {
        if (is_array($start)) {
            $this->start = $start;
            if (($tour = $this->getTour()) && ($location = $this->start['location'])) {
                if (!(($feature = $tour->getAllFeatures()[$location]) && $feature->getType() === 'Point')) $this->start['location'] = 'none';
            }
        }
        else $this->start = [];
    }
    /**
     * Turns features list into a simple list of ids. If tour is set, makes sure that all features are included in the tour.
     * 
     * @param array|null $features [['id' => $id], ...]
     * @param bool $from_yaml If yes, need to use array_column on features list, otherwise no
     */
    public function setFeatures($features, bool $from_yaml): void {
        if (is_array($features)) {
            if ($from_yaml) $this->features = array_column($features, 'id');
            else $this->features = $features;
            if ($tour = $this->getTour()) {
                $this->features = array_intersect($this->features, $tour->getIncludedFeatures());
            }
        }
        else $this->features = [];
    }
    /**
     * @param array|null $extras
     */
    public function setExtras($extras) {
        if (is_array($extras)) {
            $this->extras = array_diff_key($extras, array_flip(array_merge(self::$reserved_keys, self::$blueprint_keys)));
        }
        else $this->extras = [];
    }
}

?>