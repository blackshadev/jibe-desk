<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenterGroups\Pages;

use App\Filament\Admin\Resources\CostCenterGroups\CostCenterGroupResource;
use App\Filament\Admin\Utils\CostCenterGroupHierarchySection;
use App\Models\CostCenterGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Override;
use Webmozart\Assert\Assert;

final class EditCostCenterGroup extends EditRecord
{
    #[Override]
    protected static string $resource = CostCenterGroupResource::class;

    #[Override]
    public function content(Schema $schema): Schema
    {
        $record = $this->getRecord();
        Assert::isInstanceOf($record, CostCenterGroup::class);

        return $schema->components([
            CostCenterGroupHierarchySection::make($record),
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
