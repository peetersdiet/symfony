<?php
namespace Symfony\Component\EventDispatcher;

interface ConnectedListenerInterface
{
    public function addConnector(Connector $connector);
    
    public function getConnectors();
} 