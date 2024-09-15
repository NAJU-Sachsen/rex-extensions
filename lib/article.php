<?php

class naju_article
{
    public const DEFAULT_GROUP = 'Sachsen';
    public const GROUP_ROOT_CATEGORY = 'Ortsgruppen';
    public const GROUP_PREFIX = 'NAJU ';
    public const DEFAULT_LOGO = 'naju-logo.png';
    public const EMAIL_PATTERN = '/[a-zA-Z0-9._-]+@[a-zA-Z0-9-]+\.[a-zA-Z.]{2,5}/';
    public const LINK_PATTERN = '/http(s)?:\/\/[a-zA-Z0-9_\-\.%]+\.[a-zA-Z0-9_\-\.%]+([a-zA-Z0-9_\-\/])*(\.[a-zA-Z0-9_\-\.%]+)?(\?^[\s"\']+)?/';

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
        $kvs_group = naju_kvs::get('category.localgroup.' . rex_string::normalize($category->getName()));
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
            naju_kvs::put('category.localgroup.' . rex_string::normalize($category->getName()), $group_name);
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
                $file = $group_logo['group_logo'] ? $group_logo['group_logo'] : self::DEFAULT_LOGO;
            }

            naju_kvs::put('logo.' . $logo_name, $file);
            return $file;
        }
    }

    /**
     * @see makeEmailsAnchors()
     * @deprecated
     */
    public static function make_emails_anchors($text)
    {
        return self::makeEmailsAnchors($text);
    }

    /**
     * Turns all email addresses in some text into hyperlink anchors.
     *
     * The remainder of the text will be left as is.
     *
     * @ProducesHTML
     */
    public static function makeEmailsAnchors($text)
    {
        return preg_replace(self::EMAIL_PATTERN, '<a href="mailto:$0">$0</a>', $text);
    }

    /**
     * Turns all links in some text into hyperlink anchors.
     *
     * The remainder of the text will be left as is.
     *
     * @ProducesHTML
     */
    public static function makeLinksAnchors($text)
    {
        return preg_replace(self::LINK_PATTERN, '<a href="$0">$0</a>', $text);
    }

    /**
     * Enhances some `text` by turning all links and email addresses into proper
     * hyperlinks.
     *
     * The reaminder of the text will be left as is.
     *
     * @ProducesHTML
     */
    public static function richFormatText($text)
    {
        return self::makeLinksAnchors(self::makeEmailsAnchors($text));
    }

    /**
     * Removes the common 'NAJU' prefix from a local group name, retaining only the
     * actual group portion.
     */
    private static function dropLeadingPrefix($group)
    {
        if (!$group) {
            return '';
        } elseif (str_starts_with($group, self::GROUP_PREFIX)) {
            return substr($group, strlen(self::GROUP_PREFIX));
        } else {
            return $group;
        }
    }

    /**
     * Provides a unified format for local group names to use in logo images.
     */
    private static function generateLogoNameForLocalGroup($group)
    {
        return rex_string::normalize($group);
    }

    public static function getCurrent() : naju_article
    {
        return new naju_article(rex_article::getCurrentId());
    }

    public static function get(int $id) : naju_article
    {
        return new naju_article($id);
    }

    private rex_article $rex_art;

    protected function __construct($id)
    {
        $this->rex_art = rex_article::get($id);
    }

    public function getId() : int
    {
        return $this->rex_art->getId();
    }

    public function getLocalGroupId() : int
    {
        $candidate_ids = array();
        foreach ($this->rex_art->getParentTree() as $parent_cat) {
            $candidate_ids[] = $parent_cat->getId();
        }

        if (!$candidate_ids) {
            return -1;
        }

        $sql = rex_sql::factory();
        $sql->setTable('naju_local_group')
            ->setWhere('group_link IN (' . $sql->in($candidate_ids) . ')')
            ->select('group_id, group_name');

        $results = $sql->getArray();
        if (count($results) > 1) {
            // found multiple local groups -- this should never happen
            rex_logger::factory()->error('Found multiple matching local groups for article', ['article' => $this->rex_art]);
            return -1;
        }

        $group_id = -1;
        if ($results) {
            $group_id = $results[0]['group_id'];
        }

        return $group_id;
    }

    public function updateArticleMetadata() : void
    {
        $group_id = $this->getLocalGroupId();

        $group_name = 'NAJU Sachsen';
        if ($group_id > 0) {
            $group_name = rex_sql::factory()
                ->setTable('naju_local_group')
                ->setWhere(['group_id' => $group_id])
                ->select('group_name')
                ->getValue('group_name');
        }

        rex_sql::factory()
            ->setTable('rex_article')
            ->setWhere(['id' => $this->getId()])
            ->setValue('art_local_group', $group_id)
            ->setValue('art_group_name', $group_name)
            ->update();
    }
}
