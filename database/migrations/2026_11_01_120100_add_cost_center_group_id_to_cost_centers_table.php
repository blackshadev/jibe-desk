<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    private const array GROUPS = [
        ['number' => '11', 'external_number' => '11', 'name' => 'Overige vorderingen'],
        ['number' => '31', 'external_number' => '31', 'name' => 'inkomsten lessen'],
        ['number' => '101', 'external_number' => '101', 'name' => 'inkomsten algemeen'],
    ];

    private const array COST_CENTER_GROUPS = [
        '11' => ['500'],
        '31' => ['3008'],
        '101' => ['8104', '8105', '8115', '8270'],
    ];

    public function up(): void
    {
        Schema::table('cost_centers', static function (Blueprint $table): void {
            $table->unsignedBigInteger('cost_center_group_id')->nullable();
        });

        $this->seedGroups();
        $this->assignKnownCostCenters();
        $this->assignRemainingCostCenters();

        Schema::table('cost_centers', static function (Blueprint $table): void {
            $table->unsignedBigInteger('cost_center_group_id')->nullable(false)->change();
        });

        Schema::table('cost_centers', static function (Blueprint $table): void {
            $table->index('cost_center_group_id');
            $table->foreign('cost_center_group_id')
                ->references('id')
                ->on('cost_center_groups')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', static function (Blueprint $table): void {
            $table->dropForeign(['cost_center_group_id']);
            $table->dropIndex(['cost_center_group_id']);
            $table->dropColumn('cost_center_group_id');
        });
    }

    private function seedGroups(): void
    {
        $timestamp = now();

        foreach (self::GROUPS as $group) {
            DB::table('cost_center_groups')->insertOrIgnore([
                ...$group,
                'parent_id' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    private function assignKnownCostCenters(): void
    {
        $groupIds = DB::table('cost_center_groups')
            ->whereIn('number', array_keys(self::COST_CENTER_GROUPS))
            ->pluck('id', 'number');

        foreach (self::COST_CENTER_GROUPS as $groupNumber => $costCenterNumbers) {
            DB::table('cost_centers')
                ->whereIn('number', $costCenterNumbers)
                ->update(['cost_center_group_id' => $groupIds[$groupNumber]]);
        }
    }

    private function assignRemainingCostCenters(): void
    {
        $ungroupedCount = DB::table('cost_centers')->whereNull('cost_center_group_id')->count();

        if ($ungroupedCount === 0) {
            return;
        }

        $timestamp = now();

        DB::table('cost_center_groups')->insertOrIgnore([
            'number' => '0',
            'external_number' => '0',
            'name' => 'Ongegroepeerd',
            'parent_id' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $ungroupedId = DB::table('cost_center_groups')->where('number', '0')->value('id');

        DB::table('cost_centers')
            ->whereNull('cost_center_group_id')
            ->update(['cost_center_group_id' => $ungroupedId]);

        Log::info(sprintf(
            'Assigned %d existing cost centers without an e-Captain group to "0 Ongegroepeerd".',
            $ungroupedCount,
        ));
    }
};
