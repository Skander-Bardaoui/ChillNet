<?php

use App\Providers\AppServiceProvider;
use LaravelDoctrine\ORM\DoctrineServiceProvider;
use PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider as JWTAuthServiceProvider;

return [
    AppServiceProvider::class,
    DoctrineServiceProvider::class,
    JWTAuthServiceProvider::class,
];
