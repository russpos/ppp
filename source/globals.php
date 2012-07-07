<?php

$operator = array('(', ',', ')', '=', '->', '+', '-');
$reserved_words = array('try', 'else', 'class');
$reserved_std = array('public', 'extends', 'const', 'private', 'throw', 'static', 'new');

$booleans = array('yes', 'no', 'on', 'off', 'nil', 'none', 'true', 'false');
$synonyms = array(
    'on'   => 'true',
    'yes'  => 'true',
    'off'  => 'false',
    'no'   => 'false',
    'nil'  => 'null',
    'none' => 'null',
);
$synonym_list = array_keys($synonyms);


