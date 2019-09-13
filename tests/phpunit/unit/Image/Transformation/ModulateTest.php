<?php
namespace ImboUnitTest\Image\Transformation;

use Imbo\Image\Transformation\Modulate;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Imbo\Image\Transformation\Modulate
 */
class ModulateTest extends TestCase {
    /**
     * @var Modulate
     */
    private $transformation;

    /**
     * Set up the transformation instance
     */
    public function setUp() : void {
        $this->transformation = new Modulate();
    }

    /**
     * Data provider
     *
     * @return array[]
     */
    public function getModulateParams() {
        return [
            'no params' => [
                [], 100, 100, 100,
            ],
            'some params' => [
                ['b' => 10, 's' => 50], 10, 50, 100,
            ],
            'all params' => [
                ['b' => 1, 's' => 2, 'h' => 3], 1, 2, 3,
            ],
        ];
    }

    /**
     * @dataProvider getModulateParams
     */
    public function testUsesDefaultValuesWhenParametersAreNotSpecified(array $params, $brightness, $saturation, $hue) {
        $image = $this->createMock('Imbo\Model\Image');

        $imagick = $this->createMock('Imagick');
        $imagick->expects($this->once())->method('modulateImage')->with($brightness, $saturation, $hue);

        $this->transformation
            ->setImage($image)
            ->setImagick($imagick)
            ->transform($params);
    }
}