<?php

class naju_navigation
{

	public const SUBNAV_DEPTH_LIMIT = 3;

	/**
   * Constructs a nav menu consisting of all the sub-categories under the given
	 * category.
   *
	 * The nav will be at most SUBNAV_DEPTH_LIMIT levels deep.
	 *
	 * Categories which are under the category which is currently active (i.e. to
	 * which the currently displayed article belongs), will receive an extra
	 * 'active' class.
   *
	 * @ProducesHTML
	 */
	public static function inflate_subnav($cat, $active_ids, $depth = 1)
	{
	    // for the currently active category we will display all sub-categories, too
	    // if any of these categories is active, it will be styled accordingly

			$subnav = '';
	    $is_active = in_array($cat->getId(), $active_ids) ? 'active' : '';

	    if ($is_active) {
	        $sub_cats = $cat->getChildren(true);
	        if ($sub_cats) {
	            $depth += 1;
	            $subnav .= '<ul class="nav sub-nav flex-column">';
	            foreach ($sub_cats as $sub_cat) {
	                $is_subcat_active = in_array($sub_cat->getId(), $active_ids) ? 'active' : '';
	                $subcat_name = rex_escape($sub_cat->getValue('catname'));

	                $subnav .= '<li class="nav-item">';
	                $subnav .= "<a href='{$sub_cat->getUrl()}' class='nav-link $is_subcat_active'>$subcat_name</a>";

	                if ($depth < self::SUBNAV_DEPTH_LIMIT) {
	                    $subnav .= self::inflate_subnav($sub_cat, $active_ids, $depth);
	                }

	                $subnav .= '</li>';
	            }
	            $subnav .= '</ul>';
	        }
	    }

			return $subnav;
	}

	/**
   * Provides the category's ID which contains the currently displayed article
	 * along with all parent categories.
	 */
	public static function collect_active_category_ids()
	{
		$active_ids = array();

		$active_ids_collector = rex_category::getCurrent();

		if (!$active_ids_collector) {
			return $active_ids;
    }

		$active_ids[] = $active_ids_collector->getId();
		while ($active_ids_collector = $active_ids_collector->getParent()) {
				$active_ids[] = $active_ids_collector->getId();
		}

		return $active_ids;
	}

}
