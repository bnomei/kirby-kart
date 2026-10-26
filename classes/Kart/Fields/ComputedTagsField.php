<?php

namespace Bnomei\Kart\Fields;

use Kirby\Cms\Language;
use Kirby\Form\Field\TagsField;
use Kirby\Toolkit\A;

abstract class ComputedTagsField extends TagsField
{
    abstract protected function catalogValues(): array;

    public function toFormValue(): array
    {
        return $this->catalogValues();
    }

    public function isSubmittable(Language $language): bool
    {
        return ! $this->isDisabled() && parent::isSubmittable($language);
    }

    public function toStoredValue(): string
    {
        return A::join($this->toFormValue(), $this->separator().' ');
    }
}
