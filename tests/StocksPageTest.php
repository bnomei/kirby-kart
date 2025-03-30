<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */
it('has a blueprint from PHP', function () {
    expect(\Kirby\Data\Yaml::encode(StocksPage::phpBlueprint()))->toMatchSnapshot();
});
