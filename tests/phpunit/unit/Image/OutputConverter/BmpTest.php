<?php
namespace ImboUnitTest\Image\OutputConverter;

use Imbo\Image\OutputConverter\Bmp;
use Imbo\Exception\OutputConverterException;
use PHPUnit\Framework\TestCase;
use ImagickException;

/**
 * @coversDefaultClass Imbo\Image\OutputConverter\Bmp
 */
class BmpTest extends TestCase {
    /**
     * @var Bmp
     */
    private $converter;

    /**
     * Set up the loader
     */
    public function setUp() : void {
        $this->converter = new Bmp();
    }

    /**
     * @covers ::getSupportedMimeTypes
     */
    public function testReturnsSupportedMimeTypes() {
        $types = $this->converter->getSupportedMimeTypes();

        $this->assertIsArray($types);

        $this->assertContains('image/bmp', array_keys($types));
    }

    /**
     * @covers ::convert
     */
    public function testCanConvertImage() {
        $extension = 'bmp';
        $mimeType = 'image/bmp';

        $imagick = $this->createMock('Imagick');
        $imagick->expects($this->once())
                ->method('setImageFormat')
                ->with($extension);

        $image = $this->createMock('Imbo\Model\Image');
        $image->expects($this->once())
              ->method('hasBeenTransformed')
              ->with(true);

        $this->assertNull($this->converter->convert($imagick, $image, $extension, $mimeType));
    }

    /**
     * @covers ::convert
     */
    public function testThrowsExceptionOnImagickFailure() {
        $extension = 'bmp';

        $imagick = $this->createMock('Imagick');
        $imagick->expects($this->once())
                ->method('setImageFormat')
                ->with($extension)
                ->will($this->throwException(new ImagickException('some error')));

        $this->expectExceptionObject(new OutputConverterException('some error', 400));
        $this->converter->convert(
            $imagick,
            $this->createMock('Imbo\Model\Image'),
            $extension,
            'image/bmp'
        );
    }
}