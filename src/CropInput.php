<?php

declare(strict_types=1);

namespace App;

/**
 * Validates and normalises the product-listing form shared by
 * add_crop.php and edit_crop.php. Pure function: no DB, no globals,
 * so it is unit-tested directly.
 */
final class CropInput
{
    public const STATUSES      = ['available', 'out_of_stock'];
    public const GROWTH_STAGES = ['planted', 'growing', 'harvested', 'failed'];
    public const UNITS         = ['kg', 'bag', 'crate', 'litre', 'dozen', 'piece', 'tonne', 'bundle'];

    /**
     * @param array<string,mixed> $post        raw $_POST
     * @param array<int,string>   $categoryIds valid category id => name
     * @return array{data: array<string,mixed>, form: array<string,string>, errors: list<string>}
     *         data   – values ready for the database (nulls for blanks)
     *         form   – trimmed strings to re-populate the form
     *         errors – translation keys of validation failures
     */
    public static function fromPost(array $post, array $categoryIds): array
    {
        $f = [];
        foreach (['name', 'variety', 'category', 'price', 'unit', 'quantity', 'status',
                  'growth_stage', 'planting_date', 'expected_harvest', 'notes'] as $k) {
            $f[$k] = trim((string) ($post[$k] ?? ''));
        }

        $errors = [];

        if ($f['name'] === '') {
            $errors[] = 'err_name_required';
        } elseif (mb_strlen($f['name']) > 100) {
            $errors[] = 'err_name_too_long';
        }
        if (mb_strlen($f['variety']) > 100) {
            $errors[] = 'err_variety_too_long';
        }

        $price = null;
        if ($f['price'] !== '') {
            if (!is_numeric($f['price']) || (float) $f['price'] <= 0 || (float) $f['price'] >= 100000000) {
                $errors[] = 'err_price_invalid';
            } else {
                $price = round((float) $f['price'], 2);
            }
        }

        $quantity = null;
        if ($f['quantity'] !== '') {
            if (!ctype_digit($f['quantity']) || (int) $f['quantity'] > 2000000000) {
                $errors[] = 'err_quantity_invalid';
            } else {
                $quantity = (int) $f['quantity'];
            }
        }

        $unit   = in_array($f['unit'], self::UNITS, true) ? $f['unit'] : 'kg';
        $status = in_array($f['status'], self::STATUSES, true) ? $f['status'] : 'available';
        $stage  = in_array($f['growth_stage'], self::GROWTH_STAGES, true) ? $f['growth_stage'] : null;
        $catId  = isset($categoryIds[(int) $f['category']]) ? (int) $f['category'] : null;

        $planting = self::date($f['planting_date']);
        $harvest  = self::date($f['expected_harvest']);
        if ($f['planting_date'] !== '' && $planting === null) {
            $errors[] = 'err_planting_date_invalid';
        }
        if ($f['expected_harvest'] !== '' && $harvest === null) {
            $errors[] = 'err_harvest_date_invalid';
        }
        if ($planting !== null && $harvest !== null && $harvest < $planting) {
            $errors[] = 'err_harvest_before_planting';
        }

        return [
            'data' => [
                'name'             => $f['name'],
                'variety'          => $f['variety'] !== '' ? $f['variety'] : null,
                'category_id'      => $catId,
                'price'            => $price,
                'unit'             => $unit,
                'quantity'         => $quantity,
                'status'           => $status,
                'growth_stage'     => $stage,
                'planting_date'    => $planting,
                'expected_harvest' => $harvest,
                'notes'            => $f['notes'] !== '' ? $f['notes'] : null,
            ],
            'form'   => $f,
            'errors' => $errors,
        ];
    }

    /** Form values for an existing DB row. @param array<string,mixed> $row @return array<string,string> */
    public static function formFromRow(array $row): array
    {
        $f = [];
        foreach (['name', 'variety', 'price', 'unit', 'quantity', 'status', 'growth_stage',
                  'planting_date', 'expected_harvest', 'notes'] as $k) {
            $f[$k] = (string) ($row[$k] ?? '');
        }
        $f['category'] = (string) ($row['category_id'] ?? '');
        return $f;
    }

    /** @return array<string,string> */
    public static function emptyForm(): array
    {
        return self::formFromRow(['unit' => 'kg', 'status' => 'available']);
    }

    /** Strict Y-m-d parser; returns the normalised date or null. */
    private static function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return ($d && $d->format('Y-m-d') === $value) ? $value : null;
    }
}
