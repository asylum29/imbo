<?php
namespace ImboUnitTest\Helpers;

use Imbo\Helpers\DateFormatter;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Imbo\Helpers\DateFormatter
 */
class DateFormatterTest extends TestCase {
    /**
     * @var DateFormatter
     */
    private $helper;

    /**
     * Set up the helper
     */
    public function setUp() : void {
        $this->helper = new DateFormatter();
    }

    /**
     * Get different datetimes
     *
     * @return array[]
     */
    public function getDates() {
        return [
            [new DateTime('@1234567890'), 'Fri, 13 Feb 2009 23:31:30 GMT'],
            [new DateTime('16/Mar/2012:15:05:00 +0100'), 'Fri, 16 Mar 2012 14:05:00 GMT'],
        ];
    }

    /**
     * @dataProvider getDates
     * @covers Imbo\Helpers\DateFormatter::formatDate
     */
    public function testCanFormatADateTimeInstance($datetime, $expected) {
        $this->assertSame($expected, $this->helper->formatDate($datetime));
    }
}