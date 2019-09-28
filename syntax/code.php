<?php
/**
 * DokuWiki Plugin Code Prettifier
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * usage: ex. <Code:css linenums:5 lang-css | title > ... </Code>
 */

if (!defined('DOKU_INC')) die();

class syntax_plugin_codeprettify_code extends DokuWiki_Syntax_Plugin
{
    public function getType()
    {   // Syntax Type
        return 'protected';
    }

    public function getPType()
    {   // Paragraph Type
        return 'block';
    }

    /**
     * Connect pattern to lexer
     */
    protected $mode, $pattern;

    public function getSort()
    {   // sort number used to determine priority of this mode
        return 199; // < native 'code' mode (=200)
    }

    public function preConnect()
    {
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

    public function connectTo($mode)
    {
        $this->Lexer->addEntryPattern($this->pattern[1], $mode, $this->mode);
        if ($this->getConf('override')) {
            $this->Lexer->addEntryPattern($this->pattern[11], $mode, $this->mode);
        }
    }

    public function postConnect()
    {
        $this->Lexer->addExitPattern($this->pattern[4], $this->mode);
        if ($this->getConf('override')) {
            $this->Lexer->addExitPattern($this->pattern[14], $this->mode);
        }
    }


    /**
     * GeSHi Options Parser
     *
     * DokuWiki release 2018-04-22 "Greebo" supports some GeSHi options
     * for syntax highlighting
     * alternative of parse_highlight_options() in inc/parser/handler.php
     *
     * @param string $params  space separated list of key-value pairs
     * @return array
     * @see also https://www.dokuwiki.org/syntax_highlighting
     */
    private function getGeshiOption($params)
    {
        $opts = [];
        // remove enclosing brackets and double-quotes
        $params = str_replace('"', '', trim($params, '[]'));
        if (preg_match_all('/(\w+)=?(\w+)?/', $params, $matches)) {

            // make keys lowercase
            $keys   = array_map('strtolower', $matches[1]);
            // interpret boolian string values
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
     * @return string  Prettifier linenums parameter
     * @see also https://www.dokuwiki.org/syntax_highlighting
     */
    private function strGeshiOptions(array $opts=[])
    {
        if (isset($opts['enable_line_numbers'])) {
            $option = &$opts['enable_line_numbers'];
            $prefix = ($option == 0) ? 'no' : '';
        }
        if (isset($opts['start_line_numbers_at'])) {
            $option = &$opts['start_line_numbers_at'];
            $suffix = ($option > 0) ? ':'.$option : '';
        }
        return ($prefix or $suffix) ? $prefix.'linenums'.$suffix : '';
    }


    /**
     * Prettifier Options Parser
     *
     * @param string $params
     * @return array
     */
    private function getPrettifierOptions($params)
    {
        $opts = [];

        // offset holds the position of the matched string
        // if offset become 0, the first token of given params is NOT language
        $offset = 1;
        if (preg_match('/\b(no)?linenums(:\d+)?/', $params, $m, PREG_OFFSET_CAPTURE)) {
            $offset = ($offset > 0) ? $m[0][1] : 1;
            $opts['linenums'] = $m[1][0] ? '' : $m[0][0];
        } else {
            $opts['linenums'] = $this->getConf('linenums') ? 'linenums' : '';
        }
        if (preg_match('/\blang-\w+/', $params, $m, PREG_OFFSET_CAPTURE)) {
            $offset = ($offset > 0) ? $m[0][1] : 1;
            $opts['language'] = $m[0][0];
        } elseif ($offset) {
            // assume the first token is language; ex. C, php, css
            list ($lang, ) = explode(' ', $params, 2);
            $opts['language'] = $lang ? 'lang-'.$lang : '';
        }
        return $opts;
    }


    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                list($params, $title) = explode('|', substr($match, 5, -1), 2);

                // title parameter
                if ($title) {
                    // remove first "document_start" and last "document_end" instructions
                    $calls = array_slice(p_get_instructions($title), 1, -1);
                } else {
                    $calls = null;
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

                $opts['prettify'] = 'prettyprint';
                $opts += $this->getPrettifierOptions($params);
                $params= implode(' ', $opts);

                return $data = [$state, $params, $calls];
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
    function render($format, Doku_Renderer $renderer, $data)
    {
        if ($format == 'metadata') return false;
        if (empty($data)) return false;
        list($state, $args, $calls) = $data;

        switch ($state) {
            case DOKU_LEXER_ENTER:
                if (isset($calls)) {
                    // title of code box
                    $renderer->doc .= '<div class="plugin_codeprettify">';
                    $renderer->nest($calls);
                    $renderer->doc .= '</div>';
                }
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
