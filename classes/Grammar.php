<?php
class Grammar {
    public static function number($num) {
        $lastNumber = substr($num, -1);
        if($num > 20 || $num < 4) {
            if($lastNumber == 1) {
                return $num.'<span class="upperth">st</span>';
            }
            else if($lastNumber == 2) {
                return $num.'<span class="upperth">nd</span>';
            }
            else if($lastNumber == 3) {
                return $num.'<span class="upperth">rd</span>';
            }
            else {
                return $num.'<span class="upperth">th</span>';
            }
        }
        else {
            return $num.'<span class="upperth">th</span>';
        }
    }
    public static function isPlural($num) {

    }
}