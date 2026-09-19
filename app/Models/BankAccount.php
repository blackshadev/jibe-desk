<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property string $name
 * @property string $iban
 * @property string|null $bic
 * @property bool $active
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
final class BankAccount extends Model
{
    use HasFactory;

    /** @return HasMany<BankTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    /** @return HasMany<BankStatement, $this> */
    public function statements(): HasMany
    {
        return $this->hasMany(BankStatement::class);
    }

    /** @return HasMany<BankAccountYearBalance, $this> */
    public function yearBalances(): HasMany
    {
        return $this->hasMany(BankAccountYearBalance::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public static function normalizeIban(string $iban): string
    {
        return strtoupper(str_replace(' ', '', $iban));
    }

    protected function setIbanAttribute(string $value): void
    {
        $this->attributes['iban'] = self::normalizeIban($value);
    }
}
