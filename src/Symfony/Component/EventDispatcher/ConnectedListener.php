<?php
namespace Symfony\Component\EventDispatcher;

/**
 * Listener that provides a backreference to it's connectors.
 *
 * @author  Dieter Peeters <peetersdiet@gmail.com>
 */
class ConnectedListener implements ConnectedListenerInterface
{
    protected $connectors = array();
    
    public function addConnector(Connector $connector)
    {
        $this->connectors[] = $connector;
        return $this;
    }
    
    public function getConnectors()
    {
        return $this->connectors;
    }
} 