<?php

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Describes the configuration for the factory that uses a service container
 * to create an adapter.
 *
 * @author Matthias Molitor <molitor@informatik.uni-bonn.de>
 * @since 23.02.14
 */
class Erfurt_Store_Adapter_Container_ContainerConfiguration implements ConfigurationInterface
{

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder();
        $root = $builder->root('container');
        $root
            ->children()
                ->arrayNode('configs')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('service')
                    ->isRequired()
                ->end()
                ->scalarNode('cache_directory')
                    ->isRequired()
                ->end()
                ->arrayNode('parameters')
                    ->prototype('variable')->end()
                ->end()
            ->end();
        return $builder;
    }

}