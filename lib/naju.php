<?php

class naju
{
    /**
     * Escapes the given for HTML output but leaves certain codes intact.
     * 
     * Currently, this includes `&shy;` only.
     *
     * @param string $content
     * @return string
     */
    public static function escape(string $content)
    {
        return str_replace('&amp;shy;', '&shy;', rex_escape($content));
    }

}
