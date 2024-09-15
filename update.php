<?php

if (rex_version::compare($this->getVersion(), '2.0', '<')) {
    rex_metainfo_add_field('Ortsgruppen-Id (bitte nicht ändern)', 'art_local_group', 98, '', 1, '-1');
    rex_metainfo_add_field('Ortsgruppen-Name (bitte nicht ändern)', 'art_group_name', 99, '', 1, 'NAJU Sachsen');
}
