<?php

require_once 'scanFiles.php';
require_once'play.php';

$run = new files;
$parse = new htmlParser($run->html_array);
//$parse = new htmlParser(array(array('name' => '/home/andrew/web/working/manifesto/store/100pages/supplies.htm', 'wiki_name' => 'Store100pagesSupplies')));
//var_dump($run->html_array);
