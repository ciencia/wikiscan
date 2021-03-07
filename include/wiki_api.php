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

class wiki_api
{

    function __construct($dest='https://fr.wikipedia.org/w/api.php')
    {
        $this->dest=$dest;
        $this->init();
    }

    function init()
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_HEADER, false);
        curl_setopt($this->curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_USERAGENT, 'PHP '.phpversion());
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, ['Cache-Control: no-cache']);
    }

    function query($params)
    {
        $params['format']='php';
        curl_setopt($this->curl, CURLOPT_URL, $this->dest.'?'.http_build_query($params));
        $data=curl_exec($this->curl);
        return unserialize($data);
    }

    /**
     * Gets the wikitext contents of a page.
     * 
     * @param string $title Page title
     * @return string|boolean Wikitext
     */
    function content($title)
    {
        $attr=array(
            'action'=>'query',
            'prop'=>'revisions',
            'rvprop'=>'content|timestamp|user',
            'titles'=>$title,
            );
        $res=$this->query($attr);
        if(isset($res['query']['pages'])){
            $v=reset($res['query']['pages']);
            if(isset($v['revisions'][0]['*']))
                return $v['revisions'][0]['*'];
        }
        return false;
    }

    function category_members($categ, $ns=false)
    {
        $res=array();
        $attr=array(
            'action'=>'query',
            'list'=>'categorymembers', 
            'cmtitle'=>$categ,
            'cmlimit'=>500,
            'cmprop'=>'ids|title|sortkey|sortkeyprefix|timestamp|type',
            );
        if($ns!==false)
            $attr['cmnamespace']=$ns;
        $data=$this->query($attr);
        if(isset($data['query']['categorymembers'])){
            $res=[];
            foreach($data['query']['categorymembers'] as $v)
                $res[]=$v['title'];
            return $res;
        }
        return false;
    }


    function namespaces()
    {
        $query=array(
            'action'=>'query',
            'meta'=>'siteinfo',
            'siprop'=>'namespaces',
        );
        $data=$this->query($query);
        if(isset($data['query']['namespaces']))
            return $data['query']['namespaces'];
        return false;
    }

    function namespaces_alias()
    {
        $query=array(
            'action'=>'query',
            'meta'=>'siteinfo',
            'siprop'=>'namespacealiases',
        );
        $data=$this->query($query);
        if(isset($data['query']['namespacealiases']))
            return $data['query']['namespacealiases'];
        return false;
    }
    
    function entity($q)
    {
        $query=array(
            'action'=>'wbgetentities',
            'ids'=>$q,
            'redirects'=>'no',
        );
        $res=$this->query($query);
        if(isset($res['entities'][$q]))
            return $res['entities'][$q];
        if(isset($res['entities']))
            return $res['entities'];
        return false;
    }

}

?>