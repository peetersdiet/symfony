<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;

/**
 * Lazily loads listeners and subscribers from the dependency injection
 * container
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 * @author Dieter Peeters <peetersdiet@gmail.com>
 */
class ContainerAwareEventDispatcher extends EventDispatcher
{
    /**
     * The container from where services are loaded
     * @var ContainerInterface
     */
    private $container;

    /**
     * The service IDs of the event listeners and subscribers
     * @var array
     */
    private $listenerIds = array();

    /**
     * The services registered as listeners
     * @var array
     */
    private $listeners = array();

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Adds a service as event listener
     *
     * @param string   $eventName Event for which the listener is added
     * @param array    $callback  The service ID of the listener service & the method
     *                            name that has to be called
     * @param integer  $priority  The higher this value, the earlier an event listener
     *                            will be triggered in the chain.
     *                            Defaults to 0.
     */
    public function addListenerService($eventName, $callback, $priority = 0)
    {
        if (!is_array($callback) || 2 !== count($callback)) {
            throw new \InvalidArgumentException('Expected an array("service", "method") argument');
        }

        $this->listenerIds[$eventName][] = array($callback[0], $callback[1], $priority);
    }

    /**
    * @see EventDispatcherInterface::hasListeners
    */
    public function hasListeners($eventName = null)
    {
        if (null === $eventName) {
            return (Boolean) count($this->listenerIds) || (Boolean) count($this->listeners);
        }

        if (isset($this->listenerIds[$eventName])) {
            return true;
        }

        return parent::hasListeners($eventName);
    }

    /**
    * @see EventDispatcherInterface::getListeners
    */
    public function getListeners($eventName = null)
    {
        if (null === $eventName) {
            foreach ($this->listenerIds as $serviceEventName => $listners) {
                $this->lazyLoad($serviceEventName);
            }
        } else {
            $this->lazyLoad($eventName);
        }

        return parent::getListeners($eventName);
    }

    /**
     * Adds a service as event subscriber
     *
     * If this service is created by a factory, its class value must be correctly filled.
     * The service's class must implement Symfony\Component\EventDispatcher\EventSubscriberInterface.
     *
     * @param string $serviceId The service ID of the subscriber service
     * @param string $class     The service's class name
     */
    public function addSubscriberService($serviceId, $class)
    {
        $refClass = new \ReflectionClass($class);
        $interface = 'Symfony\Component\EventDispatcher\EventSubscriberInterface';
        if (!$refClass->implementsInterface($interface)) {
            throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $serviceId, $interface));
        }

        foreach ($class::getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                $this->listenerIds[$eventName][] = array($serviceId, $params, 0);
            } else {
                $this->listenerIds[$eventName][] = array($serviceId, $params[0], $params[1]);
            }
        }
    }

    /**
     * Adds a connector service
     * 
     * The connector should be an instance of {@see Symfony\Component\EventDispatcher\Connector}.
     *  
     * @param string $serviceId the identifier of the connector service
     */
    public function addConnectorService($serviceId)
    {
        $connector = $this->container->get($serviceId);
        $connectorClass = 'Symfony\Component\EventDispatcher\Connector';
        if (!$connector instanceof $connectorClass) {
            throw new \InvalidArgumentException(sprintf('Service "%s" must be an instance of %s.', $serviceId, $connectorClass));
        }
        
        $dispatcher = $this;
        $setter = function($eventName, $method) use ($dispatcher, $serviceId) {
            if (is_string($method)) {
                $dispatcher->addListenerService($eventName, array($serviceId, $method));
            } else {
                $dispatcher->addListenerService($eventName, array($serviceId, $method[0]), $method[1]);
            }
        };
        
        foreach ($connector->getSubscribedEvents() as $eventName => $params) {
            if (is_array($params) && is_array(reset($params))) {
                foreach ($params as $param) {
                    $setter($eventName, $param);
                }
            } else {
                $setter($eventName, $params);
            }
        }
        
        $connector->setEventDispatcher($this);
    }
    
    /**
     * {@inheritDoc}
     *
     * Lazily loads listeners for this event from the dependency injection
     * container.
     *
     * @throws \InvalidArgumentException if the service is not defined
     */
    public function dispatch($eventName, Event $event = null)
    {
        $this->lazyLoad($eventName);

        parent::dispatch($eventName, $event);
    }

    /**
     * Lazily loads listeners for this event from the dependency injection
     * container.
     *
     * @param string $eventName The name of the event to dispatch. The name of
     *                          the event is the name of the method that is
     *                          invoked on listeners.
     */
    protected function lazyLoad($eventName)
    {
        if (isset($this->listenerIds[$eventName])) {
            foreach ($this->listenerIds[$eventName] as $args) {
                list($serviceId, $method, $priority) = $args;
                $listener = $this->container->get($serviceId);

                $connectorClass = 'Symfony\Component\EventDispatcher\Connector';
                if ($listener instanceof $connectorClass) {
                    $listener = $listener->getListener();
                }
                    
                $key = $serviceId.'.'.$method;
                if (!isset($this->listeners[$eventName][$key])) {
                    $this->addListener($eventName, array($listener, $method), $priority);
                } elseif ($listener !== $this->listeners[$eventName][$key]) {
                    $this->removeListener($eventName, array($this->listeners[$eventName][$key], $method));
                    $this->addListener($eventName, array($listener, $method), $priority);
                }

                $this->listeners[$eventName][$key] = $listener;
            }
        }
    }
}
