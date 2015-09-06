/*
 * script.js : code-prettify plugin for DokuWiki
 * register prettyprint() to the event listener
 * @author Satoshi Sahara <sahara.satoshi@gmail.com>
 */

(function(){
    function init(event){
        prettyPrint();
    }
    if(window.addEventListener) {
        window.addEventListener("load",init,false);
    } else if(window.attachEvent) {
        window.attachEvent("onload",init);
    }
})();

