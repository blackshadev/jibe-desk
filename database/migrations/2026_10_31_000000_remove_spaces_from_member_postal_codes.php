<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        // Postal codes are stored without a separator (1234AB), so normalize values written by earlier imports.
        DB::table('members')
            ->where('address_postalcode', 'like', '% %')
            ->update(['address_postalcode' => DB::raw("REPLACE(address_postalcode, ' ', '')")]);
    }

    public function down(): void
    {
        // The original spacing cannot be reconstructed, so this normalization is not reverted.
    }
};
