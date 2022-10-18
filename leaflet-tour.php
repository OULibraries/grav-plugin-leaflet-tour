<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Plugin;
// use Grav\Common\Data\Data;
// use Grav\Common\Page\Header;
use RocketTheme\Toolbox\Event\Event;
use Grav\Plugin\LeafletTour\LeafletTour;
use Grav\Plugin\LeafletTour\Utils;
// use Grav\Plugin\LeafletTour\Tour;

/**
 * @package Grav\Plugin
 */
class LeafletTourPlugin extends Plugin {

    /**
     * @return array - core list of events to listen to, note that some events must be provided in onPluginsInitialized instead
     */
    public static function getSubscribedEvents(): array {
        return [
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0]
            ],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
        ];
    }

    /**
     * Composer autoload
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Subscribe to additional events
     */
    public function onPluginsInitialized(): void {
        // Enable admin-only events
        if ($this->isAdmin()) {
            $this->enable([
                'onGetPageTemplates' => ['onGetPageTemplates', 0],
                'onGetPageBlueprints' => ['onGetPageBlueprints', 0],
                'onAdminSave' => ['onAdminSave', 0],
                'onAdminAfterDelete' => ['onAdminAfterDelete', 0],
                'onAdminPageTypes' => ['onAdminPageTypes', 0],
            ]);
            return;
        }

        // Enable other events
        $this->enable([
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0]
        ]);
    }
    
    /**
     * Add the plugin's templates folder to the twig lookup paths so that any templates there will be included.
     */
    public function onTwigTemplatePaths() {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Add a LeafletTour object as a variable that can be accessed via twig (necessary for calling certain functions from templates).
     */
    public function onTwigSiteVariables() {
        $this->grav['twig']->twig_vars['leaflet_tour'] = new LeafletTour();
    }
    
    /**
     * Add templates to admin panel template list
     */
    public function onGetPageTemplates(Event $event) {
        $types = $event->types;
        $types->register('tour');
    }
    
    /**
     * Add blueprints to admin panel page editor
     */
    public function onGetPageBlueprints(Event $event) {
        $types = $event->types;
        $types->scanBlueprints(__DIR__ . '/blueprints');
    }

    /**
     * Add new shortcode(s) (from shortcodes directory)
     */
    public function onShortcodeHandlers() {
        $this->grav['shortcode']->registerAllShortcodes(__DIR__.'/shortcodes');
    }

    /**
     * Handle page and plugin data on save. LeafletTour object will deal with this.
     */
    public function onAdminSave(Event $event) {
        $obj = $event['object'];
        // plugin config has method 'get' and should have item 'leaflet_tour'
        if (method_exists($obj, 'get') && $obj->get('leaflet_tour')) LeafletTour::handlePluginConfigSave($obj);
        // page config has method 'template'
        else if (method_exists($obj, 'template')) {
            switch ($obj->template()) {
                case 'shape_dataset':
                case 'point_dataset':
                    LeafletTour::handleDatasetPageSave($obj);
                    break;
                case 'tour':
                    LeafletTour::handleTourPageSave($obj);
                    break;
                case 'modular/view':
                    LeafletTour::handleViewPageSave($obj);
                    break;
            }
        }
    }

    /**
     * Pass to LeafletTour object to handle page data on deletion.
     */
    public function onAdminAfterDelete(Event $event) {
        $obj = $event['object'];
        if (method_exists($obj, 'template')) {
            switch ($obj->template()) {
                case 'point_dataset':
                case 'shape_dataset':
                    LeafletTour::handleDatasetDeletion($obj);
                    break;
                // case 'tour':
                //     LeafletTour::handleTourDeletion($obj);
                //     break;
                // case 'modular/view':
                //     LeafletTour::handleViewDeletion($obj);
                //     break;
            }
        }
    }

    /**
     * Hide certain page types
     */
    public function onAdminPageTypes(Event $event) {
        $types = $event['types'];
        unset($types['dataset']);
        $event['types'] = $types;
    }

    // getters for any blueprints

    public static function getDatasetsList(bool $include_none = false): array {
        return LeafletTour::getDatasetsList($include_none);
    }

    public static function getTileServerList(): array {
        return Utils::TILE_SERVER_LIST;
    }

    public static function getBasemapList(): array {
        return LeafletTour::getBasemapList();
    }

    /**
     * Returns select_optgroup options
     */
    public static function getUpdatePropertiesList(): array {
        return LeafletTour::getUpdatePropertiesList();
    }

    // getters for dataset blueprints

    /**
     * Get all properties for a given dataset. Must be called from a dataset page blueprint!
     * - Include option for 'none' (optional, useful for something like name_prop where this might be desirable)
     * @return array [$prop => $prop]
     */
    public static function getDatasetPropertyList(bool $include_none = false): array {
        if ($file = Utils::getDatasetFile()) return LeafletTour::getDatasetPropertyList($file, $include_none);
        else return [];
    }
    public static function getAutoPopupOptions(): array {
        if ($file = Utils::getDatasetFile()) return LeafletTour::getAutoPopupOptions($file);
        else return [];
    }

    public static function getFeaturePropertiesFields(): array {
        if ($file = Utils::getDatasetFile()) return LeafletTour::getFeaturePropertiesFields($file);
        else return [];
    }

    public static function getShapeFillType(string $default): string {
        if ($file = Utils::getDatasetFile()) return LeafletTour::getShapeFillType($file, $default);
        else return $default;
    }

    public static function getDatasetDefaults(string $key): string {
        if ($file = Utils::getDatasetFile()) return LeafletTour::getDatasetDefaults($file, $key);
        else return '';
    }

    // getters for tour blueprints

    public static function getTourDatasetFields(): array {
        if ($file = Utils::getTourFile()) return LeafletTour::getTourDatasetFields($file);
        else return [];
    }

    public static function getTourFeatures(bool $only_points = false): array {
        if ($file = Utils::getTourFile()) return LeafletTour::getTourFeatures($file, $only_points);
        else return [];
    }

    // getters for view blueprints

    public static function getViewFeatures(bool $only_points = false): array {
        if ($file = Utils::getTourFileFromView()) return LeafletTour::getViewFeatures($file, $only_points);
        else return [];
    }

    public static function getTourIdForView(): string {
        if ($file = Utils::getTourFileFromView()) return LeafletTour::getTourIdForView($file);
        else return '';
    }
}
