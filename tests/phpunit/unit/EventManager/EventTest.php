<?php
namespace ImboUnitTest\EventManager;

use Imbo\EventManager\Event;
use Imbo\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Imbo\EventManager\Event
 */
class EventTest extends TestCase {
    /**
     * @var Event
     */
    private $event;

    /**
     * Set up the event instance
     */
    public function setUp() : void {
        $this->event = new Event();
    }

    public function getArguments() {
        return [
            'request' => [
                'getRequest', 'request', $this->createMock('Imbo\Http\Request\Request'),
            ],
            'response' => [
                'getResponse', 'response', $this->createMock('Imbo\Http\Response\Response'),
            ],
            'database' => [
                'getDatabase', 'database', $this->createMock('Imbo\Database\DatabaseInterface'),
            ],
            'storage' => [
                'getStorage', 'storage', $this->createMock('Imbo\Storage\StorageInterface'),
            ],
            'accessControl' => [
                'getAccessControl', 'accessControl', $this->createMock('Imbo\Auth\AccessControl\Adapter\AdapterInterface'),
            ],
            'manager' => [
                'getManager', 'manager', $this->createMock('Imbo\EventManager\EventManager'),
            ],
            'config' => [
                'getConfig', 'config', ['some' => 'config'],
            ],
            'handler' => [
                'getHandler', 'handler', 'handler name',
            ],
        ];
    }

    /**
     * @dataProvider getArguments
     */
    public function testCanSetAndGetRequest($method, $argument, $value) : void {
        $this->event->setArgument($argument, $value);
        $this->assertSame($value, $this->event->$method());
    }

    /**
     * @covers Imbo\EventManager\Event::setName
     * @covers Imbo\EventManager\Event::getName
     */
    public function testCanSetAndGetName() : void {
        $this->assertNull($this->event->getName());
        $this->assertSame($this->event, $this->event->setName('name'));
        $this->assertSame('name', $this->event->getName());
    }

    /**
     * @covers Imbo\EventManager\Event::stopPropagation
     * @covers Imbo\EventManager\Event::isPropagationStopped
     */
    public function testCanStopPropagation() : void {
        $this->assertFalse($this->event->isPropagationStopped());
        $this->assertSame($this->event, $this->event->stopPropagation());
        $this->assertTrue($this->event->isPropagationStopped());
    }

    /**
     * @covers Imbo\EventManager\Event::getArgument
     */
    public function testThrowsExceptionWhenGettingArgumentThatDoesNotExist() : void {
        $this->expectExceptionObject(new InvalidArgumentException(
            'Argument "foobar" does not exist',
            500
        ));
        $this->event->getArgument('foobar');
    }

    /**
     * @covers Imbo\EventManager\Event::__construct
     * @covers Imbo\EventManager\Event::setArguments
     */
    public function testCanSetArgumentsThroughConstructor() : void {
        $event = new Event(['foo' => 'bar']);
        $this->assertSame('bar', $event->getArgument('foo'));
    }

    /**
     * @covers Imbo\EventManager\Event::setArguments
     * @covers Imbo\EventManager\Event::getArgument
     * @covers Imbo\EventManager\Event::hasArgument
     */
    public function testSetArgumentsOverridesAllArguments() : void {
        $this->assertFalse($this->event->hasArgument('foo'));

        $this->assertSame($this->event, $this->event->setArguments(['foo' => 'bar']));
        $this->assertSame('bar', $this->event->getArgument('foo'));

        $this->assertSame($this->event, $this->event->setArguments(['bar' => 'foo']));
        $this->assertFalse($this->event->hasArgument('foo'));
        $this->assertSame('foo', $this->event->getArgument('bar'));
    }
}
