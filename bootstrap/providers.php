<?php

use App\Providers\AppServiceProvider;
use PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider as JWTAuthServiceProvider;

return [
    AppServiceProvider::class,
    JWTAuthServiceProvider::class,
];
