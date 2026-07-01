<?php

namespace RomanStruk\SolrScoutEngine;

use Solarium\Core\Event\Events;
use Solarium\Core\Plugin\AbstractPlugin;

class BasicDebug extends AbstractPlugin
{
    protected $start;

    protected array $output = [];

    /**
     * This method is called if the plugin is removed from the client.
     */
    public function deinitPlugin()
    {
        $dispatcher = $this->client->getEventDispatcher();
        $dispatcher->removeListener(Events::PRE_EXECUTE, [$this, 'preExecute']);
        $dispatcher->removeListener(Events::POST_EXECUTE, [$this, 'postExecute']);
    }

    public function display(): array
    {
        return $this->output;
    }

    public function preCreateRequest()
    {
        $this->timer('preCreateRequest');
    }

    public function postCreateRequest()
    {
        $this->timer('postCreateRequest');
    }

    // This method uses the available param(s) (see plugin abstract class).
    // You can access or modify data this way.
    public function preExecuteRequest($event)
    {
        $this->timer('preExecuteRequest');

        if ($event->getRequest()->getMethod() === 'POST') {
            $this->output[] = 'Raw data: '.urldecode($event->getRequest()->getRawData());
            return;
        }

        $this->output[] = 'Request URI: '.urldecode($event->getRequest()->getUri());
    }

    public function postExecuteRequest()
    {
        $this->timer('postExecuteRequest');
    }

    public function preCreateResult()
    {
        $this->timer('preCreateResult');
    }

    public function postCreateResult()
    {
        $this->timer('postCreateResult');
    }

    public function preExecute()
    {
        $this->timer('preExecute');
    }

    public function postExecute()
    {
        $this->timer('postExecute');
    }

    public function preCreateQuery()
    {
        $this->timer('preCreateQuery');
    }

    public function postCreateQuery()
    {
        $this->timer('postCreateQuery');
    }

    /**
     * This method is called when the plugin is registered with the client.
     */
    protected function initPluginType()
    {
        $this->start = microtime(true);

        $dispatcher = $this->client->getEventDispatcher();
        $dispatcher->addListener(Events::PRE_EXECUTE_REQUEST, [$this, 'preExecuteRequest']);
        $dispatcher->addListener(Events::POST_EXECUTE_REQUEST, [$this, 'postExecuteRequest']);
    }

    protected function timer($event)
    {
        $time = round(microtime(true) - $this->start, 5);
        $this->output[] = '['.$time.'] '.$event;
    }
}
