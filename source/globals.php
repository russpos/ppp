<?php

$punctuation = array(':', '(', ',', ')', '.', '=', '->', '[', ']', '{', '}', '+', '<-', '-');
$reserved_words = array('public', 'class', 'extends', 'const', 'private', 'else', 'try', 'catch', 'throw', 'elif', 'if', 'static', 'def', 'new');
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


