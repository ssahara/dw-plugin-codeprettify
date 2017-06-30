<?php
/**
 * DokuWiki Plugin Code Prettifier
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * usage: ex. <Code:css linenums:5 lang-css> ... </Code>
 */

if(!defined('DOKU_INC')) die();

class syntax_plugin_codeprettify_code extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $patterns;

    function __construct() {
        $this->mode = substr(get_class($this), 7);

        // allowing nested "<angle pairs>" in title using regex atomic grouping
        $n = 3;
        $param = str_repeat('(?>[^<>\n]+|<', $n).str_repeat('>)*', $n);       

        $this->patterns[0] = '<Code\b'.$param.'>'.'(?=.*?</Code>)';
        $this->patterns[1] = '</Code>';

        $this->patterns[2] = '<code\b.*?>(?=.*?</code>)';
        $this->patterns[3] = '</code>';
    }

    public function getType() { return 'protected'; }
    public function getPType(){ return 'block'; }
    public function getSort() { return 199; } // < native 'code' mode (=200)

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode) {
        $this->Lexer->addEntryPattern($this->patterns[0], $mode, $this->mode);
        if ($this->getConf('override')) {
            $this->Lexer->addEntryPattern($this->patterns[2], $mode, $this->mode);
        }
    }

    public function postConnect() {
        $this->Lexer->addExitPattern($this->patterns[1], $this->mode);
        if ($this->getConf('override')) {
            $this->Lexer->addExitPattern($this->patterns[3], $this->mode);
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
                $class['prettify'] = 'prettyprint';
                if (preg_match('/(?:^:| (?:lang[-:])?)(?!(?:no-?)?linenums)(\w+)/', $params, $m)) {
                    $class['language'] = 'lang-'.$m[1];
                }
                if (preg_match('/ linenums(:\d+)?/', $params, $m)) {
                    $class['linenums'] = $m[0];
                }
                return array($state, array($class, $title));
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
            case DOKU_LEXER_ENTER:
                list($class, $title) = $data;
                if ($title) {
                    //$html = '<div class="plugin_codeprettify">'.hsc($title).'</div>';
                    $html = p_render($format, p_get_instructions($title), $info);
                    $html = '<div class="plugin_codeprettify">'.$html.'</div>';
                    $renderer->doc .= $html;
                }
                $class = implode(' ', $class);
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
