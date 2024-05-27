<?php

namespace M6Web\Bundle\DaemonBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('m6_web_daemon');

        $treeBuilder
            ->getRootNode()
            ->children()
            ->arrayNode('iterations_events')
                ->prototype('array')
                    ->children()
                        ->integerNode('count')->min(1)->end()
                        ->scalarNode('name')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
