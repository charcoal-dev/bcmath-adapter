<?php
/*
 * This file is a part of "charcoal-dev/bcmath-adapter" package.
 * https://github.com/charcoal-dev/bcmath-adapter
 *
 * Copyright (c) Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/charcoal-dev/bcmath-adapter/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Charcoal\Adapters\BcMath;

/**
 * Class BigNumber
 * @package Charcoal\Adapters\BcMath
 */
class BigNumber implements \Stringable
{
    /** @var string */
    private string $value;
    /** @var int */
    private int $scale;

    /**
     * @param string|int|float $value
     * @param int $scale
     */
    public function __construct(string|int|float $value, int $scale = 18)
    {
        $this->changeScale($scale);
        $this->value = bcmul($this->checkValidNum($value), "1", $scale);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            "value" => $this->value,
            "scale" => $this->scale
        ];
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            "value" => $this->value,
            "scale" => $this->scale
        ];
    }

    /**
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->value = $this->checkValidNum($data["value"]);
        $this->changeScale($data["scale"]);
    }

    /**
     * @param int $scale
     * @return $this
     */
    public function changeScale(int $scale): self
    {
        if ($scale < 0) {
            throw new \InvalidArgumentException('BcMath scale value must be a positive integer');
        }

        $this->scale = $scale;
        return $this;
    }

    /**
     * Gets value as string
     * - If fracDigits argument is > 0, value will be set to N amount of fractional digits after decimal pointer,
     *  if not enough then further zeros will be added to the value.
     *    - A value of "0.000000000000000000" (18 scale) will become 0.0000 with retain argument set to 4
     *    - A value of "0.102300456000000000" will become "0.1023004" if trimToSize is TRUE otherwise "0.102300456"
     * @param int|null $fracDigits
     * @param bool $trimToSize
     * @return string
     */
    public function value(?int $fracDigits = null, bool $trimToSize = true): string
    {
        if (!$fracDigits || $fracDigits < 0 || $this->isInteger()) {
            return $this->value;
        }

        if ($trimToSize) {
            return bcmul($this->value, "1", $fracDigits);
        }

        $split = explode(".", rtrim(rtrim($this->value, "0"), "."));
        $fractional = $split[1] ?? "";
        $retain = $fracDigits - strlen($fractional);
        if ($retain > 0) {
            return $split[0] . "." . $fractional . str_repeat("0", $retain);
        }

        return $split[0] . "." . $fractional;
    }

    /**
     * Retrieves any integer value (that is <= PHP_INT_MAX) as int
     * @return int
     */
    public function int(): int
    {
        if (!$this->isInteger()) {
            throw new \OutOfBoundsException('BigNumber value is not an integer');
        }

        if (bccomp($this->value, strval(PHP_INT_MAX), 0) === 1) {
            throw new \OverflowException('BigNumber value exceeds PHP_INT_MAX');
        }

        return intval($this->value);
    }

    /**
     * Checks if value is integral (does not have decimals)
     * @return bool
     */
    public function isInteger(): bool
    {
        return (bool)preg_match('/^-?(0|[1-9]+[0-9]*)$/', $this->value);
    }

    /**
     * Checks if value is zero
     * @return bool
     */
    public function isZero(): bool
    {
        return bccomp($this->value, "0", $this->useScale()) === 0;
    }

    /**
     * Checks if value is greater than zero
     * @return bool
     */
    public function isPositive(): bool
    {
        return bccomp($this->value, "0", $this->useScale()) === 1;
    }

    /**
     * Checks if value is less than zero
     * @return bool
     */
    public function isNegative(): bool
    {
        return bccomp($this->value, "0", $this->useScale()) === -1;
    }

    /**
     * Compare number with another
     * @param string|float|int|BigNumber $comp
     * @param int|null $scale
     * @return int
     */
    public function cmp(string|float|int|BigNumber $comp, ?int $scale = null): int
    {
        $comp = $this->checkValidNum($comp);
        return bccomp($this->value, $comp, $this->useScale($scale));
    }

    /**
     * Compares value with a number to check if both are equal
     * @param string|float|int|BigNumber $comp
     * @param int|null $scale
     * @return bool
     */
    public function equals(string|float|int|BigNumber $comp, ?int $scale = null): bool
    {
        return $this->cmp($comp, $scale) === 0;
    }

    /**
     * Compares value with a number to check if value is greater than argument
     * @param string|float|int|BigNumber $comp
     * @param int|null $scale
     * @return bool
     */
    public function greaterThan(string|float|int|BigNumber $comp, ?int $scale = null): bool
    {
        return $this->cmp($comp, $scale) > 0;
    }

    /**
     * Compares value with a number to check if value is greater than or equals argument
     * @param string|float|int|BigNumber $comp
     * @param int|null $scale
     * @return bool
     */
    public function greaterThanOrEquals(string|float|int|BigNumber $comp, ?int $scale = null): bool
    {
        return $this->cmp($comp, $scale) >= 0;
    }

    /**
     * Compares value with a number to check if value is less than argument
     * @param string|float|int|BigNumber $comp
     * @param int|null $scale
     * @return bool
     */
    public function lessThan(string|float|int|BigNumber $comp, ?int $scale = null): bool
    {
        return $this->cmp($comp, $scale) < 0;
    }

    /**
     * Compares value with a number to check if value is less than or equals argument
     * @param string|float|int|BigNumber $comp
     * @param int|null $scale
     * @return bool
     */
    public function lessThanOrEquals(string|float|int|BigNumber $comp, ?int $scale = null): bool
    {
        return $this->cmp($comp, $scale) <= 0;
    }

    /**
     * Checks if value is within (or equals) given min and max arguments
     * @param string|float|int|BigNumber $min
     * @param string|float|int|BigNumber $max
     * @param int|null $scale
     * @return bool
     */
    public function inRange(string|float|int|BigNumber $min, string|float|int|BigNumber $max, ?int $scale = null): bool
    {
        $scale = $this->useScale($scale);
        if (bccomp($this->value, $this->checkValidNum($min), $scale) !== -1) {
            if (bccomp($this->value, $this->checkValidNum($max), $scale) !== 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string|float|int|BigNumber $num
     * @param int|null $scale
     * @return $this
     */
    public function add(string|float|int|BigNumber $num, ?int $scale = null): static
    {
        $scale = $this->useScale($scale);
        return new static(bcadd($this->value, $this->checkValidNum($num), $scale), $scale);
    }

    /**
     * @param string|float|int|BigNumber $num
     * @param int|null $scale
     * @return $this
     */
    public function sub(string|float|int|BigNumber $num, ?int $scale = null): static
    {
        $scale = $this->useScale($scale);
        return new static(bcsub($this->value, $this->checkValidNum($num), $scale), $scale);
    }

    /**
     * @param string|float|int|BigNumber $num
     * @param int|null $scale
     * @return $this
     */
    public function mul(string|float|int|BigNumber $num, ?int $scale = null): static
    {
        $scale = $this->useScale($scale);
        return new static(bcmul($this->value, $this->checkValidNum($num), $scale), $scale);
    }

    /**
     * @param int $base
     * @param int $exponent
     * @param int|null $scale
     * @return $this
     */
    public function mulByExp(int $base, int $exponent, ?int $scale = null): static
    {
        $scale = $this->useScale($scale);
        if ($base < 1) {
            throw new \InvalidArgumentException('Value for param "base" must be a positive integer');
        } elseif ($exponent < 1) {
            throw new \InvalidArgumentException('Value for param "exponent" must be a positive integer');
        }

        return new static(bcmul($this->value, bcpow(strval($base), strval($exponent), 0), $scale), $scale);
    }

    /**
     * @param string|float|int|BigNumber $num
     * @param int|null $scale
     * @return $this
     */
    public function div(string|float|int|BigNumber $num, ?int $scale = null): static
    {
        $scale = $this->useScale($scale);
        return new static(bcdiv($this->value, $this->checkValidNum($num), $scale), $scale);
    }

    /**
     * @param int $base
     * @param int $exponent
     * @param int|null $scale
     * @return $this
     */
    public function divByExp(int $base, int $exponent, ?int $scale = null): static
    {
        $scale = $this->useScale($scale);
        if ($base < 1) {
            throw new \InvalidArgumentException('Value for param "base" must be a positive integer');
        } elseif ($exponent < 1) {
            throw new \InvalidArgumentException('Value for param "exponent" must be a positive integer');
        }

        return new static(bcdiv($this->value, bcpow(strval($base), strval($exponent), 0), $scale), $scale);
    }

    /**
     * @param string|float|int|BigNumber $num
     * @param int|null $scale
     * @return $this
     */
    public function pow(string|float|int|BigNumber $num, ?int $scale = null): static
    {
        $scale = $this->useScale($scale);
        return new static(bcpow($this->value, $this->checkValidNum($num), $scale), $scale);
    }

    /**
     * @param string|float|int|BigNumber $divisor
     * @param int|null $scale
     * @return $this
     */
    public function mod(string|float|int|BigNumber $divisor, ?int $scale = null): static
    {
        $scale = $this->useScale($scale);
        return new static(bcmod($this->value, $this->checkValidNum($divisor), $scale), $scale);
    }

    /**
     * @param $divisor
     * @param int|null $scale
     * @return $this
     */
    public function remainder($divisor, ?int $scale = null): static
    {
        return $this->mod($divisor, $scale);
    }

    /**
     * @return $this
     */
    public function copy(): static
    {
        return new static($this->value, $this->scale);
    }

    /**
     * @param int|null $scale
     * @return int
     */
    private function useScale(?int $scale = null): int
    {
        return is_int($scale) && $scale >= 0 ? $scale : $this->scale;
    }

    /**
     * Checks and accepts Integers, Double/Float values or numeric Strings for BcMath operations
     * @param string|float|int|BigNumber $value
     * @return string
     */
    private function checkValidNum(string|float|int|BigNumber $value): string
    {
        $value = static::toString($value);
        if (is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Invalid argument; Expecting a valid numeric value');
    }

    /**
     * Checks if argument is a valid number, returns string if numeric value is valid otherwise NULL.
     * - Converts integer and floats values to strings to safely worth with Bcmath.
     * - Expands floats with scientific e-notations.
     * - Numeric values may be signed (negative values).
     * @param mixed $value
     * @return string|null
     */
    public static function toString(mixed $value): ?string
    {
        if ($value instanceof BigNumber) {
            return $value->value();
        }

        // Integers are obviously valid numbers
        if (is_int($value)) {
            return strval($value);
        }

        // Floats must be checked for scientific E-notations
        if (is_float($value)) {
            $value = strval($value);
        }

        // Resolve scientific notations
        if (preg_match('/e-/i', $value)) {
            // Auto-detect decimals
            $decimals = preg_split('/e-/i', $value);
            $decimals = strlen($decimals[0]) + intval($decimals[1]);
            $value = rtrim(number_format(floatval($value), $decimals, ".", ""), "0");
        } elseif (preg_match('/e\+?/i', $value)) {
            $value = number_format(floatval($value), 0, "", "");
        }

        // Check with in String
        if (is_string($value)) {
            if (preg_match('/^-?(0|[1-9]+[0-9]*)(\.[0-9]+)?$/', $value)) {
                return $value;
            }
        }

        return null;
    }
}