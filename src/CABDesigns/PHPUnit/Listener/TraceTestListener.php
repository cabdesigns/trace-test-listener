<?php

namespace CABDesigns\PHPUnit\Listener;

/**
 * A PHPUnit TestListener that searches the call stack given parameters, and
 * outputs the results to the console.
 */
class TraceTestListener implements \PHPUnit_Framework_TestListener
{

    /**
     * The directory to write and read traces from.
     * @var string
     */
    protected $traceDir;

    /**
     * The trace filename
     * @var string
     */
    protected $traceFileName;

    /**
     * Collection of patterns to search for inside trace.
     * @var array
     */
    protected $searchPatterns = [];

    /**
     * Collection of results found from search.
     * @var array
     */
    protected $searchResults = [];

    /**
     * The current suite being tested.
     * @var \PHPUnit_Framework_TestSuite
     */
    protected $currentSuite;

    /**
     * The current test being tested.
     * @var \PHPUnit_Framework_Test
     */
    protected $currentTest;

    /**
     * Number of suites to test.
     * @var integer
     */
    protected $numSuites = 0;

    /**
     * Whether the debugger has been enabled.
     * @var boolean
     */
    protected $debuggerEnabled = false;

    /**
     * Listener constructor
     * @param array  $searchPatterns Collection of search patterns to find inside trace files.
     * @param string $traceDir       The directory to write trace files to.
     */
    public function __construct(array $searchPatterns, $traceDir = 'traces')
    {
        $this->searchPatterns = $searchPatterns;
        $this->traceDir = $traceDir;
        $this->debuggerEnabled = extension_loaded('xdebug');
        $this->startListener();
    }

    /**
     * On listener start
     */
    public function startListener()
    {
        if (!file_exists($this->traceDir)) {
            mkdir($this->traceDir);
        }
    }

    /**
     * On listener end
     */
    public function endListener()
    {
        if ($this->searchResults) {

            printf(
                "\n\nFound %s tests using %s search pattern(s).",
                count($this->searchResults),
                count($this->searchPatterns)
            );

            foreach ($this->searchResults as $searchResult) {
                echo $searchResult;
            }
        }
    }

    /**
     * On test suite start
     * @param  \PHPUnit_Framework_TestSuite $suite
     */
    public function startTestSuite(\PHPUnit_Framework_TestSuite $suite)
    {

        if (!$this->shouldListen()) {
            return;
        }

        $this->numSuites++;

        $this->currentSuite = $suite;

    }

    /**
     * On test suite end
     * @param  \PHPUnit_Framework_TestSuite $suite
     */
    public function endTestSuite(\PHPUnit_Framework_TestSuite $suite)
    {
        if (!$this->shouldListen()) {
            return;
        }

        $this->numSuites--;

        if (!$this->numSuites) {
            $this->endListener();
        }
    }

    /**
     * On test start
     * @param  \PHPUnit_Framework_Test $test
     */
    public function startTest(\PHPUnit_Framework_Test $test)
    {
        if (!$this->shouldListen()) {
            return;
        }

        $this->currentTest = $test;

        $this->traceFileName = $this->buildTraceFileName();

        xdebug_start_trace($this->getTraceFullPath());
    }

    /**
     * On test end
     * @param  \PHPUnit_Framework_Test $test
     * @param  string $time Time taken for test to execute.
     */
    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        if (!$this->shouldListen()) {
            return;
        }

        xdebug_stop_trace();

        $traceFilePath = $this->getTraceFullPath() . '.xt';

        $trace = file_get_contents($traceFilePath);

        foreach ($this->searchPatterns as $searchPattern) {

            $foundPattern = $this->find($trace, $searchPattern);

            if ($foundPattern) {

                $this->addSearchResult(
                    $searchPattern,
                    $foundPattern
                );

            } else {

                unlink($traceFilePath);

            }

        }

    }

    /**
     * Add a search result to the stack
     * @param string $searchPattern
     * @param mixed $patternResult Useful if searching with regex. Otherwise ignore.
     */
    public function addSearchResult($searchPattern, $patternResult)
    {
        $searchResult = sprintf(
            "\nFound %s inside '%s::%s'.",
            $searchPattern,
            $this->currentSuite->getName(),
            $this->currentTest->getName()
        );

        $this->searchResults[] = $searchResult;
    }

    /**
     * Whether we should listen.
     * @return boolean
     */
    protected function shouldListen()
    {
        return ($this->searchPatterns && $this->debuggerEnabled);
    }

    /**
     * Find search criteria in trace output
     * @param  string $haystack The trace output
     * @param  string $needle   The search criteria
     * @return boolean
     */
    protected function find($haystack, $needle)
    {
        return false !== strpos($haystack, $needle);
    }

    /**
     * Generate a file name for the test trace
     * @return string
     */
    protected function buildTraceFileName()
    {
        return strtolower($this->currentSuite->getName() . '_' . $this->currentTest->getName());
    }

    /**
     * Get the full path of the trace file,
     * @return string
     */
    public function getTraceFullPath()
    {
        return $this->traceDir . DIRECTORY_SEPARATOR . $this->traceFileName;
    }

    /**
     * Required for Interface
     * @see \PHPUnit_Framework_TestListener::addError()
     */
    public function addError(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
    }

    /**
     * Required for Interface
     * @see \PHPUnit_Framework_TestListener::addFailure()
     */
    public function addFailure(\PHPUnit_Framework_Test $test, \PHPUnit_Framework_AssertionFailedError $e, $time)
    {
    }

    /**
     * Required for Interface
     * @see \PHPUnit_Framework_TestListener::addIncompleteTest()
     */
    public function addIncompleteTest(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
    }

    /**
     * Required for Interface
     * @see \PHPUnit_Framework_TestListener::addRiskyTest()
     */
    public function addRiskyTest(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
    }

    /**
     * Required for Interface
     * @see \PHPUnit_Framework_TestListener::addSkippedTest()
     */
    public function addSkippedTest(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
    }
}
