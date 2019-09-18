<?php declare(strict_types=1);
namespace ImboUnitTest\Image;

use Imbo\Image\InputLoader\Basic;
use Imbo\Image\InputLoader\InputLoaderInterface;
use Imbo\Image\InputLoaderManager;
use Imbo\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @coversDefaultClass Imbo\Image\InputLoaderManager
 */
class InputLoaderManagerTest extends TestCase {
    /**
     * @var InputLoaderManager
     */
    private $manager;

    /**
     * Set up the manager
     */
    public function setUp() : void {
        $this->manager = new InputLoaderManager();
    }

    /**
     * @covers ::setImagick
     */
    public function testCanSetImagickInstance() : void {
        $this->assertSame($this->manager, $this->manager->setImagick($this->createMock('Imagick')));
    }

    /**
     * @covers ::addLoaders
     */
    public function testThrowsExceptionWhenRegisteringWrongLoader() : void {
        $this->expectExceptionObject(new InvalidArgumentException(
            'Given loader (stdClass) does not implement LoaderInterface',
            500
        ));
        $this->manager->addLoaders([new stdClass()]);
    }

    /**
     * @covers ::addLoaders
     */
    public function testCanAddLoadersAsStrings() : void {
        $this->assertSame($this->manager, $this->manager->addLoaders([
            new Basic(),
        ]));
    }

    /**
     * @covers ::registerLoader
     * @covers ::getExtensionFromMimeType
     */
    public function testCanGetExtensionFromMimeType() : void {
        $this->manager->addLoaders([
            new Basic(),
        ]);
        $this->assertSame('jpg', $this->manager->getExtensionFromMimeType('image/jpeg'));
        $this->assertSame('png', $this->manager->getExtensionFromMimeType('image/png'));
        $this->assertSame('gif', $this->manager->getExtensionFromMimeType('image/gif'));
        $this->assertSame('tif', $this->manager->getExtensionFromMimeType('image/tiff'));
    }

    /**
     * @covers ::registerLoader
     * @covers ::load
     */
    public function testCanRegisterAndUseLoaders() : void {
        $imagick = $this->createMock('Imagick');
        $mime = 'image/png';
        $blob = 'some data';

        $loader1 = $this->createMock(InputLoaderInterface::class);
        $loader1->expects($this->once())
                ->method('getSupportedMimeTypes')
                ->will($this->returnValue([$mime => 'png']));
        $loader1->expects($this->once())
                ->method('load')
                ->with($imagick, $blob, $mime)
                ->will($this->returnValue(false));

        $loader2 = $this->createMock(InputLoaderInterface::class);
        $loader2->expects($this->once())
                ->method('getSupportedMimeTypes')
                ->will($this->returnValue([$mime => 'png']));
        $loader2->expects($this->once())
                ->method('load')
                ->with($imagick, $blob, $mime)
                ->will($this->returnValue(null));

        $this->manager->setImagick($imagick)
                      ->registerLoader($loader2)
                      ->registerLoader($loader1);

        $this->assertSame(
            $imagick,
            $this->manager->load($mime, $blob)
        );
    }

    /**
     * @covers ::load
     */
    public function testManagerReturnsFalseWhenNoLoaderManagesToLoadTheImage() : void {
        $loader = $this->createConfiguredMock(InputLoaderInterface::class, [
            'getSupportedMimeTypes' => ['image/png' => 'png'],
            'load' => false,
        ]);

        $this->assertFalse(
            $this->manager->setImagick($this->createMock('Imagick'))
                          ->registerLoader($loader)
                          ->load('image/png', 'some data')
        );
    }

    /**
     * @covers ::load
     */
    public function testManagerReturnsNullWhenNoLoadersExist() : void {
        $this->assertNull($this->manager->load('image/png', 'some data'));
    }
}
