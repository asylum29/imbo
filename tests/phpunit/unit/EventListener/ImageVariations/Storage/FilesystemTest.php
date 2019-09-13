<?php
namespace ImboUnitTest\EventListener\ImageVariations\Storage;

use Imbo\EventListener\ImageVariations\Storage\Filesystem;
use Imbo\Exception\StorageException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Imbo\EventListener\ImageVariations\Storage\Filesystem
 */
class FilesystemTest extends TestCase {
    /**
     * Setup method
     */
    public function setUp() : void {
        if (!class_exists('org\bovigo\vfs\vfsStream')) {
            $this->markTestSkipped('This testcase requires vfsStream to run');
        }
    }

    /**
     * @covers ::storeImageVariation
     */
    public function testThrowsExceptionWhenNotAbleToWriteToDirectory() {
        $dir = 'unwritableDirectory';

        // Create the virtual directory with no permissions
        vfsStream::setup($dir, 0);

        $adapter = new Filesystem(['dataDir' => vfsStream::url($dir)]);
        $this->expectExceptionObject(new StorageException(
            'Could not store image variation (directory not writable)',
            500
        ));
        $adapter->storeImageVariation('pub', 'img', 'blob', 700);
    }

    /**
     * @covers ::deleteImageVariations
     */
    public function testDoesNotThrowWhenDeletingNonExistantVariation() {
        $dir = 'basedir';
        vfsStream::setup($dir);

        $adapter = new Filesystem(['dataDir' => vfsStream::url($dir)]);
        $this->assertFalse($adapter->deleteImageVariations('pub', 'img'));
    }
}