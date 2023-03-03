<?php

namespace Grav\Plugin\Shortcodes;

use Grav\Plugin\LeafletTour\LeafletTour;
use Grav\Plugin\LeafletTour\Utils;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

class PopupButtonShortcode extends Shortcode {
    
    public function init() {
        $this->shortcode->getHandlers()->add('popup-button', function(ShortcodeInterface $sc) {
            // get the tour
            $page = $this->grav['page'];
            if (($page->template() === 'tour') && ($tour = LeafletTour::getTour(Utils::get(((array)$page->header()), 'id')))) {
                // make sure the feature is in the tour and has popup content
                $id = $sc->getParameter('id');
                $popups = $tour->getFeaturePopups();
                if (Utils::get($popups, $id) && ($name = Utils::get($popups[$id], 'name'))) {
                    // get the button
                    return LeafletTour::buildPopupButton($id, 'sc_btn_' . $this->shortcode->getId($sc), $name, $sc->getContent());
                }
            }
            return '';
        });
    }
}

?>