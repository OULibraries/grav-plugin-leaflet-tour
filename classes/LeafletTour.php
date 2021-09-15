<?php
namespace Grav\Plugin\LeafletTour;

use Grav\Common\Grav;
use Grav\Common\Data\Data;

// this is just the class for referencing via twig
class LeafletTour {

    protected $config;

    function __construct($config) {
        $this->config = new Data($config);
    }

    public function getTour($page) {
        return new Tour($page, $this->config);
    }
    
    public function testing() {
        return Test::getResults();
    }
    
}