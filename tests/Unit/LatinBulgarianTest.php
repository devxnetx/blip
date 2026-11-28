<?php

namespace Tests\Unit;

use App\Support\LatinBulgarian;
use PHPUnit\Framework\TestCase;

class LatinBulgarianTest extends TestCase
{
    public function test_latin_words_gain_a_cyrillic_spelling(): void
    {
        $this->assertSame(['koncert', 'концерт'], LatinBulgarian::forms('koncert'));
        $this->assertSame('концерт', LatinBulgarian::toCyrillic('kontsert'));
        $this->assertSame('софия', LatinBulgarian::toCyrillic('sofia'));
        $this->assertSame('софия', LatinBulgarian::toCyrillic('sofiya'));
        $this->assertSame('чалга', LatinBulgarian::toCyrillic('chalga'));
        $this->assertSame('диана', LatinBulgarian::toCyrillic('diana'));
        $this->assertSame('тия', LatinBulgarian::toCyrillic('tiq'));
        $this->assertSame(
            LatinBulgarian::fold('Велико Търново'),
            LatinBulgarian::fold('veliko tarnovo'),
        );
        $this->assertSame(['Концерт'], LatinBulgarian::forms('Концерт'));
    }
}
