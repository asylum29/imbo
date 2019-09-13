<?php
namespace ImboUnitTest\Resource;

use Imbo\Resource\Index;

/**
 * @coversDefaultClass Imbo\Resource\Index
 */
class IndexTest extends ResourceTests {
    /**
     * @var Index
     */
    private $resource;

    private $request;
    private $response;
    private $event;
    private $responseHeaders;

    protected function getNewResource() : Index {
        return new Index();
    }

    public function setUp() : void {
        $this->request = $this->createMock('Imbo\Http\Request\Request');
        $this->response = $this->createMock('Imbo\Http\Response\Response');
        $this->responseHeaders = $this->createMock('Symfony\Component\HttpFoundation\ResponseHeaderBag');
        $this->event = $this->createMock('Imbo\EventManager\Event');
        $this->event->expects($this->any())->method('getRequest')->will($this->returnValue($this->request));
        $this->event->expects($this->any())->method('getResponse')->will($this->returnValue($this->response));

        $this->resource = $this->getNewResource();
    }

    /**
     * @covers Imbo\Resource\Index::get
     */
    public function testSupportsHttpGet() : void {
        $this->request->expects($this->once())->method('getSchemeAndHttpHost')->will($this->returnValue('http://imbo'));
        $this->request->expects($this->once())->method('getBaseUrl')->will($this->returnValue(''));
        $this->response->expects($this->once())->method('setModel')->with($this->isInstanceOf('Imbo\Model\ArrayModel'));
        $this->response->expects($this->once())->method('setMaxAge')->with(0)->will($this->returnSelf());
        $this->response->expects($this->once())->method('setPrivate');
        $this->event->expects($this->any())->method('getConfig')->will($this->returnValue(['indexRedirect' => null]));

        $responseHeaders = $this->createMock('Symfony\Component\HttpFoundation\ResponseHeaderBag');
        $responseHeaders->expects($this->once())->method('addCacheControlDirective')->with('no-store');

        $this->response->headers = $responseHeaders;

        $this->resource->get($this->event);
    }

    public function testRedirectsIfConfigurationOptionHasBeenSet() : void {
        $url = 'http://imbo.io';
        $this->event->expects($this->any())->method('getConfig')->will($this->returnValue(['indexRedirect' => $url]));

        $responseHeaders = $this->createMock('Symfony\Component\HttpFoundation\ResponseHeaderBag');
        $responseHeaders->expects($this->once())->method('set')->with('Location', $url);

        $this->response->headers = $responseHeaders;
        $this->response->expects($this->once())->method('setStatusCode')->with(307);
        $this->response->expects($this->never())->method('setModel');

        $this->resource->get($this->event);
    }
}