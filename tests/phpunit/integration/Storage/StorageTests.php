<?php
namespace ImboIntegrationTest\Storage;

use Imbo\Exception\StorageException;
use Imbo\Storage\StorageInterface;
use DateTime;
use PHPUnit\Framework\TestCase;

abstract class StorageTests extends TestCase {
    /**
     * @var Imbo\Storage\StorageInterface
     */
    private $driver;

    /**
     * @var string
     */
    private $user = 'key';

    /**
     * @var string
     */
    private $imageIdentifier = '9cb263819af35064af0b6665a1b0fddd';

    /**
     * Binary image data
     *
     * @var string
     */
    private $imageData;

    /**
     * Get the driver we want to test
     *
     * @return StorageInterface
     */
    abstract protected function getDriver();

    /**
     * Get the currently instanced, active driver in inherited tests
     */
    protected function getDriverActive() : StorageInterface {
        return $this->driver;
    }

    /**
     * Get the user name in inherited tests
     *
     * @return string
     */
    protected function getUser() {
        return $this->user;
    }

    /**
     * Get the imageIdentifier in inherited tests
     *
     * @return string
     */
    protected function getImageIdentifier() {
        return $this->imageIdentifier;
    }

    /**
     * Set up
     */
    public function setUp() : void {
        $this->imageData = file_get_contents(FIXTURES_DIR . '/image.png');
        $this->driver = $this->getDriver();
    }

    public function testStoreAndGetImage() {
        $this->assertTrue(
            $this->driver->store($this->user, $this->imageIdentifier, $this->imageData),
            'Could not store initial image'
        );

        $this->assertSame(
            $this->imageData,
            $this->driver->getImage($this->user, $this->imageIdentifier),
            'Image data is out of sync'
        );
    }

    public function testStoreSameImageTwice() {
        $this->assertTrue(
            $this->driver->store($this->user, $this->imageIdentifier, $this->imageData),
            'Could not store initial image'
        );

        $this->assertInstanceOf(
            DateTime::class,
            $lastModified1 = $this->driver->getLastModified($this->user, $this->imageIdentifier),
            'Last modified of the first image is not a DateTime instance'
        );

        clearstatcache();
        sleep(1);

        $this->assertTrue(
            $this->driver->store($this->user, $this->imageIdentifier, $this->imageData),
            'Could not store image a second time'
        );

        $this->assertInstanceOf(
            DateTime::class,
            $lastModified2 = $this->driver->getLastModified($this->user, $this->imageIdentifier),
            'Last modified of the second image is not a DateTime instance'
        );

        $this->assertTrue(
            $lastModified2 > $lastModified1,
            'Last modification timestamp of second image is not greater than the one of the first image'
        );
    }

    public function testStoreDeleteAndGetImage() {
        $this->assertTrue(
            $this->driver->store($this->user, $this->imageIdentifier, $this->imageData),
            'Could not store initial image'
        );

        $this->assertTrue(
            $this->driver->delete($this->user, $this->imageIdentifier),
            'Could not delete image'
        );

        $this->expectExceptionObject(new StorageException('File not found', 404));

        $this->driver->getImage($this->user, $this->imageIdentifier);
    }

    public function testDeleteImageThatDoesNotExist() {
        $this->expectExceptionObject(new StorageException('File not found', 404));
        $this->driver->delete($this->user, $this->imageIdentifier);
    }

    public function testGetImageThatDoesNotExist() {
        $this->expectExceptionObject(new StorageException('File not found', 404));
        $this->driver->getImage($this->user, $this->imageIdentifier);
    }

    public function testGetLastModifiedOfImageThatDoesNotExist() {
        $this->expectExceptionObject(new StorageException('File not found', 404));
        $this->driver->getLastModified($this->user, $this->imageIdentifier);
    }

    public function testGetLastModified() {
        $this->assertTrue(
            $this->driver->store($this->user, $this->imageIdentifier, $this->imageData),
            'Could not store initial image'
        );
        $this->assertInstanceOf(
            DateTime::class,
            $this->driver->getLastModified($this->user, $this->imageIdentifier),
            'Last modification is not an instance of DateTime'
        );
    }

    public function testCanCheckIfImageAlreadyExists() {
        $this->assertFalse(
            $this->driver->imageExists($this->user, $this->imageIdentifier),
            'Image is not supposed to exist'
        );

        $this->assertTrue(
            $this->driver->store($this->user, $this->imageIdentifier, $this->imageData),
            'Could not store image'
        );

        $this->assertTrue(
            $this->driver->imageExists($this->user, $this->imageIdentifier),
            'Image does not exist'
        );
    }
}