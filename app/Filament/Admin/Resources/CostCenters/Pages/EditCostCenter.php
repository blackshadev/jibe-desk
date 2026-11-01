<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Pages;

use App\Filament\Admin\Resources\CostCenters\CostCenterResource;
use App\Filament\Admin\Utils\CostCenterGroupHierarchySection;
use App\Models\CostCenter;
use App\Models\CostCenterGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Override;
use Webmozart\Assert\Assert;

final class EditCostCenter extends EditRecord
{
    #[Override]
    protected static string $resource = CostCenterResource::class;

    #[Override]
    public function content(Schema $schema): Schema
    {
        $record = $this->getRecord();
        Assert::isInstanceOf($record, CostCenter::class);

        $group = $record->costCenterGroup;
        Assert::isInstanceOf($group, CostCenterGroup::class);

        return $schema->components([
            CostCenterGroupHierarchySection::make($group),
            $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
