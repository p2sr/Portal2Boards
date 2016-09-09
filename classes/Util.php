<?php
class Util {
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

}