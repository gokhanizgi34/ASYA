<?php

namespace App;

enum ZodiacSign: string
{
    case Aries = 'aries';
    case Taurus = 'taurus';
    case Gemini = 'gemini';
    case Cancer = 'cancer';
    case Leo = 'leo';
    case Virgo = 'virgo';
    case Libra = 'libra';
    case Scorpio = 'scorpio';
    case Sagittarius = 'sagittarius';
    case Capricorn = 'capricorn';
    case Aquarius = 'aquarius';
    case Pisces = 'pisces';

    public function label(): string
    {
        return match ($this) {
            self::Aries => 'Koç', self::Taurus => 'Boğa', self::Gemini => 'İkizler',
            self::Cancer => 'Yengeç', self::Leo => 'Aslan', self::Virgo => 'Başak',
            self::Libra => 'Terazi', self::Scorpio => 'Akrep', self::Sagittarius => 'Yay',
            self::Capricorn => 'Oğlak', self::Aquarius => 'Kova', self::Pisces => 'Balık',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Aries => '♈', self::Taurus => '♉', self::Gemini => '♊',
            self::Cancer => '♋', self::Leo => '♌', self::Virgo => '♍',
            self::Libra => '♎', self::Scorpio => '♏', self::Sagittarius => '♐',
            self::Capricorn => '♑', self::Aquarius => '♒', self::Pisces => '♓',
        };
    }
}
