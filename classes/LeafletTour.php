<?php
namespace Grav\Plugin\LeafletTour;

//require_once __DIR__ . '/Dataset.php';

use Grav\Common\Grav;
//use Grav\Common\Page\Page;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledJsonFile;
//use RocketTheme\Toolbox\File\MarkdownFile;
use Symfony\Component\Yaml\Yaml;
use Grav\Plugin\LeafletTour\Test;

// this is just the class for referencing via twig
class LeafletTour {

    public function getTour($page, $config) {
        return new Tour($page, $config);
    }
    
    public function getTestResults() {
        $text = '';
        $text .= TourTest::getResults();
        $text .= DatasetTest::getResults(false, false).UtilsTest::getResults().FeatureTest::getResults(false, false);
        return $text;
    }

    // URI Notes
    // Use Grav::instance()['uri']
    // On tour page:
    // ->paths() returns tour-1
    // ->path() -- /tour-1
    // ->route() -- /tour-1
    // ->route(true) -- /wyman-travels/tour-1
    // ->route(true, true) -- http://testing.digischolar.oucreate.com/wyman-travels/tour-1
    // ->host() -- testing.digischolar.oucreate.com
    
    /*public function getPopupBtns($viewId, $tourData) {
        $view = $tourData['views'][$viewId];
        return gettype($tourData);
        $locations = [];
        if (empty($view['features'])) return [];
        foreach ($view['features'] as $loc) {
            if ($tour_data['features'][$loc['id']]['hasPopup']) {
                $locations[] = ['id'=>$loc['id'], 'name'=>$loc['name']];
            }
        }
        return $locations;
    }*/
    
}