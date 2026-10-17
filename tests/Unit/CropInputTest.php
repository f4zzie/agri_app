<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\CropInput;
use PHPUnit\Framework\TestCase;

final class CropInputTest extends TestCase
{
    private const CATEGORIES = [1 => 'Grains & Cereals', 2 => 'Vegetables'];

    private function valid(array $override = []): array
    {
        return $override + [
            'name' => '  Maize ', 'variety' => 'H614', 'category' => '1', 'price' => '45.50', 'unit' => 'kg',
            'quantity' => '100', 'status' => 'available', 'growth_stage' => 'growing',
            'planting_date' => '2026-03-01', 'expected_harvest' => '2026-07-15', 'notes' => '',
        ];
    }

    public function testValidInputIsNormalised(): void
    {
        $r = CropInput::fromPost($this->valid(), self::CATEGORIES);

        self::assertSame([], $r['errors']);
        self::assertSame('Maize', $r['data']['name']);
        self::assertSame(45.5, $r['data']['price']);
        self::assertSame(100, $r['data']['quantity']);
        self::assertSame(1, $r['data']['category_id']);
        self::assertSame('growing', $r['data']['growth_stage']);
        self::assertNull($r['data']['notes']);
    }

    public function testBlankOptionalFieldsBecomeNull(): void
    {
        $r = CropInput::fromPost(['name' => 'Kale'], self::CATEGORIES);

        self::assertSame([], $r['errors']);
        self::assertNull($r['data']['price']);          // negotiable
        self::assertNull($r['data']['growth_stage']);   // not tracked
        self::assertSame('available', $r['data']['status']);
        self::assertSame('kg', $r['data']['unit']);
    }

    public function testNameIsRequired(): void
    {
        self::assertContains('err_name_required', CropInput::fromPost($this->valid(['name' => '   ']), self::CATEGORIES)['errors']);
    }

    public function testPriceMustBePositive(): void
    {
        foreach (['0', '-5', 'abc'] as $bad) {
            self::assertContains('err_price_invalid', CropInput::fromPost($this->valid(['price' => $bad]), self::CATEGORIES)['errors'], $bad);
        }
    }

    public function testQuantityMustBeAWholeNumber(): void
    {
        self::assertContains('err_quantity_invalid', CropInput::fromPost($this->valid(['quantity' => '2.5']), self::CATEGORIES)['errors']);
        self::assertContains('err_quantity_invalid', CropInput::fromPost($this->valid(['quantity' => '-1']), self::CATEGORIES)['errors']);
    }

    public function testHarvestCannotPrecedePlanting(): void
    {
        $r = CropInput::fromPost($this->valid(['planting_date' => '2026-05-01', 'expected_harvest' => '2026-04-01']), self::CATEGORIES);

        self::assertContains('err_harvest_before_planting', $r['errors']);
    }

    public function testImpossibleDatesAreRejected(): void
    {
        $r = CropInput::fromPost($this->valid(['planting_date' => '2026-02-30']), self::CATEGORIES);

        self::assertContains('err_planting_date_invalid', $r['errors']);
    }

    public function testUnknownEnumValuesFallBackSafely(): void
    {
        $r = CropInput::fromPost($this->valid(['unit' => 'furlong', 'status' => 'sold', 'growth_stage' => 'x', 'category' => '99']), self::CATEGORIES);

        self::assertSame('kg', $r['data']['unit']);
        self::assertSame('available', $r['data']['status']);
        self::assertNull($r['data']['growth_stage']);
        self::assertNull($r['data']['category_id']);
    }

    public function testFormFromRowRoundTrips(): void
    {
        $form = CropInput::formFromRow(['name' => 'Beans', 'category_id' => 2, 'price' => '120.00', 'unit' => 'kg', 'status' => 'available']);

        self::assertSame('2', $form['category']);
        self::assertSame('Beans', $form['name']);
        self::assertSame('', $form['growth_stage']);
    }
}
