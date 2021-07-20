<?php
namespace Grav\Plugin\LeafletTour;

/** A generic test  */
class ClassTest extends Test {
    
    function __construct(string $className, string $fullClassName) {
        parent::__construct();
        $this->class = $className;
        $this->testHeader = 'Test Results for Class '.$className;
    }

    public function runTests() {
        $methods = get_class_methods($this);
        $functionResults = [];
        foreach ($methods as $methodName) {
            if (str_starts_with($methodName, 'test')) {
                $functionResults[$methodName] = $this->$methodName();
            }
        }
    }
}
?>