<?php
namespace ImboIntegrationTest\Image\Transformation;

use Imbo\Model\Image;
use Imbo\Image\Transformation\AutoRotate;
use Imagick;

/**
 * @coversDefaultClass Imbo\Image\Transformation\AutoRotate
 */
class AutoRotateTest extends TransformationTests {
    protected function getTransformation() : AutoRotate {
        return new AutoRotate();
    }

    public function getFiles() : array {
        return [
            'orientation1.jpeg' => [FIXTURES_DIR . '/autoRotate/orientation1.jpeg', false, false],
            'orientation2.jpeg' => [FIXTURES_DIR . '/autoRotate/orientation2.jpeg', false, true],
            'orientation3.jpeg' => [FIXTURES_DIR . '/autoRotate/orientation3.jpeg', false, true],
            'orientation4.jpeg' => [FIXTURES_DIR . '/autoRotate/orientation4.jpeg', false, true],
            'orientation5.jpeg' => [FIXTURES_DIR . '/autoRotate/orientation5.jpeg', true, true],
            'orientation6.jpeg' => [FIXTURES_DIR . '/autoRotate/orientation6.jpeg', true, true],
            'orientation7.jpeg' => [FIXTURES_DIR . '/autoRotate/orientation7.jpeg', true, true],
            'orientation8.jpeg' => [FIXTURES_DIR . '/autoRotate/orientation8.jpeg', true, true],
        ];
    }

    /**
     * @dataProvider getFiles
     */
    public function testAutoRotatesAllOrientations(string $file, bool $changeDimensions, bool $transformed) : void {
        $colorValues = [
            [
                'x' => 0,
                'y' => 0,
                'color' => 'rgb(128,63,193)'
            ],
            [
                'x' => 0,
                'y' => 1000,
                'color' => 'rgb(254,57,126)'
            ],
            [
                'x' => 1000,
                'y' => 0,
                'color' => 'rgb(127,131,194)'
            ],
            [
                'x' => 1000,
                'y' => 1000,
                'color' => 'rgb(249,124,192)'
            ],
        ];

        /**
         * Load the image, perform the auto rotate tranformation and check that the color codes in
         * the four corner pixels match the known color values as defined in $colorValues
         */
        $blob = file_get_contents($file);

        $image = $this->createMock('Imbo\Model\Image');

        if ($changeDimensions) {
            $image->expects($this->once())->method('setWidth')->with(350)->will($this->returnValue($image));
            $image->expects($this->once())->method('setHeight')->with(350)->will($this->returnValue($image));
        } else {
            $image->expects($this->never())->method('setWidth');
            $image->expects($this->never())->method('setHeight');
        }

        if ($transformed) {
            $image->expects($this->once())->method('hasBeenTransformed')->with(true);
        } else {
            $image->expects($this->never())->method('hasBeenTransformed');
        }

        // Perform the auto rotate transformation on the image
        $imagick = new Imagick();
        $imagick->readImageBlob($blob);

        $this->getTransformation()->setImagick($imagick)
                                  ->setImage($image)
                                  ->transform([]);

        // Do assertion comparison on the color values
        foreach ($colorValues as $pixelInfo) {
            $pixelValue = $imagick->getImagePixelColor($pixelInfo['x'], $pixelInfo['y'])
                                  ->getColorAsString();

            $this->assertStringEndsWith($pixelInfo['color'], $pixelValue);
        }
    }
}