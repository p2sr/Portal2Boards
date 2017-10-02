<?php
class Util {

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

     public static function uMin($data1, $data2, $callback) {
         $result = $data2;
         if ($data1 != NULL) {
             if ($data2 != NULL)
                 $result = $callback($data1) < $callback($data2) ? $data1 : $data2;
             else
                 $result = $data1;
         }
         return $result;
     }

     public static function uMax($data1, $data2, $callback) {
         $result = $data2;
         if ($data1 != NULL) {
             if ($data2 != NULL)
                 $result = $callback($data1) > $callback($data2) ? $data1 : $data2;
             else
                 $result = $data1;
         }
         return $result;
     }

     public static function daysAgo($days) {
         $timestamp = time();
         $tm = (60 * 60 * 24) * $days;
         return $timestamp - $tm;
     }

     public static function escapeQuotesHTML($str) {
        return htmlspecialchars(htmlspecialchars($str, ENT_QUOTES));
     }

     public static function formatPoints($points) {
         if ($points >= 1000)
             return number_format($points, 0, "." , "");
         if ($points >= 100)
             return number_format($points, 1, "." , "");
         if ($points > 10)
             return number_format($points, 2, "." , "");
         else
             return number_format($points, 3, "." , "");

     }

}