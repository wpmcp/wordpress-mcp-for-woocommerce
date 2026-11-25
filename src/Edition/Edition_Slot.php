<?php

declare(strict_types=1);

namespace WPMCP\Edition;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One contributor to the plugin's edition slots: a named place in the boot
 * and ability registration sequence where an edition (the Pro build, or a
 * separately installed pack) adds its part. The free build has no
 * contributor, so every slot is a no-op there.
 */
interface Edition_Slot
{
    /**
     * Run the contributor's code for $slot.
     *
     * @return bool true when this contributor handled the slot
     */
    public function run(string $slot, Edition_Context $context): bool;
}
