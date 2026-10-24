<?php
/* Customer verification requirements —. */

/** The current rental policy row (is_current = 1), or null if none. */
function requirements_current_policy(PDO $pdo): ?array
{
    $row = $pdo->query("SELECT * FROM rental_policies WHERE is_current = 1 ORDER BY id DESC LIMIT 1")
        ->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/* Per-requirement completeness for a user. 'address' needs at least line 1 + city + province to be a usable postal address. */
function requirements_user_status(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT license_path, verification_photo_path, verification_status,
                                  phone, address_line1, address_line2, city, province, postal_code
                           FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $license = !empty($u['license_path']);
    $photo   = !empty($u['verification_photo_path']);
    $address = !empty($u['address_line1']) && !empty($u['city']) && !empty($u['province']);

    return [
        'license'  => $license,
        'photo'    => $photo,
        'address'  => $address,
        'complete' => $license && $photo && $address,
        'user'     => $u,
    ];
}

/** One-line-per-part postal address from a user row, for the booking snapshot. */
function requirements_format_address(array $u): string
{
    $parts = array_filter([
        trim((string) ($u['address_line1'] ?? '')),
        trim((string) ($u['address_line2'] ?? '')),
        trim(implode(', ', array_filter([
            trim((string) ($u['city'] ?? '')),
            trim((string) ($u['province'] ?? '')),
        ]))),
        trim((string) ($u['postal_code'] ?? '')),
    ], fn($p) => $p !== '');

    $address = implode("\n", $parts);
    $phone = trim((string) ($u['phone'] ?? ''));
    if ($phone !== '') {
        $address .= "\nPhone: " . $phone;
    }
    return $address;
}
