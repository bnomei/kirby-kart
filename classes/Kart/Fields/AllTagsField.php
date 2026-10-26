<?php

namespace Bnomei\Kart\Fields;

class AllTagsField extends ComputedTagsField
{
    protected function catalogValues(): array
    {
        return kart()->allTags();
    }
}
