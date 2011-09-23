<?php
/**
 * PHPIMS
 *
 * Copyright (c) 2011 Christer Edvartsen <cogo@starzinger.net>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * * The above copyright notice and this permission notice shall be included in
 *   all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package PHPIMS
 * @subpackage Unittests
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011, Christer Edvartsen
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/christeredvartsen/phpims
 */

namespace PHPIMS;

use PHPIMS\Http\Request\RequestInterface;

/**
 * @package PHPIMS
 * @subpackage Unittests
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011, Christer Edvartsen
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/christeredvartsen/phpims
 */
class FrontControllerTest extends \PHPUnit_Framework_TestCase {
    /**
     * Front controller instance
     *
     * @var PHPIMS\FrontController
     */
    private $controller;

    private $publicKey;
    private $privateKey;

    /**
     * Set up method
     */
    public function setUp() {
        $this->publicKey = md5(microtime());
        $this->privateKey = md5(microtime());

        $config = array(
            'database' => $this->getMock('PHPIMS\Database\DatabaseInterface'),
            'storage' => $this->getMock('PHPIMS\Storage\StorageInterface'),
            'auth' => array(
                $this->publicKey => $this->privateKey,
            ),
        );
        $this->controller = new FrontController($config);
    }

    /**
     * Tear down method
     */
    public function tearDown() {
        $this->controller = null;
    }

    public function testResolveResourceWithImageRequest() {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('resolveResource');
        $method->setAccessible(true);
        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getType')->will($this->returnValue(RequestInterface::RESOURCE_IMAGE));
        $this->assertInstanceOf('PHPIMS\Resource\Image', $method->invoke($this->controller, $request));
    }

    public function testResolveResourceWithImagesRequest() {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('resolveResource');
        $method->setAccessible(true);
        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getType')->will($this->returnValue(RequestInterface::RESOURCE_IMAGES));
        $this->assertInstanceOf('PHPIMS\Resource\Images', $method->invoke($this->controller, $request));
    }

    public function testResolveResourceWithMetadataRequest() {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('resolveResource');
        $method->setAccessible(true);
        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getType')->will($this->returnValue(RequestInterface::RESOURCE_METADATA));
        $this->assertInstanceOf('PHPIMS\Resource\Metadata', $method->invoke($this->controller, $request));
    }

    /**
     * @expectedException PHPIMS\Exception
     * @expectedExceptionMessage Invalid request
     * @expectedExceptionCode 400
     */
    public function testResolveResourceWithInvalidRequest() {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('resolveResource');
        $method->setAccessible(true);
        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getType')->will($this->returnValue(RequestInterface::RESOURCE_UNKNOWN));
        $method->invoke($this->controller, $request);
    }

    /**
     * @expectedException PHPIMS\Exception
     * @expectedExceptionMessage Unknown public key
     * @expectedExceptionCode 400
     */
    public function testAuthWithUnknownPublicKey() {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('auth');
        $method->setAccessible(true);

        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getPublicKey')->will($this->returnValue('some unknown key'));

        $method->invoke($this->controller, $request);
    }

    /**
     * @expectedException PHPIMS\Exception
     * @expectedExceptionMessage Missing required authentication parameter: signature
     * @expectedExceptionCode 400
     */
    public function testAuthWithMissingSignature() {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('auth');
        $method->setAccessible(true);

        $query = $this->getMock('PHPIMS\Http\ParameterContainerInterface');
        $query->expects($this->any())->method('has')->with('signature')->will($this->returnValue(false));

        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getPublicKey')->will($this->returnValue($this->publicKey));
        $request->expects($this->once())->method('getQuery')->will($this->returnValue($query));

        $method->invoke($this->controller, $request);
    }

    /**
     * @expectedException PHPIMS\Exception
     * @expectedExceptionMessage Missing required authentication parameter: timestamp
     * @expectedExceptionCode 400
     */
    public function testAuthWithMissingTimestamp() {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('auth');
        $method->setAccessible(true);

        $query = $this->getMock('PHPIMS\Http\ParameterContainerInterface');
        $query->expects($this->at(0))->method('has')->with('signature')->will($this->returnValue(true));
        $query->expects($this->at(1))->method('has')->with('timestamp')->will($this->returnValue(false));

        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getPublicKey')->will($this->returnValue($this->publicKey));
        $request->expects($this->once())->method('getQuery')->will($this->returnValue($query));

        $method->invoke($this->controller, $request);
    }

    /**
     * @expectedException PHPIMS\Exception
     * @expectedExceptionMessage Invalid authentication timestamp format
     * @expectedExceptionCode 400
     */
    public function testAuthWithInvalidTimestampFormat() {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('auth');
        $method->setAccessible(true);

        $query = $this->getMock('PHPIMS\Http\ParameterContainerInterface');
        $query->expects($this->any())->method('has')->will($this->returnValue(true));
        $query->expects($this->once())->method('get')->with('timestamp')->will($this->returnValue('some string'));

        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getPublicKey')->will($this->returnValue($this->publicKey));
        $request->expects($this->once())->method('getQuery')->will($this->returnValue($query));

        $method->invoke($this->controller, $request);
    }

    /**
     * @expectedException PHPIMS\Exception
     * @expectedExceptionMessage Authentication timestamp has expired
     * @expectedExceptionCode 401
     */
    public function testAuthWithExpiredTimestamp() {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('auth');
        $method->setAccessible(true);

        $query = $this->getMock('PHPIMS\Http\ParameterContainerInterface');
        $query->expects($this->any())->method('has')->will($this->returnValue(true));
        $query->expects($this->once())->method('get')->with('timestamp')->will($this->returnValue('2011-01-01T01:00Z'));

        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getPublicKey')->will($this->returnValue($this->publicKey));
        $request->expects($this->once())->method('getQuery')->will($this->returnValue($query));

        $method->invoke($this->controller, $request);
    }

    /**
     * @expectedException PHPIMS\Exception
     * @expectedExceptionMessage Signature mismatch
     * @expectedExceptionCode 401
     */
    public function testAuthWithSignatureMismatch() {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('auth');
        $method->setAccessible(true);

        $timestamp = gmdate('Y-m-d\TH:i\Z');
        $signature = 'some signature';

        $query = $this->getMock('PHPIMS\Http\ParameterContainerInterface');
        $query->expects($this->any())->method('has')->will($this->returnValue(true));
        $query->expects($this->any())->method('get')->will($this->returnCallback(function($arg) use($timestamp, $signature) {
            if ($arg === 'timestamp') {
                return $timestamp;
            } else if ($arg === 'signature') {
                return $signature;
            }
        }));

        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getPublicKey')->will($this->returnValue($this->publicKey));
        $request->expects($this->once())->method('getQuery')->will($this->returnValue($query));

        $method->invoke($this->controller, $request);
    }

    public function testSuccessfulAuth() {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('auth');
        $method->setAccessible(true);

        $timestamp = gmdate('Y-m-d\TH:i\Z');
        $httpMethod = 'POST';
        $resource = md5(microtime()) . '.png/meta';
        $data = $httpMethod . $resource . $this->publicKey . $timestamp;

        // Generate the correct signature
        $signature = hash_hmac('sha256', $data, $this->privateKey, true);

        $query = $this->getMock('PHPIMS\Http\ParameterContainerInterface');
        $query->expects($this->any())->method('has')->will($this->returnValue(true));
        $query->expects($this->any())->method('get')->will($this->returnCallback(function($arg) use($timestamp, $signature) {
            if ($arg === 'timestamp') {
                return $timestamp;
            } else if ($arg === 'signature') {
                return base64_encode($signature);
            }
        }));

        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getPublicKey')->will($this->returnValue($this->publicKey));
        $request->expects($this->once())->method('getQuery')->will($this->returnValue($query));
        $request->expects($this->once())->method('getMethod')->will($this->returnValue($httpMethod));
        $request->expects($this->once())->method('getResource')->will($this->returnValue($resource));

        $method->invoke($this->controller, $request);
    }

    /**
     * @expectedException PHPIMS\Exception
     * @expectedExceptionMessage I'm a teapot!
     * @expectedExceptionCode 418
     */
    public function testHandleBrew() {
        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getMethod')->will($this->returnValue('BREW'));

        $response = $this->getMock('PHPIMS\Http\Response\ResponseInterface');

        $this->controller->handle($request, $response);
    }

    /**
     * @expectedException PHPIMS\Exception
     * @expectedExceptionMessage Unsupported HTTP method
     * @expectedExceptionCode 501
     */
    public function testHandleUnsupportedHttpMethod() {
        $request = $this->getMock('PHPIMS\Http\Request\RequestInterface');
        $request->expects($this->once())->method('getMethod')->will($this->returnValue('TRACE'));

        $response = $this->getMock('PHPIMS\Http\Response\ResponseInterface');

        $this->controller->handle($request, $response);
    }
}
