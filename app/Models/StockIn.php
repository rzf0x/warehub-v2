<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'user_id',
        'period_id',
        'invoice_number',
        'date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockInItem::class);
    }

    /**
     * Encode integer ID into a short, URL-safe, HMAC-signed opaque token (non-enumerable).
     */
    public static function encodeRouteKey(int $id): string
    {
        $key = (string) config('app.key', 'warehub-stock-in-key');
        $idBytes = pack('N', $id);
        $mac = substr(hash_hmac('sha256', 'stock-in:' . $id, $key, true), 0, 12);

        return rtrim(strtr(base64_encode($idBytes . $mac), '+/', '-_'), '=');
    }

    /**
     * Decode and verify an opaque StockIn route key. Returns null if invalid or tampered.
     */
    public static function decodeRouteKey(string $token): ?int
    {
        $token = trim($token);
        if ($token === '' || ctype_digit($token)) {
            return null;
        }

        $padded = strtr($token, '-_', '+/');
        $mod = strlen($padded) % 4;
        if ($mod > 0) {
            $padded .= str_repeat('=', 4 - $mod);
        }

        $raw = base64_decode($padded, true);
        if ($raw === false || strlen($raw) !== 16) {
            return null;
        }

        $unpacked = unpack('Nid', substr($raw, 0, 4));
        $id = (int) ($unpacked['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $providedMac = substr($raw, 4, 12);
        $key = (string) config('app.key', 'warehub-stock-in-key');
        $expectedMac = substr(hash_hmac('sha256', 'stock-in:' . $id, $key, true), 0, 12);

        if (!hash_equals($expectedMac, $providedMac)) {
            return null;
        }

        return $id;
    }
}
