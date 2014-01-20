<?php

/**
 * Contains several helper methods for working with Oracle result sets.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 31.12.13
 */
class Erfurt_Store_Adapter_Oracle_ResultConverter_Util
{

    /**
     * Returns an array with the names of the variables that occur
     * in the provided result set.
     *
     * The variable names are returned as they are in the result set,
     * which means that they are in upper case.
     *
     * @param array(array(string=>string|null)) $resultSet
     * @return array(string)
     */
    public static function getVariables(array $resultSet)
    {
        if (count($resultSet) === 0) {
            // Result set is empty, no variables are bound.
            return array();
        }
        $firstRow = current($resultSet);
        $variables = array();
        foreach (array_keys($firstRow) as $column) {
            /* @var $column string */
            if (strpos($column, '$') === false) {
                // This is not a meta data, but a variable column.
                $variables[] = $column;
            }
        }
        return $variables;
    }

    /**
     * Converts the provided value into a native PHP type.
     *
     * The provided data type URI determines which conversion is used.
     * The existing data types are documented at {@link http://www.w3.org/TR/xmlschema-2/#built-in-datatypes}.
     * Currently only a subset of these is supported by this method.
     *
     * Example:
     *
     *     // Returns the integer value 42:
     *     $dataType = 'http://www.w3.org/2001/XMLSchema#int';
     *     $value    = Erfurt_Store_Adapter_Oracle_ResultConverter_Util::convertToType('42', $dataType);
     *
     * @param string $value
     * @param string|null $dataType
     * @return mixed
     */
    public static function convertToType($value, $dataType)
    {
        switch ($dataType) {
            case 'http://www.w3.org/2001/XMLSchema#boolean':
                return ($value === 'true');
            case 'http://www.w3.org/2001/XMLSchema#integer':
            case 'http://www.w3.org/2001/XMLSchema#int':
                return (int)$value;
            case 'http://www.w3.org/2001/XMLSchema#decimal':
            case 'http://www.w3.org/2001/XMLSchema#float':
            case 'http://www.w3.org/2001/XMLSchema#double':
                return (double)$value;
            case 'http://www.w3.org/2001/XMLSchema#string':
            case null:
            default:
                return $value;
        }
    }

    /**
     * Uses underscores to encode all uppercase characters in the provided variable name.
     *
     * Example:
     *
     *     $variable = 'camelCasedVariable';
     *     // Returns 'camel_cased_variable'
     *     $encoded = Erfurt_Store_Adapter_Oracle_ResultConverter_Util::encodeUpperCaseCharacters($variable);
     *
     * @param string $variable
     * @return string
     */
    public static function encodeVariableName($variable)
    {
        return preg_replace_callback('/[A-Z_]/', function (array $match) {
            return '_' . strtolower($match[0]);
        }, $variable);
    }

    /**
     * Restores upper case characters in the provided variable name.
     *
     * Example:
     *
     *     $variable = 'upper_case';
     *     // Returns 'upperCase'
     *     $decoded = Erfurt_Store_Adapter_Oracle_ResultConverter_Util::decodeVariableName($variable);
     *
     * @param string $name
     * @return string
     */
    public static function decodeVariableName($name)
    {
        $name = strtolower($name);
        return preg_replace_callback('/_([a-z_])/', function (array $match) {
            return strtoupper($match[1]);
        }, $name);
    }

    /**
     * Accepts an already escaped literal value and uses double quotes instead
     * of single quotes to enclose it.
     *
     * This is necessary as the Oracle store shows some fails to process literals
     * that are enclosed by single quotes under certain conditions (for example
     * if a custom data type is assigned to the literal).
     *
     * If the literal value is already enclosed by double quotes, then it
     * will be returned without any modification.
     *
     * Example:
     *
     *     $value = "'Hello \"world\"!'";
     *     // Returns '"Hello \\\"world\\\"!'.
     *     $converted = \Erfurt_Store_Adapter_Oracle_ResultConverter_Util::convertSingleToDoubleQuotes($value);
     *
     * @param string $escapedLiteralValue
     * @return string
     */
    public static function convertSingleToDoubleQuotes($escapedLiteralValue)
    {
        $isSingleQuoteLiteral = strpos($escapedLiteralValue, "'") === 0;
        if (!$isSingleQuoteLiteral) {
            // This literal does not use single quotes, no conversion is needed.
            return $escapedLiteralValue;
        }
        // Rewrite single quotes to double quotes.
        $isLongLiteral = strpos($escapedLiteralValue, "'''") === 0;
        // Single quotes do not have to be escaped in a double quote literal...
        $escapedLiteralValue = str_replace('\\\'', '\'', $escapedLiteralValue);
        // ... but of course the double quotes must be quoted now.
        $escapedLiteralValue = addcslashes($escapedLiteralValue, '"');
        $delimiter    = str_repeat("'", ($isLongLiteral ? 3 : 1));
        $newDelimiter = str_repeat('"', ($isLongLiteral ? 3 : 1));
        // Replace the delimiter at the beginning...
        $escapedLiteralValue = substr_replace($escapedLiteralValue, $newDelimiter, 0, strlen($newDelimiter));
        // ... and the one at the end, which might be followed by a data type definition.
        $escapedLiteralValue = substr_replace(
            $escapedLiteralValue,
            $newDelimiter,
            strrpos($escapedLiteralValue, $delimiter),
            strlen($newDelimiter)
        );
        return $escapedLiteralValue;
    }

}
