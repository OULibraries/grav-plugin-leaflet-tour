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
        return [
            'id' => $this->id,
            'title' => $this->title,
        ];
    }
    public function save(): void {
        if ($this->file) {
            $this->file->header($this->asYaml());
            $this->file->save();
        }
    }
    public function getViewData(): array {
        return [];
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

}

?>