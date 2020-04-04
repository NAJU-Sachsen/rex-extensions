<?php

if (rex::isBackend()) {
    rex_extension::register('MEDIA_UPLOAD', ['naju_image', 'imageMayBeUploaded'], rex_extension::LATE);
    rex_extension::register('MEDIA_UPLOAD', ['naju_image', 'imageMayBeUploaded'], rex_extension::LATE);
}
