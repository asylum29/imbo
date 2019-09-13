<?php
namespace ImboUnitTest\EventListener;

use Imbo\EventListener\StatsAccess;
use Imbo\Resource\Stats as StatsResource;
use Imbo\EventManager\EventManager;
use Imbo\Exception\RuntimeException;
use ReflectionProperty;

/**
 * @coversDefaultClass Imbo\EventListener\StatsAccess
 */
class StatsAccessTest extends ListenerTests {
    /**
     * @var StatsAccess
     */
    private $listener;

    private $event;
    private $request;

    public function setUp() : void {
        $this->request = $this->createMock('Imbo\Http\Request\Request');

        $this->event = $this->createMock('Imbo\EventManager\Event');
        $this->event->expects($this->any())->method('getRequest')->will($this->returnValue($this->request));

        $this->listener = new StatsAccess();
    }

    protected function getListener() : StatsAccess {
        return $this->listener;
    }

    /**
     * @covers ::checkAccess
     */
    public function testDoesNotAllowAnyIpAddressPerDefault() : void {
        $this->expectExceptionObject(new RuntimeException('Access denied', 403));
        $this->listener->checkAccess($this->event);
    }

    public function getFilterData() : array {
        return [
            'IPv4 in whitelist' => [
                '127.0.0.1',
                ['127.0.0.1'],
                true
            ],
            'IPv4 not in whitelist' => [
                '127.0.0.2',
                ['127.0.0.1'],
                false
            ],
            'IPv4 in whitelist range' => [
                '192.168.1.10',
                ['192.168.1.0/24'],
                true
            ],
            'IPv4 outside of whitelist range' => [
                '192.168.1.64',
                ['192.168.1.32/27'],
                false
            ],
            'IPv6 in whitelist (in short format)' => [
                '2a00:1b60:1011:0000:0000:0000:0000:1338',
                ['2a00:1b60:1011::1338'],
                true
            ],
            'IPv6 in whitelist (in full format)' => [
                '2a00:1b60:1011:0000:0000:0000:0000:1338',
                ['2a00:1b60:1011:0000:0000:0000:0000:1338'],
                true
            ],
            'IPv6 in whitelist range' => [
                '2001:0db8:0000:0000:0000:0000:0000:0000',
                ['2001:db8::/48'],
                true
            ],
            'IPv6 outside of whitelist range' => [
                '2001:0db9:0000:0000:0000:0000:0000:0000',
                ['2001:db8::/48'],
                false
            ],
            'IPv6 in whitelist (in short format in both fields)' => [
                '2a00:1b60:1011::1338',
                ['2a00:1b60:1011::1338'],
                true
            ],
            'Blaclisted IPv4 client and both types in allow' => [
                '1.2.3.4',
                ['127.0.0.1', '::1'],
                false
            ],
            'Whitelitsed IPv6 client and both types in allow' => [
                '::1',
                ['127.0.0.1', '::1'],
                true
            ],
            'Wildcard allows all clients' => [
                '::1',
                ['*'],
                true
            ],
        ];
    }

    /**
     * @dataProvider getFilterData
     */
    public function testCanUseDifferentFilters(string $clientIp, array $allow, bool $hasAccess) : void {
        $this->request->expects($this->once())
                      ->method('getClientIp')
                      ->will($this->returnValue($clientIp));

        $listener = new StatsAccess([
            'allow' => $allow,
        ]);

        if (!$hasAccess) {
            $this->expectExceptionObject(new RuntimeException('Access denied', 403));
        }

        $listener->checkAccess($this->event);
    }

    /**
     * @see https://github.com/imbo/imbo/issues/249
     */
    public function testListensToTheSameEventsAsTheStatsResource() : void {
        $this->assertSame(
            array_keys(StatsAccess::getSubscribedEvents()),
            array_keys(StatsResource::getSubscribedEvents()),
            'The stats access event listener does not listen to the same events as the stats resource, which it should'
        );
    }

    /**
     * @see https://github.com/imbo/imbo/issues/251
     */
    public function testHasHigherPriorityThanTheStatsResource() : void {
        $statsAccess = new StatsAccess();
        $statsResource = new StatsResource();

        $eventManager = new EventManager();
        $eventManager->addEventHandler('statsAccess', $statsAccess);
        $eventManager->addCallbacks('statsAccess', StatsAccess::getSubscribedEvents());
        $eventManager->addEventHandler('statsResource', $statsResource);
        $eventManager->addCallbacks('statsResource', StatsResource::getSubscribedEvents());

        $callbacks = new ReflectionProperty($eventManager, 'callbacks');
        $callbacks->setAccessible(true);

        $handlersForGet = $callbacks->getValue($eventManager)['stats.get'];
        $handlersForHead = $callbacks->getValue($eventManager)['stats.head'];

        $this->assertSame($statsAccess, $eventManager->getHandlerInstance($handlersForGet->extract()['handler']));
        $this->assertSame($statsResource, $eventManager->getHandlerInstance($handlersForGet->extract()['handler']));

        $this->assertSame($statsAccess, $eventManager->getHandlerInstance($handlersForHead->extract()['handler']));
        $this->assertSame($statsResource, $eventManager->getHandlerInstance($handlersForHead->extract()['handler']));
    }
}