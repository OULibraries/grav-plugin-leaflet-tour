<?php
namespace Grav\Plugin\Shortcodes;

use Grav\Common\Grav;
use Grav\Plugin\LeafletTour\Datasets;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

class ViewPopupShortcode extends Shortcode
{
    public function init()
    {
        $this->shortcode->getHandlers()->add('view-popup', function(ShortcodeInterface $sc) {
            $current = $this->grav['page'];
            $value = $sc->getContent();
            if ($current->template() !== 'tour') return ''; // don't proceed if we are not in a tour
            $header = (array)$current->header();
            if (empty($header['datasets'])) return '';
            //return Grav::instance()['locator']->findResource('plugin://');

            $buttonId = 'sc_btn_'.$this->shortcode->getId($sc);
            $id = $sc->getParameter('id');
            // loop through datasets to find feature
            foreach ($header['datasets'] as $dataset) {
                $dataset = Datasets::instance()->getDatasets()[$dataset['file']];
                $feature = $dataset->features[$id];
                if ($feature) {
                    $name = $feature['customName'] ?? $feature['properties'][$dataset->nameProperty];
                    $hasPopup = !empty($feature['popup_content']);
                    // Check tour for customName and pooup content
                    $tourFeatures = $header['features'];
                    if (!empty($tourFeatures)) {
                        $tourFeatures = array_column($tourFeatures, null, 'id');
                        if ($tourFeatures[$id]) {
                            $feature = $tourFeatures[$id];
                            $name = $feature['custom_name'] ?? $name;
                            if (!empty($feature['popup_content'])) $hasPopup = true;
                            else if ($feature['remove_popup']) $hasPopup = false;
                        }
                    }
                    if (!$hasPopup) return '';
                    // return
                    return '<button id="'.$buttonId.'" data-feature="'.$id.'" class="btn view-popup-btn">View '.$name.' popup</button>';
                }
            }
            return ''; // in case nothing is found
        });
    }
}
?>