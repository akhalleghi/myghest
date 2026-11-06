<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ExcelUnicodeCell;
use PHPUnit\Framework\TestCase;

final class ExcelUnicodeCellTest extends TestCase
{
    public function test_identifiers_keep_leading_zeros_and_long_digits(): void
    {
        $this->assertSame("'09123456789", ExcelUnicodeCell::clean('09123456789'));
        $this->assertSame("'1234567890123456", ExcelUnicodeCell::clean('1234567890123456'));
        $this->assertSame('950000', ExcelUnicodeCell::clean('950000'));
        $this->assertSame('-950000', ExcelUnicodeCell::clean('-950000'));
    }

    public function test_formulas_and_cell_separators_are_neutralized(): void
    {
        $this->assertSame("'=1+1", ExcelUnicodeCell::clean('=1+1'));
        $this->assertSame("' +SUM(A1)", ExcelUnicodeCell::clean(' +SUM(A1)'));
        $this->assertSame('a b c d', ExcelUnicodeCell::clean("a\tb\rc\nd"));
    }
}
