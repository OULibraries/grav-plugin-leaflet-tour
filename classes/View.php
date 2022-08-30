<?php
namespace Grav\Plugin\LeafletTour;

use RocketTheme\Toolbox\File\MarkdownFile;

class View {

    const DEFAULT_SHORTCODES = 'There is nothing here. Add some features to the view first.';

    private ?MarkdownFile $file;

    private string $id;
    private ?string $title;
    private array $basemaps, $overrides, $start, $features, $extras;
    private bool $no_tour_basemaps;

    private ?array $starting_bounds;
    private array $popups; // id => name

    private function __construct(array $options, array $valid_basemaps, array $point_ids, array $included_features, array $feature_popups, ?array $all_features) {
        // file: must be valid or null
        try { $this->file = $options['file']; }
        catch (\Throwable $t) { $this->file = null; }
        // id: must be string
        $this->id = is_string($options['id']) ? $options['id'] : '';
        // title: must be string or null
        $this->title = is_string($options['title']) ? $options['title'] : null;
        // arrays
        foreach (['basemaps', 'overrides', 'start'] as $key) {
            $this->$key = is_array($options[$key]) ? $options[$key] : [];
        }
        // no_tour_basemaps: must be bool
        $this->no_tour_basemaps = ($options['no_tour_basemaps'] === true);
        // extras
        $keys = ['file', 'id', 'title', 'basemaps', 'overrides', 'start', 'features', 'no_tour_basemaps'];
        $this->extras = array_diff_key($options, array_flip($keys));
        // basemaps: must be valid
        $this->basemaps = array_values(array_intersect($this->basemaps, $valid_basemaps));
        // features: must be valid
        if (is_array($options['features'])) $this->features = array_values(array_intersect(array_column($options['features'], 'id'), $included_features));
        else $this->features = [];
        // start: must be valid
        if (!is_string($this->start['location']) || !in_array($this->start['location'], $point_ids)) $this->start['location'] = 'none';
        // get starting bounds
        if ($all_features) $this->starting_bounds = self::calculateStartingBounds($this->start, $all_features[$this->start['location']]);
        else $this->starting_bounds = null;

        // get popups
        $this->popups = [];
        foreach ($this->features as $id) {
            if ($popup = $feature_popups[$id]) {
                $this->popups[$id] = $popup['name'];
            }
        }
    }

    public static function fromTour(MarkdownFile $file, array $valid_basemaps, array $point_ids, array $all_features, array $included_features, array $feature_popups): View {
        return new View(array_merge($file->header(), ['file' => $file]), $valid_basemaps, $point_ids, $included_features, $feature_popups, $all_features);
    }
    public static function fromArray(array $options, array $valid_basemaps, array $point_ids, array $included_features, array $feature_popups): View {
        return new View($options, $valid_basemaps, $point_ids, $included_features, $feature_popups, null);
    }

    public function getViewData(array $tour_options, array $tour_basemaps): array {
        $options = array_merge($tour_options, $this->getOverrides());
        $basemaps = $this->getBasemaps();
        if (!$this->hasNoTourBasemaps()) $basemaps = array_values(array_unique(array_merge($basemaps, $tour_basemaps)));
        return [
            'remove_tile_server' => $options['remove_tile_server'],
            'only_show_view_features' => $options['only_show_view_features'],
            'features' => $this->getFeatures(),
            'basemaps' => $basemaps,
            'bounds' => $this->getStartingBounds(),
        ];
    }
    public function getPopupButtonsList(): array {
        $content = ($file = $this->getFile()) ? $file->markdown() : '';
        $buttons = [];
        foreach ($this->getPopups() as $id => $name) {
            if (!preg_match("/\[popup-button\\s+id\\s*=\\s*\"?$id/", $content)) $buttons[$id] = $name;
        }
        return $buttons;
    }
    public function getShortcodesList(): string {
        $features = $this->getPopups();
        // if empty, return default message
        if (empty($features)) return self::DEFAULT_SHORTCODES;
        else {
            // turn into array of shortcodes
            $shortcodes = array_map(function($id, $name) {
                return "[popup-button id=\"$id\"] $name [/popup-button]";
            }, array_keys($features), array_values($features));
            // return as string
            return implode("\r\n", $shortcodes);
        }
    }
    public function toYaml(): array {
        return array_merge($this->getExtras(), [
            'id' => $this->getId(),
            'title' => $this->title,
            'basemaps' => $this->getBasemaps(),
            'overrides' => $this->getOverrides(),
            'start' => $this->start,
            'features' => array_map(function($id) { return ['id' => $id]; }, $this->getFeatures()),
            'no_tour_basemaps' => $this->hasNoTourBasemaps(),
            'shortcodes_list' => $this->getShortcodesList(),
        ]);
    }

    public function getFile(): ?MarkdownFile { return $this->file; }
    public function getId(): string { return $this->id; }
    public function getBasemaps(): array { return $this->basemaps; }
    public function getOverrides(): array { return $this->overrides; }
    public function getFeatures(): array { return $this->features; }
    public function getExtras(): array { return $this->extras; }
    public function hasNoTourBasemaps(): bool { return $this->no_tour_basemaps; }

    protected function getStartingBounds(): ?array { return $this->starting_bounds; }
    protected function getPopups(): array { return $this->popups; }

    public static function calculateStartingBounds(array $start, ?Feature $feature): ?array {
        // first priority: manually set bounds
        $bounds = Utils::getBounds($start['bounds'] ?? []);
        if (!$bounds && ($dist = $start['distance']) && $dist > 0) {
            // next priority: point location
            if (($feature) && ($feature->getType() === 'Point')) {
                $bounds = [
                    'lng' => $feature->getCoordinates()[0],
                    'lat' => $feature->getCoordinates()[1]
                ];
            }
            // otherwise try coordinates
            if (!$bounds && ($lng = $start['lng']) && ($lat = $start['lat'])) $bounds = ['lng' => $lng, 'lat' => $lat];
            // if something was valid, make sure distance is in meters
            if ($bounds) {
                switch ($start['units']) {
                    case 'kilometers':
                        $bounds['distance'] = $dist * 1000;
                        break;
                    case 'feet':
                        $bounds['distance'] = $dist / 0.3048;
                        break;
                    case 'miles':
                        $bounds['distance'] = $dist * 1609.34;
                        break;
                    default:
                        $bounds['distance'] = $dist;
                }
            }
        }
        return $bounds;
    }
}

?>