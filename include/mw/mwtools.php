<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author Akeron
**/

require_once('include/mw/mwns.php');

class mwTools
{
    static function wtitle($title)
    {
        return str_replace(' ','_',$title);
    }
    static function rtitle($title)
    {
        return str_replace('_',' ',$title);
    }
    static function ns_categ($ns)
    {
        if($ns==NS_MAIN)
            return 'article';
        if(mwns::get()->is_content($ns) && $ns!=NS_FILE)
            return 'article';
        if($ns%2!=0 || $ns==2600)
            return 'talk';
        if($ns==NS_USER)
            return 'user';
        if($ns==NS_PROJECT)
            return 'meta';
        if($ns==NS_FILE)
            return 'file';
        if($ns==NS_HELP)
            return 'help';
        if($ns==NS_MEDIAWIKI)
            return 'mediawiki';
        if($ns==NS_CATEGORY||$ns==NS_TEMPLATE||$ns==NS_MODULE||$ns==104/*référence*/||$ns==2300/*Gadget*/||$ns==2302/*Gadget definition*/)
            return 'annexe';
        if($ns<0)
            return 'special';
        return 'other';
    }
    static function user_groups($global_min_projects=3)
    {
        global $conf;
        $db=get_dbs();
        $rows=$db->select('select user_name,ug_group from user_groups');
        if(empty($rows)){
            if($db=get_db()){
                echo "load db groups\n";
                $rows=$db->select('select /*SLOW_OK user_groups*/ user_name,ug_group from user_groups,user where ug_user=user_id');
            }
        }
        $res=array();
        foreach($rows as $k=>$v){
            $res[$v['user_name']][$v['ug_group']]=$v['ug_group'];
            unset($rows[$k]);
        }
        if(isset($conf['multi']) && $conf['multi'] && $global_min_projects>=1){
            $db=get_dbg();
            $rows=$db->select('select user_name from global_bots where projects>='.(int)$global_min_projects);
            foreach($rows as $k=>$v){
                $res[$v['user_name']]['bot']='bot';
                unset($rows[$k]);
            }
        }
        return $res;
    }
    static function format_user($name)
    {
        return str_replace('_',' ',strtoupper(mb_substr($name,0,1)).mb_substr($name,1));
    }
    static function encode_user($name)
    {
        return urlencode(str_replace(' ','_',htmlspecialchars(strtoupper(mb_substr($name,0,1)).mb_substr($name,1))));
    }
    static function title_url($title)
    {
        $ns=mwns::get()->get_ns_str($title);
        $t=mwns::get()->remove_ns($title);
        $t=htmlspecialchars(urlencode(str_replace(' ','_',$t)));
        $t=str_replace('%2F','/',$t);
        if($ns!='')
            $t=htmlspecialchars(urlencode(str_replace(' ','_',$ns))).':'.$t;
        return $t;
    }
    static function is_revert($comment)
    {
        if(mb_strpos($comment,'annonce de révocation'))
            return false;
        if(preg_match('/(LiveRC : )?[Rr]évocation|[Aa]nnulation des modifications|[Aa]nnulation de la .*modification|[Aa]nnule la  .*modification|^(Undid|Revert to( the)?) revision|^(Undoing|Reverted( \d+)?) edit|^r(e)?v(ert(ing|ed)?)?\b|Reverted edits|(undo|restore):\d+\|\|\d+/',$comment) && !preg_match('/ajout au résumé de la révocation|ajout au journal détaillé de la révocation/i', $comment))
            return true;
        return false;
    }
    static function is_bot_comment($comment)
    {
        return preg_match('/^(ro)?bot\b/i',$comment);
    }
}
?>