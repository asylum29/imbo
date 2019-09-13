<?php
namespace ImboUnitTest\Image\Transformation;

use Imbo\Http\Response\Response;
use Imbo\Image\Transformation\DrawPois;
use Imbo\Model\Image;
use Imbo\Exception\TransformationException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Imbo\Image\Transformation\DrawPois
 */
class DrawPoisTest extends TestCase {
    /**
     * @covers Imbo\Image\Transformation\DrawPois::transform
     */
    public function testDoesNotModifyImageIfNoPoisAreFound() {
        $image = $this->createMock('Imbo\Model\Image');
        $database = $this->createMock('Imbo\Database\DatabaseInterface');
        $database->expects($this->once())->method('getMetadata')->will($this->returnValue([]));

        $event = $this->createMock('Imbo\EventManager\Event');
        $event->expects($this->any())->method('getDatabase')->will($this->returnValue($database));

        $image->expects($this->never())->method('hasBeenTransformed');

        $transformation = new DrawPois();
        $transformation->setEvent($event)->setImage($image)->transform([]);
    }

    /**
     * @covers Imbo\Image\Transformation\DrawPois::transform
     */
    public function testDoesNotModifyImageIfNoPoiMetadataKeyIsNotAnArray() {
        $image = $this->createMock('Imbo\Model\Image');
        $database = $this->createMock('Imbo\Database\DatabaseInterface');
        $database->expects($this->once())->method('getMetadata')->will($this->returnValue(['poi' => 'wat']));

        $event = $this->createMock('Imbo\EventManager\Event');
        $event->expects($this->any())->method('getDatabase')->will($this->returnValue($database));

        $image->expects($this->never())->method('hasBeenTransformed');

        $transformation = new DrawPois();
        $transformation->setEvent($event)->setImage($image)->transform([]);
    }

    /**
     * @covers Imbo\Image\Transformation\DrawPois::transform
     */
    public function testThrowsExceptionOnInvalidPoi() {
        $image = $this->createMock('Imbo\Model\Image');
        $database = $this->createMock('Imbo\Database\DatabaseInterface');
        $database->expects($this->once())->method('getMetadata')->will($this->returnValue([
            'poi' => [['foo' => 'bar']]
        ]));

        $event = $this->createMock('Imbo\EventManager\Event');
        $event->expects($this->any())->method('getDatabase')->will($this->returnValue($database));

        $image->expects($this->never())->method('hasBeenTransformed');

        $transformation = new DrawPois();
        $this->expectExceptionObject(new TransformationException(
            'Point of interest had neither `width` and `height` nor `cx` and `cy`'
        ));
        $transformation->setEvent($event)->setImage($image)->transform([]);
    }

    /**
     * @covers Imbo\Image\Transformation\DrawPois::transform
     */
    public function testDrawsSameAmountOfTimesAsPoisArePresent() {
        $image = $this->createMock('Imbo\Model\Image');
        $database = $this->createMock('Imbo\Database\DatabaseInterface');
        $database->expects($this->once())->method('getMetadata')->will($this->returnValue([
            'poi' => [[
                'x' => 362,
                'y' => 80,
                'cx' => 467,
                'cy' => 203,
                'width' => 210,
                'height' => 245
            ], [
                'x' => 74,
                'y' => 237,
                'cx' => 98,
                'cy' => 263,
                'width' => 48,
                'height' => 51
            ], [
                'cx' => 653,
                'cy' => 185
            ]]
        ]));

        $event = $this->createMock('Imbo\EventManager\Event');
        $event->expects($this->any())->method('getDatabase')->will($this->returnValue($database));

        $image->expects($this->once())->method('hasBeenTransformed')->with(true);

        $imagick = $this->createMock('Imagick');
        $imagick->expects($this->exactly(3))->method('drawImage');

        $transformation = new DrawPois();
        $transformation
            ->setEvent($event)
            ->setImage($image)
            ->setImagick($imagick)
            ->transform([]);
    }
}