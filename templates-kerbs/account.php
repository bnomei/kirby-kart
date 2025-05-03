<?php

if (! kirby()->user()) {
    go('/kart/login');
}
kerbs();
