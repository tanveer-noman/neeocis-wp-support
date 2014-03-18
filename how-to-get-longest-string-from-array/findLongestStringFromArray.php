<?php 
/*
 * @author: Tanveer Noman
 * @params: array
 * @return: array
 * @description: This function can be used to find the longest string from an
 * one dimensional array. It will search the maximum lenght, key and srting
 * and will return as array.
 */
function findLongestStringFromArray($array = array()) {
 
    if(!empty($array)){
        $lengths = array_map('strlen', $array);
        $maxLength = max($lengths);
        $key = array_search($maxLength, $lengths);
        return array('length' => $maxLength,'key' => $key,'string'=>$array[$key]);
    }
}
 
$arrData = array('This is test', 'Hello world', 'What is your name?');
 
// How to use:
print_r(findLongestStringFromArray($arrData));
 
// Output:
// Array ( [length] => 18 [key] => 2 [string] => What is your name? ) 
?>
