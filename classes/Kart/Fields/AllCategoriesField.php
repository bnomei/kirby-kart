<?php

namespace Bnomei\Kart\Fields;

class AllCategoriesField extends ComputedTagsField
{
    protected function catalogValues(): array
    {
        return kart()->allCategories();
    }
}
