<?php

/**
 * Base test case for batch processors.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 19.05.14
 */
abstract class Erfurt_Store_Adapter_Sparql_AbstractBatchProcessorTestCase extends PHPUnit_Framework_TestCase
{

    /**
     * System under test.
     *
     * @var Erfurt_Store_Adapter_Sparql_BatchProcessorInterface
     */
    protected $processor = null;

    /**
     * See {@link PHPUnit_Framework_TestCase::setUp()} for details.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->processor = $this->createProcessor();
    }

    /**
     * See {@link PHPUnit_Framework_TestCase::tearDown()} for details.
     */
    protected function tearDown()
    {
        $this->processor = null;
        parent::tearDown();
    }

    /**
     * Checks if the batch processor implements the required interface.
     */
    public function testImplementsInterface()
    {
        $this->assertInstanceOf('Erfurt_Store_Adapter_Sparql_BatchProcessorInterface', $this->processor);
    }

    /**
     * Checks if persist() can handle an empty quad list.
     */
    public function testPersistCanHandleEmptyQuadList()
    {
        $this->setExpectedException(null);
        $this->processor->persist(array());
    }

    /**
     * Checks if persist() adds a single quad.
     */
    public function testPersistAddsSingleQuad()
    {
        $quad = new Erfurt_Store_Adapter_Sparql_Quad(
            'http://example.org/subject',
            'http://example.org/predicate',
            array(
                'type'  => 'uri',
                'value' => 'http://example.org/object'
            ),
            'http://example.org/graph'
        );

        $this->processor->persist(array($quad));

        $this->assertNumberOfTriples(1);
    }

    /**
     * Checks if persist() adds multiple quads.
     */
    public function testPersistAddsMultipleQuads()
    {
        $quads = array(
            new Erfurt_Store_Adapter_Sparql_Quad(
                'http://example.org/subject1',
                'http://example.org/predicate1',
                array(
                    'type'  => 'uri',
                    'value' => 'http://example.org/object1'
                ),
                'http://example.org/graph'
            ),
            new Erfurt_Store_Adapter_Sparql_Quad(
                'http://example.org/subject2',
                'http://example.org/predicate2',
                array(
                    'type'  => 'uri',
                    'value' => 'http://example.org/object2'
                ),
                'http://example.org/graph'
            ),
        );

        $this->processor->persist($quads);

        $this->assertNumberOfTriples(2);
    }

    /**
     * Ensures that persist() adds the triples to the correct graphs.
     */
    public function testPersistAssignsQuadsToCorrectGraphs()
    {
        // Use quads that are assigned to multiple graphs.
        $quads = array(
            new Erfurt_Store_Adapter_Sparql_Quad(
                'http://example.org/subject1',
                'http://example.org/predicate1',
                array(
                    'type'  => 'uri',
                    'value' => 'http://example.org/object1'
                ),
                'http://example.org/graph1'
            ),
            new Erfurt_Store_Adapter_Sparql_Quad(
                'http://example.org/subject2',
                'http://example.org/predicate2',
                array(
                    'type'  => 'uri',
                    'value' => 'http://example.org/object2'
                ),
                'http://example.org/graph2'
            ),
        );

        $this->processor->persist($quads);

        $this->assertNumberOfTriplesInGraph('http://example.org/graph1', 1);
        $this->assertNumberOfTriplesInGraph('http://example.org/graph2', 1);
    }

    /**
     * Asserts that the given graph contains the expected number of triples.
     *
     * @param string $graph
     * @param integer $expected
     */
    protected function assertNumberOfTriplesInGraph($graph, $expected)
    {
        $query  = 'SELECT * FROM <' . $graph . '> WHERE { ?s ?p ?o . }';
        $this->assertNumberOfRowsSelected($expected, $query);
    }

    /**
     * Asserts that the provided SPARQL query selects the expected number of rows.
     *
     * @param integer $expected
     * @param string $query
     */
    protected function assertNumberOfRowsSelected($expected, $query)
    {
        $result = $this->executeSparqlQuery($query);
        $this->assertTrue(isset($result['results']['bindings']), 'Unexpected result structure.');
        $numberOfTriples = count($result['results']['bindings']);
        $this->assertEquals($expected, $numberOfTriples);
    }

    /**
     * Asserts that the whole database contains the expected number of triples.
     *
     * @param integer $expected
     */
    abstract protected function assertNumberOfTriples($expected);

    /**
     * Executes the provided SPARQL query and returns the result in extended format.
     *
     * @param string $query
     * @return mixed
     */
    abstract protected function executeSparqlQuery($query);

    /**
     * Creates the batch processor that is used in the tests.
     *
     * @return Erfurt_Store_Adapter_Sparql_BatchProcessorInterface
     */
    abstract protected function createProcessor();

}
