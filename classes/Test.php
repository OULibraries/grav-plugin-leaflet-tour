<?php
namespace Grav\Plugin\LeafletTour;

// the most basic test functions
class Test {

    protected $testHeader = 'Test Results';
    protected $errors = 0;
    protected $results = [];
    protected $count = 0;
    
    function __construct() {
        $this->setup();
    }

    public static function getResults(bool $showSuccess=false, $showPrint = true, $test=null): string {
        if (empty($test)) $test = new Test();
        return self::getTestResults($test, $showSuccess, $showPrint);
    }

    protected static function getTestResults($test, bool $showSuccess=false, $showPrint = true): string {
        $test->runAllTests();
        $text = $test->getTotalResults();
        $text .= $test->getIndividualResults($showSuccess, $showPrint);
        return $text;
    }

    protected function getTotalResults(): string {
        if ($this->errors > 0) {
            $text = '<div>'.self::printFailure('Failure: '.$this->errors.' of '.count($this->results).' tests failed').'</div>';
        } else {
            $text = '<div>'.self::printSuccess('Success: All tests passed').'</div>';
        }
        return '<div><h2>'.$this->testHeader.'</h2>'.$text.'</div>';
    }

    protected function getIndividualResults(bool $showSuccess=false, bool $showPrint=true): string {
        $text = '';
        foreach ($this->results as $result) {
            $resultText = '<h3>Results for '.$result['name'].'</h3>';
            if ($result['error']) {
                $resultText .= self::printFailure('Failure').'<p>'.self::printFailure($result['error'].'</p>');
            } else if (!$showSuccess && (!$showPrint || empty($result['text']))) continue; // skip this one
            else {
                $resultText .= self::printSuccess('Success');
                if ($result['text']) {
                    $resultText .= '<pre><code>'.$result['text'].'</code></pre>';
                }
            }
            $text .= "<div>$resultText</div>";
        }
        return '<div>'.$text.'</div>';
    }

    protected static function printFailure(string $text): string {
        return "<span style='color: red'>$text</span>";
    }
    protected static function printSuccess(string $text): string {
        return "<span style='color: green'>$text</span>";
    }

    protected function runAllTests() {
        foreach(get_class_methods($this) as $methodName) {
            if (str_starts_with($methodName, 'test')) {
                $this->count = 0;
                $result = ['name'=>$methodName];
                try {
                    $result['text'] = $this->$methodName();
                    $result['success'] = true;
                } catch (\Throwable $t) {
                    $result['error'] = $t->getMessage();
                    $this->errors++;
                } finally {
                    $this->results[] = $result;
                }
            }
        }
    }

    protected function setup() {
        // fix php's bad json handling
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set( 'serialize_precision', -1 );
        }
        // to be extended
    }

    // test functions

    public function checkString(string $expected, string $result) {
        $this->count++;
        if ($expected !== $result) throw new \Exception("Test ".$this->count.": String match failed: Expected '$expected', received '$result'");
    }
    public function checkBool(bool $expected, bool $result) {
        $this->count++;
        if ($expected !== $result) throw new \Exception("Test ".$this->count.": Expected ".var_export($expected, true).", received ".var_export($result, true));
    }
    public function checkNum($expected, $result) {
        $this->count++;
        if ($expected !== $result) throw new \Exception("Test ".$this->count.": Int match failed: Expected '$expected', received '$result'");
    }

    public function isTrue(bool $result) {
        return $this->checkBool(true, $result);
    }
    public function isFalse(bool $result) {
        return $this->checkBool(false, $result);
    }
}
?>