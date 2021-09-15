<?php
namespace Grav\Plugin\Shortcodes;

//use Grav\Common\Grav;
use Grav\Plugin\LeafletTour\Utils;
use Thunder\Shortcode\Shortcode\ProcessedShortcode;

class ListTourPopupsShortcode extends Shortcode
{
    public function init()
    {
        $this->shortcode->getRawHandlers()->add('list-tour-popups', function(ProcessedShortcode $sc) {
            $tourRoute = $sc->getParameter('route');
            $content = "";
            if (!empty($tourRoute)) {
                $popups = Utils::getAllPopups($tourRoute);
                if (!empty($popups)) {
                    foreach ($popups as $id => $popup) {
                        // header
                        $content .="\n\n<h2>".$popup['name'].'</h2>';
                        // popup content
                        $content.="\n\n".$popup['popup'];
                    }
                }
            }
            return $content;
        });
    }
}
?>