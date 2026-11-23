<?php

namespace App\Support;

final class City
{
    /**
     * Province names (and common abbreviations) that must not be used as a city.
     * Provinces whose name is also a city (e.g. Jambi, Bengkulu, Gorontalo) are
     * intentionally excluded so the city can still be entered.
     *
     * @var array<int, string>
     */
    private const PROVINCES = [
        'aceh', 'nanggroe aceh darussalam', 'nad',
        'sumatera utara', 'sumatra utara', 'sumut',
        'sumatera barat', 'sumatra barat', 'sumbar',
        'sumatera selatan', 'sumatra selatan', 'sumsel',
        'riau', 'kepulauan riau', 'kepri',
        'kepulauan bangka belitung', 'bangka belitung', 'babel',
        'lampung',
        'banten',
        'dki jakarta', 'dki',
        'jawa barat', 'jabar',
        'jawa tengah', 'jateng',
        'jawa timur', 'jatim',
        'di yogyakarta', 'diy', 'daerah istimewa yogyakarta',
        'bali',
        'nusa tenggara barat', 'ntb',
        'nusa tenggara timur', 'ntt',
        'kalimantan barat', 'kalbar',
        'kalimantan tengah', 'kalteng',
        'kalimantan selatan', 'kalsel',
        'kalimantan timur', 'kaltim',
        'kalimantan utara', 'kaltara',
        'sulawesi utara', 'sulut',
        'sulawesi tengah', 'sulteng',
        'sulawesi selatan', 'sulsel',
        'sulawesi tenggara', 'sultra',
        'sulawesi barat', 'sulbar',
        'maluku', 'maluku utara',
        'papua', 'papua barat', 'papua barat daya', 'papua tengah', 'papua pegunungan', 'papua selatan',
        'central java', 'west java', 'east java', 'west sumatra', 'north sumatra', 'south sumatra',
    ];

    /**
     * Trim, collapse whitespace and title-case so the same city is always stored
     * the same way (e.g. "  bekasi " and "BEKASI" both become "Bekasi").
     */
    public static function normalize(string $raw): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');

        return mb_convert_case(mb_strtolower($value), MB_CASE_TITLE, 'UTF-8');
    }

    public static function isValid(string $raw): bool
    {
        $value = mb_strtolower(self::normalize($raw));

        if (mb_strlen($value) < 2 || mb_strlen($value) > 100) {
            return false;
        }

        if (str_starts_with($value, 'provinsi ') || str_starts_with($value, 'prov. ') || str_starts_with($value, 'province ')) {
            return false;
        }

        return ! in_array($value, self::PROVINCES, true);
    }

    public static function validationMessage(): string
    {
        return 'Kota/Wilayah harus nama kota (contoh: Bekasi, Solo, Semarang), bukan nama provinsi.';
    }
}
