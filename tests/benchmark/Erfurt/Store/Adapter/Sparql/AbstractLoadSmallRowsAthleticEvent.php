<?php

use Faker\Generator;

/**
 * Checks the performance when loading many (small) rows via SPARQL connector.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 02.02.14
 */
abstract class Erfurt_Store_Adapter_Sparql_AbstractLoadSmallRowsAthleticEvent
    extends Erfurt_Store_Adapter_Sparql_AbstractConnectorAthleticEvent
{

    /**
     * The number of triples that will be loaded.
     */
    const NUMBER_OF_TRIPLES = 1000;

    /**
     * Populates the store with triples.
     *
     * @param \Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface $connector
     * @param \Faker\Generator $faker
     */
    public function populateStore(\Erfurt_Store_Adapter_Sparql_SparqlConnectorInterface $connector, Generator $faker)
    {
        for ($i = 0; $i < static::NUMBER_OF_TRIPLES; $i++) {
            $triple = new Erfurt_Store_Adapter_Sparql_Triple(
                'http://example.org/person/' . $faker->uuid,
                'http://xmlns.com/foaf/0.1/name',
                array(
                    'type'  => 'literal',
                    'value' => $faker->name
                )
            );
            $connector->addTriple('http://example.org/performance', $triple);
        }
    }

    /**
     * Executes a query that loads 100 rows from the store.
     *
     * @Iterations 200
     */
    public function load100Rows()
    {
        $this->load(100);
    }

    /**
     * Executes a query that loads 500 rows from the store.
     *
     * @Iterations 200
     */
    public function load500Rows()
    {
        $this->load(500);
    }

    /**
     * Executes a query that loads 1000 rows from the store.
     *
     * @Iterations 200
     */
    public function load1000Rows()
    {
        $this->load(1000);
    }

    /**
     * Queries $limit rows from the store.
     *
     * @param integer $limit
     */
    protected function load($limit)
    {
        $query = 'SELECT ?person ?name '
               . 'FROM <http://example.org/performance> '
               . 'WHERE { '
               . '    ?person <http://xmlns.com/foaf/0.1/name> ?name . '
               . '} '
               . 'LIMIT ' . $limit;
        $this->connector->query($query);
    }

}
