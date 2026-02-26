<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_formatter_test extends TestCase
{
    public function testDate(): void
    {
        $format = 'd.m.Y H:i';

        self::assertEquals(
            '12.05.2012 10:24',
            rex_formatter::date(1336811080, $format),
        );
        self::assertEquals(
            '27.06.2016 21:40',
            rex_formatter::date('2016-06-27 21:40:00', $format),
        );
    }

    public function testStrftime(): void
    {
        $oldLocale = rex_i18n::getLocale();
        rex_i18n::setLocale('en_gb');

        $strftime = static function (string|int $value, string $format): string {
            /** @psalm-suppress DeprecatedMethod */
            return @rex_formatter::strftime($value, $format); /** @phpstan-ignore-line */
        };

        $value = 1336811080;

        $format = '%d.%m.%Y %H:%M';
        self::assertEquals(
            '12.05.2012 10:24',
            $strftime($value, $format),
        );

        self::assertEquals(
            '27.06.2016 21:40',
            $strftime('2016-06-27 21:40:00', $format),
        );

        $format = 'date';
        self::assertEquals(
            '12 May 2012',
            $strftime($value, $format),
        );

        $format = 'datetime';
        self::assertEquals(
            '12 May 2012, 10:24',
            $strftime($value, $format),
        );

        rex_i18n::setLocale($oldLocale);
    }

    /** @param int|array{int, int}|string|null $format */
    #[DataProvider('dataIntlDateTime')]
    public function testIntlDateTime(string $expected, string|int|DateTimeInterface|null $value, int|array|string|null $format = null): void
    {
        if (null === $format) {
            $string = rex_formatter::intlDateTime($value);
        } else {
            /** @psalm-suppress ArgumentTypeCoercion */
            $string = rex_formatter::intlDateTime($value, $format);
        }

        self::assertSame($expected, $string);
    }

    /** @return list<array{0: string, 1: string|int|DateTimeInterface|null, 2?: int|array{int, int}|string|null}> */
    public static function dataIntlDateTime(): array
    {
        return [
            ['', null],
            ['', ''],
            ['23. Okt. 2021, 11:39', '2021-10-23 11:39:30'],
            ['23. Oktober 2021 um 11:56:38 MESZ', 1634982998, IntlDateFormatter::LONG],
            ['23.10.2021, 11:56:38', 1634982998, [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]],
            ['Samstag, 7. März 1998, 9 Uhr', '1998-03-07 09:00', "EEEE, d. MMMM y, H 'Uhr'"],
            ['Samstag, 23. Oktober 2021 um 09:30:00 Mitteleuropäische Sommerzeit', new DateTime('2021-10-23 09:30'), IntlDateFormatter::FULL],
            ['Samstag, 23. Oktober 2021 um 09:30:00 Nordamerikanische Westküsten-Sommerzeit', new DateTimeImmutable('2021-10-23 09:30', new DateTimeZone('America/Los_Angeles')), IntlDateFormatter::FULL],
        ];
    }

    #[DataProvider('dataIntlDate')]
    public function testIntlDate(string $expected, string|int|DateTimeInterface|null $value, int|string|null $format = null): void
    {
        if (null === $format) {
            $string = rex_formatter::intlDate($value);
        } else {
            /** @psalm-suppress ArgumentTypeCoercion */
            $string = rex_formatter::intlDate($value, $format);
        }

        self::assertSame($expected, $string);
    }

    /** @return list<array{0: string, 1: string|int|DateTimeInterface|null, 2?: int|string|null}> */
    public static function dataIntlDate(): array
    {
        return [
            ['', null],
            ['', ''],
            ['23. Okt. 2021', '2021-10-23 11:39:30'],
            ['23. Oktober 2021', 1634982998, IntlDateFormatter::LONG],
            ['Samstag, 7. März 1998', '1998-03-07 09:00', 'EEEE, d. MMMM y'],
            ['Samstag, 23. Oktober 2021', new DateTime('2021-10-23 09:30'), IntlDateFormatter::FULL],
        ];
    }

    #[DataProvider('dataIntlTime')]
    public function testIntlTime(string $expected, string|int|DateTimeInterface|null $value, int|string|null $format = null): void
    {
        if (null === $format) {
            $string = rex_formatter::intlTime($value);
        } else {
            /** @psalm-suppress ArgumentTypeCoercion */
            $string = rex_formatter::intlTime($value, $format);
        }

        self::assertSame($expected, $string);
    }

    /** @return list<array{0: string, 1: string|int|DateTimeInterface|null, 2?: int|string|null}> */
    public static function dataIntlTime(): array
    {
        return [
            ['', null],
            ['', ''],
            ['11:39', '2021-10-23 11:39:30'],
            ['11:56:38 MESZ', 1634982998, IntlDateFormatter::LONG],
            ['9 Uhr', '1998-03-07 09:00', "H 'Uhr'"],
            ['09:30:00 Mitteleuropäische Sommerzeit', new DateTime('2021-10-23 09:30'), IntlDateFormatter::FULL],
            ['09:30:00 Nordamerikanische Westküsten-Sommerzeit', new DateTimeImmutable('2021-10-23 09:30', new DateTimeZone('America/Los_Angeles')), IntlDateFormatter::FULL],
        ];
    }

    public function testNumber(): void
    {
        $value = 1336811080.23;

        $format = [];
        self::assertEquals(
            '1 336 811 080,23',
            rex_formatter::number($value, $format),
        );

        $format = [5, ':', '`'];
        self::assertEquals(
            '1`336`811`080:23000',
            rex_formatter::number($value, $format),
        );
    }

    public function testBytes(): void
    {
        $value = 1000;

        self::assertEquals(
            '1 000,00 B',
            rex_formatter::bytes($value),
        );

        self::assertEquals(
            '976,56 KiB',
            rex_formatter::bytes($value * 1000),
        );

        self::assertEquals(
            '953,67 MiB',
            rex_formatter::bytes($value * 1000 * 1000),
        );

        // in 32 bit php the following tests use too big numbers
        if (PHP_INT_SIZE > 4) {
            self::assertEquals(
                '931,32 GiB',
                rex_formatter::bytes($value * 1000 * 1000 * 1000),
            );

            self::assertEquals(
                '909,49 TiB',
                rex_formatter::bytes($value * 1000 * 1000 * 1000 * 1000),
            );

            self::assertEquals(
                '888,18 PiB',
                rex_formatter::bytes($value * 1000 * 1000 * 1000 * 1000 * 1000),
            );

            $format = [5]; // number of signs behind comma
            self::assertEquals(
                '953,67432 MiB',
                rex_formatter::bytes($value * 1000 * 1000, $format),
            );
        }
    }

    public function testSprintf(): void
    {
        $value = 'hallo';
        $format = 'X%sX';

        self::assertEquals(
            'XhalloX',
            rex_formatter::sprintf($value, $format),
        );
    }

    public function testNl2br(): void
    {
        $value = "very\nloooooong\ntext lala";

        self::assertEquals(
            "very<br />\nloooooong<br />\ntext lala",
            rex_formatter::nl2br($value),
        );
    }

    public function testTruncate(): void
    {
        $value = 'very loooooong text lala';

        $format = [
            'length' => 10,
            'etc' => ' usw.',
            'break_words' => true,
        ];
        self::assertEquals(
            'very  usw.',
            rex_formatter::truncate($value, $format),
        );

        // XXX hmm seems not to be correct
        $format = [
            'length' => 10,
            'etc' => ' usw.',
            'break_words' => false,
        ];
        self::assertEquals(
            'very usw.',
            rex_formatter::truncate($value, $format),
        );
    }

    public function testVersion(): void
    {
        $value = '5.1.2-alpha1';

        self::assertEquals(
            '5_1',
            rex_formatter::version($value, '%s_%s'),
        );

        self::assertEquals(
            '2-1-5',
            rex_formatter::version($value, '%3$s-%2$s-%1$s'),
        );
    }

    public function testUrl(): void
    {
        $value = 'http://example.org';

        $format = [
            'attr' => ' data-haha="foo"',
            'params' => 'ilike=+1',
        ];
        self::assertEquals(
            '<a href="http://example.org?ilike=+1" data-haha="foo">http://example.org</a>',
            rex_formatter::url($value, $format),
        );
    }

    public function testEmail(): void
    {
        $value = 'dude@example.org';

        $format = [
            'attr' => ' data-haha="foo"',
            'params' => 'ilike=+1',
        ];
        self::assertEquals(
            '<a href="mailto:dude@example.org?ilike=+1" data-haha="foo">dude@example.org</a>',
            rex_formatter::email($value, $format),
        );
    }

    public function testCustom(): void
    {
        $format = 'strtoupper';
        self::assertEquals(
            'TEST',
            rex_formatter::custom('test', $format),
        );

        $format = [
            static function ($params) {
                return $params['subject'] . ' ' . $params['some'];
            },
            ['some' => 'more params'],
        ];

        self::assertEquals(
            '77 more params',
            rex_formatter::custom('77', $format),
        );
    }
}
