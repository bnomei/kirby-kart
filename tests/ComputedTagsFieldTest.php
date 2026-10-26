<?php

use Bnomei\Kart\Fields\AllCategoriesField;
use Bnomei\Kart\Fields\AllTagsField;
use Bnomei\Kart\Fields\AllVariantsField;
use Kirby\Data\Yaml;
use Kirby\Form\Field;
use Kirby\Form\Field\TagsField;
use Kirby\Form\Fields;

it('registers computed catalog fields as Kirby field classes', function (string $type, string $class, array $expected): void {
    expect(Field::resolve($type))->toBe($class);

    $fields = new Fields([
        'catalog' => [
            'type' => $type,
            'disabled' => true,
            'translate' => false,
        ],
    ]);
    $field = $fields->field('catalog');

    expect($field)->toBeInstanceOf($class)
        ->toBeInstanceOf(TagsField::class)
        ->and($field->toFormValue())->toBe($expected)
        ->and($field->value())->toBe($expected)
        ->and($field->toArray()['type'])->toBe($type)
        ->and($field->toArray()['disabled'])->toBeTrue()
        ->and($field->toArray()['translate'])->toBeFalse()
        ->and($field->options())->toBe([])
        ->and($field->isSubmittable($fields->language()))->toBeFalse();

    $fields->fill(['catalog' => 'stale stored value']);
    expect($fields->toFormValues())->toBe(['catalog' => $expected]);

    $fields->submit(['catalog' => ['forged input']]);
    expect($fields->toFormValues())->toBe(['catalog' => $expected])
        ->and($field->toStoredValue())->toBe(implode(', ', $expected));

    $field->fill(null);
    expect($field->toFormValue())->toBe($expected);

    $field->reset();
    expect($field->toFormValue())->toBe($expected);
})->with([
    'categories' => ['allcategories', AllCategoriesField::class, ['heroes', 'ninja', 'super-villains', 'villains']],
    'tags' => ['alltags', AllTagsField::class, ['alien', 'blue', 'bo', 'claws', 'human', 'katana', 'leader', 'mutant', 'nunchucks', 'orange', 'purple', 'rat', 'red', 'rhinoceros', 'sai', 'sensei', 'staff', 'turtle', 'warthog']],
    'variants' => ['allvariants', AllVariantsField::class, []],
]);

it('displays nonempty variant values instead of stored field input', function (): void {
    $expire = kart()->option('expire');
    kart()->setOption('expire', null);
    $product = kirby()->impersonate('kirby', fn () => page('products')->createChild([
        'slug' => 'computed-variants-test',
        'template' => 'product',
        'content' => [
            'title' => 'Computed variants test',
            'variants' => Yaml::encode([
                ['variant' => 'color:blue, size:large', 'price' => 12],
                ['variant' => 'color:red, size:small', 'price' => 10],
            ]),
        ],
    ]));

    try {
        $field = new AllVariantsField(value: ['stale']);
        expect($field->toFormValue())->toBe(['color:blue', 'color:red', 'size:large', 'size:small']);
    } finally {
        kirby()->impersonate('kirby', fn () => $product->delete(true));
        kart()->setOption('expire', $expire);
    }
});
