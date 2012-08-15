<?php
define('PPP_VERSION', '0.0.3');

require_once 'globals.php';
require_once 'tokens.php';

// States
define('PPP_BRACKET_ARRAY', 301);
define('PPP_BRACKET_PROP',  302);


class PPP_ParserException_TokenBreak extends Exception { }
class PPP_ParserException_TokenContinue extends Exception { }

class PPP_Parser {

    const TOKENIZE_REGEX = '#(\"[^\"]*\")|(\'[^\']*\')|(\#\#\#)|(\<\-)|(\-\>)|(\s+|\.|:|\]|\[|,|\(|\))#';

    protected static function tokenize($string) {
        $tokens = array();
        $string = trim($string);

        $parts = preg_split(self::TOKENIZE_REGEX, $string, null, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $sub) {
            $token = PPP_Token::generate($sub);
            if (!empty($token)) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }

    protected function indent($count) {
        $this->indentation['count']++;
        $this->indentation['stack'][] = $count;
        $this->indentation['cur_spaces'] = $count;
    }

    protected function dedent($print = false) {
        $this->indentation['count']--;
        array_pop($this->indentation['stack']);
        if (empty($this->indentation['stack'])) {
            $this->indentation['cur_spaces'] = 0;
        }
        if ($print) {
            $this->output[] = $this->tabs(0, false)."}";
        }

        if (!empty($this->indentation['cur_spaces'])) {
            $this->indentation['cur_spaces'] = $this->indentation['stack'][count($this->indentation['stack'])-1];
        }
    }

    protected function match_indentation($count) {
        if ($count == $this->indentation['cur_spaces']) {
            return;
        } else if ($count > $this->indentation['cur_spaces']) {
            $this->indent($count);
        } else if ($count < $this->indentation['cur_spaces']) {
            $this->dedent(true);
            $this->match_indentation($count);
        }
    }

    protected function tabs($offset, $write = true) {
        $number = $this->indentation['count']+$this->array_stack+$offset;
        if ($number == 0) {
            return '';
        }
        $space = implode('', array_fill(0, $number*4, ' '));
        if ($write) {
            $this->token_list[] = $space;
        }
        return $space;
    }

    protected function reset_state() {
        $this->array_stack = 0;
        $this->token_list = array();
        $this->open_stack = array();
        $this->bracket_stack = array();
        $this->ends_curly = false;
        $this->is_in_catch = false;
    }

    protected function blockQuotes() {
        if ($this->token->is(PPP_BLOCKQUOTES)) {
            $this->in_block_quotes = !$this->in_block_quotes;
            $this->token_list[] = ($this->in_block_quotes) ? '/*' : '*/';
            throw new PPP_ParserException_TokenContinue();
        }
        if ($this->in_block_quotes) {
            $this->token_list[] = $this->token->value;
            throw new PPP_ParserException_TokenContinue();
        }
    }

    protected function reset_indentation() {
        $this->indentation = array(
            'stack' => array(),
            'level' => array(),
            'count' => 0,
            'cur_spaces' => 0,
        );
    }

    protected function next_token() {
        // Find the next NON-whitespace token. If you reach end of the line, then keep next_token as null
        $next_token_num = $this->cur_token;
        while ((++$next_token_num < $this->token_count) && empty($next_token)) {
            $possible_next_token = $this->tokens[$next_token_num];
            if ($possible_next_token->type !== PPP_WHITESPACE) {
                $next_token = $possible_next_token;
                break;
            }
        }
        return (empty($next_token)) ? null : $next_token;
    }

    public function parse($content) {
        global $synonyms;

        $lines = explode("\n", $content);

        $this->output = array("<?php");

        $this->in_block_quotes = false;

        $this->reset = true;
        $synonym_list = array_keys($synonyms);

        $this->cur_token = 0;
        $this->token_count = 0;

        $this->reset_state();
        $this->reset_indentation();

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                $this->output[] = '';
                continue;
            }
            if (empty($this->array_stack)&& !$this->in_block_quotes) {
                // Capture indentation
                preg_match('/^(\s+?)[^\s]/', $line, $leading_whitespace);
                if (!empty($leading_whitespace) && !empty($leading_whitespace[1])) {
                    $this->match_indentation(strlen($leading_whitespace[1]));
                } else {
                    $this->match_indentation(0);
                }
            }


            $this->tokens = self::tokenize($line);
            if ($this->reset) {
                $this->reset_state();
            }

            $this->token_count = count($this->tokens);
            $this->cur_token = 0;

            $this->reset = true;

            while ($this->cur_token < $this->token_count) {
                try {
                    $this->prev_token = null;
                    $this->next_token = null;
                    $end = false;
                    $this->token = $token = $this->tokens[$this->cur_token];

                    if ($this->cur_token == 0) {
                        if ($token->value == ']' || $token->value == '}') {
                            $tab_variation = -1;
                        } else {
                            $tab_variation = 0;
                        }
                        $this->tabs($tab_variation);
                    }

                    if ($this->cur_token-1 >= 0) {
                        $this->prev_token = $this->tokens[$this->cur_token-1];
                    }

                    $this->next_token = $next_token = $this->next_token();
                    $this->cur_token++;
                    $this->blockQuotes();
                    $token->parse($this);

                    if (is_null($this->next_token)) {
                        if ($token->value == ',' || !empty($this->array_stack)) {
                            $this->reset = false;
                            continue;
                        }
                        while (!empty($this->open_stack)) {
                            $this->token_list[] = array_pop($this->open_stack);
                        }

                        if ($this->ends_curly) {
                            $this->token_list[] = ' {';
                        } else if (!$end) {
                            $this->token_list[] = ';';
                        }
                    }
                } catch (PPP_ParserException_TokenBreak $e) {
                    break;
                } catch (PPP_ParserException_TokenContinue $e) {
                    continue;
                }
            }
            if ($this->reset) {
                $processed = implode('', $this->token_list);
                $this->output[] = $processed;
            } else {
                $this->token_list[] = "\n";
            }

        }
        while ($this->indentation['count']) {
            $this->dedent(true);
        }
        $printer =  implode(PHP_EOL, $this->output);
        return $printer;
    }
}
