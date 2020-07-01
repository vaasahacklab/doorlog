<?php
class FuzzyTime
{
    /**
     * Various time formats - used in calculations
     */
    private static $_time_formats = [
        [60, 'just now'],
        [90, '1 minute'],
        [3600, 'minutes', 60],
        [5400, '1 hour'],
        [86400, 'hours', 3600],
        [129600, '1 day'],
        [604800, 'days', 86400],
        [907200, '1 week'],
        [2628000, 'weeks', 604800],
        [3942000, '1 month'],
        [31536000, 'months', 2628000],
        [47304000, '1 year'],
        [3153600000, 'years', 31536000],
    ];

    /**
     * Convert date into a 'fuzzy' format: 15 minutes ago, 3 days ago, etc.
     *
     * @param string|number $date_from Unix timestamp or a string to parse to a date
     *
     * @return string Human readable relative date as string
     */
    public static function getFuzzyTime($date_from)
    {
        $now = time(); // current unix timestamp

        // if a number is passed assume it is a unix time stamp
        // if string is passed try and parse it to unix time stamp
        if (is_numeric($date_from)) {
            $dateFrom = $date_from;
        } elseif (is_string($date_from)) {
            $dateFrom   = strtotime($date_from);
        }

        // difference between now and the passed time.
        $difference = $now - $dateFrom;

        // value to return
        $val = '';

        if ($dateFrom <= 0) {
            $val = 'a long time ago';
        } else {
            // loop through each format measurement in array
            foreach (self::$_time_formats as $format) {
                // if the difference from now and passed time is less than first option in format measurment
                if ($difference < $format[0]) {
                    // if the format array item has no calculation value
                    if (count($format) == 2) {
                        $val = $format[1] . ($format[0] === 60 ? '' : ' ago');
                        break;
                    } else {
                        // divide difference by format item value to get number of units
                        $val = ceil($difference / $format[2]) . ' ' . $format[1] . ' ago';
                        break;
                    }
                }
            }
        }

        return $val;
    }
}
