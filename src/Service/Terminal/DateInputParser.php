<?php

namespace App\Service\Terminal;

final class DateInputParser
{
    /**
     * Parse a flexible date input used in terminal wizards / flags.
     *
     * Supported:
     *  - "" (empty):
     *      - if $defaultDaysIfEmpty !== null -> base + defaultDaysIfEmpty
     *      - else -> null
     *  - "10" -> 10 days from $base (default: today)
     *  - "today", "tomorrow"
     *  - "mon", "tue", "fri"     -> this weekday (or next if already passed)
     *  - "next mon"              -> next Monday
     *  - "mon-2", "fri+1"        -> next Monday/Friday + N weeks (N >= 0)
     *  - "dd/mm/yyyy"
     *  - "yyyy-mm-dd"
     *
     * Returns \DateTimeImmutable on success, null if cannot parse.
     */
    public function parseFlexibleDate(
        ?string $raw,
        ?\DateTimeInterface $base = null,
        ?int $defaultDaysIfEmpty = null
    ): ?\DateTimeImmutable {
        $raw = $raw ?? '';
        $raw = trim($raw);
        $base = $base ? \DateTimeImmutable::createFromInterface($base) : new \DateTimeImmutable('today');

        // Empty value
        if ($raw === '') {
            if ($defaultDaysIfEmpty !== null) {
                return $base->modify('+' . $defaultDaysIfEmpty . ' days');
            }

            return null;
        }

        // Pure number => days from now
        if (ctype_digit($raw)) {
            $days = (int) $raw;
            return $base->modify('+' . $days . ' days');
        }

        // Smart weekday-like / keyword expressions
        $smart = $this->parseSmartExpression($raw, $base);
        if ($smart) {
            return $smart;
        }

        // Fallback: explicit date formats
        $byFormat = $this->parseByFormat($raw);
        if ($byFormat) {
            return $byFormat;
        }

        return null;
    }

    /**
     * Handle things like:
     *  - today, tomorrow
     *  - mon, tue, fri
     *  - next mon
     *  - mon-2, fri+1 (weeks)
     */
    private function parseSmartExpression(string $raw, \DateTimeImmutable $base): ?\DateTimeImmutable
    {
        $raw = strtolower(trim($raw));

        if ($raw === 'today') {
            return $base;
        }

        if ($raw === 'tomorrow') {
            return $base->modify('+1 day');
        }

        // "next mon"
        if (preg_match('#^next\s+(mon|tue|wed|thu|fri|sat|sun)$#', $raw, $m)) {
            $weekday = $m[1];
            return $base->modify("next $weekday");
        }

        // "mon-2" or "fri+1"  => next <weekday> + N weeks
        if (preg_match('#^(mon|tue|wed|thu|fri|sat|sun)([+-])(\d+)$#', $raw, $m)) {
            $weekday  = $m[1];
            $weeks    = (int) $m[3];

            // start from next weekday
            $target = $base->modify("next $weekday");
            // then push weeks (sign not really meaningful; always future)
            if ($weeks > 0) {
                $target = $target->modify('+' . $weeks . ' weeks');
            }

            return $target;
        }

        // "mon", "fri" -> this weekday or next if passed
        if (preg_match('#^(mon|tue|wed|thu|fri|sat|sun)$#', $raw, $m)) {
            $weekday = $m[1];
            $target  = $base->modify("this $weekday");

            // if "this weekday" is before today, move one week ahead
            if ($target < $base) {
                $target = $target->modify('+1 week');
            }

            return $target;
        }

        return null;
    }

    /**
     * dd/mm/yyyy or yyyy-mm-dd
     */
    private function parseByFormat(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);

        // dd/mm/yyyy
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $raw)) {
            $d = \DateTimeImmutable::createFromFormat('d/m/Y', $raw);
            return $d ?: null;
        }

        // yyyy-mm-dd
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $raw)) {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
            return $d ?: null;
        }

        return null;
    }
}
