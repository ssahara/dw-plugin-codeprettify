<?php
/**
 * DokuWiki Plugin Code Prettifier
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * usage: ex. <Code:css linenums:5 lang-css | title > ... </Code>
 */

if(!defined('DOKU_INC')) die();

class syntax_plugin_codeprettify_code extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $pattern = array();

    function __construct() {
        // syntax mode, drop 'syntax_' from class name
        $this->mode = substr(get_class($this), 7);

        // allowing nested "<angle pairs>" in title using regex atomic grouping
        $n = 3;
        $param = str_repeat('(?>[^<>\n]+|<', $n).str_repeat('>)*', $n);

        // syntax patterns
        $this->pattern[1] = '<Code\b'.$param.'>'.'(?=.*?</Code>)';
        $this->pattern[4] = '</Code>';

        // DokuWiki original syntax patterns
        $this->pattern[11] = '<code\b.*?>(?=.*?</code>)';
        $this->pattern[14] = '</code>';
    }

    function getType() { return 'protected'; }
    function getPType(){ return 'block'; }
    function getSort() { return 199; } // < native 'code' mode (=200)

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addEntryPattern($this->pattern[1], $mode, $this->mode);
        if ($this->getConf('override')) {
            $this->Lexer->addEntryPattern($this->pattern[11], $mode, $this->mode);
        }
    }

    function postConnect() {
        $this->Lexer->addExitPattern($this->pattern[4], $this->mode);
        if ($this->getConf('override')) {
            $this->Lexer->addExitPattern($this->pattern[14], $this->mode);
        }
    }


    /**
     * GeSHi Options Parser
     *
     * DokuWiki release 2018-04-22 “Greebo” supports some GeSHi options
     * for syntax highlighting
     * alternative of parse_highlight_options() in inc/parser/handler.php
     *
     * @param string $params  space separated list of key-value pairs
     * @return array
     * @see also https://www.dokuwiki.org/syntax_highlighting
     */
    private function getGeshiOption($params) {
        $opts = [];
        // remove enclosing brackets and double-quotes
        $params = str_replace('"', '', trim($params, '[]'));
        if (preg_match_all('/(\w+)=?(\w+)?/', $params, $matches)) {

            // make keys lowercase
            $keys   = array_map('strtolower', $matches[1]);
            // interpret boolian string
            $values = array_map(
                function($value) {
                    if (is_numeric($value)) {
                        return $value;
                    } else {
                        $s = strtolower($value);
                        if ($s == 'true')  $value = 1;
                        if ($s == 'false') $value = 0;
                        return $value;
                    }
                },
                $matches[2]
            );

           // Note: last one prevails if same keys have appeared
           $opts = array_combine($keys, $values);
        }
        return $opts;
    }

    /**
     * Convert/interpret GeSHi Options to correspondent Prettifier options
     * - enable_line_numbers=0    -> nolinenums
     * - start_line_numbers_at=1  -> linenums:1
     *
     * @param array $opts  GeSHi options
     * @return string
     * @see also https://www.dokuwiki.org/syntax_highlighting
     */
    private function strGeshiOptions(array $opts=[]) {

        if (isset($opts['enable_line_numbers'])) {
            $option = &$opts['enable_line_numbers'];
            $prefix = (empty($option) || $option == 'false') ? 'no' : '';
        }
        if (isset($opts['start_line_numbers_at'])) {
            $option = &$opts['start_line_numbers_at'];
            $suffix = ($option > 0) ? ':'.$option : '';
        }
        return ($prefix or $suffix) ? $prefix.'linenums'.$suffix : '';
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {

        switch ($state) {
            case DOKU_LEXER_ENTER:
                list ($params, $title) = explode('|', substr($match, 5, -1));

                // title parameter
                if ($title) {
                    $plugin = substr(get_class($this), 14);
                    $calls = p_get_instructions($title);

                    // open_div instruction
                    $data = ['div_open',''];
                    $handler->addPluginCall($plugin, $data, $state,$pos,$match);

                    // title: skip first "document_start" and last "document_end" instructions
                    for ($i = 1, $max = count($calls)-1; $i < $max; $i++) {
                        $handler->CallWriter->writeCall($calls[$i]);
                    }
                    // close_div instruction
                    $data = ['div_close',''];
                    $handler->addPluginCall($plugin, $data, $state,$pos,$match);
                }

                // prettifier parameters
                $params = trim($params, ' :');

                if ( preg_match('/\[.*\]/', $params, $matches) ) {
                    // replace GeSHi parameters 
                    $params = str_replace(
                        $matches[0],
                        $this->strGeshiOptions( $this->getGeshiOption($matches[0]) ),
                        $params
                    );
                }

                // prettifier parameters, again
                $class['prettify'] = 'prettyprint';
                $check = 1;
                if (preg_match('/\b(no)?linenums(:\d+)?/', $params, $m, PREG_OFFSET_CAPTURE)) {
                    ($check) && $check = $m[0][1];
                    $class['linenums'] = $m[1][0] ? '' : $m[0][0];
                } else {
                    $class['linenums'] = $this->getConf('linenums') ? 'linenums' : '';
                }
                if (preg_match('/\blang-\w+/', $params, $m, PREG_OFFSET_CAPTURE)) {
                    ($check) && $check = $m[0][1];
                    $class['language'] = $m[0][0];
                } elseif ($check) {
                    list($lang, ) = explode(' ', $params, 2);
                    $class['language'] = $lang ? 'lang-'.$lang : '';
                }
                $params= implode(' ', $class);

                return $data = [$state, $params];
            case DOKU_LEXER_UNMATCHED:
                return $data = [$state, $match];
            case DOKU_LEXER_EXIT:
                return $data = [$state, ''];
        }
        return false;
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $data) {

        if ($format == 'metadata') return false;
        if (empty($data)) return false;
        list ($state, $args) = $data;

        switch ($state) {
            case 'div_open':
                $html = '<div class="plugin_codeprettify">';
                $renderer->doc .= $html;
                break;

            case 'div_close':
                $html = '</div>';
                $renderer->doc .= $html;
                break;

            case DOKU_LEXER_ENTER:
                $renderer->doc .= '<pre class="'.hsc($args).'">';
                break;
            case DOKU_LEXER_UNMATCHED:
                $renderer->doc .= $renderer->_xmlEntities($args);
                break;
            case DOKU_LEXER_EXIT:
                $renderer->doc .= '</pre>';
                break;
        }
        return true;

    }

}
