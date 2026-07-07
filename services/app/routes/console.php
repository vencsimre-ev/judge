<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('demo:about', function () {
    $this->info('Climbing Judge AI demo');
})->purpose('Show demo information');
