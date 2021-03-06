<?php

//TODO: what does this mean???
//MOVE TO H4

/**
* cloneDatabase.php: Copies an entire database verbatim
*                    Note that the cloning method was changed in 2014, using our own SQL dump function, to give more control
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/*
  Cloning workflow:

    1. Create empty database with blankDBStructure.sql
    2. Empty sysIdentification, sysTableLastUpdated, sysUsrGrpLinks, sysUGrps, defLanguages
    3. Copy ALL tables  
    4. reset reginfo
    5. Create indexes and procedures
    6. Copy db folder and update file path in recUploadedFiles

*/

define('MANAGER_REQUIRED', 1);   
define('PDIR','../../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');
require_once(dirname(__FILE__).'/../../../records/index/elasticSearch.php');

//require_once(dirname(__FILE__).'/../../../hsapi/utilities/utils_db_load_script.php');

$user_id = $system->get_user_id(); //keep user id (need to copy current user into cloned db for template cloning)
$mysqli  = $system->get_mysqli();

$templateddb = @$_REQUEST['templatedb'];
$isCloneTemplate = ($templateddb!=null);

if($isCloneTemplate){ //template db must be registered with id less than 21

    $ERROR_REDIR = PDIR.'hclient/framecontent/infoPage.php';

    if(mysql__usedatabase($mysqli, $templateddb)!==true){
        $system->addError(HEURIST_ERROR, "Sorry, could not connect to the database $templateddb. Operation is possible when database to be cloned is on the same server");
        include $ERROR_REDIR;
        exit();
    }

    $dbRegID = $system->get_system('sys_dbRegisteredID', true);
    if(!($dbRegID>0 && $dbRegID<1000)){
        $system->addError(HEURIST_ERROR, "Sorry, the database $templateddb must be registered with an ID less than 1000, indicating a database curated or approved by the Heurist team, to allow cloning through this function. You may also clone any database that you can log into through the Advanced functions under Administration.");
        include $ERROR_REDIR;
        exit();
    }
}else{
    $templateddb = null;
}
?>
<html>
    <head>
        <title>Clone Database</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        
        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

        <!-- jQuery UI -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
        
        <!-- Heurist CSS -->
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
        <link rel="stylesheet" type="text/css" href="<?php echo $cssLink;?>">

        <!-- Heurist JS -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>        

        <style>
            ul {color:#CCC;}
            li {line-height: 20px; list-style:outside square; font-size:9px;}
            li ul {color:#CFE8EF; font-size:9px}
            li span {font-size:11px; color:#000;}
        </style>
    </head>
    <body class="popup">

        <script type="text/javascript">
            //
            // allow only alphanum chars for database name    @todo replace with utils_ui.preventNonAlphaNumeric
            //
            function onKeyPress(event){

                event = event || window.event;
                var charCode = typeof event.which == "number" ? event.which : event.keyCode;
                if (charCode && charCode > 31)
                {
                    var keyChar = String.fromCharCode(charCode);
                    if(!/^[a-zA-Z0-9$_]+$/.test(keyChar)){

                        event.cancelBubble = true;
                        event.returnValue = false;
                        event.preventDefault();
                        if (event.stopPropagation) event.stopPropagation();

                        /* does not work
                        var ele = event.target;
                        var evt = document.createEvent("KeyboardEvent");
                        evt.initKeyEvent("keypress",true, true, window, false, false,false, false, 0, 'A'.charCodeAt(0));
                        ele.dispatchEvent(evt);*/

                        return false;
                    }
                }
                return true;
            }

            function onSubmit(event){
                
                var ele = document.getElementById("targetdbname");
                
                if(ele.value.trim()==''){
                    window.hWin.HEURIST4.msg.showMsgFlash('Define name of new database');
                    return false;
                }else{
                    ele = document.getElementById("submitBtn");
                    ele.disabled = 'disabled';

                    ele = document.getElementById("loading");
                    ele.style.display = "block";
                    ele = document.getElementById("mainform");
                    ele.style.display = "none";

                    showProgress();
                    return true;
                    //document.forms[0].submit();
                }
            }

            function showProgress(){

                var ele = document.getElementById("divProgress");
                if(ele){
                    ele.innerHTML = ele.innerHTML + ".";
                    setTimeout(showProgress, 500);
                }
            }

        </script>


        <div class="banner"><h2>Clone <?php print ($isCloneTemplate?'Template ':'')?>Database</h2></div>

        <div id="page-inner" style="overflow:auto">

        
            <div id="loading" style="display:none">
                <img alt="cloning ..." src="../../../common/images/mini-loading.gif" width="16" height="16" />
                <strong><span id="divProgress">&nbsp; Cloning of database may take a few minutes for large databases </span></strong>
            </div>
            <div id="mainform">

                <?php if(!$isCloneTemplate) { ?>

                <p>
                    This function simply copies the current database <b> <?=HEURIST_DBNAME?> </b> to a new one with no changes. <br />
                    The new database is identical to the old in all respects including users, access and attachments <br />
                    (beware of making many copies of databases containing many large files, as all uploaded files are copied).<br />
                    The target database is unregistered with the Heurist central index even if the source database is registered.
                </p>

                <?php
                
                }
                
                //verify that name of database is unique
                if(@$_REQUEST['mode']=='2'){
                    
                    $targetdbname = $_REQUEST['targetdbname'];

                    // Avoid illegal chars in db name
                    $hasInvalid = preg_match('[\W]', $targetdbname);
                    if ($hasInvalid) {
                        echo ("<p><hr><p>&nbsp;<p>Requested database copy name: <b>$targetdbname</b>".
                            "<p>Sorry, only letters, numbers and underscores (_) are allowed in the database name");

                            $_REQUEST['mode'] = 0;
                            $_REQUEST['targetdbname'] = null;
                            unset($_REQUEST['targetdbname']);
                    } // rejecting illegal characters in db name
                    else{
                        list($targetdbname, $dbname) = mysql__get_names( $targetdbname );
                        
                        $dblist = mysql__select_list2($mysqli, 'show databases');
                        if (array_search(strtolower($targetdbname), array_map('strtolower', $dblist)) !== false ){
                            echo ("<div class='ui-state-error'>Warning: database '".$targetdbname
                                ."' already exists. Please choose a different name<br/></div>");
                            $_REQUEST['mode'] = 0;
                            $_REQUEST['targetdbname'] = null;
                            unset($_REQUEST['targetdbname']);
                        }
                    }
                }
                

                // ---- SPECIFY THE TARGET DATABASE (first pass) -------------------------------------------------------------------

if(@$_REQUEST['mode']!='2' || !@$_REQUEST['targetdbname']){
                    ?>
                    <div class="separator_row" style="margin:20px 0;"></div>
                    <form name='selectdb' action='cloneDatabase.php' method='get' onsubmit="{return onSubmit(event);}">
                        <input name='mode' value='2' type='hidden'> <!-- calls the form to select mappings, step 2 -->
                        <input name='db' value='<?=HEURIST_DBNAME?>' type='hidden'>
                        <?php
                        if($isCloneTemplate){
                            print '<input name="templatedb" value="'.$_REQUEST['templatedb'].'" type="hidden">';
                        }
                        ?>
                        <p>The database will be created with the prefix <b><?=HEURIST_DB_PREFIX?></b>
                            (all databases created by this installation of the software will have the same prefix).</p>
                        <p>
                            <label>No data (copy structure definitions only):&nbsp<input type='checkbox' name='nodata' value="1"/></label>
                        </p>
                        <h3>Enter a name for the cloned database:</h3>
                        <div style="margin-left: 40px;">
                            <input type='text' name='targetdbname' id='targetdbname' size="40" onkeypress="{onKeyPress(event)}"/>
                            <input type='submit' id='submitBtn' 
                                value='Clone "<?=($isCloneTemplate)?$_REQUEST['templatedb']:HEURIST_DBNAME?>"'
                                class="h3button"/>
                        </div>

                    </form>
                </div>
            </div>
        </body>
    </html>
    <?php
    exit;
}

// ---- PROCESS THE COPY FUNCTION (second pass) --------------------------------------------------------------------

if(@$_REQUEST['mode']=='2'){

    $targetdbname = $_REQUEST['targetdbname'];
    $nodata = (@$_REQUEST['nodata']==1);
    $res = cloneDatabase($targetdbname, $nodata, $templateddb, $user_id);
    
    if(!$res){
        echo_flush ('<p style="padding-left:20px;"><h2 style="color:red">WARNING: Your database has not been cloned.</h2>'
        .'Please contact your system administrator or the Heurist developers (support at HeuristNetwork dot org) for assistance with cloning of your database.');
    }
        


    print "</div></body></html>";
}

// ---- COPY FUNCTION -----------------------------------------------------------------

//
// 1. create empty database
// 2. clean default values from some tables
// 3. clean content of all tables
// 4. add contrainsts, procedure and triggers
// 5. remove registration info and assign originID for definitions
//
function cloneDatabase($targetdbname, $nodata=false, $templateddb, $user_id) {
    global $mysqli;
    
    set_time_limit(0);

    $isCloneTemplate = ($templateddb!=null);
    
    list($targetdbname_full, $targetdbname) = mysql__get_names( $targetdbname );

    //create new empty database and structure
    echo_flush ("<p>Create Database Structure (tables)</p>");
    if(!DbUtils::databaseCreate($targetdbname_full, 1)){
        return false;
    }else{
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }
    
    // Connect to new database and  Remove initial values from empty database
    mysql__usedatabase($mysqli, $targetdbname_full);
    $mysqli->query('delete from sysIdentification where 1');
    $mysqli->query('delete from sysTableLastUpdated where 1');
    $mysqli->query('delete from defLanguages where 1');
    
    if($isCloneTemplate){
        $source_database = $templateddb;
        //copy current user from current HEURIST_DBNAME_FULL to cloned database as user#2
        $mysqli->query('update sysUGrps u1, '.HEURIST_DBNAME_FULL.'.sysUGrps u2 SET '
.'u1.ugr_Type=u2.ugr_Type ,u1.ugr_Name=u2.ugr_Name ,u1.ugr_LongName=u2.ugr_LongName ,u1.ugr_Description=u2.ugr_Description '
.',u1.ugr_Password=u2.ugr_Password ,u1.ugr_eMail=u2.ugr_eMail, u1.ugr_FirstName=u2.ugr_FirstName ,u1.ugr_LastName=u2.ugr_LastName '
.',u1.ugr_Department=u2.ugr_Department ,u1.ugr_Organisation=u2.ugr_Organisation ,u1.ugr_City=u2.ugr_City '
.',u1.ugr_State=u2.ugr_State ,u1.ugr_Postcode=u2.ugr_Postcode ,u1.ugr_Interests=u2.ugr_Interests ,u1.ugr_Enabled=u2.ugr_Enabled '
.',u1.ugr_MinHyperlinkWords=u2.ugr_MinHyperlinkWords '
.',u1.ugr_IsModelUser=u2.ugr_IsModelUser ,u1.ugr_IncomingEmailAddresses=u2.ugr_IncomingEmailAddresses '
.',u1.ugr_TargetEmailAddresses=u2.ugr_TargetEmailAddresses ,u1.ugr_URLs=u2.ugr_URLs,u1.ugr_FlagJT=u2.ugr_FlagJT '
        .' where u1.ugr_ID=2 AND u2.ugr_ID='.$user_id);
        
    }else{
        $source_database = HEURIST_DBNAME_FULL;
        $mysqli->query('delete from sysUsrGrpLinks where 1');
        $mysqli->query('delete from sysUGrps where ugr_ID>=0');
    }

    list($source_database_full, $source_database) = mysql__get_names( $source_database );
    

    echo_flush ("<p>Copy data</p>");
    // db_clone function in /common/php/db_utils.php does all the work
    if( DbUtils::databaseClone($source_database_full, $targetdbname_full, true, $nodata, $isCloneTemplate) ){
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }else{
        DbUtils::databaseDrop( false, $targetdbname_full, false, false );
        
        return false;
    }
    
    //cleanup target database to avoid issues with addition of constraints

    //1. cleanup missed trm_InverseTermId
    $mysqli->query('update defTerms t1 left join defTerms t2 on t1.trm_InverseTermId=t2.trm_ID
        set t1.trm_InverseTermId=null
    where t1.trm_ID>0 and t2.trm_ID is NULL');
    
    //2. remove missed recent records
    $mysqli->query('delete FROM usrRecentRecords
        where rre_RecID is not null
    and rre_RecID not in (select rec_ID from Records)');
    
    //3. remove missed rrc_SourceRecID and rrc_TargetRecID
    $mysqli->query('delete FROM recRelationshipsCache
        where rrc_SourceRecID is not null
    and rrc_SourceRecID not in (select rec_ID from Records)');

    $mysqli->query('delete FROM recRelationshipsCache
        where rrc_TargetRecID is not null
    and rrc_TargetRecID not in (select rec_ID from Records)');
    
    //4. cleanup orphaned details
    $mysqli->query('delete FROM recDetails
        where dtl_RecID is not null
    and dtl_RecID not in (select rec_ID from Records)');
    
    //5. cleanup missed references to uploaded files
    $mysqli->query('delete FROM recDetails
        where dtl_UploadedFileID is not null
    and dtl_UploadedFileID not in (select ulf_ID from recUploadedFiles)');

    //6. cleanup missed rec tags links
    $mysqli->query('delete FROM usrRecTagLinks where rtl_TagID not in (select tag_ID from usrTags)');
    $mysqli->query('delete FROM usrRecTagLinks where rtl_RecID not in (select rec_ID from Records)');

    //7. cleanup orphaned bookmarks
    $mysqli->query('delete FROM usrBookmarks where bkm_RecID not in (select rec_ID from Records)');

    
    $sHighLoadWarning = "<p><h4>Note: </h4>Failure to clone a database may result from high server load. Please try again, and if the problem continues contact the Heurist developers at info heuristnetwork dot org</p>";
    
    // 4. add contrainsts, procedure and triggers
    echo_flush ("<p>Addition of Referential Constraints</p>");
    if(db_script($targetdbname_full, HEURIST_DIR."admin/setup/dbcreate/addReferentialConstraints.sql")){
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }else{
        DbUtils::databaseDrop( false, $targetdbname_full, false, false );
        print $sHighLoadWarning;
        return false;
    }

    echo_flush ("<p>Addition of Procedures and Triggers</p>");
    if(db_script($targetdbname_full, HEURIST_DIR."admin/setup/dbcreate/addProceduresTriggers.sql")){
        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
    }else{
        DbUtils::databaseDrop( false, $targetdbname_full, false, false );
        print $sHighLoadWarning;
        return false;
    }    
    
    // 5. remove registration info and assign originID for definitions
    $sourceRegID = mysql__select_value($mysqli, 'select sys_dbRegisteredID from sysIdentification where 1');

    //print "<p>".$sourceRegID."</p>";
    // RESET register db ID and zotero credentials
    $query1 = "update sysIdentification set sys_dbRegisteredID=0, sys_hmlOutputDirectory=null, sys_htmlOutputDirectory=null, sys_SyncDefsWithDB=null, sys_MediaFolders='uploaded_files', sys_eMailImapProtocol='', sys_eMailImapUsername='', sys_dbRights='', sys_NewRecOwnerGrpID=0 where 1";
    $res1 = $mysqli->query($query1);
    if ($mysqli->error)  { //(mysql_num_rows($res1) == 0)
        print "<p><h4>Warning</h4><b>Unable to reset sys_dbRegisteredID in sysIdentification table. (".$mysqli->error.
        ")<br> Please reset the registration ID manually</b></p>";
    }
    //assign origin ID    
    DbUtils::databaseRegister($sourceRegID);

    // Index new database for Elasticsearch
    //TODO: Needs error report, trap error and warn or abort clone
    ElasticSearch::buildAllIndices($targetdbname_full); // ElasticSearch uses full database name including prefix

    // Copy the images and the icons directories
    //TODO: Needs error report, trap error and warn or abort clone

    folderRecurseCopy( HEURIST_FILESTORE_ROOT.$source_database, HEURIST_FILESTORE_ROOT.$targetdbname );

    // Update file path in target database  with absolute paths
    $query1 = "update recUploadedFiles set ulf_FilePath='".HEURIST_FILESTORE_ROOT.$targetdbname.
    "/' where ulf_FilePath='".HEURIST_FILESTORE_ROOT.$source_database."/' and ulf_ID>0";
    $res1 = $mysqli->query($query1);
    if ($mysqli->error)  { //(mysql_num_rows($res1) == 0)
        print "<p><h4>Warning</h4><b>Unable to set database files path to new path</b>".
        "<br>Query was:".$query1.
        "<br>Please get your system administrator to fix this problem BEFORE editing the database (your edits will affect the original database)</p>";
    }

    // Success!
    echo "<hr><p>&nbsp;</p><h2>New database '$targetdbname' created successfully</h2>";
    print "<p>Please access your new database through this link: <a href='".HEURIST_BASE_URL."?db=".$targetdbname.
    "' title='' target=\"_new\"><strong>".$targetdbname."</strong></a></p>";
    
    //SEND EMAIL ABOUT CREATING NEW DB
    $user_record = mysql__select_row_assoc($mysqli, 'select ugr_Name, CONCAT(ugr_FirstName," ",ugr_LastName) as ugr_FullName,'
         .'ugr_Organisation,ugr_eMail,ugr_Interests '
         .' FROM sysUGrps WHERE ugr_ID='.($isCloneTemplate?'2':$user_id));

    if($user_record){
    
            $fullName = $user_record['ugr_FullName'];
            
            // email the system administrator to tell them a new database has been created
            $email_text =
            "There is new Heurist database.\n".
            "Database name: ".$targetdbname_full."\n\n".
            //($cloned_from_db?('Cloned from '.($isCloneTemplate?'template ':'').'database '.$cloned_from_db."\n"):'').
            'The user who created the new database is:'.$user_record['ugr_Name']."\n".
            "Full name:    ".$fullName."\n".
            "Email address: ".$user_record['ugr_eMail']."\n".
            "Organisation:  ".$user_record['ugr_Organisation']."\n".
            "Research interests:  ".$user_record['ugr_Interests']."\n".
            "Go to the address below to review further details:\n".
            HEURIST_BASE_URL."?db=".$targetdbname;

            $email_title = 'New database: '.$targetdbname_full.' by '.$fullName.' ['.$user_record['ugr_eMail'].'] '
                .'Cloned from  '.($isCloneTemplate?'template ':'database ').$source_database_full;

            $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, $email_title, $email_text, null);
    }
    
    return true;
} // straightCopyNewDatabase
?>