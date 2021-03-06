<?php

/**
 * Base class for SPARQL connector tests.
 *
 * Contains several tests that should be fulfilled by any connector.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 02.03.14
 */
abstract class Erfurt_Store_Adapter_Sparql_AbstractSparqlConnectorTestCase extends \PHPUnit_Framework_TestCase
{

    /**
     * System under test.
     *
     * @var \Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface
     */
    protected $connector = null;

    /**
     * See {@link PHPUnit_Framework_TestCase::setUp()} for details.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->connector = $this->createConnector();
    }

    /**
     * See {@link PHPUnit_Framework_TestCase::tearDown()} for details.
     */
    protected function tearDown()
    {
        $this->connector = null;
        parent::tearDown();
    }

    /**
     * Asserts that the connector implements the required interface.
     */
    public function testImplementsInterface()
    {
        $this->assertInstanceOf('\Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface', $this->connector);
    }

    /**
     * Ensures that an exception is thrown if a syntactically invalid
     * SPARQL query is passed to query().
     */
    public function testQueryThrowsExceptionIfInvalidQueryIsPassed()
    {
        $this->setExpectedException('Exception');
        $this->connector->query('Hello world!');
    }

    /**
     * Ensures that query() returns an array if a select query is passed.
     */
    public function testQueryReturnsArrayIfSelectQueryIsPassed()
    {
        $this->insertTriple();

        $query  = 'SELECT ?subject FROM <http://example.org/graph> WHERE { ?subject ?predicate ?object. }';
        $result = $this->connector->query($query);

        $this->assertInternalType('array', $result);
    }

    /**
     * Checks if the result set that is returned by query() contains
     * the requested variables.
     */
    public function testQueryResultContainsRequestedVariables()
    {
        $this->insertTriple();

        $query = 'SELECT ?subject ?predicate ?object FROM <http://example.org/graph> '
               . 'WHERE { ?subject ?predicate ?object. }';
        $result = $this->connector->query($query);

        $this->assertExtendedResultStructure($result);
        $this->assertContains('subject', $result['head']['vars']);
        $this->assertContains('predicate', $result['head']['vars']);
        $this->assertContains('object', $result['head']['vars']);
        foreach ($result['results']['bindings'] as $row) {
            $this->assertInternalType('array', $row);
            $this->assertArrayHasKey('subject', $row);
            $this->assertArrayHasKey('predicate', $row);
            $this->assertArrayHasKey('object', $row);
        }
    }

    /**
     * Checks if the result set that is returned by query() contains
     * the defined aliased variables.
     */
    public function testQueryResultContainsAliasedVariables()
    {
        $this->insertTriple();

        $query = 'SELECT (?subject AS ?aliased) FROM <http://example.org/graph> '
               . 'WHERE { ?subject ?predicate ?object. }';
        $result = $this->connector->query($query);

        $this->assertExtendedResultStructure($result);
        $this->assertContains('aliased', $result['head']['vars']);
    }

    /**
     * Ensures that query() returns an empty set if no data
     * matches the query.
     */
    public function testQueryResultIsEmptyIfNoDataMatches()
    {
        $this->insertTriple();

        $query = 'SELECT ?object FROM <http://example.org/graph> '
               . 'WHERE { <http://testing.org/subject> ?predicate ?object. }';
        $result = $this->connector->query($query);

        $this->assertNumberOfRows(0, $result);
    }

    /**
     * Checks if query() returns the correct number of rows
     * for a query that selects a subset of the data.
     */
    public function testQueryResultReturnsCorrectNumberOfRows()
    {
        $this->insertTriple('http://example.org/subject');
        $this->insertTriple('http://example.org/subject', 'http://example.org/predicate2');
        $this->insertTriple('http://example.org/another-subject');

        $query = 'SELECT ?object FROM <http://example.org/graph> '
               . 'WHERE { <http://example.org/subject> ?predicate ?object. }';
        $result = $this->connector->query($query);

        $this->assertNumberOfRows(2, $result);
    }

    /**
     * Ensures that the result set that is returned by query()
     * is ordered correctly.
     */
    public function testQueryResultIsOrderedCorrectly()
    {
        // Insert triples unordered to ensure that they are not randomly returned
        // in order.
        $this->insertTriple('http://example.org/003');
        $this->insertTriple('http://example.org/001');
        $this->insertTriple('http://example.org/002');

        $query = 'SELECT ?subject FROM <http://example.org/graph> '
               . 'WHERE { ?subject ?predicate ?object. } ORDER BY ASC(?subject)';
        $result = $this->connector->query($query);

        $this->assertExtendedResultStructure($result);
        $subjects = array_map(function (array $row) {
            \PHPUnit_Framework_Assert::assertArrayHasKey('subject', $row);
            return $row['subject']['value'];
        }, $result['results']['bindings']);
        $expected = array(
            'http://example.org/001',
            'http://example.org/002',
            'http://example.org/003'
        );
        $this->assertEquals($expected, $subjects);
    }

    /**
     * Checks if query() handles camel cased variables (for example ?resourceUri)
     * correctly.
     */
    public function testQuerySupportsCamelCasedVariables()
    {
        $this->insertTriple();

        $query = 'SELECT ?camelCasedObject '
               . 'FROM <http://example.org/graph> '
               . 'WHERE { ?subject ?predicate ?camelCasedObject . }';

        $result = $this->connector->query($query);

        $this->assertNumberOfRows(1, $result);
        $this->assertContains('camelCasedObject', $result['head']['vars']);
        $row = array_shift($result['results']['bindings']);
        $this->assertContains('camelCasedObject', array_keys($row));
    }

    /**
     * Checks if query() accepts a query that uses numbers as variable identifiers.
     *
     * @see http://www.w3.org/TR/2013/REC-sparql11-query-20130321/#rVARNAME
     */
    public function testQueryAcceptsQueryThatUsesNumbersAsVariables()
    {
        $query = 'SELECT ?1 '
               . 'FROM <http://example.org> '
               . 'WHERE {'
               . '    {?1 a <http://example.org/animal>} UNION {?1 a <http://example.org/human>}'
               . '}';

        $result = $this->connector->query($query);

        $this->assertExtendedResultStructure($result);
    }

    /**
     * Ensures that query() returns only the variables that were
     * requested in the SPARQL query.
     */
    public function testQueryReturnsOnlyRequestedVariables()
    {
        $this->insertTriple();

        $query = 'SELECT ?subject ?object FROM <http://example.org/graph> '
               . 'WHERE { ?subject ?predicate ?object. }';
        $result = $this->connector->query($query);

        $expectedKeys = array(
            'subject',
            'object'
        );
        $this->assertExtendedResultStructure($result);
        foreach ($result['results']['bindings'] as $row) {
            /* @var $row array(string=>string) */
            $this->assertInternalType('array', $row);
            $keys = array_keys($row);
            $additionalKeys = array_diff($keys, $expectedKeys);
            $this->assertEquals(array(), $additionalKeys, 'Additional keys in result rows detected.');
        }
    }

    /**
     * Checks if query() works with Unix new line values ("\n")
     * in the query.
     */
    public function testQueryHandlesUnixNewLines()
    {
        $query = "SELECT ?subject ?object\n"
               . "FROM <http://example.org/graph>\n"
               . "WHERE { ?subject ?predicate ?object. }";

        $this->setExpectedException(null);
        $this->connector->query($query);
    }

    /**
     * Checks if query() works with Windows new line values ("\r\n")
     * in the query.
     */
    public function testQueryHandlesWindowsNewLines()
    {
        $query = "SELECT ?subject ?object\r\n"
               . "FROM <http://example.org/graph>\r\n"
               . "WHERE { ?subject ?predicate ?object. }";

        $this->setExpectedException(null);
        $this->connector->query($query);
    }

    /**
     * Checks if integer literals in the result set are converted into their native PHP type.
     */
    public function testQueryConvertsIntegerLiteralsCorrectly()
    {
        $object = array(
            'type'     => 'literal',
            'datatype' => 'http://www.w3.org/2001/XMLSchema#integer',
            'value'    => '42'
        );
        $this->insertTriple('http://example.org/subject', 'http://example.org/predicate', $object);

        $query = 'SELECT ?object '
               . 'FROM <http://example.org/graph> '
               . 'WHERE { <http://example.org/subject> <http://example.org/predicate> ?object . }';
        $result = $this->connector->query($query);

        $this->assertNumberOfRows(1, $result);
        $literalValue = $result['results']['bindings'][0]['object']['value'];
        $this->assertInternalType('integer', $literalValue);
        $this->assertEquals(42, $literalValue);
    }

    /**
     * Checks if boolean literals in the result set are converted into their native PHP type.
     */
    public function testQueryConvertsBooleanLiteralsCorrectly()
    {
        $object = array(
            'type'     => 'literal',
            'datatype' => 'http://www.w3.org/2001/XMLSchema#boolean',
            'value'    => 'false'
        );
        $this->insertTriple('http://example.org/subject', 'http://example.org/predicate', $object);

        $query = 'SELECT ?object '
               . 'FROM <http://example.org/graph> '
               . 'WHERE { <http://example.org/subject> <http://example.org/predicate> ?object . }';
        $result = $this->connector->query($query);

        $this->assertNumberOfRows(1, $result);
        $literalValue = $result['results']['bindings'][0]['object']['value'];
        $this->assertInternalType('boolean', $literalValue);
        $this->assertFalse($literalValue);
    }

    /**
     * Checks if double literals in the result set are converted into their native PHP type.
     */
    public function testQueryConvertsDoubleLiteralsCorrectly()
    {
        $object = array(
            'type'     => 'literal',
            'datatype' => 'http://www.w3.org/2001/XMLSchema#double',
            'value'    => '42.42'
        );
        $this->insertTriple('http://example.org/subject', 'http://example.org/predicate', $object);

        $query = 'SELECT ?object '
               . 'FROM <http://example.org/graph> '
               . 'WHERE { <http://example.org/subject> <http://example.org/predicate> ?object . }';
        $result = $this->connector->query($query);

        $this->assertNumberOfRows(1, $result);
        $literalValue = $result['results']['bindings'][0]['object']['value'];
        $this->assertInternalType('float', $literalValue);
        $this->assertEquals(42.42, $literalValue);
    }

    /**
     * Ensures that query() returns a boolean value if an ASK
     * query is passed.
     */
    public function testQueryReturnsBooleanIfAskQueryIsPassed()
    {
        $query = 'ASK FROM <http://example.org/graph> '
               . 'WHERE { ?subject ?predicate ?object . }';

        $result = $this->connector->query($query);

        $this->assertInternalType('boolean', $result);
    }

    /**
     * Ensures that an ASK query returns false if no triple matches
     * the provided query.
     */
    public function testAskQueryReturnsFalseIfNoTripleMatches()
    {
        $query = 'ASK FROM <http://example.org/graph> '
               . 'WHERE { ?subject ?predicate ?object . }';

        $result = $this->connector->query($query);

        $this->assertFalse($result);
    }

    /**
     * Ensures that an ASK query returns true if at least one triple matches
     * the provided SPARQL query.
     */
    public function testAskQueryReturnsTrueIfAtLeastOneTripleMatchesPattern()
    {
        $this->insertTriple();
        $query = 'ASK FROM <http://example.org/graph> '
               . 'WHERE { ?subject ?predicate ?object . }';

        $result = $this->connector->query($query);

        $this->assertTrue($result);
    }

    /**
     * Checks if query() supports queries that contain IRIs with special characters.
     */
    public function testQuerySupportsIriWithSpecialCharacters()
    {
        $query = 'SELECT ?p ?o '
               . 'FROM <http://example.org/graph> '
               . 'WHERE { <http://example.org/iri/with/quote/x\'y> ?p ?o . }';

        $this->setExpectedException(null);
        $this->connector->query($query);
    }

    /**
     * Checks if the connector can work with SPARQL queries that contain
     * a full SPARQL query as literal.
     */
    public function testConnectorCanWorkWithSparqlQueryInStringLiteral()
    {
        $object = array(
            'type'  => 'literal',
            'value' => 'SELECT ?subject WHERE { ?subject ?predicate ?object . }'
        );
        $this->insertTriple('http://example.org/subject', 'http://example.org/predicate', $object);

        $query = 'SELECT ?subject '
               . 'FROM <http://example.org/graph> '
               . 'WHERE { ?subject ?predicate %s . }';
        $query = sprintf($query, Erfurt_Utils::buildLiteralString($object['value']));
        $result = $this->connector->query($query);

        // The inserted triple should have been selected.
        $this->assertNumberOfRows(1, $result);
    }

    /**
     * Checks if the connector can work with a SPARQL query that is passed as string literal
     * and that contains another literal itself.
     */
    public function testConnectorCanWorkWithSparqlQueryThatContainsLiteralInStringLiteral()
    {
        $object = array(
            'type'  => 'literal',
            'value' => 'SELECT ?subject WHERE { ?subject ?predicate "This is a ?test variable." . }'
        );
        $this->insertTriple('http://example.org/subject', 'http://example.org/predicate', $object);

        $query = 'SELECT ?subject '
               . 'FROM <http://example.org/graph> '
               . 'WHERE { ?subject ?predicate %s . }';
        $query = sprintf($query, Erfurt_Utils::buildLiteralString($object['value']));
        $result = $this->connector->query($query);

        // The inserted triple should have been selected.
        $this->assertNumberOfRows(1, $result);
    }

    /**
     * Ensures that deleteMatchingTriples() does nothing if no triples belong to
     * the given graph.
     */
    public function testDeleteMatchingTriplesDoesNothingIfNoCorrespondingTriplesExist()
    {
        $this->insertTriple();

        $this->connector->deleteMatchingTriples(
            'http://example.org',
            new Erfurt_Store_Adapter_Sparql_TriplePattern(null, null, null)
        );

        $this->assertEquals(1, $this->countTriples());
    }

    /**
     * Checks if deleteMatchingTriples() removes all triples that belong to the
     * provided graph if a triple pattern without restrictions is passed.
     */
    public function testDeleteMatchingTriplesRemovesAllTriplesThatBelongToTheGivenGraph()
    {
        $this->insertTriple(
            'http://example.org/subject',
            'http://example.org/predicate',
            'http://example.org/object',
            'http://example.org/graph'
        );
        $this->insertTriple(
            'http://example.org/subject',
            'http://example.org/predicate',
            'http://example.org/object',
            'http://example.org/graph'
        );

        $this->connector->deleteMatchingTriples(
            'http://example.org/graph',
            new Erfurt_Store_Adapter_Sparql_TriplePattern()
        );

        $this->assertEquals(0, $this->countTriples());
    }

    /**
     * Ensures that deleteMatchingTriples() does not remove triples from other graphs.
     */
    public function testDeleteMatchingTriplesDoesNotRemoveTriplesFromOtherGraphs()
    {
        $this->insertTriple(
            'http://example.org/subject1',
            'http://example.org/predicate',
            'http://example.org/object',
            'http://example.org/graph'
        );
        $this->insertTriple(
            'http://example.org/subject2',
            'http://example.org/predicate',
            'http://example.org/object',
            'http://example.org/graph'
        );
        $this->insertTriple(
            'http://example.org/subject',
            'http://example.org/predicate',
            'http://example.org/object',
            'http://example.org/another-graph'
        );

        $this->connector->deleteMatchingTriples(
            'http://example.org/graph',
            new Erfurt_Store_Adapter_Sparql_TriplePattern()
        );

        $this->assertEquals(1, $this->countTriples());
    }

    /**
     * Ensures that deleteMatchingTriples() removes all triples that match the
     * provided graph/subject combination.
     */
    public function testDeleteMatchingTriplesRemovesAllTriplesWithProvidedSubject()
    {
        $this->insertTriple();
        $this->insertTriple(
            'http://example.org/some-subject',
            'http://example.org/predicate1'
        );
        $this->insertTriple(
            'http://example.org/some-subject',
            'http://example.org/predicate2'
        );

        $this->connector->deleteMatchingTriples(
            'http://example.org/graph',
            new Erfurt_Store_Adapter_Sparql_TriplePattern(
                'http://example.org/some-subject'
            )
        );

        $this->assertEquals(1, $this->countTriples());
    }

    /**
     * Checks if deleteMatchingTriples() removes a specific triple if all details (subject,
     * predicate, object) are passed.
     */
    public function testDeleteMatchingTriplesDeletesSpecificTripleIfAllInformationIsPassed()
    {
        $this->insertTriple();
        $this->insertTriple(
            'http://example.org/subject',
            'http://example.org/another-predicate',
            'http://example.org/another-object',
            'http://example.org/graph'
        );

        $this->connector->deleteMatchingTriples(
            'http://example.org/graph',
            new Erfurt_Store_Adapter_Sparql_TriplePattern(
                'http://example.org/subject',
                'http://example.org/another-predicate',
                array(
                    'value' => 'http://example.org/another-object',
                    'type' => 'uri'
                )
            )
        );

        $this->assertEquals(1, $this->countTriples());
    }

    /**
     * Checks if deleteMatchingTriples() is able to remove a triple with literal as
     * object.
     */
    public function testDeleteMatchingTriplesDeletesTripleWithObjectLiteral()
    {
        $this->insertTriple();
        $this->insertTriple(
            'http://example.org/subject',
            'http://example.org/another-predicate',
            array(
                'value' => 'Hello world!',
                'type' => 'literal'
            ),
            'http://example.org/graph'
        );

        $this->connector->deleteMatchingTriples(
            'http://example.org/graph',
            new Erfurt_Store_Adapter_Sparql_TriplePattern(
                'http://example.org/subject',
                'http://example.org/another-predicate',
                array(
                    'value' => 'Hello world!',
                    'type' => 'literal'
                )
            )
        );

        $this->assertEquals(1, $this->countTriples());
    }

    /**
     * Ensures that deleteMatchingTriples() returns 0 if no statement was deleted.
     */
    public function testDeleteMatchingTriplesReturnsZeroIfNoTripleWasDeleted()
    {
        $this->insertTriple();

        $deleted = $this->connector->deleteMatchingTriples(
            'http://example.org/not-existing-graph',
            new Erfurt_Store_Adapter_Sparql_TriplePattern()
        );

        $this->assertInternalType('integer', $deleted);
        $this->assertEquals(0, $deleted);
    }

    /**
     * Checks if deleteMatchingTriples() returns the number of removed triples.
     */
    public function testDeleteMatchingTriplesReturnsNumberOfDeletedTriples()
    {
        $this->insertTriple();
        $this->insertTriple(
            'http://example.org/subject1',
            'http://example.org/predicate',
            'http://example.org/object',
            'http://example.org/graph-that-will-be-deleted'
        );
        $this->insertTriple(
            'http://example.org/subject2',
            'http://example.org/predicate',
            'http://example.org/object',
            'http://example.org/graph-that-will-be-deleted'
        );
        $before = $this->countTriples();

        $deleted = $this->connector->deleteMatchingTriples(
            'http://example.org/graph-that-will-be-deleted',
            new Erfurt_Store_Adapter_Sparql_TriplePattern()
        );

        $after = $this->countTriples();
        $this->assertInternalType('integer', $deleted);
        $this->assertEquals($before - $after, $deleted);
    }

    /**
     * Checks if triples with objects that are typed as string are removed correctly.
     */
    public function testDeleteMatchingTriplesRemovesObjectLiteralsThatAreTypedAsStringCorrectly()
    {
        $object = array(
            'value'    => 'Hello',
            'type'     => 'literal',
            'datatype' => EF_XSD_NS . 'string'
        );
        $this->insertTriple(
            'http://example.org/subject',
            'http://example.org/predicate',
            $object
        );

        $this->connector->deleteMatchingTriples(
            'http://example.org/graph',
            new Erfurt_Store_Adapter_Sparql_TriplePattern(
                'http://example.org/subject',
                'http://example.org/predicate',
                $object
            )
        );

        $this->assertEquals(0, $this->countTriples());
    }

    /**
     * Checks if triples that contain a literal object of type integer are removed correctly.
     */
    public function testDeleteMatchingTriplesRemovesObjectLiteralsThatAreTypedAsIntegerCorrectly()
    {
        $object = array(
            'value'    => 42,
            'type'     => 'literal',
            'datatype' => EF_XSD_NS . 'integer'
        );
        $this->insertTriple(
            'http://example.org/subject',
            'http://example.org/predicate',
            $object
        );

        $this->connector->deleteMatchingTriples(
            'http://example.org/graph',
            new Erfurt_Store_Adapter_Sparql_TriplePattern(
                'http://example.org/subject',
                'http://example.org/predicate',
                $object
            )
        );

        $this->assertEquals(0, $this->countTriples());
    }

    /**
     * Checks if triples with object literal that has a language are removed correctly.
     */
    public function testDeleteMatchingTriplesRemovesObjectLiteralsWithLanguageCorrectly()
    {
        $this->insertTriple(
            'http://example.org/subject',
            'http://example.org/predicate',
            array(
                'value' => 'hallo',
                'type'  => 'literal',
                'lang'  => 'de'
            )
        );
        $object = array(
            'value' => 'hello',
            'type'  => 'literal',
            'lang'  => 'en'
        );
        $this->insertTriple(
            'http://example.org/subject',
            'http://example.org/predicate',
            $object
        );

        $this->connector->deleteMatchingTriples(
            'http://example.org/graph',
            new Erfurt_Store_Adapter_Sparql_TriplePattern(
                'http://example.org/subject',
                'http://example.org/predicate',
                $object
            )
        );

        $this->assertEquals(1, $this->countTriples());
    }

    /**
     * Checks if the adapter can handle blank nodes.
     *
     * Blank nodes should not be treated as URIs and it should be possible
     * to use the SPARQL function isBLANK() to detect blank nodes.
     */
    public function testConnectorSupportsBlankNodes()
    {
        $this->insertTriple();
        $this->insertTriple('_:b1', 'http://example.org/predicate', array('value' => '_:b2', 'type' => 'bnode'));

        // Select the blank nodes.
        $query = 'SELECT ?subject ?predicate ?object '
               . 'FROM <http://example.org/graph> '
               . 'WHERE {'
               . '    ?subject ?predicate ?object . '
               . '    FILTER(isBLANK(?subject) && isBLANK(?object))'
               . '}';
        $result = $this->connector->query($query);

        $this->assertNumberOfRows(1, $result);
    }

    /**
     * Checks if the connector handles literals with umlauts correctly.
     */
    public function testConnectorHandlesUmlautsCorrectly()
    {
        $literalValue = 'hühü';
        $this->insertTriple(
            'http://example.org/subject',
            'http://example.org/predicate',
            array(
                'type' => 'literal',
                'value' => $literalValue
            )
        );

        $query  = 'SELECT ?object FROM <http://example.org/graph> WHERE { ?subject ?predicate ?object . }';
        $result = $this->connector->query($query);

        $this->assertNumberOfRows(1, $result);
        $row = array_shift($result['results']['bindings']);
        $value = array_shift($row);
        $this->assertEquals($literalValue, $value['value']);
    }

    /**
     * Checks if the callback that is passed to batch() is executed.
     */
    public function testBatchExecutesProvidedCallback()
    {
        $callback = $this->getMock('\stdClass', array('__invoke'));
        $callback->expects($this->once())
            ->method('__invoke');

        $this->connector->batch($callback);
    }

    /**
     * Ensures that a connector is passed as parameter to the batch
     * callback.
     */
    public function testBatchPassesConnectorAsParameter()
    {
        $callback = $this->getMock('\stdClass', array('__invoke'));
        $callback->expects($this->once())
            ->method('__invoke')
            ->with($this->isInstanceOf('\Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface'));

        $this->connector->batch($callback);
    }

    /**
     * Checks if batch() returns the result from the callback.
     */
    public function testBatchReturnsReturnsFromCallback()
    {
        $callback = function () {
            return 42;
        };

        $result = $this->connector->batch($callback);

        $this->assertEquals(42, $result);
    }

    /**
     * Checks if triples are successfully added in batch mode.
     */
    public function testBatchCanBeUsedToInsertTriples()
    {
        $addTriples = function ($connector) {
            PHPUnit_Framework_Assert::assertInstanceOf(
                '\Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface',
                $connector
            );
            /* @var $connector \Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface */
            $connector->addTriple(
                'http://example.org/graph',
                new Erfurt_Store_Adapter_Sparql_Triple(
                    'http://example.org/subject1',
                    'http://example.org/predicate1',
                    array(
                        'type' => 'uri',
                        'value' => 'http://example.org/object1'
                    )
                )
            );
            $connector->addTriple(
                'http://example.org/graph',
                new Erfurt_Store_Adapter_Sparql_Triple(
                    'http://example.org/subject2',
                    'http://example.org/predicate2',
                    array(
                        'type' => 'uri',
                        'value' => 'http://example.org/object2'
                    )
                )
            );
        };

        $this->connector->batch($addTriples);

        $this->assertEquals(2, $this->countTriples());
    }

    /**
     * Checks if single inserts still work after using the batch mode.
     */
    public function testSingleInsertWorksAfterBatchMode()
    {
        $addTriple = function ($connector) {
            PHPUnit_Framework_Assert::assertInstanceOf(
                '\Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface',
                $connector
            );
            /* @var $connector \Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface */
            $connector->addTriple(
                'http://example.org/graph',
                new Erfurt_Store_Adapter_Sparql_Triple(
                    'http://example.org/subject/' . uniqid('s', true),
                    'http://example.org/predicate',
                    array(
                        'type' => 'uri',
                        'value' => 'http://example.org/object'
                    )
                )
            );
        };

        // Insert 1 triple in batch mode...
        $this->connector->batch($addTriple);
        // ... and another one afterwards.
        $addTriple($this->connector);

        $this->assertEquals(2, $this->countTriples());
    }

    /**
     * Asserts that the provided result set has the structure of an extended
     * result.
     *
     * An extended result set contains a head with variable names and a set
     * of bindings:
     *
     *     array(
     *         'head' => array(
     *             'vars' => array(
     *                 // Contains the names of all variables that occur in the result set.
     *             )
     *         )
     *         'results' => array(
     *             'bindings' => array(
     *                 // Contains one entry for each result set row.
     *                 // Each entry contains the variable name as key and a set
     *                 // of additional information as value:
     *             )
     *         )
     *     )
     *
     * @param array(mixed)|mixed $resultSet
     */
    protected function assertExtendedResultStructure($resultSet)
    {
        $this->assertInternalType('array', $resultSet);

        // Check the variable declaration in the head.
        $this->assertArrayHasKey('head', $resultSet);
        $this->assertInternalType('array', $resultSet['head']);
        $this->assertArrayHasKey('vars', $resultSet['head']);
        $this->assertInternalType('array', $resultSet['head']['vars']);
        $this->assertContainsOnly('string', $resultSet['head']['vars']);

        // Check the result bindings.
        $this->assertArrayHasKey('results', $resultSet);
        $this->assertInternalType('array', $resultSet['results']);
        $this->assertArrayHasKey('bindings', $resultSet['results']);
        $this->assertInternalType('array', $resultSet['results']['bindings']);
        foreach ($resultSet['results']['bindings'] as $binding) {
            /* @var $binding array(array(string=>array(string=>mixed)) */
            $this->assertInternalType('array', $binding);
            foreach ($binding as $name => $valueDefinition) {
                /* @var $name string */
                /* @var $valueDefinition array(string=>mixed) */
                $this->assertInternalType('string', $name);
                $this->assertInternalType('array', $valueDefinition);
                $this->assertArrayHasKey('type', $valueDefinition);
                $this->assertArrayHasKey('value', $valueDefinition);
            }
        }
    }

    /**
     * Inserts the provided triple into the database.
     *
     * @param string $subjectIri
     * @param string $predicateIri
     * @param string|array(string=>string) $objectIriOrSpecification
     * @param string $graphIri
     */
    protected function insertTriple(
        $subjectIri = 'http://example.org/subject',
        $predicateIri = 'http://example.org/predicate',
        $objectIriOrSpecification = 'http://example.org/object',
        $graphIri = 'http://example.org/graph'
    )
    {
        if (is_array($objectIriOrSpecification)) {
            // Specification provided.
            $object = $objectIriOrSpecification;
        } else {
            // Object URI passed.
            $object = array(
                'value' => $objectIriOrSpecification,
                'type' => 'uri'
            );
        }
        $triple = new Erfurt_Store_Adapter_Sparql_Triple($subjectIri, $predicateIri, $object);
        $this->connector->addTriple($graphIri, $triple);
    }

    /**
     * Asserts that the provided (extended) result set contains
     * the expected number of result rows.
     *
     * @param integer $expected The expected number of rows.
     * @param mixed $resultSet
     */
    protected function assertNumberOfRows($expected, $resultSet)
    {
        $this->assertExtendedResultStructure($resultSet);
        $this->assertCount($expected, $resultSet['results']['bindings']);
    }

    /**
     * Creates the SPARQL connector that will be tested.
     *
     * @return \Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface
     */
    abstract protected function createConnector();

    /**
     * Counts the number of triples in the store.
     *
     * @return integer
     */
    abstract protected function countTriples();

}
