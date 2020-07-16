<?php declare(strict_types=1);
namespace Imbo\EventListener\ImageVariations\Storage;

use Imbo\Exception\StorageException;

/**
 * Filesystem storage driver for the image variations
 *
 * Configuration options supported by this driver:
 *
 * - <pre>(string) dataDir</pre> Absolute path to the base directory the image variations should
 *                               be stored in. Must be specified.
 */
class Filesystem implements StorageInterface {
    /**
     * Parameters for the driver
     *
     * @var array
     */
    private array $params = [
        'dataDir' => null,
    ];

    /**
     * Class constructor
     *
     * @param array $params Parameters for the driver
     */
    public function __construct(array $params = null) {
        if ($params !== null) {
            $this->params = array_replace_recursive($this->params, $params);
        }
    }

    public function storeImageVariation(string $user, string $imageIdentifier, string $blob, int $width) : bool {
        if (!is_writable($this->params['dataDir'])) {
            throw new StorageException('Could not store image variation (directory not writable)', 500);
        }

        $variationsDir = $this->getImagePath($user, $imageIdentifier, $width, false);
        $oldUmask = umask(0);

        if (!is_dir($variationsDir)) {
            mkdir($variationsDir, 0775, true);
        }

        umask($oldUmask);

        $variationsPath = $this->getImagePath($user, $imageIdentifier, $width);

        return (bool) file_put_contents($variationsPath, $blob);
    }

    /**
     * {@inheritdoc}
     */
    public function getImageVariation(string $user, string $imageIdentifier, int $width) : ?string {
        $variationPath = $this->getImagePath($user, $imageIdentifier, $width);

        if (file_exists($variationPath)) {
            return file_get_contents($variationPath);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteImageVariations(string $user, string $imageIdentifier, int $width = null) : bool {
        // If width is specified, delete only the specific image
        if ($width !== null) {
            return unlink($this->getImagePath($user, $imageIdentifier, $width, true));
        }

        // If width is not specified, delete every variation, then the folder
        $variationsPath = $this->getImagePath($user, $imageIdentifier);
        if (!is_dir($variationsPath)) {
            return false;
        }

        array_map('unlink', glob($variationsPath . '/*'));
        return rmdir($variationsPath);
    }

    /**
     * Get the path to an image
     *
     * @param string $user The user which the image belongs to
     * @param string $imageIdentifier Image identifier
     * @param int $width Width of the image, in pixels
     * @param bool $includeFilename Whether or not to include the last part of the path
     *                                 (the filename itself)
     * @return string
     */
    private function getImagePath(string $user, string $imageIdentifier, int $width = null, bool $includeFilename = true) : string {
        $userPath = str_pad($user, 3, '0', STR_PAD_LEFT);
        $parts = [
            $this->params['dataDir'],
            $userPath[0],
            $userPath[1],
            $userPath[2],
            $user,
            $imageIdentifier[0],
            $imageIdentifier[1],
            $imageIdentifier[2],
            $imageIdentifier,
        ];

        if ($includeFilename) {
            $parts[] = $width;
        }

        return implode(DIRECTORY_SEPARATOR, $parts);
    }
}
