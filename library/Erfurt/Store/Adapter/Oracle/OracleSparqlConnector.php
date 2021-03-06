<?php

use Doctrine\DBAL\Connection;

/**
 * Connector for the Oracle Triple Store (named Semantic and Graph option).
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 02.02.14
 */
class Erfurt_Store_Adapter_Oracle_OracleSparqlConnector
    implements \Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface
{

    /**
     * The database connection that is used.
     *
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection = null;

    /**
     * Rewrites SPARQL queries in such a way that the Oracle database
     * can handle them.
     *
     * @var \Erfurt_Store_Adapter_Oracle_SparqlRewriter
     */
    protected $sparqlRewriter = null;

    /**
     * Wraps a SPARQL query into SQL.
     *
     * @var \Erfurt_Store_Adapter_Oracle_SparqlWrapper
     */
    protected $sparqlWrapper = null;

    /**
     * The loader that is used to retrieve CLOB values.
     *
     * @var \Erfurt_Store_Adapter_Oracle_ClobLiteralLoader
     */
    protected $clobLoader = null;

    /**
     * Buffer for triples that will be inserted.
     *
     * @var \Erfurt_Store_Adapter_Sparql_QuadBuffer
     */
    protected $buffer = null;

    /**
     * The number of combined insert operations in batch mode.
     *
     * @var integer
     */
    protected $batchSize = null;

    /**
     * Creates a connector that uses the provided database connection to interact
     * with the Oracle Triple Store.
     *
     * @param Connection $connection
     * @param integer $batchSize Number of combined inserts in batch mode.
     */
    public function __construct(Connection $connection, $batchSize = 100)
    {
        $this->connection     = $connection;
        $this->sparqlRewriter = new \Erfurt_Store_Adapter_Oracle_SparqlRewriter();
        $this->sparqlWrapper  = new \Erfurt_Store_Adapter_Oracle_SparqlWrapper(
            $this->getModelName(),
            array($this->connection, 'quote'),
            array($this->connection, 'quoteIdentifier')
        );
        $this->clobLoader = new Erfurt_Store_Adapter_Oracle_ClobLiteralLoader($connection);
        // Triples are buffered and the batch processor is used to store them.
        $this->batchSize = $batchSize;
        $batchProcessor  = new Erfurt_Store_Adapter_Oracle_CombinedInsertBatchProcessor($connection);
        $this->buffer    = new Erfurt_Store_Adapter_Sparql_QuadBuffer(array($batchProcessor, 'persist'));

    }

    /**
     * Adds the provided triple to the data store.
     *
     * @param string $graphIri
     * @param Erfurt_Store_Adapter_Sparql_Triple $triple
     */
    public function addTriple($graphIri, \Erfurt_Store_Adapter_Sparql_Triple $triple)
    {
        $this->buffer->add(Erfurt_Store_Adapter_Sparql_Quad::create($graphIri, $triple));
    }

    /**
     * Executes the provided SPARQL query and returns its results.
     *
     * The results of an ASK query must be returned as boolean.
     *
     * If the query produces a result set, then it must be returned as array
     * in extended format.
     * The extended format each value contains additional information about
     * its type and properties such as the language:
     *
     *     array(
     *         'head' => array(
     *             'vars' => array(
     *                 // Contains the names of all variables that occur in the result set.
     *                 'variable1',
     *                 'variable2'
     *             )
     *         )
     *         'results' => array(
     *             'bindings' => array(
     *                 // Contains one entry for each result set row.
     *                 // Each entry contains the variable name as key and a set
     *                 // of additional information as value:
     *                 array(
     *                     'variable1' => array(
     *                         'value' => 'http://example.org',
     *                         'type'  => 'uri'
     *                     ),
     *                     'variable2' => array(
     *                         'value' => 'Hello world!',
     *                         'type'  => 'literal'
     *                     )
     *                 )
     *             )
     *         )
     *     )
     *
     * @param string $sparqlQuery
     * @return array|boolean
     */
    public function query($sparqlQuery)
    {
        $statement = $this->createSparqlStatement($sparqlQuery);
        $statement->execute();
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $this->formatResultSet($results, $sparqlQuery);
    }

    /**
     * Deletes all triples in the given graph that match the provided pattern.
     *
     * @param string $graphIri
     * @param Erfurt_Store_Adapter_Sparql_TriplePattern $pattern
     * @return integer The number of deleted triples.
     */
    public function deleteMatchingTriples($graphIri, Erfurt_Store_Adapter_Sparql_TriplePattern $pattern)
    {
        $params = array(
            'modelAndGraph' => strtoupper($this->getModelName()) . ':<' . $graphIri . '>'
        );
        $builder = $this->connection->createQueryBuilder()
                        ->delete('erfurt_semantic_data', 'd')
                        ->where('d.triple.GET_MODEL() = :modelAndGraph');
        if ($pattern->getSubject() !== null) {
            $builder->andWhere('d.triple.GET_SUBJECT() = :subject');
            $params['subject'] = '<' . $pattern->getSubject() . '>';
        }
        if ($pattern->getPredicate() !== null) {
            $builder->andWhere('d.triple.GET_PROPERTY() = :predicate');
            $params['predicate'] = '<' . $pattern->getPredicate() . '>';
        }
        if ($pattern->getObject() !== null) {
            $builder->andWhere('d.triple.GET_TRIPLE().object = :object');
            $params['object'] = Erfurt_Store_Adapter_Oracle_ResultConverter_Util::buildLiteralFromSpec(
                $pattern->getObject()
            );
        }
        $query     = $builder->getSQL();
        $statement = $this->connection->prepare($query);
        $statement->execute($params);
        return $statement->rowCount();
    }

    /**
     * Accepts a callback function and processes it in batch mode.
     *
     * In batch mode the connector can decide to optimize the execution
     * for example by delaying inserts or wrapping the whole task
     * into a transaction.
     *
     * However, using the batch mode does *not* guarantee transactional
     * behavior.
     *
     * The callback receives the connector itself as argument, which
     * can be used to issue commands:
     *
     *     $connector->batch(function (\Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface $batchConnector) {
     *         $batchConnector->addTriple(
     *             'http://example.org',
     *             new \Erfurt_Store_Adapter_Sparql_Triple(
     *                 'http://example.org/subject1',
     *                 'http://example.org/predicate1',
     *                 'http://example.org/object1'
     *             );
     *         );
     *         $batchConnector->addTriple(
     *             'http://example.org',
     *             new \Erfurt_Store_Adapter_Sparql_Triple(
     *                 'http://example.org/subject2',
     *                 'http://example.org/predicate2',
     *                 'http://example.org/object2'
     *             );
     *         );
     *     });
     *
     * Finally, the batch() method returns the result of the provided callback:
     *
     *     // Result contains 42.
     *     $result = $connector->batch(function () {
     *         return 42;
     *     });
     *
     * @param mixed $callback A callback function.
     * @return mixed
     */
    public function batch($callback)
    {
        // Perform changes in a transaction to ensure, that the full set
        // of changes is written to disk at once instead of one commit per
        // insert or delete.
        $result    = null;
        $connector = $this;
        $buffer    = $this->buffer;
        $buffer->setSize($this->batchSize);
        $this->connection->transactional(function () use ($callback, $connector, $buffer, &$result) {
            $result = call_user_func($callback, $connector);
            $buffer->flush();
            $buffer->setSize(1);
        });
        return $result;
    }

    /**
     * Checks if the provided SPARQL query is an ASK query.
     *
     * @param string $query
     * @return boolean
     */
    protected function isAskQuery($query)
    {
        if (strpos($query, 'ASK') === false) {
            // Query does not even contain the ASK keyword, no further
            // detection required.
            return false;
        }
        $parser = new Erfurt_Sparql_Parser();
        $info   = $parser->parse($query);
        return $info->getResultForm() === 'ask';
    }

    /**
     * Creates a statement that is used to perform a SPARQL query.
     *
     * @param string $sparqlQuery The SPARQL query.
     * @return \Doctrine\DBAL\Driver\Statement
     */
    protected function createSparqlStatement($sparqlQuery)
    {
        $rewrittenSparqlQuery = $this->rewriteSparql($sparqlQuery);
        $sql = $this->sparqlWrapper->wrap($rewrittenSparqlQuery);
        return $this->connection->prepare($sql);
    }

    /**
     * Rewrites the given SPARQL query to prepare it for execution
     * by the Oracle database.
     *
     * Prefixes variables to avoid problems with reserved SQL keywords like "group"
     * and encodes variable names to be able to restore upper case characters in
     * the result set.
     *
     * @param string $query
     * @return string
     */
    protected function rewriteSparql($query)
    {
        return $this->sparqlRewriter->rewrite(Erfurt_Sparql_Parser::uncomment($query));
    }

    /**
     * Returns the name of the semantic model that is used.
     *
     * @return string
     */
    protected function getModelName()
    {
        return $this->connection->getUsername() . '_erfurt';
    }

    /**
     * Formats the provided result set, which means that keys
     * are normalized and that unnecessary elements are removed.
     *
     * @param array(string=>string) $results
     * @param string $query The SPARQL query.
     * @return array(string=>string)
     */
    protected function formatResultSet($results, $query)
    {
        if ($this->isAskQuery($query)) {
            $converter = new Erfurt_Store_Adapter_ResultConverter_CompositeConverter(array(
                new Erfurt_Store_Adapter_Oracle_ResultConverter_RawToTypedConverter($this->clobLoader),
                new Erfurt_Store_Adapter_ResultConverter_ScalarConverter()
            ));
        } else {
            $converter = new Erfurt_Store_Adapter_ResultConverter_CompositeConverter(array(
                new Erfurt_Store_Adapter_Oracle_ResultConverter_RawToTypedConverter($this->clobLoader),
                new Erfurt_Store_Adapter_Oracle_ResultConverter_RawToExtendedConverter()
            ));
        }
        return $converter->convert($results);
    }

}
