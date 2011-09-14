<?php
namespace Symfony\Component\DependencyInjection;

/**
 * Stub used to lazy load services.
 *
 * @author  Dieter Peeters <peetersdiet@gmail.com>
 */
class ServiceStub extends ContainerAware
{
    protected $id;
    
    public function __construct($container,$id)
    {
        $this->setContainer($container);
        $this->setId($id);
    }
    
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }
    
    public function getService()
    {
        return $this->container->get($this->id);
    }
}