<?php

class naju_form
{

    public static function multivalues2SQL(string $vals, string $prefix = '', string $suffix = ''): string
    {
        $val = ltrim($vals, '|');
        $val = rtrim($val, '|');
        $pure_vals = explode('|', $val);
        return $prefix . implode(',', $pure_vals) . $suffix;
    }

}
