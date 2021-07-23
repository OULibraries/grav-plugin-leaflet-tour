<?php
namespace Grav\Plugin\Shortcodes;

// TODO: Move some of this to method somewhere I can test it properly?

//use Grav\Common\Grav;
use Grav\Plugin\LeafletTour\Dataset;
use Grav\Plugin\LeafletTour\Tour;
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
                $dataset = Dataset::getDatasets()[$dataset['file']];
                $feature = $dataset->getFeatures()[$id];
                if ($feature && Tour::hasPopup($feature, $header['features'])) return Tour::getViewPopup($id, $buttonId, $feature->getName());
            }
            return ''; // in case nothing is found
        });
    }
}
?>