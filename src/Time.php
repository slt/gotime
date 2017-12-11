<?php namespace DCarbone\Go;

use DCarbone\Go\Time\Duration;

/**
 * Class Time
 * @package DCarbone\Go
 */
class Time {
    const Nanosecond = 1;
    const Microsecond = 1000 * self::Nanosecond;
    const Millisecond = 1000 * self::Microsecond;
    const Second = 1000 * self::Millisecond;
    const Minute = 60 * self::Second;
    const Hour = 60 * self::Minute;

    const unitMap = [
        'ns' => self::Nanosecond,
        'us' => self::Microsecond,
        'µs' => self::Microsecond,
        'μs' => self::Microsecond,
        'ms' => self::Millisecond,
        's'  => self::Second,
        'm'  => self::Minute,
        'h'  => self::Hour,
    ];

    /**
     * @param string $s
     * @return \DCarbone\Go\Time\Duration
     */
    public static function ParseDuration(string $s): Duration {
        if (0 === strlen($s)) {
            throw self::invalidDurationException($s);
        }

        $d = 0;
        $orig = $s;

        $neg = '-' === $s[0];
        // consume symbol
        if ('-' === $s[0] || '+' === $s[0]) {
            $s = substr($s, 1);
        }

        if ('0' === $s) {
            return new Duration();
        } else if ('' === $s) {
            throw self::invalidDurationException($orig);
        }

        while ('' !== $s) {
            $ord = ord($s[0]);
            // test for: period, less than 0, greater than 9
            if (48 > $ord || $ord > 57) {
                throw self::invalidDurationException($orig);
            }
            $v = 0;
            $pl = strlen($s);
            for ($i = 0; $i < $pl; $i++) {
                $ord = ord($s[$i]);
                if (48 > $ord || $ord > 57) {
                    break;
                }
                if (GOTIME_OVERFLOW_INT < $v) {
                    throw self::invalidDurationException($orig);
                }
                $v = $v * 10 + (int)$s[$i];
                if (GOTIME_OVERFLOW_INT < $v) {
                    throw self::invalidDurationException($orig);
                }
            }
            $s = substr($s, $i);
            $pre = $pl !== strlen($s);

            $post = false;
            $f = 0;
            $scale = 0;
            $overflow = false;
            if ('' !== $s && '.' === $s[0]) {
                $s = substr($s, 1);
                $pl = strlen($s);
                for ($i = 0; $i < $pl; $i++) {
                    $ord = ord($s[$i]);
                    if (48 > $ord || $ord > 57) {
                        break;
                    }
                    if ($overflow) {
                        continue;
                    }
                    if (GOTIME_OVERFLOW_INT < $f) {
                        $overflow = true;
                        continue;
                    }
                    $y = $f * 10 + (int)$ord;
                    if (0 > $y) {
                        $overflow = true;
                        continue;
                    }
                    $f = $y;
                    $scale *= 10;
                }
                $s = substr($s, $i);
                $post = $pl != strlen($s);
            }

            if (!$pre && !$post) {
                throw self::invalidDurationException($orig);
            }

            $pl = strlen($s);
            for ($i = 0; $i < $pl; $i++) {
                $ord = ord($s[$i]);
                if (46 === $ord || (48 <= $ord && $ord <= 57)) {
                    break;
                }
            }
            $u = substr($s, 0, $i);
            $unit = self::unitMap[$u] ?? null;
            if (null === $unit) {
                throw self::invalidDurationUnitException($u, $orig);
            }
            if (intdiv(PHP_INT_MAX, $unit) < $v) {
                throw self::invalidDurationException($orig);
            }
            $v *= $unit;
            if (0 < $f) {
                $v += (int)($f * ($unit / $scale));
                if (0 > $v) {
                    throw self::invalidDurationException($orig);
                }
            }

            $d += $v;
            if (0 > $d) {
                throw self::invalidDurationException($orig);
            }
            $s = substr($s, $i);
        }

        return new Duration($neg ? -$d : $d);
    }

    /**
     * @param string $orig
     * @return \InvalidArgumentException
     */
    private static function invalidDurationException(string $orig): \InvalidArgumentException {
        return new \InvalidArgumentException("Invalid duration: {$orig}");
    }

    /**
     * @param string $unit
     * @param string $orig
     * @return \InvalidArgumentException
     */
    private static function invalidDurationUnitException(string $unit, string $orig): \InvalidArgumentException {
        return new \InvalidArgumentException("Unknown unit {$unit} in duration {$orig}");
    }
}