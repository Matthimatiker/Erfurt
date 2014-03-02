<?php

/**
 * Connector for the Stardog triple store {@link http://stardog.com/}.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 02.03.14
 */
class Erfurt_Store_Adapter_Stardog_StardogSparqlConnector
    implements Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface
{

    /**
     * The API client that is used to interact with the triple store.
     *
     * @var Erfurt_Store_Adapter_Stardog_ApiClient
     */
    protected $client = null;

    /**
     * Creates a SPARQL connector that uses the provided API client
     * to interact with the store.
     *
     * @param Erfurt_Store_Adapter_Stardog_ApiClient $client
     */
    public function __construct(Erfurt_Store_Adapter_Stardog_ApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Adds the provided triple to the data store.
     *
     * @param string $graphIri
     * @param Erfurt_Store_Adapter_Sparql_Triple $triple
     */
    public function addTriple($graphIri, \Erfurt_Store_Adapter_Sparql_Triple $triple)
    {
        $id = $this->client->beginTransaction();
        $this->client->add(array(
            'graph-uri'      => $graphIri,
            'transaction-id' => $id,
            'triples'        => (string)$triple
        ));
        $this->client->commitTransaction(array('transaction-id' => $id));
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
        $result = $this->client->query(array('query' => $sparqlQuery));
        if ($result instanceof SimpleXMLElement) {
            $result->registerXPathNamespace('sparql', 'http://www.w3.org/2005/sparql-results#');
            $trueNodes = $result->xpath("sparql:boolean[text() = 'true']");
            return count($trueNodes) > 0;
        }
        return $result;
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
        $condition = 'WHERE { '
                   . '    GRAPH <%s> { '
                   . '        %s '
                   . '    } '
                   . '}';
        $condition  = sprintf($condition, $graphIri, (string)$pattern);
        // Determine how many triples will be affected by the delete operation.
        $countQuery = 'SELECT (COUNT(*) AS ?affected) ' . $condition;
        $results    = $this->query($countQuery);
        // Remove the matching queries.
        $deleteQuery = 'DELETE ' . $condition;
        $this->query($deleteQuery);
        return (int)$results['results']['bindings'][0]['affected']['value'];
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
        return call_user_func($callback, $this);
    }

}
