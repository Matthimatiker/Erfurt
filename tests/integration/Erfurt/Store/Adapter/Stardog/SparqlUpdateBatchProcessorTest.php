<?php

/**
 * Tests the SPARQL update batch processor.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 08.03.14
 * @group Stardog
 */
class Erfurt_Store_Adapter_Stardog_SparqlUpdateBatchProcessorTest
    extends Erfurt_Store_Adapter_Stardog_AbstractBatchProcessorTestCase
{

    /**
     * Checks if the processor stores a quad whose object literal is equal to the
     * subject URI correctly (with object as literal).
     */
    public function testPersistStoresQuadWithLiteralsThatEqualsSubjectUriCorrectly()
    {
        $this->markTestSkipped('There is currently no workaround for this issue. Should be fixed in Stardog.');
    }

    /**
     * Creates the batch processor that is used in the tests.
     *
     * @return Erfurt_Store_Adapter_Sparql_BatchProcessorInterface
     */
    protected function createProcessor()
    {
        $client = $this->helper->getDataAccessClient();
        return new Erfurt_Store_Adapter_Stardog_SparqlUpdateBatchProcessor($client);
    }

}
