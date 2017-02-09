<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Units\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use mageekguy\atoum\test;

class M6WebDaemonExtension extends test
{
    public function testDefaultConfig()
    {
        $container = $this->getContainerForConfiguration('default-config');
        $container->compile();
        $this
            ->array($iterationEvents = $container->getParameter('m6web_daemon.iterations_events'))
                ->hasSize(1)
            ->array($iterationEvent = current($iterationEvents))
                ->hasKeys(['count', 'name'])
            ->integer($iterationEvent['count'])
                ->isEqualTo(1)
            ->string($iterationEvent['name'])
                ->isEqualTo('test')
        ;
    }

    public function testMissingCountConfig()
    {
        $container = $this->getContainerForConfiguration('bad-parameters-config');
        $this
            ->exception(function () use ($container) {
                $container->compile();
            })->isInstanceOf('Symfony\Component\Config\Definition\Exception\InvalidTypeException')
        ;
    }

    protected function getContainerForConfiguration($fixtureName)
    {
        $className = $this->getTestedClassName();
        $extension = new $className();

        $container = new ContainerBuilder();
        $container->set('event_dispatcher', new \mock\Symfony\Component\EventDispatcher\EventDispatcherInterface());
        $container->registerExtension($extension);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../Fixtures/'));
        $loader->load($fixtureName.'.yml');

        return $container;
    }
}
