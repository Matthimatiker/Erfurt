<?php

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Contains logic for handling triple column types in Oracle databases.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 15.12.13
 */
class Erfurt_Store_Adapter_Oracle_Doctrine_TripleType extends Type
{

    /**
     * The name of this type.
     */
    const TRIPLE = 'sdo_rdf_triple_s';

    /**
     * Gets the SQL declaration snippet for a field of this type.
     *
     * @param array $fieldDeclaration The field declaration.
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform The currently used database platform.
     * @return string
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'SDO_RDF_TRIPLE_S';
    }

    /**
     * Returns the database types that are mapped to this type definition.
     *
     * @param AbstractPlatform $platform
     * @return array(string)
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform)
    {
        return array($this->getName());
    }

    /**
     * Gets the name of this type.
     *
     * @return string
     */
    public function getName()
    {
        return static::TRIPLE;
    }

}
