<?php

declare(strict_types=1);

// A partial override of Laragraph's own package translations — copy the
// package's lang/en/errors.php as a starting point when adding a locale.
// Keys left out here (e.g. 'validation.default') fall back to English.
return [
    'authorization' => [
        'default' => 'Non autorisé.',
        'field'   => "Vous n'êtes pas autorisé à accéder à :field.",
    ],
];
