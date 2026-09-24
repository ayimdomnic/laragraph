<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars;

use Ayimdomnic\Laragraph\Scalars\DateTimeType;
use Ayimdomnic\Laragraph\Scalars\DateType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Error\Error;
use PHPUnit\Framework\Attributes\DataProvider;

class DateParsingEdgeCasesTest extends TestCase
{
    public function test_dates_parse_to_midnight_not_the_current_time(): void
    {
        $this->assertSame('2024-01-01T00:00:00', (new DateType())->parseValue('2024-01-01')->format('Y-m-d\TH:i:s'));
        $this->assertSame('2024-01-01T00:00:00', (new DateTimeType())->parseValue('2024-01-01')->format('Y-m-d\TH:i:s'));
    }

    public function test_leap_days_are_accepted(): void
    {
        $this->assertSame('2024-02-29', (new DateType())->parseValue('2024-02-29')->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function impossibleDates(): iterable
    {
        yield 'february 31st'         => ['2023-02-31'];
        yield 'non-leap february 29'  => ['2023-02-29'];
        yield 'month 13'              => ['2024-13-01'];
        yield 'day zero'              => ['2024-01-00'];
        yield 'trailing garbage'      => ['2024-01-01x'];
    }

    #[DataProvider('impossibleDates')]
    public function test_impossible_dates_are_rejected_not_rolled_over(string $value): void
    {
        foreach ([new DateType(), new DateTimeType()] as $scalar) {
            try {
                $scalar->parseValue($value);
                $this->fail($scalar->name . " accepted {$value}");
            } catch (Error) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_impossible_date_strings_are_not_serialized(): void
    {
        foreach ([new DateType(), new DateTimeType()] as $scalar) {
            try {
                $scalar->serialize('2023-02-31');
                $this->fail($scalar->name . ' serialized an impossible date');
            } catch (Error) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dateTimes(): iterable
    {
        yield 'UTC designator'              => ['2024-01-15T09:30:00Z', '2024-01-15T09:30:00.000+00:00'];
        yield 'offset'                      => ['2024-01-15T09:30:00+02:00', '2024-01-15T09:30:00.000+02:00'];
        yield 'JavaScript toISOString()'    => ['2024-01-15T09:30:00.123Z', '2024-01-15T09:30:00.123+00:00'];
        yield 'microseconds'                => ['2024-01-15T09:30:00.123456+00:00', '2024-01-15T09:30:00.123+00:00'];
        yield 'SQL format'                  => ['2024-01-15 09:30:00', '2024-01-15T09:30:00.000+00:00'];
    }

    #[DataProvider('dateTimes')]
    public function test_common_date_time_formats_are_accepted(string $input, string $expected): void
    {
        $this->assertSame($expected, (new DateTimeType())->parseValue($input)->format('Y-m-d\TH:i:s.vP'));
    }

    public function test_invalid_times_are_rejected(): void
    {
        $this->expectException(Error::class);

        (new DateTimeType())->parseValue('2024-01-15T25:00:00Z');
    }
}
