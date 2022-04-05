<?php

namespace Grav\Plugin\Shortcodes;

use Grav\Plugin\LeafletTour\LeafletTour;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

class PopupButtonShortcode extends Shortcode {
    
    public function init() {
        $this->shortcode->getHandlers()->add('popup-button', function(ShortcodeInterface $sc) {
            // get the tour
            $page = $this->grav['page'];
            if (($page->template() === 'tour') && ($tour = LeafletTour::getTours()[((array)$page->header())['id']])) {
                // make sure the feature is in the tour and has popup content
                $id = $sc->getParameter('id');
                $popups = array_column($tour->getFeaturePopups(), 'name', 'id');
                if ($name = $popups[$id]) {
                    // get the button
                    return LeafletTour::buildPopupButton($id, 'sc_btn_' . $this->shortcode->getId($sc), $name, $sc->getContent());
                }
            }
            return '';
        });
    }
}

?>