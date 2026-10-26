<?php

namespace Bnomei\Kart\Fields;

class AllVariantsField extends ComputedTagsField
{
    protected function catalogValues(): array
    {
        return kart()->allVariants();
    }
}
