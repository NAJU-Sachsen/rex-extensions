<?php

class naju_article
{
    public const DEFAULT_GROUP = 'Sachsen';
    public const GROUP_ROOT_CATEGORY = 'Ortsgruppen';
    public const GROUP_PREFIX = 'NAJU ';
    public const DEFAULT_LOGO = 'naju-logo.png';
    public const EMAIL_PATTERN = '/[a-zA-Z0-9._-]+@[a-zA-Z0-9-]+\.[a-zA-Z.]{2,5}/';

    /**
     * Searches for the name of the local group to which the currently requested article belongs.
     *
     * The local group's name will be provided without prefix, i.e. if the group is called
     * "NAJU Dresden", then "Dresden" will be returned. Furthermore, if the requested article
     * is not part of the group article hierarchy, then the default group will be provided.
     */
    public static function determineCurrentLocalGroup()
    {
        $detected_group = '';
        $category = rex_category::getCurrent();

				// if we are on the root level, no group can be active
				if (!$category) {
					return self::DEFAULT_GROUP;
				}

        // firstly, check the KVS if there already is a group name stored
        // for the requested category
        $kvs_group = naju_kvs::get('category.localgroup.' . $category->getName());
        if ($kvs_group) {
            return $kvs_group;
        }

        // if not, traverse the category hierarchy to check if we are in the local-groups subtree
        // the categories directly underneath the 'Ortsgruppen' category will be the ones containing
        // the requested local group
        while ($category) {
            $parent = $category->getParent();
            if ($parent && $parent->getName() == self::GROUP_ROOT_CATEGORY) {
                $detected_group = $category->getName();
                break;
            }
            $category = $parent;
        }

        // finally, if we found the group, store it in the KVS, otherwise fall back to our default group
        if ($detected_group) {
            $group_name = self::dropLeadingPrefix($detected_group);
            naju_kvs::put('category.localgroup.' . $category->getName(), $group_name);
            return $group_name;
        } else {
            return self::DEFAULT_GROUP;
        }
    }

    /**
     * Provides the name of the logo file for the local group which owns the currently
     * requested article.
     */
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

		/**
		 * Turns all email addresses in some text into hyperlink anchors.
		 *
		 * The remainder of the text will be escaped.
		 *
     * @ProducesHTML
		 */
    public static function make_emails_anchors($text)
    {
        return preg_replace(self::EMAIL_PATTERN, '<a href="mailto:$0">$0</a>', rex_escape($text));
    }

    /**
     * Removes the common 'NAJU' prefix from a local group name, retaining only the
     * actual group portion.
     */
    private static function dropLeadingPrefix($group)
    {
        if (!$group) {
            return '';
        }

        return substr($group, strpos($group, self::GROUP_PREFIX)+strlen(self::GROUP_PREFIX));
    }

    /**
     * Provides a unified format for local group names to use in logo images.
     */
    private static function generateLogoNameForLocalGroup($group)
    {
        return rex_string::normalize($group);
    }
}
