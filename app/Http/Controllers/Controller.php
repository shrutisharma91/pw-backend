<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "API documentation for FinZ LMS backend",
    title: "FinZ LMS API",
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Local Development (127.0.0.1)'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Local Development (localhost)'
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Configured API Server'
)]
#[OA\SecurityScheme(
    securityScheme: "sanctum",
    type: "http",
    scheme: "bearer"
)]
abstract class Controller extends BaseController
{
    //
}
