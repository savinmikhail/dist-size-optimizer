<?php

declare(strict_types=1);

return array_values(array_unique(array_merge(
    require __DIR__ . '/export-ignore.safe.php',
    require __DIR__ . '/export-ignore.review.php',
)));
