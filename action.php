<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_blog extends DokuWiki_Action_Plugin {

  /**
   * return some info
   */
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-12-18',
      'name'   => 'Blog Plugin',
      'desc'   => 'Brings blog functionality to DokuWiki',
      'url'    => 'http://www.wikidesign.ch/en/plugin/blog/start',
    );
  }

  /**
   * register the eventhandlers
   */
  function register(&$contr){
    $contr->register_hook('ACTION_ACT_PREPROCESS',
                          'BEFORE',
                          $this,
                          'handle_act_preprocess',
                           array());
                              

    $contr->register_hook('IO_WIKIPAGE_WRITE',
                          'AFTER',
                          $this,
                          'cdateIndex',
                          array());
  }

  /**
   * Update the creation date index the blog plugin uses
   *
   * @author  Esther Brunner  <wikidesign@gmail.com>
   */
  function cdateIndex(&$event, $param){
    global $INFO;
    global $conf;
    
    if ($event->data[3]) return false;     // old revision saved
    if ($INFO['exists']) return false;     // file not new
    if (!$event->data[0][1]) return false; // file is empty
    
    // get needed information
    $id   = ($event->data[1] ? $event->data[1].':' : '').$event->data[2];
    $date = filectime($event->data[0][0]);
    
    // load blog class to update the creation date index
    $helper = plugin_load('helper', 'blog');
    return $helper->_updateCDateIndex($id, $date);
  }
    
  /**
   * Checks if 'newentry' was given as action, if so we
   * do handle the event our self and no further checking takes place
   */
  function handle_act_preprocess(&$event, $param){
    if ($event->data != 'newentry') return; // nothing to do for us
    // we can handle it -> prevent others
    // $event->stopPropagation();
    $event->preventDefault();    
    
    $event->data = $this->_handle_newEntry();
  }

  /**
   * Creates a new entry page
   */
  function _handle_newEntry(){
    global $ID;
    global $INFO;
    
    $ns    = cleanID($_REQUEST['ns']);
    $title = str_replace(':', '', $_REQUEST['title']);
    $ID    = $this->_newEntryID($ns, $title);
    $INFO  = pageinfo();
    
    // check if we are allowed to create this file
    if ($INFO['perm'] >= AUTH_CREATE){
            
      //check if locked by anyone - if not lock for my self      
      if ($INFO['locked']) return 'locked';
      else lock($ID);

      // prepare the new thread file with default stuff
      if (!@file_exists($INFO['filepath'])){
        global $TEXT;
        
        $TEXT = pageTemplate(array(($ns ? $ns.':' : '').$title));
        if (!$TEXT){
          $TEXT = "====== $title ======\n\n";
          if ((@file_exists(DOKU_PLUGIN.'discussion/action.php'))
            && (!plugin_isdisabled('discussion')))
            $TEXT .= "\n\n~~DISCUSSION~~\n";
        }
        return 'preview';
      } else {
        return 'edit';
      }
    } else {
      return 'show';
    }
  }
    
  /**
   * Returns the ID of a new entry based on its namespace, title and the date prefix
   * 
   * @author  Esther Brunner <wikidesign@gmail.com>
   * @author  Michael Arlt <michael.arlt@sk-chwanstetten.de>
   */
  function _newEntryID($ns, $title){
    $dateprefix  = $this->getConf('dateprefix');
    if (substr($dateprefix, 0, 1) == '<') {
      // <9?%y1-%y2:%d.%m._   ->  05-06:31.08._ | 06-07:01.09._
      list($newmonth, $dateprefix) = explode('?', substr($dateprefix, 1));
      if (intval(strftime("%m")) < intval($newmonth)){
        $longyear2 = strftime("%Y");
        $longyear1 = $longyear2 - 1;
      } else {
        $longyear1 = strftime("%Y");
        $longyear2 = $longyear1 + 1;
      }
      $shortyear1 = substr($longyear1, 2);
      $shortyear2 = substr($longyear2, 2);
      $dateprefix = str_replace(
        array('%Y1', '%Y2', '%y1', '%y2'),
        array($longyear1, $longyear2, $shortyear1, $shortyear2),
        $dateprefix
      );
    }
    $pre = strftime($dateprefix);
    return ($ns ? $ns.':' : '').$pre.cleanID($title);
  }

}

//Setup VIM: ex: et ts=4 enc=utf-8 :
