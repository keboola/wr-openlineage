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
                    ->cannotBeEmpty()
                ->end()
                    ->scalarNode('openlineage_api_endpoint')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('created_time_from')
                    ->isRequired()
                    ->cannotBeEmpty()
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

        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $node
            ->children()
                ->booleanNode('enabled')
                    ->isRequired()
                ->end()
                ->scalarNode('#key_private')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('ssh_host')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('user')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
            ->end()
        ;

        return $node;
    }
}
