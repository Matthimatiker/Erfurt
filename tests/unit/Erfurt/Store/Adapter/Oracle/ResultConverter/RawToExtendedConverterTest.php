<?php

/**
 * Tests the RawToExtended result set converter.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 30.12.13
 */
class Erfurt_Store_Adapter_Oracle_ResultConverter_RawToExtendedConverterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * System under test.
     *
     * @var Erfurt_Store_Adapter_Oracle_ResultConverter_RawToExtendedConverter
     */
    protected $converter = null;

    /**
     * See {@link PHPUnit_Framework_TestCase::setUp()} for details.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->converter = new Erfurt_Store_Adapter_Oracle_ResultConverter_RawToExtendedConverter();
    }

    /**
     * See {@link PHPUnit_Framework_TestCase::tearDown()} for details.
     */
    protected function tearDown()
    {
        $this->converter = null;
        parent::tearDown();
    }

    /**
     * Checks if the converter implements the necessary interface.
     */
    public function testImplementsInterface()
    {
        $this->assertInstanceOf('\Erfurt_Store_Adapter_ResultConverter_ResultConverterInterface', $this->converter);
    }

    /**
     * Ensures that convert() throws an exception if no array is passed for conversion.
     */
    public function testConvertThrowsExceptionIfNoArrayIsPassed()
    {
        $this->setExpectedException('Erfurt_Store_Adapter_ResultConverter_Exception');
        $this->converter->convert(new stdClass());
    }

    /**
     * Checks if convert() adds the variable names from the raw result set to the
     * head section of the extended result.
     */
    public function testConvertAddsVariablesToHeadSection()
    {
        $converted = $this->converter->convert($this->getRawResultSet());

        $this->assertInternalType('array', $converted);
        $this->assertArrayHasKey('head', $converted);
        $head = $converted['head'];
        $this->assertInternalType('array', $head);
        $this->assertArrayHasKey('vars', $head);
        $vars = $head['vars'];
        $this->assertInternalType('array', $vars);
        $this->assertContains('SUBJECT', $vars);
        $this->assertContains('PREDICATE', $vars);
        $this->assertContains('OBJECT', $vars);
    }

    /**
     * Checks if convert() adds the correct number of bindings.
     */
    public function testConvertAddsCorrectNumberOfBindings()
    {
        $rawResultSet = $this->getRawResultSet();
        $converted = $this->converter->convert($rawResultSet);

        $bindings = $this->getBindings($converted);
        $this->assertCount(count($rawResultSet), $bindings);
    }

    /**
     * Checks if convert() adds the correct variable types to the extended
     * result set.
     */
    public function testConvertAddsCorrectVariableTypesToBindings()
    {
        $converted = $this->converter->convert($this->getRawResultSet());

        $bindings = $this->getBindings($converted);
        $this->assertEquals('uri', $bindings[1]['SUBJECT']['type']);
        $this->assertEquals('literal', $bindings[1]['OBJECT']['type']);
    }

    /**
     * Ensures that convert() assigns the correct values to the bound variables
     * in the extended result set.
     */
    public function testConvertAssignsCorrectVariableValues()
    {
        $converted = $this->converter->convert($this->getRawResultSet());

        $bindings = $this->getBindings($converted);
        $this->assertEquals('http://www.example.org/subject', $bindings[1]['SUBJECT']['value']);
        $this->assertEquals('Object literal.', $bindings[1]['OBJECT']['value']);
    }

    /**
     * Ensures that convert() assigns the language to a literal if it is available.
     */
    public function testConvertAssignsLanguageIfAvailable()
    {
        $converted = $this->converter->convert($this->getRawResultSet());

        $bindings = $this->getBindings($converted);
        $this->assertArrayHasKey(2, $bindings);
        $this->assertArrayHasKey('lang', $bindings[2]['OBJECT']);
        $this->assertEquals('de', $bindings[2]['OBJECT']['lang']);
    }

    /**
     * Ensures that convert() does not assign a language entry to an object literal
     * if the language is not available.
     */
    public function testConvertDoesNotAssignLanguageIfItIsNotDefined()
    {
        $converted = $this->converter->convert($this->getRawResultSet());

        $bindings = $this->getBindings($converted);
        $this->assertArrayHasKey(1, $bindings);
        $this->assertArrayNotHasKey('lang', $bindings[1]['OBJECT']);
    }

    /**
     * Ensures that convert() assigns the data type to an object literal if
     * it is available.
     */
    public function testConvertAssignsDataTypeIfAvailable()
    {
        $converted = $this->converter->convert($this->getRawResultSet());

        $bindings = $this->getBindings($converted);
        $this->assertArrayHasKey(3, $bindings);
        $this->assertArrayHasKey('datatype', $bindings[3]['OBJECT']);
        $this->assertEquals(EF_XSD_NS . 'string', $bindings[3]['OBJECT']['datatype']);
    }

    /**
     * Ensures that convert() does not add a data type entry t o an object
     * literal if the data type is not explicitly defined.
     */
    public function testConvertDoesNotAssignDataTypeIfItIsNotDefined()
    {
        $converted = $this->converter->convert($this->getRawResultSet());

        $bindings = $this->getBindings($converted);
        $this->assertArrayHasKey(1, $bindings);
        $this->assertArrayNotHasKey('datatype', $bindings[1]['OBJECT']);
    }

    /**
     * Checks if convert() can handle an empty input array.
     */
    public function testConvertCanHandleEmptyArrays()
    {
        $expected = array(
            'head' => array(
                'vars' => array()
            ),
            'results' => array(
                'bindings' => array()
            )
        );

        $this->assertEquals($expected, $this->converter->convert(array()));
    }

    /**
     * Extracts the bindings from the provided converted result set.
     *
     * @param array(mixed)|mixed $converted
     * @return array(mixed)
     */
    protected function getBindings($converted)
    {
        $this->assertInternalType('array', $converted);
        $this->assertArrayHasKey('results', $converted);
        $results = $converted['results'];
        $this->assertInternalType('array', $results);
        $this->assertArrayHasKey('bindings', $results);
        $bindings = $results['bindings'];
        $this->assertInternalType('array', $bindings);
        foreach ($bindings as $index => $row) {
            /* @var $row array(array(string=>string)) */
            $this->assertInternalType('array', $row);
            foreach ($row as $variable => $data) {
                /* @var $variable string */
                /* @var $data array(string=>string) */
                $message = 'Expected variable name as index, but received: ' . $variable;
                $this->assertFalse(is_numeric($variable), $message);
                $this->assertInternalType('array', $data, 'Variable data must be provided as array.');
                $message = 'Missing value entry for variable "' . $variable . '" in row ' . $index . '.';
                $this->assertArrayHasKey('value', $data, $message);
                $message = 'Missing type entry for variable "' . $variable . '" in row ' . $index . '.';
                $this->assertArrayHasKey('type', $data, $message);
            }
        }
        return $bindings;
    }

    /**
     * Returns an example result set.
     *
     * The result set contains 2 rows and uses the variables SUBJECT,
     * PREDICATE and OBJECT.
     *
     * @return array(array(string=>string|null))
     */
    protected function getRawResultSet()
    {
        return array(
            array(
                'SUBJECT'           => 'http://www.example.org/subject',
                'SUBJECT$RDFVID'    => 7614293122126211127,
                'SUBJECT$_PREFIX'   => 'http://www.example.org/',
                'SUBJECT$_SUFFIX'   => 'subject',
                'SUBJECT$RDFVTYP'   => 'URI',
                'SUBJECT$RDFCLOB'   => null,
                'SUBJECT$RDFLTYP'   => null,
                'SUBJECT$RDFLANG'   => null,
                'PREDICATE'         => 'http://www.example.org/predicate',
                'PREDICATE$RDFVID'  => 8663359142594985318,
                'PREDICATE$_PREFIX' => 'http://www.example.org/',
                'PREDICATE$_SUFFIX' => 'predicate',
                'PREDICATE$RDFVTYP' => 'URI',
                'PREDICATE$RDFCLOB' => null,
                'PREDICATE$RDFLTYP' => null,
                'PREDICATE$RDFLANG' => null,
                'OBJECT'            => 'http://www.example.org/object',
                'OBJECT$RDFVID'     => 6944352155936009563,
                'OBJECT$_PREFIX'    => 'http://www.example.org/',
                'OBJECT$_SUFFIX'    => 'object',
                'OBJECT$RDFVTYP'    => 'URI',
                'OBJECT$RDFCLOB'    => null,
                'OBJECT$RDFLTYP'    => null,
                'OBJECT$RDFLANG'    => null,
                'SEM$ROWNUM'        => 1
            ),
            array(
                'SUBJECT'           => 'http://www.example.org/subject',
                'SUBJECT$RDFVID'    => 7614293122126211127,
                'SUBJECT$_PREFIX'   => 'http://www.example.org/',
                'SUBJECT$_SUFFIX'   => 'subject',
                'SUBJECT$RDFVTYP'   => 'URI',
                'SUBJECT$RDFCLOB'   => null,
                'SUBJECT$RDFLTYP'   => null,
                'SUBJECT$RDFLANG'   => null,
                'PREDICATE'         => 'http://www.example.org/predicate',
                'PREDICATE$RDFVID'  => 8663359142594985318,
                'PREDICATE$_PREFIX' => 'http://www.example.org/',
                'PREDICATE$_SUFFIX' => 'predicate',
                'PREDICATE$RDFVTYP' => 'URI',
                'PREDICATE$RDFCLOB' => null,
                'PREDICATE$RDFLTYP' => null,
                'PREDICATE$RDFLANG' => null,
                'OBJECT'            => 'Object literal.',
                'OBJECT$RDFVID'     => 4890984317608187388,
                'OBJECT$_PREFIX'    => 'Object literal.',
                'OBJECT$_SUFFIX'    => null,
                'OBJECT$RDFVTYP'    => 'LIT',
                'OBJECT$RDFCLOB'    => null,
                'OBJECT$RDFLTYP'    => null,
                'OBJECT$RDFLANG'    => null,
                'SEM$ROWNUM'        => 2
            ),
            // Contains object literal with language.
            array(
                'SUBJECT'           => 'http://www.example.org/subject',
                'SUBJECT$RDFVID'    => 7614293122126211127,
                'SUBJECT$_PREFIX'   => 'http://www.example.org/',
                'SUBJECT$_SUFFIX'   => 'subject',
                'SUBJECT$RDFVTYP'   => 'URI',
                'SUBJECT$RDFCLOB'   => null,
                'SUBJECT$RDFLTYP'   => null,
                'SUBJECT$RDFLANG'   => null,
                'PREDICATE'         => 'http://www.example.org/predicate',
                'PREDICATE$RDFVID'  => 8663359142594985318,
                'PREDICATE$_PREFIX' => 'http://www.example.org/',
                'PREDICATE$_SUFFIX' => 'predicate',
                'PREDICATE$RDFVTYP' => 'URI',
                'PREDICATE$RDFCLOB' => null,
                'PREDICATE$RDFLTYP' => null,
                'PREDICATE$RDFLANG' => null,
                'OBJECT'            => 'Object literal.',
                'OBJECT$RDFVID'     => 4890984317608187388,
                'OBJECT$_PREFIX'    => 'Object literal.',
                'OBJECT$_SUFFIX'    => null,
                'OBJECT$RDFVTYP'    => 'LIT',
                'OBJECT$RDFCLOB'    => null,
                'OBJECT$RDFLTYP'    => null,
                'OBJECT$RDFLANG'    => 'de',
                'SEM$ROWNUM'        => 3
            ),
            // Contains object literal with data type.
            array(
                'SUBJECT'           => 'http://www.example.org/subject',
                'SUBJECT$RDFVID'    => 7614293122126211127,
                'SUBJECT$_PREFIX'   => 'http://www.example.org/',
                'SUBJECT$_SUFFIX'   => 'subject',
                'SUBJECT$RDFVTYP'   => 'URI',
                'SUBJECT$RDFCLOB'   => null,
                'SUBJECT$RDFLTYP'   => null,
                'SUBJECT$RDFLANG'   => null,
                'PREDICATE'         => 'http://www.example.org/predicate',
                'PREDICATE$RDFVID'  => 8663359142594985318,
                'PREDICATE$_PREFIX' => 'http://www.example.org/',
                'PREDICATE$_SUFFIX' => 'predicate',
                'PREDICATE$RDFVTYP' => 'URI',
                'PREDICATE$RDFCLOB' => null,
                'PREDICATE$RDFLTYP' => null,
                'PREDICATE$RDFLANG' => null,
                'OBJECT'            => 'Object literal.',
                'OBJECT$RDFVID'     => 4890984317608187388,
                'OBJECT$_PREFIX'    => 'Object literal.',
                'OBJECT$_SUFFIX'    => null,
                'OBJECT$RDFVTYP'    => 'LIT',
                'OBJECT$RDFCLOB'    => null,
                'OBJECT$RDFLTYP'    => EF_XSD_NS . 'string',
                'OBJECT$RDFLANG'    => null,
                'SEM$ROWNUM'        => 4
            )
        );
    }

}
