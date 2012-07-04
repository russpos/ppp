<?php

function echocolor($text,$color="normal",$back=0) 
{ 
  $colors = array('light_red'  => "[1;31m", 'light_green' => "[1;32m", 'yellow'     => "[1;33m", 
                  'light_blue' => "[1;34m", 'magenta'     => "[1;35m", 'light_cyan' => "[1;36m", 
                  'white'      => "[1;37m", 'normal'      => "[0m",    'black'      => "[0;30m", 
                  'red'        => "[0;31m", 'green'       => "[0;32m", 'brown'      => "[0;33m", 
                  'blue'       => "[0;34m", 'cyan'        => "[0;36m", 'bold'       => "[1m", 
                  'underscore' => "[4m",    'reverse'     => "[7m" ); 
  $out = $colors["$color"]; 
  $ech = chr(27)."$out"."$text".chr(27)."[0m"; 
  if($back) 
  { 
    return $ech; 
  } 
  else 
  { 
    echo $ech; 
  } 
} 


require 'source/parser.php';

$parser = new PPP_Parser();

$samples = opendir($sample_dir = dirname(__FILE__).'/samples');
$output  = opendir($output_dir = dirname(__FILE__).'/output');

$failures = 0;

while ($path = readdir($samples)) {
    if ($path[0] == '.') {
        continue;
    }
    list($name, $ext) = explode('.', $path);
    $cur_sample = file_get_contents($sample_dir.'/'.$path);
    $cur_output = file_get_contents($output_dir.'/'.$name.'.php');

    $parsed = $parser->parse($cur_sample);

    $output_lines = explode("\n", $cur_output);
    $parsed_lines = explode("\n", $parsed);
    $line = 0;
    foreach ($parsed_lines as $p_line) {
        $line++;
        $o_line = array_shift($output_lines);
        if (trim($p_line) != trim($o_line)) {
            $failures++;
            echocolor(">>>>> FAILURE!! #$failures <<<<<< \n", 'red');
            echocolor($line, 'cyan');
            echo '. '.$p_line;
            echocolor("\n-------------\n", 'yellow');
            echocolor($line, 'cyan');
            echo '. '.$o_line;
            echocolor("\n^^^^^^^^^^^^^^", 'yellow');
        }
    }
}

if (!$failures) {
    echo "... ";
    echocolor("All tests pass!\n", 'green');
    exit(0);
}
exit(1);
