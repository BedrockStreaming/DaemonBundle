<?php
/**
 *
 */

namespace M6Web\Bundle\DaemonBundle\DependencyInjection;


use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;


class M6WebDaemonBundleExtension extends Extension {

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);


        foreach ($config['iterations_events'] as $event)
        {

        }
//        $container->setParameter('m6_se_api.platforms', $config['platforms']);
    }

} 