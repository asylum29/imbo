<?php
namespace ImboUnitTest\Http\Request;

use Imbo\Http\Request\Request;
use Imbo\Exception\InvalidArgumentException;
use Imbo\Router\Route;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Imbo\Http\Request\Request
 */
class RequestTest extends TestCase {
    /**
     * @var Request
     */
    private $request;

    /**
     * Set up the request
     */
    public function setUp() : void {
        $this->request = new Request();
    }

    /**
     * @covers Imbo\Http\Request\Request::getTransformations
     */
    public function testGetTransformationsWithNoTransformationsPresent() {
        $this->assertEquals([], $this->request->getTransformations());
    }

    /**
     * @covers Imbo\Http\Request\Request::getTransformations
     */
    public function testGetTransformationsWithCorrectOrder() {
        $query = [
            't' => [
                'flipHorizontally',
                'flipVertically',
            ],
        ];

        $request = new Request($query);
        $transformations = $request->getTransformations();
        $this->assertEquals('flipHorizontally', $transformations[0]['name']);
        $this->assertEquals('flipVertically', $transformations[1]['name']);
    }

    /**
     * @covers Imbo\Http\Request\Request::getTransformations
     */
    public function testGetTransformations() {
        $query = [
            't' => [
                // Valid transformations with all options
                'border:color=fff,width=2,height=2,mode=inline',
                'compress:level=90',
                'crop:x=1,y=2,width=3,height=4',
                'resize:width=100,height=100',

                // Transformations with no options
                'flipHorizontally',
                'flipVertically',

                // The same transformation can be applied multiple times
                'resize:width=50,height=75',

                // We handle zero-values appropriately
                'border:color=bf1942,height=100,mode=outbound,width=0',
                'border:color=000,height=5,width=0,mode=outbound'
            ],
        ];

        $request = new Request($query);
        $transformations = $request->getTransformations();
        $this->assertIsArray($transformations);
        $this->assertSame(count($query['t']), count($transformations));

        $this->assertEquals(['color' => 'fff', 'width' => 2, 'height' => 2, 'mode' => 'inline'], $transformations[0]['params']);
        $this->assertEquals(['level' => '90'], $transformations[1]['params']);
        $this->assertEquals(['x' => 1, 'y' => 2, 'width' => 3, 'height' => 4], $transformations[2]['params']);
        $this->assertEquals(['width' => 100, 'height' => 100], $transformations[3]['params']);
        $this->assertEquals([], $transformations[4]['params']);
        $this->assertEquals([], $transformations[5]['params']);
        $this->assertEquals(['width' => 50, 'height' => 75], $transformations[6]['params']);

        $this->assertEquals([
            'color' => 'bf1942',
            'height' => 100,
            'mode' => 'outbound',
            'width' => 0,
        ], $transformations[7]['params']);

        $this->assertEquals([
            'color' => '000',
            'height' => 5,
            'width' => 0,
            'mode' => 'outbound',
        ], $transformations[8]['params']);
    }

    /**
     * @covers Imbo\Http\Request\Request::getImageIdentifier
     */
    public function testSetGetImageIdentifier() {
        $identifier = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $this->assertNull($this->request->getImageIdentifier());

        $route = new Route();
        $this->request->setRoute($route);

        $this->assertNull($this->request->getImageIdentifier());
        $route->set('imageIdentifier', $identifier);
        $this->assertSame($identifier, $this->request->getImageIdentifier());
    }

    /**
     * @covers Imbo\Http\Request\Request::getExtension
     */
    public function testSetGetExtension() {
        $extension = 'jpg';
        $this->assertNull($this->request->getExtension());

        $route = new Route();
        $this->request->setRoute($route);

        $this->assertNull($this->request->getExtension());
        $route->set('extension', $extension);
        $this->assertSame($extension, $this->request->getExtension());
    }

    /**
     * @covers Imbo\Http\Request\Request::getUser
     */
    public function testSetGetUser() {
        $user = 'christer';
        $this->assertNull($this->request->getUser());

        $route = new Route();
        $this->request->setRoute($route);

        $this->assertNull($this->request->getUser());
        $route->set('user', $user);
        $this->assertSame($user, $this->request->getUser());
    }

    /**
     * @covers Imbo\Http\Request\Request::getPublicKey
     */
    public function testSetGetPublicKeyThroughRoute() {
        $pubkey = 'pubkey';
        $this->assertNull($this->request->getPublicKey());

        $route = new Route();
        $this->request->setRoute($route);

        $this->assertNull($this->request->getPublicKey());
        $route->set('user', $pubkey);
        $this->assertSame($pubkey, $this->request->getPublicKey());
    }

    /**
     * @covers Imbo\Http\Request\Request::getPublicKey
     */
    public function testSetGetPublicKeyThroughQuery() {
        $pubkey = 'pubkey';
        $this->assertNull($this->request->getPublicKey());

        $this->request->query->set('publicKey', $pubkey);
        $this->assertSame($pubkey, $this->request->getPublicKey());
    }

    /**
     * @covers Imbo\Http\Request\Request::getPublicKey
     */
    public function testSetGetPublicKeyThroughHeader() {
        $pubkey = 'pubkey';
        $this->assertNull($this->request->getPublicKey());

        $this->request->headers->set('X-Imbo-PublicKey', $pubkey);
        $this->assertSame($pubkey, $this->request->getPublicKey());
    }

    /**
     * @covers Imbo\Http\Request\Request::getImage
     * @covers Imbo\Http\Request\Request::setImage
     */
    public function testCanSetAndGetAnImage() {
        $image = $this->createMock('Imbo\Model\Image');
        $this->assertSame($this->request, $this->request->setImage($image));
        $this->assertSame($image, $this->request->getImage());
    }

    /**
     * @covers Imbo\Http\Request\Request::getRoute
     * @covers Imbo\Http\Request\Request::setRoute
     */
    public function testCanSetAndGetARoute() {
        $this->assertNull($this->request->getRoute());
        $route = $this->createMock('Imbo\Router\Route');
        $this->assertSame($this->request, $this->request->setRoute($route));
        $this->assertSame($route, $this->request->getRoute());
    }

    /**
     * @covers Imbo\Http\Request\Request::getTransformations
     */
    public function testRequiresTransformationsToBeSpecifiedAsAnArray() {
        $request = new Request([
            't' => 'desaturate',
        ]);
        $this->expectExceptionObject(new InvalidArgumentException(
            'Transformations must be specifed as an array',
            400
        ));
        $request->getTransformations();
    }

    /**
     * @covers Imbo\Http\Request\Request::getTransformations
     */
    public function testDoesNotGenerateWarningWhenTransformationIsNotAString() {
        $query = [
            't' => [
                [
                    'flipHorizontally',
                    'flipVertically',
                ],
            ],
        ];

        $request = new Request($query);
        $this->expectExceptionObject(new InvalidArgumentException(
            'Invalid transformation',
            400
        ));
        $request->getTransformations();
    }

    public function getQueryStrings() {
        return [
            'transformation with params' => [
                't[]=thumbnail:width=100',
                't[]=thumbnail:width=100',
            ],
            'transformation with params, encoded' => [
                't%5B0%5D%3Dthumbnail%3Awidth%3D100',
                't[0]=thumbnail:width=100',
            ],
        ];
    }

    /**
     * @dataProvider getQueryStrings
     */
    public function testGetRawUriDecodesUri($queryString, $expectedQueryString) {
        $request = new Request([], [], [], [], [], [
            'SERVER_NAME' => 'imbo',
            'SERVER_PORT' => 80,
            'QUERY_STRING' => $queryString,
        ]);

        $uri = $request->getRawUri();
        $this->assertSame($expectedQueryString, substr($uri, strpos($uri, '?') + 1));
    }
}