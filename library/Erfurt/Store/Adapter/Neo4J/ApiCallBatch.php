<?php

use Guzzle\Http\Message\EntityEnclosingRequestInterface;
use Guzzle\Http\Url;
use Guzzle\Service\Command\CommandInterface;

/**
 * Holds a collection of commands that are executed as batch.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 18.05.14
 */
class Erfurt_Store_Adapter_Neo4J_ApiCallBatch
{

    /**
     * List of jobs that will be executed as batch.
     *
     * @var array(array(string=>mixed))
     */
    protected $jobDefinitions = array();

    /**
     * Adds a command to the batch.
     *
     * @param CommandInterface $command
     * @return string Identifier that can be used to reference the result of the command in following commands.
     */
    public function addJob(CommandInterface $command)
    {
        $command->prepare();
        $request  = $command->getRequest();
        $url      = $request->getUrl(true);
        $urlParts = array(
            'path'     => substr($url->getPath(), strlen('/db/data')),
            'query'    => $url->getQuery(),
            'fragment' => $url->getFragment()
        );
        $jobDefinition = array(
            'method' => $request->getMethod(),
            'to'     => Url::buildUrl($urlParts),
            'id'     => count($this->jobDefinitions)
        );
        if ($request instanceof EntityEnclosingRequestInterface) {
            $body = (string)$request->getBody();
            if (preg_match('/\\\\u[a-zA-Z0-9]{4,4}/u', $body) > 0 && preg_match('/\\{[0-9]+\\}/u', $body) === 0) {
                // Workaround for problem that is documented here: https://github.com/neo4j/neo4j/issues/852
                // Batch processing may lead to error if the body contains some special characters.
                // Therefore, execute the command immediately if it does not depend on previous results.
                return $command->execute();
            }
            $jobDefinition['body'] = Zend_Json::decode($body, Zend_Json::TYPE_OBJECT);
        }
        $this->jobDefinitions[] = $jobDefinition;
        return sprintf('{%s}', $jobDefinition['id']);
    }

    /**
     * Serializes the calls.
     *
     * This serialization is used as request body.
     *
     * @returns string
     */
    public function __toString()
    {
        return Zend_Json::encode($this->jobDefinitions);
    }

}
