<?php

namespace Omnipulse\Tests;

use Omnipulse\Exception\InvalidConfigException;
use Omnipulse\Http\Json;
use Omnipulse\Model\Income;
use Omnipulse\Model\Money;
use Omnipulse\Model\Period;
use Omnipulse\Model\Sponsor;
use PHPUnit\Framework\TestCase;

final class FundingModelTest extends TestCase
{
    public function testMoneyFromADecimalAndBack(): void
    {
        self::assertSame(63628, Money::fromDecimal('636.28', 'eur')->cents);
        self::assertSame('EUR', Money::fromDecimal('636.28', 'eur')->currency);
        self::assertSame('636.28', Money::fromDecimal(636.28, 'EUR')->decimal());
        self::assertSame('5.00', Money::fromDecimal(5, 'USD')->decimal());
        self::assertSame('0.07', (new Money(7, 'USD'))->decimal());
        self::assertSame('-1.50', (new Money(-150, 'USD'))->decimal());
    }

    public function testMoneyRefusesWhatIsNotACurrencyOrAnAmount(): void
    {
        $this->expectException(InvalidConfigException::class);
        new Money(100, 'euro');
    }

    public function testMoneyRefusesWhatIsNotAnAmount(): void
    {
        $this->expectException(InvalidConfigException::class);
        Money::fromDecimal('a lot', 'EUR');
    }

    public function testAnIncomeByTheMonth(): void
    {
        self::assertSame('52.00', (new Income(new Money(1200, 'EUR'), Period::Week))->monthly()?->decimal());
        self::assertSame('10.00', (new Income(new Money(12000, 'EUR'), Period::Year))->monthly()?->decimal());
        self::assertSame('7.00', (new Income(new Money(700, 'EUR'), Period::Month))->monthly()?->decimal());
        self::assertNull((new Income(new Money(700, 'EUR'), Period::Once))->monthly());
    }

    public function testASponsorIsShownByNameOrHandle(): void
    {
        self::assertSame('Ada Lovelace', (new Sponsor('ada', 'Ada Lovelace'))->label());
        self::assertSame('ada', (new Sponsor('ada'))->label());
        self::assertSame('2016-10-15T15:47:40+00:00', (new Sponsor('ada', since: new \DateTimeImmutable('2016-10-15T15:47:40.951Z')))->toArray()['since']);
    }

    public function testTypedReadsOfAnAnswer(): void
    {
        $data = ['a' => ['b' => ['c' => '3', 'd' => true, 'e' => [1, 2], 'f' => '', 'g' => 'not a date']]];

        self::assertSame(3, Json::int($data, 'a', 'b', 'c'));
        self::assertSame('3', Json::string($data, 'a', 'b', 'c'));
        self::assertTrue(Json::bool($data, 'a', 'b', 'd'));
        self::assertFalse(Json::bool($data, 'a', 'b', 'c'), 'only true is true');
        self::assertSame([1, 2], Json::list($data, 'a', 'b', 'e'));
        self::assertSame([], Json::list($data, 'a', 'x'));
        self::assertNull(Json::string($data, 'a', 'b', 'f'), 'an empty string is no value');
        self::assertNull(Json::date($data, 'a', 'b', 'g'));
        self::assertNull(Json::get($data, 'a', 'b', 'c', 'deeper'));
    }
}
