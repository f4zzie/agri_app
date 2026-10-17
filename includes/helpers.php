<?php
/**
 * AgriTrack – small view helpers shared by pages.
 */

/** Escape for HTML output. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Translated category name (falls back to the stored English name). */
function t_category(?string $name): string
{
    if ($name === null || $name === '') {
        return '—';
    }
    $key = 'cat_' . trim((string) preg_replace('/[^a-z]+/', '_', strtolower($name)), '_');
    $tr  = t($key);
    return $tr === $key ? $name : $tr;
}

/** Badge for listing availability. */
function status_badge(string $status): string
{
    $cls = $status === 'available' ? 'bg-success' : 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . e(t('status_' . $status)) . '</span>';
}

/** Badge for the optional growth stage (empty string when not tracked). */
function stage_badge(?string $stage): string
{
    if ($stage === null || $stage === '') {
        return '';
    }
    $cls = ['planted' => 'bg-info text-dark', 'growing' => 'bg-primary', 'harvested' => 'bg-warning text-dark', 'failed' => 'bg-danger'][$stage] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '"><i class="bi bi-flower1 me-1"></i>' . e(t($stage)) . '</span>';
}

/** "KSh 1,250.00 / kg" or "Negotiable". */
function format_price($price, ?string $unit): string
{
    if ($price === null || $price === '') {
        return e(t('negotiable'));
    }
    return 'KSh ' . number_format((float) $price, 2) . ' / ' . e(t('unit_' . ($unit ?: 'kg')));
}

/** Public URL of an uploaded product photo, or null if missing/unsafe. */
function upload_url(?string $file, string $subdir = ''): ?string
{
    if (!App\Upload::isSafeStoredName($file)) {
        return null;
    }
    $rel = 'uploads/' . ($subdir !== '' ? $subdir . '/' : '') . $file;
    return is_file(__DIR__ . '/../public/' . $rel) ? $rel : null;
}

/** Placeholder emoji for listings without a photo, by category. */
function product_emoji(?string $category): string
{
    return [
        'Grains & Cereals' => '🌽', 'Vegetables' => '🥬', 'Fruits' => '🥭', 'Dairy & Eggs' => '🥛',
        'Meat & Poultry' => '🐔', 'Herbs & Spices' => '🌶️', 'Legumes & Pulses' => '🫘', 'Roots & Tubers' => '🥔',
    ][$category ?? ''] ?? '🌿';
}

/** Green WhatsApp button (empty string when the number is not a Kenyan mobile). */
function whatsapp_button(?string $phone, string $message, string $classes = 'btn btn-sm btn-whatsapp'): string
{
    $url = App\Phone::whatsappUrl($phone, $message);
    if ($url === null) {
        return '';
    }
    return '<a href="' . e($url) . '" class="' . e($classes) . '" target="_blank" rel="noopener">'
         . '<i class="bi bi-whatsapp me-1"></i>' . e(t('whatsapp')) . '</a>';
}
