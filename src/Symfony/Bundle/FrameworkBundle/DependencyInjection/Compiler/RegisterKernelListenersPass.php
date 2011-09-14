<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ServiceStub;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class RegisterKernelListenersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('event_dispatcher')) {
            return;
        }

        $definition = $container->getDefinition('event_dispatcher');

        foreach ($container->findTaggedServiceIds('kernel.event_listener') as $id => $events) {
            foreach ($events as $event) {
                $priority = isset($event['priority']) ? $event['priority'] : 0;

                if (!isset($event['event'])) {
                    throw new \InvalidArgumentException(sprintf('Service "%s" must define the "event" attribute on "kernel.event_listener" tags.', $id));
                }

                if (!isset($event['method'])) {
                    $event['method'] = 'on'.preg_replace(array(
                        '/(?<=\b)[a-z]/ie',
                        '/[^a-z0-9]/i'
                    ), array('strtoupper("\\0")', ''), $event['event']);
                }

                $definition->addMethodCall('addListenerService', array($event['event'], array($id, $event['method']), $priority));
            }
        }

        foreach ($container->findTaggedServiceIds('kernel.event_subscriber') as $id => $attributes) {
            // We must assume that the class value has been correcly filled, even if the service is created by a factory
            $class = $container->getDefinition($id)->getClass();

            $refClass = new \ReflectionClass($class);
            $interface = 'Symfony\Component\EventDispatcher\EventSubscriberInterface';
            if (!$refClass->implementsInterface($interface)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
            }

            $definition->addMethodCall('addSubscriberService', array($id, $class));
        }
        
        foreach ($container->findTaggedServiceIds('kernel.event_connector') as $id => $attributes) {
            // We must assume that the class value has been correcly filled, even if the service is created by a factory
            $connectorDefinition = $container->getDefinition($id);
            $class = $connectorDefinition->getClass();
            
            $refClass = new \ReflectionClass($class);
            $connectorClass = 'Symfony\Component\EventDispatcher\Connector';
            if ($connectorClass != $refClass->name && !$refClass->isSubclassOf($connectorClass)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must be an instance of %s.', $id, $connectorClass));
            }
            
            if (!empty($attributes) && !empty($attributes[0]['listener'])) {
                $arguments = array(
                    new Reference('service_container'), 
                    $attributes[0]['listener']
                ); 
                $connectorDefinition->addMethodCall('setListener', array(new Definition('Symfony\Component\DependencyInjection\ServiceStub', $arguments)));
            }
            $definition->addMethodCall('addConnectorService', array($id, $class));
        }
    }
}
