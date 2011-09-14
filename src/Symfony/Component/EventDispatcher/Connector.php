<?php
namespace Symfony\Component\EventDispatcher;

use Symfony\Component\DependencyInjection\ServiceStub;

/**
 * Holds a configurable events-to-listener map, while the associated listener can be lazy-loaded by setting it with a ServiceStub.
 *
 * @author  Dieter Peeters <peetersdiet@gmail.com>
 */
class Connector
{
    protected $map;
    protected $listener;
    protected $eventDispatcher;
    
    public function __construct($map = null, $listener = null) {
        $this->setSubscribedEvents($map);
        $this->setListener($listener);
    }
    
    public function setSubscribedEvents(array $map) {
        $this->map = $map;
        return $this;
    }
    
    public function getSubscribedEvents() {
        return $this->map;
    }
    
    public function setListener($listener) {
        if ($listener instanceof ConnectedListenerInterface) {
            $listener->addConnector($this);
        }
        $this->listener = $listener;
        return $this;
    }
    
    public function getListener() {
        if ($this->listener instanceof ServiceStub) {
            $this->setListener($this->listener->getService());
        }
        return $this->listener;
    }
    
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher = null) {
        $this->eventDispatcher = $eventDispatcher;
        return $this;
    }
    
    public function getEventDispatcher() {
        return $this->eventDispatcher;
    }
    
    public function isConnected() {
        return !empty($this->eventDispatcher);
    }
} 