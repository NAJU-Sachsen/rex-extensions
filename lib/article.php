<?php

class naju_article
{
    public const DEFAULT_GROUP = 'Sachsen';
    public const GROUP_ROOT_CATEGORY = 'Ortsgruppen';
    public const GROUP_PREFIX = 'NAJU ';
    public const DEFAULT_LOGO = 'naju-logo.png';

    public static function determineCurrentLocalGroup()
    {
        $detected_group = '';
        $category = rex_category::getCurrent();

        while ($category) {
            $parent = $category->getParent();
            if ($parent && $parent->getName() == self::GROUP_ROOT_CATEGORY) {
                $detected_group = $category->getName();
                break;
            }
            $category = $parent;
        }

        return $detected_group ? self::dropLeadingPrefix($detected_group) : self::DEFAULT_GROUP;
    }

    public static function getLogoForCurrentLocalGroup()
    {
        $local_group = self::determineCurrentLocalGroup();
        $logo_name = self::generateLogoNameForLocalGroup($local_group);

        // check the KVS if the logo was already requested and cached, otherwise do so manually
        $file = naju_kvs::get('logo.' . $logo_name);
        echo "<!-- file $file -->";
        if ($file) {
            return $file;
        } else {
            $sql = rex_sql::factory()
                ->setQuery("SELECT group_logo FROM naju_local_group WHERE group_name LIKE CONCAT('%', :group, '%')",
                    ['group' => $local_group]);
            $group_logo = $sql->getArray();

            if (!$sql->getRows()) {
                // if no groups matched, use the default logo
                $file = self::DEFAULT_LOGO;
            } else {
                // if some group matched, but it does not have a logo, use the default one as well
                $group_logo = $group_logo[0];
                $file = $group_logo['group_logo'] ?? self::DEFAULT_LOGO;
            }

            naju_kvs::put('logo.' . $logo_name, $file);
            return $file;
        }
    }

    private static function dropLeadingPrefix($group)
    {
        if (!$group) {
            return '';
        }

        return substr($group, strpos($group, self::GROUP_PREFIX)+strlen(self::GROUP_PREFIX));
    }

    private static function generateLogoNameForLocalGroup($group)
    {
        return rex_string::normalize($group);
    }
}
