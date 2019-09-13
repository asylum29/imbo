<?php
namespace ImboUnitTest\Auth\AccessControl\Adapter;

use Imbo\Auth\AccessControl\Adapter\ArrayAdapter;
use Imbo\Auth\AccessControl\Adapter\SimpleArrayAdapter;
use Imbo\Resource;
use Imbo\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Imbo\Auth\AccessControl\Adapter\SimpleArrayAdapter
 */
class SimpleArrayAdapterTest extends TestCase {
    /**
     * @dataProvider getAuthConfig
     */
    public function testCanSetKeys(array $users, $publicKey, $privateKey) {
        $accessControl = new SimpleArrayAdapter($users);

        $this->assertSame($privateKey, $accessControl->getPrivateKey($publicKey));
    }

    public function testThrowsOnMultiplePrivateKeysPerPublicKey() {
        $this->expectExceptionObject(new InvalidArgumentException(
            'A public key can only have a single private key (as of 2.0.0)',
            500
        ));
        $accessControl = new SimpleArrayAdapter([
            'publicKey' => ['key1', 'key2']
        ]);
    }

    public function testLegacyConfigKeysHaveWriteAccess() {
        $accessControl = new SimpleArrayAdapter([
            'publicKey' => 'privateKey',
        ]);

        $this->assertTrue(
            $accessControl->hasAccess(
                'publicKey',
                Resource::IMAGES_POST,
                'publicKey'
            )
        );
    }

    public function testExtendsArrayAdapter() {
        $accessControl = new SimpleArrayAdapter(['publicKey' => 'key']);
        $this->assertTrue($accessControl instanceof ArrayAdapter);
    }

    public function testIsEmpty() {
        $accessControl = new SimpleArrayAdapter();
        $this->assertTrue($accessControl->isEmpty());

        $accessControl = new SimpleArrayAdapter(['foo' => 'bar']);
        $this->assertFalse($accessControl->isEmpty());
    }

    /**
     * Data provider for testing the legacy auth compatibility
     *
     * @return array
     */
    public function getAuthConfig() {
        $users = [
            'publicKey1' => 'key1',
            'publicKey2' => 'key2',
        ];

        return [
            'no public keys exists' => [[], 'public', null],
            'public key exists' => [$users, 'publicKey2', 'key2'],
            'public key does not exist' => [$users, 'publicKey3', null],
        ];
    }
}