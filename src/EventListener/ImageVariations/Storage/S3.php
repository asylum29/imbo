<?php declare(strict_types=1);
namespace Imbo\EventListener\ImageVariations\Storage;

use Imbo\Exception\StorageException;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 * S3 storage driver for the image variations
 *
 * Configuration options supported by this driver:
 * Parameters for this adapter:
 *
 * - (string) key Your AWS access key
 * - (string) secret Your AWS secret key
 * - (string) bucket The name of the bucket to store the files in. The bucket should exist prior
 *                   to using this client. Imbo will not try to automatically add the bucket for
 *                   you.
 */
class S3 implements StorageInterface {
    private S3Client $client;
    private array $params = [
        // Access key
        'key' => null,

        // Secret key
        'secret' => null,

        // Name of the bucket to store the files in
        'bucket' => null,

        // Region
        'region' => null,

        // Version of API
        'version' => '2006-03-01',
    ];

    /**
     * Class constructor
     *
     * @param array $params Parameters for the adapter
     * @param S3Client $client Configured S3Client instance
     */
    public function __construct(array $params = null, S3Client $client = null) {
        if ($params !== null) {
            $this->params = array_replace_recursive($this->params, $params);
        }

        if ($client !== null) {
            $this->client = $client;
        }
    }

    public function storeImageVariation(string $user, string $imageIdentifier, string $imageData, int $width) : bool {
        try {
            $this->getClient()->putObject([
                'Bucket' => $this->params['bucket'],
                'Key' => $this->getImagePath($user, $imageIdentifier, $width),
                'Body' => $imageData,
            ]);
        } catch (S3Exception $e) {
            throw new StorageException('Could not store image', 500);
        }

        return true;
    }

    public function getImageVariation(string $user, string $imageIdentifier, int $width) : ?string {
        try {
            $model = $this->getClient()->getObject([
                'Bucket' => $this->params['bucket'],
                'Key' => $this->getImagePath($user, $imageIdentifier, $width),
            ]);
        } catch (S3Exception $e) {
            return null;
        }

        return (string) $model->get('Body');
    }

    public function deleteImageVariations(string $user, string $imageIdentifier, int $width = null) : bool {
        // If width is specified, delete only the specific image
        if ($width !== null) {
            $this->getClient()->deleteObject([
                'Bucket' => $this->params['bucket'],
                'Key' => $this->getImagePath($user, $imageIdentifier, $width)
            ]);

            return true;
        }

        // If width is not specified, delete every variation. Ask S3 to list all files in the
        // directory.
        $variationsPath = $this->getImagePath($user, $imageIdentifier);

        $varations = $this->getClient()->getIterator('ListObjects', [
            'Bucket' => $this->params['bucket'],
            'Prefix' => $variationsPath
        ]);

        // Note: could also use AWS's deleteMatchingObjects instead of deleting items one by one
        foreach ($varations as $variation) {
            $this->getClient()->deleteObject([
                'Bucket' => $this->params['bucket'],
                'Key' => $variation['Key']
            ]);
        }

        return true;
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
            'imageVariation',
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

        return implode('/', $parts);
    }


    /**
     * Get the S3Client instance
     *
     * @return S3Client
     */
    private function getClient() : S3Client {
        if ($this->client === null) {
            $params = [
                'credentials' => [
                    'key' => $this->params['key'],
                    'secret' => $this->params['secret'],
                ],
                'version' => $this->params['version'],
            ];

            if ($this->params['region']) {
                $params['region'] = $this->params['region'];
            }

            $this->client = new S3Client($params);
        }

        return $this->client;
    }
}
