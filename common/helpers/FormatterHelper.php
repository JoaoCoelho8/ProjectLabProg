<?php
/**
 * Created by PhpStorm.
 * User: J. Coelho
 * Date: 05/01/2019
 * Time: 10:30
 */
namespace common\helpers;

class FormatterHelper {

    /**
     * Function used to format numbers converting 1500.23 into 1 500,23 for instance.
     * @param type $number - Number to be formatted.
     * @return type - Number formatted.
     */
    public static function displayNumber($number) {
        return number_format($number, 2, ',', ' ') . " €";
    }

    /**
     * Function used to format numbers according to PHP's default.
     * @param type $number - Number to be formatted.
     * @return type - Number formatted.
     */
    public static function formatNumber($number, $precision = 2) {
        return floatval(number_format($number, $precision, '.', ''));
    }
}