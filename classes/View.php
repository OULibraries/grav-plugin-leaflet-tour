<?php
namespace Grav\Plugin\LeafletTour;

use RocketTheme\Toolbox\File\MarkdownFile;

class View {

    private static array $reserved = ['file', 'tour'];

    private ?MarkdownFile $file = null;
    private ?Tour $tour = null;

    // properties from yaml
    private ?string $id = null;
    private ?string $title = null;
    private ?array $basemaps = null;
    private ?bool $no_tour_basemaps = null;
    private array $overrides = [];
    private array $start = [];
    private array $features = [];

    private function __construct(array $options) {
        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
    }
    public static function fromFile(MarkdownFile $file): ?View {
        if ($file->exists()) {
            $options = array_diff_key((array)($file->header()), array_flip(self::$reserved));
            $options['file'] = $file;
            return new View($options);
        }
        else return null;
    }
    public static function fromArray(array $options): View {
        return new View($options);
    }

    // object methods

    /**
     * @return View An identical copy of the view
     */
    public function clone(): View {
        $options = [];
        foreach (get_object_vars($this) as $key => $value) {
            $options[$key] = $value;
        }
        return new View($options);
    }
    public function update(array $yaml): array {
        $yaml = array_diff_key($yaml, array_flip(self::$reserved));
        foreach ($yaml as $key => $value) {
            switch ($key) {
                case 'features':
                    $this->setFeatures($value);
                    break;
                case 'basemaps':
                    $this->setBasemaps($value);
                    break;
                case 'start':
                    $this->setStart($value);
                    break;
                case 'id':
                    $this->setId($value);
                    break;
                default:
                    $this->$key = $value;
                    break;
            }
        }
        return array_merge($yaml, $this->asYaml());
    }
    public function asYaml(): array {
        $yaml = get_object_vars($this);
        $yaml = array_diff_key($yaml, array_flip(self::$reserved));
        // TODO: shortcodes list
        // $yaml['shortcodes_list'] = $this->generateShortcodesList();
        return $yaml;
    }
    public function save(): void {
        if ($this->file) {
            $this->file->header($this->asYaml());
            $this->file->save();
        }
    }
    public function getViewData(): array {
        $tour = $this->getTour();
        $options = array_intersect_key($this->getOptions(), array_flip(['remove_tile_server', 'only_show_view_features']));
        $options['features'] = $this->getFeatures();
        $basemaps = $this->getBasemaps() ?? [];
        if (!$this->getOptions()['no_tour_basemaps'] && $tour) {
            foreach ($tour->getBasemaps() as $file) {
                if (!in_array($file, $basemaps)) $basemaps[] = $file;
            }
        }
        $options['basemaps'] = $basemaps;
        if ($tour && ($bounds = $tour->calculateStartingBounds($this->start))) $options['bounds'] = $bounds;
        return $options;
    }
    /**
     * Create list of view popup buttons for at the end of the view content - only features in view and tour with popups and not already included in view content
     * 
     * @return null|array - array if list popup buttons is true, array contains one HTML button entry for each feature needing one
     */
    public function getPopupButtonsList(): array {
        $buttons = [];
        if ($this->getOptions()['list_popup_buttons'] && ($file = $this->getFile()) && ($tour = $this->getTour())) {
            // $content = $file->markdown();
            $tour_popups = array_column($tour->getFeaturePopups(), 'name');
            foreach ($this->getFeatures() as $id) {
                if (($name = $tour_popups[$id])/* && !str_contains($content, "[popup-button id='$id'")*/) {
                    $buttons[] = LeafletTour::buildPopupButton($id, "$id-$this->id-popup", $name, 'TODO');
                }
            }
        }
        return $buttons;
    }

    // setters

    /**
     * @param string $id Sets $this->id if not yet set
     */
    public function setId(string $id): void {
        $this->id ??= $id;
    }
    public function setFile(MarkdownFile $file): void {
        $this->file ??= $file;
    }
    public function setTour(Tour $tour): void {
        $this->tour = $tour;
    }
    public function setFeatures(?array $features = null): void {
        $this->features = $features ?? $this->features;
        if ($tour = $this->getTour()) {
            $features = array_column($this->features, null, 'id');
            $features = array_intersect_key($features, array_flip($tour->getIncludedFeatures()));
            $this->features = array_values($features);
        }
    }
    public function setBasemaps(?array $basemaps = null): void {
        $this->basemaps = $basemaps ?? $this->basemaps ?? [];
        if ($tour = $this->getTour()) $this->basemaps = array_intersect($this->basemaps, array_column($tour->getConfig()['basemap_info'] ?? [], 'file'));
    }
    public function setStart(?array $start = null): void {
        $this->start = $start ?? $this->start;
        if (($tour = $this->getTour()) && ($location = $this->start['location'])) {
            if (!$tour->getAllFeatures()[$location]) $this->start['location'] = 'none';
        }
    }

    // getters
    
    /**
     * @return null|MarkdownFile $this->file
     */
    public function getFile(): ?MarkdownFile {
        return $this->file;
    }
    /**
     * @return null|string $this->id (should always be set)
     */
    public function getId(): ?string {
        return $this->id;
    }
    public function getTitle(): ?string {
        return $this->title;
    }
    public function getTour(): ?Tour {
        return $this->tour;
    }
    public function getBasemaps(): array { return $this->basemaps ?? []; }
    public function getFeatures(): array { return $this->features; }
    public function getOptions(): array {
        if ($tour = $this->getTour()) {
            $options = array_merge($this->overrides, $tour->getViewOptions());
            $options['no_tour_basemaps'] = $this->no_tour_basemaps;
            return $options;
        }
        return [];
    }

}

?>