<?php
namespace Imbo\Database;

use Imbo\Model\Image;
use Imbo\Model\Images;
use Imbo\Resource\Images\Query;
use Imbo\Exception\DatabaseException;
use Imbo\Exception\InvalidArgumentException;
use Imbo\Exception\DuplicateImageIdentifierException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PDO;
use DateTime;
use DateTimeZone;

/**
 * Doctrine 2 database driver
 *
 * Refer to http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest for configuration parameters
 */
class Doctrine implements DatabaseInterface {
    /**
     * Parameters for the Doctrine connection
     *
     * @var array
     */
    private $params = [];

    /**
     * Default table names for the database
     *
     * @var array
     */
    private $tableNames = [
        'imageinfo' => 'imageinfo',
        'metadata'  => 'metadata',
        'shorturl'  => 'shorturl',
    ];

    /**
     * Doctrine connection
     *
     * @var Connection
     */
    private $connection;

    /**
     * Separator used when (de)normalizing metadata
     *
     * @var string
     */
    private $metadataNamespaceSeparator = '::';

    /**
     * Class constructor
     *
     * @param array $params Parameters for the driver
     * @throws InvalidArgumentException
     */
    public function __construct(array $params) {
        if (isset($params['pdo'])) {
            throw new InvalidArgumentException(sprintf(
                "The usage of 'pdo' in the configuration for %s is not allowed, use 'driver' instead",
                __CLASS__
            ), 500);
        }

        $this->params = $params;
    }

    /**
     * {@inheritdoc}
     */
    public function insertImage($user, $imageIdentifier, Image $image, $updateIfDuplicate = true) {
        $now = time();

        if ($added = $image->getAddedDate()) {
            $added = $added->getTimestamp();
        }

        if ($updated = $image->getUpdatedDate()) {
            $updated = $updated->getTimestamp();
        }

        if ($updateIfDuplicate && $id = $this->getImageId($user, $imageIdentifier)) {
            return (boolean)$this->getConnection()->update($this->tableNames['imageinfo'], [
                'updated' => $now,
            ], [
                'id' => $id
            ]);
        }

        try {
            $result = $this->getConnection()->insert($this->tableNames['imageinfo'], [
                'size' => $image->getFilesize(),
                'user' => $user,
                'imageIdentifier' => $imageIdentifier,
                'extension' => $image->getExtension(),
                'mime' => $image->getMimeType(),
                'added' => $added ?: $now,
                'updated' => $updated ?: $now,
                'width' => $image->getWidth(),
                'height' => $image->getHeight(),
                'checksum' => $image->getChecksum(),
                'originalChecksum' => $image->getOriginalChecksum(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new DuplicateImageIdentifierException(
                'Duplicate image identifier when attempting to insert image into DB.',
                503,
                $e
            );
        } catch (DBALException $e) {
            throw new DatabaseException('Unable to save image data', 500, $e);
        }

        return (boolean) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteImage($user, $imageIdentifier) {
        if (!$id = $this->getImageId($user, $imageIdentifier)) {
            throw new DatabaseException('Image not found', 404);
        }

        $query = $this->getConnection()->createQueryBuilder();
        $query->delete($this->tableNames['imageinfo'])
              ->where('id = :id')
              ->setParameters([
                  ':id' => $id,
              ])->execute();

        $query->resetQueryParts();
        $query->delete($this->tableNames['metadata'])
              ->where('imageId = :imageId')
              ->setParameters([
                  ':imageId' => $id,
              ])->execute();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function updateMetadata($user, $imageIdentifier, array $metadata) {
        // Fetch the current connection
        $connection = $this->getConnection();
        $imageId = $this->getImageId($user, $imageIdentifier);

        // Fetch existing metadata so we can merge it with the data passed to this method
        $existing = $this->getMetadata($user, $imageIdentifier);
        $metadata = array_merge($existing, $metadata);

        // Delete existing metadata
        $this->deleteMetadata($user, $imageIdentifier);

        // Normalize metadata
        $normalizedMetadata = [];
        $this->normalizeMetadata($metadata, $normalizedMetadata);

        // Insert merged and normalized metadata
        foreach ($normalizedMetadata as $key => $value) {
            $connection->insert($this->tableNames['metadata'], [
                'imageId'  => $imageId,
                'tagName'  => $key,
                'tagValue' => $value,
            ]);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($user, $imageIdentifier) {
        if (!$id = $this->getImageId($user, $imageIdentifier)) {
            throw new DatabaseException('Image not found', 404);
        }

        $query = $this->getConnection()->createQueryBuilder();
        $query->select('tagName', 'tagValue')
              ->from($this->tableNames['metadata'], 'm')
              ->where('imageId = :imageId')
              ->setParameters([':imageId' => $id]);

        $stmt = $query->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $metadata = [];

        foreach ($rows as $row) {
            $metadata[$row['tagName']] = $row['tagValue'];
        }

        return $this->denormalizeMetadata($metadata);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMetadata($user, $imageIdentifier) {
        if (!$id = $this->getImageId($user, $imageIdentifier)) {
            throw new DatabaseException('Image not found', 404);
        }

        $query = $this->getConnection()->createQueryBuilder();
        $query->delete($this->tableNames['metadata'])
              ->where('imageId = :imageId')
              ->setParameters([
                  ':imageId' => $id,
                ])->execute();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getImages(array $users, Query $query, Images $model) {
        $images = [];

        $qb = $this->getConnection()->createQueryBuilder();
        $qb->select('*')->from($this->tableNames['imageinfo'], 'i');

        if ($users) {
            // Filter on users
            $expr = $qb->expr();
            $composite = $expr->orX();

            foreach ($users as $i => $user) {
                $composite->add($expr->eq('i.user', ':user' . $i));
                $qb->setParameter(':user' . $i, $user);
            }

            $qb->where($composite);
        }

        if ($sort = $query->sort()) {
            // Fields valid for sorting
            $validFields = [
                'size'             => true,
                'user'             => true,
                'imageIdentifier'  => true,
                'extension'        => true,
                'mime'             => true,
                'added'            => true,
                'updated'          => true,
                'width'            => true,
                'height'           => true,
                'checksum'         => true,
                'originalChecksum' => true,
            ];

            foreach ($sort as $f) {
                if (!isset($validFields[$f['field']])) {
                    throw new InvalidArgumentException('Invalid sort field: ' . $f['field'], 400);
                }

                $qb->addOrderBy($f['field'], $f['sort']);
            }
        } else {
            $qb->orderBy('added', 'DESC');
        }

        $from = $query->from();
        $to = $query->to();

        if ($from || $to) {
            if ($from !== null) {
                $qb->andWhere('added >= :from')->setParameter(':from', $from);
            }

            if ($to !== null) {
                $qb->andWhere('added <= :to')->setParameter(':to', $to);
            }
        }

        if ($imageIdentifiers = $query->imageIdentifiers()) {
            $expr = $qb->expr();
            $composite = $expr->orX();

            foreach ($imageIdentifiers as $i => $id) {
                $composite->add($expr->eq('i.imageIdentifier', ':imageIdentifier' . $i));
                $qb->setParameter(':imageIdentifier' . $i, $id);
            }

            $qb->andWhere($composite);
        }

        if ($checksums = $query->checksums()) {
            $expr = $qb->expr();
            $composite = $expr->orX();

            foreach ($checksums as $i => $id) {
                $composite->add($expr->eq('i.checksum', ':checksum' . $i));
                $qb->setParameter(':checksum' . $i, $id);
            }

            $qb->andWhere($composite);
        }

        if ($originalChecksums = $query->originalChecksums()) {
            $expr = $qb->expr();
            $composite = $expr->orX();

            foreach ($originalChecksums as $i => $id) {
                $composite->add($expr->eq('i.originalChecksum', ':originalChecksum' . $i));
                $qb->setParameter(':originalChecksum' . $i, $id);
            }

            $qb->andWhere($composite);
        }

        // Create a querybuilder that will be used to fetch the hits number, and update the model
        $hitsQb = clone $qb;
        $hitsQb->select('COUNT(i.id)');
        $stmt = $hitsQb->execute();
        $model->setHits((int) $stmt->fetchColumn());

        if ($limit = $query->limit()) {
            $qb->setMaxResults($limit);
        }

        if ($page = $query->page()) {
            $offset = (int) $query->limit() * ($page - 1);
            $qb->setFirstResult($offset);
        }

        $stmt = $qb->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $returnMetadata = $query->returnMetadata();

        foreach ($rows as $row) {
            $image = [
                'extension'        => $row['extension'],
                'added'            => new DateTime('@' . $row['added'], new DateTimeZone('UTC')),
                'updated'          => new DateTime('@' . $row['updated'], new DateTimeZone('UTC')),
                'checksum'         => $row['checksum'],
                'originalChecksum' => isset($row['originalChecksum']) ? $row['originalChecksum'] : null,
                'user'             => $row['user'],
                'imageIdentifier'  => $row['imageIdentifier'],
                'mime'             => $row['mime'],
                'size'             => (int) $row['size'],
                'width'            => (int) $row['width'],
                'height'           => (int) $row['height']
            ];

            if ($returnMetadata) {
                $image['metadata'] = $this->getMetadata($row['user'], $row['imageIdentifier']);
            }

            $images[] = $image;
        }

        return $images;
    }

    /**
     * {@inheritdoc}
     */
    public function getImageProperties($user, $imageIdentifier) {
        $query = $this->getConnection()->createQueryBuilder();
        $query->select('*')
              ->from($this->tableNames['imageinfo'], 'i')
              ->where('i.user = :user')
              ->andWhere('i.imageIdentifier = :imageIdentifier')
              ->setParameters([
                  ':user'            => $user,
                  ':imageIdentifier' => $imageIdentifier,
        ]);
        $stmt = $query->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new DatabaseException('Image not found', 404);
        }
        return $row;
    }

    /**
     * {@inheritdoc}
     */
    public function load($user, $imageIdentifier, Image $image) {
        $row = $this->getImageProperties($user, $imageIdentifier);

        $image->setWidth($row['width'])
              ->setHeight($row['height'])
              ->setFilesize($row['size'])
              ->setMimeType($row['mime'])
              ->setExtension($row['extension'])
              ->setAddedDate(new DateTime('@' . $row['added'], new DateTimeZone('UTC')))
              ->setUpdatedDate(new DateTime('@' . $row['updated'], new DateTimeZone('UTC')));

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastModified(array $users, $imageIdentifier = null) {
        $query = $this->getConnection()->createQueryBuilder();
        $query->select('i.updated')
              ->from($this->tableNames['imageinfo'], 'i')
              ->orderBy('i.updated', 'DESC')
              ->setMaxResults(1);

        if (!empty($users)) {
            $expr = $query->expr();
            $composite = $expr->orX();

            foreach ($users as $i => $user) {
                $composite->add($expr->eq('i.user', ':user' . $i));
                $query->setParameter(':user' . $i, $user);
            }

            $query->where($composite);
        }

        if ($imageIdentifier !== null) {
            $query->andWhere('i.imageIdentifier = :imageIdentifier')
                  ->setParameter(':imageIdentifier', $imageIdentifier);
        }

        $stmt = $query->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row && $imageIdentifier !== null) {
            throw new DatabaseException('Image not found', 404);
        } else if (!$row) {
            $row = ['updated' => time()];
        }

        return new DateTime('@' . $row['updated'], new DateTimeZone('UTC'));
    }

    /**
     * {@inheritdoc}
     */
    public function setLastModifiedNow($user, $imageIdentifier) {
        return $this->setLastModifiedTime($user, $imageIdentifier, new DateTime('@' . time(), new DateTimeZone('UTC')));
    }

    /**
     * {@inheritdoc}
     */
    public function setLastModifiedTime($user, $imageIdentifier, DateTime $time) {
        // Fetch the current connection
        $connection = $this->getConnection();

        if (!$imageId = $this->getImageId($user, $imageIdentifier)) {
            throw new DatabaseException('Image not found', 404);
        }

        $update = $connection->createQueryBuilder();
        $update->update($this->tableNames['imageinfo'])
               ->set('updated', $time->getTimestamp())
               ->where('id = :id')
               ->setParameters([
                   ':id' => $imageId,
               ])->execute();

        return $time;
    }

    /**
     * {@inheritdoc}
     */
    public function getNumImages($user = null) {
        $query = $this->getConnection()->createQueryBuilder();
        $query->select('COUNT(i.id)')
              ->from($this->tableNames['imageinfo'], 'i');

        if ($user) {
            $query->where('i.user = :user')
                  ->setParameter(':user', $user);
        }

        $stmt = $query->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function getNumBytes($user = null) {
        $query = $this->getConnection()->createQueryBuilder();
        $query->select('SUM(i.size)')
              ->from($this->tableNames['imageinfo'], 'i');

        if ($user) {
            $query->where('i.user = :user')
                  ->setParameter(':user', $user);
        }

        $stmt = $query->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function getNumUsers() {
        $query = $this->getConnection()->createQueryBuilder();
        $query->select('COUNT(DISTINCT(i.user))')
              ->from($this->tableNames['imageinfo'], 'i');

        $stmt = $query->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus() {
        try {
            $connection = $this->getConnection();

            return $connection->isConnected() || $connection->connect();
        } catch (DBALException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getImageMimeType($user, $imageIdentifier) {
        $query = $this->getConnection()->createQueryBuilder();
        $query->select('mime')
              ->from($this->tableNames['imageinfo'], 'i')
              ->where('i.user = :user')
              ->andWhere('i.imageIdentifier = :imageIdentifier')
              ->setParameters([
                  ':user'            => $user,
                  ':imageIdentifier' => $imageIdentifier,
              ]);

        $stmt = $query->execute();
        $mime = $stmt->fetchColumn();

        if (!$mime) {
            throw new DatabaseException('Image not found', 404);
        }

        return $mime;
    }

    /**
     * {@inheritdoc}
     */
    public function imageExists($user, $imageIdentifier) {
        return (boolean) $this->getImageId($user, $imageIdentifier);
    }

    /**
     * {@inheritdoc}
     */
    public function insertShortUrl($shortUrlId, $user, $imageIdentifier, $extension = null, array $query = []) {
        return (boolean) $this->getConnection()->insert($this->tableNames['shorturl'], [
            'shortUrlId' => $shortUrlId,
            'user' => $user,
            'imageIdentifier' => $imageIdentifier,
            'extension' => $extension,
            'query' => serialize($query),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getShortUrlParams($shortUrlId) {
        $qb = $this->getConnection()->createQueryBuilder();
        $qb->select('user', 'imageIdentifier', 'extension', 'query')
           ->from($this->tableNames['shorturl'], 's')
           ->where('shortUrlId = :shortUrlId')
           ->setParameters([':shortUrlId' => $shortUrlId]);

        $stmt = $qb->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['query'] = unserialize($row['query']);

        return $row;
    }

    /**
     * {@inheritdoc}
     */
    public function getShortUrlId($user, $imageIdentifier, $extension = null, array $query = []) {
        $qb = $this->getConnection()->createQueryBuilder();
        $qb->select('shortUrlId')
           ->from($this->tableNames['shorturl'], 's')
           ->where('user = :user')
           ->andWhere('imageIdentifier = :imageIdentifier')
           ->andWhere('query = :query')
           ->setParameters([
               ':user' => $user,
               ':imageIdentifier' => $imageIdentifier,
               ':query' => serialize($query),
           ]);

        if ($extension === null) {
            $qb->andWhere('extension is NULL');
        } else {
            $qb->andWhere('extension = :extension')
               ->setParameter(':extension', $extension);
        }

        $stmt = $qb->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $row['shortUrlId'];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteShortUrls($user, $imageIdentifier, $shortUrlId = null) {
        $qb = $this->getConnection()->createQueryBuilder();

        $qb->delete($this->tableNames['shorturl'])
           ->where('user = :user')
           ->andWhere('imageIdentifier = :imageIdentifier')
           ->setParameters([
               ':user' => $user,
               ':imageIdentifier' => $imageIdentifier,
           ]);

        if ($shortUrlId) {
            $qb->andWhere('shortUrlId = :shortUrlId')
               ->setParameter(':shortUrlId', $shortUrlId);
        }

        return (boolean) $qb->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function getAllUsers() {
        $query = $this->getConnection()->createQueryBuilder();
        $query->select('DISTINCT(i.user)')
              ->from($this->tableNames['imageinfo'], 'i');

        return array_column($query->execute()->fetchAll(), 'user');
    }

    /**
     * Get the Doctrine connection
     *
     * @return Connection
     */
    protected function getConnection() {
        if ($this->connection === null) {
            $this->connection = DriverManager::getConnection($this->params);
        }

        return $this->connection;
    }

    /**
     * Get the internal image ID
     *
     * @param string $user The user which the image belongs to
     * @param string $imageIdentifier The image identifier
     * @return int
     */
    private function getImageId($user, $imageIdentifier) {
        $query = $this->getConnection()->createQueryBuilder();
        $query->select('i.id')
              ->from($this->tableNames['imageinfo'], 'i')
              ->where('i.user = :user')
              ->andWhere('i.imageIdentifier = :imageIdentifier')
              ->setParameters([
                  ':user'            => $user,
                  ':imageIdentifier' => $imageIdentifier,
              ]);

        $stmt = $query->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) $row['id'];
    }

    /**
     * Normalize metadata
     *
     * @param array $metadata Metadata
     * @param array $normalized Normalized metadata
     * @param string $namespace Namespace for keys
     * @return array Returns an associative array with only one level
     */
    private function normalizeMetadata(array &$metadata, array &$normalized, $namespace = '') {
        foreach ($metadata as $key => $value) {
            if (strstr($key, $this->metadataNamespaceSeparator) !== false) {
                throw new DatabaseException('Invalid metadata', 400);
            }

            $ns = $namespace . ($namespace ? $this->metadataNamespaceSeparator : '') . $key;

            if (is_array($value)) {
                $this->normalizeMetadata($value, $normalized, $ns);
            } else {
                $normalized[$ns] = $value;
            }
        }
    }

    /**
     * De-normalize metadata
     *
     * @param array $data Metadata
     * @return array
     */
    private function denormalizeMetadata(array $data) {
        $result = [];

        foreach ($data as $key => $value) {
            $keys = explode($this->metadataNamespaceSeparator, $key);
            $tmp = &$result;

            foreach ($keys as $i => $key) {
                if (!isset($tmp[$key])) {
                    $tmp[$key] = null;
                }

                $tmp = &$tmp[$key];
            }

            $tmp = $value;
        }

        return $result;
    }
}