<?php

/**
 * Represents a triple pattern.
 *
 * A triple pattern may contain concrete values for subject, predicate and object,
 * but it also allows null values, which indicate that every value is allowed at
 * that position.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 01.02.14
 */
class Erfurt_Store_Adapter_Sparql_TriplePattern
{

    /**
     * The subject URI or blank node identifier.
     *
     * Null if any value is allowed.
     *
     * @var string|null
     */
    protected $subject = null;

    /**
     * The predicate URI.
     *
     * Null if any value is allowed.
     *
     * @var string|null
     */
    protected $predicate = null;

    /**
     * The object definition (URI, blank node or literal).
     *
     * Null if any value is allowed.
     *
     * @var array(string=>string)|null
     */
    protected $object = array();

    /**
     * Creates a triple that contains the given components.
     *
     * Null can be passed to indicate that every value is allowed
     * at that position.
     *
     * @param string|null $subject
     * @param string|null $predicate
     * @param array(string=>string)|null $object
     */
    public function __construct($subject = null, $predicate = null, array $object = null)
    {
        $this->subject   = $subject;
        $this->predicate = $predicate;
        $this->object    = $object;
    }

    /**
     * Returns the subject.
     *
     * @return string|null
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Returns the predicate.
     *
     * @return string|null
     */
    public function getPredicate()
    {
        return $this->predicate;
    }

    /**
     * Returns the object definition.
     *
     * @return array(string=>string)|null
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Returns a string representation of the triple.
     *
     * The provided pattern defines how this representation looks like
     * and may contain placeholders like "?subject" or "?object".
     *
     * Example:
     *
     *     // Creates a representation that can be used as pattern in a SPARQL query.
     *     $representation = $pattern->format('?subject ?predicate ?object .');
     *
     * @param string $pattern
     * @return string The string representation of the triple.
     */
    public function format($pattern)
    {
        return strtr($pattern, $this->getPlaceholderValues());
    }

    /**
     * Returns the triple in a Turtle-like format.
     *
     * Example:
     *
     *     $triple = new Erfurt_Store_Adapter_Sparql_TriplePattern(
     *         'http://example.org/subject',
     *         'http://example.org/predicate',
     *         array('type' => 'uri', 'value' => 'http://example.org/object')
     *     );
     *     // Generates:
     *     // <http://example.org/subject> <http://example.org/predicate> <http://example.org/object> .
     *     $turtle = (string)$triple;
     *
     * Null placeholders will be represented as variable identifiers, for example "?subject".
     *
     * @return string
     */
    public function __toString()
    {
        return $this->format('?subject ?predicate ?object .');
    }

    /**
     * Returns a list of placeholders (keys) and their corresponding values.
     *
     * This list is used by the format() method.
     *
     * If available, the following placeholders can be used:
     *
     * - ?subject
     * - ?predicate
     * - ?object
     *
     * @return array(string=>string)
     */
    protected function getPlaceholderValues()
    {
        $placeholders = array();
        if ($this->subject !== null) {
            $type = (strpos($this->subject, '_:') === 0) ? 'bnode' : 'uri';
            $placeholders['?subject'] = $this->formatValue(array('type' => $type, 'value' => $this->subject));
        }
        if ($this->predicate !== null) {
            $placeholders['?predicate'] = $this->formatValue(array('type' => 'uri', 'value' => $this->predicate));
        }
        if ($this->object !== null) {
            $placeholders['?object'] = $this->formatValue($this->object);
        }
        return $placeholders;
    }

    /**
     * Uses the provided value specification to create a string representation.
     *
     * The specification must provide a type as well as a value entry.
     *
     * @param array(string=>string) $valueSpecification
     * @return string
     */
    protected function formatValue(array $valueSpecification)
    {
        switch ($valueSpecification['type']) {
            case 'bnode':
                return $valueSpecification['value'];
            case 'uri':
                return '<' . $valueSpecification['value'] . '>';
            case 'literal':
            default:
                return $this->buildLiteralString(
                    $valueSpecification['value'],
                    isset($valueSpecification['datatype']) ? $valueSpecification['datatype'] : null,
                    isset($valueSpecification['lang']) ? $valueSpecification['lang'] : null
                );
        }
    }

    /**
     * Builds literal strings that fulfill the requirements in the NTriples format.
     *
     * As NTriples is a subset of Turtle, which is a subset of Notation3, the generated
     * literal representation can also be used these contexts.
     *
     * @param string $value
     * @param string|null $dataType
     * @param string|null $language
     * @return string
     * @see http://www.w3.org/2001/sw/RDFCore/ntriples/#string
     */
    protected function buildLiteralString($value, $dataType, $language)
    {
        $literal = '"' . addcslashes($value, "\\\"\n\r\t") . '"';
        if (!empty($dataType)) {
            $literal .= '^^<' . $dataType . '>';
        } elseif (!empty($language)) {
            $literal .= '@' . $language;
        }
        return $literal;
    }

}
