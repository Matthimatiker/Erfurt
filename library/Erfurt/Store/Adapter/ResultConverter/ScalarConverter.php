<?php

/**
 * Extracts a single scalar value from a result set.
 *
 * This converter returns always the first value in the first row
 * of the provided result set.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 31.12.13
 */
class Erfurt_Store_Adapter_ResultConverter_ScalarConverter
    implements \Erfurt_Store_Adapter_ResultConverter_ResultConverterInterface
{

    /**
     * Extracts a single value from the provided result set.
     *
     * Returns null if the result set is empty.
     *
     * @param array(array(string=>string)) $resultSet
     * @return string|boolean|integer|double|null
     * @throws \Erfurt_Store_Adapter_ResultConverter_Exception If conversion is not possible.
     */
    public function convert($resultSet)
    {
        if (!is_array($resultSet)) {
            $message = 'Expected array for conversion.';
            throw new Erfurt_Store_Adapter_ResultConverter_Exception($message);
        }
        if (count($resultSet) === 0) {
            // The result set is empty.
            return null;
        }
        // Return the first value in the first row.
        return current(current($resultSet));
    }

}
