<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Units\DependencyInjection;

use M6Web\Bundle\DaemonBundle\DependencyInjection\M6WebDaemonExtension;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class M6WebDaemonExtensionTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testDefaultConfig(): void
    {
        $container = $this->getContainerForConfiguration('default-config');
        $container->compile();

        $this->assertIsArray($iterationEvents = $container->getParameter('m6web_daemon.iterations_events'));
        $this->assertCount(1, $iterationEvents);
        $this->assertArrayHasKey('count', $iterationEvent = $iterationEvents[0]);
        $this->assertArrayHasKey('name', $iterationEvent);
        $this->assertIsInt($iterationEvent['count']);
        $this->assertEquals(1,  $iterationEvent['count']);
        $this->assertIsString($iterationEvent['name']);
        $this->assertEquals('test',  $iterationEvent['name']);
    }

    /**
     * @throws \Exception
     */
    public function testMissingCountConfig(): void
    {
        $container = $this->getContainerForConfiguration('bad-parameters-config');
        $this->expectException(InvalidTypeException::class);
        $container->compile();
    }

    /**
     * @throws \Exception
     */
    protected function getContainerForConfiguration(string $fixtureName): ContainerBuilder
    {
        $extension = new M6WebDaemonExtension();

        $container = new ContainerBuilder();
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
        $container->registerExtension($extension);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../Fixtures/config/'));
        $loader->load($fixtureName.'.yml');

        return $container;
    }
}
