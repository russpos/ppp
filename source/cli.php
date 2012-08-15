<?php

class PPP_Cli {

    public function __construct() {
        // Usage string
        $version = PPP::VERSION;
        $this->help_string = <<<EOD

        Usage: ppp v$version

        -f, --file <file>    Required. File to read input from. If not present, will exit
        -o, --output <file>  Optional file to write output to. If not present, will write to STDOUT
        -h, --help           Display this message

EOD;
    }

    /**
    * Helper method to write strings to a stream
    * 
    * @param handle $stream     Resource handler
    * @param string $msg        String message to write
    * @param bool   $newline    Should a new line be appended to this output
    * @access public
    * @return void
    */
    protected function output($stream, $msg, $newline) {
        if ($newline) {
            $msg .= "\n";
        }
        fwrite($stream, $msg);

    }

    /**
    * Wrapper for writing output to STDERR
    * 
    * @param string $msg      String message to write
    * @param bool   $newline  Should a new line be appended to this output
    * @access public
    * @return void
    */
    private function err($msg, $newline=true) {
        $this->output(STDERR, $msg, $newline);
    }


    /**
    * Wrapper for writing output to STDOUT
    * 
    * @param string $msg      String message to write
    * @param bool   $newline  Should a new line be appended to this output
    * @access public
    * @return void
    */
    private function out($msg, $newline=true) {
        $this->output(STDOUT, $msg, $newline);
    }

    /**
    * Helper method for aggregating short and long values from an options
    * array, as returned from `getopt`.
    * 
    * @param array $opts Array returned from `getopt`
    * @param array $args Array of string argument names that are equivalent, ie 'h' and 'help'
    * @access public
    * @return Returns the 'true' value of these shared options, with ordered precedence
    */
    private function arg($opts, $args) {
        $argname = array_shift($args);

        if (!isset($opts[$argname])) {
            if (!empty($args)) {
                return call_user_func_array(array($this, 'arg'), array($opts, $args));
            }
            return false;
        } else {
            if ($opts[$argname] === false) {
                return true;
            }
            return $opts[$argname];
        }
    }

    /**
    * I really hate the interface to `getopt`. This is much better.
    * Takes an array of long opts => short opts.
    * 
    * @param array $options 
    * @access public
    * @return void
    */
    private function getopts($options) {
        $pairs = array();
        $opt_string = '';
        $long_opts = array();
        foreach ($options as $long_opt => $short_opt) {
            $opt_string .= $short_opt;
            $long_opts[] = $long_opt;

            $long = str_replace(':', '', $long_opt);
            $short = str_replace(':', '', $short_opt);
            $pairs[$long] = array($long, $short);
        }
        $opts = getopt($opt_string, $long_opts);
        $config = array();
        foreach ($pairs as $long => $names) {
            $config[$long] = $this->arg($opts, $names);
        }
        return $config;
    }


    /**
     * main 
     * 
     * @access public
     * @return void
     */
    public function main() {

        // Load options, and print help if appropriate
        $config = $this->getopts(array('file:' => 'f:', 'help' => 'h', 'output:' => 'o:'));
        if ($config['help']) {
            $this->out($this->help_string);
            return 0;
        }

        // Open input file
        $input = null;
        if (empty($config['file'])) {
            $this->err("-f / --file argument is required!");
            return 1;
        }
        $input = fopen($config['file'], 'r');
        if (!$input) {
            $this->err("Error reading input from {$config['file']}");
            return 1;
        }

        // Verify contents of the file
        $contents = stream_get_contents($input);
        if (empty($contents)) {
            $this->err("Cannot parse empty data!");
            return 1;
        }

        // Create a parser and parse the contents
        $parser = new PPP_Parser();
        $parsed = $parser->parse($contents);

        // Push output to STDOUT or to file, if provided
        if (empty($config['output'])) {
            $this->out($parsed);
            return 0;
        }
        if (file_put_contents($config['output'], $parsed)) {
            return 0;
        } else {
            $this->err("Could not write to output file {$config['output']}");
            return 1;
        }
    }

}
