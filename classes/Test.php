<?php
namespace Grav\Plugin\LeafletTour;

// need to require each of the test classes that will be used
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

    protected $testName; // string, identifies a set of tests
    protected $showSuccess; // bool, true: each test will be a line indicating that specific test was successful, false: success will be groupoed together (takes up less space)
    protected $showPrint; // bool, show content that is supposed to be printed out

    protected $methodErrors; // number for keeping track of errors as we run through the tests
    protected $methodResults; // array for keeping track of results as we run through the tests, contains one or two of the following: ['error'=>string, 'success'=>string, 'print'=>string]
    protected $printContent; // array for keeping track of things to print [['name'=>string, 'content'=>string]]
    
    /**
     * @param string $testName - a name identifying this set of tests (presumably indicating the class that is being tested)
     * @param bool $showSuccess - indicate specifics for each successful test, or group them together to save space
     * @param bool $showPrint - show content that is supposed to be printed out
     */
    function __construct(string $testName, bool $showSuccess, bool $showPrint) {
        // fix php's bad json handling
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set( 'serialize_precision', -1 );
        }
        $this->testName = $testName;
        $this->showSuccess = $showSuccess;
        $this->showPrint = $showPrint;
    }

    /**
     * Method to be called by page. Currently, this method is modified manually to indicate what test files should be run.
     * 
     * @param bool $showSuccess - indicate specifics for each successful test, or group them together to save space
     * @param bool $showPrint - theoretically determines if certain things can be printed, needs more work
     * @return string - raw HTML with result content.
     */
    public static function getResults(bool $showSuccess=false, bool $showPrint=true): string {
        // manually indicate the test classes/files to be run
        $testClasses = [
            new PluginTest('Plugin Test', $showSuccess, $showPrint),
            new UtilsTest('Utils Test', $showSuccess, $showPrint),
            new FeatureTest('Feature Test', $showSuccess, $showPrint),
            new DatasetTest('Dataset Test', $showSuccess, $showPrint),
            new TourTest('Tour Test', $showSuccess, $showPrint),
        ];
        // set up variables
        $resultText = ''; // store raw HTML to be provided at completion
        $errCount = 0; // store number of errors encountered across all test classes
        $testCount = 0; // store number of tests run across all test classes
        // run tests from each of the test classes
        foreach ($testClasses as $testClass) {
            $classErrCount = 0; // store number of errors encountered in this test class (across all methods)
            $classTestCount = 0; // store number of tests run in this test class (across all methods)
            $resultText .= $testClass->runTests($classTestCount, $classErrCount);
            $testCount += $classTestCount;
            $errCount += $classErrCount;
        }
        // provide overview of success/failure for all tests
        if ($errCount === 0) $overviewText = '<p>'.self::printSuccess("Success: $testCount of $testCount tests passed.").'</p>';
        else $overviewText = '<p>'.self::printFailure("$errCount of $testCount failed.").'</p>';
        return $overviewText.$resultText;
    }

    /**
     * Runs through tests for a particular test class. Also calls setup and teardown methods.
     * Tests are defined as any method beginning with the word 'test'.
     * 
     * @param int &$classTestCount - variable to store number of tests run for this class
     * @param int &$classErrCount - variable to store number of errors encountered for this class's tests
     * @return string $resultText - raw HTML describing the result
     */
    protected function runTests(int &$classTestCount, int &$classErrCount): string {
        $heading = '<h2>Test Results for '.$this->testName.'</h2>';
        // make sure any errors on setup are caught so they can be printed out
        try { $this->setup(); }
        catch (\Throwable $t) {
            return $heading.'<p>'.self::printFailure('Failure on setup: '.$t->getMessage()).'</p>';
        }
        // run the actual tests
        $resultText = '';
        foreach(get_class_methods($this) as $methodName) {
            if (str_starts_with($methodName, 'test')) {
                // prepare/reset storage variables - needed so that the actual tests can modify them
                $this->methodResults = [];
                $this->methodErrors = 0;
                // Run each test method inside of a try-catch statement so that if it fails any other methods can still be run (and so that the error message for the failure will be properly displayed). Note that test errors will already be handled in the test itself - this is just insurance for any other code in the test methods.
                try {
                    // run the test
                    $this->$methodName();
                    // add tests and errors from this method to the general count for the test class
                    $classTestCount += count($this->methodResults);
                    $classErrCount += $this->methodErrors;
                } catch (\Throwable $t) {
                    $this->methodErrors++;
                    $haltedErrMsg = $t->getMessage();
                }
                // add result text for the method to the general result text
                $resultText .= $this->getMethodResults($methodName, $this->methodResults, $this->methodErrors, $haltedErrMsg ?? '');
            }
        }
        // make sure any errors on teardown are caught so they can be printed out
        try { $this->teardown(); }
        catch (\Throwable $t) {
            $classErrCount ++;
            $teardownErrMsg = $t->getMessage();
        }
        // print results
        if ($classErrCount === 0) {
            $overviewText = '<p>'.self::printSuccess("Success: $classTestCount of $classTestCount tests passed.").'</p>';
        } else {
            $overViewText = '<p>'.self::printFailure("$classErrCount of $classTestCount failed.").'</p>';
        }
        $returnText = $heading.$overviewText;
        // list out results if there are any errors or if showSuccess is true
        if ($this->showSuccess || $classErrCount > 0) $returnText .= $resultText;
        // add teardown failure, if necessary
        if ($teardownErrMsg) $returnText .= '<p>'.self::printFailure("Failure on teardown: $teardownErrMsg").'</p>';
        // add any print content, if necessary
        if ($this->showPrint) {
            $printText = $this->printContent();
            if (!empty($printText)) $returnText .= '<div>'.$printText.'</div>';
        }
        return $returnText;
    }

    /**
     * This method actually puts together the results for a given method.
     * 
     * @param string $methodName - the name of the method in question
     * @param array $methodResults - [string (methodName) => [key ('success' or 'error') => string]]
     * @param int $methodErrors - number of errors encountered while testing that method
     * @param string $haltedErrMsg - if the method ran into an error at some point, this message will be provided
     * @return string - raw HTML describing the result
     */
    protected function getMethodResults(string $methodName, array $methodResults, int $methodErrors, string $haltedErrMsg = null): string {
        $returnText = '<h3>'.strtolower(substr($methodName, 4, 1)).substr($methodName, 5).'</h3>';
        // print overview
        $testCount = count($methodResults);
        if ($methodErrors === 0) $overviewText = self::printSuccess("Success: $testCount of $testCount tests passed.");
        else $overviewText = self::printFailure("Error(s) encountered: $methodErrors of $testCount failed.");
        $returnText .= "<p>$overviewText</p>";
        // check if any errors halted the continuation of tests
        if ($haltedErrMsg) $returnText .= '<p>'.self::printFailure("Method halted with message: $haltedErrMsg").'</p>';
        // print out detailed results
        if (($this->showSuccess || $methodErrors > 0) && $testCount > 0) {
            $returnText .= '<ol>';
            $successCount = 0; // temporary variable to keep track for combining successes
            foreach ($methodResults as $index=>$testResult) {
                $str = ''; // clear str (so it doesn't hold previous result)
                // print error message
                if ($testResult['error']) $str = self::printFailure('Test failed: '.$testResult['error']);
                // or print success message
                else if ($this->showSuccess) $str = self::printSuccess('Test passed: ').' '.$testResult['success'];
                // or indicate number of successes (if this is the end of the list or the next item is an error)
                else if ($index === (count($methodResults)-1) || $methodResults[$index+1]['error']) {
                    $successCount++;
                    $str = self::printSuccess("$successCount tests passed.");
                    $successCount = 0;
                }
                // or add another success to the count
                else $successCount++;
                if (!empty($str)) $returnText .= "<li>$str</li>";
            }
            $returnText .= '</ol>';
        }
        return $returnText;
    }

    /**
     * Function that prints out any content needed for testing. Each item will be printed in its own code block.
     * Uses $this->printContent [[name, content]]
     * 
     * @return string - raw HTML with content
     */
    protected function printContent(): string {
        if (empty($this->printContent)) return '';
        $returnText = '<h3>Print Results</h3>';
        foreach ($this->printContent as $index=>$print) {
            $returnText .= '<div><h4>'.$print['name'].'</h4><pre><code>'.$print['content'].'</code></pre></div>';
        }
        return $returnText;
    }

    // wrapper functions to make HTML generation easier
    protected static function printFailure(string $text): string {
        return "<span style='color: red'>$text</span>";
    }
    protected static function printSuccess(string $text): string {
        return "<span style='color: green'>$text</span>";
    }

    /**
     * Performs several types of tests. Adds success or failure to the class variable $methodResults. If there is an error, also increments the class variable $methodErrors.
     * 
     * @param string $testType - Accepts:  true, false, empty, notEmpty, equals, equalsNumeric, size
     * @param mixed $result - the value to test
     * @param mixed $expected - the expected value for $result, required for equals and size tests
     */
    protected function doTest(string $testType, $result, $expected=null):void {
        try {
            switch ($testType) {
                case "true":
                    if ($result !== true) $err = 'Expected true. Received '.var_export($result, true).'.';
                    else $success = "Value is true.";
                    break;
                case "false":
                    if ($result !== false) $err = 'Expected false. Received '.var_export($result, true).'.';
                    else $success = 'Value is false.';
                    break;
                case "empty":
                    if (!empty($result)) $err = 'Expected empty argument. Received non-empty '.gettype($result).'.';
                    else $success = 'Value is empty.';
                    break;
                case "notEmpty":
                    if (empty($result)) $err = 'Received empty argument.';
                    else $success = 'Value is not empty.';
                    break;
                case "equals":
                    if ($result !== $expected) {
                        if ($result == $expected) $err = "Expected '$expected' of type ".gettype($expected).'. Received type '.gettype($result).'.';
                        else $err = "Expected '$expected'. Received '$result'.";
                    }
                    else $success = "Value equals $result.";
                    break;
                /*case "equalsNumeric":
                    if(!is_numeric($result)) $err = "EqualsNumeric only works if the result is a number. The result '$result' is not numeric";
                    else if ($result != $expected) $err = "Expected '$expected'. Received '$result'.";*/
                case "size":
                    if (count($result) !== $expected) $err = "Expected size of $expected. Received ".count($result).'.';
                    else $success = "Value has size of $expected.";
                    break;
                default:
                    return; // do nothing at all
            }
            if ($err) throw new \Exception($err);
        } catch (\Throwable $t) {
            $this->methodResults[] = ['error'=>$t->getMessage()];
            $this->methodErrors++;
            return;
        }
        $this->methodResults[] = ['success'=>$success];
        return;
    }

    // functions to extend
    protected function setup() {}
    protected function teardown() {}

    // functions to call

    /**
     * Allows printing out some results for manual observation.
     * 
     * @param string $name - something to identify your text
     * @param string $content - whatever you want to print
     */
    public function print(string $name, string $content) {
        $this->printContent[] = ['name'=>$name, 'content'=>$content];
    }

    /**
     * A set of assertion statements for testing. They all call doTest, which will run a check inside of a try-catch statement. The names should be self-explanatory.
     */
    public function assertTrue($result) {
        $this->doTest("true", $result);
    }
    public function assertFalse($result) {
        $this->doTest("false", $result);
    }
    public function assertEmpty($result) {
        $this->doTest("empty", $result);
    }
    public function assertNotEmpty($result) {
        $this->doTest("notEmpty", $result);
    }
    public function assertEquals($result, $expected) {
        $this->doTest("equals", $result, $expected);
    }
    public function assertNull($result) {
        $this->doTest("equals", $result, null);
    }
    public function assertSize($result, $expected) {
        $this->doTest("size", $result, $expected);
    }
}