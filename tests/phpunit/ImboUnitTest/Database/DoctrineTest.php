<?php
/**
 * This file is part of the Imbo package
 *
 * (c) Christer Edvartsen <cogo@starzinger.net>
 *
 * For the full copyright and license information, please view the LICENSE file that was
 * distributed with this source code.
 */

namespace ImboUnitTest\Database;

use Imbo\Database\Doctrine,
    PDO,
    ReflectionMethod,
    Doctrine\DBAL\DriverManager;

/**
 * @covers Imbo\Database\Doctrine
 * @group unit
 * @group database
 * @group doctrine
 */
class DoctrineTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var Doctrine
     */
    private $driver;

    /**
     * Set up the driver
     */
    public function setUp() {
        $this->driver = new Doctrine([]);
    }

    /**
     * Tear down the driver
     */
    public function tearDown() {
        $this->driver = null;
    }

    /**
     * Data provider
     *
     * @return array[]
     */
    public function getMetadata() {
        return [
            'simple key/value' => [
                ['key' => 'value', 'key2' => 'value2'],
                ['key' => 'value', 'key2' => 'value2'],
            ],
            'numeric array' => [
                ['key' => [1, 2, 3]],
                [
                    'key::0' => 1,
                    'key::1' => 2,
                    'key::2' => 3,
                ],
            ],
            'nested array' => [
                ['some' => ['key' => ['with' => ['a' => 'value']]]],
                ['some::key::with::a' => 'value'],
            ],
            'all sorts of stuff' => [
                [
                    'place' => 'Bar & Cigar',
                    'people' => [
                        [
                            'name' => 'christer',
                            'beers' => [
                                [
                                    'brewery' => 'Nøgne Ø',
                                    'name' => 'Pils',
                                ],
                                [
                                    'brewery' => 'HaandBryggeriet',
                                    'name' => 'Fyr & Flamme',
                                ],
                            ],
                        ],
                        [
                            'name' => 'espen',
                            'beers' => [
                                [
                                    'brewery' => 'AleSmith',
                                    'name' => 'Speedway Stout',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'place' => 'Bar & Cigar',
                    'people::0::name' => 'christer',
                    'people::0::beers::0::brewery' => 'Nøgne Ø',
                    'people::0::beers::0::name' => 'Pils',
                    'people::0::beers::1::brewery' => 'HaandBryggeriet',
                    'people::0::beers::1::name' => 'Fyr & Flamme',
                    'people::1::name' => 'espen',
                    'people::1::beers::0::brewery' => 'AleSmith',
                    'people::1::beers::0::name' => 'Speedway Stout',
                ],
            ],
        ];
    }

    /**
     * @covers Imbo\Database\Doctrine::normalizeMetadata
     * @dataProvider getMetadata
     */
    public function testCanNormalizeMetadata($denormalizedMetadata, $normalizedMetadata) {
        $method = new ReflectionMethod($this->driver, 'normalizeMetadata');
        $method->setAccessible(true);

        $result = [];
        $method->invokeArgs($this->driver, [&$denormalizedMetadata, &$result]);
        $this->assertSame($result, $normalizedMetadata);
    }

    /**
     * @covers Imbo\Database\Doctrine::denormalizeMetadata
     * @dataProvider getMetadata
     */
    public function testCanDenormalizeMetadata($denormalizedMetadata, $normalizedMetadata) {
        $method = new ReflectionMethod($this->driver, 'denormalizeMetadata');
        $method->setAccessible(true);

        $this->assertSame($denormalizedMetadata, $method->invoke($this->driver, $normalizedMetadata));
    }

    /**
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Invalid metadata
     * @expectedExceptionCode 400
     * @covers Imbo\Database\Doctrine::normalizeMetadata
     */
    public function testThrowsExceptionWhenKeysContainTheSeparator() {
        $method = new ReflectionMethod($this->driver, 'normalizeMetadata');
        $method->setAccessible(true);

        $result = [];
        $metadata = [
            'some::key' => 'value',
        ];
        $method->invokeArgs($this->driver, [&$metadata, &$result]);
        $this->assertSame($result, $normalizedMetadata);
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Deprecated
     * @expectedExceptionMessage The usage of pdo in the configuration array for Imbo\Database\Doctrine is deprecated and will be removed in Imbo-3.x
     */
    public function testUsageOfPdoInParametersIsDeprecated() {
        new Doctrine(['pdo' => new PDO('sqlite::memory:')]);
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Deprecated
     * @expectedExceptionMessage Specifying a connection instance in Imbo\Database\Doctrine is deprecated and will be removed in Imbo-3.x
     */
    public function testUsageOfConnectionInConstructor() {
        new Doctrine([], DriverManager::getConnection(['pdo' => new PDO('sqlite::memory:')]));
    }
}
