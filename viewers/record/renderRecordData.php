<?php

/*
* Copyright (C) 2005-2018 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* UI for viewing record
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2018 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/View
*/

require_once(dirname(__FILE__)."/../../hserver/System.php");
require_once(dirname(__FILE__).'/../../common/php/Temporal.php');

$system = new System();
$inverses = null;

if(!$system->init(@$_REQUEST['db'])){
    include dirname(__FILE__).'/../../hclient/framecontent/infoPage.php';
    exit();
}

//require_once(dirname(__FILE__).'/../../records/woot/woot.php');
require_once(dirname(__FILE__).'/../../hserver/dbaccess/db_structure.php');
require_once(dirname(__FILE__).'/../../hserver/dbaccess/db_files.php');
require_once(dirname(__FILE__).'/../../hserver/dbaccess/db_recsearch.php');
require_once(dirname(__FILE__).'/../../hserver/dbaccess/db_users.php');


$noclutter = array_key_exists('noclutter', $_REQUEST);
$is_map_popup = array_key_exists('mapPopup', $_REQUEST) && ($_REQUEST['mapPopup']==1);
$is_reloadPopup = array_key_exists('reloadPopup', $_REQUEST) && ($_REQUEST['reloadPopup']==1);

$rectypesStructure = dbs_GetRectypeStructures($system); //getAllRectypeStructures(); //get all rectype names
$terms = dbs_GetTerms($system);//getTerms();

$ACCESSABLE_OWNER_IDS = $system->get_user_group_ids();  ///do we need it??
if (!in_array(0, $ACCESSABLE_OWNER_IDS)) {
    array_push($ACCESSABLE_OWNER_IDS, 0);
}

$relRT = ($system->defineConstant('RT_RELATION')?RT_RELATION:0);
$relSrcDT = ($system->defineConstant('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0);
$relTrgDT = ($system->defineConstant('DT_TARGET_RESOURCE')?DT_TARGET_RESOURCE:0);
$relTypDT = ($system->defineConstant('DT_RELATION_TYPE') ? DT_RELATION_TYPE : 0);
$intrpDT = ($system->defineConstant('DT_INTERPRETATION_REFERENCE') ? DT_INTERPRETATION_REFERENCE : 0);
$notesDT = ($system->defineConstant('DT_SHORT_SUMMARY') ? DT_SHORT_SUMMARY : 0);
$startDT = ($system->defineConstant('DT_START_DATE') ? DT_START_DATE : 0);
$endDT = ($system->defineConstant('DT_END_DATE') ? DT_END_DATE : 0);
$titleDT = ($system->defineConstant('DT_NAME') ? DT_NAME : 0);
$system->defineConstant('DT_GEO_OBJECT');
$system->defineConstant('DT_PARENT_ENTITY');
$system->defineConstant('DT_DATE');



$rec_id = intval(@$_REQUEST['recID']);

// if we get a record id then see if there is a personal bookmark for it.
if ($rec_id>0 && !@$_REQUEST['bkmk_id']) 
{
    $bkm_ID = mysql__select_value($system->get_mysqli(),
                    'select bkm_ID from usrBookmarks where bkm_recID = '
                        .$rec_id.' and bkm_UGrpID = '.$system->get_user_id());
}else{
    $bkm_ID = intval(@$_REQUEST['bkmk_id']);    
}
$sel_ids = array();
if(@$_REQUEST['ids']){
	$sel_ids = explode(',',$_REQUEST['ids']);
}
if(!$is_map_popup){
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/global.css">
        <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <!-- <script src="../../external/jquery/jquery-1.6.min.js"></script> -->

        <!-- script type="text/javascript" src="../../external/js/simple_js_viewer/script/core/Simple_Viewer_beta_1.1.js"></script>
        <script type="text/javascript" src="../../records/files/initViewer.js"></script -->
        <script type="text/javascript" src="../../common/js/hintDiv.js"></script> <!-- for mapviewer roolover -->
        <script type="text/javascript" src="../../hclient/core/detectHeurist.js"></script>

        <script type="text/javascript">
        
            function zoomInOut(obj,thumb,url) {
                var thumb = thumb;
                var url = url;
                var currentImg = obj;
                if (currentImg.parentNode.className != "fullSize"){
                    $(currentImg).hide();                    
                    currentImg.src = url;
                    currentImg.onload=function(){
                        $(currentImg).fadeIn(500);
                    }                    
                    currentImg.parentNode.className = "fullSize";

                }else{
                    currentImg.src = thumb;
                    currentImg.parentNode.className = "thumb_image";
                }
            }
            
            //
            //
            //
            function printLTime(sdate,ele){
                var date = new Date(sdate+"+00:00");
                ele = document.getElementById(ele)
                ele.innerHTML = (''+date.getHours()).padStart(2, "0")
                        +':'+(''+date.getMinutes()).padStart(2, "0")
                        +':'+(''+date.getSeconds()).padStart(2, "0");
            }
            
            // @to reimplement
            //
            function showPlayer(obj, id, url) {

                var currentImg = obj;

                if (currentImg.parentNode.className != "fullSize"){  //thumb to player
                    currentImg.style.display = 'none';
                    currentImg.parentNode.className = "fullSize";
                    
                    //add content to player div
        $.ajax({
            url: url,
            type: "GET",
            //data: request,
            //dataType: "json",
            cache: false,
            error: function(jqXHR, textStatus, errorThrown ) {
            },
            success: function( response, textStatus, jqXHR ){
                        var obj = jqXHR.responseText;
                        if (obj){
                            var  elem = document.getElementById('player'+id);
                            elem.innerHTML = obj;
                            elem.style.display = 'block';
                            elem = document.getElementById('lnk'+id);
                            elem.style.display = 'block';
                        }
            }
        });
                    
                    /*
                    top.HEURIST.util.sendRequest(url, function(xhr) {
                        var obj = xhr.responseText;
                        //alert('!!!!'+obj);
                        if (obj){
                            var  elem = document.getElementById('player'+id);
                            elem.innerHTML = obj;
                            elem.style.display = 'block';
                            elem = document.getElementById('lnk'+id);
                            elem.style.display = 'block';
                        }
                        }, null);
                     */
                }
            }
            function hidePlayer(id) {
                var  elem = document.getElementById('player'+id);
                elem.innerHTML = '';
                elem.style.display = 'none';
                elem = document.getElementById('lnk'+id);
                elem.style.display = 'none';

                elem = document.getElementById('img'+id);
                elem.parentNode.className = "thumb_image";
                elem.style.display = 'inline-block';
            }


            function start_roll_open() {
                window.roll_open_id = setInterval(roll_open, 100);
            }

            function roll_open() {
                var wfe = window.frameElement;
                if (! wfe) return;
                var current_height = parseInt(wfe.style.height);

                var final_height = document.getElementById('bottom').offsetTop + 2;

                if (final_height > current_height + 30) {
                    // setTimeout(roll_open, 100);

                    // linear
                    //wfe.style.height = (current_height + 20) + 'px';

                    wfe.style.height = current_height + Math.round(0.5*(final_height-current_height)) + 'px';
                } else {
                    wfe.style.height = final_height + 'px';

                    clearInterval(window.roll_open_id);
                }
            }

            function sane_link_opener(link) {
                if (window.frameElement  &&  window.frameElement.name == 'viewer') {
                    top.location.href = link.href;
                    return false;
                }
            }

            //
            // catch click on a href and opens it in popup dialog
            //
            function link_open(link) {
                <?php if($is_reloadPopup){ ?>
                    this.document.location.href = link.href+'&reloadPopup=1';
                    return false;
                <?php 
                }else{
                ?>    
                try{
                   window.hWin.HEURIST4.msg.showDialog(link.href, { title:'.', width: 600, height: 500, modal:false });
                   return false;
                }catch(e){
                   return true; 
                }
                <?php
                } 
                ?>
            }

            /*NOT USED
            //on document load onLoad="add_sid();"
            function add_sid() {
                try{
                if (top.HEURIST  &&  top.HEURIST.search  &&  top.HEURIST.search.results.querySid) {
                    var e = document.getElementById("edit-link");
                    if (e) {
                        e.href = e.href.replace(/editRecord\.html\?/, "editRecord.html?sid="+top.HEURIST.search.results.querySid+"&");
                    }
                }
                }catch(e){
                }
                
                //init image viewer
                //$('.mediacontent').yoxview({ skin: "top_menu", allowedUrls: /\?db=(?:\w+)&id=(?:\w+)$/i});
                
            }
            */

        </script>
    </head>
    <body class="popup">

        <script type="text/javascript" src="../../viewers/map/mapViewer.js"></script>
        <script>
            baseURL = "<?=HEURIST_BASE_URL?>viewers/map/mapStatic.php?width=300&height=300&db=<?=HEURIST_DBNAME?>";
        </script>

        <?php
} //$is_map_popup
else{
//    print '<div style="font-size:0.8em">';
}        
        if ($bkm_ID>0) {
            $bibInfo = mysql__select_row_assoc($system->get_mysqli(),
            'select * from usrBookmarks left join Records on bkm_recID=rec_ID '
            .'left join defRecTypes on rec_RecTypeID=rty_ID where bkm_ID='
            .$bkm_ID.' and bkm_UGrpID='.$system->get_user_id()
            .' and (not rec_FlagTemporary or rec_FlagTemporary is null)');
            
            print '<div style="font-size:0.8em">';
            print_details($bibInfo);
            print '</div>';
            
        } else if ($rec_id>0) {
            $bibInfo = mysql__select_row_assoc($system->get_mysqli(), 
            'select * from Records left join defRecTypes on rec_RecTypeID=rty_ID where rec_ID='
            .$rec_id.' and not rec_FlagTemporary');
            
	        print '<div data-recid="'.$rec_id.'" style="font-size:0.8em">';
                print_details($bibInfo);
	        print '</div>';
        
            $opts = '';
            if(count($sel_ids)>1){
                
                $opts = $opts . '<option value="'.$rec_id.'">(#'.$rec_id.') '.$bibInfo['rec_Title'].'</option>';
                
                $cnt = 1;
                
                foreach($sel_ids as $id){
                if($id!=$rec_id){    
                    $bibInfo = mysql__select_row_assoc($system->get_mysqli(),
                            'select * from Records left join defRecTypes on rec_RecTypeID=rty_ID'
                            .' where rec_ID='.$id.' and not rec_FlagTemporary');
                                
                    print '<div data-recid="'.$id.'" style="font-size:0.8em;display:none">';
                    print_details($bibInfo);
                    print '</div>';
                    
                    $opts = $opts . '<option value="'.$id.'">(#'.$id.') '.$bibInfo['rec_Title'].'</option>';
                    $cnt++;
                }
                }
                
                print '<div style="font-size:0.8em">Multiple entries here<br><br><select style="font-size:0.9em"'
                .' onclick="$(\'div[data-recid]\').hide(); $(\'div[data-recid=\'+$(event.target).val()+\']\').show();" '  
                .'>'.$opts;
                print '</select></div>';
            }
        } else {
            print 'No details found';
        }
 if($is_map_popup){
//    print '</div>';
 }else{
       ?>
        <div id=bottom><div></div>

    </body>
</html>
<?php	
 }
/***** END OF OUTPUT *****/

// this functions outputs common info.
function print_details($bib) {
    global $is_map_popup;
        
    print_header_line($bib);
    
    print_public_details($bib);
    
    if(!$is_map_popup){
        print_private_details($bib);
        print_other_tags($bib);
    //print_text_details($bib);
    }
    
    print_relation_details($bib);
    print_linked_details($bib);
    
}


// this functions outputs the header line of icons and links for managing the record.
function print_header_line($bib) {
    global $is_map_popup, $system;
    
    $rec_id = $bib['rec_ID'];
    $url = $bib['rec_URL'];
    if ($url  &&  ! preg_match('!^[^\\/]+:!', $url))
        $url = 'http://' . $url;

    $webIcon = mysql__select_value($system->get_mysqli(),
                    'select dtl_Value from recDetails where dtl_RecID='
                    .$bib['rec_ID'].' and dtl_DetailTypeID=347'); //DT_WEBSITE_ICON); 
    //

    if($system->has_access()){ //is logged in
        ?>

        <div id=recID>
             ID:<?= htmlspecialchars($rec_id) ?>
            <span class="link"><a id=edit-link class="normal"
                        onClick="return sane_link_opener(this);"
                        target=_new href="<?php echo HEURIST_BASE_URL?>?fmt=edit&db=<?=HEURIST_DBNAME?>&recID=<?= $rec_id ?>">
                        <img src="../../common/images/edit-pencil.png" title="Edit record"></a>
            </span>
        </div>

        <?php
    }else{
        print "<div id=recID>ID:".htmlspecialchars($rec_id)."</div>";
    }
    ?>

    <div class=HeaderRow style="margin-bottom:<?php echo ((@$url)?'20px;':'0px;min-height:0px;'); ?>">
            <h2 style="text-transform:none; line-height:16px;<?php echo ($is_map_popup)?'max-width: 380px;':'';?>">
                <?= $bib['rec_Title'] ?>
            </h2>
    </div>        
    <div id=footer>
    <?php 
    if(!$is_map_popup){ 
    ?>
        <h3>
            <div <?="style='padding-left:20px;height:16px;background-repeat: no-repeat;background-image:url(".HEURIST_ICON_URL.$bib['rty_ID'].".png)'"?> >
                <?= htmlspecialchars($bib['rty_Name'])." [".$bib['rty_ID']."] " ?>
            </div>
        </h3>
        <br>
        <?php if (@$url) { ?>
            <span class="link">
                <a target="_new" class="external-link" href="http://web.archive.org/web/*/<?=htmlspecialchars($url)?>">page history</a>
                <a target="_new" class="external-link" href="<?=htmlspecialchars($url)?>"><?= output_chunker($url) ?></a>
                <?php if ($webIcon) print "<img id=website-icon src='" . $webIcon . "'>"; ?>
            </span>
        <?php } 
    }else{
        print '&nbsp;';
    } 
    print '</div>'; //footer

}


//this  function displays private info if there is any.
function print_private_details($bib) {
    global $system;

    if($bib['rec_OwnerUGrpID']==0){
        
        $workgroup_name = 'Everyone';
        
    }else{
    
        $permissions = recordSearchPermissions($system, $bib['rec_ID']);
        if($permissions['status']==HEURIST_OK){
            $groups = @$permissions['edit'][$bib['rec_ID']];
            if(is_array($groups)){
                array_unshift($groups, $bib['rec_OwnerUGrpID']);
            }else{
                $groups = array($bib['rec_OwnerUGrpID']);
            }
            
            $workgroup_name = user_getNamesByIds( $system, $groups );
            $workgroup_name = implode(', ',$workgroup_name);
        
        }else{

            $row = mysql__select_row($system->get_mysqli(),    
                'select grp.ugr_Name,grp.ugr_Type,concat(grp.ugr_FirstName," ",grp.ugr_LastName) from Records, '
                    .'sysUGrps grp where grp.ugr_ID=rec_OwnerUGrpID and rec_ID='.$bib['rec_ID']);
            
            $workgroup_name = NULL;
            // check to see if this record is owned by a workgroup
            if ($row!=null) {
                $workgroup_name = $row[1] == 'user'? $row[2] : $row[0];
            }
        }
    }
    
    // check for workgroup tags
    $kwds = mysql__select_all($system->get_mysqli(),    
        'select grp.ugr_Name, tag_Text from usrRecTagLinks left join usrTags on rtl_TagID=tag_ID left join '
        .'sysUGrps grp on tag_UGrpID=grp.ugr_ID left join sysUsrGrpLinks on ugl_GroupID=ugr_ID and ugl_UserID='
        .$system->get_user_id().' where rtl_RecID='.$bib['rec_ID']
        .' and tag_UGrpID is not null and ugl_ID is not null order by rtl_Order',0,0);
        
    if ( $workgroup_name || count($kwds) || $bib['bkm_ID']) {
        ?>
        <div class=detailRowHeader>Private
        <?php
        if ( $workgroup_name) {
            ?>
            <div class=detailRow>
                <div class=detailType>Ownership</div>
                <div class=detail>
                    <?php
                    print '<span style="font-weight: bold; color: black;">'.htmlspecialchars($workgroup_name).'</span>';
                    switch ($bib['rec_NonOwnerVisibility']) {
                        case 'hidden':
                            print '<span> - hidden to all except owner(s))</span></div></div>';
                            break;
                        case 'viewable':
                            print '<span> - readable by other logged-in users</span></div></div>';
                            break;
                        case 'public':
                        default:
                            print '<span> - readable by anyone (public)</span></div></div>';
                    }
                }

                $ratings = array("0"=>"none",
                    "1"=> "*",
                    "2"=>"**",
                    "3"=>"***",
                    "4"=>"****",
                    "5"=>"*****");

                $rating_label = @$ratings[@$bib['bkm_Rating']?$bib['bkm_Rating']:"0"];
                ?>

                <div class=detailRow>
                    <div class=detailType>Rating</div>
                    <div class=detail>
                        <!-- <span class=label>Rating:</span> --> <?= $rating_label? $rating_label : 'none' ?>
                    </div>
                </div>

                <?php
                if ($kwds) {
                    ?>
                    <div class=detailRow>
                        <div class=detailType>Workgroup tags</div>
                        <div class=detail>
                            <?php
                            for ($i=0; $i < count($kwds); ++$i) {
                                $grp = $kwds[$i][0];
                                $kwd = $kwds[$i][1];
                                if ($i > 0) print '&nbsp; ';
                                $grp_kwd = $grp.'\\\\'.$kwd;
                                $label = 'Tag "'.$grp_kwd.'"';
                                if (preg_match('/\\s/', $grp_kwd)) $grp_kwd = '"'.$grp_kwd.'"';
                                print htmlspecialchars($grp.' - ').'<a class=normal style="vertical-align: top;" target=_parent href="'.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&ver=1&amp;q=tag:'.urlencode($grp_kwd).'&amp;w=all&amp;label='.urlencode($label).'" title="Search for records with tag: '.htmlspecialchars($kwd).'">'.htmlspecialchars($kwd).'<img style="vertical-align: middle; margin: 1px; border: 0;" src="'.HEURIST_BASE_URL.'common/images/magglass_12x11.gif"></a>';
                            }
                            ?>
                        </div>
                    </div>

                    <?php
                }
            }
            if (is_array($bib) && array_key_exists('bkm_ID',$bib)) {
                print_personal_details($bib);
            }
        }


//this function outputs the personal information from the bookmark
function print_personal_details($bkmk) {
    global $system;

    $bkm_ID = $bkmk['bkm_ID'];
    $rec_ID = $bkmk['bkm_RecID'];

    $query = 'select tag_Text from usrRecTagLinks, usrTags '
        .'WHERE rtl_TagID=tag_ID and rtl_RecID='.$rec_ID.' and tag_UGrpID = '.
        $bkmk['bkm_UGrpID'].' order by rtl_Order';
    $tags = mysql__select_list2($system->get_mysqli(), $query);
    ?>
    <div class=detailRow>
        <div class=detailType>Personal Tags</div>
        <div class=detail>
            <?php
            if ($tags) {
                for ($i=0; $i < count($tags); ++$i) {
                    if ($i > 0) print '&nbsp; ';
                    $tag = $tags[$i];
                    $label = 'Tag "'.$tag.'"';
                    if (preg_match('/\\s/', $tag)) $tag = '"'.$tag.'"';
                    print '<a class=normal style="vertical-align: top;" target=_parent href="'.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&ver=1&amp;q=tag:'.urlencode($tag).'&amp;w=bookmark&amp;label='.urlencode($label).'" title="Search for records with tag: '.htmlspecialchars($tags[$i]).'">'.htmlspecialchars($tags[$i]).'<img style="vertical-align: middle; margin: 1px; border: 0;" src="'.HEURIST_BASE_URL.'common/images/magglass_12x11.gif"></a>';
                }
                if (count($tags)) {
                    print "<br>\n";
                }
            }
            ?>
        </div>
    </div>

    <?php
}


function print_public_details($bib) {
    global $system, $terms, $is_map_popup;
    
    $mysqli = $system->get_mysqli();

    $query = 'select rst_DisplayOrder, dtl_ID, dty_ID,
        IF(rdr.rst_DisplayName is NULL OR rdr.rst_DisplayName=\'\', dty_Name, rdr.rst_DisplayName) as name,
        dtl_Value as val,
        dtl_UploadedFileID,
        dty_Type,
        if(dtl_Geo is not null, AsWKT(dtl_Geo), null) as dtl_Geo,
        if(dtl_Geo is not null, AsWKT(envelope(dtl_Geo)), null) as bd_geo_envelope
        from recDetails
        left join defDetailTypes on dty_ID = dtl_DetailTypeID
        left join defRecStructure rdr on rdr.rst_DetailTypeID = dtl_DetailTypeID
        and rdr.rst_RecTypeID = '.$bib['rec_RecTypeID'].'
        where dtl_RecID = ' . $bib['rec_ID'] .'
        order by rdr.rst_DisplayOrder is null,
        rdr.rst_DisplayOrder,
        dty_ID,
    dtl_ID';      //show null last

    $bds = array();
    $thumbs = array();

    $bds_res = $mysqli->query($query);

    if($bds_res){
        while ($bd = $bds_res->fetch_assoc()) {

            if ($bd['dty_ID'] == 603) { //DT_FULL_IMAG_URL
                array_push($thumbs, array(
                    'url' => $bd['val'],
                    'thumb' => HEURIST_BASE_URL.'common/php/resizeImage.php?db='.HEURIST_DBNAME.'&file_url='.$bd['val']
                ));
            }

            if ($bd['dty_Type'] == 'enum') {

                if(array_key_exists($bd['val'], $terms['termsByDomainLookup']['enum'])){
                    $term = $terms['termsByDomainLookup']['enum'][$bd['val']];
                    $bd['val'] = output_chunker(getTermFullLabel($terms, $term, 'enum', false));
                    //$bd['val'] = output_chunker($terms['termsByDomainLookup']['enum'][$bd['val']][0]);
                }else{
                    $bd['val'] = "";
                }

            }else if ($bd['dty_Type'] == 'relationtype') {

                $term = $terms['termsByDomainLookup']['relation'][$bd['val']];
                $bd['val'] = output_chunker(getTermFullLabel($terms, $term, 'relation', false));
                //$bd['val'] = output_chunker($terms['termsByDomainLookup']['relation'][$bd['val']][0]);

            }else if ($bd['dty_Type'] == 'date') {

                if($bd['val']==null || $bd['val']==''){
                    //ignore empty date
                    continue;
                }else{
                    $bd['val'] = temporalToHumanReadableString($bd['val'], true);
                    $bd['val'] = output_chunker($bd['val']);
                }

            }else if ($bd['dty_Type'] == 'blocktext') {

                $bd['val'] = nl2br(str_replace('  ', '&nbsp; ', output_chunker($bd['val'])));

            }else if ($bd['dty_Type'] == 'resource') {

                $rec_id = intval($bd['val']);
                $rec_title = mysql__select_value($mysqli, 'select rec_Title from Records where rec_ID='.$rec_id);
                
                $bd['val'] = '<a target="_new" href="'.HEURIST_BASE_URL.'viewers/record/renderRecordData.php?db='
                    .HEURIST_DBNAME.'&recID='.$rec_id.(defined('use_alt_db')? '&alt' : '')
                    .'" onclick="return link_open(this);">'
                    .htmlspecialchars($rec_title).'</a>';

                //find dates
                $row = mysql__select_row($mysqli, 
                    'select cast(getTemporalDateString(dtl_Value) as DATETIME), dtl_Value '
                    .'from recDetails where dtl_DetailTypeID in ('
                    .DT_DATE.','.DT_START_DATE.') and dtl_RecID='.$rec_id );
                    
                if($row){
                    if($row[0]==null){//year
                        $bd['order_by_date'] = $row[1];
                    }else{
                        $bd['order_by_date'] = $row[0];    
                    }
                }

            }
            else if ($bd['dty_Type'] == 'file'  &&  $bd['dtl_UploadedFileID']) {

                //find obfuscated id
                /*$filedata = mysql__select_row_assoc($mysqli, 
                    'select ulf_ObfuscatedFileID, '
                        .'ulf_ExternalFileReference as remoteURL '
                        .'ulf_OrigFileName as origName,'
                        .'ulf_FileSizeKB as fileSize,'
                        .'from recUploadedFiles where ulf_ID='.$bd['dtl_UploadedFileID']);*/
                        
                $listpaths = fileGetFullInfo($system, $bd['dtl_UploadedFileID']); //see db_files.php
                if(is_array($listpaths) && count($listpaths)>0){

                    $fileinfo = $listpaths[0]; //
                    $filepath = $fileinfo['fullPath'];  //concat(ulf_FilePath,ulf_FileName as fullPath
                    $external_url = $fileinfo['ulf_ExternalFileReference'];     //ulf_ExternalFileReference
                    $mimeType = $fileinfo['fxm_MimeType'];  //fxm_MimeType
                    $params = $fileinfo['ulf_Parameters'];  //ulf_Parameters - not used anymore (for backward capability only)
                    $originalFileName = $fileinfo['ulf_OrigFileName'];
                    $fileSize = $fileinfo['ulf_FileSizeKB'];
                    $file_nonce = $fileinfo['ulf_ObfuscatedFileID'];
                    

                    $file_playerURL = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$file_nonce.'&mode=tag';
                    $file_thumbURL  = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&thumb='.$file_nonce;
                    $file_URL   = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$file_nonce; //download
                    
                    array_push($thumbs, array(
                        'id' => $bd['dtl_UploadedFileID'],
                        //'url' => $file_URL, //download
                        'external_url' => $external_url,      //external url
                        //'mediaType'=>$filedata['mediaType'], 
                        'params'=>$params,
                        'mimeType'=>$mimeType, 
                        'thumb_size'=>200,
                        'thumb' => $file_thumbURL,
                        'player' => $file_playerURL,
                        'nonce' => $file_nonce
                    ));
              
                    if($originalFileName){ 
                        $bd['val'] = '<a target="_surf" class="external-link" href="'.htmlspecialchars($file_URL).'">'.htmlspecialchars($originalFileName).'</a> '.($fileSize>0?'[' .htmlspecialchars($fileSize) . 'kB]':'');
                    }else{
                        $bd['val'] = '<a target="_surf" class="external-link" href="'.htmlspecialchars($file_URL).'">'.htmlspecialchars($file_URL).'</a>';
                    }
                }
                
/*                
                $filedata = RecUploadedFiles::getFileInfo($bd['dtl_UploadedFileID']);
                if($filedata){

                    $filedata = $filedata['file'];
                    $remoteSrc = $filedata['remoteSource'];

                    if(strpos($filedata['mimeType'],'audio/')===0 || 
                    strpos($filedata['mimeType'],'image/')===0 || 
                    strpos($filedata['mimeType'],'video/')===0){

                        //$filedata['URL'] = ;
                        $filedata['playerURL'] = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$filedata['nonce'].'&mode=tag';
                    }

                    //add to thumbnail list
                    $isplayer = (array_key_exists('playerURL', $filedata) && $filedata['playerURL']);
                    if (is_image($filedata) || $isplayer)
                    {

                        //$filedata standart thumb with 200px image
                        if(!$is_map_popup && $filedata['URL']!=$filedata['remoteURL']){
                            $filedata["thumbURL"] =
                            HEURIST_BASE_URL."common/php/resizeImage.php?maxw=200&maxh=200&".
                            (defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )."ulf_ID=".$filedata['nonce'];
                            $thumb_size = 200;
                        }else{
                            $thumb_size = 100;
                        }

                        array_push($thumbs, array(
                            'id' => $filedata['id'],
                            'url' => $filedata['URL'],   //download
                            'mediaType'=>$filedata['mediaType'], 
                            'mimeType'=>$filedata['mimeType'], 
                            'thumb_size'=>$thumb_size,
                            'thumb' => $filedata['thumbURL'],
                            'player' => $filedata['playerURL'],
                            'nonce' => $filedata['nonce']
                            //link to generate player html
                            //$isplayer?$filedata['playerURL'].(($remoteSrc=='youtube' || $remoteSrc=='gdrive')?"":"&height=60%"):null  
                        ));
                    }

                    if($filedata['URL']==$filedata['remoteURL']){ //remote resource
                        $bd['val'] = '<a target="_surf" class="external-link" href="'.htmlspecialchars($filedata['URL']).'">'.htmlspecialchars($filedata['URL']).'</a>';
                    }else{
                        $bd['val'] = '<a target="_surf" class="external-link" href="'.htmlspecialchars($filedata['URL']).'">'.htmlspecialchars($filedata['origName']).'</a> '.($filedata['fileSize']>0?'[' .htmlspecialchars($filedata['fileSize']) . 'kB]':'');
                    }
                }
*/
            } else {
                if (preg_match('/^https?:/', $bd['val'])) {
                    if (strlen($bd['val']) > 100)
                        $trim_url = preg_replace('/^(.{70}).*?(.{20})$/', '\\1...\\2', $bd['val']);
                    else
                        $trim_url = $bd['val'];
                    $bd['val'] = '<a href="'.$bd['val'].'" target="_new">'.htmlspecialchars($trim_url).'</a>';
                } else if ($bd['dtl_Geo'] && preg_match("/^POLYGON\s?[(][(]([^ ]+) ([^ ]+),[^,]*,([^ ]+) ([^,]+)/", $bd["bd_geo_envelope"], $poly)) {
                    list($match, $minX, $minY, $maxX, $maxY) = $poly;
                    if ($bd["val"] == "l"  &&  preg_match("/^LINESTRING\s?[(]([^ ]+) ([^ ]+),.*,([^ ]+) ([^ ]+)[)]$/",$bd["dtl_Geo"],$matches)) {
                        list($dummy, $minX, $minY, $maxX, $maxY) = $matches;
                    }
                    /*   redundant
                    $minX = intval($minX*10)/10;
                    $minY = intval($minY*10)/10;
                    $maxX = intval($maxX*10)/10;
                    $maxY = intval($maxY*10)/10;
                    */

                    switch ($bd["val"]) {
                        case "p": $type = "Point"; break;
                        case "pl": $type = "Polygon"; break;
                        case "c": $type = "Circle"; break;
                        case "r": $type = "Rectangle"; break;
                        case "l": $type = "Path"; break;
                        default: $type = "Unknown";
                    }

                    if ($type == "Point")
                        $bd["val"] = "<b>Point</b> ".round($minX,7).", ".round($minY,7);
                    else
                        $bd['val'] = "<b>$type</b> X ".round($minX,7).", ".round($maxX,7).
                        " Y ".round($minY,7).", ".round($maxY,7);

                    $geoimage = "<img class='geo-image' src='".HEURIST_BASE_URL
                    ."common/images/geo.gif' onmouseout='{if(mapViewer){mapViewer.hide();}}' "
                    ."onmouseover='{if(mapViewer){mapViewer.showAtStatic(event, ".$bib['rec_ID'].");}}'>&nbsp;";

                    $bd['val'] = $geoimage.$bd['val'];

                } else {
                    $bd['val'] = output_chunker($bd['val']);
                }
            }

            array_push($bds, $bd);
    }//for

        $bds_res->close();
    }
    
    usort($bds, "__sortResourcesByDate");

    if($is_map_popup){
        echo '<div>';  
    }else{  

        //print info about parent record
        foreach ($bds as $bd) {
            if(defined('DT_PARENT_ENTITY') && $bd['dty_ID']==DT_PARENT_ENTITY){
                print '<div class="detailRow" style="width:100%;border:none 1px #00ff00;">'
                .'<div class=detailType>Parent record</div><div class="detail">'
                .' '.$bd['val'].'</div></div>';
                break;
            }
        }

        echo '<div class=detailRowHeader>Shared';
    }
    ?>
    <div class=thumbnail>
        <?php
        foreach ($thumbs as $thumb) {

            print '<div class=thumb_image>';

            if($thumb['player'] && !$is_map_popup){

                if(strpos($thumb['mimeType'],'audio/')===0 || strpos($thumb['mimeType'],'video/')===0){
                    print '<div id="player'.$thumb['id'].'" style="min-height:100px;min-width:200px;">';

                    print fileGetPlayerTag($thumb['nonce'], $thumb['mimeType'], $thumb['params'], $thumb['external_url']); //see db_files
                    
                    //print getPlayerTag($thumb['nonce'], $thumb['mimeType'], $thumb['url'], null); 
                    print '</div>';    
                }else{
                    print '<img id="img'.$thumb['id'].'" style="width:'.$thumb['thumb_size'].'px" src="'.htmlspecialchars($thumb['thumb']).'" onClick="showPlayer(this,'.$thumb['id'].',\''. htmlspecialchars($thumb['player'].'&origin=recview') .'\')">';
                    print '<div id="player'.$thumb['id'].'" style="min-height:240px;min-width:320px;display:none;"></div>';
                }
            }else{  //for usual image
                print '<img src="'.htmlspecialchars($thumb['thumb']).'" onClick="zoomInOut(this,\''. htmlspecialchars($thumb['thumb']) .'\',\''. htmlspecialchars($thumb['url']) .'\')">';
            }
            print '<br/><div class="download_link">';
            if($thumb['player'] && !$is_map_popup){
                print '<a id="lnk'.$thumb['id'].'" href="#" style="display:none;" onclick="hidePlayer('.$thumb['id'].')">CLOSE</a>&nbsp;';
            }
            
            $url = $thumb['external_url']?$thumb['external_url']:(HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$thumb['nonce']);

            print '<a href="' . htmlspecialchars($url) 
                                . '" class="external-link" target=_surf class="image_tool">DOWNLOAD</a></div>';
            print '</div>';
            if($is_map_popup){
                print '<br>';
                break; //in map popup show the only thumbnail
            }
        };
        ?>
    </div>
    <?php

    $always_visible_dt = array(DT_SHORT_SUMMARY, DT_GEO_OBJECT);


    $prevLbl = null;
    foreach ($bds as $bd) {
        if(defined('DT_PARENT_ENTITY') && $bd['dty_ID']==DT_PARENT_ENTITY) continue;
        print '<div class="detailRow" style="width:100%;border:none 1px #00ff00;'
        .($is_map_popup && !in_array($bd['dty_ID'], $always_visible_dt)?'display:none':'')
        .'"><div class=detailType>'.($prevLbl==$bd['name']?'':htmlspecialchars($bd['name']))
        .'</div><div class="detail'
        .($is_map_popup && ($bd['dty_ID']!=DT_SHORT_SUMMARY)?' truncate':'').'">'
        /* debug
        .$bd['rst_DisplayOrder']
        .' '.@$bd['order_by_date']
        .' '.$bd['dtl_ID']
        .' '.$bd['dty_ID']  */
        .' '.$bd['val'].'</div></div>';
        $prevLbl = $bd['name'];
    }

    $add_date = DateTime::createFromFormat('Y-m-d H:i:s', $bib['rec_Added']); //get form database in server time
    
    if($add_date && $bib['rec_Added']!='0000-00-00 00:00:00'){
        $add_date = $add_date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); //convert to UTC
        $add_date_local = ' (<span id="lt0"></span><script type="text/javascript">printLTime("'.  //output in js in local time
                            $add_date.'", "lt0")</script> local)';

    }else{
        $add_date = false;
    }

    $mod_date = DateTime::createFromFormat('Y-m-d H:i:s', $bib['rec_Modified']); //get form database in server time
    if($mod_date){
        $mod_date = $mod_date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); //convert to UTC
        $mod_date_local = ' (<span id="lt1"></span><script type="text/javascript">printLTime("'.  //output in js in local time
                            $mod_date.'", "lt1")</script> local)';
    }else{
        $mod_date = false;
    }
    if($add_date){
        ?>
        <div class=detailRow <?php echo $is_map_popup?'style="display:none"':''?>>
            <div class=detailType>Added</div><div class=detail>
                <?php print $add_date.'  '
                .' '.$add_date_local; ?>
            </div>
        </div>
        <?php
    }
    if($mod_date){
        ?>
        <div class=detailRow <?php echo $is_map_popup?'style="display:none"':''?>>
            <div class=detailType>Updated</div><div class=detail>
                <?php print $mod_date
                .' '.$mod_date_local; ?>
            </div>
        </div>
        <?php
    }
    ?>
    <div class=detailRow <?php echo $is_map_popup?'style="display:none"':''?>>
        <div class=detailType>Cite as</div><div class="detail<?php echo ($is_map_popup?' truncate':'');?>">
            <a target=_blank class="external-link" 
                href="<?= HEURIST_BASE_URL ?>?recID=<?= $bib['rec_ID']."&db=".HEURIST_DBNAME ?>">XML</a>
            &nbsp;&nbsp;
            <a target=_blank class="external-link" 
            href="<?= HEURIST_BASE_URL ?>?recID=<?= $bib['rec_ID']."&fmt=html&db=".HEURIST_DBNAME ?>">HTML</a><?php echo ($is_map_popup?'':'<span class="prompt" style="padding-left:10px">Right click to copy URL</span>');?></div>    
    </div>


    <?php
    // </div>  
    if($is_map_popup){
        echo '<div class=detailRow><a href="#" onClick="$(\'.detailRow\').show();$(event.target).hide()">more ...</a></div>';
        echo '<div class=detailRow>&nbsp;</div>';
    }

    echo '</div>';            
}


function print_other_tags($bib) {
    return;
    //@todo implement
        ?>
        <div class="detailRow">
            <div class="detailType">Tags</div>
            <div class="detail">
                <a target="_new" 
                    href="<?=HEURIST_BASE_URL?>records/view/viewRecordTags.php?db=<?=HEURIST_DBNAME?>&recID=<?=$bib['rec_ID']?>" 
                    target=_top onclick="return link_open(this);">[Other users' tags]</a>
            </div>
        </div>
        <?php
}
    
    
/**
* get related record structure for a give relationship record
* related = { "recID" => recID,
*             "RelTermID" => relationTrmID,
*             "RelTerm" => trmLabel,
*             "ParentTermID" => parentTrmID,
*             "RelatedRecID" => linkedRecID,
*             "InterpRecID" => interpretationRecID,
*             "Notes" => relationshipNotes,
*             "Title" => relationshipTitle,
*             "StartData" => relationshipStartDate,
*             "EndDate" => relationshipEndDate}
*
* @global    int $relTypDT local id of relationtype detailType (magic number)
* @global    int $relSrcDT local id of source record pointer detailType (magic number)
* @global    int $relTrgDT local id of target record pointer detailType (magic number)
* @global    int $intrpDT local id of interpretation record pointer detailType (magic number)
* @global    int $notesDT local id of notes detailType (magic number)
* @global    int $startDT local id of start time detailType (magic number)
* @global    int $endDT local id of end time detailType (magic number)
* @global    int $titleDT local id of title detailType (magic number)
* @param     int $recID relationshipRecID to use for related
* @param     boolean $i_am_primary true if context is "from" record of relationship link
* @return    object related record structure
* @todo      change $i_am_primary to useInverseRelation
*/
function fetch_relation_details($recID, $i_am_primary) {
    
    global $system, $relTypDT, $relSrcDT, $relTrgDT, $intrpDT, $notesDT, $startDT, $endDT, $titleDT;
    
    /* get recDetails for the given linked resource and extract all the necessary values */
    $mysqli = $system->get_mysqli();
    $res = $mysqli->query('select * from recDetails where dtl_RecID = ' . $recID);
    
    $bd = array('recID' => $recID);
    if($res){
        while ($row = $res->fetch_assoc()) {
            
        switch ($row['dtl_DetailTypeID']) {
            case $relTypDT: //saw Enum change - added RelationValue for UI
                if ($i_am_primary) {
                    $bd['RelTermID'] = $row['dtl_Value'];
                } else {
                    $bd['RelTermID'] = reltype_inverse($row['dtl_Value']); // BUG: assumes reltype_inverse returns ID
                    //TODO: saw this should have a -1 which is different than self inverse and the RelTerm should be "inverse of ". term label requires checking smarty/showReps
                }
                $relval = mysql__select_row_assoc($mysqli, 
                        'select trm_Label, trm_ParentTermID from defTerms where trm_ID = ' . intval($bd['RelTermID']));
                if($relval!=null){
                    $bd['RelTerm'] = $relval['trm_Label'];
                    if ($relval['trm_ParentTermID']) {
                        $bd['ParentTermID'] = $relval['trm_ParentTermID'];
                    }
                }
                break;
            case $relTrgDT: // linked resource
                if (!$i_am_primary) break;

                $bd['RelatedRecID'] = mysql__select_row_assoc($mysqli,
                                    'select rec_ID, rec_Title, rec_RecTypeID, rec_URL'.
                                    ' from Records where rec_ID = ' . intval($row['dtl_Value']) );
                break;
            case $relSrcDT:
                if ($i_am_primary) break;
                
                $bd['RelatedRecID'] = mysql__select_row_assoc($mysqli,
                                    'select rec_ID, rec_Title, rec_RecTypeID, rec_URL'.
                                    ' from Records where rec_ID = ' . intval($row['dtl_Value']) );
                
                break;
            case $intrpDT:
                
                $bd['InterpRecID'] = mysql__select_row_assoc($mysqli,
                                    'select rec_ID, rec_Title, rec_RecTypeID, rec_URL'.
                                    ' from Records where rec_ID = ' . intval($row['dtl_Value']) );
                
                break;
            case $notesDT:
                $bd['Notes'] = $row['dtl_Value'];
                break;
            case $titleDT:
                $bd['Title'] = $row['dtl_Value'];
                break;
            case $startDT:
                $bd['StartDate'] = $row['dtl_Value'];
                break;
            case $endDT:
                $bd['EndDate'] = $row['dtl_Value'];
                break;
        }
    }
        $res->close();
    }
    
    return $bd;
}

/**
* determine the inverse of a relationship term
* @global    array llokup of term inverses by trmID to inverseTrmID
* @param     int $relTermID reltionship trmID
* @return    int inverse trmID
* @todo      modify to retrun -1 in case not inverse defined
*/
function reltype_inverse($relTermID) { //saw Enum change - find inverse as an id instead of a string

    global $system, $inverses;
    
    $mysqli = $system->get_mysqli();

    if (!$relTermID) return;
    if (!$inverses) {
        $inverses = mysql__select_assoc2($mysqli, 
                "SELECT A.trm_ID, B.trm_ID FROM defTerms A left join defTerms B on B.trm_ID=A.trm_InverseTermID"
                ." WHERE A.trm_Label is not null and B.trm_Label is not null");
    }
    $inverse = @$inverses[$relTermID];
    if (!$inverse) $inverse = array_search($relTermID, $inverses);//do an inverse search and return key.
    if (!$inverse) $inverse = $relTermID; //'Inverse of ' . FIXME: This should be -1 indicating no inverse found.
    return $inverse;
}

function print_relation_details($bib) {

        global $system, $relRT,$relSrcDT,$relTrgDT,$ACCESSABLE_OWNER_IDS, $is_map_popup, $rectypesStructure;

        $mysqli = $system->get_mysqli();
        
        $from_res = $mysqli->query('select recDetails.*
            from recDetails
            left join Records on rec_ID = dtl_RecID
            where dtl_DetailTypeID = '.$relSrcDT.
            ' and rec_RecTypeID = '.$relRT.
            ' and dtl_Value = ' . $bib['rec_ID']);        //primary resource


        $to_res = $mysqli->query('select recDetails.*
            from recDetails
            left join Records on rec_ID = dtl_RecID
            where dtl_DetailTypeID = '.$relTrgDT.
            ' and rec_RecTypeID = '.$relRT.
            ' and dtl_Value = ' . $bib['rec_ID']);          //linked resource

        if (($from_res==false || $from_res->num_rows <= 0)  &&  
             ($to_res==false || $to_res->num_rows<=0)){
               return;  
        } 

    if($is_map_popup){
       print '<div>';
    }else{
       print '<div class=detailRowHeader>Related'; 
    }

    $accessCondition = (count($ACCESSABLE_OWNER_IDS)>0?'(rec_OwnerUGrpID in ('.join(',', $ACCESSABLE_OWNER_IDS).') ':'(0 ').
    ($system->has_access()?'OR NOT rec_NonOwnerVisibility = "hidden")':'OR rec_NonOwnerVisibility = "public")');
    
    if($from_res){
    while ($reln = $from_res->fetch_assoc()) {
        
        $bd = fetch_relation_details($reln['dtl_RecID'], true);

        // check related record
        if (!@$bd['RelatedRecID'] || !array_key_exists('rec_ID',$bd['RelatedRecID'])) {
            continue;
        }
        $relatedRecID = $bd['RelatedRecID']['rec_ID'];
        
        if(mysql__select_value($mysqli, 
            "select count(rec_ID) from Records where rec_ID =$relatedRecID and $accessCondition")==0){
            //related is not accessable
            continue;
        }
        
        print '<div class=detailRow>';
        //		print '<span class=label>' . htmlspecialchars($bd['RelationType']) . '</span>';	//saw Enum change
        if(array_key_exists('RelTerm',$bd)){
            print '<div class=detailType>' . htmlspecialchars($bd['RelTerm']) . '</div>'; // fetch now returns the enum string also
        }
        print '<div class=detail>';
            if (@$bd['RelatedRecID']) {
                if(true || $is_map_popup){  
                    print '<img class="rft" style="background-image:url('.HEURIST_ICON_URL.$bd['RelatedRecID']['rec_RecTypeID'].'.png)" title="'.$rectypesStructure['names'][$bd['RelatedRecID']['rec_RecTypeID']].'" src="'.HEURIST_BASE_URL.'common/images/16x16.gif">&nbsp;';
                }
                print '<a target=_new href="'.HEURIST_BASE_URL.'viewers/record/renderRecordData.php?db='.HEURIST_DBNAME.'&recID='.$bd['RelatedRecID']['rec_ID'].(defined('use_alt_db')? '&alt' : '').'" onclick="return link_open(this);">'.htmlspecialchars($bd['RelatedRecID']['rec_Title']).'</a>';
            } else {
                print htmlspecialchars($bd['Title']);
            }
            print '&nbsp;&nbsp;';
            if (@$bd['StartDate']) print htmlspecialchars(temporalToHumanReadableString($bd['StartDate']));
            if (@$bd['EndDate']) print ' until ' . htmlspecialchars(temporalToHumanReadableString($bd['EndDate']));
        print '</div></div>';
    }
    $from_res->close();
    }
    if($to_res){
    while ($reln = $to_res->fetch_assoc()) {
        
        $bd = fetch_relation_details($reln['dtl_RecID'], false);

        // check related record
        if (!@$bd['RelatedRecID'] || !array_key_exists('rec_ID',$bd['RelatedRecID'])) {
            continue;
        }
        $relatedRecID = $bd['RelatedRecID']['rec_ID'];
        if(mysql__select_value($mysqli, 
            "select count(rec_ID) from Records where rec_ID =$relatedRecID and $accessCondition")==0){
            //related is not accessable
            continue;
        }

        print '<div class=detailRow>';
        //		print '<span class=label>' . htmlspecialchars($bd['RelationType']) . '</span>';	//saw Enum change
        if(array_key_exists('RelTerm',$bd)){
            print '<div class=detailType>' . htmlspecialchars($bd['RelTerm']) . '</div>';
        }
        print '<div class=detail>';
            if (@$bd['RelatedRecID']) {
                if(true || $is_map_popup){  
                    print '<img class="rft" style="background-image:url('.HEURIST_ICON_URL.$bd['RelatedRecID']['rec_RecTypeID'].'.png)" title="'.$rectypesStructure['names'][$bd['RelatedRecID']['rec_RecTypeID']].'" src="'.HEURIST_BASE_URL.'common/images/16x16.gif">&nbsp;';
                }
                print '<a target=_new href="'.HEURIST_BASE_URL.'viewers/record/renderRecordData.php?db='.HEURIST_DBNAME.'&recID='.$bd['RelatedRecID']['rec_ID'].(defined('use_alt_db')? '&alt' : '').'" onclick="return link_open(this);">'.htmlspecialchars($bd['RelatedRecID']['rec_Title']).'</a>';
            } else {
                print htmlspecialchars($bd['Title']);
            }
            print '&nbsp;&nbsp;';
            if (@$bd['StartDate']) print htmlspecialchars($bd['StartDate']);
            if (@$bd['EndDate']) print ' until ' . htmlspecialchars($bd['EndDate']);
        print '</div></div>';
    }
        $to_res->close();
    }
    
    print '</div>';
}


function print_linked_details($bib) {
    global $system, $relRT,$ACCESSABLE_OWNER_IDS, $is_map_popup, $rectypesStructure;
    
    $query = 'select * '.
    'from recDetails '.
    'left join defDetailTypes on dty_ID = dtl_DetailTypeID '.
    'left join Records on rec_ID = dtl_RecID '.
    'where dty_Type = "resource" '.
    'and dtl_DetailTypeID = dty_ID '.
    'and dtl_Value = ' . $bib['rec_ID'].' '.
    'and rec_RecTypeID != '.$relRT.' '.
    'and '.(count($ACCESSABLE_OWNER_IDS)>0?'(rec_OwnerUGrpID in ('.join(',', $ACCESSABLE_OWNER_IDS).') ':'(0 ').
    (($system->has_access()?'OR NOT rec_NonOwnerVisibility = "hidden")':'OR rec_NonOwnerVisibility = "public")').
    ' ORDER BY rec_RecTypeID, rec_Title');
    
    $mysqli = $system->get_mysqli();
    
    $res = $mysqli->query($query);

    if ($res==false || $res->num_rows <= 0) return;
    
    if($is_map_popup){
       print '<div>';
    }else{
       print '<div class=detailRowHeader>Linked from'; 
    ?>
        <div class=detailRow>
            <div class=detailType>Referencing records</div>
            <div class="detail"><a href="<?=HEURIST_BASE_URL?>?db=<?=HEURIST_DBNAME?>&w=all&q=linkedto:<?=$bib['rec_ID']?>" onClick="top.location.href = this.href; return false;"><b>Show list below as search results</b></a>
                <!--  <br> <i>Search = linkedto:<?=$bib['rec_ID']?> <br>(returns records pointing TO this record)</i> -->
            </div>
        </div>
    <?php
        }
        
        $lbl = 'Linked from';

        while ($row = $res->fetch_assoc()) {

            print '<div class=detailRow>';
                print '<div class=detailType>'.$lbl.'</div>';
                print '<div class="detail'.($is_map_popup?' truncate':'').'">';
                    print '<img class="rft" style="background-image:url('.HEURIST_ICON_URL.$row['rec_RecTypeID'].'.png)" title="'.$rectypesStructure['names'][$row['rec_RecTypeID']].'" src="'.HEURIST_BASE_URL.'common/images/16x16.gif">&nbsp;';
                    print '<a target=_new href="'.HEURIST_BASE_URL.'viewers/record/renderRecordData.php?db='.HEURIST_DBNAME.'&recID='.$row['rec_ID'].(defined('use_alt_db')? '&alt' : '').'" onclick="return link_open(this);">'.htmlspecialchars($row['rec_Title']).'</a>';
                print '</div></div>';

            $lbl = '';
        }
        
    print '</div>';
}

//
// functions below for WOOT and Comments are not used
//
function print_text_details($bib) {
        $cmts = getAllComments($bib["rec_ID"]);
        $result = loadWoot(array("title" => "record:".$bib["rec_ID"]));
        if (! $result["success"] && count($cmts) == 0) return;
        
        $content = "";
        $woot = @$result["woot"];
        if(is_array($woot) && is_array($woot["chunks"]))
        foreach ($woot["chunks"] as $chunk) {
            $content .= $chunk["text"] . " ";
        }
        if (strlen($content) == 0 && count($cmts) == 0) return;

        
        print '<div class=detailRowHeader>Text';
        print_woot_precis($content, $bib);
        print_threaded_comments($cmts);
        print '</div><br>&nbsp;'; // avoid ugly spacing
}

function output_chunker($val) {
    // chunk up the value so that it will be able to line-break if necessary
    $val = htmlspecialchars($val);
    return $val;
    /* it adds word breaker incorrectly, so Arabic words are displayed incorrecly
    return preg_replace('/(\\b.{15,20}\\b|.{20}.*?(?=[\x0-\x7F\xC2-\xF4]))/', '\\1<wbr>', $val);
    */
}

/*
    loadWoot returns:

    {	success
    errorType?
    woot? : {	id
    title
    version
    creator
    permissions : {	type
    userId
    userName
    groupId
    groupName
    } +
    chunks : {	number
    text
    modified
    editorId
    ownerId
    permissions : {	type
    userId
    userName
    groupId
    groupName
    } +
    } +
    }
    }
    Array (
    [id] => 2372
    [title] => record:45171
    [version] => 4
    [creator] => 1
    [permissions] => Array (
    [0] => Array (
    [type] => RW
    [userId] => 1
    [userName] => johnson
    [groupId] =>
    [groupName] => ) )
    [chunks] => Array (
    [0] => Array (
    [number] => 1
    [text] => test private to Ian
    [modified] => 2010-03-08 16:46:08
    [editorId] => 1
    [ownerId] => 1
    [permissions] => Array (
    [0] => Array (
    [type] => RW
    [userId] => 1
    [userName] => johnson
    [groupId] =>
    [groupName] => ) ) ) ) )
    */
function print_woot_precis($content,$bib) {
        if (strlen($content) == 0) return;
        ?>
        <div class=detailRow>
            <div class=detailType>WYSIWYG Text</div>
            <div class=detail>
                <?php
                $content = preg_replace("/<.*?>/", " ", $content);
                if (strlen($content) > 500) {
                    print substr($content, 0, 500) . " ...";
                } else {
                    print $content;
                }
                ?>

                <div><a target=_blank href="<?=HEURIST_BASE_URL?>records/woot/woot.html?db=<?=HEURIST_DBNAME?>&w=record:<?= $bib['rec_ID'] ?>&t=<?= $bib['rec_Title'] ?>">Click here to edit</a></div>
            </div>
        </div>
        <?php
    }


function print_threaded_comments($cmts) {
        if (count($cmts) == 0) return;
        ?>
        <div class=detailRow>
            <div class=detailType>Thread Comments</div>
            <div class=detail>
                <?php
                $printOrder = orderComments($cmts);
                $level = 1;
                foreach ($printOrder as $pair) {
                    $level = 20 * $pair["level"];
                    print '<div style=" font-style:italic; padding: 0px 0px 0px ';
                    print $level;
                    print  'px ;"> ['.$cmts[$pair['id']]["user"]. "] " . $cmts[$pair['id']]["text"] . "</div>";
                }
                ?>
            </div>
        </div>
    <?php
}


function orderComments($cmts) {
    $orderedCmtIds = array();
    $orderErrCmts = array();
    foreach ($cmts as $id => $cmt) {
        //handle root nodes
        if ($cmt['owner'] == 0) {
            // skip deleted or children with deleted parents
            if ($cmt['deleted']) continue;
            $level = $cmts[$id]["level"] = 0;
            array_push($orderedCmtIds,$id);
        }else {	//note this algrithm assumes comments are ordered by date and that a child comment always has a more recent date
            // handle deleted or children of deleted
            if ($cmts[$cmt["owner"]]["deleted"]) $cmt["deleted"] = true;
            if ($cmt["deleted"]) continue;
            $ownerIndex = array_search($cmt["owner"],$orderedCmtIds);
            $insertIndex = count($orderedCmtIds);  //set insertion to end of array as default
            if($ownerIndex === FALSE) {  // breaks assumption write code to fix up the ordering here
                array_push($orderErrCmts,array( 'id' => $id, 'level' => 1));
            }else if ($ownerIndex +1 < $insertIndex) { //not found at the end of the array  note array index +1 = array offset
                if (array_key_exists($cmt["owner"],$cmts) && array_key_exists("level",$cmts[$cmt["owner"]])){
                    $cmts[$id]["level"]  = 1 + $cmts[$cmt["owner"]]["level"] ; //child so increase the level
                    for ($i = $ownerIndex+1; $i < $insertIndex; $i++) {
                        if ( $cmts[$orderedCmtIds[$i]]["level"] < $cmts[$id]["level"]) { //found insertion point
                            $insertIndex = $i;
                            break;
                        }
                    }
                    // insert id at index point
                    array_splice($orderedCmtIds,$insertIndex,0,$id);
                }else{
                    //something is wrong just add it to the end
                    array_push($orderErrCmts,array( 'id' => $id, 'level' => 1));
                }
            }else{ //parent node is at the end of the array so just append
                $cmts[$id]["level"]  = 1 + $cmts[$cmt["owner"]]["level"] ; //child so increase the level
                array_push($orderedCmtIds,$id);
            }
        }
    }
    $ret = array();
    foreach ( $orderedCmtIds as $id) {
        array_push($ret, array( 'id' => $id, 'level' => $cmts[$id]['level']));
    }
    if (count($orderErrCmts)) $orderedCmtIds = array_merge($orderedCmtIds,$orderErrCmts);
    return $ret;
}

//sort array by order_by_date for resource (pointer) details
function __sortResourcesByDate($a, $b)
{
    if($a['rst_DisplayOrder'] == $b['rst_DisplayOrder']){
        
        if($a['dty_ID'] == $b['dty_ID']){
            
            if(@$a['order_by_date']==null ||  @$b['order_by_date']==null){
                 return ($a['dtl_ID'] < $b['dtl_ID'])?-1:1;    
            }else{
                 return ($a['order_by_date'] < $b['order_by_date']) ? -1 : 1;
            }
            
            
        }else{
            return ($a['dty_ID'] < $b['dty_ID'])?-1:1;    
        }
        
    }else {
        return (@$a['rst_DisplayOrder']==null || $a['rst_DisplayOrder'] > $b['rst_DisplayOrder'])?1:-1;
    }
}            
?>