<?php

namespace RomanStruk\SolrScoutEngine\Tests\Fakes;

use Solarium\Core\Client\Adapter\AdapterInterface;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Request;
use Solarium\Core\Client\Response;

/**
 * In-memory Solarium adapter: captures the outgoing request and returns a
 * canned response body so the engine can be tested without a live Solr.
 */
class FakeAdapter implements AdapterInterface
{
    public ?Request $lastRequest = null;

    /** @var Request[] */
    public array $requests = [];

    public string $body = '{"responseHeader":{"status":0,"QTime":0},"response":{"numFound":0,"start":0,"docs":[]}}';

    public function setResponseBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function execute(Request $request, Endpoint $endpoint): Response
    {
        $this->lastRequest = $request;
        $this->requests[] = $request;

        return new Response($this->body, ['HTTP/1.1 200 OK']);
    }

    public function lastUri(): string
    {
        return $this->lastRequest ? urldecode($this->lastRequest->getUri()) : '';
    }
}
