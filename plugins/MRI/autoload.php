<?php

/**
 * Autoloader for the MRI classes under plugins/MRI/src. Keeps the folder self-contained, so it can be copied or
 * mounted as a whole without Composer changes.
 */

spl_autoload_register(function (string $class): void {
    foreach (['LibreBooking\\Mri\\', 'LibreBooking\\Limesurvey\\', 'LibreBooking\\Studies\\'] as $prefix) {
        if (str_starts_with($class, $prefix)) {
            $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen('LibreBooking\\'))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
