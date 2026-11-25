<?php

declare(strict_types=1);

namespace WPMCP\Api\V1;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The public module API version (semver) and the compiled registry format
 * the kernel reads. A minor version is additive only; a new registry format
 * is a new integer, checked when a module root is added.
 */
final class Version
{
    public const VERSION = '1.0.0';

    public const REGISTRY_FORMAT = 1;

    private function __construct()
    {
    }
}
