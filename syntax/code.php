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

    protected $entry_pattern = '<Code\b.*?>(?=.*?</Code>)';
    protected $exit_pattern  = '</Code>';

    public function getType() { return 'protected'; }
    public function getPType(){ return 'block'; }
    public function getSort() { return 199; } // < native 'code' mode (=200)

    public function connectTo($mode) {
        $this->Lexer->addEntryPattern($this->entry_pattern, $mode, substr(get_class($this), 7));
        if ($this->getConf('override')) {
            $this->Lexer->addEntryPattern('<code\b.*?>(?=.*?</code>)', $mode, substr(get_class($this), 7));
        }
    }

    public function postConnect() {
        $this->Lexer->addExitPattern($this->exit_pattern, substr(get_class($this), 7));
        if ($this->getConf('override')) {
            $this->Lexer->addExitPattern('</code>', substr(get_class($this), 7));
        }
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler){

        switch ($state) {
            case DOKU_LEXER_ENTER:
                $match = substr($match, 5, -1);
                list($params, $title) = explode('|', $match);
                $class['prettify'] = 'prettyprint';
                if (preg_match('/(?:^[: ](?!linenums)|(?: lang[-:]))(\w+)/', $params, $m)) {
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

        if (empty($indata)) return false;
        list($state, $data) = $indata;
        if ($format != 'xhtml') return false;

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
