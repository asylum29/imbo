<?php
namespace ImboUnitTest\Http;

use Imbo\Http\ContentNegotiation;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Imbo\Http\ContentNegotiation
 */
class ContentNegotiationTest extends TestCase {
    /**
     * @var ContentNegotiation
     */
    private $cn;

    /**
     * Set up
     */
    public function setUp() : void {
        $this->cn = new ContentNegotiation();
    }

    /**
     * @return array[]
     */
    public function getIsAcceptableData() {
        return [
            ['image/png', ['image/png' => 1, 'image/*' => 0.9], 1],
            ['image/png', ['text/html' => 1, '*/*' => 0.9], 0.9],
            ['image/png', ['text/html' => 1], false],
            ['image/jpeg', ['application/json' => 1, 'text/*' => 0.9], false],
            ['application/json', ['text/html;level=1' => 1, 'text/html' => 0.9, '*/*' => 0.8, 'text/html;level=2' => 0.7, 'text/*' => 0.9], 0.8],
        ];
    }

    /**
     * @dataProvider getIsAcceptableData
     * @covers Imbo\Http\ContentNegotiation::isAcceptable
     */
    public function testCanCheckIfAMimeTypeIsAcceptable($mimeType, $acceptable, $result) {
        $this->assertSame($result, $this->cn->isAcceptable($mimeType, $acceptable));
    }

    /**
     * @return array[]
     */
    public function getMimeTypes() {
        return [
            [['image/png', 'image/gif'], ['image/*' => 1], 'image/png'],
            [['image/png', 'image/gif'], ['image/png' => 0.9, 'image/gif' => 1], 'image/gif'],
            [['image/png', 'image/gif'], ['application/json' => 1, 'image/*' => 0.9], 'image/png'],
            [['image/png', 'image/gif'], ['application/json' => 1], false],
        ];
    }

    /**
     * @dataProvider getMimeTypes
     * @covers Imbo\Http\ContentNegotiation::bestMatch
     * @covers Imbo\Http\ContentNegotiation::isAcceptable
     */
    public function testCanPickTheBestMatchFromASetOfMimeTypes($mimeTypes, $acceptable, $result) {
        $this->assertSame($result, $this->cn->bestMatch($mimeTypes, $acceptable));
    }
}