<?php
/**
 * DokuWiki Plugin bible (Syntax Component).
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Budi Susanto <budsus@ti.ukdw.ac.id>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'syntax.php';

class syntax_plugin_indobible extends DokuWiki_Syntax_Plugin {
    function getInfo(){ return confToHash(dirname(__FILE__).'/plugin.info.txt'); }

    # Required. Define plugin type (see https://www.dokuwiki.org/devel:syntax_plugins)
    public function getType() {
        return 'substition';
    }

	# Required. Define paragraph handling (see https://www.dokuwiki.org/devel:syntax_plugins)
    public function getPType() {
        return 'normal';
    }

	# Required. Define sort order to determine which plugin trumps which. This value was somewhat arbitrary
	## And can be changed without really affecting the plugin. (see https://www.dokuwiki.org/devel:syntax_plugins)
    public function getSort() {
        return 275;
    }

    #Plugin Part 1 of 3. This is the actual plugin. This part defines how to invoke the plugin
    public function connectTo($mode) {
      $this->Lexer->addSpecialPattern('\!\*[a-zA-Z0-9:;\-\s]+\*\!', $mode, 'plugin_indobible');
    }

    #Plugin Part 2 of 3. This is the handler - the workhorse - which takes the matched result from part 1 and modifies it.
    function handle($match, $state, $pos, Doku_Handler &$handler){
        $data = array(); # Blank array
        # Make sure that we're dealing with the match from our regexp in part 1, which is in the DOKU_LEXER_SPECIAL context.
        switch ($state) {
            case DOKU_LEXER_SPECIAL :
                # Okay awesome, lets process that regexp match. Call my custom function called _fetchBibleVerse().
                $match = trim($match, "!*");
                $match = trim($match, "*!");                
                $bibleLink = $this->_getIndoBibleWS($match);
                # Modified match obtained! Now return that to Dokuwiki for collection in Part 3.
                return array($bibleLink, $state, $pos);
        }

        return $data; # Upon failure, return that blank array
    }

    #Plugin part 3 of 3. This takes that result from part 2 and actually renders it to the page.
    public function render($mode, Doku_Renderer &$renderer, $data) {
        if ($mode != 'xhtml') {
            return false;
        } # If mode is not html, like if it is metadata, just return.
        //dbglog($data[0]);
        $renderer->doc .= $data[0]; # Otherwise, fetch that $bibleLink (stored in $data[0]) and pass it to the dokuwiki renderer.
        return true;
    }

    // Method: POST, PUT, GET etc
    // Data: array("param" => "value") ==> index.php?param=value
    // source: http://stackoverflow.com/questions/9802788/call-a-rest-api-in-php
    public function _CallAPI($method, $url, $data = false) {
        $curl = curl_init();

        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);

                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        // Optional Authentication:
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("X-Requested-With: XMLHttpRequest", "Content-Type: application/json; charset=utf-8"));
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);

        curl_close($curl);

        return $result;
    }

    public function _getIndoBibleWS($text) {
      //buat id unik untuk rujukan ayat
      $nospace = preg_replace('/[:;\-\s]+/', '', $text);
      
      //ganti spasi dengan +
      $enctext = str_replace(' ', '+', $text);
      $url = 'http://ws.wiblika.or.id/alkitab/baca/' . $enctext;
      
      // ambil json response dari web service
      $jsonObject = json_decode($this->_CallAPI('GET', $url), true);

      if ($jsonObject && !isset($jsonObject['code'])) {
      	$isiayat = '';
        foreach($jsonObject as $k => $o){
          $isiayat .= '<strong>(' .$o["Alkitab"]["pasal"] . ':' . $o["Alkitab"]["ayat"] . ')</strong> ';
          $firman = $o["Alkitab"]["firman"];
          if (preg_match('/^\/(.)+\*$/', $firman)) {
            $firman = substr($o["Alkitab"]["firman"], 1, -1);
          }
          $isiayat .= str_replace('"', '&quot;', $firman) . '<br>';
        }
        
        $txtFocus = "";
        if (preg_match('/Chrome/i', $_SERVER['HTTP_USER_AGENT']) || preg_match('/Firefox/i', $_SERVER['HTTP_USER_AGENT'])) {
      	  $txtFocus = 'data-trigger="focus"';
      	}
      	$txt = '<alkitab><a href="#' . $nospace . '" class="alkitab" role="button" ' . $txtFocus . ' data-container="body" data-placement="bottom" data-toggle="popover" title="'. $text .'" data-content="' . $isiayat . '">' . $text . '</a></alkitab>';
      	
        return $txt;
      } else {
        return "[" . $text . "]";
      }
    }
}
