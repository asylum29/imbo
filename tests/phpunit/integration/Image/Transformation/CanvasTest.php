<?php
namespace ImboIntegrationTest\Image\Transformation;

use Imbo\Image\Transformation\Canvas;
use Imagick;

/**
 * @coversDefaultClass Imbo\Image\Transformation\Canvas
 */
class CanvasTest extends TransformationTests {
    protected function getTransformation() : Canvas {
        return new Canvas();
    }

    public function getCanvasParameters() : array {
        return [
            // free mode with only width
            [1000, null, 'free', 1000, 463],

            // free mode with only height
            [null, 1000, 'free', 665, 1000],

            // free mode where both sides are smaller than the original
            [200, 200, 'free', 200, 200],

            // free mode where height is smaller than the original
            [1000, 200, 'free', 1000, 200],

            // free mode where width is smaller than the original
            [200, 1000, 'free', 200, 1000],

            // center, center-x and center-y modes
            [1000, 1000, 'center', 1000, 1000],
            [1000, 1000, 'center-x', 1000, 1000],
            [1000, 1000, 'center-y', 1000, 1000],

            // center, center-x and center-y modes where one of the sides are smaller than the
            // original
            [1000, 200, 'center', 1000, 200],
            [200, 1000, 'center', 200, 1000],
            [1000, 200, 'center-x', 1000, 200],
            [1000, 200, 'center-y', 1000, 200],

            // center, center-x and center-y modes where both sides are smaller than the original
            [200, 200, 'center', 200, 200],
            [200, 200, 'center-x', 200, 200],
            [200, 200, 'center-y', 200, 200],
        ];
    }

    /**
     * @dataProvider getCanvasParameters
     */
    public function testTransformWithDifferentParameters(?int $width, ?int $height, string $mode, int $resultingWidth, int $resultingHeight) : void {
        $blob = file_get_contents(FIXTURES_DIR . '/image.png');

        $image = $this->createMock('Imbo\Model\Image');
        $image->expects($this->any())->method('getBlob')->will($this->returnValue($blob));
        $image->expects($this->any())->method('getWidth')->will($this->returnValue(665));
        $image->expects($this->any())->method('getHeight')->will($this->returnValue(463));
        $image->expects($this->any())->method('getExtension')->will($this->returnValue('png'));
        $image->expects($this->once())->method('setWidth')->with($resultingWidth)->will($this->returnValue($image));
        $image->expects($this->once())->method('setHeight')->with($resultingHeight)->will($this->returnValue($image));
        $image->expects($this->once())->method('hasBeenTransformed')->with(true);

        $imagick = new Imagick();
        $imagick->readImageBlob($blob);

        $this->getTransformation()->setImagick($imagick)->setImage($image)->transform([
            'width' => $width,
            'height' => $height,
            'mode' => $mode,
        ]);
    }
}