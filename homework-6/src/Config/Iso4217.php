<?php

declare(strict_types=1);

namespace BankingPipeline\Config;

/**
 * ISO 4217 active currency codes (alphabetic codes only).
 *
 * This is the complete set of currently active ISO 4217 currency codes as of
 * the 2025 edition. Historical / withdrawn codes (e.g. DEM, FRF) are excluded
 * intentionally — they are not valid for new transactions.
 *
 * Source: ISO 4217 Maintenance Agency (https://www.iso.org/iso-4217-currency-codes.html)
 *
 * Design decision (Task 3):
 *   A hardcoded constant set was chosen over a Composer library (e.g. brick/money)
 *   because:
 *   1. The spec explicitly anticipates this approach ("an ISO 4217 set living in Config").
 *   2. brick/math (already installed) handles arithmetic; brick/money adds currency
 *      objects and formatting that are not needed for validation-only work.
 *   3. A PHP constant array is zero-runtime-cost, fully inspectable, and has no
 *      external dependency surface.
 *   4. The ISO 4217 active-currency list is stable; updates are infrequent and easy
 *      to apply in this file.
 */
final class Iso4217
{
    /**
     * Set of all currently active ISO 4217 alphabetic currency codes.
     *
     * Keys are the 3-letter uppercase codes; values are true (used as a set).
     *
     * @var array<string, true>
     */
    public const CODES = [
        'AED' => true, // UAE Dirham
        'AFN' => true, // Afghan Afghani
        'ALL' => true, // Albanian Lek
        'AMD' => true, // Armenian Dram
        'ANG' => true, // Netherlands Antillean Guilder
        'AOA' => true, // Angolan Kwanza
        'ARS' => true, // Argentine Peso
        'AUD' => true, // Australian Dollar
        'AWG' => true, // Aruban Florin
        'AZN' => true, // Azerbaijani Manat
        'BAM' => true, // Bosnia and Herzegovina Convertible Mark
        'BBD' => true, // Barbados Dollar
        'BDT' => true, // Bangladeshi Taka
        'BGN' => true, // Bulgarian Lev
        'BHD' => true, // Bahraini Dinar
        'BIF' => true, // Burundian Franc
        'BMD' => true, // Bermudian Dollar
        'BND' => true, // Brunei Dollar
        'BOB' => true, // Boliviano
        'BOV' => true, // Bolivian Mvdol (funds code)
        'BRL' => true, // Brazilian Real
        'BSD' => true, // Bahamian Dollar
        'BTN' => true, // Bhutanese Ngultrum
        'BWP' => true, // Botswana Pula
        'BYN' => true, // Belarusian Ruble
        'BZD' => true, // Belize Dollar
        'CAD' => true, // Canadian Dollar
        'CDF' => true, // Congolese Franc
        'CHE' => true, // WIR Euro (complementary currency)
        'CHF' => true, // Swiss Franc
        'CHW' => true, // WIR Franc (complementary currency)
        'CLF' => true, // Chilean Unit of Account (UF)
        'CLP' => true, // Chilean Peso
        'CNY' => true, // Renminbi (Chinese Yuan)
        'COP' => true, // Colombian Peso
        'COU' => true, // Unidad de Valor Real (UVR)
        'CRC' => true, // Costa Rican Colon
        'CUC' => true, // Cuban Convertible Peso
        'CUP' => true, // Cuban Peso
        'CVE' => true, // Cape Verdean Escudo
        'CZK' => true, // Czech Koruna
        'DJF' => true, // Djiboutian Franc
        'DKK' => true, // Danish Krone
        'DOP' => true, // Dominican Peso
        'DZD' => true, // Algerian Dinar
        'EGP' => true, // Egyptian Pound
        'ERN' => true, // Eritrean Nakfa
        'ETB' => true, // Ethiopian Birr
        'EUR' => true, // Euro
        'FJD' => true, // Fiji Dollar
        'FKP' => true, // Falkland Islands Pound
        'GBP' => true, // Pound Sterling
        'GEL' => true, // Georgian Lari
        'GHS' => true, // Ghanaian Cedi
        'GIP' => true, // Gibraltar Pound
        'GMD' => true, // Gambian Dalasi
        'GNF' => true, // Guinean Franc
        'GTQ' => true, // Guatemalan Quetzal
        'GYD' => true, // Guyanese Dollar
        'HKD' => true, // Hong Kong Dollar
        'HNL' => true, // Honduran Lempira
        'HTG' => true, // Haitian Gourde
        'HUF' => true, // Hungarian Forint
        'IDR' => true, // Indonesian Rupiah
        'ILS' => true, // Israeli New Shekel
        'INR' => true, // Indian Rupee
        'IQD' => true, // Iraqi Dinar
        'IRR' => true, // Iranian Rial
        'ISK' => true, // Icelandic Krona
        'JMD' => true, // Jamaican Dollar
        'JOD' => true, // Jordanian Dinar
        'JPY' => true, // Japanese Yen
        'KES' => true, // Kenyan Shilling
        'KGS' => true, // Kyrgyzstani Som
        'KHR' => true, // Cambodian Riel
        'KMF' => true, // Comorian Franc
        'KPW' => true, // North Korean Won
        'KRW' => true, // South Korean Won
        'KWD' => true, // Kuwaiti Dinar
        'KYD' => true, // Cayman Islands Dollar
        'KZT' => true, // Kazakhstani Tenge
        'LAK' => true, // Lao Kip
        'LBP' => true, // Lebanese Pound
        'LKR' => true, // Sri Lanka Rupee
        'LRD' => true, // Liberian Dollar
        'LSL' => true, // Lesotho Loti
        'LYD' => true, // Libyan Dinar
        'MAD' => true, // Moroccan Dirham
        'MDL' => true, // Moldovan Leu
        'MGA' => true, // Malagasy Ariary
        'MKD' => true, // Macedonian Denar
        'MMK' => true, // Myanmar Kyat
        'MNT' => true, // Mongolian Tugrik
        'MOP' => true, // Macanese Pataca
        'MRU' => true, // Mauritanian Ouguiya
        'MUR' => true, // Mauritian Rupee
        'MVR' => true, // Maldivian Rufiyaa
        'MWK' => true, // Malawian Kwacha
        'MXN' => true, // Mexican Peso
        'MXV' => true, // Mexican Unidad de Inversion (UDI)
        'MYR' => true, // Malaysian Ringgit
        'MZN' => true, // Mozambican Metical
        'NAD' => true, // Namibian Dollar
        'NGN' => true, // Nigerian Naira
        'NIO' => true, // Nicaraguan Cordoba
        'NOK' => true, // Norwegian Krone
        'NPR' => true, // Nepalese Rupee
        'NZD' => true, // New Zealand Dollar
        'OMR' => true, // Omani Rial
        'PAB' => true, // Panamanian Balboa
        'PEN' => true, // Peruvian Sol
        'PGK' => true, // Papua New Guinean Kina
        'PHP' => true, // Philippine Peso
        'PKR' => true, // Pakistani Rupee
        'PLN' => true, // Polish Zloty
        'PYG' => true, // Paraguayan Guarani
        'QAR' => true, // Qatari Riyal
        'RON' => true, // Romanian Leu
        'RSD' => true, // Serbian Dinar
        'RUB' => true, // Russian Ruble
        'RWF' => true, // Rwandan Franc
        'SAR' => true, // Saudi Riyal
        'SBD' => true, // Solomon Islands Dollar
        'SCR' => true, // Seychellois Rupee
        'SDG' => true, // Sudanese Pound
        'SEK' => true, // Swedish Krona
        'SGD' => true, // Singapore Dollar
        'SHP' => true, // Saint Helena Pound
        'SLE' => true, // Sierra Leonean Leone (new)
        'SLL' => true, // Sierra Leonean Leone (old, still active)
        'SOS' => true, // Somali Shilling
        'SRD' => true, // Surinamese Dollar
        'SSP' => true, // South Sudanese Pound
        'STN' => true, // Sao Tome and Principe Dobra
        'SVC' => true, // Salvadoran Colon
        'SYP' => true, // Syrian Pound
        'SZL' => true, // Swazi Lilangeni
        'THB' => true, // Thai Baht
        'TJS' => true, // Tajikistani Somoni
        'TMT' => true, // Turkmenistan Manat
        'TND' => true, // Tunisian Dinar
        'TOP' => true, // Tongan Pa'anga
        'TRY' => true, // Turkish Lira
        'TTD' => true, // Trinidad and Tobago Dollar
        'TWD' => true, // New Taiwan Dollar
        'TZS' => true, // Tanzanian Shilling
        'UAH' => true, // Ukrainian Hryvnia
        'UGX' => true, // Ugandan Shilling
        'USD' => true, // United States Dollar
        'USN' => true, // US Dollar (Next day)
        'UYI' => true, // Uruguay Peso en Unidades Indexadas (URUIURUI)
        'UYU' => true, // Uruguayan Peso
        'UYW' => true, // Unidad Previsional
        'UZS' => true, // Uzbekistan Som
        'VED' => true, // Bolívar Soberano (digital)
        'VES' => true, // Bolívar Soberano
        'VND' => true, // Vietnamese Dong
        'VUV' => true, // Vanuatu Vatu
        'WST' => true, // Samoan Tala
        'XAF' => true, // CFA Franc BEAC
        'XAG' => true, // Silver (one troy ounce)
        'XAU' => true, // Gold (one troy ounce)
        'XBA' => true, // European Composite Unit (EURCO)
        'XBB' => true, // European Monetary Unit (E.M.U.-6)
        'XBC' => true, // European Unit of Account 9 (E.U.A.-9)
        'XBD' => true, // European Unit of Account 17 (E.U.A.-17)
        'XCD' => true, // East Caribbean Dollar
        'XDR' => true, // Special Drawing Rights
        'XOF' => true, // CFA Franc BCEAO
        'XPD' => true, // Palladium (one troy ounce)
        'XPF' => true, // CFP Franc (Franc Pacifique)
        'XPT' => true, // Platinum (one troy ounce)
        'XSU' => true, // SUCRE
        'XTS' => true, // Reserved for testing
        'XUA' => true, // ADB Unit of Account
        'XXX' => true, // No currency
        'YER' => true, // Yemeni Rial
        'ZAR' => true, // South African Rand
        'ZMW' => true, // Zambian Kwacha
        'ZWL' => true, // Zimbabwean Dollar
    ];

    /**
     * Return true if the given code is a valid active ISO 4217 currency code.
     */
    public static function isValid(string $code): bool
    {
        return isset(self::CODES[$code]);
    }
}
