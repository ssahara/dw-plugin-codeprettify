<?php
/**
 * DokuWiki Plugin Code Prettifier
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();


/**
 * All DokuWiki plugins to interfere with the event system
 * need to inherit from this class
 */
class action_plugin_codeprettify extends DokuWiki_Action_Plugin {

    // register hook
    public function register(Doku_Event_Handler $controller) {
        //$controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, 'exportToJSINFO');
        //$controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handle_tpl_metaheader_output');
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'load_code_prettify');
    }

    /**
     * export configuration setting to $JSINFO
     */
    public function exportToJSINFO(Doku_Event $event, $param) {
        global $JSINFO;

        $loader = $this->getConf('url_loader');
        if (empty($loader)) {
            $loader = DOKU_BASE.'lib/plugins/codeprettify/google-code-prettify/run_prettify.js';
        }
        $JSINFO['plugin_codeprettify'] = array(
            'loader_base' => dirname($loader),
        );
    }

    /**
     * register google code prettifier script loader
     */
    public function handle_tpl_metaheader_output(Doku_Event $event, $param) {

        $loader = $this->getConf('url_loader');
        if (empty($loader)) {
            $loader = DOKU_BASE.'lib/plugins/codeprettify/google-code-prettify/run_prettify.js';
        }

        if ($this->getConf('lang_handlers')) {
            $lang = trim($this->getConf('lang_handlers'), ",");
            $lang = str_replace(' ', '', $lang);
            $lang = 'lang='.str_replace(',', '&lang=', $lang);
        }

        if ($this->getConf('skin')) {
            $skin = 'skin='.basename($this->getConf('skin'), '.css');
        }

        $opt = trim(implode('&', array($lang, $skin)), '&');
        if ($opt) $loader .= '?'.$opt;
        $event->data["script"][] = array (
                'type'    => 'text/javascript',
                'charset' => 'utf-8',
                'src'     => $loader,
                '_data'   => '',
        );
    }

    /**
     * register google code prettifier script and css
     */
    public function load_code_prettify(Doku_Event $event, $param) {

        // prettify.js and optional language handler scripts
        $handler = 'prettify';
        if ($this->getConf('lang_handlers')) {
            $handler .= ',' . trim($this->getConf('lang_handlers'), ',');
            $handler = str_replace(' ', '', $handler);
            $handler = str_replace(',',',lang-', $handler);
        }
        $scripts = explode(',', $handler);

        foreach ($scripts as $script) {
            $event->data['script'][] = array (
                'type'    => 'text/javascript',
                'charset' => 'utf-8',
                'src'     => DOKU_BASE.'lib/plugins/codeprettify/code-prettify/src/'.$script.'.js',
                '_data'   => '',
            );
        }

        // color theme for code-prettify (css file)
        if ($this->getConf('skin')) {
            $skin = 'styles/'.$this->getConf('skin');
        } else {
            $skin = 'src/prettify.css';
        }
        $event->data['link'][] = array (
                'rel'     => 'stylesheet',
                'type'    => 'text/css',
                'href'    => DOKU_BASE.'lib/plugins/codeprettify/code-prettify/'.$skin,
        );
    }

}
