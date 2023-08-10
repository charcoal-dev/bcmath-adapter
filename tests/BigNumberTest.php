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

use Charcoal\Adapters\BcMath\BigNumber;

/**
 * Class BigNumberTest
 */
class BigNumberTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Testing behaviour when object of BigNumber is directly treated as a string value
     * @return void
     */
    public function testAsString(): void
    {
        $bN = new BigNumber(1.23456789, 4);
        $this->assertEquals("1.2345", $bN);
        $this->assertEquals(6, strlen($bN));
    }

    /**
     * Testing "isPositive", "isNegative" and "isZero" methods.
     * @return void
     */
    public function testStates(): void
    {
        // Scale of 18
        $this->assertTrue((new BigNumber("0.000000000000000001", 18))->isPositive());
        // last digit is discarded due to scale of 17:
        $this->assertFalse((new BigNumber("0.000000000000000001", 17))->isPositive());

        $this->assertTrue((new BigNumber("0.000000000000000000", 18))->isZero());
        // last digit is actually 19th fractional part:
        $this->assertTrue((new BigNumber("0.0000000000000000001", 18))->isZero());
        $this->assertTrue((new BigNumber("0", 18))->isZero());
        $this->assertTrue((new BigNumber(0, 0))->isZero());

        $this->assertTrue((new BigNumber("-1", 0))->isNegative());
        $this->assertTrue((new BigNumber(-0.000001, 6))->isNegative());
    }

    /**
     * Testing "isInteger" and "int" value methods
     * @return void
     */
    public function testIntegers(): void
    {
        // Simple tests
        $this->assertFalse((new BigNumber("0.000"))->isInteger());
        $this->assertFalse((new BigNumber(0.001))->isInteger());
        $this->assertFalse((new BigNumber("1.20304"))->isInteger());

        // Following tests demonstrate if scale value > 0 for visibly int values,
        // constructor will automatically append N amount of fractional parts...
        $this->assertFalse((new BigNumber(1))->isInteger()); // No longer an int
        $this->assertTrue((new BigNumber(1, 0))->isInteger()); // With scale 0, it is an int
        $this->assertFalse((new BigNumber("1"))->isInteger());
        $this->assertTrue((new BigNumber("1", 0))->isInteger());
        $this->assertTrue((new BigNumber(0xffff, scale: 0))->isInteger()); // 65535
        $this->assertEquals("1.0000", (new BigNumber(1, 4))->value()); // int(1) with scale 4 becomes "1.0000" internally
        $this->assertEquals("65535.00", (new BigNumber(0xffff, 2))->value());

        // Get value as integer
        $this->assertEquals(250, (new BigNumber(0xff - 5, scale: 0))->int());
        $this->assertEquals("250", (new BigNumber(0xff - 5, scale: 0))->int());

        // Test maximum value as int
        $this->assertEquals(9223372036854775807, (new BigNumber(PHP_INT_MAX, scale: 0))->int());
    }

    /**
     * Attempt to retrieve a decimal value as int
     * @return void
     */
    public function testDecimalValueAsInt(): void
    {
        $this->expectException('OutOfBoundsException');
        (new BigNumber(1.23456))->int();
    }

    /**
     * A very big integer value that exceeds PHP_INT_MAX
     * @return void
     */
    public function testOverflowAsInt(): void
    {
        $bN = (new BigNumber(PHP_INT_MAX, 0))->add(1); // Simply add 1
        $this->assertEquals("9223372036854775808", $bN->value());
        $this->expectException('OverflowException');
        $bN->int();
    }

    /**
     * Test scaling values provided to constructor and later to arithmetic ops
     * @return void
     */
    public function testScaling(): void
    {
        $bN = new BigNumber(0, 18);
        $this->assertEquals("0.000000000000000000", $bN->value());
        $bN = $bN->add("0.12345678");
        $this->assertEquals("0.123456780000000000", $bN->value());
        $bN = $bN->mul(2);
        $this->assertEquals("0.246913560000000000", $bN->value());
        $bN = $bN->mul(4, scale: 3);
        $this->assertEquals("0.987", $bN->value());
        $this->assertEquals("0.98701", $bN->add("0.000012345", scale: 5)->value());
    }

    /**
     * @return void
     */
    public function testSerialization(): void
    {
        $bn1 = new BigNumber(1.2345678, 4);
        $bn1s = serialize($bn1);
        $bn1b = unserialize($bn1s);
        $this->assertTrue($bn1->equals($bn1b));

        /** @var BigNumber $bn2 */
        $bn2 = unserialize(serialize((new BigNumber("-1.03", 4))));
        $this->assertEquals("-1.0300", $bn2->value());

        /** @var BigNumber $bn3 */
        $bn3 = unserialize(serialize((new BigNumber("0.000000000000000138", scale: 18))));
        $this->assertEquals("0.000000000000000138", $bn3->value());
    }

    /**
     * Test the static method BigNumber::toString()
     * @return void
     */
    public function testBigNumber2String(): void
    {
        $this->assertEquals("0", BigNumber::toString(0.00));
        $this->assertEquals("0", (new BigNumber(0.00, scale: 0))->value());
        $this->assertEquals("0.00", (new BigNumber(0.0, scale: 2))->value());

        $this->assertEquals("0.00000001", BigNumber::toString(0.00000001));
        $this->assertEquals("0.00000001", BigNumber::toString("0.00000001"));
        $this->assertEquals("0.00000001", BigNumber::toString(1e-8));
        $this->assertEquals("0.00000233", BigNumber::toString(0.233e-5));

        $this->assertEquals("3000000", BigNumber::toString(0.3e+7));
        $this->assertEquals("3000000", BigNumber::toString(3e+6));
        $this->assertEquals("3430000000", BigNumber::toString(3.43e+9));
        $this->assertEquals("3430000000", BigNumber::toString("3.43e+9"));
        $this->assertEquals("1.61803398875", BigNumber::toString(1.61803398875e+0));

        $this->assertEquals("12", BigNumber::toString(1.2e+1));
        $this->assertEquals("12", BigNumber::toString(1.2e1));
        $this->assertEquals("0.12", BigNumber::toString(12e-2));
        $this->assertEquals("0.12", BigNumber::toString("12e-2"));

        $this->assertEquals("191919", BigNumber::toString(new BigNumber(191919, scale: 0)));
        $this->assertEquals("19.919", BigNumber::toString(new BigNumber(19.919, scale: 3)));
    }

    /**
     * Test comparison operators
     * @return void
     */
    public function testCompares(): void
    {
        $bN1 = new BigNumber("1.0034");
        $this->assertTrue($bN1->equals("1.0034"));
        $this->assertTrue($bN1->greaterThanOrEquals("1.0034"));
        $this->assertTrue($bN1->lessThanOrEquals("1.0034"));

        $this->assertFalse($bN1->greaterThan("1.0034000000001"));
        $this->assertTrue($bN1->greaterThan("1.00339"));

        $this->assertTrue($bN1->lessThan("1.00340000001"));
        $this->assertTrue($bN1->lessThan("1.0035"));
    }

    /**
     * Test simple arithmetic operations
     * @return void
     */
    public function testArithmeticOps(): void
    {
        $bN1 = new BigNumber(PHP_INT_MAX, scale: 4);
        $bN2 = $bN1->add("3.001234");
        $this->assertEquals("9223372036854775810.0012", $bN2->value());
        $bN3 = $bN2->mul(3.5);
        $this->assertEquals("32281802128991715335.0042", $bN3->value());
        $bN4 = $bN3->sub("32281802128991715335", scale: 6);
        $this->assertEquals("0.004200", $bN4->value());

        $bN5 = (new BigNumber(5.34))->divByExp(10, 8);
        $this->assertEquals("0.000000053400000000", $bN5->value());
        $bN6 = (new BigNumber(25351, scale: 0))->divByExp(10, 8, scale: 12);
        $this->assertEquals("0.000253510000", $bN6->value());
        $this->assertEquals("0.00025351", $bN6->value(8));

        $bN7 = (new BigNumber("3.00304567", scale: 8))->mulByExp(10, 8, scale: 0);
        $this->assertEquals(300304567, $bN7->int());

        $bN8 = $bN7->div(2.22, scale: 4);
        $this->assertEquals("135272327.4774", $bN8->value());
    }

    /**
     * Test optional trimming of fractional digits for "value" method
     * @return void
     */
    public function testOutputs(): void
    {
        $bN = new BigNumber(1.2345678, scale: 4);
        $this->assertEquals("1.2345", $bN->value());

        $bN1 = new BigNumber(0, scale: 18);
        $this->assertEquals("0.000000", $bN1->value(6));
        $this->assertEquals("0.000000000000000000", $bN1->value());

        $bN2 = new BigNumber(0.12345678, scale: 18);
        $this->assertEquals("0.1234", $bN2->value(4));
        $this->assertEquals("0.12", $bN2->value(2));
        $this->assertEquals("0.123456", $bN2->value(6));

        $bN3 = new BigNumber(0.102300456, scale: 18);
        $this->assertEquals("0.102300456000000000", $bN3->value());
        $this->assertEquals("0.1023004", $bN3->value(fracDigits: 7));
        $this->assertEquals("0.102300456", $bN3->value(fracDigits: 7, trimToSize: false));
        $this->assertEquals("0.102300456000", $bN3->value(fracDigits: 12));
        $this->assertEquals("0.102300456000", $bN3->value(fracDigits: 12, trimToSize: false));

        $bN4 = new BigNumber("0.103", scale: 18);
        $this->assertEquals("0.103000", $bN4->value(6));
        $this->assertEquals("0.103000", $bN4->value(6, false));
    }
}
