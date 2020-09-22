<?php

class naju_form
{

    public static function multivalues2SQL($vals)
    {
        $val = ltrim($vals, '|');
        $val = rtrim($val, '|');
        $pure_vals = explode('|', $val);
        return implode(',', $pure_vals);
    }

}
