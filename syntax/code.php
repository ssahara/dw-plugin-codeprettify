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

    public function getType() { return 'protected'; }
    public function getPType(){ return 'block'; }
    public function getSort() { return 199; } // < native 'code' mode (=200)

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode) {
        $this->Lexer->addEntryPattern($this->pattern[1], $mode, $this->mode);
        if ($this->getConf('override')) {
            $this->Lexer->addEntryPattern($this->pattern[11], $mode, $this->mode);
        }
    }

    public function postConnect() {
        $this->Lexer->addExitPattern($this->pattern[4], $this->mode);
        if ($this->getConf('override')) {
            $this->Lexer->addExitPattern($this->pattern[14], $this->mode);
        }
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {

        switch ($state) {
            case DOKU_LEXER_ENTER:
                $match = substr($match, 5, -1);
                list($params, $title) = explode('|', $match);

                // prettifier parameters
                $class['prettify'] = 'prettyprint';
                if (preg_match('/(?:^:| (?:lang[-:])?)(?!(?:no-?)?linenums)(\w+)/', $params, $m)) {
                    $class['language'] = 'lang-'.$m[1];
                }
                if (preg_match('/ linenums(:\d+)?/', $params, $m)) {
                    $class['linenums'] = $m[0];
                }

                // title parameter
                if ($title) {
                    $plugin = substr(get_class($this), 14);
                    $calls = p_get_instructions($title);

                    // open_div instruction
                    $data = array('div_open','');
                    $handler->addPluginCall($plugin, $data, $state,$pos,$match);

                    // title: skip first "document_start" and last "document_end" instructions
                    for ($i = 1, $max = count($calls)-1; $i < $max; $i++) {
                        $handler->CallWriter->writeCall($calls[$i]);
                    }
                    // close_div instruction
                    $data = array('div_close','');
                    $handler->addPluginCall($plugin, $data, $state,$pos,$match);
                }

                return array($state, $class);
            case DOKU_LEXER_UNMATCHED:
                return array($state, $match);
            case DOKU_LEXER_EXIT:
                return array($state, '');
        }
        return false;
    }

    /**
     * Create output
     */
    public function render($format, Doku_Renderer $renderer, $indata) {

        if ($format == 'metadata') return false;
        if (empty($indata)) return false;
        list($state, $data) = $indata;

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
                $class = implode(' ', $data);
                $renderer->doc .= '<pre class="'.$class.'">';
                break;
            case DOKU_LEXER_UNMATCHED:
                $renderer->doc .= $renderer->_xmlEntities($data);
                break;
            case DOKU_LEXER_EXIT:
                $renderer->doc .= '</pre>';
                break;
        }
        return true;

    }

}
