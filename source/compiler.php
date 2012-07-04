<?php



$content = file_get_contents(dirname(dirname(__FILE__)).'/samples/classy.ppp');

$output = array();
$indentation = 0;
$content = st_replace("|\n", ' ', $content);
$lines = preg_split("/\n/", $content);

function tab($number=0) {
    if ($number == 0) {
        return '';
    }
    return implode('', array_fill(0, $number*2, ' '));
}

$output[] = "<?php";
$indentation_level = 0;
foreach ($lines as $line) {
    $line = rtrim($line);

    if (trim($line) == '') {
        $output[] = '';
        continue;
    }

    $matches;
    preg_match('/^\s*/', $line, $matches);
    $cur_indent = strlen($matches[0])/2;

    while ($cur_indent < $indentation_level) {
        $output[] = tab($indentation_level-1).'}';
        $indentation_level--;
    }

    $indentation_level = $cur_indent;
    $line = trim($line);


    if (preg_match("/^if/", $line)) {
        $line = preg_replace('/^if /', 'if (', $line);
        $line = preg_replace('/:$/', ') :', $line);
    } else if (preg_match('/([a-z]+ +)[\$|\'|"]/', $line, $matches)) {

        $word = trim($matches[1]);
        if ($word != 'if' || $word != 'else' || $word != 'new') {
            $line = preg_replace('/[a-z]+ +/', trim($matches[1]).'(', $line);
            $line .= ')';
        }
    }
    
    if (preg_match("/:$/", $line)) {
        $line = preg_replace('/:$/', ' {', $line);
    }
    if (preg_match('/@/', $line)) {
        $line = preg_replace('/@\$/', '$this->', $line);
        $line = preg_replace('/@/', '$this->', $line);
    }

    if (preg_match('/^(public|private|protected)/', $line, $matches)) {
        $line = preg_replace('/^(public|private|protected)/', $matches[0].' function', $line);
    }

    if (!preg_match('/{$/', $line)) {
        $line = $line.';';
    }

    $output[] = tab($indentation_level).$line;

}

file_put_contents(dirname(dirname(__FILE__)).'/output/classy.php', implode("\n", $output));
echo implode("\n", $output);
