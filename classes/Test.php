<?php
namespace Grav\Plugin\LeafletTour;

require_once(__DIR__ . '/Tests/PluginTest.php');
require_once(__DIR__ . '/Tests/UtilsTest.php');
require_once(__DIR__ . '/Tests/FeatureTest.php');
require_once(__DIR__ . '/Tests/DatasetTest.php');
require_once(__DIR__ . '/Tests/TourTest.php');

/**
 * This class is for testing the site and should probably not be included in the production site. I know it's possible to set up unit testing in Grav, but it ended up being easier to throw this together than continue trying to figure it out.
 * The site still requires a certain amount of manual testing - uploading, saving, looking at the pages - but this should allow for testing the bulk of the options/functionality. It may also help pinpoint what is causing a specific issue.
 */
class Test {

    protected $testName;
    protected $showPrint;
    protected $showSuccess;
    protected $results;
    protected $methodErrors;
    protected $methodResults;
    
    function __construct(string $testName, bool $showSuccess, bool $showPrint) {
        // fix php's bad json handling
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set( 'serialize_precision', -1 );
        }
        $this->testName = $testName;
        $this->showSuccess = $showSuccess;
        $this->showPrint = $showPrint;
        $this->results = [];
    }

    /**
     * Method to be called by page.
     * @return string - raw HTML with result content.
     */
    public static function getResults(bool $showSuccess=false, bool $showPrint=true): string {
        $tests = [
            new PluginTest('Plugin Test', $showSuccess, $showPrint),
            new UtilsTest('Utils Test', $showSuccess, $showPrint),
            new FeatureTest('Feature Test', $showSuccess, $showPrint),
            new DatasetTest('Dataset Test', $showSuccess, $showPrint),
            new TourTest('Tour Test', $showSuccess, $showPrint),
        ];
        $resultText = '';
        $errCount = 0;
        $testCount = 0;
        foreach ($tests as $test) {
            $result = [];
            try {
                $test->setup();
            } catch (\Throwable $t) {
                $result = $test->getTestResults($t->getMessage());
            }
            if (empty($result)) {
                $test->runTests();
                $result = $test->getTestResults();
            }
            $resultText.=$result['text'];
            $errCount += $result['errorCount'];
            $testCount += $result['testCount'];
            // $test->teardown();
        }
        $text = '';
        if ($errCount === 0) {
            $text .= '<p>'.self::printSuccess("Success: $testCount of $testCount tests passed.").'</p>';
        } else {
            $text .= '<p>'.self::printFailure("$errCount of $testCount failed.").'</p>';
        }
        return $text.$resultText;
    }

    /**
     * Loops through all methods and runs any that being with the word test. Method is executed in a try-catch statement so that if it fails utterly the other methods can still be run (and so that the error message for the failure will be displayed nicely).
     */
    protected function runTests() {
        foreach(get_class_methods($this) as $methodName) {
            if (str_starts_with($methodName, 'test')) {
                // reset storage variables
                $this->methodResults = [];
                $this->methodErrors = 0;
                // run the method's tests
                try {
                    $this->$methodName();
                    // add the method's results to the general results
                    $this->results[$methodName] = ['resultList'=>$this->methodResults, 'errorCount'=>$this->methodErrors];
                } catch (\Throwable $t) {
                    $this->methodErrors++;
                    // add what we were able to obtain to the general results
                    $this->results[$methodName] = ['resultList'=>$this->methodResults, 'errorCount'=>$this->methodErrors, 'haltedError'=>$t->getMessage()];
                }
            }
        }
    }

    protected function getMethodResults($methodName, $methodResults): array {
        $text = '<h3>'.strtolower(substr($methodName, 4, 1)).substr($methodName, 5).'</h3>';
        // check if any errors halted the continuation of tests
        if (!empty($methodResults['haltedError'])) {
            $text .= '<p>'.self::printFailure('Method halted with message: '.$methodResults['haltedError']).'</p>';
        }
        // indicate number of errors encountered
        $errCount = $methodResults['errorCount'];
        $testCount = count($methodResults['resultList']);
        if (is_numeric($errCount) && $errCount > 0) {
            $text .= '<p>'.self::printFailure("Error(s) encountered: $errCount of $testCount failed.").'</p>';
            if (!empty($methodResults['resultList'])) {
                $text .= '<ol>';
                $successCount = 0;
                foreach ($methodResults['resultList'] as $index=>$testResult) {
                    $str = '';
                    if ($testResult['error']) { // print error message
                        $str = self::printFailure('Test failed: '.$testResult['error']);
                    } else if ($testResult['print'] && $this->showPrint) { // print provided message
                        $str = '<pre><code>'.$testResult['print'].'</code></pre>';
                    } else if ($this->showSuccess) { // print success message
                        $str = self::printSuccess('Test passed: ').' '.$testResult['success'];
                    } else if ($index === (count($methodResults['resultList'])-1) || $methodResults['resultList'][$index+1]['error'] || ($methodResults['resultList'][$index+1]['print'] && $this->showPrint)) { // print the number of successes (since we are not printing individual success messages)
                        $successCount++;
                        $str = self::printSuccess("$successCount tests passed.");
                        $successCount = 0;
                    } else { // add to the number of successes (since we are not printing individual success messages)
                        $successCount++;
                    }
                    if (!empty($str)) $text .= "<li>$str</li>";
                }
                $text .= '</ol>';
            }
        } else {
            $text .= '<p>'.self::printSuccess("Success: $testCount of $testCount tests passed.").'</p>';
        }
        return ['resultText'=>"<div>$text</div>", 'errorCount'=>$errCount, 'testCount'=>$testCount];
    }

    protected function getTestResults(string $setupFailure = null): array {
        $resultText = '';
        $errCount = 0;
        $testCount = 0;
        foreach ($this->results as $methodName=>$methodResults) {
            $result = $this->getMethodResults($methodName, $methodResults);
            $resultText .= $result['resultText'];
            $errCount += $result['errorCount'];
            $testCount += $result['testCount'];
        }
        $text = '<h2>Test Results for '.$this->testName.'</h2>';
        if ($setupFailure) {
            $text .= '<p>'.self::printFailure('Failure on setup: '.$setupFailure).'</p>';
        } else if ($errCount === 0) {
            $text .= '<p>'.self::printSuccess("Success: $testCount of $testCount tests passed.").'</p>';
        } else {
            $text .= '<p>'.self::printFailure("$errCount of $testCount failed.").'</p>'.$resultText;
        }
        return ['text'=>$text, 'errorCount'=>$errCount, 'testCount'=>$testCount];
    }

    // wrapper functions to make HTML generation easier
    protected static function printFailure(string $text): string {
        return "<span style='color: red'>$text</span>";
    }
    protected static function printSuccess(string $text): string {
        return "<span style='color: green'>$text</span>";
    }

    /**
     * Checks the value against the required parameters. Throws exception with explanation if the value does not match what is expected.
     * @return array - On standard success, this is an empty array. If there was an error, this is indicated with the 'error' key. If the test provided content to print (for manual observation), this is indicated with the 'print' key.
     */
    protected function tryTest($function, $result, $expected=null) {
        $error = '';
        $success = '';
        try {
            switch ($function) {
                case "true":
                    if ($result !== true) throw new \Exception("Expected true. Received ".var_export($result, true).'.');
                    else $success = "Value is true.";
                    break;
                case "false":
                    if ($result !== false) throw new \Exception("Expected false. Received ".var_export($result, true).'.');
                    else $success = "Value is false.";
                    break;
                case "empty":
                    if (!empty($result)) throw new \Exception("Expected empty argument. Received non-empty ".gettype($result).'.');
                    else $success = "Value is empty.";
                    break;
                case "notEmpty":
                    if (empty($result)) throw new \Exception("Received empty argument.");
                    else $success = "Value is not empty.";
                    break;
                case "equals":
                    if ($result !== $expected) throw new \Exception("Expected '$expected'. Received '$result'.");
                    else $success = "Value equals $result.";
                    break;
                case "size":
                    if (count($result) !== $expected) throw new \Exception("Expected size of $expected. Received ".count($result).'.');
                    else $success = "Value has size of $expected.";
                    break;
                case "print":
                    $print = $result;
                default:
                    break;
            }
        } catch (\Throwable $t) {
            $error = $t->getMessage();
            $this->methodErrors++;
        }
        $result = [];
        if (!empty($error)) $result['error'] = $error;
        else if (!empty($success)) $result['success'] = $success;
        if (!empty($print)) $result['print'] = $print;
        $this->methodResults[] = $result;
    }

    /**
     * Allows printing out some results for manual observation. The results will be formatted inside of an HTML <pre><code> block, so JSON and YAML content will be easy to read.
     */
    public function print($result) {
        $this->tryTest("print", $result);
    }

    /**
     * A set of assertion statements for testing. They all call tryTest, which will run a check inside of a try-catch statement.
     * The names should be self-explanatory.
     */
    public function assertTrue($result) {
        $this->tryTest("true", $result);
    }
    public function assertFalse($result) {
        $this->tryTest("false", $result);
    }
    public function assertEmpty($result) {
        $this->tryTest("empty", $result);
    }
    public function assertNotEmpty($result) {
        $this->tryTest("notEmpty", $result);
    }
    public function assertEquals($result, $expected) {
        $this->tryTest("equals", $result, $expected);
    }
    public function assertNull($result) {
        $this->tryTest("equals", $result, null);
    }
    public function assertSize($result, $expected) {
        $this->tryTest("size", $result, $expected);
    }

    /**
     * This is a complicated function so that it can handle initial creation of most of the pages. (So I don't have to keep a bunch of files around and manually upload them when I want to test)
     */
    protected function setup() {
    }
}
?>