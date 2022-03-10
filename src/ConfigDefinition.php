<?php

declare(strict_types=1);

namespace Keboola\OpenLineageWriter;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ConfigDefinition extends BaseConfigDefinition
{
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
                ->scalarNode('openlineage_api_url')
                    ->isRequired()
                ->end()
                ->scalarNode('created_time_from')
                    ->isRequired()
                ->end()
                ->booleanNode('job_name_as_config')
                    ->defaultFalse()
                ->end()
                ->append($this->addSshNode())
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }

    public function addSshNode(): NodeDefinition
    {
        $builder = new TreeBuilder('ssh');
        /** @var ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        $node
            ->children()
                ->booleanNode('enabled')->end()
                ->scalarNode('#key_private')->end()
                ->scalarNode('ssh_host')->end()
                ->scalarNode('user')->end()
            ->end()
        ;

        return $node;
    }
}
